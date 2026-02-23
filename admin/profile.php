<?php
require_once __DIR__ . "/../lib/admin.php";
require_admin_session();
require_once __DIR__ . "/../lib/roles.php";
require_once __DIR__ . "/../lib/supabase.php";
require_once __DIR__ . "/../lib/csrf.php";
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
$username = $_SESSION["admin_user"] ?? "";
$roleId = get_auth_id();
$rec = [
  "id" => $_SESSION["admin_user_id"] ?? null,
  "username" => $username ?: "—",
  "full_name" => $username ?: "—",
  "role_id" => $roleId,
  "status" => "—",
  "two_factor_enabled" => false,
  "created_at" => null,
  "last_login" => null,
];
if (sb_url() && $username) {
  $r = sb_get("users", ["select"=>"id,username,full_name,role_id,status,two_factor_enabled,created_at,last_login","username"=>"eq.".$username,"limit"=>1]);
  if (is_array($r) && isset($r[0])) $rec = $r[0];
} else {
  if (!isset($_SESSION["__users"])) $_SESSION["__users"] = [];
  foreach ($_SESSION["__users"] as $urow) { if (($urow["username"] ?? "") === $username) { $rec = $urow; break; } }
}
$roleText = ($rec["role_id"] === ROLE_SUPERADMIN ? "Superadmin" : ($rec["role_id"] === ROLE_ADMIN ? "Admin" : "User"));
$roleCls = ($rec["role_id"] === ROLE_SUPERADMIN ? "bg-primary" : ($rec["role_id"] === ROLE_ADMIN ? "bg-info" : "bg-secondary"));
$statusText = is_string($rec["status"] ?? "") ? $rec["status"] : "—";
$statusCls = ($statusText === "active" ? "bg-success" : ($statusText === "suspended" ? "bg-warning" : "bg-secondary"));
$name = is_string($rec["full_name"] ?? "") && $rec["full_name"] !== "" ? $rec["full_name"] : ($rec["username"] ?? "—");
$uid = $rec["id"] ?? null;
$created = isset($rec["created_at"]) && $rec["created_at"] ? date("M d, Y", strtotime($rec["created_at"])) : "—";
$lastLogin = isset($rec["last_login"]) && $rec["last_login"] ? date("M d, Y h:i A", strtotime($rec["last_login"])) : "—";
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Admin Profile</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
.profile-hero { position: relative; padding: 40px 0; background: linear-gradient(120deg, #0ea5e9 0%, #6366f1 40%, #0f172a 100%); color: #ffffff; border-bottom: 1px solid rgba(255,255,255,0.18); }
.profile-hero__inner { display: flex; align-items: center; justify-content: space-between; gap: 16px; }
.profile-hero__title { font-size: 28px; font-weight: 700; letter-spacing: 0.2px; }
.profile-hero__subtitle { font-size: 14px; opacity: 0.9; }
.profile-card .name { font-size: 22px; font-weight: 700; }
.profile-card .detail { display: grid; grid-template-columns: 160px 1fr; gap: 8px; }
.profile-card .detail .label { color: #64748b; font-size: 12px; text-transform: uppercase; letter-spacing: 0.6px; }
</style>
</head>
<body class="d-flex flex-column min-vh-100">
<?php $admin_nav_active = "profile"; include __DIR__ . "/admin_nav.php"; ?>
<div class="profile-hero">
  <div class="container">
    <div class="profile-hero__inner">
      <div>
        <div class="profile-hero__title">Account Profile</div>
        <div class="profile-hero__subtitle">Your account details and security</div>
      </div>
    </div>
  </div>
 </div>
<div class="container py-4">
  <div class="row g-3">
    <div class="col-12 col-lg-7">
      <div class="card profile-card">
        <div class="card-body">
          <div class="mb-3">
            <div class="name"><?= htmlspecialchars($name) ?></div>
            <div class="d-flex align-items-center gap-2">
              <span class="badge <?= htmlspecialchars($roleCls) ?>"><?= htmlspecialchars($roleText) ?></span>
              <span class="badge <?= htmlspecialchars($statusCls) ?>"><?= htmlspecialchars($statusText) ?></span>
            </div>
          </div>
          <div class="detail">
            <div class="label">User ID</div><div><?= $uid ? (int)$uid : "—" ?></div>
            <div class="label">Username</div><div><?= htmlspecialchars($rec["username"] ?? "—") ?></div>
            <div class="label">Role</div><div><?= htmlspecialchars($roleText) ?></div>
            <div class="label">Status</div><div><?= htmlspecialchars($statusText) ?></div>
            <div class="label">Created</div><div><?= htmlspecialchars($created) ?></div>
            <div class="label">Last Login</div><div><?= htmlspecialchars($lastLogin) ?></div>
            <div class="label">Password</div><div class="d-flex align-items-center gap-2"><span>Hidden</span><button type="button" class="btn btn-outline-primary btn-sm" id="btnChangePw">Change Password</button></div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<div class="modal fade" id="pwModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Change Password</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="mb-2">
          <label class="form-label">Old Password</label>
          <input type="password" id="oldPw" class="form-control" required>
        </div>
        <div class="mb-2">
          <label class="form-label">New Password</label>
          <input type="password" id="newPw" class="form-control" minlength="6" required>
        </div>
        <div class="mt-2">
          <label id="pwCaptchaPrompt" class="form-label"></label>
          <input type="text" id="pwCaptchaAnswer" class="form-control" placeholder="Enter answer">
          <input type="hidden" id="pwCaptchaToken">
        </div>
      </div>
      <div class="modal-footer">
        <input type="hidden" id="pwUserId" value="<?= $uid ? (int)$uid : 0 ?>">
        <input type="hidden" id="pwCsrf" value="<?= htmlspecialchars(csrf_token()) ?>">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" id="confirmPwChange" class="btn btn-primary">Change Password</button>
      </div>
    </div>
  </div>
</div>
<div class="container"><div id="statusMsg" class="text-muted small mt-2"></div></div>
<footer class="text-center text-muted small py-3 border-top mt-auto">
  <div class="container">© 2026 Attendance Tracker | Developed by: Von P. Gabayan Jr.</div>
</footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function(){
  const qs = (s, p=document)=>p.querySelector(s);
  const apiPost = (op, fd) => { fd.append("op",op); return fetch("/admin/auth_api.php", { method:"POST", body:fd }).then(r=>r.json()); };
  qs("#btnChangePw").addEventListener("click", async function(){
    const r = await fetch("/admin/auth_api.php?op=captcha").then(x=>x.json());
    if (r && r.ok) { qs("#pwCaptchaPrompt").textContent = r.prompt; qs("#pwCaptchaToken").value = r.token; }
    new bootstrap.Modal(qs("#pwModal")).show();
  });
  qs("#confirmPwChange").addEventListener("click", async function(){
    const fd = new FormData();
    fd.append("csrf", qs("#pwCsrf").value);
    fd.append("id", qs("#pwUserId").value);
    fd.append("old_password", qs("#oldPw").value);
    fd.append("password", qs("#newPw").value);
    fd.append("captcha_token", qs("#pwCaptchaToken").value);
    fd.append("captcha_answer", qs("#pwCaptchaAnswer").value);
    const res = await apiPost("change_password", fd);
    const msg = qs("#statusMsg");
    msg.textContent = res.ok ? "Password changed" : (res.error || "Failed");
    if (res.ok) {
      const m = qs("#pwModal");
      qs("#oldPw").value = "";
      qs("#newPw").value = "";
      qs("#pwCaptchaAnswer").value = "";
      qs("#pwCaptchaToken").value = "";
      qs("#pwCaptchaPrompt").textContent = "";
      bootstrap.Modal.getInstance(m).hide();
    }
  });
})();
</script>
</body>
</html>
