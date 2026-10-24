<?php

declare(strict_types=1);

return [

    /*
    | API key (Bearer token) — only needed for API calls: the queued
    | GenerateOgImage job and ImageApi::render(). Signed URLs need none.
    */
    'key' => env('IMAGE_API_KEY'),

    /*
    | Service origin, without /v1. Point it at a self-hosted or renamed
    | deployment here; nothing else in the package hard-codes the host.
    */
    'base_url' => env('IMAGE_API_BASE_URL', 'https://imageapi.dev'),

    /*
    | Your account id (acc_…) and signing secret, both from the dashboard or
    | GET /v1/account. The id is public; the secret signs og:image URLs and
    | must stay on the server.
    */
    'account_id' => env('IMAGE_API_ACCOUNT_ID'),

    'signing_secret' => env('IMAGE_API_SIGNING_SECRET'),

    /*
    | Default template slug for models using HasOgImage, and the default file
    | type: png, jpg, webp or pdf.
    */
    'template' => env('IMAGE_API_TEMPLATE', 'blog-og'),

    'extension' => env('IMAGE_API_EXTENSION', 'png'),

    /* Seconds before an API call gives up. */
    'timeout' => (int) env('IMAGE_API_TIMEOUT', 30),

    /* Where GenerateOgImage runs; null = the application's defaults. */
    'queue' => [
        'connection' => env('IMAGE_API_QUEUE_CONNECTION'),
        'name' => env('IMAGE_API_QUEUE'),
    ],

];
