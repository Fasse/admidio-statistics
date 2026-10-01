<?php
/**
 * The codec that stores a condition.
 *
 * Version 3 had this transformation three times over, and the copy that ran in the browser passed a
 * plain string to String.replace, which replaces only the first occurrence - so a condition with two
 * comparisons came back wrong.
 *
 * @copyright 2004-2025 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */

namespace AdmidioPlugin\Statistics\Tests\Unit;

require_once dirname(__DIR__) . '/autoload.php';

use AdmidioPlugin\Statistics\Service\ConditionCompiler;
use PHPUnit\Framework\TestCase;

final class ConditionCompilerTest extends TestCase
{
    public function testTheComparisonCharactersAreStoredAsBraces(): void
    {
        $this->assertSame('}=18 AND {=60', ConditionCompiler::encode('>=18 AND <=60'));
        $this->assertSame('>=18 AND <=60', ConditionCompiler::decode('}=18 AND {=60'));
    }

    /**
     * The regression test for the browser copy of this codec.
     */
    public function testEveryOccurrenceIsReplacedAndNotOnlyTheFirst(): void
    {
        $this->assertSame('}1 }2 {3 {4', ConditionCompiler::encode('>1 >2 <3 <4'));
        $this->assertSame('>1 >2 <3 <4', ConditionCompiler::decode('}1 }2 {3 {4'));
    }

    /**
     * @dataProvider conditions
     */
    public function testEncodingAndDecodingAreInverse(string $condition): void
    {
        $this->assertSame($condition, ConditionCompiler::decode(ConditionCompiler::encode($condition)));
    }

    /**
     * @return array<int,array<int,string>>
     */
    public static function conditions(): array
    {
        return array(
            array(''),
            array('>=18'),
            array('<30'),
            array('>=18 AND <=65'),
            array('VORHANDEN'),
            array('no comparison at all'),
            array('>=0j AND <=6j')
        );
    }

    public function testAnEmptyConditionStaysEmpty(): void
    {
        $this->assertSame('', ConditionCompiler::encode(''));
        $this->assertSame('', ConditionCompiler::decode(''));
    }

    /**
     * A row that has no condition holds null in the database.
     */
    public function testNullReadsAsAnEmptyCondition(): void
    {
        $this->assertSame('', ConditionCompiler::decode(null));
    }
}
