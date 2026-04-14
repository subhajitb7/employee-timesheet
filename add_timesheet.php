<?php
// add_timesheet.php
require_once __DIR__ . '/includes/db_connect.php';
require_login();

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $project_id = (int)($_POST['project_id'] ?? 0);
    $date = $_POST['date'] ?? '';
    $start_time = $_POST['start_time'] ?? '';
    $end_time = $_POST['end_time'] ?? '';
    $description = trim($_POST['description'] ?? '');

    if ($project_id <= 0) $errors[] = 'Select a project.';
    if (!strtotime($date)) $errors[] = 'Invalid date.';
    if (!preg_match('/^\d{2}:\d{2}$/', $start_time) || !preg_match('/^\d{2}:\d{2}$/', $end_time)) {
        $errors[] = 'Invalid time format.';
    }

    if (empty($errors)) {
        $start = DateTime::createFromFormat('H:i', $start_time);
        $end = DateTime::createFromFormat('H:i', $end_time);
        if ($start && $end) {
            $diff = $end->getTimestamp() - $start->getTimestamp();
            $hours = max(0, $diff / 3600);
        } else {
            $hours = 0;
        }

        $stmt = $pdo->prepare('INSERT INTO timesheets (user_id, project_id, date, start_time, end_time, total_hours, description, status, submitted_at) VALUES (?, ?, ?, ?, ?, ?, ?, "pending", NULL)');
        $stmt->execute([$_SESSION['user_id'], $project_id, $date, $start_time, $end_time, number_format((float)$hours, 2, '.', ''), $description]);

        $_SESSION['success'] = 'Timesheet submitted.';
        header('Location: /employee-timesheet/my_timesheets.php');
        exit;
    }
}

// fetch projects
$projects = $pdo->query('SELECT id, project_name FROM projects ORDER BY project_name')->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>
<div style="height:77vh">
<div class="form-container" style="max-width:720px;margin:2rem auto;">
    <h2>New Timesheet Entry</h2>
    <p style="color:var(--text-muted); margin-bottom:1.5rem;">Add a new timesheet entry for your work</p>

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
                    <option value="<?= htmlspecialchars((string)$p['id'], ENT_QUOTES, 'UTF-8') ?>" <?= (isset($_POST['project_id']) && (int)$_POST['project_id'] === (int)$p['id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($p['project_name'], ENT_QUOTES, 'UTF-8') ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label>Date</label>
                <input type="date" name="date" required value="<?= htmlspecialchars($_POST['date'] ?? date('Y-m-d'), ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div class="form-group">
                <label>Start time</label>
                <input type="time" name="start_time" required value="<?= htmlspecialchars($_POST['start_time'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label>End time</label>
                <input type="time" name="end_time" required value="<?= htmlspecialchars($_POST['end_time'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div class="form-group">
                <label>Description</label>
                <input type="text" name="description" value="<?= htmlspecialchars($_POST['description'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
            </div>
        </div>

        <div class="form-actions">
            <button class="btn btn-primary" type="submit">Create Entry</button>
            <a class="btn btn-secondary" href="/employee-timesheet/my_timesheets.php">Cancel</a>
        </div>
    </form>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
