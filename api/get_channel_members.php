<?php
header('Content-Type: application/json');
session_start();

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

require_once '../teams_api.php';

$teamId = $_GET['teamId'] ?? '';
$channelId = $_GET['channelId'] ?? '';

if (empty($teamId) || empty($channelId)) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing teamId or channelId']);
    exit();
}

try {
    $teamsAPI = new TeamsAPIHelper();
    $members = $teamsAPI->getChannelMembers($teamId, $channelId);
    
    $formattedMembers = [];
    foreach ($members as $member) {
        $formattedMembers[] = [
            'id' => $member['id'] ?? '',
            'displayName' => $member['displayName'] ?? 'Unknown User',
            'email' => $member['email'] ?? '',
            'roles' => $member['roles'] ?? [],
            'userPrincipalName' => $member['userPrincipalName'] ?? ''
        ];
    }
    
    echo json_encode([
        'success' => true,
        'members' => $formattedMembers,
        'count' => count($formattedMembers)
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Failed to load channel members',
        'message' => $e->getMessage()
    ]);
}
?>