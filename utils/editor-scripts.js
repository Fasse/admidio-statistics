/*******************************************************************************
 * JavaScript-Functions for the editor.
 *
 * @copyright 2004-2021 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 ******************************************************************************/

function loadConf(newConf) {
	var sta_id = $("#statistic_conf_select").val();
	if (sta_id == 1 || newConf == true) {
		self.location.href = "editor_process.php?mode=load" + saveScrollPos();
	} else {
		self.location.href = "editor_process.php?mode=load&sta_id=" + sta_id
				+ saveScrollPos();
	}
}

function deleteConfiguration() {
	var sta_id = $("#statistic_conf_select").val();
	if (sta_id == 1) {
		alert("Bitte wähle eine Konfiguration aus, die du löschen möchtest.");
	} else {
		var select_box = document.getElementById("statistic_conf_select");
		var current_text = select_box.options[select_box.selectedIndex].text;
		Check = confirm("Willst du die Konfiguration \'" + current_text
				+ "\' wirklich löschen?");
		if (Check == true) {
			self.location.href = "editor_process.php?mode=delete&sta_id="
					+ sta_id + saveScrollPos();
		}
	}
}

function disableConditionInput(idOfElement) {
	var nameOfElement = idOfElement.getAttribute("id");
	var nameOfNewElement = nameOfElement.replace("profile_field", "condition");
	if (idOfElement.value == 0) {
		document.getElementById(nameOfNewElement).disabled = true;
	} else {
		document.getElementById(nameOfNewElement).disabled = false;
	}
}

function checkAllSelectBoxes() {
	var nrOfSelectBoxes = document.getElementsByTagName("select").length;
	for ( var i = 0; i < nrOfSelectBoxes; i++) {
		var tmpSelectBox = document.getElementsByTagName("select")[i];
		var tmpSelectBoxId = tmpSelectBox.getAttribute("id");
		var testString = /(func_arg)/g;
		var validTest = testString.test(tmpSelectBoxId);
		if (validTest != false) {
			disableInvalidFunctions(tmpSelectBox);
		} else {
			testString = /(profile_field)/g;
			validTest = testString.test(tmpSelectBoxId);
			if (validTest != false) {
				disableConditionInput(tmpSelectBox);
			}
		}
	}
}

function replaceGTLT() {
	var nrOfInputBoxes = document.getElementsByTagName("input").length;
	for ( var i = 0; i < nrOfInputBoxes; i++) {
		var tmpInputBox = document.getElementsByTagName("input")[i];
		var tmpInputBoxId = tmpInputBox.getAttribute("id");
		var testString = /(condition)/g;
		var validTest = testString.test(tmpInputBoxId);
		if (validTest != false) {
			tmpInputBox.value = tmpInputBox.value.replace("<", "{");
			tmpInputBox.value = tmpInputBox.value.replace(">", "}");
		}
	}

}

function adaptStdStatisticRoleSelectBox() {
	document.form_sta_config.statistic_std_role.options[0] = null;
}

function disableInvalidFunctions(idOfElement) {
	var nameOfElement = idOfElement.getAttribute("id");
	var nameOfNewElement = nameOfElement.replace("func_arg", "func_main");
	var ElementToChange = document.getElementById(nameOfNewElement);
	if (idOfElement.value == 0) {
		ElementToChange.options[0].disabled = false;
		ElementToChange.options[1].disabled = false;
		ElementToChange.options[2].disabled = true;
		ElementToChange.options[3].disabled = true;
		ElementToChange.options[4].disabled = true;
		ElementToChange.options[5].disabled = true;
		if (ElementToChange.selectedIndex >= 2
				&& ElementToChange.selectedIndex <= 5) {
			ElementToChange.selectedIndex = 0;
		}
	} else {
		ElementToChange.options[0].disabled = true;
		ElementToChange.options[1].disabled = true;
		ElementToChange.options[2].disabled = false;
		ElementToChange.options[3].disabled = false;
		ElementToChange.options[4].disabled = false;
		ElementToChange.options[5].disabled = false;
		if (ElementToChange.selectedIndex >= 0
				&& ElementToChange.selectedIndex <= 1) {
			ElementToChange.selectedIndex = 5;
		}
	}
}

function saveScrollPos() {
	var scroll_pos = window.pageYOffset;
	var return_scroll_pos = "";
	if (scroll_pos != undefined && scroll_pos != 0) {
		return_scroll_pos = "&scroll_pos=" + scroll_pos;
	}
	return return_scroll_pos;
}

function editStructure(action, tableNr, colNr, rowNr, mvUp) {
	var sta_id = $("#statistic_conf_select").val();
	var submitParameters = "?sta_id=" + sta_id
			+ "&mode=editstructure&editaction=" + action;
	if (tableNr != undefined && tableNr != "") {
		submitParameters += "&edittblnr=" + tableNr;
	}
	if (colNr != undefined && colNr != "") {
		submitParameters += "&editcolnr=" + colNr;
	}
	if (rowNr != undefined && rowNr != "") {
		submitParameters += "&editrownr=" + rowNr;
	}
	if (mvUp == "true") {
		submitParameters += "&mvupwards=true";
	}
	submitParameters += saveScrollPos();
	document.form_sta_config.action = document.form_sta_config.action
			+ submitParameters;
	replaceGTLT();
	document.form_sta_config.submit();
}

function editRowOrder(){

}

function doFormSubmit(mode) {
	var doSubmit = false;
	var sta_id = $("#statistic_conf_select").val();
	var default_prompttext = "Neue Konfiguration";
	if (mode == "saveas") {
		mode = "save";
		sta_id = 1;
		var select_box = document.getElementById("statistic_conf_select");
		default_prompttext = select_box.options[select_box.selectedIndex].text
				+ " - Kopie";
	}

	var submitParameters = "?mode=" + mode;

	if (sta_id == 1 && mode == "save") {
        doSubmit = true;
        /*
		name = prompt("Bitte gib einen Namen für die neue Konfiguration an:",
				default_prompttext);
		if (name == undefined || name == null) {
			return;
		} else if (name == "") {
			alert("Du musst einen Namen für die neue Konfiguration angeben!");
		} else {
			submitParameters += "&name=" + name;
			doSubmit = true;
		}*/
	} else {
		submitParameters += "&sta_id=" + sta_id;
		doSubmit = true;
	}

	if (doSubmit) {
		submitParameters += saveScrollPos();
		document.form_sta_config.action = document.form_sta_config.action
				+ submitParameters;
		replaceGTLT();
		document.form_sta_config.submit();
	}
}
