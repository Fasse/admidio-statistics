<?php
/**
 * The statistics of the overview widget, and the cache their figures come through.
 *
 * What the cache has to get right is when not to answer: a stored result is only good for the
 * definition, the day and the data it was computed from. So every test here changes one of those and
 * expects the statistic to be computed again - and expects it not to be when nothing has changed.
 *
 * @copyright 2004-2025 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */

namespace AdmidioPlugin\Statistics\Tests\Integration;

require_once dirname(__DIR__) . '/autoload.php';

use Admidio\Infrastructure\Database;
use Admidio\Infrastructure\Plugins\Plugin;
use Admidio\Tests\Support\AdministratorTestCase;
use Admidio\Tests\Support\AdmidioTestFixture;
use Admidio\UI\Presenter\PagePresenter;
use AdmidioPlugin\Statistics\Service\StatisticCache;
use AdmidioPlugin\Statistics\Service\StatisticEvaluator;
use AdmidioPlugin\Statistics\Service\StatisticRepository;
use AdmidioPlugin\Statistics\Service\StatisticWidget;
use AdmidioPlugin\Statistics\ValueObject\ColumnDefinition;
use AdmidioPlugin\Statistics\ValueObject\RowDefinition;
use AdmidioPlugin\Statistics\ValueObject\StatisticDefinition;
use AdmidioPlugin\Statistics\ValueObject\StatisticFunction;
use AdmidioPlugin\Statistics\ValueObject\StatisticResult;
use AdmidioPlugin\Statistics\ValueObject\TableDefinition;

final class StatisticWidgetTest extends AdministratorTestCase
{
    private StatisticRepository $repository;
    private StatisticCache $cache;
    private AdmidioTestFixture $fixture;
    private int $orgId;
    private int $roleId;
    private int $computations = 0;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::runScript('uninstall.sql');
        self::runScript('install.sql');
    }

    public static function tearDownAfterClass(): void
    {
        self::runScript('uninstall.sql');

        parent::tearDownAfterClass();
    }

    private static function runScript(string $file): void
    {
        foreach (Database::getSqlStatementsFromSqlFile(dirname(__DIR__, 2) . '/db_scripts/' . $file) as $sql) {
            self::$gDb->queryPrepared($sql);
        }
    }

    protected function setUp(): void
    {
        parent::setUp();

        global $gCurrentOrgId;

        $this->orgId = (int)$gCurrentOrgId;
        $this->repository = new StatisticRepository($this->getDatabase());
        $this->cache = new StatisticCache($this->getDatabase());
        $this->fixture = new AdmidioTestFixture($this->getDatabase());
        $this->computations = 0;

        $this->roleId = (int)$this->fixture->createAndSaveRole('Statistics widget role', $this->orgId)['rol_id'];
        $this->addMember('sta_widget_one');
    }

    private function addMember(string $login): void
    {
        $userId = (int)$this->fixture->createAndSaveUser($login, $login . '@example.org')['usr_id'];
        $this->fixture->assignUserToRolePeriod($userId, $this->roleId, '1970-01-01', '9999-12-31');
    }

    private function save(string $name, bool $showOnOverview, ?int $id = null, string $title = 'Members'): StatisticDefinition
    {
        $table = new TableDefinition(
            'By role',
            null,
            'Group',
            array(new ColumnDefinition('Count', null, '', StatisticFunction::COUNT, null, StatisticFunction::SUM)),
            array(new RowDefinition('All'))
        );

        $id = $this->repository->save(
            new StatisticDefinition($id, $this->orgId, $name, $title, 'a subtitle', $this->roleId, array($table), array(), $showOnOverview)
        );

        return $this->repository->find($id, $this->orgId);
    }

    /**
     * The result through the cache, counting how often it really was computed.
     */
    private function remember(StatisticDefinition $definition, ?string $date = null, string $context = 'en'): StatisticResult
    {
        global $gProfileFields, $gL10n;

        $date ??= date('Y-m-d');
        $evaluator = new StatisticEvaluator($this->getDatabase(), $gProfileFields, $gL10n);

        return $this->cache->remember($definition, $date, $context, function () use ($evaluator, $definition, $date): StatisticResult {
            $this->computations++;

            return $evaluator->evaluate($definition, 'as of', $date);
        });
    }

    public function testTheFlagSurvivesTheRoundTripAndACopyDoesNotTakeIt(): void
    {
        $shown = $this->save('Shown', true);
        $hidden = $this->save('Hidden', false);

        $this->assertTrue($shown->showOnOverview);
        $this->assertFalse($hidden->showOnOverview);
        $this->assertFalse($shown->asCopyNamed('Copy')->showOnOverview);
        $this->assertTrue($shown->withTables($shown->tables)->showOnOverview);
    }

    public function testOnlyTheMarkedStatisticsOfTheOrganizationAreOnTheOverview(): void
    {
        $this->save('B shown', true);
        $this->save('Hidden', false);
        $this->save('A shown', true);

        $names = array_map(
            static fn (StatisticDefinition $definition): string => $definition->name,
            $this->repository->findForOverview($this->orgId)
        );

        $this->assertSame(array('A shown', 'B shown'), $names, 'in the order of their names');
        $this->assertSame(array(), $this->repository->findForOverview($this->orgId + 1000));
    }

    public function testAResultIsComputedOnceAndThenTakenFromTheCache(): void
    {
        $definition = $this->save('Cached', true);

        $first = $this->remember($definition);
        $second = $this->remember($definition);

        $this->assertSame(1, $this->computations);
        $this->assertSame($first->toArray(), $second->toArray(), 'the stored result is the computed one');
        $this->assertSame(array(array('All', '1')), $second->tables[0]->rows);
        $this->assertNotNull($second->tables[0]->totalRow);
    }

    public function testANewMembershipIsCountedAtOnce(): void
    {
        $definition = $this->save('Cached', true);

        $this->assertSame('1', $this->remember($definition)->tables[0]->rows[0][1]);

        $this->addMember('sta_widget_two');

        $this->assertSame('2', $this->remember($definition)->tables[0]->rows[0][1]);
        $this->assertSame(2, $this->computations);
    }

    public function testAnEditedStatisticIsComputedAgain(): void
    {
        $definition = $this->save('Cached', true);
        $this->remember($definition);

        $edited = $this->save('Cached', true, $definition->id, 'Another title');

        $this->assertSame('Another title', $this->remember($edited)->title);
        $this->assertSame(2, $this->computations);
    }

    public function testAnotherDayAndAnotherLanguageAreComputedAgain(): void
    {
        $definition = $this->save('Cached', true);

        $this->remember($definition, '2026-01-01');
        $this->remember($definition, '2026-01-02');
        $this->assertSame(2, $this->computations, 'a new day');

        $this->remember($definition, '2026-01-02', 'de');
        $this->assertSame(3, $this->computations, 'another language');

        $this->remember($definition, '2026-01-02', 'de');
        $this->assertSame(3, $this->computations);
    }

    public function testThereIsOneStoredResultForAStatisticAndItGoesWithIt(): void
    {
        $definition = $this->save('Cached', true);

        $this->remember($definition, '2026-01-01');
        $this->remember($definition, '2026-01-02');

        $count = fn (): int => (int)$this->getDatabase()->queryPrepared(
            'SELECT COUNT(*) FROM ' . StatisticCache::table() . ' WHERE sca_sta_id = ?',
            array($definition->id)
        )->fetchColumn();

        $this->assertSame(1, $count(), 'the latest result replaces the one before');

        $this->repository->delete((int)$definition->id, $this->orgId);

        $this->assertSame(0, $count());
    }

    public function testAStoredResultThatCannotBeReadIsComputedAgain(): void
    {
        $definition = $this->save('Cached', true);
        $this->remember($definition);

        $this->getDatabase()->queryPrepared(
            'UPDATE ' . StatisticCache::table() . ' SET sca_data = ? WHERE sca_sta_id = ?',
            array('{"not":"a result"}', $definition->id)
        );

        $this->assertSame('1', $this->remember($definition)->tables[0]->rows[0][1]);
        $this->assertSame(2, $this->computations);
    }

    public function testAStatisticThatWasNeverSavedIsNotCached(): void
    {
        $definition = $this->save('Cached', true)->asCopyNamed('Unsaved');

        $this->remember($definition);
        $this->remember($definition);

        $this->assertSame(2, $this->computations);
    }

    public function testTheWidgetHandsTheMarkedStatisticsToItsTemplate(): void
    {
        $shown = $this->save('Shown', true);
        $this->save('Hidden', false);
        $untitled = $this->save('Named only', true, null, '');

        $plugin = Plugin::read(dirname(__DIR__, 2));
        $statistics = StatisticWidget::statistics($plugin, $this->repository, $this->orgId);

        $this->assertCount(2, $statistics);
        $this->assertSame('Named only', $statistics[0]['title'], 'a statistic without a title shows its name');
        $this->assertSame('Members', $statistics[1]['title']);
        $this->assertSame('a subtitle', $statistics[1]['subtitle']);
        $this->assertStringContainsString('sta_id=' . $shown->id, $statistics[1]['url']);
        $this->assertStringContainsString('show.php', $statistics[1]['url']);
        $this->assertSame(array('Group', 'Count'), $statistics[1]['tables'][0]['headers']);
        $this->assertSame(array(array('All', '1')), $statistics[1]['tables'][0]['rows']);
        $this->assertNotSame($untitled->id, $shown->id);
    }

    /**
     * A page that was never set up: enough for the cases in which the widget declines before it
     * renders anything. A real one needs a session, which a test does not have.
     */
    private function page(): PagePresenter
    {
        return (new \ReflectionClass(PagePresenter::class))->newInstanceWithoutConstructor();
    }

    public function testThereIsNoWidgetForAVisitorAndNoneWithoutAMarkedStatistic(): void
    {
        global $gValidLogin;

        $plugin = Plugin::read(dirname(__DIR__, 2));
        $before = $gValidLogin;

        try {
            $this->save('Hidden', false);
            $gValidLogin = true;
            $this->assertSame('', StatisticWidget::render($this->page(), $plugin), 'no statistic is marked');

            $this->save('Shown', true);
            $gValidLogin = false;
            $this->assertSame('', StatisticWidget::render($this->page(), $plugin), 'nothing for a visitor');
        } finally {
            $gValidLogin = $before;
        }
    }

    /**
     * The template of the widget, rendered with what the widget hands it. Everything a user typed
     * is escaped.
     */
    public function testTheTemplateShowsTheStatisticsAndEscapesWhatWasTyped(): void
    {
        $this->save('Shown', true, null, 'Members & "friends"');

        $plugin = Plugin::read(dirname(__DIR__, 2));

        $smarty = new \Smarty\Smarty();
        $smarty->setTemplateDir(dirname(__DIR__, 2) . '/templates');
        $smarty->setCompileDir(sys_get_temp_dir() . '/adm_statistics_widget_test');
        $smarty->assign('staWidgetName', $plugin->id);
        $smarty->assign('staWidgetStatistics', StatisticWidget::statistics($plugin, $this->repository, $this->orgId));

        $html = $smarty->fetch(StatisticWidget::TEMPLATE);

        $this->assertStringContainsString('id="plugin-statistics"', $html);
        $this->assertStringContainsString('Members &amp; &quot;friends&quot;', $html);
        $this->assertStringContainsString('<p>a subtitle</p>', $html);
        $this->assertStringContainsString('<h5>By role</h5>', $html);
        $this->assertStringContainsString('<td>All</td>', $html);
        $this->assertStringContainsString('<td>1</td>', $html);
        $this->assertStringContainsString('<tfoot>', $html);
        $this->assertStringContainsString('show.php', $html);
    }
}
