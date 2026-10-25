@extends('layouts.public')

@section('title', $profile->name.' | Praktik Bidan Mila')

@section('content')
@php
    $groups = [];
    foreach ($schedules as $schedule) {
        if ($schedule['isOpen']) {
            $groups[$schedule['openTime'].'-'.$schedule['closeTime']][] = $schedule['dayName'];
        }
    }
    $scheduleSummary = collect($groups)->map(fn ($days, $hours) => implode(', ', $days).' '.str_replace('-', '–', $hours).' WIB')->implode(' · ');
    $phoneHref = preg_replace('/\D+/', '', $profile->phone);
    if (str_starts_with($phoneHref, '0')) $phoneHref = '62'.substr($phoneHref, 1);
@endphp
<main>
    <section class="hero section-wrap">
        <div class="hero-copy">
            <div class="eyebrow"><span></span> PRAKTIK BIDAN MANDIRI</div>
            <h1>{!! $profile->motto ? e($profile->motto) : 'Perawatan sepenuh hati, untuk <em>ibu dan buah hati.</em>' !!}</h1>
            <p class="hero-lead">{{ $profile->description ?: 'Pendampingan kesehatan ibu dan anak dengan perhatian di setiap tahap.' }}</p>
            <div class="hero-actions">
                <a class="button-primary" href="{{ route('registration.create') }}">Daftar pemeriksaan <span aria-hidden="true">→</span></a>
                <a class="text-link" href="{{ route('queue.check') }}">Cek antrean <span aria-hidden="true">↗</span></a>
            </div>
            <div class="hero-meta">
                <span class="practice-badge {{ $open ? '' : 'is-closed' }}"><i></i>{{ $open ? 'Pendaftaran dibuka' : 'Pendaftaran ditutup' }}</span>
                <span><span aria-hidden="true">◷</span> {{ $scheduleSummary ?: 'Jadwal menunggu pengaturan' }}</span>
                <span><span aria-hidden="true">▦</span> <time data-bandung-clock></time> WIB</span>
            </div>
        </div>
        <div class="hero-visual">
            <div class="photo-frame">
                <img data-default-photo @if ($profile->photo_url) src="{{ $profile->photo_url }}" @endif alt="Foto {{ $profile->name }}" onerror="this.remove()">
                <div class="photo-fallback"><span aria-hidden="true" style="font-size:56px">♥</span><span>Ruang praktik yang hangat</span></div>
                <div class="photo-caption">
                    <span class="caption-icon">♥</span>
                    <span><strong>{{ $profile->name }}{{ $profile->title ? ', '.$profile->title : '' }}</strong>
                        @if ($profile->phone)<a href="tel:+{{ $phoneHref }}">{{ $profile->phone }}</a>@endif
                    </span>
                </div>
            </div>
            <div class="hero-stamp"><span aria-hidden="true">✦</span><span>Nyaman<br>& terpercaya</span></div>
        </div>
        <div class="hero-note"><span>01</span> KESEHATAN IBU & ANAK</div>
    </section>

    <section class="quick-info" id="jadwal">
        <div class="quick-inner">
            <div class="quick-heading"><span class="section-kicker">KUNJUNGI KAMI</span><h2>Siap menemani<br>Anda hari ini.</h2></div>
            <div class="quick-item"><span class="quick-icon">◷</span><div><small>JAM PRAKTIK</small><strong>{{ $scheduleSummary ? 'Jadwal praktik' : 'Belum tersedia' }}</strong><span>{{ $scheduleSummary ?: 'Jadwal belum diatur' }}</span></div></div>
            <a class="quick-item" href="{{ $profile->maps_url ?: '#' }}" @if ($profile->maps_url) target="_blank" rel="noreferrer" @endif>
                <span class="quick-icon coral">⌖</span><div><small>LOKASI</small><strong>{{ $profile->address }}</strong><span>{{ $profile->maps_url ? 'Buka lokasi di Google Maps' : 'Alamat praktik' }}</span></div>
                @if ($profile->maps_url)<span aria-hidden="true">↗</span>@endif
            </a>
            <a class="quick-contact" href="{{ $profile->phone ? 'tel:+'.$phoneHref : '#' }}">
                <span class="quick-icon">☎</span><div><small>HUBUNGI BIDAN</small><strong>{{ $profile->phone ?: 'Nomor belum diatur' }}</strong></div>
            </a>
        </div>
    </section>

    <section class="care-section section-wrap">
        <div class="care-intro"><span class="section-kicker">LAYANAN KAMI</span><h2>Perhatian yang tepat,<br><em>di setiap perjalanan.</em></h2><p>Pilih layanan yang Anda perlukan. Kami akan membantu Anda merasa lebih tenang dan terawat.</p><a class="text-link" href="{{ route('registration.create') }}">Lihat jadwal tersedia <span aria-hidden="true">→</span></a></div>
        <div class="care-list">
            @forelse ($visitTypes as $index => $type)
                <div class="care-row"><span class="care-number {{ ['green', 'rose', 'yellow'][$index % 3] }}">{{ str_pad($index + 1, 2, '0', STR_PAD_LEFT) }}</span><div><h3>{{ $type->name }}</h3><p>{{ $type->description ?: 'Layanan kesehatan ibu dan anak dengan perhatian khusus.' }}</p></div><span class="care-arrow" aria-hidden="true">↗</span></div>
            @empty
                <p class="empty-list">Jenis pemeriksaan belum diatur.</p>
            @endforelse
        </div>
    </section>

    <section class="booking-band"><div class="booking-band-inner"><div><span class="section-kicker">LANGKAH MUDAH</span><h2>Waktu Anda berharga.<br>Atur kunjungan lebih dulu.</h2></div><a class="button-light" href="{{ route('registration.create') }}">Pilih jadwal pemeriksaan <span aria-hidden="true">→</span></a><span class="band-decoration">♡</span></div></section>
</main>
@endsection