<?php
require_once __DIR__ . "/../lib/admin.php";
require_once __DIR__ . "/../lib/csrf.php";
require_admin_session();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Account Authentication</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="d-flex flex-column min-vh-100">
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
  <div class="container-fluid">
    <a class="navbar-brand" href="/admin/dashboard">Admin</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#adminTopNav" aria-controls="adminTopNav" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="adminTopNav">
      <ul class="navbar-nav me-auto mb-2 mb-lg-0">
        <?php $rid = get_auth_id(); ?>
        <?php if ($rid !== null && $rid === ROLE_SUPERADMIN): ?>
          <li class="nav-item"><a class="nav-link" href="/superadmin/users">Users</a></li>
          <li class="nav-item"><a class="nav-link" href="/superadmin/logs">Logs</a></li>
        <?php endif; ?>
        <li class="nav-item"><a class="nav-link" href="/admin/teacher">Teachers</a></li>
        <li class="nav-item"><a class="nav-link" href="/admin/school-year">School Year</a></li>
        <li class="nav-item"><a class="nav-link" href="/admin/profile">Profile</a></li>
      </ul>
      <div class="d-flex">
        <a class="btn btn-outline-light btn-sm" href="/admin/logout">Logout</a>
      </div>
    </div>
  </div>
</nav>
<div class="container py-4">
  <h4 class="mb-3">ACCOUNT AUTHENTICATION</h4>
  <div class="row g-3">
    <div class="col-12 col-lg-4">
      <div class="card"><div class="card-body">
        <h6 class="mb-3">Change Password</h6>
        <form id="pwForm">
          <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
          <div class="mb-2"><label class="form-label">User ID</label><input name="id" type="number" class="form-control" required></div>
          <div class="mb-2"><label class="form-label">New Password</label><input name="password" type="password" class="form-control" minlength="6" required></div>
          <button class="btn btn-primary">Change</button>
        </form>
      </div></div>
      <div class="card mt-3"><div class="card-body">
        <h6 class="mb-3">Two-Factor Authentication</h6>
        <form id="setup2faForm">
          <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
          <div class="mb-2"><label class="form-label">User ID</label><input name="id" type="number" class="form-control" required></div>
          <button class="btn btn-outline-primary">Enable & Generate Secret</button>
        </form>
        <form id="disable2faForm" class="mt-2">
          <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
          <div class="mb-2"><label class="form-label">User ID</label><input name="id" type="number" class="form-control" required></div>
          <button class="btn btn-outline-danger">Disable 2FA</button>
        </form>
        <div id="secretBox" class="alert alert-warning mt-2 d-none"></div>
      </div></div>
    </div>
    <div class="col-12 col-lg-8">
      <div class="card"><div class="card-body">
        <h6 class="mb-3">Login History</h6>
        <div class="d-flex gap-2 mb-2">
          <input id="histUserId" type="number" class="form-control" placeholder="User ID" aria-label="User ID">
          <button class="btn btn-outline-secondary btn-sm" id="loadHistory">Load</button>
        </div>
        <div class="table-responsive">
          <table class="table table-sm align-middle">
            <thead><tr><th>When</th><th>IP</th><th>User Agent</th><th>Result</th><th>Reason</th></tr></thead>
            <tbody id="historyBody"></tbody>
          </table>
        </div>
      </div></div>
      <div class="alert alert-info mt-3">
        Security Settings & Policies:
        <ul class="mb-0">
          <li>Minimum password length: 6 characters</li>
          <li>Role-based access enforced (Superadmin, Admin, Teacher)</li>
          <li>Write operations require server-side Supabase service role</li>
          <li>CSRF tokens required for all modifying requests</li>
          <li>Account lockout and brute force protection can be configured via users table (failed_login_count, locked_until)</li>
        </ul>
      </div>
    </div>
  </div>
  <div id="statusMsg" class="text-muted small mt-3" role="status"></div>
</div>
<script>
(function(){
  const qs = (s, p=document)=>p.querySelector(s);
  const apiPost = (op, fd) => { fd.append("op",op); return fetch("/admin/auth_api.php", { method:"POST", body:fd }).then(r=>r.json()); };
  qs("#pwForm").addEventListener("submit", async function(e){ e.preventDefault(); const fd = new FormData(this); const r = await apiPost("change_password", fd); qs("#statusMsg").textContent = r.ok?"Password changed":(r.error||"Failed"); });
  qs("#setup2faForm").addEventListener("submit", async function(e){ e.preventDefault(); const fd = new FormData(this); const r = await apiPost("setup_2fa", fd); qs("#statusMsg").textContent = r.ok?"2FA enabled":"Failed"; const box = qs("#secretBox"); if (r.ok && r.secret){ box.classList.remove("d-none"); box.textContent = "2FA Secret: " + r.secret; } });
  qs("#disable2faForm").addEventListener("submit", async function(e){ e.preventDefault(); const fd = new FormData(this); const r = await apiPost("disable_2fa", fd); qs("#statusMsg").textContent = r.ok?"2FA disabled":(r.error||"Failed"); });
  qs("#loadHistory").addEventListener("click", async function(){ const id = qs("#histUserId").value; if (!id) return; const r = await fetch("/admin/auth_api.php?op=login_history&id="+encodeURIComponent(id)).then(x=>x.json()); const body = qs("#historyBody"); body.innerHTML=""; (r.items||[]).forEach(i => { const tr = document.createElement("tr"); tr.innerHTML = "<td>"+(i.at||"")+"</td><td>"+(i.ip||"")+"</td><td>"+(i.user_agent||"")+"</td><td>"+(i.success?"OK":"Fail")+"</td><td>"+(i.reason||"")+"</td>"; body.appendChild(tr); }); });
})();
</script>
<footer class="text-center text-muted small py-3 border-top mt-auto">
  <div class="container">© 2026 Attendance Tracker | Developed by: Von P. Gabayan Jr.</div>
</footer>
</body>
</html>
