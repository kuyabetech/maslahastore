<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

$page_title = "Add Product";

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name            = trim($_POST['name'] ?? '');
    $category        = trim($_POST['category'] ?? '');
    $unit            = $_POST['unit'] ?? '';
    $conversion      = floatval($_POST['conversion_factor'] ?? 1);
    $cost_price      = floatval($_POST['cost_price'] ?? 0);
    $selling_price   = floatval($_POST['selling_price'] ?? 0);
    $low_threshold   = intval($_POST['low_stock_threshold'] ?? 10);
    $barcode         = trim($_POST['barcode'] ?? '');
    $description     = trim($_POST['description'] ?? '');

    $batch_number    = trim($_POST['batch_number'] ?? '');
    $manufacture_date= $_POST['manufacture_date'] ?? null;
    $expiry_date     = $_POST['expiry_date'] ?? null;
    $initial_qty     = intval($_POST['initial_qty'] ?? 0);

    // Basic validation
    if (empty($name))               $errors[] = "Product name is required";
    if ($cost_price <= 0)           $errors[] = "Cost price must be greater than 0";
    if ($selling_price <= 0)        $errors[] = "Selling price must be greater than 0";
    if ($initial_qty < 0)           $errors[] = "Initial quantity cannot be negative";

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            // Insert product
            $stmt = $pdo->prepare("
                INSERT INTO products 
                (name, category, unit, conversion_factor, cost_price, selling_price, 
                 low_stock_threshold, barcode, description)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$name, $category, $unit, $conversion, $cost_price, $selling_price, 
                            $low_threshold, $barcode ?: null, $description]);
            $product_id = $pdo->lastInsertId();

            // Insert initial batch if quantity provided
            if ($initial_qty > 0) {
                $stmt = $pdo->prepare("
                    INSERT INTO product_batches 
                    (product_id, batch_number, manufacture_date, expiry_date, quantity, cost_price)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$product_id, $batch_number ?: null, $manufacture_date, $expiry_date, 
                                $initial_qty, $cost_price]);
            }

            $pdo->commit();
            $success = "Product '$name' added successfully!";
            // Optional: clear form or redirect
            // header("Location: products.php"); exit;

        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = "Error: " . $e->getMessage();
        }
    }
}
?>

<?php include '../includes/header.php'; ?>

<h2>Add New Product</h2>

<?php if ($success): ?>
    <div class="card" style="background:#d4edda; color:#155724; padding:1.2rem; margin:1rem 0;">
        <?= $success ?>
    </div>
<?php endif; ?>

<?php if ($errors): ?>
    <div class="card" style="background:#f8d7da; color:#721c24; padding:1.2rem; margin:1rem 0;">
        <ul style="margin:0; padding-left:1.2rem;">
            <?php foreach ($errors as $err): ?>
                <li><?= htmlspecialchars($err) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<form method="POST">
    <label for="name">Product Name *</label>
    <input type="text" id="name" name="name" required value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">

    <label for="category">Category</label>
    <input type="text" id="category" name="category" value="<?= htmlspecialchars($_POST['category'] ?? '') ?>">

    <label for="unit">Unit *</label>
    <select id="unit" name="unit" required>
        <option value="">Select unit</option>
        <option value="kg"        <?= ($_POST['unit']??'') === 'kg' ? 'selected' : '' ?>>kg</option>
        <option value="pieces"    <?= ($_POST['unit']??'') === 'pieces' ? 'selected' : '' ?>>pieces</option>
        <option value="liters"    <?= ($_POST['unit']??'') === 'liters' ? 'selected' : '' ?>>liters</option>
        <option value="crates"    <?= ($_POST['unit']??'') === 'crates' ? 'selected' : '' ?>>crates</option>
        <option value="other"     <?= ($_POST['unit']??'') === 'other' ? 'selected' : '' ?>>other</option>
    </select>

    <label for="conversion_factor">Conversion Factor (e.g., 1 crate = 24 pieces)</label>
    <input type="number" id="conversion_factor" name="conversion_factor" step="0.01" min="1" value="<?= htmlspecialchars($_POST['conversion_factor'] ?? '1') ?>">

    <label for="cost_price">Cost Price (₦) *</label>
    <input type="number" id="cost_price" name="cost_price" step="0.01" min="0" required value="<?= htmlspecialchars($_POST['cost_price'] ?? '') ?>">

    <label for="selling_price">Selling Price (₦) *</label>
    <input type="number" id="selling_price" name="selling_price" step="0.01" min="0" required value="<?= htmlspecialchars($_POST['selling_price'] ?? '') ?>">

    <label for="low_stock_threshold">Low Stock Alert Threshold</label>
    <input type="number" id="low_stock_threshold" name="low_stock_threshold" min="0" value="<?= htmlspecialchars($_POST['low_stock_threshold'] ?? '10') ?>">

    <label for="barcode">Barcode / SKU (optional)</label>
    <input type="text" id="barcode" name="barcode" value="<?= htmlspecialchars($_POST['barcode'] ?? '') ?>">

    <label for="description">Description</label>
    <textarea id="description" name="description" rows="3"><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>

    <hr style="margin:1.5rem 0;">

    <h3>Initial Stock Batch (optional but recommended)</h3>

    <label for="initial_qty">Initial Quantity</label>
    <input type="number" id="initial_qty" name="initial_qty" min="0" value="<?= htmlspecialchars($_POST['initial_qty'] ?? '0') ?>">

    <label for="batch_number">Batch Number</label>
    <input type="text" id="batch_number" name="batch_number" value="<?= htmlspecialchars($_POST['batch_number'] ?? '') ?>">

    <label for="manufacture_date">Manufacture Date</label>
    <input type="date" id="manufacture_date" name="manufacture_date" value="<?= htmlspecialchars($_POST['manufacture_date'] ?? '') ?>">

    <label for="expiry_date">Expiry Date</label>
    <input type="date" id="expiry_date" name="expiry_date" value="<?= htmlspecialchars($_POST['expiry_date'] ?? '') ?>">

    <button type="submit" style="margin-top:1.5rem;">Save Product</button>
</form>

<?php include '../includes/footer.php'; ?>