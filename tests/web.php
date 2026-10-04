<?php

declare(strict_types=1);

$failures = [];
$assertions = 0;

$assert = static function (bool $condition, string $message) use (&$failures, &$assertions): void {
    $assertions++;

    if (!$condition) {
        $failures[] = $message;
    }
};

/** @return array{status: int, body: string} */
$request = static function (?string $page, ?string $mode = null, ?string $enabled = '1', string $method = 'GET', ?string $exampleId = null): array {
    if ($enabled === null) {
        putenv('ADMIN_UI_ENABLED');
    } else {
        putenv('ADMIN_UI_ENABLED=' . $enabled);
    }

    $_GET = [];

    if ($page !== null) {
        $_GET['page'] = $page;
    }

    if ($mode !== null) {
        $_GET['mode'] = $mode;
    }

    if ($exampleId !== null) {
        $_GET['id'] = $exampleId;
    }

    $_SERVER['REQUEST_METHOD'] = $method;
    http_response_code(200);
    ob_start();
    require dirname(__DIR__) . '/public/index.php';
    $body = ob_get_clean();

    return ['status' => http_response_code(), 'body' => $body === false ? '' : $body];
};

$assertStructure = static function (string $html, string $pageName) use ($assert): void {
    $assert(str_starts_with(ltrim($html), '<!doctype html>'), "{$pageName}: doctype HTML5 ausente");
    $assert(substr_count($html, '<html ') === 1 && substr_count($html, '</html>') === 1, "{$pageName}: elemento html inválido");
    $assert(substr_count($html, '<head>') === 1 && substr_count($html, '</head>') === 1, "{$pageName}: head inválido");
    $assert(substr_count($html, '<body>') === 1 && substr_count($html, '</body>') === 1, "{$pageName}: body inválido");
    $assert(substr_count($html, '<main ') === 1 && substr_count($html, '</main>') === 1, "{$pageName}: main inválido");

    preg_match_all('~</?[a-z][a-z0-9-]*(?:\s[^<>]*?)?\s*/?>~i', $html, $matches);
    $stack = [];
    $voidElements = ['area', 'base', 'br', 'col', 'embed', 'hr', 'img', 'input', 'link', 'meta', 'param', 'source', 'track', 'wbr'];
    $balanced = true;

    foreach ($matches[0] as $tag) {
        if (!preg_match('~^</?([a-z][a-z0-9-]*)~i', $tag, $nameMatch)) {
            continue;
        }

        $name = strtolower($nameMatch[1]);

        if (str_starts_with($tag, '</')) {
            if (array_pop($stack) !== $name) {
                $balanced = false;
                break;
            }
        } elseif (!in_array($name, $voidElements, true) && !str_ends_with($tag, '/>')) {
            $stack[] = $name;
        }
    }

    $assert($balanced && $stack === [], "{$pageName}: tags HTML desbalanceadas");

    preg_match_all('/\bid="([^"]+)"/', $html, $ids);
    $assert(count($ids[1]) === count(array_unique($ids[1])), "{$pageName}: IDs HTML duplicados");
    preg_match_all('/<label\b[^>]*\bfor="([^"]+)"/', $html, $labels);

    foreach ($labels[1] as $id) {
        $assert(in_array($id, $ids[1], true), "{$pageName}: label sem controle {$id}");
    }
};

$blocked = $request(null, enabled: null);
$assert($blocked['status'] === 404, 'Painel sem ADMIN_UI_ENABLED deve retornar 404');
$assert(!str_contains($blocked['body'], '<html'), 'Painel bloqueado não deve renderizar a UI');
$disabled = $request('overview', enabled: '0');
$assert($disabled['status'] === 404, 'ADMIN_UI_ENABLED=0 deve manter o bloqueio');

$routes = [
    'recurrences' => 'Recorrências',
    'executions' => 'Execuções',
    'system' => 'Sistema',
];

foreach ($routes as $route => $heading) {
    $response = $request($route);
    $assert($response['status'] === 200, "Rota {$route} deve responder 200");
    $assert(str_contains($response['body'], '<h1>' . $heading . '</h1>'), "Rota {$route} deve mostrar o título correto");
    $assert(str_contains($response['body'], 'Dados de exemplo'), "Rota {$route} deve identificar dados de prévia");
    $assertStructure($response['body'], $route);
    $assert(!str_contains($response['body'], 'hesk_settings.inc.php') && !str_contains($response['body'], '/home/tech2612/') && !str_contains($response['body'], 'DB_PASSWORD'), "Rota {$route} não deve exibir segredos ou caminhos privados");
}

$home = $request(null);
$assert($home['status'] === 200 && str_contains($home['body'], '<h1>Recorrências</h1>'), 'Rota inicial deve abrir as recorrências');
$assert(!str_contains($home['body'], 'page=overview'), 'Navegação não deve repetir a listagem na visão geral');
$legacyOverview = $request('overview');
$assert($legacyOverview['status'] === 200 && str_contains($legacyOverview['body'], '<h1>Recorrências</h1>'), 'Link antigo da visão geral deve mostrar a listagem');

$list = $request('recurrences');
$assert(str_contains($list['body'], 'Nova recorrência'), 'Listagem deve oferecer nova recorrência');
$assert(str_contains($list['body'], 'mode=view') && str_contains($list['body'], 'mode=edit'), 'Ações visuais devem navegar para visualização e edição');
$assert(substr_count($list['body'], '>Editar</a>') === 3, 'Cada modelo deve oferecer edição direta');
$assert(str_contains($list['body'], 'mode=edit&amp;id=1') && str_contains($list['body'], 'mode=edit&amp;id=2') && str_contains($list['body'], 'mode=edit&amp;id=3'), 'A edição deve identificar o modelo selecionado');

foreach (['new', 'view', 'edit'] as $mode) {
    $form = $request('recurrence-form', $mode);
    $assert($form['status'] === 200, "Formulário {$mode} deve responder 200");
    $assertStructure($form['body'], "formulário {$mode}");
    $assert(str_contains($form['body'], 'name="notify_customer" type="checkbox" value="1" disabled'), "Formulário {$mode} deve impedir notificação ao solicitante");
    $assert(str_contains($form['body'], 'type="submit" disabled'), "Formulário {$mode} não deve salvar nesta etapa");
    foreach (['custom7', 'custom9', 'custom10', 'custom15', 'custom16'] as $field) {
        $assert(str_contains($form['body'], 'name="custom_fields[' . $field . ']"'), "Formulário {$mode} deve representar {$field}");
    }
}

$secondExample = $request('recurrence-form', 'edit', exampleId: '2');
$assert(str_contains($secondExample['body'], 'value="Revisão de servidores"'), 'Edição deve mostrar o nome do modelo selecionado');
$assert(str_contains($secondExample['body'], '<option value="3" selected>SERVER</option>'), 'Edição deve mostrar a categoria do modelo selecionado');
$missingExample = $request('recurrence-form', 'edit', exampleId: '999');
$assert($missingExample['status'] === 404, 'Modelo demonstrativo desconhecido deve retornar 404');

$executions = $request('executions');
foreach (['pending', 'running', 'succeeded', 'failed', 'partial'] as $status) {
    $assert(str_contains($executions['body'], 'badge--' . $status), "Execuções devem mostrar badge {$status}");
}
$assert(str_contains($executions['body'], '<summary>Ver detalhes</summary>'), 'Detalhes devem abrir por teclado e mouse');

$system = $request('system');
$assert(str_contains($system['body'], PHP_VERSION), 'Sistema deve mostrar a versão atual do PHP');
$assert(str_contains($system['body'], 'Não conectado') && str_contains($system['body'], 'Não consultado'), 'Sistema não deve simular conexão com banco ou migrations');

$unknown = $request('does-not-exist');
$assert($unknown['status'] === 404, 'Rota desconhecida deve retornar 404');
$invalidMode = $request('recurrence-form', 'delete');
$assert($invalidMode['status'] === 404, 'Modo desconhecido deve retornar 404');
$post = $request('recurrence-form', method: 'POST');
$assert($post['status'] === 405, 'POST deve ser recusado enquanto não houver gravação');

putenv('ADMIN_UI_ENABLED');

if ($failures !== []) {
    fwrite(STDERR, "TESTES UI-001A FALHARAM\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "TESTES UI-001A OK ({$assertions} asserções)\n";
