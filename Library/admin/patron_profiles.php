<?php
require_once '../auth/check_auth.php';
require_once '../includes/functions.php';
checkRole('admin');

$search = isset($_GET['search']) ? $_GET['search'] : '';
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';

// Build query based on filters
$sql = "SELECT p.*, 
        (SELECT COUNT(*) FROM borrowings WHERE patron_id = p.id AND status IN ('borrowed', 'overdue')) as currently_borrowed,
        (SELECT COUNT(*) FROM borrowings WHERE patron_id = p.id AND status = 'overdue') as overdue_count,
        (SELECT COALESCE(SUM(amount), 0) FROM fines WHERE patron_id = p.id AND status = 'unpaid') as unpaid_fines
        FROM patrons p";

$where_conditions = [];
$params = [];
$types = '';

if (!empty($search)) {
    $where_conditions[] = "(p.name LIKE ? OR p.email LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'ss';
}

// Apply filters
if ($filter == 'active') {
    $where_conditions[] = "EXISTS (SELECT 1 FROM borrowings WHERE patron_id = p.id AND status IN ('borrowed', 'overdue'))";
} elseif ($filter == 'overdue') {
    $where_conditions[] = "EXISTS (SELECT 1 FROM borrowings WHERE patron_id = p.id AND status = 'overdue')";
} elseif ($filter == 'fines') {
    $where_conditions[] = "EXISTS (SELECT 1 FROM fines WHERE patron_id = p.id AND status = 'unpaid')";
}

if (!empty($where_conditions)) {
    $sql .= " WHERE " . implode(" AND ", $where_conditions);
}

$sql .= " ORDER BY p.name ASC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$patrons = $stmt->get_result();

// Get statistics
$total_patrons = $conn->query("SELECT COUNT(*) as count FROM patrons")->fetch_assoc()['count'];
$active_borrowers = $conn->query("SELECT COUNT(DISTINCT patron_id) as count FROM borrowings WHERE status IN ('borrowed', 'overdue')")->fetch_assoc()['count'];
$patrons_with_overdues = $conn->query("SELECT COUNT(DISTINCT patron_id) as count FROM borrowings WHERE status = 'overdue'")->fetch_assoc()['count'];
$patrons_with_fines = $conn->query("SELECT COUNT(DISTINCT patron_id) as count FROM fines WHERE status = 'unpaid'")->fetch_assoc()['count'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patron Profiles - Library System</title>
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
        .btn-info {
            background: black;
            border: none;
        }
        .container {
            max-width: 1400px;
            margin: 30px auto;
            padding: 0 20px;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            text-align: center;
            cursor: pointer;
            transition: transform 0.3s;
        }
        .stat-card:hover {
            transform: translateY(-5px);
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
        .filters {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        .filter-btn {
            padding: 8px 16px;
            border-radius: 6px;
            background: #f5f6fa;
            border: 2px solid #e0e0e0;
            color: #333;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            font-size: 14px;
        }
        .filter-btn:hover {
            background: #e8eaf6;
            border-color: black;
        }
        .filter-btn.active {
            background: black;
            color: white;
            border-color: black;
        }
        .search-box {
            display: flex;
            gap: 10px;
        }
        .search-box input {
            padding: 10px 15px;
            border: 2px solid black;
            border-radius: 6px;
            width: 300px;
        }
        .patron-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
        }
        .patron-card {
            background: white;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            padding: 20px;
            transition: all 0.3s;
            cursor: pointer;
        }
        .patron-card:hover {
            border-color: black;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }
        .patron-header {
            display: flex;
            gap: 15px;
            margin-bottom: 15px;
        }
        .patron-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, black 0%, grey 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: white;
            font-weight: bold;
            flex-shrink: 0;
        }
        .patron-info {
            flex: 1;
        }
        .patron-name {
            font-size: 18px;
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
        }
        .patron-email {
            font-size: 13px;
            color: #666;
        }
        .patron-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #f0f0f0;
        }
        .stat-item {
            text-align: center;
        }
        .stat-item .label {
            font-size: 11px;
            color: #999;
            margin-bottom: 5px;
            text-transform: uppercase;
        }
        .stat-item .value {
            font-size: 20px;
            font-weight: bold;
            color: #333;
        }
        .stat-item .value.warning {
            color: #f39c12;
        }
        .stat-item .value.danger {
            color: #e74c3c;
        }
        .patron-badges {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-top: 10px;
        }
        .badge {
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
        }
        .badge-warning {
            background: #fff3cd;
            color: #856404;
        }
        .badge-danger {
            background: #f8d7da;
            color: #721c24;
        }
        .badge-active {
            background: #d4edda;
            color: #155724;
        }
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #999;
        }
        .empty-state i {
            font-size: 64px;
            margin-bottom: 20px;
            opacity: 0.5;
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <h1><i class="fas fa-user-circle"></i> Patron Profiles</h1>
        <div class="nav-links">
            <a href="dashboard.php" class="btn">Dashboard</a>
            <a href="../auth/logout.php" class="btn">Logout</a>
        </div>
    </nav>

    <div class="container">
        <!-- Statistics Overview -->
        <div class="stats-grid">
            <div class="stat-card" onclick="window.location.href='?filter=all'">
                <h3>Total Patrons</h3>
                <div class="number"><?php echo $total_patrons; ?></div>
            </div>
            <div class="stat-card" onclick="window.location.href='?filter=active'">
                <h3>Active Borrowers</h3>
                <div class="number"><?php echo $active_borrowers; ?></div>
            </div>
            <div class="stat-card" onclick="window.location.href='?filter=overdue'">
                <h3>With Overdue Books</h3>
                <div class="number"><?php echo $patrons_with_overdues; ?></div>
            </div>
            <div class="stat-card" onclick="window.location.href='?filter=fines'">
                <h3>With Unpaid Fines</h3>
                <div class="number"><?php echo $patrons_with_fines; ?></div>
            </div>
        </div>

        <!-- Filters and Search -->
        <div class="card">
            <div class="header-section">
                <div class="filters">
                    <a href="?filter=all" class="filter-btn <?php echo $filter == 'all' ? 'active' : ''; ?>">
                        <i class="fas fa-users"></i> All Patrons
                    </a>
                    <a href="?filter=active" class="filter-btn <?php echo $filter == 'active' ? 'active' : ''; ?>">
                        <i class="fas fa-book-open"></i> Active Borrowers
                    </a>
                    <a href="?filter=overdue" class="filter-btn <?php echo $filter == 'overdue' ? 'active' : ''; ?>">
                        <i class="fas fa-clock"></i> With Overdues
                    </a>
                    <a href="?filter=fines" class="filter-btn <?php echo $filter == 'fines' ? 'active' : ''; ?>">
                        <i class="fas fa-money-bill-wave"></i> With Fines
                    </a>
                </div>
                <form method="GET" class="search-box">
                    <input type="hidden" name="filter" value="<?php echo htmlspecialchars($filter); ?>">
                    <input type="text" name="search" placeholder="Search patrons..." 
                           value="<?php echo htmlspecialchars($search); ?>">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> Search
                    </button>
                </form>
            </div>
        </div>

        <!-- Patron Cards Grid -->
        <div style="margin-top: 30px;">
            <?php if ($patrons->num_rows > 0): ?>
                <div class="patron-grid">
                    <?php while($patron = $patrons->fetch_assoc()): ?>
                        <div class="patron-card" onclick="window.location.href='patron_profile.php?id=<?php echo $patron['id']; ?>'">
                            <div class="patron-header">
                                <div class="patron-avatar">
                                    <?php echo strtoupper(substr($patron['name'], 0, 2)); ?>
                                </div>
                                <div class="patron-info">
                                    <div class="patron-name"><?php echo htmlspecialchars($patron['name']); ?></div>
                                    <div class="patron-email">
                                        <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($patron['email']); ?>
                                    </div>
                                    <div class="patron-email" style="margin-top: 3px;">
                                        <i class="fas fa-calendar"></i> Member since <?php echo date('M Y', strtotime($patron['created_at'])); ?>
                                    </div>
                                </div>
                            </div>

                            <?php if ($patron['overdue_count'] > 0 || $patron['unpaid_fines'] > 0): ?>
                                <div class="patron-badges">
                                    <?php if ($patron['overdue_count'] > 0): ?>
                                        <span class="badge badge-danger">
                                            <i class="fas fa-exclamation-circle"></i> <?php echo $patron['overdue_count']; ?> Overdue
                                        </span>
                                    <?php endif; ?>
                                    <?php if ($patron['unpaid_fines'] > 0): ?>
                                        <span class="badge badge-warning">
                                            <i class="fas fa-money-bill-wave"></i> ₱<?php echo number_format($patron['unpaid_fines'], 2); ?> Fines
                                        </span>
                                    <?php endif; ?>
                                </div>
                            <?php elseif ($patron['currently_borrowed'] > 0): ?>
                                <div class="patron-badges">
                                    <span class="badge badge-active">
                                        <i class="fas fa-check-circle"></i> Active Borrower
                                    </span>
                                </div>
                            <?php endif; ?>

                            <div class="patron-stats">
                                <div class="stat-item">
                                    <div class="label">Borrowed</div>
                                    <div class="value <?php echo $patron['currently_borrowed'] > 0 ? 'warning' : ''; ?>">
                                        <?php echo $patron['currently_borrowed']; ?>
                                    </div>
                                </div>
                                <div class="stat-item">
                                    <div class="label">Overdue</div>
                                    <div class="value <?php echo $patron['overdue_count'] > 0 ? 'danger' : ''; ?>">
                                        <?php echo $patron['overdue_count']; ?>
                                    </div>
                                </div>
                                <div class="stat-item">
                                    <div class="label">Fines</div>
                                    <div class="value <?php echo $patron['unpaid_fines'] > 0 ? 'danger' : ''; ?>">
                                        ₱<?php echo number_format($patron['unpaid_fines'], 0); ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="card">
                    <div class="empty-state">
                        <i class="fas fa-user-slash"></i>
                        <h3>No Patrons Found</h3>
                        <p>Try adjusting your search or filter criteria</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>