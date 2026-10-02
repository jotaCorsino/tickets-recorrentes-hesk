# 03 — Roadmap

## Etapas

| Código | Etapa | Objetivo | Status | Critério de saída |
|---|---|---|---|---|
| KB-001 | Fundação documental | Consolidar ambiente, regras e arquitetura inicial | CONCLUÍDO | Documentação inicial publicada |
| ARC-001 | Arquitetura HESK | Confirmar fluxo nativo e estratégia de integração | CONCLUÍDO | Fluxo e restrições documentados |
| BASE-001 | Baseline manual | Criar ticket de referência para comparação | CONCLUÍDO | Ticket 42 criado e validado visualmente |
| POC-001 | Ticket único | Criar um ticket real de teste via CLI | CONCLUÍDO | Ticket correto no HESK, sem notificação indevida |
| CFG-001 | Persistência | Modelar recorrências e execuções | AGUARDANDO_HOMOLOGACAO | Estrutura persistente versionada |
| SCH-001 | Scheduler | Detectar recorrências vencidas | PENDENTE | Execução por Cron reprodutível |
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

## Etapa em homologação

### CFG-001 — Persistência

Foi implementado um banco SQLite próprio, com migrations determinísticas, repositórios, validações, CLI administrativa e testes automatizados. A estrutura registra recorrências e execuções sem acoplar a aplicação ao MariaDB do HESK.

A homologação deve confirmar no cPanel as extensões PHP, a criação do arquivo no caminho planejado, a aplicação idempotente das migrations e o ciclo `create/list/show/disable/enable`.

Nenhuma atividade de SCH-001 deve começar antes da homologação da CFG-001.
