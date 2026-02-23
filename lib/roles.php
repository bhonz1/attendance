<?php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
const ROLE_SUPERADMIN = 0;
const ROLE_ADMIN = 1;
const ROLE_TEACHER = 2;
function http_base_url() {
  $envBase = getenv("APP_BASE_URL");
  if (is_string($envBase) && $envBase !== "") return rtrim($envBase, "/");
  $proto = $_SERVER["HTTP_X_FORWARDED_PROTO"] ?? $_SERVER["HTTP_X_FORWARDED_PROTOCOL"] ?? $_SERVER["HTTP_X_FORWARDED_SCHEME"] ?? null;
  if (is_string($proto) && strpos($proto, ",") !== false) $proto = trim(explode(",", $proto)[0]);
  $https = (!empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off") || (is_string($proto) && strtolower($proto) === "https");
  $scheme = $https ? "https" : "http";
  $host = $_SERVER["HTTP_X_FORWARDED_HOST"] ?? $_SERVER["HTTP_HOST"] ?? "localhost:8000";
  if (is_string($host) && strpos($host, ",") !== false) $host = trim(explode(",", $host)[0]);
  $port = $_SERVER["HTTP_X_FORWARDED_PORT"] ?? null;
  if (is_string($port) && strpos($port, ",") !== false) $port = trim(explode(",", $port)[0]);
  if (is_string($port) && $port !== "" && is_string($host) && strpos($host, ":") === false) {
    $isDefault = ($scheme === "https" && $port === "443") || ($scheme === "http" && $port === "80");
    if (!$isDefault) $host .= ":" . $port;
  }
  return $scheme . "://" . $host;
}
function http_redirect($path, $status = null) {
  $target = $path;
  if (strpos($path, "http://") !== 0 && strpos($path, "https://") !== 0) {
    $target = http_base_url() . (strpos($path, "/") === 0 ? $path : ("/" . $path));
  } else {
    $cur = http_base_url();
    $pu = parse_url($path);
    $ph = $pu["host"] ?? "";
    $ch = parse_url($cur, PHP_URL_HOST) ?: "";
    if (strcasecmp($ph, $ch) !== 0) {
      $target = $cur;
    }
  }
  if ($status === null) {
    $m = strtoupper($_SERVER["REQUEST_METHOD"] ?? "GET");
    $status = ($m === "POST") ? 303 : 302;
  }
  header("Location: " . $target, true, $status);
  exit;
}
function get_auth_id() {
  return isset($_SESSION["auth_id"]) ? (int)$_SESSION["auth_id"] : null;
}
function set_auth_id($role) {
  $_SESSION["auth_id"] = (int)$role;
}
function require_auth_at_most($maxRole, $redirect = "/admin/login.php") {
  if (session_status() !== PHP_SESSION_ACTIVE) session_start();
  $rid = get_auth_id();
  if ($rid === null || $rid > $maxRole) {
    if (php_sapi_name() !== "cli") {
      $_SESSION["__flash_error"] = "unauthorized";
      http_redirect($redirect);
    }
  }
}
