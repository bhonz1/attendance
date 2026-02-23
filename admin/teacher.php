<?php
require_once __DIR__ . "/../lib/admin.php";
require_once __DIR__ . "/../lib/supabase.php";
require_admin_session();
$msg = null; $err = null; $msgReg = null; $errReg = null;
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
$useSupabase = sb_url() ? true : false;
$__ROLE_TEACHER = 2;
$__did_bootstrap_authno = false;
if ($useSupabase && !$__did_bootstrap_authno) {
  sb_patch("teacher_registry", ["authno" => $__ROLE_TEACHER], ["authno" => "is.null"]);
  sb_patch("teacher_registry", ["authno" => $__ROLE_TEACHER], ["authno" => "neq." . $__ROLE_TEACHER]);
  $__did_bootstrap_authno = true;
}
$syOptions = $useSupabase ? sb_get("school_years", ["select" => "id,code,description", "order" => "id.desc"]) : ($_SESSION["__school_years_meta"] ?? []);
if (!is_array($syOptions)) $syOptions = [];
$colleges = $useSupabase ? sb_get("colleges", ["select" => "id,description", "order" => "description.asc"]) : ($_SESSION["__colleges_meta"] ?? []);
if (!is_array($colleges)) $colleges = [];
$institutions = $useSupabase ? sb_get("institutions", ["select" => "id,description", "order" => "description.asc"]) : ($_SESSION["__institutions_meta"] ?? []);
if (!is_array($institutions)) $institutions = [];
$teacherRecords = $useSupabase ? sb_get("teacher_registry", ["select" => "*", "order" => "id.desc"]) : ($_SESSION["__teacher_registry"] ?? []);
if (!is_array($teacherRecords)) $teacherRecords = [];
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["reg_action"])) {
  $act = $_POST["reg_action"];
  if ($act === "create") {
    $code = trim($_POST["code"] ?? "");
    $full_name = trim($_POST["full_name"] ?? "");
    $username = trim($_POST["username"] ?? "");
    $department = trim($_POST["department"] ?? "");
    $institution = trim($_POST["institution"] ?? "");
    $sy_ids = $_POST["school_year_ids"] ?? [];
    if (!is_array($sy_ids)) $sy_ids = [];
    $password = $_POST["password"] ?? "";
    $pwd_enc = null;
    if (is_string($password) && strlen($password) >= 6) {
      $secret = getenv("TEACHER_PW_SECRET") ?: "";
      if ($secret) {
        $key = hash("sha256", $secret, true);
        $iv = random_bytes(16);
        $ct = openssl_encrypt($password, "AES-256-CBC", $key, OPENSSL_RAW_DATA, $iv);
        if ($ct !== false) $pwd_enc = base64_encode($iv) . ":" . base64_encode($ct);
      }
    }
    $photoUrl = null;
    if (isset($_FILES["photo"]) && is_array($_FILES["photo"]) && ($_FILES["photo"]["error"] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
      $size = (int)($_FILES["photo"]["size"] ?? 0);
      $tmp = $_FILES["photo"]["tmp_name"] ?? "";
      $name = $_FILES["photo"]["name"] ?? "";
      $f = finfo_open(FILEINFO_MIME_TYPE);
      $mime = $f ? finfo_file($f, $tmp) : null;
      if ($f) finfo_close($f);
      $okMime = in_array($mime, ["image/jpeg", "image/png", "image/webp"]);
      if ($okMime && $size > 0 && $size <= 5242880 && is_uploaded_file($tmp)) {
        $uploadDir = __DIR__ . "/../uploads/teachers";
        if (!is_dir($uploadDir)) { @mkdir($uploadDir, 0775, true); }
        $baseUser = strtolower(trim($username));
        $baseUser = preg_replace("/[^a-z0-9\\-_]/", "", $baseUser) ?: ("user_" . uniqid());
        $target = $uploadDir . "/" . $baseUser . ".jpg";
        @unlink($target);
        $ok = false;
        if ($mime === "image/jpeg") {
          $ok = @move_uploaded_file($tmp, $target);
        } else if ($mime === "image/png" && function_exists("imagecreatefrompng") && function_exists("imagejpeg")) {
          $im = @imagecreatefrompng($tmp);
          if ($im) { $ok = @imagejpeg($im, $target, 85); imagedestroy($im); }
        } else if ($mime === "image/webp" && function_exists("imagecreatefromwebp") && function_exists("imagejpeg")) {
          $im = @imagecreatefromwebp($tmp);
          if ($im) { $ok = @imagejpeg($im, $target, 85); imagedestroy($im); }
        }
        if ($ok) { $photoUrl = "/uploads/teachers/" . $baseUser . ".jpg"; }
      }
    }
    if (!$code || !$full_name || !$username) { $errReg = "Code, full name, username required"; }
    else if (!$pwd_enc) { $errReg = "Password required (min 6 characters)"; }
    else if (!$photoUrl) { $errReg = "Photo required"; }
    else if (!is_string($department) || trim($department) === "" || !is_string($institution) || trim($institution) === "") { $errReg = "College and institution required"; }
    else if (!is_array($sy_ids) || count(array_filter($sy_ids, function($v){ return is_string($v) && trim($v) !== ""; })) === 0) { $errReg = "Select at least one school year"; }
    else {
      if ($useSupabase) {
        $body = ["code" => $code, "full_name" => $full_name, "department" => $department, "institution" => $institution, "school_year_ids" => $sy_ids, "authno" => $__ROLE_TEACHER];
        if ($username) $body["username"] = $username;
        if ($pwd_enc) $body["password_enc"] = $pwd_enc;
        if ($photoUrl) $body["photo_url"] = $photoUrl;
        $r = sb_post("teacher_registry", $body);
        if ($r !== null) { $msgReg = "Teacher recorded"; }
        else {
          $srv = getenv("SUPABASE_SERVICE_ROLE") ?: "";
          $errReg = $srv ? "Create failed" : "Write blocked by RLS; add SUPABASE_SERVICE_ROLE or enable anon write policies";
        }
      } else {
        if (!isset($_SESSION["__teacher_registry"]) || !is_array($_SESSION["__teacher_registry"])) $_SESSION["__teacher_registry"] = [];
        $id = uniqid("tr_", true);
        $_SESSION["__teacher_registry"][] = ["id" => $id, "code" => $code, "full_name" => $full_name, "username" => $username, "photo_url" => $photoUrl, "password_enc" => $pwd_enc, "department" => $department, "institution" => $institution, "school_year_ids" => $sy_ids, "authno" => $__ROLE_TEACHER];
        $msgReg = "Teacher recorded";
      }
    }
  } elseif ($act === "update") {
    $id = $_POST["id"] ?? "";
    $code = trim($_POST["code"] ?? "");
    $full_name = trim($_POST["full_name"] ?? "");
    $username = trim($_POST["username"] ?? "");
    $department = trim($_POST["department"] ?? "");
    $institution = trim($_POST["institution"] ?? "");
    $sy_ids = $_POST["school_year_ids"] ?? [];
    if (!is_array($sy_ids)) $sy_ids = [];
    $password = $_POST["password"] ?? "";
    $pwd_enc = null;
    if (is_string($password) && strlen($password) >= 6) {
      $secret = getenv("TEACHER_PW_SECRET") ?: "";
      if ($secret) {
        $key = hash("sha256", $secret, true);
        $iv = random_bytes(16);
        $ct = openssl_encrypt($password, "AES-256-CBC", $key, OPENSSL_RAW_DATA, $iv);
        if ($ct !== false) $pwd_enc = base64_encode($iv) . ":" . base64_encode($ct);
      }
    }
    $photoUrl = null;
    if (isset($_FILES["photo"]) && is_array($_FILES["photo"]) && ($_FILES["photo"]["error"] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
      $size = (int)($_FILES["photo"]["size"] ?? 0);
      $tmp = $_FILES["photo"]["tmp_name"] ?? "";
      $name = $_FILES["photo"]["name"] ?? "";
      $f = finfo_open(FILEINFO_MIME_TYPE);
      $mime = $f ? finfo_file($f, $tmp) : null;
      if ($f) finfo_close($f);
      $okMime = in_array($mime, ["image/jpeg", "image/png", "image/webp"]);
      if ($okMime && $size > 0 && $size <= 5242880 && is_uploaded_file($tmp)) {
        $uploadDir = __DIR__ . "/../uploads/teachers";
        if (!is_dir($uploadDir)) { @mkdir($uploadDir, 0775, true); }
        $baseUser = strtolower(trim($username));
        $baseUser = preg_replace("/[^a-z0-9\\-_]/", "", $baseUser) ?: ("user_" . uniqid());
        $target = $uploadDir . "/" . $baseUser . ".jpg";
        @unlink($target);
        $ok = false;
        if ($mime === "image/jpeg") {
          $ok = @move_uploaded_file($tmp, $target);
        } else if ($mime === "image/png" && function_exists("imagecreatefrompng") && function_exists("imagejpeg")) {
          $im = @imagecreatefrompng($tmp);
          if ($im) { $ok = @imagejpeg($im, $target, 85); imagedestroy($im); }
        } else if ($mime === "image/webp" && function_exists("imagecreatefromwebp") && function_exists("imagejpeg")) {
          $im = @imagecreatefromwebp($tmp);
          if ($im) { $ok = @imagejpeg($im, $target, 85); imagedestroy($im); }
        }
        if ($ok) { $photoUrl = "/uploads/teachers/" . $baseUser . ".jpg"; }
      }
    }
    if (!$id || !$code || !$full_name) { $errReg = "ID, code, full name required"; }
    else {
      if ($useSupabase) {
        $body = ["code" => $code, "full_name" => $full_name, "department" => $department, "institution" => $institution, "school_year_ids" => $sy_ids, "authno" => $__ROLE_TEACHER];
        if ($username !== "") $body["username"] = $username;
        if ($pwd_enc) $body["password_enc"] = $pwd_enc;
        if ($photoUrl) $body["photo_url"] = $photoUrl;
        $r = sb_patch("teacher_registry", $body, ["id" => "eq." . $id]);
        if ($r !== null) { $msgReg = "Teacher updated"; }
        else {
          $srv = getenv("SUPABASE_SERVICE_ROLE") ?: "";
          $errReg = $srv ? "Update failed" : "Write blocked by RLS; add SUPABASE_SERVICE_ROLE or enable anon write policies";
        }
      } else {
        if (isset($_SESSION["__teacher_registry"]) && is_array($_SESSION["__teacher_registry"])) {
          foreach ($_SESSION["__teacher_registry"] as &$row) {
            if (($row["id"] ?? "") === $id) { 
              $row["code"] = $code; 
              $row["full_name"] = $full_name; 
              if ($username !== "") $row["username"] = $username; 
              if ($pwd_enc) $row["password_enc"] = $pwd_enc; 
              if ($photoUrl) {
                $row["photo_url"] = $photoUrl; 
              }
              $row["department"] = $department; 
              $row["institution"] = $institution; 
              $row["school_year_ids"] = $sy_ids;
              $row["authno"] = $__ROLE_TEACHER;
              $msgReg = "Teacher updated"; 
              break; 
            }
          }
        }
      }
    }
  } elseif ($act === "delete") {
    $id = $_POST["id"] ?? "";
    if (!$id) { $errReg = "ID required"; }
    else {
      if ($useSupabase) {
        $has = false;
        $photoToDelete = null;
        $rec = sb_get("teacher_registry", ["select" => "photo_url", "id" => "eq." . $id, "limit" => 1]);
        if (is_array($rec) && isset($rec[0]) && is_string($rec[0]["photo_url"] ?? null)) $photoToDelete = $rec[0]["photo_url"];
        $q1 = sb_get("class_schedule", ["select" => "id", "teacher_id" => "eq." . $id, "limit" => 1]);
        if (is_array($q1) && count($q1) > 0) $has = true;
        if (!$has) {
          $q2 = sb_get("class_schedules", ["select" => "id", "teacher_id" => "eq." . $id, "limit" => 1]);
          if (is_array($q2) && count($q2) > 0) $has = true;
        }
        if (!$has) {
          $q3 = sb_get("schedules", ["select" => "id", "teacher_id" => "eq." . $id, "limit" => 1]);
          if (is_array($q3) && count($q3) > 0) $has = true;
        }
        if ($has) {
          $errReg = "Cannot delete teacher with existing class schedules";
        } else {
          $r = sb_delete("teacher_registry", ["id" => "eq." . $id]);
          if ($r !== null) { 
            $msgReg = "Teacher deleted"; 
            if (is_string($photoToDelete) && strpos($photoToDelete, "/uploads/teachers/") === 0) {
              $base = realpath(__DIR__ . "/../uploads/teachers");
              $p = realpath(__DIR__ . "/.." . $photoToDelete);
              if ($base && $p && strpos($p, $base) === 0 && is_file($p)) { @unlink($p); }
            }
          } else {
            $srv = getenv("SUPABASE_SERVICE_ROLE") ?: "";
            $errReg = $srv ? "Delete failed" : "Write blocked by RLS; add SUPABASE_SERVICE_ROLE or enable anon delete policies";
          }
        }
      } else {
        $has = false;
        $photoToDelete = null;
        if (isset($_SESSION["__class_schedules"]) && is_array($_SESSION["__class_schedules"])) {
          foreach ($_SESSION["__class_schedules"] as $row) {
            if (($row["teacher_id"] ?? null) === $id) { $has = true; break; }
          }
        }
        if ($has) {
          $errReg = "Cannot delete teacher with existing class schedules";
        } else {
          if (isset($_SESSION["__teacher_registry"]) && is_array($_SESSION["__teacher_registry"])) {
            foreach ($_SESSION["__teacher_registry"] as $row) {
              if (($row["id"] ?? "") === $id) { $photoToDelete = is_string($row["photo_url"] ?? null) ? $row["photo_url"] : null; break; }
            }
            $_SESSION["__teacher_registry"] = array_values(array_filter($_SESSION["__teacher_registry"], function($row) use ($id) { return ($row["id"] ?? "") !== $id; }));
            $msgReg = "Teacher deleted";
            if (is_string($photoToDelete) && strpos($photoToDelete, "/uploads/teachers/") === 0) {
              $base = realpath(__DIR__ . "/../uploads/teachers");
              $p = realpath(__DIR__ . "/.." . $photoToDelete);
              if ($base && $p && strpos($p, $base) === 0 && is_file($p)) { @unlink($p); }
            }
          }
        }
      }
    }
  }
  $teacherRecords = $useSupabase ? sb_get("teacher_registry", ["select" => "*", "order" => "id.desc"]) : ($_SESSION["__teacher_registry"] ?? []);
  if (!is_array($teacherRecords)) $teacherRecords = [];
}
 
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Admin Teacher</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
.t-hero { position: relative; padding: 28px 0; background: linear-gradient(120deg, #0ea5e9 0%, #6366f1 40%, #0f172a 100%); color: #ffffff; border-bottom: 1px solid rgba(255,255,255,0.18); }
.t-hero__title { font-weight: 800; line-height: 1.1; font-size: clamp(24px, 5.5vw, 40px); letter-spacing: -0.01em; }
.t-hero__subtitle { opacity: 0.92; font-size: 15px; }
.quick-card { border: none; border-radius: 14px; box-shadow: 0 8px 20px rgba(2,6,23,0.06); transition: transform .18s ease, box-shadow .18s ease; }
.quick-card:hover { transform: translateY(-1px); box-shadow: 0 12px 28px rgba(2,6,23,0.12); }
.metric-card { border: none; border-radius: 14px; box-shadow: 0 10px 24px rgba(2,6,23,0.08); }
.metric-title { font-size: 13px; text-transform: uppercase; letter-spacing: 0.08em; color: #64748b; }
.metric-value { font-size: 22px; font-weight: 800; color: #0f172a; }
.table thead th { background: #f8fafc; }
.text-break { word-break: break-word; overflow-wrap: anywhere; }
.btn-view { background:#ffffff; color:#0f172a; border:1px solid #f59e0b; }
.btn-view:hover { background:#ffc107; color:#0f172a; border-color:#ffc107; }
</style>
</head>
<body class="d-flex flex-column min-vh-100">
<?php $admin_nav_active = "teacher"; include __DIR__ . "/admin_nav.php"; ?>
<div class="t-hero">
  <div class="container">
    <div class="d-flex align-items-center justify-content-between">
      <div>
        <div class="t-hero__title">Teacher Administration</div>
        <div class="t-hero__subtitle">Manage records, accounts, and subscriptions</div>
      </div>
    </div>
  </div>
</div>
<div class="container py-4">
<div class="d-flex justify-content-between align-items-center mb-3">
<h4 class="mb-0">Teacher Records</h4>
</div>
<?php if ($msgReg): ?><div class="alert alert-success"><?= htmlspecialchars($msgReg) ?></div><?php endif; ?>
<?php if ($errReg): ?><div class="alert alert-danger"><?= htmlspecialchars($errReg) ?></div><?php endif; ?>
 
<div class="card quick-card mb-4"><div class="card-body">
<div class="table-responsive">
<table class="table table-sm table-hover table-striped align-middle">
<thead><tr>
  <th style="width:10%">Photo</th>
  <th style="width:12%">Code</th>
  <th style="width:42%">Full Name</th>
  <th style="width:30%">Actions</th>
  <th class="text-end" style="width:6%">
    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#trCreateModal" aria-label="Add Teacher Record" title="Add Teacher Record">+</button>
  </th>
</tr></thead>
<tbody>
<?php foreach ($teacherRecords as $row): 
  $sel = $row["school_year_ids"] ?? []; 
  if (is_string($sel)) { $tmp = json_decode($sel, true); if (is_array($tmp)) $sel = $tmp; }
  $labels = [];
  foreach ($syOptions as $sy) {
    $optId = ($sy["id"] ?? ($sy["code"] ?? ""));
    $label = ($sy["code"] ?? "") ? ($sy["code"] . " — " . ($sy["description"] ?? "")) : (($sy["description"] ?? "") ?: (($sy["start_date"] ?? "") . " - " . ($sy["end_date"] ?? "")));
    if (in_array($optId, is_array($sel) ? $sel : [])) $labels[] = $label;
  }
  $syText = implode(", ", $labels);
  $rid = htmlspecialchars($row["id"] ?? "");
?>
<tr>
  <td><?php $p = $row["photo_url"] ?? null; if ($p): ?><img src="<?= htmlspecialchars($p) ?>" alt="Photo" style="width:42px;height:42px;object-fit:cover;border-radius:50%;border:1px solid #e2e8f0"><?php else: ?>—<?php endif; ?></td>
  <td><?= htmlspecialchars($row["code"] ?? "") ?></td>
  <td><?= htmlspecialchars($row["full_name"] ?? "") ?></td>
  <td>
    <div class="d-flex gap-2">
      <a class="btn btn-sm btn-view" href="/admin/view_teacher.php?id=<?= $rid ?>">View</a>
      <button class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#trEditModal_<?= $rid ?>">Edit</button>
      <button class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#trDeleteModal_<?= $rid ?>">Delete</button>
    </div>
  </td>
  <td></td>
</tr>
<?php endforeach; ?>
<?php if (count($teacherRecords) === 0): ?>
<tr><td colspan="5" class="text-muted">No teacher records</td></tr>
<?php endif; ?>
</tbody>
</table>
</div>
</div></div>
<?php foreach ($teacherRecords as $row): 
  $rid = htmlspecialchars($row["id"] ?? ""); 
  $sel = $row["school_year_ids"] ?? []; 
  if (is_string($sel)) { $tmp = json_decode($sel, true); if (is_array($tmp)) $sel = $tmp; }
?>
<div class="modal fade" id="trEditModal_<?= $rid ?>" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title">Edit Teacher Record</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
      <div class="modal-body">
        <form id="trEditForm_<?= $rid ?>" method="post" enctype="multipart/form-data">
          <input type="hidden" name="reg_action" value="update">
          <input type="hidden" name="id" value="<?= $rid ?>">
          <div class="row g-3">
            <div class="col-12 col-md-4"><label class="form-label">Teacher Code</label><input type="text" name="code" class="form-control" value="<?= htmlspecialchars($row["code"] ?? "") ?>" required></div>
            <div class="col-12 col-md-8"><label class="form-label">Full Name</label><input type="text" name="full_name" class="form-control" value="<?= htmlspecialchars($row["full_name"] ?? "") ?>" required></div>
          </div>
          <div class="row g-3 mt-1">
            <div class="col-12 col-md-6"><label class="form-label">Username</label><input type="text" name="username" class="form-control" value="<?= htmlspecialchars($row["username"] ?? "") ?>" placeholder="username/email"></div>
            <div class="col-12 col-md-6"><label class="form-label">Password</label><input type="password" name="password" class="form-control" placeholder="reset password"></div>
          </div>
          <div class="row g-3 mt-1">
            <div class="col-12 col-md-6">
              <label class="form-label">Photo</label>
              <input type="file" name="photo" class="form-control" accept="image/*">
            </div>
            <div class="col-12 col-md-6">
              <?php $p = $row["photo_url"] ?? null; if ($p): ?>
                <img src="<?= htmlspecialchars($p) ?>" alt="Current Photo" style="width:60px;height:60px;object-fit:cover;border-radius:8px;border:1px solid #e2e8f0">
              <?php endif; ?>
            </div>
          </div>
          <div class="row g-3 mt-1">
            <div class="col-12 col-md-6">
              <label class="form-label">Institution</label>
              <select name="institution" class="form-select" id="instSelect_<?= $rid ?>">
                <?php foreach ($institutions as $it): $txt = $it["descriptions"] ?? ($it["description"] ?? ""); $val = $txt ?: ""; $selVal = $row["institution"] ?? ""; ?>
                  <option value="<?= htmlspecialchars($val) ?>" <?= ($selVal === $val ? "selected" : "") ?>><?= htmlspecialchars($val) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-12 col-md-6">
              <label class="form-label">College</label>
              <select name="department" class="form-select" id="coSelect_<?= $rid ?>">
                <?php foreach ($colleges as $co): $txt = $co["descriptions"] ?? ($co["description"] ?? ""); $val = $co["code"] ?? ""; $selVal = $row["department"] ?? ""; ?>
                  <option value="<?= htmlspecialchars($val) ?>" <?= ($selVal === $val ? "selected" : "") ?>><?= htmlspecialchars($txt ?: $val) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <div class="mt-3"><label class="form-label">Subscription</label>
            <div class="d-flex flex-column" style="max-height:220px; overflow:auto">
              <?php foreach ($syOptions as $sy): 
                $optId = ($sy["id"] ?? ($sy["code"] ?? "")); 
                $label = ($sy["code"] ?? "") ? ($sy["code"] . " — " . ($sy["description"] ?? "")) : (($sy["description"] ?? "") ?: (($sy["start_date"] ?? "") . " - " . ($sy["end_date"] ?? "")));
                $checked = in_array($optId, is_array($sel) ? $sel : []);
              ?>
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" name="school_year_ids[]" value="<?= htmlspecialchars($optId) ?>" <?= $checked ? "checked" : "" ?>>
                  <label class="form-check-label"><?= htmlspecialchars($label) ?></label>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        <button type="submit" form="trEditForm_<?= $rid ?>" class="btn btn-primary">Save</button>
      </div>
    </div>
  </div>
</div>
<div class="modal fade" id="trDeleteModal_<?= $rid ?>" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title">Delete Teacher Record</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
      <div class="modal-body">
        <form id="trDeleteForm_<?= $rid ?>" method="post">
          <input type="hidden" name="reg_action" value="delete">
          <input type="hidden" name="id" value="<?= $rid ?>">
          <p class="mb-0">Are you sure you want to delete this teacher record?</p>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        <button type="submit" form="trDeleteForm_<?= $rid ?>" class="btn btn-danger">Delete</button>
      </div>
    </div>
  </div>
</div>
<?php endforeach; ?>
<div class="modal fade" id="trCreateModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title">Add Teacher Record</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
      <div class="modal-body">
        <form id="trCreateForm" method="post" enctype="multipart/form-data">
          <input type="hidden" name="reg_action" value="create">
          <div class="row g-3">
            <div class="col-12 col-md-4"><label class="form-label">Teacher Code</label><input type="text" name="code" class="form-control" required></div>
            <div class="col-12 col-md-8"><label class="form-label">Full Name</label><input type="text" name="full_name" class="form-control" required></div>
          </div>
          <div class="row g-3 mt-1">
            <div class="col-12 col-md-6"><label class="form-label">Username</label><input type="text" name="username" class="form-control" placeholder="username/email" required></div>
            <div class="col-12 col-md-6"><label class="form-label">Password</label><input type="password" name="password" class="form-control" placeholder="at least 6 characters" required></div>
          </div>
          <div class="row g-3 mt-1">
            <div class="col-12 col-md-6"><label class="form-label">Photo</label><input type="file" name="photo" class="form-control" accept="image/*" required></div>
          </div>
          <div class="row g-3 mt-1">
            <div class="col-12 col-md-6">
              <label class="form-label">Institution</label>
              <select name="institution" class="form-select" id="instSelect" required>
                <?php foreach ($institutions as $it): $txt = $it["descriptions"] ?? ($it["description"] ?? ""); $val = $txt ?: ""; ?>
                  <option value="<?= htmlspecialchars($val) ?>"><?= htmlspecialchars($val) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-12 col-md-6">
              <label class="form-label">College</label>
              <select name="department" class="form-select" id="coSelect" required>
                <?php foreach ($colleges as $co): $txt = $co["descriptions"] ?? ($co["description"] ?? ""); $val = $txt ?: ""; ?>
                  <option value="<?= htmlspecialchars($val) ?>"><?= htmlspecialchars($val) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <div class="mt-3"><label class="form-label">School Year Subscription</label>
            <div class="d-flex flex-column" style="max-height:220px; overflow:auto">
              <?php foreach ($syOptions as $sy): $optId = ($sy["id"] ?? ($sy["code"] ?? "")); $label = ($sy["code"] ?? "") ? ($sy["code"] . " — " . ($sy["description"] ?? "")) : (($sy["description"] ?? "") ?: (($sy["start_date"] ?? "") . " - " . ($sy["end_date"] ?? ""))); ?>
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" name="school_year_ids[]" value="<?= htmlspecialchars($optId) ?>">
                  <label class="form-check-label"><?= htmlspecialchars($label) ?></label>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        <button type="submit" form="trCreateForm" class="btn btn-primary">Record</button>
      </div>
    </div>
  </div>
</div>
 
</div>
<footer class="text-center text-muted small py-3 border-top mt-auto">
  <div class="container">© 2026 Attendance Tracker | Developed by: Von P. Gabayan Jr.</div>
</footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@supabase/supabase-js@2.44.2/dist/umd/supabase.min.js" crossorigin="anonymous"></script>
<script>
(function(){
  var url = "<?= htmlspecialchars(getenv('SUPABASE_URL') ?: '') ?>";
  var key = "<?= htmlspecialchars(getenv('SUPABASE_ANON_KEY') ?: (getenv('SUPABASE_PUBLISHABLE_KEY') ?: '')) ?>";
  if (!url || !key || typeof window.supabase === "undefined") return;
  var client = window.supabase.createClient(url, key);
  var channel = client.channel('realtime_admin_attendance_tr');
  channel
    .on('postgres_changes', { event: '*', schema: 'public', table: 'teacher_registry' }, function(payload) {
      location.reload();
    })
    .on('postgres_changes', { event: '*', schema: 'public', table: 'school_years' }, function(payload) {
      location.reload();
    })
    .on('postgres_changes', { event: '*', schema: 'public', table: 'colleges' }, function(payload) {
      location.reload();
    })
    .on('postgres_changes', { event: '*', schema: 'public', table: 'institutions' }, function(payload) {
      location.reload();
    })
    .subscribe();
})();
// require at least one school year on create
(function(){
  var form = document.getElementById("trCreateForm");
  if (!form) return;
  form.addEventListener("submit", function(e){
    var boxes = form.querySelectorAll('input[name="school_year_ids[]"]');
    var any = false;
    boxes.forEach(function(b){ if (b.checked) any = true; });
    if (!any) {
      e.preventDefault();
      alert("Please select at least one school year");
    }
  });
})();
</script>
<script>
(function(){
  function bindSearch(inputId, selectId){
    var input = document.getElementById(inputId);
    var sel = document.getElementById(selectId);
    if (!input || !sel) return;
    input.addEventListener("input", function(){
      var q = (input.value || "").toLowerCase();
      for (var i = 0; i < sel.options.length; i++) {
        var opt = sel.options[i];
        var t = (opt.text || "").toLowerCase();
        var v = (opt.value || "").toLowerCase();
        var m = t.indexOf(q) !== -1 || v.indexOf(q) !== -1;
        opt.hidden = !m;
      }
    });
  }
  var searchers = document.querySelectorAll("[data-search-for]");
  searchers.forEach(function(el){
    var target = el.getAttribute("data-search-for");
    if (el.id && target) bindSearch(el.id, target);
  });
})();
</script>
</body>
</html>
