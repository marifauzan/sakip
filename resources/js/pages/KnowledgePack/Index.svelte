<script>
    import { useForm, router } from '@inertiajs/svelte';
    import Layout from '../Layout.svelte';
    import {
        BookOpen,
        Plus,
        Search,
        Building2,
        Globe,
        FileText,
        CheckCircle2,
        Trash2,
        Edit3,
        X,
        Sparkles,
        Info,
        Tag,
        Calendar,
        Check,
        Power,
    } from '@lucide/svelte';

    let { sectors = [], selected_sector_id = null, can_manage = false, user_role = '', flash = {} } = $props();

    let userSelectedId = $state(null);
    let searchQuery = $state('');

    // Keep userSelectedId in sync with server-provided selected_sector_id
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
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-4 border-b border-slate-200">
            <div>
                <div class="flex items-center gap-2 mb-1">
                    <span class="inline-flex items-center gap-1.5 text-xs font-semibold px-2.5 py-0.5 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200">
                        <BookOpen class="w-3.5 h-3.5" />
                        Basis Pengetahuan SAKIP
                    </span>
                    <span class="text-xs text-slate-400 font-mono">v1.0</span>
                </div>
                <h2 class="text-2xl font-bold text-slate-900 tracking-tight">Knowledge Pack Sektor</h2>
                <p class="text-xs sm:text-sm text-slate-500 mt-1">
                    Kurasi acuan dimensi hasil, indikator acuan, dan regulasi sektoral untuk asistensi AI perjenjangan kinerja.
                </p>
            </div>
            <div class="flex items-center gap-3">
                {#if can_manage}
                    <button
                        type="button"
                        onclick={openCreateSectorModal}
                        class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-lg bg-emerald-600 text-white text-xs font-semibold hover:bg-emerald-500 shadow-2xs transition-all active:scale-[0.99]"
                    >
                        <Plus class="w-4 h-4" />
                        Tambah Sektor Baru
                    </button>
                {:else}
                    <span class="inline-flex items-center px-3 py-1.5 rounded-lg text-xs font-medium bg-slate-100 text-slate-600 border border-slate-200">
                        Mode Baca Saja ({user_role})
                    </span>
                {/if}
            </div>
        </div>

        {#if flash?.success}
            <div class="rounded-lg bg-emerald-50 p-4 border border-emerald-200 text-emerald-800 text-sm flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <CheckCircle2 class="w-4 h-4 text-emerald-600 shrink-0" />
                    <span>{flash.success}</span>
                </div>
            </div>
        {/if}

        <!-- Master-Detail Panel Layout -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
            <!-- Left Panel: Daftar Sektor (4 cols) -->
            <div class="lg:col-span-4 bg-white rounded-xl border border-slate-200 shadow-2xs overflow-hidden flex flex-col">
                <!-- Search & Header -->
                <div class="p-4 border-b border-slate-200 bg-slate-50/50 space-y-3">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <h3 class="font-semibold text-slate-900 text-xs uppercase tracking-wider">Daftar Sektor</h3>
                            <span class="text-[11px] text-slate-600 bg-slate-200/70 px-2 py-0.5 rounded-full font-mono font-medium">
                                {sectors.length}
                            </span>
                        </div>
                    </div>
                    <div class="relative">
                        <input
                            type="text"
                            aria-label="Cari sektor"
                            bind:value={searchQuery}
                            placeholder="Cari nama sektor..."
                            class="w-full pl-9 pr-3 py-2 text-xs rounded-lg border border-slate-200 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 bg-white placeholder-slate-400"
                        />
                        <Search class="w-3.5 h-3.5 text-slate-400 absolute left-3 top-2.5" />
                    </div>
                </div>

                <!-- Sector List -->
                <div class="divide-y divide-slate-100 max-h-[620px] overflow-y-auto">
                    {#if filteredSectors.length === 0}
                        <div class="p-8 text-center text-xs text-slate-400">
                            Tidak ada sektor ditemukan.
                        </div>
                    {:else}
                        {#each filteredSectors as sector (sector.id)}
                            <button
                                type="button"
                                onclick={() => selectSector(sector.id)}
                                class={`w-full text-left p-4 transition-all flex flex-col gap-1.5 hover:bg-slate-50 ${
                                    activeSector?.id === sector.id
                                        ? 'bg-emerald-50/60 border-l-3 border-emerald-600 pl-3.5'
                                        : ''
                                }`}
                            >
                                <div class="flex items-center justify-between gap-2">
                                    <div class="flex items-center gap-2 min-w-0">
                                        {#if sector.is_global}
                                            <Globe class={`w-4 h-4 shrink-0 ${activeSector?.id === sector.id ? 'text-emerald-700' : 'text-slate-400'}`} />
                                        {:else}
                                            <Building2 class={`w-4 h-4 shrink-0 ${activeSector?.id === sector.id ? 'text-emerald-700' : 'text-slate-400'}`} />
                                        {/if}
                                        <span class={`font-semibold text-xs line-clamp-1 ${
                                            activeSector?.id === sector.id ? 'text-emerald-950' : 'text-slate-800'
                                        }`}>
                                            {sector.name}
                                        </span>
                                    </div>
                                    {#if sector.is_global}
                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold bg-slate-100 text-slate-600 border border-slate-200 shrink-0">
                                            Global
                                        </span>
                                    {:else}
                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200 shrink-0">
                                            Instansi
                                        </span>
                                    {/if}
                                </div>
                                {#if sector.description}
                                    <p class="text-[11px] text-slate-500 line-clamp-2 leading-relaxed">
                                        {sector.description}
                                    </p>
                                {/if}
                                <div class="flex items-center justify-between text-[11px] text-slate-400 mt-1 pt-1 border-t border-slate-100/60">
                                    <span class="font-mono text-[10px] text-slate-400">{sector.slug || '—'}</span>
                                    <span class="inline-flex items-center gap-1 font-mono text-slate-600 text-[11px]">
                                        <BookOpen class="w-3 h-3 text-slate-400" />
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
                    <div class="bg-white rounded-xl border border-slate-200 p-12 text-center text-slate-400">
                        Pilih sektor di panel kiri untuk melihat knowledge pack terkait.
                    </div>
                {:else}
                    <!-- Sector Details Card -->
                    <div class="bg-white rounded-xl border border-slate-200 shadow-2xs p-5 space-y-4">
                        <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
                            <div>
                                <div class="flex items-center gap-2.5">
                                    <h3 class="text-lg font-bold text-slate-900">{activeSector.name}</h3>
                                    {#if activeSector.is_global}
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold bg-slate-100 text-slate-700 border border-slate-200">
                                            <Globe class="w-3 h-3 text-slate-500" />
                                            Referensi Global
                                        </span>
                                    {:else}
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                            <Building2 class="w-3 h-3 text-emerald-600" />
                                            Sektor Milik Instansi
                                        </span>
                                    {/if}
                                </div>
                                <p class="text-xs font-mono text-slate-400 mt-1">slug: {activeSector.slug || '—'}</p>
                            </div>

                            {#if activeSector.can_edit}
                                <div class="flex items-center gap-2 shrink-0">
                                    <button
                                        type="button"
                                        onclick={() => openEditSectorModal(activeSector)}
                                        class="px-3 py-1.5 rounded-lg border border-slate-200 bg-white text-slate-700 text-xs font-medium hover:bg-slate-50 transition-all flex items-center gap-1.5"
                                    >
                                        <Edit3 class="w-3.5 h-3.5 text-slate-500" />
                                        Edit Sektor
                                    </button>
                                    <button
                                        type="button"
                                        onclick={() => deleteSector(activeSector)}
                                        class="px-3 py-1.5 rounded-lg border border-rose-200 text-rose-600 text-xs font-medium hover:bg-rose-50 transition-all flex items-center gap-1.5"
                                    >
                                        <Trash2 class="w-3.5 h-3.5 text-rose-500" />
                                        Hapus
                                    </button>
                                </div>
                            {/if}
                        </div>

                        {#if activeSector.description}
                            <p class="text-xs text-slate-600 bg-slate-50 p-3.5 rounded-lg border border-slate-100 leading-relaxed">
                                {activeSector.description}
                            </p>
                        {/if}

                        {#if activeSector.is_global}
                            <div class="flex items-start gap-2.5 p-3 rounded-lg bg-slate-50 border border-slate-200 text-xs text-slate-600">
                                <Info class="w-4 h-4 text-emerald-600 shrink-0 mt-0.5" />
                                <span>
                                    Sektor ini berstatus <strong class="text-slate-800">Global</strong> (bawaan sistem nasional) dan dapat digunakan oleh seluruh instansi sebagai referensi terstandar. Modifikasi pada sektor ini dikunci di tingkat sistem pusat.
                                </span>
                            </div>
                        {/if}
                    </div>

                    <!-- Knowledge Packs Section -->
                    <div class="space-y-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <h4 class="text-base font-bold text-slate-900">Knowledge Pack Terdaftar</h4>
                                <p class="text-xs text-slate-500">Kurasi acuan domain spesifik yang dijadikan dasar grounding asistensi AI.</p>
                            </div>
                            {#if activeSector.can_edit}
                                <button
                                    type="button"
                                    onclick={openCreatePackModal}
                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-emerald-600 text-white text-xs font-semibold hover:bg-emerald-500 shadow-2xs transition-all active:scale-[0.99]"
                                >
                                    <Plus class="w-3.5 h-3.5" />
                                    Tambah Pack
                                </button>
                            {/if}
                        </div>

                        {#if activeSector.knowledge_packs.length === 0}
                            <div class="bg-white rounded-xl border border-slate-200 p-12 text-center">
                                <BookOpen class="w-10 h-10 text-slate-300 mx-auto mb-3" />
                                <p class="text-sm font-semibold text-slate-800">Belum ada Knowledge Pack</p>
                                <p class="text-xs text-slate-500 mt-1 max-w-sm mx-auto">
                                    {activeSector.can_edit ? 'Klik tombol "Tambah Pack" untuk menambahkan pedoman dimensi hasil, indikator acuan, atau regulasi.' : 'Sektor ini belum memiliki item knowledge pack terkurasi.'}
                                </p>
                                {#if activeSector.can_edit}
                                    <button
                                        type="button"
                                        onclick={openCreatePackModal}
                                        class="mt-4 inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-lg bg-emerald-600 text-white text-xs font-semibold hover:bg-emerald-500 shadow-2xs transition-all"
                                    >
                                        <Plus class="w-3.5 h-3.5" />
                                        Mulai Buat Pack Baru
                                    </button>
                                {/if}
                            </div>
                        {:else}
                            <div class="space-y-4">
                                {#each activeSector.knowledge_packs as pack (pack.id)}
                                    <div class="bg-white rounded-xl border border-slate-200 shadow-2xs p-5 space-y-3 transition-all hover:border-slate-300">
                                        <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3 pb-3 border-b border-slate-100">
                                            <div class="space-y-1">
                                                <div class="flex flex-wrap items-center gap-2">
                                                    <h5 class="font-bold text-slate-900 text-sm sm:text-base">{pack.title}</h5>
                                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-mono font-medium bg-slate-100 text-slate-600 border border-slate-200">
                                                        v{pack.version}
                                                    </span>
                                                    {#if pack.is_active}
                                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[11px] font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                                            <Check class="w-3 h-3" />
                                                            Aktif Grounding
                                                        </span>
                                                    {:else}
                                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold bg-slate-100 text-slate-500 border border-slate-200">
                                                            Nonaktif
                                                        </span>
                                                    {/if}
                                                </div>
                                                {#if pack.source}
                                                    <p class="text-xs text-slate-500 flex items-center gap-1.5">
                                                        <BookOpen class="w-3.5 h-3.5 text-slate-400 shrink-0" />
                                                        <span>Sumber: <strong class="text-slate-700 font-medium">{pack.source}</strong></span>
                                                    </p>
                                                {/if}
                                            </div>

                                            <div class="flex items-center gap-2 shrink-0">
                                                {#if pack.can_edit}
                                                    <!-- Toggle Active Switch -->
                                                    <button
                                                        type="button"
                                                        onclick={() => togglePack(pack)}
                                                        title={pack.is_active ? 'Nonaktifkan dari rekomendasi AI' : 'Aktifkan untuk rekomendasi AI'}
                                                        class={`inline-flex items-center gap-1 px-2.5 py-1 rounded-md text-xs font-medium transition-all ${
                                                            pack.is_active
                                                                ? 'bg-emerald-50 text-emerald-700 hover:bg-emerald-100 border border-emerald-200'
                                                                : 'bg-slate-100 text-slate-700 hover:bg-slate-200 border border-slate-200'
                                                        }`}
                                                    >
                                                        <Power class="w-3 h-3" />
                                                        {pack.is_active ? 'Nonaktifkan' : 'Aktifkan'}
                                                    </button>

                                                    <button
                                                        type="button"
                                                        onclick={() => openEditPackModal(pack)}
                                                        class="p-1.5 rounded-lg border border-slate-200 text-slate-600 hover:text-slate-900 hover:bg-slate-50 transition-all"
                                                        title="Edit Knowledge Pack"
                                                    >
                                                        <Edit3 class="w-3.5 h-3.5" />
                                                    </button>
                                                    <button
                                                        type="button"
                                                        onclick={() => deletePack(pack)}
                                                        class="p-1.5 rounded-lg border border-rose-200 text-rose-600 hover:bg-rose-50 transition-all"
                                                        title="Hapus Knowledge Pack"
                                                    >
                                                        <Trash2 class="w-3.5 h-3.5" />
                                                    </button>
                                                {/if}
                                            </div>
                                        </div>

                                        <!-- Content Body -->
                                        <div class="bg-slate-50/70 rounded-lg p-3.5 font-mono text-xs text-slate-800 whitespace-pre-wrap leading-relaxed border border-slate-100 max-h-56 overflow-y-auto">
                                            {pack.content}
                                        </div>

                                        <div class="flex items-center justify-between text-[11px] text-slate-400 pt-1">
                                            <span class="flex items-center gap-1 font-mono">
                                                <Calendar class="w-3 h-3 text-slate-400" />
                                                Terakhir diperbarui: {formatDate(pack.updated_at)}
                                            </span>
                                            {#if !pack.is_active}
                                                <span class="text-amber-600 font-medium text-[11px]">
                                                    Dikecualikan dari prompt AI
                                                </span>
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
        <div class="fixed inset-0 z-50 overflow-y-auto bg-slate-950/40 backdrop-blur-xs flex items-center justify-center p-4">
            <div class="bg-white rounded-xl shadow-xl border border-slate-200 max-w-lg w-full p-6 space-y-4 animate-in fade-in duration-150">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                    <div class="flex items-center gap-2">
                        <Building2 class="w-5 h-5 text-emerald-600" />
                        <h3 class="text-base font-bold text-slate-900">
                            {sectorModalMode === 'create' ? 'Tambah Sektor Baru' : 'Edit Sektor'}
                        </h3>
                    </div>
                    <button
                        type="button"
                        aria-label="Tutup modal sektor"
                        onclick={closeSectorModal}
                        class="text-slate-400 hover:text-slate-600 rounded-lg p-1 hover:bg-slate-100 transition-colors"
                    >
                        <X class="w-4 h-4" />
                    </button>
                </div>

                <form onsubmit={(e) => { e.preventDefault(); submitSector(); }} class="space-y-4">
                    <div>
                        <label for="sector-name" class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">Nama Sektor</label>
                        <input
                            id="sector-name"
                            type="text"
                            bind:value={sectorForm.name}
                            oninput={onSectorNameChange}
                            placeholder="mis. Kelautan dan Perikanan"
                            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-xs focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 bg-white"
                        />
                        {#if sectorForm.errors.name}
                            <p class="mt-1 text-xs text-rose-600">{sectorForm.errors.name}</p>
                        {/if}
                    </div>

                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <label for="sector-slug" class="block text-xs font-semibold text-slate-700 uppercase tracking-wider">Slug (URL-Friendly)</label>
                            <label class="text-[11px] text-emerald-700 flex items-center gap-1 cursor-pointer font-medium">
                                <input
                                    type="checkbox"
                                    bind:checked={isSlugManual}
                                    class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500 text-xs"
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
                            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-xs bg-slate-50 disabled:bg-slate-100 disabled:text-slate-500 font-mono focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500"
                        />
                        {#if sectorForm.errors.slug}
                            <p class="mt-1 text-xs text-rose-600">{sectorForm.errors.slug}</p>
                        {/if}
                    </div>

                    <div>
                        <label for="sector-desc" class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">Deskripsi Sektor</label>
                        <textarea
                            id="sector-desc"
                            rows="3"
                            bind:value={sectorForm.description}
                            placeholder="Jelaskan ruang lingkup urusan atau fokus tupoksi sektor..."
                            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-xs focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 bg-white leading-relaxed"
                        ></textarea>
                        {#if sectorForm.errors.description}
                            <p class="mt-1 text-xs text-rose-600">{sectorForm.errors.description}</p>
                        {/if}
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100">
                        <button
                            type="button"
                            onclick={closeSectorModal}
                            class="px-3.5 py-2 rounded-lg border border-slate-200 bg-white text-slate-700 text-xs font-medium hover:bg-slate-50 transition-all"
                        >
                            Batal
                        </button>
                        <button
                            type="submit"
                            disabled={sectorForm.processing}
                            class="px-4 py-2 rounded-lg bg-emerald-600 text-white text-xs font-semibold hover:bg-emerald-500 disabled:opacity-50 shadow-2xs transition-all"
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
        <div class="fixed inset-0 z-50 overflow-y-auto bg-slate-950/40 backdrop-blur-xs flex items-center justify-center p-4">
            <div class="bg-white rounded-xl shadow-xl border border-slate-200 max-w-2xl w-full p-6 space-y-4 animate-in fade-in duration-150">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                    <div>
                        <div class="flex items-center gap-2">
                            <BookOpen class="w-5 h-5 text-emerald-600" />
                            <h3 class="text-base font-bold text-slate-900">
                                {packModalMode === 'create' ? 'Tambah Knowledge Pack' : 'Edit Knowledge Pack'}
                            </h3>
                        </div>
                        <p class="text-xs text-slate-500 mt-0.5">Sektor Target: <strong class="text-slate-800">{activeSector?.name}</strong></p>
                    </div>
                    <button
                        type="button"
                        aria-label="Tutup modal knowledge pack"
                        onclick={closePackModal}
                        class="text-slate-400 hover:text-slate-600 rounded-lg p-1 hover:bg-slate-100 transition-colors"
                    >
                        <X class="w-4 h-4" />
                    </button>
                </div>

                <form onsubmit={(e) => { e.preventDefault(); submitPack(); }} class="space-y-4">
                    <div>
                        <label for="pack-title" class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">Judul Knowledge Pack</label>
                        <input
                            id="pack-title"
                            type="text"
                            bind:value={packForm.title}
                            placeholder="mis. Dimensi Hasil Ketahanan Pangan Nasional"
                            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-xs focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 bg-white"
                        />
                        {#if packForm.errors.title}
                            <p class="mt-1 text-xs text-rose-600">{packForm.errors.title}</p>
                        {/if}
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div class="sm:col-span-2">
                            <label for="pack-source" class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">Sumber Acuan / Regulasi (Opsional)</label>
                            <input
                                id="pack-source"
                                type="text"
                                bind:value={packForm.source}
                                placeholder="mis. Renstra Kementan / Permen PAN-RB No. 89/2021"
                                class="w-full rounded-lg border border-slate-300 px-3 py-2 text-xs focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 bg-white"
                            />
                            {#if packForm.errors.source}
                                <p class="mt-1 text-xs text-rose-600">{packForm.errors.source}</p>
                            {/if}
                        </div>
                        <div>
                            <label for="pack-version" class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">Versi</label>
                            <input
                                id="pack-version"
                                type="number"
                                min="1"
                                bind:value={packForm.version}
                                class="w-full rounded-lg border border-slate-300 px-3 py-2 text-xs font-mono focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 bg-white"
                            />
                            {#if packForm.errors.version}
                                <p class="mt-1 text-xs text-rose-600">{packForm.errors.version}</p>
                            {/if}
                        </div>
                    </div>

                    <div class="flex items-center gap-2 p-2.5 rounded-lg bg-slate-50 border border-slate-200">
                        <input
                            id="pack-active"
                            type="checkbox"
                            bind:checked={packForm.is_active}
                            class="h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500"
                        />
                        <label for="pack-active" class="text-xs font-medium text-slate-700 cursor-pointer">
                            Aktifkan sebagai konteks kurasi grounding untuk rekomendasi AI
                        </label>
                    </div>

                    <!-- Markdown Content with Quick Template Buttons -->
                    <div class="space-y-2">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <label for="pack-content" class="block text-xs font-semibold text-slate-700 uppercase tracking-wider">
                                Konten Kurasi (Format Markdown)
                            </label>
                            <!-- Quick Template Buttons -->
                            <div class="flex flex-wrap items-center gap-1.5">
                                <span class="text-[11px] text-slate-400 font-medium mr-0.5">Template Cepat:</span>
                                <button
                                    type="button"
                                    onclick={() => insertTemplate('dimensi')}
                                    class="inline-flex items-center gap-1 px-2 py-0.5 rounded border border-slate-200 bg-white text-slate-700 hover:border-emerald-300 hover:bg-emerald-50/50 hover:text-emerald-700 text-[11px] font-medium transition-all"
                                >
                                    <Sparkles class="w-3 h-3 text-emerald-600" />
                                    + Dimensi Hasil
                                </button>
                                <button
                                    type="button"
                                    onclick={() => insertTemplate('indikator')}
                                    class="inline-flex items-center gap-1 px-2 py-0.5 rounded border border-slate-200 bg-white text-slate-700 hover:border-emerald-300 hover:bg-emerald-50/50 hover:text-emerald-700 text-[11px] font-medium transition-all"
                                >
                                    <Sparkles class="w-3 h-3 text-emerald-600" />
                                    + Indikator Utama
                                </button>
                                <button
                                    type="button"
                                    onclick={() => insertTemplate('regulasi')}
                                    class="inline-flex items-center gap-1 px-2 py-0.5 rounded border border-slate-200 bg-white text-slate-700 hover:border-emerald-300 hover:bg-emerald-50/50 hover:text-emerald-700 text-[11px] font-medium transition-all"
                                >
                                    <Sparkles class="w-3 h-3 text-emerald-600" />
                                    + Regulasi Acuan
                                </button>
                            </div>
                        </div>

                        <textarea
                            id="pack-content"
                            rows="9"
                            bind:value={packForm.content}
                            placeholder="Tuliskan butir-butir acuan dimensi hasil, formulasi indikator kinerja, atau kutipan regulasi teknis..."
                            class="w-full rounded-lg border border-slate-300 p-3 text-xs font-mono focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 leading-relaxed bg-slate-50/30"
                        ></textarea>
                        {#if packForm.errors.content}
                            <p class="mt-1 text-xs text-rose-600">{packForm.errors.content}</p>
                        {/if}
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100">
                        <button
                            type="button"
                            onclick={closePackModal}
                            class="px-3.5 py-2 rounded-lg border border-slate-200 bg-white text-slate-700 text-xs font-medium hover:bg-slate-50 transition-all"
                        >
                            Batal
                        </button>
                        <button
                            type="submit"
                            disabled={packForm.processing}
                            class="px-4 py-2 rounded-lg bg-emerald-600 text-white text-xs font-semibold hover:bg-emerald-500 disabled:opacity-50 shadow-2xs transition-all"
                        >
                            {packForm.processing ? 'Menyimpan…' : (packModalMode === 'create' ? 'Simpan Knowledge Pack' : 'Simpan Perubahan')}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    {/if}
</Layout>

