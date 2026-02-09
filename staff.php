<?php
// staff_dashboard.php - Comprehensive Staff Dashboard with Room Management & Kitchen Monitor
session_start();
require_once 'db.php';

// Security Check
if (!isset($_SESSION['user_id']) || intval($_SESSION['role_id']) !== 2) {
    header('Location: index.php');
    exit;
}

$staffName = htmlspecialchars($_SESSION['display_name'] ?: $_SESSION['username']);
$staffId = intval($_SESSION['user_id']);

// Fetch Rooms with Active Rentals
$roomsSql = "
SELECT
  r.room_id,
  r.room_number,
  r.status,
  rt.type_name,
  rt.price_per_hour,
  rt.price_per_30min,
  rent.rental_id,
  rent.started_at,
  rent.total_minutes
FROM rooms r
JOIN room_types rt ON r.room_type_id = rt.room_type_id
LEFT JOIN rentals rent ON rent.room_id = r.room_id AND rent.ended_at IS NULL
ORDER BY r.room_number ASC";
$roomsResult = $mysqli->query($roomsSql);
$rooms = [];
if ($roomsResult) {
    while ($row = $roomsResult->fetch_assoc()) $rooms[] = $row;
    $roomsResult->free();
}

// Fetch Pending Kitchen Orders
$ordersSql = "
SELECT 
  o.order_id, o.status, o.ordered_at,
  rm.room_number,
  GROUP_CONCAT(CONCAT(p.product_name, ' x', oi.quantity) ORDER BY oi.order_item_id SEPARATOR '|') as items,
  SUM(oi.quantity * oi.price) as total_amount
FROM orders o
JOIN rentals r ON o.rental_id = r.rental_id
JOIN rooms rm ON r.room_id = rm.room_id
JOIN order_items oi ON o.order_id = oi.order_id
JOIN products p ON oi.product_id = p.product_id
WHERE o.status IN ('NEW', 'PREPARING', 'READY')
GROUP BY o.order_id
ORDER BY o.ordered_at ASC";
$ordersResult = $mysqli->query($ordersSql);
$pendingOrders = [];
if ($ordersResult) {
    while ($row = $ordersResult->fetch_assoc()) $pendingOrders[] = $row;
    $ordersResult->free();
}

// Get Cleaning Logs and Order Completions for Today
$today = date('Y-m-d');

// Get cleaning logs
$logsSql = "
SELECT 
  'cleaning' as activity_type,
  cl.cleaning_id as id, 
  cl.room_id, 
  cl.cleaned_at as activity_time, 
  r.room_number,
  NULL as order_id,
  NULL as items
FROM cleaning_logs cl
JOIN rooms r ON cl.room_id = r.room_id
WHERE cl.staff_id = ? AND DATE(cl.cleaned_at) = ?

UNION ALL

SELECT 
  'order' as activity_type,
  oa.audit_id as id,
  r.room_id,
  oa.created_at as activity_time,
  rm.room_number,
  o.order_id,
  GROUP_CONCAT(CONCAT(p.product_name, ' x', oi.quantity) SEPARATOR ', ') as items
FROM order_audit oa
JOIN orders o ON oa.order_id = o.order_id
JOIN rentals r ON o.rental_id = r.rental_id
JOIN rooms rm ON r.room_id = rm.room_id
JOIN order_items oi ON o.order_id = oi.order_id
JOIN products p ON oi.product_id = p.product_id
WHERE oa.action = 'STATUS_CHANGE'
  AND oa.user_id = ? 
  AND DATE(oa.created_at) = ?
  AND JSON_EXTRACT(oa.meta, '$.to') = 'DELIVERED'
GROUP BY oa.audit_id

ORDER BY activity_time DESC";

$stmt = $mysqli->prepare($logsSql);
$stmt->bind_param('isis', $staffId, $today, $staffId, $today);
$stmt->execute();
$logsResult = $stmt->get_result();
$todayLogs = [];
while ($row = $logsResult->fetch_assoc()) $todayLogs[] = $row;
$stmt->close();

// Get Statistics
$stats = $mysqli->query("SELECT 
    SUM(status='AVAILABLE') AS available, 
    SUM(status='OCCUPIED') AS occupied, 
    SUM(status='CLEANING') AS cleaning 
    FROM rooms")->fetch_assoc();
$available = intval($stats['available']); 
$occupied = intval($stats['occupied']); 
$cleaning = intval($stats['cleaning']);
$newOrdersCount = 0;
foreach ($pendingOrders as $order) {
    if ($order['status'] === 'NEW') $newOrdersCount++;
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Staff Dashboard – Wannabees Family KTV</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }
    
    body {
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
      background: #e8e4d9;
      color: #2c2c2c;
    }
    
    /* ===== HEADER ===== */
    header {
      background: white;
      padding: 15px 30px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      box-shadow: 0 2px 8px rgba(0,0,0,0.1);
      position: sticky;
      top: 0;
      z-index: 100;
    }
    
    .header-left {
      display: flex;
      align-items: center;
      gap: 15px;
    }
    
    .header-left img {
      height: 50px;
    }
    
    .header-title {
      font-size: 20px;
      font-weight: 700;
      color: #2c2c2c;
    }
    
    .header-subtitle {
      font-size: 14px;
      color: #666;
    }
    
    .header-nav {
      display: flex;
      gap: 10px;
      align-items: center;
    }
    
    .nav-btn {
      padding: 10px 20px;
      border: none;
      border-radius: 8px;
      cursor: pointer;
      font-size: 14px;
      font-weight: 600;
      transition: all 0.3s;
      display: flex;
      align-items: center;
      gap: 8px;
      position: relative;
    }
    
    .nav-btn.rooms {
      background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
      color: white;
    }
    
    .nav-btn.kitchen {
      background: linear-gradient(135deg, #f39c12 0%, #e67e22 100%);
      color: white;
    }
    
    .nav-btn.guide {
      background: #d4d4d4;
      color: #2c2c2c;
    }
    
    .nav-btn.logout {
      background: #e74c3c;
      color: white;
    }
    
    .nav-btn:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(0,0,0,0.2);
    }
    
    .nav-btn .badge {
      position: absolute;
      top: -8px;
      right: -8px;
      background: #e74c3c;
      color: white;
      border-radius: 50%;
      min-width: 22px;
      height: 22px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 11px;
      font-weight: 700;
      padding: 0 6px;
      animation: pulse 2s infinite;
    }
    
    @keyframes pulse {
      0%, 100% { transform: scale(1); }
      50% { transform: scale(1.1); }
    }
    
    /* ===== MAIN CONTENT ===== */
    main {
      padding: 25px 30px;
      max-width: 1600px;
      margin: 0 auto;
    }
    
    /* ===== SUMMARY CARDS ===== */
    .summary-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 20px;
      margin-bottom: 30px;
    }
    
    .summary-card {
      background: white;
      padding: 25px;
      border-radius: 12px;
      box-shadow: 0 4px 15px rgba(0,0,0,0.08);
      animation: slideUp 0.5s ease-out;
      position: relative;
      overflow: hidden;
      transition: transform 0.3s;
    }
    
    .summary-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 6px 20px rgba(0,0,0,0.12);
    }
    
    @keyframes slideUp {
      from {
        opacity: 0;
        transform: translateY(20px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }
    
    .summary-card::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      height: 4px;
    }
    
    .summary-card.available::before { background: #27ae60; }
    .summary-card.occupied::before { background: #e74c3c; }
    .summary-card.cleaning::before { background: #3498db; }
    .summary-card.logs::before { background: #f39c12; }
    .summary-card.orders::before { background: #9b59b6; }
    
    .summary-label {
      font-size: 14px;
      font-weight: 600;
      margin-bottom: 10px;
      color: #666;
    }
    
    .summary-value {
      font-size: 36px;
      font-weight: 700;
    }
    
    .summary-card.available .summary-value { color: #27ae60; }
    .summary-card.occupied .summary-value { color: #e74c3c; }
    .summary-card.cleaning .summary-value { color: #3498db; }
    .summary-card.logs .summary-value { color: #f39c12; }
    .summary-card.orders .summary-value { color: #9b59b6; }
    
    /* ===== SECTION HEADERS ===== */
    .section-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 20px;
      margin-top: 30px;
    }
    
    .section-title {
      font-size: 22px;
      font-weight: 700;
      color: #2c2c2c;
      display: flex;
      align-items: center;
      gap: 10px;
    }
    
    .filter-buttons {
      display: flex;
      gap: 8px;
    }
    
    .btn {
      padding: 10px 18px;
      border: none;
      border-radius: 8px;
      cursor: pointer;
      font-size: 14px;
      font-weight: 600;
      transition: all 0.3s ease;
      background: #e8e8e8;
      color: #333;
    }
    
    .btn:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    }
    
    .btn.active {
      background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
      color: white;
    }
    
    /* ===== ROOMS GRID ===== */
    .rooms-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
      gap: 18px;
      margin-bottom: 40px;
    }
    
    .room-card {
      background: white;
      padding: 22px;
      border-radius: 12px;
      cursor: pointer;
      transition: all 0.3s ease;
      text-align: center;
      position: relative;
      overflow: hidden;
      box-shadow: 0 4px 12px rgba(0,0,0,0.08);
    }
    
    .room-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 8px 25px rgba(0,0,0,0.15);
    }
    
    .room-card.available { border-left: 5px solid #27ae60; }
    .room-card.occupied { border-left: 5px solid #e74c3c; }
    .room-card.cleaning { border-left: 5px solid #3498db; }
    
    .room-number {
      font-size: 24px;
      font-weight: 700;
      margin-bottom: 8px;
      color: #2c2c2c;
    }
    
    .room-type {
      font-size: 13px;
      color: #666;
      margin-bottom: 12px;
    }
    
    .room-status {
      padding: 6px 12px;
      border-radius: 20px;
      font-size: 12px;
      font-weight: 600;
      display: inline-block;
      margin-bottom: 15px;
    }
    
    .room-card.available .room-status {
      background: #d4edda;
      color: #155724;
    }
    
    .room-card.occupied .room-status {
      background: #f8d7da;
      color: #721c24;
    }
    
    .room-card.cleaning .room-status {
      background: #d1ecf1;
      color: #0c5460;
    }
    
    .room-time {
      background: #f9f9f9;
      padding: 10px;
      border-radius: 8px;
      font-size: 13px;
      color: #666;
      margin-bottom: 12px;
    }
    
    .room-actions {
      display: flex;
      gap: 8px;
      flex-direction: column;
    }
    
    .room-btn {
      padding: 10px;
      border: none;
      border-radius: 6px;
      cursor: pointer;
      font-size: 13px;
      font-weight: 600;
      transition: all 0.3s;
    }
    
    .room-btn.mark-cleaning {
      background: #3498db;
      color: white;
    }
    
    .room-btn.mark-available {
      background: #27ae60;
      color: white;
    }
    
    .room-btn:hover {
      transform: scale(1.05);
      box-shadow: 0 4px 12px rgba(0,0,0,0.2);
    }
    
    /* ===== KITCHEN ORDERS ===== */
    .orders-container {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
      gap: 18px;
      margin-bottom: 40px;
    }
    
    .order-card {
      background: white;
      padding: 20px;
      border-radius: 12px;
      box-shadow: 0 4px 15px rgba(0,0,0,0.08);
      border-left: 6px solid #ccc;
      transition: all 0.3s;
      display: flex;
      flex-direction: column;
    }
    
    .order-card:hover {
      transform: translateY(-3px);
      box-shadow: 0 6px 20px rgba(0,0,0,0.12);
    }
    
    .order-card.status-new { 
      border-left-color: #e74c3c;
      animation: newOrderPulse 2s infinite;
    }
    .order-card.status-preparing { border-left-color: #f39c12; }
    .order-card.status-ready { 
      border-left-color: #2ecc71;
      animation: readyPulse 1.5s infinite;
    }
    
    @keyframes newOrderPulse {
      0%, 100% { box-shadow: 0 4px 15px rgba(0,0,0,0.08); }
      50% { box-shadow: 0 4px 20px rgba(231, 76, 60, 0.3); }
    }
    
    @keyframes readyPulse {
      0%, 100% { box-shadow: 0 4px 15px rgba(0,0,0,0.08); }
      50% { box-shadow: 0 4px 20px rgba(46, 204, 113, 0.3); }
    }
    
    .order-card h3 {
      margin: 0 0 10px 0;
      color: #333;
      font-size: 18px;
    }
    
    .order-card p {
      margin: 5px 0;
      color: #666;
    }
    
    .order-status-badge {
      display: inline-block;
      padding: 5px 12px;
      border-radius: 20px;
      font-size: 12px;
      font-weight: 700;
      margin-bottom: 10px;
    }
    
    .order-card.status-new .order-status-badge {
      background: #fee;
      color: #e74c3c;
    }
    
    .order-card.status-preparing .order-status-badge {
      background: #fff3cd;
      color: #856404;
    }
    
    .order-card.status-ready .order-status-badge {
      background: #d4edda;
      color: #155724;
    }
    
    .items-list {
      background: #f9f9f9;
      padding: 12px;
      margin: 12px 0;
      border-radius: 8px;
      font-size: 14px;
      line-height: 1.8;
      min-height: 80px;
      display: flex;
      flex-direction: column;
      gap: 4px;
    }
    
    .order-footer {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-top: auto;
      margin-bottom: 8px;
      padding-top: 8px;
    }
    
    .order-time {
      font-size: 13px;
      color: #999;
      display: flex;
      align-items: center;
      gap: 5px;
    }
    
    .order-total {
      font-size: 20px;
      font-weight: 700;
      color: #f39c12;
      white-space: nowrap;
    }
    
    .order-btn {
      width: 100%;
      padding: 12px;
      margin-top: auto;
      border: none;
      border-radius: 8px;
      color: white;
      font-weight: 700;
      cursor: pointer;
      font-size: 14px;
      transition: all 0.3s;
    }
    
    .btn-claim {
      background: linear-gradient(135deg, #e67e22 0%, #d35400 100%);
    }
    
    .btn-ready {
      background: linear-gradient(135deg, #27ae60 0%, #229954 100%);
    }
    
    .btn-deliver {
      background: linear-gradient(135deg, #2980b9 0%, #1f618d 100%);
    }
    
    .order-btn:hover {
      transform: scale(1.02);
      box-shadow: 0 4px 15px rgba(0,0,0,0.2);
    }
    
    /* ===== ACTIVITY LOG ===== */
    .activity-section {
      background: white;
      padding: 30px;
      border-radius: 12px;
      box-shadow: 0 4px 15px rgba(0,0,0,0.08);
    }
    
    .activity-list {
      list-style: none;
      padding: 0;
    }
    
    .activity-item {
      display: flex;
      align-items: center;
      gap: 15px;
      padding: 15px;
      border-bottom: 1px solid #f0f0f0;
      transition: background 0.3s;
    }
    
    .activity-item:hover {
      background: #f9f9f9;
    }
    
    .activity-item:last-child {
      border-bottom: none;
    }
    
    .activity-icon {
      width: 45px;
      height: 45px;
      background: linear-gradient(135deg, #f5c542 0%, #f2a20a 100%);
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      color: white;
      font-size: 18px;
      flex-shrink: 0;
    }
    
    .activity-details {
      flex: 1;
    }
    
    .activity-title {
      font-weight: 600;
      margin-bottom: 4px;
      color: #2c2c2c;
    }
    
    .activity-time {
      font-size: 13px;
      color: #999;
    }
    
    .empty-state {
      text-align: center;
      padding: 50px 20px;
      color: #999;
    }
    
    .empty-state i {
      font-size: 56px;
      margin-bottom: 15px;
      color: #ddd;
    }
    
    /* ===== MODAL ===== */
    .modal {
      display: none;
      position: fixed;
      inset: 0;
      background: rgba(0,0,0,0.6);
      z-index: 1000;
      align-items: center;
      justify-content: center;
    }
    
    .modal.active {
      display: flex;
    }
    
    .modal-content {
      background: white;
      border-radius: 16px;
      padding: 30px;
      width: 90%;
      max-width: 500px;
      animation: scaleIn 0.3s ease;
    }
    
    @keyframes scaleIn {
      from {
        opacity: 0;
        transform: scale(0.9);
      }
      to {
        opacity: 1;
        transform: scale(1);
      }
    }
    
    .modal-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 20px;
    }
    
    .modal-title {
      font-size: 22px;
      font-weight: 700;
    }
    
    .modal-close {
      background: none;
      border: none;
      font-size: 28px;
      cursor: pointer;
      color: #999;
    }
    
    .modal-close:hover {
      color: #333;
    }
    
    .modal-body {
      margin-bottom: 20px;
      line-height: 1.6;
    }
    
    .modal-actions {
      display: flex;
      gap: 12px;
    }
    
    .btn-cancel {
      flex: 1;
      padding: 14px;
      background: #e8e8e8;
      border: none;
      border-radius: 8px;
      cursor: pointer;
      font-weight: 600;
      transition: all 0.3s;
    }
    
    .btn-cancel:hover {
      background: #d4d4d4;
    }
    
    .btn-confirm {
      flex: 1;
      padding: 14px;
      background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
      border: none;
      border-radius: 8px;
      color: white;
      cursor: pointer;
      font-weight: 600;
      transition: all 0.3s;
    }
    
    .btn-confirm:hover {
      box-shadow: 0 4px 15px rgba(52, 152, 219, 0.4);
    }
    
    /* ===== RESPONSIVE ===== */
    @media (max-width: 768px) {
      header {
        flex-direction: column;
        gap: 15px;
      }
      
      .header-nav {
        width: 100%;
        justify-content: space-between;
      }
      
      .nav-btn {
        font-size: 12px;
        padding: 8px 12px;
      }
      
      .summary-grid {
        grid-template-columns: repeat(2, 1fr);
      }
      
      .rooms-grid,
      .orders-container {
        grid-template-columns: 1fr;
      }
    }
  </style>
</head>
<body>
  <header>
    <div class="header-left">
      <img src="assets/images/KTVL.png" alt="Wannabees KTV Logo">
      <div>
        <div class="header-title">Wannabees Family KTV</div>
        <div class="header-subtitle">Welcome, <?= $staffName ?></div>
      </div>
    </div>
    <div class="header-nav">
      <button class="nav-btn rooms" onclick="scrollToSection('roomsSection')">
        <i class="fas fa-door-open"></i> Rooms
      </button>
      <button class="nav-btn kitchen" onclick="scrollToSection('kitchenSection')">
        <i class="fas fa-utensils"></i> Kitchen
        <?php if ($newOrdersCount > 0): ?>
          <span class="badge"><?= $newOrdersCount ?></span>
        <?php endif; ?>
      </button>
      <button class="nav-btn guide" onclick="location.href='guide_staff.php'">
        <i class="fas fa-book-open"></i> Guide
      </button>
      <form action="logout.php" method="post" style="display:inline">
        <button class="nav-btn logout" type="submit">
          <i class="fas fa-sign-out-alt"></i> Logout
        </button>
      </form>
    </div>
  </header>

  <main>
    <!-- Summary Dashboard -->
    <div class="summary-grid">
      <div class="summary-card available">
        <div class="summary-label"><i class="fas fa-check-circle"></i> Available Rooms</div>
        <div class="summary-value"><?= $available ?></div>
      </div>
      <div class="summary-card occupied">
        <div class="summary-label"><i class="fas fa-users"></i> Occupied Rooms</div>
        <div class="summary-value"><?= $occupied ?></div>
      </div>
      <div class="summary-card cleaning">
        <div class="summary-label"><i class="fas fa-broom"></i> Cleaning Queue</div>
        <div class="summary-value"><?= $cleaning ?></div>
      </div>
      <div class="summary-card orders">
        <div class="summary-label"><i class="fas fa-utensils"></i> Pending Orders</div>
        <div class="summary-value"><?= count($pendingOrders) ?></div>
      </div>
      <div class="summary-card logs">
        <div class="summary-label"><i class="fas fa-clipboard-check"></i> Today's Work</div>
        <div class="summary-value"><?= count($todayLogs) ?></div>
      </div>
    </div>

    <!-- Rooms Section -->
    <div id="roomsSection">
      <div class="section-header">
        <h2 class="section-title">
          <i class="fas fa-door-open"></i> Rooms Management
        </h2>
        <div class="filter-buttons">
          <button class="btn active" onclick="filterRooms('ALL')">All</button>
          <button class="btn" onclick="filterRooms('AVAILABLE')">Available</button>
          <button class="btn" onclick="filterRooms('OCCUPIED')">Occupied</button>
          <button class="btn" onclick="filterRooms('CLEANING')">Cleaning</button>
        </div>
      </div>
      <div class="rooms-grid" id="roomsGrid">
        <?php foreach ($rooms as $r):
          $statusClass = strtolower($r['status']);
          $elapsed = '';
          if ($r['rental_id']) {
            $start = new DateTime($r['started_at']);
            $now = new DateTime();
            $diff = $now->getTimestamp() - $start->getTimestamp();
            $hours = floor($diff / 3600);
            $mins = floor(($diff % 3600) / 60);
            $elapsed = sprintf('%02d:%02d', $hours, $mins);
          }
        ?>
          <div class="room-card <?= $statusClass ?>" data-status="<?= $r['status'] ?>" data-room-id="<?= $r['room_id'] ?>">
            <div class="room-number">Room <?= $r['room_number'] ?></div>
            <div class="room-type"><?= htmlspecialchars($r['type_name']) ?></div>
            <div class="room-status"><?= ucfirst($r['status']) ?></div>
            
            <?php if ($r['rental_id']): ?>
              <div class="room-time">
                <i class="fas fa-clock"></i> Active: <?= $elapsed ?>
              </div>
            <?php endif; ?>
            
            <div class="room-actions">
              <?php if ($r['status'] === 'AVAILABLE' || $r['status'] === 'OCCUPIED'): ?>
                <button class="room-btn mark-cleaning" onclick="markCleaning(<?= $r['room_id'] ?>, <?= $r['room_number'] ?>)">
                  <i class="fas fa-broom"></i> Mark as Cleaning
                </button>
              <?php endif; ?>
              
              <?php if ($r['status'] === 'CLEANING'): ?>
                <button class="room-btn mark-available" onclick="markAvailable(<?= $r['room_id'] ?>, <?= $r['room_number'] ?>)">
                  <i class="fas fa-check"></i> Mark as Available
                </button>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Kitchen Orders Section -->
    <div id="kitchenSection">
      <div class="section-header">
        <h2 class="section-title">
          <i class="fas fa-fire"></i> Kitchen Monitor
        </h2>
        <button class="btn" onclick="location.reload()">
          <i class="fas fa-sync-alt"></i> Refresh
        </button>
      </div>
      <div class="orders-container" id="kitchenOrdersGrid">
        <?php if (empty($pendingOrders)): ?>
          <div class="empty-state">
            <i class="fas fa-check-circle"></i>
            <p>No pending orders. Great job!</p>
          </div>
        <?php else: ?>
          <?php foreach ($pendingOrders as $order): ?>
            <?php 
              $statusLower = strtolower($order['status']); 
              $btnHtml = '';
              
              if ($order['status'] == 'NEW') {
                $btnHtml = '<button class="order-btn btn-claim" onclick="claimOrder('.$order['order_id'].')">
                  <i class="fas fa-hand-paper"></i> Claim & Start Preparing
                </button>';
              } elseif ($order['status'] == 'PREPARING') {
                $btnHtml = '<button class="order-btn btn-ready" onclick="updateOrderStatus('.$order['order_id'].', \'READY\')">
                  <i class="fas fa-check-circle"></i> Mark as Ready
                </button>';
              } elseif ($order['status'] == 'READY') {
                $btnHtml = '<button class="order-btn btn-deliver" onclick="updateOrderStatus('.$order['order_id'].', \'DELIVERED\')">
                  <i class="fas fa-truck"></i> Mark as Delivered
                </button>';
              }
              
              $timeAgo = '';
              $orderTime = new DateTime($order['ordered_at']);
              $now = new DateTime();
              $diff = $now->getTimestamp() - $orderTime->getTimestamp();
              $minutes = floor($diff / 60);
              if ($minutes < 60) {
                $timeAgo = $minutes . ' min ago';
              } else {
                $hours = floor($minutes / 60);
                $timeAgo = $hours . 'h ' . ($minutes % 60) . 'm ago';
              }
            ?>

            <div class="order-card status-<?= $statusLower ?>" id="card-<?= $order['order_id'] ?>">
              <h3>
                <i class="fas fa-door-closed"></i> Room #<?= $order['room_number'] ?>
              </h3>
              <span class="order-status-badge"><?= $order['status'] ?></span>
              
              <div class="items-list">
                <?php 
                  // Convert pipe separator to line breaks for better display
                  $items = explode('|', $order['items']);
                  foreach ($items as $item) {
                    echo '<div>' . htmlspecialchars($item) . '</div>';
                  }
                ?>
              </div>
              
              <div class="order-footer">
                <span class="order-time">
                  <i class="fas fa-clock"></i> <?= $timeAgo ?>
                </span>
                <span class="order-total">₱<?= number_format($order['total_amount'], 2) ?></span>
              </div>
              
              <?= $btnHtml ?>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>

    <!-- Orders Section (Activity Log Style) -->
    <div class="activity-section" id="ordersSection" style="margin-top: 40px;">
      <h2 class="section-title">
        <i class="fas fa-utensils"></i> Pending Orders (Delivery)
      </h2>
      <div id="ordersActivityList">
        <div class="empty-state">
          <i class="fas fa-spinner fa-spin"></i>
          <p>Loading orders...</p>
        </div>
      </div>
    </div>

    <!-- Activity Log Section -->
    <div class="activity-section" id="activitySection" style="margin-top: 40px;">
      <h2 class="section-title">
        <i class="fas fa-clipboard-list"></i> Today's Activity Log
      </h2>
      
      <?php if (count($todayLogs) > 0): ?>
        <ul class="activity-list">
          <?php foreach ($todayLogs as $log): ?>
            <li class="activity-item">
              <?php if ($log['activity_type'] === 'cleaning'): ?>
                <div class="activity-icon" style="background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);">
                  <i class="fas fa-broom"></i>
                </div>
                <div class="activity-details">
                  <div class="activity-title">Cleaned Room <?= $log['room_number'] ?></div>
                  <div class="activity-time">
                    <i class="fas fa-clock"></i> <?= date('g:i A', strtotime($log['activity_time'])) ?>
                  </div>
                </div>
              <?php else: ?>
                <div class="activity-icon" style="background: linear-gradient(135deg, #27ae60 0%, #229954 100%);">
                  <i class="fas fa-check-circle"></i>
                </div>
                <div class="activity-details" style="flex: 1;">
                  <div class="activity-title">Delivered Order #<?= $log['order_id'] ?> to Room <?= $log['room_number'] ?></div>
                  <div style="font-size: 13px; color: #666; margin: 5px 0;"><?= htmlspecialchars($log['items']) ?></div>
                  <div class="activity-time">
                    <i class="fas fa-clock"></i> <?= date('g:i A', strtotime($log['activity_time'])) ?>
                  </div>
                </div>
              <?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php else: ?>
        <div class="empty-state">
          <i class="fas fa-clipboard-check"></i>
          <p>No activities logged today yet.</p>
        </div>
      <?php endif; ?>
    </div>
  </main>

  <!-- Confirmation Modal -->
  <div id="confirmModal" class="modal">
    <div class="modal-content">
      <div class="modal-header">
        <h3 class="modal-title" id="modalTitle">Confirm Action</h3>
        <button class="modal-close" onclick="closeModal()">×</button>
      </div>
      <div class="modal-body" id="modalBody">
        Are you sure?
      </div>
      <div class="modal-actions">
        <button class="btn-cancel" onclick="closeModal()">Cancel</button>
        <button class="btn-confirm" id="confirmButton">Confirm</button>
      </div>
    </div>
  </div>

  <script>
    // ===== UTILITY FUNCTIONS =====
    function scrollToSection(sectionId) {
      const element = document.getElementById(sectionId);
      if (element) {
        element.scrollIntoView({ behavior: 'smooth', block: 'start' });
      }
    }

    // ===== ROOM FILTERING =====
    function filterRooms(status) {
      const cards = document.querySelectorAll('.room-card');
      const buttons = document.querySelectorAll('.filter-buttons .btn');
      
      // Update active button
      buttons.forEach(btn => btn.classList.remove('active'));
      event.target.classList.add('active');
      
      // Filter cards
      cards.forEach(card => {
        if (status === 'ALL' || card.dataset.status === status) {
          card.style.display = 'block';
        } else {
          card.style.display = 'none';
        }
      });
    }

    // ===== MODAL MANAGEMENT =====
    let currentAction = null;
    let currentRoomId = null;
    let currentOrderId = null;

    function closeModal() {
      document.getElementById('confirmModal').classList.remove('active');
      currentAction = null;
      currentRoomId = null;
      currentOrderId = null;
    }

    // ===== ROOM MANAGEMENT =====
    async function markCleaning(roomId, roomNumber) {
      currentAction = 'cleaning';
      currentRoomId = roomId;
      
      document.getElementById('modalTitle').innerHTML = '<i class="fas fa-broom"></i> Mark as Cleaning';
      document.getElementById('modalBody').innerHTML = `
        <p>Mark Room ${roomNumber} as <strong>CLEANING</strong>?</p>
        <p style="margin-top: 10px; color: #666; font-size: 14px;">This will log that you started cleaning this room.</p>
      `;
      
      document.getElementById('confirmButton').onclick = confirmMarkCleaning;
      document.getElementById('confirmModal').classList.add('active');
    }

    async function confirmMarkCleaning() {
      const btn = document.getElementById('confirmButton');
      btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
      btn.disabled = true;
      
      try {
        const formData = new FormData();
        formData.append('room_id', currentRoomId);
        
        const res = await fetch('mark_cleaning.php', {
          method: 'POST',
          body: formData
        });
        
        const data = await res.json();
        
        if (data.success) {
          closeModal();
          location.reload();
        } else {
          alert('Error: ' + (data.error || 'Unknown error'));
          btn.innerHTML = 'Confirm';
          btn.disabled = false;
        }
      } catch (err) {
        alert('Network error: ' + err.message);
        btn.innerHTML = 'Confirm';
        btn.disabled = false;
      }
    }

    async function markAvailable(roomId, roomNumber) {
      currentAction = 'available';
      currentRoomId = roomId;
      
      document.getElementById('modalTitle').innerHTML = '<i class="fas fa-check-circle"></i> Mark as Available';
      document.getElementById('modalBody').innerHTML = `
        <p>Mark Room ${roomNumber} as <strong>AVAILABLE</strong>?</p>
        <p style="margin-top: 10px; color: #666; font-size: 14px;">This confirms you've finished cleaning and the room is ready for customers.</p>
      `;
      
      document.getElementById('confirmButton').onclick = confirmMarkAvailable;
      document.getElementById('confirmModal').classList.add('active');
    }

    async function confirmMarkAvailable() {
      const btn = document.getElementById('confirmButton');
      btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
      btn.disabled = true;
      
      try {
        const formData = new FormData();
        formData.append('room_id', currentRoomId);
        
        const res = await fetch('mark_available.php', {
          method: 'POST',
          body: formData
        });
        
        const data = await res.json();
        
        if (data.success) {
          closeModal();
          location.reload();
        } else {
          alert('Error: ' + (data.error || 'Unknown error'));
          btn.innerHTML = 'Confirm';
          btn.disabled = false;
        }
      } catch (err) {
        alert('Network error: ' + err.message);
        btn.innerHTML = 'Confirm';
        btn.disabled = false;
      }
    }

    // ===== ORDER MANAGEMENT =====
    async function claimOrder(orderId) {
      if (!confirm("Start preparing this order?")) return;
      
      const btn = event.target;
      const originalHTML = btn.innerHTML;
      btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Claiming...';
      btn.disabled = true;
      
      try {
        const formData = new FormData();
        formData.append('order_id', orderId);

        const res = await fetch('claim_order.php', { 
          method: 'POST', 
          body: formData 
        });
        
        const data = await res.json();
        
        if (data.success) {
          location.reload();
        } else {
          alert('Error: ' + (data.error || 'Unknown error'));
          btn.innerHTML = originalHTML;
          btn.disabled = false;
        }
      } catch (err) {
        console.error(err);
        alert('Connection failed: ' + err.message);
        btn.innerHTML = originalHTML;
        btn.disabled = false;
      }
    }

    async function updateOrderStatus(orderId, newStatus) {
      const confirmMsg = newStatus === 'READY' ? 
        'Mark this order as ready for delivery?' : 
        'Mark this order as delivered?';
      
      if (!confirm(confirmMsg)) return;
      
      const btn = event.target;
      const originalHTML = btn.innerHTML;
      btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating...';
      btn.disabled = true;
      
      try {
        const formData = new FormData();
        formData.append('order_id', orderId);
        formData.append('status', newStatus);

        const res = await fetch('mark_order_status.php', { 
          method: 'POST', 
          body: formData 
        });
        
        const data = await res.json();
        
        if (data.success) {
          location.reload();
        } else {
          alert('Error: ' + (data.error || 'Unknown error'));
          btn.innerHTML = originalHTML;
          btn.disabled = false;
        }
      } catch (err) {
        console.error(err);
        alert('Connection failed: ' + err.message);
        btn.innerHTML = originalHTML;
        btn.disabled = false;
      }
    }

    // ===== LOAD ORDERS FOR ACTIVITY LOG =====
    async function loadOrders() {
      const container = document.getElementById('ordersActivityList');
      
      if (!container) {
        console.error('ordersActivityList container not found!');
        return;
      }
      
      try {
        const res = await fetch('get_pending_orders.php');
        
        if (!res.ok) {
          throw new Error(`HTTP error! status: ${res.status}`);
        }
        
        const data = await res.json();
        console.log('Orders data:', data); // Debug log
        
        if (data.success && data.orders && data.orders.length > 0) {
          let html = '<ul class="activity-list">';
          data.orders.forEach(order => {
            const statusColor = order.status === 'NEW' ? '#e74c3c' : 
                              order.status === 'PREPARING' ? '#f39c12' : '#27ae60';
            const statusBg = order.status === 'NEW' ? 'linear-gradient(135deg, #e74c3c 0%, #c0392b 100%)' : 
                           order.status === 'PREPARING' ? 'linear-gradient(135deg, #f39c12 0%, #e67e22 100%)' : 
                           'linear-gradient(135deg, #27ae60 0%, #229954 100%)';
            
            html += `
              <li class="activity-item">
                <div class="activity-icon" style="background: ${statusBg};">
                  <i class="fas fa-utensils"></i>
                </div>
                <div class="activity-details" style="flex: 1;">
                  <div class="activity-title">Room ${order.room_number} - Order #${order.order_id}</div>
                  <div style="font-size: 13px; color: #666; margin: 5px 0;">${order.items}</div>
                  <div class="activity-time">
                    <i class="fas fa-clock"></i> ${order.time_ago}
                    <span style="margin-left: 15px; padding: 3px 10px; background: ${order.status === 'NEW' ? '#fee' : order.status === 'PREPARING' ? '#fff3cd' : '#d4edda'}; color: ${statusColor}; border-radius: 12px; font-size: 11px; font-weight: 700;">${order.status}</span>
                  </div>
                </div>
                <div style="text-align: right; display: flex; flex-direction: column; align-items: flex-end; justify-content: center; gap: 5px;">
                  <div style="font-size: 20px; font-weight: 700; color: #f39c12;">₱${order.total.toFixed(2)}</div>
                </div>
              </li>
            `;
          });
          html += '</ul>';
          container.innerHTML = html;
          console.log('Orders rendered successfully');
        } else {
          container.innerHTML = '<div class="empty-state"><i class="fas fa-check-circle"></i><p>No pending orders</p></div>';
          console.log('No orders found');
        }
      } catch (err) {
        console.error('Error loading orders:', err);
        container.innerHTML = `<div class="empty-state"><i class="fas fa-exclamation-triangle"></i><p>Error loading orders: ${err.message}</p></div>`;
      }
    }
    
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', loadOrders);
    } else {
      loadOrders();
    }
    
    setInterval(loadOrders, 10000);

    setInterval(() => {
      location.reload();
    }, 30000);

    <?php if ($newOrdersCount > 0): ?>
    //new Audio('assets/audio/NOTIFY_STAFF.mp4').play();
    <?php endif; ?> 
  </script>
</body>
</html> 