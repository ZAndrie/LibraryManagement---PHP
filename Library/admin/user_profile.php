<?php
require_once '../auth/check_auth.php';
require_once '../includes/functions.php';
checkRole('admin');

// Get user ID from URL
if (!isset($_GET['id'])) {
    header("Location: user_records.php");
    exit();
}

$user_id = intval($_GET['id']);

// Get user information
$sql = "SELECT u.*, up.*, ubs.*,
        up.id as profile_id,
        ubs.id as stats_id
        FROM users u
        LEFT JOIN user_profiles up ON u.id = up.user_id
        LEFT JOIN user_borrowing_stats ubs ON u.id = ubs.user_id
        WHERE u.id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    header("Location: user_records.php");
    exit();
}

$user = $result->fetch_assoc();

// If no profile exists, create one
if (!$user['profile_id']) {
    $sql = "INSERT INTO user_profiles (user_id) VALUES (?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
}

// If no stats exist, create one
if (!$user['stats_id']) {
    $sql = "INSERT INTO user_borrowing_stats (user_id) VALUES (?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user['total_borrowed'] = 0;
    $user['currently_borrowed'] = 0;
    $user['total_returned'] = 0;
    $user['overdue_count'] = 0;
}

// Get currently borrowed books
$sql = "SELECT b.*, bk.title, bk.author, bk.isbn, bk.category,
        DATEDIFF(b.due_date, CURDATE()) as days_until_due
        FROM borrowings b
        JOIN books bk ON b.book_id = bk.id
        JOIN patrons p ON b.patron_id = p.id
        WHERE p.email = ? AND b.status = 'borrowed'
        ORDER BY b.due_date ASC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $user['email']);
$stmt->execute();
$current_borrowings = $stmt->get_result();

// Get borrowing history
$sql = "SELECT b.*, bk.title, bk.author, bk.category
        FROM borrowings b
        JOIN books bk ON b.book_id = bk.id
        JOIN patrons p ON b.patron_id = p.id
        WHERE p.email = ?
        ORDER BY b.borrow_date DESC
        LIMIT 20";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $user['email']);
$stmt->execute();
$borrowing_history = $stmt->get_result();

// Get recent activity logs
$sql = "SELECT * FROM activity_logs 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT 10";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$activity_logs = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Profile - <?php echo htmlspecialchars($user['full_name']); ?></title>
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
        .btn-danger {
            background: black;
            border: none;
        }
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
            box-shadow: 0 2px 10px rgba(0,0,0,5);
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
        .badge-admin {
            background: #667eea;
            color: white;
        }
        .badge-client {
            background: #27ae60;
            color: white;
        }
        .badge-active {
            background: #d4edda;
            color: #155724;
        }
        .badge-suspended {
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
            box-shadow: 0 2px 10px rgba(0,0,0,5);
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
        .stat-card .number.warning { color: black; }
        .stat-card .number.success { color: black; }
        .stat-card .number.danger { color: black; }
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
            box-shadow: 0 2px 10px rgba(0,0,0,5);
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
        .activity-item {
            padding: 12px;
            border-left: 3px solid #667eea;
            background: #f8f9fa;
            margin-bottom: 10px;
            border-radius: 4px;
        }
        .activity-item .time {
            color: #999;
            font-size: 12px;
        }
        .activity-item .action {
            color: #333;
            font-weight: 600;
            margin: 5px 0;
        }
        .activity-item .description {
            color: #666;
            font-size: 13px;
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
    </style>
</head>
<body>
    <nav class="navbar">
        <h1><i class="fa fa-folder"></i> User Profile</h1>
        <div style="display: flex; gap: 15px;">
            <a href="user_records.php" class="btn">← Back to Records</a>
            <a href="dashboard.php" class="btn">Dashboard</a>
            <a href="../auth/logout.php" class="btn">Logout</a>
        </div>
    </nav>

    <div class="container">
        <div class="profile-header">
            <div class="profile-avatar">
                <?php echo strtoupper(substr($user['full_name'], 0, 2)); ?>
            </div>
            <div class="profile-info">
                <h2><?php echo htmlspecialchars($user['full_name']); ?></h2>
                <div class="meta">
                    @<?php echo htmlspecialchars($user['username']); ?> • 
                    <?php echo htmlspecialchars($user['email']); ?> •
                    Member since <?php echo date('M Y', strtotime($user['created_at'])); ?>
                </div>
                <div class="profile-badges">
                    <span class="badge badge-<?php echo $user['role']; ?>">
                        <?php echo ucfirst($user['role']); ?>
                    </span>
                    <span class="badge badge-<?php echo $user['membership_status'] ?? 'active'; ?>">
                        <?php echo ucfirst($user['membership_status'] ?? 'active'); ?>
                    </span>
                    <?php if ($user['last_login']): ?>
                        <span class="badge" style="background: #e3f2fd; color: #1976d2;">
                            Last login: <?php echo date('M d, Y', strtotime($user['last_login'])); ?>
                        </span>
                    <?php endif; ?>
                </div>
            </div>
            <div>
                <a href="edit_user.php?id=<?php echo $user['id']; ?>" class="btn btn-warning">
                    <i class="fas fa-edit"></i> Edit User
                </a>
            </div>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <h3>Total Borrowed</h3>
                <div class="number primary"><?php echo $user['total_borrowed'] ?? 0; ?></div>
            </div>
            <div class="stat-card">
                <h3>Currently Borrowed</h3>
                <div class="number warning"><?php echo $user['currently_borrowed'] ?? 0; ?></div>
            </div>
            <div class="stat-card">
                <h3>Returned Books</h3>
                <div class="number success"><?php echo $user['total_returned'] ?? 0; ?></div>
            </div>
            <div class="stat-card">
                <h3>Overdue Books</h3>
                <div class="number danger"><?php echo $user['overdue_count'] ?? 0; ?></div>
            </div>
        </div>

        <div class="content-grid">
            <div class="card">
                <h3><i class="fas fa-user"></i> Personal Information</h3>
                <div class="info-row">
                    <div class="label">Full Name:</div>
                    <div class="value"><?php echo htmlspecialchars($user['full_name']); ?></div>
                </div>
                <div class="info-row">
                    <div class="label">Username:</div>
                    <div class="value">@<?php echo htmlspecialchars($user['username']); ?></div>
                </div>
                <div class="info-row">
                    <div class="label">Email:</div>
                    <div class="value"><?php echo htmlspecialchars($user['email']); ?></div>
                </div>
                <div class="info-row">
                    <div class="label">Phone:</div>
                    <div class="value"><?php echo htmlspecialchars($user['phone'] ?? 'Not provided'); ?></div>
                </div>
                <div class="info-row">
                    <div class="label">Address:</div>
                    <div class="value"><?php echo htmlspecialchars($user['address'] ?? 'Not provided'); ?></div>
                </div>
                <div class="info-row">
                    <div class="label">Date of Birth:</div>
                    <div class="value">
                        <?php echo $user['date_of_birth'] ? date('M d, Y', strtotime($user['date_of_birth'])) : 'Not provided'; ?>
                    </div>
                </div>
            </div>

            <div class="card">
                <h3><i class="fas fa-info-circle"></i> Account Information</h3>
                <div class="info-row">
                    <div class="label">User Role:</div>
                    <div class="value">
                        <span class="badge badge-<?php echo $user['role']; ?>">
                            <?php echo ucfirst($user['role']); ?>
                        </span>
                    </div>
                </div>
                <div class="info-row">
                    <div class="label">Membership Status:</div>
                    <div class="value">
                        <span class="badge badge-<?php echo $user['membership_status'] ?? 'active'; ?>">
                            <?php echo ucfirst($user['membership_status'] ?? 'active'); ?>
                        </span>
                    </div>
                </div>
                <div class="info-row">
                    <div class="label">Account Created:</div>
                    <div class="value"><?php echo date('M d, Y h:i A', strtotime($user['created_at'])); ?></div>
                </div>
                <div class="info-row">
                    <div class="label">Last Login:</div>
                    <div class="value">
                        <?php echo $user['last_login'] ? date('M d, Y h:i A', strtotime($user['last_login'])) : 'Never'; ?>
                    </div>
                </div>
                <div class="info-row">
                    <div class="label">Last Activity:</div>
                    <div class="value">
                        <?php echo $user['last_activity'] ? date('M d, Y h:i A', strtotime($user['last_activity'])) : 'N/A'; ?>
                    </div>
                </div>
                <div class="info-row">
                    <div class="label">Last Borrow:</div>
                    <div class="value">
                        <?php echo $user['last_borrow_date'] ? date('M d, Y', strtotime($user['last_borrow_date'])) : 'Never'; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="content-grid">
            <div class="card">
                <h3><i class="fas fa-book-open"></i> Currently Borrowed Books</h3>
                <?php if ($current_borrowings->num_rows > 0): ?>
                    <?php while($borrowing = $current_borrowings->fetch_assoc()): ?>
                        <?php
                        $days_until_due = $borrowing['days_until_due'];
                        $status_class = 'status-ok';
                        $status_text = $days_until_due . ' days remaining';
                        
                        if ($days_until_due < 0) {
                            $status_class = 'status-overdue';
                            $status_text = abs($days_until_due) . ' days overdue';
                        } elseif ($days_until_due <= 3) {
                            $status_class = 'status-due-soon';
                            $status_text = 'Due in ' . $days_until_due . ' days';
                        }
                        ?>
                        <div class="book-item">
                            <h4><?php echo htmlspecialchars($borrowing['title']); ?></h4>
                            <div class="author">by <?php echo htmlspecialchars($borrowing['author']); ?></div>
                            <div class="meta">
                                <span>Borrowed: <?php echo date('M d, Y', strtotime($borrowing['borrow_date'])); ?></span>
                                <span>Due: <?php echo date('M d, Y', strtotime($borrowing['due_date'])); ?></span>
                                <span class="<?php echo $status_class; ?>"><?php echo $status_text; ?></span>
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

            <div class="card">
                <h3><i class="fas fa-history"></i> Recent Activity</h3>
                <?php if ($activity_logs->num_rows > 0): ?>
                    <?php while($log = $activity_logs->fetch_assoc()): ?>
                        <div class="activity-item">
                            <div class="time"><?php echo date('M d, Y h:i A', strtotime($log['created_at'])); ?></div>
                            <div class="action"><?php echo getActionIcon($log['action']); ?> <?php echo str_replace('_', ' ', $log['action']); ?></div>
                            <?php if ($log['description']): ?>
                                <div class="description"><?php echo htmlspecialchars($log['description']); ?></div>
                            <?php endif; ?>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-history"></i>
                        <p>No activity recorded</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="card full-width">
            <h3><i class="fas fa-list"></i> Borrowing History</h3>
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
                                    <span class="badge badge-<?php echo $history['status']; ?>">
                                        <?php echo ucfirst($history['status']); ?>
                                    </span>
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
    <?php
// Get user email for borrowing data
$user_email = $user['email']; // Assuming $user array exists from your current code

// Get borrowing data
$borrowing_stats = getUserBorrowingStats($conn, $user_email);
$current_borrowings = getUserCurrentBorrowings($conn, $user_email);
$borrowing_history = getUserBorrowingHistory($conn, $user_email, 10);
?>

<!-- Add this HTML in your profile display section -->
<div class="card" style="margin-top: 20px;">
    <h3><i class="fas fa-book"></i> Borrowing Statistics</h3>
    <div class="stats-grid" style="display: grid; grid-template-columns: repeat(5, 1fr); gap: 15px; margin-top: 15px;">
        <div style="text-align: center; padding: 15px; background: #f8f9fa; border-radius: 8px;">
            <div style="font-size: 24px; font-weight: bold; color: #333;">
                <?php echo $borrowing_stats['total_borrowed']; ?>
            </div>
            <div style="font-size: 12px; color: #666; margin-top: 5px;">Total Borrowed</div>
        </div>
        <div style="text-align: center; padding: 15px; background: #fff3cd; border-radius: 8px;">
            <div style="font-size: 24px; font-weight: bold; color: #856404;">
                <?php echo $borrowing_stats['currently_borrowed']; ?>
            </div>
            <div style="font-size: 12px; color: #666; margin-top: 5px;">Currently Borrowed</div>
        </div>
        <div style="text-align: center; padding: 15px; background: #d4edda; border-radius: 8px;">
            <div style="font-size: 24px; font-weight: bold; color: #155724;">
                <?php echo $borrowing_stats['total_returned']; ?>
            </div>
            <div style="font-size: 12px; color: #666; margin-top: 5px;">Returned</div>
        </div>
        <div style="text-align: center; padding: 15px; background: #f8d7da; border-radius: 8px;">
            <div style="font-size: 24px; font-weight: bold; color: #721c24;">
                <?php echo $borrowing_stats['overdue_count']; ?>
            </div>
            <div style="font-size: 12px; color: #666; margin-top: 5px;">Overdue</div>
        </div>
        <div style="text-align: center; padding: 15px; background: #e8f5e9; border-radius: 8px;">
            <div style="font-size: 24px; font-weight: bold; color: #2e7d32;">
                ₱<?php echo number_format($borrowing_stats['total_fines'], 0); ?>
            </div>
            <div style="font-size: 12px; color: #666; margin-top: 5px;">Unpaid Fines</div>
        </div>
    </div>
</div>

<!-- Currently Borrowed Books -->
<?php if ($current_borrowings->num_rows > 0): ?>
<div class="card" style="margin-top: 20px;">
    <h3><i class="fas fa-book-open"></i> Currently Borrowed Books</h3>
    <table style="width: 100%; margin-top: 15px;">
        <thead>
            <tr>
                <th>Book</th>
                <th>Borrowed Date</th>
                <th>Due Date</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <?php while($borrow = $current_borrowings->fetch_assoc()): ?>
                <tr>
                    <td>
                        <strong><?php echo htmlspecialchars($borrow['title']); ?></strong><br>
                        <small style="color: #666;"><?php echo htmlspecialchars($borrow['author']); ?></small>
                    </td>
                    <td><?php echo date('M d, Y', strtotime($borrow['borrow_date'])); ?></td>
                    <td><?php echo date('M d, Y', strtotime($borrow['due_date'])); ?></td>
                    <td>
                        <?php if ($borrow['status_flag'] == 'overdue'): ?>
                            <span style="color: #e74c3c; font-weight: 600;">
                                <?php echo $borrow['days_overdue']; ?> days overdue
                            </span>
                        <?php elseif ($borrow['status_flag'] == 'due_soon'): ?>
                            <span style="color: #f39c12; font-weight: 600;">
                                Due in <?php echo $borrow['days_until_due']; ?> days
                            </span>
                        <?php else: ?>
                            <span style="color: #27ae60;">
                                <?php echo $borrow['days_until_due']; ?> days remaining
                            </span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<!-- Borrowing History -->
<div class="card" style="margin-top: 20px;">
    <h3><i class="fas fa-history"></i> Recent Borrowing History</h3>
    <?php if ($borrowing_history->num_rows > 0): ?>
        <table style="width: 100%; margin-top: 15px;">
            <thead>
                <tr>
                    <th>Book</th>
                    <th>Borrowed</th>
                    <th>Returned</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php while($history = $borrowing_history->fetch_assoc()): ?>
                    <tr>
                        <td>
                            <strong><?php echo htmlspecialchars($history['title']); ?></strong><br>
                            <small style="color: #666;"><?php echo htmlspecialchars($history['author']); ?></small>
                        </td>
                        <td><?php echo date('M d, Y', strtotime($history['borrow_date'])); ?></td>
                        <td><?php echo $history['return_date'] ? date('M d, Y', strtotime($history['return_date'])) : '-'; ?></td>
                        <td>
                            <span class="status-badge status-<?php echo $history['current_status']; ?>">
                                <?php echo ucfirst($history['current_status']); ?>
                            </span>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p style="text-align: center; color: #999; padding: 20px;">No borrowing history</p>
    <?php endif; ?>
</div>
</body>
</html>