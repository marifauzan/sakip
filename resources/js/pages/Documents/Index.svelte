<script>
    import { useForm, Link, usePoll } from '@inertiajs/svelte';
    import Layout from '../Layout.svelte';
    import {
        FileText,
        UploadCloud,
        Plus,
        Search,
        Calendar,
        Layers,
        Building2,
        ArrowRight,
        X,
        AlertCircle,
        CheckCircle2,
        Clock,
        FileUp,
        Loader2
    } from '@lucide/svelte';

    let { documents = [] } = $props();

    const MAX_FILE_SIZE = 50 * 1024 * 1024; // 50 MB
    let clientFileError = $state(null);
    let selectedFileInfo = $state(null);
    let showUploadModal = $state(false);
    let searchQuery = $state('');
    let selectedTypeFilter = $state('');
    let selectedStatusFilter = $state('');
    let isDragOver = $state(false);

    const form = useForm({
        title: '',
        type: 'renstra',
        period_start: new Date().getFullYear(),
        period_end: new Date().getFullYear() + 4,
        file: null,
    });

    function openUploadModal() {
        form.reset();
        form.clearErrors();
        form.type = 'renstra';
        form.period_start = new Date().getFullYear();
        form.period_end = new Date().getFullYear() + 4;
        selectedFileInfo = null;
        clientFileError = null;
        showUploadModal = true;
    }

    function closeUploadModal() {
        showUploadModal = false;
        isDragOver = false;
    }

    function handleDrop(e) {
        e.preventDefault();
        isDragOver = false;
        const file = e.dataTransfer?.files?.[0];
        if (file) {
            processFile(file);
        }
    }

    function onFile(e) {
        const file = e.target.files?.[0];
        if (file) {
            processFile(file);
        }
    }

    function processFile(file) {
        clientFileError = null;
        selectedFileInfo = null;

        if (file.size > MAX_FILE_SIZE) {
            const sizeMB = (file.size / (1024 * 1024)).toFixed(1);
            clientFileError = `Ukuran file (${sizeMB} MB) melebihi batas maksimal 50 MB.`;
            form.file = null;
            return;
        }

        const sizeMB = (file.size / (1024 * 1024)).toFixed(2);
        selectedFileInfo = {
            name: file.name,
            size: `${sizeMB} MB`,
        };
        form.file = file;

        // Auto-fill title if empty
        if (!form.title.trim()) {
            const cleanName = file.name.replace(/\.[^/.]+$/, '');
            form.title = cleanName.charAt(0).toUpperCase() + cleanName.slice(1);
        }
    }

    function submitUpload(e) {
        e.preventDefault();
        clientFileError = null;
        if (!form.file) {
            clientFileError = 'Silakan pilih file dokumen terlebih dahulu.';
            return;
        }

        form.post('/documents', {
            forceFormData: true,
            onSuccess: () => {
                closeUploadModal();
            },
        });
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

    // Filtering
    let filteredDocuments = $derived(
        documents.filter((d) => {
            const q = searchQuery.toLowerCase().trim();
            const matchesQuery = !q ||
                d.title.toLowerCase().includes(q) ||
                (d.file_name && d.file_name.toLowerCase().includes(q));

            const matchesType = !selectedTypeFilter || d.type === selectedTypeFilter;
            const matchesStatus = !selectedStatusFilter || d.status === selectedStatusFilter;

            return matchesQuery && matchesType && matchesStatus;
        })
    );

    // Metric counters
    let totalDocs = $derived(documents.length);
    let extractedCount = $derived(documents.filter((d) => d.status === 'extracted').length);
    let totalChunks = $derived(documents.reduce((acc, d) => acc + (Number(d.chunk_count) || 0), 0));
    let processingCount = $derived(documents.filter((d) => d.status === 'uploaded' || d.status === 'extracting').length);

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
            default:
                return { label: status, class: 'bg-slate-100 text-slate-700 border-slate-200', dot: 'bg-slate-400' };
        }
    }

    function formatDocType(type) {
        const types = {
            renstra: 'Renstra',
            rpjmd: 'RPJMD',
            rpjmn: 'RPJMN',
            renstra_opd: 'Renstra OPD',
            lainnya: 'Lainnya',
        };
        return types[type] || (type ? type.toUpperCase() : 'Dokumen');
    }
</script>

<Layout title="Dokumen Perencanaan">
    <div class="space-y-6 max-w-7xl">
        <!-- 1. HEADER & ACTION -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-xl sm:text-2xl font-bold tracking-tight text-slate-900">
                    Dokumen Perencanaan
                </h1>
                <p class="mt-1 text-xs sm:text-sm text-slate-500">
                    Repositori dokumen sumber (Renstra, RPJMD, dsb) untuk ekstraksi otomatis sasaran dan indikator kinerja via AI.
                </p>
            </div>
            <div class="flex items-center gap-2 shrink-0">
                <button
                    type="button"
                    onclick={openUploadModal}
                    class="inline-flex items-center gap-2 px-4 py-2 text-xs sm:text-sm font-semibold rounded-lg bg-emerald-600 text-white hover:bg-emerald-500 transition-colors shadow-2xs cursor-pointer"
                >
                    <Plus class="w-4 h-4" />
                    <span>Unggah Dokumen</span>
                </button>
            </div>
        </div>

        <!-- ACTIVE EXTRACTION BANNER -->
        {#if hasActiveExtractions}
            <div class="flex items-center gap-3 p-3.5 rounded-xl border border-amber-200/80 bg-amber-50/70 text-amber-900 text-xs shadow-2xs">
                <span class="relative flex h-2.5 w-2.5 shrink-0">
                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-amber-400 opacity-75"></span>
                    <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-amber-500"></span>
                </span>
                <div class="flex-1">
                    <p class="font-semibold">Proses Ekstraksi AI Sedang Berjalan</p>
                    <p class="text-amber-800 text-[11px] mt-0.5">Sistem sedang mengekstrak bab, pohon sasaran, dan indikator secara otomatis. Halaman akan diperbarui seketika.</p>
                </div>
            </div>
        {/if}

        <!-- 2. SUMMARY METRICS STRIP -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 sm:gap-4">
            <div class="p-4 rounded-xl border border-slate-200 bg-white shadow-2xs">
                <p class="text-[11px] font-medium text-slate-500 uppercase tracking-wider">Total Dokumen</p>
                <div class="mt-1 flex items-baseline gap-2">
                    <span class="text-2xl font-bold text-slate-900 tabular-nums">{totalDocs}</span>
                    <span class="text-xs text-slate-400">file</span>
                </div>
            </div>

            <div class="p-4 rounded-xl border border-slate-200 bg-white shadow-2xs">
                <p class="text-[11px] font-medium text-slate-500 uppercase tracking-wider">Selesai Diekstrak</p>
                <div class="mt-1 flex items-baseline gap-2">
                    <span class="text-2xl font-bold text-emerald-700 tabular-nums">{extractedCount}</span>
                    <span class="text-xs text-emerald-600 font-medium">terindeks</span>
                </div>
            </div>

            <div class="p-4 rounded-xl border border-slate-200 bg-white shadow-2xs">
                <p class="text-[11px] font-medium text-slate-500 uppercase tracking-wider">Potongan Teks (Chunks)</p>
                <div class="mt-1 flex items-baseline gap-2">
                    <span class="text-2xl font-bold text-slate-900 tabular-nums">{totalChunks}</span>
                    <span class="text-xs text-slate-400">chunk terindeks</span>
                </div>
            </div>

            <div class="p-4 rounded-xl border border-slate-200 bg-white shadow-2xs">
                <p class="text-[11px] font-medium text-slate-500 uppercase tracking-wider">Dalam Proses</p>
                <div class="mt-1 flex items-baseline gap-2">
                    <span class="text-2xl font-bold {processingCount > 0 ? 'text-amber-600' : 'text-slate-500'} tabular-nums">{processingCount}</span>
                    <span class="text-xs text-slate-400">dokumen</span>
                </div>
            </div>
        </div>

        <!-- 3. FILTERS & SEARCH -->
        <div class="p-3.5 rounded-xl border border-slate-200 bg-white shadow-2xs flex flex-col md:flex-row items-stretch md:items-center justify-between gap-3">
            <div class="relative flex-1">
                <Search class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
                <input
                    type="text"
                    bind:value={searchQuery}
                    placeholder="Cari judul dokumen atau nama file..."
                    class="w-full pl-9 pr-4 py-2 text-xs sm:text-sm rounded-lg border border-slate-200 focus:outline-emerald-600 focus:border-emerald-600 placeholder:text-slate-400"
                />
            </div>

            <div class="flex items-center gap-2 shrink-0">
                <select
                    bind:value={selectedTypeFilter}
                    class="px-3 py-2 text-xs sm:text-sm rounded-lg border border-slate-200 bg-white text-slate-700 focus:outline-emerald-600"
                    aria-label="Filter Jenis Dokumen"
                >
                    <option value="">Semua Jenis</option>
                    <option value="renstra">Renstra</option>
                    <option value="rpjmd">RPJMD</option>
                    <option value="rpjmn">RPJMN</option>
                    <option value="renstra_opd">Renstra OPD</option>
                    <option value="lainnya">Lainnya</option>
                </select>

                <select
                    bind:value={selectedStatusFilter}
                    class="px-3 py-2 text-xs sm:text-sm rounded-lg border border-slate-200 bg-white text-slate-700 focus:outline-emerald-600"
                    aria-label="Filter Status"
                >
                    <option value="">Semua Status</option>
                    <option value="extracted">Selesai</option>
                    <option value="extracting">Ekstraksi</option>
                    <option value="uploaded">Diunggah</option>
                    <option value="failed">Gagal</option>
                </select>

                {#if searchQuery || selectedTypeFilter || selectedStatusFilter}
                    <button
                        type="button"
                        onclick={() => { searchQuery = ''; selectedTypeFilter = ''; selectedStatusFilter = ''; }}
                        class="px-2.5 py-2 text-xs font-semibold text-slate-500 hover:text-slate-800 hover:bg-slate-100 rounded-lg transition-colors cursor-pointer"
                        title="Reset filter"
                    >
                        Reset
                    </button>
                {/if}
            </div>
        </div>

        <!-- 4. LIST / TABLE -->
        {#if filteredDocuments.length === 0}
            <div class="p-12 text-center rounded-xl border border-dashed border-slate-300 bg-white">
                <div class="w-12 h-12 rounded-full bg-slate-100 text-slate-400 mx-auto flex items-center justify-center mb-3">
                    <FileUp class="w-6 h-6" />
                </div>
                <h3 class="text-sm font-bold text-slate-900">Belum ada dokumen perencanaan</h3>
                <p class="mt-1 text-xs text-slate-500 max-w-sm mx-auto">
                    {documents.length === 0
                        ? 'Unggah dokumen Renstra atau RPJMD untuk mengekstrak sasaran strategis secara otomatis dengan AI.'
                        : 'Tidak ada dokumen yang cocok dengan filter pencarian.'}
                </p>
                {#if documents.length === 0}
                    <button
                        type="button"
                        onclick={openUploadModal}
                        class="mt-4 inline-flex items-center gap-1.5 px-4 py-2 text-xs font-semibold rounded-lg bg-emerald-600 text-white hover:bg-emerald-500 transition-colors shadow-2xs cursor-pointer"
                    >
                        <Plus class="w-4 h-4" />
                        <span>Unggah Dokumen Pertama</span>
                    </button>
                {/if}
            </div>
        {:else}
            <div class="rounded-xl border border-slate-200 bg-white shadow-2xs overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-xs sm:text-sm">
                        <thead>
                            <tr class="border-b border-slate-200 bg-slate-50/70 text-[11px] font-semibold text-slate-500 uppercase tracking-wider">
                                <th class="py-3 px-4">Judul Dokumen</th>
                                <th class="py-3 px-4">Jenis</th>
                                <th class="py-3 px-4">Periode</th>
                                <th class="py-3 px-4">Sektor</th>
                                <th class="py-3 px-4 text-center">Hasil Chunk</th>
                                <th class="py-3 px-4">Status Ekstraksi</th>
                                <th class="py-3 px-4 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            {#each filteredDocuments as doc (doc.id)}
                                {@const status = formatDocStatus(doc.status)}
                                <tr class="hover:bg-slate-50/60 transition-colors group">
                                    <td class="py-3.5 px-4">
                                        <div class="flex items-start gap-2.5">
                                            <div class="w-8 h-8 rounded-lg bg-emerald-50 text-emerald-700 flex items-center justify-center shrink-0 mt-0.5">
                                                <FileText class="w-4 h-4" />
                                            </div>
                                            <div class="min-w-0">
                                                <Link
                                                    href={`/documents/${doc.id}`}
                                                    class="font-semibold text-slate-900 group-hover:text-emerald-700 transition-colors block truncate"
                                                >
                                                    {doc.title}
                                                </Link>
                                                <div class="text-[11px] text-slate-400 truncate mt-0.5">
                                                    {doc.file_name}
                                                </div>
                                            </div>
                                        </div>
                                    </td>

                                    <td class="py-3.5 px-4">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold uppercase tracking-wider bg-slate-100 text-slate-700 border border-slate-200/70">
                                            {formatDocType(doc.type)}
                                        </span>
                                    </td>

                                    <td class="py-3.5 px-4 text-slate-600">
                                        <span class="inline-flex items-center gap-1.5 font-mono text-xs text-slate-600">
                                            <Calendar class="w-3.5 h-3.5 text-slate-400" />
                                            <span>{doc.period_start ?? '—'} – {doc.period_end ?? '—'}</span>
                                        </span>
                                    </td>

                                    <td class="py-3.5 px-4">
                                        {#if doc.sector}
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs font-medium bg-slate-100 text-slate-700">
                                                <Building2 class="w-3 h-3 text-slate-400" />
                                                <span>{doc.sector.name}</span>
                                            </span>
                                        {:else}
                                            <span class="text-xs text-slate-400">—</span>
                                        {/if}
                                    </td>

                                    <td class="py-3.5 px-4 text-center">
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-xs font-semibold bg-slate-100 text-slate-700 tabular-nums">
                                            <Layers class="w-3 h-3 text-slate-400" />
                                            <span>{doc.chunk_count}</span>
                                        </span>
                                    </td>

                                    <td class="py-3.5 px-4">
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-semibold border {status.class}">
                                            <span class="w-1.5 h-1.5 rounded-full {status.dot}"></span>
                                            <span>{status.label}</span>
                                        </span>
                                    </td>

                                    <td class="py-3.5 px-4 text-right">
                                        <Link
                                            href={`/documents/${doc.id}`}
                                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-slate-200 text-xs font-semibold text-slate-700 hover:bg-emerald-50 hover:text-emerald-700 hover:border-emerald-200 transition-colors"
                                        >
                                            <span>Detail</span>
                                            <ArrowRight class="w-3.5 h-3.5" />
                                        </Link>
                                    </td>
                                </tr>
                            {/each}
                        </tbody>
                    </table>
                </div>
            </div>
        {/if}
    </div>

    <!-- MODAL UNGGAH DOKUMEN -->
    {#if showUploadModal}
        <div
            class="fixed inset-0 z-50 bg-slate-900/40 backdrop-blur-xs flex items-center justify-center p-4"
            onclick={(e) => { if (e.target === e.currentTarget) closeUploadModal(); }}
            onkeydown={(e) => { if (e.key === 'Escape') closeUploadModal(); }}
            role="dialog"
            aria-modal="true"
            tabindex="-1"
        >
            <div class="bg-white rounded-xl border border-slate-200 shadow-2xl max-w-lg w-full overflow-hidden animate-in fade-in zoom-in-95 duration-150">
                <div class="p-5 border-b border-slate-100 flex items-center justify-between">
                    <div>
                        <h3 class="text-sm sm:text-base font-bold text-slate-900">Unggah Dokumen Perencanaan</h3>
                        <p class="text-xs text-slate-500 mt-0.5">Dokumen akan diproses otomatis oleh pipeline ekstraksi teks AI.</p>
                    </div>
                    <button
                        type="button"
                        onclick={closeUploadModal}
                        class="p-1 text-slate-400 hover:text-slate-600 rounded-md cursor-pointer"
                        aria-label="Tutup"
                    >
                        <X class="w-4 h-4" />
                    </button>
                </div>

                <form onsubmit={submitUpload} class="p-5 space-y-4 text-xs sm:text-sm">
                    <!-- Dropzone -->
                    <div>
                        <label for="doc-file-input" class="block font-medium text-slate-700 mb-1.5">
                            File Dokumen (PDF, DOCX, TXT) <span class="text-rose-500">*</span>
                        </label>
                        <!-- svelte-ignore a11y_click_events_have_key_events -->
                        <!-- svelte-ignore a11y_no_static_element_interactions -->
                        <div
                            class="relative border-2 border-dashed rounded-xl p-6 text-center transition-colors {isDragOver ? 'border-emerald-500 bg-emerald-50/50' : 'border-slate-200 hover:border-slate-300 bg-slate-50/50'}"
                            ondragover={(e) => { e.preventDefault(); isDragOver = true; }}
                            ondragleave={() => { isDragOver = false; }}
                            ondrop={handleDrop}
                        >
                            <input
                                id="doc-file-input"
                                type="file"
                                accept=".pdf,.docx,.doc,.txt"
                                onchange={onFile}
                                class="absolute inset-0 w-full h-full opacity-0 cursor-pointer"
                            />
                            <div class="flex flex-col items-center justify-center">
                                <div class="w-10 h-10 rounded-full bg-emerald-50 text-emerald-600 flex items-center justify-center mb-2">
                                    <UploadCloud class="w-5 h-5" />
                                </div>
                                {#if selectedFileInfo}
                                    <div class="space-y-1">
                                        <p class="text-xs font-semibold text-emerald-800">{selectedFileInfo.name}</p>
                                        <p class="text-[11px] text-slate-500">{selectedFileInfo.size} · Klik untuk mengganti</p>
                                    </div>
                                {:else}
                                    <p class="text-xs font-medium text-slate-700">
                                        <span class="text-emerald-700 font-semibold underline">Pilih file</span> atau seret ke sini
                                    </p>
                                    <p class="text-[11px] text-slate-400 mt-1">Maksimal 50 MB per dokumen</p>
                                {/if}
                            </div>
                        </div>

                        {#if clientFileError}
                            <p class="mt-1.5 text-xs text-rose-600 flex items-center gap-1">
                                <AlertCircle class="w-3.5 h-3.5 shrink-0" />
                                <span>{clientFileError}</span>
                            </p>
                        {/if}
                        {#if form.errors.file}
                            <p class="mt-1.5 text-xs text-rose-600 flex items-center gap-1">
                                <AlertCircle class="w-3.5 h-3.5 shrink-0" />
                                <span>{form.errors.file}</span>
                            </p>
                        {/if}
                    </div>

                    <!-- Title -->
                    <div>
                        <label for="modal-doc-title" class="block font-medium text-slate-700 mb-1">
                            Judul Dokumen <span class="text-rose-500">*</span>
                        </label>
                        <input
                            id="modal-doc-title"
                            type="text"
                            bind:value={form.title}
                            required
                            placeholder="Contoh: Renstra Kementerian Pertanian 2025–2029"
                            class="w-full rounded-lg border border-slate-200 px-3 py-2 text-xs sm:text-sm focus:outline-emerald-600"
                        />
                        {#if form.errors.title}
                            <p class="mt-1 text-xs text-rose-600">{form.errors.title}</p>
                        {/if}
                    </div>

                    <!-- Type -->
                    <div>
                        <label for="modal-doc-type" class="block font-medium text-slate-700 mb-1">
                            Jenis Dokumen <span class="text-rose-500">*</span>
                        </label>
                        <select
                            id="modal-doc-type"
                            bind:value={form.type}
                            class="w-full rounded-lg border border-slate-200 px-3 py-2 text-xs sm:text-sm bg-white focus:outline-emerald-600"
                        >
                            <option value="renstra">Renstra (Rencana Strategis)</option>
                            <option value="rpjmd">RPJMD (Daerah)</option>
                            <option value="rpjmn">RPJMN (Nasional)</option>
                            <option value="renstra_opd">Renstra OPD / Perangkat Daerah</option>
                            <option value="lainnya">Lainnya</option>
                        </select>
                        {#if form.errors.type}
                            <p class="mt-1 text-xs text-rose-600">{form.errors.type}</p>
                        {/if}
                    </div>

                    <!-- Period -->
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label for="modal-doc-period-start" class="block font-medium text-slate-700 mb-1">
                                Tahun Awal
                            </label>
                            <input
                                id="modal-doc-period-start"
                                type="number"
                                bind:value={form.period_start}
                                placeholder="2025"
                                class="w-full rounded-lg border border-slate-200 px-3 py-2 text-xs sm:text-sm font-mono focus:outline-emerald-600"
                            />
                            {#if form.errors.period_start}
                                <p class="mt-1 text-xs text-rose-600">{form.errors.period_start}</p>
                            {/if}
                        </div>

                        <div>
                            <label for="modal-doc-period-end" class="block font-medium text-slate-700 mb-1">
                                Tahun Akhir
                            </label>
                            <input
                                id="modal-doc-period-end"
                                type="number"
                                bind:value={form.period_end}
                                placeholder="2029"
                                class="w-full rounded-lg border border-slate-200 px-3 py-2 text-xs sm:text-sm font-mono focus:outline-emerald-600"
                            />
                            {#if form.errors.period_end}
                                <p class="mt-1 text-xs text-rose-600">{form.errors.period_end}</p>
                            {/if}
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-2.5 pt-3 border-t border-slate-100">
                        <button
                            type="button"
                            onclick={closeUploadModal}
                            class="px-3.5 py-2 rounded-lg border border-slate-200 text-slate-700 font-semibold hover:bg-slate-50 transition-colors cursor-pointer"
                        >
                            Batal
                        </button>
                        <button
                            type="submit"
                            disabled={form.processing}
                            class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-emerald-600 text-white font-semibold hover:bg-emerald-500 disabled:opacity-50 transition-colors shadow-2xs cursor-pointer"
                        >
                            {#if form.processing}
                                <Loader2 class="w-4 h-4 animate-spin" />
                                <span>Mengunggah...</span>
                            {:else}
                                <UploadCloud class="w-4 h-4" />
                                <span>Unggah & Ekstrak</span>
                            {/if}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    {/if}
</Layout>

