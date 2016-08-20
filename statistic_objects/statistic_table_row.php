<?php
/******************************************************************************
 * Definiert eine Zeile innerhalb einer Statistiktabelle
 *
 * Copyright    : (c) 2004 - 2015 The Admidio Team
 * Homepage     : http://www.admidio.org
 *
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.htm
 *
 * Parameters:
 *
 *****************************************************************************/
class StatisticTableRow
{
    protected $label;
    protected $condition;


    public function __construct($label, $statisticCondition){
        $this->label = $label;
        $this->condition=$statisticCondition;
    }

    //------------------------ GETTER ------------------------------- //

    public function getLabel () {
        return $this->label;
    }

    public function getCondition(){
        return $this->condition;
    }

}
