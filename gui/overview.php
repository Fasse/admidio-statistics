<?php
/******************************************************************************
 * Zeigt eine Uebersicht aller Statistiken , die sich der User anzeigen lassen kann.
 *
 * @copyright 2004-2021 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 *****************************************************************************/


require_once('../includes.php');
require_once(ADMIDIO_PATH.'/adm_program/system/login_valid.php');
require_once(STATISTICS_PATH.'/utils/db_access.php');

// Url fuer die Zuruecknavigation merken
$gNavigation->addUrl(CURRENT_URL);

// Html-Kopf wird geschrieben
$page = new HtmlPage('admidio-plugin-statistics-overview', $gL10n->get('PLG_STATISTICS_STATISTICS'));

//DB-Hilfsklasse instanzieren
$staDBHandler = new DBAccess();

//Überprüfen, ob das Plugin installiert ist
$pluginInstalled = $staDBHandler->getPluginInstalled();

// check if the current user has the right to view the statistics
$sql = 'SELECT men_id FROM ' . TBL_MENU . ' WHERE men_name_intern = \'statistics\' ';
$statement = $gDb->query($sql);
$row = $statement->fetch();

// Read current roles rights of the menu entry
$displayMenu = new RolesRights($gDb, 'menu_view', $row['men_id']);
$rolesDisplayRight = $displayMenu->getRolesIds();

// check for right to show the menu
if (count($rolesDisplayRight) > 0 && !$displayMenu->hasRight($gCurrentUser->getRoleMemberships()))
{
    $hasAccess = false;
}
else
{
    $hasAccess = true;
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
        $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
        // => EXIT
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
