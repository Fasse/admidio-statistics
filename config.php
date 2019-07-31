<?php
/******************************************************************************
 * Konfigurationsdatei fuer das Admidio-Plugin Statistiken
 *
 * Copyright    : (c) 2004 - 2015 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 *
 *****************************************************************************/

/* Freigabe einer Funktion für eine bestimmte Benutzergruppe
'Benutzer'        :     nur registrierte Benutzer
'Rollenverwalter' :     nur Benutzer mit dem Recht "Rollen zu erstellen"
'Listenberechtigte' :   nur Benutzer mit dem Recht "Mitgliederlisten aller Rollen einsehen"
'<Rollenname>'    :     nur Mitglieder dieser Rolle

Beispiel:  $plgFreigabe = array('Administrator');

Um eine Funktion für mehrere Benutzergruppen freizuschalten,
einfach das array um die jeweilige Benutzergruppe erweitern

Beispiel:  $plgFreigabe = ('Administrator','Vorstand','Rollenname1','Rollenname2');  */

/* ***** Berechtigungen fuer das Plugin ***** */

//Legt fest, wer Statistiken anzeigen darf
$plgAllowShow = array('Administrator');

//Legt fest, wer Statistiken konfigurieren darf
$plgAllowConfig =  array('Administrator');
?>
