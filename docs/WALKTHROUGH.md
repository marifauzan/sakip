# Walkthrough — SAKIP v1 & v2

Panduan langkah demi langkah untuk **developer** yang baru bergabung: apa saja yang dibangun di setiap versi, dan bagaimana menguji setiap fitur.

---

## Bagian A — Walkthrough v1 (MVP)

### A.1 Autentikasi & Multi-Tenancy

**Yang dibangun:** login session-based + isolasi data per organisasi.

**Uji manual:**
```bash
# Login → otomatis redirect ke dashboard
GET  /login          → form login
POST /login          → set session, redirect /dashboard

# Akses tanpa login → redirect ke /login (302)
curl -o /dev/null -w "%{http_code}" http://localhost/dashboard   # → 302
```

**Uji isolasi tenant:**
```bash
php artisan test --filter=MultiTenancyTest
```

**Di balik layar:**
- Setiap model bisnis punya `organization_id`.
- Endpoint memanggil `abort_unless($resource->organization_id === $user->organization_id, 403)`.
- Lihat: `app/Http/Controllers/*Controller.php`, pola `authorizeOrganization()`.

---

### A.2 Dokumen — Unggah & Ekstraksi

**Yang dibangun:** unggah PDF/DOCX/TXT → ekstraksi teks otomatis (background) → chunking dengan metadata halaman/bab.

**Alur:**
```
Upload (form) → Document(status=uploaded) → ExtractDocument (queue)
   → status=extracting → DocumentExtractor::extract()
   → chunk per paragraf + deteksi bab → status=extracted
```

**Uji manual:**
1. Buka `/documents`
2. Isi judul + jenis + unggah file PDF/DOCX
3. Tunggu ~3-10 detik (halaman auto-refresh), status berubah → **Selesai Diekstrak**
4. Klik judul dokumen → lihat daftar chunk + metadata halaman

**Uji otomatis:**
```bash
php artisan test --filter=DocumentTest
```

**Verifikasi ekstraksi (CLI):**
```bash
php artisan tinker
>>> $d = App\Models\Document::latest()->first();
>>> $d->status;                    // 'extracted'
>>> $d->chunks()->count();         // jumlah chunk
>>> $d->chunks()->first()->content; // isi teks
```

**Poin teknis:**
- PDF via `smalot/pdfparser`; DOCX via ekstraktor ZIP/XML sendiri (hindari kerentanan XXE PHPWord).
- Chunking **per paragraf** (bukan per-N kata) agar judul bab tidak terpisah dari isinya.
- File disimpan di disk `private` (`storage/app/private`) — tidak bisa diakses URL publik.

---

### A.3 Editor Perjenjangan Kinerja

**Yang dibangun:** sasaran (node), hubungan antarsasaran (DAG), indikator.

**Uji manual:**
1. Buka `/kinerja` → buat rancangan baru (isi nama + periode)
2. Di halaman rancangan:
   - **Tambah Sasaran:** isi rumusan + jenis (outcome/output/aktivitas)
   - **Hubungkan Sasaran:** pilih parent → child + alasan sebab-akibat
   - **Tambah indikator:** di tiap simpul (nama, satuan, arah, baseline, target)

**Uji validasi anti-siklus (krusial):**
```bash
php artisan test --filter=DagValidationTest
```

Kasus yang diuji:
| Kasus | Harus |
|---|---|
| A → A (self-loop) | Ditolak |
| A → B, lalu B → A | Ditolak (siklus) |
| A → B → C, lalu C → A | Ditolak (siklus transitif) |
| A → B → C, lalu A → C | **Diterima** (redundan, bukan siklus) |

**Di balik layar:** `DagValidator::wouldCreateCycle()` — DFS iteratif.

---

### A.4 Rekomendasi AI

**Yang dibangun:** usulan turunan sasaran & indikator dari AI.

**Uji manual:**
1. Buka rancangan → pilih simpul
2. Klik **"✨ Usulkan turunan"** → tunggu 5-30 detik
3. Muncul panel ungu berisi usulan + alasan + asumsi + **sitasi**
4. Klik **"Terima sebagai sasaran"** → node baru dibuat dengan `source_type=ai_proposed`

**Uji otomatis:**
```bash
php artisan test --filter=AiRecommendationTest
```

**Verifikasi manual via CLI:**
```bash
php artisan tinker
>>> $n = App\Models\Node::first();
>>> $svc = app(App\Services\AiRecommendationService::class);
>>> $res = $svc->recommendChildren($n);
>>> $res['recommendations'][0]['statement'];
>>> $res['recommendations'][0]['references'];  // contoh: ['D7-H66']
>>> $res['sources'];                            // metadata dokumen+halaman
```

---

### A.5 Reviu & Ekspor

**Yang dibangun:** komentar/approve/reject + ekspor Markdown/JSON.

**Uji manual:**
1. Di halaman rancangan, bagian **Reviu & Persetujuan**
2. Pilih simpul (atau "Seluruh rancangan") + keputusan + komentar → Simpan
3. Klik **Markdown** / **JSON** di bagian **Ekspor** → file terunduh

**Uji otomatis:**
```bash
php artisan test --filter=ReviewAndExportTest
```

**Efek "approve":** node `source_type` berubah menjadi `approved`.

---

## Bagian B — Walkthrough v2

### B.1 Deteksi Sektor + Konfirmasi User

**Yang dibangun:** AI mendeteksi sektor dokumen, user **wajib mengonfirmasi**.

**Alur (dua langkah — disengaja):**
```
Klik "Deteksi dengan AI"
   → detectSector() → {name, confidence}
   → sector_id di-set, sector_confirmed_at = NULL
   → Badge KUNING "Usulan AI (perlu konfirmasi)"

Pilih sektor dari dropdown → Klik "Konfirmasi Sektor"
   → sector_confirmed_at = now()
   → Badge HIJAU "Terkonfirmasi"
```

**Uji manual:**
1. Buka `/documents/{id}` (dokumen harus sudah `extracted`)
2. Klik **"Deteksi dengan AI"**
3. Periksa usulan → bila perlu, ubah lewat dropdown
4. Klik **"Konfirmasi Sektor"**

**Uji otomatis:**
```bash
php artisan test --filter=DocumentSectorTest
```

**Mengapa dua langkah?** Kalau AI salah klasifikasi (mis. dokumen kesehatan dianggap pertanian), rekomendasi berikutnya akan salah arah. Gerbang konfirmasi mencegahnya.

**Daftar sektor yang dikenali:** Pendidikan, Pertanian, Kesehatan, Infrastruktur, Ekonomi, Lingkungan Hidup, Pariwisata, atau "Umum".

---

### B.2 Knowledge Pack 7 Sektor

**Yang dibangun:** konten kurasi per sektor (dimensi hasil + indikator umum).

**Uji:**
```bash
php artisan db:seed --class=SectorKnowledgeSeeder
php artisan test --filter=SectorKnowledgeSeederTest
```

**Verifikasi:**
```bash
php artisan tinker
>>> App\Models\Sector::withCount('knowledgePacks')->get()->map(fn($s) => "{$s->name}: {$s->knowledge_packs_count} packs");
```

**Isi tiap sektor** (2 pack):
- **Dimensi Hasil** — aspek hasil yang relevan di sektor itu
- **Indikator Umum** — indikator standar sektor tersebut

**Poin penting:** konten ini **dikurasi manual**, bukan hasil web search. Sumbernya dinyatakan di field `source` dan diberi `version`.

---

### B.3 Sitasi Sumber (Dokumen + Halaman)

**Yang dibangun:** setiap rekomendasi AI menyertakan rujukan halaman.

**Format ID rujukan:** `D<document_id>-H<halaman>` (contoh: `D7-H66`).

**Uji manual:**
1. Minta rekomendasi AI pada simpul
2. Lihat field **sumber** di tiap usulan
3. **Verifikasi:** buka dokumen, cek halaman tersebut

**Verifikasi via CLI:**
```bash
php artisan tinker
>>> $doc = App\Models\Document::find(7);
>>> substr($doc->chunks()->where('page', 66)->first()->content, 0, 400);
```

**Contoh nyata (terverifikasi):**

Sasaran: *"Meningkatnya produksi dan produktivitas komoditas pangan strategis"*

| Rekomendasi AI | Sitasi | Isi halaman 66 |
|---|---|---|
| Tersedianya infrastruktur dan sistem pertanian yang berketahanan terhadap perubahan iklim | `D7-H66` | ✅ "Pembangunan Berketahanan Iklim" |
| Terjaminnya status kesehatan hewan yang bebas dari wabah | `D7-H66` | ✅ "Penguatan Surveilans, Pengendalian KLB/wabah" |

---

### B.4 Hybrid Retrieval (pgvector + Fallback Teks)

**Yang dibangun:** pencarian dokumen bertingkat — semantik bila embedding tersedia, teks bila tidak.

**Cara kerja:**
```
Embedding dikonfigurasi & ada chunk ber-embedding?
   ├─ Ya  → pencarian vektor (cosine, index HNSW)
   └─ Tidak → pencarian teks (semua token harus muncul)
```

**Uji otomatis:**
```bash
php artisan test --filter=SemanticSearchTest
```

**Verifikasi mode yang dipakai:**
```bash
php artisan tinker
>>> $svc = app(App\Services\SemanticSearchService::class);
>>> $res = $svc->search(1, 'produksi pangan', 5);
>>> $res[0]['mode'];   // 'text' (karena embedding belum aktif) atau 'semantic'
```

**Status infrastruktur:**
```bash
# Cek ekstensi & kolom
sudo -u postgres psql -d sakip -c "SELECT extversion FROM pg_extension WHERE extname='vector';"
sudo -u postgres psql -d sakip -c "\d document_chunks" | grep embedding
```

**Mengapa hybrid?** Provider LLM saat ini tidak punya endpoint embedding (HTTP 500). Hybrid memastikan aplikasi **selalu berfungsi**. Detail: [`EMBEDDING-NOTES.md`](EMBEDDING-NOTES.md).

---

### B.5 Import Renstra Kementan

**Yang dibangun:** Renstra Kementerian Pertanian 2025–2029 (268 halaman) ter-import & ter-ekstraksi.

**Data:**
- Dokumen ID 7, sektor Pertanian (terkonfirmasi)
- 268 chunk, semua dengan metadata halaman

**Uji:**
```bash
php artisan tinker
>>> $d = App\Models\Document::find(7);
>>> $d->title;                              // "Renstra Kementerian Pertanian 2025-2029"
>>> $d->chunks()->count();                  // 268
>>> $d->sector->name;                       // "Pertanian"
>>> $d->sector_confirmed_at !== null;       // true
```

**Asal dokumen:** `ppid.pertanian.go.id` — Permentan No. 40 Tahun 2025.

---

## Bagian C — Alur Demo End-to-End (untuk Stakeholder)

Urutan yang paling meyakinkan untuk presentasi:

```
1. Login sebagai admin@kementan.test
   → Tunjukkan dashboard dengan organisasi "Kementerian Pertanian"

2. Buka Dokumen → tunjukkan
   → Renstra Kementan 2025-2029 (268 halaman)
   → Sektor "Pertanian" berbadge hijau TERKONFIRMASI
   → Klik "Deteksi dengan AI" untuk melihat prosesnya

3. Buka Kinerja → tinjau rancangan
   → Sasaran: "Meningkatnya produksi dan produktivitas komoditas pangan strategis"

4. Klik "✨ Usulkan turunan"  ← MOMEN KUNCI
   → Tununjukkan AI mengusulkan turunan
   → Sorot SITASI (D7-H66) → buka halaman 66 → buktikan cocok
   → Klik "Terima sebagai sasaran" untuk menunjukkan kontrol manusia

5. Klik "✨ Usulkan indikator" pada simpul
   → Tunjukkan usulan indikator + satuan

6. Bagian Reviu → tulis komentar, klik Setujui

7. Bagian Ekspor → unduh Markdown → tunjukkan hasilnya
```

**Poin yang ditekankan ke stakeholder:**
1. AI **menyarankan**, manusia **memutuskan** (tombol Terima eksplisit)
2. Setiap rekomendasi **bisa ditelusuri** ke halaman dokumen
3. Data antar-instansi **terisolasi** (multi-tenant)
4. Sumber pengetahuan **dokumen otoritatif + knowledge pack kurasi**, bukan internet bebas

---

## Bagian D — Perintah Pengujian Ringkas

```bash
# Seluruh test
php artisan test

# Per fitur
php artisan test --filter=MultiTenancyTest        # isolasi tenant
php artisan test --filter=DagValidationTest       # anti-siklus (unit)
php artisan test --filter=DocumentTest            # unggah & ekstraksi
php artisan test --filter=DocumentSectorTest      # deteksi & konfirmasi sektor
php artisan test --filter=AiRecommendationTest    # rekomendasi AI
php artisan test --filter=ReviewAndExportTest     # reviu & ekspor
php artisan test --filter=SemanticSearchTest      # hybrid retrieval
php artisan test --filter=SectorKnowledgeSeederTest # knowledge pack

# Catatan: jalankan config:clear & route:clear dulu bila habis deploy,
# agar test tidak memakai cache/database produksi.
```
