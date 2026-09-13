<?php

namespace Database\Seeders;

use App\Models\Indicator;
use App\Models\KinerjaTree;
use App\Models\KnowledgePack;
use App\Models\Node;
use App\Models\NodeLink;
use App\Models\Organization;
use App\Models\Review;
use App\Models\Sector;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // ─────────────────────────────────────────────────────────────
        // Organisasi contoh (K/L dan Pemda)
        // ─────────────────────────────────────────────────────────────
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

        $kementan = Organization::create([
            'name' => 'Kementerian Pertanian',
            'type' => 'kementerian_lembaga',
            'code' => 'KEMENTAN',
        ]);

        // ─────────────────────────────────────────────────────────────
        // Akun demo
        // ─────────────────────────────────────────────────────────────

        // Kemenag
        User::create([
            'name' => 'Admin Kemenag',
            'email' => 'admin@kemenag.test',
            'password' => Hash::make('password'),
            'organization_id' => $kemenag->id,
            'role' => 'admin',
        ]);

        // Disdik
        User::create([
            'name' => 'Planner Disdik',
            'email' => 'planner@disdik.test',
            'password' => Hash::make('password'),
            'organization_id' => $dinasPendidikan->id,
            'role' => 'planner',
        ]);

        // Kementan — 3 role lengkap untuk demo
        User::create([
            'name' => 'Admin Kementan',
            'email' => 'admin@kementan.test',
            'password' => Hash::make('password'),
            'organization_id' => $kementan->id,
            'role' => 'admin',
        ]);

        $plannerKementan = User::create([
            'name' => 'Perencana Kementan',
            'email' => 'planner@kementan.test',
            'password' => Hash::make('password'),
            'organization_id' => $kementan->id,
            'role' => 'planner',
        ]);

        $reviewerKementan = User::create([
            'name' => 'Reviewer Kementan',
            'email' => 'reviewer@kementan.test',
            'password' => Hash::make('password'),
            'organization_id' => $kementan->id,
            'role' => 'reviewer',
        ]);

        // ─────────────────────────────────────────────────────────────
        // Sektor + knowledge pack (kurasi, BUKAN hasil web search)
        // ─────────────────────────────────────────────────────────────

        // Sektor Pendidikan (global)
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

        // Sektor Pertanian (global)
        $sektorPertanian = Sector::create([
            'organization_id' => null,
            'name' => 'Pertanian',
            'slug' => 'pertanian',
            'description' => 'Sektor pertanian: ketahanan pangan, produktivitas, kesejahteraan petani, dan daya saing produk pertanian.',
        ]);

        KnowledgePack::create([
            'sector_id' => $sektorPertanian->id,
            'title' => 'Dimensi Hasil Pertanian',
            'content' => implode("\n", [
                'Ketahanan pangan: ketersediaan, akses, pemanfaatan, dan stabilitas pangan',
                'Produktivitas: hasil per hektar komoditas pangan dan hortikultura',
                'Kesejahteraan petani: Nilai Tukar Petani (NTP) dan pendapatan usaha tani',
                'Daya saing: nilai tambah, olahan, dan ekspor produk pertanian',
                'Keberlanjutan: pengelolaan lahan, air, dan adaptasi perubahan iklim',
            ]),
            'source' => 'Referensi kebijakan pertanian (kurasi internal)',
            'version' => 1,
        ]);

        KnowledgePack::create([
            'sector_id' => $sektorPertanian->id,
            'title' => 'Indikator Umum Pertanian',
            'content' => implode("\n", [
                'Produksi padi (juta ton GKG)',
                'Nilai Tukar Petani (NTP)',
                'Luas tanam & luas panen (ha)',
                'Produktivitas padi (ku/ha)',
                'Skor Pola Pangan Harapan (PPH)',
                'Persentase lahan beririgasi',
            ]),
            'source' => 'Referensi kebijakan pertanian (kurasi internal)',
            'version' => 1,
        ]);

        // ─────────────────────────────────────────────────────────────
        // Pohon kinerja contoh — Kementan
        // ─────────────────────────────────────────────────────────────
        $tree = KinerjaTree::create([
            'organization_id' => $kementan->id,
            'sector_id' => $sektorPertanian->id,
            'name' => 'Renstra Kementan 2025–2029',
            'period_start' => 2025,
            'period_end' => 2029,
            'status' => 'draft',
        ]);

        // -- Sasaran Strategis (SS) — root outcome
        $ss = Node::create([
            'tree_id' => $tree->id,
            'code' => 'SS',
            'statement' => 'Meningkatnya Ketahanan Pangan dan Kesejahteraan Petani',
            'type' => 'outcome',
            'source_type' => 'user_edited',
            'order' => 0,
        ]);

        // -- SS.1 — outcome turunan
        $ss1 = Node::create([
            'tree_id' => $tree->id,
            'code' => 'SS.1',
            'statement' => 'Meningkatnya Produksi dan Produktivitas Komoditas Pertanian',
            'type' => 'outcome',
            'source_type' => 'user_edited',
            'order' => 1,
        ]);

        // -- SS.2 — outcome turunan
        $ss2 = Node::create([
            'tree_id' => $tree->id,
            'code' => 'SS.2',
            'statement' => 'Meningkatnya Nilai Tambah dan Daya Saing Produk Pertanian',
            'type' => 'outcome',
            'source_type' => 'user_edited',
            'order' => 2,
        ]);

        // -- SS.3 — outcome turunan
        $ss3 = Node::create([
            'tree_id' => $tree->id,
            'code' => 'SS.3',
            'statement' => 'Meningkatnya Kesejahteraan Petani',
            'type' => 'outcome',
            'source_type' => 'user_edited',
            'order' => 3,
        ]);

        // -- O.1 — output dari SS.1
        $o1 = Node::create([
            'tree_id' => $tree->id,
            'code' => 'O.1',
            'statement' => 'Tersedianya Sarana dan Prasarana Pertanian yang Memadai',
            'type' => 'output',
            'source_type' => 'user_edited',
            'order' => 4,
        ]);

        // -- O.2 — output dari SS.1
        $o2 = Node::create([
            'tree_id' => $tree->id,
            'code' => 'O.2',
            'statement' => 'Meningkatnya Penerapan Teknologi Pertanian Modern',
            'type' => 'output',
            'source_type' => 'ai_proposed',
            'order' => 5,
        ]);

        // -- Relasi antar sasaran (DAG)
        NodeLink::create([
            'tree_id' => $tree->id,
            'parent_node_id' => $ss->id,
            'child_node_id' => $ss1->id,
            'reason' => 'Produksi dan produktivitas adalah faktor utama ketahanan pangan',
        ]);

        NodeLink::create([
            'tree_id' => $tree->id,
            'parent_node_id' => $ss->id,
            'child_node_id' => $ss2->id,
            'reason' => 'Nilai tambah dan daya saing mendukung keberlanjutan ketahanan pangan',
        ]);

        NodeLink::create([
            'tree_id' => $tree->id,
            'parent_node_id' => $ss->id,
            'child_node_id' => $ss3->id,
            'reason' => 'Kesejahteraan petani adalah dimensi langsung dari sasaran strategis',
        ]);

        NodeLink::create([
            'tree_id' => $tree->id,
            'parent_node_id' => $ss1->id,
            'child_node_id' => $o1->id,
            'reason' => 'Sarana prasarana (irigasi, alsintan) menunjang peningkatan produksi',
        ]);

        NodeLink::create([
            'tree_id' => $tree->id,
            'parent_node_id' => $ss1->id,
            'child_node_id' => $o2->id,
            'reason' => 'Teknologi modern meningkatkan efisiensi dan produktivitas',
        ]);

        // -- Indikator
        Indicator::create([
            'node_id' => $ss->id,
            'name' => 'Skor Pola Pangan Harapan (PPH) Konsumsi',
            'definition' => 'Komposisi pangan yang mencerminkan keragaman dan keseimbangan gizi',
            'unit' => 'skor',
            'direction' => 'naik',
            'data_source' => 'BPS / Susenas',
            'baseline' => '92.5',
            'target' => '97.0',
        ]);

        Indicator::create([
            'node_id' => $ss1->id,
            'name' => 'Produksi padi nasional',
            'definition' => 'Total produksi padi dalam bentuk Gabah Kering Giling',
            'unit' => 'juta ton GKG',
            'direction' => 'naik',
            'data_source' => 'BPS / Kementan',
            'baseline' => '54.7',
            'target' => '58.0',
        ]);

        Indicator::create([
            'node_id' => $ss1->id,
            'name' => 'Produktivitas padi',
            'definition' => 'Rata-rata hasil padi per hektar lahan panen',
            'unit' => 'ku/ha',
            'direction' => 'naik',
            'data_source' => 'BPS',
            'baseline' => '52.3',
            'target' => '55.0',
        ]);

        Indicator::create([
            'node_id' => $ss2->id,
            'name' => 'Nilai ekspor produk pertanian',
            'definition' => 'Total nilai ekspor komoditas pertanian Indonesia',
            'unit' => 'miliar USD',
            'direction' => 'naik',
            'data_source' => 'BPS / Kemendag',
            'baseline' => '5.2',
            'target' => '7.5',
        ]);

        Indicator::create([
            'node_id' => $ss3->id,
            'name' => 'Nilai Tukar Petani (NTP)',
            'definition' => 'Rasio indeks harga yang diterima petani terhadap indeks harga yang dibayar petani',
            'unit' => 'indeks',
            'direction' => 'naik',
            'data_source' => 'BPS',
            'baseline' => '107.5',
            'target' => '115.0',
        ]);

        Indicator::create([
            'node_id' => $o1->id,
            'name' => 'Luas lahan beririgasi baru/rehabilitasi',
            'definition' => 'Luas lahan pertanian yang mendapat jaringan irigasi baru atau rehabilitasi',
            'unit' => 'ribu ha',
            'direction' => 'naik',
            'data_source' => 'Kementan / Kemen PUPR',
            'baseline' => '25.0',
            'target' => '50.0',
        ]);

        Indicator::create([
            'node_id' => $o2->id,
            'name' => 'Jumlah kelompok tani pengguna teknologi',
            'definition' => 'Kelompok tani yang menerapkan teknologi pertanian modern (alsintan, benih unggul, presisi)',
            'unit' => 'kelompok',
            'direction' => 'naik',
            'data_source' => 'Kementan',
            'baseline' => '15000',
            'target' => '30000',
        ]);

        // -- Review contoh (reviewer approve sasaran strategis)
        Review::create([
            'tree_id' => $tree->id,
            'node_id' => $ss->id,
            'user_id' => $reviewerKementan->id,
            'decision' => 'approve',
            'comment' => 'Sasaran strategis sudah sesuai dengan arah kebijakan Renstra 2025–2029.',
        ]);
    }
}
