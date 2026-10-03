# 02 — Arquitetura proposta

## Objetivo

Adicionar recorrência ao HESK sem transformar a automação em uma modificação do core.

## Visão geral

```text
Configuração
    ↓
Scheduler
    ↓
Seleção das recorrências vencidas
    ↓
Proteção contra duplicidade
    ↓
Materialização de N itens persistentes
    ↓
Tracking ID persistido e reconciliação
    ↓
Adaptador HESK
    ↓
hesk_newTicket(...)
    ↓
Item succeeded e contagem sincronizada
```

## Fluxo nativo estudado

```text
admin/new_ticket.php
    ↓ POST
admin/admin_submit_ticket.php
    ↓
valida cliente
valida categoria
valida custom fields
gera trackid
define openedby
define owner
aplica autoassign
trata mensagem
define assignedby
    ↓
hesk_newTicket($tmpvar)
    ↓
ticket + relacionamento com customer
    ↓
notificações
```

## Decisão de integração

O projeto deverá reutilizar o bootstrap e as funções internas necessárias do HESK, mas manter sua própria camada de serviço.

A implementação não deve simplesmente chamar `admin_submit_ticket.php` via HTTP nem depender de uma sessão web humana.

O primeiro experimento deve reproduzir, de maneira controlada, apenas a preparação necessária do objeto de ticket e então usar a função nativa `hesk_newTicket()`.

## Bootstrap CLI validado para a POC

A integração CLI prepara, antes de carregar `inc/common.inc.php`, o contexto HTTPS exigido pela instalação atual:

```php
$_SERVER['HTTPS'] = 'on';
$_SERVER['HTTP_X_FORWARDED_PROTO'] = 'https';
$_SERVER['SERVER_NAME'] = 'suporte.technolife.net.br';
$_SERVER['REQUEST_URI'] = '/';
```

Também define `NO_HTTP_HEADER`, mecanismo nativo do HESK para execução sem resposta HTTP. Isso impede redirects e headers web no CLI sem modificar `common.inc.php`.

O bootstrap da POC:

1. exige `PHP_SAPI=cli`;
2. valida o caminho e a versão `3.7.12`;
3. carrega configurações e funções comuns;
4. carrega as funções de banco e conecta pelo mecanismo do HESK;
5. carrega as estruturas nativas de clientes, campos, prioridades e status;
6. carrega `posting_functions.inc.php`;
7. confirma que `hesk_newTicket()` está disponível.

Não há login ou sessão web, chamada HTTP ao formulário administrativo, alteração no core ou `INSERT` próprio na tabela de tickets.

## Componentes implementados

Na POC-001 foram implementados:

```text
bin/
  poc-create-ticket.php

src/
  CliOptions.php
  HeskBootstrap.php
  HeskTicketCreator.php
```

Na CFG-001 foram acrescentados, sem carregar o HESK:

```text
bin/
  recurrence.php

database/migrations/
  001_initial_schema.sql

src/
  Database.php
  MigrationRunner.php
  RecurrenceCliOptions.php
  RecurrenceValidator.php
  RecurrenceRepository.php
  RecurrenceExecutionRepository.php
```

O SQLite da aplicação é independente do MariaDB do HESK. SQL de domínio fica restrito às migrations e aos repositórios. Scheduler, cálculo de próximas datas, geração em lote e interface permanecem fora da CFG-001.

Na SCH-001 foram acrescentados:

```text
bin/
  scheduler.php

src/
  SchedulerCliOptions.php
  RecurrenceScheduleCalculator.php
  Scheduler.php
```

`RecurrenceRepository` passou a selecionar recorrências vencidas e avançar `next_run_at` de forma controlada. `RecurrenceExecutionRepository` passou a localizar uma execution por recorrência e competência. O script CLI apenas compõe esses componentes; não contém SQL, não carrega `HeskBootstrap` e não chama `hesk_newTicket()`.

## Fluxo do scheduler

```text
instante UTC injetado
    ↓
recorrências enabled e vencidas, ordenadas por next_run_at e id
    ↓
cálculo da próxima data na timezone civil da recorrência
    ↓
transação SQLite por recorrência
    ├─ cria recurrence_execution pending
    └─ avança next_run_at por comparação com o valor esperado
    ↓
commit ou rollback integral
```

O modo `check` encerra antes da transação e apenas relata o que seria feito. Cada chamada processa no máximo uma competência por recorrência, mesmo quando há vários meses de atraso.

Na SAFE-001 foram acrescentados, sem carregar o HESK:

```text
bin/
  execution.php

database/migrations/
  002_execution_leases.sql

src/
  ExecutionCliOptions.php
  ExecutionLeaseService.php
  ImmediateTransaction.php
```

`RecurrenceExecutionRepository` passou a encapsular seleção de candidato, claim, heartbeat, finalização, retry e listagem. A regra de transição permanece no serviço e a CLI apenas compõe os componentes. A migration 001 homologada não foi alterada.

Na BATCH-001 foram acrescentados:

```text
bin/
  worker.php

database/migrations/
  003_execution_items.sql

src/
  BatchProcessor.php
  ExecutionItemRepository.php
  TicketGateway.php
  HeskTicketGateway.php
  WorkerCliOptions.php
```

Cada `recurrence_execution` materializa exatamente `expected_count` itens. A abstração `TicketGateway` permite testar o motor sem carregar o HESK; sua implementação real gera e consulta tracking IDs pelo HESK, usa `hesk_newTicket()` como único caminho de criação e serializa a consulta/criação de cada tracking ID com um named lock do MariaDB. O comando `execution.php items` fornece inspeção somente leitura.

## Modelo conceitual da recorrência

Cada recorrência deverá conter, no mínimo:

- nome;
- ativa/inativa;
- intervalo;
- unidade do intervalo;
- próxima execução;
- quantidade por execução;
- solicitante interno;
- categoria;
- prioridade;
- status;
- responsável;
- assunto;
- mensagem;
- custom fields;
- política de notificação.

Exemplo:

```text
Nome: Preventiva Workstations - Empresa X
Ativo: sim
Repetir a cada: 3 meses
Quantidade: 10
Categoria: WORKSTATION
custom7: Presencial
custom9: WINDOWS
custom10: REQ_Manutenção preventiva
custom15: Empresa X
custom16: vazio
Responsável: técnico configurado
Assunto: Manutenção preventiva
Mensagem: Realizar manutenção preventiva da estação.
Notificar solicitante: não
```

## Idempotência

Cada execução deverá possuir uma chave lógica única, por exemplo:

```text
recurrence_id + scheduled_for
```

Antes de gerar qualquer lote, o motor verifica se aquela execução já foi processada.

A CFG-001 fornece a garantia estrutural `UNIQUE (recurrence_id, scheduled_for)`, impedindo duas linhas para a mesma ocorrência programada.

A SCH-001 respeita essa restrição e informa uma execution preexistente como ignorada, sem avançar silenciosamente a recorrência. Ela termina seu trabalho ao criar a execution `pending`.

A SAFE-001 atua na etapa seguinte:

```text
Scheduler
    ↓
execution pending
    ↓ claim atômico com BEGIN IMMEDIATE
execution running + lease temporário
    ↓
heartbeat ou stale takeover
    ↓
materialização idempotente de expected_count itens
    ↓
tracking ID persistido antes da criação
    ↓
lookup HESK e criação somente quando ausente
    ↓
succeeded | failed | partial
```

O claim usa token criptograficamente aleatório como prova de posse. Somente o token do lease ativo pode renovar ou finalizar. `failed` e `partial` precisam de retry explícito, que reaproveita a mesma linha; `succeeded` é terminal. Uma linha `running` antiga sem metadados de lease é tratada como inconsistente e não é tomada automaticamente.

A proteção deve sobreviver a:

- execução duplicada do Cron;
- retry manual;
- interrupção parcial;
- reinício do processo.

A BATCH-001 complementa essa proteção com uma identidade SQLite por ticket, tracking ID estável e reconciliação antes de qualquer nova criação. Itens `succeeded` não são recriados, e itens `creating` ou `failed` reutilizam o mesmo tracking ID no retry.

SQLite e MariaDB não compartilham transação. Para a corrida conhecida no HESK, o gateway real usa `GET_LOCK('tickets-recorrentes:<trackid>', 10)`, repete o lookup dentro do lock, cria somente se o ticket continuar ausente e libera com `RELEASE_LOCK` em `finally`. Se o processo cair depois da criação no HESK e antes do sucesso no SQLite, o próximo worker localiza o ticket pelo tracking ID e conclui o item. A garantia é limitada a esse protocolo: depende de todos os criadores concorrentes desse fluxo respeitarem o mesmo lock e da disponibilidade do lookup e do MariaDB.

## Datas e campos personalizados

- `timezone` preserva a zona IANA usada para interpretação futura da agenda;
- `next_run_at`, `scheduled_for` e timestamps operacionais são persistidos como ISO-8601 em UTC;
- `custom_fields` é validado como mapa de chaves `custom1` a `custom100` e serializado em JSON;
- a CLI administrativa da persistência não inicializa o HESK e não cria tickets.

## Lote

Uma recorrência com quantidade 10 produz uma execution cujo `expected_count=10`. Esse snapshot é o tamanho imutável do lote, mesmo que `recurrence.quantity` mude depois. A materialização cria uma única linha para cada `item_index` de 1 a 10, protegida por `UNIQUE (execution_id, item_index)`.

Cada item percorre `pending → creating → succeeded` ou `failed`. O tracking ID é persistido antes da chamada externa; `created_count` é sempre recalculado como a quantidade de itens `succeeded`. O estado final é `succeeded` quando N/N itens terminam, `failed` quando 0/N terminam e houve falha, ou `partial` quando apenas parte do lote termina. `failed` e `partial` continuam usando o retry explícito da SAFE-001.

No futuro, cada item do lote poderá receber patrimônio próprio.

## Compatibilidade com atualizações do HESK

A integração fica isolada em `HeskBootstrap`, `HeskTicketCreator` e `HeskTicketGateway`.

Quando o HESK for atualizado, a camada de integração poderá ser validada sem reescrever o scheduler ou o modelo de recorrência.
