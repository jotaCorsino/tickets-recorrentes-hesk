<?php declare(strict_types=1); ?>
<section class="system-grid" aria-label="Informações do sistema">
    <article class="system-card"><span class="system-card__icon" aria-hidden="true">01</span><p>Versão do sistema</p><strong>UI-001A · prévia</strong><small>Fundação visual, sem integração de dados.</small></article>
    <article class="system-card"><span class="system-card__icon" aria-hidden="true">02</span><p>PHP</p><strong><?= $escape(PHP_VERSION) ?></strong><small>Versão em uso pelo servidor desta página.</small></article>
    <article class="system-card"><span class="system-card__icon" aria-hidden="true">03</span><p>Banco da aplicação</p><strong>Não conectado</strong><small>Nenhuma consulta ao SQLite nesta etapa.</small></article>
    <article class="system-card"><span class="system-card__icon" aria-hidden="true">04</span><p>Status das migrations</p><strong>Não consultado</strong><small>Será exibido quando o painel tiver acesso controlado ao banco.</small></article>
</section>
<section class="surface-card system-note" aria-labelledby="system-note-title">
    <p class="section-kicker">ESCOPO DA PRÉVIA</p>
    <h2 id="system-note-title">Preparado para evoluir com segurança.</h2>
    <p>Esta tela mostra apenas informações sem credenciais. Configurações de banco, caminhos privados e parâmetros do HESK não são exibidos.</p>
</section>
