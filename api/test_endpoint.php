<?php
header('Content-Type: application/json');
echo json_encode([
    'success' => true, 
    'message' => 'API endpoint is accessible',
    'timestamp' => date('Y-m-d H:i:s')
]);
?>