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

require(__DIR__. '/includes.php');
require(__DIR__. '/config.php');

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
    // Create menu
    $statisticsMenu = new Menu('Statistics', 'Statistiken');

    if ($showOverview) {
        $statisticsMenu->addItem('overview', ADMIDIO_URL. '/adm_plugins/' . $plugin_folder_name . '/gui/overview.php', LINK_TEXT_OVERWIEW, THEME_PATH. '/icons/lists.png" alt="'. LINK_TEXT_OVERWIEW . '" title="'. LINK_TEXT_OVERWIEW);
    }
    if ($showConfig) {
        $statisticsMenu->addItem('config', ADMIDIO_URL. '/adm_plugins/' . $plugin_folder_name . '/gui/editor.php', LINK_TEXT_CONFIG, THEME_PATH. '/icons/options.png" alt="'. LINK_TEXT_CONFIG . '" title="'. LINK_TEXT_CONFIG);
    }
    if ($showInstall) {
        $statisticsMenu->addItem('install', ADMIDIO_URL. '/adm_plugins/' . $plugin_folder_name . '/install/install.php', LINK_TEXT_INSTALLATION, THEME_PATH. '/icons/backup.png" alt="'. LINK_TEXT_INSTALLATION . '" title="'. LINK_TEXT_INSTALLATION);
    }
    echo' <div id="plgStatistics" class="admidio-plugin-content">';
    echo $statisticsMenu->show();
    echo' </div>';
}
?>
