<?php
// modules/cashier/get_pending_count.php
session_start();
require_once '../../config/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'cashier') {
    echo json_encode(['count' => 0]);
    exit();
}

$count = $conn->query("SELECT COUNT(*) as count FROM orders WHERE order_status = 'pending'")->fetch_assoc()['count'];
header('Content-Type: application/json');
echo json_encode(['count' => intval($count)]);
$conn->close();
?>