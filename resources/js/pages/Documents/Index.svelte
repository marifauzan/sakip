<script>
    import { useForm, Link, usePoll } from '@inertiajs/svelte';
    import Layout from '../Layout.svelte';

    let { documents = [] } = $props();

    const MAX_FILE_SIZE = 50 * 1024 * 1024; // 50 MB
    let clientFileError = $state(null);
    let selectedFileInfo = $state(null);

    const form = useForm({
        title: '',
        type: 'renstra',
        period_start: '',
        period_end: '',
        file: null,
    });

    function submit() {
        clientFileError = null;
        if (!form.file) {
            clientFileError = 'File dokumen wajib dipilih.';
            return;
        }
        if (form.file.size > MAX_FILE_SIZE) {
            clientFileError = 'Ukuran file melebihi batas maksimal 50 MB.';
            return;
        }

        form.post('/documents', {
            forceFormData: true,
            onSuccess: () => {
                form.reset();
                selectedFileInfo = null;
                clientFileError = null;
            },
        });
    }

    function onFile(e) {
        clientFileError = null;
        selectedFileInfo = null;
        const file = e.target.files?.[0];
        if (!file) {
            form.file = null;
            return;
        }

        if (file.size > MAX_FILE_SIZE) {
            const sizeMB = (file.size / (1024 * 1024)).toFixed(1);
            clientFileError = `Ukuran file (${sizeMB} MB) melebihi batas maksimal 50 MB. Harap pilih file yang lebih kecil.`;
            e.target.value = '';
            form.file = null;
            return;
        }

        const sizeMB = (file.size / (1024 * 1024)).toFixed(2);
        selectedFileInfo = `${file.name} (${sizeMB} MB)`;
        form.file = file;
    }

    let hasActiveExtractions = $derived(
        documents.some((d) => d.status === 'uploaded' || d.status === 'extracting')
    );

    const { start, stop } = usePoll(3000, { only: ['documents'] }, { autoStart: false });

    $effect(() => {
        if (hasActiveExtractions) {
            start();
        } else {
            stop();
        }
    });

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
        <div class="flex items-center justify-between">
            <h2 class="text-xl font-bold">Dokumen Perencanaan</h2>
            {#if hasActiveExtractions}
                <div class="flex items-center gap-2 text-xs text-yellow-700 bg-yellow-50 border border-yellow-200 px-3 py-1.5 rounded-md">
                    <span class="inline-block h-2 w-2 animate-pulse rounded-full bg-yellow-500"></span>
                    <span>Memperbarui status ekstraksi otomatis…</span>
                </div>
            {/if}
        </div>

        <!-- Form upload -->
        <form
            onsubmit={(e) => { e.preventDefault(); submit(); }}
            class="rounded-lg border border-gray-200 bg-white p-4 space-y-4"
        >
            <div class="grid grid-cols-2 gap-4">
                <div class="col-span-2">
                    <label for="doc-title" class="block text-sm font-medium text-gray-700">Judul Dokumen</label>
                    <input
                        id="doc-title"
                        type="text"
                        bind:value={form.title}
                        placeholder="Contoh: Renstra Dinas Pendidikan 2025-2029"
                        class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
                    />
                    {#if form.errors.title}<p class="mt-1 text-xs text-red-600">{form.errors.title}</p>{/if}
                </div>

                <div>
                    <label for="doc-type" class="block text-sm font-medium text-gray-700">Jenis Dokumen</label>
                    <select
                        id="doc-type"
                        bind:value={form.type}
                        class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
                    >
                        <option value="renstra">Renstra</option>
                        <option value="rpjmd">RPJMD</option>
                        <option value="rpjmn">RPJMN</option>
                        <option value="renstra_opd">Renstra OPD</option>
                        <option value="lainnya">Lainnya</option>
                    </select>
                    {#if form.errors.type}<p class="mt-1 text-xs text-red-600">{form.errors.type}</p>{/if}
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="doc-period-start" class="block text-sm font-medium text-gray-700">Tahun Awal</label>
                        <input
                            id="doc-period-start"
                            type="number"
                            placeholder="2025"
                            bind:value={form.period_start}
                            class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
                        />
                        {#if form.errors.period_start}<p class="mt-1 text-xs text-red-600">{form.errors.period_start}</p>{/if}
                    </div>
                    <div>
                        <label for="doc-period-end" class="block text-sm font-medium text-gray-700">Tahun Akhir</label>
                        <input
                            id="doc-period-end"
                            type="number"
                            placeholder="2029"
                            bind:value={form.period_end}
                            class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
                        />
                        {#if form.errors.period_end}<p class="mt-1 text-xs text-red-600">{form.errors.period_end}</p>{/if}
                    </div>
                </div>

                <div class="col-span-2">
                    <label for="doc-file" class="block text-sm font-medium text-gray-700">File Dokumen (PDF/DOCX/TXT, maksimal 50 MB)</label>
                    <input
                        id="doc-file"
                        type="file"
                        accept=".pdf,.docx,.doc,.txt"
                        onchange={onFile}
                        class="mt-1 w-full text-sm file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100"
                    />
                    {#if selectedFileInfo}
                        <p class="mt-1 text-xs text-green-700 font-medium">✓ File dipilih: {selectedFileInfo}</p>
                    {/if}
                    {#if clientFileError}
                        <p class="mt-1 text-xs text-red-600 font-medium">{clientFileError}</p>
                    {/if}
                    {#if form.errors.file}
                        <p class="mt-1 text-xs text-red-600 font-medium">{form.errors.file}</p>
                    {/if}
                </div>
            </div>

            <button
                type="submit"
                disabled={form.processing}
                class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500 disabled:opacity-50"
            >
                {form.processing ? 'Mengunggah…' : 'Unggah'}
            </button>
        </form>

        <!-- Daftar Dokumen -->
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
                                <td class="px-4 py-3 text-gray-600 uppercase text-xs font-semibold">{doc.type}</td>
                                <td class="px-4 py-3 text-gray-600">
                                    {doc.period_start ?? '—'}–{doc.period_end ?? '—'}
                                </td>
                                <td class="px-4 py-3 text-gray-600">{doc.sector?.name ?? '—'}</td>
                                <td class="px-4 py-3 text-gray-600">{doc.chunk_count}</td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs {statusColor[doc.status] ?? ''}">
                                        {#if doc.status === 'extracting'}
                                            <span class="inline-block h-1.5 w-1.5 animate-pulse rounded-full bg-yellow-600"></span>
                                        {/if}
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
