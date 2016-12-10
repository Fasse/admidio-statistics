<?php
/******************************************************************************
 * Installationsskript für das Statistik-Plugin
 *
 * Copyright    : (c) 2004 - 2015 The Admidio Team
 * Homepage     : http://www.admidio.org
 *
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.htm
 *
 * Parameters:
 *
 * install-state =  1 : (Default) Initialisierung des Installationsvorgangs
 *                  2 : Prüfung auf vorhandene Installationen
 *                  3 : Backup vorhandener Daten
 *                  4 : Installationsvorgang
 *                  5 : Recovery vorhandener Daten
 *                  6 : Abschluss der Installation
 *
 * backup        =  ja    :   es wurde ein Backup gewünscht, Recovery ausführen
 *                  sonst :   es wurde kein Backup gewünscht, Recovery überspringen
 *
 *****************************************************************************/
//Import benötigter Skripts
require_once('../includes.php');
require_once(STATISTICS_PATH.'/statistic_objects/statistic.php');
require_once(STATISTICS_PATH.'/utils/db_access.php');

$page = new HtmlPage('Statistik-Plugin Installation');
$statisticsInstallationMenu = $page->getMenu();
$statisticsInstallationMenu->addItem('menu_item_back', $gNavigation->getPreviousUrl(), $gL10n->get('SYS_BACK'), 'back.png');

//Überprüfen, ob der Benutzer Zugriff auf die Seite hat
$hasAccess = false;
foreach ($plgAllowInstall AS $i)
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

if($hasAccess == true) {
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

    $navbarPlugin = new HtmlForm('navbar_statistics_installation', null, $page, array('action' => 'install.php', 'type' => 'default', 'setFocus' => false));
    $navbarPlugin->openGroupBox('');

    // Aufrufen der Entsprechenden Installationsschrittes
    if ($installState == 4 && !checkPreviousInstallations()){
        //"Installation abgeschlossen!"
        $navbarPlugin = new HtmlForm('navbar_statistics_installation', '../gui/editor.php', $page, array('type' => 'default', 'setFocus' => false));
        $navbarPlugin->openGroupBox('');
    }else{
        $navbarPlugin = new HtmlForm('navbar_statistics_installation', 'install.php', $page, array('type' => 'default', 'setFocus' => false));
        $navbarPlugin->openGroupBox('');
    }

    if ($installState == 1){
        $page->addHtml(askInstallationStart($page));
    }elseif ($installState == 2){
        $navbarPlugin->addDescription('Es wird jetzt geprüft, ob bereits eine Version des Statistik-Plugins installiert ist:');
        if (checkPreviousInstallations()) {
            $navbarPlugin->addDescription('Es ist bereits eine Version des Plugin installiert. <br/> Bitte das Plugin zuerst deinstallieren.');
            showActionButton('home');
        } else {
            $navbarPlugin->addDescription('Es wurde keine installierte Version gefunden.');
            showActionButton('install');
        }
    }elseif ($installState == 3){
        $navbarPlugin->addDescription('Es ist bereits eine Version des Statistik-Plugins installiert, bitte geben sie an, ob vorhandene Statistik-Definitionen beibehalten werden sollen.');
    }elseif ($installState == 4){
        if (checkPreviousInstallations()) {
            $navbarPlugin->addDescription('Es ist bereits eine Version des Plugin installiert. Bitte diese zuerst deinstallieren.');
            showActionButton('home');
        } else {
            $navbarPlugin->addDescription('Das Plugin wurde installiert.');
            startInstallation();
            showActionButton('config');
        }
    }elseif ($installState == 5){
        if (checkPreviousInstallations()) {
            $navbarPlugin->addDescription('Mit diesem Vorgang wird das Plugin entfernt und alle gespeicherten Statistik-Konfigurationen gelöscht!');
            showActionButton('uninstall');
        } else {
            $navbarPlugin->addDescription('Es wurde keine installierte Version gefunden.');
            showActionButton('home');
        }
    }elseif ($installState == 6){
        //echo "Installation abgeschlossen!";
        $navbarPlugin->addDescription('Deinstallation erfolgreich.');
        deleteOldTables();
        showActionButton('home');
    }else{
        $navbarPlugin->addDescription('Ungültiger Installations-Status');
    }
} else {
    if ($gValidLogin) {
        $navbarPlugin->addDescription('Sie haben keine Berechtigung, diese Seite anzuzeigen.');
    } else {
        require_once(SERVER_PATH.'/adm_program/system/login_valid.php');
    }
}

// show html of complete page
$page->show();

//Fragen ob die Installation gestartet werden soll.
function askInstallationStart($page){
    global $navbarPlugin;
	// Create install options
	$selectionBox = array(2 => 'Installieren', 5 => 'Deinstallieren');
    $navbarPlugin->addDescription('Willkommen zur Installation des Admidio-Statistik-Plugin');
    $navbarPlugin->addSelectBox('install-state', 'Aktion', $selectionBox);
    $navbarPlugin->addSubmitButton('btn_send', 'Aktion ausführen');
    $navbarPlugin->closeGroupBox();
    $page->addHtml($navbarPlugin->show(false));
}


function showActionButton ($type='home') {
    global $navbarPlugin;
    global $page;

    switch ($type) {
        case 'home':
            $value = 1;
            $text = 'Zurück zum Start';
            $link = 'install.php';
            break;
        case 'install':
            $value = 4;
            $text = 'Plugin jetzt installieren';
            $link = 'install.php';
            break;
        case 'askUninstall':
            $value = 5;
            $text = 'Zur Deinstallation';
            $link = 'install.php';
            break;
        case 'uninstall':
            $value = 6;
            $text = 'Plugin jetzt deinstallieren';
            $link = 'install.php';
            break;
        case 'config':
            $value = 1;
            $text = 'Zum Statistikeditor';
            $link = '../gui/editor.php';
            break;
    }
    $navbarPlugin->addInput('install-state', '', $value, array('class' => 'hide'));
    $navbarPlugin->addSubmitButton('btn_send', $text, array('link' => $link));
    $navbarPlugin->closeGroupBox();
    $page->addHtml($navbarPlugin->show(false));

}

//Prüfung auf bereits vorhandene Tabellen in der Datenbank
function checkPreviousInstallations(){
    global $gDb, $gLogger;

    $sql = 'SELECT * FROM ' . TBL_STATISTICS;
    $pdoStatement = $gDb->query($sql,false);
    if ($pdoStatement !== false && $pdoStatement->rowCount() > 0) {
        $gLogger->info('1::'.$pdoStatement->rowCount());
        return true;
    } else {
        return false;
    }
}

//Backup bereits vorhandener Statistikdefinitionen
function saveOldDefinitionData(){


}

//Installation des Plugins
function startInstallation(){
    global $navbarPlugin;

    executeSQLSktipt('db_statistic_install.sql');
    $navbarPlugin->addDescription('Die Tabellen für das Plugin wurden in der Admidio-Datenbank angelegt.');
    addStatisticTemplates();
    $navbarPlugin->addDescription('Beispielstatistiken wurden hinzugefügt.');
}

function addStatisticTemplates() {
    global $gCurrentOrganization;
    $currentOrgID = $gCurrentOrganization->getValue('org_id','');;


    //Leere Statistik, die für die temporäre Bearbeitung einer Statistik reserviert ist
    $statistic0 = new Statistic(null,$currentOrgID, 'TEMPORARY STATISTIC',null,null, null);

    //Altersstatistik
    $statistic1 = new Statistic(null,$currentOrgID, 'Altersstatistik ','Altersstatistik' ,'Jahr ' . date('Y'), 2);
    $tbl1 = new StatisticTable('Nach Altergsgruppen', null,'Altersgruppen');
    $tbl1->addRow(new StatisticTableRow('0-6 Jahre',new StatisticCondition('>=0j AND <=6j',10)));
    $tbl1->addRow(new StatisticTableRow('7-14 Jahre',new StatisticCondition('>=6j AND <=14j',10)));
    $tbl1->addRow(new StatisticTableRow('15-18 Jahre',new StatisticCondition('>=14j AND <=18j',10)));
    $tbl1->addRow(new StatisticTableRow('19-26 Jahre',new StatisticCondition('>=18j AND <=26j',10)));
    $tbl1->addRow(new StatisticTableRow('27-40 Jahre',new StatisticCondition('>=26j AND <=40j',10)));
    $tbl1->addRow(new StatisticTableRow('41-60 Jahre',new StatisticCondition('>=40j AND <=60j',10)));
    $tbl1->addRow(new StatisticTableRow('ab 61 Jahre',new StatisticCondition('>=61j',10)));
    $tbl1->addRow(new StatisticTableRow('Keine Angabe',new StatisticCondition('FEHLT',10)));
    $tbl1->addColumn(new StatisticTableColumn('Männlich',new StatisticCondition('Männlich',11),new StatisticFunction('#',''),'sum'));
    $tbl1->addColumn(new StatisticTableColumn('Weiblich',new StatisticCondition('Weiblich',11),new StatisticFunction('#',''),'sum'));
    $tbl1->addColumn(new StatisticTableColumn('Keine Angabe',new StatisticCondition('FEHLT',11),new StatisticFunction('#',''),'sum'));
    $tbl1->addColumn(new StatisticTableColumn('Gesamt',new StatisticCondition(null,null),new StatisticFunction('#',''),'sum'));
    $statistic1->addTable($tbl1);

    //Statistik über die Vollständigkeit der Profile
    $statistic2 = new Statistic(null,$currentOrgID, 'Profilvollständigkeit','Vollständigkeit der Profilangaben ' ,'', 2);
    $tbl6 = new StatisticTable('Nach Vollständigkeit', null,'Profilfeld');
    $tbl6->addRow(new StatisticTableRow('Name',new StatisticCondition('VORHANDEN',1)));
    $tbl6->addRow(new StatisticTableRow('Vorname',new StatisticCondition('VORHANDEN',2)));
    $tbl6->addRow(new StatisticTableRow('Adresse',new StatisticCondition('VORHANDEN',3)));
    $tbl6->addRow(new StatisticTableRow('PLZ',new StatisticCondition('VORHANDEN',4)));
    $tbl6->addRow(new StatisticTableRow('Ort',new StatisticCondition('VORHANDEN',5)));
    $tbl6->addRow(new StatisticTableRow('Land',new StatisticCondition('VORHANDEN',6)));
    $tbl6->addRow(new StatisticTableRow('Telefonnummer',new StatisticCondition('VORHANDEN',7)));
    $tbl6->addRow(new StatisticTableRow('Mögliche Angaben',new StatisticCondition('',0)));
    $tbl6->addColumn(new StatisticTableColumn('Anzahl',new StatisticCondition(null,null),new StatisticFunction('#',''),''));
    $tbl6->addColumn(new StatisticTableColumn('Prozent',new StatisticCondition(null,null),new StatisticFunction('%',''),''));
    $statistic2->addTable($tbl6);

    $tbl7 = new StatisticTable('Nach fehlenden Angaben', null,'Profilfeld');
    $tbl7->addRow(new StatisticTableRow('Name',new StatisticCondition('FEHLT',1)));
    $tbl7->addRow(new StatisticTableRow('Vorname',new StatisticCondition('FEHLT',2)));
    $tbl7->addRow(new StatisticTableRow('Adresse',new StatisticCondition('FEHLT',3)));
    $tbl7->addRow(new StatisticTableRow('PLZ',new StatisticCondition('FEHLT',4)));
    $tbl7->addRow(new StatisticTableRow('Ort',new StatisticCondition('FEHLT',5)));
    $tbl7->addRow(new StatisticTableRow('Land',new StatisticCondition('FEHLT',6)));
    $tbl7->addRow(new StatisticTableRow('Telefonnummer',new StatisticCondition('FEHLT',7)));
    $tbl7->addRow(new StatisticTableRow('Mögliche Angaben',new StatisticCondition('',0)));
    $tbl7->addColumn(new StatisticTableColumn('Anzahl',new StatisticCondition(null,null),new StatisticFunction('#',''),''));
    $tbl7->addColumn(new StatisticTableColumn('Prozent',new StatisticCondition(null,null),new StatisticFunction('%',''),''));
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
    global $navbarPlugin;

    executeSQLSktipt('db_statistic_delete.sql');
    $navbarPlugin->addDescription('Die Tabellen des Plugins, welche nicht mehr benötigt werden, wurden aus der Admidio-Datenbank entfernt.');
}
?>
