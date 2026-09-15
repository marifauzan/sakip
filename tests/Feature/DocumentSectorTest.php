<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\Organization;
use App\Models\Sector;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentSectorTest extends TestCase
{
    use RefreshDatabase;

    private Organization $orgA;
    private Organization $orgB;
    private User $userA;
    private User $userB;
    private Sector $globalSector;
    private Document $document;

    protected function setUp(): void
    {
        parent::setUp();

        $this->orgA = Organization::create(['name' => 'Org A', 'type' => 'kementerian_lembaga']);
        $this->orgB = Organization::create(['name' => 'Org B', 'type' => 'pemerintah_daerah']);

        $this->userA = User::create([
            'name' => 'User A', 'email' => 'a@test.test', 'password' => bcrypt('password'),
            'organization_id' => $this->orgA->id, 'role' => 'planner',
        ]);
        $this->userB = User::create([
            'name' => 'User B', 'email' => 'b@test.test', 'password' => bcrypt('password'),
            'organization_id' => $this->orgB->id, 'role' => 'planner',
        ]);

        $this->globalSector = Sector::create([
            'organization_id' => null, 'name' => 'Pertanian', 'slug' => 'pertanian',
        ]);

        $this->document = Document::create([
            'organization_id' => $this->orgA->id,
            'title' => 'Renstra Test',
            'type' => 'renstra',
            'status' => 'extracted',
            'file_path' => 'documents/test.pdf',
            'file_name' => 'test.pdf',
            'file_mime' => 'application/pdf',
            'file_size' => 100,
        ]);
    }

    public function test_confirm_sector_sets_confirmed_timestamp(): void
    {
        $this->actingAs($this->userA)
            ->post("/documents/{$this->document->id}/confirm-sector", [
                'sector_id' => $this->globalSector->id,
            ])
            ->assertRedirect();

        $this->document->refresh();
        $this->assertSame($this->globalSector->id, $this->document->sector_id);
        $this->assertNotNull($this->document->sector_confirmed_at);
    }

    public function test_confirm_sector_can_clear_sector(): void
    {
        $this->document->update(['sector_id' => $this->globalSector->id]);

        $this->actingAs($this->userA)
            ->post("/documents/{$this->document->id}/confirm-sector", ['sector_id' => null])
            ->assertRedirect();

        $this->document->refresh();
        $this->assertNull($this->document->sector_id);
        $this->assertNotNull($this->document->sector_confirmed_at);
    }

    public function test_user_cannot_confirm_sector_of_other_organization(): void
    {
        $this->actingAs($this->userB)
            ->post("/documents/{$this->document->id}/confirm-sector", [
                'sector_id' => $this->globalSector->id,
            ])
            ->assertForbidden();
    }

    public function test_user_cannot_confirm_with_sector_from_other_organization(): void
    {
        $otherSector = Sector::create([
            'organization_id' => $this->orgB->id, 'name' => 'Sektor B', 'slug' => 'sektor-b',
        ]);

        $this->actingAs($this->userA)
            ->post("/documents/{$this->document->id}/confirm-sector", [
                'sector_id' => $otherSector->id,
            ])
            ->assertForbidden();
    }

    public function test_show_page_exposes_sector_and_confirmation_state(): void
    {
        $this->document->update([
            'sector_id' => $this->globalSector->id,
            'sector_confirmed_at' => now(),
        ]);

        $this->actingAs($this->userA)
            ->get("/documents/{$this->document->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Documents/Show')
                ->where('document.sector.name', 'Pertanian')
                ->where('document.sector_confirmed', true)
                ->has('availableSectors', 1)
            );
    }
}
