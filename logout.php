<?php
session_start();
session_unset();
session_destroy();
header("Location: /fashionhub/Homepage.php");
exit;
?>