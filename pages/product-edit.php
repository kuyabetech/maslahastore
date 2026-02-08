<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

// Only admin & owner can edit products
if (!in_array($_SESSION['user_role'] ?? 'cashier', ['admin', 'owner'])) {
    header("Location: products.php");
    exit;
}

$page_title = "Edit Product";

$product_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$errors     = [];
$success    = '';

// Fetch existing product
$stmt = $pdo->prepare("
    SELECT name, category, unit, conversion_factor, cost_price, selling_price,
           low_stock_threshold, barcode, description
    FROM products 
    WHERE id = ?
");
$stmt->execute([$product_id]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$product) {
    $errors[] = "Product not found.";
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($errors)) {
    // Optional: add CSRF check here if implemented
    // if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) { ... }

    $name            = trim($_POST['name'] ?? '');
    $category        = trim($_POST['category'] ?? '');
    $unit            = $_POST['unit'] ?? '';
    $conversion      = floatval($_POST['conversion_factor'] ?? 1);
    $cost_price      = floatval($_POST['cost_price'] ?? 0);
    $selling_price   = floatval($_POST['selling_price'] ?? 0);
    $low_threshold   = intval($_POST['low_stock_threshold'] ?? 0);
    $barcode         = trim($_POST['barcode'] ?? '');
    $description     = trim($_POST['description'] ?? '');

    // Validation
    if (empty($name))               $errors[] = "Product name is required";
    if ($cost_price <= 0)           $errors[] = "Cost price must be greater than 0";
    if ($selling_price <= 0)        $errors[] = "Selling price must be greater than 0";
    if ($selling_price < $cost_price) $errors[] = "Selling price should be higher than cost price";

    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                UPDATE products SET 
                    name = ?, category = ?, unit = ?, conversion_factor = ?,
                    cost_price = ?, selling_price = ?, low_stock_threshold = ?,
                    barcode = ?, description = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $name, $category, $unit, $conversion,
                $cost_price, $selling_price, $low_threshold,
                $barcode ?: null, $description, $product_id
            ]);

            $success = "Product '$name' updated successfully!";
            // Refresh product data
            $product = [
                'name' => $name, 'category' => $category, 'unit' => $unit,
                'conversion_factor' => $conversion, 'cost_price' => $cost_price,
                'selling_price' => $selling_price, 'low_stock_threshold' => $low_threshold,
                'barcode' => $barcode, 'description' => $description
            ];

            // Optional: redirect after success
            // header("Location: products.php?updated=1"); exit;

        } catch (Exception $e) {
            $errors[] = "Database error: " . htmlspecialchars($e->getMessage());
        }
    }
}

// Use existing product data or posted data (in case of error)
$form_data = !empty($errors) && $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : $product;
?>

<?php include '../includes/header.php'; ?>

<div class="container" style="max-width: 900px; margin: 2rem auto; padding: 0 1rem;">

    <h1 style="font-size: 1.8rem; margin-bottom: 1.5rem; color: #1e293b;">
        <span class="material-icons" style="vertical-align: middle; font-size: 2rem; color: #2563eb; margin-right: 0.6rem;">edit</span>
        Edit Product
    </h1>

    <?php if ($success): ?>
        <div class="alert" style="background:#ecfdf5; color:#065f46; border:1px solid #a7f3d0; padding:1.2rem; border-radius:0.5rem; margin-bottom:1.5rem; display:flex; align-items:center; gap:0.8rem;">
            <span class="material-icons">check_circle</span>
            <?= htmlspecialchars($success) ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <div class="alert" style="background:#fef2f2; color:#991b1b; border:1px solid #fecaca; padding:1.2rem; border-radius:0.5rem; margin-bottom:1.5rem;">
            <ul style="margin:0; padding-left:1.4rem;">
                <?php foreach ($errors as $err): ?>
                    <li><?= htmlspecialchars($err) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if ($product): ?>
        <form method="POST" style="background:white; padding:2rem; border-radius:0.75rem; box-shadow:0 4px 6px -1px rgba(0,0,0,0.1); border:1px solid #e5e7eb;">
            
            <div style="margin-bottom:1.4rem;">
                <label for="name" style="display:block; font-weight:500; margin-bottom:0.4rem;">Product Name *</label>
                <input type="text" id="name" name="name" required 
                       value="<?= htmlspecialchars($form_data['name'] ?? '') ?>"
                       style="width:100%; padding:0.7rem; border:1px solid #d1d5db; border-radius:0.375rem;">
            </div>

            <div style="margin-bottom:1.4rem;">
                <label for="category" style="display:block; font-weight:500; margin-bottom:0.4rem;">Category</label>
                <input type="text" id="category" name="category" 
                       value="<?= htmlspecialchars($form_data['category'] ?? '') ?>"
                       style="width:100%; padding:0.7rem; border:1px solid #d1d5db; border-radius:0.375rem;">
            </div>

            <div style="margin-bottom:1.4rem;">
                <label for="unit" style="display:block; font-weight:500; margin-bottom:0.4rem;">Unit *</label>
                <select id="unit" name="unit" required 
                        style="width:100%; padding:0.7rem; border:1px solid #d1d5db; border-radius:0.375rem;">
                    <option value="">Select unit</option>
                    <option value="kg"     <?= ($form_data['unit'] ?? '') === 'kg'     ? 'selected' : '' ?>>kg</option>
                    <option value="pieces" <?= ($form_data['unit'] ?? '') === 'pieces' ? 'selected' : '' ?>>pieces</option>
                    <option value="liters" <?= ($form_data['unit'] ?? '') === 'liters' ? 'selected' : '' ?>>liters</option>
                    <option value="crates" <?= ($form_data['unit'] ?? '') === 'crates' ? 'selected' : '' ?>>crates</option>
                    <option value="other"  <?= ($form_data['unit'] ?? '') === 'other'  ? 'selected' : '' ?>>other</option>
                </select>
            </div>

            <div style="margin-bottom:1.4rem;">
                <label for="conversion_factor" style="display:block; font-weight:500; margin-bottom:0.4rem;">
                    Conversion Factor (e.g., 1 crate = 24 pieces)
                </label>
                <input type="number" id="conversion_factor" name="conversion_factor" step="0.01" min="1"
                       value="<?= htmlspecialchars($form_data['conversion_factor'] ?? '1') ?>"
                       style="width:100%; padding:0.7rem; border:1px solid #d1d5db; border-radius:0.375rem;">
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:1.4rem; margin-bottom:1.4rem;">
                <div>
                    <label for="cost_price" style="display:block; font-weight:500; margin-bottom:0.4rem;">Cost Price (₦) *</label>
                    <input type="number" id="cost_price" name="cost_price" step="0.01" min="0" required
                           value="<?= htmlspecialchars($form_data['cost_price'] ?? '') ?>"
                           style="width:100%; padding:0.7rem; border:1px solid #d1d5db; border-radius:0.375rem;">
                </div>
                <div>
                    <label for="selling_price" style="display:block; font-weight:500; margin-bottom:0.4rem;">Selling Price (₦) *</label>
                    <input type="number" id="selling_price" name="selling_price" step="0.01" min="0" required
                           value="<?= htmlspecialchars($form_data['selling_price'] ?? '') ?>"
                           style="width:100%; padding:0.7rem; border:1px solid #d1d5db; border-radius:0.375rem;">
                </div>
            </div>

            <div style="margin-bottom:1.4rem;">
                <label for="low_stock_threshold" style="display:block; font-weight:500; margin-bottom:0.4rem;">Low Stock Alert Threshold</label>
                <input type="number" id="low_stock_threshold" name="low_stock_threshold" min="0"
                       value="<?= htmlspecialchars($form_data['low_stock_threshold'] ?? '10') ?>"
                       style="width:100%; padding:0.7rem; border:1px solid #d1d5db; border-radius:0.375rem;">
            </div>

            <div style="margin-bottom:1.4rem;">
                <label for="barcode" style="display:block; font-weight:500; margin-bottom:0.4rem;">Barcode / SKU (optional)</label>
                <input type="text" id="barcode" name="barcode"
                       value="<?= htmlspecialchars($form_data['barcode'] ?? '') ?>"
                       style="width:100%; padding:0.7rem; border:1px solid #d1d5db; border-radius:0.375rem;">
            </div>

            <div style="margin-bottom:1.8rem;">
                <label for="description" style="display:block; font-weight:500; margin-bottom:0.4rem;">Description</label>
                <textarea id="description" name="description" rows="4"
                          style="width:100%; padding:0.7rem; border:1px solid #d1d5db; border-radius:0.375rem; resize:vertical;"><?= htmlspecialchars($form_data['description'] ?? '') ?></textarea>
            </div>

            <hr style="margin:2rem 0; border-color:#e5e7eb;">

            <div style="display:flex; justify-content:space-between; align-items:center;">
                <a href="products.php" style="color:#6b7280; text-decoration:none; font-weight:500;">← Back to Inventory</a>
                <button type="submit" 
                        style="background:#2563eb; color:white; padding:0.8rem 1.8rem; border:none; border-radius:0.5rem; font-weight:500; cursor:pointer; transition:background 0.2s;">
                    Update Product
                </button>
            </div>
        </form>
    <?php endif; ?>

</div>

<?php include '../includes/footer.php'; ?>