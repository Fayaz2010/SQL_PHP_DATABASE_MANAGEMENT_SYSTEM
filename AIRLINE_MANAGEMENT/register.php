<?php
session_start();
require_once 'db.php';
if (isset($_SESSION['role'])) { header("Location: dashboard.php"); exit; }

$msg = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $type   = $_POST['user_type'];
    $fname  = mysqli_real_escape_string($conn, trim($_POST['first_name']));
    $lname  = mysqli_real_escape_string($conn, trim($_POST['last_name']));
    $email  = mysqli_real_escape_string($conn, trim($_POST['email']));
    $pass   = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $dob    = mysqli_real_escape_string($conn, trim($_POST['date_of_birth'] ?? ''));
    $passport = mysqli_real_escape_string($conn, trim($_POST['passport_number'] ?? ''));
    $nation = mysqli_real_escape_string($conn, trim($_POST['nationality'] ?? ''));
    $role   = mysqli_real_escape_string($conn, trim($_POST['emp_role'] ?? 'Ground Staff'));
    $hire   = mysqli_real_escape_string($conn, trim($_POST['hire_date'] ?? date('Y-m-d')));

    if ($_POST['email'] === 'bracuairline.admin370@gmail.com') {
        $msg = "err:That email is reserved for admin.";
    } elseif ($type === 'passenger') {
        if (!$fname || !$lname || !$email || !$_POST['password'] || !$dob || !$passport || !$nation) {
            $msg = "err:All fields are required for passenger registration.";
        } else {
            $r = mysqli_query($conn,
                "INSERT INTO passengers (first_name,last_name,date_of_birth,email,password,passport_number,nationality)
                 VALUES ('$fname','$lname','$dob','$email','$pass','$passport','$nation')");
            if ($r) { header("Location: login.php?ok=Registration+successful.+Please+log+in."); exit; }
            else $msg = "err:Email or passport number already exists.";
        }
    } elseif ($type === 'employee') {
        if (!$fname || !$lname || !$email || !$_POST['password']) {
            $msg = "err:First name, last name, email and password are required.";
        } else {
            $r = mysqli_query($conn,
                "INSERT INTO employees (first_name,last_name,role,hire_date,email,password)
                 VALUES ('$fname','$lname','$role','$hire','$email','$pass')");
            if ($r) { header("Location: login.php?ok=Registration+successful.+Please+log+in."); exit; }
            else $msg = "err:Email already exists.";
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
  <title>Register — Airline DBMS</title>
  <link rel="stylesheet" href="style.css">
  <script>
    function showFields() {
      var t = document.getElementById('user_type').value;
      document.getElementById('pass_fields').style.display = (t==='passenger') ? 'block' : 'none';
      document.getElementById('emp_fields').style.display  = (t==='employee')  ? 'block' : 'none';
    }
  </script>
</head>
<body>
<div class="login-wrap" style="max-width:480px">
  <h2>✈ Register New Account</h2>
  <?php if ($msg): $p = explode(":", $msg, 2); ?>
  <div class="msg <?= $p[0] ?>"><?= $p[1] ?></div>
  <?php endif; ?>
  <form method="POST" action="register.php">
    <label>Register as</label>
    <select name="user_type" id="user_type" onchange="showFields()" required>
      <option value="">-- Select --</option>
      <option value="passenger" <?= (($_POST['user_type'] ?? '')==='passenger')?'selected':'' ?>>Passenger</option>
      <option value="employee"  <?= (($_POST['user_type'] ?? '')==='employee') ?'selected':'' ?>>Employee</option>
    </select>
    <label>First Name</label>
    <input type="text" name="first_name" required value="<?= htmlspecialchars($_POST['first_name'] ?? '') ?>">
    <label>Last Name</label>
    <input type="text" name="last_name" required value="<?= htmlspecialchars($_POST['last_name'] ?? '') ?>">
    <label>Email</label>
    <input type="email" name="email" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
    <label>Password</label>
    <input type="password" name="password" required>

    <div id="pass_fields" style="display:none">
      <label>Date of Birth</label>
      <input type="date" name="date_of_birth" value="<?= htmlspecialchars($_POST['date_of_birth'] ?? '') ?>">
      <label>Passport Number</label>
      <input type="text" name="passport_number" value="<?= htmlspecialchars($_POST['passport_number'] ?? '') ?>">
      <label>Nationality</label>
      <input type="text" name="nationality" value="<?= htmlspecialchars($_POST['nationality'] ?? '') ?>">
    </div>

    <div id="emp_fields" style="display:none">
      <label>Role</label>
      <select name="emp_role">
        <option value="Pilot">Pilot</option>
        <option value="Co-Pilot">Co-Pilot</option>
        <option value="Cabin Crew">Cabin Crew</option>
        <option value="Ground Staff" selected>Ground Staff</option>
      </select>
      <label>Hire Date</label>
      <input type="date" name="hire_date" value="<?= date('Y-m-d') ?>">
    </div>

    <br>
    <input type="submit" value="Register" class="btn blue" style="width:100%">
  </form>
  <hr>
  <p style="text-align:center;font-size:14px">Already registered? <a href="login.php">Login here</a></p>
</div>
<script>showFields();</script>
</body>
</html>
