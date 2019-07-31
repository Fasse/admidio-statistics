/******************************************************************************
 * SQL script deletes tables for statistic plugin
 *
 * Copyright    : (c) 2004 - 2015 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
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
