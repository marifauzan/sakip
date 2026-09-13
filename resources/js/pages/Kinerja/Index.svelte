<script>
    import { useForm, Link } from '@inertiajs/svelte';
    import Layout from '../Layout.svelte';
    import {
        GitFork,
        Plus,
        Search,
        Layers,
        Calendar,
        Building2,
        ArrowRight,
        X,
        CheckCircle2,
        Clock,
        FileSpreadsheet
    } from '@lucide/svelte';

    let { trees = [], sectors = [] } = $props();

    let searchQuery = $state('');
    let selectedSectorFilter = $state('');
    let selectedStatusFilter = $state('');
    let showCreateModal = $state(false);

    const form = useForm({
        name: '',
        period_start: new Date().getFullYear(),
        period_end: new Date().getFullYear() + 4,
        sector_id: ''
    });

    function openCreateModal() {
        form.reset();
        form.clearErrors();
        form.period_start = new Date().getFullYear();
        form.period_end = new Date().getFullYear() + 4;
        showCreateModal = true;
    }

    function closeCreateModal() {
        showCreateModal = false;
    }

    function submitCreate(e) {
        e.preventDefault();
        form.post('/kinerja', {
            onSuccess: () => {
                closeCreateModal();
            }
        });
    }

    // Filtered trees
    let filteredTrees = $derived(
        trees.filter((t) => {
            const matchesQuery = !searchQuery.trim() ||
                t.name.toLowerCase().includes(searchQuery.toLowerCase().trim()) ||
                (t.sector?.name && t.sector.name.toLowerCase().includes(searchQuery.toLowerCase().trim()));

            const matchesSector = !selectedSectorFilter || String(t.sector_id) === String(selectedSectorFilter);
            const matchesStatus = !selectedStatusFilter || t.status === selectedStatusFilter;

            return matchesQuery && matchesSector && matchesStatus;
        })
    );

    // Summary metrics
    let totalTrees = $derived(trees.length);
    let totalNodes = $derived(trees.reduce((acc, t) => acc + (Number(t.nodes_count) || 0), 0));
    let draftCount = $derived(trees.filter((t) => t.status === 'draft').length);
    let activeCount = $derived(trees.filter((t) => t.status === 'final' || t.status === 'active').length);

    function formatStatus(status) {
        if (status === 'final' || status === 'active') {
            return { label: 'Final / Disetujui', class: 'bg-emerald-50 text-emerald-800 border-emerald-200', dot: 'bg-emerald-500' };
        }
        return { label: 'Draft Rancangan', class: 'bg-slate-100 text-slate-700 border-slate-200', dot: 'bg-slate-400' };
    }
</script>

<Layout title="Pohon Kinerja">
    <div class="space-y-6 max-w-7xl">
        <!-- 1. HEADER & ACTION -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-xl sm:text-2xl font-bold tracking-tight text-slate-900">
                    Pohon Kinerja & Perjenjangan
                </h1>
                <p class="mt-1 text-xs sm:text-sm text-slate-500">
                    Struktur logis perjenjangan kinerja (outcome ke output) dan indikator terukur sesuai PermenPAN-RB.
                </p>
            </div>
            <div class="flex items-center gap-2 shrink-0">
                <button
                    type="button"
                    onclick={openCreateModal}
                    class="inline-flex items-center gap-2 px-4 py-2 text-xs sm:text-sm font-semibold rounded-lg bg-emerald-600 text-white hover:bg-emerald-500 transition-colors shadow-2xs cursor-pointer"
                >
                    <Plus class="w-4 h-4" />
                    <span>Buat Rancangan Baru</span>
                </button>
            </div>
        </div>

        <!-- 2. SUMMARY METRICS STRIP -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 sm:gap-4">
            <div class="p-4 rounded-xl border border-slate-200 bg-white shadow-2xs">
                <p class="text-[11px] font-medium text-slate-500 uppercase tracking-wider">Total Rancangan</p>
                <div class="mt-1 flex items-baseline gap-2">
                    <span class="text-2xl font-bold text-slate-900 tabular-nums">{totalTrees}</span>
                    <span class="text-xs text-slate-400">dokumen</span>
                </div>
            </div>

            <div class="p-4 rounded-xl border border-slate-200 bg-white shadow-2xs">
                <p class="text-[11px] font-medium text-slate-500 uppercase tracking-wider">Total Sasaran Kinerja</p>
                <div class="mt-1 flex items-baseline gap-2">
                    <span class="text-2xl font-bold text-slate-900 tabular-nums">{totalNodes}</span>
                    <span class="text-xs text-slate-400">simpul terdaftar</span>
                </div>
            </div>

            <div class="p-4 rounded-xl border border-slate-200 bg-white shadow-2xs">
                <p class="text-[11px] font-medium text-slate-500 uppercase tracking-wider">Draft Berjalan</p>
                <div class="mt-1 flex items-baseline gap-2">
                    <span class="text-2xl font-bold text-slate-700 tabular-nums">{draftCount}</span>
                    <span class="text-xs text-slate-400">dalam penyusunan</span>
                </div>
            </div>

            <div class="p-4 rounded-xl border border-slate-200 bg-white shadow-2xs">
                <p class="text-[11px] font-medium text-slate-500 uppercase tracking-wider">Final / Disetujui</p>
                <div class="mt-1 flex items-baseline gap-2">
                    <span class="text-2xl font-bold text-emerald-700 tabular-nums">{activeCount}</span>
                    <span class="text-xs text-emerald-600 font-medium">siap dievaluasi</span>
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
                    placeholder="Cari nama rancangan pohon kinerja atau sektor..."
                    class="w-full pl-9 pr-4 py-2 text-xs sm:text-sm rounded-lg border border-slate-200 focus:outline-emerald-600 focus:border-emerald-600 placeholder:text-slate-400"
                />
            </div>

            <div class="flex items-center gap-2 shrink-0">
                <select
                    bind:value={selectedSectorFilter}
                    class="px-3 py-2 text-xs sm:text-sm rounded-lg border border-slate-200 bg-white text-slate-700 focus:outline-emerald-600"
                    aria-label="Filter Sektor"
                >
                    <option value="">Semua Sektor</option>
                    {#each sectors as sec (sec.id)}
                        <option value={sec.id}>{sec.name} {sec.is_global ? '(Global)' : ''}</option>
                    {/each}
                </select>

                <select
                    bind:value={selectedStatusFilter}
                    class="px-3 py-2 text-xs sm:text-sm rounded-lg border border-slate-200 bg-white text-slate-700 focus:outline-emerald-600"
                    aria-label="Filter Status"
                >
                    <option value="">Semua Status</option>
                    <option value="draft">Draft Rancangan</option>
                    <option value="final">Final / Disetujui</option>
                </select>

                {#if searchQuery || selectedSectorFilter || selectedStatusFilter}
                    <button
                        type="button"
                        onclick={() => { searchQuery = ''; selectedSectorFilter = ''; selectedStatusFilter = ''; }}
                        class="px-2.5 py-2 text-xs font-semibold text-slate-500 hover:text-slate-800 hover:bg-slate-100 rounded-lg transition-colors cursor-pointer"
                        title="Reset filter"
                    >
                        Reset
                    </button>
                {/if}
            </div>
        </div>

        <!-- 4. LIST / TABLE -->
        {#if filteredTrees.length === 0}
            <div class="p-12 text-center rounded-xl border border-dashed border-slate-300 bg-white">
                <div class="w-12 h-12 rounded-full bg-slate-100 text-slate-400 mx-auto flex items-center justify-center mb-3">
                    <GitFork class="w-6 h-6" />
                </div>
                <h3 class="text-sm font-bold text-slate-900">Tidak ada pohon kinerja yang cocok</h3>
                <p class="mt-1 text-xs text-slate-500 max-w-sm mx-auto">
                    {trees.length === 0
                        ? 'Belum ada rancangan pohon kinerja yang dibuat. Buat rancangan baru untuk memulai cascading kausalitas.'
                        : 'Coba ubah kueri pencarian atau filter sektor dan status di atas.'}
                </p>
                {#if trees.length === 0}
                    <button
                        type="button"
                        onclick={openCreateModal}
                        class="mt-4 inline-flex items-center gap-1.5 px-4 py-2 text-xs font-semibold rounded-lg bg-emerald-600 text-white hover:bg-emerald-500 transition-colors shadow-2xs cursor-pointer"
                    >
                        <Plus class="w-4 h-4" />
                        <span>Mulai Buat Rancangan</span>
                    </button>
                {/if}
            </div>
        {:else}
            <div class="rounded-xl border border-slate-200 bg-white shadow-2xs overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-xs sm:text-sm">
                        <thead>
                            <tr class="border-b border-slate-200 bg-slate-50/70 text-[11px] font-semibold text-slate-500 uppercase tracking-wider">
                                <th class="py-3 px-4">Nama Rancangan</th>
                                <th class="py-3 px-4">Sektor Referensi</th>
                                <th class="py-3 px-4">Periode</th>
                                <th class="py-3 px-4 text-center">Sasaran</th>
                                <th class="py-3 px-4">Status</th>
                                <th class="py-3 px-4 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            {#each filteredTrees as tree (tree.id)}
                                {@const status = formatStatus(tree.status)}
                                <tr class="hover:bg-slate-50/60 transition-colors group">
                                    <td class="py-3.5 px-4">
                                        <div class="flex items-start gap-2.5">
                                            <div class="w-8 h-8 rounded-lg bg-emerald-50 text-emerald-700 flex items-center justify-center shrink-0 mt-0.5">
                                                <GitFork class="w-4 h-4" />
                                            </div>
                                            <div>
                                                <Link
                                                    href={`/kinerja/${tree.id}`}
                                                    class="font-semibold text-slate-900 group-hover:text-emerald-700 transition-colors"
                                                >
                                                    {tree.name}
                                                </Link>
                                                <div class="text-[11px] text-slate-400 font-mono mt-0.5">
                                                    ID: #{tree.id}
                                                </div>
                                            </div>
                                        </div>
                                    </td>

                                    <td class="py-3.5 px-4">
                                        {#if tree.sector}
                                            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-medium bg-slate-100 text-slate-700 border border-slate-200/70">
                                                <Building2 class="w-3 h-3 text-slate-400" />
                                                <span>{tree.sector.name}</span>
                                            </span>
                                        {:else}
                                            <span class="text-xs text-slate-400 italic">Umum / Semua Sektor</span>
                                        {/if}
                                    </td>

                                    <td class="py-3.5 px-4 text-slate-600">
                                        <span class="inline-flex items-center gap-1.5 font-mono text-xs text-slate-600">
                                            <Calendar class="w-3.5 h-3.5 text-slate-400" />
                                            <span>{tree.period_start ?? '—'} – {tree.period_end ?? '—'}</span>
                                        </span>
                                    </td>

                                    <td class="py-3.5 px-4 text-center">
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-xs font-semibold bg-slate-100 text-slate-700 tabular-nums">
                                            <Layers class="w-3 h-3 text-slate-400" />
                                            <span>{tree.nodes_count}</span>
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
                                            href={`/kinerja/${tree.id}`}
                                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-slate-200 text-xs font-semibold text-slate-700 hover:bg-emerald-50 hover:text-emerald-700 hover:border-emerald-200 transition-colors"
                                        >
                                            <span>Buka</span>
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

    <!-- MODAL BUAT RANCANGAN -->
    {#if showCreateModal}
        <div
            class="fixed inset-0 z-50 bg-slate-900/40 backdrop-blur-xs flex items-center justify-center p-4"
            onclick={(e) => { if (e.target === e.currentTarget) closeCreateModal(); }}
            onkeydown={(e) => { if (e.key === 'Escape') closeCreateModal(); }}
            role="dialog"
            aria-modal="true"
            tabindex="-1"
        >
            <div class="bg-white rounded-xl border border-slate-200 shadow-2xl max-w-lg w-full overflow-hidden animate-in fade-in zoom-in-95 duration-150">
                <div class="p-5 border-b border-slate-100 flex items-center justify-between">
                    <div>
                        <h3 class="text-sm sm:text-base font-bold text-slate-900">Buat Rancangan Pohon Kinerja Baru</h3>
                        <p class="text-xs text-slate-500 mt-0.5">Mulai menyusun sasaran strategis, program, dan kegiatan instansi.</p>
                    </div>
                    <button
                        type="button"
                        onclick={closeCreateModal}
                        class="p-1 text-slate-400 hover:text-slate-600 rounded-md cursor-pointer"
                        aria-label="Tutup"
                    >
                        <X class="w-4 h-4" />
                    </button>
                </div>

                <form onsubmit={submitCreate} class="p-5 space-y-4 text-xs sm:text-sm">
                    <div>
                        <label for="modal-tree-name" class="block font-medium text-slate-700 mb-1">
                            Nama Rancangan <span class="text-rose-500">*</span>
                        </label>
                        <input
                            id="modal-tree-name"
                            type="text"
                            bind:value={form.name}
                            required
                            placeholder="mis. Renstra Kementan 2025–2029"
                            class="w-full rounded-lg border border-slate-200 px-3 py-2 text-xs sm:text-sm focus:outline-emerald-600"
                        />
                        {#if form.errors.name}
                            <p class="mt-1 text-xs text-rose-600">{form.errors.name}</p>
                        {/if}
                    </div>

                    <div>
                        <label for="modal-tree-sector" class="block font-medium text-slate-700 mb-1">
                            Sektor Terkait (Opsional)
                        </label>
                        <select
                            id="modal-tree-sector"
                            bind:value={form.sector_id}
                            class="w-full rounded-lg border border-slate-200 px-3 py-2 text-xs sm:text-sm bg-white focus:outline-emerald-600"
                        >
                            <option value="">— Tidak Terikat Sektor Tertentu (Umum) —</option>
                            {#each sectors as sector (sector.id)}
                                <option value={sector.id}>
                                    {sector.name} {sector.is_global ? '(Referensi Global)' : '(Instansi)'}
                                </option>
                            {/each}
                        </select>
                        <p class="mt-1 text-[11px] text-slate-400">
                            Pilih sektor jika ingin memuat Knowledge Pack standar regulasi yang sesuai secara otomatis.
                        </p>
                        {#if form.errors.sector_id}
                            <p class="mt-1 text-xs text-rose-600">{form.errors.sector_id}</p>
                        {/if}
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label for="modal-period-start" class="block font-medium text-slate-700 mb-1">
                                Tahun Awal
                            </label>
                            <input
                                id="modal-period-start"
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
                            <label for="modal-period-end" class="block font-medium text-slate-700 mb-1">
                                Tahun Akhir
                            </label>
                            <input
                                id="modal-period-end"
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
                            onclick={closeCreateModal}
                            class="px-3.5 py-2 rounded-lg border border-slate-200 text-slate-700 font-semibold hover:bg-slate-50 transition-colors cursor-pointer"
                        >
                            Batal
                        </button>
                        <button
                            type="submit"
                            disabled={form.processing}
                            class="px-4 py-2 rounded-lg bg-emerald-600 text-white font-semibold hover:bg-emerald-500 disabled:opacity-50 transition-colors shadow-2xs cursor-pointer"
                        >
                            {form.processing ? 'Menyimpan...' : 'Buat Rancangan'}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    {/if}
</Layout>

