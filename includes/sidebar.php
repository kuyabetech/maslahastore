<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <h3>MASLAHA STORE</h3>
        <button class="close-btn" onclick="toggleSidebar()">×</button>
    </div>

    <nav>
        <a href="dashboard.php" class="<?= basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active' : '' ?>">
            Dashboard
        </a>
        <a href="products.php" class="<?= basename($_SERVER['PHP_SELF']) === 'products.php' ? 'active' : '' ?>">
            Products
        </a>
                <?php if (isset($_SESSION['user_role']) && in_array($_SESSION['user_role'], ['owner', 'manager'])): ?>
                    <a href="add_product.php">Add Product</a>
            <a href="users.php">Users</a>
        <?php endif; ?>


        <div class="menu-item has-submenu">
            <a href="#" onclick="toggleSubmenu(this)">Sales & Transactions</a>
            <div class="submenu">
                <a href="add_sale.php">Record Sale</a>
                <a href="returns.php">Returns</a>
            </div>
        </div>

        <a href="expenses.php">Expenses</a>
        <a href="reports.php">Reports</a>



        <a href="../logout.php" class="logout">Logout</a>
    </nav>
</aside>