<?php
// Feature A: Flight Scheduling — Admin only
// Change: admin now sets base_price (Economy) when adding/editing a flight
require_once '../auth.php';
requireRole(['admin']);
require_once '../db.php';
$msg = "";

if (isset($_GET['del_airport'])) {
    $id = (int)$_GET['del_airport'];
    $r  = mysqli_query($conn, "DELETE FROM airports WHERE airport_id=$id");
    $msg = $r ? "ok:Airport deleted." : "err:Cannot delete — airport may be in use by a flight.";
}
if (isset($_POST['add_airport'])) {
    $name = mysqli_real_escape_string($conn, trim($_POST['airport_name']));
    $city = mysqli_real_escape_string($conn, trim($_POST['city']));
    $ctry = mysqli_real_escape_string($conn, trim($_POST['country']));
    $code = strtoupper(mysqli_real_escape_string($conn, trim($_POST['airport_code'])));
    if ($name && $city && $ctry && $code) {
        $r = mysqli_query($conn, "INSERT INTO airports (airport_name,city,country,airport_code) VALUES ('$name','$city','$ctry','$code')");
        $msg = $r ? "ok:Airport added." : "err:Airport code may already exist.";
    } else { $msg = "err:All airport fields are required."; }
}
if (isset($_GET['del_aircraft'])) {
    $id = (int)$_GET['del_aircraft'];
    $r  = mysqli_query($conn, "DELETE FROM aircraft WHERE aircraft_id=$id");
    $msg = $r ? "ok:Aircraft deleted." : "err:Cannot delete — aircraft may be in use by a flight.";
}
if (isset($_POST['add_aircraft'])) {
    $model   = mysqli_real_escape_string($conn, trim($_POST['model']));
    $cap     = (int)$_POST['capacity'];
    $manuf   = mysqli_real_escape_string($conn, trim($_POST['manufacturer']));
    $airline = mysqli_real_escape_string($conn, trim($_POST['airline_company']));
    if ($model && $cap > 0 && $manuf && $airline) {
        $r = mysqli_query($conn, "INSERT INTO aircraft (model,capacity,manufacturer,airline_company) VALUES ('$model',$cap,'$manuf','$airline')");
        $msg = $r ? "ok:Aircraft added." : "err:Error adding aircraft.";
    } else { $msg = "err:All aircraft fields are required."; }
}
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    mysqli_query($conn, "DELETE FROM flights WHERE flight_id=$id");
    $msg = "ok:Flight deleted.";
}
if (isset($_POST['add'])) {
    $aid        = (int)$_POST['aircraft_id'];
    $dep        = (int)$_POST['departure_airport_id'];
    $arr        = (int)$_POST['arrival_airport_id'];
    $dt         = mysqli_real_escape_string($conn, $_POST['departure_time']);
    $at         = mysqli_real_escape_string($conn, $_POST['arrival_time']);
    $st         = mysqli_real_escape_string($conn, $_POST['flight_status']);
    $base_price = (float)$_POST['base_price'];

    if ($aid && $dep && $arr && $dt && $at && $base_price > 0) {
        if ($dep === $arr) { $msg = "err:Departure and arrival cannot be the same."; }
        elseif ($at <= $dt) { $msg = "err:Arrival must be after departure."; }
        else {
            $r = mysqli_query($conn,
                "INSERT INTO flights (aircraft_id,departure_airport_id,arrival_airport_id,departure_time,arrival_time,flight_status,base_price)
                 VALUES ($aid,$dep,$arr,'$dt','$at','$st',$base_price)");
            $msg = $r ? "ok:Flight added." : "err:Error adding flight.";
        }
    } else { $msg = "err:All fields are required. Base price must be greater than 0."; }
}
if (isset($_POST['update'])) {
    $id         = (int)$_POST['flight_id'];
    $aid        = (int)$_POST['aircraft_id'];
    $dep        = (int)$_POST['departure_airport_id'];
    $arr        = (int)$_POST['arrival_airport_id'];
    $dt         = mysqli_real_escape_string($conn, $_POST['departure_time']);
    $at         = mysqli_real_escape_string($conn, $_POST['arrival_time']);
    $st         = mysqli_real_escape_string($conn, $_POST['flight_status']);
    $base_price = (float)$_POST['base_price'];
    $r = mysqli_query($conn,
        "UPDATE flights SET aircraft_id=$aid,departure_airport_id=$dep,arrival_airport_id=$arr,
         departure_time='$dt',arrival_time='$at',flight_status='$st',base_price=$base_price
         WHERE flight_id=$id");
    $msg = $r ? "ok:Flight updated." : "err:Error updating flight.";
}

$edit = null;
if (isset($_GET['edit'])) {
    $id   = (int)$_GET['edit'];
    $edit = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM flights WHERE flight_id=$id"));
}

$airports_dd  = mysqli_query($conn, "SELECT * FROM airports ORDER BY airport_name");
$aircrafts_dd = mysqli_query($conn, "SELECT * FROM aircraft ORDER BY model");
$airports_tbl = mysqli_query($conn, "SELECT * FROM airports ORDER BY airport_name");
$aircraft_tbl = mysqli_query($conn, "SELECT * FROM aircraft ORDER BY model");
$flights = mysqli_query($conn,
    "SELECT f.*, dep.airport_name AS dep_name, dep.airport_code AS dep_code,
            arr.airport_name AS arr_name, arr.airport_code AS arr_code,
            ac.model, ac.capacity,
            TIMESTAMPDIFF(MINUTE, f.departure_time, f.arrival_time) AS duration_mins,
            (ac.capacity - COUNT(b.booking_id)) AS available_seats
     FROM flights f
     JOIN airports dep ON f.departure_airport_id = dep.airport_id
     JOIN airports arr ON f.arrival_airport_id   = arr.airport_id
     JOIN aircraft ac  ON f.aircraft_id          = ac.aircraft_id
     LEFT JOIN bookings b ON f.flight_id = b.flight_id AND b.booking_status = 'Confirmed'
     GROUP BY f.flight_id ORDER BY f.departure_time");
?>
<!DOCTYPE html>
<html>
<head>
  <title>Feature A — Flights</title>
  <link rel="stylesheet" href="../style.css">
</head>
<body>
<?php include '../navbar.php'; ?>
<div class="box">
<h2>Feature A — Flight Scheduling <span class="badge badge-admin">Admin Only</span></h2>
<?php if ($msg): $p = explode(":", $msg, 2); ?><div class="msg <?= $p[0] ?>"><?= $p[1] ?></div><?php endif; ?>

<h3>Step 1 — Add Airports</h3>
<form method="POST" action="flights.php">
  <div class="row2">
    <div><label>Airport Name</label><input type="text" name="airport_name" placeholder="e.g. Hazrat Shahjalal Intl"></div>
    <div><label>Airport Code</label><input type="text" name="airport_code" placeholder="e.g. DAC" maxlength="10"></div>
  </div>
  <div class="row2">
    <div><label>City</label><input type="text" name="city" placeholder="e.g. Dhaka"></div>
    <div><label>Country</label><input type="text" name="country" placeholder="e.g. Bangladesh"></div>
  </div>
  <input type="submit" name="add_airport" value="Add Airport" class="btn blue">
</form>
<?php if (mysqli_num_rows($airports_tbl) > 0): ?>
<div class="tbl-wrap" style="margin-top:10px"><table>
  <tr><th>ID</th><th>Name</th><th>City</th><th>Country</th><th>Code</th><th>Delete</th></tr>
  <?php while ($a = mysqli_fetch_assoc($airports_tbl)): ?>
  <tr>
    <td><?= $a['airport_id'] ?></td><td><?= htmlspecialchars($a['airport_name']) ?></td>
    <td><?= htmlspecialchars($a['city']) ?></td><td><?= htmlspecialchars($a['country']) ?></td>
    <td><?= htmlspecialchars($a['airport_code']) ?></td>
    <td><a href="flights.php?del_airport=<?= $a['airport_id'] ?>" class="btn red" onclick="return confirm('Delete this airport?')">Delete</a></td>
  </tr>
  <?php endwhile; ?>
</table></div>
<?php else: ?><p style="color:#888;font-size:13px;margin-top:8px">No airports yet.</p><?php endif; ?>
<hr>

<h3>Step 2 — Add Aircraft</h3>
<form method="POST" action="flights.php">
  <div class="row2">
    <div><label>Model</label><input type="text" name="model" placeholder="e.g. Boeing 737"></div>
    <div><label>Seat Capacity</label><input type="number" name="capacity" placeholder="e.g. 180" min="1"></div>
  </div>
  <div class="row2">
    <div><label>Manufacturer</label><input type="text" name="manufacturer" placeholder="e.g. Boeing"></div>
    <div><label>Airline Company</label><input type="text" name="airline_company" placeholder="e.g. Biman Bangladesh"></div>
  </div>
  <input type="submit" name="add_aircraft" value="Add Aircraft" class="btn blue">
</form>
<?php if (mysqli_num_rows($aircraft_tbl) > 0): ?>
<div class="tbl-wrap" style="margin-top:10px"><table>
  <tr><th>ID</th><th>Model</th><th>Capacity</th><th>Manufacturer</th><th>Airline</th><th>Delete</th></tr>
  <?php while ($ac = mysqli_fetch_assoc($aircraft_tbl)): ?>
  <tr>
    <td><?= $ac['aircraft_id'] ?></td><td><?= htmlspecialchars($ac['model']) ?></td>
    <td><?= $ac['capacity'] ?> seats</td><td><?= htmlspecialchars($ac['manufacturer']) ?></td>
    <td><?= htmlspecialchars($ac['airline_company']) ?></td>
    <td><a href="flights.php?del_aircraft=<?= $ac['aircraft_id'] ?>" class="btn red" onclick="return confirm('Delete this aircraft?')">Delete</a></td>
  </tr>
  <?php endwhile; ?>
</table></div>
<?php else: ?><p style="color:#888;font-size:13px;margin-top:8px">No aircraft yet.</p><?php endif; ?>
<hr>

<h3><?= $edit ? "Edit Flight" : "Step 3 — Add New Flight" ?></h3>
<form method="POST" action="flights.php<?= $edit ? '?edit='.$edit['flight_id'] : '' ?>">
  <?php if ($edit): ?><input type="hidden" name="flight_id" value="<?= $edit['flight_id'] ?>"><?php endif; ?>
  <div class="row2">
    <div>
      <label>Departure Airport (From)</label>
      <select name="departure_airport_id" required>
        <option value="">-- Select --</option>
        <?php mysqli_data_seek($airports_dd, 0); while ($a = mysqli_fetch_assoc($airports_dd)): ?>
        <option value="<?= $a['airport_id'] ?>" <?= ($edit && $edit['departure_airport_id']==$a['airport_id']) ? 'selected' : '' ?>>
          <?= htmlspecialchars($a['airport_name'].' ('.$a['airport_code'].') — '.$a['city']) ?>
        </option>
        <?php endwhile; ?>
      </select>
    </div>
    <div>
      <label>Arrival Airport (To)</label>
      <select name="arrival_airport_id" required>
        <option value="">-- Select --</option>
        <?php mysqli_data_seek($airports_dd, 0); while ($a = mysqli_fetch_assoc($airports_dd)): ?>
        <option value="<?= $a['airport_id'] ?>" <?= ($edit && $edit['arrival_airport_id']==$a['airport_id']) ? 'selected' : '' ?>>
          <?= htmlspecialchars($a['airport_name'].' ('.$a['airport_code'].') — '.$a['city']) ?>
        </option>
        <?php endwhile; ?>
      </select>
    </div>
  </div>
  <div class="row2">
    <div>
      <label>Departure Date &amp; Time</label>
      <input type="datetime-local" name="departure_time" required
        value="<?= $edit ? str_replace(' ','T',$edit['departure_time']) : '' ?>">
    </div>
    <div>
      <label>Arrival Date &amp; Time</label>
      <input type="datetime-local" name="arrival_time" required
        value="<?= $edit ? str_replace(' ','T',$edit['arrival_time']) : '' ?>">
    </div>
  </div>
  <div class="row2">
    <div>
      <label>Aircraft</label>
      <select name="aircraft_id" required>
        <option value="">-- Select --</option>
        <?php mysqli_data_seek($aircrafts_dd, 0); while ($ac = mysqli_fetch_assoc($aircrafts_dd)): ?>
        <option value="<?= $ac['aircraft_id'] ?>" <?= ($edit && $edit['aircraft_id']==$ac['aircraft_id']) ? 'selected' : '' ?>>
          <?= htmlspecialchars($ac['model'].' — '.$ac['capacity'].' seats — '.$ac['airline_company']) ?>
        </option>
        <?php endwhile; ?>
      </select>
    </div>
    <div>
      <label>Flight Status</label>
      <select name="flight_status">
        <?php foreach (['Scheduled','Delayed','Cancelled'] as $s): ?>
        <option value="<?= $s ?>" <?= ($edit && $edit['flight_status']==$s) ? 'selected' : '' ?>><?= $s ?></option>
        <?php endforeach; ?>
      </select>
    </div>
  </div>

  <!-- BASE PRICE set by admin -->
  <div class="row2">
    <div>
      <label>Base Ticket Price — Economy ($)</label>
      <input type="number" name="base_price" required step="0.01" min="1"
        placeholder="e.g. 200.00"
        value="<?= $edit ? $edit['base_price'] : '' ?>">
      <small>Business = base + $150 &nbsp;|&nbsp; First Class = base + $250 &nbsp;(applied automatically at booking)</small>
    </div>
  </div>

  <br>
  <?php if ($edit): ?>
  <input type="submit" name="update" value="Update Flight" class="btn blue">
  <a href="flights.php" class="btn grey">Cancel</a>
  <?php else: ?>
  <input type="submit" name="add" value="Add Flight" class="btn blue">
  <?php endif; ?>
</form>
<hr>

<h3>All Flights</h3>
<div class="tbl-wrap"><table>
  <tr>
    <th>ID</th><th>From</th><th>To</th><th>Departure</th><th>Arrival</th>
    <th>Duration (derived)</th><th>Aircraft</th><th>Capacity</th>
    <th>Seats Available (derived)</th>
    <th>Economy Price</th><th>Business Price</th><th>First Class Price</th>
    <th>Status</th><th>Actions</th>
  </tr>
  <?php if (mysqli_num_rows($flights) === 0): ?>
  <tr><td colspan="14" style="text-align:center;color:#888">No flights yet.</td></tr>
  <?php endif;
  while ($row = mysqli_fetch_assoc($flights)):
    $hrs   = floor($row['duration_mins'] / 60);
    $mins  = $row['duration_mins'] % 60;
    $avail = max(0, $row['available_seats']);
    $bp    = $row['base_price'];
  ?>
  <tr>
    <td><?= $row['flight_id'] ?></td>
    <td><?= htmlspecialchars($row['dep_name']) ?> (<?= $row['dep_code'] ?>)</td>
    <td><?= htmlspecialchars($row['arr_name']) ?> (<?= $row['arr_code'] ?>)</td>
    <td><?= $row['departure_time'] ?></td>
    <td><?= $row['arrival_time'] ?></td>
    <td><?= $hrs ?>h <?= $mins ?>m</td>
    <td><?= htmlspecialchars($row['model']) ?></td>
    <td><?= $row['capacity'] ?></td>
    <td style="color:<?= $avail > 0 ? 'green' : 'red' ?>"><?= $avail ?></td>
    <td>$<?= number_format($bp, 2) ?></td>
    <td>$<?= number_format($bp + 150, 2) ?></td>
    <td>$<?= number_format($bp + 250, 2) ?></td>
    <td><?= $row['flight_status'] ?></td>
    <td>
      <a href="flights.php?edit=<?= $row['flight_id'] ?>" class="btn orange">Edit</a>
      <a href="flights.php?delete=<?= $row['flight_id'] ?>" class="btn red" onclick="return confirm('Delete this flight?')">Delete</a>
    </td>
  </tr>
  <?php endwhile; ?>
</table></div>
</div>
</body>
</html>
