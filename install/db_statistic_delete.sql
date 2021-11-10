/******************************************************************************
 * SQL script deletes tables for statistic plugin
 *
 * @copyright 2004-2021 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 ******************************************************************************/


/*==============================================================*/
/* Delete Tables                                   	    */
/*==============================================================*/
drop table if exists %PREFIX%_statistics_rows cascade;
drop table if exists %PREFIX%_statistics_columns cascade;
drop table if exists %PREFIX%_statistics_tables cascade;
drop table if exists %PREFIX%_statistics cascade;
delete from %PREFIX%_menu where men_name_intern IN ('statistics', 'statistics_editor');
