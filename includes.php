<?php
/******************************************************************************
 * Script to include all necessary files and constants
 *
 * Copyright    : (c) 2004 - 2019 The Admidio Team
 * Homepage     : https://www.admidio.org
 * License      : GNU Public License 2 https://www.gnu.org/licenses/gpl-2.0.html
 *
 *****************************************************************************/

$rootPath = dirname(dirname(__DIR__));
$pluginFolder = basename(__DIR__);

if(!defined('STATISTICS_PATH'))
{
    define('STATISTICS_PATH', __DIR__);
}

require_once(__DIR__.'/config.php');
require_once($rootPath.'/adm_program/system/common.php');

?>
