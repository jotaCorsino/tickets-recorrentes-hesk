<?php declare(strict_types=1); ?>
<section class="surface-card system-panel" aria-labelledby="system-title">
    <div class="surface-card__heading"><h2 id="system-title">Estado da prévia</h2></div>
    <dl>
        <div><dt>Versão do sistema</dt><dd>UI-001A · prévia</dd></div>
        <div><dt>PHP</dt><dd><?= $escape(PHP_VERSION) ?></dd></div>
        <div><dt>Banco da aplicação</dt><dd>Não conectado</dd></div>
        <div><dt>Status das migrations</dt><dd>Não consultado</dd></div>
    </dl>
</section>
