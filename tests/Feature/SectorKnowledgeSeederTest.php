<?php

namespace Tests\Feature;

use App\Models\KnowledgePack;
use App\Models\Sector;
use Database\Seeders\SectorKnowledgeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SectorKnowledgeSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_creates_all_sectors_with_two_packs(): void
    {
        $this->seed(SectorKnowledgeSeeder::class);

        $expected = ['kesehatan', 'infrastruktur', 'ekonomi', 'lingkungan-hidup', 'pariwisata'];

        foreach ($expected as $slug) {
            $sector = Sector::where('slug', $slug)->first();
            $this->assertNotNull($sector, "Sektor {$slug} tidak dibuat.");
            $this->assertSame(2, $sector->knowledgePacks()->count(), "Sektor {$slug} tidak punya 2 pack.");
            $this->assertNull($sector->organization_id, "Sektor {$slug} harus global.");
        }
    }

    public function test_seeder_is_idempotent(): void
    {
        $this->seed(SectorKnowledgeSeeder::class);
        $countAfterFirst = KnowledgePack::count();

        $this->seed(SectorKnowledgeSeeder::class);

        $this->assertSame($countAfterFirst, KnowledgePack::count(), 'Seeder tidak idempotent.');
    }

    public function test_each_pack_has_content_and_source(): void
    {
        $this->seed(SectorKnowledgeSeeder::class);

        foreach (KnowledgePack::all() as $pack) {
            $this->assertNotEmpty(trim($pack->content), "Pack {$pack->title} kosong.");
            $this->assertNotEmpty($pack->source, "Pack {$pack->title} tanpa sumber.");
            $this->assertTrue($pack->is_active);
        }
    }
}
