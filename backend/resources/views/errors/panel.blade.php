@php
    $locale = \App\Support\LocaleManager::resolve(request());
    $home = \App\Support\LocaleManager::localizedPath($locale, '/');
@endphp
<!DOCTYPE html>
<html lang="{{ $locale }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>{{ $title ?? 'Ошибка' }}</title>
    <style>
        * { box-sizing: border-box; }

        body {
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 24px;
            font-family: system-ui, -apple-system, 'Segoe UI', Roboto, Ubuntu, Cantarell, 'Noto Sans', sans-serif;
            color: #0f172a;
            background: #eef1f6;
            -webkit-tap-highlight-color: transparent;
        }

        .panel {
            width: 100%;
            max-width: 420px;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 20px;
            padding: 24px;
            box-shadow: 0 4px 16px rgba(37, 99, 235, 0.1);
        }

        .badge {
            display: inline-flex;
            align-items: center;
            padding: 6px 10px;
            border-radius: 999px;
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            color: #2563eb;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.02em;
        }

        h1 {
            margin: 14px 0 8px;
            font-size: 1.25rem;
            font-weight: 800;
            letter-spacing: -0.02em;
            color: #0f172a;
        }

        p {
            margin: 0;
            color: #64748b;
            line-height: 1.5;
            font-size: 0.9375rem;
        }

        .actions {
            display: grid;
            gap: 10px;
            margin-top: 20px;
        }

        .btn {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            min-height: 48px;
            padding: 12px 18px;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 700;
            text-decoration: none;
            border: 2px solid transparent;
            cursor: pointer;
            font-family: inherit;
            -webkit-tap-highlight-color: transparent;
        }

        .btn-primary {
            background: #2563eb;
            border-color: #1d4ed8;
            color: #ffffff;
        }

        .btn-secondary {
            background: #ffffff;
            border-color: #cbd5e1;
            color: #0f172a;
        }

        .fine {
            margin-top: 16px;
            font-size: 12px;
            color: #94a3b8;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="panel" role="alertdialog" aria-labelledby="error-title" aria-describedby="error-desc">
        <span class="badge">{{ $badge }}</span>
        <h1 id="error-title">{{ $heading }}</h1>
        <p id="error-desc">{{ $message }}</p>

        <div class="actions">
            @if(isset($primaryAction))
                <button type="button" class="btn btn-primary" onclick="{{ $primaryAction }}">
                    {{ $primaryLabel ?? 'Понятно' }}
                </button>
            @else
                <a class="btn btn-primary" href="{{ $home }}">{{ $primaryLabel ?? 'На главную' }}</a>
                @isset($secondaryAction)
                    <button type="button" class="btn btn-secondary" onclick="{{ $secondaryAction }}">
                        {{ $secondaryLabel }}
                    </button>
                @endisset
            @endif
        </div>

        <p class="fine">kztusdt.kz • {{ strtoupper($locale) }}</p>
    </div>
</body>
</html>
