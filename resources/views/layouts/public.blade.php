<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f8f7f2">
    <meta name="description" content="Pendaftaran dan informasi antrean praktik bidan.">
    <title>@yield('title', 'Praktik Bidan Mila')</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    @yield('head')
    @vite(['src/styles.css', 'src/app.js'])
</head>
<body>
<div class="site-shell">
    <header class="site-header">
        <a class="brand" href="{{ route('home') }}" aria-label="Beranda Praktik Bidan Mila">
            <span class="brand-mark">♥</span>
            <span class="brand-name">bidan<span>mila</span><small>praktik mandiri</small></span>
        </a>
        <button class="mobile-menu icon-button" type="button" data-menu-toggle aria-label="Buka menu">☰</button>
        <nav class="public-nav" aria-label="Navigasi utama">
            <a class="{{ request()->routeIs('home') ? 'active' : '' }}" href="{{ route('home') }}">Beranda</a>
            <a href="{{ route('home') }}#jadwal">Jadwal praktik</a>
            <a class="{{ request()->routeIs('queue.*') ? 'active' : '' }}" href="{{ route('queue.check') }}">Cek antrean</a>
            <a class="nav-cta" href="{{ route('registration.create') }}">Buat janji <span aria-hidden="true">↗</span></a>
        </nav>
    </header>
    @if ($errors->any())
        <div class="server-warning" role="alert">{{ $errors->first() }}</div>
    @endif
    @if (session('notice'))
        <div class="server-warning" role="status">{{ session('notice') }}</div>
    @endif
    @yield('content')
    <footer class="site-footer">
        <div class="footer-brand"><span class="brand-mark">♥</span><span>Praktik Bidan Mila</span></div>
        <p>Merawat dengan perhatian, menemani setiap langkah.</p>
        <a class="footer-admin" href="{{ route('login') }}">Panel bidan <span aria-hidden="true">↗</span></a>
        <small>© {{ now()->year }} Praktik Bidan Mila</small>
    </footer>
</div>
</body>
</html>