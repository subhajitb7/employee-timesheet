<?php
// edit_timesheet.php
require_once __DIR__ . '/includes/db_connect.php';
require_login();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    $_SESSION['error'] = 'Invalid timesheet.';
    header('Location: /employee-timesheet/my_timesheets.php');
    exit;
}

// fetch timesheet and ensure ownership and pending
$stmt = $pdo->prepare('SELECT * FROM timesheets WHERE id = ? LIMIT 1');
$stmt->execute([$id]);
$ts = $stmt->fetch();
if (!$ts || (int)$ts['user_id'] !== (int)$_SESSION['user_id']) {
    $_SESSION['error'] = 'Not found or access denied.';
    header('Location: /employee-timesheet/my_timesheets.php');
    exit;
}
// Block edit when status != pending OR submitted_at not null (locked)
if ($ts['status'] !== 'pending' || !empty($ts['submitted_at'])) {
    $_SESSION['error'] = 'Cannot edit timesheet that is submitted or already reviewed.';
    header('Location: /employee-timesheet/my_timesheets.php');
    exit;
}

// Convert time format from database (HH:MM:SS) to HTML5 time input format (HH:MM)
function formatTimeForInput($time) {
    if (empty($time)) return '';
    // If time already in HH:MM format, return as is
    if (preg_match('/^\d{2}:\d{2}$/', $time)) {
        return $time;
    }
    // If time is in HH:MM:SS format, extract HH:MM
    if (preg_match('/^(\d{2}:\d{2}):\d{2}$/', $time, $matches)) {
        return $matches[1];
    }
    // Try to parse and format
    $parts = explode(':', $time);
    if (count($parts) >= 2) {
        return $parts[0] . ':' . $parts[1];
    }
    return $time;
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $project_id = (int)($_POST['project_id'] ?? 0);
    $date = $_POST['date'] ?? '';
    $start_time = $_POST['start_time'] ?? '';
    $end_time = $_POST['end_time'] ?? '';
    $description = trim($_POST['description'] ?? '');

    if ($project_id <= 0) $errors[] = 'Select project.';
    if (!strtotime($date)) $errors[] = 'Invalid date.';
    // HTML5 time input returns HH:MM format, validate that
    if (!preg_match('/^\d{2}:\d{2}$/', $start_time) || !preg_match('/^\d{2}:\d{2}$/', $end_time)) {
        $errors[] = 'Invalid time format. Please use HH:MM format.';
    }

    if (empty($errors)) {
        $start = DateTime::createFromFormat('H:i', $start_time);
        $end = DateTime::createFromFormat('H:i', $end_time);
        $hours = 0;
        if ($start && $end) {
            $diff = $end->getTimestamp() - $start->getTimestamp();
            $hours = max(0, $diff / 3600);
        }
        $stmt = $pdo->prepare('UPDATE timesheets SET project_id = ?, date = ?, start_time = ?, end_time = ?, total_hours = ?, description = ?, updated_at = NOW() WHERE id = ? AND user_id = ?');
        $stmt->execute([$project_id, $date, $start_time, $end_time, number_format((float)$hours, 2, '.', ''), $description, $id, $_SESSION['user_id']]);

        $_SESSION['success'] = 'Timesheet updated.';
        header('Location: /employee-timesheet/my_timesheets.php');
        exit;
    }
}

// fetch projects
$projects = $pdo->query('SELECT id, project_name FROM projects ORDER BY project_name')->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>
<div class="form-container" style="max-width:720px;margin:2rem auto;height:77vh">
    <h2>Edit Timesheet</h2>
    <p style="color:var(--text-muted); margin-bottom:1.5rem;">Update your timesheet entry details</p>

    <?php if (!empty($errors)): ?>
        <div class="error">
            <ul style="margin-left:18px;margin-top:0.5rem;">
                <?php foreach ($errors as $err): ?>
                    <li><?= htmlspecialchars($err, ENT_QUOTES, 'UTF-8') ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="post" action="">
        <div class="form-group">
            <label>Project</label>
            <select name="project_id" required>
                <option value="">-- Select project --</option>
                <?php foreach ($projects as $p): ?>
                    <option value="<?= htmlspecialchars((string)$p['id'], ENT_QUOTES, 'UTF-8') ?>" <?= ((int)$p['id'] === (int)$ts['project_id']) ? 'selected' : '' ?>><?= htmlspecialchars($p['project_name'], ENT_QUOTES, 'UTF-8') ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label>Date</label>
                <input type="date" name="date" required value="<?= htmlspecialchars($ts['date'], ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div class="form-group">
                <label>Start time</label>
                <input type="time" name="start_time" required value="<?= htmlspecialchars(formatTimeForInput($ts['start_time'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label>End time</label>
                <input type="time" name="end_time" required value="<?= htmlspecialchars(formatTimeForInput($ts['end_time'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div class="form-group">
                <label>Description</label>
                <input type="text" name="description" value="<?= htmlspecialchars($ts['description'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
            </div>
        </div>

        <div class="form-actions">
            <button class="btn btn-primary" type="submit">Save Changes</button>
            <a class="btn btn-secondary" href="/employee-timesheet/my_timesheets.php">Cancel</a>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
