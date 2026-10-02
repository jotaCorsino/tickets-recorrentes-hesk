# 03 — Roadmap

## Etapas

| Código | Etapa | Objetivo | Status | Critério de saída |
|---|---|---|---|---|
| KB-001 | Fundação documental | Consolidar ambiente, regras e arquitetura inicial | CONCLUÍDO | Documentação inicial publicada |
| ARC-001 | Arquitetura HESK | Confirmar fluxo nativo e estratégia de integração | AGUARDANDO_HOMOLOGACAO | Fluxo e restrições documentados |
| POC-001 | Ticket único | Criar um ticket real de teste via CLI | PENDENTE | Ticket correto no HESK, sem notificação indevida |
| CFG-001 | Persistência | Modelar recorrências e execuções | PENDENTE | Estrutura persistente versionada |
| SCH-001 | Scheduler | Detectar recorrências vencidas | PENDENTE | Execução por Cron reprodutível |
| SAFE-001 | Idempotência | Evitar duplicações | PENDENTE | Retry não duplica lote |
| BATCH-001 | Lotes | Gerar múltiplos tickets independentes | PENDENTE | N tickets rastreados individualmente |
| UI-001 | Painel | Editar recorrências sem alterar código | PENDENTE | CRUD funcional e simples |
| DEP-001 | cPanel | Implantar no ambiente real | PENDENTE | Deploy e Cron documentados |
| OPS-001 | Operação | Criar manual técnico | PENDENTE | Instalação, uso, logs, falhas e recuperação documentados |

## Próxima tarefa

### POC-001 — Ticket único

Objetivo: validar a integração mínima com HESK 3.7.12.

Cenário alvo:

- categoria: WORKSTATION;
- subcategoria: WINDOWS;
- problema/requisição: REQ_Manutenção preventiva;
- empresa: empresa de teste;
- solicitante: conta interna;
- responsável: técnico definido;
- status: Novo;
- prioridade: Baixa;
- notificar solicitante: não;
- sem anexos.

Não implementar nesta tarefa:

- Cron;
- múltiplos tickets;
- painel;
- recorrência;
- banco próprio;
- edição por navegador.

Critério de saída:

1. script executável por CLI;
2. ticket criado corretamente no HESK;
3. campos personalizados corretos;
4. owner correto;
5. nenhuma notificação indevida ao solicitante;
6. erro claro se parâmetros forem inválidos;
7. nenhum arquivo do core do HESK alterado.
