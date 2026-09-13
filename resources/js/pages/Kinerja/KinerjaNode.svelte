<script>
    import { Handle, Position } from '@xyflow/svelte';
    import { Target, Layers, Zap, Sparkles, ChevronRight, Activity } from '@lucide/svelte';

    let { id, data, selected } = $props();

    const isSelected = $derived(Boolean(selected || data?.isSelected));

    // Type configuration matching PermenPAN-RB cascading standards
    const typeConfig = {
        outcome: {
            label: 'Outcome',
            icon: Target,
            badgeClass: 'bg-emerald-50 text-emerald-800 border-emerald-200/80',
            dotClass: 'bg-emerald-500',
            borderHover: 'hover:border-emerald-500/80',
        },
        output: {
            label: 'Output',
            icon: Layers,
            badgeClass: 'bg-sky-50 text-sky-800 border-sky-200/80',
            dotClass: 'bg-sky-500',
            borderHover: 'hover:border-sky-500/80',
        },
        aktivitas: {
            label: 'Aktivitas',
            icon: Zap,
            badgeClass: 'bg-amber-50 text-amber-800 border-amber-200/80',
            dotClass: 'bg-amber-500',
            borderHover: 'hover:border-amber-500/80',
        },
    };

    let currentConfig = $derived(typeConfig[data?.type] || typeConfig.outcome);
    let targetPos = $derived(data?.targetPosition ?? Position.Top);
    let sourcePos = $derived(data?.sourcePosition ?? Position.Bottom);

    function handleClick() {
        if (data?.onSelect && data?.rawNode) {
            data.onSelect(data.rawNode);
        }
    }
</script>

<div
    class="group relative rounded-xl border bg-white p-3.5 shadow-2xs transition-all duration-150 w-[290px] text-left cursor-pointer select-none {currentConfig.borderHover} {isSelected ? 'ring-2 ring-emerald-600 border-emerald-600 shadow-md bg-emerald-50/20' : 'border-slate-200/90 hover:shadow-md'}"
    onclick={handleClick}
    onkeydown={(e) => (e.key === 'Enter' || e.key === ' ') && handleClick()}
    role="button"
    tabindex="0"
    aria-label={`Simpul ${data?.code || ''} ${data?.statement || ''}`}
>
    <!-- Handle Target (Input dari Induk/Parent) -->
    <Handle
        type="target"
        position={targetPos}
        class="!w-3 !h-3 !bg-slate-300 !border-2 !border-white group-hover:!bg-emerald-500 transition-colors"
    />

    <!-- Top Row: Type Badge + Code + Pending AI Indicator -->
    <div class="flex items-center justify-between gap-2 mb-2">
        <div class="flex items-center gap-1.5 min-w-0">
            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[10px] font-bold uppercase tracking-wider border {currentConfig.badgeClass}">
                <span class="w-1.5 h-1.5 rounded-full {currentConfig.dotClass}"></span>
                <span>{currentConfig.label}</span>
            </span>
            {#if data?.code}
                <span class="font-mono text-[11px] font-semibold text-slate-700 px-1.5 py-0.5 rounded bg-slate-100 border border-slate-200/70 truncate max-w-[100px]" title={data.code}>
                    {data.code}
                </span>
            {/if}
        </div>

        {#if data?.hasPendingAi}
            <span class="inline-flex items-center gap-0.5 px-1.5 py-0.5 rounded text-[10px] font-semibold bg-emerald-100 text-emerald-800 border border-emerald-200 animate-pulse shrink-0" title="Ada usulan AI pending">
                <Sparkles class="w-2.5 h-2.5 text-emerald-600" />
                <span>AI</span>
            </span>
        {/if}
    </div>

    <!-- Body: Statement Text -->
    <p class="text-xs font-semibold text-slate-900 leading-snug line-clamp-3 mb-3 group-hover:text-emerald-900 transition-colors">
        {data?.statement || 'Rumusan sasaran'}
    </p>

    <!-- Bottom Row: Metrics & Quick Action -->
    <div class="pt-2 border-t border-slate-100 flex items-center justify-between text-[11px] text-slate-500">
        <div class="flex items-center gap-1.5">
            <Activity class="w-3.5 h-3.5 text-slate-400" />
            <span class="font-medium tabular-nums">{data?.indicatorsCount ?? 0} Indikator</span>
        </div>
        <span class="inline-flex items-center gap-0.5 font-semibold text-emerald-700 group-hover:text-emerald-800 group-hover:translate-x-0.5 transition-transform">
            Detail <ChevronRight class="w-3 h-3" />
        </span>
    </div>

    <!-- Handle Source (Output ke Turunan/Child) -->
    <Handle
        type="source"
        position={sourcePos}
        class="!w-3 !h-3 !bg-slate-300 !border-2 !border-white group-hover:!bg-emerald-500 transition-colors"
    />
</div>
