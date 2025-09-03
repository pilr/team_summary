<?php
/**
 * Test script to verify date filter functionality
 * Tests: today, this week, this month filters
 */

// Simulate the getDateRange function from summaries.php
function getDateRange($date_range, $custom_start = "", $custom_end = "") {
    $now = new DateTime();
    $startDate = null;
    $endDate = null;
    
    switch ($date_range) {
        case "today":
            $startDate = new DateTime("today");
            $endDate = new DateTime("today 23:59:59");
            break;
        case "week":
            $startDate = new DateTime("monday this week");
            $endDate = new DateTime("sunday this week 23:59:59");
            break;
        case "month":
            $startDate = new DateTime("first day of this month");
            $endDate = new DateTime("last day of this month 23:59:59");
            break;
        default:
            $startDate = new DateTime("today");
            $endDate = new DateTime("today 23:59:59");
    }
    
    return ["start" => $startDate, "end" => $endDate];
}

// Test message filtering function
function isMessageInDateRange($message, $startDate, $endDate) {
    if (!isset($message["createdDateTime"])) {
        return false;
    }
    
    try {
        $messageDate = new DateTime($message["createdDateTime"]);
        return $messageDate >= $startDate && $messageDate < $endDate;
    } catch (Exception $e) {
        return false;
    }
}

echo "=== TESTING DATE FILTER FUNCTIONALITY ===\n\n";

// Test TODAY filter
echo "1. TESTING TODAY FILTER:\n";
$todayRange = getDateRange("today");
echo "Date range: " . $todayRange["start"]->format("Y-m-d H:i:s") . " to " . $todayRange["end"]->format("Y-m-d H:i:s") . "\n";

// Test THIS WEEK filter  
echo "\n2. TESTING THIS WEEK FILTER:\n";
$weekRange = getDateRange("week");
echo "Date range: " . $weekRange["start"]->format("Y-m-d H:i:s") . " to " . $weekRange["end"]->format("Y-m-d H:i:s") . "\n";

// Test THIS MONTH filter
echo "\n3. TESTING THIS MONTH FILTER:\n";
$monthRange = getDateRange("month");
echo "Date range: " . $monthRange["start"]->format("Y-m-d H:i:s") . " to " . $monthRange["end"]->format("Y-m-d H:i:s") . "\n";

echo "\n✅ FILTER FUNCTIONALITY TEST COMPLETED\n";
?>
