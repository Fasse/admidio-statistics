<?php
/******************************************************************************
 * Template f�r Skript zur Auslesung und Darstellung von Statistiken
 *
 * Copyright    : (c) 2004 - 2015 The Admidio Team
 * Homepage     : http://www.admidio.org
 *
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.htm
 *
 * Parameters:
 *
 * def          : Name der Definitionsdatei, die ausgelesen werden soll
 *
 *****************************************************************************/

require_once('../includes.php');
require_once(STATISTICS_PATH.'/statistic_objects/statistic.php');
require_once(STATISTICS_PATH.'/utils/db_access.php');
require_once(STATISTICS_PATH.'/utils/evaluator.php');
global $gNavigation;

// Check Parameter
$getStaId = admFuncVariableIsValid($_GET, 'sta_id','numeric');
$getMode  = admFuncVariableIsValid($_GET, 'mode', 'string', array('defaultValue' => 'html', 'validValues' => array('html', 'print')));

// Url fuer die Zuruecknavigation merken
$gNavigation->addUrl(CURRENT_URL);

//�berpr�fen, ob der Benutzer Zugriff auf die Seite hat
$hasAccess = false;
foreach ($plgAllowShow AS $i)
{
    if($i == 'Benutzer'
        && $gValidLogin == true)
    {
        $hasAccess = true;
    }
    elseif($i == 'Rollenverwalter'
        && $gCurrentUser->assignRoles())
    {
        $hasAccess = true;
    }
    elseif($i == 'Listenberechtigte'
        && $gCurrentUser->viewAllLists())
    {
        $hasAccess = true;
    }
    elseif(hasRole($i))
    {
        $hasAccess = true;
    }
}

if($hasAccess == true)
{
    // Html-Kopf wird geschrieben
    $page = new HtmlPage('Statistik');
    $page->setTitle('Statistik');

    if ($getStaId > 0)
    {
        try
        {
            $staDB = new DBAccess();
            $statisticDefinition = $staDB->getStatistic($getStaId);
            $staCalc = new Evaluator();
            $statistic = $staCalc->calculateStatistic($statisticDefinition);
        }
        catch (AdmException $e)
        {
            $e->showHtml();
        }
    }
    else
    {
        $gMessage->show('Keine Statistiken gefunden!');
    }

    $page->addJavascript('
        $("#menu_item_print_view").click(function () {
            window.open("'. ADMIDIO_URL . FOLDER_PLUGINS . '/statistics/gui/show.php?" +
            "sta_id='.$getStaId.'&mode=print", "_blank");
        });', true);

    if ($getMode == 'print')
    {
        $page->hideThemeHtml();
        $page->hideMenu();
        $page->setPrintMode();
    }
    else
    {
        $statisticsShow = $page->getMenu();
        $statisticsShow->addItem('menu_item_back', $gNavigation->getPreviousUrl(), $gL10n->get('SYS_BACK'), 'back.png');
        
        // link to print preview
        $statisticsShow->addItem('menu_item_print_view', '#', $gL10n->get('LST_PRINT_PREVIEW'), 'print.png');
    }
    
    $page->addHtml('<div>');
    //Hauptitel
    $page->setHeadline(($statistic->getTitle() == '' ? '': $statistic->getTitle()));

    //Untertitel
    $page->addHtml('<h2 id="statisticSubtitle">'.  ($statistic->getSubtitle() == '' ? '': $statistic->getSubtitle()) .'</h2>');
    $page->addHtml('<span id="date">Stand: ' . date('d.m.Y') .'</span></div>');

    //In dieser Schleife werden alle Tabellen der Statistik berechnet und angezeigt
    $tables = $statistic->getTables();
    foreach ($tables as $table) {

        $page->addHtml('<h3>' . ($table->getTitle() == '' ? '&nbsp;': $table->getTitle()) . '</h3>');

        if (($table->getRoleID() != $statistic->getStandardRoleID() && $table->getRoleID() != '')) {
            $tableRole = $table->getRoleID();
        } else {
            $tableRole = $statistic->getStandardRoleID();
        }

                if ($getMode == 'print')
        { 
            $showTable = new HtmlTable('tableStatistic', null, false, false,'table table-condensed table-striped');   
        }
        else
        {
        
        $showTable = new HtmlTable('tableStatistic', null, true, true);
          }
        $page->addHtml('Rolle ' . $staCalc->getRoleNameFromID($tableRole) . ', ' . $staCalc->getUserCountFromRoleId($tableRole) .' Eintr&auml;ge');
        
        $columnAlign  = array();
        $columnHeading = array();

        if($table->getFirstColumnLabel() == '' )
        {
            $columnHeading[] = '&nbsp;';
        }
        else
        {
            $columnHeading[] = $table->getFirstColumnLabel();
        }
        
        $columnAlign[] = 'left';
        $columns = $table->getColumns();

            for ($c= 0;$c < count($columns); ++$c) {
                if($columns[$c]->getLabel() == '' )
                {
                    $columnHeading[] = '&nbsp;';
                }
                else
                {
                    $columnHeading[] = $columns[$c]->getLabel();
                }
                $columnAlign[] = 'left';
            }
            
        $showTable->setColumnAlignByArray($columnAlign);
        $showTable->addRowHeadingByArray($columnHeading);

        $rows = $table->getRows();

        for ($r = 0; $r < count($rows); ++$r) {

            $columnValues = array();
            if ($rows[$r]->getLabel() == ' ')
            {
                $columnValues[] = '&nbsp;';
            }
            else
            {
                $columnValues[] = $rows[$r]->getLabel();
            }
            for ($c= 0;$c < count($columns); $c++) {
                            $currentCell = $table->getCell($c,$r);
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
        $gMessage->show('Sie haben keine Berechtigung, diese Seite anzuzeigen.');
    } else {
        require_once(SERVER_PATH.'/adm_program/system/login_valid.php');
    }
}
?>
