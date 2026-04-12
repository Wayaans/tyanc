<?php

declare(strict_types=1);

namespace App\Exports;

use App\Models\User;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/** @implements WithMapping<User> */
final class UsersExport implements FromCollection, WithHeadings, WithMapping
{
    use Exportable;

    /**
     * @return Collection<int, User>
     */
    public function collection(): Collection
    {
        return User::query()
            ->with('roles')
            ->orderBy('name')
            ->orderBy('username')
            ->get();
    }

    /**
     * @param  User  $user
     * @return array<int, string|null>
     */
    public function map($user): array
    {
        return [
            $user->name,
            $user->username,
            $user->email,
            $user->status->value,
            $user->locale,
            $user->timezone,
            $user->roles->pluck('name')->join(', '),
            $user->created_at?->toDateTimeString(),
        ];
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return [
            __('Full name'),
            __('Username'),
            __('Email'),
            __('Status'),
            __('Locale'),
            __('Timezone'),
            __('Roles'),
            __('Joined'),
        ];
    }
}
