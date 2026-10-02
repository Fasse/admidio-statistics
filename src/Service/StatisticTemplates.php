<?php

declare(strict_types=1);

namespace AdmidioPlugin\Statistics\Service;

use Admidio\Infrastructure\Exception;
use AdmidioPlugin\Statistics\ValueObject\ColumnDefinition;
use AdmidioPlugin\Statistics\ValueObject\RowDefinition;
use AdmidioPlugin\Statistics\ValueObject\StatisticDefinition;
use AdmidioPlugin\Statistics\ValueObject\StatisticFunction;
use AdmidioPlugin\Statistics\ValueObject\TableDefinition;

/**
 * The two example statistics, built in PHP rather than seeded by the installation.
 *
 * Version 3 inserted them from install.sql, which could not work properly: the rows named their
 * profile fields by the numbers 1 to 7, 10 and 11, which are only the right ones on an Admidio that
 * still has its fields in the order it was installed with, and the labels were written in whatever
 * language the installation happened to be running in and then stayed that way for good.
 *
 * Here the fields are looked up by their internal name, a row whose field this installation does not
 * have is left out, and every label is translated at the moment the example is created. An
 * administrator asks for them from the editor, so nothing is written during installation at all.
 *
 * @copyright 2004-2025 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
final class StatisticTemplates
{
    /**
     * The age bands of the age statistic, as the condition the parser reads. The suffix stands for
     * years of age and ConditionParser accepts it in any language.
     *
     * @var array<string,string>
     */
    private const AGE_BANDS = array(
        '0-6' => '>=0j AND <=6j',
        '7-14' => '>=7j AND <=14j',
        '15-18' => '>=15j AND <=18j',
        '19-26' => '>=19j AND <=26j',
        '27-40' => '>=27j AND <=40j',
        '41-60' => '>=41j AND <=60j',
        '>= 61' => '>=61j'
    );

    /**
     * The profile fields the completeness statistic reports on, by internal name.
     *
     * @var array<string,string>
     */
    private const COMPLETENESS_FIELDS = array(
        'LAST_NAME' => 'SYS_LASTNAME',
        'FIRST_NAME' => 'SYS_FIRSTNAME',
        'ADDRESS' => 'SYS_ADDRESS',
        'POSTCODE' => 'SYS_POSTCODE',
        'CITY' => 'SYS_CITY',
        'COUNTRY' => 'SYS_COUNTRY',
        'PHONE' => 'SYS_PHONE'
    );

    /**
     * Create the examples this installation can actually show.
     *
     * @param StatisticRepository $repository
     * @param int $orgId
     * @param int $standardRoleId The role the examples count, which the editor takes from the field
     *                            the administrator has chosen.
     * @return int How many examples were created.
     * @throws Exception
     */
    public static function create(StatisticRepository $repository, int $orgId, int $standardRoleId = 0): int
    {
        /*
         * The role of the editor's form if one is chosen there, and otherwise the first role this
         * user may see. The examples are examples: they have to count something, and an administrator
         * can point them at another role afterwards. Version 3 wrote the number 2 into them.
         */
        if ($standardRoleId <= 0) {
            $standardRoleId = StatisticFormService::firstSelectableRoleId() ?? 0;
        }

        if ($standardRoleId <= 0) {
            throw new Exception('PLG_STATISTICS_NO_ROLE_AVAILABLE');
        }

        $created = 0;

        foreach (array(self::ageStatistic($orgId, $standardRoleId), self::completeness($orgId, $standardRoleId)) as $example) {
            if ($example === null) {
                continue;
            }

            $repository->save($example);
            $created++;
        }

        return $created;
    }

    /**
     * Members by age band and gender.
     *
     * @param int $orgId
     * @param int $standardRoleId
     * @return StatisticDefinition|null **null** when this installation has no birthday field, which
     *         is what the whole example is about.
     * @throws Exception
     */
    private static function ageStatistic(int $orgId, int $standardRoleId): ?StatisticDefinition
    {
        global $gL10n;

        $birthday = self::fieldId('BIRTHDAY');
        if ($birthday === null) {
            return null;
        }

        $rows = array();
        foreach (self::AGE_BANDS as $label => $condition) {
            $rows[] = new RowDefinition($gL10n->get('PLG_STATISTICS_XY_YEARS', array($label)), $birthday, $condition);
        }
        $rows[] = new RowDefinition(
            $gL10n->get('PLG_STATISTICS_NO_INFORMATION'),
            $birthday,
            $gL10n->get('PLG_STATISTICS_MISSING')
        );

        $gender = self::fieldId('GENDER');
        $columns = array();

        if ($gender !== null) {
            /*
             * The gender is a selection, so the condition names one of the entries it offers by its
             * label - which is how the evaluator looks a selection up.
             */
            foreach (array('SYS_MALE', 'SYS_FEMALE') as $genderLabel) {
                $columns[] = new ColumnDefinition(
                    $gL10n->get($genderLabel),
                    $gender,
                    $gL10n->get($genderLabel),
                    StatisticFunction::COUNT,
                    null,
                    StatisticFunction::SUM
                );
            }

            $columns[] = new ColumnDefinition(
                $gL10n->get('PLG_STATISTICS_NO_INFORMATION'),
                $gender,
                $gL10n->get('PLG_STATISTICS_MISSING'),
                StatisticFunction::COUNT,
                null,
                StatisticFunction::SUM
            );
        }

        $columns[] = new ColumnDefinition(
            $gL10n->get('PLG_STATISTICS_TOTAL'),
            null,
            '',
            StatisticFunction::COUNT,
            null,
            StatisticFunction::SUM
        );

        $table = new TableDefinition(
            $gL10n->get('PLG_STATISTICS_BY_AGE_GROUPS'),
            null,
            $gL10n->get('PLG_STATISTICS_AGE_GROUPS'),
            $columns,
            $rows
        );

        return new StatisticDefinition(
            null,
            $orgId,
            $gL10n->get('PLG_STATISTICS_AGE_STATISTICS'),
            $gL10n->get('PLG_STATISTICS_AGE_STATISTICS'),
            $gL10n->get('PLG_STATISTICS_YEAR') . ' ' . date('Y'),
            $standardRoleId,
            array($table)
        );
    }

    /**
     * How complete the profiles are: one table counting the fields that are filled in and one
     * counting the fields that are not.
     *
     * @param int $orgId
     * @param int $standardRoleId
     * @return StatisticDefinition|null **null** when none of the fields it reports on exists here.
     * @throws Exception
     */
    private static function completeness(int $orgId, int $standardRoleId): ?StatisticDefinition
    {
        global $gL10n;

        $available = self::completenessTable(
            $gL10n->get('PLG_STATISTICS_AFTER_COMPLETENESS'),
            $gL10n->get('PLG_STATISTICS_AVAILABLE')
        );
        $missing = self::completenessTable(
            $gL10n->get('PLG_STATISTICS_AFTER_MISSING_INFORMATIONS'),
            $gL10n->get('PLG_STATISTICS_MISSING')
        );

        if ($available === null || $missing === null) {
            return null;
        }

        return new StatisticDefinition(
            null,
            $orgId,
            $gL10n->get('PLG_STATISTICS_PROFILE_COMPLETENESS'),
            $gL10n->get('PLG_STATISTICS_COMPLETENESS_OF_PROFILE'),
            '',
            $standardRoleId,
            array($available, $missing)
        );
    }

    /**
     * @param string $title
     * @param string $condition The keyword that asks whether the field has a value.
     * @return TableDefinition|null **null** when none of the fields exists here.
     * @throws Exception
     */
    private static function completenessTable(string $title, string $condition): ?TableDefinition
    {
        global $gL10n;

        $rows = array();
        foreach (self::COMPLETENESS_FIELDS as $nameIntern => $label) {
            $fieldId = self::fieldId($nameIntern);
            if ($fieldId === null) {
                continue;
            }

            $rows[] = new RowDefinition($gL10n->get($label), $fieldId, $condition);
        }

        if ($rows === array()) {
            return null;
        }

        // The last row counts everybody, which is what the percentages above are a share of.
        $rows[] = new RowDefinition($gL10n->get('PLG_STATISTICS_POSSIBLE_INFORMATIONS'), null, '');

        $columns = array(
            new ColumnDefinition($gL10n->get('PLG_STATISTICS_NUMBER'), null, '', StatisticFunction::COUNT),
            new ColumnDefinition($gL10n->get('PLG_STATISTICS_PERCENT'), null, '', StatisticFunction::PERCENT)
        );

        return new TableDefinition($title, null, $gL10n->get('SYS_PROFILE_FIELD'), $columns, $rows);
    }

    /**
     * The id of a profile field, by the internal name that does not change when an administrator
     * renames or reorders the fields.
     *
     * @param string $nameIntern
     * @return int|null **null** when this installation has no such field.
     */
    private static function fieldId(string $nameIntern): ?int
    {
        global $gProfileFields;

        $id = (int) $gProfileFields->getProperty($nameIntern, 'usf_id');

        return $id > 0 ? $id : null;
    }
}
