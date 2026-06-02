<?php
// Feature H: Employee Hierarchy — Admin (all), Employee (own only)
require_once '../auth.php';
requireRole(['admin', 'employee']);
require_once '../db.php';
$isAdmin = isAdmin();
$myId    = (int)currentUserId();
$msg     = "";

function calcYears($hire) { return date_diff(date_create($hire), date_create('today'))->y; }

if ($isAdmin && isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    mysqli_query($conn, "UPDATE employees SET supervisor_id=NULL WHERE supervisor_id=$id");
    mysqli_query($conn, "DELETE FROM employees WHERE employee_id=$id");
    $msg = "ok:Employee deleted.";
}

if (isset($_POST['update'])) {
    $id = (int)$_POST['employee_id'];
    if (!$isAdmin && $id != $myId) {
        $msg = "err:You can only edit your own profile.";
    } else {
        $fname   = mysqli_real_escape_string($conn, trim($_POST['first_name']));
        $lname   = mysqli_real_escape_string($conn, trim($_POST['last_name']));
        $street  = mysqli_real_escape_string($conn, trim($_POST['street']));
        $city    = mysqli_real_escape_string($conn, trim($_POST['city']));
        $country = mysqli_real_escape_string($conn, trim($_POST['country']));
        $langs   = $_POST['languages'];
        if ($isAdmin) {
            $role   = mysqli_real_escape_string($conn, $_POST['role']);
            $salary = (float)$_POST['salary'];
            $hire   = mysqli_real_escape_string($conn, $_POST['hire_date']);
            $sup    = $_POST['supervisor_id'];
            if ($sup != "" && (int)$sup == $id) {
                $msg = "err:An employee cannot be their own supervisor.";
            } else {
                $sup_val = ($sup != "") ? (int)$sup : "NULL";
                $r = mysqli_query($conn,
                    "UPDATE employees SET first_name='$fname',last_name='$lname',street='$street',city='$city',country='$country',role='$role',salary=$salary,hire_date='$hire',supervisor_id=$sup_val WHERE employee_id=$id");
                if ($r) {
                    mysqli_query($conn, "DELETE FROM employee_languages WHERE employee_id=$id");
                    foreach (explode(",", $langs) as $lang) { $lang=trim(mysqli_real_escape_string($conn,$lang)); if ($lang) mysqli_query($conn,"INSERT INTO employee_languages (employee_id,language) VALUES ($id,'$lang')"); }
                    $msg = "ok:Employee updated.";
                } else { $msg = "err:Error updating."; }
            }
        } else {
            $r = mysqli_query($conn,
                "UPDATE employees SET first_name='$fname',last_name='$lname',street='$street',city='$city',country='$country' WHERE employee_id=$id");
            if ($r) {
                mysqli_query($conn, "DELETE FROM employee_languages WHERE employee_id=$id");
                foreach (explode(",", $langs) as $lang) { $lang=trim(mysqli_real_escape_string($conn,$lang)); if ($lang) mysqli_query($conn,"INSERT INTO employee_languages (employee_id,language) VALUES ($id,'$lang')"); }
                $msg = "ok:Your profile has been updated.";
            } else { $msg = "err:Error updating your profile."; }
        }
    }
}

$edit = null; $edit_langs = "";
if (isset($_GET['edit'])) {
    $eid  = $isAdmin ? (int)$_GET['edit'] : $myId;
    $edit = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM employees WHERE employee_id=$eid"));
    $lr   = mysqli_query($conn, "SELECT language FROM employee_languages WHERE employee_id=$eid");
    $ll   = []; while ($l=mysqli_fetch_assoc($lr)) $ll[]=$l['language'];
    $edit_langs = implode(", ", $ll);
}

if ($isAdmin) {
    $emp_list = mysqli_query($conn,
        "SELECT e.*,CONCAT(s.first_name,' ',s.last_name) AS supervisor_name,GROUP_CONCAT(el.language SEPARATOR ', ') AS languages FROM employees e LEFT JOIN employees s ON e.supervisor_id=s.employee_id LEFT JOIN employee_languages el ON e.employee_id=el.employee_id GROUP BY e.employee_id ORDER BY e.last_name");
} else {
    $emp_list = mysqli_query($conn,
        "SELECT e.*,CONCAT(s.first_name,' ',s.last_name) AS supervisor_name,GROUP_CONCAT(el.language SEPARATOR ', ') AS languages FROM employees e LEFT JOIN employees s ON e.supervisor_id=s.employee_id LEFT JOIN employee_languages el ON e.employee_id=el.employee_id WHERE e.employee_id=$myId GROUP BY e.employee_id");
}
$all_emp = mysqli_query($conn, "SELECT employee_id,first_name,last_name,role FROM employees ORDER BY last_name");
?>
<!DOCTYPE html><html><head><title>Feature H — Hierarchy</title><link rel="stylesheet" href="../style.css"></head><body>
<?php include '../navbar.php'; ?>
<div class="box">
<h2>Feature H — Employee Reporting Hierarchy <span class="badge badge-<?= $isAdmin?'admin':'emp' ?>"><?= $isAdmin?'Admin':'Employee' ?></span></h2>
<?php if ($msg): $p=explode(":",$msg,2); ?><div class="msg <?= $p[0] ?>"><?= $p[1] ?></div><?php endif; ?>

<?php if ($edit): ?>
<h3><?= $isAdmin?"Edit Employee":"Edit My Profile" ?></h3>
<?php if (!$isAdmin): ?><div class="msg info">You can update your name, address, and languages. Role, salary, hire date, and supervisor can only be changed by an admin.</div><?php endif; ?>
<form method="POST" action="hierarchy.php?edit=<?= $edit['employee_id'] ?>">
  <input type="hidden" name="employee_id" value="<?= $edit['employee_id'] ?>">
  <div class="row2">
    <div><label>First Name</label><input type="text" name="first_name" required value="<?= htmlspecialchars($edit['first_name']) ?>"></div>
    <div><label>Last Name</label><input type="text" name="last_name" required value="<?= htmlspecialchars($edit['last_name']) ?>"></div>
  </div>
  <label>Street</label><input type="text" name="street" value="<?= htmlspecialchars($edit['street']??'') ?>">
  <div class="row2">
    <div><label>City</label><input type="text" name="city" value="<?= htmlspecialchars($edit['city']??'') ?>"></div>
    <div><label>Country</label><input type="text" name="country" value="<?= htmlspecialchars($edit['country']??'') ?>"></div>
  </div>
  <?php if ($isAdmin): ?>
  <div class="row2">
    <div><label>Role</label><select name="role"><?php foreach(['Pilot','Co-Pilot','Cabin Crew','Ground Staff'] as $r): ?><option value="<?= $r ?>" <?= $edit['role']==$r?'selected':'' ?>><?= $r ?></option><?php endforeach; ?></select></div>
    <div><label>Salary ($)</label><input type="number" name="salary" step="0.01" value="<?= $edit['salary'] ?>"></div>
  </div>
  <div class="row2">
    <div><label>Hire Date</label><input type="date" name="hire_date" required value="<?= $edit['hire_date'] ?>"></div>
    <div><label>Supervisor (recursive — optional)</label><select name="supervisor_id">
      <option value="">— No Supervisor —</option>
      <?php mysqli_data_seek($all_emp,0); while ($e=mysqli_fetch_assoc($all_emp)): if ($e['employee_id']==$edit['employee_id']) continue; ?>
      <option value="<?= $e['employee_id'] ?>" <?= $edit['supervisor_id']==$e['employee_id']?'selected':'' ?>><?= htmlspecialchars($e['first_name'].' '.$e['last_name'].' ('.$e['role'].')') ?></option>
      <?php endwhile; ?></select></div>
  </div>
  <?php else: ?>
  <div class="row2">
    <div><label>Role</label><input type="text" value="<?= htmlspecialchars($edit['role']) ?>" disabled></div>
    <div><label>Salary</label><input type="text" value="$<?= number_format($edit['salary'],2) ?>" disabled></div>
  </div>
  <div class="row2">
    <div><label>Hire Date</label><input type="text" value="<?= $edit['hire_date'] ?>" disabled></div>
    <div><label>Years of Service (derived)</label><input type="text" value="<?= calcYears($edit['hire_date']) ?> years" disabled></div>
  </div>
  <?php endif; ?>
  <label>Languages Spoken (comma separated — multivalued)</label>
  <input type="text" name="languages" placeholder="e.g. English, Bengali" value="<?= htmlspecialchars($edit_langs) ?>">
  <br><br>
  <input type="submit" name="update" value="Save Changes" class="btn blue">
  <a href="hierarchy.php" class="btn grey">Cancel</a>
</form><hr>
<?php endif; ?>

<h3><?= $isAdmin?"All Employees":"My Profile" ?></h3>
<div class="tbl-wrap"><table>
  <tr><th>ID</th><th>Full Name</th><th>Role</th><th>Address</th><th>Hire Date</th><th>Years of Service</th><th>Languages</th><th>Supervisor</th><th>Actions</th></tr>
  <?php while ($row=mysqli_fetch_assoc($emp_list)):
    $addr=implode(', ',array_filter([$row['street']??'',$row['city']??'',$row['country']??''])); ?>
  <tr>
    <td><?= $row['employee_id'] ?></td>
    <td><?= htmlspecialchars($row['first_name'].' '.$row['last_name']) ?></td>
    <td><?= htmlspecialchars($row['role']) ?></td>
    <td><?= htmlspecialchars($addr?:'—') ?></td>
    <td><?= $row['hire_date'] ?></td>
    <td><?= calcYears($row['hire_date']) ?> yrs</td>
    <td><?= $row['languages']?htmlspecialchars($row['languages']):'—' ?></td>
    <td><?= $row['supervisor_name']?htmlspecialchars($row['supervisor_name']):'— (Top Level)' ?></td>
    <td>
      <?php if ($isAdmin || $row['employee_id']==$myId): ?><a href="hierarchy.php?edit=<?= $row['employee_id'] ?>" class="btn orange">Edit</a><?php endif; ?>
      <?php if ($isAdmin): ?><a href="hierarchy.php?delete=<?= $row['employee_id'] ?>" class="btn red" onclick="return confirm('Delete?')">Delete</a><?php endif; ?>
    </td>
  </tr>
  <?php endwhile; ?>
</table></div>

<?php
$top = mysqli_query($conn, "SELECT employee_id,first_name,last_name,role FROM employees WHERE supervisor_id IS NULL ORDER BY last_name");
if (mysqli_num_rows($top)>0): ?>
<hr><h3>Reporting Tree (Recursive MANAGES Relationship)</h3>
<?php
function showSubordinates($conn,$sup_id,$depth=0){
    $subs=mysqli_query($conn,"SELECT employee_id,first_name,last_name,role FROM employees WHERE supervisor_id=$sup_id ORDER BY last_name");
    while($s=mysqli_fetch_assoc($subs)){
        echo "<div style='margin-left:".($depth*24+10)."px;padding:4px 0;font-size:14px'>└─ <b>".htmlspecialchars($s['first_name'].' '.$s['last_name'])."</b> <span style='color:#888'>(".htmlspecialchars($s['role']).")</span></div>";
        showSubordinates($conn,$s['employee_id'],$depth+1);
    }
}
while ($te=mysqli_fetch_assoc($top)): ?>
<div style="padding:6px 10px;background:#eef;border-left:3px solid #002060;margin-bottom:4px;font-size:14px">
  <b><?= htmlspecialchars($te['first_name'].' '.$te['last_name']) ?></b>
  <span style="color:#888">(<?= htmlspecialchars($te['role']) ?>) — Top Level</span>
</div>
<?php showSubordinates($conn,$te['employee_id']); endwhile; endif; ?>
</div></body></html>
