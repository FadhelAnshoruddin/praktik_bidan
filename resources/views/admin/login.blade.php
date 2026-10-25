<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Panel Bidan | Praktik Bidan Mila</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    @vite(['src/styles.css', 'src/app.js'])
</head>
<body>
<main class="login-screen">
    <a class="back-link" href="{{ route('home') }}"><span aria-hidden="true">←</span> Kembali ke website</a>
    <form class="login-card" method="post" action="{{ route('admin.authenticate') }}">
        @csrf
        <span class="brand-mark">♥</span><span class="section-kicker">PANEL BIDAN</span>
        <h1>Selamat datang<br><em>kembali.</em></h1><p>Masuk untuk mengelola antrean dan kunjungan pasien.</p>
        <label>Email<input type="email" name="email" value="{{ old('email') }}" placeholder="nama@email.com" autocomplete="username" required></label>
        <label>Kata sandi<input type="password" name="password" placeholder="Masukkan kata sandi" autocomplete="current-password" required></label>
        @if ($errors->any())<p class="form-error" role="alert">{{ $errors->first() }}</p>@endif
        <button class="button-primary form-submit" type="submit">Masuk ke panel <span aria-hidden="true">→</span></button>
    </form>
</main>
</body>
</html>