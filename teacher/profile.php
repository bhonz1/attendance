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
    $raw = $rec["school_year_ids"] ?? [];
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
      $opts = array_values(array_filter($options, function($row) use ($subsStr){
        $id = (string)($row["id"] ?? "");
        $code = (string)($row["code"] ?? "");
        return in_array($id, $subsStr, true) || in_array($code, $subsStr, true);
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
<title>Teacher Profile</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
:root { --brand-1:#0ea5e9; --brand-2:#6366f1; --ink:#0f172a; }
body { min-height:100vh; background: linear-gradient(180deg, #f8fafc 0%, #eef2ff 100%); }
.t-hero { position: relative; padding: 28px 0; background: linear-gradient(120deg, var(--brand-1) 0%, var(--brand-2) 40%, #0f172a 100%); color: #ffffff; border-bottom: 1px solid rgba(255,255,255,0.18); }
.t-hero__title { font-weight: 800; line-height: 1.1; font-size: clamp(24px, 5.5vw, 40px); letter-spacing: -0.01em; }
.profile-card { border: none; border-radius: 14px; box-shadow: 0 10px 24px rgba(2,6,23,0.08); }
.profile-avatar { width: 64px; height: 64px; border-radius: 50%; background: #e2e8f0; color: #0f172a; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 20px; overflow: hidden; }
.profile-avatar img { width:100%; height:100%; object-fit:cover; display:block; }
.profile-title { font-weight: 800; letter-spacing: 0.2px; }
.profile-sub { color: #64748b; font-size: 13px; font-weight: 600; }
.field-label { text-transform: uppercase; font-size: 11px; color: #64748b; letter-spacing: 0.12em; }
.sy-chip { display:inline-block; border: 1px solid #c7d2fe; color:#3730a3; background:#eef2ff; padding: 2px 8px; border-radius: 999px; font-size: 12px; margin-right: 6px; margin-top: 6px; }
.pass-view { display:inline-flex; align-items:center; gap:6px; }
.eye-icon { display:inline-flex; align-items:center; justify-content:center; }
</style>
</head>
<body class="min-vh-100 d-flex flex-column">
<?php $teacher_nav_active = "profile"; include __DIR__ . "/teacher_nav.php"; ?>
<div class="t-hero">
  <div class="container">
    <div class="d-flex align-items-center justify-content-between">
      <div>
        <div class="t-hero__title">Profile</div>
      </div>
      <div class="d-flex align-items-center gap-2"></div>
    </div>
  </div>
</div>
<main class="container py-4 flex-grow-1">
  <div class="card profile-card mb-3">
    <div class="card-body">
      <div class="d-flex align-items-center justify-content-between">
        <div class="d-flex align-items-center gap-3">
          <div class="profile-avatar">
            <?php if ($profile["photo_src"]): ?>
              <img src="<?= htmlspecialchars($profile["photo_src"]) ?>" alt="Profile">
            <?php else: ?>
              <?= htmlspecialchars(initials($profile["full_name"])) ?>
            <?php endif; ?>
          </div>
          <div>
            <div class="profile-title text-uppercase"><?= htmlspecialchars($profile["full_name"]) ?></div>
            <div class="profile-sub"><?= htmlspecialchars($profile["code"] . " - " . (string)$profile["teacher_id"]) ?></div>
          </div>
        </div>
        <div class="d-flex align-items-center gap-4">
          <div>
            <div class="field-label">Username</div>
            <div><?= htmlspecialchars($profile["username"]) ?></div>
          </div>
          <div>
            <div class="field-label">Password</div>
            <div class="pass-view">
              <span id="pwMask"><?= htmlspecialchars($profile["password_mask"]) ?></span>
            </div>
          </div>
          <div>
            <div class="field-label">College</div>
            <div class="fw-bold"><?= htmlspecialchars($profile["college"] ?: "N/A") ?></div>
          </div>
          <div>
            <div class="field-label">Institution</div>
            <div class="fw-bold"><?= htmlspecialchars($profile["institution"] ?: "N/A") ?></div>
          </div>
        </div>
      </div>
      <div class="mt-3">
        <div class="field-label">School Years</div>
        <div>
          <?php foreach ($profile["school_years"] as $sy): ?>
            <span class="sy-chip"><?= htmlspecialchars(($sy["code"] ?: $sy["id"]) . " - " . (string)$sy["id"] . " — " . $sy["sy"]) ?></span>
          <?php endforeach; ?>
          <?php if (count($profile["school_years"])===0): ?>
            <span class="text-muted">None</span>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</main>
<footer class="text-center text-muted small py-3 border-top mt-auto">
  <div class="container">© 2026 Attendance Tracker | Developed by: Von P. Gabayan Jr.</div>
</footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
