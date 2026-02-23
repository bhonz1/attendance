<?php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
$active = $teacher_nav_active ?? "";
?>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
  <div class="container-fluid">
    <a class="navbar-brand" href="/teacher/dashboard.php">Teacher</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#teacherNav" aria-controls="teacherNav" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="teacherNav">
      <ul class="navbar-nav me-auto mb-2 mb-lg-0">
        <li class="nav-item"><a class="nav-link<?= ($active === "students" ? " active" : "") ?>" href="/teacher/view_student.php">Students</a></li>
        <li class="nav-item"><a class="nav-link<?= ($active === "profile" ? " active" : "") ?>" href="/teacher/profile.php">Profile</a></li>
      </ul>
      <div class="d-flex">
        <a class="btn btn-outline-light btn-sm" href="/teacher/logout.php">Logout</a>
      </div>
    </div>
  </div>
</nav>
