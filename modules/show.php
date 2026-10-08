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

    $asOf = (new DateTime(DATE_NOW))->format($gSettingsManager->getString('system_date'));

    $evaluator = new StatisticEvaluator($gDb, $gProfileFields, $gL10n);
    $result = $evaluator->evaluate($definition, $asOf);

    $gNavigation->addUrl(CURRENT_URL, $result->title);

    $page = PagePresenter::withHtmlIDAndHeadline('adm_plg_statistics_show', $result->title);

    if ($getMode === 'print') {
        $page->setPrintMode();
    } else {
        $printUrl = SecurityUtils::encodeUrl($plugin->getUrl('show.php'), array(
            'sta_id' => $getStatisticId,
            'mode' => 'print'
        ));

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
    $page->show();
} catch (Throwable $e) {
    handleException($e);
}
