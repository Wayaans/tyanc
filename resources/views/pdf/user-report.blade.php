<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
    <head>
        <meta charset="utf-8">
        <title>{{ __('User report') }}</title>
        <style>
            body {
                font-family: DejaVu Sans, sans-serif;
                font-size: 12px;
                color: #111827;
            }

            table {
                width: 100%;
                border-collapse: collapse;
                margin-top: 16px;
            }

            th,
            td {
                border: 1px solid #d1d5db;
                padding: 8px;
                text-align: left;
                vertical-align: top;
            }

            th {
                background: #f3f4f6;
                font-weight: 700;
            }

            h1 {
                margin: 0;
                font-size: 20px;
            }

            .meta {
                margin-top: 6px;
                color: #6b7280;
                font-size: 11px;
            }
        </style>
    </head>
    <body>
        <h1>{{ __('User report') }}</h1>
        <p class="meta">{{ __('Generated at :time', ['time' => $generatedAt->toDateTimeString()]) }}</p>

        <table>
            <thead>
                <tr>
                    <th>{{ __('Full name') }}</th>
                    <th>{{ __('Username') }}</th>
                    <th>{{ __('Email') }}</th>
                    <th>{{ __('Status') }}</th>
                    <th>{{ __('Locale') }}</th>
                    <th>{{ __('Timezone') }}</th>
                    <th>{{ __('Roles') }}</th>
                    <th>{{ __('Joined') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($users as $user)
                    <tr>
                        <td>{{ $user->name }}</td>
                        <td>{{ $user->username }}</td>
                        <td>{{ $user->email }}</td>
                        <td>{{ $user->status?->value ?? (string) $user->status }}</td>
                        <td>{{ $user->locale }}</td>
                        <td>{{ $user->timezone }}</td>
                        <td>{{ $user->roles->pluck('name')->join(', ') }}</td>
                        <td>{{ $user->created_at?->toDateTimeString() }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </body>
</html>
