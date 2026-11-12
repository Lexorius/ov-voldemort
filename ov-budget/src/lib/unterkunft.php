<?php
declare(strict_types=1);

/**
 * Unterkunft: alles, was am und im Gebäude zu tun ist, an einer Stelle.
 *
 * Kern sind die offenen Punkte – Mängel (auch aus einem Divera-Formular) und
 * Aufgaben am Gebäude – mit Ort aus den Stell- und Lagerplätzen, Dringlichkeit,
 * Zuständigkeit, Frist und einem Verlauf, der jeden Schritt festhält. Darum
 * herum hängt die Übersicht die Module zusammen, die es schon gibt: Räume
 * und Lagerflächen, Gebäudekosten aus den Objektkonten des Budgets, Zähler.
 *
 * Status kommen aus der Auswahlliste „unterkunft_status", Dringlichkeiten
 * teilen sich die Liste der Aufgaben („todo_prioritaet").
 */

const UNTERKUNFT_ARTEN = ['mangel' => 'Mangel', 'aufgabe' => 'Aufgabe'];
/** Status, die gegenüber Divera als „In Bearbeitung" gelten */
const UNTERKUNFT_IN_ARBEIT = ['in-arbeit', 'beauftragt', 'wartet'];

function unterkunft_art(string $art): string
{
    return array_key_exists($art, UNTERKUNFT_ARTEN) ? $art : 'mangel';
}

/* ==================================================================== */
/*  Lesen                                                                 */
/* ==================================================================== */

function unterkunft_punkt_select(): string
{
    return 'SELECT p.*, st.label AS status_label, st.color AS status_color, st.slug AS status_slug, COALESCE(st.is_final, 0) AS status_final,
                   pr.label AS prioritaet_label, pr.color AS prioritaet_color, COALESCE(pr.weight, 0) AS prioritaet_gewicht,
                   so.name AS standort_name, zu.display_name AS zustaendig_name, me.display_name AS melder_user_name
            FROM unterkunft_punkte p
            LEFT JOIN list_items st ON st.id = p.status_id
            LEFT JOIN list_items pr ON pr.id = p.prioritaet_id
            LEFT JOIN standorte so  ON so.id = p.standort_id
            LEFT JOIN users zu      ON zu.id = p.zustaendig_id
            LEFT JOIN users me      ON me.id = p.melder_user_id';
}

function unterkunft_punkt_find(?int $id): ?array
{
    return $id > 0 ? db_row(unterkunft_punkt_select() . ' WHERE p.id = ?', [$id]) : null;
}

/**
 * Punkte nach Filter. $f: zustand ('offen' Vorgabe, 'erledigt', 'alle'), art,
 * status_id, prioritaet_id, standort_ids (Liste), ohne_ort, zustaendig_id, q.
 * Offene zuerst, dann nach Dringlichkeit, Frist und Alter.
 */
function unterkunft_punkte(array $f = []): array
{
    $w = [];
    $p = [];
    $zustand = (string)($f['zustand'] ?? 'offen');
    if ($zustand === 'offen') {
        $w[] = 'COALESCE(st.is_final, 0) = 0';
    } elseif ($zustand === 'erledigt') {
        $w[] = 'COALESCE(st.is_final, 0) = 1';
    }
    if (!empty($f['art'])) {
        $w[] = 'p.art = ?';
        $p[] = unterkunft_art((string)$f['art']);
    }
    foreach (['status_id', 'prioritaet_id', 'zustaendig_id'] as $spalte) {
        if (!empty($f[$spalte])) {
            $w[] = 'p.' . $spalte . ' = ?';
            $p[] = (int)$f[$spalte];
        }
    }
    if (!empty($f['standort_ids'])) {
        $ids = array_values(array_unique(array_map('intval', (array)$f['standort_ids'])));
        $w[] = 'p.standort_id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')';
        array_push($p, ...$ids);
    } elseif (!empty($f['ohne_ort'])) {
        $w[] = 'p.standort_id IS NULL';
    }
    if (trim((string)($f['q'] ?? '')) !== '') {
        $like = '%' . trim((string)$f['q']) . '%';
        $w[] = '(p.titel LIKE ? OR p.beschreibung LIKE ? OR p.ort_text LIKE ? OR p.melder_name LIKE ?)';
        array_push($p, $like, $like, $like, $like);
    }
    return db_all(
        unterkunft_punkt_select() . ($w ? ' WHERE ' . implode(' AND ', $w) : '')
        . ' ORDER BY COALESCE(st.is_final, 0), COALESCE(pr.weight, 0) DESC, p.faellig_am IS NULL, p.faellig_am, p.created_at, p.id',
        $p
    );
}

/** Offene Punkte an diesen Plätzen – für die Seite eines Platzes */
function unterkunft_offen_anzahl(array $standortIds): int
{
    $ids = array_values(array_unique(array_filter(array_map('intval', $standortIds))));
    if (!$ids) {
        return 0;
    }
    return (int)db_val(
        'SELECT COUNT(*) FROM unterkunft_punkte p LEFT JOIN list_items st ON st.id = p.status_id
         WHERE COALESCE(st.is_final, 0) = 0 AND p.standort_id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')',
        $ids,
        0
    );
}

function unterkunft_log(int $punktId): array
{
    return db_all(
        'SELECT l.*, u.display_name AS user_name, a.label AS alt_label, n.label AS neu_label, n.color AS neu_color
         FROM unterkunft_log l
         LEFT JOIN users u ON u.id = l.user_id
         LEFT JOIN list_items a ON a.id = l.status_alt
         LEFT JOIN list_items n ON n.id = l.status_neu
         WHERE l.punkt_id = ? ORDER BY l.created_at, l.id',
        [$punktId]
    );
}

/* ==================================================================== */
/*  Reine Funktionen                                                      */
/* ==================================================================== */

/** Vergleichsform für Ortsnamen: klein, ohne Leer- und Satzzeichen, Umlaute aufgelöst. Reine Funktion. */
function unterkunft_norm(string $text): string
{
    $t = strtr(mb_strtolower(trim($text)), ['ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss']);
    return (string)preg_replace('/[^a-z0-9]/', '', $t);
}

/**
 * Den Platz zu einem Freitext finden („Küche", „Halle 1 / Tor 3", „K1"):
 * erst der genaue Name oder das Kürzel, dann der Pfad, dann der längste
 * Name, der im Text vorkommt. Nur eindeutige Treffer. Reine Funktion.
 */
function unterkunft_standort_finden(string $text, array $standorte): ?int
{
    $soll = unterkunft_norm($text);
    if ($soll === '') {
        return null;
    }
    $genau = [];
    foreach ($standorte as $s) {
        if (unterkunft_norm((string)$s['name']) === $soll || ((string)($s['kurz'] ?? '') !== '' && unterkunft_norm((string)$s['kurz']) === $soll)) {
            $genau[] = (int)$s['id'];
        }
    }
    if (count($genau) === 1) {
        return $genau[0];
    }
    $nachId = array_column($standorte, null, 'id');
    $pfad = static function (array $s) use ($nachId): string {
        $teile = [];
        $sicher = 0;
        while ($s && $sicher++ < 12) {
            array_unshift($teile, (string)$s['name']);
            $s = $nachId[(int)($s['parent_id'] ?? 0)] ?? null;
        }
        return unterkunft_norm(implode('', $teile));
    };
    $treffer = [];
    foreach ($standorte as $s) {
        $p = $pfad($s);
        if ($p === $soll || (mb_strlen($soll) >= 6 && str_ends_with($p, $soll))) {
            $treffer[] = (int)$s['id'];
        }
    }
    if (count($treffer) === 1) {
        return $treffer[0];
    }
    // Der längste Name, der im Text steckt – „Wasserhahn in der Küche tropft" findet „Küche"
    $beste = [];
    $laenge = 0;
    foreach ($standorte as $s) {
        $n = unterkunft_norm((string)$s['name']);
        if (mb_strlen($n) >= 4 && str_contains($soll, $n)) {
            if (mb_strlen($n) > $laenge) {
                $beste = [(int)$s['id']];
                $laenge = mb_strlen($n);
            } elseif (mb_strlen($n) === $laenge) {
                $beste[] = (int)$s['id'];
            }
        }
    }
    return count($beste) === 1 ? $beste[0] : null;
}

/** Der oberste Platz über einem Platz – das Gebäude, die Halle, der Hof. Reine Funktion. */
function unterkunft_wurzel(int $standortId, array $standorte): ?int
{
    $nachId = array_column($standorte, null, 'id');
    $s = $nachId[$standortId] ?? null;
    $sicher = 0;
    while ($s && (int)($s['parent_id'] ?? 0) > 0 && isset($nachId[(int)$s['parent_id']]) && $sicher++ < 12) {
        $s = $nachId[(int)$s['parent_id']];
    }
    return $s ? (int)$s['id'] : null;
}

/** Ort eines Punkts als Text: Pfad des Platzes, sonst der Freitext. Reine Funktion. */
function unterkunft_ort_text(array $p, array $standorte): string
{
    $id = (int)($p['standort_id'] ?? 0);
    if ($id > 0 && function_exists('standort_pfad')) {
        $pfad = standort_pfad($id, $standorte);
        if ($pfad !== '') {
            return $pfad;
        }
    }
    if ($id > 0 && trim((string)($p['standort_name'] ?? '')) !== '') {
        return (string)$p['standort_name'];
    }
    return trim((string)($p['ort_text'] ?? ''));
}

/** Tage seit der Meldung. Reine Funktion. */
function unterkunft_alter_tage(array $p, ?int $jetzt = null): int
{
    $ts = strtotime((string)($p['created_at'] ?? '')) ?: ($jetzt ?? time());
    return max(0, (int)floor((($jetzt ?? time()) - $ts) / 86400));
}

/** Ist die Frist eines offenen Punkts überschritten? Reine Funktion. */
function unterkunft_ueberfaellig(array $p, ?string $heute = null): bool
{
    return (int)($p['status_final'] ?? 0) === 0 && (string)($p['faellig_am'] ?? '') !== '' && (string)$p['faellig_am'] < ($heute ?? date('Y-m-d'));
}

/** Kennzahlen über die offenen Punkte. Reine Funktion. */
function unterkunft_stats(array $offene, ?int $jetzt = null): array
{
    $jetzt ??= time();
    $heute = date('Y-m-d', $jetzt);
    $s = ['offen' => count($offene), 'maengel' => 0, 'aufgaben' => 0, 'ueberfaellig' => 0, 'ohne_zustaendig' => 0, 'aeltester_tage' => 0, 'aus_divera' => 0];
    foreach ($offene as $p) {
        $s[(string)$p['art'] === 'aufgabe' ? 'aufgaben' : 'maengel']++;
        if (unterkunft_ueberfaellig($p, $heute)) {
            $s['ueberfaellig']++;
        }
        if ((int)($p['zustaendig_id'] ?? 0) === 0) {
            $s['ohne_zustaendig']++;
        }
        if ((string)($p['divera_entry_id'] ?? '') !== '') {
            $s['aus_divera']++;
        }
        $s['aeltester_tage'] = max($s['aeltester_tage'], unterkunft_alter_tage($p, $jetzt));
    }
    return $s;
}

/**
 * Die Gebäude, Hallen und Höfe mit dem, was darunter liegt und offen ist.
 * Rückgabe je Wurzel: platz, darunter (Zählung nach Typ), offen. Dazu
 * 'ohne' = offene Punkte ohne Platz. Reine Funktion.
 */
function unterkunft_gebaeude(array $standorte, array $offene): array
{
    $wurzeln = [];
    foreach ($standorte as $s) {
        if ((int)($s['parent_id'] ?? 0) === 0 && (int)($s['is_active'] ?? 1) === 1) {
            $wurzeln[(int)$s['id']] = ['platz' => $s, 'darunter' => [], 'ids' => [(int)$s['id']], 'offen' => 0];
        }
    }
    foreach ($standorte as $s) {
        $w = unterkunft_wurzel((int)$s['id'], $standorte);
        if ($w !== null && isset($wurzeln[$w]) && (int)$s['id'] !== $w && (int)($s['is_active'] ?? 1) === 1) {
            $typ = (string)$s['typ'];
            $wurzeln[$w]['darunter'][$typ] = ($wurzeln[$w]['darunter'][$typ] ?? 0) + 1;
            $wurzeln[$w]['ids'][] = (int)$s['id'];
        }
    }
    $ohne = 0;
    foreach ($offene as $p) {
        $w = (int)($p['standort_id'] ?? 0) > 0 ? unterkunft_wurzel((int)$p['standort_id'], $standorte) : null;
        if ($w !== null && isset($wurzeln[$w])) {
            $wurzeln[$w]['offen']++;
        } else {
            $ohne++;
        }
    }
    return ['gebaeude' => array_values($wurzeln), 'ohne' => $ohne];
}

/** „3 Räume · 2 Stellplätze" aus der Zählung nach Typ. Reine Funktion. */
function unterkunft_darunter_text(array $zaehlung): string
{
    $teile = [];
    foreach ((defined('STANDORT_TYPEN') ? STANDORT_TYPEN : []) as $typ => $def) {
        $n = (int)($zaehlung[$typ] ?? 0);
        if ($n > 0) {
            $teile[] = $n . ' ' . ($n === 1 ? $def['label'] : $def['mehrzahl']);
        }
    }
    return implode(' · ', $teile);
}

/** Darf diese Person den Punkt ändern? Leitung immer, sonst wer ihn gemeldet hat, solange er offen ist. */
function unterkunft_darf_aendern(array $p, ?array $user): bool
{
    if (can('manage_unterkunft')) {
        return true;
    }
    return $user !== null && can('report_unterkunft') && (int)($p['status_final'] ?? 0) === 0
        && ((int)($p['created_by'] ?? 0) === (int)($user['id'] ?? -1) || (int)($p['melder_user_id'] ?? 0) === (int)($user['id'] ?? -1));
}

/* ==================================================================== */
/*  Schreiben                                                             */
/* ==================================================================== */

/** Eintrag im Verlauf – jeder Schritt bleibt nachvollziehbar */
function unterkunft_log_add(int $punktId, ?array $user, string $text, ?int $statusAlt = null, ?int $statusNeu = null, string $quelle = 'mensch'): void
{
    db_insert('unterkunft_log', [
        'punkt_id'   => $punktId,
        'user_id'    => $user['id'] ?? null,
        'autor'      => mb_substr((string)($user['display_name'] ?? $user['username'] ?? ($quelle === 'divera' ? 'Divera' : '')), 0, 150),
        'quelle'     => in_array($quelle, ['divera', 'system'], true) ? $quelle : 'mensch',
        'text'       => $text,
        'status_alt' => $statusAlt,
        'status_neu' => $statusNeu,
        'created_at' => date('Y-m-d H:i:s'),
    ]);
}

/**
 * Punkt aus dem Formular speichern. Gibt [id, fehler] zurück. Wer nur
 * melden darf, setzt Titel, Beschreibung, Ort und Dringlichkeit; Status,
 * Zuständigkeit und Frist gehören der Leitung.
 */
function unterkunft_punkt_save_from_post(?array $existing, array $user): array
{
    $verwalten = can('manage_unterkunft');
    $titel = mb_substr(post_str('titel'), 0, 200);
    $fehler = [];
    if ($titel === '') {
        $fehler[] = 'Bitte kurz sagen, worum es geht.';
    }
    $standortId = post_int('standort_id');
    if ($standortId !== null && $standortId > 0 && function_exists('standort_find') && !standort_find($standortId)) {
        $standortId = null;
    }
    if ($fehler) {
        return [null, $fehler];
    }

    $d = [
        'titel'         => $titel,
        'beschreibung'  => post_str('beschreibung'),
        'art'           => unterkunft_art(post_str('art', (string)($existing['art'] ?? 'mangel'))),
        'standort_id'   => $standortId ?: null,
        'ort_text'      => mb_substr(post_str('ort_text'), 0, 200),
        'prioritaet_id' => post_int('prioritaet_id') ?: list_default_id('todo_prioritaet'),
    ];
    if ($verwalten) {
        $d['zustaendig_id'] = post_int('zustaendig_id') ?: null;
        $d['faellig_am'] = post_date('faellig_am');
        $d['firma'] = mb_substr(post_str('firma'), 0, 150);
    }

    if ($existing) {
        $id = (int)$existing['id'];
        db_update('unterkunft_punkte', $d, 'id = ?', [$id]);
        $aenderungen = [];
        foreach (['titel' => 'Titel', 'standort_id' => 'Ort', 'ort_text' => 'Ort', 'prioritaet_id' => 'Dringlichkeit', 'zustaendig_id' => 'Zuständigkeit', 'faellig_am' => 'Frist', 'firma' => 'Firma', 'art' => 'Art', 'beschreibung' => 'Beschreibung'] as $feld => $name) {
            if (array_key_exists($feld, $d) && (string)($existing[$feld] ?? '') !== (string)($d[$feld] ?? '')) {
                $aenderungen[$name] = true;
            }
        }
        if ($aenderungen) {
            unterkunft_log_add($id, $user, 'Geändert: ' . implode(', ', array_keys($aenderungen)));
        }
        audit('unterkunft.punkt_geaendert', 'unterkunft', $id, $titel);
        return [$id, []];
    }

    $d['status_id'] = list_default_id('unterkunft_status');
    $d['melder_user_id'] = (int)$user['id'];
    $d['melder_name'] = mb_substr((string)($user['display_name'] ?? $user['username'] ?? ''), 0, 150);
    $d['created_by'] = (int)$user['id'];
    $d['created_at'] = date('Y-m-d H:i:s');
    $id = db_insert('unterkunft_punkte', $d);
    unterkunft_log_add($id, $user, UNTERKUNFT_ARTEN[$d['art']] . ' gemeldet', null, $d['status_id']);
    audit('unterkunft.punkt_gemeldet', 'unterkunft', $id, $titel);
    return [$id, []];
}

/** Status setzen, mit Notiz im Verlauf; ein abschließender Status trägt das Erledigt-Datum ein */
function unterkunft_status_setzen(array $p, int $statusId, string $notiz, array $user): bool
{
    $neu = function_exists('list_item') ? list_item($statusId) : null;
    if (!$neu || (string)($neu['list_key'] ?? 'unterkunft_status') !== 'unterkunft_status') {
        return false;
    }
    $alt = (int)($p['status_id'] ?? 0);
    $notiz = trim($notiz);
    if ($alt === $statusId && $notiz === '') {
        return false;
    }
    if ($alt !== $statusId) {
        db_update('unterkunft_punkte', [
            'status_id'   => $statusId,
            'erledigt_am' => (int)($neu['is_final'] ?? 0) === 1 ? date('Y-m-d H:i:s') : null,
        ], 'id = ?', [(int)$p['id']]);
        audit('unterkunft.status', 'unterkunft', (int)$p['id'], (string)$neu['label']);
    }
    unterkunft_log_add((int)$p['id'], $user, $notiz, $alt !== $statusId ? ($alt ?: null) : null, $alt !== $statusId ? $statusId : null);
    return true;
}

function unterkunft_punkt_delete(array $p): void
{
    db_exec('DELETE FROM unterkunft_punkte WHERE id = ?', [(int)$p['id']]);
    audit('unterkunft.punkt_geloescht', 'unterkunft', (int)$p['id'], (string)$p['titel']);
}

/* ==================================================================== */
/*  Divera: Defektformular                                                */
/* ==================================================================== */

/**
 * Einen Eintrag des Defektformulars in einen Mangel übersetzen. Reine
 * Funktion über Zuordnung, Formular, Plätze und Benutzer.
 */
function unterkunft_aus_divera(array $entry, array $map, array $form, array $standorte = [], array $userByName = []): array
{
    $f = $entry['fields'];
    $get = static fn(string $ziel) => trim(divera_field_value($f, (string)($map[$ziel] ?? '')));

    $beschreibung = $get('beschreibung');
    $titel = $get('titel');
    if ($titel === '') {
        $ersteZeile = trim((string)strtok($beschreibung, "\n"));
        $titel = $ersteZeile !== '' ? mb_substr($ersteZeile, 0, 120) : 'Defekt aus Divera ' . ($entry['id'] ?: date('Y-m-d H:i'));
    }

    $ortText = $get('ort');
    if ($ortText === '' && trim((string)($entry['adresse'] ?? '')) !== '') {
        $ortText = trim((string)$entry['adresse']);
    }
    $standortId = $ortText !== '' ? unterkunft_standort_finden($ortText, $standorte) : null;
    // Steht der Raum nur im Titel oder in der Beschreibung, dort suchen
    $standortId ??= unterkunft_standort_finden($titel, $standorte);

    $prText = $get('prioritaet');
    $prioritaetId = $prText !== '' ? list_id_by_text('todo_prioritaet', $prText) : null;
    $prioritaetId ??= list_default_id('todo_prioritaet');

    $name = $get('melder') ?: trim((string)($entry['user'] ?? ''));
    $userId = $name !== '' ? ($userByName[mb_strtolower($name)] ?? null) : null;

    // Nicht zugeordnete Felder anhängen – nichts geht verloren
    $used = array_filter(array_values($map));
    $rest = [];
    foreach ($f as $k => $v) {
        if ($v === '' || in_array($k, $used, true)) {
            continue;
        }
        $rest[] = $k . ': ' . $v;
    }
    if (!empty($entry['anhaenge'])) {
        $rest[] = sprintf('In Divera hängen %d Datei(en) an diesem Eintrag.', (int)$entry['anhaenge']);
    }
    if ($rest) {
        $beschreibung = trim($beschreibung . "\n\n— Weitere Angaben aus Divera —\n" . implode("\n", $rest));
    }

    $zeit = $entry['date'] ?? null;
    $gemeldet = is_numeric($zeit) ? date('Y-m-d H:i:s', (int)$zeit) : (is_string($zeit) && strtotime($zeit) ? date('Y-m-d H:i:s', (int)strtotime($zeit)) : date('Y-m-d H:i:s'));

    return [
        'titel'           => mb_substr($titel, 0, 200),
        'beschreibung'    => $beschreibung,
        'art'             => 'mangel',
        'standort_id'     => $standortId,
        'ort_text'        => $standortId ? '' : mb_substr($ortText, 0, 200),
        'prioritaet_id'   => $prioritaetId,
        'status_id'       => !empty($form['default_status_id']) ? (int)$form['default_status_id'] : list_default_id('unterkunft_status'),
        'melder_user_id'  => $userId,
        'melder_name'     => mb_substr($name, 0, 150),
        'divera_form_id'  => (string)$form['form_id'],
        'divera_entry_id' => (string)$entry['id'],
        'divera_status'   => $entry['status'] ?? null,
        'created_at'      => $gemeldet,
    ];
}

/**
 * Gewünschter Divera-Status eines Mangels: abschließend → Abgeschlossen (4),
 * in Arbeit, beauftragt oder wartend → In Bearbeitung (2), sonst
 * Weitergeleitet (6). Reine Funktion.
 */
function unterkunft_divera_zielstatus(array $p): int
{
    if ((int)($p['status_final'] ?? 0) === 1) {
        return 4;
    }
    return in_array((string)($p['status_slug'] ?? ''), UNTERKUNFT_IN_ARBEIT, true) ? 2 : 6;
}
