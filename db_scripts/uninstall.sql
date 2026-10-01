/*
 * Remove the database structure of the statistics plugin.
 *
 * The tables go child first, so the foreign keys need no cascade. The menu entry is not deleted
 * here: it belongs to the component of the plugin, and Admidio removes it together with the
 * plugin - see PluginInstaller::removeMenuEntries().
 *
 * @copyright 2004-2025 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */

DROP TABLE IF EXISTS %PREFIX%_statistics_rows;

DROP TABLE IF EXISTS %PREFIX%_statistics_columns;

DROP TABLE IF EXISTS %PREFIX%_statistics_tables;

DROP TABLE IF EXISTS %PREFIX%_statistics;
