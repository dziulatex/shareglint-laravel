<?php

// A stand-in for the API under `php -S`: records each request to
// $FAKE_API_LOG and answers POST /v1/images with $FAKE_API_STATUS.
$log = getenv('FAKE_API_LOG');
$status = (int) (@file_get_contents($log.'.status') ?: 200);

file_put_contents($log, json_encode([
    'method' => $_SERVER['REQUEST_METHOD'],
    'uri' => $_SERVER['REQUEST_URI'],
    'authorization' => $_SERVER['HTTP_AUTHORIZATION'] ?? null,
    'idempotency_key' => $_SERVER['HTTP_IDEMPOTENCY_KEY'] ?? null,
    'body' => json_decode((string) file_get_contents('php://input'), true),
])."\n", FILE_APPEND);

http_response_code($status);
header('Content-Type: application/json');

echo json_encode(match ($status) {
    200 => ['id' => 'img_1', 'status' => 'completed', 'format' => 'png', 'url' => 'https://cdn.example.com/renders/img_1.png'],
    402 => ['error' => 'quota_exceeded', 'message' => 'Quota exhausted.'],
    503 => ['error' => 'sync_overloaded', 'message' => 'Busy.', 'id' => 'img_2'],
    default => ['error' => 'server_error', 'message' => 'Boom.'],
});
