<script>
    import { useForm, Link, router } from '@inertiajs/svelte';
    import Layout from '../Layout.svelte';
    import {
        SvelteFlow,
        Controls,
        Background,
        MiniMap,
        Panel,
        Position,
        MarkerType
    } from '@xyflow/svelte';
    import '@xyflow/svelte/dist/style.css';
    import dagre from '@dagrejs/dagre';
    import KinerjaNode from './KinerjaNode.svelte';
    import {
        Network,
        ListTree,
        Plus,
        Link2,
        Download,
        FileDown,
        Sparkles,
        Check,
        X,
        ChevronRight,
        Activity,
        Target,
        Layers,
        Zap,
        TrendingUp,
        TrendingDown,
        Minus,
        MessageSquare,
        CheckCircle2,
        AlertCircle,
        RotateCcw,
        Search,
        Filter,
        ArrowLeft,
        FileText,
        ArrowRight
    } from '@lucide/svelte';

    let { tree, flash = {} } = $props();

    // Register custom node type for SvelteFlow
    const nodeTypes = {
        kinerjaNode: KinerjaNode,
    };

    // Core derived data
    let nodes = $derived(tree.nodes || []);
    let links = $derived(tree.links || []);
    let reviews = $derived(tree.reviews || []);

    // Total indicators count
    let totalIndicators = $derived(
        nodes.reduce((acc, n) => acc + (n.indicators?.length || 0), 0)
    );

    // View mode: 'graph' (Mode Grafik Visual) | 'outline' (Mode Struktur Pohon)
    let viewMode = $state('graph');

    // Layout direction for Visual Flow: 'TB' (Top to Bottom) | 'LR' (Left to Right)
    let layoutDirection = $state('TB');

    // Drawer state
    let isDrawerOpen = $state(false);
    let activeNodeId = $state(null);
    let activeDrawerTab = $state('indicators'); // 'indicators' | 'ai' | 'reviews'

    // Currently active node in drawer
    let activeNode = $derived(nodes.find((n) => n.id === activeNodeId) || null);

    // Modals visibility
    let showAddNodeModal = $state(false);
    let showAddLinkModal = $state(false);
    let showExportModal = $state(false);

    // Outline search & filter state
    let outlineSearch = $state('');
    let outlineTypeFilter = $state('all'); // 'all' | 'outcome' | 'output' | 'aktivitas'

    // Forms
    const nodeForm = useForm({
        statement: '',
        type: 'outcome',
        code: '',
        parent_node_id: '',
        relationship_reason: '',
    });

    const linkForm = useForm({
        parent_node_id: '',
        child_node_id: '',
        reason: '',
    });

    const indicatorForm = useForm({
        name: '',
        unit: '',
        direction: 'naik',
        baseline: '',
        target: '',
        definition: '',
        data_source: '',
    });

    const reviewForm = useForm({
        node_id: '',
        decision: 'comment',
        comment: '',
    });

    // AI Recommendation states per node
    let aiChildren = $state({});
    let aiSources = $state({});
    let aiIndicators = $state({});
    let aiRecIds = $state({});
    let aiLoading = $state({}); // nodeId -> 'children' | 'indicators' | null
    let aiStep = $state({}); // nodeId -> string
    let aiError = $state({}); // nodeId -> string | null

    const indicatorSteps = [
        'Menganalisis rumusan sasaran kinerja...',
        'Mencari rujukan dokumen Renstra & Knowledge Pack sektor...',
        'Menyelaraskan indikator kinerja terukur standar PermenPAN-RB...',
        'Merumuskan usulan target, baseline, dan definisi operasional...',
    ];

    const childrenSteps = [
        'Menganalisis sasaran strategis tingkat utama...',
        'Memeriksa pohon kausalitas & kondisi esensial yang dibutuhkan...',
        'Meninjau konteks tupoksi serta standar cascading kinerja...',
        'Menyusun usulan perjenjangan sasaran turunan (outcome / output)...',
    ];

    let timerIntervals = {};

    function startThinkingSteps(nodeId, steps) {
        let stepIndex = 0;
        aiStep[nodeId] = steps[0];
        if (timerIntervals[nodeId]) {
            clearInterval(timerIntervals[nodeId]);
        }
        timerIntervals[nodeId] = setInterval(() => {
            stepIndex = (stepIndex + 1) % steps.length;
            aiStep[nodeId] = steps[stepIndex];
        }, 2200);
    }

    function stopThinkingSteps(nodeId) {
        if (timerIntervals[nodeId]) {
            clearInterval(timerIntervals[nodeId]);
            delete timerIntervals[nodeId];
        }
    }

    // Sync initial pending AI recommendations from tree prop
    $effect(() => {
        for (const n of tree.nodes || []) {
            if ((n.pending_ai_children ?? []).length > 0 && !aiChildren[n.id]) {
                aiChildren[n.id] = n.pending_ai_children;
            }
            if ((n.pending_ai_indicators ?? []).length > 0 && !aiIndicators[n.id]) {
                aiIndicators[n.id] = n.pending_ai_indicators;
            }
            if (!aiRecIds[n.id]) {
                aiRecIds[n.id] = {
                    indicatorRecId: n.pending_indicator_recommendation_id,
                    childRecId: n.pending_child_recommendation_id,
                };
            }
        }
    });

    // Dagre Graph Layout Computation
    function computeFlowLayout(rawNodes, rawLinks, direction = 'TB') {
        if (!rawNodes || rawNodes.length === 0) {
            return { nodes: [], edges: [] };
        }

        const isHorizontal = direction === 'LR';
        const dagreGraph = new dagre.graphlib.Graph();
        dagreGraph.setDefaultEdgeLabel(() => ({}));
        dagreGraph.setGraph({
            rankdir: direction,
            nodesep: isHorizontal ? 50 : 40,
            ranksep: isHorizontal ? 80 : 70,
            marginx: 40,
            marginy: 40,
        });

        const nodeWidth = 290;
        const nodeHeight = 140;

        rawNodes.forEach((node) => {
            dagreGraph.setNode(String(node.id), { width: nodeWidth, height: nodeHeight });
        });

        rawLinks.forEach((link) => {
            if (
                rawNodes.some((n) => n.id === link.parent_node_id) &&
                rawNodes.some((n) => n.id === link.child_node_id)
            ) {
                dagreGraph.setEdge(String(link.parent_node_id), String(link.child_node_id));
            }
        });

        dagre.layout(dagreGraph);

        const layoutedNodes = rawNodes.map((node) => {
            const nodeWithPosition = dagreGraph.node(String(node.id)) || { x: 0, y: 0 };
            const isSelected = activeNodeId === node.id;
            // Jika user sudah pernah memindahkan simpul (drag), pakai posisi tersimpan.
            // Jika belum, pakai hasil auto-layout dagre.
            const hasSavedPosition = node.pos_x !== null && node.pos_x !== undefined
                && node.pos_y !== null && node.pos_y !== undefined;
            return {
                id: String(node.id),
                type: 'kinerjaNode',
                targetPosition: isHorizontal ? Position.Left : Position.Top,
                sourcePosition: isHorizontal ? Position.Right : Position.Bottom,
                selected: isSelected,
                position: hasSavedPosition
                    ? { x: node.pos_x, y: node.pos_y }
                    : {
                        x: nodeWithPosition.x - nodeWidth / 2,
                        y: nodeWithPosition.y - nodeHeight / 2,
                    },
                data: {
                    rawNode: node,
                    code: node.code,
                    statement: node.statement,
                    type: node.type,
                    source_type: node.source_type,
                    indicatorsCount: (node.indicators || []).length,
                    indicators: node.indicators || [],
                    hasPendingAi:
                        (node.pending_ai_children?.length || 0) > 0 ||
                        (node.pending_ai_indicators?.length || 0) > 0,
                    targetPosition: isHorizontal ? Position.Left : Position.Top,
                    sourcePosition: isHorizontal ? Position.Right : Position.Bottom,
                    isSelected,
                    onSelect: (selectedRaw) => openDrawer(selectedRaw),
                },
            };
        });

        const layoutedEdges = rawLinks
            .filter(
                (l) =>
                    rawNodes.some((n) => n.id === l.parent_node_id) &&
                    rawNodes.some((n) => n.id === l.child_node_id)
            )
            .map((link) => ({
                id: `e-${link.parent_node_id}-${link.child_node_id}`,
                source: String(link.parent_node_id),
                target: String(link.child_node_id),
                label: link.reason || undefined,
                type: 'smoothstep',
                style: 'stroke: #64748b; stroke-width: 1.5px;',
                markerEnd: {
                    type: MarkerType.ArrowClosed,
                    color: '#64748b',
                    width: 14,
                    height: 14,
                },
                labelStyle: 'fill: #334155; font-size: 11px; font-weight: 600;',
                labelBgStyle: 'fill: #ffffff; fill-opacity: 0.96; stroke: #cbd5e1; stroke-width: 1px;',
                labelBgPadding: [4, 8],
                labelBgBorderRadius: 6,
            }));

        return { nodes: layoutedNodes, edges: layoutedEdges };
    }

    // Reactive Flow State
    let flowNodes = $state([]);
    let flowEdges = $state([]);

    function syncFlowGraph() {
        const layout = computeFlowLayout(nodes, links, layoutDirection);
        flowNodes = layout.nodes;
        flowEdges = layout.edges;
    }

    $effect(() => {
        // Track dependencies
        nodes;
        links;
        layoutDirection;
        activeNodeId;
        syncFlowGraph();
    });

    // Hierarchical Outline Mode Computation
    function buildHierarchicalOutline(allNodes, allLinks) {
        if (!allNodes || allNodes.length === 0) return [];

        const nodesMap = new Map(allNodes.map((n) => [n.id, n]));
        const childrenMap = new Map();
        const parentLinksMap = new Map();
        const inDegree = new Map(allNodes.map((n) => [n.id, 0]));

        allLinks.forEach((link) => {
            if (nodesMap.has(link.parent_node_id) && nodesMap.has(link.child_node_id)) {
                if (!childrenMap.has(link.parent_node_id)) {
                    childrenMap.set(link.parent_node_id, []);
                }
                childrenMap.get(link.parent_node_id).push(link);

                if (!parentLinksMap.has(link.child_node_id)) {
                    parentLinksMap.set(link.child_node_id, []);
                }
                parentLinksMap.get(link.child_node_id).push(link);

                inDegree.set(link.child_node_id, (inDegree.get(link.child_node_id) || 0) + 1);
            }
        });

        // Root nodes have 0 incoming parent links
        let rootNodes = allNodes.filter((n) => (inDegree.get(n.id) || 0) === 0);
        if (rootNodes.length === 0 && allNodes.length > 0) {
            rootNodes = [allNodes[0]];
        }

        const rows = [];
        const visitedInBranch = new Set();
        const allVisited = new Set();

        function traverse(node, depth, parentLink = null) {
            if (visitedInBranch.has(node.id)) return;

            visitedInBranch.add(node.id);
            allVisited.add(node.id);

            const childLinks = childrenMap.get(node.id) || [];
            rows.push({
                node,
                depth,
                parentLink,
                hasChildren: childLinks.length > 0,
                childrenCount: childLinks.length,
                parentCount: (parentLinksMap.get(node.id) || []).length,
                parentNames: (parentLinksMap.get(node.id) || [])
                    .map((l) => nodesMap.get(l.parent_node_id)?.statement)
                    .filter(Boolean),
            });

            childLinks.forEach((link) => {
                const childNode = nodesMap.get(link.child_node_id);
                if (childNode) {
                    traverse(childNode, depth + 1, link);
                }
            });

            visitedInBranch.delete(node.id);
        }

        rootNodes.forEach((root) => {
            traverse(root, 0, null);
        });

        // Add disconnected/orphan nodes
        allNodes.forEach((node) => {
            if (!allVisited.has(node.id)) {
                traverse(node, 0, null);
            }
        });

        return rows;
    }

    let outlineRows = $derived(buildHierarchicalOutline(nodes, links));

    // Filtered outline rows based on search and type selector
    let filteredOutlineRows = $derived.by(() => {
        let res = outlineRows;
        if (outlineTypeFilter !== 'all') {
            res = res.filter((r) => r.node.type === outlineTypeFilter);
        }
        if (outlineSearch.trim()) {
            const q = outlineSearch.toLowerCase().trim();
            res = res.filter(
                (r) =>
                    r.node.statement?.toLowerCase().includes(q) ||
                    r.node.code?.toLowerCase().includes(q) ||
                    r.node.indicators?.some((i) => i.name?.toLowerCase().includes(q))
            );
        }
        return res;
    });

    // Drawer Management
    function openDrawer(node) {
        if (!node) return;
        activeNodeId = node.id;
        isDrawerOpen = true;
    }

    function closeDrawer() {
        isDrawerOpen = false;
        activeNodeId = null;
    }

    // Modal Triggers
    function openAddNodeModal(prefilledParentId = '') {
        nodeForm.reset();
        if (prefilledParentId) {
            nodeForm.parent_node_id = String(prefilledParentId);
        }
        showAddNodeModal = true;
    }

    function openAddLinkModal(prefilledParentId = '') {
        linkForm.reset();
        if (prefilledParentId) {
            linkForm.parent_node_id = String(prefilledParentId);
        }
        showAddLinkModal = true;
    }

    // Form Submissions
    function submitAddNode(e) {
        e?.preventDefault();
        nodeForm.post(`/kinerja/${tree.id}/nodes`, {
            preserveScroll: true,
            onSuccess: () => {
                nodeForm.reset();
                showAddNodeModal = false;
            },
        });
    }

    function submitAddLink(e) {
        e?.preventDefault();
        linkForm.post(`/kinerja/${tree.id}/links`, {
            preserveScroll: true,
            onSuccess: () => {
                linkForm.reset();
                showAddLinkModal = false;
            },
        });
    }

    function submitAddIndicator(e) {
        e?.preventDefault();
        if (!activeNode) return;
        indicatorForm.post(`/kinerja/${tree.id}/nodes/${activeNode.id}/indicators`, {
            preserveScroll: true,
            onSuccess: () => {
                indicatorForm.reset();
            },
        });
    }

    function submitReview(e) {
        e?.preventDefault();
        if (!activeNode) return;
        reviewForm.node_id = activeNode.id;
        reviewForm.post(`/kinerja/${tree.id}/reviews`, {
            preserveScroll: true,
            onSuccess: () => {
                reviewForm.reset('comment');
            },
        });
    }

    // AI Recommendations Requests
    async function askChildren(nodeId) {
        aiError[nodeId] = null;
        aiLoading[nodeId] = 'children';
        startThinkingSteps(nodeId, childrenSteps);
        try {
            const res = await fetch(`/kinerja/${tree.id}/nodes/${nodeId}/ai/children`, {
                method: 'POST',
                headers: {
                    'X-Inertia': 'false',
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-XSRF-TOKEN': csrf(),
                },
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok) {
                throw new Error(
                    data.message || `Gagal menganalisis sasaran turunan (HTTP ${res.status})`
                );
            }
            aiChildren[nodeId] = data.recommendations ?? [];
            aiSources[nodeId] = data.sources ?? [];
            if (data.recommendation_id) {
                if (!aiRecIds[nodeId]) aiRecIds[nodeId] = {};
                aiRecIds[nodeId].childRecId = data.recommendation_id;
            }
            if ((data.recommendations ?? []).length === 0) {
                aiError[nodeId] = 'AI tidak menemukan rekomendasi sasaran turunan tambahan.';
            }
        } catch (err) {
            aiError[nodeId] = err.message || 'Terjadi kesalahan saat memproses usulan turunan.';
        } finally {
            stopThinkingSteps(nodeId);
            aiLoading[nodeId] = null;
        }
    }

    async function askIndicators(nodeId) {
        aiError[nodeId] = null;
        aiLoading[nodeId] = 'indicators';
        startThinkingSteps(nodeId, indicatorSteps);
        try {
            const res = await fetch(`/kinerja/${tree.id}/nodes/${nodeId}/ai/indicators`, {
                method: 'POST',
                headers: {
                    'X-Inertia': 'false',
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-XSRF-TOKEN': csrf(),
                },
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok) {
                throw new Error(
                    data.message || `Gagal menganalisis usulan indikator (HTTP ${res.status})`
                );
            }
            aiIndicators[nodeId] = data.indicators ?? [];
            if (data.recommendation_id) {
                if (!aiRecIds[nodeId]) aiRecIds[nodeId] = {};
                aiRecIds[nodeId].indicatorRecId = data.recommendation_id;
            }
            if ((data.indicators ?? []).length === 0) {
                aiError[nodeId] = 'AI tidak menemukan rekomendasi indikator baru.';
            }
        } catch (err) {
            aiError[nodeId] = err.message || 'Terjadi kesalahan saat memproses usulan indikator.';
        } finally {
            stopThinkingSteps(nodeId);
            aiLoading[nodeId] = null;
        }
    }

    async function acceptChild(nodeId, rec) {
        try {
            const res = await fetch(`/kinerja/${tree.id}/nodes/${nodeId}/ai/children/accept`, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-XSRF-TOKEN': csrf(),
                },
                body: JSON.stringify({
                    statement: rec.statement,
                    relationship_reason: rec.relationship_reason,
                    recommendation_id: aiRecIds[nodeId]?.childRecId,
                }),
            });
            if (!res.ok) {
                const data = await res.json().catch(() => ({}));
                alert(data.message || 'Gagal menerima usulan sasaran turunan.');
                return;
            }
            router.reload({ preserveScroll: true });
        } catch (err) {
            alert('Gagal menerima usulan sasaran: ' + (err.message || err));
        }
    }

    async function acceptIndicator(nodeId, ind) {
        try {
            const res = await fetch(`/kinerja/${tree.id}/nodes/${nodeId}/ai/indicators/accept`, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-XSRF-TOKEN': csrf(),
                },
                body: JSON.stringify({
                    name: ind.name,
                    definition: ind.definition,
                    unit: ind.unit,
                    direction: ind.direction,
                    data_source: ind.data_source,
                    recommendation_id: aiRecIds[nodeId]?.indicatorRecId,
                }),
            });
            if (!res.ok) {
                const data = await res.json().catch(() => ({}));
                alert(data.message || 'Gagal menerima usulan indikator.');
                return;
            }
            router.reload({ preserveScroll: true });
        } catch (err) {
            alert('Gagal menerima usulan indikator: ' + (err.message || err));
        }
    }

    async function dismissAi(nodeId, kind) {
        try {
            if (kind === 'indicators') {
                aiIndicators[nodeId] = [];
            } else {
                aiChildren[nodeId] = [];
            }
            await fetch(`/kinerja/${tree.id}/nodes/${nodeId}/ai/dismiss`, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-XSRF-TOKEN': csrf(),
                },
                body: JSON.stringify({
                    kind: kind === 'indicators' ? 'recommend_indicators' : 'recommend_children',
                }),
            });
        } catch (err) {
            console.error('Gagal mengabaikan rekomendasi AI:', err);
        }
    }

    function csrf() {
        if (typeof document === 'undefined') return '';
        const m = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
        return m ? decodeURIComponent(m[1]) : '';
    }

    /**
     * Dipanggil saat user menarik garis dari satu simpul ke simpul lain.
     * Membuat hubungan parent -> child lewat endpoint yang sudah ada
     * (validasi anti-siklus tetap berlaku di backend).
     */
    function onConnect(connection) {
        if (!connection?.source || !connection?.target) return;
        if (connection.source === connection.target) {
            toastError('Simpul tidak bisa dihubungkan ke dirinya sendiri.');
            return;
        }

        fetch(`/kinerja/${tree.id}/links`, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf(),
            },
            body: JSON.stringify({
                parent_node_id: Number(connection.source),
                child_node_id: Number(connection.target),
            }),
        })
            .then(async (res) => {
                if (res.ok) {
                    router.reload({ only: ['tree'] });
                } else if (res.status === 422 || res.status === 302) {
                    router.reload({ only: ['tree'] });
                } else {
                    const data = await res.json().catch(() => ({}));
                    toastError(data.message || 'Gagal membuat hubungan (mungkin membentuk siklus).');
                }
            })
            .catch(() => toastError('Terjadi kesalahan saat menghubungkan simpul.'));
    }

    /** Simpan posisi simpul setelah user selesai menggeser (drag). */
    let savePositionTimer = null;
    function onNodeDragStop({ nodes }) {
        if (!nodes?.length) return;

        // Debounce: simpan sekali setelah user berhenti menggeser.
        clearTimeout(savePositionTimer);
        savePositionTimer = setTimeout(() => {
            const positions = nodes.map((n) => ({
                id: Number(n.id),
                pos_x: Math.round(n.position.x),
                pos_y: Math.round(n.position.y),
            }));

            fetch(`/kinerja/${tree.id}/positions`, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf(),
                },
                body: JSON.stringify({ positions }),
            }).catch(() => {
                /* senyap: posisi gagal tersimpan bukan kesalahan kritis */
            });
        }, 400);
    }

    function toastError(message) {
        if (typeof window !== 'undefined' && window.alert) {
            window.alert(message);
        }
    }

    function handleGlobalKeyDown(e) {
        if (e.key === 'Escape') {
            if (isDrawerOpen) closeDrawer();
            if (showAddNodeModal) showAddNodeModal = false;
            if (showAddLinkModal) showAddLinkModal = false;
            if (showExportModal) showExportModal = false;
        }
    }

    // Node type helpers
    const typeBadges = {
        outcome: {
            label: 'Outcome',
            badge: 'bg-emerald-50 text-emerald-800 border-emerald-200/80',
            dot: 'bg-emerald-500',
        },
        output: {
            label: 'Output',
            badge: 'bg-sky-50 text-sky-800 border-sky-200/80',
            dot: 'bg-sky-500',
        },
        aktivitas: {
            label: 'Aktivitas',
            badge: 'bg-amber-50 text-amber-800 border-amber-200/80',
            dot: 'bg-amber-500',
        },
    };

    function formatSourceType(source) {
        switch (source) {
            case 'approved':
                return { label: 'Disetujui', class: 'bg-emerald-100 text-emerald-800 border-emerald-200' };
            case 'ai_proposed':
                return { label: 'Usulan AI', class: 'bg-emerald-50 text-emerald-800 border-emerald-200' };
            case 'extracted':
                return { label: 'Ekstraksi Renstra', class: 'bg-slate-100 text-slate-700 border-slate-200' };
            default:
                return { label: 'Manual Pengguna', class: 'bg-slate-100 text-slate-700 border-slate-200' };
        }
    }

    function formatDirection(dir) {
        switch (dir) {
            case 'naik':
                return { label: 'Semakin Tinggi Baik', icon: TrendingUp, class: 'text-emerald-700 bg-emerald-50 border-emerald-200' };
            case 'turun':
                return { label: 'Semakin Rendah Baik', icon: TrendingDown, class: 'text-rose-700 bg-rose-50 border-rose-200' };
            default:
                return { label: 'Tetap Terkendali', icon: Minus, class: 'text-slate-700 bg-slate-100 border-slate-200' };
        }
    }

    function formatTreeStatus(status) {
        switch (status) {
            case 'approved':
                return { label: 'Disetujui', class: 'bg-emerald-100 text-emerald-800 border-emerald-200' };
            case 'reviewed':
                return { label: 'Tereviu', class: 'bg-sky-100 text-sky-800 border-sky-200' };
            default:
                return { label: 'Draft Rancangan', class: 'bg-slate-100 text-slate-700 border-slate-200' };
        }
    }

    // Helper to find parents and children of activeNode
    let activeParents = $derived.by(() => {
        if (!activeNode) return [];
        const parentLinkObjects = links.filter((l) => l.child_node_id === activeNode.id);
        return parentLinkObjects.map((l) => ({
            node: nodes.find((n) => n.id === l.parent_node_id),
            reason: l.reason,
            linkId: l.id,
        })).filter((p) => Boolean(p.node));
    });

    let activeChildren = $derived.by(() => {
        if (!activeNode) return [];
        const childLinkObjects = links.filter((l) => l.parent_node_id === activeNode.id);
        return childLinkObjects.map((l) => ({
            node: nodes.find((n) => n.id === l.child_node_id),
            reason: l.reason,
            linkId: l.id,
        })).filter((c) => Boolean(c.node));
    });

    let activeReviews = $derived.by(() => {
        if (!activeNode) return [];
        return reviews.filter((r) => r.node_id === activeNode.id);
    });

    let treeStatusInfo = $derived(formatTreeStatus(tree.status));
    let activeTypeInfo = $derived(activeNode ? (typeBadges[activeNode.type] || typeBadges.outcome) : null);
    let activeSrcInfo = $derived(activeNode ? formatSourceType(activeNode.source_type) : null);
</script>

<svelte:window onkeydown={handleGlobalKeyDown} />

<Layout title={tree.name}>
    <div class="space-y-6">
        <!-- 1. HEADER & CONTEXT BAR -->
        <div class="bg-white rounded-xl border border-slate-200/80 p-5 sm:p-6 shadow-xs">
            <!-- Top Row: Back link & Sektor -->
            <div class="flex flex-wrap items-center justify-between gap-3 mb-3">
                <Link
                    href="/kinerja"
                    class="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-600 hover:text-emerald-700 transition-colors"
                >
                    <ArrowLeft class="w-4 h-4" />
                    <span>Kembali ke Daftar Pohon Kinerja</span>
                </Link>

                <div class="flex flex-wrap items-center gap-2">
                    {#if tree.sector}
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-xs font-semibold bg-emerald-50 text-emerald-800 border border-emerald-200/80">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-600"></span>
                            <span>Sektor: {tree.sector.name}</span>
                        </span>
                    {:else}
                        <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-medium bg-slate-100 text-slate-600 border border-slate-200">
                            Semua Sektor / Umum
                        </span>
                    {/if}

                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold border {treeStatusInfo.class}">
                        {treeStatusInfo.label}
                    </span>
                </div>
            </div>

            <!-- Middle Row: Title & Metrics -->
            <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4 pt-2 border-t border-slate-100">
                <div>
                    <h1 class="text-xl sm:text-2xl font-bold text-slate-900 tracking-tight">
                        {tree.name}
                    </h1>
                    <div class="flex flex-wrap items-center gap-3 mt-1.5 text-xs text-slate-500 font-medium">
                        <span class="tabular-nums font-semibold text-slate-700">{nodes.length}</span> Sasaran Kinerja
                        <span>·</span>
                        <span class="tabular-nums font-semibold text-slate-700">{links.length}</span> Hubungan Kausal
                        <span>·</span>
                        <span class="tabular-nums font-semibold text-slate-700">{totalIndicators}</span> Indikator Terukur
                        {#if tree.period_start || tree.period_end}
                            <span>·</span>
                            <span>Periode {tree.period_start ?? '—'}–{tree.period_end ?? '—'}</span>
                        {/if}
                    </div>
                </div>

                <!-- Right Action Buttons -->
                <div class="flex flex-wrap items-center gap-2.5">
                    <button
                        type="button"
                        onclick={() => openAddNodeModal('')}
                        class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-lg bg-emerald-600 text-white text-xs font-semibold hover:bg-emerald-500 shadow-2xs transition-colors"
                    >
                        <Plus class="w-4 h-4" />
                        <span>Tambah Sasaran</span>
                    </button>

                    <button
                        type="button"
                        onclick={() => openAddLinkModal('')}
                        disabled={nodes.length < 2}
                        class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-lg bg-white border border-slate-200 text-slate-700 text-xs font-semibold hover:bg-slate-50 hover:border-slate-300 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                    >
                        <Link2 class="w-4 h-4 text-slate-500" />
                        <span>Hubungkan Node</span>
                    </button>

                    <button
                        type="button"
                        onclick={() => { showExportModal = true; }}
                        class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg bg-white border border-slate-200 text-slate-700 text-xs font-semibold hover:bg-slate-50 hover:border-slate-300 transition-colors"
                    >
                        <Download class="w-4 h-4 text-slate-500" />
                        <span>Ekspor Dokumen</span>
                    </button>
                </div>
            </div>

            <!-- Bottom Row: View Mode Segmented Switcher & Layout Controls -->
            <div class="flex flex-wrap items-center justify-between gap-3 mt-5 pt-4 border-t border-slate-100">
                <!-- Dual-Mode Toggle -->
                <div class="inline-flex p-1 rounded-lg bg-slate-100 border border-slate-200/80">
                    <button
                        type="button"
                        onclick={() => { viewMode = 'graph'; }}
                        class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-md text-xs font-semibold transition-all {viewMode === 'graph' ? 'bg-white text-emerald-800 shadow-2xs' : 'text-slate-600 hover:text-slate-900'}"
                    >
                        <Network class="w-4 h-4 {viewMode === 'graph' ? 'text-emerald-600' : 'text-slate-400'}" />
                        <span>Mode Grafik Visual</span>
                    </button>

                    <button
                        type="button"
                        onclick={() => { viewMode = 'outline'; }}
                        class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-md text-xs font-semibold transition-all {viewMode === 'outline' ? 'bg-white text-emerald-800 shadow-2xs' : 'text-slate-600 hover:text-slate-900'}"
                    >
                        <ListTree class="w-4 h-4 {viewMode === 'outline' ? 'text-emerald-600' : 'text-slate-400'}" />
                        <span>Mode Struktur Pohon</span>
                    </button>
                </div>

                <!-- Auxiliary Controls based on Mode -->
                {#if viewMode === 'graph'}
                    <div class="flex items-center gap-2">
                        <span class="text-xs text-slate-500 font-medium hidden sm:inline">Arah Alur:</span>
                        <div class="inline-flex rounded-lg border border-slate-200 bg-white p-0.5 shadow-2xs text-xs">
                            <button
                                type="button"
                                onclick={() => { layoutDirection = 'TB'; }}
                                class="px-2.5 py-1 rounded-md font-medium transition-colors {layoutDirection === 'TB' ? 'bg-emerald-50 text-emerald-800 font-semibold' : 'text-slate-600 hover:bg-slate-50'}"
                            >
                                ⬇ Atas-Bawah
                            </button>
                            <button
                                type="button"
                                onclick={() => { layoutDirection = 'LR'; }}
                                class="px-2.5 py-1 rounded-md font-medium transition-colors {layoutDirection === 'LR' ? 'bg-emerald-50 text-emerald-800 font-semibold' : 'text-slate-600 hover:bg-slate-50'}"
                            >
                                ➡ Kiri-Kanan
                            </button>
                        </div>

                        <button
                            type="button"
                            onclick={syncFlowGraph}
                            class="p-1.5 rounded-lg border border-slate-200 bg-white text-slate-600 hover:text-emerald-700 hover:bg-slate-50 shadow-2xs transition-colors"
                            title="Tata Ulang Otomatis (Auto-Layout)"
                        >
                            <RotateCcw class="w-4 h-4" />
                        </button>
                    </div>
                {:else}
                    <div class="flex items-center gap-2 text-xs text-slate-500 font-medium">
                        <span>Menampilkan</span>
                        <span class="font-bold text-slate-800 tabular-nums">{filteredOutlineRows.length}</span>
                        <span>dari {outlineRows.length} simpul pohon</span>
                    </div>
                {/if}
            </div>
        </div>

        <!-- 2. MAIN CONTENT CANVAS (DUAL-MODE) -->
        {#if viewMode === 'graph'}
            <!-- VISUAL FLOW GRAPH MODE (@xyflow/svelte) -->
            <div class="relative w-full h-[720px] rounded-xl border border-slate-200/90 bg-slate-50/60 shadow-xs overflow-hidden">
                {#if nodes.length === 0}
                    <div class="absolute inset-0 flex flex-col items-center justify-center p-6 text-center z-10">
                        <div class="h-14 w-14 rounded-2xl bg-emerald-50 border border-emerald-200 flex items-center justify-center text-emerald-600 mb-3 shadow-2xs">
                            <Network class="w-7 h-7" />
                        </div>
                        <h3 class="text-base font-bold text-slate-900">Pohon Kinerja Masih Kosong</h3>
                        <p class="text-xs text-slate-500 max-w-md mt-1 mb-4">
                            Mulai rancang pohon kinerja cascading Anda dengan mendefinisikan Sasaran Strategis utama (Outcome) sesuai standar PermenPAN-RB.
                        </p>
                        <button
                            type="button"
                            onclick={() => openAddNodeModal('')}
                            class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg bg-emerald-600 text-white text-xs font-semibold hover:bg-emerald-500 shadow-2xs transition-colors"
                        >
                            <Plus class="w-4 h-4" />
                            <span>Tambah Sasaran Pertama</span>
                        </button>
                    </div>
                {:else}
                    <SvelteFlow
                        bind:nodes={flowNodes}
                        bind:edges={flowEdges}
                        {nodeTypes}
                        fitView
                        minZoom={0.2}
                        maxZoom={2}
                        class="bg-slate-50/40"
                        onconnect={onConnect}
                        onnodedragstop={onNodeDragStop}
                        nodesDraggable={true}
                        nodesConnectable={true}
                    >
                        <Background variant="dots" gap={20} size={1} color="#cbd5e1" />
                        <Controls class="!bg-white !border !border-slate-200 !rounded-lg !shadow-xs" />
                        <MiniMap
                            class="!rounded-lg !border !border-slate-200 !shadow-xs overflow-hidden hidden sm:block"
                            maskColor="rgba(241, 245, 249, 0.75)"
                        />
                        <Panel position="top-left" class="bg-white/90 backdrop-blur-xs border border-slate-200 rounded-lg p-2 shadow-2xs text-[11px] text-slate-500 flex items-center gap-3">
                            <div class="flex items-center gap-1.5">
                                <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                                <span class="font-medium text-slate-700">Outcome</span>
                            </div>
                            <div class="flex items-center gap-1.5">
                                <span class="w-2 h-2 rounded-full bg-sky-500"></span>
                                <span class="font-medium text-slate-700">Output</span>
                            </div>
                            <div class="flex items-center gap-1.5">
                                <span class="w-2 h-2 rounded-full bg-amber-500"></span>
                                <span class="font-medium text-slate-700">Aktivitas</span>
                            </div>
                            <span class="text-slate-300">|</span>
                            <span>Klik kartu simpul untuk membuka detail &amp; AI</span>
                        </Panel>
                    </SvelteFlow>
                {/if}
            </div>
        {:else}
            <!-- STRUCTURED HIERARCHICAL OUTLINE MODE -->
            <div class="bg-white rounded-xl border border-slate-200/90 shadow-xs overflow-hidden">
                <!-- Search and Filtering Toolbar -->
                <div class="p-4 border-b border-slate-100 bg-slate-50/50 flex flex-col sm:flex-row items-center justify-between gap-3">
                    <div class="relative w-full sm:w-80">
                        <Search class="w-4 h-4 absolute left-3 top-2.5 text-slate-400" />
                        <input
                            type="text"
                            bind:value={outlineSearch}
                            placeholder="Cari rumusan sasaran, kode, indikator..."
                            class="w-full pl-9 pr-3 py-1.5 rounded-lg border border-slate-200 text-xs bg-white focus:outline-emerald-600 focus:border-emerald-600"
                        />
                    </div>

                    <div class="flex items-center gap-2 w-full sm:w-auto">
                        <Filter class="w-4 h-4 text-slate-400 shrink-0" />
                        <select
                            bind:value={outlineTypeFilter}
                            class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs bg-white font-medium text-slate-700 focus:outline-emerald-600"
                        >
                            <option value="all">Semua Tingkatan Hierarki</option>
                            <option value="outcome">Hanya Outcome</option>
                            <option value="output">Hanya Output</option>
                            <option value="aktivitas">Hanya Aktivitas</option>
                        </select>
                    </div>
                </div>

                <!-- Hierarchical Tree Table -->
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-slate-50 border-b border-slate-200/80 text-slate-600 font-semibold uppercase tracking-wider">
                            <tr>
                                <th class="py-3 px-4 w-[48%]">Sasaran Kinerja &amp; Cascading Kausal</th>
                                <th class="py-3 px-4 w-[30%]">Indikator Kinerja Terukur</th>
                                <th class="py-3 px-4 w-[12%]">Sumber &amp; Status</th>
                                <th class="py-3 px-4 w-[10%] text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            {#if filteredOutlineRows.length === 0}
                                <tr>
                                    <td colspan="4" class="py-12 text-center text-slate-500">
                                        <div class="h-10 w-10 rounded-full bg-slate-100 text-slate-400 mx-auto flex items-center justify-center mb-2">
                                            <ListTree class="w-5 h-5" />
                                        </div>
                                        <p class="text-sm font-semibold text-slate-800">Tidak ada sasaran yang cocok</p>
                                        <p class="text-xs text-slate-400 mt-0.5">Ubah kata kunci pencarian atau tambah sasaran baru.</p>
                                    </td>
                                </tr>
                            {:else}
                                {#each filteredOutlineRows as row (row.node.id)}
                                    {@const node = row.node}
                                    {@const typeInfo = typeBadges[node.type] || typeBadges.outcome}
                                    {@const isSelected = activeNodeId === node.id}
                                    {@const srcInfo = formatSourceType(node.source_type)}
                                    <tr class="hover:bg-slate-50/80 transition-colors group {isSelected ? 'bg-emerald-50/30' : ''}">
                                        <!-- Column 1: Cascading Hierarchy & Statement -->
                                        <td class="py-3.5 pr-4 align-top" style="padding-left: {16 + row.depth * 28}px">
                                            <div class="flex items-start gap-2.5">
                                                <!-- Branch Line Marker -->
                                                {#if row.depth > 0}
                                                    <span class="text-slate-300 font-mono text-sm leading-none mt-1 shrink-0">↳</span>
                                                {/if}

                                                <div class="space-y-1.5 min-w-0 flex-1">
                                                    <div class="flex flex-wrap items-center gap-1.5">
                                                        <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider border {typeInfo.badge}">
                                                            <span class="w-1.5 h-1.5 rounded-full {typeInfo.dot}"></span>
                                                            <span>{typeInfo.label}</span>
                                                        </span>

                                                        {#if node.code}
                                                            <span class="font-mono text-[10px] font-semibold text-slate-700 px-1.5 py-0.5 rounded bg-slate-100 border border-slate-200">
                                                                {node.code}
                                                            </span>
                                                        {/if}

                                                        {#if (node.pending_ai_children?.length || 0) > 0 || (node.pending_ai_indicators?.length || 0) > 0}
                                                            <span class="inline-flex items-center gap-0.5 px-1.5 py-0.5 rounded text-[10px] font-semibold bg-emerald-100 text-emerald-800 border border-emerald-200">
                                                                <Sparkles class="w-2.5 h-2.5 text-emerald-600" />
                                                                <span>Ada Usulan AI</span>
                                                            </span>
                                                        {/if}
                                                    </div>

                                                    <button
                                                        type="button"
                                                        onclick={() => openDrawer(node)}
                                                        class="text-left font-semibold text-slate-900 group-hover:text-emerald-800 transition-colors block text-xs leading-relaxed"
                                                    >
                                                        {node.statement}
                                                    </button>

                                                    {#if row.parentLink?.reason}
                                                        <div class="text-[11px] text-slate-500 bg-slate-50 px-2 py-1 rounded border border-slate-100 font-normal">
                                                            <span class="font-medium text-slate-700">Kausalitas:</span> {row.parentLink.reason}
                                                        </div>
                                                    {/if}
                                                </div>
                                            </div>
                                        </td>

                                        <!-- Column 2: Indicators Summary with Tabular-Nums -->
                                        <td class="py-3.5 px-4 align-top">
                                            {#if node.indicators && node.indicators.length > 0}
                                                <div class="space-y-1.5">
                                                    {#each node.indicators as ind (ind.id)}
                                                        {@const dirInfo = formatDirection(ind.direction)}
                                                        {@const DirIcon = dirInfo.icon}
                                                        <div class="flex items-center justify-between gap-2 p-1.5 rounded-md bg-slate-50/70 border border-slate-100">
                                                            <span class="font-medium text-slate-800 truncate" title={ind.name}>
                                                                {ind.name}
                                                            </span>
                                                            <div class="flex items-center gap-2 shrink-0">
                                                                {#if ind.unit}
                                                                    <span class="text-[10px] text-slate-500 font-mono">
                                                                        {ind.unit}
                                                                    </span>
                                                                {/if}
                                                                {#if ind.target}
                                                                    <span class="font-mono text-[11px] font-semibold text-emerald-800 tabular-nums bg-emerald-50 px-1.5 py-0.5 rounded border border-emerald-100">
                                                                        {ind.target}
                                                                    </span>
                                                                {/if}
                                                            </div>
                                                        </div>
                                                    {/each}
                                                </div>
                                            {:else}
                                                <span class="text-slate-400 italic">Belum ada indikator</span>
                                            {/if}
                                        </td>

                                        <!-- Column 3: Source & Review Status -->
                                        <td class="py-3.5 px-4 align-top">
                                            <div class="space-y-1">
                                                <span class="inline-block px-2 py-0.5 rounded text-[10px] font-semibold border {srcInfo.class}">
                                                    {srcInfo.label}
                                                </span>
                                                <div class="text-[11px] text-slate-400">
                                                    {(node.indicators || []).length} indikator
                                                </div>
                                            </div>
                                        </td>

                                        <!-- Column 4: Quick Action -->
                                        <td class="py-3.5 px-4 align-top text-right space-y-1">
                                            <button
                                                type="button"
                                                onclick={() => openDrawer(node)}
                                                class="inline-flex items-center gap-1 px-2.5 py-1 rounded-md bg-emerald-50 text-emerald-700 hover:bg-emerald-100 font-semibold transition-colors text-[11px]"
                                            >
                                                <span>Detail</span>
                                                <ChevronRight class="w-3 h-3" />
                                            </button>

                                            <button
                                                type="button"
                                                onclick={() => openAddNodeModal(node.id)}
                                                class="inline-flex items-center gap-1 px-2.5 py-1 rounded-md bg-slate-100 text-slate-700 hover:bg-slate-200 font-semibold transition-colors text-[11px] block w-full text-center justify-center"
                                                title="Tambah Sasaran Turunan dari simpul ini"
                                            >
                                                <Plus class="w-3 h-3" />
                                                <span>Turunan</span>
                                            </button>
                                        </td>
                                    </tr>
                                {/each}
                            {/if}
                        </tbody>
                    </table>
                </div>
            </div>
        {/if}
    </div>

    <!-- 3. SLIDE-OVER DRAWER (NODE DETAIL & AI ASSISTANT) -->
    {#if isDrawerOpen && activeNode}
        <!-- Backdrop Overlay -->
        <div
            class="fixed inset-0 z-40 bg-slate-900/30 backdrop-blur-xs transition-opacity"
            onclick={closeDrawer}
            onkeydown={(e) => e.key === 'Escape' && closeDrawer()}
            role="button"
            tabindex="-1"
            aria-label="Tutup detail simpul"
        ></div>

        <!-- Slide-Over Panel -->
        <aside
            class="fixed inset-y-0 right-0 z-50 w-full max-w-xl bg-white shadow-2xl border-l border-slate-200 flex flex-col transform transition-transform duration-200 ease-in-out"
            aria-labelledby="drawer-title"
        >
            <!-- Drawer Header -->
            <div class="p-5 border-b border-slate-200 bg-slate-50/70 shrink-0">
                <div class="flex items-center justify-between gap-3 mb-2">
                    <div class="flex items-center gap-2 min-w-0">
                        {#if activeTypeInfo}
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[11px] font-bold uppercase tracking-wider border {activeTypeInfo.badge}">
                                <span class="w-1.5 h-1.5 rounded-full {activeTypeInfo.dot}"></span>
                                <span>{activeTypeInfo.label}</span>
                            </span>
                        {/if}

                        {#if activeNode.code}
                            <span class="font-mono text-xs font-semibold text-slate-700 px-2 py-0.5 rounded bg-white border border-slate-200">
                                {activeNode.code}
                            </span>
                        {/if}

                        {#if activeSrcInfo}
                            <span class="px-2 py-0.5 rounded text-[10px] font-semibold border {activeSrcInfo.class}">
                                {activeSrcInfo.label}
                            </span>
                        {/if}
                    </div>

                    <button
                        type="button"
                        onclick={closeDrawer}
                        class="p-1.5 text-slate-400 hover:text-slate-700 hover:bg-slate-200/60 rounded-md transition-colors"
                        aria-label="Tutup Drawer"
                    >
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <h2 id="drawer-title" class="text-base sm:text-lg font-bold text-slate-900 leading-snug">
                    {activeNode.statement}
                </h2>

                <!-- Drawer Navigation Tabs -->
                <div class="flex items-center gap-1 mt-4 pt-3 border-t border-slate-200/80">
                    <button
                        type="button"
                        onclick={() => { activeDrawerTab = 'indicators'; }}
                        class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors {activeDrawerTab === 'indicators' ? 'bg-emerald-600 text-white shadow-2xs' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-200/50'}"
                    >
                        <Activity class="w-3.5 h-3.5" />
                        <span>Indikator ({(activeNode.indicators || []).length})</span>
                    </button>

                    <button
                        type="button"
                        onclick={() => { activeDrawerTab = 'ai'; }}
                        class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors {activeDrawerTab === 'ai' ? 'bg-emerald-600 text-white shadow-2xs' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-200/50'}"
                    >
                        <Sparkles class="w-3.5 h-3.5" />
                        <span>Asistensi AI</span>
                        {#if (aiChildren[activeNode.id]?.length || 0) > 0 || (aiIndicators[activeNode.id]?.length || 0) > 0}
                            <span class="w-2 h-2 rounded-full bg-amber-400 animate-pulse"></span>
                        {/if}
                    </button>

                    <button
                        type="button"
                        onclick={() => { activeDrawerTab = 'reviews'; }}
                        class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors {activeDrawerTab === 'reviews' ? 'bg-emerald-600 text-white shadow-2xs' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-200/50'}"
                    >
                        <MessageSquare class="w-3.5 h-3.5" />
                        <span>Hubungan &amp; Reviu</span>
                    </button>
                </div>
            </div>

            <!-- Drawer Scrollable Body -->
            <div class="flex-1 overflow-y-auto p-5 space-y-6">
                <!-- TAB 1: INDIKATOR KINERJA -->
                {#if activeDrawerTab === 'indicators'}
                    <div class="space-y-4">
                        <div class="flex items-center justify-between">
                            <h3 class="text-xs font-bold text-slate-800 uppercase tracking-wider">
                                Daftar Indikator Kinerja Terukur
                            </h3>
                            <span class="text-xs text-slate-500 tabular-nums">
                                {(activeNode.indicators || []).length} indikator
                            </span>
                        </div>

                        <!-- Existing Indicators List -->
                        {#if (activeNode.indicators || []).length > 0}
                            <div class="space-y-3">
                                {#each activeNode.indicators as ind (ind.id)}
                                    {@const dirInfo = formatDirection(ind.direction)}
                                    {@const DirIcon = dirInfo.icon}
                                    <div class="p-3.5 rounded-xl border border-slate-200 bg-slate-50/50 space-y-2">
                                        <div class="flex items-start justify-between gap-2">
                                            <p class="text-sm font-bold text-slate-900">{ind.name}</p>
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[10px] font-semibold border {dirInfo.class} shrink-0">
                                                <DirIcon class="w-3 h-3" />
                                                <span>{dirInfo.label}</span>
                                            </span>
                                        </div>

                                        {#if ind.definition}
                                            <p class="text-xs text-slate-600">{ind.definition}</p>
                                        {/if}

                                        <div class="flex flex-wrap items-center gap-3 pt-2 border-t border-slate-200/70 text-xs text-slate-600">
                                            {#if ind.unit}
                                                <div class="flex items-center gap-1">
                                                    <span class="text-slate-400">Satuan:</span>
                                                    <span class="font-mono font-medium text-slate-800">{ind.unit}</span>
                                                </div>
                                            {/if}
                                            {#if ind.baseline}
                                                <div class="flex items-center gap-1">
                                                    <span class="text-slate-400">Baseline:</span>
                                                    <span class="font-mono font-semibold tabular-nums text-slate-700">{ind.baseline}</span>
                                                </div>
                                            {/if}
                                            {#if ind.target}
                                                <div class="flex items-center gap-1">
                                                    <span class="text-slate-400">Target:</span>
                                                    <span class="font-mono font-bold tabular-nums text-emerald-700">{ind.target}</span>
                                                </div>
                                            {/if}
                                            {#if ind.data_source}
                                                <div class="flex items-center gap-1 text-[11px] text-slate-500">
                                                    <span class="text-slate-400">Sumber:</span>
                                                    <span>{ind.data_source}</span>
                                                </div>
                                            {/if}
                                        </div>
                                    </div>
                                {/each}
                            </div>
                        {:else}
                            <div class="p-6 rounded-xl border border-dashed border-slate-200 text-center">
                                <p class="text-xs text-slate-500">Belum ada indikator yang terdaftar untuk sasaran ini.</p>
                            </div>
                        {/if}

                        <!-- Add Indicator Form -->
                        <div class="pt-4 border-t border-slate-200">
                            <h4 class="text-xs font-bold text-slate-800 uppercase tracking-wider mb-3">
                                Tambah Indikator Baru
                            </h4>
                            <form onsubmit={submitAddIndicator} class="space-y-3">
                                <div>
                                    <label for="ind-name" class="block text-xs font-medium text-slate-700 mb-1">
                                        Nama Indikator <span class="text-rose-500">*</span>
                                    </label>
                                    <input
                                        id="ind-name"
                                        type="text"
                                        bind:value={indicatorForm.name}
                                        placeholder="mis. Persentase Peningkatan Kualitas Layanan Publik"
                                        class="w-full rounded-lg border border-slate-200 px-3 py-2 text-xs focus:outline-emerald-600 focus:border-emerald-600"
                                        required
                                    />
                                    {#if indicatorForm.errors.name}
                                        <p class="text-[11px] text-rose-600 mt-1">{indicatorForm.errors.name}</p>
                                    {/if}
                                </div>

                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <label for="ind-unit" class="block text-xs font-medium text-slate-700 mb-1">Satuan</label>
                                        <input
                                            id="ind-unit"
                                            type="text"
                                            bind:value={indicatorForm.unit}
                                            placeholder="mis. Persen (%), Indeks, Nilai"
                                            class="w-full rounded-lg border border-slate-200 px-3 py-2 text-xs focus:outline-emerald-600"
                                        />
                                    </div>
                                    <div>
                                        <label for="ind-dir" class="block text-xs font-medium text-slate-700 mb-1">Arah Kinerja</label>
                                        <select
                                            id="ind-dir"
                                            bind:value={indicatorForm.direction}
                                            class="w-full rounded-lg border border-slate-200 px-3 py-2 text-xs bg-white focus:outline-emerald-600"
                                        >
                                            <option value="naik">Semakin tinggi semakin baik</option>
                                            <option value="turun">Semakin rendah semakin baik</option>
                                            <option value="tetap">Tetap / Terkendali</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <label for="ind-base" class="block text-xs font-medium text-slate-700 mb-1">Baseline Awal</label>
                                        <input
                                            id="ind-base"
                                            type="text"
                                            bind:value={indicatorForm.baseline}
                                            placeholder="mis. 75.5"
                                            class="w-full rounded-lg border border-slate-200 px-3 py-2 text-xs font-mono focus:outline-emerald-600"
                                        />
                                    </div>
                                    <div>
                                        <label for="ind-target" class="block text-xs font-medium text-slate-700 mb-1">Target Akhir</label>
                                        <input
                                            id="ind-target"
                                            type="text"
                                            bind:value={indicatorForm.target}
                                            placeholder="mis. 85.0"
                                            class="w-full rounded-lg border border-slate-200 px-3 py-2 text-xs font-mono focus:outline-emerald-600"
                                        />
                                    </div>
                                </div>

                                <div>
                                    <label for="ind-source" class="block text-xs font-medium text-slate-700 mb-1">Sumber Data (Opsional)</label>
                                    <input
                                        id="ind-source"
                                        type="text"
                                        bind:value={indicatorForm.data_source}
                                        placeholder="mis. Survei Kepuasan Masyarakat, BPS"
                                        class="w-full rounded-lg border border-slate-200 px-3 py-2 text-xs focus:outline-emerald-600"
                                    />
                                </div>

                                <div>
                                    <label for="ind-def" class="block text-xs font-medium text-slate-700 mb-1">Definisi Operasional</label>
                                    <textarea
                                        id="ind-def"
                                        bind:value={indicatorForm.definition}
                                        rows="2"
                                        placeholder="Penjelasan formula atau metode perhitungan indikator..."
                                        class="w-full rounded-lg border border-slate-200 px-3 py-2 text-xs focus:outline-emerald-600"
                                    ></textarea>
                                </div>

                                <button
                                    type="submit"
                                    disabled={indicatorForm.processing}
                                    class="w-full py-2 px-4 rounded-lg bg-emerald-600 text-white text-xs font-semibold hover:bg-emerald-500 disabled:opacity-50 transition-colors shadow-2xs"
                                >
                                    {indicatorForm.processing ? 'Menyimpan...' : 'Simpan Indikator'}
                                </button>
                            </form>
                        </div>
                    </div>

                <!-- TAB 2: ASISTENSI & REKOMENDASI AI -->
                {:else if activeDrawerTab === 'ai'}
                    <div class="space-y-4">
                        <!-- Guidance Notice -->
                        <div class="rounded-xl border border-emerald-200 bg-emerald-50/70 p-4 text-xs text-emerald-900 space-y-1.5">
                            <div class="flex items-center gap-2 font-bold text-emerald-900">
                                <Sparkles class="w-4 h-4 text-emerald-600" />
                                <span>Asistensi AI Terkurasi PermenPAN-RB</span>
                            </div>
                            <p class="text-emerald-800 leading-relaxed">
                                AI akan meninjau konteks dokumen perencanaan dan Knowledge Pack regulasi untuk memberikan usulan turunan pohon kinerja yang valid secara kausalitas dan indikator yang terukur.
                            </p>
                        </div>

                        <!-- Action Buttons -->
                        <div class="grid grid-cols-2 gap-3">
                            <button
                                type="button"
                                onclick={() => askChildren(activeNode.id)}
                                disabled={Boolean(aiLoading[activeNode.id])}
                                class="flex items-center justify-center gap-2 px-3.5 py-2.5 rounded-lg bg-emerald-600 text-white text-xs font-semibold hover:bg-emerald-500 disabled:opacity-50 shadow-2xs transition-colors"
                            >
                                <Sparkles class="w-3.5 h-3.5" />
                                <span>Usulkan Turunan</span>
                            </button>

                            <button
                                type="button"
                                onclick={() => askIndicators(activeNode.id)}
                                disabled={Boolean(aiLoading[activeNode.id])}
                                class="flex items-center justify-center gap-2 px-3.5 py-2.5 rounded-lg bg-emerald-600 text-white text-xs font-semibold hover:bg-emerald-500 disabled:opacity-50 shadow-2xs transition-colors"
                            >
                                <Sparkles class="w-3.5 h-3.5" />
                                <span>Usulkan Indikator</span>
                            </button>
                        </div>

                        <!-- Clean Emerald AI Thinking State -->
                        {#if aiLoading[activeNode.id]}
                            <div class="rounded-xl border border-emerald-200 bg-emerald-50/60 p-4 shadow-2xs space-y-3">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-2">
                                        <span class="relative flex h-2.5 w-2.5">
                                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                                            <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-emerald-600"></span>
                                        </span>
                                        <span class="text-xs font-bold text-emerald-900">
                                            {aiLoading[activeNode.id] === 'indicators' ? 'Merumuskan Usulan Indikator' : 'Merumuskan Sasaran Turunan'}
                                        </span>
                                    </div>
                                    <span class="rounded-full bg-emerald-100 px-2.5 py-0.5 text-[10px] font-semibold text-emerald-800 border border-emerald-200/60">
                                        Sedang Berpikir
                                    </span>
                                </div>

                                <div class="flex items-center gap-2 text-xs text-emerald-800">
                                    <svg class="animate-spin h-4 w-4 text-emerald-600 shrink-0" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    <span class="font-medium">{aiStep[activeNode.id] || 'Menganalisis perjenjangan kinerja...'}</span>
                                </div>

                                <div class="h-1.5 w-full bg-emerald-100 rounded-full overflow-hidden relative">
                                    <div class="progress-shimmer h-full bg-emerald-600 rounded-full"></div>
                                </div>
                            </div>
                        {/if}

                        <!-- AI Error Alert -->
                        {#if aiError[activeNode.id]}
                            <div class="rounded-xl border border-rose-200 bg-rose-50 p-3.5 flex items-start justify-between gap-3">
                                <div class="flex items-start gap-2.5">
                                    <AlertCircle class="w-4 h-4 text-rose-600 mt-0.5 shrink-0" />
                                    <div>
                                        <p class="text-xs font-bold text-rose-800">Gagal Mendapatkan Usulan AI</p>
                                        <p class="text-xs text-rose-600 mt-0.5">{aiError[activeNode.id]}</p>
                                    </div>
                                </div>
                                <button
                                    type="button"
                                    onclick={() => { aiError[activeNode.id] = null; }}
                                    class="text-slate-400 hover:text-slate-600"
                                >
                                    <X class="w-4 h-4" />
                                </button>
                            </div>
                        {/if}

                        <!-- Results: Usulan Sasaran Turunan -->
                        {#if (aiChildren[activeNode.id] || []).length > 0}
                            <div class="rounded-xl border border-slate-200 bg-slate-50/60 p-4 space-y-3">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-1.5">
                                        <span class="text-xs font-bold text-slate-900">Rekomendasi Sasaran Turunan</span>
                                        <span class="px-1.5 py-0.5 rounded-full bg-emerald-100 text-emerald-800 text-[10px] font-bold">
                                            {aiChildren[activeNode.id].length}
                                        </span>
                                    </div>
                                    <button
                                        type="button"
                                        onclick={() => dismissAi(activeNode.id, 'children')}
                                        class="text-[11px] font-medium text-slate-500 hover:text-rose-600 transition-colors"
                                    >
                                        ✕ Abaikan Semua
                                    </button>
                                </div>

                                <div class="space-y-3">
                                    {#each aiChildren[activeNode.id] as rec, i (i)}
                                        <div class="p-3.5 rounded-lg border border-slate-200 bg-white space-y-2 shadow-2xs">
                                            <p class="text-xs font-bold text-slate-900">{rec.statement}</p>
                                            {#if rec.relationship_reason}
                                                <div class="text-[11px] text-slate-600 bg-slate-50 p-2 rounded border border-slate-100">
                                                    <span class="font-semibold text-slate-700">Alasan Kausal:</span> {rec.relationship_reason}
                                                </div>
                                            {/if}
                                            {#if rec.assumptions && rec.assumptions.length > 0}
                                                <div class="text-[11px] text-slate-500">
                                                    <span class="font-medium text-slate-600">Asumsi:</span> {rec.assumptions.join('; ')}
                                                </div>
                                            {/if}
                                            {#if rec.missing_data && rec.missing_data.length > 0}
                                                <div class="text-[11px] text-amber-700 bg-amber-50 px-2 py-1 rounded border border-amber-200">
                                                    <span class="font-semibold">Perlu diverifikasi:</span> {rec.missing_data.join('; ')}
                                                </div>
                                            {/if}
                                            <div class="pt-2 flex justify-end">
                                                <button
                                                    type="button"
                                                    onclick={() => acceptChild(activeNode.id, rec)}
                                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md bg-emerald-600 text-white text-xs font-semibold hover:bg-emerald-500 shadow-2xs transition-colors"
                                                >
                                                    <Check class="w-3.5 h-3.5" />
                                                    <span>Terima Sebagai Sasaran</span>
                                                </button>
                                            </div>
                                        </div>
                                    {/each}
                                </div>
                            </div>
                        {/if}

                        <!-- Results: Usulan Indikator Kinerja -->
                        {#if (aiIndicators[activeNode.id] || []).length > 0}
                            <div class="rounded-xl border border-slate-200 bg-slate-50/60 p-4 space-y-3">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-1.5">
                                        <span class="text-xs font-bold text-slate-900">Rekomendasi Indikator</span>
                                        <span class="px-1.5 py-0.5 rounded-full bg-emerald-100 text-emerald-800 text-[10px] font-bold">
                                            {aiIndicators[activeNode.id].length}
                                        </span>
                                    </div>
                                    <button
                                        type="button"
                                        onclick={() => dismissAi(activeNode.id, 'indicators')}
                                        class="text-[11px] font-medium text-slate-500 hover:text-rose-600 transition-colors"
                                    >
                                        ✕ Abaikan Semua
                                    </button>
                                </div>

                                <div class="space-y-3">
                                    {#each aiIndicators[activeNode.id] as ind, i (i)}
                                        <div class="p-3.5 rounded-lg border border-slate-200 bg-white space-y-2 shadow-2xs">
                                            <div class="flex items-start justify-between gap-2">
                                                <p class="text-xs font-bold text-slate-900">{ind.name}</p>
                                                {#if ind.unit}
                                                    <span class="font-mono text-[10px] font-medium text-slate-600 bg-slate-100 px-1.5 py-0.5 rounded">
                                                        {ind.unit}
                                                    </span>
                                                {/if}
                                            </div>
                                            {#if ind.definition}
                                                <p class="text-xs text-slate-600">{ind.definition}</p>
                                            {/if}
                                            {#if ind.data_source}
                                                <p class="text-[11px] text-slate-500">
                                                    <span class="font-medium text-slate-600">Sumber:</span> {ind.data_source}
                                                </p>
                                            {/if}
                                            <div class="pt-2 flex justify-end">
                                                <button
                                                    type="button"
                                                    onclick={() => acceptIndicator(activeNode.id, ind)}
                                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md bg-emerald-600 text-white text-xs font-semibold hover:bg-emerald-500 shadow-2xs transition-colors"
                                                >
                                                    <Check class="w-3.5 h-3.5" />
                                                    <span>Terima Indikator</span>
                                                </button>
                                            </div>
                                        </div>
                                    {/each}
                                </div>
                            </div>
                        {/if}
                    </div>

                <!-- TAB 3: HUBUNGAN & REVIU -->
                {:else if activeDrawerTab === 'reviews'}
                    <div class="space-y-6">
                        <!-- Hubungan Hierarki Sebab-Akibat -->
                        <div class="space-y-3">
                            <div class="flex items-center justify-between">
                                <h3 class="text-xs font-bold text-slate-800 uppercase tracking-wider">
                                    Hubungan Sebab-Akibat
                                </h3>
                                <button
                                    type="button"
                                    onclick={() => openAddLinkModal(activeNode.id)}
                                    class="inline-flex items-center gap-1 text-xs font-semibold text-emerald-700 hover:text-emerald-800"
                                >
                                    <Plus class="w-3.5 h-3.5" />
                                    <span>Hubungkan Simpul</span>
                                </button>
                            </div>

                            <!-- Induk / Parents -->
                            <div class="rounded-lg border border-slate-200 bg-slate-50/50 p-3 space-y-2">
                                <p class="text-[11px] font-bold text-slate-500 uppercase tracking-wider">Diturunkan dari (Sasaran Induk):</p>
                                {#if activeParents.length > 0}
                                    <div class="space-y-1.5">
                                        {#each activeParents as item (item.linkId)}
                                            <div class="p-2 rounded bg-white border border-slate-200 text-xs">
                                                <p class="font-semibold text-slate-800">{item.node.statement}</p>
                                                {#if item.reason}
                                                    <p class="text-[11px] text-slate-500 mt-0.5">Alasan: {item.reason}</p>
                                                {/if}
                                            </div>
                                        {/each}
                                    </div>
                                {:else}
                                    <p class="text-xs text-slate-400 italic">Merupakan sasaran tingkat utama (Root Outcome).</p>
                                {/if}
                            </div>

                            <!-- Turunan / Children -->
                            <div class="rounded-lg border border-slate-200 bg-slate-50/50 p-3 space-y-2">
                                <p class="text-[11px] font-bold text-slate-500 uppercase tracking-wider">Menurunkan ke (Sasaran Turunan):</p>
                                {#if activeChildren.length > 0}
                                    <div class="space-y-1.5">
                                        {#each activeChildren as item (item.linkId)}
                                            <div class="p-2 rounded bg-white border border-slate-200 text-xs">
                                                <p class="font-semibold text-slate-800">{item.node.statement}</p>
                                                {#if item.reason}
                                                    <p class="text-[11px] text-slate-500 mt-0.5">Alasan: {item.reason}</p>
                                                {/if}
                                            </div>
                                        {/each}
                                    </div>
                                {:else}
                                    <p class="text-xs text-slate-400 italic">Belum menurunkan sasaran lain.</p>
                                {/if}
                            </div>
                        </div>

                        <!-- Reviu & Persetujuan -->
                        <div class="space-y-3 pt-4 border-t border-slate-200">
                            <h3 class="text-xs font-bold text-slate-800 uppercase tracking-wider">
                                Reviu &amp; Persetujuan Simpul
                            </h3>

                            <form onsubmit={submitReview} class="space-y-3">
                                <div>
                                    <label for="rev-decision" class="block text-xs font-medium text-slate-700 mb-1">Keputusan Reviu</label>
                                    <select
                                        id="rev-decision"
                                        bind:value={reviewForm.decision}
                                        class="w-full rounded-lg border border-slate-200 px-3 py-2 text-xs bg-white focus:outline-emerald-600"
                                    >
                                        <option value="comment">Catatan / Komentar</option>
                                        <option value="approve">Setujui Simpul (Approve)</option>
                                        <option value="reject">Perlu Perbaikan (Reject)</option>
                                    </select>
                                </div>

                                <div>
                                    <label for="rev-comment" class="block text-xs font-medium text-slate-700 mb-1">Catatan Reviu</label>
                                    <textarea
                                        id="rev-comment"
                                        bind:value={reviewForm.comment}
                                        rows="2"
                                        placeholder="Tuliskan catatan analisis atau rekomendasi perbaikan..."
                                        class="w-full rounded-lg border border-slate-200 px-3 py-2 text-xs focus:outline-emerald-600"
                                    ></textarea>
                                </div>

                                <button
                                    type="submit"
                                    disabled={reviewForm.processing}
                                    class="w-full py-2 px-4 rounded-lg bg-emerald-600 text-white text-xs font-semibold hover:bg-emerald-500 disabled:opacity-50 transition-colors shadow-2xs"
                                >
                                    {reviewForm.processing ? 'Menyimpan...' : 'Kirim Catatan Reviu'}
                                </button>
                            </form>

                            <!-- Past Reviews for Active Node -->
                            {#if activeReviews.length > 0}
                                <div class="space-y-2 pt-2">
                                    <p class="text-[11px] font-semibold text-slate-500">Riwayat Reviu ({activeReviews.length}):</p>
                                    {#each activeReviews as rev (rev.id)}
                                        <div class="p-2.5 rounded-lg bg-slate-50 border border-slate-200 text-xs space-y-1">
                                            <div class="flex items-center justify-between">
                                                <span class="font-bold text-slate-800">{rev.user || 'Pengguna'}</span>
                                                <span class="px-1.5 py-0.5 rounded text-[10px] font-semibold {rev.decision === 'approve' ? 'bg-emerald-100 text-emerald-800' : rev.decision === 'reject' ? 'bg-rose-100 text-rose-800' : 'bg-slate-200 text-slate-700'}">
                                                    {rev.decision}
                                                </span>
                                            </div>
                                            {#if rev.comment}
                                                <p class="text-slate-600 text-[11px]">{rev.comment}</p>
                                            {/if}
                                        </div>
                                    {/each}
                                </div>
                            {/if}
                        </div>
                    </div>
                {/if}
            </div>
        </aside>
    {/if}

    <!-- 4. MODAL TAMBAH SASARAN -->
    {#if showAddNodeModal}
        <div
            class="fixed inset-0 z-50 bg-slate-900/40 backdrop-blur-xs flex items-center justify-center p-4"
            onclick={(e) => { if (e.target === e.currentTarget) showAddNodeModal = false; }}
            onkeydown={(e) => { if (e.key === 'Escape') showAddNodeModal = false; }}
            role="dialog"
            aria-modal="true"
            tabindex="-1"
        >
            <div class="bg-white rounded-xl border border-slate-200 shadow-2xl max-w-lg w-full overflow-hidden animate-in fade-in zoom-in-95 duration-150">
                <div class="p-5 border-b border-slate-100 flex items-center justify-between">
                    <h3 class="text-sm font-bold text-slate-900">Tambah Sasaran Kinerja Baru</h3>
                    <button
                        type="button"
                        onclick={() => { showAddNodeModal = false; }}
                        class="p-1 text-slate-400 hover:text-slate-600 rounded-md"
                    >
                        <X class="w-4 h-4" />
                    </button>
                </div>

                <form onsubmit={submitAddNode} class="p-5 space-y-3.5 text-xs">
                    <div>
                        <label for="node-stmt" class="block font-medium text-slate-700 mb-1">
                            Rumusan Sasaran (Hasil / Outcome / Output) <span class="text-rose-500">*</span>
                        </label>
                        <textarea
                            id="node-stmt"
                            bind:value={nodeForm.statement}
                            rows="2"
                            placeholder="mis. Meningkatnya Kualitas Pelayanan Publik dan Kepuasan Masyarakat"
                            class="w-full rounded-lg border border-slate-200 px-3 py-2 text-xs focus:outline-emerald-600"
                            required
                        ></textarea>
                        {#if nodeForm.errors.statement}
                            <p class="text-[11px] text-rose-600 mt-1">{nodeForm.errors.statement}</p>
                        {/if}
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label for="node-type" class="block font-medium text-slate-700 mb-1">Tingkat Sasaran</label>
                            <select
                                id="node-type"
                                bind:value={nodeForm.type}
                                class="w-full rounded-lg border border-slate-200 px-3 py-2 text-xs bg-white focus:outline-emerald-600"
                            >
                                <option value="outcome">Outcome (Hasil)</option>
                                <option value="output">Output (Keluaran)</option>
                                <option value="aktivitas">Aktivitas (Kegiatan)</option>
                            </select>
                        </div>
                        <div>
                            <label for="node-code" class="block font-medium text-slate-700 mb-1">Kode Simpul (Opsional)</label>
                            <input
                                id="node-code"
                                type="text"
                                bind:value={nodeForm.code}
                                placeholder="mis. SS-01, SP-02"
                                class="w-full rounded-lg border border-slate-200 px-3 py-2 text-xs font-mono focus:outline-emerald-600"
                            />
                        </div>
                    </div>

                    {#if nodes.length > 0}
                        <div class="pt-2 border-t border-slate-100">
                            <label for="node-parent" class="block font-medium text-slate-700 mb-1">
                                Hubungkan ke Sasaran Induk (Opsional)
                            </label>
                            <select
                                id="node-parent"
                                bind:value={nodeForm.parent_node_id}
                                class="w-full rounded-lg border border-slate-200 px-3 py-2 text-xs bg-white focus:outline-emerald-600"
                            >
                                <option value="">— Tidak Ada (Jadikan Simpul Utama) —</option>
                                {#each nodes as n (n.id)}
                                    <option value={n.id}>
                                        {n.code ? `[${n.code}] ` : ''}{n.statement}
                                    </option>
                                {/each}
                            </select>
                        </div>

                        {#if nodeForm.parent_node_id}
                            <div>
                                <label for="node-reason" class="block font-medium text-slate-700 mb-1">
                                    Alasan Hubungan Kausalitas
                                </label>
                                <input
                                    id="node-reason"
                                    type="text"
                                    bind:value={nodeForm.relationship_reason}
                                    placeholder="mis. Merupakan kondisi penentu pencapaian outcome induk"
                                    class="w-full rounded-lg border border-slate-200 px-3 py-2 text-xs focus:outline-emerald-600"
                                />
                            </div>
                        {/if}
                    {/if}

                    <div class="flex items-center justify-end gap-2.5 pt-3 border-t border-slate-100">
                        <button
                            type="button"
                            onclick={() => { showAddNodeModal = false; }}
                            class="px-3.5 py-2 rounded-lg border border-slate-200 text-slate-700 font-semibold hover:bg-slate-50 transition-colors"
                        >
                            Batal
                        </button>
                        <button
                            type="submit"
                            disabled={nodeForm.processing}
                            class="px-4 py-2 rounded-lg bg-emerald-600 text-white font-semibold hover:bg-emerald-500 disabled:opacity-50 transition-colors shadow-2xs"
                        >
                            {nodeForm.processing ? 'Menyimpan...' : 'Simpan Sasaran'}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    {/if}

    <!-- 5. MODAL HUBUNGKAN NODE -->
    {#if showAddLinkModal}
        <div
            class="fixed inset-0 z-50 bg-slate-900/40 backdrop-blur-xs flex items-center justify-center p-4"
            onclick={(e) => { if (e.target === e.currentTarget) showAddLinkModal = false; }}
            onkeydown={(e) => { if (e.key === 'Escape') showAddLinkModal = false; }}
            role="dialog"
            aria-modal="true"
            tabindex="-1"
        >
            <div class="bg-white rounded-xl border border-slate-200 shadow-2xl max-w-lg w-full overflow-hidden animate-in fade-in zoom-in-95 duration-150">
                <div class="p-5 border-b border-slate-100 flex items-center justify-between">
                    <h3 class="text-sm font-bold text-slate-900">Hubungkan Simpul Kausal (Parent → Child)</h3>
                    <button
                        type="button"
                        onclick={() => { showAddLinkModal = false; }}
                        class="p-1 text-slate-400 hover:text-slate-600 rounded-md"
                    >
                        <X class="w-4 h-4" />
                    </button>
                </div>

                <form onsubmit={submitAddLink} class="p-5 space-y-3.5 text-xs">
                    <div>
                        <label for="link-parent" class="block font-medium text-slate-700 mb-1">
                            Simpul Induk (Parent / Outcome) <span class="text-rose-500">*</span>
                        </label>
                        <select
                            id="link-parent"
                            bind:value={linkForm.parent_node_id}
                            class="w-full rounded-lg border border-slate-200 px-3 py-2 text-xs bg-white focus:outline-emerald-600"
                            required
                        >
                            <option value="">— Pilih Sasaran Induk —</option>
                            {#each nodes as n (n.id)}
                                <option value={n.id}>
                                    {n.code ? `[${n.code}] ` : ''}{n.statement} ({n.type})
                                </option>
                            {/each}
                        </select>
                    </div>

                    <div>
                        <label for="link-child" class="block font-medium text-slate-700 mb-1">
                            Simpul Turunan (Child / Output) <span class="text-rose-500">*</span>
                        </label>
                        <select
                            id="link-child"
                            bind:value={linkForm.child_node_id}
                            class="w-full rounded-lg border border-slate-200 px-3 py-2 text-xs bg-white focus:outline-emerald-600"
                            required
                        >
                            <option value="">— Pilih Sasaran Turunan —</option>
                            {#each nodes as n (n.id)}
                                <option value={n.id} disabled={n.id === Number(linkForm.parent_node_id)}>
                                    {n.code ? `[${n.code}] ` : ''}{n.statement} ({n.type})
                                </option>
                            {/each}
                        </select>
                    </div>

                    <div>
                        <label for="link-reason" class="block font-medium text-slate-700 mb-1">
                            Alasan Kausalitas / Logika Hubungan (Opsional)
                        </label>
                        <input
                            id="link-reason"
                            type="text"
                            bind:value={linkForm.reason}
                            placeholder="mis. Melalui penguatan kapasitas kelembagaan"
                            class="w-full rounded-lg border border-slate-200 px-3 py-2 text-xs focus:outline-emerald-600"
                        />
                    </div>

                    {#if linkForm.errors.link}
                        <div class="p-3 rounded-lg border border-rose-200 bg-rose-50 text-rose-800 text-[11px] flex items-start gap-2">
                            <AlertCircle class="w-4 h-4 text-rose-600 shrink-0 mt-0.5" />
                            <span>{linkForm.errors.link}</span>
                        </div>
                    {/if}

                    <div class="flex items-center justify-end gap-2.5 pt-3 border-t border-slate-100">
                        <button
                            type="button"
                            onclick={() => { showAddLinkModal = false; }}
                            class="px-3.5 py-2 rounded-lg border border-slate-200 text-slate-700 font-semibold hover:bg-slate-50 transition-colors"
                        >
                            Batal
                        </button>
                        <button
                            type="submit"
                            disabled={linkForm.processing}
                            class="px-4 py-2 rounded-lg bg-emerald-600 text-white font-semibold hover:bg-emerald-500 disabled:opacity-50 transition-colors shadow-2xs"
                        >
                            {linkForm.processing ? 'Menghubungkan...' : 'Hubungkan Simpul'}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    {/if}

    <!-- 6. MODAL EKSPOR DOKUMEN -->
    {#if showExportModal}
        <div
            class="fixed inset-0 z-50 bg-slate-900/40 backdrop-blur-xs flex items-center justify-center p-4"
            onclick={(e) => { if (e.target === e.currentTarget) showExportModal = false; }}
            onkeydown={(e) => { if (e.key === 'Escape') showExportModal = false; }}
            role="dialog"
            aria-modal="true"
            tabindex="-1"
        >
            <div class="bg-white rounded-xl border border-slate-200 shadow-2xl max-w-md w-full overflow-hidden animate-in fade-in zoom-in-95 duration-150">
                <div class="p-5 border-b border-slate-100 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <Download class="w-4 h-4 text-emerald-600" />
                        <h3 class="text-sm font-bold text-slate-900">Ekspor Pohon Kinerja</h3>
                    </div>
                    <button
                        type="button"
                        onclick={() => { showExportModal = false; }}
                        class="p-1 text-slate-400 hover:text-slate-600 rounded-md"
                    >
                        <X class="w-4 h-4" />
                    </button>
                </div>

                <div class="p-5 space-y-3">
                    <p class="text-xs text-slate-600 leading-relaxed">
                        Pilih format berkas untuk mengekspor seluruh rumusan sasaran, perjenjangan pohon kinerja, dan indikator terukur:
                    </p>

                    <a
                        href={`/kinerja/${tree.id}/export?format=markdown`}
                        target="_blank"
                        class="flex items-start gap-3 p-3.5 rounded-xl border border-slate-200 hover:border-emerald-500 hover:bg-emerald-50/20 transition-all group block"
                    >
                        <div class="h-9 w-9 rounded-lg bg-emerald-50 text-emerald-700 border border-emerald-100 flex items-center justify-center shrink-0 group-hover:bg-emerald-600 group-hover:text-white transition-colors">
                            <FileText class="w-5 h-5" />
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-xs font-bold text-slate-900 group-hover:text-emerald-900">
                                Dokumen Laporan Markdown (.md)
                            </p>
                            <p class="text-[11px] text-slate-500 mt-0.5">
                                Format teks terstruktur standar PermenPAN-RB untuk lampiran dokumen Renstra atau evaluasi AKIP.
                            </p>
                        </div>
                        <ChevronRight class="w-4 h-4 text-slate-300 group-hover:text-emerald-600 transition-colors mt-1" />
                    </a>

                    <a
                        href={`/kinerja/${tree.id}/export?format=json`}
                        target="_blank"
                        class="flex items-start gap-3 p-3.5 rounded-xl border border-slate-200 hover:border-emerald-500 hover:bg-emerald-50/20 transition-all group block"
                    >
                        <div class="h-9 w-9 rounded-lg bg-slate-100 text-slate-700 border border-slate-200 flex items-center justify-center shrink-0 group-hover:bg-emerald-600 group-hover:text-white transition-colors">
                            <Download class="w-5 h-5" />
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-xs font-bold text-slate-900 group-hover:text-emerald-900">
                                Struktur Data Graf JSON (.json)
                            </p>
                            <p class="text-[11px] text-slate-500 mt-0.5">
                                Format data lengkap node, edge, dan indikator untuk integrasi sistem atau migrasi.
                            </p>
                        </div>
                        <ChevronRight class="w-4 h-4 text-slate-300 group-hover:text-emerald-600 transition-colors mt-1" />
                    </a>
                </div>

                <div class="p-4 bg-slate-50 border-t border-slate-100 flex justify-end">
                    <button
                        type="button"
                        onclick={() => { showExportModal = false; }}
                        class="px-3.5 py-1.5 rounded-lg border border-slate-200 text-xs font-semibold text-slate-700 hover:bg-white transition-colors"
                    >
                        Tutup
                    </button>
                </div>
            </div>
        </div>
    {/if}
</Layout>

<style>
    @keyframes emeraldShimmer {
        0% { transform: translateX(-100%); width: 35%; }
        50% { width: 55%; }
        100% { transform: translateX(300%); width: 35%; }
    }
    .progress-shimmer {
        animation: emeraldShimmer 1.8s infinite ease-in-out;
    }
</style>
