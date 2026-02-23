<?php
require_once __DIR__ . "/../lib/admin.php";
require_once __DIR__ . "/../lib/supabase.php";
require_once __DIR__ . "/../lib/urlref.php";
require_admin_session();
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
$msgSchedule = $_SESSION["__schedule_msg"] ?? null;
$errSchedule = $_SESSION["__schedule_err"] ?? null;
unset($_SESSION["__schedule_msg"], $_SESSION["__schedule_err"]);
$method = $_SERVER["REQUEST_METHOD"] ?? "";
if ($method === "GET" && (isset($_GET["op"]) && $_GET["op"] === "captcha")) {
  header("Content-Type: application/json");
  $code = bin2hex(random_bytes(3));
  $_SESSION["__reveal_captcha"] = $code;
  echo json_encode(["ok"=>true,"code"=>$code]); exit;
}
if ($method === "POST" && (isset($_POST["op"]) && $_POST["op"] === "reveal_password")) {
  header("Content-Type: application/json");
  require_once __DIR__ . "/../lib/csrf.php";
  if (!csrf_validate($_POST["csrf"] ?? "")) { echo json_encode(["ok"=>false,"error"=>"Invalid CSRF"]); exit; }
  $captcha = $_POST["captcha"] ?? "";
  $expect = $_SESSION["__reveal_captcha"] ?? "";
  if (!$expect || strtolower($captcha) !== strtolower($expect)) { echo json_encode(["ok"=>false,"error"=>"Invalid captcha"]); exit; }
  $tid = $_POST["id"] ?? "";
  if (!$tid) { echo json_encode(["ok"=>false,"error"=>"Missing id"]); exit; }
  $rec = null;
  if (sb_url()) {
    $r = sb_get("teacher_registry", ["select"=>"username,password_enc", "id"=>"eq.".$tid, "limit"=>1]);
    if (is_array($r) && isset($r[0])) $rec = $r[0];
  } else {
    if (isset($_SESSION["__teacher_registry"]) && is_array($_SESSION["__teacher_registry"])) {
      foreach ($_SESSION["__teacher_registry"] as $row) { if (($row["id"] ?? "") === $tid) { $rec = $row; break; } }
    }
  }
  if (!$rec) { echo json_encode(["ok"=>false,"error"=>"Not found"]); exit; }
  $cipher = $rec["password_enc"] ?? "";
  $secret = getenv("TEACHER_PW_SECRET") ?: "";
  if (!$cipher || !$secret || strpos($cipher, ":") === false) { echo json_encode(["ok"=>false,"error"=>"No encrypted password"]); exit; }
  list($ivb64, $ctb64) = explode(":", $cipher, 2);
  $iv = base64_decode($ivb64, true);
  $ct = base64_decode($ctb64, true);
  if ($iv === false || $ct === false) { echo json_encode(["ok"=>false,"error"=>"Invalid data"]); exit; }
  $key = hash("sha256", $secret, true);
  $pt = openssl_decrypt($ct, "AES-256-CBC", $key, OPENSSL_RAW_DATA, $iv);
  if ($pt === false) { echo json_encode(["ok"=>false,"error"=>"Decrypt failed"]); exit; }
  echo json_encode(["ok"=>true,"username"=>($rec["username"] ?? ""), "password"=>$pt]); exit;
}
if ($method === "POST" && isset($_POST["schedule_action"])) {
  require_once __DIR__ . "/../lib/csrf.php";
  $tk = $_POST["csrf"] ?? "";
  if (!csrf_validate($tk)) { $tok = url_ref_create(["id"=> (string)($_POST["teacher_id"] ?? "") ]); http_redirect("/admin/view_teacher.php?ref=" . $tok); }
  $act = $_POST["schedule_action"];
  $sid = $_POST["id"] ?? "";
  $tid = $_POST["teacher_id"] ?? "";
  if ($act === "create") {
    $typePost = $_POST["type"] ?? "";
    $typeStr = is_array($typePost) ? implode(", ", array_values(array_filter($typePost, function($v){ return trim($v) !== ""; }))) : trim($typePost);
    $body = [
      "teacher_id" => $tid,
      "subject_description" => trim($_POST["subject_description"] ?? ""),
      "time" => trim($_POST["time"] ?? ""),
      "day" => trim($_POST["day"] ?? ""),
      "room" => trim($_POST["room"] ?? ""),
      "class" => trim($_POST["class"] ?? ""),
      "class_code" => trim($_POST["class_code"] ?? ""),
      "type" => $typeStr,
      "schoolyear_id" => trim($_POST["schoolyear_id"] ?? "")
    ];
    $ok = false;
    if (sb_url()) {
      $r = sb_post("class_schedule", $body);
      if ($r !== null) { $ok = true; }
      if (!$ok) {
        $alt = [
          "teacher_id" => $tid,
          "subject" => $body["subject_description"],
          "time" => $body["time"],
          "day" => $body["day"],
          "room" => $body["room"],
          "class_name" => $body["class"],
          "code" => $body["class_code"],
          "type" => $body["type"],
          "school_year_id" => $body["schoolyear_id"]
        ];
        $r = sb_post("class_schedule", $alt);
        if ($r !== null) { $ok = true; }
      }
      if (!$ok) { $r = sb_post("class_schedules", $body); if ($r !== null) $ok = true; }
      if (!$ok) { $r = sb_post("class_schedules", isset($alt) ? $alt : $body); if ($r !== null) $ok = true; }
      if (!$ok) { $r = sb_post("schedules", $body); if ($r !== null) $ok = true; }
      if (!$ok) { $r = sb_post("schedules", isset($alt) ? $alt : $body); if ($r !== null) $ok = true; }
    } else {
      if (!isset($_SESSION["__class_schedules"]) || !is_array($_SESSION["__class_schedules"])) $_SESSION["__class_schedules"] = [];
      $idNew = uniqid("cs_", true);
      $row = ["id"=>$idNew] + $body;
      $_SESSION["__class_schedules"][] = $row;
      $ok = true;
    }
    if ($ok) { $_SESSION["__schedule_msg"] = "Schedule created"; }
    else {
      $srv = getenv("SUPABASE_SERVICE_ROLE") ?: "";
      $baseMsg = $srv ? "Create failed" : "Write blocked by RLS; configure SUPABASE_SERVICE_ROLE or allow anon writes";
      $errInfo = function_exists("sb_last_error") ? sb_last_error() : null;
      $extra = "";
      if (is_array($errInfo)) {
        $code = $errInfo["code"] ?? null;
        $json = $errInfo["body_json"] ?? null;
        $raw = $errInfo["body_raw"] ?? "";
        $msg = (is_array($json) ? ($json["message"] ?? ($json["hint"] ?? ($json["error"] ?? ""))) : "");
        if (!$msg && is_string($raw) && strlen($raw)) $msg = substr($raw, 0, 200);
        if ($code) $extra .= " (HTTP " . $code . ")";
        if ($msg) $extra .= ": " . $msg;
      }
      $_SESSION["__schedule_err"] = $baseMsg . $extra;
    }
  } elseif ($act === "update" && $sid) {
    $typePost = $_POST["type"] ?? "";
    $typeStr = is_array($typePost) ? implode(", ", array_values(array_filter($typePost, function($v){ return trim($v) !== ""; }))) : trim($typePost);
    $body = [
      "teacher_id" => $tid,
      "subject_description" => trim($_POST["subject_description"] ?? ""),
      "time" => trim($_POST["time"] ?? ""),
      "day" => trim($_POST["day"] ?? ""),
      "room" => trim($_POST["room"] ?? ""),
      "class" => trim($_POST["class"] ?? ""),
      "class_code" => trim($_POST["class_code"] ?? ""),
      "type" => $typeStr,
      "schoolyear_id" => trim($_POST["schoolyear_id"] ?? "")
    ];
    $ok = false;
    if (sb_url()) {
      $r = sb_patch("class_schedule", $body, ["id" => "eq." . $sid]);
      if ($r !== null) { $ok = true; }
      if (!$ok) {
        $alt = [
          "teacher_id" => $tid,
          "subject" => $body["subject_description"],
          "time" => $body["time"],
          "day" => $body["day"],
          "room" => $body["room"],
          "class_name" => $body["class"],
          "code" => $body["class_code"],
          "type" => $body["type"],
          "school_year_id" => $body["schoolyear_id"]
        ];
        $r = sb_patch("class_schedule", $alt, ["id" => "eq." . $sid]);
        if ($r !== null) { $ok = true; }
      }
      if (!$ok) { $r = sb_patch("class_schedules", $body, ["id" => "eq." . $sid]); if ($r !== null) $ok = true; }
      if (!$ok) { $r = sb_patch("class_schedules", isset($alt) ? $alt : $body, ["id" => "eq." . $sid]); if ($r !== null) $ok = true; }
      if (!$ok) { $r = sb_patch("schedules", $body, ["id" => "eq." . $sid]); if ($r !== null) $ok = true; }
      if (!$ok) { $r = sb_patch("schedules", isset($alt) ? $alt : $body, ["id" => "eq." . $sid]); if ($r !== null) $ok = true; }
    } else {
      if (isset($_SESSION["__class_schedules"]) && is_array($_SESSION["__class_schedules"])) {
        foreach ($_SESSION["__class_schedules"] as &$row) {
          if (($row["id"] ?? "") === $sid) { 
            $row["subject_description"] = $body["subject_description"];
            $row["time"] = $body["time"];
            $row["day"] = $body["day"];
            $row["room"] = $body["room"];
            $row["class"] = $body["class"];
            $row["class_code"] = $body["class_code"];
            $row["type"] = $body["type"];
            $row["schoolyear_id"] = $body["schoolyear_id"];
            $ok = true;
            break;
          }
        }
      }
    }
    if ($ok) { $_SESSION["__schedule_msg"] = "Schedule updated"; }
    else {
      $srv = getenv("SUPABASE_SERVICE_ROLE") ?: "";
      $baseMsg = $srv ? "Update failed" : "Write blocked by RLS; configure SUPABASE_SERVICE_ROLE or allow anon writes";
      $errInfo = function_exists("sb_last_error") ? sb_last_error() : null;
      $extra = "";
      if (is_array($errInfo)) {
        $code = $errInfo["code"] ?? null;
        $json = $errInfo["body_json"] ?? null;
        $raw = $errInfo["body_raw"] ?? "";
        $msg = (is_array($json) ? ($json["message"] ?? ($json["hint"] ?? ($json["error"] ?? ""))) : "");
        if (!$msg && is_string($raw) && strlen($raw)) $msg = substr($raw, 0, 200);
        if ($code) $extra .= " (HTTP " . $code . ")";
        if ($msg) $extra .= ": " . $msg;
      }
      $_SESSION["__schedule_err"] = $baseMsg . $extra;
    }
  } elseif ($act === "delete" && $sid) {
    $ok = false;
    if (sb_url()) {
      $r = sb_delete("class_schedule", ["id" => "eq." . $sid]);
      if ($r !== null) $ok = true;
      if (!$ok) { $r = sb_delete("class_schedules", ["id" => "eq." . $sid]); if ($r !== null) $ok = true; }
      if (!$ok) { $r = sb_delete("schedules", ["id" => "eq." . $sid]); if ($r !== null) $ok = true; }
    } else {
      if (isset($_SESSION["__class_schedules"]) && is_array($_SESSION["__class_schedules"])) {
        $_SESSION["__class_schedules"] = array_values(array_filter($_SESSION["__class_schedules"], function($row) use ($sid) { return ($row["id"] ?? "") !== $sid; }));
        $ok = true;
      }
    }
    if ($ok) { $_SESSION["__schedule_msg"] = "Schedule deleted"; }
    else {
      $srv = getenv("SUPABASE_SERVICE_ROLE") ?: "";
      $baseMsg = $srv ? "Delete failed" : "Write blocked by RLS; configure SUPABASE_SERVICE_ROLE or allow anon writes";
      $errInfo = function_exists("sb_last_error") ? sb_last_error() : null;
      $extra = "";
      if (is_array($errInfo)) {
        $code = $errInfo["code"] ?? null;
        $json = $errInfo["body_json"] ?? null;
        $raw = $errInfo["body_raw"] ?? "";
        $msg = (is_array($json) ? ($json["message"] ?? ($json["hint"] ?? ($json["error"] ?? ""))) : "");
        if (!$msg && is_string($raw) && strlen($raw)) $msg = substr($raw, 0, 200);
        if ($code) $extra .= " (HTTP " . $code . ")";
        if ($msg) $extra .= ": " . $msg;
      }
      $_SESSION["__schedule_err"] = $baseMsg . $extra;
    }
  }
  $tok = url_ref_create(["id"=> (string)$tid ]);
  http_redirect("/admin/view_teacher.php?ref=" . $tok);
}
$useSupabase = sb_url() ? true : false;
$id = $_GET["id"] ?? "";
$refTok = $_GET["ref"] ?? "";
if ($refTok !== "") {
  $ref = url_ref_consume($refTok);
  if (is_array($ref)) {
    $rid = $ref["id"] ?? "";
    if (is_string($rid)) $id = preg_replace('/[^0-9A-Za-z_\\-]/', '', $rid);
  }
}
if (is_string($id)) $id = preg_replace('/[^0-9A-Za-z_\\-]/', '', $id);
$record = null;
$classes = [];
$syOptions = $useSupabase ? sb_get("school_years", ["select" => "id,code,description,start_date,end_date", "order" => "id.desc"]) : ($_SESSION["__school_years_meta"] ?? []);
if (!is_array($syOptions)) $syOptions = [];
if ($useSupabase && $id !== "") {
  $rows = sb_get("teacher_registry", ["select" => "*", "id" => "eq." . $id, "limit" => 1]);
  if (is_array($rows) && isset($rows[0])) $record = $rows[0];
  if ($record === null) { $record = null; }
  if ($record && isset($record["id"])) {
    $classes = sb_get("class_schedule", ["select" => "id,subject_description,time,day,room,class,class_code,type,schoolyear_id,teacher_id", "teacher_id" => "eq." . $record["id"], "order" => "id.desc"]);
    if (!is_array($classes)) {
      $classes = sb_get("class_schedules", ["select" => "id,subject_description,time,day,room,class,class_code,type,schoolyear_id,teacher_id", "teacher_id" => "eq." . $record["id"], "order" => "id.desc"]);
    }
    if (!is_array($classes)) {
      $classes = sb_get("schedules", ["select" => "id,subject_description,time,day,room,class,class_code,type,schoolyear_id,teacher_id", "teacher_id" => "eq." . $record["id"], "order" => "id.desc"]);
    }
    if (!is_array($classes)) $classes = [];
  }
} else {
  if ($id !== "" && isset($_SESSION["__teacher_registry"]) && is_array($_SESSION["__teacher_registry"])) {
    foreach ($_SESSION["__teacher_registry"] as $row) {
      if (($row["id"] ?? "") === $id) { $record = $row; break; }
    }
  }
  if (isset($_SESSION["__class_schedules"]) && is_array($_SESSION["__class_schedules"]) && $record && isset($record["id"])) {
    $classes = array_values(array_filter($_SESSION["__class_schedules"], function($c) use ($record) { return ($c["teacher_id"] ?? null) === $record["id"]; }));
  }
}
$teacherSyIds = [];
if ($record) {
  $teacherSyIds = $record["school_year_ids"] ?? [];
  if (is_string($teacherSyIds)) {
    $tmp = json_decode($teacherSyIds, true);
    if (is_array($tmp)) $teacherSyIds = $tmp; else $teacherSyIds = [];
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>View Teacher</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
.v-hero { position: relative; padding: 28px 0; background: linear-gradient(120deg, #0ea5e9 0%, #6366f1 40%, #0f172a 100%); color: #ffffff; border-bottom: 1px solid rgba(255,255,255,0.18); }
.v-hero__title { font-weight: 800; line-height: 1.1; font-size: clamp(24px, 5.5vw, 40px); letter-spacing: -0.01em; }
.label { font-size: 12px; text-transform: uppercase; letter-spacing: 0.08em; color: #64748b; }
.value { font-size: 18px; font-weight: 700; color: #0f172a; }
.avatar { width: 90px; height: 90px; object-fit: cover; border-radius: 50%; border: 2px solid #e2e8f0; }
.badge-soft { background: #eef2ff; color: #3730a3; border-radius: 999px; padding: 4px 10px; font-size: 12px; }
</style>
</head>
<body class="d-flex flex-column min-vh-100">
<?php $admin_nav_active = "teacher"; include __DIR__ . "/admin_nav.php"; ?>
<div class="v-hero">
  <div class="container">
    <div class="d-flex align-items-center justify-content-between">
      <div>
        <div class="v-hero__title">View Teacher</div>
        <div>Details and subscription</div>
      </div>
      <div>
        <a href="/admin/teacher.php" class="btn btn-light btn-sm">Back</a>
      </div>
    </div>
  </div>
</div>
<div class="container py-4">
  <?php if (!$record): ?>
    <div class="alert alert-warning">Record not found</div>
  <?php else: ?>
    <div class="card">
      <div class="card-body">
        <div class="d-flex align-items-center gap-3 mb-3">
          <?php $p = $record["photo_url"] ?? null; ?>
          <?php if ($p): ?>
            <img class="avatar" src="<?= htmlspecialchars($p) ?>" alt="Photo">
          <?php else: ?>
            <div class="avatar d-flex align-items-center justify-content-center bg-light text-muted">—</div>
          <?php endif; ?>
          <div>
            <div class="value"><?= htmlspecialchars($record["full_name"] ?? "") ?></div>
            <div class="text-muted"><?= htmlspecialchars($record["code"] ?? "") ?></div>
          </div>
        </div>
        <div class="row g-3">
          <div class="col-12 col-md-6">
            <div class="label">Username</div>
            <div class="value"><?= htmlspecialchars($record["username"] ?? "") ?></div>
          </div>
          <div class="col-12 col-md-6">
            <div class="label">Password</div>
            <div class="d-flex align-items-center gap-2">
              <span id="pwMasked" class="value">********</span>
              <button class="btn btn-outline-warning btn-sm" id="openReveal" type="button" aria-label="Reveal Password"><span id="eyeIconOpen" aria-hidden="true">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M12 5c-7 0-10 7-10 7s3 7 10 7 10-7 10-7-3-7-10-7zm0 12c-4.418 0-8-5-8-5s3.582-5 8-5 8 5 8 5-3.582 5-8 5z"/><circle cx="12" cy="12" r="3"/></svg>
              </span><span id="eyeIconClosed" class="d-none" aria-hidden="true">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M2 12s3-7 10-7c3.613 0 6.34 1.763 8.08 3.49l1.28-1.28 1.06 1.06-19 19-1.06-1.06 3.03-3.03C3.56 20.23 2 17 2 12zm8.06-.94-2.12-2.12A4 4 0 0 0 8 12a4 4 0 0 0 4 4c.74 0 1.43-.2 2.02-.55l-2.06-2.06A2 2 0 0 1 10.06 11.06z"/></svg>
              </span></button>
            </div>
          </div>
          <div class="col-12 col-md-6">
            <div class="label">College</div>
            <div class="value"><?= htmlspecialchars($record["department"] ?? "") ?></div>
          </div>
          <div class="col-12 col-md-6">
            <div class="label">Institution</div>
            <div class="value"><?= htmlspecialchars($record["institution"] ?? "") ?></div>
          </div>
          <div class="col-12 col-md-6">
            <div class="label">School Years</div>
            <div>
              <?php
                $sel = $record["school_year_ids"] ?? [];
                if (is_string($sel)) { $tmp = json_decode($sel, true); if (is_array($tmp)) $sel = $tmp; }
                $labels = [];
                foreach ($syOptions as $sy) {
                  $optId = ($sy["id"] ?? ($sy["code"] ?? ""));
                  $label = ($sy["code"] ?? "") ? ($sy["code"] . " — " . ($sy["description"] ?? "")) : (($sy["description"] ?? "") ?: (($sy["start_date"] ?? "") . " - " . ($sy["end_date"] ?? "")));
                  if (in_array($optId, is_array($sel) ? $sel : [])) $labels[] = $label;
                }
                if (count($labels) === 0) echo '<span class="text-muted">—</span>';
                else foreach ($labels as $lb) { echo '<span class="badge-soft me-1">' . htmlspecialchars($lb) . '</span>'; }
              ?>
            </div>
          </div>
        </div>
      </div>
    </div>
  <?php endif; ?>
</div>
<?php if ($record): ?>
<div class="container pb-4">
  <div class="card">
    <div class="card-body">
      <div class="d-flex align-items-center justify-content-between mb-3">
        <h5 class="mb-0">Class Schedule</h5>
        <div class="d-flex gap-2">
          <button class="btn btn-outline-success btn-sm" data-bs-toggle="modal" data-bs-target="#csCreateModal">+</button>
        </div>
      </div>
      <?php if ($msgSchedule): ?><div class="alert alert-success"><?= htmlspecialchars($msgSchedule) ?></div><?php endif; ?>
      <?php if ($errSchedule): ?><div class="alert alert-danger"><?= htmlspecialchars($errSchedule) ?></div><?php endif; ?>
      <div class="table-responsive">
        <table class="table table-sm align-middle">
          <thead>
            <tr>
              <th>Class Code</th>
              <th>Subject Description</th>
              <th>Time</th>
              <th>Day</th>
              <th>Room</th>
              <th>Class</th>
              <th>Type</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <?php if (is_array($classes) && count($classes) > 0): ?>
              <?php foreach ($classes as $c): ?>
                <tr>
                  <td><?= htmlspecialchars($c["class_code"] ?? ($c["code"] ?? "")) ?></td>
                  <td><?= htmlspecialchars($c["subject_description"] ?? ($c["subject"] ?? "")) ?></td>
                  <td><?= htmlspecialchars($c["time"] ?? ($c["time_str"] ?? "")) ?></td>
                  <td><?= htmlspecialchars($c["day"] ?? ($c["day_str"] ?? "")) ?></td>
                  <td><?= htmlspecialchars($c["room"] ?? ($c["room_name"] ?? "")) ?></td>
                  <td><?= htmlspecialchars($c["class"] ?? ($c["class_name"] ?? "")) ?></td>
                  <td><?= htmlspecialchars($c["type"] ?? "") ?></td>
                  <td>
                    <?php $sid = htmlspecialchars($c["id"] ?? ""); ?>
                    <div class="d-flex gap-2">
                      <button class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#csEditModal_<?= $sid ?>">Edit</button>
                      <button class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#csDeleteModal_<?= $sid ?>">Delete</button>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr><td colspan="8" class="text-muted">No schedule</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
      <!-- Bulk Uploading moved outside the Class Schedule card -->
    </div>
  </div>
</div>
<?php endif; ?>
<?php /* Bulk Uploading feature removed */ ?>
<?php if ($record): ?>
<div class="modal fade" id="csCreateModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title">Add Schedule</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
      <div class="modal-body">
        <form method="post">
          <input type="hidden" name="schedule_action" value="create">
          <input type="hidden" name="teacher_id" value="<?= htmlspecialchars($record["id"] ?? "") ?>">
          <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
          <div class="mb-2"><label class="form-label">Class Code</label><input name="class_code" class="form-control"></div>
          <div class="mb-2"><label class="form-label">Subject Description</label><input name="subject_description" class="form-control"></div>
          <div class="mb-2"><label class="form-label">Time</label><input name="time" class="form-control"></div>
          <div class="mb-2"><label class="form-label">Day</label><input name="day" class="form-control"></div>
          <div class="mb-2"><label class="form-label">Room</label><input name="room" class="form-control"></div>
          <div class="mb-2"><label class="form-label">Class</label><input name="class" class="form-control"></div>
          <div class="mb-2">
            <label class="form-label">Type</label>
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="type[]" value="Lecture" id="typeLecture">
              <label class="form-check-label" for="typeLecture">Lecture</label>
            </div>
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="type[]" value="Laboratory" id="typeLaboratory">
              <label class="form-check-label" for="typeLaboratory">Laboratory</label>
            </div>
          </div>
          <div class="mb-2">
            <label class="form-label">Schoolyear ID</label>
            <select name="schoolyear_id" class="form-select" required>
              <?php
                $hasOpt = false;
                foreach ($syOptions as $sy) {
                  $optId = ($sy["id"] ?? ($sy["code"] ?? ""));
                  if (in_array($optId, is_array($teacherSyIds) ? $teacherSyIds : [])) {
                    $label = ($sy["code"] ?? "") ? (($sy["code"] ?? "") . " — " . ($sy["description"] ?? "")) : (($sy["description"] ?? "") ?: (($sy["start_date"] ?? "") . " - " . ($sy["end_date"] ?? "")));
                    echo '<option value="'.htmlspecialchars($optId).'">'.htmlspecialchars($label).'</option>';
                    $hasOpt = true;
                  }
                }
                if (!$hasOpt) {
                  echo '<option value="" disabled selected>No subscription</option>';
                }
              ?>
            </select>
          </div>
          <div class="text-end"><button type="submit" class="btn btn-success">Add</button></div>
        </form>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>
<?php /* Bulk Uploading modals removed */ ?>
<?php if (is_array($classes) && count($classes) > 0 && $record): ?>
<?php foreach ($classes as $c): $sid = htmlspecialchars($c["id"] ?? ""); ?>
<div class="modal fade" id="csEditModal_<?= $sid ?>" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title">Edit Schedule</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
      <div class="modal-body">
        <form method="post">
          <input type="hidden" name="schedule_action" value="update">
          <input type="hidden" name="id" value="<?= $sid ?>">
          <input type="hidden" name="teacher_id" value="<?= htmlspecialchars($record["id"] ?? "") ?>">
          <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
          <div class="mb-2"><label class="form-label">Class Code</label><input name="class_code" class="form-control" value="<?= htmlspecialchars($c["class_code"] ?? ($c["code"] ?? "")) ?>"></div>
          <div class="mb-2"><label class="form-label">Subject Description</label><input name="subject_description" class="form-control" value="<?= htmlspecialchars($c["subject_description"] ?? ($c["subject"] ?? "")) ?>"></div>
          <div class="mb-2"><label class="form-label">Time</label><input name="time" class="form-control" value="<?= htmlspecialchars($c["time"] ?? ($c["time_str"] ?? "")) ?>"></div>
          <div class="mb-2"><label class="form-label">Day</label><input name="day" class="form-control" value="<?= htmlspecialchars($c["day"] ?? ($c["day_str"] ?? "")) ?>"></div>
          <div class="mb-2"><label class="form-label">Room</label><input name="room" class="form-control" value="<?= htmlspecialchars($c["room"] ?? ($c["room_name"] ?? "")) ?>"></div>
          <div class="mb-2"><label class="form-label">Class</label><input name="class" class="form-control" value="<?= htmlspecialchars($c["class"] ?? ($c["class_name"] ?? "")) ?>"></div>
          <?php $tvals = array_map('trim', explode(',', (string)($c["type"] ?? ""))); $tl = in_array("Lecture", $tvals); $tb = in_array("Laboratory", $tvals); ?>
          <div class="mb-2">
            <label class="form-label">Type</label>
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="type[]" value="Lecture" id="typeLecture_<?= $sid ?>" <?= $tl ? "checked" : "" ?>>
              <label class="form-check-label" for="typeLecture_<?= $sid ?>">Lecture</label>
            </div>
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="type[]" value="Laboratory" id="typeLaboratory_<?= $sid ?>" <?= $tb ? "checked" : "" ?>>
              <label class="form-check-label" for="typeLaboratory_<?= $sid ?>">Laboratory</label>
            </div>
          </div>
          <div class="mb-2"><label class="form-label">Schoolyear ID</label><input name="schoolyear_id" class="form-control" value="<?= htmlspecialchars($c["schoolyear_id"] ?? ($c["school_year_id"] ?? ($c["sy_id"] ?? ""))) ?>"></div>
          <div class="text-end"><button type="submit" class="btn btn-primary">Save</button></div>
        </form>
      </div>
    </div>
  </div>
</div>
<div class="modal fade" id="csDeleteModal_<?= $sid ?>" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title">Delete Schedule</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
      <div class="modal-body">
        <p class="text-muted">This action cannot be undone.</p>
        <form method="post">
          <input type="hidden" name="schedule_action" value="delete">
          <input type="hidden" name="id" value="<?= $sid ?>">
          <input type="hidden" name="teacher_id" value="<?= htmlspecialchars($record["id"] ?? "") ?>">
          <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
          <div class="d-flex justify-content-end gap-2">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-danger">Delete</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
<?php endforeach; ?>
<?php endif; ?>
<div class="modal fade" id="revealPwModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title">Reveal Password</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
      <div class="modal-body">
        <form id="revealPwForm" method="post">
          <input type="hidden" name="op" value="reveal_password">
          <input type="hidden" name="id" value="<?= htmlspecialchars($record["id"] ?? "") ?>">
          <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
          <div class="mb-2">
            <label class="form-label">Verification Letters</label>
            <div id="captchaCode" class="form-control text-center" style="font-weight:700; letter-spacing:0.2em;">------</div>
          </div>
          <div class="mb-2">
            <label class="form-label">Enter Letters</label>
            <input type="text" name="captcha" class="form-control" maxlength="6" required>
          </div>
        </form>
        <div id="revealStatus" class="text-muted small"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        <button type="button" class="btn btn-outline-secondary" id="refreshCaptcha">Refresh</button>
        <button type="submit" form="revealPwForm" class="btn btn-primary">Reveal</button>
      </div>
    </div>
  </div>
  </div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function(){
  const q = s => document.querySelector(s);
  const id = "<?= htmlspecialchars($record["id"] ?? "") ?>";
  const open = q("#openReveal");
  let revealed = false;
  if (open) {
    open.addEventListener("click", function(){
      if (!revealed) {
        const m = new bootstrap.Modal(document.getElementById('revealPwModal'));
        m.show();
        fetch(location.pathname + location.search + (location.search ? "&" : "?") + "op=captcha").then(x=>x.json()).then(function(r){
          if (r && r.ok) { const el = document.getElementById("captchaCode"); el.textContent = r.code; }
        });
      } else {
        document.getElementById("pwMasked").textContent = "********";
        revealed = false;
        open.classList.remove("btn-outline-danger");
        open.classList.add("btn-outline-warning");
        document.getElementById("eyeIconOpen").classList.remove("d-none");
        document.getElementById("eyeIconClosed").classList.add("d-none");
      }
    });
  }
  const form = document.getElementById("revealPwForm");
  if (form) {
    form.addEventListener("submit", async function(e){
      e.preventDefault();
      const fd = new FormData(form);
      const r = await fetch(location.pathname + location.search, { method:"POST", body:fd }).then(x=>x.json()).catch(()=>({ok:false,error:"Network"}));
      const status = document.getElementById("revealStatus");
      if (r && r.ok) {
        document.getElementById("pwMasked").textContent = r.password ? r.password : "—";
        status.textContent = r.password ? "Showing password" : "No password set";
        revealed = !!r.password;
        if (revealed) {
          open.classList.remove("btn-outline-warning");
          open.classList.add("btn-outline-danger");
          document.getElementById("eyeIconOpen").classList.add("d-none");
          document.getElementById("eyeIconClosed").classList.remove("d-none");
          const modalEl = document.getElementById('revealPwModal');
          if (modalEl) {
            const m = bootstrap.Modal.getOrCreateInstance(modalEl);
            m.hide();
          }
        }
      } else {
        status.textContent = (r && r.error) ? r.error : "Failed";
      }
    });
  }
  const refresh = document.getElementById("refreshCaptcha");
  if (refresh) {
    refresh.addEventListener("click", function(){
      fetch(location.pathname + location.search + (location.search ? "&" : "?") + "op=captcha").then(x=>x.json()).then(function(r){
        if (r && r.ok) { const el = document.getElementById("captchaCode"); el.textContent = r.code; }
      });
    });
  }
})();
</script>
<script>
(function(){
  const csrfToken = "<?= htmlspecialchars(csrf_token()) ?>";
  const teacherCode = "<?= htmlspecialchars($record["code"] ?? "") ?>";
  const file = document.getElementById("uploadFileInput");
  const start = document.getElementById("scanStart");
  const proceed = document.getElementById("scanProceed");
  const status = document.getElementById("scanStatus");
  const tbody = document.querySelector("#bulkTable tbody");
  const rowCount = null;
  const bulkTbody = document.querySelector("#bulkTable tbody");
  const bulkAll = document.getElementById("bulkAll");
  const mergedBox = document.getElementById("bulkMergedText");
  let parsed = [];
  function hasRows(){ return tbody.querySelectorAll('tr').length > 0; }
  function ensureScanEnabled(){
    const hasSelected = !!(file && file.files && file.files.length > 0 && /\.(jpg|jpeg)$/i.test((file.files[0].name || '')));
    if (window.currentUploadUrl || hasSelected) setReadyStyle(); else setIdleStyle();
  }
  function isRowComplete(tr){
    const inputs = tr.querySelectorAll('input.form-control');
    const subj = inputs[1] ? inputs[1].value.trim() : '';
    const time = inputs[2] ? inputs[2].value.trim() : '';
    const day = inputs[3] ? inputs[3].value.trim() : '';
    const room = inputs[4] ? inputs[4].value.trim() : '';
    const cls = inputs[5] ? inputs[5].value.trim() : '';
    return !!(cls && subj && time && day && room);
  }
  function updateSaveEnabled(){
    let ok = false;
    tbody.querySelectorAll('tr').forEach(function(tr){ if (isRowComplete(tr)) ok = true; });
    if (proceed) proceed.disabled = !ok;
  }
  function generateRows(nDesired) {
    let n = parseInt((nDesired !== undefined ? nDesired : ((rowCount && rowCount.value) ? rowCount.value : '0')), 10);
    if (!isFinite(n) || n < 1) n = 5;
    if (n > 100) n = 100;
    tbody.innerHTML = '';
    for (let i=0;i<n;i++){
      const tr = document.createElement('tr');
      tr.innerHTML =
        '<td><input class="form-control form-control-sm sc-code" value=""></td>'+
        '<td><input class="form-control form-control-sm sc-subj" value=""></td>'+
        '<td><input class="form-control form-control-sm sc-time" value=""></td>'+
        '<td><input class="form-control form-control-sm sc-day" value=""></td>'+
        '<td><input class="form-control form-control-sm sc-room" value=""></td>'+
        '<td><input class="form-control form-control-sm sc-class" value=""></td>'+
        '<td><select class="form-select form-select-sm sc-type"><option value=""></option><option value="Lecture">Lecture</option><option value="Laboratory">Laboratory</option></select></td>';
      tbody.appendChild(tr);
    }
    if (status) status.textContent = 'Generated '+n+' rows';
    if (proceed) proceed.disabled = true;
    ensureScanEnabled();
    updateSaveEnabled();
  }
  function parseText(t) {
    const lines = t.split(/\r?\n/).map(function(s){ return s.replace(/[–—]/g, "-").replace(/\s+/g, " ").trim(); }).filter(function(s){ return s.length>0; });
    const items = [];
    function normDay(tok) {
      const d = tok.replace(/[^A-Za-z]/g,'').toUpperCase();
      if (d === 'MWF') return 'MWF';
      if (d === 'TTH' || d === 'TT') return 'TTh';
      if (d.startsWith('TH')) return 'Th';
      if (d === 'MW') return 'MW';
      if (d === 'WF') return 'WF';
      if (d === 'M' || d === 'T' || d === 'W' || d === 'F') return d;
      if (d === 'SA') return 'Sa';
      if (d === 'SU') return 'Su';
      return null;
    }
    function isRoom(tok) {
      return /^[A-Za-z]{1,5}[A-Za-z]?\d{2,4}[A-Za-z]?$/.test(tok);
    }
    function findHeaderIndex() {
      for (let i=0;i<lines.length;i++) {
        const u = lines[i].toUpperCase();
        if (u.includes('SECTION') && u.includes('CODE') && u.includes('DESCRIPTION') && u.includes('SCHEDULE')) return i;
      }
      return -1;
    }
    function isFooterLine(s) {
      const u = s.toUpperCase();
      return (u.includes('TOTAL PREPARATIONS') || u.includes('OVERLOAD UNITS') || u.includes('APPROVED'));
    }
    const h = findHeaderIndex();
    const startIndex = h >= 0 ? (h + 1) : 0;
    for (let i=startIndex;i<lines.length;i++) {
      const s = lines[i];
      if (isFooterLine(s)) break;
      const tokens = s.split(' ').filter(function(x){ return x.length>0; });
      let room = null, day = null, roomIdx = -1, dayIdx = -1;
      for (let k=tokens.length-1; k>=0; k--) {
        if (!room && isRoom(tokens[k])) { room = tokens[k]; roomIdx = k; continue; }
        if (room && day === null) {
          const d = normDay(tokens[k]);
          if (d) { day = d; dayIdx = k; break; }
        }
      }
      // fallback: if no room found, pick last token that looks like letters+digits
      if (!room) {
        for (let k=tokens.length-1; k>=0; k--) {
          const tk = tokens[k];
          if (/^[A-Za-z]+[A-Za-z]?\d{2,}[A-Za-z]?$/.test(tk)) { room = tk; roomIdx = k; break; }
        }
      }
      if (!room) { room = ''; }
      if (!day) { day = ''; }
      let tm = s.match(/(\d{1,2}(?::\d{2})?\s*(?:am|pm)?)[\s-]+(\d{1,2}(?::\d{2})?\s*(?:am|pm)?)/i) || s.match(/(\d{1,2}(?::\d{2})?)[\s]*(?:am|pm)?[\s-]+(\d{1,2}(?::\d{2})?)[\s]*(am|pm)/i);
      let time = null;
      if (tm) time = tm[0];
      else {
        const seg = tokens.slice(Math.max(0, dayIdx-5), dayIdx).join(' ');
        const tm2 = seg.match(/(\d{1,2}(?::\d{2})?\s*(?:am|pm)?)[\s-]+(\d{1,2}(?::\d{2})?\s*(?:am|pm)?)/i) || seg.match(/(\d{1,2}(?::\d{2})?)[\s]*(?:am|pm)?[\s-]+(\d{1,2}(?::\d{2})?)[\s]*(am|pm)/i);
        if (tm2) time = tm2[0];
      }
      if (!time) time = '';
      const tStart = time ? s.indexOf(time) : -1;
      let left = '';
      if (tStart > 0) { left = s.slice(0, tStart).trim(); }
      else if (dayIdx >= 0) { left = tokens.slice(0, dayIdx).join(' '); }
      else if (roomIdx >= 0) { left = tokens.slice(0, roomIdx).join(' '); }
      else { left = s; }
      left = left.replace(/\s+/g,' ');
      const parts = left.split(' ');
      if (parts.length < 1) continue;
      const section = parts[0] + (parts[1] ? (' ' + parts[1]) : '');
      let idx = Math.min(2, parts.length);
      const codeTokens = [];
      while (idx < parts.length) {
        const tk = parts[idx];
        if (/^(?:[A-Z]{2,}|[A-Z]{2,}\d+|\d+|PC|CS|IT)$/i.test(tk)) { codeTokens.push(tk); idx++; continue; }
        break;
      }
      const code = codeTokens.join(' ');
      let descTokens = parts.slice(idx);
      while (descTokens.length && /^\d+(?:\.\d+)?$/.test(descTokens[descTokens.length-1])) { descTokens.pop(); }
      const desc = descTokens.join(' ');
      items.push({ section, code, description: desc, time, day, room });
    }
    if (items.length === 0 && lines.length) {
      for (let i=0;i<lines.length;i++) {
        const s = lines[i];
        const tokens = s.split(' ').filter(function(x){ return x.length>0; });
        const amIdx = tokens.findIndex(function(x){ return /\b(?:am|pm)\b/i.test(x); });
        if (amIdx === -1) continue;
        let i1 = amIdx - 1;
        while (i1 >= 0 && !/^\d{1,2}(?::\d{2})?$/.test(tokens[i1])) i1--;
        let i2 = amIdx + 1;
        while (i2 < tokens.length && !/^\d{1,2}(?::\d{2})?$/.test(tokens[i2])) i2++;
        if (i1 < 0 || i2 >= tokens.length) continue;
        const time = tokens[i1] + ' ' + tokens[amIdx].toLowerCase() + ' - ' + tokens[i2] + ' ' + tokens[amIdx].toLowerCase();
        let room = null, day = null;
        for (let k=tokens.length-1; k>=0; k--) {
          if (!room && /^[A-Za-z]{1,5}[A-Za-z]?\d{2,4}[A-Za-z]?$/.test(tokens[k])) { room = tokens[k]; continue; }
          if (room && day === null) {
            const dRaw = tokens[k].replace(/[^A-Za-z]/g,'').toUpperCase();
            const d = dRaw === 'MWF' ? 'MWF' : (dRaw === 'TTH' || dRaw === 'TT' ? 'TTh' : (dRaw.startsWith('TH') ? 'Th' : (['MW','WF','M','T','W','F'].includes(dRaw) ? dRaw : (dRaw==='SA'?'Sa':(dRaw==='SU'?'Su':null)))));
            if (d) { day = d; break; }
          }
        }
        if (!room || !day) continue;
        const left = tokens.slice(0, i1).join(' ').replace(/\s+/g,' ');
        const parts = left.split(' ').filter(function(x){ return x.length>0; });
        if (parts.length < 3) continue;
        const section = parts[0] + (parts[1] ? (' ' + parts[1]) : '');
        let idx = 2;
        const codeTokens = [];
        while (idx < parts.length) { const tk = parts[idx]; if (/^(?:[A-Z]{2,}|[A-Z]{2,}\d+|\d+|PC|CS|IT)$/i.test(tk)) { codeTokens.push(tk); idx++; continue; } break; }
        const code = codeTokens.join(' ');
        const desc = parts.slice(idx).join(' ');
        if (!desc) continue;
        items.push({ section, code, description: desc, time, day, room });
      }
    }
    return items;
  }
  function mergeItems(items) {
    const map = new Map();
    const order = [];
    items.forEach(function(it){
      const k = ((it.section || '') + '\u0001' + (it.code || '') + '\u0001' + (it.description || '')).toUpperCase();
      if (!map.has(k)) {
        order.push(k);
        map.set(k, { section: it.section || '', code: it.code || '', description: it.description || '', times: [], days: [], rooms: [], type: it.type || '' });
      }
      const m = map.get(k);
      const t = (it.time || '').trim();
      const d = (it.day || '').trim();
      const r = (it.room || '').trim();
      if (t && !m.times.includes(t)) m.times.push(t);
      if (d && !m.days.includes(d)) m.days.push(d);
      if (r && !m.rooms.includes(r)) m.rooms.push(r);
    });
    return order.map(function(k){
      const m = map.get(k);
      return {
        section: m.section,
        code: m.code,
        description: m.description,
        time: m.times.join(';'),
        day: m.days.join('').replace(/\s+/g,''),
        room: m.rooms.join(';'),
        type: m.type || 'Lecture'
      };
    });
  }
  function parseCsv(t) {
    const lines = t.split(/\r?\n/).filter(function(s){ return s.trim().length>0; });
    if (!lines.length) return [];
    function countChar(s, ch){ let c=0; for (let i=0;i<s.length;i++) if (s[i]===ch) c++; return c; }
    let delim = ',';
    try { const first = lines[0]; delim = (countChar(first, ',') >= countChar(first, ';')) ? ',' : ';'; } catch (_){}
    function splitLine(s){
      const out = []; let cur = ''; let q = false;
      for (let i=0;i<s.length;i++){
        const ch = s[i];
        if (ch === '"') { if (q && s[i+1] === '"') { cur += '"'; i++; } else { q = !q; } continue; }
        if (ch === delim && !q) { out.push(cur); cur=''; continue; }
        cur += ch;
      }
      out.push(cur);
      return out.map(function(x){ return x.trim(); });
    }
    const header = splitLine(lines[0]).map(function(h){ return h.toLowerCase(); });
    function idx(names){ for (let i=0;i<header.length;i++){ const h = header[i]; for (let j=0;j<names.length;j++){ if (h === names[j]) return i; } } return -1; }
    const iClass = idx(['class','section']);
    const iCode = idx(['class code','class_code','code']);
    const iDesc = idx(['subject description','subject_description','description']);
    const iTime = idx(['time']);
    const iDay = idx(['day']);
    const iRoom = idx(['room']);
    const iType = idx(['type']);
    const iTeacher = idx(['teacher code','teacher_code','teacher']);
    const iSchoolYear = idx(['school year','school_year','schoolyear','schoolyear_id']);
    const items = [];
    for (let li=1; li<lines.length; li++){
      const cols = splitLine(lines[li]);
      if (!cols.length) continue;
      const section = iClass >= 0 ? cols[iClass] : '';
      const code = iCode >= 0 ? cols[iCode] : '';
      const desc = iDesc >= 0 ? cols[iDesc] : '';
      const time = iTime >= 0 ? cols[iTime] : '';
      const day = iDay >= 0 ? cols[iDay].replace(/\s+/g,'') : '';
      const room = iRoom >= 0 ? cols[iRoom] : '';
      const type = iType >= 0 ? cols[iType] : '';
      const teacher_code = iTeacher >= 0 ? cols[iTeacher] : '';
      const schoolyear = iSchoolYear >= 0 ? cols[iSchoolYear] : '';
      if (section || code || desc || time || day || room) {
        items.push({ section, code, description: desc, time, day, room, type, teacher_code, schoolyear });
      }
    }
    return items;
  }
  async function ocrTextFromCanvas(canvas) {
    const white = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789:-/ ,._()';
    async function run(psm) {
      try {
        const r = await Tesseract.recognize(canvas, 'eng', { psm: psm, tessedit_char_whitelist: white });
        return (r && r.data && r.data.text) ? r.data.text : '';
      } catch (_e) { return ''; }
    }
    let text = await run(6);
    if ((text || '').trim().length === 0) { text = await run(11); }
    if ((text || '').trim().length === 0) {
      try {
        const worker = await Tesseract.createWorker({
          workerPath: 'https://cdn.jsdelivr.net/npm/tesseract.js@4.1.1/dist/worker.min.js',
          corePath: 'https://cdn.jsdelivr.net/npm/tesseract.js-core@4.0.3/tesseract-core.wasm.js',
          langPath: 'https://tessdata.projectnaptha.com/4.0.0'
        });
        await worker.loadLanguage('eng');
        await worker.initialize('eng');
        const r3 = await worker.recognize(canvas, { psm: 6, tessedit_char_whitelist: white });
        text = (r3 && r3.data && r3.data.text) ? r3.data.text : '';
        if ((text || '').trim().length === 0) {
          const r4 = await worker.recognize(canvas, { psm: 11, tessedit_char_whitelist: white });
          text = (r4 && r4.data && r4.data.text) ? r4.data.text : '';
        }
        await worker.terminate();
      } catch (__e) {}
    }
    return text;
  }
  function renderPreview(items) {
    tbody.innerHTML = '';
    items.forEach(it=>{
      const tr = document.createElement('tr');
      const subj = (it.code ? (it.code + ' - ') : '') + (it.description || '');
      tr.innerHTML =
        '<td><input class="form-control form-control-sm sc-code" value=""></td>'+
        '<td><input class="form-control form-control-sm sc-subj" value="'+subj+'"></td>'+
        '<td><input class="form-control form-control-sm sc-time" value="'+(it.time || '')+'"></td>'+
        '<td><input class="form-control form-control-sm sc-day" value="'+(it.day || '')+'"></td>'+
        '<td><input class="form-control form-control-sm sc-room" value="'+(it.room || '')+'"></td>'+
        '<td><input class="form-control form-control-sm sc-class" value="'+(it.section || '')+'"></td>'+
        '<td><select class="form-select form-select-sm sc-type"><option value=""></option><option value="Lecture">Lecture</option><option value="Laboratory">Laboratory</option></select></td>';
      tbody.appendChild(tr);
    });
    updateSaveEnabled();
  }
  function renderBulk(items) {
    bulkTbody.innerHTML = '';
    items.forEach((it, i)=>{
      const tr = document.createElement('tr');
      const subj = (it.code ? (it.code + ' - ') : '') + (it.description || '');
      const t = 'Lecture';
      tr.innerHTML =
        '<td><input class="form-control form-control-sm" value="'+teacherCode+'"></td>'+
        '<td><input class="form-control form-control-sm" value="'+subj+'"></td>'+
        '<td><input class="form-control form-control-sm" value="'+it.time+'"></td>'+
        '<td><input class="form-control form-control-sm" value="'+it.day+'"></td>'+
        '<td><input class="form-control form-control-sm" value="'+it.room+'"></td>'+
        '<td><input class="form-control form-control-sm" value="'+it.section+'"></td>'+
        '<td><select class="form-select form-select-sm">'+
          '<option value=""></option>'+
          '<option value="Lecture"'+(t==='Lecture'?' selected':'')+'>Lecture</option>'+
          '<option value="Laboratory"'+(t==='Laboratory'?' selected':'')+'>Laboratory</option>'+
        '</select></td>';
      bulkTbody.appendChild(tr);
    });
  }
  function setMergedText(items) {
    if (!mergedBox) return;
    const sySel = document.getElementById('bulkSySelect');
    const syVal = sySel ? (sySel.value || '') : '';
    const data = items.map(function(it){
      return {
        teacher_code: teacherCode,
        schoolyear: syVal,
        class: (it.section || ''),
        class_code: teacherCode,
        subject_description: (it.subject_description ? it.subject_description : ((it.code ? (it.code + ' - ') : '') + (it.description || ''))),
        time: (it.time || ''),
        day: (it.day || '').replace(/\s+/g,''),
        room: (it.room || ''),
        type: 'Lecture'
      };
    });
    mergedBox.value = JSON.stringify(data, null, 2);
  }
  if (start) {
    // toggle scan button style when file is selected
    function setReadyStyle() {
      start.classList.remove('btn-outline-secondary','btn-primary','btn-success');
      start.classList.add('btn-warning');
      start.disabled = false;
    }
    function setIdleStyle() {
      start.classList.remove('btn-warning','btn-primary','btn-success');
      start.classList.add('btn-outline-secondary');
      start.disabled = true;
    }
    // Generate button removed; scan handles generation
    if (file) {
      file.addEventListener('change', function(){
        if (!file.files || file.files.length === 0) { setIdleStyle(); status.textContent = ''; return; }
        const f = file.files[0];
        const name = (f.name || '').toLowerCase();
        const allowed = (name.endsWith('.jpg') || name.endsWith('.jpeg'));
        if (allowed) { status.textContent = 'File selected: ' + f.name + ' — click Scan'; }
        else { status.textContent = 'Unsupported file type'; }
        ensureScanEnabled();
      });
    }
    ensureScanEnabled();
  document.addEventListener('bulkFileUploaded', function(e){
    ensureScanEnabled();
    if (status) status.textContent = 'File ready: ' + ((e && e.detail && e.detail.name) ? e.detail.name : '');
  });
    start.addEventListener('click', async function(){
      const name = (uploadInput && uploadInput.files && uploadInput.files[0] ? (uploadInput.files[0].name || '') : '').toLowerCase();
      const allowed = (name.endsWith('.jpg') || name.endsWith('.jpeg'));
      if (!allowed) { status.textContent = 'Unsupported file type (JPG only)'; return; }
      if (!window.currentUploadUrl) {
        if (!uploadInput || !uploadInput.files || uploadInput.files.length === 0) { status.textContent = 'No document has been uploaded'; return; }
        const f = uploadInput.files[0];
        const fd = new FormData();
        fd.append('op','upload_scan');
        fd.append('csrf','<?= htmlspecialchars(csrf_token()) ?>');
        fd.append('file', f);
        status.textContent = 'Uploading...';
        const res = await fetch(location.pathname + location.search, { method:'POST', body: fd }).then(x=>x.json()).catch(()=>({ok:false,error:'Network error'}));
        if (!res || !res.ok) { status.textContent = (res && res.error) ? res.error : 'Upload failed'; return; }
        window.currentUploadUrl = res.url || null;
        uploadPreview.style.display = "";
        var embed = document.getElementById("uploadEmbed");
        if (embed) embed.innerHTML = window.currentUploadUrl ? ('<img src="'+window.currentUploadUrl+'" class="img-fluid border rounded" alt="Uploaded image preview">') : '';
      }
      status.textContent = 'Scanning...';
      if (proceed) proceed.disabled = true;
      parsed = [];
      try {
        let text = '';
        const pv = document.getElementById('scanCanvasPreview');
        if (pv) { pv.width = 0; pv.height = 0; }
        {
          const img = new Image();
          await new Promise((resolve,reject)=>{ img.onload=resolve; img.onerror=reject; img.src=window.currentUploadUrl; });
          const canvas = document.createElement('canvas');
          const scale = (img.naturalWidth < 1000 || img.naturalHeight < 1000) ? 3 : 2;
          canvas.width = img.naturalWidth * scale;
          canvas.height = img.naturalHeight * scale;
          const ctx = canvas.getContext('2d');
          ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
          if (pv) {
            pv.width = img.naturalWidth;
            pv.height = img.naturalHeight;
            pv.getContext('2d').drawImage(img, 0, 0, pv.width, pv.height);
          }
          const imgData = ctx.getImageData(0, 0, canvas.width, canvas.height);
          const d = imgData.data;
          for (let i=0;i<d.length;i+=4) {
            const g = 0.2126*d[i] + 0.7152*d[i+1] + 0.0722*d[i+2];
            const v = g > 200 ? 255 : (g < 40 ? 0 : g);
            d[i]=d[i+1]=d[i+2]=v;
          }
          ctx.putImageData(imgData, 0, 0);
          text = pv ? await ocrTextFromCanvas(pv) : await ocrTextFromCanvas(canvas);
        }
        // Continue even if OCR text is empty
        parsed = parseText(text);
        parsed = mergeItems(parsed);
        setMergedText(parsed);
        updateSaveEnabled();
        renderBulk(parsed);
        status.textContent = parsed.length ? ('Scanned and loaded '+parsed.length+' merged rows') : 'No rows detected';
      } catch (e) {
        status.textContent = 'Scan failed: ' + (e && e.message ? e.message : 'Unknown error');
        try { console.error(e); } catch (_){}
      }
    });
  tbody.addEventListener('input', function(){ updateSaveEnabled(); });
  }
  if (proceed) {
    proceed.addEventListener('click', function(){
      const m = bootstrap.Modal.getOrCreateInstance(document.getElementById('bulkClassModal'));
      const rows = [];
      tbody.querySelectorAll('tr').forEach(function(tr){
        const code = tr.querySelector('.sc-code') ? tr.querySelector('.sc-code').value.trim() : '';
        const cls = tr.querySelector('.sc-class') ? tr.querySelector('.sc-class').value.trim() : '';
        const subj = tr.querySelector('.sc-subj') ? tr.querySelector('.sc-subj').value.trim() : '';
        const time = tr.querySelector('.sc-time') ? tr.querySelector('.sc-time').value.trim() : '';
        const day = tr.querySelector('.sc-day') ? tr.querySelector('.sc-day').value.trim() : '';
        const room = tr.querySelector('.sc-room') ? tr.querySelector('.sc-room').value.trim() : '';
        const typeSel = tr.querySelector('.sc-type'); 
        const type = typeSel ? (typeSel.value || '').trim() : '';
        let desc = subj;
        if (code && subj.startsWith(code+' - ')) { desc = subj.slice((code+' - ').length); }
        else if (subj.includes(' - ')) { desc = subj.split(' - ').slice(1).join(' - ').trim(); }
        if (cls && subj && time && day && room) {
          rows.push({ section: cls, code: code, description: desc, time: time, day: day, room: room, type: type });
        }
      });
      renderBulk(rows);
      m.show();
    });
  }
  const bulkForm = document.getElementById('bulkClassForm');
  if (bulkForm) {
    bulkForm.addEventListener('submit', function(e){
      const rows = [];
      bulkTbody.querySelectorAll('tr').forEach(function(tr){
        const inputs = tr.querySelectorAll('input.form-control');
        const selects = tr.querySelectorAll('select.form-select');
        const code = inputs[0].value.trim();
        const subj = inputs[1].value.trim();
        const time = inputs[2].value.trim();
        const day = inputs[3].value.trim();
        const room = inputs[4].value.trim();
        const cls = inputs[5].value.trim();
        const type = selects[0] ? (selects[0].value || '').trim() : '';
        if (cls && subj && time && day && room) {
          rows.push({ class: cls, class_code: code, subject_description: subj, time: time, day: day, room: room, type: type });
        }
      });
      document.getElementById('bulkItems').value = JSON.stringify(rows);
    });
  }
})();
</script>
<footer class="text-center text-muted small py-3 border-top mt-auto">
  <div class="container">© 2026 Attendance Tracker | Developed by: Von P. Gabayan Jr.</div>
</footer>
<script>
(function(){
  if (location.search) {
    history.replaceState(null, document.title, location.pathname);
  }
})();
</script>
</body>
</html>
