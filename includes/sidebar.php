<?php
$currentUser = $_SESSION['user'] ?? ['name' => 'User', 'email' => '', 'role' => 'user'];
$activePage  = $activePage ?? '';
$nav = [
    ['id' => 'expense',  'label' => 'Expense Entry',   'icon' => 'bi-receipt-cutoff', 'href' => '/index.php'],
    ['id' => 'accounts', 'label' => 'Account Setup',   'icon' => 'bi-diagram-3',      'href' => '/pages/accounts-setup.php'],
    ['id' => 'sap',      'label' => 'SAP Setup',       'icon' => 'bi-gear-wide',      'href' => '/pages/sap-setup.php'],
    ['id' => 'users',    'label' => 'Users',            'icon' => 'bi-people',         'href' => '/pages/users.php'],
];
$initials = strtoupper(implode('', array_map(fn($w) => $w[0], explode(' ', trim($currentUser['name'])))));
$initials = substr($initials, 0, 2);
?>
<div class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <div class="sidebar-logo">
            <div class="sidebar-logo-icon"><i class="bi bi-receipt-cutoff"></i></div>
            <div class="sidebar-logo-text">
                <span class="logo-name">ExpenseFlow</span>
                <span class="logo-sub">SAP Business One</span>
            </div>
        </div>
        <button class="sidebar-close d-xl-none" id="sidebarClose"><i class="bi bi-x-lg"></i></button>
    </div>

    <nav class="sidebar-nav">
        <span class="nav-label">Navigation</span>
        <?php foreach ($nav as $item): ?>
        <a href="<?= $item['href'] ?>"
           class="nav-item <?= $activePage === $item['id'] ? 'active' : '' ?>">
            <i class="bi <?= $item['icon'] ?> nav-icon"></i>
            <span><?= $item['label'] ?></span>
            <?php if ($activePage === $item['id']): ?>
            <span class="nav-active-dot"></span>
            <?php endif; ?>
        </a>
        <?php endforeach; ?>
    </nav>

    <div class="sidebar-footer">
        <div class="user-card">
            <div class="user-avatar"><?= htmlspecialchars($initials) ?></div>
            <div class="user-info">
                <div class="user-name"><?= htmlspecialchars($currentUser['name']) ?></div>
                <div class="user-role"><?= ucfirst($currentUser['role']) ?></div>
            </div>
            <a href="/logout.php" class="logout-btn" title="Logout">
                <i class="bi bi-box-arrow-right"></i>
            </a>
        </div>
    </div>
</div>
<div class="sidebar-overlay d-xl-none" id="sidebarOverlay"></div>
