<?php
/**
 * Wanderungen (Migrationen) für bestehende Installationen.
 *
 * Wird von setup.php zwischen schema.sql und seed.sql aufgerufen. Jede
 * Wanderung läuft genau einmal; erledigte werden in schema_migrations
 * vermerkt. Alles hier muss auch auf einer frisch angelegten, leeren
 * Datenbank fehlerfrei durchlaufen.
 */
declare(strict_types=1);

function ovb_table_exists(PDO $pdo, string $table): bool
{
    static $cache = [];
    if (isset($cache[$table])) {
        return $cache[$table];
    }
    $st = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.tables
         WHERE table_schema = DATABASE() AND table_name = ?'
    );
    $st->execute([$table]);
    return $cache[$table] = ((int)$st->fetchColumn() > 0);
}

function ovb_index_exists(PDO $pdo, string $table, string $index): bool
{
    $st = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.statistics
         WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?'
    );
    $st->execute([$table, $index]);
    return (int)$st->fetchColumn() > 0;
}

/** Spalten, die auf list_items zeigen: [Tabelle, Spalte, zusätzliche Bedingung] */
function ovb_list_item_refs(): array
{
    return [
        ['users',         'fachgruppe_id',         ''],
        ['user_functions', 'function_id',          ''],
        ['wishes',        'fachgruppe_id',         ''],
        ['wishes',        'kategorie_id',          ''],
        ['wishes',        'dringlichkeit_id',      ''],
        ['wishes',        'status_id',             ''],
        ['wishes',        'einheit_id',            ''],
        ['todos',         'status_id',             ''],
        ['todos',         'prioritaet_id',         ''],
        ['todos',         'target_id',             "target_type IN ('fachgruppe','funktion')"],
        ['budgets',       'kategorie_id',          ''],
        ['budgets',       'fachgruppe_id',         ''],
        ['divera_forms',  'default_status_id',     ''],
        ['divera_forms',  'default_fachgruppe_id', ''],
        ['expenses',      'kategorie_id',          ''],
        ['expenses',      'fachgruppe_id',         ''],
    ];
}

/** Wie oft wird ein Listeneintrag verwendet? */
function ovb_count_usage(PDO $pdo, int $id): int
{
    $sum = 0;
    foreach (ovb_list_item_refs() as [$table, $column, $extra]) {
        if (!ovb_table_exists($pdo, $table)) {
            continue;
        }
        $sql = sprintf('SELECT COUNT(*) FROM `%s` WHERE `%s` = ?', $table, $column);
        if ($extra !== '') {
            $sql .= ' AND ' . $extra;
        }
        $st = $pdo->prepare($sql);
        $st->execute([$id]);
        $sum += (int)$st->fetchColumn();
    }
    return $sum;
}

/** Verweise von einem Listeneintrag auf einen anderen umbiegen */
function ovb_repoint(PDO $pdo, int $from, int $to): int
{
    $moved = 0;
    foreach (ovb_list_item_refs() as [$table, $column, $extra]) {
        if (!ovb_table_exists($pdo, $table)) {
            continue;
        }
        // user_functions hat einen zusammengesetzten Primärschlüssel: dort kann
        // das Umbiegen auf einen bereits vorhandenen Eintrag treffen. IGNORE
        // überspringt diese Fälle, die Reste räumt das anschliessende DELETE weg.
        $ignore = $table === 'user_functions' ? 'IGNORE ' : '';
        $sql = sprintf('UPDATE %s`%s` SET `%s` = ? WHERE `%s` = ?', $ignore, $table, $column, $column);
        if ($extra !== '') {
            $sql .= ' AND ' . $extra;
        }
        $st = $pdo->prepare($sql);
        $st->execute([$to, $from]);
        $moved += $st->rowCount();

        if ($table === 'user_functions') {
            $pdo->prepare('DELETE FROM user_functions WHERE function_id = ?')->execute([$from]);
        }
    }
    return $moved;
}

/**
 * Plant die Zusammenführung, ohne die Datenbank anzufassen.
 *
 * Gruppiert wird nach list_key und slug, ersatzweise nach der Bezeichnung.
 * Behalten wird der am häufigsten verwendete Eintrag; bei Gleichstand der
 * älteste, also der mit der kleinsten id. So bleibt der Eintrag stehen, den
 * die vorhandenen Daten ohnehin meinen.
 *
 * Rückgabe je Gruppe: ['key' => ..., 'keep' => id, 'drop' => [id, ...]]
 */
function ovb_dedupe_plan(array $rows, callable $usage): array
{
    $groups = [];
    foreach ($rows as $r) {
        $key = $r['list_key'] . '|' . (trim((string)$r['slug']) !== ''
            ? 's:' . $r['slug']
            : 'l:' . mb_strtolower(trim((string)$r['label'])));
        $groups[$key][] = $r;
    }

    $plan = [];
    foreach ($groups as $key => $group) {
        if (count($group) < 2) {
            continue;
        }

        // Nach id sortieren, damit bei Gleichstand der aelteste gewinnt
        usort($group, static fn($a, $b) => (int)$a['id'] <=> (int)$b['id']);

        $keep = (int)$group[0]['id'];
        $bestUse = -1;
        foreach ($group as $r) {
            $use = $usage((int)$r['id']);
            if ($use > $bestUse) {
                $bestUse = $use;
                $keep = (int)$r['id'];
            }
        }

        $drop = [];
        foreach ($group as $r) {
            if ((int)$r['id'] !== $keep) {
                $drop[] = (int)$r['id'];
            }
        }
        $plan[] = ['key' => $key, 'keep' => $keep, 'drop' => $drop];
    }
    return $plan;
}

/**
 * Doppelte Einträge in list_items zusammenführen.
 *
 * Ursache: list_items hatte keinen eindeutigen Schlüssel, deshalb hat das
 * INSERT IGNORE in seed.sql bei jedem Start die Grunddaten erneut eingefügt.
 *
 * Vorgehen je Gruppe gleicher Einträge (gleicher list_key und slug):
 *   - behalten wird der am häufigsten verwendete Eintrag, bei Gleichstand
 *     der älteste (kleinste id)
 *   - Verweise der übrigen werden auf den behaltenen umgebogen
 *   - erst danach werden die dann unbenutzten Dubletten gelöscht
 * Es geht also keine Zuordnung verloren.
 */
function ovb_dedupe_list_items(PDO $pdo, callable $say): void
{
    $rows = $pdo->query(
        'SELECT id, list_key, slug, label, is_default, is_active
         FROM list_items ORDER BY list_key, id'
    )->fetchAll(PDO::FETCH_ASSOC);

    $plan = ovb_dedupe_plan($rows, static fn(int $id): int => ovb_count_usage($pdo, $id));

    $geloescht = 0;
    $umgebogen = 0;
    $gruppen = count($plan);

    foreach ($plan as $gruppe) {
        foreach ($gruppe['drop'] as $id) {
            $umgebogen += ovb_repoint($pdo, $id, $gruppe['keep']);

            $rest = ovb_count_usage($pdo, $id);
            if ($rest > 0) {
                // Sollte nach dem Umbiegen nicht vorkommen – dann lieber stehen lassen
                $say(sprintf('Dublette #%d wird noch %d-mal verwendet und bleibt bestehen.', $id, $rest));
                continue;
            }
            $pdo->prepare('DELETE FROM list_items WHERE id = ?')->execute([$id]);
            $geloescht++;
        }
    }

    // Vorgabewert darf es je Liste nur einmal geben. Bewusst als eigener
    // Durchgang: ein Zurücksetzen je Gruppe würde den Vorgabewert einer
    // anderen Gruppe derselben Liste mit löschen.
    foreach ($pdo->query('SELECT DISTINCT list_key FROM list_items')->fetchAll(PDO::FETCH_COLUMN) as $key) {
        $ids = $pdo->prepare(
            'SELECT id FROM list_items WHERE list_key = ? AND is_default = 1 ORDER BY sort_order, id'
        );
        $ids->execute([$key]);
        $alle = $ids->fetchAll(PDO::FETCH_COLUMN);
        if (count($alle) > 1) {
            $behalten = (int)array_shift($alle);
            $in = implode(',', array_map('intval', $alle));
            $pdo->exec('UPDATE list_items SET is_default = 0 WHERE id IN (' . $in . ')');
            $say(sprintf('Liste "%s": Vorgabewert auf einen Eintrag begrenzt (#%d).', $key, $behalten));
        }
    }

    if ($gruppen === 0) {
        $say('Keine doppelten Listeneinträge gefunden.');
    } else {
        $say(sprintf(
            '%d Gruppe(n) mit Dubletten bereinigt: %d Eintrag/Einträge gelöscht, %d Verweis(e) umgebogen.',
            $gruppen,
            $geloescht,
            $umgebogen
        ));
    }
}

/** Leere oder mehrfach vergebene Schlüssel auffüllen, damit der Index greifen kann */
function ovb_fix_list_slugs(PDO $pdo, callable $say): void
{
    $rows = $pdo->query('SELECT id, list_key, slug, label FROM list_items ORDER BY list_key, id')
        ->fetchAll(PDO::FETCH_ASSOC);

    $gesehen = [];
    $geaendert = 0;

    foreach ($rows as $r) {
        $slug = (string)$r['slug'];
        if ($slug === '') {
            $slug = ovb_slugify((string)$r['label']);
        }
        if ($slug === '') {
            $slug = 'eintrag-' . (int)$r['id'];
        }

        $basis = $slug;
        $n = 2;
        while (isset($gesehen[$r['list_key'] . '|' . $slug])) {
            $slug = $basis . '-' . $n++;
        }
        $gesehen[$r['list_key'] . '|' . $slug] = true;

        if ($slug !== (string)$r['slug']) {
            $pdo->prepare('UPDATE list_items SET slug = ? WHERE id = ?')->execute([$slug, (int)$r['id']]);
            $geaendert++;
        }
    }

    if ($geaendert > 0) {
        $say(sprintf('%d Schlüssel ergänzt oder eindeutig gemacht.', $geaendert));
    }
}

function ovb_slugify(string $v): string
{
    $v = strtr($v, ['ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss', 'Ä' => 'ae', 'Ö' => 'oe', 'Ü' => 'ue']);
    $v = mb_strtolower(trim($v));
    return trim((string)preg_replace('/[^a-z0-9]+/', '-', $v), '-');
}

/* ==================================================================== */

function ovb_migrate(PDO $pdo, callable $say): void
{
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS schema_migrations (
            id         VARCHAR(80) NOT NULL,
            applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            note       VARCHAR(255) NOT NULL DEFAULT \'\',
            PRIMARY KEY (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    $erledigt = static function (string $id) use ($pdo): bool {
        $st = $pdo->prepare('SELECT COUNT(*) FROM schema_migrations WHERE id = ?');
        $st->execute([$id]);
        return (int)$st->fetchColumn() > 0;
    };
    $merken = static function (string $id, string $note = '') use ($pdo): void {
        $pdo->prepare('INSERT IGNORE INTO schema_migrations (id, note) VALUES (?,?)')
            ->execute([$id, mb_substr($note, 0, 255)]);
    };

    /* ---- 001: Dubletten in list_items zusammenführen ---- */
    if (!$erledigt('001_dedupe_list_items')) {
        $say('Wanderung 001: doppelte Listeneinträge prüfen ...');

        /*
         * Vorher eine Kopie ablegen. Das ist DDL und beendet eine offene
         * Transaktion, muss also vor beginTransaction() geschehen. Die Kopie
         * bleibt liegen; sie kostet fast nichts und erlaubt notfalls einen
         * Blick auf den Zustand vor der Bereinigung.
         */
        if (!ovb_table_exists($pdo, 'list_items_backup_dedupe')) {
            try {
                $pdo->exec('CREATE TABLE list_items_backup_dedupe AS SELECT * FROM list_items');
                $say('Sicherungskopie der Listeneinträge angelegt (list_items_backup_dedupe).');
            } catch (PDOException $ex) {
                $say('Hinweis: Sicherungskopie nicht möglich (' . $ex->getMessage() . ').');
            }
        }

        $pdo->beginTransaction();
        try {
            ovb_dedupe_list_items($pdo, $say);
            ovb_fix_list_slugs($pdo, $say);
            $pdo->commit();
        } catch (Throwable $ex) {
            $pdo->rollBack();
            throw $ex;
        }
        $merken('001_dedupe_list_items');
    }

    /* ---- 002: eindeutiger Schlüssel, damit es nicht wieder passiert ---- */
    if (!ovb_index_exists($pdo, 'list_items', 'uq_list_slug')) {
        try {
            $pdo->exec('ALTER TABLE list_items ADD UNIQUE KEY uq_list_slug (list_key, slug)');
            $say('Eindeutiger Schlüssel auf list_items ergänzt – Grunddaten können sich nicht mehr verdoppeln.');
        } catch (PDOException $ex) {
            $say('Hinweis: eindeutiger Schlüssel auf list_items konnte nicht angelegt werden ('
                . $ex->getMessage() . ').');
        }
    }
    $merken('002_unique_list_slug');
}
