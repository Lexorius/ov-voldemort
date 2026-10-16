<?php
declare(strict_types=1);

/** Verwaltung der KI-Auswertung: Verbindung testen, Warteschlange, Fehler, Nachholen */
$me = require_role('admin');

$fehler = '';
$hinweis = '';
$test = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    switch (post_str('action')) {
        case 'test':
            $test = ki_verbindung_testen();
            if ($test['ok']) {
                $modell = trim((string)setting('ki_modell', ''));
                $vision = trim((string)setting('ki_vision_modell', ''));
                $fehlend = [];
                if ($modell !== '' && !ki_modell_vorhanden($modell, $test['modelle'])) {
                    $fehlend[] = $modell;
                }
                if ($vision !== '' && !ki_modell_vorhanden($vision, $test['modelle'])) {
                    $fehlend[] = $vision;
                }
                $hinweis = sprintf('Ollama antwortet, %d Modell(e) vorhanden.', count($test['modelle']));
                if ($fehlend) {
                    $fehler = 'Nicht auf dem Ollama-Rechner: ' . implode(', ', $fehlend) . ' – dort „ollama pull <Modell>" ausführen.';
                }
            } else {
                $fehler = $test['fehler'];
            }
            audit('ki.test', 'ki', null, $test['ok'] ? 'ok' : $test['fehler']);
            break;

        case 'abarbeiten':
            if (!ki_aktiv()) {
                $fehler = 'Die KI-Auswertung ist ausgeschaltet.';
                break;
            }
            set_time_limit(300);
            $r = ki_warteschlange_abarbeiten(3, 240);
            $hinweis = sprintf('%d Datei(en) bearbeitet: %d fertig, %d übersprungen, %d fehlgeschlagen.', $r['bearbeitet'], $r['fertig'], $r['uebersprungen'], $r['fehler']);
            if ($r['meldungen']) {
                $fehler = implode(' · ', $r['meldungen']);
            }
            audit('ki.abarbeiten', 'ki', null, $hinweis);
            break;

        case 'alle':
            if (!ki_aktiv()) {
                $fehler = 'Die KI-Auswertung ist ausgeschaltet.';
                break;
            }
            $n = ki_alle_einreihen();
            $hinweis = sprintf('%d Datei(en) eingereiht. Der minütliche Abruf arbeitet sie nach und nach ab.', $n)
                . (ki_vision_modell() === '' ? ' Bilder bleiben außen vor, solange kein Vision-Modell eingetragen ist.' : '');
            audit('ki.alle', 'ki', null, (string)$n);
            break;

        case 'erneut':
            $n = ki_erneut_versuchen();
            $hinweis = sprintf('%d fehlgeschlagene(r) Auftrag/Aufträge werden erneut versucht.', $n);
            audit('ki.erneut', 'ki', null, (string)$n);
            break;
    }
}

render('admin/ki', [
    'title'    => 'KI-Auswertung',
    'fehler'   => $fehler,
    'hinweis'  => $hinweis,
    'test'     => $test,
    'status'   => ki_warteschlange_status(),
    'fehlerliste' => ki_fehler_liste(15),
    'gruende'  => ki_fehler_gruende(),
    'aktiv'    => ki_aktiv(),
    'url'      => ki_url(),
    'urlGrund' => ki_url_pruefen((string)setting('ki_ollama_url', '')),
    'pdftotext' => ki_programm_finden('pdftotext') !== null,
    'pdftoppm'  => ki_programm_finden('pdftoppm') !== null,
    'module'   => array_map(static fn(string $typ, array $def) => ['label' => $def['label'], 'an' => setting_bool($def['schalter'], true)], array_keys(KI_TYPEN), KI_TYPEN),
]);
