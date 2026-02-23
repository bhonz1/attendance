<?php
require_once __DIR__ . "/../lib/auth.php";
logout_teacher();
require_once __DIR__ . "/../lib/roles.php";
http_redirect("/index");
