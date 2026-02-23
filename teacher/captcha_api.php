<?php
require_once __DIR__ . "/../lib/auth.php";
require_once __DIR__ . "/../lib/csrf.php";
header("Content-Type: application/json");
require_teacher_session();
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
$tk = $_POST["csrf"] ?? "";
if (!csrf_validate($tk)) { echo json_encode(["ok"=>false,"error"=>"Invalid CSRF"]); exit; }
function rand_code($len=6){
  $chars = "ABCDEFGHJKLMNPQRSTUVWXYZ23456789";
  $out = "";
  for ($i=0;$i<$len;$i++) { $out .= $chars[random_int(0, strlen($chars)-1)]; }
  return $out;
}
$code = rand_code(6);
$_SESSION["captcha_code"] = $code;
echo json_encode(["ok"=>true,"challenge"=>$code]);
exit;
