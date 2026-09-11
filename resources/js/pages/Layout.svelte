<script>
    import { Link } from '@inertiajs/svelte';
    let { children, auth = null } = $props();
</script>

<div class="min-h-screen">
    <header class="bg-white border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 py-4 flex items-center justify-between">
            <div class="flex items-center gap-6">
                <h1 class="text-lg font-semibold text-gray-900">SAKIP</h1>
                {#if auth?.user}
                    <nav class="flex items-center gap-4 text-sm">
                        <Link href="/dashboard" class="text-gray-600 hover:text-gray-900">Dashboard</Link>
                        <Link href="/documents" class="text-gray-600 hover:text-gray-900">Dokumen</Link>
                        <Link href="/kinerja" class="text-gray-600 hover:text-gray-900">Kinerja</Link>
                    </nav>
                {/if}
            </div>
            <span class="text-sm text-gray-500">Sistem Analisis &amp; Konsolidasi Kinerja Instansi</span>
        </div>
    </header>
    <main class="max-w-7xl mx-auto px-4 py-6">
        {@render children()}
    </main>
</div>
