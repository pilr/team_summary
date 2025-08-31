# Database Import Instructions

## Database Information
- **Database Name**: `u175828155_team_summary`
- **Username**: `u175828155_team_summary`  
- **Password**: `x[=5Pja3O`
- **Host**: `localhost`

## Import Steps

### Step 1: Access phpMyAdmin
1. Login to your hosting control panel
2. Open phpMyAdmin
3. Select database `u175828155_team_summary` from the left sidebar

### Step 2: Import Schema (Tables Structure)
1. Click on **Import** tab
2. Click **Choose File** button
3. Select `database_schema.sql`
4. Leave all settings as default
5. Click **Go** button
6. Wait for success message: "Import has been successfully finished"

### Step 3: Import Sample Data (Test Data)
1. Still in the **Import** tab
2. Click **Choose File** button again
3. Select `sample_data.sql`
4. Click **Go** button
5. Wait for success message

## Verification

After import, you should see these tables in your database:
- ✅ `users` (8 sample users)
- ✅ `teams` (5 teams)
- ✅ `channels` (13 channels)
- ✅ `messages` (15+ messages)
- ✅ `summaries` (3 summaries)
- ✅ `delivery_logs` (4 delivery records)
- ✅ Plus 6 additional supporting tables

## Test Login Credentials

After import, you can login with:
- **Email**: `demo@company.com`
- **Password**: `demo123`

## File Execution Order

**IMPORTANT**: Import files in this exact order:
1. `database_schema.sql` (creates tables)
2. `sample_data.sql` (adds test data)

## Troubleshooting

### If you get "Table already exists" errors:
- Drop all existing tables first, or
- Add `DROP TABLE IF EXISTS table_name;` before each CREATE TABLE

### If import fails due to file size:
- Increase `upload_max_filesize` in PHP
- Or import sections manually

### If you get character encoding issues:
- Ensure database collation is `utf8mb4_unicode_ci`
- Check that phpMyAdmin is set to UTF-8

## Database Connection Test

You can test the database connection by running this PHP code:
```php
<?php
try {
    $pdo = new PDO(
        "mysql:host=localhost;dbname=u175828155_team_summary;charset=utf8mb4",
        "u175828155_team_summary",
        "x[=5Pja3O",
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    echo "Database connection successful!";
    
    // Count users
    $stmt = $pdo->query("SELECT COUNT(*) FROM users");
    $count = $stmt->fetchColumn();
    echo "<br>Found {$count} users in database.";
    
} catch (PDOException $e) {
    echo "Connection failed: " . $e->getMessage();
}
?>
```

## Next Steps

Once imported successfully:
1. Your PHP application will automatically use real database data
2. Login with demo credentials to test
3. All dashboard data will be pulled from MySQL
4. Summaries page will show real statistics
5. Activity logs will track user actions

The application is now fully database-driven!