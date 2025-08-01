<?php
// Check for PHP errors and configuration
header('Content-Type: application/json');

$response = [
    'success' => true,
    'php_info' => [
        'version' => PHP_VERSION,
        'error_reporting' => error_reporting(),
        'display_errors' => ini_get('display_errors'),
        'log_errors' => ini_get('log_errors'),
        'error_log' => ini_get('error_log')
    ],
    'server_info' => [
        'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? 'unknown',
        'script_name' => $_SERVER['SCRIPT_NAME'] ?? 'unknown',
        'request_uri' => $_SERVER['REQUEST_URI'] ?? 'unknown'
    ],
    'file_info' => [
        'current_file' => __FILE__,
        'current_dir' => __DIR__,
        'parent_dir' => dirname(__DIR__)
    ]
];

echo json_encode($response, JSON_PRETTY_PRINT);
?>
