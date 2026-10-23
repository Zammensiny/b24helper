<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'ollama' => [
        'url' => env('OLLAMA_URL', 'http://127.0.0.1:11434'),
        'model' => env('OLLAMA_MODEL', 'qwen2.5:0.5b-instruct'),
        'system_prompt_path' => resource_path('ai/system_prompt_simple.txt'),
    ],

    'catalog' => [
        'path' => resource_path('ai/snapshot/catalog_snapshot.jsonl'),
        'metadata_path' => resource_path('ai/snapshot/snapshot_metadata.json'),
        'manifest_path' => resource_path('ai/snapshot/manifest.json'),
        'audit_path' => resource_path('ai/snapshot/data_audit.json'),
    ],

    'store_policy' => [
        'path' => resource_path('ai/store_policy.json'),
    ],

    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
        'proxy_token' => env('AI_API_TOKEN'),
        'system_prompt_path' => resource_path('ai/system_prompt_openai_v2.txt'),
        'response_schema_path' => resource_path('ai/response_schema.json'),
        'order_flow_path' => resource_path('ai/order_flow.txt'),
    ],

];
