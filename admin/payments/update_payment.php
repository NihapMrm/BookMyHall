<?php
/**
 * update_payment.php — Admin: POST handler to update payment status
 * Logs the status change to the transactions audit table.
 * Module 5 – Nihap
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/db_connection.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/session_guard.php';

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(BASE_URL . '/admin/payments/manage_payments.php');
}

$paymentId = (int)($_POST['payment_id'] ?? 0);
$newStatus = sanitizeInput($_POST['new_status'] ?? '');
$note      = sanitizeInput($_POST['note'] ?? '');

$validStatuses = ['pending', 'paid', 'refunded', 'failed'];

if ($paymentId <= 0 || !in_array($newStatus, $validStatuses, true)) {
    setFlash('error', 'Invalid request.');
    redirect(BASE_URL . '/admin/payments/manage_payments.php');
}

// Fetch current payment
$payment = null;
try {
    $stmt = $pdo->prepare("SELECT payment_id, status FROM payments WHERE payment_id = ?");
    $stmt->execute([$paymentId]);
    $payment = $stmt->fetch();
} catch (PDOException $e) {
    error_log('update_payment fetch: ' . $e->getMessage());
}

if (!$payment) {
    setFlash('error', 'Payment not found.');
    redirect(BASE_URL . '/admin/payments/manage_payments.php');
}

// Validate transition
$allowedTransitions = [
    'pending'  => ['paid', 'failed'],
    'paid'     => ['refunded'],
    'failed'   => ['pending'],
    'refunded' => [],
];
$allowed = $allowedTransitions[$payment['status']] ?? [];

if (!in_array($newStatus, $allowed, true)) {
    setFlash('error', 'Invalid status transition from "' . $payment['status'] . '" to "' . $newStatus . '".');
    redirect(BASE_URL . '/admin/payments/payment_details.php?id=' . $paymentId);
}

// Apply update + log transaction
try {
    $pdo->beginTransaction();

    $updStmt = $pdo->prepare("UPDATE payments SET status = ?, updated_at = NOW() WHERE payment_id = ?");
    $updStmt->execute([$newStatus, $paymentId]);

    $txStmt = $pdo->prepare(
        "INSERT INTO transactions (payment_id, changed_by, old_status, new_status, note)
         VALUES (?, ?, ?, ?, ?)"
    );
    $txStmt->execute([
        $paymentId,
        $_SESSION['admin_id'],
        $payment['status'],
        $newStatus,
        $note ?: null,
    ]);

    $pdo->commit();
    setFlash('success', 'Payment status updated to "' . ucfirst($newStatus) . '" successfully.');
} catch (PDOException $e) {
    $pdo->rollBack();
    error_log('update_payment: ' . $e->getMessage());
    setFlash('error', 'Failed to update payment status. Please try again.');
}

redirect(BASE_URL . '/admin/payments/payment_details.php?id=' . $paymentId);
