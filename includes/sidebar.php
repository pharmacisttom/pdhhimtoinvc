<?php
$currentPage = basename($_SERVER['PHP_SELF'] ?? '');
$connectionStatuses = getSystemConnectionStatuses();
$appMeta = getAppMeta();
$menuItems = [
    ['href' => 'index.php', 'label' => 'ภาพรวมผู้บริหาร', 'icon' => 'bi-speedometer2'],
    ['href' => 'weekly_executive_brief.php', 'label' => 'สรุปรายสัปดาห์', 'icon' => 'bi-journal-richtext'],
    ['href' => 'invc_stock.php', 'label' => 'ข้อมูลคลังยา INVC', 'icon' => 'bi-box-seam'],
    ['href' => 'pcu_dispense.php', 'label' => 'การเบิกของ PCU', 'icon' => 'bi-houses'],
    ['href' => 'purchase_report.php', 'label' => 'ข้อมูลการจัดซื้อยา', 'icon' => 'bi-receipt-cutoff'],
    ['href' => 'himpro_dispense.php', 'label' => 'ข้อมูลจ่าย Himpro', 'icon' => 'bi-clipboard2-pulse'],
    ['href' => 'drug_analysis.php', 'label' => 'วิเคราะห์รายยา', 'icon' => 'bi-activity'],
    ['href' => 'match_report.php', 'label' => 'Match INVC-Himpro', 'icon' => 'bi-diagram-3'],
    ['href' => 'mapping.php', 'label' => 'จัดการ Mapping', 'icon' => 'bi-link-45deg'],
    ['href' => 'logs.php', 'label' => 'ประวัติการใช้งาน', 'icon' => 'bi-clock-history'],
    ['href' => 'version.php', 'label' => 'เวอร์ชันระบบ', 'icon' => 'bi-git'],
];
?>
<style>
    :root {
        --sidebar-bg: #111827;
        --sidebar-bg-2: #1f2937;
        --sidebar-text: rgba(255, 255, 255, 0.88);
        --sidebar-muted: #94a3b8;
        --sidebar-border: rgba(148, 163, 184, 0.18);
        --sidebar-accent: #3b82f6;
        --sidebar-accent-2: #1d4ed8;
    }

    .app-sidebar {
        width: 286px;
        min-height: 100vh;
        background:
            radial-gradient(circle at top, rgba(59, 130, 246, 0.2), transparent 28%),
            linear-gradient(180deg, var(--sidebar-bg) 0%, var(--sidebar-bg-2) 100%);
        color: #fff;
        border-right: 1px solid var(--sidebar-border);
        position: sticky;
        top: 0;
        z-index: 1040;
    }

    .app-sidebar-inner {
        min-height: 100vh;
        padding: 1.25rem 1rem 1rem;
        display: flex;
        flex-direction: column;
        gap: 1rem;
    }

    .app-sidebar-brand {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 0.75rem;
    }

    .app-sidebar-title {
        color: #fff;
        text-decoration: none;
        font-weight: 700;
        font-size: 1.45rem;
        letter-spacing: -0.02em;
    }

    .app-sidebar-subtitle {
        color: var(--sidebar-muted);
        font-size: 0.92rem;
        margin-top: 0.2rem;
    }

    .app-sidebar-version {
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        margin-top: 0.7rem;
        padding: 0.35rem 0.7rem;
        border-radius: 999px;
        background: rgba(255, 255, 255, 0.08);
        color: #dbeafe;
        font-size: 0.78rem;
        font-weight: 600;
    }

    .app-sidebar-toggle {
        display: none;
        border: 1px solid rgba(255, 255, 255, 0.12);
        background: rgba(255, 255, 255, 0.06);
        color: #fff;
        border-radius: 0.85rem;
        width: 42px;
        height: 42px;
        align-items: center;
        justify-content: center;
    }

    .app-sidebar-divider {
        border-top: 1px solid var(--sidebar-border);
        margin: 0.15rem 0 0;
    }

    .app-sidebar-label {
        color: var(--sidebar-muted);
        font-size: 0.76rem;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        font-weight: 600;
        padding: 0 0.5rem;
    }

    .app-sidebar-menu {
        list-style: none;
        margin: 0;
        padding: 0;
        display: grid;
        gap: 0.35rem;
    }

    .app-sidebar-link {
        display: flex;
        align-items: center;
        gap: 0.85rem;
        color: var(--sidebar-text);
        text-decoration: none;
        border-radius: 1rem;
        padding: 0.85rem 0.95rem;
        transition: transform 0.18s ease, background-color 0.18s ease, box-shadow 0.18s ease;
    }

    .app-sidebar-link:hover,
    .app-sidebar-link:focus {
        color: #fff;
        background: rgba(59, 130, 246, 0.14);
        transform: translateX(2px);
    }

    .app-sidebar-link.active {
        color: #fff;
        background: linear-gradient(135deg, var(--sidebar-accent) 0%, var(--sidebar-accent-2) 100%);
        box-shadow: 0 16px 30px rgba(29, 78, 216, 0.28);
    }

    .app-sidebar-link i {
        width: 1.2rem;
        text-align: center;
        font-size: 1rem;
    }

    .app-sidebar-status-grid {
        display: grid;
        gap: 0.6rem;
    }

    .app-sidebar-status {
        border: 1px solid rgba(255, 255, 255, 0.08);
        background: rgba(255, 255, 255, 0.05);
        border-radius: 1rem;
        padding: 0.8rem 0.9rem;
    }

    .app-sidebar-status-top {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem;
        margin-bottom: 0.25rem;
    }

    .app-sidebar-status-title {
        display: flex;
        align-items: center;
        gap: 0.55rem;
        color: #fff;
        font-weight: 500;
    }

    .app-sidebar-status-text {
        color: var(--sidebar-muted);
        font-size: 0.82rem;
    }

    .status-dot {
        width: 10px;
        height: 10px;
        border-radius: 50%;
        display: inline-block;
        flex-shrink: 0;
    }

    .status-dot.online {
        background: #22c55e;
        box-shadow: 0 0 0 4px rgba(34, 197, 94, 0.16);
    }

    .status-dot.offline {
        background: #ef4444;
        box-shadow: 0 0 0 4px rgba(239, 68, 68, 0.14);
    }

    .app-sidebar-user {
        margin-top: auto;
        border: 1px solid rgba(255, 255, 255, 0.08);
        background: rgba(255, 255, 255, 0.04);
        border-radius: 1.15rem;
        padding: 0.95rem;
    }

    .app-sidebar-user-name {
        color: #fff;
        font-weight: 600;
        margin-bottom: 0.1rem;
    }

    .app-sidebar-user-role {
        color: var(--sidebar-muted);
        font-size: 0.88rem;
        margin-bottom: 0.35rem;
    }

    .app-sidebar-user-version {
        color: #cbd5e1;
        font-size: 0.78rem;
        margin-bottom: 0.9rem;
    }

    .app-sidebar-menu-shell {
        display: contents;
    }

    @media (max-width: 991.98px) {
        .app-sidebar {
            width: 100%;
            min-height: auto;
            position: sticky;
        }

        .app-sidebar-inner {
            min-height: auto;
        }

        .app-sidebar-toggle {
            display: inline-flex;
        }

        .app-sidebar-menu-shell {
            display: none;
        }

        .app-sidebar.is-open .app-sidebar-menu-shell {
            display: block;
        }
    }
</style>
<aside class="app-sidebar flex-shrink-0" id="appSidebar">
    <div class="app-sidebar-inner">
        <div class="app-sidebar-brand">
            <div>
                <a href="index.php" class="app-sidebar-title">Smart Pharmacy</a>
                <div class="app-sidebar-subtitle">Executive Dashboard / INVC</div>
                <div class="app-sidebar-version">
                    <i class="bi bi-git"></i>
                    <span>v<?= e($appMeta['current_version']) ?></span>
                </div>
            </div>
            <button type="button" class="app-sidebar-toggle" id="sidebarToggle" aria-expanded="false" aria-controls="sidebarMenuShell">
                <i class="bi bi-list"></i>
            </button>
        </div>

        <div class="app-sidebar-divider"></div>

        <div class="app-sidebar-menu-shell" id="sidebarMenuShell">
            <div class="app-sidebar-label">Sections</div>
            <ul class="app-sidebar-menu">
                <?php foreach ($menuItems as $item): ?>
                    <li>
                        <a href="<?= e($item['href']) ?>" class="app-sidebar-link <?= $currentPage === $item['href'] ? 'active' : '' ?>">
                            <i class="bi <?= e($item['icon']) ?>"></i>
                            <span><?= e($item['label']) ?></span>
                        </a>
                    </li>
                <?php endforeach; ?>
                <?php if (isAdminUser()): ?>
                    <li>
                        <a href="setpassword_admin.php" class="app-sidebar-link <?= $currentPage === 'setpassword_admin.php' ? 'active' : '' ?>">
                            <i class="bi bi-key"></i>
                            <span>Set Password Admin</span>
                        </a>
                    </li>
                <?php endif; ?>
            </ul>

            <div class="app-sidebar-divider"></div>

            <div class="app-sidebar-label">System Status</div>
            <div class="app-sidebar-status-grid">
                <?php foreach ($connectionStatuses as $status): ?>
                    <div class="app-sidebar-status">
                        <div class="app-sidebar-status-top">
                            <div class="app-sidebar-status-title">
                                <span class="status-dot <?= $status['ok'] ? 'online' : 'offline' ?>"></span>
                                <span><?= e($status['label']) ?></span>
                            </div>
                            <span class="badge <?= $status['ok'] ? 'text-bg-success' : 'text-bg-danger' ?>">
                                <?= $status['ok'] ? 'Online' : 'Offline' ?>
                            </span>
                        </div>
                        <div class="app-sidebar-status-text"><?= e($status['message']) ?></div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="app-sidebar-user">
                <div class="app-sidebar-user-name"><?= e($_SESSION['full_name'] ?? '') ?></div>
                <div class="app-sidebar-user-role"><?= e($_SESSION['role'] ?? '') ?></div>
                <div class="app-sidebar-user-version">Version <?= e($appMeta['current_version']) ?> • Build <?= e($appMeta['build']) ?></div>
                <a href="logout.php" class="btn btn-danger w-100">
                    <i class="bi bi-box-arrow-right me-1"></i> Logout
                </a>
            </div>
        </div>
    </div>
</aside>
<script>
    (function() {
        const sidebar = document.getElementById('appSidebar');
        const toggle = document.getElementById('sidebarToggle');
        if (!sidebar || !toggle) {
            return;
        }

        toggle.addEventListener('click', function() {
            const isOpen = sidebar.classList.toggle('is-open');
            toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        });
    })();
</script>
