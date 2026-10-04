<?php

declare(strict_types=1);

use TicketsRecorrentesHesk\Web\AdminUi;

require_once dirname(__DIR__) . '/src/Web/AdminUi.php';
require_once dirname(__DIR__) . '/src/Web/DemoData.php';

$page = $_GET['page'] ?? 'recurrences';
$mode = $_GET['mode'] ?? 'new';
$exampleId = $_GET['id'] ?? null;
$response = (new AdminUi(getenv('ADMIN_UI_ENABLED') === '1'))->respond(
    is_string($page) ? $page : '',
    is_string($mode) ? $mode : '',
    (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'),
    is_string($exampleId) ? $exampleId : null
);

http_response_code($response['status']);
header('Content-Type: ' . $response['content_type']);
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');
header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; base-uri 'none'; form-action 'self'; frame-ancestors 'none'");

echo $response['body'];
