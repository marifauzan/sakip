<script>
    import { Link, router, usePoll } from '@inertiajs/svelte';
    import Layout from '../Layout.svelte';

    let { document } = $props();

    let retrying = $state(false);

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

    const statusLabel = {
        uploaded: 'Diunggah (menunggu antrean)',
        extracting: 'Sedang mengekstrak teks…',
        extracted: 'Selesai',
        failed: 'Gagal',
    };
    const statusColor = {
        uploaded: 'bg-gray-100 text-gray-700',
        extracting: 'bg-yellow-100 text-yellow-800',
        extracted: 'bg-green-100 text-green-800',
        failed: 'bg-red-100 text-red-800',
    };
</script>

<Layout>
    <div class="space-y-6">
        <div class="flex items-start justify-between">
            <div>
                <Link href="/documents" class="text-sm text-indigo-600 hover:underline">← Kembali ke Dokumen</Link>
                <h2 class="text-xl font-bold mt-2">{document.title}</h2>
                <p class="text-sm text-gray-500">
                    <span class="uppercase font-semibold text-xs">{document.type}</span> ·
                    Periode: {document.period_start ?? '—'}–{document.period_end ?? '—'} ·
                    File: {document.file_name}
                </p>
            </div>
            <span class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-medium {statusColor[document.status] ?? ''}">
                {#if document.status === 'extracting'}
                    <span class="inline-block h-2 w-2 animate-pulse rounded-full bg-yellow-500"></span>
                {/if}
                {statusLabel[document.status] ?? document.status}
            </span>
        </div>

        {#if isExtracting}
            <div class="rounded-lg border border-yellow-200 bg-yellow-50 p-4 text-sm text-yellow-800 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <span class="inline-block h-2.5 w-2.5 animate-pulse rounded-full bg-yellow-500"></span>
                    <span>Proses ekstraksi teks sedang berjalan di latar belakang. Status akan diperbarui secara otomatis.</span>
                </div>
            </div>
        {/if}

        {#if document.extract_error || document.status === 'failed'}
            <div class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700 flex items-center justify-between gap-4">
                <div>
                    <p class="font-semibold">Ekstraksi Dokumen Gagal</p>
                    <p class="mt-1 text-xs text-red-600">
                        {document.extract_error ?? 'Terjadi kesalahan sistem saat memproses dokumen.'}
                    </p>
                </div>
                <button
                    type="button"
                    onclick={retryExtract}
                    disabled={retrying}
                    class="shrink-0 rounded-md bg-red-600 px-3.5 py-2 text-xs font-semibold text-white hover:bg-red-500 disabled:opacity-50"
                >
                    {retrying ? 'Memproses…' : 'Ekstrak Ulang'}
                </button>
            </div>
        {/if}

        <div class="rounded-lg border border-gray-200 bg-white p-4">
            <h3 class="font-semibold mb-2">Hasil Ekstraksi ({document.chunks.length} chunk)</h3>
            {#if document.chunks.length === 0}
                <p class="text-sm text-gray-500">
                    {#if isExtracting}
                        Menunggu hasil ekstraksi…
                    {:else}
                        Belum ada potongan teks hasil ekstraksi.
                    {/if}
                </p>
            {:else}
                <ul class="space-y-2">
                    {#each document.chunks as chunk (chunk.id)}
                        <li class="rounded-md border border-gray-100 bg-gray-50 p-3">
                            <div class="text-xs text-gray-400 mb-1">
                                Chunk #{chunk.chunk_index + 1} · Halaman {chunk.page ?? '—'}
                                {#if chunk.section} · <span class="font-medium text-gray-500">{chunk.section}</span>{/if}
                            </div>
                            <p class="text-sm text-gray-700">{chunk.preview}…</p>
                        </li>
                    {/each}
                </ul>
            {/if}
        </div>
    </div>
</Layout>
