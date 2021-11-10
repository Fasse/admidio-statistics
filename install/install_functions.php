<?php
/******************************************************************************
 * Script to include all necessary files and constants
 *
 * @copyright 2004-2021 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 *****************************************************************************/

use Ramsey\Uuid\Uuid;

// Check for existing tables in the database
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

// add menu entries for the statistic plugin
function statAddMenu() {
    global $gDb, $pluginFolder;

    $sql = 'INSERT INTO '.TBL_MENU.'
                   (men_com_id, men_men_id_parent, men_uuid, men_node, men_order, men_standard, men_name_intern, men_url, men_icon, men_name, men_description)
            VALUES (NULL, 3, \'' . Uuid::uuid4() . '\', 0, 100, 1, \'statistics\', \''.FOLDER_PLUGINS.'/'.$pluginFolder.'/gui/overview.php\', \'fa-list\', \'PLG_STATISTICS_STATISTICS\', \'PLG_STATISTICS_STATISTICS_DESC\')
                 , (NULL, 3, \'' . Uuid::uuid4() . '\', 0, 101, 1, \'statistics_editor\', \''.FOLDER_PLUGINS.'/'.$pluginFolder.'/gui/editor.php\', \'fa-cog\', \'PLG_STATISTICS_STATISTICS_EDITOR\', \'PLG_STATISTICS_STATISTICS_EDITOR_DESC\')';
    $gDb->query($sql);

}
