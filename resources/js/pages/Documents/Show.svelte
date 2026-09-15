<script>
    import { Link, router, usePoll } from '@inertiajs/svelte';
    import Layout from '../Layout.svelte';
    import {
        ArrowLeft,
        FileText,
        Calendar,
        Layers,
        AlertCircle,
        CheckCircle2,
        RefreshCw,
        Clock,
        FileCheck,
        Tag,
        Sparkles
    } from '@lucide/svelte';

    let { document, availableSectors = [] } = $props();

    let retrying = $state(false);
    let detecting = $state(false);
    let savingSector = $state(false);
    let sectorMessage = $state('');
    let sectorError = $state(false);
    let selectedSector = $state(document.sector?.id ?? '');

    async function detectSector() {
        detecting = true;
        sectorMessage = '';
        try {
            const res = await fetch(`/documents/${document.id}/detect-sector`, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf(),
                },
            });
            const data = await res.json();
            if (!res.ok) {
                sectorError = true;
                sectorMessage = data.error ?? 'Gagal mendeteksi sektor.';
            } else {
                sectorError = false;
                sectorMessage = `AI mengusulkan sektor "${data.sector.name}" (keyakinan: ${data.confidence}). Periksa lalu klik Konfirmasi Sektor.`;
                // Muat ulang agar dropdown & badge sinkron.
                router.reload({ only: ['document', 'availableSectors'] });
            }
        } catch (e) {
            sectorError = true;
            sectorMessage = 'Terjadi kesalahan saat menghubungi server.';
        } finally {
            detecting = false;
        }
    }

    function confirmSector() {
        savingSector = true;
        sectorMessage = '';
        router.post(
            `/documents/${document.id}/confirm-sector`,
            { sector_id: selectedSector === '' ? null : selectedSector },
            {
                preserveScroll: true,
                onSuccess: () => {
                    sectorError = false;
                    sectorMessage = 'Sektor berhasil dikonfirmasi.';
                },
                onError: () => {
                    sectorError = true;
                    sectorMessage = 'Gagal menyimpan sektor.';
                },
                onFinish: () => {
                    savingSector = false;
                },
            }
        );
    }

    function csrf() {
        // Gunakan globalThis.cookie via document (browser), bukan prop `document`.
        const m = window.document.cookie.match(/XSRF-TOKEN=([^;]+)/);
        return m ? decodeURIComponent(m[1]) : '';
    }

    function retryExtract() {
        retrying = true;
        router.post(`/documents/${document.id}/retry-extract`, {}, {
            preserveScroll: true,
            onFinish: () => {
                retrying = false;
            },
        });
    }

    let isExtracting = $derived(
        document.status === 'uploaded' || document.status === 'extracting'
    );

    const { start, stop } = usePoll(3000, { only: ['document'] }, { autoStart: false });

    $effect(() => {
        if (isExtracting) {
            start();
        } else {
            stop();
        }
    });

    function formatDocStatus(status) {
        switch (status) {
            case 'extracted':
                return { label: 'Selesai Diekstrak', class: 'bg-emerald-50 text-emerald-800 border-emerald-200', dot: 'bg-emerald-500' };
            case 'extracting':
                return { label: 'Sedang Mengekstrak…', class: 'bg-amber-50 text-amber-800 border-amber-200', dot: 'bg-amber-500 animate-pulse' };
            case 'uploaded':
                return { label: 'Diunggah (Antrean)', class: 'bg-slate-100 text-slate-700 border-slate-200', dot: 'bg-slate-400' };
            case 'failed':
                return { label: 'Ekstraksi Gagal', class: 'bg-rose-50 text-rose-800 border-rose-200', dot: 'bg-rose-500' };
        }
    }

    let docStatus = $derived(formatDocStatus(document.status));
</script>

<Layout title={document.title}>
    <div class="space-y-6 max-w-5xl">
        <!-- TOP CONTEXT / BACK LINK -->
        <div>
            <Link
                href="/documents"
                class="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-600 hover:text-emerald-700 transition-colors"
            >
                <ArrowLeft class="w-4 h-4" />
                <span>Kembali ke Daftar Dokumen</span>
            </Link>
        </div>

        <!-- HEADER CARD -->
        <div class="p-6 rounded-xl border border-slate-200 bg-white shadow-2xs">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div class="space-y-1.5">
                    <div class="flex items-center gap-2">
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold uppercase tracking-wider bg-slate-100 text-slate-700 border border-slate-200/70">
                            {document.type}
                        </span>
                        {#if document.sector}
                            <span class="text-xs text-slate-500">· {document.sector.name}</span>
                        {/if}
                    </div>
                    <h1 class="text-xl sm:text-2xl font-bold tracking-tight text-slate-900">
                        {document.title}
                    </h1>
                    <div class="flex flex-wrap items-center gap-3 text-xs text-slate-500 font-mono">
                        <span class="inline-flex items-center gap-1">
                            <Calendar class="w-3.5 h-3.5 text-slate-400" />
                            <span>Periode: {document.period_start ?? '—'} – {document.period_end ?? '—'}</span>
                        </span>
                        <span>·</span>
                        <span class="inline-flex items-center gap-1 text-slate-600">
                            <FileText class="w-3.5 h-3.5 text-slate-400" />
                            <span>{document.file_name}</span>
                        </span>
                    </div>
                </div>

                <div class="shrink-0 flex items-center gap-2">
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold border {docStatus.class}">
                        <span class="w-1.5 h-1.5 rounded-full {docStatus.dot}"></span>
                        <span>{docStatus.label}</span>
                    </span>
                </div>
            </div>
        </div>

        <!-- SEKTOR / DOMAIN DOSSIER -->
        <div class="p-5 rounded-xl border border-slate-200 bg-white shadow-2xs space-y-4">
            <div class="flex items-center justify-between gap-3">
                <div class="flex items-center gap-2">
                    <Tag class="w-4 h-4 text-indigo-600" />
                    <h2 class="text-sm font-bold text-slate-900">Sektor / Domain Keilmuan</h2>
                </div>
                {#if document.sector}
                    {#if document.sector_confirmed}
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold border bg-emerald-50 text-emerald-800 border-emerald-200">
                            <CheckCircle2 class="w-3 h-3" /> Terkonfirmasi
                        </span>
                    {:else}
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold border bg-amber-50 text-amber-800 border-amber-200">
                            <AlertCircle class="w-3 h-3" /> Usulan AI (perlu konfirmasi)
                        </span>
                    {/if}
                {/if}
            </div>

            {#if detecting}
                <p class="text-xs text-slate-500 flex items-center gap-2">
                    <RefreshCw class="w-3.5 h-3.5 animate-spin" /> Menganalisis dokumen untuk mendeteksi sektor…
                </p>
            {:else}
                <div class="flex flex-wrap items-end gap-3">
                    <div class="flex-1 min-w-[220px]">
                        <label class="block text-[11px] font-semibold text-slate-500 mb-1">Sektor dokumen</label>
                        <select
                            bind:value={selectedSector}
                            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-500"
                        >
                            <option value="">— Belum ditentukan —</option>
                            {#each availableSectors as s (s.id)}
                                <option value={s.id}>{s.name}</option>
                            {/each}
                        </select>
                    </div>
                    <button
                        type="button"
                        onclick={detectSector}
                        class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg text-xs font-semibold border border-indigo-200 bg-indigo-50 text-indigo-700 hover:bg-indigo-100 transition-colors"
                    >
                        <Sparkles class="w-3.5 h-3.5" /> Deteksi dengan AI
                    </button>
                    <button
                        type="button"
                        onclick={confirmSector}
                        disabled={savingSector}
                        class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg text-xs font-semibold bg-slate-900 text-white hover:bg-slate-800 disabled:opacity-50 transition-colors"
                    >
                        {savingSector ? 'Menyimpan…' : 'Konfirmasi Sektor'}
                    </button>
                </div>

                {#if sectorMessage}
                    <p class="text-xs {sectorError ? 'text-rose-600' : 'text-emerald-700'}">{sectorMessage}</p>
                {/if}

                <p class="text-[11px] text-slate-400 leading-relaxed">
                    AI hanya mengusulkan. Sektor dipakai sebagai konteks rekomendasi setelah Anda konfirmasi.
                </p>
            {/if}
        </div>

        <!-- EXTRACTION IN PROGRESS BANNER -->
        {#if isExtracting}
            <div class="p-4 rounded-xl border border-amber-200/80 bg-amber-50/70 text-amber-900 text-xs flex items-center gap-3 shadow-2xs">
                <span class="relative flex h-2.5 w-2.5 shrink-0">
                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-amber-400 opacity-75"></span>
                    <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-amber-500"></span>
                </span>
                <p>
                    Proses ekstraksi teks sedang berjalan di latar belakang. Struktur bab dan indikator akan dimuat secara otomatis setelah selesai.
                </p>
            </div>
        {/if}

        <!-- EXTRACTION ERROR BANNER -->
        {#if document.extract_error || document.status === 'failed'}
            <div class="p-4 rounded-xl border border-rose-200 bg-rose-50/70 text-rose-900 text-xs flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 shadow-2xs">
                <div class="flex items-start gap-2.5">
                    <AlertCircle class="w-5 h-5 text-rose-600 shrink-0 mt-0.5" />
                    <div>
                        <p class="font-bold">Ekstraksi Dokumen Gagal</p>
                        <p class="text-rose-700 text-[11px] mt-0.5">
                            {document.extract_error ?? 'Terjadi kesalahan sistem saat memproses dokumen.'}
                        </p>
                    </div>
                </div>
                <button
                    type="button"
                    onclick={retryExtract}
                    disabled={retrying}
                    class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-lg bg-rose-600 text-white font-semibold hover:bg-rose-500 disabled:opacity-50 transition-colors shrink-0 cursor-pointer"
                >
                    <RefreshCw class="w-3.5 h-3.5 {retrying ? 'animate-spin' : ''}" />
                    <span>{retrying ? 'Memproses…' : 'Ekstrak Ulang'}</span>
                </button>
            </div>
        {/if}

        <!-- EXTRACTED CHUNKS LIST -->
        <div class="p-5 rounded-xl border border-slate-200 bg-white shadow-2xs space-y-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <Layers class="w-4 h-4 text-emerald-600" />
                    <h2 class="text-sm font-bold text-slate-900">
                        Hasil Ekstraksi Dokumen ({document.chunks.length} Potongan Teks)
                    </h2>
                </div>
            </div>

            {#if document.chunks.length === 0}
                <div class="p-8 text-center rounded-lg border border-dashed border-slate-200 bg-slate-50/50">
                    <FileText class="w-8 h-8 text-slate-300 mx-auto mb-2" />
                    <p class="text-xs text-slate-500">
                        {#if isExtracting}
                            Sedang menganalisis dan memotong dokumen…
                        {:else}
                            Belum ada potongan teks hasil ekstraksi yang tersimpan.
                        {/if}
                    </p>
                </div>
            {:else}
                <div class="space-y-2.5">
                    {#each document.chunks as chunk (chunk.id)}
                        <div class="p-3.5 rounded-lg border border-slate-200/80 bg-slate-50/40 hover:bg-white hover:border-slate-300 transition-colors">
                            <div class="flex items-center justify-between text-[11px] text-slate-400 mb-1 font-mono">
                                <span>Chunk #{chunk.chunk_index + 1} · Halaman {chunk.page ?? '—'}</span>
                                {#if chunk.section}
                                    <span class="font-medium text-slate-600 font-sans">{chunk.section}</span>
                                {/if}
                            </div>
                            <p class="text-xs text-slate-700 leading-relaxed">{chunk.preview}…</p>
                        </div>
                    {/each}
                </div>
            {/if}
        </div>
    </div>
</Layout>

