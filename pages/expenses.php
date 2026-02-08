<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

$page_title = "Expenses";

$message = $errors = [];

// Add new expense
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $category = trim($_POST['category'] ?? '');
    $amount   = floatval($_POST['amount'] ?? 0);
    $date     = $_POST['date'] ?? date('Y-m-d');
    $notes    = trim($_POST['notes'] ?? '');

    if (empty($category) || $amount <= 0) {
        $errors[] = "Category and amount > 0 required.";
    } else {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO expenses (user_id, category, amount, expense_date, notes)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$_SESSION['user_id'], $category, $amount, $date, $notes]);
            $message = "Expense of " . format_naira($amount) . " added.";
        } catch (Exception $e) {
            $errors[] = "Error: " . $e->getMessage();
        }
    }
}

// List expenses (last 60 days)
$expenses = $pdo->query("
    SELECT e.id, e.category, e.amount, e.expense_date, e.notes, u.username
    FROM expenses e
    LEFT JOIN users u ON e.user_id = u.id
    WHERE e.expense_date >= DATE_SUB(CURDATE(), INTERVAL 60 DAY)
    ORDER BY e.expense_date DESC
")->fetchAll(PDO::FETCH_ASSOC);

$total_this_month = $pdo->query("SELECT SUM(amount) FROM expenses WHERE expense_date >= DATE_FORMAT(CURDATE(), '%Y-%m-01')")->fetchColumn() ?: 0;
?>

<?php include '../includes/header.php'; ?>

<h2>Business Expenses</h2>

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

<!-- Add Expense Form -->
<form method="POST" style="max-width:500px; margin-bottom:3rem;">
    <label>Category</label>
    <input type="text" name="category" placeholder="e.g. Rent, Transport, Supplies" required>

    <label>Amount (₦)</label>
    <input type="number" name="amount" step="0.01" min="0.01" required>

    <label>Date</label>
    <input type="date" name="date" value="<?= date('Y-m-d') ?>">

    <label>Notes</label>
    <textarea name="notes" rows="3"></textarea>

    <button type="submit" style="margin-top:1rem;">Add Expense</button>
</form>

<h3>Recent Expenses (last 60 days)</h3>

<?php if (empty($expenses)): ?>
    <p style="color:var(--gray); text-align:center;">No expenses recorded yet.</p>
<?php else: ?>
    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Category</th>
                <th>Amount</th>
                <th>By</th>
                <th>Notes</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($expenses as $exp): ?>
            <tr>
                <td><?= date('d M Y', strtotime($exp['expense_date'])) ?></td>
                <td><?= htmlspecialchars($exp['category']) ?></td>
                <td><?= format_naira($exp['amount']) ?></td>
                <td><?= htmlspecialchars($exp['username'] ?? 'Unknown') ?></td>
                <td><?= htmlspecialchars($exp['notes'] ?: '-') ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <p style="margin-top:1rem; font-weight:bold; text-align:right;">
        Total this month: <?= format_naira($total_this_month) ?>
    </p>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>