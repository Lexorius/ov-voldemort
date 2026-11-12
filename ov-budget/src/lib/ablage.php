<?php
declare(strict_types=1);

/*
 * Dateiablage: alle Dokumente des Ortsverbands in einem Baum aus Kategorien.
 *
 * Zwei Sorten Kategorien stehen nebeneinander:
 *   - eigene (Tabelle ablage_kategorien): die Leitung legt sie an, benennt sie
 *     um und hängt sie um; die Dokumente darin liegen in ablage_dateien
 *   - automatische: je Dateiablage eines Moduls eine gesperrte Kategorie mit
 *     Unterkategorien (Fahrzeuge › GKW, Veranstaltungen › 2026 › Sommerfest,
 *     Budget › 2026 › Ausgaben).
 *     Sie stehen in keiner Tabelle, sondern werden beim Anzeigen aus den
 *     Modulen gerechnet – so stimmen sie immer, und gepflegt wird im Modul.
 *
 * Jeder Knoten hat einen Schlüssel: eigene „k12", automatische „fahrzeug",
 * „fahrzeug.7", „veranstaltung.j2026", „stan.3.Ordner/Unterordner",
 * „buchung.j2026.ausgabe", „karte.g.Taktische Zeichen".
 *
 * Wer was sieht, entscheidet allein der Baum: ablage_baum_fuer() nimmt nur
 * auf, was die Person sehen darf – Dateien außerhalb davon gibt es für sie
 * nicht, weder in der Liste noch in der Suche noch beim Abruf.
 */

/** Die Dateiablagen der Module: Name aus den Einstellungen, Recht zum Sehen, Seite des Moduls */
const ABLAGE_QUELLEN = [
    'fahrzeug'      => ['label' => 'Fahrzeuge',       'name' => 'fahrzeug_modul_name',      'recht' => 'view_vehicles',   'route' => 'vehicles'],
    'veranstaltung' => ['label' => 'Veranstaltungen', 'name' => 'veranstaltung_modul_name', 'recht' => 'view_events',     'route' => 'events'],
    'termin'        => ['label' => 'Kalender',        'name' => 'kalender_modul_name',      'recht' => null,              'route' => 'kalender'],
    'standort'      => ['label' => 'Standorte',       'name' => 'standort_modul_name',      'recht' => null,              'route' => 'standorte'],
    'stan'          => ['label' => 'STAN',            'name' => 'stan_modul_name',          'recht' => null,              'route' => 'stan'],
    'newsletter'    => ['label' => 'Newsletter',      'name' => 'newsletter_modul_name',    'recht' => 'view_newsletter', 'route' => 'newsletter'],
    'wunsch'        => ['label' => 'Wünsch dir was',  'name' => 'wunsch_modul_name',        'recht' => null,              'route' => 'wishes'],
    'buchung'       => ['label' => 'Budget',          'name' => 'budget_modul_name',        'recht' => 'view_expenses',   'route' => 'budget'],
    // Symbole der Karten – Piktogramme, keine Dokumente: sie laufen nicht durch die KI-Auswertung
    'karte'         => ['label' => 'Karten',          'name' => 'karten_modul_name',        'recht' => 'view_karten',     'route' => 'karten'],
];

/* ==================================================================== */
/* Schlüssel und Baum – reine Funktionen                                 */
/* ==================================================================== */

/**
 * Schlüssel zerlegen: ['art' => 'eigen'|'auto'|'', 'id', 'quelle', 'teil', 'rest'].
 * „k12" → eigene Kategorie 12; „fahrzeug.7" → Quelle fahrzeug, id 7;
 * „veranstaltung.j2026" → teil „j2026"; „stan.3.A/B" → id 3, rest „A/B". Reine Funktion.
 */
function ablage_schluessel(string $key): array
{
    $leer = ['art' => '', 'id' => 0, 'quelle' => '', 'teil' => '', 'rest' => ''];
    $key = trim($key);
    if (preg_match('/^k([1-9]\d{0,9})$/', $key, $m)) {
        return ['art' => 'eigen', 'id' => (int)$m[1]] + $leer;
    }
    $t = explode('.', $key, 3);
    if (!isset(ABLAGE_QUELLEN[$t[0]])) {
        return $leer;
    }
    $teil = (string)($t[1] ?? '');
    return ['art' => 'auto', 'id' => ctype_digit($teil) ? (int)$teil : 0, 'quelle' => $t[0], 'teil' => $teil, 'rest' => (string)($t[2] ?? '')];
}

/** Ein Knoten mit allen Feldern, die Baum und Ansicht erwarten. Reine Funktion. */
function ablage_knoten_neu(string $key, ?string $eltern, string $name, array $mehr = []): array
{
    return $mehr + [
        'key'          => $key,
        'eltern'       => $eltern,
        'name'         => $name,
        'auto'         => false,   // aus einem Modul gerechnet: gesperrt, mit Schloss
        'quelle'       => '',
        'id'           => 0,       // Kennung der eigenen Kategorie
        'anzahl'       => 0,       // Dokumente direkt in diesem Knoten
        'url'          => '',      // bei automatischen: dorthin im Modul
        'hinweis'      => '',      // kleiner Zusatz hinter dem Namen (Kennzeichen, Datum)
        'beschreibung' => '',
        'nur_leitung'  => false,
    ];
}

/**
 * Aus der flachen Knotenliste den Baum: ['knoten' => [key => Knoten + kinder, gesamt, tiefe],
 * 'wurzeln' => [keys]]. Die Reihenfolge bleibt, wie geliefert. Knoten, deren Eltern fehlen,
 * werden Wurzeln. „gesamt" zählt die Dokumente samt allem darunter. Reine Funktion.
 */
function ablage_baum(array $knoten): array
{
    $k = [];
    foreach ($knoten as $n) {
        $n['kinder'] = [];
        $n['gesamt'] = (int)$n['anzahl'];
        $n['tiefe'] = 0;
        $k[(string)$n['key']] = $n;
    }
    $wurzeln = [];
    foreach ($k as $key => $n) {
        $e = $n['eltern'];
        if ($e !== null && $e !== $key && isset($k[$e])) {
            $k[$e]['kinder'][] = $key;
        } else {
            $k[$key]['eltern'] = null;
            $wurzeln[] = $key;
        }
    }
    $lauf = static function (string $key, int $tiefe, array $weg) use (&$lauf, &$k): int {
        $k[$key]['tiefe'] = $tiefe;
        $summe = (int)$k[$key]['anzahl'];
        foreach ($k[$key]['kinder'] as $kind) {
            if (!isset($weg[$kind])) {
                $summe += $lauf($kind, $tiefe + 1, $weg + [$kind => true]);
            }
        }
        return $k[$key]['gesamt'] = $summe;
    };
    foreach ($wurzeln as $w) {
        $lauf($w, 0, [$w => true]);
    }
    return ['knoten' => $k, 'wurzeln' => $wurzeln];
}

/** Der Weg von der Wurzel bis zum Knoten als Liste der Knoten. Reine Funktion. */
function ablage_pfad(string $key, array $baum): array
{
    $weg = [];
    $gesehen = [];
    while ($key !== '' && isset($baum['knoten'][$key]) && !isset($gesehen[$key])) {
        $gesehen[$key] = true;
        array_unshift($weg, $baum['knoten'][$key]);
        $key = (string)($baum['knoten'][$key]['eltern'] ?? '');
    }
    return $weg;
}

/** „Verwaltung › Verträge › Miete". Reine Funktion. */
function ablage_pfad_text(string $key, array $baum, string $trenner = ' › '): string
{
    return implode($trenner, array_column(ablage_pfad($key, $baum), 'name'));
}

/** Der Baum als verschachtelte Liste; der Weg zum gewählten Knoten ist aufgeklappt. */
function ablage_baum_html(array $baum, string $aktuell = ''): string
{
    $offen = array_flip(array_column(ablage_pfad($aktuell, $baum), 'key'));
    $zeichnen = static function (array $keys) use (&$zeichnen, $baum, $aktuell, $offen): string {
        $h = '<ul>';
        foreach ($keys as $key) {
            $n = $baum['knoten'][$key];
            $titel = $n['auto']
                ? 'Kommt aus dem Modul und wird dort gepflegt'
                : ($n['nur_leitung'] ? 'Nur für die Leitung sichtbar' : '');
            $zeile = '<span class="abaum__icon"' . ($titel !== '' ? ' title="' . e($titel) . '"' : '') . ' aria-hidden="true">'
                . ($n['auto'] ? '🔒' : ($n['nur_leitung'] ? '👁' : '📁')) . '</span>'
                . '<a class="abaum__link" href="' . e(url('ablage', ['k' => $key])) . '"' . ($key === $aktuell ? ' aria-current="page"' : '') . '>'
                . e((string)$n['name']) . ($n['auto'] ? '<span class="sr-only"> (gesperrt, aus dem Modul)</span>' : '') . '</a>'
                . '<span class="abaum__zahl">' . ((int)$n['gesamt'] > 0 ? (int)$n['gesamt'] : '') . '</span>';
            $klasse = 'abaum__zeile' . ($key === $aktuell ? ' is-aktiv' : '');
            if ($n['kinder']) {
                $h .= '<li><details' . (isset($offen[$key]) ? ' open' : '') . '><summary class="' . $klasse . '">' . $zeile . '</summary>'
                    . $zeichnen($n['kinder']) . '</details></li>';
            } else {
                $h .= '<li><div class="' . $klasse . ' abaum__blatt">' . $zeile . '</div></li>';
            }
        }
        return $h . '</ul>';
    };
    return $baum['wurzeln'] ? '<nav class="abaum" aria-label="Kategorien">' . $zeichnen($baum['wurzeln']) . '</nav>' : '';
}

/* ==================================================================== */
/* Eigene Kategorien                                                     */
/* ==================================================================== */

/** Alle eigenen Kategorien nach Namen, mit der Zahl ihrer Dokumente */
function ablage_kategorien(): array
{
    return db_all('SELECT k.*, (SELECT COUNT(*) FROM ablage_dateien d WHERE d.kategorie_id = k.id) AS anzahl
                   FROM ablage_kategorien k ORDER BY k.name, k.id');
}

function ablage_kategorie_find(?int $id): ?array
{
    return $id ? db_row('SELECT * FROM ablage_kategorien WHERE id = ?', [$id]) : null;
}

/** [id => Zeile]. Reine Funktion. */
function ablage_kategorien_index(array $rows): array
{
    $out = [];
    foreach ($rows as $r) {
        $out[(int)$r['id']] = $r;
    }
    return $out;
}

/** Trägt die Kategorie oder eine über ihr den Haken „Nur Leitung"? Reine Funktion. */
function ablage_kategorie_nur_leitung(int $id, array $index): bool
{
    $gesehen = [];
    while ($id > 0 && isset($index[$id]) && !isset($gesehen[$id])) {
        $gesehen[$id] = true;
        if ((int)($index[$id]['nur_leitung'] ?? 0) === 1) {
            return true;
        }
        $id = (int)($index[$id]['parent_id'] ?? 0);
    }
    return false;
}

/** Kennungen aller Kategorien unter dieser, über alle Ebenen. Reine Funktion. */
function ablage_kategorie_nachkommen(int $id, array $rows): array
{
    $kinder = [];
    foreach ($rows as $r) {
        $kinder[(int)($r['parent_id'] ?? 0)][] = (int)$r['id'];
    }
    $out = [];
    $offen = $kinder[$id] ?? [];
    while ($offen) {
        $n = array_shift($offen);
        if ($n === $id || in_array($n, $out, true)) {
            continue;
        }
        $out[] = $n;
        foreach ($kinder[$n] ?? [] as $k) {
            $offen[] = $k;
        }
    }
    return $out;
}

/**
 * Darf die Kategorie $id unter $parentId hängen? Die oberste Ebene (null) geht immer;
 * sonst muss es die Eltern geben, und sie dürfen weder die Kategorie selbst noch
 * eine ihrer Unterkategorien sein – das gäbe einen Kreis. Reine Funktion.
 */
function ablage_eltern_erlaubt(?int $id, ?int $parentId, array $rows): bool
{
    if ($parentId === null || $parentId === 0) {
        return true;
    }
    if (!isset(ablage_kategorien_index($rows)[$parentId])) {
        return false;
    }
    if ($id === null) {
        return true;
    }
    return $parentId !== $id && !in_array($parentId, ablage_kategorie_nachkommen($id, $rows), true);
}

/** Auswahlfeld der eigenen Kategorien, eingerückt nach Ebene. $ausser: diese Kennungen fehlen. Reine Funktion. */
function ablage_kategorie_optionen(array $rows, ?int $selected, string $leer = '', array $ausser = []): string
{
    $baum = ablage_baum(ablage_eigene_knoten($rows, true));
    $h = $leer !== '' ? '<option value="">' . e($leer) . '</option>' : '';
    $lauf = static function (array $keys) use (&$lauf, &$h, $baum, $selected, $ausser): void {
        foreach ($keys as $key) {
            $n = $baum['knoten'][$key];
            if (in_array((int)$n['id'], $ausser, true)) {
                continue;   // samt allem darunter
            }
            $h .= '<option value="' . (int)$n['id'] . '"' . ((int)$n['id'] === (int)$selected ? ' selected' : '') . '>'
                . str_repeat('– ', (int)$n['tiefe']) . e((string)$n['name']) . '</option>';
            $lauf($n['kinder']);
        }
    };
    $lauf($baum['wurzeln']);
    return $h;
}

/**
 * Die eigenen Kategorien als Knoten. Was „Nur Leitung" trägt – selbst oder
 * über eine Kategorie darüber –, fehlt für alle anderen. Reine Funktion.
 */
function ablage_eigene_knoten(array $rows, bool $leitung): array
{
    $index = ablage_kategorien_index($rows);
    $out = [];
    foreach ($rows as $r) {
        $id = (int)$r['id'];
        $verborgen = ablage_kategorie_nur_leitung($id, $index);
        if ($verborgen && !$leitung) {
            continue;
        }
        $out[] = ablage_knoten_neu('k' . $id, !empty($r['parent_id']) ? 'k' . (int)$r['parent_id'] : null, (string)$r['name'], [
            'id'           => $id,
            'anzahl'       => (int)($r['anzahl'] ?? 0),
            'beschreibung' => (string)($r['beschreibung'] ?? ''),
            'nur_leitung'  => $verborgen,
        ]);
    }
    return $out;
}

/** Kategorie aus dem Formular anlegen oder ändern. Gibt [id oder null, Fehler[]] zurück. */
function ablage_kategorie_save_from_post(?array $bestehend, array $user): array
{
    $id = $bestehend ? (int)$bestehend['id'] : null;
    $name = trim((string)preg_replace('/\s+/u', ' ', post_str('name')));
    $parent = post_int('parent_id') ?: null;
    $rows = ablage_kategorien();

    $errors = [];
    if ($name === '') {
        $errors[] = 'Bitte einen Namen angeben.';
    } elseif (mb_strlen($name) > 150) {
        $errors[] = 'Der Name ist zu lang (höchstens 150 Zeichen).';
    }
    if (!ablage_eltern_erlaubt($id, $parent, $rows)) {
        $errors[] = 'Eine Kategorie kann nicht unter sich selbst oder unter einer ihrer Unterkategorien hängen.';
    }
    foreach ($rows as $r) {
        if ((int)$r['id'] !== (int)$id && (int)($r['parent_id'] ?? 0) === (int)$parent && mb_strtolower((string)$r['name']) === mb_strtolower($name)) {
            $errors[] = 'Auf dieser Ebene gibt es schon eine Kategorie „' . $name . '".';
            break;
        }
    }
    if ($errors) {
        return [null, $errors];
    }

    $data = [
        'parent_id'    => $parent,
        'name'         => $name,
        'beschreibung' => mb_substr(trim(post_str('beschreibung')), 0, 500),
        'nur_leitung'  => post_bool('nur_leitung'),
    ];
    if ($id) {
        db_update('ablage_kategorien', $data, 'id = ?', [$id]);
        audit('ablage.kategorie_geaendert', 'ablage_kategorie', $id, $name);
        return [$id, []];
    }
    $data['created_by'] = (int)$user['id'];
    $id = db_insert('ablage_kategorien', $data);
    audit('ablage.kategorie_angelegt', 'ablage_kategorie', $id, $name);
    return [$id, []];
}

/** Kategorie löschen – nur leer. Gibt den Grund zurück, wenn es nicht geht, sonst null. */
function ablage_kategorie_delete(array $kat): ?string
{
    $id = (int)$kat['id'];
    if ((int)db_val('SELECT COUNT(*) FROM ablage_kategorien WHERE parent_id = ?', [$id], 0) > 0) {
        return 'Die Kategorie hat Unterkategorien – erst diese umhängen oder löschen.';
    }
    if ((int)db_val('SELECT COUNT(*) FROM ablage_dateien WHERE kategorie_id = ?', [$id], 0) > 0) {
        return 'In der Kategorie liegen noch Dokumente – erst verschieben oder entfernen.';
    }
    db_exec('DELETE FROM ablage_kategorien WHERE id = ?', [$id]);
    audit('ablage.kategorie_geloescht', 'ablage_kategorie', $id, (string)$kat['name']);
    return null;
}

/* ==================================================================== */
/* Automatische Kategorien aus den Modulen                               */
/* ==================================================================== */

/**
 * Fahrzeuge: je Fahrzeug eine Kategorie – alle im Dienst, die anderen nur,
 * wenn noch Dateien daran hängen. $rows: id, bezeichnung, kennzeichen, is_active, n. Reine Funktion.
 */
function ablage_knoten_fahrzeuge(array $rows): array
{
    $out = [];
    foreach ($rows as $r) {
        $aktiv = (int)($r['is_active'] ?? 1) === 1;
        if (!$aktiv && (int)$r['n'] === 0) {
            continue;
        }
        $out[] = ablage_knoten_neu('fahrzeug.' . (int)$r['id'], 'fahrzeug', (string)$r['bezeichnung'], [
            'auto' => true, 'quelle' => 'fahrzeug', 'anzahl' => (int)$r['n'],
            'hinweis' => trim((string)($r['kennzeichen'] ?? '') . ($aktiv ? '' : ' · außer Dienst'), ' ·'),
            'url' => url('vehicle', ['id' => (int)$r['id']]) . '#dokumente',
        ]);
    }
    return $out;
}

/**
 * Nach Jahren gegliedert (Veranstaltungen, Termine, Wünsche): erst das Jahr, darunter
 * je Eintrag eine Kategorie. $rows: id, name, jahr, datum, n – in der gewünschten
 * Reihenfolge; die Jahre erscheinen in der Reihenfolge ihres ersten Eintrags. Reine Funktion.
 */
function ablage_knoten_jahre(string $quelle, array $rows, string $route, string $anker = ''): array
{
    $jahre = [];
    $eintraege = [];
    foreach ($rows as $r) {
        $jahr = (int)($r['jahr'] ?? 0);
        $jahrKey = $quelle . '.j' . $jahr;
        $jahre[$jahrKey] ??= ablage_knoten_neu($jahrKey, $quelle, $jahr > 0 ? (string)$jahr : 'ohne Datum', ['auto' => true, 'quelle' => $quelle]);
        $eintraege[] = ablage_knoten_neu($quelle . '.' . (int)$r['id'], $jahrKey, (string)$r['name'], [
            'auto' => true, 'quelle' => $quelle, 'anzahl' => (int)$r['n'],
            'hinweis' => !empty($r['datum']) ? de_date(substr((string)$r['datum'], 0, 10)) : '',
            'url' => url($route, ['id' => (int)$r['id']]) . $anker,
        ]);
    }
    return array_merge(array_values($jahre), $eintraege);
}

/**
 * Stell- und Lagerplätze: der Baum der Plätze, beschnitten auf alles, woran Bilder
 * hängen – samt dem Weg dorthin. $zahl: [standort_id => Bilder]. Reine Funktion.
 */
function ablage_knoten_standorte(array $plaetze, array $zahl): array
{
    $index = [];
    foreach ($plaetze as $p) {
        $index[(int)$p['id']] = $p;
    }
    $behalten = [];
    foreach ($zahl as $sid => $n) {
        $id = (int)$sid;
        while ((int)$n > 0 && $id > 0 && isset($index[$id]) && !isset($behalten[$id])) {
            $behalten[$id] = true;
            $id = (int)($index[$id]['parent_id'] ?? 0);
        }
    }
    $out = [];
    foreach ($plaetze as $p) {
        $id = (int)$p['id'];
        if (!isset($behalten[$id])) {
            continue;
        }
        $eltern = (int)($p['parent_id'] ?? 0);
        $out[] = ablage_knoten_neu('standort.' . $id, $eltern > 0 && isset($behalten[$eltern]) ? 'standort.' . $eltern : 'standort', (string)$p['name'], [
            'auto' => true, 'quelle' => 'standort', 'anzahl' => (int)($zahl[$id] ?? 0),
            'hinweis' => (string)($p['kurz'] ?? ''),
            'url' => url('standorte', ['id' => $id]),
        ]);
    }
    return $out;
}

/**
 * Budget: je Haushaltsjahr „Ausgaben" und „Einnahmen" – die Belege liegen direkt darin, die
 * Buchung steht am Beleg. $rows: jahr, art, n – neuestes Jahr zuerst. Reine Funktion.
 */
function ablage_knoten_buchungen(array $rows): array
{
    $jahre = [];
    $arten = [];
    foreach ($rows as $r) {
        $jahr = (int)$r['jahr'];
        $art = (string)$r['art'] === 'einnahme' ? 'einnahme' : 'ausgabe';
        $jahrKey = 'buchung.j' . $jahr;
        $jahre[$jahrKey] ??= ablage_knoten_neu($jahrKey, 'buchung', (string)$jahr, ['auto' => true, 'quelle' => 'buchung']);
        $arten[] = ablage_knoten_neu($jahrKey . '.' . $art, $jahrKey, $art === 'einnahme' ? 'Einnahmen' : 'Ausgaben', [
            'auto' => true, 'quelle' => 'buchung', 'anzahl' => (int)$r['n'],
            'url' => url('expenses', ['jahr' => $jahr, 'art' => $art]),
        ]);
    }
    return array_merge(array_values($jahre), $arten);
}

/**
 * Karten: die Symbolbibliothek, je Gruppe eine Unterkategorie; Symbole ohne Gruppe
 * stehen unter „Ohne Gruppe". $rows: gruppe, n – nach Gruppe sortiert. Reine Funktion.
 */
function ablage_knoten_karten(array $rows): array
{
    $out = [];
    $ohne = null;
    foreach ($rows as $r) {
        $gruppe = (string)$r['gruppe'];
        $knoten = ablage_knoten_neu($gruppe === '' ? 'karte.ohne' : 'karte.g.' . $gruppe, 'karte', $gruppe === '' ? 'Ohne Gruppe' : $gruppe,
            ['auto' => true, 'quelle' => 'karte', 'anzahl' => (int)$r['n'], 'url' => url('karten_symbole')]);
        if ($gruppe === '') {
            $ohne = $knoten;   // ans Ende
        } else {
            $out[] = $knoten;
        }
    }
    return $ohne ? array_merge($out, [$ohne]) : $out;
}

/** Der Ordner einer STAN-Datei aus ihrem Pfad im Paket – '' für die oberste Ebene. Reine Funktion. */
function ablage_stan_ordner(string $pfad): string
{
    $ordner = dirname(str_replace('\\', '/', $pfad));
    return $ordner === '.' || $ordner === '/' ? '' : trim($ordner, '/');
}

/**
 * STAN: je Version eine Kategorie, darunter die Ordner des Pakets als Baum.
 * $versionen: id, nummer, bemerkung (neueste zuerst); $pfade: version_id, pfad je Datei. Reine Funktion.
 */
function ablage_knoten_stan(array $versionen, array $pfade): array
{
    $zahl = [];   // [version_id][ordner] => Dateien direkt darin
    foreach ($pfade as $p) {
        $vid = (int)$p['version_id'];
        $ordner = ablage_stan_ordner((string)$p['pfad']);
        $zahl[$vid][$ordner] = ($zahl[$vid][$ordner] ?? 0) + 1;
        // jeder Ordner auf dem Weg ist ein Knoten, auch wenn nur Unterordner darin liegen
        while ($ordner !== '' && str_contains($ordner, '/')) {
            $ordner = substr($ordner, 0, (int)strrpos($ordner, '/'));
            $zahl[$vid][$ordner] ??= 0;
        }
    }
    $out = [];
    foreach ($versionen as $i => $v) {
        $vid = (int)$v['id'];
        $vKey = 'stan.' . $vid;
        $out[] = ablage_knoten_neu($vKey, 'stan', 'Version ' . (int)$v['nummer'], [
            'auto' => true, 'quelle' => 'stan', 'anzahl' => (int)($zahl[$vid][''] ?? 0),
            'hinweis' => trim(($i === 0 ? 'aktuell' : '') . (trim((string)($v['bemerkung'] ?? '')) !== '' ? ' · ' . trim((string)$v['bemerkung']) : ''), ' ·'),
            'url' => url('stan', ['version' => $vid]) . '#dateien',
        ]);
        $ordnerListe = array_keys($zahl[$vid] ?? []);
        natcasesort($ordnerListe);
        foreach ($ordnerListe as $ordner) {
            $ordner = (string)$ordner;
            if ($ordner === '') {
                continue;
            }
            $pos = strrpos($ordner, '/');
            $out[] = ablage_knoten_neu($vKey . '.' . $ordner, $pos === false ? $vKey : $vKey . '.' . substr($ordner, 0, $pos),
                $pos === false ? $ordner : substr($ordner, $pos + 1),
                ['auto' => true, 'quelle' => 'stan', 'anzahl' => (int)$zahl[$vid][$ordner], 'url' => url('stan', ['version' => $vid]) . '#dateien']);
        }
    }
    return $out;
}

/** Die Dateiablagen der Module als gesperrte Kategorien – nur die, die die Person sehen darf */
function ablage_auto_knoten(array $user): array
{
    $out = [];
    foreach (ABLAGE_QUELLEN as $quelle => $def) {
        if ($def['recht'] !== null && !can($def['recht'])) {
            continue;
        }
        $wurzel = ablage_knoten_neu($quelle, null, (string)setting($def['name'], $def['label']),
            ['auto' => true, 'quelle' => $quelle, 'url' => url($def['route'])]);
        $darunter = [];
        switch ($quelle) {
            case 'fahrzeug':
                $darunter = ablage_knoten_fahrzeuge(db_all(
                    'SELECT v.id, v.bezeichnung, v.kennzeichen, v.is_active,
                            (SELECT COUNT(*) FROM vehicle_files f WHERE f.vehicle_id = v.id) AS n
                     FROM vehicles v ORDER BY v.is_active DESC, v.bezeichnung, v.id'));
                break;
            case 'veranstaltung':
                $darunter = ablage_knoten_jahre('veranstaltung', db_all(
                    'SELECT e.id, e.titel AS name, YEAR(e.beginn) AS jahr, e.beginn AS datum,
                            (SELECT COUNT(*) FROM event_files f WHERE f.event_id = e.id) AS n
                     FROM events e WHERE EXISTS (SELECT 1 FROM event_files f WHERE f.event_id = e.id)
                     ORDER BY e.beginn DESC, e.id DESC'), 'event', '#dateien');
                break;
            case 'termin':
                $termine = db_all(
                    'SELECT k.id, k.titel AS name, YEAR(k.beginn) AS jahr, k.beginn AS datum, k.ziel, k.ziel_id, k.created_by, '
                    . kalender_fachgruppen_sub() . ',
                            (SELECT COUNT(*) FROM calendar_files f WHERE f.entry_id = k.id) AS n
                     FROM calendar_entries k WHERE EXISTS (SELECT 1 FROM calendar_files f WHERE f.entry_id = k.id)
                     ORDER BY k.beginn DESC, k.id DESC');
                // nur Termine, die die Person auch im Kalender sieht
                $darunter = ablage_knoten_jahre('termin', array_values(array_filter($termine,
                    static fn(array $t): bool => kalender_sichtbar($t, $user))), 'kalender_termin', '#dateien');
                break;
            case 'standort':
                $zahl = [];
                foreach (db_all('SELECT standort_id, COUNT(*) AS n FROM standort_bilder GROUP BY standort_id') as $r) {
                    $zahl[(int)$r['standort_id']] = (int)$r['n'];
                }
                $darunter = ablage_knoten_standorte(standort_all(), $zahl);
                break;
            case 'stan':
                $darunter = ablage_knoten_stan(
                    db_all('SELECT id, nummer, bemerkung FROM stan_versionen ORDER BY nummer DESC, id DESC'),
                    db_all('SELECT version_id, pfad FROM stan_dateien'));
                break;
            case 'newsletter':
                $wurzel['anzahl'] = (int)db_val("SELECT COUNT(*) FROM newsletter WHERE stored_name <> ''", [], 0);
                break;
            case 'wunsch':
                $darunter = ablage_knoten_jahre('wunsch', db_all(
                    'SELECT w.id, w.bezeichnung AS name, YEAR(w.created_at) AS jahr, w.created_at AS datum,
                            (SELECT COUNT(*) FROM wish_attachments a WHERE a.wish_id = w.id) AS n
                     FROM wishes w WHERE EXISTS (SELECT 1 FROM wish_attachments a WHERE a.wish_id = w.id)
                     ORDER BY w.created_at DESC, w.id DESC'), 'wish', '#anlagen');
                break;
            case 'karte':
                $darunter = ablage_knoten_karten(db_all('SELECT gruppe, COUNT(*) AS n FROM karten_symbole GROUP BY gruppe ORDER BY gruppe'));
                break;
            case 'buchung':
                $darunter = ablage_knoten_buchungen(db_all(
                    'SELECT x.jahr, x.art, COUNT(*) AS n FROM expense_files f JOIN expenses x ON x.id = f.expense_id
                     GROUP BY x.jahr, x.art ORDER BY x.jahr DESC, x.art'));
                break;
        }
        $out[] = $wurzel;
        foreach ($darunter as $n) {
            $out[] = $n;
        }
    }
    return $out;
}

/** Der ganze Baum, wie ihn die Person sieht: erst die eigenen Kategorien, dann die der Module */
function ablage_baum_fuer(array $user, ?array $eigene = null): array
{
    return ablage_baum(array_merge(
        ablage_eigene_knoten($eigene ?? ablage_kategorien(), can('manage_ablage')),
        ablage_auto_knoten($user)
    ));
}

/* ==================================================================== */
/* Dokumente lesen – über alle Ablagen in einer Form                     */
/* ==================================================================== */

/**
 * Woher die Dateien einer Ablage kommen: Spalten auf gemeinsame Namen gebracht, alles
 * unter dem Alias f. 'suche': Spalten für die Namenssuche, 'fest': Bedingung, die immer gilt,
 * 'route': Seite, die die Datei ausliefert. null = unbekannte Ablage. Reine Funktion.
 */
function ablage_quelle_sql(string $typ): ?array
{
    $gemeinsam = 'f.id, f.orig_name, f.mime, f.size_bytes, f.created_at';
    $wer = 'f.uploaded_by, u.display_name AS hochgeladen_von';
    $u = 'LEFT JOIN users u ON u.id = f.uploaded_by';
    return match ($typ) {
        'ablage' => ['route' => 'ablage_datei', 'fest' => '', 'suche' => ['f.titel', 'f.orig_name'],
            'spalten' => "$gemeinsam, f.titel, f.thumb_name, f.art, f.kategorie_id AS objekt_id, NULL AS zusatz, $wer",
            'von' => "ablage_dateien f $u"],
        'fahrzeug' => ['route' => 'vehicle_file', 'fest' => '', 'suche' => ['f.titel', 'f.orig_name'],
            'spalten' => "$gemeinsam, f.titel, f.thumb_name, f.art, f.vehicle_id AS objekt_id,
                CASE WHEN f.tank_id IS NOT NULL THEN 'Tankbeleg' WHEN f.order_id IS NOT NULL THEN CONCAT('Auftrag ', COALESCE(NULLIF(o.nummer, ''), f.order_id)) ELSE NULL END AS zusatz, $wer",
            'von' => "vehicle_files f $u LEFT JOIN vehicle_orders o ON o.id = f.order_id"],
        'veranstaltung' => ['route' => 'event_file', 'fest' => '', 'suche' => ['f.titel', 'f.orig_name'],
            'spalten' => "$gemeinsam, f.titel, f.thumb_name, f.art, f.event_id AS objekt_id, NULL AS zusatz, $wer",
            'von' => "event_files f $u"],
        'termin' => ['route' => 'kalender_file', 'fest' => '', 'suche' => ['f.titel', 'f.orig_name'],
            'spalten' => "$gemeinsam, f.titel, f.thumb_name, f.art, f.entry_id AS objekt_id, NULL AS zusatz, $wer",
            'von' => "calendar_files f $u"],
        'standort' => ['route' => 'standort_bild', 'fest' => '', 'suche' => ['f.titel', 'f.orig_name'],
            'spalten' => "$gemeinsam, f.titel, f.thumb_name, 'bild' AS art, f.standort_id AS objekt_id, NULL AS zusatz, $wer",
            'von' => "standort_bilder f $u"],
        'stan' => ['route' => 'stan_file', 'fest' => '', 'suche' => ['f.name', 'f.pfad'],
            'spalten' => "f.id, f.name AS orig_name, f.mime, f.size_bytes, f.created_at, '' AS titel, NULL AS thumb_name, 'dokument' AS art,
                f.version_id AS objekt_id, f.pfad AS zusatz, NULL AS uploaded_by, NULL AS hochgeladen_von",
            'von' => 'stan_dateien f'],
        'newsletter' => ['route' => 'newsletter_datei', 'fest' => "f.stored_name <> ''", 'suche' => ['f.titel', 'f.orig_name'],
            'spalten' => "$gemeinsam, f.titel, NULL AS thumb_name, 'dokument' AS art, 0 AS objekt_id, NULL AS zusatz,
                f.created_by AS uploaded_by, u.display_name AS hochgeladen_von",
            'von' => 'newsletter f LEFT JOIN users u ON u.id = f.created_by'],
        'wunsch' => ['route' => 'download', 'fest' => '', 'suche' => ['f.orig_name'],
            'spalten' => "$gemeinsam, '' AS titel, NULL AS thumb_name, 'dokument' AS art, f.wish_id AS objekt_id, f.kind AS zusatz, $wer",
            'von' => "wish_attachments f $u"],
        // Belege: die Buchung steht als Zusatz am Beleg; Jahr und Art sagen, in welchem Knoten er liegt
        'buchung' => ['route' => 'expense_file', 'fest' => '', 'suche' => ['f.titel', 'f.orig_name', 'x.bezeichnung', 'x.lieferant', 'x.beleg_nr'],
            'spalten' => "$gemeinsam, f.titel, f.thumb_name, f.art, f.expense_id AS objekt_id, x.bezeichnung AS zusatz, $wer,
                x.jahr AS gruppe_jahr, x.art AS gruppe_art",
            'von' => "expense_files f $u JOIN expenses x ON x.id = f.expense_id"],
        // Symbole der Karten: das Symbol ist seine eigene Vorschau, die Gruppe sagt, in welchem Knoten es liegt
        'karte' => ['route' => 'karten_symbol', 'fest' => '', 'suche' => ['f.name', 'f.orig_name', 'f.gruppe'],
            'spalten' => "$gemeinsam, f.name AS titel, f.stored_name AS thumb_name, 'bild' AS art, 0 AS objekt_id, f.gruppe AS zusatz, $wer",
            'von' => "karten_symbole f $u"],
        default => null,
    };
}

/** Eine Zeile fertig machen: Ablage, Anzeigename, Verweis auf die Datei und auf die Vorschau. Reine Funktion. */
function ablage_zeile_fertig(string $typ, array $r, string $route): array
{
    $r['typ'] = $typ;
    $r['id'] = (int)$r['id'];
    $r['objekt_id'] = (int)($r['objekt_id'] ?? 0);
    $r['titel'] = trim((string)($r['titel'] ?? ''));
    $r['name'] = $r['titel'] !== '' ? $r['titel'] : (string)$r['orig_name'];
    $r['zusatz'] = (string)($r['zusatz'] ?? '');
    $r['link'] = url($route, ['id' => $r['id']]);
    $r['vorschau'] = trim((string)($r['thumb_name'] ?? '')) !== '' ? url($route, ['id' => $r['id'], 'vorschau' => 1]) : '';
    return $r;
}

/** Dateien einer Ablage, neueste zuerst. $where arbeitet mit dem Alias f. */
function ablage_zeilen(string $typ, string $where = '', array $params = [], int $limit = 500, string $order = 'f.created_at DESC, f.id DESC'): array
{
    $q = ablage_quelle_sql($typ);
    if ($q === null) {
        return [];
    }
    $w = array_values(array_filter([$q['fest'], $where], static fn(string $x): bool => $x !== ''));
    $rows = db_all(
        "SELECT {$q['spalten']} FROM {$q['von']}" . ($w ? ' WHERE (' . implode(') AND (', $w) . ')' : '')
        . " ORDER BY $order LIMIT " . max(1, $limit),
        $params
    );
    return array_map(static fn(array $r): array => ablage_zeile_fertig($typ, $r, $q['route']), $rows);
}

/** In welchem Knoten des Baums liegt die Datei? Reine Funktion. */
function ablage_datei_knoten(array $d): string
{
    $oid = (int)($d['objekt_id'] ?? 0);
    switch ((string)($d['typ'] ?? '')) {
        case 'ablage':
            return 'k' . $oid;
        case 'newsletter':
            return 'newsletter';
        case 'karte':
            $gruppe = (string)($d['gruppe'] ?? $d['zusatz'] ?? '');
            return $gruppe === '' ? 'karte.ohne' : 'karte.g.' . $gruppe;
        case 'buchung':
            return 'buchung.j' . (int)($d['gruppe_jahr'] ?? 0) . '.' . ((string)($d['gruppe_art'] ?? '') === 'einnahme' ? 'einnahme' : 'ausgabe');
        case 'stan':
            $ordner = ablage_stan_ordner((string)($d['pfad'] ?? $d['zusatz'] ?? ''));
            return 'stan.' . $oid . ($ordner !== '' ? '.' . $ordner : '');
        case 'fahrzeug': case 'veranstaltung': case 'termin': case 'standort': case 'wunsch':
            return $d['typ'] . '.' . $oid;
    }
    return '';
}

/** Die Dokumente direkt in einem Knoten. Der Aufrufer nimmt den Knoten aus dem Baum der Person – das ist die Rechteprüfung. */
function ablage_dateien_von(array $knoten): array
{
    $s = ablage_schluessel((string)$knoten['key']);
    if ($s['art'] === 'eigen') {
        return ablage_zeilen('ablage', 'f.kategorie_id = ?', [$s['id']]);
    }
    if ($s['art'] !== 'auto') {
        return [];
    }
    if ($s['quelle'] === 'newsletter') {
        return $s['teil'] === '' ? ablage_zeilen('newsletter') : [];
    }
    if ($s['quelle'] === 'karte') {
        if (!($s['teil'] === 'ohne' || ($s['teil'] === 'g' && $s['rest'] !== ''))) {
            return [];   // die Wurzel: nur Unterkategorien
        }
        $out = [];
        foreach (ablage_zeilen('karte', 'f.gruppe = ?', [$s['teil'] === 'ohne' ? '' : $s['rest']], 2000, 'f.name, f.id') as $d) {
            $d['gruppe'] = $d['zusatz'];
            $d['zusatz'] = '';   // die Gruppe steht schon als Kategorie da
            $out[] = $d;
        }
        return $out;
    }
    if ($s['quelle'] === 'buchung') {
        // nur „Ausgaben" und „Einnahmen" eines Jahres tragen Belege
        return preg_match('/^j(\d{4})$/', $s['teil'], $m) && in_array($s['rest'], ['ausgabe', 'einnahme'], true)
            ? ablage_zeilen('buchung', 'x.jahr = ? AND x.art = ?', [(int)$m[1], $s['rest']])
            : [];
    }
    if ($s['id'] <= 0) {
        return [];   // Wurzel eines Moduls oder ein Jahr: nur Unterkategorien
    }
    if ($s['quelle'] === 'stan') {
        $out = [];
        foreach (ablage_zeilen('stan', 'f.version_id = ?', [$s['id']], 5000, 'f.pfad, f.name, f.id') as $d) {
            if (ablage_stan_ordner($d['zusatz']) === $s['rest']) {
                $d['pfad'] = $d['zusatz'];
                $d['zusatz'] = '';   // der Ordner steht schon als Kategorie da
                $out[] = $d;
            }
        }
        return $out;
    }
    $spalte = ['fahrzeug' => 'f.vehicle_id', 'veranstaltung' => 'f.event_id', 'termin' => 'f.entry_id',
               'standort' => 'f.standort_id', 'wunsch' => 'f.wish_id'][$s['quelle']] ?? null;
    return $spalte !== null ? ablage_zeilen($s['quelle'], $spalte . ' = ?', [$s['id']]) : [];
}

/** Nur Dateien, deren Knoten im Baum der Person hängt; „knoten" wird dabei gesetzt. Reine Funktion. */
function ablage_im_baum(array $dateien, array $baum): array
{
    $out = [];
    foreach ($dateien as $d) {
        $key = ablage_datei_knoten($d);
        if ($key !== '' && isset($baum['knoten'][$key])) {
            $d['knoten'] = $key;
            $out[] = $d;
        }
    }
    return $out;
}

/** Die zuletzt abgelegten Dokumente der eigenen Kategorien, soweit die Person sie sieht */
function ablage_neueste(array $baum, int $anzahl = 10): array
{
    return array_slice(ablage_im_baum(ablage_zeilen('ablage', '', [], $anzahl * 4), $baum), 0, $anzahl);
}

/**
 * Suche über die ganze Ablage: im Namen der Dateien (Titel, Dateiname, bei der STAN auch
 * der Ordner) und in den Schlagwörtern und Zusammenfassungen der KI. Gefunden wird nur,
 * was im Baum der Person hängt. 'wo' sagt, wo der Treffer saß.
 */
function ablage_suche(string $q, array $baum, int $limit = 80): array
{
    $q = trim($q);
    if ($q === '') {
        return [];
    }
    $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q) . '%';
    $typen = ['ablage'];
    foreach (array_keys(ABLAGE_QUELLEN) as $quelle) {
        if (isset($baum['knoten'][$quelle])) {
            $typen[] = $quelle;
        }
    }
    $treffer = [];
    foreach ($typen as $typ) {
        $spalten = ablage_quelle_sql($typ)['suche'];
        $w = implode(' OR ', array_map(static fn(string $c): string => "$c LIKE ?", $spalten));
        foreach (ablage_zeilen($typ, $w, array_fill(0, count($spalten), $like), $limit) as $d) {
            $treffer[$typ . ':' . $d['id']] = $d + ['wo' => 'Name'];
        }
    }
    // Schlagwörter und Zusammenfassungen der KI
    $ids = [];
    foreach (db_all('SELECT typ, datei_id FROM datei_ki WHERE tags LIKE ? OR zusammenfassung LIKE ? ORDER BY geaendert_am DESC, id DESC LIMIT ' . ($limit * 3), [$like, $like]) as $r) {
        $typ = (string)$r['typ'];
        if (in_array($typ, $typen, true) && !isset($treffer[$typ . ':' . (int)$r['datei_id']])) {
            $ids[$typ][] = (int)$r['datei_id'];
        }
    }
    foreach ($ids as $typ => $liste) {
        $liste = array_slice(array_values(array_unique($liste)), 0, $limit);
        foreach (ablage_zeilen($typ, 'f.id IN (' . implode(',', array_fill(0, count($liste), '?')) . ')', $liste, $limit) as $d) {
            $treffer[$typ . ':' . $d['id']] = $d + ['wo' => 'Schlagwort oder Zusammenfassung'];
        }
    }
    $out = ablage_im_baum(array_values($treffer), $baum);
    usort($out, static fn(array $a, array $b): int => strcmp((string)$b['created_at'], (string)$a['created_at']) ?: $b['id'] <=> $a['id']);
    return array_slice($out, 0, $limit);
}

/**
 * Je Datei festhalten, was die Person daran darf: 'darf_ki' (Zusammenfassung und
 * Schlagwörter ändern) und 'darf_aendern' (umbenennen, verschieben, entfernen –
 * nur bei Dokumenten der eigenen Kategorien; alles andere pflegt das Modul).
 */
function ablage_rechte_setzen(array $dateien, array $user): array
{
    $leitung = in_array((string)($user['role'] ?? ''), ['admin', 'leitung'], true);
    $termine = [];
    foreach ($dateien as &$d) {
        $d['darf_aendern'] = $d['typ'] === 'ablage' && afile_darf_aendern($d, $user);
        $d['darf_ki'] = match ($d['typ']) {
            'ablage'        => $d['darf_aendern'],
            'fahrzeug'      => can('manage_vehicles'),
            'veranstaltung' => can('manage_events'),
            'standort'      => can('admin'),
            'stan'          => $leitung,
            'newsletter'    => can('manage_newsletter'),
            'buchung'       => can('manage_budget'),
            'karte'         => false,   // Symbole haben keine KI-Auswertung
            'wunsch'        => can('manage_wishes') || (int)($d['uploaded_by'] ?? 0) === (int)$user['id'],
            'termin'        => $termine[$d['objekt_id']] ??= (static function (int $id) use ($user): bool {
                $t = kalender_find($id);
                return $t !== null && kalender_darf_bearbeiten($t, $user);
            })($d['objekt_id']),
            default         => false,
        };
    }
    unset($d);
    return $dateien;
}

/** KI-Ergebnisse und offene Aufträge zu einer gemischten Liste: [typ => ['ergebnisse' => …, 'auftraege' => …]] */
function ablage_ki_daten(array $dateien): array
{
    $ids = [];
    foreach ($dateien as $d) {
        $ids[(string)$d['typ']][] = (int)$d['id'];
    }
    $out = [];
    foreach ($ids as $typ => $liste) {
        $out[$typ] = function_exists('ki_fuer_dateien') ? ki_fuer_dateien($typ, $liste) : ['ergebnisse' => [], 'auftraege' => []];
    }
    return $out;
}

/** Kürzel für das Symbol einer Datei: PDF, DOCX … – oder „?" ohne Endung. Reine Funktion. */
function ablage_endung(string $name): string
{
    $e = strtoupper(pathinfo($name, PATHINFO_EXTENSION));
    return $e !== '' ? mb_substr($e, 0, 4) : '?';
}

/* ==================================================================== */
/* Dokumente der eigenen Kategorien                                      */
/* ==================================================================== */

function afile_dir(): string
{
    $dir = upload_dir() . DIRECTORY_SEPARATOR . 'ablage';
    if (!is_dir($dir)) {
        @mkdir($dir, 0770, true);
    }
    return $dir;
}

function afile_find(?int $id): ?array
{
    return $id ? db_row('SELECT f.*, u.display_name AS hochgeladen_von FROM ablage_dateien f LEFT JOIN users u ON u.id = f.uploaded_by WHERE f.id = ?', [$id]) : null;
}

/** Pfad zur Datei oder zur Vorschau – oder null */
function afile_path(array $datei, bool $vorschau = false): ?string
{
    $name = $vorschau && ($datei['thumb_name'] ?? null) ? $datei['thumb_name'] : $datei['stored_name'];
    $pfad = afile_dir() . DIRECTORY_SEPARATOR . basename((string)$name);
    return is_file($pfad) ? $pfad : null;
}

/** Umbenennen, verschieben, entfernen: die Leitung alles, sonst nur, wer hochladen darf, das Eigene. */
function afile_darf_aendern(array $datei, array $user): bool
{
    return can('manage_ablage') || (can('upload_ablage') && (int)($datei['uploaded_by'] ?? 0) === (int)$user['id']);
}

/**
 * Hochgeladene Dateien in eine eigene Kategorie legen. Gibt [anzahl, fehler[]] zurück.
 * Fotos werden neu geschrieben – das entfernt den Aufnahmeort aus den Metadaten –,
 * bleiben aber groß (oft sind es abfotografierte Schriftstücke) und bekommen ein
 * Vorschaubild. Jede Datei geht danach in die KI-Auswertung.
 */
function afile_store_uploads(int $kategorieId, string $feld, string $titel, array $user): array
{
    [$dateien, $fehler] = upload_dokumente_ablegen($feld, afile_dir());
    foreach ($dateien as $d) {
        $id = db_insert('ablage_dateien', [
            'kategorie_id' => $kategorieId,
            'art'          => $d['art'],
            'titel'        => trim($titel) !== '' ? mb_substr(trim($titel), 0, 200) : $d['titel'],
            'orig_name'    => $d['orig_name'],
            'stored_name'  => $d['stored_name'],
            'thumb_name'   => $d['thumb_name'],
            'mime'         => $d['mime'],
            'size_bytes'   => $d['size_bytes'],
            'uploaded_by'  => (int)$user['id'],
        ]);
        if (function_exists('ki_einreihen')) {
            ki_einreihen('ablage', $id, $d['art']);
        }
        audit('ablage.datei_abgelegt', 'ablage_datei', $id, $d['orig_name']);
    }
    return [count($dateien), $fehler];
}

/** Titel und Kategorie eines Dokuments ändern; ein leerer Titel fällt auf den Dateinamen zurück */
function afile_aendern(array $datei, string $titel, int $kategorieId): void
{
    $titel = trim($titel) !== '' ? trim($titel) : pathinfo((string)$datei['orig_name'], PATHINFO_FILENAME);
    db_update('ablage_dateien', ['titel' => mb_substr($titel, 0, 200), 'kategorie_id' => $kategorieId], 'id = ?', [(int)$datei['id']]);
    audit('ablage.datei_geaendert', 'ablage_datei', (int)$datei['id'], $titel);
}

function afile_delete(array $datei): void
{
    foreach ([$datei['stored_name'] ?? '', $datei['thumb_name'] ?? ''] as $name) {
        if ($name) {
            $pfad = afile_dir() . DIRECTORY_SEPARATOR . basename((string)$name);
            if (is_file($pfad)) {
                @unlink($pfad);
            }
        }
    }
    db_exec('DELETE FROM ablage_dateien WHERE id = ?', [(int)$datei['id']]);
    if (function_exists('ki_vergessen')) {
        ki_vergessen('ablage', (int)$datei['id']);
    }
    audit('ablage.datei_entfernt', 'ablage_datei', (int)$datei['id'], (string)($datei['titel'] ?: $datei['orig_name']));
}
