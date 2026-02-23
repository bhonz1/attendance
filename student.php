<?php
require_once __DIR__ . "/lib/supabase.php";
require_once __DIR__ . "/lib/csrf.php";
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
$useSb = sb_url() ? true : false;
$errors = [];
$success = false;
$csrf_token = csrf_token();
function field($name) { return trim((string)($_POST[$name] ?? "")); }
if ($_SERVER["REQUEST_METHOD"] === "POST") {
  if (!csrf_validate($_POST["csrf"] ?? "")) {
    $errors[] = "Invalid form submission.";
  } else {
    $student_number = field("student_number");
    $full_name = field("full_name");
    $class_code = field("course_section");
    $school_year_id = field("school_year");
    if ($student_number === "") $errors[] = "Student Number is required.";
    if ($full_name === "") $errors[] = "Full Name is required.";
    if ($class_code === "") $errors[] = "Course/Section is required.";
    if ($school_year_id === "") $errors[] = "School Year is required.";
    if (empty($errors)) {
      if ($useSb) {
        $existing = sb_get("students", ["select"=>"id","student_number"=>"eq.".$student_number,"limit"=>1]);
        if (is_array($existing) && isset($existing[0])) {
          $errors[] = "Student Number already exists.";
        }
      } else {
        $list = $_SESSION["__students"] ?? [];
        foreach ($list as $row) {
          if (($row["student_number"] ?? "") === $student_number) { $errors[] = "Student Number already exists."; break; }
        }
      }
    }
    if (empty($errors) && $useSb) {
      $payload = [
        "student_number" => $student_number,
        "full_name" => $full_name,
        "class_code" => $class_code,
        "school_year_id" => is_numeric($school_year_id) ? (int)$school_year_id : null
      ];
      $res = sb_post("students", $payload);
      if (is_array($res) && (isset($res["id"]) || isset($res[0]["id"]))) {
        $success = true;
        $_POST = [];
        $csrf_token = csrf_token();
      } else {
        $err = sb_last_error();
        $msg = "Registration failed.";
        if (is_array($err)) {
          $code = $err["code"] ?? null;
          $bj = $err["body_json"] ?? null;
          if ($code === 409) {
            $msg = "Student Number already exists.";
          } elseif ($code === 404) {
            $msg = "Table students not found.";
          } elseif ($code === 401 || $code === 403) {
            $msg = "Unauthorized API access.";
          } elseif ($code === 400) {
            if (is_array($bj) && isset($bj["message"]) && is_string($bj["message"])) {
              $msg = $bj["message"];
            } else {
              $msg = "Invalid request.";
            }
          } elseif (is_array($bj) && isset($bj["message"]) && is_string($bj["message"])) {
            $msg = "Registration failed: " . $bj["message"];
          }
        }
        $errors[] = $msg;
      }
    } elseif (empty($errors)) {
      if (!isset($_SESSION["__students"])) $_SESSION["__students"] = [];
      $id = count($_SESSION["__students"]) + 1;
      $_SESSION["__students"][] = ["id"=>$id,"student_number"=>$student_number,"full_name"=>$full_name,"class_code"=>$class_code,"school_year_id"=>$school_year_id];
      $success = true;
      $_POST = [];
      $csrf_token = csrf_token();
    }
  }
}
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Student Registration</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
:root { --orange-1:#f59e0b; --orange-2:#d97706; --ink:#0f172a; --brand-1:#0ea5e9; --brand-2:#6366f1; }
body { min-height:100vh; background: linear-gradient(180deg, #f8fafc 0%, #eef2ff 100%); }
.header { background: linear-gradient(120deg, var(--brand-1) 0%, var(--brand-2) 45%, #0f172a 100%); color:#fff; border-bottom: 1px solid rgba(255,255,255,0.18); }
.card-elev { border: none; border-radius: 14px; box-shadow: 0 10px 24px rgba(2,6,23,0.08); }
.req::after { content:" *"; color:#ef4444; }
.page-container { max-width: 1400px; }
.ui-title { font-weight: 800; letter-spacing: -0.01em; color: var(--ink); }
.ui-subtitle { color: #475569; }
.side-panel { border-radius: 12px; background: linear-gradient(180deg, #fff7ed 0%, #fff 60%); border: 1px solid #fde68a; }
.form-label { font-weight: 600; color: #334155; }
.form-control { border-radius: 12px; border-color: #e5e7eb; }
.form-control:focus { border-color: #f59e0b; box-shadow: 0 0 0 .2rem #f59e0b33; }
.btn-submit { display:inline-flex; align-items:center; justify-content:center; gap:8px; padding:12px 18px; border-radius:12px; border:none; color:#0c111b; background: linear-gradient(135deg, var(--orange-1) 0%, var(--orange-2) 100%); box-shadow: 0 10px 24px rgba(245,158,11,0.28), 0 6px 12px rgba(245,158,11,0.18); font-weight:700; }
.btn-submit:hover { filter: brightness(1.03) saturate(1.05); box-shadow: 0 14px 30px rgba(245,158,11,0.32), 0 8px 16px rgba(245,158,11,0.22); }
.w-12ch { width: min(100%, 12ch); }
.w-100ch { width: min(100%, 100ch); }
.w-120ch { width: min(100%, 120ch); }
</style>
</head>
<body class="min-vh-100 d-flex flex-column">
<nav class="navbar navbar-expand-lg navbar-dark header">
  <div class="container-fluid">
    <a class="navbar-brand" href="/student">Student Registration</a>
    <div class="d-flex ms-auto">
      <a class="btn btn-light btn-sm" href="/">Back</a>
    </div>
  </div>
</nav>
<main class="container page-container py-4 flex-grow-1">
  <div class="row justify-content-center">
    <div class="col-md-12 col-lg-12">
      <div class="card card-elev">
        <div class="card-body p-4">
          <div class="row g-4 align-items-stretch">
            <div class="col-12">
              <h1 class="ui-title mb-2">Student Registration</h1>
              <p class="ui-subtitle mb-3">Provide student information.</p>
              <?php if ($success): ?>
                <div class="alert alert-success" role="alert">Registration successful.</div>
              <?php endif; ?>
              <?php if (!empty($errors)): ?>
                <div class="alert alert-danger" role="alert">
                  <?php foreach ($errors as $e): ?><div><?= htmlspecialchars($e) ?></div><?php endforeach; ?>
                </div>
              <?php endif; ?>
              <form method="post" action="/student" novalidate>
                <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf_token) ?>">
                <div class="row g-3">
                  <div class="col-12 col-md-4 col-lg-3">
                    <label for="student_number" class="form-label req">Student Number</label>
                    <input type="text" class="form-control w-12ch" id="student_number" name="student_number" placeholder="e.g., 251-0000" maxlength="12" value="<?= htmlspecialchars($_POST["student_number"] ?? "") ?>" required>
                  </div>
                </div>
                <div class="row g-3 mt-1">
                  <div class="col-12">
                    <label for="full_name" class="form-label req">Full Name</label>
                    <input type="text" class="form-control w-120ch" id="full_name" name="full_name" maxlength="160" placeholder="Lastname, First Name, MI" value="<?= htmlspecialchars($_POST["full_name"] ?? "") ?>" required>
                  </div>
                </div>
                <div class="row g-3 mt-1">
                  <div class="col-12 col-md-6">
                    <label for="course_section" class="form-label req">Course/Section</label>
                    <input type="text" class="form-control w-100ch" id="course_section" name="course_section" maxlength="80" placeholder="e.g., BSIT 3A" value="<?= htmlspecialchars($_POST["course_section"] ?? "") ?>" required>
                  </div>
                </div>
                <?php
                  $syOptions = $useSb ? sb_get("school_years", ["select"=>"id,code,description","order"=>"id.desc"]) : ($_SESSION["__school_years_meta"] ?? []);
                  if (!is_array($syOptions)) $syOptions = [];
                ?>
                <div class="row g-3 mt-1">
                  <div class="col-12 col-md-6">
                    <label for="school_year" class="form-label">School Year</label>
                    <select class="form-select w-100ch" id="school_year" name="school_year" required>
                      <option value="">Select School Year</option>
                      <?php foreach ($syOptions as $sy): 
                        $descRaw = trim((string)($sy["description"] ?? ""));
                        $codeRaw = trim((string)($sy["code"] ?? ""));
                        $display = $descRaw !== "" ? $descRaw : $codeRaw;
                        $label = preg_match("/^\\s*SY\\b/i", $display) ? $display : ("SY " . $display);
                        $val = (string)($sy["id"] ?? "");
                      ?>
                        <option value="<?= htmlspecialchars($val) ?>" <?= isset($_POST["school_year"]) && ((string)$_POST["school_year"] === $val) ? "selected" : "" ?>><?= htmlspecialchars($label) ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                </div>
                <div class="mt-3 d-flex justify-content-start">
                  <button type="submit" class="btn-submit">Register</button>
                </div>
              </form>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</main>
<footer class="text-center text-muted small py-3 border-top mt-auto">
  <div class="container">© <?= date("Y") ?> Attendance Tracker | Developed by: Von P. Gabayan Jr.</div>
</footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
