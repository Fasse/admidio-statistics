/*
 * Database structure of the statistics plugin.
 *
 * The table names are the ones the plugin has always used, so a database written by an earlier
 * version is kept as it is. This file therefore has to be idempotent: it runs when the plugin is
 * installed, and that is also the moment an installation still carrying the tables of version 3
 * arrives here.
 *
 * Only PRIMARY KEY and the foreign keys are declared inside the tables. Both are standard SQL and
 * are skipped together with the table, which is what keeps this file repeatable. A secondary index
 * could not be, because neither engine knows ADD CONSTRAINT IF NOT EXISTS and Postgres does not
 * accept the MySQL KEY clause that Admidio does not rewrite.
 *
 * @copyright 2004-2025 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */

CREATE TABLE IF NOT EXISTS %PREFIX%_statistics
(
    sta_id                      integer unsigned    NOT NULL    AUTO_INCREMENT,
    sta_org_id                  integer unsigned    NOT NULL,
    sta_name                    varchar(50)         NOT NULL,
    sta_title                   varchar(200)        NULL,
    sta_subtitle                varchar(200)        NULL,
    sta_std_role                integer unsigned    NOT NULL,
    PRIMARY KEY (sta_id)
)
ENGINE = InnoDB
DEFAULT CHARSET = utf8mb4
COLLATE = utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS %PREFIX%_statistics_tables
(
    stt_id                      integer unsigned    NOT NULL    AUTO_INCREMENT,
    stt_title                   varchar(200)        NULL,
    stt_role                    integer             NULL,
    stt_first_column_label      varchar(50)         NULL,
    stt_sta_id                  integer unsigned    NOT NULL,
    PRIMARY KEY (stt_id),
    CONSTRAINT %PREFIX%_FK_STT_STA FOREIGN KEY (stt_sta_id)
        REFERENCES %PREFIX%_statistics (sta_id) ON DELETE CASCADE ON UPDATE CASCADE
)
ENGINE = InnoDB
DEFAULT CHARSET = utf8mb4
COLLATE = utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS %PREFIX%_statistics_columns
(
    stc_id                      integer unsigned    NOT NULL    AUTO_INCREMENT,
    stc_label                   varchar(50)         NULL,
    stc_field_condition         varchar(200)        NULL,
    stc_profile_field           varchar(50)         NULL,
    stc_function_main           varchar(50)         NULL,
    stc_function_arg            varchar(200)        NULL,
    stc_function_total          varchar(50)         NULL,
    stc_stt_id                  integer unsigned    NOT NULL,
    PRIMARY KEY (stc_id),
    CONSTRAINT %PREFIX%_FK_STC_STT FOREIGN KEY (stc_stt_id)
        REFERENCES %PREFIX%_statistics_tables (stt_id) ON DELETE CASCADE ON UPDATE CASCADE
)
ENGINE = InnoDB
DEFAULT CHARSET = utf8mb4
COLLATE = utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS %PREFIX%_statistics_rows
(
    str_id                      integer unsigned    NOT NULL    AUTO_INCREMENT,
    str_label                   varchar(50)         NULL,
    str_field_condition         varchar(200)        NULL,
    str_profile_field           varchar(50)         NULL,
    str_stt_id                  integer unsigned    NOT NULL,
    PRIMARY KEY (str_id),
    CONSTRAINT %PREFIX%_FK_STR_STT FOREIGN KEY (str_stt_id)
        REFERENCES %PREFIX%_statistics_tables (stt_id) ON DELETE CASCADE ON UPDATE CASCADE
)
ENGINE = InnoDB
DEFAULT CHARSET = utf8mb4
COLLATE = utf8mb4_unicode_ci;

/*
 * Version 3 reserved sta_id = 1 for a scratch row it called "TEMPORARY STATISTIC" and wrote every
 * keystroke of the editor into it. Version 4 keeps the draft in the form and in the request, so the
 * row has no meaning any more. Its children go with it through the foreign keys, and on a new
 * installation this deletes nothing.
 */
DELETE FROM %PREFIX%_statistics WHERE sta_id = 1;

/*
 * The same scratch row received the name of the loaded statistic in its integer sta_org_id column,
 * because version 3 passed the constructor arguments in the wrong order. A statistic that belongs
 * to no organization can never be listed or shown, so whatever is left of that is removed here.
 */
DELETE FROM %PREFIX%_statistics WHERE sta_org_id = 0;

/*
 * Version 3 inserted its two menu entries itself, with no component of their own. Admidio adds the
 * menu entry of a plugin when it is installed and removes it with the plugin, so the two rows left
 * over would be a second, dead copy - and one of them claims the internal name this plugin is
 * about to be given. The men_com_id condition keeps this away from any entry that belongs to a
 * component, including this plugin's own on a re-install.
 */
DELETE FROM %PREFIX%_menu WHERE men_name_intern IN ('statistics', 'statistics_editor') AND men_com_id IS NULL;
