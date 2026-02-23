<?php
require_once __DIR__ . "/../lib/auth.php";
logout_teacher();
header("Location: /index.php");
exit;
