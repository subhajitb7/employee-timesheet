<?php
// timesheet_print.php
require_once __DIR__ . '/includes/db_connect.php';
require_login();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    echo 'Invalid timesheet.';
    exit;
}
$stmt = $pdo->prepare('SELECT t.*, p.project_name, u.full_name, u.email FROM timesheets t JOIN projects p ON p.id = t.project_id JOIN users u ON u.id = t.user_id WHERE t.id = ? LIMIT 1');
$stmt->execute([$id]);
$t = $stmt->fetch();
if (!$t) {
    echo 'Timesheet not found.';
    exit;
}

// allow access to owner or admin
if ((int)$t['user_id'] !== (int)($_SESSION['user_id'] ?? 0) && ($_SESSION['role'] ?? '') !== 'admin') {
    echo 'Access denied.';
    exit;
}

// simple inline print styles - keep minimal for PDF-friendly rendering
?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Timesheet #<?= htmlspecialchars((string)$t['id'], ENT_QUOTES, 'UTF-8') ?> - Print</title>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <style>
        body { font-family: Arial, sans-serif; color:#222; padding:20px; }
        .card { border: 1px solid #ccc; padding:16px; margin-bottom:12px; border-radius:6px; }
        h1 { font-size:20px; margin-bottom:8px; }
        table { width:100%; border-collapse: collapse; margin-top:8px; }
        td, th { padding:8px; border:1px solid #ddd; text-align:left; }
        .meta { margin-bottom:12px; }
        @media print { button#printBtn { display:none } }
    </style>
</head>
<body>
    <div class="card">
        <h1>Timesheet #<?= htmlspecialchars((string)$t['id'], ENT_QUOTES, 'UTF-8') ?></h1>
        <div class="meta">
            <strong>Employee:</strong> <?= htmlspecialchars($t['full_name'], ENT_QUOTES, 'UTF-8') ?> (<?= htmlspecialchars($t['email'], ENT_QUOTES, 'UTF-8') ?>)<br>
            <strong>Project:</strong> <?= htmlspecialchars($t['project_name'], ENT_QUOTES, 'UTF-8') ?><br>
            <strong>Date:</strong> <?= htmlspecialchars($t['date'], ENT_QUOTES, 'UTF-8') ?><br>
            <strong>Submitted:</strong> <?= htmlspecialchars($t['submitted_at'] ?? '—', ENT_QUOTES, 'UTF-8') ?><br>
            <strong>Status:</strong> <?= htmlspecialchars($t['status'], ENT_QUOTES, 'UTF-8') ?>
        </div>

        <table>
            <tr><th>Start</th><th>End</th><th>Total Hours</th></tr>
            <tr>
                <td><?= htmlspecialchars($t['start_time'], ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars($t['end_time'], ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars((string)$t['total_hours'], ENT_QUOTES, 'UTF-8') ?></td>
            </tr>
        </table>

        <h3 style="margin-top:14px">Description</h3>
        <div><?= nl2br(htmlspecialchars((string)($t['description'] ?? ''), ENT_QUOTES, 'UTF-8')) ?></div>

        <?php if (!empty($t['admin_remarks'])): ?>
            <h3 style="margin-top:14px">Admin Remarks</h3>
            <div><?= nl2br(htmlspecialchars((string)$t['admin_remarks'], ENT_QUOTES, 'UTF-8')) ?></div>
        <?php endif; ?>

        <div style="margin-top:18px">
            <button id="printBtn" onclick="window.print()">Print / Save as PDF</button>
        </div>
    </div>
</body>
</html>
