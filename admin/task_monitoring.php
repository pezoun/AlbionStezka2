<?php
// admin/task_monitoring.php
session_start();
require_once __DIR__ . '/../config/connect.php';
require_once __DIR__ . '/is_admin.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit;
}

$loggedUserId = (int)$_SESSION['user_id'];
if (!is_admin($conn, $loggedUserId)) {
    header('Location: ../pages/homepage.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Task Monitoring | Albion Stezka</title>
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
</head>
<body>
    <div class="container">
        <nav class="sidebar">
            <a class="brand" href="../pages/homepage.php">
                <i class="fa-solid fa-layer-group"></i>
                <span>Albion Stezka</span>
            </a>
            <div class="menu">
                <a class="item" href="../pages/homepage.php"><i class="fa-solid fa-house"></i><span>Uvítání</span></a>
                <a class="item" href="../pages/tasks.php"><i class="fa-solid fa-list-check"></i><span>Úkoly</span></a>
                <a class="item" href="../pages/patrons.php"><i class="fa-solid fa-user-shield"></i><span>Patroni</span></a>
                <a class="item" href="manage_patrons.php"><i class="fa-solid fa-screwdriver-wrench"></i><span>Správa Patronů</span></a>
                <a class="item" href="approve_users.php"><i class="fa-solid fa-user-check"></i><span>Schvalování</span></a>
                <a class="item" href="admin_panel.php"><i class="fa-solid fa-shield-halved"></i><span>Admin Panel</span></a>
                <a class="item active" href="task_monitoring.php"><i class="fa-solid fa-chart-line"></i><span>Task Monitoring</span></a>
            </div>
            <div class="user-section">
                <a class="item" href="../user/profile.php"><i class="fa-solid fa-user"></i><span>Účet</span></a>
                <a class="item" href="../user/settings.php"><i class="fa-solid fa-gear"></i><span>Nastavení</span></a>
                <a class="item danger" href="../auth/logout.php"><i class="fa-solid fa-right-from-bracket"></i><span>Odhlásit</span></a>
            </div>
        </nav>

        <main class="main-content">
            <div class="content-header">
                <h1><i class="fa-solid fa-chart-line"></i> Task Monitoring</h1>
                <p>Monitor user task interactions and progress across all categories</p>
            </div>

            <!-- User Selection -->
            <div class="card">
                <div class="card-header">
                    <h2><i class="fa-solid fa-users"></i> Select User</h2>
                </div>
                <div class="card-content">
                    <select id="userSelect" class="form-control" style="width: 300px; margin-bottom: 20px;">
                        <option value="">Select a user...</option>
                    </select>
                    <button onclick="loadUserActivity()" class="btn primary">
                        <i class="fa-solid fa-search"></i> Load Activity
                    </button>
                </div>
            </div>

            <!-- User Activity Display -->
            <div id="userActivity" style="display: none;">
                <!-- Overall Stats -->
                <div class="card">
                    <div class="card-header">
                        <h2><i class="fa-solid fa-chart-pie"></i> Overall Statistics</h2>
                    </div>
                    <div class="card-content">
                        <div class="stats-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                            <div class="stat-card">
                                <div class="stat-value" id="totalCategories">-</div>
                                <div class="stat-label">Categories Active</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-value" id="totalTasksTracked">-</div>
                                <div class="stat-label">Tasks Tracked</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-value" id="tasksInProgress">-</div>
                                <div class="stat-label">In Progress</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-value" id="tasksCompleted">-</div>
                                <div class="stat-label">Completed</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Category Breakdown -->
                <div class="card">
                    <div class="card-header">
                        <h2><i class="fa-solid fa-layer-group"></i> Category Breakdown</h2>
                    </div>
                    <div class="card-content">
                        <div id="categoryBreakdown">
                            <!-- Will be populated by JavaScript -->
                        </div>
                    </div>
                </div>

                <!-- Recent Activity -->
                <div class="card">
                    <div class="card-header">
                        <h2><i class="fa-solid fa-clock"></i> Recent Activity</h2>
                    </div>
                    <div class="card-content">
                        <div id="recentActivity">
                            <!-- Will be populated by JavaScript -->
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Load users on page init
        document.addEventListener('DOMContentLoaded', function() {
            loadUsers();
        });

        async function loadUsers() {
            try {
                const response = await fetch('../api/get_user_detail.php');
                const data = await response.text();
                // This API might need adjustment to list all users
                
                // For now, let's populate with a simple query
                const userSelect = document.getElementById('userSelect');
                // You might want to create a separate API to list all users
                // For now, users can manually enter user ID or we can load from existing data
                
                // Temporary: allow manual input
                userSelect.innerHTML = '<option value="">Enter user ID manually or select...</option>';
                
                // Add some sample options (you'd replace this with actual user list)
                for (let i = 1; i <= 10; i++) {
                    const option = document.createElement('option');
                    option.value = i;
                    option.textContent = `User ID ${i}`;
                    userSelect.appendChild(option);
                }
                
            } catch (error) {
                console.error('Error loading users:', error);
            }
        }

        async function loadUserActivity() {
            const userId = document.getElementById('userSelect').value;
            if (!userId) {
                alert('Please select a user');
                return;
            }

            try {
                const response = await fetch(`../api/get_user_activity.php?user_id=${userId}`);
                const result = await response.json();

                if (result.success) {
                    displayUserActivity(result.data);
                } else {
                    alert('Error loading user activity: ' + result.error);
                }
            } catch (error) {
                console.error('Error loading user activity:', error);
                alert('Network error loading user activity');
            }
        }

        function displayUserActivity(data) {
            // Show the activity section
            document.getElementById('userActivity').style.display = 'block';

            // Update overall stats
            document.getElementById('totalCategories').textContent = data.overall_stats.total_categories;
            document.getElementById('totalTasksTracked').textContent = data.overall_stats.total_tasks_tracked;
            document.getElementById('tasksInProgress').textContent = data.overall_stats.tasks_in_progress;
            document.getElementById('tasksCompleted').textContent = data.overall_stats.tasks_completed;

            // Display category breakdown
            const categoryDiv = document.getElementById('categoryBreakdown');
            categoryDiv.innerHTML = '';
            
            data.categories.forEach(category => {
                const categoryCard = document.createElement('div');
                categoryCard.className = 'category-stats';
                categoryCard.style.cssText = 'border: 1px solid #ddd; padding: 15px; margin: 10px 0; border-radius: 8px;';
                
                const completionRate = category.tasks_started > 0 ? 
                    Math.round((category.tasks_completed / category.tasks_started) * 100) : 0;
                
                categoryCard.innerHTML = `
                    <h3>${category.category_key}</h3>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 10px; margin-top: 10px;">
                        <div><strong>Started:</strong> ${category.tasks_started}</div>
                        <div><strong>In Progress:</strong> ${category.tasks_in_progress}</div>
                        <div><strong>Completed:</strong> ${category.tasks_completed}</div>
                        <div><strong>Completion:</strong> ${completionRate}%</div>
                        <div><strong>First Started:</strong> ${category.first_started || 'N/A'}</div>
                        <div><strong>Last Activity:</strong> ${category.last_activity || 'N/A'}</div>
                    </div>
                `;
                categoryDiv.appendChild(categoryCard);
            });

            // Display recent activity
            const activityDiv = document.getElementById('recentActivity');
            activityDiv.innerHTML = '';
            
            if (data.recent_activity.length > 0) {
                const activityTable = document.createElement('table');
                activityTable.className = 'table';
                activityTable.style.width = '100%';
                
                activityTable.innerHTML = `
                    <thead>
                        <tr>
                            <th>Category</th>
                            <th>Task #</th>
                            <th>Status</th>
                            <th>Started</th>
                            <th>Completed</th>
                            <th>Last Updated</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${data.recent_activity.map(activity => `
                            <tr>
                                <td>${activity.category_key}</td>
                                <td>${activity.task_index + 1}</td>
                                <td>
                                    <span class="badge ${getStatusClass(activity.status)}">
                                        ${getStatusText(activity.status)}
                                    </span>
                                </td>
                                <td>${activity.started_at || '-'}</td>
                                <td>${activity.completed_at || '-'}</td>
                                <td>${activity.updated_at}</td>
                            </tr>
                        `).join('')}
                    </tbody>
                `;
                activityDiv.appendChild(activityTable);
            } else {
                activityDiv.innerHTML = '<p>No activity recorded yet.</p>';
            }
        }

        function getStatusClass(status) {
            switch (status) {
                case 0: return 'secondary';
                case 1: return 'warning';
                case 2: return 'success';
                default: return 'secondary';
            }
        }

        function getStatusText(status) {
            switch (status) {
                case 0: return 'Not Started';
                case 1: return 'In Progress';
                case 2: return 'Completed';
                default: return 'Unknown';
            }
        }
    </script>

    <style>
        .stats-grid .stat-card {
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
            text-align: center;
        }
        
        .stat-value {
            font-size: 2em;
            font-weight: bold;
            color: #2563eb;
        }
        
        .stat-label {
            color: #6b7280;
            margin-top: 5px;
        }
        
        .badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.85em;
            font-weight: 500;
        }
        
        .badge.success { background: #dcfce7; color: #166534; }
        .badge.warning { background: #fef3c7; color: #92400e; }
        .badge.secondary { background: #f1f5f9; color: #475569; }
        
        .table {
            border-collapse: collapse;
            border-spacing: 0;
        }
        
        .table th, .table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .table th {
            background: #f9fafb;
            font-weight: 600;
        }
    </style>
</body>
</html>