<?php
require_once '../auth/check_auth.php';
require_once '../includes/functions.php';
checkRole('admin');

// Get filter
$category = isset($_GET['category']) ? $_GET['category'] : '';
$status = isset($_GET['status']) ? $_GET['status'] : '';

// Build query
$sql = "SELECT * FROM books WHERE 1=1";
$params = array();
$types = "";

if (!empty($category)) {
    $sql .= " AND category = ?";
    $params[] = $category;
    $types .= "s";
}

if ($status == 'available') {
    $sql .= " AND available > 0";
} elseif ($status == 'unavailable') {
    $sql .= " AND available = 0";
}

$sql .= " ORDER BY title";

if (!empty($params)) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $books = $stmt->get_result();
} else {
    $books = $conn->query($sql);
}

// Get categories for filter
$categories = $conn->query("SELECT DISTINCT category FROM books WHERE category IS NOT NULL ORDER BY category");

// Get statistics
$total_books = $conn->query("SELECT COUNT(*) as total FROM books")->fetch_assoc()['total'];
$total_copies = $conn->query("SELECT SUM(quantity) as total FROM books")->fetch_assoc()['total'];
$available_copies = $conn->query("SELECT SUM(available) as total FROM books")->fetch_assoc()['total'];
$borrowed_copies = $total_copies - $available_copies;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory - Library System</title>
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
        }
        .btn:hover { background: grey; }
        .btn-primary {
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
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            text-align: center;
        }
        .stat-card h3 {
            color: #666;
            font-size: 14px;
            margin-bottom: 10px;
        }
        .stat-card .number {
            font-size: 32px;
            font-weight: bold;
            color: black;
        }
        .card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }
        .filter-section {
            display: flex;
            gap: 15px;
            margin-bottom: 25px;
            align-items: center;
        }
        select {
            padding: 10px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 6px;
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
        .availability {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .progress-bar {
            flex: 1;
            height: 8px;
            background: #eee;
            border-radius: 4px;
            overflow: hidden;
        }
        .progress-fill {
            height: 100%;
            background: #27ae60;
            transition: width 0.3s;
        }
        .progress-fill.low {
            background: #f39c12;
        }
        .progress-fill.none {
            background: #e74c3c;
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <h1><i class="fas fa-chart-bar"></i> Book Inventory</h1>
        <div style="display: flex; gap: 15px;">
            <a href="dashboard.php" class="btn">Dashboard</a>
            <a href="../auth/logout.php" class="btn">Logout</a>
        </div>
    </nav>

    <div class="container">
        <div class="stats-grid">
            <div class="stat-card">
                <h3>Total Titles</h3>
                <div class="number"><?php echo $total_books; ?></div>
            </div>
            <div class="stat-card">
                <h3>Total Copies</h3>
                <div class="number"><?php echo $total_copies; ?></div>
            </div>
            <div class="stat-card">
                <h3>Available</h3>
                <div class="number" style="color: black;"><?php echo $available_copies; ?></div>
            </div>
            <div class="stat-card">
                <h3>Borrowed</h3>
                <div class="number" style="color: black;"><?php echo $borrowed_copies; ?></div>
            </div>
        </div>

        <div class="card">
            <form method="GET" class="filter-section">
                <label style="font-weight: 600;">Filter by:</label>
                <select name="category">
                    <option value="">All Categories</option>
                    <?php while($cat = $categories->fetch_assoc()): ?>
                        <option value="<?php echo $cat['category']; ?>" <?php echo $category == $cat['category'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($cat['category']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
                
                <select name="status">
                    <option value="">All Status</option>
                    <option value="available" <?php echo $status == 'available' ? 'selected' : ''; ?>>Available</option>
                    <option value="unavailable" <?php echo $status == 'unavailable' ? 'selected' : ''; ?>>Unavailable</option>
                </select>
                
                <button type="submit" class="btn btn-primary">Apply Filter</button>
                <a href="inventory.php" class="btn btn-primary">Clear</a>
            </form>

            <table>
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Author</th>
                        <th>Category</th>
                        <th>ISBN</th>
                        <th>Total</th>
                        <th>Availability</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($books->num_rows > 0): ?>
                        <?php while($book = $books->fetch_assoc()): ?>
                            <?php
                            $percentage = ($book['available'] / $book['quantity']) * 100;
                            $bar_class = '';
                            if ($percentage == 0) $bar_class = 'none';
                            elseif ($percentage < 50) $bar_class = 'low';
                            ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($book['title']); ?></strong></td>
                                <td><?php echo htmlspecialchars($book['author']); ?></td>
                                <td><?php echo htmlspecialchars($book['category']); ?></td>
                                <td><?php echo htmlspecialchars($book['isbn']); ?></td>
                                <td><?php echo $book['quantity']; ?></td>
                                <td>
                                    <div class="availability">
                                        <span style="min-width: 80px;">
                                            <?php echo $book['available']; ?> / <?php echo $book['quantity']; ?>
                                        </span>
                                        <div class="progress-bar">
                                            <div class="progress-fill <?php echo $bar_class; ?>" 
                                                 style="width: <?php echo $percentage; ?>%"></div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" style="text-align: center; color: #999; padding: 40px;">
                                No books found
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>