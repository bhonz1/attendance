<?php
require_once __DIR__ . "/../lib/auth.php";
require_once __DIR__ . "/../lib/supabase.php";
require_once __DIR__ . "/../lib/csrf.php";
require_teacher_session();
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
$name = $_SESSION["teacher_name"] ?? "Teacher";
$useSb = sb_url() ? true : false;
$rows = [];
if ($useSb) {
  $r = sb_get("students", ["select"=>"id,student_number,full_name,class_code", "order"=>"id.desc"]);
  if (is_array($r)) $rows = $r;
} else {
  $r = $_SESSION["__students"] ?? [];
  if (is_array($r)) $rows = $r;
}
$students = array_map(function($s){
  return [
    "id" => $s["id"] ?? null,
    "student_number" => $s["student_number"] ?? "",
    "full_name" => $s["full_name"] ?? ($s["name"] ?? ""),
    "class_code" => $s["class_code"] ?? ""
  ];
}, $rows);
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Students</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
:root { --brand-1:#0ea5e9; --brand-2:#6366f1; --ink:#0f172a; }
body { min-height:100vh; background: linear-gradient(180deg, #f8fafc 0%, #eef2ff 100%); }
.t-hero { position: relative; padding: 28px 0; background: linear-gradient(120deg, var(--brand-1) 0%, var(--brand-2) 40%, #0f172a 100%); color: #ffffff; border-bottom: 1px solid rgba(255,255,255,0.18); }
.t-hero__title { font-weight: 800; line-height: 1.1; font-size: clamp(24px, 5.5vw, 40px); letter-spacing: -0.01em; }
.card-elev { border: none; border-radius: 14px; box-shadow: 0 10px 24px rgba(2,6,23,0.08); }
</style>
</head>
<body class="min-vh-100 d-flex flex-column">
<?php $teacher_nav_active = "students"; include __DIR__ . "/teacher_nav.php"; ?>
<div class="t-hero">
  <div class="container">
    <div class="d-flex align-items-center justify-content-between">
      <div>
        <div class="t-hero__title">Students</div>
        <div class="small opacity-75">Welcome <?= htmlspecialchars($name) ?></div>
      </div>
      <div class="d-flex align-items-center gap-2"></div>
    </div>
  </div>
</div>
<main class="container py-4 flex-grow-1">
  <div class="card card-elev">
    <div class="card-body">
      <div class="table-responsive">
        <table class="table table-striped table-sm align-middle">
          <thead>
            <tr>
              <th>Student Number</th>
              <th>Full Name</th>
              <th>Course/Section</th>
            </tr>
          </thead>
          <tbody>
            <?php if (is_array($students) && count($students) > 0): ?>
              <?php foreach ($students as $s): ?>
                <tr>
                  <td><?= htmlspecialchars($s["student_number"] ?? "") ?></td>
                  <td><?= htmlspecialchars($s["full_name"] ?? "") ?></td>
                  <td><?= htmlspecialchars($s["class_code"] ?? "") ?></td>
                </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr><td colspan="3" class="text-muted">No students found</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
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
