<?php
// api/save_task_progress.php
session_start();
require_once __DIR__ . '/../config/connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'User not authenticated']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON input']);
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$category_key = $input['category_key'] ?? '';
$task_index = (int)($input['task_index'] ?? -1);
$status = (int)($input['status'] ?? 0);  // 0: not started, 1: in progress, 2: completed

if (empty($category_key) || $task_index < 0 || $status < 0 || $status > 2) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid parameters']);
    exit;
}

try {
    // Check if record exists
    $check_stmt = $conn->prepare("SELECT id, status FROM user_task_progress WHERE user_id = ? AND category_key = ? AND task_index = ?");
    $check_stmt->bind_param("isi", $user_id, $category_key, $task_index);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    $existing = $result->fetch_assoc();

    $now = date('Y-m-d H:i:s');
    
    if ($existing) {
        // Update existing record
        $prev_status = (int)$existing['status'];
        
        // Set timestamps based on status change
        $started_at = null;
        $completed_at = null;
        
        if ($prev_status == 0 && $status == 1) {
            // Starting task
            $started_at = $now;
        } elseif ($status == 2) {
            // Completing task
            $completed_at = $now;
            if ($prev_status == 0) {
                $started_at = $now; // Started and completed simultaneously
            }
        }
        
        $sql = "UPDATE user_task_progress SET status = ?, updated_at = ?";
        $params = [$status, $now];
        $types = "is";
        
        if ($started_at) {
            $sql .= ", started_at = ?";
            $params[] = $started_at;
            $types .= "s";
        }
        if ($completed_at) {
            $sql .= ", completed_at = ?";
            $params[] = $completed_at;
            $types .= "s";
        }
        
        $sql .= " WHERE id = ?";
        $params[] = $existing['id'];
        $types .= "i";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        
    } else {
        // Insert new record
        $started_at = ($status >= 1) ? $now : null;
        $completed_at = ($status == 2) ? $now : null;
        
        $stmt = $conn->prepare("INSERT INTO user_task_progress (user_id, task_id, category_key, task_index, status, started_at, completed_at, updated_at) VALUES (?, NULL, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("isiisss", $user_id, $category_key, $task_index, $status, $started_at, $completed_at, $now);
    }

    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'Task progress saved',
            'data' => [
                'user_id' => $user_id,
                'category_key' => $category_key,
                'task_index' => $task_index,
                'status' => $status,
                'timestamp' => $now
            ]
        ]);
    } else {
        throw new Exception('Failed to save task progress');
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}

$conn->close();
?>