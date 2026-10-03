<?php

declare(strict_types=1);

namespace TicketsRecorrentesHesk\Web;

final class DemoData
{
    /** @return array<string, int> */
    public static function summary(): array
    {
        return [
            'active' => 8,
            'inactive' => 3,
            'pending' => 2,
            'failed' => 1,
        ];
    }

    /** @return list<array<string, int|string>> */
    public static function recurrences(): array
    {
        return [
            [
                'name' => 'Preventiva de estações',
                'status' => 'Ativa',
                'frequency' => 'A cada 3 meses',
                'next' => '15 out 2026 · 09:00',
                'quantity' => 10,
                'customer' => 'Empresa exemplo',
                'owner' => 'Equipe técnica',
            ],
            [
                'name' => 'Revisão de servidores',
                'status' => 'Ativa',
                'frequency' => 'A cada 1 mês',
                'next' => '30 out 2026 · 08:30',
                'quantity' => 2,
                'customer' => 'Cliente demonstrativo',
                'owner' => 'Responsável exemplo',
            ],
            [
                'name' => 'Inventário de periféricos',
                'status' => 'Inativa',
                'frequency' => 'A cada 1 ano',
                'next' => 'Pausada',
                'quantity' => 4,
                'customer' => 'Empresa exemplo',
                'owner' => 'Equipe técnica',
            ],
        ];
    }

    /** @return list<array<string, int|string>> */
    public static function executions(): array
    {
        return [
            ['id' => 104, 'recurrence' => 'Preventiva de estações', 'scheduled' => '15 out 2026', 'status' => 'pending', 'expected' => 10, 'created' => 0, 'attempts' => 0, 'started' => '—', 'finished' => '—'],
            ['id' => 103, 'recurrence' => 'Revisão de servidores', 'scheduled' => '30 set 2026', 'status' => 'running', 'expected' => 2, 'created' => 1, 'attempts' => 1, 'started' => '30 set · 08:30', 'finished' => '—'],
            ['id' => 102, 'recurrence' => 'Preventiva de estações', 'scheduled' => '15 jul 2026', 'status' => 'succeeded', 'expected' => 10, 'created' => 10, 'attempts' => 1, 'started' => '15 jul · 09:00', 'finished' => '15 jul · 09:02'],
            ['id' => 101, 'recurrence' => 'Inventário de periféricos', 'scheduled' => '02 jul 2026', 'status' => 'failed', 'expected' => 4, 'created' => 0, 'attempts' => 2, 'started' => '02 jul · 10:00', 'finished' => '02 jul · 10:01'],
            ['id' => 100, 'recurrence' => 'Revisão de servidores', 'scheduled' => '30 jun 2026', 'status' => 'partial', 'expected' => 2, 'created' => 1, 'attempts' => 1, 'started' => '30 jun · 08:30', 'finished' => '30 jun · 08:31'],
        ];
    }
}
