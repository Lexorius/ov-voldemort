<?php
/** Tests für den Kontakt-Import (CSV und vCard). Aufruf: php t_import.php */
declare(strict_types=1);

require dirname(__DIR__) . '/src/lib/util.php';
require dirname(__DIR__) . '/src/lib/import.php';

$ok = 0;
$bad = 0;
function is_it(string $name, mixed $got, mixed $want): void
{
    global $ok, $bad;
    if ($got === $want) {
        $ok++;
        return;
    }
    $bad++;
    echo "FEHL  $name\n      erwartet: " . var_export($want, true) . "\n      erhalten: " . var_export($got, true) . "\n";
}

/** Zuordnung als Spaltenname => Ziel, für lesbare Vergleiche */
function mapping_named(array $header, array $extra = []): array
{
    $out = [];
    foreach (import_guess_mapping($header, $extra) as $i => $ziel) {
        $out[$header[$i]] = $ziel;
    }
    return $out;
}

/* ================================================================ */
echo "=== Zeichensatz ===\n";

$cp1252 = "Nachname;Stra\xDFe\nM\xFCller;Hauptstra\xDFe 1\n";          // so speichert Excel auf deutschem Windows
is_it('Windows-1252 wird UTF-8', import_to_utf8($cp1252), "Nachname;Straße\nMüller;Hauptstraße 1\n");

is_it('UTF-8-BOM wird entfernt', import_to_utf8("\xEF\xBB\xBFName;Ort\n"), "Name;Ort\n");
is_it('UTF-8 bleibt unveraendert', import_to_utf8("Müller\n"), "Müller\n");

$utf16 = "\xFF\xFE" . mb_convert_encoding("Name;Ort\r\nMüller;Köln\r\n", 'UTF-16LE', 'UTF-8');
is_it('UTF-16LE mit BOM (Outlook "Unicode")', import_to_utf8($utf16), "Name;Ort\nMüller;Köln\n");

is_it('Windows-Zeilenenden', import_to_utf8("a;b\r\nc;d\r\n"), "a;b\nc;d\n");
is_it('alte Mac-Zeilenenden', import_to_utf8("a;b\rc;d\r"), "a;b\nc;d\n");

/* ================================================================ */
echo "\n=== CSV zerlegen ===\n";

is_it('Semikolon erkannt', import_csv_delimiter("Name;Vorname;Ort\n"), ';');
is_it('Komma erkannt', import_csv_delimiter("First Name,Last Name,Company\n"), ',');
is_it('Tabulator erkannt', import_csv_delimiter("Name\tOrt\n"), "\t");
// Kommas innerhalb von Anführungszeichen dürfen nicht mitzählen
is_it('Komma im Zitat zaehlt nicht', import_csv_delimiter("\"Berg, Anna\";Ort;PLZ\n"), ';');

$csv = "Name;Notiz;Ort\n"
    . "Berg;\"zweizeilige\nBemerkung\";Köln\n"
    . "\n"                                       // Leerzeile
    . "Kurz\n"                                   // zu wenige Spalten
    . "Lang;a;b;c;d\n";                          // zu viele Spalten
$p = import_csv_parse($csv);
is_it('Kopfzeile', $p['header'], ['Name', 'Notiz', 'Ort']);
is_it('Leerzeile uebersprungen', count($p['rows']), 3);
is_it('mehrzeiliges Feld bleibt ganz', $p['rows'][0], ['Berg', "zweizeilige\nBemerkung", 'Köln']);
is_it('kurze Zeile aufgefuellt', $p['rows'][1], ['Kurz', '', '']);
is_it('lange Zeile gekuerzt', $p['rows'][2], ['Lang', 'a', 'b']);
is_it('Leerraum getrimmt', import_csv_parse("Name;Ort\n  Berg ; Köln \n")['rows'][0], ['Berg', 'Köln']);
is_it('verdoppelte Anfuehrungszeichen', import_csv_parse("Name\n\"THW \"\"OV\"\" Nord\"\n")['rows'][0], ['THW "OV" Nord']);

/* ================================================================ */
echo "\n=== Spaltenzuordnung ===\n";

// Selbstgebaute Excel-Liste, wie sie in jedem OV herumliegt
is_it('deutsche Excel-Liste', mapping_named(
    ['Anrede', 'Titel', 'Vorname', 'Name', 'Firma', 'Funktion', 'Straße', 'PLZ', 'Ort', 'Telefon', 'Mobiltelefon', 'E-Mail', 'Bemerkung']),
    ['Anrede' => 'anrede', 'Titel' => 'titel', 'Vorname' => 'vorname', 'Name' => 'nachname',
     'Firma' => 'organisation', 'Funktion' => 'position', 'Straße' => 'strasse', 'PLZ' => 'plz',
     'Ort' => 'ort', 'Telefon' => 'telefon', 'Mobiltelefon' => 'mobil', 'E-Mail' => 'email', 'Bemerkung' => 'notiz']);

is_it('Name allein ist der volle Name', mapping_named(['Name', 'E-Mail']),
    ['Name' => 'vollname', 'E-Mail' => 'email']);

// Google Contacts: "Name" ist hier der volle Name, weil es "Family Name" gibt
is_it('Google Contacts', mapping_named(
    ['Name', 'Given Name', 'Family Name', 'Organization 1 - Name', 'Organization 1 - Title',
     'E-mail 1 - Type', 'E-mail 1 - Value', 'Phone 1 - Type', 'Phone 1 - Value',
     'Address 1 - Street', 'Address 1 - City', 'Address 1 - Postal Code', 'Notes', 'Group Membership']),
    ['Name' => 'vollname', 'Given Name' => 'vorname', 'Family Name' => 'nachname',
     'Organization 1 - Name' => 'organisation', 'Organization 1 - Title' => 'position',
     'E-mail 1 - Type' => '', 'E-mail 1 - Value' => 'email', 'Phone 1 - Type' => '', 'Phone 1 - Value' => 'telefon',
     'Address 1 - Street' => 'strasse', 'Address 1 - City' => 'ort', 'Address 1 - Postal Code' => 'plz',
     'Notes' => 'notiz', 'Group Membership' => 'kategorie']);

// Outlook englisch, Ausschnitt mit den Stolperfallen "Car Phone", "Business Fax", "E-mail Display Name"
is_it('Outlook englisch', mapping_named(
    ['Title', 'First Name', 'Last Name', 'Company', 'Job Title', 'Business Street', 'Business City',
     'Business Postal Code', 'Business Country/Region', 'Business Fax', 'Business Phone', 'Car Phone',
     'Home Phone', 'Mobile Phone', 'E-mail Address', 'E-mail Display Name', 'Categories', 'Notes']),
    ['Title' => 'titel', 'First Name' => 'vorname', 'Last Name' => 'nachname', 'Company' => 'organisation',
     'Job Title' => 'position', 'Business Street' => 'strasse', 'Business City' => 'ort',
     'Business Postal Code' => 'plz', 'Business Country/Region' => 'land', 'Business Fax' => '',
     'Business Phone' => 'telefon', 'Car Phone' => '', 'Home Phone' => '', 'Mobile Phone' => 'mobil',
     'E-mail Address' => 'email', 'E-mail Display Name' => '', 'Categories' => 'kategorie', 'Notes' => 'notiz']);

is_it('Outlook deutsch', mapping_named(
    ['Anrede', 'Vorname', 'Nachname', 'Firma', 'Position', 'Straße geschäftlich', 'Ort geschäftlich',
     'Postleitzahl geschäftlich', 'Land/Region geschäftlich', 'Fax geschäftlich', 'Telefon geschäftlich',
     'Telefon geschäftlich 2', 'Mobiltelefon', 'E-Mail-Adresse', 'Kategorien', 'Notizen']),
    ['Anrede' => 'anrede', 'Vorname' => 'vorname', 'Nachname' => 'nachname', 'Firma' => 'organisation',
     'Position' => 'position', 'Straße geschäftlich' => 'strasse', 'Ort geschäftlich' => 'ort',
     'Postleitzahl geschäftlich' => 'plz', 'Land/Region geschäftlich' => 'land', 'Fax geschäftlich' => '',
     'Telefon geschäftlich' => 'telefon', 'Telefon geschäftlich 2' => '', 'Mobiltelefon' => 'mobil',
     'E-Mail-Adresse' => 'email', 'Kategorien' => 'kategorie', 'Notizen' => 'notiz']);

is_it('jedes Ziel nur einmal', mapping_named(['E-Mail', 'E-Mail privat', 'Telefon', 'Telefon 2']),
    ['E-Mail' => 'email', 'E-Mail privat' => '', 'Telefon' => 'telefon', 'Telefon 2' => '']);

$extra = ['mitglied-seit' => ['label' => 'Mitglied seit', 'type' => 'date'],
          'newsletter'    => ['label' => 'Newsletter erwünscht', 'type' => 'bool']];
is_it('Freifelder ueber Beschriftung und Schluessel', mapping_named(['Name', 'Mitglied seit', 'Newsletter'], $extra),
    ['Name' => 'vollname', 'Mitglied seit' => 'extra:mitglied-seit', 'Newsletter' => 'extra:newsletter']);

is_it('Unbekanntes bleibt frei', mapping_named(['Schuhgröße', 'Lieblingsfarbe']),
    ['Schuhgröße' => '', 'Lieblingsfarbe' => '']);

is_it('Zeile uebersetzen, Leeres faellt weg',
    import_csv_row(['Berg', '', 'Köln'], ['nachname', 'email', 'ort']),
    ['nachname' => 'Berg', 'ort' => 'Köln']);

/* ================================================================ */
echo "\n=== vCard 3.0 ===\n";

$vcf3 = "BEGIN:VCARD\r\n"
    . "VERSION:3.0\r\n"
    . "N:Berg;Anna;;Frau Dr.;\r\n"
    . "FN:Dr. Anna Berg\r\n"
    . "ORG:Stadt Musterstadt;Ordnungsamt\r\n"
    . "TITLE:Leiterin\r\n"
    . "EMAIL;TYPE=HOME:privat@example.org\r\n"
    . "item1.EMAIL;TYPE=WORK,PREF:Anna.Berg@Musterstadt.de\r\n"
    . "TEL;TYPE=HOME:0221 111\r\n"
    . "TEL;TYPE=WORK,VOICE:0221 222\r\n"
    . "TEL;TYPE=CELL:0170 333\r\n"
    . "TEL;TYPE=FAX:0221 999\r\n"
    . "ADR;TYPE=HOME:;;Gartenweg 5;Musterstadt;;12345;Deutschland\r\n"
    . "ADR;TYPE=WORK:;;Rathausplatz 1;Musterstadt;;12345;Deutschland\r\n"
    . "NOTE:Erste Zeile\\nzweite Zeile\\, mit Komma und einer sehr langen Fortsetzung, die \r\n"
    . " auf die naechste Zeile umgebrochen wurde\r\n"
    . "CATEGORIES:Kommune,Politik\r\n"
    . "PHOTO;ENCODING=b;TYPE=JPEG:/9j/4AAQSkZJRgABAQ\r\n"
    . "END:VCARD\r\n"
    . "BEGIN:VCARD\r\n"
    . "VERSION:3.0\r\n"
    . "FN:Freiwillige Feuerwehr Musterstadt\r\n"
    . "ORG:Freiwillige Feuerwehr Musterstadt\r\n"
    . "EMAIL:wehr@example.org\r\n"
    . "END:VCARD\r\n"
    . "BEGIN:VCARD\r\n"
    . "VERSION:4.0\r\n"
    . "FN:Peter Stein\r\n"
    . "TEL;VALUE=uri;TYPE=cell:tel:+49-170-444\r\n"
    . "END:VCARD\r\n";

$karten = import_vcard_parse(import_to_utf8($vcf3));
is_it('drei Karten', count($karten), 3);

$a = $karten[0];
is_it('Nachname aus N', $a['nachname'], 'Berg');
is_it('Vorname aus N', $a['vorname'], 'Anna');
is_it('Anrede aus Praefix', $a['anrede'], 'Frau');
is_it('Titel aus Praefix', $a['titel'], 'Dr.');
is_it('Organisation mit Abteilung', $a['organisation'], 'Stadt Musterstadt, Ordnungsamt');
is_it('Funktion aus TITLE', $a['position'], 'Leiterin');
is_it('bevorzugte E-Mail gewinnt, Gruppenpraefix entfernt', $a['email'], 'Anna.Berg@Musterstadt.de');
is_it('dienstliches Telefon gewinnt, international geschrieben', $a['telefon'], '+49 221222');
is_it('Mobil erkannt und international geschrieben', $a['mobil'], '+49 170 333');
is_it('dienstliche Anschrift gewinnt', [$a['strasse'], $a['plz'], $a['ort'], $a['land']],
    ['Rathausplatz 1', '12345', 'Musterstadt', 'Deutschland']);
is_it('Notiz: Maskierungen und Zeilenumbruch aufgeloest', $a['notiz'],
    "Erste Zeile\nzweite Zeile, mit Komma und einer sehr langen Fortsetzung, die auf die naechste Zeile umgebrochen wurde");
is_it('erste Kategorie', $a['kategorie'], 'Kommune');
is_it('Foto nicht uebernommen', array_key_exists('photo', $a), false);

$b = $karten[1];
is_it('reine Organisation ohne Namensfeld', [$b['organisation'], isset($b['vollname']), isset($b['nachname'])],
    ['Freiwillige Feuerwehr Musterstadt', false, false]);

$c = $karten[2];
is_it('vCard 4: nur FN wird zum vollen Namen', $c['vollname'], 'Peter Stein');
is_it('vCard 4: tel:-URI und kleingeschriebener Typ', $c['mobil'], '+49 170 444');

/* ================================================================ */
echo "\n=== vCard 2.1 (altes Outlook, Handys) ===\n";

$vcf21 = "BEGIN:VCARD\r\n"
    . "VERSION:2.1\r\n"
    . "N;CHARSET=ISO-8859-1;ENCODING=QUOTED-PRINTABLE:M=FCller;J=FCrgen;;Herr;\r\n"
    . "ORG;CHARSET=ISO-8859-1;ENCODING=QUOTED-PRINTABLE:THW Ortsverband K=F6ln-Porz, sehr langer Name der =\r\n"
    . "umgebrochen ist\r\n"
    . "TEL;CELL;PREF:0171 555\r\n"
    . "TEL;WORK:0221 666\r\n"
    . "EMAIL;INTERNET:j.mueller@example.org\r\n"
    . "END:VCARD\r\n";

$k = import_vcard_parse(import_to_utf8($vcf21));
is_it('eine Karte', count($k), 1);
is_it('Quoted-Printable mit Latin-1 dekodiert', [$k[0]['nachname'], $k[0]['vorname']], ['Müller', 'Jürgen']);
is_it('Anrede Herr', $k[0]['anrede'], 'Herr');
is_it('weicher Zeilenumbruch zusammengefuegt', $k[0]['organisation'],
    'THW Ortsverband Köln-Porz, sehr langer Name der umgebrochen ist');
is_it('Typen ohne TYPE= erkannt', [$k[0]['mobil'], $k[0]['telefon']], ['+49 171 555', '+49 221666']);
is_it('E-Mail', $k[0]['email'], 'j.mueller@example.org');

is_it('vCard erkannt', import_is_vcard("\n  BEGIN:VCARD\nEND:VCARD"), true);
is_it('CSV ist keine vCard', import_is_vcard("Name;Ort\n"), false);
is_it('kaputte Karte ohne END liefert nichts', import_vcard_parse("BEGIN:VCARD\nN:Berg;Anna\n"), []);

/* ================================================================ */
echo "\n=== Feldwerte -> Kontakt ===\n";

is_it('Name mit Leerzeichen', import_split_name('Anna Maria Berg'), ['Anna Maria', 'Berg']);
is_it('Name mit Komma', import_split_name('Berg, Anna'), ['Anna', 'Berg']);
is_it('nur ein Wort', import_split_name('Berg'), ['', 'Berg']);
is_it('Leerraum zusammengezogen', import_split_name('  Anna    Berg '), ['Anna', 'Berg']);

is_it('Anrede Herr', import_norm_anrede('Hr.'), 'Herr');
is_it('Anrede Frau', import_norm_anrede('Mrs'), 'Frau');
is_it('Anrede aus Geschlecht', import_norm_anrede('w'), 'Frau');
is_it('unbekannte Anrede wird leer', import_norm_anrede('Firma'), '');

$kontakt = import_fields_to_contact([
    'vollname' => 'Anna Berg', 'anrede' => 'frau', 'email' => 'Anna.Berg@Example.ORG',
    'organisation' => str_repeat('x', 200), 'kategorie' => ' Kommune ',
    'extra:newsletter' => ' ja ', 'notiz' => 'Hinweis',
]);
is_it('voller Name geteilt', [$kontakt['vorname'], $kontakt['nachname']], ['Anna', 'Berg']);
is_it('Anrede vereinheitlicht', $kontakt['anrede'], 'Frau');
is_it('E-Mail kleingeschrieben', $kontakt['email'], 'anna.berg@example.org');
is_it('ueberlange Organisation gekuerzt', mb_strlen($kontakt['organisation']), 150);
is_it('Kategorie als Rohtext', $kontakt['kategorie_text'], 'Kommune');
is_it('Freifeld gesammelt', $kontakt['_extra'], ['newsletter' => 'ja']);
is_it('ungueltige E-Mail verworfen', import_fields_to_contact(['nachname' => 'X', 'email' => 'kein at'])['email'], '');
is_it('eigener Nachname geht vor vollem Namen',
    import_fields_to_contact(['vollname' => 'Dr. Anna Berg', 'nachname' => 'Berg', 'vorname' => 'Anna'])['nachname'], 'Berg');

is_it('brauchbar mit Nachname', import_contact_usable(import_fields_to_contact(['nachname' => 'Berg'])), true);
is_it('brauchbar mit Organisation', import_contact_usable(import_fields_to_contact(['organisation' => 'Presse'])), true);
is_it('unbrauchbar nur mit Telefon', import_contact_usable(import_fields_to_contact(['telefon' => '123'])), false);

/* ================================================================ */
echo "\n=== Dublettenschluessel ===\n";

is_it('E-Mail unabhaengig von Schreibweise',
    import_dedupe_keys(['email' => 'Anna.Berg@X.de'])[0], import_dedupe_keys(['email' => 'anna.berg@x.de '])[0]);
is_it('Name mit Umlaut und Leerraum gleich',
    import_dedupe_keys(['vorname' => 'Jürgen', 'nachname' => 'Müller'])[0],
    import_dedupe_keys(['vorname' => 'juergen ', 'nachname' => 'MUELLER'])[0]);
is_it('verschiedene Organisation ist keine Dublette',
    import_dedupe_keys(['nachname' => 'Berg', 'organisation' => 'Stadt']) === import_dedupe_keys(['nachname' => 'Berg', 'organisation' => 'Kreis']),
    false);
is_it('ganz leer ergibt keine Schluessel', import_dedupe_keys([]), []);

echo "\n$ok bestanden, $bad fehlgeschlagen\n";
exit($bad > 0 ? 1 : 0);
