<?php

namespace Database\Seeders;

use App\Models\KnowledgePack;
use App\Models\Organization;
use App\Models\Sector;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Organisasi contoh (K/L dan Pemda)
        $kemenag = Organization::create([
            'name' => 'Kementerian Agama (contoh)',
            'type' => 'kementerian_lembaga',
            'code' => 'KEMENAG',
        ]);

        $dinasPendidikan = Organization::create([
            'name' => 'Dinas Pendidikan Provinsi (contoh)',
            'type' => 'pemerintah_daerah',
            'code' => 'DISDIK',
        ]);

        // Admin & planner
        User::create([
            'name' => 'Admin Kemenag',
            'email' => 'admin@kemenag.test',
            'password' => Hash::make('password'),
            'organization_id' => $kemenag->id,
            'role' => 'admin',
        ]);

        User::create([
            'name' => 'Planner Disdik',
            'email' => 'planner@disdik.test',
            'password' => Hash::make('password'),
            'organization_id' => $dinasPendidikan->id,
            'role' => 'planner',
        ]);

        // Sektor + knowledge pack (kurasi, BUKAN hasil web search)
        $sektorPendidikan = Sector::create([
            'organization_id' => null,
            'name' => 'Pendidikan',
            'slug' => 'pendidikan',
            'description' => 'Sektor pendidikan: mutu, akses, dan tata kelola pendidikan.',
        ]);

        KnowledgePack::create([
            'sector_id' => $sektorPendidikan->id,
            'title' => 'Dimensi Hasil Pendidikan',
            'content' => implode("\n", [
                'Akses: partisipasi pendidikan (APK/APM)',
                'Mutu: kualitas pembelajaran dan hasil belajar',
                'Relevansi: kesesuaian lulusan dengan kebutuhan',
                'Tata kelola: efektivitas layanan pendidikan',
            ]),
            'source' => 'Referensi kebijakan pendidikan (kurasi internal)',
            'version' => 1,
        ]);

        KnowledgePack::create([
            'sector_id' => $sektorPendidikan->id,
            'title' => 'Indikator Umum Pendidikan',
            'content' => implode("\n", [
                'Angka Partisipasi Kasar (APK)',
                'Angka Partisipasi Murni (APM)',
                'Rata-rata lama sekolah (RLS)',
                'Harapan lama sekolah (HLS)',
                'Persentase guru berkualifikasi',
            ]),
            'source' => 'Referensi kebijakan pendidikan (kurasi internal)',
            'version' => 1,
        ]);
    }
}
