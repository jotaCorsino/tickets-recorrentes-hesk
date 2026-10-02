# 04 — Decisões técnicas

## ADR-001 — Recorrência fora do core do HESK

**Decisão:** manter o motor em projeto separado.

**Motivo:** preservar atualizações do HESK e reduzir risco operacional.

## ADR-002 — Cron como relógio, não como regra de negócio

**Decisão:** usar um único Cron periódico para chamar o scheduler.

**Motivo:** frequência, quantidade e datas pertencem à configuração das recorrências e não à configuração do cPanel.

## ADR-003 — Não usar e-mail como mecanismo principal

**Decisão:** não usar IMAP/piping para gerar preventivas.

**Motivo:** os tickets possuem categoria, custom fields, responsável e demais atributos estruturados que seriam mais frágeis via e-mail.

## ADR-004 — Não inserir diretamente em hesktx_tickets

**Decisão:** reutilizar funções do HESK.

**Motivo:** a criação nativa também mantém customer mapping, tracking ID, owner, histórico, mensagens e outros comportamentos.

## ADR-005 — Conta interna como solicitante

**Decisão:** o `customer_id` será uma conta interna.

**Motivo:** a equipe cria o ticket, enquanto a empresa atendida é representada por `custom15`.

## ADR-006 — Configuração em vez de hardcode

**Decisão:** empresa, frequência, volume, técnico, categoria, assunto, mensagem e custom fields deverão ser editáveis sem alterar PHP.

## ADR-007 — Idempotência obrigatória

**Decisão:** toda execução recorrente terá controle persistente contra duplicação.

## ADR-008 — Modelos nativos do HESK não serão a fonte principal

**Decisão:** modelos do HESK poderão ser usados manualmente, mas não guardarão toda a definição da recorrência.

**Motivo:** eles não representam a ficha completa necessária ao sistema.

## ADR-009 — Codex como engenheiro

**Decisão:** implementação por tarefas isoladas, branches e PRs, sempre com homologação antes da próxima etapa.

## ADR-010 — IDs de equipe validados separadamente do solicitante

**Decisão:** usar os IDs de `hesktx_users` apenas para usuários da equipe, como responsável e autor interno.

**Mapeamento atual:** System=1, Nilton Teodoro=2, João Gabriel Silveira=3 e João Paulo Corsino=4.

**Motivo:** o solicitante do ticket usa `customer_id`, proveniente de `hesktx_customers`, e não um ID de `hesktx_users`.

## ADR-011 — Solicitante interno dedicado da POC

**Decisão:** a POC usa o customer `21 - Automação Technolife` como solicitante.

**Configuração validada:** `customer_id=21`, nome `Automação Technolife` e notificação ao solicitante desabilitada.

**Motivo:** melhora rastreabilidade e auditoria sem acoplar a automação ao registro genérico `9 - TECHNOLIFE`.

## ADR-012 — Técnicos 3 e 4 habilitados para WORKSTATION

**Decisão:** João Gabriel Silveira (`user_id=3`) e João Paulo Corsino (`user_id=4`) são responsáveis válidos para a categoria `5 - WORKSTATION`.

**Evidência operacional:** ambos pertencem ao grupo de permissão 2, e as categorias observadas para esse grupo foram `1, 2, 3, 4, 5, 7, 8, 9`.

**Motivo:** o HESK considera acesso por grupo de permissão ao validar o responsável do ticket.

## ADR-013 — Bootstrap CLI com contexto HTTPS

**Decisão:** preparar `HTTPS`, `HTTP_X_FORWARDED_PROTO`, `SERVER_NAME` e `REQUEST_URI` antes de carregar `common.inc.php`, além de definir `NO_HTTP_HEADER` para o contexto CLI.

**Motivo:** a instalação usa `force_ssl`; sem contexto adequado, o bootstrap tenta redirecionar e encerra o processo. A solução mantém o core do HESK intacto.

## ADR-014 — Prioridade resolvida pelo HESK

**Decisão:** localizar a prioridade `Baixa` pelo nome entre as prioridades carregadas do próprio HESK e validar seu ID antes de criar o ticket.

**Motivo:** o ID não deve ser presumido silenciosamente. Se o nome não for encontrado de forma inequívoca, `--check` lista os valores disponíveis e `--execute` é bloqueado.

## ADR-015 — Persistência sem notificação ao solicitante

**Decisão:** usar somente `hesk_newTicket()` para persistir a POC e não invocar `hesk_notifyCustomer()` nem criar mecanismo próprio de e-mail.

**Motivo:** no fluxo nativo, persistência e notificação são etapas separadas. A baseline exige que o solicitante não seja notificado.

## ADR-016 — SQLite separado do banco do HESK

**Decisão:** persistir recorrências e execuções em um arquivo SQLite próprio da aplicação.

**Motivo:** a carga prevista é pequena, há um único processo Cron planejado, o suporte a PDO SQLite foi confirmado e o ambiente cPanel permite implantação e backup simples sem credenciais adicionais. O isolamento evita tabelas ou alterações no MariaDB gerenciado pelo HESK. O acesso fica encapsulado em repositórios para permitir migração futura caso múltiplos workers ou concorrência elevada justifiquem outro banco.

## ADR-017 — Migrations versionadas e verificadas por checksum

**Decisão:** aplicar arquivos SQL ordenados e registrar versão, checksum e data em `schema_migrations`.

**Motivo:** o deploy precisa ser determinístico e seguro para reexecução. Uma migration já aplicada não pode ser alterada silenciosamente.

## ADR-018 — Datas persistidas em UTC com timezone IANA na recorrência

**Decisão:** normalizar instantes em ISO-8601 UTC e guardar separadamente a zona IANA configurada.

**Motivo:** UTC evita ambiguidade na comparação de vencimentos, enquanto a zona original será necessária para cálculos civis futuros, inclusive mudanças de horário legal.

## ADR-019 — Unicidade estrutural não conclui a idempotência operacional

**Decisão:** criar desde já a restrição única `(recurrence_id, scheduled_for)`, sem declarar concluída a SAFE-001.

**Motivo:** a restrição bloqueia duplicidade de registros, mas reserva atômica, retomada após falha parcial e política de retry dependem do scheduler e serão implementadas em tarefa própria.

## ADR-020 — Cálculo civil na timezone da recorrência

**Decisão:** converter o instante UTC para a timezone IANA configurada, adicionar o intervalo no calendário civil e converter o resultado novamente para UTC.

**Motivo:** o horário local deve permanecer estável mesmo quando o offset UTC mudar. Meses e anos usam clamp para o último dia válido, evitando saltos como 31 de janeiro para março.

## ADR-021 — Uma competência por recorrência em cada run

**Decisão:** cada execução do scheduler processa no máximo o `next_run_at` atual de cada recorrência selecionada.

**Motivo:** atrasos não podem gerar loops ilimitados ou rajadas inesperadas. Se a próxima data continuar vencida, outro run avançará mais uma competência.

## ADR-022 — Execution e avanço da recorrência na mesma transação

**Decisão:** inserir `recurrence_execution` e atualizar `next_run_at` atomicamente, com comparação do valor esperado.

**Motivo:** uma falha não pode deixar uma execution sem avanço nem uma recorrência avançada sem seu registro. Divergência ou erro provoca rollback integral.

## ADR-023 — Dry run e limite global de segurança

**Decisão:** oferecer `check` somente leitura e limitar cada chamada a 100 recorrências por padrão, aceitando de 1 a 1000.

**Motivo:** a operação precisa ser inspecionável antes da escrita e ter custo máximo previsível. O limite conta recorrências, não a quantidade futura de tickets.

## ADR-024 — Claim por lease temporário

**Decisão:** um worker assume uma `recurrence_execution` por tempo limitado, movendo-a de `pending` para `running` e registrando owner, expiração e tentativa.

**Motivo:** posse permanente impediria recuperação após interrupção; ausência de reserva permitiria processamento simultâneo.

## ADR-025 — Token aleatório como prova de posse

**Decisão:** cada claim gera `bin2hex(random_bytes(32))`; heartbeat e finish exigem o token do lease ativo. Listagens e consultas genéricas não o expõem.

**Motivo:** ID, PID e timestamp são previsíveis e não provam posse. Um novo claim invalida imediatamente o token anterior.

## ADR-026 — BEGIN IMMEDIATE serializa claims no SQLite

**Decisão:** selecionar e atualizar o candidato dentro de uma transação própria iniciada com `BEGIN IMMEDIATE`.

**Motivo:** duas conexões não podem observar e assumir simultaneamente a mesma linha entre um `SELECT` e um `UPDATE`. O helper é isolado para não alterar as transações já usadas pelo scheduler.

## ADR-027 — Lease expirado é recuperável

**Decisão:** uma execution `running` com token, owner e `lease_expires_at <= agora` pode ser assumida por outro worker, recebendo novo token e incremento de `attempt_count`.

**Motivo:** um worker interrompido não deve bloquear o trabalho indefinidamente. Uma linha `running` sem lease continua sendo estado legado inconsistente e exige intervenção explícita.

## ADR-028 — Failed e partial exigem retry explícito

**Decisão:** `failed` e `partial` não participam do claim automático. O comando de retry reutiliza a mesma execution, preserva identidade e `attempt_count`, limpa erro e timestamps da tentativa e retorna a linha a `pending`.

**Motivo:** uma falha precisa ser reconhecida antes de nova tentativa, evitando loops automáticos e perda do histórico acumulado.

## ADR-029 — Succeeded é terminal

**Decisão:** uma execution `succeeded` não aceita claim nem retry e não volta a `pending`.

**Motivo:** reabrir silenciosamente um trabalho concluído ampliaria o risco de duplicação.

## ADR-030 — Exactly-once de ticket depende da BATCH-001

**Decisão:** SAFE-001 declara idempotência somente no nível da `recurrence_execution`, não no nível de tickets HESK.

**Motivo:** ainda não existem identidade persistente por item, tracking ID reservado antes da criação ou reconciliação após crash. BATCH-001 deverá criar esse contrato antes de habilitar o processamento real.

## Pontos ainda pendentes

- modelo real completo das manutenções preventivas;
- identidade persistente e reconciliação de cada item de lote na BATCH-001;
- procedimento administrativo para resolver executions legadas `running` sem lease.
