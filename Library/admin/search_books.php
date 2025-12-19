<?php
require_once '../auth/check_auth.php';
require_once '../includes/functions.php';
checkRole('admin');

// Get filter type
$filter_type = isset($_GET['filter_type']) ? $_GET['filter_type'] : 'quick';

// Get search parameters based on filter type
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$title = isset($_GET['title']) ? trim($_GET['title']) : '';
$author = isset($_GET['author']) ? trim($_GET['author']) : '';
$category = isset($_GET['category']) ? $_GET['category'] : '';
$isbn = isset($_GET['isbn']) ? trim($_GET['isbn']) : '';
$year_from = isset($_GET['year_from']) ? $_GET['year_from'] : '';
$year_to = isset($_GET['year_to']) ? $_GET['year_to'] : '';
$availability = isset($_GET['availability']) ? $_GET['availability'] : '';

// Build query
$sql = "SELECT * FROM books WHERE 1=1";
$params = array();
$types = "";

// Quick search
if (!empty($search)) {
    $sql .= " AND (title LIKE ? OR author LIKE ? OR isbn LIKE ? OR category LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "ssss";
}

// Title filter
if (!empty($title)) {
    $sql .= " AND title LIKE ?";
    $params[] = "%$title%";
    $types .= "s";
}

// Author filter
if (!empty($author)) {
    $sql .= " AND author LIKE ?";
    $params[] = "%$author%";
    $types .= "s";
}

// Category filter
if (!empty($category)) {
    $sql .= " AND category = ?";
    $params[] = $category;
    $types .= "s";
}

// ISBN filter
if (!empty($isbn)) {
    $sql .= " AND isbn LIKE ?";
    $params[] = "%$isbn%";
    $types .= "s";
}

// Year range filter
if (!empty($year_from)) {
    $sql .= " AND published_year >= ?";
    $params[] = $year_from;
    $types .= "i";
}
if (!empty($year_to)) {
    $sql .= " AND published_year <= ?";
    $params[] = $year_to;
    $types .= "i";
}

// Availability filter
if ($availability == 'available') {
    $sql .= " AND available > 0";
} elseif ($availability == 'unavailable') {
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

// Get all categories for filter
$categories = $conn->query("SELECT DISTINCT category FROM books WHERE category IS NOT NULL ORDER BY category");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Search Books - Library System</title>
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
        .btn:hover {
            background: grey;
        }
        .btn-warning {
            background: black;
            border: none;
        }
        .btn-warning:hover {
            background: grey;
        }
        .container {
            max-width: 1400px;
            margin: 30px auto;
            padding: 0 20px;
        }
        .search-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,5);
            margin-bottom: 30px;
        }
        
        /* Filter Tabs */
        .filter-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            border-bottom: 2px solid black;
            padding-bottom: 0;
            flex-wrap: wrap;
        }
        .filter-tab {
            padding: 12px 20px;
            background: none;
            border: none;
            color: black;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            position: relative;
            transition: all 0.3s;
            white-space: nowrap;
        }
        .filter-tab:hover {
            color: grey;
        }
        .filter-tab.active {
            color: grey;
        }
        .filter-tab.active::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            right: 0;
            height: 2px;
            background: grey;
        }
        
        /* Filter Content */
        .filter-content {
            display: none;
        }
        .filter-content.active {
            display: block;
        }
        
        .search-form {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        .search-form input,
        .search-form select {
            flex: 1;
            min-width: 200px;
            padding: 12px 15px;
            border: 2px solid black;
            border-radius: 8px;
            font-size: 14px;
        }
        .search-form input:focus,
        .search-form select:focus {
            outline: none;
            border-color: grey;
        }
        .btn-search {
            padding: 12px 24px;
            background: black;
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            white-space: nowrap;
        }
        .btn-search:hover {
            background: grey;
        }
        
        /* Advanced Filter Grid */
        .advanced-filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }
        .advanced-filter-grid input,
        .advanced-filter-grid select {
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
        }
        .filter-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            flex-wrap: wrap;
        }
        .btn-clear {
            padding: 12px 24px;
            background: black;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
        }
        .btn-clear:hover {
            background: grey;
        }
        
        /* Active Filters Display */
        .active-filters {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 20px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        .filter-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 12px;
            background: black;
            color: white;
            border-radius: 20px;
            font-size: 13px;
        }
        
        .results-info {
            margin-bottom: 20px;
            color: black;
            font-size: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }
        .export-btn {
            padding: 8px 16px;
            background: black;
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
        }
        .export-btn:hover {
            background: grey;
        }
        
        .books-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 20px;
        }
        .book-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,5);
            transition: all 0.3s;
        }
        .book-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
        }
        .book-card h3 {
            color: black;
            margin-bottom: 8px;
            font-size: 20px;
        }
        .book-card .author {
            color: #666;
            font-size: 15px;
            margin-bottom: 12px;
        }
        .book-card .info {
            display: flex;
            flex-direction: column;
            gap: 8px;
            margin-bottom: 15px;
        }
        .book-card .info-item {
            display: flex;
            justify-content: space-between;
            font-size: 14px;
        }
        .book-card .info-item .label {
            color: black;
        }
        .book-card .info-item .value {
            color: black;
            font-weight: 500;
        }
        .category-badge {
            display: inline-block;
            padding: 5px 12px;
            background: black;
            color: white;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            margin-bottom: 12px;
        }
        .availability {
            padding: 10px;
            border-radius: 8px;
            text-align: center;
            font-weight: 600;
            font-size: 14px;
            margin-bottom: 15px;
        }
        .availability.available {
            background: #d4edda;
            color: #155724;
        }
        .availability.unavailable {
            background: #f8d7da;
            color: #721c24;
        }
        .actions {
            display: flex;
            gap: 8px;
        }
        .no-results {
            text-align: center;
            padding: 60px 20px;
            color: #999;
        }
        .no-results .icon {
            font-size: 64px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <h1><i class="fas fa-search"></i> Search Books (Admin)</h1>
        <div style="display: flex; gap: 15px;">
            <a href="../home.php" class="btn"><i class="fas fa-home"></i> Home</a>
            <a href="dashboard.php" class="btn"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
            <a href="../auth/logout.php" class="btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </nav>

    <div class="container">
        <div class="search-card">
            <!-- Filter Tabs -->
            <div class="filter-tabs">
                <button class="filter-tab <?php echo empty($filter_type) || $filter_type == 'quick' ? 'active' : ''; ?>" 
                        onclick="switchTab('quick')">
                    <i class="fas fa-search"></i> Quick Search
                </button>
                <button class="filter-tab <?php echo $filter_type == 'title' ? 'active' : ''; ?>" 
                        onclick="switchTab('title')">
                    <i class="fas fa-book"></i> By Title
                </button>
                <button class="filter-tab <?php echo $filter_type == 'author' ? 'active' : ''; ?>" 
                        onclick="switchTab('author')">
                    <i class="fas fa-user"></i> By Author
                </button>
                <button class="filter-tab <?php echo $filter_type == 'isbn' ? 'active' : ''; ?>" 
                        onclick="switchTab('isbn')">
                    <i class="fas fa-barcode"></i> By ISBN
                </button>
                <button class="filter-tab <?php echo $filter_type == 'category' ? 'active' : ''; ?>" 
                        onclick="switchTab('category')">
                    <i class="fas fa-tag"></i> By Category
                </button>
                <button class="filter-tab <?php echo $filter_type == 'advanced' ? 'active' : ''; ?>" 
                        onclick="switchTab('advanced')">
                    <i class="fas fa-sliders-h"></i> Advanced
                </button>
            </div>
            
            <!-- Quick Search -->
            <div id="quick-filter" class="filter-content <?php echo empty($filter_type) || $filter_type == 'quick' ? 'active' : ''; ?>">
                <form method="GET" class="search-form">
                    <input type="text" name="search" placeholder="Search by title, author, ISBN, or category..." 
                           value="<?php echo htmlspecialchars($search); ?>">
                    <button type="submit" class="btn-search">
                        <i class="fas fa-search"></i> Search
                    </button>
                </form>
            </div>
            
            <!-- Title Search -->
            <div id="title-filter" class="filter-content <?php echo $filter_type == 'title' ? 'active' : ''; ?>">
                <form method="GET" class="search-form">
                    <input type="hidden" name="filter_type" value="title">
                    <input type="text" name="title" placeholder="Enter book title..." 
                           value="<?php echo htmlspecialchars($title); ?>">
                    <button type="submit" class="btn-search">
                        <i class="fas fa-search"></i> Search
                    </button>
                </form>
            </div>
            
            <!-- Author Search -->
            <div id="author-filter" class="filter-content <?php echo $filter_type == 'author' ? 'active' : ''; ?>">
                <form method="GET" class="search-form">
                    <input type="hidden" name="filter_type" value="author">
                    <input type="text" name="author" placeholder="Enter author name..." 
                           value="<?php echo htmlspecialchars($author); ?>">
                    <button type="submit" class="btn-search">
                        <i class="fas fa-search"></i> Search
                    </button>
                </form>
            </div>
            
            <!-- ISBN Search -->
            <div id="isbn-filter" class="filter-content <?php echo $filter_type == 'isbn' ? 'active' : ''; ?>">
                <form method="GET" class="search-form">
                    <input type="hidden" name="filter_type" value="isbn">
                    <input type="text" name="isbn" placeholder="Enter ISBN..." 
                           value="<?php echo htmlspecialchars($isbn); ?>">
                    <button type="submit" class="btn-search">
                        <i class="fas fa-search"></i> Search
                    </button>
                </form>
            </div>
            
            <!-- Category Search -->
            <div id="category-filter" class="filter-content <?php echo $filter_type == 'category' ? 'active' : ''; ?>">
                <form method="GET" class="search-form">
                    <input type="hidden" name="filter_type" value="category">
                    <select name="category">
                        <option value="">Select a category...</option>
                        <?php while($cat = $categories->fetch_assoc()): ?>
                            <option value="<?php echo htmlspecialchars($cat['category']); ?>"
                                    <?php echo $category == $cat['category'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat['category']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                    <button type="submit" class="btn-search">
                        <i class="fas fa-search"></i> Search
                    </button>
                </form>
            </div>
            
            <!-- Advanced Search -->
            <div id="advanced-filter" class="filter-content <?php echo $filter_type == 'advanced' ? 'active' : ''; ?>">
                <form method="GET">
                    <input type="hidden" name="filter_type" value="advanced">
                    <div class="advanced-filter-grid">
                        <input type="text" name="title" placeholder="Title" 
                               value="<?php echo htmlspecialchars($title); ?>">
                        <input type="text" name="author" placeholder="Author" 
                               value="<?php echo htmlspecialchars($author); ?>">
                        <select name="category">
                            <option value="">All Categories</option>
                            <?php 
                            $categories->data_seek(0);
                            while($cat = $categories->fetch_assoc()): 
                            ?>
                                <option value="<?php echo htmlspecialchars($cat['category']); ?>"
                                        <?php echo $category == $cat['category'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cat['category']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                        <input type="text" name="isbn" placeholder="ISBN" 
                               value="<?php echo htmlspecialchars($isbn); ?>">
                        <input type="number" name="year_from" placeholder="Year From" min="1000" max="9999"
                               value="<?php echo htmlspecialchars($year_from); ?>">
                        <input type="number" name="year_to" placeholder="Year To" min="1000" max="9999"
                               value="<?php echo htmlspecialchars($year_to); ?>">
                        <select name="availability">
                            <option value="">All Books</option>
                            <option value="available" <?php echo $availability == 'available' ? 'selected' : ''; ?>>
                                Available Only
                            </option>
                            <option value="unavailable" <?php echo $availability == 'unavailable' ? 'selected' : ''; ?>>
                                Unavailable Only
                            </option>
                        </select>
                    </div>
                    <div class="filter-actions">
                        <button type="button" class="btn-clear" onclick="clearAdvancedFilters()">
                            <i class="fas fa-times"></i> Clear Filters
                        </button>
                        <button type="submit" class="btn-search">
                            <i class="fas fa-search"></i> Search
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <?php if (!empty($search) || !empty($title) || !empty($author) || !empty($category) || !empty($isbn) || !empty($year_from) || !empty($year_to) || !empty($availability)): ?>
            <!-- Active Filters Display -->
            <div class="active-filters">
                <strong style="margin-right: 10px;">Active Filters:</strong>
                <?php if (!empty($search)): ?>
                    <span class="filter-badge">
                        <i class="fas fa-search"></i> Search: "<?php echo htmlspecialchars($search); ?>"
                    </span>
                <?php endif; ?>
                <?php if (!empty($title)): ?>
                    <span class="filter-badge">
                        <i class="fas fa-book"></i> Title: "<?php echo htmlspecialchars($title); ?>"
                    </span>
                <?php endif; ?>
                <?php if (!empty($author)): ?>
                    <span class="filter-badge">
                        <i class="fas fa-user"></i> Author: "<?php echo htmlspecialchars($author); ?>"
                    </span>
                <?php endif; ?>
                <?php if (!empty($category)): ?>
                    <span class="filter-badge">
                        <i class="fas fa-tag"></i> Category: <?php echo htmlspecialchars($category); ?>
                    </span>
                <?php endif; ?>
                <?php if (!empty($isbn)): ?>
                    <span class="filter-badge">
                        <i class="fas fa-barcode"></i> ISBN: <?php echo htmlspecialchars($isbn); ?>
                    </span>
                <?php endif; ?>
                <?php if (!empty($year_from) || !empty($year_to)): ?>
                    <span class="filter-badge">
                        <i class="fas fa-calendar"></i> Year: <?php echo $year_from ?: '—'; ?> to <?php echo $year_to ?: '—'; ?>
                    </span>
                <?php endif; ?>
                <?php if (!empty($availability)): ?>
                    <span class="filter-badge">
                        <i class="fas fa-check-circle"></i> <?php echo ucfirst($availability); ?> Books
                    </span>
                <?php endif; ?>
            </div>
            
            <div class="results-info">
                <div>
                    <strong><?php echo $books->num_rows; ?></strong> books found
                </div>
                <?php if ($books->num_rows > 0): ?>
                    <button class="export-btn" onclick="alert('Export feature coming soon!')">
                        <i class="fas fa-file-export"></i> Export Results
                    </button>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($books->num_rows > 0): ?>
            <div class="books-grid">
                <?php while($book = $books->fetch_assoc()): ?>
                    <div class="book-card">
                        <h3><?php echo htmlspecialchars($book['title']); ?></h3>
                        <div class="author">by <?php echo htmlspecialchars($book['author']); ?></div>
                        
                        <?php if ($book['category']): ?>
                            <span class="category-badge"><?php echo htmlspecialchars($book['category']); ?></span>
                        <?php endif; ?>
                        
                        <div class="info">
                            <?php if ($book['isbn']): ?>
                                <div class="info-item">
                                    <span class="label">ISBN:</span>
                                    <span class="value"><?php echo htmlspecialchars($book['isbn']); ?></span>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($book['published_year']): ?>
                                <div class="info-item">
                                    <span class="label">Published:</span>
                                    <span class="value"><?php echo $book['published_year']; ?></span>
                                </div>
                            <?php endif; ?>
                            
                            <div class="info-item">
                                <span class="label">Total Copies:</span>
                                <span class="value"><?php echo $book['quantity']; ?></span>
                            </div>
                        </div>
                        
                        <div class="availability <?php echo $book['available'] > 0 ? 'available' : 'unavailable'; ?>">
                            <?php if ($book['available'] > 0): ?>
                                ✓ <?php echo $book['available']; ?> copies available
                            <?php else: ?>
                                ✗ Currently unavailable
                            <?php endif; ?>
                        </div>

                        <div class="actions">
                            <a href="edit_book.php?id=<?php echo $book['id']; ?>" class="btn btn-warning">
                                <i class="fas fa-edit"></i> Edit Book
                            </a>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="no-results">
                <div class="icon">📚</div>
                <h2>No books found</h2>
                <p>Try adjusting your search criteria or add new books to the library</p>
                <a href="add_book.php" class="btn" style="margin-top: 20px; display: inline-block; background: #667eea;">
                    <i class="fas fa-plus"></i> Add New Book
                </a>
            </div>
        <?php endif; ?>
    </div>
    
    <script>
        function switchTab(tabName) {
            // Hide all filter contents
            document.querySelectorAll('.filter-content').forEach(content => {
                content.classList.remove('active');
            });
            
            // Remove active class from all tabs
            document.querySelectorAll('.filter-tab').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Show selected filter content
            document.getElementById(tabName + '-filter').classList.add('active');
            
            // Add active class to clicked tab
            event.target.closest('.filter-tab').classList.add('active');
        }
        
        function clearAdvancedFilters() {
            const form = document.querySelector('#advanced-filter form');
            form.querySelectorAll('input[type="text"], input[type="number"]').forEach(input => {
                input.value = '';
            });
            form.querySelectorAll('select').forEach(select => {
                select.selectedIndex = 0;
            });
        }
    </script>
</body>
</html>