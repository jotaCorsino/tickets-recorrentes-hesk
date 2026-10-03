<?php

declare(strict_types=1);

$navigation = [
    ['key' => 'overview', 'label' => 'Visão geral', 'number' => '01'],
    ['key' => 'recurrences', 'label' => 'Recorrências', 'number' => '02'],
    ['key' => 'executions', 'label' => 'Execuções', 'number' => '03'],
    ['key' => 'system', 'label' => 'Sistema', 'number' => '04'],
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
                <div>
                    <strong>Tickets Recorrentes</strong>
                    <span>Administração</span>
                </div>
            </div>
            <div class="sidebar__section-label">ESPAÇO DE TRABALHO</div>
            <nav class="sidebar__nav" aria-label="Navegação principal">
                <?php foreach ($navigation as $item): ?>
                    <a class="nav-link<?= $activeNav === $item['key'] ? ' nav-link--active' : '' ?>"
                       href="index.php?page=<?= $escape($item['key']) ?>"
                       <?= $activeNav === $item['key'] ? 'aria-current="page"' : '' ?>>
                        <span class="nav-link__number" aria-hidden="true"><?= $escape($item['number']) ?></span>
                        <span><?= $escape($item['label']) ?></span>
                        <span class="nav-link__arrow" aria-hidden="true">↗</span>
                    </a>
                <?php endforeach; ?>
            </nav>
            <div class="sidebar__footer">
                <span class="sidebar__footer-dot" aria-hidden="true"></span>
                <div><strong>UI-001A</strong><span>Prévia visual do painel</span></div>
            </div>
        </aside>

        <div class="workspace">
            <header class="topbar">
                <div class="topbar__left">
                    <button class="menu-toggle" type="button" data-menu-toggle aria-label="Abrir menu" aria-controls="sidebar-nav" aria-expanded="false">
                        <span></span><span></span><span></span>
                    </button>
                    <div class="topbar__identity">
                        <span>Tickets Recorrentes</span>
                        <strong>Administração</strong>
                    </div>
                </div>
                <span class="environment-pill"><span aria-hidden="true"></span> Ambiente de prévia</span>
            </header>

            <main class="content" id="content">
                <div class="page-heading">
                    <div>
                        <p class="eyebrow">ADMINISTRAÇÃO <span aria-hidden="true">/</span> <?= $escape($heading) ?></p>
                        <h1><?= $escape($heading) ?></h1>
                        <p class="page-heading__intro"><?= $escape($intro) ?></p>
                    </div>
                    <span class="preview-label">Prévia visual</span>
                </div>
                <div class="demo-notice" role="note">
                    <span class="demo-notice__icon" aria-hidden="true">i</span>
                    <span>Dados demonstrativos nesta etapa. Este painel ainda não consulta ou altera o banco de recorrências.</span>
                </div>
                <?php require $view; ?>
            </main>
            <footer class="workspace-footer">Tickets Recorrentes <span aria-hidden="true">·</span> Fundação visual UI-001A</footer>
        </div>
    </div>
</body>
</html>
