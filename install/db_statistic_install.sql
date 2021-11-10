/******************************************************************************
 * SQL script with database updates for statistic plugin
 *
 * @copyright 2004-2021 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 ******************************************************************************/


/*==============================================================*/
/* Table: statistics                                     	    */
/*==============================================================*/
CREATE TABLE %PREFIX%_statistics
(
	sta_id 	            INTEGER 			unsigned NOT NULL AUTO_INCREMENT,
	sta_org_id 	        INTEGER 			unsigned NOT NULL,
	sta_name 			VARCHAR(50) 		NOT NULL,
	sta_title 			VARCHAR(200),
	sta_subtitle 		VARCHAR(200),
	sta_std_role 		INTEGER 			unsigned NOT NULL,
	PRIMARY KEY (sta_id)
)

engine = InnoDB
auto_increment = 1
default character set = utf8
collate = utf8_unicode_ci;

/*==============================================================*/
/* Table: statistics_tables                                      */
/*==============================================================*/
CREATE TABLE %PREFIX%_statistics_tables
(
	stt_id   				    INTEGER 					unsigned NOT NULL AUTO_INCREMENT,
	stt_title 					VARCHAR(200),
	stt_role 					INTEGER,
	stt_first_column_label 		VARCHAR(50),
	stt_sta_id 			        INTEGER 					unsigned NOT NULL,
	PRIMARY KEY (stt_id)
)

engine = InnoDB
auto_increment = 1
default character set = utf8
collate = utf8_unicode_ci;

/*==============================================================*/
/* Table: statistics_columns                            	        */
/*==============================================================*/
CREATE TABLE %PREFIX%_statistics_columns
(
	stc_id 				        INTEGER 					unsigned NOT NULL AUTO_INCREMENT,
	stc_label 					VARCHAR(50),
	stc_field_condition 		VARCHAR(200),
	stc_profile_field 			VARCHAR(50),
	stc_function_main 			VARCHAR(50),
	stc_function_arg 			VARCHAR(200),
	stc_function_total 			VARCHAR(50),
	stc_stt_id   				INTEGER 					unsigned NOT NULL,
	PRIMARY KEY (stc_id)
)

engine = InnoDB
auto_increment = 1
default character set = utf8
collate = utf8_unicode_ci;

/*==============================================================*/
/* Table: statistics_rows                                      	*/
/*==============================================================*/
CREATE TABLE %PREFIX%_statistics_rows
(
	str_id 					INTEGER 					unsigned NOT NULL AUTO_INCREMENT,
	str_label 				VARCHAR(50),
	str_field_condition 	VARCHAR(200),
	str_profile_field 		VARCHAR(50),
	str_stt_id 				INTEGER 					unsigned NOT NULL,
	PRIMARY KEY (str_id)
)

engine = InnoDB
auto_increment = 1
default character set = utf8
collate = utf8_unicode_ci;

/*==============================================================*/
/* Constraints                                                  */
/*==============================================================*/

ALTER TABLE %PREFIX%_statistics_tables ADD CONSTRAINT %PREFIX%_FK_STT_STA FOREIGN KEY (stt_sta_id)
		REFERENCES %PREFIX%_statistics (sta_id) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE %PREFIX%_statistics_rows ADD CONSTRAINT %PREFIX%_FK_STR_STT FOREIGN KEY (str_stt_id)
		REFERENCES %PREFIX%_statistics_tables (stt_id) ON DELETE CASCADE ON UPDATE CASCADE;
ALTER TABLE %PREFIX%_statistics_columns ADD CONSTRAINT %PREFIX%_FK_STC_STT FOREIGN KEY (stc_stt_id )
		REFERENCES %PREFIX%_statistics_tables (stt_id) ON DELETE CASCADE ON UPDATE CASCADE;;

