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

## Pontos ainda pendentes

- modelo real completo das manutenções preventivas;
- decisão de persistência da aplicação.
