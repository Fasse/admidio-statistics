<?php
/******************************************************************************
 * Statistiken V 3.1.1 (kompatibel mit Admidio 4.0)
 *
 * Beta Version
 *
 * Dieses Plugin ermöglicht das Erstellen von Statistiken aus den Profildaten
 * der angemeldeten Benutzer. Konfigurierte Statistiken können gespeichert,
 * geladen und angezeigt werden.
 *
 *
 * @copyright 2004-2020 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 *****************************************************************************/

require_once(__DIR__. '/includes.php');
require_once(__DIR__. '/utils/db_constants.php');
require_once(__DIR__. '/install/install_functions.php');

define('LINK_TEXT_INSTALLATION','Installation / Deinstallation');
define('LINK_TEXT_OVERWIEW','Statistiken');
define('LINK_TEXT_CONFIG', 'Statistikeditor');

$showOverview = false;

if(!isset($plgAllowShow))
{
    $plgAllowShow = array('Administrator');
}

if(!isset($plgAllowConfig))
{
    $plgAllowConfig = array('Administrator');
}

foreach ($plgAllowShow AS $i)
{
    if($i == 'Benutzer'
        && $gValidLogin == true)
    {
        $showOverview = true;
    }
    elseif($i == 'Rollenverwalter'
        && $gCurrentUser->assignRoles())
    {
        $showOverview = true;
    }
    elseif($i == 'Listenberechtigte'
        && $gCurrentUser->viewAllLists())
    {
        $hasAccess = true;
    }
    elseif(hasRole($i))
    {
        $showOverview = true;
    }
}

$showConfig = false;

foreach ($plgAllowConfig AS $i)
{
    if($i == 'Benutzer'
        && $gValidLogin == true)
    {
        $showPlugin = true;
    }
    elseif($i == 'Rollenverwalter'
        && $gCurrentUser->assignRoles())
    {
        $showConfig = true;
    }
    elseif($i == 'Listenberechtigte'
        && $gCurrentUser->viewAllLists())
    {
        $hasAccess = true;
    }
    elseif(hasRole($i))
    {
        $showConfig = true;
    }
}

$showInstall = false;
if($gCurrentUser->isAdministrator())
{
    $showInstall = true;
}

if ($showOverview || $showConfig || $showInstall) {
     $sql = 'SELECT men_id FROM ' . TBL_MENU . '
              WHERE men_name_intern IN (\'statistics\', \'statistics_editor\')';
     $statement = $gDb->query($sql);

     if($statement->rowCount() === 0)
     {
        if(statCheckPreviousInstallations())
        {
            statAddMenu();
        }
        else
        {
            echo 'Please install plugin statistics first!';
        }
    }
}
?>
