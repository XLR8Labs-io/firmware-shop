<?php

namespace Tests\Concerns;

/**
 * InnoDB only adds rows to a FULLTEXT index when their transaction commits, so a
 * full-text search can't see rows written inside RefreshDatabase's rollback.
 * Tests using this trait commit their writes and must clean up the tables they touch.
 */
trait CommitsDatabaseWrites
{
    /**
     * @var array<int, string|null>
     */
    protected array $connectionsToTransact = [];
}
