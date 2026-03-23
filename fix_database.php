<?php
require_once __DIR__ . '/config/connect.php';

echo "<h2>Database Table Fix</h2>";

try {
    // First, let's see the current table structure
    echo "<h3>Current table structure:</h3>";
    $result = $conn->query("DESCRIBE user_task_progress");
    if ($result) {
        echo "<table border='1'><tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            foreach ($row as $value) {
                echo "<td>" . ($value ?? 'NULL') . "</td>";
            }
            echo "</tr>";
        }
        echo "</table>";
    }
    
    echo "<h3>Making task_id nullable...</h3>";
    
    // Make task_id nullable
    $alterQuery = "ALTER TABLE user_task_progress MODIFY COLUMN task_id INT NULL";
    if ($conn->query($alterQuery)) {
        echo "<p style='color: green;'>✅ Successfully made task_id nullable</p>";
    } else {
        echo "<p style='color: red;'>❌ Error modifying task_id: " . $conn->error . "</p>";
    }
    
    echo "<h3>New table structure:</h3>";
    $result = $conn->query("DESCRIBE user_task_progress");
    if ($result) {
        echo "<table border='1'><tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            foreach ($row as $value) {
                echo "<td>" . ($value ?? 'NULL') . "</td>";
            }
            echo "</tr>";
        }
        echo "</table>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
}

$conn->close();
?>