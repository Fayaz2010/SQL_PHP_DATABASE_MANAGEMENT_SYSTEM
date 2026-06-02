<?php
// Feature I: Flight Operations Dashboard — Admin only, read-only
require_once '../auth.php';
requireRole(['admin']);
require_once '../db.php';

$total_passengers       = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM passengers"))['c'];
$total_employees        = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM employees"))['c'];
$total_flights          = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM flights"))['c'];
$total_airports         = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM airports"))['c'];
$total_aircraft         = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM aircraft"))['c'];
$total_bookings         = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM bookings"))['c'];
$confirmed_bookings     = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM bookings WHERE booking_status='Confirmed'"))['c'];
$total_revenue          = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COALESCE(SUM(payment_amount),0) AS s FROM payments WHERE payment_status='Completed'"))['s'];
$total_baggage          = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM baggage"))['c'];
$total_crew_assignments = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM crew_assignment"))['c'];

$flights_data = mysqli_query($conn,
    "SELECT f.flight_id, f.departure_time, f.arrival_time, f.flight_status, f.base_price,
            dep.airport_code AS dep_code, dep.city AS dep_city,
            arr.airport_code AS arr_code, arr.city AS arr_city,
            ac.model, ac.capacity,
            TIMESTAMPDIFF(MINUTE, f.departure_time, f.arrival_time) AS duration_mins,
            (ac.capacity - COALESCE(bk_count.cnt,0)) AS available_seats,
            COALESCE(bk_count.cnt,  0) AS seats_booked,
            COALESCE(pay_sum.total_pay, 0) AS revenue_collected,
            COALESCE(crew_count.cc, 0) AS crew_assigned,
            COALESCE(bag_count.bc,  0) AS total_baggage
     FROM flights f
     JOIN airports dep ON f.departure_airport_id = dep.airport_id
     JOIN airports arr ON f.arrival_airport_id   = arr.airport_id
     JOIN aircraft ac  ON f.aircraft_id = ac.aircraft_id
     LEFT JOIN (
         SELECT flight_id, COUNT(*) AS cnt FROM bookings WHERE booking_status='Confirmed' GROUP BY flight_id
     ) bk_count ON f.flight_id = bk_count.flight_id
     LEFT JOIN (
         SELECT b.flight_id, SUM(p.payment_amount) AS total_pay
         FROM payments p JOIN bookings b ON p.booking_id=b.booking_id
         WHERE p.payment_status='Completed' GROUP BY b.flight_id
     ) pay_sum ON f.flight_id = pay_sum.flight_id
     LEFT JOIN (
         SELECT flight_id, COUNT(*) AS cc FROM crew_assignment GROUP BY flight_id
     ) crew_count ON f.flight_id = crew_count.flight_id
     LEFT JOIN (
         SELECT b.flight_id, COUNT(bg.bag_number) AS bc
         FROM baggage bg JOIN bookings b ON bg.booking_id=b.booking_id GROUP BY b.flight_id
     ) bag_count ON f.flight_id = bag_count.flight_id
     ORDER BY f.departure_time");
?>
<!DOCTYPE html>
<html>
<head>
  <title>Feature I — Operations Dashboard</title>
  <link rel="stylesheet" href="../style.css">
</head>
<body>
<?php include '../navbar.php'; ?>
<div class="box">
  <h2>Feature I — Flight Operations Dashboard
    <span class="badge badge-admin">Admin Only — Read Only</span>
  </h2>
  <p style="font-size:13px;color:#555;margin-bottom:18px">
    Full system summary across all entities and relationships. No edits here.
  </p>

  <!-- System-wide counts -->
  <h3>System-Wide Summary</h3>
  <table style="max-width:700px;margin-bottom:20px">
    <tr><th>Metric</th><th>Count / Value</th></tr>
    <tr><td>Total Registered Passengers</td><td><b><?= $total_passengers ?></b></td></tr>
    <tr><td>Total Employees</td><td><b><?= $total_employees ?></b></td></tr>
    <tr><td>Total Airports</td><td><b><?= $total_airports ?></b></td></tr>
    <tr><td>Total Aircraft in Fleet</td><td><b><?= $total_aircraft ?></b></td></tr>
    <tr><td>Total Flights Scheduled</td><td><b><?= $total_flights ?></b></td></tr>
    <tr><td>Total Bookings (all statuses)</td><td><b><?= $total_bookings ?></b></td></tr>
    <tr><td>Confirmed Bookings</td><td><b style="color:green"><?= $confirmed_bookings ?></b></td></tr>
    <tr><td>Total Crew Assignments</td><td><b><?= $total_crew_assignments ?></b></td></tr>
    <tr><td>Total Baggage Items</td><td><b><?= $total_baggage ?></b></td></tr>
    <tr><td>Total Revenue Collected (Completed payments)</td><td><b style="color:green">$<?= number_format($total_revenue, 2) ?></b></td></tr>
  </table>

  <!-- Employee role breakdown -->
  <h3>Employee Role Breakdown</h3>
  <?php $roles = mysqli_query($conn, "SELECT role, COUNT(*) AS cnt FROM employees GROUP BY role ORDER BY role"); ?>
  <table style="max-width:400px;margin-bottom:20px">
    <tr><th>Role</th><th>Count</th></tr>
    <?php while ($r = mysqli_fetch_assoc($roles)): ?>
    <tr><td><?= htmlspecialchars($r['role']) ?></td><td><?= $r['cnt'] ?></td></tr>
    <?php endwhile; ?>
  </table>

  <!-- Payment status breakdown -->
  <h3>Payment Status Breakdown</h3>
  <?php $pay_stats = mysqli_query($conn, "SELECT payment_status, COUNT(*) AS cnt, COALESCE(SUM(payment_amount),0) AS total FROM payments GROUP BY payment_status"); ?>
  <table style="max-width:450px;margin-bottom:20px">
    <tr><th>Status</th><th>Count</th><th>Total Amount</th></tr>
    <?php while ($ps = mysqli_fetch_assoc($pay_stats)): ?>
    <tr>
      <td style="color:<?= $ps['payment_status']==='Completed'?'green':($ps['payment_status']==='Refunded'?'red':'orange') ?>;font-weight:bold"><?= $ps['payment_status'] ?></td>
      <td><?= $ps['cnt'] ?></td>
      <td>$<?= number_format($ps['total'], 2) ?></td>
    </tr>
    <?php endwhile; ?>
  </table>

  <!-- Baggage status breakdown -->
  <h3>Baggage Status Breakdown</h3>
  <?php $bag_stats = mysqli_query($conn, "SELECT baggage_status, COUNT(*) AS cnt FROM baggage GROUP BY baggage_status"); ?>
  <table style="max-width:350px;margin-bottom:20px">
    <tr><th>Status</th><th>Count</th></tr>
    <?php while ($bs = mysqli_fetch_assoc($bag_stats)): ?>
    <tr>
      <td style="color:<?= $bs['baggage_status']==='Delivered'?'green':($bs['baggage_status']==='Lost'?'red':'orange') ?>"><?= $bs['baggage_status'] ?></td>
      <td><?= $bs['cnt'] ?></td>
    </tr>
    <?php endwhile; ?>
  </table>

  <!-- Per-flight summary — occupancy % column removed -->
  <h3>Per-Flight Operations Summary</h3>
  <div class="tbl-wrap"><table>
    <tr>
      <th>Flight ID</th>
      <th>Route</th>
      <th>Departure</th>
      <th>Duration (derived)</th>
      <th>Aircraft</th>
      <th>Total Capacity</th>
      <th>Seats Booked</th>
      <th>Seats Available (derived)</th>
      <th>Economy Price</th>
      <th>Crew Assigned</th>
      <th>Baggage Items</th>
      <th>Revenue Collected</th>
      <th>Status</th>
    </tr>
    <?php if (mysqli_num_rows($flights_data) === 0): ?>
    <tr><td colspan="13" style="text-align:center;color:#888">No flights yet.</td></tr>
    <?php endif;
    while ($row = mysqli_fetch_assoc($flights_data)):
      $hrs   = floor($row['duration_mins'] / 60);
      $mins  = $row['duration_mins'] % 60;
      $avail = max(0, $row['available_seats']);
      $st_col = $row['flight_status']==='Scheduled' ? 'green'
              : ($row['flight_status']==='Cancelled' ? 'red' : 'orange');
    ?>
    <tr>
      <td><?= $row['flight_id'] ?></td>
      <td>
        <?= $row['dep_code'] ?> → <?= $row['arr_code'] ?><br>
        <small><?= htmlspecialchars($row['dep_city']) ?> → <?= htmlspecialchars($row['arr_city']) ?></small>
      </td>
      <td><?= $row['departure_time'] ?></td>
      <td><?= $hrs ?>h <?= $mins ?>m</td>
      <td><?= htmlspecialchars($row['model']) ?></td>
      <td><?= $row['capacity'] ?></td>
      <td><?= $row['seats_booked'] ?></td>
      <td style="color:<?= $avail > 0 ? 'green' : 'red' ?>;font-weight:bold"><?= $avail ?></td>
      <td>$<?= number_format($row['base_price'], 2) ?></td>
      <td style="color:<?= $row['crew_assigned'] > 0 ? 'green' : 'red' ?>"><?= $row['crew_assigned'] ?></td>
      <td><?= $row['total_baggage'] ?></td>
      <td style="color:green">$<?= number_format($row['revenue_collected'], 2) ?></td>
      <td style="color:<?= $st_col ?>;font-weight:bold"><?= $row['flight_status'] ?></td>
    </tr>
    <?php endwhile; ?>
  </table></div>

</div>
</body>
</html>
