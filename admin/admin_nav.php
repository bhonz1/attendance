<?php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
$active = $admin_nav_active ?? "";
?>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
  <div class="container-fluid">
    <a class="navbar-brand" href="/admin/dashboard">Admin</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#adminTopNav" aria-controls="adminTopNav" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="adminTopNav">
      <ul class="navbar-nav me-auto mb-2 mb-lg-0">
        <li class="nav-item"><a class="nav-link<?= ($active === 'settings' ? ' active' : '') ?>" href="/admin/setting">Settings</a></li>
        <li class="nav-item"><a class="nav-link<?= ($active === 'teacher' ? ' active' : '') ?>" href="/admin/teacher">Teacher</a></li>
        <li class="nav-item"><a class="nav-link<?= ($active === 'profile' ? ' active' : '') ?>" href="/admin/profile">Profile</a></li>
      </ul>
      <div class="d-flex">
        <a class="btn btn-outline-light btn-sm" href="/admin/logout">Logout</a>
      </div>
    </div>
  </div>
</nav>
