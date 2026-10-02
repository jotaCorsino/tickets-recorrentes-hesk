# 01 — Levantamento do ambiente HESK

## Ambiente

- HESK: 3.7.12
- PHP: 8.2.33
- Banco: MariaDB 10.11.19
- URL administrativa: `https://suporte.technolife.net.br/admin/admin_main.php`
- Diretório da instalação: `/home/tech2612/suporte.technolife.net.br/`
- Prefixo das tabelas: `hesktx_`
- Cron disponível via cPanel

## Categorias identificadas

| ID | Categoria |
|---:|---|
| 1 | EMAIL |
| 2 | UTM |
| 3 | SERVER |
| 4 | BACKOFFICE |
| 5 | WORKSTATION |
| 6 | CONSULTORIA |
| 7 | REDE |
| 8 | PERIFERICOS |
| 9 | HOSPEDAGEM |

## Campos personalizados relevantes

| Identificação | Campo | Observação |
|---:|---|---|
| 7 | Tipo de atendimento | obrigatório |
| 9 | WORKSTATION > Subcategoria | obrigatório em WORKSTATION |
| 10 | WORKSTATION > Problema/Requisição | obrigatório em WORKSTATION |
| 15 | CLIENTE | empresa atendida |
| 16 | PATRIMONIO | opcional |

### custom9 — WORKSTATION > Subcategoria

Valores conhecidos:

- WINDOWS
- OFFICE
- ANTIVIRUS

### custom10 — WORKSTATION > Problema/Requisição

Valores conhecidos:

- PRO_Não abre programa
- REQ_Instalar programa
- REQ_Desinstalar programa
- REQ_Entregar, Instalar e configurar
- REQ_Manutenção preventiva

### custom15 — CLIENTE

Campo do tipo caixa de combinação, pesquisável e compartilhado entre categorias selecionadas. Representa a empresa atendida e não deve ser confundido com o solicitante nativo do HESK.

## Solicitante x empresa atendida

O HESK exige um `customer_id` para o solicitante. Para esta automação:

- solicitante: conta interna da Technolife;
- empresa atendida: `custom15 - CLIENTE`.

Assim, o cliente externo não precisa abrir o chamado.

## Responsável

A tela administrativa permite:

- não atribuído;
- atribuição automática;
- atribuição explícita a um técnico com acesso à categoria.

A automação deverá permitir configurar o técnico responsável.

## Notificações

Na criação administrativa existe a opção de notificar o cliente. Para a primeira versão da automação:

- notificação ao solicitante/cliente externo: desabilitada;
- notificação ao técnico atribuído: preservar o comportamento nativo do HESK quando aplicável.

## Banco

Tabelas observadas e relevantes:

- `hesktx_tickets`
- `hesktx_ticket_to_customer`
- `hesktx_custom_fields`
- `hesktx_customers`
- `hesktx_users`
- `hesktx_categories`
- `hesktx_ticket_templates`

A solução não deverá escrever diretamente em `hesktx_tickets`.

## Arquivos HESK analisados

- `admin/new_ticket.php`
- `admin/admin_submit_ticket.php`
- `inc/posting_functions.inc.php`

Esses arquivos são referências de comportamento do HESK 3.7.12 e não devem ser copiados para o repositório do projeto sem necessidade.
