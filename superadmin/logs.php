<?php
require_once __DIR__ . "/../lib/admin.php";
require_once __DIR__ . "/../lib/roles.php";
require_once __DIR__ . "/../lib/csrf.php";
require_auth_at_most(ROLE_SUPERADMIN, "/admin/login.php");
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
if (get_auth_id() !== ROLE_SUPERADMIN) { http_redirect("/admin/dashboard.php"); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>System Logs</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
.table-wrap { position: relative; min-height: 240px; }
.loading { position: absolute; inset: 0; display: none; align-items: center; justify-content: center; background: rgba(15,23,42,0.06); }
.loading.show { display: flex; }
.spinner { width: 36px; height: 36px; border-radius: 50%; border: 4px solid #e2e8f0; border-top-color: #6366f1; animation: spin 1s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }
.highlight { background: #fde68a; }
.sa-hero { position: relative; padding: 32px 0; background: linear-gradient(120deg, #9333ea 0%, #0ea5e9 45%, #0f172a 100%); color: #ffffff; border-bottom: 1px solid rgba(255,255,255,0.18); }
.sa-hero__inner { display: flex; align-items: center; justify-content: space-between; gap: 16px; }
.sa-hero__title { font-size: 24px; font-weight: 700; letter-spacing: 0.2px; }
.sa-hero__subtitle { font-size: 14px; opacity: 0.9; }
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
        <li class="nav-item"><a class="nav-link active" aria-current="page" href="/superadmin/logs.php">Logs</a></li>
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
        <div class="sa-hero__title">System Logs</div>
        <div class="sa-hero__subtitle">Audit admin and teacher activities</div>
      </div>
      <div class="d-flex gap-2">
        <select id="refresh" class="form-select form-select-sm" style="max-width:160px" aria-label="Auto refresh">
          <option value="0">Manual</option>
          <option value="5">Every 5s</option>
          <option value="10">Every 10s</option>
          <option value="30">Every 30s</option>
        </select>
        <button class="btn btn-light btn-sm" id="btnRefresh">Refresh</button>
      </div>
    </div>
  </div>
</div>
<div class="container py-4">
  <div class="card mb-3"><div class="card-body">
    <div class="row g-2">
      <div class="col-12 col-md-3"><input id="search" class="form-control" placeholder="Search or /regex/" aria-label="Search"></div>
      <div class="col-6 col-md-2"><input id="start" type="date" class="form-control" aria-label="Start date"></div>
      <div class="col-6 col-md-2"><input id="end" type="date" class="form-control" aria-label="End date"></div>
      <div class="col-6 col-md-2">
        <select id="severity" class="form-select" aria-label="Severity">
          <option value="">All Severity</option>
          <option>INFO</option><option>WARNING</option><option>ERROR</option><option>CRITICAL</option>
        </select>
      </div>
      <div class="col-6 col-md-1">
        <select id="role" class="form-select" aria-label="Role">
          <option value="">All Roles</option>
          <option value="0">Superadmin</option><option value="1">Admin</option><option value="2">Teacher</option>
        </select>
      </div>
      <div class="col-6 col-md-2"><input id="action" class="form-control" placeholder="Action type" aria-label="Action"></div>
      <div class="col-6 col-md-2"><input id="ip" class="form-control" placeholder="IP address" aria-label="IP"></div>
      <div class="col-12 col-md-2">
        <select id="exportType" class="form-select" aria-label="Export type">
          <option value="csv">CSV</option><option value="json">JSON</option><option value="pdf">PDF</option>
        </select>
      </div>
      <div class="col-12 col-md-2">
        <button class="btn btn-primary w-100" id="btnExport">Export</button>
      </div>
      <div class="col-12 col-md-3">
        <div class="input-group">
          <span class="input-group-text">Retention (days)</span>
          <input id="retention" type="number" class="form-control" min="1" value="90">
          <button class="btn btn-outline-secondary" id="btnRetention">Save</button>
        </div>
      </div>
    </div>
  </div></div>
  <div class="table-wrap">
    <div class="loading" id="loading"><div class="spinner" role="status" aria-label="Loading"></div></div>
    <div class="table-responsive">
      <table class="table table-sm align-middle" id="logsTable">
        <thead><tr>
          <th>ID</th><th>Time (UTC)</th><th>User</th><th>Role</th><th>Action</th><th>Resource</th><th>IP</th><th>User Agent</th><th>Session</th><th>Status</th><th>Severity</th>
        </tr></thead>
        <tbody></tbody>
      </table>
    </div>
  </div>
  <div class="d-flex justify-content-between align-items-center mt-2">
    <div id="statusMsg" class="text-muted small" role="status"></div>
    <div class="d-flex gap-2">
      <button class="btn btn-outline-secondary btn-sm" id="prev">Prev</button>
      <span id="pageInfo" class="small text-muted"></span>
      <button class="btn btn-outline-secondary btn-sm" id="next">Next</button>
    </div>
  </div>
</div>
<script>
(function(){
  const qs = s => document.querySelector(s);
  const qsa = s => Array.from(document.querySelectorAll(s));
  const state = { page:1, size:100, last_id:0, interval:0 };
  const loading = qs("#loading");
  function setLoading(b){ loading.classList.toggle("show", !!b); }
  function roleText(r){ return r==0?"Superadmin":r==1?"Admin":"Teacher"; }
  function highlight(text, query, regex){
    if (!query) return text;
    try {
      if (regex) {
        const re = new RegExp(query, "gi");
        return text.replace(re, m => `<span class="highlight">${m}</span>`);
      } else {
        const esc = query.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        const re = new RegExp(esc, "gi");
        return text.replace(re, m => `<span class="highlight">${m}</span>`);
      }
    } catch(e){ return text; }
  }
  async function load(){
    setLoading(true);
    const p = new URLSearchParams({ op:"list", page:state.page, page_size:state.size });
    ["start","end","severity","role","action","ip"].forEach(k => { const v = qs("#"+k).value.trim(); if (v) p.set(k, v); });
    const s = qs("#search").value.trim();
    if (s) {
      const isRegex = s.startsWith("/") && s.endsWith("/") && s.length>2;
      p.set("search", isRegex? s.slice(1,-1) : s); p.set("regex", isRegex?"1":"0");
    }
    const r = await fetch("/superadmin/logs_api.php?"+p.toString()).then(x=>x.json()).catch(()=>({ok:false,error:"Network error"}));
    setLoading(false);
    if (!r.ok) { qs("#statusMsg").textContent = r.error || "Load failed"; return; }
    const tbody = qs("#logsTable tbody"); tbody.innerHTML = "";
    const regex = p.get("regex")==="1"; const q = p.get("search")||"";
    r.items.forEach(e => {
      const tr = document.createElement("tr");
      tr.innerHTML = `
        <td>${e.id||""}</td>
        <td>${e.timestamp||""}</td>
        <td>${highlight(e.actor||"", q, regex)}</td>
        <td>${roleText(e.user_role)}</td>
        <td>${highlight(e.action||"", q, regex)}</td>
        <td>${highlight(e.resource||"", q, regex)}</td>
        <td>${e.ip||""}</td>
        <td>${(e.user_agent||"").slice(0,56)}</td>
        <td>${e.session_id||""}</td>
        <td>${e.response_status||""}</td>
        <td>${e.severity||""}</td>`;
      tbody.appendChild(tr);
    });
    qs("#pageInfo").textContent = `Page ${r.page} · ${r.items.length} items`;
    qs("#statusMsg").textContent = `Total ${r.total}`;
  }
  function schedule(){
    if (state.timer) clearInterval(state.timer);
    const v = parseInt(qs("#refresh").value,10);
    state.interval = v;
    if (v>0) { state.timer = setInterval(load, v*1000); }
  }
  load();
  ["search","start","end","severity","role","action","ip"].forEach(id => qs("#"+id).addEventListener("input", function(){ state.page=1; load(); }));
  qs("#prev").addEventListener("click", function(){ if (state.page>1){ state.page--; load(); } });
  qs("#next").addEventListener("click", function(){ state.page++; load(); });
  qs("#btnRefresh").addEventListener("click", load);
  qs("#refresh").addEventListener("change", schedule);
  schedule();
  qs("#btnExport").addEventListener("click", function(){
    const type = qs("#exportType").value;
    const p = new URLSearchParams({ op:"export", type });
    const s = qs("#search").value.trim();
    if (s) {
      const isRegex = s.startsWith("/") && s.endsWith("/") && s.length>2;
      p.set("search", isRegex? s.slice(1,-1) : s); p.set("regex", isRegex?"1":"0");
    }
    ["start","end","severity","role","action","ip"].forEach(k => { const v = qs("#"+k).value.trim(); if (v) p.set(k, v); });
    window.open("/superadmin/logs_api.php?"+p.toString(), "_blank");
  });
  qs("#btnRetention").addEventListener("click", async function(){
    const fd = new FormData(); fd.append("op","set_retention"); fd.append("days", qs("#retention").value);
    const r = await fetch("/superadmin/logs_api.php", { method:"POST", body:fd }).then(x=>x.json()).catch(()=>({ok:false}));
    qs("#statusMsg").textContent = r.ok ? `Retention set to ${r.days} days` : "Retention update failed";
    load();
  });
})();
</script>
<footer class="text-center text-muted small py-3 border-top mt-auto">
  <div class="container">© 2026 Attendance Tracker | Developed by: Von P. Gabayan Jr.</div>
</footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
