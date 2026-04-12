<?php

declare(strict_types=1);

namespace App\Data\Tyanc\Activity;

use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Activity;
use Spatie\LaravelData\Data;

final class ActivityLogEntryData extends Data
{
    /**
     * @param  array<string, mixed>  $properties
     */
    public function __construct(
        public string $id,
        public string $log_name,
        public string $event,
        public string $description,
        public ?string $subject_type,
        public ?string $subject_id,
        public ?string $subject_name,
        public ?string $causer_id,
        public ?string $causer_name,
        public array $properties,
        public string $created_at,
    ) {}

    public static function fromModel(Activity $activity): self
    {
        $activity->loadMissing('subject', 'causer');

        return new self(
            id: (string) $activity->id,
            log_name: (string) ($activity->log_name ?? 'default'),
            event: (string) ($activity->event ?? $activity->description ?? 'updated'),
            description: (string) $activity->description,
            subject_type: $activity->subject_type,
            subject_id: is_scalar($activity->subject_id) ? (string) $activity->subject_id : null,
            subject_name: self::resolveModelName($activity->subject),
            causer_id: is_scalar($activity->causer_id) ? (string) $activity->causer_id : null,
            causer_name: self::resolveModelName($activity->causer),
            properties: $activity->properties->toArray(),
            created_at: $activity->created_at instanceof CarbonInterface ? $activity->created_at->toIso8601String() : now()->toIso8601String(),
        );
    }

    private static function resolveModelName(?Model $model): ?string
    {
        if ($model instanceof User) {
            return $model->name;
        }

        if (! $model instanceof Model) {
            return null;
        }

        $name = data_get($model, 'name');

        if (is_string($name) && $name !== '') {
            return $name;
        }

        return (string) $model->getKey();
    }
}
