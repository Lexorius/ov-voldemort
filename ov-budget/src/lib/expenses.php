<?php
declare(strict_types=1);

/**
 * Gesamtbudget je Haushaltsjahr und die laufenden Ausgaben.
 * Die Wunschliste plant, dieses Modul bucht das tatsächlich Ausgegebene.
 */

/* ---------------- Jahresbudget ---------------- */

function budget_year(int $jahr): ?array
{
    return db_row('SELECT * FROM budget_years WHERE jahr = ?', [$jahr]);
}

function budget_year_betrag(int $jahr): float
{
    return (float)(budget_year($jahr)['betrag'] ?? 0);
}

function budget_year_save(int $jahr, float $betrag, string $beschreibung, int $aktiv): void
{
    db_exec(
        'INSERT INTO budget_years (jahr, betrag, beschreibung, is_active) VALUES (?,?,?,?)
         ON DUPLICATE KEY UPDATE betrag = VALUES(betrag), beschreibung = VALUES(beschreibung),
                                 is_active = VALUES(is_active)',
        [$jahr, $betrag, $beschreibung, $aktiv]
    );
    audit('jahresbudget.gespeichert', 'budget_year', $jahr, money($betrag));
}

/** Alle Jahre, zu denen es irgendetwas gibt */
function budget_years_known(): array
{
    $rows = db_all(
        'SELECT jahr FROM budget_years
         UNION SELECT jahr FROM budgets
         UNION SELECT jahr FROM expenses
         ORDER BY jahr DESC'
    );
    $jahre = array_map(static fn($r) => (int)$r['jahr'], $rows);
    $aktuell = setting_int('haushaltsjahr', (int)date('Y'));
    if (!in_array($aktuell, $jahre, true)) {
        $jahre[] = $aktuell;
        rsort($jahre);
    }
    return $jahre;
}

/* ---------------- Ausgaben ---------------- */

function expense_find(int $id): ?array
{
    return db_row('SELECT * FROM expenses WHERE id = ?', [$id]);
}

/**
 * $f: jahr, q, kategorie_id, fachgruppe_id, budget_id, von, bis, sort, limit
 */
function expense_query(array $f = []): array
{
    $w = [];
    $p = [];

    if (!empty($f['jahr'])) {
        $w[] = 'e.jahr = ?';
        $p[] = (int)$f['jahr'];
    }
    if (!empty($f['q'])) {
        $w[] = '(e.bezeichnung LIKE ? OR e.beschreibung LIKE ? OR e.lieferant LIKE ? OR e.beleg_nr LIKE ?)';
        $like = '%' . $f['q'] . '%';
        array_push($p, $like, $like, $like, $like);
    }
    foreach (['kategorie_id', 'fachgruppe_id', 'budget_id'] as $col) {
        if (!empty($f[$col])) {
            $w[] = 'e.' . $col . ' = ?';
            $p[] = (int)$f[$col];
        }
    }
    if (!empty($f['von'])) {
        $w[] = 'e.datum >= ?';
        $p[] = $f['von'];
    }
    if (!empty($f['bis'])) {
        $w[] = 'e.datum <= ?';
        $p[] = $f['bis'];
    }

    $order = match ($f['sort'] ?? 'datum') {
        'betrag' => 'e.betrag_brutto DESC',
        'name'   => 'e.bezeichnung ASC',
        'alt'    => 'e.datum ASC, e.id ASC',
        default  => 'e.datum DESC, e.id DESC',
    };

    $sql = 'SELECT e.*,
                   ka.label AS kategorie_label, ka.color AS kategorie_color,
                   fg.label AS fachgruppe_label,
                   b.name AS budget_name,
                   w.bezeichnung AS wunsch_bezeichnung,
                   u.display_name AS erfasser
            FROM expenses e
            LEFT JOIN list_items ka ON ka.id = e.kategorie_id
            LEFT JOIN list_items fg ON fg.id = e.fachgruppe_id
            LEFT JOIN budgets    b  ON b.id  = e.budget_id
            LEFT JOIN wishes     w  ON w.id  = e.wish_id
            LEFT JOIN users      u  ON u.id  = e.created_by'
        . ($w ? ' WHERE ' . implode(' AND ', $w) : '')
        . ' ORDER BY ' . $order;

    if (!empty($f['limit'])) {
        $sql .= ' LIMIT ' . (int)$f['limit'];
    }

    return db_all($sql, $p);
}

function expense_stats(array $rows): array
{
    $s = ['anzahl' => count($rows), 'brutto' => 0.0, 'netto' => 0.0];
    foreach ($rows as $r) {
        $s['brutto'] += (float)$r['betrag_brutto'];
        $s['netto'] += (float)$r['betrag_netto'];
    }
    return $s;
}

/** Ausgaben eines Jahres je Kategorie */
function expense_by_category(int $jahr): array
{
    // Ohne Kategorie erfasste Ausgaben kommen als NULL zurück und werden
    // in der Anzeige beschriftet – kein SQL-Literal, das je nach sql_mode
    // als Bezeichner gelesen werden könnte.
    return db_all(
        'SELECT ka.id, ka.label, ka.color,
                COUNT(*) AS anzahl,
                SUM(e.betrag_brutto) AS brutto,
                SUM(e.betrag_netto) AS netto
         FROM expenses e
         LEFT JOIN list_items ka ON ka.id = e.kategorie_id
         WHERE e.jahr = ?
         GROUP BY ka.id, ka.label, ka.color
         ORDER BY brutto DESC',
        [$jahr]
    );
}

/** Ausgaben eines Jahres je Monat, immer zwölf Werte */
function expense_by_month(int $jahr): array
{
    $out = array_fill(1, 12, 0.0);
    foreach (db_all(
        'SELECT MONTH(datum) AS m, SUM(betrag_brutto) AS brutto
         FROM expenses WHERE jahr = ? GROUP BY MONTH(datum)',
        [$jahr]
    ) as $r) {
        $out[(int)$r['m']] = (float)$r['brutto'];
    }
    return $out;
}

/** Summe der Ausgaben eines Jahres */
function expense_total(int $jahr, string $feld = 'betrag_brutto'): float
{
    $feld = $feld === 'betrag_netto' ? 'betrag_netto' : 'betrag_brutto';
    return (float)db_val('SELECT COALESCE(SUM(' . $feld . '),0) FROM expenses WHERE jahr = ?', [$jahr], 0);
}

/** Bereits verbuchte Ausgaben je Budgettopf */
function expense_by_budget(int $jahr): array
{
    $out = [];
    foreach (db_all(
        'SELECT budget_id, SUM(betrag_brutto) AS brutto, SUM(betrag_netto) AS netto
         FROM expenses WHERE jahr = ? AND budget_id IS NOT NULL GROUP BY budget_id',
        [$jahr]
    ) as $r) {
        $out[(int)$r['budget_id']] = ['brutto' => (float)$r['brutto'], 'netto' => (float)$r['netto']];
    }
    return $out;
}

/**
 * Ausgabe aus dem Formular speichern. Gibt [id, fehler[]] zurück.
 */
function expense_save_from_post(?array $existing, array $user): array
{
    $errors = [];

    $bezeichnung = post_str('bezeichnung');
    if ($bezeichnung === '') {
        $errors[] = 'Bitte eine Bezeichnung angeben.';
    }

    $datum = post_date('datum');
    if (!$datum) {
        $errors[] = 'Bitte ein gültiges Datum angeben.';
    }

    $mwst = post_dec('mwst_satz', setting_float('mwst_satz', 19.0));
    if ($mwst < 0 || $mwst > 100) {
        $mwst = setting_float('mwst_satz', 19.0);
    }

    // Erfasst wird je nach Einstellung brutto oder netto, gespeichert immer beides
    $eingabe = post_dec('betrag');
    if ($eingabe <= 0) {
        $errors[] = 'Bitte einen Betrag größer als null angeben.';
    }
    if (setting('ausgaben_betragsart', 'brutto') === 'netto') {
        $netto = $eingabe;
        $brutto = round($netto * (1 + $mwst / 100), 2);
    } else {
        $brutto = $eingabe;
        $netto = round($brutto / (1 + $mwst / 100), 2);
    }

    if ($errors) {
        return [null, $errors];
    }

    $data = [
        'jahr'          => post_int('jahr') ?: (int)substr((string)$datum, 0, 4),
        'datum'         => $datum,
        'bezeichnung'   => mb_substr($bezeichnung, 0, 200),
        'beschreibung'  => post_str('beschreibung'),
        'kategorie_id'  => post_int('kategorie_id'),
        'fachgruppe_id' => post_int('fachgruppe_id'),
        'budget_id'     => post_int('budget_id'),
        'wish_id'       => post_int('wish_id'),
        'betrag_brutto' => $brutto,
        'mwst_satz'     => $mwst,
        'betrag_netto'  => $netto,
        'lieferant'     => mb_substr(post_str('lieferant'), 0, 150),
        'beleg_nr'      => mb_substr(post_str('beleg_nr'), 0, 100),
        'bezahlt_am'    => post_date('bezahlt_am'),
        'notiz'         => post_str('notiz'),
        'updated_by'    => (int)$user['id'],
    ];

    if ($existing) {
        db_update('expenses', $data, 'id = ?', [$existing['id']]);
        $id = (int)$existing['id'];
        audit('ausgabe.bearbeitet', 'expense', $id, $data['bezeichnung'] . ' / ' . money($brutto));
    } else {
        $data['created_by'] = (int)$user['id'];
        $id = db_insert('expenses', $data);
        audit('ausgabe.erfasst', 'expense', $id, $data['bezeichnung'] . ' / ' . money($brutto));
    }

    return [$id, []];
}
