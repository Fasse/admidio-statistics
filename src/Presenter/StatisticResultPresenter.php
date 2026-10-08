<?php

declare(strict_types=1);

namespace AdmidioPlugin\Statistics\Presenter;

use Admidio\UI\Component\DataTables;
use Admidio\UI\Presenter\PagePresenter;
use AdmidioPlugin\Statistics\ValueObject\StatisticResult;

/**
 * Hands a computed statistic to the template that renders it.
 *
 * Both the page that shows a saved statistic and the preview the editor renders above its form go
 * through here, which is why the preview no longer needs to write anything to the database in order
 * to be shown.
 *
 * The tables are handed over as plain arrays rather than as the result objects, so that the template
 * only ever reads array keys.
 *
 * @copyright 2004-2025 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
final class StatisticResultPresenter
{
    public const TEMPLATE = 'plugin.statistics.result.tpl';

    /**
     * The id prefix of the rendered tables. Each table needs one of its own, because the sortable
     * table script is set up per table.
     */
    private const TABLE_ID_PREFIX = 'adm_plg_statistics_table_';

    /**
     * @param PagePresenter $page
     * @param StatisticResult $result
     * @param bool $printMode Whether the page is the print view, which gets a plain table instead of
     *                        a sortable one.
     * @return void
     */
    public static function assign(PagePresenter $page, StatisticResult $result, bool $printMode = false): void
    {
        $tables = array();

        foreach ($result->tables as $index => $table) {
            $id = self::TABLE_ID_PREFIX . $index;

            if (!$printMode) {
                $dataTables = new DataTables($page, $id);
                $dataTables->createJavascript(count($table->rows), count($table->headers));
            }

            $tables[] = array(
                'id' => $id,
                'title' => $table->title,
                'caption' => $table->caption,
                'headers' => $table->headers,
                'rows' => $table->rows,
                'totalRow' => $table->totalRow
            );
        }

        $page->assignSmartyVariable('staSubtitle', $result->subtitle);
        $page->assignSmartyVariable('staAsOf', $result->asOf);
        $page->assignSmartyVariable('staResultTables', $tables);
        $page->assignSmartyVariable('staPrintMode', $printMode);
    }
}
