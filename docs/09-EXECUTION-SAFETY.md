# 09 — Segurança e idempotência de execution

**Status da SAFE-001:** `CONCLUÍDO`

## Objetivo e limite

SAFE-001 protege a posse e as transições de uma `recurrence_execution` contra workers concorrentes, Cron sobreposto, interrupção de processo, lease abandonado, retry indevido e uso de token antigo.

Isoladamente, esta etapa não carrega o HESK, não cria tickets, não implementa lote e não altera `recurrence.next_run_at`. Ela opera somente sobre executions já criadas pelo scheduler. A BATCH-001 reutiliza este contrato sem criar um mecanismo paralelo de posse.

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

## Integração com a BATCH-001

SAFE-001 impede que dois workers processem conscientemente a mesma execution ao mesmo tempo e protege suas transições. A BATCH-001 acrescenta a identidade de ticket que não pertencia a esta etapa:

- um item SQLite por ticket do lote;
- tracking ID persistido antes da criação;
- reconciliação pelo tracking ID após interrupção;
- preservação de itens `succeeded` no retry;
- `created_count` derivado dos itens concluídos.

O `BatchProcessor` recebe uma execution já claimed. Antes de cada item, renova o lease; todas as mutações de item e de `created_count` exigem status `running`, token correspondente e `lease_expires_at` futuro. Depois de um lookup ou create externo, o worker renova novamente antes de persistir o resultado. Se perder a posse, para sem finalizar nem processar o item seguinte. Um novo worker assume pelo stale takeover e reconcilia qualquer ticket que tenha sido criado no intervalo.

Isso não transforma SQLite e MariaDB em uma transação única. A garantia contra a corrida conhecida usa tracking ID estável, lookup e named lock MariaDB no gateway BATCH; os detalhes e os limites estão em `docs/10-BATCH-PROCESSING.md`.

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
php bin/execution.php items --db-path=/caminho/app.sqlite --id=1
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

## Resultado da homologação real

A SAFE-001 foi homologada com sucesso no servidor real em 02/10/2026, usando somente o banco isolado:

```text
/home/tech2612/hesk-recorrencias/storage/safe-homolog.sqlite
```

O banco real `/home/tech2612/hesk-recorrencias/storage/app.sqlite` permaneceu intacto e nenhum ticket foi criado no HESK.

### Migrations e scheduler

A primeira execução aplicou `001_initial_schema` e `002_execution_leases`, com `foreign_keys=1` e `journal_mode=wal`. A segunda execução não aplicou nenhuma migration, confirmando a idempotência.

A recorrência de homologação foi criada com ID `1` e `next_run_at=2026-10-01T12:00:00Z`. O scheduler processou uma recorrência, sem ignoradas ou erros, criou a execution `1` para a mesma competência e avançou `next_run_at` para `2027-10-01T12:00:00Z`.

A execution inicial ficou com:

- `status=pending`;
- `expected_count=1` e `created_count=0`;
- `attempt_count=0`;
- início, fim, erro e dados de lease nulos.

### Claim exclusivo e heartbeat

`homolog-worker-a` obteve o primeiro claim, movendo a execution para `running`, com lease de 300 segundos e `attempt_count=1`. O token foi mantido somente em variável temporária da sessão.

Enquanto esse lease estava válido, `homolog-worker-b` tentou assumir a mesma execution e recebeu:

```text
CLAIM NAO REALIZADO
Motivo: active_lease
```

O heartbeat do worker A foi aprovado e renovou a expiração. `started_at` e `last_attempt_at` permaneceram associados ao início da tentativa, e o comando `show` não expôs `lease_token`.

### Falha, retry e segunda tentativa

A primeira tentativa foi finalizada como `failed`, com `error_message=falha controlada de homologação`. `finished_at` foi preenchido, o erro foi preservado e owner e expiração do lease foram limpos.

O retry explícito reutilizou a execution ID `1` e confirmou:

- mesmo `recurrence_id` e `scheduled_for`;
- `attempt_count=1` preservado;
- retorno a `pending`;
- limpeza de `started_at`, `finished_at`, `error_message` e lease;
- preservação de `last_attempt_at` como histórico da tentativa anterior.

`homolog-worker-b` realizou o segundo claim, recebeu um novo token e elevou `attempt_count` para `2`.

### Sucesso terminal

A segunda tentativa terminou com `FINISH OK` e estado final:

- `status=succeeded`;
- `attempt_count=2`;
- `created_count=0`;
- `finished_at` preenchido;
- erro, owner e expiração do lease nulos.

O retry posterior foi recusado com `terminal_succeeded`, confirmando que `succeeded` é terminal.

### Stale takeover

O stale takeover por expiração do lease não foi reproduzido manualmente nesta homologação do cPanel. A implementação permanece coberta por `tests/safety.php`, que valida lease expirado, takeover por outro worker, novo token, incremento de tentativa e impossibilidade de heartbeat ou finish pelo token antigo.

### Encerramento e isolamento

A recorrência ID `1` foi deixada com `enabled=false`. `safe-homolog.sqlite` e os auxiliares `-wal` e `-shm` foram removidos; depois da limpeza, `storage` continha somente `app.sqlite`. As variáveis temporárias de token, claim e caminho do banco foram removidas da sessão.

Com esses resultados, SAFE-001 está concluída. A BATCH-001 foi implementada e homologada posteriormente e está `CONCLUÍDO`; isso não altera os resultados históricos desta homologação.

## Testes locais

```bash
php tests/run.php
php tests/persistence.php
php tests/scheduler.php
php tests/safety.php
```

`tests/safety.php` cobre upgrade da migration 001 para 002, reexecução idempotente, duas conexões concorrentes, claim, heartbeat, expiração, stale takeover, invalidação do token antigo, estados finais, retry, estado legado, limites da CLI, preservação da recorrência e limpeza do banco temporário.
