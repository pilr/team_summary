<?php
/**
 * Cron Job Script - Maintain Teams Connections
 * Run this script every 15-30 minutes to maintain persistent Teams connections
 * 
 * Usage: php maintain_teams_connections.php
 * Or set up as a cron job: */15 * * * * /usr/bin/php /path/to/maintain_teams_connections.php
 */

require_once __DIR__ . '/persistent_teams_service.php';

// Prevent script from running in web context for security
if (isset($_SERVER['REQUEST_METHOD'])) {
    http_response_code(403);
    die('Access denied. This script can only be run from command line.');
}

echo "Starting Teams connections maintenance...\n";
echo "Time: " . date('Y-m-d H:i:s') . "\n";

try {
    $service = new PersistentTeamsService();
    $result = $service->maintainConnections();
    
    if ($result) {
        echo "✓ Teams connections maintained successfully\n";
        exit(0);
    } else {
        echo "✗ Failed to maintain some Teams connections\n";
        exit(1);
    }
    
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    error_log("Teams maintenance error: " . $e->getMessage());
    exit(1);
}
?>