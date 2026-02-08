<?php
session_start();
require_once '../includes/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'owner') {
    header("Location: ../pages/dashboard.php");
    exit;
}

$page_title = "Manage Users";

$message = $errors = [];

// Add new user
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_user'])) {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $role     = $_POST['role'] ?? 'cashier';

    if (strlen($username) < 3 || strlen($password) < 6) {
        $errors[] = "Username ≥ 3 chars, password ≥ 6 chars.";
    } else {
        $check = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $check->execute([$username]);
        if ($check->fetch()) {
            $errors[] = "Username already exists.";
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
            $stmt->execute([$username, $hash, $role]);
            $message = "User '$username' created as $role.";
        }
    }
}

// List users
$users = $pdo->query("SELECT id, username, role, created_at FROM users ORDER BY role, username")->fetchAll();
?>

<?php include '../includes/header.php'; ?>

<h2>Manage Users</h2>

<?php if ($message): ?>
    <div style="background:#d4edda; color:#155724; padding:1rem; border-radius:6px; margin:1rem 0;">
        <?= htmlspecialchars($message) ?>
    </div>
<?php endif; ?>

<?php if ($errors): ?>
    <div style="background:#f8d7da; color:#721c24; padding:1rem; border-radius:6px; margin:1rem 0;">
        <?= implode('<br>', array_map('htmlspecialchars', $errors)) ?>
    </div>
<?php endif; ?>

<!-- Add User Form -->
<form method="POST" style="max-width:500px; margin-bottom:3rem;">
    <input type="hidden" name="add_user" value="1">

    <label>Username</label>
    <input type="text" name="username" required minlength="3">

    <label>Password</label>
    <input type="password" name="password" required minlength="6">

    <label>Role</label>
    <select name="role">
        <option value="cashier">Cashier</option>
        <option value="manager">Manager</option>
        <option value="owner">Owner</option>
    </select>

    <button type="submit">Create User</button>
</form>

<h3>Existing Users</h3>

<table>
    <thead>
        <tr>
            <th>Username</th>
            <th>Role</th>
            <th>Created</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($users as $u): ?>
        <tr>
            <td><?= htmlspecialchars($u['username']) ?></td>
            <td><?= htmlspecialchars(ucfirst($u['role'])) ?></td>
            <td><?= date('d M Y', strtotime($u['created_at'])) ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<p style="margin-top:1.5rem; color:var(--gray);">
    Note: Password change / delete features can be added later.
</p>

<?php include '../includes/footer.php'; ?>