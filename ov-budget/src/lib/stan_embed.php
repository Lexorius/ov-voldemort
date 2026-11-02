<?php
declare(strict_types=1);

/**
 * Bedeutungssuche in der STAN: Die Texte der Dateien werden in Absätze
 * geteilt, jeder Absatz bekommt über ein Embedding-Modell in Ollama einen
 * Vektor. Eine Frage bekommt denselben Vektor, und die Absätze, die ihr am
 * nächsten liegen, sind die Treffer – auch wenn dort „Mobilfunk-
 * Teilnehmerkarte" statt „SIM-Karte" steht.
 *
 * Der minütliche Abruf trägt die Vektoren nach. Ohne Embedding-Modell
 * bleibt es bei der Stichwortsuche. Alles läuft über Ollama im eigenen Netz.
 */

const STAN_ABSATZ_ZEICHEN = 1200;     // Zielgröße eines Absatzes
const STAN_ABSATZ_UEBERLAPP = 150;    // so viel vom vorigen Absatz wiederholt sich, damit nichts an der Grenze verloren geht
const STAN_EMBED_JE_ABRUF = 32;       // Absätze je Abruf an Ollama
const STAN_EMBED_DATEIEN_JE_ABRUF = 5;
const STAN_EMBED_TREFFER = 40;        // beste Absätze je Frage

function stan_embed_modell(): string
{
    return trim((string)setting('ki_embedding_modell', ''));
}

function stan_embed_aktiv(): bool
{
    return function_exists('ki_aktiv') && ki_aktiv() && stan_embed_modell() !== '';
}

/* ==================================================================== */
/*  Reine Funktionen                                                      */
/* ==================================================================== */

/**
 * Text in Absätze teilen: an Leerzeilen, dann an Satzenden, zur Not hart.
 * Jeder Absatz beginnt mit dem Ende des vorigen (Überlappung). Reine Funktion.
 */
function stan_absaetze_teilen(string $text, int $laenge = STAN_ABSATZ_ZEICHEN, int $ueberlapp = STAN_ABSATZ_UEBERLAPP): array
{
    $text = trim((string)preg_replace("/[ \t\x{00A0}]+/u", ' ', str_replace(["\r\n", "\r"], "\n", $text)));
    if ($text === '') {
        return [];
    }
    // Stücke: Leerzeilen trennen, zu lange Stücke an Satzenden oder Leerzeichen weiter teilen
    $stuecke = [];
    foreach (preg_split("/\n\s*\n/u", $text) ?: [] as $block) {
        $block = trim((string)preg_replace('/\s*\n\s*/u', ' ', $block));
        if ($block === '') {
            continue;
        }
        while (mb_strlen($block) > $laenge) {
            $schnitt = 0;
            foreach (['. ', '! ', '? ', '; ', ' '] as $trenner) {
                $pos = mb_strrpos(mb_substr($block, 0, $laenge), $trenner);
                if ($pos !== false && $pos > intdiv($laenge, 3)) {
                    $schnitt = $pos + mb_strlen($trenner);
                    break;
                }
            }
            if ($schnitt === 0) {
                $schnitt = $laenge;
            }
            $stuecke[] = trim(mb_substr($block, 0, $schnitt));
            $block = trim(mb_substr($block, $schnitt));
        }
        if ($block !== '') {
            $stuecke[] = $block;
        }
    }
    // Stücke zu Absätzen bündeln, bis die Zielgröße erreicht ist
    $absaetze = [];
    $aktuell = '';
    foreach ($stuecke as $s) {
        if ($aktuell !== '' && mb_strlen($aktuell) + 1 + mb_strlen($s) > $laenge) {
            $absaetze[] = $aktuell;
            $aktuell = '';
        }
        $aktuell = $aktuell === '' ? $s : $aktuell . "\n" . $s;
    }
    if ($aktuell !== '') {
        $absaetze[] = $aktuell;
    }
    // Überlappung: das Ende des vorigen Absatzes vorn anhängen
    if ($ueberlapp > 0) {
        for ($i = count($absaetze) - 1; $i >= 1; $i--) {
            $schwanz = mb_substr($absaetze[$i - 1], -$ueberlapp);
            $leer = mb_strpos($schwanz, ' ');
            if ($leer !== false) {
                $schwanz = mb_substr($schwanz, $leer + 1);
            }
            $absaetze[$i] = '… ' . $schwanz . "\n" . $absaetze[$i];
        }
    }
    return $absaetze;
}

/** Vektor auf Länge 1 bringen und als Binärfolge packen. Reine Funktion. */
function stan_vektor_packen(array $v): string
{
    $norm = 0.0;
    foreach ($v as $x) {
        $norm += (float)$x * (float)$x;
    }
    $norm = sqrt($norm) ?: 1.0;
    $out = [];
    foreach ($v as $x) {
        $out[] = (float)$x / $norm;
    }
    return pack('f*', ...$out);
}

function stan_vektor_entpacken(string $b): array
{
    return $b === '' ? [] : array_values(unpack('f*', $b) ?: []);
}

/** Skalarprodukt – bei Einheitsvektoren der Kosinus. Reine Funktion. */
function stan_vektor_punkt(array $a, array $b): float
{
    $n = min(count($a), count($b));
    $s = 0.0;
    for ($i = 0; $i < $n; $i++) {
        $s += $a[$i] * $b[$i];
    }
    return $s;
}

/* ==================================================================== */
/*  Ollama                                                                */
/* ==================================================================== */

/** Vektoren für mehrere Texte in einem Aufruf. Wirft KiException. */
function stan_embed_abfragen(array $texte): array
{
    $modell = stan_embed_modell();
    if ($modell === '') {
        throw new KiException('Kein Embedding-Modell eingetragen.');
    }
    $d = ki_http('POST', '/api/embed', ['model' => $modell, 'input' => array_values($texte), 'truncate' => true], ki_timeout());
    $v = $d['embeddings'] ?? null;
    if (!is_array($v) || count($v) !== count($texte)) {
        throw new KiException('Ollama lieferte keine passenden Vektoren – unterstützt das Modell „' . $modell . '" Embeddings?');
    }
    return $v;
}

/* ==================================================================== */
/*  Index pflegen                                                         */
/* ==================================================================== */

/** Wie weit der Index ist: absaetze, offen (ohne Vektor), dateien_ohne (mit Text, aber ohne Absätze), fehler */
function stan_embed_stand(): array
{
    return [
        'absaetze'     => (int)db_val('SELECT COUNT(*) FROM stan_absaetze', [], 0),
        'offen'        => (int)db_val('SELECT COUNT(*) FROM stan_absaetze WHERE vektor IS NULL', [], 0),
        'dateien_ohne' => (int)db_val("SELECT COUNT(*) FROM stan_dateien d WHERE d.text IS NOT NULL AND d.text <> '' AND NOT EXISTS (SELECT 1 FROM stan_absaetze a WHERE a.datei_id = d.id)", [], 0),
        'fehler'       => state_get('stan_embed_fehler', ''),
    ];
}

/** Absätze einer Datei neu anlegen (ohne Vektoren) – etwa, wenn ihr Text neu gelesen wurde */
function stan_absaetze_anlegen(int $dateiId, string $text): int
{
    db_exec('DELETE FROM stan_absaetze WHERE datei_id = ?', [$dateiId]);
    $n = 0;
    foreach (stan_absaetze_teilen($text) as $i => $absatz) {
        db_insert('stan_absaetze', ['datei_id' => $dateiId, 'nr' => $i + 1, 'text' => $absatz]);
        $n++;
    }
    return $n;
}

/**
 * Vom minütlichen Abruf: Absätze für neue Texte anlegen, dann ein paar
 * Vektoren holen. Rückgabe: dateien, absaetze, fehler, ruhe.
 */
function stan_embed_nachholen(int $max = STAN_EMBED_JE_ABRUF): array
{
    $r = ['dateien' => 0, 'absaetze' => 0, 'fehler' => '', 'ruhe' => false];
    if (!stan_embed_aktiv()) {
        return $r;
    }
    foreach (db_all("SELECT d.id, d.text FROM stan_dateien d WHERE d.text IS NOT NULL AND d.text <> '' AND NOT EXISTS (SELECT 1 FROM stan_absaetze a WHERE a.datei_id = d.id) ORDER BY d.id LIMIT " . STAN_EMBED_DATEIEN_JE_ABRUF) as $d) {
        stan_absaetze_anlegen((int)$d['id'], (string)$d['text']);
        $r['dateien']++;
    }
    if (ki_ruhe_bis() > time()) {
        $r['ruhe'] = true;
        return $r;
    }
    $offen = db_all('SELECT id, text FROM stan_absaetze WHERE vektor IS NULL ORDER BY datei_id, nr LIMIT ' . max(1, $max));
    if (!$offen) {
        return $r;
    }
    try {
        $vektoren = stan_embed_abfragen(array_map(static fn($a) => (string)$a['text'], $offen));
        foreach ($offen as $i => $a) {
            db_update('stan_absaetze', ['vektor' => stan_vektor_packen((array)$vektoren[$i]), 'modell' => stan_embed_modell()], 'id = ?', [(int)$a['id']]);
            $r['absaetze']++;
        }
        state_save('stan_embed_fehler', '');
    } catch (KiNichtErreichbar $ex) {
        state_save('ki_ruhe_bis', (string)(time() + KI_RUHE_SEK));
        $r['ruhe'] = true;
        $r['fehler'] = $ex->getMessage();
    } catch (KiException $ex) {
        state_save('stan_embed_fehler', mb_substr($ex->getMessage(), 0, 500));
        $r['fehler'] = $ex->getMessage();
    }
    return $r;
}

/* ==================================================================== */
/*  Suche                                                                 */
/* ==================================================================== */

/**
 * Die Absätze, die der Frage am nächsten liegen. Wirft KiException, wenn
 * Ollama den Vektor der Frage nicht liefert. Rückgabe je Treffer:
 * id, datei_id, nr, text, score (Kosinus, 0 bis 1).
 */
function stan_embed_suche(string $frage, ?int $versionId = null, int $limit = STAN_EMBED_TREFFER): array
{
    $vFrage = stan_vektor_entpacken(stan_vektor_packen((array)stan_embed_abfragen([$frage])[0]));
    $sql = 'SELECT a.id, a.datei_id, a.nr, a.vektor FROM stan_absaetze a';
    $p = [];
    if ($versionId !== null) {
        $sql .= ' JOIN stan_dateien d ON d.id = a.datei_id AND d.version_id = ?';
        $p[] = $versionId;
    }
    $sql .= ' WHERE a.vektor IS NOT NULL';
    $treffer = [];
    foreach (db_all($sql, $p) as $a) {
        $score = stan_vektor_punkt($vFrage, stan_vektor_entpacken((string)$a['vektor']));
        $treffer[] = ['id' => (int)$a['id'], 'datei_id' => (int)$a['datei_id'], 'nr' => (int)$a['nr'], 'score' => $score];
    }
    usort($treffer, static fn($x, $y) => $y['score'] <=> $x['score']);
    $treffer = array_slice($treffer, 0, $limit);
    if (!$treffer) {
        return [];
    }
    $ids = array_column($treffer, 'id');
    $texte = [];
    foreach (db_all('SELECT id, text FROM stan_absaetze WHERE id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')', $ids) as $z) {
        $texte[(int)$z['id']] = (string)$z['text'];
    }
    foreach ($treffer as &$t) {
        $t['text'] = $texte[$t['id']] ?? '';
    }
    unset($t);
    return $treffer;
}
