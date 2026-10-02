# 09 — Segurança e idempotência de execution

**Status da SAFE-001:** `AGUARDANDO_HOMOLOGACAO`

## Objetivo e limite

SAFE-001 protege a posse e as transições de uma `recurrence_execution` contra workers concorrentes, Cron sobreposto, interrupção de processo, lease abandonado, retry indevido e uso de token antigo.

Esta etapa não carrega o HESK, não cria tickets, não implementa lote e não altera `recurrence.next_run_at`. Ela opera somente sobre executions já criadas pelo scheduler.

## Fluxo e máquina de estados

```text
Scheduler
    ↓
pending
    ↓ claim
running
    ├─ finish succeeded → succeeded (terminal)
    ├─ finish failed    → failed  ── retry explícito ──→ pending
    └─ finish partial   → partial ── retry explícito ──→ pending
```

Regras:

- `pending` pode ser claimed;
- `running` com lease válido pertence exclusivamente ao token atual;
- `running` com lease expirado e metadados completos pode sofrer stale takeover;
- `running` sem token, owner ou expiração é estado legado inconsistente e não é roubado automaticamente;
- `failed` e `partial` não entram no claim automático;
- `succeeded` não aceita claim nem retry.

## Migration 002

`database/migrations/002_execution_leases.sql` preserva a migration 001 e a restrição `UNIQUE (recurrence_id, scheduled_for)`. Ela acrescenta em `recurrence_executions`:

- `attempt_count INTEGER NOT NULL DEFAULT 0`;
- `lease_token TEXT NULL`;
- `lease_owner TEXT NULL`;
- `lease_expires_at TEXT NULL`;
- `last_attempt_at TEXT NULL`.

O índice `idx_recurrence_executions_claimable` usa `status`, `lease_expires_at`, `scheduled_for` e `id` para apoiar a busca ordenada de trabalho elegível. Registros anteriores recebem `attempt_count=0` e permanecem sem lease.

## Claim e concorrência SQLite

O serviço abre `BEGIN IMMEDIATE` antes de procurar o candidato. Enquanto mantém o lock:

1. localiza a primeira execution elegível, ou exclusivamente o ID solicitado;
2. confirma o status e a expiração;
3. gera `bin2hex(random_bytes(32))`;
4. move a linha para `running`;
5. registra owner, token, expiração, `last_attempt_at` e `started_at`;
6. incrementa `attempt_count`;
7. executa `COMMIT`, ou `ROLLBACK` em erro.

Assim, duas conexões SQLite não selecionam e assumem a mesma execution simultaneamente. O scheduler mantém sua transação atual e não realiza claim.

## Lease, owner e token

O lease padrão dura 300 segundos. A API e a CLI aceitam valores entre 30 e 3600 segundos. Todos os instantes são ISO-8601 UTC.

`lease_owner` é um texto legível de 1 a 255 caracteres. Pode seguir um formato operacional como `hostname:pid`, mas a biblioteca não depende dessa convenção.

O token aleatório é a prova de posse. Somente o token correspondente a um lease `running` ainda não expirado pode renovar ou finalizar. `list` e `show` nunca exibem `lease_token`; ele aparece somente no resultado do claim concedido e não deve ser salvo em logs, documentação ou Git.

## Heartbeat e stale takeover

Heartbeat estende `lease_expires_at` apenas quando status, token e validade ainda conferem. No instante exato da expiração, o lease já é considerado expirado e o worker antigo deve parar.

Uma execution `running` com `lease_expires_at <= agora` pode ser assumida por outro worker. O takeover troca owner e token, atualiza as datas e incrementa `attempt_count`. O token anterior perde imediatamente a capacidade de renovar, concluir, falhar ou modificar o estado.

## Finalização e retry

Finish aceita `succeeded`, `failed` ou `partial` somente com token ativo. Em todos os casos registra `finished_at` e limpa token, owner e expiração.

- `succeeded` sempre limpa `error_message` e é terminal;
- `failed` exige mensagem de erro;
- `partial` aceita mensagem explicativa opcional.

Retry é administrativo e explícito, somente para `failed` ou `partial`. Ele mantém ID, `recurrence_id`, `scheduled_for`, contagens e `attempt_count`; limpa lease, `error_message`, `started_at` e `finished_at`; e devolve a mesma linha a `pending`. Uma nova linha nunca é criada para retry.

## Limite da garantia e contrato futuro

SAFE-001 impede que dois workers processem conscientemente a mesma execution ao mesmo tempo e protege suas transições. Isso ainda não é exactly-once de ticket HESK.

Permanecem fora desta etapa:

- item persistente por ticket do lote;
- `hesk_ticket_id` ou tracking ID por item;
- identificador persistido antes da criação no HESK;
- reconciliação depois de crash;
- criação de um ou vários tickets.

BATCH-001 deverá persistir a identidade de cada item, reutilizá-la em retries e permitir verificar no HESK se o ticket já existe antes de repetir a criação. Nenhum desses mecanismos é implementado aqui.

## CLI administrativa

Ajuda:

```bash
php bin/execution.php
```

Listar e inspecionar sem expor token:

```bash
php bin/execution.php list --db-path=/caminho/app.sqlite
php bin/execution.php list --db-path=/caminho/app.sqlite --status=pending
php bin/execution.php show --db-path=/caminho/app.sqlite --id=1
```

Claim da próxima execution ou de um ID específico:

```bash
php bin/execution.php claim --db-path=/caminho/app.sqlite --worker=worker-a --lease-seconds=300
php bin/execution.php claim --db-path=/caminho/app.sqlite --worker=worker-a --id=1 --lease-seconds=300
```

Heartbeat, finish e retry:

```bash
php bin/execution.php heartbeat --db-path=/caminho/app.sqlite --id=1 --token=<TOKEN> --lease-seconds=300
php bin/execution.php finish --db-path=/caminho/app.sqlite --id=1 --token=<TOKEN> --result=succeeded
php bin/execution.php finish --db-path=/caminho/app.sqlite --id=1 --token=<TOKEN> --result=failed --error="erro controlado"
php bin/execution.php finish --db-path=/caminho/app.sqlite --id=1 --token=<TOKEN> --result=partial --error="processamento parcial"
php bin/execution.php retry --db-path=/caminho/app.sqlite --id=1
```

Recusas e validações retornam exit code diferente de zero. A CLI exige migrations atualizadas, não contém SQL, não carrega o HESK e não cria recurrence ou execution.

## Homologação no cPanel

Não use o `app.sqlite` real. Execute a homologação somente em:

```text
/home/tech2612/hesk-recorrencias/storage/safe-homolog.sqlite
```

Os tokens ficam apenas em variáveis temporárias do shell. Não os copie para arquivos ou logs.

### Preparação e criação da execution

```bash
cd /home/tech2612/hesk-recorrencias

test ! -e /home/tech2612/hesk-recorrencias/storage/safe-homolog.sqlite || { echo "Banco de homologação já existe; interrompendo."; exit 1; }

export APP_DB_PATH=/home/tech2612/hesk-recorrencias/storage/safe-homolog.sqlite

/usr/local/bin/php bin/recurrence.php migrate
/usr/local/bin/php bin/recurrence.php create --file=config/examples/safe-homolog.json
/usr/local/bin/php bin/scheduler.php run --limit=100
/usr/local/bin/php bin/execution.php list --status=pending
/usr/local/bin/php bin/execution.php show --id=1
```

O `migrate` deve aplicar `001_initial_schema` e `002_execution_leases`. O scheduler deve criar a execution `1` como `pending`, sem ticket no HESK.

### Claim exclusivo e heartbeat

```bash
CLAIM_A=$(/usr/local/bin/php bin/execution.php claim --id=1 --worker=homolog-worker-a --lease-seconds=300)
printf '%s\n' "$CLAIM_A"
TOKEN_A=$(printf '%s\n' "$CLAIM_A" | sed -n 's/^Lease token: //p')
test -n "$TOKEN_A"

if /usr/local/bin/php bin/execution.php claim --id=1 --worker=homolog-worker-b --lease-seconds=300; then
    echo "ERRO: worker B obteve um lease já ativo"
    exit 1
fi

/usr/local/bin/php bin/execution.php heartbeat --id=1 --token="$TOKEN_A" --lease-seconds=300
/usr/local/bin/php bin/execution.php show --id=1
```

O worker B deve receber `CLAIM NAO REALIZADO` com motivo `active_lease`. O show deve permanecer `running`, com `attempt_count=1`, owner A e expiração renovada, sem exibir o token.

### Falha controlada, retry e sucesso

```bash
/usr/local/bin/php bin/execution.php finish --id=1 --token="$TOKEN_A" --result=failed --error="falha controlada de homologação"
/usr/local/bin/php bin/execution.php show --id=1

/usr/local/bin/php bin/execution.php retry --id=1
/usr/local/bin/php bin/execution.php show --id=1

CLAIM_B=$(/usr/local/bin/php bin/execution.php claim --id=1 --worker=homolog-worker-b --lease-seconds=300)
printf '%s\n' "$CLAIM_B"
TOKEN_B=$(printf '%s\n' "$CLAIM_B" | sed -n 's/^Lease token: //p')
test -n "$TOKEN_B"

/usr/local/bin/php bin/execution.php finish --id=1 --token="$TOKEN_B" --result=succeeded
/usr/local/bin/php bin/execution.php show --id=1

if /usr/local/bin/php bin/execution.php retry --id=1; then
    echo "ERRO: execution succeeded aceitou retry"
    exit 1
fi
```

Depois da falha, o status deve ser `failed` e a mensagem deve estar presente. O retry deve manter a execution ID `1` e `attempt_count=1`, voltando a `pending` com erro e timestamps de tentativa limpos. O segundo claim deve elevar `attempt_count` para `2`. O finish final deve deixar `succeeded`, `finished_at` preenchido e lease limpo; o retry seguinte deve ser recusado como `terminal_succeeded`.

### Encerramento

```bash
/usr/local/bin/php bin/recurrence.php disable --id=1

unset TOKEN_A TOKEN_B CLAIM_A CLAIM_B APP_DB_PATH

rm -f /home/tech2612/hesk-recorrencias/storage/safe-homolog.sqlite
rm -f /home/tech2612/hesk-recorrencias/storage/safe-homolog.sqlite-wal
rm -f /home/tech2612/hesk-recorrencias/storage/safe-homolog.sqlite-shm
```

Confirme que os três arquivos isolados foram removidos, `/home/tech2612/hesk-recorrencias/storage/app.sqlite` permaneceu intacto e nenhum ticket foi criado no HESK.

## Testes locais

```bash
php tests/run.php
php tests/persistence.php
php tests/scheduler.php
php tests/safety.php
```

`tests/safety.php` cobre upgrade da migration 001 para 002, reexecução idempotente, duas conexões concorrentes, claim, heartbeat, expiração, stale takeover, invalidação do token antigo, estados finais, retry, estado legado, limites da CLI, preservação da recorrência e limpeza do banco temporário.
