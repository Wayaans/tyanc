<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('Activity log') }}</title>
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
    <h1>{{ __('Activity log') }}</h1>
    <p class="meta">{{ __('Generated at') }}: {{ $generatedAt->format('Y-m-d H:i') }}</p>

    <table>
        <thead>
            <tr>
                <th>{{ __('Log') }}</th>
                <th>{{ __('Event') }}</th>
                <th>{{ __('Description') }}</th>
                <th>{{ __('Caused by') }}</th>
                <th>{{ __('When') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($activities as $activity)
                <tr>
                    <td>{{ $activity->log_name }}</td>
                    <td>{{ $activity->event ?? $activity->description }}</td>
                    <td>{{ $activity->description }}</td>
                    <td>{{ $activity->causer?->name ?? __('Unknown') }}</td>
                    <td>{{ optional($activity->created_at)->format('Y-m-d H:i') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
