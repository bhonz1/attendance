<?php
require_once __DIR__ . "/../lib/admin.php";
require_once __DIR__ . "/../lib/roles.php";
require_auth_at_most(ROLE_SUPERADMIN, "/admin/login.php");
require_once __DIR__ . "/../lib/supabase.php";
header("Content-Type: application/json");
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
if (!defined("UNIT_TEST")) define("UNIT_TEST", false);
$useSb = (sb_url() !== null) && !UNIT_TEST;
function out($d){ echo json_encode($d); if (!UNIT_TEST) exit; }
function ensure_store(){
  if (!isset($_SESSION["__sys_logs"])) $_SESSION["__sys_logs"] = [];
  if (!isset($_SESSION["__sys_logs_access"])) $_SESSION["__sys_logs_access"] = [];
  if (!isset($_SESSION["__log_retention_days"])) $_SESSION["__log_retention_days"] = (int)(getenv("LOG_RETENTION_DAYS") ?: 90);
}
function prune(){
  ensure_store();
  $days = (int)$_SESSION["__log_retention_days"];
  $cut = time() - ($days * 86400);
  $_SESSION["__sys_logs"] = array_values(array_filter($_SESSION["__sys_logs"], function($e) use ($cut){
    $ts = isset($e["timestamp"]) ? strtotime($e["timestamp"]) : null;
    return !$ts || $ts >= $cut;
  }));
}
function audit_access($actor, $action){
  global $useSb;
  ensure_store();
  $rec = [
    "at" => gmdate("c"),
    "actor" => $actor,
    "action" => $action,
    "ip" => $_SERVER["REMOTE_ADDR"] ?? "",
    "ua" => $_SERVER["HTTP_USER_AGENT"] ?? "",
    "session_id" => session_id()
  ];
  if ($useSb) {
    sb_post("logs_access", $rec);
  } else {
    $_SESSION["__sys_logs_access"][] = $rec;
  }
}
function matches($e, $f){
  if (($f["severity"] ?? "") !== "" && ($e["severity"] ?? "") !== $f["severity"]) return false;
  if (($f["role"] ?? "") !== "" && (string)($e["user_role"] ?? "") !== (string)$f["role"]) return false;
  if (($f["action"] ?? "") !== "" && stripos($e["action"] ?? "", $f["action"]) === false) return false;
  if (($f["ip"] ?? "") !== "" && stripos($e["ip"] ?? "", $f["ip"]) === false) return false;
  if (($f["start"] ?? "") !== "") {
    $ts = isset($e["timestamp"]) ? strtotime($e["timestamp"]) : 0;
    if ($ts < strtotime($f["start"])) return false;
  }
  if (($f["end"] ?? "") !== "") {
    $ts = isset($e["timestamp"]) ? strtotime($e["timestamp"]) : 0;
    if ($ts > strtotime($f["end"] . " 23:59:59")) return false;
  }
  $q = trim($f["search"] ?? "");
  if ($q !== "") {
    $hay = json_encode($e);
    if (($f["regex"] ?? "0") === "1") {
      set_error_handler(function(){});
      $m = @preg_match("/$q/i", $hay);
      restore_error_handler();
      if ($m !== 1) return false;
    } else {
      if (stripos($hay, $q) === false) return false;
    }
  }
  return true;
}
$op = $_GET["op"] ?? $_POST["op"] ?? "list";
prune();
if ($op === "list") {
  ensure_store();
  $filters = [
    "severity" => $_GET["severity"] ?? "",
    "role" => $_GET["role"] ?? "",
    "action" => $_GET["action"] ?? "",
    "ip" => $_GET["ip"] ?? "",
    "start" => $_GET["start"] ?? "",
    "end" => $_GET["end"] ?? "",
    "search" => $_GET["search"] ?? "",
    "regex" => $_GET["regex"] ?? "0"
  ];
  $page = max(1, (int)($_GET["page"] ?? 1));
  $size = min(1000, max(10, (int)($_GET["page_size"] ?? 100)));
  if ($useSb) {
    $q = ["select"=>"id,timestamp,user_id,user_role,action,resource,ip,user_agent,session_id,response_status,severity,actor","order"=>"timestamp.desc","limit"=>$size,"offset"=>($page-1)*$size];
    if ($filters["severity"] !== "") $q["severity"] = "eq." . $filters["severity"];
    if ($filters["role"] !== "") $q["user_role"] = "eq." . $filters["role"];
    if ($filters["action"] !== "") $q["action"] = "ilike.*" . $filters["action"] . "*";
    if ($filters["ip"] !== "") $q["ip"] = "ilike.*" . $filters["ip"] . "*";
    if ($filters["start"] !== "") $q["timestamp"] = "gte." . $filters["start"];
    if ($filters["end"] !== "") $q["timestamp"] = isset($q["timestamp"]) ? $q["timestamp"] . ",lte." . $filters["end"] : "lte." . $filters["end"];
    if ($filters["search"] !== "" && $filters["regex"] === "0") {
      $s = $filters["search"];
      $q["or"] = "(actor.ilike.*$s*,action.ilike.*$s*,resource.ilike.*$s*,ip.ilike.*$s*,user_agent.ilike.*$s*,session_id.ilike.*$s*,response_status.ilike.*$s*,severity.ilike.*$s*)";
    }
    $items = sb_get("system_logs", $q) ?: [];
    if ($filters["search"] !== "" && $filters["regex"] === "1") {
      $items = array_values(array_filter($items, function($e) use ($filters){ return matches($e, $filters); }));
    }
    $total = is_array($items) ? count($items) + (($page-1)*$size) : 0;
    audit_access($_SESSION["admin_user"] ?? "admin", "logs_list");
    out(["ok"=>true,"items"=>$items ?: [],"page"=>$page,"page_size"=>$size,"total"=>$total]);
  } else {
    $all = $_SESSION["__sys_logs"];
    $filtered = array_values(array_filter($all, function($e) use ($filters){ return matches($e, $filters); }));
    $total = count($filtered);
    $start = ($page - 1) * $size;
    $items = array_slice($filtered, $start, $size);
    audit_access($_SESSION["admin_user"] ?? "admin", "logs_list");
    out(["ok"=>true,"items"=>$items,"page"=>$page,"page_size"=>$size,"total"=>$total]);
  }
} else if ($op === "stream") {
  ensure_store();
  $since = (int)($_GET["since"] ?? 0);
  if ($useSb) {
    $q = ["select"=>"id,timestamp,user_id,user_role,action,resource,ip,user_agent,session_id,response_status,severity,actor","order"=>"id.asc","id"=>"gt.".$since,"limit"=>1000];
    $items = sb_get("system_logs", $q) ?: [];
    $last = (count($items) ? end($items)["id"] : $since);
    audit_access($_SESSION["admin_user"] ?? "admin", "logs_stream");
    out(["ok"=>true,"items"=>$items,"last_id"=>$last]);
  } else {
    $items = $_SESSION["__sys_logs"];
    $items = array_values(array_filter($items, function($e) use ($since){ return ($e["id"] ?? 0) > $since; }));
    audit_access($_SESSION["admin_user"] ?? "admin", "logs_stream");
    out(["ok"=>true,"items"=>$items,"last_id"=> (count($items) ? end($items)["id"] : $since)]);
  }
} else if ($op === "export") {
  ensure_store();
  $type = $_GET["type"] ?? "csv";
  $fields = isset($_GET["fields"]) ? explode(",", $_GET["fields"]) : ["timestamp","user_id","user_role","action","resource","ip","user_agent","session_id","response_status","severity"];
  $items = $_SESSION["__sys_logs"];
  if ($useSb) {
    $items = sb_get("system_logs", ["select"=>implode(",", $fields),"limit"=>1000]) ?: [];
  }
  audit_access($_SESSION["admin_user"] ?? "admin", "logs_export_$type");
  if ($type === "json") {
    header("Content-Type: application/json");
    header("Content-Disposition: attachment; filename=logs.json");
    out(["ok"=>true,"items"=>$items]);
  } else if ($type === "csv") {
    header("Content-Type: text/csv");
    header("Content-Disposition: attachment; filename=logs.csv");
    $out = fopen("php://output", "w");
    fputcsv($out, $fields);
    foreach ($items as $e) {
      $row = [];
      foreach ($fields as $f) { $row[] = $e[$f] ?? ""; }
      fputcsv($out, $row);
    }
    fclose($out);
    if (!UNIT_TEST) exit;
  } else if ($type === "pdf") {
    header("Content-Type: text/html");
    header("Content-Disposition: attachment; filename=logs.html");
    echo "<html><head><meta charset='utf-8'><title>Logs Export</title><style>table{border-collapse:collapse}td,th{border:1px solid #ccc;padding:6px}</style></head><body>";
    echo "<h3>Logs Export</h3><table><thead><tr>";
    foreach ($fields as $f) echo "<th>".htmlspecialchars($f)."</th>";
    echo "</tr></thead><tbody>";
    foreach ($items as $e) {
      echo "<tr>";
      foreach ($fields as $f) echo "<td>".htmlspecialchars((string)($e[$f] ?? ""))."</td>";
      echo "</tr>";
    }
    echo "</tbody></table><script>window.print()</script></body></html>";
    if (!UNIT_TEST) exit;
  } else {
    out(["ok"=>false,"error"=>"Unsupported export type"]);
  }
} else if ($op === "set_retention") {
  ensure_store();
  $days = (int)($_POST["days"] ?? 90);
  $_SESSION["__log_retention_days"] = max(1, $days);
  prune();
  if ($useSb) {
    $cut = gmdate("Y-m-d", time() - ($_SESSION["__log_retention_days"]*86400));
    sb_delete("system_logs", ["timestamp"=>"lt.".$cut]);
  }
  audit_access($_SESSION["admin_user"] ?? "admin", "set_retention");
  out(["ok"=>true,"days"=>$_SESSION["__log_retention_days"]]);
} else {
  out(["ok"=>false,"error"=>"Unknown op"]);
}
