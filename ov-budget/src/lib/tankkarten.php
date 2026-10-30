<?php
declare(strict_types=1);

/**
 * Tankkarten des Ortsverbands – und Ladekarten für E-Fahrzeuge.
 *
 * Eine Karte gehört zu einem Anbieter (DKV, Shell, Aral, ein Ladenetz …),
 * hat eine Nummer, einen Status und ist Fahrzeugen zugeordnet: eine Karte
 * für mehrere Fahrzeuge oder mehrere Karten für ein Fahrzeug, beides geht.
 * Daneben kann sie bei einer Person liegen – der Zugführerin im Portemonnaie,
 * dem Schirrmeister im Schrank.
 *
 * Die Art sagt, wofür sie freischaltet: Kraftstoff an der Zapfsäule, Laden
 * an der Säule, oder beides. Anbieter und Status kommen aus den
 * Auswahllisten und lassen sich in der Verwaltung ändern.
 *
 * Die PIN steht hier, damit sie nicht auf dem Zettel im Handschuhfach liegt.
 * Sie ist der Leitung vorbehalten und wird in der Liste verdeckt angezeigt.
 */

/** Wofür die Karte freischaltet */
const TANKKARTE_ARTEN = [
    'kraftstoff' => 'Kraftstoff',
    'laden'      => 'Laden (E-Fahrzeug)',
    'beides'     => 'Kraftstoff und Laden',
];

function tankkarte_art(string $art): string
{
    return array_key_exists($art, TANKKARTE_ARTEN) ? $art : 'kraftstoff';
}

/** Schaltet die Karte Laden frei? Reine Funktion. */
function tankkarte_laedt(array $k): bool
{
    return in_array((string)($k['art'] ?? 'kraftstoff'), ['laden', 'beides'], true);
}

/** Schaltet die Karte Kraftstoff frei? Reine Funktion. */
function tankkarte_tankt(array $k): bool
{
    return in_array((string)($k['art'] ?? 'kraftstoff'), ['kraftstoff', 'beides'], true);
}

/** Ist die PIN hinterlegt? Reine Funktion. */
function tankkarte_hat_pin(array $k): bool
{
    return (int)($k['hat_pin'] ?? 0) === 1;
}

/** Verdeckte Anzeige der PIN. Reine Funktion. */
function tankkarte_verdeckt(string $wert): string
{
    $wert = trim($wert);
    return $wert === '' ? '' : str_repeat('•', max(4, mb_strlen($wert)));
}

/* ==================================================================== */
/*  Lesen                                                                 */
/* ==================================================================== */

function tankkarte_find(?int $id): ?array
{
    if (!$id) {
        return null;
    }
    $k = db_row(tankkarte_select() . ' WHERE k.id = ?', [$id]);
    if ($k) {
        $k = tankkarten_mit_fahrzeugen([$k])[0];
    }
    return $k;
}

/** Gemeinsamer Rumpf der Abfragen – mit allem, was die Anzeige braucht */
function tankkarte_select(): string
{
    return 'SELECT k.*,
                   a.label AS anbieter_label, a.color AS anbieter_color,
                   st.label AS status_label, st.color AS status_color, st.slug AS status_slug, st.is_final AS status_final,
                   u.display_name AS person_label
            FROM tankkarten k
            LEFT JOIN list_items a  ON a.id  = k.anbieter_id
            LEFT JOIN list_items st ON st.id = k.status_id
            LEFT JOIN users      u  ON u.id  = k.person_id';
}

/** Die zugeordneten Fahrzeuge an die Karten hängen (Schlüssel „fahrzeuge") */
function tankkarten_mit_fahrzeugen(array $karten): array
{
    $ids = array_values(array_unique(array_map(static fn($k) => (int)$k['id'], $karten)));
    $je = [];
    if ($ids) {
        $zeilen = db_all(
            'SELECT kf.tankkarte_id, v.id, v.bezeichnung, v.kennzeichen, v.is_active
             FROM tankkarte_fahrzeuge kf
             JOIN vehicles v ON v.id = kf.vehicle_id
             WHERE kf.tankkarte_id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')
             ORDER BY v.bezeichnung',
            $ids
        );
        foreach ($zeilen as $z) {
            $je[(int)$z['tankkarte_id']][] = ['id' => (int)$z['id'], 'bezeichnung' => (string)$z['bezeichnung'],
                'kennzeichen' => (string)$z['kennzeichen'], 'is_active' => (int)$z['is_active']];
        }
    }
    foreach ($karten as &$k) {
        $k['fahrzeuge'] = $je[(int)$k['id']] ?? [];
    }
    unset($k);
    return $karten;
}

/**
 * Liste der Karten.
 * $f: q, anbieter_id, status_id, art, vehicle_id, aktiv ('alle'), sort
 */
function tankkarte_query(array $f = []): array
{
    $w = [];
    $p = [];
    $join = '';

    if (!empty($f['q'])) {
        $w[] = '(k.nummer LIKE ? OR k.kundennummer LIKE ? OR k.aufbewahrung LIKE ? OR k.notiz LIKE ? OR a.label LIKE ?)';
        $like = '%' . $f['q'] . '%';
        array_push($p, $like, $like, $like, $like, $like);
    }
    foreach (['anbieter_id', 'status_id'] as $spalte) {
        if (!empty($f[$spalte])) {
            $w[] = 'k.' . $spalte . ' = ?';
            $p[] = (int)$f[$spalte];
        }
    }
    if (!empty($f['art'])) {
        // „laden" findet auch Karten, die beides können – und umgekehrt
        $art = tankkarte_art((string)$f['art']);
        $w[] = $art === 'beides' ? 'k.art = ?' : '(k.art = ? OR k.art = \'beides\')';
        $p[] = $art;
    }
    if (!empty($f['vehicle_id'])) {
        $join = ' JOIN tankkarte_fahrzeuge kf ON kf.tankkarte_id = k.id AND kf.vehicle_id = ?';
        array_unshift($p, (int)$f['vehicle_id']);
    }
    if (($f['aktiv'] ?? '') !== 'alle') {
        $w[] = 'k.is_active = 1';
    }

    $order = match ($f['sort'] ?? '') {
        'nummer'  => 'k.nummer ASC',
        'gueltig' => 'k.gueltig_bis IS NULL, k.gueltig_bis ASC',
        'neu'     => 'k.created_at DESC',
        default   => 'a.sort_order, a.label, k.art, k.nummer',
    };

    $sql = tankkarte_select();
    // Die Fahrzeug-Einschränkung muss vor den LEFT JOINs stehen: Parameter in Reihenfolge
    if ($join !== '') {
        $sql = str_replace('FROM tankkarten k', 'FROM tankkarten k' . $join, $sql);
    }
    return tankkarten_mit_fahrzeugen(db_all($sql . ($w ? ' WHERE ' . implode(' AND ', $w) : '') . ' ORDER BY ' . $order, $p));
}

/** Karten eines Fahrzeugs – für die Fahrzeugakte und das Tankformular */
function tankkarten_for_vehicle(int $vehicleId): array
{
    return tankkarte_query(['vehicle_id' => $vehicleId]);
}

/* ==================================================================== */
/*  Reine Funktionen für die Anzeige                                      */
/* ==================================================================== */

/** Kurzer Name: „DKV …4711" – Anbieter und die letzten Stellen der Nummer. Reine Funktion. */
function tankkarte_bezeichnung(array $k): string
{
    $nummer = trim((string)($k['nummer'] ?? ''));
    $anbieter = trim((string)($k['anbieter_label'] ?? ''));
    $kurz = mb_strlen($nummer) > 6 ? '…' . mb_substr($nummer, -4) : $nummer;
    $s = trim($anbieter . ' ' . $kurz);
    return $s !== '' ? $s : 'Tankkarte';
}

/** Welche Fahrzeuge, als Text. Reine Funktion. */
function tankkarte_fahrzeuge_text(array $k): string
{
    $namen = [];
    foreach ($k['fahrzeuge'] ?? [] as $v) {
        $namen[] = (string)$v['bezeichnung'] . ((string)($v['kennzeichen'] ?? '') !== '' ? ' · ' . (string)$v['kennzeichen'] : '');
    }
    return implode(', ', $namen);
}

/**
 * Wie lange gilt die Karte noch? Reine Funktion.
 * Rückgabe: ['tage' => int|null, 'stufe' => 'ok'|'bald'|'faellig'|'offen']
 */
function tankkarte_gueltig(array $k, int $warnTage = 60, ?string $heute = null): array
{
    $bis = trim((string)($k['gueltig_bis'] ?? ''));
    if ($bis === '') {
        return ['tage' => null, 'stufe' => 'offen'];
    }
    $heute ??= date('Y-m-d');
    $tage = (int)floor((strtotime($bis) - strtotime($heute)) / 86400);
    return ['tage' => $tage, 'stufe' => $tage < 0 ? 'faellig' : ($tage <= $warnTage ? 'bald' : 'ok')];
}

/** Zahlen für den Kopf der Liste. Reine Funktion. */
function tankkarte_stats(array $karten, int $warnTage = 60, ?string $heute = null): array
{
    $s = ['anzahl' => count($karten), 'laden' => 0, 'ohne_fahrzeug' => 0, 'gueltig_bald' => 0, 'anbieter' => []];
    foreach ($karten as $k) {
        if (tankkarte_laedt($k)) {
            $s['laden']++;
        }
        if (!($k['fahrzeuge'] ?? [])) {
            $s['ohne_fahrzeug']++;
        }
        if (in_array(tankkarte_gueltig($k, $warnTage, $heute)['stufe'], ['bald', 'faellig'], true)) {
            $s['gueltig_bald']++;
        }
        $a = trim((string)($k['anbieter_label'] ?? '')) ?: 'ohne Anbieter';
        $s['anbieter'][$a] = ($s['anbieter'][$a] ?? 0) + 1;
    }
    arsort($s['anbieter']);
    return $s;
}

/* ==================================================================== */
/*  Schreiben                                                             */
/* ==================================================================== */

/** Karte aus dem Formular speichern. Gibt [id, fehler[]] zurück. */
function tankkarte_save_from_post(?array $karte, array $user): array
{
    $fehler = [];
    $nummer = mb_substr(preg_replace('/\s+/', ' ', post_str('nummer')) ?? '', 0, 60);
    $anbieterId = post_int('anbieter_id');
    $art = tankkarte_art(post_str('art', 'kraftstoff'));
    $hatPin = post_bool('hat_pin');

    if ($nummer === '') {
        $fehler[] = 'Bitte die Kartennummer angeben – sie steht auf der Karte.';
    }
    if (!$anbieterId) {
        $fehler[] = 'Bitte den Anbieter wählen. Fehlt er, lässt er sich unter Verwaltung → Auswahllisten anlegen.';
    }
    // Dieselbe Nummer beim selben Anbieter ist fast immer ein Versehen
    if ($nummer !== '' && $anbieterId) {
        $doppelt = db_val('SELECT id FROM tankkarten WHERE nummer = ? AND anbieter_id = ? AND id <> ?',
            [$nummer, $anbieterId, (int)($karte['id'] ?? 0)]);
        if ($doppelt) {
            $fehler[] = 'Diese Kartennummer steht bei diesem Anbieter schon auf einer anderen Karte.';
        }
    }

    // Fahrzeuge: nur, wenn das Feld im Formular war (Auswahl mit „+ Hinzufügen")
    $fahrzeuge = isset($_POST['fahrzeuge_feld']) ? wahl_ids('fahrzeuge') : null;

    if ($fehler) {
        return [null, $fehler];
    }

    $daten = [
        'nummer'        => $nummer,
        'anbieter_id'   => $anbieterId,
        'art'           => $art,
        'status_id'     => post_int('status_id'),
        'kundennummer'  => mb_substr(post_str('kundennummer'), 0, 60),
        'gueltig_bis'   => post_date('gueltig_bis'),
        'hat_pin'       => $hatPin,
        'pin'           => $hatPin ? mb_substr(post_str('pin'), 0, 20) : '',
        'person_id'     => post_int('person_id'),
        'aufbewahrung'  => mb_substr(post_str('aufbewahrung'), 0, 150),
        'ausgegeben_am' => post_date('ausgegeben_am'),
        'notiz'         => post_str('notiz'),
        'is_active'     => post_bool('is_active'),
        'updated_by'    => (int)$user['id'],
    ];

    if ($karte) {
        $id = (int)$karte['id'];
        db_update('tankkarten', $daten, 'id = ?', [$id]);
        audit('tankkarte.bearbeitet', 'tankkarte', $id, $nummer);
    } else {
        $daten['created_by'] = (int)$user['id'];
        $id = db_insert('tankkarten', $daten);
        audit('tankkarte.angelegt', 'tankkarte', $id, $nummer);
    }
    if ($fahrzeuge !== null) {
        tankkarte_fahrzeuge_setzen($id, $fahrzeuge);
    }
    return [$id, []];
}

/** Zuordnung zu Fahrzeugen neu schreiben */
function tankkarte_fahrzeuge_setzen(int $id, array $vehicleIds): void
{
    db_exec('DELETE FROM tankkarte_fahrzeuge WHERE tankkarte_id = ?', [$id]);
    foreach (array_values(array_unique(array_map('intval', $vehicleIds))) as $vid) {
        if ($vid > 0) {
            db_insert('tankkarte_fahrzeuge', ['tankkarte_id' => $id, 'vehicle_id' => $vid]);
        }
    }
}

/** Ein Fahrzeug an eine Karte hängen – etwa aus der Fahrzeugakte */
function tankkarte_fahrzeug_hinzu(array $karte, int $vehicleId, array $user): void
{
    $ids = array_map(static fn($v) => (int)$v['id'], $karte['fahrzeuge'] ?? []);
    if (!in_array($vehicleId, $ids, true)) {
        $ids[] = $vehicleId;
        tankkarte_fahrzeuge_setzen((int)$karte['id'], $ids);
        db_update('tankkarten', ['updated_by' => (int)$user['id']], 'id = ?', [(int)$karte['id']]);
        audit('tankkarte.zugeordnet', 'tankkarte', (int)$karte['id'], 'fahrzeug:' . $vehicleId);
    }
}

function tankkarte_delete(array $karte): void
{
    db_exec('DELETE FROM tankkarten WHERE id = ?', [(int)$karte['id']]);
    audit('tankkarte.geloescht', 'tankkarte', (int)$karte['id'], (string)$karte['nummer']);
}

/**
 * Auswahl fürs Tankformular: Karten des Fahrzeugs zuerst, dann die übrigen
 * geführten. Reine Funktion über zwei Listen.
 */
function tankkarte_optionen(array $eigene, array $alle): array
{
    $eigeneIds = array_map(static fn($k) => (int)$k['id'], $eigene);
    $gruppen = ['Diesem Fahrzeug zugeordnet' => [], 'Weitere Karten' => []];
    foreach ($eigene as $k) {
        $gruppen['Diesem Fahrzeug zugeordnet'][] = $k;
    }
    foreach ($alle as $k) {
        if (!in_array((int)$k['id'], $eigeneIds, true)) {
            $gruppen['Weitere Karten'][] = $k;
        }
    }
    return array_filter($gruppen);
}
