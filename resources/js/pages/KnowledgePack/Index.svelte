<script>
    import { useForm, router } from '@inertiajs/svelte';
    import Layout from '../Layout.svelte';

    let { sectors = [], selected_sector_id = null, can_manage = false, user_role = '', flash = {} } = $props();

    let userSelectedId = $state(null);
    let searchQuery = $state('');

    // Keep userSelectedId in sync with server-provided selected_sector_id (e.g. after redirects or navigations)
    $effect(() => {
        if (selected_sector_id !== null && selected_sector_id !== undefined) {
            userSelectedId = selected_sector_id;
        }
    });

    let selectedId = $derived(
        (userSelectedId && sectors.some((s) => s.id === userSelectedId))
            ? userSelectedId
            : (selected_sector_id && sectors.some((s) => s.id === selected_sector_id))
                ? selected_sector_id
                : (sectors.length > 0 ? sectors[0].id : null)
    );

    let activeSector = $derived(
        sectors.find((s) => s.id === selectedId) || sectors[0] || null
    );

    let filteredSectors = $derived(
        sectors.filter((s) => {
            const q = searchQuery.toLowerCase().trim();
            if (!q) return true;
            return s.name.toLowerCase().includes(q) || (s.description && s.description.toLowerCase().includes(q));
        })
    );

    function selectSector(id) {
        userSelectedId = id;
    }

    // --- Modal Sektor State ---
    let showSectorModal = $state(false);
    let sectorModalMode = $state('create'); // 'create' | 'edit'
    let editingSectorId = $state(null);
    let isSlugManual = $state(false);

    const sectorForm = useForm({
        name: '',
        slug: '',
        description: '',
    });

    function slugify(text) {
        return text
            .toString()
            .toLowerCase()
            .trim()
            .replace(/\s+/g, '-')
            .replace(/[^\w\-]+/g, '')
            .replace(/\-\-+/g, '-');
    }

    function onSectorNameChange() {
        if (!isSlugManual && sectorModalMode === 'create') {
            sectorForm.slug = slugify(sectorForm.name);
        }
    }

    function openCreateSectorModal() {
        sectorModalMode = 'create';
        editingSectorId = null;
        isSlugManual = false;
        sectorForm.reset();
        sectorForm.clearErrors();
        showSectorModal = true;
    }

    function openEditSectorModal(sector) {
        sectorModalMode = 'edit';
        editingSectorId = sector.id;
        isSlugManual = true;
        sectorForm.name = sector.name;
        sectorForm.slug = sector.slug || '';
        sectorForm.description = sector.description || '';
        sectorForm.clearErrors();
        showSectorModal = true;
    }

    function closeSectorModal() {
        showSectorModal = false;
    }

    function submitSector() {
        if (sectorModalMode === 'create') {
            sectorForm.post('/knowledge-packs/sectors', {
                onSuccess: () => {
                    closeSectorModal();
                },
            });
        } else {
            sectorForm.put(`/knowledge-packs/sectors/${editingSectorId}`, {
                onSuccess: () => {
                    closeSectorModal();
                },
            });
        }
    }

    function deleteSector(sector) {
        if (confirm(`Apakah Anda yakin ingin menghapus sektor "${sector.name}" beserta semua knowledge pack di dalamnya?`)) {
            router.delete(`/knowledge-packs/sectors/${sector.id}`, {
                preserveScroll: true,
                onSuccess: () => {
                    if (selectedId === sector.id) {
                        const remaining = sectors.filter((s) => s.id !== sector.id);
                        userSelectedId = remaining.length > 0 ? remaining[0].id : null;
                    }
                },
            });
        }
    }

    // --- Modal Knowledge Pack State ---
    let showPackModal = $state(false);
    let packModalMode = $state('create'); // 'create' | 'edit'
    let editingPackId = $state(null);

    const packForm = useForm({
        title: '',
        source: '',
        version: 1,
        is_active: true,
        content: '',
    });

    function openCreatePackModal() {
        if (!activeSector || activeSector.is_global) return;
        packModalMode = 'create';
        editingPackId = null;
        packForm.reset();
        packForm.version = 1;
        packForm.is_active = true;
        packForm.clearErrors();
        showPackModal = true;
    }

    function openEditPackModal(pack) {
        packModalMode = 'edit';
        editingPackId = pack.id;
        packForm.title = pack.title;
        packForm.source = pack.source || '';
        packForm.version = pack.version || 1;
        packForm.is_active = pack.is_active;
        packForm.content = pack.content || '';
        packForm.clearErrors();
        showPackModal = true;
    }

    function closePackModal() {
        showPackModal = false;
    }

    function submitPack() {
        if (packModalMode === 'create') {
            packForm.post(`/knowledge-packs/sectors/${activeSector.id}/packs`, {
                preserveScroll: true,
                onSuccess: () => {
                    closePackModal();
                },
            });
        } else {
            packForm.put(`/knowledge-packs/packs/${editingPackId}`, {
                preserveScroll: true,
                onSuccess: () => {
                    closePackModal();
                },
            });
        }
    }

    function deletePack(pack) {
        if (confirm(`Hapus knowledge pack "${pack.title}"?`)) {
            router.delete(`/knowledge-packs/packs/${pack.id}`, {
                preserveScroll: true,
            });
        }
    }

    function togglePack(pack) {
        router.patch(`/knowledge-packs/packs/${pack.id}/toggle`, {}, {
            preserveScroll: true,
        });
    }

    // Quick Templates
    const templates = {
        dimensi: `### Dimensi Hasil
- **Akses**: Pemerataan dan keterjangkauan penerima manfaat.
- **Mutu**: Kualitas standar dan kepuasan pelayanan.
- **Relevansi**: Manfaat langsung terhadap kebutuhan riil sasaran.
- **Tata Kelola**: Efisiensi sumber daya dan kepatuhan akuntabilitas.`,

        indikator: `### Indikator Utama
- **Nama Indikator**: [Nama Indikator Kinerja]
  - Definisi Operasional: [Penjelasan formula atau makna capaian]
  - Satuan: [mis. %, Skor, Indeks, Satuan Kerja]
  - Polaritas: Naik (Makin tinggi makin baik)
  - Sumber Data: [Unit/BPS/Laporan Resmi]`,

        regulasi: `### Regulasi Acuan
- **Undang-Undang / Peraturan Pemerintah**: UU No. ... / PP No. ...
- **Peraturan Menteri Terkait**: Permen No. ... Pedoman Penyelenggaraan ...
- **Standar Pelayanan Minimal (SPM)**: SPM Bidang ...`,
    };

    function insertTemplate(type) {
        const textToInsert = templates[type];
        if (!textToInsert) return;

        if (!packForm.content || packForm.content.trim() === '') {
            packForm.content = textToInsert;
        } else {
            packForm.content = packForm.content.trimEnd() + '\n\n' + textToInsert;
        }
    }

    function formatDate(dateStr) {
        if (!dateStr) return '—';
        try {
            const d = new Date(dateStr);
            return d.toLocaleDateString('id-ID', {
                year: 'numeric',
                month: 'short',
                day: 'numeric',
            });
        } catch {
            return dateStr;
        }
    }
</script>

<Layout>
    <div class="space-y-6">
        <!-- Page Header -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-2 border-b border-gray-200">
            <div>
                <h2 class="text-2xl font-bold text-gray-900 tracking-tight">Manajemen Knowledge Pack Sektor</h2>
                <p class="text-sm text-gray-500 mt-1">
                    Kurasi referensi dimensi hasil, indikator acuan, dan regulasi pendukung untuk perjenjangan kinerja instansi.
                </p>
            </div>
            <div class="flex items-center gap-2">
                {#if can_manage}
                    <button
                        type="button"
                        onclick={openCreateSectorModal}
                        class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg bg-indigo-600 text-white text-sm font-semibold hover:bg-indigo-700 shadow-sm transition-colors"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        Tambah Sektor
                    </button>
                {:else}
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-amber-50 text-amber-700 border border-amber-200">
                        Mode Baca Saja ({user_role})
                    </span>
                {/if}
            </div>
        </div>

        {#if flash?.success}
            <div class="rounded-md bg-emerald-50 p-4 border border-emerald-200 text-emerald-800 text-sm flex items-center justify-between">
                <span>{flash.success}</span>
            </div>
        {/if}

        <!-- Master-Detail Panel Layout -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
            <!-- Left Panel: Daftar Sektor (4 cols) -->
            <div class="lg:col-span-4 bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden flex flex-col">
                <!-- Search & Header -->
                <div class="p-4 border-b border-gray-200 bg-gray-50/50 space-y-3">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <h3 class="font-semibold text-gray-900 text-sm">Daftar Sektor</h3>
                            <span class="text-xs text-gray-500 bg-gray-200/80 px-2 py-0.5 rounded-full font-medium">
                                {sectors.length} Sektor
                            </span>
                        </div>
                        {#if can_manage}
                            <button
                                type="button"
                                onclick={openCreateSectorModal}
                                class="inline-flex items-center gap-1 px-2.5 py-1 rounded-md bg-indigo-600 text-white text-xs font-semibold hover:bg-indigo-700 shadow-xs transition-colors"
                            >
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                </svg>
                                Tambah Sektor
                            </button>
                        {/if}
                    </div>
                    <div class="relative">
                        <input
                            type="text"
                            aria-label="Cari sektor"
                            bind:value={searchQuery}
                            placeholder="Cari sektor..."
                            class="w-full pl-9 pr-3 py-2 text-sm rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 bg-white"
                        />
                        <svg class="w-4 h-4 text-gray-400 absolute left-3 top-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </div>
                </div>

                <!-- Sector List -->
                <div class="divide-y divide-gray-100 max-h-[600px] overflow-y-auto">
                    {#if filteredSectors.length === 0}
                        <div class="p-8 text-center text-sm text-gray-500">
                            Tidak ada sektor ditemukan.
                        </div>
                    {:else}
                        {#each filteredSectors as sector (sector.id)}
                            <button
                                type="button"
                                onclick={() => selectSector(sector.id)}
                                class={`w-full text-left p-4 transition-colors flex flex-col gap-1.5 hover:bg-indigo-50/50 ${
                                    activeSector?.id === sector.id
                                        ? 'bg-indigo-50/80 border-l-4 border-indigo-600 pl-3.5'
                                        : ''
                                }`}
                            >
                                <div class="flex items-center justify-between gap-2">
                                    <span class="font-semibold text-sm text-gray-900 line-clamp-1">
                                        {sector.name}
                                    </span>
                                    {#if sector.is_global}
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold bg-gray-100 text-gray-700 border border-gray-300">
                                            Global
                                        </span>
                                    {:else}
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold bg-purple-50 text-purple-700 border border-purple-200">
                                            Instansi
                                        </span>
                                    {/if}
                                </div>
                                {#if sector.description}
                                    <p class="text-xs text-gray-500 line-clamp-2">
                                        {sector.description}
                                    </p>
                                {/if}
                                <div class="flex items-center justify-between text-xs text-gray-400 mt-1">
                                    <span class="font-mono text-[11px]">{sector.slug || '—'}</span>
                                    <span class="inline-flex items-center gap-1 font-medium text-gray-600">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                                        </svg>
                                        {sector.knowledge_packs_count} pack
                                    </span>
                                </div>
                            </button>
                        {/each}
                    {/if}
                </div>
            </div>

            <!-- Right Panel: Detail Sektor & Knowledge Packs (8 cols) -->
            <div class="lg:col-span-8 space-y-6">
                {#if !activeSector}
                    <div class="bg-white rounded-xl border border-gray-200 p-12 text-center text-gray-500">
                        Pilih sektor di panel kiri untuk melihat knowledge pack terkait.
                    </div>
                {:else}
                    <!-- Sector Details Card -->
                    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 space-y-4">
                        <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
                            <div>
                                <div class="flex items-center gap-2.5">
                                    <h3 class="text-xl font-bold text-gray-900">{activeSector.name}</h3>
                                    {#if activeSector.is_global}
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-gray-100 text-gray-700 border border-gray-300">
                                            Referensi Global
                                        </span>
                                    {:else}
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-purple-50 text-purple-700 border border-purple-200">
                                            Sektor Milik Instansi
                                        </span>
                                    {/if}
                                </div>
                                <p class="text-xs font-mono text-gray-400 mt-1">slug: {activeSector.slug || '—'}</p>
                            </div>

                            {#if activeSector.can_edit}
                                <div class="flex items-center gap-2 shrink-0">
                                    <button
                                        type="button"
                                        onclick={() => openEditSectorModal(activeSector)}
                                        class="px-3 py-1.5 rounded-lg border border-gray-300 text-gray-700 text-xs font-semibold hover:bg-gray-50 transition-colors"
                                    >
                                        Edit Sektor
                                    </button>
                                    <button
                                        type="button"
                                        onclick={() => deleteSector(activeSector)}
                                        class="px-3 py-1.5 rounded-lg border border-rose-200 text-rose-600 text-xs font-semibold hover:bg-rose-50 transition-colors"
                                    >
                                        Hapus
                                    </button>
                                </div>
                            {/if}
                        </div>

                        {#if activeSector.description}
                            <p class="text-sm text-gray-600 bg-gray-50 p-3 rounded-lg border border-gray-100">
                                {activeSector.description}
                            </p>
                        {/if}

                        {#if activeSector.is_global}
                            <div class="flex items-center gap-2 p-3 rounded-lg bg-blue-50/70 border border-blue-100 text-xs text-blue-800">
                                <svg class="w-4 h-4 text-blue-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <span>
                                    Sektor ini berstatus <strong>Global</strong> (bawaan sistem) dan dapat digunakan oleh seluruh instansi sebagai referensi terkurasi. Modifikasi dan penambahan knowledge pack pada sektor ini hanya dapat dilakukan di tingkat sistem.
                                </span>
                            </div>
                        {/if}
                    </div>

                    <!-- Knowledge Packs Section -->
                    <div class="space-y-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <h4 class="text-lg font-bold text-gray-900">Knowledge Pack</h4>
                                <p class="text-xs text-gray-500">Daftar kartu kurasi pengetahuan untuk sektor ini.</p>
                            </div>
                            {#if activeSector.can_edit}
                                <button
                                    type="button"
                                    onclick={openCreatePackModal}
                                    class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-lg bg-indigo-600 text-white text-xs font-semibold hover:bg-indigo-700 shadow-sm transition-colors"
                                >
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                    </svg>
                                    Tambah Knowledge Pack
                                </button>
                            {/if}
                        </div>

                        {#if activeSector.knowledge_packs.length === 0}
                            <div class="bg-white rounded-xl border border-gray-200 p-12 text-center">
                                <svg class="w-12 h-12 text-gray-300 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                                </svg>
                                <p class="text-sm font-medium text-gray-700">Belum ada Knowledge Pack</p>
                                <p class="text-xs text-gray-500 mt-1">
                                    {activeSector.can_edit ? 'Klik tombol "Tambah Knowledge Pack" untuk menambahkan kurasi konten.' : 'Sektor ini belum memiliki item knowledge pack.'}
                                </p>
                            </div>
                        {:else}
                            <div class="space-y-4">
                                {#each activeSector.knowledge_packs as pack (pack.id)}
                                    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 space-y-3 transition-shadow hover:shadow-md">
                                        <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3 pb-3 border-b border-gray-100">
                                            <div>
                                                <div class="flex flex-wrap items-center gap-2">
                                                    <h5 class="font-bold text-gray-900 text-base">{pack.title}</h5>
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-medium bg-gray-100 text-gray-700">
                                                        v{pack.version}
                                                    </span>
                                                    {#if pack.is_active}
                                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                                            Aktif
                                                        </span>
                                                    {:else}
                                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold bg-gray-100 text-gray-500 border border-gray-300">
                                                            Nonaktif
                                                        </span>
                                                    {/if}
                                                </div>
                                                {#if pack.source}
                                                    <p class="text-xs text-gray-500 mt-1 flex items-center gap-1">
                                                        <svg class="w-3.5 h-3.5 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                                                        </svg>
                                                        Sumber: {pack.source}
                                                    </p>
                                                {/if}
                                            </div>

                                            <div class="flex items-center gap-2 shrink-0">
                                                {#if pack.can_edit}
                                                    <!-- Toggle Active Switch -->
                                                    <button
                                                        type="button"
                                                        onclick={() => togglePack(pack)}
                                                        title={pack.is_active ? 'Nonaktifkan knowledge pack' : 'Aktifkan knowledge pack'}
                                                        class={`inline-flex items-center px-2.5 py-1 rounded-md text-xs font-semibold transition-colors ${
                                                            pack.is_active
                                                                ? 'bg-emerald-50 text-emerald-700 hover:bg-emerald-100 border border-emerald-200'
                                                                : 'bg-gray-100 text-gray-700 hover:bg-gray-200 border border-gray-300'
                                                        }`}
                                                    >
                                                        {pack.is_active ? 'Matikan' : 'Aktifkan'}
                                                    </button>

                                                    <button
                                                        type="button"
                                                        onclick={() => openEditPackModal(pack)}
                                                        class="p-1.5 rounded-lg border border-gray-300 text-gray-600 hover:text-gray-900 hover:bg-gray-50 transition-colors"
                                                        title="Edit Knowledge Pack"
                                                    >
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                                        </svg>
                                                    </button>
                                                    <button
                                                        type="button"
                                                        onclick={() => deletePack(pack)}
                                                        class="p-1.5 rounded-lg border border-rose-200 text-rose-600 hover:bg-rose-50 transition-colors"
                                                        title="Hapus Knowledge Pack"
                                                    >
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                        </svg>
                                                    </button>
                                                {/if}
                                            </div>
                                        </div>

                                        <!-- Content Body -->
                                        <div class="bg-gray-50 rounded-lg p-4 font-mono text-xs text-gray-800 whitespace-pre-wrap leading-relaxed border border-gray-100 max-h-60 overflow-y-auto">
                                            {pack.content}
                                        </div>

                                        <div class="flex items-center justify-between text-[11px] text-gray-400 pt-1">
                                            <span>Terakhir diperbarui: {formatDate(pack.updated_at)}</span>
                                            {#if !pack.is_active}
                                                <span class="text-amber-600 font-medium">Tidak dimasukkan dalam konteks AI</span>
                                            {/if}
                                        </div>
                                    </div>
                                {/each}
                            </div>
                        {/if}
                    </div>
                {/if}
            </div>
        </div>
    </div>

    <!-- Modal Form Sektor -->
    {#if showSectorModal}
        <div class="fixed inset-0 z-50 overflow-y-auto bg-gray-900/60 backdrop-blur-xs flex items-center justify-center p-4">
            <div class="bg-white rounded-xl shadow-xl border border-gray-200 max-w-lg w-full p-6 space-y-4 animate-in fade-in">
                <div class="flex items-center justify-between pb-3 border-b border-gray-100">
                    <h3 class="text-lg font-bold text-gray-900">
                        {sectorModalMode === 'create' ? 'Tambah Sektor Baru' : 'Edit Sektor'}
                    </h3>
                    <button
                        type="button"
                        aria-label="Tutup modal sektor"
                        onclick={closeSectorModal}
                        class="text-gray-400 hover:text-gray-600"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <form onsubmit={(e) => { e.preventDefault(); submitSector(); }} class="space-y-4">
                    <div>
                        <label for="sector-name" class="block text-sm font-medium text-gray-700">Nama Sektor</label>
                        <input
                            id="sector-name"
                            type="text"
                            bind:value={sectorForm.name}
                            oninput={onSectorNameChange}
                            placeholder="mis. Kelautan dan Perikanan"
                            class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                        />
                        {#if sectorForm.errors.name}
                            <p class="mt-1 text-xs text-rose-600">{sectorForm.errors.name}</p>
                        {/if}
                    </div>

                    <div>
                        <div class="flex items-center justify-between">
                            <label for="sector-slug" class="block text-sm font-medium text-gray-700">Slug (URL-Friendly)</label>
                            <label class="text-xs text-indigo-600 flex items-center gap-1 cursor-pointer">
                                <input
                                    type="checkbox"
                                    bind:checked={isSlugManual}
                                    class="rounded text-indigo-600 focus:ring-indigo-500 text-xs"
                                />
                                Edit manual
                            </label>
                        </div>
                        <input
                            id="sector-slug"
                            type="text"
                            bind:value={sectorForm.slug}
                            disabled={!isSlugManual}
                            placeholder="mis. kelautan-perikanan"
                            class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm bg-gray-50 disabled:bg-gray-100 disabled:text-gray-500 font-mono text-xs focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                        />
                        {#if sectorForm.errors.slug}
                            <p class="mt-1 text-xs text-rose-600">{sectorForm.errors.slug}</p>
                        {/if}
                    </div>

                    <div>
                        <label for="sector-desc" class="block text-sm font-medium text-gray-700">Deskripsi Sektor</label>
                        <textarea
                            id="sector-desc"
                            rows="3"
                            bind:value={sectorForm.description}
                            placeholder="Jelaskan ruang lingkup, fokus urusan, atau batasan sektor..."
                            class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                        ></textarea>
                        {#if sectorForm.errors.description}
                            <p class="mt-1 text-xs text-rose-600">{sectorForm.errors.description}</p>
                        {/if}
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-3 border-t border-gray-100">
                        <button
                            type="button"
                            onclick={closeSectorModal}
                            class="px-4 py-2 rounded-lg border border-gray-300 text-gray-700 text-sm font-medium hover:bg-gray-50"
                        >
                            Batal
                        </button>
                        <button
                            type="submit"
                            disabled={sectorForm.processing}
                            class="px-4 py-2 rounded-lg bg-indigo-600 text-white text-sm font-semibold hover:bg-indigo-700 disabled:opacity-50"
                        >
                            {sectorForm.processing ? 'Menyimpan…' : (sectorModalMode === 'create' ? 'Buat Sektor' : 'Simpan Perubahan')}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    {/if}

    <!-- Modal Form Knowledge Pack -->
    {#if showPackModal}
        <div class="fixed inset-0 z-50 overflow-y-auto bg-gray-900/60 backdrop-blur-xs flex items-center justify-center p-4">
            <div class="bg-white rounded-xl shadow-xl border border-gray-200 max-w-2xl w-full p-6 space-y-4 animate-in fade-in">
                <div class="flex items-center justify-between pb-3 border-b border-gray-100">
                    <div>
                        <h3 class="text-lg font-bold text-gray-900">
                            {packModalMode === 'create' ? 'Tambah Knowledge Pack' : 'Edit Knowledge Pack'}
                        </h3>
                        <p class="text-xs text-gray-500">Sektor: <span class="font-semibold text-gray-700">{activeSector?.name}</span></p>
                    </div>
                    <button
                        type="button"
                        aria-label="Tutup modal knowledge pack"
                        onclick={closePackModal}
                        class="text-gray-400 hover:text-gray-600"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <form onsubmit={(e) => { e.preventDefault(); submitPack(); }} class="space-y-4">
                    <div>
                        <label for="pack-title" class="block text-sm font-medium text-gray-700">Judul Knowledge Pack</label>
                        <input
                            id="pack-title"
                            type="text"
                            bind:value={packForm.title}
                            placeholder="mis. Dimensi Hasil Ketahanan Pangan"
                            class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                        />
                        {#if packForm.errors.title}
                            <p class="mt-1 text-xs text-rose-600">{packForm.errors.title}</p>
                        {/if}
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div class="sm:col-span-2">
                            <label for="pack-source" class="block text-sm font-medium text-gray-700">Sumber / Dokumen Acuan (Opsional)</label>
                            <input
                                id="pack-source"
                                type="text"
                                bind:value={packForm.source}
                                placeholder="mis. Renstra Kementan / Permen PAN-RB No. 89/2021"
                                class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                            />
                            {#if packForm.errors.source}
                                <p class="mt-1 text-xs text-rose-600">{packForm.errors.source}</p>
                            {/if}
                        </div>
                        <div>
                            <label for="pack-version" class="block text-sm font-medium text-gray-700">Versi</label>
                            <input
                                id="pack-version"
                                type="number"
                                min="1"
                                bind:value={packForm.version}
                                class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                            />
                            {#if packForm.errors.version}
                                <p class="mt-1 text-xs text-rose-600">{packForm.errors.version}</p>
                            {/if}
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        <input
                            id="pack-active"
                            type="checkbox"
                            bind:checked={packForm.is_active}
                            class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                        />
                        <label for="pack-active" class="text-sm font-medium text-gray-700 cursor-pointer">
                            Status Aktif (Gunakan sebagai referensi rekomendasi AI)
                        </label>
                    </div>

                    <!-- Markdown Content with Quick Template Buttons -->
                    <div class="space-y-1.5">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <label for="pack-content" class="block text-sm font-medium text-gray-700">
                                Konten Kurasi (Format Markdown)
                            </label>
                            <!-- Quick Template Buttons -->
                            <div class="flex flex-wrap items-center gap-1.5">
                                <span class="text-xs text-gray-400 font-medium mr-1">Template Cepat:</span>
                                <button
                                    type="button"
                                    onclick={() => insertTemplate('dimensi')}
                                    class="inline-flex items-center px-2 py-1 rounded bg-indigo-50 text-indigo-700 hover:bg-indigo-100 border border-indigo-200 text-xs font-semibold transition-colors"
                                >
                                    + Dimensi Hasil
                                </button>
                                <button
                                    type="button"
                                    onclick={() => insertTemplate('indikator')}
                                    class="inline-flex items-center px-2 py-1 rounded bg-emerald-50 text-emerald-700 hover:bg-emerald-100 border border-emerald-200 text-xs font-semibold transition-colors"
                                >
                                    + Indikator Utama
                                </button>
                                <button
                                    type="button"
                                    onclick={() => insertTemplate('regulasi')}
                                    class="inline-flex items-center px-2 py-1 rounded bg-amber-50 text-amber-700 hover:bg-amber-100 border border-amber-200 text-xs font-semibold transition-colors"
                                >
                                    + Regulasi Acuan
                                </button>
                            </div>
                        </div>

                        <textarea
                            id="pack-content"
                            rows="10"
                            bind:value={packForm.content}
                            placeholder="Tuliskan butir-butir dimensi hasil, indikator acuan, atau kutipan regulasi di sini..."
                            class="mt-1 w-full rounded-lg border border-gray-300 p-3 text-sm font-mono focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 leading-relaxed"
                        ></textarea>
                        {#if packForm.errors.content}
                            <p class="mt-1 text-xs text-rose-600">{packForm.errors.content}</p>
                        {/if}
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-3 border-t border-gray-100">
                        <button
                            type="button"
                            onclick={closePackModal}
                            class="px-4 py-2 rounded-lg border border-gray-300 text-gray-700 text-sm font-medium hover:bg-gray-50"
                        >
                            Batal
                        </button>
                        <button
                            type="submit"
                            disabled={packForm.processing}
                            class="px-4 py-2 rounded-lg bg-indigo-600 text-white text-sm font-semibold hover:bg-indigo-700 disabled:opacity-50"
                        >
                            {packForm.processing ? 'Menyimpan…' : (packModalMode === 'create' ? 'Simpan Knowledge Pack' : 'Simpan Perubahan')}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    {/if}
</Layout>
