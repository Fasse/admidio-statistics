<?php
/******************************************************************************
 * Hilsklasse zum Verwalten der Statistiken in der DB
 *
 * @copyright 2004-2021 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 *****************************************************************************/

require_once('db_constants.php');


class DBAccess {
    private $tableStatistics;
    private $tableTables;
    private $tableColumns;
    private $tableRows;
    private $pluginInstalled;

    public function __construct() {
        global $gDb;

        if ($this->pluginIsInstalled()) {
            $this->tableStatistics = new TableAccess($gDb, TBL_STATISTICS, 'sta');
            $this->tableTables = new TableAccess($gDb, TBL_TABLES, 'stt');
            $this->tableColumns = new TableAccess($gDb, TBL_COLUMNS, 'stc');
            $this->tableRows = new TableAccess($gDb, TBL_ROWS, 'str');
            $this->pluginInstalled = true;
        } else {
            $this->pluginInstalled = false;
        }
    }

    private function pluginIsInstalled(){
        global $gDb;

        $result = $gDb->query("SHOW TABLES LIKE '" . TBL_STATISTICS . '\'');
        $table = $result->rowCount();
        if ($table == 1) {
           return true;
       } else {
           return false;
       }
    }

    public function getPluginInstalled() {
        return $this->pluginInstalled;
    }

	//Liest eine Statistik aus der Datenbank aus und gibt ein Statistik-Objekt zurück
	public function getStatistic($id) {
        global $gDb;

        $this->tableStatistics->readDataById($id);

        //Metadaten
        $statistic = new Statistic($id, $this->tableStatistics->getValue(TBL_STA_NAME),
                                        $this->tableStatistics->getValue(TBL_STA_ORG_ID),
                                        $this->tableStatistics->getValue(TBL_STA_TITLE),
                                        $this->tableStatistics->getValue(TBL_STA_SUBTITLE),
                                        $this->tableStatistics->getValue(TBL_STA_STDROLE));

        //Tabellen
        $tables = $gDb->query('SELECT * FROM ' . TBL_TABLES .' WHERE ' . TBL_STT_STA_ID . ' = '. $id);
        while($tableRecord = $tables->fetch())
        {
            $currentTable = new StatisticTable($tableRecord[TBL_STT_TITLE],
                                               $tableRecord[TBL_STT_ROLE],
                                               $tableRecord[TBL_STT_FIRST_COLUMN_LABEL]);

            //Zeilen
            $rows = $gDb->query('SELECT * FROM ' . TBL_ROWS .' WHERE ' . TBL_STR_STT_ID . ' = '. $tableRecord[TBL_STT_ID]);
            while($rowRecord = $rows->fetch()) {
                $currentTable->addRow(new StatisticTableRow($rowRecord[TBL_STR_LABEL],
                                                            new StatisticCondition(
                                                                $this->switchUnsaveableConditionChars($rowRecord[TBL_STR_FIELD_CONDITION],true),
                                                                $rowRecord[TBL_STR_PROFILE_FIELD])));
            }

            //Spalten
            $columns = $gDb->query('SELECT * FROM ' . TBL_COLUMNS .' WHERE ' . TBL_STC_STT_ID . ' = '. $tableRecord[TBL_STT_ID]);
            while($colRecord = $columns->fetch()) {
                $currentTable->addColumn(new StatisticTableColumn($colRecord[TBL_STC_LABEL],
                                                               new StatisticCondition(
                                                                   $this->switchUnsaveableConditionChars($colRecord[TBL_STC_FIELD_CONDITION],true),
                                                                   $colRecord[TBL_STC_PROFILE_FIELD]),
                                                               new StatisticFunction(
                                                                   $colRecord[TBL_STC_FUNCTION_MAIN],
                                                                   $colRecord[TBL_STC_FUNCTION_ARG]),
                                                               $colRecord[TBL_STC_FUNCTION_TOTAL]));
            }

            //Gefüllte Tabelle dem Statistik-Objekt hinzufügen
            $statistic->addTable($currentTable);
        }
        return $statistic;
    }

	//Speichert eine neue Statistik in der Datenbank oder aktualisiert eine bestehende.
    //Übergeben wird ein Statistik-Objekt. Hat dieses eine ID, wird die Statistik aktualisiert, sonst neu erstellt.
    public function saveStatistic($statistic) {

        $statisticID = $statistic->getDbID();

        //Hier wird unterschieden, eine bereits bestehende Statistik aktualisiert oder eine neue gespeichert wird
        if ($statisticID != null) {
            //UPDATE
            $this->tableStatistics->readDataById($statisticID);
            $this->tableStatistics->setValue(TBL_STA_ORG_ID,$statistic->getOrgID());
            $this->tableStatistics->setValue(TBL_STA_TITLE,$statistic->getTitle());
            $this->tableStatistics->setValue(TBL_STA_SUBTITLE,$statistic->getSubtitle());
            $this->tableStatistics->setValue(TBL_STA_STDROLE,$statistic->getStandardRoleID());
            $this->tableStatistics->save();
            //Alte Tabellen vor Update löschen. DB-Constraints führen dazu, dass auch die dazugehörigen Zeilen und Spalten gelöscht werden.
            global $gDb;
            $gDb->query('DELETE FROM ' . TBL_TABLES . ' WHERE ' . TBL_STT_STA_ID . ' = ' . $statisticID);
        } else {
            //NEU SPEICHERN
            $this->tableStatistics->clear();
            $this->tableStatistics->setValue(TBL_STA_ORG_ID,$statistic->getOrgID());
            $this->tableStatistics->setValue(TBL_STA_NAME,$statistic->getName());
            $this->tableStatistics->setValue(TBL_STA_TITLE,$statistic->getTitle());
            $this->tableStatistics->setValue(TBL_STA_SUBTITLE,$statistic->getSubtitle());
            $this->tableStatistics->setValue(TBL_STA_STDROLE,$statistic->getStandardRoleID());
            $this->tableStatistics->save();
            //Statistik-ID des eben gespeicherten Datensatzes lesen
            $statisticID = $this->tableStatistics->getValue(TBL_STA_ID);
        }

        //Tabellen speichern
        foreach($statistic->getTables() as $table){
            $this->tableTables->clear();
            $this->tableTables->setValue(TBL_STT_TITLE,$table->getTitle());
            $this->tableTables->setValue(TBL_STT_ROLE,$table->getRoleID());
            $this->tableTables->setValue(TBL_STT_FIRST_COLUMN_LABEL,$table->getFirstColumnLabel());
            $this->tableTables->setValue(TBL_STT_STA_ID,$statisticID);
            $this->tableTables->save();

            //ID des eben gespeicherten Datensatzes lesen
            $tableID = $this->tableTables->getValue(TBL_STT_ID);

            //Zeilen hinzufügen
            foreach($table->getRows() as $row) {
                $this->tableRows->clear();
                $this->tableRows->setValue(TBL_STR_LABEL,$row->getLabel());
                $this->tableRows->setValue(TBL_STR_FIELD_CONDITION,$this->switchUnsaveableConditionChars($row->getCondition()->getUserCondition(),false));
                $this->tableRows->setValue(TBL_STR_PROFILE_FIELD,$row->getCondition()->getProfileFieldID());
                $this->tableRows->setValue(TBL_STR_STT_ID,$tableID);
                $this->tableRows->save();
            }

            //Spalten hinzufügen
            foreach($table->getColumns() as $column) {
                $this->tableColumns->clear();
                $this->tableColumns->setValue(TBL_STC_LABEL,$column->getLabel());
                $this->tableColumns->setValue(TBL_STC_FIELD_CONDITION,$this->switchUnsaveableConditionChars($column->getCondition()->getUserCondition(),false));
                $this->tableColumns->setValue(TBL_STC_PROFILE_FIELD,$column->getCondition()->getProfileFieldID());
                $this->tableColumns->setValue(TBL_STC_FUNCTION_MAIN,$column->getFunction()->getName());
                $this->tableColumns->setValue(TBL_STC_FUNCTION_ARG,$column->getFunction()->getArgument());
                $this->tableColumns->setValue(TBL_STC_FUNCTION_TOTAL,$column->getFunctionTotal());
                $this->tableColumns->setValue(TBL_STC_STT_ID, $tableID);
                $this->tableColumns->save();
            }
        }
    }

    private function switchUnsaveableConditionChars($condition, $on){
        if ($on) {
            $search = array("}","{");
            $replace = array(">","<");
        } else {
            $search = array(">","<");
            $replace = array("}","{");
        }

        return str_replace($search,$replace,$condition);
    }

	//Löscht eine Statistik aus der DB anhand deren ID.
	public function deleteStatistic($id) {
        //Statistik wird gelöscht. DB-Constraints führen dazu, dass auch die dazugehörigen Tabellen gelöscht werden.
        $this->tableStatistics->clear();
        $this->tableStatistics->readDataById($id);
        $this->tableStatistics->delete();
    }

	//Gibt die IDs aller in der Datenbank gespeicherten Statistiken für eine bestimmte Organisation zurück
	public function getStatisticIDs($orgId = '1'){
        global $gDb;
        $IDs = array();

        //Statistiken der zugehörigen Organisation anzeigen
        $staResultSet = $gDb->query('SELECT * FROM ' . TBL_STATISTICS . '
                                    WHERE ' . TBL_STA_ORG_ID . ' = ' . $orgId);
        while($statisticRecord = $staResultSet->fetch())
        {
            //ID 1 ist für die temporäre Speicherung der Statistiken reserviert
            if ($statisticRecord[TBL_STA_ID] != 1) $IDs[] = $statisticRecord[TBL_STA_ID];
        }

        return $IDs;
    }

    //Gibt den Namen zu einer Statistik-ID zurück
    public function getStatisticName($id){
        $this->tableStatistics->readDataById($id);
        return $this->tableStatistics->getValue(TBL_STA_NAME);
    }
}
?>
