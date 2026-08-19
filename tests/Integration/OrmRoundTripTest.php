<?php

declare(strict_types=1);

/*
 * Proves the Phase 1 data stack round-trips: Phinx-migrated Postgres schema →
 * CakeORM Table → Entity → back out of the database.
 */

use App\Model\Entity\ImportBatch;
use App\Model\Table\ImportBatchesTable;
use Cake\ORM\Locator\TableLocator;

/**
 * Resolves the ImportBatches table through a fresh locator.
 */
function importBatchesTable(): ImportBatchesTable
{
    $table = (new TableLocator())->get('ImportBatches', [
        'className' => ImportBatchesTable::class,
    ]);

    if (!$table instanceof ImportBatchesTable) {
        throw new RuntimeException('Expected an ImportBatchesTable instance.');
    }

    return $table;
}

// Every test in this file needs the staging database. Grouping at the file
// level lets a database-less run exclude them (--exclude-group=database)
// while `lando test` exercises them for real.
pest()->group('database');

beforeEach(function (): void {
    if (databaseIsReachable()) {
        importBatchesTable()->deleteAll(['source_system' => 'test-harness']);
    }
});

afterEach(function (): void {
    if (databaseIsReachable()) {
        importBatchesTable()->deleteAll(['source_system' => 'test-harness']);
    }
});

/**
 * Skips a test when the staging database is unreachable.
 */
function skipWithoutDatabase(): Closure
{
    return fn (): bool => !databaseIsReachable();
}

it('round-trips an import batch through the ORM', function (): void {
    $batches = importBatchesTable();

    $batch = $batches->newEntity([
        'source_system' => 'test-harness',
        'source_file' => 'synthetic.csv',
        'status' => 'pending',
    ]);

    expect($batches->save($batch))->not->toBeFalse();
    expect($batch->id)->toBeInt();

    $found = $batches->getBatch($batch->id);

    expect($found)->toBeInstanceOf(ImportBatch::class);
    expect($found->source_system)->toBe('test-harness');
    expect($found->source_file)->toBe('synthetic.csv');
    expect($found->status)->toBe('pending');
    expect($found->created)->not->toBeNull();
})->skip(skipWithoutDatabase(), 'Staging Postgres is not reachable; run inside Lando.');

it('enforces validation on required fields', function (): void {
    $batches = importBatchesTable();

    $batch = $batches->newEntity(['source_system' => '']);

    expect($batches->save($batch))->toBeFalse()
        ->and($batch->getErrors())->toHaveKey('source_system');
})->skip(skipWithoutDatabase(), 'Staging Postgres is not reachable; run inside Lando.');
