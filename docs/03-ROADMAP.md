# 03 — Roadmap

## Etapas

| Código | Etapa | Objetivo | Status | Critério de saída |
|---|---|---|---|---|
| KB-001 | Fundação documental | Consolidar ambiente, regras e arquitetura inicial | CONCLUÍDO | Documentação inicial publicada |
| ARC-001 | Arquitetura HESK | Confirmar fluxo nativo e estratégia de integração | CONCLUÍDO | Fluxo e restrições documentados |
| BASE-001 | Baseline manual | Criar ticket de referência para comparação | CONCLUÍDO | Ticket 42 criado e validado visualmente |
| POC-001 | Ticket único | Criar um ticket real de teste via CLI | CONCLUÍDO | Ticket correto no HESK, sem notificação indevida |
| CFG-001 | Persistência | Modelar recorrências e execuções | CONCLUÍDO | Estrutura persistente versionada |
| SCH-001 | Scheduler | Detectar recorrências vencidas | AGUARDANDO_HOMOLOGACAO | Execução por Cron reprodutível |
| SAFE-001 | Idempotência | Evitar duplicações | PENDENTE | Retry não duplica lote |
| BATCH-001 | Lotes | Gerar múltiplos tickets independentes | PENDENTE | N tickets rastreados individualmente |
| UI-001 | Painel | Editar recorrências sem alterar código | PENDENTE | CRUD funcional e simples |
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

## SCH-001 aguardando homologação

A implementação seleciona recorrências ativas e vencidas, calcula a próxima ocorrência na timezone configurada, cria uma `recurrence_execution` pendente e atualiza `next_run_at` na mesma transação.

Foram definidos:

- modos `check` sem escrita e `run` com persistência;
- limite padrão de 100 recorrências, configurável entre 1 e 1000;
- uma competência por recorrência em cada run;
- cálculo civil para day, week, month e year, com ajuste de fim de mês;
- rollback integral entre execution e avanço da recorrência;
- inspeção somente leitura das executions pela CLI administrativa.

A homologação deverá usar exclusivamente `storage/scheduler-homolog.sqlite`. O `app.sqlite` real e sua recorrência ID `1` não devem ser alterados. SAFE-001 e BATCH-001 permanecem pendentes.
