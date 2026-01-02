<?php
/**
 * Quality Test Script
 * 
 * This script performs:
 * 1. Dynamic testing of Database Connection.
 * 2. Static analysis of code files for known quality issues (Linting/Logic).
 */

// Ensure we report all errors
error_reporting(E_ALL);
ini_set('display_errors', 1);

$files = [
    'index' => __DIR__ . '/index.php',
    'validation' => __DIR__ . '/validation.php',
    'db_config' => __DIR__ . '/db-config.php'
];

$passCount = 0;
$failCount = 0;

function runTest($name, $callback) {
    global $passCount, $failCount;
    echo "TEST: $name ... ";
    try {
        $result = $callback();
        if ($result === true) {
            echo "\033[32mPASS\033[0m\n";
            $passCount++;
        } else {
            echo "\033[31mFAIL\033[0m\n";
            if (is_string($result)) echo "  -> $result\n";
            $failCount++;
        }
    } catch (Exception $e) {
        echo "\033[31mERROR\033[0m\n";
        echo "  -> " . $e->getMessage() . "\n";
        $failCount++;
    }
}

echo "Starting Quality Code Tests\n";
echo "===========================\n";

// 1. Database Connection Test
runTest("Database Connection", function() use ($files) {
    if (!file_exists($files['db_config'])) return "db-config.php missing";
    
    require_once $files['db_config'];
    
    if (!defined('DB_DSN')) return "DB_DSN not defined";
    
    try {
        // Use the $options defined in db-config.php if available
        global $options;
        new PDO(DB_DSN, DB_USER, DB_PASS, isset($options) ? $options : []);
        return true;
    } catch (PDOException $e) {
        return "Connection failed: " . $e->getMessage();
    }
});

// 2. Static Analysis: validation.php Logic Check
runTest("validation.php Logic (Author Check)", function() use ($files) {
    $content = file_get_contents($files['validation']);
    // Check for the specific copy-paste error: checking 'title' again instead of 'author'
    if (preg_match('/elseif\s*\(!isset\(\$_POST\["title"\]\)\s*\|\|\s*empty\(\$_POST\["author"\]\)\)/', $content)) {
        return "Found logic error: checking 'title' instead of 'author' in elseif block";
    }
    return true;
});

// 3. Static Analysis: index.php HTML Syntax
runTest("index.php HTML Syntax (Nesting & Tags)", function() use ($files) {
    $content = file_get_contents($files['index']);
    
    if (preg_match('/<h2[^>]*>.*?<small[^>]*>((?!<\/small>).)*?<\/h2>/s', $content)) return "Found invalid nesting: <h2> closed before <small>";
    if (preg_match('/<cite[^>]*>.*?<cite>/s', $content)) return "Found invalid closing tag: <cite> used instead of </cite>";
    
    return true;
});

// 4. Static Analysis: Security (XSS Check)
runTest("index.php Security (XSS)", function() use ($files) {
    $content = file_get_contents($files['index']);
    // Check for direct output of $article variables without sanitization
    if (preg_match('/<\?=\s*(?!htmlspecialchars)\$article\[/', $content)) {
        return "Found potential XSS vulnerability: Outputting \$article data without htmlspecialchars()";
    }
    return true;
});

// 5. Static Analysis: Security (SQL Injection)
runTest("Global Security (SQL Injection)", function() use ($files) {
    foreach ($files as $name => $path) {
        if ($name === 'db_config') continue;
        $content = file_get_contents($path);
        // Check for variables interpolated directly into SQL strings (Basic Heuristic)
        if (preg_match('/["\']\s*(SELECT|INSERT|UPDATE|DELETE)\s+.*\$[a-zA-Z_].*["\']/i', $content)) {
            return "Potential SQL Injection in $name.php: Variable interpolation in SQL string";
        }
    }
    return true;
});

// 6. Dynamic Analysis: Performance (DB Latency)
runTest("Performance: Database Latency (< 100ms)", function() use ($files) {
    if (!file_exists($files['db_config'])) return "Skipped: config missing";
    require_once $files['db_config'];
    global $options;
    
    $start = microtime(true);
    try {
        $pdo = new PDO(DB_DSN, DB_USER, DB_PASS, isset($options) ? $options : []);
        $pdo->query("SELECT 1"); // Simple query to test round-trip
    } catch (PDOException $e) {
        return "Connection failed during perf test";
    }
    $duration = (microtime(true) - $start) * 1000; // Convert to ms
    
    if ($duration > 100) return "Too slow: " . round($duration, 2) . "ms (Limit: 100ms)";
    return true;
});

// 7. Static Analysis: Performance (Anti-Patterns)
runTest("Performance: Static Analysis (No SELECT *)", function() use ($files) {
    $content = file_get_contents($files['index']);
    // Using SELECT * is bad for performance (fetching unnecessary columns)
    if (preg_match('/SELECT\s+\*\s+FROM/i', $content)) {
        return "Found 'SELECT *' in index.php. Define specific columns for better performance.";
    }
    return true;
});

echo "===========================\n";
echo "Tests Completed: $passCount Passed, $failCount Failed.\n";
if ($failCount > 0) exit(1);