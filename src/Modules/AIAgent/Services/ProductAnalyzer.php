<?php
/**
 * Product Analyzer Service
 * 
 * @package Forooshyar\Modules\AIAgent\Services
 */

namespace Forooshyar\Modules\AIAgent\Services;

use Forooshyar\Modules\AIAgent\Contracts\AnalyzerInterface;
use Forooshyar\Modules\AIAgent\Contracts\LLMProviderInterface;

class ProductAnalyzer implements AnalyzerInterface
{
    /** @var LLMProviderInterface */
    private $llm;

    /** @var DatabaseService */
    private $database;

    /** @var SettingsManager */
    private $settings;

    /** @var Logger */
    private $logger;

    /**
     * @param LLMProviderInterface $llm
     * @param DatabaseService $database
     * @param SettingsManager $settings
     * @param Logger $logger
     */
    public function __construct(
        LLMProviderInterface $llm,
        DatabaseService $database,
        SettingsManager $settings,
        Logger $logger
    ) {
        $this->llm = $llm;
        $this->database = $database;
        $this->settings = $settings;
        $this->logger = $logger;
    }

    /**
     * Run analysis on products
     *
     * @param array $options Analysis options
     * @return array Analysis results
     */
    public function analyze(array $options = [])
    {
        $limit = isset($options['limit']) ? $options['limit'] : 50;
        $products = $this->getEntities($limit);

        appLogger("[AIAgent] Starting product analysis - Found " . count($products) . " products to analyze");

        $results = [
            'type' => 'product_analysis',
            'total' => count($products),
            'analyzed' => 0,
            'suggestions' => [],
            'errors' => [],
        ];

        foreach ($products as $product) {
            try {
                $analysis = $this->analyzeEntity($product['id']);
                if ($analysis['success']) {
                    $results['analyzed']++;
                    if (!empty($analysis['suggestions'])) {
                        $results['suggestions'] = array_merge($results['suggestions'], $analysis['suggestions']);
                    }
                    appLogger("[AIAgent] Product {$product['id']} analyzed successfully");
                } else {
                    // Capture failed analysis (not exception, but success=false)
                    $errorMsg = isset($analysis['error']) ? $analysis['error'] : __('خطای ناشناخته', 'forooshyar');
                    $results['errors'][] = [
                        'product_id' => $product['id'],
                        'error' => $errorMsg,
                    ];
                    appLogger("[AIAgent] Product {$product['id']} analysis failed: {$errorMsg}");
                    $this->logger->warning('Product analysis returned failure', [
                        'product_id' => $product['id'],
                        'error' => $errorMsg,
                    ]);
                }
            } catch (\Exception $e) {
                $results['errors'][] = [
                    'product_id' => $product['id'],
                    'error' => $e->getMessage(),
                ];
                appLogger("[AIAgent] Product {$product['id']} threw exception: " . $e->getMessage());
                $this->logger->error('Product analysis failed', [
                    'product_id' => $product['id'],
                    'error' => $e->getMessage(),
                ]);
            }
        }

        appLogger("[AIAgent] Product analysis complete - Analyzed: {$results['analyzed']}/{$results['total']}, Errors: " . count($results['errors']));

        return $results;
    }

    /**
     * Analyze a single product
     *
     * @param int $entityId
     * @return array
     */
    public function analyzeEntity($entityId)
    {
        appLogger("[AIAgent] Starting analysis for product ID: {$entityId}");
        
        $product = wc_get_product($entityId);
        if (!$product) {
            appLogger("[AIAgent] Product not found: {$entityId}");
            return ['success' => false, 'error' => __('محصول یافت نشد', 'forooshyar')];
        }

        appLogger("[AIAgent] Product found: {$product->get_name()}");
        
        $productData = $this->getProductData($product);
        $messages = $this->buildPrompt($productData);

        $this->logger->logPrompt($messages, $this->llm->getProviderName());

        appLogger("[AIAgent] Calling LLM for product: {$entityId}");
        
        $startTime = microtime(true);
        $response = $this->llm->call($messages);
        $duration = (microtime(true) - $startTime) * 1000;

        $this->logger->logResponse($response, $this->llm->getProviderName());

        if (!$response['success']) {
            appLogger("[AIAgent] LLM call failed for product {$entityId}: " . ($response['error'] ?? 'Unknown error'));
            return ['success' => false, 'error' => $response['error']];
        }

        appLogger("[AIAgent] LLM call successful for product {$entityId}, parsing response...");
        
        $parsed = $this->parseResponse($response);
        
        appLogger("[AIAgent] Parsed response - Analysis: " . substr($parsed['analysis'], 0, 200));
        appLogger("[AIAgent] Parsed response - Suggestions count: " . count($parsed['suggestions']));

        // Save to database
        $analysisId = $this->database->saveAnalysis([
            'analysis_type' => 'product_analysis',
            'entity_id' => $entityId,
            'entity_type' => 'product',
            'analysis_data' => $parsed['analysis'],
            'suggestions' => $parsed['suggestions'],
            'priority_score' => $parsed['priority_score'],
            'llm_provider' => $this->llm->getProviderName(),
            'llm_model' => $this->settings->get('llm_model'),
            'tokens_used' => isset($response['data']['tokens']) ? $response['data']['tokens'] : 0,
            'duration_ms' => round($duration),
        ]);

        appLogger("[AIAgent] Analysis saved to database with ID: " . ($analysisId ?: 'FAILED'));

        return [
            'success' => true,
            'id' => $analysisId,
            'analysis' => $parsed['analysis'],
            'suggestions' => $parsed['suggestions'],
            'priority_score' => $parsed['priority_score'],
        ];
    }

    /**
     * Get products to analyze
     *
     * @param int $limit
     * @return array
     */
    public function getEntities($limit)
    {
        $args = apply_filters('aiagent_products_query', [
            'status' => 'publish',
            'limit' => $limit,
            'orderby' => 'date',
            'order' => 'DESC',
            'return' => 'ids',
        ]);

        $productIds = wc_get_products($args);

        $products = [];
        foreach ($productIds as $id) {
            $products[] = ['id' => $id];
        }

        return $products;
    }

    /**
     * Get product data for analysis
     *
     * @param \WC_Product $product
     * @return array
     */
    private function getProductData($product)
    {
        $data = [
            'id' => $product->get_id(),
            'name' => $product->get_name(),
            'type' => $product->get_type(),
            'status' => $product->get_status(),
            'price' => $product->get_price(),
            'regular_price' => $product->get_regular_price(),
            'sale_price' => $product->get_sale_price(),
            'stock_status' => $product->get_stock_status(),
            'stock_quantity' => $product->get_stock_quantity(),
            'total_sales' => $product->get_total_sales(),
            'average_rating' => $product->get_average_rating(),
            'review_count' => $product->get_review_count(),
            'categories' => wp_get_post_terms($product->get_id(), 'product_cat', ['fields' => 'names']),
            'date_created' => $product->get_date_created() ? $product->get_date_created()->format('Y-m-d') : null,
        ];

        // Get recent orders for this product
        $data['recent_orders'] = $this->getProductOrderStats($product->get_id());

        return $data;
    }

    /**
     * Get order statistics for a product
     *
     * @param int $productId
     * @return array
     */
    private function getProductOrderStats($productId)
    {
        global $wpdb;

        $thirtyDaysAgo = date('Y-m-d', strtotime('-30 days'));

        $stats = $wpdb->get_row($wpdb->prepare("
            SELECT 
                COUNT(DISTINCT oi.order_id) as order_count,
                SUM(oim.meta_value) as total_quantity,
                SUM(oim2.meta_value) as total_revenue
            FROM {$wpdb->prefix}woocommerce_order_items oi
            JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim ON oi.order_item_id = oim.order_item_id AND oim.meta_key = '_qty'
            JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim2 ON oi.order_item_id = oim2.order_item_id AND oim2.meta_key = '_line_total'
            JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim3 ON oi.order_item_id = oim3.order_item_id AND oim3.meta_key = '_product_id'
            JOIN {$wpdb->posts} p ON oi.order_id = p.ID
            WHERE oim3.meta_value = %d
            AND p.post_date >= %s
            AND p.post_status IN ('wc-completed', 'wc-processing')
        ", $productId, $thirtyDaysAgo), ARRAY_A);

        return [
            'orders_30d' => isset($stats['order_count']) ? (int) $stats['order_count'] : 0,
            'quantity_30d' => isset($stats['total_quantity']) ? (int) $stats['total_quantity'] : 0,
            'revenue_30d' => isset($stats['total_revenue']) ? (float) $stats['total_revenue'] : 0,
        ];
    }

    /**
     * Build prompt for LLM
     *
     * @param array $entityData
     * @return array Messages array for LLM
     */
    public function buildPrompt(array $entityData)
    {
        $systemPrompt = $this->getSystemPrompt();
        $userPrompt = $this->getUserPrompt($entityData);

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $userPrompt],
        ];

        return apply_filters('aiagent_llm_prompt', $messages, 'product_analysis', $entityData);
    }

    /**
     * Get system prompt
     *
     * @return string
     */
    private function getSystemPrompt()
    {
        return "You are an expert WooCommerce sales optimization AI agent. Analyze product data and provide actionable suggestions to improve sales performance.

Your response MUST be valid JSON with this structure:
{
    \"analysis\": \"Brief analysis summary\",
    \"priority_score\": 1-100,
    \"suggestions\": [
        {
            \"type\": \"action_type\",
            \"priority\": 1-100,
            \"data\": {},
            \"reasoning\": \"Why this action is recommended\"
        }
    ]
}

Available action types: send_email, create_discount, update_product, create_campaign, schedule_followup, create_bundle, inventory_alert, schedule_price_change

Priority guidelines:
- 90-100: Critical - immediate action required
- 70-89: High - significant opportunity
- 50-69: Medium - worth implementing
- 30-49: Low - minor optimization
- 1-29: Minimal impact";
    }

    /**
     * Get user prompt with product data
     *
     * @param array $data
     * @return string
     */
    private function getUserPrompt(array $data)
    {
        return "Analyze this WooCommerce product and suggest optimization actions:

Product: {$data['name']} (ID: {$data['id']})
Type: {$data['type']}
Price: {$data['price']} (Regular: {$data['regular_price']}, Sale: {$data['sale_price']})
Stock: {$data['stock_status']} (Qty: {$data['stock_quantity']})
Total Sales: {$data['total_sales']}
Rating: {$data['average_rating']} ({$data['review_count']} reviews)
Categories: " . implode(', ', $data['categories']) . "
Created: {$data['date_created']}

Last 30 Days Performance:
- Orders: {$data['recent_orders']['orders_30d']}
- Quantity Sold: {$data['recent_orders']['quantity_30d']}
- Revenue: {$data['recent_orders']['revenue_30d']}

Provide analysis and actionable suggestions in JSON format.";
    }

    /**
     * Parse LLM response
     *
     * @param array $response
     * @return array Parsed analysis data
     */
    public function parseResponse(array $response)
    {
        $content = isset($response['data']['content']) ? $response['data']['content'] : '';

        // Try to extract JSON from response
        $json = $this->extractJson($content);

        if ($json === null) {
            return [
                'analysis' => $content,
                'suggestions' => [],
                'priority_score' => 50,
            ];
        }

        return [
            'analysis' => isset($json['analysis']) ? $json['analysis'] : '',
            'suggestions' => isset($json['suggestions']) ? $json['suggestions'] : [],
            'priority_score' => isset($json['priority_score']) ? (int) $json['priority_score'] : 50,
        ];
    }

    /**
     * Extract JSON from response content
     *
     * @param string $content
     * @return array|null
     */
    private function extractJson($content)
    {
        // Try direct decode
        $decoded = json_decode($content, true);
        if ($decoded !== null) {
            return $decoded;
        }

        // Try to find JSON in content
        if (preg_match('/\{[\s\S]*\}/', $content, $matches)) {
            $decoded = json_decode($matches[0], true);
            if ($decoded !== null) {
                return $decoded;
            }
        }

        return null;
    }
}
