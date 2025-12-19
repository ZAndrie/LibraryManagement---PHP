<?php
require_once '../auth/check_auth.php';
require_once '../includes/functions.php';
checkRole('admin');

// Get filters
$role_filter = isset($_GET['role']) ? $_GET['role'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Build query
$sql = "SELECT u.*, 
        COALESCE(ubs.total_borrowed, 0) as total_borrowed,
        COALESCE(ubs.currently_borrowed, 0) as currently_borrowed,
        COALESCE(ubs.total_returned, 0) as total_returned,
        COALESCE(ubs.overdue_count, 0) as overdue_count,
        up.membership_status
        FROM users u 
        LEFT JOIN user_borrowing_stats ubs ON u.id = ubs.user_id
        LEFT JOIN user_profiles up ON u.id = up.user_id
        WHERE 1=1";
$params = array();
$types = "";

if (!empty($search)) {
    $sql .= " AND (u.username LIKE ? OR u.full_name LIKE ? OR u.email LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "sss";
}

if (!empty($role_filter)) {
    $sql .= " AND u.role = ?";
    $params[] = $role_filter;
    $types .= "s";
}

if (!empty($status_filter)) {
    $sql .= " AND up.membership_status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

$sql .= " ORDER BY u.id DESC";

if (!empty($params)) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $users = $stmt->get_result();
} else {
    $users = $conn->query($sql);
}

// Get statistics
$total_users = $conn->query("SELECT COUNT(*) as total FROM users WHERE role != 'admin'")->fetch_assoc()['total'];
$active_borrowers = $conn->query("SELECT COUNT(DISTINCT user_id) as total FROM user_borrowing_stats WHERE currently_borrowed > 0")->fetch_assoc()['total'];
$total_admins = $conn->query("SELECT COUNT(*) as total FROM users WHERE role = 'admin'")->fetch_assoc()['total'];
$total_clients = $conn->query("SELECT COUNT(*) as total FROM users WHERE role = 'client'")->fetch_assoc()['total'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Records - Library System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f6fa;
        }
        .navbar {
            background: linear-gradient(135deg, black 0%, black 100%);
            color: white;
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .navbar h1 { font-size: 24px; }
        .btn {
            padding: 8px 16px;
            border-radius: 6px;
            text-decoration: none;
            color: white;
            background: rgba(255,255,255,0.2);
            border: 1px solid rgba(255,255,255,0.3);
            cursor: pointer;
            transition: all 0.3s;
            display: inline-block;
            font-size: 14px;
        }
        .btn:hover { background: grey }
        .btn-primary {
            background: black;
            border: none;
        }
        .btn-view {
            background: black;
            border: none;
            padding: 6px 12px;
            font-size: 12px;
        }
        .container {
            max-width: 1400px;
            margin: 30px auto;
            padding: 0 20px;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,5);
        }
        .stat-card h3 {
            color: #666;
            font-size: 14px;
            margin-bottom: 10px;
            text-transform: uppercase;
        }
        .stat-card .number {
            font-size: 36px;
            font-weight: bold;
            color: black;
        }
        .card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,5);
            margin-bottom: 20px;
        }
        .filter-section {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr auto auto;
            gap: 15px;
            margin-bottom: 20px;
        }
        .filter-section input,
        .filter-section select {
            padding: 10px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 6px;
            font-size: 14px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        th {
            background: #f8f9fa;
            font-weight: 600;
            color: #333;
            font-size: 13px;
        }
        td { font-size: 13px; }
        .user-info {
            display: flex;
            flex-direction: column;
        }
        .user-info .name {
            font-weight: 600;
            color: #333;
        }
        .user-info .email {
            font-size: 12px;
            color: #666;
        }
        .role-badge {
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }
        .role-admin {
            background: #667eea;
            color: white;
        }
        .role-client {
            background: #27ae60;
            color: white;
        }
        .status-badge {
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }
        .status-active {
            background: #d4edda;
            color: #155724;
        }
        .status-suspended {
            background: #f8d7da;
            color: #721c24;
        }
        .status-inactive {
            background: #e2e3e5;
            color: #383d41;
        }
        .stats-mini {
            display: flex;
            gap: 15px;
            font-size: 12px;
        }
        .stats-mini .item {
            display: flex;
            flex-direction: column;
        }
        .stats-mini .label {
            color: #999;
        }
        .stats-mini .value {
            color: #333;
            font-weight: 600;
            font-size: 14px;
        }
        .value.warning {
            color: #f39c12;
        }
        .value.danger {
            color: #e74c3c;
        }
        .value.success {
            color: #27ae60;
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <h1><i class="fa fa-folder"></i> User Records</h1>
        <div style="display: flex; gap: 15px;">
            <a href="dashboard.php" class="btn">Dashboard</a>
            <a href="../auth/logout.php" class="btn">Logout</a>
        </div>
    </nav>

    <div class="container">
        <div class="stats-grid">
            <div class="stat-card">
                <h3>Total Users</h3>
                <div class="number"><?php echo $total_users; ?></div>
            </div>
            <div class="stat-card">
                <h3>Administrators</h3>
                <div class="number"><?php echo $total_admins; ?></div>
            </div>
            <div class="stat-card">
                <h3>Clients</h3>
                <div class="number"><?php echo $total_clients; ?></div>
            </div>
            <div class="stat-card">
                <h3>Active Borrowers</h3>
                <div class="number"><?php echo $active_borrowers; ?></div>
            </div>
        </div>

        <div class="card">
            <h2 style="margin-bottom: 20px;">Filter & Search Users</h2>
            <form method="GET" class="filter-section">
                <input type="text" name="search" placeholder="Search by name, username, or email..." 
                       value="<?php echo htmlspecialchars($search); ?>">
                
                <select name="role">
                    <option value="">All Roles</option>
                    <option value="admin" <?php echo $role_filter == 'admin' ? 'selected' : ''; ?>>Admin</option>
                    <option value="client" <?php echo $role_filter == 'client' ? 'selected' : ''; ?>>Client</option>
                </select>

                <select name="status">
                    <option value="">All Status</option>
                    <option value="active" <?php echo $status_filter == 'active' ? 'selected' : ''; ?>>Active</option>
                    <option value="suspended" <?php echo $status_filter == 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                    <option value="inactive" <?php echo $status_filter == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                </select>
                
                <button type="submit" class="btn btn-primary">Apply Filters</button>
                <a href="user_records.php" class="btn btn-primary">Clear</a>
            </form>
        </div>

        <div class="card">
            <h2 style="margin-bottom: 20px;">User Records</h2>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>User Information</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Borrowing Statistics</th>
                        <th>Last Login</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($users && $users->num_rows > 0): ?>
                        <?php while($user = $users->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($user['id']); ?></td>
                                <td>
                                    <div class="user-info">
                                        <span class="name"><?php echo htmlspecialchars($user['full_name']); ?></span>
                                        <span class="email">@<?php echo htmlspecialchars($user['username']); ?> • <?php echo htmlspecialchars($user['email']); ?></span>
                                    </div>
                                </td>
                                <td>
                                    <span class="role-badge role-<?php echo htmlspecialchars($user['role']); ?>">
                                        <?php echo htmlspecialchars(ucfirst($user['role'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php 
                                    $status = $user['membership_status'] ?? 'active';
                                    ?>
                                    <span class="status-badge status-<?php echo htmlspecialchars($status); ?>">
                                        <?php echo htmlspecialchars(ucfirst($status)); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="stats-mini">
                                        <div class="item">
                                            <span class="label">Total</span>
                                            <span class="value"><?php echo htmlspecialchars($user['total_borrowed']); ?></span>
                                        </div>
                                        <div class="item">
                                            <span class="label">Current</span>
                                            <span class="value warning"><?php echo htmlspecialchars($user['currently_borrowed']); ?></span>
                                        </div>
                                        <div class="item">
                                            <span class="label">Returned</span>
                                            <span class="value success"><?php echo htmlspecialchars($user['total_returned']); ?></span>
                                        </div>
                                        <div class="item">
                                            <span class="label">Overdue</span>
                                            <span class="value danger"><?php echo htmlspecialchars($user['overdue_count']); ?></span>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($user['last_login']): ?>
                                        <?php echo date('M d, Y', strtotime($user['last_login'])); ?>
                                    <?php else: ?>
                                        <span style="color: #999;">Never</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="user_profile.php?id=<?php echo htmlspecialchars($user['id']); ?>" class="btn btn-view">View Profile</a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 40px; color: #999;">
                                No users found
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>