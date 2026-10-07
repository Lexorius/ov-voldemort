<?php
declare(strict_types=1);

/*
 * Stell- und Lagerplätze: Gebäude, Hallen und Höfe mit Stockwerken, Räumen,
 * Stellplätzen, Schränken und Regalen – als Baum. Gepflegt in der Verwaltung,
 * damit Fahrzeuge, Geräte und Material später einen eindeutigen Platz haben.
 */

/** Typen mit Beschriftung; 'oben' = darf ganz oben stehen, 'eltern' = worunter er sonst hängen darf */
const STANDORT_TYPEN = [
    'gebaeude'      => ['label' => 'Gebäude', 'mehrzahl' => 'Gebäude', 'icon' => '🏢', 'oben' => true, 'eltern' => []],
    'halle'         => ['label' => 'Halle', 'mehrzahl' => 'Hallen', 'icon' => '🏭', 'oben' => true, 'eltern' => ['gebaeude']],
    'hof'           => ['label' => 'Hof', 'mehrzahl' => 'Höfe', 'icon' => '▦', 'oben' => true, 'eltern' => []],
    'stockwerk'     => ['label' => 'Stockwerk', 'mehrzahl' => 'Stockwerke', 'icon' => '≡', 'eltern' => ['gebaeude', 'halle']],
    'raum'          => ['label' => 'Raum', 'mehrzahl' => 'Räume', 'icon' => '▢', 'eltern' => ['gebaeude', 'halle', 'stockwerk']],
    'stellplatz'    => ['label' => 'Fahrzeugstellplatz', 'mehrzahl' => 'Fahrzeugstellplätze', 'icon' => '🚒', 'eltern' => ['halle', 'gebaeude', 'stockwerk']],
    'hofstellplatz' => ['label' => 'Stellplatz auf dem Hof', 'mehrzahl' => 'Stellplätze auf dem Hof', 'icon' => '🅿', 'eltern' => ['hof']],
    'schrank'       => ['label' => 'Schrank', 'mehrzahl' => 'Schränke', 'icon' => '🗄', 'eltern' => ['raum', 'halle', 'stockwerk', 'gebaeude']],
    'regal'         => ['label' => 'Regal', 'mehrzahl' => 'Regale', 'icon' => '▤', 'eltern' => ['schrank', 'raum', 'halle']],
];
const STANDORT_MAX_SERIE = 200;

function standort_typ(string $typ): string
{
    return array_key_exists($typ, STANDORT_TYPEN) ? $typ : 'raum';
}

/** Darf ein Typ unter diesem Elterntyp hängen? Reine Funktion. */
function standort_passt(string $typ, ?string $elternTyp): bool
{
    $t = STANDORT_TYPEN[standort_typ($typ)];
    return $elternTyp === null ? !empty($t['oben']) : in_array($elternTyp, $t['eltern'], true);
}

/** Welche Typen unter diesem Elterntyp möglich sind. Reine Funktion. */
function standort_kindtypen(?string $elternTyp): array
{
    return array_keys(array_filter(STANDORT_TYPEN, static fn($t) => $elternTyp === null ? !empty($t['oben']) : in_array($elternTyp, $t['eltern'], true)));
}

function standort_all(bool $nurAktive = false): array
{
    return db_all('SELECT * FROM standorte' . ($nurAktive ? ' WHERE is_active = 1' : '') . ' ORDER BY parent_id, sort_order, name, id');
}

function standort_find(?int $id): ?array
{
    return $id > 0 ? db_row('SELECT * FROM standorte WHERE id = ?', [$id]) : null;
}

/**
 * Flache Liste in einen Baum: jede Zeile bekommt 'kinder' und 'tiefe'.
 * Waisen (Eltern gelöscht) hängen oben. Reine Funktion.
 */
function standort_baum(array $rows): array
{
    $nachEltern = [];
    $ids = array_column($rows, 'id');
    foreach ($rows as $r) {
        $p = (int)($r['parent_id'] ?? 0);
        $nachEltern[in_array($p, $ids, false) ? $p : 0][] = $r;
    }
    $bauen = static function (int $eltern, int $tiefe) use (&$bauen, $nachEltern): array {
        $out = [];
        foreach ($nachEltern[$eltern] ?? [] as $r) {
            $r['tiefe'] = $tiefe;
            $r['kinder'] = $bauen((int)$r['id'], $tiefe + 1);
            $out[] = $r;
        }
        return $out;
    };
    return $bauen(0, 0);
}

/** Baum als Liste in Lesereihenfolge (Tiefensuche). Reine Funktion. */
function standort_flach(array $baum): array
{
    $out = [];
    foreach ($baum as $k) {
        $kinder = $k['kinder'];
        unset($k['kinder']);
        $out[] = $k;
        foreach (standort_flach($kinder) as $u) {
            $out[] = $u;
        }
    }
    return $out;
}

/** „Haupthaus › 2. Stock › Raum 12". $rows: alle Standorte (id => Zeile). Reine Funktion. */
function standort_pfad(int $id, array $rows, string $trenner = ' › '): string
{
    $nachId = isset($rows[$id]) && !isset($rows[0]) ? $rows : array_column($rows, null, 'id');
    $teile = [];
    $schutz = 0;
    while ($id > 0 && isset($nachId[$id]) && $schutz++ < 50) {
        array_unshift($teile, (string)$nachId[$id]['name']);
        $id = (int)($nachId[$id]['parent_id'] ?? 0);
    }
    return implode($trenner, $teile);
}

/** Alle Nachkommen eines Knotens (ids), zum Verhindern von Schleifen. Reine Funktion. */
function standort_nachkommen(int $id, array $rows): array
{
    $out = [];
    foreach ($rows as $r) {
        if ((int)($r['parent_id'] ?? 0) === $id) {
            $out[] = (int)$r['id'];
            foreach (standort_nachkommen((int)$r['id'], $rows) as $u) {
                $out[] = $u;
            }
        }
    }
    return $out;
}

/** Optionen für eine Auswahl, eingerückt nach Tiefe. */
function standort_optionen(array $rows, ?int $selected, string $leer = '– keiner –', array $ausser = []): string
{
    $html = '<option value="">' . e($leer) . '</option>';
    foreach (standort_flach(standort_baum($rows)) as $s) {
        if (in_array((int)$s['id'], $ausser, true)) {
            continue;
        }
        $html .= '<option value="' . (int)$s['id'] . '"' . ((int)$s['id'] === (int)$selected ? ' selected' : '') . '>'
            . e(str_repeat('— ', (int)$s['tiefe']) . $s['name'] . ' (' . STANDORT_TYPEN[standort_typ((string)$s['typ'])]['label'] . ')') . '</option>';
    }
    return $html;
}

function standort_naechste_sort(?int $parentId): int
{
    return (int)db_val('SELECT COALESCE(MAX(sort_order),0) + 10 FROM standorte WHERE ' . ($parentId ? 'parent_id = ?' : 'parent_id IS NULL'), $parentId ? [$parentId] : [], 10);
}

/**
 * Standort aus dem Formular speichern – einzeln oder als Serie („Raum" 1–16).
 * Gibt [ids, fehler[]] zurück.
 */
function standort_save_from_post(?array $existing, array $user): array
{
    $errors = [];
    $name = trim(post_str('name'));
    $typ = standort_typ(post_str('typ', (string)($existing['typ'] ?? 'raum')));
    $parentId = post_int('parent_id') ?: null;
    $parent = $parentId ? standort_find($parentId) : null;
    if ($parentId && !$parent) {
        $errors[] = 'Den gewählten übergeordneten Platz gibt es nicht.';
        $parentId = null;
    }
    if ($name === '') {
        $errors[] = 'Bitte einen Namen angeben.';
    }
    if (!standort_passt($typ, $parent ? (string)$parent['typ'] : null)) {
        $label = STANDORT_TYPEN[$typ]['label'];
        $erlaubt = STANDORT_TYPEN[$typ]['eltern'];
        $errors[] = $parent === null
            ? sprintf('%s braucht einen übergeordneten Platz: %s.', $label, implode(', ', array_map(static fn($e) => STANDORT_TYPEN[$e]['label'], $erlaubt)))
            : ($erlaubt
                ? sprintf('%s kann nur unter %s liegen%s.', $label, implode(', ', array_map(static fn($e) => STANDORT_TYPEN[$e]['label'], $erlaubt)), !empty(STANDORT_TYPEN[$typ]['oben']) ? ' – oder ganz oben' : '')
                : sprintf('%s liegt immer ganz oben, ohne übergeordneten Platz.', $label));
    }
    if ($existing && $parentId) {
        $alle = standort_all();
        if ($parentId === (int)$existing['id'] || in_array($parentId, standort_nachkommen((int)$existing['id'], $alle), true)) {
            $errors[] = 'Ein Platz kann nicht unter sich selbst oder einem seiner Unterplätze liegen.';
        }
    }
    $anzahl = $existing ? 1 : max(1, min(STANDORT_MAX_SERIE, (int)post_int('anzahl', 1)));
    $start = max(0, (int)post_int('start', 1));
    if ($errors) {
        return [[], $errors];
    }
    $koordinaten = null;
    if (trim(post_str('koordinaten')) !== '') {
        $koordinaten = standort_koordinaten_parsen(post_str('koordinaten'));
        if ($koordinaten === null) {
            return [[], ['Die Koordinaten sind nicht lesbar – bitte als „Breite, Länge" mit Punkt, z. B. 49.142, 9.218.']];
        }
    }
    $data = [
        'typ'       => $typ,
        'parent_id' => $parentId,
        'kurz'      => mb_substr(trim(post_str('kurz')), 0, 30),
        'notiz'     => post_str('notiz'),
        'is_active' => post_bool('is_active'),
        'updated_by' => (int)$user['id'],
    ];
    if (isset($_POST['koordinaten'])) {
        // Von Hand eingetragen oder geleert – das Gerät setzt sie über „Jetzt Position setzen"
        $alt = ($existing['geo_lat'] ?? null) !== null ? [round((float)$existing['geo_lat'], 6), round((float)$existing['geo_lng'], 6)] : null;
        if ($koordinaten !== $alt) {
            $data['geo_lat'] = $koordinaten[0] ?? null;
            $data['geo_lng'] = $koordinaten[1] ?? null;
            $data['geo_genauigkeit'] = null;
            $data['geo_quelle'] = $koordinaten ? 'mensch' : null;
            $data['geo_at'] = $koordinaten ? date('Y-m-d H:i:s') : null;
        }
    }
    if ($existing) {
        $data['name'] = mb_substr($name, 0, 120);
        db_update('standorte', $data, 'id = ?', [(int)$existing['id']]);
        audit('standort.bearbeitet', 'standort', (int)$existing['id'], $data['name']);
        return [[(int)$existing['id']], []];
    }
    $ids = [];
    $sort = standort_naechste_sort($parentId);
    for ($i = 0; $i < $anzahl; $i++) {
        $zeile = $data + ['created_by' => (int)$user['id']];
        $zeile['name'] = mb_substr($anzahl > 1 ? trim($name) . ' ' . ($start + $i) : $name, 0, 120);
        $zeile['kurz'] = $anzahl > 1 && $data['kurz'] !== '' ? mb_substr($data['kurz'] . ($start + $i), 0, 30) : $data['kurz'];
        $zeile['sort_order'] = $sort + $i * 10;
        $ids[] = db_insert('standorte', $zeile);
    }
    audit('standort.angelegt', 'standort', $ids[0], $anzahl > 1 ? sprintf('%s %d–%d', $name, $start, $start + $anzahl - 1) : $name);
    return [$ids, []];
}

/** Löschen nur ohne Unterplätze. Gibt eine Fehlermeldung oder null zurück. */
function standort_delete(array $s): ?string
{
    $kinder = (int)db_val('SELECT COUNT(*) FROM standorte WHERE parent_id = ?', [(int)$s['id']], 0);
    if ($kinder > 0) {
        return sprintf('„%s" hat noch %d Unterplatz/Unterplätze – bitte erst diese löschen oder verschieben.', (string)$s['name'], $kinder);
    }
    db_exec('DELETE FROM standorte WHERE id = ?', [(int)$s['id']]);
    audit('standort.geloescht', 'standort', (int)$s['id'], (string)$s['name']);
    return null;
}

/** Geschwister neu durchnummerieren und einen davon verschieben. Reine Funktion auf der Liste. */
function standort_verschieben(array $geschwister, int $id, string $richtung): array
{
    $ids = array_map('intval', array_column($geschwister, 'id'));
    $pos = array_search($id, $ids, true);
    if ($pos === false) {
        return $ids;
    }
    $neu = $richtung === 'hoch' ? $pos - 1 : $pos + 1;
    if ($neu < 0 || $neu >= count($ids)) {
        return $ids;
    }
    [$ids[$pos], $ids[$neu]] = [$ids[$neu], $ids[$pos]];
    return $ids;
}

function standort_move(array $s, string $richtung): void
{
    $parentId = (int)($s['parent_id'] ?? 0);
    $geschwister = db_all('SELECT id FROM standorte WHERE ' . ($parentId ? 'parent_id = ?' : 'parent_id IS NULL') . ' ORDER BY sort_order, name, id', $parentId ? [$parentId] : []);
    foreach (standort_verschieben($geschwister, (int)$s['id'], $richtung) as $i => $id) {
        db_update('standorte', ['sort_order' => ($i + 1) * 10], 'id = ?', [$id]);
    }
}

/* ==================================================================== */
/* Lage: Koordinaten und Karte                                           */
/* ==================================================================== */

/**
 * „48.123456, 9.123456" (auch mit Semikolon, Leerzeichen oder Komma als
 * Dezimalzeichen bei zwei Kommas) in [lat, lng]. null, wenn nichts da
 * oder unbrauchbar. Reine Funktion.
 */
function standort_koordinaten_parsen(string $text): ?array
{
    $text = trim(str_replace(['°', 'N', 'E', 'O'], ' ', $text));
    if ($text === '') {
        return null;
    }
    $teile = preg_split('/\s*[;\s]\s*|\s*,\s+/', $text) ?: [];
    if (count($teile) < 2 && substr_count($text, ',') === 1) {
        $teile = explode(',', $text);
    }
    $teile = array_values(array_filter(array_map('trim', $teile), 'strlen'));
    if (count($teile) !== 2) {
        return null;
    }
    $lat = (float)str_replace(',', '.', $teile[0]);
    $lng = (float)str_replace(',', '.', $teile[1]);
    if (!is_numeric(str_replace(',', '.', $teile[0])) || !is_numeric(str_replace(',', '.', $teile[1]))
        || $lat < -90 || $lat > 90 || $lng < -180 || $lng > 180 || ($lat === 0.0 && $lng === 0.0)) {
        return null;
    }
    return [round($lat, 6), round($lng, 6)];
}

/** Koordinaten lesbar: „48,12346 / 9,12346". Reine Funktion. */
function standort_koordinaten_text(?float $lat, ?float $lng): string
{
    return $lat === null || $lng === null ? '' : number_format($lat, 5, ',', '') . ' / ' . number_format($lng, 5, ',', '');
}

/** Position setzen – vom Gerät (geraet) oder von Hand (mensch). Gibt einen Fehlertext oder null. */
function standort_position_setzen(array $s, ?float $lat, ?float $lng, ?float $genauigkeit, string $quelle, array $user): ?string
{
    if ($lat === null || $lng === null) {
        db_update('standorte', ['geo_lat' => null, 'geo_lng' => null, 'geo_genauigkeit' => null, 'geo_quelle' => null, 'geo_at' => null, 'updated_by' => (int)$user['id']], 'id = ?', [(int)$s['id']]);
        audit('standort.position', 'standort', (int)$s['id'], 'Position entfernt');
        return null;
    }
    if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
        return 'Die Koordinaten liegen außerhalb der Erde.';
    }
    db_update('standorte', [
        'geo_lat'         => round($lat, 6),
        'geo_lng'         => round($lng, 6),
        'geo_genauigkeit' => $genauigkeit !== null ? round($genauigkeit, 1) : null,
        'geo_quelle'      => $quelle === 'geraet' ? 'geraet' : 'mensch',
        'geo_at'          => date('Y-m-d H:i:s'),
        'updated_by'      => (int)$user['id'],
    ], 'id = ?', [(int)$s['id']]);
    audit('standort.position', 'standort', (int)$s['id'], standort_koordinaten_text($lat, $lng));
    return null;
}

/* ==================================================================== */
/* Bilder                                                                */
/* ==================================================================== */

function standort_bild_dir(): string
{
    $dir = upload_dir() . DIRECTORY_SEPARATOR . 'standorte';
    if (!is_dir($dir)) {
        @mkdir($dir, 0770, true);
    }
    return $dir;
}

function standort_bilder(int $standortId): array
{
    return db_all('SELECT * FROM standort_bilder WHERE standort_id = ? ORDER BY is_cover DESC, id', [$standortId]);
}

function standort_bild_find(int $id): ?array
{
    return $id > 0 ? db_row('SELECT * FROM standort_bilder WHERE id = ?', [$id]) : null;
}

/** Titelbild je Platz: [standort_id => Bild] */
function standort_titelbilder(array $standortIds): array
{
    $ids = array_values(array_unique(array_filter(array_map('intval', $standortIds))));
    if (!$ids) {
        return [];
    }
    $in = implode(',', array_fill(0, count($ids), '?'));
    $out = [];
    foreach (db_all("SELECT * FROM standort_bilder WHERE standort_id IN ($in) ORDER BY is_cover DESC, id", $ids) as $b) {
        $sid = (int)$b['standort_id'];
        if (!isset($out[$sid]) || ((int)$b['is_cover'] === 1 && (int)$out[$sid]['is_cover'] !== 1)) {
            $out[$sid] = $b;
        }
    }
    return $out;
}

function standort_bild_pfad(array $bild, bool $vorschau = false): ?string
{
    $name = $vorschau && $bild['thumb_name'] ? $bild['thumb_name'] : $bild['stored_name'];
    $pfad = standort_bild_dir() . DIRECTORY_SEPARATOR . basename((string)$name);
    return is_file($pfad) ? $pfad : null;
}

/** Hochgeladene Bilder speichern – verkleinert, ohne Aufnahmeort. Gibt [Anzahl, Fehler[]] zurück. */
function standort_bilder_speichern(int $standortId, string $feld, string $titel, array $user): array
{
    $fehler = [];
    $anzahl = 0;
    if (empty($_FILES[$feld]) || !is_array($_FILES[$feld]['name'])) {
        return [0, ['Es kam keine Datei an. Bei sehr großen Dateien bricht der Browser den Upload ab, bevor die Anwendung ihn sieht.']];
    }
    if (!vfile_gd_available()) {
        return [0, ['Bilder brauchen die PHP-Erweiterung GD – sie fehlt in dieser Installation.']];
    }
    $max = upload_max_bytes();
    $dir = standort_bild_dir();
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
        if (!is_uploaded_file($tmp) && !defined('OVB_TEST_UPLOADS')) {
            $fehler[] = sprintf('„%s" wurde nicht über das Formular hochgeladen.', $name);
            continue;
        }
        $mime = class_exists('finfo') ? (string)(new finfo(FILEINFO_MIME_TYPE))->file($tmp) : '';
        $grund = vfile_check($name, (int)$_FILES[$feld]['size'][$i], $mime, 'bild', VFILE_BILD_TYPEN, $max);
        if ($grund !== null) {
            $fehler[] = $grund;
            continue;
        }
        $endung = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $basis = date('Ymd_His') . '_' . bin2hex(random_bytes(8));
        $gespeichert = $basis . '.' . $endung;
        $ziel = $dir . DIRECTORY_SEPARATOR . $gespeichert;
        if (vfile_process_image($tmp, $ziel, $mime, VFILE_BILD_MAX) === null) {
            $fehler[] = sprintf('„%s" ließ sich nicht als Bild lesen.', $name);
            continue;
        }
        @unlink($tmp);
        $vorschau = $basis . '_vorschau.' . $endung;
        if (vfile_process_image($ziel, $dir . DIRECTORY_SEPARATOR . $vorschau, $mime, VFILE_VORSCHAU_MAX) === null) {
            $vorschau = null;
        }
        $id = db_insert('standort_bilder', [
            'standort_id' => $standortId,
            'titel'       => mb_substr(trim($titel) !== '' ? trim($titel) : pathinfo($name, PATHINFO_FILENAME), 0, 200),
            'orig_name'   => mb_substr($name, 0, 255),
            'stored_name' => $gespeichert,
            'thumb_name'  => $vorschau,
            'mime'        => $mime,
            'size_bytes'  => (int)filesize($ziel),
            'uploaded_by' => (int)$user['id'],
        ]);
        if ($anzahl === 0 && !standort_bilder_hat_cover($standortId)) {
            standort_bild_cover_setzen($standortId, $id);
        }
        $anzahl++;
    }
    if ($anzahl > 0) {
        audit('standort.bilder', 'standort', $standortId, $anzahl . ' Bild(er)');
    }
    return [$anzahl, $fehler];
}

function standort_bilder_hat_cover(int $standortId): bool
{
    return (int)db_val('SELECT COUNT(*) FROM standort_bilder WHERE standort_id = ? AND is_cover = 1', [$standortId], 0) > 0;
}

function standort_bild_cover_setzen(int $standortId, int $bildId): void
{
    db_exec('UPDATE standort_bilder SET is_cover = CASE WHEN id = ? THEN 1 ELSE 0 END WHERE standort_id = ?', [$bildId, $standortId]);
}

function standort_bild_delete(array $bild): void
{
    foreach ([$bild['stored_name'], $bild['thumb_name']] as $name) {
        if ($name) {
            @unlink(standort_bild_dir() . DIRECTORY_SEPARATOR . basename((string)$name));
        }
    }
    db_exec('DELETE FROM standort_bilder WHERE id = ?', [(int)$bild['id']]);
    audit('standort.bild_geloescht', 'standort', (int)$bild['standort_id'], (string)$bild['titel']);
    // bleibt ein Bild übrig, wird das erste Titelbild
    if ((int)$bild['is_cover'] === 1) {
        $erstes = db_row('SELECT id FROM standort_bilder WHERE standort_id = ? ORDER BY id LIMIT 1', [(int)$bild['standort_id']]);
        if ($erstes) {
            standort_bild_cover_setzen((int)$bild['standort_id'], (int)$erstes['id']);
        }
    }
}

/** Zählung je Typ für die Übersicht. Reine Funktion. */
function standort_zaehlung(array $rows): array
{
    $out = [];
    foreach ($rows as $r) {
        $out[(string)$r['typ']] = ($out[(string)$r['typ']] ?? 0) + 1;
    }
    return $out;
}
