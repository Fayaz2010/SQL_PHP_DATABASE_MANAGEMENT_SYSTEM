<?php
// Feature D: Booking & Ticket
// Change: ticket price derived from flight base_price + class surcharge
//         passenger cannot set price — shown read-only, calculated server-side
require_once '../auth.php';
requireRole(['admin','passenger']);
require_once '../db.php';
$isAdmin = isAdmin();
$myId    = currentUserId();
$msg     = "";

// ── DELETE (admin only) ───────────────────────────────────────
if ($isAdmin && isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    mysqli_query($conn, "DELETE FROM bookings WHERE booking_id=$id");
    $msg = "ok:Booking and its ticket/payment deleted.";
}

// ── CANCEL ────────────────────────────────────────────────────
if (isset($_GET['cancel'])) {
    $id  = (int)$_GET['cancel'];
    $chk = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT passenger_id FROM bookings WHERE booking_id=$id"));
    if ($isAdmin || ($chk && $chk['passenger_id'] == $myId)) {
        mysqli_query($conn, "UPDATE bookings SET booking_status='Cancelled' WHERE booking_id=$id");
        mysqli_query($conn, "UPDATE payments SET payment_status='Refunded' WHERE booking_id=$id");
        $msg = "ok:Booking cancelled. Payment marked as Refunded.";
    } else { $msg = "err:Access denied."; }
}

// ── MAKE BOOKING ──────────────────────────────────────────────
if (isset($_POST['book'])) {
    $fid     = (int)$_POST['flight_id'];
    $seat    = mysqli_real_escape_string($conn, trim($_POST['seat_number']));
    $cls     = mysqli_real_escape_string($conn, $_POST['seat_class']);
    $pass_id = $isAdmin ? (int)$_POST['passenger_id'] : $myId;

    if (!$fid || !$seat || !$cls) {
        $msg = "err:All fields are required.";
    } else {
        // Derive price from flight base_price + class surcharge
        $flt_row = mysqli_fetch_assoc(mysqli_query($conn,
            "SELECT base_price FROM flights WHERE flight_id=$fid"));
        if (!$flt_row) { $msg = "err:Flight not found."; }
        else {
            $base  = (float)$flt_row['base_price'];
            $price = $base;
            if ($cls === 'Business')    $price = $base + 150;
            if ($cls === 'First Class') $price = $base + 250;

            // Check seat taken
            $taken = mysqli_fetch_assoc(mysqli_query($conn,
                "SELECT booking_id FROM bookings WHERE flight_id=$fid AND seat_number='$seat' AND booking_status!='Cancelled'"));
            if ($taken) { $msg = "err:Seat $seat is already taken on this flight."; }
            else {
                // Check availability
                $avail_row = mysqli_fetch_assoc(mysqli_query($conn,
                    "SELECT ac.capacity,(ac.capacity-COUNT(b.booking_id)) AS available
                     FROM flights f JOIN aircraft ac ON f.aircraft_id=ac.aircraft_id
                     LEFT JOIN bookings b ON f.flight_id=b.flight_id AND b.booking_status='Confirmed'
                     WHERE f.flight_id=$fid GROUP BY f.flight_id"));
                if ($avail_row && $avail_row['available'] <= 0) {
                    $msg = "err:This flight is fully booked.";
                } else {
                    $today = date('Y-m-d');
                    $r = mysqli_query($conn,
                        "INSERT INTO bookings (passenger_id,flight_id,booking_date,seat_number,booking_status)
                         VALUES ($pass_id,$fid,'$today','$seat','Confirmed')");
                    if ($r) {
                        $bk_id = mysqli_insert_id($conn);
                        mysqli_query($conn,
                            "INSERT INTO tickets (booking_id,ticket_price,seat_class,issue_date)
                             VALUES ($bk_id,$price,'$cls','$today')");
                        $msg = "ok:Booking confirmed! Seat $seat — $cls — Price: \$".number_format($price,2);
                    } else { $msg = "err:Error creating booking."; }
                }
            }
        }
    }
}

// Pre-fill flight from search page
$pflt = isset($_GET['flight_id']) ? (int)$_GET['flight_id'] : 0;

// Fetch base_price for pre-selected flight (for JS price preview)
$prefill_price = 0;
if ($pflt) {
    $pr = mysqli_fetch_assoc(mysqli_query($conn, "SELECT base_price FROM flights WHERE flight_id=$pflt"));
    if ($pr) $prefill_price = $pr['base_price'];
}

// ── FETCH BOOKINGS ────────────────────────────────────────────
$where = $isAdmin ? "" : "AND b.passenger_id=$myId";
$bookings = mysqli_query($conn,
    "SELECT b.*,t.ticket_price,t.seat_class,t.issue_date,
            CONCAT(p.first_name,' ',p.last_name) AS pax_name,
            dep.airport_code AS dep_code, arr.airport_code AS arr_code, f.departure_time
     FROM bookings b
     JOIN passengers p ON b.passenger_id=p.passenger_id
     JOIN flights f    ON b.flight_id=f.flight_id
     JOIN airports dep ON f.departure_airport_id=dep.airport_id
     JOIN airports arr ON f.arrival_airport_id=arr.airport_id
     LEFT JOIN tickets t ON b.booking_id=t.booking_id
     WHERE 1=1 $where ORDER BY b.booking_date DESC");

// Flights dropdown — include base_price for JS
$flights_q = mysqli_query($conn,
    "SELECT f.flight_id, f.departure_time, f.base_price,
            dep.airport_code AS dep_code, arr.airport_code AS arr_code
     FROM flights f
     JOIN airports dep ON f.departure_airport_id=dep.airport_id
     JOIN airports arr ON f.arrival_airport_id=arr.airport_id
     WHERE f.flight_status!='Cancelled' ORDER BY f.departure_time");

$pax_list = $isAdmin ?
    mysqli_query($conn, "SELECT passenger_id,first_name,last_name FROM passengers ORDER BY last_name") : null;
?>
<!DOCTYPE html>
<html>
<head>
  <title>Feature D — Booking</title>
  <link rel="stylesheet" href="../style.css">
</head>
<body>
<?php include '../navbar.php'; ?>
<div class="box">
  <h2>Feature D — Booking &amp; Ticket Issuance
    <span class="badge badge-<?= $isAdmin?'admin':'pass' ?>"><?= $isAdmin?'Admin':'My Bookings' ?></span>
  </h2>

  <?php if ($msg): $p = explode(":", $msg, 2); ?>
  <div class="msg <?= $p[0] ?>"><?= $p[1] ?></div>
  <?php endif; ?>

  <h3>Make a New Booking</h3>
  <form method="POST" action="booking.php">

    <?php if ($isAdmin): ?>
    <label>Passenger</label>
    <select name="passenger_id" required>
      <option value="">-- Select Passenger --</option>
      <?php while ($px = mysqli_fetch_assoc($pax_list)): ?>
      <option value="<?= $px['passenger_id'] ?>"><?= htmlspecialchars($px['first_name'].' '.$px['last_name']) ?></option>
      <?php endwhile; ?>
    </select>
    <?php endif; ?>

    <label>Flight</label>
    <select name="flight_id" required id="flight_sel" onchange="updatePrice(this)">
      <option value="">-- Select Flight --</option>
      <?php while ($f = mysqli_fetch_assoc($flights_q)): ?>
      <option value="<?= $f['flight_id'] ?>"
              data-price="<?= $f['base_price'] ?>"
              <?= ($pflt == $f['flight_id']) ? 'selected' : '' ?>>
        Flight #<?= $f['flight_id'] ?> — <?= $f['dep_code'] ?>→<?= $f['arr_code'] ?>
        (<?= $f['departure_time'] ?>) — Economy: $<?= number_format($f['base_price'],2) ?>
      </option>
      <?php endwhile; ?>
    </select>

    <div class="row2">
      <div>
        <label>Seat Number (e.g. 12A)</label>
        <input type="text" name="seat_number" required placeholder="12A" maxlength="10">
      </div>
      <div>
        <label>Seat Class</label>
        <select name="seat_class" required id="cls_sel" onchange="updatePrice(document.getElementById('flight_sel'))">
          <option value="Economy">Economy</option>
          <option value="Business">Business (+$150)</option>
          <option value="First Class">First Class (+$250)</option>
        </select>
      </div>
    </div>

    <!-- Price shown read-only — derived from flight + class -->
    <label>Ticket Price (auto-calculated — cannot be changed)</label>
    <input type="text" id="price_display"
           value="<?= $prefill_price > 0 ? '$'.number_format($prefill_price,2) : 'Select a flight to see price' ?>"
           disabled style="background:#f0f0f0;color:#333;font-weight:bold">
    <small>Economy = base price set by admin &nbsp;|&nbsp; Business = base + $150 &nbsp;|&nbsp; First Class = base + $250</small>

    <br><br>
    <input type="submit" name="book" value="Confirm Booking" class="btn green">
  </form>

  <script>
    function updatePrice(flightSel) {
      var opt   = flightSel.options[flightSel.selectedIndex];
      var base  = parseFloat(opt.getAttribute('data-price')) || 0;
      var cls   = document.getElementById('cls_sel').value;
      var price = base;
      if (cls === 'Business')    price = base + 150;
      if (cls === 'First Class') price = base + 250;
      var disp  = document.getElementById('price_display');
      disp.value = base > 0 ? '$' + price.toFixed(2) : 'Select a flight to see price';
    }
    // Run on page load in case a flight is pre-selected
    window.onload = function() {
      var sel = document.getElementById('flight_sel');
      if (sel) updatePrice(sel);
    };
  </script>

  <hr>
  <h3><?= $isAdmin ? "All Bookings" : "My Bookings &amp; Tickets" ?></h3>
  <div class="tbl-wrap"><table>
    <tr>
      <th>Booking ID</th>
      <?php if ($isAdmin): ?><th>Passenger</th><?php endif; ?>
      <th>Flight</th><th>Route</th><th>Departure</th>
      <th>Seat</th><th>Booking Date</th><th>Status</th>
      <th>Ticket Class</th><th>Price (derived)</th><th>Issue Date</th>
      <th>Actions</th>
    </tr>
    <?php if (mysqli_num_rows($bookings) === 0): ?>
    <tr><td colspan="12" style="text-align:center;color:#888">No bookings found.</td></tr>
    <?php endif;
    while ($row = mysqli_fetch_assoc($bookings)):
      $sc = $row['booking_status']==='Confirmed' ? 'green'
          : ($row['booking_status']==='Cancelled' ? 'red' : 'orange');
    ?>
    <tr>
      <td><?= $row['booking_id'] ?></td>
      <?php if ($isAdmin): ?><td><?= htmlspecialchars($row['pax_name']) ?></td><?php endif; ?>
      <td>Flight #<?= $row['flight_id'] ?></td>
      <td><?= $row['dep_code'] ?> → <?= $row['arr_code'] ?></td>
      <td><?= $row['departure_time'] ?></td>
      <td><?= htmlspecialchars($row['seat_number']) ?></td>
      <td><?= $row['booking_date'] ?></td>
      <td style="color:<?= $sc ?>;font-weight:bold"><?= $row['booking_status'] ?></td>
      <td><?= $row['seat_class'] ?: '—' ?></td>
      <td><?= $row['ticket_price'] ? '$'.number_format($row['ticket_price'],2) : '—' ?></td>
      <td><?= $row['issue_date'] ?: '—' ?></td>
      <td>
        <?php if ($row['booking_status'] === 'Confirmed'): ?>
        <a href="booking.php?cancel=<?= $row['booking_id'] ?>" class="btn orange"
           onclick="return confirm('Cancel this booking?')">Cancel</a>
        <?php endif; ?>
        <?php if ($isAdmin): ?>
        <a href="booking.php?delete=<?= $row['booking_id'] ?>" class="btn red"
           onclick="return confirm('Permanently delete?')">Delete</a>
        <?php endif; ?>
      </td>
    </tr>
    <?php endwhile; ?>
  </table></div>
</div>
</body>
</html>
