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

function ovb_column_exists(PDO $pdo, string $table, string $column): bool
{
    $st = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.columns
         WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?'
    );
    $st->execute([$table, $column]);
    return (int)$st->fetchColumn() > 0;
}

/** Spalten, die auf list_items zeigen: [Tabelle, Spalte, zusätzliche Bedingung] */
function ovb_list_item_refs(): array
{
    return [
        ['users',         'fachgruppe_id',         ''],
        ['user_functions', 'function_id',          ''],
        ['bestell_rechte', 'funktion_id',          ''],
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
        ['contacts',      'kategorie_id',          ''],
        ['contact_group_members', 'status_id',     ''],
        ['meetings',      'typ_id',                ''],
        ['meeting_attendees', 'status_id',         ''],
        ['vehicles',      'typ_id',                ''],
        ['vehicles',      'fachgruppe_id',         ''],
        ['vehicles',      'status_id',             ''],
        ['vehicle_orders', 'art_id',               ''],
        ['vehicle_orders', 'prioritaet_id',        ''],
        ['vehicle_orders', 'status_id',            ''],
        ['meeting_series', 'typ_id',               ''],
        ['talking_points', 'fachgruppe_id',        ''],
        ['talking_points', 'prioritaet_id',        ''],
        ['talking_points', 'status_id',            ''],
        ['events',         'typ_id',              ''],
        ['sims',           'typ_id',              ''],
        ['sims',           'status_id',           ''],
        ['sims',           'ziel_id',             "ziel_typ = 'fachgruppe'"],
        ['events',         'fachgruppe_id',       ''],
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
        // user_functions und bestell_rechte haben eindeutige Schlüssel: dort kann
        // das Umbiegen auf einen bereits vorhandenen Eintrag treffen. IGNORE
        // überspringt diese Fälle, die Reste räumt das anschliessende DELETE weg.
        $eindeutig = in_array($table, ['user_functions', 'bestell_rechte'], true);
        $ignore = $eindeutig ? 'IGNORE ' : '';
        $sql = sprintf('UPDATE %s`%s` SET `%s` = ? WHERE `%s` = ?', $ignore, $table, $column, $column);
        if ($extra !== '') {
            $sql .= ' AND ' . $extra;
        }
        $st = $pdo->prepare($sql);
        $st->execute([$to, $from]);
        $moved += $st->rowCount();

        if ($eindeutig) {
            $pdo->prepare(sprintf('DELETE FROM `%s` WHERE `%s` = ?', $table, $column))->execute([$from]);
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

    /* ---- 003: Buchungen kennen Einnahmen ---- */
    if (ovb_table_exists($pdo, 'expenses') && !ovb_column_exists($pdo, 'expenses', 'art')) {
        try {
            $pdo->exec(
                "ALTER TABLE expenses
                 ADD COLUMN art ENUM('ausgabe','einnahme') NOT NULL DEFAULT 'ausgabe' AFTER id"
            );
            $say('Buchungen um die Richtung erweitert – Bestand gilt als Ausgabe.');
        } catch (PDOException $ex) {
            $say('Hinweis: Spalte "art" konnte nicht ergänzt werden (' . $ex->getMessage() . ').');
        }
    }
    if (ovb_table_exists($pdo, 'expenses') && !ovb_column_exists($pdo, 'expenses', 'referenz')) {
        try {
            $pdo->exec(
                "ALTER TABLE expenses
                 ADD COLUMN referenz VARCHAR(100) NOT NULL DEFAULT '' AFTER beleg_nr"
            );
            $say('Buchungen um das Feld für Einsatz- und Auftragsnummern erweitert.');
        } catch (PDOException $ex) {
            $say('Hinweis: Spalte "referenz" konnte nicht ergänzt werden (' . $ex->getMessage() . ').');
        }
    }
    $merken('003_expenses_einnahmen');

    /* ---- 004: wiederkehrende Besprechungen ---- */
    // meeting_series selbst legt schema.sql an; hier nur die Ergänzungen an meetings
    if (ovb_table_exists($pdo, 'meetings')) {
        $schritte = [];

        if (!ovb_column_exists($pdo, 'meetings', 'series_id')) {
            $pdo->exec(
                'ALTER TABLE meetings
                 ADD COLUMN series_id INT UNSIGNED NULL AFTER id,
                 ADD COLUMN serien_datum DATE NULL AFTER series_id'
            );
            $schritte[] = 'Spalten';
        }
        if (!ovb_index_exists($pdo, 'meetings', 'uq_serie_termin')) {
            $pdo->exec('ALTER TABLE meetings ADD UNIQUE KEY uq_serie_termin (series_id, serien_datum)');
            $schritte[] = 'Schlüssel';
        }
        if (ovb_table_exists($pdo, 'meeting_series') && !ovb_constraint_exists($pdo, 'meetings', 'fk_meet_serie')) {
            $pdo->exec(
                'ALTER TABLE meetings ADD CONSTRAINT fk_meet_serie
                 FOREIGN KEY (series_id) REFERENCES meeting_series(id) ON DELETE SET NULL'
            );
            $schritte[] = 'Fremdschlüssel';
        }
        $typ = (string)$pdo->query(
            "SELECT COLUMN_TYPE FROM information_schema.columns
             WHERE table_schema = DATABASE() AND table_name = 'meetings' AND column_name = 'status'"
        )->fetchColumn();
        if ($typ !== '' && !str_contains($typ, 'abgesagt')) {
            $pdo->exec(
                "ALTER TABLE meetings
                 MODIFY status ENUM('geplant','abgeschlossen','abgesagt') NOT NULL DEFAULT 'geplant'"
            );
            $schritte[] = 'Status "abgesagt"';
        }

        if ($schritte) {
            $say('Besprechungen für Serien erweitert: ' . implode(', ', $schritte) . '.');
        }
    }
    $merken('004_meeting_series');

    /* ---- 005: Freigabe von Wünschen zur Bestellung ---- */
    if (ovb_table_exists($pdo, 'wishes') && !ovb_column_exists($pdo, 'wishes', 'freigegeben_von')) {
        $pdo->exec(
            'ALTER TABLE wishes
             ADD COLUMN freigegeben_von INT UNSIGNED NULL AFTER divera_entry_id,
             ADD COLUMN freigegeben_am DATETIME NULL AFTER freigegeben_von'
        );
        if (!ovb_constraint_exists($pdo, 'wishes', 'fk_w_frei')) {
            $pdo->exec(
                'ALTER TABLE wishes ADD CONSTRAINT fk_w_frei
                 FOREIGN KEY (freigegeben_von) REFERENCES users(id) ON DELETE SET NULL'
            );
        }
        $say('Wünsche um die Freigabe zur Bestellung erweitert.');
    }
    $merken('005_wish_freigabe');

    /* ---- 006: Beschriftungen von Einstellungen auffrischen ---- */
    // seed.sql legt Einstellungen nur an (INSERT IGNORE). Aendert sich spaeter
    // die Beschriftung oder der Hinweistext, bekaeme eine bestehende
    // Installation ihn nie zu sehen. Die Werte selbst bleiben unberuehrt.
    if (ovb_table_exists($pdo, 'settings')) {
        $n = ovb_refresh_setting_texts($pdo, APP_ROOT . '/sql/seed.sql');
        if ($n > 0) {
            $say(sprintf('%d Beschriftung(en) von Einstellungen aufgefrischt.', $n));
        }
    }
    $merken('006_setting_texte');

    /* ---- 007: ISSI am Fahrzeug ---- */
    if (ovb_table_exists($pdo, 'vehicles') && !ovb_column_exists($pdo, 'vehicles', 'issi')) {
        $pdo->exec("ALTER TABLE vehicles ADD COLUMN issi VARCHAR(80) NOT NULL DEFAULT '' AFTER funkrufname");
        $say('Fahrzeuge um die ISSI erweitert.');
    }
    $merken('007_vehicle_issi');

    /* ---- 008: Divera-Fahrzeugdaten ---- */
    if (ovb_table_exists($pdo, 'vehicles')) {
        $spalten = [
            'opta'              => "VARCHAR(60) NOT NULL DEFAULT '' AFTER issi",
            'ric'               => "VARCHAR(30) NOT NULL DEFAULT '' AFTER opta",
            'divera_vehicle_id' => 'INT UNSIGNED NULL AFTER ric',
            'fms_status'        => 'TINYINT NULL AFTER divera_vehicle_id',
            'fms_note'          => "VARCHAR(255) NOT NULL DEFAULT '' AFTER fms_status",
            'fms_at'            => 'DATETIME NULL AFTER fms_note',
            'geo_lat'           => 'DECIMAL(9,6) NULL AFTER fms_at',
            'geo_lng'           => 'DECIMAL(9,6) NULL AFTER geo_lat',
            'geo_at'            => 'DATETIME NULL AFTER geo_lng',
            'divera_besatzung'  => 'TEXT NULL AFTER geo_at',
            'divera_daten'      => 'MEDIUMTEXT NULL AFTER divera_besatzung',
            'divera_sync_at'    => 'DATETIME NULL AFTER divera_daten',
        ];
        $neu = [];
        foreach ($spalten as $name => $definition) {
            if (!ovb_column_exists($pdo, 'vehicles', $name)) {
                $pdo->exec("ALTER TABLE vehicles ADD COLUMN $name $definition");
                $neu[] = $name;
            }
        }
        if (!ovb_index_exists($pdo, 'vehicles', 'uq_divera_fz')) {
            $pdo->exec('ALTER TABLE vehicles ADD UNIQUE KEY uq_divera_fz (divera_vehicle_id)');
        }
        if ($neu) {
            $say('Fahrzeuge um Divera-Daten erweitert (' . count($neu) . ' Spalten).');
        }
    }
    if (ovb_table_exists($pdo, 'vehicle_journal')) {
        $typ = (string)$pdo->query(
            "SELECT COLUMN_TYPE FROM information_schema.columns
             WHERE table_schema = DATABASE() AND table_name = 'vehicle_journal' AND column_name = 'quelle'"
        )->fetchColumn();
        if ($typ !== '' && !str_contains($typ, 'divera')) {
            $pdo->exec(
                "ALTER TABLE vehicle_journal
                 MODIFY quelle ENUM('mensch','stein','divera','system') NOT NULL DEFAULT 'mensch'"
            );
        }
    }
    $merken('008_divera_fahrzeuge');

    /* ---- 009: Merkwerte aus der Einstellungsmaske nehmen ---- */
    // Sie wurden bisher in der Gruppe "Allgemein" angelegt und standen dort als
    // Textfelder; ein Speichern schrieb dann veraltete Werte zurück.
    if (ovb_table_exists($pdo, 'settings')) {
        $intern = ['stein_letzter_abruf', 'stein_pause_bis', 'stein_offene_assets',
                   'divera_status_letzter_abruf', 'divera_stamm_letzter_abruf', 'divera_offene_fahrzeuge'];
        $st = $pdo->prepare("UPDATE settings SET sgroup = '_intern', label = skey WHERE skey = ? AND sgroup <> '_intern'");
        $n = 0;
        foreach ($intern as $k) {
            $st->execute([$k]);
            $n += $st->rowCount();
        }
        if ($n > 0) {
            $say(sprintf('%d interne Merkwerte aus der Einstellungsmaske genommen.', $n));
        }
    }
    $merken('009_interne_merkwerte');

    /* ---- 010: richtige Divera-Pfade für Formulare ---- */
    // Die ersten Vorgaben (/v2/forms ...) gibt es bei Divera nicht; nur wer sie
    // nicht selbst geändert hat, bekommt die richtigen.
    if (ovb_table_exists($pdo, 'settings')) {
        $st = $pdo->prepare('UPDATE settings SET svalue = ? WHERE skey = ? AND svalue = ?');
        $st->execute(['/v2/reporttypes', 'divera_forms_path', '/v2/forms']);
        $n = $st->rowCount();
        $st->execute(['/v2/reporttypes/{form_id}/reports', 'divera_entries_path', '/v2/forms/{form_id}/entries']);
        $n += $st->rowCount();
        if ($n > 0) {
            $say('Divera-Pfade für Formulare auf /v2/reporttypes umgestellt.');
        }
    }
    $merken('010_divera_reporttypes');

    /* ---- 011: Aufgaben kennen ihre Besprechung ---- */
    if (ovb_table_exists($pdo, 'todos')) {
        if (!ovb_column_exists($pdo, 'todos', 'meeting_id')) {
            $pdo->exec('ALTER TABLE todos ADD COLUMN meeting_id INT UNSIGNED NULL AFTER wish_id,
                        ADD KEY idx_todo_meeting (meeting_id)');
        }
        if (!ovb_column_exists($pdo, 'todos', 'talking_point_id')) {
            $pdo->exec('ALTER TABLE todos ADD COLUMN talking_point_id INT UNSIGNED NULL AFTER meeting_id,
                        ADD KEY idx_todo_tp (talking_point_id)');
        }
        if (ovb_table_exists($pdo, 'meetings') && !ovb_constraint_exists($pdo, 'todos', 'fk_todo_meeting')) {
            $pdo->exec('ALTER TABLE todos ADD CONSTRAINT fk_todo_meeting
                        FOREIGN KEY (meeting_id) REFERENCES meetings(id) ON DELETE SET NULL');
        }
        if (ovb_table_exists($pdo, 'talking_points') && !ovb_constraint_exists($pdo, 'todos', 'fk_todo_tp')) {
            $pdo->exec('ALTER TABLE todos ADD CONSTRAINT fk_todo_tp
                        FOREIGN KEY (talking_point_id) REFERENCES talking_points(id) ON DELETE SET NULL');
        }
        // Bisher angelegte Aufgaben aus Talking Points nachtragen
        if (ovb_table_exists($pdo, 'talking_points')) {
            $n = $pdo->exec('UPDATE todos t JOIN talking_points tp ON tp.todo_id = t.id
                             SET t.talking_point_id = tp.id, t.meeting_id = tp.meeting_id
                             WHERE t.talking_point_id IS NULL');
            if ($n > 0) {
                $say(sprintf('%d Aufgabe(n) ihrer Besprechung zugeordnet.', $n));
            }
        }
    }
    $merken('011_todo_herkunft');

    /* ---- 012: Themen über Divera-Formulare einreichen ---- */
    if (ovb_table_exists($pdo, 'divera_forms') && !ovb_column_exists($pdo, 'divera_forms', 'ziel')) {
        $pdo->exec("ALTER TABLE divera_forms ADD COLUMN ziel VARCHAR(20) NOT NULL DEFAULT 'wunsch' AFTER name");
    }
    if (ovb_table_exists($pdo, 'divera_log') && !ovb_column_exists($pdo, 'divera_log', 'tp_id')) {
        $pdo->exec('ALTER TABLE divera_log ADD COLUMN tp_id INT UNSIGNED NULL AFTER wish_id');
    }
    if (ovb_table_exists($pdo, 'talking_points')) {
        if (!ovb_column_exists($pdo, 'talking_points', 'einbringer_name')) {
            $pdo->exec("ALTER TABLE talking_points ADD COLUMN einbringer_name VARCHAR(150) NOT NULL DEFAULT '' AFTER eingebracht_von");
        }
        if (!ovb_column_exists($pdo, 'talking_points', 'divera_form_id')) {
            $pdo->exec("ALTER TABLE talking_points
                        ADD COLUMN divera_form_id VARCHAR(60) NOT NULL DEFAULT '' AFTER einbringer_name,
                        ADD COLUMN divera_entry_id VARCHAR(60) NOT NULL DEFAULT '' AFTER divera_form_id");
        }
        if (!ovb_index_exists($pdo, 'talking_points', 'idx_tp_divera')) {
            $pdo->exec('ALTER TABLE talking_points ADD KEY idx_tp_divera (divera_form_id, divera_entry_id)');
        }
    }
    $merken('012_divera_themen');

    /* ---- 013: Bearbeitungsstand an Divera zurückmelden ---- */
    if (ovb_table_exists($pdo, 'divera_forms') && !ovb_column_exists($pdo, 'divera_forms', 'status_sync')) {
        $pdo->exec('ALTER TABLE divera_forms ADD COLUMN status_sync TINYINT(1) NOT NULL DEFAULT 0 AFTER ziel');
    }
    foreach (['wishes', 'talking_points'] as $tabelle) {
        if (ovb_table_exists($pdo, $tabelle) && !ovb_column_exists($pdo, $tabelle, 'divera_status')) {
            $pdo->exec("ALTER TABLE $tabelle ADD COLUMN divera_status TINYINT NULL AFTER divera_entry_id");
        }
    }
    $merken('013_divera_status');

    /* ---- 014: Rufnummern der Kontakte international schreiben ---- */
    if (ovb_table_exists($pdo, 'contacts')) {
        // phone_human() steht in util.php; die Wanderung läuft ohne bootstrap.php
        if (!function_exists('phone_human')) {
            require_once APP_ROOT . '/src/lib/util.php';
        }
        $land = '+49';
        $row = $pdo->query("SELECT svalue FROM settings WHERE skey = 'telefon_landesvorwahl'")->fetchColumn();
        if (is_string($row) && trim($row) !== '') {
            $land = trim($row);
        }
        $st = $pdo->prepare('UPDATE contacts SET telefon = ?, mobil = ? WHERE id = ?');
        $n = 0;
        foreach ($pdo->query('SELECT id, telefon, mobil FROM contacts') as $c) {
            $tel = phone_human((string)$c['telefon'], $land);
            $mob = phone_human((string)$c['mobil'], $land);
            if ($tel === (string)$c['telefon'] && $mob === (string)$c['mobil']) {
                continue;
            }
            $st->execute([mb_substr($tel, 0, 60), mb_substr($mob, 0, 60), (int)$c['id']]);
            $n++;
        }
        if ($n > 0) {
            $say(sprintf('%d Kontakt(e): Rufnummern international geschrieben.', $n));
        }
    }
    $merken('014_rufnummern');

    /* ---- 015: Benachrichtigungen über Home Assistant ---- */
    if (ovb_table_exists($pdo, 'users')) {
        if (!ovb_column_exists($pdo, 'users', 'ha_notify')) {
            $pdo->exec("ALTER TABLE users ADD COLUMN ha_notify VARCHAR(120) NOT NULL DEFAULT '' AFTER phone");
        }
        if (!ovb_column_exists($pdo, 'users', 'notify_aktiv')) {
            $pdo->exec('ALTER TABLE users ADD COLUMN notify_aktiv TINYINT(1) NOT NULL DEFAULT 1 AFTER ha_notify');
        }
    }
    $merken('015_benachrichtigungen');

    /* ---- 016: Web-Push-Abos ---- */
    if (!ovb_table_exists($pdo, 'push_subscriptions') && ovb_table_exists($pdo, 'users')) {
        $pdo->exec("CREATE TABLE push_subscriptions (
            id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id    INT UNSIGNED NOT NULL,
            endpoint   VARCHAR(500) NOT NULL,
            p256dh     VARCHAR(150) NOT NULL,
            auth       VARCHAR(60)  NOT NULL,
            geraet     VARCHAR(150) NOT NULL DEFAULT '',
            last_ok    DATETIME     NULL,
            created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_endpoint (endpoint(255)),
            KEY idx_push_user (user_id),
            CONSTRAINT fk_push_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $say('Tabelle für Web-Push-Abos angelegt.');
    }
    $merken('016_webpush');

    /* ---- 017: Anmerkungen zu Talking Points ---- */
    if (!ovb_table_exists($pdo, 'talking_point_comments') && ovb_table_exists($pdo, 'talking_points')) {
        $pdo->exec("CREATE TABLE talking_point_comments (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  tp_id      INT UNSIGNED NOT NULL,
  user_id    INT UNSIGNED NULL,
  body       TEXT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_tpc_tp (tp_id, id),
  CONSTRAINT fk_tpc_tp   FOREIGN KEY (tp_id)   REFERENCES talking_points(id) ON DELETE CASCADE,
  CONSTRAINT fk_tpc_user FOREIGN KEY (user_id) REFERENCES users(id)          ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $say('Tabelle für Anmerkungen zu Talking Points angelegt.');
    }
    $merken('017_tp_anmerkungen');

    /* ---- 018: Favoriten bei den Fahrzeugen ---- */
    if (!ovb_table_exists($pdo, 'vehicle_favorites')
        && ovb_table_exists($pdo, 'vehicles') && ovb_table_exists($pdo, 'users')) {
        $pdo->exec("CREATE TABLE vehicle_favorites (
  user_id    INT UNSIGNED NOT NULL,
  vehicle_id INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, vehicle_id),
  KEY idx_vfav_fz (vehicle_id),
  CONSTRAINT fk_vfav_user FOREIGN KEY (user_id)    REFERENCES users(id)    ON DELETE CASCADE,
  CONSTRAINT fk_vfav_fz   FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $say('Tabelle für Fahrzeug-Favoriten angelegt.');
    }
    $merken('018_fahrzeug_favoriten');

    /* ---- 019: Nummer der THW-Verwaltung am Instandsetzungsauftrag ---- */
    if (ovb_table_exists($pdo, 'vehicle_orders') && !ovb_column_exists($pdo, 'vehicle_orders', 'thw_nummer')) {
        $pdo->exec("ALTER TABLE vehicle_orders
                    ADD COLUMN thw_nummer VARCHAR(60) NOT NULL DEFAULT '' AFTER auftragsnummer,
                    ADD KEY idx_thw_nummer (thw_nummer)");
    }
    $merken('019_thw_nummer');

    /* ---- 020: Wünsche einem Fahrzeug zuordnen ---- */
    if (ovb_table_exists($pdo, 'wishes')) {
        if (!ovb_column_exists($pdo, 'wishes', 'vehicle_id')) {
            $pdo->exec('ALTER TABLE wishes ADD COLUMN vehicle_id INT UNSIGNED NULL AFTER budget_id,
                        ADD KEY idx_w_fahrzeug (vehicle_id)');
        }
        if (ovb_table_exists($pdo, 'vehicles') && !ovb_constraint_exists($pdo, 'wishes', 'fk_w_fahrzeug')) {
            $pdo->exec('ALTER TABLE wishes ADD CONSTRAINT fk_w_fahrzeug
                        FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE SET NULL');
        }
    }
    $merken('020_wunsch_fahrzeug');

    /* ---- 021: Herkunft des Standorts ---- */
    if (ovb_table_exists($pdo, 'vehicles') && !ovb_column_exists($pdo, 'vehicles', 'geo_quelle')) {
        $pdo->exec("ALTER TABLE vehicles ADD COLUMN geo_quelle VARCHAR(20) NOT NULL DEFAULT '' AFTER geo_lng");
        // Was schon da ist, kam bisher nur aus Divera
        $pdo->exec("UPDATE vehicles SET geo_quelle = 'divera' WHERE geo_lat IS NOT NULL AND geo_quelle = ''");
    }
    $merken('021_standort_quelle');

    /* ---- 022: QR-Standortmeldung ---- */
    if (ovb_table_exists($pdo, 'vehicles')) {
        foreach ([
            'geo_park_seit'     => 'DATETIME NULL',
            'geo_park_gemeldet' => 'TINYINT(1) NOT NULL DEFAULT 0',
            'qr_token'          => "VARCHAR(80) NOT NULL DEFAULT ''",
        ] as $spalte => $art) {
            if (!ovb_column_exists($pdo, 'vehicles', $spalte)) {
                $pdo->exec("ALTER TABLE vehicles ADD COLUMN $spalte $art");
            }
        }
        if (!ovb_index_exists($pdo, 'vehicles', 'idx_qr_token')) {
            $pdo->exec('ALTER TABLE vehicles ADD KEY idx_qr_token (qr_token)');
        }
    }
    $merken('022_qr_standort');

    /* ---- 023: mehrere Connectoren ---- */
    if (!ovb_table_exists($pdo, 'connectors')) {
        $pdo->exec(
            "CREATE TABLE connectors (
               id                   INT UNSIGNED NOT NULL AUTO_INCREMENT,
               name                 VARCHAR(100) NOT NULL,
               url                  VARCHAR(255) NOT NULL DEFAULT '',
               kurz_url             VARCHAR(255) NOT NULL DEFAULT '',
               fuer_fahrzeuge       TINYINT(1)   NOT NULL DEFAULT 1,
               fuer_veranstaltungen TINYINT(1)   NOT NULL DEFAULT 0,
               is_active            TINYINT(1)   NOT NULL DEFAULT 1,
               pem                  TEXT         NULL,
               pubkey               VARCHAR(255) NOT NULL DEFAULT '',
               server_pub           VARCHAR(255) NOT NULL DEFAULT '',
               version              VARCHAR(20)  NOT NULL DEFAULT '',
               gekoppelt_am         DATETIME     NULL,
               angemeldet_am        DATETIME     NULL,
               letzter_abruf        DATETIME     NULL,
               notiz                TEXT         NULL,
               created_at           DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
               updated_at           DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
               PRIMARY KEY (id)
             ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }
    if (ovb_table_exists($pdo, 'vehicles') && !ovb_column_exists($pdo, 'vehicles', 'qr_connector_id')) {
        $pdo->exec('ALTER TABLE vehicles ADD COLUMN qr_connector_id INT UNSIGNED NULL AFTER qr_token');
    }
    if (ovb_table_exists($pdo, 'vehicles') && !ovb_constraint_exists($pdo, 'vehicles', 'fk_fz_con')) {
        $pdo->exec('ALTER TABLE vehicles ADD CONSTRAINT fk_fz_con FOREIGN KEY (qr_connector_id)
                    REFERENCES connectors(id) ON DELETE SET NULL');
    }

    // Den bisher einzigen Connector aus den Einstellungen in die Tabelle holen
    if (ovb_table_exists($pdo, 'settings')
        && (int)$pdo->query('SELECT COUNT(*) FROM connectors')->fetchColumn() === 0) {
        $wert = static function (string $schluessel) use ($pdo): string {
            $st = $pdo->prepare('SELECT svalue FROM settings WHERE skey = ?');
            $st->execute([$schluessel]);
            return (string)($st->fetchColumn() ?: '');
        };
        $url = rtrim(trim($wert('connector_url')), '/');
        if ($url !== '') {
            $serverPub = $wert('connector_server_pub');
            $abruf = (int)$wert('connector_letzter_abruf');
            $st = $pdo->prepare(
                'INSERT INTO connectors (name, url, fuer_fahrzeuge, is_active, pem, pubkey,
                                         server_pub, gekoppelt_am, angemeldet_am, letzter_abruf)
                 VALUES (?,?,1,?,?,?,?,?,?,?)'
            );
            $st->execute([
                'Connector',
                $url,
                $wert('connector_aktiv') === '1' ? 1 : 0,
                $wert('connector_pem'),
                $wert('connector_pub'),
                $serverPub,
                $serverPub !== '' ? date('Y-m-d H:i:s') : null,
                // Fassung 2 hiess: die Zugaenge sind dort bereits angemeldet
                $wert('connector_format') === '2' ? date('Y-m-d H:i:s') : null,
                $abruf > 0 ? date('Y-m-d H:i:s', $abruf) : null,
            ]);
            $id = (int)$pdo->lastInsertId();
            if (ovb_column_exists($pdo, 'vehicles', 'qr_connector_id')) {
                $pdo->prepare("UPDATE vehicles SET qr_connector_id = ? WHERE qr_token <> ''")->execute([$id]);
            }
        }
    }
    // Adresse und Schluessel stehen jetzt am Connector, nicht mehr in den Einstellungen
    if (ovb_table_exists($pdo, 'settings')) {
        $pdo->exec("DELETE FROM settings WHERE skey IN
                    ('connector_url','connector_pem','connector_pub','connector_server_pub',
                     'connector_angemeldet','connector_format')");
    }
    $merken('023_connectoren');

    /* ---- 024: Veranstaltungen ---- */
    if (!ovb_table_exists($pdo, 'events')) {
        $pdo->exec(<<<'SQL'
CREATE TABLE events (
  id                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
  titel              VARCHAR(200) NOT NULL,
  beschreibung       TEXT         NULL,
  ort                VARCHAR(200) NOT NULL DEFAULT '',
  beginn             DATETIME     NOT NULL,
  ende               DATETIME     NULL,
  status             ENUM('geplant','laeuft','abgeschlossen','abgesagt') NOT NULL DEFAULT 'geplant',
  jahr               SMALLINT     NOT NULL,
  budget_id          INT UNSIGNED NULL,
  fachgruppe_id      INT UNSIGNED NULL,
  kosten_geplant     DECIMAL(12,2) NOT NULL DEFAULT 0,
  connector_id       INT UNSIGNED NULL,
  code_laenge        TINYINT UNSIGNED NOT NULL DEFAULT 6,
  begleiter_max      TINYINT UNSIGNED NOT NULL DEFAULT 0,
  kommentare_erlaubt TINYINT(1)   NOT NULL DEFAULT 1,
  vertretung_erlaubt TINYINT(1)   NOT NULL DEFAULT 1,
  rueckmeldung_bis   DATE         NULL,
  hinweis            TEXT         NULL,
  einladung_aktiv    TINYINT(1)   NOT NULL DEFAULT 0,
  angemeldet_am      DATETIME     NULL,
  notiz              TEXT         NULL,
  created_by         INT UNSIGNED NULL,
  updated_by         INT UNSIGNED NULL,
  created_at         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_ev_beginn (beginn),
  KEY idx_ev_jahr (jahr, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }
    if (!ovb_table_exists($pdo, 'event_guests')) {
        $pdo->exec(<<<'SQL'
CREATE TABLE event_guests (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  event_id       INT UNSIGNED NOT NULL,
  contact_id     INT UNSIGNED NULL,
  name           VARCHAR(150) NOT NULL DEFAULT '',
  code           VARCHAR(32)  NOT NULL,
  status         ENUM('offen','zusage','absage','vertretung') NOT NULL DEFAULT 'offen',
  begleiter      TINYINT UNSIGNED NOT NULL DEFAULT 0,
  vertretung     VARCHAR(150) NOT NULL DEFAULT '',
  kommentar      TEXT         NULL,
  quelle         VARCHAR(20)  NOT NULL DEFAULT '',
  geantwortet_am DATETIME     NULL,
  notiz          TEXT         NULL,
  created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_code (code),
  UNIQUE KEY uq_event_kontakt (event_id, contact_id),
  KEY idx_eg_event (event_id, status),
  CONSTRAINT fk_eg_event FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }
    if (!ovb_table_exists($pdo, 'event_files')) {
        $pdo->exec(<<<'SQL'
CREATE TABLE event_files (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  event_id     INT UNSIGNED NOT NULL,
  art          VARCHAR(20)  NOT NULL DEFAULT 'dokument',
  titel        VARCHAR(200) NOT NULL DEFAULT '',
  orig_name    VARCHAR(255) NOT NULL DEFAULT '',
  stored_name  VARCHAR(255) NOT NULL,
  thumb_name   VARCHAR(255) NULL,
  mime         VARCHAR(120) NOT NULL DEFAULT '',
  size_bytes   INT UNSIGNED NOT NULL DEFAULT 0,
  betrag       DECIMAL(12,2) NULL,
  uploaded_by  INT UNSIGNED NULL,
  created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_ef_event (event_id),
  CONSTRAINT fk_ef_event FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }
    // Buchungen koennen zu einer Veranstaltung gehoeren
    if (ovb_table_exists($pdo, 'expenses') && !ovb_column_exists($pdo, 'expenses', 'event_id')) {
        $pdo->exec('ALTER TABLE expenses ADD COLUMN event_id INT UNSIGNED NULL AFTER wish_id');
    }
    if (ovb_table_exists($pdo, 'expenses') && !ovb_constraint_exists($pdo, 'expenses', 'fk_exp_ev')) {
        $pdo->exec('ALTER TABLE expenses ADD CONSTRAINT fk_exp_ev FOREIGN KEY (event_id)
                    REFERENCES events(id) ON DELETE SET NULL');
    }
    $merken('024_veranstaltungen');

    /* ---- 025: Arten von Veranstaltungen ---- */
    if (ovb_table_exists($pdo, 'events') && !ovb_column_exists($pdo, 'events', 'typ_id')) {
        $pdo->exec('ALTER TABLE events ADD COLUMN typ_id INT UNSIGNED NULL AFTER status');
    }
    if (ovb_table_exists($pdo, 'events') && !ovb_constraint_exists($pdo, 'events', 'fk_ev_typ')) {
        $pdo->exec('ALTER TABLE events ADD CONSTRAINT fk_ev_typ FOREIGN KEY (typ_id)
                    REFERENCES list_items(id) ON DELETE SET NULL');
    }
    $merken('025_veranstaltungsarten');

    /* ---- 026: Veranstaltungen ohne Gaesteliste ---- */
    if (ovb_table_exists($pdo, 'events')) {
        foreach ([
            'gaesteliste'        => 'TINYINT(1) NOT NULL DEFAULT 1',
            'teilnehmer_geplant' => 'SMALLINT UNSIGNED NULL',
            'teilnehmer_ist'     => 'SMALLINT UNSIGNED NULL',
        ] as $spalte => $art) {
            if (!ovb_column_exists($pdo, 'events', $spalte)) {
                $pdo->exec("ALTER TABLE events ADD COLUMN $spalte $art");
            }
        }
    }
    $merken('026_teilnehmerzahl');

    /* ---- 027: SIM-Karten ---- */
    if (!ovb_table_exists($pdo, 'sims')) {
        $pdo->exec(<<<'SQL'
CREATE TABLE sims (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  rufnummer     VARCHAR(40)  NOT NULL DEFAULT '',
  iccid         VARCHAR(30)  NOT NULL DEFAULT '',
  typ_id        INT UNSIGNED NULL,
  status_id     INT UNSIGNED NULL,
  anbieter      VARCHAR(80)  NOT NULL DEFAULT '',
  tarif         VARCHAR(120) NOT NULL DEFAULT '',
  datenvolumen  VARCHAR(40)  NOT NULL DEFAULT '',
  kosten_monat  DECIMAL(10,2) NULL,
  vertrag_bis   DATE         NULL,
  pin           VARCHAR(20)  NOT NULL DEFAULT '',
  puk           VARCHAR(30)  NOT NULL DEFAULT '',
  geraet        VARCHAR(150) NOT NULL DEFAULT '',
  ziel_typ      ENUM('ov','fahrzeug','fachgruppe','person') NOT NULL DEFAULT 'ov',
  ziel_id       INT UNSIGNED NULL,
  ausgegeben_am DATE         NULL,
  notiz         TEXT         NULL,
  is_active     TINYINT(1)   NOT NULL DEFAULT 1,
  created_by    INT UNSIGNED NULL,
  updated_by    INT UNSIGNED NULL,
  created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_sim_ziel (ziel_typ, ziel_id),
  KEY idx_sim_nummer (rufnummer)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }
    $merken('027_simkarten');

    /* ---- 028: TETRA-Karten, Vertrag und PIN zuschaltbar ---- */
    if (ovb_table_exists($pdo, 'sims')) {
        foreach ([
            'karte_art'   => "ENUM('mobilfunk','tetra') NOT NULL DEFAULT 'mobilfunk'",
            'issi'        => "VARCHAR(40) NOT NULL DEFAULT ''",
            'opta'        => "VARCHAR(60) NOT NULL DEFAULT ''",
            'hat_vertrag' => 'TINYINT(1) NOT NULL DEFAULT 0',
            'hat_pin'     => 'TINYINT(1) NOT NULL DEFAULT 0',
        ] as $spalte => $art) {
            if (!ovb_column_exists($pdo, 'sims', $spalte)) {
                $pdo->exec("ALTER TABLE sims ADD COLUMN $spalte $art");
            }
        }
        // Was schon Vertragsangaben oder eine PIN hat, bekommt den Haken gesetzt
        $pdo->exec("UPDATE sims SET hat_vertrag = 1
                    WHERE vertrag_bis IS NOT NULL OR kosten_monat IS NOT NULL
                       OR anbieter <> '' OR tarif <> '' OR datenvolumen <> ''");
        $pdo->exec("UPDATE sims SET hat_pin = 1 WHERE pin <> '' OR puk <> ''");
    }
    $merken('028_tetra_karten');
}

/**
 * Beschriftung, Hinweis, Typ und Gruppe der Einstellungen aus seed.sql
 * uebernehmen. Der gespeicherte Wert (svalue) bleibt, wie er ist.
 */
function ovb_refresh_setting_texts(PDO $pdo, string $seedFile): int
{
    if (!is_file($seedFile)) {
        return 0;
    }
    $n = 0;
    $st = $pdo->prepare(
        'UPDATE settings SET label = ?, hint = ?, stype = ?, sgroup = ?, sort_order = ?
         WHERE skey = ? AND (label <> ? OR hint <> ? OR stype <> ? OR sgroup <> ? OR sort_order <> ?)'
    );
    foreach (ovb_seed_settings((string)file_get_contents($seedFile)) as $r) {
        $st->execute([
            $r['label'], $r['hint'], $r['stype'], $r['sgroup'], $r['sort'],
            $r['skey'],
            $r['label'], $r['hint'], $r['stype'], $r['sgroup'], $r['sort'],
        ]);
        $n += $st->rowCount();
    }
    return $n;
}

/**
 * Die Einstellungszeilen aus seed.sql lesen. Reine Funktion, damit sie sich
 * ohne Datenbank pruefen laesst.
 * Format je Zeile: ('skey','svalue','label','hint','stype','sgroup',sort),
 */
function ovb_seed_settings(string $sql): array
{
    $out = [];
    $muster = "/^\('([a-z0-9_]+)','((?:[^']|'')*)','((?:[^']|'')*)','((?:[^']|'')*)',"
        . "'([a-z]+)','((?:[^']|'')*)',(\d+)\)[,;]\s*$/mi";
    if (!preg_match_all($muster, $sql, $treffer, PREG_SET_ORDER)) {
        return $out;
    }
    $entf = static fn(string $v): string => str_replace("''", "'", $v);
    foreach ($treffer as $m) {
        $out[] = [
            'skey'   => $m[1],
            'label'  => $entf($m[3]),
            'hint'   => $entf($m[4]),
            'stype'  => $m[5],
            'sgroup' => $entf($m[6]),
            'sort'   => (int)$m[7],
        ];
    }
    return $out;
}

function ovb_constraint_exists(PDO $pdo, string $table, string $name): bool
{
    $st = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.table_constraints
         WHERE constraint_schema = DATABASE() AND table_name = ? AND constraint_name = ?'
    );
    $st->execute([$table, $name]);
    return (int)$st->fetchColumn() > 0;
}
