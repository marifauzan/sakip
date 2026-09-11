<script>
    import { useForm, Link } from '@inertiajs/svelte';
    import Layout from '../Layout.svelte';

    let { documents = [] } = $props();

    const form = useForm({
        title: '',
        type: 'renstra',
        period_start: '',
        period_end: '',
        file: null,
    });

    function submit(e) {
        form.post('/documents', { forceFormData: true });
    }

    function onFile(e) {
        form.file = e.target.files[0] ?? null;
    }

    const statusLabel = {
        uploaded: 'Diunggah',
        extracting: 'Ekstraksi…',
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
        <h2 class="text-xl font-bold">Dokumen Perencanaan</h2>

        <!-- Form upload -->
        <form
            onsubmit={(e) => { e.preventDefault(); submit(); }}
            class="rounded-lg border border-gray-200 bg-white p-4 space-y-4"
        >
            <div class="grid grid-cols-2 gap-4">
                <div class="col-span-2">
                    <label class="block text-sm font-medium text-gray-700">Judul Dokumen</label>
                    <input type="text" bind:value={form.title} class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm" />
                    {#if form.errors.title}<p class="mt-1 text-xs text-red-600">{form.errors.title}</p>{/if}
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Jenis</label>
                    <select bind:value={form.type} class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
                        <option value="renstra">Renstra</option>
                        <option value="rpjmd">RPJMD</option>
                        <option value="rpjmn">RPJMN</option>
                        <option value="renstra_opd">Renstra OPD</option>
                        <option value="lainnya">Lainnya</option>
                    </select>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Tahun Awal</label>
                        <input type="number" bind:value={form.period_start} class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Tahun Akhir</label>
                        <input type="number" bind:value={form.period_end} class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm" />
                    </div>
                </div>

                <div class="col-span-2">
                    <label class="block text-sm font-medium text-gray-700">File (PDF/DOCX/TXT, max 20MB)</label>
                    <input type="file" accept=".pdf,.docx,.doc,.txt" onchange={onFile} class="mt-1 w-full text-sm" />
                    {#if form.errors.file}<p class="mt-1 text-xs text-red-600">{form.errors.file}</p>{/if}
                </div>
            </div>

            <button type="submit" disabled={form.processing} class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500 disabled:opacity-50">
                {form.processing ? 'Mengunggah…' : 'Unggah'}
            </button>
        </form>

        <!-- Daftar -->
        {#if documents.length === 0}
            <p class="text-sm text-gray-500">Belum ada dokumen.</p>
        {:else}
            <div class="overflow-hidden rounded-lg border border-gray-200 bg-white">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr class="text-left text-gray-500">
                            <th class="px-4 py-3">Judul</th>
                            <th class="px-4 py-3">Jenis</th>
                            <th class="px-4 py-3">Periode</th>
                            <th class="px-4 py-3">Sektor</th>
                            <th class="px-4 py-3">Chunk</th>
                            <th class="px-4 py-3">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        {#each documents as doc (doc.id)}
                            <tr>
                                <td class="px-4 py-3">
                                    <Link href={`/documents/${doc.id}`} class="text-indigo-600 hover:underline font-medium">
                                        {doc.title}
                                    </Link>
                                    <div class="text-xs text-gray-400">{doc.file_name}</div>
                                </td>
                                <td class="px-4 py-3 text-gray-600">{doc.type}</td>
                                <td class="px-4 py-3 text-gray-600">
                                    {doc.period_start ?? '—'}–{doc.period_end ?? '—'}
                                </td>
                                <td class="px-4 py-3 text-gray-600">{doc.sector?.name ?? '—'}</td>
                                <td class="px-4 py-3 text-gray-600">{doc.chunk_count}</td>
                                <td class="px-4 py-3">
                                    <span class="rounded-full px-2 py-0.5 text-xs {statusColor[doc.status] ?? ''}">
                                        {statusLabel[doc.status] ?? doc.status}
                                    </span>
                                </td>
                            </tr>
                        {/each}
                    </tbody>
                </table>
            </div>
        {/if}
    </div>
</Layout>
