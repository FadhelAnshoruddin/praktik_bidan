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
@php
    $tabs = ['queue' => 'Antrean hari ini', 'history' => 'Pendaftaran pasien', 'profile' => 'Profil & jadwal', 'closures' => 'Hari libur'];
    $tab = array_key_exists($tab, $tabs) ? $tab : 'queue';
    $todayRows = collect($registrations)->where('date', $today)->sortBy('queue')->values();
    $waitingCount = $todayRows->where('status', 'WAITING')->count();
    $activeCount = $todayRows->where('status', 'IN_EXAMINATION')->count();
    $completedCount = $todayRows->where('status', 'COMPLETED')->count();
    $called = $todayRows->first(fn ($item) => in_array($item['status'], ['CALLED', 'IN_EXAMINATION'], true));
    $statusLabels = ['WAITING' => 'Menunggu', 'CALLED' => 'Dipanggil', 'IN_EXAMINATION' => 'Dalam pemeriksaan', 'COMPLETED' => 'Selesai', 'CANCELLED' => 'Dibatalkan', 'NO_SHOW' => 'Tidak hadir'];
    $query = mb_strtolower((string) request('q', ''));
    $historyRows = collect($registrations)->filter(fn ($item) => $query === '' || str_contains(mb_strtolower($item['name'].' '.$item['code'].' '.$item['phone']), $query));
@endphp
<div class="admin-shell">
    <aside class="admin-sidebar">
        <a class="brand" href="{{ route('home') }}"><span class="brand-mark">♥</span><span class="brand-name">bidan<span>mila</span><small>panel pengelola</small></span></a>
        <div class="admin-profile"><span class="avatar">{{ mb_substr($profile->name, 0, 1) }}</span><div><strong>{{ $profile->name }}</strong><small>Pengelola praktik</small></div><span class="online-dot"></span></div>
        <span class="side-label">MENU UTAMA</span>
        <nav class="admin-nav" aria-label="Panel bidan">
            @foreach ($tabs as $key => $label)<a class="{{ $tab === $key ? 'selected' : '' }}" href="{{ route('admin.dashboard', ['tab' => $key]) }}"><span aria-hidden="true">{{ ['queue' => '▤', 'history' => '▦', 'profile' => '◎', 'closures' => '▣'][$key] }}</span>{{ $label }}@if ($key === 'queue')<span>{{ $waitingCount }}</span>@endif</a>@endforeach
        </nav>
        <div class="sidebar-bottom"><form method="post" action="{{ route('admin.logout') }}">@csrf<button class="logout-button" type="submit"><span aria-hidden="true">↙</span> Keluar panel</button></form></div>
    </aside>
    <main class="admin-main">
        <header class="admin-topbar"><div><span>PANEL PRAKTIK · {{ mb_strtoupper($profile->name) }}</span><h1>{{ $tabs[$tab] }}</h1></div><div class="topbar-date"><span aria-hidden="true">▦</span> {{ \Carbon\CarbonImmutable::parse($today)->locale('id')->translatedFormat('l, j F Y') }}</div></header>
        @if (session('notice'))<p class="profile-note" role="status">{{ session('notice') }}</p>@endif
        @if ($errors->any())<p class="form-error" role="alert">{{ $errors->first() }}</p>@endif

        @if ($tab === 'queue')
            <div class="admin-stat-grid">
                <div class="admin-stat"><span class="stat-icon green">♙</span><div><small>Menunggu</small><strong>{{ $waitingCount }}</strong></div><span class="stat-trend">Hari ini</span></div>
                <div class="admin-stat"><span class="stat-icon coral">♥</span><div><small>Sedang diperiksa</small><strong>{{ $activeCount }}</strong></div><span class="stat-trend">Hari ini</span></div>
                <div class="admin-stat"><span class="stat-icon yellow">✓</span><div><small>Selesai hari ini</small><strong>{{ $completedCount }}</strong></div><span class="stat-trend">Hari ini</span></div>
            </div>
            <section class="practice-control {{ $open ? '' : 'closed' }}"><div class="practice-control-icon">◷</div><div><span class="section-kicker">STATUS PRAKTIK</span><strong>{{ $open ? 'Praktik sedang buka' : 'Praktik ditutup sementara' }}</strong><small>{{ $open ? 'Pasien dapat membuat pendaftaran baru.' : 'Pendaftaran baru sedang dihentikan.' }}</small></div><form method="post" action="{{ route('admin.practice.update') }}">@csrf @method('PUT')<input type="hidden" name="is_open" value="{{ $open ? 0 : 1 }}"><button class="toggle-switch {{ $open ? 'on' : '' }}" type="submit" aria-label="{{ $open ? 'Tutup praktik' : 'Buka praktik' }}"><span></span></button></form></section>
            <section class="admin-queue-section">
                <div class="list-heading"><div><span class="section-kicker">DAFTAR KUNJUNGAN</span><h2>Antrean aktif <span>{{ $todayRows->count() }}</span></h2></div><form class="filter-select" method="get"><input type="hidden" name="tab" value="queue"><select name="status" onchange="this.form.submit()"><option value="">Semua</option>@foreach (['WAITING' => 'Menunggu', 'CALLED' => 'Dipanggil', 'COMPLETED' => 'Selesai'] as $value => $label)<option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>@endforeach</select><noscript><button type="submit">Filter</button></noscript></form></div>
                @if ($called)<div class="now-serving"><span class="serving-pulse"></span><span><small>SEDANG DILAYANI</small><strong>A-{{ str_pad($called['queue'], 3, '0', STR_PAD_LEFT) }} · {{ $called['name'] }}</strong></span><span class="serving-tag">{{ $statusLabels[$called['status']] }}</span></div>@endif
                @php($displayRows = request('status') ? $todayRows->where('status', request('status')) : $todayRows)
                <div class="queue-table"><div class="queue-table-head"><span>NO. ANTREAN</span><span>NAMA PASIEN</span><span>PEMERIKSAAN</span><span>STATUS</span><span>TINDAKAN</span></div>
                    @forelse ($displayRows as $item)
                        @php($next = ['WAITING' => ['CALLED', 'Panggil'], 'CALLED' => ['IN_EXAMINATION', 'Mulai periksa'], 'IN_EXAMINATION' => ['COMPLETED', 'Selesaikan']][$item['status']] ?? null)
                        <div class="queue-table-row"><strong class="admin-queue-number">A-{{ str_pad($item['queue'], 3, '0', STR_PAD_LEFT) }}</strong><div class="patient-cell"><strong>{{ $item['name'] }}</strong><small>{{ $item['phone'] }}</small></div><span class="visit-cell">{{ $item['type'] }} · {{ $item['appointmentTime'] ?? 'Jam belum ditetapkan' }} WIB</span><span class="status-pill {{ strtolower($item['status']) }}">{{ $statusLabels[$item['status']] }}</span><div class="row-actions">
                            @if ($next)<form method="post" action="{{ route('admin.registrations.status', $item['id']) }}">@csrf<input type="hidden" name="status" value="{{ $next[0] }}"><button type="submit">{{ $next[1] }} <span aria-hidden="true">→</span></button></form>@endif
                            @if ($item['status'] === 'WAITING')<form method="post" action="{{ route('admin.registrations.status', $item['id']) }}">@csrf<input type="hidden" name="status" value="NO_SHOW"><button class="more-action" type="submit" title="Tandai tidak hadir" aria-label="Tandai tidak hadir">×</button></form>@endif
                            @if ($item['status'] === 'CALLED')<form method="post" action="{{ route('admin.registrations.status', $item['id']) }}">@csrf<input type="hidden" name="status" value="CALLED"><button class="more-action" type="submit" title="Panggil ulang" aria-label="Panggil ulang">↻</button></form>@endif
                        </div></div>
                    @empty<div class="empty-list">Belum ada antrean pada kategori ini.</div>@endforelse
                </div><p class="queue-footnote"><span aria-hidden="true">♢</span> Data antrean hanya terlihat oleh pengelola praktik.</p>
            </section>
        @elseif ($tab === 'history')
            <section class="history-panel"><div class="history-toolbar"><div><span class="section-kicker">SEMUA KUNJUNGAN</span><h2>Riwayat pendaftaran</h2></div><form class="history-search" method="get"><input type="hidden" name="tab" value="history"><span aria-hidden="true">⌕</span><input name="q" value="{{ request('q') }}" placeholder="Cari nama, nomor, atau kode"><button type="submit" aria-label="Cari">→</button></form></div>
                <div class="history-table"><div class="history-head"><span>PASIEN</span><span>PEMERIKSAAN</span><span>TANGGAL</span><span>ANTREAN</span><span>STATUS</span></div>
                    @forelse ($historyRows as $item)<div class="history-row"><div><strong>{{ $item['name'] }}</strong><small>{{ $item['code'] }}</small></div><span>{{ $item['type'] }}</span><span>{{ \Carbon\CarbonImmutable::parse($item['date'])->locale('id')->translatedFormat('j M Y') }} · {{ $item['appointmentTime'] ?? '-' }} WIB</span><strong>A-{{ str_pad($item['queue'], 3, '0', STR_PAD_LEFT) }}</strong><span class="status-pill {{ strtolower($item['status']) }}">{{ $statusLabels[$item['status']] }}</span></div>
                    @empty<div class="empty-list">Tidak ada pendaftaran yang cocok.</div>@endforelse
                </div>
            </section>
        @elseif ($tab === 'profile')
            <section class="profile-panel">
                <div class="profile-panel-head"><span class="section-kicker">INFORMASI PRAKTIK</span><h2>Profil & jadwal</h2><p>Informasi ini ditampilkan kepada pasien.</p></div>
                <form method="post" action="{{ route('admin.profile.update') }}">@csrf @method('PUT')
                    <div class="profile-fields">
                        <label>Nama praktik<input name="name" value="{{ old('name', $profile->name) }}" required></label>
                        <label>Gelar (opsional)<input name="title" value="{{ old('title', $profile->title) }}"></label>
                        <label>Nomor kontak<input name="phone" value="{{ old('phone', $profile->phone) }}" inputmode="tel" placeholder="Masukkan nomor praktik"></label>
                        <label>Alamat praktik<input name="address" value="{{ old('address', $profile->address) }}" required></label>
                        <label>Tautan Google Maps<input type="url" name="maps_url" value="{{ old('maps_url', $profile->maps_url) }}" placeholder="https://maps.app.goo.gl/..."></label>
                        <label>Foto bidan (URL HTTPS)<input type="url" name="photo_url" value="{{ old('photo_url', $profile->photo_url) }}" placeholder="https://..."></label>
                        <label>Moto<input name="motto" value="{{ old('motto', $profile->motto) }}"></label>
                        <label>Deskripsi praktik<input name="description" value="{{ old('description', $profile->description) }}"></label>
                        <label>Durasi pemeriksaan<select name="duration"><option value="15" @selected($profile->duration === 15)>15 menit</option><option value="20" @selected($profile->duration === 20)>20 menit</option><option value="30" @selected($profile->duration === 30)>30 menit</option>@if (!in_array($profile->duration, [15, 20, 30], true))<option value="{{ $profile->duration }}" selected>{{ $profile->duration }} menit</option>@endif</select></label>
                        <label>Kapasitas pasien per hari<input type="number" name="capacity" min="1" max="100" value="{{ old('capacity', $profile->capacity) }}" required></label>
                    </div>
                    <button class="button-primary" type="submit">Simpan profil <span aria-hidden="true">✓</span></button>
                </form>
                <form class="schedule-editor" method="post" action="{{ route('admin.schedules.update') }}">@csrf @method('PUT')
                    <div><span class="section-kicker">JADWAL MINGGUAN</span><h3>Atur hari dan jam praktik</h3></div>
                    @foreach ($schedules as $index => $schedule)<div class="schedule-line"><strong>{{ $schedule['dayName'] }}</strong><label class="schedule-check"><input type="checkbox" name="schedules[{{ $index }}][is_open]" value="1" @checked($schedule['isOpen'])> Buka</label><input type="hidden" name="schedules[{{ $index }}][day]" value="{{ $schedule['day'] }}"><input type="time" name="schedules[{{ $index }}][open_time]" aria-label="{{ $schedule['dayName'] }} jam buka" value="{{ $schedule['openTime'] }}" @disabled(!$schedule['isOpen'])><input type="time" name="schedules[{{ $index }}][close_time]" aria-label="{{ $schedule['dayName'] }} jam tutup" value="{{ $schedule['closeTime'] }}" @disabled(!$schedule['isOpen'])></div>@endforeach
                    <button class="button-primary" type="submit">Simpan jadwal <span aria-hidden="true">✓</span></button>
                </form>
                <form method="post" action="{{ route('admin.practice.update') }}">@csrf @method('PUT')<input type="hidden" name="is_open" value="{{ $open ? 0 : 1 }}"><button class="button-primary" type="submit">{{ $open ? 'Tutup pendaftaran sementara' : 'Buka pendaftaran' }} <span aria-hidden="true">→</span></button></form>
                <p class="profile-note"><span aria-hidden="true">♢</span> Data pasien dienkripsi sebelum ditulis ke database.</p>
            </section>
        @else
            <section class="closures-panel"><div class="closures-heading"><span class="section-kicker">PENGATURAN KALENDER</span><h2>Penutupan khusus</h2><p>Tanggal yang ditambahkan tidak akan tersedia untuk pendaftaran pasien.</p></div>
                <form class="closure-form" method="post" action="{{ route('admin.closures.store') }}">@csrf
                    <label>Tanggal<input type="date" name="date" value="{{ old('date', $today) }}" min="{{ $today }}" required></label>
                    <label>Alasan penutupan<input name="reason" value="{{ old('reason') }}" maxlength="200" placeholder="Contoh: Libur praktik" required></label>
                    <button class="button-primary" type="submit"><span aria-hidden="true">▦</span> Tambah hari libur</button>
                </form>
                <div class="closure-list" aria-live="polite">
                    @forelse ($closures as $closure)<div class="closure-row"><span class="closure-date"><span aria-hidden="true">▦</span>{{ \Carbon\CarbonImmutable::parse($closure->date)->locale('id')->translatedFormat('D, j M Y') }}</span><span class="closure-reason">{{ $closure->reason }}</span><form method="post" action="{{ route('admin.closures.delete', $closure->id) }}">@csrf @method('DELETE')<button class="icon-button" type="submit" aria-label="Hapus hari libur" title="Hapus hari libur">×</button></form></div>
                    @empty<div class="empty-list">Belum ada hari libur khusus.</div>@endforelse
                </div>
            </section>
        @endif
    </main>
</div>
</body>
</html>