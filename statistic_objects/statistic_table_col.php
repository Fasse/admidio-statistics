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
class StatisticTableColumn
{
    protected $label;
    protected $condition;
    protected $function;
    protected $functionTotal;

    public function __construct($label, $statisticCondition, $statisticFunction, $functionTotal)
    {
        $this->label = $label;
        $this->condition = $statisticCondition;
        $this->function = $statisticFunction;
        $this->functionTotal = $functionTotal;
    }

    //------------------------ GETTER ------------------------------- //

    public function getLabel () {
        return $this->label;
    }

    public function getCondition() {
        return $this->condition;
    }

    public function getFunction(){
        return $this->function;
    }

    public function getFunctionTotal() {
        return $this->functionTotal;
    }
}
