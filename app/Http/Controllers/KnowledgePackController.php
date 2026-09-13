<?php

namespace App\Http\Controllers;

use App\Models\KnowledgePack;
use App\Models\Sector;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class KnowledgePackController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $canManage = in_array($user->role, ['admin', 'planner'], true);

        $sectors = Sector::query()
            ->where(fn ($q) => $q->whereNull('organization_id')->orWhere('organization_id', $user->organization_id))
            ->withCount('knowledgePacks')
            ->with(['knowledgePacks' => function ($query) {
                $query->orderBy('created_at', 'desc');
            }])
            ->orderByRaw('CASE WHEN organization_id IS NULL THEN 0 ELSE 1 END, name ASC')
            ->get()
            ->map(fn (Sector $s) => [
                'id' => $s->id,
                'name' => $s->name,
                'slug' => $s->slug,
                'description' => $s->description,
                'organization_id' => $s->organization_id,
                'is_global' => is_null($s->organization_id),
                'can_edit' => $canManage && ! is_null($s->organization_id) && $s->organization_id === $user->organization_id,
                'knowledge_packs_count' => $s->knowledge_packs_count,
                'knowledge_packs' => $s->knowledgePacks->map(fn (KnowledgePack $p) => [
                    'id' => $p->id,
                    'sector_id' => $p->sector_id,
                    'title' => $p->title,
                    'content' => $p->content,
                    'source' => $p->source,
                    'version' => $p->version,
                    'is_active' => (bool) $p->is_active,
                    'updated_at' => $p->updated_at?->toISOString(),
                    'can_edit' => $canManage && ! is_null($s->organization_id) && $s->organization_id === $user->organization_id,
                ]),
            ]);

        $requestedSectorId = $request->query('sector_id') ? (int) $request->query('sector_id') : null;
        $selectedSectorId = ($requestedSectorId && $sectors->contains('id', $requestedSectorId))
            ? $requestedSectorId
            : ($sectors->first()['id'] ?? null);

        return Inertia::render('KnowledgePack/Index', [
            'sectors' => $sectors,
            'selected_sector_id' => $selectedSectorId,
            'can_manage' => $canManage,
            'user_role' => $user->role,
        ]);
    }

    public function storeSector(Request $request)
    {
        $user = $request->user();
        abort_unless(in_array($user->role, ['admin', 'planner'], true), 403, 'Aksi hanya diizinkan untuk Admin dan Perencana.');

        $data = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('sectors', 'name')->where(fn ($q) => $q->where('organization_id', $user->organization_id)),
            ],
            'slug' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);

        $slug = ! empty($data['slug']) ? Str::slug($data['slug']) : Str::slug($data['name']);
        $slug = ($slug !== '') ? $slug : null;

        $sector = Sector::create([
            'organization_id' => $user->organization_id,
            'name' => $data['name'],
            'slug' => $slug,
            'description' => $data['description'] ?? null,
        ]);

        return redirect()->route('knowledge-packs.index', ['sector_id' => $sector->id])
            ->with('success', 'Sektor berhasil ditambahkan.');
    }

    public function updateSector(Request $request, Sector $sector)
    {
        $user = $request->user();
        abort_unless(in_array($user->role, ['admin', 'planner'], true), 403, 'Aksi hanya diizinkan untuk Admin dan Perencana.');
        abort_if(is_null($sector->organization_id), 403, 'Sektor global tidak dapat diubah.');
        abort_unless($sector->organization_id === $user->organization_id, 403, 'Anda tidak memiliki akses ke sektor ini.');

        $data = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('sectors', 'name')
                    ->where(fn ($q) => $q->where('organization_id', $user->organization_id))
                    ->ignore($sector->id),
            ],
            'slug' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);

        $slug = ! empty($data['slug']) ? Str::slug($data['slug']) : Str::slug($data['name']);
        $slug = ($slug !== '') ? $slug : null;

        $sector->update([
            'name' => $data['name'],
            'slug' => $slug,
            'description' => $data['description'] ?? null,
        ]);

        return redirect()->route('knowledge-packs.index', ['sector_id' => $sector->id])
            ->with('success', 'Sektor berhasil diperbarui.');
    }

    public function destroySector(Request $request, Sector $sector)
    {
        $user = $request->user();
        abort_unless(in_array($user->role, ['admin', 'planner'], true), 403, 'Aksi hanya diizinkan untuk Admin dan Perencana.');
        abort_if(is_null($sector->organization_id), 403, 'Sektor global tidak dapat dihapus.');
        abort_unless($sector->organization_id === $user->organization_id, 403, 'Anda tidak memiliki akses ke sektor ini.');

        $sector->delete();

        return redirect()->route('knowledge-packs.index')
            ->with('success', 'Sektor berhasil dihapus.');
    }

    public function storePack(Request $request, Sector $sector)
    {
        $user = $request->user();
        abort_unless(in_array($user->role, ['admin', 'planner'], true), 403, 'Aksi hanya diizinkan untuk Admin dan Perencana.');
        abort_if(is_null($sector->organization_id), 403, 'Tidak dapat menambahkan knowledge pack pada sektor global.');
        abort_unless($sector->organization_id === $user->organization_id, 403, 'Anda tidak memiliki akses ke sektor ini.');

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
            'source' => ['nullable', 'string', 'max:255'],
            'version' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $sector->knowledgePacks()->create([
            'title' => $data['title'],
            'content' => $data['content'],
            'source' => $data['source'] ?? null,
            'version' => $data['version'] ?? 1,
            'is_active' => $request->has('is_active') ? $request->boolean('is_active') : true,
        ]);

        return redirect()->route('knowledge-packs.index', ['sector_id' => $sector->id])
            ->with('success', 'Knowledge Pack berhasil ditambahkan.');
    }

    public function updatePack(Request $request, KnowledgePack $pack)
    {
        $user = $request->user();
        abort_unless(in_array($user->role, ['admin', 'planner'], true), 403, 'Aksi hanya diizinkan untuk Admin dan Perencana.');

        $pack->load('sector');
        abort_if(is_null($pack->sector) || is_null($pack->sector->organization_id), 403, 'Knowledge pack pada sektor global tidak dapat diubah.');
        abort_unless($pack->sector->organization_id === $user->organization_id, 403, 'Anda tidak memiliki akses ke knowledge pack ini.');

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
            'source' => ['nullable', 'string', 'max:255'],
            'version' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $pack->update([
            'title' => $data['title'],
            'content' => $data['content'],
            'source' => $data['source'] ?? null,
            'version' => $data['version'] ?? $pack->version,
            'is_active' => $request->has('is_active') ? $request->boolean('is_active') : $pack->is_active,
        ]);

        return redirect()->route('knowledge-packs.index', ['sector_id' => $pack->sector_id])
            ->with('success', 'Knowledge Pack berhasil diperbarui.');
    }

    public function destroyPack(Request $request, KnowledgePack $pack)
    {
        $user = $request->user();
        abort_unless(in_array($user->role, ['admin', 'planner'], true), 403, 'Aksi hanya diizinkan untuk Admin dan Perencana.');

        $pack->load('sector');
        abort_if(is_null($pack->sector) || is_null($pack->sector->organization_id), 403, 'Knowledge pack pada sektor global tidak dapat dihapus.');
        abort_unless($pack->sector->organization_id === $user->organization_id, 403, 'Anda tidak memiliki akses ke knowledge pack ini.');

        $sectorId = $pack->sector_id;
        $pack->delete();

        return redirect()->route('knowledge-packs.index', ['sector_id' => $sectorId])
            ->with('success', 'Knowledge Pack berhasil dihapus.');
    }

    public function togglePack(Request $request, KnowledgePack $pack)
    {
        $user = $request->user();
        abort_unless(in_array($user->role, ['admin', 'planner'], true), 403, 'Aksi hanya diizinkan untuk Admin dan Perencana.');

        $pack->load('sector');
        abort_if(is_null($pack->sector) || is_null($pack->sector->organization_id), 403, 'Knowledge pack pada sektor global tidak dapat diubah.');
        abort_unless($pack->sector->organization_id === $user->organization_id, 403, 'Anda tidak memiliki akses ke knowledge pack ini.');

        $pack->update([
            'is_active' => ! $pack->is_active,
        ]);

        return redirect()->route('knowledge-packs.index', ['sector_id' => $pack->sector_id])
            ->with('success', 'Status Knowledge Pack berhasil diubah.');
    }
}
