<script>
    let { title = 'SAKIP' } = $props();
</script>

<div class="min-h-screen">
    <header class="bg-white border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 py-4 flex items-center justify-between">
            <h1 class="text-lg font-semibold text-gray-900">SAKIP</h1>
            <span class="text-sm text-gray-500">Sistem Analisis &amp; Konsolidasi Kinerja Instansi</span>
        </div>
    </header>
    <main class="max-w-7xl mx-auto px-4 py-6">
        {@render children()}
    </main>
</div>
