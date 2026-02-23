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
$useSb = sb_url() ? true : false;
$pw = null;
if ($useSb) {
  $r = sb_get("teacher_registry", ["select"=>"password_enc", "id"=>"eq.".$tid, "limit"=>1]);
  if (is_array($r) && isset($r[0])) {
    $cipher = $r[0]["password_enc"] ?? "";
    if (is_string($cipher) && strlen($cipher)) {
      $secret = getenv("TEACHER_PW_SECRET") ?: "";
      if (strpos($cipher, ":") !== false && $secret) {
        $parts = explode(":", $cipher, 2);
        $iv = base64_decode($parts[0], true);
        $ct = base64_decode($parts[1], true);
        if ($iv !== false && $ct !== false) {
          $key = hash("sha256", $secret, true);
          $dec = openssl_decrypt($ct, "AES-256-CBC", $key, OPENSSL_RAW_DATA, $iv);
          if ($dec !== false) $pw = $dec;
        }
      } else {
        $pw = $cipher;
      }
    }
  }
}
if ($pw) {
  unset($_SESSION["captcha_code"]);
  echo json_encode(["ok"=>true,"password"=>$pw]);
} else {
  echo json_encode(["ok"=>false,"error"=>"Unavailable"]);
}
exit;
