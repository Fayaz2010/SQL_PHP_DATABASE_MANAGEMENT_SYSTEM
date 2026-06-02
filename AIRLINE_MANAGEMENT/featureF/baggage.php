<?php
// Feature F: Baggage Check-In
// Covers: Baggage WEAK ENTITY, partial key bag_number,
//         CONTAINS identifying relationship (Booking owns Baggage)
// Access: Admin (all bookings), Passenger (own bookings only)
require_once '../auth.php';
requireRole(['admin','passenger']);
require_once '../db.php';
$isAdmin = isAdmin();
$myId    = currentUserId();
$msg     = "";

// ── DELETE BAGGAGE ITEM (admin only) ──────────────────────────
if ($isAdmin && isset($_GET['delete'])) {
    $bk_id  = (int)$_GET['bk_id'];
    $bag_no = (int)$_GET['bag_no'];
    mysqli_query($conn, "DELETE FROM baggage WHERE booking_id=$bk_id AND bag_number=$bag_no");
    $msg = "ok:Baggage item deleted.";
}

// ── UPDATE BAGGAGE STATUS ─────────────────────────────────────
if (isset($_POST['update_status'])) {
    $bk_id  = (int)$_POST['booking_id'];
    $bag_no = (int)$_POST['bag_number'];
    $status = mysqli_real_escape_string($conn, $_POST['baggage_status']);
    // Verify access
    $chk = mysqli_fetch_assoc(mysqli_query($conn, "SELECT passenger_id FROM bookings WHERE booking_id=$bk_id"));
    if (!$isAdmin && (!$chk || $chk['passenger_id'] != $myId)) {
        $msg = "err:Access denied.";
    } else {
        mysqli_query($conn, "UPDATE baggage SET baggage_status='$status' WHERE booking_id=$bk_id AND bag_number=$bag_no");
        $msg = "ok:Baggage status updated.";
    }
}

// ── ADD BAGGAGE ITEM ──────────────────────────────────────────
// bag_number is the partial key — system assigns next number within booking
if (isset($_POST['add_baggage'])) {
    $bk_id  = (int)$_POST['booking_id'];
    $weight = (float)$_POST['weight'];
    $status = mysqli_real_escape_string($conn, $_POST['baggage_status']);

    $chk = mysqli_fetch_assoc(mysqli_query($conn, "SELECT passenger_id FROM bookings WHERE booking_id=$bk_id"));
    if (!$isAdmin && (!$chk || $chk['passenger_id'] != $myId)) {
        $msg = "err:Access denied.";
    } elseif ($bk_id <= 0 || $weight <= 0) {
        $msg = "err:Select a booking and enter a valid weight.";
    } else {
        // Get next bag_number for this booking (partial key auto-increment within booking)
        $max = mysqli_fetch_assoc(mysqli_query($conn,
            "SELECT COALESCE(MAX(bag_number),0)+1 AS next_bag FROM baggage WHERE booking_id=$bk_id"));
        $next_bag = (int)$max['next_bag'];
        $r = mysqli_query($conn,
            "INSERT INTO baggage (booking_id,bag_number,weight,baggage_status) VALUES ($bk_id,$next_bag,$weight,'$status')");
        $msg = $r ? "ok:Baggage item #$next_bag added to booking #$bk_id." : "err:Error adding baggage.";
    }
}

// ── FETCH BOOKINGS for dropdown ────────────────────────────────
$bk_where = $isAdmin ? "" : "AND b.passenger_id=$myId";
$confirmed_bookings = mysqli_query($conn,
    "SELECT b.booking_id, b.seat_number, dep.airport_code AS dep_code, arr.airport_code AS arr_code, f.departure_time
     FROM bookings b JOIN flights f ON b.flight_id=f.flight_id
     JOIN airports dep ON f.departure_airport_id=dep.airport_id
     JOIN airports arr ON f.arrival_airport_id=arr.airport_id
     WHERE b.booking_status='Confirmed' $bk_where ORDER BY b.booking_date DESC");

// ── FETCH ALL BAGGAGE ─────────────────────────────────────────
$bag_where = $isAdmin ? "" : "AND b.passenger_id=$myId";
$all_baggage = mysqli_query($conn,
    "SELECT bg.*, b.seat_number, CONCAT(p.first_name,' ',p.last_name) AS pax_name,
            dep.airport_code AS dep_code, arr.airport_code AS arr_code, f.departure_time
     FROM baggage bg
     JOIN bookings b   ON bg.booking_id = b.booking_id
     JOIN passengers p ON b.passenger_id = p.passenger_id
     JOIN flights f    ON b.flight_id = f.flight_id
     JOIN airports dep ON f.departure_airport_id = dep.airport_id
     JOIN airports arr ON f.arrival_airport_id   = arr.airport_id
     WHERE 1=1 $bag_where
     ORDER BY bg.booking_id, bg.bag_number");

$statuses = ['Checked-In','Loaded','In Transit','Delivered','Lost'];
?>
<!DOCTYPE html>
<html>
<head>
  <title>Feature F — Baggage</title>
  <link rel="stylesheet" href="../style.css">
</head>
<body>
<?php include '../navbar.php'; ?>
<div class="box">
  <h2>Feature F — Baggage Check-In
    <span class="badge badge-<?= $isAdmin?'admin':'pass' ?>"><?= $isAdmin?'Admin':'My Baggage' ?></span>
  </h2>
  <p style="font-size:13px;color:#555;margin-bottom:14px">
    Baggage is a <b>weak entity</b> — each bag is identified by its <b>bag number (partial key)</b> only within its booking.
    The composite primary key is <b>(booking_id, bag_number)</b>.
    A booking may have zero or more baggage items (partial participation on Booking side).
  </p>

  <?php if ($msg): $p = explode(":", $msg, 2); ?>
  <div class="msg <?= $p[0] ?>"><?= $p[1] ?></div>
  <?php endif; ?>

  <!-- ADD BAGGAGE FORM -->
  <?php if (mysqli_num_rows($confirmed_bookings) > 0): ?>
  <h3>Add Baggage Item to a Booking</h3>
  <form method="POST" action="baggage.php">
    <label>Booking (Confirmed only)</label>
    <select name="booking_id" required>
      <option value="">-- Select Booking --</option>
      <?php while ($bk = mysqli_fetch_assoc($confirmed_bookings)): ?>
      <option value="<?= $bk['booking_id'] ?>">
        Booking #<?= $bk['booking_id'] ?> — Seat <?= $bk['seat_number'] ?> |
        <?= $bk['dep_code'] ?>→<?= $bk['arr_code'] ?> (<?= $bk['departure_time'] ?>)
      </option>
      <?php endwhile; ?>
    </select>

    <div class="row2">
      <div>
        <label>Weight (kg)</label>
        <input type="number" name="weight" required step="0.1" min="0.1" max="50" placeholder="e.g. 23.5">
      </div>
      <div>
        <label>Initial Status</label>
        <select name="baggage_status">
          <?php foreach ($statuses as $s): ?>
          <option value="<?= $s ?>" <?= $s==='Checked-In'?'selected':'' ?>><?= $s ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <small>Bag number (partial key) is assigned automatically within the booking.</small>
    <br><br>
    <input type="submit" name="add_baggage" value="Add Baggage Item" class="btn blue">
  </form>
  <hr>
  <?php else: ?>
  <div class="msg info">No confirmed bookings found to add baggage to.</div>
  <?php endif; ?>

  <!-- BAGGAGE TABLE -->
  <h3><?= $isAdmin?"All Baggage Records":"My Baggage Items" ?></h3>
  <div class="tbl-wrap">
  <table>
    <tr>
      <th>Booking ID</th>
      <th>Bag No. (Partial Key)</th>
      <?php if ($isAdmin): ?><th>Passenger</th><?php endif; ?>
      <th>Route</th>
      <th>Departure</th>
      <th>Seat</th>
      <th>Weight (kg)</th>
      <th>Status</th>
      <th>Update Status</th>
      <?php if ($isAdmin): ?><th>Delete</th><?php endif; ?>
    </tr>
    <?php if (mysqli_num_rows($all_baggage) === 0): ?>
    <tr><td colspan="10" style="text-align:center;color:#888">No baggage records found.</td></tr>
    <?php endif;
    while ($row = mysqli_fetch_assoc($all_baggage)):
      $sc = $row['baggage_status'] === 'Delivered' ? 'green'
          : ($row['baggage_status'] === 'Lost' ? 'red'
          : ($row['baggage_status'] === 'Checked-In' ? '#005577' : 'orange'));
    ?>
    <tr>
      <td>#<?= $row['booking_id'] ?></td>
      <td><b><?= $row['bag_number'] ?></b></td>
      <?php if ($isAdmin): ?><td><?= htmlspecialchars($row['pax_name']) ?></td><?php endif; ?>
      <td><?= $row['dep_code'] ?> → <?= $row['arr_code'] ?></td>
      <td><?= $row['departure_time'] ?></td>
      <td><?= htmlspecialchars($row['seat_number']) ?></td>
      <td><?= $row['weight'] ?> kg</td>
      <td style="color:<?= $sc ?>;font-weight:bold"><?= $row['baggage_status'] ?></td>
      <td>
        <form method="POST" action="baggage.php" style="display:flex;gap:5px;align-items:center">
          <input type="hidden" name="booking_id" value="<?= $row['booking_id'] ?>">
          <input type="hidden" name="bag_number" value="<?= $row['bag_number'] ?>">
          <select name="baggage_status" style="padding:4px;font-size:13px;width:115px">
            <?php foreach ($statuses as $s): ?>
            <option value="<?= $s ?>" <?= $row['baggage_status']===$s?'selected':'' ?>><?= $s ?></option>
            <?php endforeach; ?>
          </select>
          <input type="submit" name="update_status" value="Save" class="btn blue" style="padding:4px 9px;font-size:13px">
        </form>
      </td>
      <?php if ($isAdmin): ?>
      <td>
        <a href="baggage.php?delete=1&bk_id=<?= $row['booking_id'] ?>&bag_no=<?= $row['bag_number'] ?>"
           class="btn red" onclick="return confirm('Delete this baggage item?')">Delete</a>
      </td>
      <?php endif; ?>
    </tr>
    <?php endwhile; ?>
  </table>
  </div>
</div>
</body>
</html>
