<?php
/**
 * Create Campaign Action
 * 
 * @package Forooshyar\Modules\AIAgent\Actions
 */

namespace Forooshyar\Modules\AIAgent\Actions;

class CreateCampaignAction extends AbstractAction
{
    protected $type = 'create_campaign';
    protected $name = 'Create Campaign';
    protected $description = 'Create marketing campaign';
    protected $requiredFields = [];
    protected $optionalFields = ['campaign_name', 'campaign_message', 'target_audience', 'campaign_type', 'duration_days', 'message', 'channels', 'budget'];

    public function execute(array $data)
    {
        // Support LLM field names
        $campaignName = $this->getField($data, 'campaign_name', __('کمپین جدید', 'forooshyar'));
        $campaignMessage = $this->getField($data, 'campaign_message', $this->getField($data, 'message', ''));
        $targetAudience = $this->getField($data, 'target_audience', '');
        $campaignType = $this->getField($data, 'campaign_type', 'promotional');
        $durationDays = intval($this->getField($data, 'duration_days', 7));
        
        // Store campaign data for manual execution
        $campaignData = [
            'name' => sanitize_text_field($campaignName),
            'message' => wp_kses_post($campaignMessage),
            'audience' => sanitize_text_field($targetAudience),
            'type' => sanitize_text_field($campaignType),
            'duration_days' => $durationDays,
            'channels' => $this->getField($data, 'channels', []),
            'budget' => floatval($this->getField($data, 'budget', 0)),
            'entity_id' => $this->getField($data, 'entity_id'),
            'entity_type' => $this->getField($data, 'entity_type'),
            'created_at' => current_time('mysql'),
            'expires_at' => date('Y-m-d H:i:s', strtotime("+{$durationDays} days")),
        ];

        $campaignId = 'campaign_' . time();
        update_option('aiagent_' . $campaignId, $campaignData);

        return $this->success(__('کمپین ایجاد شد', 'forooshyar'), array_merge($campaignData, ['id' => $campaignId]));
    }
    
    public function validate(array $data)
    {
        $errors = [];
        
        // Check for campaign name
        $campaignName = $this->getField($data, 'campaign_name', '');
        if (empty($campaignName)) {
            $errors[] = __('نام کمپین الزامی است', 'forooshyar');
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }
}
