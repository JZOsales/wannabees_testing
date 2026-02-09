<?php
// process_payment.php - process bill payment, end rental, mark room CLEANING, record transactions
require_once 'db.php';
session_start();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['success'=>false,'error'=>'Method not allowed']); exit; }
if (!isset($_SESSION['user_id'])) { http_response_code(403); echo json_encode(['success'=>false,'error'=>'Forbidden']); exit; }

$bill_id = isset($_POST['bill_id']) ? intval($_POST['bill_id']) : 0;
$amount = isset($_POST['amount']) ? floatval($_POST['amount']) : 0.0;
$payment_method = isset($_POST['payment_method']) ? trim($_POST['payment_method']) : 'CASH';
$cashier_id = intval($_SESSION['user_id']);

if ($bill_id <= 0 || $amount <= 0) { http_response_code(400); echo json_encode(['success'=>false,'error'=>'Invalid request']); exit; }

$mysqli->begin_transaction();
try {
    $stmt = $mysqli->prepare("SELECT rental_id, grand_total, is_paid FROM bills WHERE bill_id = ? LIMIT 1 FOR UPDATE");
    $stmt->bind_param('i', $bill_id);
    $stmt->execute();
    $bill = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$bill) throw new Exception('Bill not found');
    $rental_id = intval($bill['rental_id']);

    $now = date('Y-m-d H:i:s');
    $stmt = $mysqli->prepare("INSERT INTO payments (bill_id, amount_paid, payment_method, paid_at) VALUES (?, ?, ?, ?)");
    $stmt->bind_param('idss', $bill_id, $amount, $payment_method, $now);
    $stmt->execute();
    $payment_id = $stmt->insert_id;
    $stmt->close();

    $date = date('Y-m-d');
    $stmt = $mysqli->prepare("INSERT INTO transactions (bill_id, transaction_date, total_amount) VALUES (?, ?, ?)");
    $stmt->bind_param('isd', $bill_id, $date, $amount);
    $stmt->execute();
    $transaction_id = $stmt->insert_id;
    $stmt->close();

    $stmt = $mysqli->prepare("UPDATE bills SET is_paid = 1 WHERE bill_id = ?");
    $stmt->bind_param('i', $bill_id);
    $stmt->execute();
    $stmt->close();

    $ended_at = date('Y-m-d H:i:s');
    $stmt = $mysqli->prepare("UPDATE rentals SET ended_at = ?, is_active = 0 WHERE rental_id = ?");
    $stmt->bind_param('si', $ended_at, $rental_id);
    $stmt->execute();
    $stmt->close();

    $stmt = $mysqli->prepare("SELECT room_id FROM rentals WHERE rental_id = ? LIMIT 1");
    $stmt->bind_param('i', $rental_id);
    $stmt->execute();
    $rrow = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($rrow && isset($rrow['room_id'])) {
        $room_id = intval($rrow['room_id']);
        $stmt = $mysqli->prepare("UPDATE rooms SET status = 'CLEANING' WHERE room_id = ?");
        $stmt->bind_param('i', $room_id);
        $stmt->execute();
        $stmt->close();
    }

    $mysqli->commit();
    echo json_encode(['success'=>true,'payment_id'=>$payment_id,'transaction_id'=>$transaction_id]);
    exit;
} catch (Exception $e) {
    $mysqli->rollback();
    http_response_code(500);
    echo json_encode(['success'=>false,'error'=>'Server error: '. $e->getMessage()]);
    exit;
}