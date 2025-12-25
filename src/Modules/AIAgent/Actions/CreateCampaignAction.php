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
    protected $requiredFields = ['campaign_name', 'target_audience'];
    protected $optionalFields = ['message', 'channels', 'budget'];

    public function execute(array $data)
    {
        // Store campaign data for manual execution
        $campaignData = [
            'name' => sanitize_text_field($data['campaign_name']),
            'audience' => sanitize_text_field($data['target_audience']),
            'message' => isset($data['message']) ? wp_kses_post($data['message']) : '',
            'channels' => isset($data['channels']) ? $data['channels'] : [],
            'budget' => isset($data['budget']) ? floatval($data['budget']) : 0,
            'created_at' => current_time('mysql'),
        ];

        update_option('aiagent_campaign_' . time(), $campaignData);

        return $this->success('Campaign created', $campaignData);
    }
}
