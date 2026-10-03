<?php declare(strict_types=1); ?>
<section class="page-toolbar" aria-label="Ações de recorrências">
    <div>
        <p class="page-toolbar__label">CONFIGURAÇÕES</p>
        <p>Visualize agendas e prepare novas recorrências.</p>
    </div>
    <a class="button button--primary" href="index.php?page=recurrence-form">+ Nova recorrência</a>
</section>

<section class="surface-card" aria-labelledby="recurrences-title">
    <div class="surface-card__heading">
        <div><p class="section-kicker">LISTAGEM</p><h2 id="recurrences-title">Todas as recorrências</h2></div>
        <span class="count-pill"><?= $escape(count($recurrences)) ?> exemplos</span>
    </div>
    <div class="table-scroll">
        <table class="responsive-table recurrence-table">
            <thead>
                <tr>
                    <th scope="col">Nome</th><th scope="col">Status</th><th scope="col">Frequência</th>
                    <th scope="col">Próxima execução</th><th scope="col">Quantidade</th><th scope="col">Cliente</th>
                    <th scope="col">Responsável</th><th scope="col">Ações</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($recurrences as $recurrence): ?>
                <tr>
                    <td data-label="Nome"><strong class="table-primary"><?= $escape($recurrence['name']) ?></strong><span class="table-secondary">Exemplo visual</span></td>
                    <td data-label="Status"><span class="badge <?= $recurrence['status'] === 'Ativa' ? 'badge--active' : 'badge--inactive' ?>"><?= $escape($recurrence['status']) ?></span></td>
                    <td data-label="Frequência"><?= $escape($recurrence['frequency']) ?></td>
                    <td data-label="Próxima execução"><?= $escape($recurrence['next']) ?></td>
                    <td data-label="Quantidade"><?= $escape($recurrence['quantity']) ?></td>
                    <td data-label="Cliente"><?= $escape($recurrence['customer']) ?></td>
                    <td data-label="Responsável"><?= $escape($recurrence['owner']) ?></td>
                    <td data-label="Ações">
                        <div class="row-actions">
                            <a href="index.php?page=recurrence-form&amp;mode=view">Visualizar</a>
                            <a href="index.php?page=recurrence-form&amp;mode=edit">Editar</a>
                            <button type="button" disabled title="Disponível após integração da UI com o repositório">
                                <?= $recurrence['status'] === 'Ativa' ? 'Desativar' : 'Ativar' ?>
                            </button>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <p class="surface-card__footnote">As ações de ativar e desativar serão habilitadas quando esta página estiver conectada ao repositório.</p>
</section>
