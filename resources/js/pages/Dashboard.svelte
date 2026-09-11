<script>
    import { router } from '@inertiajs/svelte';
    import Layout from './Layout.svelte';

    let { auth } = $props();

    function logout() {
        router.post('/logout');
    }
</script>

<Layout {auth}>
    <div class="space-y-4">
        <div class="flex items-center justify-between">
            <h2 class="text-xl font-bold">Dashboard</h2>
            <button
                onclick={logout}
                class="rounded-md border border-gray-300 px-3 py-1.5 text-sm text-gray-600 hover:bg-gray-50"
            >
                Keluar
            </button>
        </div>

        {#if auth?.user}
            <div class="rounded-lg border border-gray-200 bg-white p-4 space-y-2">
                <p class="text-sm">
                    Masuk sebagai <span class="font-semibold">{auth.user.name}</span>
                    <span class="text-gray-400">({auth.user.role})</span>
                </p>
                {#if auth.organization}
                    <p class="text-sm text-gray-600">
                        Organisasi: <span class="font-semibold">{auth.organization.name}</span>
                        <span class="text-gray-400">({auth.organization.type})</span>
                    </p>
                {/if}
            </div>
        {/if}

        <div class="rounded-lg border border-gray-200 bg-white p-4">
            <p class="text-sm text-gray-500">
                MVP scaffold &amp; auth berjalan. Tahap berikutnya: dokumen &amp; editor kinerja.
            </p>
        </div>
    </div>
</Layout>
