<?php
// claim_order.php - staff claims an order (assign to them and set PREPARING), writes audit and notifies WS
require_once 'db.php';
header('Content-Type: application/json');
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['success'=>false,'error'=>'Method not allowed']); exit; }
if (!isset($_SESSION['user_id']) || intval($_SESSION['role_id']) !== 2) { http_response_code(403); echo json_encode(['success'=>false,'error'=>'Forbidden']); exit; }

$order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
if ($order_id <= 0) { http_response_code(400); echo json_encode(['success'=>false,'error'=>'Invalid order']); exit; }

$staff_id = intval($_SESSION['user_id']);
$role_id = intval($_SESSION['role_id']);
$now = date('Y-m-d H:i:s');

$mysqli->begin_transaction();
try {
    $stmt = $mysqli->prepare("SELECT order_id, status, assigned_staff_id FROM orders WHERE order_id = ? LIMIT 1 FOR UPDATE");
    $stmt->bind_param('i', $order_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) throw new Exception('Order not found');
    if ($row['assigned_staff_id'] && intval($row['assigned_staff_id']) !== 0) throw new Exception('Already claimed');

    $stmt = $mysqli->prepare("UPDATE orders SET assigned_staff_id = ?, assigned_at = ?, status = 'PREPARING' WHERE order_id = ?");
    $stmt->bind_param('isi', $staff_id, $now, $order_id);
    $stmt->execute();
    $stmt->close();

    // audit
    $meta = json_encode(['action' => 'CLAIM', 'assigned_to' => $staff_id]);
    $stmt = $mysqli->prepare("INSERT INTO order_audit (order_id, action, user_id, role_id, meta, created_at) VALUES (?, 'CLAIMED', ?, ?, ?, ?)");
    $stmt->bind_param('iisss', $order_id, $staff_id, $role_id, $meta, $now);
    $stmt->execute();
    $stmt->close();

    $mysqli->commit();

    // notify WS
    $notify = ['type' => 'claim', 'order_id' => $order_id, 'assigned_staff_id' => $staff_id, 'assigned_at' => $now];
    $ch = curl_init('http://127.0.0.1:8080/notify');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); curl_setopt($ch, CURLOPT_TIMEOUT, 1);
    curl_setopt($ch, CURLOPT_POST, true); curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($notify));
    @curl_exec($ch); @curl_close($ch);

    echo json_encode(['success'=>true,'order_id'=>$order_id,'assigned_staff_id'=>$staff_id]);
    exit;
} catch (Exception $e) {
    $mysqli->rollback();
    http_response_code(400);
    echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
    exit;
}