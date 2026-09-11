<script>
    import { useForm, router } from '@inertiajs/svelte';
    import Layout from '../Layout.svelte';

    const form = useForm({ email: '', password: '', remember: false });

    function submit() {
        form.post('/login', { onError: () => form.reset('password') });
    }
</script>

<Layout>
    <div class="max-w-sm mx-auto mt-16">
        <h2 class="text-xl font-bold mb-6">Masuk ke SAKIP</h2>

        <form onsubmit={(e) => { e.preventDefault(); submit(); }} class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700">Email</label>
                <input
                    type="email"
                    bind:value={form.email}
                    class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
                />
                {#if form.errors.email}
                    <p class="mt-1 text-xs text-red-600">{form.errors.email}</p>
                {/if}
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">Password</label>
                <input
                    type="password"
                    bind:value={form.password}
                    class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
                />
                {#if form.errors.password}
                    <p class="mt-1 text-xs text-red-600">{form.errors.password}</p>
                {/if}
            </div>

            <label class="flex items-center gap-2 text-sm text-gray-600">
                <input type="checkbox" bind:checked={form.remember} />
                Ingat saya
            </label>

            <button
                type="submit"
                disabled={form.processing}
                class="w-full rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white hover:bg-indigo-500 disabled:opacity-50"
            >
                {form.processing ? 'Memproses...' : 'Masuk'}
            </button>
        </form>

        <p class="mt-6 text-xs text-gray-400">
            Demo: admin@kemenag.test / planner@disdik.test (password: password)
        </p>
    </div>
</Layout>
