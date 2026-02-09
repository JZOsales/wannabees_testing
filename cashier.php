<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id']) || intval($_SESSION['role_id']) !== 3) {
    header('Location: index.php');
    exit;
}

$cashierName = htmlspecialchars($_SESSION['display_name'] ?: $_SESSION['username']);

$sql = "
SELECT
  r.room_id,
  r.room_number,
  r.status,
  rt.type_name,
  rt.price_per_hour,
  rt.price_per_30min,
  rent.rental_id,
  rent.started_at,
  rent.total_minutes,
  b.bill_id,
  b.total_room_cost,
  b.total_orders_cost,
  b.grand_total,
  b.is_paid
FROM rooms r
JOIN room_types rt ON r.room_type_id = rt.room_type_id
LEFT JOIN rentals rent ON rent.room_id = r.room_id AND rent.ended_at IS NULL
LEFT JOIN bills b ON b.rental_id = rent.rental_id
ORDER BY rt.price_per_hour ASC, r.room_number ASC";
$result = $mysqli->query($sql);
$rooms = [];
if ($result) {
    while ($row = $result->fetch_assoc()) $rooms[] = $row;
    $result->free();
}

$cnt = $mysqli->query("SELECT SUM(status='AVAILABLE') AS available, SUM(status='OCCUPIED') AS occupied, SUM(status='CLEANING') AS cleaning FROM rooms")->fetch_assoc();
$available = intval($cnt['available']); 
$occupied = intval($cnt['occupied']); 
$cleaning = intval($cnt['cleaning']);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Cashier — Wannabees Family KTV</title>
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
      background: #f8f9fa;
      color: #212529;
      line-height: 1.5;
    }
    
    /* Header */
    header {
      background: #ffffff;
      padding: 1rem 1.5rem;
      border-bottom: 1px solid #e9ecef;
      position: sticky;
      top: 0;
      z-index: 100;
    }
    
    .header-container {
      max-width: 1400px;
      margin: 0 auto;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 1rem;
    }
    
    .header-left {
      display: flex;
      align-items: center;
      gap: 1rem;
      flex: 1;
      min-width: 0;
    }
    
    .header-left img {
      height: 40px;
      flex-shrink: 0;
    }
    
    .header-info {
      min-width: 0;
    }
    
    .header-title {
      font-size: 1.125rem;
      font-weight: 600;
      color: #212529;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }
    
    .header-subtitle {
      font-size: 0.875rem;
      color: #6c757d;
    }
    
    .header-nav {
      display: flex;
      gap: 0.5rem;
      align-items: center;
      flex-shrink: 0;
    }
    
    .nav-btn {
      padding: 0.5rem 1rem;
      border: 1px solid #dee2e6;
      border-radius: 6px;
      background: #ffffff;
      cursor: pointer;
      font-size: 0.875rem;
      font-weight: 500;
      transition: all 0.2s;
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
      color: #495057;
      text-decoration: none;
      white-space: nowrap;
    }
    
    .nav-btn:hover {
      background: #f8f9fa;
      border-color: #adb5bd;
    }
    
    .nav-btn.active {
      background: #f5c542;
      color: #2c2c2c;
      border-color: #f5c542;
    }
    
    .nav-btn.logout {
      border-color: #dc3545;
      color: #dc3545;
    }
    
    .nav-btn.logout:hover {
      background: #dc3545;
      color: #ffffff;
    }
    
    main {
      padding: 1.5rem;
      max-width: 1400px;
      margin: 0 auto;
    }
    
    .summary-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 1rem;
      margin-bottom: 2rem;
    }
    
    .summary-card {
      background: #ffffff;
      padding: 1.5rem;
      border-radius: 8px;
      border: 1px solid #e9ecef;
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 0.5rem;
    }
    
    .summary-label {
      font-size: 0.875rem;
      font-weight: 500;
      color: #6c757d;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }
    
    .summary-value {
      font-size: 2.5rem;
      font-weight: 700;
      color: #212529;
    }
    
    .summary-card.available .summary-value {
      color: #198754;
    }
    
    .summary-card.occupied .summary-value {
      color: #dc3545;
    }
    
    .summary-card.cleaning .summary-value {
      color: #0d6efd;
    }
    
    .room-section {
      margin-bottom: 2rem;
    }
    
    .section-title {
      font-size: 1rem;
      font-weight: 600;
      margin-bottom: 1rem;
      color: #212529;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }
    
    .rooms-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
      gap: 1rem;
    }
    
    .room-card {
      background: #ffffff;
      padding: 1.5rem 1rem;
      border-radius: 8px;
      border: 2px solid #e9ecef;
      cursor: pointer;
      transition: all 0.2s;
      text-align: center;
      display: flex;
      flex-direction: column;
      gap: 0.5rem;
    }
    
    .room-card:hover {
      border-color: #adb5bd;
      box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    }
    
    .room-card.available {
      border-color: #198754;
      background: #f8fff9;
    }
    
    .room-card.available:hover {
      background: #eafff0;
    }
    
    .room-card.occupied {
      border-color: #dc3545;
      background: #fff8f8;
    }
    
    .room-card.occupied:hover {
      background: #fff0f0;
    }
    
    .room-card.cleaning {
      border-color: #0d6efd;
      background: #f8fbff;
    }
    
    .room-card.cleaning:hover {
      background: #e7f3ff;
    }
    
    .room-number {
      font-size: 1.5rem;
      font-weight: 700;
      color: #212529;
    }
    
    .room-type {
      font-size: 0.75rem;
      color: #6c757d;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }
    
    .room-status-text {
      font-size: 0.75rem;
      font-weight: 600;
      padding: 0.25rem 0.5rem;
      border-radius: 4px;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }
    
    .room-card.available .room-status-text {
      background: #d1f4e0;
      color: #0d5c2f;
    }
    
    .room-card.occupied .room-status-text {
      background: #ffd9dd;
      color: #841f2b;
    }
    
    .room-card.cleaning .room-status-text {
      background: #cfe2ff;
      color: #084298;
    }
    
    .room-time {
      font-size: 0.875rem;
      font-weight: 600;
      color: #dc3545;
      font-variant-numeric: tabular-nums;
    }
    
    /* Modal Overlay */
    .modal-overlay {
      display: none;
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: rgba(0, 0, 0, 0.5);
      z-index: 1000;
      align-items: center;
      justify-content: center;
      padding: 1rem;
    }
    
    .modal-overlay.active {
      display: flex;
    }
    
    .modal-box {
      background: #ffffff;
      border-radius: 8px;
      max-width: 500px;
      width: 100%;
      max-height: 90vh;
      overflow-y: auto;
      box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
    }
    
    .modal-header {
      padding: 1.5rem;
      border-bottom: 1px solid #e9ecef;
    }
    
    .modal-title {
      font-size: 1.25rem;
      font-weight: 600;
      color: #212529;
      margin-bottom: 0.25rem;
    }
    
    .modal-subtitle {
      font-size: 0.875rem;
      color: #6c757d;
    }
    
    .modal-content {
      padding: 1.5rem;
    }
    
    .modal-section {
      margin-bottom: 1.5rem;
    }
    
    .modal-section:last-child {
      margin-bottom: 0;
    }
    
    .modal-section-title {
      font-size: 0.875rem;
      font-weight: 600;
      color: #212529;
      margin-bottom: 0.75rem;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }
    
    .duration-grid {
      display: grid;
      grid-template-columns: repeat(2, 1fr);
      gap: 0.75rem;
    }
    
    .duration-option {
      padding: 1rem;
      border: 2px solid #e9ecef;
      border-radius: 6px;
      cursor: pointer;
      transition: all 0.2s;
      text-align: center;
    }
    
    .duration-option:hover {
      border-color: #adb5bd;
    }
    
    .duration-option.selected {
      border-color: #f5c542;
      background: #fffbf0;
    }
    
    .duration-time {
      font-size: 0.875rem;
      font-weight: 600;
      color: #212529;
      margin-bottom: 0.25rem;
    }
    
    .duration-price {
      font-size: 1rem;
      font-weight: 700;
      color: #495057;
    }
    
    .total-row {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 1rem;
      background: #f8f9fa;
      border-radius: 6px;
      margin-bottom: 0.75rem;
    }
    
    .total-label {
      font-size: 0.875rem;
      font-weight: 600;
      color: #6c757d;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }
    
    .total-value {
      font-size: 1.5rem;
      font-weight: 700;
      color: #212529;
    }
    
    .modal-note {
      font-size: 0.75rem;
      color: #6c757d;
      text-align: center;
      font-style: italic;
    }
    
    .modal-actions {
      padding: 1.5rem;
      border-top: 1px solid #e9ecef;
      display: flex;
      gap: 0.75rem;
    }
    
    .btn-modal {
      flex: 1;
      padding: 0.75rem;
      border: none;
      border-radius: 6px;
      cursor: pointer;
      font-size: 0.875rem;
      font-weight: 600;
      transition: all 0.2s;
    }
    
    .btn-close {
      background: #f8f9fa;
      color: #495057;
      border: 1px solid #dee2e6;
    }
    
    .btn-close:hover {
      background: #e9ecef;
    }
    
    .btn-start, .btn-pay {
      background: #f5c542;
      color: #2c2c2c;
      font-weight: 600;
    }
    
    .btn-start:hover, .btn-pay:hover {
      background: #f2a20a;
    }
    
    .btn-modal:disabled {
      opacity: 0.5;
      cursor: not-allowed;
    }
    
    /* Billing Modal Specific */
    .bill-section {
      margin-bottom: 1.5rem;
      padding-bottom: 1.5rem;
      border-bottom: 1px solid #e9ecef;
    }
    
    .bill-section:last-child {
      border-bottom: none;
      margin-bottom: 0;
      padding-bottom: 0;
    }
    
    .bill-section-title {
      font-size: 0.875rem;
      font-weight: 600;
      color: #212529;
      margin-bottom: 0.75rem;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }
    
    .bill-row {
      display: flex;
      justify-content: space-between;
      padding: 0.5rem 0;
      font-size: 0.875rem;
    }
    
    .bill-label {
      color: #6c757d;
    }
    
    .bill-value {
      font-weight: 600;
      color: #212529;
    }
    
    .order-item {
      display: flex;
      justify-content: space-between;
      padding: 0.5rem 0;
      font-size: 0.875rem;
    }
    
    .grand-total-box {
      background: #fffbf0;
      padding: 1rem;
      border-radius: 6px;
      border: 2px solid #f5c542;
      margin-bottom: 1.5rem;
    }
    
    .grand-total-row {
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
    
    .grand-total-label {
      font-size: 0.875rem;
      font-weight: 600;
      color: #6c757d;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }
    
    .grand-total-amount {
      font-size: 1.75rem;
      font-weight: 700;
      color: #212529;
    }
    
    .payment-method-section {
      margin-bottom: 1.5rem;
    }
    
    .payment-method-title {
      font-size: 0.875rem;
      font-weight: 600;
      color: #212529;
      margin-bottom: 0.75rem;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }
    
    .payment-methods {
      display: grid;
      grid-template-columns: repeat(2, 1fr);
      gap: 0.75rem;
    }
    
    .payment-btn {
      padding: 0.75rem;
      border: 2px solid #e9ecef;
      border-radius: 6px;
      background: #ffffff;
      cursor: pointer;
      font-size: 0.875rem;
      font-weight: 600;
      transition: all 0.2s;
      color: #495057;
    }
    
    .payment-btn:hover {
      border-color: #adb5bd;
    }
    
    .payment-btn.selected {
      border-color: #f5c542;
      background: #f5c542;
      color: #2c2c2c;
    }
    
    /* Responsive */
    @media (max-width: 768px) {
      header {
        padding: 0.75rem 1rem;
      }
      
      .header-container {
        flex-wrap: wrap;
      }
      
      .header-title {
        font-size: 1rem;
      }
      
      .header-subtitle {
        font-size: 0.75rem;
      }
      
      .header-nav {
        width: 100%;
        justify-content: flex-end;
        margin-top: 0.5rem;
      }
      
      .nav-btn {
        font-size: 0.75rem;
        padding: 0.5rem 0.75rem;
      }
      
      .nav-btn span {
        display: none;
      }
      
      main {
        padding: 1rem;
      }
      
      .summary-grid {
        grid-template-columns: repeat(3, 1fr);
        gap: 0.75rem;
      }
      
      .summary-card {
        padding: 1rem;
      }
      
      .summary-label {
        font-size: 0.625rem;
      }
      
      .summary-value {
        font-size: 1.75rem;
      }
      
      .rooms-grid {
        grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
        gap: 0.75rem;
      }
      
      .room-card {
        padding: 1rem 0.75rem;
      }
      
      .room-number {
        font-size: 1.25rem;
      }
      
      .duration-grid {
        grid-template-columns: 1fr;
      }
      
      .modal-box {
        max-width: 100%;
      }
    }
    
    @media (max-width: 480px) {
      .summary-grid {
        grid-template-columns: 1fr;
      }
      
      .payment-methods {
        grid-template-columns: 1fr;
      }
    }
  </style>
</head>
<body>
  <header>
    <div class="header-container">
      <div class="header-left">
        <img src="logo.png" alt="Logo" onerror="this.style.display='none'">
        <div class="header-info">
          <div class="header-title">Wannabees Family KTV</div>
          <div class="header-subtitle">Cashier: <?= $cashierName ?></div>
        </div>
      </div>
      <nav class="header-nav">
        <a href="cashier.php" class="nav-btn active">
          <i class="fas fa-door-open"></i>
          <span>Rooms</span>
        </a>
        <a href="sales_report_cashier.php" class="nav-btn">
          <i class="fas fa-chart-line"></i>
          <span>Sales</span>
        </a>
        <a href="guide_cashier.php" class="nav-btn">
          <i class="fas fa-book"></i>
          <span>Guide</span>
        </a>
        <a href="logout.php" class="nav-btn logout">
          <i class="fas fa-sign-out-alt"></i>
          <span>Logout</span>
        </a>
      </nav>
    </div>
  </header>

  <main>
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

    <?php
    $byType = [];
    foreach ($rooms as $r) {
      $byType[$r['type_name']][] = $r;
    }
    foreach ($byType as $typeName => $typeRooms):
    ?>
      <div class="room-section">
        <div class="section-title"><?= htmlspecialchars($typeName) ?></div>
        <div class="rooms-grid">
          <?php foreach ($typeRooms as $room): ?>
            <div class="room-card <?= strtolower($room['status']) ?>" 
                 onclick="handleRoomClick(this)"
                 data-room='<?= json_encode($room, JSON_HEX_APOS | JSON_HEX_QUOT) ?>'>
              <div class="room-number"><?= htmlspecialchars($room['room_number']) ?></div>
              <div class="room-type"><?= htmlspecialchars($room['type_name']) ?></div>
              <div class="room-status-text"><?= htmlspecialchars($room['status']) ?></div>
              <?php if ($room['status'] === 'OCCUPIED' && $room['started_at']): ?>
                <div class="room-time" data-started="<?= htmlspecialchars($room['started_at']) ?>">00:00:00</div>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </main>

  <!-- Start Rental Modal -->
  <div id="startRentalModal" class="modal-overlay">
    <div class="modal-box">
      <div class="modal-header">
        <div class="modal-title" id="startRoomInfo">Room</div>
        <div class="modal-subtitle">Select rental duration</div>
      </div>
      <div class="modal-content">
        <div class="modal-section">
          <div class="modal-section-title">Duration</div>
          <div id="durationGrid" class="duration-grid"></div>
        </div>
        <div class="total-row">
          <span class="total-label">Total Amount</span>
          <span class="total-value" id="startTotalAmount">₱0.00</span>
        </div>
        <div class="modal-note">
          Customer can extend time using the tablet inside the room
        </div>
      </div>
      <div class="modal-actions">
        <button class="btn-modal btn-close" onclick="closeStartModal()">Cancel</button>
        <button class="btn-modal btn-start" onclick="confirmStartRental()">Start Rental</button>
      </div>
    </div>
  </div>

  <!-- Billing Modal -->
  <div id="billingModal" class="modal-overlay">
    <div class="modal-box">
      <div class="modal-header">
        <div class="modal-title" id="billRoomName">Room</div>
        <div class="modal-subtitle" id="billStartTime">Started:</div>
      </div>
      <div class="modal-content" id="billContent">
        <p style="text-align:center;color:#6c757d;">Loading...</p>
      </div>
      <div class="modal-actions">
        <button class="btn-modal btn-close" onclick="closeBillingModal()">Close</button>
        <button class="btn-modal btn-pay" onclick="confirmPayment()">Continue to Billing</button>
      </div>
    </div>
  </div>

  <script>
    let currentRoom = null;
    let selectedPaymentMethod = 'CASH';
    let selectedDuration = 60;
    let currentBillData = null;
    
    function handleRoomClick(el) {
      const room = JSON.parse(el.dataset.room);
      currentRoom = room;
      
      if (room.status === 'AVAILABLE') {
        openStartModal();
      } else if (room.status === 'OCCUPIED') {
        openBillingModal();
      }
    }
    
    function openStartModal() {
      document.getElementById('startRoomInfo').textContent = `Room ${currentRoom.room_number} - ${currentRoom.type_name}`;
      
      const price30 = parseFloat(currentRoom.price_per_30min) || (parseFloat(currentRoom.price_per_hour) / 2);
      const durations = [
        { minutes: 30, label: '30 Minutes', price: price30 },
        { minutes: 60, label: '1 Hour', price: price30 * 2 },
        { minutes: 120, label: '2 Hours', price: price30 * 4 },
        { minutes: 180, label: '3 Hours', price: price30 * 6 }
      ];
      
      const grid = document.getElementById('durationGrid');
      grid.innerHTML = '';
      durations.forEach(d => {
        const div = document.createElement('div');
        div.className = 'duration-option' + (d.minutes === 60 ? ' selected' : '');
        div.innerHTML = `<div class="duration-time">${d.label}</div><div class="duration-price">₱${d.price.toFixed(2)}</div>`;
        div.onclick = () => selectDuration(d.minutes, price30);
        grid.appendChild(div);
      });
      
      selectedDuration = 60;
      updateStartTotal(price30);
      document.getElementById('startRentalModal').classList.add('active');
    }
    
    function closeStartModal() {
      document.getElementById('startRentalModal').classList.remove('active');
    }
    
    function selectDuration(minutes, price30) {
      selectedDuration = minutes;
      document.querySelectorAll('.duration-option').forEach(d => d.classList.remove('selected'));
      event.currentTarget.classList.add('selected');
      updateStartTotal(price30);
    }
    
    function updateStartTotal(price30) {
      const amount = price30 * (selectedDuration / 30);
      document.getElementById('startTotalAmount').textContent = `₱${amount.toFixed(2)}`;
    }
    
    async function confirmStartRental() {
      const btn = event.target;
      btn.textContent = 'Starting...';
      btn.disabled = true;
      
      try {
        const formData = new FormData();
        formData.append('room_id', currentRoom.room_id);
        formData.append('minutes', selectedDuration);
        
        const res = await fetch('start_rental.php', {
          method: 'POST',
          body: formData
        });
        
        const data = await res.json();
        
        if (data.success) {
          closeStartModal();
          location.reload();
        } else {
          alert('Error: ' + (data.error || 'Unknown error'));
          btn.textContent = 'Start Rental';
          btn.disabled = false;
        }
      } catch (err) {
        alert('Network error: ' + err.message);
        btn.textContent = 'Start Rental';
        btn.disabled = false;
      }
    }
    
    async function openBillingModal() {
      document.getElementById('billRoomName').textContent = `Room ${currentRoom.room_number}`;
      document.getElementById('billStartTime').textContent = `Started: ${new Date(currentRoom.started_at).toLocaleTimeString('en-US', {hour: 'numeric', minute: '2-digit', hour12: true})}`;
      document.getElementById('billingModal').classList.add('active');
      
      try {
        const res = await fetch('get_bill.php?rental_id=' + currentRoom.rental_id);
        const data = await res.json();
        
        if (data.success) {
          currentBillData = data;
          renderBill(data);
        } else {
          document.getElementById('billContent').innerHTML = '<p style="text-align:center;color:#6c757d;">Error loading bill</p>';
        }
      } catch (err) {
        document.getElementById('billContent').innerHTML = '<p style="text-align:center;color:#6c757d;">Network error</p>';
      }
    }
    
    function closeBillingModal() {
      document.getElementById('billingModal').classList.remove('active');
      currentBillData = null;
    }
    
    function goToBillingPage() {
      window.location.href = 'billing.php?rental_id=' + currentRoom.rental_id;
    }
    
    function renderBill(data) {
      const { bill, rental, orders, extensions } = data;
      
      let html = '<div class="bill-section">';
      html += '<div class="bill-section-title">Room Rental</div>';
      html += `<div class="bill-row"><span class="bill-label">Room Type:</span><span class="bill-value">${rental.type_name}</span></div>`;
      html += `<div class="bill-row"><span class="bill-label">Duration:</span><span class="bill-value">${rental.total_minutes} minutes</span></div>`;
      html += `<div class="bill-row"><span class="bill-label">Rate:</span><span class="bill-value">₱${parseFloat(rental.total_minutes >= 60 ? currentRoom.price_per_hour : currentRoom.price_per_30min).toFixed(2)}/hr</span></div>`;
      html += `<div class="bill-row"><span class="bill-label">Room Total:</span><span class="bill-value">₱${parseFloat(bill.total_room_cost).toFixed(2)}</span></div>`;
      html += '</div>';
      
      if (extensions && extensions.length > 0) {
        html += '<div class="bill-section">';
        html += '<div class="bill-section-title">Time Extensions</div>';
        extensions.forEach(ext => {
          html += `<div class="bill-row"><span class="bill-label">Extension ${ext.extension_id} (${ext.minutes_added} min)</span><span class="bill-value">₱${parseFloat(ext.cost).toFixed(2)}</span></div>`;
        });
        html += '</div>';
      }
      
      if (orders && orders.length > 0) {
        html += '<div class="bill-section">';
        html += '<div class="bill-section-title">Orders</div>';
        orders.forEach(item => {
          const lineTotal = parseFloat(item.price) * parseInt(item.quantity);
          html += `<div class="order-item"><span>${item.product_name} x${item.quantity}</span><span>₱${lineTotal.toFixed(2)}</span></div>`;
        });
        html += `<div class="bill-row"><span class="bill-label">Orders Total:</span><span class="bill-value">₱${parseFloat(bill.total_orders_cost).toFixed(2)}</span></div>`;
        html += '</div>';
      }
      
      html += '<div class="grand-total-box">';
      html += '<div class="grand-total-row">';
      html += '<span class="grand-total-label">Grand Total:</span>';
      html += `<span class="grand-total-amount">₱${parseFloat(bill.grand_total).toFixed(2)}</span>`;
      html += '</div>';
      html += '</div>';
      
      
      document.getElementById('billContent').innerHTML = html;
    }
    
    function selectPayment(btn) {
      document.querySelectorAll('.payment-btn').forEach(b => b.classList.remove('selected'));
      btn.classList.add('selected');
      selectedPaymentMethod = btn.dataset.method;
    }
    
    async function confirmPayment() {
      if (!currentBillData) return;
      
      if (!confirm('Process payment and end rental?')) return;
      
      // Redirect to billing page for final processing
      window.location.href = 'billing.php?rental_id=' + currentRoom.rental_id;
    }
    
    function startAllTimers() {
      document.querySelectorAll('.room-card.occupied .room-time').forEach(timeEl => {
        const startedAt = timeEl.dataset.started;
        if (!startedAt) return;
        
        function updateTimer() {
          const t = startedAt.split(/[- :]/);
          const startDate = new Date(t[0], t[1]-1, t[2], t[3], t[4], t[5]);
          const now = new Date();
          
          const elapsedSeconds = Math.floor((now - startDate) / 1000);
          
          const safeElapsed = elapsedSeconds < 0 ? 0 : elapsedSeconds;
          
          const hours = Math.floor(safeElapsed / 3600);
          const minutes = Math.floor((safeElapsed % 3600) / 60);
          const seconds = safeElapsed % 60;
          
          const timeStr = `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
          
          timeEl.textContent = timeStr;
        }
        
        updateTimer();
        setInterval(updateTimer, 1000);
      });
    }

    startAllTimers();

    setInterval(() => {
      location.reload();
    }, 30000);
  </script>
</body>
</html>