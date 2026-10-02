# 06 — Implementação e homologação da POC CLI

## Estado

`POC-001`: **CONCLUÍDO**.

Os testes locais validaram sintaxe e comportamento isolado da linha de comando. A homologação no servidor HESK 3.7.12 também foi concluída com sucesso em 02/10/2026.

## Escopo implementado

- execução exclusiva por PHP CLI;
- caminho do HESK por `--hesk-path` ou variável `HESK_PATH`;
- bootstrap do HESK 3.7.12 com contexto HTTPS;
- conexão pelo mecanismo de banco do HESK;
- modo `--check` somente leitura;
- validação do solicitante, categoria, responsável, autor interno, prioridade, status e campos personalizados;
- tracking ID novo por `hesk_createID()`;
- persistência por `hesk_newTicket()`;
- nenhum seguidor, anexo ou vencimento;
- nenhuma chamada de notificação ao solicitante.

Não foram implementados Cron, recorrência, lote, painel ou banco próprio.

## Arquivos necessários

Copiar mantendo a mesma estrutura:

```text
bin/poc-create-ticket.php
src/CliOptions.php
src/HeskBootstrap.php
src/HeskTicketCreator.php
```

Os testes e documentos não são necessários para executar a POC no servidor.

## Instalação pelo Git no cPanel

No terminal do cPanel:

```bash
cd /home/tech2612
git clone --branch task/POC-001-ticket-cli --single-branch https://github.com/jotaCorsino/tickets-recorrentes-hesk.git hesk-recorrencias
cd /home/tech2612/hesk-recorrencias
chmod 750 bin/poc-create-ticket.php
```

Se `/home/tech2612/hesk-recorrencias/` já existir, não executar o `git clone` sobre a pasta. Conferir o conteúdo existente antes de atualizar.

## 1. Validação sem criação

Executar primeiro:

```bash
cd /home/tech2612/hesk-recorrencias
/usr/local/bin/php bin/poc-create-ticket.php --check --hesk-path=/home/tech2612/suporte.technolife.net.br
```

A saída precisa começar com:

```text
CHECK OK
```

e terminar com:

```text
Nenhum ticket foi criado.
```

Conferir na saída:

- HESK `3.7.12`;
- solicitante `21 - Automação Technolife`;
- categoria `5 - WORKSTATION`;
- responsável e autor interno `4 - João Paulo Corsino`;
- prioridade resolvida com o nome `Baixa`;
- status nativo `0` correspondente a Novo;
- `custom7=Presencial`;
- `custom9=WINDOWS`;
- `custom10=REQ_Manutenção preventiva`;
- `custom15=TECHNOLIFE`;
- `custom16` vazio;
- `hesk_newTicket` disponível.

Se a prioridade `Baixa` não for localizada, o comando lista as prioridades encontradas e falha sem criar ticket. Não executar o próximo passo enquanto qualquer validação falhar.

## 2. Criação única

Somente depois de conferir `CHECK OK`, executar uma vez:

```bash
cd /home/tech2612/hesk-recorrencias
/usr/local/bin/php bin/poc-create-ticket.php --execute --hesk-path=/home/tech2612/suporte.technolife.net.br
```

A saída deve informar:

- ID numérico;
- tracking ID novo;
- assunto;
- responsável;
- solicitante;
- confirmação de que a POC não enviou notificação ao solicitante.

Cada nova execução de `--execute` cria outro ticket. A idempotência pertence a uma etapa posterior e não faz parte desta POC.

## Alternativa com variável de ambiente

```bash
cd /home/tech2612/hesk-recorrencias
HESK_PATH=/home/tech2612/suporte.technolife.net.br /usr/local/bin/php bin/poc-create-ticket.php --check
```

## Homologação no HESK

Homologação concluída em 02/10/2026.

O modo `--check` retornou `CHECK OK` no ambiente real e confirmou HESK 3.7.12, solicitante 21, categoria 5, responsável/autor 4, prioridade Baixa (ID 3), status Novo e os campos personalizados da baseline, sem criar ticket.

Uma única execução de `--execute` criou:

- ticket `43`;
- tracking ID `299-RY2-QZ4L`;
- assunto correto;
- solicitante `Automação Technolife`;
- categoria `WORKSTATION`;
- prioridade `Baixa`;
- status `Novo`;
- responsável `João Paulo Corsino`;
- `custom7=Presencial`;
- `custom9=WINDOWS`;
- `custom10=REQ_Manutenção preventiva`;
- `custom15=TECHNOLIFE`;
- `custom16` vazio;
- sem data de vencimento.

A tela do HESK foi conferida visualmente após a criação e os valores correspondem à baseline manual. A POC não invoca `hesk_notifyCustomer()`.
