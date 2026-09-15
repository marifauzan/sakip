<?php

namespace Database\Seeders;

use App\Models\KnowledgePack;
use App\Models\Sector;
use Illuminate\Database\Seeder;

/**
 * Knowledge pack untuk sektor-sektor tambahan.
 * Konten KURASI (bukan hasil web search), mengikuti pola yang sama
 * dengan sektor Pendidikan & Pertanian: Dimensi Hasil + Indikator Umum.
 */
class SectorKnowledgeSeeder extends Seeder
{
    public function run(): void
    {
        $sectors = [
            'kesehatan' => [
                'name' => 'Kesehatan',
                'description' => 'Sektor kesehatan: akses, mutu layanan, dan derajat kesehatan masyarakat.',
                'dimensions' => [
                    'Akses: keterjangkauan dan pemerataan layanan kesehatan',
                    'Mutu: keselamatan dan efektivitas pelayanan klinis',
                    'Derajat kesehatan: status kesehatan ibu, anak, dan gizi masyarakat',
                    'Pencegahan: pengendalian penyakit menular dan tidak menular',
                    'Tata kelola: pembiayaan dan ketersediaan tenaga kesehatan',
                ],
                'indicators' => [
                    'Angka Kematian Ibu (AKI) per 100.000 kelahiran hidup',
                    'Angka Kematian Bayi (AKB) per 1.000 kelahiran hidup',
                    'Prevalensi stunting pada balita (%)',
                    'Persentase penduduk dengan akses sanitasi layak',
                    'Cakupan imunisasi dasar lengkap (%)',
                    'Angka kesakitan tuberkulosis (per 100.000 penduduk)',
                ],
            ],
            'infrastruktur' => [
                'name' => 'Infrastruktur',
                'description' => 'Sektor infrastruktur: konektivitas, ketahanan bangunan, dan layanan dasar.',
                'dimensions' => [
                    'Konektivitas: kualitas dan keterhubungan jaringan jalan/jembatan',
                    'Ketersediaan: akses rumah tangga terhadap layanan dasar',
                    'Ketahanan: kesiapan infrastruktur terhadap bencana',
                    'Keberlanjutan: pemeliharaan dan umur layanan aset',
                ],
                'indicators' => [
                    'Persentase kemantapan jalan (nasional/daerah)',
                    'Persentase rumah tangga dengan akses air minum layak',
                    'Panjang jalan terbangun (km)',
                    'Persentase kawasan permukiman kumuh (% dari luas kawasan)',
                    'Rasio elektrifikasi (%)',
                ],
            ],
            'ekonomi' => [
                'name' => 'Ekonomi',
                'description' => 'Sektor ekonomi: pertumbuhan, daya saing, dan kesejahteraan.',
                'dimensions' => [
                    'Pertumbuhan: laju pertumbuhan ekonomi wilayah',
                    'Dayasaing: produktivitas dan nilai tambah usaha',
                    'Investasi: iklim usaha dan realisasi penanaman modal',
                    'Kesejahteraan: daya beli dan penurunan kemiskinan',
                ],
                'indicators' => [
                    'Laju pertumbuhan ekonomi (%)',
                    'Tingkat Pengangguran Terbuka (TPT) (%)',
                    'Realisasi investasi (Rp triliun)',
                    'Persentase penduduk miskin (%)',
                    'Produk Domestik Regional Bruto (PDRB) per kapita',
                ],
            ],
            'lingkungan-hidup' => [
                'name' => 'Lingkungan Hidup',
                'description' => 'Sektor lingkungan: kualitas lingkungan, pengelolaan sampah, dan mitigasi iklim.',
                'dimensions' => [
                    'Kualitas udara: indeks pencemaran dan emisi',
                    'Kualitas air: pengelolaan sungai dan air limbah',
                    'Pengelolaan sampah: pengurangan dan daur ulang',
                    'Iklim: mitigasi dan adaptasi perubahan iklim',
                    'Keanekaragaman hayati: konservasi kawasan',
                ],
                'indicators' => [
                    'Indeks Kualitas Lingkungan Hidup (IKLH)',
                    'Indeks Kualitas Udara (IKU)',
                    'Indeks Kualitas Air (IKA)',
                    'Persentase pengelolaan sampah terkelola (%)',
                    'Luas kawasan konservasi (ha)',
                ],
            ],
            'pariwisata' => [
                'name' => 'Pariwisata',
                'description' => 'Sektor pariwisata: daya tarik, kunjungan, dan nilai ekonomi kreatif.',
                'dimensions' => [
                    'Daya tarik: kualitas destinasi dan atraksi',
                    'Aksesibilitas: keterjangkauan menuju destinasi',
                    'Kunjungan: pertumbuhan wisatawan domestik & mancanegara',
                    'Ekonomi kreatif: kontribusi usaha subsektor kreatif',
                ],
                'indicators' => [
                    'Jumlah kunjungan wisatawan mancanegara',
                    'Jumlah kunjungan wisatawan nusantara',
                    'Kontribusi pariwisata terhadap PDRB (%)',
                    'Tingkat hunian hotel (%)',
                    'Jumlah desa wisata terkelola',
                ],
            ],
        ];

        foreach ($sectors as $slug => $data) {
            $sector = Sector::firstOrCreate(
                ['slug' => $slug],
                [
                    'organization_id' => null,
                    'name' => $data['name'],
                    'description' => $data['description'],
                ]
            );

            KnowledgePack::firstOrCreate(
                ['sector_id' => $sector->id, 'title' => 'Dimensi Hasil '.$data['name']],
                [
                    'content' => implode("\n", $data['dimensions']),
                    'source' => 'Referensi kebijakan sektoral (kurasi internal)',
                    'version' => 1,
                    'is_active' => true,
                ]
            );

            KnowledgePack::firstOrCreate(
                ['sector_id' => $sector->id, 'title' => 'Indikator Umum '.$data['name']],
                [
                    'content' => implode("\n", $data['indicators']),
                    'source' => 'Referensi kebijakan sektoral (kurasi internal)',
                    'version' => 1,
                    'is_active' => true,
                ]
            );
        }

        $this->command?->info('Seeded '.count($sectors).' sector knowledge packs.');
    }
}
