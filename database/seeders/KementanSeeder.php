<?php

namespace Database\Seeders;

use App\Models\KnowledgePack;
use App\Models\Organization;
use App\Models\Sector;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class KementanSeeder extends Seeder
{
    public function run(): void
    {
        // Organisasi Kementerian Pertanian
        $kementan = Organization::firstOrCreate(
            ['code' => 'KEMENTAN'],
            [
                'name' => 'Kementerian Pertanian',
                'type' => 'kementerian_lembaga',
            ]
        );

        // Admin + planner + reviewer
        $users = [
            ['name' => 'Admin Kementan', 'email' => 'admin@kementan.test', 'role' => 'admin'],
            ['name' => 'Planner Kementan', 'email' => 'planner@kementan.test', 'role' => 'planner'],
            ['name' => 'Reviewer Kementan', 'email' => 'reviewer@kementan.test', 'role' => 'reviewer'],
        ];

        foreach ($users as $u) {
            User::firstOrCreate(
                ['email' => $u['email']],
                [
                    'name' => $u['name'],
                    'password' => Hash::make('password'),
                    'organization_id' => $kementan->id,
                    'role' => $u['role'],
                ]
            );
        }

        // Sektor Pertanian + knowledge pack (kurasi, bukan web search)
        $sektorPertanian = Sector::firstOrCreate(
            ['slug' => 'pertanian'],
            [
                'organization_id' => null,
                'name' => 'Pertanian',
                'description' => 'Sektor pertanian: produksi, ketahanan pangan, dan kesejahteraan petani.',
            ]
        );

        $packs = [
            [
                'title' => 'Dimensi Hasil Pertanian',
                'content' => implode("\n", [
                    'Produksi: peningkatan produksi komoditas strategis (padi, jagung, kedelai, dll)',
                    'Produktivitas: peningkatan produktivitas lahan dan hasil per satuan luas',
                    'Ketahanan pangan: ketersediaan, akses, dan stabilitas pangan',
                    'Kesejahteraan petani: peningkatan pendapatan dan nilai tukar petani (NTP)',
                    'Keberlanjutan: pengelolaan sumber daya pertanian yang berkelanjutan',
                ]),
                'source' => 'Referensi kebijakan pertanian (kurasi internal)',
            ],
            [
                'title' => 'Indikator Umum Pertanian',
                'content' => implode("\n", [
                    'Produksi padi (juta ton)',
                    'Produksi komoditas strategis (jagung, kedelai, dll)',
                    'Nilai Tukar Petani (NTP)',
                    'Nilai Tukar Usaha Rumah Tangga Pertanian (NTUP)',
                    'Produktivitas padi (kuintal/hektar)',
                    'Luas lahan pertanian pangan berkelanjutan',
                ]),
                'source' => 'Referensi kebijakan pertanian (kurasi internal)',
            ],
        ];

        foreach ($packs as $pack) {
            KnowledgePack::firstOrCreate(
                ['sector_id' => $sektorPertanian->id, 'title' => $pack['title']],
                [
                    'content' => $pack['content'],
                    'source' => $pack['source'],
                    'version' => 1,
                    'is_active' => true,
                ]
            );
        }
    }
}
