@extends('layouts.public')

@section('title', 'Pendaftaran berhasil | Praktik Bidan Mila')

@section('content')
@if (!$registration)
    <main class="empty-page section-wrap"><span aria-hidden="true" style="font-size:42px">✓</span><h1>Belum ada pendaftaran.</h1><a class="button-primary" href="{{ route('registration.create') }}">Mulai pendaftaran <span aria-hidden="true">→</span></a></main>
@else
    @php
        $phoneHref = preg_replace('/\D+/', '', $profile->phone);
        if (str_starts_with($phoneHref, '0')) $phoneHref = '62'.substr($phoneHref, 1);
    @endphp
    <main class="success-page section-wrap">
        <div class="success-top"><span class="success-check">✓</span><span class="section-kicker">PENDAFTARAN BERHASIL</span><h1>Sampai jumpa,<br><em>{{ str($registration['name'])->explode(' ')->first() }}.</em></h1><p>Simpan kode ini untuk melihat perkembangan antrean Anda.</p></div>
        <section class="ticket">
            <div class="ticket-head"><span>KODE PENDAFTARAN</span><span class="ticket-live"><i></i> TERSIMPAN</span></div>
            <div class="ticket-code">{{ $registration['code'] }}<button class="icon-button" type="button" data-copy="{{ $registration['code'] }}" aria-label="Salin kode">▢</button></div>
            <div class="ticket-details">
                <div><small>NOMOR ANTREAN</small><strong class="ticket-queue">A-{{ str_pad($registration['queue'], 3, '0', STR_PAD_LEFT) }}</strong></div>
                <div><small>TANGGAL KUNJUNGAN</small><strong>{{ \Carbon\CarbonImmutable::parse($registration['date'])->locale('id')->translatedFormat('j F') }}</strong></div>
                <div><small>JENIS PEMERIKSAAN</small><strong>{{ $registration['type'] }}</strong></div>
                <div><small>JAM PEMERIKSAAN</small><strong>{{ $registration['appointmentTime'] ?? $registration['estimatedTime'] }} WIB</strong></div>
            </div>
            <div class="ticket-foot"><span><span aria-hidden="true">◷</span> Datang sesuai jam pemeriksaan yang dipilih</span><span class="status-pill waiting">Menunggu</span></div>
        </section>
        <div class="success-actions"><a class="button-primary" href="{{ route('queue.check') }}">Lihat status antrean <span aria-hidden="true">→</span></a>@if ($profile->phone)<a class="button-outline" href="tel:+{{ $phoneHref }}"><span aria-hidden="true">☎</span> Hubungi bidan</a>@endif</div>
        <a class="text-link success-back" href="{{ route('home') }}">Kembali ke beranda <span aria-hidden="true">↗</span></a>
    </main>
@endif
@endsection