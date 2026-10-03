<?php

declare(strict_types=1);

namespace TicketsRecorrentesHesk\Web;

final class AdminUi
{
    private const ROUTES = [
        'overview' => [
            'view' => 'overview.php',
            'nav' => 'overview',
            'heading' => 'Visão geral',
            'intro' => 'Um ponto de partida para acompanhar agendas e execuções.',
        ],
        'recurrences' => [
            'view' => 'recurrences.php',
            'nav' => 'recurrences',
            'heading' => 'Recorrências',
            'intro' => 'Organize o que deve ser criado e quando cada agenda será executada.',
        ],
        'recurrence-form' => [
            'view' => 'recurrence-form.php',
            'nav' => 'recurrences',
            'heading' => 'Nova recorrência',
            'intro' => 'Estrutura visual do cadastro. O salvamento chegará na próxima etapa.',
        ],
        'executions' => [
            'view' => 'executions.php',
            'nav' => 'executions',
            'heading' => 'Execuções',
            'intro' => 'Acompanhe competências, tentativas e resultados de cada lote.',
        ],
        'system' => [
            'view' => 'system.php',
            'nav' => 'system',
            'heading' => 'Sistema',
            'intro' => 'Informações seguras da prévia administrativa.',
        ],
    ];

    public function __construct(private readonly bool $enabled)
    {
    }

    /** @return array{status: int, content_type: string, body: string} */
    public function respond(string $page, string $mode = 'new', string $method = 'GET'): array
    {
        if (!$this->enabled) {
            return $this->unavailable();
        }

        if ($method !== 'GET') {
            return [
                'status' => 405,
                'content_type' => 'text/plain; charset=UTF-8',
                'body' => 'A gravação ainda não está disponível.',
            ];
        }

        $route = self::ROUTES[$page] ?? null;

        if ($route === null || ($page === 'recurrence-form' && !in_array($mode, ['new', 'view', 'edit'], true))) {
            return $this->unavailable();
        }

        if ($page === 'recurrence-form') {
            $route['heading'] = match ($mode) {
                'view' => 'Visualizar recorrência',
                'edit' => 'Editar recorrência',
                default => 'Nova recorrência',
            };
        }

        $activeNav = $route['nav'];
        $heading = $route['heading'];
        $intro = $route['intro'];
        $view = __DIR__ . '/views/pages/' . $route['view'];
        $recurrences = DemoData::recurrences();
        $executions = DemoData::executions();
        $summary = DemoData::summary();
        $escape = static fn (string|int $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        ob_start();
        require __DIR__ . '/views/layout.php';
        $html = ob_get_clean();

        return [
            'status' => 200,
            'content_type' => 'text/html; charset=UTF-8',
            'body' => $html === false ? '' : $html,
        ];
    }

    /** @return array{status: int, content_type: string, body: string} */
    private function unavailable(): array
    {
        return [
            'status' => 404,
            'content_type' => 'text/plain; charset=UTF-8',
            'body' => 'Página indisponível.',
        ];
    }
}
