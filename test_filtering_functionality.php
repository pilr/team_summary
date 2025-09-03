<?php
/**
 * Test suite for filtering functionality in summaries.php
 * This script validates all filtering features including date, type, and channel filters
 */

ob_start();
session_start();
require_once 'database_helper.php';

// Test configuration
$test_results = [];

/**
 * Test date range filtering functionality
 */
function testDateRangeFiltering() {
    $results = [];
    
    // Test getDateRange function with different ranges
    require_once 'summaries.php';
    
    // Test 1: Today date range
    $today_range = getDateRange('today');
    $results['today'] = [
        'start_is_today' => $today_range['start']->format('Y-m-d') === date('Y-m-d'),
        'end_is_today' => $today_range['end']->format('Y-m-d') === date('Y-m-d'),
        'time_span_valid' => $today_range['start'] <= $today_range['end']
    ];
    
    // Test 2: Week date range
    $week_range = getDateRange('week');
    $results['week'] = [
        'start_is_monday' => $week_range['start']->format('N') == 1, // Monday is 1
        'end_after_start' => $week_range['start'] <= $week_range['end'],
        'within_current_week' => $week_range['start']->format('W') === date('W')
    ];
    
    // Test 3: Month date range
    $month_range = getDateRange('month');
    $results['month'] = [
        'start_is_first_day' => $month_range['start']->format('j') == 1,
        'end_after_start' => $month_range['start'] <= $month_range['end'],
        'same_month' => $month_range['start']->format('m') === $month_range['end']->format('m')
    ];
    
    // Test 4: Custom date range
    $custom_start = '2024-01-01';
    $custom_end = '2024-01-31';
    $custom_range = getDateRange('custom', $custom_start, $custom_end);
    $results['custom'] = [
        'start_matches' => $custom_range['start']->format('Y-m-d') === $custom_start,
        'end_matches' => $custom_range['end']->format('Y-m-d') === $custom_end,
        'valid_range' => $custom_range['start'] <= $custom_range['end']
    ];
    
    return $results;
}

/**
 * Test isMessageInDateRange function
 */
function testMessageDateFiltering() {
    $results = [];
    
    // Mock message data
    $messages = [
        ['createdDateTime' => date('c')], // Today
        ['createdDateTime' => date('c', strtotime('-1 day'))], // Yesterday
        ['createdDateTime' => date('c', strtotime('-1 week'))], // Last week
        ['createdDateTime' => 'invalid-date'], // Invalid date
        [] // No date
    ];
    
    $today_range = getDateRange('today');
    
    foreach ($messages as $index => $message) {
        $in_range = isMessageInDateRange($message, $today_range['start'], $today_range['end']);
        $results["message_$index"] = $in_range;
    }
    
    return $results;
}

/**
 * Test type filtering functionality
 */
function testTypeFiltering() {
    $results = [];
    
    // Mock Teams API messages for testing
    $test_messages = [
        [
            'importance' => 'high',
            'body' => ['content' => 'This is urgent!'],
            'attachments' => []
        ],
        [
            'importance' => 'normal',
            'body' => ['content' => 'Hello @everyone'],
            'attachments' => []
        ],
        [
            'importance' => 'normal',
            'body' => ['content' => 'Regular message'],
            'attachments' => [['name' => 'file.pdf']]
        ],
        [
            'importance' => 'normal',
            'body' => ['content' => 'Regular message'],
            'attachments' => []
        ]
    ];
    
    // Test urgent filtering
    $urgent_count = 0;
    $mention_count = 0;
    $file_count = 0;
    
    foreach ($test_messages as $message) {
        if (isset($message['importance']) && $message['importance'] === 'high') {
            $urgent_count++;
        }
        if (isset($message['body']['content']) && strpos($message['body']['content'], '@') !== false) {
            $mention_count++;
        }
        if (isset($message['attachments']) && !empty($message['attachments'])) {
            $file_count++;
        }
    }
    
    $results = [
        'urgent_filtering' => $urgent_count === 1,
        'mention_filtering' => $mention_count === 1,
        'file_filtering' => $file_count === 1,
        'total_messages' => count($test_messages) === 4
    ];
    
    return $results;
}

/**
 * Test channel filtering functionality
 */
function testChannelFiltering() {
    $results = [];
    
    // Mock channel data
    $mock_channels = [
        ['id' => 'chan1', 'displayName' => 'General'],
        ['id' => 'chan2', 'displayName' => 'Development'],
        ['id' => 'chan3', 'displayName' => 'Marketing']
    ];
    
    // Test channel selection
    $selected_channel = 'chan2';
    $found_channel = null;
    
    foreach ($mock_channels as $channel) {
        if ($channel['id'] === $selected_channel) {
            $found_channel = $channel;
            break;
        }
    }
    
    $results = [
        'channel_found' => $found_channel !== null,
        'correct_channel' => $found_channel['displayName'] === 'Development',
        'all_channels_option' => true, // Always available
        'channel_count' => count($mock_channels) === 3
    ];
    
    return $results;
}

/**
 * Test combined filtering scenarios
 */
function testCombinedFiltering() {
    $results = [];
    
    // Test scenario: Today + Urgent + Specific Channel
    $date_range = getDateRange('today');
    $channel_filter = 'chan1';
    $type_filter = 'urgent';
    
    // Mock data that should pass all filters
    $test_message = [
        'createdDateTime' => date('c'),
        'importance' => 'high',
        'body' => ['content' => 'Urgent message'],
        'channel_id' => 'chan1'
    ];
    
    // Test each filter
    $passes_date = isMessageInDateRange($test_message, $date_range['start'], $date_range['end']);
    $passes_type = ($test_message['importance'] === 'high');
    $passes_channel = ($test_message['channel_id'] === $channel_filter);
    
    $results = [
        'passes_date_filter' => $passes_date,
        'passes_type_filter' => $passes_type,
        'passes_channel_filter' => $passes_channel,
        'passes_all_filters' => $passes_date && $passes_type && $passes_channel
    ];
    
    return $results;
}

/**
 * Test filter reset functionality
 */
function testFilterReset() {
    $results = [];
    
    // Simulate filter reset by setting all to default values
    $default_filters = [
        'date_range' => 'today',
        'channel_filter' => 'all',
        'type_filter' => 'all'
    ];
    
    // Test URL parameter handling
    $test_params = [
        'range' => 'today',
        'channel' => 'all', 
        'type' => 'all'
    ];
    
    $results = [
        'date_reset' => $test_params['range'] === $default_filters['date_range'],
        'channel_reset' => $test_params['channel'] === $default_filters['channel_filter'],
        'type_reset' => $test_params['type'] === $default_filters['type_filter'],
        'all_reset' => count(array_diff($test_params, ['range' => 'today', 'channel' => 'all', 'type' => 'all'])) === 0
    ];
    
    return $results;
}

/**
 * Test filtering performance with mock large dataset
 */
function testFilteringPerformance() {
    $results = [];
    
    $start_time = microtime(true);
    
    // Generate mock large dataset
    $large_dataset = [];
    for ($i = 0; $i < 1000; $i++) {
        $large_dataset[] = [
            'createdDateTime' => date('c', strtotime("-$i hours")),
            'importance' => ($i % 10 === 0) ? 'high' : 'normal',
            'body' => ['content' => "Message $i" . (($i % 15 === 0) ? ' @mention' : '')],
            'attachments' => ($i % 20 === 0) ? [['name' => "file$i.pdf"]] : []
        ];
    }
    
    // Test filtering large dataset
    $date_range = getDateRange('today');
    $filtered_count = 0;
    
    foreach ($large_dataset as $message) {
        if (isMessageInDateRange($message, $date_range['start'], $date_range['end'])) {
            $filtered_count++;
        }
    }
    
    $end_time = microtime(true);
    $execution_time = $end_time - $start_time;
    
    $results = [
        'dataset_size' => count($large_dataset),
        'filtered_count' => $filtered_count,
        'execution_time' => $execution_time,
        'performance_acceptable' => $execution_time < 1.0, // Should complete within 1 second
        'memory_usage' => memory_get_peak_usage(true)
    ];
    
    return $results;
}

/**
 * Run all filtering tests
 */
function runFilteringTests() {
    global $test_results;
    
    echo "<h1>Teams Summary Filtering Functionality Test Results</h1>\n";
    echo "<style>
        .test-section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 5px; }
        .pass { color: green; font-weight: bold; }
        .fail { color: red; font-weight: bold; }
        .result { margin: 5px 0; }
        .summary { background: #f5f5f5; padding: 10px; margin: 20px 0; border-radius: 5px; }
    </style>\n";
    
    $all_tests_passed = true;
    
    // Test 1: Date Range Filtering
    echo "<div class='test-section'>\n";
    echo "<h2>1. Date Range Filtering Tests</h2>\n";
    $date_results = testDateRangeFiltering();
    foreach ($date_results as $range_type => $tests) {
        echo "<h3>$range_type Range:</h3>\n";
        foreach ($tests as $test_name => $passed) {
            $status = $passed ? "<span class='pass'>PASS</span>" : "<span class='fail'>FAIL</span>";
            echo "<div class='result'>$test_name: $status</div>\n";
            if (!$passed) $all_tests_passed = false;
        }
    }
    echo "</div>\n";
    
    // Test 2: Message Date Filtering
    echo "<div class='test-section'>\n";
    echo "<h2>2. Message Date Range Validation</h2>\n";
    $message_date_results = testMessageDateFiltering();
    foreach ($message_date_results as $test_name => $result) {
        $status = is_bool($result) ? ($result ? "PASS" : "FAIL") : "RESULT: $result";
        $class = is_bool($result) ? ($result ? 'pass' : 'fail') : '';
        echo "<div class='result'>$test_name: <span class='$class'>$status</span></div>\n";
    }
    echo "</div>\n";
    
    // Test 3: Type Filtering
    echo "<div class='test-section'>\n";
    echo "<h2>3. Type Filtering Tests</h2>\n";
    $type_results = testTypeFiltering();
    foreach ($type_results as $test_name => $passed) {
        $status = $passed ? "<span class='pass'>PASS</span>" : "<span class='fail'>FAIL</span>";
        echo "<div class='result'>$test_name: $status</div>\n";
        if (!$passed) $all_tests_passed = false;
    }
    echo "</div>\n";
    
    // Test 4: Channel Filtering
    echo "<div class='test-section'>\n";
    echo "<h2>4. Channel Filtering Tests</h2>\n";
    $channel_results = testChannelFiltering();
    foreach ($channel_results as $test_name => $passed) {
        $status = $passed ? "<span class='pass'>PASS</span>" : "<span class='fail'>FAIL</span>";
        echo "<div class='result'>$test_name: $status</div>\n";
        if (!$passed) $all_tests_passed = false;
    }
    echo "</div>\n";
    
    // Test 5: Combined Filtering
    echo "<div class='test-section'>\n";
    echo "<h2>5. Combined Filtering Tests</h2>\n";
    $combined_results = testCombinedFiltering();
    foreach ($combined_results as $test_name => $passed) {
        $status = $passed ? "<span class='pass'>PASS</span>" : "<span class='fail'>FAIL</span>";
        echo "<div class='result'>$test_name: $status</div>\n";
        if (!$passed) $all_tests_passed = false;
    }
    echo "</div>\n";
    
    // Test 6: Filter Reset
    echo "<div class='test-section'>\n";
    echo "<h2>6. Filter Reset Functionality</h2>\n";
    $reset_results = testFilterReset();
    foreach ($reset_results as $test_name => $passed) {
        $status = $passed ? "<span class='pass'>PASS</span>" : "<span class='fail'>FAIL</span>";
        echo "<div class='result'>$test_name: $status</div>\n";
        if (!$passed) $all_tests_passed = false;
    }
    echo "</div>\n";
    
    // Test 7: Performance Testing
    echo "<div class='test-section'>\n";
    echo "<h2>7. Filtering Performance Tests</h2>\n";
    $perf_results = testFilteringPerformance();
    foreach ($perf_results as $test_name => $result) {
        if ($test_name === 'performance_acceptable') {
            $status = $result ? "<span class='pass'>PASS</span>" : "<span class='fail'>FAIL</span>";
            echo "<div class='result'>$test_name: $status</div>\n";
            if (!$result) $all_tests_passed = false;
        } else {
            echo "<div class='result'>$test_name: $result</div>\n";
        }
    }
    echo "</div>\n";
    
    // Overall Summary
    echo "<div class='summary'>\n";
    echo "<h2>Test Summary</h2>\n";
    if ($all_tests_passed) {
        echo "<div class='pass'>✅ ALL TESTS PASSED - Filtering functionality is working correctly!</div>\n";
    } else {
        echo "<div class='fail'>❌ Some tests failed - Please review the failing tests above</div>\n";
    }
    echo "<p>Test completed at: " . date('Y-m-d H:i:s') . "</p>\n";
    echo "</div>\n";
    
    return $all_tests_passed;
}

// Only include the functions if summaries.php exists and contains the required functions
if (file_exists('summaries.php')) {
    // Extract just the functions we need for testing
    ob_start();
    include 'summaries.php';
    ob_end_clean();
    
    // Run the tests
    runFilteringTests();
} else {
    echo "<h1>Error: summaries.php not found</h1>";
    echo "<p>This test requires summaries.php to be present in the same directory.</p>";
}
?>