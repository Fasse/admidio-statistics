<?php

declare(strict_types=1);

namespace AdmidioPlugin\Statistics\Service;

/**
 * The one place that knows how a condition is stored.
 *
 * A condition is typed with the comparison characters as themselves, but it is stored with "<" as
 * "{" and ">" as "}". That is not decoration: Admidio strips tags from a submitted string, so a
 * condition travelling as itself would lose anything that looks like markup. The encoding is kept
 * exactly as version 3 wrote it, so conditions already in the database keep working.
 *
 * Version 3 had this transformation in three places - the persistence layer, the evaluator and the
 * editor's JavaScript - and the JavaScript one passed a plain string to String.replace, which
 * replaces only the first occurrence, so a condition such as ">=18 AND <=60" was mangled on its way
 * through the browser. There is one implementation now, and it runs on the server.
 *
 * @copyright 2004-2025 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
final class ConditionCompiler
{
    /**
     * @var array<int,string> The characters as the user types them.
     */
    private const PLAIN = array('>', '<');

    /**
     * @var array<int,string> The characters as the database holds them.
     */
    private const STORED = array('}', '{');

    /**
     * Turn a condition as the user typed it into the form the database holds.
     *
     * @param string $condition
     * @return string
     */
    public static function encode(string $condition): string
    {
        if ($condition === '') {
            return '';
        }

        return str_replace(self::PLAIN, self::STORED, $condition);
    }

    /**
     * Turn a stored condition back into the form the user typed and the evaluator parses.
     *
     * @param mixed $condition The stored value, which may be null for a row that has none.
     * @return string
     */
    public static function decode(mixed $condition): string
    {
        $condition = (string) $condition;
        if ($condition === '') {
            return '';
        }

        return str_replace(self::STORED, self::PLAIN, $condition);
    }
}
