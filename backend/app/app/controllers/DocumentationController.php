<?php
/**
 * SAMS Documentation Controller
 * Handles AI-powered documentation management
 * Provides endpoints for document creation, editing, and PDF export
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../app/services/AiDocumentationService.php';

class DocumentationController
{
    private $docService;
    
    public function __construct()
    {
        $this->docService = new AiDocumentationService();
        $this->requireAdminAccess();
    }
    
    /**
     * Create new document
     */
    public function createDocument()
    {
        $title = $_POST['title'] ?? '';
        $content = $_POST['content'] ?? '';
        $contentType = $_POST['content_type'] ?? 'markdown';
        $category = $_POST['category'] ?? 'general';
        $tags = $_POST['tags'] ?? [];
        $parentId = $_POST['parent_id'] ?? null;
        
        // Validate input
        if (empty($title) || empty($content)) {
            $this->sendJsonResponse([
                'success' => false,
                'message' => 'Title and content are required'
            ], 400);
            return;
        }
        
        $authorId = $_SESSION['user_id'];
        
        $result = $this->docService->createDocument($title, $content, $authorId, [
            'content_type' => $contentType,
            'category' => $category,
            'tags' => $tags,
            'parent_id' => $parentId
        ]);
        
        if ($result['success']) {
            $this->sendJsonResponse($result);
        } else {
            $this->sendJsonResponse($result, 400);
        }
    }
    
    /**
     * Update document
     */
    public function updateDocument()
    {
        $documentId = $_POST['document_id'] ?? '';
        $title = $_POST['title'] ?? '';
        $content = $_POST['content'] ?? '';
        $contentType = $_POST['content_type'] ?? 'markdown';
        $category = $_POST['category'] ?? 'general';
        $tags = $_POST['tags'] ?? [];
        $status = $_POST['status'] ?? 'draft';
        $changesSummary = $_POST['changes_summary'] ?? 'Document updated';
        
        // Validate input
        if (empty($documentId) || empty($title) || empty($content)) {
            $this->sendJsonResponse([
                'success' => false,
                'message' => 'Document ID, title, and content are required'
            ], 400);
            return;
        }
        
        $authorId = $_SESSION['user_id'];
        
        $result = $this->docService->updateDocument($documentId, $title, $content, $authorId, [
            'content_type' => $contentType,
            'category' => $category,
            'tags' => $tags,
            'status' => $status,
            'changes_summary' => $changesSummary
        ]);
        
        if ($result['success']) {
            $this->sendJsonResponse($result);
        } else {
            $this->sendJsonResponse($result, 400);
        }
    }
    
    /**
     * Get document list
     */
    public function getDocuments()
    {
        $authorId = $_GET['author_id'] ?? null;
        $category = $_GET['category'] ?? null;
        $status = $_GET['status'] ?? null;
        $limit = $_GET['limit'] ?? 50;
        $offset = $_GET['offset'] ?? 0;
        
        $result = $this->docService->getDocuments([
            'author_id' => $authorId,
            'category' => $category,
            'status' => $status,
            'limit' => $limit,
            'offset' => $offset
        ]);
        
        if ($result['success']) {
            $this->sendJsonResponse($result);
        } else {
            $this->sendJsonResponse($result, 500);
        }
    }
    
    /**
     * Get document details
     */
    public function getDocument()
    {
        $documentId = $_GET['id'] ?? '';
        
        if (empty($documentId)) {
            $this->sendJsonResponse([
                'success' => false,
                'message' => 'Document ID is required'
            ], 400);
            return;
        }
        
        $result = $this->docService->getDocument($documentId);
        
        if ($result['success']) {
            $this->sendJsonResponse($result);
        } else {
            $this->sendJsonResponse($result, 404);
        }
    }
    
    /**
     * Delete document
     */
    public function deleteDocument()
    {
        $documentId = $_POST['document_id'] ?? '';
        
        if (empty($documentId)) {
            $this->sendJsonResponse([
                'success' => false,
                'message' => 'Document ID is required'
            ], 400);
            return;
        }
        
        $authorId = $_SESSION['user_id'];
        
        $result = $this->docService->deleteDocument($documentId, $authorId);
        
        if ($result['success']) {
            $this->sendJsonResponse($result);
        } else {
            $this->sendJsonResponse($result, 400);
        }
    }
    
    /**
     * Archive document
     */
    public function archiveDocument()
    {
        $documentId = $_POST['document_id'] ?? '';
        
        if (empty($documentId)) {
            $this->sendJsonResponse([
                'success' => false,
                'message' => 'Document ID is required'
            ], 400);
            return;
        }
        
        $authorId = $_SESSION['user_id'];
        
        $result = $this->docService->archiveDocument($documentId, $authorId);
        
        if ($result['success']) {
            $this->sendJsonResponse($result);
        } else {
            $this->sendJsonResponse($result, 400);
        }
    }
    
    /**
     * Export document to PDF
     */
    public function exportDocument()
    {
        $documentId = $_POST['document_id'] ?? '';
        $exportType = $_POST['export_type'] ?? 'pdf';
        $options = $_POST['options'] ?? [];
        
        if (empty($documentId)) {
            $this->sendJsonResponse([
                'success' => false,
                'message' => 'Document ID is required'
            ], 400);
            return;
        }
        
        if ($exportType === 'pdf') {
            $result = $this->docService->exportToPDF($documentId, $options);
            
            if ($result['success']) {
                $this->sendJsonResponse($result);
            } else {
                $this->sendJsonResponse($result, 500);
            }
        } else {
            $this->sendJsonResponse([
                'success' => false,
                'message' => 'Unsupported export type'
            ], 400);
        }
    }
    
    /**
     * Download exported document
     */
    public function downloadDocument()
    {
        $exportId = $_GET['id'] ?? '';
        
        if (empty($exportId)) {
            $this->sendJsonResponse([
                'success' => false,
                'message' => 'Export ID is required'
            ], 400);
            return;
        }
        
        // Get export record
        $export = $this->docService->db->fetchOne(
            "SELECT * FROM ai_document_exports WHERE id = ?",
            [$exportId]
        );
        
        if (!$export) {
            $this->sendJsonResponse([
                'success' => false,
                'message' => 'Export not found'
            ], 404);
            return;
        }
        
        // Check if file exists
        if (!file_exists($export['file_path'])) {
            $this->sendJsonResponse([
                'success' => false,
                'message' => 'File not found'
            ], 404);
            return;
        }
        
        // Update download count
        $this->docService->db->update('ai_document_exports', [
            'download_count' => $export['download_count'] + 1,
            'last_downloaded' => date('Y-m-d H:i:s')
        ], 'id = ?', [$exportId]);
        
        // Set headers for file download
        $fileName = basename($export['file_path']);
        $fileSize = filesize($export['file_path']);
        
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $fileName . '"');
        header('Content-Length: ' . $fileSize);
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');
        header('Expires: 0');
        
        // Output file
        readfile($export['file_path']);
        exit;
    }
    
    /**
     * Search documents
     */
    public function searchDocuments()
    {
        $query = $_GET['q'] ?? '';
        $authorId = $_GET['author_id'] ?? null;
        $category = $_GET['category'] ?? null;
        $limit = $_GET['limit'] ?? 50;
        
        if (empty($query)) {
            $this->sendJsonResponse([
                'success' => false,
                'message' => 'Search query is required'
            ], 400);
            return;
        }
        
        $result = $this->docService->searchDocuments($query, [
            'author_id' => $authorId,
            'category' => $category,
            'limit' => $limit
        ]);
        
        if ($result['success']) {
            $this->sendJsonResponse($result);
        } else {
            $this->sendJsonResponse($result, 500);
        }
    }
    
    /**
     * Get document statistics
     */
    public function getStatistics()
    {
        $result = $this->docService->getStatistics();
        
        if ($result['success']) {
            $this->sendJsonResponse($result);
        } else {
            $this->sendJsonResponse($result, 500);
        }
    }
    
    /**
     * Get document categories
     */
    public function getCategories()
    {
        try {
            $categories = $this->docService->db->fetchAll("
                SELECT category, COUNT(*) as count
                FROM ai_documents
                GROUP BY category
                ORDER BY count DESC
            ");
            
            $this->sendJsonResponse([
                'success' => true,
                'categories' => $categories
            ]);
        } catch (Exception $e) {
            $this->sendJsonResponse([
                'success' => false,
                'message' => 'Error getting categories: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get document history
     */
    public function getDocumentHistory()
    {
        $documentId = $_GET['id'] ?? '';
        
        if (empty($documentId)) {
            $this->sendJsonResponse([
                'success' => false,
                'message' => 'Document ID is required'
            ], 400);
            return;
        }
        
        try {
            $history = $this->docService->db->fetchAll("
                SELECT h.*, u.first_name, u.last_name
                FROM ai_document_history h
                JOIN users u ON h.author_id = u.id
                WHERE h.document_id = ?
                ORDER BY h.version_number DESC
            ", [$documentId]);
            
            $this->sendJsonResponse([
                'success' => true,
                'history' => $history
            ]);
        } catch (Exception $e) {
            $this->sendJsonResponse([
                'success' => false,
                'message' => 'Error getting document history: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Restore document version
     */
    public function restoreDocumentVersion()
    {
        $documentId = $_POST['document_id'] ?? '';
        $versionNumber = $_POST['version_number'] ?? '';
        
        if (empty($documentId) || empty($versionNumber)) {
            $this->sendJsonResponse([
                'success' => false,
                'message' => 'Document ID and version number are required'
            ], 400);
            return;
        }
        
        try {
            // Get version to restore
            $version = $this->docService->db->fetchOne(
                "SELECT * FROM ai_document_history WHERE document_id = ? AND version_number = ?",
                [$documentId, $versionNumber]
            );
            
            if (!$version) {
                $this->sendJsonResponse([
                    'success' => false,
                    'message' => 'Version not found'
                ], 404);
                return;
            }
            
            // Update document with version data
            $this->docService->db->update('ai_documents', [
                'title' => $version['title'],
                'content' => $version['content'],
                'formatted_content' => $version['formatted_content'],
                'updated_at' => date('Y-m-d H:i:s')
            ], 'id = ?', [$documentId]);
            
            // Create new history record
            $this->docService->db->insert('ai_document_history', [
                'document_id' => $documentId,
                'title' => $version['title'],
                'content' => $version['content'],
                'formatted_content' => $version['formatted_content'],
                'version_number' => $this->docService->db->fetchOne("SELECT MAX(version_number) FROM ai_document_history WHERE document_id = ?", [$documentId])['MAX(version_number)'] + 1,
                'changes_summary' => 'Restored from version ' . $versionNumber,
                'author_id' => $_SESSION['user_id']
            ]);
            
            $this->sendJsonResponse([
                'success' => true,
                'message' => 'Document restored successfully'
            ]);
        } catch (Exception $e) {
            $this->sendJsonResponse([
                'success' => false,
                'message' => 'Error restoring version: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Require admin access
     */
    private function requireAdminAccess()
    {
        session_start();
        
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
            $this->sendJsonResponse([
                'success' => false,
                'message' => 'Admin access required'
            ], 403);
            exit;
        }
    }
    
    /**
     * Send JSON response
     */
    private function sendJsonResponse($data, $statusCode = 200)
    {
        header('Content-Type: application/json');
        http_response_code($statusCode);
        echo json_encode($data);
        exit;
    }
}

// Route handler
if (isset($_GET['action'])) {
    $controller = new DocumentationController();
    
    switch ($_GET['action']) {
        case 'create':
            $controller->createDocument();
            break;
        case 'update':
            $controller->updateDocument();
            break;
        case 'list':
            $controller->getDocuments();
            break;
        case 'get':
            $controller->getDocument();
            break;
        case 'delete':
            $controller->deleteDocument();
            break;
        case 'archive':
            $controller->archiveDocument();
            break;
        case 'export':
            $controller->exportDocument();
            break;
        case 'download':
            $controller->downloadDocument();
            break;
        case 'search':
            $controller->searchDocuments();
            break;
        case 'statistics':
            $controller->getStatistics();
            break;
        case 'categories':
            $controller->getCategories();
            break;
        case 'history':
            $controller->getDocumentHistory();
            break;
        case 'restore':
            $controller->restoreDocumentVersion();
            break;
        default:
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'Unknown action'
            ]);
            break;
    }
} else {
    // Show available endpoints
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => 'SAMS AI Documentation API',
        'endpoints' => [
            'POST /admin/document?action=create' => 'Create new document',
            'POST /admin/document?action=update' => 'Update existing document',
            'GET /admin/document?action=list' => 'Get document list',
            'GET /admin/document?action=get&id={id}' => 'Get document details',
            'POST /admin/document?action=delete' => 'Delete document',
            'POST /admin/document?action=archive' => 'Archive document',
            'POST /admin/document?action=export' => 'Export document to PDF',
            'GET /admin/document?action=download&id={id}' => 'Download exported document',
            'GET /admin/document?action=search&q={query}' => 'Search documents',
            'GET /admin/document?action=statistics' => 'Get usage statistics',
            'GET /admin/document?action=categories' => 'Get document categories',
            'GET /admin/document?action=history&id={id}' => 'Get document version history',
            'POST /admin/document?action=restore' => 'Restore document version'
        ]
    ]);
}
?>
