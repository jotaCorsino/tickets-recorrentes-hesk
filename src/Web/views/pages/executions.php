<?php declare(strict_types=1); ?>
<section class="page-toolbar" aria-label="Resumo de execuções">
    <div><p class="page-toolbar__label">HISTÓRICO</p><p>Consulte a evolução dos lotes, inclusive tentativas parciais.</p></div>
    <span class="count-pill"><?= $escape(count($executions)) ?> exemplos</span>
</section>
<section class="surface-card" aria-labelledby="executions-title">
    <div class="surface-card__heading"><div><p class="section-kicker">PROCESSAMENTO</p><h2 id="executions-title">Histórico de execuções</h2></div><span class="section-caption">Prévia dos cinco estados</span></div>
    <div class="table-scroll">
        <table class="responsive-table execution-table">
            <thead><tr><th scope="col">ID</th><th scope="col">Recorrência</th><th scope="col">Competência</th><th scope="col">Status</th><th scope="col">Esperados</th><th scope="col">Criados</th><th scope="col">Tentativas</th><th scope="col">Início</th><th scope="col">Fim</th><th scope="col">Detalhes</th></tr></thead>
            <tbody>
            <?php foreach ($executions as $execution): ?>
                <tr>
                    <td data-label="ID"><strong class="table-id">#<?= $escape($execution['id']) ?></strong></td>
                    <td data-label="Recorrência"><strong class="table-primary"><?= $escape($execution['recurrence']) ?></strong></td>
                    <td data-label="Competência"><?= $escape($execution['scheduled']) ?></td>
                    <td data-label="Status"><span class="badge badge--<?= $escape($execution['status']) ?>"><?= $escape($execution['status']) ?></span></td>
                    <td data-label="Esperados"><?= $escape($execution['expected']) ?></td><td data-label="Criados"><?= $escape($execution['created']) ?></td><td data-label="Tentativas"><?= $escape($execution['attempts']) ?></td>
                    <td data-label="Início"><?= $escape($execution['started']) ?></td><td data-label="Fim"><?= $escape($execution['finished']) ?></td>
                    <td data-label="Detalhes"><details class="detail-disclosure"><summary>Ver detalhes</summary><p>A visualização de itens BATCH será conectada ao histórico na próxima etapa da UI.</p></details></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
