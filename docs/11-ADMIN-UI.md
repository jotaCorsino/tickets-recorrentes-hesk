# 11 — Fundação visual do painel administrativo

**Subtarefa:** UI-001A. **Status de UI-001:** `EM_ANDAMENTO`.

## Objetivo e processo visual

UI-001A é uma prévia para avaliação do usuário antes da integração com SQLite e HESK. Não consulta bancos, não cria tickets e não grava recorrências. Os registros e as opções exibidos são exemplos.

Tarefas de frontend/UI da UI-001 devem utilizar a skill de construção de sites do ChatGPT/Codex para criação e refinamento visual. A interface deve ser examinada em desktop e mobile e apresentada para avaliação do usuário antes de integrar funcionalidades definitivas. Esta regra se aplica a UI-001A, UI-001B, UI-001C, UI-001D, UI-001E e UI-001F sempre que houver alteração visual.

A revisão desta prévia utilizou `sites:sites` em modo local, sobre o checkout PHP, com inspeção iterativa no navegador. O feedback visual do usuário levou à remoção do dashboard duplicado: Recorrências passou a ser a tela inicial. A direção visual usa azul e azul escuro, fundo claro, menos textos auxiliares e ações de edição visíveis em cada modelo. A UI-001B depende da avaliação desta versão.

## Estrutura

```text
public/index.php              entrada web, gate e cabeçalhos
public/assets/css/admin.css   identidade visual e responsividade
public/assets/js/admin.js     menu mobile e foco por teclado
src/Web/AdminUi.php           rotas permitidas e composição de templates
src/Web/DemoData.php          exemplos exclusivos da prévia
src/Web/views/               layout e páginas PHP
tests/web.php                 testes da camada visual e da barreira
```

O painel usa PHP 8.2, HTML5, CSS3 e JavaScript vanilla. Não requer Node.js, framework web ou build em produção. O HTML fica nos templates. `AdminUi` aceita somente rotas permitidas e escapa dados antes de renderizá-los.

## Navegação

| URL | Conteúdo |
|---|---|
| `index.php` ou `?page=recurrences` | Modelos demonstrativos, situação, próxima execução e edição direta |
| `?page=overview` | Compatibilidade: apresenta a mesma lista de recorrências |
| `?page=recurrence-form` | Formulário visual para novo modelo |
| `?page=recurrence-form&mode=view&id=1` | Visualização demonstrativa com controles desabilitados |
| `?page=recurrence-form&mode=edit&id=1` | Edição visual do exemplo escolhido, sem salvamento |
| `?page=executions` | Histórico compacto; tentativas e horários em “Ver detalhes” |
| `?page=system` | Versão, PHP e estados não conectados |

A sidebar contém apenas Recorrências, Execuções e Sistema. Os modelos de exemplo possuem IDs locais para que **Editar** e **Visualizar** mostrem o registro escolhido. Esses IDs não vêm do banco. O formulário mantém as seções e campos previstos, com seletores demonstrativos para solicitante, categoria e responsáveis. `notify_customer` permanece desmarcado e desabilitado.

No desktop, a lista usa linhas espaçosas. No mobile, cada linha vira um cartão com a ação **Editar** visível; o menu lateral é recolhível, fecha com Escape e posiciona o foco no primeiro link. Links para três grupos do formulário facilitam percorrer a ficha longa. Foco visível, labels associados e HTML semântico apoiam a navegação por teclado.

## Barreira de desenvolvimento

Por padrão, a UI responde 404. Somente `ADMIN_UI_ENABLED=1` libera a prévia. Não há senha hardcoded, sessão, conexão com banco nem leitura de `hesk_settings.inc.php`. Respostas desabilitam cache e incluem cabeçalhos de proteção. Esta chave é uma barreira de desenvolvimento, não autenticação definitiva: não publique o painel habilitado em endereço público nesta etapa.

Para visualizar localmente, na raiz do projeto:

```bash
ADMIN_UI_ENABLED=1 php -S 127.0.0.1:8765 -t public
```

Abra `http://127.0.0.1:8765/index.php`. Sem a variável, a URL retorna 404.

## Limites e validação

- **Editar** e **Visualizar** navegam entre exemplos; nenhuma alteração é persistida. POST retorna 405 e o botão Salvar fica desabilitado.
- Ativar/pausar e notificar o solicitante continuam desabilitados.
- Seletores e valores do HESK são demonstrativos; opções reais e validações pertencem à integração futura.
- A página Sistema não revela credenciais, caminhos privados nem configurações MariaDB/HESK.
- Nenhuma migration, scheduler, SAFE, BATCH, criação de tickets ou worker foi alterado nesta revisão.

```bash
php tests/run.php
php tests/persistence.php
php tests/scheduler.php
php tests/safety.php
php tests/batch.php
php tests/web.php
```

Antes da homologação final de UI-001, ainda serão necessários autenticação e autorização, proteção CSRF para escritas, integração aos repositórios, validação de dados HESK e estados reais de erro/vazio. UI-001 continua `EM_ANDAMENTO` até essas etapas e a homologação.
