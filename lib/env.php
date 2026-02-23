<?php
function env_load($path = null) {
  static $loaded = false;
  if ($loaded) return;
  if ($path === null) {
    $base = __DIR__ . "/../";
    $candidates = [$base . ".env.local", $base . ".env"];
    foreach ($candidates as $p) { if (is_file($p)) { $path = $p; break; } }
    if ($path === null) $path = $base . ".env";
  }
  if (!is_file($path)) { $loaded = true; return; }
  $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
  foreach ($lines as $line) {
    $t = trim($line);
    if ($t === "" || $t[0] === "#") continue;
    $pos = strpos($line, "=");
    if ($pos === false) continue;
    $key = trim(substr($line, 0, $pos));
    $val = trim(substr($line, $pos + 1));
    $len = strlen($val);
    if ($len >= 2) {
      $f = $val[0];
      $l = $val[$len - 1];
      if (($f === '"' && $l === '"') || ($f === "'" && $l === "'")) {
        $val = substr($val, 1, $len - 2);
      }
    }
    putenv($key . "=" . $val);
    $_ENV[$key] = $val;
  }
  $loaded = true;
}
