<?php
require_once 'database_helper.php';

try {
    global $db;
    $pdo = $db->getPDO(); // We'll need to add this method
    
    // Check if oauth_tokens table exists
    $result = $pdo->query("SHOW TABLES LIKE 'oauth_tokens'");
    $tableExists = $result->rowCount() > 0;
    
    if (!$tableExists) {
        echo "OAuth tokens table does not exist. Creating it...\n";
        
        // Read and execute the SQL schema
        $sql = file_get_contents('schema/oauth_tokens.sql');
        
        // Split by delimiter for multiple statements
        $statements = explode('//DELIMITER', str_replace('DELIMITER //', '//DELIMITER', $sql));
        
        foreach ($statements as $statement) {
            $statement = trim($statement);
            if (!empty($statement)) {
                $pdo->exec($statement);
            }
        }
        
        echo "OAuth tokens table created successfully!\n";
    } else {
        echo "OAuth tokens table already exists.\n";
    }
    
    // Test the table by checking its structure
    $result = $pdo->query("DESCRIBE oauth_tokens");
    echo "Table structure:\n";
    while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
        echo "  {$row['Field']} - {$row['Type']}\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>