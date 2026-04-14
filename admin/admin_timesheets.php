<?php
// admin/admin_timesheets.php
require_once __DIR__ . '/../includes/db_connect.php';
require_role('admin');

// Individual approve/reject action handling
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && isset($_POST['id'])) {
    $action = $_POST['action'];
    $id = (int)$_POST['id'];
    $admin_remarks = trim($_POST['admin_remarks'] ?? '');
    
    if (!in_array($action, ['approve', 'reject'], true) || $id <= 0) {
        $_SESSION['error'] = 'Invalid action or timesheet ID.';
        header('Location: /employee-timesheet/admin/admin_timesheets.php');
        exit;
    }
    
    $status = $action === 'approve' ? 'approved' : 'rejected';
    $stmt = $pdo->prepare('UPDATE timesheets SET status = ?, admin_remarks = ?, updated_at = NOW() WHERE id = ?');
    $stmt->execute([$status, $admin_remarks, $id]);
    $_SESSION['success'] = 'Timesheet ' . htmlspecialchars($status, ENT_QUOTES, 'UTF-8') . '.';
    header('Location: /employee-timesheet/admin/admin_timesheets.php');
    exit;
}

// Bulk action handling
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action'])) {
    $action = $_POST['bulk_action'];
    $ids = $_POST['selected'] ?? [];
    if (!in_array($action, ['approve','reject'], true) || !is_array($ids) || empty($ids)) {
        $_SESSION['error'] = 'No action or items selected.';
        header('Location: /employee-timesheet/admin/admin_timesheets.php');
        exit;
    }
    $status = $action === 'approve' ? 'approved' : 'rejected';
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $params = array_map('intval', $ids);
    $sql = "UPDATE timesheets SET status = ?, updated_at = NOW() WHERE id IN ($placeholders)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array_merge([$status], $params));
    $_SESSION['success'] = 'Bulk update applied.';
    header('Location: /employee-timesheet/admin/admin_timesheets.php');
    exit;
}

// CSV export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $employee_id = isset($_GET['employee_id']) ? (int)$_GET['employee_id'] : 0;
    $project_id = isset($_GET['project_id']) ? (int)$_GET['project_id'] : 0;
    $status = $_GET['status'] ?? '';
    $date_from = $_GET['date_from'] ?? '';
    $date_to = $_GET['date_to'] ?? '';

    $sql = 'SELECT t.id, u.full_name AS employee, u.email, p.project_name, t.date, t.start_time, t.end_time, t.total_hours, t.status, t.admin_remarks, t.description, t.submitted_at
            FROM timesheets t
            JOIN users u ON u.id = t.user_id
            JOIN projects p ON p.id = t.project_id
            WHERE 1=1';
    $params = [];
    if ($employee_id > 0) {
        $sql .= ' AND u.id = ?'; $params[] = $employee_id;
    }
    if ($project_id > 0) {
        $sql .= ' AND p.id = ?'; $params[] = $project_id;
    }
    if ($status !== '') {
        $sql .= ' AND t.status = ?'; $params[] = $status;
    }
    if ($date_from && strtotime($date_from)) {
        $sql .= ' AND t.date >= ?'; $params[] = $date_from;
    }
    if ($date_to && strtotime($date_to)) {
        $sql .= ' AND t.date <= ?'; $params[] = $date_to;
    }
    $sql .= ' ORDER BY t.date DESC, t.created_at DESC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=timesheets-export-' . date('Ymd-His') . '.csv');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['ID','Employee','Email','Project','Date','Start','End','Hours','Status','Admin Remarks','Description','Submitted At']);
    foreach ($rows as $r) {
        fputcsv($out, [
            $r['id'],
            $r['employee'],
            $r['email'],
            $r['project_name'],
            $r['date'],
            $r['start_time'],
            $r['end_time'],
            $r['total_hours'],
            $r['status'],
            $r['admin_remarks'],
            $r['description'],
            $r['submitted_at']
        ]);
    }
    fclose($out);
    exit;
}

// fetch filter lists
$projects = $pdo->query('SELECT id, project_name FROM projects ORDER BY project_name')->fetchAll();
$employees = $pdo->query('SELECT id, full_name FROM users WHERE role = "employee" ORDER BY full_name')->fetchAll();

// prepare where
$where = ' WHERE 1=1';
$params = [];
if (!empty($_GET['employee_id'])) {
    $where .= ' AND u.id = ?'; $params[] = (int)$_GET['employee_id'];
}
if (!empty($_GET['project_id'])) {
    $where .= ' AND p.id = ?'; $params[] = (int)$_GET['project_id'];
}
if (isset($_GET['status']) && $_GET['status'] !== '') {
    $where .= ' AND t.status = ?'; $params[] = $_GET['status'];
}
if (!empty($_GET['date_from']) && strtotime($_GET['date_from'])) {
    $where .= ' AND t.date >= ?'; $params[] = $_GET['date_from'];
}
if (!empty($_GET['date_to']) && strtotime($_GET['date_to'])) {
    $where .= ' AND t.date <= ?'; $params[] = $_GET['date_to'];
}

$sql = 'SELECT t.*, u.full_name, p.project_name
        FROM timesheets t
        JOIN users u ON u.id = t.user_id
        JOIN projects p ON p.id = t.project_id' . $where . ' ORDER BY t.date DESC, t.created_at DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div style="margin-top:1rem;height:77vh">
    <h2>All Timesheets</h2>
    <p style="color:var(--text-muted); margin-bottom:1.5rem;">Manage and review all employee timesheet entries</p>

    <div class="filters">
        <form method="get" class="filter-form">
            <label>Employee:
                <select name="employee_id">
                    <option value="">All</option>
                    <?php foreach ($employees as $emp): ?>
                        <option value="<?= htmlspecialchars((string)$emp['id'], ENT_QUOTES, 'UTF-8') ?>" <?= (isset($_GET['employee_id']) && (int)$_GET['employee_id'] === (int)$emp['id']) ? 'selected' : '' ?>><?= htmlspecialchars($emp['full_name'], ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label>Project:
                <select name="project_id">
                    <option value="">All</option>
                    <?php foreach ($projects as $p): ?>
                        <option value="<?= htmlspecialchars((string)$p['id'], ENT_QUOTES, 'UTF-8') ?>" <?= (isset($_GET['project_id']) && (int)$_GET['project_id'] === (int)$p['id']) ? 'selected' : '' ?>><?= htmlspecialchars($p['project_name'], ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label>Status:
                <select name="status">
                    <option value="">All</option>
                    <option value="pending" <?= (isset($_GET['status']) && $_GET['status'] === 'pending') ? 'selected' : '' ?>>Pending</option>
                    <option value="approved" <?= (isset($_GET['status']) && $_GET['status'] === 'approved') ? 'selected' : '' ?>>Approved</option>
                    <option value="rejected" <?= (isset($_GET['status']) && $_GET['status'] === 'rejected') ? 'selected' : '' ?>>Rejected</option>
                </select>
            </label>

            <label>From: <input type="date" name="date_from" value="<?= htmlspecialchars($_GET['date_from'] ?? '', ENT_QUOTES, 'UTF-8') ?>"></label>
            <label>To: <input type="date" name="date_to" value="<?= htmlspecialchars($_GET['date_to'] ?? '', ENT_QUOTES, 'UTF-8') ?>"></label>

            <button class="btn btn-primary" type="submit">Filter</button>
            <a class="btn btn-secondary" href="/employee-timesheet/admin/admin_timesheets.php">Reset</a>

            <a class="btn btn-primary" style="margin-left:auto" href="<?= '/employee-timesheet/admin/admin_timesheets.php?export=csv' .
                (isset($_GET['employee_id']) && $_GET['employee_id'] !== '' ? '&employee_id=' . (int)$_GET['employee_id'] : '') .
                (isset($_GET['project_id']) && $_GET['project_id'] !== '' ? '&project_id=' . (int)$_GET['project_id'] : '') .
                (isset($_GET['status']) && $_GET['status'] !== '' ? '&status=' . urlencode($_GET['status']) : '') .
                (isset($_GET['date_from']) && $_GET['date_from'] ? '&date_from=' . urlencode($_GET['date_from']) : '') .
                (isset($_GET['date_to']) && $_GET['date_to'] ? '&date_to=' . urlencode($_GET['date_to']) : '') ?>">Export CSV</a>
        </form>
    </div>

    <form method="post" id="bulkForm">
        <div style="display:flex;gap:8px;align-items:center;margin:8px 0">
            <select name="bulk_action" required>
                <option value="">Bulk action</option>
                <option value="approve">Approve selected</option>
                <option value="reject">Reject selected</option>
            </select>
            <button class="btn btn-primary" type="submit">Apply</button>
        </div>

        <table class="data-table" style="margin-top:12px">
            <thead>
                <tr>
                    <th><input type="checkbox" id="select-all"></th>
                    <th>Employee</th>
                    <th>Date</th>
                    <th>Project</th>
                    <th>Hours</th>
                    <th>Status</th>
                    <th>Remarks</th>
                    <th>Submitted</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($rows)): ?>
                <tr><td colspan="9" style="text-align:center">No timesheets found.</td></tr>
            <?php else: foreach ($rows as $r): ?>
                <tr>
                    <td><input class="timesheet-checkbox" type="checkbox" name="selected[]" value="<?= htmlspecialchars((string)$r['id'], ENT_QUOTES, 'UTF-8') ?>"></td>
                    <td><?= htmlspecialchars($r['full_name'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($r['date'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($r['project_name'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars((string)$r['total_hours'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><span class="status status-<?= htmlspecialchars($r['status'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($r['status'], ENT_QUOTES, 'UTF-8') ?></span></td>
                    <td><?= htmlspecialchars($r['admin_remarks'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                    <td>
                        <?php
                        if (!empty($r['submitted_at']) && $r['submitted_at'] !== '0000-00-00 00:00:00') {
                            $ts = strtotime($r['submitted_at']);
                            if ($ts !== false) {
                                echo htmlspecialchars(date('Y-m-d H:i', $ts), ENT_QUOTES, 'UTF-8');
                            } else {
                                echo htmlspecialchars($r['submitted_at'], ENT_QUOTES, 'UTF-8');
                            }
                        } else {
                            echo '-';
                        }
                        ?>
                    </td>
                    <td>
                        <form method="post" style="display:inline" onsubmit="return confirm('Approve this timesheet?');">
                            <input type="hidden" name="id" value="<?= htmlspecialchars((string)$r['id'], ENT_QUOTES, 'UTF-8') ?>">
                            <input type="hidden" name="action" value="approve">
                            <button class="btn btn-small btn-primary" type="submit">Approve</button>
                        </form>

                        <form method="post" style="display:inline" onsubmit="return confirm('Reject this timesheet?');">
                            <input type="hidden" name="id" value="<?= htmlspecialchars((string)$r['id'], ENT_QUOTES, 'UTF-8') ?>">
                            <input type="hidden" name="action" value="reject">
                            <button class="btn btn-small btn-danger" type="submit">Reject</button>
                        </form>

                        <button class="btn btn-small btn-secondary" onclick="openModal(
                            '<?= htmlspecialchars((string)$r['id'], ENT_QUOTES, 'UTF-8') ?>',
                            '<?= htmlspecialchars($r['full_name'], ENT_QUOTES, 'UTF-8') ?>',
                            '<?= htmlspecialchars($r['project_name'], ENT_QUOTES, 'UTF-8') ?>',
                            '<?= htmlspecialchars($r['description'] ?? '', ENT_QUOTES, 'UTF-8') ?>',
                            '<?= htmlspecialchars($r['admin_remarks'] ?? '', ENT_QUOTES, 'UTF-8') ?>'
                        )">Review</button>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </form>
</div>

<!-- Modal markup -->
<div id="reviewModal" class="modal" role="dialog" aria-hidden="true">
    <div class="modal-content">
        <span class="close" onclick="closeModal()">&times;</span>
        <h3>Review Timesheet</h3>
        <div id="modalDetails"></div>
        <form method="post">
            <input type="hidden" id="modalTimesheetId" name="id" value="">
            <div class="form-group">
                <label>Admin remarks</label>
                <textarea id="modalRemarks" name="admin_remarks" rows="4" style="width:100%"></textarea>
            </div>
            <div class="form-actions">
                <button class="btn btn-primary" name="action" value="approve" type="submit">Approve</button>
                <button class="btn btn-danger" name="action" value="reject" type="submit">Reject</button>
                <button class="btn btn-secondary" type="button" onclick="closeModal()">Cancel</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
