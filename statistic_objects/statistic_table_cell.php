<?php
/******************************************************************************
 * Definiert eine Zelle innerhalb einer Statistiktabelle
 *
 * Copyright    : (c) 2004 - 2015 The Admidio Team
 * Homepage     : http://www.admidio.org
 *
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.htm
 *
 * Parameters:
 *
 *****************************************************************************/
class StatisticTableCell
{
    protected $columnNr;
    protected $rowNr;
    protected $value;


    public function __construct($columnNr,$rowNr,$value=''){
        $this->rowNr = $rowNr;
        $this->columnNr = $columnNr;
        $this->value = $value;
    }

    //------------------------ GETTER -------------------------------

    public function getColumnNr () {
        return $this->columnNr;
    }

    public function getRowNr(){
        return $this->rowNr;
    }

    public function getValue(){
        return $this->value;
    }

    //------------------------ SETTER -------------------------------

    public function setValue($value){
        $this->value = $value;
    }

}