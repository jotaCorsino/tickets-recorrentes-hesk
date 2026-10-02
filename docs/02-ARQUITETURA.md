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

## Componentes previstos

```text
bin/
  run.php

src/
  HeskBootstrap.php
  HeskTicketCreator.php
  RecurrenceService.php
  Scheduler.php
  ExecutionRepository.php
  RecurrenceRepository.php

config/
storage/
logs/
```

A estrutura poderá ser ajustada durante a POC.

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

A proteção deve sobreviver a:

- execução duplicada do Cron;
- retry manual;
- interrupção parcial;
- reinício do processo.

## Lote

Uma recorrência com quantidade 10 gera 10 tickets independentes e 10 vínculos de rastreabilidade.

No futuro, cada item do lote poderá receber patrimônio próprio.

## Compatibilidade com atualizações do HESK

A integração deve ficar isolada em `HeskBootstrap` e `HeskTicketCreator`.

Quando o HESK for atualizado, a camada de integração poderá ser validada sem reescrever o scheduler ou o modelo de recorrência.
