<?php

class BursarManager
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
        if (!$this->tableExists('finance_invoices') || !$this->tableExists('finance_payments')) {
            return [
                'status' => 'unavailable',
                'role' => 'bursar',
                'tenant' => $this->tenant_id,
                'total_invoices' => 0,
                'unpaid_invoices' => 0,
                'total_invoiced' => 0,
                'total_paid' => 0,
            ];
        }

        $invoiceSummary = $this->db->fetchOne(
            "SELECT
                COUNT(*) AS total_invoices,
                COALESCE(SUM(CASE WHEN status = 'unpaid' THEN 1 ELSE 0 END), 0) AS unpaid_invoices,
                COALESCE(SUM(amount), 0) AS total_invoiced
             FROM finance_invoices
             WHERE tenant_id = ?",
            [$this->tenant_id]
        ) ?: [];

        $paymentSummary = $this->db->fetchOne(
            "SELECT
                COUNT(*) AS total_payments,
                COALESCE(SUM(fp.amount_paid), 0) AS total_paid
             FROM finance_payments fp
             INNER JOIN finance_invoices fi ON fi.id = fp.invoice_id
             WHERE fi.tenant_id = ?",
            [$this->tenant_id]
        ) ?: [];

        return [
            'status' => 'operational',
            'role' => 'bursar',
            'tenant' => $this->tenant_id,
            'total_invoices' => (int)($invoiceSummary['total_invoices'] ?? 0),
            'unpaid_invoices' => (int)($invoiceSummary['unpaid_invoices'] ?? 0),
            'total_invoiced' => (float)($invoiceSummary['total_invoiced'] ?? 0),
            'total_payments' => (int)($paymentSummary['total_payments'] ?? 0),
            'total_paid' => (float)($paymentSummary['total_paid'] ?? 0),
        ];
    }

    public function generateInvoice($data)
    {
        $this->requireTable('finance_invoices');

        $studentId = (int)($data['student_id'] ?? 0);
        $amount = round((float)($data['amount'] ?? 0), 2);
        $description = trim((string)($data['description'] ?? ''));
        $dueDate = trim((string)($data['due_date'] ?? ''));

        if ($studentId <= 0) {
            throw new InvalidArgumentException('A valid student is required.');
        }
        if ($amount <= 0) {
            throw new InvalidArgumentException('Invoice amount must be greater than zero.');
        }
        if ($description === '') {
            throw new InvalidArgumentException('Invoice description is required.');
        }
        if ($dueDate === '') {
            throw new InvalidArgumentException('A due date is required.');
        }

        $invoiceId = $this->db->insert('finance_invoices', [
            'student_id' => $studentId,
            'amount' => $amount,
            'description' => $description,
            'due_date' => $dueDate,
            'status' => 'unpaid',
            'tenant_id' => $this->tenant_id,
        ]);

        if (!$invoiceId) {
            throw new RuntimeException('Unable to create invoice.');
        }

        return ['invoice_id' => (int)$invoiceId];
    }

    public function recordPayment($invoice_id, $amount_paid, $method)
    {
        $this->requireTable('finance_invoices');
        $this->requireTable('finance_payments');

        $invoiceId = (int)$invoice_id;
        $amountPaid = round((float)$amount_paid, 2);
        $method = trim((string)$method);

        if ($invoiceId <= 0) {
            throw new InvalidArgumentException('A valid invoice is required.');
        }
        if ($amountPaid <= 0) {
            throw new InvalidArgumentException('Payment amount must be greater than zero.');
        }
        if ($method === '') {
            throw new InvalidArgumentException('Payment method is required.');
        }

        $invoice = $this->db->fetchOne(
            "SELECT id, amount, status
             FROM finance_invoices
             WHERE id = ? AND tenant_id = ?",
            [$invoiceId, $this->tenant_id]
        );

        if (!$invoice) {
            throw new RuntimeException('Invoice not found for this tenant.');
        }

        $transactionId = 'TX_' . strtoupper(bin2hex(random_bytes(8)));

        $this->db->beginTransaction();

        try {
            $paymentId = $this->db->insert('finance_payments', [
                'invoice_id' => $invoiceId,
                'amount_paid' => $amountPaid,
                'payment_method' => $method,
                'transaction_id' => $transactionId,
                'payment_date' => date('Y-m-d H:i:s'),
            ]);

            if (!$paymentId) {
                throw new RuntimeException('Unable to record payment.');
            }

            $paymentTotals = $this->db->fetchOne(
                "SELECT COALESCE(SUM(amount_paid), 0) AS total_paid
                 FROM finance_payments
                 WHERE invoice_id = ?",
                [$invoiceId]
            ) ?: ['total_paid' => 0];

            $totalPaid = (float)($paymentTotals['total_paid'] ?? 0);
            $invoiceAmount = (float)($invoice['amount'] ?? 0);
            $status = $totalPaid >= $invoiceAmount ? 'paid' : 'partial';

            $updated = $this->db->update(
                'finance_invoices',
                ['status' => $status],
                'id = ? AND tenant_id = ?',
                [$invoiceId, $this->tenant_id]
            );

            if (!$updated) {
                throw new RuntimeException('Unable to update invoice payment status.');
            }

            $this->db->commit();

            return [
                'payment_id' => (int)$paymentId,
                'transaction_id' => $transactionId,
                'status' => $status,
            ];
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
