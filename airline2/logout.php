<?php
session_start();
session_destroy();
header("Location: login.php?ok=You+have+been+logged+out.");
exit;
?>
