<?php
/**
 * Teams Connection Daemon
 * Background service to continuously maintain Teams connections
 * 
 * Usage: php teams_connection_daemon.php [start|stop|status]
 */

require_once __DIR__ . '/persistent_teams_service.php';

// Configuration
define('DAEMON_INTERVAL', 900); // 15 minutes in seconds
define('PID_FILE', __DIR__ . '/teams_daemon.pid');
define('LOG_FILE', __DIR__ . '/teams_daemon.log');

// Prevent web access
if (isset($_SERVER['REQUEST_METHOD'])) {
    http_response_code(403);
    die('Access denied. This script can only be run from command line.');
}

function logMessage($message) {
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] $message\n";
    file_put_contents(LOG_FILE, $logEntry, FILE_APPEND | LOCK_EX);
    echo $logEntry;
}

function isDaemonRunning() {
    if (!file_exists(PID_FILE)) {
        return false;
    }
    
    $pid = (int) file_get_contents(PID_FILE);
    if (!$pid) {
        return false;
    }
    
    // Check if process is actually running (Unix/Linux)
    if (function_exists('posix_kill')) {
        return posix_kill($pid, 0);
    }
    
    // Windows alternative
    if (PHP_OS_FAMILY === 'Windows') {
        $output = shell_exec("tasklist /FI \"PID eq $pid\" 2>NUL");
        return strpos($output, (string)$pid) !== false;
    }
    
    return true; // Assume running if we can't check
}

function startDaemon() {
    if (isDaemonRunning()) {
        logMessage("Teams daemon is already running");
        exit(1);
    }
    
    logMessage("Starting Teams connection daemon...");
    
    // Save PID
    file_put_contents(PID_FILE, getmypid());
    
    // Install signal handlers (Unix/Linux only)
    if (function_exists('pcntl_signal')) {
        pcntl_signal(SIGTERM, 'signalHandler');
        pcntl_signal(SIGINT, 'signalHandler');
        declare(ticks = 1);
    }
    
    $service = new PersistentTeamsService();
    
    while (true) {
        try {
            logMessage("Maintaining Teams connections...");
            $result = $service->maintainConnections();
            
            if ($result) {
                logMessage("✓ Teams connections maintained successfully");
            } else {
                logMessage("⚠ Some Teams connections could not be maintained");
            }
            
            // Sleep for the specified interval
            sleep(DAEMON_INTERVAL);
            
        } catch (Exception $e) {
            logMessage("✗ Error: " . $e->getMessage());
            sleep(60); // Wait 1 minute before retrying on error
        }
        
        // Handle signals (Unix/Linux only)
        if (function_exists('pcntl_signal_dispatch')) {
            pcntl_signal_dispatch();
        }
    }
}

function stopDaemon() {
    if (!file_exists(PID_FILE)) {
        logMessage("Teams daemon is not running (no PID file)");
        exit(0);
    }
    
    $pid = (int) file_get_contents(PID_FILE);
    if (!$pid) {
        logMessage("Invalid PID file");
        unlink(PID_FILE);
        exit(1);
    }
    
    logMessage("Stopping Teams daemon (PID: $pid)...");
    
    // Send termination signal
    if (function_exists('posix_kill')) {
        if (posix_kill($pid, SIGTERM)) {
            sleep(2);
            if (!posix_kill($pid, 0)) {
                logMessage("Teams daemon stopped successfully");
                unlink(PID_FILE);
                exit(0);
            }
            // Force kill if still running
            posix_kill($pid, SIGKILL);
        }
    } elseif (PHP_OS_FAMILY === 'Windows') {
        exec("taskkill /PID $pid /F");
    }
    
    unlink(PID_FILE);
    logMessage("Teams daemon stopped");
}

function getStatus() {
    if (isDaemonRunning()) {
        $pid = file_get_contents(PID_FILE);
        logMessage("Teams daemon is running (PID: $pid)");
        exit(0);
    } else {
        logMessage("Teams daemon is not running");
        exit(1);
    }
}

function signalHandler($signal) {
    logMessage("Received signal $signal, shutting down gracefully...");
    unlink(PID_FILE);
    exit(0);
}

// Parse command line arguments
$command = $argv[1] ?? 'start';

switch ($command) {
    case 'start':
        startDaemon();
        break;
    case 'stop':
        stopDaemon();
        break;
    case 'restart':
        stopDaemon();
        sleep(1);
        startDaemon();
        break;
    case 'status':
        getStatus();
        break;
    default:
        echo "Usage: php teams_connection_daemon.php [start|stop|restart|status]\n";
        exit(1);
}
?>