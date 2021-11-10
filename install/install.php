<?php
/******************************************************************************
 * Installationsskript für das Statistik-Plugin
 *
 * @copyright 2004-2021 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 * install-state =  1 : (Default) Initialisierung des Installationsvorgangs
 *                  4 : Install plugin
 *                  5 : ask for uninstall plugin
 *                  6 : Uninstall plugin
 *
 * backup        =  ja    :   es wurde ein Backup gewünscht, Recovery ausführen
 *                  sonst :   es wurde kein Backup gewünscht, Recovery überspringen
 *
 *****************************************************************************/
//Import benötigter Skripts
require_once('../includes.php');
require_once('install_functions.php');
require_once(STATISTICS_PATH.'/statistic_objects/statistic.php');
require_once(STATISTICS_PATH.'/utils/db_access.php');

$page = new HtmlPage('admidio-plugin-statistics-install', $gL10n->get('PLG_STATISTICS_INSTALLATION_STATISTICS_PLUGIN'));


if($gCurrentUser->isAdministrator()) {
    //Übergabevariablen prüfen
    if (isset($_POST['install-state']) && is_numeric($_POST['install-state'])){
        $installState = $_POST['install-state'];
    }else{
        $installState = 1;
    }

    if (isset($_POST['backup']) && $_POST['backup'] == "ja"){
        $backupDesired = true;
    }else{
        $backupDesired = false;
    }

    // Create action form
    $link = 'install.php';

    if($installState == 4) {
        $link = '../gui/editor.php';
    }

    $navbarPlugin = new HtmlForm('navbar_statistics_installation', $link, $page, array('type' => 'default', 'setFocus' => false));
    $navbarPlugin->openGroupBox('');

    if ($installState == 1){
        $page->addHtml(askInstallationStart($page));
    }elseif ($installState == 4){
        if (statCheckPreviousInstallations()) {
            // plugin has already been installed.
            $gMessage->show($gL10n->get('PLG_STATISTICS_PLUGIN_ALREADY_INSTALLED'));
            // EXIT
        } else {
            $navbarPlugin->addDescription($gL10n->get('PLG_STATISTICS_PLUGIN_INSTALLED'));
            startInstallation();
            showActionButton('config');
        }
    }elseif ($installState == 5){
        if (statCheckPreviousInstallations()) {
            $navbarPlugin->addDescription($gL10n->get('PLG_STATISTICS_UNINSTALL_WARNING'));
            showActionButton('uninstall');
        } else {
            $navbarPlugin->addDescription($gL10n->get('PLG_STATISTICS_PLUGIN_NOT_FOUND'));
            showActionButton('home');
        }
    }elseif ($installState == 6){
        $navbarPlugin->addDescription($gL10n->get('PLG_STATISTICS_PLUGIN_UNINSTALLED'));
        deleteOldTables();
        showActionButton('home');
    }else{
        $navbarPlugin->addDescription($gL10n->get('SYS_INVALID_PAGE_VIEW'));
    }
} else {
    if ($gValidLogin) {
        $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
        // => EXIT
    } else {
        require_once(ADMIDIO_PATH.'/adm_program/system/login_valid.php');
    }
}

// show html of complete page
$page->show();

//Fragen ob die Installation gestartet werden soll.
function askInstallationStart($page){
    global $navbarPlugin, $gL10n;
	// Create install options
	$selectionBox = array(4 => $gL10n->get('PLG_STATISTICS_INSTALL'), 5 => $gL10n->get('PLG_STATISTICS_UNINSTALL'));
    $navbarPlugin->addDescription($gL10n->get('PLG_STATISTICS_WELCOME_HEADLINE'));
    $navbarPlugin->addSelectBox('install-state', $gL10n->get('PLG_STATISTICS_ACTION'), $selectionBox, array('property' => HtmlForm::FIELD_REQUIRED));
    $navbarPlugin->addSubmitButton('btn_send', $gL10n->get('PLG_STATISTICS_PERFORM_ACTION'));
    $navbarPlugin->closeGroupBox();
    $page->addHtml($navbarPlugin->show(false));
}


function showActionButton ($type='home') {
    global $navbarPlugin, $gL10n, $page;

    switch ($type) {
        case 'home':
            $value = 1;
            $text = $gL10n->get('PLG_STATISTICS_PERFORM_ACTION');
            break;
        case 'askUninstall':
            $value = 5;
            $text = $gL10n->get('PLG_STATISTICS_CONTINUE_TO_UNINSTALL');
            break;
        case 'uninstall':
            $value = 6;
            $text = $gL10n->get('PLG_STATISTICS_UNINSTALL_PLUGIN');
            break;
        case 'config':
            $value = 1;
            $text = $gL10n->get('PLG_STATISTICS_CONTINUE_TO_STATISTICS_EDITOR');
            break;
    }
    $navbarPlugin->addInput('install-state', '', $value, array('class' => 'invisible'));
    $navbarPlugin->addSubmitButton('btn_send', $text);
    $navbarPlugin->closeGroupBox();
    $page->addHtml($navbarPlugin->show(false));

}

//Backup bereits vorhandener Statistikdefinitionen
function saveOldDefinitionData(){

}

//Installation des Plugins
function startInstallation(){
    global $navbarPlugin, $gL10n, $gMenu;

    executeSQLSktipt('db_statistic_install.sql');
    $navbarPlugin->addDescription($gL10n->get('PLG_STATISTICS_TABLES_CREATED'));
    addStatisticTemplates();
    statAddMenu();
    $navbarPlugin->addDescription($gL10n->get('PLG_STATISTICS_EXAMPLES_ADDED'));
    $gMenu->initialize();
}

function addStatisticTemplates() {
    global $gCurrentOrganization, $gL10n;
    $currentOrgID = $gCurrentOrganization->getValue('org_id','');;


    //Leere Statistik, die für die temporäre Bearbeitung einer Statistik reserviert ist
    $statistic0 = new Statistic(null,$currentOrgID, 'TEMPORARY STATISTIC',null,null, null);

    //Altersstatistik
    $statistic1 = new Statistic(null,$currentOrgID, $gL10n->get('PLG_STATISTICS_AGE_STATISTICS'), $gL10n->get('PLG_STATISTICS_AGE_STATISTICS') , $gL10n->get('PLG_STATISTICS_YEAR') . ' ' . date('Y'), 2);
    $tbl1 = new StatisticTable($gL10n->get('PLG_STATISTICS_BY_AGE_GROUPS'), null, $gL10n->get('PLG_STATISTICS_AGE_GROUPS'));
    $tbl1->addRow(new StatisticTableRow($gL10n->get('PLG_STATISTICS_XY_YEARS', array('0-6')),new StatisticCondition('>=0j AND <=6j',10)));
    $tbl1->addRow(new StatisticTableRow($gL10n->get('PLG_STATISTICS_XY_YEARS', array('7-14')),new StatisticCondition('>=7j AND <=14j',10)));
    $tbl1->addRow(new StatisticTableRow($gL10n->get('PLG_STATISTICS_XY_YEARS', array('15-18')),new StatisticCondition('>=15j AND <=18j',10)));
    $tbl1->addRow(new StatisticTableRow($gL10n->get('PLG_STATISTICS_XY_YEARS', array('19-26')),new StatisticCondition('>=19j AND <=26j',10)));
    $tbl1->addRow(new StatisticTableRow($gL10n->get('PLG_STATISTICS_XY_YEARS', array('27-40')),new StatisticCondition('>=27j AND <=40j',10)));
    $tbl1->addRow(new StatisticTableRow($gL10n->get('PLG_STATISTICS_XY_YEARS', array('41-60')),new StatisticCondition('>=41j AND <=60j',10)));
    $tbl1->addRow(new StatisticTableRow($gL10n->get('PLG_STATISTICS_XY_YEARS', array('>= 61')),new StatisticCondition('>=61j',10)));
    $tbl1->addRow(new StatisticTableRow($gL10n->get('PLG_STATISTICS_NO_INFORMATION'),new StatisticCondition($gL10n->get('PLG_STATISTICS_MISSING'),10)));
    $tbl1->addColumn(new StatisticTableColumn($gL10n->get('SYS_MALE'),new StatisticCondition($gL10n->get('SYS_MALE'),11),new StatisticFunction('#',''),'sum'));
    $tbl1->addColumn(new StatisticTableColumn($gL10n->get('SYS_FEMALE'),new StatisticCondition($gL10n->get('SYS_FEMALE'),11),new StatisticFunction('#',''),'sum'));
    $tbl1->addColumn(new StatisticTableColumn($gL10n->get('PLG_STATISTICS_NO_INFORMATION'),new StatisticCondition($gL10n->get('PLG_STATISTICS_MISSING'),11),new StatisticFunction('#',''),'sum'));
    $tbl1->addColumn(new StatisticTableColumn($gL10n->get('PLG_STATISTICS_TOTAL'),new StatisticCondition(null,null),new StatisticFunction('#',''),'sum'));
    $statistic1->addTable($tbl1);

    //Statistik über die Vollständigkeit der Profile
    $statistic2 = new Statistic(null,$currentOrgID, $gL10n->get('PLG_STATISTICS_PROFILE_COMPLETENESS'), $gL10n->get('PLG_STATISTICS_COMPLETENESS_OF_PROFILE') ,'', 2);
    $tbl6 = new StatisticTable($gL10n->get('PLG_STATISTICS_AFTER_COMPLETENESS'), null,$gL10n->get('SYS_PROFILE_FIELD'));
    $tbl6->addRow(new StatisticTableRow($gL10n->get('SYS_LASTNAME'),new StatisticCondition($gL10n->get('PLG_STATISTICS_AVAILABLE'),1)));
    $tbl6->addRow(new StatisticTableRow($gL10n->get('SYS_FIRSTNAME'),new StatisticCondition($gL10n->get('PLG_STATISTICS_AVAILABLE'),2)));
    $tbl6->addRow(new StatisticTableRow($gL10n->get('SYS_ADDRESS'),new StatisticCondition($gL10n->get('PLG_STATISTICS_AVAILABLE'),3)));
    $tbl6->addRow(new StatisticTableRow($gL10n->get('SYS_POSTCODE'),new StatisticCondition($gL10n->get('PLG_STATISTICS_AVAILABLE'),4)));
    $tbl6->addRow(new StatisticTableRow($gL10n->get('SYS_CITY'),new StatisticCondition($gL10n->get('PLG_STATISTICS_AVAILABLE'),5)));
    $tbl6->addRow(new StatisticTableRow($gL10n->get('SYS_COUNTRY'),new StatisticCondition($gL10n->get('PLG_STATISTICS_AVAILABLE'),6)));
    $tbl6->addRow(new StatisticTableRow($gL10n->get('SYS_PHONE'),new StatisticCondition($gL10n->get('PLG_STATISTICS_AVAILABLE'),7)));
    $tbl6->addRow(new StatisticTableRow($gL10n->get('PLG_STATISTICS_POSSIBLE_INFORMATIONS'),new StatisticCondition('',0)));
    $tbl6->addColumn(new StatisticTableColumn($gL10n->get('PLG_STATISTICS_NUMBER'),new StatisticCondition(null,null),new StatisticFunction('#',''),''));
    $tbl6->addColumn(new StatisticTableColumn($gL10n->get('PLG_STATISTICS_PERCENT'),new StatisticCondition(null,null),new StatisticFunction('%',''),''));
    $statistic2->addTable($tbl6);

    $tbl7 = new StatisticTable($gL10n->get('PLG_STATISTICS_AFTER_MISSING_INFORMATIONS'), null, $gL10n->get('SYS_PROFILE_FIELD'));
    $tbl7->addRow(new StatisticTableRow($gL10n->get('SYS_LASTNAME'),new StatisticCondition($gL10n->get('PLG_STATISTICS_MISSING'),1)));
    $tbl7->addRow(new StatisticTableRow($gL10n->get('SYS_FIRSTNAME'),new StatisticCondition($gL10n->get('PLG_STATISTICS_MISSING'),2)));
    $tbl7->addRow(new StatisticTableRow($gL10n->get('SYS_ADDRESS'),new StatisticCondition($gL10n->get('PLG_STATISTICS_MISSING'),3)));
    $tbl7->addRow(new StatisticTableRow($gL10n->get('SYS_POSTCODE'),new StatisticCondition($gL10n->get('PLG_STATISTICS_MISSING'),4)));
    $tbl7->addRow(new StatisticTableRow($gL10n->get('SYS_CITY'),new StatisticCondition($gL10n->get('PLG_STATISTICS_MISSING'),5)));
    $tbl7->addRow(new StatisticTableRow($gL10n->get('SYS_COUNTRY'),new StatisticCondition($gL10n->get('PLG_STATISTICS_MISSING'),6)));
    $tbl7->addRow(new StatisticTableRow($gL10n->get('SYS_PHONE'),new StatisticCondition($gL10n->get('PLG_STATISTICS_MISSING'),7)));
    $tbl7->addRow(new StatisticTableRow($gL10n->get('PLG_STATISTICS_POSSIBLE_INFORMATIONS'),new StatisticCondition('',0)));
    $tbl7->addColumn(new StatisticTableColumn($gL10n->get('PLG_STATISTICS_NUMBER'),new StatisticCondition(null,null),new StatisticFunction('#',''),''));
    $tbl7->addColumn(new StatisticTableColumn($gL10n->get('PLG_STATISTICS_PERCENT'),new StatisticCondition(null,null),new StatisticFunction('%',''),''));
    $statistic2->addTable($tbl7);

    //Templates in DB speichern
    $staDB = new DBAccess();
    $staDB->saveStatistic($statistic0);
    $staDB->saveStatistic($statistic1);
    $staDB->saveStatistic($statistic2);
}


//Ausführen von angegebenen SQL-Skripts
function executeSQLSktipt($skriptPath){
    global $gDb;
    global $g_tbl_praefix;

    $filename = $skriptPath;
    $file     = fopen($filename, 'r');
    $content  = fread($file, filesize($filename));
    $sql_arr  = explode(';', $content);
    fclose($file);

    foreach($sql_arr as $sql)
    {
        if(strlen(trim($sql)) > 0)
        {
            // Prefix fuer die Tabellen einsetzen und SQL-Statement ausfuehren
            $sql = str_replace('%PREFIX%', $g_tbl_praefix, $sql);
            //echo "<b>Folgende Query wuerde jetzt abgesetzt:</b><br /><br />";
            //echo $sql;
            //echo "<br /><br />";
            $gDb->query($sql);
            //echo $result;
        }
    }
}

//Recovery der Daten
function restoreOldDefinitionData(){


}

//Abschluss der Installation
function deleteOldTables(){
    global $navbarPlugin, $gL10n, $gMenu;

    executeSQLSktipt('db_statistic_delete.sql');
    $navbarPlugin->addDescription($gL10n->get('PLG_STATISTICS_UNINSTALL_TABLES_DELETED'));
    $gMenu->initialize();
}
?>
