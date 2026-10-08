<?php
/**
 ***********************************************************************************************
 * Overview of all statistics the current user may look at.
 *
 * This is the page the menu entry of the plugin points at, which is why it is index.php: Admidio
 * builds that entry from this file when the plugin is installed. The editor is reached from the page
 * functions menu here, and only by an administrator, so that the plugin needs one menu entry rather
 * than the two the old version inserted into the menu table itself.
 *
 * @copyright 2004-2025 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

use Admidio\Infrastructure\Plugins\Plugin;
use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\UI\Component\DataTables;
use Admidio\UI\Presenter\PagePresenter;
use AdmidioPlugin\Statistics\Service\StatisticRepository;
use AdmidioPlugin\Statistics\Service\StatisticsAccess;
use AdmidioPlugin\Statistics\Statistics;

try {
    require_once(__DIR__ . '/../../../system/common.php');
    require(__DIR__ . '/../../../system/login_valid.php');

    StatisticsAccess::assertMayView();

    $plugin = Statistics::plugin();

    $gNavigation->addStartUrl(CURRENT_URL, $gL10n->get('PLG_STATISTICS_STATISTICS'), $plugin->icon);

    $page = PagePresenter::withHtmlIDAndHeadline(
        'adm_plg_statistics_overview',
        $gL10n->get('PLG_STATISTICS_STATISTICS')
    );

    $repository = new StatisticRepository($gDb);

    $statistics = array();
    foreach ($repository->findAllForOrganization((int) $gCurrentOrgId) as $statistic) {
        $statistics[] = array(
            'name' => $statistic['name'],
            'url' => SecurityUtils::encodeUrl($plugin->getUrl('show.php'), array('sta_id' => $statistic['id']))
        );
    }

    if (StatisticsAccess::mayConfigure()) {
        $page->addPageFunctionsMenuItem(
            'adm_menu_item_plg_statistics_editor',
            $gL10n->get('PLG_STATISTICS_STATISTICS_EDITOR'),
            $plugin->getUrl('editor.php'),
            'bi-gear-fill',
            '',
            0,
            $gL10n->get('PLG_STATISTICS_STATISTICS_EDITOR_DESC')
        );
    }

    if ($statistics !== array()) {
        $dataTables = new DataTables($page, 'adm_plg_statistics_overview_table');
        $dataTables->createJavascript(count($statistics), 1);
    }

    $page->addTemplateFolder($plugin->getDirectory(Plugin::DIR_TEMPLATES));
    $page->addTemplateFile('plugin.statistics.overview.tpl');
    $page->assignSmartyVariable('statistics', $statistics);
    $page->show();
} catch (Throwable $e) {
    handleException($e);
}
