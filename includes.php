<?php
/******************************************************************************
 * PHP-Skript für das ermitteln von Dateipfaden
 * 
 * Copyright    : (c) 2004 - 2015 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 * 
 * 
 *****************************************************************************/

// Pfad des Plugins ermitteln
$admidio_folder_pos     = strpos(__FILE__,'adm_plugins');
$admidio_folder_path    = substr(__FILE__,0,$admidio_folder_pos);

$plugin_folder_pos      = strpos(__FILE__, 'adm_plugins') + 11;
$plugin_folder_path     = substr(__FILE__,0,$plugin_folder_pos);

$plugin_file_pos        = strpos(__FILE__, 'includes.php');
$plugin_folder_name     = substr(__FILE__, $plugin_folder_pos+1, $plugin_file_pos-$plugin_folder_pos-2);


if(!defined('PLUGIN_PATH'))
{
    define('PLUGIN_PATH', substr(__FILE__, 0, $plugin_folder_pos));
}

if(!defined('STATISTICS_PATH'))
{
    define('STATISTICS_PATH', $plugin_folder_path.'/'.$plugin_folder_name);
}

require_once(STATISTICS_PATH.'/config.php');
require_once($admidio_folder_path.'adm_program/system/common.php');

?>