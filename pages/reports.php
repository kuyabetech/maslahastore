<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

$page_title = "Reports";

// Require owner or manager role for full reports
if (!in_array($_SESSION['user_role'], ['owner', 'manager'])) {
    header("Location: ../pages/dashboard.php");
    exit;
}

// Time periods
$today = date('Y-m-d');
$start_week = date('Y-m-d', strtotime('monday this week'));
$start_month = date('Y-m-01');

// 1. Sales & Profit Summary
$sales_query = "
    SELECT 
        DATE(s.sale_date) AS sale_date,
        SUM(s.grand_total) AS total_sales,
        SUM(s.total_cost) AS total_cost,
        SUM(s.vat) AS total_vat
    FROM sales s
    GROUP BY DATE(s.sale_date)
    ORDER BY sale_date DESC
    LIMIT 30
";
$sales_data = $pdo->query($sales_query)->fetchAll(PDO::FETCH_ASSOC);

// Calculate profit
$daily_profit = [];
foreach ($sales_data as $row) {
    $daily_profit[$row['sale_date']] = $row['total_sales'] - $row['total_cost'];
}

// 2. Totals
$total_sales_today = $pdo->query("SELECT SUM(grand_total) FROM sales WHERE DATE(sale_date) = CURDATE()")->fetchColumn() ?: 0;
$total_profit_today = $pdo->query("SELECT SUM(grand_total - total_cost) FROM sales WHERE DATE(sale_date) = CURDATE()")->fetchColumn() ?: 0;

$total_sales_week = $pdo->query("SELECT SUM(grand_total) FROM sales WHERE sale_date >= '$start_week'")->fetchColumn() ?: 0;
$total_profit_week = $pdo->query("SELECT SUM(grand_total - total_cost) FROM sales WHERE sale_date >= '$start_week'")->fetchColumn() ?: 0;

$total_sales_month = $pdo->query("SELECT SUM(grand_total) FROM sales WHERE sale_date >= '$start_month'")->fetchColumn() ?: 0;
$total_profit_month = $pdo->query("SELECT SUM(grand_total - total_cost) FROM sales WHERE sale_date >= '$start_month'")->fetchColumn() ?: 0;

// 3. Expenses (subtract from profit)
$total_expenses_month = $pdo->query("SELECT SUM(amount) FROM expenses WHERE expense_date >= '$start_month'")->fetchColumn() ?: 0;
$net_profit_month = $total_profit_month - $total_expenses_month;

// 4. Low stock & near expiry
$low_stock = get_low_stock_products($pdo);
$near_expiry = $pdo->query("
    SELECT p.name, b.expiry_date, b.quantity
    FROM product_batches b
    JOIN products p ON b.product_id = p.id
    WHERE b.quantity > 0 AND b.expiry_date <= DATE_ADD(CURDATE(), INTERVAL 14 DAY)
    ORDER BY b.expiry_date ASC
    LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);

// Prepare chart data (last 7 days for simplicity)
$chart_labels = [];
$chart_sales = [];
$chart_profit = [];

$start = strtotime('-6 days');
for ($i = 0; $i < 7; $i++) {
    $day = date('Y-m-d', $start + $i * 86400);
    $chart_labels[] = date('d M', strtotime($day));

    $found = false;
    foreach ($sales_data as $row) {
        if ($row['sale_date'] === $day) {
            $chart_sales[] = (float)$row['total_sales'];
            $chart_profit[] = (float)($row['total_sales'] - $row['total_cost']);
            $found = true;
            break;
        }
    }
    if (!$found) {
        $chart_sales[] = 0;
        $chart_profit[] = 0;
    }
}
?>

<?php include '../includes/header.php'; ?>

<h2>Business Reports</h2>

<div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(280px, 1fr)); gap:1.5rem; margin:2rem 0;">
    <div class="card">
        <h3>Today</h3>
        <p>Sales: <strong><?= format_naira($total_sales_today) ?></strong></p>
        <p>Profit: <strong style="color:var(--success);"><?= format_naira($total_profit_today) ?></strong></p>
    </div>

    <div class="card">
        <h3>This Week</h3>
        <p>Sales: <strong><?= format_naira($total_sales_week) ?></strong></p>
        <p>Profit: <strong style="color:var(--success);"><?= format_naira($total_profit_week) ?></strong></p>
    </div>

    <div class="card">
        <h3>This Month</h3>
        <p>Sales: <strong><?= format_naira($total_sales_month) ?></strong></p>
        <p>Gross Profit: <strong style="color:var(--success);"><?= format_naira($total_profit_month) ?></strong></p>
        <p>Net Profit (after expenses): <strong style="color:<?= $net_profit_month >= 0 ? 'var(--success)' : 'var(--danger)' ?>;"><?= format_naira($net_profit_month) ?></strong></p>
    </div>
</div>

<!-- Alerts -->
<?php if (!empty($low_stock) || !empty($near_expiry)): ?>
    <div class="card alert-low" style="margin:2rem 0;">
        <h3>Urgent Alerts</h3>
        <?php if (!empty($low_stock)): ?>
            <p><strong>Low Stock (<?= count($low_stock) ?> items):</strong></p>
            <ul style="margin-left:1.5rem;">
                <?php foreach ($low_stock as $ls): ?>
                    <li><?= htmlspecialchars($ls['name']) ?> – <?= number_format($ls['stock']) ?> left</li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <?php if (!empty($near_expiry)): ?>
            <p><strong>Near Expiry (next 14 days):</strong></p>
            <ul style="margin-left:1.5rem;">
                <?php foreach ($near_expiry as $ne): ?>
                    <li><?= htmlspecialchars($ne['name']) ?> – Expires <?= date('d M Y', strtotime($ne['expiry_date'])) ?> (<?= $ne['quantity'] ?> left)</li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
<?php endif; ?>

<!-- Chart -->
<div class="card" style="margin-top:2rem;">
    <h3>Last 7 Days Sales & Profit</h3>
    <canvas id="salesChart" height="200"></canvas>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    const ctx = document.getElementById('salesChart').getContext('2d');
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: <?= json_encode($chart_labels) ?>,
            datasets: [
                {
                    label: 'Sales (₦)',
                    data: <?= json_encode($chart_sales) ?>,
                    backgroundColor: 'rgba(54, 162, 235, 0.6)',
                    borderColor: 'rgba(54, 162, 235, 1)',
                    borderWidth: 1
                },
                {
                    label: 'Profit (₦)',
                    data: <?= json_encode($chart_profit) ?>,
                    backgroundColor: 'rgba(75, 192, 192, 0.6)',
                    borderColor: 'rgba(75, 192, 192, 1)',
                    borderWidth: 1
                }
            ]
        },
        options: {
            responsive: true,
            scales: {
                y: { beginAtZero: true }
            }
        }
    });
</script>

<!-- Export stub -->
<div style="margin-top:2rem; text-align:center;">
    <button onclick="alert('CSV export coming soon – full data download in next version');" style="padding:0.8rem 2rem; background:var(--primary); color:white; border:none; border-radius:6px; cursor:pointer;">
        Export Report as CSV
    </button>
</div>

<?php include '../includes/footer.php'; ?>