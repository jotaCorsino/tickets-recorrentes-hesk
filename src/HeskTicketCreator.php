<?php

declare(strict_types=1);

namespace TicketsRecorrentesHesk;

use DomainException;
use RuntimeException;

final class HeskTicketCreator
{
    /**
     * @param array<string, mixed> $definition
     * @return array<string, mixed>
     */
    public function validate(array $definition): array
    {
        global $hesk_settings;

        $errors = [];

        if (!function_exists('hesk_newTicket')) {
            $errors[] = 'hesk_newTicket() não está disponível.';
        }

        $customerId = $this->positiveInteger($definition, 'customer_id', $errors);
        $categoryId = $this->positiveInteger($definition, 'category', $errors);
        $ownerId = $this->positiveInteger($definition, 'owner', $errors);
        $openedById = $this->positiveInteger($definition, 'openedby', $errors);

        $customer = $customerId > 0 ? \hesk_get_selectable_customer_by_id($customerId) : null;

        if ($customer === null) {
            $errors[] = "Solicitante {$customerId} não existe ou não pode ser selecionado para um ticket.";
        } elseif (!$this->sameLabel((string) $customer['name'], (string) $definition['customer_name'])) {
            $errors[] = sprintf(
                'Solicitante %d divergente: encontrado "%s"; esperado "%s".',
                $customerId,
                $customer['name'],
                $definition['customer_name']
            );
        }

        $category = $categoryId > 0 ? $this->findCategory($categoryId) : null;

        if ($category === null) {
            $errors[] = "Categoria {$categoryId} não encontrada.";
        } elseif (!$this->sameLabel((string) $category['name'], (string) $definition['category_name'])) {
            $errors[] = sprintf(
                'Categoria %d divergente: encontrada "%s"; esperada "%s".',
                $categoryId,
                $category['name'],
                $definition['category_name']
            );
        }

        $owner = $ownerId > 0 && $categoryId > 0
            ? $this->findStaffWithCategoryAccess($ownerId, $categoryId)
            : null;

        if ($owner === null) {
            $errors[] = "Responsável {$ownerId} não está ativo ou não possui acesso à categoria {$categoryId}.";
        } elseif (!$this->sameLabel((string) $owner['name'], (string) $definition['owner_name'])) {
            $errors[] = sprintf(
                'Responsável %d divergente: encontrado "%s"; esperado "%s".',
                $ownerId,
                $owner['name'],
                $definition['owner_name']
            );
        }

        $openedBy = $openedById === $ownerId
            ? $owner
            : ($openedById > 0 && $categoryId > 0
                ? $this->findStaffWithCategoryAccess($openedById, $categoryId)
                : null);

        if ($openedBy === null) {
            $errors[] = "Autor interno {$openedById} não está ativo ou não possui acesso à categoria {$categoryId}.";
        } elseif (!$this->sameLabel((string) $openedBy['name'], (string) $definition['openedby_name'])) {
            $errors[] = sprintf(
                'Autor interno %d divergente: encontrado "%s"; esperado "%s".',
                $openedById,
                $openedBy['name'],
                $definition['openedby_name']
            );
        }

        $priority = $this->resolvePriority((string) $definition['priority_name']);

        if ($priority === null) {
            $available = [];

            foreach (($hesk_settings['priorities'] ?? []) as $id => $candidate) {
                $available[] = sprintf('%s=%s', $id, $candidate['name'] ?? 'sem nome');
            }

            $errors[] = sprintf(
                'Prioridade "%s" não encontrada. Valores disponíveis no HESK: %s.',
                $definition['priority_name'],
                $available === [] ? 'nenhum' : implode(', ', $available)
            );
        }

        $statusId = isset($definition['status']) && is_int($definition['status'])
            ? $definition['status']
            : -1;
        $status = $hesk_settings['statuses'][$statusId] ?? null;

        if ($status === null) {
            $errors[] = "Status {$statusId} não está disponível no HESK.";
        }

        $subject = isset($definition['subject']) && is_string($definition['subject'])
            ? trim($definition['subject'])
            : '';
        $message = isset($definition['message']) && is_string($definition['message'])
            ? trim($definition['message'])
            : '';

        if ($subject === '') {
            $errors[] = 'O assunto da baseline está vazio.';
        } elseif (\hesk_mb_strlen($subject) > 255) {
            $errors[] = 'O assunto da baseline excede 255 caracteres.';
        }

        if ($message === '') {
            $errors[] = 'A mensagem da baseline está vazia.';
        }

        if (($hesk_settings['staff_ticket_formatting'] ?? 0) === 2 && !class_exists('DOMDocument')) {
            $errors[] = 'O modo de formatação HTML do HESK exige a extensão DOM do PHP.';
        }

        $customFields = $this->validateCustomFields(
            $definition['custom_fields'] ?? null,
            $categoryId,
            $errors
        );

        if ($errors !== []) {
            throw new DomainException("Validação da POC falhou:\n- " . implode("\n- ", $errors));
        }

        return [
            'hesk_version' => (string) ($hesk_settings['hesk_version'] ?? 'desconhecida'),
            'hesk_new_ticket' => true,
            'customer' => [
                'id' => $customerId,
                'name' => (string) $customer['name'],
            ],
            'category' => [
                'id' => $categoryId,
                'name' => (string) $category['name'],
            ],
            'owner' => [
                'id' => $ownerId,
                'name' => (string) $owner['name'],
                'user' => (string) $owner['user'],
            ],
            'openedby' => [
                'id' => $openedById,
                'name' => (string) $openedBy['name'],
                'user' => (string) $openedBy['user'],
            ],
            'priority' => $priority,
            'status' => [
                'id' => $statusId,
                'name' => (string) $status['name'],
            ],
            'custom_fields' => $customFields,
        ];
    }

    /**
     * @param array<string, mixed> $definition
     * @return array{id: int, trackid: string, subject: string, owner: int, customer_id: int}
     */
    public function create(array $definition): array
    {
        global $hesk_settings, $hesklang;

        $validation = $this->validate($definition);
        $openedBy = $validation['openedby'];
        $owner = $validation['owner'];

        $history = sprintf(
            $hesklang['thist7'],
            \hesk_date(),
            addslashes($openedBy['name']) . ' (' . $openedBy['user'] . ')'
        );
        $history .= sprintf(
            $hesklang['thist2'],
            \hesk_date(),
            addslashes($owner['name']) . ' (' . $owner['user'] . ')',
            addslashes($openedBy['name']) . ' (' . $openedBy['user'] . ')'
        );

        [$message, $messageHtml] = $this->prepareMessage((string) $definition['message']);

        $ticketData = [
            'customer_id' => $validation['customer']['id'],
            'follower_ids' => [],
            'category' => $validation['category']['id'],
            'priority' => $validation['priority']['id'],
            'status' => $validation['status']['id'],
            'subject' => \hesk_input((string) $definition['subject']),
            'message' => $message,
            'message_html' => $messageHtml,
            'trackid' => \hesk_createID(),
            'history' => $history,
            'openedby' => $validation['openedby']['id'],
            'owner' => $validation['owner']['id'],
            'assignedby' => $validation['openedby']['id'],
            'attachments' => '',
            'due_date' => '',
        ];

        // hesk_newTicket() devolve todos os campos ativos no resultado; por isso
        // cada campo carregado pelo HESK precisa existir no payload.
        foreach (($hesk_settings['custom_fields'] ?? []) as $key => $_field) {
            $ticketData[$key] = '';
        }

        foreach ($validation['custom_fields'] as $key => $field) {
            $ticketData[$key] = $this->prepareCustomFieldValue((string) $field['value']);
        }

        // Replica o fluxo administrativo: tickets abertos por staff não registram
        // o IP da estação/servidor que executou o comando.
        $hesk_settings['client_IP'] = '';

        $created = \hesk_newTicket($ticketData);

        if (!is_array($created) || empty($created['id']) || empty($created['trackid'])) {
            throw new RuntimeException('hesk_newTicket() não retornou a identificação do ticket criado.');
        }

        return [
            'id' => (int) $created['id'],
            'trackid' => (string) $created['trackid'],
            'subject' => (string) $created['subject'],
            'owner' => (int) $created['owner'],
            'customer_id' => (int) $ticketData['customer_id'],
        ];
    }

    /**
     * @param array<string, mixed> $definition
     * @param list<string> $errors
     */
    private function positiveInteger(array $definition, string $key, array &$errors): int
    {
        $value = $definition[$key] ?? null;

        if (!is_int($value) || $value < 1) {
            $errors[] = "{$key} deve ser um inteiro positivo.";
            return 0;
        }

        return $value;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findCategory(int $categoryId): ?array
    {
        global $hesk_settings;

        $prefix = \hesk_dbEscape($hesk_settings['db_pfix']);
        $result = \hesk_dbQuery(
            "SELECT `id`, `name`, `type` FROM `{$prefix}categories` WHERE `id`={$categoryId} LIMIT 1"
        );

        $category = \hesk_dbFetchAssoc($result);

        return is_array($category) ? $category : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findStaffWithCategoryAccess(int $staffId, int $categoryId): ?array
    {
        global $hesk_settings;

        $prefix = \hesk_dbEscape($hesk_settings['db_pfix']);
        $result = \hesk_dbQuery(
            "SELECT `name`, `user`, `isadmin`, `categories`,
                CASE WHEN EXISTS (
                    SELECT 1 FROM `{$prefix}permission_group_categories` AS `inner_category`
                    INNER JOIN `{$prefix}permission_group_members` AS `inner_member`
                        ON `inner_category`.`group_id` = `inner_member`.`group_id`
                    WHERE `inner_member`.`user_id` = {$staffId}
                        AND `inner_category`.`category_id` = {$categoryId}
                ) THEN 1 ELSE 0 END AS `category_access_via_permission_group`
            FROM `{$prefix}users`
            WHERE `id`={$staffId} AND `active`=1
            LIMIT 1"
        );

        $staff = \hesk_dbFetchAssoc($result);

        if (!is_array($staff)) {
            return null;
        }

        if ((int) $staff['isadmin'] === 1 || (int) $staff['category_access_via_permission_group'] === 1) {
            return $staff;
        }

        $directCategories = array_filter(array_map('intval', explode(',', (string) $staff['categories'])));

        return in_array($categoryId, $directCategories, true) ? $staff : null;
    }

    /**
     * @return array{id: int, name: string}|null
     */
    private function resolvePriority(string $expectedName): ?array
    {
        global $hesk_settings;

        $matches = [];

        foreach (($hesk_settings['priorities'] ?? []) as $id => $priority) {
            $name = (string) ($priority['name'] ?? '');

            if ($this->sameLabel($name, $expectedName) && \hesk_is_valid_priority_id((string) $id)) {
                $matches[] = ['id' => (int) $id, 'name' => $name];
            }
        }

        return count($matches) === 1 ? $matches[0] : null;
    }

    /**
     * @param mixed $configuredFields
     * @param list<string> $errors
     * @return array<string, array{name: string, type: string, value: string}>
     */
    private function validateCustomFields(mixed $configuredFields, int $categoryId, array &$errors): array
    {
        global $hesk_settings;

        if (!is_array($configuredFields)) {
            $errors[] = 'A configuração de campos personalizados é inválida.';
            return [];
        }

        $validated = [];

        foreach ($configuredFields as $key => $value) {
            if (!is_string($key) || !preg_match('/^custom(?:[1-9]|[1-9][0-9]|100)$/', $key)) {
                $errors[] = 'Identificador de campo personalizado inválido.';
                continue;
            }

            if (!is_string($value)) {
                $errors[] = "Valor de {$key} deve ser texto.";
                continue;
            }

            $field = $hesk_settings['custom_fields'][$key] ?? null;

            if (!is_array($field) || empty($field['use'])) {
                $errors[] = "{$key} não está ativo no HESK.";
                continue;
            }

            if (!\hesk_is_custom_field_in_category($key, $categoryId)) {
                $errors[] = "{$key} não pertence à categoria {$categoryId}.";
                continue;
            }

            if ((int) ($field['req'] ?? 0) === 2 && trim($value) === '') {
                $errors[] = "{$key} ({$field['name']}) é obrigatório e está vazio.";
                continue;
            }

            $type = (string) ($field['type'] ?? '');
            $supportedTypes = ['text', 'textarea', 'radio', 'select'];

            if (!in_array($type, $supportedTypes, true)) {
                $errors[] = "{$key} usa o tipo não suportado nesta POC: {$type}.";
                continue;
            }

            $canonicalValue = $value;
            $optionKey = $type === 'select' ? 'select_options' : ($type === 'radio' ? 'radio_options' : null);

            if ($optionKey !== null && trim($value) !== '') {
                $options = $field['value'][$optionKey] ?? [];
                $canonicalValue = $this->findCanonicalOption($value, is_array($options) ? $options : []);

                if ($canonicalValue === null) {
                    $printableOptions = array_map(static fn (mixed $option): string => (string) $option, $options);
                    $errors[] = sprintf(
                        '%s (%s) não aceita "%s". Opções encontradas: %s.',
                        $key,
                        $field['name'],
                        $value,
                        $printableOptions === [] ? 'nenhuma' : implode(', ', $printableOptions)
                    );
                    continue;
                }
            }

            $validated[$key] = [
                'name' => (string) $field['name'],
                'type' => $type,
                'value' => $canonicalValue,
            ];
        }

        return $validated;
    }

    /**
     * @param list<mixed> $options
     */
    private function findCanonicalOption(string $expected, array $options): ?string
    {
        foreach ($options as $option) {
            if (is_string($option) && $this->sameLabel(trim($option), trim($expected))) {
                return $option;
            }
        }

        return null;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function prepareMessage(string $rawMessage): array
    {
        global $hesk_settings;

        $message = \hesk_input($rawMessage);
        $messageHtml = $message;

        if ((int) ($hesk_settings['staff_ticket_formatting'] ?? 0) === 2) {
            $messageHtml = \hesk_html_entity_decode($messageHtml);

            require_once HESK_PATH . 'inc/htmlpurifier/HeskHTMLPurifier.php';
            require_once HESK_PATH . 'inc/html2text/html2text.php';

            $purifier = new \HeskHTMLPurifier($hesk_settings['cache_dir']);
            $messageHtml = $purifier->heskPurify($messageHtml);
            $message = \convert_html_to_text($messageHtml);
            $message = \fix_newlines($message);
            $message = nl2br(\hesk_htmlspecialchars($message));
        } else {
            $message = nl2br(\hesk_makeURL($message));
            $messageHtml = $message;
        }

        return [$message, $messageHtml];
    }

    private function prepareCustomFieldValue(string $value): string
    {
        return \hesk_makeURL(nl2br(\hesk_input($value)));
    }

    private function sameLabel(string $actual, string $expected): bool
    {
        $normalize = static function (string $value): string {
            $value = preg_replace('/\s+/u', ' ', trim($value)) ?? trim($value);

            return function_exists('mb_strtolower')
                ? mb_strtolower($value, 'UTF-8')
                : strtolower($value);
        };

        return $normalize($actual) === $normalize($expected);
    }
}
