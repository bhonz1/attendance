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
<title>Superadmin · User Management</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
.table-wrap { position: relative; }
.loading { position: absolute; inset: 0; display: none; align-items: center; justify-content: center; background: rgba(15,23,42,0.08); }
.loading.show { display: flex; }
.spinner { width: 36px; height: 36px; border-radius: 50%; border: 4px solid #e2e8f0; border-top-color: #6366f1; animation: spin 1s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }
.sa-hero { position: relative; padding: 32px 0; background: linear-gradient(120deg, #0ea5e9 0%, #6366f1 45%, #0f172a 100%); color: #ffffff; border-bottom: 1px solid rgba(255,255,255,0.18); }
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
        <li class="nav-item"><a class="nav-link" href="/superadmin/logs.php">Logs</a></li>
        <li class="nav-item"><a class="nav-link active" aria-current="page" href="/superadmin/users.php">Users</a></li>
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
        <div class="sa-hero__title">User Management</div>
        <div class="sa-hero__subtitle">Create, edit, and manage accounts</div>
      </div>
      <div class="d-flex gap-2">
        <button class="btn btn-light btn-sm" id="openAddUser" aria-haspopup="dialog" aria-controls="addUserModal">Add User</button>
      </div>
    </div>
  </div>
</div>
<div class="container py-4">
  <div class="row g-3">
    <div class="col-12">
      <div class="card"><div class="card-body">
        <div class="mb-2"></div>
        <div class="table-wrap">
          <div class="loading" id="loading"><div class="spinner" role="status" aria-label="Loading"></div></div>
          <div class="table-responsive">
            <table class="table table-sm align-middle" id="usersTable">
              <thead><tr>
                <th style="width:36px;"><input type="checkbox" id="selectAll" aria-label="Select all"></th>
                <th>Full Name</th><th>Username</th><th>Role</th><th>Status</th><th>Last Login</th><th style="width:160px">Actions</th>
              </tr></thead>
              <tbody></tbody>
            </table>
          </div>
        </div>
        <div class="d-flex gap-2 align-items-center mt-2">
          <select id="bulkRole" class="form-select" style="max-width:200px" aria-label="Assign role">
            <option value="">Assign Role...</option>
            <option value="1">Admin</option>
            <option value="0">Superadmin</option>
          </select>
          <button class="btn btn-outline-primary btn-sm" id="applyBulkRole">Apply</button>
        </div>
        <div class="d-flex justify-content-between align-items-center mt-2">
          <div id="statusMsg" class="text-muted small" role="status"></div>
          <div class="d-flex gap-2">
            <button class="btn btn-outline-secondary btn-sm" id="prevPage">Prev</button>
            <span id="pageInfo" class="small text-muted"></span>
            <button class="btn btn-outline-secondary btn-sm" id="nextPage">Next</button>
          </div>
        </div>
      </div></div>
    </div>
  </div>
</div>
<div class="modal" id="addUserModal" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="addUserTitle" style="display:none;">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="addUserTitle">Add User</h5>
        <button type="button" class="btn-close" aria-label="Close" id="closeAddUser"></button>
      </div>
      <div class="modal-body">
        <form id="createForm">
          <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
          <div class="mb-2"><label class="form-label">Full Name</label><input name="full_name" class="form-control" required></div>
          <div class="mb-2"><label class="form-label">Username</label><input name="username" class="form-control" required></div>
          <div class="mb-2"><label class="form-label">Password</label><input name="password" type="password" class="form-control" required minlength="6"></div>
          <div class="mb-2"><label class="form-label">Role</label>
            <select name="role_id" class="form-select">
              <option value="1">Admin</option>
              <option value="0">Superadmin</option>
            </select>
          </div>
          <div class="mb-2"><label class="form-label">Status</label>
            <select name="status" class="form-select">
              <option value="active">Active</option>
              <option value="inactive">Inactive</option>
              <option value="suspended">Suspended</option>
            </select>
          </div>
          <button class="btn btn-primary">Create</button>
        </form>
      </div>
    </div>
  </div>
</div>
<div class="modal" id="editUserModal" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="editUserTitle" style="display:none;">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="editUserTitle">Edit User</h5>
        <button type="button" class="btn-close" aria-label="Close" id="closeEditUser"></button>
      </div>
      <div class="modal-body">
        <form id="editForm">
          <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
          <input type="hidden" name="id">
          <div class="mb-2"><label class="form-label">Full Name</label><input name="full_name" class="form-control" required></div>
          <div class="mb-2"><label class="form-label">Username</label><input name="username" class="form-control" required></div>
          <div class="mb-2"><label class="form-label">Role</label>
            <select name="role_id" class="form-select">
              <option value="1">Admin</option>
              <option value="0">Superadmin</option>
            </select>
          </div>
          <div class="mb-2"><label class="form-label">Status</label>
            <select name="status" class="form-select">
              <option value="active">Active</option>
              <option value="inactive">Inactive</option>
              <option value="suspended">Suspended</option>
            </select>
          </div>
          <button class="btn btn-primary">Save Changes</button>
        </form>
      </div>
    </div>
  </div>
</div>
<div class="modal" id="deleteUserModal" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="deleteUserTitle" style="display:none;">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="deleteUserTitle">Delete User</h5>
        <button type="button" class="btn-close" aria-label="Close" id="closeDeleteUser"></button>
      </div>
      <div class="modal-body">
        <form id="deleteForm">
          <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
          <input type="hidden" name="id">
          <input type="hidden" name="captcha_token">
          <p class="mb-2">Are you sure you want to delete this user?</p>
          <div class="mb-2"><label class="form-label" id="captchaPrompt">Verification</label><input name="captcha_answer" class="form-control" placeholder="Enter answer" required></div>
          <button class="btn btn-danger">Delete</button>
        </form>
      </div>
    </div>
  </div>
</div>
<script>
(function(){
  const qs = (s, p=document) => p.querySelector(s);
  const qsa = (s, p=document) => Array.from(p.querySelectorAll(s));
  const api = (form) => {
    const fd = new FormData(form);
    return fetch("/superadmin/users_api.php", { method: "POST", body: fd }).then(r => r.json());
  };
  const AUTH_ROLE = <?= json_encode(get_auth_id()) ?>;
  const ROLE_SUPERADMIN = 0, ROLE_ADMIN = 1, ROLE_TEACHER = 2;
  const state = { page: 1 };
  const loading = qs("#loading");
  function setLoading(b){ loading.classList.toggle("show", !!b); }
  function roleText(id){ return id==0?"Superadmin":id==1?"Admin":"Teacher"; }
  function roleBadge(id){ const cls=id==0?"bg-primary":id==1?"bg-info":"bg-success"; return '<span class="badge '+cls+'">'+roleText(id)+'</span>'; }
  function statusBadge(s){ const map={active:"success",inactive:"secondary",suspended:"warning"}; return '<span class="badge bg-'+(map[s]||"secondary")+'">'+s+'</span>'; }
  function esc(s){ return String(s||"").replace(/[&<>"']/g,function(c){return({"&":"&amp;","<":"&lt;",">":"&gt;","\"":"&quot;","'":"&#39;"})[c]}); }
  async function load(){
    setLoading(true);
    const params = new URLSearchParams({ op:"list", page:state.page });
    const resp = await fetch("/superadmin/users_api.php?"+params.toString()).then(r=>r.json()).catch(()=>({ok:false,error:"Network error"}));
    setLoading(false);
    if (!resp.ok) { qs("#statusMsg").textContent = resp.error || "Load failed"; return; }
    const tbody = qs("#usersTable tbody");
    tbody.innerHTML = "";
    resp.items.forEach(u => {
      const tr = document.createElement("tr");
      tr.innerHTML = `
        <td><input type="checkbox" class="sel" value="${u.id}"></td>
        <td class="full_name text-break">${esc(u.full_name||"")}</td>
        <td class="username text-break">${esc(u.username||"")}</td>
        <td>
          <div class="d-flex align-items-center gap-2"><span class="role-badge">${roleBadge(u.role_id)}</span></div>
        </td>
        <td>
          <div class="status-badge">${statusBadge(u.status)}</div>
        </td>
        <td>${u.last_login ? new Date(u.last_login).toLocaleString() : "—"}</td>
        <td class="d-flex gap-2">
          <button class="btn btn-outline-primary btn-sm edit" aria-label="Edit" title="Edit">Edit</button>
          <button class="btn btn-outline-danger btn-sm del" aria-label="Delete" title="Delete">Delete</button>
        </td>`;
      tbody.appendChild(tr);
      tr.querySelector(".edit").addEventListener("click", function(){
        const m = qs("#editUserModal");
        const f = qs("#editForm");
        f.reset();
        f.querySelector("[name=id]").value = u.id;
        f.querySelector("[name=full_name]").value = u.full_name || "";
        f.querySelector("[name=username]").value = u.username || "";
        f.querySelector("[name=role_id]").value = String(u.role_id);
        f.querySelector("[name=status]").value = String(u.status || "active");
        m.style.display="block"; m.setAttribute("aria-hidden","false");
      });
      tr.querySelector(".del").addEventListener("click", async function(){
        const m = qs("#deleteUserModal");
        const f = qs("#deleteForm");
        f.reset();
        f.querySelector("[name=id]").value = u.id;
        const cap = await fetch("/superadmin/users_api.php?op=captcha").then(x=>x.json()).catch(()=>null);
        if (cap && cap.ok) {
          qs("#captchaPrompt").textContent = cap.prompt;
          f.querySelector("[name=captcha_token]").value = cap.token;
        } else {
          qs("#captchaPrompt").textContent = "Verification";
          f.querySelector("[name=captcha_token]").value = "";
        }
        m.style.display="block"; m.setAttribute("aria-hidden","false");
      });
    });
    qs("#pageInfo").textContent = "Page " + resp.page + " · " + resp.items.length + " items";
    qs("#prevPage").disabled = (resp.page <= 1);
    qs("#nextPage").disabled = (resp.items.length < resp.page_size);
    qs("#selectAll").checked = false;
  }
  load();
  // filters removed
  qs("#prevPage").addEventListener("click", function(){ if (state.page > 1) { state.page--; load(); } });
  qs("#nextPage").addEventListener("click", function(){ state.page++; load(); });
  qs("#selectAll").addEventListener("change", function(){ qsa(".sel").forEach(cb => cb.checked = qs("#selectAll").checked); });
  async function bulk(status){
    const ids = qsa(".sel").filter(cb => cb.checked).map(cb => cb.value);
    if (ids.length === 0) { alert("Select users"); return; }
    const fd = new FormData();
    fd.append("op","bulk_status"); fd.append("csrf","<?= htmlspecialchars(csrf_token()) ?>");
    ids.forEach(id => fd.append("ids[]", id));
    fd.append("status", status);
    const r = await fetch("/superadmin/users_api.php", { method:"POST", body:fd }).then(x=>x.json());
    qs("#statusMsg").textContent = r.ok ? "Updated" : (r.error || "Bulk update failed");
    if (r.ok) load();
  }
  if (AUTH_ROLE !== ROLE_SUPERADMIN) {
    qs("#bulkRole").disabled = true; qs("#applyBulkRole").disabled = true;
  } else {
    qs("#applyBulkRole").addEventListener("click", async function(){
      const role = qs("#bulkRole").value;
      if (role === "") { alert("Select a role"); return; }
      const ids = qsa(".sel").filter(cb => cb.checked).map(cb => cb.value);
      if (ids.length === 0) { alert("Select users"); return; }
      const fd = new FormData(); fd.append("op","assign_role"); fd.append("csrf","<?= htmlspecialchars(csrf_token()) ?>");
      ids.forEach(id => fd.append("ids[]", id));
      fd.append("role_id", role);
      const r = await fetch("/superadmin/users_api.php", { method:"POST", body:fd }).then(x=>x.json());
      qs("#statusMsg").textContent = r.ok ? "Roles updated" : (r.error || "Assign role failed");
      if (r.ok) load();
    });
  }
  function openModal(){ const m=qs("#addUserModal"); m.style.display="block"; m.setAttribute("aria-hidden","false"); qs("#createForm").querySelector("[name=username]").focus(); }
  function closeModal(){ const m=qs("#addUserModal"); m.style.display="none"; m.setAttribute("aria-hidden","true"); }
  qs("#openAddUser").addEventListener("click", openModal);
  qs("#closeAddUser").addEventListener("click", closeModal);
  document.addEventListener("keydown", function(e){ if (e.key==="Escape") closeModal(); });
  qs("#createForm").addEventListener("submit", async function(e){
    e.preventDefault();
    const fd = new FormData(this); fd.append("op","create");
    const r = await fetch("/superadmin/users_api.php", { method:"POST", body:fd }).then(x=>x.json());
    qs("#statusMsg").textContent = r.ok ? "User created" : (r.error || "Create failed");
    if (r.ok) { this.reset(); closeModal(); load(); }
  });
  function openEditModal(){ const m=qs("#editUserModal"); m.style.display="block"; m.setAttribute("aria-hidden","false"); }
  function closeEditModal(){ const m=qs("#editUserModal"); m.style.display="none"; m.setAttribute("aria-hidden","true"); }
  qs("#closeEditUser").addEventListener("click", closeEditModal);
  document.addEventListener("keydown", function(e){ if (e.key==="Escape") closeEditModal(); });
  qs("#editForm").addEventListener("submit", async function(e){
    e.preventDefault();
    const fd = new FormData(this); fd.append("op","update");
    const r = await fetch("/superadmin/users_api.php", { method:"POST", body:fd }).then(x=>x.json());
    qs("#statusMsg").textContent = r.ok ? "Saved" : (r.error || "Save failed");
    if (r.ok) { this.reset(); closeEditModal(); load(); }
  });
  function closeDeleteModal(){ const m=qs("#deleteUserModal"); m.style.display="none"; m.setAttribute("aria-hidden","true"); }
  qs("#closeDeleteUser").addEventListener("click", closeDeleteModal);
  qs("#deleteForm").addEventListener("submit", async function(e){
    e.preventDefault();
    const fd = new FormData(this); fd.append("op","delete");
    const r = await fetch("/superadmin/users_api.php", { method:"POST", body:fd }).then(x=>x.json());
    qs("#statusMsg").textContent = r.ok ? "Deleted" : (r.error || "Delete failed");
    if (r.ok) { this.reset(); closeDeleteModal(); load(); }
  });
})();
</script>
<footer class="text-center text-muted small py-3 border-top mt-auto">
  <div class="container">© 2026 Attendance Tracker | Developed by: Von P. Gabayan Jr.</div>
</footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
