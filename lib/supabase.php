<?php
require_once __DIR__ . "/env.php";
env_load();
function sb_last_error() {
  return $GLOBALS["__sb_last_error"] ?? null;
}
function sb_url() {
  $u = getenv("SUPABASE_URL");
  return $u ? rtrim($u, "/") . "/rest/v1" : null;
}
function sb_key_read() {
  $k = getenv("SUPABASE_ANON_KEY");
  if (!$k || $k === "") {
    $k = getenv("SUPABASE_PUBLISHABLE_KEY");
  }
  return $k ?: null;
}
function sb_key_write() {
  $k = getenv("SUPABASE_SERVICE_ROLE");
  $k = $k ?: "";
  $isJwt = function($s){
    if (!is_string($s) || strlen($s) < 20) return false;
    if (strpos($s, ".") === false) return false;
    $parts = explode(".", $s);
    if (count($parts) < 2) return false;
    return strlen($parts[0]) > 10 && strlen($parts[1]) > 10;
  };
  if (!$isJwt($k) || stripos($k, "YOUR-SERVICE-ROLE-KEY") !== false) {
    $k = getenv("SUPABASE_ANON_KEY");
  }
  return $k ?: null;
}
function sb_headers($write=false) {
  $key = $write ? sb_key_write() : (sb_key_read() ?: sb_key_write());
  if (!$key) return null;
  return [
    "apikey: " . $key,
    "Authorization: Bearer " . $key,
    "Accept: application/json",
    "Content-Type: application/json",
    $write ? "Prefer: return=representation" : ""
  ];
}
function sb_request($method, $path, $body=null, $query=[], $write=false, $extraHdrs=[]) {
  $base = sb_url();
  $hdrs = sb_headers($write);
  if (!$base || !$hdrs) return null;
  $url = $base . "/" . ltrim($path, "/");
  if ($query) $url .= "?" . http_build_query($query);
  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, $url);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  $hdrs = array_values(array_filter(array_merge($hdrs, $extraHdrs)));
  curl_setopt($ch, CURLOPT_HTTPHEADER, $hdrs);
  curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 8);
  curl_setopt($ch, CURLOPT_TIMEOUT, 16);
  if (strtoupper($method) === "POST") curl_setopt($ch, CURLOPT_POST, true);
  else curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
  if ($body !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
  $resp = curl_exec($ch);
  $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);
  if ($code >= 200 && $code < 300) {
    $GLOBALS["__sb_last_error"] = null;
    return $resp && strlen($resp) ? json_decode($resp, true) : [];
  }
  $GLOBALS["__sb_last_error"] = [
    "code" => $code,
    "body_raw" => $resp,
    "body_json" => $resp && strlen($resp) ? (json_decode($resp, true) ?: null) : null,
  ];
  return null;
}
function sb_get($path, $query=[]) { return sb_request("GET", $path, null, $query, true); }
function sb_post($path, $body) { return sb_request("POST", $path, $body, [], true); }
function sb_patch($path, $body, $query=[]) { return sb_request("PATCH", $path, $body, $query, true); }
function sb_delete($path, $query=[]) {
  return sb_request("DELETE", $path, null, $query, true);
}
function sb_upsert($path, $body, $on_conflict=null) {
  $q = [];
  if ($on_conflict) $q["on_conflict"] = $on_conflict;
  return sb_request("POST", $path, $body, $q, true, ["Prefer: resolution=merge-duplicates", "Prefer: return=representation"]);
}
function sb_rpc($fn, $args=[]) {
  return sb_request("POST", "rpc/" . $fn, $args, [], true);
}
