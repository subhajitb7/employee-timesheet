<?php
// admin/reports.php
require_once __DIR__ . '/../includes/db_connect.php';
require_role('admin');

// Fetch summary: hours per project (existing)
$summaryStmt = $pdo->query('SELECT p.id, p.project_name, IFNULL(SUM(t.total_hours),0) AS total_hours, COUNT(t.id) AS entries
                            FROM projects p
                            LEFT JOIN timesheets t ON t.project_id = p.id
                            GROUP BY p.id, p.project_name
                            ORDER BY total_hours DESC');
$summary = $summaryStmt->fetchAll();

// Add summary: hours per employee
$employeeSummaryStmt = $pdo->query('SELECT u.id, u.full_name, IFNULL(SUM(t.total_hours),0) AS total_hours, COUNT(t.id) AS entries
                                   FROM users u
                                   LEFT JOIN timesheets t ON t.user_id = u.id
                                   WHERE u.role = "employee"
                                   GROUP BY u.id, u.full_name
                                   ORDER BY total_hours DESC');
$employeeSummary = $employeeSummaryStmt->fetchAll();

// Projects & employees list for filtering/export
$projects = $pdo->query('SELECT id, project_name FROM projects ORDER BY project_name')->fetchAll();
$employees = $pdo->query('SELECT id, full_name FROM users WHERE role = "employee" ORDER BY full_name')->fetchAll();

// CSV export for reports: by project, employee, status or date range
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $project_id = isset($_GET['project_id']) ? (int)$_GET['project_id'] : 0;
    $employee_id = isset($_GET['employee_id']) ? (int)$_GET['employee_id'] : 0;
    $status = $_GET['status'] ?? '';
    $date_from = $_GET['date_from'] ?? '';
    $date_to = $_GET['date_to'] ?? '';

    $sql = 'SELECT t.id, u.full_name, p.project_name, t.date, t.start_time, t.end_time, t.total_hours, t.status, t.admin_remarks, t.description
            FROM timesheets t
            JOIN users u ON u.id = t.user_id
            JOIN projects p ON p.id = t.project_id
            WHERE 1=1';
    $params = [];

    if ($project_id > 0) { $sql .= ' AND p.id = ?'; $params[] = $project_id; }
    if ($employee_id > 0) { $sql .= ' AND u.id = ?'; $params[] = $employee_id; }
    if ($status !== '') { $sql .= ' AND t.status = ?'; $params[] = $status; }
    if ($date_from && strtotime($date_from)) { $sql .= ' AND t.date >= ?'; $params[] = $date_from; }
    if ($date_to && strtotime($date_to)) { $sql .= ' AND t.date <= ?'; $params[] = $date_to; }
    $sql .= ' ORDER BY t.date DESC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=report-' . date('Ymd-His') . '.csv');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['ID','Employee','Project','Date','Start','End','Hours','Status','Admin Remarks','Description']);
    foreach ($rows as $r) {
        fputcsv($out, [
            $r['id'],
            $r['full_name'],
            $r['project_name'],
            $r['date'],
            $r['start_time'],
            $r['end_time'],
            $r['total_hours'],
            $r['status'],
            $r['admin_remarks'],
            $r['description']
        ]);
    }
    fclose($out);
    exit;
}

// Fetch list for the table view (with filters)
$where = ' WHERE 1=1';
$params = [];
if (!empty($_GET['project_id'])) { $where .= ' AND p.id = ?'; $params[] = (int)$_GET['project_id']; }
if (!empty($_GET['employee_id'])) { $where .= ' AND u.id = ?'; $params[] = (int)$_GET['employee_id']; }
if (isset($_GET['status']) && $_GET['status'] !== '') { $where .= ' AND t.status = ?'; $params[] = $_GET['status']; }
if (!empty($_GET['date_from']) && strtotime($_GET['date_from'])) { $where .= ' AND t.date >= ?'; $params[] = $_GET['date_from']; }
if (!empty($_GET['date_to']) && strtotime($_GET['date_to'])) { $where .= ' AND t.date <= ?'; $params[] = $_GET['date_to']; }

$sql = 'SELECT t.*, u.full_name, p.project_name
        FROM timesheets t
        JOIN users u ON u.id = t.user_id
        JOIN projects p ON p.id = t.project_id' . $where . ' ORDER BY t.date DESC, t.created_at DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div style="margin-top:1rem;">
    <h2>Reports & Analytics</h2>
    <p style="color:var(--text-muted); margin-bottom:1.5rem;">View timesheet summaries and generate reports</p>

    <div class="stats-grid" style="margin-bottom:20px">
        <?php foreach ($summary as $s): ?>
            <div class="stat-card">
                <h3><?= htmlspecialchars($s['project_name'], ENT_QUOTES, 'UTF-8') ?></h3>
                <div class="stat-number"><?= htmlspecialchars((string)number_format((float)$s['total_hours'],2), ENT_QUOTES, 'UTF-8') ?></div>
                <div class="help-text"><?= htmlspecialchars((string)$s['entries'], ENT_QUOTES, 'UTF-8') ?> entries</div>
            </div>
        <?php endforeach; ?>
    </div>

    <h3>Totals per Employee</h3>
    <div class="stats-grid" style="margin-bottom:20px">
        <?php foreach ($employeeSummary as $es): ?>
            <div class="stat-card">
                <h3><?= htmlspecialchars($es['full_name'], ENT_QUOTES, 'UTF-8') ?></h3>
                <div class="stat-number"><?= htmlspecialchars((string)number_format((float)$es['total_hours'],2), ENT_QUOTES, 'UTF-8') ?></div>
                <div class="help-text"><?= htmlspecialchars((string)$es['entries'], ENT_QUOTES, 'UTF-8') ?> entries</div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="form-container">
        <h3>Filter & Export</h3>
        <form method="get" class="filter-form" style="background: transparent; border: none; padding: 0;">
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

            <button class="btn btn-primary" type="submit">Apply</button>
            <a class="btn btn-primary" href="<?= '/employee-timesheet/admin/reports.php?export=csv' .
                (isset($_GET['employee_id']) && $_GET['employee_id'] !== '' ? '&employee_id=' . (int)$_GET['employee_id'] : '') .
                (isset($_GET['project_id']) && $_GET['project_id'] !== '' ? '&project_id=' . (int)$_GET['project_id'] : '') .
                (isset($_GET['status']) && $_GET['status'] !== '' ? '&status=' . urlencode($_GET['status']) : '') .
                (isset($_GET['date_from']) && $_GET['date_from'] ? '&date_from=' . urlencode($_GET['date_from']) : '') .
                (isset($_GET['date_to']) && $_GET['date_to'] ? '&date_to=' . urlencode($_GET['date_to']) : '') ?>">Export CSV</a>
        </form>
    </div>

    <div style="margin-top:16px">
        <h3>Matching Timesheets</h3>
        <table class="data-table">
            <thead>
                <tr><th>ID</th><th>Employee</th><th>Project</th><th>Date</th><th>Hours</th><th>Status</th><th>Submitted</th></tr>
            </thead>
            <tbody>
                <?php if (empty($rows)): ?>
                    <tr><td colspan="7" style="text-align:center">No records.</td></tr>
                <?php else: foreach ($rows as $r): ?>
                    <tr>
                        <td><?= htmlspecialchars((string)$r['id'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($r['full_name'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($r['project_name'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($r['date'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars((string)$r['total_hours'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><span class="status status-<?= htmlspecialchars($r['status'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($r['status'], ENT_QUOTES, 'UTF-8') ?></span></td>
                        <td><?= htmlspecialchars($r['submitted_at'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
