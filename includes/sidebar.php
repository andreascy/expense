<?php
$currentUser = $_SESSION['user'] ?? ['name' => 'User', 'email' => '', 'role' => 'user'];
$activePage  = $activePage ?? '';
$initials    = strtoupper(substr(implode('', array_map(fn($w) => $w[0], explode(' ', trim($currentUser['name'])))), 0, 2));

// Load company logo / name from settings
$companyName = 'LumenBooks';
$companyLogo = '';
try {
    require_once __DIR__ . '/../config/database.php';
    $s = getDb()->query("SELECT key, value FROM settings WHERE key IN ('APP_COMPANY','COMPANY_LOGO')")->fetchAll(PDO::FETCH_KEY_PAIR);
    if (!empty($s['APP_COMPANY'])) $companyName = $s['APP_COMPANY'];
    if (!empty($s['COMPANY_LOGO'])) $companyLogo = $s['COMPANY_LOGO'];
} catch (Throwable $e) {}

$nav = [
    ['id' => 'dashboard',  'label' => 'Dashboard',        'icon' => 'bi-grid-1x2',       'href' => '/pages/dashboard.php'],
    ['id' => 'expense',    'label' => 'Expense Entry',     'icon' => 'bi-receipt-cutoff', 'href' => '/index.php'],
    ['id' => 'je',         'label' => 'Journal Entry',     'icon' => 'bi-journal-text',   'href' => '/pages/journal-entry.php'],
    ['sep' => true],
    ['id' => 'bp',         'label' => 'Business Partners', 'icon' => 'bi-people-fill',    'href' => '/pages/business-partners.php'],
    ['sep' => true],
    ['id' => 'accounts',   'label' => 'Account Setup',     'icon' => 'bi-diagram-3',      'href' => '/pages/accounts-setup.php'],
    ['id' => 'sap',        'label' => 'SAP Setup',         'icon' => 'bi-gear-wide',      'href' => '/pages/sap-setup.php'],
    ['id' => 'company',    'label' => 'Company Setup',     'icon' => 'bi-building',       'href' => '/pages/company-setup.php'],
    ['id' => 'users',      'label' => 'Users',             'icon' => 'bi-person-gear',    'href' => '/pages/users.php', 'adminOnly' => true],
];
?>
<div class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <div class="sidebar-logo">
            <?php if ($companyLogo): ?>
            <img src="/<?= htmlspecialchars($companyLogo) ?>" class="company-logo-img" alt="Logo">
            <?php else: ?>
            <img src="/assets/img/logo.svg" class="sidebar-logo-icon" alt="LumenBooks" style="width:36px;height:36px;background:none;padding:0">
            <?php endif; ?>
            <div class="sidebar-logo-text">
                <span class="logo-name">LumenBooks</span>
                <span class="logo-sub"><?= htmlspecialchars($companyName) ?></span>
            </div>
        </div>
        <button class="sidebar-close d-xl-none" id="sidebarClose"><i class="bi bi-x-lg"></i></button>
    </div>

    <nav class="sidebar-nav">
        <?php foreach ($nav as $item):
            if (!empty($item['sep'])): ?>
            <div class="nav-group-sep"></div>
            <?php continue; endif;
            if (!empty($item['adminOnly']) && ($currentUser['role'] ?? '') !== 'admin') continue;
        ?>
        <a href="<?= $item['href'] ?>" class="nav-item <?= $activePage === $item['id'] ? 'active' : '' ?>">
            <i class="bi <?= $item['icon'] ?> nav-icon"></i>
            <span><?= $item['label'] ?></span>
            <?php if ($activePage === $item['id']): ?><span class="nav-active-dot"></span><?php endif; ?>
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
            <a href="/logout.php" class="logout-btn" title="Logout"><i class="bi bi-box-arrow-right"></i></a>
        </div>
    </div>
</div>
<div class="sidebar-overlay d-xl-none" id="sidebarOverlay"></div>
