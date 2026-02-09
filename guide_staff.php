<?php
session_start();
require_once 'db.php';
if (!isset($_SESSION['user_id']) || intval($_SESSION['role_id']) !== 2) { 
    header('Location: index.php'); 
    exit; 
}
$staffName = htmlspecialchars($_SESSION['display_name'] ?: $_SESSION['username']);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Staff Guide – Wannabees KTV</title>
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
    
    /* Header */
    header {
      background: white;
      padding: 15px 30px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      box-shadow: 0 2px 5px rgba(0,0,0,0.1);
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
      border-radius: 6px;
      cursor: pointer;
      font-size: 14px;
      font-weight: 600;
      transition: all 0.3s;
      display: flex;
      align-items: center;
      gap: 8px;
    }
    
    .nav-btn.rooms {
      background: #d4d4d4;
      color: #2c2c2c;
    }
    
    .nav-btn.orders {
      background: #f5c542;
      color: #2c2c2c;
    }
    
    .nav-btn.guide {
      background: #3498db;
      color: white;
    }
    
    .nav-btn.logout {
      background: #e74c3c;
      color: white;
    }
    
    .nav-btn:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 8px rgba(0,0,0,0.15);
    }
    
    /* Main Content */
    main {
      padding: 30px;
      max-width: 1200px;
      margin: 0 auto;
    }
    
    .hero-section {
      background: white;
      border-radius: 16px;
      padding: 40px;
      text-align: center;
      margin-bottom: 30px;
      box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    }
    
    .hero-title {
      font-size: 32px;
      font-weight: 700;
      margin-bottom: 10px;
      color: #2c2c2c;
    }
    
    .hero-subtitle {
      font-size: 18px;
      color: #666;
    }
    
    .guide-section {
      background: white;
      border-radius: 16px;
      padding: 30px;
      margin-bottom: 20px;
      box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    }
    
    .section-title {
      font-size: 24px;
      font-weight: 700;
      margin-bottom: 20px;
      color: #2c2c2c;
      display: flex;
      align-items: center;
      gap: 10px;
    }
    
    .section-icon {
      width: 40px;
      height: 40px;
      background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      color: white;
      font-size: 20px;
    }
    
    .step {
      margin-bottom: 25px;
      padding: 20px;
      background: #f9f9f9;
      border-radius: 12px;
      border-left: 4px solid #3498db;
    }
    
    .step-number {
      display: inline-block;
      width: 30px;
      height: 30px;
      background: #3498db;
      color: white;
      border-radius: 50%;
      text-align: center;
      line-height: 30px;
      font-weight: 700;
      margin-right: 10px;
    }
    
    .step-title {
      font-size: 18px;
      font-weight: 600;
      margin-bottom: 10px;
      color: #2c2c2c;
    }
    
    .step-description {
      color: #666;
      line-height: 1.6;
      margin-left: 40px;
    }
    
    .tip-box {
      background: #e3f2fd;
      border-left: 4px solid #2196f3;
      padding: 15px 20px;
      border-radius: 8px;
      margin-top: 20px;
    }
    
    .tip-title {
      color: #1976d2;
      font-weight: 600;
      margin-bottom: 8px;
      display: flex;
      align-items: center;
      gap: 8px;
    }
    
    .tip-text {
      color: #555;
      font-size: 14px;
      line-height: 1.6;
    }
    
    .status-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
      gap: 20px;
      margin-top: 20px;
    }
    
    .status-card {
      padding: 20px;
      border-radius: 12px;
      text-align: center;
      color: white;
      font-weight: 600;
    }
    
    .status-card.available {
      background: #27ae60;
    }
    
    .status-card.occupied {
      background: #e74c3c;
    }
    
    .status-card.cleaning {
      background: #3498db;
    }
    
    .status-icon {
      font-size: 36px;
      margin-bottom: 10px;
    }
    
    .status-label {
      font-size: 18px;
      margin-bottom: 5px;
    }
    
    .status-description {
      font-size: 14px;
      opacity: 0.9;
    }
    
    .warning-box {
      background: #fff3cd;
      border-left: 4px solid #ffc107;
      padding: 15px 20px;
      border-radius: 8px;
      margin-top: 20px;
    }
    
    .warning-title {
      color: #856404;
      font-weight: 600;
      margin-bottom: 8px;
      display: flex;
      align-items: center;
      gap: 8px;
    }
    
    .warning-text {
      color: #856404;
      font-size: 14px;
      line-height: 1.6;
    }
    
    .checklist {
      list-style: none;
      margin-left: 40px;
      margin-top: 10px;
    }
    
    .checklist li {
      padding: 8px 0;
      color: #666;
      display: flex;
      align-items: center;
      gap: 10px;
    }
    
    .checklist li::before {
      content: '✓';
      display: inline-block;
      width: 20px;
      height: 20px;
      background: #27ae60;
      color: white;
      border-radius: 50%;
      text-align: center;
      line-height: 20px;
      font-weight: 700;
      flex-shrink: 0;
    }
  </style>
</head>
<body>
  <header>
    <div class="header-left">
      <img src="assets/images/KTVL.png" alt="logo">
      <div>
        <div class="header-title">Wannabees Family KTV</div>
        <div class="header-subtitle">Staff Guide</div>
      </div>
    </div>
    <div class="header-nav">
      <button class="nav-btn rooms" onclick="location.href='staff.php'">
        <i class="fas fa-door-open"></i> Rooms
      </button>
      <button class="nav-btn orders" onclick="location.href='staff.php#ordersSection'">
        <i class="fas fa-utensils"></i> Orders
      </button>
      <button class="nav-btn guide">
        <i class="fas fa-book-open"></i> Guide
      </button>
      <form action="logout.php" method="post" style="display:inline">
        <button class="nav-btn logout">
          <i class="fas fa-sign-out-alt"></i> Logout
        </button>
      </form>
    </div>
  </header>

  <main>
    <div class="hero-section">
      <h1 class="hero-title">Staff Operations Guide</h1>
      <p class="hero-subtitle">Your complete reference for room management and cleaning procedures</p>
    </div>

    <!-- Room Status Overview -->
    <div class="guide-section">
      <h2 class="section-title">
        <span class="section-icon"><i class="fas fa-traffic-light"></i></span>
        Understanding Room Status
      </h2>
      
      <div class="status-grid">
        <div class="status-card available">
          <div class="status-icon"><i class="fas fa-check-circle"></i></div>
          <div class="status-label">Available</div>
          <div class="status-description">Room is clean and ready for customers</div>
        </div>
        
        <div class="status-card occupied">
          <div class="status-icon"><i class="fas fa-users"></i></div>
          <div class="status-label">Occupied</div>
          <div class="status-description">Customers are currently using this room</div>
        </div>
        
        <div class="status-card cleaning">
          <div class="status-icon"><i class="fas fa-broom"></i></div>
          <div class="status-label">Cleaning</div>
          <div class="status-description">Room needs to be cleaned and prepared</div>
        </div>
      </div>
    </div>

    <!-- Room Cleaning Workflow -->
    <div class="guide-section">
      <h2 class="section-title">
        <span class="section-icon"><i class="fas fa-clipboard-list"></i></span>
        Room Cleaning Workflow
      </h2>
      
      <div class="step">
        <div class="step-title">
          <span class="step-number">1</span>
          Check Dashboard
        </div>
        <div class="step-description">
          Look for rooms with <strong>CLEANING</strong> status (blue color). These rooms need your attention.
        </div>
      </div>
      
      <div class="step">
        <div class="step-title">
          <span class="step-number">2</span>
          Enter the Room
        </div>
        <div class="step-description">
          Open the room and assess what needs to be cleaned. Bring your cleaning supplies and equipment.
        </div>
      </div>
      
      <div class="step">
        <div class="step-title">
          <span class="step-number">3</span>
          Clean Thoroughly
        </div>
        <div class="step-description">
          Follow the cleaning checklist below. Ensure the room is spotless and ready for the next guest.
        </div>
      </div>
      
      <div class="step">
        <div class="step-title">
          <span class="step-number">4</span>
          Mark as Available
        </div>
        <div class="step-description">
          When finished, click on the room card and tap <strong>"Mark as Available"</strong>. This logs your work and makes the room ready for customers.
        </div>
      </div>
      
      <div class="tip-box">
        <div class="tip-title">
          <i class="fas fa-lightbulb"></i> Pro Tip
        </div>
        <div class="tip-text">
          Your cleaning activity is automatically logged with timestamp. You can see your daily work summary in the "Today's Activity Log" section.
        </div>
      </div>
    </div>

    <!-- Cleaning Checklist -->
    <div class="guide-section">
      <h2 class="section-title">
        <span class="section-icon"><i class="fas fa-tasks"></i></span>
        Room Cleaning Checklist
      </h2>
      
      <div class="step">
        <div class="step-title">
          <i class="fas fa-check-double"></i> Essential Tasks
        </div>
        <ul class="checklist">
          <li>Clear all trash and dispose properly</li>
          <li>Wipe down all tables and surfaces</li>
          <li>Clean and organize microphones</li>
          <li>Vacuum or sweep the floor</li>
          <li>Sanitize remote controls and tablets</li>
          <li>Check and clean the TV/monitor</li>
          <li>Arrange furniture neatly</li>
          <li>Restock tissues and amenities</li>
          <li>Check air conditioning/ventilation</li>
          <li>Ensure all equipment is working</li>
        </ul>
      </div>
      
      <div class="warning-box">
        <div class="warning-title">
          <i class="fas fa-exclamation-triangle"></i> Quality Standards
        </div>
        <div class="warning-text">
          Every room must meet our cleanliness standards before being marked as Available. If you notice any equipment issues or damage, report it to the manager immediately.
        </div>
      </div>
    </div>

    <!-- Handling Orders -->
    <div class="guide-section">
      <h2 class="section-title">
        <span class="section-icon"><i class="fas fa-utensils"></i></span>
        Managing Customer Orders
      </h2>
      
      <div class="step">
        <div class="step-title">
          <span class="step-number">1</span>
          Monitor Orders
        </div>
        <div class="step-description">
          Check the <strong>"Pending Orders"</strong> section regularly. New orders will appear here automatically.
        </div>
      </div>
      
      <div class="step">
        <div class="step-title">
          <span class="step-number">2</span>
          Coordinate with Kitchen
        </div>
        <div class="step-description">
          Work with the kitchen or bar staff to ensure orders are prepared correctly and promptly.
        </div>
      </div>
      
      <div class="step">
        <div class="step-title">
          <span class="step-number">3</span>
          Deliver to Room
        </div>
        <div class="step-description">
          Take the completed order to the correct room number. Always knock before entering and greet customers politely.
        </div>
      </div>
      
      <div class="step">
        <div class="step-title">
          <span class="step-number">4</span>
          Mark as Delivered
        </div>
        <div class="step-description">
          After delivery, update the order status in the system to keep accurate records.
        </div>
      </div>
      
      <div class="tip-box">
        <div class="tip-title">
          <i class="fas fa-lightbulb"></i> Customer Service
        </div>
        <div class="tip-text">
          Always deliver orders with a smile! Ask if they need anything else and remind them they can order more through the tablet. Great service leads to happy customers and better tips!
        </div>
      </div>
    </div>

    <!-- Emergency Procedures -->
    <div class="guide-section">
      <h2 class="section-title">
        <span class="section-icon"><i class="fas fa-exclamation-circle"></i></span>
        Emergency & Special Situations
      </h2>
      
      <div class="step">
        <div class="step-title">
          <i class="fas fa-tools"></i> Equipment Problems
        </div>
        <div class="step-description">
          If you notice broken microphones, faulty screens, or other equipment issues:
          <ul class="checklist">
            <li>Mark the room as CLEANING if it's occupied</li>
            <li>Report to the manager immediately</li>
            <li>Do NOT mark the room as available until fixed</li>
          </ul>
        </div>
      </div>
      
      <div class="step">
        <div class="step-title">
          <i class="fas fa-hand-paper"></i> Customer Complaints
        </div>
        <div class="step-description">
          If a customer has a complaint or issue:
          <ul class="checklist">
            <li>Listen carefully and apologize for the inconvenience</li>
            <li>Try to resolve simple issues immediately</li>
            <li>Call the manager for serious concerns</li>
            <li>Always remain professional and courteous</li>
          </ul>
        </div>
      </div>
      
      <div class="warning-box">
        <div class="warning-title">
          <i class="fas fa-phone-alt"></i> Need Help?
        </div>
        <div class="warning-text">
          For any situation you're unsure about, don't hesitate to contact the manager or senior staff. It's better to ask than to make a mistake!
        </div>
      </div>
    </div>

    <!-- Best Practices -->
    <div class="guide-section">
      <h2 class="section-title">
        <span class="section-icon"><i class="fas fa-star"></i></span>
        Best Practices for Success
      </h2>
      
      <div class="status-grid">
        <div class="status-card" style="background: #27ae60;">
          <div class="status-icon"><i class="fas fa-tachometer-alt"></i></div>
          <div class="status-label">Work Efficiently</div>
          <div class="status-description">Clean rooms quickly but thoroughly to maximize availability</div>
        </div>
        
        <div class="status-card" style="background: #3498db;">
          <div class="status-icon"><i class="fas fa-eye"></i></div>
          <div class="status-label">Stay Alert</div>
          <div class="status-description">Check the dashboard regularly for new cleaning requests</div>
        </div>
        
        <div class="status-card" style="background: #f39c12;">
          <div class="status-icon"><i class="fas fa-comments"></i></div>
          <div class="status-label">Communicate</div>
          <div class="status-description">Keep managers informed of any issues or delays</div>
        </div>
        
        <div class="status-card" style="background: #9b59b6;">
          <div class="status-icon"><i class="fas fa-smile-beam"></i></div>
          <div class="status-label">Be Friendly</div>
          <div class="status-description">Great attitude leads to better tips and job satisfaction</div>
        </div>
      </div>
    </div>

    <!-- Back Button -->
    <div class="guide-section" style="text-align: center;">
      <button class="nav-btn rooms" onclick="location.href='staff.php'" 
              style="padding: 15px 40px; font-size: 16px;">
        <i class="fas fa-arrow-left"></i> Back to Dashboard
      </button>
    </div>
  </main>
</body>
</html>