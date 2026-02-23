<?php
require_once __DIR__ . "/../lib/admin.php";
require_once __DIR__ . "/../lib/supabase.php";
require_once __DIR__ . "/../lib/csrf.php";
require_admin_session();
header("Content-Type: application/json");
header("Cache-Control: no-store");
header("X-Content-Type-Options: nosniff");
header("Referrer-Policy: no-referrer");
header("Strict-Transport-Security: max-age=31536000; includeSubDomains; preload");
if (!defined("UNIT_TEST")) define("UNIT_TEST", false);
$useSb = (sb_url() !== null) && !UNIT_TEST;
$method = $_SERVER["REQUEST_METHOD"] ?? "";
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
$op = $_GET["op"] ?? $_POST["op"] ?? "";
function json_out($d){ echo json_encode($d); if (!UNIT_TEST) exit; }
function random_secret(){ return bin2hex(random_bytes(16)); }
function captcha_pw_issue() {
  if (!isset($_SESSION["__captcha_pw_change"]) || !is_array($_SESSION["__captcha_pw_change"])) $_SESSION["__captcha_pw_change"] = [];
  $a = random_int(1, 9);
  $b = random_int(1, 9);
  $token = bin2hex(random_bytes(8));
  $_SESSION["__captcha_pw_change"][$token] = hash("sha256", (string)($a + $b));
  return ["ok"=>true,"prompt"=>"What is $a + $b?","token"=>$token];
}
function captcha_pw_validate($token, $answer) {
  $map = $_SESSION["__captcha_pw_change"] ?? [];
  $val = isset($map[$token]) ? $map[$token] : null;
  $ok = ($val !== null) && hash_equals($val, hash("sha256", (string)$answer));
  if ($val !== null) unset($_SESSION["__captcha_pw_change"][$token]);
  return $ok;
}
if ($method === "GET" && $op === "captcha") { json_out(captcha_pw_issue()); }
if ($method === "POST" && !csrf_validate($_POST["csrf"] ?? "")) { json_out(["ok"=>false,"error"=>"Invalid CSRF"]); }
if ($op === "change_password") {
  $id = (int)($_POST["id"] ?? 0);
  $old = $_POST["old_password"] ?? "";
  $password = $_POST["password"] ?? "";
  $ct = $_POST["captcha_token"] ?? "";
  $ca = $_POST["captcha_answer"] ?? "";
  if ($id <= 0 || strlen($old) < 1 || strlen($password) < 6) json_out(["ok"=>false,"error"=>"Invalid data"]);
  if (!captcha_pw_validate($ct,$ca)) json_out(["ok"=>false,"error"=>"Invalid verification"]);
  $rec = null;
  if ($useSb) {
    $rows = sb_get("users", ["select"=>"id,username,password_hash","id"=>"eq.".$id,"limit"=>1]);
    if (is_array($rows) && isset($rows[0])) $rec = $rows[0];
  } else {
    if (!isset($_SESSION["__users"]) || !is_array($_SESSION["__users"])) $_SESSION["__users"] = [];
    foreach ($_SESSION["__users"] as $u) { if (($u["id"] ?? 0) === $id) { $rec = $u; break; } }
  }
  if (!$rec || !isset($rec["password_hash"])) json_out(["ok"=>false,"error"=>"Unauthorized"]);
  $un = $_SESSION["admin_user"] ?? "";
  if ($un === "" || (($rec["username"] ?? "") !== $un)) json_out(["ok"=>false,"error"=>"Unauthorized"]);
  if (!password_verify($old, $rec["password_hash"])) json_out(["ok"=>false,"error"=>"Incorrect old password"]);
  $hash = password_hash($password, PASSWORD_DEFAULT);
  if ($useSb) {
    $r = sb_patch("users", ["password_hash"=>$hash], ["id"=>"eq.".$id]);
    if ($r === null) {
      $e = function_exists("sb_last_error") ? sb_last_error() : null;
      $msg = "Update failed";
      if (is_array($e)) {
        $bj = $e["body_json"] ?? null;
        if (is_array($bj) && isset($bj["message"])) $msg = "Supabase: " . $bj["message"];
        else if (isset($e["code"]) && $e["code"]) $msg = "Supabase HTTP " . $e["code"];
      }
      json_out(["ok"=>false,"error"=>$msg]);
    }
    json_out(["ok"=>true]);
  } else {
    $ok = false;
    foreach ($_SESSION["__users"] as &$u) { if (($u["id"] ?? 0) === $id) { $u["password_hash"] = $hash; $ok = true; break; } }
    json_out($ok ? ["ok"=>true] : ["ok"=>false,"error"=>"User not found"]);
  }
} else if ($op === "setup_2fa") {
  $id = (int)($_POST["id"] ?? 0);
  if ($id <= 0) json_out(["ok"=>false,"error"=>"Invalid id"]);
  $secret = random_secret();
  if ($useSb) {
    $r = sb_patch("users", ["two_factor_enabled"=>true, "two_factor_secret"=>$secret], ["id"=>"eq.".$id]);
    json_out(["ok"=>$r!==null, "secret"=>$secret]);
  } else {
    $ok = false;
    foreach ($_SESSION["__users"] as &$u) { if (($u["id"] ?? 0) === $id) { $u["two_factor_enabled"] = true; $u["two_factor_secret"] = $secret; $ok = true; break; } }
    json_out(["ok"=>$ok, "secret"=>$ok ? $secret : null]);
  }
} else if ($op === "disable_2fa") {
  $id = (int)($_POST["id"] ?? 0);
  if ($id <= 0) json_out(["ok"=>false,"error"=>"Invalid id"]);
  if ($useSb) {
    $r = sb_patch("users", ["two_factor_enabled"=>false, "two_factor_secret"=>null], ["id"=>"eq.".$id]);
    json_out(["ok"=>$r!==null]);
  } else {
    $ok = false;
    foreach ($_SESSION["__users"] as &$u) { if (($u["id"] ?? 0) === $id) { $u["two_factor_enabled"] = false; $u["two_factor_secret"] = null; $ok = true; break; } }
    json_out(["ok"=>$ok]);
  }
} else if ($op === "login_history") {
  $id = (int)($_GET["id"] ?? 0);
  if ($id <= 0) json_out(["ok"=>false,"error"=>"Invalid id"]);
  $items = $useSb ? (sb_get("login_history", ["select"=>"id,at,ip,user_agent,success,reason", "user_id"=>"eq.".$id, "limit"=>50, "order"=>"at.desc"]) ?: []) : [];
  json_out(["ok"=>true, "items"=>$items]);
} else {
  json_out(["ok"=>false,"error"=>"Unknown op"]);
}
