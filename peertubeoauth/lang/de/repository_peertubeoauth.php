<?php
defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'PeerTube (OAuth2)';
$string['configplugin'] = 'PeerTube (OAuth2) Konfiguration';

// Admin / Typ-Ebene.
$string['instanceurl'] = 'PeerTube-Instanz-URL';
$string['instanceurl_help'] = 'Adresse der PeerTube-Instanz Ihrer Schule, z. B. https://peertube.beispiel-schule.de.';
$string['fallbackheader'] = 'Gemeinsames Moderator-Konto. Alle Lehrkräfte teilen sich dieses eine PeerTube-Konto für die Anmeldung. Jede Lehrkraft erhält stattdessen einen eigenen Kanal innerhalb dieses Kontos (siehe unten).';
$string['fallbackusername'] = 'Moderator-Kontoname';
$string['fallbackusername_help'] = 'Benutzername des gemeinsamen PeerTube-Moderatorkontos, über das sich alle Lehrkräfte anmelden.';
$string['fallbackpassword'] = 'Moderator-Kontopasswort';

// Nutzer- / Instanz-Ebene.
$string['channelinfo'] = 'Geben Sie hier den Namen Ihres persönlichen PeerTube-Kanals ein (innerhalb des gemeinsamen Moderator-Kontos). Sie sehen danach nur die Videos aus diesem Kanal im Datei-Picker. Lassen Sie das Feld leer, um alle Videos des Moderator-Kontos zu sehen.';
$string['channelname'] = 'PeerTube-Kanalname';
$string['channelname_help'] = 'Der technische Name (URL-Kennung) Ihres Kanals auf PeerTube, z. B. "frau-mueller". Sie finden ihn in der Adresse Ihres Kanals auf PeerTube oder erfragen ihn bei Ihrer Administration.';

$string['enablecourseinstances'] = 'PeerTube-Instanzen auf Kursebene erlauben';
$string['enableuserinstances'] = 'PeerTube-Instanzen auf Nutzerebene erlauben';

$string['untitled'] = 'Unbenanntes Video';
$string['privacy_public'] = 'Öffentlich';
$string['privacy_unlisted'] = 'Nicht gelistet';
$string['privacy_private'] = 'Privat';
$string['privatewarning'] = '(nicht abspielbar! Bitte auf "Nicht gelistet" umstellen)';
