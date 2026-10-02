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
Montagem do ticket
    ↓
Adaptador HESK
    ↓
hesk_newTicket(...)
    ↓
Registro da execução
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

A CFG-001 já fornece a garantia estrutural `UNIQUE (recurrence_id, scheduled_for)`. Ela impede duas linhas para a mesma ocorrência programada, mas ainda não implementa a política completa de reserva, retry e retomada. Essa política pertence à SAFE-001.

A proteção deve sobreviver a:

- execução duplicada do Cron;
- retry manual;
- interrupção parcial;
- reinício do processo.

## Datas e campos personalizados

- `timezone` preserva a zona IANA usada para interpretação futura da agenda;
- `next_run_at`, `scheduled_for` e timestamps operacionais são persistidos como ISO-8601 em UTC;
- `custom_fields` é validado como mapa de chaves `custom1` a `custom100` e serializado em JSON;
- a CLI administrativa da persistência não inicializa o HESK e não cria tickets.

## Lote

Uma recorrência com quantidade 10 gera 10 tickets independentes e 10 vínculos de rastreabilidade.

No futuro, cada item do lote poderá receber patrimônio próprio.

## Compatibilidade com atualizações do HESK

A integração deve ficar isolada em `HeskBootstrap` e `HeskTicketCreator`.

Quando o HESK for atualizado, a camada de integração poderá ser validada sem reescrever o scheduler ou o modelo de recorrência.
