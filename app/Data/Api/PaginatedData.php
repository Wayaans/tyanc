<?php

declare(strict_types=1);

namespace App\Data\Api;

use App\Data\Tables\DataTableQueryData;
use Spatie\LaravelData\Data;

final class PaginatedData extends Data
{
    /**
     * @param  list<mixed>  $data
     * @param  array<string, mixed>  $meta
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public array $data,
        public array $meta,
        public array $context = [],
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $context
     */
    public static function fromTablePayload(array $payload, array $context = []): self
    {
        $query = $payload['query'] ?? null;

        if ($query instanceof DataTableQueryData) {
            $context['query'] = $query->toArray();
        } elseif (is_array($query)) {
            $context['query'] = $query;
        }

        if (is_array($payload['filters'] ?? null)) {
            $context['filters'] = $payload['filters'];
        }

        if (is_array($payload['summary'] ?? null)) {
            $context['summary'] = $payload['summary'];
        }

        return new self(
            data: array_values((array) ($payload['rows'] ?? $payload['data'] ?? [])),
            meta: is_array($payload['meta'] ?? null) ? $payload['meta'] : [],
            context: array_filter(
                $context,
                fn (mixed $value): bool => $value !== null && $value !== [],
            ),
        );
    }
}
