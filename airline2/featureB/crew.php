<?php
// Feature B: Crew Assignment — Admin assigns, Employee views own
require_once '../auth.php';
requireRole(['admin','employee']);
require_once '../db.php';
$isAdmin = isAdmin();
$myId    = currentUserId();
$msg     = "";

if ($isAdmin && isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    mysqli_query($conn, "DELETE FROM crew_assignment WHERE assignment_id=$id");
    $msg = "ok:Assignment removed.";
}
if ($isAdmin && isset($_POST['add'])) {
    $eid  = (int)$_POST['employee_id'];
    $fid  = (int)$_POST['flight_id'];
    $acid = (int)$_POST['aircraft_id'];
    $role = mysqli_real_escape_string($conn, $_POST['role']);
    $date = mysqli_real_escape_string($conn, $_POST['assignment_date']);
    if ($eid && $fid && $acid && $role && $date) {
        $r = mysqli_query($conn, "INSERT INTO crew_assignment (employee_id,flight_id,aircraft_id,role,assignment_date) VALUES ($eid,$fid,$acid,'$role','$date')");
        $msg = $r ? "ok:Crew member assigned." : "err:Error assigning crew.";
    } else { $msg = "err:All fields are required."; }
}

if ($isAdmin) {
    $assignments = mysqli_query($conn,
        "SELECT ca.*,CONCAT(e.first_name,' ',e.last_name) AS emp_name,e.role AS emp_role,
                dep.airport_code AS dep_code,arr.airport_code AS arr_code,f.departure_time,ac.model
         FROM crew_assignment ca JOIN employees e ON ca.employee_id=e.employee_id
         JOIN flights f ON ca.flight_id=f.flight_id
         JOIN airports dep ON f.departure_airport_id=dep.airport_id
         JOIN airports arr ON f.arrival_airport_id=arr.airport_id
         JOIN aircraft ac ON ca.aircraft_id=ac.aircraft_id ORDER BY ca.assignment_date DESC");
} else {
    $assignments = mysqli_query($conn,
        "SELECT ca.*,CONCAT(e.first_name,' ',e.last_name) AS emp_name,e.role AS emp_role,
                dep.airport_code AS dep_code,arr.airport_code AS arr_code,f.departure_time,ac.model
         FROM crew_assignment ca JOIN employees e ON ca.employee_id=e.employee_id
         JOIN flights f ON ca.flight_id=f.flight_id
         JOIN airports dep ON f.departure_airport_id=dep.airport_id
         JOIN airports arr ON f.arrival_airport_id=arr.airport_id
         JOIN aircraft ac ON ca.aircraft_id=ac.aircraft_id
         WHERE ca.employee_id=$myId ORDER BY ca.assignment_date DESC");
}
$employees = mysqli_query($conn, "SELECT employee_id,first_name,last_name,role FROM employees ORDER BY last_name");
$flights   = mysqli_query($conn,
    "SELECT f.flight_id,f.departure_time,f.aircraft_id,dep.airport_code AS dep_code,arr.airport_code AS arr_code
     FROM flights f JOIN airports dep ON f.departure_airport_id=dep.airport_id
     JOIN airports arr ON f.arrival_airport_id=arr.airport_id
     WHERE f.flight_status!='Cancelled' ORDER BY f.departure_time");
?>
<!DOCTYPE html><html><head><title>Feature B — Crew</title><link rel="stylesheet" href="../style.css"></head><body>
<?php include '../navbar.php'; ?>
<div class="box">
<h2>Feature B — Crew Assignment Manager <span class="badge badge-<?= $isAdmin?'admin':'emp' ?>"><?= $isAdmin?'Admin':'Your Assignments' ?></span></h2>
<?php if ($msg): $p=explode(":",$msg,2); ?><div class="msg <?= $p[0] ?>"><?= $p[1] ?></div><?php endif; ?>

<?php if ($isAdmin): ?>
<h3>Assign Crew to Flight (Ternary: Employee + Flight + Aircraft)</h3>
<form method="POST" action="crew.php">
  <div class="row2">
    <div><label>Employee</label><select name="employee_id" required><option value="">-- Select Employee --</option>
      <?php while ($e=mysqli_fetch_assoc($employees)): ?>
      <option value="<?= $e['employee_id'] ?>"><?= htmlspecialchars($e['first_name'].' '.$e['last_name'].' ('.$e['role'].')') ?></option>
      <?php endwhile; ?></select></div>
    <div><label>Flight</label><select name="flight_id" required id="fsel" onchange="setAircraft(this)"><option value="">-- Select Flight --</option>
      <?php while ($f=mysqli_fetch_assoc($flights)): ?>
      <option value="<?= $f['flight_id'] ?>" data-ac="<?= $f['aircraft_id'] ?>">Flight #<?= $f['flight_id'] ?> — <?= $f['dep_code'] ?>→<?= $f['arr_code'] ?> (<?= $f['departure_time'] ?>)</option>
      <?php endwhile; ?></select></div>
  </div>
  <div class="row2">
    <div><label>Aircraft ID (auto-filled from flight)</label><input type="number" name="aircraft_id" id="acid" required placeholder="Select a flight first"></div>
    <div><label>Crew Role for this Flight</label><input type="text" name="role" required placeholder="e.g. Captain, Purser"></div>
  </div>
  <label>Assignment Date</label><input type="date" name="assignment_date" required value="<?= date('Y-m-d') ?>" style="width:200px">
  <br><br><input type="submit" name="add" value="Assign Crew" class="btn blue">
</form>
<script>function setAircraft(s){var o=s.options[s.selectedIndex];document.getElementById('acid').value=o.getAttribute('data-ac')||'';}</script>
<hr>
<?php endif; ?>

<h3><?= $isAdmin?"All Crew Assignments":"Your Flight Assignments" ?></h3>
<div class="tbl-wrap"><table>
  <tr><th>ID</th><th>Employee</th><th>Emp. Role</th><th>Flight</th><th>Route</th><th>Departure</th><th>Aircraft</th><th>Crew Role (rel.attr)</th><th>Assigned Date (rel.attr)</th><?php if ($isAdmin): ?><th>Remove</th><?php endif; ?></tr>
  <?php if (mysqli_num_rows($assignments)===0): ?><tr><td colspan="10" style="text-align:center;color:#888">No assignments.</td></tr><?php endif;
  while ($row=mysqli_fetch_assoc($assignments)): ?>
  <tr>
    <td><?= $row['assignment_id'] ?></td>
    <td><?= htmlspecialchars($row['emp_name']) ?></td>
    <td><?= htmlspecialchars($row['emp_role']) ?></td>
    <td>Flight #<?= $row['flight_id'] ?></td>
    <td><?= $row['dep_code'] ?> → <?= $row['arr_code'] ?></td>
    <td><?= $row['departure_time'] ?></td>
    <td><?= htmlspecialchars($row['model']) ?></td>
    <td><?= htmlspecialchars($row['role']) ?></td>
    <td><?= $row['assignment_date'] ?></td>
    <?php if ($isAdmin): ?><td><a href="crew.php?delete=<?= $row['assignment_id'] ?>" class="btn red" onclick="return confirm('Remove?')">Remove</a></td><?php endif; ?>
  </tr>
  <?php endwhile; ?>
</table></div>
</div></body></html>
