<?php
declare(strict_types=1);

/**
 * Kontakt-Import aus CSV und vCard.
 *
 * Alles hier ist bewusst frei von Datenbankzugriffen: Zeichensatz erkennen,
 * Dateien zerlegen, Spalten zuordnen und Zeilen in Kontaktfelder übersetzen.
 * Die Seite contacts_import.php kümmert sich um Dubletten und das Speichern.
 */

/** Felder, in die importiert werden kann – Schlüssel wie in der Tabelle contacts */
function import_targets(): array
{
    return [
        'anrede'       => 'Anrede',
        'titel'        => 'Titel',
        'vorname'      => 'Vorname',
        'nachname'     => 'Nachname',
        'vollname'     => 'Name (Vor- und Nachname in einer Spalte)',
        'organisation' => 'Organisation',
        'position'     => 'Funktion / Position',
        'kategorie'    => 'Kategorie',
        'email'        => 'E-Mail',
        'telefon'      => 'Telefon',
        'mobil'        => 'Mobil',
        'strasse'      => 'Straße',
        'plz'          => 'PLZ',
        'ort'          => 'Ort',
        'land'         => 'Land',
        'anschreiben'  => 'Briefanrede',
        'notiz'        => 'Notiz',
    ];
}

/* ==================================================================== */
/* Zeichensatz                                                           */
/* ==================================================================== */

/**
 * Rohdaten nach UTF-8 bringen. Excel auf deutschem Windows speichert CSV als
 * Windows-1252, Outlook "Unicode" als UTF-16 mit BOM, alles Neuere als UTF-8.
 */
function import_to_utf8(string $raw): string
{
    // Kein vorzeitiges return: auch UTF-16 braucht danach einheitliche Zeilenenden
    if (str_starts_with($raw, "\xFF\xFE")) {
        $raw = (string)mb_convert_encoding(substr($raw, 2), 'UTF-8', 'UTF-16LE');
    } elseif (str_starts_with($raw, "\xFE\xFF")) {
        $raw = (string)mb_convert_encoding(substr($raw, 2), 'UTF-8', 'UTF-16BE');
    } elseif (str_starts_with($raw, "\xEF\xBB\xBF")) {
        $raw = substr($raw, 3);
    }
    if (!mb_check_encoding($raw, 'UTF-8')) {
        $raw = (string)mb_convert_encoding($raw, 'UTF-8', 'Windows-1252');
    }
    // Zeilenenden vereinheitlichen
    return str_replace(["\r\n", "\r"], "\n", $raw);
}

/** Ist das eine vCard-Datei? */
function import_is_vcard(string $utf8): bool
{
    return (bool)preg_match('/^\s*BEGIN:VCARD/im', $utf8);
}

/* ==================================================================== */
/* CSV                                                                   */
/* ==================================================================== */

/** Trennzeichen anhand der Kopfzeile schätzen – Anführungszeichen werden übergangen */
function import_csv_delimiter(string $utf8): string
{
    $zeile = strtok($utf8, "\n") ?: '';
    $ohneZitate = (string)preg_replace('/"[^"]*"/', '', $zeile);
    $zaehler = [
        ';'  => substr_count($ohneZitate, ';'),
        ','  => substr_count($ohneZitate, ','),
        "\t" => substr_count($ohneZitate, "\t"),
    ];
    arsort($zaehler);
    $best = (string)array_key_first($zaehler);
    return $zaehler[$best] > 0 ? $best : ';';
}

/**
 * CSV zerlegen. Mehrzeilige Felder in Anführungszeichen bleiben zusammen.
 * Rückgabe: ['delimiter' => ..., 'header' => [...], 'rows' => [[...], ...]]
 * Leere Zeilen fallen weg; jede Zeile hat so viele Spalten wie die Kopfzeile.
 */
function import_csv_parse(string $utf8, ?string $delimiter = null): array
{
    $delimiter ??= import_csv_delimiter($utf8);

    $fh = fopen('php://temp', 'r+b');
    fwrite($fh, $utf8);
    rewind($fh);

    $header = null;
    $rows = [];
    while (($zeile = fgetcsv($fh, 0, $delimiter, '"', '')) !== false) {
        if ($zeile === [null] || implode('', array_map('strval', $zeile)) === '') {
            continue;
        }
        $zeile = array_map(static fn($v) => trim((string)$v), $zeile);
        if ($header === null) {
            $header = $zeile;
            continue;
        }
        // Auf die Breite der Kopfzeile bringen
        $zeile = array_slice(array_pad($zeile, count($header), ''), 0, count($header));
        $rows[] = $zeile;
    }
    fclose($fh);

    return ['delimiter' => $delimiter, 'header' => $header ?? [], 'rows' => $rows];
}

/** Spaltenname auf einen Vergleichsschlüssel bringen: "E-Mail-Adresse" -> "emailadresse" */
function import_norm(string $v): string
{
    $v = strtr(mb_strtolower(trim($v)), ['ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss']);
    return (string)preg_replace('/[^a-z0-9]/', '', $v);
}

/**
 * Welche Spaltennamen passen zu welchem Feld? Zuerst wird exakt verglichen,
 * danach nach Bestandteilen. Die Reihenfolge ist wichtig: "Mobiltelefon"
 * muss als Mobil erkannt werden, bevor "telefon" greift.
 */
function import_synonyms(): array
{
    return [
        'email'        => [['email', 'emailadresse', 'emailaddress', 'mail', 'email1', 'email1value', 'emailadressegeschaeftlich'],
                           ['email', 'mail']],
        'mobil'        => [['mobil', 'mobile', 'mobiltelefon', 'handy', 'mobilephone', 'cell', 'cellphone', 'mobilnummer', 'handynummer'],
                           ['mobil', 'handy', 'mobile', 'cell']],
        'telefon'      => [['telefon', 'telephone', 'phone', 'tel', 'festnetz', 'telefonnummer', 'businessphone',
                            'telefongeschaeftlich', 'geschaeftlich', 'homephone', 'phone1value', 'rufnummer'],
                           ['telefon', 'phone', 'rufnummer']],
        'plz'          => [['plz', 'postleitzahl', 'zip', 'zipcode', 'postalcode', 'businesspostalcode'],
                           ['postleitzahl', 'postalcode', 'plz']],
        'strasse'      => [['strasse', 'str', 'street', 'adresse', 'anschrift', 'businessstreet', 'homestreet', 'address1street'],
                           ['strasse', 'street']],
        'ort'          => [['ort', 'stadt', 'city', 'wohnort', 'businesscity', 'homecity', 'address1city', 'gemeinde',
                            'ortgeschaeftlich', 'ortprivat'],
                           ['city']],
        'land'         => [['land', 'country', 'countryregion', 'businesscountryregion', 'staat', 'address1country',
                            'landregiongeschaeftlich', 'landregionprivat', 'landregion'],
                           ['country']],
        'vorname'      => [['vorname', 'firstname', 'givenname', 'first', 'rufname', 'vornamen'],
                           ['vorname', 'firstname', 'givenname']],
        'nachname'     => [['nachname', 'lastname', 'familyname', 'surname', 'familienname', 'zuname'],
                           ['nachname', 'lastname', 'familyname']],
        'organisation' => [['organisation', 'organization', 'firma', 'company', 'unternehmen', 'behoerde', 'einrichtung',
                            'verein', 'dienststelle', 'institution', 'organisationsname', 'organization1name'],
                           ['firma', 'company', 'organisation', 'organization']],
        'position'     => [['position', 'funktion', 'jobtitle', 'beruf', 'rolle', 'role', 'dienstbezeichnung', 'amt',
                            'stelle', 'organization1title'],
                           ['funktion', 'position', 'jobtitle']],
        'anrede'       => [['anrede', 'salutation', 'prefix', 'nameprefix', 'geschlecht'], []],
        'titel'        => [['titel', 'title', 'akademischertitel', 'grad'], []],
        'kategorie'    => [['kategorie', 'kategorien', 'category', 'categories', 'gruppe', 'gruppen', 'groupmembership'],
                           ['kategorie']],
        'notiz'        => [['notiz', 'notizen', 'notes', 'note', 'bemerkung', 'bemerkungen', 'kommentar', 'anmerkung', 'hinweis'],
                           ['notiz', 'bemerkung']],
        'anschreiben'  => [['briefanrede', 'anschreiben', 'salutationletter'], ['briefanrede']],
    ];
}

/**
 * Spalten automatisch zuordnen. Jedes Ziel wird höchstens einmal vergeben.
 * Frei definierte Felder werden über Schlüssel oder Beschriftung erkannt.
 *
 * Rückgabe: Spaltenindex => Ziel ('' = nicht übernehmen, 'extra:<schluessel>')
 */
function import_guess_mapping(array $header, array $extraFields = []): array
{
    $norm = array_map('import_norm', $header);
    $map = array_fill(0, count($header), '');
    $vergeben = [];

    $setze = static function (int $i, string $ziel) use (&$map, &$vergeben): void {
        $map[$i] = $ziel;
        $vergeben[$ziel] = true;
    };

    // "Name" bedeutet je nach Quelle Verschiedenes: neben einer eigenen
    // Nachname-Spalte (Google) ist es der volle Name, neben einer Vorname-
    // Spalte (deutsche Listen) der Nachname, allein der volle Name.
    $hatVorname = (bool)array_intersect($norm, import_synonyms()['vorname'][0]);
    $hatNachname = (bool)array_intersect($norm, import_synonyms()['nachname'][0]);

    // 1. Durchgang: exakte Treffer
    foreach ($norm as $i => $n) {
        if ($n === '') {
            continue;
        }
        if ($n === 'name') {
            $ziel = (!$hatNachname && $hatVorname) ? 'nachname' : 'vollname';
            if (!isset($vergeben[$ziel])) {
                $setze($i, $ziel);
            }
            continue;
        }
        foreach ($extraFields as $key => $def) {
            if (($n === import_norm($key) || $n === import_norm($def['label'])) && !isset($vergeben['extra:' . $key])) {
                $setze($i, 'extra:' . $key);
                continue 2;
            }
        }
        foreach (import_synonyms() as $ziel => [$exakt]) {
            if (!isset($vergeben[$ziel]) && in_array($n, $exakt, true)) {
                $setze($i, $ziel);
                continue 2;
            }
        }
    }

    // 2. Durchgang: Bestandteile, nur für noch freie Spalten
    foreach ($norm as $i => $n) {
        if ($n === '' || $map[$i] !== '') {
            continue;
        }
        foreach (import_synonyms() as $ziel => [, $teile]) {
            if (isset($vergeben[$ziel])) {
                continue;
            }
            foreach ($teile as $teil) {
                if (str_contains($n, $teil)) {
                    $setze($i, $ziel);
                    continue 3;
                }
            }
        }
    }

    return $map;
}

/** CSV-Zeile anhand der Zuordnung in Feldwerte übersetzen */
function import_csv_row(array $zeile, array $map): array
{
    $out = [];
    foreach ($map as $i => $ziel) {
        if ($ziel === '' || !isset($zeile[$i])) {
            continue;
        }
        $wert = trim((string)$zeile[$i]);
        if ($wert === '') {
            continue;
        }
        // Erster Treffer gewinnt, falls ein Ziel doch mehrfach gewählt wurde
        $out[$ziel] ??= $wert;
    }
    return $out;
}

/* ==================================================================== */
/* vCard                                                                 */
/* ==================================================================== */

/**
 * vCard-Datei zerlegen (Versionen 2.1, 3.0 und 4.0).
 * Rückgabe: Liste von Feldwerten im selben Format wie import_csv_row().
 */
function import_vcard_parse(string $utf8): array
{
    $karten = [];
    $aktuell = null;

    foreach (import_vcard_lines($utf8) as $zeile) {
        if (preg_match('/^BEGIN:VCARD$/i', $zeile)) {
            $aktuell = [];
            continue;
        }
        if (preg_match('/^END:VCARD$/i', $zeile)) {
            if ($aktuell !== null) {
                $karten[] = import_vcard_to_fields($aktuell);
            }
            $aktuell = null;
            continue;
        }
        if ($aktuell === null) {
            continue;
        }
        $eigenschaft = import_vcard_property($zeile);
        if ($eigenschaft) {
            $aktuell[] = $eigenschaft;
        }
    }

    return array_values(array_filter($karten));
}

/**
 * Logische Zeilen bilden: Folgezeilen mit führendem Leerzeichen oder Tab
 * gehören zur vorigen (RFC 6350), bei Quoted-Printable (vCard 2.1) endet
 * eine fortgesetzte Zeile auf "=".
 */
function import_vcard_lines(string $utf8): array
{
    $out = [];
    foreach (explode("\n", $utf8) as $zeile) {
        if ($out && ($zeile !== '' && ($zeile[0] === ' ' || $zeile[0] === "\t"))) {
            $out[count($out) - 1] .= substr($zeile, 1);
            continue;
        }
        $letzte = $out ? $out[count($out) - 1] : '';
        if ($out && preg_match('/QUOTED-PRINTABLE/i', strstr($letzte, ':', true) ?: '')
            && str_ends_with($letzte, '=')) {
            $out[count($out) - 1] = substr($letzte, 0, -1) . $zeile;
            continue;
        }
        if (trim($zeile) !== '') {
            // Nur das Zeilenende abschneiden: ein Leerzeichen vor einer Faltung
            // gehört zum Wert und darf beim Zusammenfügen nicht verloren gehen
            $out[] = rtrim($zeile, "\r");
        }
    }
    return $out;
}

/** Eine Eigenschaftszeile zerlegen: Name, Parameter, Wert (dekodiert) */
function import_vcard_property(string $zeile): ?array
{
    $pos = strpos($zeile, ':');
    if ($pos === false) {
        return null;
    }
    $kopf = substr($zeile, 0, $pos);
    $wert = substr($zeile, $pos + 1);

    $teile = explode(';', $kopf);
    $name = strtoupper((string)array_shift($teile));
    // Gruppenpräfix wie "item1.EMAIL" entfernen
    if (str_contains($name, '.')) {
        $name = substr($name, strrpos($name, '.') + 1);
    }

    $typen = [];
    $encoding = '';
    $charset = '';
    foreach ($teile as $p) {
        if (str_contains($p, '=')) {
            [$k, $v] = explode('=', $p, 2);
            $k = strtoupper(trim($k));
            $v = trim($v, " \"");
            if ($k === 'TYPE') {
                foreach (explode(',', $v) as $t) {
                    $typen[] = strtoupper(trim($t));
                }
            } elseif ($k === 'ENCODING') {
                $encoding = strtoupper($v);
            } elseif ($k === 'CHARSET') {
                $charset = strtoupper($v);
            } elseif ($k === 'PREF') {
                $typen[] = 'PREF';
            }
        } else {
            // vCard 2.1: Typen ohne "TYPE=", etwa "TEL;CELL;WORK"
            $bare = strtoupper(trim($p));
            if ($bare === 'QUOTED-PRINTABLE') {
                $encoding = $bare;
            } elseif ($bare !== '') {
                $typen[] = $bare;
            }
        }
    }

    if ($encoding === 'QUOTED-PRINTABLE') {
        $wert = quoted_printable_decode($wert);
        if ($charset !== '' && $charset !== 'UTF-8') {
            $wert = (string)mb_convert_encoding($wert, 'UTF-8', $charset);
        }
    } elseif ($encoding === 'B' || $encoding === 'BASE64') {
        // Fotos und Ähnliches werden nicht übernommen
        return null;
    }
    if (!mb_check_encoding($wert, 'UTF-8')) {
        $wert = (string)mb_convert_encoding($wert, 'UTF-8', 'Windows-1252');
    }

    return ['name' => $name, 'typen' => $typen, 'wert' => $wert];
}

/** Strukturierten Wert an unmaskierten Semikolons teilen und Maskierungen auflösen */
function import_vcard_split(string $wert): array
{
    $teile = preg_split('/(?<!\\\\);/', $wert) ?: [];
    return array_map('import_vcard_unescape', $teile);
}

function import_vcard_unescape(string $v): string
{
    return trim(strtr($v, ['\\n' => "\n", '\\N' => "\n", '\\,' => ',', '\\;' => ';', '\\\\' => '\\']));
}

/** Eigenschaften einer Karte in Kontaktfelder übersetzen */
function import_vcard_to_fields(array $eigenschaften): array
{
    $f = [];
    $telefon = [];
    $mobil = [];
    $email = [];
    $adressen = [];

    foreach ($eigenschaften as $e) {
        $typen = $e['typen'];
        switch ($e['name']) {
            case 'N':
                $n = import_vcard_split($e['wert']);
                if (($n[0] ?? '') !== '') {
                    $f['nachname'] = $n[0];
                }
                if (($n[1] ?? '') !== '') {
                    $f['vorname'] = $n[1];
                }
                $praefix = $n[3] ?? '';
                if ($praefix !== '') {
                    // "Herr Dr." oder "Frau" oder "Dr." – Anrede und Titel trennen
                    foreach (preg_split('/\s+/', $praefix) ?: [] as $wort) {
                        $klein = mb_strtolower(rtrim($wort, '.'));
                        if (in_array($klein, ['herr', 'mr', 'hr'], true)) {
                            $f['anrede'] ??= 'Herr';
                        } elseif (in_array($klein, ['frau', 'mrs', 'ms', 'fr'], true)) {
                            $f['anrede'] ??= 'Frau';
                        } elseif ($wort !== '') {
                            $f['titel'] = trim(($f['titel'] ?? '') . ' ' . $wort);
                        }
                    }
                }
                break;

            case 'FN':
                $f['_fn'] = import_vcard_unescape($e['wert']);
                break;

            case 'ORG':
                $org = array_values(array_filter(import_vcard_split($e['wert']), static fn($x) => $x !== ''));
                if ($org) {
                    $f['organisation'] = implode(', ', $org);
                }
                break;

            case 'TITLE':
                $f['position'] = import_vcard_unescape($e['wert']);
                break;

            case 'ROLE':
                $f['position'] ??= import_vcard_unescape($e['wert']);
                break;

            case 'EMAIL':
                $wert = import_vcard_unescape($e['wert']);
                if ($wert !== '') {
                    in_array('PREF', $typen, true) ? array_unshift($email, $wert) : $email[] = $wert;
                }
                break;

            case 'TEL':
                $wert = import_vcard_unescape(preg_replace('/^tel:/i', '', $e['wert']) ?? '');
                if ($wert === '' || in_array('FAX', $typen, true)) {
                    break;
                }
                if (in_array('CELL', $typen, true)) {
                    $mobil[] = $wert;
                } elseif (in_array('WORK', $typen, true) || in_array('PREF', $typen, true)) {
                    array_unshift($telefon, $wert);
                } else {
                    $telefon[] = $wert;
                }
                break;

            case 'ADR':
                $a = import_vcard_split($e['wert']);
                $adresse = [
                    'strasse' => $a[2] ?? '',
                    'ort'     => $a[3] ?? '',
                    'plz'     => $a[5] ?? '',
                    'land'    => $a[6] ?? '',
                ];
                if (in_array('WORK', $typen, true) || in_array('PREF', $typen, true)) {
                    array_unshift($adressen, $adresse);
                } else {
                    $adressen[] = $adresse;
                }
                break;

            case 'NOTE':
                $f['notiz'] = import_vcard_unescape($e['wert']);
                break;

            case 'CATEGORIES':
                $kat = import_vcard_split(str_replace(',', ';', $e['wert']));
                if (($kat[0] ?? '') !== '') {
                    $f['kategorie'] = $kat[0];
                }
                break;
        }
    }

    if ($email) {
        $f['email'] = $email[0];
    }
    if ($telefon) {
        $f['telefon'] = $telefon[0];
    }
    if ($mobil) {
        $f['mobil'] = $mobil[0];
    }
    if ($adressen) {
        foreach ($adressen[0] as $k => $v) {
            if ($v !== '') {
                $f[$k] = $v;
            }
        }
    }

    // Ohne N-Eigenschaft den Anzeigenamen aufteilen
    if (!isset($f['nachname']) && !isset($f['vorname']) && isset($f['_fn'])
        && $f['_fn'] !== ($f['organisation'] ?? null)) {
        $f['vollname'] = $f['_fn'];
    }
    unset($f['_fn']);

    return array_filter($f, static fn($v) => $v !== '');
}

/* ==================================================================== */
/* Feldwerte -> Kontakt                                                  */
/* ==================================================================== */

/** "Anna Maria Berg" -> ['Anna Maria', 'Berg'], "Berg, Anna" -> ['Anna', 'Berg'] */
function import_split_name(string $name): array
{
    $name = trim((string)preg_replace('/\s+/', ' ', $name));
    if ($name === '') {
        return ['', ''];
    }
    if (str_contains($name, ',')) {
        [$nach, $vor] = array_map('trim', explode(',', $name, 2));
        return [$vor, $nach];
    }
    $pos = strrpos($name, ' ');
    return $pos === false ? ['', $name] : [substr($name, 0, $pos), substr($name, $pos + 1)];
}

/** Anrede-Freitext vereinheitlichen */
function import_norm_anrede(string $v): string
{
    return match (mb_strtolower(rtrim(trim($v), '.'))) {
        'herr', 'hr', 'mr', 'm', 'männlich', 'maennlich' => 'Herr',
        'frau', 'fr', 'mrs', 'ms', 'w', 'weiblich' => 'Frau',
        default => '',
    };
}

/**
 * Feldwerte in einen speicherbaren Kontakt übersetzen.
 * Kategorie und Zusatzfelder bleiben als Rohwert erhalten – deren Auflösung
 * gegen die Auswahlliste braucht die Datenbank und passiert beim Speichern.
 */
function import_fields_to_contact(array $f): array
{
    if (isset($f['vollname']) && !isset($f['nachname'])) {
        [$vor, $nach] = import_split_name($f['vollname']);
        $f['vorname'] ??= $vor;
        $f['nachname'] = $nach;
    }

    $laengen = [
        'anrede' => 30, 'titel' => 40, 'vorname' => 80, 'nachname' => 80,
        'organisation' => 150, 'position' => 150, 'email' => 150,
        'telefon' => 60, 'mobil' => 60, 'strasse' => 150, 'plz' => 15,
        'ort' => 100, 'land' => 60, 'anschreiben' => 150,
    ];

    $c = [];
    foreach ($laengen as $feld => $max) {
        $c[$feld] = mb_substr(trim((string)($f[$feld] ?? '')), 0, $max);
    }
    $c['anrede'] = import_norm_anrede($c['anrede']);
    $c['email'] = mb_strtolower($c['email']);
    if ($c['email'] !== '' && !filter_var($c['email'], FILTER_VALIDATE_EMAIL)) {
        $c['email'] = '';
    }
    $c['notiz'] = trim((string)($f['notiz'] ?? ''));
    $c['kategorie_text'] = trim((string)($f['kategorie'] ?? ''));

    $extra = [];
    foreach ($f as $k => $v) {
        if (str_starts_with((string)$k, 'extra:')) {
            $extra[substr((string)$k, 6)] = trim((string)$v);
        }
    }
    $c['_extra'] = $extra;

    return $c;
}

/** Hat die Zeile genug, um ein Kontakt zu sein? */
function import_contact_usable(array $c): bool
{
    return $c['nachname'] !== '' || $c['organisation'] !== '';
}

/**
 * Schlüssel für die Dublettenprüfung. Eine E-Mail-Adresse ist das
 * verlässlichste Merkmal; ohne sie zählt der Name samt Organisation.
 */
function import_dedupe_keys(array $c): array
{
    $keys = [];
    if (trim((string)($c['email'] ?? '')) !== '') {
        $keys[] = 'm:' . mb_strtolower(trim((string)$c['email']));
    }
    $name = import_norm((string)($c['vorname'] ?? '')) . '|' . import_norm((string)($c['nachname'] ?? ''))
        . '|' . import_norm((string)($c['organisation'] ?? ''));
    if ($name !== '||') {
        $keys[] = 'n:' . $name;
    }
    return $keys;
}

/* ==================================================================== */
/* Zuordnung gegen Auswahllisten und Zusatzfelder                        */
/* ==================================================================== */

/**
 * Freitext einer Auswahlliste zuordnen – bewusst streng.
 *
 * Erlaubt sind nur ein gleicher Name, ein gleicher Schlüssel oder ein
 * Name, der mit dem Text beginnt ("Kommune" passt zu "Kommune / Verwaltung").
 * Teilstrings mitten im Wort zählen nicht: sonst landete "Mitglieder" wegen
 * der Buchstaben "it" in der Kategorie IT.
 *
 * $items: Listeneinträge mit id, label, slug
 */
function import_match_list(string $text, array $items): ?int
{
    $n = import_norm($text);
    if ($n === '') {
        return null;
    }
    foreach ($items as $i) {
        if ($n === import_norm((string)$i['label']) || $n === import_norm((string)$i['slug'])) {
            return (int)$i['id'];
        }
    }
    if (mb_strlen($n) >= 4) {
        foreach ($items as $i) {
            if (str_starts_with(import_norm((string)$i['label']), $n)) {
                return (int)$i['id'];
            }
        }
    }
    return null;
}

/** Wert eines Zusatzfeldes passend zu seinem Typ aufbereiten */
function import_extra_value(string $wert, string $typ): string
{
    $wert = trim($wert);
    if ($wert === '') {
        return '';
    }
    switch ($typ) {
        case 'bool':
            return in_array(mb_strtolower($wert), ['1', 'ja', 'j', 'yes', 'y', 'x', 'true', 'wahr', 'on'], true) ? '1' : '0';
        case 'date':
            if (preg_match('/^(\d{1,2})\.(\d{1,2})\.(\d{4})$/', $wert, $m)) {
                return sprintf('%04d-%02d-%02d', (int)$m[3], (int)$m[2], (int)$m[1]);
            }
            return preg_match('/^\d{4}-\d{2}-\d{2}$/', $wert) ? $wert : '';
        case 'number':
            $zahl = str_replace([' ', "\xc2\xa0"], '', $wert);
            if (str_contains($zahl, ',')) {
                $zahl = str_replace(['.', ','], ['', '.'], $zahl);
            }
            return is_numeric($zahl) ? $zahl : '';
        default:
            return $wert;
    }
}

/* ==================================================================== */
/* Entscheidung je Zeile                                                  */
/* ==================================================================== */

const IMPORT_STRATEGIEN = [
    'ueberspringen'  => 'Vorhandene Kontakte überspringen',
    'ergaenzen'      => 'Vorhandene Kontakte ergänzen (nur leere Felder füllen)',
    'ueberschreiben' => 'Vorhandene Kontakte überschreiben',
    'neu'            => 'Immer neu anlegen',
];

/**
 * Für jede Zeile entscheiden, was passiert. Rein rechnerisch – die
 * vorhandenen Kontakte kommen als Schlüssel => id herein.
 *
 * Aktionen: neu, ergaenzen, ueberschreiben, ueberspringen, fehler
 * Doppelte Einträge innerhalb der Datei werden immer übersprungen, auch
 * wenn "immer neu anlegen" gewählt ist – das ist praktisch nie gewollt.
 */
function import_decide(array $zeilen, array $index, string $strategie): array
{
    $strategie = array_key_exists($strategie, IMPORT_STRATEGIEN) ? $strategie : 'ueberspringen';
    $plan = [];
    $gesehen = [];

    foreach ($zeilen as $nr => $felder) {
        $kontakt = import_fields_to_contact($felder);
        $eintrag = ['zeile' => $nr + 1, 'kontakt' => $kontakt, 'aktion' => 'neu', 'id' => null, 'grund' => ''];

        if (!import_contact_usable($kontakt)) {
            $eintrag['aktion'] = 'fehler';
            $eintrag['grund'] = 'weder Nachname noch Organisation';
            $plan[] = $eintrag;
            continue;
        }

        $keys = import_dedupe_keys($kontakt);
        foreach ($keys as $k) {
            if (isset($gesehen[$k])) {
                $eintrag['aktion'] = 'ueberspringen';
                $eintrag['grund'] = 'doppelt in der Datei, wie Zeile ' . $gesehen[$k];
                $plan[] = $eintrag;
                continue 2;
            }
        }
        foreach ($keys as $k) {
            $gesehen[$k] = $eintrag['zeile'];
        }

        $treffer = null;
        foreach ($keys as $k) {
            if (isset($index[$k])) {
                $treffer = (int)$index[$k];
                break;
            }
        }

        if ($treffer !== null && $strategie !== 'neu') {
            $eintrag['id'] = $treffer;
            $eintrag['aktion'] = $strategie;
            if ($strategie === 'ueberspringen') {
                $eintrag['grund'] = 'bereits vorhanden';
            }
        }
        $plan[] = $eintrag;
    }

    return $plan;
}

/** Felder, die beim Zusammenführen berücksichtigt werden */
const IMPORT_MERGE_FELDER = [
    'anrede', 'titel', 'vorname', 'nachname', 'organisation', 'position',
    'email', 'telefon', 'mobil', 'strasse', 'plz', 'ort', 'land', 'anschreiben', 'notiz',
];

/**
 * Welche Felder eines vorhandenen Kontakts ändern sich?
 * "ergaenzen" füllt nur leere Felder, "ueberschreiben" setzt jeden
 * mitgelieferten Wert. Leere Importwerte löschen nie etwas.
 */
function import_merge(array $bestehend, array $neu, string $strategie): array
{
    $aenderungen = [];
    foreach (IMPORT_MERGE_FELDER as $feld) {
        $wert = trim((string)($neu[$feld] ?? ''));
        if ($wert === '') {
            continue;
        }
        $alt = trim((string)($bestehend[$feld] ?? ''));
        if ($strategie === 'ergaenzen' && $alt !== '') {
            continue;
        }
        if ($alt !== $wert) {
            $aenderungen[$feld] = $wert;
        }
    }
    return $aenderungen;
}

/** Zusatzfelder nach derselben Regel zusammenführen */
function import_merge_extra(array $bestehend, array $neu, string $strategie): array
{
    $ergebnis = $bestehend;
    foreach ($neu as $key => $wert) {
        if ($wert === '') {
            continue;
        }
        if ($strategie === 'ergaenzen' && trim((string)($bestehend[$key] ?? '')) !== '') {
            continue;
        }
        $ergebnis[$key] = $wert;
    }
    return $ergebnis;
}
