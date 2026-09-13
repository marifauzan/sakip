<script>
    import { Link, page, router } from '@inertiajs/svelte';
    import {
        LayoutDashboard,
        FileText,
        GitFork,
        BookOpen,
        PanelLeftClose,
        PanelLeft,
        LogOut,
        Building,
        User,
        ChevronRight,
        Menu,
        X,
        CircleCheck,
        CircleAlert,
        Sparkles
    } from '@lucide/svelte';

    let { children, auth = null, title = null, breadcrumbs = [] } = $props();

    // Responsive and collapse state with localStorage persistence
    let isCollapsed = $state(
        typeof window !== 'undefined'
            ? localStorage.getItem('sakip_sidebar_collapsed') === 'true'
            : false
    );
    let isMobileOpen = $state(false);

    // Current auth context from prop or global Inertia shared props
    let currentAuth = $derived(auth ?? page.props.auth);
    let isAuthenticated = $derived(Boolean(currentAuth?.user));

    // Normalized current path without query parameters or trailing slash
    let currentPath = $derived((page.url || '').split('?')[0].replace(/\/$/, '') || '/');

    // Automatically close mobile drawer when route changes
    $effect(() => {
        if (page.url) {
            isMobileOpen = false;
        }
    });

    // Navigation items
    const navItems = [
        {
            name: 'Dashboard',
            href: '/dashboard',
            icon: LayoutDashboard,
            isActive: (path) => path === '/dashboard' || path === '/',
        },
        {
            name: 'Dokumen',
            href: '/documents',
            icon: FileText,
            isActive: (path) => path === '/documents' || path.startsWith('/documents/'),
        },
        {
            name: 'Pohon Kinerja',
            href: '/kinerja',
            icon: GitFork,
            isActive: (path) => path === '/kinerja' || path.startsWith('/kinerja/'),
        },
        {
            name: 'Knowledge Pack',
            href: '/knowledge-packs',
            icon: BookOpen,
            isActive: (path) => path === '/knowledge-packs' || path.startsWith('/knowledge-packs/'),
        },
    ];

    // Compute automatic breadcrumbs if not provided
    let computedBreadcrumbs = $derived.by(() => {
        if (breadcrumbs && breadcrumbs.length > 0) {
            return breadcrumbs;
        }
        const path = currentPath;
        const crumbs = [{ label: 'Beranda', href: '/dashboard' }];

        if (path === '/' || path === '/dashboard') {
            crumbs.push({ label: 'Dashboard', href: '/dashboard' });
            return crumbs;
        }

        if (path.startsWith('/documents')) {
            crumbs.push({ label: 'Dokumen Perencanaan', href: '/documents' });
            if (path !== '/documents') {
                crumbs.push({ label: title || 'Detail Dokumen', href: page.url || path });
            }
            return crumbs;
        }

        if (path.startsWith('/kinerja')) {
            crumbs.push({ label: 'Pohon Kinerja', href: '/kinerja' });
            if (path !== '/kinerja') {
                crumbs.push({ label: title || 'Detail Pohon Kinerja', href: page.url || path });
            }
            return crumbs;
        }

        if (path.startsWith('/knowledge-packs')) {
            crumbs.push({ label: 'Knowledge Pack', href: '/knowledge-packs' });
            if (path !== '/knowledge-packs') {
                crumbs.push({ label: title || 'Aturan Sektor', href: page.url || path });
            }
            return crumbs;
        }

        if (title) {
            crumbs.push({ label: title, href: page.url || path });
        }

        return crumbs;
    });

    function toggleSidebar() {
        isCollapsed = !isCollapsed;
        if (typeof window !== 'undefined') {
            try {
                localStorage.setItem('sakip_sidebar_collapsed', String(isCollapsed));
            } catch {
                // Storage may be unavailable
            }
        }
    }

    function toggleMobile() {
        isMobileOpen = !isMobileOpen;
    }

    function closeMobile() {
        isMobileOpen = false;
    }

    function logout() {
        router.post('/logout');
    }

    function formatRole(role) {
        switch (role) {
            case 'planner':
                return 'Perencana';
            case 'reviewer':
                return 'Reviewer';
            case 'admin':
                return 'Admin';
            default:
                return role || 'Pengguna';
        }
    }

    function getInitials(name) {
        if (!name) return 'SK';
        const parts = name.trim().split(/\s+/);
        if (parts.length === 1) return parts[0].substring(0, 2).toUpperCase();
        return (parts[0][0] + parts[1][0]).toUpperCase();
    }
</script>

<svelte:head>
    <title>{title ? `${title} \u2014 SAKIP` : 'SAKIP \u2014 Sistem Akuntabilitas Kinerja'}</title>
</svelte:head>

{#if !isAuthenticated}
    <!-- Guest Layout (e.g. Login page) -->
    <div class="min-h-screen bg-slate-50 text-slate-900 flex flex-col justify-between">
        <header class="bg-white/80 backdrop-blur-sm border-b border-slate-200">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 py-4 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-emerald-600 text-white shadow-xs">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <rect width="6" height="4" x="9" y="3" rx="1" />
                            <rect width="6" height="4" x="3" y="17" rx="1" />
                            <rect width="6" height="4" x="15" y="17" rx="1" />
                            <path d="M12 7v6" />
                            <path d="M6 17v-2a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v2" />
                        </svg>
                    </div>
                    <div>
                        <h1 class="text-base font-bold text-slate-900 tracking-tight leading-tight">SAKIP</h1>
                        <p class="text-[11px] text-slate-500 font-medium leading-none">Sistem Akuntabilitas Kinerja</p>
                    </div>
                </div>
                <span class="text-xs text-slate-500 hidden sm:inline-block">Standar PermenPAN-RB</span>
            </div>
        </header>

        <main class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 py-8">
            {#if page.props.flash?.success}
                <div class="mb-6 flex items-start gap-3 rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800">
                    <CircleCheck class="w-5 h-5 text-emerald-600 shrink-0 mt-0.5" />
                    <span>{page.props.flash.success}</span>
                </div>
            {/if}
            {#if page.props.flash?.error}
                <div class="mb-6 flex items-start gap-3 rounded-lg border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800">
                    <CircleAlert class="w-5 h-5 text-rose-600 shrink-0 mt-0.5" />
                    <span>{page.props.flash.error}</span>
                </div>
            {/if}
            {@render children()}
        </main>

        <footer class="border-t border-slate-200 bg-white py-4 text-center text-xs text-slate-400">
            &copy; {new Date().getFullYear()} SAKIP &mdash; Sistem Analisis &amp; Konsolidasi Kinerja Instansi Pemerintah
        </footer>
    </div>
{:else}
    <!-- Authenticated SaaS App Shell -->
    <div class="min-h-screen bg-slate-50 text-slate-900 flex flex-col md:flex-row antialiased">
        <!-- Mobile Drawer Overlay -->
        {#if isMobileOpen}
            <div
                class="fixed inset-0 z-40 bg-slate-900/40 backdrop-blur-xs md:hidden"
                onclick={closeMobile}
                onkeydown={(e) => e.key === 'Escape' && closeMobile()}
                tabindex="-1"
                role="button"
                aria-label="Tutup navigasi"
            ></div>
        {/if}

        <!-- Mobile Drawer Content -->
        <aside
            class="fixed inset-y-0 left-0 z-50 w-72 bg-white border-r border-slate-200 shadow-xl md:hidden flex flex-col justify-between transform transition-transform duration-200 ease-in-out {isMobileOpen ? 'translate-x-0' : '-translate-x-full'}"
        >
            <div class="flex flex-col h-full">
                <!-- Mobile Drawer Header -->
                <div class="h-16 px-4 border-b border-slate-200 flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-emerald-600 text-white shadow-xs">
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                <rect width="6" height="4" x="9" y="3" rx="1" />
                                <rect width="6" height="4" x="3" y="17" rx="1" />
                                <rect width="6" height="4" x="15" y="17" rx="1" />
                                <path d="M12 7v6" />
                                <path d="M6 17v-2a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v2" />
                            </svg>
                        </div>
                        <div>
                            <h1 class="text-base font-bold text-slate-900 tracking-tight leading-tight">SAKIP</h1>
                            <p class="text-[11px] text-slate-500 font-medium leading-none">Akuntabilitas Kinerja</p>
                        </div>
                    </div>
                    <button
                        type="button"
                        onclick={closeMobile}
                        class="p-1.5 text-slate-400 hover:text-slate-600 rounded-md hover:bg-slate-100"
                        aria-label="Tutup menu"
                    >
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <!-- Mobile Nav Items -->
                <nav class="p-3 space-y-1 overflow-y-auto flex-1">
                    {#each navItems as item}
                        {@const active = item.isActive(currentPath)}
                        {@const Icon = item.icon}
                        <Link
                            href={item.href}
                            onclick={closeMobile}
                            class="group flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm transition-all duration-150 relative {active ? 'bg-emerald-50 text-emerald-800 font-semibold' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100 font-medium'}"
                        >
                            {#if active}
                                <span class="absolute left-0 top-1.5 bottom-1.5 w-1 bg-emerald-600 rounded-r"></span>
                            {/if}
                            <Icon class="w-5 h-5 shrink-0 transition-colors {active ? 'text-emerald-600' : 'text-slate-400 group-hover:text-slate-600'}" />
                            <span>{item.name}</span>
                        </Link>
                    {/each}
                </nav>

                <!-- Mobile Bottom User Info -->
                <div class="p-4 border-t border-slate-200 bg-slate-50">
                    <div class="mb-3 flex items-center gap-2 text-xs text-slate-600 font-medium truncate">
                        <Building class="w-4 h-4 text-slate-400 shrink-0" />
                        <span class="truncate" title={currentAuth?.organization?.name || 'Tanpa Instansi'}>
                            {currentAuth?.organization?.name || 'Tanpa Instansi'}
                        </span>
                    </div>
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2.5 min-w-0">
                            <div class="h-8 w-8 rounded-full bg-emerald-100 text-emerald-800 font-bold text-xs flex items-center justify-center shrink-0 border border-emerald-200">
                                {getInitials(currentAuth?.user?.name)}
                            </div>
                            <div class="min-w-0">
                                <p class="text-xs font-semibold text-slate-900 truncate" title={currentAuth?.user?.name}>
                                    {currentAuth?.user?.name}
                                </p>
                                <span class="inline-block text-[10px] uppercase font-bold tracking-wider px-1.5 py-0.5 rounded bg-emerald-100/60 text-emerald-800 border border-emerald-200/50">
                                    {formatRole(currentAuth?.user?.role)}
                                </span>
                            </div>
                        </div>
                        <button
                            type="button"
                            onclick={logout}
                            class="p-2 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-md transition-colors"
                            title="Keluar"
                            aria-label="Keluar"
                        >
                            <LogOut class="w-4 h-4" />
                        </button>
                    </div>
                </div>
            </div>
        </aside>

        <!-- Desktop Collapsible Sidebar -->
        <aside
            class="hidden md:flex flex-col bg-white border-r border-slate-200/90 transition-all duration-200 ease-in-out shrink-0 select-none {isCollapsed ? 'w-20' : 'w-64'} sticky top-0 h-screen z-20"
        >
            <!-- Sidebar Header / Logo -->
            <div class="h-16 px-4 border-b border-slate-200/80 flex items-center {isCollapsed ? 'justify-center' : 'justify-between'}">
                <Link href="/dashboard" class="flex items-center gap-3 min-w-0 group">
                    <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-emerald-600 text-white shadow-xs shrink-0 group-hover:bg-emerald-500 transition-colors">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <rect width="6" height="4" x="9" y="3" rx="1" />
                            <rect width="6" height="4" x="3" y="17" rx="1" />
                            <rect width="6" height="4" x="15" y="17" rx="1" />
                            <path d="M12 7v6" />
                            <path d="M6 17v-2a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v2" />
                        </svg>
                    </div>
                    {#if !isCollapsed}
                        <div class="truncate">
                            <h1 class="text-base font-bold text-slate-900 tracking-tight leading-tight">SAKIP</h1>
                            <p class="text-[11px] text-slate-400 font-medium leading-none">Akuntabilitas Kinerja</p>
                        </div>
                    {/if}
                </Link>
                {#if !isCollapsed}
                    <button
                        type="button"
                        onclick={toggleSidebar}
                        class="p-1.5 text-slate-400 hover:text-slate-700 hover:bg-slate-100 rounded-md transition-colors"
                        title="Perkecil sidebar"
                        aria-label="Perkecil sidebar"
                    >
                        <PanelLeftClose class="w-4 h-4" />
                    </button>
                {/if}
            </div>

            {#if isCollapsed}
                <div class="flex justify-center pt-2 pb-1 border-b border-slate-100">
                    <button
                        type="button"
                        onclick={toggleSidebar}
                        class="p-1.5 text-slate-400 hover:text-slate-700 hover:bg-slate-100 rounded-md transition-colors"
                        title="Perluas sidebar"
                        aria-label="Perluas sidebar"
                    >
                        <PanelLeft class="w-4 h-4" />
                    </button>
                </div>
            {/if}

            <!-- Nav Items -->
            <nav class="flex-1 px-3 py-4 space-y-1.5 overflow-y-auto">
                {#each navItems as item}
                    {@const active = item.isActive(currentPath)}
                    {@const Icon = item.icon}
                    <Link
                        href={item.href}
                        class="group flex items-center {isCollapsed ? 'justify-center px-2 py-2.5' : 'gap-3 px-3 py-2.5'} rounded-lg text-sm transition-all duration-150 relative {active ? 'bg-emerald-50 text-emerald-800 font-semibold' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100/80 font-medium'}"
                        title={isCollapsed ? item.name : undefined}
                    >
                        {#if active}
                            <span class="absolute left-0 top-1.5 bottom-1.5 w-1 bg-emerald-600 rounded-r"></span>
                        {/if}
                        <Icon class="w-5 h-5 shrink-0 transition-colors {active ? 'text-emerald-600' : 'text-slate-400 group-hover:text-slate-600'}" />
                        {#if !isCollapsed}
                            <span class="truncate">{item.name}</span>
                        {/if}
                    </Link>
                {/each}
            </nav>

            <!-- Bottom Organization & User Profile Badge -->
            <div class="p-3 border-t border-slate-200/80 bg-slate-50/60">
                {#if !isCollapsed}
                    <div class="mb-2.5 px-2.5 py-2 rounded-lg bg-white border border-slate-200/70 shadow-2xs">
                        <div class="flex items-center gap-1.5 text-slate-400 mb-0.5">
                            <Building class="w-3.5 h-3.5 shrink-0" />
                            <span class="text-[10px] uppercase font-bold tracking-wider text-slate-400">Instansi</span>
                        </div>
                        <p class="text-xs font-semibold text-slate-800 truncate" title={currentAuth?.organization?.name || 'Tanpa Instansi'}>
                            {currentAuth?.organization?.name || 'Tanpa Instansi'}
                        </p>
                    </div>

                    <div class="flex items-center justify-between pt-0.5">
                        <div class="flex items-center gap-2.5 min-w-0">
                            <div class="h-8 w-8 rounded-full bg-emerald-100 text-emerald-800 font-bold text-xs flex items-center justify-center shrink-0 border border-emerald-200">
                                {getInitials(currentAuth?.user?.name)}
                            </div>
                            <div class="min-w-0">
                                <p class="text-xs font-semibold text-slate-900 truncate" title={currentAuth?.user?.name}>
                                    {currentAuth?.user?.name}
                                </p>
                                <span class="inline-block text-[10px] uppercase font-bold tracking-wider px-1.5 py-0.5 rounded bg-slate-200/70 text-slate-700">
                                    {formatRole(currentAuth?.user?.role)}
                                </span>
                            </div>
                        </div>

                        <button
                            type="button"
                            onclick={logout}
                            class="p-1.5 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-md transition-colors shrink-0"
                            title="Keluar"
                            aria-label="Keluar"
                        >
                            <LogOut class="w-4 h-4" />
                        </button>
                    </div>
                {:else}
                    <!-- Collapsed User View -->
                    <div class="flex flex-col items-center gap-2 py-1">
                        <div
                            class="h-8 w-8 rounded-full bg-emerald-100 text-emerald-800 font-bold text-xs flex items-center justify-center border border-emerald-200"
                            title="{currentAuth?.user?.name || 'Pengguna'} • {currentAuth?.organization?.name || 'Tanpa Instansi'} ({formatRole(currentAuth?.user?.role)})"
                        >
                            {getInitials(currentAuth?.user?.name)}
                        </div>
                        <button
                            type="button"
                            onclick={logout}
                            class="p-1.5 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-md transition-colors"
                            title="Keluar"
                            aria-label="Keluar"
                        >
                            <LogOut class="w-4 h-4" />
                        </button>
                    </div>
                {/if}
            </div>
        </aside>

        <!-- Main Content Column -->
        <div class="flex-1 flex flex-col min-w-0">
            <!-- Sticky Context Header -->
            <header class="sticky top-0 z-10 h-16 bg-white/95 backdrop-blur-sm border-b border-slate-200/80 px-4 sm:px-6 lg:px-8 flex items-center justify-between shadow-2xs">
                <div class="flex items-center gap-3 min-w-0">
                    <!-- Mobile Hamburger -->
                    <button
                        type="button"
                        onclick={toggleMobile}
                        class="md:hidden p-2 -ml-2 text-slate-500 hover:text-slate-800 hover:bg-slate-100 rounded-md transition-colors"
                        aria-label="Buka menu navigasi"
                    >
                        <Menu class="w-5 h-5" />
                    </button>

                    <!-- Breadcrumbs Trail -->
                    <nav class="flex items-center gap-1.5 text-xs sm:text-sm font-medium text-slate-500 min-w-0" aria-label="Breadcrumb">
                        {#each computedBreadcrumbs as crumb, idx}
                            {#if idx > 0}
                                <ChevronRight class="w-3.5 h-3.5 text-slate-300 shrink-0" />
                            {/if}
                            {#if idx === computedBreadcrumbs.length - 1}
                                <span class="text-slate-900 font-semibold truncate">{crumb.label}</span>
                            {:else}
                                <Link href={crumb.href} class="hover:text-slate-800 transition-colors truncate">
                                    {crumb.label}
                                </Link>
                            {/if}
                        {/each}
                    </nav>
                </div>

                <!-- Right Header Elements -->
                <div class="flex items-center gap-2 sm:gap-3 shrink-0">
                    {#if currentAuth?.organization}
                        <div class="hidden sm:flex items-center gap-1.5 px-3 py-1 rounded-full bg-slate-100 border border-slate-200 text-xs text-slate-700 font-medium max-w-[240px]">
                            <Building class="w-3.5 h-3.5 text-slate-500 shrink-0" />
                            <span class="truncate">{currentAuth.organization.name}</span>
                        </div>
                    {/if}

                    <div class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200 text-[11px] font-semibold tracking-wide uppercase">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                        <span>{formatRole(currentAuth?.user?.role)}</span>
                    </div>
                </div>
            </header>

            <!-- Page Body Area -->
            <main class="flex-1 p-4 sm:p-6 lg:p-8 max-w-7xl w-full mx-auto space-y-6">
                <!-- Flash Notification Banner -->
                {#if page.props.flash?.success}
                    <div class="flex items-start gap-3 rounded-lg border border-emerald-200 bg-emerald-50/90 p-4 text-sm text-emerald-900 shadow-2xs">
                        <CircleCheck class="w-5 h-5 text-emerald-600 shrink-0 mt-0.5" />
                        <div class="flex-1">{page.props.flash.success}</div>
                    </div>
                {/if}
                {#if page.props.flash?.error}
                    <div class="flex items-start gap-3 rounded-lg border border-rose-200 bg-rose-50/90 p-4 text-sm text-rose-900 shadow-2xs">
                        <CircleAlert class="w-5 h-5 text-rose-600 shrink-0 mt-0.5" />
                        <div class="flex-1">{page.props.flash.error}</div>
                    </div>
                {/if}

                <!-- Main Content Slot -->
                {@render children()}
            </main>
        </div>
    </div>
{/if}

