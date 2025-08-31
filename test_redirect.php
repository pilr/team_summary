<?php
session_start();

// Simple test to check if redirects work
if (isset($_POST['test_redirect'])) {
    $_SESSION['test'] = 'Redirect worked!';
    header('Location: test_redirect.php?success=1');
    exit();
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Redirect Test</title>
</head>
<body>
    <h1>Redirect Test</h1>
    
    <?php if (isset($_GET['success'])): ?>
        <div style="color: green; font-weight: bold;">
            ✅ Redirect Test Successful!<br>
            Session data: <?php echo $_SESSION['test'] ?? 'None'; ?>
        </div>
        <a href="test_redirect.php">Test Again</a>
    <?php else: ?>
        <form method="POST">
            <button type="submit" name="test_redirect">Test Redirect</button>
        </form>
        <p>Click the button to test if PHP redirects are working on this server.</p>
    <?php endif; ?>
    
    <hr>
    <h2>Server Information</h2>
    <p><strong>PHP Version:</strong> <?php echo phpversion(); ?></p>
    <p><strong>Output Buffering:</strong> <?php echo ob_get_level() > 0 ? 'Enabled' : 'Disabled'; ?></p>
    <p><strong>Headers Sent:</strong> <?php echo headers_sent() ? 'Yes' : 'No'; ?></p>
    
    <hr>
    <a href="login.php">Back to Login</a>
</body>
</html>