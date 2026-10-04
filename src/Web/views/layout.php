<?php

declare(strict_types=1);

$navigation = [
    ['key' => 'recurrences', 'label' => 'Recorrências'],
    ['key' => 'executions', 'label' => 'Execuções'],
    ['key' => 'system', 'label' => 'Sistema'],
];
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title><?= $escape($heading) ?> · Tickets Recorrentes</title>
    <link rel="stylesheet" href="assets/css/admin.css">
    <script src="assets/js/admin.js" defer></script>
</head>
<body>
    <a class="skip-link" href="#content">Ir para o conteúdo</a>
    <div class="app-shell">
        <button class="menu-backdrop" type="button" data-menu-backdrop hidden aria-label="Fechar menu"></button>
        <aside class="sidebar" id="sidebar-nav" data-sidebar>
            <div class="sidebar__brand">
                <span class="brand-mark" aria-hidden="true"><span></span><span></span><span></span><span></span></span>
                <div><strong>Tickets Recorrentes</strong><span>Administração</span></div>
            </div>
            <nav class="sidebar__nav" aria-label="Navegação principal">
                <?php foreach ($navigation as $item): ?>
                    <a class="nav-link<?= $activeNav === $item['key'] ? ' nav-link--active' : '' ?>"
                       href="index.php?page=<?= $escape($item['key']) ?>"
                       <?= $activeNav === $item['key'] ? 'aria-current="page"' : '' ?>>
                        <?= $escape($item['label']) ?>
                    </a>
                <?php endforeach; ?>
            </nav>
            <div class="sidebar__footer">UI-001A <span>Prévia visual</span></div>
        </aside>

        <div class="workspace">
            <header class="topbar">
                <div class="topbar__left">
                    <button class="menu-toggle" type="button" data-menu-toggle aria-label="Abrir menu" aria-controls="sidebar-nav" aria-expanded="false">
                        <span></span><span></span><span></span>
                    </button>
                    <span class="topbar__title">Tickets Recorrentes</span>
                </div>
                <span class="preview-badge">Dados de exemplo</span>
            </header>

            <main class="content" id="content">
                <div class="page-heading">
                    <div>
                        <h1><?= $escape($heading) ?></h1>
                        <p><?= $escape($intro) ?></p>
                    </div>
                    <?php if ($page === 'recurrences'): ?>
                        <a class="button button--primary page-heading__action" href="index.php?page=recurrence-form">Nova recorrência</a>
                    <?php endif; ?>
                </div>
                <?php require $view; ?>
            </main>
        </div>
    </div>
</body>
</html>
