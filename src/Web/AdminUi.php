<?php

declare(strict_types=1);

namespace TicketsRecorrentesHesk\Web;

final class AdminUi
{
    private const ROUTES = [
        'recurrences' => [
            'view' => 'recurrences.php',
            'nav' => 'recurrences',
            'heading' => 'Recorrências',
            'intro' => 'Modelos de tickets programados.',
        ],
        'recurrence-form' => [
            'view' => 'recurrence-form.php',
            'nav' => 'recurrences',
            'heading' => 'Nova recorrência',
            'intro' => 'Campos do modelo, sem salvamento nesta prévia.',
        ],
        'executions' => [
            'view' => 'executions.php',
            'nav' => 'executions',
            'heading' => 'Execuções',
            'intro' => 'Acompanhe os lotes processados.',
        ],
        'system' => [
            'view' => 'system.php',
            'nav' => 'system',
            'heading' => 'Sistema',
            'intro' => 'Informações da prévia.',
        ],
    ];

    public function __construct(private readonly bool $enabled)
    {
    }

    /** @return array{status: int, content_type: string, body: string} */
    public function respond(string $page, string $mode = 'new', string $method = 'GET', ?string $exampleId = null): array
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

        // Preserve old preview links while keeping recurrences as the only landing page.
        if ($page === 'overview') {
            $page = 'recurrences';
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
        $example = null;

        if ($page === 'recurrence-form' && $mode !== 'new') {
            $exampleId ??= '1';

            foreach ($recurrences as $recurrence) {
                if ($exampleId === (string) $recurrence['id']) {
                    $example = $recurrence;
                    break;
                }
            }

            if ($example === null) {
                return $this->unavailable();
            }
        }
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
