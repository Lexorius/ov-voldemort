<?php
declare(strict_types=1);

/**
 * Fragen an die STAN: ein kleiner Chat mit dem Textmodell in Ollama.
 *
 * Das Modell bekommt nicht die ganze STAN, sondern das, was ins
 * Kontextfenster passt: Stichwörter aus der Frage, Treffer im Volltext und
 * in den KI-Schlagwörtern, die besten Dateien – ganz, wenn sie ins Fenster
 * passen, sonst die Stellen um die Treffer. Die Antwort soll nur daraus
 * kommen und die Quellen nennen. Alles läuft über Ollama im eigenen Netz;
 * die Texte verlassen das Haus nicht.
 *
 * Abschaltbar: Hauptschalter der KI, Schalter „STAN-Dokumente auswerten"
 * und der eigene Schalter „Fragen an die STAN".
 */

const STAN_CHAT_VERLAUF_MAX = 8;        // so viele Fragen bleiben in der Sitzung
const STAN_CHAT_VERLAUF_PROMPT = 3;     // so viele davon sieht das Modell
const STAN_CHAT_AUSZUG = 700;           // Zeichen je Auszug
const STAN_CHAT_AUSZUEGE_JE_DATEI = 3;
const STAN_CHAT_FRAGE_MAX = 500;
const STAN_CHAT_KANDIDATEN = 60;        // so viele Dateien holt die Suche, bevor bewertet wird
const STAN_CHAT_ZEICHEN_JE_TOKEN = 3;   // deutscher Text in einem 7B-Modell, vorsichtig geschätzt
const STAN_CHAT_RESERVE = 2500;         // Zeichen für Anweisung, Verlauf und Antwort
const STAN_CHAT_DOPPELT_SEK = 180;      // so lange gilt dieselbe Frage als Doppelklick

/** Wörter, die in fast jeder Frage stehen und nichts suchen helfen */
const STAN_CHAT_STOPP = ['der', 'die', 'das', 'des', 'dem', 'den', 'ein', 'eine', 'einer', 'eines', 'einem', 'einen', 'und', 'oder', 'aber',
    'ist', 'sind', 'war', 'waren', 'wird', 'werden', 'hat', 'haben', 'gibt', 'kann', 'können', 'soll', 'sollen', 'muss', 'müssen', 'darf', 'dürfen',
    'wie', 'was', 'wer', 'wo', 'wann', 'warum', 'wieso', 'welche', 'welcher', 'welches', 'welchen', 'viele', 'viel', 'wieviele', 'wieviel',
    'für', 'von', 'mit', 'bei', 'nach', 'vor', 'über', 'unter', 'zum', 'zur', 'zu', 'am', 'im', 'in', 'an', 'auf', 'aus', 'um', 'bis', 'als',
    'nicht', 'auch', 'noch', 'nur', 'man', 'ich', 'wir', 'uns', 'ihr', 'sie', 'es', 'ob', 'dass', 'laut', 'gemäß', 'bitte', 'mal', 'denn',
    'mir', 'mich', 'dich', 'dir', 'ihn', 'ihm', 'ihnen', 'dieser', 'diese', 'dieses', 'jeder', 'jede', 'jedes', 'alle', 'etwas', 'sehr',
    'the', 'and', 'for', 'with', 'how', 'many', 'much', 'what', 'which', 'does', 'are', 'ovs', 'stan', 'steht', 'drin', 'drinnen', 'stehen'];

function stan_chat_aktiv(): bool
{
    return function_exists('ki_modul_aktiv') && ki_modul_aktiv('stan') && setting_bool('ki_stan_chat', true);
}

/** So viele Zeichen Auszüge passen in eine Anfrage – aus dem Kontextfenster des Modells */
function stan_chat_budget(): int
{
    $tokens = function_exists('ki_num_ctx') ? ki_num_ctx() : 8192;
    return max(6000, $tokens * STAN_CHAT_ZEICHEN_JE_TOKEN - STAN_CHAT_RESERVE);
}

/** Wie viele Dateien je Frage: mehr Fenster, mehr Dateien – zwischen vier und zwölf. Reine Funktion. */
function stan_chat_quellen_max(int $budget): int
{
    return max(4, min(12, intdiv($budget, 6000)));
}

/** Wortstamm für die Suche: „Aggregate" und „Aggregat" sollen dasselbe finden. Reine Funktion. */
function stan_chat_stamm(string $wort): string
{
    $w = mb_strtolower(trim($wort));
    if (mb_strlen($w) < 6 || is_numeric($w)) {
        return $w;
    }
    foreach (['ungen', 'ung', 'nen', 'en', 'er', 'es', 'en', 'e', 'n', 's'] as $endung) {
        if (str_ends_with($w, $endung) && mb_strlen($w) - mb_strlen($endung) >= 4) {
            return mb_substr($w, 0, mb_strlen($w) - mb_strlen($endung));
        }
    }
    return $w;
}

/** Stichwörter aus der Frage – ohne Füllwörter, höchstens acht. Reine Funktion. */
function stan_chat_stichworte(string $frage): array
{
    $teile = preg_split('/[^\p{L}\p{N}\-]+/u', mb_strtolower($frage)) ?: [];
    $out = [];
    foreach ($teile as $t) {
        $t = trim($t, '-');
        if ($t === '' || in_array($t, STAN_CHAT_STOPP, true)) {
            continue;
        }
        if (mb_strlen($t) < 3 && !(is_numeric($t) && mb_strlen($t) >= 2)) {
            continue;
        }
        if (!in_array($t, $out, true)) {
            $out[] = $t;
        }
        if (count($out) >= 8) {
            break;
        }
    }
    return $out;
}

/** Wie gut passt ein Text zur Frage: Treffer je Stamm, verschiedene Stämme zählen mehr. Reine Funktion. */
function stan_chat_bewerten(string $text, array $staemme): int
{
    $t = mb_strtolower($text);
    $punkte = 0;
    foreach ($staemme as $s) {
        if ($s === '') {
            continue;
        }
        $n = substr_count($t, $s);
        if ($n > 0) {
            $punkte += min($n, 5) + 3;
        }
    }
    return $punkte;
}

/** Stellen um die Treffer herum, zusammengelegt, wenn sie sich überlappen. Reine Funktion. */
function stan_chat_auszuege(string $text, array $staemme, int $breite = STAN_CHAT_AUSZUG, int $max = STAN_CHAT_AUSZUEGE_JE_DATEI): array
{
    $text = trim((string)preg_replace('/\s+/u', ' ', $text));
    if ($text === '') {
        return [];
    }
    $klein = mb_strtolower($text);
    $laenge = mb_strlen($text);
    $treffer = [];
    foreach ($staemme as $s) {
        if ($s === '') {
            continue;
        }
        $von = 0;
        while (count($treffer) < 24 && ($pos = mb_strpos($klein, $s, $von)) !== false) {
            $treffer[] = $pos;
            $von = $pos + mb_strlen($s);
        }
    }
    if (!$treffer) {
        return [mb_substr($text, 0, $breite) . ($laenge > $breite ? ' …' : '')];
    }
    sort($treffer);
    $fenster = [];
    foreach ($treffer as $pos) {
        $start = max(0, $pos - intdiv($breite, 3));
        $ende = min($laenge, $start + $breite);
        if ($fenster && $start <= $fenster[count($fenster) - 1][1]) {
            $fenster[count($fenster) - 1][1] = max($fenster[count($fenster) - 1][1], $ende);
            continue;
        }
        $fenster[] = [$start, $ende];
    }
    $out = [];
    foreach (array_slice($fenster, 0, $max) as [$start, $ende]) {
        $out[] = ($start > 0 ? '… ' : '') . mb_substr($text, $start, $ende - $start) . ($ende < $laenge ? ' …' : '');
    }
    return $out;
}

/**
 * Das Fenster auf die Quellen verteilen: Was ganz hineinpasst, geht ganz
 * (vollstaendig = true), der Rest als Auszüge um die Treffer. Reine Funktion.
 * Jede Quelle: text (bereinigt), ki (Zusammenfassung oder ''), staemme von außen.
 */
function stan_chat_zuschneiden(array $quellen, array $staemme, int $budget): array
{
    $rest = $budget;
    $out = [];
    $n = count($quellen);
    foreach ($quellen as $i => $q) {
        $anteil = intdiv(max(0, $rest), max(1, $n - $i));
        $text = (string)($q['text'] ?? '');
        $ki = trim((string)($q['ki'] ?? ''));
        $laenge = mb_strlen($text);
        $vollstaendig = $text !== '' && $laenge <= (int)($anteil * 1.4) && $laenge <= $rest;
        if ($vollstaendig) {
            $auszuege = [$text];
            $verbraucht = $laenge;
        } else {
            $auszuege = $text !== '' ? stan_chat_auszuege($text, $staemme) : ($ki !== '' ? [$ki] : []);
            if ($text !== '' && $ki !== '') {
                array_unshift($auszuege, 'Zusammenfassung: ' . mb_substr($ki, 0, 400));
            }
            // Auszüge auf den Anteil stutzen
            $gekuerzt = [];
            $verbraucht = 0;
            foreach ($auszuege as $a) {
                if ($verbraucht + mb_strlen($a) > $anteil) {
                    $platz = $anteil - $verbraucht;
                    if ($platz > 120) {
                        $gekuerzt[] = mb_substr($a, 0, $platz) . ' …';
                        $verbraucht += $platz;
                    }
                    break;
                }
                $gekuerzt[] = $a;
                $verbraucht += mb_strlen($a);
            }
            $auszuege = $gekuerzt;
        }
        $rest -= $verbraucht;
        $q['auszuege'] = $auszuege;
        $q['vollstaendig'] = $vollstaendig;
        unset($q['text'], $q['ki']);
        $out[] = $q;
    }
    return $out;
}

/** Dateien, die zur Frage passen, mit Text oder Auszügen – die besten zuerst, aufs Fenster zugeschnitten */
function stan_chat_quellen(string $frage, ?int $versionId = null, ?int $max = null, ?int $budget = null): array
{
    $woerter = stan_chat_stichworte($frage);
    if (!$woerter) {
        return [];
    }
    $budget ??= stan_chat_budget();
    $max ??= stan_chat_quellen_max($budget);
    $staemme = array_values(array_unique(array_map('stan_chat_stamm', $woerter)));
    $w = [];
    $p = [];
    foreach ($staemme as $s) {
        $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $s) . '%';
        $w[] = '(d.text LIKE ? OR d.name LIKE ? OR k.tags LIKE ? OR k.zusammenfassung LIKE ?)';
        array_push($p, $like, $like, $like, $like);
    }
    $sql = "SELECT d.id, d.version_id, d.pfad, d.name, d.text, v.nummer AS version_nummer, k.zusammenfassung AS ki_zusammenfassung, k.tags AS ki_tags
            FROM stan_dateien d
            JOIN stan_versionen v ON v.id = d.version_id
            LEFT JOIN datei_ki k ON k.typ = 'stan' AND k.datei_id = d.id
            WHERE (" . implode(' OR ', $w) . ')';
    if ($versionId !== null) {
        $sql .= ' AND d.version_id = ?';
        $p[] = $versionId;
    }
    $sql .= ' ORDER BY v.nummer DESC LIMIT ' . STAN_CHAT_KANDIDATEN;

    $quellen = [];
    foreach (db_all($sql, $p) as $r) {
        $text = trim((string)preg_replace('/\s+/u', ' ', (string)($r['text'] ?? '')));
        $ki = trim((string)($r['ki_zusammenfassung'] ?? ''));
        $punkte = stan_chat_bewerten($text . ' ' . $ki . ' ' . (string)$r['name'] . ' ' . (string)($r['ki_tags'] ?? ''), $staemme);
        if ($punkte <= 0) {
            continue;
        }
        $quellen[] = ['id' => (int)$r['id'], 'name' => (string)$r['name'], 'pfad' => (string)$r['pfad'], 'version_nummer' => (int)$r['version_nummer'],
            'punkte' => $punkte, 'text' => $text, 'ki' => $ki];
    }
    usort($quellen, static fn($a, $b) => [$b['punkte'], $b['version_nummer']] <=> [$a['punkte'], $a['version_nummer']]);
    return stan_chat_zuschneiden(array_slice($quellen, 0, $max), $staemme, $budget);
}

/** Die Bitte an das Modell: Quellen mit Nummern, der bisherige Verlauf, die Frage. Reine Funktion. */
function stan_chat_prompt(string $frage, array $quellen, array $verlauf, int $maxZeichen = 9000): string
{
    $kontext = '';
    foreach ($quellen as $i => $q) {
        $block = '[' . ($i + 1) . '] ' . $q['name'] . ($q['pfad'] !== '' && dirname($q['pfad']) !== '.' ? ' (' . dirname($q['pfad']) . ')' : '')
            . ', Version ' . $q['version_nummer'] . (!empty($q['vollstaendig']) ? ', vollständig' : ', Auszüge') . "\n";
        foreach ($q['auszuege'] as $a) {
            $block .= $a . "\n";
        }
        if (mb_strlen($kontext) + mb_strlen($block) > $maxZeichen) {
            $rest = $maxZeichen - mb_strlen($kontext);
            if ($rest > 200) {
                $kontext .= mb_substr($block, 0, $rest) . " …\n";
            }
            break;
        }
        $kontext .= $block . "\n";
    }

    $bisher = '';
    foreach (array_slice($verlauf, -STAN_CHAT_VERLAUF_PROMPT) as $t) {
        $bisher .= 'Frage: ' . mb_substr((string)$t['frage'], 0, 300) . "\nAntwort: " . mb_substr((string)$t['antwort'], 0, 600) . "\n";
    }

    return "Du hilfst einem Ortsverband des THW, seine Stärke- und Ausstattungsnachweisung (STAN) zu durchsuchen.\n"
        . "Beantworte die Frage auf Deutsch, knapp und sachlich, nur anhand der nummerierten Quellen. "
        . "Nenne hinter jeder Aussage die Nummer der Quelle in eckigen Klammern, etwa [2]. "
        . "Steht die Antwort nicht in den Quellen, sage das klar und rate nicht. Zahlen und Bezeichnungen wörtlich übernehmen.\n\n"
        . "Quellen aus der STAN:\n" . ($kontext !== '' ? $kontext : "(keine)\n")
        . ($bisher !== '' ? "\nBisheriger Verlauf:\n" . $bisher : '')
        . "\nFrage: " . trim($frage) . "\nAntwort:";
}

/** Frage bereinigen: Leerraum zusammenziehen, Länge begrenzen. Reine Funktion. */
function stan_chat_frage_normieren(string $frage): string
{
    return mb_substr(trim((string)preg_replace('/\s+/u', ' ', $frage)), 0, STAN_CHAT_FRAGE_MAX);
}

/**
 * Dieselbe Frage kurz hintereinander – ein Doppelklick. Dann die vorhandene
 * Antwort statt einer zweiten Anfrage. Reine Funktion.
 */
function stan_chat_doppelt(string $frage, array $verlauf, ?int $jetzt = null, int $sekunden = STAN_CHAT_DOPPELT_SEK): ?array
{
    $frage = mb_strtolower(stan_chat_frage_normieren($frage));
    if ($frage === '' || !$verlauf) {
        return null;
    }
    $jetzt ??= time();
    $letzte = $verlauf[count($verlauf) - 1];
    $am = strtotime((string)($letzte['am'] ?? '')) ?: 0;
    if (mb_strtolower((string)($letzte['frage'] ?? '')) === $frage && $jetzt - $am <= $sekunden) {
        return $letzte;
    }
    return null;
}

/**
 * Eine Frage beantworten. Rückgabe: frage, antwort, quellen (id, name, pfad,
 * version_nummer, vollstaendig), modell, am, wiederholt. Wirft KiException,
 * wenn die KI nicht kann.
 */
function stan_chat_fragen(string $frage, ?int $versionId, array $verlauf): array
{
    $frage = stan_chat_frage_normieren($frage);
    if ($frage === '') {
        throw new KiException('Bitte eine Frage eingeben.');
    }
    if (!stan_chat_aktiv()) {
        throw new KiException('Fragen an die STAN sind ausgeschaltet (Einstellungen → KI-Auswertung).');
    }
    if (($doppelt = stan_chat_doppelt($frage, $verlauf)) !== null) {
        return ['wiederholt' => true] + $doppelt;
    }
    $budget = stan_chat_budget();
    $quellen = stan_chat_quellen($frage, $versionId, null, $budget);
    $antwort = ['frage' => $frage, 'quellen' => [], 'modell' => '', 'am' => date('Y-m-d H:i:s'), 'wiederholt' => false];
    foreach ($quellen as $q) {
        $antwort['quellen'][] = ['id' => $q['id'], 'name' => $q['name'], 'pfad' => $q['pfad'], 'version_nummer' => $q['version_nummer'], 'vollstaendig' => !empty($q['vollstaendig'])];
    }
    if (!$quellen) {
        $antwort['antwort'] = 'Dazu habe ich in der STAN nichts gefunden. Vielleicht mit anderen Begriffen fragen'
            . ($versionId !== null ? ' oder „alle Versionen" wählen' : '') . '.';
        return $antwort;
    }
    $prompt = stan_chat_prompt($frage, $quellen, $verlauf, $budget);
    $antwort['antwort'] = ki_text_antwort($prompt, $budget + STAN_CHAT_RESERVE);
    $antwort['modell'] = trim((string)setting('ki_modell', ''));
    if ($antwort['antwort'] === '') {
        $antwort['antwort'] = 'Das Modell hat keine Antwort geliefert.';
    }
    return $antwort;
}

/** Verlauf aus der Sitzung, höchstens STAN_CHAT_VERLAUF_MAX Einträge */
function stan_chat_verlauf(): array
{
    $v = $_SESSION['stan_chat'] ?? [];
    return is_array($v) ? array_values($v) : [];
}

function stan_chat_verlauf_merken(array $eintrag): void
{
    if (!empty($eintrag['wiederholt'])) {
        return;
    }
    unset($eintrag['wiederholt']);
    $v = stan_chat_verlauf();
    $v[] = $eintrag;
    $_SESSION['stan_chat'] = array_slice($v, -STAN_CHAT_VERLAUF_MAX);
}

function stan_chat_verlauf_leeren(): void
{
    unset($_SESSION['stan_chat']);
}
