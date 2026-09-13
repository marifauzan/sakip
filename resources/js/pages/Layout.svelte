<script>
    import { Link, page } from '@inertiajs/svelte';
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
                        <Link href="/knowledge-packs" class="text-gray-600 hover:text-gray-900">Knowledge Pack</Link>
                    </nav>
                {/if}
            </div>
            <span class="text-sm text-gray-500">Sistem Analisis &amp; Konsolidasi Kinerja Instansi</span>
        </div>
    </header>
    <main class="max-w-7xl mx-auto px-4 py-6 space-y-4">
        {#if page.props.flash?.success}
            <div class="rounded-md bg-green-50 border border-green-200 p-4 text-sm text-green-800">
                {page.props.flash.success}
            </div>
        {/if}
        {#if page.props.flash?.error}
            <div class="rounded-md bg-red-50 border border-red-200 p-4 text-sm text-red-800">
                {page.props.flash.error}
            </div>
        {/if}
        {@render children()}
    </main>
</div>
