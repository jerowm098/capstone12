<?php
session_start();
session_unset();
session_destroy();
header('Location: kiosk_landing.php');
exit();
?>