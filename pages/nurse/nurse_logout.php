<?php
// capstonemain/pages/nurse/nurse_logout.php
session_start();
session_destroy();
header('Location: nurse_login.php');
exit();
?>