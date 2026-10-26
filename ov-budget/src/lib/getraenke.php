<?php
declare(strict_types=1);

/**
 * Getränke: Lieferanten, Getränkearten, Lager je Stell- und Lagerplatz,
 * Leermeldungen vom QR-Code, Bestellungen per E-Mail, Abrechnung ins Budget.
 *
 * Der Weg einer Bestellung:
 *   Leermeldung (QR am Lagerplatz, über den Connector) oder Mindestbestand
 *   → Bedarf je Lieferant → Bestellung als Entwurf, von einer verantwortlichen
 *   Person geprüft → per E-Mail an den Lieferanten (Status „bestellt")
 *   → Lieferung eintragen: Zugang ins Lager (Status „geliefert")
 *   → Rechnung abrechnen: Ausgabe im Budget (Status „abgerechnet")
 *
 * Das Lager führt je Getränk und Platz eine Zahl Gebinde (Kästen). Jede
 * Änderung ist eine Bewegung: Zugang, Abgang oder Inventur (neuer Stand).
 */

const GETRAENKE_STAND = ['leer' => 'leer', 'knapp' => 'wird knapp', 'ok' => 'reicht noch'];
const GETRAENKE_BESTELL_STATUS = ['entwurf' => 'Entwurf', 'bestellt' => 'bestellt', 'geliefert' => 'geliefert', 'abgerechnet' => 'abgerechnet'];
const GETRAENKE_BEWEGUNG = ['zugang' => 'Zugang', 'abgang' => 'Abgang', 'inventur' => 'Inventur'];

/* ==================================================================== */
/* Lieferanten                                                           */
/* ==================================================================== */

function getraenke_lieferanten(bool $nurAktive = false): array
{
    return db_all('SELECT l.*, (SELECT COUNT(*) FROM getraenke g WHERE g.lieferant_id = l.id AND g.is_active = 1) AS getraenke_anzahl
                   FROM getraenke_lieferanten l' . ($nurAktive ? ' WHERE l.is_active = 1' : '') . ' ORDER BY l.name');
}

function getraenke_lieferant_find(?int $id): ?array
{
    return $id ? db_row('SELECT * FROM getraenke_lieferanten WHERE id = ?', [$id]) : null;
}

/** Lieferant aus dem Formular. Rückgabe: [id, fehler[]] */
function getraenke_lieferant_save_from_post(?array $l): array
{
    $fehler = [];
    $name = mb_substr(trim(post_str('name')), 0, 100);
    $email = trim(post_str('email'));
    if ($name === '') {
        $fehler[] = 'Bitte einen Namen angeben.';
    }
    if ($email !== '' && !mail_adresse_gueltig($email)) {
        $fehler[] = 'Die E-Mail-Adresse ist ungültig.';
    }
    if ($fehler) {
        return [null, $fehler];
    }
    $daten = [
        'name'         => $name,
        'email'        => mb_substr($email, 0, 150),
        'telefon'      => mb_substr(trim(post_str('telefon')), 0, 60),
        'kundennummer' => mb_substr(trim(post_str('kundennummer')), 0, 60),
        'strasse'      => mb_substr(trim(post_str('strasse')), 0, 150),
        'plz'          => mb_substr(trim(post_str('plz')), 0, 15),
        'ort'          => mb_substr(trim(post_str('ort')), 0, 100),
        'notiz'        => post_str('notiz'),
        'is_active'    => post_bool('is_active') ? 1 : 0,
    ];
    if ($l) {
        db_update('getraenke_lieferanten', $daten, 'id = ?', [(int)$l['id']]);
        $id = (int)$l['id'];
        audit('getraenke.lieferant_bearbeitet', 'getraenke_lieferant', $id, $name);
    } else {
        $id = db_insert('getraenke_lieferanten', $daten);
        audit('getraenke.lieferant_angelegt', 'getraenke_lieferant', $id, $name);
    }
    return [$id, []];
}

/** Anschrift des Lieferanten in einer Zeile: "Musterstraße 1, 12345 Musterstadt". Reine Funktion. */
function getraenke_lieferant_anschrift(array $l): string
{
    $ort = trim(trim((string)($l['plz'] ?? '')) . ' ' . trim((string)($l['ort'] ?? '')));
    return implode(', ', array_filter([trim((string)($l['strasse'] ?? '')), $ort]));
}

/** Löschen – nur ohne Bestellungen; Getränke verlieren ihren Lieferanten. Rückgabe: Fehlertext oder null */
function getraenke_lieferant_delete(array $l): ?string
{
    if ((int)db_val('SELECT COUNT(*) FROM getraenke_bestellungen WHERE lieferant_id = ?', [(int)$l['id']], 0) > 0) {
        return 'Zu diesem Lieferanten gibt es Bestellungen – besser auf „nicht aktiv" setzen.';
    }
    db_exec('UPDATE getraenke SET lieferant_id = NULL WHERE lieferant_id = ?', [(int)$l['id']]);
    db_exec('DELETE FROM getraenke_lieferanten WHERE id = ?', [(int)$l['id']]);
    audit('getraenke.lieferant_geloescht', 'getraenke_lieferant', (int)$l['id'], (string)$l['name']);
    return null;
}

/* ==================================================================== */
/* Getränke                                                              */
/* ==================================================================== */

function getraenke_alle(bool $nurAktive = false): array
{
    return db_all('SELECT g.*, l.name AS lieferant_name, l.is_active AS lieferant_aktiv
                   FROM getraenke g LEFT JOIN getraenke_lieferanten l ON l.id = g.lieferant_id'
        . ($nurAktive ? ' WHERE g.is_active = 1' : '') . ' ORDER BY g.sort_order, g.name');
}

function getraenk_find(?int $id): ?array
{
    return $id ? db_row('SELECT g.*, l.name AS lieferant_name FROM getraenke g LEFT JOIN getraenke_lieferanten l ON l.id = g.lieferant_id WHERE g.id = ?', [$id]) : null;
}

/** Getränk aus dem Formular. Rückgabe: [id, fehler[]] */
function getraenk_save_from_post(?array $g): array
{
    $fehler = [];
    $name = mb_substr(trim(post_str('name')), 0, 100);
    if ($name === '') {
        $fehler[] = 'Bitte einen Namen angeben – etwa „Wasser still" oder „Apfelschorle".';
    }
    $lieferantId = post_int('lieferant_id');
    if ($lieferantId > 0 && getraenke_lieferant_find($lieferantId) === null) {
        $fehler[] = 'Diesen Lieferanten gibt es nicht.';
    }
    if ($fehler) {
        return [null, $fehler];
    }
    $daten = [
        'name'          => $name,
        'lieferant_id'  => $lieferantId > 0 ? $lieferantId : null,
        'gebinde'       => mb_substr(trim(post_str('gebinde')) ?: 'Kasten', 0, 60),
        'preis'         => round(max(0.0, post_dec('preis')), 2),
        'mindestbestand' => max(0, min(9999, post_int('mindestbestand'))),
        'sort_order'    => post_int('sort_order'),
        'notiz'         => mb_substr(trim(post_str('notiz')), 0, 200),
        'is_active'     => post_bool('is_active') ? 1 : 0,
    ];
    if ($g) {
        db_update('getraenke', $daten, 'id = ?', [(int)$g['id']]);
        $id = (int)$g['id'];
        audit('getraenke.bearbeitet', 'getraenk', $id, $name);
    } else {
        $id = db_insert('getraenke', $daten);
        audit('getraenke.angelegt', 'getraenk', $id, $name);
    }
    return [$id, []];
}

/** Löschen – nur ohne Bestellpositionen. Rückgabe: Fehlertext oder null */
function getraenk_delete(array $g): ?string
{
    if ((int)db_val('SELECT COUNT(*) FROM getraenke_positionen WHERE getraenk_id = ?', [(int)$g['id']], 0) > 0) {
        return 'Dieses Getränk steht in Bestellungen – besser auf „nicht aktiv" setzen.';
    }
    db_exec('DELETE FROM getraenke_bewegungen WHERE getraenk_id = ?', [(int)$g['id']]);
    db_exec('DELETE FROM getraenke_bestand WHERE getraenk_id = ?', [(int)$g['id']]);
    db_exec('DELETE FROM getraenke_meldungen WHERE getraenk_id = ?', [(int)$g['id']]);
    db_exec('DELETE FROM getraenke WHERE id = ?', [(int)$g['id']]);
    audit('getraenke.geloescht', 'getraenk', (int)$g['id'], (string)$g['name']);
    return null;
}

/** Getränke nach Lieferant gruppieren: [lieferant_id => [getraenke]]; 0 = ohne Lieferant. Reine Funktion. */
function getraenke_nach_lieferant(array $getraenke): array
{
    $out = [];
    foreach ($getraenke as $g) {
        $out[(int)($g['lieferant_id'] ?? 0)][] = $g;
    }
    ksort($out);
    return $out;
}

/** "Wasser still (Kasten)" oder "Wasser still". Reine Funktion. */
function getraenk_text(array $g): string
{
    $gebinde = trim((string)($g['gebinde'] ?? ''));
    return (string)$g['name'] . ($gebinde !== '' ? ' (' . $gebinde . ')' : '');
}

/* ==================================================================== */
/* Lager                                                                 */
/* ==================================================================== */

/** Alle Bestandszeilen: getraenk_id, standort_id, anzahl */
function getraenke_bestand_alle(): array
{
    return db_all('SELECT b.*, s.name AS standort_name FROM getraenke_bestand b JOIN standorte s ON s.id = b.standort_id ORDER BY s.name');
}

/** Summe je Getränk über alle Plätze. Reine Funktion. */
function getraenke_bestand_summe(array $bestand): array
{
    $out = [];
    foreach ($bestand as $b) {
        $out[(int)$b['getraenk_id']] = ($out[(int)$b['getraenk_id']] ?? 0) + (int)$b['anzahl'];
    }
    return $out;
}

/**
 * Getränke × Plätze als Tabelle. Reine Funktion.
 * Rückgabe: ['plaetze' => [standort_id => name], 'zeilen' => [getraenk_id => ['je' => [standort_id => anzahl], 'summe' => n]]]
 */
function getraenke_bestand_matrix(array $getraenke, array $bestand): array
{
    $plaetze = [];
    $zeilen = [];
    foreach ($getraenke as $g) {
        $zeilen[(int)$g['id']] = ['je' => [], 'summe' => 0];
    }
    foreach ($bestand as $b) {
        $gid = (int)$b['getraenk_id'];
        $sid = (int)$b['standort_id'];
        $plaetze[$sid] = (string)($b['standort_name'] ?? ('Platz ' . $sid));
        if (!isset($zeilen[$gid])) {
            continue;
        }
        $zeilen[$gid]['je'][$sid] = (int)$b['anzahl'];
        $zeilen[$gid]['summe'] += (int)$b['anzahl'];
    }
    asort($plaetze);
    return ['plaetze' => $plaetze, 'zeilen' => $zeilen];
}

/** Plätze, an denen Getränke liegen oder ein QR-Code hängt – die Lagerplätze */
function getraenke_lagerplaetze(): array
{
    return db_all("SELECT s.* FROM standorte s
                   WHERE s.is_active = 1 AND (s.getraenke_qr_token <> ''
                      OR EXISTS (SELECT 1 FROM getraenke_bestand b WHERE b.standort_id = s.id)
                      OR EXISTS (SELECT 1 FROM getraenke_meldungen m WHERE m.standort_id = s.id))
                   ORDER BY s.name");
}

/** Bestand eines Platzes: [getraenk_id => anzahl] */
function getraenke_bestand_platz(int $standortId): array
{
    $out = [];
    foreach (db_all('SELECT getraenk_id, anzahl FROM getraenke_bestand WHERE standort_id = ?', [$standortId]) as $b) {
        $out[(int)$b['getraenk_id']] = (int)$b['anzahl'];
    }
    return $out;
}

/**
 * Eine Bewegung buchen und den Bestand nachführen. Zugang und Abgang
 * verschieben um $anzahl, Inventur setzt auf $anzahl. Rückgabe: neuer Bestand.
 */
function getraenke_buchen(int $getraenkId, int $standortId, string $art, int $anzahl, ?array $user, string $notiz = '', ?int $bestellungId = null, string $melder = ''): int
{
    if (!isset(GETRAENKE_BEWEGUNG[$art])) {
        throw new InvalidArgumentException('Unbekannte Bewegung: ' . $art);
    }
    $anzahl = max(0, min(99999, $anzahl));
    $bisher = (int)db_val('SELECT anzahl FROM getraenke_bestand WHERE getraenk_id = ? AND standort_id = ?', [$getraenkId, $standortId], -1);
    $neu = match ($art) {
        'zugang'   => max(0, $bisher) + $anzahl,
        'abgang'   => max(0, max(0, $bisher) - $anzahl),
        'inventur' => $anzahl,
    };
    if ($bisher < 0) {
        db_insert('getraenke_bestand', ['getraenk_id' => $getraenkId, 'standort_id' => $standortId, 'anzahl' => $neu]);
    } else {
        db_update('getraenke_bestand', ['anzahl' => $neu], 'getraenk_id = ? AND standort_id = ?', [$getraenkId, $standortId]);
    }
    db_insert('getraenke_bewegungen', [
        'getraenk_id'   => $getraenkId,
        'standort_id'   => $standortId,
        'art'           => $art,
        'anzahl'        => $anzahl,
        'bestand_neu'   => $neu,
        'bestellung_id' => $bestellungId,
        'melder'        => mb_substr($melder, 0, 60),
        'notiz'         => mb_substr($notiz, 0, 200),
        'created_by'    => $user !== null ? (int)($user['id'] ?? 0) ?: null : null,
    ]);
    return $neu;
}

/** Die letzten Bewegungen – eines Platzes oder aller */
function getraenke_bewegungen(int $standortId = 0, int $limit = 40): array
{
    return db_all('SELECT w.*, g.name AS getraenk_name, g.gebinde, s.name AS standort_name, u.display_name AS benutzer
                   FROM getraenke_bewegungen w
                   JOIN getraenke g ON g.id = w.getraenk_id
                   JOIN standorte s ON s.id = w.standort_id
                   LEFT JOIN users u ON u.id = w.created_by'
        . ($standortId > 0 ? ' WHERE w.standort_id = ' . $standortId : '')
        . ' ORDER BY w.id DESC LIMIT ' . max(1, min(500, $limit)));
}

/* ==================================================================== */
/* Leermeldungen vom QR-Code                                             */
/* ==================================================================== */

/** Bezeichnung für den Anker des QR-Codes. Reine Funktion. */
function getraenke_qr_name(array $standort): string
{
    return trim((string)($standort['name'] ?? ''));
}

/**
 * Eine entschlüsselte Meldung vom Lagerplatz übernehmen. Je Getränk:
 * „leer" setzt den Bestand auf null und merkt Bedarf, „wird knapp" merkt
 * Bedarf, „reicht noch" erledigt offenen Bedarf. Eine Zahl dazu ist eine
 * Inventur. Rückgabe: Zahl der übernommenen Getränke.
 */
function getraenke_meldung_anwenden(array $standort, array $daten, int $ts): int
{
    $wann = date('Y-m-d H:i:s', $ts > 0 ? $ts : time());
    $melder = mb_substr(trim((string)($daten['melder'] ?? '')), 0, 60);
    $aktiv = [];
    foreach (getraenke_alle(true) as $g) {
        $aktiv[(int)$g['id']] = $g;
    }
    $n = 0;
    foreach ((array)($daten['meldungen'] ?? []) as $m) {
        if (!is_array($m)) {
            continue;
        }
        $gid = (int)($m['id'] ?? 0);
        $stand = (string)($m['stand'] ?? '');
        if (!isset($aktiv[$gid]) || !isset(GETRAENKE_STAND[$stand])) {
            continue;
        }
        $anzahl = isset($m['anzahl']) && $m['anzahl'] !== null && $m['anzahl'] !== '' && is_numeric($m['anzahl']) ? max(0, (int)$m['anzahl']) : null;
        if ($stand === 'leer') {
            $anzahl = 0;
        }
        if ($anzahl !== null) {
            getraenke_buchen($gid, (int)$standort['id'], 'inventur', $anzahl, null, 'Meldung vom QR-Code', null, $melder);
        }
        if ($stand === 'ok') {
            db_update('getraenke_meldungen', ['erledigt_am' => $wann],
                'getraenk_id = ? AND standort_id = ? AND erledigt_am IS NULL', [$gid, (int)$standort['id']]);
        } else {
            // Offene Meldung desselben Getränks am selben Platz ersetzen, nicht stapeln
            db_exec('DELETE FROM getraenke_meldungen WHERE getraenk_id = ? AND standort_id = ? AND erledigt_am IS NULL', [$gid, (int)$standort['id']]);
            db_insert('getraenke_meldungen', [
                'standort_id' => (int)$standort['id'],
                'getraenk_id' => $gid,
                'stand'       => $stand,
                'anzahl'      => $anzahl,
                'melder'      => $melder,
                'gemeldet_am' => $wann,
            ]);
        }
        $n++;
    }
    if ($n > 0) {
        audit('getraenke.meldung', 'standort', (int)$standort['id'], sprintf('%d Getränk(e) gemeldet%s', $n, $melder !== '' ? ' (' . $melder . ')' : ''));
    }
    return $n;
}

/** Offene Meldungen mit Getränk, Lieferant und Platz, neueste zuerst */
function getraenke_meldungen_offen(): array
{
    return db_all('SELECT m.*, g.name AS getraenk_name, g.gebinde, g.lieferant_id, s.name AS standort_name
                   FROM getraenke_meldungen m
                   JOIN getraenke g ON g.id = m.getraenk_id
                   JOIN standorte s ON s.id = m.standort_id
                   WHERE m.erledigt_am IS NULL ORDER BY m.gemeldet_am DESC');
}

function getraenke_meldung_erledigen(int $id, ?int $bestellungId = null): void
{
    db_update('getraenke_meldungen', ['erledigt_am' => date('Y-m-d H:i:s'), 'bestellung_id' => $bestellungId], 'id = ? AND erledigt_am IS NULL', [$id]);
}

/** Marke für den Stand. Reine Funktion. */
function getraenke_stand_badge(string $stand): string
{
    $farbe = match ($stand) {
        'leer'  => '#b91c1c',
        'knapp' => '#a16207',
        default => '#15803d',
    };
    return '<span class="badge" style="background:' . $farbe . '">' . e(GETRAENKE_STAND[$stand] ?? $stand) . '</span>';
}

/* ==================================================================== */
/* Bestellungen                                                          */
/* ==================================================================== */

function getraenke_bestellungen(array $status = []): array
{
    $w = '';
    $p = [];
    if ($status) {
        $w = ' WHERE b.status IN (' . implode(',', array_fill(0, count($status), '?')) . ')';
        $p = array_values($status);
    }
    return db_all('SELECT b.*, l.name AS lieferant_name, s.name AS standort_name,
                          (SELECT COALESCE(SUM(p.anzahl), 0) FROM getraenke_positionen p WHERE p.bestellung_id = b.id) AS gebinde_anzahl,
                          (SELECT COALESCE(SUM(p.anzahl * p.preis), 0) FROM getraenke_positionen p WHERE p.bestellung_id = b.id) AS summe
                   FROM getraenke_bestellungen b
                   LEFT JOIN getraenke_lieferanten l ON l.id = b.lieferant_id
                   LEFT JOIN standorte s ON s.id = b.standort_id' . $w . ' ORDER BY b.id DESC', $p);
}

function getraenke_bestellung_find(?int $id): ?array
{
    return $id ? db_row('SELECT b.*, l.name AS lieferant_name, l.email AS lieferant_email, l.kundennummer, s.name AS standort_name
                         FROM getraenke_bestellungen b
                         LEFT JOIN getraenke_lieferanten l ON l.id = b.lieferant_id
                         LEFT JOIN standorte s ON s.id = b.standort_id WHERE b.id = ?', [$id]) : null;
}

function getraenke_positionen(int $bestellungId): array
{
    return db_all('SELECT p.*, g.name AS getraenk_name, g.gebinde FROM getraenke_positionen p
                   JOIN getraenke g ON g.id = p.getraenk_id WHERE p.bestellung_id = ? ORDER BY g.sort_order, g.name', [$bestellungId]);
}

/** "G-2026-007": Jahr und laufende Nummer */
function getraenke_bestellnummer(?int $jetzt = null): string
{
    $jahr = date('Y', $jetzt ?? time());
    $n = (int)db_val("SELECT COUNT(*) FROM getraenke_bestellungen WHERE nummer LIKE ?", ['G-' . $jahr . '-%'], 0) + 1;
    return sprintf('G-%s-%03d', $jahr, $n);
}

/** Summe der Positionen. Reine Funktion. */
function getraenke_bestellung_summe(array $positionen): float
{
    $s = 0.0;
    foreach ($positionen as $p) {
        $s += (int)$p['anzahl'] * (float)($p['preis'] ?? 0);
    }
    return round($s, 2);
}

/**
 * Vorschlag für eine Bestellung bei einem Lieferanten: je Getränk, was bis
 * zum Mindestbestand fehlt – bei „leer" mindestens der Mindestbestand oder
 * ein Gebinde, bei „wird knapp" mindestens ein Gebinde. Reine Funktion.
 * Rückgabe: [getraenk_id => anzahl], nur Getränke dieses Lieferanten.
 */
function getraenke_bestellvorschlag(int $lieferantId, array $getraenke, array $bestandSumme, array $meldungen): array
{
    $out = [];
    foreach ($getraenke as $g) {
        if ((int)($g['lieferant_id'] ?? 0) !== $lieferantId || (int)($g['is_active'] ?? 1) !== 1) {
            continue;
        }
        $gid = (int)$g['id'];
        $bestand = (int)($bestandSumme[$gid] ?? 0);
        $mindest = (int)($g['mindestbestand'] ?? 0);
        $bedarf = max(0, $mindest - $bestand);
        foreach ($meldungen as $m) {
            if ((int)$m['getraenk_id'] !== $gid) {
                continue;
            }
            $bedarf = max($bedarf, (string)$m['stand'] === 'leer' ? max(1, $mindest) : 1);
        }
        if ($bedarf > 0) {
            $out[$gid] = $bedarf;
        }
    }
    return $out;
}

/**
 * Bestellung aus dem Formular anlegen oder (als Entwurf) ändern.
 * Positionen kommen als pos[getraenk_id] = anzahl. Rückgabe: [id, fehler[]]
 */
function getraenke_bestellung_save_from_post(?array $b, array $user): array
{
    $fehler = [];
    if ($b && (string)$b['status'] !== 'entwurf') {
        return [null, ['Nur ein Entwurf lässt sich ändern.']];
    }
    $lieferant = getraenke_lieferant_find(post_int('lieferant_id'));
    if ($lieferant === null) {
        $fehler[] = 'Bitte einen Lieferanten wählen.';
    }
    $standortId = post_int('standort_id');
    if ($standortId > 0 && standort_find($standortId) === null) {
        $fehler[] = 'Diesen Platz gibt es nicht.';
    }
    $positionen = [];
    $preise = [];
    foreach (getraenke_alle(false) as $g) {
        $preise[(int)$g['id']] = (float)$g['preis'];
    }
    foreach ((array)($_POST['pos'] ?? []) as $gid => $anzahl) {
        $gid = (int)$gid;
        $anzahl = (int)$anzahl;
        if ($gid > 0 && $anzahl > 0 && isset($preise[$gid])) {
            $positionen[$gid] = min(9999, $anzahl);
        }
    }
    if (!$positionen) {
        $fehler[] = 'Bitte mindestens ein Getränk mit einer Anzahl eintragen.';
    }
    if ($fehler) {
        return [null, $fehler];
    }
    $daten = [
        'lieferant_id' => (int)$lieferant['id'],
        'standort_id'  => $standortId > 0 ? $standortId : null,
        'notiz'        => mb_substr(trim(post_str('notiz')), 0, 1000),
    ];
    if ($b) {
        db_update('getraenke_bestellungen', $daten, 'id = ?', [(int)$b['id']]);
        $id = (int)$b['id'];
        db_exec('DELETE FROM getraenke_positionen WHERE bestellung_id = ?', [$id]);
        audit('getraenke.bestellung_bearbeitet', 'getraenke_bestellung', $id, (string)$b['nummer']);
    } else {
        $daten['nummer'] = getraenke_bestellnummer();
        $daten['status'] = 'entwurf';
        $daten['created_by'] = (int)($user['id'] ?? 0) ?: null;
        $id = db_insert('getraenke_bestellungen', $daten);
        audit('getraenke.bestellung_angelegt', 'getraenke_bestellung', $id, $daten['nummer']);
    }
    foreach ($positionen as $gid => $anzahl) {
        db_insert('getraenke_positionen', ['bestellung_id' => $id, 'getraenk_id' => $gid, 'anzahl' => $anzahl, 'preis' => $preise[$gid]]);
    }
    return [$id, []];
}

function getraenke_bestellung_delete(array $b): ?string
{
    if ((string)$b['status'] !== 'entwurf') {
        return 'Nur ein Entwurf lässt sich löschen.';
    }
    db_exec('DELETE FROM getraenke_positionen WHERE bestellung_id = ?', [(int)$b['id']]);
    db_exec('DELETE FROM getraenke_bestellungen WHERE id = ?', [(int)$b['id']]);
    audit('getraenke.bestellung_geloescht', 'getraenke_bestellung', (int)$b['id'], (string)$b['nummer']);
    return null;
}

/** Marke für den Status. Reine Funktion. */
function getraenke_status_badge(string $status): string
{
    $farbe = match ($status) {
        'bestellt'    => '#0284c7',
        'geliefert'   => '#15803d',
        'abgerechnet' => '#64748b',
        default       => '#a16207',
    };
    return '<span class="badge" style="background:' . $farbe . '">' . e(GETRAENKE_BESTELL_STATUS[$status] ?? $status) . '</span>';
}

/**
 * Der Text der Bestell-Mail. Reine Funktion.
 * $ctx: ov_name, besteller, besteller_email, besteller_telefon, lieferadresse, hinweis
 */
function getraenke_bestellung_text(array $b, array $positionen, array $lieferant, ?array $standort, array $ctx): string
{
    $z = [];
    $z[] = 'Sehr geehrte Damen und Herren,';
    $z[] = '';
    $z[] = sprintf('hiermit bestellen wir (Bestellnummer %s%s):', (string)$b['nummer'],
        trim((string)($lieferant['kundennummer'] ?? '')) !== '' ? ', Kundennummer ' . trim((string)$lieferant['kundennummer']) : '');
    $z[] = '';
    foreach ($positionen as $p) {
        $gebinde = trim((string)($p['gebinde'] ?? ''));
        $z[] = sprintf('  %4d × %s%s', (int)$p['anzahl'], (string)$p['getraenk_name'], $gebinde !== '' ? ' (' . $gebinde . ')' : '');
    }
    $z[] = '';
    $adresse = trim((string)($ctx['lieferadresse'] ?? ''));
    if ($adresse !== '' || $standort !== null) {
        $z[] = 'Lieferung an:';
        if ($adresse !== '') {
            foreach (explode("\n", str_replace("\r", '', $adresse)) as $zeile) {
                $z[] = '  ' . trim($zeile);
            }
        }
        if ($standort !== null) {
            $z[] = '  Abladen: ' . (string)$standort['name'];
        }
        $z[] = '';
    }
    if (trim((string)($b['notiz'] ?? '')) !== '') {
        $z[] = trim((string)$b['notiz']);
        $z[] = '';
    }
    if (trim((string)($ctx['hinweis'] ?? '')) !== '') {
        $z[] = trim((string)$ctx['hinweis']);
        $z[] = '';
    }
    $z[] = 'Mit freundlichen Grüßen';
    $z[] = trim((string)($ctx['besteller'] ?? ''));
    $z[] = trim((string)($ctx['ov_name'] ?? ''));
    $kontakt = array_filter([trim((string)($ctx['besteller_email'] ?? '')), trim((string)($ctx['besteller_telefon'] ?? ''))]);
    if ($kontakt) {
        $z[] = implode(' · ', $kontakt);
    }
    return implode("\n", array_map('rtrim', $z));
}

/**
 * Bestellung per E-Mail an den Lieferanten schicken – die Prüfung durch
 * die verantwortliche Person ist der Klick. Rückgabe: ['ok', 'text']
 */
function getraenke_bestellung_senden(array $b, array $user): array
{
    if ((string)$b['status'] !== 'entwurf') {
        return ['ok' => false, 'text' => 'Diese Bestellung ist schon ' . (GETRAENKE_BESTELL_STATUS[(string)$b['status']] ?? $b['status']) . '.'];
    }
    $lieferant = getraenke_lieferant_find((int)($b['lieferant_id'] ?? 0));
    if ($lieferant === null || !mail_adresse_gueltig((string)$lieferant['email'])) {
        return ['ok' => false, 'text' => 'Der Lieferant hat keine gültige E-Mail-Adresse.'];
    }
    $positionen = getraenke_positionen((int)$b['id']);
    if (!$positionen) {
        return ['ok' => false, 'text' => 'Die Bestellung hat keine Positionen.'];
    }
    $standort = (int)($b['standort_id'] ?? 0) > 0 ? standort_find((int)$b['standort_id']) : null;
    $text = getraenke_bestellung_text($b, $positionen, $lieferant, $standort, [
        'ov_name'           => (string)setting('ov_name', ''),
        'besteller'         => (string)($user['display_name'] ?? $user['username'] ?? ''),
        'besteller_email'   => (string)($user['email'] ?? ''),
        'besteller_telefon' => (string)($user['phone'] ?? ''),
        'lieferadresse'     => (string)setting('getraenke_lieferadresse', ''),
        'hinweis'           => (string)setting('getraenke_bestell_hinweis', ''),
    ]);
    $betreff = sprintf('Bestellung %s – %s', (string)$b['nummer'], (string)setting('ov_name', 'OV-Multitool'));
    try {
        mail_senden((string)$lieferant['email'], $betreff, $text, (string)$lieferant['name']);
    } catch (Throwable $ex) {
        return ['ok' => false, 'text' => 'Die Bestellung ließ sich nicht verschicken: ' . $ex->getMessage()];
    }
    $jetzt = date('Y-m-d H:i:s');
    db_update('getraenke_bestellungen', [
        'status'      => 'bestellt',
        'gesendet_am' => $jetzt,
        'gesendet_an' => (string)$lieferant['email'],
        'gesendet_von' => (int)($user['id'] ?? 0) ?: null,
        'mail_text'   => $text,
    ], 'id = ?', [(int)$b['id']]);
    // Offene Meldungen zu den bestellten Getränken gelten als erledigt
    $ids = array_map(static fn($p) => (int)$p['getraenk_id'], $positionen);
    db_exec('UPDATE getraenke_meldungen SET erledigt_am = ?, bestellung_id = ? WHERE erledigt_am IS NULL AND getraenk_id IN ('
        . implode(',', array_fill(0, count($ids), '?')) . ')', array_merge([$jetzt, (int)$b['id']], $ids));
    audit('getraenke.bestellt', 'getraenke_bestellung', (int)$b['id'], (string)$b['nummer'] . ' an ' . (string)$lieferant['email']);
    return ['ok' => true, 'text' => sprintf('Bestellung %s an %s (%s) verschickt.', (string)$b['nummer'], (string)$lieferant['name'], (string)$lieferant['email'])];
}

/**
 * Lieferung eintragen: je Position die gelieferte Zahl, alles geht als
 * Zugang auf den Platz. Rückgabe: ['ok', 'text']
 */
function getraenke_bestellung_liefern(array $b, array $geliefert, int $standortId, array $user): array
{
    if (!in_array((string)$b['status'], ['bestellt', 'entwurf'], true)) {
        return ['ok' => false, 'text' => 'Diese Bestellung ist schon ' . (GETRAENKE_BESTELL_STATUS[(string)$b['status']] ?? $b['status']) . '.'];
    }
    $standort = standort_find($standortId);
    if ($standort === null) {
        return ['ok' => false, 'text' => 'Bitte den Platz wählen, an dem die Lieferung eingelagert wird.'];
    }
    $positionen = getraenke_positionen((int)$b['id']);
    $summe = 0;
    foreach ($positionen as $p) {
        $n = max(0, min(9999, (int)($geliefert[(int)$p['getraenk_id']] ?? $p['anzahl'])));
        db_update('getraenke_positionen', ['geliefert' => $n], 'id = ?', [(int)$p['id']]);
        if ($n > 0) {
            getraenke_buchen((int)$p['getraenk_id'], $standortId, 'zugang', $n, $user, 'Lieferung ' . (string)$b['nummer'], (int)$b['id']);
            $summe += $n;
        }
    }
    db_update('getraenke_bestellungen', ['status' => 'geliefert', 'geliefert_am' => date('Y-m-d H:i:s'), 'standort_id' => $standortId], 'id = ?', [(int)$b['id']]);
    audit('getraenke.geliefert', 'getraenke_bestellung', (int)$b['id'], sprintf('%s: %d Gebinde nach %s', (string)$b['nummer'], $summe, (string)$standort['name']));
    return ['ok' => true, 'text' => sprintf('%d Gebinde als Zugang auf „%s" gebucht.', $summe, (string)$standort['name'])];
}

/**
 * Rechnung abrechnen: eine Ausgabe im Budget, verknüpft mit der Bestellung.
 * Rückgabe: ['ok', 'text', 'expense_id']
 */
function getraenke_bestellung_abrechnen(array $b, float $betrag, string $datum, int $kategorieId, string $belegNr, ?string $bezahltAm, array $user): array
{
    if ((string)$b['status'] === 'abgerechnet') {
        return ['ok' => false, 'text' => 'Diese Bestellung ist schon abgerechnet.', 'expense_id' => (int)($b['expense_id'] ?? 0)];
    }
    if ($betrag <= 0) {
        return ['ok' => false, 'text' => 'Bitte den Rechnungsbetrag angeben.', 'expense_id' => 0];
    }
    $t = strtotime($datum);
    if ($t === false) {
        return ['ok' => false, 'text' => 'Bitte ein gültiges Rechnungsdatum angeben.', 'expense_id' => 0];
    }
    $datum = date('Y-m-d', $t);
    $jahr = (int)date('Y', $t);
    $status = $bezahltAm !== null && $bezahltAm !== '' ? 'bezahlt' : 'offen';
    $sperre = budget_sperre_pruefen($jahr, $datum, $status);
    if ($sperre !== null) {
        return ['ok' => false, 'text' => $sperre, 'expense_id' => 0];
    }
    $positionen = getraenke_positionen((int)$b['id']);
    $beschreibung = implode(', ', array_map(static fn($p) => (int)($p['geliefert'] ?? $p['anzahl']) . ' × ' . (string)$p['getraenk_name'], $positionen));
    $expenseId = db_insert('expenses', [
        'art'           => 'ausgabe',
        'jahr'          => $jahr,
        'datum'         => $datum,
        'bezeichnung'   => mb_substr('Getränke ' . (string)$b['nummer'] . ' – ' . (string)($b['lieferant_name'] ?? ''), 0, 200),
        'beschreibung'  => $beschreibung,
        'kategorie_id'  => $kategorieId > 0 ? $kategorieId : null,
        'betrag_brutto' => round($betrag, 2),
        'mwst_satz'     => 0,
        'betrag_netto'  => round($betrag, 2),
        'lieferant'     => mb_substr((string)($b['lieferant_name'] ?? ''), 0, 150),
        'beleg_nr'      => mb_substr($belegNr, 0, 100),
        'referenz'      => (string)$b['nummer'],
        'bezahlt_am'    => $status === 'bezahlt' ? date('Y-m-d', (int)strtotime((string)$bezahltAm)) : null,
        'status'        => $status,
        'created_by'    => (int)($user['id'] ?? 0) ?: null,
        'updated_by'    => (int)($user['id'] ?? 0) ?: null,
    ]);
    db_update('getraenke_bestellungen', ['status' => 'abgerechnet', 'expense_id' => $expenseId, 'rechnungsbetrag' => round($betrag, 2)], 'id = ?', [(int)$b['id']]);
    audit('ausgabe.angelegt', 'expense', $expenseId, 'Getränke ' . (string)$b['nummer'] . ' / ' . money($betrag));
    audit('getraenke.abgerechnet', 'getraenke_bestellung', (int)$b['id'], (string)$b['nummer'] . ' / ' . money($betrag));
    return ['ok' => true, 'text' => sprintf('Ausgabe über %s im Budget angelegt (%s).', money($betrag), $status === 'bezahlt' ? 'bezahlt' : 'gebucht, noch nicht bezahlt'), 'expense_id' => $expenseId];
}
