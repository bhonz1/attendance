<?php
require_once __DIR__ . "/../lib/auth.php";
require_once __DIR__ . "/../lib/csrf.php";
require_once __DIR__ . "/../lib/supabase.php";
header("Content-Type: application/json");
require_teacher_session();
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
$tk = $_POST["csrf"] ?? "";
if (!csrf_validate($tk)) { echo json_encode(["ok"=>false,"error"=>"Invalid CSRF"]); exit; }
$cap = $_POST["captcha"] ?? "";
$capSess = $_SESSION["captcha_code"] ?? null;
if (!$capSess || !is_string($cap) || !hash_equals($capSess, $cap)) {
  echo json_encode(["ok"=>false,"error"=>"Invalid captcha"]); exit;
}
$tid = $_SESSION["teacher_id"] ?? null;
if (!$tid) { echo json_encode(["ok"=>false,"error"=>"Unauthorized"]); exit; }
unset($_SESSION["captcha_code"]);
echo json_encode(["ok"=>false,"error"=>"Password reveal disabled"]);
exit;
