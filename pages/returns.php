<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

$page_title = "Returns & Refunds";

// Only owner/manager can process returns
if (!in_array($_SESSION['user_role'], ['owner', 'manager'])) {
    header("Location: ../pages/dashboard.php");
    exit;
}

$message = $errors = [];

// Handle return submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'process_return') {
    $sale_id     = (int)($_POST['sale_id'] ?? 0);
    $product_id  = (int)($_POST['product_id'] ?? 0);
    $batch_id    = (int)($_POST['batch_id'] ?? 0);
    $quantity    = (float)($_POST['quantity'] ?? 0);
    $reason      = trim($_POST['reason'] ?? '');

    if ($sale_id <= 0 || $product_id <= 0 || $batch_id <= 0 || $quantity <= 0) {
        $errors[] = "Invalid return details.";
    } elseif (empty($reason)) {
        $errors[] = "Please provide a return reason.";
    } else {
        try {
            $pdo->beginTransaction();

            // Verify the item was sold in this sale and batch
            $check_stmt = $pdo->prepare("
                SELECT si.quantity AS sold_qty, si.price
                FROM sale_items si
                WHERE si.sale_id = ? AND si.product_id = ? AND si.batch_id = ?
            ");
            $check_stmt->execute([$sale_id, $product_id, $batch_id]);
            $sold = $check_stmt->fetch();

            if (!$sold || $quantity > $sold['sold_qty']) {
                throw new Exception("Cannot return more than sold or invalid item/batch.");
            }

            // Add stock back to the original batch
            $update_batch = $pdo->prepare("
                UPDATE product_batches 
                SET quantity = quantity + ? 
                WHERE id = ?
            ");
            $update_batch->execute([$quantity, $batch_id]);

            // Log the return
            $return_stmt = $pdo->prepare("
                INSERT INTO returns 
                (sale_id, product_id, batch_id, quantity, reason, return_date, user_id)
                VALUES (?, ?, ?, ?, ?, NOW(), ?)
            ");
            $return_stmt->execute([$sale_id, $product_id, $batch_id, $quantity, $reason, $_SESSION['user_id']]);

            // Optional: Adjust sale total (reduce grand_total by returned amount)
            $refund_amount = $quantity * $sold['price'];
            $adjust_sale = $pdo->prepare("
                UPDATE sales 
                SET grand_total = grand_total - ?, 
                    subtotal = subtotal - ?,
                    vat = vat - ?
                WHERE id = ?
            ");
            $adjust_sale->execute([
                $refund_amount,
                $refund_amount / 1.075, // approximate reverse VAT
                ($refund_amount / 1.075) * 0.075,
                $sale_id
            ]);

            $pdo->commit();

            $message = "Return processed successfully: {$quantity} unit(s) added back to stock.";
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = "Return failed: " . $e->getMessage();
        }
    }
}

// Fetch recent sales (last 30 days) for selection
$recent_sales = $pdo->query("
    SELECT id, sale_date, grand_total, payment_method, notes
    FROM sales
    WHERE sale_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    ORDER BY sale_date DESC
    LIMIT 50
")->fetchAll(PDO::FETCH_ASSOC);

// Fetch sale items for a selected sale (via GET or after POST)
$selected_sale_id = (int)($_GET['sale_id'] ?? $_POST['sale_id'] ?? 0);
$sale_items = [];
if ($selected_sale_id > 0) {
    $items_stmt = $pdo->prepare("
        SELECT si.id, si.product_id, si.batch_id, si.quantity, si.price,
               p.name, p.unit, b.batch_number, b.expiry_date
        FROM sale_items si
        JOIN products p ON si.product_id = p.id
        JOIN product_batches b ON si.batch_id = b.id
        WHERE si.sale_id = ?
    ");
    $items_stmt->execute([$selected_sale_id]);
    $sale_items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<?php include '../includes/header.php'; ?>

<h2>Returns & Refunds</h2>

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

<!-- Select Sale -->
<form method="GET" style="margin-bottom:2rem;">
    <label>Select Recent Sale to Process Return</label>
    <select name="sale_id" onchange="this.form.submit()" required>
        <option value="">-- Choose a sale --</option>
        <?php foreach ($recent_sales as $sale): ?>
            <option value="<?= $sale['id'] ?>" <?= $sale['id'] == $selected_sale_id ? 'selected' : '' ?>>
                Sale #<?= $sale['id'] ?> - <?= date('d M Y H:i', strtotime($sale['sale_date'])) ?> 
                (₦<?= number_format($sale['grand_total'], 2) ?> - <?= $sale['payment_method'] ?>)
            </option>
        <?php endforeach; ?>
    </select>
</form>

<?php if ($selected_sale_id > 0 && !empty($sale_items)): ?>
    <h3>Sale #<?= $selected_sale_id ?> Items</h3>

    <table>
        <thead>
            <tr>
                <th>Product</th>
                <th>Batch / Expiry</th>
                <th>Sold Qty</th>
                <th>Price</th>
                <th>Return Qty</th>
                <th>Reason</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($sale_items as $item): ?>
            <tr>
                <td><?= htmlspecialchars($item['name']) ?></td>
                <td>
                    <?= htmlspecialchars($item['batch_number'] ?: 'N/A') ?><br>
                    <small>Expiry: <?= $item['expiry_date'] ? date('d M Y', strtotime($item['expiry_date'])) : '-' ?></small>
                </td>
                <td><?= number_format($item['quantity'], 2) ?> <?= $item['unit'] ?></td>
                <td><?= format_naira($item['price']) ?></td>
                <td>
                    <form method="POST" style="display:flex; gap:0.5rem; align-items:center;">
                        <input type="hidden" name="action" value="process_return">
                        <input type="hidden" name="sale_id" value="<?= $selected_sale_id ?>">
                        <input type="hidden" name="product_id" value="<?= $item['product_id'] ?>">
                        <input type="hidden" name="batch_id" value="<?= $item['batch_id'] ?>">
                        <input type="number" name="quantity" min="0.01" max="<?= $item['quantity'] ?>" step="0.01" style="width:80px;" required>
                        <input type="text" name="reason" placeholder="Reason (e.g. Defective)" required style="flex:1;">
                        <button type="submit" style="background:var(--danger); color:white; padding:0.5rem 1rem; border:none; border-radius:4px;">Process Return</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <p style="margin-top:1.5rem; color:var(--gray); font-size:0.9rem;">
        Note: Stock will be added back to the original batch. Refund amount is automatically adjusted in sale record.
    </p>
<?php elseif ($selected_sale_id > 0): ?>
    <p style="text-align:center; color:var(--gray); padding:2rem;">
        No items found in this sale.
    </p>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>