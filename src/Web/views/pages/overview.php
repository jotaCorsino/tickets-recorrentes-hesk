<?php declare(strict_types=1); ?>
<section class="welcome-panel" aria-labelledby="welcome-title">
    <div>
        <span class="section-kicker">OPERAÇÃO EM UM SÓ LUGAR</span>
        <h2 id="welcome-title">Uma visão clara das próximas rotinas.</h2>
        <p>Planeje chamados recorrentes e acompanhe cada execução com contexto e rastreabilidade.</p>
        <div class="welcome-panel__actions">
            <a class="button button--light" href="index.php?page=recurrences">Ver recorrências <span aria-hidden="true">↗</span></a>
            <a class="button button--ghost-light" href="index.php?page=recurrence-form">Nova recorrência</a>
        </div>
    </div>
    <div class="welcome-panel__visual" aria-hidden="true">
        <span class="visual-line visual-line--one"></span>
        <span class="visual-line visual-line--two"></span>
        <span class="visual-line visual-line--three"></span>
        <span class="visual-node visual-node--one"></span>
        <span class="visual-node visual-node--two"></span>
        <span class="visual-node visual-node--three"></span>
    </div>
</section>

<section class="section-block" aria-labelledby="summary-title">
    <div class="section-heading">
        <div><p class="section-kicker">RESUMO</p><h2 id="summary-title">Indicadores</h2></div>
        <span class="section-caption">Valores demonstrativos</span>
    </div>
    <div class="metric-grid">
        <article class="metric-card metric-card--green">
            <div class="metric-card__top"><span>Recorrências ativas</span><span class="metric-card__symbol" aria-hidden="true">↗</span></div>
            <strong><?= $escape($summary['active']) ?></strong>
            <p>Agendas prontas para próximas competências</p>
        </article>
        <article class="metric-card metric-card--slate">
            <div class="metric-card__top"><span>Recorrências inativas</span><span class="metric-card__symbol" aria-hidden="true">—</span></div>
            <strong><?= $escape($summary['inactive']) ?></strong>
            <p>Configurações preservadas sem novos agendamentos</p>
        </article>
        <article class="metric-card metric-card--blue">
            <div class="metric-card__top"><span>Execuções pendentes</span><span class="metric-card__symbol" aria-hidden="true">◷</span></div>
            <strong><?= $escape($summary['pending']) ?></strong>
            <p>Competências aguardando processamento</p>
        </article>
        <article class="metric-card metric-card--red">
            <div class="metric-card__top"><span>Execuções com falha</span><span class="metric-card__symbol" aria-hidden="true">!</span></div>
            <strong><?= $escape($summary['failed']) ?></strong>
            <p>Itens que exigirão revisão antes do retry</p>
        </article>
    </div>
</section>

<section class="surface-card section-block" aria-labelledby="next-title">
    <div class="surface-card__heading">
        <div><p class="section-kicker">AGENDA</p><h2 id="next-title">Próximas recorrências</h2></div>
        <a class="text-link" href="index.php?page=recurrences">Ver todas <span aria-hidden="true">↗</span></a>
    </div>
    <div class="table-scroll">
        <table class="responsive-table overview-table">
            <thead><tr><th scope="col">Nome</th><th scope="col">Próxima execução</th><th scope="col">Frequência</th><th scope="col">Quantidade</th><th scope="col">Status</th></tr></thead>
            <tbody>
            <?php foreach ($recurrences as $recurrence): ?>
                <tr>
                    <td data-label="Nome"><strong class="table-primary"><?= $escape($recurrence['name']) ?></strong></td>
                    <td data-label="Próxima execução"><?= $escape($recurrence['next']) ?></td>
                    <td data-label="Frequência"><?= $escape($recurrence['frequency']) ?></td>
                    <td data-label="Quantidade"><?= $escape($recurrence['quantity']) ?></td>
                    <td data-label="Status"><span class="badge <?= $recurrence['status'] === 'Ativa' ? 'badge--active' : 'badge--inactive' ?>"><?= $escape($recurrence['status']) ?></span></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
