<?php

declare(strict_types=1);

$isExample = $example !== null;
$isView = $mode === 'view';
$customFields = [
    ['key' => 'custom7', 'label' => 'Tipo de atendimento', 'placeholder' => 'Selecione ou informe'],
    ['key' => 'custom9', 'label' => 'Subcategoria', 'placeholder' => 'Conforme a categoria'],
    ['key' => 'custom10', 'label' => 'Problema ou requisição', 'placeholder' => 'Conforme a categoria'],
    ['key' => 'custom15', 'label' => 'Cliente atendido', 'placeholder' => 'Empresa atendida'],
    ['key' => 'custom16', 'label' => 'Patrimônio', 'placeholder' => 'Opcional'],
];
?>
<a class="back-link" href="index.php?page=recurrences"><span aria-hidden="true">←</span> Recorrências</a>

<nav class="form-jump" aria-label="Seções do formulário">
    <a href="#identification-title">Modelo e agenda</a>
    <a href="#ticket-title">Ticket e conteúdo</a>
    <a href="#custom-title">Complementos</a>
</nav>

<form class="form-layout" action="index.php?page=recurrence-form" method="post" novalidate>
    <?php if ($isView): ?><fieldset class="form-readonly" disabled><legend class="visually-hidden">Dados demonstrativos da recorrência</legend><?php endif; ?>
    <section class="form-section" aria-labelledby="identification-title">
        <h2 id="identification-title">Identificação</h2>
        <div class="field-grid">
            <div class="field"><label for="recurrence-name">Nome da recorrência</label><input id="recurrence-name" name="name" type="text" maxlength="255" placeholder="Ex.: Preventiva trimestral" value="<?= $escape($example['name'] ?? '') ?>"></div>
            <div class="field field--switch"><span class="field-label">Situação</span><label class="switch-label" for="recurrence-active"><input id="recurrence-active" name="enabled" type="checkbox" <?= !$isExample || $example['status'] === 'Ativa' ? 'checked' : '' ?>><span class="switch-track" aria-hidden="true"></span><span>Ativa</span></label></div>
        </div>
    </section>

    <section class="form-section" aria-labelledby="schedule-title">
        <h2 id="schedule-title">Agendamento</h2>
        <div class="field-grid field-grid--three">
            <div class="field"><label for="recurrence-timezone">Fuso horário</label><input id="recurrence-timezone" name="timezone" type="text" list="timezone-suggestions" placeholder="America/Sao_Paulo" value="<?= $isExample ? 'America/Sao_Paulo' : '' ?>"><datalist id="timezone-suggestions"><option value="America/Sao_Paulo"></option><option value="UTC"></option><option value="America/Manaus"></option><option value="Europe/Lisbon"></option></datalist></div>
            <div class="field"><label for="recurrence-interval">Repetir a cada</label><input id="recurrence-interval" name="interval_value" type="number" min="1" step="1" placeholder="1" value="<?= $escape($example['interval_value'] ?? '') ?>"></div>
            <div class="field"><label for="recurrence-unit">Unidade</label><select id="recurrence-unit" name="interval_unit"><option value="day">Dia</option><option value="week">Semana</option><option value="month" <?= ($example['interval_unit'] ?? '') === 'month' ? 'selected' : '' ?>>Mês</option><option value="year" <?= ($example['interval_unit'] ?? '') === 'year' ? 'selected' : '' ?>>Ano</option></select></div>
            <div class="field field--wide"><label for="recurrence-next">Próxima execução</label><input id="recurrence-next" name="next_run_at" type="datetime-local" value="<?= $escape($example['next_local'] ?? '') ?>"></div>
        </div>
    </section>

    <section class="form-section" aria-labelledby="ticket-title">
        <h2 id="ticket-title">Tickets</h2>
        <div class="field-grid field-grid--three">
            <div class="field"><label for="ticket-quantity">Quantidade por execução</label><input id="ticket-quantity" name="quantity" type="number" min="1" step="1" placeholder="1" value="<?= $escape($example['quantity'] ?? '') ?>"></div>
            <div class="field"><label for="ticket-customer">Solicitante</label><select id="ticket-customer" name="customer_id"><option value="">Selecione</option><option value="21" <?= $isExample ? 'selected' : '' ?>>Automação Technolife</option></select></div>
            <div class="field"><label for="ticket-category">Categoria</label><select id="ticket-category" name="category_id"><option value="">Selecione</option><option value="5" <?= ($example['category_id'] ?? null) === 5 ? 'selected' : '' ?>>WORKSTATION</option><option value="3" <?= ($example['category_id'] ?? null) === 3 ? 'selected' : '' ?>>SERVER</option><option value="8" <?= ($example['category_id'] ?? null) === 8 ? 'selected' : '' ?>>PERIFERICOS</option></select></div>
            <div class="field"><label for="ticket-priority">Prioridade</label><input id="ticket-priority" name="priority_name" type="text" placeholder="Nome no HESK" value="<?= $isExample ? 'Baixa' : '' ?>"></div>
            <div class="field"><label for="ticket-status">Status</label><input id="ticket-status" name="status_id" type="number" min="0" placeholder="ID do status" value="<?= $isExample ? '0' : '' ?>"></div>
            <div class="field"><label for="ticket-owner">Responsável</label><select id="ticket-owner" name="owner_id"><option value="">Selecione</option><option value="3" <?= ($example['owner_id'] ?? null) === 3 ? 'selected' : '' ?>>Técnico exemplo A</option><option value="4" <?= ($example['owner_id'] ?? null) === 4 ? 'selected' : '' ?>>Técnico exemplo B</option></select></div>
            <div class="field"><label for="ticket-openedby">Autor interno</label><select id="ticket-openedby" name="openedby_id"><option value="">Selecione</option><option value="4" <?= $isExample ? 'selected' : '' ?>>Técnico exemplo B</option></select></div>
        </div>
    </section>

    <section class="form-section" aria-labelledby="content-title">
        <h2 id="content-title">Conteúdo</h2>
        <div class="field-grid">
            <div class="field field--wide"><label for="ticket-subject">Assunto</label><input id="ticket-subject" name="subject" type="text" maxlength="255" placeholder="Assunto do ticket" value="<?= $escape($example['subject'] ?? '') ?>"></div>
            <div class="field field--wide"><label for="ticket-message">Mensagem</label><textarea id="ticket-message" name="message" rows="4" placeholder="Descreva o atendimento recorrente..."><?= $escape($example['message'] ?? '') ?></textarea></div>
        </div>
    </section>

    <section class="form-section" aria-labelledby="custom-title">
        <h2 id="custom-title">Campos personalizados</h2>
        <div class="field-grid">
            <?php foreach ($customFields as $field): ?>
                <div class="field"><label for="field-<?= $escape($field['key']) ?>"><?= $escape($field['label']) ?></label><input id="field-<?= $escape($field['key']) ?>" name="custom_fields[<?= $escape($field['key']) ?>]" type="text" placeholder="<?= $escape($field['placeholder']) ?>"></div>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="form-section" aria-labelledby="notification-title">
        <h2 id="notification-title">Notificação</h2>
        <div class="disabled-option"><label for="notify-customer"><input id="notify-customer" name="notify_customer" type="checkbox" value="1" disabled> Notificar solicitante</label><p>Notificações ao solicitante ainda não estão disponíveis.</p></div>
    </section>
    <?php if ($isView): ?></fieldset><?php endif; ?>
    <div class="form-actions"><span>Esta prévia não salva alterações.</span><a class="button button--secondary" href="index.php?page=recurrences">Cancelar</a><button class="button button--primary" type="submit" disabled>Salvar</button></div>
</form>
