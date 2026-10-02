# 05 — Baseline manual da POC

## Objetivo

Registrar o chamado manual de referência que a implementação automática deverá reproduzir no HESK 3.7.12.

## Resultado

O chamado manual foi criado com sucesso em 02/10/2026.

- Número do chamado: `42`
- Código de identificação: `L32-DAG-R778`
- Solicitante: `Automação Technolife`
- `customer_id`: `21`
- Categoria: `5 - WORKSTATION`
- Prioridade: `Baixa`
- Status: `Novo`
- Responsável: `João Paulo Corsino` (`user_id=4`)
- Tipo de atendimento: `Presencial`
- `custom9`: `WINDOWS`
- `custom10`: `REQ_Manutenção preventiva`
- `custom15`: `TECHNOLIFE`
- `custom16`: vazio
- Data de vencimento: nenhuma
- Assunto: `[POC] Manutenção preventiva - WORKSTATION`
- Mensagem:
  - `Chamado de teste para validação da automação de manutenções preventivas.`
  - `Não executar atendimento.`

## Uso como referência

A `POC-001` automática deverá reproduzir este resultado sem depender de sessão web humana e sem alterar o core do HESK.

A comparação de homologação deve conferir pelo menos:

1. solicitante correto;
2. categoria correta;
3. prioridade e status corretos;
4. responsável correto;
5. custom fields corretos;
6. assunto e mensagem corretos;
7. ausência de vencimento quando não configurado;
8. ausência de notificação ao solicitante;
9. geração normal de número e tracking ID pelo HESK;
10. relacionamento normal em `ticket_to_customer`.

## Observação

Este chamado manual é apenas a baseline funcional. Ele não conclui a `POC-001`, pois a POC exige criação automática via CLI usando o mecanismo do HESK.
