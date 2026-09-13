<?php

namespace Tests\Feature;

use App\Models\Sector;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_seeder_executes_successfully(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseHas('organizations', ['code' => 'KEMENTAN']);
        $this->assertDatabaseHas('users', ['email' => 'admin@kementan.test']);
        $this->assertDatabaseHas('users', ['email' => 'planner@kementan.test']);
        $this->assertDatabaseHas('users', ['email' => 'reviewer@kementan.test']);
        $this->assertDatabaseHas('kinerja_trees', ['name' => 'Renstra Kementan 2025–2029']);
        $sektorPertanian = Sector::where('slug', 'pertanian')->first();
        $this->assertNotNull($sektorPertanian);
        $this->assertDatabaseHas('kinerja_trees', [
            'name' => 'Renstra Kementan 2025–2029',
            'sector_id' => $sektorPertanian->id,
        ]);
        $this->assertDatabaseHas('nodes', ['code' => 'SS']);
        $this->assertDatabaseHas('reviews', ['decision' => 'approve']);
    }
}
