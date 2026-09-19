<?php

declare(strict_types=1);

namespace Tests\Unit\Config;

use Tests\TestCase;

final class QueueConfigTest extends TestCase
{
    public function test_database_jobs_are_enqueued_only_after_transaction_commit(): void
    {
        $this->assertTrue(config('queue.connections.database.after_commit'));
    }
}
