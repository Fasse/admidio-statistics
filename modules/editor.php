<?php
/**
 ***********************************************************************************************
 * The configuration editor of the statistics plugin.
 *
 * A GET renders the editor, a POST acts on what was submitted and then renders it again. The two are
 * one page on purpose: a structure change - adding a column, moving a row - has to be applied to the
 * unsaved definition that was just submitted and shown straight back, so it cannot redirect, and
 * splitting the acting half off would mean writing the whole rendering half twice.
 *
 * Parameters:
 *
 * sta_id : id of the statistic to load, absent for a new one
 *
 * POST adm_statistics_action : one of the fifteen structure operations, or preview, save, saveas,
 *                             delete
 * POST adm_statistics_target : the table, or the table and the row or column, the operation applies to
 * POST adm_statistics_scroll : the scroll position to restore after the page is rendered again
 *
 * This replaces gui/editor.php together with gui/editor_process.php. The old pair had no CSRF token,
 * no login check in the acting half at all, and performed deletions on a GET, so a link was enough to
 * destroy a saved statistic. It also kept every keystroke in a reserved database row, sta_id = 1,
 * which every administrator of every organization shared; the draft now lives in the request and in
 * the form object the session holds.
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
use AdmidioPlugin\Statistics\Presenter\StatisticEditorPresenter;
use AdmidioPlugin\Statistics\Presenter\StatisticResultPresenter;
use AdmidioPlugin\Statistics\Service\StatisticEvaluator;
use AdmidioPlugin\Statistics\Service\StatisticFormService;
use AdmidioPlugin\Statistics\Service\StatisticRepository;
use AdmidioPlugin\Statistics\Service\StatisticsAccess;
use AdmidioPlugin\Statistics\Service\StatisticStructureService;
use AdmidioPlugin\Statistics\Statistics;
use AdmidioPlugin\Statistics\ValueObject\StatisticDefinition;

try {
    require_once(__DIR__ . '/../../../system/common.php');
    require(__DIR__ . '/../../../system/login_valid.php');

    StatisticsAccess::assertMayConfigure();

    $plugin = Statistics::plugin();
    $orgId = (int) $gCurrentOrgId;
    $repository = new StatisticRepository($gDb);

    $scrollPosition = 0;
    $previewResult = null;

    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        /*
         * The form that rendered the page is taken out of the session, so the list of legal field
         * names is the one the server wrote. validate() compares the token, enforces the required
         * fields, strips markup and refuses any submitted key the form does not know.
         */
        $form = $gCurrentSession->getFormObject((string) ($_POST['adm_csrf_token'] ?? ''));
        $formValues = $form->validate($_POST);

        $action = (string) ($formValues[StatisticFormService::FIELD_ACTION] ?? '');
        $scrollPosition = max(0, (int) ($formValues[StatisticFormService::FIELD_SCROLL] ?? 0));
        $definition = StatisticFormService::fromFormValues($form, $formValues, $orgId);

        if (in_array($action, StatisticStructureService::ACTIONS, true)) {
            list($targetTable, $targetElement) = StatisticFormService::parseTarget(
                (string) ($formValues[StatisticFormService::FIELD_TARGET] ?? '')
            );

            $definition = StatisticStructureService::apply($definition, $action, $targetTable, $targetElement);
        } elseif ($action === StatisticEditorPresenter::ACTION_PREVIEW) {
            $asOf = (new DateTime(DATE_NOW))->format($gSettingsManager->getString('system_date'));
            $evaluator = new StatisticEvaluator($gDb, $gProfileFields, $gL10n);
            $previewResult = $evaluator->evaluate($definition, $asOf);
        } elseif ($action === StatisticEditorPresenter::ACTION_SAVE) {
            StatisticFormService::assertSaveable($definition);
            $savedId = $repository->save($definition);

            admRedirect(SecurityUtils::encodeUrl($plugin->getUrl('editor.php'), array('sta_id' => $savedId)));
            // => EXIT
        } elseif ($action === StatisticEditorPresenter::ACTION_SAVE_AS) {
            StatisticFormService::assertSaveable($definition);
            $copy = $definition->asCopyNamed(
                $gL10n->get('PLG_STATISTICS_COPY_OF_NAME', array($definition->name))
            );
            $savedId = $repository->save($copy);

            admRedirect(SecurityUtils::encodeUrl($plugin->getUrl('editor.php'), array('sta_id' => $savedId)));
            // => EXIT
        } elseif ($action === StatisticEditorPresenter::ACTION_DELETE) {
            if ($definition->isNew()) {
                throw new Exception('SYS_INVALID_PAGE_VIEW');
            }

            $repository->delete((int) $definition->id, $orgId);

            admRedirect($plugin->getUrl('editor.php'));
            // => EXIT
        } else {
            throw new Exception('SYS_INVALID_PAGE_VIEW');
        }
    } else {
        $getStatisticId = (int) admFuncVariableIsValid($_GET, 'sta_id', 'numeric', array('defaultValue' => 0));

        if ($getStatisticId > 0) {
            $definition = $repository->find($getStatisticId, $orgId);

            if ($definition === null) {
                throw new Exception('SYS_INVALID_PAGE_VIEW');
            }
        } else {
            $definition = StatisticDefinition::createEmpty($orgId);
        }
    }

    $page = PagePresenter::withHtmlIDAndHeadline(
        'adm_plg_statistics_editor',
        $gL10n->get('PLG_STATISTICS_CONFIGURE_STATISTIC')
    );
    $gNavigation->addUrl(CURRENT_URL, $page->getHeadline());

    $page->addCssFile($plugin->getAssetUrl('statistics.editor.css'));
    $page->addJavascriptFile($plugin->getAssetUrl('statistics.editor.js'));
    $page->addTemplateFolder($plugin->getDirectory(Plugin::DIR_TEMPLATES));

    $page->assignSmartyVariable('staHasPreview', $previewResult !== null);
    if ($previewResult !== null) {
        StatisticResultPresenter::assign($page, $previewResult);
    }

    $form = StatisticEditorPresenter::createForm(
        $page,
        $plugin,
        $definition,
        $repository->findAllForOrganization($orgId),
        $scrollPosition
    );

    /*
     * Submitting without AJAX, because a structure action has to render the whole editor again rather
     * than replace the form inline.
     */
    $form->addToHtmlPage(false);
    $gCurrentSession->addFormObject($form);

    if ($scrollPosition > 0) {
        // The replacement for the onload hook the old editor set on a global that no longer exists.
        $page->addJavascript('window.scrollTo(0, ' . $scrollPosition . ');', true);
    }

    $page->show();
} catch (Throwable $e) {
    handleException($e);
}
