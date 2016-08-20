<?php
/******************************************************************************
 * Konstanten für die DB des Statistikaddons
 *
 * Copyright    : (c) 2004 - 2015 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 *****************************************************************************/

define('TBL_STATISTICS', $g_tbl_praefix . '_statistics');
define('TBL_TABLES', $g_tbl_praefix . '_statistics_tables');
define('TBL_COLUMNS',  $g_tbl_praefix . '_statistics_columns');
define('TBL_ROWS',  $g_tbl_praefix . '_statistics_rows');

define('TBL_STA_ID' , 'sta_id');
define('TBL_STA_ORG_ID' , 'sta_org_id');
define('TBL_STA_NAME', 'sta_name');
define('TBL_STA_TITLE', 'sta_title');
define('TBL_STA_SUBTITLE', 'sta_subtitle');
define('TBL_STA_STDROLE', 'sta_std_role');

define('TBL_STT_ID' , 'stt_id');
define('TBL_STT_TITLE', 'stt_title');
define('TBL_STT_ROLE', 'stt_role');
define('TBL_STT_FIRST_COLUMN_LABEL', 'stt_first_column_label');
define('TBL_STT_STA_ID', 'stt_sta_id');

define('TBL_STC_ID' , 'stc_id');
define('TBL_STC_LABEL' ,  'stc_label');
define('TBL_STC_FIELD_CONDITION' , 'stc_field_condition');
define('TBL_STC_PROFILE_FIELD', 'stc_profile_field');
define('TBL_STC_FUNCTION_MAIN', 'stc_function_main');
define('TBL_STC_FUNCTION_ARG', 'stc_function_arg');
define('TBL_STC_FUNCTION_TOTAL', 'stc_function_total');
define('TBL_STC_STT_ID', 'stc_stt_id');

define('TBL_STR_ID' , 'str_id');
define('TBL_STR_LABEL', 'str_label');
define('TBL_STR_FIELD_CONDITION', 'str_field_condition');
define('TBL_STR_PROFILE_FIELD', 'str_profile_field');
define('TBL_STR_STT_ID', 'str_stt_id');
?>