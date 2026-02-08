<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MASLAHA STORE<?= isset($page_title) ? " - $page_title" : "" ?></title>
    <link rel="stylesheet" href="../assets/css/style.css?v=<?= time() ?>">
    <!-- Optional: QuaggaJS if you're using barcode scanning -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" integrity="sha512-SnH5WK+bZxgPHs44uWIX+LLJAJ9/2PkPKZ5QiAj6Ta86w+fsb2TkcmfRyVX3pBnMFcV7oQPJkl9QevSCWr3W6A==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <script src="https://cdn.jsdelivr.net/npm/@ericblade/quagga2@1.8.4/dist/quagga.min.js" defer></script>
    <script src="../assets/js/main.js" defer></script>
</head>
<body>

<header class="navbar">
    <div class="navbar-container">
        <!-- Logo + Brand -->
        <a href="../pages/dashboard.php" class="navbar-brand">
            <img 
                src="../assets/img/logo.jpg" 
                alt="MASLAHA STORE" 
                class="logo-img"
                width="180" 
                height="60"
                loading="lazy"
            >
            <span class="logo-text">MASLAHA STORE</span>
        </a>

        <!-- Hamburger toggle (mobile only) -->
        <button 
            class="navbar-toggle" 
            aria-label="Toggle sidebar menu" 
            aria-expanded="false"
            onclick="toggleSidebar()"
        >
            <span class="bar"></span>
            <span class="bar"></span>
            <span class="bar"></span>
        </button>

        <!-- Right side: User info + dropdown -->
        <?php if (isset($_SESSION['user_id'])): ?>
            <div class="navbar-right">
                <div class="user-dropdown">
                    <button class="user-btn" aria-haspopup="true" aria-expanded="false">
                        <span class="user-avatar">
                            <!-- Optional: user photo or icon -->
                            <svg width="32" height="32" viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <circle cx="16" cy="16" r="16" fill="#3b82f6"/>
                                <path d="M16 12C17.6569 12 19 10.6569 19 9C19 7.34315 17.6569 6 16 6C14.3431 6 13 7.34315 13 9C13 10.6569 14.3431 12 16 12Z" fill="white"/>
                                <path d="M22 22C22 18.6863 19.3137 16 16 16C12.6863 16 10 18.6863 10 22H22Z" fill="white"/>
                            </svg>
                        </span>
                        <div class="user-info">
                            <span class="user-name"><?= htmlspecialchars($_SESSION['username'] ?? 'User') ?></span>
                            <span class="user-role"><?= ucfirst($_SESSION['user_role'] ?? 'User') ?></span>
                        </div>
                    </button>

                    <div class="dropdown-menu">
                        <a href="../logout.php" class="dropdown-item logout-item">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                                <polyline points="16 17 21 12 16 7"/>
                                <line x1="21" y1="12" x2="9" y2="12"/>
                            </svg>
                            Logout
                        </a>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</header>

<!-- Sidebar -->
<?php include 'sidebar.php'; ?>

<main class="main-content">