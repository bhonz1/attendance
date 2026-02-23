<?php
require_once __DIR__ . "/../lib/auth.php";
require_once __DIR__ . "/../lib/supabase.php";
require_once __DIR__ . "/../lib/csrf.php";
require_teacher_session();
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
$name = $_SESSION["teacher_name"] ?? "Teacher";
$email = $_SESSION["teacher_email"] ?? "";
$uname = $_SESSION["teacher_username"] ?? "";
$tid = $_SESSION["teacher_id"] ?? null;
$useSb = sb_url() ? true : false;
$profile = ["full_name"=>$name, "username"=>$email, "college"=>"", "institution"=>"", "password_mask"=>"", "school_years"=>[], "photo_src"=>"", "code"=>"", "teacher_id"=>$tid];
if ($useSb && $tid) {
  $rows = sb_get("teacher_registry", ["select"=>"id,teacher_id,username,email,full_name,password_enc,department,college,institution,photo_url,code,teacher_code,short_code,school_year_ids", "id"=>"eq.".$tid, "limit"=>1]);
  if (!is_array($rows) || !isset($rows[0])) {
    $rows = sb_get("teacher_registry", ["select"=>"id,teacher_id,username,email,full_name,password_enc,department,college,institution,photo_url,code,teacher_code,short_code,school_year_ids", "teacher_id"=>"eq.".$tid, "limit"=>1]);
  }
  if ((!is_array($rows) || !isset($rows[0])) && $uname) {
    $rows = sb_get("teacher_registry", ["select"=>"id,teacher_id,username,email,full_name,password_enc,department,college,institution,photo_url,code,teacher_code,short_code,school_year_ids", "username"=>"eq.".$uname, "limit"=>1]);
  }
  if ((!is_array($rows) || !isset($rows[0])) && $email) {
    $rows = sb_get("teacher_registry", ["select"=>"id,teacher_id,username,email,full_name,password_enc,department,college,institution,photo_url,code,teacher_code,short_code,school_year_ids", "email"=>"eq.".$email, "limit"=>1]);
  }
  if (is_array($rows) && isset($rows[0])) {
    $rec = $rows[0];
    $profile["full_name"] = $rec["full_name"] ?? $profile["full_name"];
    $profile["username"] = $rec["username"] ?? ($rec["email"] ?? $profile["username"]);
    $profile["college"] = $rec["department"] ?? ($rec["college"] ?? "");
    $profile["institution"] = $rec["institution"] ?? "";
    $profile["teacher_id"] = $rec["teacher_id"] ?? ($rec["id"] ?? $profile["teacher_id"]);
    $code = $rec["code"] ?? ($rec["teacher_code"] ?? ($rec["short_code"] ?? ""));
    if (is_string($code) && strlen(trim($code))>0) { $profile["code"] = strtoupper(trim($code)); }
    if (!$profile["code"]) { $profile["code"] = strtoupper(initials($profile["full_name"])); }
    $cipher = $rec["password_enc"] ?? "";
    $profile["password_mask"] = is_string($cipher) && strlen($cipher) ? str_repeat("•", 10) : "";
    $purl = $rec["photo_url"] ?? "";
    if (is_string($purl) && strlen($purl)) {
      $p = trim($purl);
      if (strpos($p, "http://") === 0 || strpos($p, "https://") === 0 || strpos($p, "data:") === 0) {
        $profile["photo_src"] = $p;
      } else if (strpos($p, "/uploads/teachers/") === 0 || strpos($p, "uploads/teachers/") === 0) {
        $profile["photo_src"] = (strpos($p, "/") === 0) ? $p : ("/" . $p);
      } else {
        $base = basename($p);
        $abs = dirname(__DIR__) . "/uploads/teachers/" . $base;
        if (file_exists($abs)) {
          $profile["photo_src"] = "/uploads/teachers/" . rawurlencode($base);
        }
      }
    }
    $subs = [];
    $raw = $rec["school_year_ids"] ?? ($rec["school_year_subscription"] ?? []);
    if (is_string($raw)) {
      $tmp = json_decode($raw, true);
      if (is_array($tmp)) $subs = array_values(array_filter($tmp, function($v){ return (string)$v !== ""; }));
    } else if (is_array($raw)) {
      $subs = array_values(array_filter($raw, function($v){ return (string)$v !== ""; }));
    }
    $options = sb_get("school_years", ["select"=>"id,code,description,start_date,end_date", "order"=>"id.desc"]);
    if (!is_array($options)) $options = [];
    if (count($subs)>0) {
      $subsStr = array_map("strval", $subs);
      $useByCode = false;
      foreach ($subsStr as $v) { if (!ctype_digit($v)) { $useByCode = true; break; } }
      $opts = array_values(array_filter($options, function($row) use ($subsStr, $useByCode){
        $id = (string)($row["id"] ?? "");
        $code = (string)($row["code"] ?? "");
        return $useByCode ? in_array($code, $subsStr, true) : in_array($id, $subsStr, true);
      }));
      $profile["school_years"] = array_map(function($row){
        $id = $row["id"] ?? "";
        $code = $row["code"] ?? "";
        $desc = $row["description"] ?? "";
        return ["id"=>$id, "code"=>$code, "label"=>($code ? $code : (string)$id), "sy"=>"SY " . $desc];
      }, $opts);
    }
  }
} else {
  $profile["password_mask"] = "••••••••••";
}
$profile["code"] = $profile["code"] ?: strtoupper(initials($profile["full_name"]));
$classesCount = 0;
if ($useSb && $tid) {
  $rows = sb_get("class_schedule", ["select"=>"id", "teacher_id"=>"eq.".$tid]);
  if (is_array($rows)) $classesCount = count($rows);
} else if ($tid) {
  $rows = $_SESSION["__class_schedules"] ?? [];
  if (is_array($rows)) {
    $classesCount = count(array_filter($rows, function($r) use ($tid){
      return (string)($r["teacher_id"] ?? "") === (string)$tid;
    }));
  }
}
$classes = [];
if ($tid) {
  if ($useSb) {
    $rows = sb_get("class_schedule", ["select"=>"id,subject_description,time,day,room,class_code,class,type,schoolyear_id,teacher_id", "teacher_id"=>"eq.".$tid, "order"=>"id.desc"]);
    if (is_array($rows)) {
      $classes = array_map(function($r){
        return [
          "id"=>$r["id"]??null,
          "class_code"=>$r["class_code"] ?? "",
          "class"=>$r["class"] ?? "",
          "subject_description"=>$r["subject_description"] ?? "",
          "time"=>$r["time"] ?? "",
          "day"=>$r["day"] ?? "",
          "room"=>$r["room"] ?? "",
          "type"=>$r["type"] ?? "",
          "schoolyear_id"=>$r["schoolyear_id"] ?? null,
          "teacher_id"=>$r["teacher_id"] ?? null
        ];
      }, $rows);
    }
  } else {
    $rows = $_SESSION["__class_schedules"] ?? [];
    if (is_array($rows)) {
      $classes = array_values(array_filter($rows, function($r) use ($tid){
        $rid = $r["teacher_id"] ?? null;
        return (string)$rid === (string)$tid;
      }));
      $classes = array_map(function($r){
        return [
          "id"=>$r["id"]??null,
          "class_code"=>$r["class_code"] ?? ($r["code"] ?? ""),
          "class"=>$r["class"] ?? "",
          "subject_description"=>$r["subject_description"] ?? ($r["subject"] ?? ""),
          "time"=>$r["time"] ?? "",
          "day"=>$r["day"] ?? "",
          "room"=>$r["room"] ?? "",
          "type"=>$r["type"] ?? "",
          "schoolyear_id"=>$r["schoolyear_id"] ?? ($r["school_year_id"] ?? null),
          "teacher_id"=>$r["teacher_id"] ?? null
        ];
      }, $classes);
    }
  }
}
function initials($s) {
  $t = preg_split("/\\s+/", trim($s));
  $letters = [];
  foreach ($t as $w) { if ($w !== "") $letters[] = mb_strtoupper(mb_substr($w,0,1)); }
  $letters = array_slice($letters, 0, 3);
  return implode("", $letters) ?: "T";
}
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Teacher Dashboard</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
:root { --brand-1:#0ea5e9; --brand-2:#6366f1; --ink:#0f172a; }
body { min-height:100vh; background: linear-gradient(180deg, #f8fafc 0%, #eef2ff 100%); }
.t-hero { position: relative; padding: 28px 0; background: linear-gradient(120deg, var(--brand-1) 0%, var(--brand-2) 40%, #0f172a 100%); color: #ffffff; border-bottom: 1px solid rgba(255,255,255,0.18); }
.t-hero__title { font-weight: 800; line-height: 1.1; font-size: clamp(24px, 5.5vw, 40px); letter-spacing: -0.01em; }
.t-hero__subtitle { opacity: 0.92; font-size: 15px; }
.metric-card { border: none; border-radius: 14px; box-shadow: 0 10px 24px rgba(2,6,23,0.08); }
.metric-title { font-size: 13px; text-transform: uppercase; letter-spacing: 0.08em; color: #64748b; }
.metric-value { font-size: 22px; font-weight: 800; color: var(--ink); }
.quick-card { border: none; border-radius: 14px; box-shadow: 0 8px 20px rgba(2,6,23,0.06); transition: transform .18s ease, box-shadow .18s ease; }
.quick-card:hover { transform: translateY(-1px); box-shadow: 0 12px 28px rgba(2,6,23,0.12); }
.btn-outline-secondary { border-color:#cbd5e1; color:#334155; }
.profile-card { border: none; border-radius: 14px; box-shadow: 0 10px 24px rgba(2,6,23,0.08); }
.profile-avatar { width: 64px; height: 64px; border-radius: 50%; background: #e2e8f0; color: #0f172a; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 20px; overflow: hidden; }
.profile-avatar img { width:100%; height:100%; object-fit:cover; display:block; }
.profile-title { font-weight: 800; letter-spacing: 0.2px; }
.profile-sub { color: #64748b; font-size: 13px; font-weight: 600; }
.field-label { text-transform: uppercase; font-size: 11px; color: #64748b; letter-spacing: 0.12em; }
.sy-chip { display:inline-block; border: 1px solid #c7d2fe; color:#3730a3; background:#eef2ff; padding: 2px 8px; border-radius: 999px; font-size: 12px; margin-right: 6px; margin-top: 6px; }
.pass-view { display:inline-flex; align-items:center; gap:6px; }
.eye-icon { display:inline-flex; align-items:center; justify-content:center; }
:root { --eye-yellow:#facc15; }
.view-btn { background-color:var(--eye-yellow); border-color:var(--eye-yellow); color:#111827; }
.view-btn:hover { background-color:var(--eye-yellow); border-color:var(--eye-yellow); color:#111827; }
.view-btn svg { width:16px; height:16px; }
.view-btn svg path, .view-btn svg circle { fill:#000000; stroke:#000000; stroke-width:1.5; }
.view-btn:hover svg path, .view-btn:hover svg circle { fill:#000000; stroke:#000000; }
.add-student-btn { background-color:var(--brand-2); border-color:var(--brand-2); color:#ffffff; }
.add-student-btn:hover { background-color:var(--brand-2); border-color:var(--brand-2); color:#ffffff; }
.add-student-btn svg { width:16px; height:16px; }
.add-student-btn svg path { fill:none; stroke:#000000; stroke-width:1.5; }
.add-student-btn:hover svg path { stroke:#ffffff; }
.action-btn { font-size: 11px; padding: 2px 8px; line-height: 1.2; }
</style>
</head>
<body class="min-vh-100 d-flex flex-column">
<?php include __DIR__ . "/teacher_nav.php"; ?>
<div class="t-hero">
  <div class="container">
    <div class="d-flex align-items-center justify-content-between">
      <div>
        <div class="t-hero__title">Teacher Dashboard</div>
        <div class="t-hero__subtitle">Welcome <?= htmlspecialchars($name) ?> to Attendance Tracker!</div>
      </div>
      <div class="d-flex align-items-center gap-2"></div>
    </div>
  </div>
</div>
<main class="container py-4 flex-grow-1">
  
  <div class="row g-3 mt-1">
    <div class="col-md-12">
      <div class="card table-card">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-center mb-2">
            <div class="fw-bold">Class Schedule</div>
          </div>
          <div class="table-responsive">
            <table class="table table-striped table-sm align-middle">
              <thead>
                <tr>
                  <th>Class Code</th>
                  <th>Class</th>
                  <th>Subject</th>
                  <th>Time</th>
                  <th>Day</th>
                  <th>Room</th>
                  <th>Type</th>
                  <th>Action</th>
                </tr>
              </thead>
              <tbody>
                <?php if (is_array($classes) && count($classes)>0): ?>
                  <?php foreach ($classes as $c): ?>
                    <tr>
                      <td><?= htmlspecialchars($c["class_code"] ?? "") ?></td>
                      <td><?= htmlspecialchars($c["class"] ?? "") ?></td>
                      <td><?= htmlspecialchars($c["subject_description"] ?? "") ?></td>
                      <td><?= htmlspecialchars($c["time"] ?? "") ?></td>
                      <td><?= htmlspecialchars($c["day"] ?? "") ?></td>
                      <td><?= htmlspecialchars($c["room"] ?? "") ?></td>
                      <td><?= htmlspecialchars($c["type"] ?? "") ?></td>
                      <td>
                        <?php require_once __DIR__ . "/../lib/urlref.php"; $vTok = url_ref_create(["class"=>(string)($c["id"] ?? "")]); ?>
                        <a class="btn btn-light btn-sm add-student-btn action-btn" href="/teacher/add-student?ref=<?= htmlspecialchars($vTok) ?>" title="Add Students" aria-label="Add Students">Add Students</a>
                        <a class="btn btn-light btn-sm view-btn action-btn" href="/teacher/classes?ref=<?= htmlspecialchars($vTok) ?>" title="View Class" aria-label="View Class">View Class</a>
                        <?php $aTok = url_ref_create(["class"=>(string)($c["id"] ?? "")]); ?>
                        <a class="btn btn-primary btn-sm action-btn" href="/teacher/attendance?ref=<?= htmlspecialchars($aTok) ?>" title="Attendance" aria-label="Attendance">Attendance</a>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php else: ?>
                  <tr><td colspan="8" class="text-muted">No classes found</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
</main>
<footer class="text-center text-muted small py-3 border-top mt-auto">
  <div class="container">© 2026 Attendance Tracker | Developed by: Von P. Gabayan Jr.</div>
</footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script></script>
</body>
</html>
