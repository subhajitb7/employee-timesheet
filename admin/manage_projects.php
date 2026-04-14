<?php
// admin/manage_projects.php
require_once __DIR__ . '/../includes/db_connect.php';
require_role('admin');

$errors = [];

// CREATE / UPDATE project
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['save_project']) || isset($_POST['update_project']))) {
    $id = (int)($_POST['id'] ?? 0);
    $project_name = trim($_POST['project_name'] ?? '');

    if ($project_name === '') {
        $errors[] = 'Project name required.';
    }

    if (empty($errors)) {
        // Check duplicate project name for new or changed name
        $stmt = $pdo->prepare('SELECT id FROM projects WHERE project_name = ? AND id <> ? LIMIT 1');
        $stmt->execute([$project_name, $id]);
        if ($stmt->fetch()) {
            $errors[] = 'Project name already exists.';
        } else {
            if ($id === 0) {
                // create
                $stmt = $pdo->prepare('INSERT INTO projects (project_name) VALUES (?)');
                $stmt->execute([$project_name]);
                $_SESSION['success'] = 'Project created.';
                header('Location: /employee-timesheet/admin/manage_projects.php');
                exit;
            } else {
                // update
                $stmt = $pdo->prepare('UPDATE projects SET project_name = ? WHERE id = ?');
                $stmt->execute([$project_name, $id]);
                $_SESSION['success'] = 'Project updated.';
                header('Location: /employee-timesheet/admin/manage_projects.php');
                exit;
            }
        }
    }
}

// DELETE project
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_project'])) {
    $del_id = (int)($_POST['delete_project'] ?? 0);
    if ($del_id > 0) {
        // Check if project is used in timesheets
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM timesheets WHERE project_id = ?');
        $stmt->execute([$del_id]);
        $count = (int)$stmt->fetchColumn();
        
        if ($count > 0) {
            $errors[] = 'Cannot delete project that has timesheet entries.';
        } else {
            $stmt = $pdo->prepare('DELETE FROM projects WHERE id = ?');
            $stmt->execute([$del_id]);
            $_SESSION['success'] = 'Project deleted.';
            header('Location: /employee-timesheet/admin/manage_projects.php');
            exit;
        }
    } else {
        $errors[] = 'Invalid project id.';
    }
}

// Fetch projects
$projects = $pdo->query('SELECT p.id, p.project_name, p.created_at, COUNT(t.id) AS timesheet_count 
                         FROM projects p 
                         LEFT JOIN timesheets t ON t.project_id = p.id 
                         GROUP BY p.id, p.project_name, p.created_at 
                         ORDER BY p.created_at DESC')->fetchAll();

// If editing a specific project, fetch details
$editProject = null;
if (!empty($_GET['edit']) && is_numeric($_GET['edit'])) {
    $eid = (int)$_GET['edit'];
    $stmt = $pdo->prepare('SELECT id, project_name FROM projects WHERE id = ? LIMIT 1');
    $stmt->execute([$eid]);
    $editProject = $stmt->fetch();
}

require_once __DIR__ . '/../includes/header.php';
?>

<div style="margin-top:1rem;height:77vh">
    <h2>Manage Projects</h2>
    <p style="color:var(--text-muted); margin-bottom:1.5rem;">Create and manage project entries</p>

    <?php if (!empty($errors)): ?>
        <div class="error">
            <ul style="margin-left:18px;margin-top:0.5rem;">
                <?php foreach ($errors as $err): ?>
                    <li><?= htmlspecialchars($err, ENT_QUOTES, 'UTF-8') ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div style="display:flex;gap:20px;flex-wrap:wrap">
        <!-- Add / Edit form -->
        <div style="flex:1;min-width:320px">
            <div class="form-container">
                <h3><?= $editProject ? 'Edit Project' : 'Add New Project' ?></h3>
                <form method="post" style="max-width:420px">
                    <input type="hidden" name="id" value="<?= htmlspecialchars((string)($editProject['id'] ?? 0), ENT_QUOTES, 'UTF-8') ?>">
                    <div class="form-group">
                        <label>Project Name</label>
                        <input type="text" name="project_name" required value="<?= htmlspecialchars($editProject['project_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="Enter project name">
                    </div>
                    <div class="form-actions">
                        <button class="btn btn-primary" name="<?= $editProject ? 'update_project' : 'save_project' ?>" type="submit"><?= $editProject ? 'Save' : 'Create' ?></button>
                        <?php if ($editProject): ?><a class="btn btn-secondary" href="/employee-timesheet/admin/manage_projects.php">Cancel</a><?php endif; ?>
                    </div>
                </form>
            </div>
        </div>

        <!-- Projects list -->
        <div style="flex:1.6;min-width:420px">
            <div class="form-container">
                <h3>Existing Projects</h3>
                <table class="data-table">
                    <thead>
                        <tr><th>Project Name</th><th>Timesheets</th><th>Created</th><th>Actions</th></tr>
                    </thead>
                    <tbody>
                    <?php if (empty($projects)): ?>
                        <tr><td colspan="4" style="text-align:center">No projects found.</td></tr>
                    <?php else: foreach ($projects as $p): ?>
                        <tr>
                            <td><?= htmlspecialchars($p['project_name'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars((string)$p['timesheet_count'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($p['created_at'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td>
                                <a class="btn btn-small btn-primary" href="/employee-timesheet/admin/manage_projects.php?edit=<?= htmlspecialchars((string)$p['id'], ENT_QUOTES, 'UTF-8') ?>">Edit</a>

                                <?php if ((int)$p['timesheet_count'] === 0): ?>
                                    <form method="post" style="display:inline" onsubmit="return confirm('Delete this project? This cannot be undone.');">
                                        <button class="btn btn-small btn-danger" type="submit" name="delete_project" value="<?= htmlspecialchars((string)$p['id'], ENT_QUOTES, 'UTF-8') ?>">Delete</button>
                                    </form>
                                <?php else: ?>
                                    <span style="font-size:0.875rem;color:var(--text-muted)">In use</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>



