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
  $tok = csrf_token();
  setcookie("CSRF", $tok, 0, "/", "", isset($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] === "on", false);
}
require_once __DIR__ . "/roles.php";
function admin_env_user() { return getenv("ADMIN_USER") ?: null; }
function admin_env_hash() { return getenv("ADMIN_PASSWORD_HASH") ?: null; }
function admin_role_id() {
  $eu = admin_env_user();
  if ($eu && strtolower($eu) === "beast") return ROLE_SUPERADMIN;
  $v = getenv("ADMIN_ROLE_ID");
  if ($v === false || $v === null || $v === "") return ROLE_SUPERADMIN;
  $i = (int)$v;
  if ($i !== ROLE_SUPERADMIN && $i !== ROLE_ADMIN) return ROLE_SUPERADMIN;
  return $i;
}
function admin_login($u, $p) {
  $eu = admin_env_user();
  $eh = admin_env_hash();
  if ($eu && $eh && $u === $eu) {
    if (!password_verify($p, $eh)) {
      if (session_status() !== PHP_SESSION_ACTIVE) session_start();
      $ip = $_SERVER["REMOTE_ADDR"] ?? "";
      $ua = $_SERVER["HTTP_USER_AGENT"] ?? "";
      $sid = session_id();
      require_once __DIR__ . "/supabase.php";
      if (sb_url()) {
        $log = ["timestamp"=>gmdate("c"),"user_id"=>null,"user_role"=>admin_role_id(),"action"=>"login","resource"=>"auth","ip"=>$ip,"user_agent"=>$ua,"session_id"=>$sid,"response_status"=>"UNAUTHORIZED","severity"=>"WARNING","actor"=>$u];
        sb_post("system_logs", $log);
      } else {
        if (!isset($_SESSION["__sys_logs"])) $_SESSION["__sys_logs"] = [];
        $id = count($_SESSION["__sys_logs"]) + 1;
        $_SESSION["__sys_logs"][] = ["id"=>$id,"timestamp"=>gmdate("c"),"user_id"=>null,"user_role"=>admin_role_id(),"action"=>"login","resource"=>"auth","ip"=>$ip,"user_agent"=>$ua,"session_id"=>$sid,"response_status"=>"UNAUTHORIZED","severity"=>"WARNING","actor"=>$u];
      }
      return ["ok" => false, "error" => "Unauthorized"];
    }
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    $_SESSION["admin"] = true;
    $_SESSION["admin_user"] = $eu;
    set_auth_id(admin_role_id());
    $ip = $_SERVER["REMOTE_ADDR"] ?? "";
    $ua = $_SERVER["HTTP_USER_AGENT"] ?? "";
    $sid = session_id();
    require_once __DIR__ . "/supabase.php";
    if (sb_url()) {
      $log = ["timestamp"=>gmdate("c"),"user_id"=>null,"user_role"=>admin_role_id(),"action"=>"login","resource"=>"auth","ip"=>$ip,"user_agent"=>$ua,"session_id"=>$sid,"response_status"=>"OK","severity"=>"INFO","actor"=>$eu];
      sb_post("system_logs", $log);
    } else {
      if (!isset($_SESSION["__sys_logs"])) $_SESSION["__sys_logs"] = [];
      $id = count($_SESSION["__sys_logs"]) + 1;
      $_SESSION["__sys_logs"][] = ["id"=>$id,"timestamp"=>gmdate("c"),"user_id"=>null,"user_role"=>admin_role_id(),"action"=>"login","resource"=>"auth","ip"=>$ip,"user_agent"=>$ua,"session_id"=>$sid,"response_status"=>"OK","severity"=>"INFO","actor"=>$eu];
    }
    return ["ok" => true];
  }
  if (session_status() !== PHP_SESSION_ACTIVE) session_start();
  $rec = null;
  $hash = null;
  $rid = null;
  $status = null;
  $useSb = false;
  $sbOk = false;
  require_once __DIR__ . "/supabase.php";
  if (sb_url()) {
    $useSb = true;
    $r = sb_get("users", ["select"=>"id,username,password_hash,role_id,status","username"=>"eq.".$u,"limit"=>1]);
    if (is_array($r) && isset($r[0])) {
      $rec = $r[0];
      $hash = $rec["password_hash"] ?? null;
      $rid = isset($rec["role_id"]) ? (int)$rec["role_id"] : null;
      $status = $rec["status"] ?? null;
      $sbOk = true;
    }
  } else {
    if (!isset($_SESSION["__users"])) $_SESSION["__users"] = [];
    $found = null;
    foreach ($_SESSION["__users"] as $urow) { if (($urow["username"] ?? "") === $u) { $found = $urow; break; } }
    if ($found) {
      $rec = $found;
      $hash = $rec["password_hash"] ?? null;
      $rid = isset($rec["role_id"]) ? (int)$rec["role_id"] : null;
      $status = $rec["status"] ?? null;
    }
  }
  if (!$rec) {
    if (!isset($_SESSION["__users"])) $_SESSION["__users"] = [];
    $found = null;
    foreach ($_SESSION["__users"] as $urow) { if (($urow["username"] ?? "") === $u) { $found = $urow; break; } }
    if ($found) {
      $rec = $found;
      $hash = $rec["password_hash"] ?? null;
      $rid = isset($rec["role_id"]) ? (int)$rec["role_id"] : null;
      $status = $rec["status"] ?? null;
    }
  }
  if (!$rec || !$hash) {
    $ip = $_SERVER["REMOTE_ADDR"] ?? "";
    $ua = $_SERVER["HTTP_USER_AGENT"] ?? "";
    $sid = session_id();
    if (sb_url()) {
      sb_post("system_logs", ["timestamp"=>gmdate("c"),"user_id"=>null,"user_role"=>ROLE_ADMIN,"action"=>"login","resource"=>"auth","ip"=>$ip,"user_agent"=>$ua,"session_id"=>$sid,"response_status"=>"UNAUTHORIZED","severity"=>"WARNING","actor"=>$u]);
    } else {
      if (!isset($_SESSION["__sys_logs"])) $_SESSION["__sys_logs"] = [];
      $id = count($_SESSION["__sys_logs"]) + 1;
      $_SESSION["__sys_logs"][] = ["id"=>$id,"timestamp"=>gmdate("c"),"user_id"=>null,"user_role"=>ROLE_ADMIN,"action"=>"login","resource"=>"auth","ip"=>$ip,"user_agent"=>$ua,"session_id"=>$sid,"response_status"=>"UNAUTHORIZED","severity"=>"WARNING","actor"=>$u];
    }
    return ["ok"=>false,"error"=>"Unauthorized"];
  }
  if ($status !== "active") {
    $ip = $_SERVER["REMOTE_ADDR"] ?? "";
    $ua = $_SERVER["HTTP_USER_AGENT"] ?? "";
    $sid = session_id();
    if (sb_url()) {
      sb_post("system_logs", ["timestamp"=>gmdate("c"),"user_id"=>$rec["id"] ?? null,"user_role"=>$rid ?? ROLE_ADMIN,"action"=>"login","resource"=>"auth","ip"=>$ip,"user_agent"=>$ua,"session_id"=>$sid,"response_status"=>"INACTIVE","severity"=>"WARNING","actor"=>$u]);
    } else {
      if (!isset($_SESSION["__sys_logs"])) $_SESSION["__sys_logs"] = [];
      $id = count($_SESSION["__sys_logs"]) + 1;
      $_SESSION["__sys_logs"][] = ["id"=>$id,"timestamp"=>gmdate("c"),"user_id"=>$rec["id"] ?? null,"user_role"=>$rid ?? ROLE_ADMIN,"action"=>"login","resource"=>"auth","ip"=>$ip,"user_agent"=>$ua,"session_id"=>$sid,"response_status"=>"INACTIVE","severity"=>"WARNING","actor"=>$u];
    }
    return ["ok"=>false,"error"=>"Account inactive"];
  }
  if ($rid === null || $rid > ROLE_ADMIN) {
    $ip = $_SERVER["REMOTE_ADDR"] ?? "";
    $ua = $_SERVER["HTTP_USER_AGENT"] ?? "";
    $sid = session_id();
    if (sb_url()) {
      sb_post("system_logs", ["timestamp"=>gmdate("c"),"user_id"=>$rec["id"] ?? null,"user_role"=>$rid ?? ROLE_ADMIN,"action"=>"login","resource"=>"auth","ip"=>$ip,"user_agent"=>$ua,"session_id"=>$sid,"response_status"=>"UNAUTHORIZED","severity"=>"WARNING","actor"=>$u]);
    } else {
      if (!isset($_SESSION["__sys_logs"])) $_SESSION["__sys_logs"] = [];
      $id = count($_SESSION["__sys_logs"]) + 1;
      $_SESSION["__sys_logs"][] = ["id"=>$id,"timestamp"=>gmdate("c"),"user_id"=>$rec["id"] ?? null,"user_role"=>$rid ?? ROLE_ADMIN,"action"=>"login","resource"=>"auth","ip"=>$ip,"user_agent"=>$ua,"session_id"=>$sid,"response_status"=>"UNAUTHORIZED","severity"=>"WARNING","actor"=>$u];
    }
    return ["ok"=>false,"error"=>"Unauthorized"];
  }
  if (!password_verify($p, $hash)) {
    $ip = $_SERVER["REMOTE_ADDR"] ?? "";
    $ua = $_SERVER["HTTP_USER_AGENT"] ?? "";
    $sid = session_id();
    if (sb_url()) {
      sb_post("system_logs", ["timestamp"=>gmdate("c"),"user_id"=>$rec["id"] ?? null,"user_role"=>$rid ?? ROLE_ADMIN,"action"=>"login","resource"=>"auth","ip"=>$ip,"user_agent"=>$ua,"session_id"=>$sid,"response_status"=>"UNAUTHORIZED","severity"=>"WARNING","actor"=>$u]);
    } else {
      if (!isset($_SESSION["__sys_logs"])) $_SESSION["__sys_logs"] = [];
      $id = count($_SESSION["__sys_logs"]) + 1;
      $_SESSION["__sys_logs"][] = ["id"=>$id,"timestamp"=>gmdate("c"),"user_id"=>$rec["id"] ?? null,"user_role"=>$rid ?? ROLE_ADMIN,"action"=>"login","resource"=>"auth","ip"=>$ip,"user_agent"=>$ua,"session_id"=>$sid,"response_status"=>"UNAUTHORIZED","severity"=>"WARNING","actor"=>$u];
    }
    return ["ok"=>false,"error"=>"Unauthorized"];
  }
  $_SESSION["admin"] = true;
  $_SESSION["admin_user"] = $u;
  if (isset($rec["id"])) $_SESSION["admin_user_id"] = (int)$rec["id"];
  set_auth_id($rid);
  if ($useSb && $sbOk) {
    $ip = $_SERVER["REMOTE_ADDR"] ?? "";
    $ua = $_SERVER["HTTP_USER_AGENT"] ?? "";
    sb_post("login_history", ["user_id"=>$rec["id"],"ip"=>$ip,"user_agent"=>$ua,"success"=>true,"reason"=>"admin_login"]);
    $sid = session_id();
    $log = ["timestamp"=>gmdate("c"),"user_id"=>$rec["id"],"user_role"=>$rid,"action"=>"login","resource"=>"auth","ip"=>$ip,"user_agent"=>$ua,"session_id"=>$sid,"response_status"=>"OK","severity"=>"INFO","actor"=>$u];
    sb_post("system_logs", $log);
  } else {
    $_SESSION["__last_login"] = ["user"=>$u,"at"=>date("c")];
    $ip = $_SERVER["REMOTE_ADDR"] ?? "";
    $ua = $_SERVER["HTTP_USER_AGENT"] ?? "";
    $sid = session_id();
    if (!isset($_SESSION["__sys_logs"])) $_SESSION["__sys_logs"] = [];
    $id = count($_SESSION["__sys_logs"]) + 1;
    $_SESSION["__sys_logs"][] = ["id"=>$id,"timestamp"=>gmdate("c"),"user_id"=>$rec["id"] ?? null,"user_role"=>$rid,"action"=>"login","resource"=>"auth","ip"=>$ip,"user_agent"=>$ua,"session_id"=>$sid,"response_status"=>"OK","severity"=>"INFO","actor"=>$u];
  }
  return ["ok"=>true];
}
function admin_logout() {
  if (session_status() !== PHP_SESSION_ACTIVE) session_start();
  $actor = $_SESSION["admin_user"] ?? "admin";
  $uid = $_SESSION["admin_user_id"] ?? null;
  $role = isset($_SESSION["auth_id"]) ? (int)$_SESSION["auth_id"] : ROLE_ADMIN;
  $ip = $_SERVER["REMOTE_ADDR"] ?? "";
  $ua = $_SERVER["HTTP_USER_AGENT"] ?? "";
  $sid = session_id();
  unset($_SESSION["admin"], $_SESSION["admin_user"], $_SESSION["auth_id"]);
  require_once __DIR__ . "/supabase.php";
  if (sb_url()) {
    $log = ["timestamp"=>gmdate("c"),"user_id"=>$uid,"user_role"=>$role,"action"=>"logout","resource"=>"auth","ip"=>$ip,"user_agent"=>$ua,"session_id"=>$sid,"response_status"=>"OK","severity"=>"INFO","actor"=>$actor];
    sb_post("system_logs", $log);
  } else {
    if (!isset($_SESSION["__sys_logs"])) $_SESSION["__sys_logs"] = [];
    $id = count($_SESSION["__sys_logs"]) + 1;
    $_SESSION["__sys_logs"][] = ["id"=>$id,"timestamp"=>gmdate("c"),"user_id"=>$uid,"user_role"=>$role,"action"=>"logout","resource"=>"auth","ip"=>$ip,"user_agent"=>$ua,"session_id"=>$sid,"response_status"=>"OK","severity"=>"INFO","actor"=>$actor];
  }
  $_SESSION = [];
  if (ini_get("session.use_cookies") && php_sapi_name() !== "cli") {
    $params = session_get_cookie_params();
    setcookie(session_name(), "", time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
  }
  session_destroy();
}
function require_admin_session() {
  require_once __DIR__ . "/roles.php";
  if (session_status() !== PHP_SESSION_ACTIVE) session_start();
  require_auth_at_most(ROLE_ADMIN, "/admin/login.php");
}
