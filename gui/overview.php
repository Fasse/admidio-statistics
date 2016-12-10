<?php
/******************************************************************************
 * Zeigt eine Uebersicht aller Statistiken , die sich der User anzeigen lassen kann.
 *
 * Copyright    : (c) 2004 - 2015 The Admidio Team
 * Homepage     : http://www.admidio.orgl
 *
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.htm
 *
 * Parameters:
 *
 *****************************************************************************/


require_once('../includes.php');
require_once(SERVER_PATH.'/adm_program/system/login_valid.php');
require_once(SERVER_PATH.'/adm_program/system/classes/tableroles.php');
require_once(STATISTICS_PATH.'/utils/db_access.php');
global $gNavigation;


// Url fuer die Zuruecknavigation merken
$gNavigation->addUrl(CURRENT_URL);

// Html-Kopf wird geschrieben
$page = new HtmlPage();
$page->setTitle('Statistik');

$statisticsOverview = $page->getMenu();
$statisticsOverview->addItem('menu_item_back', $gNavigation->getPreviousUrl(), $gL10n->get('SYS_BACK'), 'back.png');

//DB-Hilfsklasse instanzieren
$staDBHandler = new DBAccess();

//Überprüfen, ob das Plugin installiert ist
$pluginInstalled = $staDBHandler->getPluginInstalled();

//Überprüfen, ob der Benutzer Zugriff auf die Seite hat
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

if ($pluginInstalled) {
    if($hasAccess == true) {
        $page->setHeadline('Statistik-Übersicht');

        $class_table = 'tableStatistic';

        $staDB = new DBAccess();
        global $gCurrentOrganization;
        $staIDs = $staDB->getStatisticIDs($gCurrentOrganization->getValue('org_id',''));

        if (count($staIDs) == 0){
            $page->addHtml('Es konnten keine Statistik-Definitionen gefunden werden');
        } else {
            $page->addHtml('<p>Es wurden <b>' . count($staIDs) . '</b> Statistik-Definitionen gefunden:</p>');
            $page->addHtml('<p>W&auml;hlen Sie eine Statistik aus, welche angezeigt werden soll:</p>');

            //Create table object
            $tableOverview = new HtmlTable($class_table, null, true, true);
            // create array with all column heading values
            $columnHeading = array('Name der Statistik');
            $tableOverview->setColumnAlignByArray(array('left'));
            $tableOverview->addRowHeadingByArray($columnHeading);
            // create array with all statistic list
            $columnValues = array();
            foreach($staIDs as $id)
            {
                $columnValues[] = '<a href="./show.php?sta_id='. $id . '">' .$staDB->getStatisticName($id) . '</a>';
            }
            for ($result = 0; $result < count($columnValues); ++$result)
            {
                $tableOverview->addRow($columnValues[$result]);
            }
            $htmlTableOverview = $tableOverview->show(false);
            $page->addHtml($htmlTableOverview);
        }

    } else {
        if ($gValidLogin) {
            $page->addHtml('<p>Sie haben keine Berechtigung, diese Seite anzuzeigen.</p>');
        }
    }
} else {
        $page->addHtml('<p>Das Plugin ist nicht installiert, bitte zuerst installieren.</p>');
        $text = 'Zur Installation';
        $link = '../install/install.php';

        $navbarPlugin = new HtmlForm('navbar_statistics_installation', $link, $page, array('type' => 'default', 'setFocus' => false));
        $navbarPlugin->addSubmitButton('btn_send', $text);
        $page->addHtml($navbarPlugin->show(false));

}
$page->show();
?>
