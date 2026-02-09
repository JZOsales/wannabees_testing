<?php
// create_user.php - create a user (Owner only) with room assignment support
session_start();
require_once 'db.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { 
    http_response_code(405); 
    echo json_encode(['success'=>false,'error'=>'Method not allowed']); 
    exit; 
}

if (!isset($_SESSION['user_id']) || intval($_SESSION['role_id']) !== 1) { 
    http_response_code(403); 
    echo json_encode(['success'=>false,'error'=>'Forbidden']); 
    exit; 
}

$username = isset($_POST['username']) ? trim($_POST['username']) : '';
$password = isset($_POST['password']) ? $_POST['password'] : '';
$role_id = isset($_POST['role_id']) ? intval($_POST['role_id']) : 4;
$display = isset($_POST['display_name']) ? trim($_POST['display_name']) : null;
$room_id = isset($_POST['room_id']) && $_POST['room_id'] !== '' ? intval($_POST['room_id']) : null;

if ($username === '' || $password === '' || strlen($password) < 6) { 
    echo json_encode(['success'=>false,'error'=>'Invalid input. Username and password (min 6 chars) required.']); 
    exit; 
}

// Check if username already exists
$stmt = $mysqli->prepare("SELECT user_id FROM users WHERE username = ? LIMIT 1");
$stmt->bind_param('s', $username);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows > 0) { 
    $stmt->close(); 
    echo json_encode(['success'=>false,'error'=>'Username already exists']); 
    exit; 
}
$stmt->close();

// If role is Customer/Tablet (role_id = 4) and room_id is provided, check if room is already assigned
if ($role_id === 4 && $room_id !== null) {
    $stmt = $mysqli->prepare("SELECT user_id FROM users WHERE room_id = ? AND role_id = 4 LIMIT 1");
    $stmt->bind_param('i', $room_id);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        $stmt->close();
        echo json_encode(['success'=>false,'error'=>'This room is already assigned to another user']); 
        exit;
    }
    $stmt->close();
}

// Hash the password
$hash = password_hash($password, PASSWORD_DEFAULT);
$now = date('Y-m-d H:i:s');

// Insert new user with room_id - using 'password' column (not password_hash)
$stmt = $mysqli->prepare("INSERT INTO users (username, password, role_id, room_id, is_active, created_at, display_name) VALUES (?, ?, ?, ?, 1, ?, ?)");
$stmt->bind_param('ssiiss', $username, $hash, $role_id, $room_id, $now, $display);

if ($stmt->execute()) { 
    $id = $stmt->insert_id; 
    $stmt->close(); 
    echo json_encode(['success'=>true,'user_id'=>$id]); 
    exit; 
} else { 
    $err = $mysqli->error; 
    $stmt->close();
    echo json_encode(['success'=>false,'error'=>'DB error: '.$err]); 
    exit; 
}