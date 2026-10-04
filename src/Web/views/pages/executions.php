<?php

declare(strict_types=1);

$statusLabels = [
    'pending' => 'Pendente',
    'running' => 'Em andamento',
    'succeeded' => 'Concluída',
    'failed' => 'Falhou',
    'partial' => 'Parcial',
];
?>
<section class="surface-card execution-list" aria-labelledby="executions-title">
    <div class="surface-card__heading">
        <h2 id="executions-title">Histórico</h2>
        <span class="count-pill"><?= $escape(count($executions)) ?> exemplos</span>
    </div>
    <ul>
        <?php foreach ($executions as $execution): ?>
            <li class="execution-row">
                <div class="execution-row__main">
                    <strong><?= $escape($execution['recurrence']) ?></strong>
                    <span>#<?= $escape($execution['id']) ?> · Competência <?= $escape($execution['scheduled']) ?></span>
                </div>
                <span class="badge badge--<?= $escape($execution['status']) ?>"><?= $escape($statusLabels[$execution['status']] ?? $execution['status']) ?></span>
                <div class="execution-row__progress">
                    <strong><?= $escape($execution['created']) ?> / <?= $escape($execution['expected']) ?></strong>
                    <span>tickets criados</span>
                </div>
                <details class="execution-details">
                    <summary>Ver detalhes</summary>
                    <dl>
                        <div><dt>Tentativas</dt><dd><?= $escape($execution['attempts']) ?></dd></div>
                        <div><dt>Início</dt><dd><?= $escape($execution['started']) ?></dd></div>
                        <div><dt>Fim</dt><dd><?= $escape($execution['finished']) ?></dd></div>
                    </dl>
                    <p>Itens do lote serão exibidos após a integração.</p>
                </details>
            </li>
        <?php endforeach; ?>
    </ul>
</section>
