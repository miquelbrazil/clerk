<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * The first real table: every ingestion run is an import batch.
 *
 * Phase 3 builds out the full canonical schema around this; the batch concept
 * lands first because it is what makes every later ingestion attributable and
 * reversible (docs/plan.md Phase 3).
 */
final class CreateImportBatches extends AbstractMigration
{
    public function change(): void
    {
        $this->table('import_batches')
            ->addColumn('source_system', 'string', ['limit' => 64])
            ->addColumn('source_file', 'string', ['limit' => 512, 'null' => true])
            ->addColumn('status', 'string', ['limit' => 32, 'default' => 'pending'])
            ->addColumn('notes', 'text', ['null' => true])
            ->addColumn('started_at', 'timestamp', ['null' => true])
            ->addColumn('completed_at', 'timestamp', ['null' => true])
            ->addColumn('created', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('modified', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['source_system'])
            ->addIndex(['status'])
            ->create();
    }
}
