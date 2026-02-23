<?php
require_once __DIR__ . "/../lib/admin.php";
require_admin_session();
require_once __DIR__ . "/../lib/supabase.php";
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
$teachersCount = "—";
$subjectsCount = "—";
if (sb_url()) {
  $t = sb_get("teacher_registry", ["select" => "id"]);
  if (is_array($t)) $teachersCount = count($t);
  $sched = sb_get("class_schedule", ["select" => "id"]);
  if (!is_array($sched)) $sched = sb_get("class_schedules", ["select" => "id"]);
  if (!is_array($sched)) $sched = sb_get("schedules", ["select" => "id"]);
  if (is_array($sched)) $subjectsCount = count($sched);
} else {
  $tr = $_SESSION["__teacher_registry"] ?? [];
  if (is_array($tr)) $teachersCount = count($tr);
  $cs = $_SESSION["__class_schedules"] ?? [];
  if (is_array($cs)) $subjectsCount = count($cs);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Admin Dashboard</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
.dash-hero { position: relative; padding: 40px 0; background: linear-gradient(120deg, #0ea5e9 0%, #6366f1 40%, #0f172a 100%); color: #ffffff; border-bottom: 1px solid rgba(255,255,255,0.18); }
.dash-hero__inner { display: flex; align-items: center; justify-content: space-between; gap: 16px; }
.dash-hero__title { font-weight: 800; line-height: 1.1; font-size: clamp(28px, 6vw, 44px); letter-spacing: -0.01em; }
.dash-hero__subtitle { opacity: 0.92; font-size: 16px; }
.dash-hero__art { flex: 0 0 auto; width: 280px; height: 160px; opacity: 0.85; }
.metric-card { border: none; border-radius: 14px; box-shadow: 0 10px 24px rgba(2,6,23,0.08); }
.metric { display: flex; align-items: center; gap: 14px; }
.metric__icon { width: 48px; height: 48px; border-radius: 12px; display: inline-flex; align-items: center; justify-content: center; }
.metric__title { font-size: 13px; text-transform: uppercase; letter-spacing: 0.08em; color: #64748b; }
.metric__value { font-size: 28px; font-weight: 800; color: #0f172a; }
.quick-card { border: none; border-radius: 14px; box-shadow: 0 8px 20px rgba(2,6,23,0.06); transition: transform .18s ease, box-shadow .18s ease; }
.quick-card:hover { transform: translateY(-2px); box-shadow: 0 12px 28px rgba(2,6,23,0.12); }
.timeline { list-style: none; margin: 0; padding: 0; }
.timeline__item { display: flex; align-items: flex-start; gap: 12px; padding: 10px 0; border-bottom: 1px dashed #e2e8f0; }
.timeline__dot { width: 10px; height: 10px; border-radius: 50%; margin-top: 6px; }
.timeline__line { flex: 0 0 auto; width: 2px; background: #cbd5e1; display: none; }
.timeline__body { flex: 1; }
.badge-soft { background: #eef2ff; color: #3730a3; border-radius: 999px; padding: 4px 10px; font-size: 12px; }
.text-muted-soft { color: #64748b; }
</style>
</head>
<body class="d-flex flex-column min-vh-100">
<?php include __DIR__ . "/admin_nav.php"; ?>
<div class="dash-hero">
  <div class="container">
    <div class="dash-hero__inner">
      <div>
        <div class="dash-hero__title">Attendance Administration</div>
        <div class="dash-hero__subtitle">Modern oversight of teachers, subjects, and school years</div>
      </div>
      <div class="dash-hero__art" aria-hidden="true">
        <svg viewBox="0 0 260 160">
          <defs>
            <linearGradient id="g1" x1="0" y1="0" x2="1" y2="1">
              <stop offset="0%" stop-color="#22c55e"/>
              <stop offset="100%" stop-color="#0ea5e9"/>
            </linearGradient>
          </defs>
          <rect x="16" y="20" width="200" height="96" rx="16" fill="#ffffff" opacity="0.9"/>
          <rect x="16" y="20" width="200" height="28" rx="16" fill="#6366f1"/>
          <circle cx="36" cy="34" r="6" fill="#f59e0b"/>
          <circle cx="56" cy="34" r="6" fill="#22c55e"/>
          <circle cx="76" cy="34" r="6" fill="#3b82f6"/>
          <rect x="28" y="60" width="80" height="10" rx="5" fill="#cbd5e1"/>
          <rect x="28" y="80" width="120" height="10" rx="5" fill="#cbd5e1"/>
          <rect x="28" y="100" width="70" height="10" rx="5" fill="#cbd5e1"/>
          <path d="M140 98 l16 16 l30 -34" stroke="url(#g1)" stroke-width="8" fill="none" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
      </div>
    </div>
  </div>
</div>
<div class="container py-4">
  <div class="row g-3">
    <div class="col-12 col-md-3">
      <div class="card metric-card">
        <div class="card-body metric">
          <div class="metric__icon" style="background:#dbeafe"><svg width="24" height="24" viewBox="0 0 24 24"><path fill="#1d4ed8" d="M12 12a5 5 0 1 0-5-5 5 5 0 0 0 5 5Zm-9 9a9 9 0 0 1 18 0Z"/></svg></div>
          <div>
            <div class="metric__title">Teachers</div>
            <div class="metric__value"><?= htmlspecialchars($teachersCount) ?></div>
          </div>
        </div>
      </div>
    </div>
    <div class="col-12 col-md-3">
      <div class="card metric-card">
        <div class="card-body metric">
          <div class="metric__icon" style="background:#fef3c7"><svg width="24" height="24" viewBox="0 0 24 24"><path fill="#b45309" d="M4 6h16v2H4zm0 5h16v2H4zm0 5h16v2H4z"/></svg></div>
          <div>
            <div class="metric__title">Subjects</div>
            <div class="metric__value"><?= htmlspecialchars($subjectsCount) ?></div>
          </div>
        </div>
      </div>
    </div>
    <div class="col-12 col-md-6">
      <div class="card metric-card">
        <div class="card-body">
          <div class="d-flex align-items-center justify-content-between mb-2">
            <div class="metric__title">Realtime: Teacher Activities</div>
            <span class="badge-soft" id="rtStatus">offline</span>
          </div>
          <div style="max-height:220px; overflow:auto">
            <ul id="teacherActivityFeed" class="timeline mb-0"></ul>
          </div>
        </div>
      </div>
    </div>
  </div>
  
</div>
<script src="https://cdn.jsdelivr.net/npm/@supabase/supabase-js@2.44.2/dist/umd/supabase.min.js" crossorigin="anonymous"></script>
<script>
(function(){
  var url = "<?= htmlspecialchars(getenv('SUPABASE_URL') ?: '') ?>";
  var key = "<?= htmlspecialchars(getenv('SUPABASE_ANON_KEY') ?: (getenv('SUPABASE_PUBLISHABLE_KEY') ?: '')) ?>";
  var feed = document.getElementById("teacherActivityFeed");
  var statusEl = document.getElementById("rtStatus");
  function pushItem(e){
    if (!feed || !e) return;
    var li = document.createElement("li");
    li.className = "timeline__item";
    var dot = document.createElement("div");
    dot.className = "timeline__dot";
    dot.style.background = "#22c55e";
    var body = document.createElement("div");
    body.className = "timeline__body";
    var ts = document.createElement("div");
    ts.className = "text-muted-soft";
    ts.textContent = (e.timestamp || new Date().toISOString()).replace("T"," ").replace("Z","");
    var txt = document.createElement("div");
    var actor = e.actor || "";
    var action = e.action || "";
    var resource = e.resource || "";
    txt.textContent = (actor ? actor + " " : "") + (action ? action : "") + (resource ? " (" + resource + ")" : "");
    body.appendChild(ts);
    body.appendChild(txt);
    li.appendChild(dot);
    li.appendChild(body);
    feed.insertBefore(li, feed.firstChild);
    while (feed.children.length > 30) feed.removeChild(feed.lastChild);
  }
  function isTeacherEvent(e){
    var r = e && (e.user_role !== undefined ? String(e.user_role) : "");
    var a = e && (e.actor || "");
    if (r === "2") return true;
    if (a && a.toLowerCase().indexOf("teacher") >= 0) return true;
    return false;
  }
  if (url && key && typeof window.supabase !== "undefined") {
    var client = window.supabase.createClient(url, key);
    client.from("system_logs").select("id,timestamp,actor,user_role,action,resource").order("timestamp", { ascending: false }).limit(10).then(function(res){
      var items = (res && res.data) ? res.data : [];
      items.forEach(function(e){ if (isTeacherEvent(e)) pushItem(e); });
    });
    var channel = client.channel("realtime_admin_attendance_dashboard");
    channel.on("postgres_changes", { event: "*", schema: "public", table: "system_logs" }, function(payload){
      var e = payload.new || payload.old || {};
      if (isTeacherEvent(e)) pushItem(e);
    }).subscribe(function(status){
      if (statusEl) statusEl.textContent = "online";
    });
  }
})();
</script>
<footer class="text-center text-muted small py-3 border-top mt-auto">
  <div class="container">© 2026 Attendance Tracker | Developed by: Von P. Gabayan Jr.</div>
</footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
