<?php
/**
 * Simple test to verify basic AJAX functionality without GLPI
 */

// Set JSON content type
header('Content-Type: application/json');

// Check required parameters
if (!isset($_GET['rule_id']) || !isset($_GET['rule_type'])) {
    echo json_encode(['error' => 'Missing required parameters']);
    exit;
}

$rule_id = intval($_GET['rule_id']);
$rule_type = $_GET['rule_type'];

if ($rule_id <= 0) {
    echo json_encode(['error' => 'Invalid rule ID']);
    exit;
}

// Return mock data to test if basic AJAX works
$response = [
    'success' => true,
    'rule' => [
        'id' => $rule_id,
        'name' => 'Test Rule',
        'type' => $rule_type
    ],
    'stats' => [
        'total_installations' => 5,
        'unique_software' => 3,
        'unique_computers' => 2,
        'unique_users' => 1
    ],
    'installations' => []
];

echo json_encode($response);
?>