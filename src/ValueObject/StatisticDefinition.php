<?php

declare(strict_types=1);

namespace AdmidioPlugin\Statistics\ValueObject;

/**
 * A complete statistic as it is configured and stored: its metadata and its tables.
 *
 * The named constructor fromRecord() is the only way a definition is built out of the database, and
 * it reads every value by column name. Version 3 had a constructor of six positional values and
 * called it with the name where the organization belonged, so every statistic it read carried the
 * name in orgId and the organization in name - and saving it back wrote a name into the integer
 * sta_org_id column. Reading by name, and the typed properties below, make that unexpressible.
 *
 * @copyright 2004-2025 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
final class StatisticDefinition
{
    /** @var array<int,TableDefinition> */
    public readonly array $tables;

    /**
     * @param int|null $id The database id, or **null** for a statistic that has not been saved yet.
     * @param int $orgId The organization the statistic belongs to.
     * @param string $name The name the statistic is listed and loaded under.
     * @param string $title The headline of the rendered statistic.
     * @param string $subtitle The second line of the rendered statistic.
     * @param int $standardRoleId The role every table counts unless it names one of its own.
     * @param array<int,TableDefinition> $tables
     */
    public function __construct(
        public readonly ?int $id,
        public readonly int $orgId,
        public readonly string $name = '',
        public readonly string $title = '',
        public readonly string $subtitle = '',
        public readonly int $standardRoleId = 0,
        array $tables = array()
    ) {
        $this->tables = array_values($tables);
    }

    /**
     * Build a statistic from its database record, keyed by column name.
     *
     * @param array<string,mixed> $record A record of the statistics table.
     * @param array<int,TableDefinition> $tables
     * @return self
     */
    public static function fromRecord(array $record, array $tables): self
    {
        return new self(
            (int) $record['sta_id'],
            (int) $record['sta_org_id'],
            (string) ($record['sta_name'] ?? ''),
            (string) ($record['sta_title'] ?? ''),
            (string) ($record['sta_subtitle'] ?? ''),
            (int) ($record['sta_std_role'] ?? 0),
            $tables
        );
    }

    /**
     * The statistic the editor starts from when no saved one was chosen: one table with one column
     * and one row, as version 3 built it in createEmptyStatistic(). Unlike version 3 this carries
     * no id, because there is no scratch row any more, and it does not guess a standard role - the
     * editor asks for one and will not save without it.
     *
     * @param int $orgId
     * @return self
     */
    public static function createEmpty(int $orgId): self
    {
        return new self(null, $orgId, '', '', '', 0, array(TableDefinition::createEmpty()));
    }

    /**
     * @return bool Whether this statistic still has to be inserted.
     */
    public function isNew(): bool
    {
        return $this->id === null;
    }

    /**
     * @param array<int,TableDefinition> $tables
     * @return self
     */
    public function withTables(array $tables): self
    {
        return new self(
            $this->id,
            $this->orgId,
            $this->name,
            $this->title,
            $this->subtitle,
            $this->standardRoleId,
            $tables
        );
    }

    /**
     * Used when the editor saves a loaded statistic under a new name, which has to become a new
     * record rather than overwrite the one it was read from.
     *
     * @param string $name
     * @return self
     */
    public function asCopyNamed(string $name): self
    {
        return new self(
            null,
            $this->orgId,
            $name,
            $this->title,
            $this->subtitle,
            $this->standardRoleId,
            $this->tables
        );
    }
}
