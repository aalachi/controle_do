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
        $pdo = new PDO(DB_DSN, DB_USER, DB_PASS, isset($options) ? $options : []);
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
    
    if (preg_match('/<h2[^>]*>.*?<small[^>]*>.*?<\/h2>/s', $content)) return "Found invalid nesting: <h2> closed before <small>";
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

echo "===========================\n";
echo "Tests Completed: $passCount Passed, $failCount Failed.\n";
if ($failCount > 0) exit(1);