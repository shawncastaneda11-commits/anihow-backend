<!DOCTYPE html>
<html lang="{{ $lang === 'fil' ? 'fil' : 'en' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} · AniHow</title>
    <style>
        :root { color-scheme: light dark; --brand: #1f5a3e; --muted: #5b6660; --line: #dfe5e1; --bg: #ffffff; --text: #18201c; }
        @media (prefers-color-scheme: dark) { :root { --brand: #a8d5ba; --muted: #a3ada7; --line: #2e3a34; --bg: #121a17; --text: #f3f0e8; } }
        * { box-sizing: border-box; }
        body { margin: 0; background: var(--bg); color: var(--text); font: 16px/1.6 system-ui, -apple-system, "Segoe UI", Roboto, Arial, sans-serif; }
        header { background: #1f5a3e; color: #fff; padding: 18px 16px; }
        header a { color: #fff; text-decoration: none; font-weight: 700; font-size: 20px; }
        main { max-width: 760px; margin: 0 auto; padding: 24px 16px 48px; }
        h1 { font-size: 26px; margin: 0 0 4px; }
        h2 { font-size: 18px; margin: 28px 0 6px; }
        p, li { margin: 0 0 10px; }
        .muted { color: var(--muted); font-size: 14px; }
        .lang { margin: 0 0 20px; font-size: 14px; }
        a { color: var(--brand); font-weight: 600; }
        ol { padding-left: 22px; }
        footer { border-top: 1px solid var(--line); margin-top: 36px; padding-top: 16px; font-size: 14px; color: var(--muted); }
    </style>
</head>
<body>
<header><a href="{{ url('/privacy') }}">AniHow</a></header>
<main>
    <h1>{{ $title }}</h1>
    <p class="lang">
        @if ($lang === 'fil')
            <a href="?lang=en">English</a> · Filipino
        @else
            English · <a href="?lang=fil">Filipino</a>
        @endif
    </p>
    @yield('content')
    <footer>
        AniHow, a digital market hub for farmers in Cavite.
        <a href="{{ route('legal.privacy', ['lang' => $lang]) }}">{{ $lang === 'fil' ? 'Patakaran sa privacy' : 'Privacy policy' }}</a> ·
        <a href="{{ route('legal.delete-account', ['lang' => $lang]) }}">{{ $lang === 'fil' ? 'Burahin ang account' : 'Delete your account' }}</a>
    </footer>
</main>
</body>
</html>
