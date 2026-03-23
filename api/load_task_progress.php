<?php
// api/load_task_progress.php
session_start();
require_once __DIR__ . '/../config/connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'User not authenticated']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$category_key = $_GET['category_key'] ?? '';

if (empty($category_key)) {
    http_response_code(400);
    echo json_encode(['error' => 'Category key is required']);
    exit;
}

try {
    // Load all task progress for this user and category
    $stmt = $conn->prepare("
        SELECT task_index, status, started_at, completed_at, updated_at
        FROM user_task_progress 
        WHERE user_id = ? AND category_key = ? 
        ORDER BY task_index
    ");
    $stmt->bind_param("is", $user_id, $category_key);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $progress = [];
    $stats = [
        'total_tasks' => 0,
        'not_started' => 0,
        'in_progress' => 0,
        'completed' => 0
    ];
    
    while ($row = $result->fetch_assoc()) {
        $task_index = (int)$row['task_index'];
        $status = (int)$row['status'];
        
        $progress[$task_index] = [
            'status' => $status,
            'started_at' => $row['started_at'],
            'completed_at' => $row['completed_at'],
            'updated_at' => $row['updated_at']
        ];
        
        $stats['total_tasks']++;
        switch ($status) {
            case 0: $stats['not_started']++; break;
            case 1: $stats['in_progress']++; break;
            case 2: $stats['completed']++; break;
        }
    }
    
    // Also get activity summary
    $activity_stmt = $conn->prepare("
        SELECT 
            COUNT(*) as total_interactions,
            COUNT(CASE WHEN status >= 1 THEN 1 END) as tasks_started,
            COUNT(CASE WHEN status = 2 THEN 1 END) as tasks_completed,
            MIN(started_at) as first_interaction,
            MAX(updated_at) as last_interaction
        FROM user_task_progress 
        WHERE user_id = ? AND category_key = ?
    ");
    $activity_stmt->bind_param("is", $user_id, $category_key);
    $activity_stmt->execute();
    $activity_result = $activity_stmt->get_result();
    $activity = $activity_result->fetch_assoc();

    echo json_encode([
        'success' => true,
        'data' => [
            'user_id' => $user_id,
            'category_key' => $category_key,
            'progress' => $progress,
            'stats' => $stats,
            'activity' => $activity
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}

$conn->close();
?>