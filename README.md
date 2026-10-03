# Tickets Recorrentes HESK

## Resumo

Será desenvolvido um **sistema externo em PHP, com interface HTML administrativa**, integrado ao HESK 3.7.12 para criar chamados recorrentes automaticamente.

Pelo painel será possível configurar empresa, categoria e campos personalizados, técnico responsável, prioridade, quantidade de tickets, frequência, assunto e mensagem. Essas configurações ficarão registradas no próprio sistema de recorrências.

O painel **não criará um Cron para cada automação**. Haverá **um único Cron Job no cPanel**, executado periodicamente, responsável por chamar o PHP do sistema. A cada execução, o sistema verificará quais recorrências chegaram à data programada e quais chamados precisam ser gerados.

Quando uma recorrência precisar ser executada, o PHP montará os dados dos chamados e utilizará o **próprio mecanismo e funções internas do HESK** para criá-los. Não será necessário abrir e preencher automaticamente o formulário do HESK nem inserir tickets diretamente no banco.

```text
Painel HTML
    ↓
salva as configurações das recorrências
    ↓
Cron único do cPanel executa periodicamente
    ↓
PHP identifica as recorrências que venceram
    ↓
PHP utiliza as funções internas do HESK
    ↓
tickets são criados normalmente no HESK
    ↓
execução é registrada para evitar duplicidade
```

Já foram identificados os campos, categorias, responsáveis e permissões necessários. Também foi criado o solicitante interno **Automação Technolife** e validado manualmente um ticket de manutenção preventiva que servirá como referência para a implementação.

**Base homologada:** a prova de conceito PHP CLI e a persistência própria em SQLite foram homologadas com sucesso no servidor real. A CFG-001 confirmou migrations idempotentes, foreign keys, WAL, normalização UTC e administração da recorrência sem criar tickets no HESK.

**Base homologada:** a SCH-001 também foi homologada com sucesso no servidor real. O dry run não persistiu alterações; dois runs processaram exatamente uma competência cada, criaram duas executions pendentes e avançaram `next_run_at` até `2026-10-30T12:00:00Z`. O banco isolado foi removido, o `app.sqlite` permaneceu intacto e nenhum ticket foi criado no HESK.

**Base homologada:** a SAFE-001 foi homologada com sucesso no servidor real em banco isolado. O fluxo confirmou claim exclusivo, bloqueio de segundo worker durante lease ativo, heartbeat, falha controlada, retry da mesma execution, nova tentativa e estado terminal `succeeded`. O `app.sqlite` permaneceu intacto e nenhum ticket foi criado no HESK. O stale takeover continua validado pelos testes automatizados, não por reprodução manual no cPanel.

**Base homologada:** a BATCH-001 foi homologada com sucesso no servidor real em 02/10/2026 usando banco isolado. A execution criou dois itens e os tickets HESK `44` e `45`, terminou com `created_count=2` e recusou nova execução como `terminal_succeeded`, sem recriar o lote. O banco isolado foi removido e o hash do `app.sqlite` real permaneceu idêntico. O crash exato entre HESK e SQLite não foi provocado manualmente; lookup, reconciliação e recuperação continuam cobertos pelos testes automatizados.

**Situação da etapa atual:** BATCH-001 está concluída. UI-001 e DEP-001 continuam pendentes e nenhuma próxima implementação foi iniciada.

**Em resumo:** o painel será responsável por administrar **o que, quando e quantos chamados devem ser criados**; um único Cron fará a verificação periódica; e o PHP realizará a criação dos tickets através do próprio HESK, com controle de recorrência e prevenção de duplicidades.

---

Base de conhecimento, planejamento e acompanhamento do sistema de criação automática de chamados recorrentes no HESK OSS da Technolife.

## Objetivo

Criar uma solução externa ao núcleo do HESK capaz de gerar chamados recorrentes de forma automática, configurável e segura, incluindo criação em lote, campos personalizados, empresa atendida, responsável, prioridade, periodicidade e prevenção contra duplicidade.

O sistema deverá utilizar o fluxo e as funções nativas do HESK sempre que possível, evitando alterações no core e evitando inserções SQL diretas na tabela de tickets.

## Ambiente validado

- HESK: 3.7.12
- PHP: 8.2.33
- Extensões PHP da aplicação: `PDO`, `pdo_sqlite` e `sqlite3`
- Banco: MariaDB 10.11.19
- Banco da aplicação: SQLite separado do banco do HESK
- URL: `https://suporte.technolife.net.br/`
- Instalação: `/home/tech2612/suporte.technolife.net.br/`
- Prefixo das tabelas HESK: `hesktx_`
- Execução agendada: cPanel Cron Jobs

## Fluxo técnico identificado

```text
Configuração da recorrência
        ↓
Scheduler / Cron
        ↓
Motor de recorrências
        ↓
Adaptador HESK
        ↓
Validações equivalentes ao fluxo administrativo
        ↓
hesk_newTicket(...)
        ↓
Ticket criado no HESK
```

Referências do fluxo atual do HESK 3.7.12:

```text
admin/new_ticket.php
        ↓
admin/admin_submit_ticket.php
        ↓
inc/posting_functions.inc.php
        ↓
hesk_newTicket(...)
```

## Regras principais

- Não alterar o core do HESK para implementar recorrência.
- Não criar tickets com `INSERT` direto em `hesktx_tickets`.
- Usar uma conta interna como solicitante dos chamados automáticos.
- A empresa atendida será representada pelo campo personalizado `custom15 - CLIENTE`.
- O cliente externo não deverá receber notificação na criação automática inicial.
- O ticket deverá poder nascer atribuído a um técnico definido na recorrência.
- Frequência e quantidade deverão ser configuração, não código.
- Toda execução deverá ser idempotente para impedir tickets duplicados.
- O motor deverá permitir evolução futura para individualização por patrimônio/equipamento.

## Mapeamento inicial — WORKSTATION

| Elemento | HESK |
|---|---|
| Categoria | `5 - WORKSTATION` |
| Tipo de atendimento | `custom7` |
| WORKSTATION > Subcategoria | `custom9` |
| WORKSTATION > Problema/Requisição | `custom10` |
| CLIENTE | `custom15` |
| PATRIMONIO | `custom16` |

Valores já identificados:

- `custom9`: WINDOWS, OFFICE, ANTIVIRUS
- `custom10`: inclui `REQ_Manutenção preventiva`

## Acompanhamento

| Código | Etapa | Objetivo | Status | Critério de saída |
|---|---|---|---|---|
| KB-001 | Fundação documental | Criar base de conhecimento e regras do projeto | CONCLUÍDO | README, AGENTS e documentação inicial publicados |
| ARC-001 | Arquitetura HESK | Mapear fluxo nativo de criação de ticket e pontos de integração | CONCLUÍDO | Fluxo `new_ticket → admin_submit_ticket → hesk_newTicket` documentado |
| BASE-001 | Baseline manual | Criar o ticket de referência que a automação deverá reproduzir | CONCLUÍDO | Ticket 42 criado e validado visualmente |
| POC-001 | Prova de conceito | Criar 1 ticket WORKSTATION de teste pelo mecanismo correto do HESK | CONCLUÍDO | Ticket criado via CLI com campos, responsável e notificações corretos |
| CFG-001 | Modelo de recorrência | Definir estrutura configurável de empresa, frequência, quantidade e ticket | CONCLUÍDO | Configuração persistente validada |
| SCH-001 | Scheduler | Implementar execução por Cron e cálculo de recorrências vencidas | CONCLUÍDO | Execução automática controlada pelo cPanel |
| BATCH-001 | Geração em lote | Criar N tickets independentes em uma execução | CONCLUÍDO | Uma execution materializa N itens persistentes; cada item possui identidade HESK rastreável, retries não recriam itens succeeded e tickets existentes são reconciliados pelo tracking ID |
| SAFE-001 | Idempotência | Proteger posse e transições de cada execution | CONCLUÍDO | Uma execution não é processada simultaneamente por dois workers; stale lease e retry preservam a mesma identidade |
| UI-001 | Administração | Criar interface simples para editar recorrências | PENDENTE | Frequência, volume e parâmetros alteráveis sem editar PHP |
| DEP-001 | Implantação | Preparar instalação segura no cPanel | PENDENTE | Deploy reproduzível e Cron configurado |
| OPS-001 | Operação | Criar manual técnico de instalação, uso e manutenção | PENDENTE | Documentação operacional concluída |

### Status utilizados

`PENDENTE` → `EM_ANDAMENTO` → `AGUARDANDO_HOMOLOGACAO` → `CONCLUÍDO`

Use `BLOQUEADO` somente quando existir impedimento real.

## Fluxo de trabalho com Codex

Cada tarefa deverá ser executada isoladamente.

1. Ler `README.md`, `AGENTS.md` e a documentação relacionada à tarefa.
2. Sincronizar o repositório local com `origin/main`.
3. Criar uma branch específica para a tarefa.
4. Inspecionar antes de modificar.
5. Implementar somente o escopo solicitado.
6. Executar validações e testes aplicáveis.
7. Atualizar documentação e status.
8. Commitar e publicar a branch.
9. Abrir ou atualizar o Pull Request.
10. Informar branch, commit, PR, arquivos alterados e validações.
11. Parar em `AGUARDANDO_HOMOLOGACAO`.
12. Só iniciar a próxima tarefa após homologação.

## Documentação

- [Levantamento do ambiente HESK](docs/01-LEVANTAMENTO-HESK.md)
- [Arquitetura proposta](docs/02-ARQUITETURA.md)
- [Roadmap](docs/03-ROADMAP.md)
- [Decisões técnicas](docs/04-DECISOES-TECNICAS.md)
- [Baseline manual da POC](docs/05-POC-MANUAL.md)
- [Implementação e homologação da POC CLI](docs/06-POC-CLI.md)
- [Persistência e modelo de recorrência](docs/07-PERSISTENCIA.md)
- [Scheduler de recorrências](docs/08-SCHEDULER.md)
- [Segurança e idempotência de execution](docs/09-EXECUTION-SAFETY.md)
- [Processamento de lotes e reconciliação HESK](docs/10-BATCH-PROCESSING.md)

## Princípio do projeto

O HESK continua sendo o sistema de tickets. Este projeto será a camada de automação e recorrência ao redor dele.
