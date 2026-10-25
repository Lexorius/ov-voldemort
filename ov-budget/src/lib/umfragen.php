<?php
declare(strict_types=1);

/**
 * Abstimmungen: Umfragen mit einer oder mehreren Seiten, die über den
 * Connector im Netz stehen.
 *
 * Gestaltet wird hier, beantwortet draußen: Hinter einer kurzen Adresse
 * oder einem QR-Code zeigt der Connector die Fragen. Die Antworten werden
 * im Browser für unseren Schlüssel verschlüsselt, liegen beim Connector nur
 * als Geheimtext und sind nach dem Abholen dort gelöscht. Ausgewertet wird
 * hier – und auch hier lassen sich die Antworten mit einem Klick löschen.
 *
 * Jede Abstimmung ist zeitlich begrenzt: Ohne Ende gibt es keine Freigabe,
 * und der Connector nimmt außerhalb des Fensters nichts an.
 *
 * Die Seiten liegen als JSON in der Spalte `seiten`:
 *   [{ "titel": "…", "text": "…", "felder": [
 *       { "id": "f1", "typ": "einfach",  "frage": "…", "pflicht": 1, "optionen": ["A", "B"] },
 *       { "id": "f2", "typ": "mehrfach", "frage": "…", "pflicht": 0, "optionen": ["A", "B", "C"] },
 *       { "id": "f3", "typ": "skala",    "frage": "…", "min": 1, "max": 5, "links": "…", "rechts": "…" },
 *       { "id": "f4", "typ": "janein",   "frage": "…" },
 *       { "id": "f5", "typ": "text",     "frage": "…", "lang": 1 },
 *       { "id": "f6", "typ": "hinweis",  "frage": "…" } ] }]
 */

const UMFRAGE_FELDTYPEN = [
    'einfach'  => 'Eine Antwort (Auswahl)',
    'mehrfach' => 'Mehrere Antworten (Ankreuzen)',
    'janein'   => 'Ja / Nein',
    'skala'    => 'Skala (z. B. 1 bis 5)',
    'text'     => 'Freitext',
    'hinweis'  => 'Hinweistext (keine Frage)',
];

const UMFRAGE_STATUS = ['entwurf' => 'Entwurf', 'offen' => 'Freigegeben', 'geschlossen' => 'Geschlossen'];

const UMFRAGE_MAX_SEITEN = 20;
const UMFRAGE_MAX_FELDER = 50;
const UMFRAGE_MAX_OPTIONEN = 30;

/* ==================================================================== */
/* Laden                                                                 */
/* ==================================================================== */

function umfrage_all(): array
{
    return db_all('SELECT u.*, (SELECT COUNT(*) FROM umfrage_antworten a WHERE a.umfrage_id = u.id) AS antworten_anzahl
                   FROM umfragen u ORDER BY u.ende DESC, u.id DESC');
}

function umfrage_find(?int $id): ?array
{
    return $id ? db_row('SELECT * FROM umfragen WHERE id = ?', [$id]) : null;
}

/** Die Seiten einer Abstimmung als Liste – aus dem JSON der Spalte. Reine Funktion. */
function umfrage_seiten(array $u): array
{
    $roh = $u['seiten'] ?? [];
    if (is_string($roh)) {
        $roh = json_decode($roh, true);
    }
    return is_array($roh) ? array_values($roh) : [];
}

/** Wie viele echte Fragen stehen in den Seiten (Hinweise zählen nicht). Reine Funktion. */
function umfrage_fragen_anzahl(array $seiten): int
{
    $n = 0;
    foreach ($seiten as $s) {
        foreach ((array)($s['felder'] ?? []) as $f) {
            if (($f['typ'] ?? '') !== 'hinweis') {
                $n++;
            }
        }
    }
    return $n;
}

/* ==================================================================== */
/* Säubern und prüfen                                                    */
/* ==================================================================== */

/**
 * Seiten aus dem Gestalter säubern und auf Fehler prüfen.
 * Rückgabe: [gesäuberte Seiten, Fehlertexte]. Die Regeln sind dieselben,
 * die der Connector beim Empfang anlegt – was hier durchgeht, kommt dort
 * unverändert an. Reine Funktion.
 */
function umfrage_seiten_pruefen(array $seiten): array
{
    $text = static fn(string $wert, int $laenge): string => mb_substr(
        trim(preg_replace('/[\x00-\x08\x0b\x0c\x0e-\x1f]/u', '', $wert) ?? ''), 0, $laenge);
    $fehler = [];
    $out = [];
    $ids = [];
    $seiten = array_values($seiten);
    if (count($seiten) > UMFRAGE_MAX_SEITEN) {
        $fehler[] = sprintf('Höchstens %d Seiten.', UMFRAGE_MAX_SEITEN);
        $seiten = array_slice($seiten, 0, UMFRAGE_MAX_SEITEN);
    }
    foreach ($seiten as $si => $s) {
        if (!is_array($s)) {
            continue;
        }
        $seiteNr = $si + 1;
        $felder = [];
        $roh = array_values((array)($s['felder'] ?? []));
        if (count($roh) > UMFRAGE_MAX_FELDER) {
            $fehler[] = sprintf('Seite %d: höchstens %d Fragen.', $seiteNr, UMFRAGE_MAX_FELDER);
            $roh = array_slice($roh, 0, UMFRAGE_MAX_FELDER);
        }
        foreach ($roh as $fi => $f) {
            if (!is_array($f)) {
                continue;
            }
            $nr = sprintf('Seite %d, Frage %d', $seiteNr, $fi + 1);
            $typ = (string)($f['typ'] ?? '');
            if (!isset(UMFRAGE_FELDTYPEN[$typ])) {
                $fehler[] = $nr . ': unbekannter Fragetyp.';
                continue;
            }
            $id = (string)($f['id'] ?? '');
            if (!preg_match('/^[a-z0-9_]{1,20}$/', $id) || isset($ids[$id])) {
                $id = 'f' . (count($ids) + 1);
                while (isset($ids[$id])) {
                    $id .= '_';
                }
            }
            $ids[$id] = true;
            $e = ['id' => $id, 'typ' => $typ, 'frage' => $text((string)($f['frage'] ?? ''), 500),
                  'pflicht' => !empty($f['pflicht']) && $typ !== 'hinweis' ? 1 : 0];
            if ($e['frage'] === '') {
                $fehler[] = $nr . ': ' . ($typ === 'hinweis' ? 'Der Hinweistext ist leer.' : 'Die Frage hat keinen Text.');
                continue;
            }
            if ($typ === 'einfach' || $typ === 'mehrfach') {
                $optionen = [];
                foreach (array_values((array)($f['optionen'] ?? [])) as $o) {
                    $o = $text(is_scalar($o) ? (string)$o : '', 200);
                    if ($o !== '') {
                        $optionen[] = $o;
                    }
                }
                if (count($optionen) > UMFRAGE_MAX_OPTIONEN) {
                    $fehler[] = $nr . sprintf(': höchstens %d Antwortmöglichkeiten.', UMFRAGE_MAX_OPTIONEN);
                    $optionen = array_slice($optionen, 0, UMFRAGE_MAX_OPTIONEN);
                }
                if (count($optionen) < 2) {
                    $fehler[] = $nr . ': mindestens zwei Antwortmöglichkeiten, eine je Zeile.';
                    continue;
                }
                $e['optionen'] = $optionen;
            } elseif ($typ === 'skala') {
                $min = max(0, min(10, (int)($f['min'] ?? 1)));
                $max = max(0, min(10, (int)($f['max'] ?? 5)));
                if ($max <= $min) {
                    $fehler[] = $nr . ': Die Skala braucht ein Ende über dem Anfang (0 bis 10).';
                    continue;
                }
                $e['min'] = $min;
                $e['max'] = $max;
                $e['links'] = $text((string)($f['links'] ?? ''), 60);
                $e['rechts'] = $text((string)($f['rechts'] ?? ''), 60);
            } elseif ($typ === 'text') {
                $e['lang'] = !empty($f['lang']) ? 1 : 0;
            }
            $felder[] = $e;
        }
        if (!$felder) {
            $fehler[] = sprintf('Seite %d hat keine Frage.', $seiteNr);
            continue;
        }
        $out[] = ['titel' => $text((string)($s['titel'] ?? ''), 200),
                  'text' => $text((string)($s['text'] ?? ''), 2000), 'felder' => $felder];
    }
    if (!$out && !$fehler) {
        $fehler[] = 'Die Abstimmung braucht mindestens eine Seite mit einer Frage.';
    }
    return [$out, $fehler];
}

/** Alle Felder aller Seiten, nach Kennung. Reine Funktion. */
function umfrage_felder(array $seiten): array
{
    $out = [];
    foreach ($seiten as $s) {
        foreach ((array)($s['felder'] ?? []) as $f) {
            if (isset($f['id'])) {
                $out[(string)$f['id']] = $f;
            }
        }
    }
    return $out;
}

/**
 * Eine hereingekommene Antwort gegen die Fragen prüfen: nur bekannte
 * Felder, nur erlaubte Werte. Was nicht passt, fällt weg. Reine Funktion.
 */
function umfrage_antwort_normieren(array $seiten, array $daten): array
{
    $felder = umfrage_felder($seiten);
    $antworten = [];
    foreach ((array)($daten['antworten'] ?? []) as $id => $wert) {
        $f = $felder[(string)$id] ?? null;
        if ($f === null) {
            continue;
        }
        switch ($f['typ']) {
            case 'einfach':
                if (is_numeric($wert) && isset($f['optionen'][(int)$wert])) {
                    $antworten[(string)$id] = (int)$wert;
                }
                break;
            case 'mehrfach':
                $liste = [];
                foreach ((array)$wert as $w) {
                    if (is_numeric($w) && isset($f['optionen'][(int)$w])) {
                        $liste[(int)$w] = (int)$w;
                    }
                }
                if ($liste) {
                    $antworten[(string)$id] = array_values($liste);
                }
                break;
            case 'janein':
                if ($wert === 'ja' || $wert === 'nein') {
                    $antworten[(string)$id] = $wert;
                }
                break;
            case 'skala':
                if (is_numeric($wert) && (int)$wert >= (int)$f['min'] && (int)$wert <= (int)$f['max']) {
                    $antworten[(string)$id] = (int)$wert;
                }
                break;
            case 'text':
                $t = is_scalar($wert) ? trim((string)$wert) : '';
                $t = mb_substr((string)preg_replace('/[\x00-\x08\x0b\x0c\x0e-\x1f]/u', '', $t), 0, !empty($f['lang']) ? 4000 : 500);
                if ($t !== '') {
                    $antworten[(string)$id] = $t;
                }
                break;
        }
    }
    $name = is_scalar($daten['name'] ?? null) ? trim((string)$daten['name']) : '';
    return [
        'antworten' => $antworten,
        'name'      => mb_substr((string)preg_replace('/[\x00-\x1f\x7f]/u', ' ', $name), 0, 60),
    ];
}

/* ==================================================================== */
/* Code, Adresse, Zustand                                                */
/* ==================================================================== */

/** So steht eine Abstimmung beim Connector: als Prüfsumme ihres Codes. Reine Funktion. */
function umfrage_kennung(string $code): string
{
    return hash('sha256', 'ovb-umfrage:' . strtoupper(trim($code)));
}

/** Sieht die Zeichenfolge wie ein Code aus? Reine Funktion. */
function umfrage_code_gueltig(string $code): bool
{
    return (bool)preg_match('/^[' . EVENT_CODE_ZEICHEN . ']{4,16}$/', strtoupper(trim($code)));
}

/**
 * Neuer Code. Abstimmungen und Einladungen teilen sich die kurze Adresse,
 * darum darf er in keiner der beiden Listen schon vorkommen.
 */
function umfrage_code_neu(int $laenge): string
{
    $laenge = max(4, min(16, $laenge));
    $zeichen = EVENT_CODE_ZEICHEN;
    $anzahl = strlen($zeichen);
    for ($versuch = 0; $versuch < 20; $versuch++) {
        $code = '';
        for ($i = 0; $i < $laenge; $i++) {
            $code .= $zeichen[random_int(0, $anzahl - 1)];
        }
        if (!db_val('SELECT id FROM umfragen WHERE code = ?', [$code])
            && !db_val('SELECT id FROM event_guests WHERE code = ?', [$code])) {
            return $code;
        }
    }
    throw new RuntimeException('Es ließ sich kein freier Code finden. Bitte die Länge des Codes erhöhen.');
}

/** Die Adresse, die auf dem QR-Code und im Link steht. Reine Funktion. */
function umfrage_url(array $connector, string $code): string
{
    $kurz = rtrim(trim((string)($connector['kurz_url'] ?? '')), '/');
    if ($kurz !== '') {
        return $kurz . '/' . $code;
    }
    return rtrim(trim((string)($connector['url'] ?? '')), '/') . '/index.php?u=' . rawurlencode($code);
}

/**
 * Was gerade los ist: entwurf, wartet (freigegeben, Beginn in der Zukunft),
 * laeuft, abgelaufen (Ende vorbei) oder geschlossen. Reine Funktion.
 */
function umfrage_zustand(array $u, ?int $jetzt = null): string
{
    $jetzt ??= time();
    $status = (string)($u['status'] ?? 'entwurf');
    if ($status !== 'offen') {
        return $status === 'geschlossen' ? 'geschlossen' : 'entwurf';
    }
    $beginn = trim((string)($u['beginn'] ?? '')) !== '' ? (int)strtotime((string)$u['beginn']) : 0;
    $ende = (int)strtotime((string)($u['ende'] ?? ''));
    if ($beginn > $jetzt) {
        return 'wartet';
    }
    return $jetzt > $ende ? 'abgelaufen' : 'laeuft';
}

function umfrage_zustand_text(string $zustand): string
{
    return match ($zustand) {
        'wartet'      => 'freigegeben, öffnet später',
        'laeuft'      => 'läuft',
        'abgelaufen'  => 'beendet',
        'geschlossen' => 'geschlossen',
        default       => 'Entwurf',
    };
}

/** Marke für Listen und Kopf. Reine Funktion. */
function umfrage_zustand_badge(string $zustand): string
{
    $farbe = match ($zustand) {
        'laeuft'     => '#15803d',
        'wartet'     => '#0284c7',
        'abgelaufen' => '#a16207',
        default      => '#64748b',
    };
    return '<span class="badge" style="background:' . $farbe . '">' . e(umfrage_zustand_text($zustand)) . '</span>';
}

/** Nimmt der Connector gerade Antworten an? Reine Funktion. */
function umfrage_laeuft(array $u, ?int $jetzt = null): bool
{
    return umfrage_zustand($u, $jetzt) === 'laeuft';
}

/** "So., 5. Dezember 2026, 18:00" aus einem DATETIME. */
function umfrage_zeit_text(?string $wert): string
{
    return trim((string)$wert) === '' ? '' : de_datetime($wert);
}

/* ==================================================================== */
/* Speichern                                                             */
/* ==================================================================== */

/**
 * Aus dem Formular anlegen oder ändern. Rückgabe: [id, fehler[]].
 * Die Seiten kommen als JSON aus dem Gestalter (seiten_json).
 */
function umfrage_save_from_post(?array $u): array
{
    $fehler = [];
    $titel = mb_substr(trim(post_str('titel')), 0, 200);
    if ($titel === '') {
        $fehler[] = 'Bitte einen Titel angeben.';
    }
    $datum = static function (string $wert): ?string {
        $wert = trim(str_replace('T', ' ', $wert));
        if ($wert === '') {
            return null;
        }
        $t = strtotime($wert);
        return $t === false ? null : date('Y-m-d H:i:00', $t);
    };
    $beginn = $datum(post_str('beginn'));
    $ende = $datum(post_str('ende'));
    if (trim(post_str('beginn')) !== '' && $beginn === null) {
        $fehler[] = 'Der Beginn ist kein gültiger Zeitpunkt.';
    }
    if ($ende === null) {
        $fehler[] = 'Bitte ein Ende angeben – eine Abstimmung ist immer zeitlich begrenzt.';
    } elseif ($beginn !== null && $ende <= $beginn) {
        $fehler[] = 'Das Ende muss nach dem Beginn liegen.';
    }

    $roh = json_decode(post_str('seiten_json'), true);
    [$seiten, $seitenFehler] = umfrage_seiten_pruefen(is_array($roh) ? $roh : []);
    $fehler = array_merge($fehler, $seitenFehler);

    $connectorId = post_int('connector_id');
    $connector = $connectorId ? connector_find($connectorId) : null;
    if ($connectorId && $connector === null) {
        $fehler[] = 'Diesen Connector gibt es nicht.';
    }
    $rhythmus = post_str('bericht_rhythmus');
    if (!isset(UMFRAGE_BERICHT[$rhythmus])) {
        $rhythmus = '';
    }
    $kontaktId = post_int('bericht_kontakt_id');
    $kontakt = $kontaktId > 0 && function_exists('contact_find') ? contact_find($kontaktId) : null;
    if ($rhythmus !== '' && ($kontakt === null || !mail_adresse_gueltig((string)($kontakt['email'] ?? '')))) {
        $fehler[] = 'Für den Bericht einen Kontakt mit E-Mail-Adresse wählen.';
    }
    if ($fehler) {
        return [null, $fehler];
    }

    $daten = [
        'titel'        => $titel,
        'beschreibung' => mb_substr(trim(post_str('beschreibung')), 0, 2000),
        'connector_id' => $connector !== null ? (int)$connector['id'] : null,
        'beginn'       => $beginn,
        'ende'         => $ende,
        'name_feld'    => post_bool('name_feld') ? 1 : 0,
        'dank'         => mb_substr(trim(post_str('dank')), 0, 500),
        'seiten'       => json_encode($seiten, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'bericht_rhythmus'   => $rhythmus,
        'bericht_kontakt_id' => $kontakt !== null ? (int)$kontakt['id'] : null,
        'bericht_ki'         => post_bool('bericht_ki') ? 1 : 0,
    ];
    if ($u) {
        db_update('umfragen', $daten, 'id = ?', [(int)$u['id']]);
        $id = (int)$u['id'];
        audit('umfrage.bearbeitet', 'umfrage', $id, $titel);
    } else {
        $daten['code'] = umfrage_code_neu(setting_int('umfrage_code_laenge', 6));
        $daten['status'] = 'entwurf';
        $daten['created_by'] = (int)(current_user()['id'] ?? 0) ?: null;
        $id = db_insert('umfragen', $daten);
        audit('umfrage.angelegt', 'umfrage', $id, $titel);
    }
    return [$id, []];
}

function umfrage_status_setzen(array $u, string $status): void
{
    if (!isset(UMFRAGE_STATUS[$status])) {
        throw new InvalidArgumentException('Unbekannter Status: ' . $status);
    }
    db_update('umfragen', ['status' => $status], 'id = ?', [(int)$u['id']]);
    audit('umfrage.status', 'umfrage', (int)$u['id'], $status);
}

function umfrage_code_erneuern(array $u): string
{
    $code = umfrage_code_neu(max(4, strlen((string)$u['code'])));
    db_update('umfragen', ['code' => $code], 'id = ?', [(int)$u['id']]);
    audit('umfrage.code', 'umfrage', (int)$u['id'], 'neu');
    return $code;
}

function umfrage_delete(array $u): void
{
    db_exec('DELETE FROM umfrage_antworten WHERE umfrage_id = ?', [(int)$u['id']]);
    db_exec('DELETE FROM umfragen WHERE id = ?', [(int)$u['id']]);
    audit('umfrage.geloescht', 'umfrage', (int)$u['id'], (string)$u['titel']);
}

/* ==================================================================== */
/* Antworten                                                             */
/* ==================================================================== */

/** Alle Antworten einer Abstimmung, älteste zuerst, mit entpackten Daten */
function umfrage_antworten(array $u): array
{
    $out = [];
    foreach (db_all('SELECT * FROM umfrage_antworten WHERE umfrage_id = ? ORDER BY ts, id', [(int)$u['id']]) as $a) {
        $d = json_decode((string)$a['daten'], true);
        $a['antworten'] = is_array($d) ? $d : [];
        $out[] = $a;
    }
    return $out;
}

/** Eine entschlüsselte Antwort ablegen – geprüft gegen die Fragen */
function umfrage_antwort_speichern(array $u, array $daten, int $ts): int
{
    $sauber = umfrage_antwort_normieren(umfrage_seiten($u), $daten);
    return db_insert('umfrage_antworten', [
        'umfrage_id' => (int)$u['id'],
        'ts'         => date('Y-m-d H:i:s', $ts > 0 ? $ts : time()),
        'name'       => $sauber['name'],
        'daten'      => json_encode($sauber['antworten'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ]);
}

function umfrage_antworten_loeschen(array $u): int
{
    $n = db_exec('DELETE FROM umfrage_antworten WHERE umfrage_id = ?', [(int)$u['id']]);
    audit('umfrage.antworten_geloescht', 'umfrage', (int)$u['id'], (string)$n);
    return $n;
}

/**
 * Auswertung je Frage. Reine Funktion.
 * Rückgabe: ['anzahl' => Antworten gesamt, 'felder' => [id => [...]]]
 *   einfach/mehrfach: 'zaehler' => [i => n], 'beantwortet' => n
 *   janein:           'ja' => n, 'nein' => n, 'beantwortet' => n
 *   skala:            'verteilung' => [wert => n], 'schnitt' => ?float, 'beantwortet' => n
 *   text:             'texte' => [...], 'beantwortet' => n
 */
function umfrage_auswertung(array $seiten, array $antworten): array
{
    $felder = [];
    foreach (umfrage_felder($seiten) as $id => $f) {
        if ($f['typ'] === 'hinweis') {
            continue;
        }
        $e = ['typ' => $f['typ'], 'frage' => (string)$f['frage'], 'beantwortet' => 0];
        switch ($f['typ']) {
            case 'einfach':
            case 'mehrfach':
                $e['optionen'] = array_values((array)$f['optionen']);
                $e['zaehler'] = array_fill(0, count($e['optionen']), 0);
                break;
            case 'janein':
                $e['ja'] = 0;
                $e['nein'] = 0;
                break;
            case 'skala':
                $e['min'] = (int)$f['min'];
                $e['max'] = (int)$f['max'];
                $e['links'] = (string)($f['links'] ?? '');
                $e['rechts'] = (string)($f['rechts'] ?? '');
                $e['verteilung'] = array_fill_keys(range((int)$f['min'], (int)$f['max']), 0);
                $e['summe'] = 0;
                $e['schnitt'] = null;
                break;
            case 'text':
                $e['texte'] = [];
                break;
        }
        $felder[$id] = $e;
    }
    foreach ($antworten as $a) {
        foreach ((array)($a['antworten'] ?? []) as $id => $wert) {
            if (!isset($felder[$id])) {
                continue;
            }
            $e = &$felder[$id];
            switch ($e['typ']) {
                case 'einfach':
                    if (is_int($wert) && isset($e['zaehler'][$wert])) {
                        $e['zaehler'][$wert]++;
                        $e['beantwortet']++;
                    }
                    break;
                case 'mehrfach':
                    $traf = false;
                    foreach ((array)$wert as $w) {
                        if (is_int($w) && isset($e['zaehler'][$w])) {
                            $e['zaehler'][$w]++;
                            $traf = true;
                        }
                    }
                    if ($traf) {
                        $e['beantwortet']++;
                    }
                    break;
                case 'janein':
                    if ($wert === 'ja' || $wert === 'nein') {
                        $e[$wert]++;
                        $e['beantwortet']++;
                    }
                    break;
                case 'skala':
                    if (is_int($wert) && isset($e['verteilung'][$wert])) {
                        $e['verteilung'][$wert]++;
                        $e['summe'] += $wert;
                        $e['beantwortet']++;
                    }
                    break;
                case 'text':
                    if (is_string($wert) && $wert !== '') {
                        $e['texte'][] = $wert;
                        $e['beantwortet']++;
                    }
                    break;
            }
            unset($e);
        }
    }
    foreach ($felder as &$e) {
        if ($e['typ'] === 'skala') {
            $e['schnitt'] = $e['beantwortet'] > 0 ? round($e['summe'] / $e['beantwortet'], 2) : null;
        }
    }
    unset($e);
    return ['anzahl' => count($antworten), 'felder' => $felder];
}

/** Prozent, ganzzahlig, ohne Division durch null. Reine Funktion. */
function umfrage_prozent(int $teil, int $ganz): int
{
    return $ganz > 0 ? (int)round($teil * 100 / $ganz) : 0;
}

/** Eine Antwort lesbar machen – für Tabelle und CSV. Reine Funktion. */
function umfrage_wert_text(array $feld, mixed $wert): string
{
    if ($wert === null) {
        return '';
    }
    switch ($feld['typ']) {
        case 'einfach':
            return is_int($wert) ? (string)($feld['optionen'][$wert] ?? '') : '';
        case 'mehrfach':
            $t = [];
            foreach ((array)$wert as $w) {
                if (is_int($w) && isset($feld['optionen'][$w])) {
                    $t[] = (string)$feld['optionen'][$w];
                }
            }
            return implode('; ', $t);
        case 'janein':
            return $wert === 'ja' ? 'Ja' : ($wert === 'nein' ? 'Nein' : '');
        default:
            return is_scalar($wert) ? (string)$wert : '';
    }
}

/** CSV mit einer Zeile je Antwort, Semikolon-getrennt, für Excel mit BOM. Reine Funktion. */
function umfrage_csv(array $seiten, array $antworten): string
{
    $felder = array_filter(umfrage_felder($seiten), static fn($f) => $f['typ'] !== 'hinweis');
    $zeile = static function (array $werte): string {
        return implode(';', array_map(static function ($w): string {
            $w = str_replace(["\r\n", "\r"], "\n", (string)$w);
            return '"' . str_replace('"', '""', $w) . '"';
        }, $werte)) . "\r\n";
    };
    $out = "\xEF\xBB\xBF" . $zeile(array_merge(['Zeitpunkt', 'Name'], array_map(static fn($f) => (string)$f['frage'], array_values($felder))));
    foreach ($antworten as $a) {
        $w = [de_datetime((string)$a['ts']), (string)($a['name'] ?? '')];
        foreach ($felder as $id => $f) {
            $w[] = umfrage_wert_text($f, $a['antworten'][$id] ?? null);
        }
        $out .= $zeile($w);
    }
    return $out;
}

/* ==================================================================== */
/* Bericht per E-Mail: täglich, wöchentlich oder am Ende                 */
/* ==================================================================== */

const UMFRAGE_BERICHT = [
    ''             => 'kein Bericht',
    'taeglich'     => 'täglich – und zum Abschluss',
    'woechentlich' => 'wöchentlich – und zum Abschluss',
    'ende'         => 'nur am Ende der Abstimmung',
];

/** Lesbarer Name des Rhythmus. Reine Funktion. */
function umfrage_bericht_text_rhythmus(string $rhythmus): string
{
    return UMFRAGE_BERICHT[$rhythmus] ?? UMFRAGE_BERICHT[''];
}

/**
 * Ist ein Bericht fällig – und welcher? Reine Funktion.
 *
 * Rückgabe 'taeglich', 'woechentlich', 'ende' oder null. Zwischenberichte
 * gehen frühestens ab $stunde Uhr und nur, solange die Abstimmung läuft.
 * Der Abschlussbericht geht einmal, nachdem der letzte Abruf nach dem Ende
 * Zeit hatte ($karenz Sekunden) – bei jedem Rhythmus.
 */
function umfrage_bericht_faellig(array $u, int $jetzt, int $stunde = 7, int $karenz = 600): ?string
{
    $rhythmus = (string)($u['bericht_rhythmus'] ?? '');
    if (!isset(UMFRAGE_BERICHT[$rhythmus]) || $rhythmus === '' || (int)($u['bericht_kontakt_id'] ?? 0) <= 0
        || (string)($u['status'] ?? '') !== 'offen') {
        return null;
    }
    $ende = (int)strtotime((string)($u['ende'] ?? ''));
    if ($ende <= 0) {
        return null;
    }
    if ($jetzt >= $ende) {
        return empty($u['bericht_ende_am']) && $jetzt >= $ende + $karenz ? 'ende' : null;
    }
    if ($rhythmus === 'ende' || (int)date('G', $jetzt) < $stunde) {
        return null;
    }
    $beginn = trim((string)($u['beginn'] ?? '')) !== '' ? (int)strtotime((string)$u['beginn']) : 0;
    if ($beginn > $jetzt) {
        return null;
    }
    $letzter = trim((string)($u['bericht_zuletzt'] ?? '')) !== '' ? (string)$u['bericht_zuletzt'] : (string)($u['created_at'] ?? '');
    $zuletzt = $letzter !== '' ? (int)strtotime($letzter) : 0;
    if ($rhythmus === 'taeglich') {
        return date('Y-m-d', $zuletzt) < date('Y-m-d', $jetzt) ? 'taeglich' : null;
    }
    return $jetzt >= $zuletzt + 7 * 86400 ? 'woechentlich' : null;
}

/** Betreff je Art. Reine Funktion. */
function umfrage_bericht_betreff(array $u, string $art, ?int $jetzt = null): string
{
    $jetzt ??= time();
    return $art === 'ende'
        ? sprintf('Ergebnis der Abstimmung „%s"', (string)$u['titel'])
        : sprintf('Abstimmung „%s": Zwischenstand %s', (string)$u['titel'], date('d.m.Y', $jetzt));
}

/**
 * Der Bericht als Text – so, wie er in der E-Mail steht. Reine Funktion.
 * $ki ist eine fertige Zusammenfassung oder null.
 */
function umfrage_bericht_text(array $u, array $seiten, array $antworten, string $art, ?string $ki = null, ?int $jetzt = null, string $empfaenger = ''): string
{
    $jetzt ??= time();
    $a = umfrage_auswertung($seiten, $antworten);
    $z = [];
    $z[] = 'Abstimmung „' . (string)$u['titel'] . '"';
    $z[] = $art === 'ende'
        ? 'Ergebnis – die Abstimmung ist beendet (Stand ' . date('d.m.Y, H:i', $jetzt) . ' Uhr)'
        : 'Zwischenstand vom ' . date('d.m.Y, H:i', $jetzt) . ' Uhr';
    $zeitraum = trim((string)($u['beginn'] ?? '')) !== '' ? 'Zeitraum: ' . de_datetime((string)$u['beginn']) . ' bis ' : 'Offen bis ';
    $z[] = $zeitraum . de_datetime((string)$u['ende']);
    $z[] = 'Antworten: ' . $a['anzahl'];
    if (trim((string)($u['beschreibung'] ?? '')) !== '') {
        $z[] = '';
        $z[] = trim((string)$u['beschreibung']);
    }
    if ($ki !== null && trim($ki) !== '') {
        $z[] = '';
        $z[] = '--- Zusammenfassung (KI, ohne Gewähr) ---';
        $z[] = trim($ki);
    }
    $z[] = '';
    $z[] = '--- Ergebnisse je Frage ---';
    $nr = 0;
    foreach ($a['felder'] as $f) {
        $nr++;
        $z[] = '';
        switch ($f['typ']) {
            case 'einfach':
            case 'mehrfach':
                $basis = $f['typ'] === 'einfach' ? (int)$f['beantwortet'] : (int)$a['anzahl'];
                $z[] = sprintf('%d. %s (%d Antwort%s%s)', $nr, $f['frage'], $f['beantwortet'], $f['beantwortet'] === 1 ? '' : 'en',
                    $f['typ'] === 'mehrfach' ? ', Mehrfachauswahl' : '');
                foreach ($f['optionen'] as $i => $o) {
                    $z[] = sprintf('   %s: %d (%d %%)', $o, $f['zaehler'][$i], umfrage_prozent((int)$f['zaehler'][$i], $basis));
                }
                break;
            case 'janein':
                $z[] = sprintf('%d. %s (%d Antwort%s)', $nr, $f['frage'], $f['beantwortet'], $f['beantwortet'] === 1 ? '' : 'en');
                $z[] = sprintf('   Ja: %d (%d %%) · Nein: %d (%d %%)', $f['ja'], umfrage_prozent((int)$f['ja'], (int)$f['beantwortet']),
                    $f['nein'], umfrage_prozent((int)$f['nein'], (int)$f['beantwortet']));
                break;
            case 'skala':
                $z[] = sprintf('%d. %s (Skala %d bis %d, Durchschnitt %s, %d Antwort%s)', $nr, $f['frage'], $f['min'], $f['max'],
                    $f['schnitt'] !== null ? number_format((float)$f['schnitt'], 1, ',', '.') : '–', $f['beantwortet'], $f['beantwortet'] === 1 ? '' : 'en');
                $teile = [];
                foreach ($f['verteilung'] as $wert => $n) {
                    $teile[] = $wert . ': ' . $n;
                }
                $z[] = '   ' . implode(' · ', $teile) . ($f['links'] !== '' || $f['rechts'] !== '' ? sprintf('   (%d = %s, %d = %s)', $f['min'], $f['links'], $f['max'], $f['rechts']) : '');
                break;
            case 'text':
                $z[] = sprintf('%d. %s (%d Eingabe%s)', $nr, $f['frage'], count($f['texte']), count($f['texte']) === 1 ? '' : 'n');
                foreach (array_slice($f['texte'], 0, 50) as $t) {
                    $z[] = '   – ' . str_replace("\n", "\n     ", $t);
                }
                if (count($f['texte']) > 50) {
                    $z[] = sprintf('   … und %d weitere', count($f['texte']) - 50);
                }
                break;
        }
    }
    $namen = array_values(array_filter(array_map(static fn($r) => trim((string)($r['name'] ?? '')), $antworten)));
    if ((int)($u['name_feld'] ?? 0) === 1 && $namen) {
        $z[] = '';
        $z[] = 'Namen (freiwillig angegeben): ' . implode(', ', $namen);
    }
    $z[] = '';
    $z[] = '--';
    $z[] = 'Dieser Bericht kommt automatisch aus OV-Multitool (Abstimmungen)' . ($empfaenger !== '' ? ', verschickt an ' . $empfaenger : '') . '.';
    return implode("\n", $z);
}

/**
 * Zusammenfassung durch die KI – nur, wenn sie eingeschaltet und erreichbar
 * ist. Schlägt sie fehl, geht der Bericht ohne sie hinaus. Rückgabe null,
 * wenn es keine gibt.
 */
function umfrage_bericht_ki(string $bericht): ?string
{
    if (!function_exists('ki_aktiv') || !ki_aktiv() || !function_exists('ki_text_antwort')) {
        return null;
    }
    $prompt = "Du bekommst die Ergebnisse einer Umfrage eines THW-Ortsverbands. Fasse sie auf Deutsch in höchstens acht Sätzen zusammen: "
        . "die Kernaussagen, deutliche Mehrheiten, die Stimmung aus den Freitexten. Schreibe Fließtext ohne Einleitung, ohne Aufzählungszeichen, "
        . "ohne Überschrift und ohne JSON. Erfinde nichts, was nicht in den Zahlen steht.\n\n" . $bericht;
    try {
        $antwort = trim(ki_text_antwort($prompt));
        return $antwort !== '' ? ki_text_kuerzen($antwort, 2500) : null;
    } catch (Throwable $ex) {
        return null;
    }
}

/**
 * Einen Bericht an den eingetragenen Kontakt schicken und das festhalten.
 * Rückgabe: ['ok' => bool, 'text' => Meldung für den Benutzer, 'ki' => bool]
 */
function umfrage_bericht_senden(array $u, string $art, ?int $jetzt = null): array
{
    $jetzt ??= time();
    $kontakt = function_exists('contact_find') && (int)($u['bericht_kontakt_id'] ?? 0) > 0 ? contact_find((int)$u['bericht_kontakt_id']) : null;
    if ($kontakt === null || !mail_adresse_gueltig((string)($kontakt['email'] ?? ''))) {
        return ['ok' => false, 'text' => 'Für den Bericht ist kein Kontakt mit E-Mail-Adresse eingetragen.', 'ki' => false];
    }
    $seiten = umfrage_seiten($u);
    $antworten = umfrage_antworten($u);
    $name = function_exists('contact_name') ? contact_name($kontakt) : trim((string)($kontakt['vorname'] ?? '') . ' ' . (string)($kontakt['nachname'] ?? ''));
    $ki = null;
    if ((int)($u['bericht_ki'] ?? 0) === 1 && $antworten) {
        $ki = umfrage_bericht_ki(umfrage_bericht_text($u, $seiten, $antworten, $art, null, $jetzt));
    }
    $text = umfrage_bericht_text($u, $seiten, $antworten, $art, $ki, $jetzt, $name);
    try {
        mail_senden((string)$kontakt['email'], umfrage_bericht_betreff($u, $art, $jetzt), $text, $name);
    } catch (Throwable $ex) {
        audit('umfrage.bericht_fehler', 'umfrage', (int)$u['id'], mb_substr($ex->getMessage(), 0, 200));
        return ['ok' => false, 'text' => 'Der Bericht ließ sich nicht verschicken: ' . $ex->getMessage(), 'ki' => $ki !== null];
    }
    $neu = ['bericht_zuletzt' => date('Y-m-d H:i:s', $jetzt)];
    if ($art === 'ende') {
        $neu['bericht_ende_am'] = date('Y-m-d H:i:s', $jetzt);
    }
    db_update('umfragen', $neu, 'id = ?', [(int)$u['id']]);
    audit('umfrage.bericht', 'umfrage', (int)$u['id'], $art . ' an ' . (string)$kontakt['email'] . ($ki !== null ? ' mit KI' : ''));
    return ['ok' => true, 'text' => sprintf('%s an %s (%s) verschickt%s.', $art === 'ende' ? 'Ergebnis' : 'Zwischenstand', $name,
        (string)$kontakt['email'], $ki !== null ? ', mit KI-Zusammenfassung' : ''), 'ki' => $ki !== null];
}

/**
 * Alle fälligen Berichte verschicken – ruft der automatische Abruf.
 * Rückgabe: ['geprueft', 'gesendet', 'fehler' => [Texte]]
 */
function umfrage_berichte_senden(?int $jetzt = null): array
{
    $jetzt ??= time();
    $res = ['geprueft' => 0, 'gesendet' => 0, 'fehler' => []];
    if (!mail_eingerichtet()) {
        return $res;
    }
    $stunde = max(0, min(23, setting_int('umfrage_bericht_stunde', 7)));
    $karenz = max(1, setting_int('umfrage_intervall_minuten', 5)) * 60 + 120;
    foreach (db_all("SELECT * FROM umfragen WHERE status = 'offen' AND bericht_rhythmus <> '' AND bericht_kontakt_id IS NOT NULL") as $u) {
        $res['geprueft']++;
        $art = umfrage_bericht_faellig($u, $jetzt, $stunde, $karenz);
        if ($art === null) {
            continue;
        }
        $r = umfrage_bericht_senden($u, $art, $jetzt);
        if ($r['ok']) {
            $res['gesendet']++;
        } else {
            $res['fehler'][] = (string)$u['titel'] . ': ' . $r['text'];
            // Nicht jede Minute neu scheitern: erst wieder nach einer Stunde
            db_update('umfragen', ['bericht_zuletzt' => date('Y-m-d H:i:s', $jetzt - 86400 + 3600)], 'id = ?', [(int)$u['id']]);
        }
    }
    return $res;
}
