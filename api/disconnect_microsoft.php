<?php
header('Content-Type: application/json');
session_start();
require_once '../database_helper.php';

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Invalid session']);
    exit();
}

try {
    global $db;
    $success = $db->deleteOAuthToken($user_id, 'microsoft');
    
    if ($success) {
        echo json_encode(['success' => true, 'message' => 'Microsoft account disconnected successfully']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to disconnect account']);
    }
    
} catch (Exception $e) {
    error_log("Disconnect Microsoft error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database error occurred']);
}
?>