<?php
/******************************************************************************
 * Repräsentiert eine Auswahlbedingung für eine Spalte oder Zeile einer Tabelle
 *
 * Copyright    : (c) 2004 - 2015 The Admidio Team
 * Homepage     : http://www.admidio.org
 *
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.htm
 *
 * Parameters:
 *
 *****************************************************************************/
class StatisticCondition{
    private $userCondition;
    private $profileFieldID;

    public function __construct($userCondition,$profileFieldID){
        $this->userCondition = $userCondition;
        $this->profileFieldID = $profileFieldID;
    }

    //------------------------ GETTER -------------------------------//

    public function getUserCondition(){
        return $this->userCondition;
    }

    public function getProfileFieldID(){
        return $this->profileFieldID;
    }

}