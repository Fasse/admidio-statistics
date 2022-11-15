<?php
/******************************************************************************
 * Konfigurationsseite zum Erstellen eigener Statistiken
 *
 * @copyright 2004-2021 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 * sta_id       :   (optional) Statistik deren Konfiguration direkt angezeigt werden soll,
 *                  falls nicht angegeben, wird eine neue leere statistik angezeigt.
 *              1 : temporäreDefinition
 * show_as      :   (optional) obwohl die Konfiguration aus der via 'sta_id' ausgelesenen Daten geladen wird,
 *                  wird diese Konfigruation als andere Konfiguration angezeigt und identifiziert.
 *                  Dies ermöglicht eine temporär erzeugte Konfiguration in eine andere bestehende Konfiguration zu speichern.
 *
 *****************************************************************************/

//Import benötigter Skripts
require_once('../includes.php');
require_once(STATISTICS_PATH.'/utils/db_access.php');
require_once(STATISTICS_PATH.'/utils/form_elements.php');
require_once(STATISTICS_PATH.'/statistic_objects/statistic.php');

//DB-Hilfsklasse instanzieren
$staDBHandler = new DBAccess();

//Überprüfen, ob das Plugin installiert ist
$pluginInstalled = $staDBHandler->getPluginInstalled();

// check if the current user has the right to view the statistics
$sql = 'SELECT men_id FROM ' . TBL_MENU . ' WHERE men_name_intern = \'statistics_editor\' ';
$statement = $gDb->query($sql);
$row = $statement->fetch();

// Read current roles rights of the menu entry
$displayMenu = new RolesRights($gDb, 'menu_view', $row['men_id']);
$rolesDisplayRight = $displayMenu->getRolesIds();

// check for right to show the menu
if (count($rolesDisplayRight) > 0 && !$displayMenu->hasRight($gCurrentUser->getRoleMemberships()))
{
    $hasAccess = false;
}
else
{
    $hasAccess = true;
}

// Html-Kopf wird geschrieben
$page = new HtmlPage('admidio-plugin-statistics-editor', $gL10n->get('PLG_STATISTICS_CONFIGURE_STATISTIC'));

if ($pluginInstalled) {
    if($hasAccess) {
        global $gCurrentOrganization;

        //Erzeugen von Skriptvariablen
        $getStatisticID        = 1;

        function generateProfileFieldSelectBox($zeroValue = false, $grouping = true, $basicData = true,
        		$roleInformation = true, $defaultEntry = -1, $fieldId = 'admSelectBox', $optAttributes = ''){
        	global $gProfileFields;
        	global $gL10n;
			global $gCurrentUser;
			global $page;

        	$user_fields = array();

        	$i = 1;
        	$oldCategoryNameIntern = '';
        	$posEndOfMasterData = 0;

        	foreach($gProfileFields->getProfileFields() as $field){
        		// at the end of category basic data save positions for loginname and username
        		// they will be added after profile fields loop
        		if($basicData && $oldCategoryNameIntern == 'BASIC_DATA'
        				&& $field->getValue('cat_name_intern') != 'BASIC_DATA')
        		{
        			$posEndOfMasterData = $i;
        			$i = $i + 2;
        		}

        		// add profile field to user field array
        		if($field->getValue('usf_hidden') == 0 || $gCurrentUser->editUsers())
        		{

        			//$user_fields[$i] = new Object();
        			$user_fields[$i]['cat_id']   =  $field->getValue('cat_id');
        			$user_fields[$i]['cat_name'] =  $field->getValue('cat_name');
        			$user_fields[$i]['usf_id']   = $field->getValue('usf_id');
        			$user_fields[$i]['usf_name'] = addslashes($field->getValue('usf_name'));
        			$user_fields[$i]['usf_name_intern'] = addslashes($field->getValue('usf_name_intern'));

        			$oldCategoryNameIntern = $field->getValue('cat_name_intern');
        			$i++;
        		}
        	}

        	// Add loginname and photo at the end of category basic data
        	// add new category with start and end date of role membership
        	if ($basicData){

	        	if($posEndOfMasterData == 0)
	        	{
	        		$posEndOfMasterData = $i;
	        		$i = $i + 2;
	        	}


	        	//$user_fields[$posEndOfMasterData] = new Object();
	        	$user_fields[$posEndOfMasterData]['cat_id']   = $user_fields[1]['cat_id'];
	        	$user_fields[$posEndOfMasterData]['cat_name'] = $user_fields[1]['cat_name'];
	        	$user_fields[$posEndOfMasterData]['usf_id']   = 'usr_login_name';
	        	$user_fields[$posEndOfMasterData]['usf_name'] = $gL10n->get('SYS_USERNAME');
	        	$user_fields[$posEndOfMasterData]['usf_name_intern'] = $gL10n->get('SYS_USERNAME');

	        	//$user_fields[$posEndOfMasterData+1] = new Object();
	        	$user_fields[$posEndOfMasterData+1]['cat_id']   = $user_fields[1]['cat_id'];
	        	$user_fields[$posEndOfMasterData+1]['cat_name'] = $user_fields[1]['cat_name'];
	        	$user_fields[$posEndOfMasterData+1]['usf_id']   = 'usr_photo';
	        	$user_fields[$posEndOfMasterData+1]['usf_name'] = $gL10n->get('SYS_PHOTO');
	        	$user_fields[$posEndOfMasterData+1]['usf_name_intern'] = $gL10n->get('SYS_PHOTO');
        	}

	        if ($roleInformation){
	        	//$user_fields[$i] = new Object();
	        	$user_fields[$i]['cat_id']   = -1;
	        	$user_fields[$i]['cat_name'] = $gL10n->get('SYS_ROLE_INFORMATION');
	        	$user_fields[$i]['usf_id']   = 'mem_begin';
	        	$user_fields[$i]['usf_name'] = $gL10n->get('SYS_MEMBERSHIP_START');
	        	$user_fields[$i]['usf_name_intern'] = $gL10n->get('SYS_MEMBERSHIP_START');

	        	$i++;

	        	//$user_fields[$i] = new Object();
	        	$user_fields[$i]['cat_id']   = -1;
	        	$user_fields[$i]['cat_name'] = $gL10n->get('SYS_ROLE_INFORMATION');
	        	$user_fields[$i]['usf_id']   = 'mem_end';
	        	$user_fields[$i]['usf_name'] = $gL10n->get('SYS_MEMBERSHIP_END');
	        	$user_fields[$i]['usf_name_intern'] = $gL10n->get('SYS_MEMBERSHIP_END');
        	}

        	//generateHtml
        	$category = '';

        	$selectBoxHtml = '<select class="form-control" size="1" id="'.$fieldId.'" name="'.$fieldId.'" '.$optAttributes.'>';

        	if ($zeroValue){
        		$zeroSelected = '';
        		if($defaultEntry == 0){
        			$zeroSelected = ' selected="selected" ';
        		}
        		$selectBoxHtml .='<option value="0"'.$zeroSelected.'>'.$zeroValue.'</option>';
        	}

        	for ($counter = 1; $counter <= count($user_fields); $counter++)
        	{
        		if($grouping && $category != $user_fields[$counter]['cat_name'] )
        		{
        			if($category != '')
        			{
        				$selectBoxHtml .= '</optgroup>';
        			}
        			$selectBoxHtml .= '<optgroup label="' . $user_fields[$counter]['cat_name'] . '">';
        			$category = $user_fields[$counter]['cat_name'];
        		}

        		$selected = '';
        		if($user_fields[$counter]['usf_id'] == $defaultEntry){
        			$selected = ' selected="selected" ';
        		}
        		// bei einer neuen Liste sind Vorname und Nachname in den ersten Spalten vorbelegt
        		/*             	if((  ($fieldNumberIntern == 0 && $user_fields[$counter]["usf_name_intern"] == "LAST_NAME")
        		 || ($fieldNumberIntern == 1 && $user_fields[$counter]["usf_name_intern"] == "FIRST_NAME"))
        				&& listId == 0)
        		{

        		}

        		// bei gespeicherten Listen das entsprechende Profilfeld selektieren
        		if($default_fields[$fieldNumberIntern])
        		{
        		if($user_fields[$counter]["usf_id"] == $default_fields[$fieldNumberIntern]["usf_id"])
        		{
        		$selected = " selected=\"selected\" ";
        		}
        		} */
        		$selectBoxHtml .= '<option value="' . $user_fields[$counter]['usf_id'] . '"' . $selected . '>' . $user_fields[$counter]['usf_name'] . '</option>';
        	}
        	if ($grouping){
        		$selectBoxHtml .= '</optgroup>';
        	}
        	$selectBoxHtml .= '</select>';

        	$page->addHtml($selectBoxHtml);
        }



/*         $actualProfileFieldsWithSelect [0] = 'Auswahl';
        $actualProfileFieldsWithNo [0] = 'Alle';
        //Aktuell verfügbare Profilfelder auslesen.
        foreach($gProfileFields->getProfileFields() as $field)
        {
            //$actualProfileFields[$field->getValue('usf_id')] = $field->getValue('usf_name');
            $actualProfileFieldsWithSelect[$field->getValue('usf_id')] = $field->getValue('usf_name');
            $actualProfileFieldsWithNo[$field->getValue('usf_id')] = $field->getValue('usf_name');
        } */
        $actualFunctions = array('#'=>'#','%'=>'%','min'=>'min','max'=>'max','avg'=>'avg','sum'=>'sum');
        $actualFunctionsTotal = array(''=> $gL10n->get('PLG_STATISTICS_NONE'),'min'=>'min','max'=>'max','avg'=>'avg','sum'=>'sum');


        //IDs und Namen vorhandener Statistik-Konfigurationen aus der DB holen
        $allStatisticConfigIDs = $staDBHandler->getStatisticIDs($gCurrentOrganization->getValue('org_id'));
        $allStatisticConfigurations[1] = $gL10n->get('SYS_CREATE_NEW_CONFIGURATION');
        foreach ($allStatisticConfigIDs as $statisticID){
            $allStatisticConfigurations[$statisticID] = $staDBHandler->getStatisticName($statisticID);
        }

        //Auslesen der Statistik-Konfiguration anhand des Übergabeparameter (falls gesetzt);

        $getScrollPos = admFuncVariableIsValid($_GET, 'scroll_pos', 'numeric', array('defaultValue' => 0));

        if (isset($_GET['sta_id'])){ //falls Statistik-ID gesetzt, vorhandene Konfiguration auslesen
            $getStatisticID = admFuncVariableIsValid($_GET, 'sta_id', 'numeric');
        }else{
            header('Location: editor_process.php?mode=load&scroll_pos='.$getScrollPos);
        }

        //Es wird immer die temporäre Statistik-Konfiguration aus der DB geholt.
        $statistic = $staDBHandler->getStatistic(1);

        //Arrays für die Beschriftung des Eingabe-Dialoges und die Bezeichnungen der Felder erzeugen.
        $formColumnLabels = array($gL10n->get('PLG_STATISTICS_COLUMNS'),$gL10n->get('SYS_DESIGNATION'),$gL10n->get('PLG_STATISTICS_SELECTION'),$gL10n->get('SYS_CONDITION'),$gL10n->get('PLG_STATISTICS_EVALUATE'),$gL10n->get('PLG_STATISTICS_FUNCTION'),$gL10n->get('PLG_STATISTICS_SUM_FUNCTION'),'');
        $formColumnInputNames = array('first','label','profile_field','condition','func_arg','func_main','func_total','last');
        $formColumnInputSelectValues = array(/* 'profile_field'=>$actualProfileFieldsWithNo, *//* 'func_arg'=>$actualProfileFieldsWithSelect, */'func_main'=>$actualFunctions,'func_total'=>$actualFunctionsTotal);
        $formRowLabels = array($gL10n->get('PLG_STATISTICS_ROWS'),$gL10n->get('SYS_DESIGNATION'),$gL10n->get('PLG_STATISTICS_SELECTION'),$gL10n->get('SYS_CONDITION'),'');
        $formRowInputNames = array('first','label','profile_field','condition','last');
        /* $formRowInputSelectValues = array('profile_field'=>$actualProfileFieldsWithNo); */


        //Funktionen für den allgemeinen Gebrauch in diesem Skript


        /*function mockGetStatisticName($ID){
            $mockDB             = array('0'=>'eine neue Konfiguration erstellen','12'=>'Altersstatistik','56'=>'Wohnortstatistik');
            return $mockDB[$ID];
        }*/

        function generateClassSuffix($actualElement, $nrOfElements){
            $classSuffix = '';

            if ($actualElement == 0){
                $classSuffix = '_first';
            }elseif($actualElement == 1){
                $classSuffix = '_second';
            }elseif($actualElement == $nrOfElements-1){
                $classSuffix = '_last';
            }

            return $classSuffix;
        }

        function removeScrollPosFromURL($currentURL){
            $pattern = '/&scroll_pos=\d*/';
            $replacement = '';
            $subject = $currentURL;
            return preg_replace($pattern,$replacement,$subject);
        }

        // Url fuer die Zuruecknavigation merken
        $gNavigation->addUrl(removeScrollPosFromURL(CURRENT_URL));

        function generateStatisticConfigSelectBox($entryArray, $defaultEntry = '', $fieldId = 'admSelectBox', $optAttributes = '', $createFirstEntry = false)
        {
            global $gL10n;

            $selectBoxHtml = '<select class="form-control" size="1" id="'.$fieldId.'" name="'.$fieldId.'" '.$optAttributes.'>';
            if($createFirstEntry)
            {
                $selectBoxHtml .= '<option value=" "';
                if(strlen($defaultEntry) == 0){
                    $selectBoxHtml .= ' selected="selected" ';
                }
                $selectBoxHtml .= '>- '.$gL10n->get('SYS_PLEASE_CHOOSE').' -</option>';
            }

            $value = reset($entryArray);
            for($arrayCount = 0; $arrayCount < count($entryArray); $arrayCount++)
            {
                // create entry in html
                $selectBoxHtml .= '<option value="'.key($entryArray).'"';
                if(key($entryArray) == $defaultEntry)
                {
                    $selectBoxHtml .= ' selected="selected" ';
                }
                $selectBoxHtml .= '>'.$value.'</option>';
                $value = next($entryArray);
            }
            $selectBoxHtml .= '</select>';
            return $selectBoxHtml;
        }

        $stdFrameStyle = 'border:1px solid #7d7d7d; margin: 10px 0px;padding: 10px; overflow: auto;';

        $page->addHeader('
			<link rel="stylesheet" type="text/css" href="../stylesheets/editor-stylesheet.css">
            <script src="../utils/editor-scripts.js" type="text/javascript"></script>');

        $doOnLoad = 'checkAllSelectBoxes(); adaptStdStatisticRoleSelectBox();';
        if ($getScrollPos != 0){
            $doOnLoad .= ' scrollTo(0,'.$getScrollPos.');';
        }

        /*******************************/
        $gLayout['onload'] = $doOnLoad;
        /********************************/

        $tables = $statistic->getTables();
        $nrOfTables = count($tables);

        $page->addHtml('
         <div class="formHead">'.$gL10n->get('PLG_STATISTICS_STATISTICS_EDITOR').'</div>
        <form id="form_sta_config" name="form_sta_config" action="editor_process.php" method="post">

            <div class= "stdDiv" id="div_config_selection">

                <p>'.$gL10n->get('PLG_STATISTICS_EDITOR_CONFIG_LOAD_OR_CHANGE').'</p>'
                .generateStatisticConfigSelectBox($allStatisticConfigurations,$getStatisticID,'statistic_conf_select','onchange="loadConf()"').'
                <a class="admidio-icon-link" href="javascript: doFormSubmit(\'save\')"><i class="fas fa-save" data-toggle="tooltip" title="'.$gL10n->get('SYS_SAVE_CONFIGURATION').'"></i></a>
                <a class="admidio-icon-link" href="javascript: doFormSubmit(\'saveas\')"><i class="fas fa-clone" data-toggle="tooltip" title="'.$gL10n->get('SYS_COPY_VAR', array($gL10n->get('SYS_CONFIGURATION'))).'"></i></a>
                <a class="admidio-icon-link" href="javascript: loadConf(true)"><i class="fas fa-plus-circle" data-toggle="tooltip" title="'.$gL10n->get('SYS_CREATE_NEW_CONFIGURATION').'"></i></a>
                <a class="admidio-icon-link" href="javascript: loadConf()"><i class="fas fa-undo" data-toggle="tooltip" title="'.$gL10n->get('PLG_STATISTICS_UNDO_ALL_CHANGES_OF_CONFIGURATION').'"></i></a>
                <a class="admidio-icon-link" href="javascript: deleteConfiguration()"><i class="fas fa-trash-alt" data-toggle="tooltip" title="'.$gL10n->get('SYS_DELETE_CONFIGURATION').'"></i></a>
                <a class="admidio-icon-link" href="../resources/Benutzerhandbuch.pdf" target="_blank"><i class="fas fa-info-circle admidio-info-icon" data-toggle="tooltip" title="'.$gL10n->get('PLG_STATISTICS_OPEN_MANUAL_GERMAN').'"></i></a>

                <h3>'.$gL10n->get('PLG_STATISTICS_GENERAL_INFORMATIONS').'</h3>
                <div class ="InputLabelBox form-group">
                    <span class="textLabel control-label">'.$gL10n->get('PLG_STATISTICS_STATISTICS_TITLE').'</span>
                    <input class ="textInput form-control" type="text" name="statistic_title" id="statistic_title" value="'.$statistic->getTitle().'">
                </div>
                <div class ="InputLabelBox form-group">
                    <span class="textLabel control-label">'.$gL10n->get('PLG_STATISTICS_STATISTICS_SUBTITLE').'</span>
                    <input class ="textInput form-control" type="text" name="statistic_subtitle" id="statistic_subtitle" value="'.$statistic->getSubtitle().'">
                </div>
                <div class ="InputLabelBox form-group">
                    <span class="textLabel control-label">'.$gL10n->get('PLG_STATISTICS_STATISTICS_STANDARD_ROLE').'</span>
                    <a class="admidio-icon-link openPopup" href="javascript:void(0);" data-href="help.php?help_id=533">
                        <i class="fas fa-info-circle admidio-info-icon" data-toggle="tooltip" title="'.$gL10n->get('PLG_STATISTICS_SHOW_HELP_ON_THIS_TOPIC').'"></i>
                    </a>'
                    .FormElements::generateRoleSelectBox($statistic->getStandardRoleID(),'statistic_std_role').'
                    <input class ="textInput" type="hidden" name="nr_of_tables" id="nr_of_tables" value="'.$nrOfTables.'">
                </div>
                <br />
            </div>
        ');

        //Schleife für die Erzegung der Eingabemaske für die Tabellen-Konfigurationen
        for ($tc=0;$tc<$nrOfTables;$tc++) {
            $columns = $tables[$tc]->getColumns();
            $rows = $tables[$tc]->getRows();
            $effectiveNrOfColumns = count($columns);
            $effectiveNrOfRows = count($rows);
            $nrOfColumns = $effectiveNrOfColumns+1;
            $nrOfRows =$effectiveNrOfRows+3;
            $page->addHtml('
            <div class= "stdDiv" id="div_table'.$tc.'_config">
                <h3>'.$gL10n->get('PLG_STATISTICS_XY_TABLE', array(($tc+1).'.')).'</h3>
                <div class ="InputLabelBox form-group">
                    <span class="textLabel control-label">'.$gL10n->get('PLG_STATISTICS_TABLE_TITLE').'</span>
                    <input class ="textInput form-control" type="text" name="table'.$tc.'_title" id="table'.$tc.'_title" value="'.$tables[$tc]->getTitle().'">
                </div>
                <div class ="InputLabelBox form-group">
                    <span class="textLabel control-label">'.$gL10n->get('PLG_STATISTICS_TABLE_ROLE').'</span>
                    <a class="admidio-icon-link openPopup" href="javascript:void(0);" data-href="help.php?help_id=542">
                        <i class="fas fa-info-circle admidio-info-icon" data-toggle="tooltip" title="'.$gL10n->get('PLG_STATISTICS_SHOW_HELP_ON_THIS_TOPIC').'"></i>
                    </a>'
                    .FormElements::generateRoleSelectBox($tables[$tc]->getRoleID(),'table'.$tc.'_role" class="roleInput').'
                </div>
                <br />
                <input type="hidden" name="table'.$tc.'_nr_of_columns" id="table'.$tc.'_nr_of_columns" value="'.$effectiveNrOfColumns.'">
                <input type="hidden" name="table'.$tc.'_nr_of_rows" id="table'.$tc.'_nr_of_rows" value="'.$effectiveNrOfRows.'">
                <div class= "stdDiv" id="div_table'.$tc.'_column_config">
                <br />
                    <table class="col_conf_tbl">
            ');

            //Schleife für die Erzegung der Eingabe-Tabelle der Spalten-Konfigurationen
            for ($frmRow=0;$frmRow<8;$frmRow++) {
                if ($frmRow == 0){
                    $page->addHtml('<thead>');
                } elseif ($frmRow == 1){
                    $page->addHtml('<tbody>');
                }

                $page->addHtml('<tr class="col_conf_tbl_row_'.$formColumnInputNames[$frmRow].'">');
                for ($cc=0;$cc<$nrOfColumns;$cc++) {

                    $classSuffix = generateClassSuffix($cc,$nrOfColumns);
                    $page->addHtml('<td class="col_conf_tbl_col_'.$formColumnInputNames[$frmRow].$classSuffix.'">');
                    if ($cc == 0){
                        if ($frmRow != 7){
                            $page->addHtml($formColumnLabels[$frmRow]);
                        }

                        if ($frmRow > 0 && $frmRow < 7){
                            $page->addHtml('
                            <a class="admidio-icon-link openPopup" href="javascript:void(0);" data-href="help.php?help_id=55'.$frmRow.'">
                                <i class="fas fa-info-circle admidio-info-icon" data-toggle="tooltip" title="'.$gL10n->get('PLG_STATISTICS_SHOW_HELP_ON_THIS_TOPIC').'"></i>
                            </a>');
                        }

                    }else{
                    	$colIdf = $cc-1;
                        if ($frmRow == 0){
                            $page->addHtml($gL10n->get('PLG_STATISTICS_XY_COLUMN', array(($cc).'.')));
                        }elseif ($frmRow == 1){
                            $page->addHtml('<input name="table'.$tc.'_column'.$colIdf.'_'.$formColumnInputNames[$frmRow].'" id="table'.$tc.'_column'.$colIdf.'_'.$formColumnInputNames[$frmRow].'" type="text" value="'.$columns[$colIdf]->getLabel().'">');
                        }elseif ($frmRow == 2){
                        	generateProfileFieldSelectBox($gL10n->get('SYS_ALL'),true,false,false,$columns[$colIdf]->getCondition()->getProfileFieldID(),'table'.$tc.'_column'.$colIdf.'_'.$formColumnInputNames[$frmRow],'onchange=" disableConditionInput(this)"');
                        }elseif ($frmRow == 3){
                            $page->addHtml('<input name="table'.$tc.'_column'.$colIdf.'_'.$formColumnInputNames[$frmRow].'" id="table'.$tc.'_column'.$colIdf.'_'.$formColumnInputNames[$frmRow].'" type="text" value="'.$columns[$colIdf]->getCondition()->getUserCondition().'">');
                        }elseif ($frmRow == 4){
                        	generateProfileFieldSelectBox($gL10n->get('PLG_STATISTICS_SELECTION'),true,false,false,$columns[$colIdf]->getFunction()->getArgument(),'table'.$tc.'_column'.$colIdf.'_'.$formColumnInputNames[$frmRow],'onchange="disableInvalidFunctions(this)"');
                        }elseif ($frmRow == 5){
                            $page->addHtml(FormElements::generateDynamicSelectBox($formColumnInputSelectValues[$formColumnInputNames[$frmRow]],$columns[$colIdf]->getFunction()->getName(),'table'.$tc.'_column'.$colIdf.'_'.$formColumnInputNames[$frmRow]));
                        }elseif ($frmRow == 6){
                            $page->addHtml(FormElements::generateDynamicSelectBox($formColumnInputSelectValues[$formColumnInputNames[$frmRow]],$columns[$colIdf]->getFunctionTotal(),'table'.$tc.'_column'.$colIdf.'_'.$formColumnInputNames[$frmRow]));
                        }elseif ($frmRow == 7){

                        	if ($cc > 1){
                        		$page->addHtml('<a class="admidio-icon-link" href="javascript: editStructure(\'mvcol\',\''.$tc.'\',\''.$colIdf.'\',\'\',\'true\')"><i class="fas fa-chevron-circle-left" data-toggle="tooltip" title="'.$gL10n->get('PLG_STATISTICS_MOVE_COLUMN_BACKWARDS').'"></i></a>');
                        	}
                        	if ($cc < $effectiveNrOfColumns){
                        		$page->addHtml('<a class="admidio-icon-link" href="javascript: editStructure(\'mvcol\',\''.$tc.'\',\''.$colIdf.'\')"><i class="fas fa-chevron-circle-right" data-toggle="tooltip" title="'.$gL10n->get('PLG_STATISTICS_MOVE_COLUMN_FORWARD').'"></i></a>');
                        	}
                        	$page->addHtml('<a class="admidio-icon-link" href="javascript: editStructure(\'duplcol\',\''.$tc.'\',\''.$colIdf.'\')"><i class="fas fa-clone" data-toggle="tooltip" title="'.$gL10n->get('PLG_STATISTICS_DUPLICATE_COLUMN').'"></i></a>');
                        	if ($effectiveNrOfColumns > 1){
                        		$page->addHtml('<a class="admidio-icon-link" href="javascript: editStructure(\'delcol\',\''.$tc.'\',\''.$colIdf.'\')"><i class="fas fa-trash-alt" data-toggle="tooltip" title="'.$gL10n->get('PLG_STATISTICS_DELETE_COLUMN').'"></i></a>');
                        	}
                            if ($cc == $effectiveNrOfColumns){
                            	$page->addHtml('<a class="admidio-icon-link" href="javascript: editStructure(\'addcol\',\''.$tc.'\')"><i class="fas fa-plus-circle" data-toggle="tooltip" title="'.$gL10n->get('PLG_STATISTICS_ADD_COLUMN').'"></i></a>');
                            }
                        }else{
                            $page->addHtml('<input class="form-control" name="table'.$tc.'_column'.$colIdf.'_'.$formColumnInputNames[$frmRow].'" id="table'.$tc.'_column'.$colIdf.'_'.$formColumnInputNames[$frmRow].'" type="text">');
                        }
                    }
                    $page->addHtml('</td>');
                }
                $page->addHtml('</tr>');

                if ($frmRow == 0){
                    $page->addHtml('</thead>');
                } elseif ($frmRow == 7){
                    $page->addHtml('</tbody>');
                }


            }

            $page->addHtml('
                </table>
            </div>
            <div class= "stdDiv" id="div_table'.$tc.'_row_config">
                <table class="row_conf_tbl">');

            //Schleife für die Erzegung der Eingabe-Tabelle der Zeilen-Konfigurationen
            for ($rc=0;$rc<$nrOfRows;$rc++) {
                $rowIdf = $rc-2;
                $classSuffix = generateClassSuffix($rc,$nrOfRows);
                if ($rc == 0){
                    $page->addHtml('<thead>');
                } elseif ($rc == 1){
                    $page->addHtml('<tbody>');
                }
                $page->addHtml('<tr class="row_conf_tbl_row'.$classSuffix.'">');
                for ($frmCol=0;$frmCol<5;$frmCol++) {
                    $page->addHtml('<td class="row_conf_tbl_col_'.$formRowInputNames[$frmCol].$classSuffix.'">');
                    if ($rc == 0){
                        $page->addHtml($formRowLabels[$frmCol]);
                        if ($frmCol > 0 && $frmCol < 4){
                            $page->addHtml('
                            <a class="admidio-icon-link openPopup" href="javascript:void(0);" data-href="help.php?help_id=56'.($frmCol+1).'">
                                <i class="fas fa-info-circle admidio-info-icon" data-toggle="tooltip" title="'.$gL10n->get('PLG_STATISTICS_SHOW_HELP_ON_THIS_TOPIC').'"></i>
                            </a>');
                        }
                    }elseif($rc == 1){
                        if ($frmCol == 0){
                            $page->addHtml($gL10n->get('PLG_STATISTICS_HEADER'));
                        }elseif ($frmCol == 1){
                            $page->addHtml('<input class="form-control" name="table'.$tc.'_first_column_label" id="table'.$tc.'_first_column_label" type="text" value="'.$tables[$tc]->getFirstColumnLabel().'">');
                        }
                    }elseif ($rc == $nrOfRows-1){
                        $tst = 1;
                    }else{
                        if ($frmCol == 0){
                            $page->addHtml($gL10n->get('PLG_STATISTICS_XY_ROW', array(($rc-1).'.')));
                            $page->addHtml('<a class="admidio-icon-link" href="javascript: editStructure(\'duplrow\',\''.$tc.'\',\'\',\''.$rowIdf.'\')"><i class="fas fa-clone" data-toggle="tooltip" title="'.$gL10n->get('PLG_STATISTICS_DUPLICATE_ROW').'"></i></a>');
                            if ($rc < $nrOfRows-2){
                            	$page->addHtml('<a class="admidio-icon-link" href="javascript: editStructure(\'mvrow\',\''.$tc.'\',\'\',\''.$rowIdf.'\')"><i class="fas fa-chevron-circle-down" data-toggle="tooltip" title="'.$gL10n->get('PLG_STATISTICS_MOVE_ROW_DOWN').'"></i></a>');
                            }
                            if ($rc > 2){
                            	$page->addHtml('<a class="admidio-icon-link" href="javascript: editStructure(\'mvrow\',\''.$tc.'\',\'\',\''.$rowIdf.'\',\'true\')"><i class="fas fa-chevron-circle-up" data-toggle="tooltip" title="'.$gL10n->get('PLG_STATISTICS_MOVE_ROW_UP').'"></i></a>');
                            }
                            if ($rc == $nrOfRows-2){
                            	$page->addHtml('<a class="admidio-icon-link" href="javascript: editStructure(\'addrow\',\''.$tc.'\')"><i class="fas fa-plus-circle" data-toggle="tooltip" title="'.$gL10n->get('PLG_STATISTICS_ADD_ROW').'"></i></a>');
                            }

                        }elseif($frmCol == 1){
                            $page->addHtml('<input class="form-control" name="table'.$tc.'_row'.$rowIdf.'_'.$formRowInputNames[$frmCol].'" id="table'.$tc.'_row'.$rowIdf.'_'.$formRowInputNames[$frmCol].'" type="text" value="'.$rows[$rowIdf]->getLabel().'">');
                        }elseif($frmCol == 2){
                        	generateProfileFieldSelectBox('Alle',true,false,false,$rows[$rowIdf]->getCondition()->getProfileFieldID(),'table'.$tc.'_row'.$rowIdf.'_'.$formRowInputNames[$frmCol],'onchange="disableConditionInput(this)"');
                        }elseif($frmCol == 3){
                            $page->addHtml('<input class="form-control" name="table'.$tc.'_row'.$rowIdf.'_'.$formRowInputNames[$frmCol].'" id="table'.$tc.'_row'.$rowIdf.'_'.$formRowInputNames[$frmCol].'" type="text" value="'.$rows[$rowIdf]->getCondition()->getUserCondition().'">');
                        }elseif($frmCol == 4){
                        	if ($effectiveNrOfRows > 1){
                        		$page->addHtml('<a class="admidio-icon-link" href="javascript: editStructure(\'delrow\',\''.$tc.'\',\'\',\''.$rowIdf.'\')"><i class="fas fa-trash-alt" data-toggle="tooltip" title="'.$gL10n->get('PLG_STATISTICS_DELETE_ROW').'"></i></a>');
                        	}
                        }
                    }
                    $page->addHtml('</td>');
                }
                $page->addHtml('</tr>');

                if ($rc == 0){
                    $page->addHtml('</thead>');
                } elseif ($rc == $nrOfRows-1){
                    $page->addHtml('</tbody>');
                }

            }
            $page->addHtml('</table>');
            $page->addHtml('</div>');

            if ($tc == $nrOfTables-1){
            	$page->addHtml('<a class="admidio-icon-link" href="javascript: editStructure(\'addtable\')"><i class="fas fa-plus-circle" data-toggle="tooltip" title="'.$gL10n->get('PLG_STATISTICS_ADD_TABLE').'"></i></a>');
            }
            if ($tc > 0){
            	$page->addHtml('<a class="admidio-icon-link" href="javascript: editStructure(\'mvtable\',\''.$tc.'\',\'\',\'\',\'true\')"><i class="fas fa-chevron-circle-up" data-toggle="tooltip" title="'.$gL10n->get('PLG_STATISTICS_MOVE_TABLE_UP').'"></i></a>');
            }
            if ($tc < $nrOfTables-1){
            	$page->addHtml('<a class="admidio-icon-link" href="javascript: editStructure(\'mvtable\',\''.$tc.'\')"><i class="fas fa-chevron-circle-down" data-toggle="tooltip" title="'.$gL10n->get('PLG_STATISTICS_MOVE_TABLE_DOWN').'"></i></a>');
            }
            $page->addHtml('<a class="admidio-icon-link" href="javascript: editStructure(\'dupltable\',\''.$tc.'\')"><i class="fas fa-clone" data-toggle="tooltip" title="'.$gL10n->get('PLG_STATISTICS_DUPLICATE_TABLE').'"></i></a>');
            if ($nrOfTables >1){
            	$page->addHtml('<a class="admidio-icon-link" href="javascript: editStructure(\'deltable\',\''.$tc.'\')"><i class="fas fa-trash-alt" data-toggle="tooltip" title="'.$gL10n->get('PLG_STATISTICS_DELETE_TABLE').'"></i></a>');
            }

            $page->addHtml('</div>');
        }

        $page->addHtml('<br /><input class="btn btn-primary admidio-margin-bottom" type="button" name="show_statistic" value="Statistik anzeigen" onclick="doFormSubmit(\'show\')" />');
        $page->addHtml('<a class="admidio-icon-link align-top openPopup" href="javascript:void(0);" data-href="help.php?help_id=427"><i class="fas fa-info-circle admidio-info-icon" data-toggle="tooltip" title="'.$gL10n->get('PLG_STATISTICS_SHOW_HELP_ON_THIS_TOPIC').'"></i></a>');
        $page->addHtml('</form>');
    } else  {

        if ($gValidLogin) {
            $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
            // => EXIT
        } else {
            require_once(ADMIDIO_PATH.'/adm_program/system/login_valid.php');
        }
    }
} else {

    $page->addHtml('<p>'.$gL10n->get('PLG_STATISTICS_PLUGIN_NOT_FOUND_PLEASE_INSTALL').'</p>');
    $text = $gL10n->get('PLG_STATISTICS_INSTALL');
    $link = '../install/install.php';
    $page->addHtml('<p><form action="'. $link . '" method="post"  >
                            <input class="btn btn-primary" type="submit" name="action" value="' . $text . '" />
                        </form>
                    </p>');
}
$page->show();
// Url fuer die Zuruecknavigation merken, ohne Scrollposition zu entfernen!
$gNavigation->addUrl(CURRENT_URL);
