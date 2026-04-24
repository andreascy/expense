<?php $pageTitle = $pageTitle ?? 'Dashboard'; ?>
<header class="topbar">
    <div class="topbar-left">
        <button class="topbar-toggle d-xl-none" id="sidebarToggle">
            <i class="bi bi-list"></i>
        </button>
        <div class="topbar-title">
            <h1><?= htmlspecialchars($pageTitle) ?></h1>
            <?php if (!empty($pageSubtitle)): ?>
            <span class="topbar-subtitle"><?= htmlspecialchars($pageSubtitle) ?></span>
            <?php endif; ?>
        </div>
    </div>
    <div class="topbar-right">
        <?php if (!empty($topbarActions)): ?>
            <?= $topbarActions ?>
        <?php endif; ?>
    </div>
</header>
