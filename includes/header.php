<?php
// includes/header.php
require_once __DIR__ . '/db_connect.php';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Employee Timesheet - Modern Time Tracking</title>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="description" content="Modern employee timesheet management system">
    <link rel="icon" type="image/svg+xml" href="/employee-timesheet/assets/images/favicon.svg">
    <link rel="apple-touch-icon" href="/employee-timesheet/assets/images/favicon.svg">
    <link rel="stylesheet" href="/employee-timesheet/assets/css/style.css">
</head>
<body>
<nav class="navbar">
    <a class="nav-brand" href="/employee-timesheet/<?= !empty($_SESSION['user_id']) ? 'dashboard.php' : 'index.php' ?>">⏱️ Timesheet</a>
    <ul class="nav-menu">
        <?php if (!empty($_SESSION['user_id'])): ?>

            <li><a href="/employee-timesheet/dashboard.php">Dashboard</a></li>

            <?php if ($_SESSION['role'] !== 'admin'): ?>
                <!-- Only employees see My Timesheets -->
                <li><a href="/employee-timesheet/my_timesheets.php">My Timesheets</a></li>
            <?php endif; ?>

            <?php if (!empty($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                <li><a href="/employee-timesheet/admin/admin_timesheets.php">Admin</a></li>
                <li><a href="/employee-timesheet/admin/manage_projects.php">Projects</a></li>
                <li><a href="/employee-timesheet/admin/manage_employees.php">Employees</a></li>
            <?php endif; ?>

            <li><a href="/employee-timesheet/logout.php">Logout</a></li>

        <?php else: ?>

            <li><a href="/employee-timesheet/index.php">Login</a></li>
            <li><a href="/employee-timesheet/register.php">Register</a></li>

        <?php endif; ?>
    </ul>
</nav>
<main class="container">
<?php
// flash messages
if (!empty($_SESSION['success'])) {
    echo '<div class="success">' . htmlspecialchars((string)$_SESSION['success'], ENT_QUOTES, 'UTF-8') . '</div>';
    unset($_SESSION['success']);
}
if (!empty($_SESSION['error'])) {
    echo '<div class="error">' . htmlspecialchars((string)$_SESSION['error'], ENT_QUOTES, 'UTF-8') . '</div>';
    unset($_SESSION['error']);
}
?>
