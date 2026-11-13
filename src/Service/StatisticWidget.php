<?php

declare(strict_types=1);

namespace AdmidioPlugin\Statistics\Service;

use Admidio\Infrastructure\Plugins\Plugin;
use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\UI\Presenter\PagePresenter;
use AdmidioPlugin\Statistics\ValueObject\StatisticResult;

/**
 * The widget of the overview page: every statistic that asks to be shown there, one below the other.
 *
 * A statistic asks with the checkbox in its editor. The widget is one widget of the plugin, so it is
 * placed and switched off like the widget of any other plugin; a statistic that is not marked, and an
 * organization that has marked none, simply contribute nothing.
 *
 * The figures come through StatisticCache, because this is rendered on every view of the overview.
 *
 * @copyright 2004-2025 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
final class StatisticWidget
{
    public const TEMPLATE = 'plugin.statistics.widget.tpl';

    /**
     * The HTML of the widget, or an empty string when there is nothing to show or nobody to show it
     * to. Who may see the widget at all was decided before this is called, by the access preference
     * of the plugin; a statistic is for logged-in users whatever that preference says.
     *
     * @param PagePresenter $page
     * @param Plugin $plugin
     * @return string
     * @throws \Throwable
     */
    public static function render(PagePresenter $page, Plugin $plugin): string
    {
        global $gDb, $gCurrentOrgId, $gValidLogin;

        if (empty($gValidLogin)) {
            return '';
        }

        $statistics = self::statistics($plugin, new StatisticRepository($gDb), (int) $gCurrentOrgId);
        if ($statistics === array()) {
            return '';
        }

        return $plugin->renderTemplate($page, self::TEMPLATE, array(
            'staWidgetName' => $plugin->id,
            'staWidgetStatistics' => $statistics
        ));
    }

    /**
     * The statistics of the widget, computed or taken from the cache, as the template reads them.
     *
     * @param Plugin $plugin
     * @param StatisticRepository $repository
     * @param int $orgId
     * @return array<int,array<string,mixed>>
     * @throws \Throwable
     */
    public static function statistics(Plugin $plugin, StatisticRepository $repository, int $orgId): array
    {
        global $gDb, $gProfileFields, $gL10n, $gSettingsManager;

        $definitions = $repository->findForOverview($orgId);
        if ($definitions === array()) {
            return array();
        }

        $date = date('Y-m-d');
        $dateFormat = $gSettingsManager->getString('system_date');
        $asOf = (new \DateTimeImmutable($date))->format($dateFormat);

        $evaluator = new StatisticEvaluator($gDb, $gProfileFields, $gL10n, $dateFormat);
        $cache = new StatisticCache($gDb);

        $statistics = array();
        foreach ($definitions as $definition) {
            $result = $cache->remember(
                $definition,
                $date,
                $gL10n->getLanguage() . '|' . $dateFormat,
                static fn (): StatisticResult => $evaluator->evaluate($definition, $asOf, $date)
            );

            $tables = array();
            foreach ($result->tables as $table) {
                $tables[] = array(
                    'title' => $table->title,
                    'headers' => $table->headers,
                    'rows' => $table->rows,
                    'totalRow' => $table->totalRow
                );
            }

            $statistics[] = array(
                'title' => $result->title !== '' ? $result->title : $definition->name,
                'subtitle' => $result->subtitle,
                'url' => SecurityUtils::encodeUrl($plugin->getUrl('show.php'), array('sta_id' => $definition->id)),
                'tables' => $tables
            );
        }

        return $statistics;
    }
}
