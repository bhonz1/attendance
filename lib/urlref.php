<?php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
function url_ref_create($params) {
  $tok = bin2hex(random_bytes(16));
  if (!isset($_SESSION["__url_ref"])) $_SESSION["__url_ref"] = [];
  $_SESSION["__url_ref"][$tok] = $params;
  return $tok;
}
function url_ref_consume($tok) {
  if (!is_string($tok) || $tok === "") return null;
  if (!isset($_SESSION["__url_ref"]) || !isset($_SESSION["__url_ref"][$tok])) return null;
  $v = $_SESSION["__url_ref"][$tok];
  unset($_SESSION["__url_ref"][$tok]);
  return is_array($v) ? $v : null;
}
