<?php
session_start();
require_once '../../config/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../../index.php');
    exit();
}

function redirect_with_message(string $message, string $type = 'ok'): void {
    header('Location: ../../modules/admin/purchase_orders.php?msg=' . urlencode($message) . '&type=' . urlencode($type));
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_with_message('Invalid request method.', 'error');
}

$action = $_POST['action'] ?? '';
$poId = (int)($_POST['po_id'] ?? 0);
$adminId = (int)$_SESSION['user_id'];
$reason = trim($_POST['reason'] ?? '');

if ($poId <= 0 || !in_array($action, ['approve_po', 'reject_po'], true)) {
    redirect_with_message('Invalid approval request.', 'error');
}

$check = $conn->prepare('SELECT po_id, po_number, status FROM purchase_orders WHERE po_id = ? LIMIT 1');
$check->bind_param('i', $poId);
$check->execute();
$po = $check->get_result()->fetch_assoc();
$check->close();

if (!$po) {
    redirect_with_message('Purchase order not found.', 'error');
}

if ($po['status'] !== 'pending_approval') {
    redirect_with_message('Only pending purchase orders can be approved or rejected.', 'error');
}

if ($action === 'approve_po') {
    $status = 'approved';
    $rejectionReason = null;
} else {
    $status = 'rejected';
    $rejectionReason = $reason !== '' ? $reason : 'No reason specified';
}

$update = $conn->prepare(
    'UPDATE purchase_orders
     SET status = ?, approved_by = ?, approved_at = NOW(), rejection_reason = ?
     WHERE po_id = ?'
);
$update->bind_param('sisi', $status, $adminId, $rejectionReason, $poId);
$update->execute();
$update->close();

if ($status === 'approved') {
    redirect_with_message('PO ' . $po['po_number'] . ' approved successfully.');
}

redirect_with_message('PO ' . $po['po_number'] . ' rejected.');
