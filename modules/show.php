<?php
/**
 ***********************************************************************************************
 * Shows one saved statistic with its computed figures.
 *
 * Parameters:
 *
 * sta_id : id of the statistic to show
 * mode   : html  - the normal page (default)
 *          print - the print view
 * date   : the day (Y-m-d) the memberships are looked at, today when absent. Ignored for a
 *          statistic whose rows or columns name days of their own.
 *
 * @copyright 2004-2025 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

use Admidio\Infrastructure\Exception;
use Admidio\Infrastructure\Plugins\Plugin;
use Admidio\Infrastructure\Utils\SecurityUtils;
use Admidio\UI\Presenter\PagePresenter;
use AdmidioPlugin\Statistics\Presenter\StatisticResultPresenter;
use AdmidioPlugin\Statistics\Service\StatisticEvaluator;
use AdmidioPlugin\Statistics\Service\StatisticRepository;
use AdmidioPlugin\Statistics\Service\StatisticsAccess;
use AdmidioPlugin\Statistics\Statistics;
use AdmidioPlugin\Statistics\ValueObject\RowDefinition;

try {
    require_once(__DIR__ . '/../../../system/common.php');
    require(__DIR__ . '/../../../system/login_valid.php');

    StatisticsAccess::assertMayView();

    $getStatisticId = (int) admFuncVariableIsValid($_GET, 'sta_id', 'numeric', array('requireValue' => true));
    $getMode = admFuncVariableIsValid($_GET, 'mode', 'string', array(
        'defaultValue' => 'html',
        'validValues' => array('html', 'print')
    ));

    $plugin = Statistics::plugin();

    /*
     * The statistic is looked up with the organization, so one organization cannot read another's by
     * guessing an id. A statistic that is not there is an invalid request and says so: the old page
     * printed the message of the exception and then carried on to read the statistic it had failed to
     * load, which ended in a fatal error.
     */
    $repository = new StatisticRepository($gDb);
    $definition = $repository->find($getStatisticId, (int) $gCurrentOrgId);

    if ($definition === null) {
        throw new Exception('SYS_INVALID_PAGE_VIEW');
    }

    /*
     * A statistic can be shown for another day than today, unless a row or a column of it names a
     * day of its own: that one is a history already. Anything that is not a day means today.
     */
    $dateFormat = $gSettingsManager->getString('system_date');
    $date = null;
    if (!$definition->usesReferenceDates()) {
        $date = RowDefinition::toDate(admFuncVariableIsValid($_GET, 'date', 'string'));
    }

    $asOf = (new DateTime($date ?? DATE_NOW))->format($dateFormat);

    $evaluator = new StatisticEvaluator($gDb, $gProfileFields, $gL10n, $dateFormat);
    $result = $evaluator->evaluate($definition, $asOf, $date);

    $gNavigation->addUrl(CURRENT_URL, $result->title);

    $page = PagePresenter::withHtmlIDAndHeadline('adm_plg_statistics_show', $result->title);

    if ($getMode === 'print') {
        $page->setPrintMode();
    } else {
        $printParameters = array('sta_id' => $getStatisticId, 'mode' => 'print');
        if ($date !== null) {
            $printParameters['date'] = $date;
        }
        $printUrl = SecurityUtils::encodeUrl($plugin->getUrl('show.php'), $printParameters);

        $page->addJavascript('
            $("#adm_menu_item_plg_statistics_print").click(function() {
                window.open("' . $printUrl . '", "_blank");
            });', true);

        $page->addPageFunctionsMenuItem(
            'adm_menu_item_plg_statistics_print',
            $gL10n->get('SYS_PRINT_PREVIEW'),
            'javascript:void(0);',
            'bi-printer-fill'
        );
    }

    $page->addTemplateFolder($plugin->getDirectory(Plugin::DIR_TEMPLATES));
    $page->addTemplateFile(StatisticResultPresenter::TEMPLATE);
    StatisticResultPresenter::assign($page, $result, $getMode === 'print');

    if ($getMode !== 'print' && !$definition->usesReferenceDates()) {
        StatisticResultPresenter::assignDateForm($page, $plugin->getUrl('show.php'), $getStatisticId, $date ?? DATE_NOW);
    }

    $page->show();
} catch (Throwable $e) {
    handleException($e);
}
