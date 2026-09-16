# Catatan: Semantic Embedding (Ditunda)

> **Status: SIAP DIINFRASTRUKTUR, DITUNDA PENGISIAN MODEL**
> Infrastruktur 100% terpasang. Yang kurang hanya `LLM_EMBEDDING_MODEL` di `.env`.

---

## 1. Apa itu Embedding?

**Embedding** adalah representasi teks sebagai **vektor angka** yang menangkap maknanya.

```
"produksi pangan strategis"   → [0.021, -0.134, 0.887, ... ]  (1536 angka)
"hasil panen komoditas utama" → [0.019, -0.128, 0.879, ... ]  (1536 angka)
                                                        ↑
                                    vektor mirip = makna mirip
```

Dua kalimat yang **berbeda kata** tapi **sama makna** akan menghasilkan vektor yang berdekatan. Sebaliknya, kalimat dengan kata sama tapi makna berbeda (mis. "kambing hitam") akan berjauhan.

### Bedanya dengan pencarian teks biasa

| Query | Pencarian teks | Pencarian semantik |
|---|---|---|
| "produksi pangan" | Hanya cocok jika dokumen memuat kata **"produksi"** dan **"pangan"** | Cocok juga dengan "hasil panen bahan pokok", "output pertanian" |
| "kesejahteraan petani" | Gagal jika dokumen menulis "pendapatan rumah tangga tani" | Berhasil, karena maknanya sama |
| "berketahanan iklim" | Gagal jika dokumen menulis "tahan perubahan cuaca" | Berhasil |

---

## 2. FUNGSI Embedding di SAKIP

Embedding dipakai di **satu tempat kritis**: **retrieval** — memilih potongan dokumen (chunk) yang paling relevan untuk dijadikan konteks AI.

### Alur tanpa embedding (kondisi sekarang)

```
User pilih sasaran: "Meningkatnya produksi pangan strategis"
    │
    ▼
Pencarian TEKS: cari chunk yang mengandung "produksi" DAN "pangan" DAN "strategis"
    │
    └── Risiko: chunk relevan yang memakai istilah berbeda TIDAK ditemukan
```

### Alur dengan embedding (setelah diaktifkan)

```
User pilih sasaran: "Meningkatnya produksi pangan strategis"
    │
    ▼
embed(query) → vektor
    │
    ▼
Cari chunk dengan cosine distance terkecil (index HNSW)
    │
    └── Chunk relevan ditemukan MESKI istilahnya berbeda
```

### Efek berantai ke kualitas

```
Retrieval lebih baik
    → Konteks yang dikirim ke LLM lebih relevan
    → Rekomendasi lebih tepat sasaran
    → Sitasi lebih akurat
    → Kepercayaan perencana meningkat
```

**Kesimpulan: embedding adalah pengungkit kualitas AI yang paling besar di SAKIP.**

---

## 3. KENAPA Ini Penting

### 3.1 Untuk kualitas rekomendasi

Renstra dan RPJMD ditulis dengan istilah yang bervariasi. Penulis berbeda memakai kata berbeda untuk konsep sama. Tanpa embedding, AI bisa melewatkan bagian dokumen yang justru paling relevan — hanya karena perbedaan pilihan kata.

### 3.2 Untuk skala dokumen

Saat ini SAKIP punya **268 chunk** dari satu dokumen (Renstra Kementan). Dengan pencarian teks, ini masih terkelola. Tapi:

| Jumlah dokumen | Chunk (estimasi) | Pencarian teks | Pencarian semantik |
|---|---|---|---|
| 1 | ~270 | Layak | Layak |
| 5 | ~1.400 | Mulai lambat | Cepat (index HNSW) |
| 20 | ~5.400 | Lambat | Cepat |
| 100 | ~27.000 | Sangat lambat | Tetap cepat |

**Pencarian teks saat ini memuat SELURUH chunk ke memori** lalu memfilternya di PHP. Ini tidak akan bisa diskalakan. Pencarian semantik memakai **index HNSW** di PostgreSQL — tetap cepat berapa pun jumlah chunk.

### 3.3 Untuk penggunaan nyata instansi

Sebuah K/L punya banyak dokumen: Renstra, RPJMD (untuk pemda), Renja, LAKIP, evaluasi kinerja, dokumen sektoral. Perencana yang mencari "dasar untuk sasaran X" membutuhkan pencarian lintas-dokumen yang cerdas — bukan pencarian kata kunci.

---

## 4. KENAPA Ditunda

### 4.1 Sebab utama: provider LLM tidak menyediakan endpoint embedding

Provider `api.tokito.xyz` menyediakan 19 model — **semuanya chat/LLM** (`glm`, `deepseek`, `gpt`, `claude`, `gemini`, `gemma`, `grok`). Tidak ada model embedding.

**Bukti pengujian:**

| Uji | Hasil |
|---|---|
| `GET /v1/models` → cari "embed" | Tidak ada model embedding |
| `POST /v1/embeddings` model `text-embedding-3-small` | **HTTP 500** |
| `POST /v1/embeddings` model `text-embedding-ada-002` | **HTTP 500** |
| `POST /v1/embeddings` model `auto` | **HTTP 500** |
| `POST /v1/embeddings` model `bge-m3` | **HTTP 500** |

**Catatan penting:** respons adalah **HTTP 500 (Internal Server Error)**, bukan 404 "model not found". Ini menunjukkan **endpoint embedding memang tidak diimplementasikan** di provider tersebut — bukan sekadar model yang belum terdaftar.

### 4.2 Sebab kedua: kebutuhan belum mendesak

Saat ini SAKIP dipakai untuk demo dengan **satu dokumen (268 chunk)**. Pada skala ini, pencarian teks masih memberikan hasil yang baik dan sudah terbukti akurat (sitasi `D7-H66` terverifikasi benar).

### 4.3 Sebab ketiga: keputusan provider = keputusan kebijakan

Untuk aplikasi instansi pemerintah, pemilihan provider embedding bukan sekadar teknis. Data dokumen perencanaan dikirim ke provider luar. Ini perlu pertimbangan:
- Di mana data diproses & disimpan?
- Apakah data dipakai untuk training?
- Sesuai kebijakan keamanan instansi?

**Karena itu keputusan ini baiknya diambil oleh Anda (pemilik produk), bukan saya secara teknis.**

---

## 5. OPSI Provider Embedding (saat siap)

| Provider | Model | Dimensi | Perkiraan biaya | Kelebihan | Kekurangan |
|---|---|---|---|---|---|
| **OpenAI** | `text-embedding-3-small` | **1536** | ~$0.02 / 1M token | Paling praktis; **dimensi sudah cocok dengan skema** | Data ke OpenAI (AS) |
| **Cohere** | `embed-multilingual-v3` | 1024 | ~$0.10 / 1M token | **Multibahasa**, bagus untuk Bahasa Indonesia | Perlu ubah dimensi kolom |
| **Voyage AI** | `voyage-3-lite` | 512/1024 | Lebih murah | Kualitas baik | Perlu ubah dimensi |
| **Ollama (lokal)** | `bge-m3` | 1024 | **Gratis** | Data **tidak keluar server**; cocok instansi | Butuh RAM ~2-4GB; kualitas di bawah komersial |

### Rekomendasi

| Situasi | Pilihan |
|---|---|
| Ingin cepat & praktis, data tidak sensitif | **OpenAI** (dimensi 1536 sudah pas — cukup set env, tanpa migrasi) |
| Data sensitif / kedaulatan data (instansi pemerintah) | **Ollama lokal + bge-m3** |
| Prioritas Bahasa Indonesia | **Cohere multilingual** |

> ⚠️ **Catatan keamanan:** untuk aplikasi instansi pemerintah, **Ollama lokal** menghindari pengiriman dokumen perencanaan ke pihak ketiga. Ini pertimbangan penting meskipun kualitasnya sedikit di bawah opsi komersial.

---

## 6. Infrastruktur yang SUDAH Siap

Semua ini sudah terpasang di produksi dan menunggu diaktifkan:

| Komponen | Status |
|---|---|
| Ekstensi pgvector 0.6.0 | ✅ Terpasang di PostgreSQL |
| Kolom `document_chunks.embedding vector(1536)` | ✅ Ada (nullable) |
| Index HNSW (`vector_cosine_ops`) | ✅ Terpasang |
| `EmbeddingClient` (klien API) | ✅ Ada, graceful jika tak dikonfigurasi |
| `SemanticSearchService` (pencarian vektor + fallback) | ✅ Ada & teruji |
| Job `EmbedDocumentChunks` (batch embedding) | ✅ Ada |
| Migrasi sadar-driver (skip di SQLite) | ✅ Ada |
| 7 test untuk jalur semantik & fallback | ✅ Lulus |

**Artinya: mengaktifkan embedding = mengubah 1 baris `.env` + jalankan job.**

---

## 7. Cara Mengaktifkan (saat provider siap)

```bash
# 1. Set model embedding di .env
LLM_EMBEDDING_MODEL=text-embedding-3-small
# (atau base_url terpisah bila providernya berbeda dari provider chat)

# 2. Refresh config
php artisan config:clear && php artisan config:cache

# 3. Embed chunk yang sudah ada (batch, lewat queue)
php artisan tinker
>>> App\Models\Document::all()->each(fn($d) => App\Jobs\EmbedDocumentChunks::dispatch($d));
```

Setelah itu, `SemanticSearchService` **otomatis** memakai jalur semantik (karena `hasEmbeddings()` bernilai true) tanpa perubahan kode.

### Jika dimensi model ≠ 1536

```bash
# Buat migrasi baru untuk mengubah dimensi kolom
php artisan make:migration change_embedding_dimension
```
Lalu di migrasi: `DROP` kolom lama → `ADD COLUMN embedding vector(<dimensi_baru>)` → recreate index. (Kalau pakai OpenAI 1536, **langkah ini tidak perlu**.)

---

## 8. Ringkasan

| Pertanyaan | Jawaban |
|---|---|
| **Apa fungsinya?** | Menemukan potongan dokumen paling relevan berdasarkan **makna**, bukan sekadar kecocokan kata |
| **Kenapa penting?** | Pengungkit kualitas AI terbesar: retrieval lebih baik → konteks lebih relevan → rekomendasi & sitasi lebih akurat |
| **Kenapa ditunda?** | (1) Provider chat tidak menyediakan endpoint embedding (HTTP 500), (2) skala masih kecil, (3) pilihan provider = keputusan kebijakan, bukan teknis |
| **Apakah aplikasi rusak tanpa itu?** | **Tidak.** Hybrid retrieval otomatis memakai pencarian teks — aplikasi berfungsi penuh |
| **Apa yang perlu disiapkan?** | Pilih provider → set `LLM_EMBEDDING_MODEL` → jalankan job embedding |
| **Risiko menunda?** | Kualitas retrieval sedikit di bawah potensi maksimal; pencarian teks tidak skalabel untuk 100+ dokumen |
