<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\KinerjaTree;
use App\Models\KnowledgePack;
use App\Models\Organization;
use App\Models\Sector;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = Organization::create([
            'name' => 'Bappeda Prov. Jateng',
            'type' => 'pemerintah_daerah',
            'code' => 'BAPPEDA-JTG',
        ]);

        $this->user = User::create([
            'name' => 'Budi Santoso',
            'email' => 'budi@bappeda.test',
            'password' => Hash::make('password123'),
            'organization_id' => $this->org->id,
            'role' => 'planner',
        ]);
    }

    public function test_guest_is_redirected_from_dashboard(): void
    {
        $response = $this->get('/dashboard');
        $response->assertRedirect('/login');
    }

    public function test_authenticated_user_can_view_dashboard_with_metrics(): void
    {
        $sector = Sector::create([
            'name' => 'Pertanian & Ketahanan Pangan',
            'code' => 'PERTANIAN',
        ]);

        KnowledgePack::create([
            'sector_id' => $sector->id,
            'title' => 'KP Pertanian 2025',
            'content' => 'Sample content',
            'source' => 'Kementan',
            'version' => 1,
            'is_active' => true,
        ]);

        KinerjaTree::create([
            'organization_id' => $this->org->id,
            'sector_id' => $sector->id,
            'name' => 'Pohon Kinerja Ketahanan Pangan',
            'period_start' => 2025,
            'period_end' => 2029,
            'status' => 'draft',
        ]);

        Document::create([
            'organization_id' => $this->org->id,
            'title' => 'Renstra Dinas Ketahanan Pangan',
            'type' => 'renstra',
            'period_start' => 2025,
            'period_end' => 2029,
            'status' => 'uploaded',
            'file_path' => 'documents/sample.pdf',
            'file_name' => 'sample.pdf',
            'file_mime' => 'application/pdf',
            'file_size' => 1024,
            'version' => 1,
        ]);

        $response = $this->actingAs($this->user)->get('/dashboard');

        $response->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->has('auth.user')
                ->where('auth.user.name', 'Budi Santoso')
                ->where('auth.organization.name', 'Bappeda Prov. Jateng')
                ->where('metrics.trees_count', 1)
                ->where('metrics.documents_count', 1)
                ->where('metrics.knowledge_packs_count', 1)
                ->has('recentTrees', 1)
                ->has('recentDocuments', 1)
            );
    }

    public function test_shared_auth_props_available_on_authenticated_routes(): void
    {
        $response = $this->actingAs($this->user)->get('/documents');

        $response->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Documents/Index')
                ->has('auth.user')
                ->where('auth.user.name', 'Budi Santoso')
                ->where('auth.organization.name', 'Bappeda Prov. Jateng')
            );
    }

    public function test_dashboard_isolates_metrics_across_organizations(): void
    {
        $otherOrg = Organization::create([
            'name' => 'Dinas Pendidikan',
            'type' => 'perangkat_daerah',
            'code' => 'DISDIK-01',
        ]);

        // Global sector + KP (visible to all)
        $globalSector = Sector::create([
            'name' => 'Kesehatan Global',
            'code' => 'KESEHATAN',
        ]);
        KnowledgePack::create([
            'sector_id' => $globalSector->id,
            'title' => 'KP Global Kesehatan',
            'content' => 'Global content',
            'source' => 'Kemenkes',
            'version' => 1,
            'is_active' => true,
        ]);

        // Other organization private sector + KP (must NOT be visible to Budi)
        $otherSector = Sector::create([
            'organization_id' => $otherOrg->id,
            'name' => 'Pendidikan Khusus Disdik',
            'code' => 'DIK-KHUSUS',
        ]);
        KnowledgePack::create([
            'sector_id' => $otherSector->id,
            'title' => 'KP Rahasia Disdik',
            'content' => 'Private content',
            'source' => 'Disdik',
            'version' => 1,
            'is_active' => true,
        ]);

        // Other organization trees & documents
        KinerjaTree::create([
            'organization_id' => $otherOrg->id,
            'name' => 'Pohon Kinerja Disdik',
            'period_start' => 2025,
            'period_end' => 2029,
            'status' => 'draft',
        ]);
        Document::create([
            'organization_id' => $otherOrg->id,
            'title' => 'Renstra Disdik',
            'type' => 'renstra',
            'period_start' => 2025,
            'period_end' => 2029,
            'status' => 'uploaded',
            'file_path' => 'documents/disdik.pdf',
            'file_name' => 'disdik.pdf',
            'file_mime' => 'application/pdf',
            'file_size' => 1024,
            'version' => 1,
        ]);

        $response = $this->actingAs($this->user)->get('/dashboard');

        $response->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('metrics.trees_count', 0)
                ->where('metrics.documents_count', 0)
                ->where('metrics.knowledge_packs_count', 1)
                ->has('recentTrees', 0)
                ->has('recentDocuments', 0)
            );
    }

    public function test_dashboard_renders_for_user_without_organization(): void
    {
        $userWithoutOrg = User::create([
            'name' => 'Siti Admin',
            'email' => 'siti@admin.test',
            'password' => Hash::make('password123'),
            'organization_id' => null,
            'role' => 'admin',
        ]);

        $response = $this->actingAs($userWithoutOrg)->get('/dashboard');

        $response->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('auth.user.name', 'Siti Admin')
                ->where('auth.organization', null)
                ->where('metrics.trees_count', 0)
                ->where('metrics.documents_count', 0)
                ->has('recentTrees', 0)
                ->has('recentDocuments', 0)
            );
    }
}
