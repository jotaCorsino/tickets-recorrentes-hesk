# 08 — Scheduler de recorrências

**Status da SCH-001:** `CONCLUÍDO`

## Objetivo e limite

O scheduler identifica recorrências ativas e vencidas, registra cada ocorrência como `pending` e avança `next_run_at`. Ele não cria tickets, não carrega o HESK, não processa executions pendentes e não instala Cron.

## Scheduler x Cron

O scheduler contém a regra de seleção, cálculo e persistência. O Cron será apenas o relógio externo que futuramente chamará:

```bash
php bin/scheduler.php run --db-path=/caminho/app.sqlite --limit=100
```

Nenhum Cron é criado pela aplicação nesta etapa.

## Fluxo

```text
agora UTC
    ↓
findDue: enabled=1 e next_run_at <= agora
    ↓ ordena por next_run_at, id e aplica limit
para cada recorrência
    ↓
scheduled_for = next_run_at atual
    ↓
calcula próxima data no calendário civil da timezone
    ↓
transação SQLite
    ├─ cria recurrence_execution pending
    └─ atualiza next_run_at se ainda tiver o valor esperado
    ↓
commit ou rollback integral
```

Não foi necessária migration `002`; o schema homologado em `001_initial_schema.sql` já comporta a SCH-001 e não foi alterado.

## Clock e seleção

`Scheduler::check()` e `Scheduler::run()` recebem um `DateTimeImmutable`. A CLI injeta o horário UTC corrente; testes usam instantes fixos.

`RecurrenceRepository::findDue()` seleciona somente:

```text
enabled = 1 AND next_run_at <= agora UTC
```

O resultado usa o índice `idx_recurrences_enabled_next_run`, ordena por `next_run_at` e `id` e respeita `--limit`. O padrão é 100 e os valores aceitos são de 1 a 1000 recorrências. `quantity` não altera esse limite.

## Cálculo civil e timezone

O banco continua armazenando instantes em UTC. Para calcular a próxima ocorrência:

1. converte `scheduled_for` para a timezone IANA da recorrência;
2. adiciona o intervalo no calendário civil local;
3. converte o resultado novamente para UTC.

Assim, uma agenda de 09:00 permanece às 09:00 locais quando o offset legal mudar.

Unidades:

- `day`: adiciona dias civis;
- `week`: adiciona blocos de sete dias civis;
- `month`: calcula ano/mês de destino e limita o dia ao último válido;
- `year`: mantém mês e dia quando válidos e limita o dia quando necessário.

Exemplos:

```text
31/jan + 1 mês → 28/fev ou 29/fev
31/mar + 1 mês → 30/abr
29/fev + 1 ano não bissexto → 28/fev
```

Hora, minuto, segundo e timezone civil são preservados. O cálculo seguinte sempre parte do `next_run_at` persistido corrente.

## Política de catch-up

Cada run processa no máximo uma competência por recorrência. Se `next_run_at` estiver vários períodos atrasado, o scheduler registra a competência mais antiga e avança somente uma vez. Um run posterior poderá processar a próxima competência ainda vencida.

Essa política evita loops e rajadas ilimitadas. Catch-up configurável não faz parte da SCH-001.

## Transação e duplicidade

A criação da execution e o avanço de `next_run_at` usam a mesma transação. Se a inserção ou atualização falhar, ambas são revertidas.

O update compara o `next_run_at` esperado e exige que a recorrência continue ativa. Se outro processo ou operador modificar a linha, o scheduler faz rollback e relata erro.

Se já existir `(recurrence_id, scheduled_for)`, a recorrência é reportada em `skipped` com motivo `execution_already_exists`; nenhuma nova linha é criada e `next_run_at` não avança silenciosamente.

Essa proteção não conclui SAFE-001. Permanecem fora do escopo:

- locking e reserva entre múltiplos workers;
- retry automático;
- retomada depois de falha;
- recuperação de lotes parciais;
- tickets criados antes de um eventual crash.

## CLI

Sem argumentos, a CLI mostra ajuda e não executa o scheduler.

Dry run:

```bash
php bin/scheduler.php check --db-path=/caminho/app.sqlite --limit=100
```

O comando informa o horário usado, as recorrências vencidas, `scheduled_for` e o próximo instante calculado. Nenhuma recorrência ou execution é alterada.

Persistência:

```bash
php bin/scheduler.php run --db-path=/caminho/app.sqlite --limit=100
```

O relatório separa recorrências processadas, ignoradas e com erro. Também é possível configurar o banco por `APP_DB_PATH`.

Inspeção somente leitura das executions:

```bash
php bin/recurrence.php executions --db-path=/caminho/app.sqlite --id=1
```

## Homologação no cPanel

Não use o `app.sqlite` real. A homologação deve ocorrer somente em:

```text
/home/tech2612/hesk-recorrencias/storage/scheduler-homolog.sqlite
```

Comandos exatos:

```bash
cd /home/tech2612/hesk-recorrencias

test ! -e /home/tech2612/hesk-recorrencias/storage/scheduler-homolog.sqlite || { echo "Banco de homologação já existe; interrompendo."; exit 1; }

export APP_DB_PATH=/home/tech2612/hesk-recorrencias/storage/scheduler-homolog.sqlite

/usr/local/bin/php bin/recurrence.php migrate
/usr/local/bin/php bin/recurrence.php create --file=config/examples/scheduler-homolog.json

/usr/local/bin/php bin/recurrence.php show --id=1
/usr/local/bin/php bin/recurrence.php executions --id=1

/usr/local/bin/php bin/scheduler.php check --limit=100
/usr/local/bin/php bin/recurrence.php show --id=1
/usr/local/bin/php bin/recurrence.php executions --id=1

/usr/local/bin/php bin/scheduler.php run --limit=100
/usr/local/bin/php bin/recurrence.php show --id=1
/usr/local/bin/php bin/recurrence.php executions --id=1

/usr/local/bin/php bin/scheduler.php run --limit=100
/usr/local/bin/php bin/recurrence.php show --id=1
/usr/local/bin/php bin/recurrence.php executions --id=1

/usr/local/bin/php bin/recurrence.php disable --id=1

unset APP_DB_PATH
rm -f /home/tech2612/hesk-recorrencias/storage/scheduler-homolog.sqlite
rm -f /home/tech2612/hesk-recorrencias/storage/scheduler-homolog.sqlite-wal
rm -f /home/tech2612/hesk-recorrencias/storage/scheduler-homolog.sqlite-shm
```

Resultados esperados:

- antes do `check`, a lista de executions está vazia;
- depois do `check`, a recorrência e a lista continuam inalteradas;
- o primeiro `run` cria uma execution `pending` para `2026-08-31T12:00:00Z` e avança para `2026-09-30T12:00:00Z`;
- o segundo `run` cria somente a próxima execution e avança para `2026-10-30T12:00:00Z`;
- `expected_count=1`, `created_count=0` e datas de início/fim permanecem nulas;
- nenhum ticket é criado no HESK;
- os arquivos do banco isolado são removidos ao final;
- `/home/tech2612/hesk-recorrencias/storage/app.sqlite` não é tocado.

## Resultado da homologação real

A SCH-001 foi homologada com sucesso no servidor real em 02/10/2026, usando somente o banco isolado:

```text
/home/tech2612/hesk-recorrencias/storage/scheduler-homolog.sqlite
```

A migration foi aplicada normalmente e a recorrência de homologação foi criada com ID `1` e `next_run_at=2026-08-31T12:00:00Z`.

### Dry run

O modo `check` encontrou uma recorrência vencida, com:

```text
scheduled_for: 2026-08-31T12:00:00Z
next_run_at calculado: 2026-09-30T12:00:00Z
```

Nenhuma alteração foi persistida e a lista de executions continuou vazia.

### Primeiro run

O primeiro `run` retornou uma processada, zero ignoradas e zero erros. Ele criou a execution `1` com:

- `scheduled_for=2026-08-31T12:00:00Z`;
- `status=pending`;
- `expected_count=1`;
- `created_count=0`;
- `started_at=null`;
- `finished_at=null`;
- `error_message=null`.

O `next_run_at` avançou para `2026-09-30T12:00:00Z`.

### Segundo run e catch-up

O segundo `run` também retornou uma processada, zero ignoradas e zero erros. Ele criou somente a execution `2`, para `scheduled_for=2026-09-30T12:00:00Z`, e avançou `next_run_at` para `2026-10-30T12:00:00Z`.

Foram confirmadas exatamente duas executions, uma para cada competência processada. Isso validou a política conservadora de uma competência por recorrência por run.

O check final informou zero recorrências vencidas e não realizou alterações.

### Encerramento e isolamento

- recorrência ID `1` deixada com `enabled=false`;
- `scheduler-homolog.sqlite` removido;
- auxiliares `-wal` e `-shm` removidos;
- `/home/tech2612/hesk-recorrencias/storage/app.sqlite` permaneceu intacto;
- nenhum ticket foi criado no HESK.

Com esses resultados, a SCH-001 está concluída. SAFE-001 e BATCH-001 permanecem pendentes e não foram iniciadas.

## Exemplo futuro de Cron

Após homologação e em tarefa de implantação, uma entrada poderá ter formato semelhante a:

```cron
*/5 * * * * APP_DB_PATH=/home/tech2612/hesk-recorrencias/storage/app.sqlite /usr/local/bin/php /home/tech2612/hesk-recorrencias/bin/scheduler.php run --limit=100 >> /home/tech2612/logs/hesk-recorrencias-scheduler.log 2>&1
```

Esse exemplo é apenas documental. A SCH-001 não instala nem ativa Cron.

## Testes locais

```bash
php tests/run.php
php tests/persistence.php
php tests/scheduler.php
```

A suíte do scheduler cobre seleção, dry run, persistência, atomicidade, rollback, duplicidade, catch-up, limite, timezone, unidades de intervalo, fim de mês e limpeza do banco temporário.
