<?php

namespace Tests\Feature\Console\Commands;

use App\Models\OntStatusHistory;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class PruneOntStatusHistoryTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_it_deletes_history_beyond_retention_and_keeps_recent_rows(): void
    {
        $expired = OntStatusHistory::factory()->create([
            'observed_at' => now()->subDays(100),
        ]);
        $recent = OntStatusHistory::factory()->create([
            'observed_at' => now()->subDays(10),
        ]);

        $this->artisan('genieacs:prune-history')->assertSuccessful();

        $this->assertDatabaseMissing('ont_status_histories', ['id' => $expired->id]);
        $this->assertDatabaseHas('ont_status_histories', ['id' => $recent->id]);
    }

    public function test_retention_is_clamped_to_the_minimum_of_thirty_days(): void
    {
        $twentyDaysOld = OntStatusHistory::factory()->create([
            'observed_at' => now()->subDays(20),
        ]);

        $this->artisan('genieacs:prune-history', ['--days' => 5])->assertSuccessful();

        $this->assertDatabaseHas('ont_status_histories', ['id' => $twentyDaysOld->id]);
    }

    public function test_retention_is_clamped_to_the_maximum_of_ninety_days(): void
    {
        $hundredDaysOld = OntStatusHistory::factory()->create([
            'observed_at' => now()->subDays(100),
        ]);

        $this->artisan('genieacs:prune-history', ['--days' => 365])->assertSuccessful();

        $this->assertDatabaseMissing('ont_status_histories', ['id' => $hundredDaysOld->id]);
    }

    public function test_it_honours_the_configured_retention_window(): void
    {
        config()->set('genieacs.history_retention_days', 30);
        $thirtyOneDaysOld = OntStatusHistory::factory()->create([
            'observed_at' => now()->subDays(31)->subHour(),
        ]);
        $twentyNineDaysOld = OntStatusHistory::factory()->create([
            'observed_at' => now()->subDays(29),
        ]);

        $this->artisan('genieacs:prune-history')->assertSuccessful();

        $this->assertDatabaseMissing('ont_status_histories', ['id' => $thirtyOneDaysOld->id]);
        $this->assertDatabaseHas('ont_status_histories', ['id' => $twentyNineDaysOld->id]);
    }
}
