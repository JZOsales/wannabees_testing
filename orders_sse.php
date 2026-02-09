<?php
require_once 'db.php';
if (session_status() == PHP_SESSION_NONE) session_start();
// allow staff & owner only to connect
if (!isset($_SESSION['user_id']) || !in_array(intval($_SESSION['role_id']), [1,2])) {
    http_response_code(403); echo "Forbidden\n"; exit;
}

header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
set_time_limit(0);

$lastPayload = null;
$intervalSec = 3;

while (true) {
    // Query pending orders as simple JSON snapshot
    $sql = "
    SELECT
      o.order_id,
      o.ordered_at,
      o.status,
      o.assigned_staff_id,
      u.display_name AS assigned_staff_name,
      r.room_id,
      rm.room_number,
      GROUP_CONCAT(CONCAT(oi.quantity, 'x ', p.product_name) ORDER BY oi.order_item_id SEPARATOR '||') AS item_list
    FROM orders o
    JOIN rentals r ON o.rental_id = r.rental_id
    JOIN rooms rm ON r.room_id = rm.room_id
    LEFT JOIN users u ON o.assigned_staff_id = u.user_id
    JOIN order_items oi ON oi.order_id = o.order_id
    JOIN products p ON oi.product_id = p.product_id
    WHERE o.status IN ('NEW','PREPARING','READY')
    GROUP BY o.order_id
    ORDER BY o.ordered_at ASC
    LIMIT 500";
    $res = $mysqli->query($sql);
    $orders = [];
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $items = [];
            if (!empty($row['item_list'])) {
                foreach (explode('||', $row['item_list']) as $it) $items[] = $it;
            }
            $orders[] = [
                'order_id' => intval($row['order_id']),
                'ordered_at' => $row['ordered_at'],
                'status' => $row['status'],
                'assigned_staff_id' => $row['assigned_staff_id'] ? intval($row['assigned_staff_id']) : null,
                'assigned_staff_name' => $row['assigned_staff_name'] ?: null,
                'room_id' => intval($row['room_id']),
                'room_number' => intval($row['room_number']),
                'items' => $items
            ];
        }
        $res->free();
    }

    $payload = json_encode(['orders' => $orders]);

    if ($payload !== $lastPayload) {
        // send as event "orders"
        echo "event: orders\n";
        // ensure proper newlines per SSE spec
        foreach (explode("\n", trim(chunk_split($payload, 8000, "\n"))) as $line) {
            echo 'data: ' . $line . "\n";
        }
        echo "\n";
        @ob_flush(); @flush();
        $lastPayload = $payload;
    } else {
        // send a heartbeat comment to keep connection alive
        echo ": heartbeat\n\n";
        @ob_flush(); @flush();
    }

    // sleep for interval; break on client disconnect
    for ($i=0;$i<$intervalSec;$i++) {
        if (connection_aborted()) { exit; }
        sleep(1);
    }
}