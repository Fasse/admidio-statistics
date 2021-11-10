<?php
/******************************************************************************
 * Script to include all necessary files and constants
 *
 * @copyright 2004-2021 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 *****************************************************************************/

$rootPath = dirname(dirname(__DIR__));
$pluginFolder = basename(__DIR__);

if(!defined('STATISTICS_PATH'))
{
    define('STATISTICS_PATH', __DIR__);
}

require_once($rootPath.'/adm_program/system/common.php');

?>
