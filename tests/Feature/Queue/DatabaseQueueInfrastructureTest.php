<?php

declare(strict_types=1);

namespace Tests\Feature\Queue;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class DatabaseQueueInfrastructureTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_queue_tables_are_available(): void
    {
        $this->assertTrue(Schema::hasTable('jobs'));
        $this->assertTrue(Schema::hasTable('failed_jobs'));
        $this->assertTrue(Schema::hasColumns('jobs', [
            'queue',
            'payload',
            'attempts',
            'reserved_at',
            'available_at',
            'created_at',
        ]));
    }
}
