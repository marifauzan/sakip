<script>
    import { useForm, Link } from '@inertiajs/svelte';
    import Layout from '../Layout.svelte';

    let { tree } = $props();

    const nodeForm = useForm({ statement: '', type: 'outcome', code: '' });
    const linkForm = useForm({ parent_node_id: '', child_node_id: '', reason: '' });
    const indicatorForm = useForm({
        node_id: '',
        name: '',
        unit: '',
        direction: 'naik',
        baseline: '',
        target: '',
    });

    const nodes = tree.nodes;
    const links = tree.links;

    function nodeLabel(id) {
        const n = nodes.find((x) => x.id === Number(id));
        return n ? (n.code ? `${n.code} — ${n.statement}` : n.statement) : '—';
    }

    function submitNode(e) {
        nodeForm.post(`/kinerja/${tree.id}/nodes`, { preserveScroll: true });
    }
    function submitLink(e) {
        linkForm.post(`/kinerja/${tree.id}/links`, { preserveScroll: true });
    }
    function submitIndicator(e) {
        indicatorForm.post(`/kinerja/${tree.id}/nodes/${indicatorForm.node_id}/indicators`, { preserveScroll: true });
    }

    // Anak dari tiap node (untuk menampilkan relasi)
    function childrenOf(nodeId) {
        return links.filter((l) => l.parent_node_id === nodeId).map((l) => l.child_node_id);
    }
    function indicatorsOf(nodeId) {
        const n = nodes.find((x) => x.id === nodeId);
        return n ? n.indicators : [];
    }

    const typeLabel = { outcome: 'Outcome', output: 'Output', aktivitas: 'Aktivitas' };
</script>

<Layout>
    <div class="space-y-6">
        <div>
            <Link href="/kinerja" class="text-sm text-indigo-600 hover:underline">← Kembali</Link>
            <h2 class="text-xl font-bold mt-2">{tree.name}</h2>
            <p class="text-sm text-gray-500">{nodes.length} sasaran · {links.length} hubungan · status: {tree.status}</p>
        </div>

        <!-- Tambah Sasaran -->
        <form onsubmit={(e) => { e.preventDefault(); submitNode(); }} class="rounded-lg border border-gray-200 bg-white p-4 space-y-3">
            <h3 class="font-semibold">Tambah Sasaran</h3>
            <div class="grid grid-cols-3 gap-3">
                <div class="col-span-2">
                    <input type="text" bind:value={nodeForm.statement} placeholder="Rumusan sasaran (hasil)" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm" />
                </div>
                <select bind:value={nodeForm.type} class="rounded-md border border-gray-300 px-3 py-2 text-sm">
                    <option value="outcome">Outcome</option>
                    <option value="output">Output</option>
                    <option value="aktivitas">Aktivitas</option>
                </select>
            </div>
            <div class="flex items-center gap-3">
                <input type="text" bind:value={nodeForm.code} placeholder="Kode (opsional)" class="w-40 rounded-md border border-gray-300 px-3 py-2 text-sm" />
                <button type="submit" disabled={nodeForm.processing} class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500 disabled:opacity-50">
                    Tambah
                </button>
            </div>
            {#if nodeForm.errors.statement}<p class="text-xs text-red-600">{nodeForm.errors.statement}</p>{/if}
        </form>

        <!-- Daftar Sasaran + hubungan + indikator -->
        <div class="space-y-3">
            {#each nodes as node (node.id)}
                <div class="rounded-lg border border-gray-200 bg-white p-4">
                    <div class="flex items-start justify-between">
                        <div>
                            <div class="flex items-center gap-2">
                                {#if node.code}<span class="rounded bg-gray-100 px-1.5 py-0.5 text-xs font-mono text-gray-600">{node.code}</span>{/if}
                                <span class="text-xs text-gray-400 uppercase">{typeLabel[node.type] ?? node.type}</span>
                            </div>
                            <p class="mt-1 font-medium text-gray-900">{node.statement}</p>
                        </div>
                        <div class="text-xs text-gray-400">
                            {#each indicatorsOf(node.id) as ind (ind.id)}
                                <div class="text-right">{ind.name} {ind.target ? `→ ${ind.target}` : ''}</div>
                            {/each}
                        </div>
                    </div>

                    {#if childrenOf(node.id).length > 0}
                        <div class="mt-2 ml-4 border-l-2 border-gray-100 pl-3 space-y-1">
                            <div class="text-xs text-gray-400 font-medium">Menurunkan:</div>
                            {#each childrenOf(node.id) as childId (childId)}
                                <div class="text-sm text-gray-600">▸ {nodeLabel(childId)}</div>
                            {/each}
                        </div>
                    {/if}

                    <!-- Tambah indikator inline -->
                    <details class="mt-3">
                        <summary class="text-xs text-indigo-600 cursor-pointer hover:underline">+ Tambah indikator</summary>
                        <form onsubmit={(e) => { e.preventDefault(); indicatorForm.node_id = node.id; submitIndicator(); }} class="mt-2 grid grid-cols-2 gap-2">
                            <input type="text" bind:value={indicatorForm.name} placeholder="Nama indikator" class="col-span-2 rounded-md border border-gray-300 px-2 py-1.5 text-sm" />
                            <input type="text" bind:value={indicatorForm.unit} placeholder="Satuan" class="rounded-md border border-gray-300 px-2 py-1.5 text-sm" />
                            <select bind:value={indicatorForm.direction} class="rounded-md border border-gray-300 px-2 py-1.5 text-sm">
                                <option value="naik">Semakin tinggi baik</option>
                                <option value="turun">Semakin rendah baik</option>
                                <option value="tetap">Tetap</option>
                            </select>
                            <input type="text" bind:value={indicatorForm.baseline} placeholder="Baseline" class="rounded-md border border-gray-300 px-2 py-1.5 text-sm" />
                            <input type="text" bind:value={indicatorForm.target} placeholder="Target" class="rounded-md border border-gray-300 px-2 py-1.5 text-sm" />
                            <button type="submit" class="col-span-2 rounded-md bg-gray-800 px-3 py-1.5 text-xs font-semibold text-white hover:bg-gray-700">Simpan Indikator</button>
                        </form>
                    </details>
                </div>
            {/each}

            {#if nodes.length === 0}
                <p class="text-sm text-gray-500">Belum ada sasaran. Tambahkan di atas.</p>
            {/if}
        </div>

        <!-- Hubungkan Sasaran -->
        {#if nodes.length >= 2}
            <form onsubmit={(e) => { e.preventDefault(); submitLink(); }} class="rounded-lg border border-gray-200 bg-white p-4 space-y-3">
                <h3 class="font-semibold">Hubungkan Sasaran (parent → child)</h3>
                <div class="grid grid-cols-2 gap-3">
                    <select bind:value={linkForm.parent_node_id} class="rounded-md border border-gray-300 px-3 py-2 text-sm">
                        <option value="">— Pilih sasaran induk —</option>
                        {#each nodes as node (node.id)}
                            <option value={node.id}>{nodeLabel(node.id)}</option>
                        {/each}
                    </select>
                    <select bind:value={linkForm.child_node_id} class="rounded-md border border-gray-300 px-3 py-2 text-sm">
                        <option value="">— Pilih sasaran turunan —</option>
                        {#each nodes as node (node.id)}
                            <option value={node.id}>{nodeLabel(node.id)}</option>
                        {/each}
                    </select>
                </div>
                <input type="text" bind:value={linkForm.reason} placeholder="Alasan hubungan sebab-akibat (opsional)" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm" />
                {#if linkForm.errors.link}<p class="text-xs text-red-600">{linkForm.errors.link}</p>{/if}
                <button type="submit" disabled={linkForm.processing} class="rounded-md bg-gray-800 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-700 disabled:opacity-50">
                    Hubungkan
                </button>
            </form>
        {/if}
    </div>
</Layout>
