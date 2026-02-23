<?php
require_once __DIR__ . "/lib/env.php";
env_load();
require_once __DIR__ . "/lib/roles.php";
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="description" content="Attendance Tracker home with quick access to marking attendance, reports, and management">
<meta name="keywords" content="attendance, tracker, teacher, admin, school, reports">
<meta name="robots" content="index,follow">
<title>Attendance Tracker</title>
<style>
html, body { height: 100%; }
body {
  margin: 0;
  font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, "Apple Color Emoji", "Segoe UI Emoji";
  background: radial-gradient(1200px 600px at 50% -200px, #eef3ff 20%, #e6ecff 40%, #dde6ff 60%, #d7e0ff 100%), linear-gradient(180deg, #f7faff 0%, #eef3ff 100%);
  color: #0f172a;
  -webkit-font-smoothing: antialiased; -moz-osx-font-smoothing: grayscale;
}
.container {
  min-height: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 32px;
}
.card {
  width: 100%;
  max-width: 980px;
  border-radius: 16px;
  background: #ffffff;
  box-shadow: 0 10px 30px rgba(2, 6, 23, 0.08), 0 4px 10px rgba(2, 6, 23, 0.06);
  overflow: hidden;
}
.topbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 12px 16px;
  border-bottom: 1px solid rgba(15,23,42,0.08);
}
.status {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  font-size: 13px;
  color: #475569;
}
.pill {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 10px;
  border-radius: 999px;
  background: #eef2ff;
  color: #3730a3;
  font-weight: 600;
}
.logout {
  text-decoration: none;
  font-weight: 600;
  color: #ef4444;
  border: 1px solid rgba(239,68,68,0.3);
  border-radius: 10px;
  padding: 6px 10px;
}
.logout:hover { background: rgba(239,68,68,0.08); }
.header {
  padding: 32px 32px 16px;
  text-align: center;
}
.brand {
  display: inline-block;
  font-weight: 800;
  font-size: 34px;
  letter-spacing: -0.02em;
  color: #0f172a;
}
.hero {
  display: grid;
  grid-template-columns: 1.15fr .85fr;
  align-items: center;
  gap: 18px;
  padding: 0 32px 16px;
}
.hero__text h1 {
  font-size: 30px;
  font-weight: 800;
  letter-spacing: -0.01em;
  margin: 0 0 6px;
}
.hero__text p { margin: 0; color: #475569; }
.hero__art {
  display: flex; align-items: center; justify-content: center;
}
.hero__svg {
  width: 220px; height: 160px;
  filter: drop-shadow(0 10px 30px rgba(2,6,23,0.08));
}
.subtitle {
  margin-top: 6px;
  font-size: 14px;
  color: #475569;
}
.content {
  padding: 24px 32px 32px;
}
.cta {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 16px;
}
.btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 18px 22px;
  border-radius: 12px;
  font-weight: 600;
  font-size: 18px;
  letter-spacing: 0.01em;
  text-decoration: none;
  transition: transform 220ms ease, box-shadow 220ms ease, filter 220ms ease;
  will-change: transform;
}
.btn:focus { outline: 3px solid #93c5fd66; outline-offset: 2px; }
.btn:hover { transform: translateY(-2px); }
.btn:active { transform: translateY(0); }
.btn-teacher {
  color: #0c111b;
  background: linear-gradient(135deg, #a5b4fc 0%, #818cf8 45%, #6366f1 100%);
  box-shadow: 0 10px 24px rgba(99, 102, 241, 0.28), 0 6px 12px rgba(99, 102, 241, 0.18);
}
.btn-teacher:hover {
  filter: brightness(1.03) saturate(1.05);
  box-shadow: 0 14px 30px rgba(99, 102, 241, 0.32), 0 8px 16px rgba(99, 102, 241, 0.22);
}
.btn-admin {
  color: #0c111b;
  background: linear-gradient(135deg, #34d399 0%, #22c55e 50%, #16a34a 100%);
  box-shadow: 0 10px 24px rgba(34, 197, 94, 0.28), 0 6px 12px rgba(34, 197, 94, 0.18);
}
.btn-admin:hover {
  filter: brightness(1.03) saturate(1.05);
  box-shadow: 0 14px 30px rgba(34, 197, 94, 0.32), 0 8px 16px rgba(34, 197, 94, 0.22);
}
.btn-superadmin {
  color: #0c111b;
  background: linear-gradient(135deg, #a78bfa 0%, #8b5cf6 50%, #7c3aed 100%);
  box-shadow: 0 10px 24px rgba(124, 58, 237, 0.28), 0 6px 12px rgba(124, 58, 237, 0.18);
}
.btn-superadmin:hover {
  filter: brightness(1.03) saturate(1.05);
  box-shadow: 0 14px 30px rgba(124, 58, 237, 0.32), 0 8px 16px rgba(124, 58, 237, 0.22);
}
.btn-mark {
  color: #0c111b;
  background: linear-gradient(135deg, #fcd34d 0%, #f59e0b 50%, #d97706 100%);
  box-shadow: 0 10px 24px rgba(245, 158, 11, 0.28), 0 6px 12px rgba(245, 158, 11, 0.18);
}
.btn-mark:hover {
  filter: brightness(1.03) saturate(1.05);
  box-shadow: 0 14px 30px rgba(245, 158, 11, 0.32), 0 8px 16px rgba(245, 158, 11, 0.22);
}
.btn-reports {
  color: #0c111b;
  background: linear-gradient(135deg, #93c5fd 0%, #60a5fa 50%, #3b82f6 100%);
  box-shadow: 0 10px 24px rgba(59, 130, 246, 0.28), 0 6px 12px rgba(59, 130, 246, 0.18);
}
.btn-reports:hover { filter: brightness(1.03) saturate(1.05); box-shadow: 0 14px 30px rgba(59,130,246,0.32), 0 8px 16px rgba(59,130,246,0.22); }
.btn-manage {
  color: #0c111b;
  background: linear-gradient(135deg, #cbd5e1 0%, #94a3b8 50%, #64748b 100%);
  box-shadow: 0 10px 24px rgba(100, 116, 139, 0.28), 0 6px 12px rgba(100, 116, 139, 0.18);
}
.btn-manage:hover { filter: brightness(1.03) saturate(1.05); box-shadow: 0 14px 30px rgba(100,116,139,0.32), 0 8px 16px rgba(100,116,139,0.22); }
.footer {
  padding: 16px 24px 28px;
  text-align: center;
  color: #64748b;
  font-size: 13px;
}
@media (max-width: 640px) {
  .card { border-radius: 12px; }
  .brand { font-size: 26px; }
  .hero { grid-template-columns: 1fr; }
  .cta { grid-template-columns: 1fr; }
  .btn { font-size: 16px; padding: 16px 20px; }
}
@media (prefers-reduced-motion: reduce) {
  .btn { transition: none; }
}
.loading {
  position: fixed; inset: 0; display: none; align-items: center; justify-content: center;
  background: rgba(15,23,42,0.18); backdrop-filter: blur(2px);
}
.loading.show { display: flex; }
.spinner {
  width: 48px; height: 48px; border-radius: 50%;
  border: 4px solid #e2e8f0; border-top-color: #6366f1; animation: spin 1s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }
</style>
</head>
<body>
<main class="container" role="main">
  <section class="card" aria-label="Application Entry">
    <div class="topbar" role="banner">
      <a class="brand" href="/" aria-label="Home">Attendance Tracker</a>
    </div>
    <div class="hero">
      <div class="hero__text">
        <h1>WELCOME TO ATTENDANCE TRACKER</h1>
        <p>Track, manage, and review attendance with clarity.</p>
      </div>
      <div class="hero__art" aria-hidden="true">
        <svg class="hero__svg" viewBox="0 0 200 140">
          <rect x="20" y="20" width="160" height="100" rx="12" fill="#ffffff" stroke="#94a3b8" />
          <rect x="20" y="20" width="160" height="28" rx="12" fill="#6366f1"/>
          <circle cx="40" cy="34" r="6" fill="#f59e0b"/>
          <circle cx="60" cy="34" r="6" fill="#22c55e"/>
          <circle cx="80" cy="34" r="6" fill="#3b82f6"/>
          <rect x="32" y="60" width="48" height="10" rx="5" fill="#cbd5e1"/>
          <rect x="32" y="80" width="68" height="10" rx="5" fill="#cbd5e1"/>
          <rect x="32" y="100" width="40" height="10" rx="5" fill="#cbd5e1"/>
          <path d="M120 95 l14 14 l26 -30" stroke="#22c55e" stroke-width="8" fill="none" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
      </div>
    </div>
    <div class="content">
      <div class="cta" role="navigation" aria-label="Primary navigation">
        <a class="btn btn-mark" data-loading href="/student.php" aria-label="Open Student Registration">Student</a>
        <a class="btn btn-teacher" data-loading href="/teacher/Login.php" aria-label="Open Teacher Portal">Teacher</a>
        <a class="btn btn-admin" data-loading href="/admin/login.php" aria-label="Open Admin Portal">Admin</a>
      </div>
    </div>
    <div class="footer">
      <span>© <?php echo date("Y"); ?> Attendance Tracker | Developed by: Von P. Gabayan Jr.</span>
    </div>
  </section>
</main>
<div class="loading" id="loadingOverlay" aria-hidden="true" aria-live="polite"><div class="spinner" role="status" aria-label="Loading"></div></div>
<script>
(function(){
  const overlay = document.getElementById("loadingOverlay");
  const links = document.querySelectorAll("[data-loading]");
  links.forEach(a => {
    a.addEventListener("click", function(){
      if (!overlay) return;
      overlay.classList.add("show");
      document.body.setAttribute("aria-busy","true");
    });
  });
  // no logout on index
})();
</script>
</body>
</html>
