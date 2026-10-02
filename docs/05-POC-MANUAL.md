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

A `POC-001` automática foi homologada reproduzindo este resultado sem depender de sessão web humana e sem alterar o core do HESK.

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

## Implementação automática

A implementação CLI correspondente está disponível em `bin/poc-create-ticket.php` e foi homologada em 02/10/2026.

O modo `--check` valida a instalação, o banco e todos os IDs/valores da baseline sem criar ticket. O modo `--execute` repete as mesmas validações e somente então chama `hesk_newTicket()` uma vez. A POC não chama a rotina de notificação ao solicitante.

Ver `docs/06-POC-CLI.md` para os arquivos e comandos exatos de homologação.


## Resultado da homologação automática

- Data: `02/10/2026`
- Ticket criado: `43`
- Tracking ID: `299-RY2-QZ4L`
- `--check`: aprovado sem criar ticket
- `--execute`: aprovado com criação única
- Solicitante: `Automação Technolife` (`customer_id=21`)
- Categoria: `WORKSTATION`
- Prioridade: `Baixa` (`id=3` no ambiente homologado)
- Status: `Novo`
- Responsável: `João Paulo Corsino` (`user_id=4`)
- Campos personalizados: equivalentes à baseline
- Vencimento: nenhum
- Notificação ao solicitante: a POC não chama rotina de notificação

A conferência visual do ticket confirmou a equivalência funcional com a baseline manual.
