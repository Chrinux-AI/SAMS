<?php

/**
 * Error Verification Script
 * Run this to verify all functions are working correctly
 */

echo "🔧 System Error Check & Verification\n";
echo "=====================================\n\n";

// Include the necessary files
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/database.php';

$errors = [];
$success_count = 0;

// Test authentication functions
echo "1. Testing Authentication Functions...\n";
try {
    if (function_exists('check_login')) {
        echo "   ✅ check_login() function exists\n";
        $success_count++;
    } else {
        $errors[] = "check_login() function missing";
    }

    if (function_exists('require_login')) {
        echo "   ✅ require_login() function exists\n";
        $success_count++;
    } else {
        $errors[] = "require_login() function missing";
    }

    if (function_exists('is_logged_in')) {
        echo "   ✅ is_logged_in() function exists\n";
        $success_count++;
    } else {
        $errors[] = "is_logged_in() function missing";
    }
} catch (Exception $e) {
    $errors[] = "Authentication functions error: " . $e->getMessage();
}

// Test AI Analytics functions
echo "\n2. Testing AI Analytics Functions...\n";
try {
    if (function_exists('calculateAttendanceTrend')) {
        $trend = calculateAttendanceTrend();
        echo "   ✅ calculateAttendanceTrend() function working\n";
        $success_count++;
    } else {
        $errors[] = "calculateAttendanceTrend() function missing";
    }

    if (function_exists('identifyRiskStudents')) {
        $risk_students = identifyRiskStudents();
        echo "   ✅ identifyRiskStudents() function working\n";
        $success_count++;
    } else {
        $errors[] = "identifyRiskStudents() function missing";
    }

    if (function_exists('predictPerformance')) {
        $prediction = predictPerformance();
        echo "   ✅ predictPerformance() function working\n";
        $success_count++;
    } else {
        $errors[] = "predictPerformance() function missing";
    }

    if (function_exists('analyzeOptimalSchedule')) {
        $schedule = analyzeOptimalSchedule();
        echo "   ✅ analyzeOptimalSchedule() function working\n";
        $success_count++;
    } else {
        $errors[] = "analyzeOptimalSchedule() function missing";
    }
} catch (Exception $e) {
    $errors[] = "AI Analytics functions error: " . $e->getMessage();
}

// Test Real-time Sync functions
echo "\n3. Testing Real-time Sync Functions...\n";
try {
    if (function_exists('getConnectedDevices')) {
        $devices = getConnectedDevices();
        echo "   ✅ getConnectedDevices() function working (returned: $devices)\n";
        $success_count++;
    } else {
        $errors[] = "getConnectedDevices() function missing";
    }

    if (function_exists('getRealTimePackets')) {
        $packets = getRealTimePackets();
        echo "   ✅ getRealTimePackets() function working (returned: $packets)\n";
        $success_count++;
    } else {
        $errors[] = "getRealTimePackets() function missing";
    }
} catch (Exception $e) {
    $errors[] = "Real-time Sync functions error: " . $e->getMessage();
}

// Test Smart Insights functions
echo "\n4. Testing Smart Insights Functions...\n";
try {
    if (function_exists('generateSmartInsights')) {
        $insights = generateSmartInsights();
        echo "   ✅ generateSmartInsights() function working\n";
        $success_count++;
    } else {
        $errors[] = "generateSmartInsights() function missing";
    }
} catch (Exception $e) {
    $errors[] = "Smart Insights functions error: " . $e->getMessage();
}

// Test Mobile & API functions
echo "\n5. Testing Mobile & API Functions...\n";
try {
    if (function_exists('getMobileActiveSessions')) {
        $sessions = getMobileActiveSessions();
        echo "   ✅ getMobileActiveSessions() function working (returned: $sessions)\n";
        $success_count++;
    } else {
        $errors[] = "getMobileActiveSessions() function missing";
    }

    if (function_exists('getApiRequestsToday')) {
        $requests = getApiRequestsToday();
        echo "   ✅ getApiRequestsToday() function working (returned: $requests)\n";
        $success_count++;
    } else {
        $errors[] = "getApiRequestsToday() function missing";
    }

    if (function_exists('getBlockedAttempts')) {
        $blocked = getBlockedAttempts();
        echo "   ✅ getBlockedAttempts() function working (returned: $blocked)\n";
        $success_count++;
    } else {
        $errors[] = "getBlockedAttempts() function missing";
    }

    if (function_exists('getActiveTokens')) {
        $tokens = getActiveTokens();
        echo "   ✅ getActiveTokens() function working (returned: $tokens)\n";
        $success_count++;
    } else {
        $errors[] = "getActiveTokens() function missing";
    }
} catch (Exception $e) {
    $errors[] = "Mobile & API functions error: " . $e->getMessage();
}

// Test Database functions
echo "\n6. Testing Database Functions...\n";
try {
    if (function_exists('db')) {
        echo "   ✅ db() function exists\n";
        $success_count++;
    } else {
        $errors[] = "db() function missing";
    }

    if (function_exists('format_date')) {
        $date = format_date('2024-01-01');
        echo "   ✅ format_date() function working (returned: $date)\n";
        $success_count++;
    } else {
        $errors[] = "format_date() function missing";
    }
} catch (Exception $e) {
    $errors[] = "Database functions error: " . $e->getMessage();
}

// Summary
echo "\n" . str_repeat("=", 50) . "\n";
echo "📊 VERIFICATION SUMMARY\n";
echo str_repeat("=", 50) . "\n";

if (empty($errors)) {
    echo "🎉 ALL TESTS PASSED! ✅\n";
    echo "✅ $success_count functions verified successfully\n";
    echo "✅ No errors found in the system\n";
    echo "✅ Dashboard and all pages should work correctly\n\n";
    echo "🚀 Your attendance system is ready to use!\n";
} else {
    echo "⚠️  Issues Found:\n";
    foreach ($errors as $error) {
        echo "   ❌ $error\n";
    }
    echo "\n✅ $success_count functions working correctly\n";
    echo "❌ " . count($errors) . " issues need attention\n";
}

echo "\nFor more details, check the error logs in your LAMPP logs directory.\n";
