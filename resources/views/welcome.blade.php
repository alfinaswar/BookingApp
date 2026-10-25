<!doctype html>
<html lang="id">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover" />
    <title>{{ \App\Support\Brand::name() }}</title>
    <link rel="icon" href="{{ \App\Support\Brand::favicon() }}" />
    <link href="{{ asset('') }}assets/css/tabler.min.css?1692870487" rel="stylesheet" />
    <style>
        :root {
            {!! \App\Support\Brand::cssVariables() !!}
        }

        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(160deg, var(--brand-primary) 0%, #0d2b1d 100%);
            color: #fff;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            margin: 0;
            padding: 24px;
        }

        .landing-card {
            max-width: 560px;
            width: 100%;
            text-align: center;
        }

        .landing-logo {
            max-height: 110px;
            max-width: 100%;
            object-fit: contain;
            background: #fff;
            border-radius: 16px;
            padding: 16px 24px;
        }

        .landing-title {
            font-size: 2rem;
            font-weight: 700;
            margin: 24px 0 8px;
        }

        .landing-tagline {
            opacity: .85;
            font-size: 1.05rem;
            margin-bottom: 28px;
        }

        .btn-landing {
            background: #fff;
            color: var(--brand-primary);
            border: none;
            padding: 14px 32px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 1rem;
            text-decoration: none;
            display: inline-block;
            transition: transform .15s ease;
        }

        .btn-landing:hover {
            transform: translateY(-2px);
            color: var(--brand-primary);
        }

        .btn-outline-landing {
            background: transparent;
            color: #fff;
            border: 1px solid rgba(255, 255, 255, .5);
            padding: 14px 32px;
            border-radius: 10px;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            margin-left: 8px;
        }

        .btn-outline-landing:hover {
            background: rgba(255, 255, 255, .12);
            color: #fff;
        }

        .landing-contact {
            margin-top: 32px;
            font-size: .875rem;
            opacity: .8;
            line-height: 1.7;
        }

        .landing-contact a {
            color: #fff;
            text-decoration: underline;
        }
    </style>
</head>

<body>
    <div class="landing-card">
        <img src="{{ \App\Support\Brand::logo() }}" alt="{{ \App\Support\Brand::name() }}"
            class="landing-logo">

        <h1 class="landing-title">{{ \App\Support\Brand::company() }}</h1>

        @if (\App\Support\Brand::tagline())
            <p class="landing-tagline">{{ \App\Support\Brand::tagline() }}</p>
        @endif

        <div class="mt-4">
            @auth
                <a href="{{ route('home') }}" class="btn-landing">Buka Dashboard</a>
            @else
                <a href="{{ route('booking.index') }}" class="btn-landing">Pesan Sekarang</a>
                <a href="{{ route('login') }}" class="btn-outline-landing">Masuk</a>
            @endauth
        </div>

        <div class="landing-contact">
            @if (\App\Support\Brand::address())
                <div>{{ \App\Support\Brand::address() }}</div>
            @endif
            @if (\App\Support\Brand::phone())
                <div>Telp: {{ \App\Support\Brand::phone() }}</div>
            @endif
            @if (\App\Support\Brand::email())
                <div><a href="mailto:{{ \App\Support\Brand::email() }}">{{ \App\Support\Brand::email() }}</a></div>
            @endif
            @if (\App\Support\Brand::whatsappUrl())
                <div><a href="{{ \App\Support\Brand::whatsappUrl() }}" target="_blank">WhatsApp</a></div>
            @endif
            <div class="mt-3" style="opacity:.7;">{{ \App\Support\Brand::footer() }}</div>
        </div>
    </div>
</body>

</html>
