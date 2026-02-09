<?php
// api/get_user_activity.php
session_start();
require_once __DIR__ . '/../config/connect.php';
require_once __DIR__ . '/../admin/is_admin.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'User not authenticated']);
    exit;
}

$logged_user_id = (int)$_SESSION['user_id'];
$is_admin = is_admin($conn, $logged_user_id);

// Allow users to see their own activity, admins can see any user's activity
$target_user_id = (int)($_GET['user_id'] ?? $logged_user_id);

if ($target_user_id !== $logged_user_id && !$is_admin) {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied']);
    exit;
}

try {
    // Get user info
    $user_stmt = $conn->prepare("SELECT Id, firstName, lastName, nickname, email FROM users WHERE Id = ?");
    $user_stmt->bind_param("i", $target_user_id);
    $user_stmt->execute();
    $user_result = $user_stmt->get_result();
    $user_info = $user_result->fetch_assoc();
    
    if (!$user_info) {
        http_response_code(404);
        echo json_encode(['error' => 'User not found']);
        exit;
    }

    // Get overall activity summary
    $summary_stmt = $conn->prepare("
        SELECT 
            category_key,
            COUNT(*) as total_tasks_tracked,
            COUNT(CASE WHEN status >= 1 THEN 1 END) as tasks_started,
            COUNT(CASE WHEN status = 1 THEN 1 END) as tasks_in_progress,
            COUNT(CASE WHEN status = 2 THEN 1 END) as tasks_completed,
            MIN(started_at) as first_started,
            MAX(completed_at) as last_completed,
            MAX(updated_at) as last_activity
        FROM user_task_progress 
        WHERE user_id = ?
        GROUP BY category_key
        ORDER BY category_key
    ");
    $summary_stmt->bind_param("i", $target_user_id);
    $summary_stmt->execute();
    $summary_result = $summary_stmt->get_result();
    
    $categories = [];
    $overall_stats = [
        'total_categories' => 0,
        'total_tasks_tracked' => 0,
        'tasks_started' => 0,
        'tasks_in_progress' => 0,
        'tasks_completed' => 0
    ];
    
    while ($row = $summary_result->fetch_assoc()) {
        $categories[] = $row;
        $overall_stats['total_categories']++;
        $overall_stats['total_tasks_tracked'] += $row['total_tasks_tracked'];
        $overall_stats['tasks_started'] += $row['tasks_started'];
        $overall_stats['tasks_in_progress'] += $row['tasks_in_progress'];
        $overall_stats['tasks_completed'] += $row['tasks_completed'];
    }

    // Get recent activity (last 50 interactions)
    $recent_stmt = $conn->prepare("
        SELECT category_key, task_index, status, started_at, completed_at, updated_at
        FROM user_task_progress 
        WHERE user_id = ?
        ORDER BY updated_at DESC
        LIMIT 50
    ");
    $recent_stmt->bind_param("i", $target_user_id);
    $recent_stmt->execute();
    $recent_result = $recent_stmt->get_result();
    
    $recent_activity = [];
    while ($row = $recent_result->fetch_assoc()) {
        $recent_activity[] = $row;
    }

    echo json_encode([
        'success' => true,
        'data' => [
            'user_info' => $user_info,
            'overall_stats' => $overall_stats,
            'categories' => $categories,
            'recent_activity' => $recent_activity,
            'is_admin_view' => $is_admin && $target_user_id !== $logged_user_id
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}

$conn->close();
?>