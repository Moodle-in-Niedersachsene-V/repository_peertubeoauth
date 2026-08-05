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

$string['channelinfo'] = 'Geben Sie hier den Namen Ihres persönlichen PeerTube-Kanals ein (innerhalb des gemeinsamen Moderator-Kontos). Sie sehen danach nur die Videos aus diesem Kanal im Datei-Picker. Lassen Sie das Feld leer, um alle Videos des Moderator-Kontos zu sehen.';
$string['channelname'] = 'PeerTube-Kanalname';
$string['channelname_help'] = 'Der technische Name (URL-Kennung) Ihres Kanals auf PeerTube, zum Beispiel "gms_muster_mueller_maria". Sie finden ihn in der Adresse Ihres Kanals auf PeerTube oder erfragen ihn bei Ihrer Administration.';
$string['configplugin'] = 'PeerTube (OAuth2) Konfiguration';
$string['embedparams'] = 'Embed-URL-Parameter';
$string['embedparams_help'] = 'Query-Parameter, die vom Fallback-Renderer an jedes eingebettete Video angehängt werden. Mehrere Parameter mit einem kaufmännischen Und trennen. Standard ist peertubeLink=0&p2p=0&warningTitle=0. Damit wird P2P deaktiviert, sodass die IP-Adressen der Zuschauer nicht an andere Peers weitergegeben werden, was bei Schülerinnen und Schülern empfohlen ist. Außerdem werden der PeerTube-Link und der IP-Warnhinweis im Player entfernt. Mit title=0 wird zusätzlich das Titel-Overlay ausgeblendet. Bereits an einem Link gesetzte Parameter bleiben erhalten.';
$string['enablecourseinstances'] = 'PeerTube-Instanzen auf Kursebene erlauben';
$string['enableuserinstances'] = 'PeerTube-Instanzen auf Nutzerebene erlauben';
$string['fallbackheader'] = 'Gemeinsames Moderator-Konto. Alle Lehrkräfte teilen sich dieses eine PeerTube-Konto für die Anmeldung. Jede Lehrkraft erhält stattdessen einen eigenen Kanal innerhalb dieses Kontos, siehe unten.';
$string['fallbackpassword'] = 'Moderator-Kontopasswort';
$string['fallbackusername'] = 'Moderator-Kontoname';
$string['fallbackusername_help'] = 'Benutzername des gemeinsamen PeerTube-Moderatorkontos, über das sich alle Lehrkräfte anmelden.';
$string['instanceurl'] = 'PeerTube-Instanz-URL';
$string['instanceurl_help'] = 'Adresse der PeerTube-Instanz Ihrer Schule, zum Beispiel https://peertube.beispiel-schule.de.';
$string['peertubeoauth:view'] = 'PeerTube (OAuth2)-Repository verwenden';
$string['pluginname'] = 'PeerTube (OAuth2)';
$string['privacy:metadata'] = 'Das Plugin PeerTube (OAuth2) speichert keine personenbezogenen Daten. Die Zugangsdaten sind seitenweite Administrationseinstellungen, und das Zugriffstoken wird ausschließlich in der Sitzung gehalten.';
$string['privacy_private'] = 'Privat';
$string['privacy_public'] = 'Öffentlich';
$string['privacy_unlisted'] = 'Nicht gelistet';
$string['privatewarning'] = '(nicht abspielbar, bitte auf nicht gelistet umstellen)';
$string['schoolcode'] = 'Schulkürzel';
$string['schoolcode_help'] = 'Kurzes Kürzel für diese Schule, zum Beispiel "gms_muster". Es wird automatisch als Präfix in PeerTube-Kanalnamen verwendet, die das Upload-Plugin für Lehrkräfte anlegt, zum Beispiel "gms_muster_mueller_maria". Nur Buchstaben, Zahlen und Unterstriche sind erlaubt.';
$string['untitled'] = 'Unbenanntes Video';
