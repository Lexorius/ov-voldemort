<?php
declare(strict_types=1);

/*
 * Bestellungen: Eine Bestellung fasst mehrere freigegebene Wünsche
 * zusammen – ein Lieferant, ein Auftrag, eine Rechnung. Dazu die
 * Verknüpfung einer Buchung mit mehreren Wünschen und Fahrzeugen.
 */

const BESTELLUNG_STATUS = [
    'bestellt'    => 'bestellt',
    'geliefert'   => 'geliefert – Wünsche sind beschafft',
    'abgerechnet' => 'abgerechnet – Rechnung gebucht',
    'storniert'   => 'storniert – Wünsche wieder freigegeben',
];
const BESTELLUNG_FARBEN = ['bestellt' => '#0891b2', 'geliefert' => '#15803d', 'abgerechnet' => '#166534', 'storniert' => '#64748b'];

function bestellung_status(string $s): string
{
    return array_key_exists($s, BESTELLUNG_STATUS) ? $s : 'bestellt';
}

/** Laufende Nummer je Jahr: B-2026-003 */
function bestellung_nummer_neu(?int $jahr = null): string
{
    $jahr ??= (int)date('Y');
    $n = (int)db_val('SELECT COUNT(*) FROM bestellungen WHERE nummer LIKE ?', ['B-' . $jahr . '-%'], 0) + 1;
    return sprintf('B-%d-%03d', $jahr, $n);
}

function bestellung_select(): string
{
    return 'SELECT b.*, u.display_name AS erfasser,
                   (SELECT COUNT(*) FROM bestellung_wuensche bw WHERE bw.bestellung_id = b.id) AS wuensche,
                   (SELECT COALESCE(SUM(w.netto_gesamt),0) FROM bestellung_wuensche bw JOIN wishes w ON w.id = bw.wish_id WHERE bw.bestellung_id = b.id) AS summe,
                   (SELECT COALESCE(SUM(e.betrag_brutto),0) FROM expenses e WHERE e.bestellung_id = b.id AND e.art = \'ausgabe\' AND e.status <> \'geplant\') AS gebucht,
                   (SELECT COUNT(*) FROM expenses e WHERE e.bestellung_id = b.id) AS buchungen
            FROM bestellungen b
            LEFT JOIN users u ON u.id = b.created_by';
}

function bestellung_find(int $id): ?array
{
    return $id > 0 ? db_row(bestellung_select() . ' WHERE b.id = ?', [$id]) : null;
}

/** $f: status, q, jahr, offen (nicht abgerechnet/storniert) */
function bestellung_query(array $f = []): array
{
    $w = [];
    $p = [];
    if (!empty($f['status'])) {
        $w[] = 'b.status = ?';
        $p[] = bestellung_status((string)$f['status']);
    }
    if (!empty($f['offen'])) {
        $w[] = "b.status IN ('bestellt','geliefert')";
    }
    if (!empty($f['jahr'])) {
        $w[] = 'b.nummer LIKE ?';
        $p[] = 'B-' . (int)$f['jahr'] . '-%';
    }
    if (!empty($f['q'])) {
        $w[] = '(b.nummer LIKE ? OR b.lieferant LIKE ? OR b.bestell_nr LIKE ? OR b.notiz LIKE ?)';
        $like = '%' . $f['q'] . '%';
        array_push($p, $like, $like, $like, $like);
    }
    return db_all(bestellung_select() . ($w ? ' WHERE ' . implode(' AND ', $w) : '') . ' ORDER BY b.bestellt_am DESC, b.id DESC', $p);
}

/** Die Wünsche einer Bestellung mit Status */
function bestellung_wuensche(int $id): array
{
    return db_all(
        'SELECT w.*, st.label AS status_label, st.color AS status_color, st.slug AS status_slug,
                fg.label AS fachgruppe_label, fz.bezeichnung AS fahrzeug
         FROM bestellung_wuensche bw
         JOIN wishes w ON w.id = bw.wish_id
         LEFT JOIN list_items st ON st.id = w.status_id
         LEFT JOIN list_items fg ON fg.id = w.fachgruppe_id
         LEFT JOIN vehicles fz ON fz.id = w.vehicle_id
         WHERE bw.bestellung_id = ?
         ORDER BY w.bezeichnung',
        [$id]
    );
}

/** Die Bestellung zu einem Wunsch – die jüngste, falls er in mehreren steckt */
function bestellung_zu_wunsch(int $wishId): ?array
{
    return db_row(bestellung_select() . ' JOIN bestellung_wuensche bw ON bw.bestellung_id = b.id WHERE bw.wish_id = ? ORDER BY b.id DESC LIMIT 1', [$wishId]);
}

/** Buchungen zu einer Bestellung */
function bestellung_buchungen(int $id): array
{
    return expense_query(['bestellung_id' => $id]);
}

/** Welche Wünsche sich bestellen lassen: nur freigegebene. Gibt [passende, abgelehnte Namen] zurück. Reine Funktion. */
function bestellung_wuensche_pruefen(array $wuensche): array
{
    $gut = [];
    $schlecht = [];
    foreach ($wuensche as $w) {
        if ((string)($w['status_slug'] ?? '') === 'freigegeben') {
            $gut[] = $w;
        } else {
            $schlecht[] = (string)$w['bezeichnung'];
        }
    }
    return [$gut, $schlecht];
}

/**
 * Bestellung anlegen oder ändern. $wishIds nur beim Anlegen (Wünsche werden
 * als bestellt markiert). Gibt [id, fehler[]] zurück.
 */
function bestellung_save_from_post(?array $existing, array $user, array $wishIds = []): array
{
    $errors = [];
    $lieferant = trim(post_str('lieferant'));
    if ($lieferant === '') {
        $errors[] = 'Bitte den Lieferanten angeben.';
    }
    $datum = post_date('bestellt_am') ?: date('Y-m-d');
    $wuensche = [];
    if (!$existing) {
        $ids = array_values(array_unique(array_filter(array_map('intval', $wishIds), static fn($i) => $i > 0)));
        if (!$ids) {
            $errors[] = 'Bitte mindestens einen Wunsch auswählen.';
        } else {
            foreach ($ids as $wid) {
                $w = wish_find_full($wid);
                if ($w) {
                    $wuensche[] = $w;
                }
            }
            [$gut, $schlecht] = bestellung_wuensche_pruefen($wuensche);
            if ($schlecht) {
                $errors[] = 'Nur freigegebene Wünsche lassen sich bestellen – nicht: ' . implode(', ', $schlecht) . '.';
            }
            $wuensche = $gut;
        }
    }
    if ($errors) {
        return [null, $errors];
    }
    $data = [
        'lieferant'   => mb_substr($lieferant, 0, 150),
        'bestellt_am' => $datum,
        'bestell_nr'  => mb_substr(post_str('bestell_nr'), 0, 100),
        'notiz'       => post_str('notiz'),
        'updated_by'  => (int)$user['id'],
    ];
    if ($existing) {
        db_update('bestellungen', $data, 'id = ?', [(int)$existing['id']]);
        $id = (int)$existing['id'];
        audit('bestellung.bearbeitet', 'bestellung', $id, (string)$existing['nummer']);
        return [$id, []];
    }
    $data['nummer'] = bestellung_nummer_neu((int)substr($datum, 0, 4));
    $data['status'] = 'bestellt';
    $data['created_by'] = (int)$user['id'];
    $id = db_insert('bestellungen', $data);
    foreach ($wuensche as $w) {
        db_insert('bestellung_wuensche', ['bestellung_id' => $id, 'wish_id' => (int)$w['id']]);
        wish_mark_ordered($w, $user);
    }
    audit('bestellung.angelegt', 'bestellung', $id, $data['nummer'] . ' · ' . count($wuensche) . ' Wunsch/Wünsche · ' . $data['lieferant']);
    return [$id, []];
}

/**
 * Status setzen und die Wünsche mitziehen: geliefert → beschafft,
 * storniert → wieder freigegeben. Gibt eine Fehlermeldung oder null zurück.
 */
function bestellung_status_setzen(array $b, string $status, array $user): ?string
{
    $status = bestellung_status($status);
    if ($status === (string)$b['status']) {
        return null;
    }
    $wunschStatus = null;
    if ($status === 'geliefert' || ($status === 'abgerechnet' && (string)$b['status'] === 'bestellt')) {
        $wunschStatus = list_id_by_slug('wunsch_status', 'beschafft');
        if (!$wunschStatus) {
            return 'Der Status „beschafft" fehlt in der Liste Wunsch-Status (Schlüssel: beschafft).';
        }
    } elseif ($status === 'storniert') {
        $wunschStatus = list_id_by_slug('wunsch_status', 'freigegeben');
    }
    db_update('bestellungen', ['status' => $status, 'updated_by' => (int)$user['id']], 'id = ?', [(int)$b['id']]);
    if ($wunschStatus) {
        foreach (bestellung_wuensche((int)$b['id']) as $w) {
            db_update('wishes', ['status_id' => $wunschStatus, 'updated_by' => (int)$user['id']], 'id = ?', [(int)$w['id']]);
        }
    }
    audit('bestellung.status', 'bestellung', (int)$b['id'], $b['nummer'] . ' → ' . $status);
    return null;
}

function bestellung_delete(array $b, array $user): void
{
    if ((string)$b['status'] === 'bestellt') {
        bestellung_status_setzen($b, 'storniert', $user);
    }
    db_exec('UPDATE expenses SET bestellung_id = NULL WHERE bestellung_id = ?', [(int)$b['id']]);
    db_exec('DELETE FROM bestellung_wuensche WHERE bestellung_id = ?', [(int)$b['id']]);
    db_exec('DELETE FROM bestellungen WHERE id = ?', [(int)$b['id']]);
    audit('bestellung.geloescht', 'bestellung', (int)$b['id'], (string)$b['nummer']);
}

/** Kennzeichen für Listen */
function bestellung_badge(string $status): string
{
    $s = bestellung_status($status);
    return '<span class="badge" style="background:' . BESTELLUNG_FARBEN[$s] . '">' . e(explode(' –', BESTELLUNG_STATUS[$s])[0]) . '</span>';
}

/* ==================================================================== */
/* Buchungen mit mehreren Bezügen                                        */
/* ==================================================================== */

const EXPENSE_LINK_TYPEN = ['wish' => 'Wunsch', 'vehicle' => 'Fahrzeug'];

/** Verknüpfungen einer Buchung: ['wish' => [ids], 'vehicle' => [ids]] */
function expense_links(int $expenseId): array
{
    $out = ['wish' => [], 'vehicle' => []];
    foreach (db_all('SELECT typ, ziel_id FROM expense_links WHERE expense_id = ? ORDER BY id', [$expenseId]) as $r) {
        $out[(string)$r['typ']][] = (int)$r['ziel_id'];
    }
    return $out;
}

/** Verknüpfungen neu setzen; wish_id der Buchung bleibt der erste Wunsch (für ältere Auswertungen). */
function expense_links_speichern(int $expenseId, array $wishIds, array $vehicleIds): void
{
    $wishIds = array_values(array_unique(array_filter(array_map('intval', $wishIds), static fn($i) => $i > 0)));
    $vehicleIds = array_values(array_unique(array_filter(array_map('intval', $vehicleIds), static fn($i) => $i > 0)));
    db_exec('DELETE FROM expense_links WHERE expense_id = ?', [$expenseId]);
    foreach ($wishIds as $id) {
        db_insert('expense_links', ['expense_id' => $expenseId, 'typ' => 'wish', 'ziel_id' => $id]);
    }
    foreach ($vehicleIds as $id) {
        db_insert('expense_links', ['expense_id' => $expenseId, 'typ' => 'vehicle', 'ziel_id' => $id]);
    }
    db_update('expenses', ['wish_id' => $wishIds[0] ?? null], 'id = ?', [$expenseId]);
}

/** Buchungen zu einem Wunsch oder Fahrzeug (über Verknüpfung oder den alten Einzelbezug) */
function expenses_fuer(string $typ, int $zielId): array
{
    $typ = array_key_exists($typ, EXPENSE_LINK_TYPEN) ? $typ : 'wish';
    $alt = $typ === 'wish' ? ' OR e.wish_id = ?' : '';
    $p = [$typ, $zielId];
    if ($alt !== '') {
        $p[] = $zielId;
    }
    return db_all(
        'SELECT e.*, ka.label AS kategorie_label, ka.color AS kategorie_color, b.nummer AS bestellung_nummer
         FROM expenses e
         LEFT JOIN list_items ka ON ka.id = e.kategorie_id
         LEFT JOIN bestellungen b ON b.id = e.bestellung_id
         WHERE EXISTS (SELECT 1 FROM expense_links l WHERE l.expense_id = e.id AND l.typ = ? AND l.ziel_id = ?)' . $alt . '
         ORDER BY e.datum DESC, e.id DESC',
        $p
    );
}

/** Summe der gebuchten Ausgaben zu einem Bezug. Reine Funktion über die Liste. */
function expenses_summe(array $rows): float
{
    $s = 0.0;
    foreach ($rows as $r) {
        if ((string)$r['art'] === 'ausgabe' && (string)($r['status'] ?? 'bezahlt') !== 'geplant') {
            $s += (float)$r['betrag_brutto'];
        }
    }
    return round($s, 2);
}
