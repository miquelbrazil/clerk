<?php

declare(strict_types=1);

namespace App\Model\Table;

use App\Model\Entity\ImportBatch;
use Cake\ORM\Table;
use Cake\Validation\Validator;
use RuntimeException;

/**
 * Import batches: one row per ingestion run.
 *
 * @method \App\Model\Entity\ImportBatch newEmptyEntity()
 * @method \App\Model\Entity\ImportBatch newEntity(array<string, mixed> $data, array<string, mixed> $options = [])
 */
class ImportBatchesTable extends Table
{
    /**
     * @param array<string, mixed> $config
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('import_batches');
        $this->setPrimaryKey('id');
        $this->setEntityClass(ImportBatch::class);

        $this->addBehavior('Timestamp');
    }

    /**
     * Typed accessor: Table::get() is declared as returning EntityInterface,
     * which loses the concrete entity type for static analysis.
     */
    public function getBatch(int $id): ImportBatch
    {
        $entity = $this->get($id);

        if (!$entity instanceof ImportBatch) {
            throw new RuntimeException(sprintf('Record %d is not an ImportBatch.', $id));
        }

        return $entity;
    }

    public function validationDefault(Validator $validator): Validator
    {
        return $validator
            ->notEmptyString('source_system')
            ->maxLength('source_system', 64)
            ->maxLength('source_file', 512)
            ->notEmptyString('status')
            ->maxLength('status', 32);
    }
}
