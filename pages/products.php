<?php
// ── Same backend logic as before ────────────────────────────────────────
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

$user_role = $_SESSION['user_role'] ?? 'cashier';
$page_title = "Inventory";

// CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Delete logic (owner only) ── unchanged ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_product']) && $user_role === 'owner') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $message = ['type' => 'error', 'text' => 'Security token mismatch.'];
    } else {
        $product_id = (int)$_POST['product_id'];
        try {
            $pdo->beginTransaction();
            $pdo->prepare("DELETE FROM product_batches WHERE product_id = ?")->execute([$product_id]);
            $pdo->prepare("DELETE FROM products WHERE id = ?")->execute([$product_id]);
            $pdo->commit();
            $message = ['type' => 'success', 'text' => 'Product deleted successfully.'];
        } catch (Exception $e) {
            $pdo->rollBack();
            $message = ['type' => 'error', 'text' => 'Delete failed: ' . htmlspecialchars($e->getMessage())];
        }
    }
}

// Search & filter ── unchanged ──
$search = trim($_GET['search'] ?? '');
$show_low = isset($_GET['show_low']) && $_GET['show_low'] === 'true';

$where = $params = [];
if ($search !== '') {
    $where[] = "(p.name LIKE ? OR p.barcode LIKE ? OR p.category LIKE ? OR p.description LIKE ?)";
    $searchParam = "%$search%";
    $params = [$searchParam, $searchParam, $searchParam, $searchParam];
}
if ($show_low) {
    $where[] = "(p.low_stock_threshold > 0 AND COALESCE(SUM(b.quantity), 0) <= p.low_stock_threshold)";
}
$where_clause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// Products query ── simplified GROUP BY ──
$query = "
    SELECT p.id, p.name, p.category, p.unit, p.barcode,
           p.cost_price, p.selling_price, p.low_stock_threshold, p.description,
           COALESCE(SUM(b.quantity), 0) AS total_stock,
           MIN(b.expiry_date) AS nearest_expiry
    FROM products p
    LEFT JOIN product_batches b ON p.id = b.product_id
    $where_clause
    GROUP BY p.id
    ORDER BY p.name ASC
";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

$low_stock_count = count(get_low_stock_products($pdo));
?>

<?php include '../includes/header.php'; ?>

<style>
    :root {
        --primary: #2563eb;
        --primary-dark: #1d4ed8;
        --danger: #dc2626;
        --warning: #d97706;
        --success: #16a34a;
        --gray-100: #f3f4f6;
        --gray-200: #e5e7eb;
        --gray-600: #4b5563;
        --gray-800: #1f2937;
    }

    .container { max-width: 1400px; margin: 0 auto; padding: 1.5rem; }

    h1.page-title {
        font-size: 1.875rem;
        font-weight: 700;
        color: var(--gray-800);
        display: flex;
        align-items: center;
        gap: 0.75rem;
        margin-bottom: 1.75rem;
    }

    .alert {
        padding: 1rem 1.25rem;
        border-radius: 0.5rem;
        margin-bottom: 1.5rem;
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }
    .alert-success { background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; }
    .alert-error   { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
    .alert-warning { background: #fffbeb; color: #92400e; border: 1px solid #fde68a; }

    .controls {
        display: flex;
        flex-wrap: wrap;
        gap: 1rem;
        margin-bottom: 1.75rem;
        align-items: center;
    }

    .search-form {
        flex: 1;
        min-width: 280px;
    }
    .search-input {
        width: 100%;
        padding: 0.75rem 1rem;
        border: 1px solid var(--gray-200);
        border-radius: 0.5rem;
        font-size: 0.95rem;
    }
    .btn {
        padding: 0.7rem 1.4rem;
        border-radius: 0.5rem;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.2s;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        text-decoration: none;
    }
    .btn-primary    { background: var(--primary); color: white; border: none; }
    .btn-primary:hover { background: var(--primary-dark); }
    .btn-success    { background: var(--success); color: white; }
    .btn-danger     { color: var(--danger); background: none; border: none; padding: 0; font-size: 0.9rem; }
    .btn-danger:hover { color: #b91c1c; text-decoration: underline; }

    .filter-toggle {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        white-space: nowrap;
        font-size: 0.95rem;
        color: var(--gray-600);
    }

    .table-container {
        background: white;
        border-radius: 0.75rem;
        box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1), 0 2px 4px -1px rgba(0,0,0,0.06);
        overflow: hidden;
        border: 1px solid var(--gray-200);
    }

    table.inventory-table {
        width: 100%;
        border-collapse: collapse;
    }
    th, td {
        padding: 1rem 1.25rem;
        text-align: left;
        border-bottom: 1px solid var(--gray-200);
    }
    th {
        background: var(--gray-100);
        font-weight: 600;
        color: var(--gray-600);
        font-size: 0.875rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }
    tr:hover {
        background: #f8fafc;
    }
    tr.low-stock { background: #fffbeb; }
    tr.out-of-stock { background: #fef2f2; }
    .stock-qty { font-weight: 600; }
    .expiry-warning { color: var(--danger); font-weight: 600; }

    .actions { display: flex; gap: 1.1rem; font-size: 0.9rem; }
    .actions a { color: var(--primary); font-weight: 500; }
    .actions a:hover { text-decoration: underline; }

    .summary-bar {
        margin-top: 1.75rem;
        padding: 1.25rem;
        background: var(--gray-100);
        border-radius: 0.5rem;
        display: flex;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 1.5rem;
        font-size: 0.95rem;
    }
    .summary-item strong { color: var(--gray-800); }

    @media (max-width: 768px) {
        .table-container { border: none; box-shadow: none; }
        table, thead, tbody, th, td, tr { display: block; }
        thead tr { position: absolute; top: -9999px; left: -9999px; }
        tr {
            margin-bottom: 1.25rem;
            border: 1px solid var(--gray-200);
            border-radius: 0.75rem;
            background: white;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        td {
            border: none;
            padding: 0.9rem 1.25rem;
            position: relative;
            padding-left: 50%;
        }
        td:before {
            content: attr(data-label);
            position: absolute;
            left: 1.25rem;
            width: 45%;
            font-weight: 600;
            color: var(--gray-600);
        }
        .actions { justify-content: flex-start; margin-top: 0.5rem; }
    }
</style>

<div class="container">

    <h1 class="page-title">
        <span class="material-icons" style="font-size:2.2rem; color:var(--primary);">inventory_2</span>
        Inventory
    </h1>

    <?php if (isset($message)): ?>
        <div class="alert alert-<?= $message['type'] === 'success' ? 'success' : 'error' ?>">
            <span class="material-icons"><?= $message['type'] === 'success' ? 'check_circle' : 'error' ?></span>
            <?= htmlspecialchars($message['text']) ?>
        </div>
    <?php endif; ?>

    <div class="controls">
        <form method="GET" class="search-form">
            <input type="text" name="search" class="search-input"
                   placeholder="Search products, barcode, category..." value="<?= htmlspecialchars($search) ?>">
        </form>

        <form method="GET">
            <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">
            <label class="filter-toggle">
                <input type="checkbox" name="show_low" value="true" <?= $show_low ? 'checked' : '' ?> onchange="this.form.submit()">
                Low / out-of-stock only
            </label>
        </form>

        <?php if (in_array($user_role, ['admin','owner'])): ?>
            <a href="product-add.php" class="btn btn-success">
                <span class="material-icons">add</span> Add Product
            </a>
        <?php endif; ?>
    </div>

    <?php if ($low_stock_count > 0 && !$show_low): ?>
        <div class="alert alert-warning">
            <span class="material-icons">warning</span>
            <div>
                <strong><?= $low_stock_count ?> item<?= $low_stock_count === 1 ? '' : 's' ?> low or out of stock</strong>
                <a href="?show_low=true<?= $search ? '&search='.urlencode($search) : '' ?>"
                   style="margin-left:1rem; color:var(--warning); font-weight:600;">View →</a>
            </div>
        </div>
    <?php endif; ?>

    <?php if (empty($products)): ?>
        <div style="text-align:center; padding:4rem 1rem; color:var(--gray-600); background:var(--gray-100); border-radius:0.75rem;">
            <h3 style="margin-bottom:0.75rem;">No products found</h3>
            <p><?= $search || $show_low ? "Try adjusting your search or filter." : "Add your first product to get started." ?></p>
        </div>
    <?php else: ?>
        <div class="table-container">
            <table class="inventory-table">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Category</th>
                        <th>Unit</th>
                        <th>Stock</th>
                        <th>Cost</th>
                        <th>Sell</th>
                        <th>Expiry</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($products as $p):
                    $stock = (float)$p['total_stock'];
                    $threshold = (float)$p['low_stock_threshold'];
                    $class = $stock <= 0 ? 'out-of-stock' : ($threshold > 0 && $stock <= $threshold ? 'low-stock' : '');
                    $expiryWarn = $p['nearest_expiry'] && (strtotime($p['nearest_expiry']) - time()) / 86400 <= 30;
                ?>
                    <tr class="<?= $class ?>">
                        <td data-label="Product">
                            <div style="font-weight:600;"><?= htmlspecialchars($p['name']) ?></div>
                            <div style="font-size:0.85rem; color:var(--gray-600);">
                                <?= htmlspecialchars($p['barcode'] ?: '—') ?>
                            </div>
                        </td>
                        <td data-label="Category"><?= htmlspecialchars($p['category'] ?: '—') ?></td>
                        <td data-label="Unit"><?= htmlspecialchars($p['unit'] ?: '—') ?></td>
                        <td data-label="Stock">
                            <span class="stock-qty"><?= number_format($stock, 2) ?></span>
                            <?php if ($threshold > 0): ?>
                                <span style="font-size:0.85rem; color:var(--gray-600);">
                                    / <?= $threshold ?>
                                </span>
                            <?php endif; ?>
                        </td>
                        <td data-label="Cost"><?= number_format($p['cost_price'], 2) ?></td>
                        <td data-label="Sell"><?= number_format($p['selling_price'], 2) ?></td>
                        <td data-label="Expiry" class="<?= $expiryWarn ? 'expiry-warning' : '' ?>">
                            <?= $p['nearest_expiry'] ? date('d M Y', strtotime($p['nearest_expiry'])) : '—' ?>
                        </td>
                        <td data-label="Actions" class="actions">
                            <a href="product-view.php?id=<?= $p['id'] ?>">View</a>
                            <?php if (in_array($user_role, ['admin','owner'])): ?>
                                <a href="product-edit.php?id=<?= $p['id'] ?>">Edit</a>
                            <?php endif; ?>
                            <?php if ($user_role === 'owner'): ?>
                                <form method="POST" style="display:inline;" 
                                      onsubmit="return confirm('Delete <?= htmlspecialchars(addslashes($p['name'])) ?>?');">
                                    <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                    <button type="submit" name="delete_product" class="btn btn-danger">
                                        Delete
                                    </button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="summary-bar">
            <div class="summary-item"><strong>Total Products:</strong> <?= count($products) ?></div>
            <div class="summary-item">
                <strong>Low Stock:</strong> 
                <span style="color:var(--warning);">
                    <?= count(array_filter($products, fn($p) => ($s=(float)$p['total_stock']) > 0 && ($t=(float)$p['low_stock_threshold']) > 0 && $s <= $t)) ?>
                </span>
            </div>
            <div class="summary-item">
                <strong>Out of Stock:</strong> 
                <span style="color:var(--danger);">
                    <?= count(array_filter($products, fn($p) => (float)$p['total_stock'] <= 0)) ?>
                </span>
            </div>
        </div>
    <?php endif; ?>

</div>

<?php include '../includes/footer.php'; ?>