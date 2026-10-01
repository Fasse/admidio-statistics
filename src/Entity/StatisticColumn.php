<?php

declare(strict_types=1);

namespace AdmidioPlugin\Statistics\Entity;

use Admidio\Infrastructure\Database;
use Admidio\Infrastructure\Entity\Entity;
use Admidio\Infrastructure\Exception;

/**
 * One configured column of a statistic table, with its condition and its two functions.
 *
 * @copyright 2004-2025 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
class StatisticColumn extends Entity
{
    /**
     * @param Database $database
     * @param int $id The ID of the record that should be read, or 0 for a new one.
     * @throws Exception
     */
    public function __construct(Database $database, int $id = 0)
    {
        parent::__construct($database, self::table(), 'stc', $id);
    }

    /**
     * The name of the table, with the table prefix of this installation.
     * @return string
     */
    public static function table(): string
    {
        return TABLE_PREFIX . '_statistics_columns';
    }
}
