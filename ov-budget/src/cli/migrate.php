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
        ['radios',         'typ_id',              ''],
        ['radios',         'status_id',           ''],
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

    /* ---- 029: Funkgeraete, Gruppen und Bestands-QR ---- */
    if (!ovb_table_exists($pdo, 'radio_groups')) {
        $pdo->exec(<<<'SQL'
CREATE TABLE radio_groups (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name            VARCHAR(150) NOT NULL,
  beschreibung    TEXT         NULL,
  lagerort        VARCHAR(150) NOT NULL DEFAULT '',
  ziel_typ        ENUM('ov','fahrzeug','fachgruppe','person') NOT NULL DEFAULT 'ov',
  ziel_id         INT UNSIGNED NULL,
  qr_token        VARCHAR(80)  NOT NULL DEFAULT '',
  qr_connector_id INT UNSIGNED NULL,
  zuletzt_gesehen DATETIME     NULL,
  zuletzt_anzahl  SMALLINT UNSIGNED NULL,
  zuletzt_melder  VARCHAR(60)  NOT NULL DEFAULT '',
  is_active       TINYINT(1)   NOT NULL DEFAULT 1,
  created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_gruppe_qr (qr_token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }
    if (!ovb_table_exists($pdo, 'radios')) {
        $pdo->exec(<<<'SQL'
CREATE TABLE radios (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  bezeichnung    VARCHAR(150) NOT NULL,
  typ_id         INT UNSIGNED NULL,
  status_id      INT UNSIGNED NULL,
  hersteller     VARCHAR(80)  NOT NULL DEFAULT '',
  modell         VARCHAR(80)  NOT NULL DEFAULT '',
  seriennummer   VARCHAR(60)  NOT NULL DEFAULT '',
  inventarnummer VARCHAR(60)  NOT NULL DEFAULT '',
  funkrufname    VARCHAR(80)  NOT NULL DEFAULT '',
  group_id       INT UNSIGNED NULL,
  qr_token        VARCHAR(80) NOT NULL DEFAULT '',
  qr_connector_id INT UNSIGNED NULL,
  zuletzt_gesehen DATETIME    NULL,
  zuletzt_melder  VARCHAR(60) NOT NULL DEFAULT '',
  ziel_typ       ENUM('ov','fahrzeug','fachgruppe','person') NOT NULL DEFAULT 'ov',
  ziel_id        INT UNSIGNED NULL,
  standort       VARCHAR(150) NOT NULL DEFAULT '',
  beschafft_am   DATE         NULL,
  pruefung_bis   DATE         NULL,
  notiz          TEXT         NULL,
  is_active      TINYINT(1)   NOT NULL DEFAULT 1,
  created_by     INT UNSIGNED NULL,
  updated_by     INT UNSIGNED NULL,
  created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_funk_ziel (ziel_typ, ziel_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }
    // Fuer Bestandsinstallationen: die Spalten einzeln nachziehen
    if (ovb_table_exists($pdo, 'radios')) {
        foreach ([
            'group_id'        => 'INT UNSIGNED NULL',
            'qr_token'        => "VARCHAR(80) NOT NULL DEFAULT ''",
            'qr_connector_id' => 'INT UNSIGNED NULL',
            'zuletzt_gesehen' => 'DATETIME NULL',
            'zuletzt_melder'  => "VARCHAR(60) NOT NULL DEFAULT ''",
        ] as $spalte => $art) {
            if (!ovb_column_exists($pdo, 'radios', $spalte)) {
                $pdo->exec("ALTER TABLE radios ADD COLUMN $spalte $art");
            }
        }
    }
    if (ovb_table_exists($pdo, 'sims') && !ovb_column_exists($pdo, 'sims', 'radio_id')) {
        $pdo->exec('ALTER TABLE sims ADD COLUMN radio_id INT UNSIGNED NULL AFTER geraet');
    }
    if (ovb_table_exists($pdo, 'sims') && !ovb_constraint_exists($pdo, 'sims', 'fk_sim_funk')) {
        $pdo->exec('ALTER TABLE sims ADD CONSTRAINT fk_sim_funk FOREIGN KEY (radio_id)
                    REFERENCES radios(id) ON DELETE SET NULL');
    }
    if (ovb_table_exists($pdo, 'connectors') && !ovb_column_exists($pdo, 'connectors', 'fuer_bestand')) {
        $pdo->exec('ALTER TABLE connectors ADD COLUMN fuer_bestand TINYINT(1) NOT NULL DEFAULT 0
                    AFTER fuer_veranstaltungen');
    }
    $merken('029_funkgeraete');

    /* ---------- 030: keine Mehrwertsteuer mehr ---------- */
    // Der Betrag ist, was tatsächlich geflossen ist. Beide Spalten tragen
    // denselben Wert, damit ältere Auswertungen und Sicherungen weiter passen.
    if (ovb_table_exists($pdo, 'expenses')) {
        $n = $pdo->exec('UPDATE expenses SET betrag_netto = betrag_brutto, mwst_satz = 0
                         WHERE betrag_netto <> betrag_brutto OR mwst_satz <> 0');
        if ($n > 0) {
            $say("Buchungen ohne Mehrwertsteuer: $n Zeile(n) angeglichen.");
        }
        $pdo->exec('ALTER TABLE expenses ALTER COLUMN mwst_satz SET DEFAULT 0');
    }
    if (ovb_table_exists($pdo, 'wishes')) {
        $pdo->exec('UPDATE wishes SET mwst_satz = 0 WHERE mwst_satz <> 0');
        $pdo->exec('ALTER TABLE wishes ALTER COLUMN mwst_satz SET DEFAULT 0');
    }
    if (ovb_table_exists($pdo, 'settings')) {
        $pdo->exec("DELETE FROM settings WHERE skey IN ('mwst_satz', 'ausgaben_betragsart')");
    }
    $merken('030_ohne_mwst');

    /* ---------- 031: Verbrauch – Zaehler, Staende, Tarife ---------- */
    if (ovb_table_exists($pdo, 'connectors') && !ovb_column_exists($pdo, 'connectors', 'fuer_verbrauch')) {
        $pdo->exec('ALTER TABLE connectors ADD COLUMN fuer_verbrauch TINYINT(1) NOT NULL DEFAULT 0
                    AFTER fuer_bestand');
    }
    $verbrauchSql = <<<'SQL'
-- ------------------------------------------------------------
-- Verbrauch: Zaehler fuer Strom, Gas und Wasser, ihre Staende, Tarife
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS meters (
  id              INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  art             ENUM('strom','gas','wasser') NOT NULL DEFAULT 'strom',
  name            VARCHAR(150)  NOT NULL,
  zaehlernummer   VARCHAR(80)   NOT NULL DEFAULT '',
  standort        VARCHAR(150)  NOT NULL DEFAULT '',
  einheit         VARCHAR(10)   NOT NULL DEFAULT 'kWh',
  -- Umrechnung Zaehlereinheit -> Tarifeinheit (Gas: m3 -> kWh)
  umrechnung      DECIMAL(10,4) NOT NULL DEFAULT 1.0000,
  quelle          ENUM('manuell','ha') NOT NULL DEFAULT 'manuell',
  ha_entity       VARCHAR(200)  NOT NULL DEFAULT '',
  ha_faktor       DECIMAL(12,6) NOT NULL DEFAULT 1.000000,
  qr_token        VARCHAR(80)   NOT NULL DEFAULT '',
  qr_connector_id INT UNSIGNED  NULL,
  notiz           TEXT          NULL,
  is_active       TINYINT(1)    NOT NULL DEFAULT 1,
  created_by      INT UNSIGNED  NULL,
  created_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_meter_art (art, is_active),
  KEY idx_meter_qr (qr_token),
  CONSTRAINT fk_meter_con FOREIGN KEY (qr_connector_id) REFERENCES connectors(id) ON DELETE SET NULL,
  CONSTRAINT fk_meter_cb  FOREIGN KEY (created_by)      REFERENCES users(id)      ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS meter_readings (
  id          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  meter_id    INT UNSIGNED  NOT NULL,
  stand       DECIMAL(14,3) NOT NULL,
  gelesen_am  DATETIME      NOT NULL,
  quelle      ENUM('manuell','ha','qr') NOT NULL DEFAULT 'manuell',
  melder      VARCHAR(60)   NOT NULL DEFAULT '',
  notiz       VARCHAR(255)  NOT NULL DEFAULT '',
  created_by  INT UNSIGNED  NULL,
  created_at  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_reading_meter (meter_id, gelesen_am),
  CONSTRAINT fk_reading_meter FOREIGN KEY (meter_id)   REFERENCES meters(id) ON DELETE CASCADE,
  CONSTRAINT fk_reading_cb    FOREIGN KEY (created_by) REFERENCES users(id)  ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tariffs (
  id               INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  art              ENUM('strom','gas','wasser') NOT NULL DEFAULT 'strom',
  name             VARCHAR(150)  NOT NULL,
  anbieter         VARCHAR(150)  NOT NULL DEFAULT '',
  gueltig_von      DATE          NOT NULL,
  gueltig_bis      DATE          NULL,
  arbeitspreis     DECIMAL(10,4) NOT NULL DEFAULT 0,
  grundpreis_monat DECIMAL(10,2) NOT NULL DEFAULT 0,
  einheit          VARCHAR(10)   NOT NULL DEFAULT 'kWh',
  notiz            TEXT          NULL,
  created_at       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_tarif (art, gueltig_von)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;
    foreach (array_filter(array_map('trim', explode(";\n", $verbrauchSql))) as $anweisung) {
        if (preg_match('/CREATE TABLE IF NOT EXISTS (\w+)/', $anweisung, $m) && !ovb_table_exists($pdo, $m[1])) {
            $pdo->exec($anweisung);
            $say('Tabelle ' . $m[1] . ' angelegt.');
        }
    }
    $merken('031_verbrauch');

    /* ---------- 032: Abfrage aus Home Assistant in Minuten statt Stunden ---------- */
    if (ovb_table_exists($pdo, 'settings')) {
        $st = $pdo->prepare('SELECT svalue FROM settings WHERE skey = ?');
        $st->execute(['verbrauch_ha_intervall_stunden']);
        $alt = $st->fetchColumn();
        if ($alt !== false) {
            $minuten = max(5, (int)$alt * 60);
            $pdo->prepare('INSERT INTO settings (skey, svalue, sgroup, label, stype, sort_order)
                           VALUES (?, ?, ?, ?, ?, ?)
                           ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)')
                ->execute(['verbrauch_ha_intervall_minuten', (string)$minuten, 'Verbrauch',
                           'Zähler aus Home Assistant lesen alle … Minuten', 'number', 50]);
            $pdo->prepare('DELETE FROM settings WHERE skey = ?')->execute(['verbrauch_ha_intervall_stunden']);
            $say("Abfrage aus Home Assistant: $alt Stunden werden $minuten Minuten.");
        }
    }
    $merken('032_ha_intervall_minuten');

    /* ---------- 033: Index auf den Zeitpunkt der Staende ---------- */
    if (ovb_table_exists($pdo, 'meter_readings') && !ovb_index_exists($pdo, 'meter_readings', 'idx_reading_zeit')) {
        $pdo->exec('ALTER TABLE meter_readings ADD KEY idx_reading_zeit (gelesen_am)');
    }
    $merken('033_reading_zeit_index');

    /* ---------- 034: OV-Budget heisst OV-Multitool ---------- */
    if (ovb_table_exists($pdo, 'settings')) {
        $n = $pdo->exec("UPDATE settings SET svalue = 'OV-Multitool' WHERE skey = 'app_name' AND svalue = 'OV-Budget'");
        if ($n > 0) {
            $say('Die Anwendung heisst jetzt OV-Multitool (Name der Anwendung angepasst).');
        }
    }
    $merken('034_multitool');

    /* ---------- 035: fest verbaute Funkgeraete ---------- */
    if (ovb_table_exists($pdo, 'radios') && !ovb_column_exists($pdo, 'radios', 'fest_verbaut')) {
        $pdo->exec('ALTER TABLE radios ADD COLUMN fest_verbaut TINYINT(1) NOT NULL DEFAULT 0 AFTER is_active');
        // Bestand nachziehen: Fahrzeugfunk und Feststationen sind fest verbaut
        $n = $pdo->exec("UPDATE radios r JOIN list_items t ON t.id = r.typ_id
                         SET r.fest_verbaut = 1
                         WHERE t.list_key = 'funk_typ' AND t.slug IN ('mrt', 'frt')");
        if ($n > 0) {
            $say("Funkgeraete: $n Geraet(e) als fest verbaut gekennzeichnet (MRT, FRT).");
        }
    }
    $merken('035_funk_fest_verbaut');

    /* ---------- 036: Verpflegung bei Veranstaltungen ---------- */
    if (ovb_table_exists($pdo, 'events')) {
        foreach ([
            'verpflegung'             => 'TINYINT(1) NOT NULL DEFAULT 0',
            'verpflegung_personen'    => 'SMALLINT UNSIGNED NULL',
            'verpflegung_fruehstueck' => 'TINYINT UNSIGNED NOT NULL DEFAULT 0',
            'verpflegung_mittag'      => 'TINYINT UNSIGNED NOT NULL DEFAULT 0',
            'verpflegung_abend'       => 'TINYINT UNSIGNED NOT NULL DEFAULT 0',
            'verpflegung_notiz'       => "VARCHAR(255) NOT NULL DEFAULT ''",
        ] as $spalte => $art) {
            if (!ovb_column_exists($pdo, 'events', $spalte)) {
                $pdo->exec("ALTER TABLE events ADD COLUMN $spalte $art");
            }
        }
    }
    $merken('036_verpflegung');

    /* ---------- 037: Tagessatz statt dreier Einzelsaetze ---------- */
    if (!ovb_table_exists($pdo, 'verpflegung_saetze')) {
        $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS verpflegung_saetze (
  id                 INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  gueltig_von        DATE          NOT NULL,
  gueltig_bis        DATE          NULL,
  tagessatz          DECIMAL(8,2)  NOT NULL DEFAULT 0,
  anteil_fruehstueck TINYINT UNSIGNED NOT NULL DEFAULT 20,
  anteil_mittag      TINYINT UNSIGNED NOT NULL DEFAULT 40,
  anteil_abend       TINYINT UNSIGNED NOT NULL DEFAULT 40,
  notiz              VARCHAR(150)  NOT NULL DEFAULT '',
  created_at         DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_tagessatz (gueltig_von)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);
        // Bisherige Einzelsaetze (falls schon eingetragen) als ersten Tagessatz uebernehmen
        $st = $pdo->prepare('SELECT skey, svalue FROM settings WHERE skey IN (?, ?, ?)');
        $st->execute(['verpflegung_satz_fruehstueck', 'verpflegung_satz_mittag', 'verpflegung_satz_abend']);
        $alt = ['verpflegung_satz_fruehstueck' => 0.0, 'verpflegung_satz_mittag' => 0.0, 'verpflegung_satz_abend' => 0.0];
        foreach ($st->fetchAll() as $r) {
            $alt[$r['skey']] = (float)str_replace(',', '.', (string)$r['svalue']);
        }
        $summe = array_sum($alt);
        if ($summe > 0) {
            $f = (int)round($alt['verpflegung_satz_fruehstueck'] / $summe * 100);
            $m = (int)round($alt['verpflegung_satz_mittag'] / $summe * 100);
            $pdo->prepare('INSERT INTO verpflegung_saetze (gueltig_von, tagessatz, anteil_fruehstueck, anteil_mittag, anteil_abend, notiz)
                           VALUES (?, ?, ?, ?, ?, ?)')
                ->execute([date('Y-01-01'), round($summe, 2), $f, $m, 100 - $f - $m, 'aus den bisherigen Einzelsaetzen']);
            $say('Verpflegung: bisherige Einzelsaetze als Tagessatz ' . number_format($summe, 2, ',', '.') . ' EUR uebernommen.');
        }
        $pdo->exec("DELETE FROM settings WHERE skey IN ('verpflegung_satz_fruehstueck', 'verpflegung_satz_mittag', 'verpflegung_satz_abend')");
    }
    $merken('037_tagessatz');

    /* ---------- 038: Mahlzeiten je Kalendertag ---------- */
    if (ovb_table_exists($pdo, 'events') && !ovb_column_exists($pdo, 'events', 'verpflegung_tage')) {
        $pdo->exec('ALTER TABLE events ADD COLUMN verpflegung_tage TEXT NULL AFTER verpflegung_notiz');
    }
    $merken('038_verpflegung_tage');

    /* ---------- 039: Zaehlerrollen (Unterzaehler, Solar) und Einspeiseverguetung ---------- */
    if (ovb_table_exists($pdo, 'meters')) {
        if (!ovb_column_exists($pdo, 'meters', 'rolle')) {
            $pdo->exec("ALTER TABLE meters ADD COLUMN rolle ENUM('bezug','unter','erzeugung','einspeisung') NOT NULL DEFAULT 'bezug' AFTER art");
        }
        if (!ovb_column_exists($pdo, 'meters', 'parent_id')) {
            $pdo->exec('ALTER TABLE meters ADD COLUMN parent_id INT UNSIGNED NULL AFTER rolle');
        }
        if (!ovb_constraint_exists($pdo, 'meters', 'fk_meter_parent')) {
            $pdo->exec('ALTER TABLE meters ADD CONSTRAINT fk_meter_parent FOREIGN KEY (parent_id) REFERENCES meters(id) ON DELETE SET NULL');
        }
    }
    if (ovb_table_exists($pdo, 'tariffs')) {
        $pdo->exec("ALTER TABLE tariffs MODIFY art ENUM('strom','gas','wasser','einspeisung') NOT NULL DEFAULT 'strom'");
    }
    $merken('039_zaehler_rollen');

    /* ---------- 040: Zweiter Faktor (TOTP) ---------- */
    foreach ([
        'totp_secret'  => "VARCHAR(64) NOT NULL DEFAULT '' AFTER must_change_pw",
        'totp_aktiv'   => 'TINYINT(1) NOT NULL DEFAULT 0 AFTER totp_secret',
        'totp_seit'    => 'DATETIME NULL AFTER totp_aktiv',
        'totp_letzter' => 'INT UNSIGNED NOT NULL DEFAULT 0 AFTER totp_seit',
        'totp_backup'  => 'TEXT NULL AFTER totp_letzter',
    ] as $spalte => $def) {
        if (!ovb_column_exists($pdo, 'users', $spalte)) {
            $pdo->exec("ALTER TABLE users ADD COLUMN $spalte $def");
        }
    }
    $merken('040_totp');

    /* ---------- 041: Stand einer Buchung (bezahlt, offen, geplant) ---------- */
    if (ovb_table_exists($pdo, 'expenses')) {
        if (!ovb_column_exists($pdo, 'expenses', 'status')) {
            $pdo->exec("ALTER TABLE expenses ADD COLUMN status ENUM('bezahlt','offen','geplant') NOT NULL DEFAULT 'bezahlt' AFTER bezahlt_am");
        }
        if (!ovb_index_exists($pdo, 'expenses', 'idx_exp_status')) {
            $pdo->exec('ALTER TABLE expenses ADD KEY idx_exp_status (jahr, status)');
        }
    }
    $merken('041_buchung_status');

    /* ---------- 042: Weg einer Einnahme (abgerechnet, gestellt, zugesagt) ---------- */
    if (ovb_table_exists($pdo, 'expenses')) {
        $pdo->exec("ALTER TABLE expenses MODIFY status ENUM('bezahlt','offen','geplant','abgerechnet','gestellt','zugesagt') NOT NULL DEFAULT 'bezahlt'");
        foreach ([
            'abgerechnet_am'     => 'DATE NULL AFTER status',
            'abgerechnet_betrag' => 'DECIMAL(12,2) NULL AFTER abgerechnet_am',
            'gestellt_am'        => 'DATE NULL AFTER abgerechnet_betrag',
            'gestellt_betrag'    => 'DECIMAL(12,2) NULL AFTER gestellt_am',
            'gestellt_nr'        => "VARCHAR(100) NOT NULL DEFAULT '' AFTER gestellt_betrag",
            'zugesagt_am'        => 'DATE NULL AFTER gestellt_nr',
            'zugesagt_betrag'    => 'DECIMAL(12,2) NULL AFTER zugesagt_am',
        ] as $spalte => $def) {
            if (!ovb_column_exists($pdo, 'expenses', $spalte)) {
                $pdo->exec("ALTER TABLE expenses ADD COLUMN $spalte $def");
            }
        }
        // Bisher „offen" bei Einnahmen hiess: Rechnung gestellt
        $pdo->exec("UPDATE expenses SET status = 'gestellt', gestellt_am = COALESCE(gestellt_am, datum)
                    WHERE art = 'einnahme' AND status = 'offen'");
    }
    $merken('042_einnahme_stufen');

    /* ---------- 043: Stichtag des Jahresbudgets ---------- */
    if (ovb_table_exists($pdo, 'budget_years') && !ovb_column_exists($pdo, 'budget_years', 'stichtag')) {
        $pdo->exec('ALTER TABLE budget_years ADD COLUMN stichtag DATE NULL AFTER is_active');
    }
    $merken('043_budget_stichtag');

    /* ---------- 044: Haushaltsjahr automatisch (0 = Kalenderjahr) ---------- */
    if (ovb_table_exists($pdo, 'settings')) {
        $pdo->exec("UPDATE settings SET svalue = '0' WHERE skey = 'haushaltsjahr' AND svalue = '2026'");
    }
    $merken('044_haushaltsjahr_automatisch');

    /* ---------- 045: Kalender ---------- */
    if (!ovb_table_exists($pdo, 'calendar_entries')) {
        $pdo->exec("CREATE TABLE calendar_entries (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            titel VARCHAR(200) NOT NULL,
            beschreibung TEXT NULL,
            ort VARCHAR(150) NOT NULL DEFAULT '',
            beginn DATETIME NOT NULL,
            ende DATETIME NULL,
            ganztag TINYINT(1) NOT NULL DEFAULT 0,
            ziel ENUM('ov','fachgruppe','funktion','user') NOT NULL DEFAULT 'user',
            ziel_id INT UNSIGNED NULL,
            farbe VARCHAR(7) NOT NULL DEFAULT '',
            created_by INT UNSIGNED NULL,
            updated_by INT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_cal_beginn (beginn),
            KEY idx_cal_ziel (ziel, ziel_id),
            CONSTRAINT fk_cal_cb FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
            CONSTRAINT fk_cal_ub FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }
    if (!ovb_table_exists($pdo, 'calendar_ha')) {
        $pdo->exec("CREATE TABLE calendar_ha (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            entity_id VARCHAR(120) NOT NULL,
            kalender_name VARCHAR(100) NOT NULL DEFAULT '',
            uid VARCHAR(190) NOT NULL DEFAULT '',
            titel VARCHAR(200) NOT NULL,
            beschreibung TEXT NULL,
            ort VARCHAR(150) NOT NULL DEFAULT '',
            beginn DATETIME NOT NULL,
            ende DATETIME NULL,
            ganztag TINYINT(1) NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            KEY idx_calha_beginn (beginn),
            KEY idx_calha_entity (entity_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }
    $merken('045_kalender');

    /* ---------- 046: Bestellungen und Buchungen mit mehreren Bezuegen ---------- */
    if (!ovb_table_exists($pdo, 'bestellungen')) {
        $pdo->exec("CREATE TABLE bestellungen (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            nummer VARCHAR(30) NOT NULL,
            lieferant VARCHAR(150) NOT NULL DEFAULT '',
            bestellt_am DATE NOT NULL,
            bestell_nr VARCHAR(100) NOT NULL DEFAULT '',
            status ENUM('bestellt','geliefert','abgerechnet','storniert') NOT NULL DEFAULT 'bestellt',
            notiz TEXT NULL,
            created_by INT UNSIGNED NULL,
            updated_by INT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_bestellung_nummer (nummer),
            KEY idx_bestellung_status (status, bestellt_am),
            CONSTRAINT fk_best_cb FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
            CONSTRAINT fk_best_ub FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }
    if (!ovb_table_exists($pdo, 'bestellung_wuensche')) {
        $pdo->exec("CREATE TABLE bestellung_wuensche (
            bestellung_id INT UNSIGNED NOT NULL,
            wish_id INT UNSIGNED NOT NULL,
            PRIMARY KEY (bestellung_id, wish_id),
            KEY idx_bw_wish (wish_id),
            CONSTRAINT fk_bw_best FOREIGN KEY (bestellung_id) REFERENCES bestellungen(id) ON DELETE CASCADE,
            CONSTRAINT fk_bw_wish FOREIGN KEY (wish_id) REFERENCES wishes(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }
    if (!ovb_table_exists($pdo, 'expense_links')) {
        $pdo->exec("CREATE TABLE expense_links (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            expense_id INT UNSIGNED NOT NULL,
            typ ENUM('wish','vehicle') NOT NULL,
            ziel_id INT UNSIGNED NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_link (expense_id, typ, ziel_id),
            KEY idx_link_ziel (typ, ziel_id),
            CONSTRAINT fk_link_exp FOREIGN KEY (expense_id) REFERENCES expenses(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        // Bisherige Einzelbezuege uebernehmen
        $pdo->exec("INSERT IGNORE INTO expense_links (expense_id, typ, ziel_id)
                    SELECT id, 'wish', wish_id FROM expenses WHERE wish_id IS NOT NULL");
    }
    if (ovb_table_exists($pdo, 'expenses') && !ovb_column_exists($pdo, 'expenses', 'bestellung_id')) {
        $pdo->exec('ALTER TABLE expenses ADD COLUMN bestellung_id INT UNSIGNED NULL AFTER event_id');
    }
    if (ovb_table_exists($pdo, 'expenses') && !ovb_constraint_exists($pdo, 'expenses', 'fk_exp_best')) {
        $pdo->exec('ALTER TABLE expenses ADD CONSTRAINT fk_exp_best FOREIGN KEY (bestellung_id) REFERENCES bestellungen(id) ON DELETE SET NULL');
    }
    $merken('046_bestellungen');

    /* ---------- 047: Bezuege der Tagesordnungspunkte ---------- */
    if (!ovb_table_exists($pdo, 'tp_links')) {
        $pdo->exec("CREATE TABLE tp_links (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            tp_id INT UNSIGNED NOT NULL,
            typ ENUM('termin','vehicle','radio') NOT NULL,
            ziel_id INT UNSIGNED NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_tp_link (tp_id, typ, ziel_id),
            KEY idx_tp_link_ziel (typ, ziel_id),
            CONSTRAINT fk_tpl_tp FOREIGN KEY (tp_id) REFERENCES talking_points(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }
    $merken('047_tp_links');

    /* ---------- 048/049: Bezug zu Veranstaltungen und Budget (ziel_id = Haushaltsjahr) ---------- */
    // Beide Schritte setzen die Werteliste in einem Rutsch – robust gegen Zeilen mit unbekanntem Wert,
    // an denen ein MODIFY im strengen Modus scheiterte (Neustart-Schleife in 2.1.4/2.1.5).
    if (ovb_table_exists($pdo, 'tp_links')) {
        $weg = ovb_enum_setzen($pdo, 'tp_links', 'typ', ['termin', 'event', 'vehicle', 'radio', 'budget']);
        if ($weg > 0) {
            $say(sprintf('tp_links: %d Bezug/Bezüge mit unbekanntem Typ entfernt.', $weg));
        }
    }
    $merken('048_tp_links_event');
    $merken('049_tp_links_budget');

    /* ---------- 050: Stell- und Lagerplaetze ---------- */
    if (!ovb_table_exists($pdo, 'standorte')) {
        $pdo->exec("CREATE TABLE standorte (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            parent_id INT UNSIGNED NULL,
            typ ENUM('gebaeude','halle','hof','stockwerk','raum','stellplatz','hofstellplatz','schrank','regal') NOT NULL DEFAULT 'raum',
            name VARCHAR(120) NOT NULL,
            kurz VARCHAR(30) NOT NULL DEFAULT '',
            notiz TEXT NULL,
            sort_order INT NOT NULL DEFAULT 0,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_by INT UNSIGNED NULL,
            updated_by INT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_standort_parent (parent_id, sort_order),
            CONSTRAINT fk_standort_parent FOREIGN KEY (parent_id) REFERENCES standorte(id) ON DELETE SET NULL,
            CONSTRAINT fk_standort_cb FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
            CONSTRAINT fk_standort_ub FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }
    $merken('050_standorte');

    /* ---------- 051: Lage und Bilder der Stell- und Lagerplaetze ---------- */
    if (ovb_table_exists($pdo, 'standorte')) {
        foreach ([
            'geo_lat'         => 'DECIMAL(9,6) NULL AFTER notiz',
            'geo_lng'         => 'DECIMAL(9,6) NULL AFTER geo_lat',
            'geo_genauigkeit' => 'DECIMAL(8,1) NULL AFTER geo_lng',
            'geo_quelle'      => "ENUM('geraet','mensch') NULL AFTER geo_genauigkeit",
            'geo_at'          => 'DATETIME NULL AFTER geo_quelle',
        ] as $spalte => $def) {
            if (!ovb_column_exists($pdo, 'standorte', $spalte)) {
                $pdo->exec("ALTER TABLE standorte ADD COLUMN $spalte $def");
            }
        }
    }
    if (!ovb_table_exists($pdo, 'standort_bilder')) {
        $pdo->exec("CREATE TABLE standort_bilder (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            standort_id INT UNSIGNED NOT NULL,
            titel VARCHAR(200) NOT NULL DEFAULT '',
            orig_name VARCHAR(255) NOT NULL DEFAULT '',
            stored_name VARCHAR(120) NOT NULL,
            thumb_name VARCHAR(120) NULL,
            mime VARCHAR(100) NOT NULL DEFAULT '',
            size_bytes INT UNSIGNED NOT NULL DEFAULT 0,
            is_cover TINYINT(1) NOT NULL DEFAULT 0,
            uploaded_by INT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_sb_standort (standort_id, is_cover),
            CONSTRAINT fk_sb_standort FOREIGN KEY (standort_id) REFERENCES standorte(id) ON DELETE CASCADE,
            CONSTRAINT fk_sb_user FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }
    $merken('051_standort_lage_bilder');

    /* ---------- 052: Standard-Stellplatz je Fahrzeug ---------- */
    if (ovb_table_exists($pdo, 'vehicles') && ovb_table_exists($pdo, 'standorte')) {
        if (!ovb_column_exists($pdo, 'vehicles', 'standort_id')) {
            $pdo->exec('ALTER TABLE vehicles ADD COLUMN standort_id INT UNSIGNED NULL AFTER standort');
        }
        if (!ovb_constraint_exists($pdo, 'vehicles', 'fk_fz_standort')) {
            $pdo->exec('ALTER TABLE vehicles ADD CONSTRAINT fk_fz_standort FOREIGN KEY (standort_id) REFERENCES standorte(id) ON DELETE SET NULL');
        }
    }
    $merken('052_fahrzeug_stellplatz');

    /* ---------- 053: Zaehler am Platz ---------- */
    if (ovb_table_exists($pdo, 'meters') && ovb_table_exists($pdo, 'standorte')) {
        if (!ovb_column_exists($pdo, 'meters', 'standort_id')) {
            $pdo->exec('ALTER TABLE meters ADD COLUMN standort_id INT UNSIGNED NULL AFTER standort');
        }
        if (!ovb_constraint_exists($pdo, 'meters', 'fk_meter_standort')) {
            $pdo->exec('ALTER TABLE meters ADD CONSTRAINT fk_meter_standort FOREIGN KEY (standort_id) REFERENCES standorte(id) ON DELETE SET NULL');
        }
    }
    $merken('053_zaehler_standort');

    /* ---------- 054: Zaehler misst einen Bereich ---------- */
    if (ovb_table_exists($pdo, 'meters') && ovb_table_exists($pdo, 'standorte')) {
        if (!ovb_column_exists($pdo, 'meters', 'bereich_id')) {
            $pdo->exec('ALTER TABLE meters ADD COLUMN bereich_id INT UNSIGNED NULL AFTER standort_id');
            // Was bisher „Platz" war, war meist der versorgte Bereich – uebernehmen, der Zaehlerplatz wird neu gesetzt
            $pdo->exec('UPDATE meters SET bereich_id = standort_id WHERE bereich_id IS NULL AND standort_id IS NOT NULL');
        }
        if (!ovb_constraint_exists($pdo, 'meters', 'fk_meter_bereich')) {
            $pdo->exec('ALTER TABLE meters ADD CONSTRAINT fk_meter_bereich FOREIGN KEY (bereich_id) REFERENCES standorte(id) ON DELETE SET NULL');
        }
    }
    $merken('054_zaehler_bereich');

    /* ---------- 055: Funkgeraete und Gruppen am Platz ---------- */
    if (ovb_table_exists($pdo, 'standorte')) {
        if (ovb_table_exists($pdo, 'radio_groups')) {
            if (!ovb_column_exists($pdo, 'radio_groups', 'standort_id')) {
                $pdo->exec('ALTER TABLE radio_groups ADD COLUMN standort_id INT UNSIGNED NULL AFTER lagerort');
            }
            if (!ovb_constraint_exists($pdo, 'radio_groups', 'fk_gruppe_standort')) {
                $pdo->exec('ALTER TABLE radio_groups ADD CONSTRAINT fk_gruppe_standort FOREIGN KEY (standort_id) REFERENCES standorte(id) ON DELETE SET NULL');
            }
        }
        if (ovb_table_exists($pdo, 'radios')) {
            if (!ovb_column_exists($pdo, 'radios', 'standort_id')) {
                $pdo->exec('ALTER TABLE radios ADD COLUMN standort_id INT UNSIGNED NULL AFTER standort');
            }
            if (!ovb_constraint_exists($pdo, 'radios', 'fk_funk_standort')) {
                $pdo->exec('ALTER TABLE radios ADD CONSTRAINT fk_funk_standort FOREIGN KEY (standort_id) REFERENCES standorte(id) ON DELETE SET NULL');
            }
        }
    }
    $merken('055_funk_standort');

    /* ---------- 056: Hallenplan ---------- */
    if (ovb_table_exists($pdo, 'standorte')) {
        if (!ovb_column_exists($pdo, 'standorte', 'plan_bild_id')) {
            $pdo->exec('ALTER TABLE standorte ADD COLUMN plan_bild_id INT UNSIGNED NULL AFTER sort_order');
        }
        if (!ovb_column_exists($pdo, 'standorte', 'plan_x')) {
            $pdo->exec('ALTER TABLE standorte ADD COLUMN plan_x DECIMAL(5,2) NULL AFTER plan_bild_id');
            $pdo->exec('ALTER TABLE standorte ADD COLUMN plan_y DECIMAL(5,2) NULL AFTER plan_x');
        }
        if (ovb_table_exists($pdo, 'standort_bilder') && !ovb_constraint_exists($pdo, 'standorte', 'fk_standort_plan')) {
            $pdo->exec('ALTER TABLE standorte ADD CONSTRAINT fk_standort_plan FOREIGN KEY (plan_bild_id) REFERENCES standort_bilder(id) ON DELETE SET NULL');
        }
    }
    $merken('056_hallenplan');

    /* ---------- 057: Objektkonten ---------- */
    if (!ovb_table_exists($pdo, 'objektkonten')) {
        $pdo->exec("CREATE TABLE objektkonten (
            id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
            nummer        VARCHAR(20)  NOT NULL DEFAULT '',
            name          VARCHAR(150) NOT NULL,
            beschreibung  VARCHAR(255) NOT NULL DEFAULT '',
            color         VARCHAR(20)  NOT NULL DEFAULT '#64748b',
            sort_order    INT          NOT NULL DEFAULT 0,
            is_active     TINYINT(1)   NOT NULL DEFAULT 1,
            created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_objektkonto_sort (sort_order, nummer)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }
    if (ovb_table_exists($pdo, 'list_items') && !ovb_column_exists($pdo, 'list_items', 'objektkonto_id')) {
        $pdo->exec('ALTER TABLE list_items ADD COLUMN objektkonto_id INT UNSIGNED NULL AFTER is_final');
    }
    if (ovb_table_exists($pdo, 'list_items') && !ovb_constraint_exists($pdo, 'list_items', 'fk_list_objektkonto')) {
        $pdo->exec('ALTER TABLE list_items ADD CONSTRAINT fk_list_objektkonto FOREIGN KEY (objektkonto_id) REFERENCES objektkonten(id) ON DELETE SET NULL');
    }
    $merken('057_objektkonten');

    /* ---------- 058: Auslagen von Helfern ---------- */
    if (ovb_table_exists($pdo, 'expenses')) {
        if (!ovb_column_exists($pdo, 'expenses', 'ausgelegt_von')) {
            $pdo->exec('ALTER TABLE expenses ADD COLUMN ausgelegt_von INT UNSIGNED NULL AFTER bezahlt_am');
            $pdo->exec("ALTER TABLE expenses ADD COLUMN ausgelegt_name VARCHAR(100) NOT NULL DEFAULT '' AFTER ausgelegt_von");
            $pdo->exec('ALTER TABLE expenses ADD COLUMN erstattet_am DATE NULL AFTER ausgelegt_name');
        }
        if (!ovb_constraint_exists($pdo, 'expenses', 'fk_exp_auslage')) {
            $pdo->exec('ALTER TABLE expenses ADD CONSTRAINT fk_exp_auslage FOREIGN KEY (ausgelegt_von) REFERENCES users(id) ON DELETE SET NULL');
        }
    }
    $merken('058_auslagen');

    /* ---------- 059: Auslage in Stufen (an die Regionalstelle weitergegeben) ---------- */
    if (ovb_table_exists($pdo, 'expenses') && !ovb_column_exists($pdo, 'expenses', 'weitergegeben_am')) {
        $pdo->exec('ALTER TABLE expenses ADD COLUMN weitergegeben_am DATE NULL AFTER ausgelegt_name');
    }
    $merken('059_auslage_stufen');

    /* ---------- 060: Termine mit Fahrzeugen und Platz ---------- */
    if (ovb_table_exists($pdo, 'calendar_entries')) {
        if (!ovb_column_exists($pdo, 'calendar_entries', 'standort_id')) {
            $pdo->exec('ALTER TABLE calendar_entries ADD COLUMN standort_id INT UNSIGNED NULL AFTER ort');
        }
        if (ovb_table_exists($pdo, 'standorte') && !ovb_constraint_exists($pdo, 'calendar_entries', 'fk_cal_standort')) {
            $pdo->exec('ALTER TABLE calendar_entries ADD CONSTRAINT fk_cal_standort FOREIGN KEY (standort_id) REFERENCES standorte(id) ON DELETE SET NULL');
        }
        if (ovb_table_exists($pdo, 'vehicles') && !ovb_table_exists($pdo, 'calendar_vehicles')) {
            $pdo->exec('CREATE TABLE calendar_vehicles (
                entry_id    INT UNSIGNED NOT NULL,
                vehicle_id  INT UNSIGNED NOT NULL,
                PRIMARY KEY (entry_id, vehicle_id),
                KEY idx_cv_vehicle (vehicle_id),
                CONSTRAINT fk_cv_entry   FOREIGN KEY (entry_id)   REFERENCES calendar_entries(id) ON DELETE CASCADE,
                CONSTRAINT fk_cv_vehicle FOREIGN KEY (vehicle_id) REFERENCES vehicles(id)         ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        }
    }
    $merken('060_termin_fahrzeuge');

    /* ---------- 061: mehrere Standorte je Termin und Veranstaltung ---------- */
    if (ovb_table_exists($pdo, 'standorte')) {
        if (ovb_table_exists($pdo, 'calendar_entries') && !ovb_table_exists($pdo, 'calendar_standorte')) {
            $pdo->exec("CREATE TABLE calendar_standorte (
                entry_id    INT UNSIGNED NOT NULL,
                standort_id INT UNSIGNED NOT NULL,
                PRIMARY KEY (entry_id, standort_id),
                KEY idx_cs_standort (standort_id),
                CONSTRAINT fk_cs_ziel     FOREIGN KEY (entry_id)    REFERENCES calendar_entries(id) ON DELETE CASCADE,
                CONSTRAINT fk_cs_standort FOREIGN KEY (standort_id) REFERENCES standorte(id)        ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            // Den bisherigen einzelnen Platz (2.1.22.0) mitnehmen
            if (ovb_column_exists($pdo, 'calendar_entries', 'standort_id')) {
                $pdo->exec('INSERT IGNORE INTO calendar_standorte (entry_id, standort_id)
                            SELECT id, standort_id FROM calendar_entries WHERE standort_id IS NOT NULL');
            }
        }
        if (ovb_constraint_exists($pdo, 'calendar_entries', 'fk_cal_standort')) {
            $pdo->exec('ALTER TABLE calendar_entries DROP FOREIGN KEY fk_cal_standort');
        }
        if (ovb_column_exists($pdo, 'calendar_entries', 'standort_id')) {
            $pdo->exec('ALTER TABLE calendar_entries DROP COLUMN standort_id');
        }
        if (ovb_table_exists($pdo, 'events') && !ovb_table_exists($pdo, 'event_standorte')) {
            $pdo->exec("CREATE TABLE event_standorte (
                event_id    INT UNSIGNED NOT NULL,
                standort_id INT UNSIGNED NOT NULL,
                PRIMARY KEY (event_id, standort_id),
                KEY idx_es_standort (standort_id),
                CONSTRAINT fk_es_ziel     FOREIGN KEY (event_id)    REFERENCES events(id)    ON DELETE CASCADE,
                CONSTRAINT fk_es_standort FOREIGN KEY (standort_id) REFERENCES standorte(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        }
    }
    $merken('061_mehrere_standorte');

    /* ---------- 062: mehrere Fachgruppen je Termin ---------- */
    if (ovb_table_exists($pdo, 'calendar_entries') && !ovb_table_exists($pdo, 'calendar_fachgruppen')) {
        $pdo->exec("CREATE TABLE calendar_fachgruppen (
            entry_id      INT UNSIGNED NOT NULL,
            fachgruppe_id INT UNSIGNED NOT NULL,
            PRIMARY KEY (entry_id, fachgruppe_id),
            KEY idx_cf_fachgruppe (fachgruppe_id),
            CONSTRAINT fk_cf_ziel       FOREIGN KEY (entry_id)      REFERENCES calendar_entries(id) ON DELETE CASCADE,
            CONSTRAINT fk_cf_fachgruppe FOREIGN KEY (fachgruppe_id) REFERENCES list_items(id)       ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }
    $merken('062_termin_fachgruppen');

    /* ---------- 063: Dateien an Terminen ---------- */
    if (ovb_table_exists($pdo, 'calendar_entries') && !ovb_table_exists($pdo, 'calendar_files')) {
        $pdo->exec("CREATE TABLE calendar_files (
            id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
            entry_id     INT UNSIGNED NOT NULL,
            art          VARCHAR(20)  NOT NULL DEFAULT 'dokument',
            titel        VARCHAR(200) NOT NULL DEFAULT '',
            orig_name    VARCHAR(255) NOT NULL DEFAULT '',
            stored_name  VARCHAR(255) NOT NULL,
            thumb_name   VARCHAR(255) NULL,
            mime         VARCHAR(120) NOT NULL DEFAULT '',
            size_bytes   INT UNSIGNED NOT NULL DEFAULT 0,
            uploaded_by  INT UNSIGNED NULL,
            created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_cfile_entry (entry_id),
            CONSTRAINT fk_cfile_entry FOREIGN KEY (entry_id)    REFERENCES calendar_entries(id) ON DELETE CASCADE,
            CONSTRAINT fk_cfile_ub    FOREIGN KEY (uploaded_by) REFERENCES users(id)            ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }
    $merken('063_termin_dateien');

    /* ---------- 064: Werkstatttermine (Termin <-> Instandsetzungsauftrag) ---------- */
    if (ovb_table_exists($pdo, 'calendar_entries') && ovb_table_exists($pdo, 'vehicle_orders') && !ovb_table_exists($pdo, 'calendar_auftraege')) {
        $pdo->exec('CREATE TABLE calendar_auftraege (
            entry_id  INT UNSIGNED NOT NULL,
            order_id  INT UNSIGNED NOT NULL,
            PRIMARY KEY (entry_id, order_id),
            KEY idx_ca_order (order_id),
            CONSTRAINT fk_ca_entry FOREIGN KEY (entry_id) REFERENCES calendar_entries(id) ON DELETE CASCADE,
            CONSTRAINT fk_ca_order FOREIGN KEY (order_id) REFERENCES vehicle_orders(id)   ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    }
    $merken('064_werkstatttermine');

    /* ---------- 065: Kontakte am Termin ---------- */
    if (ovb_table_exists($pdo, 'calendar_entries') && ovb_table_exists($pdo, 'contacts') && !ovb_table_exists($pdo, 'calendar_kontakte')) {
        $pdo->exec('CREATE TABLE calendar_kontakte (
            entry_id    INT UNSIGNED NOT NULL,
            contact_id  INT UNSIGNED NOT NULL,
            PRIMARY KEY (entry_id, contact_id),
            KEY idx_ck_contact (contact_id),
            CONSTRAINT fk_ck_entry   FOREIGN KEY (entry_id)   REFERENCES calendar_entries(id) ON DELETE CASCADE,
            CONSTRAINT fk_ck_contact FOREIGN KEY (contact_id) REFERENCES contacts(id)         ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    }
    $merken('065_termin_kontakte');

    /* ---------- 066: Transportkapazitaet der Fahrzeuge ---------- */
    if (ovb_table_exists($pdo, 'vehicles') && !ovb_column_exists($pdo, 'vehicles', 'sitzplaetze')) {
        $pdo->exec('ALTER TABLE vehicles ADD COLUMN sitzplaetze SMALLINT UNSIGNED NULL AFTER betriebsstunden');
        $pdo->exec('ALTER TABLE vehicles ADD COLUMN zuladung_kg INT UNSIGNED NULL AFTER sitzplaetze');
    }
    $merken('066_kapazitaet');

    /* ---------- 067: THW-Kontakte (Dienststellen, Funktionspostfaecher) ---------- */
    if (!ovb_table_exists($pdo, 'thw_dienststellen')) {
        $pdo->exec("CREATE TABLE thw_dienststellen (
            id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
            parent_id   INT UNSIGNED NULL,
            typ         ENUM('leitung','landesverband','regionalstelle','ortsverband','sonstige') NOT NULL DEFAULT 'ortsverband',
            name        VARCHAR(150) NOT NULL,
            kurz        VARCHAR(30)  NOT NULL DEFAULT '',
            strasse     VARCHAR(150) NOT NULL DEFAULT '',
            plz         VARCHAR(15)  NOT NULL DEFAULT '',
            ort         VARCHAR(100) NOT NULL DEFAULT '',
            telefon     VARCHAR(60)  NOT NULL DEFAULT '',
            email       VARCHAR(150) NOT NULL DEFAULT '',
            web         VARCHAR(200) NOT NULL DEFAULT '',
            notiz       TEXT         NULL,
            sort_order  INT          NOT NULL DEFAULT 0,
            is_active   TINYINT(1)   NOT NULL DEFAULT 1,
            created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_thw_parent (parent_id, sort_order),
            CONSTRAINT fk_thw_parent FOREIGN KEY (parent_id) REFERENCES thw_dienststellen(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }
    if (!ovb_table_exists($pdo, 'thw_postfaecher')) {
        $pdo->exec("CREATE TABLE thw_postfaecher (
            id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
            dienststelle_id  INT UNSIGNED NULL,
            name             VARCHAR(150) NOT NULL,
            email            VARCHAR(150) NOT NULL,
            beschreibung     VARCHAR(500) NOT NULL DEFAULT '',
            offiziell        TINYINT(1)   NOT NULL DEFAULT 1,
            behoerdennetz    TINYINT(1)   NOT NULL DEFAULT 0,
            notiz            TEXT         NULL,
            sort_order       INT          NOT NULL DEFAULT 0,
            is_active        TINYINT(1)   NOT NULL DEFAULT 1,
            created_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_thw_pf_dst (dienststelle_id, sort_order),
            CONSTRAINT fk_thw_pf_dst FOREIGN KEY (dienststelle_id) REFERENCES thw_dienststellen(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }
    $merken('067_thw_kontakte');

    /* ---------- 068: Termine mit THW-Dienststellen ---------- */
    if (ovb_table_exists($pdo, 'calendar_entries') && ovb_table_exists($pdo, 'thw_dienststellen') && !ovb_table_exists($pdo, 'calendar_dienststellen')) {
        $pdo->exec('CREATE TABLE calendar_dienststellen (
            entry_id         INT UNSIGNED NOT NULL,
            dienststelle_id  INT UNSIGNED NOT NULL,
            PRIMARY KEY (entry_id, dienststelle_id),
            KEY idx_cd_dst (dienststelle_id),
            CONSTRAINT fk_cd_entry FOREIGN KEY (entry_id)        REFERENCES calendar_entries(id)  ON DELETE CASCADE,
            CONSTRAINT fk_cd_dst   FOREIGN KEY (dienststelle_id) REFERENCES thw_dienststellen(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    }
    $merken('068_termin_dienststellen');

    /* ---------- 069: Kontakte an THW-Dienststellen ---------- */
    if (ovb_table_exists($pdo, 'thw_dienststellen') && ovb_table_exists($pdo, 'contacts') && !ovb_table_exists($pdo, 'thw_dienststelle_kontakte')) {
        $pdo->exec('CREATE TABLE thw_dienststelle_kontakte (
            dienststelle_id  INT UNSIGNED NOT NULL,
            contact_id       INT UNSIGNED NOT NULL,
            PRIMARY KEY (dienststelle_id, contact_id),
            KEY idx_tdk_contact (contact_id),
            CONSTRAINT fk_tdk_dst     FOREIGN KEY (dienststelle_id) REFERENCES thw_dienststellen(id) ON DELETE CASCADE,
            CONSTRAINT fk_tdk_contact FOREIGN KEY (contact_id)      REFERENCES contacts(id)          ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    }
    $merken('069_thw_kontakte_link');

    /* ---------- 070: Traegerfahrzeug (Boot auf Anhaenger) ---------- */
    if (ovb_table_exists($pdo, 'vehicles')) {
        if (!ovb_column_exists($pdo, 'vehicles', 'traeger_id')) {
            $pdo->exec('ALTER TABLE vehicles ADD COLUMN traeger_id INT UNSIGNED NULL AFTER zuladung_kg');
        }
        if (!ovb_constraint_exists($pdo, 'vehicles', 'fk_fz_traeger')) {
            $pdo->exec('ALTER TABLE vehicles ADD CONSTRAINT fk_fz_traeger FOREIGN KEY (traeger_id) REFERENCES vehicles(id) ON DELETE SET NULL');
        }
    }
    $merken('070_traeger');

    /* ---------- 071: KI-Auswertung der Dateien (Ollama) ---------- */
    if (!ovb_table_exists($pdo, 'ki_auftraege')) {
        $pdo->exec("CREATE TABLE ki_auftraege (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  typ          VARCHAR(20)  NOT NULL,
  datei_id     INT UNSIGNED NOT NULL,
  status       ENUM('offen','laeuft','fertig','fehler') NOT NULL DEFAULT 'offen',
  versuche     TINYINT UNSIGNED NOT NULL DEFAULT 0,
  fehler       VARCHAR(500) NOT NULL DEFAULT '',
  erstellt_am  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  gestartet_am DATETIME     NULL,
  erledigt_am  DATETIME     NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_ki_auftrag (typ, datei_id),
  KEY idx_ki_status (status, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    }
    if (!ovb_table_exists($pdo, 'datei_ki')) {
        $pdo->exec("CREATE TABLE datei_ki (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  typ             VARCHAR(20)  NOT NULL,
  datei_id        INT UNSIGNED NOT NULL,
  zusammenfassung TEXT         NOT NULL,
  tags            VARCHAR(500) NOT NULL DEFAULT '',
  modell          VARCHAR(100) NOT NULL DEFAULT '',
  quelle          VARCHAR(20)  NOT NULL DEFAULT 'text',
  erstellt_am     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  geaendert_am    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_datei_ki (typ, datei_id),
  KEY idx_datei_ki_tags (tags(191))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    }
    $merken('071_ki');

    /* ---------- 072: Entfernungen (!! TEST !!) ---------- */
    if (!ovb_table_exists($pdo, 'geo_adressen')) {
        $pdo->exec("CREATE TABLE geo_adressen (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  adresse      VARCHAR(255) NOT NULL,
  text         VARCHAR(255) NOT NULL DEFAULT '',
  lat          DECIMAL(9,6) NULL,
  lng          DECIMAL(9,6) NULL,
  status       ENUM('offen','fertig','fehler') NOT NULL DEFAULT 'offen',
  versuche     TINYINT UNSIGNED NOT NULL DEFAULT 0,
  fehler       VARCHAR(255) NOT NULL DEFAULT '',
  quelle       VARCHAR(40)  NOT NULL DEFAULT '',
  treffer      VARCHAR(255) NOT NULL DEFAULT '',
  erstellt_am  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  erledigt_am  DATETIME     NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_geo_adresse (adresse),
  KEY idx_geo_adr_status (status, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    }
    if (!ovb_table_exists($pdo, 'geo_routen')) {
        $pdo->exec("CREATE TABLE geo_routen (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  von_lat      DECIMAL(9,6) NOT NULL,
  von_lng      DECIMAL(9,6) NOT NULL,
  nach_lat     DECIMAL(9,6) NOT NULL,
  nach_lng     DECIMAL(9,6) NOT NULL,
  km           DECIMAL(8,1) NULL,
  minuten      INT UNSIGNED NULL,
  status       ENUM('offen','fertig','fehler') NOT NULL DEFAULT 'offen',
  versuche     TINYINT UNSIGNED NOT NULL DEFAULT 0,
  fehler       VARCHAR(255) NOT NULL DEFAULT '',
  quelle       VARCHAR(40)  NOT NULL DEFAULT '',
  erstellt_am  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  erledigt_am  DATETIME     NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_geo_route (von_lat, von_lng, nach_lat, nach_lng),
  KEY idx_geo_route_status (status, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    }
    $merken('072_geo');

    /* ---------- 073: Fahrzeuge je Veranstaltung ---------- */
    if (!ovb_table_exists($pdo, 'event_vehicles') && ovb_table_exists($pdo, 'events') && ovb_table_exists($pdo, 'vehicles')) {
        $pdo->exec("CREATE TABLE event_vehicles (
  event_id    INT UNSIGNED NOT NULL,
  vehicle_id  INT UNSIGNED NOT NULL,
  PRIMARY KEY (event_id, vehicle_id),
  KEY idx_ev_vehicle (vehicle_id),
  CONSTRAINT fk_ev_event   FOREIGN KEY (event_id)   REFERENCES events(id)   ON DELETE CASCADE,
  CONSTRAINT fk_ev_vehicle FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    }
    $merken('073_event_fahrzeuge');

    /* ---------- 074: Besprechungen über mehrere Tage, Fahrzeuge je Besprechung ---------- */
    if (ovb_table_exists($pdo, 'meetings') && !ovb_column_exists($pdo, 'meetings', 'ende_datum')) {
        $pdo->exec('ALTER TABLE meetings ADD COLUMN ende_datum DATE NULL AFTER ende');
    }
    if (!ovb_table_exists($pdo, 'meeting_vehicles') && ovb_table_exists($pdo, 'meetings') && ovb_table_exists($pdo, 'vehicles')) {
        $pdo->exec("CREATE TABLE meeting_vehicles (
  meeting_id  INT UNSIGNED NOT NULL,
  vehicle_id  INT UNSIGNED NOT NULL,
  PRIMARY KEY (meeting_id, vehicle_id),
  KEY idx_mv_vehicle (vehicle_id),
  CONSTRAINT fk_mv_meeting FOREIGN KEY (meeting_id) REFERENCES meetings(id) ON DELETE CASCADE,
  CONSTRAINT fk_mv_vehicle FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    }
    $merken('074_besprechung_tage_fahrzeuge');

    /* ---------- 075: Besprechung online, in der Unterkunft oder außer Haus ---------- */
    if (ovb_table_exists($pdo, 'meetings') && !ovb_column_exists($pdo, 'meetings', 'treffen')) {
        $pdo->exec("ALTER TABLE meetings ADD COLUMN treffen ENUM('inhaus','online','ausserhaus') NOT NULL DEFAULT 'inhaus' AFTER ort, ADD COLUMN link VARCHAR(500) NOT NULL DEFAULT '' AFTER treffen");
    }
    $merken('075_besprechung_treffen');

    /* ---------- 076: zentrale Rufnummern bei den Funktionspostfächern ---------- */
    if (ovb_table_exists($pdo, 'thw_postfaecher') && !ovb_column_exists($pdo, 'thw_postfaecher', 'telefon')) {
        $pdo->exec("ALTER TABLE thw_postfaecher MODIFY email VARCHAR(150) NOT NULL DEFAULT '', ADD COLUMN telefon VARCHAR(40) NOT NULL DEFAULT '' AFTER email");
    }
    $merken('076_postfach_telefon');

    /* ---------- 077: STAN als Dokumentenpaket in Versionen ---------- */
    if (!ovb_table_exists($pdo, 'stan_versionen')) {
        $pdo->exec("CREATE TABLE stan_versionen (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nummer       INT UNSIGNED NOT NULL,
  bemerkung    VARCHAR(200) NOT NULL DEFAULT '',
  zip_name     VARCHAR(255) NOT NULL DEFAULT '',
  orig_name    VARCHAR(255) NOT NULL DEFAULT '',
  zip_bytes    BIGINT UNSIGNED NOT NULL DEFAULT 0,
  uploaded_by  INT UNSIGNED NULL,
  created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_stan_nummer (nummer),
  CONSTRAINT fk_stan_ub FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    }
    if (!ovb_table_exists($pdo, 'stan_dateien')) {
        $pdo->exec("CREATE TABLE stan_dateien (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  version_id   INT UNSIGNED NOT NULL,
  pfad         VARCHAR(500) NOT NULL,
  name         VARCHAR(255) NOT NULL,
  stored_name  VARCHAR(255) NOT NULL,
  mime         VARCHAR(120) NOT NULL DEFAULT '',
  size_bytes   BIGINT UNSIGNED NOT NULL DEFAULT 0,
  created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_stan_version (version_id),
  CONSTRAINT fk_stan_version FOREIGN KEY (version_id) REFERENCES stan_versionen(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    }
    $merken('077_stan');

    /* ---------- 078: STAN durchsuchbar – Volltext je Datei ---------- */
    if (ovb_table_exists($pdo, 'stan_dateien') && !ovb_column_exists($pdo, 'stan_dateien', 'text')) {
        $pdo->exec('ALTER TABLE stan_dateien ADD COLUMN text MEDIUMTEXT NULL AFTER size_bytes');
    }
    $merken('078_stan_text');

    /* ---------- 079: Hochrechnung je Kategorie ---------- */
    if (!ovb_table_exists($pdo, 'budget_prognosen')) {
        $pdo->exec("CREATE TABLE budget_prognosen (
  id            INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  jahr          SMALLINT      NOT NULL,
  -- 0 = Buchungen ohne Kategorie; kein Fremdschluessel, damit die Einstellung eine geloeschte Kategorie ueberlebt
  kategorie_id  INT UNSIGNED  NOT NULL DEFAULT 0,
  art           ENUM('auto','fest','aus') NOT NULL DEFAULT 'auto',
  betrag_monat  DECIMAL(12,2) NOT NULL DEFAULT 0,
  notiz         VARCHAR(200)  NOT NULL DEFAULT '',
  PRIMARY KEY (id),
  UNIQUE KEY uq_prognose (jahr, kategorie_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
    }
    $merken('079_prognose');

    /* ---------- 080: wiederkehrende Rechnungen an der Ausgabe ---------- */
    if (ovb_table_exists($pdo, 'expenses') && !ovb_column_exists($pdo, 'expenses', 'wiederkehrend')) {
        $pdo->exec("ALTER TABLE expenses ADD COLUMN wiederkehrend ENUM('','monatlich','vierteljaehrlich','jaehrlich') NOT NULL DEFAULT '' AFTER referenz");
    }
    $merken('080_wiederkehrend');
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

/**
 * Eine ENUM-Spalte auf eine neue Werteliste setzen. Ein schlichtes MODIFY
 * scheitert im strengen SQL-Modus an jeder Zeile, deren Wert nicht in der
 * neuen Liste steht (Fehler 1265 „Data truncated"). Darum: Zeilen mit
 * unbekanntem Wert entfernen, dann ohne strengen Modus ändern.
 */
function ovb_enum_setzen(PDO $pdo, string $table, string $column, array $werte, string $rest = 'NOT NULL'): int
{
    $liste = implode(',', array_map(static fn($w) => $pdo->quote($w), $werte));
    $geloescht = $pdo->exec("DELETE FROM `$table` WHERE `$column` = '' OR `$column` NOT IN ($liste)");
    $modus = (string)$pdo->query('SELECT @@SESSION.sql_mode')->fetchColumn();
    $pdo->exec("SET SESSION sql_mode = ''");
    try {
        $pdo->exec("ALTER TABLE `$table` MODIFY `$column` ENUM($liste) $rest");
    } finally {
        $pdo->exec('SET SESSION sql_mode = ' . $pdo->quote($modus));
    }
    return (int)$geloescht;
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
