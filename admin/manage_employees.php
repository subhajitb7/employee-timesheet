<?php
// admin/manage_employees.php
require_once __DIR__ . '/../includes/db_connect.php';
require_role('admin');

$errors = [];

// CREATE / UPDATE user
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['save_user']) || isset($_POST['update_user']))) {
    $id = (int)($_POST['id'] ?? 0);
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $role = $_POST['role'] ?? 'employee';
    $password = $_POST['password'] ?? '';

    if ($full_name === '') $errors[] = 'Full name required.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email required.';
    if (!in_array($role, ['admin', 'employee'], true)) $errors[] = 'Invalid role.';

    // On create require password
    if ($id === 0 && strlen($password) < 6) $errors[] = 'Password must be 6+ chars for new user.';

    if (empty($errors)) {
        // check duplicate email for new or changed email
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? AND id <> ? LIMIT 1');
        $stmt->execute([$email, $id]);
        if ($stmt->fetch()) {
            $errors[] = 'Email already in use.';
        } else {
            if ($id === 0) {
                // create
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare('INSERT INTO users (full_name, email, password, role) VALUES (?, ?, ?, ?)');
                $stmt->execute([$full_name, $email, $hash, $role]);
                $_SESSION['success'] = 'User created.';
                header('Location: /employee-timesheet/admin/manage_employees.php');
                exit;
            } else {
                // update
                if ($password !== '') {
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare('UPDATE users SET full_name = ?, email = ?, password = ?, role = ? WHERE id = ?');
                    $stmt->execute([$full_name, $email, $hash, $role, $id]);
                } else {
                    $stmt = $pdo->prepare('UPDATE users SET full_name = ?, email = ?, role = ? WHERE id = ?');
                    $stmt->execute([$full_name, $email, $role, $id]);
                }
                $_SESSION['success'] = 'User updated.';
                header('Location: /employee-timesheet/admin/manage_employees.php');
                exit;
            }
        }
    }
}

// DELETE user
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user'])) {
    $del_id = (int)($_POST['delete_user'] ?? 0);
    if ($del_id > 0) {
        // prevent deleting yourself
        if ($del_id === (int)$_SESSION['user_id']) {
            $errors[] = 'You cannot delete your own account.';
        } else {
            $stmt = $pdo->prepare('DELETE FROM users WHERE id = ?');
            $stmt->execute([$del_id]);
            $_SESSION['success'] = 'User deleted.';
            header('Location: /employee-timesheet/admin/manage_employees.php');
            exit;
        }
    } else {
        $errors[] = 'Invalid user id.';
    }
}

// Fetch users
$users = $pdo->query('SELECT id, full_name, email, role, created_at FROM users ORDER BY created_at DESC')->fetchAll();

// If editing a specific user, fetch details
$editUser = null;
if (!empty($_GET['edit']) && is_numeric($_GET['edit'])) {
    $eid = (int)$_GET['edit'];
    $stmt = $pdo->prepare('SELECT id, full_name, email, role FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$eid]);
    $editUser = $stmt->fetch();
}

require_once __DIR__ . '/../includes/header.php';
?>

<div style="margin-top:1rem;height:77vh">
    <h2>Manage Employees</h2>
    <p style="color:var(--text-muted); margin-bottom:1.5rem;">Create and manage employee accounts</p>

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
            <h3><?= $editUser ? 'Edit User' : 'Add New User' ?></h3>
                <form method="post" style="max-width:420px">
                <input type="hidden" name="id" value="<?= htmlspecialchars((string)($editUser['id'] ?? 0), ENT_QUOTES, 'UTF-8') ?>">
                <div class="form-group">
                    <label>Full name</label>
                    <input type="text" name="full_name" required value="<?= htmlspecialchars($editUser['full_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" required value="<?= htmlspecialchars($editUser['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div class="form-group">
                    <label>Role</label>
                    <select name="role">
                        <option value="employee" <?= (isset($editUser['role']) && $editUser['role'] === 'employee') ? 'selected' : '' ?>>Employee</option>
                        <option value="admin" <?= (isset($editUser['role']) && $editUser['role'] === 'admin') ? 'selected' : '' ?>>Admin</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Password <?php if ($editUser): ?>(leave blank to keep)<?php endif; ?></label>
                    <input type="password" name="password" <?= $editUser ? '' : 'required' ?>>
                </div>
                <div class="form-actions">
                    <button class="btn btn-primary" name="<?= $editUser ? 'update_user' : 'save_user' ?>" type="submit"><?= $editUser ? 'Save' : 'Create' ?></button>
                    <?php if ($editUser): ?><a class="btn btn-secondary" href="/employee-timesheet/admin/manage_employees.php">Cancel</a><?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Users list -->
        <div style="flex:1.6;min-width:420px">
            <h3>Existing Users</h3>
            <table class="data-table">
                <thead>
                    <tr><th>Name</th><th>Email</th><th>Role</th><th>Created</th><th>Actions</th></tr>
                </thead>
                <tbody>
                <?php if (empty($users)): ?>
                    <tr><td colspan="5" style="text-align:center">No users found.</td></tr>
                <?php else: foreach ($users as $u): ?>
                    <tr>
                        <td><?= htmlspecialchars($u['full_name'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($u['email'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($u['role'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($u['created_at'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td>
                            <a class="btn btn-small btn-primary" href="/employee-timesheet/admin/manage_employees.php?edit=<?= htmlspecialchars((string)$u['id'], ENT_QUOTES, 'UTF-8') ?>">Edit</a>

                                <?php if ((int)$u['id'] !== (int)$_SESSION['user_id']): ?>
                                    <form method="post" style="display:inline" onsubmit="return confirm('Delete this user? This cannot be undone.');">
                                    <button class="btn btn-small btn-danger" type="submit" name="delete_user" value="<?= htmlspecialchars((string)$u['id'], ENT_QUOTES, 'UTF-8') ?>">Delete</button>
                                </form>
                            <?php else: ?>
                                <span style="font-size:13px;color:#6c757d;margin-left:8px">Cannot delete yourself</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
