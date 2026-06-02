<?php
// Feature G: Passenger Travel History
// Admin: search and view any passenger's full journey
// Passenger: view own history only
// Covers: MAKES, FOR_FLIGHT, HAS_TICKET, HAS_PAYMENT, CONTAINS — all traced from one passenger
require_once '../auth.php';
requireRole(['admin','passenger']);
require_once '../db.php';
$isAdmin = isAdmin();
$myId    = currentUserId();

// Admin can look up any passenger by ID or name
$search_pax = null;
$history    = [];
$pax_info   = null;

if ($isAdmin && isset($_GET['passenger_id']) && $_GET['passenger_id'] != '') {
    $pid = (int)$_GET['passenger_id'];
    $pax_info = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM passengers WHERE passenger_id=$pid"));
} elseif (!$isAdmin) {
    $pid = $myId;
    $pax_info = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM passengers WHERE passenger_id=$pid"));
}

if ($pax_info) {
    $pid = (int)$pax_info['passenger_id'];

    // Fetch all bookings for this passenger with full joined data
    $history = mysqli_query($conn,
        "SELECT b.*,
                dep.airport_name AS dep_name, dep.city AS dep_city, dep.airport_code AS dep_code,
                arr.airport_name AS arr_name, arr.city AS arr_city, arr.airport_code AS arr_code,
                f.departure_time, f.arrival_time, f.flight_status,
                ac.model AS aircraft_model,
                t.ticket_price, t.seat_class, t.issue_date,
                pay.payment_amount, pay.payment_method, pay.payment_date, pay.payment_status,
                TIMESTAMPDIFF(MINUTE, f.departure_time, f.arrival_time) AS duration_mins,
                COUNT(bg.bag_number) AS baggage_count
         FROM bookings b
         JOIN flights f    ON b.flight_id = f.flight_id
         JOIN airports dep ON f.departure_airport_id = dep.airport_id
         JOIN airports arr ON f.arrival_airport_id   = arr.airport_id
         JOIN aircraft ac  ON f.aircraft_id = ac.aircraft_id
         LEFT JOIN tickets  t   ON b.booking_id = t.booking_id
         LEFT JOIN payments pay ON b.booking_id = pay.booking_id
         LEFT JOIN baggage  bg  ON b.booking_id = bg.booking_id
         WHERE b.passenger_id = $pid
         GROUP BY b.booking_id
         ORDER BY b.booking_date DESC");

    // Fetch passenger phones
    $phones_r = mysqli_query($conn, "SELECT phone FROM passenger_phones WHERE passenger_id=$pid");
    $phones = [];
    while ($ph = mysqli_fetch_assoc($phones_r)) $phones[] = $ph['phone'];
}

// Passenger list for admin dropdown
$pax_list = $isAdmin ? mysqli_query($conn, "SELECT passenger_id,first_name,last_name,email FROM passengers ORDER BY last_name") : null;

function calcAge($dob) { return date_diff(date_create($dob), date_create('today'))->y; }
?>
<!DOCTYPE html>
<html>
<head>
  <title>Feature G — Travel History</title>
  <link rel="stylesheet" href="../style.css">
</head>
<body>
<?php include '../navbar.php'; ?>
<div class="box">
  <h2>Feature G — Passenger Travel History
    <span class="badge badge-<?= $isAdmin?'admin':'pass' ?>"><?= $isAdmin?'Admin':'My History' ?></span>
  </h2>
  <p style="font-size:13px;color:#555;margin-bottom:14px">
    Read-only view. Traces all relationships outward from one passenger:
    MAKES → FOR_FLIGHT → HAS_TICKET → HAS_PAYMENT → CONTAINS (baggage).
  </p>

  <!-- Admin: passenger search -->
  <?php if ($isAdmin): ?>
  <h3>Look Up a Passenger</h3>
  <form method="GET" action="history.php">
    <label>Select Passenger</label>
    <select name="passenger_id" style="width:400px">
      <option value="">-- Select a passenger --</option>
      <?php while ($px = mysqli_fetch_assoc($pax_list)): ?>
      <option value="<?= $px['passenger_id'] ?>"
        <?= (isset($_GET['passenger_id']) && $_GET['passenger_id']==$px['passenger_id']) ? 'selected' : '' ?>>
        #<?= $px['passenger_id'] ?> — <?= htmlspecialchars($px['first_name'].' '.$px['last_name']) ?> (<?= htmlspecialchars($px['email']) ?>)
      </option>
      <?php endwhile; ?>
    </select>
    <br><br>
    <input type="submit" value="View History" class="btn blue">
  </form>
  <hr>
  <?php endif; ?>

  <!-- Passenger profile summary -->
  <?php if ($pax_info): ?>
  <h3>Passenger Profile</h3>
  <table style="max-width:600px">
    <tr><th style="width:200px">Field</th><th>Value</th></tr>
    <tr><td>Passenger ID</td><td><?= $pax_info['passenger_id'] ?></td></tr>
    <tr><td>Full Name (composite)</td><td><?= htmlspecialchars($pax_info['first_name'].' '.$pax_info['last_name']) ?></td></tr>
    <tr><td>Date of Birth</td><td><?= $pax_info['date_of_birth'] ?></td></tr>
    <tr><td>Age (derived)</td><td><?= calcAge($pax_info['date_of_birth']) ?> years</td></tr>
    <tr><td>Email</td><td><?= htmlspecialchars($pax_info['email']) ?></td></tr>
    <tr><td>Phone(s) (multivalued)</td><td><?= $phones ? htmlspecialchars(implode(', ', $phones)) : '—' ?></td></tr>
    <tr><td>Passport Number</td><td><?= htmlspecialchars($pax_info['passport_number']) ?></td></tr>
    <tr><td>Nationality</td><td><?= htmlspecialchars($pax_info['nationality']) ?></td></tr>
  </table>
  <hr>

  <!-- Full journey history -->
  <h3>Full Journey History (<?= mysqli_num_rows($history) ?> booking(s))</h3>
  <?php if (mysqli_num_rows($history) === 0): ?>
  <div class="msg info">This passenger has no bookings yet.</div>
  <?php else:
  $total_spent = 0;
  $booking_rows = [];
  while ($row = mysqli_fetch_assoc($history)) $booking_rows[] = $row;
  foreach ($booking_rows as $row):
    $hrs  = floor($row['duration_mins'] / 60);
    $mins = $row['duration_mins'] % 60;
    $bk_color = $row['booking_status']==='Confirmed' ? '#004400'
              : ($row['booking_status']==='Cancelled' ? '#880000' : '#664400');
    if ($row['payment_amount']) $total_spent += $row['payment_amount'];
  ?>
  <div style="border:1px solid #ccd;border-radius:4px;padding:14px;margin-bottom:16px;background:#fafbff">

    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px">
      <b style="font-size:15px;color:#002060">
        Booking #<?= $row['booking_id'] ?> — <?= htmlspecialchars($row['dep_name']) ?> → <?= htmlspecialchars($row['arr_name']) ?>
      </b>
      <span style="background:<?= $bk_color ?>;color:white;padding:3px 10px;border-radius:10px;font-size:13px">
        <?= $row['booking_status'] ?>
      </span>
    </div>

    <table style="font-size:13px;margin-bottom:8px">
      <tr>
        <td style="padding:4px 12px 4px 0"><b>Route</b></td>
        <td style="padding:4px 12px"><?= $row['dep_code'] ?> (<?= htmlspecialchars($row['dep_city']) ?>) → <?= $row['arr_code'] ?> (<?= htmlspecialchars($row['arr_city']) ?>)</td>
        <td style="padding:4px 12px"><b>Departure</b></td>
        <td style="padding:4px 0"><?= $row['departure_time'] ?></td>
      </tr>
      <tr>
        <td style="padding:4px 12px 4px 0"><b>Arrival</b></td>
        <td style="padding:4px 12px"><?= $row['arrival_time'] ?></td>
        <td style="padding:4px 12px"><b>Duration (derived)</b></td>
        <td style="padding:4px 0"><?= $hrs ?>h <?= $mins ?>m</td>
      </tr>
      <tr>
        <td style="padding:4px 12px 4px 0"><b>Aircraft</b></td>
        <td style="padding:4px 12px"><?= htmlspecialchars($row['aircraft_model']) ?></td>
        <td style="padding:4px 12px"><b>Flight Status</b></td>
        <td style="padding:4px 0"><?= $row['flight_status'] ?></td>
      </tr>
      <tr>
        <td style="padding:4px 12px 4px 0"><b>Seat No.</b></td>
        <td style="padding:4px 12px"><?= htmlspecialchars($row['seat_number']) ?></td>
        <td style="padding:4px 12px"><b>Booking Date</b></td>
        <td style="padding:4px 0"><?= $row['booking_date'] ?></td>
      </tr>
    </table>

    <!-- Ticket (HAS_TICKET 1:1) -->
    <div style="background:#eef5ee;padding:8px 12px;border-left:3px solid #006600;margin-bottom:7px;font-size:13px">
      <b>Ticket (HAS_TICKET — 1:1):</b>&nbsp;
      <?php if ($row['ticket_price']): ?>
        Class: <?= htmlspecialchars($row['seat_class']) ?> &nbsp;|&nbsp;
        Price: $<?= number_format($row['ticket_price'], 2) ?> &nbsp;|&nbsp;
        Issued: <?= $row['issue_date'] ?>
      <?php else: ?>
        <span style="color:#888">No ticket issued yet.</span>
      <?php endif; ?>
    </div>

    <!-- Payment (HAS_PAYMENT 1:1) -->
    <div style="background:#eef0ff;padding:8px 12px;border-left:3px solid #000088;margin-bottom:7px;font-size:13px">
      <b>Payment (HAS_PAYMENT — 1:1):</b>&nbsp;
      <?php if ($row['payment_amount']): ?>
        Amount: $<?= number_format($row['payment_amount'], 2) ?> &nbsp;|&nbsp;
        Method: <?= $row['payment_method'] ?> &nbsp;|&nbsp;
        Date: <?= $row['payment_date'] ?> &nbsp;|&nbsp;
        Status: <span style="color:<?= $row['payment_status']==='Completed'?'green':($row['payment_status']==='Refunded'?'red':'orange') ?>;font-weight:bold"><?= $row['payment_status'] ?></span>
      <?php else: ?>
        <span style="color:#888">No payment record yet.</span>
      <?php endif; ?>
    </div>

    <!-- Baggage (CONTAINS identifying relationship) -->
    <?php
    $bags = mysqli_query($conn, "SELECT * FROM baggage WHERE booking_id={$row['booking_id']} ORDER BY bag_number");
    $bag_count = mysqli_num_rows($bags);
    ?>
    <div style="background:#fff5e6;padding:8px 12px;border-left:3px solid #cc6600;font-size:13px">
      <b>Baggage (CONTAINS — weak entity — <?= $bag_count ?> item(s)):</b>&nbsp;
      <?php if ($bag_count === 0): ?>
        <span style="color:#888">No checked baggage for this booking.</span>
      <?php else: ?>
        <?php while ($bg = mysqli_fetch_assoc($bags)): ?>
          Bag #<?= $bg['bag_number'] ?> (partial key): <?= $bg['weight'] ?> kg —
          <span style="color:<?= $bg['baggage_status']==='Delivered'?'green':($bg['baggage_status']==='Lost'?'red':'orange') ?>">
            <?= $bg['baggage_status'] ?>
          </span> &nbsp;
        <?php endwhile; ?>
      <?php endif; ?>
    </div>

  </div>
  <?php endforeach; ?>

  <!-- Summary -->
  <div style="background:#f0f0f0;padding:12px 16px;border:1px solid #ccc;font-size:14px;margin-top:8px">
    <b>Summary:</b> &nbsp;
    Total bookings: <?= count($booking_rows) ?> &nbsp;|&nbsp;
    Total spent: $<?= number_format($total_spent, 2) ?>
  </div>
  <?php endif; ?>

  <?php elseif ($isAdmin): ?>
  <div class="msg info">Select a passenger above to view their travel history.</div>
  <?php endif; ?>

</div>
</body>
</html>
