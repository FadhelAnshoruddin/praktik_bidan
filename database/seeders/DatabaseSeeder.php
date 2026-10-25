<?php

namespace Database\Seeders;

use App\Models\Profile;
use App\Models\VisitType;
use App\Models\WeeklySchedule;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        Profile::query()->updateOrCreate(['id' => 1], [
            'name' => 'Bidan Mila',
            'title' => '',
            'motto' => '',
            'description' => '',
            'photo_url' => '',
            'phone' => '',
            'address' => 'Unnamed Road, Cangkuang Wetan, Kec. Dayeuhkolot, Kabupaten Bandung, Jawa Barat 40238',
            'maps_url' => 'https://maps.app.goo.gl/rssdPfhDPcTZTPo69',
            'duration' => 20,
            'capacity' => 20,
        ]);

        foreach (range(0, 6) as $day) {
            WeeklySchedule::query()->updateOrCreate(['day_of_week' => $day], ['is_open' => false]);
        }

        foreach ([
            'Pemeriksaan kehamilan',
            'Pemeriksaan ibu',
            'Pemeriksaan bayi',
            'Kontrol',
            'Konsultasi',
            'Imunisasi',
        ] as $name) {
            VisitType::query()->firstOrCreate(['name' => $name]);
        }

        DB::table('system_settings')->updateOrInsert(
            ['key' => 'practice_open'],
            ['value' => 'false'],
        );
    }
}