<?php
declare(strict_types=1);

/**
 * KI-Auswertung hochgeladener Dateien über ein Ollama im eigenen Netz:
 * je Datei eine Zusammenfassung und Schlagwörter.
 *
 * Komplett abschaltbar: Ist der Hauptschalter aus, wird nichts eingereiht,
 * keine Verbindung aufgebaut und nichts verändert. Schon vorhandene
 * Zusammenfassungen bleiben sichtbar. Dokumente gehen nur an Adressen im
 * eigenen Netz – öffentliche Adressen lehnt die Einstellung ab.
 *
 * Ablauf: Upload → Auftrag in ki_auftraege → der minütliche Abruf
 * (cron.php) arbeitet ein paar Aufträge ab → Ergebnis in datei_ki.
 */

class KiException extends RuntimeException
{
}

/** Dauerhafter Grund (kein Vision-Modell, Dateiart nicht lesbar): wird nicht wiederholt */
class KiUebersprungen extends KiException
{
}

/** Ollama antwortet gar nicht (Rechner schläft, Netz weg): Auftrag zurück, Warteschlange ruht eine Weile */
class KiNichtErreichbar extends KiException
{
}

/** Dateiablagen, die ausgewertet werden können */
const KI_TYPEN = [
    'fahrzeug'      => ['schalter' => 'ki_fahrzeuge',       'label' => 'Fahrzeugakte',          'tabelle' => 'vehicle_files',   'route' => 'vehicle_file'],
    'veranstaltung' => ['schalter' => 'ki_veranstaltungen', 'label' => 'Veranstaltung',         'tabelle' => 'event_files',     'route' => 'event_file'],
    'termin'        => ['schalter' => 'ki_termine',         'label' => 'Termin',                'tabelle' => 'calendar_files',  'route' => 'kalender_file'],
    'standort'      => ['schalter' => 'ki_standorte',       'label' => 'Stell- und Lagerplatz', 'tabelle' => 'standort_bilder', 'route' => 'standort_bild'],
    'stan'          => ['schalter' => 'ki_stan',            'label' => 'STAN',                  'tabelle' => 'stan_dateien',    'route' => 'stan_file', 'bildfilter' => "f.mime NOT LIKE 'image/%'"],
    'newsletter'    => ['schalter' => 'ki_newsletter',      'label' => 'Newsletter',            'tabelle' => 'newsletter',      'route' => 'newsletter_datei', 'bildfilter' => "f.stored_name <> ''"],
];
const KI_VERSUCHE_MAX = 3;
const KI_RUHE_SEK = 600;   // so lange ruht die Warteschlange, wenn Ollama nicht erreichbar war
const KI_BILD_MAX_BYTES = 8 * 1024 * 1024;

/* ==================================================================== */
/* Schalter und Adresse                                                  */
/* ==================================================================== */

/** Hauptschalter an und eine brauchbare Adresse eingetragen? */
function ki_aktiv(): bool
{
    return setting_bool('ki_aktiv', false) && ki_url() !== '';
}

/** Darf diese Ablage ausgewertet werden? Hauptschalter und Modulschalter. */
function ki_modul_aktiv(string $typ): bool
{
    return isset(KI_TYPEN[$typ]) && ki_aktiv() && setting_bool(KI_TYPEN[$typ]['schalter'], true);
}

/** Ist der Rechner im eigenen Netz? Private IP-Adressen, Namen ohne Punkt, .local/.lan/.home/.internal. Reine Funktion. */
function ki_host_privat(string $host): bool
{
    $host = strtolower(trim($host, "[] \t"));
    if ($host === '') {
        return false;
    }
    if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
        // „privat" oder „reserviert" (127.0.0.1, ::1) = nicht öffentlich erreichbar
        return filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
    }
    if ($host === 'localhost' || !str_contains($host, '.')) {
        return true;
    }
    foreach (['.local', '.lan', '.home', '.internal', '.localhost', '.home.arpa', '.intern'] as $endung) {
        if (str_ends_with($host, $endung)) {
            return true;
        }
    }
    return false;
}

/** Warum taugt die Adresse nicht? null = sie passt. Reine Funktion. */
function ki_url_pruefen(string $url): ?string
{
    $url = trim($url);
    if ($url === '') {
        return 'Keine Adresse eingetragen.';
    }
    $t = parse_url($url);
    if (!is_array($t) || !in_array(strtolower((string)($t['scheme'] ?? '')), ['http', 'https'], true) || empty($t['host'])) {
        return 'Die Adresse muss mit http:// oder https:// beginnen, z. B. http://192.168.1.20:11434.';
    }
    if (isset($t['user']) || isset($t['pass']) || isset($t['query']) || isset($t['fragment'])) {
        return 'Nur Schema, Rechner, Port und Pfad – ohne Benutzername, Passwort oder Parameter.';
    }
    if (!ki_host_privat((string)$t['host'])) {
        return 'Nur Adressen im eigenen Netz sind erlaubt: private IP-Adressen (10.x, 172.16–31.x, 192.168.x), Rechnernamen ohne Punkt oder Namen auf .local, .lan, .home, .internal.';
    }
    return null;
}

/** Die eingestellte Adresse ohne Schrägstrich am Ende – oder leer, wenn sie nicht taugt. */
function ki_url(): string
{
    $url = rtrim(trim((string)setting('ki_ollama_url', '')), '/');
    return ki_url_pruefen($url) === null ? $url : '';
}

function ki_timeout(): int
{
    return max(10, min(600, setting_int('ki_timeout_sek', 120)));
}

/** Eingetragenes Vision-Modell oder leer – ohne eines werden Bilder gar nicht erst eingereiht */
function ki_vision_modell(): string
{
    return trim((string)setting('ki_vision_modell', ''));
}

/* ==================================================================== */
/* Warteschlange                                                         */
/* ==================================================================== */

/**
 * Datei zur Auswertung vormerken. Tut nichts, wenn die Auswertung aus ist –
 * und nichts für Bilder, solange kein Vision-Modell eingetragen ist.
 */
function ki_einreihen(string $typ, int $dateiId, string $art = ''): bool
{
    if ($dateiId <= 0 || !ki_modul_aktiv($typ)) {
        return false;
    }
    if ($art === 'bild' && ki_vision_modell() === '') {
        return false;
    }
    db_exec(
        "INSERT INTO ki_auftraege (typ, datei_id, status, versuche, fehler) VALUES (?, ?, 'offen', 0, '')
         ON DUPLICATE KEY UPDATE status = 'offen', versuche = 0, fehler = '', gestartet_am = NULL, erledigt_am = NULL",
        [$typ, $dateiId]
    );
    return true;
}

/** Auftrag und Ergebnis zu einer Datei löschen – beim Entfernen der Datei. */
function ki_vergessen(string $typ, int $dateiId): void
{
    db_exec('DELETE FROM ki_auftraege WHERE typ = ? AND datei_id = ?', [$typ, $dateiId]);
    db_exec('DELETE FROM datei_ki WHERE typ = ? AND datei_id = ?', [$typ, $dateiId]);
}

/** Alle Dateien einer eingeschalteten Ablage einreihen, die noch keinen Auftrag haben. Gibt die Zahl der neuen Aufträge. */
function ki_alle_einreihen(): int
{
    $n = 0;
    $ohneBilder = ki_vision_modell() === '';
    foreach (KI_TYPEN as $typ => $def) {
        if (!ki_modul_aktiv($typ) || ($ohneBilder && $typ === 'standort')) {
            continue;   // Plätze haben nur Bilder
        }
        $n += db_exec(
            "INSERT INTO ki_auftraege (typ, datei_id, status) SELECT ?, f.id, 'offen' FROM `{$def['tabelle']}` f
             WHERE f.id NOT IN (SELECT a.datei_id FROM ki_auftraege a WHERE a.typ = ?)" . ($ohneBilder ? ' AND ' . ($def['bildfilter'] ?? "f.art <> 'bild'") : ''),
            [$typ, $typ]
        );
    }
    return $n;
}

/** Alle Aufträge zurück auf Anfang – nach einem Modellwechsel oder einer neuen Fassung des Prompts */
function ki_alles_neu(): int
{
    return db_exec("UPDATE ki_auftraege SET status = 'offen', versuche = 0, fehler = '', gestartet_am = NULL, erledigt_am = NULL WHERE status <> 'laeuft'");
}

/** Fehlgeschlagene Aufträge noch einmal versuchen */
function ki_erneut_versuchen(): int
{
    return db_exec("UPDATE ki_auftraege SET status = 'offen', versuche = 0, fehler = '' WHERE status = 'fehler'");
}

/** Zähler je Stand: offen, laeuft, fertig, fehler – und wie viele Ergebnisse es gibt */
function ki_warteschlange_status(): array
{
    $out = ['offen' => 0, 'laeuft' => 0, 'fertig' => 0, 'fehler' => 0, 'ergebnisse' => 0];
    foreach (db_all('SELECT status, COUNT(*) AS n FROM ki_auftraege GROUP BY status') as $r) {
        $out[(string)$r['status']] = (int)$r['n'];
    }
    $out['ergebnisse'] = (int)db_val('SELECT COUNT(*) FROM datei_ki', [], 0);
    return $out;
}

/** Woran es scheitert, gezählt: [['fehler' => …, 'n' => …], …] */
function ki_fehler_gruende(): array
{
    return db_all("SELECT fehler, COUNT(*) AS n FROM ki_auftraege WHERE status = 'fehler' GROUP BY fehler ORDER BY n DESC, fehler");
}

/** Die letzten fehlgeschlagenen Aufträge mit Dateinamen */
function ki_fehler_liste(int $limit = 20): array
{
    $out = [];
    foreach (db_all("SELECT * FROM ki_auftraege WHERE status = 'fehler' ORDER BY erledigt_am DESC, id DESC LIMIT " . max(1, $limit)) as $a) {
        $l = ki_datei_laden((string)$a['typ'], (int)$a['datei_id']);
        $a['name'] = $l ? (string)($l['datei']['titel'] ?: $l['datei']['orig_name']) : '(Datei gelöscht)';
        $a['eltern'] = $l['eltern'] ?? null;
        $out[] = $a;
    }
    return $out;
}

/** Bis wann die Warteschlange ruht (Ollama war nicht erreichbar) – 0 = sie läuft */
function ki_ruhe_bis(): int
{
    return (int)state_get('ki_ruhe_bis', '0');
}

/**
 * Ein paar Aufträge abarbeiten – vom minütlichen Abruf. Höchstens $max
 * Dateien und höchstens etwa $sekunden lang, damit der Abruf nicht hängt.
 * War Ollama zuletzt nicht erreichbar, ruht die Warteschlange KI_RUHE_SEK
 * lang; $trotzRuhe = true (Knopf „Jetzt abarbeiten") übergeht das.
 */
function ki_warteschlange_abarbeiten(int $max = 3, int $sekunden = 50, bool $trotzRuhe = false): array
{
    $r = ['bearbeitet' => 0, 'fertig' => 0, 'uebersprungen' => 0, 'fehler' => 0, 'ruhe' => false, 'meldungen' => []];
    if (!ki_aktiv() || (!$trotzRuhe && ki_ruhe_bis() > time()) || !db_lock('ki_warteschlange')) {
        return $r;
    }
    try {
        // Hängengebliebene (Prozess abgebrochen) nach einer halben Stunde freigeben
        db_exec("UPDATE ki_auftraege SET status = 'offen' WHERE status = 'laeuft' AND gestartet_am < NOW() - INTERVAL 30 MINUTE");
        $start = time();
        $offen = db_all(
            "SELECT * FROM ki_auftraege WHERE status IN ('offen', 'fehler') AND versuche < ? ORDER BY id LIMIT " . max(1, $max),
            [KI_VERSUCHE_MAX]
        );
        foreach ($offen as $a) {
            if (time() - $start > $sekunden) {
                break;
            }
            $typ = (string)$a['typ'];
            if (!ki_modul_aktiv($typ)) {
                continue;   // Ablage inzwischen abgeschaltet – bleibt liegen
            }
            db_exec("UPDATE ki_auftraege SET status = 'laeuft', versuche = versuche + 1, gestartet_am = NOW() WHERE id = ?", [(int)$a['id']]);
            $r['bearbeitet']++;
            try {
                ki_datei_auswerten($typ, (int)$a['datei_id']);
                db_exec("UPDATE ki_auftraege SET status = 'fertig', fehler = '', erledigt_am = NOW() WHERE id = ?", [(int)$a['id']]);
                $r['fertig']++;
            } catch (KiNichtErreichbar $ex) {
                // Rechner schläft oder Netz weg: Auftrag unverbraucht zurück, Versuch nicht zählen, eine Weile Ruhe
                db_exec("UPDATE ki_auftraege SET status = 'offen', versuche = versuche - 1, fehler = ?, gestartet_am = NULL WHERE id = ?",
                    [mb_substr($ex->getMessage(), 0, 500), (int)$a['id']]);
                state_save('ki_ruhe_bis', (string)(time() + KI_RUHE_SEK));
                $r['bearbeitet']--;
                $r['ruhe'] = true;
                $r['meldungen'][] = $ex->getMessage() . ' – die Warteschlange ruht ' . intdiv(KI_RUHE_SEK, 60) . ' Minuten';
                break;
            } catch (KiUebersprungen $ex) {
                // Dauerhafter Grund: gleich ausgeschöpft, damit nichts dreimal vergeblich läuft
                db_exec("UPDATE ki_auftraege SET status = 'fehler', versuche = ?, fehler = ?, erledigt_am = NOW() WHERE id = ?",
                    [KI_VERSUCHE_MAX, mb_substr($ex->getMessage(), 0, 500), (int)$a['id']]);
                $r['uebersprungen']++;
            } catch (Throwable $ex) {
                $text = mb_substr($ex->getMessage(), 0, 500);
                db_exec("UPDATE ki_auftraege SET status = 'fehler', fehler = ?, erledigt_am = NOW() WHERE id = ?", [$text, (int)$a['id']]);
                $r['fehler']++;
                $r['meldungen'][] = KI_TYPEN[$typ]['label'] . ' #' . (int)$a['datei_id'] . ': ' . $text;
            }
        }
    } finally {
        db_unlock('ki_warteschlange');
    }
    return $r;
}

/* ==================================================================== */
/* Ergebnisse                                                            */
/* ==================================================================== */

/** Ergebnisse und offene Aufträge zu mehreren Dateien einer Ablage: ['ergebnisse' => [id => …], 'auftraege' => [id => …]] */
function ki_fuer_dateien(string $typ, array $ids): array
{
    $out = ['ergebnisse' => [], 'auftraege' => []];
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
    if (!$ids || !isset(KI_TYPEN[$typ])) {
        return $out;
    }
    $ph = implode(',', array_fill(0, count($ids), '?'));
    foreach (db_all("SELECT * FROM datei_ki WHERE typ = ? AND datei_id IN ($ph)", array_merge([$typ], $ids)) as $r) {
        $r['tags'] = ki_tags_parsen((string)($r['tags'] ?? ''));
        $out['ergebnisse'][(int)$r['datei_id']] = $r;
    }
    if (ki_aktiv()) {
        foreach (db_all("SELECT datei_id, status, fehler, versuche FROM ki_auftraege WHERE typ = ? AND datei_id IN ($ph)", array_merge([$typ], $ids)) as $a) {
            $out['auftraege'][(int)$a['datei_id']] = $a;
        }
    }
    return $out;
}

function ki_ergebnis_speichern(string $typ, int $dateiId, string $zusammenfassung, array $tags, string $modell, string $quelle): void
{
    db_exec(
        'INSERT INTO datei_ki (typ, datei_id, zusammenfassung, tags, modell, quelle) VALUES (?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE zusammenfassung = VALUES(zusammenfassung), tags = VALUES(tags), modell = VALUES(modell), quelle = VALUES(quelle)',
        [$typ, $dateiId, $zusammenfassung, ki_tags_text($tags), mb_substr($modell, 0, 100), $quelle]
    );
}

/** Von Hand angepasst: Zusammenfassung und Schlagwörter aus dem Formular */
function ki_hand_speichern(string $typ, int $dateiId, string $zusammenfassung, string $tags): void
{
    $alt = db_row('SELECT modell FROM datei_ki WHERE typ = ? AND datei_id = ?', [$typ, $dateiId]);
    ki_ergebnis_speichern($typ, $dateiId, ki_text_kuerzen($zusammenfassung, 2000), ki_tags_parsen($tags), (string)($alt['modell'] ?? ''), 'hand');
}

/** HTML-Baustein unter einer Datei: Zusammenfassung, Schlagwörter, Bearbeiten */
function ki_hinweis_html(string $typ, array $datei, array $kiDaten, bool $darf): string
{
    $id = (int)($datei['id'] ?? 0);
    return render_partial('partials/ki_hinweis', [
        'typ'     => $typ,
        'datei'   => $datei,
        'ki'      => $kiDaten['ergebnisse'][$id] ?? null,
        'auftrag' => $kiDaten['auftraege'][$id] ?? null,
        'darf'    => $darf,
    ]);
}

/* ==================================================================== */
/* Rechte und Zuordnung                                                  */
/* ==================================================================== */

/**
 * Datei samt Umfeld laden: ['datei' => Zeile, 'pfad' => Dateipfad oder null,
 * 'kontext' => Satz für das Modell, 'eltern' => ['text' => …, 'url' => …]] – oder null.
 */
function ki_datei_laden(string $typ, int $id): ?array
{
    switch ($typ) {
        case 'fahrzeug':
            $d = vfile_find($id);
            if (!$d) {
                return null;
            }
            $v = vehicle_find((int)$d['vehicle_id']);
            $kontext = 'Dokument aus der Fahrzeugakte' . ($v ? ' von ' . $v['bezeichnung'] . (!empty($v['kennzeichen']) ? ' (' . $v['kennzeichen'] . ')' : '') : '')
                . (!empty($d['auftrag_nummer']) ? ', Instandsetzungsauftrag ' . $d['auftrag_nummer'] : '');
            $anker = ($d['art'] ?? '') === 'bild' ? ($d['order_id'] ? '#fotos' : '#bilder') : ($d['order_id'] ? '#dateien' : '#dokumente');
            $eltern = $d['order_id']
                ? ['text' => 'Auftrag ' . ($d['auftrag_nummer'] ?? $d['order_id']) . ($v ? ' · ' . $v['bezeichnung'] : ''), 'url' => url('vehicle_order', ['id' => (int)$d['order_id']]) . $anker]
                : ['text' => $v ? (string)$v['bezeichnung'] : 'Fahrzeug', 'url' => url('vehicle', ['id' => (int)$d['vehicle_id']]) . $anker];
            $pfad = vfile_path($d);
            break;
        case 'veranstaltung':
            $d = efile_find($id);
            if (!$d) {
                return null;
            }
            $ev = event_find((int)$d['event_id']);
            $kontext = 'Datei zur Veranstaltung' . ($ev ? ' „' . $ev['titel'] . '"' : '');
            $eltern = ['text' => $ev ? (string)$ev['titel'] : 'Veranstaltung', 'url' => url('event', ['id' => (int)$d['event_id']]) . '#dateien'];
            $pfad = efile_path($d);
            break;
        case 'termin':
            $d = kfile_find($id);
            if (!$d) {
                return null;
            }
            $t = kalender_find((int)$d['entry_id']);
            $kontext = 'Anhang zum Termin' . ($t ? ' „' . $t['titel'] . '"' : '');
            $eltern = ['text' => $t ? (string)$t['titel'] : 'Termin', 'url' => url('kalender_termin', ['id' => (int)$d['entry_id']]) . '#dateien'];
            $pfad = kfile_path($d);
            break;
        case 'standort':
            $d = standort_bild_find($id);
            if (!$d) {
                return null;
            }
            $s = standort_find((int)$d['standort_id']);
            $kontext = 'Bild des Stell- oder Lagerplatzes' . ($s ? ' „' . $s['name'] . '"' : '');
            $eltern = ['text' => $s ? (string)$s['name'] : 'Platz', 'url' => (can('admin') ? url('admin_standort_edit', ['id' => (int)$d['standort_id']]) : url('standorte', ['id' => (int)$d['standort_id']])) . '#bilder'];
            $pfad = standort_bild_pfad($d);
            break;
        case 'stan':
            $d = stan_datei_find($id);
            if (!$d) {
                return null;
            }
            $ordner = str_replace('\\', '/', dirname((string)$d['pfad']));
            $kontext = 'Dokument aus der STAN (Stärke- und Ausstattungsnachweisung), Version ' . (int)$d['version_nummer'] . ($ordner !== '.' && $ordner !== '' ? ', Ordner „' . $ordner . '"' : '');
            $eltern = ['text' => 'STAN Version ' . (int)$d['version_nummer'], 'url' => url('stan', ['version' => (int)$d['version_id']]) . '#dateien'];
            $pfad = stan_datei_pfad($d);
            // Für den Rest wie die anderen Ablagen: orig_name / titel
            $d['orig_name'] = (string)$d['name'];
            $d['titel'] = '';
            break;
        case 'newsletter':
            $d = newsletter_find($id);
            if (!$d) {
                return null;
            }
            $kontext = 'Newsletter des Ortsverbands „' . (string)$d['titel'] . '" – bitte für die Verwaltung zusammenfassen: Themen, Termine, Aufrufe';
            $eltern = ['text' => 'Newsletter ' . (string)$d['titel'], 'url' => url('newsletter_detail', ['id' => (int)$d['id']]) . '#ki'];
            $pfad = newsletter_pfad($d);
            $d['titel'] = (string)$d['titel'];
            $d['orig_name'] = (string)($d['orig_name'] ?: 'newsletter.pdf');
            break;
        default:
            return null;
    }
    $kontext .= '. Dateiname: ' . (string)$d['orig_name'] . (!empty($d['titel']) && $d['titel'] !== pathinfo((string)$d['orig_name'], PATHINFO_FILENAME) ? ', Titel: ' . $d['titel'] : '');
    return ['datei' => $d, 'pfad' => $pfad, 'kontext' => $kontext, 'eltern' => $eltern];
}

function ki_darf_sehen(string $typ, array $datei, array $user): bool
{
    switch ($typ) {
        case 'fahrzeug':
            return can('view_vehicles');
        case 'veranstaltung':
            return can('view_events');
        case 'termin':
            $t = kalender_find((int)($datei['entry_id'] ?? 0));
            return $t !== null && kalender_sichtbar($t, $user);
        case 'standort':
        case 'stan':
            return true;
        case 'newsletter':
            return can('view_newsletter');
    }
    return false;
}

function ki_darf_bearbeiten(string $typ, array $datei, array $user): bool
{
    switch ($typ) {
        case 'fahrzeug':
            return can('manage_vehicles');
        case 'veranstaltung':
            return can('manage_events');
        case 'termin':
            $t = kalender_find((int)($datei['entry_id'] ?? 0));
            return $t !== null && kalender_darf_bearbeiten($t, $user);
        case 'standort':
            return can('admin');
        case 'stan':
            return in_array((string)($user['role'] ?? ''), ['admin', 'leitung'], true);
        case 'newsletter':
            return can('manage_newsletter');
    }
    return false;
}

/* ==================================================================== */
/* Suche                                                                 */
/* ==================================================================== */

/** Dateien nach Schlagwort oder Wort in der Zusammenfassung – nur, was der Benutzer sehen darf */
function ki_suche(string $q, array $user, int $limit = 40): array
{
    $q = trim($q);
    if ($q === '') {
        return [];
    }
    $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q) . '%';
    $out = [];
    foreach (db_all('SELECT * FROM datei_ki WHERE tags LIKE ? OR zusammenfassung LIKE ? ORDER BY geaendert_am DESC, id DESC LIMIT ' . ($limit * 2), [$like, $like]) as $r) {
        $typ = (string)$r['typ'];
        $l = isset(KI_TYPEN[$typ]) ? ki_datei_laden($typ, (int)$r['datei_id']) : null;
        if (!$l || !ki_darf_sehen($typ, $l['datei'], $user)) {
            continue;
        }
        $r['tags'] = ki_tags_parsen((string)$r['tags']);
        $out[] = [
            'typ'    => $typ,
            'label'  => KI_TYPEN[$typ]['label'],
            'datei'  => $l['datei'],
            'ki'     => $r,
            'link'   => url(KI_TYPEN[$typ]['route'], ['id' => (int)$r['datei_id']]),
            'eltern' => $l['eltern'],
        ];
        if (count($out) >= $limit) {
            break;
        }
    }
    return $out;
}

/** Die häufigsten Schlagwörter: [['tag' => …, 'n' => …], …]. Reine Zählung über die Tag-Texte. */
function ki_tags_zaehlen(array $tagTexte, int $limit = 30): array
{
    $zaehler = [];
    $schreibweise = [];
    foreach ($tagTexte as $text) {
        foreach (ki_tags_parsen((string)$text) as $t) {
            $k = mb_strtolower($t);
            $zaehler[$k] = ($zaehler[$k] ?? 0) + 1;
            $schreibweise[$k] ??= $t;
        }
    }
    arsort($zaehler);
    $out = [];
    foreach (array_slice($zaehler, 0, $limit, true) as $k => $n) {
        $out[] = ['tag' => $schreibweise[$k], 'n' => $n];
    }
    return $out;
}

function ki_haeufige_tags(int $limit = 30): array
{
    return ki_tags_zaehlen(array_column(db_all('SELECT tags FROM datei_ki ORDER BY id DESC LIMIT 2000'), 'tags'), $limit);
}

/* ==================================================================== */
/* Reine Helfer: Schlagwörter, Antwort, Prompt, Text                     */
/* ==================================================================== */

/** „a, b; c" → ['a', 'b', 'c'] – getrimmt, ohne Dubletten (Groß/Klein egal), höchstens 10 à 40 Zeichen */
function ki_tags_parsen(string $s): array
{
    $out = [];
    foreach (preg_split('/[,;\n#]+/u', $s) ?: [] as $t) {
        $t = trim((string)preg_replace('/\s+/u', ' ', trim($t, " \t\r\"'„“”.")));
        if ($t === '') {
            continue;
        }
        $t = mb_substr($t, 0, 40);
        $k = mb_strtolower($t);
        if (!isset($out[$k])) {
            $out[$k] = $t;
        }
        if (count($out) >= 10) {
            break;
        }
    }
    return array_values($out);
}

function ki_tags_text(array $tags): string
{
    return implode(', ', $tags);
}

/** Leerraum glätten, Zeilenumbrüche vereinheitlichen, kaputte Bytes entfernen, kürzen */
function ki_text_kuerzen(string $t, int $max): string
{
    if (!mb_check_encoding($t, 'UTF-8')) {
        $t = (string)mb_convert_encoding($t, 'UTF-8', 'UTF-8');
    }
    $t = (string)preg_replace("/\r\n?/", "\n", $t);
    $t = (string)preg_replace('/[ \t\x{00A0}]+/u', ' ', $t);
    $t = (string)preg_replace("/ *\n */", "\n", $t);
    $t = trim((string)preg_replace("/\n{3,}/", "\n\n", $t));
    return mb_strlen($t) > $max ? rtrim(mb_substr($t, 0, $max)) . ' …' : $t;
}

/** Die Bitte an das Modell. Ohne Text wird das mitgeschickte Bild beschrieben. Reine Funktion. */
function ki_prompt(string $kontext, string $text): string
{
    $kopf = "Du hilfst beim Archiv eines THW-Ortsverbands. Antworte auf Deutsch.\n"
        . "Kontext: $kontext\n\n"
        . "Antworte ausschließlich mit einem JSON-Objekt dieser Form:\n";
    if ($text !== '') {
        return $kopf
            . '{"zusammenfassung": "2 bis 4 Sätze: worum es geht, mit den wichtigsten Angaben wie Datum, Beträge, Namen, Nummern", '
            . '"schlagwoerter": ["5 bis 8 kurze Schlagwörter: Dokumentart, Thema, Gegenstand, Fahrzeug, Werkstatt oder Firma", "einzelne Wörter oder kurze Begriffe, keine Sätze", "keine Daten, Beträge, Kennzeichen oder Nummern – die gehören in die Zusammenfassung"]}' . "\n\n"
            . "Inhalt:\n\"\"\"\n$text\n\"\"\"";
    }
    return $kopf
        . '{"zusammenfassung": "2 bis 3 Sätze: was auf dem Bild zu sehen ist – Ort, Raum, Fahrzeuge, Geräte, Zustand, lesbarer Text auf Schildern oder Kennzeichen", '
        . '"schlagwoerter": ["5 bis 8 Schlagwörter aus je einem Wort oder zwei Wörtern: Gegenstände, Fahrzeugart, Raumart, Zustand"]}' . "\n\n"
        . 'Nenne nur, was tatsächlich zu sehen ist. Keine Sätze darüber, was fehlt oder nicht erkennbar ist. Keine Wiederholungen.';
}

/**
 * Die Antwort des Modells lesen: JSON herausziehen (auch aus ```-Zäunen oder
 * mit Geplauder drumherum), Felder prüfen. null = nicht verwertbar. Reine Funktion.
 */
function ki_antwort_parsen(string $roh): ?array
{
    $roh = trim($roh);
    $a = strpos($roh, '{');
    $z = strrpos($roh, '}');
    if ($a === false || $z === false || $z < $a) {
        return null;
    }
    $d = json_decode(substr($roh, $a, $z - $a + 1), true);
    if (!is_array($d)) {
        return null;
    }
    $zf = $d['zusammenfassung'] ?? $d['summary'] ?? '';
    $tags = $d['schlagwoerter'] ?? $d['schlagwörter'] ?? $d['tags'] ?? $d['keywords'] ?? [];
    if (is_array($tags)) {
        $tags = implode(', ', array_map(static fn($t) => is_scalar($t) ? (string)$t : '', $tags));
    }
    // Daten, Zahlen und Beträge taugen nicht als Schlagwort – die stehen in der Zusammenfassung
    // … und Satzfetzen aus vier und mehr Wörtern auch nicht
    $tags = array_values(array_filter(ki_tags_parsen(is_scalar($tags) ? (string)$tags : ''),
        static fn(string $t) => !preg_match('/^[\d.,:\/ €$%-]+(\s?(eur|euro|uhr|kg|km|l|h))?$/iu', $t) && count(preg_split('/\s+/u', $t)) <= 3));
    $zf = ki_text_kuerzen(is_scalar($zf) ? (string)$zf : '', 2000);
    if ($zf === '' && !$tags) {
        return null;
    }
    return ['zusammenfassung' => $zf, 'tags' => $tags];
}

/** Ist das Modell in der Liste von Ollama? Genau oder bis auf „:latest". Reine Funktion. */
function ki_modell_vorhanden(string $modell, array $modelle): bool
{
    $modell = strtolower(trim($modell));
    if ($modell === '') {
        return false;
    }
    foreach ($modelle as $m) {
        $m = strtolower((string)$m);
        if ($m === $modell || $m === $modell . ':latest' || $m . ':latest' === $modell) {
            return true;
        }
    }
    return false;
}

/* ==================================================================== */
/* Text aus Dateien                                                      */
/* ==================================================================== */

/** Programm im PATH finden (pdftotext, pdftoppm) */
function ki_programm_finden(string $name): ?string
{
    foreach (array_merge(explode(PATH_SEPARATOR, (string)getenv('PATH')), ['/usr/bin', '/usr/local/bin']) as $dir) {
        if ($dir === '') {
            continue;
        }
        foreach ([$name, $name . '.exe'] as $n) {
            $p = rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . $n;
            if (is_file($p) && is_executable($p)) {
                return $p;
            }
        }
    }
    return null;
}

/** Text aus einem PDF (die ersten 20 Seiten). null = pdftotext fehlt. */
function ki_pdf_text(string $pfad): ?string
{
    $prog = ki_programm_finden('pdftotext');
    if ($prog === null) {
        return null;
    }
    $still = DIRECTORY_SEPARATOR === '\\' ? 'NUL' : '/dev/null';
    $out = shell_exec(escapeshellarg($prog) . ' -layout -l 20 -enc UTF-8 ' . escapeshellarg($pfad) . ' - 2>' . $still);
    return is_string($out) ? $out : '';
}

/** Erste Seite eines PDFs als PNG (für das Vision-Modell). null = pdftoppm fehlt oder scheitert. */
function ki_pdf_bild(string $pfad): ?string
{
    $prog = ki_programm_finden('pdftoppm');
    if ($prog === null) {
        return null;
    }
    $basis = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ovb_ki_' . bin2hex(random_bytes(6));
    $still = DIRECTORY_SEPARATOR === '\\' ? 'NUL' : '/dev/null';
    shell_exec(escapeshellarg($prog) . ' -png -r 110 -f 1 -l 1 -singlefile ' . escapeshellarg($pfad) . ' ' . escapeshellarg($basis) . ' 2>' . $still);
    return is_file($basis . '.png') ? $basis . '.png' : null;
}

/** Text aus Office-XML (docx, xlsx, pptx, odt, ods, odp): Absätze und Zellen getrennt, Tags weg. Reine Funktion. */
function ki_xml_text(string $xml): string
{
    $xml = (string)preg_replace('#</(w:p|text:p|text:h|a:p|si|text:list-item)>#', "\n", $xml);
    $xml = (string)preg_replace('#<(w:tab|text:tab|w:br|text:line-break)\b[^>]*/?>#', "\t", $xml);
    $xml = (string)preg_replace('#</(w:tc|table:table-cell|a:tc)>#', "\t", $xml);
    return html_entity_decode(strip_tags($xml), ENT_QUOTES | ENT_XML1, 'UTF-8');
}

/** Text aus einer ZIP-basierten Office-Datei. null = ZIP-Erweiterung fehlt oder Datei kaputt. */
function ki_zip_text(string $pfad, string $endung): ?string
{
    if (!class_exists('ZipArchive')) {
        return null;
    }
    $z = new ZipArchive();
    if ($z->open($pfad) !== true) {
        return null;
    }
    $teile = match ($endung) {
        'docx'              => ['word/document.xml'],
        'xlsx'              => ['xl/sharedStrings.xml'],
        'odt', 'ods', 'odp' => ['content.xml'],
        default             => [],
    };
    if ($endung === 'pptx') {
        for ($i = 0; $i < $z->numFiles; $i++) {
            $n = (string)$z->getNameIndex($i);
            if (preg_match('#^ppt/slides/slide\d+\.xml$#', $n)) {
                $teile[] = $n;
            }
        }
        natsort($teile);
    }
    $text = '';
    foreach ($teile as $t) {
        $x = $z->getFromName($t);
        if (is_string($x)) {
            $text .= ki_xml_text($x) . "\n";
        }
    }
    $z->close();
    return $text;
}

/**
 * Was bekommt das Modell? ['text' => Text oder null, 'bild' => Bildpfad oder null, 'tmp' => aufzuräumende Datei oder null]
 * Wirft KiException, wenn die Dateiart nicht lesbar ist.
 */
function ki_text_aus_datei(string $pfad, string $mime, string $name): array
{
    $endung = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    $mime = strtolower($mime);
    if (str_starts_with($mime, 'image/') || in_array($endung, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) {
        return ['text' => null, 'bild' => $pfad, 'tmp' => null];
    }
    if ($mime === 'application/pdf' || $endung === 'pdf') {
        $t = ki_pdf_text($pfad);
        if ($t === null) {
            throw new KiUebersprungen('pdftotext fehlt auf dem Rechner – PDFs können nicht gelesen werden.');
        }
        if (mb_strlen(trim($t)) >= 40) {
            return ['text' => $t, 'bild' => null, 'tmp' => null];
        }
        // Kaum Text: wohl gescannt – die erste Seite als Bild
        $bild = ki_pdf_bild($pfad);
        return ['text' => trim($t) !== '' ? $t : null, 'bild' => $bild, 'tmp' => $bild];
    }
    if (in_array($endung, ['docx', 'xlsx', 'pptx', 'odt', 'ods', 'odp'], true)) {
        $t = ki_zip_text($pfad, $endung);
        if ($t === null) {
            throw new KiUebersprungen('Die Office-Datei ließ sich nicht öffnen (ZIP-Erweiterung oder Datei beschädigt).');
        }
        return ['text' => $t, 'bild' => null, 'tmp' => null];
    }
    if (in_array($endung, ['txt', 'csv', 'md'], true) || str_starts_with($mime, 'text/')) {
        return ['text' => (string)file_get_contents($pfad), 'bild' => null, 'tmp' => null];
    }
    throw new KiUebersprungen('Diese Dateiart wird nicht ausgewertet (' . ($endung ?: $mime) . ').');
}

/* ==================================================================== */
/* Ollama                                                                */
/* ==================================================================== */

/**
 * Anfrage an Ollama. Die Prüfungen hängen über $GLOBALS['ki_http_attrappe']
 * eine Attrappe ein; echte Aufrufe gehen über cURL, zur Not über den Stream-Wrapper.
 */
function ki_http(string $methode, string $pfad, ?array $daten, int $timeout): array
{
    if (isset($GLOBALS['ki_http_attrappe']) && is_callable($GLOBALS['ki_http_attrappe'])) {
        return ($GLOBALS['ki_http_attrappe'])($methode, $pfad, $daten);
    }
    $url = ki_url();
    if ($url === '') {
        throw new KiException('Keine gültige Ollama-Adresse eingetragen.');
    }
    $koerper = $daten === null ? null : (string)json_encode($daten, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $code = 0;
    if (function_exists('curl_init')) {
        $ch = curl_init($url . $pfad);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Accept: application/json'],
            CURLOPT_USERAGENT      => 'OV-Multitool/' . app_version(),
            CURLOPT_PROTOCOLS      => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_FOLLOWLOCATION => false,
        ]);
        if ($koerper !== null) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $koerper);
        }
        $roh = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $fehler = curl_error($ch);
        curl_close($ch);
        if ($roh === false) {
            // Verbindung kam gar nicht zustande (Rechner schläft, Netz weg) – oder Ollama hat zu lange gerechnet
            $verbindung = $code === 0 && (stripos((string)$fehler, 'connect') !== false || stripos((string)$fehler, 'resolve') !== false || stripos((string)$fehler, 'refused') !== false);
            if ($verbindung) {
                throw new KiNichtErreichbar('Ollama ist nicht erreichbar: ' . ($fehler ?: 'keine Verbindung'));
            }
            throw new KiException('Ollama hat nicht rechtzeitig geantwortet (Zeitlimit ' . $timeout . ' s): ' . ($fehler ?: 'Zeitüberschreitung'));
        }
    } else {
        $ctx = stream_context_create(['http' => [
            'method'        => $methode,
            'header'        => "Content-Type: application/json\r\nAccept: application/json\r\n",
            'content'       => $koerper ?? '',
            'timeout'       => $timeout,
            'ignore_errors' => true,
        ]]);
        $roh = @file_get_contents($url . $pfad, false, $ctx);
        if ($roh === false) {
            throw new KiNichtErreichbar('Ollama ist nicht erreichbar.');
        }
        foreach ($http_response_header ?? [] as $h) {
            if (preg_match('#^HTTP/\S+\s+(\d+)#', (string)$h, $m)) {
                $code = (int)$m[1];
            }
        }
    }
    $d = json_decode((string)$roh, true);
    if (!is_array($d)) {
        throw new KiException('Ollama antwortete nicht mit JSON (HTTP ' . $code . ') – stimmt die Adresse?');
    }
    if ($code >= 400 || isset($d['error'])) {
        throw new KiException('Ollama meldet: ' . (string)($d['error'] ?? ('HTTP ' . $code)));
    }
    return $d;
}

/** Erreichbar? Welche Modelle gibt es? ['ok' => bool, 'fehler' => …, 'modelle' => [...]] */
function ki_verbindung_testen(): array
{
    try {
        $d = ki_http('GET', '/api/tags', null, 15);
    } catch (KiException $e) {
        return ['ok' => false, 'fehler' => $e->getMessage(), 'modelle' => []];
    }
    $modelle = [];
    foreach ((array)($d['models'] ?? []) as $m) {
        if (is_array($m) && !empty($m['name'])) {
            $modelle[] = (string)$m['name'];
        }
    }
    return ['ok' => true, 'fehler' => '', 'modelle' => $modelle];
}

/** Eine Antwort vom Modell holen – mit Bild als Base64, wenn eines mitkommt */
function ki_generieren(string $modell, string $prompt, ?string $bildBase64 = null, bool $json = true): string
{
    $daten = [
        'model'   => $modell,
        'prompt'  => $prompt,
        'stream'  => false,
        // num_predict: Schluss nach gut 400 Wörtern; repeat_penalty: gegen Schleifen („token repeat limit reached")
        'options' => ['temperature' => 0.2, 'num_predict' => 600, 'repeat_penalty' => 1.15],
    ];
    if ($json) {
        $daten['format'] = 'json';
    }
    if ($bildBase64 !== null) {
        $daten['images'] = [$bildBase64];
    }
    $d = ki_http('POST', '/api/generate', $daten, ki_timeout());
    return (string)($d['response'] ?? '');
}

/**
 * Freier Text vom Textmodell – etwa die Zusammenfassung eines Berichts.
 * Nur wenn die KI eingeschaltet ist und nicht gerade ruht; wirft KiException.
 */
function ki_text_antwort(string $prompt, int $maxZeichen = 6000): string
{
    if (!ki_aktiv()) {
        throw new KiException('Die KI-Auswertung ist ausgeschaltet.');
    }
    if (ki_ruhe_bis() > time()) {
        throw new KiException('Ollama war zuletzt nicht erreichbar – die KI ruht noch.');
    }
    $modell = trim((string)setting('ki_modell', ''));
    if ($modell === '') {
        throw new KiException('Kein Textmodell eingetragen.');
    }
    return trim(ki_generieren($modell, ki_text_kuerzen($prompt, $maxZeichen), null, false));
}

/**
 * Eine Datei auswerten und das Ergebnis speichern. Gibt ['zusammenfassung', 'tags', 'modell', 'quelle'] zurück,
 * wirft KiException mit einem Grund für den Benutzer.
 */
function ki_datei_auswerten(string $typ, int $id): array
{
    $l = ki_datei_laden($typ, $id);
    if ($l === null) {
        throw new KiUebersprungen('Die Datei gibt es nicht mehr.');
    }
    if ($l['pfad'] === null) {
        throw new KiUebersprungen('Die Datei fehlt in der Ablage.');
    }
    $q = ki_text_aus_datei($l['pfad'], (string)$l['datei']['mime'], (string)$l['datei']['orig_name']);
    try {
        $text = $q['text'] !== null ? ki_text_kuerzen((string)$q['text'], max(1000, setting_int('ki_max_zeichen', 12000))) : '';
        $modell = trim((string)setting('ki_modell', ''));
        $vision = ki_vision_modell();
        if ($text === '') {
            if ($q['bild'] === null) {
                throw new KiUebersprungen('Kein lesbarer Text in der Datei – vermutlich gescannt. Dafür ist ein Vision-Modell und pdftoppm nötig.');
            }
            if ($vision === '') {
                throw new KiUebersprungen('Bilder werden nur mit einem Vision-Modell ausgewertet (Einstellung „Vision-Modell").');
            }
            if ((int)filesize($q['bild']) > KI_BILD_MAX_BYTES) {
                throw new KiUebersprungen('Das Bild ist zu groß für die Auswertung (über ' . bytes_human(KI_BILD_MAX_BYTES) . ').');
            }
            $antwort = ki_generieren($vision, ki_prompt($l['kontext'], ''), base64_encode((string)file_get_contents($q['bild'])));
            $benutzt = $vision;
            $quelle = 'bild';
        } else {
            if ($modell === '') {
                throw new KiException('Kein Textmodell eingetragen.');
            }
            $antwort = ki_generieren($modell, ki_prompt($l['kontext'], $text));
            $benutzt = $modell;
            $quelle = 'text';
        }
    } finally {
        if (!empty($q['tmp']) && is_file($q['tmp'])) {
            @unlink($q['tmp']);
        }
    }
    $e = ki_antwort_parsen($antwort);
    if ($e === null) {
        throw new KiException('Die Antwort des Modells war nicht verwertbar: ' . mb_substr(trim($antwort), 0, 120));
    }
    ki_ergebnis_speichern($typ, $id, $e['zusammenfassung'], $e['tags'], $benutzt, $quelle);
    return $e + ['modell' => $benutzt, 'quelle' => $quelle];
}
