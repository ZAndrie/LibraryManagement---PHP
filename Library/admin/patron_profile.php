<?php
require_once '../auth/check_auth.php';
require_once '../includes/functions.php';
checkRole('admin');

// Get patron ID from URL
if (!isset($_GET['id'])) {
    header("Location: patrons.php");
    exit();
}

$patron_id = intval($_GET['id']);

// Get patron information
$sql = "SELECT * FROM patrons WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $patron_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    header("Location: patrons.php");
    exit();
}

$patron = $result->fetch_assoc();
$patron_email = $patron['email'];
$borrowing_stats = getUserBorrowingStats($conn, $patron_email);
$current_borrowings = getUserCurrentBorrowings($conn, $patron_email);
// Get patron statistics
$stats = getPatronStatistics($conn, $patron_id);

// Get currently borrowed books
$sql = "SELECT b.*, bk.title, bk.author, bk.isbn, bk.category,
        DATEDIFF(b.due_date, CURDATE()) as days_until_due,
        DATEDIFF(CURDATE(), b.due_date) as days_overdue
        FROM borrowings b
        JOIN books bk ON b.book_id = bk.id
        WHERE b.patron_id = ? AND b.status IN ('borrowed', 'overdue')
        ORDER BY b.due_date ASC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $patron_id);
$stmt->execute();
$current_borrowings = $stmt->get_result();

// Get borrowing history
$sql = "SELECT b.*, bk.title, bk.author, bk.category,
        DATEDIFF(b.return_date, b.due_date) as days_late
        FROM borrowings b
        JOIN books bk ON b.book_id = bk.id
        WHERE b.patron_id = ?
        ORDER BY b.borrow_date DESC
        LIMIT 20";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $patron_id);
$stmt->execute();
$borrowing_history = $stmt->get_result();

// Get fines
$sql = "SELECT f.*, b.title as book_title, br.due_date, br.return_date
        FROM fines f
        JOIN borrowings br ON f.borrowing_id = br.id
        JOIN books b ON br.book_id = b.id
        WHERE f.patron_id = ?
        ORDER BY f.created_at DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $patron_id);
$stmt->execute();
$fines = $stmt->get_result();

// Calculate total unpaid fines
$total_unpaid_fines = getPatronTotalFines($conn, $patron_id);

// Get favorite categories
$favorite_categories = getPatronFavoriteCategories($conn, $patron_id);

// Get borrowing trends
$monthly_trends = getPatronBorrowingTrends($conn, $patron_id);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patron Profile - <?php echo htmlspecialchars($patron['name']); ?></title>
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
        .btn:hover { background: grey; }
        .btn-warning {
            background: black;
            border: none;
        }
        .container {
            max-width: 1400px;
            margin: 30px auto;
            padding: 0 20px;
        }
        .profile-header {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 30px;
            display: grid;
            grid-template-columns: auto 1fr auto;
            gap: 30px;
            align-items: center;
        }
        .profile-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: linear-gradient(135deg, black 0%, grey 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 48px;
            color: white;
            font-weight: bold;
        }
        .profile-info h2 {
            color: #333;
            margin-bottom: 10px;
        }
        .profile-info .meta {
            color: #666;
            font-size: 14px;
            margin-bottom: 15px;
        }
        .profile-badges {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        .badge {
            padding: 6px 15px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: 600;
        }
        .badge-active {
            background: #d4edda;
            color: #155724;
        }
        .badge-warning {
            background: #fff3cd;
            color: #856404;
        }
        .badge-danger {
            background: #f8d7da;
            color: #721c24;
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
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            text-align: center;
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
        }
        .stat-card .number.primary { color: black; }
        .stat-card .number.warning { color: #f39c12; }
        .stat-card .number.success { color: #27ae60; }
        .stat-card .number.danger { color: #e74c3c; }
        .content-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 30px;
        }
        .card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .card h3 {
            color: #333;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }
        .info-row {
            display: flex;
            padding: 12px 0;
            border-bottom: 1px solid #f5f5f5;
        }
        .info-row .label {
            flex: 0 0 150px;
            color: #666;
            font-weight: 600;
        }
        .info-row .value {
            color: #333;
        }
        .book-item {
            padding: 15px;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            margin-bottom: 15px;
        }
        .book-item h4 {
            color: #333;
            margin-bottom: 5px;
        }
        .book-item .author {
            color: #666;
            font-size: 14px;
            margin-bottom: 10px;
        }
        .book-item .meta {
            display: flex;
            gap: 15px;
            font-size: 13px;
            flex-wrap: wrap;
        }
        .book-item .meta span {
            color: #999;
        }
        .status-overdue {
            color: #e74c3c;
            font-weight: 600;
        }
        .status-due-soon {
            color: #f39c12;
            font-weight: 600;
        }
        .status-ok {
            color: #27ae60;
            font-weight: 600;
        }
        .full-width {
            grid-column: 1 / -1;
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
        .empty-state {
            text-align: center;
            padding: 40px;
            color: #999;
        }
        .empty-state i {
            font-size: 48px;
            margin-bottom: 15px;
            opacity: 0.5;
        }
        .category-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 6px;
            margin-bottom: 8px;
        }
        .category-item .name {
            font-weight: 600;
            color: #333;
        }
        .category-item .count {
            background: black;
            color: white;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
        }
        .trend-item {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        .trend-item:last-child {
            border-bottom: none;
        }
        .fine-item {
            padding: 15px;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            margin-bottom: 15px;
        }
        .fine-item .amount {
            font-size: 20px;
            font-weight: bold;
            color: #e74c3c;
        }
        .alert-box {
            background: #fff3cd;
            border-left: 4px solid #f39c12;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
        }
        .alert-box strong {
            color: #856404;
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <h1><i class="fas fa-user-circle"></i> Patron Profile</h1>
        <div style="display: flex; gap: 15px;">
            <a href="patrons.php" class="btn">← Back to Patrons</a>
            <a href="dashboard.php" class="btn">Dashboard</a>
            <a href="../auth/logout.php" class="btn">Logout</a>
        </div>
    </nav>

    <div class="container">
        <!-- Unpaid Fines Alert -->
        <?php if ($total_unpaid_fines > 0): ?>
        <div class="alert-box">
            <strong><i class="fas fa-exclamation-triangle"></i> Alert:</strong> 
            This patron has unpaid fines totaling ₱<?php echo number_format($total_unpaid_fines, 2); ?>
        </div>
        <?php endif; ?>

        <!-- Profile Header -->
        <div class="profile-header">
            <div class="profile-avatar">
                <?php echo strtoupper(substr($patron['name'], 0, 2)); ?>
            </div>
            <div class="profile-info">
                <h2><?php echo htmlspecialchars($patron['name']); ?></h2>
                <div class="meta">
                    <?php echo htmlspecialchars($patron['email']); ?> • 
                    <?php echo htmlspecialchars($patron['phone'] ?? 'No phone'); ?> •
                    Member since <?php echo date('M Y', strtotime($patron['created_at'])); ?>
                </div>
                <div class="profile-badges">
                    <?php if ($stats['overdue_count'] > 0): ?>
                        <span class="badge badge-danger">
                            <?php echo $stats['overdue_count']; ?> Overdue Book(s)
                        </span>
                    <?php endif; ?>
                    <?php if ($total_unpaid_fines > 0): ?>
                        <span class="badge badge-warning">
                            ₱<?php echo number_format($total_unpaid_fines, 2); ?> Unpaid Fines
                        </span>
                    <?php endif; ?>
                    <?php if ($stats['currently_borrowed'] > 0): ?>
                        <span class="badge badge-active">
                            <?php echo $stats['currently_borrowed']; ?> Book(s) Borrowed
                        </span>
                    <?php endif; ?>
                </div>
            </div>
            <div>
                <a href="edit_patron.php?id=<?php echo $patron['id']; ?>" class="btn btn-warning">
                    <i class="fas fa-edit"></i> Edit Patron
                </a>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <h3>Total Borrowed</h3>
                <div class="number primary"><?php echo $stats['total_borrowed']; ?></div>
            </div>
            <div class="stat-card">
                <h3>Currently Borrowed</h3>
                <div class="number warning"><?php echo $stats['currently_borrowed']; ?></div>
            </div>
            <div class="stat-card">
                <h3>Returned Books</h3>
                <div class="number success"><?php echo $stats['total_returned']; ?></div>
            </div>
            <div class="stat-card">
                <h3>Overdue Books</h3>
                <div class="number danger"><?php echo $stats['overdue_count']; ?></div>
            </div>
            <div class="stat-card">
                <h3>Unpaid Fines</h3>
                <div class="number danger">₱<?php echo number_format($total_unpaid_fines, 2); ?></div>
            </div>
            <div class="stat-card">
                <h3>Return Rate</h3>
                <div class="number success">
                    <?php 
                    echo $stats['total_borrowed'] > 0 
                        ? round(($stats['total_returned'] / $stats['total_borrowed']) * 100) 
                        : 0; 
                    ?>%
                </div>
            </div>
        </div>

        <!-- Personal Information and Reading Preferences -->
        <div class="content-grid">
            <div class="card">
                <h3><i class="fas fa-user"></i> Personal Information</h3>
                <div class="info-row">
                    <div class="label">Full Name:</div>
                    <div class="value"><?php echo htmlspecialchars($patron['name']); ?></div>
                </div>
                <div class="info-row">
                    <div class="label">Email:</div>
                    <div class="value"><?php echo htmlspecialchars($patron['email']); ?></div>
                </div>
                <div class="info-row">
                    <div class="label">Phone:</div>
                    <div class="value"><?php echo htmlspecialchars($patron['phone'] ?? 'Not provided'); ?></div>
                </div>
                <div class="info-row">
                    <div class="label">Address:</div>
                    <div class="value"><?php echo htmlspecialchars($patron['address'] ?? 'Not provided'); ?></div>
                </div>
                <div class="info-row">
                    <div class="label">Member Since:</div>
                    <div class="value"><?php echo date('M d, Y', strtotime($patron['created_at'])); ?></div>
                </div>
                <div class="info-row">
                    <div class="label">Patron ID:</div>
                    <div class="value">#<?php echo str_pad($patron['id'], 6, '0', STR_PAD_LEFT); ?></div>
                </div>
            </div>

            <div class="card">
                <h3><i class="fas fa-heart"></i> Favorite Categories</h3>
                <?php if (!empty($favorite_categories)): ?>
                    <?php foreach ($favorite_categories as $category): ?>
                        <div class="category-item">
                            <span class="name"><?php echo htmlspecialchars($category['category']); ?></span>
                            <span class="count"><?php echo $category['count']; ?> books</span>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-book"></i>
                        <p>No borrowing history yet</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Currently Borrowed Books -->
        <div class="card" style="margin-bottom: 30px;">
            <h3><i class="fas fa-book-open"></i> Currently Borrowed Books</h3>
            <?php if ($current_borrowings->num_rows > 0): ?>
                <?php while($borrowing = $current_borrowings->fetch_assoc()): ?>
                    <?php
                    $days_until_due = $borrowing['days_until_due'];
                    $status_class = 'status-ok';
                    $status_text = $days_until_due . ' days remaining';
                    
                    if ($borrowing['status'] == 'overdue') {
                        $status_class = 'status-overdue';
                        $status_text = $borrowing['days_overdue'] . ' days overdue';
                    } elseif ($days_until_due <= 3 && $days_until_due >= 0) {
                        $status_class = 'status-due-soon';
                        $status_text = 'Due in ' . $days_until_due . ' days';
                    }
                    ?>
                    <div class="book-item">
                        <h4><?php echo htmlspecialchars($borrowing['title']); ?></h4>
                        <div class="author">by <?php echo htmlspecialchars($borrowing['author']); ?></div>
                        <div class="meta">
                            <span><i class="fas fa-calendar-alt"></i> Borrowed: <?php echo date('M d, Y', strtotime($borrowing['borrow_date'])); ?></span>
                            <span><i class="fas fa-calendar-check"></i> Due: <?php echo date('M d, Y', strtotime($borrowing['due_date'])); ?></span>
                            <span class="<?php echo $status_class; ?>"><i class="fas fa-clock"></i> <?php echo $status_text; ?></span>
                            <span><i class="fas fa-tag"></i> <?php echo htmlspecialchars($borrowing['category']); ?></span>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-book"></i>
                    <p>No books currently borrowed</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Fines and Monthly Trends -->
        <div class="content-grid">
            <div class="card">
                <h3><i class="fas fa-money-bill-wave"></i> Fines & Penalties</h3>
                <?php if ($fines->num_rows > 0): ?>
                    <?php while($fine = $fines->fetch_assoc()): ?>
                        <div class="fine-item">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                                <strong><?php echo htmlspecialchars($fine['book_title']); ?></strong>
                                <span class="amount">₱<?php echo number_format($fine['amount'], 2); ?></span>
                            </div>
                            <div style="font-size: 12px; color: #666;">
                                <div><i class="fas fa-calendar"></i> Due: <?php echo date('M d, Y', strtotime($fine['due_date'])); ?></div>
                                <?php if ($fine['return_date']): ?>
                                    <div><i class="fas fa-undo"></i> Returned: <?php echo date('M d, Y', strtotime($fine['return_date'])); ?></div>
                                <?php endif; ?>
                                <div><i class="fas fa-clock"></i> Days Overdue: <?php echo $fine['days_overdue']; ?></div>
                            </div>
                            <div style="margin-top: 10px;">
                                <span class="badge badge-<?php echo $fine['status'] == 'unpaid' ? 'danger' : ($fine['status'] == 'paid' ? 'active' : 'warning'); ?>">
                                    <?php echo ucfirst($fine['status']); ?>
                                </span>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-check-circle"></i>
                        <p>No fines recorded</p>
                    </div>
                <?php endif; ?>
            </div>

            <div class="card">
                <h3><i class="fas fa-chart-line"></i> Borrowing Trends (Last 6 Months)</h3>
                <?php if (!empty($monthly_trends)): ?>
                    <?php foreach ($monthly_trends as $trend): ?>
                        <div class="trend-item">
                            <span style="color: #666;"><?php echo $trend['month']; ?></span>
                            <span style="font-weight: 600; color: black;"><?php echo $trend['count']; ?> books</span>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-chart-bar"></i>
                        <p>No recent borrowing activity</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Complete Borrowing History -->
        <div class="card full-width">
            <h3><i class="fas fa-list"></i> Complete Borrowing History</h3>
            <?php if ($borrowing_history->num_rows > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Book Title</th>
                            <th>Author</th>
                            <th>Category</th>
                            <th>Borrowed Date</th>
                            <th>Due Date</th>
                            <th>Return Date</th>
                            <th>Status</th>
                            <th>Days Late</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($history = $borrowing_history->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($history['title']); ?></td>
                                <td><?php echo htmlspecialchars($history['author']); ?></td>
                                <td><?php echo htmlspecialchars($history['category']); ?></td>
                                <td><?php echo date('M d, Y', strtotime($history['borrow_date'])); ?></td>
                                <td><?php echo date('M d, Y', strtotime($history['due_date'])); ?></td>
                                <td>
                                    <?php echo $history['return_date'] ? date('M d, Y', strtotime($history['return_date'])) : '-'; ?>
                                </td>
                                <td>
                                    <span class="badge badge-<?php echo $history['status'] == 'returned' ? 'active' : ($history['status'] == 'overdue' ? 'danger' : 'warning'); ?>">
                                        <?php echo ucfirst($history['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php 
                                    if ($history['days_late'] > 0) {
                                        echo '<span style="color: #e74c3c; font-weight: 600;">' . $history['days_late'] . ' days</span>';
                                    } else {
                                        echo '<span style="color: #27ae60;">On time</span>';
                                    }
                                    ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-book-reader"></i>
                    <p>No borrowing history</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>