<?php
require_once __DIR__ . "/../lib/admin.php";
require_once __DIR__ . "/../lib/roles.php";
require_once __DIR__ . "/../lib/csrf.php";
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
$rid = get_auth_id();
if ($rid === ROLE_SUPERADMIN) { http_redirect("/superadmin/dashboard.php"); }
$prefill = trim($_POST["username"] ?? "");
if ($_SERVER["REQUEST_METHOD"] === "POST") {
  if (!csrf_validate($_POST["csrf"] ?? "")) {
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    $_SESSION["__flash_error"] = "Invalid request";
    http_redirect("/admin/login.php");
  }
  $u = trim($_POST["username"] ?? "");
  $p = $_POST["password"] ?? "";
  $res = admin_login($u, $p);
  if ($res["ok"]) {
    $role = get_auth_id();
    if ($role === ROLE_SUPERADMIN) { http_redirect("/superadmin/dashboard.php"); }
    http_redirect("/admin/dashboard.php");
  }
  if (session_status() !== PHP_SESSION_ACTIVE) session_start();
  $_SESSION["__flash_error"] = $res["error"] ?? "Unauthorized";
  http_redirect("/admin/login.php");
}
$error = isset($_SESSION["__flash_error"]) ? $_SESSION["__flash_error"] : null;
if ($error !== null) unset($_SESSION["__flash_error"]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Admin Login</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
.login-hero { position: relative; padding: 32px 0; background: linear-gradient(120deg, #0ea5e9 0%, #6366f1 45%, #0f172a 100%); color: #ffffff; border-bottom: 1px solid rgba(255,255,255,0.18); }
.login-hero__inner { display: flex; align-items: center; justify-content: space-between; gap: 16px; }
.login-hero__title { font-size: 28px; font-weight: 700; letter-spacing: 0.2px; }
.login-hero__subtitle { font-size: 14px; opacity: 0.9; }
.login-card { border: none; border-radius: 14px; box-shadow: 0 10px 24px rgba(2,6,23,0.08); }
</style>
</head>
<body class="d-flex flex-column min-vh-100 bg-light">
<div class="login-hero">
  <div class="container">
    <div class="login-hero__inner">
      <div>
        <div class="login-hero__title">Admin Sign In</div>
        <div class="login-hero__subtitle">Access attendance administration</div>
      </div>
      <div>
        <a href="/index.php" class="btn btn-light btn-sm">Back</a>
      </div>
    </div>
  </div>
  </div>
<div class="container py-5">
  <div class="row justify-content-center">
    <div class="col-md-5">
      <div class="card login-card">
        <div class="card-body">
          <div class="text-center mb-3">
            <h4 class="mb-0">Sign in to Admin</h4>
            <div class="text-muted">Use your admin credentials</div>
          </div>
          <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
          <form method="post" autocomplete="off">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
            <div class="mb-3"><label class="form-label">Username</label><input type="text" name="username" class="form-control" required autofocus value="<?= htmlspecialchars($prefill) ?>"></div>
            <div class="mb-3"><label class="form-label">Password</label><input type="password" name="password" class="form-control" required></div>
            <button type="submit" class="btn btn-primary w-100">Login</button>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>
<footer class="text-center text-muted small py-3 border-top mt-auto">
  <div class="container">© 2026 Attendance Tracker | Developed by: Von P. Gabayan Jr.</div>
</footer>
</body>
</html>
