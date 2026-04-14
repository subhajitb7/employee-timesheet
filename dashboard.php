<?php
// dashboard.php (role-aware, admin shows admin-only sections; "This Week (hours)" removed for admin)
require_once __DIR__ . '/includes/db_connect.php';
require_login();

$role = $_SESSION['role'] ?? 'employee';
$userId = (int)($_SESSION['user_id'] ?? 0);

/**
 * Format the submitted_at field from a timesheet row.
 * Uses only submitted_at.
 */
function format_submitted_row(array $row): string {
    $submitted = $row['submitted_at'] ?? '';

    if (!empty($submitted) && $submitted !== '0000-00-00 00:00:00') {
        $ts = strtotime($submitted);
        if ($ts !== false) {
            return htmlspecialchars(date('Y-m-d H:i', $ts), ENT_QUOTES, 'UTF-8');
        }
        return htmlspecialchars((string)$submitted, ENT_QUOTES, 'UTF-8');
    }

    return '-';
}

// Recent timesheets for the logged-in user (select submitted_at explicitly)
// NOTE: We still load this for non-admin users; admins will not display this section.
$recentTimesheets = [];
if ($role !== 'admin') {
    $recentStmt = $pdo->prepare(
        'SELECT t.id, t.date, t.total_hours, t.status, p.project_name, t.submitted_at
         FROM timesheets t
         LEFT JOIN projects p ON p.id = t.project_id
         WHERE t.user_id = ?
         ORDER BY t.date DESC, t.created_at DESC
         LIMIT 8'
    );
    $recentStmt->execute([$userId]);
    $recentTimesheets = $recentStmt->fetchAll();
}

// This week's totals for logged-in user
$dt = new DateTime();
$dayOfWeek = (int)$dt->format('N'); // 1 (Mon) - 7 (Sun)
$weekStart = (clone $dt)->modify('-' . ($dayOfWeek - 1) . ' days')->setTime(0, 0, 0);
$weekEnd = (clone $weekStart)->modify('+6 days')->setTime(23, 59, 59);
$sw = $weekStart->format('Y-m-d');
$ew = $weekEnd->format('Y-m-d');

$weekStmt = $pdo->prepare('SELECT IFNULL(SUM(total_hours),0) FROM timesheets WHERE user_id = ? AND date BETWEEN ? AND ?');
$weekStmt->execute([$userId, $sw, $ew]);
$weekHours = (float)$weekStmt->fetchColumn();

// Pending count for this user
$pendingForMeStmt = $pdo->prepare('SELECT COUNT(*) FROM timesheets WHERE user_id = ? AND status = "pending"');
$pendingForMeStmt->execute([$userId]);
$pendingForMe = (int)$pendingForMeStmt->fetchColumn();

// Admin-only aggregates
$totalProjects = $totalUsers = $totalTimesheets = $pendingApprovals = 0;
$adminRecent = [];
if ($role === 'admin') {
    $totalProjects = (int)$pdo->query('SELECT COUNT(*) FROM projects')->fetchColumn();
    $totalUsers = (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
    $totalTimesheets = (int)$pdo->query('SELECT COUNT(*) FROM timesheets')->fetchColumn();
    $pendingApprovals = (int)$pdo->query("SELECT COUNT(*) FROM timesheets WHERE status = 'pending'")->fetchColumn();

    $arStmt = $pdo->query(
        'SELECT t.id, t.date, t.total_hours, t.status, p.project_name, u.full_name, t.submitted_at, t.description, t.admin_remarks
         FROM timesheets t
         LEFT JOIN projects p ON p.id = t.project_id
         LEFT JOIN users u ON u.id = t.user_id
         ORDER BY t.submitted_at DESC, t.created_at DESC
         LIMIT 8'
    );
    $adminRecent = $arStmt->fetchAll();
}

require_once __DIR__ . '/includes/header.php';

// Grid style differs for admin vs employee
$gridStyle = $role === 'admin'
    ? 'display:grid;grid-template-columns:repeat(4,1fr);gap:20px;margin-bottom:22px;'
    : 'display:grid;grid-template-columns:repeat(3,1fr);gap:20px;margin-bottom:22px;';
?>

<div class="container">
  <div class="dashboard" style="margin-top:8px;">
      <h1>Welcome, <?= htmlspecialchars($_SESSION['user_name'] ?? 'User', ENT_QUOTES, 'UTF-8') ?></h1>
      <p style="color:#6c757d; margin-bottom:18px">Here's a quick overview of your timesheets and projects.</p>

      <div class="stats-grid" style="<?= $gridStyle ?>">
          <?php if ($role === 'admin'): ?>
              <div class="stat-card">
                  <h3>Projects</h3>
                  <div class="stat-number"><?= htmlspecialchars((string)$totalProjects, ENT_QUOTES, 'UTF-8') ?></div>
                  <div class="help-text">Active projects</div>
              </div>

              <div class="stat-card">
                  <h3>Employees</h3>
                  <div class="stat-number"><?= htmlspecialchars((string)$totalUsers, ENT_QUOTES, 'UTF-8') ?></div>
                  <div class="help-text">Registered users</div>
              </div>

              <div class="stat-card">
                  <h3>Total Timesheets</h3>
                  <div class="stat-number"><?= htmlspecialchars((string)$totalTimesheets, ENT_QUOTES, 'UTF-8') ?></div>
                  <div class="help-text">All entries</div>
              </div>

              <div class="stat-card">
                  <h3>Pending Approvals</h3>
                  <div class="stat-number"><?= htmlspecialchars((string)$pendingApprovals, ENT_QUOTES, 'UTF-8') ?></div>
                  <div class="help-text">Timesheets awaiting review</div>
              </div>

          <?php else: /* employee view */ ?>

              <?php
                $myTimesheetsStmt = $pdo->prepare('SELECT COUNT(*) FROM timesheets WHERE user_id = ?');
                $myTimesheetsStmt->execute([$userId]);
                $myTimesheets = (int)$myTimesheetsStmt->fetchColumn();
              ?>

              <div class="stat-card">
                  <h3>Your Timesheets</h3>
                  <div class="stat-number"><?= htmlspecialchars((string)$myTimesheets, ENT_QUOTES, 'UTF-8') ?></div>
                  <div class="help-text">Entries you've created</div>
              </div>

              <div class="stat-card">
                  <h3>This Week (hours)</h3>
                  <div class="stat-number"><?= htmlspecialchars((string)number_format($weekHours, 2), ENT_QUOTES, 'UTF-8') ?></div>
                  <div class="help-text"><?= htmlspecialchars($weekStart->format('M d'), ENT_QUOTES, 'UTF-8') ?> — <?= htmlspecialchars($weekEnd->format('M d'), ENT_QUOTES, 'UTF-8') ?></div>
              </div>

              <div class="stat-card">
                  <h3>Pending (your entries)</h3>
                  <div class="stat-number"><?= htmlspecialchars((string)$pendingForMe, ENT_QUOTES, 'UTF-8') ?></div>
                  <div class="help-text">Pending for your review/approval</div>
              </div>

          <?php endif; ?>
      </div>

      <div class="quick-actions" style="display:flex;gap:12px;flex-wrap:wrap;margin-bottom:20px;">
          <?php if ($role !== 'admin'): ?>
              <a class="btn btn-primary" href="/employee-timesheet/add_timesheet.php">Add Timesheet</a>
              <a class="btn btn-secondary" href="/employee-timesheet/my_timesheets.php">My Timesheets</a>
          <?php endif; ?>

          <?php if ($role === 'admin'): ?>
              <a class="btn btn-primary" href="/employee-timesheet/admin/admin_timesheets.php">Manage Timesheets</a>
              <a class="btn btn-secondary" href="/employee-timesheet/admin/manage_employees.php">Manage Employees</a>
              <a class="btn btn-secondary" href="/employee-timesheet/admin/manage_projects.php">Manage Projects</a>
              <a class="btn btn-primary" href="/employee-timesheet/admin/reports.php">Reports</a>
          <?php endif; ?>
      </div>

      <div style="display:grid;gap:20px; grid-template-columns:<?php echo ($role === 'admin') ? '1fr' : '1fr 1fr'; ?>;">
          <?php if ($role !== 'admin'): ?>
              <div class="form-container">
                  <h3>Your recent timesheets</h3>
                  <?php if (empty($recentTimesheets)): ?>
                      <p class="help-text">No recent entries. Add your first timesheet now.</p>
                  <?php else: ?>
                      <table class="data-table">
                          <thead>
                              <tr><th>Date</th><th>Project</th><th>Hours</th><th>Status</th><th>Submitted</th></tr>
                          </thead>
                          <tbody>
                          <?php foreach ($recentTimesheets as $r): ?>
                              <tr>
                                  <td><?= htmlspecialchars($r['date'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                                  <td><?= htmlspecialchars($r['project_name'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                                  <td><?= htmlspecialchars((string)$r['total_hours'], ENT_QUOTES, 'UTF-8') ?></td>
                                  <td>
                                    <?php
                                      $st = $r['status'] ?? '';
                                      if ($st === '' || $st === null) {
                                          echo '<span class="status">-</span>';
                                      } else {
                                          echo '<span class="status status-' . htmlspecialchars($st, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($st, ENT_QUOTES, 'UTF-8') . '</span>';
                                      }
                                    ?>
                                  </td>
                                  <td><?= format_submitted_row($r) ?></td>
                              </tr>
                          <?php endforeach; ?>
                          </tbody>
                      </table>
                  <?php endif; ?>
              </div>
          <?php endif; ?>

          <?php if ($role === 'admin'): ?>
              <div class="form-container">
                  <h3>Recent submissions (admin)</h3>
                  <?php if (empty($adminRecent)): ?>
                      <p class="help-text">No recent submissions.</p>
                  <?php else: ?>
                      <table class="data-table">
                          <thead>
                              <tr><th>Date</th><th>Employee</th><th>Project</th><th>Hours</th><th>Status</th><th>Submitted</th></tr>
                          </thead>
                          <tbody>
                          <?php foreach ($adminRecent as $a): ?>
                              <tr>
                                  <td><?= htmlspecialchars($a['date'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                                  <td><?= htmlspecialchars($a['full_name'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                                  <td><?= htmlspecialchars($a['project_name'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                                  <td><?= htmlspecialchars((string)$a['total_hours'], ENT_QUOTES, 'UTF-8') ?></td>
                                  <td><span class="status status-<?= htmlspecialchars($a['status'] ?? '', ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($a['status'] ?? '', ENT_QUOTES, 'UTF-8') ?></span></td>
                                  <td><?= format_submitted_row($a) ?></td>
                              </tr>
                          <?php endforeach; ?>
                          </tbody>
                      </table>
                  <?php endif; ?>
              </div>
          <?php else: ?>
              <!-- For non-admin, the admin column is the right-hand column -->
              <div class="form-container">
                  <h3>Recent submissions (latest)</h3>
                  <?php if (empty($recentTimesheets)): ?>
                      <p class="help-text">No recent submissions.</p>
                  <?php else: ?>
                      <table class="data-table">
                          <thead>
                              <tr><th>Date</th><th>Project</th><th>Hours</th><th>Status</th><th>Submitted</th></tr>
                          </thead>
                          <tbody>
                          <?php foreach ($recentTimesheets as $r): ?>
                              <tr>
                                  <td><?= htmlspecialchars($r['date'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                                  <td><?= htmlspecialchars($r['project_name'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                                  <td><?= htmlspecialchars((string)$r['total_hours'], ENT_QUOTES, 'UTF-8') ?></td>
                                  <td><span class="status status-<?= htmlspecialchars($r['status'] ?? '', ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($r['status'] ?? '-', ENT_QUOTES, 'UTF-8') ?></span></td>
                                  <td><?= format_submitted_row($r) ?></td>
                              </tr>
                          <?php endforeach; ?>
                          </tbody>
                      </table>
                  <?php endif; ?>
              </div>
          <?php endif; ?>
      </div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
