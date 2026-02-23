<?php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
function csrf_token() {
  if (!isset($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(16));
  }
  return $_SESSION["csrf_token"];
}
function csrf_validate($token) {
  if (!isset($_SESSION["csrf_token"])) return false;
  return hash_equals($_SESSION["csrf_token"], (string)$token);
}
