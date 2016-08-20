<?php
/******************************************************************************
 * Statistik-Objekt, enthält Metadaten und Tabellen
 *
 * Copyright    : (c) 2004 - 2015 The Admidio Team
 * Homepage     : http://www.admidio.org
 *
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.htm
 *
 * Parameters:
 *
 *****************************************************************************/

require_once('statistic_table.php');
require_once('statistic_table_col.php');
require_once('statistic_table_row.php');
require_once('statistic_table_cell.php');
require_once('statistic_condition.php');
require_once('statistic_function.php');

class Statistic {
    //Eindeutige Nummer, mit der die Statistik in der DB identifiziert werden kann.
    protected $dbID;
    protected $orgID;
    protected $name;
    protected $title;
	protected $subtitle;
	protected $standardRoleID;
   
	// Array, das die Tabellen der Statistik enthält (statisticTable-Objekte)
	protected $tables = array();

    public function __construct($dbID,$orgID, $name, $title, $subtitle, $standardRoleID){
        $this->dbID = $dbID;
        $this->orgID = $orgID;
        $this->name = $name;
        $this->title = $title;
        $this->subtitle = $subtitle;
        $this->standardRoleID = $standardRoleID;
    }

    // ------------------------ GETTER ---------------------------- //

    public function getDbID () {
        return $this->dbID;
    }

    public function getOrgID()
    {
        return $this->orgID;
    }

    public function getName () {
        return $this->name;
    }

    public function getTitle () {
        return $this->title;
    }

	public function getSubtitle () {
        return $this->subtitle;
    }

    public function getStandardRoleID () {
        return $this->standardRoleID;
    }

    public function getTables(){
        return $this->tables;
    }
    
    public function getTable($nr){
    	return $this->tables[$nr];
    }

    // ------------------------ SETTER ---------------------------- //
    public function setTmpStatistic (){
        $this->dbID = 1;
    }
    
    public function setName($name){
    	$this->name = $name;
    }
    
//     public function setTable($table, $nr){
//     	$this->tables[$nr] = $table;
//     }

    public function setTableArray($tables){
    	$this->tables=$tables;
    }

    // ------------------------ ADDER ---------------------------- //

    public function addTable($table){
        $this->tables[] = $table ;
    }

    // ------------------------ DELETER ---------------------------- //

    public function deleteTable($nr){
        unset($this->tables[$nr]);
    }
}
?>