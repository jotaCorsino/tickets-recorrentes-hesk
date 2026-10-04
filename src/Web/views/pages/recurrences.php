<?php declare(strict_types=1); ?>
<section class="surface-card model-list" aria-labelledby="models-title">
    <div class="surface-card__heading">
        <h2 id="models-title">Modelos cadastrados</h2>
        <span class="count-pill"><?= $escape(count($recurrences)) ?> exemplos</span>
    </div>

    <?php foreach ($recurrences as $recurrence): ?>
        <article class="model-row">
            <div class="model-row__main">
                <div class="model-row__title">
                    <h3><?= $escape($recurrence['name']) ?></h3>
                    <span class="badge <?= $recurrence['status'] === 'Ativa' ? 'badge--active' : 'badge--inactive' ?>"><?= $escape($recurrence['status']) ?></span>
                </div>
                <p><?= $escape($recurrence['frequency']) ?> <span aria-hidden="true">·</span> <?= $escape($recurrence['quantity']) ?> tickets por execução</p>
            </div>
            <div class="model-row__next">
                <span>Próxima execução</span>
                <strong><?= $escape($recurrence['next']) ?></strong>
            </div>
            <div class="model-row__actions">
                <a class="button button--outline" href="index.php?page=recurrence-form&amp;mode=edit&amp;id=<?= $escape($recurrence['id']) ?>" aria-label="Editar <?= $escape($recurrence['name']) ?>">Editar</a>
                <a class="text-link" href="index.php?page=recurrence-form&amp;mode=view&amp;id=<?= $escape($recurrence['id']) ?>" aria-label="Visualizar <?= $escape($recurrence['name']) ?>">Visualizar</a>
                <button class="text-button" type="button" disabled title="Disponível após integração da UI com o repositório">
                    <?= $recurrence['status'] === 'Ativa' ? 'Pausar' : 'Ativar' ?>
                </button>
            </div>
        </article>
    <?php endforeach; ?>
</section>
