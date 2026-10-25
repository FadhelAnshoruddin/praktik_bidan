@extends('layouts.public')

@section('title', 'Cek antrean | Praktik Bidan Mila')

@if ($registration)
    @section('head')<meta http-equiv="refresh" content="20">@endsection
@endif

@section('content')
@php
    $phoneHref = preg_replace('/\D+/', '', $profile->phone);
    if (str_starts_with($phoneHref, '0')) $phoneHref = '62'.substr($phoneHref, 1);
    $statusLabels = ['WAITING' => 'Menunggu', 'CALLED' => 'Dipanggil', 'IN_EXAMINATION' => 'Dalam pemeriksaan', 'COMPLETED' => 'Selesai', 'CANCELLED' => 'Dibatalkan', 'NO_SHOW' => 'Tidak hadir'];
@endphp
<main class="check-page section-wrap">
    <div class="check-intro"><span class="section-kicker">INFORMASI ANTREAN</span><h1>Tetap tenang,<br><em>kami kabari di sini.</em></h1><p>Masukkan kode yang Anda terima setelah mendaftar untuk melihat status antrean.</p></div>
    <form class="lookup-form" method="post" action="{{ route('queue.lookup') }}">
        @csrf
        <label for="lookup-code">KODE PENDAFTARAN</label>
        <div class="lookup-input"><span aria-hidden="true">⌕</span><input id="lookup-code" name="code" value="{{ old('code', $code) }}" placeholder="BID-20261001-A1B2C3D4E5" autocapitalize="characters" required><button type="submit" aria-label="Cari kode pendaftaran"><span aria-hidden="true">→</span></button></div>
        @error('code')<p class="lookup-error" role="alert">{{ $message }}</p>@enderror
        <small>Kode pendaftaran tercantum pada bukti pendaftaran Anda.</small>
    </form>
    @if ($registration)
        @php
            $position = in_array($registration['status'], ['COMPLETED', 'CANCELLED', 'NO_SHOW'], true) ? ($registration['status'] === 'COMPLETED' ? 'Selesai' : '-') : $registration['ahead'] + 1;
            $progress = max(8, min(100, 100 - $registration['ahead'] * 13));
        @endphp
        <section class="queue-result" aria-live="polite">
            <div class="result-heading"><div><span class="section-kicker">PENDAFTARAN DITEMUKAN</span><h2>Halo, {{ str($registration['name'])->explode(' ')->first() }}.</h2></div><span class="status-pill {{ strtolower($registration['status']) }}">{{ $statusLabels[$registration['status']] }}</span></div>
            <div class="queue-progress"><div><small>ANTREAN ANDA</small><strong>A-{{ str_pad($registration['queue'], 3, '0', STR_PAD_LEFT) }}</strong></div><div class="progress-line"><span style="width:{{ $progress }}%"></span></div><div><small>POSISI</small><strong>{{ $position }}</strong></div></div>
            <div class="result-facts"><div><small>SEDANG DIPANGGIL</small><strong>{{ $registration['currentlyCalled'] ? 'A-'.str_pad($registration['currentlyCalled'], 3, '0', STR_PAD_LEFT) : 'Belum ada' }}</strong></div><div><small>PASIEN DI DEPAN</small><strong>{{ $registration['ahead'] }} orang</strong></div><div><small>TANGGAL KUNJUNGAN</small><strong>{{ \Carbon\CarbonImmutable::parse($registration['date'])->locale('id')->translatedFormat('j F') }}</strong></div><div><small>JAM PEMERIKSAAN</small><strong>{{ $registration['appointmentTime'] ?? $registration['estimatedTime'] }} WIB</strong></div></div>
            <div class="privacy-line"><span aria-hidden="true">♢</span> Informasi pasien lain tidak ditampilkan.</div>
        </section>
    @endif
    <a class="check-contact" href="{{ $profile->phone ? 'tel:+'.$phoneHref : '#' }}"><span class="contact-icon">☎</span><span><small>PERLU BANTUAN?</small><strong>{{ $profile->phone ? 'Hubungi Bidan' : 'Nomor kontak belum diatur' }}</strong><span>{{ $profile->phone ?: 'Silakan hubungi praktik secara langsung.' }}</span></span>@if ($profile->phone)<span aria-hidden="true">↗</span>@endif</a>
</main>
@endsection