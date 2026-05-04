<?php

class LibrarianManager
{
    private $db;
    private $tenant_id;

    public function __construct()
    {
        $this->db = db();
        $this->tenant_id = (int)($_SESSION['tenant_id'] ?? 1);
    }

    public function getDashboardStats()
    {
        if (!$this->tableExists('lib_books') || !$this->tableExists('lib_loans')) {
            return [
                'status' => 'unavailable',
                'role' => 'librarian',
                'tenant' => $this->tenant_id,
                'books' => 0,
                'active_loans' => 0,
                'overdue_loans' => 0,
            ];
        }

        $books = $this->db->fetchOne(
            "SELECT COUNT(*) AS total_books, COALESCE(SUM(copies_available), 0) AS available_copies
             FROM lib_books
             WHERE tenant_id = ?",
            [$this->tenant_id]
        ) ?: [];

        $loans = $this->db->fetchOne(
            "SELECT
                COALESCE(SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END), 0) AS active_loans,
                COALESCE(SUM(CASE WHEN status = 'overdue' THEN 1 ELSE 0 END), 0) AS overdue_loans
             FROM lib_loans",
            []
        ) ?: [];

        return [
            'status' => 'operational',
            'role' => 'librarian',
            'tenant' => $this->tenant_id,
            'books' => (int)($books['total_books'] ?? 0),
            'available_copies' => (int)($books['available_copies'] ?? 0),
            'active_loans' => (int)($loans['active_loans'] ?? 0),
            'overdue_loans' => (int)($loans['overdue_loans'] ?? 0),
        ];
    }

    public function addBook($data)
    {
        $this->requireTable('lib_books');

        $title = trim((string)($data['title'] ?? ''));
        $author = trim((string)($data['author'] ?? ''));
        $isbn = trim((string)($data['isbn'] ?? ''));
        $copies = (int)($data['copies'] ?? $data['copies_available'] ?? 1);
        $copies = max(1, $copies);

        if ($title === '') {
            throw new InvalidArgumentException('Book title is required.');
        }

        $bookId = $this->db->insert('lib_books', [
            'title' => $title,
            'author' => $author !== '' ? $author : null,
            'isbn' => $isbn !== '' ? $isbn : null,
            'copies_available' => $copies,
            'status' => $copies <= 2 ? 'low' : 'available',
            'tenant_id' => $this->tenant_id,
        ]);

        if (!$bookId) {
            throw new RuntimeException('Unable to add book.');
        }

        return ['id' => (int)$bookId];
    }

    public function issueBook($book_id, $student_id, $due_date)
    {
        $this->requireTable('lib_books');
        $this->requireTable('lib_loans');

        $bookId = (int)$book_id;
        $studentId = (int)$student_id;
        $dueDate = trim((string)$due_date);

        if ($bookId <= 0 || $studentId <= 0) {
            throw new InvalidArgumentException('A valid book and student are required.');
        }
        if ($dueDate === '') {
            throw new InvalidArgumentException('A due date is required.');
        }

        $book = $this->db->fetchOne(
            "SELECT id, copies_available
             FROM lib_books
             WHERE id = ? AND tenant_id = ?",
            [$bookId, $this->tenant_id]
        );

        if (!$book) {
            throw new RuntimeException('Book not found for this tenant.');
        }

        $availableCopies = (int)($book['copies_available'] ?? 0);
        if ($availableCopies <= 0) {
            throw new RuntimeException('No copies available.');
        }

        $this->db->beginTransaction();

        try {
            $remainingCopies = $availableCopies - 1;
            $status = $remainingCopies <= 0 ? 'out' : ($remainingCopies <= 2 ? 'low' : 'available');

            $updated = $this->db->update(
                'lib_books',
                [
                    'copies_available' => $remainingCopies,
                    'status' => $status,
                ],
                'id = ? AND tenant_id = ?',
                [$bookId, $this->tenant_id]
            );

            if (!$updated) {
                throw new RuntimeException('Unable to reserve a book copy.');
            }

            $loanId = $this->db->insert('lib_loans', [
                'book_id' => $bookId,
                'student_id' => $studentId,
                'due_date' => $dueDate,
                'status' => 'active',
            ]);

            if (!$loanId) {
                throw new RuntimeException('Unable to create loan.');
            }

            $this->db->commit();

            return ['loan_id' => (int)$loanId];
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    private function requireTable($table)
    {
        if (!$this->tableExists($table)) {
            throw new RuntimeException("Required table '{$table}' is missing.");
        }
    }

    private function tableExists($table)
    {
        return (bool)$this->db->fetchOne("SHOW TABLES LIKE ?", [$table]);
    }
}
