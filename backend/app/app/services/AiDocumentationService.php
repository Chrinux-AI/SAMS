<?php
/**
 * SAMS AI Documentation Service
 * AI-powered documentation generation and management
 * Professional formatting with PDF export capabilities
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/functions.php';

class AiDocumentationService
{
    public $db;
    private $logger;

    public function __construct()
    {
        $this->db = db();
        $this->logger = new Logger('ai_documentation');
        $this->initDocumentationTables();
    }

    /**
     * Initialize documentation tables
     */
    private function initDocumentationTables()
    {
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

        $documentHistoryTable = "
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

        $documentExportsTable = "
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

        $this->db->createTable($documentsTable);
        $this->db->createTable($documentHistoryTable);
        $this->db->createTable($documentExportsTable);
    }

    /**
     * Create new document with AI formatting
     */
    public function createDocument($title, $content, $authorId, $options = [])
    {
        try {
            $contentType = $options['content_type'] ?? 'markdown';
            $category = $options['category'] ?? 'general';
            $tags = $options['tags'] ?? [];
            $parentId = $options['parent_id'] ?? null;

            // AI formatting
            $formattedContent = $this->formatContent($content, $contentType);

            // Create document
            $documentId = $this->db->insert('ai_documents', [
                'title' => $title,
                'content' => $content,
                'formatted_content' => $formattedContent,
                'content_type' => $contentType,
                'category' => $category,
                'tags' => json_encode($tags),
                'author_id' => $authorId,
                'status' => 'draft',
                'version' => 1,
                'parent_id' => $parentId
            ]);

            // Create initial history record
            $this->db->insert('ai_document_history', [
                'document_id' => $documentId,
                'title' => $title,
                'content' => $content,
                'formatted_content' => $formattedContent,
                'version_number' => 1,
                'changes_summary' => 'Initial document creation',
                'author_id' => $authorId
            ]);

            $this->logger->info('Document created', [
                'document_id' => $documentId,
                'title' => $title,
                'author_id' => $authorId
            ]);

            return [
                'success' => true,
                'document_id' => $documentId,
                'message' => 'Document created successfully'
            ];

        } catch (Exception $e) {
            $this->logger->error('Error creating document', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'message' => 'Error creating document: ' . $e->getMessage()
            ];
        }
    }

    public function update($table, $data, $where, $whereParams = [])
    {
        $set = [];
        foreach ($data as $key => $value) {
            $set[] = "{$key} = :{$key}";
        }
        $setString = implode(', ', $set);

        // Convert positional WHERE parameters to named parameters
        $whereNamed = $where;
        $namedWhereParams = [];

        if (!empty($whereParams)) {
            // Check if we have positional parameters (numeric keys)
            if (isset($whereParams[0])) {
                // Convert ? to named parameters
                $paramIndex = 0;
                $whereNamed = preg_replace_callback('/\?/', function () use (&$paramIndex) {
                    return ':where_param_' . $paramIndex++;
                }, $where);

                // Create named array from positional array
                foreach ($whereParams as $index => $value) {
                    $namedWhereParams['where_param_' . $index] = $value;
                }
            } else {
                // Already named parameters
                $namedWhereParams = $whereParams;
            }
        }

        $safeTable = $this->validateTableName($table);
        $sql = "UPDATE {$safeTable} SET {$setString} WHERE {$whereNamed}";
        $params = array_merge($data, $namedWhereParams);

        $stmt = $this->db->getConnection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount() > 0;
    }

    /**
     * Validate that a table name contains only safe characters.
     */
    private function validateTableName(string $table): string
    {
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]{0,63}$/', $table)) {
            throw new InvalidArgumentException("Invalid table name: {$table}");
        }
        return "`{$table}`";
    }

    /**
     * Update document with AI formatting
     */
    public function updateDocument($documentId, $title, $content, $authorId, $options = [])
    {
        try {
            // Get current document
            $currentDocument = $this->db->fetchOne(
                "SELECT * FROM ai_documents WHERE id = ?",
                [$documentId]
            );

            if (!$currentDocument) {
                return [
                    'success' => false,
                    'message' => 'Document not found'
                ];
            }

            $contentType = $options['content_type'] ?? $currentDocument['content_type'];
            $category = $options['category'] ?? $currentDocument['category'];
            $tags = $options['tags'] ?? json_decode($currentDocument['tags'] ?? '{}', true);
            $status = $options['status'] ?? $currentDocument['status'];
            $changesSummary = $options['changes_summary'] ?? 'Document updated';

            // AI formatting
            $formattedContent = $this->formatContent($content, $contentType);

            // Update document
            $newVersion = $currentDocument['version'] + 1;

            $this->db->update('ai_documents', [
                'title' => $title,
                'content' => $content,
                'formatted_content' => $formattedContent,
                'content_type' => $contentType,
                'category' => $category,
                'tags' => json_encode($tags),
                'status' => $status,
                'version' => $newVersion,
                'updated_at' => date('Y-m-d H:i:s')
            ], 'id = ?', [$documentId]);

            // Create history record
            $this->db->insert('ai_document_history', [
                'document_id' => $documentId,
                'title' => $title,
                'content' => $content,
                'formatted_content' => $formattedContent,
                'version_number' => $newVersion,
                'changes_summary' => $changesSummary,
                'author_id' => $authorId
            ]);

            $this->logger->info('Document updated', [
                'document_id' => $documentId,
                'version' => $newVersion,
                'author_id' => $authorId
            ]);

            return [
                'success' => true,
                'document_id' => $documentId,
                'version' => $newVersion,
                'message' => 'Document updated successfully'
            ];

        } catch (Exception $e) {
            $this->logger->error('Error updating document', [
                'document_id' => $documentId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'message' => 'Error updating document: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Format content using AI
     */
    private function formatContent($content, $contentType = 'markdown')
    {
        switch ($contentType) {
            case 'markdown':
                return $this->formatMarkdown($content);
            case 'plain':
                return $this->formatPlainText($content);
            case 'notes':
                return $this->formatNotes($content);
            default:
                return $this->formatMarkdown($content);
        }
    }

    /**
     * Format markdown content
     */
    private function formatMarkdown($content)
    {
        // AI-powered markdown formatting
        $formatted = $content;

        // Clean up content
        $formatted = $this->cleanContent($formatted);

        // Structure content
        $formatted = $this->structureContent($formatted);

        // Add proper headings
        $formatted = $this->addProperHeadings($formatted);

        // Format lists
        $formatted = $this->formatLists($formatted);

        // Add emphasis
        $formatted = $this->addEmphasis($formatted);

        // Add code blocks
        $formatted = $this->addCodeBlocks($formatted);

        // Add tables
        $formatted = $this->addTables($formatted);

        // Add metadata
        $formatted = $this->addMetadata($formatted);

        return $formatted;
    }

    /**
     * Format plain text content
     */
    private function formatPlainText($content)
    {
        // Convert plain text to structured format
        $formatted = $this->cleanContent($content);

        // Detect paragraphs
        $paragraphs = preg_split('/\n\s*\n/', $formatted);

        $structured = '';
        foreach ($paragraphs as $paragraph) {
            $paragraph = trim($paragraph);
            if (!empty($paragraph)) {
                // Check if it's a heading
                if (strlen($paragraph) < 50 && preg_match('/^[A-Z]/', $paragraph)) {
                    $structured .= "\n## " . $paragraph . "\n\n";
                } else {
                    $structured .= $paragraph . "\n\n";
                }
            }
        }

        return trim($structured);
    }

    /**
     * Format notes content
     */
    private function formatNotes($content)
    {
        // Format notes with bullet points and structure
        $formatted = $this->cleanContent($content);

        // Convert to bullet points
        $lines = explode("\n", $formatted);
        $structured = '';

        foreach ($lines as $line) {
            $line = trim($line);
            if (!empty($line)) {
                if (preg_match('/^[•\-\*]\s*/', $line)) {
                    $structured .= $line . "\n";
                } else {
                    $structured .= "• " . $line . "\n";
                }
            }
        }

        return trim($structured);
    }

    /**
     * Clean content
     */
    private function cleanContent($content)
    {
        // Remove extra whitespace
        $content = preg_replace('/\s+/', ' ', $content);

        // Remove special characters
        $content = preg_replace('/[^\w\s\.\,\!\?\-\n\r]/', '', $content);

        // Fix punctuation spacing
        $content = preg_replace('/\s*([.,!?])\s*/', '$1 ', $content);

        return trim($content);
    }

    /**
     * Structure content
     */
    private function structureContent($content)
    {
        // Add proper paragraph breaks
        $content = preg_replace('/\n{3,}/', "\n\n", $content);

        // Ensure proper spacing
        $content = preg_replace('/([a-z])([A-Z])/', '$1\n\n$2', $content);

        return $content;
    }

    /**
     * Add proper headings
     */
    private function addProperHeadings($content)
    {
        // Convert title to main heading
        $lines = explode("\n", $content);
        $structured = '';

        foreach ($lines as $index => $line) {
            $line = trim($line);

            if ($index === 0 && !empty($line)) {
                // First line as main title
                $structured .= "# " . $line . "\n\n";
            } elseif (preg_match('/^[A-Z][^a-z]*$/', $line) && strlen($line) < 50) {
                // All caps short lines as subheadings
                $structured .= "## " . $line . "\n\n";
            } else {
                $structured .= $line . "\n";
            }
        }

        return trim($structured);
    }

    /**
     * Format lists
     */
    private function formatLists($content)
    {
        // Convert numbered lists
        $content = preg_replace('/^(\d+)\.\s*(.+)$/m', '$1. $2', $content);

        // Convert bullet lists
        $content = preg_replace('/^[•\-\*]\s*(.+)$/m', '- $1', $content);

        return $content;
    }

    /**
     * Add emphasis
     */
    private function addEmphasis($content)
    {
        // Bold important terms
        $importantTerms = ['important', 'note', 'warning', 'critical', 'urgent'];

        foreach ($importantTerms as $term) {
            $content = preg_replace('/\b(' . $term . ')\b/i', '**$1**', $content);
        }

        // Italic emphasis
        $content = preg_replace('/\b(please|note that|remember)\b/i', '*$1*', $content);

        return $content;
    }

    /**
     * Add code blocks
     */
    private function addCodeBlocks($content)
    {
        // Convert inline code
        $content = preg_replace('/`([^`]+)`/', '`$1`', $content);

        // Convert code blocks
        $content = preg_replace('/```\n(.*?)\n```/s', "```\n$1\n```", $content);

        return $content;
    }

    /**
     * Add tables
     */
    private function addTables($content)
    {
        // Simple table detection
        if (preg_match_all('/^\|(.+)\|$/m', $content, $matches)) {
            $tableContent = '';
            $rows = [];

            foreach ($matches[1] as $row) {
                $cells = explode('|', trim($row));
                $cells = array_map('trim', $cells);
                $rows[] = $cells;
            }

            if (count($rows) > 1) {
                // Header row
                $tableContent .= '|' . implode(' | ', $rows[0]) . "|\n";
                $tableContent .= '|' . str_repeat('---|', count($rows[0])) . "\n";

                // Data rows
                for ($i = 1; $i < count($rows); $i++) {
                    $tableContent .= '|' . implode(' | ', $rows[$i]) . "|\n";
                }

                return str_replace($matches[0][0], $tableContent, $content);
            }
        }

        return $content;
    }

    /**
     * Add metadata
     */
    private function addMetadata($content)
    {
        $metadata = "---\n";
        $metadata .= "generated: " . date('Y-m-d H:i:s') . "\n";
        $metadata .= "author: SAMS AI Documentation Service\n";
        $metadata .= "version: 1.0\n";
        $metadata .= "---\n\n";

        return $metadata . $content;
    }

    /**
     * Export document to PDF
     */
    public function exportToPDF($documentId, $options = [])
    {
        try {
            // Get document
            $document = $this->db->fetchOne(
                "SELECT * FROM ai_documents WHERE id = ?",
                [$documentId]
            );

            if (!$document) {
                return [
                    'success' => false,
                    'message' => 'Document not found'
                ];
            }

            // Generate PDF
            $pdfContent = $this->generatePDF($document, $options);

            // Save PDF file
            $fileName = $this->sanitizeFileName($document['title']) . '.pdf';
            $filePath = $this->savePDFFile($fileName, $pdfContent);

            // Create export record
            $exportId = $this->db->insert('ai_document_exports', [
                'document_id' => $documentId,
                'export_type' => 'pdf',
                'file_path' => $filePath,
                'file_size' => strlen($pdfContent),
                'export_settings' => json_encode($options),
                'created_at' => date('Y-m-d H:i:s')
            ]);

            $this->logger->info('Document exported to PDF', [
                'document_id' => $documentId,
                'export_id' => $exportId,
                'file_path' => $filePath
            ]);

            return [
                'success' => true,
                'export_id' => $exportId,
                'file_path' => $filePath,
                'download_url' => BASE_URL . '/admin/document/download?id=' . $exportId,
                'message' => 'PDF exported successfully'
            ];

        } catch (Exception $e) {
            $this->logger->error('Error exporting to PDF', [
                'document_id' => $documentId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'message' => 'Error exporting to PDF: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Generate PDF content
     */
    private function generatePDF($document, $options = [])
    {
        // Check if TCPDF is available, otherwise use alternative method
        if (class_exists('TCPDF')) {
            require_once __DIR__ . '/../vendor/autoload.php';

            // Create new PDF
            $pdf = new \TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);

            // Set document info
            $pdf->SetCreator('SAMS AI Documentation Service');
            $pdf->SetAuthor('SAMS System');
            $pdf->SetTitle($document['title']);

            // Add page
            $pdf->AddPage();

            // Set font
            $pdf->SetFont('helvetica', '', 12);

            // Add title
            $pdf->SetFont('helvetica', 'B', 16);
            $pdf->Cell(0, 10, $document['title'], 0, 1, 'C');
            $pdf->Ln(15);

            // Add metadata
            $pdf->SetFont('helvetica', '', 10);
            $pdf->Cell(0, 8, 'Generated: ' . date('Y-m-d H:i:s'), 0, 1, 'C');
            $pdf->Cell(0, 8, 'Version: ' . $document['version'], 0, 1, 'C');
            $pdf->Ln(10);

            // Add content
            $this->addContentToPDF($pdf, $document['formatted_content']);

            // Add footer
            $pdf->SetFooterMargin(15);
            $pdf->SetAutoPageBreak(true, 10, 5);
            $pdf->SetFont('helvetica', 'I', 8);
            $pdf->Cell(0, 10, 'Page ' . $pdf->getAliasNumPage() . ' of {nb}', 0, 0, 'C');

            return $pdf->Output('', 'S');
        } else {
            // Fallback to simple HTML to PDF conversion
            return $this->generateSimplePDF($document, $options);
        }
    }

    /**
     * Generate simple PDF using HTML
     */
    private function generateSimplePDF($document, $options = [])
    {
        // Create HTML content
        $html = $this->createHTMLContent($document);

        // Use DOMPDF if available, otherwise return HTML as string
        if (class_exists('Dompdf\Dompdf')) {
            $dompdf = new \Dompdf\Dompdf();
            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();
            return $dompdf->output();
        } else {
            // Return HTML as string (will be handled by browser)
            return $html;
        }
    }

    /**
     * Create HTML content for PDF
     */
    private function createHTMLContent($document)
    {
        $content = $document['formatted_content'];

        // Convert markdown to HTML
        $html = $this->markdownToHTML($content);

        // Wrap in HTML document
        $htmlContent = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <title>{$document['title']}</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 40px; line-height: 1.6; }
                h1 { color: #333; border-bottom: 2px solid #333; padding-bottom: 10px; }
                h2 { color: #555; border-bottom: 1px solid #555; padding-bottom: 5px; }
                h3 { color: #666; }
                ul, ol { margin-left: 20px; }
                li { margin-bottom: 5px; }
                code { background: #f4f4f4; padding: 2px 4px; border-radius: 3px; }
                pre { background: #f4f4f4; padding: 10px; border-radius: 5px; overflow-x: auto; }
                table { border-collapse: collapse; width: 100%; margin: 20px 0; }
                th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                th { background-color: #f2f2f2; }
                .metadata { background: #f9f9f9; padding: 10px; border-radius: 5px; margin-bottom: 20px; }
            </style>
        </head>
        <body>
            <h1>{$document['title']}</h1>
            <div class='metadata'>
                <p><strong>Generated:</strong> " . date('Y-m-d H:i:s') . "</p>
                <p><strong>Version:</strong> {$document['version']}</p>
                <p><strong>Author:</strong> {$document['first_name']} {$document['last_name']}</p>
            </div>
            <div class='content'>
                $html
            </div>
        </body>
        </html>";

        return $htmlContent;
    }

    /**
     * Convert markdown to HTML
     */
    private function markdownToHTML($content)
    {
        $html = $content;

        // Headers
        $html = preg_replace('/^### (.+)$/m', '<h3>$1</h3>', $html);
        $html = preg_replace('/^## (.+)$/m', '<h2>$1</h2>', $html);
        $html = preg_replace('/^# (.+)$/m', '<h1>$1</h1>', $html);

        // Bold
        $html = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $html);

        // Italic
        $html = preg_replace('/\*(.+?)\*/', '<em>$1</em>', $html);

        // Code
        $html = preg_replace('/`(.+?)`/', '<code>$1</code>', $html);

        // Code blocks
        $html = preg_replace('/```(.+?)```/s', '<pre><code>$1</code></pre>', $html);

        // Lists
        $html = preg_replace('/^- (.+)$/m', '<li>$1</li>', $html);
        $html = preg_replace('/^(\d+)\. (.+)$/m', '<li>$1. $2</li>', $html);

        // Wrap lists
        $html = preg_replace('/(<li>.*<\/li>)/s', '<ul>$1</ul>', $html);

        // Paragraphs
        $html = preg_replace('/^(?!<[hul]|<li|<pre|<code)(.+)$/m', '<p>$1</p>', $html);

        return $html;
    }

    /**
     * Add content to PDF
     */
    private function addContentToPDF($pdf, $content)
    {
        $lines = explode("\n", $content);

        foreach ($lines as $line) {
            $line = trim($line);

            if (empty($line)) {
                $pdf->Ln(5);
                continue;
            }

            // Handle headings
            if (preg_match('/^#{1,6}\s+(.+)$/', $line, $matches)) {
                $level = strlen($matches[0]) - 1;
                $text = $matches[1];

                switch ($level) {
                    case 1:
                        $pdf->SetFont('helvetica', 'B', 20);
                        break;
                    case 2:
                        $pdf->SetFont('helvetica', 'B', 16);
                        break;
                    case 3:
                        $pdf->SetFont('helvetica', 'B', 14);
                        break;
                    case 4:
                        $pdf->SetFont('helvetica', 'B', 12);
                        break;
                    case 5:
                        $pdf->SetFont('helvetica', 'B', 11);
                        break;
                    case 6:
                        $pdf->SetFont('helvetica', 'B', 10);
                        break;
                }

                $pdf->Cell(0, 10, $text, 0, 1, 'L');
                $pdf->Ln(8);
                $pdf->SetFont('helvetica', '', 12);
                continue;
            }

            // Handle lists
            if (preg_match('/^[\-\*]\s+(.+)$/', $line, $matches)) {
                $pdf->Cell(10, 5, '•', 0, 0, 'L');
                $pdf->Cell(0, 5, $matches[1], 0, 1, 'L');
                $pdf->Ln(8);
                continue;
            }

            // Handle numbered lists
            if (preg_match('/^(\d+)\.\s+(.+)$/', $line, $matches)) {
                $pdf->Cell(15, 5, $matches[1] . '.', 0, 0, 'L');
                $pdf->Cell(0, 5, $matches[2], 0, 1, 'L');
                $pdf->Ln(8);
                continue;
            }

            // Handle code blocks
            if (preg_match('/^```(.*)```$/', $line, $matches)) {
                $codeContent = $matches[1];
                $pdf->SetFont('courier', '', 10);
                $pdf->Cell(0, 5, 'Code:', 0, 1, 'L');
                $pdf->Ln(5);
                $pdf->MultiCell(0, 5, $codeContent, 0, 'L');
                $pdf->SetFont('helvetica', '', 12);
                $pdf->Ln(8);
                continue;
            }

            // Regular text
            $pdf->MultiCell(0, 5, $line, 0, 'L');
            $pdf->Ln(8);
        }
    }

    /**
     * Save PDF file
     */
    private function savePDFFile($fileName, $content)
    {
        $uploadDir = __DIR__ . '/../../uploads/documents/';

        // Create directory if it doesn't exist
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $filePath = $uploadDir . $fileName;

        // Save file
        file_put_contents($filePath, $content);

        return $filePath;
    }

    /**
     * Sanitize file name
     */
    private function sanitizeFileName($fileName)
    {
        // Remove special characters
        $fileName = preg_replace('/[^a-zA-Z0-9\s\-_\.]/', '', $fileName);

        // Replace spaces with underscores
        $fileName = preg_replace('/\s+/', '_', $fileName);

        // Remove multiple underscores
        $fileName = preg_replace('/_+/', '_', $fileName);

        // Trim underscores from start and end
        $fileName = trim($fileName, '_');

        return $fileName ?: 'document';
    }

    /**
     * Get document list
     */
    public function getDocuments($options = [])
    {
        try {
            $authorId = $options['author_id'] ?? null;
            $category = $options['category'] ?? null;
            $status = $options['status'] ?? null;
            $limit = $options['limit'] ?? 50;
            $offset = $options['offset'] ?? 0;

            $where = [];
            $params = [];

            if ($authorId) {
                $where[] = "d.author_id = ?";
                $params[] = $authorId;
            }

            if ($category) {
                $where[] = "d.category = ?";
                $params[] = $category;
            }

            if ($status) {
                $where[] = "d.status = ?";
                $params[] = $status;
            }

            $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

            $documents = $this->db->fetchAll("
                SELECT d.*, u.first_name, u.last_name,
                       (SELECT COUNT(*) FROM ai_document_history h WHERE h.document_id = d.id) as version_count
                FROM ai_documents d
                JOIN users u ON d.author_id = u.id
                $whereClause
                ORDER BY d.updated_at DESC
                LIMIT ? OFFSET ?
            ", array_merge($params, [$limit, $offset]));

            return [
                'success' => true,
                'documents' => $documents,
                'total' => $this->db->count('ai_documents', $whereClause, $params)
            ];

        } catch (Exception $e) {
            $this->logger->error('Error getting documents', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'message' => 'Error getting documents: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get document details
     */
    public function getDocument($documentId)
    {
        try {
            $document = $this->db->fetchOne("
                SELECT d.*, u.first_name, u.last_name
                FROM ai_documents d
                JOIN users u ON d.author_id = u.id
                WHERE d.id = ?
            ", [$documentId]);

            if (!$document) {
                return [
                    'success' => false,
                    'message' => 'Document not found'
                ];
            }

            // Get document history
            $history = $this->db->fetchAll("
                SELECT h.*, u.first_name, u.last_name
                FROM ai_document_history h
                JOIN users u ON h.author_id = u.id
                WHERE h.document_id = ?
                ORDER BY h.version_number DESC
            ", [$documentId]);

            // Get exports
            $exports = $this->db->fetchAll("
                SELECT * FROM ai_document_exports
                WHERE document_id = ?
                ORDER BY created_at DESC
            ", [$documentId]);

            $document['history'] = $history;
            $document['exports'] = $exports;

            return [
                'success' => true,
                'document' => $document
            ];

        } catch (Exception $e) {
            $this->logger->error('Error getting document', [
                'document_id' => $documentId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'message' => 'Error getting document: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Delete document
     */
    public function deleteDocument($documentId, $authorId)
    {
        try {
            // Check if document exists and user is author
            $document = $this->db->fetchOne(
                "SELECT * FROM ai_documents WHERE id = ? AND author_id = ?",
                [$documentId, $authorId]
            );

            if (!$document) {
                return [
                    'success' => false,
                    'message' => 'Document not found or access denied'
                ];
            }

            // Delete exports
            $this->db->delete('ai_document_exports', 'document_id = ?', [$documentId]);

            // Delete history
            $this->db->delete('ai_document_history', 'document_id = ?', [$documentId]);

            // Delete document
            $this->db->delete('ai_documents', 'id = ?', [$documentId]);

            $this->logger->info('Document deleted', [
                'document_id' => $documentId,
                'author_id' => $authorId
            ]);

            return [
                'success' => true,
                'message' => 'Document deleted successfully'
            ];

        } catch (Exception $e) {
            $this->logger->error('Error deleting document', [
                'document_id' => $documentId,
                'author_id' => $authorId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'message' => 'Error deleting document: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Archive document
     */
    public function archiveDocument($documentId, $authorId)
    {
        try {
            // Check if document exists and user is author
            $document = $this->db->fetchOne(
                "SELECT * FROM ai_documents WHERE id = ? AND author_id = ?",
                [$documentId, $authorId]
            );

            if (!$document) {
                return [
                    'success' => false,
                    'message' => 'Document not found or access denied'
                ];
            }

            // Archive document
            $this->db->update('ai_documents', [
                'status' => 'archived',
                'updated_at' => date('Y-m-d H:i:s')
            ], 'id = ?', [$documentId]);

            $this->logger->info('Document archived', [
                'document_id' => $documentId,
                'author_id' => $authorId
            ]);

            return [
                'success' => true,
                'message' => 'Document archived successfully'
            ];

        } catch (Exception $e) {
            $this->logger->error('Error archiving document', [
                'document_id' => $documentId,
                'author_id' => $authorId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'message' => 'Error archiving document: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get document statistics
     */
    public function getStatistics()
    {
        try {
            $totalDocs = $this->db->count('ai_documents');
            $publishedDocs = $this->db->count('ai_documents', 'status = ?', ['published']);
            $draftDocs = $this->db->count('ai_documents', 'status = ?', ['draft']);
            $archivedDocs = $this->db->count('ai_documents', 'status = ?', ['archived']);

            $totalExports = $this->db->count('ai_document_exports');
            $pdfExports = $this->db->count('ai_document_exports', 'export_type = ?', ['pdf']);

            $totalDownloads = $this->db->fetchOne("
                SELECT SUM(download_count) as total FROM ai_document_exports
            ")['total'] ?? 0;

            // Category breakdown
            $categoryBreakdown = $this->db->fetchAll("
                SELECT category, COUNT(*) as count
                FROM ai_documents
                GROUP BY category
                ORDER BY count DESC
            ");

            // Author breakdown
            $authorBreakdown = $this->db->fetchAll("
                SELECT u.first_name, u.last_name, COUNT(*) as count
                FROM ai_documents d
                JOIN users u ON d.author_id = u.id
                GROUP BY u.id, u.first_name, u.last_name
                ORDER BY count DESC
                LIMIT 10
            ");

            return [
                'success' => true,
                'statistics' => [
                    'total_documents' => $totalDocs,
                    'published_documents' => $publishedDocs,
                    'draft_documents' => $draftDocs,
                    'archived_documents' => $archivedDocs,
                    'total_exports' => $totalExports,
                    'pdf_exports' => $pdfExports,
                    'total_downloads' => $totalDownloads,
                    'category_breakdown' => $categoryBreakdown,
                    'author_breakdown' => $authorBreakdown
                ]
            ];

        } catch (Exception $e) {
            $this->logger->error('Error getting statistics', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'message' => 'Error getting statistics: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Search documents
     */
    public function searchDocuments($query, $options = [])
    {
        try {
            $authorId = $options['author_id'] ?? null;
            $category = $options['category'] ?? null;
            $limit = $options['limit'] ?? 50;

            $where = ["(d.title LIKE ? OR d.content LIKE ?)"];
            $params = ["%$query%", "%$query%"];

            if ($authorId) {
                $where[] = "d.author_id = ?";
                $params[] = $authorId;
            }

            if ($category) {
                $where[] = "d.category = ?";
                $params[] = $category;
            }

            $documents = $this->db->fetchAll("
                SELECT d.*, u.first_name, u.last_name
                FROM ai_documents d
                JOIN users u ON d.author_id = u.id
                WHERE " . implode(' AND ', $where) . "
                ORDER BY d.updated_at DESC
                LIMIT ?
            ", array_merge($params, [$limit]));

            return [
                'success' => true,
                'documents' => $documents,
                'query' => $query
            ];

        } catch (Exception $e) {
            $this->logger->error('Error searching documents', [
                'query' => $query,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'message' => 'Error searching documents: ' . $e->getMessage()
            ];
        }
    }
}

/**
 * Simple Logger for documentation service
 */
class Logger
{
    private $logFile;

    public function __construct($name = 'ai_documentation')
    {
        $this->logFile = __DIR__ . '/../../logs/' . $name . '.log';
        $this->ensureLogDirectory();
    }

    public function info($message, $context = [])
    {
        $this->log('INFO', $message, $context);
    }

    public function error($message, $context = [])
    {
        $this->log('ERROR', $message, $context);
    }

    public function debug($message, $context = [])
    {
        $this->log('DEBUG', $message, $context);
    }

    private function log($level, $message, $context = [])
    {
        $timestamp = date('Y-m-d H:i:s');
        $contextStr = $context ? ' | ' . json_encode($context) : '';
        $logMessage = "[$timestamp] [$level] $message $contextStr\n";

        file_put_contents($this->logFile, $logMessage, FILE_APPEND | LOCK_EX);
    }

    private function ensureLogDirectory()
    {
        $logDir = dirname($this->logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
    }
}
