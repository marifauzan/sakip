# SAKIP — Dokumentasi Proses Deployment

> Sistem Analisis & Konsolidasi Kinerja Instansi Pemerintah
> Aplikasi asisten AI untuk perjenjangan kinerja berbasis PermenPANRB 89/2021.

---

## 1. Ringkasan Aplikasi

SAKIP membantu instansi (K/L dan pemerintah daerah) menyusun perjenjangan kinerja dengan bantuan AI. AI **menyarankan** turunan sasaran dan indikator; keputusan akhir tetap pada perencana/reviewer.

**Prinsip inti:**
- AI = asisten (menyarankan), bukan penentu.
- Setiap rekomendasi dapat ditelusuri (alasan + sumber + status usulan).
- Logika kinerja dulu, struktur organisasi belakangan.
- Tenancy & keamanan adalah fondasi.

---

## 2. Arsitektur

```
[Frontend: Inertia + Svelte 5]
         │ (server-side rendering via Laravel)
[Backend: Laravel 12 + FrankenPHP  (:8080)]
         ├── PostgreSQL 16  (data aplikasi)
         ├── Storage lokal  (dokumen privat)
         └── AI Service (api.tokito.xyz/v1, model auto)

[Proxy pusat: service `sakp` (Caddy/FrankenPHP, port 80/443)]
         └── sakip.mar-iworks.com → reverse_proxy localhost:8080
```

**Keputusan arsitektur:**
- Satu repo Laravel (frontend + backend jadi satu), konsisten dengan `sakp`.
- AI dipanggil hanya dari backend (API key di `.env`).
- RAG MVP = pencarian teks (Postgres), bukan vector DB.
- Multi-tenant via `organization_id` di semua tabel.

---

## 3. Struktur Data (ERD inti)

```
organizations ──< users (role: admin/planner/reviewer)
organizations ──< sectors ──< knowledge_packs (kurasi, BUKAN web search)
organizations ──< documents ──< document_chunks (teks + halaman + bab)
organizations ──< kinerja_trees ──< nodes ──< indicators
                               └──< node_links (DAG, anti-siklus)
                               └──< reviews (comment/approve/reject)
organizations ──< ai_recommendations (log audit AI)
```

---

## 4. Fase Pengembangan (git history)

| Commit | Tahap |
|---|---|
| `b06f5d5` | Scaffold (Laravel 12 + Inertia + Svelte + PostgreSQL) |
| `982cbae` | Auth + multi-tenant + organisasi + sektor |
| `ec7ffc8` | Dokumen (upload + ekstraksi PDF/DOCX + chunking) |
| `95b4b13` | Editor kinerja (sasaran, relasi DAG, indikator) |
| `ed975eb` | Integrasi AI (rekomendasi turunan/indikator/deteksi sektor) |
| `c93dd72` | Reviu + persetujuan + ekspor Markdown/JSON |
| `f3b6ba0` | Deploy produksi (trust proxy, disk private, service) |

---

## 5. Konfigurasi Produksi

### `.env` (nilai penting)
```
APP_ENV=production
APP_DEBUG=false
APP_URL=https://sakip.mar-iworks.com

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=sakip
DB_USERNAME=sakip
DB_PASSWORD=*** (role lokal)

SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database

LLM_BASE_URL=https://api.tokito.xyz/v1
LLM_MODEL=auto
LLM_API_KEY=*** (dari ~/.hermes/config.yaml)
```

### Service systemd (2 proses)
```bash
# Web (FrankenPHP)
/etc/systemd/system/sakip.service
ExecStart=/usr/local/bin/frankenphp run --config /home/ubuntu/sakip/Caddyfile

# Queue worker (ekstraksi dokumen background)
/etc/systemd/system/sakip-queue.service
ExecStart=/usr/bin/php /home/ubuntu/sakip/artisan queue:work --sleep=3 --tries=3 --timeout=120
```

### Caddyfile (app → port lokal)
```
{
    frankenphp
    order php_server before file_server
    admin 127.0.0.1:2020   # unik, karena 2019 dipakai sakp
}
:8080 {
    root * /home/ubuntu/sakip/public
    request_body {
        max_size 64MB
    }
    php_server
    encode zstd gzip
    file_server
}
```

### Proxy pusat (`/home/ubuntu/sakp/Caddyfile`, ditambah)
```
sakip.mar-iworks.com {
    request_body {
        max_size 64MB
    }
    reverse_proxy localhost:8080
    encode zstd gzip
}
```

---

## 6. Perintah Operasional

```bash
# Restart aplikasi
sudo systemctl restart sakip sakip-queue

# Lihat status
systemctl is-active sakip sakip-queue sakp

# Log
sudo journalctl -u sakip -n 50
sudo journalctl -u sakip-queue -n 50

# Rebuild frontend
cd /home/ubuntu/sakip && npm run build

# Cache clear (setelah ubah config/route)
php artisan config:cache route:cache

# Migrasi
php artisan migrate --force

# Seed ulang (org + user demo + sektor pendidikan)
php artisan db:seed --force
```

---

## 7. Akun Demo

| Email | Role | Organisasi |
|---|---|---|
| `admin@kemenag.test` | admin | Kementerian Agama (contoh) |
| `planner@disdik.test` | planner | Dinas Pendidikan (contoh) |
| `admin@kementan.test` | admin | Kementerian Pertanian |
| `planner@kementan.test` | planner | Kementerian Pertanian |
| `reviewer@kementan.test` | reviewer | Kementerian Pertanian |

Password: `password` (GANTI untuk produksi nyata!)

> Akun Kementan dilengkapi data contoh pohon kinerja "Renstra Kementan 2025–2029"
> (6 sasaran, 7 indikator, relasi DAG) untuk keperluan demo ke pimpinan.

---

## 8. Keamanan & Konfigurasi Upload

- Batas upload dokumen di aplikasi: **50 MB** (`public/.user.ini` mengonfigurasi `upload_max_filesize=60M` dan `post_max_size=64M`).
- `APP_DEBUG=false`, API key LLM hanya di backend.
- `trustProxies(at: '*')` di `bootstrap/app.php` (di belakang reverse proxy).
- Multi-tenant: filter `organization_id` + abort 403 lintas org.
- Dokumen = data (bukan instruksi) → system prompt terpisah (anti prompt-injection).
- DOCX diekstrak via ZIP/XML sendiri (bukan PHPWord, yang punya kerentanan XXE).
- `.env`, `.git`, `/storage` → 404 (tidak ter-expose).

---

## 9. Troubleshooting Umum

| Gejala | Penyebab | Solusi |
|---|---|---|
| `bind: address already in use` (2019) | Admin Caddy bentrok dgn sakp | Set `admin 127.0.0.1:2020` |
| Redirect ke http (bukan https) | Lupa `trustProxies` | Tambah `trustProxies(at: '*')` |
| `Disk [private] does not have a configured driver` | Disk `private` belum didefinisikan | Tambah di `config/filesystems.php` |
| `The file failed to upload` (> 2MB) | Batas runtime PHP default 2M | Pastikan `public/.user.ini` aktif atau set di Caddy / php.ini |
| `reload` service gagal | FrankenPHP tidak support reload | Pakai `restart` |
| Upload 500 | File tidak tersimpan | Cek permission `storage/app/private` |

---

## 10. Roadmap (v2) — ✅ SELESAI

Semua item v2 sudah diimplementasikan dan ter-deploy (tag `v2.0.0`):

1. **✅ Deteksi sektor terhubung UI** — panel di halaman dokumen dengan tombol "Deteksi dengan AI", dropdown sektor, dan tombol "Konfirmasi Sektor". Status dibedakan: `Usulan AI` vs `Terkonfirmasi`.
2. **✅ Knowledge pack 7 sektor** — Pendidikan, Pertanian, Kesehatan, Infrastruktur, Ekonomi, Lingkungan Hidup, Pariwisata (masing-masing 2 pack: Dimensi Hasil + Indikator Umum).
3. **✅ Retrieval semantik (pgvector)** — ekstensi `vector`, kolom `embedding vector(1536)` + index HNSW di `document_chunks`, job `EmbedDocumentChunks`, `EmbeddingClient`, dan `SemanticSearchService` dengan **fallback otomatis ke pencarian teks** bila provider embedding tidak tersedia.
4. **✅ Sitasi sumber** — retrieval mengembalikan konteks berlabel (`[D<id>-H<halaman>]` + nama dokumen + bagian) yang bisa dikutip AI.
5. **✅ Import Renstra Kementan 2025-2029** — 268 halaman ter-ekstraksi ke 268 chunk, sektor Pertanian terkonfirmasi.

### Catatan penting v2
- **Provider `api.tokito.xyz` tidak menyediakan model embedding.** Arsitektur dirancang graceful: sistem otomatis memakai pencarian teks. Untuk mengaktifkan pencarian semantik, set `LLM_EMBEDDING_MODEL` di `.env` ke model embedding dari provider yang mendukung endpoint `/embeddings` (mis. `text-embedding-3-small`), lalu jalankan `php artisan tinker` + `EmbedDocumentChunks::dispatch($doc)` untuk meng-embed chunk yang ada.
- **pgvector hanya di PostgreSQL.** Migrasi embedding dilewati otomatis di SQLite (test suite tetap jalan tanpa pgvector).

## 11. Akun & Organisasi

| Email | Role | Organisasi | Password |
|---|---|---|---|
| `admin@kemenag.test` | admin | Kementerian Agama (contoh) | `password` |
| `planner@disdik.test` | planner | Dinas Pendidikan (contoh) | `password` |
| `admin@kementan.test` | admin | **Kementerian Pertanian** | `password` |
| `planner@kementan.test` | planner | **Kementerian Pertanian** | `password` |
| `reviewer@kementan.test` | reviewer | **Kementerian Pertanian** | `password` |

> ⚠️ Akun masih demo. Ganti password + tambah verifikasi email sebelum dipakai produksi nyata.

## 12. Roadmap v3 (berikutnya)

1. Aktifkan embedding (set `LLM_EMBEDDING_MODEL`) saat provider tersedia → pencarian semantik penuh.
2. Knowledge pack dikelola lewat UI admin (sudah ada `KnowledgePackController`).
3. Sinkronisasi lintas periode Renstra (bandingkan target antar-tahun).
4. Integrasi ekspor ke format dokumen resmi (DOCX/PDF).
5. Autentikasi produksi (verifikasi email, reset password, 2FA).
