<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Simple role-based protection (expand later)
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}
// Get user role (must be set during login)
$user_role = $_SESSION['user_role'] ?? 'cashier'; // fallback

$page_title = "Dashboard";

// ── Real stats (using your existing functions + safe queries) ────────

// Low stock count
$low_stock_items = get_low_stock_products($pdo);
$low_stock_count = count($low_stock_items);

// Total products
$total_products = $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn() ?: 0;

// Inventory value (selling price × current stock)
$inventory_value_stmt = $pdo->query("
    SELECT COALESCE(SUM(p.selling_price * (
        SELECT SUM(quantity) 
        FROM product_batches 
        WHERE product_id = p.id 
        AND quantity > 0
    )), 0) AS total_value
    FROM products p
");
$inventory_value = (float) $inventory_value_stmt->fetchColumn() ?: 0;

// Last updated
$last_updated = date('d M Y, h:i A');

// Your original placeholders
$today_sales = 0;
$today_profit = 0;
?>

<?php include '../includes/header.php'; ?>

<!-- Font Awesome 6 CDN (add once in header.php or here) -->


<h2 style="text-align:center; margin:1.5rem 0 0.5rem 0; color:#1e293b;">
    <i class="fas fa-tachometer-alt" style="color:#3b82f6; margin-right:0.5rem;"></i>
    Welcome, <?= htmlspecialchars($_SESSION['username']) ?>!
</h2>

<p style="text-align:center; color:var(--gray); margin-bottom:1rem; font-size:0.9rem;">
    Last updated: <?= $last_updated ?> • Product Management Dashboard
</p>

<!--<p style="text-align:center; color:#dc2626; margin-bottom:2rem; font-weight:500;">
    Note: Currently limited to product management only (add, edit, delete).
</p>
-->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 1.5rem; margin: 2rem 0;">

    <!-- Total Products -->
    <div class="card" style="transition:transform 0.2s;" onmouseover="this.style.transform='translateY(-6px)';" onmouseout="this.style.transform='translateY(0)';">
        <h3><i class="fas fa-boxes-stacked" style="color:#3b82f6; margin-right:0.5rem;"></i>Total Products</h3>
        <div class="stat-number" style="color:#3b82f6;"><?= number_format($total_products) ?></div>
        <small style="color:var(--gray);">In inventory</small>
    </div>

    <!-- Inventory Value -->
   <!-- <div class="card" style="transition:transform 0.2s;" onmouseover="this.style.transform='translateY(-6px)';" onmouseout="this.style.transform='translateY(0)';">
        <h3><i class="fas fa-coins" style="color:#10b981; margin-right:0.5rem;"></i>Inventory Value</h3>
        <div class="stat-number" style="color:#10b981;"><?= format_naira($inventory_value) ?></div>
        <small style="color:var(--gray);">Estimated selling value</small>
    </div>
-->
    <!-- Low Stock -->
    <div class="card <?= $low_stock_count > 0 ? 'alert-low' : '' ?>" style="transition:transform 0.2s;" onmouseover="this.style.transform='translateY(-6px)';" onmouseout="this.style.transform='translateY(0)';">
        <h3><i class="fas fa-triangle-exclamation" style="color:#ef4444; margin-right:0.5rem;"></i>Low Stock Items</h3>
        <div class="stat-number"><?= $low_stock_count ?></div>
        <?php if ($low_stock_count > 0): ?>
            <p><a href="products.php" style="color:var(--danger);">View products →</a></p>
        <?php else: ?>
            <p style="color:#10b981;"><i class="fas fa-check-circle" style="margin-right:0.3rem;"></i>All stock levels good</p>
        <?php endif; ?>
    </div>

    <!-- Your original sales/profit cards (kept unchanged) -->
    <div class="card">
        <h3><i class="fas fa-shopping-cart" style="color:#3b82f6; margin-right:0.5rem;"></i>Today's Sales</h3>
        <div class="stat-number"><?= format_naira($today_sales) ?></div>
        <small style="color:var(--gray);">As of <?= date('d M Y') ?></small>
    </div>

    <div class="card">
        <h3><i class="fas fa-chart-line" style="color:#10b981; margin-right:0.5rem;"></i>Today's Profit</h3>
        <div class="stat-number"><?= format_naira($today_profit) ?></div>
    </div>

</div>

<!-- Quick links (kept your exact original structure) -->
<div style="margin-top: 2rem;">
    <h3>Quick Actions</h3>
    <div style="display: flex; flex-wrap: wrap; gap: 1rem;">
      <?php if ($user_role === 'owner'): ?>
            <a href="add_product.php" style="padding:1rem 1.5rem; background:var(--primary); color:white; border-radius:8px; text-decoration:none;">
                <span class="material-icons" style="vertical-align:middle; margin-right:0.5rem;">add_box</span>+ Add Product
            </a>
        <?php endif; ?>  

        <a href="add_sale.php" style="padding:1rem 1.5rem; background:var(--success); color:white; border-radius:8px; text-decoration:none;">
            <i class="fas fa-cash-register" style="margin-right:0.5rem;"></i>Record Sale
        </a>
        <a href="expenses.php" style="padding:1rem 1.5rem; background:var(--warning); color:#333; border-radius:8px; text-decoration:none;">
            <i class="fas fa-file-invoice-dollar" style="margin-right:0.5rem;"></i>Log Expense
        </a>
    </div>
</div>

<?php include '../includes/footer.php'; ?>