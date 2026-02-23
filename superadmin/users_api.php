<?php
require_once __DIR__ . "/../lib/admin.php";
require_once __DIR__ . "/../lib/roles.php";
require_once __DIR__ . "/../lib/supabase.php";
require_once __DIR__ . "/../lib/csrf.php";
require_auth_at_most(ROLE_SUPERADMIN, "/admin/login.php");
header("Content-Type: application/json");
header("Cache-Control: no-store");
header("X-Content-Type-Options: nosniff");
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
if (!defined("UNIT_TEST")) define("UNIT_TEST", false);
$method = $_SERVER["REQUEST_METHOD"] ?? "";
$op = $_GET["op"] ?? $_POST["op"] ?? "";
function out($d){ echo json_encode($d); if (!UNIT_TEST) exit; }
function is_super(){ $rid = get_auth_id(); return $rid !== null && $rid === ROLE_SUPERADMIN; }
function clean_status($s){ $s = strtolower(trim((string)$s)); return in_array($s, ["active","inactive","suspended"], true) ? $s : null; }
function clean_role($r){ $i = (int)$r; return ($i === ROLE_SUPERADMIN || $i === ROLE_ADMIN) ? $i : null; }
function to_in_clause($ids){ $ids = array_values(array_filter(array_map(function($v){ return (int)$v; }, (array)$ids), function($v){ return $v > 0; })); return count($ids) ? "(" . implode(",", $ids) . ")" : null; }
function captcha_validate($token, $answer) {
  if (session_status() !== PHP_SESSION_ACTIVE) session_start();
  if (!isset($_SESSION["__captcha_delete"]) || !is_array($_SESSION["__captcha_delete"])) $_SESSION["__captcha_delete"] = [];
  $map = $_SESSION["__captcha_delete"];
  $key = (string)$token;
  $val = isset($map[$key]) ? $map[$key] : null;
  $ok = ($val !== null) && hash_equals($val, hash("sha256", (string)$answer));
  if ($val !== null) { unset($_SESSION["__captcha_delete"][$key]); }
  return $ok;
}
if ($method === "POST" && !csrf_validate($_POST["csrf"] ?? "")) { out(["ok"=>false,"error"=>"Invalid CSRF"]); }
if ($op === "list" && $method === "GET") {
  $page = max(1, (int)($_GET["page"] ?? 1));
  $pageSize = min(100, max(1, (int)($_GET["page_size"] ?? 10)));
  $search = trim($_GET["search"] ?? "");
  $role = isset($_GET["role_id"]) && $_GET["role_id"] !== "" ? clean_role($_GET["role_id"]) : null;
  $status = isset($_GET["status"]) && $_GET["status"] !== "" ? clean_status($_GET["status"]) : null;
  $createdStart = trim($_GET["created_start"] ?? "");
  $createdEnd = trim($_GET["created_end"] ?? "");
  $items = [];
  if (sb_url()) {
    $q = ["select"=>"id,username,full_name,role_id,status","order"=>"id.desc","limit"=>$pageSize,"offset"=>($page-1)*$pageSize];
    if ($search !== "") $q["or"] = "(username.ilike.*" . $search . "*,full_name.ilike.*" . $search . "*)";
    if ($role !== null) $q["role_id"] = "eq." . $role;
    if ($status !== null) $q["status"] = "eq." . $status;
    if ($createdStart !== "") $q["created_at"] = "gte." . $createdStart;
    if ($createdEnd !== "") $q["created_at"] = isset($q["created_at"]) ? $q["created_at"] . ",lte." . $createdEnd : "lte." . $createdEnd;
    $rows = sb_get("users", $q);
    if ($rows === null) {
      $e = sb_last_error();
      $msg = "Upstream error";
      if (is_array($e)) {
        $bj = $e["body_json"] ?? null;
        if (is_array($bj) && isset($bj["message"])) $msg = "Supabase: " . $bj["message"];
        else if (isset($e["code"]) && $e["code"]) $msg = "Supabase HTTP " . $e["code"];
      }
      out(["ok"=>false,"error"=>$msg]);
    }
    $items = is_array($rows) ? $rows : [];
  } else {
    if (!isset($_SESSION["__users"]) || !is_array($_SESSION["__users"])) $_SESSION["__users"] = [];
    $pool = $_SESSION["__users"];
    $filtered = [];
    foreach ($pool as $u) {
      if ($search !== "" && stripos($u["username"] ?? "", $search) === false) continue;
      if ($role !== null && (int)($u["role_id"] ?? -1) !== $role) continue;
      if ($status !== null && ($u["status"] ?? "") !== $status) continue;
      $filtered[] = $u;
    }
    usort($filtered, function($a,$b){ return ($b["id"] ?? 0) <=> ($a["id"] ?? 0); });
    $offset = ($page-1)*$pageSize;
    $items = array_slice($filtered, $offset, $pageSize);
  }
  out(["ok"=>true,"items"=>$items, "page"=>$page, "page_size"=>$pageSize, "total"=>count($items)]);
} elseif ($op === "captcha" && $method === "GET") {
  if (session_status() !== PHP_SESSION_ACTIVE) session_start();
  if (!isset($_SESSION["__captcha_delete"]) || !is_array($_SESSION["__captcha_delete"])) $_SESSION["__captcha_delete"] = [];
  $a = random_int(2, 9);
  $b = random_int(2, 9);
  $ans = (string)($a + $b);
  $tok = bin2hex(random_bytes(8));
  $_SESSION["__captcha_delete"][$tok] = hash("sha256", $ans);
  out(["ok"=>true,"token"=>$tok,"prompt"=>"What is ".$a." + ".$b."?"]);
} elseif ($op === "create" && $method === "POST") {
  $username = trim($_POST["username"] ?? "");
  $full_name = trim($_POST["full_name"] ?? "");
  $password = $_POST["password"] ?? "";
  $role_id = clean_role($_POST["role_id"] ?? -1);
  $status = clean_status($_POST["status"] ?? "active");
  if ($username === "" || strlen($password) < 6 || $role_id === null || $status === null) out(["ok"=>false,"error"=>"Invalid data"]);
  $hash = password_hash($password, PASSWORD_DEFAULT);
  if (sb_url()) {
    $body = ["username"=>$username,"password_hash"=>$hash,"role_id"=>$role_id,"status"=>$status];
    if ($full_name !== "") $body["full_name"] = $full_name;
    $r = sb_post("users", $body);
    if (is_array($r) && isset($r[0]) && isset($r[0]["id"])) out(["ok"=>true,"item"=>["id"=>$r[0]["id"]]]);
    out(["ok"=>false,"error"=>"Create failed"]);
  } else {
    if (!isset($_SESSION["__users"]) || !is_array($_SESSION["__users"])) $_SESSION["__users"] = [];
    foreach ($_SESSION["__users"] as $u) { if (($u["username"] ?? "") === $username) out(["ok"=>false,"error"=>"Username exists"]); }
    $id = count($_SESSION["__users"]) + 1;
    $_SESSION["__users"][] = ["id"=>$id,"username"=>$username,"full_name"=>$full_name,"password_hash"=>$hash,"role_id"=>$role_id,"status"=>$status,"two_factor_enabled"=>false,"created_at"=>date("c"),"last_login"=>null];
    out(["ok"=>true,"item"=>["id"=>$id]]);
  }
} elseif ($op === "update" && $method === "POST") {
  $id = (int)($_POST["id"] ?? 0);
  if ($id <= 0) out(["ok"=>false,"error"=>"Invalid id"]);
  $body = [];
  if (isset($_POST["username"])) { $u = trim($_POST["username"]); if ($u !== "") $body["username"] = $u; }
  if (isset($_POST["full_name"])) { $fn = trim($_POST["full_name"]); if ($fn !== "") $body["full_name"] = $fn; }
  if (isset($_POST["status"])) { $s = clean_status($_POST["status"]); if ($s !== null) $body["status"] = $s; }
  if (isset($_POST["role_id"])) {
    if (!is_super()) out(["ok"=>false,"error"=>"Unauthorized role change"]);
    $r = clean_role($_POST["role_id"]);
    if ($r === null) out(["ok"=>false,"error"=>"Invalid role"]);
    $body["role_id"] = $r;
  }
  if (empty($body)) out(["ok"=>false,"error"=>"No changes"]);
  if (sb_url()) {
    $r = sb_patch("users", $body, ["id"=>"eq.".$id]);
    if ($r === null) {
      $e = sb_last_error();
      $msg = "Update failed";
      if (is_array($e)) {
        $bj = $e["body_json"] ?? null;
        if (is_array($bj) && isset($bj["message"])) $msg = "Supabase: " . $bj["message"];
        else if (isset($e["code"]) && $e["code"]) $msg = "Supabase HTTP " . $e["code"];
      }
      out(["ok"=>false,"error"=>$msg]);
    }
    out(["ok"=>true]);
  } else {
    if (!isset($_SESSION["__users"]) || !is_array($_SESSION["__users"])) $_SESSION["__users"] = [];
    foreach ($_SESSION["__users"] as &$row) {
      if (($row["id"] ?? 0) === $id) {
        foreach ($body as $k=>$v) $row[$k] = $v;
        out(["ok"=>true]);
      }
    }
    out(["ok"=>false,"error"=>"Not found"]);
  }
} elseif ($op === "delete" && $method === "POST") {
  $id = (int)($_POST["id"] ?? 0);
  if ($id <= 0) out(["ok"=>false,"error"=>"Invalid id"]);
  $ct = $_POST["captcha_token"] ?? "";
  $ca = $_POST["captcha_answer"] ?? "";
  if (!captcha_validate($ct, $ca)) out(["ok"=>false,"error"=>"Invalid verification"]);
  if (sb_url()) {
    $r = sb_delete("users", ["id"=>"eq.".$id]);
    if ($r === null) {
      $e = sb_last_error();
      $msg = "Delete failed";
      if (is_array($e)) {
        $bj = $e["body_json"] ?? null;
        if (is_array($bj) && isset($bj["message"])) $msg = "Supabase: " . $bj["message"];
        else if (isset($e["code"]) && $e["code"]) $msg = "Supabase HTTP " . $e["code"];
      }
      out(["ok"=>false,"error"=>$msg]);
    }
    out(["ok"=>true]);
  } else {
    if (!isset($_SESSION["__users"]) || !is_array($_SESSION["__users"])) $_SESSION["__users"] = [];
    $new = []; $found = false;
    foreach ($_SESSION["__users"] as $row) { if (($row["id"] ?? 0) === $id) { $found = true; continue; } $new[] = $row; }
    $_SESSION["__users"] = $new;
    out(["ok"=>$found]);
  }
} elseif ($op === "bulk_status" && $method === "POST") {
  $ids = $_POST["ids"] ?? $_POST["ids[]"] ?? [];
  $status = clean_status($_POST["status"] ?? "");
  if ($status === null) out(["ok"=>false,"error"=>"Invalid status"]);
  $in = to_in_clause($ids);
  if ($in === null) out(["ok"=>false,"error"=>"No ids"]);
  if (sb_url()) {
    $r = sb_patch("users", ["status"=>$status], ["id"=>"in.".$in]);
    if ($r === null) {
      $e = sb_last_error();
      $msg = "Bulk update failed";
      if (is_array($e)) {
        $bj = $e["body_json"] ?? null;
        if (is_array($bj) && isset($bj["message"])) $msg = "Supabase: " . $bj["message"];
        else if (isset($e["code"]) && $e["code"]) $msg = "Supabase HTTP " . $e["code"];
      }
      out(["ok"=>false,"error"=>$msg]);
    }
    out(["ok"=>true]);
  } else {
    if (!isset($_SESSION["__users"]) || !is_array($_SESSION["__users"])) $_SESSION["__users"] = [];
    foreach ($_SESSION["__users"] as &$row) { if (in_array((int)$row["id"], array_map("intval", (array)$ids), true)) $row["status"] = $status; }
    out(["ok"=>true]);
  }
} elseif ($op === "assign_role" && $method === "POST") {
  if (!is_super()) out(["ok"=>false,"error"=>"Unauthorized"]);
  $ids = $_POST["ids"] ?? $_POST["ids[]"] ?? [];
  $role_id = clean_role($_POST["role_id"] ?? -1);
  if ($role_id === null) out(["ok"=>false,"error"=>"Invalid role"]);
  $in = to_in_clause($ids);
  if ($in === null) out(["ok"=>false,"error"=>"No ids"]);
  if (sb_url()) {
    $r = sb_patch("users", ["role_id"=>$role_id], ["id"=>"in.".$in]);
    if ($r === null) {
      $e = sb_last_error();
      $msg = "Assign role failed";
      if (is_array($e)) {
        $bj = $e["body_json"] ?? null;
        if (is_array($bj) && isset($bj["message"])) $msg = "Supabase: " . $bj["message"];
        else if (isset($e["code"]) && $e["code"]) $msg = "Supabase HTTP " . $e["code"];
      }
      out(["ok"=>false,"error"=>$msg]);
    }
    out(["ok"=>true]);
  } else {
    if (!isset($_SESSION["__users"]) || !is_array($_SESSION["__users"])) $_SESSION["__users"] = [];
    foreach ($_SESSION["__users"] as &$row) { if (in_array((int)$row["id"], array_map("intval", (array)$ids), true)) $row["role_id"] = $role_id; }
    out(["ok"=>true]);
  }
} else {
  out(["ok"=>false,"error"=>"Unknown op"]);
}
