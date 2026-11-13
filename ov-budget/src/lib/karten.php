<?php
declare(strict_types=1);

/*
 * Karten: Kartenausschnitte auf OpenStreetMap mit eigenen Symbolen.
 *
 *   karten          ein Ausschnitt: Name, Mittelpunkt, Zoom
 *   karten_symbole  die Bibliothek: hochgeladene SVG und Bilder, nach Gruppen
 *   karten_marker   ein Symbol auf einer Karte: wo, wie groß, wie gedreht, Beschriftung
 *
 * Die Karte selbst zeichnet der Browser (assets/js/karte.js) ohne fremde
 * Bibliothek; die Kacheln lädt er direkt vom eingestellten Kachelserver.
 * Per Rechtsklick (am Handy: lange drücken) setzt man ein Symbol aus der
 * Bibliothek. Dazu einblendbar: die Fahrzeuge mit ihrer zuletzt gemeldeten
 * Position (Divera, QR-Code im Fahrzeug, von Hand).
 *
 * SVG kann Skript enthalten. Deshalb wird jede SVG beim Hochladen geprüft
 * und abgelehnt, sobald sie Aktives oder Fremdes enthält; ausgeliefert wird
 * sie nur mit einer Regel, die dem Browser jedes Skript verbietet, und
 * eingebunden nur als Bild.
 */

/** Dateiendungen der Symbole */
const KARTEN_SYMBOL_TYPEN = ['svg', 'png', 'jpg', 'jpeg', 'webp', 'gif'];
/** Rasterbilder werden auf diese Kantenlänge begrenzt – ein Symbol braucht nicht mehr */
const KARTEN_SYMBOL_MAX = 1024;
/** Eine SVG darf höchstens so groß sein – sie wird beim Prüfen ganz gelesen */
const KARTEN_SVG_MAX_BYTES = 2 * 1024 * 1024;
const KARTEN_ZOOM_MIN = 2;
const KARTEN_ZOOM_MAX = 19;
const KARTEN_GROESSE_MIN = 12;
const KARTEN_GROESSE_MAX = 400;
const KARTEN_GROESSE_VORGABE = 40;
const KARTEN_KACHEL_VORGABE = 'https://tile.openstreetmap.org/{z}/{x}/{y}.png';
/** Mitte Deutschlands – Start, solange es keinen besseren Punkt gibt */
const KARTEN_START = ['lat' => 51.1634, 'lng' => 10.4477, 'zoom' => 6];
/** Ab so vielen Stunden gilt eine Fahrzeugposition als alt (blasser gezeichnet) */
const KARTEN_FAHRZEUG_ALT_STD = 24;
/** Takt, in dem eine offene Karte die Fahrzeugpositionen neu holt (Sekunden): Vorgabe und Grenzen */
const KARTEN_FAHRZEUG_SEKUNDEN = 30;
const KARTEN_FAHRZEUG_SEKUNDEN_MIN = 10;
const KARTEN_FAHRZEUG_SEKUNDEN_MAX = 600;

/* ==================================================================== */
/* Reine Helfer                                                          */
/* ==================================================================== */

/** Liegt der Punkt auf der Karte? Die Kacheln reichen bis gut 85 Grad Breite. Reine Funktion. */
function karten_koordinate_ok(mixed $lat, mixed $lng): bool
{
    return is_numeric($lat) && is_numeric($lng) && abs((float)$lat) <= 85.05 && abs((float)$lng) <= 180.0;
}

function karten_zoom(mixed $zoom): int
{
    return max(KARTEN_ZOOM_MIN, min(KARTEN_ZOOM_MAX, (int)round((float)$zoom)));
}

function karten_groesse(mixed $px): int
{
    return max(KARTEN_GROESSE_MIN, min(KARTEN_GROESSE_MAX, (int)round((float)$px)));
}

/** Drehung in ganzen Grad von 0 bis 359 – auch aus negativen oder zu großen Werten. Reine Funktion. */
function karten_drehung(mixed $grad): int
{
    return (((int)round((float)$grad)) % 360 + 360) % 360;
}

/**
 * Taugt die Adresse der Kacheln? Sie muss mit http(s):// beginnen und die Platzhalter
 * {z}, {x} und {y} enthalten; sonst gilt die Vorgabe. Reine Funktion.
 */
function karten_kachel_url_pruefen(string $url): string
{
    $url = trim($url);
    $ok = (bool)preg_match('#^https?://[^\s"\'<>]+$#i', $url)
        && str_contains($url, '{z}') && str_contains($url, '{x}') && str_contains($url, '{y}');
    return $ok ? $url : KARTEN_KACHEL_VORGABE;
}

/**
 * Wie oft eine offene Karte die Fahrzeugpositionen neu holt, in Sekunden – zwischen 10 und 600.
 * Frischer als der Abruf bei Divera werden die Positionen dadurch nicht. Reine Funktion über den Wert.
 */
function karten_fahrzeug_sekunden(mixed $wert = null): int
{
    $wert ??= setting('karten_fahrzeug_sekunden', (string)KARTEN_FAHRZEUG_SEKUNDEN);
    $n = is_numeric($wert) ? (int)round((float)$wert) : KARTEN_FAHRZEUG_SEKUNDEN;
    return max(KARTEN_FAHRZEUG_SEKUNDEN_MIN, min(KARTEN_FAHRZEUG_SEKUNDEN_MAX, $n));
}

function karten_kachel_url(): string
{
    return karten_kachel_url_pruefen((string)setting('karten_kachel_url', KARTEN_KACHEL_VORGABE));
}

/**
 * Darf diese SVG als Symbol dienen? null = ja, sonst der Grund.
 * Abgelehnt wird alles Aktive (Skript, Ereignis-Attribute, eingebettete Seiten) und alles,
 * was etwas von außen nachlädt. Es wird nichts „bereinigt" – eine Datei ist sauber oder
 * sie bleibt draußen. Reine Funktion.
 */
function karten_svg_pruefen(string $svg): ?string
{
    if (strlen($svg) > KARTEN_SVG_MAX_BYTES) {
        return 'die Datei ist für ein Symbol zu groß (höchstens ' . bytes_human(KARTEN_SVG_MAX_BYTES) . ')';
    }
    if (!mb_check_encoding($svg, 'UTF-8')) {
        return 'die Datei ist nicht in UTF-8 gespeichert';
    }
    if (!preg_match('/<svg[\s>]/i', $svg)) {
        return 'die Datei ist keine SVG';
    }
    // Zeichenverweise könnten Verbotenes verstecken (&#106;avascript:) – erst auflösen, dann prüfen
    $klar = html_entity_decode($svg, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $klar = (string)preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $klar);
    $verboten = [
        '/<\s*script/i'                                   => 'sie enthält Skript',
        '/<\s*(foreignObject|iframe|embed|object|audio|video|canvas|handler|listener|set|animate)\b/i'
                                                          => 'sie enthält eingebettete oder bewegte Inhalte',
        '/[\s"\'\/]on[a-z]+\s*=/i'                        => 'sie enthält Ereignis-Attribute (onload, onclick …)',
        '/(javascript|vbscript)\s*:/i'                    => 'sie enthält einen Skript-Verweis',
        '/<!\s*(ENTITY|DOCTYPE[^>]*\[)/i'                 => 'sie enthält eigene Entitäten (in Inkscape als „Normales SVG" speichern)',
        '/<\?xml-stylesheet/i'                            => 'sie bindet ein fremdes Stylesheet ein',
        '/@import/i'                                      => 'sie lädt ein fremdes Stylesheet nach',
        // besitzergreifend (*+, ?+), damit das Anführungszeichen nicht wieder hergegeben wird
        '/(?<![a-z])href\s*+=\s*+["\']?+\s*+(?!#|data:image\/(png|jpe?g|gif|webp);)/i'
                                                          => 'sie verweist auf etwas außerhalb der Datei',
        // eine eingebettete Schrift (data:) ist Teil der Datei und lädt nichts nach
        '/url\(\s*+["\']?+\s*+(?!#|data:image\/(png|jpe?g|gif|webp);|data:(?:application\/(?:x-)?font-(?:woff2?|ttf|otf|truetype|opentype)|font\/(?:woff2?|ttf|otf|sfnt))[;,])/i'
                                                          => 'sie lädt etwas von außerhalb nach',
    ];
    foreach ($verboten as $muster => $grund) {
        if (preg_match($muster, $klar)) {
            return $grund;
        }
    }
    return null;
}

/**
 * Eine hochgeladene Symboldatei prüfen: Endung, Größe, und ob der Inhalt zur Endung passt.
 * $inhalt wird nur bei SVG gebraucht. null = passt, sonst der Grund. Reine Funktion.
 */
function karten_symbol_pruefen(string $name, int $groesse, string $mime, ?string $inhalt, int $maxBytes): ?string
{
    if ($groesse <= 0) {
        return sprintf('„%s" ist leer.', $name);
    }
    if ($groesse > $maxBytes) {
        return sprintf('„%s" ist zu groß (höchstens %s).', $name, bytes_human($maxBytes));
    }
    $endung = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    if (!in_array($endung, KARTEN_SYMBOL_TYPEN, true)) {
        return sprintf('„%s": Dateityp .%s geht nicht als Symbol (erlaubt: %s).', $name, $endung, implode(', ', KARTEN_SYMBOL_TYPEN));
    }
    if ($endung === 'svg') {
        $grund = karten_svg_pruefen((string)$inhalt);
        return $grund === null ? null : sprintf('„%s" wurde abgelehnt: %s.', $name, $grund);
    }
    if (!str_starts_with($mime, 'image/') || $mime === 'image/svg+xml') {
        return sprintf('„%s" trägt eine Bild-Endung, ist aber kein solches Bild.', $name);
    }
    return null;
}

/**
 * Gruppenname glätten und an eine vorhandene Schreibweise angleichen – „fahrzeuge" wird
 * „Fahrzeuge", wenn es die Gruppe so schon gibt; sonst zerfiele sie in zwei. Reine Funktion.
 */
function karten_gruppe_angleichen(string $neu, array $vorhandene): string
{
    $neu = mb_substr(trim((string)preg_replace('/\s+/u', ' ', $neu)), 0, 100);
    foreach ($vorhandene as $g) {
        if ($neu !== '' && mb_strtolower((string)$g) === mb_strtolower($neu)) {
            return (string)$g;
        }
    }
    return $neu;
}

/** Symbole nach Gruppe: [gruppe => Symbole[]], Gruppen nach Namen, die ohne Gruppe zuletzt. Reine Funktion. */
function karten_symbole_gruppiert(array $symbole): array
{
    $out = [];
    foreach ($symbole as $s) {
        $out[(string)($s['gruppe'] ?? '')][] = $s;
    }
    uksort($out, static fn($a, $b): int => ((string)$a === '') <=> ((string)$b === '') ?: strnatcasecmp((string)$a, (string)$b));
    return $out;
}

/** „vor 5 Min.", „vor 3 Std.", „vor 2 Tagen" – wie alt eine Position ist. Reine Funktion. */
function karten_alter_text(int $sekunden): string
{
    $sekunden = max(0, $sekunden);
    if ($sekunden < 90) {
        return 'gerade eben';
    }
    if ($sekunden < 3600) {
        return 'vor ' . (int)round($sekunden / 60) . ' Min.';
    }
    if ($sekunden < 2 * 86400) {
        return 'vor ' . (int)round($sekunden / 3600) . ' Std.';
    }
    return 'vor ' . (int)round($sekunden / 86400) . ' Tagen';
}

/**
 * Fahrzeuge für die Karte: nur mit brauchbarer Position. Je Fahrzeug id, name, kurz
 * (Funkrufname, sonst Bezeichnung), lat, lng, stand (Text), alt (Position älter als ein Tag
 * oder ohne Zeitpunkt), url. $rows: Zeilen aus vehicles. Reine Funktion.
 */
function karten_fahrzeug_punkte(array $rows, ?int $jetzt = null): array
{
    $jetzt ??= time();
    $out = [];
    foreach ($rows as $r) {
        if (!karten_koordinate_ok($r['geo_lat'] ?? null, $r['geo_lng'] ?? null)) {
            continue;
        }
        $zeit = !empty($r['geo_at']) ? strtotime((string)$r['geo_at']) : false;
        $alter = $zeit !== false ? $jetzt - $zeit : null;
        $quelle = ['divera' => 'Divera', 'qr' => 'QR-Code', 'mensch' => 'von Hand'][(string)($r['geo_quelle'] ?? '')] ?? '';
        $out[] = [
            'id'    => (int)$r['id'],
            'name'  => (string)$r['bezeichnung'],
            'kurz'  => trim((string)($r['funkrufname'] ?? '')) !== '' ? trim((string)$r['funkrufname']) : (string)$r['bezeichnung'],
            'lat'   => round((float)$r['geo_lat'], 6),
            'lng'   => round((float)$r['geo_lng'], 6),
            'stand' => trim(($alter !== null ? karten_alter_text($alter) : 'Zeitpunkt unbekannt') . ($quelle !== '' ? ' · ' . $quelle : '')),
            'alt'   => $alter === null || $alter > KARTEN_FAHRZEUG_ALT_STD * 3600,
            'url'   => url('vehicle', ['id' => (int)$r['id']]),
            // das taktische Zeichen des Fahrzeugs – ohne bleibt es beim Punkt
            'src'   => (int)($r['symbol_id'] ?? 0) > 0 ? url('karten_symbol', ['id' => (int)$r['symbol_id']]) : '',
        ];
    }
    return $out;
}

/** Ein gesetztes Symbol, wie es der Browser braucht. Reine Funktion. */
function karten_marker_daten(array $m): array
{
    return [
        'id'      => (int)$m['id'],
        'symbol'  => (int)$m['symbol_id'],
        'lat'     => round((float)$m['lat'], 6),
        'lng'     => round((float)$m['lng'], 6),
        'groesse' => karten_groesse($m['groesse'] ?? KARTEN_GROESSE_VORGABE),
        'drehung' => karten_drehung($m['drehung'] ?? 0),
        'text'    => (string)($m['beschriftung'] ?? ''),
        'name'    => (string)($m['symbol_name'] ?? ''),
        'src'     => url('karten_symbol', ['id' => (int)$m['symbol_id']]),
    ];
}

/**
 * Alles, was karte.js zum Zeichnen braucht – als ein Paket für das data-Attribut.
 * $darf: ['setzen' => bool, 'verwalten' => bool, 'fahrzeuge' => bool]
 */
function karten_konfig(array $karte, array $marker, array $symbole, array $darf): array
{
    return [
        'id'        => (int)$karte['id'],
        'lat'       => (float)$karte['lat'],
        'lng'       => (float)$karte['lng'],
        'zoom'      => karten_zoom($karte['zoom']),
        'zoomMin'   => KARTEN_ZOOM_MIN,
        'zoomMax'   => KARTEN_ZOOM_MAX,
        'kacheln'   => karten_kachel_url(),
        'quelle'    => (string)setting('karten_kachel_quelle', '© OpenStreetMap-Mitwirkende'),
        'api'       => url('karten_api'),
        'symbolSeite' => url('karten_symbole'),
        'csrf'      => csrf_token(),
        'setzen'    => !empty($darf['setzen']),
        'verwalten' => !empty($darf['verwalten']),
        // Fahrzeuge gibt es nur für die, die sie auch sonst sehen dürfen
        'fahrzeuge' => !empty($darf['fahrzeuge']),
        'fahrzeugeAn' => !empty($darf['fahrzeuge']) && (int)($karte['fahrzeuge'] ?? 0) === 1,
        'fahrzeugSekunden' => karten_fahrzeug_sekunden(),
        'groesse'   => ['min' => KARTEN_GROESSE_MIN, 'max' => KARTEN_GROESSE_MAX, 'vorgabe' => KARTEN_GROESSE_VORGABE],
        'marker'    => array_map('karten_marker_daten', $marker),
        'symbole'   => array_map(static fn(array $s): array => [
            'id' => (int)$s['id'], 'name' => (string)$s['name'], 'gruppe' => (string)$s['gruppe'],
            'src' => url('karten_symbol', ['id' => (int)$s['id']]),
        ], $symbole),
    ];
}

/* ==================================================================== */
/* Kartenausschnitte                                                     */
/* ==================================================================== */

function karten_liste(): array
{
    return db_all('SELECT k.*, u.display_name AS angelegt_von,
                          (SELECT COUNT(*) FROM karten_marker m WHERE m.karte_id = k.id) AS marker
                   FROM karten k LEFT JOIN users u ON u.id = k.created_by ORDER BY k.name, k.id');
}

function karte_find(?int $id): ?array
{
    return $id ? db_row('SELECT * FROM karten WHERE id = ?', [$id]) : null;
}

/** Wo eine neue Karte beginnt: an der Unterkunft, wenn sie Koordinaten hat, sonst mitten in Deutschland */
function karten_start(): array
{
    $heim = function_exists('geo_heim') ? geo_heim() : null;
    return $heim ? ['lat' => (float)$heim['lat'], 'lng' => (float)$heim['lng'], 'zoom' => 15] : KARTEN_START;
}

/** Karte aus dem Formular anlegen oder ändern. Gibt [id oder null, Fehler[]] zurück. */
function karte_save_from_post(?array $bestehend, array $user): array
{
    $name = trim((string)preg_replace('/\s+/u', ' ', post_str('name')));
    $errors = [];
    if ($name === '') {
        $errors[] = 'Bitte einen Namen angeben.';
    } elseif (mb_strlen($name) > 150) {
        $errors[] = 'Der Name ist zu lang (höchstens 150 Zeichen).';
    }
    // Mittelpunkt als „53.0793, 8.8017"; leer = bleibt bzw. Startpunkt
    $start = $bestehend ?? karten_start();
    $lat = (float)$start['lat'];
    $lng = (float)$start['lng'];
    $text = trim(post_str('mittelpunkt'));
    if ($text !== '') {
        $k = function_exists('standort_koordinaten_parsen') ? standort_koordinaten_parsen($text) : null;
        if ($k === null || !karten_koordinate_ok($k[0], $k[1])) {
            $errors[] = 'Der Mittelpunkt ist nicht lesbar – erwartet werden Breite und Länge, etwa „53.0793, 8.8017".';
        } else {
            [$lat, $lng] = $k;
        }
    }
    if ($errors) {
        return [null, $errors];
    }
    $data = [
        'name'         => $name,
        'beschreibung' => mb_substr(trim(post_str('beschreibung')), 0, 500),
        'lat'          => round($lat, 6),
        'lng'          => round($lng, 6),
        'zoom'         => karten_zoom(post_int('zoom', (int)$start['zoom'])),
        'fahrzeuge'    => post_bool('fahrzeuge'),
    ];
    if ($bestehend) {
        db_update('karten', $data, 'id = ?', [(int)$bestehend['id']]);
        audit('karten.karte_geaendert', 'karte', (int)$bestehend['id'], $name);
        return [(int)$bestehend['id'], []];
    }
    $data['created_by'] = (int)$user['id'];
    $id = db_insert('karten', $data);
    audit('karten.karte_angelegt', 'karte', $id, $name);
    return [$id, []];
}

/** Den Ausschnitt, den jemand gerade vor sich hat, als Start der Karte merken */
function karte_ausschnitt_setzen(array $karte, float $lat, float $lng, int $zoom): void
{
    db_update('karten', ['lat' => round($lat, 6), 'lng' => round($lng, 6), 'zoom' => karten_zoom($zoom)], 'id = ?', [(int)$karte['id']]);
}

/** Karte löschen; ihre gesetzten Symbole gehen mit (die Bibliothek bleibt) */
function karte_delete(array $karte): void
{
    db_exec('DELETE FROM karten WHERE id = ?', [(int)$karte['id']]);
    audit('karten.karte_geloescht', 'karte', (int)$karte['id'], (string)$karte['name']);
}

/* ==================================================================== */
/* Symbolbibliothek                                                      */
/* ==================================================================== */

function karten_symbol_dir(): string
{
    $dir = upload_dir() . DIRECTORY_SEPARATOR . 'karten';
    if (!is_dir($dir)) {
        @mkdir($dir, 0770, true);
    }
    return $dir;
}

/** Alle Symbole nach Gruppe und Name, mit der Zahl ihrer Verwendungen auf Karten */
function karten_symbole(): array
{
    return db_all('SELECT s.*, u.display_name AS hochgeladen_von,
                          (SELECT COUNT(*) FROM karten_marker m WHERE m.symbol_id = s.id) AS verwendet,
                          (SELECT COUNT(*) FROM vehicles v WHERE v.symbol_id = s.id) AS fahrzeuge
                   FROM karten_symbole s LEFT JOIN users u ON u.id = s.uploaded_by ORDER BY s.gruppe, s.name, s.id');
}

/** Nur Kennung, Name und Gruppe – für Auswahlfelder, etwa das taktische Zeichen eines Fahrzeugs */
function karten_symbole_kurz(): array
{
    return db_all('SELECT id, name, gruppe FROM karten_symbole ORDER BY gruppe, name, id');
}

/**
 * <option>-Liste der Symbole, nach Gruppen. Jede Option trägt die Adresse ihres Bildes
 * (data-src), damit daneben das gewählte Zeichen erscheinen kann. Reine Funktion bis auf url().
 */
function karten_symbol_optionen(array $symbole, ?int $gewaehlt, string $leer = '– kein Zeichen –'): string
{
    $html = '<option value="">' . e($leer) . '</option>';
    foreach (karten_symbole_gruppiert($symbole) as $gruppe => $liste) {
        $html .= '<optgroup label="' . e((string)$gruppe !== '' ? (string)$gruppe : 'Ohne Gruppe') . '">';
        foreach ($liste as $s) {
            $html .= '<option value="' . (int)$s['id'] . '" data-src="' . e(url('karten_symbol', ['id' => (int)$s['id']])) . '"'
                . ((int)$s['id'] === (int)$gewaehlt ? ' selected' : '') . '>' . e((string)$s['name']) . '</option>';
        }
        $html .= '</optgroup>';
    }
    return $html;
}

function karten_symbol_find(?int $id): ?array
{
    return $id ? db_row('SELECT * FROM karten_symbole WHERE id = ?', [$id]) : null;
}

/** Pfad zur Symboldatei – oder null */
function karten_symbol_pfad(array $symbol): ?string
{
    $pfad = karten_symbol_dir() . DIRECTORY_SEPARATOR . basename((string)$symbol['stored_name']);
    return is_file($pfad) ? $pfad : null;
}

/** Die Gruppennamen, die es schon gibt */
function karten_gruppen(): array
{
    return array_values(array_filter(array_column(db_all('SELECT DISTINCT gruppe FROM karten_symbole ORDER BY gruppe'), 'gruppe'),
        static fn($g): bool => (string)$g !== ''));
}

/**
 * Hochgeladene Symbole in die Bibliothek legen. Gibt [anzahl, fehler[]] zurück.
 * SVG wird geprüft und unverändert abgelegt; Rasterbilder werden neu geschrieben (ohne
 * Metadaten, höchstens KARTEN_SYMBOL_MAX Kantenlänge, Transparenz bleibt).
 * Ein Name gilt nur für eine einzelne Datei – bei mehreren heißt jedes Symbol wie seine Datei.
 */
function karten_symbole_speichern(string $feld, string $name, string $gruppe, array $user): array
{
    if (empty($_FILES[$feld]) || !is_array($_FILES[$feld]['name'])) {
        return [0, ['Es kam keine Datei an. Bei sehr großen Dateien bricht der Browser den Upload ab, bevor die Anwendung ihn sieht.']];
    }
    $dir = karten_symbol_dir();
    if (!is_dir($dir) || !is_writable($dir)) {
        return [0, [sprintf('Der Ordner %s ist nicht beschreibbar.', $dir)]];
    }
    $gruppe = karten_gruppe_angleichen($gruppe, karten_gruppen());
    $gewaehlt = count(array_filter($_FILES[$feld]['name'], static fn($n): bool => (string)$n !== ''));
    $max = upload_max_bytes();
    $anzahl = 0;
    $fehler = [];
    foreach ($_FILES[$feld]['name'] as $i => $orig) {
        $orig = (string)$orig;
        $err = (int)$_FILES[$feld]['error'][$i];
        if ($err === UPLOAD_ERR_NO_FILE || $orig === '') {
            continue;
        }
        if ($err !== UPLOAD_ERR_OK) {
            $fehler[] = sprintf('„%s" konnte nicht hochgeladen werden (Fehlercode %d).', $orig, $err);
            continue;
        }
        $tmp = (string)$_FILES[$feld]['tmp_name'][$i];
        $groesse = (int)$_FILES[$feld]['size'][$i];
        // Nur Dateien, die PHP selbst entgegengenommen hat (die Tests schalten das ab)
        if (!is_uploaded_file($tmp) && !defined('OVB_TEST_UPLOADS')) {
            $fehler[] = sprintf('„%s" wurde nicht über das Formular hochgeladen.', $orig);
            continue;
        }
        $endung = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
        $istSvg = $endung === 'svg';
        $mime = class_exists('finfo') ? (string)(new finfo(FILEINFO_MIME_TYPE))->file($tmp) : '';
        $inhalt = $istSvg && $groesse <= KARTEN_SVG_MAX_BYTES ? (string)file_get_contents($tmp) : null;
        $grund = $istSvg && $inhalt === null
            ? sprintf('„%s" ist für ein Symbol zu groß (höchstens %s).', $orig, bytes_human(KARTEN_SVG_MAX_BYTES))
            : karten_symbol_pruefen($orig, $groesse, $mime, $inhalt, $max);
        if ($grund !== null) {
            $fehler[] = $grund;
            continue;
        }
        $gespeichert = date('Ymd_His') . '_' . bin2hex(random_bytes(8)) . '.' . $endung;
        $ziel = $dir . DIRECTORY_SEPARATOR . $gespeichert;
        if (!$istSvg && function_exists('vfile_process_image') && vfile_process_image($tmp, $ziel, $mime, KARTEN_SYMBOL_MAX, 90) !== null) {
            @unlink($tmp);
            $groesse = (int)filesize($ziel);
        } elseif (!(defined('OVB_TEST_UPLOADS') ? rename($tmp, $ziel) : move_uploaded_file($tmp, $ziel))) {
            $fehler[] = sprintf('„%s" konnte nicht gespeichert werden.', $orig);
            continue;
        }
        $id = db_insert('karten_symbole', [
            'name'        => mb_substr($gewaehlt === 1 && trim($name) !== '' ? trim($name) : pathinfo($orig, PATHINFO_FILENAME), 0, 150),
            'gruppe'      => $gruppe,
            'orig_name'   => mb_substr($orig, 0, 255),
            'stored_name' => $gespeichert,
            'mime'        => $istSvg ? 'image/svg+xml' : $mime,
            'size_bytes'  => $groesse,
            'uploaded_by' => (int)$user['id'],
        ]);
        audit('karten.symbol_hochgeladen', 'karten_symbol', $id, $orig);
        $anzahl++;
    }
    return [$anzahl, $fehler];
}

/** Name und Gruppe eines Symbols ändern; ein leerer Name fällt auf den Dateinamen zurück */
function karten_symbol_aendern(array $symbol, string $name, string $gruppe): void
{
    $name = trim($name) !== '' ? trim($name) : pathinfo((string)$symbol['orig_name'], PATHINFO_FILENAME);
    db_update('karten_symbole', [
        'name'   => mb_substr($name, 0, 150),
        'gruppe' => karten_gruppe_angleichen($gruppe, karten_gruppen()),
    ], 'id = ?', [(int)$symbol['id']]);
}

/** Symbol aus der Bibliothek löschen – nur, wenn es auf keiner Karte mehr sitzt. Gibt den Grund oder null. */
function karten_symbol_delete(array $symbol): ?string
{
    $n = (int)db_val('SELECT COUNT(*) FROM karten_marker WHERE symbol_id = ?', [(int)$symbol['id']], 0);
    if ($n > 0) {
        return sprintf('„%s" sitzt noch %d-mal auf Karten – erst dort entfernen.', (string)$symbol['name'], $n);
    }
    $fz = (int)db_val('SELECT COUNT(*) FROM vehicles WHERE symbol_id = ?', [(int)$symbol['id']], 0);
    if ($fz > 0) {
        return sprintf('„%s" ist noch %d Fahrzeug(en) als taktisches Zeichen zugeordnet – erst dort ein anderes wählen.', (string)$symbol['name'], $fz);
    }
    $pfad = karten_symbol_pfad($symbol);
    if ($pfad !== null) {
        @unlink($pfad);
    }
    db_exec('DELETE FROM karten_symbole WHERE id = ?', [(int)$symbol['id']]);
    audit('karten.symbol_geloescht', 'karten_symbol', (int)$symbol['id'], (string)$symbol['name']);
    return null;
}

/**
 * Eine ganze Gruppe aus der Bibliothek nehmen – etwa, um einen Abruf rückgängig zu machen.
 * Was noch auf einer Karte sitzt oder einem Fahrzeug zugeordnet ist, bleibt.
 * Gibt [gelöscht, geblieben] zurück.
 */
function karten_gruppe_loeschen(string $gruppe): array
{
    $weg = 0;
    $bleibt = 0;
    foreach (db_all('SELECT * FROM karten_symbole WHERE gruppe = ? ORDER BY id', [$gruppe]) as $s) {
        $benutzt = (int)db_val('SELECT COUNT(*) FROM karten_marker WHERE symbol_id = ?', [(int)$s['id']], 0)
            + (int)db_val('SELECT COUNT(*) FROM vehicles WHERE symbol_id = ?', [(int)$s['id']], 0);
        if ($benutzt > 0) {
            $bleibt++;
            continue;
        }
        $pfad = karten_symbol_pfad($s);
        if ($pfad !== null) {
            @unlink($pfad);
        }
        db_exec('DELETE FROM karten_symbole WHERE id = ?', [(int)$s['id']]);
        $weg++;
    }
    if ($weg > 0) {
        audit('karten.gruppe_geloescht', 'karten_symbol', null, sprintf('%s: %d Symbole', $gruppe !== '' ? $gruppe : 'ohne Gruppe', $weg));
    }
    return [$weg, $bleibt];
}

/* ==================================================================== */
/* Gesetzte Symbole                                                      */
/* ==================================================================== */

function karten_marker(int $karteId): array
{
    return db_all('SELECT m.*, s.name AS symbol_name FROM karten_marker m JOIN karten_symbole s ON s.id = m.symbol_id
                   WHERE m.karte_id = ? ORDER BY m.id', [$karteId]);
}

function karten_marker_find(?int $id): ?array
{
    return $id ? db_row('SELECT m.*, s.name AS symbol_name FROM karten_marker m JOIN karten_symbole s ON s.id = m.symbol_id WHERE m.id = ?', [$id]) : null;
}

/** Symbol auf die Karte setzen. Gibt das gesetzte Symbol zurück – oder einen Fehlertext. */
function karten_marker_neu(array $karte, array $symbol, mixed $lat, mixed $lng, array $user): array|string
{
    if (!karten_koordinate_ok($lat, $lng)) {
        return 'Diese Stelle liegt nicht auf der Karte.';
    }
    $id = db_insert('karten_marker', [
        'karte_id'   => (int)$karte['id'],
        'symbol_id'  => (int)$symbol['id'],
        'lat'        => round((float)$lat, 6),
        'lng'        => round((float)$lng, 6),
        'groesse'    => KARTEN_GROESSE_VORGABE,
        'drehung'    => 0,
        'created_by' => (int)$user['id'],
    ]);
    return ['id' => $id, 'symbol_id' => (int)$symbol['id'], 'lat' => (float)$lat, 'lng' => (float)$lng,
            'groesse' => KARTEN_GROESSE_VORGABE, 'drehung' => 0, 'beschriftung' => '', 'symbol_name' => (string)$symbol['name']];
}

/**
 * Ein gesetztes Symbol ändern – nur die Felder, die in $neu vorkommen:
 * lat und lng (zusammen), groesse, drehung, beschriftung. Gibt die geänderten Felder zurück – oder einen Fehlertext.
 */
function karten_marker_aendern(array $marker, array $neu): array|string
{
    $data = [];
    if (array_key_exists('lat', $neu) || array_key_exists('lng', $neu)) {
        if (!karten_koordinate_ok($neu['lat'] ?? null, $neu['lng'] ?? null)) {
            return 'Diese Stelle liegt nicht auf der Karte.';
        }
        $data['lat'] = round((float)$neu['lat'], 6);
        $data['lng'] = round((float)$neu['lng'], 6);
    }
    if (array_key_exists('groesse', $neu)) {
        $data['groesse'] = karten_groesse($neu['groesse']);
    }
    if (array_key_exists('drehung', $neu)) {
        $data['drehung'] = karten_drehung($neu['drehung']);
    }
    if (array_key_exists('beschriftung', $neu)) {
        $data['beschriftung'] = mb_substr(trim((string)preg_replace('/\s+/u', ' ', (string)$neu['beschriftung'])), 0, 150);
    }
    if ($data) {
        db_update('karten_marker', $data, 'id = ?', [(int)$marker['id']]);
    }
    return $data;
}

function karten_marker_delete(array $marker): void
{
    db_exec('DELETE FROM karten_marker WHERE id = ?', [(int)$marker['id']]);
}

/* ==================================================================== */
/* Fahrzeuge                                                             */
/* ==================================================================== */

/** Fahrzeuge im Dienst, die eine Position gemeldet haben */
function karten_fahrzeuge(?int $jetzt = null): array
{
    return karten_fahrzeug_punkte(db_all(
        'SELECT id, bezeichnung, funkrufname, geo_lat, geo_lng, geo_at, geo_quelle, symbol_id FROM vehicles
         WHERE is_active = 1 AND geo_lat IS NOT NULL AND geo_lng IS NOT NULL ORDER BY bezeichnung, id'), $jetzt);
}
