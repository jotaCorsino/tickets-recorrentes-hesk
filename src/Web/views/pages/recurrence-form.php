<?php

declare(strict_types=1);

$isExample = $mode !== 'new';
$isView = $mode === 'view';
$customFields = [
    ['key' => 'custom7', 'label' => 'Tipo de atendimento', 'placeholder' => 'Ex.: Presencial'],
    ['key' => 'custom9', 'label' => 'Subcategoria', 'placeholder' => 'Conforme a categoria'],
    ['key' => 'custom10', 'label' => 'Problema ou requisição', 'placeholder' => 'Conforme a categoria'],
    ['key' => 'custom15', 'label' => 'Cliente atendido', 'placeholder' => 'Empresa atendida'],
    ['key' => 'custom16', 'label' => 'Patrimônio', 'placeholder' => 'Opcional'],
];
?>
<div class="form-intro">
    <a class="back-link" href="index.php?page=recurrences"><span aria-hidden="true">←</span> Voltar para recorrências</a>
    <span class="preview-label"><?= $isView ? 'Somente visualização' : 'Sem gravação nesta etapa' ?></span>
</div>

<nav class="form-jump" aria-label="Seções do formulário">
    <a href="#identification-title">Identificação</a>
    <a href="#schedule-title">Agendamento</a>
    <a href="#ticket-title">Tickets</a>
    <a href="#content-title">Conteúdo</a>
    <a href="#custom-title">Campos personalizados</a>
    <a href="#notification-title">Notificação</a>
</nav>

<form class="form-layout" action="index.php?page=recurrence-form" method="post" novalidate>
    <?php if ($isView): ?><fieldset class="form-readonly" disabled><legend class="visually-hidden">Dados demonstrativos da recorrência</legend><?php endif; ?>
    <section class="form-section" aria-labelledby="identification-title">
        <div class="form-section__header"><span class="form-section__number">01</span><div><h2 id="identification-title">Identificação</h2><p>Dê um nome claro à rotina e defina seu estado.</p></div></div>
        <div class="field-grid">
            <div class="field field--wide"><label for="recurrence-name">Nome da recorrência</label><input id="recurrence-name" name="name" type="text" maxlength="255" placeholder="Ex.: Preventiva trimestral" value="<?= $isExample ? 'Preventiva de estações' : '' ?>"><small>Use um nome que facilite a busca e a auditoria.</small></div>
            <div class="field field--switch"><span class="field-label">Situação</span><label class="switch-label" for="recurrence-active"><input id="recurrence-active" name="enabled" type="checkbox" checked><span class="switch-track" aria-hidden="true"></span><span>Recorrência ativa</span></label><small>Agendas inativas não geram novas competências.</small></div>
        </div>
    </section>

    <section class="form-section" aria-labelledby="schedule-title">
        <div class="form-section__header"><span class="form-section__number">02</span><div><h2 id="schedule-title">Agendamento</h2><p>Determine o intervalo e o próximo momento de execução.</p></div></div>
        <div class="field-grid field-grid--three">
            <div class="field"><label for="recurrence-timezone">Timezone</label><input id="recurrence-timezone" name="timezone" type="text" list="timezone-suggestions" placeholder="America/Sao_Paulo" value="<?= $isExample ? 'America/Sao_Paulo' : '' ?>"><datalist id="timezone-suggestions"><option value="America/Sao_Paulo"></option><option value="UTC"></option><option value="America/Manaus"></option><option value="Europe/Lisbon"></option></datalist><small>Informe uma timezone IANA.</small></div>
            <div class="field"><label for="recurrence-interval">Repetir a cada</label><input id="recurrence-interval" name="interval_value" type="number" min="1" step="1" placeholder="1" value="<?= $isExample ? '3' : '' ?>"></div>
            <div class="field"><label for="recurrence-unit">Unidade</label><select id="recurrence-unit" name="interval_unit"><option value="day">Dia</option><option value="week">Semana</option><option value="month" <?= $isExample ? 'selected' : '' ?>>Mês</option><option value="year">Ano</option></select></div>
            <div class="field field--wide"><label for="recurrence-next">Próxima execução</label><input id="recurrence-next" name="next_run_at" type="datetime-local" value="<?= $isExample ? '2026-10-15T09:00' : '' ?>"><small>Horário local na timezone informada. A persistência fará a conversão para UTC.</small></div>
        </div>
    </section>

    <section class="form-section" aria-labelledby="ticket-title">
        <div class="form-section__header"><span class="form-section__number">03</span><div><h2 id="ticket-title">Tickets</h2><p>Defina o volume e os identificadores necessários no HESK.</p></div></div>
        <div class="field-grid field-grid--three">
            <div class="field"><label for="ticket-quantity">Quantidade por execução</label><input id="ticket-quantity" name="quantity" type="number" min="1" step="1" value="<?= $isExample ? '10' : '' ?>" placeholder="1"></div>
            <div class="field"><label for="ticket-customer">Solicitante</label><input id="ticket-customer" name="customer_id" type="number" min="1" placeholder="ID do cliente interno" value="<?= $isExample ? '21' : '' ?>"></div>
            <div class="field"><label for="ticket-category">Categoria</label><input id="ticket-category" name="category_id" type="number" min="1" placeholder="ID da categoria" value="<?= $isExample ? '5' : '' ?>"></div>
            <div class="field"><label for="ticket-priority">Prioridade</label><input id="ticket-priority" name="priority_name" type="text" placeholder="Nome no HESK" value="<?= $isExample ? 'Baixa' : '' ?>"></div>
            <div class="field"><label for="ticket-status">Status</label><input id="ticket-status" name="status_id" type="number" min="0" placeholder="ID do status" value="<?= $isExample ? '0' : '' ?>"></div>
            <div class="field"><label for="ticket-owner">Responsável</label><input id="ticket-owner" name="owner_id" type="number" min="1" placeholder="ID do técnico" value="<?= $isExample ? '4' : '' ?>"></div>
            <div class="field"><label for="ticket-openedby">Autor interno</label><input id="ticket-openedby" name="openedby_id" type="number" min="1" placeholder="ID do usuário" value="<?= $isExample ? '4' : '' ?>"></div>
        </div>
        <p class="form-section__hint">Nesta prévia, os IDs são apenas campos visuais. A integração futura deverá validar as opções diretamente no HESK.</p>
    </section>

    <section class="form-section" aria-labelledby="content-title">
        <div class="form-section__header"><span class="form-section__number">04</span><div><h2 id="content-title">Conteúdo</h2><p>Prepare o assunto e a mensagem que cada ticket receberá.</p></div></div>
        <div class="field-grid"><div class="field field--wide"><label for="ticket-subject">Assunto</label><input id="ticket-subject" name="subject" type="text" maxlength="255" placeholder="Assunto do ticket" value="<?= $isExample ? 'Manutenção preventiva' : '' ?>"></div><div class="field field--wide"><label for="ticket-message">Mensagem</label><textarea id="ticket-message" name="message" rows="5" placeholder="Descreva o atendimento recorrente..."><?= $isExample ? 'Realizar a manutenção preventiva conforme o planejamento.' : '' ?></textarea></div></div>
    </section>

    <section class="form-section" aria-labelledby="custom-title">
        <div class="form-section__header"><span class="form-section__number">05</span><div><h2 id="custom-title">Campos personalizados</h2><p>Área reutilizável para campos habilitados conforme a categoria.</p></div></div>
        <div class="field-grid">
            <?php foreach ($customFields as $field): ?>
                <div class="field"><label for="field-<?= $escape($field['key']) ?>"><?= $escape($field['label']) ?> <span class="field-code"><?= $escape($field['key']) ?></span></label><input id="field-<?= $escape($field['key']) ?>" name="custom_fields[<?= $escape($field['key']) ?>]" type="text" placeholder="<?= $escape($field['placeholder']) ?>"></div>
            <?php endforeach; ?>
        </div>
        <p class="form-section__hint">A definição de campos é apresentada por configuração. Valores e obrigatoriedade serão validados na integração.</p>
    </section>

    <section class="form-section" aria-labelledby="notification-title">
        <div class="form-section__header"><span class="form-section__number">06</span><div><h2 id="notification-title">Notificação</h2><p>Política de comunicação do ticket automático.</p></div></div>
        <div class="disabled-option"><label for="notify-customer"><input id="notify-customer" name="notify_customer" type="checkbox" value="1" disabled> Notificar solicitante</label><p>Indisponível nesta etapa. O worker homologado ainda não aceita <code>notify_customer=true</code>; o valor permanece desativado.</p></div>
    </section>
    <?php if ($isView): ?></fieldset><?php endif; ?>
    <div class="form-actions"><p>Prévia de interface: nenhuma informação será salva.</p><a class="button button--secondary" href="index.php?page=recurrences">Cancelar</a><button class="button button--primary" type="submit" disabled>Salvar (em breve)</button></div>
</form>
