<?php

declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * @property int $id
 * @property string $source_system
 * @property string|null $source_file
 * @property string $status
 * @property string|null $notes
 * Datetime columns hydrate as Cake\I18n\DateTime. cakephp/i18n is a required
 * dependency rather than an optional one: the ORM's Timestamp behavior
 * instantiates that class directly and fatals without it.
 *
 * @property \Cake\I18n\DateTime|null $started_at
 * @property \Cake\I18n\DateTime|null $completed_at
 * @property \Cake\I18n\DateTime $created
 * @property \Cake\I18n\DateTime $modified
 */
class ImportBatch extends Entity
{
    /**
     * @var array<string, bool>
     */
    protected array $_accessible = [
        'source_system' => true,
        'source_file' => true,
        'status' => true,
        'notes' => true,
        'started_at' => true,
        'completed_at' => true,
    ];
}
