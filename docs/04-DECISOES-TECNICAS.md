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

## ADR-011 — Solicitante interno dedicado é preferível

**Situação observada:** não existe hoje customer com e-mail `@technolife.net.br`. Há um registro `9 - TECHNOLIFE`.

**Direção preferencial:** criar um customer dedicado à automação, em vez de acoplar definitivamente o sistema a um registro genérico.

**Motivo:** melhora rastreabilidade, auditoria e permite alterar política de e-mail/notificação sem afetar outros usos do registro TECHNOLIFE.

A POC pode reutilizar o customer 9 somente se isso for deliberadamente aprovado e validado como selecionável no formulário administrativo.

## Pontos ainda pendentes

- definir se o solicitante será `customer_id=9 (TECHNOLIFE)` ou uma nova conta dedicada;
- confirmar acesso do técnico escolhido à categoria `5 - WORKSTATION`, inclusive por grupos de permissão;
- modelo real completo das manutenções preventivas;
- decisão de persistência da aplicação;
- estratégia final de bootstrap do HESK para CLI.
