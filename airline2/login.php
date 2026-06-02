<?php
session_start();
require_once 'db.php';

if (isset($_SESSION['role'])) { header("Location: dashboard.php"); exit; }

$msg = "";
define('ADMIN_EMAIL', 'bracuairline.admin370@gmail.com');
define('ADMIN_PASS',  'CSE370LAB');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $pass  = $_POST['password'];

    if ($email === ADMIN_EMAIL && $pass === ADMIN_PASS) {
        $_SESSION['role']      = 'admin';
        $_SESSION['user_id']   = 0;
        $_SESSION['user_name'] = 'Admin';
        header("Location: dashboard.php"); exit;
    }

    $e   = mysqli_real_escape_string($conn, $email);
    $row = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM passengers WHERE email='$e'"));
    if ($row && password_verify($pass, $row['password'])) {
        $_SESSION['role']      = 'passenger';
        $_SESSION['user_id']   = $row['passenger_id'];
        $_SESSION['user_name'] = $row['first_name'] . ' ' . $row['last_name'];
        header("Location: dashboard.php"); exit;
    }

    $row = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM employees WHERE email='$e'"));
    if ($row && password_verify($pass, $row['password'])) {
        $_SESSION['role']      = 'employee';
        $_SESSION['user_id']   = $row['employee_id'];
        $_SESSION['user_name'] = $row['first_name'] . ' ' . $row['last_name'];
        header("Location: dashboard.php"); exit;
    }

    $msg = "err:Invalid email or password. If you are new, please register first.";
}

if (isset($_GET['error'])) $msg = "err:" . htmlspecialchars($_GET['error']);
if (isset($_GET['ok']))    $msg = "ok:"  . htmlspecialchars($_GET['ok']);
?>
<!DOCTYPE html>
<html>
<head>
  <title>Login — Airline DBMS</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="login-wrap">
  <h2>✈ Airline DBMS Login</h2>
  <?php if ($msg): $p = explode(":", $msg, 2); ?>
  <div class="msg <?= $p[0] ?>"><?= $p[1] ?></div>
  <?php endif; ?>
  <form method="POST" action="login.php">
    <label>Email</label>
    <input type="email" name="email" required placeholder="your@email.com"
           value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>">
    <label>Password</label>
    <input type="password" name="password" required placeholder="Password">
    <br><br>
    <input type="submit" value="Login" class="btn blue" style="width:100%">
  </form>
  <hr>
  <p style="text-align:center;font-size:14px">New user? <a href="register.php">Register here</a></p>
  <hr>
  <p style="font-size:12px;color:#888;text-align:center">Admin: bracuairline.admin370@gmail.com</p>
</div>
</body>
</html>
