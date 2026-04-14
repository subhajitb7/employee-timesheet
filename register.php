<?php
// register.php
require_once __DIR__ . '/includes/db_connect.php';

if (!empty($_SESSION['user_id'])) {
    header('Location: /employee-timesheet/dashboard.php');
    exit;
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $password2 = $_POST['password2'] ?? '';

    if ($name === '') $errors[] = 'Full name required.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email required.';
    if (strlen($password) < 6) $errors[] = 'Password must be 6+ chars.';
    if ($password !== $password2) $errors[] = 'Passwords do not match.';

    if (empty($errors)) {
        // check email exists
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $errors[] = 'Email already registered.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare('INSERT INTO users (full_name, email, password, role) VALUES (?, ?, ?, ?)');
            $stmt->execute([$name, $email, $hash, 'employee']);
            $_SESSION['success'] = 'Registration complete. Please login.';
            header('Location: /employee-timesheet/index.php');
            exit;
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>
<div class="login-container">
    <div class="login-box">
        <h1>Create Account</h1>

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
            <label>Full Name</label>
            <input type="text" name="full_name" required value="<?= htmlspecialchars($_POST['full_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="Enter your full name" autocomplete="name">
        </div>
        <div class="form-group">
            <label>Email Address</label>
            <input type="email" name="email" required value="<?= htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="you@example.com" autocomplete="email">
        </div>
        <div class="form-group">
            <label>Password</label>
            <input type="password" name="password" required placeholder="Enter your password" autocomplete="new-password">
        </div>
        <div class="form-group">
            <label>Confirm Password</label>
            <input type="password" name="password2" required placeholder="Confirm your password" autocomplete="new-password">
        </div>
        <div class="form-actions">
            <button class="btn btn-primary" type="submit" style="flex: 1;">Register</button>
            <a class="btn btn-secondary" href="/employee-timesheet/index.php">Back to login</a>
        </div>
    </form>
    </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
