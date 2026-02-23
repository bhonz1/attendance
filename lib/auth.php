<?php
require_once __DIR__ . "/env.php";
env_load();
require_once __DIR__ . "/csrf.php";
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: SAMEORIGIN");
header("Referrer-Policy: strict-origin-when-cross-origin");
header("Permissions-Policy: geolocation=(), camera=(), microphone=()");
header("Content-Security-Policy: default-src 'self' https: data: blob:; script-src 'self' https: 'unsafe-inline'; style-src 'self' https: 'unsafe-inline'; img-src 'self' https: data: blob:; connect-src 'self' https: wss: ws:");
if (php_sapi_name() !== "cli") {
  if (session_status() !== PHP_SESSION_ACTIVE) session_start();
  $tok = csrf_token();
  setcookie("CSRF", $tok, 0, "/", "", isset($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] === "on", false);
}
require_once __DIR__ . "/supabase.php";
require_once __DIR__ . "/roles.php";
function auth_use_mock() {
  $force = getenv("AUTH_FORCE_MOCK");
  if ($force === "1") return true;
  return sb_url() === null;
}
function &auth_mock_store() {
  if (session_status() !== PHP_SESSION_ACTIVE) session_start();
  if (!isset($_SESSION["__auth_mock"])) $_SESSION["__auth_mock"] = ["teacher" => null];
  return $_SESSION["__auth_mock"];
}
function teacher_exists() {
  if (auth_use_mock()) {
    $store = auth_mock_store();
    return $store["teacher"];
  } else {
    $r = sb_get("teacher_registry", ["select" => "id,username,full_name,session_id,password_enc", "limit" => 1]);
    return is_array($r) && count($r) > 0 ? $r[0] : null;
  }
}
function register_teacher($email, $name, $password) {
  $existing = teacher_exists();
  if ($existing) return ["ok" => false, "error" => "Teacher already registered"];
  if (!is_string($password) || strlen($password) < 6) return ["ok" => false, "error" => "Weak password"];
  $secret = getenv("TEACHER_PW_SECRET") ?: "";
  if (!$secret) return ["ok" => false, "error" => "Encryption not configured"];
  $key = hash("sha256", $secret, true);
  $iv = random_bytes(16);
  $ct = openssl_encrypt($password, "AES-256-CBC", $key, OPENSSL_RAW_DATA, $iv);
  if ($ct === false) return ["ok" => false, "error" => "Encrypt failed"];
  $enc = base64_encode($iv) . ":" . base64_encode($ct);
  if (auth_use_mock()) {
    $store =& auth_mock_store();
    $store["teacher"] = ["id" => 1, "email" => $email, "name" => $name, "password_enc" => $enc, "session_id" => null];
    return ["ok" => true];
  } else {
    $res = sb_post("teacher_registry", ["full_name" => $name, "username" => $email, "password_enc" => $enc, "session_id" => null]);
    if (!$res) return ["ok" => false, "error" => "Registration failed"];
    return ["ok" => true];
  }
}
function login_teacher($username, $password) {
  $t = null;
  if (auth_use_mock()) {
    $store =& auth_mock_store();
    $t = $store["teacher"];
    if (!$t || (($t["username"] ?? "") !== $username)) {
      if (session_status() !== PHP_SESSION_ACTIVE) session_start();
      $ip = $_SERVER["REMOTE_ADDR"] ?? "";
      $ua = $_SERVER["HTTP_USER_AGENT"] ?? "";
      $sid = session_id();
      if (auth_use_mock()) {
        if (!isset($_SESSION["__sys_logs"])) $_SESSION["__sys_logs"] = [];
        $id = count($_SESSION["__sys_logs"]) + 1;
        $_SESSION["__sys_logs"][] = ["id"=>$id,"timestamp"=>gmdate("c"),"user_id"=>null,"user_role"=>ROLE_TEACHER,"action"=>"login","resource"=>"auth","ip"=>$ip,"user_agent"=>$ua,"session_id"=>$sid,"response_status"=>"UNAUTHORIZED","severity"=>"WARNING","actor"=>$username];
      } else {
        sb_post("system_logs", ["timestamp"=>gmdate("c"),"user_id"=>null,"user_role"=>ROLE_TEACHER,"action"=>"login","resource"=>"auth","ip"=>$ip,"user_agent"=>$ua,"session_id"=>$sid,"response_status"=>"UNAUTHORIZED","severity"=>"WARNING","actor"=>$username]);
      }
      return ["ok" => false, "error" => "Login failed"];
    }
  } else {
    $r = sb_get("teacher_registry", ["select" => "id,username,full_name,password_enc", "username" => "eq." . $username, "limit" => 1]);
    if (!is_array($r) || count($r) === 0) {
      $r = sb_request("GET", "teacher_registry", null, ["select" => "id,username,full_name,password_enc", "username" => "ilike." . $username, "limit" => 1], true);
    }
    if (!is_array($r) || count($r) === 0) {
      if (session_status() !== PHP_SESSION_ACTIVE) session_start();
      $ip = $_SERVER["REMOTE_ADDR"] ?? "";
      $ua = $_SERVER["HTTP_USER_AGENT"] ?? "";
      $sid = session_id();
      $errInfo = sb_last_error();
      $msg = "Login failed";
      if (is_array($errInfo)) {
        $code = $errInfo["code"] ?? null;
        if ($code === 401 || $code === 403) $msg = "Login failed";
      } else {
        $msg = "Login failed";
      }
      sb_post("system_logs", ["timestamp"=>gmdate("c"),"user_id"=>null,"user_role"=>ROLE_TEACHER,"action"=>"login","resource"=>"auth","ip"=>$ip,"user_agent"=>$ua,"session_id"=>$sid,"response_status"=>"UNAUTHORIZED","severity"=>"WARNING","actor"=>$username]);
      return ["ok" => false, "error" => $msg];
    }
    $t = $r[0];
  }
  $ok = false;
  $cipher = $t["password_enc"] ?? "";
  $secret = getenv("TEACHER_PW_SECRET") ?: "";
  if (is_string($cipher) && strlen($cipher) > 10 && $secret) {
    $parts = explode(":", $cipher);
    if (count($parts) === 2) {
      $iv = base64_decode($parts[0]);
      $ct = base64_decode($parts[1]);
      $key = hash("sha256", $secret, true);
      $dec = openssl_decrypt($ct, "AES-256-CBC", $key, OPENSSL_RAW_DATA, $iv);
      if ($dec !== false && hash_equals($dec, $password)) $ok = true;
    }
  }
  if (!$ok && is_string($cipher) && strlen($cipher) > 0) {
    if (hash_equals($cipher, $password)) $ok = true;
  }
  if (!$ok && !auth_use_mock() && isset($t["id"])) {
    $q = sb_get("teacher_registry", ["select" => "password_hash", "id" => "eq." . $t["id"], "limit" => 1]);
    if (is_array($q) && isset($q[0])) {
      $ph = $q[0]["password_hash"] ?? null;
      if (is_string($ph) && strlen($ph) > 10) {
        if (password_verify($password, $ph)) $ok = true;
      }
    }
  }
  if (!$ok) {
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    $ip = $_SERVER["REMOTE_ADDR"] ?? "";
    $ua = $_SERVER["HTTP_USER_AGENT"] ?? "";
    $sid = session_id();
    if (auth_use_mock()) {
      if (!isset($_SESSION["__sys_logs"])) $_SESSION["__sys_logs"] = [];
      $id = count($_SESSION["__sys_logs"]) + 1;
      $_SESSION["__sys_logs"][] = ["id"=>$id,"timestamp"=>gmdate("c"),"user_id"=>$t["id"] ?? null,"user_role"=>ROLE_TEACHER,"action"=>"login","resource"=>"auth","ip"=>$ip,"user_agent"=>$ua,"session_id"=>$sid,"response_status"=>"UNAUTHORIZED","severity"=>"WARNING","actor"=>$username];
    } else {
      sb_post("system_logs", ["timestamp"=>gmdate("c"),"user_id"=>$t["id"] ?? null,"user_role"=>ROLE_TEACHER,"action"=>"login","resource"=>"auth","ip"=>$ip,"user_agent"=>$ua,"session_id"=>$sid,"response_status"=>"UNAUTHORIZED","severity"=>"WARNING","actor"=>$username]);
    }
    return ["ok" => false, "error" => "Login failed"];
  }
  if (session_status() !== PHP_SESSION_ACTIVE) session_start();
  session_regenerate_id(true);
  $_SESSION["teacher_id"] = $t["id"];
  $_SESSION["teacher_email"] = $t["username"] ?? "";
  $_SESSION["teacher_name"] = $t["full_name"] ?? ($t["name"] ?? "");
  $_SESSION["teacher_username"] = $t["username"] ?? $username;
  set_auth_id(ROLE_TEACHER);
  $sid = session_id();
  if (auth_use_mock()) {
    $store =& auth_mock_store();
    $store["teacher"]["session_id"] = $sid;
  }
  $ip = $_SERVER["REMOTE_ADDR"] ?? "";
  $ua = $_SERVER["HTTP_USER_AGENT"] ?? "";
  if (auth_use_mock()) {
    if (!isset($_SESSION["__sys_logs"])) $_SESSION["__sys_logs"] = [];
    $id = count($_SESSION["__sys_logs"]) + 1;
    $_SESSION["__sys_logs"][] = ["id"=>$id,"timestamp"=>gmdate("c"),"user_id"=>$t["id"],"user_role"=>ROLE_TEACHER,"action"=>"login","resource"=>"auth","ip"=>$ip,"user_agent"=>$ua,"session_id"=>$sid,"response_status"=>"OK","severity"=>"INFO","actor"=>($_SESSION["teacher_email"] ?? "teacher")];
  } else {
    sb_post("system_logs", ["timestamp"=>gmdate("c"),"user_id"=>$t["id"],"user_role"=>ROLE_TEACHER,"action"=>"login","resource"=>"auth","ip"=>$ip,"user_agent"=>$ua,"session_id"=>$sid,"response_status"=>"OK","severity"=>"INFO","actor"=>($_SESSION["teacher_email"] ?? "teacher")]);
  }
  return ["ok" => true];
}
function logout_teacher() {
  if (session_status() !== PHP_SESSION_ACTIVE) session_start();
  if (isset($_SESSION["teacher_id"])) {
    $tid = $_SESSION["teacher_id"];
    $ip = $_SERVER["REMOTE_ADDR"] ?? "";
    $ua = $_SERVER["HTTP_USER_AGENT"] ?? "";
    $sid = session_id();
    $actor = $_SESSION["teacher_email"] ?? "teacher";
    if (auth_use_mock()) {
      $store =& auth_mock_store();
      if ($store["teacher"] && $store["teacher"]["id"] === $tid) $store["teacher"]["session_id"] = null;
      if (!isset($_SESSION["__sys_logs"])) $_SESSION["__sys_logs"] = [];
      $id = count($_SESSION["__sys_logs"]) + 1;
      $_SESSION["__sys_logs"][] = ["id"=>$id,"timestamp"=>gmdate("c"),"user_id"=>$tid,"user_role"=>ROLE_TEACHER,"action"=>"logout","resource"=>"auth","ip"=>$ip,"user_agent"=>$ua,"session_id"=>$sid,"response_status"=>"OK","severity"=>"INFO","actor"=>$actor];
    } else {
      sb_patch("teacher_registry", ["session_id" => null], ["id" => "eq." . $tid]);
      sb_post("system_logs", ["timestamp"=>gmdate("c"),"user_id"=>$tid,"user_role"=>ROLE_TEACHER,"action"=>"logout","resource"=>"auth","ip"=>$ip,"user_agent"=>$ua,"session_id"=>$sid,"response_status"=>"OK","severity"=>"INFO","actor"=>$actor]);
    }
  }
  $_SESSION = [];
  if (ini_get("session.use_cookies") && php_sapi_name() !== "cli") {
    $params = session_get_cookie_params();
    setcookie(session_name(), "", time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
  }
  session_destroy();
}
function require_teacher_session() {
  if (session_status() !== PHP_SESSION_ACTIVE) session_start();
  $rid = get_auth_id();
  if ($rid !== null && $rid <= ROLE_ADMIN) return;
  if (!isset($_SESSION["teacher_id"])) {
    if (php_sapi_name() !== "cli") {
      $_SESSION["__flash_error"] = "unauthorized";
      http_redirect("/teacher/login.php");
    }
    return;
  }
  $tid = $_SESSION["teacher_id"];
  if (auth_use_mock()) {
    $store =& auth_mock_store();
    if (!$store["teacher"] || $store["teacher"]["id"] !== $tid) {
      logout_teacher();
      if (php_sapi_name() !== "cli") {
        $_SESSION["__flash_error"] = "unauthorized";
        http_redirect("/teacher/login.php");
      }
      return;
    }
  }
}
