<?php

declare(strict_types=1);

return [

    /*
    | API key (Bearer token) — only needed for API calls: the queued
    | GenerateOgImage job and Shareglint::render(). Signed URLs need none.
    */
    'key' => env('SHAREGLINT_KEY'),

    /*
    | Service origin, without /v1. Point it at a self-hosted or renamed
    | deployment here; nothing else in the package hard-codes the host.
    */
    'base_url' => env('SHAREGLINT_BASE_URL', 'https://shareglint.com'),

    /*
    | Your account id (acc_…) and signing secret, both from the dashboard or
    | GET /v1/account. The id is public; the secret signs og:image URLs and
    | must stay on the server.
    */
    'account_id' => env('SHAREGLINT_ACCOUNT_ID'),

    'signing_secret' => env('SHAREGLINT_SIGNING_SECRET'),

    /*
    | Default template slug for models using HasOgImage, and the default file
    | type: png, jpg, webp or pdf.
    */
    'template' => env('SHAREGLINT_TEMPLATE', 'blog-og'),

    'extension' => env('SHAREGLINT_EXTENSION', 'png'),

    /* Seconds before an API call gives up. */
    'timeout' => (int) env('SHAREGLINT_TIMEOUT', 30),

    /* Where GenerateOgImage runs; null = the application's defaults. */
    'queue' => [
        'connection' => env('SHAREGLINT_QUEUE_CONNECTION'),
        'name' => env('SHAREGLINT_QUEUE'),
    ],

];
