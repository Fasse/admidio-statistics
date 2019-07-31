<?php
/******************************************************************************
 * Statistiken V 2.3.2 (kompatibel mit Admidio 3.3)
 *
 * Beta Version
 *
 * Dieses Plugin ermöglicht das Erstellen von Statistiken aus den Profildaten
 * der angemeldeten Benutzer. Konfigurierte Statistiken können gespeichert,
 * geladen und angezeigt werden.
 *
 *
 * Copyright    : (c) 2004 - 2015 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 *
 *****************************************************************************/

require_once(__DIR__. '/includes.php');
require_once(__DIR__. '/utils/db_constants.php');
require(__DIR__. '/config.php');
require_once(__DIR__. '/install/install_functions.php');

define('LINK_TEXT_INSTALLATION','Installation / Deinstallation');
define('LINK_TEXT_OVERWIEW','Statistiken');
define('LINK_TEXT_CONFIG', 'Statistikeditor');

$showOverview = false;

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
