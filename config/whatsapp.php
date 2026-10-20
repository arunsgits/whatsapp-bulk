<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Bulk Send Enabled
    |--------------------------------------------------------------------------
    |
    | When false, bulk message campaigns cannot be created and no WhatsApp
    | messages are sent via bulk send (no charges applied). Use this for
    | testing. When true, bulk send works normally.
    |
    */

    'bulk_send_enabled' => env('BULK_SEND_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Webhook Verify Token (Meta "Verify token" field)
    |--------------------------------------------------------------------------
    |
    | Use the same value in Meta Developer → WhatsApp → Configuration → Webhook.
    | Default: test
    |
    */

    'webhook_verify_token' => env('WHATSAPP_HOOK_VERIFY_TOKEN', 'test'),

    /*
    |--------------------------------------------------------------------------
    | Forward Webhook (second project / same Meta app)
    |--------------------------------------------------------------------------
    |
    | Meta allows only one webhook URL per app. When this endpoint receives
    | verification or events, the same request is forwarded to the URL below
    | so another project (e.g. CRM) can process them too.
    |
    */

    'forward_webhook_enabled' => env('WHATSAPP_FORWARD_WEBHOOK_ENABLED', false),

    'forward_webhook_url' => env('WHATSAPP_FORWARD_WEBHOOK_URL', null),

    'forward_webhook_verify_token' => env('WHATSAPP_FORWARD_WEBHOOK_VERIFY_TOKEN', null),

];
