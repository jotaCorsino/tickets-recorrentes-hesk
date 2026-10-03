# 03 — Roadmap

## Etapas

| Código | Etapa | Objetivo | Status | Critério de saída |
|---|---|---|---|---|
| KB-001 | Fundação documental | Consolidar ambiente, regras e arquitetura inicial | CONCLUÍDO | Documentação inicial publicada |
| ARC-001 | Arquitetura HESK | Confirmar fluxo nativo e estratégia de integração | CONCLUÍDO | Fluxo e restrições documentados |
| BASE-001 | Baseline manual | Criar ticket de referência para comparação | CONCLUÍDO | Ticket 42 criado e validado visualmente |
| POC-001 | Ticket único | Criar um ticket real de teste via CLI | CONCLUÍDO | Ticket correto no HESK, sem notificação indevida |
| CFG-001 | Persistência | Modelar recorrências e execuções | CONCLUÍDO | Estrutura persistente versionada |
| SCH-001 | Scheduler | Detectar recorrências vencidas | CONCLUÍDO | Execução por Cron reprodutível |
| SAFE-001 | Idempotência de execution | Proteger claim, lease e retry da mesma execution | CONCLUÍDO | Uma execution não é processada simultaneamente por dois workers; stale lease e retry preservam a mesma identidade |
| BATCH-001 | Lotes | Gerar múltiplos tickets independentes | CONCLUÍDO | Uma execution materializa N itens persistentes; cada item possui identidade HESK rastreável, retries não recriam itens succeeded e tickets existentes são reconciliados pelo tracking ID |
| UI-001 | Painel | Editar recorrências sem alterar código | EM_ANDAMENTO | CRUD funcional e simples |
| DEP-001 | cPanel | Implantar no ambiente real | PENDENTE | Deploy e Cron documentados |
| OPS-001 | Operação | Criar manual técnico | PENDENTE | Instalação, uso, logs, falhas e recuperação documentados |

## Baseline validada

Ticket manual de referência:

- número: `42`;
- tracking ID: `L32-DAG-R778`;
- solicitante: `Automação Technolife` (`customer_id=21`);
- categoria: `WORKSTATION`;
- prioridade: `Baixa`;
- status: `Novo`;
- responsável: `João Paulo Corsino` (`user_id=4`);
- atendimento: `Presencial`;
- subcategoria: `WINDOWS`;
- problema/requisição: `REQ_Manutenção preventiva`;
- cliente: `TECHNOLIFE`;
- patrimônio: vazio;
- vencimento: nenhum.

Ver `docs/05-POC-MANUAL.md`.

## POC-001 homologada

Em 02/10/2026, o modo `--check` foi executado no servidor real com `CHECK OK`, sem criação de ticket. Em seguida, uma única execução de `--execute` criou o ticket `43`, tracking ID `299-RY2-QZ4L`.

A conferência visual confirmou solicitante, categoria, prioridade, status, responsável, campos personalizados, assunto, mensagem e ausência de vencimento conforme a baseline manual. A implementação não chama a rotina de notificação ao solicitante.

## CFG-001 homologada

Em 02/10/2026, a persistência foi homologada no servidor real com o banco:

```text
/home/tech2612/hesk-recorrencias/storage/app.sqlite
```

Resultados confirmados:

- migration `001_initial_schema` aplicada com sucesso;
- segunda execução de `migrate` sem migrations pendentes;
- `foreign_keys=1` e `journal_mode=wal`;
- recorrência de exemplo criada com ID `1`;
- `next_run_at` normalizado para `2027-01-15T12:00:00Z`;
- comandos `list`, `show --id=1`, `disable` e `enable` aprovados;
- recorrência ID `1` deixada desabilitada ao final;
- diretório `storage` com permissão `750` e `app.sqlite` com permissão `660`;
- nenhum ticket criado no HESK.

## SCH-001 homologada

Em 02/10/2026, o scheduler foi homologado no servidor real usando exclusivamente:

```text
/home/tech2612/hesk-recorrencias/storage/scheduler-homolog.sqlite
```

Resultados confirmados:

- migration e criação da recorrência ID `1` executadas normalmente;
- `check` encontrou uma recorrência vencida e calculou `2026-09-30T12:00:00Z` sem persistir alterações;
- primeiro run criou a execution `1` para `2026-08-31T12:00:00Z` e avançou uma competência;
- segundo run criou a execution `2` para `2026-09-30T12:00:00Z` e avançou para `2026-10-30T12:00:00Z`;
- as duas executions ficaram `pending`, sem início, término ou erro;
- check final encontrou zero recorrências vencidas;
- recorrência deixada desabilitada e banco isolado, WAL e SHM removidos;
- `app.sqlite` real permaneceu intacto e nenhum ticket foi criado no HESK.

## SAFE-001 homologada

Em 02/10/2026, a segurança de execution foi homologada no servidor real usando exclusivamente:

```text
/home/tech2612/hesk-recorrencias/storage/safe-homolog.sqlite
```

As migrations `001_initial_schema` e `002_execution_leases` foram aplicadas com `foreign_keys=1` e `journal_mode=wal`; a segunda execução não encontrou migrations pendentes. O scheduler criou a execution `1` como `pending`, para `scheduled_for=2026-10-01T12:00:00Z`, e avançou a recorrência para `2027-10-01T12:00:00Z`.

O worker A obteve o primeiro claim e elevou `attempt_count` de `0` para `1`. Durante o lease ativo, o worker B foi recusado com `active_lease`. O heartbeat renovou a expiração sem alterar o início ou a identificação da tentativa, e `show` não expôs o token.

A primeira tentativa terminou em `failed` com a mensagem controlada. O retry explícito reutilizou a execution `1`, preservou identidade, competência, contagens e `last_attempt_at`, manteve `attempt_count=1` e limpou lease, erro, início e fim. O worker B realizou o segundo claim, elevou `attempt_count` para `2` e finalizou como `succeeded`; lease e erro ficaram nulos. O retry posterior foi bloqueado como `terminal_succeeded`.

A recorrência foi desabilitada e o banco isolado, WAL e SHM foram removidos. O `app.sqlite` real permaneceu intacto e nenhum ticket foi criado no HESK. O stale takeover por expiração não foi reproduzido manualmente no cPanel; ele permanece coberto por `tests/safety.php`, incluindo novo token, incremento de tentativa e invalidação do token antigo.

SAFE-001 está `CONCLUÍDO`. Sua posse por lease permanece a base usada pelo worker de lotes.

## BATCH-001 homologada

Em 02/10/2026, a geração em lote foi homologada no servidor real usando exclusivamente:

```text
/home/tech2612/hesk-recorrencias/storage/batch-homolog.sqlite
```

O primeiro `migrate` exibiu de forma inconsistente `Migrations aplicadas: nenhuma`. Sem atribuir causa a essa divergência, a inspeção direta de `schema_migrations` confirmou `001_initial_schema`, `002_execution_leases` e `003_execution_items`, todas com `applied_at=2026-10-02T18:41:05Z`; o schema continha as tabelas esperadas, com `foreign_keys=1` e `journal_mode=wal`. A segunda execução informou corretamente que não havia migrations pendentes.

A recorrência ID `1`, com `quantity=2` e `notify_customer=false`, originou a execution ID `1` para `2026-10-01T12:00:00Z` e avançou `next_run_at` para `2027-10-01T12:00:00Z`. O check permaneceu somente leitura: não fez claim, não materializou itens e não criou tickets.

O run real criou os tickets HESK `44` (`XNP-1EU-SDY4`) e `45` (`S8J-V7T-N6QP`). Os dois itens terminaram `succeeded`, com uma tentativa cada; a execution terminou `succeeded`, com `expected_count=2`, `created_count=2`, `attempt_count=1`, erro e lease nulos. A conferência visual validou solicitante, categoria, prioridade, status, responsável, campos personalizados, assunto, mensagem, patrimônio vazio e ausência de vencimento. As telas não comprovaram ausência de envio de notificação; o fluxo homologado estava configurado com `notify_customer=false` e a BATCH-001 não introduz rotina de notificação.

A reexecução foi recusada como `terminal_succeeded` e preservou execution, itens, tracking IDs, IDs dos tickets e contadores, confirmando que o lote concluído não foi recriado. O crash exato entre `hesk_newTicket()` e o sucesso no SQLite não foi provocado manualmente; lookup, reconciliação, crash recovery e named lock permanecem cobertos pelos testes automatizados.

A recorrência foi desabilitada, o banco isolado e seus auxiliares foram removidos, e `storage` voltou a conter somente `app.sqlite`. O hash do banco real permaneceu `732d714ed1aacaa4ac7849bb324817f7f07dd8bcac22ab96126106eee9da9082` antes e depois. BATCH-001 está `CONCLUÍDO`; naquela homologação, UI-001 e DEP-001 ainda estavam `PENDENTE` e não haviam sido iniciadas.

## UI-001A — fundação visual em andamento

A primeira subtarefa da UI-001 disponibiliza uma prévia web navegável com visão geral, recorrências, formulário, execuções e sistema. A barreira `ADMIN_UI_ENABLED=1` permite avaliação local sem expor o painel por padrão. Os dados são demonstrativos e não há leitura ou gravação no SQLite/HESK. Ver `docs/11-ADMIN-UI.md`.

UI-001 permanece `EM_ANDAMENTO`. Integração real, autenticação administrativa e CRUD pertencem às próximas subtarefas; a homologação final ainda não ocorreu.
