<?php

declare(strict_types=1);

namespace App\Imports;

use App\Actions\CreateUser;
use App\Actions\UpdateUser;
use App\Enums\UserStatus;
use App\Models\ImportRun;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

final readonly class UsersImport implements SkipsEmptyRows, ToCollection, WithChunkReading, WithHeadingRow
{
    public function __construct(private string $importRunId) {}

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     */
    public function collection(Collection $rows): void
    {
        foreach ($rows as $row) {
            $attributes = $this->normalizeRow($row);

            if (($attributes['email'] ?? null) === null) {
                continue;
            }

            DB::transaction(function () use ($attributes): void {
                $user = User::query()
                    ->withTrashed()
                    ->firstWhere('email', $attributes['email']);

                if ($user instanceof User) {
                    if ($user->trashed()) {
                        $user->restore();
                    }

                    resolve(UpdateUser::class)->handle($user, $attributes);
                } else {
                    resolve(CreateUser::class)->handle($attributes, Str::random(32));
                }
            });

            ImportRun::query()
                ->whereKey($this->importRunId)
                ->increment('processed_rows');
        }
    }

    public function chunkSize(): int
    {
        return 200;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, string>
     */
    private function normalizeRow(array $row): array
    {
        $email = $this->nullableString($row['email'] ?? null);
        $statusValue = UserStatus::tryFrom($this->nullableString($row['status'] ?? null) ?? '');
        $status = $statusValue instanceof UserStatus
            ? $statusValue->value
            : UserStatus::Active->value;

        return array_filter([
            'username' => $this->nullableString($row['username'] ?? null),
            'email' => $email,
            'status' => $status,
            'locale' => $this->nullableString($row['locale'] ?? null) ?? config('app.locale', 'en'),
            'timezone' => $this->nullableString($row['timezone'] ?? null) ?? config('app.timezone', 'UTC'),
            'first_name' => $this->nullableString($row['first_name'] ?? null),
            'last_name' => $this->nullableString($row['last_name'] ?? null),
            'phone_number' => $this->nullableString($row['phone_number'] ?? null),
            'city' => $this->nullableString($row['city'] ?? null),
            'company_name' => $this->nullableString($row['company_name'] ?? null),
            'job_title' => $this->nullableString($row['job_title'] ?? null),
        ], fn (mixed $value): bool => $value !== null && $value !== '');
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = mb_trim((string) $value);

        return $value === '' ? null : $value;
    }
}
