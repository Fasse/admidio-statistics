<?php
/******************************************************************************
 * Definiert eine statistische Funktion
 *
 * Copyright    : (c) 2004 - 2015 The Admidio Team
 * Homepage     : http://www.admidio.org
 *
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.htm
 *
 * Parameters:
 *  name:
 *
 *****************************************************************************/
class StatisticFunction{
    private $name;
    private $argument;

    public function __construct($name,$argument){
        $this->name = $name;
        $this->argument = $argument;
    }

    //------------------------ GETTER -------------------------------//

    public function getName(){
        return $this->name;
    }

    public function getArgument(){
        return $this->argument;
    }

}