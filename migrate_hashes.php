<?php
// migrate_hashes.php - run once to hash plaintext passwords then delete this file
require_once 'db.php';

$res = $mysqli->query("SELECT user_id, password_hash FROM users");
if (!$res) { die("Query failed: " . $mysqli->error); }

while ($row = $res->fetch_assoc()) {
    $id = (int)$row['user_id'];
    $pw = $row['password_hash'];
    if ($pw === null) continue;
    if (strpos($pw, '$') === 0) {
        echo "User {$id} already hashed\n";
        continue;
    }
    $new = password_hash($pw, PASSWORD_DEFAULT);
    $stmt = $mysqli->prepare("UPDATE users SET password_hash = ? WHERE user_id = ?");
    $stmt->bind_param('si', $new, $id);
    if ($stmt->execute()) {
        echo "Updated user {$id}\n";
    } else {
        echo "Failed user {$id}: " . $mysqli->error . "\n";
    }
    $stmt->close();
}
echo "Done\n";