@extends('layouts.public')

@section('title', 'Pendaftaran | Praktik Bidan Mila')

@section('content')
@php
    $availableSlots = collect($booking['slots'])->where('available', true);
    $oldTime = old('appointment_time');
    $selectedTime = $availableSlots->contains('time', $oldTime) ? $oldTime : ($availableSlots->first()['time'] ?? '');
@endphp
<main class="form-page section-wrap">
    <a class="back-link" href="{{ route('home') }}"><span aria-hidden="true">←</span> Kembali ke beranda</a>
    <div class="form-layout">
        <div class="form-aside"><span class="section-kicker">PENDAFTARAN PASIEN</span><h1>Satu langkah kecil untuk <em>merawat diri.</em></h1><p>Isi data singkat berikut untuk mengatur kunjungan. Tidak perlu membuat akun.</p><div class="form-aside-note"><span aria-hidden="true">♢</span><span><strong>Data pendaftaran terenkripsi.</strong><br>Hanya bidan dapat melihat detail pasien.</span></div></div>
        <form class="registration-form" method="post" action="{{ route('registration.store') }}" data-registration-form data-today-slots-url="{{ route('registration.today-slots') }}">
            @csrf
            <input type="hidden" name="date" value="{{ $booking['date'] }}" data-registration-date>
            <div class="form-heading"><div><span>01 — DATA PASIEN</span><h2>Informasi kunjungan</h2></div><span class="required-note">* Wajib diisi</span></div>
            <label>Nama lengkap <b>*</b><input name="name" value="{{ old('name') }}" placeholder="Nama sesuai identitas" autocomplete="name" required>@error('name')<small class="lookup-error">{{ $message }}</small>@enderror</label>
            <label>Nomor WhatsApp <b>*</b><div class="input-with-icon"><span aria-hidden="true">☎</span><input name="phone" value="{{ old('phone') }}" placeholder="08xx xxxx xxxx" inputmode="tel" autocomplete="tel" required></div><small>Untuk keperluan konfirmasi jika dibutuhkan.</small>@error('phone')<small class="lookup-error">{{ $message }}</small>@enderror</label>
            <div class="form-two-col">
                <label>Jenis pemeriksaan <b>*</b><div class="select-wrap"><select name="type" required><option value="">Pilih pemeriksaan</option>@foreach ($visitTypes as $type)<option value="{{ $type->name }}" @selected(old('type', $visitTypes->first()?->name) === $type->name)>{{ $type->name }}</option>@endforeach</select><span aria-hidden="true">⌄</span></div></label>
                <label>Hari kunjungan <b>*</b><span class="today-date-value" data-today-date-label>{{ $booking['dateLabel'] }}</span><small>Jadwal hanya tersedia untuk hari ini.</small></label>
            </div>
            <label>Jam pemeriksaan <b>*</b><div class="select-wrap"><select name="appointment_time" required data-registration-time-select @disabled(!$availableSlots->isNotEmpty())><option value="">Pilih jam pemeriksaan</option>@foreach ($booking['slots'] as $slot)<option value="{{ $slot['time'] }}" @disabled(!$slot['available']) @selected($selectedTime === $slot['time'])>{{ $slot['time'] }} WIB{{ $slot['reason'] ? ' · '.$slot['reason'] : '' }}</option>@endforeach</select><span aria-hidden="true">◷</span></div>@error('appointment_time')<small class="lookup-error">{{ $message }}</small>@enderror</label>
            <label>Keluhan atau catatan <span class="optional">Opsional</span><textarea name="complaint" rows="3" maxlength="1000" placeholder="Ceritakan singkat keperluan kunjungan Anda">{{ old('complaint') }}</textarea></label>
            <div class="inline-notice" data-registration-no-slots @if ($open && $booking['isScheduled'] && $availableSlots->isNotEmpty()) hidden @endif><span aria-hidden="true">◷</span> @if (!$open) Pendaftaran sedang ditutup sementara. @elseif (!$booking['isScheduled']) Praktik tidak buka hari ini. @else Semua jam pemeriksaan hari ini sudah lewat atau terisi. @endif</div>
            <button class="button-primary form-submit" type="submit" data-registration-submit @disabled(!$open || !$booking['isScheduled'] || !$availableSlots->isNotEmpty())>Konfirmasi pendaftaran <span aria-hidden="true">→</span></button>
            <p class="form-privacy"><span aria-hidden="true">♢</span> Data disimpan terenkripsi di database praktik.</p>
        </form>
    </div>
</main>
@endsection