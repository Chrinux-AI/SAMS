<?php
/**
 * Bulk Student Import
 * CSV upload and processing with validation
 */

session_start();
require_once '../includes/config.php';
require_once '../includes/database.php';
require_once '../includes/functions.php';
require_once '../includes/AdminWorkflow.php';

// Check admin access
if (!is_logged_in() || !in_array($_SESSION['role'], ['admin', 'super_admin'])) {
    header('Location: ../login.php');
    exit;
}

$workflow = new SAMS_AdminWorkflow();
$message = '';
$message_type = '';
$import_results = null;

// Handle CSV upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    $file = $_FILES['csv_file'];
    
    if ($file['error'] === UPLOAD_ERR_OK) {
        $uploadDir = '../uploads/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
        
        $filePath = $uploadDir . basename($file['name']);
        
        if (move_uploaded_file($file['tmp_name'], $filePath)) {
            // Process the CSV
            $import_results = $workflow->bulkImportStudents($filePath);
            
            if ($import_results['success']) {
                $message = $import_results['message'];
                $message_type = 'success';
            } else {
                $message = $import_results['error'];
                $message_type = 'error';
            }
            
            // Clean up uploaded file
            unlink($filePath);
        } else {
            $message = 'Failed to upload file';
            $message_type = 'error';
        }
    } else {
        $message = 'Please select a valid CSV file';
        $message_type = 'error';
    }
}

$page_title = 'Bulk Import Students';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?> - SAMS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', system-ui, sans-serif; background: #f1f5f9; }
        .container { max-width: 1000px; margin: 0 auto; padding: 2rem; }
        
        .page-header {
            background: white;
            border-radius: 16px;
            padding: 1.5rem 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        }
        .page-header h1 {
            font-size: 1.75rem;
            color: #1e293b;
            margin-bottom: 0.5rem;
        }
        .page-header p { color: #64748b; }
        
        .alert {
            padding: 1rem 1.25rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        .alert-success { background: #dcfce7; color: #166534; border: 1px solid #86efac; }
        .alert-error { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }
        .alert-info { background: #dbeafe; color: #1e40af; border: 1px solid #93c5fd; }
        
        .card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            padding: 2rem;
            margin-bottom: 2rem;
        }
        .card h2 {
            font-size: 1.25rem;
            color: #1e293b;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .upload-area {
            border: 2px dashed #e2e8f0;
            border-radius: 12px;
            padding: 3rem;
            text-align: center;
            transition: all 0.2s;
            cursor: pointer;
        }
        .upload-area:hover {
            border-color: #4f46e5;
            background: rgba(79, 70, 229, 0.02);
        }
        .upload-area i {
            font-size: 3rem;
            color: #4f46e5;
            margin-bottom: 1rem;
        }
        .upload-area h3 {
            color: #374151;
            margin-bottom: 0.5rem;
        }
        .upload-area p {
            color: #64748b;
            font-size: 0.9rem;
        }
        .upload-area input {
            display: none;
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            border-radius: 10px;
            font-weight: 600;
            text-decoration: none;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
        }
        .btn-primary {
            background: linear-gradient(135deg, #4f46e5, #7c3aed);
            color: white;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(79, 70, 229, 0.3);
        }
        .btn-secondary {
            background: #f1f5f9;
            color: #374151;
        }
        
        .template-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }
        .template-table th,
        .template-table td {
            padding: 0.75rem;
            text-align: left;
            border: 1px solid #e2e8f0;
            font-size: 0.875rem;
        }
        .template-table th {
            background: #f8fafc;
            font-weight: 600;
            color: #374151;
        }
        .template-table td {
            color: #475569;
        }
        .template-table code {
            background: #f1f5f9;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-family: monospace;
        }
        
        .results-summary {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        .result-box {
            background: #f8fafc;
            border-radius: 12px;
            padding: 1.5rem;
            text-align: center;
        }
        .result-box.success { background: #dcfce7; color: #166534; }
        .result-box.error { background: #fee2e2; color: #991b1b; }
        .result-box.pending { background: #fef3c7; color: #92400e; }
        .result-box h3 {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        .result-box p {
            font-size: 0.875rem;
            font-weight: 500;
        }
        
        .error-list {
            background: #fee2e2;
            border: 1px solid #fca5a5;
            border-radius: 10px;
            padding: 1rem;
            margin-top: 1rem;
        }
        .error-list h4 {
            color: #991b1b;
            margin-bottom: 0.75rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .error-list ul {
            margin: 0;
            padding-left: 1.25rem;
            color: #b91c1c;
        }
        .error-list li {
            margin-bottom: 0.375rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="page-header">
            <h1><i class="fas fa-file-upload"></i> Bulk Import Students</h1>
            <p>Import multiple students at once using a CSV file</p>
        </div>
        
        <?php if ($message): ?>
            <div class="alert alert-<?= $message_type ?>">
                <i class="fas fa-<?= $message_type === 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i>
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>
        
        <?php if ($import_results): ?>
            <div class="card">
                <h2><i class="fas fa-chart-pie"></i> Import Results</h2>
                <div class="results-summary">
                    <div class="result-box success">
                        <h3><?= $import_results['results']['created'] ?? 0 ?></h3>
                        <p>Created Successfully</p>
                    </div>
                    <div class="result-box error">
                        <h3><?= $import_results['results']['failed'] ?? 0 ?></h3>
                        <p>Failed</p>
                    </div>
                    <div class="result-box pending">
                        <h3><?= $import_results['results']['total'] ?? 0 ?></h3>
                        <p>Total Processed</p>
                    </div>
                </div>
                
                <?php if (!empty($import_results['results']['errors'])): ?>
                    <div class="error-list">
                        <h4><i class="fas fa-exclamation-triangle"></i> Errors</h4>
                        <ul>
                            <?php foreach (array_slice($import_results['results']['errors'], 0, 10) as $error): ?>
                                <li>
                                    Row <?= $error['row'] ?? 'N/A' ?>: 
                                    <?= htmlspecialchars($error['errors'][0] ?? 'Unknown error') ?>
                                </li>
                            <?php endforeach; ?>
                            <?php if (count($import_results['results']['errors']) > 10): ?>
                                <li>... and <?= count($import_results['results']['errors']) - 10 ?> more errors</li>
                            <?php endif; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                
                <div style="margin-top: 1.5rem; text-align: center;">
                    <a href="users.php?tab=students" class="btn btn-primary">
                        <i class="fas fa-users"></i> View Students
                    </a>
                </div>
            </div>
        <?php else: ?>
            <div class="card">
                <h2><i class="fas fa-cloud-upload-alt"></i> Upload CSV File</h2>
                
                <form method="POST" enctype="multipart/form-data" id="uploadForm">
                    <label class="upload-area" id="dropZone">
                        <input type="file" name="csv_file" accept=".csv" required id="fileInput">
                        <i class="fas fa-cloud-upload-alt"></i>
                        <h3>Drop your CSV file here</h3>
                        <p>or click to browse files</p>
                    </label>
                    
                    <div style="margin-top: 1.5rem; text-align: center;">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-upload"></i> Import Students
                        </button>
                    </div>
                </form>
            </div>
            
            <div class="card">
                <h2><i class="fas fa-table"></i> CSV Template</h2>
                <p>Your CSV file should have the following columns:</p>
                
                <table class="template-table">
                    <thead>
                        <tr>
                            <th>Column Name</th>
                            <th>Required</th>
                            <th>Description</th>
                            <th>Example</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><code>full_name</code></td>
                            <td><i class="fas fa-check" style="color: #10b981;"></i> Yes</td>
                            <td>Student's full name</td>
                            <td>John Smith</td>
                        </tr>
                        <tr>
                            <td><code>email</code></td>
                            <td><i class="fas fa-check" style="color: #10b981;"></i> Yes</td>
                            <td>Student's email address</td>
                            <td>john@school.com</td>
                        </tr>
                        <tr>
                            <td><code>admission_no</code></td>
                            <td><i class="fas fa-times" style="color: #ef4444;"></i> No</td>
                            <td>Admission number</td>
                            <td>ADM2024001</td>
                        </tr>
                        <tr>
                            <td><code>grade_level</code></td>
                            <td><i class="fas fa-times" style="color: #ef4444;"></i> No</td>
                            <td>Grade/Class level (1-12)</td>
                            <td>10</td>
                        </tr>
                        <tr>
                            <td><code>parent_email</code></td>
                            <td><i class="fas fa-times" style="color: #ef4444;"></i> No</td>
                            <td>Parent's email for notifications</td>
                            <td>parent@email.com</td>
                        </tr>
                    </tbody>
                </table>
                
                <div style="margin-top: 1.5rem;">
                    <a href="#" class="btn btn-secondary" onclick="downloadTemplate()">
                        <i class="fas fa-download"></i> Download Template
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <script>
        // File input handling
        const fileInput = document.getElementById('fileInput');
        const dropZone = document.getElementById('dropZone');
        
        dropZone.addEventListener('click', () => fileInput.click());
        
        dropZone.addEventListener('dragover', (e) => {
            e.preventDefault();
            dropZone.style.borderColor = '#4f46e5';
            dropZone.style.background = 'rgba(79, 70, 229, 0.02)';
        });
        
        dropZone.addEventListener('dragleave', () => {
            dropZone.style.borderColor = '#e2e8f0';
            dropZone.style.background = 'transparent';
        });
        
        dropZone.addEventListener('drop', (e) => {
            e.preventDefault();
            dropZone.style.borderColor = '#e2e8f0';
            dropZone.style.background = 'transparent';
            
            if (e.dataTransfer.files.length) {
                fileInput.files = e.dataTransfer.files;
                updateDropZone(e.dataTransfer.files[0].name);
            }
        });
        
        fileInput.addEventListener('change', () => {
            if (fileInput.files.length) {
                updateDropZone(fileInput.files[0].name);
            }
        });
        
        function updateDropZone(filename) {
            dropZone.innerHTML = `
                <i class="fas fa-file-csv" style="color: #10b981;"></i>
                <h3>${filename}</h3>
                <p>Click to change file</p>
                <input type="file" name="csv_file" accept=".csv" required id="fileInput">
            `;
            // Re-attach event listeners
            document.getElementById('fileInput').addEventListener('change', arguments.callee);
        }
        
        function downloadTemplate() {
            const csv = 'full_name,email,admission_no,grade_level,parent_email\n' +
                       'John Smith,john@school.com,ADM2024001,10,parent1@email.com\n' +
                       'Jane Doe,jane@school.com,ADM2024002,10,parent2@email.com';
            
            const blob = new Blob([csv], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'student_import_template.csv';
            a.click();
            window.URL.revokeObjectURL(url);
        }
    </script>
</body>
</html>
