<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('Users') }}</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            color: #0f172a;
            font-size: 12px;
        }

        h1 {
            margin: 0 0 12px;
            font-size: 22px;
        }

        p.meta {
            margin: 0 0 20px;
            color: #475569;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            border: 1px solid #cbd5e1;
            padding: 8px;
            text-align: left;
            vertical-align: top;
        }

        th {
            background: #f8fafc;
            font-weight: 700;
        }
    </style>
</head>
<body>
    <h1>{{ __('Users') }}</h1>
    <p class="meta">{{ __('Generated at') }}: {{ $generatedAt->format('Y-m-d H:i') }}</p>

    <table>
        <thead>
            <tr>
                <th>{{ __('Full name') }}</th>
                <th>{{ __('Username') }}</th>
                <th>{{ __('Email') }}</th>
                <th>{{ __('Status') }}</th>
                <th>{{ __('Roles') }}</th>
                <th>{{ __('Company') }}</th>
                <th>{{ __('City') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($users as $user)
                <tr>
                    <td>{{ $user->name }}</td>
                    <td>{{ $user->username }}</td>
                    <td>{{ $user->email }}</td>
                    <td>{{ __($user->status?->value ?? (string) $user->status) }}</td>
                    <td>{{ $user->roles->pluck('name')->join(', ') }}</td>
                    <td>{{ $user->profile?->company_name }}</td>
                    <td>{{ $user->profile?->city }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
