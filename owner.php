<?php
session_start();
require_once 'db.php';
if (!isset($_SESSION['user_id']) || intval($_SESSION['role_id']) !== 1) {
    header('Location: index.php');
    exit;
}
$ownerName = htmlspecialchars($_SESSION['display_name'] ?: $_SESSION['username']);

// Handle room add/edit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'save_room') {
        $room_id = isset($_POST['room_id']) ? intval($_POST['room_id']) : 0;
        $room_number = trim($_POST['room_number']);
        $room_type_id = intval($_POST['room_type_id']);
        // Status is NOT accepted from form - it's managed by the system only

        // validate unique room_number (exclude current id when editing)
        $dupCount = 0;
        if ($room_id > 0) {
            $chk = $mysqli->prepare("SELECT COUNT(*) FROM rooms WHERE room_number = ? AND room_id != ?");
            $chk->bind_param('si', $room_number, $room_id);
        } else {
            $chk = $mysqli->prepare("SELECT COUNT(*) FROM rooms WHERE room_number = ?");
            $chk->bind_param('s', $room_number);
        }
        $chk->execute();
        $chk->bind_result($dupCount);
        $chk->fetch();
        $chk->close();

        // If duplicate, keep form values and show error (no redirect)
        if ($dupCount > 0) {
            $error = 'Room number already exists. Choose a different number.';
            // preserve submitted values for the modal
            $preserve = [
                'room_id' => $room_id,
                'room_number' => $room_number,
                'room_type_id' => $room_type_id
            ];
        } else {
            // proceed with save (insert/update)
             if ($room_id > 0) {
                try {
                    // UPDATE: only room_number and room_type_id, NOT status
                    $stmt = $mysqli->prepare("UPDATE rooms SET room_number = ?, room_type_id = ? WHERE room_id = ?");
                    $stmt->bind_param('sii', $room_number, $room_type_id, $room_id);
                    $stmt->execute();
                    $stmt->close();
                } catch (mysqli_sql_exception $e) {
                    $error = 'Database error: ' . $e->getMessage();
                    $preserve = [
                        'room_id' => $room_id,
                        'room_number' => $room_number,
                        'room_type_id' => $room_type_id
                    ];
                }
             } else {
                try {
                    // INSERT: new rooms default to AVAILABLE status
                    $stmt = $mysqli->prepare("INSERT INTO rooms (room_number, room_type_id, status) VALUES (?, ?, 'AVAILABLE')");
                    $stmt->bind_param('si', $room_number, $room_type_id);
                    $stmt->execute();
                    $stmt->close();
                } catch (mysqli_sql_exception $e) {
                    $error = 'Database error: ' . $e->getMessage();
                    $preserve = [
                        'room_id' => $room_id,
                        'room_number' => $room_number,
                        'room_type_id' => $room_type_id
                    ];
                }
             }
            if (empty($error)) {
                header('Location: owner.php');
                exit;
            }
        }
    }
}

// Get room types for the dropdown
$roomTypes = [];
$rtResult = $mysqli->query("SELECT room_type_id, type_name, price_per_hour FROM room_types ORDER BY price_per_hour ASC");
if ($rtResult) {
    while ($rt = $rtResult->fetch_assoc()) $roomTypes[] = $rt;
    $rtResult->free();
}

$sql = "
SELECT
  r.room_id,
  r.room_number,
  r.status,
  rt.room_type_id,
  rt.type_name,
  rt.price_per_hour,
  rt.price_per_30min,
  rent.rental_id,
  rent.started_at,
  rent.total_minutes
FROM rooms r
JOIN room_types rt ON r.room_type_id = rt.room_type_id
LEFT JOIN rentals rent ON rent.room_id = r.room_id AND rent.ended_at IS NULL
ORDER BY rt.price_per_hour ASC, r.room_number ASC
";
$result = $mysqli->query($sql);
$rooms = [];
if ($result) {
    while ($row = $result->fetch_assoc()) $rooms[] = $row;
    $result->free();
}

// Get counts
$cnt = $mysqli->query("SELECT SUM(status='AVAILABLE') AS available, SUM(status='OCCUPIED') AS occupied, SUM(status='CLEANING') AS cleaning FROM rooms")->fetch_assoc();
$available = intval($cnt['available']); 
$occupied = intval($cnt['occupied']); 
$cleaning = intval($cnt['cleaning']);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Rooms — Wannabees Family KTV</title>
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
      background: #f0f0f0;
      color: #2c2c2c;
      height: 100vh;
      overflow-x: hidden;
    }
    
    /* Header - Consistent with inventory */
    header {
      background: #f5f5f5;
      padding: 10px 15px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      box-shadow: 0 2px 10px rgba(0,0,0,0.08);
      position: sticky;
      top: 0;
      z-index: 100;
      min-height: 60px;
    }
    
    .header-left {
      display: flex;
      align-items: center;
      gap: 8px;
      flex-shrink: 0;
    }
    .header-left img {
      width: 36px;
      height: 36px;
      object-fit: contain;
      display: block;
      margin-right: .1px;
      border-radius: 6px;
    }

    .header-title {
      font-size: 16px;
      font-weight: 600;
      line-height: 1.2;
    }
    
    .header-subtitle {
      font-size: 12px;
      color: #666;
      display: none;
    }
    
    .header-nav {
      display: flex;
      gap: 4px;
      flex-wrap: wrap;
      align-items: center;
    }
    .header-actions {
      display: flex;
      gap: 8px;
      align-items: center;
      margin-left: 12px;
    }
    .mobile-nav-toggle {
      display: none;
      background: none;
      border: none;
      font-size: 18px;
      cursor: pointer;
      color: #333;
    }
    
    .btn {
      padding: 7px 12px;
      border: 1px solid #ddd;
      border-radius: 4px;
      cursor: pointer;
      font-size: 12px;
      font-weight: 500;
      transition: all 0.2s ease;
      background: white;
      color: #555;
      white-space: nowrap;
      display: inline-flex;
      align-items: center;
      gap: 4px;
    }
    
    .btn i {
      font-size: 10px;
    }
    
    .btn:hover {
      background: #f8f8f8;
      border-color: #bbb;
    }
    
    .btn-primary {
      background: #f2a20a;
      color: white;
      border-color: #f2a20a;
    }
    
    .btn-primary:hover {
      background: #d89209;
      border-color: #d89209;
    }
    
    .btn-danger {
      background: white;
      color: #e74c3c;
      border-color: #e74c3c;
    }
    
    .btn-danger:hover {
      background: #fef5f5;
      border-color: #c0392b;
      color: #c0392b;
    }
    
    main {
      padding: 12px;
      height: calc(100vh - 60px);
      overflow-y: auto;
      display: flex;
      flex-direction: column;
    }
    
    .page-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 8px;
      flex-shrink: 0;
    }
    
    .page-title {
      font-size: 18px;
      font-weight: 700;
      color: #2c2c2c;
    }
    
    /* Search and Filter Bar - Consistent with inventory */
    .search-filter-bar {
      display: flex;
      gap: 8px;
      margin-bottom: 10px;
      flex-wrap: wrap;
      align-items: center;
    }
    
    .search-box {
      flex: 1;
      min-width: 200px;
      position: relative;
      display: flex;
      align-items: center;
    }
    
    .search-box i {
      position: absolute;
      left: 12px;
      color: #999;
      font-size: 12px;
      pointer-events: none;
    }
    
    .search-box input {
      width: 100%;
      padding: 8px 12px 8px 35px;
      border: 1px solid #ddd;
      border-radius: 4px;
      font-size: 13px;
      transition: all 0.2s;
    }
    
    .search-box input:focus {
      outline: none;
      border-color: #f2a20a;
      box-shadow: 0 0 0 3px rgba(242,162,10,0.1);
    }
    
    #typeFilter,
    #statusFilter {
      padding: 8px 12px;
      border: 1px solid #ddd;
      border-radius: 4px;
      font-size: 13px;
      background: white;
      color: #555;
      cursor: pointer;
      transition: all 0.2s;
      min-width: 120px;
    }
    
    #typeFilter:focus,
    #statusFilter:focus {
      outline: none;
      border-color: #f2a20a;
      box-shadow: 0 0 0 3px rgba(242,162,10,0.1);
    }
    
    .filter-actions {
      display: flex;
      gap: 4px;
    }
    
    .results-counter {
      padding: 6px 12px;
      background: #e8f5e9;
      border-radius: 4px;
      font-size: 12px;
      color: #2e7d32;
      font-weight: 500;
      display: none;
    }
    
    /* Summary Cards - Compact */
    .summary-grid {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 10px;
      margin-bottom: 15px;
      flex-shrink: 0;
    }
    
    .summary-card {
      background: white;
      padding: 12px;
      border-radius: 8px;
      box-shadow: 0 2px 8px rgba(0,0,0,0.06);
      text-align: center;
    }
    
    .summary-card.available {
      border-left: 4px solid #27ae60;
    }
    
    .summary-card.occupied {
      border-left: 4px solid #e74c3c;
    }
    
    .summary-card.cleaning {
      border-left: 4px solid #3498db;
    }
    
    .summary-label {
      font-size: 11px;
      font-weight: 600;
      margin-bottom: 5px;
      text-transform: uppercase;
    }
    
    .summary-card.available .summary-label {
      color: #27ae60;
    }
    
    .summary-card.occupied .summary-label {
      color: #e74c3c;
    }
    
    .summary-card.cleaning .summary-label {
      color: #3498db;
    }
    
    .summary-value {
      font-size: 24px;
      font-weight: 700;
    }
    
    /* Room Sections */
    .room-section {
      margin-bottom: 15px;
    }
    
    .section-title {
      font-size: 13px;
      font-weight: 600;
      margin-bottom: 8px;
      color: #555;
      padding: 5px 0;
      border-bottom: 2px solid #e0e0e0;
    }
    
    .rooms-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(110px, 1fr));
      gap: 8px;
    }
    
    /* Room Cards */
    .room-card {
      background: white;
      padding: 10px;
      border-radius: 8px;
      cursor: pointer;
      transition: all 0.2s ease;
      text-align: center;
      position: relative;
      overflow: hidden;
      min-height: 85px;
      display: flex;
      flex-direction: column;
      justify-content: center;
    }
    
    .room-card:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    }
    
    .room-card.available {
      background: #27ae60;
      color: white;
    }
    
    .room-card.occupied {
      background: #e74c3c;
      color: white;
    }
    
    .room-card.cleaning {
      background: #3498db;
      color: white;
    }
    
    .room-number {
      font-size: 16px;
      font-weight: 700;
      margin-bottom: 4px;
    }
    
    .room-status {
      font-size: 10px;
      opacity: 0.9;
      margin-bottom: 4px;
      text-transform: uppercase;
    }
    
    .room-time {
      background: rgba(255,255,255,0.2);
      padding: 4px;
      border-radius: 4px;
      font-size: 9px;
      margin-top: 4px;
      line-height: 1.3;
    }
    
    /* Modals - Consistent with inventory */
    .modal {
      display: none;
      position: fixed;
      inset: 0;
      background: rgba(0,0,0,0.6);
      z-index: 1000;
      overflow-y: auto;
      padding: 20px;
    }
    
    .modal.active {
      display: flex;
      align-items: center;
      justify-content: center;
    }
    
    .modal-content {
      background: white;
      border-radius: 12px;
      width: 100%;
      max-width: 500px;
      position: relative;
      padding: 24px;
      box-shadow: 0 20px 60px rgba(0,0,0,0.3);
      animation: modalSlideIn 0.3s ease-out;
    }
    
    @keyframes modalSlideIn {
      from {
        opacity: 0;
        transform: translateY(-30px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }
    
    .modal-close {
      position: absolute;
      top: 16px;
      right: 16px;
      background: none;
      border: none;
      font-size: 28px;
      color: #999;
      cursor: pointer;
      width: 32px;
      height: 32px;
      display: flex;
      align-items: center;
      justify-content: center;
      border-radius: 50%;
      transition: all 0.2s;
    }
    
    .modal-close:hover {
      background: #f0f0f0;
      color: #333;
    }
    
    .modal-title {
      font-size: 20px;
      font-weight: 700;
      margin-bottom: 8px;
      color: #2c2c2c;
    }
    
    .modal-subtitle {
      font-size: 13px;
      color: #666;
      margin-bottom: 20px;
    }
    
    /* Form Styling */
    .form-group {
      margin-bottom: 16px;
    }
    
    .form-group label {
      display: block;
      font-size: 13px;
      font-weight: 600;
      color: #555;
      margin-bottom: 6px;
    }
    
    .form-group input,
    .form-group select {
      width: 100%;
      padding: 10px 12px;
      border: 1px solid #ddd;
      border-radius: 6px;
      font-size: 14px;
      transition: all 0.2s;
    }
    
    .form-group input:focus,
    .form-group select:focus {
      outline: none;
      border-color: #f2a20a;
      box-shadow: 0 0 0 3px rgba(242,162,10,0.1);
    }
    
    .form-group input:disabled,
    .form-group select:disabled {
      background: #f5f5f5;
      color: #999;
      cursor: not-allowed;
    }
    
    .form-info {
      background: #e3f2fd;
      border-left: 4px solid #2196f3;
      padding: 12px;
      border-radius: 6px;
      margin-bottom: 16px;
      font-size: 13px;
      color: #1976d2;
    }
    
    .form-info i {
      margin-right: 8px;
    }
    
    .form-error {
      color: #c0392b;
      background: #fdecea;
      padding: 8px;
      border-radius: 6px;
      margin-bottom: 12px;
      font-size: 13px;
    }
    
    .modal-actions {
      display: flex;
      gap: 8px;
      justify-content: flex-end;
      margin-top: 24px;
      padding-top: 16px;
      border-top: 1px solid #eee;
    }
    
    .btn-cancel {
      padding: 10px 20px;
      background: white;
      color: #666;
      border: 1px solid #ddd;
      border-radius: 6px;
      cursor: pointer;
      font-size: 14px;
      font-weight: 500;
      transition: all 0.2s;
    }
    
    .btn-cancel:hover {
      background: #f5f5f5;
      border-color: #bbb;
    }
    
    .btn-save {
      padding: 10px 20px;
      background: #f2a20a;
      color: white;
      border: none;
      border-radius: 6px;
      cursor: pointer;
      font-size: 14px;
      font-weight: 500;
      transition: all 0.2s;
      display: inline-flex;
      align-items: center;
      gap: 6px;
    }
    
    .btn-save:hover {
      background: #d89209;
    }
    
    /* Billing Modal */
    .billing-modal {
      max-width: 600px;
    }
    
    .billing-section {
      margin-bottom: 20px;
      padding-bottom: 16px;
      border-bottom: 1px solid #eee;
    }
    
    .billing-section:last-of-type {
      border-bottom: none;
    }
    
    .billing-section h4 {
      font-size: 14px;
      font-weight: 600;
      color: #555;
      margin-bottom: 10px;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }
    
    .billing-row {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 8px 0;
      font-size: 13px;
    }
    
    .billing-label {
      color: #666;
    }
    
    .billing-value {
      font-weight: 600;
      color: #2c2c2c;
    }
    
    .item-list {
      max-height: 200px;
      overflow-y: auto;
    }
    
    .item-row {
      display: flex;
      justify-content: space-between;
      padding: 8px 0;
      font-size: 13px;
      border-bottom: 1px solid #f5f5f5;
    }
    
    .item-row:last-child {
      border-bottom: none;
    }
    
    .grand-total {
      background: linear-gradient(135deg, #f2a20a 0%, #d89209 100%);
      color: white;
      padding: 16px;
      border-radius: 8px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin: 20px 0;
    }
    
    .grand-total-label {
      font-size: 16px;
      font-weight: 600;
    }
    
    .grand-total-amount {
      font-size: 24px;
      font-weight: 700;
    }
    
    /* Empty State */
    .empty-state {
      text-align: center;
      padding: 60px 20px;
      color: #999;
    }
    
    .empty-state i {
      font-size: 64px;
      margin-bottom: 16px;
      opacity: 0.3;
    }
    
    .empty-state h3 {
      font-size: 18px;
      margin-bottom: 8px;
      color: #666;
    }
    
    .empty-state p {
      font-size: 14px;
    }
    
    /* Responsive */
    @media (max-width: 768px) {
      .header-subtitle {
        display: block;
      }
      
      .summary-grid {
        grid-template-columns: repeat(3, 1fr);
        gap: 6px;
      }
      
      .summary-card {
        padding: 8px;
      }
      
      .summary-label {
        font-size: 10px;
      }
      
      .summary-value {
        font-size: 20px;
      }
      
      .rooms-grid {
        grid-template-columns: repeat(auto-fill, minmax(90px, 1fr));
        gap: 6px;
      }
      
      .search-filter-bar {
        flex-direction: column;
        align-items: stretch;
      }
      
      .search-box {
        width: 100%;
      }
      
      #typeFilter,
      #statusFilter {
        width: 100%;
      }
      
      .filter-actions {
        width: 100%;
      }
      
      .filter-actions .btn {
        flex: 1;
      }
      .header-nav {
        display: none;
        position: absolute;
        top: 100%;
        right: 15px;
        background: white;
        box-shadow: 0 4px 15px rgba(0,0,0,0.15);
        border-radius: 8px;
        padding: 10px;
        flex-direction: column;
        gap: 8px;
        min-width: 200px;
      }
      .header-nav.active {
        display: flex;
      }
      .mobile-nav-toggle {
        display: block;
      }
      .btn {
        width: 100%;
        justify-content: flex-start;
        padding: 10px 15px;
        font-size: 14px;
      }
      .btn i {
        font-size: 14px;
      }
      .header-nav .logout-form {
        display: block;
        width: 100%;
      }
      .header-nav .logout-form .btn {
        width: 100%;
        padding: 10px 15px;
        font-size: 14px;
      }
      /* keep top action visible on mobile, but compact */
      .header-actions .btn {
        padding: 8px 10px;
        font-size: 13px;
      }

    }
  </style>
</head>
<body>
  <header>
    <div class="header-left">
      <img src="assets/images/KTVL.png" alt="Wannabees KTV" onerror="this.style.display='none'">
      <div>
        <div class="header-title">Wannabees Family KTV</div>
      </div>
    </div>
    <button class="mobile-nav-toggle" onclick="toggleMobileNav()">
      <i class="fas fa-bars"></i>
    </button>
    
    <div class="header-nav" id="headerNav">
      <button class="btn btn-primary"><i class="fas fa-box"></i> <span>Rooms</span></button>

      <button class="btn" onclick="location.href='inventory.php'">
        <i class="fas fa-box"></i> Inventory 
      </button>
      
      <button class="btn" onclick="location.href='sales_report.php'"><i class="fas fa-dollar-sign"></i> Sales</button>
      <button class="btn" onclick="location.href='pricing.php'"><i class="fas fa-tag"></i> Pricing</button>
      <button class="btn" onclick="location.href='users.php'"><i class="fas fa-users"></i> Users</button>
      <button class="btn" onclick="location.href='guide.php'"><i class="fas fa-book"></i> Guide</button>
      <form method="post" action="logout.php" class="logout-form">
        <button type="button" class="btn btn-danger" onclick="logoutNow(this)">
          <i class="fas fa-sign-out-alt"></i> Logout
        </button>
      </form>
    </div>
  </header>

  <main>
    <div class="page-header">
      <h1 class="page-title">Room Manage</h1>
      <button class="btn btn-primary" onclick="openRoomModal()">
        <i class="fas fa-plus"></i> Add Room
      </button>
    </div>
    
    <!-- Search and Filter Bar -->
    <div class="search-filter-bar">
      <div class="search-box">
        <i class="fas fa-search"></i>
        <input type="text" id="searchInput" placeholder="Search rooms by number..." oninput="filterRooms()">
      </div>
      
      <select id="typeFilter" onchange="filterRooms()">
        <option value="">All Types</option>
        <?php foreach ($roomTypes as $rt): ?>
          <option value="<?= htmlspecialchars($rt['type_name']) ?>">
            <?= htmlspecialchars($rt['type_name']) ?> (₱<?= number_format($rt['price_per_hour'], 0) ?>/hr)
          </option>
        <?php endforeach; ?>
      </select>
      
      <select id="statusFilter" onchange="filterRooms()">
        <option value="">All Statuses</option>
        <option value="AVAILABLE">Available</option>
        <option value="OCCUPIED">Occupied</option>
        <option value="CLEANING">Cleaning</option>
      </select>
      
      <div class="filter-actions">
        <button class="btn" onclick="clearFilters()">
          <i class="fas fa-times"></i> Clear
        </button>
      </div>
      
      <div class="results-counter" id="resultsCounter">
        Showing <strong id="resultsCount">0</strong> rooms
      </div>
    </div>
    
    <!-- Summary Cards -->
    <div class="summary-grid">
      <div class="summary-card available">
        <div class="summary-label">Available</div>
        <div class="summary-value"><?= $available ?></div>
      </div>
      <div class="summary-card occupied">
        <div class="summary-label">Occupied</div>
        <div class="summary-value"><?= $occupied ?></div>
      </div>
      <div class="summary-card cleaning">
        <div class="summary-label">Cleaning</div>
        <div class="summary-value"><?= $cleaning ?></div>
      </div>
    </div>

    <!-- All Rooms Grid -->
    <div class="room-section">
      <div class="section-title">All Rooms</div>
      <div class="rooms-grid" id="roomsGrid">
        <?php foreach ($rooms as $r):
          $statusClass = strtolower($r['status']);
        ?>
          <div class="room-card <?= $statusClass ?>" 
               data-room='<?= json_encode($r) ?>'
               onclick="handleRoomClick(this)">
            <div class="room-number">R<?= $r['room_number'] ?></div>
            <div class="room-status"><?= ucfirst($r['status']) ?></div>
            <?php if ($r['rental_id']): ?>
              <div class="room-time" data-started="<?= $r['started_at'] ?>">⏱️ 00:00:00<br><?= $r['total_minutes'] ?>m</div>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </main>

  <!-- Room Modal (Add/Edit) -->
  <div id="roomModal" class="modal">
    <div class="modal-content">
      <button class="modal-close" onclick="closeRoomModal()">×</button>
      <h3 class="modal-title" id="roomModalTitle">Add Room</h3>
      <div class="modal-subtitle">Configure room details</div>
      
      <form method="post">
        <input type="hidden" name="action" value="save_room">
        <input type="hidden" name="room_id" id="room_id" value="<?= isset($preserve['room_id']) ? intval($preserve['room_id']) : '' ?>">
        <?php if (!empty($error)): ?>
          <div class="form-error">
            <?= htmlspecialchars($error) ?>
          </div>
        <?php endif; ?>
        
        <div class="form-group">
          <label for="room_number">Room Number</label>
          <input type="text" id="room_number" name="room_number" required placeholder="e.g., 101" value="<?= isset($preserve['room_number']) ? htmlspecialchars($preserve['room_number']) : '' ?>">
        </div>
        
        <div class="form-group">
          <label for="room_type_id">Room Type</label>
          <select id="room_type_id" name="room_type_id" required>
            <option value="">-- Select Room Type --</option>
            <?php foreach ($roomTypes as $rt): ?>
              <option value="<?= $rt['room_type_id'] ?>" <?= (isset($preserve['room_type_id']) && intval($preserve['room_type_id']) === intval($rt['room_type_id'])) ? 'selected' : '' ?>>
                <?= htmlspecialchars($rt['type_name']) ?> (₱<?= number_format($rt['price_per_hour'], 0) ?>/hr)
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        
        <div class="form-info" id="statusInfo" style="display: none;">
          <i class="fas fa-info-circle"></i>
          Room status is automatically managed by the system based on rental activity.
        </div>
        
        <div class="modal-actions">
          <button type="button" class="btn-cancel" onclick="closeRoomModal()">Cancel</button>
          <button type="submit" class="btn-save">
            <i class="fas fa-save"></i> Save Room
          </button>
        </div>
      </form>
    </div>
  </div>

  <!-- Billing Modal -->
  <div id="billingModal" class="modal">
    <div class="modal-content billing-modal">
      <button class="modal-close" onclick="closeBillingModal()">×</button>
      <h3 class="modal-title">Billing - <span id="billRoomNumber"></span></h3>
      <div class="modal-subtitle" id="billStartTime"></div>
      <div class="modal-subtitle" style="color: #f2a20a; font-weight: 600;">👁️ View Only - For monitoring purposes</div>
      
      <div class="billing-section">
        <h4>Room Rental</h4>
        <div id="roomRentalDetails"></div>
      </div>
      
      <div class="billing-section">
        <h4>Time Extensions</h4>
        <div id="extensionDetails"></div>
      </div>
      
      <div class="billing-section">
        <h4>Orders</h4>
        <div id="orderDetails" class="item-list"></div>
      </div>
      
      <div class="grand-total">
        <div class="grand-total-label">Grand Total:</div>
        <div class="grand-total-amount" id="grandTotal">₱0.00</div>
      </div>
      
      <div class="modal-actions">
        <button class="btn-cancel" onclick="closeBillingModal()">Close</button>
      </div>
    </div>
  </div>

  <script>
    let currentRoom = null;
    let currentRoomEl = null;
    const roomTimers = {};
    let currentBillData = null;
    
    // Room Modal Functions
    function openRoomModal() {
      document.getElementById('roomModalTitle').textContent = 'Add Room';
      document.getElementById('room_id').value = '';
      document.getElementById('room_number').value = '';
      document.getElementById('room_type_id').value = '';
      document.getElementById('statusInfo').style.display = 'none';
      document.getElementById('roomModal').classList.add('active');
      document.body.style.overflow = 'hidden';
    }
    
    function openEditRoomModal(room) {
      document.getElementById('roomModalTitle').textContent = 'Edit Room';
      document.getElementById('room_id').value = room.room_id;
      document.getElementById('room_number').value = room.room_number;
      document.getElementById('room_type_id').value = room.room_type_id;
      document.getElementById('statusInfo').style.display = 'block';
      document.getElementById('roomModal').classList.add('active');
      document.body.style.overflow = 'hidden';
    }
    
    function closeRoomModal() {
      document.getElementById('roomModal').classList.remove('active');
      document.body.style.overflow = 'auto';
    }
    
    function handleRoomClick(el) {
      const room = JSON.parse(el.dataset.room);
      currentRoom = room;
      currentRoomEl = el;
      
      if (room.status === 'OCCUPIED') {
        openBillingModal();
      } else {
        // Show options: View Details or Edit
        if (confirm('Room ' + room.room_number + ' - ' + room.type_name + '\nStatus: ' + room.status + '\n\nClick OK to edit this room, or Cancel to close.')) {
          openEditRoomModal(room);
        }
      }
    }
    
    async function openBillingModal() {
      const modal = document.getElementById('billingModal');
      document.getElementById('billRoomNumber').textContent = `Room ${currentRoom.room_number}`;
      document.getElementById('billStartTime').textContent = `Started: ${currentRoom.started_at || 'N/A'}`;
      
      try {
        const res = await fetch(`get_bill.php?rental_id=${currentRoom.rental_id}`);
        const data = await res.json();
        
        if (data.success) {
          currentBillData = data;
          renderBillDetails(data);
          modal.classList.add('active');
        } else {
          alert('Error loading bill: ' + (data.error || 'Unknown'));
        }
      } catch (err) {
        alert('Network error: ' + err.message);
      }
    }
    
    function renderBillDetails(data) {
      const { bill, rental, orders, extensions } = data;
      
      const roomDetails = document.getElementById('roomRentalDetails');
      roomDetails.innerHTML = `
        <div class="billing-row">
          <span class="billing-label">Room Type:</span>
          <span class="billing-value">${rental.type_name}</span>
        </div>
        <div class="billing-row">
          <span class="billing-label">Duration:</span>
          <span class="billing-value">${rental.total_minutes} minutes</span>
        </div>
        <div class="billing-row">
          <span class="billing-label">Room Cost:</span>
          <span class="billing-value">₱${parseFloat(bill.total_room_cost).toFixed(2)}</span>
        </div>
      `;
      
      const extDetails = document.getElementById('extensionDetails');
      if (extensions.length > 0) {
        extDetails.innerHTML = extensions.map(ext => `
          <div class="billing-row">
            <span class="billing-label">${ext.minutes_added} minutes</span>
            <span class="billing-value">₱${parseFloat(ext.cost).toFixed(2)}</span>
          </div>
        `).join('');
      } else {
        extDetails.innerHTML = '<div class="billing-row"><span class="billing-label">No extensions</span></div>';
      }
      
      const orderDetails = document.getElementById('orderDetails');
      if (orders.length > 0) {
        orderDetails.innerHTML = orders.map(item => `
          <div class="item-row">
            <span>${item.product_name} x${item.quantity}</span>
            <span>₱${(parseFloat(item.price) * parseInt(item.quantity)).toFixed(2)}</span>
          </div>
        `).join('');
      } else {
        orderDetails.innerHTML = '<div class="billing-row"><span class="billing-label">No orders</span></div>';
      }
      
      document.getElementById('grandTotal').textContent = `₱${parseFloat(bill.grand_total).toFixed(2)}`;
    }
    
    function closeBillingModal() {
      document.getElementById('billingModal').classList.remove('active');
    }
    
    function formatElapsed(seconds) {
      const h = Math.floor(seconds / 3600);
      const m = Math.floor((seconds % 3600) / 60);
      const s = seconds % 60;
      return `${String(h).padStart(2,'0')}:${String(m).padStart(2,'0')}:${String(s).padStart(2,'0')}`;
    }

    function startTimerForCard(cardEl) {
      try {
        const timeEl = cardEl.querySelector('.room-time');
        if (!timeEl) return;
        
        const startedAtStr = timeEl.dataset.started;
        if (!startedAtStr) return;
        
        const room = JSON.parse(cardEl.dataset.room);
        const roomId = room.room_id;
        
        if (roomTimers[roomId]) {
          clearInterval(roomTimers[roomId]);
        }
        
        function tick() {
          try {
            const parts = startedAtStr.split(' ');
            const dateParts = parts[0].split('-');
            const timeParts = parts[1].split(':');
            
            const startDate = new Date(
              parseInt(dateParts[0]),
              parseInt(dateParts[1]) - 1,
              parseInt(dateParts[2]),
              parseInt(timeParts[0]),
              parseInt(timeParts[1]),
              parseInt(timeParts[2])
            );
            
            const now = new Date();
            const elapsed = Math.floor((now - startDate) / 1000);
            
            if (elapsed >= 0) {
              timeEl.innerHTML = `⏱️ ${formatElapsed(elapsed)}<br>${room.total_minutes || ''}m`;
            }
          } catch (e) {
            console.error('Timer tick error:', e);
          }
        }
        
        tick();
        roomTimers[roomId] = setInterval(tick, 1000);
      } catch (e) {
        console.error('Timer start error:', e);
      }
    }

    function startAllTimers() {
      document.querySelectorAll('.room-card.occupied .room-time').forEach(timeEl => {
        const cardEl = timeEl.closest('.room-card');
        if (cardEl) {
          startTimerForCard(cardEl);
        }
      });
    }

    // Filter Functions
    function clearFilters() {
      document.getElementById('searchInput').value = '';
      document.getElementById('typeFilter').value = '';
      document.getElementById('statusFilter').value = '';
      filterRooms();
    }
    
    function filterRooms() {
      const searchInput = document.getElementById('searchInput').value.toLowerCase();
      const typeFilter = document.getElementById('typeFilter').value.toLowerCase();
      const statusFilter = document.getElementById('statusFilter').value.toLowerCase();
      
      const roomCards = document.querySelectorAll('.room-card');
      let visibleCount = 0;
      
      roomCards.forEach(card => {
        const room = JSON.parse(card.dataset.room);
        const roomNumber = room.room_number.toLowerCase();
        const roomType = room.type_name.toLowerCase();
        const roomStatus = room.status.toLowerCase();
        
        // Check search match
        const matchesSearch = roomNumber.includes(searchInput);
        
        // Check type match
        const matchesType = !typeFilter || roomType.includes(typeFilter);
        
        // Check status match
        const matchesStatus = !statusFilter || roomStatus === statusFilter;
        
        // Show or hide card
        if (matchesSearch && matchesType && matchesStatus) {
          card.style.display = '';
          visibleCount++;
        } else {
          card.style.display = 'none';
        }
      });
      
      // Update results counter
      const resultsCounter = document.getElementById('resultsCounter');
      const resultsCount = document.getElementById('resultsCount');
      const isFiltering = searchInput || typeFilter || statusFilter;
      
      if (isFiltering) {
        resultsCounter.style.display = 'block';
        resultsCount.textContent = visibleCount;
      } else {
        resultsCounter.style.display = 'none';
      }
    }

    // Close modals on outside click
    document.getElementById('roomModal').addEventListener('click', function(e) {
      if (e.target === this) closeRoomModal();
    });
    
    document.getElementById('billingModal').addEventListener('click', function(e) {
      if (e.target === this) closeBillingModal();
    });
    
    // Close modals on ESC key
    document.addEventListener('keydown', function(e) {
      if (e.key === 'Escape') {
        closeRoomModal();
        closeBillingModal();
      }
    });

    startAllTimers();
    
    setInterval(() => {
      location.reload();  
    }, 30000);

    function logoutNow(el) {
      const form = el && el.closest('form');
      if (navigator.sendBeacon) {
        navigator.sendBeacon('logout.php', new Blob([], { type: 'text/plain' }));
        window.location = 'index.php';
        return;
      }
      if (form) form.submit();
      else {
        const f = document.createElement('form');
        f.method = 'post';
        f.action = 'logout.php';
        document.body.appendChild(f);
        f.submit();
      }
    }
    
    function toggleMobileNav() {
      const nav = document.getElementById('headerNav');
      nav.classList.toggle('active');
    }
  </script>

  <?php if (!empty($error)): ?>
  <script>
    document.addEventListener('DOMContentLoaded', function(){
      document.getElementById('roomModal').classList.add('active');
      document.body.style.overflow = 'hidden';
      const title = document.getElementById('roomModalTitle');
      title.textContent = <?= isset($preserve['room_id']) && $preserve['room_id']>0 ? "'Edit Room'" : "'Add Room'" ?>;
      if (<?= isset($preserve['room_id']) && $preserve['room_id']>0 ? 'true' : 'false' ?>) {
        document.getElementById('statusInfo').style.display = 'block';
      }
    });
  </script>
  <?php endif; ?>
</body>
</html>