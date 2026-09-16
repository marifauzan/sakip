# Changelog

Semua perubahan penting pada SAKIP didokumentasikan di file ini.

Format berdasarkan [Keep a Changelog](https://keepachangelog.com/id/1.0.0/),
versi mengikuti [Semantic Versioning](https://semver.org/lang/id/).

---

## [Unreleased]

### Rencana
- Editor pohon kinerja visual (drag & drop) — untuk demo stakeholder
- Ekspor DOCX/PDF (format dokumen resmi)
- Aktivasi embedding (menunggu keputusan provider) — lihat `docs/EMBEDDING-NOTES.md`

---

## [2.0.0] — 2026-09-15

Rilis v2: fokus pada **ketertelusuran** dan **kedalaman konteks AI**.

### Added

- **Deteksi sektor AI + konfirmasi user** (`DocumentController@detectSector`, `@confirmSector`)
  - Panel di halaman dokumen: tombol "Deteksi dengan AI", dropdown, tombol "Konfirmasi Sektor"
  - Status dua tahap: `Usulan AI` (kuning) → `Terkonfirmasi` (hijau)
  - Field baru `documents.sector_confirmed_at`

- **Knowledge pack 5 sektor tambahan** (`SectorKnowledgeSeeder`)
  - Kesehatan, Infrastruktur, Ekonomi, Lingkungan Hidup, Pariwisata
  - Total: **7 sektor, 14 knowledge pack** (2 per sektor)

- **Sitasi sumber pada rekomendasi AI**
  - Retrieval mengembalikan konteks berlabel `[D<id>-H<halaman>]`
  - Metadata: nama dokumen, halaman, bagian
  - Field `references` pada output rekomendasi
  - Kolom `sources` pada response API

- **Hybrid retrieval (pgvector + fallback teks)**
  - Ekstensi PostgreSQL `vector` 0.6.0
  - Kolom `document_chunks.embedding vector(1536)` + index **HNSW** (`vector_cosine_ops`)
  - `EmbeddingClient` — klien embedding graceful (no-op bila tak dikonfigurasi)
  - `SemanticSearchService` — pencarian vektor dengan **fallback otomatis** ke teks
  - Job `EmbedDocumentChunks` — batch embedding
  - Migrasi sadar-driver (dilewati di SQLite agar test tetap jalan)

- **Import Renstra Kementerian Pertanian 2025–2029**
  - 268 halaman → 268 chunk dengan metadata halaman
  - Sektor Pertanian terkonfirmasi

- **Test baru (15)**: `DocumentSectorTest`, `SemanticSearchTest`, `SectorKnowledgeSeederTest`
  - Termasuk uji keamanan multi-tenant untuk sektor dan pencarian

### Changed

- `AiRecommendationService` — konstruktor kini menerima `SemanticSearchService`
- `retrieveRelevantText()` — delegasi ke `SemanticSearchService`, mengembalikan `{text, sources}`
- Log `ai_recommendations.output` — kini menyertakan `sources`
- `config/llm.php` — tambah `embedding_model` (opsional)

### Fixed

- **CI: `ViteManifestNotFoundException`** — test yang merender halaman gagal karena `public/build/manifest.json` belum ada. Solusi: `npm ci` + `npm run build` **sebelum** test di CI.
- **CI: gagal `composer install` di PHP 8.2** — dependency lock butuh PHP 8.3+. Solusi: matrix CI = PHP 8.3, constraint `composer.json` → `^8.3`.
- **CI: `boost:update` menggagalkan `composer update`** — command tidak terdaftar di luar environment dev. Solusi: hapus dari `post-update-cmd`.

---

## [1.0.0] — 2026-09-14

Rilis pertama (MVP). Fokus: **perjenjangan kinerja berbantuan AI dengan kontrol manusia**.

### Added

- **Scaffold** — Laravel 12 + Inertia 3 + Svelte 5 + Tailwind 4 + PostgreSQL 16
- **Multi-tenancy** — `organizations`, kolom `organization_id` di semua model bisnis, trait `BelongsToOrganization`, gerbang `abort_unless(403)`
- **Autentikasi** — login/logout session-based (`AuthController`)
- **Manajemen dokumen** — unggah PDF/DOCX/TXT, ekstraksi via queue
  - `DocumentExtractor`: PDF via `smalot/pdfparser`; DOCX via ekstraktor ZIP/XML sendiri
  - Chunking per paragraf + deteksi bab heuristik
  - State machine: `uploaded` → `extracting` → `extracted`/`failed`
- **Editor perjenjangan kinerja** — `kinerja_trees`, `nodes`, `node_links`, `indicators`
  - `DagValidator` — pencegahan siklus (DFS)
  - `source_type`: `extracted` / `ai_proposed` / `user_edited` / `approved`
- **Integrasi AI** (`AiRecommendationService`)
  - `recommendChildren` — usulan turunan sasaran
  - `recommendIndicators` — usulan indikator
  - `detectSector` — klasifikasi sektor
  - `LlmClient` — klien OpenAI-compatible dengan JSON mode
  - Tabel `ai_recommendations` — log audit lengkap
- **Knowledge pack** — `sectors` + `knowledge_packs` (kurasi, bukan web search)
  - Sektor awal: Pendidikan, Pertanian
- **Reviu & persetujuan** (`ReviewController`) — comment/approve/reject
- **Ekspor** (`KinerjaExporter`) — Markdown (hierarki + tabel) & JSON
- **Seeder Kementan** — organisasi + 3 user (admin/planner/reviewer)

### Security

- `.env` tidak pernah ter-commit (diverifikasi)
- Dokumen disimpan di disk `private` (tidak dapat diakses URL publik)
- API key LLM hanya di backend
- Menolak `phpoffice/phpword` karena kerentanan XXE pada `phpoffice/math` (belum ada patch)
- Mitigasi prompt injection: system prompt dipisah dari konten dokumen

### Infrastructure

- **CI/CD** (GitHub Actions)
  - CI: PHP 8.3, test di setiap push `main` & PR
  - CD: auto-deploy via SSH, **hanya saat push tag `v*`**
  - Deploy script: pull → install → build → **clear cache** → migrate → re-cache → restart
- Deployment: FrankenPHP (`:8080`) + queue worker, reverse proxy Caddy pusat
- Dokumentasi: `DEPLOYMENT.md`

---

## [0.1.0] — 2026-09-11

### Added

- Inisialisasi proyek (scaffold Laravel 12 + Inertia + Svelte)
- Database PostgreSQL `sakip`, konfigurasi dasar
- Halaman dashboard pertama

---

## Konvensi Commit

Proyek ini memakai [Conventional Commits](https://www.conventionalcommits.org/):

| Prefix | Makna |
|---|---|
| `feat` | Fitur baru |
| `fix` | Perbaikan bug |
| `docs` | Dokumentasi |
| `ci` | Perubahan CI/CD |
| `chore` | Pemeliharaan (dependencies, konfigurasi) |
| `test` | Penambahan/perbaikan test |
| `refactor` | Perubahan kode tanpa mengubah perilaku |

Contoh: `feat(v2): sitasi sumber (dokumen+halaman) pada konteks retrieval AI`
