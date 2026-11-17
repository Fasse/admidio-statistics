<?php

declare(strict_types=1);

namespace AdmidioPlugin\Statistics\Service;

use Admidio\Infrastructure\Database;
use AdmidioPlugin\Statistics\ValueObject\StatisticDefinition;
use AdmidioPlugin\Statistics\ValueObject\StatisticResult;

/**
 * Keeps the computed figures of a statistic, for the place that shows them on every page view.
 *
 * Every cell of a statistic is at least one query, and the overview page is the page everybody
 * opens. So the widget there asks this class first, and the statistic is only computed again when
 * its figures can have changed.
 *
 * A stored result is used under the key it was stored with, and the key is made of:
 *
 * - the definition of the statistic, so that editing it shows at once;
 * - the day, so that the figures are new every morning;
 * - the language and the date format, because the result holds finished labels.
 *
 * Everything else a figure is computed from - the users and their profile values, the memberships,
 * the roles and their names, the profile fields and their selection options - is watched through
 * the hooks of these entities: a change of any of them throws every stored result away. A
 * fingerprint of the tables could not see all of these changes, because Admidio does not move the
 * change timestamp of a record its creator changes again within a quarter of an hour, and the
 * selection options have no timestamp at all. A change that bypasses the entities, such as a direct
 * database edit, shows on the next day.
 *
 * There is one stored result per statistic, the latest, so the table does not grow. It goes with its
 * statistic through the foreign key.
 *
 * The cache never stands in the way: when it cannot be read, written or emptied, for whatever
 * reason, the statistic is computed and shown as if there were no cache.
 *
 * @copyright 2004-2025 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
final class StatisticCache
{
    /**
     * The longest result that is stored, in bytes. The column is a text column, which MySQL limits
     * to 65535 bytes; a statistic larger than this is simply computed every time.
     */
    private const MAX_LENGTH = 60000;

    /**
     * The entities whose changes can change a figure, by the ID of their hooks.
     */
    public const WATCHED_ENTITIES = array(
        'user',
        'user_data',
        'membership',
        'role',
        'profile_field',
        'profile_field_select_option'
    );

    /**
     * The stages of those hooks after which the change is in the database.
     */
    public const WATCHED_STAGES = array('created', 'updated', 'deleted');

    /**
     * The registration ID of the callbacks, so that they can be removed again.
     */
    public const HOOK_ID = 'statistics_cache';

    /**
     * @param Database $database
     */
    public function __construct(private readonly Database $database)
    {
    }

    /**
     * The name of the table, with the table prefix of this installation.
     *
     * @return string
     */
    public static function table(): string
    {
        return TABLE_PREFIX . '_statistics_cache';
    }

    /**
     * The names of the hooks after which the stored results are thrown away: the specific hooks of
     * the watched entities, so that the far more frequent changes of everything else cost nothing.
     *
     * @return array<int,string>
     */
    public static function invalidatingHooks(): array
    {
        $hooks = array();
        foreach (self::WATCHED_ENTITIES as $entity) {
            foreach (self::WATCHED_STAGES as $stage) {
                $hooks[] = $entity . '_' . $stage;
            }
        }

        return $hooks;
    }

    /**
     * Throw away every stored result, after a change of data a figure may be computed from.
     *
     * Admidio calls it when the transaction of the change commits, never for a change that was
     * rolled back. It must not fail whatever the caller does next, so a failure is swallowed.
     *
     * @return void
     */
    public static function invalidate(): void
    {
        global $gDb;

        if (!$gDb instanceof Database) {
            return;
        }

        try {
            $gDb->queryPrepared('DELETE FROM ' . self::table(), array(), false);
        } catch (\Throwable) {
            // Nothing is lost: the results are computed again on the next day at the latest.
        }
    }

    /**
     * The result of a statistic: the stored one while it is still right, a newly computed one
     * otherwise.
     *
     * @param StatisticDefinition $definition
     * @param string $date The day (Y-m-d) the figures are of.
     * @param string $context Whatever else the finished result depends on, such as the language.
     * @param callable $compute callable(): StatisticResult
     * @return StatisticResult
     */
    public function remember(StatisticDefinition $definition, string $date, string $context, callable $compute): StatisticResult
    {
        if ($definition->id === null) {
            return $compute();
        }

        $key = $this->key($definition, $date, $context);

        if ($key !== null) {
            $stored = $this->read($definition->id, $key);
            if ($stored !== null) {
                return $stored;
            }
        }

        $result = $compute();

        if ($key !== null) {
            $this->write($definition->id, $key, $result);
        }

        return $result;
    }

    /**
     * @param StatisticDefinition $definition
     * @param string $date
     * @param string $context
     * @return string|null **null** when the definition cannot be encoded, so nothing is cached.
     */
    private function key(StatisticDefinition $definition, string $date, string $context): ?string
    {
        try {
            return hash('sha256', json_encode(array($definition, $date, $context), JSON_THROW_ON_ERROR));
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param int $statisticId
     * @param string $key
     * @return StatisticResult|null
     */
    private function read(int $statisticId, string $key): ?StatisticResult
    {
        try {
            $sql = 'SELECT sca_data FROM ' . self::table() . ' WHERE sca_sta_id = ? AND sca_key = ?';
            $statement = $this->database->queryPrepared($sql, array($statisticId, $key), false);
            $data = $statement === false ? false : $statement->fetchColumn();

            if (!is_string($data)) {
                return null;
            }

            return StatisticResult::fromArray(json_decode($data, true, 16, JSON_THROW_ON_ERROR));
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param int $statisticId
     * @param string $key
     * @param StatisticResult $result
     * @return void
     */
    private function write(int $statisticId, string $key, StatisticResult $result): void
    {
        try {
            $data = json_encode($result->toArray(), JSON_THROW_ON_ERROR);
            if (strlen($data) > self::MAX_LENGTH) {
                return;
            }

            /*
             * Two page views may get here at the same moment. The second insert then fails on the
             * primary key, and that is fine: both computed the same result.
             */
            $sql = 'DELETE FROM ' . self::table() . ' WHERE sca_sta_id = ?';
            $this->database->queryPrepared($sql, array($statisticId), false);

            $sql = 'INSERT INTO ' . self::table() . ' (sca_sta_id, sca_key, sca_data) VALUES (?, ?, ?)';
            $this->database->queryPrepared($sql, array($statisticId, $key, $data), false);
        } catch (\Throwable) {
            // A result that could not be stored is computed again next time.
        }
    }
}
