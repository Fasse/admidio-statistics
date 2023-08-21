<?php
/******************************************************************************
 * Berechnet eine Statistik anhand der Statistik-Definition
 *
 * @copyright 2004-2021 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 ****************************************************************************
 */

require_once(STATISTICS_PATH.'/statistic_objects/statistic.php');

class Evaluator
{
    protected $DEBUG_MODE = false;

    //Wandelt eine Statistikdefinition in eine Statistik mit berechneten Werten um.
    public function calculateStatistic($staDef) {
        global $gL10n;

        // ----- Statistikdefinition kopieren -----

        // *** Metadaten übertragen ***

        $staFinal = new Statistic($staDef->getdbID(),$staDef->getOrgId(),$staDef->getName(),
                                  $staDef->getTitle(),$staDef->getSubtitle(),
                                  $staDef->getStandardRoleID());

        // *** Tabellen übertragen ***

        $tables = $staDef->getTables();
        foreach ($tables as $table) {
            //Enthält alle Bedingungen der gesamten Tabelle. Für die aktuelle Tabelle zurücksetzen.
            $allConditions = array();

            //Rolle, die für diese Tabelle definiert wurde. Ist keine Tabellenrolle angegeben, wird die Standardrolle der Statistik genommen
            $tableRoleID = $table->getRoleID();
            $effectiveTableRoleID = (empty($tableRoleID) ? $staDef->getStandardRoleID():$tableRoleID);

            $newTable = new StatisticTable($table->getTitle(),
                                           $table->getRoleID(),
                                           $table->getFirstColumnLabel());

            // ***** Spalten übertragen *****

            //Gibt an, ob eine Spalte ohne Bedingung existiert
            $missingColCondition = false;

            $columns = $table->getColumns();
            foreach($columns as $column) {
                $newTable->addColumn(new StatisticTableColumn($column->getLabel(),
                    new StatisticCondition($column->getCondition()->getUserCondition(), $this->getProfileFieldNameFromID($column->getCondition()->getProfileFieldID())),
                    new StatisticFunction(null,null),
                    $column->getFunctionTotal()));
                //Spaltenbedingung, falls vorhanden, zum Array aller Bedingungen der Tabelle hinzufügen
                $colConditionID = $column->getCondition()->getProfileFieldID();
                if ($colConditionID <> 0) {
                    $allConditions[] = $column->getCondition();
                } else {
                    $missingColCondition = true;
                }
            }

            // ***** Zeilen übertragen *****

            //Gibt an, ob eine Zeile ohne Bedingung existiert
            $missingRowCondition = false;

            $rows = $table->getRows();
            foreach($rows as $row) {
                $newTable->addRow(new StatisticTableRow($row->getLabel(),
                    new StatisticCondition($row->getCondition()->getUserCondition(), $this->getProfileFieldNameFromID($row->getCondition()->getProfileFieldID()))));
                //Zeilenbedingung, falls vorhanden, zum Array aller Bedingungen der Tabelle hinzufügen
                $rowConditionID = $row->getCondition()->getProfileFieldID();
                if ($rowConditionID <> 0) {
                    $allConditions[] = $row->getCondition();
                } else {
                    $missingRowCondition = true;
                }
            }

            if ($this->DEBUG_MODE) echo '----- Tabellepopulation START -----<br/>' ;

            // ***** Tabellenpopulation berechnen *****
            if ($missingColCondition && $missingRowCondition) {
                $tablePopulation = $this->getUserCountFromRoleId($effectiveTableRoleID);
            } else {
                $tablePopulation = count($this->getUserIdsMeetConditions($effectiveTableRoleID, $allConditions,false));
            }

            if ($this->DEBUG_MODE) echo 'Tabellenpopulation \'' .$table->getTitle() . '\': ' . $tablePopulation . '<br/>';

            if ($this->DEBUG_MODE) echo '----- Tabellepopulation ENDE -----<br/>';

            // ***** Statistische Werte aller Zellen berechnen *****
            for($r = 0; $r < count($rows); $r++) {
                for($c = 0; $c < count($columns); $c++) {

                    //Bedingungen für aktuelle Zelle zurücksetzen
                    $conditions = array();

                    //Zeilenbedingung, falls vorhanden, hinzufügen
                    $rowConditionID = $rows[$r]->getCondition()->getProfileFieldID();
                    if ($rowConditionID <> 0) $conditions[] = $rows[$r]->getCondition();

                    //Spaltenbedingung, falls vorhanden, hinzufügen
                    $colConditionID = $columns[$c]->getCondition()->getProfileFieldID();
                    if ($colConditionID <> 0) $conditions[] = $columns[$c]->getCondition();

                    $function = $columns[$c]->getFunction();

                    $newTable->setCell(new StatisticTableCell($c,$r,$this->getStatisticCellValue($conditions,$function,$effectiveTableRoleID,$tablePopulation)));

                }
            }

            // ***** Spaltentotal berechnen *****
            $addTotalRow = false;

            for($c = 0; $c < count($columns); $c++) {
                if (!is_null($columns[$c]->getFunctionTotal())) {
                    $addTotalRow = true;
                    $lastRowNr = count($table->getRows());
                    $min = 10000;
                    $max = 0;
                    $totalValue = 0;
                    for($r = 0; $r < count($rows); $r++) {
                        $currentValue = $newTable->getCell($c,$r)->getValue();
                        $totalValue += $currentValue;
                        If ($currentValue < $min) $min = $currentValue;
                        If ($currentValue > $max) $max = $currentValue;
                    }

                    $functionTotal = $columns[$c]->getFunctionTotal();
                    switch($functionTotal) {
                        case 'sum':
                            $totalString = '(Σ) ' . $totalValue;
                            break;
                        case 'avg':
                            $totalString =  '(Ø) ' . sprintf("%01.1f",$totalValue / count($rows));
                            break;
                        case 'min':
                            $totalString = '(min) ' . $min;
                            break;
                        case 'max':
                            $totalString =  '(max) ' . ($max) ;
                            break;
                        default:
                            $totalString = '?';
                    }

                    if ($columns[$c]->getFunction()->getName() == '%') $totalString .= ' %';

                    $newTable->setCell(new StatisticTableCell($c,$lastRowNr,$totalString));
                }
            }

            if ($addTotalRow) $newTable->addRow(new StatisticTableRow($gL10n->get('PLG_STATISTICS_TOTAL'),new StatisticCondition(null,null)));

            $staFinal->addTable($newTable);
        }

        return $staFinal;
    }

    private function getStatisticCellValue($conditions, $function, $roleID, $tablePopulation) {
        switch ($function->getName()) {
            case '#':
                return $this->staFuncCount($roleID, $conditions,$function->getArgument());
                break;
            case '%':
                return $this->staFuncPercent($roleID,$conditions ,$tablePopulation,$function->getArgument());
                break;
            case 'max':
                return $this->staFuncMax($roleID, $conditions,$function->getArgument());
                break;
            case 'min':
                return $this->staFuncMin($roleID, $conditions,$function->getArgument());
                break;
            case 'avg':
                return $this->staFuncAvg($roleID, $conditions,$function->getArgument());
                break;
            case 'sum':
                return $this->staFuncSum($roleID, $conditions,$function->getArgument());
                break;
            default:
                return '0';
        }
    }

    //***************************************************************
    // Statistik-Funktionen
    //***************************************************************
    public function staFuncCount($roleID, $conditionsArray,$arguments) {
        $result = NULL;

        $userIDs =  $this->getUserIdsMeetConditions($roleID,$conditionsArray);
        //if ($this->DEBUG_MODE) print_r($userIDs);

        $result = count($userIDs);
        //$result = count($userIDs);

        return $result;
    }

    public function staFuncPercent($roleID, $conditionsArray, $baseValue, $arguments) {

        if ($baseValue <> 0) {
            $userIDsCellCondition =  $this->getUserIdsMeetConditions($roleID,$conditionsArray);
            $percentage = 100 / $baseValue * count($userIDsCellCondition);
            //$result  =  sprintf("%02.1f %%",$percentage);

            //$percentage = 100 / $this->getUserCountFromRoleId($roleID) * count($userIDsCellCondition);
        } else {
            $percentage = 0;
        }

        $result  =  sprintf("%01.1f",$percentage) . ' %';
        return $result;
    }


    public function staFuncMax($roleID,$conditionsArray, $profileFieldID) {
        $result = NULL;

        $userIDs =  $this->getUserIdsMeetConditions($roleID,$conditionsArray,false);

        if(empty($userIDs)) {
            $result = 0;
        } else {
            $records = $this->getProfileDataDB($userIDs,$profileFieldID);
            $userFieldType = $this->getProfileFieldTypeFromID($profileFieldID);

            switch ($userFieldType) {
                case 'DATE':
                    $years = $this->getYearsFromDates($records);
                    $result = max($years);
                    break;
                case 'NUMERIC':
                    $result = max($records);
                    break;
                case 'TEXT':
                    $result = max($records);
                    break;
                case 'DECIMAL':
                    $result = max($records);
                    break;
            }
        }
        return $result;
    }

	public function staFuncMin($roleID,$conditionsArray,$profileFieldID) {
        $result = NULL;

        $userIDs =  $this->getUserIdsMeetConditions($roleID,$conditionsArray,false);

        if(empty($userIDs)) {
            $result = 0;
        } else {
            $records = $this->getProfileDataDB($userIDs,$profileFieldID);
            $userFieldType = $this->getProfileFieldTypeFromID($profileFieldID);

            switch ($userFieldType) {
                case 'DATE':
                    $years = $this->getYearsFromDates($records);
                    $result = min($years);
                    break;
                case 'NUMERIC':
                    $result = min($records);
                    break;
                case 'TEXT':
                    $result = min($records);
                    break;
                case 'DECIMAL':
                    $result = min($records);
                    break;
            }
        }

        return $result;
    }

	public function staFuncAvg($roleID,$conditionsArray,$profileFieldID) {
        $result = NULL;

        $userIDs =  $this->getUserIdsMeetConditions($roleID,$conditionsArray,false);

        if(empty($userIDs)) {
            $result = 0;
        } else {
            $records = $this->getProfileDataDB($userIDs,$profileFieldID);
            $userFieldType = $this->getProfileFieldTypeFromID($profileFieldID);

            switch ($userFieldType) {
                case 'DATE':
                    $years = $this->getYearsFromDates($records);
                    $result = array_sum($years) / count($years);
                    break;
                case 'NUMERIC':
                    $result =  array_sum($records) / count($records);
                    break;
                case 'DECIMAL':
                    $result =  array_sum($records) / count($records);
                    break;
            }
        }

        $result  =  sprintf("%01.1f",$result);
        return $result;
    }

    public function staFuncSum($roleID,$conditionsArray,$profileFieldID) {
        $result = NULL;

        $userIDs =  $this->getUserIdsMeetConditions($roleID,$conditionsArray,false);

        if(empty($userIDs)) {
            $result = 0;
        } else {
            $records = $this->getProfileDataDB($userIDs,$profileFieldID);
            $userFieldType = $this->getProfileFieldTypeFromID($profileFieldID);

            switch ($userFieldType) {
                case 'DATE':
                    $years = $this->getYearsFromDates($records);
                    $result = array_sum($years);
                    break;
                case 'NUMERIC':
                    $result = array_sum($records);
                    break;
                case 'DECIMAL':
                    $result = array_sum($records);
                    break;
            }
        }

        return $result;
    }



    //***************************************************************
    // Private Hilfsfunktionen
    //***************************************************************


    private function getProfileDataDB ($userIDs, $userFieldID) {
        global $gDb;

        $DBValues = array();

        $sql = 'SELECT * FROM ' . TBL_USER_DATA . ' WHERE usd_usr_id IN (' . implode(',',$userIDs) . ') AND usd_usf_id = ' . $userFieldID;

        if ($this->DEBUG_MODE) echo 'getProfileDataDB: ' . $sql . '<br />';

        $recordset = $gDb->query($sql);

        while($row = $recordset->fetch())
        {
            $DBValues[] = $row['usd_value'];
        }

        return $DBValues;
    }

    private function getUserIdsMeetConditions ($roleID,$conditions, $mustMeetAll=true){
        global $gDb;
        $allUserIds = array();

        if ($this->DEBUG_MODE) {
            echo '<hr/>----- Auswahlbedingungen auswerten START -----<br/>';
            echo '<p><b>Anzahl Bedingungen:</b> ' . count($conditions) . '</p>';
            echo ($mustMeetAll ? ' (Alle Bedingungen müssen erfüllt sein.': ' (Mindestens eine Bedingung muss erfüllt sein.') . ')</p>';
            echo '<b>Bedingungen:</b><ol>';
            foreach($conditions as $condition) {
                echo '<li>'. $condition->getUserCondition() .'</li>';
            }
            echo '</ol><b>SQL-Statements:</b><ol>';
        }

        //Wurden keine Bedinungen angegeben, gib alle User-IDs der Rolle zurück
        if (empty($conditions)){
            if ($this->DEBUG_MODE) echo 'Alle Rollenmitglieder werden ausgewählt, da keine Bedinungen vorhanden sind.<br/>';
            $sql = 'SELECT * FROM '. TBL_MEMBERS.' WHERE mem_end = \'9999-12-31\' AND mem_rol_id = '.$roleID;
            $recordset = $gDb->query($sql);

            while($row = $recordset->fetch())
            {
                $userIdsMeetCond[] = $row['mem_usr_id'];
            }
        } else {
            //Jede Bedinung muss einzeln ausgewertet werden, da unteschiedliche Profilfelder einbezogen sein können
            foreach ($conditions as $condition) {
                $userCondition = $condition->getUserCondition();
                $profileFieldID = $condition->getProfileFieldID();

                switch  (strtoupper($userCondition))
                {
                    case 'FEHLT':
                    case 'MISSING':
                        $sql = 'SELECT DISTINCT(mem_usr_id) FROM ' .  TBL_MEMBERS. ' WHERE mem_end = \'9999-12-31\' AND mem_rol_id = '.$roleID . ' AND mem_usr_id NOT IN (SELECT DISTINCT(usd_usr_id) FROM ' . TBL_USER_DATA . ' WHERE usd_usf_id = ' . $profileFieldID .')';
                        break;
                    case 'VORHANDEN':
                    case 'AVAILABLE':
                        //Bedingung am Schluss der Abfrage wird weggelassen. Eintrag in Tabelle User-Data muss jedoch vorhanden sein.
                        $sql = 'SELECT * FROM '. TBL_MEMBERS.' JOIN ' . TBL_USER_DATA . ' ON mem_usr_id = usd_usr_id WHERE mem_end = \'9999-12-31\' AND mem_rol_id = '.$roleID . ' AND usd_usf_id = ' . $profileFieldID;
                        break;
                    default:
                        //Wandelt die Bedingung, die der User eingegeben hat, in ein SQL-Statement um
                        $SQLCondition = $this->getSQLFromUserConditions($profileFieldID, $userCondition);

                        $sql = 'SELECT * FROM '. TBL_MEMBERS.' JOIN ' . TBL_USER_DATA . ' ON mem_usr_id = usd_usr_id WHERE mem_end = \'9999-12-31\' AND mem_rol_id = '.$roleID . ' AND usd_usf_id = ' . $profileFieldID . $SQLCondition;
                        break;
                }

                if ($this->DEBUG_MODE) {
                    echo '<li>' . $sql . '</li>';
                }

                $recordset = $gDb->query($sql);

                while($row = $recordset->fetch())
                {
                    $allUserIds[] = $row['mem_usr_id'];
                }
            }

            $userIdsMeetCond = array();
            $NrOfCond = count($conditions);

            if ($this->DEBUG_MODE) {
                echo '</ol>';
                echo '<b>Ausgewählte User-IDs</b>:<ol>';
            }

            if ($mustMeetAll) {
                //Alle Bedingungen müssen gleichzeitig zutreffen damit eine User-ID (bzw. das Profil) ausgewählt wird.
                //Jede User-ID, auf die alle Bedingungen zutreffen, muss so oft im Array vorkommen, wie es Bedingungen gibt.
                foreach ($allUserIds as $id) {
                    if ($this->array_count_this_value($allUserIds,$id) == $NrOfCond) {
                        if (!in_array($id,$userIdsMeetCond)) {
                            $userIdsMeetCond[] = $id;
                            if ($this->DEBUG_MODE) echo '<li>Ok: ' .$id .'</li>';
                        }
                    } else {
                        if ($this->DEBUG_MODE) echo '<li>NOK: ' .$id . '</li>';
                    }
                }
            } else {
                //Nur eine der übergebenen Bedingungen muss zutreffen
                foreach ($allUserIds as $id) {
                        if (!in_array($id,$userIdsMeetCond)) {
                            //echo 'ID ' . $id . ' entspricht mindestens einer Bedinung und wurde dem Array hinzugefügt.<br />';
                            $userIdsMeetCond[] = $id;
                            if ($this->DEBUG_MODE) echo '<li>Ok: ' .$id . '</li>';
                        }
                }
            }
        }
        if ($this->DEBUG_MODE) {
            echo '</ol>';
            //echo '<b>Anzahl IDs ausgewählt:</b>' . count($userIdsMeetCond) . '<br/>';
            echo '----- Auswahlbedingungen auswerten ENDE -----<br/><hr/>';
        }
        return $userIdsMeetCond;
    }

    private function array_count_this_value($array, $value) {
        $values=array_count_values($array);
        return $values[$value];
    }


    private function getProfileFieldTypeFromID ($profileFieldID) {
        global $gProfileFields;

        $fieldType = $gProfileFields->getPropertyByID($profileFieldID,'usf_type','text');

        return $fieldType;
    }



    private function getYearsFromDates ($arrayDates) {
       foreach ($arrayDates as $dateValue) {
           list($Y,$m,$d)    = explode("-",$dateValue);
           $arrayYears[] = ( date("md") < $m.$d ? date("Y")-$Y-1 : date("Y")-$Y );
       }

       return $arrayYears;
    }

    public function getSQLFromUserConditions ($userFieldID, $condition) {
        global $gL10n, $gProfileFields, $gLogger;

        $SQLCondStr = '';

        if(strtoupper($condition) !== 'FEHLT' || strtoupper($condition) !== 'MISSING')
        {
            //Vergleichszeichen ersetzen
            $condition = str_replace('>','}',$condition);
            $condition = str_replace('<','{',$condition);

            //mögliche Feldtypen, die der Condition Parser von admidio erkennt: date, string, checkbox, int
            $userFieldType = $this->getProfileFieldTypeFromID($userFieldID);

            //Wie wenn der User keine Condition angegeben hätte
            //$condition = str_replace('VORHANDEN','',$condition);

            switch ($userFieldType) {
                case 'DATE' :
                    $dataType = 'date';
                    break;
                case 'TEXT':
                case 'URL':
                case 'EMAIL':
                    $dataType = 'string';
                    break;
                case 'CHECKBOX':
                    $dataType = 'checkbox';
                    $arrCheckboxValues = array($gL10n->get('SYS_YES'), $gL10n->get('SYS_NO'), 'true', 'false');
                    $arrCheckboxKeys   = array(1, 0, 1, 0);
                    $condition = str_replace(array_map('StringUtils::strToLower',$arrCheckboxValues), $arrCheckboxKeys, StringUtils::strToLower($condition));
                    break;
                case 'NUMERIC':
                    $dataType = 'int';
                    break;
                case 'DROPDOWN':
                case 'RADIO_BUTTON':
                    $dataType = 'string'; //eigentlich $dataType = 'int';
                    // replace all field values with their internal numbers
                    $arrListValues = $gProfileFields->getPropertyById($userFieldID, 'usf_value_list', 'text');
                    // replace with preg_replace the whole word so that male will not be replaced in female
                    $condition = array_search(StringUtils::strToLower($condition), array_map('StringUtils::strToLower', $arrListValues), true);
                    break;
                default:
                    $dataType = 'string';
            }

            $condParser = new ConditionParser();
            $SQLCondStr = $condParser->makeSqlStatement($condition, 'usd_value', $dataType, '');
        }

        return $SQLCondStr;
    }

    private function getUserIdsFromRoleId ($roleID){
        global $gDb;

        $sql = 'SELECT * FROM '. TBL_MEMBERS.' WHERE mem_end = \'9999-12-31\' AND mem_rol_id = '.$roleID;
        $recordset = $gDb->query($sql);

        while($row = $recordset->fetch())
        {
            $allUserIds[] = $row['mem_usr_id'];
        }

        return $allUserIds;
    }


    private function getProfileFieldNameFromID ($userFieldID) {
        if (isset($userFieldID) && is_int($userFieldID)) {
            global $gDb;
            $sql = 'SELECT * FROM '. TBL_USER_FIELDS.' WHERE usf_id = ' . $userFieldID;
            $recordset = $gDb->query($sql);
            $row = $recordset->fetch();
            $roleName = $row['usf_name'];
        } else {
            $roleName = '';
        }

        return $roleName;
    }

    //***************************************************************
    // Öffentliche Hilfsfunktionen
    //***************************************************************

    public function getUserCountFromRoleId ($roleID){
        global $gDb;

        $sql = 'SELECT * FROM '. TBL_MEMBERS.' WHERE mem_end = \'9999-12-31\' AND mem_rol_id = '.$roleID;
        $recordset = $gDb->query($sql);
        $userCount = $recordset->rowCount();

        return $userCount;
    }

    public function getRoleNameFromID ($roleID) {
        if (isset($roleID)) {
            global $gDb;
            $sql = 'SELECT * FROM '. TBL_ROLES.' WHERE rol_id = ' . $roleID;
            $recordset = $gDb->query($sql);
            $row = $recordset->fetch();
            $roleName = $row['rol_name'];
        } else {
            $roleName = '';
        }

        return $roleName;
    }

}

?>
