<?php
/******************************************************************************
 * Template für Skript zur Auslesung und Darstellung von Statistiken
 *
 * @copyright 2004-2021 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 *****************************************************************************/

require_once('../includes.php');
require_once(STATISTICS_PATH . '/statistic_objects/statistic.php');
require_once(STATISTICS_PATH . '/utils/db_access.php');
require_once(STATISTICS_PATH . '/utils/evaluator.php');

// Check Parameter
$getStaId = admFuncVariableIsValid($_GET, 'sta_id', 'numeric');
$getMode = admFuncVariableIsValid($_GET, 'mode', 'string', array('defaultValue' => 'html', 'validValues' => array('html', 'print')));

// check if the current user has the right to view the statistics
$sql = 'SELECT men_id FROM ' . TBL_MENU . ' WHERE men_name_intern = \'statistics\' ';
$statement = $gDb->query($sql);
$row = $statement->fetch();

// Read current roles rights of the menu entry
$displayMenu = new RolesRights($gDb, 'menu_view', $row['men_id']);
$rolesDisplayRight = $displayMenu->getRolesIds();

// check for right to show the menu
if (count($rolesDisplayRight) > 0 && !$displayMenu->hasRight($gCurrentUser->getRoleMemberships())) {
    $hasAccess = false;
} else {
    $hasAccess = true;
}

if ($hasAccess == true) {
    if ($getStaId > 0) {
        try {
            $staDB = new DBAccess();
            $statisticDefinition = $staDB->getStatistic($getStaId);
            $staCalc = new Evaluator();
            $statistic = $staCalc->calculateStatistic($statisticDefinition);
        } catch (AdmException $e) {
            $e->showHtml();
        }
    } else {
        $gMessage->show($gL10n->get('SYS_INVALID_PAGE_VIEW'));
    }

    $headline = ($statistic->getTitle() == '' ? '' : $statistic->getTitle());
    $gNavigation->addUrl(CURRENT_URL, $headline);

    $page = new HtmlPage('admidio-plugin-statistics', $headline);
    $page->addJavascript('
        $("#menu_item_print_view").click(function () {
            window.open("' . ADMIDIO_URL . FOLDER_PLUGINS . '/statistics/gui/show.php?" +
            "sta_id=' . $getStaId . '&mode=print", "_blank");
        });', true);

    if ($getMode == 'print') {
        $page->setPrintMode();
    } else {
        // link to print preview
        $page->addPageFunctionsMenuItem('menu_item_print_view', $gL10n->get('SYS_PRINT_PREVIEW'), 'javascript:void();', 'fa-print');
    }

    $page->addHtml('<div>');

    //Untertitel
    $datetime = new DateTime(DATE_NOW);
    $currentDate = $datetime->format($gSettingsManager->getString('system_date'));

    $page->addHtml('<h2 id="statisticSubtitle">' . ($statistic->getSubtitle() == '' ? '' : $statistic->getSubtitle()) . '</h2>');
    $page->addHtml('<span id="date">' . $gL10n->get('PLG_STATISTICS_AS_OF_YX', array($currentDate)) . '</span></div>');

    //In dieser Schleife werden alle Tabellen der Statistik berechnet und angezeigt
    $tableNumber = 0;
    $tables = $statistic->getTables();

    foreach ($tables as $table) {

        $page->addHtml('<h3>' . ($table->getTitle() == '' ? '&nbsp;' : $table->getTitle()) . '</h3>');

        if (($table->getRoleID() != $statistic->getStandardRoleID() && $table->getRoleID() != '')) {
            $tableRole = $table->getRoleID();
        } else {
            $tableRole = $statistic->getStandardRoleID();
        }

        if ($getMode == 'print') {
            $showTable = new HtmlTable('tableStatistic' . $tableNumber++, $page, false, false, 'table table-condensed table-striped');
        } else {
            $showTable = new HtmlTable('tableStatistic' . $tableNumber++, $page, true, true);
        }
        $page->addHtml($gL10n->get('SYS_ROLE') . ' ' . $staCalc->getRoleNameFromID($tableRole) . ', ' . $gL10n->get('PLG_STATISTICS_XY_ENTRIES', array($staCalc->getUserCountFromRoleId($tableRole))));

        $columnAlign = array();
        $columnHeading = array();

        if ($table->getFirstColumnLabel() == '') {
            $columnHeading[] = '&nbsp;';
        } else {
            $columnHeading[] = $table->getFirstColumnLabel();
        }

        $columnAlign[] = 'left';
        $columns = $table->getColumns();

        for ($c = 0; $c < count($columns); ++$c) {
            if ($columns[$c]->getLabel() == '') {
                $columnHeading[] = '&nbsp;';
            } else {
                $columnHeading[] = $columns[$c]->getLabel();
            }
            $columnAlign[] = 'left';
        }

        $showTable->setColumnAlignByArray($columnAlign);
        $showTable->addRowHeadingByArray($columnHeading);

        $rows = $table->getRows();

        for ($r = 0; $r < count($rows); ++$r) {

            $columnValues = array();
            if ($rows[$r]->getLabel() == ' ') {
                $columnValues[] = '&nbsp;';
            } else {
                $columnValues[] = $rows[$r]->getLabel();
            }
            for ($c = 0; $c < count($columns); $c++) {
                $currentCell = $table->getCell($c, $r);
                $columnValues[] = $currentCell->getValue();
            }
            $showTable->addRowByArray($columnValues);
        }

        $htmlShowTable = $showTable->show(false);
        $page->addHtml($htmlShowTable);
    }

    $page->show();

} else {
    if ($gValidLogin) {
        $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
        // => EXIT
    } else {
        require_once(ADMIDIO_PATH . '/adm_program/system/login_valid.php');
    }
}
?>
