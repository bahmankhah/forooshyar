<?php
/**
 * AI Agent Dashboard View
 * Persian interface with WordPress tables, tabs, and pagination
 * 
 * @var array $stats
 * @var array $status
 * @var string $currentTab
 * @var int $currentPage
 * @var int $perPage
 * @var array $actionCounts
 * @var array $analysisCounts
 * @var array|null $actionsData
 * @var array|null $analysesData
 * @var string $statusFilter
 * @var string $typeFilter
 */

if (!defined('ABSPATH')) exit;

// Persian translations
$tierNames = [
    'free' => 'رایگان',
    'basic' => 'پایه',
    'pro' => 'حرفه‌ای',
    'enterprise' => 'سازمانی',
];

$actionTypeLabels = [
    'send_email' => 'ارسال ایمیل',
    'send_sms' => 'ارسال پیامک',
    'create_discount' => 'ایجاد تخفیف',
    'update_product' => 'بروزرسانی محصول',
    'create_campaign' => 'ایجاد کمپین',
    'schedule_followup' => 'زمان‌بندی پیگیری',
    'create_bundle' => 'ایجاد بسته',
    'inventory_alert' => 'هشدار موجودی',
    'loyalty_reward' => 'پاداش وفاداری',
    'schedule_price_change' => 'تغییر قیمت',
];

$statusLabels = [
    'pending' => 'در انتظار',
    'approved' => 'تأیید شده',
    'completed' => 'انجام شده',
    'failed' => 'ناموفق',
    'cancelled' => 'لغو شده',
];

$statusColors = [
    'pending' => '#f0ad4e',
    'approved' => '#5bc0de',
    'completed' => '#5cb85c',
    'failed' => '#d9534f',
    'cancelled' => '#777',
];

$entityTypeLabels = [
    'product' => 'محصول',
    'customer' => 'مشتری',
];

$currentTierName = $tierNames[$status['tier']] ?? $status['tier'];
$baseUrl = admin_url('admin.php?page=forooshyar-ai-agent');

/**
 * Format date to Persian/Jalali
 */
function aiagent_format_date($date) {
    if (empty($date)) return '-';
    $timestamp = strtotime($date);
    // Use WordPress date_i18n for localization
    return date_i18n('j F Y - H:i', $timestamp);
}

/**
 * Get priority class
 */
function aiagent_priority_class($score) {
    if ($score >= 70) return 'high';
    if ($score >= 50) return 'medium';
    return 'low';
}

/**
 * Get reasoning for an action - from action_data or from linked analysis
 */
function aiagent_get_action_reasoning($action) {
    $actionData = $action['action_data'] ?? [];
    
    // First try to get from action_data
    if (!empty($actionData['reasoning'])) {
        return $actionData['reasoning'];
    }
    
    // If not found and we have analysis_id, try to get from analysis suggestions
    if (!empty($action['analysis_id'])) {
        $db = \Forooshyar\WPLite\Container::resolve(\Forooshyar\Modules\AIAgent\Services\DatabaseService::class);
        $analysis = $db->getAnalysis($action['analysis_id']);
        
        if ($analysis && !empty($analysis['suggestions'])) {
            // Find the suggestion that matches this action type
            foreach ($analysis['suggestions'] as $suggestion) {
                if (($suggestion['type'] ?? '') === $action['action_type']) {
                    if (!empty($suggestion['reasoning'])) {
                        return $suggestion['reasoning'];
                    }
                }
            }
        }
    }
    
    return '';
}

/**
 * Format action details for display
 */
function aiagent_format_action_details($actionType, $data) {
    if (empty($data) || !is_array($data)) {
        return '';
    }
    
    $details = [];
    
    switch ($actionType) {
        case 'create_discount':
            if (!empty($data['discount_percent'])) {
                $details[] = '<strong>' . esc_html($data['discount_percent']) . '%</strong> تخفیف';
            }
            if (!empty($data['discount_code'])) {
                $details[] = 'کد: <code>' . esc_html($data['discount_code']) . '</code>';
            }
            if (!empty($data['valid_days'])) {
                $details[] = esc_html($data['valid_days']) . ' روز اعتبار';
            }
            if (!empty($data['description'])) {
                $details[] = esc_html($data['description']);
            }
            break;
            
        case 'send_email':
            if (!empty($data['subject'])) {
                $details[] = '<strong>موضوع:</strong> ' . esc_html($data['subject']);
            }
            if (!empty($data['message'])) {
                $msg = mb_strlen($data['message']) > 100 ? mb_substr($data['message'], 0, 100) . '...' : $data['message'];
                $details[] = '<strong>پیام:</strong> ' . esc_html($msg);
            }
            break;
            
        case 'send_sms':
            if (!empty($data['message'])) {
                $details[] = '<strong>پیامک:</strong> ' . esc_html($data['message']);
            }
            break;
            
        case 'update_product':
            if (!empty($data['new_price'])) {
                $details[] = 'قیمت جدید: <strong>' . number_format($data['new_price']) . '</strong>';
            }
            if (!empty($data['price_change_percent'])) {
                $sign = $data['price_change_percent'] > 0 ? '+' : '';
                $details[] = 'تغییر: ' . $sign . esc_html($data['price_change_percent']) . '%';
            }
            if (!empty($data['update_description'])) {
                $details[] = esc_html($data['update_description']);
            }
            break;
            
        case 'create_campaign':
            if (!empty($data['campaign_name'])) {
                $details[] = '<strong>' . esc_html($data['campaign_name']) . '</strong>';
            }
            if (!empty($data['campaign_message'])) {
                $msg = mb_strlen($data['campaign_message']) > 80 ? mb_substr($data['campaign_message'], 0, 80) . '...' : $data['campaign_message'];
                $details[] = esc_html($msg);
            }
            if (!empty($data['duration_days'])) {
                $details[] = esc_html($data['duration_days']) . ' روز';
            }
            break;
            
        case 'schedule_followup':
            if (!empty($data['followup_days'])) {
                $details[] = 'پیگیری در ' . esc_html($data['followup_days']) . ' روز آینده';
            }
            if (!empty($data['followup_type'])) {
                $typeLabel = $data['followup_type'] === 'email' ? 'ایمیل' : 'پیامک';
                $details[] = 'از طریق ' . $typeLabel;
            }
            if (!empty($data['followup_message'])) {
                $msg = mb_strlen($data['followup_message']) > 60 ? mb_substr($data['followup_message'], 0, 60) . '...' : $data['followup_message'];
                $details[] = esc_html($msg);
            }
            break;
            
        case 'inventory_alert':
            if (!empty($data['alert_message'])) {
                $details[] = esc_html($data['alert_message']);
            }
            if (!empty($data['recommended_quantity'])) {
                $details[] = 'سفارش پیشنهادی: ' . esc_html($data['recommended_quantity']) . ' عدد';
            }
            break;
            
        case 'schedule_price_change':
            if (!empty($data['new_price'])) {
                $details[] = 'قیمت جدید: <strong>' . number_format($data['new_price']) . '</strong>';
            }
            if (!empty($data['change_date'])) {
                $details[] = 'تاریخ: ' . esc_html($data['change_date']);
            }
            if (!empty($data['change_reason'])) {
                $details[] = esc_html($data['change_reason']);
            }
            break;
            
        case 'create_bundle':
            if (!empty($data['bundle_name'])) {
                $details[] = '<strong>' . esc_html($data['bundle_name']) . '</strong>';
            }
            if (!empty($data['bundle_discount'])) {
                $details[] = esc_html($data['bundle_discount']) . '% تخفیف بسته';
            }
            if (!empty($data['bundle_description'])) {
                $details[] = esc_html($data['bundle_description']);
            }
            break;
            
        case 'loyalty_reward':
            if (!empty($data['reward_type'])) {
                $typeLabels = [
                    'discount' => 'تخفیف',
                    'free_shipping' => 'ارسال رایگان',
                    'gift' => 'هدیه'
                ];
                $details[] = '<strong>' . ($typeLabels[$data['reward_type']] ?? $data['reward_type']) . '</strong>';
            }
            if (!empty($data['reward_value'])) {
                $details[] = 'مقدار: ' . esc_html($data['reward_value']);
            }
            if (!empty($data['reward_code'])) {
                $details[] = 'کد: <code>' . esc_html($data['reward_code']) . '</code>';
            }
            if (!empty($data['reward_message'])) {
                $msg = mb_strlen($data['reward_message']) > 60 ? mb_substr($data['reward_message'], 0, 60) . '...' : $data['reward_message'];
                $details[] = esc_html($msg);
            }
            break;
    }
    
    if (empty($details)) {
        return '';
    }
    
    return '<div class="action-details">' . implode(' <span class="sep">|</span> ', $details) . '</div>';
}
?>
<div class="wrap aiagent-dashboard" dir="rtl">
    <h1 class="wp-heading-inline"><?php _e('دستیار فروش هوشمند', 'forooshyar'); ?></h1>
    
    <button type="button" class="page-title-action" id="run-analysis" <?php echo !$status['enabled'] ? 'disabled' : ''; ?>>
        <?php _e('شروع تحلیل', 'forooshyar'); ?>
    </button>
    <button type="button" class="page-title-action" id="cancel-analysis" style="display:none;">
        <?php _e('لغو تحلیل', 'forooshyar'); ?>
    </button>
    
    <hr class="wp-header-end">

    <?php if (!$status['enabled']): ?>
    <div class="notice notice-warning">
        <p><?php _e('ماژول دستیار هوشمند غیرفعال است. برای استفاده، آن را در تنظیمات فعال کنید.', 'forooshyar'); ?></p>
    </div>
    <?php endif; ?>

    <!-- Analysis Progress Section -->
    <div id="analysis-progress-section" class="notice notice-info" style="display:none; padding: 15px;">
        <div class="progress-header" style="display: flex; justify-content: space-between; margin-bottom: 10px;">
            <span class="progress-status"><strong><?php _e('در حال تحلیل...', 'forooshyar'); ?></strong></span>
            <span class="progress-percent">0%</span>
        </div>
        <div class="progress-bar-container" style="height: 20px; background: #e5e5e5; border-radius: 4px; overflow: hidden;">
            <div class="progress-bar-fill" style="width: 0%; height: 100%; background: #0073aa; transition: width 0.3s;"></div>
        </div>
        <div class="progress-details" style="margin-top: 10px; display: flex; gap: 20px; color: #666;">
            <span id="progress-products"><?php _e('محصولات:', 'forooshyar'); ?> 0/0</span>
            <span id="progress-customers"><?php _e('مشتریان:', 'forooshyar'); ?> 0/0</span>
            <span id="progress-actions"><?php _e('اقدامات:', 'forooshyar'); ?> 0</span>
        </div>
        <div class="progress-current" id="current-item" style="margin-top: 5px; font-size: 12px; color: #888;"></div>
    </div>

    <!-- Status Bar -->
    <div class="aiagent-status-bar" style="background: #fff; padding: 15px 20px; margin-bottom: 20px; border: 1px solid #ccd0d4; display: flex; justify-content: space-between; align-items: center;">
        <div class="status-info" style="display: flex; gap: 20px; align-items: center;">
            <span class="status-badge" style="padding: 5px 12px; border-radius: 4px; font-size: 12px; font-weight: 600; background: <?php echo $status['enabled'] ? '#d4edda' : '#f8d7da'; ?>; color: <?php echo $status['enabled'] ? '#155724' : '#721c24'; ?>;">
                <?php echo $status['enabled'] ? __('فعال', 'forooshyar') : __('غیرفعال', 'forooshyar'); ?>
            </span>
            <span style="color: #666;"><?php _e('سطح اشتراک:', 'forooshyar'); ?> <strong><?php echo esc_html($currentTierName); ?></strong></span>
            <span style="color: #666;"><?php _e('مدل:', 'forooshyar'); ?> <strong><?php echo esc_html($status['llm_model'] ?? '-'); ?></strong></span>
        </div>
        <div class="status-stats" style="display: flex; gap: 30px;">
            <div style="text-align: center;">
                <div style="font-size: 24px; font-weight: 600; color: #0073aa;"><?php echo esc_html($stats['summary']['pending_actions']); ?></div>
                <div style="font-size: 11px; color: #666;"><?php _e('در انتظار', 'forooshyar'); ?></div>
            </div>
            <div style="text-align: center;">
                <div style="font-size: 24px; font-weight: 600; color: #46b450;"><?php echo esc_html($stats['summary']['completed_today']); ?></div>
                <div style="font-size: 11px; color: #666;"><?php _e('انجام شده امروز', 'forooshyar'); ?></div>
            </div>
            <div style="text-align: center;">
                <div style="font-size: 24px; font-weight: 600; color: #826eb4;"><?php echo esc_html($stats['summary']['success_rate']); ?>%</div>
                <div style="font-size: 11px; color: #666;"><?php _e('نرخ موفقیت', 'forooshyar'); ?></div>
            </div>
            <div style="text-align: center;">
                <div style="font-size: 24px; font-weight: 600; color: #ffb900;"><?php echo esc_html($stats['summary']['analyses_today']); ?></div>
                <div style="font-size: 11px; color: #666;"><?php _e('تحلیل امروز', 'forooshyar'); ?></div>
            </div>
        </div>
    </div>

    <!-- Navigation Tabs -->
    <nav class="nav-tab-wrapper wp-clearfix">
        <a href="<?php echo esc_url($baseUrl); ?>" class="nav-tab <?php echo $currentTab === 'overview' ? 'nav-tab-active' : ''; ?>">
            <?php _e('نمای کلی', 'forooshyar'); ?>
        </a>
        <a href="<?php echo esc_url($baseUrl . '&tab=pending'); ?>" class="nav-tab <?php echo $currentTab === 'pending' ? 'nav-tab-active' : ''; ?>">
            <?php _e('در انتظار تأیید', 'forooshyar'); ?>
            <?php if ($actionCounts['pending'] + $actionCounts['approved'] > 0): ?>
            <span class="count" style="background: #0073aa; color: #fff; padding: 2px 6px; border-radius: 10px; font-size: 11px; margin-right: 5px;">
                <?php echo $actionCounts['pending'] + $actionCounts['approved']; ?>
            </span>
            <?php endif; ?>
        </a>
        <a href="<?php echo esc_url($baseUrl . '&tab=actions'); ?>" class="nav-tab <?php echo $currentTab === 'actions' ? 'nav-tab-active' : ''; ?>">
            <?php _e('همه اقدامات', 'forooshyar'); ?>
            <span class="count" style="background: #ddd; color: #555; padding: 2px 6px; border-radius: 10px; font-size: 11px; margin-right: 5px;">
                <?php echo $actionCounts['all']; ?>
            </span>
        </a>
        <a href="<?php echo esc_url($baseUrl . '&tab=analyses'); ?>" class="nav-tab <?php echo $currentTab === 'analyses' ? 'nav-tab-active' : ''; ?>">
            <?php _e('تحلیل‌ها', 'forooshyar'); ?>
            <span class="count" style="background: #ddd; color: #555; padding: 2px 6px; border-radius: 10px; font-size: 11px; margin-right: 5px;">
                <?php echo $analysisCounts['all']; ?>
            </span>
        </a>
        <a href="<?php echo esc_url($baseUrl . '&tab=completed'); ?>" class="nav-tab <?php echo $currentTab === 'completed' ? 'nav-tab-active' : ''; ?>">
            <?php _e('تاریخچه', 'forooshyar'); ?>
        </a>
    </nav>

    <div class="tab-content" style="background: #fff; border: 1px solid #ccd0d4; border-top: none; padding: 20px;">

        <?php if ($currentTab === 'overview'): ?>
        <!-- Overview Tab -->
        <div class="overview-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
            <!-- Recent Pending Actions -->
            <div class="overview-section">
                <h3 style="margin-top: 0; padding-bottom: 10px; border-bottom: 1px solid #eee;">
                    <?php _e('اقدامات در انتظار', 'forooshyar'); ?>
                    <a href="<?php echo esc_url($baseUrl . '&tab=pending'); ?>" style="font-size: 12px; font-weight: normal; margin-right: 10px;">
                        <?php _e('مشاهده همه', 'forooshyar'); ?> &larr;
                    </a>
                </h3>
                <?php if (!empty($actionsData['items'])): ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th style="width: 30%;"><?php _e('نوع', 'forooshyar'); ?></th>
                            <th style="width: 20%;"><?php _e('وضعیت', 'forooshyar'); ?></th>
                            <th style="width: 15%;"><?php _e('اولویت', 'forooshyar'); ?></th>
                            <th style="width: 35%;"><?php _e('تاریخ', 'forooshyar'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $pendingItems = array_filter($actionsData['items'], function($a) {
                            return in_array($a['status'], ['pending', 'approved']);
                        });
                        if (empty($pendingItems)): ?>
                        <tr><td colspan="4" style="text-align: center; color: #666;"><?php _e('اقدامی در انتظار نیست', 'forooshyar'); ?></td></tr>
                        <?php else: foreach (array_slice($pendingItems, 0, 5) as $action): ?>
                        <tr>
                            <td>
                                <strong><?php echo esc_html($actionTypeLabels[$action['action_type']] ?? $action['action_type']); ?></strong>
                            </td>
                            <td>
                                <span style="display: inline-block; padding: 3px 8px; border-radius: 3px; font-size: 11px; background: <?php echo $statusColors[$action['status']] ?? '#ddd'; ?>; color: #fff;">
                                    <?php echo esc_html($statusLabels[$action['status']] ?? $action['status']); ?>
                                </span>
                            </td>
                            <td>
                                <span class="priority-<?php echo aiagent_priority_class($action['priority_score']); ?>" style="font-weight: 600;">
                                    <?php echo esc_html($action['priority_score']); ?>
                                </span>
                            </td>
                            <td style="font-size: 12px; color: #666;">
                                <?php echo aiagent_format_date($action['created_at']); ?>
                            </td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <p style="color: #666; text-align: center;"><?php _e('هنوز اقدامی ثبت نشده است', 'forooshyar'); ?></p>
                <?php endif; ?>
            </div>

            <!-- Recent Analyses -->
            <div class="overview-section">
                <h3 style="margin-top: 0; padding-bottom: 10px; border-bottom: 1px solid #eee;">
                    <?php _e('تحلیل‌های اخیر', 'forooshyar'); ?>
                    <a href="<?php echo esc_url($baseUrl . '&tab=analyses'); ?>" style="font-size: 12px; font-weight: normal; margin-right: 10px;">
                        <?php _e('مشاهده همه', 'forooshyar'); ?> &larr;
                    </a>
                </h3>
                <?php if (!empty($analysesData['items'])): ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th style="width: 25%;"><?php _e('نوع', 'forooshyar'); ?></th>
                            <th style="width: 35%;"><?php _e('موجودیت', 'forooshyar'); ?></th>
                            <th style="width: 15%;"><?php _e('امتیاز', 'forooshyar'); ?></th>
                            <th style="width: 25%;"><?php _e('تاریخ', 'forooshyar'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($analysesData['items'] as $analysis): 
                            $entityName = '';
                            if ($analysis['entity_type'] === 'product') {
                                $product = wc_get_product($analysis['entity_id']);
                                $entityName = $product ? $product->get_name() : __('محصول', 'forooshyar') . ' #' . $analysis['entity_id'];
                            } else {
                                $entityName = __('مشتری', 'forooshyar') . ' #' . $analysis['entity_id'];
                            }
                        ?>
                        <tr>
                            <td><?php echo esc_html($entityTypeLabels[$analysis['entity_type']] ?? $analysis['entity_type']); ?></td>
                            <td><strong><?php echo esc_html($entityName); ?></strong></td>
                            <td>
                                <span class="priority-<?php echo aiagent_priority_class($analysis['priority_score']); ?>" style="font-weight: 600;">
                                    <?php echo esc_html($analysis['priority_score']); ?>
                                </span>
                            </td>
                            <td style="font-size: 12px; color: #666;">
                                <?php echo aiagent_format_date($analysis['created_at']); ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <p style="color: #666; text-align: center;"><?php _e('هنوز تحلیلی انجام نشده است', 'forooshyar'); ?></p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Activity Chart -->
        <div style="margin-top: 30px;">
            <h3 style="margin-top: 0; padding-bottom: 10px; border-bottom: 1px solid #eee;">
                <?php _e('فعالیت ۳۰ روز گذشته', 'forooshyar'); ?>
            </h3>
            <div style="position: relative; height: 250px; width: 100%;">
                <canvas id="activity-chart"></canvas>
            </div>
        </div>

        <?php elseif ($currentTab === 'pending'): ?>
        <!-- Pending Actions Tab -->
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
            <h3 style="margin: 0;"><?php _e('اقدامات در انتظار اجرا', 'forooshyar'); ?></h3>
            <?php if (!empty($actionsData['items'])): ?>
            <div style="display: flex; gap: 10px;">
                <button type="button" class="button button-primary" id="btn-approve-all">
                    <span class="dashicons dashicons-yes" style="vertical-align: middle;"></span>
                    <?php _e('تأیید همه', 'forooshyar'); ?>
                </button>
                <button type="button" class="button" id="btn-dismiss-all" style="color: #a00;">
                    <span class="dashicons dashicons-no" style="vertical-align: middle;"></span>
                    <?php _e('حذف همه', 'forooshyar'); ?>
                </button>
            </div>
            <?php endif; ?>
        </div>
        
        <?php if (!empty($actionsData['items'])): ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th style="width: 5%;"><?php _e('شناسه', 'forooshyar'); ?></th>
                    <th style="width: 18%;"><?php _e('نوع اقدام', 'forooshyar'); ?></th>
                    <th style="width: 8%;"><?php _e('اولویت', 'forooshyar'); ?></th>
                    <th style="width: 34%;"><?php _e('توضیحات هوش مصنوعی', 'forooshyar'); ?></th>
                    <th style="width: 15%;"><?php _e('تاریخ', 'forooshyar'); ?></th>
                    <th style="width: 20%;"><?php _e('عملیات', 'forooshyar'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($actionsData['items'] as $action): 
                    $actionData = $action['action_data'] ?? [];
                    $reasoning = aiagent_get_action_reasoning($action);
                    
                    // Get entity info for display
                    $entityInfo = '';
                    if (!empty($actionData['entity_type']) && !empty($actionData['entity_id'])) {
                        $entityType = $actionData['entity_type'] === 'product' ? 'محصول' : 'مشتری';
                        $entityInfo = $entityType . ' #' . $actionData['entity_id'];
                        
                        if ($actionData['entity_type'] === 'product' && function_exists('wc_get_product')) {
                            $product = wc_get_product($actionData['entity_id']);
                            if ($product) {
                                $entityInfo = $product->get_name();
                            }
                        }
                    } elseif (!empty($actionData['product_id'])) {
                        $entityInfo = 'محصول #' . $actionData['product_id'];
                        if (function_exists('wc_get_product')) {
                            $product = wc_get_product($actionData['product_id']);
                            if ($product) {
                                $entityInfo = $product->get_name();
                            }
                        }
                    } elseif (!empty($actionData['customer_id'])) {
                        $entityInfo = 'مشتری #' . $actionData['customer_id'];
                    }
                ?>
                <tr>
                    <td><?php echo esc_html($action['id']); ?></td>
                    <td>
                        <strong><?php echo esc_html($actionTypeLabels[$action['action_type']] ?? $action['action_type']); ?></strong>
                        <?php if ($entityInfo): ?>
                        <br><small style="color: #666;"><?php echo esc_html($entityInfo); ?></small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="priority-<?php echo aiagent_priority_class($action['priority_score']); ?>" style="font-weight: 600; padding: 3px 8px; border-radius: 3px; background: <?php echo $action['priority_score'] >= 70 ? '#ffeaea' : ($action['priority_score'] >= 50 ? '#fff3cd' : '#e8f5e9'); ?>;">
                            <?php echo esc_html($action['priority_score']); ?>
                        </span>
                    </td>
                    <td>
                        <?php 
                        $actionDetails = aiagent_format_action_details($action['action_type'], $actionData);
                        if ($actionDetails): ?>
                        <?php echo $actionDetails; ?>
                        <?php endif; ?>
                        <?php if ($reasoning): ?>
                        <div style="max-height: 40px; overflow: hidden; font-size: 11px; color: #777; line-height: 1.4; margin-top: 5px;">
                            <?php echo esc_html($reasoning); ?>
                        </div>
                        <?php elseif (!$actionDetails): ?>
                        <span style="color: #999;">-</span>
                        <?php endif; ?>
                    </td>
                    <td style="font-size: 12px; color: #666;">
                        <?php echo aiagent_format_date($action['created_at']); ?>
                    </td>
                    <td>
                        <button type="button" class="button button-small button-primary btn-execute-action" data-id="<?php echo esc_attr($action['id']); ?>">
                            <?php _e('اجرا', 'forooshyar'); ?>
                        </button>
                        <button type="button" class="button button-small btn-dismiss-action" data-id="<?php echo esc_attr($action['id']); ?>" style="color: #a00;">
                            <?php _e('حذف', 'forooshyar'); ?>
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <?php 
        // Pagination
        if ($actionsData['total_pages'] > 1):
            $paginationBase = $baseUrl . '&tab=pending&paged=%#%';
        ?>
        <div class="tablenav bottom">
            <div class="tablenav-pages">
                <span class="displaying-num">
                    <?php printf(__('%s مورد', 'forooshyar'), number_format_i18n($actionsData['total'])); ?>
                </span>
                <?php
                echo paginate_links([
                    'base' => $paginationBase,
                    'format' => '',
                    'current' => $currentPage,
                    'total' => $actionsData['total_pages'],
                    'prev_text' => '&rarr;',
                    'next_text' => '&larr;',
                ]);
                ?>
            </div>
        </div>
        <?php endif; ?>
        
        <?php else: ?>
        <p style="text-align: center; color: #666; padding: 40px 0;">
            <?php _e('هیچ اقدامی در انتظار نیست.', 'forooshyar'); ?>
        </p>
        <?php endif; ?>

        <?php elseif ($currentTab === 'actions'): ?>
        <!-- All Actions Tab -->
        <div style="margin-bottom: 15px; display: flex; justify-content: space-between; align-items: center;">
            <form method="get" style="display: inline-flex; gap: 10px; align-items: center;">
                <input type="hidden" name="page" value="forooshyar-ai-agent">
                <input type="hidden" name="tab" value="actions">
                <select name="status" style="min-width: 150px;">
                    <option value=""><?php _e('همه وضعیت‌ها', 'forooshyar'); ?></option>
                    <?php foreach ($statusLabels as $key => $label): ?>
                    <option value="<?php echo esc_attr($key); ?>" <?php selected($statusFilter, $key); ?>><?php echo esc_html($label); ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="button"><?php _e('فیلتر', 'forooshyar'); ?></button>
            </form>
            <?php 
            // Show bulk buttons only when viewing pending/approved or all actions
            $hasPendingActions = false;
            if (!empty($actionsData['items'])) {
                foreach ($actionsData['items'] as $a) {
                    if (in_array($a['status'], ['pending', 'approved'])) {
                        $hasPendingActions = true;
                        break;
                    }
                }
            }
            if ($hasPendingActions): 
            ?>
            <div style="display: flex; gap: 10px;">
                <button type="button" class="button button-primary" id="btn-approve-all-actions">
                    <span class="dashicons dashicons-yes" style="vertical-align: middle;"></span>
                    <?php _e('تأیید همه در انتظار', 'forooshyar'); ?>
                </button>
                <button type="button" class="button" id="btn-dismiss-all-actions" style="color: #a00;">
                    <span class="dashicons dashicons-no" style="vertical-align: middle;"></span>
                    <?php _e('حذف همه در انتظار', 'forooshyar'); ?>
                </button>
            </div>
            <?php endif; ?>
        </div>

        <?php if (!empty($actionsData['items'])): ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th style="width: 5%;"><?php _e('شناسه', 'forooshyar'); ?></th>
                    <th style="width: 15%;"><?php _e('نوع اقدام', 'forooshyar'); ?></th>
                    <th style="width: 10%;"><?php _e('وضعیت', 'forooshyar'); ?></th>
                    <th style="width: 8%;"><?php _e('اولویت', 'forooshyar'); ?></th>
                    <th style="width: 25%;"><?php _e('توضیحات هوش مصنوعی', 'forooshyar'); ?></th>
                    <th style="width: 15%;"><?php _e('تاریخ ایجاد', 'forooshyar'); ?></th>
                    <th style="width: 17%;"><?php _e('عملیات', 'forooshyar'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($actionsData['items'] as $action): 
                    $actionData = $action['action_data'] ?? [];
                    $reasoning = aiagent_get_action_reasoning($action);
                    
                    // Get entity info for display
                    $entityInfo = '';
                    if (!empty($actionData['entity_type']) && !empty($actionData['entity_id'])) {
                        $entityType = $actionData['entity_type'] === 'product' ? 'محصول' : 'مشتری';
                        $entityInfo = $entityType . ' #' . $actionData['entity_id'];
                        
                        // Try to get product name
                        if ($actionData['entity_type'] === 'product' && function_exists('wc_get_product')) {
                            $product = wc_get_product($actionData['entity_id']);
                            if ($product) {
                                $entityInfo = $product->get_name();
                            }
                        }
                    } elseif (!empty($actionData['product_id'])) {
                        $entityInfo = 'محصول #' . $actionData['product_id'];
                        if (function_exists('wc_get_product')) {
                            $product = wc_get_product($actionData['product_id']);
                            if ($product) {
                                $entityInfo = $product->get_name();
                            }
                        }
                    } elseif (!empty($actionData['customer_id'])) {
                        $entityInfo = 'مشتری #' . $actionData['customer_id'];
                    }
                ?>
                <tr>
                    <td><?php echo esc_html($action['id']); ?></td>
                    <td>
                        <strong><?php echo esc_html($actionTypeLabels[$action['action_type']] ?? $action['action_type']); ?></strong>
                        <?php if ($entityInfo): ?>
                        <br><small style="color: #666;"><?php echo esc_html($entityInfo); ?></small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span style="display: inline-block; padding: 3px 8px; border-radius: 3px; font-size: 11px; background: <?php echo $statusColors[$action['status']] ?? '#ddd'; ?>; color: #fff;">
                            <?php echo esc_html($statusLabels[$action['status']] ?? $action['status']); ?>
                        </span>
                    </td>
                    <td>
                        <span style="font-weight: 600;"><?php echo esc_html($action['priority_score']); ?></span>
                    </td>
                    <td>
                        <?php 
                        $actionDetails = aiagent_format_action_details($action['action_type'], $actionData);
                        if ($actionDetails): ?>
                        <?php echo $actionDetails; ?>
                        <?php endif; ?>
                        <?php if ($reasoning): ?>
                        <div style="max-height: 40px; overflow: hidden; font-size: 11px; color: #777; line-height: 1.4; margin-top: 5px;">
                            <?php echo esc_html($reasoning); ?>
                        </div>
                        <?php elseif (!$actionDetails): ?>
                        <span style="color: #999;">-</span>
                        <?php endif; ?>
                    </td>
                    <td style="font-size: 12px; color: #666;">
                        <?php echo aiagent_format_date($action['created_at']); ?>
                    </td>
                    <td>
                        <?php if (in_array($action['status'], ['pending', 'approved'])): ?>
                            <button type="button" class="button button-small button-primary btn-execute-action" data-id="<?php echo esc_attr($action['id']); ?>">
                                <?php _e('اجرا', 'forooshyar'); ?>
                            </button>
                            <button type="button" class="button button-small btn-dismiss-action" data-id="<?php echo esc_attr($action['id']); ?>" style="color: #a00;">
                                <?php _e('رد', 'forooshyar'); ?>
                            </button>
                        <?php else: ?>
                            <span style="color: #999; font-size: 12px;">-</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <?php 
        // Pagination
        if ($actionsData['total_pages'] > 1):
            $paginationBase = $baseUrl . '&tab=actions' . ($statusFilter ? '&status=' . $statusFilter : '') . '&paged=%#%';
        ?>
        <div class="tablenav bottom">
            <div class="tablenav-pages">
                <span class="displaying-num">
                    <?php printf(__('%s مورد', 'forooshyar'), number_format_i18n($actionsData['total'])); ?>
                </span>
                <?php
                echo paginate_links([
                    'base' => $paginationBase,
                    'format' => '',
                    'current' => $currentPage,
                    'total' => $actionsData['total_pages'],
                    'prev_text' => '&rarr;',
                    'next_text' => '&larr;',
                ]);
                ?>
            </div>
        </div>
        <?php endif; ?>
        
        <?php else: ?>
        <p style="text-align: center; color: #666; padding: 40px 0;">
            <?php _e('هیچ اقدامی یافت نشد.', 'forooshyar'); ?>
        </p>
        <?php endif; ?>

        <?php elseif ($currentTab === 'analyses'): ?>
        <!-- Analyses Tab -->
        <div style="margin-bottom: 15px;">
            <form method="get" style="display: inline-flex; gap: 10px; align-items: center;">
                <input type="hidden" name="page" value="forooshyar-ai-agent">
                <input type="hidden" name="tab" value="analyses">
                <select name="type" style="min-width: 150px;">
                    <option value=""><?php _e('همه انواع', 'forooshyar'); ?></option>
                    <?php foreach ($entityTypeLabels as $key => $label): ?>
                    <option value="<?php echo esc_attr($key); ?>" <?php selected($typeFilter, $key); ?>><?php echo esc_html($label); ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="button"><?php _e('فیلتر', 'forooshyar'); ?></button>
            </form>
        </div>

        <?php if (!empty($analysesData['items'])): ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th style="width: 5%;"><?php _e('شناسه', 'forooshyar'); ?></th>
                    <th style="width: 10%;"><?php _e('نوع', 'forooshyar'); ?></th>
                    <th style="width: 20%;"><?php _e('موجودیت', 'forooshyar'); ?></th>
                    <th style="width: 8%;"><?php _e('امتیاز', 'forooshyar'); ?></th>
                    <th style="width: 35%;"><?php _e('تحلیل هوش مصنوعی', 'forooshyar'); ?></th>
                    <th style="width: 10%;"><?php _e('پیشنهادات', 'forooshyar'); ?></th>
                    <th style="width: 12%;"><?php _e('تاریخ', 'forooshyar'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($analysesData['items'] as $analysis): 
                    $analysisData = $analysis['analysis_data'] ?? '';
                    if (is_array($analysisData)) {
                        $analysisText = $analysisData['analysis'] ?? wp_json_encode($analysisData);
                    } else {
                        $analysisText = $analysisData;
                    }
                    $suggestions = $analysis['suggestions'] ?? [];
                    
                    $entityName = '';
                    if ($analysis['entity_type'] === 'product') {
                        $product = wc_get_product($analysis['entity_id']);
                        $entityName = $product ? $product->get_name() : __('محصول', 'forooshyar') . ' #' . $analysis['entity_id'];
                    } else {
                        $entityName = __('مشتری', 'forooshyar') . ' #' . $analysis['entity_id'];
                    }
                ?>
                <tr>
                    <td><?php echo esc_html($analysis['id']); ?></td>
                    <td><?php echo esc_html($entityTypeLabels[$analysis['entity_type']] ?? $analysis['entity_type']); ?></td>
                    <td><strong><?php echo esc_html($entityName); ?></strong></td>
                    <td>
                        <span style="font-weight: 600; padding: 3px 8px; border-radius: 3px; background: <?php echo $analysis['priority_score'] >= 70 ? '#ffeaea' : ($analysis['priority_score'] >= 50 ? '#fff3cd' : '#e8f5e9'); ?>;">
                            <?php echo esc_html($analysis['priority_score']); ?>
                        </span>
                    </td>
                    <td>
                        <?php if ($analysisText): ?>
                        <div style="max-height: 80px; overflow: hidden; font-size: 12px; color: #555; line-height: 1.5;">
                            <?php echo esc_html($analysisText); ?>
                        </div>
                        <?php else: ?>
                        <span style="color: #999;">-</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if (!empty($suggestions)): 
                            // Build tooltip content with action types
                            $tooltipItems = [];
                            foreach ($suggestions as $suggestion) {
                                $type = $suggestion['type'] ?? '';
                                $typeLabel = $actionTypeLabels[$type] ?? $type;
                                $tooltipItems[] = '• ' . $typeLabel;
                            }
                            $tooltipContent = implode("\n", $tooltipItems);
                        ?>
                        <span class="suggestions-badge" 
                              style="background: #e7f3ff; color: #0073aa; padding: 3px 8px; border-radius: 3px; font-size: 11px; cursor: pointer; position: relative;"
                              data-suggestions="<?php echo esc_attr(wp_json_encode($suggestions)); ?>"
                              title="<?php echo esc_attr($tooltipContent); ?>">
                            <?php echo count($suggestions); ?> <?php _e('پیشنهاد', 'forooshyar'); ?>
                            <span class="dashicons dashicons-info" style="font-size: 14px; vertical-align: middle; margin-right: 3px;"></span>
                        </span>
                        <?php else: ?>
                        <span style="color: #999;">-</span>
                        <?php endif; ?>
                    </td>
                    <td style="font-size: 12px; color: #666;">
                        <?php echo aiagent_format_date($analysis['created_at']); ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <?php 
        // Pagination
        if ($analysesData['total_pages'] > 1):
            $paginationBase = $baseUrl . '&tab=analyses' . ($typeFilter ? '&type=' . $typeFilter : '') . '&paged=%#%';
        ?>
        <div class="tablenav bottom">
            <div class="tablenav-pages">
                <span class="displaying-num">
                    <?php printf(__('%s مورد', 'forooshyar'), number_format_i18n($analysesData['total'])); ?>
                </span>
                <?php
                echo paginate_links([
                    'base' => $paginationBase,
                    'format' => '',
                    'current' => $currentPage,
                    'total' => $analysesData['total_pages'],
                    'prev_text' => '&rarr;',
                    'next_text' => '&larr;',
                ]);
                ?>
            </div>
        </div>
        <?php endif; ?>
        
        <?php else: ?>
        <p style="text-align: center; color: #666; padding: 40px 0;">
            <?php _e('هنوز تحلیلی انجام نشده است. روی "شروع تحلیل" کلیک کنید.', 'forooshyar'); ?>
        </p>
        <?php endif; ?>

        <?php elseif ($currentTab === 'completed'): ?>
        <!-- Completed/History Tab -->
        <h3 style="margin-top: 0;"><?php _e('تاریخچه اقدامات انجام شده', 'forooshyar'); ?></h3>
        
        <?php if (!empty($actionsData['items'])): ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th style="width: 5%;"><?php _e('شناسه', 'forooshyar'); ?></th>
                    <th style="width: 18%;"><?php _e('نوع اقدام', 'forooshyar'); ?></th>
                    <th style="width: 12%;"><?php _e('وضعیت', 'forooshyar'); ?></th>
                    <th style="width: 8%;"><?php _e('اولویت', 'forooshyar'); ?></th>
                    <th style="width: 25%;"><?php _e('توضیحات', 'forooshyar'); ?></th>
                    <th style="width: 16%;"><?php _e('تاریخ ایجاد', 'forooshyar'); ?></th>
                    <th style="width: 16%;"><?php _e('تاریخ اجرا', 'forooshyar'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($actionsData['items'] as $action): 
                    $actionData = $action['action_data'] ?? [];
                    $reasoning = aiagent_get_action_reasoning($action);
                ?>
                <tr>
                    <td><?php echo esc_html($action['id']); ?></td>
                    <td>
                        <strong><?php echo esc_html($actionTypeLabels[$action['action_type']] ?? $action['action_type']); ?></strong>
                    </td>
                    <td>
                        <span style="display: inline-block; padding: 3px 8px; border-radius: 3px; font-size: 11px; background: <?php echo $statusColors[$action['status']] ?? '#ddd'; ?>; color: #fff;">
                            <?php echo esc_html($statusLabels[$action['status']] ?? $action['status']); ?>
                        </span>
                    </td>
                    <td>
                        <span style="font-weight: 600;"><?php echo esc_html($action['priority_score']); ?></span>
                    </td>
                    <td>
                        <?php 
                        $actionDetails = aiagent_format_action_details($action['action_type'], $actionData);
                        if ($actionDetails): ?>
                        <?php echo $actionDetails; ?>
                        <?php endif; ?>
                        <?php if ($reasoning): ?>
                        <div style="max-height: 30px; overflow: hidden; font-size: 11px; color: #777; margin-top: 5px;">
                            <?php echo esc_html($reasoning); ?>
                        </div>
                        <?php elseif (!$actionDetails): ?>
                        <span style="color: #999;">-</span>
                        <?php endif; ?>
                    </td>
                    <td style="font-size: 12px; color: #666;">
                        <?php echo aiagent_format_date($action['created_at']); ?>
                    </td>
                    <td style="font-size: 12px; color: #666;">
                        <?php echo aiagent_format_date($action['executed_at'] ?? ''); ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <?php 
        // Pagination
        if ($actionsData['total_pages'] > 1):
            $paginationBase = $baseUrl . '&tab=completed&paged=%#%';
        ?>
        <div class="tablenav bottom">
            <div class="tablenav-pages">
                <span class="displaying-num">
                    <?php printf(__('%s مورد', 'forooshyar'), number_format_i18n($actionsData['total'])); ?>
                </span>
                <?php
                echo paginate_links([
                    'base' => $paginationBase,
                    'format' => '',
                    'current' => $currentPage,
                    'total' => $actionsData['total_pages'],
                    'prev_text' => '&rarr;',
                    'next_text' => '&larr;',
                ]);
                ?>
            </div>
        </div>
        <?php endif; ?>
        
        <?php else: ?>
        <p style="text-align: center; color: #666; padding: 40px 0;">
            <?php _e('هنوز اقدامی انجام نشده است.', 'forooshyar'); ?>
        </p>
        <?php endif; ?>

        <?php endif; ?>
    </div>
</div>

<!-- Suggestions Modal -->
<div id="suggestions-modal" class="aiagent-modal" style="display: none;">
    <div class="aiagent-modal-overlay"></div>
    <div class="aiagent-modal-content" dir="rtl">
        <div class="aiagent-modal-header">
            <h3><?php _e('پیشنهادات هوش مصنوعی', 'forooshyar'); ?></h3>
            <button type="button" class="aiagent-modal-close">&times;</button>
        </div>
        <div class="aiagent-modal-body">
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width: 30%;"><?php _e('نوع اقدام', 'forooshyar'); ?></th>
                        <th style="width: 15%;"><?php _e('اولویت', 'forooshyar'); ?></th>
                        <th style="width: 55%;"><?php _e('توضیحات', 'forooshyar'); ?></th>
                    </tr>
                </thead>
                <tbody id="suggestions-modal-body">
                </tbody>
            </table>
        </div>
    </div>
</div>

<style>
/* Priority colors */
.priority-high { color: #d9534f; }
.priority-medium { color: #f0ad4e; }
.priority-low { color: #5cb85c; }

/* RTL fixes for pagination */
[dir="rtl"] .tablenav-pages .pagination-links {
    direction: ltr;
    display: inline-flex;
}

/* Table improvements */
.wp-list-table td {
    vertical-align: middle;
}

/* Button loading state */
.button.loading {
    opacity: 0.7;
    pointer-events: none;
}

/* Nav tab count badges */
.nav-tab .count {
    vertical-align: middle;
}

/* Suggestions badge hover */
.suggestions-badge:hover {
    background: #cce5ff !important;
}

/* Action details styling */
.action-details {
    font-size: 12px;
    color: #333;
    line-height: 1.5;
    background: #f8f9fa;
    padding: 6px 10px;
    border-radius: 4px;
    border-right: 3px solid #0073aa;
}

.action-details strong {
    color: #0073aa;
}

.action-details code {
    background: #e9ecef;
    padding: 1px 5px;
    border-radius: 3px;
    font-size: 11px;
}

.action-details .sep {
    color: #ccc;
    margin: 0 5px;
}

/* Modal styles */
.aiagent-modal {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: 100000;
}

.aiagent-modal-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.5);
}

.aiagent-modal-content {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 5px 30px rgba(0, 0, 0, 0.3);
    max-width: 700px;
    width: 90%;
    max-height: 80vh;
    overflow: hidden;
    display: flex;
    flex-direction: column;
}

.aiagent-modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px 20px;
    border-bottom: 1px solid #ddd;
    background: #f5f5f5;
}

.aiagent-modal-header h3 {
    margin: 0;
    font-size: 16px;
}

.aiagent-modal-close {
    background: none;
    border: none;
    font-size: 24px;
    cursor: pointer;
    color: #666;
    padding: 0;
    line-height: 1;
}

.aiagent-modal-close:hover {
    color: #d9534f;
}

.aiagent-modal-body {
    padding: 20px;
    overflow-y: auto;
    max-height: calc(80vh - 60px);
}

.aiagent-modal-body .wp-list-table {
    margin: 0;
}
</style>

<script>
jQuery(document).ready(function($) {
    // Initialize chart if on overview tab
    <?php if ($currentTab === 'overview' && !empty($stats['daily'])): ?>
    if (typeof Chart !== 'undefined') {
        var ctx = document.getElementById('activity-chart');
        if (ctx) {
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: <?php echo wp_json_encode(array_column($stats['daily'], 'date')); ?>,
                    datasets: [
                        {
                            label: '<?php _e('تحلیل‌ها', 'forooshyar'); ?>',
                            data: <?php echo wp_json_encode(array_column($stats['daily'], 'analyses')); ?>,
                            borderColor: '#0073aa',
                            backgroundColor: 'rgba(0, 115, 170, 0.1)',
                            fill: true,
                            tension: 0.3
                        },
                        {
                            label: '<?php _e('اقدامات', 'forooshyar'); ?>',
                            data: <?php echo wp_json_encode(array_column($stats['daily'], 'actions')); ?>,
                            borderColor: '#46b450',
                            backgroundColor: 'rgba(70, 180, 80, 0.1)',
                            fill: true,
                            tension: 0.3
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'top',
                            rtl: true
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: { stepSize: 1 }
                        }
                    }
                }
            });
        }
    }
    <?php endif; ?>

    // Start analysis
    $('#run-analysis').on('click', function() {
        var $btn = $(this);
        $btn.prop('disabled', true).text('<?php _e('در حال شروع...', 'forooshyar'); ?>');
        
        $.ajax({
            url: aiagentAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'aiagent_start_analysis',
                nonce: aiagentAdmin.nonce,
                type: 'all'
            },
            success: function(response) {
                if (response.success) {
                    $('#analysis-progress-section').show();
                    $('#cancel-analysis').show();
                    $btn.hide();
                    startProgressPolling();
                } else {
                    alert(response.data.message || '<?php _e('خطا در شروع تحلیل', 'forooshyar'); ?>');
                    $btn.prop('disabled', false).text('<?php _e('شروع تحلیل', 'forooshyar'); ?>');
                }
            },
            error: function() {
                alert('<?php _e('خطا در ارتباط با سرور', 'forooshyar'); ?>');
                $btn.prop('disabled', false).text('<?php _e('شروع تحلیل', 'forooshyar'); ?>');
            }
        });
    });

    // Cancel analysis
    $('#cancel-analysis').on('click', function() {
        var $btn = $(this);
        $btn.prop('disabled', true).text('<?php _e('در حال لغو...', 'forooshyar'); ?>');
        
        $.ajax({
            url: aiagentAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'aiagent_cancel_analysis',
                nonce: aiagentAdmin.nonce
            },
            success: function() {
                stopProgressPolling();
                resetAnalysisUI();
            },
            error: function() {
                stopProgressPolling();
                resetAnalysisUI();
            }
        });
    });

    // Dismiss/Reject action (deletes permanently)
    $(document).on('click', '.btn-dismiss-action', function() {
        var $btn = $(this);
        var actionId = $btn.data('id');
        
        if (!confirm('<?php _e('آیا از حذف این اقدام اطمینان دارید؟', 'forooshyar'); ?>')) return;
        
        $btn.addClass('loading').text('...');
        
        $.ajax({
            url: aiagentAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'aiagent_dismiss_action',
                nonce: aiagentAdmin.nonce,
                action_id: actionId
            },
            success: function(response) {
                if (response.success) {
                    $btn.closest('tr').fadeOut(300, function() {
                        $(this).remove();
                    });
                } else {
                    alert(response.data.message || '<?php _e('خطا', 'forooshyar'); ?>');
                    $btn.removeClass('loading').text('<?php _e('حذف', 'forooshyar'); ?>');
                }
            },
            error: function() {
                alert('<?php _e('خطا در ارتباط با سرور', 'forooshyar'); ?>');
                $btn.removeClass('loading').text('<?php _e('حذف', 'forooshyar'); ?>');
            }
        });
    });

    // Approve all pending actions
    $('#btn-approve-all').on('click', function() {
        if (!confirm('<?php _e('آیا از تأیید همه اقدامات در انتظار اطمینان دارید؟', 'forooshyar'); ?>')) return;
        
        var $btn = $(this);
        $btn.prop('disabled', true).text('<?php _e('در حال تأیید...', 'forooshyar'); ?>');
        
        $.ajax({
            url: aiagentAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'aiagent_approve_all_actions',
                nonce: aiagentAdmin.nonce
            },
            success: function(response) {
                if (response.success) {
                    alert(response.data.message);
                    location.reload();
                } else {
                    alert(response.data.message || '<?php _e('خطا', 'forooshyar'); ?>');
                    $btn.prop('disabled', false).html('<span class="dashicons dashicons-yes" style="vertical-align: middle;"></span> <?php _e('تأیید همه', 'forooshyar'); ?>');
                }
            },
            error: function() {
                alert('<?php _e('خطا در ارتباط با سرور', 'forooshyar'); ?>');
                $btn.prop('disabled', false).html('<span class="dashicons dashicons-yes" style="vertical-align: middle;"></span> <?php _e('تأیید همه', 'forooshyar'); ?>');
            }
        });
    });

    // Dismiss all pending actions
    $('#btn-dismiss-all').on('click', function() {
        if (!confirm('<?php _e('آیا از حذف همه اقدامات در انتظار اطمینان دارید؟ این عمل قابل بازگشت نیست.', 'forooshyar'); ?>')) return;
        
        var $btn = $(this);
        $btn.prop('disabled', true).text('<?php _e('در حال حذف...', 'forooshyar'); ?>');
        
        $.ajax({
            url: aiagentAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'aiagent_dismiss_all_actions',
                nonce: aiagentAdmin.nonce
            },
            success: function(response) {
                if (response.success) {
                    alert(response.data.message);
                    location.reload();
                } else {
                    alert(response.data.message || '<?php _e('خطا', 'forooshyar'); ?>');
                    $btn.prop('disabled', false).html('<span class="dashicons dashicons-no" style="vertical-align: middle;"></span> <?php _e('حذف همه', 'forooshyar'); ?>');
                }
            },
            error: function() {
                alert('<?php _e('خطا در ارتباط با سرور', 'forooshyar'); ?>');
                $btn.prop('disabled', false).html('<span class="dashicons dashicons-no" style="vertical-align: middle;"></span> <?php _e('حذف همه', 'forooshyar'); ?>');
            }
        });
    });

    // Approve all actions (from All Actions tab)
    $('#btn-approve-all-actions').on('click', function() {
        if (!confirm('<?php _e('آیا از تأیید همه اقدامات در انتظار اطمینان دارید؟', 'forooshyar'); ?>')) return;
        
        var $btn = $(this);
        $btn.prop('disabled', true).text('<?php _e('در حال تأیید...', 'forooshyar'); ?>');
        
        $.ajax({
            url: aiagentAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'aiagent_approve_all_actions',
                nonce: aiagentAdmin.nonce
            },
            success: function(response) {
                if (response.success) {
                    alert(response.data.message);
                    location.reload();
                } else {
                    alert(response.data.message || '<?php _e('خطا', 'forooshyar'); ?>');
                    $btn.prop('disabled', false).html('<span class="dashicons dashicons-yes" style="vertical-align: middle;"></span> <?php _e('تأیید همه در انتظار', 'forooshyar'); ?>');
                }
            },
            error: function() {
                alert('<?php _e('خطا در ارتباط با سرور', 'forooshyar'); ?>');
                $btn.prop('disabled', false).html('<span class="dashicons dashicons-yes" style="vertical-align: middle;"></span> <?php _e('تأیید همه در انتظار', 'forooshyar'); ?>');
            }
        });
    });

    // Dismiss all actions (from All Actions tab)
    $('#btn-dismiss-all-actions').on('click', function() {
        if (!confirm('<?php _e('آیا از حذف همه اقدامات در انتظار اطمینان دارید؟ این عمل قابل بازگشت نیست.', 'forooshyar'); ?>')) return;
        
        var $btn = $(this);
        $btn.prop('disabled', true).text('<?php _e('در حال حذف...', 'forooshyar'); ?>');
        
        $.ajax({
            url: aiagentAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'aiagent_dismiss_all_actions',
                nonce: aiagentAdmin.nonce
            },
            success: function(response) {
                if (response.success) {
                    alert(response.data.message);
                    location.reload();
                } else {
                    alert(response.data.message || '<?php _e('خطا', 'forooshyar'); ?>');
                    $btn.prop('disabled', false).html('<span class="dashicons dashicons-no" style="vertical-align: middle;"></span> <?php _e('حذف همه در انتظار', 'forooshyar'); ?>');
                }
            },
            error: function() {
                alert('<?php _e('خطا در ارتباط با سرور', 'forooshyar'); ?>');
                $btn.prop('disabled', false).html('<span class="dashicons dashicons-no" style="vertical-align: middle;"></span> <?php _e('حذف همه در انتظار', 'forooshyar'); ?>');
            }
        });
    });

    // Execute action
    $(document).on('click', '.btn-execute-action', function() {
        var $btn = $(this);
        var $row = $btn.closest('tr');
        var actionId = $btn.data('id');
        
        if (!confirm('<?php _e('آیا از اجرای این اقدام اطمینان دارید؟', 'forooshyar'); ?>')) return;
        
        $btn.addClass('loading').prop('disabled', true).text('...');
        
        $.ajax({
            url: aiagentAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'aiagent_execute_action',
                nonce: aiagentAdmin.nonce,
                action_id: actionId
            },
            success: function(response) {
                if (response.success) {
                    // Show success message
                    var message = response.data.message || '<?php _e('اقدام با موفقیت اجرا شد', 'forooshyar'); ?>';
                    
                    // Update the row status or remove it
                    $row.find('.btn-execute-action, .btn-dismiss-action').remove();
                    $row.find('td:last').html('<span style="color: #46b450; font-weight: bold;"><?php _e('انجام شد', 'forooshyar'); ?></span>');
                    
                    // Update status badge if exists
                    var $statusBadge = $row.find('span[style*="background"]').first();
                    if ($statusBadge.length) {
                        $statusBadge.css('background', '#5cb85c').text('<?php _e('انجام شده', 'forooshyar'); ?>');
                    }
                    
                    // Fade the row slightly to indicate completion
                    $row.css('opacity', '0.7');
                    
                    // Show a brief notification
                    showNotification(message, 'success');
                } else {
                    var errorMsg = response.data.message || '<?php _e('خطا در اجرای اقدام', 'forooshyar'); ?>';
                    showNotification(errorMsg, 'error');
                    $btn.removeClass('loading').prop('disabled', false).text('<?php _e('اجرا', 'forooshyar'); ?>');
                }
            },
            error: function() {
                showNotification('<?php _e('خطا در ارتباط با سرور', 'forooshyar'); ?>', 'error');
                $btn.removeClass('loading').prop('disabled', false).text('<?php _e('اجرا', 'forooshyar'); ?>');
            }
        });
    });
    
    // Simple notification function
    function showNotification(message, type) {
        var bgColor = type === 'success' ? '#46b450' : '#dc3232';
        var $notification = $('<div class="aiagent-notification" style="position: fixed; top: 50px; left: 50%; transform: translateX(-50%); background: ' + bgColor + '; color: #fff; padding: 12px 24px; border-radius: 4px; z-index: 100001; box-shadow: 0 2px 10px rgba(0,0,0,0.2);">' + message + '</div>');
        $('body').append($notification);
        setTimeout(function() {
            $notification.fadeOut(300, function() { $(this).remove(); });
        }, 3000);
    }

    // Progress polling
    var progressInterval = null;
    
    function startProgressPolling() {
        progressInterval = setInterval(function() {
            $.ajax({
                url: aiagentAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'aiagent_get_analysis_progress',
                    nonce: aiagentAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        updateProgressUI(response.data);
                        
                        if (response.data.status === 'completed' || response.data.status === 'failed' || response.data.status === 'cancelled') {
                            stopProgressPolling();
                            if (response.data.status === 'completed') {
                                setTimeout(function() { location.reload(); }, 1500);
                            } else {
                                resetAnalysisUI();
                            }
                        }
                    }
                }
            });
        }, 2000);
    }
    
    function stopProgressPolling() {
        if (progressInterval) {
            clearInterval(progressInterval);
            progressInterval = null;
        }
    }
    
    function updateProgressUI(data) {
        var percent = data.progress || 0;
        $('.progress-percent').text(percent + '%');
        $('.progress-bar-fill').css('width', percent + '%');
        $('#progress-products').text('<?php _e('محصولات:', 'forooshyar'); ?> ' + (data.products_analyzed || 0) + '/' + (data.products_total || 0));
        $('#progress-customers').text('<?php _e('مشتریان:', 'forooshyar'); ?> ' + (data.customers_analyzed || 0) + '/' + (data.customers_total || 0));
        $('#progress-actions').text('<?php _e('اقدامات:', 'forooshyar'); ?> ' + (data.actions_created || 0));
        if (data.current_item) {
            $('#current-item').text('<?php _e('در حال تحلیل:', 'forooshyar'); ?> ' + data.current_item);
        }
    }
    
    function resetAnalysisUI() {
        $('#analysis-progress-section').hide();
        $('#cancel-analysis').hide().prop('disabled', false).text('<?php _e('لغو تحلیل', 'forooshyar'); ?>');
        $('#run-analysis').show().prop('disabled', false).text('<?php _e('شروع تحلیل', 'forooshyar'); ?>');
        $('.progress-percent').text('0%');
        $('.progress-bar-fill').css('width', '0%');
    }

    // Check for ongoing analysis on page load
    $.ajax({
        url: aiagentAdmin.ajaxUrl,
        type: 'POST',
        data: {
            action: 'aiagent_get_analysis_progress',
            nonce: aiagentAdmin.nonce
        },
        success: function(response) {
            if (response.success && response.data.status === 'running') {
                $('#analysis-progress-section').show();
                $('#cancel-analysis').show();
                $('#run-analysis').hide();
                updateProgressUI(response.data);
                startProgressPolling();
            }
        }
    });

    // Action type labels for modal
    var actionTypeLabels = <?php echo wp_json_encode($actionTypeLabels); ?>;

    // Format action details for modal display
    function formatActionDetailsJS(type, data) {
        if (!data || typeof data !== 'object') return '';
        
        var details = [];
        
        switch (type) {
            case 'create_discount':
                if (data.discount_percent) details.push('<strong>' + data.discount_percent + '%</strong> تخفیف');
                if (data.discount_code) details.push('کد: <code>' + data.discount_code + '</code>');
                if (data.valid_days) details.push(data.valid_days + ' روز اعتبار');
                if (data.description) details.push(data.description);
                break;
            case 'send_email':
                if (data.subject) details.push('<strong>موضوع:</strong> ' + data.subject);
                if (data.message) {
                    var msg = data.message.length > 80 ? data.message.substring(0, 80) + '...' : data.message;
                    details.push('<strong>پیام:</strong> ' + msg);
                }
                break;
            case 'send_sms':
                if (data.message) details.push('<strong>پیامک:</strong> ' + data.message);
                break;
            case 'update_product':
                if (data.new_price) details.push('قیمت جدید: <strong>' + Number(data.new_price).toLocaleString() + '</strong>');
                if (data.price_change_percent) {
                    var sign = data.price_change_percent > 0 ? '+' : '';
                    details.push('تغییر: ' + sign + data.price_change_percent + '%');
                }
                if (data.update_description) details.push(data.update_description);
                break;
            case 'create_campaign':
                if (data.campaign_name) details.push('<strong>' + data.campaign_name + '</strong>');
                if (data.campaign_message) {
                    var msg = data.campaign_message.length > 60 ? data.campaign_message.substring(0, 60) + '...' : data.campaign_message;
                    details.push(msg);
                }
                if (data.duration_days) details.push(data.duration_days + ' روز');
                break;
            case 'schedule_followup':
                if (data.followup_days) details.push('پیگیری در ' + data.followup_days + ' روز آینده');
                if (data.followup_type) {
                    var typeLabel = data.followup_type === 'email' ? 'ایمیل' : 'پیامک';
                    details.push('از طریق ' + typeLabel);
                }
                if (data.followup_message) {
                    var msg = data.followup_message.length > 50 ? data.followup_message.substring(0, 50) + '...' : data.followup_message;
                    details.push(msg);
                }
                break;
            case 'inventory_alert':
                if (data.alert_message) details.push(data.alert_message);
                if (data.recommended_quantity) details.push('سفارش پیشنهادی: ' + data.recommended_quantity + ' عدد');
                break;
            case 'schedule_price_change':
                if (data.new_price) details.push('قیمت جدید: <strong>' + Number(data.new_price).toLocaleString() + '</strong>');
                if (data.change_date) details.push('تاریخ: ' + data.change_date);
                if (data.change_reason) details.push(data.change_reason);
                break;
            case 'create_bundle':
                if (data.bundle_name) details.push('<strong>' + data.bundle_name + '</strong>');
                if (data.bundle_discount) details.push(data.bundle_discount + '% تخفیف بسته');
                if (data.bundle_description) details.push(data.bundle_description);
                break;
            case 'loyalty_reward':
                if (data.reward_type) {
                    var typeLabels = { 'discount': 'تخفیف', 'free_shipping': 'ارسال رایگان', 'gift': 'هدیه' };
                    details.push('<strong>' + (typeLabels[data.reward_type] || data.reward_type) + '</strong>');
                }
                if (data.reward_value) details.push('مقدار: ' + data.reward_value);
                if (data.reward_code) details.push('کد: <code>' + data.reward_code + '</code>');
                if (data.reward_message) {
                    var msg = data.reward_message.length > 50 ? data.reward_message.substring(0, 50) + '...' : data.reward_message;
                    details.push(msg);
                }
                break;
        }
        
        if (details.length === 0) return '';
        return '<div class="action-details">' + details.join(' <span class="sep">|</span> ') + '</div>';
    }

    // Suggestions modal
    $(document).on('click', '.suggestions-badge', function(e) {
        e.preventDefault();
        var suggestions = $(this).data('suggestions');
        if (!suggestions || suggestions.length === 0) return;
        
        var $tbody = $('#suggestions-modal-body');
        $tbody.empty();
        
        $.each(suggestions, function(i, suggestion) {
            var type = suggestion.type || '';
            var typeLabel = actionTypeLabels[type] || type;
            var priority = suggestion.priority || 0;
            var reasoning = suggestion.reasoning || '-';
            var data = suggestion.data || {};
            
            var priorityClass = priority >= 70 ? 'high' : (priority >= 50 ? 'medium' : 'low');
            var priorityBg = priority >= 70 ? '#ffeaea' : (priority >= 50 ? '#fff3cd' : '#e8f5e9');
            
            // Format action details
            var actionDetails = formatActionDetailsJS(type, data);
            var descriptionHtml = '';
            if (actionDetails) {
                descriptionHtml += actionDetails;
            }
            if (reasoning && reasoning !== '-') {
                descriptionHtml += '<div style="font-size: 11px; color: #777; margin-top: 5px;">' + reasoning + '</div>';
            }
            if (!descriptionHtml) {
                descriptionHtml = '<span style="color: #999;">-</span>';
            }
            
            var row = '<tr>' +
                '<td><strong>' + typeLabel + '</strong></td>' +
                '<td><span style="font-weight: 600; padding: 3px 8px; border-radius: 3px; background: ' + priorityBg + ';">' + priority + '</span></td>' +
                '<td>' + descriptionHtml + '</td>' +
                '</tr>';
            $tbody.append(row);
        });
        
        $('#suggestions-modal').show();
    });

    // Close modal
    $(document).on('click', '.aiagent-modal-close, .aiagent-modal-overlay', function() {
        $('#suggestions-modal').hide();
    });

    // Close modal on escape key
    $(document).on('keydown', function(e) {
        if (e.key === 'Escape') {
            $('#suggestions-modal').hide();
        }
    });
});
</script>
