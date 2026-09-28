<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * German strings for repository_peertubeoauth.
 *
 * @package    repository_peertubeoauth
 * @author     Moodle in Niedersachsen e. V.
 * @copyright  2026 Moodle in Niedersachsen e. V.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['addmapping'] = 'Gruppenkanal hinzufügen';
$string['channelcreated'] = 'Der Kanal "{$a}" wurde auf PeerTube angelegt.';
$string['channelexists'] = 'Der Kanal "{$a}" besteht auf PeerTube bereits und wurde übernommen.';
$string['channelinfo'] = 'Geben Sie hier den Namen Ihres persönlichen PeerTube-Kanals ein (innerhalb des gemeinsamen Moderator-Kontos). Sie sehen danach die Videos aus diesem Kanal im Datei-Picker, zusammen mit den Videos der Gruppenkanäle Ihrer globalen Gruppen. Lassen Sie das Feld leer, um alle Videos des Moderator-Kontos zu sehen.';
$string['channelname'] = 'PeerTube-Kanalname';
$string['channelname_help'] = 'Der technische Name (URL-Kennung) Ihres Kanals auf PeerTube, zum Beispiel "gms_muster_mueller_maria". Sie finden ihn in der Adresse Ihres Kanals auf PeerTube oder erfragen ihn bei Ihrer Administration.';
$string['cohort'] = 'Globale Gruppe';
$string['cohort_help'] = 'Mitglieder dieser globalen Gruppe sehen die Videos des zugeordneten Kanals im Datei-Picker. Aufgeführt werden nur Gruppen ohne bestehende Zuordnung.';
$string['cohortchannelintro'] = 'Ein Gruppenkanal ist ein gewöhnlicher Kanal innerhalb des gemeinsamen Moderator-Kontos. Mitglieder der zugeordneten globalen Gruppe finden seine Videos im Datei-Picker, zusätzlich zu ihrem eigenen Kanal. Da alle Kanäle demselben PeerTube-Konto gehören, regelt diese Zuordnung, wer ein Video findet, nicht wer es abspielen darf.';
$string['configplugin'] = 'PeerTube (OAuth2) Konfiguration';
$string['confirmdeletemapping'] = 'Diese Zuordnung wirklich entfernen? Der Kanal und seine Videos bleiben auf PeerTube bestehen, und bereits in Kurse eingebettete Videos laufen weiter.';
$string['createchannel'] = 'Kanal auf PeerTube anlegen';
$string['createchannel_help'] = 'Existiert der Kanal noch nicht, wird er unter dem gemeinsamen Moderator-Konto angelegt. Lassen Sie die Option gesetzt, sofern Sie den Kanal nicht bereits von Hand auf PeerTube erstellt haben.';
$string['embedparams'] = 'Embed-URL-Parameter';
$string['embedparams_help'] = 'Query-Parameter, die vom Fallback-Renderer an jedes eingebettete Video angehängt werden. Mehrere Parameter mit einem kaufmännischen Und trennen. Standard ist peertubeLink=0&p2p=0&warningTitle=0. Damit wird P2P deaktiviert, sodass die IP-Adressen der Zuschauer nicht an andere Peers weitergegeben werden, was bei Schülerinnen und Schülern empfohlen ist. Außerdem werden der PeerTube-Link und der IP-Warnhinweis im Player entfernt. Mit title=0 wird zusätzlich das Titel-Overlay ausgeblendet. Bereits an einem Link gesetzte Parameter bleiben erhalten.';
$string['enablecourseinstances'] = 'PeerTube-Instanzen auf Kursebene erlauben';
$string['enableuserinstances'] = 'PeerTube-Instanzen auf Nutzerebene erlauben';
$string['errorchannelcreate'] = 'Die Zuordnung wurde gespeichert, der Kanal "{$a}" konnte auf PeerTube aber nicht angelegt werden. Bitte legen Sie ihn dort von Hand an oder prüfen Sie die Einstellungen des Moderator-Kontos.';
$string['errorchannelname'] = 'Ein Kanalname darf nur Kleinbuchstaben, Ziffern, Unterstriche und Punkte enthalten. Lassen Sie das Feld leer, um ihn aus dem Gruppennamen abzuleiten.';
$string['errorcohortmissing'] = 'Die ausgewählte globale Gruppe existiert nicht mehr.';
$string['errornotconfigured'] = 'Die PeerTube-Instanz-URL ist noch nicht konfiguriert. Solange sie fehlt, können keine Gruppenkanäle auf PeerTube angelegt werden.';
$string['fallbackheader'] = 'Gemeinsames Moderator-Konto. Alle Lehrkräfte teilen sich dieses eine PeerTube-Konto für die Anmeldung. Jede Lehrkraft erhält stattdessen einen eigenen Kanal innerhalb dieses Kontos, siehe unten.';
$string['fallbackpassword'] = 'Moderator-Kontopasswort';
$string['fallbackusername'] = 'Moderator-Kontoname';
$string['fallbackusername_help'] = 'Benutzername des gemeinsamen PeerTube-Moderatorkontos, über das sich alle Lehrkräfte anmelden.';
$string['groupchanneldisplayname'] = 'Anzeigename auf PeerTube';
$string['groupchanneldisplayname_help'] = 'Der lesbare Name, unter dem der Kanal auf PeerTube erscheint. Leer lassen, um den Namen der globalen Gruppe zu verwenden.';
$string['groupchannelname'] = 'Kanalname';
$string['groupchannelname_help'] = 'Die technische Kanalkennung auf PeerTube. Erlaubt sind nur Kleinbuchstaben, Ziffern, Unterstriche und Punkte; Bindestriche sind nicht zulässig. Leer lassen, um die Kennung aus Schulkürzel und Gruppenname abzuleiten.';
$string['groupchannels'] = 'Gruppenkanäle';
$string['instanceurl'] = 'PeerTube-Instanz-URL';
$string['instanceurl_help'] = 'Adresse der PeerTube-Instanz Ihrer Schule, zum Beispiel https://peertube.beispiel-schule.de.';
$string['managecohortchannels'] = 'Gruppenkanäle verwalten';
$string['mappingdeleted'] = 'Die Zuordnung wurde entfernt.';
$string['mappingsaved'] = 'Die Zuordnung wurde gespeichert.';
$string['nocohortsavailable'] = 'Es gibt keine globale Gruppe mehr ohne Zuordnung. Legen Sie zuerst eine Gruppe an oder bearbeiten Sie eine bestehende Zuordnung.';
$string['nomappings'] = 'Es sind noch keine Gruppenkanäle zugeordnet.';
$string['orphanedmapping'] = 'Gruppe gelöscht';
$string['peertubeoauth:view'] = 'PeerTube (OAuth2)-Repository verwenden';
$string['pluginname'] = 'PeerTube (OAuth2)';
$string['privacy:metadata'] = 'Das Plugin PeerTube (OAuth2) speichert keine personenbezogenen Daten. Die Zugangsdaten sind seitenweite Administrationseinstellungen, das Zugriffstoken wird ausschließlich in der Sitzung gehalten, und die Tabelle der Gruppenkanäle enthält einen Verweis auf eine globale Gruppe samt Kanalname, jedoch keine Angaben zu einzelnen Personen.';
$string['privacy_private'] = 'Privat';
$string['privacy_public'] = 'Öffentlich';
$string['privacy_unlisted'] = 'Nicht gelistet';
$string['privatewarning'] = '(nicht abspielbar, bitte auf nicht gelistet umstellen)';
$string['schoolcode'] = 'Schulkürzel';
$string['schoolcode_help'] = 'Kurzes Kürzel für diese Schule, zum Beispiel "gms_muster". Es wird automatisch als Präfix in PeerTube-Kanalnamen verwendet, die das Upload-Plugin für Lehrkräfte anlegt, zum Beispiel "gms_muster_mueller_maria". Nur Buchstaben, Zahlen und Unterstriche sind erlaubt.';
$string['untitled'] = 'Unbenanntes Video';
