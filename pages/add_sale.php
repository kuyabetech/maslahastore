<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

$page_title = "Add Sale";

// Cart initialization
if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];

// Actions
$action = $_POST['action'] ?? '';
$message = $errors = [];
$show_receipt = $receipt_text = $whatsapp_url = false;

if ($action === 'add_item') {
    $pid = (int)($_POST['product_id'] ?? 0);
    $qty = (float)($_POST['quantity'] ?? 0);
    $disc = (float)($_POST['discount'] ?? 0);

    if ($pid <= 0 || $qty <= 0) {
        $errors[] = "Invalid product or quantity.";
    } else {
        $stmt = $pdo->prepare("SELECT id, name, unit, selling_price FROM products WHERE id = ?");
        $stmt->execute([$pid]);
        $prod = $stmt->fetch();

        if (!$prod) {
            $errors[] = "Product not found.";
        } else {
            $stock = get_product_stock($pdo, $pid);
            if ($stock < $qty) {
                $errors[] = "Only {$stock} available.";
            } else {
                $_SESSION['cart'][] = [
                    'product_id'   => $pid,
                    'name'         => $prod['name'],
                    'unit'         => $prod['unit'],
                    'quantity'     => $qty,
                    'price'        => $prod['selling_price'],
                    'discount_pct' => $disc,
                ];
                $message = "Added item to cart.";
            }
        }
    }
}

if ($action === 'remove_item') {
    $idx = (int)($_POST['index'] ?? -1);
    if (isset($_SESSION['cart'][$idx])) {
        unset($_SESSION['cart'][$idx]);
        $_SESSION['cart'] = array_values($_SESSION['cart']);
        $message = "Item removed.";
    }
}

if ($action === 'confirm_sale') {
    $pay_method = $_POST['payment_method'] ?? 'Cash';
    $notes = trim($_POST['notes'] ?? '');

    if (empty($_SESSION['cart'])) {
        $errors[] = "Cart is empty.";
    } else {
        try {
            $pdo->beginTransaction();

            $subtotal = 0;
            foreach ($_SESSION['cart'] as $item) {
                $line = $item['quantity'] * $item['price'];
                $disc_amt = $line * ($item['discount_pct'] / 100);
                $subtotal += ($line - $disc_amt);
            }
            $vat = $subtotal * 0.075;
            $grand = $subtotal + $vat;

            $stmt = $pdo->prepare("
                INSERT INTO sales (user_id, subtotal, vat, grand_total, payment_method, notes)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$_SESSION['user_id'], $subtotal, $vat, $grand, $pay_method, $notes]);
            $sale_id = $pdo->lastInsertId();

            // TODO: Full batch deduction logic here (FIFO) in future update

            $pdo->commit();

            // Build receipt text
            $receipt_text = "MASLAHA STORE Receipt\n";
            $receipt_text .= "Sale #{$sale_id} - " . date('d/m/Y H:i') . "\n";
            $receipt_text .= "Cashier: {$_SESSION['username']}\n\nItems:\n";
            foreach ($_SESSION['cart'] as $item) {
                $line = $item['quantity'] * $item['price'];
                $disc_amt = $line * ($item['discount_pct'] / 100);
                $ltotal = $line - $disc_amt;
                $receipt_text .= "- {$item['name']} x {$item['quantity']} {$item['unit']}\n";
                $receipt_text .= "  @ " . format_naira($item['price']) . "   Total: " . format_naira($ltotal) . "\n";
            }
            $receipt_text .= "\nSubtotal: " . format_naira($subtotal) . "\n";
            $receipt_text .= "VAT (7.5%): " . format_naira($vat) . "\n";
            $receipt_text .= "TOTAL: " . format_naira($grand) . "\n";
            $receipt_text .= "Paid: {$pay_method}\n";
            $receipt_text .= "Notes: " . ($notes ?: 'None') . "\n";
            $receipt_text .= "Thanks for shopping! MASLAHA STORE - Abuja";

            $whatsapp_url = "https://wa.me/?text=" . urlencode($receipt_text);

            $message = "Sale #{$sale_id} saved! Total: " . format_naira($grand);
            $show_receipt = true;

            $_SESSION['cart'] = []; // Clear cart

        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = "Error saving sale: " . $e->getMessage();
        }
    }
}

// Load products for dropdown
$products = $pdo->query("
    SELECT p.id, p.name, p.unit, COALESCE(SUM(b.quantity), 0) AS stock
    FROM products p LEFT JOIN product_batches b ON p.id = b.product_id
    GROUP BY p.id ORDER BY p.name
")->fetchAll();
?>

<?php include '../includes/header.php'; ?>

<h2>Add Sale</h2>

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

<!-- Add to Cart Form -->
<form method="post">
    <input type="hidden" name="action" value="add_item">

    <label>Product (with stock)</label>
    <select name="product_id" required>
        <option value="">-- Select --</option>
        <?php foreach ($products as $p): ?>
            <option value="<?= $p['id'] ?>">
                <?= htmlspecialchars($p['name']) ?> (<?= $p['unit'] ?> - Stock: <?= number_format($p['stock']) ?>)
            </option>
        <?php endforeach; ?>
    </select>

    <label>Quantity</label>
    <input type="number" name="quantity" step="0.01" min="0.01" required>

    <label>Discount (%)</label>
    <input type="number" name="discount" min="0" max="100" step="0.1" value="0">

    <button type="submit">Add to Cart</button>
</form>

<?php if (!empty($_SESSION['cart'])): ?>
    <h3>Cart</h3>
    <table>
        <thead>
            <tr><th>Item</th><th>Qty</th><th>Price</th><th>Disc %</th><th>Line Total</th><th></th></tr>
        </thead>
        <tbody>
        <?php $cart_total = 0; foreach ($_SESSION['cart'] as $i => $item):
            $line = $item['quantity'] * $item['price'];
            $disc = $line * ($item['discount_pct'] / 100);
            $ltotal = $line - $disc;
            $cart_total += $ltotal;
        ?>
            <tr>
                <td><?= htmlspecialchars($item['name']) ?></td>
                <td><?= number_format($item['quantity'], 2) ?></td>
                <td><?= format_naira($item['price']) ?></td>
                <td><?= $item['discount_pct'] ?>%</td>
                <td><?= format_naira($ltotal) ?></td>
                <td>
                    <form method="post" style="display:inline;">
                        <input type="hidden" name="action" value="remove_item">
                        <input type="hidden" name="index" value="<?= $i ?>">
                        <button type="submit" style="background:var(--danger); color:white; border:none; padding:0.3rem 0.6rem; border-radius:4px;">Remove</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <div style="text-align:right; margin:1.5rem 0;">
        Subtotal: <?= format_naira($cart_total) ?><br>
        VAT: <?= format_naira($cart_total * 0.075) ?><br>
        <strong>Total: <?= format_naira($cart_total * 1.075) ?></strong>
    </div>

    <form method="post">
        <input type="hidden" name="action" value="confirm_sale">
        <label>Payment Method</label>
        <select name="payment_method">
            <option>Cash</option>
            <option>OPay</option>
            <option>PalmPay</option>
            <option>Bank Transfer</option>
        </select>

        <label>Notes</label>
        <textarea name="notes" rows="2"></textarea>

        <button type="submit" style="background:var(--success); display:block; width:100%; margin-top:1rem;">Confirm & Save Sale</button>
    </form>
<?php endif; ?>

<!-- Receipt Preview (shows after confirm) -->
<?php if ($show_receipt): ?>
    <div class="receipt" style="margin-top:3rem; max-width:480px; margin-left:auto; margin-right:auto; background:white; border:1px solid #ccc; border-radius:8px; overflow:hidden; box-shadow:0 2px 10px rgba(0,0,0,0.1);">
        <div style="background:var(--dark); color:white; padding:1rem; text-align:center;">
            <h3 style="margin:0;">MASLAHA STORE</h3>
            <small><?= date('d M Y H:i') ?> • Abuja</small>
        </div>
        <div style="padding:1.2rem;">
            <p><strong>Sale #<?= $sale_id ?></strong></p>
            <?php foreach ($_SESSION['cart'] as $item): // Note: cart is cleared, but use previous data if needed ?>
                <!-- For simplicity, receipt is generated from text above -->
            <?php endforeach; ?>
            <pre style="white-space:pre-wrap; font-family:inherit; background:#f8f9fa; padding:1rem; border-radius:6px;"><?= htmlspecialchars($receipt_text) ?></pre>
        </div>
        <div style="padding:1rem; text-align:center; background:#f1f3f5;">
            <a href="<?= $whatsapp_url ?>" target="_blank" style="display:inline-block; padding:0.8rem 1.5rem; background:#25D366; color:white; border-radius:6px; text-decoration:none; margin:0.5rem;">
                Share on WhatsApp
            </a>
            <button onclick="navigator.clipboard.writeText(`<?= addslashes($receipt_text) ?>`).then(()=>alert('Copied!'))" style="padding:0.8rem 1.5rem; background:var(--primary); color:white; border:none; border-radius:6px; cursor:pointer; margin:0.5rem;">
                Copy Receipt
            </button>
            <button onclick="window.print()" style="padding:0.8rem 1.5rem; background:var(--gray); color:white; border:none; border-radius:6px; cursor:pointer; margin:0.5rem;">
                Print
            </button>
        </div>
    </div>

    <style>
        @media print {
            body > *:not(.receipt) { display:none; }
            .receipt { border:none; box-shadow:none; margin:0; width:100%; }
            .receipt div:last-child { display:none; } /* hide buttons when printing */
        }
    </style>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>