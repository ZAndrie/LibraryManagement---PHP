<?php
require_once '../auth/check_auth.php';
require_once '../includes/functions.php';
checkRole('admin');

$message = '';
$error = '';
$import_results = null;

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['excel_file'])) {
    $file = $_FILES['excel_file'];
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $error = "Error uploading file";
    } else {
        $allowed_extensions = ['xls', 'xlsx', 'csv'];
        $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if (!in_array($file_extension, $allowed_extensions)) {
            $error = "Invalid file type. Please upload an Excel file (.xls, .xlsx) or CSV file";
        } else {
            try {
                if ($file_extension == 'csv') {
                    $rows = [];
                    if (($handle = fopen($file['tmp_name'], "r")) !== FALSE) {
                        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                            $rows[] = $data;
                        }
                        fclose($handle);
                    }
                    $import_results = importPatrons($conn, $rows);
                } else {
                    require_once '../includes/SimpleXLSX.php';
                    
                    if ($xlsx = SimpleXLSX::parse($file['tmp_name'])) {
                        $rows = $xlsx->rows();
                        $import_results = importPatrons($conn, $rows);
                    } else {
                        $error = "Error parsing Excel file: " . SimpleXLSX::parseError();
                    }
                }
                
                if ($import_results && $import_results['success'] > 0) {
                    $message = "Successfully imported {$import_results['success']} patron(s)!";
                    logActivity($conn, $_SESSION['user_id'], 'IMPORT_PATRONS', "Imported {$import_results['success']} patron records");
                } elseif ($import_results && $import_results['errors'] > 0) {
                    $error = "Import completed with {$import_results['errors']} error(s). Successfully imported: {$import_results['success']}";
                } elseif ($import_results) {
                    $error = "No records were imported. Please check your file format.";
                }
                
            } catch (Exception $e) {
                $error = "Error processing file: " . $e->getMessage();
            }
        }
    }
}

function importPatrons($conn, $rows) {
    $success = 0;
    $errors = 0;
    $error_log = [];
    
    // Skip header row
    if (count($rows) > 0) {
        array_shift($rows);
    }
    
    foreach ($rows as $index => $row) {
        $row_num = $index + 2;
        
        // Get data
        $name = isset($row[0]) ? trim((string)$row[0]) : '';
        $email = isset($row[1]) ? trim((string)$row[1]) : '';
        $phone = isset($row[2]) ? trim((string)$row[2]) : '';
        $address = isset($row[3]) ? trim((string)$row[3]) : '';
        
        // Skip empty rows
        if (empty($name) && empty($email)) {
            continue;
        }
        
        // Validation
        if (empty($name)) {
            $error_log[] = "Row $row_num: Name is required";
            $errors++;
            continue;
        }
        
        if (empty($email)) {
            $error_log[] = "Row $row_num: Email is required";
            $errors++;
            continue;
        }
        
        // Clean email
        $email = strtolower(trim($email));
        
        // Validate email format
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error_log[] = "Row $row_num: Invalid email format - $email";
            $errors++;
            continue;
        }
        
        // Insert patron directly (no duplicate check)
        $sql = "INSERT INTO patrons (name, email, phone, address) VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        
        if (!$stmt) {
            $error_log[] = "Row $row_num: Database error - " . $conn->error;
            $errors++;
            continue;
        }
        
        $stmt->bind_param("ssss", $name, $email, $phone, $address);
        
        if ($stmt->execute()) {
            $success++;
        } else {
            $error_log[] = "Row $row_num: Failed to insert - " . $stmt->error;
            $errors++;
        }
        $stmt->close();
    }
    
    return [
        'success' => $success,
        'skipped' => 0,
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
    <title>Import Patrons - Library System</title>
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
        .template-section {
            background: #e8f5e9;
            padding: 20px;
            border-radius: 8px;
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
            grid-template-columns: repeat(2, 1fr);
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
        <h1><i class="fas fa-file-import"></i> Import Patrons</h1>
        <div style="display: flex; gap: 15px;">
            <a href="patrons.php" class="btn">← Back to Patrons</a>
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
            
            <div class="info-box">
                <strong><i class="fas fa-info-circle"></i> Supported Formats:</strong> Excel (.xls, .xlsx) and CSV (.csv)
            </div>

            <form method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="excel_file">Select File:</label>
                    <input type="file" id="excel_file" name="excel_file" accept=".xls,.xlsx,.csv" required>
                </div>
                
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-upload"></i> Import Patrons
                </button>
            </form>
        </div>

        <div class="card">
            <div class="template-section">
                <h3><i class="fas fa-table"></i> Required Excel Format</h3>
                <p>First row must be headers, followed by your data:</p>
                
                <table class="sample-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Address</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>John Doe</td>
                            <td>john@example.com</td>
                            <td>09123456789</td>
                            <td>123 Main St</td>
                        </tr>
                        <tr>
                            <td>Jane Smith</td>
                            <td>jane@example.com</td>
                            <td>09987654321</td>
                            <td>456 Oak Ave</td>
                        </tr>
                    </tbody>
                </table>
                <p style="margin-top: 15px; color: #666;">
                    <strong>Required:</strong> Name and Email<br>
                    <strong>Optional:</strong> Phone and Address
                </p>
            </div>
        </div>
    </div>
</body>
</html>