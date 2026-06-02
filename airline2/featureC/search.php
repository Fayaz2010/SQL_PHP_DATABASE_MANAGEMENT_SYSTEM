<?php
// Feature C: Flight Search — all roles
require_once '../auth.php';
requireRole(['admin','employee','passenger']);
require_once '../db.php';
$results  = null; $searched = false;
$dep_city = trim($_GET['dep_city'] ?? '');
$arr_city = trim($_GET['arr_city'] ?? '');
$dep_date = trim($_GET['dep_date'] ?? '');

if ($dep_city || $arr_city) {
    $searched = true;
    $where = "WHERE f.flight_status != 'Cancelled'";
    if ($dep_city) { $d=mysqli_real_escape_string($conn,$dep_city); $where.=" AND (dep.city LIKE '%$d%' OR dep.airport_name LIKE '%$d%' OR dep.airport_code LIKE '%$d%')"; }
    if ($arr_city) { $a=mysqli_real_escape_string($conn,$arr_city); $where.=" AND (arr.city LIKE '%$a%' OR arr.airport_name LIKE '%$a%' OR arr.airport_code LIKE '%$a%')"; }
    if ($dep_date) { $dt=mysqli_real_escape_string($conn,$dep_date); $where.=" AND DATE(f.departure_time)='$dt'"; }
    $results = mysqli_query($conn,
        "SELECT f.*,dep.airport_name AS dep_name,dep.city AS dep_city,dep.airport_code AS dep_code,
                arr.airport_name AS arr_name,arr.city AS arr_city,arr.airport_code AS arr_code,
                ac.model,ac.capacity,
                TIMESTAMPDIFF(MINUTE,f.departure_time,f.arrival_time) AS duration_mins,
                (ac.capacity-COUNT(b.booking_id)) AS available_seats
         FROM flights f JOIN airports dep ON f.departure_airport_id=dep.airport_id
         JOIN airports arr ON f.arrival_airport_id=arr.airport_id
         JOIN aircraft ac ON f.aircraft_id=ac.aircraft_id
         LEFT JOIN bookings b ON f.flight_id=b.flight_id AND b.booking_status='Confirmed'
         $where GROUP BY f.flight_id ORDER BY f.departure_time");
}
$isPass = isPassenger();
?>
<!DOCTYPE html><html><head><title>Feature C — Search</title><link rel="stylesheet" href="../style.css"></head><body>
<?php include '../navbar.php'; ?>
<div class="box">
<h2>Feature C — Flight Search &amp; Availability</h2>
<form method="GET" action="search.php">
  <div class="row2">
    <div><label>Departure City / Airport</label><input type="text" name="dep_city" placeholder="e.g. Dhaka, DAC" value="<?= htmlspecialchars($dep_city) ?>"></div>
    <div><label>Arrival City / Airport</label><input type="text" name="arr_city" placeholder="e.g. Dubai, DXB" value="<?= htmlspecialchars($arr_city) ?>"></div>
  </div>
  <label>Travel Date (optional)</label>
  <input type="date" name="dep_date" value="<?= htmlspecialchars($dep_date) ?>" style="width:200px">
  <br><br>
  <input type="submit" value="Search Flights" class="btn blue">
  <a href="search.php" class="btn grey">Clear</a>
</form><hr>
<?php if (!$searched): ?><p style="color:#888">Enter a departure or arrival city to search.</p>
<?php elseif ($results && mysqli_num_rows($results)===0): ?><div class="msg info">No flights found. Try different cities or date.</div>
<?php else: ?>
<h3>Search Results</h3>
<div class="tbl-wrap"><table>
  <tr><th>Flight ID</th><th>From</th><th>To</th><th>Departure</th><th>Arrival</th><th>Duration (derived)</th><th>Aircraft</th><th>Total Seats</th><th>Available (derived)</th><th>Status</th><?php if ($isPass): ?><th>Book</th><?php endif; ?></tr>
  <?php while ($row=mysqli_fetch_assoc($results)):
    $hrs=floor($row['duration_mins']/60); $mins=$row['duration_mins']%60; $avail=max(0,$row['available_seats']); ?>
  <tr>
    <td><?= $row['flight_id'] ?></td>
    <td><?= htmlspecialchars($row['dep_name']) ?><br><small><?= $row['dep_city'] ?> (<?= $row['dep_code'] ?>)</small></td>
    <td><?= htmlspecialchars($row['arr_name']) ?><br><small><?= $row['arr_city'] ?> (<?= $row['arr_code'] ?>)</small></td>
    <td><?= $row['departure_time'] ?></td><td><?= $row['arrival_time'] ?></td>
    <td><?= $hrs ?>h <?= $mins ?>m</td>
    <td><?= htmlspecialchars($row['model']) ?></td><td><?= $row['capacity'] ?></td>
    <td style="color:<?= $avail>0?'green':'red' ?>;font-weight:bold"><?= $avail ?></td>
    <td><?= $row['flight_status'] ?></td>
    <?php if ($isPass): ?><td><?= $avail>0 ? '<a href="../featureD/booking.php?flight_id='.$row['flight_id'].'" class="btn green">Book</a>' : '<span style="color:red;font-size:13px">Full</span>' ?></td><?php endif; ?>
  </tr>
  <?php endwhile; ?>
</table></div>
<?php endif; ?>
</div></body></html>
