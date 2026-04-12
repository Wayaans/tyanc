<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Models\ApprovalRequest;
use BackedEnum;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait InteractsWithApprovals
{
    /**
     * @return MorphMany<ApprovalRequest, $this>
     */
    public function approvalRequests(): MorphMany
    {
        return $this->morphMany(ApprovalRequest::class, 'subject');
    }

    /**
     * @return MorphMany<ApprovalRequest, $this>
     */
    public function approvalHistory(): MorphMany
    {
        return $this->approvalRequests()->latest('requested_at');
    }

    public function approvalSubjectLabel(): string
    {
        $attributes = $this->getAttributes();

        foreach ($this->approvalSubjectLabelAttributes() as $attribute) {
            if (! array_key_exists((string) $attribute, $attributes) && ! in_array($attribute, $this->getFillable(), true)) {
                continue;
            }

            $value = $this->getAttributeValue($attribute);

            if (! is_scalar($value)) {
                continue;
            }

            $label = mb_trim((string) $value);

            if ($label !== '') {
                return $label;
            }
        }

        return class_basename($this);
    }

    /**
     * @return array<string, mixed>
     */
    public function approvalSubjectSnapshot(): array
    {
        $snapshot = [
            'id' => is_scalar($this->getKey()) ? (string) $this->getKey() : null,
        ];

        foreach ($this->approvalSubjectSnapshotFields() as $field) {
            $value = $this->getAttributeValue($field);

            if ($value instanceof BackedEnum) {
                $value = $value->value;
            }

            if ($value === null) {
                continue;
            }

            $snapshot[$field] = is_scalar($value)
                ? $value
                : $value;
        }

        return $snapshot;
    }

    /**
     * @return list<string>
     */
    protected function approvalSubjectLabelAttributes(): array
    {
        return ['label', 'name', 'title', 'file_name', 'key', 'email'];
    }

    /**
     * @return list<string>
     */
    protected function approvalSubjectSnapshotFields(): array
    {
        /** @var list<string> $fields */
        $fields = array_values(collect([
            'key',
            'label',
            'name',
            'title',
            'file_name',
            'email',
            'username',
            'status',
            'enabled',
            'route_prefix',
            'permission_namespace',
            'guard_name',
            'level',
            'type',
            'processed_rows',
            'sort_order',
        ])
            ->filter(fn (string $field): bool => array_key_exists($field, $this->getAttributes()) || in_array($field, $this->getFillable(), true))
            ->values()
            ->all());

        return $fields;
    }
}
