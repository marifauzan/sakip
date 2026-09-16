# SAKIP

**Sistem Analisis & Konsolidasi Kinerja Instansi Pemerintah**

Aplikasi berbantuan AI untuk penyusunan **perjenjangan kinerja** instansi pemerintah (K/L dan pemerintah daerah), mengacu pada **PermenPANRB No. 89 Tahun 2021**.

> **Prinsip inti:** AI **menyarankan**, manusia **memutuskan**. Setiap rekomendasi AI dapat ditelusuri ke sumbernya (dokumen + halaman).

- 🌐 Produksi: https://sakip.mar-iworks.com
- 📦 Repo: https://github.com/marifauzan/sakip

---

## Daftar Isi

- [Fitur](#fitur)
- [Arsitektur Singkat](#arsitektur-singkat)
- [Stack Teknologi](#stack-teknologi)
- [Instalasi Lokal](#instalasi-lokal)
- [Akun Demo](#akun-demo)
- [Dokumentasi Lanjutan](#dokumentasi-lanjutan)
- [Pengujian](#pengujian)

---

## Fitur

| Fitur | Deskripsi |
|---|---|
| **Multi-tenant** | Isolasi data antar-instansi; setiap query difilter `organization_id`. |
| **Manajemen Dokumen** | Unggah Renstra/RPJMD (PDF/DOCX/TXT), ekstraksi teks otomatis via queue, chunking dengan metadata halaman & bab. |
| **Deteksi Sektor** | AI mengklasifikasi sektor/domain dokumen (Pendidikan, Pertanian, Kesehatan, dst.) — **wajib dikonfirmasi user**. |
| **Editor Perjenjangan Kinerja** | Sasaran (outcome/output/aktivitas), hubungan antarsasaran (DAG dengan validasi anti-siklus), indikator. |
| **Rekomendasi AI** | Usulan turunan sasaran & indikator, berbasis dokumen + knowledge pack sektor, **lengkap dengan sitasi halaman**. |
| **Knowledge Pack** | Konten kurasi per sektor (dimensi hasil + indikator umum) — bukan hasil pencarian web bebas. |
| **Reviu & Persetujuan** | Komentar, approve/reject per simpul; status `ai_proposed` → `approved`. |
| **Ekspor** | Markdown & JSON (hierarki + tabel indikator). |

---

## Arsitektur Singkat

```
┌─────────────────────────────────────────────┐
│  Frontend: Inertia + Svelte 5 (SSR)         │
└──────────────────┬──────────────────────────┘
                   │
┌──────────────────▼──────────────────────────┐
│  Backend: Laravel 12 + FrankenPHP (:8080)   │
│                                             │
│  Controllers ──► Services ──► Jobs (queue)  │
│                     │                       │
│  ┌──────────────────┼────────────────────┐  │
│  │ PostgreSQL 16    │ LLM API (external) │  │
│  │ + pgvector       │ /chat/completions  │  │
│  └──────────────────┴────────────────────┘  │
└──────────────────┬──────────────────────────┘
                   │ reverse_proxy
┌──────────────────▼──────────────────────────┐
│  Proxy pusat: Caddy (TLS) — sakp.service    │
└─────────────────────────────────────────────┘
```

**Detail arsitektur lengkap:** [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md)

---

## Stack Teknologi

| Layer | Teknologi |
|---|---|
| Backend | PHP 8.3, Laravel 12 |
| Frontend | Inertia.js 3, Svelte 5, Tailwind CSS 4, Vite 7 |
| Database | PostgreSQL 16 + pgvector 0.6 |
| Web Server | FrankenPHP 1.12 (Caddy 2.11) |
| Queue | Database driver (`sakip-queue.service`) |
| AI | LLM OpenAI-compatible (`api.tokito.xyz`) |
| Diagram | `@xyflow/svelte` + `@dagrejs/dagre` |
| CI/CD | GitHub Actions → auto-deploy via SSH (tag `v*`) |

---

## Instalasi Lokal

```bash
# 1. Clone & install dependensi
git clone git@github.com:marifauzan/sakip.git
cd sakip
composer install
npm install

# 2. Konfigurasi environment
cp .env.example .env
php artisan key:generate

# 3. Setup database (PostgreSQL)
#    Buat role & database sesuai DB_* di .env, lalu:
php artisan migrate

# 4. Seed data awal (organisasi, user demo, sektor, knowledge pack)
php artisan db:seed
php artisan db:seed --class=KementanSeeder
php artisan db:seed --class=SectorKnowledgeSeeder

# 5. Build frontend
npm run build

# 6. Jalankan (butuh 2 proses: web + queue worker)
composer run dev   # atau: php artisan serve + php artisan queue:work
```

### Environment Variables Penting

```env
# Database
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=sakip
DB_USERNAME=sakip
DB_PASSWORD=***

# LLM (OpenAI-compatible)
LLM_BASE_URL=https://api.tokito.xyz/v1
LLM_API_KEY=***
LLM_MODEL=auto

# Embedding (OPSIONAL — lihat catatan di docs/ARCHITECTURE.md#embedding)
LLM_EMBEDDING_MODEL=
```

---

## Akun Demo

| Email | Role | Organisasi | Password |
|---|---|---|---|
| `admin@kementan.test` | admin | Kementerian Pertanian | `password` |
| `planner@kementan.test` | planner | Kementerian Pertanian | `password` |
| `reviewer@kementan.test` | reviewer | Kementerian Pertanian | `password` |
| `admin@kemenag.test` | admin | Kementerian Agama | `password` |
| `planner@disdik.test` | planner | Dinas Pendidikan | `password` |

> ⚠️ **Akun demo.** Ganti password + tambahkan verifikasi email sebelum digunakan di produksi.

---

## Dokumentasi Lanjutan

| Dokumen | Isi |
|---|---|
| [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md) | Arsitektur mendetail, model data, alur request, keputusan desain |
| [`docs/AI-METHOD.md`](docs/AI-METHOD.md) | Metode AI: prompt engineering, retrieval, grounding, anti-injection |
| [`docs/WALKTHROUGH.md`](docs/WALKTHROUGH.md) | Walkthrough fitur v1 & v2 (langkah demi langkah) |
| [`docs/EMBEDDING-NOTES.md`](docs/EMBEDDING-NOTES.md) | Kenapa embedding penting, fungsinya, kenapa ditunda |
| [`DEPLOYMENT.md`](DEPLOYMENT.md) | Deployment, CI/CD, operasional, troubleshooting |
| [`CHANGELOG.md`](CHANGELOG.md) | Riwayat versi |

---

## Pengujian

```bash
# PENTING: clear cache dulu, kalau tidak test bisa memakai database produksi!
php artisan config:clear && php artisan route:clear

# Semua test (SQLite in-memory)
php artisan test

# Test tertentu
php artisan test --filter=MultiTenancyTest
php artisan test --filter=SemanticSearchTest
```

> ⚠️ **Pitfall yang sering terjadi:** setelah deploy (`config:cache` + `route:cache`),
> `php artisan test` akan memakai cache produksi — termasuk **database PostgreSQL produksi**.
> Selalu `config:clear && route:clear` sebelum menjalankan test lokal.
>
> Sejak v2, `phpunit.xml` sudah memaksa `DB_CONNECTION=sqlite` + `DB_DATABASE=:memory:`,
> tetapi **config cache mengalahkannya**. Clear cache selalu menyelesaikan ini.

---

## Lisensi

MIT
