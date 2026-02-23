<?php
require_once __DIR__ . "/../lib/admin.php";
require_once __DIR__ . "/../lib/supabase.php";
require_once __DIR__ . "/../lib/csrf.php";
require_admin_session();
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
$useSupabase = sb_url() ? true : false;
$msgSy = null; $errSy = null;
$msgCollege = null; $errCollege = null;
$msgInst = null; $errInst = null;
$csrf_token = csrf_token();
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["action"])) {
  $action = $_POST["action"];
  if (!csrf_validate($_POST["csrf"] ?? "")) {
    if (strpos($action, "sy_") === 0) $errSy = "Invalid form submission.";
    else if (strpos($action, "college_") === 0) $errCollege = "Invalid form submission.";
    else if (strpos($action, "inst_") === 0) $errInst = "Invalid form submission.";
  } else {
  if ($action === "sy_create") {
    $code = trim($_POST["code"] ?? ""); $desc = trim($_POST["description"] ?? "");
    $start = $_POST["start_date"] ?? ""; $end = $_POST["end_date"] ?? "";
    if (!$code || !$desc) { $errSy = "Code and description required"; }
    else {
      if ($useSupabase) {
        $r = sb_post("school_years", ["code"=>$code,"description"=>$desc,"start_date"=>$start?:null,"end_date"=>$end?:null]);
        $msgSy = $r !== null ? "School year created" : "Create failed";
        if ($r === null && getenv("SUPABASE_SERVICE_ROLE")) $errSy = "Create failed";
      } else {
        if (!isset($_SESSION["__school_years_meta"]) || !is_array($_SESSION["__school_years_meta"])) $_SESSION["__school_years_meta"] = [];
        $id = uniqid("sy_", true);
        $_SESSION["__school_years_meta"][] = ["id"=>$id,"code"=>$code,"description"=>$desc,"start_date"=>$start?:null,"end_date"=>$end?:null];
        $msgSy = "School year created";
      }
    }
  } else if ($action === "sy_update") {
    $id = $_POST["id"] ?? ""; $code = trim($_POST["code"] ?? ""); $desc = trim($_POST["description"] ?? "");
    $start = $_POST["start_date"] ?? ""; $end = $_POST["end_date"] ?? "";
    if (!$id || !$code || !$desc) { $errSy = "ID, code, and description required"; }
    else {
      if ($useSupabase) {
        $r = sb_patch("school_years", ["code"=>$code,"description"=>$desc,"start_date"=>$start?:null,"end_date"=>$end?:null], ["id"=>"eq.".$id]);
        $msgSy = $r !== null ? "School year updated" : "Update failed";
        if ($r === null && getenv("SUPABASE_SERVICE_ROLE")) $errSy = "Update failed";
      } else {
        if (isset($_SESSION["__school_years_meta"]) && is_array($_SESSION["__school_years_meta"])) {
          foreach ($_SESSION["__school_years_meta"] as &$row) {
            if (($row["id"] ?? "") === $id) { $row["code"]=$code; $row["description"]=$desc; $row["start_date"]=$start?:null; $row["end_date"]=$end?:null; $msgSy="School year updated"; break; }
          }
        }
      }
    }
  } else if ($action === "sy_delete") {
    $id = $_POST["id"] ?? "";
    if (!$id) { $errSy = "ID required"; }
    else {
      if ($useSupabase) {
        $syRec = sb_get("school_years", ["select"=>"id,code","id"=>"eq.".$id,"limit"=>1]);
        $codeVal = (is_array($syRec) && isset($syRec[0])) ? ($syRec[0]["code"] ?? null) : null;
        $trs = sb_get("teacher_registry", ["select"=>"id,school_year_ids"]);
        $linked = false;
        if (is_array($trs)) {
          foreach ($trs as $t) {
            $arr = $t["school_year_ids"] ?? [];
            if (is_string($arr)) { $tmp = json_decode($arr, true); if (is_array($tmp)) $arr = $tmp; }
            if (in_array($id, is_array($arr) ? $arr : [], true)) { $linked = true; break; }
            if ($codeVal && in_array($codeVal, is_array($arr) ? $arr : [], true)) { $linked = true; break; }
          }
        }
        if ($linked) {
          $errSy = "Cannot delete school year linked to teachers";
        } else {
          $r = sb_delete("school_years", ["id"=>"eq.".$id]);
          $msgSy = $r !== null ? "School year deleted" : "Delete failed";
        }
      } else {
        if (isset($_SESSION["__school_years_meta"]) && is_array($_SESSION["__school_years_meta"])) {
          $codeVal = null;
          foreach ($_SESSION["__school_years_meta"] as $row) { if (($row["id"] ?? "") === $id) { $codeVal = $row["code"] ?? null; break; } }
          $linked = false;
          if (isset($_SESSION["__teacher_registry"]) && is_array($_SESSION["__teacher_registry"])) {
            foreach ($_SESSION["__teacher_registry"] as $t) {
              $arr = $t["school_year_ids"] ?? [];
              if (is_string($arr)) { $tmp = json_decode($arr, true); if (is_array($tmp)) $arr = $tmp; }
              if (in_array($id, is_array($arr) ? $arr : [], true)) { $linked = true; break; }
              if ($codeVal && in_array($codeVal, is_array($arr) ? $arr : [], true)) { $linked = true; break; }
            }
          }
          if ($linked) {
            $errSy = "Cannot delete school year linked to teachers";
          } else {
            $_SESSION["__school_years_meta"] = array_values(array_filter($_SESSION["__school_years_meta"], function($row) use ($id){ return ($row["id"] ?? "") !== $id; }));
            $msgSy = "School year deleted";
          }
        }
      }
    }
  } else if ($action === "college_create") {
    $desc = trim($_POST["description"] ?? "");
    if (!$desc) { $errCollege = "Description required"; }
    else {
      $code = strtoupper(preg_replace("/[^A-Za-z0-9]+/", "_", $desc));
      $code = trim($code, "_");
      if ($code === "") $code = "CO_" . substr(uniqid("", true), -6);
      else $code = substr($code, 0, 32);
      if ($useSupabase) {
        $payload = ["code"=>$code,"descriptions"=>$desc];
        $r = sb_post("colleges", $payload);
        if ($r === null) {
          $errInfo = sb_last_error();
          $codeVal = is_array($errInfo) ? ($errInfo["code"] ?? null) : null;
          $dup = is_array($errInfo) && (is_string($errInfo["body_raw"] ?? "") && stripos($errInfo["body_raw"], "duplicate") !== false);
          if ($codeVal === 409 || $dup) {
            $code = $code . "_" . substr(uniqid("", true), -4);
            $payload["code"] = $code;
            $r = sb_post("colleges", $payload);
          }
          if ($r === null) {
            $payload2 = ["code"=>$code,"description"=>$desc];
            $r = sb_post("colleges", $payload2);
          }
          if ($r === null) {
            $payload3 = ["descriptions"=>$desc];
            $r = sb_post("colleges", $payload3);
          }
          if ($r === null) {
            $payload4 = ["description"=>$desc];
            $r = sb_post("colleges", $payload4);
          }
          if ($r === null) {
            $errInfo2 = sb_last_error();
            $codeVal2 = is_array($errInfo2) ? ($errInfo2["code"] ?? null) : null;
            if (!$codeVal2 || $codeVal2 === 401 || $codeVal2 === 403 || $codeVal2 === 404) {
              if (!isset($_SESSION["__colleges_meta"]) || !is_array($_SESSION["__colleges_meta"])) $_SESSION["__colleges_meta"] = [];
              $id = uniqid("co_", true);
              $_SESSION["__colleges_meta"][] = ["id"=>$id,"code"=>$code,"descriptions"=>$desc,"description"=>$desc];
              $msgCollege = "College created";
            } else {
              $errCollege = "Create failed";
            }
          } else {
            $msgCollege = "College created";
          }
        } else {
          $msgCollege = "College created";
        }
      } else {
        if (!isset($_SESSION["__colleges_meta"]) || !is_array($_SESSION["__colleges_meta"])) $_SESSION["__colleges_meta"] = [];
        $id = uniqid("co_", true);
        $_SESSION["__colleges_meta"][] = ["id"=>$id,"code"=>$code,"descriptions"=>$desc,"description"=>$desc];
        $msgCollege = "College created";
      }
    }
  } else if ($action === "college_update") {
    $id = $_POST["id"] ?? ""; $desc = trim($_POST["description"] ?? "");
    if (!$id || !$desc) { $errCollege = "ID and description required"; }
    else {
      if ($useSupabase) {
        $r = sb_patch("colleges", ["descriptions"=>$desc], ["id"=>"eq.".$id]);
        if ($r === null) {
          $r2 = sb_patch("colleges", ["description"=>$desc], ["id"=>"eq.".$id]);
          if ($r2 === null) {
            $errInfo = sb_last_error();
            $codeVal = is_array($errInfo) ? ($errInfo["code"] ?? null) : null;
            if (!$codeVal || $codeVal === 401 || $codeVal === 403 || $codeVal === 404) {
              if (isset($_SESSION["__colleges_meta"]) && is_array($_SESSION["__colleges_meta"])) {
                foreach ($_SESSION["__colleges_meta"] as &$row) { if (($row["id"] ?? "") === $id) { $row["descriptions"]=$desc; $row["description"]=$desc; $msgCollege="College updated"; break; } }
              } else {
                $errCollege = "Update failed";
              }
            } else {
              $errCollege = "Update failed";
            }
          } else {
            $msgCollege = "College updated";
          }
        } else {
          $msgCollege = "College updated";
        }
      } else {
        if (isset($_SESSION["__colleges_meta"]) && is_array($_SESSION["__colleges_meta"])) {
          foreach ($_SESSION["__colleges_meta"] as &$row) { if (($row["id"] ?? "") === $id) { $row["descriptions"]=$desc; $row["description"]=$desc; $msgCollege="College updated"; break; } }
        }
      }
    }
  } else if ($action === "college_delete") {
    $id = $_POST["id"] ?? "";
    if (!$id) { $errCollege = "ID required"; }
    else {
      if ($useSupabase) {
        $r = sb_delete("colleges", ["id"=>"eq.".$id]);
        $msgCollege = $r !== null ? "College deleted" : "Delete failed";
      } else {
        if (isset($_SESSION["__colleges_meta"]) && is_array($_SESSION["__colleges_meta"])) {
          $_SESSION["__colleges_meta"] = array_values(array_filter($_SESSION["__colleges_meta"], function($row) use ($id){ return ($row["id"] ?? "") !== $id; }));
          $msgCollege = "College deleted";
        }
      }
    }
  } else if ($action === "inst_create") {
    $desc = trim($_POST["description"] ?? "");
    if (!$desc) { $errInst = "Description required"; }
    else {
      $code = strtoupper(preg_replace("/[^A-Za-z0-9]+/", "_", $desc));
      $code = trim($code, "_");
      if ($code === "") $code = "IN_" . substr(uniqid("", true), -6);
      else $code = substr($code, 0, 32);
      if ($useSupabase) {
        $payload = ["code"=>$code,"descriptions"=>$desc];
        $r = sb_post("institutions", $payload);
        if ($r === null) {
          $errInfo = sb_last_error();
          $codeVal = is_array($errInfo) ? ($errInfo["code"] ?? null) : null;
          $dup = is_array($errInfo) && (is_string($errInfo["body_raw"] ?? "") && stripos($errInfo["body_raw"], "duplicate") !== false);
          if ($codeVal === 409 || $dup) {
            $code = $code . "_" . substr(uniqid("", true), -4);
            $payload["code"] = $code;
            $r = sb_post("institutions", $payload);
          }
          if ($r === null) {
            $payload2 = ["code"=>$code,"description"=>$desc];
            $r = sb_post("institutions", $payload2);
          }
          if ($r === null) {
            $payload3 = ["descriptions"=>$desc];
            $r = sb_post("institutions", $payload3);
          }
          if ($r === null) {
            $payload4 = ["description"=>$desc];
            $r = sb_post("institutions", $payload4);
          }
          if ($r === null) {
            $errInfo2 = sb_last_error();
            $codeVal2 = is_array($errInfo2) ? ($errInfo2["code"] ?? null) : null;
            if (!$codeVal2 || $codeVal2 === 401 || $codeVal2 === 403 || $codeVal2 === 404) {
              if (!isset($_SESSION["__institutions_meta"]) || !is_array($_SESSION["__institutions_meta"])) $_SESSION["__institutions_meta"] = [];
              $id = uniqid("in_", true);
              $_SESSION["__institutions_meta"][] = ["id"=>$id,"code"=>$code,"descriptions"=>$desc,"description"=>$desc];
              $msgInst = "Institution created";
            } else {
              $errInst = "Create failed";
            }
          } else {
            $msgInst = "Institution created";
          }
        } else {
          $msgInst = "Institution created";
        }
      } else {
        if (!isset($_SESSION["__institutions_meta"]) || !is_array($_SESSION["__institutions_meta"])) $_SESSION["__institutions_meta"] = [];
        $id = uniqid("in_", true);
        $_SESSION["__institutions_meta"][] = ["id"=>$id,"code"=>$code,"descriptions"=>$desc,"description"=>$desc];
        $msgInst = "Institution created";
      }
    }
  } else if ($action === "inst_update") {
    $id = $_POST["id"] ?? ""; $desc = trim($_POST["description"] ?? "");
    if (!$id || !$desc) { $errInst = "ID and description required"; }
    else {
      if ($useSupabase) {
        $r = sb_patch("institutions", ["descriptions"=>$desc], ["id"=>"eq.".$id]);
        if ($r === null) {
          $r2 = sb_patch("institutions", ["description"=>$desc], ["id"=>"eq.".$id]);
          if ($r2 === null) {
            $errInfo = sb_last_error();
            $codeVal = is_array($errInfo) ? ($errInfo["code"] ?? null) : null;
            if (!$codeVal || $codeVal === 401 || $codeVal === 403 || $codeVal === 404) {
              if (isset($_SESSION["__institutions_meta"]) && is_array($_SESSION["__institutions_meta"])) {
                foreach ($_SESSION["__institutions_meta"] as &$row) { if (($row["id"] ?? "") === $id) { $row["descriptions"]=$desc; $row["description"]=$desc; $msgInst="Institution updated"; break; } }
              } else {
                $errInst = "Update failed";
              }
            } else {
              $errInst = "Update failed";
            }
          } else {
            $msgInst = "Institution updated";
          }
        } else {
          $msgInst = "Institution updated";
        }
      } else {
        if (isset($_SESSION["__institutions_meta"]) && is_array($_SESSION["__institutions_meta"])) {
          foreach ($_SESSION["__institutions_meta"] as &$row) { if (($row["id"] ?? "") === $id) { $row["descriptions"]=$desc; $row["description"]=$desc; $msgInst="Institution updated"; break; } }
        }
      }
    }
  } else if ($action === "inst_delete") {
    $id = $_POST["id"] ?? "";
    if (!$id) { $errInst = "ID required"; }
    else {
      if ($useSupabase) {
        $r = sb_delete("institutions", ["id"=>"eq.".$id]);
        $msgInst = $r !== null ? "Institution deleted" : "Delete failed";
      } else {
        if (isset($_SESSION["__institutions_meta"]) && is_array($_SESSION["__institutions_meta"])) {
          $_SESSION["__institutions_meta"] = array_values(array_filter($_SESSION["__institutions_meta"], function($row) use ($id){ return ($row["id"] ?? "") !== $id; }));
          $msgInst = "Institution deleted";
        }
      }
    }
  }
}
}
$syList = $useSupabase ? sb_get("school_years", ["select"=>"id,code,description,start_date,end_date","order"=>"id.desc"]) : ($_SESSION["__school_years_meta"] ?? []);
$syList = is_array($syList) ? $syList : [];
$colleges = $useSupabase ? sb_get("colleges", ["select"=>"id,description","order"=>"description.asc"]) : ($_SESSION["__colleges_meta"] ?? []);
if (!is_array($colleges)) $colleges = [];
$institutions = $useSupabase ? sb_get("institutions", ["select"=>"id,description","order"=>"description.asc"]) : ($_SESSION["__institutions_meta"] ?? []);
if (!is_array($institutions)) $institutions = [];
 
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Admin Settings</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
.set-hero { position: relative; padding: 28px 0; background: linear-gradient(120deg, #0ea5e9 0%, #6366f1 40%, #0f172a 100%); color: #ffffff; border-bottom: 1px solid rgba(255,255,255,0.18); }
.set-hero__title { font-weight: 800; line-height: 1.1; font-size: clamp(24px, 5.5vw, 40px); letter-spacing: -0.01em; }
.quick-card { border: none; border-radius: 14px; box-shadow: 0 8px 20px rgba(2,6,23,0.06); }
.metric-card { border: none; border-radius: 14px; box-shadow: 0 10px 24px rgba(2,6,23,0.08); }
.table thead th { background: #f8fafc; }
</style>
</head>
<body class="d-flex flex-column min-vh-100">
<?php $admin_nav_active = "settings"; include __DIR__ . "/admin_nav.php"; ?>
<div class="set-hero">
  <div class="container">
    <div class="d-flex align-items-center justify-content-between">
      <div>
        <div class="set-hero__title">Settings</div>
      </div>
    </div>
  </div>
 </div>
<div class="container py-4">
  <?php if ($msgSy): ?><div class="alert alert-success"><?= htmlspecialchars($msgSy) ?></div><?php endif; ?>
  <?php if ($errSy): ?><div class="alert alert-danger"><?= htmlspecialchars($errSy) ?></div><?php endif; ?>
  <div class="card quick-card mb-4"><div class="card-body">
    <div class="d-flex align-items-center justify-content-between mb-2"><div class="fw-bold">School Year</div><button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#syCreateModal">+</button></div>
    <div class="table-responsive">
      <table class="table table-sm table-hover table-striped align-middle">
        <thead><tr><th style="width:16%">Code</th><th>Description</th><th style="width:16%">Start</th><th style="width:16%">End</th><th style="width:28%">Actions</th></tr></thead>
        <tbody>
          <?php foreach ($syList as $row): ?>
          <tr>
            <td><?= htmlspecialchars($row["code"] ?? "") ?></td>
            <td><?= htmlspecialchars($row["descriptions"] ?? ($row["description"] ?? "")) ?></td>
            <td><?= htmlspecialchars($row["start_date"] ?? "—") ?></td>
            <td><?= htmlspecialchars($row["end_date"] ?? "—") ?></td>
            <td>
              <form method="post" class="d-inline">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf_token) ?>">
                <input type="hidden" name="action" value="sy_delete">
                <input type="hidden" name="id" value="<?= htmlspecialchars((string)($row["id"] ?? "")) ?>">
                <button type="submit" class="btn btn-outline-danger btn-sm">Delete</button>
              </form>
              <button class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#syEditModal<?= htmlspecialchars((string)($row["id"] ?? "")) ?>">Edit</button>
              <div class="modal fade" id="syEditModal<?= htmlspecialchars((string)($row["id"] ?? "")) ?>" tabindex="-1" aria-hidden="true"><div class="modal-dialog"><div class="modal-content">
                <div class="modal-header"><h5 class="modal-title">Edit School Year</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
                <form method="post"><div class="modal-body">
                  <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf_token) ?>">
                  <input type="hidden" name="action" value="sy_update">
                  <input type="hidden" name="id" value="<?= htmlspecialchars((string)($row["id"] ?? "")) ?>">
                  <div class="mb-2"><label class="form-label">Code</label><input class="form-control" name="code" value="<?= htmlspecialchars($row["code"] ?? "") ?>" required></div>
                  <div class="mb-2"><label class="form-label">Description</label><input class="form-control" name="description" value="<?= htmlspecialchars($row["descriptions"] ?? ($row["description"] ?? "")) ?>" required></div>
                  <div class="mb-2"><label class="form-label">Start Date</label><input type="date" class="form-control" name="start_date" value="<?= htmlspecialchars($row["start_date"] ?? "") ?>"></div>
                  <div class="mb-2"><label class="form-label">End Date</label><input type="date" class="form-control" name="end_date" value="<?= htmlspecialchars($row["end_date"] ?? "") ?>"></div>
                </div><div class="modal-footer"><button type="submit" class="btn btn-primary btn-sm">Save</button></div></form>
              </div></div></div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div></div>
  <div class="row g-3 mb-4">
    <div class="col-12 col-lg-6">
      <?php if ($msgInst): ?><div class="alert alert-success"><?= htmlspecialchars($msgInst) ?></div><?php endif; ?>
      <?php if ($errInst): ?><div class="alert alert-danger"><?= htmlspecialchars($errInst) ?></div><?php endif; ?>
      <div class="card quick-card"><div class="card-body">
        <div class="d-flex align-items-center justify-content-between mb-2"><div class="fw-bold">Institution</div><button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#inCreateModal">+</button></div>
        <div class="table-responsive">
          <table class="table table-sm table-hover table-striped align-middle">
            <thead><tr><th>Description</th><th style="width:28%">Actions</th></tr></thead>
            <tbody>
              <?php foreach ($institutions as $row): ?>
              <tr>
                <td><?= htmlspecialchars($row["description"] ?? "") ?></td>
                <td>
                  <form method="post" class="d-inline">
                    <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf_token) ?>">
                    <input type="hidden" name="action" value="inst_delete">
                    <input type="hidden" name="id" value="<?= htmlspecialchars((string)($row["id"] ?? "")) ?>">
                    <button type="submit" class="btn btn-outline-danger btn-sm">Delete</button>
                  </form>
                  <button class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#inEditModal<?= htmlspecialchars((string)($row["id"] ?? "")) ?>">Edit</button>
                  <div class="modal fade" id="inEditModal<?= htmlspecialchars((string)($row["id"] ?? "")) ?>" tabindex="-1" aria-hidden="true"><div class="modal-dialog"><div class="modal-content">
                    <div class="modal-header"><h5 class="modal-title">Edit Institution</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
                    <form method="post"><div class="modal-body">
                      <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf_token) ?>">
                      <input type="hidden" name="action" value="inst_update">
                      <input type="hidden" name="id" value="<?= htmlspecialchars((string)($row["id"] ?? "")) ?>">
                      <div class="mb-2"><label class="form-label">Description</label><input class="form-control" name="description" value="<?= htmlspecialchars($row["description"] ?? "") ?>" required></div>
                    </div><div class="modal-footer"><button type="submit" class="btn btn-primary btn-sm">Save</button></div></form>
                  </div></div></div>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div></div>
    </div>
    <div class="col-12 col-lg-6">
      <?php if ($msgCollege): ?><div class="alert alert-success"><?= htmlspecialchars($msgCollege) ?></div><?php endif; ?>
      <?php if ($errCollege): ?><div class="alert alert-danger"><?= htmlspecialchars($errCollege) ?></div><?php endif; ?>
      <div class="card quick-card"><div class="card-body">
        <div class="d-flex align-items-center justify-content-between mb-2"><div class="fw-bold">College</div><button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#coCreateModal">+</button></div>
        <div class="table-responsive">
          <table class="table table-sm table-hover table-striped align-middle">
            <thead><tr><th>Description</th><th style="width:28%">Actions</th></tr></thead>
            <tbody>
              <?php foreach ($colleges as $row): ?>
              <tr>
                <td><?= htmlspecialchars($row["description"] ?? "") ?></td>
                <td>
                  <form method="post" class="d-inline">
                    <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf_token) ?>">
                    <input type="hidden" name="action" value="college_delete">
                    <input type="hidden" name="id" value="<?= htmlspecialchars((string)($row["id"] ?? "")) ?>">
                    <button type="submit" class="btn btn-outline-danger btn-sm">Delete</button>
                  </form>
                  <button class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#coEditModal<?= htmlspecialchars((string)($row["id"] ?? "")) ?>">Edit</button>
                  <div class="modal fade" id="coEditModal<?= htmlspecialchars((string)($row["id"] ?? "")) ?>" tabindex="-1" aria-hidden="true"><div class="modal-dialog"><div class="modal-content">
                    <div class="modal-header"><h5 class="modal-title">Edit College</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
                    <form method="post"><div class="modal-body">
                      <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf_token) ?>">
                      <input type="hidden" name="action" value="college_update">
                      <input type="hidden" name="id" value="<?= htmlspecialchars((string)($row["id"] ?? "")) ?>">
                      <div class="mb-2"><label class="form-label">Description</label><input class="form-control" name="description" value="<?= htmlspecialchars($row["description"] ?? "") ?>" required></div>
                    </div><div class="modal-footer"><button type="submit" class="btn btn-primary btn-sm">Save</button></div></form>
                  </div></div></div>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div></div>
    </div>
  </div>
</div>
<div class="modal fade" id="syCreateModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog"><div class="modal-content">
  <div class="modal-header"><h5 class="modal-title">Add School Year</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
  <form method="post"><div class="modal-body">
    <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf_token) ?>">
    <input type="hidden" name="action" value="sy_create">
    <div class="mb-2"><label class="form-label">Code</label><input class="form-control" name="code" required></div>
    <div class="mb-2"><label class="form-label">Description</label><input class="form-control" name="description" required></div>
    <div class="mb-2"><label class="form-label">Start Date</label><input type="date" class="form-control" name="start_date"></div>
    <div class="mb-2"><label class="form-label">End Date</label><input type="date" class="form-control" name="end_date"></div>
  </div><div class="modal-footer"><button type="submit" class="btn btn-primary btn-sm">Add</button></div></form>
</div></div></div>
<div class="modal fade" id="coCreateModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog"><div class="modal-content">
  <div class="modal-header"><h5 class="modal-title">Add College</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
  <form method="post"><div class="modal-body">
    <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf_token) ?>">
    <input type="hidden" name="action" value="college_create">
    <div class="mb-2"><label class="form-label">Description</label><input class="form-control" name="description" required></div>
  </div><div class="modal-footer"><button type="submit" class="btn btn-primary btn-sm">Add</button></div></form>
</div></div></div>
<div class="modal fade" id="inCreateModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog"><div class="modal-content">
  <div class="modal-header"><h5 class="modal-title">Add Institution</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
  <form method="post"><div class="modal-body">
    <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf_token) ?>">
    <input type="hidden" name="action" value="inst_create">
    <div class="mb-2"><label class="form-label">Description</label><input class="form-control" name="description" required></div>
  </div><div class="modal-footer"><button type="submit" class="btn btn-primary btn-sm">Add</button></div></form>
</div></div></div>
<footer class="text-center text-muted small py-3 border-top mt-auto">
  <div class="container">© 2026 Attendance Tracker | Developed by: Von P. Gabayan Jr.</div>
</footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
