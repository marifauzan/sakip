<?php

namespace App\Http\Controllers;

use App\Jobs\ExtractDocument;
use App\Models\Document;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DocumentController extends Controller
{
    public function index(Request $request)
    {
        $organizationId = $request->user()->organization_id;

        $documents = Document::query()
            ->where('organization_id', $organizationId)
            ->with('sector')
            ->latest()
            ->get()
            ->map(fn (Document $d) => [
                'id' => $d->id,
                'title' => $d->title,
                'type' => $d->type,
                'period_start' => $d->period_start,
                'period_end' => $d->period_end,
                'status' => $d->status,
                'file_name' => $d->file_name,
                'sector' => $d->sector?->only('id', 'name'),
                'chunk_count' => $d->chunks()->count(),
                'created_at' => $d->created_at->toISOString(),
            ]);

        return Inertia::render('Documents/Index', ['documents' => $documents]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', 'in:renstra,rpjmd,rpjmn,renstra_opd,lainnya'],
            'period_start' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'period_end' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'file' => ['required', 'file', 'mimes:pdf,docx,doc,txt', 'max:51200'],
        ], [
            'title.required' => 'Judul dokumen wajib diisi.',
            'title.max' => 'Judul dokumen maksimal 255 karakter.',
            'type.required' => 'Jenis dokumen wajib dipilih.',
            'type.in' => 'Jenis dokumen tidak valid.',
            'period_start.integer' => 'Tahun awal harus berupa angka tahun.',
            'period_start.min' => 'Tahun awal minimal 2000.',
            'period_start.max' => 'Tahun awal maksimal 2100.',
            'period_end.integer' => 'Tahun akhir harus berupa angka tahun.',
            'period_end.min' => 'Tahun akhir minimal 2000.',
            'period_end.max' => 'Tahun akhir maksimal 2100.',
            'file.required' => 'File dokumen wajib dipilih.',
            'file.file' => 'File yang diunggah tidak valid.',
            'file.mimes' => 'Format file harus berupa PDF, DOCX, DOC, atau TXT.',
            'file.max' => 'Ukuran file tidak boleh melebihi 50 MB.',
            'file.uploaded' => 'File gagal diunggah ke server. Pastikan ukuran file tidak melebihi 50 MB.',
        ]);

        $file = $request->file('file');
        $path = $file->store('documents', 'private');

        $document = Document::create([
            'organization_id' => $request->user()->organization_id,
            'title' => $data['title'],
            'type' => $data['type'],
            'period_start' => $data['period_start'] ?? null,
            'period_end' => $data['period_end'] ?? null,
            'status' => 'uploaded',
            'file_path' => $path,
            'file_name' => $file->getClientOriginalName(),
            'file_mime' => $file->getMimeType() ?: $file->getClientMimeType(),
            'file_size' => $file->getSize(),
        ]);

        ExtractDocument::dispatch($document);

        return redirect()->route('documents.index')
            ->with('success', 'Dokumen diunggah; ekstraksi berjalan di latar belakang.');
    }

    public function retryExtract(Request $request, Document $document)
    {
        $this->authorizeOrganization($request, $document);

        $document->update([
            'status' => 'uploaded',
            'extract_error' => null,
        ]);

        ExtractDocument::dispatch($document);

        return back()->with('success', 'Proses ekstraksi ulang dokumen telah dimulai.');
    }

    public function show(Request $request, Document $document)
    {
        $this->authorizeOrganization($request, $document);

        $document->load('sector', 'chunks');

        // Sektor yang tersedia (global + milik organisasi ini) untuk dropdown konfirmasi.
        $availableSectors = \App\Models\Sector::query()
            ->where(fn ($q) => $q->whereNull('organization_id')
                ->orWhere('organization_id', $document->organization_id))
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('Documents/Show', [
            'document' => [
                'id' => $document->id,
                'title' => $document->title,
                'type' => $document->type,
                'period_start' => $document->period_start,
                'period_end' => $document->period_end,
                'status' => $document->status,
                'file_name' => $document->file_name,
                'extract_error' => $document->extract_error,
                'sector' => $document->sector?->only('id', 'name'),
                'sector_confirmed' => $document->sector_confirmed_at !== null,
                'chunks' => $document->chunks->map(fn ($c) => [
                    'id' => $c->id,
                    'page' => $c->page,
                    'section' => $c->section,
                    'chunk_index' => $c->chunk_index,
                    'preview' => mb_substr($c->content, 0, 200),
                ]),
            ],
            'availableSectors' => $availableSectors,
        ]);
    }

    /**
     * AI mendeteksi sektor dari isi dokumen. Hasilnya BELUM final —
     * user wajib mengonfirmasi/mengoreksi sebelum dipakai.
     */
    public function detectSector(Request $request, Document $document, \App\Services\AiRecommendationService $ai)
    {
        $this->authorizeOrganization($request, $document);

        try {
            $result = $ai->detectSector($document);
        } catch (\Throwable $e) {
            return response()->json([
                'error' => 'Gagal mendeteksi sektor: '.$e->getMessage(),
            ], 422);
        }

        // Cari/buat sector dengan nama hasil deteksi (belum dikonfirmasi).
        $sector = \App\Models\Sector::firstOrCreate(
            ['organization_id' => null, 'name' => $result['name']],
            ['slug' => str()->slug($result['name'])],
        );

        // Simpan sebagai USULAN: sector_id di-set, tapi confirmed_at tetap null.
        $document->update([
            'sector_id' => $sector->id,
            'sector_confirmed_at' => null,
        ]);

        return response()->json([
            'sector' => $sector->only('id', 'name'),
            'confidence' => $result['confidence'],
        ]);
    }

    /** User mengonfirmasi atau mengoreksi sektor dokumen. */
    public function confirmSector(Request $request, Document $document)
    {
        $this->authorizeOrganization($request, $document);

        $data = $request->validate([
            'sector_id' => ['nullable', 'integer', 'exists:sectors,id'],
        ]);

        // Pastikan sektor milik organisasi ini atau global.
        if ($data['sector_id']) {
            $sector = \App\Models\Sector::findOrFail($data['sector_id']);
            abort_unless(
                $sector->organization_id === null
                    || $sector->organization_id === $document->organization_id,
                403,
                'Sektor tidak tersedia untuk organisasi ini.'
            );
        }

        $document->update([
            'sector_id' => $data['sector_id'] ?? null,
            'sector_confirmed_at' => now(),
        ]);

        return back()->with('success', 'Sektor dokumen dikonfirmasi.');
    }

    private function authorizeOrganization(Request $request, Document $document): void
    {
        abort_unless(
            $document->organization_id === $request->user()->organization_id,
            403,
            'Anda tidak memiliki akses ke dokumen ini.'
        );
    }
}
