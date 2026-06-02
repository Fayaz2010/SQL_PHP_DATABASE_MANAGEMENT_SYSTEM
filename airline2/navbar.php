<?php
$depth = substr_count($_SERVER['PHP_SELF'], "/") - 2;
$base  = str_repeat("../", $depth);
$role  = currentRole();
?>
<div class="nav">
  <a class="brand" href="<?= $base ?>dashboard.php">✈ Airline DBMS</a>

  <?php if ($role === 'admin'): ?>
    <a href="<?= $base ?>featureA/flights.php">Flights</a>
    <a href="<?= $base ?>featureH/hierarchy.php">Employees</a>
    <a href="<?= $base ?>featureB/crew.php">Crew</a>
    <a href="<?= $base ?>featureC/search.php">Search</a>
    <a href="<?= $base ?>featureD/booking.php">Bookings</a>
    <a href="<?= $base ?>featureE/payment.php">Payments</a>
    <a href="<?= $base ?>featureF/baggage.php">Baggage</a>
    <a href="<?= $base ?>featureG/history.php">History</a>
    <a href="<?= $base ?>featureI/ops_dashboard.php">Dashboard</a>

  <?php elseif ($role === 'employee'): ?>
    <a href="<?= $base ?>featureH/hierarchy.php">My Profile</a>
    <a href="<?= $base ?>featureB/crew.php">My Assignments</a>
    <a href="<?= $base ?>featureC/search.php">Search Flights</a>

  <?php elseif ($role === 'passenger'): ?>
    <a href="<?= $base ?>featureC/search.php">Search Flights</a>
    <a href="<?= $base ?>featureD/booking.php">My Bookings</a>
    <a href="<?= $base ?>featureE/payment.php">My Payments</a>
    <a href="<?= $base ?>featureF/baggage.php">My Baggage</a>
    <a href="<?= $base ?>featureG/history.php">My History</a>
  <?php endif; ?>

  <span class="who">Logged in as: <?= htmlspecialchars(currentUserName()) ?> (<?= ucfirst($role) ?>)</span>
  <a href="<?= $base ?>logout.php" class="logout">Logout</a>
</div>
