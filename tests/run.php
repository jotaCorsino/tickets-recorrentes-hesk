<?php

declare(strict_types=1);

use TicketsRecorrentesHesk\CliOptions;
use TicketsRecorrentesHesk\HeskTicketCreator;

require dirname(__DIR__) . '/src/CliOptions.php';
require dirname(__DIR__) . '/src/HeskTicketCreator.php';

final class FakeHeskResult
{
    /** @param array<string, mixed>|null $row */
    public function __construct(public ?array $row)
    {
    }
}

/** @return array<string, mixed>|null */
function hesk_get_selectable_customer_by_id(int $id): ?array
{
    return $id === 21
        ? ['id' => 21, 'name' => 'Automação Technolife', 'email' => 'teste@automacao.net.br']
        : null;
}

function hesk_dbEscape(string $value): string
{
    return $value;
}

function hesk_dbQuery(string $sql): FakeHeskResult
{
    if (str_contains($sql, '`hesktx_categories`')) {
        return new FakeHeskResult(['id' => 5, 'name' => 'WORKSTATION', 'type' => '0']);
    }

    if (str_contains($sql, '`hesktx_users`')) {
        return new FakeHeskResult([
            'name' => 'João Paulo Corsino',
            'user' => 'joao',
            'isadmin' => '0',
            'categories' => '',
            'category_access_via_permission_group' => '1',
        ]);
    }

    throw new RuntimeException("SQL inesperado no teste isolado: {$sql}");
}

/** @return array<string, mixed>|false */
function hesk_dbFetchAssoc(FakeHeskResult $result): array|false
{
    $row = $result->row;
    $result->row = null;

    return $row ?? false;
}

function hesk_is_valid_priority_id(int|string $id): bool
{
    return isset($GLOBALS['hesk_settings']['priorities'][(int) $id]);
}

function hesk_is_custom_field_in_category(string $field, int $category): bool
{
    return isset($GLOBALS['hesk_settings']['custom_fields'][$field]) && $category === 5;
}

function hesk_mb_strlen(string $value): int
{
    return strlen($value);
}

function hesk_input(string $value): string
{
    return trim($value);
}

function hesk_date(): string
{
    return '02/10/2026 12:00:00';
}

function hesk_createID(): string
{
    return 'ABC-DEF-G123';
}

function hesk_makeURL(string $value): string
{
    return $value;
}

/**
 * @param array<string, mixed> $ticket
 * @return array<string, mixed>
 */
function hesk_newTicket(array $ticket): array
{
    $GLOBALS['new_ticket_calls']++;
    $GLOBALS['created_ticket_data'] = $ticket;

    return [
        'id' => 43,
        'trackid' => $ticket['trackid'],
        'subject' => $ticket['subject'],
        'owner' => $ticket['owner'],
    ];
}

$failures = [];

$assertSame = static function (mixed $expected, mixed $actual, string $message) use (&$failures): void {
    if ($expected !== $actual) {
        $failures[] = sprintf('%s: esperado %s, recebido %s', $message, var_export($expected, true), var_export($actual, true));
    }
};

$assertThrows = static function (callable $callback, string $expectedMessage, string $message) use (&$failures): void {
    try {
        $callback();
        $failures[] = "{$message}: nenhuma exceção foi lançada";
    } catch (InvalidArgumentException $error) {
        if (!str_contains($error->getMessage(), $expectedMessage)) {
            $failures[] = "{$message}: mensagem inesperada: {$error->getMessage()}";
        }
    }
};

$help = CliOptions::parse(['poc-create-ticket.php'], null);
$assertSame('help', $help->mode, 'Sem argumentos deve mostrar ajuda');

$check = CliOptions::parse(
    ['poc-create-ticket.php', '--check', '--hesk-path=/opt/hesk'],
    null
);
$assertSame('check', $check->mode, 'Modo check');
$assertSame('/opt/hesk', $check->heskPath, 'Caminho por argumento');

$execute = CliOptions::parse(['poc-create-ticket.php', '--execute'], '/srv/hesk');
$assertSame('execute', $execute->mode, 'Modo execute');
$assertSame('/srv/hesk', $execute->heskPath, 'Caminho por variável de ambiente');

$assertThrows(
    static fn (): CliOptions => CliOptions::parse(
        ['poc-create-ticket.php', '--check', '--execute', '--hesk-path=/opt/hesk'],
        null
    ),
    'somente um modo',
    'Modos simultâneos devem falhar'
);

$assertThrows(
    static fn (): CliOptions => CliOptions::parse(['poc-create-ticket.php', '--check'], null),
    'Informe a instalação do HESK',
    'Check sem caminho deve falhar'
);

$assertThrows(
    static fn (): CliOptions => CliOptions::parse(
        ['poc-create-ticket.php', '--check', '--hesk-path=/opt/hesk', '--unknown'],
        null
    ),
    'Opção desconhecida',
    'Opção desconhecida deve falhar'
);

$GLOBALS['hesk_settings'] = [
    'hesk_version' => '3.7.12',
    'db_pfix' => 'hesktx_',
    'staff_ticket_formatting' => 0,
    'priorities' => [
        7 => ['id' => 7, 'name' => 'Baixa'],
    ],
    'statuses' => [
        0 => ['name' => 'Novo'],
    ],
    'custom_fields' => [
        'custom7' => [
            'use' => '1',
            'req' => '2',
            'name' => 'Tipo de atendimento',
            'type' => 'select',
            'value' => ['select_options' => ['Presencial', 'Remoto']],
        ],
        'custom9' => [
            'use' => '1',
            'req' => '2',
            'name' => 'WORKSTATION > Subcategoria',
            'type' => 'select',
            'value' => ['select_options' => ['WINDOWS', 'OFFICE', 'ANTIVIRUS']],
        ],
        'custom10' => [
            'use' => '1',
            'req' => '2',
            'name' => 'WORKSTATION > Problema/Requisição',
            'type' => 'select',
            'value' => ['select_options' => ['REQ_Manutenção preventiva', 'REQ_Instalar programa']],
        ],
        'custom15' => [
            'use' => '1',
            'req' => '2',
            'name' => 'CLIENTE',
            'type' => 'select',
            'value' => ['select_options' => ['TECHNOLIFE', 'EMPRESA X']],
        ],
        'custom16' => [
            'use' => '1',
            'req' => '0',
            'name' => 'PATRIMONIO',
            'type' => 'text',
            'value' => null,
        ],
    ],
];
$GLOBALS['hesklang'] = [
    'thist7' => '[%s] Ticket aberto por %s.',
    'thist2' => '[%s] Atribuído a %s por %s.',
];
$GLOBALS['new_ticket_calls'] = 0;
$GLOBALS['created_ticket_data'] = null;

$definition = [
    'customer_id' => 21,
    'customer_name' => 'Automação Technolife',
    'category' => 5,
    'category_name' => 'WORKSTATION',
    'priority_name' => 'Baixa',
    'status' => 0,
    'owner' => 4,
    'owner_name' => 'João Paulo Corsino',
    'openedby' => 4,
    'openedby_name' => 'João Paulo Corsino',
    'subject' => '[POC] Manutenção preventiva - WORKSTATION',
    'message' => "Chamado de teste para validação da automação de manutenções preventivas.\nNão executar atendimento.",
    'custom_fields' => [
        'custom7' => 'Presencial',
        'custom9' => 'WINDOWS',
        'custom10' => 'REQ_Manutenção preventiva',
        'custom15' => 'TECHNOLIFE',
        'custom16' => '',
    ],
];

$creator = new HeskTicketCreator();
$report = $creator->validate($definition);
$assertSame(0, $GLOBALS['new_ticket_calls'], 'Validate não deve criar ticket');
$assertSame(7, $report['priority']['id'], 'Prioridade deve ser resolvida por nome, não por ID fixo');
$assertSame('TECHNOLIFE', $report['custom_fields']['custom15']['value'], 'Cliente deve ser validado nas opções');

$created = $creator->create($definition);
$payload = $GLOBALS['created_ticket_data'];
$assertSame(1, $GLOBALS['new_ticket_calls'], 'Execute deve criar exatamente um ticket');
$assertSame(43, $created['id'], 'ID retornado');
$assertSame('ABC-DEF-G123', $created['trackid'], 'Tracking ID deve vir do HESK');
$assertSame(7, $payload['priority'], 'Payload deve usar prioridade resolvida');
$assertSame(4, $payload['owner'], 'Payload deve manter owner');
$assertSame(4, $payload['openedby'], 'Payload deve manter openedby');
$assertSame(4, $payload['assignedby'], 'Payload deve manter assignedby');
$assertSame([], $payload['follower_ids'], 'Payload não deve ter seguidores');
$assertSame('', $payload['attachments'], 'Payload não deve ter anexos');
$assertSame('', $payload['due_date'], 'Payload não deve ter vencimento');
$assertSame('', $payload['custom16'], 'Patrimônio deve permanecer vazio');

if ($failures !== []) {
    fwrite(STDERR, "TESTES FALHARAM\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "TESTES OK\n";
