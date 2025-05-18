<?php
/******************************************************************************
 * Statistiken V 3.6.0 (kompatibel mit Admidio 5.0 and above)
 *
 * Dieses Plugin ermöglicht das Erstellen von Statistiken aus den Profildaten
 * der angemeldeten Benutzer. Konfigurierte Statistiken können gespeichert,
 * geladen und angezeigt werden.
 *
 *
 * @copyright 2004-2025 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 *****************************************************************************/

require_once(__DIR__ . '/includes.php');
require_once(__DIR__ . '/utils/db_constants.php');
require_once(__DIR__ . '/install/install_functions.php');

$showOverview = false;

if (!isset($plgAllowShow)) {
    $plgAllowShow = array('Administrator');
}

if (!isset($plgAllowConfig)) {
    $plgAllowConfig = array('Administrator');
}

foreach ($plgAllowShow as $i) {
    if ($i == 'Benutzer'
        && $gValidLogin) {
        $showOverview = true;
    } elseif ($i == 'Rollenverwalter'
        && $gCurrentUser->isAdministratorRoles()) {
        $showOverview = true;
    } elseif ($i == 'Listenberechtigte'
        && $gCurrentUser->checkRolesRight('rol_all_lists_view')) {
        $showOverview = true;
    } elseif ($i === 'Administrator' && $gCurrentUser->isAdministrator()) {
        $showOverview = true;
    } elseif (hasRole($i)) {
        $showOverview = true;
    }
}

$showConfig = false;

foreach ($plgAllowConfig as $i) {
    if ($i == 'Benutzer'
        && $gValidLogin) {
        $showPlugin = true;
    } elseif ($i == 'Rollenverwalter'
        && $gCurrentUser->isAdministratorRoles()) {
        $showConfig = true;
    } elseif ($i == 'Listenberechtigte'
        && $gCurrentUser->checkRolesRight('rol_all_lists_view')) {
        $showConfig = true;
    } elseif ($i === 'Administrator' && $gCurrentUser->isAdministrator()) {
        $showConfig = true;
    } elseif (hasRole($i)) {
        $showConfig = true;
    }
}

$showInstall = false;
if ($gCurrentUser->isAdministrator()) {
    $showInstall = true;
}

if ($showOverview || $showConfig || $showInstall) {
    $sql = 'SELECT men_id FROM ' . TBL_MENU . '
              WHERE men_name_intern IN (\'statistics\', \'statistics_editor\')';
    $statement = $gDb->query($sql);

    if ($statement->rowCount() === 0) {
        if (statCheckPreviousInstallations()) {
            statAddMenu();
        } else {
            header('Location: ./install/install.php');
            exit();
        }
    } else {
        echo 'Plugin is successfully installed. Please go to the menu of Admidio and select the new added entries of the statistic plugin!';
    }
}
