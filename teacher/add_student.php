<?php
require_once __DIR__ . "/../lib/auth.php";
require_once __DIR__ . "/../lib/supabase.php";
require_once __DIR__ . "/../lib/csrf.php";
require_once __DIR__ . "/../lib/urlref.php";
require_teacher_session();
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
$name = $_SESSION["teacher_name"] ?? "Teacher";
$useSb = sb_url() ? true : false;
$tid = $_SESSION["teacher_id"] ?? null;
$alert = null;
if (isset($_SESSION["__add_student_alert"]) && is_array($_SESSION["__add_student_alert"])) { $alert = $_SESSION["__add_student_alert"]; unset($_SESSION["__add_student_alert"]); }
$cid = $_GET["class"] ?? ($_GET["id"] ?? "");
$cid = is_string($cid) ? preg_replace('/[^0-9]/', '', $cid) : $cid;
$next = $_GET["return"] ?? "";
$refTok = $_GET["ref"] ?? "";
$defaultNextTok = url_ref_create(["class"=>(string)$cid]);
$defaultNext = "/teacher/classes.php?ref=" . $defaultNextTok;
if ($refTok !== "") {
  $ref = url_ref_consume($refTok);
  if (is_array($ref)) {
    $toClass = $ref["class"] ?? null;
    if ($toClass !== null) {
      $cid = is_string($toClass) ? preg_replace('/[^0-9]/', '', $toClass) : $toClass;
      $defaultNext = "/teacher/classes.php?class=" . urlencode((string)$cid);
    }
  }
}
if (!is_string($next) || $next === "" || strpos($next, "/") !== 0) { $next = $defaultNext; }
$classRec = null;
if ($cid !== "" && $useSb) {
  $r = sb_get("class_schedule", ["select"=>"id,subject_description,time,day,room,class,class_code,type,schoolyear_id,teacher_id", "id"=>"eq.".$cid, "limit"=>1]);
  if (is_array($r) && isset($r[0])) {
    $rec = $r[0];
    if ((string)($rec["teacher_id"] ?? "") === (string)$tid) $classRec = $rec;
  }
}
if ($cid !== "" && $classRec === null) {
  $rowsCS = $_SESSION["__class_schedules"] ?? [];
  if (is_array($rowsCS)) {
    foreach ($rowsCS as $row) {
      if ((string)($row["id"] ?? "") === (string)$cid && (string)($row["teacher_id"] ?? "") === (string)$tid) { $classRec = $row; break; }
    }
  }
}
$subject = $classRec["subject_description"] ?? "";
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
$schoolyearLabel = "";
if (is_string($schoolyearDesc) && $schoolyearDesc !== "") {
  $schoolyearLabel = preg_match("/^\\s*SY\\b/i", $schoolyearDesc) ? $schoolyearDesc : ("SY " . $schoolyearDesc);
}
// Fetch students filtered by Class (matches Course/Section/class_code)
$rows = [];
if ($useSb) {
  $params = ["select"=>"id,student_number,full_name,class_code,school_year_id", "order"=>"full_name.asc"];
  if (is_string($className) && strlen(trim($className))>0) {
    $params["class_code"] = "eq." . $className;
  }
  if ($syId !== null) {
    $params["school_year_id"] = "eq." . $syId;
  }
  $r = sb_get("students", $params);
  if (is_array($r)) $rows = $r;
} else {
  $r = $_SESSION["__students"] ?? [];
  if (is_array($r)) {
    if (is_string($className) && strlen(trim($className))>0) {
      $r = array_values(array_filter($r, function($row) use ($className){
        return (string)($row["class_code"] ?? "") === (string)$className;
      }));
    }
    if ($syId !== null) {
      $r = array_values(array_filter($r, function($row) use ($syId, $schoolyearDesc){
        $sid = $row["school_year_id"] ?? null;
        $slabel = $row["school_year"] ?? "";
        if ($sid !== null && (string)$sid === (string)$syId) return true;
        if ($slabel !== "" && (string)$slabel === (string)$schoolyearDesc) return true;
        return false;
      }));
    }
    usort($r, function($a, $b) {
      $af = trim((string)($a["full_name"] ?? ($a["name"] ?? "")));
      $bf = trim((string)($b["full_name"] ?? ($b["name"] ?? "")));
      return strcasecmp($af, $bf);
    });
    $rows = $r;
  }
}
$students = array_map(function($s){
  return [
    "id" => $s["id"] ?? null,
    "student_number" => $s["student_number"] ?? "",
    "full_name" => $s["full_name"] ?? ($s["name"] ?? ""),
    "class_code" => $s["class_code"] ?? "",
    "school_year_id" => $s["school_year_id"] ?? null
  ];
}, $rows);
// students fetching moved below after class details to apply filtering
$enrolledSet = [];
if ($code !== "") {
  if ($useSb) {
    $cs = sb_get("class_students", ["select"=>"student_number,class_code", "class_code"=>"eq.".$code]);
    if (is_array($cs)) {
      foreach ($cs as $row) {
        $sn = (string)($row["student_number"] ?? "");
        if ($sn !== "") $enrolledSet[] = $sn;
      }
    }
  } else {
    $cs = $_SESSION["__class_students"] ?? [];
    if (is_array($cs)) {
      foreach ($cs as $row) {
        if ((string)($row["class_code"] ?? "") === (string)$code) {
          $sn = (string)($row["student_number"] ?? "");
          if ($sn !== "") $enrolledSet[] = $sn;
        }
      }
    }
  }
}
$enrolledSet = array_values(array_unique(array_map("strval", $enrolledSet)));
if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $alert = null;
  $sel = isset($_POST["students"]) && is_array($_POST["students"]) ? $_POST["students"] : [];
  $tok = $_POST["csrf"] ?? "";
  if (!csrf_validate($tok)) {
    $alert = ["type"=>"danger","text"=>"Invalid form submission."];
  } else if (count($sel) === 0) {
    $alert = ["type"=>"warning","text"=>"No students selected."];
  } else {
    $payload = [];
    foreach ($sel as $v) {
      $sn = null;
      foreach ($students as $s) {
        if ((string)($s["id"] ?? "") === (string)$v || (string)($s["student_number"] ?? "") === (string)$v) { $sn = $s["student_number"] ?? null; break; }
      }
      if ($sn && $code !== "") $payload[] = ["class_code"=>$code, "student_number"=>$sn];
    }
    if (count($payload) > 0) {
      if ($useSb) {
        $res = sb_upsert("class_students", $payload, "class_code,student_number");
        if ($res === null) {
          $alert = ["type"=>"danger","text"=>"Add failed"];
        } else {
          $_SESSION["__add_student_alert"] = ["type"=>"success","text"=>"Student(s) added to class"];
          require_once __DIR__ . "/../lib/roles.php";
          http_redirect($next);
        }
      } else {
        if (!isset($_SESSION["__class_students"]) || !is_array($_SESSION["__class_students"])) $_SESSION["__class_students"] = [];
        foreach ($payload as $row) {
          $exists = false;
          foreach ($_SESSION["__class_students"] as $cs) {
            if ((string)($cs["class_code"] ?? "") === (string)$row["class_code"] && (string)($cs["student_number"] ?? "") === (string)$row["student_number"]) { $exists = true; break; }
          }
          if (!$exists) {
            $idNew = count($_SESSION["__class_students"]) + 1;
            $_SESSION["__class_students"][] = ["id"=>$idNew, "class_code"=>$row["class_code"], "student_number"=>$row["student_number"]];
          }
        }
        $_SESSION["__add_student_alert"] = ["type"=>"success","text"=>"Student(s) added to class"];
        require_once __DIR__ . "/../lib/roles.php";
        http_redirect($next);
      }
    } else {
      $alert = ["type"=>"warning","text"=>"No valid students selected."];
    }
  }
}
$csrf_token = csrf_token();
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Add Student</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
:root { --brand-1:#0ea5e9; --brand-2:#6366f1; --ink:#0f172a; }
body { min-height:100vh; background: linear-gradient(180deg, #f8fafc 0%, #eef2ff 100%); }
.t-hero { position: relative; padding: 28px 0; background: linear-gradient(120deg, var(--brand-1) 0%, var(--brand-2) 40%, #0f172a 100%); color: #ffffff; border-bottom: 1px solid rgba(255,255,255,0.18); }
.t-hero__title { font-weight: 800; line-height: 1.1; font-size: clamp(24px, 5.5vw, 40px); letter-spacing: -0.01em; }
.card-elev { border: none; border-radius: 14px; box-shadow: 0 10px 24px rgba(2,6,23,0.08); }
.details-table td:nth-child(2), .details-table td:nth-child(4) { font-weight: 700; }
</style>
</head>
<body class="min-vh-100 d-flex flex-column">
<?php $teacher_nav_active = "students"; include __DIR__ . "/teacher_nav.php"; ?>
<div class="t-hero">
  <div class="container">
    <div class="d-flex align-items-center justify-content-between">
      <div>
        <div class="t-hero__title">Add Student</div>
      </div>
      <div class="d-flex align-items-center gap-2">
        <a class="btn btn-outline-light btn-sm" href="/teacher/dashboard">Back</a>
      </div>
    </div>
  </div>
</div>
<main class="container py-4 flex-grow-1">
  <?php if ($classRec !== null): ?>
  <div class="card card-elev mb-3">
    <div class="card-body">
      <div class="table-responsive">
        <table class="table table-striped table-sm align-middle details-table">
          <tbody>
            <tr><td>Class Code</td><td><?= htmlspecialchars($code) ?></td><td>School Year</td><td><?= htmlspecialchars($schoolyearLabel) ?></td></tr>
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
        <div class="table-responsive">
          <table class="table table-striped table-sm align-middle">
            <thead>
              <tr>
                <th style="width:36px"><input type="checkbox" onclick="document.querySelectorAll('.stu-check').forEach(cb=>cb.checked=this.checked)"></th>
                <th>Student Number</th>
                <th>Full Name</th>
                <th>Course/Section</th>
              </tr>
            </thead>
            <tbody>
              <?php if (is_array($students) && count($students) > 0): ?>
                <?php foreach ($students as $s): ?>
                  <tr>
                    <?php $isEn = in_array((string)($s["student_number"] ?? ""), $enrolledSet, true); ?>
                    <td><?php if (!$isEn): ?><input class="form-check-input stu-check" type="checkbox" name="students[]" value="<?= htmlspecialchars((string)($s['id'] ?? ($s['student_number'] ?? ''))) ?>"><?php endif; ?></td>
                    <td><?= htmlspecialchars($s["student_number"] ?? "") ?></td>
                    <td><?= htmlspecialchars($s["full_name"] ?? "") ?></td>
                    <td><?= htmlspecialchars($s["class_code"] ?? "") ?></td>
                  </tr>
                <?php endforeach; ?>
              <?php else: ?>
                <tr><td colspan="4" class="text-muted">No students found</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
        <div class="d-flex justify-content-center gap-2 mt-3">
          <button type="submit" class="btn btn-primary">Add Student</button>
          <a href="<?= htmlspecialchars($next) ?>" class="btn btn-outline-secondary">Cancel</a>
        </div>
      </form>
    </div>
  </div>
</main>
<footer class="text-center text-muted small py-3 border-top mt-auto">
  <div class="container">© <?= date("Y") ?> Attendance Tracker | Developed by: Von P. Gabayan Jr.</div>
</footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
