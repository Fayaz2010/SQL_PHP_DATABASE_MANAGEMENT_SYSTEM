<?php
// Feature E: Payment Processing
// Changes:
//   1. Passenger cannot set payment_status — always saved as Pending, only admin can change it
//   2. Passenger can add/update their phone numbers (multivalued attribute) from this page
require_once '../auth.php';
requireRole(['admin','passenger']);
require_once '../db.php';
$isAdmin = isAdmin();
$myId    = currentUserId();
$msg     = "";

// ── DELETE PAYMENT (admin only) ───────────────────────────────
if ($isAdmin && isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    mysqli_query($conn, "DELETE FROM payments WHERE payment_id=$id");
    $msg = "ok:Payment record deleted.";
}

// ── ADD PAYMENT ───────────────────────────────────────────────
if (isset($_POST['add_payment'])) {
    $bk_id  = (int)$_POST['booking_id'];
    $amount = (float)$_POST['payment_amount'];
    $method = mysqli_real_escape_string($conn, $_POST['payment_method']);
    $today  = date('Y-m-d');

    // Passengers always submit as Pending — admin changes it later
    $status = $isAdmin
        ? mysqli_real_escape_string($conn, $_POST['payment_status'])
        : 'Pending';

    $chk = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT passenger_id FROM bookings WHERE booking_id=$bk_id"));
    if (!$isAdmin && (!$chk || $chk['passenger_id'] != $myId)) {
        $msg = "err:Access denied.";
    } elseif (!$bk_id || $amount <= 0 || !$method) {
        $msg = "err:All fields are required.";
    } else {
        $exists = mysqli_fetch_assoc(mysqli_query($conn,
            "SELECT payment_id FROM payments WHERE booking_id=$bk_id"));
        if ($exists) {
            $msg = "err:A payment record already exists for this booking.";
        } else {
            $r = mysqli_query($conn,
                "INSERT INTO payments (booking_id,payment_amount,payment_method,payment_date,payment_status)
                 VALUES ($bk_id,$amount,'$method','$today','$status')");
            if ($r) {
                if ($status === 'Completed') {
                    mysqli_query($conn,
                        "UPDATE bookings SET booking_status='Confirmed' WHERE booking_id=$bk_id");
                }
                $msg = "ok:Payment submitted successfully. Status: $status.";
            } else { $msg = "err:Error adding payment."; }
        }
    }
}

// ── UPDATE PAYMENT STATUS (admin only) ───────────────────────
if ($isAdmin && isset($_POST['update_status'])) {
    $pid    = (int)$_POST['payment_id'];
    $status = mysqli_real_escape_string($conn, $_POST['payment_status']);
    mysqli_query($conn, "UPDATE payments SET payment_status='$status' WHERE payment_id=$pid");
    $bk = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT booking_id FROM payments WHERE payment_id=$pid"));
    if ($bk) {
        if ($status === 'Refunded')  mysqli_query($conn, "UPDATE bookings SET booking_status='Cancelled' WHERE booking_id={$bk['booking_id']}");
        if ($status === 'Completed') mysqli_query($conn, "UPDATE bookings SET booking_status='Confirmed'  WHERE booking_id={$bk['booking_id']}");
    }
    $msg = "ok:Payment status updated to $status.";
}

// ── SAVE PHONE NUMBERS (passenger only — multivalued attribute) ──
if (!$isAdmin && isset($_POST['save_phones'])) {
    $raw_phones = $_POST['phones'];
    // Delete all existing phones for this passenger then re-insert
    mysqli_query($conn, "DELETE FROM passenger_phones WHERE passenger_id=$myId");
    foreach (explode(",", $raw_phones) as $phone) {
        $phone = trim(mysqli_real_escape_string($conn, $phone));
        if ($phone !== '') {
            mysqli_query($conn,
                "INSERT INTO passenger_phones (passenger_id, phone) VALUES ($myId, '$phone')");
        }
    }
    $msg = "ok:Phone numbers saved.";
}

// ── FETCH PAYMENTS ────────────────────────────────────────────
$where = $isAdmin ? "" : "AND b.passenger_id=$myId";
$payments = mysqli_query($conn,
    "SELECT pay.*, b.seat_number, b.booking_status,
            CONCAT(p.first_name,' ',p.last_name) AS pax_name,
            dep.airport_code AS dep_code, arr.airport_code AS arr_code, f.departure_time
     FROM payments pay
     JOIN bookings b   ON pay.booking_id  = b.booking_id
     JOIN passengers p ON b.passenger_id  = p.passenger_id
     JOIN flights f    ON b.flight_id     = f.flight_id
     JOIN airports dep ON f.departure_airport_id = dep.airport_id
     JOIN airports arr ON f.arrival_airport_id   = arr.airport_id
     WHERE 1=1 $where ORDER BY pay.payment_date DESC");

// Bookings without payment yet
$bk_where = $isAdmin ? "" : "AND b.passenger_id=$myId";
$unpaid = mysqli_query($conn,
    "SELECT b.booking_id, b.seat_number,
            dep.airport_code AS dep_code, arr.airport_code AS arr_code, f.departure_time
     FROM bookings b
     JOIN flights f    ON b.flight_id = f.flight_id
     JOIN airports dep ON f.departure_airport_id = dep.airport_id
     JOIN airports arr ON f.arrival_airport_id   = arr.airport_id
     LEFT JOIN payments pay ON b.booking_id = pay.booking_id
     WHERE pay.payment_id IS NULL AND b.booking_status != 'Cancelled' $bk_where
     ORDER BY b.booking_date DESC");
$has_unpaid = mysqli_num_rows($unpaid) > 0;

// Passenger's current phones (for the phone form)
$current_phones = [];
if (!$isAdmin) {
    $pr = mysqli_query($conn,
        "SELECT phone FROM passenger_phones WHERE passenger_id=$myId ORDER BY id");
    while ($p = mysqli_fetch_assoc($pr)) $current_phones[] = $p['phone'];
}
?>
<!DOCTYPE html>
<html>
<head>
  <title>Feature E — Payments</title>
  <link rel="stylesheet" href="../style.css">
</head>
<body>
<?php include '../navbar.php'; ?>
<div class="box">
  <h2>Feature E — Payment Processing
    <span class="badge badge-<?= $isAdmin?'admin':'pass' ?>"><?= $isAdmin?'Admin':'My Payments' ?></span>
  </h2>

  <?php if ($msg): $p = explode(":", $msg, 2); ?>
  <div class="msg <?= $p[0] ?>"><?= $p[1] ?></div>
  <?php endif; ?>

  <!-- ── PASSENGER: UPDATE PHONE NUMBERS ──────────────────── -->
  <?php if (!$isAdmin): ?>
  <h3>My Phone Numbers (multivalued attribute)</h3>
  <form method="POST" action="payment.php">
    <label>Phone Numbers (separate multiple numbers by comma)</label>
    <input type="text" name="phones"
           placeholder="e.g. 01711111111, 01811222222"
           value="<?= htmlspecialchars(implode(', ', $current_phones)) ?>">
    <small>These phone numbers will appear in your travel history profile.</small>
    <br><br>
    <input type="submit" name="save_phones" value="Save Phone Numbers" class="btn blue">
  </form>
  <hr>
  <?php endif; ?>

  <!-- ── ADD PAYMENT FORM ──────────────────────────────────── -->
  <?php if ($has_unpaid): ?>
  <h3>Add Payment for a Booking</h3>
  <form method="POST" action="payment.php">
    <label>Booking (unpaid)</label>
    <select name="booking_id" required>
      <option value="">-- Select Booking --</option>
      <?php while ($bk = mysqli_fetch_assoc($unpaid)): ?>
      <option value="<?= $bk['booking_id'] ?>">
        Booking #<?= $bk['booking_id'] ?> — Seat <?= $bk['seat_number'] ?> |
        <?= $bk['dep_code'] ?>→<?= $bk['arr_code'] ?> (<?= $bk['departure_time'] ?>)
      </option>
      <?php endwhile; ?>
    </select>

    <div class="row2">
      <div>
        <label>Payment Amount ($)</label>
        <input type="number" name="payment_amount" required step="0.01" min="0.01">
      </div>
      <div>
        <label>Payment Method</label>
        <select name="payment_method" required>
          <option value="Card">Card</option>
          <option value="Cash">Cash</option>
          <option value="Online">Online</option>
        </select>
      </div>
    </div>

    <?php if ($isAdmin): ?>
    <label>Payment Status</label>
    <select name="payment_status" style="width:200px">
      <option value="Pending">Pending</option>
      <option value="Completed">Completed</option>
    </select>
    <small>Setting Completed will automatically confirm the booking.</small>
    <?php else: ?>
    <!-- Passenger cannot set status — always Pending -->
    <div class="msg info" style="margin-top:10px">
      Your payment will be submitted as <b>Pending</b>. The admin will confirm it.
    </div>
    <?php endif; ?>

    <br><br>
    <input type="submit" name="add_payment" value="Submit Payment" class="btn blue">
  </form>
  <hr>
  <?php else: ?>
  <div class="msg info">All your bookings already have a payment record.</div>
  <?php endif; ?>

  <!-- ── PAYMENTS TABLE ────────────────────────────────────── -->
  <h3><?= $isAdmin ? "All Payment Records" : "My Payment Records" ?></h3>
  <div class="tbl-wrap"><table>
    <tr>
      <th>Pay ID</th>
      <?php if ($isAdmin): ?><th>Passenger</th><?php endif; ?>
      <th>Booking</th><th>Route</th><th>Departure</th>
      <th>Amount</th><th>Method</th><th>Date</th>
      <th>Payment Status</th><th>Booking Status</th>
      <?php if ($isAdmin): ?><th>Update Status</th><th>Delete</th><?php endif; ?>
    </tr>
    <?php if (mysqli_num_rows($payments) === 0): ?>
    <tr><td colspan="12" style="text-align:center;color:#888">No payment records found.</td></tr>
    <?php endif;
    while ($row = mysqli_fetch_assoc($payments)):
      $pc = $row['payment_status']==='Completed' ? 'green'
          : ($row['payment_status']==='Refunded' ? 'red' : 'orange');
    ?>
    <tr>
      <td><?= $row['payment_id'] ?></td>
      <?php if ($isAdmin): ?><td><?= htmlspecialchars($row['pax_name']) ?></td><?php endif; ?>
      <td>#<?= $row['booking_id'] ?></td>
      <td><?= $row['dep_code'] ?> → <?= $row['arr_code'] ?></td>
      <td><?= $row['departure_time'] ?></td>
      <td>$<?= number_format($row['payment_amount'], 2) ?></td>
      <td><?= $row['payment_method'] ?></td>
      <td><?= $row['payment_date'] ?></td>
      <td style="color:<?= $pc ?>;font-weight:bold"><?= $row['payment_status'] ?></td>
      <td><?= $row['booking_status'] ?></td>
      <?php if ($isAdmin): ?>
      <td>
        <form method="POST" action="payment.php" style="display:flex;gap:5px;align-items:center">
          <input type="hidden" name="payment_id" value="<?= $row['payment_id'] ?>">
          <select name="payment_status" style="padding:4px;font-size:13px;width:110px">
            <?php foreach (['Pending','Completed','Refunded'] as $st): ?>
            <option value="<?= $st ?>" <?= $row['payment_status']===$st?'selected':'' ?>><?= $st ?></option>
            <?php endforeach; ?>
          </select>
          <input type="submit" name="update_status" value="Save" class="btn blue"
                 style="padding:4px 9px;font-size:13px">
        </form>
      </td>
      <td>
        <a href="payment.php?delete=<?= $row['payment_id'] ?>" class="btn red"
           onclick="return confirm('Delete this payment record?')">Delete</a>
      </td>
      <?php endif; ?>
    </tr>
    <?php endwhile; ?>
  </table></div>
</div>
</body>
</html>
