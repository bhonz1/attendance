<?php
require_once __DIR__ . "/../lib/admin.php";
admin_logout();
header("Location: /index.php");
exit;
