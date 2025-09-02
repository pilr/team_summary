<?php
require_once '../config.php';
require_once '../classes/TeamsAPI.php';

header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$user_id = $_SESSION['user_id'];

try {
    // Get OpenAI API key
    $openai_key = trim(file_get_contents('../openai.txt'));
    
    if (empty($openai_key)) {
        echo json_encode(['success' => false, 'error' => 'OpenAI API key not found']);
        exit;
    }
    
    // Initialize Teams API
    $teamsAPI = new TeamsAPI($db, $user_id);
    
    // Get all channels
    $channels = $teamsAPI->getChannels();
    
    if (empty($channels)) {
        echo json_encode(['success' => false, 'error' => 'No channels found']);
        exit;
    }
    
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
    
    // Call OpenAI API
    $prompt = "Please analyze these Microsoft Teams messages and provide a comprehensive summary including:\n\n" .
              "1. **Key Discussion Topics**: What are the main subjects being discussed?\n" .
              "2. **Important Decisions**: Any decisions made or conclusions reached?\n" .
              "3. **Action Items**: Tasks or follow-ups mentioned?\n" .
              "4. **Team Activity**: Overall communication patterns and engagement?\n" .
              "5. **Notable Mentions**: Important announcements or highlights?\n\n" .
              "Format the response in clear sections with bullet points where appropriate.\n\n" .
              "Messages:\n" . $messageText;
    
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
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        echo json_encode(['success' => false, 'error' => 'OpenAI API request failed: ' . $response]);
        exit;
    }
    
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
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>