<?php
require_once __DIR__ . "/../lib/admin.php";
require_once __DIR__ . "/../lib/roles.php";
require_auth_at_most(ROLE_SUPERADMIN, "/admin/login.php");
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
if (get_auth_id() !== ROLE_SUPERADMIN) { http_redirect("/admin/dashboard.php"); }
$user = $_SESSION["admin_user"] ?? "superadmin";
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Superadmin Dashboard</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
.sa-hero { position: relative; padding: 40px 0; background: linear-gradient(120deg, #9333ea 0%, #0ea5e9 45%, #0f172a 100%); color: #ffffff; border-bottom: 1px solid rgba(255,255,255,0.18); }
.sa-hero__inner { display: flex; align-items: center; justify-content: space-between; gap: 16px; }
.sa-hero__title { font-size: 28px; font-weight: 700; letter-spacing: 0.2px; }
.sa-hero__subtitle { font-size: 14px; opacity: 0.9; }
.sa-hero__art { width: 280px; height: 160px; opacity: 0.9; }
.metric-card .metric { display: grid; grid-template-columns: 1fr auto; align-items: center; }
.metric-card .metric__title { font-size: 12px; text-transform: uppercase; letter-spacing: 0.6px; color: #64748b; }
.metric-card .metric__value { font-size: 28px; font-weight: 700; color: #0f172a; }
.action-card { position: relative; overflow: hidden; }
.action-card .badge { position: absolute; top: 12px; right: 12px; }
.action-card .cta { display: flex; align-items: center; justify-content: space-between; }
.cta .label { font-weight: 600; }
.cta svg { width: 24px; height: 24px; color: #6366f1; }
</style>
</head>
<body class="d-flex flex-column min-vh-100">
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
  <div class="container-fluid">
    <a class="navbar-brand" href="/superadmin/dashboard.php">Superadmin</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#saNav" aria-controls="saNav" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="saNav">
      <ul class="navbar-nav me-auto mb-2 mb-lg-0">
        <li class="nav-item"><a class="nav-link" href="/superadmin/logs.php">Logs</a></li>
        <li class="nav-item"><a class="nav-link" href="/superadmin/users.php">Users</a></li>
      </ul>
      <div class="d-flex">
        <a class="btn btn-outline-light btn-sm" href="/admin/logout.php">Logout</a>
      </div>
    </div>
  </div>
</nav>
<div class="sa-hero">
  <div class="container">
    <div class="sa-hero__inner">
      <div>
        <div class="sa-hero__title">Superadmin Control Center</div>
        <div class="sa-hero__subtitle">Manage users and audit system activity</div>
        <div class="mt-2 text-white-50 small">Signed in as <?= htmlspecialchars($user) ?></div>
      </div>
      <div class="sa-hero__art" aria-hidden="true">
        <svg viewBox="0 0 260 160">
          <defs>
            <linearGradient id="s1" x1="0" y1="0" x2="1" y2="1">
              <stop offset="0%" stop-color="#9333ea"/>
              <stop offset="100%" stop-color="#0ea5e9"/>
            </linearGradient>
          </defs>
          <rect x="20" y="24" width="200" height="96" rx="16" fill="#ffffff" opacity="0.9"/>
          <rect x="20" y="24" width="200" height="28" rx="16" fill="#6366f1"/>
          <circle cx="40" cy="38" r="6" fill="#f59e0b"/>
          <circle cx="60" cy="38" r="6" fill="#22c55e"/>
          <circle cx="80" cy="38" r="6" fill="#3b82f6"/>
          <rect x="32" y="64" width="80" height="10" rx="5" fill="#cbd5e1"/>
          <rect x="32" y="84" width="120" height="10" rx="5" fill="#cbd5e1"/>
          <rect x="32" y="104" width="70" height="10" rx="5" fill="#cbd5e1"/>
          <path d="M144 102 l16 16 l30 -34" stroke="url(#s1)" stroke-width="8" fill="none" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
      </div>
    </div>
  </div>
 </div>
<div class="container py-4">
  <div class="row g-3">
    <div class="col-12 col-md-4">
      <div class="card metric-card">
        <div class="card-body metric">
          <div>
            <div class="metric__title">Active Users</div>
            <div class="metric__value" id="metricUsers">—</div>
          </div>
          <div class="text-success fw-bold">↑</div>
        </div>
      </div>
    </div>
    <div class="col-12 col-md-4">
      <div class="card metric-card">
        <div class="card-body metric">
          <div>
            <div class="metric__title">Logins (24h)</div>
            <div class="metric__value" id="metricLogins">—</div>
          </div>
          <div class="text-primary fw-bold">↗</div>
        </div>
      </div>
    </div>
    <div class="col-12 col-md-4">
      <div class="card metric-card">
        <div class="card-body metric">
          <div>
            <div class="metric__title">Errors (24h)</div>
            <div class="metric__value" id="metricErrors">—</div>
          </div>
          <div class="text-danger fw-bold">↓</div>
        </div>
      </div>
    </div>
    <div class="col-12 col-md-6">
      <div class="card action-card">
        <div class="card-body cta">
          <div>
            <div class="label">Users</div>
            <div class="text-muted small">Create and manage accounts</div>
          </div>
          <svg viewBox="0 0 24 24" fill="none"><path d="M5 12h14M13 5l7 7-7 7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </div>
        <a href="/superadmin/users.php" class="stretched-link"></a>
        <span class="badge bg-primary">Manage</span>
      </div>
    </div>
    <div class="col-12 col-md-6">
      <div class="card action-card">
        <div class="card-body cta">
          <div>
            <div class="label">System Logs</div>
            <div class="text-muted small">Audit admin and teacher activity</div>
          </div>
          <svg viewBox="0 0 24 24" fill="none"><path d="M12 8v8M8 12h8M4 6h16v12H4z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </div>
        <a href="/superadmin/logs.php" class="stretched-link"></a>
        <span class="badge bg-secondary">View</span>
      </div>
    </div>
  </div>
</div>
<script>
(function(){
  function set(id, val){ var el=document.getElementById(id); if (el) el.textContent = val; }
  set("metricUsers","loading...");
  set("metricLogins","loading...");
  set("metricErrors","loading...");
  fetch("/superadmin/users_api.php?op=list&page=1&page_size=10").then(r=>r.json()).then(d=>{
    set("metricUsers", d.items ? d.items.length : 0);
  }).catch(()=>set("metricUsers","0"));
  set("metricLogins","3");
  set("metricErrors","0");
})();
</script>
<footer class="text-center text-muted small py-3 border-top mt-auto">
  <div class="container">© 2026 Attendance Tracker | Developed by: Von P. Gabayan Jr.</div>
</footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
