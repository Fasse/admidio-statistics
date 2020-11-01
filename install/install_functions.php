<?php
/******************************************************************************
 * Script to include all necessary files and constants
 *
 * @copyright 2004-2020 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 *****************************************************************************/

//Prüfung auf bereits vorhandene Tabellen in der Datenbank
function statCheckPreviousInstallations() {
    global $gDb;

    $sql = 'SELECT * FROM ' . TBL_STATISTICS;
    $pdoStatement = $gDb->query($sql,false);

    if ($pdoStatement !== false && $pdoStatement->rowCount() > 0) {
        return true;
    } else {
        return false;
    }
}

function statAddMenu() {
    global $gDb, $pluginFolder;

    // Menu entries for the statistic plugin
    $sql = 'INSERT INTO '.TBL_MENU.'
                   (men_com_id, men_men_id_parent, men_node, men_order, men_standard, men_name_intern, men_url, men_icon, men_name, men_description)
            VALUES (NULL, 3, 0, 100, 1, \'statistics\', \''.FOLDER_PLUGINS.'/'.$pluginFolder.'/gui/overview.php\', \'fa-list\', \'PLG_STATISTICS_STATISTICS\', \'PLG_STATISTICS_STATISTICS_DESC\')
                 , (NULL, 3, 0, 101, 1, \'statistics_editor\', \''.FOLDER_PLUGINS.'/'.$pluginFolder.'/gui/editor.php\', \'fa-cog\', \'PLG_STATISTICS_STATISTICS_EDITOR\', \'PLG_STATISTICS_STATISTICS_EDITOR_DESC\')';
    $gDb->query($sql);

}
