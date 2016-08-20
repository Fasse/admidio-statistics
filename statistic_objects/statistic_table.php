<?php
/******************************************************************************
 * Definiert eine Tabelle innerhalb einer Statistik
 *
 * Copyright    : (c) 2004 - 2015 The Admidio Team
 * Homepage     : http://www.admidio.org
 *
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.htm
 *****************************************************************************/

require_once ('statistic_table_row.php');
require_once ('statistic_table_col.php');
require_once (STATISTICS_PATH.'/utils/evaluator.php');

class StatisticTable {
	protected $title;
	protected $roleID;
    protected $firstColumnLabel;

    protected $rows = array();
    protected $columns = array();
    protected $cells = array();

	public function __construct($title, $roleID, $firstColumnLabel)
    {
		$this->title = $title;
		$this->roleID = $roleID;
        $this->firstColumnLabel = $firstColumnLabel;
    }

    //  -------------------- ADDER ------------------------ //

    public function addRow($statisticTableRow) {
        $this->rows[] = $statisticTableRow;
    }

    public function addColumn($statisticTableColumn) {
        $this->columns[] = $statisticTableColumn;
    }

//	addCell-Funktion wurde durch setCell ersetzt    
//     public function addCell($statisticTableCell) {
//         $this->cells[] = $statisticTableCell;
//     }
    
    //  -------------------- SETTER ------------------------ //
    
//     public function setRow($statisticTableRow, $nr) {
//     	$this->rows[$nr] = $statisticTableRow;
//     }
    
//     public function setColumn($statisticTableColumn, $nr) {
//     	$this->columns[$nr] = $statisticTableColumn;
//     }
	public function setRowArray($rows){
		$this->rows=$rows;
	}
	public function setColumnArray($columns){
		$this->columns=$columns;
	}

    public function setCell($statisticTableCell){
    	
		$colNr = $statisticTableCell->getColumnNr();
		$rowNr = $statisticTableCell->getRowNr();
		
		//Zuerst alte Zelle löschen (falls vorhanden)
		$this->deleteCell($colNr, $rowNr);
		
		//Danach neue hinzufügen
		$this->cells[] = $statisticTableCell;		
    }
    
    // ------------------------ DELETER ---------------------------- //

    public function deleteRow($nr){
        unset($this->rows[$nr]);
    }

    public function deleteColumn($nr){
        unset($this->columns[$nr]);
    }

    public function deleteCell($colNr, $rowNr){
        foreach ($this->cells as $cell) {
            if ($cell->getColumnNr() == $colNr && $cell->getRowNr() == $rowNr){ 
            	unset($cell);
            	break;
            }       
        }
    }

    //  -------------------- GETTER ------------------------ //

    public function getTitle() {
        return $this->title;
    }

    public function getRoleID() {
        return $this->roleID;
    }

    public function getFirstColumnLabel() {
        return $this->firstColumnLabel;
    }

    public function getColumns(){
        return $this->columns;
    }

    public function getRows(){
        return $this->rows;
    }
    
    public function getColumn($nr){
    	return $this->columns[$nr];
    }
    
    public function getRow($nr){
    	return $this->rows[$nr];
    }

    public function getCell($colNr, $rowNr){
        foreach ($this->cells as $cell) {
            if ($cell->getColumnNr() == $colNr && $cell->getRowNr() == $rowNr) return $cell;
        }

        //Falls die Zelle nicht existiert, gib eine leere Zelle zurück
        return new StatisticTableCell($colNr,$rowNr,null);
    }

 }
?>