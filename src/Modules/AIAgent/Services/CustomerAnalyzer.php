<?php
/**
 * Customer Analyzer Service
 * 
 * @package Forooshyar\Modules\AIAgent\Services
 */

namespace Forooshyar\Modules\AIAgent\Services;

use Forooshyar\Modules\AIAgent\Contracts\AnalyzerInterface;
use Forooshyar\Modules\AIAgent\Contracts\LLMProviderInterface;

class CustomerAnalyzer implements AnalyzerInterface
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
     * Run analysis on customers
     *
     * @param array $options Analysis options
     * @return array Analysis results
     */
    public function analyze(array $options = [])
    {
        $limit = isset($options['limit']) ? $options['limit'] : 100;
        $customers = $this->getEntities($limit);

        $results = [
            'type' => 'customer_analysis',
            'total' => count($customers),
            'analyzed' => 0,
            'suggestions' => [],
            'errors' => [],
        ];

        foreach ($customers as $customer) {
            try {
                $analysis = $this->analyzeEntity($customer['id']);
                if ($analysis['success']) {
                    $results['analyzed']++;
                    if (!empty($analysis['suggestions'])) {
                        $results['suggestions'] = array_merge($results['suggestions'], $analysis['suggestions']);
                    }
                }
            } catch (\Exception $e) {
                $results['errors'][] = [
                    'customer_id' => $customer['id'],
                    'error' => $e->getMessage(),
                ];
                $this->logger->error('Customer analysis failed', [
                    'customer_id' => $customer['id'],
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $results;
    }

    /**
     * Analyze a single customer
     *
     * @param int $entityId
     * @return array
     */
    public function analyzeEntity($entityId)
    {
        $customer = new \WC_Customer($entityId);
        if (!$customer->get_id()) {
            return ['success' => false, 'error' => 'Customer not found'];
        }

        $customerData = $this->getCustomerData($customer);
        $messages = $this->buildPrompt($customerData);

        $this->logger->logPrompt($messages, $this->llm->getProviderName());

        $startTime = microtime(true);
        $response = $this->llm->call($messages);
        $duration = (microtime(true) - $startTime) * 1000;

        $this->logger->logResponse($response, $this->llm->getProviderName());

        if (!$response['success']) {
            return ['success' => false, 'error' => $response['error']];
        }

        $parsed = $this->parseResponse($response);

        // Save to database
        $analysisId = $this->database->saveAnalysis([
            'analysis_type' => 'customer_analysis',
            'entity_id' => $entityId,
            'entity_type' => 'customer',
            'analysis_data' => $parsed['analysis'],
            'suggestions' => $parsed['suggestions'],
            'priority_score' => $parsed['priority_score'],
            'llm_provider' => $this->llm->getProviderName(),
            'llm_model' => $this->settings->get('llm_model'),
            'tokens_used' => isset($response['data']['tokens']) ? $response['data']['tokens'] : 0,
            'duration_ms' => round($duration),
        ]);

        return [
            'success' => true,
            'id' => $analysisId,
            'analysis' => $parsed['analysis'],
            'suggestions' => $parsed['suggestions'],
            'priority_score' => $parsed['priority_score'],
        ];
    }

    /**
     * Get customers to analyze
     *
     * @param int $limit
     * @return array
     */
    public function getEntities($limit)
    {
        $args = apply_filters('aiagent_customers_query', [
            'role' => 'customer',
            'number' => $limit,
            'orderby' => 'registered',
            'order' => 'DESC',
            'fields' => ['ID'],
        ]);

        $users = get_users($args);

        $customers = [];
        foreach ($users as $user) {
            $customers[] = ['id' => $user->ID];
        }

        return $customers;
    }

    /**
     * Get customer data for analysis
     *
     * @param \WC_Customer $customer
     * @return array
     */
    private function getCustomerData($customer)
    {
        $data = [
            'id' => $customer->get_id(),
            'email' => $customer->get_email(),
            'first_name' => $customer->get_first_name(),
            'last_name' => $customer->get_last_name(),
            'date_created' => $customer->get_date_created() ? $customer->get_date_created()->format('Y-m-d') : null,
            'total_spent' => $customer->get_total_spent(),
            'order_count' => $customer->get_order_count(),
        ];

        // Get order history and behavior
        $data['order_stats'] = $this->getCustomerOrderStats($customer->get_id());
        $data['segment'] = $this->determineCustomerSegment($data);

        return $data;
    }

    /**
     * Get order statistics for a customer
     *
     * @param int $customerId
     * @return array
     */
    private function getCustomerOrderStats($customerId)
    {
        global $wpdb;

        // Get last order date
        $lastOrder = $wpdb->get_var($wpdb->prepare("
            SELECT MAX(p.post_date) 
            FROM {$wpdb->posts} p
            JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_customer_user'
            WHERE pm.meta_value = %d
            AND p.post_type = 'shop_order'
            AND p.post_status IN ('wc-completed', 'wc-processing')
        ", $customerId));

        // Get average order value
        $avgOrder = $wpdb->get_var($wpdb->prepare("
            SELECT AVG(pm.meta_value)
            FROM {$wpdb->posts} p
            JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_order_total'
            JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = '_customer_user'
            WHERE pm2.meta_value = %d
            AND p.post_type = 'shop_order'
            AND p.post_status IN ('wc-completed', 'wc-processing')
        ", $customerId));

        // Get orders in last 90 days
        $ninetyDaysAgo = date('Y-m-d', strtotime('-90 days'));
        $recentOrders = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*)
            FROM {$wpdb->posts} p
            JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_customer_user'
            WHERE pm.meta_value = %d
            AND p.post_type = 'shop_order'
            AND p.post_status IN ('wc-completed', 'wc-processing')
            AND p.post_date >= %s
        ", $customerId, $ninetyDaysAgo));

        $daysSinceLastOrder = $lastOrder ? floor((time() - strtotime($lastOrder)) / 86400) : null;

        return [
            'last_order_date' => $lastOrder,
            'days_since_last_order' => $daysSinceLastOrder,
            'average_order_value' => $avgOrder ? round((float) $avgOrder, 2) : 0,
            'orders_last_90_days' => (int) $recentOrders,
        ];
    }

    /**
     * Determine customer segment
     *
     * @param array $data
     * @return string
     */
    private function determineCustomerSegment(array $data)
    {
        $orderCount = (int) $data['order_count'];
        $totalSpent = (float) $data['total_spent'];
        $daysSinceLastOrder = $data['order_stats']['days_since_last_order'];

        // New customer (1 order or less)
        if ($orderCount <= 1) {
            return 'new';
        }

        // VIP (high value)
        if ($totalSpent > 500 || $orderCount > 10) {
            return 'vip';
        }

        // Dormant (no orders in 180+ days)
        if ($daysSinceLastOrder !== null && $daysSinceLastOrder > 180) {
            return 'dormant';
        }

        // At risk (no orders in 60-180 days)
        if ($daysSinceLastOrder !== null && $daysSinceLastOrder > 60) {
            return 'at_risk';
        }

        // Active
        return 'active';
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

        return apply_filters('aiagent_llm_prompt', $messages, 'customer_analysis', $entityData);
    }

    /**
     * Get system prompt
     *
     * @return string
     */
    private function getSystemPrompt()
    {
        return "You are an expert WooCommerce customer lifecycle optimization AI agent. Analyze customer data and provide actionable suggestions to maximize customer lifetime value.

Your response MUST be valid JSON with this structure:
{
    \"analysis\": \"Brief analysis summary\",
    \"priority_score\": 1-100,
    \"suggestions\": [
        {
            \"type\": \"action_type\",
            \"priority\": 1-100,
            \"data\": {\"customer_id\": ID, ...},
            \"reasoning\": \"Why this action is recommended\"
        }
    ]
}

Available action types: send_email, create_discount, schedule_followup, loyalty_reward, create_campaign

Customer segments:
- new: First-time buyers needing onboarding
- active: Regular buyers for retention
- vip: High-value customers for premium treatment
- at_risk: Declining engagement, needs reactivation
- dormant: Inactive, needs winback campaign

Priority guidelines:
- 90-100: Critical - immediate action required
- 70-89: High - significant opportunity
- 50-69: Medium - worth implementing
- 30-49: Low - minor optimization
- 1-29: Minimal impact";
    }

    /**
     * Get user prompt with customer data
     *
     * @param array $data
     * @return string
     */
    private function getUserPrompt(array $data)
    {
        return "Analyze this WooCommerce customer and suggest engagement actions:

Customer ID: {$data['id']}
Name: {$data['first_name']} {$data['last_name']}
Registered: {$data['date_created']}
Segment: {$data['segment']}

Purchase History:
- Total Orders: {$data['order_count']}
- Total Spent: {$data['total_spent']}
- Average Order Value: {$data['order_stats']['average_order_value']}
- Last Order: {$data['order_stats']['last_order_date']}
- Days Since Last Order: {$data['order_stats']['days_since_last_order']}
- Orders (Last 90 Days): {$data['order_stats']['orders_last_90_days']}

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
