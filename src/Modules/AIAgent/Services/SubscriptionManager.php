<?php
/**
 * Subscription Manager Service
 * 
 * Controls feature access and usage limits based on subscription tier.
 * Supports: free, basic, pro, enterprise tiers with configurable features and limits.
 * 
 * @package Forooshyar\Modules\AIAgent\Services
 */

namespace Forooshyar\Modules\AIAgent\Services;

use Forooshyar\Modules\AIAgent\Contracts\SubscriptionInterface;
use Forooshyar\Modules\AIAgent\Exceptions\SubscriptionLimitException;
use Forooshyar\Modules\AIAgent\Exceptions\FeatureDisabledException;

class SubscriptionManager implements SubscriptionInterface
{
    // Feature constants
    const FEATURE_PRODUCT_ANALYSIS = 'product_analysis';
    const FEATURE_CUSTOMER_ANALYSIS = 'customer_analysis';
    const FEATURE_AUTO_ACTIONS = 'auto_actions';
    const FEATURE_SQL_ANALYSIS = 'sql_analysis';
    const FEATURE_ADVANCED_REPORTS = 'advanced_reports';
    const FEATURE_MULTI_LLM = 'multi_llm_providers';

    // Limit type constants
    const LIMIT_ANALYSES_PER_DAY = 'analyses_per_day';
    const LIMIT_ACTIONS_PER_DAY = 'actions_per_day';
    const LIMIT_PRODUCTS_PER_ANALYSIS = 'products_per_analysis';
    const LIMIT_CUSTOMERS_PER_ANALYSIS = 'customers_per_analysis';

    // Tier constants
    const TIER_FREE = 'free';
    const TIER_BASIC = 'basic';
    const TIER_PRO = 'pro';
    const TIER_ENTERPRISE = 'enterprise';

    /** @var SettingsManager */
    private $settings;

    /** @var array */
    private $tiers;

    /** @var array */
    private $usageCache = [];

    /** @var array Feature descriptions for UI */
    private $featureDescriptions = [
        self::FEATURE_PRODUCT_ANALYSIS => 'Analyze product performance and get optimization suggestions',
        self::FEATURE_CUSTOMER_ANALYSIS => 'Analyze customer behavior and lifecycle stages',
        self::FEATURE_AUTO_ACTIONS => 'Automatically execute suggested actions',
        self::FEATURE_SQL_ANALYSIS => 'Advanced SQL-based data analysis',
        self::FEATURE_ADVANCED_REPORTS => 'Detailed analytics and reporting',
        self::FEATURE_MULTI_LLM => 'Use multiple LLM providers',
    ];

    /** @var array Tier display names */
    private $tierNames = [
        self::TIER_FREE => 'Free',
        self::TIER_BASIC => 'Basic',
        self::TIER_PRO => 'Professional',
        self::TIER_ENTERPRISE => 'Enterprise',
    ];

    /**
     * @param SettingsManager $settings
     */
    public function __construct(SettingsManager $settings)
    {
        $this->settings = $settings;
        $this->loadTiers();
    }

    /**
     * Load tier configuration from appConfig
     *
     * @return void
     */
    private function loadTiers()
    {
        $config = appConfig('aiagent', []);
        $this->tiers = isset($config['subscription']['tiers']) ? $config['subscription']['tiers'] : [];
    }

    /**
     * Check if AI Agent module is globally enabled
     * Always returns true - module is always active as long as LLM is configured
     *
     * @return bool
     */
    public function isModuleEnabled()
    {
        // Module is always enabled - no subscription required
        return true;
    }

    /**
     * Check if specific feature is enabled
     * Always returns true - all features are available
     *
     * @param string $feature
     * @return bool
     */
    public function isFeatureEnabled($feature)
    {
        // All features are always enabled
        return true;
    }

    /**
     * Get subscription tier (free, basic, pro, enterprise)
     *
     * @return string
     */
    public function getSubscriptionTier()
    {
        return get_option('aiagent_subscription_tier', 'free');
    }

    /**
     * Set subscription tier
     *
     * @param string $tier
     * @return bool
     */
    public function setSubscriptionTier($tier)
    {
        if (!isset($this->tiers[$tier])) {
            return false;
        }
        return update_option('aiagent_subscription_tier', $tier);
    }

    /**
     * Get all enabled features for current tier
     *
     * @return array
     */
    public function getEnabledFeatures()
    {
        $tier = $this->getSubscriptionTier();
        $tierConfig = $this->getTierConfig($tier);

        if (empty($tierConfig)) {
            return [];
        }

        $features = isset($tierConfig['features']) ? $tierConfig['features'] : [];

        // Enterprise has all features
        if (in_array('*', $features)) {
            return [
                self::FEATURE_PRODUCT_ANALYSIS,
                self::FEATURE_CUSTOMER_ANALYSIS,
                self::FEATURE_AUTO_ACTIONS,
                self::FEATURE_SQL_ANALYSIS,
                self::FEATURE_ADVANCED_REPORTS,
                self::FEATURE_MULTI_LLM,
            ];
        }

        return $features;
    }

    /**
     * Check usage limits (analyses per day, actions per day)
     *
     * @param string $limitType
     * @return array ['allowed' => int, 'used' => int, 'remaining' => int]
     */
    public function checkUsageLimit($limitType)
    {
        $tier = $this->getSubscriptionTier();
        $tierConfig = $this->getTierConfig($tier);
        $limits = isset($tierConfig['limits']) ? $tierConfig['limits'] : [];

        $allowed = isset($limits[$limitType]) ? $limits[$limitType] : 0;
        $used = $this->getUsageCount($limitType);

        // -1 means unlimited
        if ($allowed === -1) {
            return [
                'allowed' => -1,
                'used' => $used,
                'remaining' => -1,
            ];
        }

        return [
            'allowed' => $allowed,
            'used' => $used,
            'remaining' => max(0, $allowed - $used),
        ];
    }

    /**
     * Check if usage limit is exceeded
     * Always returns false - no limits
     *
     * @param string $limitType
     * @return bool
     */
    public function isLimitExceeded($limitType)
    {
        // No limits - always allow
        return false;
    }

    /**
     * Increment usage counter
     *
     * @param string $limitType
     * @param int $count
     * @return void
     */
    public function incrementUsage($limitType, $count = 1)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'forooshyar_aiagent_usage';
        $today = current_time('Y-m-d');

        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT count FROM {$table} WHERE usage_type = %s AND usage_date = %s",
            $limitType,
            $today
        ));

        if ($existing !== null) {
            $wpdb->update(
                $table,
                ['count' => $existing + $count],
                ['usage_type' => $limitType, 'usage_date' => $today],
                ['%d'],
                ['%s', '%s']
            );
        } else {
            $wpdb->insert(
                $table,
                [
                    'usage_type' => $limitType,
                    'usage_date' => $today,
                    'count' => $count,
                ],
                ['%s', '%s', '%d']
            );
        }

        // Clear cache
        unset($this->usageCache[$limitType]);
    }

    /**
     * Get usage count for today
     *
     * @param string $limitType
     * @return int
     */
    private function getUsageCount($limitType)
    {
        if (isset($this->usageCache[$limitType])) {
            return $this->usageCache[$limitType];
        }

        global $wpdb;
        $table = $wpdb->prefix . 'forooshyar_aiagent_usage';
        $today = current_time('Y-m-d');

        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT count FROM {$table} WHERE usage_type = %s AND usage_date = %s",
            $limitType,
            $today
        ));

        $this->usageCache[$limitType] = (int) $count;
        return $this->usageCache[$limitType];
    }

    /**
     * Get tier configuration
     *
     * @param string $tier
     * @return array
     */
    public function getTierConfig($tier)
    {
        return isset($this->tiers[$tier]) ? $this->tiers[$tier] : [];
    }

    /**
     * Get all tiers
     *
     * @return array
     */
    public function getAllTiers()
    {
        return $this->tiers;
    }

    /**
     * Check if LLM provider is allowed for current tier
     *
     * @param string $provider
     * @return bool
     */
    public function isProviderAllowed($provider)
    {
        $tier = $this->getSubscriptionTier();
        $tierConfig = $this->getTierConfig($tier);
        $providers = isset($tierConfig['llm_providers']) ? $tierConfig['llm_providers'] : [];

        if (in_array('*', $providers)) {
            return true;
        }

        return in_array($provider, $providers);
    }

    /**
     * Get allowed LLM providers for current tier
     *
     * @return array
     */
    public function getAllowedProviders()
    {
        $tier = $this->getSubscriptionTier();
        $tierConfig = $this->getTierConfig($tier);
        $providers = isset($tierConfig['llm_providers']) ? $tierConfig['llm_providers'] : [];

        if (in_array('*', $providers)) {
            return ['ollama', 'openai', 'anthropic'];
        }

        return $providers;
    }

    /**
     * Get limit value for current tier
     *
     * @param string $limitType
     * @return int
     */
    public function getLimit($limitType)
    {
        $tier = $this->getSubscriptionTier();
        $tierConfig = $this->getTierConfig($tier);
        $limits = isset($tierConfig['limits']) ? $tierConfig['limits'] : [];

        return isset($limits[$limitType]) ? $limits[$limitType] : 0;
    }

    /**
     * Require a feature to be enabled, throw exception if not
     *
     * @param string $feature
     * @throws FeatureDisabledException
     * @return void
     */
    public function requireFeature($feature)
    {
        if (!$this->isFeatureEnabled($feature)) {
            $featureName = isset($this->featureDescriptions[$feature]) 
                ? $this->featureDescriptions[$feature] 
                : $feature;
            throw new FeatureDisabledException(
                sprintf('Feature "%s" is not available in your subscription tier.', $featureName)
            );
        }
    }

    /**
     * Require usage limit not exceeded, throw exception if exceeded
     *
     * @param string $limitType
     * @throws SubscriptionLimitException
     * @return void
     */
    public function requireWithinLimit($limitType)
    {
        if ($this->isLimitExceeded($limitType)) {
            $usage = $this->checkUsageLimit($limitType);
            throw new SubscriptionLimitException(
                sprintf(
                    'Usage limit exceeded for %s. Used: %d, Allowed: %d',
                    $limitType,
                    $usage['used'],
                    $usage['allowed']
                )
            );
        }
    }

    /**
     * Get feature description
     *
     * @param string $feature
     * @return string
     */
    public function getFeatureDescription($feature)
    {
        return isset($this->featureDescriptions[$feature]) 
            ? $this->featureDescriptions[$feature] 
            : $feature;
    }

    /**
     * Get all feature descriptions
     *
     * @return array
     */
    public function getAllFeatureDescriptions()
    {
        return $this->featureDescriptions;
    }

    /**
     * Get tier display name
     *
     * @param string|null $tier
     * @return string
     */
    public function getTierDisplayName($tier = null)
    {
        if ($tier === null) {
            $tier = $this->getSubscriptionTier();
        }
        return isset($this->tierNames[$tier]) ? $this->tierNames[$tier] : ucfirst($tier);
    }

    /**
     * Get all tier names
     *
     * @return array
     */
    public function getAllTierNames()
    {
        return $this->tierNames;
    }

    /**
     * Get features comparison for upgrade UI
     *
     * @return array
     */
    public function getFeaturesComparison()
    {
        $comparison = [];
        $allFeatures = array_keys($this->featureDescriptions);

        foreach ($this->tiers as $tierKey => $tierConfig) {
            $tierFeatures = isset($tierConfig['features']) ? $tierConfig['features'] : [];
            $hasAll = in_array('*', $tierFeatures);

            $comparison[$tierKey] = [
                'name' => $this->getTierDisplayName($tierKey),
                'features' => [],
                'limits' => isset($tierConfig['limits']) ? $tierConfig['limits'] : [],
                'providers' => isset($tierConfig['llm_providers']) ? $tierConfig['llm_providers'] : [],
            ];

            foreach ($allFeatures as $feature) {
                $comparison[$tierKey]['features'][$feature] = $hasAll || in_array($feature, $tierFeatures);
            }
        }

        return $comparison;
    }

    /**
     * Get upgrade suggestions based on current usage
     *
     * @return array
     */
    public function getUpgradeSuggestions()
    {
        $suggestions = [];
        $currentTier = $this->getSubscriptionTier();
        $tierOrder = [self::TIER_FREE, self::TIER_BASIC, self::TIER_PRO, self::TIER_ENTERPRISE];
        $currentIndex = array_search($currentTier, $tierOrder);

        if ($currentIndex === false || $currentIndex >= count($tierOrder) - 1) {
            return $suggestions;
        }

        // Check if approaching limits
        $limitTypes = [
            self::LIMIT_ANALYSES_PER_DAY,
            self::LIMIT_ACTIONS_PER_DAY,
        ];

        foreach ($limitTypes as $limitType) {
            $usage = $this->checkUsageLimit($limitType);
            if ($usage['allowed'] > 0 && $usage['remaining'] <= ($usage['allowed'] * 0.2)) {
                $nextTier = $tierOrder[$currentIndex + 1];
                $nextLimit = $this->getLimitForTier($limitType, $nextTier);
                
                $suggestions[] = [
                    'reason' => 'limit_approaching',
                    'limit_type' => $limitType,
                    'current_limit' => $usage['allowed'],
                    'used' => $usage['used'],
                    'suggested_tier' => $nextTier,
                    'new_limit' => $nextLimit,
                ];
            }
        }

        return $suggestions;
    }

    /**
     * Get limit for a specific tier
     *
     * @param string $limitType
     * @param string $tier
     * @return int
     */
    public function getLimitForTier($limitType, $tier)
    {
        $tierConfig = $this->getTierConfig($tier);
        $limits = isset($tierConfig['limits']) ? $tierConfig['limits'] : [];
        return isset($limits[$limitType]) ? $limits[$limitType] : 0;
    }

    /**
     * Get usage statistics for dashboard
     *
     * @return array
     */
    public function getUsageStatistics()
    {
        return [
            'tier' => $this->getSubscriptionTier(),
            'tier_name' => $this->getTierDisplayName(),
            'features' => $this->getEnabledFeatures(),
            'limits' => [
                'analyses' => $this->checkUsageLimit(self::LIMIT_ANALYSES_PER_DAY),
                'actions' => $this->checkUsageLimit(self::LIMIT_ACTIONS_PER_DAY),
            ],
            'providers' => $this->getAllowedProviders(),
        ];
    }

    /**
     * Get usage history for a period
     *
     * @param int $days
     * @return array
     */
    public function getUsageHistory($days = 30)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'forooshyar_aiagent_usage';
        $startDate = date('Y-m-d', strtotime("-{$days} days"));

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT usage_type, usage_date, count 
             FROM {$table} 
             WHERE usage_date >= %s 
             ORDER BY usage_date ASC",
            $startDate
        ), ARRAY_A);

        $history = [];
        foreach ($results as $row) {
            $date = $row['usage_date'];
            if (!isset($history[$date])) {
                $history[$date] = [];
            }
            $history[$date][$row['usage_type']] = (int) $row['count'];
        }

        return $history;
    }

    /**
     * Reset daily usage counters (for testing or admin override)
     *
     * @return bool
     */
    public function resetDailyUsage()
    {
        global $wpdb;
        $table = $wpdb->prefix . 'forooshyar_aiagent_usage';
        $today = current_time('Y-m-d');

        $result = $wpdb->delete(
            $table,
            ['usage_date' => $today],
            ['%s']
        );

        $this->usageCache = [];

        return $result !== false;
    }

    /**
     * Check if module can perform an action
     *
     * @param string $feature Required feature
     * @param string|null $limitType Limit to check
     * @return array ['allowed' => bool, 'reason' => string|null]
     */
    public function canPerformAction($feature, $limitType = null)
    {
        // Check if module is enabled
        if (!$this->isModuleEnabled()) {
            return [
                'allowed' => false,
                'reason' => 'Module is not enabled',
            ];
        }

        // Check feature
        if (!$this->isFeatureEnabled($feature)) {
            return [
                'allowed' => false,
                'reason' => sprintf('Feature "%s" is not available in your subscription', $feature),
            ];
        }

        // Check limit if specified
        if ($limitType !== null && $this->isLimitExceeded($limitType)) {
            $usage = $this->checkUsageLimit($limitType);
            return [
                'allowed' => false,
                'reason' => sprintf(
                    'Daily limit exceeded (%d/%d)',
                    $usage['used'],
                    $usage['allowed']
                ),
            ];
        }

        return [
            'allowed' => true,
            'reason' => null,
        ];
    }

    /**
     * Get subscription status for admin display
     *
     * @return array
     */
    public function getSubscriptionStatus()
    {
        $tier = $this->getSubscriptionTier();
        $tierConfig = $this->getTierConfig($tier);

        return [
            'enabled' => $this->isModuleEnabled(),
            'tier' => $tier,
            'tier_name' => $this->getTierDisplayName($tier),
            'features' => $this->getEnabledFeatures(),
            'feature_details' => array_map(function ($feature) {
                return [
                    'name' => $feature,
                    'description' => $this->getFeatureDescription($feature),
                    'enabled' => $this->isFeatureEnabled($feature),
                ];
            }, array_keys($this->featureDescriptions)),
            'limits' => isset($tierConfig['limits']) ? $tierConfig['limits'] : [],
            'usage' => [
                'analyses' => $this->checkUsageLimit(self::LIMIT_ANALYSES_PER_DAY),
                'actions' => $this->checkUsageLimit(self::LIMIT_ACTIONS_PER_DAY),
            ],
            'providers' => $this->getAllowedProviders(),
            'upgrade_suggestions' => $this->getUpgradeSuggestions(),
        ];
    }

    /**
     * Apply filters to subscription features (for extensibility)
     *
     * @param array $features
     * @param string $tier
     * @return array
     */
    private function applyFeatureFilters($features, $tier)
    {
        return apply_filters('aiagent_subscription_features', $features, $tier);
    }

    /**
     * Apply filters to usage limits (for extensibility)
     *
     * @param int $limit
     * @param string $type
     * @param string $tier
     * @return int
     */
    private function applyLimitFilters($limit, $type, $tier)
    {
        return apply_filters('aiagent_usage_limit', $limit, $type, $tier);
    }

    /**
     * Clear usage cache
     *
     * @return void
     */
    public function clearCache()
    {
        $this->usageCache = [];
    }
}
