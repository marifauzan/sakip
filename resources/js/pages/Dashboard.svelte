<script>
    import { Link, page } from '@inertiajs/svelte';
    import Layout from './Layout.svelte';
    import {
        GitFork,
        FileText,
        BookOpen,
        ShieldCheck,
        Sparkles,
        ArrowRight,
        Plus,
        Upload,
        Layers,
        CircleCheck,
        Building,
        User,
        Clock,
        FolderOpen
    } from '@lucide/svelte';

    let {
        auth,
        metrics = { trees_count: 0, documents_count: 0, knowledge_packs_count: 0, nodes_count: 0 },
        recentTrees = [],
        recentDocuments = []
    } = $props();

    let currentAuth = $derived(auth ?? page.props.auth);

    function formatRole(role) {
        switch (role) {
            case 'planner':
                return 'Perencana Kinerja';
            case 'reviewer':
                return 'Reviewer / Asesor';
            case 'admin':
                return 'Administrator';
            default:
                return role || 'Pengguna';
        }
    }

    function formatDocStatus(status) {
        switch (status) {
            case 'uploaded':
                return { label: 'Terunggah', class: 'bg-slate-100 text-slate-700 border-slate-200' };
            case 'processing':
                return { label: 'Memproses AI', class: 'bg-amber-50 text-amber-800 border-amber-200' };
            case 'extracted':
                return { label: 'Terekstraksi', class: 'bg-emerald-50 text-emerald-800 border-emerald-200' };
            case 'failed':
                return { label: 'Gagal Ekstrak', class: 'bg-rose-50 text-rose-800 border-rose-200' };
            default:
                return { label: status, class: 'bg-slate-100 text-slate-700 border-slate-200' };
        }
    }

    function formatTreeStatus(status) {
        switch (status) {
            case 'published':
                return { label: 'Terpublikasi', class: 'bg-emerald-50 text-emerald-800 border-emerald-200' };
            case 'reviewed':
                return { label: 'Telah Direview', class: 'bg-emerald-50 text-emerald-800 border-emerald-200' };
            case 'draft':
            default:
                return { label: 'Draft Rancangan', class: 'bg-slate-100 text-slate-700 border-slate-200' };
        }
    }

    function formatPeriod(start, end) {
        if (start && end) return `Periode: ${start} \u2013 ${end}`;
        if (start) return `Mulai: ${start}`;
        if (end) return `Hingga: ${end}`;
        return 'Periode belum ditentukan';
    }
</script>

<Layout auth={currentAuth} title="Dashboard">
    <div class="space-y-8">
        <!-- 1. Executive Console Header / Hero -->
        <div class="bg-white rounded-xl border border-slate-200/80 p-6 sm:p-7 shadow-xs">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-6">
                <div class="space-y-2">
                    <div class="flex items-center gap-2">
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                            Konsol Eksekutif
                        </span>
                        {#if currentAuth?.organization}
                            <span class="text-xs text-slate-400 font-medium">&bull;</span>
                            <span class="text-xs font-semibold text-slate-600 flex items-center gap-1">
                                <Building class="w-3.5 h-3.5 text-slate-400" />
                                {currentAuth.organization.name}
                            </span>
                        {/if}
                    </div>

                    <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-slate-900">
                        Selamat datang, {currentAuth?.user?.name || 'Pengguna SAKIP'}
                    </h1>
                    <p class="text-sm text-slate-500 max-w-2xl leading-relaxed">
                        Sistem Analisis &amp; Konsolidasi Kinerja Instansi Pemerintah. Pantau integrasi cascading sasaran strategis, kelengkapan dokumen Renstra, dan kesiapan evaluasi akuntabilitas kinerja.
                    </p>
                </div>

                <!-- Primary Action Shortcuts -->
                <div class="flex flex-wrap items-center gap-3 shrink-0">
                    <Link
                        href="/documents"
                        class="inline-flex items-center gap-2 px-3.5 py-2 rounded-lg bg-white hover:bg-slate-50 text-slate-700 text-sm font-medium border border-slate-200 shadow-2xs transition-colors"
                    >
                        <Upload class="w-4 h-4 text-slate-500" />
                        <span>Unggah Dokumen</span>
                    </Link>
                    <Link
                        href="/kinerja"
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white text-sm font-semibold shadow-xs transition-colors"
                    >
                        <Plus class="w-4 h-4" />
                        <span>Buka Pohon Kinerja</span>
                    </Link>
                </div>
            </div>
        </div>

        <!-- 2. Metric Summary Cards Grid -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-5">
            <!-- Card 1: Pohon Kinerja -->
            <div class="bg-white rounded-xl border border-slate-200/80 p-5 shadow-xs flex flex-col justify-between hover:border-slate-300 transition-colors">
                <div class="flex items-start justify-between gap-3 mb-4">
                    <div>
                        <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Pohon Kinerja</p>
                        <h3 class="text-3xl font-bold text-slate-900 tracking-tight mt-1 tabular-nums">
                            {metrics?.trees_count ?? 0}
                        </h3>
                    </div>
                    <div class="h-10 w-10 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center shrink-0 border border-emerald-100">
                        <GitFork class="w-5 h-5" />
                    </div>
                </div>
                <div class="pt-3 border-t border-slate-100 flex items-center justify-between text-xs">
                    <span class="text-slate-500 tabular-nums">
                        {metrics?.nodes_count ?? 0} Sasaran &amp; Output
                    </span>
                    <Link href="/kinerja" class="font-semibold text-emerald-700 hover:text-emerald-800 flex items-center gap-1">
                        Buka Pohon <ArrowRight class="w-3.5 h-3.5" />
                    </Link>
                </div>
            </div>

            <!-- Card 2: Dokumen Perencanaan -->
            <div class="bg-white rounded-xl border border-slate-200/80 p-5 shadow-xs flex flex-col justify-between hover:border-slate-300 transition-colors">
                <div class="flex items-start justify-between gap-3 mb-4">
                    <div>
                        <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Dokumen Perencanaan</p>
                        <h3 class="text-3xl font-bold text-slate-900 tracking-tight mt-1 tabular-nums">
                            {metrics?.documents_count ?? 0}
                        </h3>
                    </div>
                    <div class="h-10 w-10 rounded-lg bg-emerald-50 text-emerald-700 flex items-center justify-center shrink-0 border border-emerald-100">
                        <FileText class="w-5 h-5" />
                    </div>
                </div>
                <div class="pt-3 border-t border-slate-100 flex items-center justify-between text-xs">
                    <span class="text-slate-500">Renstra / RPJMD</span>
                    <Link href="/documents" class="font-semibold text-emerald-700 hover:text-emerald-800 flex items-center gap-1">
                        Lihat Dokumen <ArrowRight class="w-3.5 h-3.5" />
                    </Link>
                </div>
            </div>

            <!-- Card 3: Knowledge Pack -->
            <div class="bg-white rounded-xl border border-slate-200/80 p-5 shadow-xs flex flex-col justify-between hover:border-slate-300 transition-colors">
                <div class="flex items-start justify-between gap-3 mb-4">
                    <div>
                        <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Knowledge Pack</p>
                        <h3 class="text-3xl font-bold text-slate-900 tracking-tight mt-1 tabular-nums">
                            {metrics?.knowledge_packs_count ?? 0}
                        </h3>
                    </div>
                    <div class="h-10 w-10 rounded-lg bg-amber-50 text-amber-600 flex items-center justify-center shrink-0 border border-amber-100">
                        <BookOpen class="w-5 h-5" />
                    </div>
                </div>
                <div class="pt-3 border-t border-slate-100 flex items-center justify-between text-xs">
                    <span class="text-slate-500">Standar Sektoral</span>
                    <Link href="/knowledge-packs" class="font-semibold text-amber-700 hover:text-amber-800 flex items-center gap-1">
                        Aturan Sektor <ArrowRight class="w-3.5 h-3.5" />
                    </Link>
                </div>
            </div>

            <!-- Card 4: Kepatuhan Cascading -->
            <div class="bg-white rounded-xl border border-slate-200/80 p-5 shadow-xs flex flex-col justify-between hover:border-slate-300 transition-colors">
                <div class="flex items-start justify-between gap-3 mb-4">
                    <div>
                        <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Standar Evaluasi</p>
                        <h3 class="text-xl font-bold text-slate-900 tracking-tight mt-2">
                            PermenPAN-RB
                        </h3>
                    </div>
                    <div class="h-10 w-10 rounded-lg bg-purple-50 text-purple-600 flex items-center justify-center shrink-0 border border-purple-100">
                        <ShieldCheck class="w-5 h-5" />
                    </div>
                </div>
                <div class="pt-3 border-t border-slate-100 flex items-center justify-between text-xs">
                    <span class="text-slate-500">Pedoman 88/2021</span>
                    <span class="inline-flex items-center gap-1 font-semibold text-emerald-700">
                        <CircleCheck class="w-3.5 h-3.5" /> Terstandarisasi
                    </span>
                </div>
            </div>
        </div>

        <!-- 3. Primary Workflow Shortcuts -->
        <div class="space-y-3">
            <div class="flex items-center justify-between">
                <h2 class="text-base font-bold text-slate-900 tracking-tight">Alur Kerja Utama SAKIP</h2>
                <span class="text-xs text-slate-400 font-medium">3 Tahapan Penyusunan Kinerja</span>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <!-- Step 1 -->
                <Link
                    href="/documents"
                    class="group bg-white rounded-xl border border-slate-200/80 p-5 hover:border-emerald-300 hover:shadow-xs transition-all relative"
                >
                    <div class="flex items-start gap-3.5">
                        <div class="h-9 w-9 rounded-lg bg-slate-100 text-slate-700 group-hover:bg-emerald-50 group-hover:text-emerald-700 flex items-center justify-center shrink-0 transition-colors">
                            <Upload class="w-4 h-4" />
                        </div>
                        <div class="space-y-1">
                            <h3 class="text-sm font-bold text-slate-900 group-hover:text-emerald-800 transition-colors flex items-center gap-1.5">
                                1. Unggah Dokumen Renstra
                                <ArrowRight class="w-3.5 h-3.5 text-slate-400 group-hover:text-emerald-600 transition-transform group-hover:translate-x-0.5" />
                            </h3>
                            <p class="text-xs text-slate-500 leading-relaxed">
                                Simpan dokumen perencanaan PDF untuk diekstraksi menjadi target dan indikator kinerja awal.
                            </p>
                        </div>
                    </div>
                </Link>

                <!-- Step 2 -->
                <Link
                    href="/kinerja"
                    class="group bg-white rounded-xl border border-slate-200/80 p-5 hover:border-emerald-300 hover:shadow-xs transition-all relative"
                >
                    <div class="flex items-start gap-3.5">
                        <div class="h-9 w-9 rounded-lg bg-slate-100 text-slate-700 group-hover:bg-emerald-50 group-hover:text-emerald-700 flex items-center justify-center shrink-0 transition-colors">
                            <GitFork class="w-4 h-4" />
                        </div>
                        <div class="space-y-1">
                            <h3 class="text-sm font-bold text-slate-900 group-hover:text-emerald-800 transition-colors flex items-center gap-1.5">
                                2. Rancang Pohon Kinerja
                                <ArrowRight class="w-3.5 h-3.5 text-slate-400 group-hover:text-emerald-600 transition-transform group-hover:translate-x-0.5" />
                            </h3>
                            <p class="text-xs text-slate-500 leading-relaxed">
                                Petakan cascading sasaran strategis, outcome program, hingga output kegiatan secara logis.
                            </p>
                        </div>
                    </div>
                </Link>

                <!-- Step 3 -->
                <Link
                    href="/knowledge-packs"
                    class="group bg-white rounded-xl border border-slate-200/80 p-5 hover:border-emerald-300 hover:shadow-xs transition-all relative"
                >
                    <div class="flex items-start gap-3.5">
                        <div class="h-9 w-9 rounded-lg bg-slate-100 text-slate-700 group-hover:bg-emerald-50 group-hover:text-emerald-700 flex items-center justify-center shrink-0 transition-colors">
                            <Sparkles class="w-4 h-4" />
                        </div>
                        <div class="space-y-1">
                            <h3 class="text-sm font-bold text-slate-900 group-hover:text-emerald-800 transition-colors flex items-center gap-1.5">
                                3. Knowledge Pack &amp; AI
                                <ArrowRight class="w-3.5 h-3.5 text-slate-400 group-hover:text-emerald-600 transition-transform group-hover:translate-x-0.5" />
                            </h3>
                            <p class="text-xs text-slate-500 leading-relaxed">
                                Terapkan aturan sektor dan rekomendasi indikator AI untuk memastikan akurasi cascading.
                            </p>
                        </div>
                    </div>
                </Link>
            </div>
        </div>

        <!-- 4. Two-Column Recent Activity Overview -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Left Column: Recent Pohon Kinerja -->
            <div class="bg-white rounded-xl border border-slate-200/80 shadow-xs flex flex-col justify-between">
                <div class="p-5 border-b border-slate-100 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <GitFork class="w-4 h-4 text-emerald-600" />
                        <h2 class="text-sm font-bold text-slate-900 tracking-tight">Pohon Kinerja Terkini</h2>
                    </div>
                    <Link href="/kinerja" class="text-xs font-semibold text-emerald-700 hover:text-emerald-800 flex items-center gap-1">
                        Lihat Semua ({metrics?.trees_count ?? 0}) <ArrowRight class="w-3 h-3" />
                    </Link>
                </div>

                <div class="p-5 flex-1">
                    {#if recentTrees && recentTrees.length > 0}
                        <div class="divide-y divide-slate-100">
                            {#each recentTrees as tree}
                                {@const statusInfo = formatTreeStatus(tree.status)}
                                <div class="py-3.5 first:pt-0 last:pb-0 flex items-center justify-between gap-4 group">
                                    <div class="space-y-1 min-w-0">
                                        <Link
                                            href={`/kinerja/${tree.id}`}
                                            class="text-sm font-semibold text-slate-900 hover:text-emerald-700 transition-colors truncate block"
                                            title={tree.name}
                                        >
                                            {tree.name}
                                        </Link>
                                        <div class="flex flex-wrap items-center gap-2 text-xs text-slate-500">
                                            {#if tree.sector?.name}
                                                <span class="inline-block px-2 py-0.5 rounded bg-slate-100 text-slate-700 font-medium text-[11px]">
                                                    {tree.sector.name}
                                                </span>
                                            {/if}
                                            <span>{formatPeriod(tree.period_start, tree.period_end)}</span>
                                            <span class="text-slate-300">&bull;</span>
                                            <span class="tabular-nums">{tree.nodes_count ?? 0} node</span>
                                        </div>
                                    </div>

                                    <div class="flex items-center gap-2 shrink-0">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium border {statusInfo.class}">
                                            {statusInfo.label}
                                        </span>
                                        <Link
                                            href={`/kinerja/${tree.id}`}
                                            class="p-1.5 text-slate-400 group-hover:text-emerald-600 rounded-md hover:bg-slate-100 transition-colors"
                                            title="Buka Pohon Kinerja"
                                        >
                                            <ArrowRight class="w-4 h-4" />
                                        </Link>
                                    </div>
                                </div>
                            {/each}
                        </div>
                    {:else}
                        <div class="py-8 text-center space-y-3">
                            <div class="h-10 w-10 rounded-full bg-slate-100 text-slate-400 mx-auto flex items-center justify-center">
                                <GitFork class="w-5 h-5" />
                            </div>
                            <div class="space-y-1">
                                <p class="text-sm font-semibold text-slate-800">Belum ada Pohon Kinerja</p>
                                <p class="text-xs text-slate-500 max-w-sm mx-auto">
                                    Mulai susun struktur cascading kinerja instansi Anda dari Sasaran Strategis hingga Output.
                                </p>
                            </div>
                            <Link
                                href="/kinerja"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md bg-emerald-600 text-white text-xs font-semibold hover:bg-emerald-500 transition-colors"
                            >
                                <Plus class="w-3.5 h-3.5" /> Buat Pohon Kinerja
                            </Link>
                        </div>
                    {/if}
                </div>
            </div>

            <!-- Right Column: Recent Dokumen -->
            <div class="bg-white rounded-xl border border-slate-200/80 shadow-xs flex flex-col justify-between">
                <div class="p-5 border-b border-slate-100 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <FileText class="w-4 h-4 text-emerald-600" />
                        <h2 class="text-sm font-bold text-slate-900 tracking-tight">Dokumen Perencanaan Terkini</h2>
                    </div>
                    <Link href="/documents" class="text-xs font-semibold text-emerald-700 hover:text-emerald-800 flex items-center gap-1">
                        Lihat Semua ({metrics?.documents_count ?? 0}) <ArrowRight class="w-3 h-3" />
                    </Link>
                </div>

                <div class="p-5 flex-1">
                    {#if recentDocuments && recentDocuments.length > 0}
                        <div class="divide-y divide-slate-100">
                            {#each recentDocuments as doc}
                                {@const statusInfo = formatDocStatus(doc.status)}
                                <div class="py-3.5 first:pt-0 last:pb-0 flex items-center justify-between gap-4 group">
                                    <div class="space-y-1 min-w-0">
                                        <Link
                                            href={`/documents/${doc.id}`}
                                            class="text-sm font-semibold text-slate-900 hover:text-emerald-700 transition-colors truncate block"
                                            title={doc.title}
                                        >
                                            {doc.title}
                                        </Link>
                                        <div class="flex flex-wrap items-center gap-2 text-xs text-slate-500">
                                            <span class="inline-block px-1.5 py-0.5 rounded bg-slate-100 text-slate-700 font-semibold text-[10px] uppercase border border-slate-200">
                                                {doc.type}
                                            </span>
                                            <span>{formatPeriod(doc.period_start, doc.period_end)}</span>
                                        </div>
                                    </div>

                                    <div class="flex items-center gap-2 shrink-0">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium border {statusInfo.class}">
                                            {statusInfo.label}
                                        </span>
                                        <Link
                                            href={`/documents/${doc.id}`}
                                            class="p-1.5 text-slate-400 group-hover:text-emerald-600 rounded-md hover:bg-slate-100 transition-colors"
                                            title="Lihat Dokumen"
                                        >
                                            <ArrowRight class="w-4 h-4" />
                                        </Link>
                                    </div>
                                </div>
                            {/each}
                        </div>
                    {:else}
                        <div class="py-8 text-center space-y-3">
                            <div class="h-10 w-10 rounded-full bg-slate-100 text-slate-400 mx-auto flex items-center justify-center">
                                <FileText class="w-5 h-5" />
                            </div>
                            <div class="space-y-1">
                                <p class="text-sm font-semibold text-slate-800">Belum ada Dokumen Diunggah</p>
                                <p class="text-xs text-slate-500 max-w-sm mx-auto">
                                    Unggah Renstra atau RPJMD untuk memulai analisis dan ekstraksi sasaran kinerja secara otomatis.
                                </p>
                            </div>
                            <Link
                                href="/documents"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md bg-emerald-600 text-white text-xs font-semibold hover:bg-emerald-500 transition-colors"
                            >
                                <Upload class="w-3.5 h-3.5" /> Unggah Dokumen
                            </Link>
                        </div>
                    {/if}
                </div>
            </div>
        </div>

        <!-- 5. PermenPAN-RB Cascading Standards Guidance Notice -->
        <div class="bg-slate-100/70 border border-slate-200/80 rounded-xl p-5 text-slate-600 text-xs">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <div class="flex items-center gap-2 text-slate-800 font-semibold">
                    <ShieldCheck class="w-4 h-4 text-emerald-600" />
                    <span>Panduan Tingkatan Cascading SAKIP (PermenPAN-RB No. 88/2021)</span>
                </div>
                <span class="text-slate-400">Hierarki Logis Kinerja</span>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mt-3 pt-3 border-t border-slate-200/60 text-slate-600">
                <div class="space-y-0.5">
                    <p class="font-semibold text-slate-800">1. Sasaran Strategis (SS)</p>
                    <p class="text-[11px] text-slate-500">Tanggung jawab Eselon I / Pimpinan Instansi, fokus pada dampak makro (Impact).</p>
                </div>
                <div class="space-y-0.5">
                    <p class="font-semibold text-slate-800">2. Outcome Program</p>
                    <p class="text-[11px] text-slate-500">Tanggung jawab Eselon II, perubahan terukur yang dihasilkan dari intervensi pelayanan.</p>
                </div>
                <div class="space-y-0.5">
                    <p class="font-semibold text-slate-800">3. Output Kegiatan</p>
                    <p class="text-[11px] text-slate-500">Tanggung jawab Eselon III/IV/JF, barang/jasa langsung yang diserahkan kepada penerima manfaat.</p>
                </div>
            </div>
        </div>
    </div>
</Layout>

