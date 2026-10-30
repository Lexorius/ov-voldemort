<?php
declare(strict_types=1);

/**
 * Tankkarten aus einer CSV-Datei übernehmen – etwa aus der Kartenübersicht
 * des Anbieters oder der bisherigen Excel-Liste. Drei Schritte wie beim
 * Kontakt-Import: hochladen, Spalten zuordnen, Vorschau, importieren.
 *
 * Fahrzeuge werden über Kennzeichen oder Bezeichnung erkannt (mehrere je
 * Zelle, durch Komma getrennt), Anbieter und Status über die Auswahllisten;
 * unbekannte Anbieter lassen sich auf Wunsch anlegen. Vorhanden ist eine
 * Karte, wenn Anbieter und Nummer schon da sind.
 */

const TANKKARTE_IMPORT_MAX = 2000;

/** Felder, die eine Spalte werden kann */
function tankkarte_import_ziele(): array
{
    return [
        'nummer'        => 'Kartennummer',
        'anbieter'      => 'Anbieter',
        'art'           => 'Schaltet frei (Kraftstoff / Laden / beides)',
        'status'        => 'Status',
        'kundennummer'  => 'Kundennummer',
        'gueltig_bis'   => 'Gültig bis',
        'ausgegeben_am' => 'Ausgegeben am',
        'fahrzeuge'     => 'Fahrzeuge (Kennzeichen oder Bezeichnung, mehrere mit Komma)',
        'person'        => 'Liegt bei (Name des Mitglieds)',
        'aufbewahrung'  => 'Aufbewahrung',
        'pin'           => 'PIN',
        'verbleib'      => 'Verbleib (gesperrt / zurückgegeben / abgelaufen)',
        'verbleib_am'   => 'Verbleib seit',
        'sperrgrund'    => 'Grund der Sperrung',
        'notiz'         => 'Notiz',
    ];
}

/** Spaltennamen, die zu einem Ziel passen – zuerst genau, dann als Bestandteil */
function tankkarte_import_synonyme(): array
{
    return [
        'nummer'        => [['nummer', 'kartennummer', 'karte', 'cardnumber', 'card', 'kartennr', 'nr', 'pan', 'tankkarte', 'tankkartennummer'], ['kartennummer', 'cardnumber', 'kartennr']],
        'anbieter'      => [['anbieter', 'provider', 'issuer', 'herausgeber', 'gesellschaft', 'kartenanbieter', 'lieferant'], ['anbieter', 'provider']],
        'art'           => [['art', 'typ', 'type', 'freischaltung', 'schaltetfrei', 'kartenart', 'verwendung'], ['freischaltung', 'kartenart']],
        'status'        => [['status', 'zustand', 'state'], []],
        'kundennummer'  => [['kundennummer', 'kundennr', 'customernumber', 'customer', 'kunde', 'account', 'konto', 'kontonummer'], ['kundennummer', 'kundennr', 'customer']],
        'gueltig_bis'   => [['gueltigbis', 'gueltig', 'ablauf', 'ablaufdatum', 'expiry', 'expires', 'expirydate', 'validuntil', 'validto', 'laufzeit'], ['gueltig', 'ablauf', 'expir', 'valid']],
        'ausgegeben_am' => [['ausgegebenam', 'ausgegeben', 'ausgabe', 'ausgabedatum', 'issued', 'issuedate', 'seit'], ['ausgegeben', 'ausgabe', 'issued']],
        'fahrzeuge'     => [['fahrzeug', 'fahrzeuge', 'kennzeichen', 'vehicle', 'vehicles', 'kfz', 'kfzkennzeichen', 'licenseplate', 'zuordnung', 'fahrzeugzuordnung'], ['fahrzeug', 'kennzeichen', 'vehicle']],
        'person'        => [['person', 'liegtbei', 'mitglied', 'inhaber', 'halter', 'name', 'ausgegebenan', 'verantwortlich', 'nutzer', 'user', 'holder', 'cardholder'], ['person', 'inhaber', 'holder']],
        'aufbewahrung'  => [['aufbewahrung', 'ort', 'ablage', 'aufbewahrungsort', 'standort', 'wo', 'location'], ['aufbewahrung', 'ablage']],
        'pin'           => [['pin', 'pincode', 'geheimzahl'], ['pin']],
        'verbleib'      => [['verbleib', 'gesperrt', 'sperre', 'blocked'], ['verbleib', 'sperr']],
        'verbleib_am'   => [['verbleibseit', 'gesperrtseit', 'gesperrtam', 'sperrdatum', 'blockeddate'], ['sperrdatum', 'gesperrtseit']],
        'sperrgrund'    => [['sperrgrund', 'grund', 'grundsperrung', 'grunddersperrung', 'reason', 'blockreason'], ['sperrgrund', 'grund', 'reason']],
        'notiz'         => [['notiz', 'notizen', 'notes', 'note', 'bemerkung', 'bemerkungen', 'kommentar', 'anmerkung', 'hinweis', 'remarks', 'remark', 'comment', 'comments'], ['notiz', 'bemerkung', 'kommentar', 'remark']],
    ];
}

/**
 * Spalten automatisch zuordnen – jedes Ziel höchstens einmal.
 * Rückgabe: Spaltenindex => Ziel ('' = nicht übernehmen). Reine Funktion.
 */
function tankkarte_import_mapping(array $header): array
{
    $norm = array_map('import_norm', $header);
    $map = array_fill(0, count($header), '');
    $vergeben = [];
    $synonyme = tankkarte_import_synonyme();

    foreach ($norm as $i => $n) {
        if ($n === '') {
            continue;
        }
        foreach ($synonyme as $ziel => [$exakt]) {
            if (!isset($vergeben[$ziel]) && in_array($n, $exakt, true)) {
                $map[$i] = $ziel;
                $vergeben[$ziel] = true;
                continue 2;
            }
        }
    }
    foreach ($norm as $i => $n) {
        if ($n === '' || $map[$i] !== '') {
            continue;
        }
        foreach ($synonyme as $ziel => [, $teile]) {
            if (isset($vergeben[$ziel])) {
                continue;
            }
            foreach ($teile as $teil) {
                if (str_contains($n, $teil)) {
                    $map[$i] = $ziel;
                    $vergeben[$ziel] = true;
                    continue 3;
                }
            }
        }
    }
    return $map;
}

/** Datei zerlegen: header, roh, zeilen (Feldwerte je Zeile nach Zuordnung) */
function tankkarte_import_parse(string $utf8, ?array $mapping = null): array
{
    $csv = import_csv_parse($utf8);
    $mapping ??= tankkarte_import_mapping($csv['header']);
    $zeilen = [];
    foreach ($csv['rows'] as $roh) {
        $zeilen[] = import_csv_row($roh, $mapping);
    }
    return ['header' => $csv['header'], 'roh' => $csv['rows'], 'zeilen' => $zeilen, 'mapping' => $mapping];
}

/** Datum aus deutscher oder ISO-Schreibweise – oder null. Reine Funktion. */
function tankkarte_import_datum(string $wert): ?string
{
    $wert = trim($wert);
    if ($wert === '') {
        return null;
    }
    if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})/', $wert, $m)) {
        return checkdate((int)$m[2], (int)$m[3], (int)$m[1]) ? sprintf('%04d-%02d-%02d', $m[1], $m[2], $m[3]) : null;
    }
    if (preg_match('/^(\d{1,2})\.(\d{1,2})\.(\d{2,4})$/', $wert, $m)) {
        $jahr = (int)$m[3] < 100 ? 2000 + (int)$m[3] : (int)$m[3];
        return checkdate((int)$m[2], (int)$m[1], $jahr) ? sprintf('%04d-%02d-%02d', $jahr, $m[2], $m[1]) : null;
    }
    if (preg_match('/^(\d{1,2})\/(\d{4})$/', $wert, $m)) {
        // „06/2027" wie auf der Karte: Ende des Monats
        return date('Y-m-t', mktime(0, 0, 0, (int)$m[1], 1, (int)$m[2])) ?: null;
    }
    return null;
}

/** Freitext „Laden", „Ladekarte", „Strom", „beides" … auf die Art bringen. Reine Funktion. */
function tankkarte_import_art(string $wert): string
{
    $n = import_norm($wert);
    if ($n === '') {
        return 'kraftstoff';
    }
    if (str_contains($n, 'beide') || str_contains($n, 'kombi') || str_contains($n, 'both') || (str_contains($n, 'lade') && (str_contains($n, 'tank') || str_contains($n, 'kraftstoff')))) {
        return 'beides';
    }
    if (str_contains($n, 'lade') || str_contains($n, 'strom') || str_contains($n, 'charg') || $n === 'e' || str_contains($n, 'elektro') || str_contains($n, 'ev')) {
        return 'laden';
    }
    return 'kraftstoff';
}

/** Freitext auf den Verbleib bringen: „gesperrt", „zurückgegeben", „abgelaufen", sonst im Bestand. Reine Funktion. */
function tankkarte_import_verbleib(string $wert): string
{
    $n = import_norm($wert);
    if ($n === '' || in_array($n, ['nein', 'no', '0', 'aktiv', 'imbestand', 'bestand', 'false'], true)) {
        return '';
    }
    if (str_contains($n, 'sperr') || str_contains($n, 'block') || in_array($n, ['ja', 'yes', '1', 'true', 'x'], true)) {
        return 'gesperrt';
    }
    if (str_contains($n, 'zurueck') || str_contains($n, 'return') || str_contains($n, 'abgegeben')) {
        return 'zurueckgegeben';
    }
    if (str_contains($n, 'abgelauf') || str_contains($n, 'expir') || str_contains($n, 'ungueltig')) {
        return 'abgelaufen';
    }
    return '';
}

/** Fahrzeugspalte zerlegen: „THW 99020, MTW" → zwei Namen. Reine Funktion. */
function tankkarte_import_fahrzeuge_split(string $wert): array
{
    $teile = preg_split('/\s*[,;|\/]\s*|\s+und\s+|\n/u', $wert) ?: [];
    return array_values(array_filter(array_map('trim', $teile), static fn($t) => $t !== ''));
}

/**
 * Fahrzeug zu einem Text finden: Kennzeichen, Bezeichnung oder Funkrufname,
 * Schreibweise egal. Reine Funktion über die Fahrzeugliste.
 */
function tankkarte_import_fahrzeug(string $text, array $fahrzeuge): ?int
{
    $soll = stein_vergleichsform($text);
    if ($soll === '') {
        return null;
    }
    foreach ([['kennzeichen'], ['bezeichnung', 'funkrufname']] as $felder) {
        foreach ($fahrzeuge as $v) {
            foreach ($felder as $feld) {
                if (stein_vergleichsform((string)($v[$feld] ?? '')) === $soll) {
                    return (int)$v['id'];
                }
            }
        }
    }
    return null;
}

/**
 * Eine Zeile prüfen und in Kartendaten übersetzen. Reine Funktion über den
 * Kontext (Anbieter- und Statusliste, Fahrzeuge, Personen).
 * Rückgabe: karte, fahrzeug_ids, fahrzeuge_unbekannt, anbieter_text,
 * anbieter_neu, fehler[], hinweise[].
 */
function tankkarte_import_zeile(array $f, array $kontext): array
{
    $fehler = [];
    $hinweise = [];
    $nummer = mb_substr((string)preg_replace('/\s+/', ' ', trim((string)($f['nummer'] ?? ''))), 0, 60);
    if ($nummer === '') {
        $fehler[] = 'keine Kartennummer';
    }

    $anbieterText = trim((string)($f['anbieter'] ?? ''));
    $anbieterId = $anbieterText !== '' ? import_match_list($anbieterText, $kontext['anbieter'] ?? []) : null;
    if ($anbieterText === '' && !empty($kontext['anbieter_vorgabe'])) {
        $anbieterId = (int)$kontext['anbieter_vorgabe'];
    }
    $anbieterNeu = $anbieterText !== '' && $anbieterId === null;
    if ($anbieterText === '' && $anbieterId === null) {
        $fehler[] = 'kein Anbieter – in der Datei oder als Vorgabe';
    } elseif ($anbieterNeu && empty($kontext['anbieter_anlegen'])) {
        $fehler[] = 'Anbieter „' . $anbieterText . '" ist unbekannt';
    }

    $statusId = trim((string)($f['status'] ?? '')) !== '' ? import_match_list((string)$f['status'], $kontext['status'] ?? []) : null;
    if ($statusId === null) {
        $statusId = $kontext['status_vorgabe'] ?? null;
        if (trim((string)($f['status'] ?? '')) !== '') {
            $hinweise[] = 'Status „' . trim((string)$f['status']) . '" unbekannt, Vorgabe genommen';
        }
    }

    $fahrzeugIds = [];
    $unbekannt = [];
    foreach (tankkarte_import_fahrzeuge_split((string)($f['fahrzeuge'] ?? '')) as $name) {
        $id = tankkarte_import_fahrzeug($name, $kontext['fahrzeuge'] ?? []);
        if ($id === null) {
            $unbekannt[] = $name;
        } elseif (!in_array($id, $fahrzeugIds, true)) {
            $fahrzeugIds[] = $id;
        }
    }
    if ($unbekannt) {
        $hinweise[] = 'Fahrzeug nicht gefunden: ' . implode(', ', $unbekannt);
    }

    $personId = null;
    $personText = trim((string)($f['person'] ?? ''));
    if ($personText !== '') {
        $soll = import_norm($personText);
        foreach ($kontext['personen'] ?? [] as $p) {
            if (import_norm((string)$p['display_name']) === $soll) {
                $personId = (int)$p['id'];
                break;
            }
        }
        if ($personId === null) {
            $hinweise[] = 'Person „' . $personText . '" nicht gefunden – steht in der Aufbewahrung';
        }
    }

    $verbleib = tankkarte_import_verbleib((string)($f['verbleib'] ?? ''));
    $sperrgrund = $verbleib === 'gesperrt' ? mb_substr(trim((string)($f['sperrgrund'] ?? '')), 0, 255) : '';
    if ($verbleib === 'gesperrt' && $sperrgrund === '') {
        $sperrgrund = 'aus Import übernommen';
    }
    $gueltig = tankkarte_import_datum((string)($f['gueltig_bis'] ?? ''));
    if (trim((string)($f['gueltig_bis'] ?? '')) !== '' && $gueltig === null) {
        $hinweise[] = 'Gültig bis „' . trim((string)$f['gueltig_bis']) . '" nicht lesbar';
    }
    $pin = mb_substr(trim((string)($f['pin'] ?? '')), 0, 20);
    $aufbewahrung = mb_substr(trim((string)($f['aufbewahrung'] ?? '')), 0, 150);
    if ($personId === null && $personText !== '') {
        $aufbewahrung = mb_substr(trim($aufbewahrung === '' ? $personText : $aufbewahrung . ' · ' . $personText), 0, 150);
    }

    return [
        'karte' => [
            'nummer'        => $nummer,
            'anbieter_id'   => $anbieterId,
            'art'           => tankkarte_import_art((string)($f['art'] ?? '')),
            'status_id'     => $statusId,
            'kundennummer'  => mb_substr(trim((string)($f['kundennummer'] ?? '')), 0, 60),
            'gueltig_bis'   => $gueltig,
            'hat_pin'       => $pin !== '' ? 1 : 0,
            'pin'           => $pin,
            'person_id'     => $personId,
            'aufbewahrung'  => $aufbewahrung,
            'ausgegeben_am' => tankkarte_import_datum((string)($f['ausgegeben_am'] ?? '')),
            'notiz'         => trim((string)($f['notiz'] ?? '')),
            'verbleib'      => $verbleib,
            'verbleib_am'   => $verbleib === '' ? null : (tankkarte_import_datum((string)($f['verbleib_am'] ?? '')) ?? date('Y-m-d')),
            'sperrgrund'    => $sperrgrund,
            'is_active'     => $verbleib === '' ? 1 : 0,
        ],
        'fahrzeug_ids'        => $fahrzeugIds,
        'fahrzeuge_unbekannt' => $unbekannt,
        'anbieter_text'       => $anbieterText,
        'anbieter_neu'        => $anbieterNeu,
        'fehler'              => $fehler,
        'hinweise'            => $hinweise,
    ];
}

const TANKKARTE_IMPORT_STRATEGIEN = [
    'ueberspringen'  => 'Vorhandene Karten überspringen',
    'ueberschreiben' => 'Vorhandene Karten überschreiben',
];

/** Schlüssel, unter dem eine Karte als vorhanden gilt: Anbieter und Nummer. Reine Funktion. */
function tankkarte_import_schluessel(?int $anbieterId, string $nummer, string $anbieterText = ''): string
{
    return ($anbieterId !== null ? 'id' . $anbieterId : 'neu:' . import_norm($anbieterText)) . '|' . import_norm($nummer);
}

/**
 * Für jede Zeile entscheiden: neu, ueberschreiben, ueberspringen, fehler.
 * $index: Schlüssel => id der vorhandenen Karten. Reine Funktion.
 */
function tankkarte_import_plan(array $zeilen, array $kontext, array $index, string $strategie): array
{
    $strategie = array_key_exists($strategie, TANKKARTE_IMPORT_STRATEGIEN) ? $strategie : 'ueberspringen';
    $plan = [];
    $gesehen = [];
    foreach ($zeilen as $nr => $f) {
        $z = tankkarte_import_zeile($f, $kontext);
        $eintrag = $z + ['zeile' => $nr + 1, 'aktion' => 'neu', 'id' => null, 'grund' => ''];
        if ($z['fehler']) {
            $eintrag['aktion'] = 'fehler';
            $eintrag['grund'] = implode(', ', $z['fehler']);
            $plan[] = $eintrag;
            continue;
        }
        $key = tankkarte_import_schluessel($z['karte']['anbieter_id'], $z['karte']['nummer'], $z['anbieter_text']);
        if (isset($gesehen[$key])) {
            $eintrag['aktion'] = 'ueberspringen';
            $eintrag['grund'] = 'doppelt in der Datei, wie Zeile ' . $gesehen[$key];
            $plan[] = $eintrag;
            continue;
        }
        $gesehen[$key] = $eintrag['zeile'];
        if (isset($index[$key])) {
            $eintrag['id'] = (int)$index[$key];
            $eintrag['aktion'] = $strategie;
            if ($strategie === 'ueberspringen') {
                $eintrag['grund'] = 'bereits vorhanden';
            }
        }
        $plan[] = $eintrag;
    }
    return $plan;
}

/** Vorhandene Karten als Schlüssel => id */
function tankkarte_import_index(): array
{
    $index = [];
    foreach (db_all('SELECT id, nummer, anbieter_id FROM tankkarten') as $k) {
        $index[tankkarte_import_schluessel($k['anbieter_id'] !== null ? (int)$k['anbieter_id'] : null, (string)$k['nummer'])] = (int)$k['id'];
    }
    return $index;
}

/** Was der Import zum Entscheiden braucht */
function tankkarte_import_kontext(array $optionen): array
{
    return [
        'anbieter'         => list_items('tankkarte_anbieter', false),
        'status'           => list_items('tankkarte_status', false),
        'fahrzeuge'        => db_all('SELECT id, bezeichnung, funkrufname, kennzeichen FROM vehicles ORDER BY is_active DESC, bezeichnung'),
        'personen'         => db_all('SELECT id, display_name FROM users WHERE is_active = 1'),
        'anbieter_vorgabe' => $optionen['anbieter_id'] ?? null,
        'anbieter_anlegen' => !empty($optionen['anbieter_anlegen']),
        'status_vorgabe'   => list_default_id('tankkarte_status'),
    ];
}

/** Anbieter aus der Datei in der Auswahlliste anlegen – einmal je Text */
function tankkarte_import_anbieter(string $text, array &$cache): ?int
{
    $n = import_norm($text);
    if (array_key_exists($n, $cache)) {
        return $cache[$n];
    }
    $slug = slugify($text) ?: 'anbieter';
    db_exec(
        'INSERT IGNORE INTO list_items (list_key, label, slug, color, sort_order) VALUES (?,?,?,?,?)',
        ['tankkarte_anbieter', mb_substr($text, 0, 150), mb_substr($slug, 0, 150), '#64748b', 900]
    );
    $id = db_val('SELECT id FROM list_items WHERE list_key = ? AND slug = ?', ['tankkarte_anbieter', $slug]);
    return $cache[$n] = ($id ? (int)$id : null);
}

/** Den Plan ausführen. Rückgabe: neu, ueberschrieben, uebersprungen, fehler, anbieter_neu, hinweise[] */
function tankkarte_import_execute(array $plan, array $optionen, array $user): array
{
    $erg = ['neu' => 0, 'ueberschrieben' => 0, 'uebersprungen' => 0, 'fehler' => 0, 'anbieter_neu' => 0, 'hinweise' => []];
    $anbieterCache = [];
    foreach ($plan as $e) {
        foreach ($e['hinweise'] as $h) {
            if (count($erg['hinweise']) < 200) {
                $erg['hinweise'][] = 'Zeile ' . $e['zeile'] . ': ' . $h;
            }
        }
        if ($e['aktion'] === 'fehler') {
            $erg['fehler']++;
            if (count($erg['hinweise']) < 200) {
                $erg['hinweise'][] = 'Zeile ' . $e['zeile'] . ': ' . $e['grund'];
            }
            continue;
        }
        if ($e['aktion'] === 'ueberspringen') {
            $erg['uebersprungen']++;
            continue;
        }
        $daten = $e['karte'];
        if ($daten['anbieter_id'] === null && $e['anbieter_neu']) {
            $vorher = count($anbieterCache);
            $daten['anbieter_id'] = tankkarte_import_anbieter($e['anbieter_text'], $anbieterCache);
            if (count($anbieterCache) > $vorher && $daten['anbieter_id'] !== null) {
                $erg['anbieter_neu']++;
            }
            if ($daten['anbieter_id'] === null) {
                $erg['fehler']++;
                $erg['hinweise'][] = 'Zeile ' . $e['zeile'] . ': Anbieter „' . $e['anbieter_text'] . '" ließ sich nicht anlegen';
                continue;
            }
        }
        $daten['updated_by'] = (int)$user['id'];
        if ($e['aktion'] === 'ueberschreiben' && $e['id']) {
            db_update('tankkarten', $daten, 'id = ?', [(int)$e['id']]);
            $id = (int)$e['id'];
            $erg['ueberschrieben']++;
            audit('tankkarte.import_ueberschrieben', 'tankkarte', $id, $daten['nummer']);
        } else {
            $daten['created_by'] = (int)$user['id'];
            $id = db_insert('tankkarten', $daten);
            $erg['neu']++;
            audit('tankkarte.import', 'tankkarte', $id, $daten['nummer']);
        }
        if ($e['fahrzeug_ids'] || $e['aktion'] === 'ueberschreiben') {
            tankkarte_fahrzeuge_setzen($id, $e['fahrzeug_ids']);
        }
    }
    return $erg;
}
