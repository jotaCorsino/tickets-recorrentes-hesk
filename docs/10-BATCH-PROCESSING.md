# 10 — Processamento de lotes e reconciliação HESK

**Status da BATCH-001:** `AGUARDANDO_HOMOLOGACAO`

## Objetivo

BATCH-001 transforma uma `recurrence_execution` já claimed em `expected_count` tickets independentes. Cada ticket planejado recebe uma identidade persistente antes da criação no HESK, pode ser reconciliado depois de interrupção e mantém seu histórico entre retries.

O escopo não inclui patrimônio individual, anexos, notificações, painel web, Cron real ou implantação definitiva. Todos os itens usam a mesma definição da recorrência e `notify_customer=true` é recusado explicitamente.

## Migration 003 e modelo dos itens

`database/migrations/003_execution_items.sql` cria `recurrence_execution_items` sem alterar as migrations 001 e 002 homologadas.

| Campo | Regra |
|---|---|
| `id` | chave primária autoincrementável |
| `execution_id` | FK para `recurrence_executions.id`, com update e delete restritos |
| `item_index` | inteiro positivo e único dentro da execution |
| `status` | `pending`, `creating`, `succeeded` ou `failed` |
| `hesk_trackid` | nulo até a preparação; único quando preenchido |
| `hesk_ticket_id` | nulo até criação/reconciliação; positivo e único quando preenchido |
| `creation_attempts` | número não negativo de chamadas de criação iniciadas |
| `last_attempt_at` | instante UTC da última tentativa de criação |
| `error_message` | último erro do item, ou nulo |
| `created_at`, `updated_at` | timestamps UTC do item |

Há `UNIQUE (execution_id, item_index)` e índice `(execution_id, status, item_index)`. O FK usa `ON UPDATE RESTRICT` e `ON DELETE RESTRICT` para impedir que a execução seja removida deixando itens órfãos.

## Materialização e tamanho do lote

O tamanho do lote é o snapshot `recurrence_execution.expected_count`. O valor atual de `recurrence.quantity` não é consultado durante o processamento nem durante retries.

`ExecutionItemRepository::materialize()` roda em `BEGIN IMMEDIATE`, exige o lease ativo da execution e usa inserção idempotente para criar exatamente os índices `1..N`. Ao final, verifica se a lista persistida corresponde integralmente ao intervalo esperado. Uma segunda chamada retorna os mesmos IDs e não cria itens adicionais.

## Estados do item

```text
pending
   ↓ tracking ID persistido
creating ───────────────→ succeeded
   │                         ↑
   └─ erro → failed ── retry ┘
```

- `pending`: item materializado, ainda sem tentativa externa;
- `creating`: tracking ID já persistido, `creation_attempts` incrementado e chamada externa prestes a ocorrer;
- `succeeded`: tracking ID e ID numérico do ticket confirmados;
- `failed`: erro preservado; o tracking ID, quando já atribuído, não é apagado.

Um retry ignora `succeeded` e volta a processar `pending`, `creating` ou `failed`. Não cria outra execution, outro item ou outro tracking ID para a mesma identidade.

## Tracking ID e evolução do criador

`HeskTicketCreator::generateTrackingId()` usa `hesk_createID()`. O `BatchProcessor` salva o valor no item antes de consultar ou criar o ticket. A atribuição só ocorre quando `hesk_trackid` ainda é nulo e é protegida pelo token do lease.

`HeskTicketCreator::create()` passou a aceitar um tracking ID explícito. Quando ele é omitido, a POC-001 preserva o comportamento anterior. Quando informado, o mesmo valor é enviado a `hesk_newTicket()` e o retorno precisa corresponder exatamente ao identificador preparado.

Os labels adicionais da POC (`customer_name`, `category_name`, `owner_name` e `openedby_name`) agora são opcionais. Quando presentes, continuam validados. O worker monta a definição persistente usando `customer_id`, `category_id`, `priority_name`, `status_id`, `owner_id`, `openedby_id`, `subject`, `message` e `custom_fields`.

## Gateway, lookup e reconciliação

`TicketGateway` separa o motor do HESK real e permite um fake determinístico nos testes. `HeskTicketGateway` delega validação e criação a `HeskTicketCreator` e faz lookup somente leitura de `id` e `trackid` em `hesktx_tickets`.

Para um item com tracking ID:

1. o worker consulta o HESK;
2. se o ticket existir, grava `succeeded` e seu ID sem chamar criação;
3. se não existir, grava `creating` e chama o gateway;
4. o gateway repete o lookup sob lock, chama `hesk_newTicket()` somente se ainda necessário e valida o registro persistido;
5. o worker grava `succeeded` apenas se ainda possuir o lease.

Esse fluxo recupera o caso em que o HESK confirmou o ticket, mas o processo caiu antes de salvar o sucesso no SQLite. O retry encontra o ticket pelo mesmo tracking ID e reconcilia o item.

## Concorrência no HESK

O código estudado do HESK 3.7.12 verifica a disponibilidade ao gerar o tracking ID, mas não forneceu evidência suficiente de uma restrição `UNIQUE` no MariaDB que, sozinha, impedisse duas inserções simultâneas com o mesmo valor. Por isso, o gateway usa um named lock determinístico:

```text
GET_LOCK('tickets-recorrentes:<trackid>', 10)
    ↓
lookup do tracking ID
    ├─ existe: devolver ticket reconciliado
    └─ não existe: hesk_newTicket() + novo lookup de validação
    ↓
RELEASE_LOCK(...) em finally
```

Nenhuma transação SQLite permanece aberta durante lookup ou criação no HESK. O único caminho de escrita de ticket é `hesk_newTicket()`; o projeto não insere nem atualiza diretamente `hesktx_tickets`.

## Integração com SAFE e perda de lease

`bin/worker.php run` usa `ExecutionLeaseService::claimById()` e entrega o token somente em memória ao `BatchProcessor`; o token nunca é impresso. Antes de cada item, o lease é renovado. Depois de lookup ou create externo, há nova renovação antes de registrar o resultado.

Materialização, tracking ID, mudanças de estado, ID do ticket, `created_count` e finalização exigem simultaneamente:

```text
execution.status = running
lease_token = token atual
lease_expires_at > agora
```

Se o lease for perdido, o worker para, não finaliza a execution e não inicia itens seguintes. Se o HESK tiver criado um ticket nesse intervalo, ele não é apagado; o worker sucessor o reconcilia pelo tracking ID.

## created_count e resultado da execution

`created_count` não conta chamadas ao HESK. Ele é sincronizado com `COUNT(*)` dos itens `succeeded` e nunca pode ultrapassar `expected_count`.

- `created_count == expected_count`: `succeeded`;
- `created_count == 0` e o lote não concluiu: `failed`;
- `0 < created_count < expected_count`: `partial`.

`failed` e `partial` exigem o retry explícito da SAFE-001. `succeeded` continua terminal.

## Janela entre SQLite e HESK e limite da garantia

SQLite e MariaDB não oferecem transação distribuída. A implementação reduz a janela conhecida com identidade persistente, lookup, named lock por tracking ID e reconciliação. Assim:

- retry reutiliza a mesma identidade;
- item `succeeded` não é recriado;
- ticket já existente é recuperado pelo tracking ID;
- dois workers que seguem o gateway BATCH serializam consulta e criação desse tracking ID.

Não se declara uma garantia absoluta de exactly-once fora desse protocolo. A proteção depende da disponibilidade do MariaDB, da integridade do tracking ID e de todos os criadores concorrentes relevantes respeitarem o mesmo named lock. Uma alteração externa que crie ou modifique tickets ignorando esse contrato está fora da garantia.

## CLIs

Validar ambiente e uma execution sem claim, materialização ou criação:

```bash
php bin/worker.php check \
  --db-path=/caminho/app.sqlite \
  --hesk-path=/caminho/hesk \
  --id=1
```

Executar uma execution específica:

```bash
php bin/worker.php run \
  --db-path=/caminho/app.sqlite \
  --hesk-path=/caminho/hesk \
  --id=1 \
  --worker=hostname:pid \
  --lease-seconds=300
```

Inspecionar itens sem expor o token:

```bash
php bin/execution.php items --db-path=/caminho/app.sqlite --id=1
```

A saída de itens contém ID, índice, status, tracking ID, ticket ID, tentativas, data da última tentativa e erro. Nenhuma CLI imprime configurações ou credenciais do HESK.

## Testes locais

```bash
php tests/run.php
php tests/persistence.php
php tests/scheduler.php
php tests/safety.php
php tests/batch.php
```

`tests/batch.php` executa 89 asserções sem carregar o HESK real. A cobertura inclui migration 003 e sua idempotência, preservação das migrations 001/002, materialização, snapshot de quantidade, tracking ID anterior à criação, reconciliação após crash, item `creating`, sucesso, falha total, lote parcial, retry, tokens inválido/expirado/antigo, heartbeat, perda de lease, preservação de `next_run_at`, `notify_customer=true`, CLIs, named lock e remoção do banco temporário. As suítes anteriores também permanecem aprovadas.

## Homologação controlada no cPanel

Esta etapa cria dois tickets reais, claramente identificados por `[HOMOLOGAÇÃO BATCH-001]`. Eles devem permanecer no HESK para conferência manual; não os exclua automaticamente. Não use inicialmente o `app.sqlite` real e não exponha arquivos ou credenciais do HESK.

### 1. Preparar e confirmar a revisão

```bash
cd /home/tech2612/hesk-recorrencias

git branch --show-current
git rev-parse HEAD
git status --short
```

Confirme a branch `task/BATCH-001-ticket-batches`, o commit informado no PR e a árvore limpa. Em seguida proteja o banco real por hash e confirme que o banco isolado não existe:

```bash
APP_DB=/home/tech2612/hesk-recorrencias/storage/app.sqlite
BATCH_DB=/home/tech2612/hesk-recorrencias/storage/batch-homolog.sqlite
HESK_ROOT=/home/tech2612/suporte.technolife.net.br/

APP_SHA_BEFORE=$(sha256sum "$APP_DB" | awk '{print $1}')
test -n "$APP_SHA_BEFORE"

test ! -e "$BATCH_DB" || { echo "Banco de homologação já existe; interrompendo."; exit 1; }
test ! -e "${BATCH_DB}-wal"
test ! -e "${BATCH_DB}-shm"

export APP_DB_PATH="$BATCH_DB"
export HESK_PATH="$HESK_ROOT"
```

### 2. Aplicar e repetir as migrations

```bash
/usr/local/bin/php bin/recurrence.php migrate
/usr/local/bin/php bin/recurrence.php migrate
```

A primeira execução deve aplicar `001_initial_schema`, `002_execution_leases` e `003_execution_items`, com `foreign_keys=1` e `journal_mode=wal`. A segunda deve informar `Migrations aplicadas: nenhuma`.

### 3. Criar recorrência e execution

```bash
/usr/local/bin/php bin/recurrence.php create --file=config/examples/batch-homolog.json
/usr/local/bin/php bin/recurrence.php show --id=1
/usr/local/bin/php bin/scheduler.php run --limit=100
/usr/local/bin/php bin/execution.php show --id=1
```

Confirme recorrência ID 1 com `quantity=2`; execution ID 1 em `pending`, `expected_count=2`, `created_count=0`; e avanço da recorrência para a próxima competência. Até aqui nenhum ticket deve ter sido criado.

### 4. Check e inspeção antes da criação

```bash
/usr/local/bin/php bin/worker.php check --id=1
/usr/local/bin/php bin/execution.php items --id=1
/usr/local/bin/php bin/execution.php show --id=1
```

O `check` deve imprimir `BATCH CHECK OK`, `Expected: 2`, zero itens existentes, nenhum claim e nenhum ticket criado. `items` deve retornar uma lista vazia e a execution deve continuar `pending` com `attempt_count=0`.

### 5. Executar o lote

```bash
WORKER_ID="$(hostname):$$"

/usr/local/bin/php bin/worker.php run \
  --id=1 \
  --worker="$WORKER_ID" \
  --lease-seconds=300

/usr/local/bin/php bin/execution.php show --id=1
/usr/local/bin/php bin/execution.php items --id=1
```

O run deve informar `BATCH RUN OK`, dois itens `succeeded`, `Expected: 2`, `Succeeded: 2`, `Failed: 0`, `Created now: 2`, `created_count=2` e execution `succeeded`. Cada item deve ter `hesk_trackid`, `hesk_ticket_id`, `creation_attempts=1` e erro nulo.

### 6. Conferir os tickets no HESK

Abra o HESK e procure os dois tracking IDs exibidos por `execution.php items`. Para cada ticket, confirme:

- solicitante `Automação Technolife` (`customer_id=21`);
- categoria `WORKSTATION` (`category_id=5`);
- prioridade `Baixa`;
- status `Novo` (`status_id=0`);
- responsável e autor `João Paulo Corsino` (`user_id=4`);
- assunto `[HOMOLOGAÇÃO BATCH-001] Manutenção preventiva - WORKSTATION`;
- mensagem de homologação, incluindo `Não executar atendimento.`;
- `custom7=Presencial`, `custom9=WINDOWS`, `custom10=REQ_Manutenção preventiva`, `custom15=TECHNOLIFE` e `custom16` vazio;
- nenhum vencimento;
- nenhuma notificação ao solicitante.

### 7. Confirmar execução terminal e ausência de duplicação

```bash
if /usr/local/bin/php bin/worker.php run \
  --id=1 \
  --worker="$WORKER_ID" \
  --lease-seconds=300; then
    echo "ERRO: execution succeeded aceitou novo processamento"
    exit 1
fi

/usr/local/bin/php bin/execution.php show --id=1
/usr/local/bin/php bin/execution.php items --id=1
```

O segundo run deve ser recusado como `terminal_succeeded`. Confirme no HESK que os dois tracking IDs continuam apontando para exatamente os mesmos dois tickets e que nenhum terceiro ticket foi criado.

O crash exato entre o commit no HESK e a atualização no SQLite não deve ser provocado manualmente nem simulado com edição direta de `hesktx_tickets`. Esse cenário permanece coberto pelo fake automatizado; a homologação real valida criação normal e reexecução terminal de forma não destrutiva.

### 8. Encerrar e preservar somente os tickets

```bash
/usr/local/bin/php bin/recurrence.php disable --id=1
/usr/local/bin/php bin/recurrence.php show --id=1

unset APP_DB_PATH HESK_PATH WORKER_ID

rm -f /home/tech2612/hesk-recorrencias/storage/batch-homolog.sqlite
rm -f /home/tech2612/hesk-recorrencias/storage/batch-homolog.sqlite-wal
rm -f /home/tech2612/hesk-recorrencias/storage/batch-homolog.sqlite-shm

test ! -e /home/tech2612/hesk-recorrencias/storage/batch-homolog.sqlite
test ! -e /home/tech2612/hesk-recorrencias/storage/batch-homolog.sqlite-wal
test ! -e /home/tech2612/hesk-recorrencias/storage/batch-homolog.sqlite-shm

APP_SHA_AFTER=$(sha256sum /home/tech2612/hesk-recorrencias/storage/app.sqlite | awk '{print $1}')
test "$APP_SHA_BEFORE" = "$APP_SHA_AFTER"

unset APP_DB BATCH_DB HESK_ROOT APP_SHA_BEFORE APP_SHA_AFTER
```

Confirme a recorrência desabilitada antes da limpeza, a remoção exclusiva do banco isolado e auxiliares, e o hash inalterado de `app.sqlite`. Os dois tickets de homologação ficam no HESK para auditoria manual.

## Critério de saída

Uma execution materializa N itens persistentes; cada item possui identidade HESK rastreável, retries não recriam itens `succeeded` e tickets existentes são reconciliados pelo tracking ID.

A BATCH-001 só poderá ser marcada `CONCLUÍDO` depois da homologação acima. UI-001 e DEP-001 não foram iniciadas.
