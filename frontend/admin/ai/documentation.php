<?php
/**
 * SAMS AI Documentation Interface
 * Admin interface for AI-powered documentation management
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/database.php';
require_login('../login.php');
if (!has_role('admin')) {
    redirect('../login.php', 'Admin access required.', 'error');
}

// Set page variables
$page_title = 'AI Documentation Assistant';
$page_subtitle = 'Create and manage professional documents with AI assistance';
$breadcrumbs = [
    ['text' => 'Home', 'href' => '../index.php'],
    ['text' => 'Admin', 'href' => 'dashboard.php'],
    ['text' => 'AI Documentation']
];

// Start content buffer
ob_start();
?>

<!-- Documentation Header -->
<div class="row mb-6">
    <div class="col-md-8">
        <div class="card">
            <div class="card-body">
                <h1 class="mb-4">
                    <i class="fas fa-file-alt me-2"></i>
                    AI Documentation Assistant
                </h1>
                <p class="text-muted mb-4">
                    Create professional documents with AI-powered formatting, structure, and PDF export capabilities.
                </p>

                <div class="row">
                    <div class="col-md-3">
                        <div class="text-center">
                            <h3 class="text-primary">
                            <?php
                            $stats = $db->query("SELECT COUNT(*) as total FROM ai_documents")->fetch(PDO::FETCH_ASSOC);
                            echo number_format($stats['total']);
                            ?>
                        </h3>
                            <p class="text-muted">Total Documents</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-center">
                            <h3 class="text-success">
                                <?php
                                $published = $db->query("SELECT COUNT(*) as total FROM ai_documents WHERE status = 'published'")->fetch(PDO::FETCH_ASSOC);
                                echo number_format($published['total']);
                                ?>
                            </h3>
                            <p class="text-muted">Published</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-center">
                            <h3 class="text-warning">
                                <?php
                                $drafts = $db->query("SELECT COUNT(*) as total FROM ai_documents WHERE status = 'draft'")->fetch(PDO::FETCH_ASSOC);
                                echo number_format($drafts['total']);
                                ?>
                            </h3>
                            <p class="text-muted">Drafts</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-center">
                            <h3 class="text-info">
                                <?php
                                $exports = $db->query("SELECT COUNT(*) as total FROM ai_document_exports")->fetch(PDO::FETCH_ASSOC);
                                echo number_format($exports['total']);
                                ?>
                            </h3>
                            <p class="text-muted">Exports</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Quick Actions</h5>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <button class="btn btn-primary" onclick="createNewDocument()">
                        <i class="fas fa-plus me-2"></i>New Document
                    </button>
                    <button class="btn btn-success" onclick="showDocumentList()">
                        <i class="fas fa-list me-2"></i>View Documents
                    </button>
                    <button class="btn btn-info" onclick="showSearchInterface()">
                        <i class="fas fa-search me-2"></i>Search
                    </button>
                    <button class="btn btn-warning" onclick="showStatistics()">
                        <i class="fas fa-chart-bar me-2"></i>Statistics
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Document Editor -->
<div id="documentEditor" class="card mb-6" style="display: none;">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="fas fa-edit me-2"></i>
            Document Editor
        </h5>
    </div>
    <div class="card-body">
        <form id="documentForm">
            <div class="row">
                <div class="col-md-8">
                    <div class="form-group mb-3">
                        <label for="documentTitle" class="form-label">Document Title</label>
                        <input type="text" class="form-control" id="documentTitle" required>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group mb-3">
                        <label for="documentCategory" class="form-label">Category</label>
                        <select class="form-control" id="documentCategory">
                            <option value="general">General</option>
                            <option value="policy">Policy</option>
                            <option value="procedure">Procedure</option>
                            <option value="manual">Manual</option>
                            <option value="report">Report</option>
                            <option value="memo">Memo</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="form-group mb-3">
                        <label for="contentType" class="form-label">Content Type</label>
                        <select class="form-control" id="contentType">
                            <option value="markdown">Markdown</option>
                            <option value="plain">Plain Text</option>
                            <option value="notes">Notes</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group mb-3">
                        <label for="documentTags" class="form-label">Tags (comma-separated)</label>
                        <input type="text" class="form-control" id="documentTags" placeholder="tag1, tag2, tag3">
                    </div>
                </div>
            </div>

            <div class="form-group mb-3">
                <label for="documentContent" class="form-label">Content</label>
                <textarea class="form-control" id="documentContent" rows="15" required></textarea>
                <small class="text-muted">AI will automatically format and structure your content.</small>
            </div>

            <div class="form-group mb-3">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="autoFormat">
                    <label class="form-check-label" for="autoFormat">
                        Apply AI formatting and structure
                    </label>
                </div>
            </div>

            <div class="d-flex justify-content-between">
                <div>
                    <button type="button" class="btn btn-secondary" onclick="previewDocument()">
                        <i class="fas fa-eye me-2"></i>Preview
                    </button>
                    <button type="button" class="btn btn-info" onclick="formatContent()">
                        <i class="fas fa-magic me-2"></i>Format with AI
                    </button>
                </div>
                <div>
                    <button type="button" class="btn btn-light" onclick="cancelDocument()">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Save Document
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Document Preview -->
<div id="documentPreview" class="card mb-6" style="display: none;">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="fas fa-eye me-2"></i>
            Document Preview
        </h5>
    </div>
    <div class="card-body">
        <div id="previewContent"></div>
        <div class="mt-4">
            <button type="button" class="btn btn-secondary" onclick="backToEditor()">
                <i class="fas fa-arrow-left me-2"></i>Back to Editor
            </button>
            <button type="button" class="btn btn-success" onclick="saveDocument()">
                <i class="fas fa-save me-2"></i>Save Document
            </button>
        </div>
    </div>
</div>

<!-- Document List -->
<div id="documentList" class="card mb-6" style="display: none;">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="fas fa-list me-2"></i>
            Documents
        </h5>
    </div>
    <div class="card-body">
        <div class="row mb-3">
            <div class="col-md-6">
                <input type="text" class="form-control" id="searchInput" placeholder="Search documents...">
            </div>
            <div class="col-md-3">
                <select class="form-control" id="categoryFilter">
                    <option value="">All Categories</option>
                    <option value="general">General</option>
                    <option value="policy">Policy</option>
                    <option value="procedure">Procedure</option>
                    <option value="manual">Manual</option>
                    <option value="report">Report</option>
                    <option value="memo">Memo</option>
                    <option value="other">Other</option>
                </select>
            </div>
            <div class="col-md-3">
                <select class="form-control" id="statusFilter">
                    <option value="">All Status</option>
                    <option value="draft">Draft</option>
                    <option value="published">Published</option>
                    <option value="archived">Archived</option>
                </select>
            </div>
        </div>

        <div id="documentTableContainer">
            <div class="text-center">
                <div class="spinner"></div>
                <p>Loading documents...</p>
            </div>
        </div>
    </div>
</div>

<!-- Document Details Modal -->
<div class="modal fade" id="documentModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Document Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="modalContent"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" onclick="editDocument()">Edit</button>
                <button type="button" class="btn btn-success" onclick="exportDocument()">Export PDF</button>
            </div>
        </div>
    </div>
</div>

<!-- Search Interface -->
<div id="searchInterface" class="card mb-6" style="display: none;">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="fas fa-search me-2"></i>
            Search Documents
        </h5>
    </div>
    <div class="card-body">
        <div class="row mb-3">
            <div class="col-md-8">
                <input type="text" class="form-control" id="searchQuery" placeholder="Enter search terms...">
            </div>
            <div class="col-md-4">
                <button class="btn btn-primary w-100" onclick="searchDocuments()">
                    <i class="fas fa-search me-2"></i>Search
                </button>
            </div>
        </div>

        <div id="searchResults"></div>
    </div>
</div>

<!-- Statistics -->
<div id="statisticsInterface" class="card mb-6" style="display: none;">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="fas fa-chart-bar me-2"></i>
            Documentation Statistics
        </h5>
    </div>
    <div class="card-body">
        <div id="statisticsContent">
            <div class="text-center">
                <div class="spinner"></div>
                <p>Loading statistics...</p>
            </div>
        </div>
    </div>
</div>

<script>
let currentDocumentId = null;
let documents = [];

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    loadDocuments();

    // Event listeners
    document.getElementById('documentForm').addEventListener('submit', function(e) {
        e.preventDefault();
        saveDocument();
    });

    document.getElementById('searchInput').addEventListener('input', function() {
        filterDocuments();
    });

    document.getElementById('categoryFilter').addEventListener('change', function() {
        filterDocuments();
    });

    document.getElementById('statusFilter').addEventListener('change', function() {
        filterDocuments();
    });
});

// Create new document
function createNewDocument() {
    currentDocumentId = null;
    document.getElementById('documentForm').reset();
    document.getElementById('documentEditor').style.display = 'block';
    document.getElementById('documentList').style.display = 'none';
    document.getElementById('searchInterface').style.display = 'none';
    document.getElementById('statisticsInterface').style.display = 'none';
    document.getElementById('documentPreview').style.display = 'none';
}

// Show document list
function showDocumentList() {
    document.getElementById('documentEditor').style.display = 'none';
    document.getElementById('documentList').style.display = 'block';
    document.getElementById('searchInterface').style.display = 'none';
    document.getElementById('statisticsInterface').style.display = 'none';
    document.getElementById('documentPreview').style.display = 'none';
    loadDocuments();
}

// Show search interface
function showSearchInterface() {
    document.getElementById('documentEditor').style.display = 'none';
    document.getElementById('documentList').style.display = 'none';
    document.getElementById('searchInterface').style.display = 'block';
    document.getElementById('statisticsInterface').style.display = 'none';
    document.getElementById('documentPreview').style.display = 'none';
}

// Show statistics
function showStatistics() {
    document.getElementById('documentEditor').style.display = 'none';
    document.getElementById('documentList').style.display = 'none';
    document.getElementById('searchInterface').style.display = 'none';
    document.getElementById('statisticsInterface').style.display = 'block';
    document.getElementById('documentPreview').style.display = 'none';
    loadStatistics();
}

// Load documents
function loadDocuments() {
    fetch('<?php echo BASE_URL; ?>/app/controllers/DocumentationController.php?action=list')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                documents = data.documents;
                displayDocuments(documents);
            } else {
                console.error('Error loading documents:', data.message);
            }
        })
        .catch(error => console.error('Error:', error));
}

// Display documents
function displayDocuments(docs) {
    const container = document.getElementById('documentTableContainer');

    if (docs.length === 0) {
        container.innerHTML = '<p class="text-muted">No documents found.</p>';
        return;
    }

    let html = `
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Category</th>
                        <th>Status</th>
                        <th>Author</th>
                        <th>Updated</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
    `;

    docs.forEach(doc => {
        const statusBadge = getStatusBadge(doc.status);
        const updatedDate = new Date(doc.updated_at).toLocaleDateString();

        html += `
            <tr>
                <td>
                    <strong>${doc.title}</strong>
                    ${doc.tags ? `<br><small class="text-muted">${doc.tags}</small>` : ''}
                </td>
                <td>${doc.category}</td>
                <td>${statusBadge}</td>
                <td>${doc.first_name} ${doc.last_name}</td>
                <td>${updatedDate}</td>
                <td>
                    <div class="btn-group">
                        <button class="btn btn-sm btn-outline-primary" onclick="viewDocument(${doc.id})">
                            <i class="fas fa-eye"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-success" onclick="exportDocument(${doc.id})">
                            <i class="fas fa-file-pdf"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-warning" onclick="editDocument(${doc.id})">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-danger" onclick="deleteDocument(${doc.id})">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `;
    });

    html += `
                </tbody>
            </table>
        </div>
    `;

    container.innerHTML = html;
}

// Get status badge
function getStatusBadge(status) {
    const badges = {
        'draft': '<span class="badge bg-secondary">Draft</span>',
        'published': '<span class="badge bg-success">Published</span>',
        'archived': '<span class="badge bg-warning">Archived</span>'
    };
    return badges[status] || '<span class="badge bg-secondary">Unknown</span>';
}

// Save document
function saveDocument() {
    const title = document.getElementById('documentTitle').value;
    const content = document.getElementById('documentContent').value;
    const contentType = document.getElementById('contentType').value;
    const category = document.getElementById('documentCategory').value;
    const tags = document.getElementById('documentTags').value.split(',').map(tag => tag.trim()).filter(tag => tag);

    const url = currentDocumentId ?
        '<?php echo BASE_URL; ?>/app/controllers/DocumentationController.php?action=update' :
        '<?php echo BASE_URL; ?>/app/controllers/DocumentationController.php?action=create';

    const formData = new FormData();
    formData.append('title', title);
    formData.append('content', content);
    formData.append('content_type', contentType);
    formData.append('category', category);
    formData.append('tags', JSON.stringify(tags));

    if (currentDocumentId) {
        formData.append('document_id', currentDocumentId);
    }

    fetch(url, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Document saved successfully!');
            currentDocumentId = data.document_id;
            loadDocuments();
            showDocumentList();
        } else {
            alert('Error saving document: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error saving document');
    });
}

// View document
function viewDocument(id) {
    fetch(`<?php echo BASE_URL; ?>/app/controllers/DocumentationController.php?action=get&id=${id}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const doc = data.document;
                const modal = new bootstrap.Modal(document.getElementById('documentModal'));
                document.getElementById('modalContent').innerHTML = `
                    <h4>${doc.title}</h4>
                    <p><strong>Category:</strong> ${doc.category}</p>
                    <p><strong>Status:</strong> ${getStatusBadge(doc.status)}</p>
                    <p><strong>Author:</strong> ${doc.first_name} ${doc.last_name}</p>
                    <p><strong>Created:</strong> ${new Date(doc.created_at).toLocaleDateString()}</p>
                    <p><strong>Updated:</strong> ${new Date(doc.updated_at).toLocaleDateString()}</p>
                    <hr>
                    <div class="document-content">
                        ${formatContentForDisplay(doc.formatted_content)}
                    </div>
                `;
                modal.show();
            } else {
                alert('Error loading document: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error loading document');
        });
}

// Export document
function exportDocument(id) {
    const docId = id || currentDocumentId;
    if (!docId) {
        alert('No document to export');
        return;
    }

    const formData = new FormData();
    formData.append('document_id', docId);

    fetch('<?php echo BASE_URL; ?>/app/controllers/DocumentationController.php?action=export', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Open download link
            window.open(data.download_url, '_blank');
        } else {
            alert('Error exporting document: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error exporting document');
    });
}

// Edit document
function editDocument(id) {
    const docId = id || currentDocumentId;
    if (!docId) {
        alert('No document to edit');
        return;
    }

    fetch(`<?php echo BASE_URL; ?>/app/controllers/DocumentationController.php?action=get&id=${docId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const doc = data.document;
                currentDocumentId = doc.id;

                document.getElementById('documentTitle').value = doc.title;
                document.getElementById('documentContent').value = doc.content;
                document.getElementById('contentType').value = doc.content_type;
                document.getElementById('documentCategory').value = doc.category;
                document.getElementById('documentTags').value = doc.tags ? doc.tags.join(', ') : '';

                document.getElementById('documentEditor').style.display = 'block';
                document.getElementById('documentList').style.display = 'none';

                // Close modal if open
                const modal = bootstrap.Modal.getInstance(document.getElementById('documentModal'));
                if (modal) modal.hide();
            } else {
                alert('Error loading document: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error loading document');
        });
}

// Delete document
function deleteDocument(id) {
    if (!confirm('Are you sure you want to delete this document?')) {
        return;
    }

    const formData = new FormData();
    formData.append('document_id', id);

    fetch('<?php echo BASE_URL; ?>/app/controllers/DocumentationController.php?action=delete', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Document deleted successfully!');
            loadDocuments();
        } else {
            alert('Error deleting document: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error deleting document');
    });
}

// Search documents
function searchDocuments() {
    const query = document.getElementById('searchQuery').value;
    if (!query) {
        alert('Please enter search terms');
        return;
    }

    fetch(`<?php echo BASE_URL; ?>/app/controllers/DocumentationController.php?action=search&q=${encodeURIComponent(query)}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displaySearchResults(data.documents);
            } else {
                alert('Error searching documents: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error searching documents');
        });
}

// Display search results
function displaySearchResults(docs) {
    const container = document.getElementById('searchResults');

    if (docs.length === 0) {
        container.innerHTML = '<p class="text-muted">No documents found.</p>';
        return;
    }

    let html = '<h6>Search Results</h6><div class="list-group">';

    docs.forEach(doc => {
        html += `
            <div class="list-group-item">
                <h6>${doc.title}</h6>
                <p class="text-muted">${doc.category} - ${doc.status}</p>
                <button class="btn btn-sm btn-outline-primary" onclick="viewDocument(${doc.id})">View</button>
                <button class="btn btn-sm btn-outline-success" onclick="exportDocument(${doc.id})">Export</button>
            </div>
        `;
    });

    html += '</div>';
    container.innerHTML = html;
}

// Load statistics
function loadStatistics() {
    fetch('<?php echo BASE_URL; ?>/app/controllers/DocumentationController.php?action=statistics')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayStatistics(data.statistics);
            } else {
                alert('Error loading statistics: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error loading statistics');
        });
}

// Display statistics
function displayStatistics(stats) {
    const container = document.getElementById('statisticsContent');

    let html = `
        <div class="row">
            <div class="col-md-6">
                <h6>Document Statistics</h6>
                <ul class="list-group">
                    <li class="list-group-item">
                        <strong>Total Documents:</strong> ${stats.total_documents}
                    </li>
                    <li class="list-group-item">
                        <strong>Published:</strong> ${stats.published_documents}
                    </li>
                    <li class="list-group-item">
                        <strong>Drafts:</strong> ${stats.draft_documents}
                    </li>
                    <li class="list-group-item">
                        <strong>Archived:</strong> ${stats.archived_documents}
                    </li>
                </ul>
            </div>
            <div class="col-md-6">
                <h6>Export Statistics</h6>
                <ul class="list-group">
                    <li class="list-group-item">
                        <strong>Total Exports:</strong> ${stats.total_exports}
                    </li>
                    <li class="list-group-item">
                        <strong>PDF Exports:</strong> ${stats.pdf_exports}
                    </li>
                    <li class="list-group-item">
                        <strong>Total Downloads:</strong> ${stats.total_downloads}
                    </li>
                </ul>
            </div>
        </div>
    `;

    if (stats.category_breakdown && stats.category_breakdown.length > 0) {
        html += `
            <h6 class="mt-4">Category Breakdown</h6>
            <div class="row">
        `;

        stats.category_breakdown.forEach(cat => {
            html += `
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-body text-center">
                            <h5>${cat.count}</h5>
                            <p class="text-muted">${cat.category}</p>
                        </div>
                    </div>
                </div>
            `;
        });

        html += '</div>';
    }

    container.innerHTML = html;
}

// Format content for display
function formatContentForDisplay(content) {
    // Simple markdown to HTML conversion
    let html = content;

    // Headers
    html = html.replace(/^### (.+)$/gm, '<h6>$1</h6>');
    html = html.replace(/^## (.+)$/gm, '<h5>$1</h5>');
    html = html.replace(/^# (.+)$/gm, '<h4>$1</h4>');

    // Bold
    html = html.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');

    // Italic
    html = html.replace(/\*(.+?)\*/g, '<em>$1</em>');

    // Code
    html = html.replace(/`(.+?)`/g, '<code>$1</code>');

    // Line breaks
    html = html.replace(/\n/g, '<br>');

    return html;
}

// Filter documents
function filterDocuments() {
    const searchTerm = document.getElementById('searchInput').value.toLowerCase();
    const categoryFilter = document.getElementById('categoryFilter').value;
    const statusFilter = document.getElementById('statusFilter').value;

    const filtered = documents.filter(doc => {
        const matchesSearch = !searchTerm ||
            doc.title.toLowerCase().includes(searchTerm) ||
            doc.category.toLowerCase().includes(searchTerm);

        const matchesCategory = !categoryFilter || doc.category === categoryFilter;
        const matchesStatus = !statusFilter || doc.status === statusFilter;

        return matchesSearch && matchesCategory && matchesStatus;
    });

    displayDocuments(filtered);
}

// Cancel document
function cancelDocument() {
    if (confirm('Are you sure you want to cancel? Any unsaved changes will be lost.')) {
        showDocumentList();
    }
}

// Format content with AI
function formatContent() {
    const content = document.getElementById('documentContent').value;
    const contentType = document.getElementById('contentType').value;

    // Simulate AI formatting (in production, this would call the AI service)
    let formatted = content;

    // Add proper headings
    formatted = formatted.replace(/^([A-Z][^.!?]+)$/gm, '## $1');

    // Add bullet points
    formatted = formatted.replace(/^(\d+\.\s)/gm, '$1');
    formatted = formatted.replace(/^([a-z]\))/gm, '$1');

    // Add emphasis
    formatted = formatted.replace(/\b(important|note|warning|critical)\b/gi, '**$1**');

    document.getElementById('documentContent').value = formatted;
    alert('Content formatted with AI!');
}

// Preview document
function previewDocument() {
    const title = document.getElementById('documentTitle').value;
    const content = document.getElementById('documentContent').value;

    if (!title || !content) {
        alert('Please enter title and content');
        return;
    }

    const formattedContent = formatContentForDisplay(content);

    document.getElementById('previewContent').innerHTML = `
        <h3>${title}</h3>
        <hr>
        <div class="document-content">
            ${formattedContent}
        </div>
    `;

    document.getElementById('documentEditor').style.display = 'none';
    document.getElementById('documentPreview').style.display = 'block';
}

// Back to editor
function backToEditor() {
    document.getElementById('documentEditor').style.display = 'block';
    document.getElementById('documentPreview').style.display = 'none';
}
</script>

<?php
// Get content and include layout
$content = ob_get_clean();
include __DIR__ . '/../../app/views/layouts/main.php';
?>
