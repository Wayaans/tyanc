<?php

declare(strict_types=1);

namespace App\Exports;

use App\Models\User;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Exportable;

final class UsersExport implements FromCollection, WithHeadings, WithMapping
{
    use Exportable;

    public function collection()
    {
        return User::query()
            ->with('roles')
            ->orderBy('name')
            ->orderBy('username')
            ->get();
    }

    public function map($user): array
    {
        return [
            $user->name,
            $user->username,
            $user->email,
            $user->status?->value ?? (string) $user->status,
            $user->locale,
            $user->timezone,
            $user->roles->pluck('name')->join(', '),
            $user->created_at?->toDateTimeString(),
        ];
    }

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
