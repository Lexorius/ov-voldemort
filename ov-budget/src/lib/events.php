<?php
declare(strict_types=1);

/**
 * Veranstaltungen: Planung, Geld, Dateien und Einladungen.
 *
 * Eine Veranstaltung hält zusammen, was sonst über mehrere Module verstreut
 * wäre: Termin und Ort, ein Budgettopf mit geplanten Kosten, die Buchungen,
 * die tatsächlich dafür angefallen sind, Rechnungen und Programme als Datei –
 * und die Gästeliste.
 *
 * Die Gästeliste kommt aus dem Kontaktmodul. Jede eingeladene Person bekommt
 * einen eigenen Einladungscode. Er steht in einer kurzen Adresse
 * (z. B. https://i.example.de/AB12CD); wer sie öffnet, kann zusagen, absagen,
 * Begleiter ankündigen oder eine Vertretung nennen – ohne Zugang zu dieser
 * Anwendung. Ausgeliefert wird diese Seite vom Connector, siehe connector.php.
 */

const EVENT_STATUS = [
    'geplant'       => 'Geplant',
    'laeuft'        => 'Läuft',
    'abgeschlossen' => 'Abgeschlossen',
    'abgesagt'      => 'Abgesagt',
];

const EVENT_ANTWORTEN = [
    'offen'      => 'Offen',
    'zusage'     => 'Zusage',
    'absage'     => 'Absage',
    'vertretung' => 'Vertretung',
];

/**
 * Zeichen für Einladungscodes: Großbuchstaben und Ziffern ohne die, die man
 * am Telefon oder auf Papier verwechselt (0/O, 1/I/L).
 */
const EVENT_CODE_ZEICHEN = '23456789ABCDEFGHJKMNPQRSTUVWXYZ';

function event_status_label(string $status): string
{
    return EVENT_STATUS[$status] ?? $status;
}

function event_antwort_label(string $status): string
{
    return EVENT_ANTWORTEN[$status] ?? $status;
}

/* ==================================================================== */
/* Veranstaltungen                                                       */
/* ==================================================================== */

function event_find(?int $id): ?array
{
    if (!$id) {
        return null;
    }
    return db_row(
        'SELECT e.*, b.name AS budget_name, b.betrag_netto AS budget_betrag,
                fg.label AS fachgruppe_label, t.label AS typ_label, t.color AS typ_color, t.slug AS typ_slug,
                c.name AS connector_name, u.display_name AS ersteller
         FROM events e
         LEFT JOIN budgets    b  ON b.id  = e.budget_id
         LEFT JOIN list_items fg ON fg.id = e.fachgruppe_id
         LEFT JOIN list_items t  ON t.id  = e.typ_id
         LEFT JOIN connectors c  ON c.id  = e.connector_id
         LEFT JOIN users      u  ON u.id  = e.created_by
         WHERE e.id = ?',
        [$id]
    );
}

/**
 * Liste der Veranstaltungen.
 * $f: jahr, status, q, zeit ('kommend'|'vergangen'), sort, limit
 */
function event_query(array $f = []): array
{
    $w = [];
    $p = [];

    if (!empty($f['jahr'])) {
        $w[] = 'e.jahr = ?';
        $p[] = (int)$f['jahr'];
    }
    if (!empty($f['status'])) {
        $w[] = 'e.status = ?';
        $p[] = (string)$f['status'];
    }
    if (!empty($f['typ_id'])) {
        $w[] = 'e.typ_id = ?';
        $p[] = (int)$f['typ_id'];
    }
    if (!empty($f['q'])) {
        $w[] = '(e.titel LIKE ? OR e.beschreibung LIKE ? OR e.ort LIKE ?)';
        $like = '%' . $f['q'] . '%';
        array_push($p, $like, $like, $like);
    }
    if (($f['zeit'] ?? '') === 'kommend') {
        $w[] = "(COALESCE(e.ende, e.beginn) >= NOW() AND e.status <> 'abgeschlossen')";
    } elseif (($f['zeit'] ?? '') === 'vergangen') {
        $w[] = "(COALESCE(e.ende, e.beginn) < NOW() OR e.status = 'abgeschlossen')";
    }

    $order = match ($f['sort'] ?? '') {
        'alt'   => 'e.beginn ASC, e.id ASC',
        'titel' => 'e.titel ASC',
        default => 'e.beginn DESC, e.id DESC',
    };

    $sql = 'SELECT e.*, b.name AS budget_name, fg.label AS fachgruppe_label,
                   t.label AS typ_label, t.color AS typ_color,
                   (SELECT COUNT(*) FROM event_guests g WHERE g.event_id = e.id) AS gaeste,
                   (SELECT COUNT(*) FROM event_guests g WHERE g.event_id = e.id
                     AND g.status IN (\'zusage\',\'vertretung\')) AS zusagen,
                   (SELECT COALESCE(SUM(1 + g.begleiter), 0) FROM event_guests g
                     WHERE g.event_id = e.id AND g.status IN (\'zusage\',\'vertretung\')) AS personen
            FROM events e
            LEFT JOIN budgets    b  ON b.id  = e.budget_id
            LEFT JOIN list_items fg ON fg.id = e.fachgruppe_id
            LEFT JOIN list_items t  ON t.id  = e.typ_id'
        . ($w ? ' WHERE ' . implode(' AND ', $w) : '')
        . ' ORDER BY ' . $order;

    if (!empty($f['limit'])) {
        $sql .= ' LIMIT ' . (int)$f['limit'];
    }
    return db_all($sql, $p);
}

/** Jahre, zu denen es Veranstaltungen gibt – immer mit dem laufenden Jahr */
function event_years(): array
{
    $jahre = array_map(static fn($r) => (int)$r['jahr'], db_all('SELECT DISTINCT jahr FROM events ORDER BY jahr DESC'));
    $aktuell = setting_int('haushaltsjahr', (int)date('Y'));
    if (!in_array($aktuell, $jahre, true)) {
        $jahre[] = $aktuell;
        rsort($jahre);
    }
    return $jahre;
}

/** Datum und Uhrzeit aus zwei Feldern zu einem Zeitpunkt zusammensetzen. Reine Funktion. */
function event_datetime(?string $datum, string $zeit, string $vorgabe = '00:00'): ?string
{
    if ($datum === null || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $datum)) {
        return null;
    }
    $zeit = preg_match('/^(\d{1,2}):(\d{2})/', $zeit, $m)
        ? sprintf('%02d:%02d', min(23, (int)$m[1]), min(59, (int)$m[2]))
        : $vorgabe;
    return $datum . ' ' . $zeit . ':00';
}

/** Veranstaltung aus dem Formular speichern. Gibt [id, fehler[]] zurück. */
function event_save_from_post(?array $e, array $user): array
{
    $fehler = [];

    $titel = mb_substr(post_str('titel'), 0, 200);
    if ($titel === '') {
        $fehler[] = 'Bitte einen Titel angeben.';
    }
    $beginn = event_datetime(post_date('datum'), post_str('beginn_zeit'), '00:00');
    if ($beginn === null) {
        $fehler[] = 'Bitte ein gültiges Datum angeben.';
    }
    // Ein Ende gibt es nur, wenn Datum oder Uhrzeit dafür ausgefüllt sind
    $endeDatum = post_date('ende_datum');
    $endeZeit = post_str('ende_zeit');
    $ende = ($endeDatum !== null || $endeZeit !== '')
        ? event_datetime($endeDatum ?? post_date('datum'), $endeZeit, '23:59')
        : null;
    if ($ende !== null && $beginn !== null && strtotime($ende) < strtotime($beginn)) {
        $fehler[] = 'Das Ende liegt vor dem Beginn.';
    }

    $laenge = max(4, min(16, post_int('code_laenge', setting_int('veranstaltung_code_laenge', 6)) ?? 6));
    $begleiter = max(0, min(50, post_int('begleiter_max', 0) ?? 0));

    $status = post_str('status', 'geplant');
    if (!array_key_exists($status, EVENT_STATUS)) {
        $status = 'geplant';
    }

    if ($fehler) {
        return [null, $fehler];
    }

    $daten = [
        'titel'              => $titel,
        'beschreibung'       => post_str('beschreibung'),
        'ort'                => mb_substr(post_str('ort'), 0, 200),
        'beginn'             => $beginn,
        'ende'               => $ende,
        'status'             => $status,
        'typ_id'             => post_int('typ_id'),
        'jahr'               => (int)substr((string)$beginn, 0, 4),
        'budget_id'          => post_int('budget_id'),
        'fachgruppe_id'      => post_int('fachgruppe_id'),
        'kosten_geplant'     => post_dec('kosten_geplant'),
        'connector_id'       => post_int('connector_id'),
        'code_laenge'        => $laenge,
        'begleiter_max'      => $begleiter,
        'kommentare_erlaubt' => post_bool('kommentare_erlaubt'),
        'vertretung_erlaubt' => post_bool('vertretung_erlaubt'),
        'rueckmeldung_bis'   => post_date('rueckmeldung_bis'),
        'gaesteliste'        => post_bool('gaesteliste'),
        'teilnehmer_geplant' => post_int('teilnehmer_geplant'),
        'teilnehmer_ist'     => post_int('teilnehmer_ist'),
        'verpflegung'        => post_bool('verpflegung'),
        'verpflegung_personen' => post_int('verpflegung_personen'),
        'verpflegung_fruehstueck' => 0,
        'verpflegung_mittag' => 0,
        'verpflegung_abend'  => 0,
        'verpflegung_notiz'  => mb_substr(post_str('verpflegung_notiz'), 0, 255),
        'hinweis'            => post_str('hinweis'),
        'notiz'              => post_str('notiz'),
        'updated_by'         => (int)$user['id'],
    ];
    // Mahlzeiten je Kalendertag: aus dem Tagesraster, sonst aus dem Zeitraum vorgeschlagen
    if ($daten['verpflegung'] === 1) {
        $tage = isset($_POST['vt']) || isset($_POST['vt_da'])
            ? verpflegung_tage_aus_post($_POST['vt'] ?? [], (string)$beginn, $ende)
            : verpflegung_tage_abgleichen(verpflegung_tage_von($e ?? []), (string)$beginn, $ende);
        $summen = verpflegung_summen($tage);
        $daten['verpflegung_tage'] = (string)json_encode($tage);
        $daten['verpflegung_fruehstueck'] = $summen['fruehstueck'];
        $daten['verpflegung_mittag'] = $summen['mittag'];
        $daten['verpflegung_abend'] = $summen['abend'];
    } else {
        $daten['verpflegung_tage'] = null;
    }

    if ($e) {
        db_update('events', $daten, 'id = ?', [(int)$e['id']]);
        $id = (int)$e['id'];
        audit('veranstaltung.bearbeitet', 'event', $id, $titel);
    } else {
        $daten['created_by'] = (int)$user['id'];
        $id = db_insert('events', $daten);
        audit('veranstaltung.angelegt', 'event', $id, $titel);
    }
    return [$id, []];
}

function event_delete(array $e): void
{
    foreach (efile_list((int)$e['id']) as $datei) {
        efile_delete($datei);
    }
    // Buchungen bleiben bestehen, verlieren aber den Bezug
    db_exec('UPDATE expenses SET event_id = NULL WHERE event_id = ?', [(int)$e['id']]);
    db_exec('DELETE FROM events WHERE id = ?', [(int)$e['id']]);
    audit('veranstaltung.geloescht', 'event', (int)$e['id'], (string)$e['titel']);
}

/** Kurznamen der Arten, die ohne Gästeliste auskommen. Reine Funktion. */
function event_typen_ohne_liste(string $roh): array
{
    $out = [];
    foreach (explode(',', $roh) as $slug) {
        $slug = trim($slug);
        if ($slug !== '') {
            $out[] = $slug;
        }
    }
    return $out;
}

/** Kommt diese Art ohne Gästeliste aus? */
function event_typ_ohne_liste(?int $typId): bool
{
    if (!$typId) {
        return false;
    }
    $slug = (string)(list_item($typId)['slug'] ?? '');
    return $slug !== '' && in_array($slug, event_typen_ohne_liste(
        (string)setting('veranstaltung_ohne_gaesteliste', '')), true);
}

/** Wird bei dieser Veranstaltung eine Gästeliste geführt? Reine Funktion. */
function event_mit_gaesteliste(array $e): bool
{
    return (int)($e['gaesteliste'] ?? 1) === 1;
}

/**
 * Wie viele Personen kommen: bei einer Gästeliste die Summe der Zusagen,
 * sonst die eingetragene Zahl. Reine Funktion.
 */
function event_personen(array $e, array $stats): int
{
    if (event_mit_gaesteliste($e)) {
        return (int)($stats['personen'] ?? 0);
    }
    $ist = $e['teilnehmer_ist'] ?? null;
    return (int)($ist !== null && $ist !== '' ? $ist : (int)($e['teilnehmer_geplant'] ?? 0));
}

/* ==================================================================== */
/* Geld                                                                  */
/* ==================================================================== */

/** Was die Veranstaltung bisher gekostet und eingebracht hat */
function event_kosten(int $eventId): array
{
    $res = ['ausgaben' => 0.0, 'einnahmen' => 0.0, 'buchungen' => 0];
    foreach (db_all(
        'SELECT art, COUNT(*) AS anzahl, COALESCE(SUM(betrag_brutto),0) AS brutto
         FROM expenses WHERE event_id = ? AND status <> \'geplant\' GROUP BY art',
        [$eventId]
    ) as $r) {
        $res['buchungen'] += (int)$r['anzahl'];
        if ((string)$r['art'] === 'einnahme') {
            $res['einnahmen'] = (float)$r['brutto'];
        } else {
            $res['ausgaben'] = (float)$r['brutto'];
        }
    }
    return $res;
}

/**
 * Was die Veranstaltungen eines Jahres noch kosten werden: geplante Kosten
 * plus kalkulierte Verpflegung, abzüglich dessen, was schon gebucht ist –
 * je Veranstaltung nie unter null. Abgesagte und abgeschlossene zählen nicht.
 */
function events_geplante_kosten(int $jahr): array
{
    $out = ['gesamt' => 0.0, 'verpflegung' => 0.0, 'anzahl' => 0, 'liste' => []];
    $saetze = null;
    foreach (event_query(['jahr' => $jahr]) as $e) {
        if (!in_array((string)$e['status'], ['geplant', 'laeuft'], true)) {
            continue;
        }
        $verpflegung = 0.0;
        if ((int)($e['verpflegung'] ?? 0) === 1) {
            $saetze ??= tagessatz_query();
            $satz = tagessatz_am($saetze, substr((string)$e['beginn'], 0, 10));
            $personen = verpflegung_personen($e, event_stats(event_guests((int)$e['id'])));
            $verpflegung = verpflegung_kalkulation($e, tagessatz_mahlzeiten($satz), $personen)['gesamt'];
        }
        $geplant = (float)$e['kosten_geplant'] + $verpflegung;
        $gebucht = event_kosten((int)$e['id'])['ausgaben'];
        $rest = max(0.0, round($geplant - $gebucht, 2));
        if ($geplant <= 0) {
            continue;
        }
        $out['anzahl']++;
        $out['gesamt'] += $rest;
        $out['verpflegung'] += min($rest, $verpflegung);
        $out['liste'][] = ['id' => (int)$e['id'], 'titel' => (string)$e['titel'], 'beginn' => (string)$e['beginn'],
            'geplant' => $geplant, 'verpflegung' => $verpflegung, 'gebucht' => $gebucht, 'rest' => $rest];
    }
    $out['gesamt'] = round($out['gesamt'], 2);
    $out['verpflegung'] = round($out['verpflegung'], 2);
    return $out;
}

/** Buchungen einer Veranstaltung */
function event_expenses(int $eventId): array
{
    return db_all(
        'SELECT e.*, ka.label AS kategorie_label, b.name AS budget_name
         FROM expenses e
         LEFT JOIN list_items ka ON ka.id = e.kategorie_id
         LEFT JOIN budgets    b  ON b.id  = e.budget_id
         WHERE e.event_id = ?
         ORDER BY e.datum DESC, e.id DESC',
        [$eventId]
    );
}

/* ==================================================================== */
/* Gäste und Einladungscodes                                             */
/* ==================================================================== */

/**
 * Neuer Einladungscode. Er muss über alle Veranstaltungen hinweg eindeutig
 * sein – in der kurzen Adresse steht nur er.
 */
function event_code_neu(int $laenge): string
{
    $laenge = max(4, min(16, $laenge));
    $zeichen = EVENT_CODE_ZEICHEN;
    $anzahl = strlen($zeichen);
    for ($versuch = 0; $versuch < 20; $versuch++) {
        $code = '';
        for ($i = 0; $i < $laenge; $i++) {
            $code .= $zeichen[random_int(0, $anzahl - 1)];
        }
        if (!db_val('SELECT id FROM event_guests WHERE code = ?', [$code])) {
            return $code;
        }
    }
    throw new RuntimeException('Es ließ sich kein freier Einladungscode finden. '
        . 'Bitte die Länge des Codes erhöhen.');
}

/** Sieht die Zeichenfolge wie ein Code aus? Reine Funktion. */
function event_code_gueltig(string $code): bool
{
    return (bool)preg_match('/^[' . EVENT_CODE_ZEICHEN . ']{4,16}$/', strtoupper(trim($code)));
}

/**
 * So steht eine Veranstaltung beim Connector: als Prüfsumme ihrer Nummer.
 * Reine Funktion.
 */
function event_kennung(array|int $e): string
{
    return hash('sha256', 'ovb-veranstaltung:' . (is_array($e) ? (int)$e['id'] : $e));
}

/** So steht ein Code beim Connector: als Prüfsumme. Reine Funktion. */
function event_code_kennung(string $code): string
{
    return hash('sha256', 'ovb-einladung:' . strtoupper(trim($code)));
}

/** Die Adresse, die in der Einladung steht. Reine Funktion. */
function event_invite_url(array $connector, string $code): string
{
    $kurz = rtrim(trim((string)($connector['kurz_url'] ?? '')), '/');
    if ($kurz !== '') {
        return $kurz . '/' . $code;
    }
    return rtrim(trim((string)($connector['url'] ?? '')), '/') . '/index.php?e=' . rawurlencode($code);
}

/** Gästeliste mit den Angaben aus dem Kontaktmodul */
function event_guests(int $eventId, string $status = ''): array
{
    $w = ['g.event_id = ?'];
    $p = [$eventId];
    if ($status !== '') {
        $w[] = 'g.status = ?';
        $p[] = $status;
    }
    return db_all(
        'SELECT g.*, k.vorname, k.nachname, k.anrede, k.titel AS kontakt_titel,
                k.organisation, k.position, k.email, k.telefon, k.mobil,
                k.strasse, k.plz, k.ort, k.land, k.anschreiben
         FROM event_guests g
         LEFT JOIN contacts k ON k.id = g.contact_id
         WHERE ' . implode(' AND ', $w) . '
         ORDER BY COALESCE(NULLIF(g.name, \'\'), k.nachname, \'\'), k.vorname, g.id',
        $p
    );
}

function event_guest_find(?int $id): ?array
{
    if (!$id) {
        return null;
    }
    return db_row(
        'SELECT g.*, k.vorname, k.nachname, k.anrede, k.titel AS kontakt_titel, k.organisation
         FROM event_guests g
         LEFT JOIN contacts k ON k.id = g.contact_id
         WHERE g.id = ?',
        [$id]
    );
}

function event_guest_by_code(string $code): ?array
{
    return db_row('SELECT * FROM event_guests WHERE code = ?', [strtoupper(trim($code))]);
}

/** Wie die eingeladene Person heißt. Reine Funktion. */
function event_guest_name(array $gast): string
{
    $eigen = trim((string)($gast['name'] ?? ''));
    if ($eigen !== '') {
        return $eigen;
    }
    $name = trim(implode(' ', array_filter([
        trim((string)($gast['vorname'] ?? '')),
        trim((string)($gast['nachname'] ?? '')),
    ])));
    return $name !== '' ? $name : 'Ohne Namen';
}

/**
 * Die Angaben aus dem Kontakt so zusammenstellen, wie das Kontaktmodul sie
 * kennt – für Anschrift und Briefanrede. Reine Funktion.
 */
function event_guest_contact(array $g): array
{
    return [
        'anrede'       => (string)($g['anrede'] ?? ''),
        'titel'        => (string)($g['kontakt_titel'] ?? ''),
        'vorname'      => (string)($g['vorname'] ?? ''),
        'nachname'     => (string)($g['nachname'] ?? ''),
        'organisation' => (string)($g['organisation'] ?? ''),
        'position'     => (string)($g['position'] ?? ''),
        'strasse'      => (string)($g['strasse'] ?? ''),
        'plz'          => (string)($g['plz'] ?? ''),
        'ort'          => (string)($g['ort'] ?? ''),
        'land'         => (string)($g['land'] ?? ''),
        'anschreiben'  => (string)($g['anschreiben'] ?? ''),
    ];
}

/**
 * Anschrift für den Umschlag, Zeile für Zeile. Wer ohne Kontakt auf der
 * Liste steht, hat nur seinen Namen. Reine Funktion.
 */
function event_guest_address(array $g): array
{
    $zeilen = contact_address_lines(event_guest_contact($g));
    if (!$zeilen) {
        $eigen = trim((string)($g['name'] ?? ''));
        return $eigen !== '' ? [$eigen] : [];
    }
    return $zeilen;
}

/** Briefanrede, wie sie das Kontaktmodul bildet */
function event_guest_salutation(array $g): string
{
    return contact_salutation(event_guest_contact($g));
}

/** Reicht das für einen Brief? Reine Funktion. */
function event_guest_postfaehig(array $g): bool
{
    return trim((string)($g['strasse'] ?? '')) !== '' && trim((string)($g['ort'] ?? '')) !== '';
}

/**
 * Eine Person einladen. Gibt die id zurück – oder null, wenn sie schon
 * auf der Liste steht.
 */
function event_guest_add(array $e, ?int $contactId, string $name = ''): ?int
{
    if ($contactId !== null && db_val('SELECT id FROM event_guests WHERE event_id = ? AND contact_id = ?',
            [(int)$e['id'], $contactId])) {
        return null;
    }
    return db_insert('event_guests', [
        'event_id'   => (int)$e['id'],
        'contact_id' => $contactId,
        'name'       => mb_substr(trim($name), 0, 150),
        'code'       => event_code_neu((int)$e['code_laenge']),
    ]);
}

/** Eine ganze Kontaktgruppe einladen. Gibt die Anzahl der neuen Einträge zurück. */
function event_guest_add_group(array $e, int $groupId): int
{
    $n = 0;
    foreach (contact_group_members($groupId) as $k) {
        if (event_guest_add($e, (int)$k['id']) !== null) {
            $n++;
        }
    }
    return $n;
}

function event_guest_remove(array $gast): void
{
    db_exec('DELETE FROM event_guests WHERE id = ?', [(int)$gast['id']]);
}

/** Neuer Code für eine eingeladene Person – der alte gilt dann nicht mehr */
function event_guest_code_neu(array $e, array $gast): string
{
    $code = event_code_neu((int)$e['code_laenge']);
    db_update('event_guests', ['code' => $code], 'id = ?', [(int)$gast['id']]);
    return $code;
}

/**
 * Eine Rückmeldung eintragen – von der Einladungsseite oder von Hand.
 * $quelle: 'einladung' (über den Connector) oder 'mensch'
 */
function event_guest_antwort(array $e, array $gast, array $antwort, string $quelle = 'mensch'): array
{
    $status = (string)($antwort['status'] ?? 'offen');
    if (!array_key_exists($status, EVENT_ANTWORTEN)) {
        $status = 'offen';
    }
    if ($status === 'vertretung' && (int)$e['vertretung_erlaubt'] !== 1) {
        $status = 'zusage';
    }

    $begleiter = max(0, (int)($antwort['begleiter'] ?? 0));
    $begleiter = min($begleiter, (int)$e['begleiter_max']);
    if ($status === 'absage') {
        $begleiter = 0;
    }

    $vertretung = $status === 'vertretung'
        ? mb_substr(trim((string)($antwort['vertretung'] ?? '')), 0, 150)
        : '';

    $kommentar = (int)$e['kommentare_erlaubt'] === 1
        ? mb_substr(trim((string)($antwort['kommentar'] ?? '')), 0, 2000)
        : '';

    $daten = [
        'status'         => $status,
        'begleiter'      => $begleiter,
        'vertretung'     => $vertretung,
        'kommentar'      => $kommentar !== '' ? $kommentar : null,
        'quelle'         => $quelle,
        'geantwortet_am' => $status === 'offen' ? null : date('Y-m-d H:i:s'),
    ];
    db_update('event_guests', $daten, 'id = ?', [(int)$gast['id']]);
    return array_merge($gast, $daten);
}

/**
 * Zahlen zur Gästeliste: wie viele haben zugesagt, wie viele Personen kommen.
 * Reine Funktion – sie rechnet nur die übergebene Liste durch.
 */
function event_stats(array $gaeste): array
{
    $s = ['eingeladen' => count($gaeste), 'offen' => 0, 'zusagen' => 0, 'absagen' => 0,
          'vertretungen' => 0, 'personen' => 0, 'begleiter' => 0, 'kommentare' => 0];
    foreach ($gaeste as $g) {
        $status = (string)$g['status'];
        $begleiter = (int)$g['begleiter'];
        if ($status === 'zusage' || $status === 'vertretung') {
            // Die eingeladene Person (oder ihre Vertretung) zählt mit
            $s['personen'] += 1 + $begleiter;
            $s['begleiter'] += $begleiter;
            $s[$status === 'zusage' ? 'zusagen' : 'vertretungen']++;
        } elseif ($status === 'absage') {
            $s['absagen']++;
        } else {
            $s['offen']++;
        }
        if (trim((string)($g['kommentar'] ?? '')) !== '') {
            $s['kommentare']++;
        }
    }
    return $s;
}

/* ==================================================================== */
/* Dateien                                                               */
/* ==================================================================== */

function efile_dir(): string
{
    $dir = upload_dir() . DIRECTORY_SEPARATOR . 'veranstaltungen';
    if (!is_dir($dir)) {
        @mkdir($dir, 0770, true);
    }
    return $dir;
}

function efile_list(int $eventId): array
{
    return db_all(
        'SELECT f.*, u.display_name AS hochgeladen_von
         FROM event_files f
         LEFT JOIN users u ON u.id = f.uploaded_by
         WHERE f.event_id = ?
         ORDER BY f.created_at DESC, f.id DESC',
        [$eventId]
    );
}

function efile_find(?int $id): ?array
{
    return $id ? db_row('SELECT * FROM event_files WHERE id = ?', [$id]) : null;
}

/** Summe der Beträge, die an Dateien hängen (z. B. Rechnungen) */
function efile_betrag(array $dateien): float
{
    $summe = 0.0;
    foreach ($dateien as $f) {
        $summe += (float)($f['betrag'] ?? 0);
    }
    return $summe;
}

/**
 * Hochgeladene Dateien speichern. Gibt [anzahl, fehler[]] zurück.
 * Bilder werden neu geschrieben – das entfernt den Aufnahmeort aus den
 * Metadaten – und bekommen ein Vorschaubild.
 */
function efile_store_uploads(int $eventId, string $feld, string $titel, ?float $betrag, array $user): array
{
    $fehler = [];
    $anzahl = 0;
    if (empty($_FILES[$feld]) || !is_array($_FILES[$feld]['name'])) {
        return [0, ['Es kam keine Datei an. Bei sehr großen Dateien bricht der Browser '
            . 'den Upload ab, bevor die Anwendung ihn sieht.']];
    }

    $erlaubt = array_values(array_unique(array_merge(vfile_document_types(), VFILE_BILD_TYPEN)));
    $max = upload_max_bytes();
    $dir = efile_dir();
    if (!is_dir($dir) || !is_writable($dir)) {
        return [0, [sprintf('Der Ordner %s ist nicht beschreibbar.', $dir)]];
    }

    foreach ($_FILES[$feld]['name'] as $i => $name) {
        $name = (string)$name;
        $err = (int)$_FILES[$feld]['error'][$i];
        if ($err === UPLOAD_ERR_NO_FILE || $name === '') {
            continue;
        }
        if ($err !== UPLOAD_ERR_OK) {
            $fehler[] = sprintf('„%s" konnte nicht hochgeladen werden (Fehlercode %d).', $name, $err);
            continue;
        }
        $tmp = (string)$_FILES[$feld]['tmp_name'][$i];
        $groesse = (int)$_FILES[$feld]['size'][$i];
        if (!is_uploaded_file($tmp) && !defined('OVB_TEST_UPLOADS')) {
            $fehler[] = sprintf('„%s" wurde nicht über das Formular hochgeladen.', $name);
            continue;
        }

        $mime = class_exists('finfo') ? (string)(new finfo(FILEINFO_MIME_TYPE))->file($tmp) : '';
        $grund = vfile_check($name, $groesse, $mime, 'dokument', $erlaubt, $max);
        if ($grund !== null) {
            $fehler[] = $grund;
            continue;
        }

        $endung = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $basis = date('Ymd_His') . '_' . bin2hex(random_bytes(8));
        $gespeichert = $basis . '.' . $endung;
        $ziel = $dir . DIRECTORY_SEPARATOR . $gespeichert;
        $vorschau = null;

        $istBild = in_array($endung, VFILE_BILD_TYPEN, true) && str_starts_with($mime, 'image/');
        $verarbeitet = $istBild && vfile_process_image($tmp, $ziel, $mime, VFILE_BILD_MAX) !== null;
        if ($verarbeitet) {
            @unlink($tmp);
            $groesse = (int)filesize($ziel);
            $vorschau = $basis . '_vorschau.' . $endung;
            if (vfile_process_image($ziel, $dir . DIRECTORY_SEPARATOR . $vorschau, $mime, VFILE_VORSCHAU_MAX) === null) {
                $vorschau = null;
            }
        } elseif (!(defined('OVB_TEST_UPLOADS') ? rename($tmp, $ziel) : move_uploaded_file($tmp, $ziel))) {
            $fehler[] = sprintf('„%s" konnte nicht gespeichert werden.', $name);
            continue;
        }

        db_insert('event_files', [
            'event_id'    => $eventId,
            'art'         => $istBild ? 'bild' : 'dokument',
            'titel'       => mb_substr(trim($titel) !== '' ? trim($titel) : pathinfo($name, PATHINFO_FILENAME), 0, 200),
            'orig_name'   => mb_substr($name, 0, 255),
            'stored_name' => $gespeichert,
            'thumb_name'  => $vorschau,
            'mime'        => $mime,
            'size_bytes'  => $groesse,
            'betrag'      => $betrag !== null && $betrag > 0 ? $betrag : null,
            'uploaded_by' => (int)$user['id'],
        ]);
        $anzahl++;
    }
    return [$anzahl, $fehler];
}

function efile_delete(array $datei): void
{
    foreach ([$datei['stored_name'] ?? '', $datei['thumb_name'] ?? ''] as $name) {
        if ($name) {
            $pfad = efile_dir() . DIRECTORY_SEPARATOR . basename((string)$name);
            if (is_file($pfad)) {
                @unlink($pfad);
            }
        }
    }
    db_exec('DELETE FROM event_files WHERE id = ?', [(int)$datei['id']]);
}

/** Pfad zur Datei oder zur Vorschau – oder null */
function efile_path(array $datei, bool $vorschau = false): ?string
{
    $name = $vorschau && ($datei['thumb_name'] ?? null) ? $datei['thumb_name'] : $datei['stored_name'];
    $pfad = efile_dir() . DIRECTORY_SEPARATOR . basename((string)$name);
    return is_file($pfad) ? $pfad : null;
}

/* ==================================================================== */
/* Verpflegung: Frühstück, Mittag- und Abendessen je Person              */
/* ==================================================================== */

const VERPFLEGUNG_MAHLZEITEN = ['fruehstueck' => 'Frühstück', 'mittag' => 'Mittagessen', 'abend' => 'Abendessen'];

/* ---------- Tagessätze: je Person und Tag, prozentual verteilt ---------- */

function tagessatz_query(): array
{
    return db_all('SELECT * FROM verpflegung_saetze ORDER BY gueltig_von DESC, id DESC');
}

function tagessatz_find(int $id): ?array
{
    return $id > 0 ? db_row('SELECT * FROM verpflegung_saetze WHERE id = ?', [$id]) : null;
}

/** Der Tagessatz, der an einem Tag gilt – der jüngste passende. Reine Funktion. */
function tagessatz_am(array $saetze, string $datum): ?array
{
    $treffer = null;
    foreach ($saetze as $s) {
        if ((string)$s['gueltig_von'] > $datum) {
            continue;
        }
        $bis = (string)($s['gueltig_bis'] ?? '');
        if ($bis !== '' && $bis < $datum) {
            continue;
        }
        if ($treffer === null || (string)$s['gueltig_von'] > (string)$treffer['gueltig_von']) {
            $treffer = $s;
        }
    }
    return $treffer;
}

/** Aus Tagessatz und Anteilen die Beträge je Mahlzeit. Reine Funktion. */
function tagessatz_mahlzeiten(?array $satz): array
{
    $tag = (float)($satz['tagessatz'] ?? 0);
    return [
        'fruehstueck' => round($tag * (int)($satz['anteil_fruehstueck'] ?? 0) / 100, 2),
        'mittag'      => round($tag * (int)($satz['anteil_mittag'] ?? 0) / 100, 2),
        'abend'       => round($tag * (int)($satz['anteil_abend'] ?? 0) / 100, 2),
    ];
}

/** Sätze je Mahlzeit für eine Veranstaltung: der Tagessatz am Beginn */
function verpflegung_saetze(?string $datum = null): array
{
    return tagessatz_mahlzeiten(tagessatz_am(tagessatz_query(), substr($datum ?? date('Y-m-d'), 0, 10)));
}

/** Tagessatz aus dem Formular speichern. Rückgabe: [id, fehler[]] */
function tagessatz_save_from_post(?array $existing): array
{
    $errors = [];
    $von = post_date('gueltig_von');
    if (!$von) {
        $errors[] = 'Bitte angeben, ab wann der Tagessatz gilt.';
    }
    $bis = post_date('gueltig_bis');
    if ($von && $bis && $bis < $von) {
        $errors[] = 'Das Ende liegt vor dem Anfang.';
    }
    $tag = post_dec('tagessatz');
    if ($tag <= 0) {
        $errors[] = 'Bitte einen Tagessatz größer als null angeben.';
    }
    $anteile = [];
    foreach (['fruehstueck', 'mittag', 'abend'] as $k) {
        $anteile[$k] = max(0, min(100, post_int('anteil_' . $k, 0) ?? 0));
    }
    if (array_sum($anteile) !== 100) {
        $errors[] = sprintf('Die drei Anteile ergeben %d %% – sie müssen zusammen 100 %% ergeben.', array_sum($anteile));
    }
    if ($errors) {
        return [null, $errors];
    }
    $data = [
        'gueltig_von'        => $von,
        'gueltig_bis'        => $bis,
        'tagessatz'          => round($tag, 2),
        'anteil_fruehstueck' => $anteile['fruehstueck'],
        'anteil_mittag'      => $anteile['mittag'],
        'anteil_abend'       => $anteile['abend'],
        'notiz'              => mb_substr(post_str('notiz'), 0, 150),
    ];
    if ($existing) {
        db_update('verpflegung_saetze', $data, 'id = ?', [(int)$existing['id']]);
        $id = (int)$existing['id'];
        audit('tagessatz.bearbeitet', 'tagessatz', $id, money($data['tagessatz']));
    } else {
        $id = db_insert('verpflegung_saetze', $data);
        audit('tagessatz.angelegt', 'tagessatz', $id, money($data['tagessatz']));
    }
    return [$id, []];
}

function tagessatz_delete(array $satz): void
{
    db_exec('DELETE FROM verpflegung_saetze WHERE id = ?', [(int)$satz['id']]);
    audit('tagessatz.geloescht', 'tagessatz', (int)$satz['id'], money((float)$satz['tagessatz']));
}

/** Arten, bei denen Verpflegung von vornherein angehakt ist (Slugs) */
function verpflegung_typen(): array
{
    return event_typen_ohne_liste((string)setting('veranstaltung_verpflegung', 'ausbildung,uebung,einsatz'));
}

function event_typ_verpflegung(?int $typId): bool
{
    if (!$typId) {
        return false;
    }
    $slug = (string)(list_item($typId)['slug'] ?? '');
    return $slug !== '' && in_array($slug, verpflegung_typen(), true);
}

/**
 * Wie viele Mahlzeiten je Person der Zeitraum nahelegt: je Kalendertag ein
 * Frühstück, wenn die Veranstaltung um 8 Uhr schon läuft, ein Mittagessen,
 * wenn sie 12:30 Uhr umfasst, ein Abendessen bei 18:30 Uhr. Reine Funktion.
 */
function verpflegung_vorschlag(string $beginn, ?string $ende): array
{
    $a = strtotime($beginn);
    $b = $ende ? strtotime($ende) : false;
    if ($a === false) {
        return ['fruehstueck' => 0, 'mittag' => 0, 'abend' => 0];
    }
    if ($b === false || $b < $a) {
        $b = strtotime(date('Y-m-d', $a) . ' 23:59:00');
    }
    $out = ['fruehstueck' => 0, 'mittag' => 0, 'abend' => 0];
    for ($tag = strtotime(date('Y-m-d', $a)); $tag <= $b; $tag += 86400) {
        foreach (['fruehstueck' => '08:00', 'mittag' => '12:30', 'abend' => '18:30'] as $key => $uhr) {
            $t = strtotime(date('Y-m-d', $tag) . ' ' . $uhr);
            if ($t >= $a && $t <= $b) {
                $out[$key]++;
            }
        }
    }
    return $out;
}

/**
 * Die Kalendertage der Veranstaltung mit den Mahlzeiten, die der Zeitraum
 * nahelegt – je Tag Frühstück (8:00), Mittag (12:30), Abend (18:30), wenn
 * die Veranstaltung zu der Zeit läuft. Reine Funktion.
 * Rückgabe: [datum => ['fruehstueck' => bool, 'mittag' => bool, 'abend' => bool]]
 */
function verpflegung_tage(string $beginn, ?string $ende): array
{
    $a = strtotime($beginn);
    if ($a === false) {
        return [];
    }
    $b = $ende ? strtotime($ende) : false;
    if ($b === false || $b < $a) {
        $b = strtotime(date('Y-m-d', $a) . ' 23:59:00');
    }
    $out = [];
    for ($tag = strtotime(date('Y-m-d', $a)); $tag <= $b && count($out) < 60; $tag += 86400) {
        $datum = date('Y-m-d', $tag);
        $zeile = [];
        foreach (['fruehstueck' => '08:00', 'mittag' => '12:30', 'abend' => '18:30'] as $key => $uhr) {
            $t = strtotime($datum . ' ' . $uhr);
            $zeile[$key] = $t >= $a && $t <= $b;
        }
        $out[$datum] = $zeile;
    }
    return $out;
}

/**
 * Gespeicherte Tage mit dem Zeitraum abgleichen: Tage außerhalb fallen weg,
 * neue Tage bekommen den Vorschlag. Reine Funktion.
 */
function verpflegung_tage_abgleichen(array $gespeichert, string $beginn, ?string $ende): array
{
    $out = [];
    foreach (verpflegung_tage($beginn, $ende) as $datum => $vorschlag) {
        $out[$datum] = isset($gespeichert[$datum]) && is_array($gespeichert[$datum])
            ? ['fruehstueck' => !empty($gespeichert[$datum]['fruehstueck']),
               'mittag' => !empty($gespeichert[$datum]['mittag']),
               'abend' => !empty($gespeichert[$datum]['abend'])]
            : $vorschlag;
    }
    return $out;
}

/** Aus dem Formular (vt[datum][mahlzeit]) die Tage lesen. Reine Funktion. */
function verpflegung_tage_aus_post(mixed $vt, string $beginn, ?string $ende): array
{
    $roh = [];
    foreach (is_array($vt) ? $vt : [] as $datum => $mahlzeiten) {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$datum) && is_array($mahlzeiten)) {
            $roh[(string)$datum] = $mahlzeiten;
        }
    }
    // Ein Tag, den das Formular kennt, aber ohne Häkchen schickt, ist bewusst leer
    $out = [];
    foreach (verpflegung_tage($beginn, $ende) as $datum => $vorschlag) {
        $out[$datum] = isset($roh[$datum])
            ? ['fruehstueck' => !empty($roh[$datum]['fruehstueck']), 'mittag' => !empty($roh[$datum]['mittag']),
               'abend' => !empty($roh[$datum]['abend'])]
            : $vorschlag;
    }
    return $out;
}

/** Summen je Mahlzeit aus den Tagen. Reine Funktion. */
function verpflegung_summen(array $tage): array
{
    $out = ['fruehstueck' => 0, 'mittag' => 0, 'abend' => 0];
    foreach ($tage as $t) {
        foreach ($out as $k => $n) {
            if (!empty($t[$k])) {
                $out[$k]++;
            }
        }
    }
    return $out;
}

/** Gespeicherte Tage einer Veranstaltung (JSON) */
function verpflegung_tage_von(array $e): array
{
    $roh = json_decode((string)($e['verpflegung_tage'] ?? ''), true);
    return is_array($roh) ? $roh : [];
}

/** "1 Tag 18 Std." – wie lange die Veranstaltung geht. Reine Funktion. */
function event_dauer_text(string $beginn, ?string $ende): string
{
    $a = strtotime($beginn);
    $b = $ende ? strtotime($ende) : false;
    if ($a === false || $b === false || $b <= $a) {
        return '';
    }
    $minuten = (int)round(($b - $a) / 60);
    $tage = intdiv($minuten, 1440);
    $stunden = intdiv($minuten % 1440, 60);
    $rest = $minuten % 60;
    $teile = [];
    if ($tage > 0) {
        $teile[] = $tage . ($tage === 1 ? ' Tag' : ' Tage');
    }
    if ($stunden > 0) {
        $teile[] = $stunden . ' Std.';
    }
    if ($rest > 0 && $tage === 0) {
        $teile[] = $rest . ' Min.';
    }
    return implode(' ', $teile);
}

/** Für wie viele Personen gerechnet wird: eigene Zahl, sonst wie die Teilnehmer. Reine Funktion. */
function verpflegung_personen(array $e, array $stats): int
{
    $eigene = $e['verpflegung_personen'] ?? null;
    if ($eigene !== null && $eigene !== '') {
        return max(0, (int)$eigene);
    }
    return event_personen($e, $stats);
}

/**
 * Die Rechnung: je Mahlzeit Anzahl × Satz × Personen, dazu die Summe.
 * Reine Funktion. Rückgabe: ['personen', 'zeilen' => [key => [label, anzahl, satz, betrag]], 'gesamt', 'ohne_satz']
 */
function verpflegung_kalkulation(array $e, array $saetze, int $personen): array
{
    $out = ['personen' => $personen, 'zeilen' => [], 'gesamt' => 0.0, 'ohne_satz' => false];
    foreach (VERPFLEGUNG_MAHLZEITEN as $key => $label) {
        $anzahl = max(0, (int)($e['verpflegung_' . $key] ?? 0));
        $satz = (float)($saetze[$key] ?? 0);
        $betrag = round($anzahl * $satz * $personen, 2);
        if ($anzahl > 0 && $satz <= 0) {
            $out['ohne_satz'] = true;
        }
        $out['zeilen'][$key] = ['label' => $label, 'anzahl' => $anzahl, 'satz' => $satz, 'betrag' => $betrag];
        $out['gesamt'] += $betrag;
    }
    $out['gesamt'] = round($out['gesamt'], 2);
    return $out;
}
