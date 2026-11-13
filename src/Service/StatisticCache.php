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
 * Whether they can is decided by a key, and a result is only used under the key it was stored with.
 * The key is made of everything the figures depend on:
 *
 * - the definition of the statistic, so that editing it shows at once;
 * - the day, so that the figures are new every morning;
 * - the language and the date format, because the result holds finished labels;
 * - a fingerprint of the memberships and of the users: how many there are and when one was last
 *   created or changed. A membership that is added, ended or deleted moves it, and so does a profile
 *   that is saved.
 *
 * There is one stored result per statistic, the latest, so the table does not grow. It goes with its
 * statistic through the foreign key.
 *
 * The cache never stands in the way: when it cannot be read or written, for whatever reason, the
 * statistic is computed and shown as if there were no cache.
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
     * @return string|null **null** when the state of the data cannot be read, so nothing is cached.
     */
    private function key(StatisticDefinition $definition, string $date, string $context): ?string
    {
        $fingerprint = $this->dataFingerprint();
        if ($fingerprint === null) {
            return null;
        }

        try {
            return hash('sha256', json_encode(array($definition, $date, $context, $fingerprint), JSON_THROW_ON_ERROR));
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * The state of everything a figure is computed from, in one query.
     *
     * @return string|null
     */
    private function dataFingerprint(): ?string
    {
        $sql = 'SELECT (SELECT COUNT(*) FROM ' . TBL_MEMBERS . ') AS members,
                       (SELECT MAX(mem_timestamp_create) FROM ' . TBL_MEMBERS . ') AS member_created,
                       (SELECT MAX(mem_timestamp_change) FROM ' . TBL_MEMBERS . ') AS member_changed,
                       (SELECT COUNT(*) FROM ' . TBL_USERS . ') AS users,
                       (SELECT MAX(usr_timestamp_create) FROM ' . TBL_USERS . ') AS user_created,
                       (SELECT MAX(usr_timestamp_change) FROM ' . TBL_USERS . ') AS user_changed';

        try {
            $statement = $this->database->queryPrepared($sql, array(), false);
            $record = $statement === false ? false : $statement->fetch(\PDO::FETCH_NUM);
        } catch (\Throwable) {
            return null;
        }

        return $record === false ? null : implode('|', array_map('strval', $record));
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
