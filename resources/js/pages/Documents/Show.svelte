<script>
    import { Link } from '@inertiajs/svelte';
    import Layout from '../Layout.svelte';

    let { document } = $props();
</script>

<Layout>
    <div class="space-y-6">
        <div>
            <Link href="/documents" class="text-sm text-indigo-600 hover:underline">← Kembali ke Dokumen</Link>
            <h2 class="text-xl font-bold mt-2">{document.title}</h2>
            <p class="text-sm text-gray-500">
                {document.type} · {document.period_start ?? '—'}–{document.period_end ?? '—'} · {document.file_name}
            </p>
        </div>

        {#if document.extract_error}
            <div class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700">
                Ekstraksi gagal: {document.extract_error}
            </div>
        {/if}

        <div class="rounded-lg border border-gray-200 bg-white p-4">
            <h3 class="font-semibold mb-2">Hasil Ekstraksi ({document.chunks.length} chunk)</h3>
            {#if document.chunks.length === 0}
                <p class="text-sm text-gray-500">Belum ada hasil ekstraksi.</p>
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
