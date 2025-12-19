<?php
require_once '../auth/check_auth.php';
require_once '../includes/functions.php';
checkRole('admin');

$message = '';
$error = '';
$import_results = null;

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['excel_file'])) {
    $file = $_FILES['excel_file'];
    
    // Validate file
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $error = "Error uploading file";
    } else {
        $allowed_extensions = ['xls', 'xlsx', 'csv'];
        $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if (!in_array($file_extension, $allowed_extensions)) {
            $error = "Invalid file type. Please upload an Excel file (.xls, .xlsx) or CSV file";
        } else {
            try {
                // For CSV files
                if ($file_extension == 'csv') {
                    $rows = [];
                    if (($handle = fopen($file['tmp_name'], "r")) !== FALSE) {
                        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                            $rows[] = $data;
                        }
                        fclose($handle);
                    }
                    $import_results = importUsers($conn, $rows);
                } else {
                    // For Excel files - using simple PHP Excel reader
                    require_once '../includes/SimpleXLSX.php';
                    
                    if ($xlsx = SimpleXLSX::parse($file['tmp_name'])) {
                        $rows = $xlsx->rows();
                        $import_results = importUsers($conn, $rows);
                    } else {
                        $error = "Error parsing Excel file: " . SimpleXLSX::parseError();
                    }
                }
                
                if ($import_results && $import_results['success'] > 0) {
                    $message = "Import completed! Success: {$import_results['success']}, Skipped: {$import_results['skipped']}, Errors: {$import_results['errors']}";
                    logActivity($conn, $_SESSION['user_id'], 'IMPORT_USERS', "Imported {$import_results['success']} user records");
                } elseif ($import_results) {
                    $error = "Import completed with issues. Success: {$import_results['success']}, Skipped: {$import_results['skipped']}, Errors: {$import_results['errors']}";
                }
                
            } catch (Exception $e) {
                $error = "Error processing file: " . $e->getMessage();
            }
        }
    }
}

function importUsers($conn, $rows) {
    $success = 0;
    $skipped = 0;
    $errors = 0;
    $error_log = [];
    
    // Skip header row
    array_shift($rows);
    
    foreach ($rows as $index => $row) {
        $row_num = $index + 2;
        
        // Skip empty rows
        if (empty($row[0])) {
            $skipped++;
            continue;
        }
        
        // Expected columns: Username, Password, Full Name, Email, Role
        $username = trim($row[0]);
        $password = trim($row[1] ?? '');
        $full_name = trim($row[2] ?? '');
        $email = trim($row[3] ?? '');
        $role = trim(strtolower($row[4] ?? 'client'));
        
        // Validation
        if (empty($username) || empty($password) || empty($full_name) || empty($email)) {
            $error_log[] = "Row $row_num: Username, Password, Full Name, and Email are required";
            $errors++;
            continue;
        }
        
        if (strlen($username) < 3) {
            $error_log[] = "Row $row_num: Username must be at least 3 characters";
            $errors++;
            continue;
        }
        
        if (strlen($password) < 6) {
            $error_log[] = "Row $row_num: Password must be at least 6 characters";
            $errors++;
            continue;
        }
        
        if (!in_array($role, ['admin', 'client'])) {
            $error_log[] = "Row $row_num: Role must be 'admin' or 'client', got '$role'";
            $errors++;
            continue;
        }
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error_log[] = "Row $row_num: Invalid email format - $email";
            $errors++;
            continue;
        }
        
        // Check if username exists
        $check_sql = "SELECT id FROM users WHERE username = ?";
        $stmt = $conn->prepare($check_sql);
        $stmt->bind_param("s", $username);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $error_log[] = "Row $row_num: Username already exists - $username";
            $skipped++;
            continue;
        }
        
        // Check if email exists
        $check_sql = "SELECT id FROM users WHERE email = ?";
        $stmt = $conn->prepare($check_sql);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $error_log[] = "Row $row_num: Email already exists - $email";
            $skipped++;
            continue;
        }
        
        // Hash password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        // Insert user
        $sql = "INSERT INTO users (username, password, full_name, email, role) VALUES (?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssss", $username, $hashed_password, $full_name, $email, $role);
        
        if ($stmt->execute()) {
            $success++;
        } else {
            $error_log[] = "Row $row_num: Database error - " . $stmt->error;
            $errors++;
        }
    }
    
    return [
        'success' => $success,
        'skipped' => $skipped,
        'errors' => $errors,
        'error_log' => $error_log
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Import Users - Library System</title>
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
            font-size: 16px;
            padding: 12px 24px;
        }
        .container {
            max-width: 1000px;
            margin: 30px auto;
            padding: 0 20px;
        }
        .card {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 20px;
        }
        .card h2 {
            margin-bottom: 20px;
            color: #333;
        }
        .alert {
            padding: 15px 20px;
            border-radius: 8px;
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
        .info-box {
            background: #e3f2fd;
            border-left: 4px solid #2196F3;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        .warning-box {
            background: #fff3cd;
            border-left: 4px solid #f39c12;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        .danger-box {
            background: #f8d7da;
            border-left: 4px solid #e74c3c;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 600;
        }
        input[type="file"] {
            width: 100%;
            padding: 12px;
            border: 2px dashed #e0e0e0;
            border-radius: 8px;
            background: #f8f9fa;
            cursor: pointer;
        }
        .instructions {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .instructions h3 {
            color: #333;
            margin-bottom: 15px;
        }
        .instructions ol {
            margin-left: 20px;
        }
        .instructions li {
            margin-bottom: 8px;
            color: #666;
        }
        .template-section {
            background: #e8f5e9;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .sample-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        .sample-table th,
        .sample-table td {
            border: 1px solid #ddd;
            padding: 10px;
            text-align: left;
            font-size: 13px;
        }
        .sample-table th {
            background: #f5f5f5;
            font-weight: 600;
        }
        .error-log {
            background: #fff5f5;
            padding: 15px;
            border-radius: 8px;
            max-height: 300px;
            overflow-y: auto;
            margin-top: 15px;
        }
        .error-log-item {
            padding: 8px;
            border-bottom: 1px solid #ffebee;
            color: #c62828;
            font-size: 13px;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin-top: 20px;
        }
        .stat-box {
            padding: 15px;
            border-radius: 8px;
            text-align: center;
        }
        .stat-box.success {
            background: #d4edda;
            color: #155724;
        }
        .stat-box.warning {
            background: #fff3cd;
            color: #856404;
        }
        .stat-box.error {
            background: #f8d7da;
            color: #721c24;
        }
        .stat-box .number {
            font-size: 32px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .stat-box .label {
            font-size: 12px;
            text-transform: uppercase;
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <h1><i class="fas fa-file-import"></i> Import Users from Excel</h1>
        <div style="display: flex; gap: 15px;">
            <a href="users.php" class="btn">← Back to Users</a>
            <a href="dashboard.php" class="btn">Dashboard</a>
        </div>
    </nav>

    <div class="container">
        <?php if ($message): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo $message; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <?php if ($import_results): ?>
            <div class="card">
                <h2>Import Results</h2>
                <div class="stats-grid">
                    <div class="stat-box success">
                        <div class="number"><?php echo $import_results['success']; ?></div>
                        <div class="label">Successfully Imported</div>
                    </div>
                    <div class="stat-box warning">
                        <div class="number"><?php echo $import_results['skipped']; ?></div>
                        <div class="label">Skipped (Duplicates)</div>
                    </div>
                    <div class="stat-box error">
                        <div class="number"><?php echo $import_results['errors']; ?></div>
                        <div class="label">Errors</div>
                    </div>
                </div>

                <?php if (!empty($import_results['error_log'])): ?>
                    <div class="error-log">
                        <strong><i class="fas fa-list"></i> Error Details:</strong>
                        <?php foreach ($import_results['error_log'] as $log): ?>
                            <div class="error-log-item"><?php echo htmlspecialchars($log); ?></div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div class="card">
            <h2>Upload Excel File</h2>
            
            <div class="danger-box">
                <strong><i class="fas fa-shield-alt"></i> Security Warning:</strong><br>
                Passwords in the Excel file will be hashed before storage. Never share this file as it contains plain-text passwords!
            </div>

            <div class="info-box">
                <strong><i class="fas fa-info-circle"></i> Supported Formats:</strong><br>
                Excel files (.xls, .xlsx) and CSV files (.csv)
            </div>

            <form method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="excel_file">Select File to Import:</label>
                    <input type="file" id="excel_file" name="excel_file" accept=".xls,.xlsx,.csv" required>
                </div>
                
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-upload"></i> Import Users
                </button>
            </form>
        </div>

        <div class="card">
            <div class="template-section">
                <h3><i class="fas fa-download"></i> Excel Template Format</h3>
                <p>Your Excel file must have the following columns in this exact order:</p>
                
                <table class="sample-table">
                    <thead>
                        <tr>
                            <th>Column A</th>
                            <th>Column B</th>
                            <th>Column C</th>
                            <th>Column D</th>
                            <th>Column E</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><strong>Username</strong></td>
                            <td><strong>Password</strong></td>
                            <td><strong>Full Name</strong></td>
                            <td><strong>Email</strong></td>
                            <td><strong>Role</strong></td>
                        </tr>
                        <tr>
                            <td>johndoe</td>
                            <td>password123</td>
                            <td>John Doe</td>
                            <td>john.doe@example.com</td>
                            <td>client</td>
                        </tr>
                        <tr>
                            <td>janesmith</td>
                            <td>securepass456</td>
                            <td>Jane Smith</td>
                            <td>jane.smith@example.com</td>
                            <td>admin</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card">
            <div class="instructions">
                <h3><i class="fas fa-clipboard-list"></i> Instructions</h3>
                <ol>
                    <li><strong>Prepare your Excel file</strong> with the columns shown above</li>
                    <li><strong>First row must be headers:</strong> Username, Password, Full Name, Email, Role</li>
                    <li><strong>All fields are required</strong> - rows with missing data will cause errors</li>
                    <li><strong>Username must be at least 3 characters</strong></li>
                    <li><strong>Password must be at least 6 characters</strong></li>
                    <li><strong>Email must be valid</strong> - invalid emails will cause errors</li>
                    <li><strong>Role must be either "admin" or "client"</strong> (case-insensitive)</li>
                    <li><strong>Username and Email must be unique</strong> - duplicates will be skipped</li>
                    <li>Passwords will be automatically hashed for security</li>
                    <li>Save your file as .xlsx, .xls, or .csv</li>
                    <li>Upload the file using the form above</li>
                </ol>
            </div>

            <div class="warning-box">
                <strong><i class="fas fa-exclamation-triangle"></i> Important Notes:</strong>
                <ul style="margin-left: 20px; margin-top: 10px;">
                    <li>The first row of your Excel file will be treated as headers and skipped</li>
                    <li>Empty rows will be automatically skipped</li>
                    <li>Duplicate usernames or emails will not be imported</li>
                    <li>All imported data will be logged in the activity logs</li>
                    <li>Passwords are case-sensitive</li>
                    <li><strong>Store the Excel file securely as it contains plain-text passwords!</strong></li>
                </ul>
            </div>

            <div class="danger-box">
                <strong><i class="fas fa-key"></i> Password Security:</strong>
                <ul style="margin-left: 20px; margin-top: 10px;">
                    <li>Passwords in the Excel file are in plain text for import purposes only</li>
                    <li>Upon import, all passwords are automatically hashed using bcrypt</li>
                    <li>The original Excel file should be deleted after import</li>
                    <li>Never email or share the Excel file containing passwords</li>
                    <li>Users should change their passwords after first login</li>
                </ul>
            </div>
        </div>
    </div>
</body>
</html>