<?php
// my_timesheets.php (robust submit-week + display)
require_once __DIR__ . '/includes/db_connect.php';
require_login();

// Daily submission: submit a single timesheet entry
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_day'])) {
    $submit_id = (int)$_POST['submit_day'];
    if ($submit_id > 0) {
        $stmt = $pdo->prepare('SELECT user_id, status, submitted_at FROM timesheets WHERE id = ? LIMIT 1');
        $stmt->execute([$submit_id]);
        $t = $stmt->fetch();
        if (!$t || (int)$t['user_id'] !== (int)$_SESSION['user_id']) {
            $_SESSION['error'] = 'Not found or access denied.';
        } elseif (!empty($t['submitted_at'])) {
            $_SESSION['error'] = 'This timesheet has already been submitted.';
        } else {
            $updateStmt = $pdo->prepare('UPDATE timesheets SET submitted_at = NOW(), status = "pending" WHERE id = ? AND submitted_at IS NULL');
            $updateStmt->execute([$submit_id]);
            if ($updateStmt->rowCount() > 0) {
                $_SESSION['success'] = 'Timesheet submitted successfully.';
            } else {
                $_SESSION['error'] = 'Failed to submit timesheet.';
            }
        }
        header('Location: /employee-timesheet/my_timesheets.php');
        exit;
    }
}

// Delete action (only pending and owned)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $del_id = (int)$_POST['delete_id'];
    if ($del_id > 0) {
        $stmt = $pdo->prepare('SELECT user_id, status, submitted_at FROM timesheets WHERE id = ? LIMIT 1');
        $stmt->execute([$del_id]);
        $t = $stmt->fetch();
        if (!$t || (int)$t['user_id'] !== (int)$_SESSION['user_id']) {
            $_SESSION['error'] = 'Not found or access denied.';
        } elseif ($t['status'] !== 'pending' || !empty($t['submitted_at'])) {
            $_SESSION['error'] = 'Cannot delete a submitted or reviewed timesheet.';
        } else {
            $pdo->prepare('DELETE FROM timesheets WHERE id = ?')->execute([$del_id]);
            $_SESSION['success'] = 'Timesheet deleted.';
        }
        header('Location: /employee-timesheet/my_timesheets.php');
        exit;
    }
}

// Weekly submission: set submitted_at for entries in the chosen week (lock)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_week'])) {

    $week_start_raw = trim($_POST['week_start'] ?? '');
    if (!$week_start_raw) {
        $_SESSION['error'] = 'Week start date required.';
        header('Location: /employee-timesheet/my_timesheets.php');
        exit;
    }

    // Accept the date input; create DateTime and normalize to server timezone
    try {
        $dt = new DateTime($week_start_raw);
    } catch (Exception $e) {
        $_SESSION['error'] = 'Invalid week start date.';
        header('Location: /employee-timesheet/my_timesheets.php');
        exit;
    }

    // Ensure the chosen date is a Monday (or treat it as the week's Monday)
    $dayOfWeek = (int)$dt->format('N'); // 1=Mon ... 7=Sun
    $monday = (clone $dt)->modify('-' . ($dayOfWeek - 1) . ' days')->setTime(0, 0, 0);
    $sunday = (clone $monday)->modify('+6 days')->setTime(23, 59, 59);

    $start = $monday->format('Y-m-d');
    $end = $sunday->format('Y-m-d');

    // Update rows for this user where submitted_at IS NULL (not previously locked)
    $stmt = $pdo->prepare('UPDATE timesheets 
                           SET submitted_at = NOW(), status = "pending" 
                           WHERE user_id = ? AND date BETWEEN ? AND ? AND submitted_at IS NULL');
    $stmt->execute([$_SESSION['user_id'], $start, $end]);
    $affected = $stmt->rowCount();

    if ($affected > 0) {
        $_SESSION['success'] = 'Week submitted (entries locked).';
    } else {
        $_SESSION['error'] = 'No entries found to submit for that week, or they were already submitted.';
    }

    header('Location: /employee-timesheet/my_timesheets.php');
    exit;
}

// fetch timesheets
$stmt = $pdo->prepare('SELECT t.*, p.project_name FROM timesheets t JOIN projects p ON p.id = t.project_id WHERE t.user_id = ? ORDER BY t.date DESC, t.created_at DESC');
$stmt->execute([$_SESSION['user_id']]);
$timesheets = $stmt->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>
<div style="margin-top:1rem;height:77vh">
    <h2>My Timesheets</h2>
    <p style="color:var(--text-muted); margin-bottom:1.5rem;">Manage and submit your timesheet entries</p>

    <div class="form-container" style="margin-bottom:2rem;">
        <form method="post" style="display:flex;gap:1rem;flex-wrap:wrap;align-items:flex-end;">
            <div class="form-group" style="flex:1;min-width:200px;margin-bottom:0;">
                <label>Week Start (Monday)</label>
                <input type="date" name="week_start" required value="<?= htmlspecialchars($_POST['week_start'] ?? date('Y-m-d', strtotime('monday this week')), ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div style="display:flex;gap:0.75rem;align-items:flex-end;">
                <button class="btn btn-primary" name="submit_week" type="submit">Submit Week</button>
            </div>
        </form>
        <p style="color:var(--text-muted);font-size:0.875rem;margin-top:0.75rem;margin-bottom:0;">Submitting sets the week's entries as submitted (locked)</p>
    </div>

    <table class="data-table">
        <thead>
            <tr>
                <th>Date</th>
                <th>Project</th>
                <th>Hours</th>
                <th>Status</th>
                <th>Submitted</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($timesheets)): ?>
            <tr><td colspan="6" style="text-align:center">No timesheets found.</td></tr>
        <?php else: foreach ($timesheets as $t): ?>
            <tr>
                <td><?= htmlspecialchars($t['date'], ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars($t['project_name'], ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars((string)$t['total_hours'], ENT_QUOTES, 'UTF-8') ?></td>
                <td><span class="status status-<?= htmlspecialchars($t['status'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($t['status'], ENT_QUOTES, 'UTF-8') ?></span></td>
                <td>
                    <?php
                        // Display submitted_at in readable format, if present
                        if (!empty($t['submitted_at']) && $t['submitted_at'] !== '0000-00-00 00:00:00') {
                            // Safely format timestamp
                            $ts = strtotime($t['submitted_at']);
                            if ($ts !== false) {
                                $fmt = date('Y-m-d H:i', $ts);
                                echo htmlspecialchars($fmt, ENT_QUOTES, 'UTF-8');
                            } else {
                                echo '-';
                            }
                        } else {
                            echo '-';
                        }
                    ?>
                </td>
                <td>
                    <?php if ($t['status'] === 'pending' && empty($t['submitted_at'])): ?>
                        <form method="post" style="display:inline" onsubmit="return confirm('Submit this timesheet entry? It will be locked after submission.');">
                            <button class="btn btn-small btn-success" type="submit" name="submit_day" value="<?= htmlspecialchars((string)$t['id'], ENT_QUOTES, 'UTF-8') ?>">Submit</button>
                        </form>
                        <a class="btn btn-small btn-primary" href="/employee-timesheet/edit_timesheet.php?id=<?= htmlspecialchars((string)$t['id'], ENT_QUOTES, 'UTF-8') ?>">Edit</a>
                        <form method="post" style="display:inline" onsubmit="return confirm('Delete this timesheet?');">
                            <button class="btn btn-small btn-danger" type="submit" name="delete_id" value="<?= htmlspecialchars((string)$t['id'], ENT_QUOTES, 'UTF-8') ?>">Delete</button>
                        </form>
                    <?php else: ?>
                        <span style="font-size:13px;color:#6c757d">Locked</span>
                    <?php endif; ?>

                    <a class="btn btn-small btn-secondary" href="/employee-timesheet/timesheet_print.php?id=<?= htmlspecialchars((string)$t['id'], ENT_QUOTES, 'UTF-8') ?>" target="_blank">Print</a>
                </td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
