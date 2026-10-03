# 11 — Fundação visual do painel administrativo

**Subtarefa:** UI-001A — fundação visual. **Status de UI-001:** `EM_ANDAMENTO`.

## Objetivo e limite

UI-001A entrega uma prévia navegável para avaliação visual antes de conectar o painel aos repositórios. A interface não consulta SQLite ou HESK, não cria tickets e não grava recorrências. Todos os números e registros exibidos estão identificados como dados demonstrativos.

O painel usa PHP 8.2, HTML5, CSS3 e JavaScript vanilla. Não há dependência de Node.js, framework web ou compilação de assets em produção.

## Estrutura

```text
public/
  index.php                 front controller, barreira de acesso e cabeçalhos
  assets/css/admin.css      layout, componentes e responsividade
  assets/js/admin.js        menu mobile e navegação acessível
src/Web/
  AdminUi.php               whitelist de rotas e composição de templates
  DemoData.php              dados exclusivos da prévia
  views/layout.php          estrutura comum, sidebar e cabeçalho
  views/pages/              visão geral, recorrências, formulário, execuções e sistema
tests/web.php               bloqueio, rotas, HTML, campos e conteúdo seguro
```

O HTML fica nos templates. `AdminUi` aceita somente rotas de uma lista fixa e não usa parâmetros de URL como caminhos de arquivo. Textos dinâmicos são escapados antes de aparecer no HTML.

## Rotas

As rotas usam `index.php?page=...`, sem depender de regras de rewrite do Apache ou LiteSpeed. Isso também permite abrir o painel em um subdiretório do cPanel.

| URL | Conteúdo |
|---|---|
| `index.php` ou `?page=overview` | Visão geral, indicadores e próximas recorrências |
| `?page=recurrences` | Listagem visual e ações de prévia |
| `?page=recurrence-form` | Formulário visual para nova recorrência |
| `?page=recurrence-form&mode=view` | Visualização demonstrativa, campos desabilitados |
| `?page=recurrence-form&mode=edit` | Edição demonstrativa, sem salvamento |
| `?page=executions` | Histórico demonstrativo e os cinco estados |
| `?page=system` | Versão da prévia, PHP e estados não conectados |

Rota ou modo desconhecido retorna 404. Método diferente de GET retorna 405 enquanto não houver gravação. O botão **Salvar** permanece desabilitado; ativar/desativar na listagem também permanece desabilitado. Os detalhes de execution abrem uma explicação visual, sem consulta aos itens BATCH.

## Design e acessibilidade

No desktop, a navegação usa sidebar à esquerda e área principal à direita. Abaixo de 760 px, a sidebar vira um menu recolhível com botão, overlay, `aria-expanded`, fechamento por Escape e foco inicial no primeiro link. Tabelas largas têm rolagem horizontal em telas menores.

O painel usa HTML semântico, labels ligados aos controles, link para pular ao conteúdo, foco visível, contraste e redução de animação conforme a preferência do dispositivo. A identidade visual é própria e neutra, sem copiar o HESK.

O formulário separa identificação, agendamento, tickets, conteúdo, campos personalizados e notificação. Os campos `custom7`, `custom9`, `custom10`, `custom15` e `custom16` são renderizados a partir de descritores de campo, sem condicionar o layout à categoria WORKSTATION. Seus valores e obrigatoriedade serão carregados e validados na integração futura.

## Barreira de desenvolvimento

Por padrão, a UI responde 404. Somente `ADMIN_UI_ENABLED=1` permite abrir o painel. Não há senha hardcoded, sessão web, conexão ao banco ou leitura de `hesk_settings.inc.php`. A resposta desabilita cache e adiciona cabeçalhos de segurança para tipo de conteúdo, frame e política de conteúdo.

Esta chave é uma barreira mínima de desenvolvimento, **não autenticação definitiva**. Não publique o painel com `ADMIN_UI_ENABLED=1` em endereço público até implementar autenticação e autorização administrativas na UI-001.

Para visualizar localmente, a partir da raiz do projeto:

```bash
ADMIN_UI_ENABLED=1 php -S 127.0.0.1:8765 -t public
```

Abra `http://127.0.0.1:8765/index.php` no navegador. Sem a variável, a mesma URL retorna 404. O servidor de desenvolvimento é apenas para avaliação local.

## Limites e próximos passos

- Nenhum número no dashboard ou linha nas tabelas representa dados reais.
- Os campos de IDs do HESK são visuais; a próxima etapa deverá oferecer seleção e validação pelos dados do HESK.
- O formulário não salva. Seu POST é recusado com 405.
- `notify_customer` fica desmarcado e desabilitado, pois o worker homologado rejeita `true`.
- A página Sistema não revela caminho do banco, credenciais ou configurações MariaDB/HESK.
- Não há migration nova, alteração do scheduler, SAFE, BATCH ou criação de tickets nesta subtarefa.

Antes da homologação final de UI-001, implementar autenticação e autorização, proteção CSRF para escritas, integração com os repositórios, estados vazios/erros reais e validação de dados do HESK. A UI-001B dependerá da avaliação visual desta prévia.

## Validação local

```bash
php tests/run.php
php tests/persistence.php
php tests/scheduler.php
php tests/safety.php
php tests/batch.php
php tests/web.php
```

`tests/web.php` verifica a barreira por ambiente, rotas, modos do formulário, estrutura das tags HTML, unicidade dos IDs, associação de labels, ausência de conteúdo sensível, recusa de POST e controles indisponíveis.
