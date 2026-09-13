<script>
    import { useForm, Link } from '@inertiajs/svelte';
    import Layout from '../Layout.svelte';

    let { trees = [], sectors = [] } = $props();

    const form = useForm({ name: '', period_start: '', period_end: '', sector_id: '' });

    function submit(e) {
        form.post('/kinerja');
    }
</script>

<Layout>
    <div class="space-y-6">
        <h2 class="text-xl font-bold">Rancangan Perjenjangan Kinerja</h2>

        <form onsubmit={(e) => { e.preventDefault(); submit(); }} class="rounded-lg border border-gray-200 bg-white p-4 space-y-4">
            <div>
                <label for="tree-name" class="block text-sm font-medium text-gray-700">Nama Rancangan</label>
                <input id="tree-name" type="text" bind:value={form.name} class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm" placeholder="mis. Perjenjangan Kinerja 2026" />
                {#if form.errors.name}<p class="mt-1 text-xs text-red-600">{form.errors.name}</p>{/if}
            </div>

            <div>
                <label for="tree-sector" class="block text-sm font-medium text-gray-700">Sektor Terkait (Opsional)</label>
                <select id="tree-sector" bind:value={form.sector_id} class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm bg-white">
                    <option value="">— Tidak Terikat Sektor Tertentu —</option>
                    {#each sectors as sector (sector.id)}
                        <option value={sector.id}>
                            {sector.name} {sector.is_global ? '(Global)' : '(Instansi)'}
                        </option>
                    {/each}
                </select>
                {#if form.errors.sector_id}<p class="mt-1 text-xs text-red-600">{form.errors.sector_id}</p>{/if}
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label for="tree-period-start" class="block text-sm font-medium text-gray-700">Tahun Awal</label>
                    <input id="tree-period-start" type="number" bind:value={form.period_start} class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm" />
                </div>
                <div>
                    <label for="tree-period-end" class="block text-sm font-medium text-gray-700">Tahun Akhir</label>
                    <input id="tree-period-end" type="number" bind:value={form.period_end} class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm" />
                </div>
            </div>
            <button type="submit" disabled={form.processing} class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500 disabled:opacity-50">
                {form.processing ? 'Membuat…' : 'Buat Rancangan'}
            </button>
        </form>

        {#if trees.length === 0}
            <p class="text-sm text-gray-500">Belum ada rancangan.</p>
        {:else}
            <div class="overflow-hidden rounded-lg border border-gray-200 bg-white">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr class="text-left text-gray-500">
                            <th class="px-4 py-3">Nama</th>
                            <th class="px-4 py-3">Sektor</th>
                            <th class="px-4 py-3">Periode</th>
                            <th class="px-4 py-3">Sasaran</th>
                            <th class="px-4 py-3">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        {#each trees as tree (tree.id)}
                            <tr>
                                <td class="px-4 py-3">
                                    <Link href={`/kinerja/${tree.id}`} class="text-indigo-600 hover:underline font-medium">{tree.name}</Link>
                                </td>
                                <td class="px-4 py-3 text-gray-600">
                                    {#if tree.sector}
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-50 text-blue-700 border border-blue-200">
                                            {tree.sector.name}
                                        </span>
                                    {:else}
                                        <span class="text-gray-400">—</span>
                                    {/if}
                                </td>
                                <td class="px-4 py-3 text-gray-600">{tree.period_start ?? '—'}–{tree.period_end ?? '—'}</td>
                                <td class="px-4 py-3 text-gray-600">{tree.nodes_count}</td>
                                <td class="px-4 py-3 text-gray-600">{tree.status}</td>
                            </tr>
                        {/each}
                    </tbody>
                </table>
            </div>
        {/if}
    </div>
</Layout>
