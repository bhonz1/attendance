<?php
require_once __DIR__ . "/../lib/auth.php";
require_once __DIR__ . "/../lib/supabase.php";
require_once __DIR__ . "/../lib/csrf.php";
require_teacher_session();
require_once __DIR__ . "/../lib/urlref.php";
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
$csrf_token = csrf_token();
$tid = $_SESSION["teacher_id"] ?? null;
$name = $_SESSION["teacher_name"] ?? "Teacher";
$useSb = sb_url() ? true : false;
$cid = $_GET["class"] ?? ($_GET["id"] ?? "");
$cid = is_string($cid) ? preg_replace('/[^0-9]/', '', $cid) : $cid;
$refTok = $_GET["ref"] ?? "";
if ($refTok !== "") {
  $ref = url_ref_consume($refTok);
  if (is_array($ref)) {
    $cid = $ref["class"] ?? $cid;
    if (isset($ref["student"])) $_GET["student"] = $ref["student"];
  }
}
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
$subject = $classRec["subject_description"] ?? "";
$code = $classRec["class_code"] ?? "";
$subj = $classRec["subject_description"] ?? "";
$className = $classRec["class"] ?? "";
$schedule = ($classRec["time"] ?? "") . "; " . ($classRec["day"] ?? "") . "; " . ($classRec["room"] ?? "");
$instructor = $name;
$syId = $classRec["schoolyear_id"] ?? null;
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
$selectedSn = $_GET["student"] ?? "";
$selectedSn = is_string($selectedSn) ? preg_replace('/[^0-9A-Za-z\\-]/', '', $selectedSn) : $selectedSn;
$studentMap = [];
foreach ($students as $s) { $studentMap[(string)($s["student_number"] ?? "")] = $s; }
$monitorSN = "";
$monitorName = "";
if ($selectedSn !== "" && isset($studentMap[$selectedSn])) {
  $monitorSN = $selectedSn;
  $monitorName = $studentMap[$selectedSn]["full_name"] ?? ($studentMap[$selectedSn]["name"] ?? "");
}
$alert = null;
if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $tok = $_POST["csrf"] ?? "";
  if (!csrf_validate($tok)) {
    $alert = ["type"=>"danger","text"=>"Invalid form submission."];
  } else {
    $remarkSn = $_POST["remark_sn"] ?? "";
    $remarkSn = is_string($remarkSn) ? preg_replace('/[^0-9A-Za-z\\-]/', '', $remarkSn) : "";
    $remarkDate = trim((string)($_POST["remark_date"] ?? ""));
    $remarkText = trim((string)($_POST["remark_text"] ?? ""));
    if (strlen($remarkText) > 500) $remarkText = substr($remarkText, 0, 500);
    if ($remarkSn !== "" && $remarkDate !== "" && $code !== "") {
      if (!isset($studentMap[$remarkSn])) {
        $alert = ["type"=>"danger","text"=>"Invalid student"];
      } else if (!preg_match("/^\\d{4}-\\d{2}-\\d{2}$/", $remarkDate)) {
        $alert = ["type"=>"danger","text"=>"Invalid date"];
      } else if ($useSb) {
        $upd = sb_patch("class_attendances", ["remarks"=>$remarkText], ["class_code"=>"eq.".$code, "student_number"=>"eq.".$remarkSn, "date"=>"eq.".$remarkDate]);
        if ($upd === null) {
          $alert = ["type"=>"danger","text"=>"Save remark failed"];
        } else {
          $alert = ["type"=>"success","text"=>"Remark saved"];
        }
      } else {
        $rows = $_SESSION["__attendance"] ?? [];
        $found = false;
        if (is_array($rows)) {
          foreach ($rows as &$r) {
            if ((string)($r["class_code"] ?? "") === (string)$code && (string)($r["student_number"] ?? "") === (string)$remarkSn && (string)($r["date"] ?? "") === (string)$remarkDate) {
              $r["remarks"] = $remarkText;
              $found = true;
            }
          }
          unset($r);
          $_SESSION["__attendance"] = $rows;
        }
        if ($found) $alert = ["type"=>"success","text"=>"Remark saved"]; else $alert = ["type"=>"danger","text"=>"Save remark failed"];
      }
    } else {
      $alert = ["type"=>"warning","text"=>"Incomplete remark details"];
    }
  }
}
$attendanceRows = [];
if ($code !== "") {
  if ($useSb) {
    $q = ["select"=>"student_number,class_code,date,status,remarks", "class_code"=>"eq.".$code, "order"=>"date.asc"];
    if ($selectedSn !== "") $q["student_number"] = "eq.".$selectedSn;
    $att = sb_get("class_attendances", $q);
    if (is_array($att)) {
      foreach ($att as $r) {
        $sn = (string)($r["student_number"] ?? "");
        $fn = isset($studentMap[$sn]) ? ($studentMap[$sn]["full_name"] ?? ($studentMap[$sn]["name"] ?? "")) : "";
        $attendanceRows[] = ["student_number"=>$sn, "full_name"=>$fn, "date"=>$r["date"] ?? "", "status"=>$r["status"] ?? "", "remarks"=>$r["remarks"] ?? ""];
      }
    }
  } else {
    $att = $_SESSION["__attendance"] ?? [];
    if (is_array($att)) {
      foreach ($att as $r) {
        $sn = (string)($r["student_number"] ?? "");
        $cc = (string)($r["class_code"] ?? "");
        if ($cc !== (string)$code) continue;
        if ($selectedSn !== "" && $sn !== $selectedSn) continue;
        $fn = isset($studentMap[$sn]) ? ($studentMap[$sn]["full_name"] ?? ($studentMap[$sn]["name"] ?? "")) : "";
        $attendanceRows[] = ["student_number"=>$sn, "full_name"=>$fn, "date"=>$r["date"] ?? "", "status"=>$r["status"] ?? "", "remarks"=>$r["remarks"] ?? ""];
      }
      usort($attendanceRows, function($a,$b){ return strcmp((string)($a["date"] ?? ""), (string)($b["date"] ?? "")); });
    }
  }
}
$attendanceBySn = [];
foreach ($attendanceRows as $r) {
  $sn = (string)($r["student_number"] ?? "");
  if ($sn === "") continue;
  if (!isset($attendanceBySn[$sn])) {
    $attendanceBySn[$sn] = ["full_name" => ($r["full_name"] ?? ""), "rows" => []];
  }
  $attendanceBySn[$sn]["rows"][] = $r;
}
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Class</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
:root { --brand-1:#0ea5e9; --brand-2:#6366f1; --ink:#0f172a; --yellow:#facc15; }
body { min-height:100vh; background: linear-gradient(180deg, #f8fafc 0%, #eef2ff 100%); }
.t-hero { position: relative; padding: 28px 0; background: linear-gradient(120deg, var(--brand-1) 0%, var(--brand-2) 40%, #0f172a 100%); color: #ffffff; border-bottom: 1px solid rgba(255,255,255,0.18); }
.t-hero__title { font-weight: 800; line-height: 1.1; font-size: clamp(22px, 5vw, 36px); letter-spacing: -0.01em; }
.card-elev { border: none; border-radius: 14px; box-shadow: 0 10px 24px rgba(2,6,23,0.08); }
.section-title { font-weight: 700; color: #0f172a; }
.list-box { border: none; border-radius: 14px; box-shadow: 0 8px 20px rgba(2,6,23,0.06); }
.monitoring-card .card-body { padding: 16px 36px 36px; }
.monitoring-title { margin-bottom: 18px; }
.add-student-btn { background-color:#ffffff; border-color:#e5e7eb; color:#111827; }
.add-student-btn:hover { background-color:var(--yellow); border-color:var(--yellow); color:#111827; }
.add-student-btn svg { width:16px; height:16px; }
.add-student-btn svg path { fill:#000000; stroke:#000000; stroke-width:1.5; }
.details-table td:nth-child(2), .details-table td:nth-child(4) { font-weight: 700; }
.btn-view-all { background-color:#ffffff; border-color:#e5e7eb; color:#111827; }
.btn-view-all:hover { background-color:var(--yellow); border-color:var(--yellow); color:#111827; }
.student-sep { border-top:3px solid #e5e7eb; margin:12px 0; }
</style>
</head>
<body class="min-vh-100 d-flex flex-column">
<?php include __DIR__ . "/teacher_nav.php"; ?>
<div class="t-hero">
  <div class="container">
    <div class="d-flex align-items-center justify-content-between">
      <div>
        <div class="t-hero__title">Class View</div>
        <div class="small opacity-75">Manage your Class Attendance!</div>
      </div>
      <div>
        <a class="btn btn-outline-light btn-sm" href="/teacher/dashboard">Back</a>
      </div>
    </div>
  </div>
</div>
<main class="container py-4 flex-grow-1">
  <div class="card card-elev mb-3">
    <div class="card-body">
      <div class="table-responsive">
        <table class="table table-striped table-sm align-middle details-table">
          <tbody>
            <tr><td>Class Code</td><td><?= htmlspecialchars($code) ?></td><td>School Year</td><td><?= htmlspecialchars($schoolyearDesc) ?></td></tr>
            <tr><td>Subject</td><td><?= htmlspecialchars($subj) ?></td><td>Class</td><td><?= htmlspecialchars($className) ?></td></tr>
            <tr><td>Schedule</td><td><?= htmlspecialchars($schedule) ?></td><td>Instructor</td><td><?= htmlspecialchars($instructor) ?></td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
  <div class="row g-3">
    <div class="col-md-4">
      <div class="card list-box">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-center mb-2">
            <div class="section-title">Students</div>
            <?php $tokAdd = url_ref_create(["class"=>(string)$cid]); ?>
            <a class="btn btn-light btn-sm add-student-btn" href="/teacher/add-student?ref=<?= htmlspecialchars($tokAdd) ?>" title="Add Student" aria-label="Add Student">
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" aria-hidden="true">
                <path d="M8 3v10M3 8h10"></path>
              </svg>
            </a>
          </div>
          <?php if (is_array($students) && count($students)>0): ?>
            <ol class="mb-0 ps-3">
              <?php foreach ($students as $i => $s): ?>
                <?php $tok = url_ref_create(["class"=>(string)$cid,"student"=>(string)($s["student_number"] ?? "")]); ?>
                <li><a href="/teacher/classes?ref=<?= htmlspecialchars($tok) ?>"><?= htmlspecialchars($s["full_name"] ?? ($s["name"] ?? "")) ?></a></li>
              <?php endforeach; ?>
            </ol>
          <?php else: ?>
            <div class="text-muted">No students found</div>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <div class="col-md-8">
      <div class="card list-box monitoring-card">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-center mb-2">
            <div class="section-title monitoring-title mb-0">Attendance Monitoring</div>
            <div class="d-flex align-items-center gap-2">
              <?php $tokAll = url_ref_create(["class"=>(string)$cid]); ?>
              <a class="btn btn-light btn-sm btn-view-all" href="/teacher/classes?ref=<?= htmlspecialchars($tokAll) ?>">View All</a>
            </div>
          </div>
          <?php if ($monitorSN !== ""): ?>
            <div class="mb-3">
              <div><strong>Student Number:</strong> <?= htmlspecialchars($monitorSN) ?></div>
              <div><strong>Full Name:</strong> <?= htmlspecialchars($monitorName) ?></div>
            </div>
            <div class="table-responsive">
              <table class="table table-striped table-sm align-middle">
                <thead><tr><th>Date</th><th>Status</th><th>Remarks</th><th>Action</th></tr></thead>
                <tbody>
                  <?php if (is_array($attendanceRows) && count($attendanceRows)>0): ?>
                    <?php foreach ($attendanceRows as $r): ?>
                      <tr>
                        <td><?= htmlspecialchars($r["date"] ?? "") ?></td>
                        <td><?= htmlspecialchars($r["status"] ?? "") ?></td>
                        <td>
                          <div class="d-flex align-items-center gap-2">
                            <span class="text-muted"><?= htmlspecialchars($r["remarks"] ?? "") ?></span>
                            <button type="button" class="btn btn-light btn-sm" data-bs-toggle="modal" data-bs-target="#remarkModal" data-sn="<?= htmlspecialchars((string)($r["student_number"] ?? "")) ?>" data-date="<?= htmlspecialchars((string)($r["date"] ?? "")) ?>">+</button>
                          </div>
                        </td>
                         <td>
                           
                         </td>
                      </tr>
                    <?php endforeach; ?>
                  <?php else: ?>
                    <tr><td colspan="4" class="text-muted">No attendance records</td></tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
            <?php
              $p = 0; $a = 0; $t = 0;
              foreach ($attendanceRows as $rr) {
                $s = strtolower((string)($rr["status"] ?? ""));
                if ($s === "present") $p++;
                elseif ($s === "absent") $a++;
                elseif ($s === "tardy") $t++;
              }
            ?>
            <div class="small"><strong>Summary:</strong> Present <?= htmlspecialchars((string)$p) ?>, Absent <?= htmlspecialchars((string)$a) ?>, Tardy <?= htmlspecialchars((string)$t) ?></div>
            <div class="student-sep"></div>
          <?php else: ?>
            <?php if (is_array($attendanceBySn) && count($attendanceBySn) > 0): ?>
              <?php foreach ($attendanceBySn as $snKey => $grp): ?>
                <div class="mb-3">
                  <div><strong>Student Number:</strong> <?= htmlspecialchars($snKey) ?></div>
                  <div><strong>Full Name:</strong> <?= htmlspecialchars($grp["full_name"] ?? "") ?></div>
                </div>
                <div class="table-responsive mb-0">
                  <table class="table table-striped table-sm align-middle">
                    <thead><tr><th>Date</th><th>Status</th><th>Remarks</th><th>Action</th></tr></thead>
                    <tbody>
                      <?php foreach ($grp["rows"] as $r): ?>
                        <tr>
                          <td><?= htmlspecialchars($r["date"] ?? "") ?></td>
                          <td><?= htmlspecialchars($r["status"] ?? "") ?></td>
                          <td>
                            <div class="d-flex align-items-center gap-2">
                              <span class="text-muted"><?= htmlspecialchars($r["remarks"] ?? "") ?></span>
                              <button type="button" class="btn btn-light btn-sm" data-bs-toggle="modal" data-bs-target="#remarkModal" data-sn="<?= htmlspecialchars((string)($r["student_number"] ?? "")) ?>" data-date="<?= htmlspecialchars((string)($r["date"] ?? "")) ?>">+</button>
                            </div>
                          </td>
                          <td>
                            
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
                <?php
                  $p = 0; $a = 0; $t = 0;
                  foreach ($grp["rows"] as $rr) {
                    $s = strtolower((string)($rr["status"] ?? ""));
                    if ($s === "present") $p++;
                    elseif ($s === "absent") $a++;
                    elseif ($s === "tardy") $t++;
                  }
                ?>
                <div class="small"><strong>Summary:</strong> Present <?= htmlspecialchars((string)$p) ?>, Absent <?= htmlspecialchars((string)$a) ?>, Tardy <?= htmlspecialchars((string)$t) ?></div>
                <div class="student-sep"></div>
              <?php endforeach; ?>
            <?php else: ?>
              <div class="text-muted">No attendance records</div>
            <?php endif; ?>
          <?php endif; ?>
          <?php if (isset($alert) && is_array($alert)): ?>
            <div class="alert alert-<?= htmlspecialchars($alert["type"]) ?> mt-3" role="alert"><?= htmlspecialchars($alert["text"]) ?></div>
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
<div class="modal fade" id="remarkModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-sm">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Add Remark</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="post">
        <div class="modal-body">
          <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf_token) ?>">
          <input type="hidden" name="remark_sn" id="remarkSn">
          <div class="mb-2">
            <label class="form-label small">Date</label>
            <input type="date" class="form-control form-control-sm" name="remark_date" id="remarkDateInput">
          </div>
          <input type="text" class="form-control" name="remark_text" id="remarkText" placeholder="Enter remark">
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary btn-sm">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>
<script>
(function(){
  var modal = document.getElementById('remarkModal');
  if (modal) {
    modal.addEventListener('show.bs.modal', function (event) {
      var btn = event.relatedTarget;
      var sn = btn ? (btn.getAttribute('data-sn') || '') : '';
      document.getElementById('remarkSn').value = sn;
      var dateInput = document.getElementById('remarkDateInput');
      var dt = btn ? (btn.getAttribute('data-date') || '') : '';
      dateInput.value = dt || (new Date().toISOString().slice(0,10));
      document.getElementById('remarkText').value = '';
    });
  }
})();
</script>
</body>
</html>
