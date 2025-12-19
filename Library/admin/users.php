<?php
require_once '../auth/check_auth.php';
require_once '../includes/functions.php';
checkRole('admin');

$message = '';
$error = '';

// Handle delete
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    
    // Prevent deleting own account
    if ($id == $_SESSION['user_id']) {
        $error = "You cannot delete your own account!";
    } else {
        $sql = "DELETE FROM users WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            $message = "User deleted successfully!";
        } else {
            $error = "Error deleting user.";
        }
    }
}

// Get filter and search parameters
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Build query based on filter
$search_param = "%$search%";

if ($filter == 'users') {
    // Show only users (exclude patrons-only)
    $sql = "SELECT u.*, 
            (SELECT COUNT(*) FROM patrons p WHERE p.email = u.email) as has_patron
            FROM users u 
            WHERE (u.username LIKE ? OR u.full_name LIKE ? OR u.email LIKE ?)
            ORDER BY u.id DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sss", $search_param, $search_param, $search_param);
} elseif ($filter == 'admin') {
    // Show only admins
    $sql = "SELECT u.*, 
            (SELECT COUNT(*) FROM patrons p WHERE p.email = u.email) as has_patron
            FROM users u 
            WHERE u.role = 'admin' AND (u.username LIKE ? OR u.full_name LIKE ? OR u.email LIKE ?)
            ORDER BY u.id DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sss", $search_param, $search_param, $search_param);
} elseif ($filter == 'client') {
    // Show only clients
    $sql = "SELECT u.*, 
            (SELECT COUNT(*) FROM patrons p WHERE p.email = u.email) as has_patron
            FROM users u 
            WHERE u.role = 'client' AND (u.username LIKE ? OR u.full_name LIKE ? OR u.email LIKE ?)
            ORDER BY u.id DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sss", $search_param, $search_param, $search_param);
} elseif ($filter == 'patrons') {
    // Show all patrons (including those without user accounts)
    $sql = "SELECT p.id, p.name as full_name, p.email, p.phone, p.address, p.created_at,
            u.id as user_id, u.username, u.role,
            'patron' as account_type
            FROM patrons p
            LEFT JOIN users u ON p.email = u.email
            WHERE (p.name LIKE ? OR p.email LIKE ? OR p.phone LIKE ?)
            ORDER BY p.id DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sss", $search_param, $search_param, $search_param);
} else {
    // Show all (users + patrons)
    $sql = "SELECT u.*, 
            (SELECT COUNT(*) FROM patrons p WHERE p.email = u.email) as has_patron
            FROM users u 
            WHERE (u.username LIKE ? OR u.full_name LIKE ? OR u.email LIKE ?)
            ORDER BY u.id DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sss", $search_param, $search_param, $search_param);
}

$stmt->execute();
$results = $stmt->get_result();

// Get counts for filter badges
$total_users = $conn->query("SELECT COUNT(*) as count FROM users")->fetch_assoc()['count'];
$total_admins = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'admin'")->fetch_assoc()['count'];
$total_clients = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'client'")->fetch_assoc()['count'];
$total_patrons = $conn->query("SELECT COUNT(*) as count FROM patrons")->fetch_assoc()['count'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - Library System</title>
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
        .navbar .nav-links { display: flex; gap: 15px; }
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
            border: none;
            font-size: 14px;
        }
        .btn:hover { background: grey; }
        .btn-primary {
            background: black;
            border: none;
        }
        .btn-success {
            background: black;
            border: none;
        }
        .btn-danger {
            background: black;
            border: none;
        }
        .btn-warning {
            background: black;
            border: none;
        }
        .btn-info {
            background: black;
            border: none;
        }
        .container {
            max-width: 1400px;
            margin: 30px auto;
            padding: 0 20px;
        }
        .card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .header-section {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            flex-wrap: wrap;
            gap: 15px;
        }
        .search-box {
            display: flex;
            gap: 10px;
        }
        .search-box input {
            padding: 10px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 6px;
            width: 300px;
        }
        .filter-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 25px;
            flex-wrap: wrap;
        }
        .filter-tab {
            padding: 10px 20px;
            border-radius: 8px;
            text-decoration: none;
            color: #666;
            background: #f5f5f5;
            border: 2px solid transparent;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            font-weight: 500;
        }
        .filter-tab:hover {
            background: #e8e8e8;
        }
        .filter-tab.active {
            background: black;
            color: white;
            border-color: black;
        }
        .filter-tab .count {
            background: rgba(255,255,255,0.2);
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 12px;
            font-weight: 600;
        }
        .filter-tab.active .count {
            background: rgba(255,255,255,0.3);
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
        }
        .actions {
            display: flex;
            gap: 8px;
        }
        .alert {
            padding: 12px 20px;
            border-radius: 6px;
            margin-bottom: 20px;
        }
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
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
        .role-patron {
            background: #3498db;
            color: white;
        }
        .type-badge {
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            background: #e8f5e9;
            color: #2e7d32;
        }
        .type-badge.user-account {
            background: #e3f2fd;
            color: #1976d2;
        }
        .type-badge.patron-only {
            background: #fff3e0;
            color: #e65100;
        }
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #999;
        }
        .empty-state i {
            font-size: 48px;
            margin-bottom: 15px;
            opacity: 0.5;
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <h1><i class="fas fa-users"></i> Manage Users & Patrons</h1>
        <div class="nav-links">
            <a href="dashboard.php" class="btn">Dashboard</a>
            <a href="../auth/logout.php" class="btn">Logout</a>
        </div>
    </nav>

    <div class="container">
        <?php if ($message): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo $message; ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></div>
        <?php endif; ?>

        <div class="card">
            <div class="header-section">
                <h2>User & Patron Management</h2>
                <div style="display: flex; gap: 10px;">
                    <form method="GET" class="search-box">
                        <input type="hidden" name="filter" value="<?php echo htmlspecialchars($filter); ?>">
                        <input type="text" name="search" placeholder="Search by name, email, username..." 
                               value="<?php echo htmlspecialchars($search); ?>">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i> Search
                        </button>
                        <?php if ($search): ?>
                            <a href="?filter=<?php echo $filter; ?>" class="btn">Clear</a>
                        <?php endif; ?>
                    </form>
                    <a href="add_user.php" class="btn btn-success">
                        <i class="fas fa-plus"></i> Add New User
                    </a>
                </div>
            </div>

            <div class="filter-tabs">
                <a href="?filter=all&search=<?php echo urlencode($search); ?>" 
                   class="filter-tab <?php echo $filter == 'all' ? 'active' : ''; ?>">
                    <i class="fas fa-layer-group"></i>
                    All
                    <span class="count"><?php echo $total_users; ?></span>
                </a>
                <a href="?filter=admin&search=<?php echo urlencode($search); ?>" 
                   class="filter-tab <?php echo $filter == 'admin' ? 'active' : ''; ?>">
                    <i class="fas fa-user-shield"></i>
                    Admins
                    <span class="count"><?php echo $total_admins; ?></span>
                </a>
                <a href="?filter=client&search=<?php echo urlencode($search); ?>" 
                   class="filter-tab <?php echo $filter == 'client' ? 'active' : ''; ?>">
                    <i class="fas fa-user"></i>
                    Clients
                    <span class="count"><?php echo $total_clients; ?></span>
                </a>
                <a href="?filter=patrons&search=<?php echo urlencode($search); ?>" 
                   class="filter-tab <?php echo $filter == 'patrons' ? 'active' : ''; ?>">
                    <i class="fas fa-address-card"></i>
                    Patrons
                    <span class="count"><?php echo $total_patrons; ?></span>
                </a>
            </div>

            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <?php if ($filter != 'patrons'): ?>
                        <th>Username</th>
                        <?php endif; ?>
                        <th>Role/Type</th>
                        <th>Account Type</th>
                        <?php if ($filter == 'patrons'): ?>
                        <th>Phone</th>
                        <?php endif; ?>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($results->num_rows > 0): ?>
                        <?php while($row = $results->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo $row['id']; ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($row['full_name']); ?></strong>
                                </td>
                                <td><?php echo htmlspecialchars($row['email']); ?></td>
                                <?php if ($filter != 'patrons'): ?>
                                <td>
                                    <?php if (isset($row['username'])): ?>
                                        @<?php echo htmlspecialchars($row['username']); ?>
                                    <?php else: ?>
                                        <span style="color: #999;">-</span>
                                    <?php endif; ?>
                                </td>
                                <?php endif; ?>
                                <td>
                                    <?php if ($filter == 'patrons'): ?>
                                        <?php if (isset($row['role'])): ?>
                                            <span class="role-badge role-<?php echo $row['role']; ?>">
                                                <?php echo ucfirst($row['role']); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="role-badge role-patron">Patron Only</span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="role-badge role-<?php echo $row['role']; ?>">
                                            <?php echo ucfirst($row['role']); ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($filter == 'patrons'): ?>
                                        <?php if (isset($row['user_id']) && $row['user_id']): ?>
                                            <span class="type-badge user-account">
                                                <i class="fas fa-check-circle"></i> Has User Account
                                            </span>
                                        <?php else: ?>
                                            <span class="type-badge patron-only">
                                                <i class="fas fa-address-card"></i> Patron Only
                                            </span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <?php if (isset($row['has_patron']) && $row['has_patron'] > 0): ?>
                                            <span class="type-badge">
                                                <i class="fas fa-check-circle"></i> User + Patron
                                            </span>
                                        <?php else: ?>
                                            <span class="type-badge user-account">
                                                <i class="fas fa-user"></i> User Only
                                            </span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                                <?php if ($filter == 'patrons'): ?>
                                <td><?php echo htmlspecialchars($row['phone'] ?? '-'); ?></td>
                                <?php endif; ?>
                                <td><?php echo date('M d, Y', strtotime($row['created_at'])); ?></td>
                                <td>
                                    <div class="actions">
                                        <?php if ($filter != 'patrons' || (isset($row['user_id']) && $row['user_id'])): ?>
                                            <a href="user_profile.php?id=<?php echo isset($row['user_id']) ? $row['user_id'] : $row['id']; ?>" 
                                               class="btn btn-info" 
                                               title="View Profile">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        <?php endif; ?>
                                        
                                        <?php if ($filter != 'patrons'): ?>
                                            <a href="edit_user.php?id=<?php echo $row['id']; ?>" 
                                               class="btn btn-warning"
                                               title="Edit User">
                                                <i class="fas fa-pen"></i>
                                            </a>
                                            <?php if ($row['id'] != $_SESSION['user_id']): ?>
                                                <a href="?delete=<?php echo $row['id']; ?>&filter=<?php echo $filter; ?>" 
                                                   class="btn btn-danger" 
                                                   title="Delete User"
                                                   onclick="return confirm('Are you sure you want to delete this user?')">
                                                    <i class="fas fa-trash-alt"></i>
                                                </a>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <a href="patrons.php" class="btn btn-info" title="View in Patrons">
                                                <i class="fas fa-external-link-alt"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8">
                                <div class="empty-state">
                                    <i class="fas fa-search"></i>
                                    <p>No results found</p>
                                    <?php if ($search): ?>
                                        <p style="font-size: 14px; margin-top: 10px;">
                                            Try adjusting your search terms
                                        </p>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>