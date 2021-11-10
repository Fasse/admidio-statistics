<?php
/******************************************************************************
 * Hilfeseite & Benutzerhandbuch für das Statistik-Plugin
 *
 * @copyright 2004-2021 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 * help_id       :   id des Hilfetextes
 * full         :   true = ganzer Hilfetext
 *              :   false = Kurzfassung des Hilfetextes
 * example      :   true = zeigt ein Beispiel zum Hilfethema (falls vorhanden)
 *****************************************************************************/
require_once('../includes.php');

$documentTitle = 'Statistik Plug-In für Admidio - Benutzerhandbuch';

$helpTitles = array(
    '100'           =>'Einleitung',

    '200'           =>'Einführung in das Statistik-Plugin',
    '210'       =>'Aufbau und Funktionsweise des PlugIns',
    '220'       =>'Übersicht über die Komponenten des PlugIns',
    '221'   =>'Menüeinträge für Statistik-PlugIn',
    '222'   =>'Statistik-Übersicht',
    '223'   =>'Konfigruations-Editor',
    '224'   =>'Statistik-Anzeige',
    '225'   =>'Installations-Manager',
    '226'   =>'Konfigurations-Datenbank',

    '300'           =>'Installation und Konfiguration des Plugins',

    '400'           =>'Arbeiten mit dem Statistik-Plugin',
    '410'       =>'Anzeigen von statistischen Auswertungen',
    '420'       =>'Verwenden des Konfigurations-Editors',
    '421'   =>'Erstellen einer neuen Konfiguration',
    '422'   =>'Bearbeiten einer bestehenden Konfiguration',
    '423'   =>'Speichern einer Konfiguration',
    '424'   =>'Eine bestehende Konfiguration unter einem neuen Namen abspeichern',
    '425'   =>'Änderungen an einer Statistik-Konfiguration rückgängig machen',
    '426'   =>'Eine Konfiguration löschen',
    '427'   =>'Vorschau für eine Konfiguration anzeigen',
    '428'   =>'Verändern der Struktur einer Konfiguration',

    '500'           =>'Aufbau einer Statistik-Konfiguration',
    '510'       =>'Allgemeine Erläuterungen zur Statistik-Konfiguration',
    '511'   =>'Farbliche Strukturierung der Eingabefelder',
    '512'   =>'Sonderzeichen',
    '520'       =>'Namen einer Konfiguration',
    '530'       =>'Allgemeine Angaben einer Statistik',
    '531'   =>'Titel der Statistik',
    '532'   =>'Untertitel der Statistik',
    '533'   =>'Standardrolle der Statistik',
    '540'       =>'Angaben für eine Tabelle',
    '541'   =>'Titel der Tabelle',
    '542'   =>'Rolle der Tabelle',
    '550'       =>'Angaben für die Spalten einer Tabelle',
    '551'   =>'Bezeichnung der Spalte',
    '552'   =>'Profilfeld-Auswahl für eine Spalte',
    '553'   =>'Bedingung für die Profilfeld-Auswahl der Spalte',
    '554'   =>'Profilfeld-Auswahl für eine Spalten-Funktion',
    '555'   =>'Spalten-Funktion',
    '556'   =>'Total-Funktion',
    '560'       =>'Angaben für die Zeilen einer Tabelle',
    '561'   =>'Kopfzeile',
    '562'   =>'Bezeichnung der Zeile',
    '563'   =>'Profilfeld-Auswahl für eine Zeile',
    '564'   =>'Bedingung für die Profilfeld-Auswahl der Zeile',

    '600'           =>'Beispiele für Konfigurationen',
    '610'       =>'Zusammenspiel von Spalten und Zeilenkonfigurationen',
    '620'       =>'Statistik mit Prozentsätzen',
    '630'       =>'Spezifische Profifeld-Auswahl',

    '700'           =>'Anhang'
);

$helpTexts = array(
    '100'           =>'Dies ist das Benutzerhandbuch für das Admidio-Statistik-PlugIn. Hier finden Sie alle Informationen für die Installation, Konfiguration und Bedienung des Statistik-PlugIns. In diesem Benutzerhandbuch wird das Admidio-Statistik-PlugIn ganz einfach PlugIn genannt.',

    '200'           =>'',
    '210'       =>'',
    '220'       =>'In diesem Kapitel erhalten sie eine Übersicht über die verschiedenen Komponenten des Statistik-PlugIns und deren Aufgaben.',
    '221'   =>'Je nach Konfiguration des Plugins werden einem Benutzer im Admidio-Hauptmenü oder an einem anderen Ort die Verknüpfungen für die Statistik-Übersicht, den Konfigurations-Editor und den Installations-Manager angezeigt.',
    '222'   =>'Diese Anzeige, zeigt eine Übersicht aller bereits erstellten Statistik-Konfigurationen. Von hier aus kann man sich die Auswertungen einer Statistik direkt Anzeigen lassen.',
    '223'   =>'Mit dem Editor können Sie Statistik-Konfigurationen erstellen, bearbeiten und löschen.',
    '224'   =>'Zeigt eine statistische Auswertung für eine vorhandene Statistik-Konfiguration.',
    '225'   =>'Ermöglicht die Installation und Deinstallation des PlugIns.',
    '226'   =>'Mit der Plugin-Installation werden in der Admidio-Datenbank Tabellen zur Abspeicherung von Statistik-Konfigurationen eingefügt. Für die Benutzung des Plugins, soll und muss nichts manuell an diesen Tabellen verändert werden. !Ändern sie niemals manuell mit einem Datenbank-Editor die Tabellen oder Einträge von Tabellen. Dies kann zu Inkonsistenzen und Datenverlust oder einer defekten Installation führen! Für das Erstellen und Löschen der Tabellen sollte nur der Installations-Manager verwendet werden (Kapitel  3 ).',

    '300'           =>'',

    '400'           =>'',
    '410'       =>'',
    '420'       =>'Im folgenden Kapitel wird die Verwendung des Konfigurations-Editors beschrieben. Dies ist wohl die wichtigste und umfangreichste Komponente des Plugins. Es kann durchaus sehr komplex sein Statistik-Konfigurationen so zu erstellen, das sie das gewünschte Resultat anzeigen. Beachten sie deshalb genau die Beschreibungen der einzelnen Eingabemöglichkeiten und schauen sie sich die in Kapitel  6  aufgeführten Beispiele an.',
    '421'   =>'Um eine neue Konfiguration zu erstellen, wählen sie im Dropdown-Menü den Eintrag "eine neue Statistik-Konfiguration erstellen" aus. Sie können auch das Symbol (image:add) verwenden. Danach wird eine neue, leere Konfiguration geladen.',
    '422'   =>'Wählen sie im Dropdown-Menü eine bereits gespeicherte Statstik-Konfiguration aus. Es wird die ausgewählte Konfiguration aus der Datenbank geladen.',
    '423'   =>'Mit dem Symbol (image:save) speichern sie die Änderungen an einer Bestehenden Statistik-Konfiguration. Falls sie eine neue Konfiguration erstellt haben, müssen sie vor dem Speichern einen Namen für die Konfiguration angeben.',
    '424'   =>'Mit dem Symbol (image:saveas) können sie eine bestehende Konfiguration als Kopie unter neuem Namen abspeichern.',
    '425'   =>'Mit dem Symbol (image:undo) werden alle an dieser Statistik gemachten, ungespeicherten Änderungen rückgängig gemacht. Dies bedeutet, dass der letzte Stand dieser Konfiguration aus der Datenbank geladen wird. !Es werden alle ungespeicherten Änderungen gelöscht, falls sie nur eine Änderung in einem Textfeld rückgängig machen möchten, benutzen sie die Ctrl-Z Funktion ihres Browsers!',
    '426'   =>'Mit dem Symbol (image:delete) löschen sie die aktuell ausgewählte Statistik-Konfiguration aus der Datenbank. !Das Löschen erfolgt definitiv und kann nicht Rückgängig gemacht werden!',
    '427'   =>'Mit dem Knopf (image:button_show) können sie sich eine Vorschau der aktuellen Statistik-Konfiguration anzeigen lassen. Sie erhalten die gleiche Ausgabe wie unter Kapitel  4.1  beschrieben. !Die Vorschau wird mit den aktuellen Änderungen der Konfiguration erstellt. Benutzen sie deshalb immer das Symbol (image:button_back) auf der Anzeige-Seite oder den Zurück-Button ihres Browsers um zum Konfigurations-Editor zurück zu gelangen. Falls sie hier einen anderen Link auswählen, oder ihr Browser-Fenster schliessen gehen alle ungespeicherten Änderungen verloren!',
    '428'   =>'Die Struktur der Statistik-Konfiguration kann geändert werden. Folgende Elemente können verändert werden:<br />
                        <ul>
                            <li>
                                Anzahl der Tabellen einer Konfiguration:<br />
                                Mit (image:button_add_table) erstellen sie eine neue Tabelle.<br />
                                Mit (image:button_del_table) löschen sie eine vorhandene Tabelle.<br />
                            </li><li>
                                Anzahl der Spalten einer Tabelle:<br />
                                Mit (image:button_add_col) erstellten sie eine neue Spalte.<br />
                                Mit (image:delete) unter der Spalte löschen sie diese.<br />
                            </li><li>
                                Anzahl der Zeilen einer Tabelle:<br />
                                Mit (image:button_add_row) erstellen sie eine neue Zeile.<br />
                                Mit (image:delete) hinter der Zeile löschen sie diese.<br />
                            </li>
                        </ul>
                        Die Reihenfolge von Tabellen, Zeilen und Spalten können sie nicht direkt ändern. Sie können z.B. ein Element, welches sie nach unten verschieben wollen löschen. Alle nachstehenden Elemente, nach dem gelöschten Element rücken eine Position nach vorn.<br />
                        Wenn sie ein Element verschieben wollen, fügen sie besser ein neues Element ein und kopieren sie die eingegebenen Daten mit Ctrl-C, Ctrl-V an die gewünschte Stelle. Dies Funktioniert natürlich nur bei Texteingabefeldern.',
    '500'           =>'Eine Statistik-Konfiguration besteht grundsätzlich aus drei Teilen:
                        <ul>
                            <li>Namen der Konfiguration</li>
                            <li>Allgemeine Angaben der Statistik</li>
                            <li>Konfiguration für eine oder mehrere Tabellen einer Statistik</li>
                        </ul>
                        Die Konfiguration der Tabellen enthält des weiteren:
                        <ul>
                            <li>Angaben für die Tabelle</li>
                            <li>Angaben für die Spalten der Tabelle</li>
                            <li>Angaben für die Zeilen der Tabelle</li>
                        </ul>',
    '510'       =>'In diesem Kurzen Abschnitt sind einige allgemeingültige Regeln und Hinweise Festgehalten, die Ihnen bei der Erstellung von Konfigurationen helfen werden.',
    '511'   =>'Im Konfigurations-Editor sind Eingabefelder, welche in Zusammenhang miteinander stehen, oder die einem ähnlichen Eingabetyp entsprechen in der selben Farbe hervorgehoben:
                        <ul>
                            <li>Orange: Alle Felder, die zur Bezeichnung von Spalten und Zeilen verwendet werden.</li>
                            <li>Blau: Alle Felder, die zur Formulierung von Einschränkungen dienen</li>
                            <li>Grün: Alle Felder, die zur Definition von Funktionen benötigt werden</li>
                            <li>Rot: Alle Felder, die zur Definition von Total-Funktionen benötigt werden</li>
                        </ul>',
    '512'   =>'Im Allgemeinen sind praktisch alle Sonderzeichen erlaubt, für die Formulierungen von Bedingungen sind die speziellen Zeichen (Operatoren, Wildcards, etc.) zu beachten. Nicht erlaubt sind hingegen gewisse Kombinationen von Zeichen, z.B: "<" in Verbindung mit einem darauffolgenden Zeichen. Diese Kombination wird i.d.R. als Beginn eines HTML-Tags gewertet und wird aus Sicherheitsgründen entfernt.',
    '520'       =>'Der Namen einer Statistik-Konfiguration dient wird verwendet um die Konfiguration in der Datenbank abzuspeichern. Dieser Name erscheint in der Übersicht (Kapitel  2.2.2 ) und bei der Auswahl im Konfigurations-Editor(Kapitel  2.2.3 ). Er erscheint hingegen nicht in der Statistik-Anzeige. Der Name einer Konfiguration muss zwingend angegeben werden, jedoch nicht eindeutig sein. Es können also auch mehrere Konfigurationen gleich benannt werden. Es ist zu empfehlen, eindeutige und sprechende Namen für eine Konfiguration zu vergeben, da dies für den Benutzer das einzige Identifikationsmerkmal einer Konfiguration ist.',
    '530'       =>'Jede Statistik hat drei allgemeine Angaben, welche in diesem Kapitel kurz beschrieben sind.',
    '531'   =>'Der Titel einer Statistik erscheint zuoberst bei der Statistik-Anzeige. Ein Titel für die Statistik ist nicht zwingend. (image:statistic_title)',
    '532'   =>'Optional kann auch ein Untertitel gespeichert werden, der weitergehende Informationen zu einer Statistik enthält. (image:statistic_subtitle)',
    '533'   =>'Die Standardrolle der Statistik kommt nur dann zum Zug, falls für die einzelne Tabelle keine Rolle angegeben wurde. Es muss zwingend eine gültige Standardrolle angegeben werden, auch wenn für alle Tabellen eine eigene Rolle ausgewählt wurde. (image:statistic_std_role)',
    '540'       =>'Die Tabelle enthält neben den Spalten und Zeilenkonfigurationen allgemeine Angaben. Jede Tabelle enthält mindestens eine Spalte und eine Zeile.',
    '541'   =>'Der Tabellentitel ist optional und ist vor allem bei Statistiken mit mehreren Tabellen zu empfehlen. (image:table_title)',
    '542'   =>'Es kann für jede Tabelle eine spezifische Rolle definiert werden. Diese ist optional und überschreibt die Standardrolle der Statistik. Folglich kommt die Standardrolle zum Zug, wenn keine spezifische Rolle für die Tabelle eingetragen wurde. (image:table_role)',
    '550'       =>'Für eine Spalte können verschiedene Angaben und Kriterien gemacht werden. Die verschiedenen Angaben stehen z.T. in Bezug zueinander.',
    '551'   =>'Die Bezeichnung einer Spalte ist als Spalten-Überschrift anzusehen. Es ist empfehlenswert einen Text einzutragen, der die Ergebnisse in dieser Spalte beschreibt. Dies ist aber optional. (image:col_label)',
    '552'   =>'Die Profilfeld-Auswahl dient dazu, eine Einschränkung für die Auswertung der Datensätze in dieser Spalte zu Definieren. Diese Angabe hängt immer mit einer Bedingung zusammen. Es kann ein Profilfeld angegeben werden, um nur bestimmte Datensätze für die Auswertung dieser Spalte zu berücksichtigen, welche eine bestimmte Bedingung erfüllen, die definiert werden muss (Kapitel  5.5.3 ). (image:col_profile_field)',
    '553'   =>'Wenn eine Profilfeld-Auswahl für die Spalte getroffen wurde, kann mit einer Bedingung eingeschränkt werden, welche Datensätze für die Auswertung dieser Spalte berücksichtigt werden.
                        Die Bedingung bezieht sich also immer auf das ausgewählte Profilfeld (Kapitel  5.5.2 ).
                        (image:col_condition)<br />
                        Die Formulierung einer Bedingung folgt einer ganz bestimmten Syntax, welche eingehalten werden muss. Ansonsten kann keine korrekte oder vollständige Auswertung für die gewünschte Bedingung gemacht werden. Für die Formulierung einer Bedingung sind folgende Syntax-Elemente zu Berücksichtigen:<br />
                        (table:comparision_operators)<br />
                        (table:wildcards)<br />
                        (table:value_checks)<br />
                        (table:logical_operators)<br />
                        (table:field_types)<br />
                        (table:std_field_types)<br />
                        Aus der Profifeld-Auswahl (Kapitel  5.5.2 ) und einer gültigen Bedingung entsteht eine gültige Einschränkung.
                        Zu der Formulierung von Bedingungen, beachten sie auch die Beispiele für Statistik-Konfigurationen in Kapitel  6 .
                        Tipp: Die Formulierung von Einschränkungen für die Statistik-Erstellung funktioniert ähnlich wie bei der Erstellung von Listen. Falls sie die Listenfunktion nicht kennen, sollten sie vielleicht einmal eine eigene Liste erstellen.',
    '554'   =>'Für jede Funktion in einer Spalte muss angegeben werden, auf welches Profilfeld sich die Spalten-Funktion bezieht. (image:col_func_arg)
                        Dafür gibt es zwei Möglichkeiten:
                        <ul>
                            <li>
                                Die bestehende Auswahl für die Spalten-Funktion verwenden:<br />
                                Die Spalten-Funktion wird auf die Auswahl der Profilfelder angewendet, welche sie bei der Auswahl und Bedingung für die Spalte und die Zeile angegeben haben. Die Spalten-Funktion bezieht sich also immer auf alle definieren Auswahlen und Bedingungen (Siehe Kapitel  5.5.2 , 5.5.3 , 5.6.3 , 5.6.4 )
                            </li>
                            <li>
                                Ein zusätzliches Profilfeld für die Spalten-Funktion verwenden:<br />
                                Wird ein anderer Wert als „Auswahl“ getroffen bezieht sich die Funktion auf die Daten in dem Ausgewählten Profilfeld. Hiermit sind kombinierte Auswertungen möglich. Siehe hierzu Kapitel  6.3 . Die so Ausgewerteten Daten beziehen sich jedoch immer noch auf die Einschränkungen, welche bei der Auswahl und Bedingung für die Spalte und die Zeile gemacht wurde.
                            </li>
                        </ul>',
    '555'   =>'Die Spalten-Funktion bezieht sich immer auf die in Kapitel  5.5.4  beschriebene Profilfeld-Auswahl.
                        (image:col_func)
                        Insgesamt gibt es 5 verschiedene Funktionen, die gemacht werden können. Es können jedoch nicht alle Funktionen für alle Profilfeld-Auswahl benutzt werden:
                        Funktionen für eine bestehende Profilfeld-Auswahl:<br />
                        <ul>
                            <li>
                                Anzahl (#): Zeigt die Anzahl Datensätze, welche die definierten Einschränkungen erfüllen.
                            </li>
                            <li>
                                Prozentsatz (%): Zeigt den prozentualen Anteil Datensätze, welche die definieren Einschränkungen erfüllen im Vergleich zu den allen anderen in dieser Tabelle ausgegebenen Werte. 100% bezieht sich also immer auf die Summe aller Auswertungen in der jeweiligen Tabelle. Beispiel: Kapitel  6.2 . !Dieses Verhalten bedeutet, dass wenn z.B. alle Mitglieder einer Rolle in einer Statistik erscheinen sollen, dies mit Einschränkungen so gemacht werden muss, dass alle Möglichkeiten explizit definiert werden müssen!
                            </li>
                        </ul>
                        Funktionen für eine zusätzliche Profifeld-Auswahl. Siehe dazu Kapitel  6.3 .
                        <ul>
                            <li>
                                Summe (sum): Diese Funktion macht nur bei einem Zahlenfeld Sinn. Sie zeigt die Summe der Zahlenwerte eines definierten Profilfeldes aller Datensätze, die die vorhandenen Einschränkungen erfüllen.
                            </li>
                            <li>
                                Durchschnitt (avg): Wie Summe, nur wird der Durchschnitt angezeigt.
                            </li>
                            <li>
                                Minimum (min): Wie Summe, nur wird der kleinste Wert angezeigt. Diese Funktion ist auch für Textfelder verwendbar, sie zeigt dann den alphabetisch gesehen kleinsten Wert an.
                            </li>
                            <li>
                                Maximum (max): Wie Summe, nur wird der grösste Wert angezeigt. Diese Funktion ist auch für Textfelder verwendbar, sie zeigt dann den alphabetisch gesehen grössten Wert an.
                            </li>
                        </ul>',
    '556'   =>'Optional kann eine Total-Funktion für eine Spalte definiert werden.
                        (image:col_func_total)
                        Diese bezieht sich immer auf alle Ergebnisse der jeweiligen Spalte. Hier stehen die Funktionen Summe, Durchschnitt, Minimum und Maximum zur Verfügung.',
    '560'       =>'Für eine Zeile können zusätzliche Angaben und Kriterien gemacht werden. Sie werden mit den Angaben der Spalten-Konfigurationen kombiniert, wie folgendes Beispiel zeigt: Kaptiel  6.1 .',
    '561'   =>'Hier kann ein Text eingetragen werden, der als Überschrift für die 1. Spalte mit den Zeilenbezeichnungen dient. (image:row_headrow)',
    '562'   =>'Hier kann ein Text eingetragen werden, der die Ergebnisse in dieser Zeile beschreibt.<br />
                        (image:row_label)',
    '563'   =>'Diese Eingabe wird nach den gleichen Regeln gemacht wie die Profifeld-Auswahl für eine Spalte (Kapitel  5.5.2 ). (image:row_profile_field)',
    '564'   =>'Diese Eingabe wird nach den gleichen Regeln gemacht wie die Bedingung für die Profifeld-Auswahl für eine Spalte (Kapitel  5.5.3 ). Die Einschränkung für eine Zeile ist gleichwertig wie die der Spalte und wird mit der Einschränkung für die Spalte kombiniert. Dies bedeutet, dass nur Ergebnisse angezeigt werden, die sowohl die Einschränkung für die Zeile als auch diejenige der Spalte erfüllt. Es kann jedoch auch nur eine Einschränkung für die Spalte oder eine Einschränkung für die Zeile oder gar keine Einschränkung gemacht werden. Im letztgenannten Fall, werden dann alle Datensätze ausgewertet, die in der Rolle sind, die für diese Tabelle gilt. <br />
                        (image:row_condition)',

    '600'           =>'',
    '610'       =>'(image:example_select_count)<br />
                        Dieses Beispiel bezieht sich auf eine Konfiguration, in der die Auswahl direkt quantitativ ausgewertet wird (siehe Bild). Die Tabelle zeigt Möglichkeiten, wie Einschränkung kombiniert werden können und was das zu erwartende Resultat ist.<br />
                        (table:example_select_count)',
    '620'       =>'(image:example_select_percent) <br />
                        Dieses Beispiel bezieht sich auf eine Konfiguration, in der die Auswahl prozentual ausgewertet wird (siehe Bild).  Nehmen wir an, die für die Statistik ausgewählte Rolle umfasst 156 Profile, davon  haben 100 als Geschlecht männlich angegeben. <br />
                        (table:example_select_percent) <br />
                        Das Tabellentotal beträgt 100 (100%), da in der Tabelle die weiblichen Mitglieder nicht berücksichtigt werden. Demzufolge hat es 56 männliche Mitglieder zwischen 20 und 25 Jahren, 23 sind zwischen 25 und 30 Jahre alt ...',
    '630'       =>'(image:example_specific_field)<br />
                        Das Beispiel auf dem Bild würde das Alter des jüngsten Rollenmitglieds, das in der Auswahl erfasst ist ausgegeben. In der Tabelle unten ist beschrieben, wie die einzelnen Funktionen die Profilfelder auswerten. Gewisse Kombination machen keinen Sinn und können zu Fehlern führen!<br />
                        (table:example_specific_field)',

    '700'           =>''
);

$shortTexts = array(
    '100'           =>'Dies ist das Benutzerhandbuch für das Admidio-Statistik-PlugIn. Hier finden Sie alle Informationen für die Installation, Konfiguration und Bedienung des Statistik-PlugIns. In diesem Benutzerhandbuch wird das Admidio-Statistik-PlugIn ganz einfach PlugIn genannt.',

    '200'           =>''
);


$helpImages = array(
    'add'                    =>'<i class="fas fa-plus-circle"></i>',
    'save'                   =>'<i class="fas fa-save"></i>',
    'saveas'                 =>'<i class="fas fa-clone"></i>',
    'undo'                   =>'<i class="fas fa-undo"></i>',
    'delete'                 =>'<i class="fas fa-trash-alt"></i>',
    'button_show'            =>'<img src="../resources/images/button_show.png" alt="button_show"/>',
    'button_back'            =>'<img src="../resources/images/button_back.png" alt="button_back"/>',
    'button_add_table'       =>'<img src="../resources/images/button_add_table.png" alt="button_add_table"/>',
    'button_del_table'       =>'<img src="../resources/images/button_del_table.png" alt="button_del_table"/>',
    'button_add_col'         =>'<img src="../resources/images/button_add_col.png" alt="button_add_col"/>',
    'button_add_row'         =>'<img src="../resources/images/button_add_row.png" alt="button_add_row"/>',
    'statistic_title'        =>'<img src="../resources/images/statistic_title.png" alt="statistic_title"/>',
    'statistic_subtitle'     =>'<img src="../resources/images/statistic_subtitle.png" alt="statistic_subtitle"/>',
    'statistic_std_role'     =>'<img src="../resources/images/statistic_std_role.png" alt="statistic_std_role"/>',
    'table_title'            =>'<img src="../resources/images/table_title.png" alt="table_title"/>',
    'table_role'             =>'<img src="../resources/images/table_role.png" alt="table_role"/>',
    'col_label'              =>'<img src="../resources/images/col_label.png" alt="col_label"/>',
    'col_profile_field'      =>'<img src="../resources/images/col_profile_field.png" alt="col_profile_field"/>',
    'col_condition'          =>'<img src="../resources/images/col_condition.png" alt="col_condition"/>',
    'col_func_arg'           =>'<img src="../resources/images/col_func_arg.png" alt="col_func_arg"/>',
    'col_func'               =>'<img src="../resources/images/col_func.png" alt="col_func"/>',
    'col_func_total'         =>'<img src="../resources/images/col_func_total.png" alt="col_func_total"/>',
    'row_headrow'            =>'<img src="../resources/images/row_headrow.png" alt="row_headrow"/>',
    'row_label'              =>'<img src="../resources/images/row_label.png" alt="row_label"/>',
    'row_profile_field'      =>'<img src="../resources/images/row_profile_field.png" alt="row_profile_field"/>',
    'row_condition'          =>'<img src="../resources/images/row_condition.png" alt="row_condition"/>',
    'example_select_count'   =>'<img src="../resources/images/example_select_count.png" alt="example_select_count"/>',
    'example_select_percent' =>'<img src="../resources/images/example_select_percent.png" alt="example_select_percent"/>',
    'example_specific_field' =>'<img src="../resources/images/example_specific_field.png" alt="example_specific_field"/>'
);

$helpTables = array(
    'comparision_operators'   =>'<table class="tableList">
                                    <thead>
                                        <tr>
                                            <td>Zeichen</td>
                                            <td>Bedeutung</td>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td>=, ==, like, is, ist</td>
                                            <td>Gleich</td>
                                        </tr>
                                        <tr>
                                            <td>!,!=, &lt;&gt;</td>
                                            <td>Ungleich</td>
                                        </tr>
                                        <tr>
                                            <td>&gt;</td>
                                            <td>Grösser als</td>
                                        </tr>
                                        <tr>
                                            <td>&lt;</td>
                                            <td>Kleiner als</td>
                                        </tr>
                                        <tr>
                                            <td>&gt;=</td>
                                            <td>Grösser gleich</td>
                                        </tr>
                                        <tr>
                                            <td>=&lt;</td>
                                            <td>Kleiner gleich</td>
                                        </tr>
                                    </tbody>
                                </table>',
    'wildcards'   =>'<table class="tableList">
                                    <thead>
                                        <tr>
                                            <td>Zeichen</td>
                                            <td>Bedeutung</td>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td>*</td>
                                            <td>für 0,1 oder mehr beliebige Zeichen</td>
                                        </tr>
                                    </tbody>
                                </table>',
    'value_checks'   =>'<table class="tableList">
                                    <thead>
                                        <tr>
                                            <td>Zeichen</td>
                                            <td>Bedeutung</td>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td>FEHLT</td>
                                            <td>Feldinhalt ist leer (z.B. Textfeld, bei dem nichts eingetragen wurde)</td>
                                        </tr>
                                        <tr>
                                            <td>VORHANDEN</td>
                                            <td>Es wurde ein Wert in das Feld eingetragen.</td>
                                        </tr>
                                    </tbody>
                                </table>',
    'logical_operators'   =>'<table class="tableList">
                                    <thead>
                                        <tr>
                                            <td>Zeichen</td>
                                            <td>Bedeutung</td>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td>&amp;,und, and,&amp;&amp;, +</td>
                                            <td>Logische-UND-Verknüpfung von mehreren Bedingungen</td>
                                        </tr>
                                        <tr>
                                            <td>|,oder, or, ||</td>
                                            <td>Logische-ODER-Verknüpfung von mehreren Bedingungen</td>
                                        </tr>
                                    </tbody>
                                </table>',
    'field_types'   =>'<table class="tableList">
                                    <thead>
                                        <tr>
                                            <td>Feldtyp</td>
                                            <td>gültige Bedingungskriterien</td>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td>Textfeld</td>
                                            <td>Buchstaben, Zahlen und Sonderzeichen</td>
                                        </tr>
                                        <tr>
                                            <td>Zahlenfeld</td>
                                            <td>Ziffern 0-9 (nur ganzzahlige Werte sind erlaubt)</td>
                                        </tr>
                                        <tr>
                                            <td>Datumsfeld</td>
                                            <td>TT.MM.JJJJ oder eine Zahl mit angehängtem “j“(z.B. 18j)</td>
                                        </tr>
                                        <tr>
                                            <td>Checkbox- bzw. Ja/Nein-Feld</td>
                                            <td>Ja, Nein</td>
                                        </tr>
                                        <tr>
                                            <td>Optionsfeld bzw. Drop-Down-Feld</td>
                                            <td>die für das ausgewählte Feld möglichen Auswahl-Werte (z.B. “männlich“)</td>
                                        </tr>
                                        <tr>
                                            <td>E-Mail-Feld</td>
                                            <td>für E-Mail Adressen erlaubte Zeichen</td>
                                        </tr>
                                        <tr>
                                            <td>URL-Feld</td>
                                            <td>für Webadressen erlaubte Zeichen</td>
                                        </tr>
                                    </tbody>
                                </table>',
    'std_field_types'   =>'<table class="tableList">
                                    <thead>
                                        <tr>
                                            <td>Standardfeld</td>
                                            <td>Feldtyp</td>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td>Nachname</td>
                                            <td>Textfeld</td>
                                        </tr>
                                        <tr>
                                            <td>Vorname</td>
                                            <td>Textfeld</td>
                                        </tr>
                                        <tr>
                                            <td>Adresse</td>
                                            <td>Textfeld</td>
                                        </tr>
                                        <tr>
                                            <td>PLZ</td>
                                            <td>Textfeld</td>
                                        </tr>
                                        <tr>
                                            <td>Ort</td>
                                            <td>Textfeld</td>
                                        </tr>
                                        <tr>
                                            <td>Land</td>
                                            <td>Textfeld</td>
                                        </tr>
                                        <tr>
                                            <td>Telefon</td>
                                            <td>Textfeld</td>
                                        </tr>
                                        <tr>
                                            <td>Handy</td>
                                            <td>Textfeld</td>
                                        </tr>
                                        <tr>
                                            <td>Fax</td>
                                            <td>Textfeld</td>
                                        </tr>
                                        <tr>
                                            <td>Geburtstag</td>
                                            <td>Datumsfeld</td>
                                        </tr>
                                        <tr>
                                            <td>Geschlecht</td>
                                            <td>Optionsfeld</td>
                                        </tr>
                                        <tr>
                                            <td>E-Mail</td>
                                            <td>E-Mail-Feld</td>
                                        </tr>
                                        <tr>
                                            <td>Webseite</td>
                                            <td>URL-Feld</td>
                                        </tr>
                                        <tr>
                                            <td>Soziale Netzwerke</td>
                                            <td>Textfeld</td>
                                        </tr>
                                        <tr>
                                            <td>Mitgliedsnummer</td>
                                            <td>Zahlenfeld</td>
                                        </tr>
                                    </tbody>
                                </table>',
    'example_select_count'   =>'',
    'example_select_percent'   =>'',
    'example_specific_field'   =>''
);

//falls Hilfetext-ID gesetzt, vorhandene Konfiguration auslesen
if (isset($_GET['help_id'])){
    $helpID      = admFuncVariableIsValid($_GET, 'help_id', 'numeric');
    if($helpID != 0){
        displayHelpText($helpID);
    }else{
        displayWholeManual();
    }
}else{
    displayWholeManual();
}

function replaceImageLink($sourceText){
    global $helpImages;
    $replacedText = $sourceText;
    if (preg_match_all("#\(image:(.*?)\)#", $sourceText, $match)){
        for ($i = 0; $i < count($match[1]); $i++) {
            $search = "(image:".$match[1][$i].")";
            $replace = $helpImages[$match[1][$i]];
            $replacedText = str_replace($search,$replace,$replacedText);
        }
    }
    return $replacedText;
}

function replaceTable($sourceText){
    global $helpTables;
    $replacedText = $sourceText;
    if (preg_match_all("#\(table:(.*?)\)#", $sourceText, $match)){
        for ($i = 0; $i < count($match[1]); $i++) {
            $search = "(table:".$match[1][$i].")";
            $replace = $helpTables[$match[1][$i]];
            $replacedText = str_replace($search,$replace,$replacedText);
        }
    }
    return $replacedText;
}

function displayHelpText($helpID){

    global $gL10n, $helpTitles, $helpTexts;

    $helpWindow = header('Content-type: text/html; charset=utf-8');

    $helpWindow .= '
        <div class="modal-header">
            <h3 class="modal-title">'.$gL10n->get('SYS_NOTE').'</h3>
            <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
        </div>
        <div class="modal-body">';
            if (array_key_exists($helpID,$helpTitles)){

                $outputText = replaceImageLink($helpTexts[$helpID]);
                $outputText2 = replaceTable($outputText);

                $helpWindow .= '<p>'.$outputText2.'</p>';
            }else{
                $helpWindow .= 'Kapitel '.$helpID.' wurde nicht gefunden.';
            }

        $helpWindow .= '</div>';
        echo $helpWindow;
}

function displayWholeManual(){
    global $helpTitles;
    foreach ($helpTitles as $key => $value){
        displayHelpText($key);
    }
}
?>
