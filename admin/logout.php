<?php
require_once __DIR__ . "/../lib/admin.php";
admin_logout();
require_once __DIR__ . "/../lib/roles.php";
http_redirect("/index.php");
