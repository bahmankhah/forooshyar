<?php
/**
 * Analysis Job Manager
 * 
 * مدیریت کارهای تحلیل به صورت پایدار با ذخیره‌سازی در دیتابیس
 * این سیستم از WordPress Cron و Heartbeat API برای اطمینان از ادامه کار استفاده می‌کند
 * 
 * @package Forooshyar\Modules\AIAgent\Services
 */

namespace Forooshyar\Modules\AIAgent\Services;

use function Forooshyar\WPLite\appLogger;

class AnalysisJobManager
{
    const OPTION_JOB_STATE = 'aiagent_analysis_job';
    const CRON_HOOK = 'aiagent_process_analysis_job';
    const HEARTBEAT_HOOK = 'aiagent_job_heartbeat';
    const JOB_TABLE = 'aiagent_jobs';
    
    const STATUS_IDLE = 'idle';
    const STATUS_RUNNING = 'running';
    const STATUS_PAUSED = 'paused';
    const STATUS_CANCELLING = 'cancelling';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';
    const STATUS_CANCELLED = 'cancelled';

    /** @var ProductAnalyzer */
    private $productAnalyzer;

    /** @var CustomerAnalyzer */
    private $customerAnalyzer;

    /** @var SubscriptionManager */
    private $subscription;

    /** @var SettingsManager */
    private $settings;

    /** @var DatabaseService */
    private $database;

    /** @var ActionExecutor|null */
    private $actionExecutor;

    /** @var int حداکثر زمان اجرای یک batch (ثانیه) */
    const MAX_BATCH_EXECUTION_TIME = 25;
    
    /** @var int حداکثر زمان بدون فعالیت قبل از تلقی به عنوان متوقف شده (ثانیه) */
    const STALE_JOB_THRESHOLD = 120;

    /**
     * @param ProductAnalyzer $productAnalyzer
     * @param CustomerAnalyzer $customerAnalyzer
     * @param SubscriptionManager $subscription
     * @param SettingsManager $settings
     * @param DatabaseService $database
     * @param ActionExecutor|null $actionExecutor
     */
    public function __construct(
        ProductAnalyzer $productAnalyzer,
        CustomerAnalyzer $customerAnalyzer,
        SubscriptionManager $subscription,
        SettingsManager $settings,
        DatabaseService $database,
        $actionExecutor = null
    ) {
        $this->productAnalyzer = $productAnalyzer;
        $this->customerAnalyzer = $customerAnalyzer;
        $this->subscription = $subscription;
        $this->settings = $settings;
        $this->database = $database;
        $this->actionExecutor = $actionExecutor;
        
        // ثبت هوک‌های WordPress
        $this->registerHooks();
    }

    /**
     * ثبت هوک‌های WordPress برای پردازش پس‌زمینه
     *
     * @return void
     */
    private function registerHooks()
    {
        // هوک اصلی پردازش
        add_action(self::CRON_HOOK, [$this, 'processNextBatch']);
        
        // هوک Heartbeat برای بررسی وضعیت کار
        add_action(self::HEARTBEAT_HOOK, [$this, 'checkAndResumeJob']);
        
        // اضافه کردن به Heartbeat API وردپرس
        add_filter('heartbeat_received', [$this, 'handleHeartbeat'], 10, 2);
        add_filter('heartbeat_nopriv_received', [$this, 'handleHeartbeat'], 10, 2);
    }

    /**
     * پاسخ به Heartbeat API وردپرس
     * این متد اطمینان می‌دهد که کار در حال اجرا ادامه پیدا کند
     *
     * @param array $response
     * @param array $data
     * @return array
     */
    public function handleHeartbeat($response, $data)
    {
        if (!empty($data['aiagent_check_job'])) {
            $jobState = $this->getJobState();
            
            // اگر کار در حال اجرا است، آن را ادامه بده
            if ($jobState['status'] === self::STATUS_RUNNING) {
                $this->ensureJobIsProcessing();
                $response['aiagent_job_status'] = $this->getJobProgress();
            }
        }
        
        return $response;
    }

    /**
     * بررسی و ادامه کار متوقف شده
     *
     * @return void
     */
    public function checkAndResumeJob()
    {
        $jobState = $this->getJobState();
        
        if ($jobState['status'] !== self::STATUS_RUNNING) {
            return;
        }
        
        // بررسی آیا کار متوقف شده است
        $lastUpdate = strtotime($jobState['updated_at']);
        $now = time();
        
        if (($now - $lastUpdate) > self::STALE_JOB_THRESHOLD) {
            appLogger("[AIAgent] کار متوقف شده تشخیص داده شد، در حال ادامه...");
            $this->scheduleProcessing();
        }
    }

    /**
     * اطمینان از اینکه کار در حال پردازش است
     *
     * @return void
     */
    private function ensureJobIsProcessing()
    {
        $jobState = $this->getJobState();
        
        if ($jobState['status'] !== self::STATUS_RUNNING) {
            return;
        }
        
        // بررسی آیا cron زمان‌بندی شده است
        if (!wp_next_scheduled(self::CRON_HOOK)) {
            $this->scheduleProcessing();
        }
    }

    /**
     * شروع یک کار تحلیل جدید
     *
     * @param string $type 'all', 'products', یا 'customers'
     * @return array
     */
    public function startJob($type = 'all')
    {
        // بررسی آیا کاری در حال اجرا است
        $currentJob = $this->getJobState();
        if ($currentJob['status'] === self::STATUS_RUNNING) {
            // بررسی آیا کار قدیمی متوقف شده است
            $lastUpdate = strtotime($currentJob['updated_at']);
            $now = time();
            
            if (($now - $lastUpdate) > self::STALE_JOB_THRESHOLD) {
                appLogger("[AIAgent] کار قدیمی متوقف شده، در حال بازنشانی...");
                $this->resetJobState();
            } else {
                return [
                    'success' => false,
                    'error' => __('یک تحلیل در حال اجرا است. لطفاً صبر کنید یا آن را لغو کنید.', 'forooshyar'),
                ];
            }
        }

        // بررسی اشتراک
        if (!$this->subscription->isModuleEnabled()) {
            return [
                'success' => false,
                'error' => __('ماژول دستیار هوشمند فعال نیست', 'forooshyar'),
            ];
        }

        // بررسی تنظیمات نوع تحلیل
        $includeProducts = $this->settings->get('analysis_include_products');
        $includeCustomers = $this->settings->get('analysis_include_customers');
        
        if ($includeProducts === null || $includeProducts === '') {
            $includeProducts = true;
        } else {
            $includeProducts = (bool) $includeProducts;
        }
        
        if ($includeCustomers === null || $includeCustomers === '') {
            $includeCustomers = true;
        } else {
            $includeCustomers = (bool) $includeCustomers;
        }
        
        appLogger("[AIAgent] تنظیمات تحلیل - محصولات: " . ($includeProducts ? 'بله' : 'خیر') . "، مشتریان: " . ($includeCustomers ? 'بله' : 'خیر'));

        // دریافت موجودیت‌ها برای تحلیل
        $products = [];
        $customers = [];

        if ($type === 'all' || $type === 'products') {
            if ($includeProducts) {
                $limit = $this->settings->get('analysis_product_limit', 50);
                if (!$limit || $limit <= 0) {
                    $limit = 50;
                }
                appLogger("[AIAgent] دریافت محصولات با محدودیت: {$limit}");
                $products = $this->productAnalyzer->getEntities($limit);
                appLogger("[AIAgent] تعداد محصولات: " . count($products));
            }
        }

        if ($type === 'all' || $type === 'customers') {
            if ($includeCustomers) {
                $limit = $this->settings->get('analysis_customer_limit', 100);
                if (!$limit || $limit <= 0) {
                    $limit = 100;
                }
                appLogger("[AIAgent] دریافت مشتریان با محدودیت: {$limit}");
                $customers = $this->customerAnalyzer->getEntities($limit);
                appLogger("[AIAgent] تعداد مشتریان: " . count($customers));
            }
        }

        $totalItems = count($products) + count($customers);
        
        appLogger("[AIAgent] مجموع موارد برای تحلیل: {$totalItems}");
        
        if ($totalItems === 0) {
            $reasons = [];
            if (!$includeProducts && !$includeCustomers) {
                $reasons[] = __('تحلیل محصولات و مشتریان در تنظیمات غیرفعال است', 'forooshyar');
            } else {
                if ($includeProducts && empty($products)) {
                    $reasons[] = __('هیچ محصول منتشر شده‌ای یافت نشد', 'forooshyar');
                }
                if ($includeCustomers && empty($customers)) {
                    $reasons[] = __('هیچ مشتری‌ای یافت نشد', 'forooshyar');
                }
            }
            
            $errorMsg = !empty($reasons) 
                ? implode('. ', $reasons) 
                : __('هیچ موردی برای تحلیل یافت نشد', 'forooshyar');
            
            return [
                'success' => false,
                'error' => $errorMsg,
            ];
        }

        // ایجاد وضعیت کار
        $jobId = uniqid('job_', true);
        $jobState = [
            'id' => $jobId,
            'status' => self::STATUS_RUNNING,
            'type' => $type,
            'started_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
            'last_heartbeat' => time(),
            'products_total' => count($products),
            'products_processed' => 0,
            'products_success' => 0,
            'products_failed' => 0,
            'customers_total' => count($customers),
            'customers_processed' => 0,
            'customers_success' => 0,
            'customers_failed' => 0,
            'current_item' => null,
            'errors' => [],
            'actions_created' => 0,
            'pending_products' => array_column($products, 'id'),
            'pending_customers' => array_column($customers, 'id'),
        ];

        $this->saveJobState($jobState);
        
        // ذخیره در جدول scheduled برای پایداری بیشتر
        $this->saveJobToDatabase($jobState);

        // زمان‌بندی پردازش فوری
        $this->scheduleProcessing();
        
        // زمان‌بندی heartbeat برای بررسی دوره‌ای
        $this->scheduleHeartbeat();

        appLogger("[AIAgent] کار شروع شد: {$jobId} - محصولات: " . count($products) . "، مشتریان: " . count($customers));

        return [
            'success' => true,
            'job_id' => $jobId,
            'message' => sprintf(
                __('تحلیل شروع شد. %d مورد در صف تحلیل قرار گرفت.', 'forooshyar'),
                $totalItems
            ),
        ];
    }

    /**
     * ذخیره کار در جدول دیتابیس برای پایداری
     *
     * @param array $jobState
     * @return int|false
     */
    private function saveJobToDatabase(array $jobState)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'aiagent_scheduled';
        
        // حذف کارهای قبلی با همین نوع
        $wpdb->delete($table, [
            'task_type' => 'analysis_job',
            'status' => 'pending',
        ]);
        
        return $wpdb->insert($table, [
            'task_type' => 'analysis_job',
            'task_data' => wp_json_encode($jobState),
            'scheduled_at' => current_time('mysql'),
            'status' => 'pending',
        ], ['%s', '%s', '%s', '%s']);
    }

    /**
     * بروزرسانی کار در دیتابیس
     *
     * @param array $jobState
     * @return void
     */
    private function updateJobInDatabase(array $jobState)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'aiagent_scheduled';
        
        $wpdb->update(
            $table,
            [
                'task_data' => wp_json_encode($jobState),
                'status' => $jobState['status'] === self::STATUS_RUNNING ? 'pending' : $jobState['status'],
            ],
            [
                'task_type' => 'analysis_job',
                'status' => 'pending',
            ],
            ['%s', '%s'],
            ['%s', '%s']
        );
    }

    /**
     * پردازش دسته بعدی موارد
     *
     * @return void
     */
    public function processNextBatch()
    {
        $startTime = time();
        $jobState = $this->getJobState();

        // بررسی آیا کار باید ادامه یابد
        if ($jobState['status'] !== self::STATUS_RUNNING) {
            if ($jobState['status'] === self::STATUS_CANCELLING) {
                $jobState['status'] = self::STATUS_CANCELLED;
                $jobState['updated_at'] = current_time('mysql');
                $this->saveJobState($jobState);
                $this->updateJobInDatabase($jobState);
                appLogger("[AIAgent] کار لغو شد: {$jobState['id']}");
            }
            return;
        }

        // بروزرسانی heartbeat
        $jobState['last_heartbeat'] = time();
        $this->saveJobState($jobState);

        $processed = 0;
        $maxItems = 5; // پردازش حداکثر 5 مورد در هر batch

        // پردازش محصولات
        while ($processed < $maxItems && !empty($jobState['pending_products'])) {
            // بررسی زمان اجرا
            if ((time() - $startTime) > self::MAX_BATCH_EXECUTION_TIME) {
                appLogger("[AIAgent] زمان batch به پایان رسید، زمان‌بندی ادامه...");
                break;
            }

            $productId = array_shift($jobState['pending_products']);
            $jobState['current_item'] = ['type' => 'product', 'id' => $productId];
            $jobState['updated_at'] = current_time('mysql');
            $this->saveJobState($jobState);

            try {
                appLogger("[AIAgent] در حال تحلیل محصول: {$productId}");
                $result = $this->productAnalyzer->analyzeEntity($productId);
                $jobState['products_processed']++;
                
                if ($result['success']) {
                    $jobState['products_success']++;
                    if (!empty($result['suggestions'])) {
                        $actionsCreated = $this->createActionsFromSuggestions($result);
                        $jobState['actions_created'] += $actionsCreated;
                    }
                    appLogger("[AIAgent] محصول {$productId} با موفقیت تحلیل شد");
                } else {
                    $jobState['products_failed']++;
                    $jobState['errors'][] = [
                        'type' => 'product',
                        'id' => $productId,
                        'error' => $result['error'] ?? __('خطای ناشناخته', 'forooshyar'),
                    ];
                    appLogger("[AIAgent] خطا در تحلیل محصول {$productId}: " . ($result['error'] ?? 'نامشخص'));
                }
            } catch (\Exception $e) {
                $jobState['products_processed']++;
                $jobState['products_failed']++;
                $jobState['errors'][] = [
                    'type' => 'product',
                    'id' => $productId,
                    'error' => $e->getMessage(),
                ];
                appLogger("[AIAgent] استثنا در تحلیل محصول {$productId}: " . $e->getMessage());
            }

            $processed++;
            
            // بررسی لغو
            $currentState = $this->getJobState();
            if ($currentState['status'] === self::STATUS_CANCELLING) {
                $jobState['status'] = self::STATUS_CANCELLED;
                $jobState['updated_at'] = current_time('mysql');
                $jobState['current_item'] = null;
                $this->saveJobState($jobState);
                $this->updateJobInDatabase($jobState);
                appLogger("[AIAgent] کار در حین پردازش لغو شد: {$jobState['id']}");
                return;
            }
        }

        // پردازش مشتریان
        while ($processed < $maxItems && !empty($jobState['pending_customers'])) {
            // بررسی زمان اجرا
            if ((time() - $startTime) > self::MAX_BATCH_EXECUTION_TIME) {
                appLogger("[AIAgent] زمان batch به پایان رسید، زمان‌بندی ادامه...");
                break;
            }

            $customerId = array_shift($jobState['pending_customers']);
            $jobState['current_item'] = ['type' => 'customer', 'id' => $customerId];
            $jobState['updated_at'] = current_time('mysql');
            $this->saveJobState($jobState);

            try {
                appLogger("[AIAgent] در حال تحلیل مشتری: {$customerId}");
                $result = $this->customerAnalyzer->analyzeEntity($customerId);
                $jobState['customers_processed']++;
                
                if ($result['success']) {
                    $jobState['customers_success']++;
                    if (!empty($result['suggestions'])) {
                        $actionsCreated = $this->createActionsFromSuggestions($result);
                        $jobState['actions_created'] += $actionsCreated;
                    }
                    appLogger("[AIAgent] مشتری {$customerId} با موفقیت تحلیل شد");
                } else {
                    $jobState['customers_failed']++;
                    $jobState['errors'][] = [
                        'type' => 'customer',
                        'id' => $customerId,
                        'error' => $result['error'] ?? __('خطای ناشناخته', 'forooshyar'),
                    ];
                    appLogger("[AIAgent] خطا در تحلیل مشتری {$customerId}: " . ($result['error'] ?? 'نامشخص'));
                }
            } catch (\Exception $e) {
                $jobState['customers_processed']++;
                $jobState['customers_failed']++;
                $jobState['errors'][] = [
                    'type' => 'customer',
                    'id' => $customerId,
                    'error' => $e->getMessage(),
                ];
                appLogger("[AIAgent] استثنا در تحلیل مشتری {$customerId}: " . $e->getMessage());
            }

            $processed++;
            
            // بررسی لغو
            $currentState = $this->getJobState();
            if ($currentState['status'] === self::STATUS_CANCELLING) {
                $jobState['status'] = self::STATUS_CANCELLED;
                $jobState['updated_at'] = current_time('mysql');
                $jobState['current_item'] = null;
                $this->saveJobState($jobState);
                $this->updateJobInDatabase($jobState);
                return;
            }
        }

        // بروزرسانی وضعیت کار
        $jobState['updated_at'] = current_time('mysql');
        $jobState['current_item'] = null;

        // بررسی اتمام کار
        if (empty($jobState['pending_products']) && empty($jobState['pending_customers'])) {
            $this->completeJob($jobState);
        } else {
            $this->saveJobState($jobState);
            $this->updateJobInDatabase($jobState);
            // زمان‌بندی batch بعدی
            $this->scheduleProcessing();
            appLogger("[AIAgent] batch پردازش شد، موارد باقی‌مانده: محصولات=" . count($jobState['pending_products']) . "، مشتریان=" . count($jobState['pending_customers']));
        }
    }

    /**
     * تکمیل کار و ذخیره نتایج
     *
     * @param array $jobState
     * @return void
     */
    private function completeJob(array &$jobState)
    {
        $jobState['status'] = self::STATUS_COMPLETED;
        $jobState['completed_at'] = current_time('mysql');
        
        // افزایش شمارنده استفاده
        $this->subscription->incrementUsage('analyses_per_day');
        
        // اجرای خودکار اقدامات تأیید شده
        $actionsExecuted = 0;
        if ($this->settings->get('actions_auto_execute', false) && $this->actionExecutor) {
            $actionsExecuted = $this->executeApprovedActions();
            $jobState['actions_executed'] = $actionsExecuted;
            appLogger("[AIAgent] {$actionsExecuted} اقدام تأیید شده به صورت خودکار اجرا شد");
        }
        
        $this->saveJobState($jobState);
        $this->updateJobInDatabase($jobState);
        
        // ذخیره رکورد اجرای تحلیل
        $this->database->saveAnalysisRun([
            'type' => $jobState['type'],
            'success' => true,
            'products_analyzed' => $jobState['products_success'],
            'customers_analyzed' => $jobState['customers_success'],
            'actions_created' => $jobState['actions_created'],
            'actions_executed' => $actionsExecuted,
            'duration_ms' => 0,
        ]);
        
        // پاکسازی cron
        wp_clear_scheduled_hook(self::CRON_HOOK);
        wp_clear_scheduled_hook(self::HEARTBEAT_HOOK);
        
        appLogger("[AIAgent] کار تکمیل شد: {$jobState['id']} - محصولات: {$jobState['products_success']}/{$jobState['products_total']}، مشتریان: {$jobState['customers_success']}/{$jobState['customers_total']}، اقدامات اجرا شده: {$actionsExecuted}");
    }

    /**
     * لغو کار جاری
     *
     * @return array
     */
    public function cancelJob()
    {
        $jobState = $this->getJobState();

        if ($jobState['status'] !== self::STATUS_RUNNING) {
            return [
                'success' => false,
                'error' => __('هیچ تحلیلی در حال اجرا نیست', 'forooshyar'),
            ];
        }

        $jobState['status'] = self::STATUS_CANCELLING;
        $jobState['updated_at'] = current_time('mysql');
        $this->saveJobState($jobState);
        $this->updateJobInDatabase($jobState);

        appLogger("[AIAgent] درخواست لغو کار: {$jobState['id']}");

        return [
            'success' => true,
            'message' => __('درخواست لغو تحلیل ارسال شد', 'forooshyar'),
        ];
    }

    /**
     * دریافت وضعیت کار جاری
     *
     * @return array
     */
    public function getJobState()
    {
        $state = get_option(self::OPTION_JOB_STATE, null);
        
        // اگر وضعیت موجود است و کامل/لغو/شکست خورده، آن را برگردان
        if ($state && in_array($state['status'], [self::STATUS_COMPLETED, self::STATUS_CANCELLED, self::STATUS_FAILED], true)) {
            return $state;
        }
        
        // اگر وضعیت موجود است و در حال اجرا، بررسی کن که آیا در دیتابیس هم همین وضعیت است
        if ($state && $state['status'] === self::STATUS_RUNNING) {
            // بررسی دیتابیس برای اطمینان از همگام بودن
            $dbState = $this->loadJobFromDatabaseAnyStatus();
            if ($dbState && $dbState['status'] !== self::STATUS_RUNNING) {
                // دیتابیس وضعیت متفاوتی دارد، از آن استفاده کن
                $this->saveJobState($dbState);
                return $dbState;
            }
            return $state;
        }
        
        if (!$state) {
            // بررسی دیتابیس برای کار ذخیره شده
            $state = $this->loadJobFromDatabase();
        }
        
        if (!$state) {
            return $this->getDefaultState();
        }

        return $state;
    }

    /**
     * بارگذاری کار از دیتابیس (فقط pending)
     *
     * @return array|null
     */
    private function loadJobFromDatabase()
    {
        global $wpdb;
        $table = $wpdb->prefix . 'aiagent_scheduled';
        
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT task_data FROM {$table} WHERE task_type = %s AND status = %s ORDER BY id DESC LIMIT 1",
            'analysis_job',
            'pending'
        ));
        
        if ($row && !empty($row->task_data)) {
            $data = json_decode($row->task_data, true);
            if ($data) {
                // همگام‌سازی با option
                $this->saveJobState($data);
                return $data;
            }
        }
        
        return null;
    }
    
    /**
     * بارگذاری آخرین کار از دیتابیس (هر وضعیتی)
     *
     * @return array|null
     */
    private function loadJobFromDatabaseAnyStatus()
    {
        global $wpdb;
        $table = $wpdb->prefix . 'aiagent_scheduled';
        
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT task_data, status FROM {$table} WHERE task_type = %s ORDER BY id DESC LIMIT 1",
            'analysis_job'
        ));
        
        if ($row && !empty($row->task_data)) {
            $data = json_decode($row->task_data, true);
            if ($data) {
                // اگر وضعیت در task_data با وضعیت رکورد متفاوت است، از وضعیت رکورد استفاده کن
                if ($row->status === 'completed') {
                    $data['status'] = self::STATUS_COMPLETED;
                } elseif ($row->status === 'cancelled') {
                    $data['status'] = self::STATUS_CANCELLED;
                } elseif ($row->status === 'failed') {
                    $data['status'] = self::STATUS_FAILED;
                }
                return $data;
            }
        }
        
        return null;
    }

    /**
     * دریافت پیشرفت کار برای نمایش
     *
     * @return array
     */
    public function getJobProgress()
    {
        $state = $this->getJobState();
        
        // اگر وضعیت idle است، یعنی هیچ کاری در حال اجرا نیست
        if ($state['status'] === self::STATUS_IDLE) {
            return [
                'status' => self::STATUS_IDLE,
                'job_id' => null,
                'is_running' => false,
                'is_cancelling' => false,
                'progress' => 0,
                'percentage' => 0,
                'products_total' => 0,
                'products_analyzed' => 0,
                'products_processed' => 0,
                'products_failed' => 0,
                'customers_total' => 0,
                'customers_analyzed' => 0,
                'customers_processed' => 0,
                'customers_failed' => 0,
                'actions_created' => 0,
                'current_item' => null,
                'errors' => [],
                'started_at' => null,
                'updated_at' => null,
                'completed_at' => null,
            ];
        }
        
        $totalItems = $state['products_total'] + $state['customers_total'];
        $processedItems = $state['products_processed'] + $state['customers_processed'];
        $percentage = $totalItems > 0 ? round(($processedItems / $totalItems) * 100) : 0;

        // فرمت مورد جاری برای نمایش
        $currentItemText = null;
        if (!empty($state['current_item'])) {
            $type = $state['current_item']['type'] === 'product' ? 'محصول' : 'مشتری';
            $currentItemText = $type . ' #' . $state['current_item']['id'];
        }

        return [
            'status' => $state['status'],
            'job_id' => isset($state['id']) ? $state['id'] : null,
            'is_running' => $state['status'] === self::STATUS_RUNNING,
            'is_cancelling' => $state['status'] === self::STATUS_CANCELLING,
            'is_completed' => $state['status'] === self::STATUS_COMPLETED,
            'is_failed' => $state['status'] === self::STATUS_FAILED,
            'is_cancelled' => $state['status'] === self::STATUS_CANCELLED,
            'progress' => $percentage,
            'percentage' => $percentage,
            'products_total' => isset($state['products_total']) ? $state['products_total'] : 0,
            'products_analyzed' => isset($state['products_success']) ? $state['products_success'] : 0,
            'products_processed' => isset($state['products_processed']) ? $state['products_processed'] : 0,
            'products_failed' => isset($state['products_failed']) ? $state['products_failed'] : 0,
            'customers_total' => isset($state['customers_total']) ? $state['customers_total'] : 0,
            'customers_analyzed' => isset($state['customers_success']) ? $state['customers_success'] : 0,
            'customers_processed' => isset($state['customers_processed']) ? $state['customers_processed'] : 0,
            'customers_failed' => isset($state['customers_failed']) ? $state['customers_failed'] : 0,
            'actions_created' => isset($state['actions_created']) ? $state['actions_created'] : 0,
            'current_item' => $currentItemText,
            'errors' => isset($state['errors']) ? \array_slice($state['errors'], -5) : [],
            'started_at' => isset($state['started_at']) ? $state['started_at'] : null,
            'updated_at' => isset($state['updated_at']) ? $state['updated_at'] : null,
            'completed_at' => isset($state['completed_at']) ? $state['completed_at'] : null,
        ];
    }

    /**
     * بازنشانی وضعیت کار
     *
     * @return void
     */
    public function resetJobState()
    {
        delete_option(self::OPTION_JOB_STATE);
        wp_clear_scheduled_hook(self::CRON_HOOK);
        wp_clear_scheduled_hook(self::HEARTBEAT_HOOK);
        
        // پاکسازی از دیتابیس
        global $wpdb;
        $table = $wpdb->prefix . 'aiagent_scheduled';
        $wpdb->delete($table, ['task_type' => 'analysis_job', 'status' => 'pending']);
    }
    
    /**
     * تأیید اتمام کار و پاکسازی وضعیت
     * این متد پس از نمایش نتیجه به کاربر فراخوانی می‌شود
     *
     * @return void
     */
    public function acknowledgeCompletion()
    {
        $state = $this->getJobState();
        
        // فقط اگر کار تمام شده باشد، وضعیت را پاک کن
        if (in_array($state['status'], [self::STATUS_COMPLETED, self::STATUS_CANCELLED, self::STATUS_FAILED], true)) {
            delete_option(self::OPTION_JOB_STATE);
            appLogger("[AIAgent] وضعیت کار پاک شد پس از تأیید اتمام");
        }
    }

    /**
     * ذخیره وضعیت کار
     *
     * @param array $state
     * @return void
     */
    private function saveJobState(array $state)
    {
        update_option(self::OPTION_JOB_STATE, $state, false);
    }

    /**
     * دریافت وضعیت پیش‌فرض
     *
     * @return array
     */
    private function getDefaultState()
    {
        return [
            'id' => null,
            'status' => self::STATUS_IDLE,
            'type' => null,
            'started_at' => null,
            'updated_at' => null,
            'completed_at' => null,
            'last_heartbeat' => null,
            'products_total' => 0,
            'products_processed' => 0,
            'products_success' => 0,
            'products_failed' => 0,
            'customers_total' => 0,
            'customers_processed' => 0,
            'customers_success' => 0,
            'customers_failed' => 0,
            'current_item' => null,
            'errors' => [],
            'actions_created' => 0,
            'pending_products' => [],
            'pending_customers' => [],
        ];
    }

    /**
     * زمان‌بندی پردازش از طریق cron
     *
     * @return void
     */
    private function scheduleProcessing()
    {
        // پاکسازی زمان‌بندی‌های قبلی
        wp_clear_scheduled_hook(self::CRON_HOOK);
        
        // زمان‌بندی فوری
        wp_schedule_single_event(time() + 1, self::CRON_HOOK);
        
        appLogger("[AIAgent] پردازش زمان‌بندی شد");
        
        // همچنین یک درخواست HTTP برای اطمینان از اجرا ارسال کن
        $this->triggerAsyncProcessing();
    }

    /**
     * زمان‌بندی heartbeat برای بررسی دوره‌ای
     *
     * @return void
     */
    private function scheduleHeartbeat()
    {
        if (!wp_next_scheduled(self::HEARTBEAT_HOOK)) {
            wp_schedule_event(time() + 60, 'every_minute', self::HEARTBEAT_HOOK);
        }
    }

    /**
     * ارسال درخواست async برای اطمینان از اجرای cron
     *
     * @return void
     */
    private function triggerAsyncProcessing()
    {
        // روش 1: اجرای مستقیم cron
        $this->spawnCron();
        
        // روش 2: استفاده از wp_remote_post برای ارسال درخواست غیرهمزمان
        $url = add_query_arg([
            'doing_wp_cron' => time(),
        ], site_url('/wp-cron.php'));
        
        wp_remote_post($url, [
            'timeout' => 0.5,
            'blocking' => false,
            'sslverify' => false,
            'headers' => [
                'Cache-Control' => 'no-cache',
            ],
        ]);
        
        appLogger("[AIAgent] درخواست async برای اجرای cron ارسال شد");
    }
    
    /**
     * اجرای مستقیم cron بدون انتظار برای درخواست HTTP
     *
     * @return void
     */
    private function spawnCron()
    {
        // بررسی آیا cron در حال اجرا است
        if (defined('DOING_CRON') && DOING_CRON) {
            return;
        }
        
        // اجرای مستقیم hook اگر امکان‌پذیر باشد
        if (function_exists('spawn_cron')) {
            spawn_cron();
        }
    }
    
    /**
     * پردازش فوری بدون انتظار برای cron
     * این متد می‌تواند از طریق AJAX فراخوانی شود
     *
     * @return void
     */
    public function processImmediately()
    {
        $jobState = $this->getJobState();
        
        if ($jobState['status'] !== self::STATUS_RUNNING) {
            return;
        }
        
        // اجرای مستقیم پردازش
        $this->processNextBatch();
    }

    /**
     * ایجاد اقدامات از پیشنهادات تحلیل
     *
     * @param array $analysisResult
     * @return int تعداد اقدامات ایجاد شده
     */
    private function createActionsFromSuggestions(array $analysisResult)
    {
        if (empty($analysisResult['suggestions'])) {
            appLogger("[AIAgent] هیچ پیشنهادی برای ایجاد اقدام وجود ندارد");
            return 0;
        }

        $enabledActions = $this->settings->get('actions_enabled_types', []);
        $requireApproval = $this->settings->get('actions_require_approval', []);
        $created = 0;

        appLogger("[AIAgent] ایجاد اقدامات از " . \count($analysisResult['suggestions']) . " پیشنهاد");
        appLogger("[AIAgent] انواع اقدامات فعال: " . implode(', ', $enabledActions));

        foreach ($analysisResult['suggestions'] as $suggestion) {
            $actionType = $suggestion['type'] ?? '';

            appLogger("[AIAgent] پردازش پیشنهاد نوع: {$actionType}");

            if (!\in_array($actionType, $enabledActions, true)) {
                appLogger("[AIAgent] رد شد نوع اقدام '{$actionType}' - در لیست فعال نیست");
                continue;
            }

            $priority = (int) ($suggestion['priority'] ?? 50);
            $needsApproval = \in_array($actionType, $requireApproval, true);

            // شامل کردن دلیل و اطلاعات موجودیت در action_data
            $suggestionData = $suggestion['data'] ?? [];
            
            if (!empty($suggestion['reasoning'])) {
                $suggestionData['reasoning'] = $suggestion['reasoning'];
            }
            
            // اضافه کردن اطلاعات موجودیت برای جلوگیری از تکرار
            if (!empty($analysisResult['entity_id'])) {
                $suggestionData['entity_id'] = $analysisResult['entity_id'];
            }
            if (!empty($analysisResult['entity_type'])) {
                $suggestionData['entity_type'] = $analysisResult['entity_type'];
            }
            
            if (!empty($suggestion['data']['product_id'])) {
                $suggestionData['product_id'] = $suggestion['data']['product_id'];
            }
            if (!empty($suggestion['data']['customer_id'])) {
                $suggestionData['customer_id'] = $suggestion['data']['customer_id'];
            }

            $actionData = [
                'analysis_id' => $analysisResult['id'] ?? null,
                'action_type' => $actionType,
                'action_data' => $suggestionData,
                'status' => $needsApproval ? 'pending' : 'approved',
                'priority_score' => $priority,
                'requires_approval' => $needsApproval ? 1 : 0,
            ];

            $actionId = $this->database->saveAction($actionData);
            if ($actionId) {
                $created++;
                appLogger("[AIAgent] اقدام ایجاد شد با شناسه: {$actionId}، نوع: {$actionType}");
            } else {
                appLogger("[AIAgent] خطا در ذخیره اقدام نوع: {$actionType}");
            }
        }

        appLogger("[AIAgent] مجموع اقدامات ایجاد شده: {$created}");
        return $created;
    }

    /**
     * اجرای خودکار اقدامات تأیید شده
     *
     * @return int تعداد اقدامات اجرا شده
     */
    private function executeApprovedActions()
    {
        if (!$this->actionExecutor) {
            appLogger("[AIAgent] ActionExecutor برای اجرای خودکار در دسترس نیست");
            return 0;
        }

        $limit = (int) $this->settings->get('actions_max_per_run', 10);
        $priorityThreshold = (int) $this->settings->get('analysis_priority_threshold', 70);
        
        // دریافت اقدامات تأیید شده با اولویت بالا
        $actions = $this->database->getActions([
            'status' => 'approved',
        ], $limit, 0);

        $executed = 0;

        foreach ($actions as $action) {
            // فقط اقدامات با اولویت بالا را اجرا کن
            if ($action['priority_score'] < $priorityThreshold) {
                continue;
            }

            try {
                $result = $this->actionExecutor->executeById($action['id']);
                if ($result['success']) {
                    $executed++;
                    appLogger("[AIAgent] اقدام #{$action['id']} ({$action['action_type']}) به صورت خودکار اجرا شد");
                } else {
                    appLogger("[AIAgent] خطا در اجرای خودکار اقدام #{$action['id']}: " . ($result['error'] ?? 'نامشخص'));
                }
            } catch (\Exception $e) {
                appLogger("[AIAgent] استثنا در اجرای خودکار اقدام #{$action['id']}: " . $e->getMessage());
            }
        }

        return $executed;
    }

    /**
     * بازیابی کار متوقف شده (برای فراخوانی دستی یا از طریق WP-CLI)
     *
     * @return array
     */
    public function resumeStaleJob()
    {
        $jobState = $this->getJobState();
        
        if ($jobState['status'] !== self::STATUS_RUNNING) {
            return [
                'success' => false,
                'error' => __('هیچ کار در حال اجرایی برای بازیابی وجود ندارد', 'forooshyar'),
            ];
        }
        
        $lastUpdate = strtotime($jobState['updated_at']);
        $now = time();
        
        if (($now - $lastUpdate) <= self::STALE_JOB_THRESHOLD) {
            return [
                'success' => false,
                'error' => __('کار هنوز فعال است و نیازی به بازیابی ندارد', 'forooshyar'),
            ];
        }
        
        // بازیابی کار
        $jobState['updated_at'] = current_time('mysql');
        $jobState['last_heartbeat'] = time();
        $this->saveJobState($jobState);
        
        $this->scheduleProcessing();
        
        appLogger("[AIAgent] کار متوقف شده بازیابی شد: {$jobState['id']}");
        
        return [
            'success' => true,
            'message' => __('کار با موفقیت بازیابی شد', 'forooshyar'),
        ];
    }

    /**
     * دریافت آمار کارها
     *
     * @return array
     */
    public function getJobStats()
    {
        global $wpdb;
        $table = $wpdb->prefix . 'aiagent_scheduled';
        
        $stats = $wpdb->get_results(
            "SELECT status, COUNT(*) as count FROM {$table} WHERE task_type = 'analysis_job' GROUP BY status",
            ARRAY_A
        );
        
        $result = [
            'pending' => 0,
            'completed' => 0,
            'failed' => 0,
            'cancelled' => 0,
        ];
        
        foreach ($stats as $row) {
            $result[$row['status']] = (int) $row['count'];
        }
        
        return $result;
    }
}
