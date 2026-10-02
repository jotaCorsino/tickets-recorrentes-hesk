# 01 — Levantamento do ambiente HESK

## Ambiente

- HESK: 3.7.12
- PHP: 8.2.33
- Extensões requeridas para a persistência: `PDO`, `pdo_sqlite` e `sqlite3`
- Banco: MariaDB 10.11.19
- Banco da aplicação: SQLite próprio, separado do MariaDB do HESK
- URL administrativa: `https://suporte.technolife.net.br/admin/admin_main.php`
- Diretório da instalação: `/home/tech2612/suporte.technolife.net.br/`
- Prefixo das tabelas: `hesktx_`
- Cron disponível via cPanel

## Persistência da aplicação

A CFG-001 definiu o arquivo de produção planejado em:

```text
/home/tech2612/hesk-recorrencias/storage/app.sqlite
```

O caminho é configurável por `--db-path` ou pela variável `APP_DB_PATH`. O SQLite guarda somente recorrências e seu histórico de execuções; o HESK continua usando seu próprio MariaDB. O arquivo de banco e seus auxiliares WAL não devem ser versionados.

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

### Solicitante dedicado criado

Foi criado um customer específico para a automação:

| ID | Nome | E-mail | verified |
|---:|---|---|---:|
| 21 | Automação Technolife | teste@automacao.net.br | 0 |

Para a POC, o solicitante será `customer_id=21`.

O campo de e-mail desta base não é utilizado operacionalmente no fluxo atual, e os registros existentes usam endereços de teste/inválidos. Mesmo assim, a opção **Enviar notificação por e-mail ao cliente** deve permanecer desabilitada nos tickets automáticos.

## Equipe — IDs HESK

Mapeamento observado em `hesktx_users`:

| ID | Nome | Perfil |
|---:|---|---|
| 1 | System | administrador |
| 2 | Nilton Teodoro | administrador |
| 3 | João Gabriel Silveira | técnico |
| 4 | João Paulo Corsino | técnico |

Os IDs acima são IDs de **usuários da equipe** e servem para campos como `owner`, `openedby` e `assignedby`. Eles não substituem o `customer_id` do solicitante.

### Grupos de permissão e categorias

Em `hesktx_permission_group_members`:

| user_id | group_id |
|---:|---:|
| 3 | 2 |
| 4 | 2 |

O grupo 2 possui acesso às categorias observadas no levantamento:

`1, 2, 3, 4, 5, 7, 8, 9`

Portanto, **João Gabriel Silveira (3)** e **João Paulo Corsino (4)** possuem acesso à categoria `5 - WORKSTATION` por grupo de permissão e podem ser considerados responsáveis válidos para a POC.

O grupo 1 possui acesso às categorias `1, 2, 3, 4, 5`, mas nenhum vínculo de usuário com esse grupo foi observado no levantamento enviado.

## Responsável

A tela administrativa permite:

- não atribuído;
- atribuição automática;
- atribuição explícita a um técnico com acesso à categoria.

A automação deverá permitir configurar o técnico responsável e validar o acesso dele à categoria selecionada.

Para a POC de WORKSTATION, os usuários 3 e 4 estão validados quanto ao acesso à categoria.

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
- `hesktx_permission_group_categories`
- `hesktx_permission_group_members`

A solução não deverá escrever diretamente em `hesktx_tickets`.

## Arquivos HESK analisados

- `admin/new_ticket.php`
- `admin/admin_submit_ticket.php`
- `inc/posting_functions.inc.php`

Esses arquivos são referências de comportamento do HESK 3.7.12 e não devem ser copiados para o repositório do projeto sem necessidade.
