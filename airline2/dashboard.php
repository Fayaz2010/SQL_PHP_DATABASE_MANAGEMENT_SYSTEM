<?php
require_once 'auth.php';
requireRole(['admin','passenger','employee']);
require_once 'db.php';
$role = currentRole();
$name = currentUserName();
?>
<!DOCTYPE html>
<html>
<head>
  <title>Dashboard — Airline DBMS</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
<?php include 'navbar.php'; ?>
<div class="box">
  <h2>Welcome, <?= htmlspecialchars($name) ?>
    <span class="badge badge-<?= $role==='admin'?'admin':($role==='employee'?'emp':'pass') ?>">
      <?= ucfirst($role) ?>
    </span>
  </h2>
  <p style="margin-bottom:20px">Select a feature below or use the navigation bar above.</p>

  <?php if ($role === 'admin'): ?>
  <h3>Admin — Full Access to All Features</h3>
  <table>
    <tr><th>Feature</th><th>Description</th><th>Link</th></tr>
    <tr><td>A</td><td>Flight Scheduling — add airports, aircraft, and flights</td><td><a href="featureA/flights.php" class="btn blue">Open</a></td></tr>
    <tr><td>H</td><td>Employee Hierarchy — manage staff and reporting tree</td><td><a href="featureH/hierarchy.php" class="btn blue">Open</a></td></tr>
    <tr><td>B</td><td>Crew Assignment — assign employees to flights (ternary)</td><td><a href="featureB/crew.php" class="btn blue">Open</a></td></tr>
    <tr><td>C</td><td>Flight Search — search flights by city or airport</td><td><a href="featureC/search.php" class="btn blue">Open</a></td></tr>
    <tr><td>D</td><td>Booking &amp; Ticket — manage all bookings and tickets</td><td><a href="featureD/booking.php" class="btn blue">Open</a></td></tr>
    <tr><td>E</td><td>Payment Processing — manage all payments</td><td><a href="featureE/payment.php" class="btn blue">Open</a></td></tr>
    <tr><td>F</td><td>Baggage Check-In — manage baggage for any booking</td><td><a href="featureF/baggage.php" class="btn blue">Open</a></td></tr>
    <tr><td>G</td><td>Passenger Travel History — full journey view per passenger</td><td><a href="featureG/history.php" class="btn blue">Open</a></td></tr>
    <tr><td>I</td><td>Flight Operations Dashboard — full system summary</td><td><a href="featureI/ops_dashboard.php" class="btn blue">Open</a></td></tr>
  </table>

  <?php elseif ($role === 'employee'): ?>
  <h3>Employee — Your Access</h3>
  <table>
    <tr><th>Feature</th><th>Description</th><th>Link</th></tr>
    <tr><td>H</td><td>My Profile — view and edit your own profile and reporting</td><td><a href="featureH/hierarchy.php" class="btn blue">Open</a></td></tr>
    <tr><td>B</td><td>My Assignments — view your crew flight assignments</td><td><a href="featureB/crew.php" class="btn blue">Open</a></td></tr>
    <tr><td>C</td><td>Flight Search — search available flights</td><td><a href="featureC/search.php" class="btn blue">Open</a></td></tr>
  </table>

  <?php elseif ($role === 'passenger'): ?>
  <h3>Passenger — Your Access</h3>
  <table>
    <tr><th>Feature</th><th>Description</th><th>Link</th></tr>
    <tr><td>C</td><td>Flight Search — search flights and book</td><td><a href="featureC/search.php" class="btn blue">Open</a></td></tr>
    <tr><td>D</td><td>My Bookings &amp; Tickets — view and manage your bookings</td><td><a href="featureD/booking.php" class="btn blue">Open</a></td></tr>
    <tr><td>E</td><td>My Payments — view your payment records</td><td><a href="featureE/payment.php" class="btn blue">Open</a></td></tr>
    <tr><td>F</td><td>My Baggage — check in and track your baggage</td><td><a href="featureF/baggage.php" class="btn blue">Open</a></td></tr>
    <tr><td>G</td><td>My Travel History — full view of all your journeys</td><td><a href="featureG/history.php" class="btn blue">Open</a></td></tr>
  </table>
  <?php endif; ?>
</div>
</body>
</html>
