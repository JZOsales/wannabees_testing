<?php
// request_payment.php - Customer requests staff to come and process payment
session_start();
require_once 'db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$rental_id = isset($_POST['rental_id']) ? intval($_POST['rental_id']) : 0;
$room_id = isset($_POST['room_id']) ? intval($_POST['room_id']) : 0;

if ($rental_id <= 0 || $room_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
    exit;
}

try {
    // Get room and bill information
    $billSql = "
    SELECT b.bill_id, b.grand_total, b.is_paid, r.room_number
    FROM bills b
    JOIN rentals rent ON b.rental_id = rent.rental_id
    JOIN rooms r ON rent.room_id = r.room_id
    WHERE b.rental_id = ?
    ORDER BY b.created_at DESC
    LIMIT 1";
    
    $stmt = $mysqli->prepare($billSql);
    $stmt->bind_param('i', $rental_id);
    $stmt->execute();
    $bill = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$bill) {
        echo json_encode(['success' => false, 'error' => 'Bill not found']);
        exit;
    }
    
    if ($bill['is_paid'] == 1) {
        echo json_encode(['success' => false, 'error' => 'Bill has already been paid']);
        exit;
    }
    
    // Here you can add notification logic
    // For example: insert into a notifications table, send websocket notification, etc.
    
    // For now, we'll just return success
    // You can extend this to store payment requests in a table
    $notifSql = "
    INSERT INTO payment_requests (rental_id, room_id, bill_id, requested_at, status)
    VALUES (?, ?, ?, NOW(), 'PENDING')
    ON DUPLICATE KEY UPDATE requested_at = NOW(), status = 'PENDING'";
    
    // Check if payment_requests table exists, if not, skip this
    $tableCheck = $mysqli->query("SHOW TABLES LIKE 'payment_requests'");
    if ($tableCheck->num_rows > 0) {
        $stmt = $mysqli->prepare($notifSql);
        $stmt->bind_param('iii', $rental_id, $room_id, $bill['bill_id']);
        $stmt->execute();
        $stmt->close();
    }
    
    // Notify WebSocket (optional - if you have WS set up)
    $notify = [
        'type' => 'payment_request',
        'rental_id' => $rental_id,
        'room_id' => $room_id,
        'room_number' => $bill['room_number'],
        'amount' => floatval($bill['grand_total']),
        'requested_at' => date('Y-m-d H:i:s')
    ];
    
    $ch = curl_init('http://127.0.0.1:8080/notify');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 1);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($notify));
    @curl_exec($ch);
    @curl_close($ch);
    
    echo json_encode([
        'success' => true,
        'message' => 'Payment request sent successfully',
        'room_number' => $bill['room_number'],
        'amount' => floatval($bill['grand_total'])
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Server error: ' . $e->getMessage()
    ]);
}