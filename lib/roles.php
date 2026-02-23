<?php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
const ROLE_SUPERADMIN = 0;
const ROLE_ADMIN = 1;
const ROLE_TEACHER = 2;
function http_base_url() {
  $https = (!empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off");
  $scheme = $https ? "https" : "http";
  $host = $_SERVER["HTTP_HOST"] ?? "localhost:8000";
  return $scheme . "://" . $host;
}
function http_redirect($path, $status = null) {
  $target = $path;
  if (strpos($path, "http://") !== 0 && strpos($path, "https://") !== 0) {
    $target = http_base_url() . (strpos($path, "/") === 0 ? $path : ("/" . $path));
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
