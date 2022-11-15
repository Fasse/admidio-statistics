<?php
/******************************************************************************
 * Verarbeitet das Formular der Konfigurationsseite und dessen Aktionen
 *
 * @copyright 2004-2021 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
*
* Parameters:
*
* sta_id : (optional) ID der Statistik, die aktuell bearbeitet werden soll
* mode   : save        - Statistikkonfiguration unter neuem Namen speichern oder bestehende aktualisieren
*          delete      - Statistikkonfiguration loeschen
*          show        - Temporäre Statistikkonfiguration speichern und anzeigen
*          load        - Speichert die Angegebene Statistik in der Temporäten Statistik, welche zum Laden im GUI benötigt wird.
*          editstucture- Verändern der Struktur der temporären Statistikkonfiguration
* name   : (optional) für mode 'save' und 'saveas' die Statistik wird unter diesem Namen gespeichert
*
* Hier muss die Nummer der Tabelle angegeben werden, welcher das neue Element hinzugefügt werden soll.
* editaction:   bewirkt, dass die Struktur der Konfiguration verändert wird (hinzufügen und löschen von Elementen)
*                  danach wird der Konfigurationseditor wieder geladen
*                  addrow      - fügt eine neue leere Zeile der temporären Definition hinzu
*                  addcol      - fügt eine neue leere Spalte der temporären Definition hinzu
*                  addtable    - fügt eine neue leere Tabelle der temporären Definition hinzu
*                  delrow      - entfernt eine Zeile (samt Inhalt) aus der temporären Definition
*                  delcol      - entfernt eine Spalte (samt Inhalt) aus der temporären Definition
*                  deltable    - entfernt eine Tabelle (samt Inhalt) aus der temporären Definition
*                  duplrow     - dupliziert eine Zeile der temporären Definition
*                  duplcol     - dupliziert eine Spalte der temporären Definition
*                  dupltable   - dupliziert eine Tabelle der der temporären Definition
*                  mvrow       - verschiebt eine Zeile der temporären Definition
*                  mvcol       - verschiebt eine Spalte der temporären Definition
*                  mvtable     - verschiebt eine Tabelle der temporären Definition
* editrownr :      Nummer der Zeile die verändert werden soll (beim Löschen zwingend)
* editcolnr :      Nummer der Spalte die verändert werden soll (beim Löschen zwingend)
* edittblnr :      Nummer der Tabelle die verändert werden soll (beim Löschen und Hinzufügen von Spalten und Zeilen zwingend)
* mvupwards :	   (boolean) muss gesetzt werden wenn ein Element nach oben verschoben werden soll
*
*****************************************************************************/
//Import benötigter Skripts
require_once('../includes.php');
require_once(STATISTICS_PATH.'/utils/db_access.php');
require_once(STATISTICS_PATH.'/statistic_objects/statistic.php');


// check if the current user has the right to view the statistics
$sql = 'SELECT men_id FROM ' . TBL_MENU . ' WHERE men_name_intern = \'statistics_editor\' ';
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

if($hasAccess) {
	//Auslesen der Übergabe Parameter
	$getMode      = admFuncVariableIsValid($_GET, 'mode', 'string', array('validValues' => array('load','save','delete','show','editstructure')));
	$getScrollPos = admFuncVariableIsValid($_GET, 'scroll_pos', 'numeric', array('defaultValue' => 0));
	$getName      ='';
	$staDBHandler = new DBAccess();

	//echo 'Mode:'.$getMode;
	//echo '<br />StaID:'.$getStaID;
	//echo '<br />Name:'.$getName;

	switch ($getMode){
		case 'load':
			$getLoadID     = admFuncVariableIsValid($_GET, 'sta_id', 'numeric', array('defaultValue' => 0));
			if ($getLoadID == 0){
				$loadStatistic=createEmptyStatistic();
				$getLoadID = 1;
			}else{
				$loadStatistic=$staDBHandler->getStatistic($getLoadID);
				$loadStatistic->setTmpStatistic();
			}

			$staDBHandler->saveStatistic($loadStatistic);
			returnToGUI($getLoadID);
			break;
		case 'save':
			$getSaveID     = admFuncVariableIsValid($_GET, 'sta_id', 'numeric', array('defaultValue' => 0));
			$getNewID = false;

			if ($getSaveID == ''){
				$getSaveName  = admFuncVariableIsValid($_GET, 'name', 'string');
				$getNewID = true;
				$saveStatistic = createStatisticFromPostInputs('',$getSaveName);
			}else{
				$saveStatistic = createStatisticFromPostInputs($getSaveID);
			}

			$staDBHandler->saveStatistic($saveStatistic);
			$saveStatistic->setTmpStatistic();
			$staDBHandler->saveStatistic($saveStatistic);

			if ($getNewID){
				$staIDs = $staDBHandler->getStatisticIDs();
				$newStaID = max($staIDs);
			}else{
				$newStaID = $getSaveID;
			}
			returnToGUI($newStaID);
			break;
		case 'delete':
			$getStaID     = admFuncVariableIsValid($_GET, 'sta_id', 'numeric', array('defaultValue' => 0));
			$staDBHandler->deleteStatistic($getStaID);
			returnToGUI();
			break;
		case 'show':
			$staDBHandler->saveStatistic(createStatisticFromPostInputs(1));
			showStatistic(1);
			break;
		case 'editstructure':
			$showAsStaID     = admFuncVariableIsValid($_GET, 'sta_id', 'numeric', array('defaultValue' => 0));
			$staDBHandler->saveStatistic(editStructure(createStatisticFromPostInputs(1)));
			returnToGUI($showAsStaID);
			break;
	}
}

function createStatisticFromPostInputs($staID = '',$staName = '')
{
	global $gCurrentOrganization;
	$staOrgId = $gCurrentOrganization->getValue('org_id');

	//Auslesen der Eingaben für die allgemeinen Statistik-Informationen
	$postStatisticTitle              = admFuncVariableIsValid($_POST, 'statistic_title', 'string');
	$postStatisticSubtitle           = admFuncVariableIsValid($_POST, 'statistic_subtitle', 'string');
	$postStatisticStdRole            = admFuncVariableIsValid($_POST, 'statistic_std_role', 'numeric', array('defaultValue' => 0));
	$postNrOfTables                  = admFuncVariableIsValid($_POST, 'nr_of_tables', 'numeric', array('defaultValue' => 1));

	$tmpStatistic = new Statistic($staID,$staOrgId,$staName,$postStatisticTitle,$postStatisticSubtitle,$postStatisticStdRole);

	//Schleife für die Auslesung der Eingaben der Tabellen-Konfigurationen
	for ($tc=0;$tc<$postNrOfTables;$tc++) {
		$postTableTitle              = admFuncVariableIsValid($_POST, 'table'.$tc.'_title', 'string');
		$postTableRole               = admFuncVariableIsValid($_POST, 'table'.$tc.'_role', 'numeric', array('defaultValue' => 0));
		$postTableFirstColLabel      = admFuncVariableIsValid($_POST, 'table'.$tc.'_first_column_label', 'string');
		$postNrOfRows                = admFuncVariableIsValid($_POST, 'table'.$tc.'_nr_of_rows', 'numeric',array('defaultValue' => 1));
		$postNrOfColumns             = admFuncVariableIsValid($_POST, 'table'.$tc.'_nr_of_columns', 'numeric', array('defaultValue' => 0));

		$tmpTable = new StatisticTable($postTableTitle,$postTableRole,$postTableFirstColLabel);

		//Schleife für die Auslesung der Eingaben der Spalten-Konfigurationen
		for ($cc=0;$cc<$postNrOfColumns;$cc++) {
			$postColumnLabel         = admFuncVariableIsValid($_POST, 'table'.$tc.'_column'.$cc.'_label', 'string');
			$postColumnProfileField  = admFuncVariableIsValid($_POST, 'table'.$tc.'_column'.$cc.'_profile_field', 'numeric', array('defaultValue' => 0));
			$postColumnCondition     = admFuncVariableIsValid($_POST, 'table'.$tc.'_column'.$cc.'_condition', 'string');
			$postColumnFunctionArg   = admFuncVariableIsValid($_POST, 'table'.$tc.'_column'.$cc.'_func_arg', 'numeric', array('defaultValue' => 0));
			$postColumnFunctionMain  = admFuncVariableIsValid($_POST, 'table'.$tc.'_column'.$cc.'_func_main', 'string');
			$postColumnFunctionTotal = admFuncVariableIsValid($_POST, 'table'.$tc.'_column'.$cc.'_func_total', 'string');

			$tmpCondition = new StatisticCondition($postColumnCondition,$postColumnProfileField);
			$tmpFunction = new StatisticFunction($postColumnFunctionMain,$postColumnFunctionArg);
			$tmpColumn  = new StatisticTableColumn($postColumnLabel,$tmpCondition,$tmpFunction,$postColumnFunctionTotal);
			$tmpTable->addColumn($tmpColumn);
		}

		//Schleife für die Auslesung der Eingaben der Zeilen-Konfigurationen
		for ($rc=0;$rc<$postNrOfRows;$rc++) {
			$postRowLabel            = admFuncVariableIsValid($_POST, 'table'.$tc.'_row'.$rc.'_label', 'string');
			$postRowProfileField     = admFuncVariableIsValid($_POST, 'table'.$tc.'_row'.$rc.'_profile_field', 'numeric', array('defaultValue' => 0));
			$postColumnCondition     = admFuncVariableIsValid($_POST, 'table'.$tc.'_row'.$rc.'_condition', 'string');

			$tmpCondition = new StatisticCondition($postColumnCondition,$postRowProfileField);
			$tmpRow  = new StatisticTableRow($postRowLabel,$tmpCondition);
			$tmpTable->addRow($tmpRow);
		}
		$tmpStatistic->addTable($tmpTable);
	}
	return $tmpStatistic;
}

function showStatistic($StaID){
	$LocationUpdateString = 'Location: show.php';
	$LocationUpdateString .= '?sta_id='.$StaID;

	header($LocationUpdateString);
}

function editStructure($statisticToEdit){

	$tmpStatistic = $statisticToEdit;
	$getEditStructure = admFuncVariableIsValid($_GET, 'editaction', 'string', array('validValues' => array('addrow','addcol','addtable','delrow','delcol','deltable','duplrow','duplcol','dupltable','mvrow','mvcol','mvtable')));
	$getTableNr       = admFuncVariableIsValid($_GET, 'edittblnr', 'numeric', array('defaultValue' => 0));

	switch ($getEditStructure){
		case 'addrow':
			$tmpTables = $tmpStatistic->getTables();
			$tableToEdit = $tmpTables[$getTableNr];

			$tableToEdit->addRow(createEmptyRow());
			break;
		case 'addcol':
			$tmpTables = $tmpStatistic->getTables();
			$tableToEdit = $tmpTables[$getTableNr];

			$tableToEdit->addColumn(createEmptyColumn());
			break;
		case 'addtable':
			$tmpStatistic->addTable(createEmptyTable());
			break;

		case 'delrow':
			$getRowNr       = admFuncVariableIsValid($_GET, 'editrownr', 'numeric', array('defaultValue' => 0));

			$tmpTables = $tmpStatistic->getTables();
			$tableToEdit = $tmpTables[$getTableNr];

			$tableToEdit->deleteRow($getRowNr);
			break;
		case 'delcol':
			$getColNr       = admFuncVariableIsValid($_GET, 'editcolnr', 'numeric', array('defaultValue' => 0));

			$tmpTables = $tmpStatistic->getTables();
			$tableToEdit = $tmpTables[$getTableNr];

			$tableToEdit->deleteColumn($getColNr);
			break;
		case 'deltable':
			$tmpStatistic->deleteTable($getTableNr);
			break;
		case 'duplrow':
			$getRowNr       = admFuncVariableIsValid($_GET, 'editrownr', 'numeric', array('defaultValue' => 0));

			$tmpTables = $tmpStatistic->getTables();
			$tableToEdit = $tmpTables[$getTableNr];

			$originalRows = $tableToEdit->getRows();
			$duplicatedRows = array($originalRows[$getRowNr]);
			array_splice($originalRows, $getRowNr,0,$duplicatedRows);

			$tableToEdit->setRowArray($originalRows);

			break;
		case 'duplcol':
			$getColNr       = admFuncVariableIsValid($_GET, 'editcolnr', 'numeric', array('defaultValue' => 0));

			$tmpTables = $tmpStatistic->getTables();
			$tableToEdit = $tmpTables[$getTableNr];

			$originalColumns = $tableToEdit->getColumns();
			$duplicatedColumns = array($originalColumns[$getColNr]);
			array_splice($originalColumns, $getColNr,0,$duplicatedColumns);

			$tableToEdit->setColumnArray($originalColumns);

			break;
		case 'dupltable':
			$tmpTables = $tmpStatistic->getTables();
			$tableToEdit = $tmpTables[$getTableNr];

			$duplicatedTables = array($tableToEdit);
			array_splice($tmpTables, $getTableNr,0,$duplicatedTables);

			$tmpStatistic->setTableArray($tmpTables);
			break;
		case 'mvrow':
			$getRowNr       = admFuncVariableIsValid($_GET, 'editrownr', 'numeric', array('defaultValue' => 0));
			$mvUp       	= admFuncVariableIsValid($_GET, 'mvupwards', 'boolean');

			$tmpTables = $tmpStatistic->getTables();
			$tableToEdit = $tmpTables[$getTableNr];

			$originalRows = $tableToEdit->getRows();

			moveArrayElement($originalRows, $getRowNr, $mvUp);

			$tableToEdit->setRowArray($originalRows);

			break;
		case 'mvcol':
			$getColNr       = admFuncVariableIsValid($_GET, 'editcolnr', 'numeric', array('defaultValue' => 0));
			$mvUp       	= admFuncVariableIsValid($_GET, 'mvupwards', 'boolean');

			$tmpTables = $tmpStatistic->getTables();
			$tableToEdit = $tmpTables[$getTableNr];

			$originalColumns = $tableToEdit->getColumns();
			moveArrayElement($originalColumns, $getColNr, $mvUp);

			$tableToEdit->setColumnArray($originalColumns);



			break;
		case 'mvtable':
			$mvUp       	= admFuncVariableIsValid($_GET, 'mvupwards', 'boolean');
			$tmpTables = $tmpStatistic->getTables();

			moveArrayElement($tmpTables, $getTableNr, $mvUp);
			$tmpStatistic->setTableArray($tmpTables);
			break;
	}
	return $tmpStatistic;
}

function moveArrayElement(&$array,$elementNr, $upwards = false){
	$dstIndex = $elementNr;
	if ($upwards && $elementNr > 0){
		$dstIndex--;
	}elseif (!$upwards && $elementNr < (count($array)-1)){
		$dstIndex++;
	}
	$fromElement = $array[$elementNr];
	$toElement = $array[$dstIndex];

	$array[$elementNr] = $toElement;
	$array[$dstIndex] = $fromElement;
}

function createEmptyStatistic(){
	global $gCurrentOrganization;

	$tmpStatistic = new Statistic(1,$gCurrentOrganization->getValue('org_id'),'','','',2);
	for ($tc = 0;$tc <1;$tc++){
		$tmpStatistic->addTable(createEmptyTable());
	}
	return $tmpStatistic;
}

//Wenn der Benutzer eine neue leere Statistik anlegt
function createEmptyTable(){
	$tmpTable = new StatisticTable('',0,'');

	for ($rc = 0;$rc <1;$rc++){
		$tmpTable->addRow(createEmptyRow());
	}

	for ($cc = 0;$cc <1;$cc++){
		$tmpTable->addColumn(createEmptyColumn());
	}

	return $tmpTable;
}

function createEmptyRow(){
	$tmpCondition = new StatisticCondition('',0);
    return new StatisticTableRow('',$tmpCondition);
}

function createEmptyColumn(){
	$tmpCondition = new StatisticCondition('',0);
	$tmpFunction = new StatisticFunction('',0);
    return new StatisticTableColumn('',$tmpCondition,$tmpFunction,'');
}

function createScrollPos(){
	global $getScrollPos;
	$scrollPos = '';
	if ($getScrollPos !=0){
		$scrollPos = 'scroll_pos='.$getScrollPos;
	}
	return $scrollPos;
}

function returnToGUI($StaID = null){
	$LocationUpdateString = 'Location: editor.php';

	if ($StaID != null){
		$LocationUpdateString .= '?sta_id='.$StaID.'&'.createScrollPos();
	}else{
		$LocationUpdateString .= '?'.createScrollPos();
	}
	header($LocationUpdateString);
}

