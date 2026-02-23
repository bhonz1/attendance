<?php
require_once __DIR__ . "/../lib/auth.php";
require_once __DIR__ . "/../lib/supabase.php";
require_once __DIR__ . "/../lib/csrf.php";
require_teacher_session();
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
$tid = $_SESSION["teacher_id"] ?? null;
$name = $_SESSION["teacher_name"] ?? "Teacher";
$useSb = sb_url() ? true : false;
$cid = $_GET["class"] ?? ($_GET["id"] ?? "");
$classRec = null;
if ($cid !== "" && $useSb) {
  $r = sb_get("class_schedule", ["select"=>"id,subject_description,time,day,room,class,class_code,type,schoolyear_id,teacher_id", "id"=>"eq.".$cid, "limit"=>1]);
  if (is_array($r) && isset($r[0])) {
    $rec = $r[0];
    if ((string)($rec["teacher_id"] ?? "") === (string)$tid) $classRec = $rec;
  }
}
if ($cid !== "" && $classRec === null) {
  $rows = $_SESSION["__class_schedules"] ?? [];
  if (is_array($rows)) {
    foreach ($rows as $row) {
      if ((string)($row["id"] ?? "") === (string)$cid && (string)($row["teacher_id"] ?? "") === (string)$tid) { $classRec = $row; break; }
    }
  }
}
$subject = $classRec["subject_description"] ?? ($classRec["subject"] ?? "");
$code = $classRec["class_code"] ?? ($classRec["code"] ?? "");
$className = $classRec["class"] ?? "";
$schedule = ($classRec["time"] ?? "") . "; " . ($classRec["day"] ?? "") . "; " . ($classRec["room"] ?? "");
$instructor = $name;
$syId = $classRec["schoolyear_id"] ?? ($classRec["school_year_id"] ?? null);
$schoolyearDesc = "";
if ($syId !== null) {
  if ($useSb) {
    $syRows = sb_get("school_years", ["select"=>"id,code,description", "id"=>"eq.".$syId, "limit"=>1]);
    if (is_array($syRows) && isset($syRows[0])) {
      $schoolyearDesc = $syRows[0]["description"] ?? ($syRows[0]["code"] ?? "");
    }
  }
  if ($schoolyearDesc === "" && isset($_SESSION["__school_years_meta"]) && is_array($_SESSION["__school_years_meta"])) {
    foreach ($_SESSION["__school_years_meta"] as $row) {
      if ((string)($row["id"] ?? "") === (string)$syId) { $schoolyearDesc = $row["description"] ?? ($row["code"] ?? ""); break; }
    }
  }
}
$students = [];
if ($code !== "") {
  if ($useSb) {
    $csRows = sb_get("class_students", ["select"=>"student_number", "class_code"=>"eq.".$code]);
    $sns = [];
    if (is_array($csRows)) {
      foreach ($csRows as $row) { $sn = (string)($row["student_number"] ?? ""); if ($sn !== "") $sns[] = $sn; }
    }
    if (count($sns) > 0) {
      $inVals = "in.(" . implode(",", array_map(function($x){ return '"' . $x . '"'; }, $sns)) . ")";
      $stuRows = sb_get("students", ["select"=>"id,student_number,full_name,class_code", "student_number"=>$inVals, "order"=>"full_name.asc"]);
      if (is_array($stuRows)) {
        $students = array_map(function($s){
          return ["id"=>$s["id"] ?? null, "student_number"=>$s["student_number"] ?? "", "full_name"=>$s["full_name"] ?? ($s["name"] ?? ""), "class_code"=>$s["class_code"] ?? ""];
        }, $stuRows);
      }
    }
  } else {
    $sns = [];
    $csRows = $_SESSION["__class_students"] ?? [];
    if (is_array($csRows)) {
      foreach ($csRows as $row) { if ((string)($row["class_code"] ?? "") === (string)$code) { $sn = (string)($row["student_number"] ?? ""); if ($sn !== "") $sns[] = $sn; } }
    }
    $all = $_SESSION["__students"] ?? [];
    $students = array_values(array_filter(is_array($all) ? $all : [], function($row) use ($sns){
      return in_array((string)($row["student_number"] ?? ""), array_map("strval", $sns), true);
    }));
    usort($students, function($a, $b){
      $af = trim((string)($a["full_name"] ?? ($a["name"] ?? "")));
      $bf = trim((string)($b["full_name"] ?? ($b["name"] ?? "")));
      return strcasecmp($af, $bf);
    });
  }
}
$dateSel = $_POST["date"] ?? ($_GET["date"] ?? date("Y-m-d"));
$statusBySn = [];
if ($useSb && $code !== "" && $dateSel !== "") {
  $rows = sb_get("class_attendances", ["select"=>"student_number,status,remarks", "class_code"=>"eq.".$code, "date"=>"eq.".$dateSel]);
  if (is_array($rows)) {
    foreach ($rows as $r) {
      $sn = (string)($r["student_number"] ?? "");
      if ($sn !== "") $statusBySn[$sn] = ["status"=>$r["status"] ?? "", "remarks"=>$r["remarks"] ?? ""];
    }
  }
} else if ($code !== "" && $dateSel !== "") {
  $rows = $_SESSION["__attendance"] ?? [];
  if (is_array($rows)) {
    foreach ($rows as $r) {
      $sn = (string)($r["student_number"] ?? "");
      $cc = (string)($r["class_code"] ?? "");
      $dt = (string)($r["date"] ?? "");
      if ($cc === (string)$code && $dt === (string)$dateSel && $sn !== "") $statusBySn[$sn] = ["status"=>$r["status"] ?? "", "remarks"=>$r["remarks"] ?? ""];
    }
  }
}
$alert = null;
if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $tok = $_POST["csrf"] ?? "";
  if (!csrf_validate($tok)) {
    $alert = ["type"=>"danger","text"=>"Invalid form submission."];
  } else {
    $dateVal = $_POST["date"] ?? "";
    $st = $_POST["status"] ?? [];
    $applyHolidayAll = isset($_POST["holiday_day"]);
    $applyPresentAll = isset($_POST["all_present"]) && !$applyHolidayAll;
    $payload = [];
    $missingNums = [];
    foreach ($students as $s) {
      static $rowIdx = 0; $rowIdx++;
      $sn = (string)($s["student_number"] ?? "");
      if ($sn === "") continue;
      $chosen = "";
      if ($applyHolidayAll) {
        $chosen = "Holiday";
      } else if ($applyPresentAll) {
        $chosen = "Present";
      } else {
        $vals = isset($st[$sn]) ? (is_array($st[$sn]) ? $st[$sn] : [$st[$sn]]) : [];
        foreach ($vals as $v) { $vv = trim((string)$v); if ($vv !== "") { $chosen = $vv; break; } }
        if ($chosen === "") { $missingNums[] = $rowIdx; }
      }
      if ($dateVal !== "" && $chosen !== "") {
        $payload[] = ["class_code"=>$code, "student_number"=>$sn, "date"=>$dateVal, "status"=>$chosen];
      }
    }
    if (!$applyHolidayAll && !$applyPresentAll && count($missingNums) > 0) {
      $alert = ["type"=>"warning","text"=>"There are " . count($missingNums) . " students without a status: " . implode(", ", $missingNums)];
    } else if (count($payload) > 0) {
      if ($useSb) {
        $res = sb_upsert("class_attendances", $payload, "class_code,student_number,date");
        if ($res === null) {
          $err = sb_last_error();
          $msg = "Save failed.";
          if (is_array($err)) {
            $code = $err["code"] ?? null;
            $bj = $err["body_json"] ?? null;
            $needFallback = is_array($bj) && isset($bj["message"]) && is_string($bj["message"]) && stripos($bj["message"], "no unique or exclusion constraint matching") !== false;
            if ($needFallback) {
              $okAll = true;
              foreach ($payload as $r) {
                $upd = sb_patch("class_attendances", ["status"=>$r["status"]], ["class_code"=>"eq.".$r["class_code"], "student_number"=>"eq.".$r["student_number"], "date"=>"eq.".$r["date"]]);
                if ($upd === null) {
                  $ins = sb_post("class_attendances", $r);
                  if ($ins === null) { $okAll = false; break; }
                }
              }
              if ($okAll) {
                $alert = ["type"=>"success","text"=>"Attendance saved"];
                $statusBySn = [];
                foreach ($payload as $r) { $statusBySn[(string)$r["student_number"]] = ["status"=>$r["status"], "remarks"=>""]; }
                $bj = null; $code = null;
              }
            }
            if ($code === 409) {
              $msg = "Duplicate attendance entries for the selected date.";
            } elseif ($code === 404) {
              $msg = "Table class_attendances not found.";
            } elseif ($code === 401 || $code === 403) {
              $msg = "Unauthorized API access. Check RLS policies or use a service role.";
            } elseif ($code === 400) {
              if (is_array($bj) && isset($bj["message"]) && is_string($bj["message"])) {
                $msg = $bj["message"];
              } else {
                $msg = "Invalid request.";
              }
            } elseif (is_array($bj) && isset($bj["message"]) && is_string($bj["message"])) {
              $msg = "Save failed: " . $bj["message"];
            }
          }
          if (!isset($alert)) $alert = ["type"=>"danger","text"=>$msg];
        } else {
          $alert = ["type"=>"success","text"=>"Attendance saved"];
          $statusBySn = [];
          foreach ($payload as $r) { $statusBySn[(string)$r["student_number"]] = ["status"=>$r["status"], "remarks"=>""]; }
        }
      } else {
        if (!isset($_SESSION["__attendance"]) || !is_array($_SESSION["__attendance"])) $_SESSION["__attendance"] = [];
        $_SESSION["__attendance"] = array_values(array_filter($_SESSION["__attendance"], function($row) use ($code, $dateVal){
          return !((string)($row["class_code"] ?? "") === (string)$code && (string)($row["date"] ?? "") === (string)$dateVal);
        }));
        foreach ($payload as $r) { $_SESSION["__attendance"][] = $r; }
        $alert = ["type"=>"success","text"=>"Attendance saved"];
        $statusBySn = [];
        foreach ($payload as $r) { $statusBySn[(string)$r["student_number"]] = ["status"=>$r["status"], "remarks"=>""]; }
      }
    } else {
      $alert = ["type"=>"warning","text"=>"No attendance to save."];
    }
  }
}
$csrf_token = csrf_token();
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Attendance</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
:root { --brand-1:#0ea5e9; --brand-2:#6366f1; --ink:#0f172a; --yellow:#facc15; }
body { min-height:100vh; background: linear-gradient(180deg, #f8fafc 0%, #eef2ff 100%); }
.t-hero { position: relative; padding: 28px 0; background: linear-gradient(120deg, var(--brand-1) 0%, var(--brand-2) 40%, #0f172a 100%); color: #ffffff; border-bottom: 1px solid rgba(255,255,255,0.18); }
.t-hero__title { font-weight: 800; line-height: 1.1; font-size: clamp(22px, 5vw, 36px); letter-spacing: -0.01em; }
.card-elev { border: none; border-radius: 14px; box-shadow: 0 10px 24px rgba(2,6,23,0.08); }
.details-table td:nth-child(2), .details-table td:nth-child(4) { font-weight: 700; }
.status-cell { min-width: 240px; }
.status-group { display:flex; gap:8px; flex-wrap:wrap; }
.status-group .form-check-input { margin-top: 0.2rem; }
.btn-save { background-color: var(--brand-2); border-color: var(--brand-2); color: #ffffff; }
.btn-save:hover { background-color: var(--brand-2); border-color: var(--brand-2); color: #ffffff; }
.btn-cancel { background-color:#ffffff; border-color:#e5e7eb; color:#111827; }
.btn-cancel:hover { background-color:var(--yellow); border-color:var(--yellow); color:#111827; }
</style>
</head>
<body class="min-vh-100 d-flex flex-column">
<?php include __DIR__ . "/teacher_nav.php"; ?>
<div class="t-hero">
  <div class="container">
    <div class="d-flex align-items-center justify-content-between">
      <div>
        <div class="t-hero__title">Attendance</div>
        <div class="small opacity-75">Manage Attendance of your Class!</div>
      </div>
      <div>
        <a class="btn btn-outline-light btn-sm" href="/teacher/dashboard.php">Back</a>
      </div>
    </div>
  </div>
</div>
<main class="container py-4 flex-grow-1">
  <?php if ($code !== ""): ?>
  <div class="card card-elev mb-3">
    <div class="card-body">
      <div class="table-responsive">
        <table class="table table-striped table-sm align-middle details-table">
          <tbody>
            <tr><td>Class Code</td><td><?= htmlspecialchars($code) ?></td><td>School Year</td><td><?= htmlspecialchars($schoolyearDesc ? ("SY " . $schoolyearDesc) : "") ?></td></tr>
            <tr><td>Subject</td><td><?= htmlspecialchars($subject) ?></td><td>Class</td><td><?= htmlspecialchars($className) ?></td></tr>
            <tr><td>Schedule</td><td><?= htmlspecialchars($schedule) ?></td><td>Instructor</td><td><?= htmlspecialchars($instructor) ?></td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
  <?php endif; ?>
  <div class="card card-elev">
    <div class="card-body">
      <form method="post">
        <?php if (isset($alert) && is_array($alert)): ?>
          <div class="alert alert-<?= htmlspecialchars($alert["type"]) ?>" role="alert"><?= htmlspecialchars($alert["text"]) ?></div>
        <?php endif; ?>
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf_token) ?>">
        <div class="row g-2 mb-3">
          <div class="col-auto"><label class="form-label">Date</label></div>
          <div class="col-auto"><input type="date" class="form-control" name="date" value="<?= htmlspecialchars($dateSel) ?>"></div>
        </div>
        <div class="table-responsive">
          <table class="table table-striped table-sm align-middle">
            <thead>
              <tr>
                <th style="width:60px">Number</th>
                <th style="width:140px">Student Number</th>
                <th>Full Name</th>
                <th class="status-cell">
                  Status
                  <div class="form-check form-check-inline ms-2">
                    <input class="form-check-input" type="checkbox" id="allPresent" name="all_present">
                    <label class="form-check-label" for="allPresent">All Present</label>
                  </div>
                  <div class="form-check form-check-inline ms-2">
                    <input class="form-check-input" type="checkbox" id="holidayDay" name="holiday_day">
                    <label class="form-check-label" for="holidayDay">Holiday</label>
                  </div>
                </th>
              </tr>
            </thead>
            <tbody>
              <?php if (is_array($students) && count($students)>0): ?>
                <?php foreach ($students as $i => $s): $sn = (string)($s["student_number"] ?? ""); $st = $statusBySn[$sn]["status"] ?? ""; ?>
                  <tr>
                    <td><?= htmlspecialchars((string)($i+1)) ?></td>
                    <td><?= htmlspecialchars($sn) ?></td>
                    <td><?= htmlspecialchars($s["full_name"] ?? ($s["name"] ?? "")) ?></td>
                    <td>
                      <div class="status-group" data-group="<?= htmlspecialchars($sn) ?>">
                        <div class="form-check"><input class="form-check-input" type="checkbox" name="status[<?= htmlspecialchars($sn) ?>][]" value="Present" <?= $st==='Present'?'checked':'' ?>><label class="form-check-label">Present</label></div>
                        <div class="form-check"><input class="form-check-input" type="checkbox" name="status[<?= htmlspecialchars($sn) ?>][]" value="Absent" <?= $st==='Absent'?'checked':'' ?>><label class="form-check-label">Absent</label></div>
                        <div class="form-check"><input class="form-check-input" type="checkbox" name="status[<?= htmlspecialchars($sn) ?>][]" value="Tardy" <?= $st==='Tardy'?'checked':'' ?>><label class="form-check-label">Tardy</label></div>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php else: ?>
                <tr><td colspan="4" class="text-muted">No students found</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
        <div class="d-flex justify-content-center gap-2 mt-3">
          <button type="submit" class="btn btn-save">Save Attendance</button>
          <a class="btn btn-cancel" href="/teacher/dashboard.php">Cancel</a>
        </div>
      </form>
    </div>
  </div>
</main>
<footer class="text-center text-muted small py-3 border-top mt-auto">
  <div class="container">© 2026 Attendance Tracker | Developed by: Von P. Gabayan Jr.</div>
</footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function(){
  document.querySelectorAll('.status-group').forEach(function(group){
    group.addEventListener('change', function(e){
      if (e.target && e.target.matches('.form-check-input[type="checkbox"]')) {
        if (e.target.checked) {
          group.querySelectorAll('.form-check-input[type="checkbox"]').forEach(function(cb){
            if (cb !== e.target) cb.checked = false;
          });
        }
      }
    });
  });
  var allPresent = document.getElementById('allPresent');
  var holidayDay = document.getElementById('holidayDay');
  function setRowEnabled(enabled){
    document.querySelectorAll('.status-group .form-check-input').forEach(function(cb){
      cb.disabled = !enabled;
    });
  }
  if (allPresent) {
    allPresent.addEventListener('change', function(){
      if (this.checked) {
        if (holidayDay) holidayDay.checked = false;
        setRowEnabled(true);
        document.querySelectorAll('.status-group').forEach(function(group){
          var present = group.querySelector('input[value="Present"]');
          var absent = group.querySelector('input[value="Absent"]');
          var tardy = group.querySelector('input[value="Tardy"]');
          if (present) present.checked = true;
          if (absent) absent.checked = false;
          if (tardy) tardy.checked = false;
        });
      }
    });
  }
  if (holidayDay) {
    holidayDay.addEventListener('change', function(){
      if (this.checked) {
        if (allPresent) allPresent.checked = false;
        document.querySelectorAll('.status-group .form-check-input').forEach(function(cb){ cb.checked = false; });
        setRowEnabled(false);
      } else {
        setRowEnabled(true);
      }
    });
  }
  var successAlert = document.querySelector('.alert.alert-success');
  if (successAlert) {
    if (allPresent) allPresent.checked = false;
    if (holidayDay) holidayDay.checked = false;
    document.querySelectorAll('.status-group .form-check-input').forEach(function(cb){ cb.checked = false; cb.disabled = false; });
    setRowEnabled(true);
  }
})();
</script>
</body>
</html>
