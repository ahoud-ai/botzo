<?php

namespace App\Contracts;

interface MessagingGatewayContract
{
    public function sendMessage($contactUuId, $messageContent, $userId = null, $type = "text", $buttons = [], $header = [], $footer = null, $buttonLabel = null);

    public function sendTemplateMessageAsync($contactUuId, $templateContent, $userId = null, $campaignId = null, $mediaId = null);

    public function sendTemplateMessage($contactUuId, $templateContent, $userId = null, $campaignId = null, $mediaId = null);

    public function sendMedia($contactUuId, $mediaType, $mediaFileName, $mediaFilePath, $mediaUrl, $location, $caption = null, $transcription = null, array $mediaMetadata = []);
}
