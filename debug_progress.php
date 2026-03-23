<?php
session_start();
require_once __DIR__ . '/config/connect.php';

echo "<h2>Debug Progress Loading</h2>";

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo "<p style='color: red;'>❌ User not logged in</p>";
    echo "<p>Session contents:</p>";
    echo "<pre>" . print_r($_SESSION, true) . "</pre>";
    exit;
}

echo "<p style='color: green;'>✅ User logged in: ID = " . $_SESSION['user_id'] . "</p>";

// Test database connection
try {
    $test = $conn->query("SELECT 1");
    echo "<p style='color: green;'>✅ Database connection working</p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Database connection failed: " . $e->getMessage() . "</p>";
    exit;
}

// Test categories
$categories = ['skauting', 'dovednosti', 'zdrave_zivot'];
echo "<h3>Testing categories:</h3>";

foreach ($categories as $category) {
    echo "<h4>Category: $category</h4>";
    
    try {
        $user_id = (int)$_SESSION['user_id'];
        $stmt = $conn->prepare("
            SELECT task_index, status, started_at, completed_at, updated_at
            FROM user_task_progress 
            WHERE user_id = ? AND category_key = ? 
            ORDER BY task_index
        ");
        $stmt->bind_param("is", $user_id, $category);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $count = 0;
        while ($row = $result->fetch_assoc()) {
            $count++;
            echo "<p>Task {$row['task_index']}: Status {$row['status']}, Updated: {$row['updated_at']}</p>";
        }
        
        if ($count === 0) {
            echo "<p style='color: orange;'>⚠️ No progress found for this category</p>";
        } else {
            echo "<p style='color: green;'>✅ Found $count saved tasks</p>";
        }
        
    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ Error querying category $category: " . $e->getMessage() . "</p>";
    }
}

// Test API endpoint
echo "<h3>Testing API endpoint:</h3>";
$testCategory = 'skauting';
$url = "http://localhost:8888/AlbionStezka2/api/load_task_progress.php?category_key=" . $testCategory;

try {
    $context = stream_context_create([
        'http' => [
            'header' => 'Cookie: ' . $_SERVER['HTTP_COOKIE'] ?? ''
        ]
    ]);
    $response = file_get_contents($url, false, $context);
    
    if ($response) {
        echo "<p style='color: green;'>✅ API accessible</p>";
        echo "<p>Response:</p>";
        echo "<pre>" . htmlspecialchars($response) . "</pre>";
    } else {
        echo "<p style='color: red;'>❌ API not accessible</p>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ API test failed: " . $e->getMessage() . "</p>";
}

$conn->close();
?>