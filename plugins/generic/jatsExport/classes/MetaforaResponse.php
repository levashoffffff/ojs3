<?php

/**
 * @file classes/MetaforaResponse.php
 *
 * @class MetaforaResponse
 *
 * @brief Result of one Metafora API call, with Metafora's error payloads
 * (ErrorResponse / ValidationErrorResponse) turned into readable text.
 */

namespace APP\plugins\generic\jatsExport\classes;

class MetaforaResponse
{
    public function __construct(
        public int $status,
        public ?array $json,
        public string $raw,
        protected ?string $transportError = null,
    ) {
    }

    public function ok(): bool
    {
        return $this->status >= 200 && $this->status < 300;
    }

    /** `data` part of a success or error response */
    public function data(): array
    {
        return (array) ($this->json['data'] ?? []);
    }

    /** Machine error code, e.g. XML_ALREADY_EXISTS, SCHEMA_ERROR */
    public function errorCode(): ?string
    {
        return $this->json['error'] ?? null;
    }

    /**
     * Human-readable error: message plus XSD lines, missing-data list or
     * per-field validation messages, whichever Metafora sent.
     */
    public function errorText(): string
    {
        if ($this->transportError) {
            return $this->transportError;
        }
        $parts = [];
        if (!empty($this->json['message'])) {
            $parts[] = $this->json['message'];
        }
        $data = $this->data();
        if (!empty($data['description'])) {
            $parts[] = trim($data['description']);
        }
        if (!empty($this->json['description'])) {
            $parts[] = trim($this->json['description']);
        }
        if (!empty($data['errors'])) {
            $parts[] = implode("\n", (array) $data['errors']);
        }
        foreach ((array) ($this->json['errors'] ?? []) as $field => $messages) {
            $parts[] = $field . ': ' . implode('; ', (array) $messages);
        }
        if (!$parts) {
            $parts[] = match ($this->status) {
                401 => 'API-ключ неактивен',
                403 => 'Нет доступа: неверный API-ключ или нет прав на журнал',
                404 => 'Не найдено',
                default => 'HTTP ' . $this->status . ($this->raw !== '' ? ': ' . mb_substr($this->raw, 0, 300) : ''),
            };
        }
        return implode("\n", array_unique($parts));
    }
}
