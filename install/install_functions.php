<?php
/******************************************************************************
 * Script to include all necessary files and constants
 *
 * Copyright    : (c) 2004 - 2019 The Admidio Team
 * Homepage     : https://www.admidio.org
 * License      : GNU Public License 2 https://www.gnu.org/licenses/gpl-2.0.html
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
            VALUES (NULL, 3, 0, 100, 1, \'statistics\', \''.FOLDER_PLUGINS.'/'.$pluginFolder.'/gui/overview.php\', \'lists.png\', \'PLG_STATISTICS_STATISTICS\', \'PLG_STATISTICS_STATISTICS_DESC\')
                 , (NULL, 3, 0, 101, 1, \'statistics_editor\', \''.FOLDER_PLUGINS.'/'.$pluginFolder.'/gui/editor.php\', \'options.png\', \'PLG_STATISTICS_STATISTICS_EDITOR\', \'PLG_STATISTICS_STATISTICS_EDITOR_DESC\')';
    $gDb->query($sql);

}
