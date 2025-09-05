<?php
// Prevent any output before JSON response
error_reporting(E_ALL);
ini_set('display_errors', 0);
ob_start();

session_start();
require_once '../config.php';
require_once '../user_teams_api.php';

// Clear any output buffer and send clean JSON
ob_clean();
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');
header('Expires: 0');

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'] ?? null;

if (!$user_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'User ID not found in session']);
    exit;
}

try {
    // Get OpenAI API key
    $openai_file = '../openai.txt';
    if (!file_exists($openai_file)) {
        echo json_encode(['success' => false, 'error' => 'OpenAI key file not found']);
        exit;
    }
    
    $openai_key = trim(file_get_contents($openai_file));
    
    if (empty($openai_key) || strlen($openai_key) < 10) {
        echo json_encode(['success' => false, 'error' => 'Invalid OpenAI API key']);
        exit;
    }
    
    // Check if OpenAI service is disabled
    if ($openai_key === 'DISABLED_OPENAI_SERVICE_UNAVAILABLE' || !preg_match('/^sk-[a-zA-Z0-9\-_]+$/', $openai_key)) {
        echo json_encode([
            'success' => false, 
            'error' => 'AI Summary service is temporarily unavailable. Please try again later or contact support if this persists.',
            'service_status' => 'disabled'
        ]);
        exit;
    }
    
    error_log("AI Summary: OpenAI key loaded successfully for user $user_id");
    
    // Initialize Teams API
    $userTeamsAPI = new UserTeamsAPIHelper($user_id);
    
    if (!$userTeamsAPI->isConnected()) {
        echo json_encode(['success' => false, 'error' => 'Microsoft account not connected']);
        exit;
    }
    
    $teamsAPI = $userTeamsAPI;
    
    // Get user's custom AI prompt from settings
    require_once '../database_helper.php';
    $dbHelper = new DatabaseHelper();
    $user_settings = $dbHelper->getUserSettings($user_id);
    
    // Default AI prompt if user hasn't customized it
    $defaultPrompt = 'Please analyze these Microsoft Teams messages and provide a comprehensive summary including:

1. **Key Discussion Topics**: What are the main subjects being discussed?
2. **Important Decisions**: Any decisions made or conclusions reached?
3. **Action Items**: Tasks or follow-ups mentioned?
4. **Team Activity**: Overall communication patterns and engagement?
5. **Notable Mentions**: Important announcements or highlights?

Format the response in clear sections with bullet points where appropriate.';
    
    $customPrompt = $user_settings['ai_summary_prompt'] ?? $defaultPrompt;
    error_log("AI Summary: Using custom prompt for user $user_id (length: " . strlen($customPrompt) . " chars)");
    
    // Get all channels
    $channels = $teamsAPI->getAllChannels();
    
    if (empty($channels)) {
        error_log("AI Summary: No channels found for user $user_id");
        echo json_encode(['success' => false, 'error' => 'No channels found']);
        exit;
    }
    
    error_log("AI Summary: Found " . count($channels) . " channels for user $user_id");
    
    // Collect messages from all channels
    $allMessages = [];
    $totalMessages = 0;
    $maxMessages = 200; // Limit to avoid token limits
    
    foreach ($channels as $channel) {
        if ($totalMessages >= $maxMessages) break;
        
        $messages = $teamsAPI->getChannelMessages($channel['teamId'], $channel['id'], min(50, $maxMessages - $totalMessages));
        
        foreach ($messages as $message) {
            if ($totalMessages >= $maxMessages) break;
            
            // Extract text content
            $content = '';
            if (isset($message['body']['content'])) {
                $content = strip_tags($message['body']['content']);
                $content = html_entity_decode($content);
                $content = trim($content);
            }
            
            if (!empty($content) && strlen($content) > 10) {
                $allMessages[] = [
                    'channel' => $channel['displayName'],
                    'team' => $channel['teamName'],
                    'author' => $message['from']['user']['displayName'] ?? 'Unknown',
                    'content' => $content,
                    'timestamp' => $message['createdDateTime']
                ];
                $totalMessages++;
            }
        }
    }
    
    if (empty($allMessages)) {
        echo json_encode(['success' => false, 'error' => 'No messages found to summarize']);
        exit;
    }
    
    // Prepare text for OpenAI
    $messageText = "Here are recent Microsoft Teams messages from various channels:\n\n";
    
    foreach ($allMessages as $msg) {
        $messageText .= "Channel: {$msg['channel']} (Team: {$msg['team']})\n";
        $messageText .= "Author: {$msg['author']}\n";
        $messageText .= "Message: {$msg['content']}\n";
        $messageText .= "Time: {$msg['timestamp']}\n\n";
    }
    
    // Truncate if too long (keep under token limits)
    if (strlen($messageText) > 8000) {
        $messageText = substr($messageText, 0, 8000) . "... (truncated)";
    }
    
    // Call OpenAI API using custom prompt
    $prompt = $customPrompt . "\n\nMessages:\n" . $messageText;
    
    $postData = json_encode([
        'model' => 'gpt-4o-mini',
        'messages' => [
            [
                'role' => 'system',
                'content' => 'You are an AI assistant that analyzes Microsoft Teams conversations to provide insightful summaries for team productivity and collaboration.'
            ],
            [
                'role' => 'user',
                'content' => $prompt
            ]
        ],
        'max_tokens' => 1000,
        'temperature' => 0.7
    ]);
    
    $ch = curl_init('https://api.openai.com/v1/chat/completions');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $openai_key
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    if ($curlError) {
        error_log("AI Summary: cURL error: " . $curlError);
        echo json_encode(['success' => false, 'error' => 'Connection error: ' . $curlError]);
        exit;
    }
    
    if ($httpCode !== 200) {
        error_log("AI Summary: OpenAI API error (HTTP $httpCode): " . $response);
        echo json_encode(['success' => false, 'error' => 'OpenAI API request failed (HTTP ' . $httpCode . '): ' . substr($response, 0, 200)]);
        exit;
    }
    
    error_log("AI Summary: OpenAI API call successful for user $user_id");
    
    $aiResponse = json_decode($response, true);
    
    if (!isset($aiResponse['choices'][0]['message']['content'])) {
        echo json_encode(['success' => false, 'error' => 'Invalid OpenAI response']);
        exit;
    }
    
    $summary = $aiResponse['choices'][0]['message']['content'];
    
    echo json_encode([
        'success' => true,
        'summary' => $summary,
        'message_count' => count($allMessages),
        'channels_analyzed' => count($channels),
        'generated_at' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    // Clear any output buffer to ensure clean JSON
    if (ob_get_level()) {
        ob_clean();
    }
    
    error_log("AI Summary: Exception for user $user_id: " . $e->getMessage());
    error_log("AI Summary: Stack trace: " . $e->getTraceAsString());
    
    // Send clean JSON error response
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error: ' . $e->getMessage()]);
}

// Ensure all output is sent
if (ob_get_level()) {
    ob_end_flush();
}
?>