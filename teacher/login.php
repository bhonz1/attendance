<?php
require_once __DIR__ . "/../lib/auth.php";
require_once __DIR__ . "/../lib/csrf.php";
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
$err = null;
$msg = null;
$prefill = trim($_POST["username"] ?? "");
if ($_SERVER["REQUEST_METHOD"] === "POST") {
  if (!csrf_validate($_POST["csrf"] ?? "")) {
    $err = "Invalid request";
  } else {
    $u = trim($_POST["username"] ?? "");
    $p = (string)($_POST["password"] ?? "");
    $r = login_teacher($u, $p);
    if (is_array($r) && ($r["ok"] ?? false)) {
      http_redirect("/teacher/dashboard.php");
    } else {
      $err = is_array($r) ? ($r["error"] ?? "Login failed") : "Login failed";
    }
  }
}
$f = $_SESSION["__flash_error"] ?? null;
unset($_SESSION["__flash_error"]);
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Teacher Login</title>
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
        <div class="login-hero__title">Teacher Sign In</div>
        <div class="login-hero__subtitle">Access your teacher dashboard</div>
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
            <h4 class="mb-0">Sign in to Teacher</h4>
            <div class="text-muted">Use your teacher account</div>
          </div>
          <?php if ($f === "unauthorized"): ?><div class="alert alert-warning">Please sign in</div><?php endif; ?>
          <?php if ($f === "duplicate_session"): ?><div class="alert alert-warning">Session ended, please sign in again</div><?php endif; ?>
          <?php if ($err): ?><div class="alert alert-danger"><?= htmlspecialchars($err) ?></div><?php endif; ?>
          <?php if ($msg): ?><div class="alert alert-success"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
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
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
