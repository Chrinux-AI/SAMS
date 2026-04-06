<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/functions.php';

header('Content-Type: application/json');

// Ensure correct roles
if (!is_logged_in() || (!has_role('accountant') && !has_role('owner') && !has_role('super_admin'))) {
  http_response_code(403);
  echo json_encode(['success' => false, 'error' => 'Unauthorized access. Accountant role required.']);
  exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
  http_response_code(405);
  echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
  exit;
}

try {
  // 1. Gather Database Aggregations for the Prompt

  // Revenue (Fee Payments)
  // Current Month
  $revCurrentRes = db()->fetchOne(
    "SELECT SUM(amount_paid) as total FROM fee_payments WHERE MONTH(payment_date) = MONTH(CURRENT_DATE()) AND YEAR(payment_date) = YEAR(CURRENT_DATE())"
  );
  $revenueCurrentMonth = $revCurrentRes ? ($revCurrentRes['total'] ?? 0) : 0;

  // Last Month
  $revLastRes = db()->fetchOne(
    "SELECT SUM(amount_paid) as total FROM fee_payments WHERE MONTH(payment_date) = MONTH(CURRENT_DATE() - INTERVAL 1 MONTH) AND YEAR(payment_date) = YEAR(CURRENT_DATE() - INTERVAL 1 MONTH)"
  );
  $revenueLastMonth = $revLastRes ? ($revLastRes['total'] ?? 0) : 0;

  // Expenditure (Expenses)
  // Current Month
  $expCurrentRes = db()->fetchOne(
    "SELECT SUM(amount) as total FROM expenses WHERE MONTH(expense_date) = MONTH(CURRENT_DATE()) AND YEAR(expense_date) = YEAR(CURRENT_DATE())"
  );
  $expensesCurrentMonth = $expCurrentRes ? ($expCurrentRes['total'] ?? 0) : 0;

  // Last Month
  $expLastRes = db()->fetchOne(
    "SELECT SUM(amount) as total FROM expenses WHERE MONTH(expense_date) = MONTH(CURRENT_DATE() - INTERVAL 1 MONTH) AND YEAR(expense_date) = YEAR(CURRENT_DATE() - INTERVAL 1 MONTH)"
  );
  $expensesLastMonth = $expLastRes ? ($expLastRes['total'] ?? 0) : 0;

  // Receivables (Unpaid Fee Invoices)
  $recRes = db()->fetchOne(
    "SELECT SUM(amount) as total FROM fee_invoices WHERE status = 'pending' OR status = 'partial'"
  );
  $receivables = $recRes ? ($recRes['total'] ?? 0) : 0;

  // Payables (Pending Purchase Orders)
  $payRes = db()->fetchOne(
    "SELECT SUM(total_amount) as total FROM purchase_orders WHERE status = 'pending'"
  );
  $payables = $payRes ? ($payRes['total'] ?? 0) : 0;

  // 2. Prepare the AI Prompt
  // Format numbers safely as floats.
  $revenueCurrentMonth = (float)$revenueCurrentMonth;
  $revenueLastMonth = (float)$revenueLastMonth;
  $expensesCurrentMonth = (float)$expensesCurrentMonth;
  $expensesLastMonth = (float)$expensesLastMonth;
  $receivables = (float)$receivables;
  $payables = (float)$payables;

  $prompt = "You are the 'Royal ADI Accountants AI', an expert financial assistant for our school management system.
Your job is to provide clear, professional, and factual financial insights to the school accountant based EXCLUSIVELY on the data below.
DO NOT hallucinate. DO NOT make outside assumptions. DO NOT invent external factors.
Always format currency values in Naira (Symbol: ₦). Include the symbol in your response text.

REAL-TIME FINANCIAL DATA:
-------------------------
Revenue (Current Month): ₦" . number_format($revenueCurrentMonth, 2) . "
Revenue (Last Month): ₦" . number_format($revenueLastMonth, 2) . "
Expenses (Current Month): ₦" . number_format($expensesCurrentMonth, 2) . "
Expenses (Last Month): ₦" . number_format($expensesLastMonth, 2) . "
Outstanding Fee Invoices (Receivables expected): ₦" . number_format($receivables, 2) . "
Pending Purchase Orders (Payables owed): ₦" . number_format($payables, 2) . "
-------------------------

Please output a concise, 3-4 paragraph professional summary of the financial health.
Highlight the month-over-month differences in revenue and expenses.
Identify whether the current payables vs receivables ratio is healthy or concerning.
Keep the tone encouraging but strictly professional and analytical.";

  // 3. Call the Gemini API
  $apiKey = defined('GEMINI_API_KEY') ? GEMINI_API_KEY : '';

  // Check if the API key is configured. If not, don't attempt the curl.
  if (empty($apiKey)) {
    http_response_code(500);
    echo json_encode([
      'success' => false,
      'error' => 'Gemini API Key is not configured. Please contact the system administrator to set GEMINI_API_KEY.',
      'debug_data' => [
        'revenue_current' => $revenueCurrentMonth,
        'revenue_last' => $revenueLastMonth,
        'expenses_current' => $expensesCurrentMonth,
        'expenses_last' => $expensesLastMonth,
        'receivables' => $receivables,
        'payables' => $payables
      ]
    ]);
    exit;
  }

  $apiUrl = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=' . $apiKey;

  $requestData = [
    'contents' => [
      [
        'parts' => [
          [
            'text' => $prompt
          ]
        ]
      ]
    ],
    'generationConfig' => [
      'temperature' => 0.2, // Low temperature for more factual/analytical responses
      'topK' => 40,
      'topP' => 0.95,
      'maxOutputTokens' => 1024,
    ]
  ];

  $ch = curl_init($apiUrl);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_POST, true);
  curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json'
  ]);
  curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($requestData));

  // For debugging/production safety depending on environment
  curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

  $response = curl_exec($ch);
  $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

  if (curl_errno($ch)) {
    throw new Exception("cURL Error: " . curl_error($ch));
  }

  curl_close($ch);

  $responseData = json_decode($response, true);

  if ($httpCode !== 200 || !isset($responseData['candidates'][0]['content']['parts'][0]['text'])) {
    error_log("Gemini API Error: " . print_r($responseData, true));
    throw new Exception("Failed to generate AI insights from Gemini API. HTTP Code: {$httpCode}");
  }

  $insightText = $responseData['candidates'][0]['content']['parts'][0]['text'];

  echo json_encode([
    'success' => true,
    'data' => [
      'raw_stats' => [
        'revenue_current' => $revenueCurrentMonth,
        'revenue_last' => $revenueLastMonth,
        'expenses_current' => $expensesCurrentMonth,
        'expenses_last' => $expensesLastMonth,
        'receivables' => $receivables,
        'payables' => $payables
      ],
      'insights' => trim($insightText)
    ]
  ]);
} catch (Exception $e) {
  http_response_code(500);
  echo json_encode(['success' => false, 'error' => $e->getMessage()]);
  exit;
}
