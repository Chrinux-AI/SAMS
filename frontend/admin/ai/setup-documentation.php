<?php
/**
 * SAMS AI Documentation Setup Script
 * Creates necessary database tables for documentation system
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';

$db = db();

// Create ai_documents table
$documentsTable = "
CREATE TABLE IF NOT EXISTS ai_documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    formatted_content TEXT,
    content_type VARCHAR(50) DEFAULT 'markdown',
    category VARCHAR(100) DEFAULT 'general',
    tags JSON,
    author_id INT NOT NULL,
    status ENUM('draft', 'published', 'archived') DEFAULT 'draft',
    version INT DEFAULT 1,
    parent_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_author_id (author_id),
    INDEX idx_status (status),
    INDEX idx_category (category),
    INDEX idx_created_at (created_at),
    FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (parent_id) REFERENCES ai_documents(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
";

// Create ai_document_history table
$historyTable = "
CREATE TABLE IF NOT EXISTS ai_document_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    document_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    formatted_content TEXT,
    version_number INT NOT NULL,
    changes_summary TEXT,
    author_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_document_id (document_id),
    INDEX idx_version_number (version_number),
    INDEX idx_created_at (created_at),
    FOREIGN KEY (document_id) REFERENCES ai_documents(id) ON DELETE CASCADE,
    FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
";

// Create ai_document_exports table
$exportsTable = "
CREATE TABLE IF NOT EXISTS ai_document_exports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    document_id INT NOT NULL,
    export_type VARCHAR(20) NOT NULL DEFAULT 'pdf',
    file_path VARCHAR(500),
    file_size BIGINT DEFAULT 0,
    export_settings JSON,
    download_count INT DEFAULT 0,
    last_downloaded TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_document_id (document_id),
    INDEX idx_export_type (export_type),
    INDEX idx_created_at (created_at),
    FOREIGN KEY (document_id) REFERENCES ai_documents(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
";

echo "Creating AI Documentation tables...\n";

try {
    $db->createTable($documentsTable);
    echo "✓ Documents table created successfully\n";
} catch (Exception $e) {
    echo "✗ Error creating documents table: " . $e->getMessage() . "\n";
}

try {
    $db->createTable($historyTable);
    echo "✓ Document history table created successfully\n";
} catch (Exception $e) {
    echo "✗ Error creating document history table: " . $e->getMessage() . "\n";
}

try {
    $db->createTable($exportsTable);
    echo "✓ Document exports table created successfully\n";
} catch (Exception $e) {
    echo "✗ Error creating document exports table: " . $e->getMessage() . "\n";
}

// Create uploads directory
$uploadsDir = __DIR__ . '/../../uploads/documents/';
if (!is_dir($uploadsDir)) {
    if (mkdir($uploadsDir, 0755, true)) {
        echo "✓ Uploads directory created successfully\n";
    } else {
        echo "✗ Error creating uploads directory\n";
    }
} else {
    echo "✓ Uploads directory already exists\n";
}

// Check if documentation service exists
if (file_exists(__DIR__ . '/../app/services/AiDocumentationService.php')) {
    echo "✓ AI Documentation Service exists\n";
} else {
    echo "✗ AI Documentation Service not found\n";
}

// Check if documentation controller exists
if (file_exists(__DIR__ . '/../app/controllers/DocumentationController.php')) {
    echo "✓ Documentation Controller exists\n";
} else {
    echo "✗ Documentation Controller not found\n";
}

// Check if documentation interface exists
if (file_exists(__DIR__ . '/documentation.php')) {
    echo "✓ Documentation Interface exists\n";
} else {
    echo "✗ Documentation Interface not found\n";
}

// Check for PDF generation libraries
$tcpdfAvailable = class_exists('TCPDF');
$dompdfAvailable = class_exists('Dompdf\Dompdf');

if ($tcpdfAvailable) {
    echo "✓ TCPDF library available for PDF generation\n";
} else {
    echo "! TCPDF library not available (will use HTML fallback)\n";
}

if ($dompdfAvailable) {
    echo "✓ DomPDF library available for PDF generation\n";
} else {
    echo "! DomPDF library not available (will use HTML fallback)\n";
}

// Test database connection
try {
    $testQuery = $db->query("SELECT 1")->fetch(PDO::FETCH_ASSOC);
    echo "✓ Database connection working\n";
} catch (Exception $e) {
    echo "✗ Database connection error: " . $e->getMessage() . "\n";
}

// Check for sample documents
$sampleDocs = $db->query("SELECT COUNT(*) as count FROM ai_documents")->fetch(PDO::FETCH_ASSOC);
echo "✓ Current documents in database: " . $sampleDocs['count'] . "\n";

echo "\nAI Documentation setup completed!\n";
echo "Features available:\n";
echo "- AI-powered content formatting and structuring\n";
echo "- Professional PDF export with clean formatting\n";
echo "- Document version history and tracking\n";
echo "- Category-based organization\n";
echo "- Search and filtering capabilities\n";
echo "- Download and archive system\n";
echo "- Complete audit trail\n";
echo "- Multi-format content support (Markdown, Plain Text, Notes)\n";
echo "- Custom tags and metadata\n";
echo "- Export statistics and analytics\n";
echo "\nAccess the documentation interface at:\n";
echo BASE_URL . "/admin/ai/documentation.php\n";
?>
