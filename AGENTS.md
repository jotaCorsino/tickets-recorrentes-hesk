# AGENTS.md

## Propósito

Este arquivo define como agentes de implementação devem trabalhar neste repositório.

## Regra principal

O projeto automatiza a criação de tickets recorrentes no HESK OSS da Technolife sem substituir o HESK e sem alterar seu core desnecessariamente.

## Fonte de verdade

Antes de qualquer alteração, leia nesta ordem:

1. `README.md`
2. `docs/01-LEVANTAMENTO-HESK.md`
3. `docs/02-ARQUITETURA.md`
4. `docs/03-ROADMAP.md`
5. `docs/04-DECISOES-TECNICAS.md`
6. documentação específica da tarefa, se existir

## Responsabilidades do Codex

O Codex é o engenheiro de implementação. Em cada tarefa deve:

- inspecionar o estado atual antes de alterar;
- respeitar o escopo da tarefa;
- preservar compatibilidade com HESK 3.7.12 e PHP 8.2;
- preferir funções e fluxos nativos do HESK;
- não editar arquivos do core do HESK como solução permanente;
- não inserir tickets diretamente com SQL;
- implementar proteção contra duplicidade quando aplicável;
- produzir logs e erros úteis para operação;
- testar antes de concluir;
- atualizar documentação afetada;
- manter commits pequenos e rastreáveis.

## Fluxo Git obrigatório

1. Sincronizar `main`.
2. Criar uma branch específica.
3. Implementar apenas a tarefa atual.
4. Validar.
5. Atualizar documentação/status.
6. Commitar.
7. Push.
8. Abrir ou atualizar PR.
9. Reportar:
   - branch;
   - commit;
   - PR;
   - arquivos alterados;
   - testes/validações;
   - riscos ou pendências.
10. Parar para homologação.

## Status

- `PENDENTE`
- `EM_ANDAMENTO`
- `AGUARDANDO_HOMOLOGACAO`
- `CONCLUÍDO`
- `BLOQUEADO`

Nunca marcar como `CONCLUÍDO` antes da homologação quando a tarefa exigir teste operacional no HESK.

## Restrições arquiteturais

Não fazer:

- `INSERT` direto em `hesktx_tickets` como mecanismo principal;
- alterações no core do HESK sem decisão documentada;
- dependência de sessão web humana para execução pelo Cron;
- duplicar regras do HESK sem necessidade;
- hardcode de empresa, técnico, frequência ou volume;
- armazenar credenciais no Git;
- enviar notificações ao cliente externo por padrão.

Preferir:

- bootstrap controlado do ambiente HESK;
- funções internas estáveis já usadas pelo próprio HESK;
- configuração persistente;
- idempotência;
- logs;
- validação de IDs e opções antes da criação;
- execução via CLI/Cron.

## Segurança

Nunca versionar:

- `hesk_settings.inc.php`;
- senhas;
- tokens;
- credenciais de banco;
- backups com dados reais;
- exportações contendo dados pessoais desnecessários.

## Critério de qualidade

Uma tarefa só pode ir para `AGUARDANDO_HOMOLOGACAO` quando:

- escopo implementado;
- testes aplicáveis executados;
- erros conhecidos registrados;
- documentação atualizada;
- árvore Git limpa;
- branch publicada;
- PR disponível quando solicitado pelo fluxo.
