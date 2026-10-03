<?php
declare(strict_types=1);
/*
 * Budgetplanung: Stand der Buchungen in der Übersicht, geplante Kosten der
 * Veranstaltungen samt Verpflegung, Nebenkosten-Hinweis aus dem Verbrauch.
 */
session_start();
$GLOBALS['settings'] = ['waehrung' => 'EUR', 'budget_modul_name' => 'Budget', 'budget_intro' => '', 'budget_warn_prozent' => '90',
    'budget_rundung' => '0', 'verpflegung_typen' => '', 'verbrauch_modul_name' => 'Verbrauch'];
$GLOBALS['tabellen'] = [];
function db_all(string $sql, array $p = []): array {
    if (str_contains($sql, 'FROM settings')) {
        $r = [];
        foreach ($GLOBALS['settings'] as $k => $v) { $r[] = ['skey'=>$k,'svalue'=>$v,'sgroup'=>'x','stype'=>'text','label'=>'','hint'=>'','sort_order'=>0]; }
        return $r;
    }
    foreach ($GLOBALS['tabellen'] as $marke => $rows) {
        if (str_contains($sql, $marke)) { return is_callable($rows) ? $rows($p) : $rows; }
    }
    return [];
}
function db_row(string $sql, array $p = []): ?array { return null; }
function db_val(string $sql, array $p = [], mixed $d = null) { return $d; }
function db_exec(string $sql, array $p = []): int { return 1; }
function db_insert(string $t, array $d): int { return 1; }
function db_update(string $t, array $d, string $w, array $p): int { return 1; }
function can(string $was, mixed $ctx = null): bool { return true; }
function current_user(): ?array { return ['id' => 1]; }
$app = dirname(__DIR__);
foreach (['util', 'settings', 'lists', 'view', 'wishes', 'order_rights', 'expenses', 'events', 'verbrauch'] as $lib) { require $app . '/src/lib/' . $lib . '.php'; }

$ok = 0; $fail = 0;
$check = function (string $n, bool $c) use (&$ok, &$fail) { if ($c) { $ok++; } else { $fail++; echo "FAIL: $n\n"; } };
$nah = static fn(?float $a, float $b): bool => $a !== null && abs($a - $b) < 0.01;

/* ---------- Geplante Kosten der Veranstaltungen ---------- */
$GLOBALS['tabellen'] = [
    'FROM events' => [
        // Übung mit Verpflegung: 12 Personen geplant, 1 Frühstück, 2 Mittag, 1 Abend; Tagessatz 20 € (20/40/40)
        ['id' => 1, 'titel' => 'Übung Hochwasser', 'status' => 'geplant', 'beginn' => '2026-10-01 17:00:00', 'ende' => '2026-10-03 11:00:00',
         'kosten_geplant' => 300, 'verpflegung' => 1, 'verpflegung_personen' => 12, 'verpflegung_fruehstueck' => 1, 'verpflegung_mittag' => 2, 'verpflegung_abend' => 1,
         'teilnehmer_geplant' => 12, 'typ_id' => null, 'budget_id' => null],
        // Feier ohne Verpflegung, teils gebucht
        ['id' => 2, 'titel' => 'Sommerfest', 'status' => 'geplant', 'beginn' => '2026-07-10 16:00:00', 'ende' => null,
         'kosten_geplant' => 1000, 'verpflegung' => 0, 'verpflegung_personen' => null, 'verpflegung_fruehstueck' => 0, 'verpflegung_mittag' => 0, 'verpflegung_abend' => 0,
         'teilnehmer_geplant' => null, 'typ_id' => null, 'budget_id' => null],
        // abgesagt zählt nicht
        ['id' => 3, 'titel' => 'Abgesagt', 'status' => 'abgesagt', 'beginn' => '2026-08-01 10:00:00', 'ende' => null,
         'kosten_geplant' => 5000, 'verpflegung' => 0, 'verpflegung_personen' => null, 'verpflegung_fruehstueck' => 0, 'verpflegung_mittag' => 0, 'verpflegung_abend' => 0,
         'teilnehmer_geplant' => null, 'typ_id' => null, 'budget_id' => null],
        // ohne Planung zählt nicht
        ['id' => 4, 'titel' => 'Dienstabend', 'status' => 'geplant', 'beginn' => '2026-09-01 19:00:00', 'ende' => null,
         'kosten_geplant' => 0, 'verpflegung' => 0, 'verpflegung_personen' => null, 'verpflegung_fruehstueck' => 0, 'verpflegung_mittag' => 0, 'verpflegung_abend' => 0,
         'teilnehmer_geplant' => null, 'typ_id' => null, 'budget_id' => null],
        // schon mehr gebucht als geplant: Rest null, nicht negativ
        ['id' => 5, 'titel' => 'Ausbildung', 'status' => 'laeuft', 'beginn' => '2026-05-01 09:00:00', 'ende' => null,
         'kosten_geplant' => 100, 'verpflegung' => 0, 'verpflegung_personen' => null, 'verpflegung_fruehstueck' => 0, 'verpflegung_mittag' => 0, 'verpflegung_abend' => 0,
         'teilnehmer_geplant' => null, 'typ_id' => null, 'budget_id' => null],
    ],
    'FROM verpflegung_saetze' => [['id' => 1, 'tagessatz' => 20, 'anteil_fruehstueck' => 20, 'anteil_mittag' => 40, 'anteil_abend' => 40, 'gueltig_von' => '2026-01-01', 'gueltig_bis' => null]],
    'FROM event_guests' => [],
    'FROM expenses' => static fn(array $p) => match ((int)($p[0] ?? 0)) {
        2 => [['art' => 'ausgabe', 'anzahl' => 1, 'brutto' => 400]],
        5 => [['art' => 'ausgabe', 'anzahl' => 2, 'brutto' => 250]],
        default => [],
    },
];
$g = events_geplante_kosten(2026);
// Übung: 12 × (1×4 + 2×8 + 1×8) = 12 × 28 = 336 Verpflegung + 300 = 636; Sommerfest 1000 − 400 = 600; Ausbildung 0
$check('drei Veranstaltungen mit Planung', $g['anzahl'] === 3 && count($g['liste']) === 3);
$check('Summe: 636 + 600 + 0', $nah($g['gesamt'], 1236.0));
$check('davon Verpflegung 336', $nah($g['verpflegung'], 336.0));
$uebung = $g['liste'][0];
$check('Übung: geplant, Verpflegung, Rest', $uebung['titel'] === 'Übung Hochwasser' && $nah($uebung['geplant'], 636.0) && $nah($uebung['verpflegung'], 336.0) && $nah($uebung['rest'], 636.0));
$fest = $g['liste'][1];
$check('Sommerfest: 400 gebucht, 600 offen', $nah($fest['gebucht'], 400.0) && $nah($fest['rest'], 600.0));
$check('mehr gebucht als geplant: Rest 0', $nah($g['liste'][2]['rest'], 0.0));
$check('abgesagt und ohne Planung fehlen', !in_array('Abgesagt', array_column($g['liste'], 'titel'), true) && !in_array('Dienstabend', array_column($g['liste'], 'titel'), true));

/* ---------- Jahreszahlen mit Planung ---------- */
$z = budget_jahr_zahlen(2026);
$check('Planung fließt in die Jahreszahlen', $nah($z['geplant_veranstaltungen'], 1236.0) && $nah($z['geplant_verpflegung'], 336.0) && $z['geplant_anzahl'] === 3 && $nah($z['geplant'], 1236.0));
$check('frei nach Planung', $nah($z['frei_nach_planung'], $z['frei'] - 1236.0));

/* ---------- Nebenkosten-Hinweis aus dem Verbrauch ---------- */
$stats = ['zaehler' => 3, 'kosten_jahr' => 1810.0, 'erloes_jahr' => 10.0, 'ohne_tarif' => 0,
          'je_art' => ['strom' => ['zaehler' => 2, 'kosten' => 1500.0], 'gas' => ['zaehler' => 1, 'kosten' => 310.0], 'wasser' => ['zaehler' => 0, 'kosten' => 0.0]]];
$h = verbrauch_kostenhinweis(2027, strtotime('2026-07-01 12:00:00'), $stats);
$check('Vorjahr läuft: Kosten bis heute abzüglich Erlös', $h !== null && $h['vorjahr'] === 2026 && $nah($h['kosten'], 1800.0) && $h['laeuft']);
$check('hochgerechnet über 181 Tage', $h['tage'] === 181 && $nah($h['hochrechnung'], 1800.0 / 181 * 365));
$check('je Art nur mit Zähler', $h['je_art'] === ['strom' => 1500.0, 'gas' => 310.0]);
$h = verbrauch_kostenhinweis(2027, strtotime('2027-03-01'), $stats);
$check('abgeschlossenes Vorjahr ohne Hochrechnung', !$h['laeuft'] && $h['hochrechnung'] === null && $nah($h['kosten'], 1800.0));
$h = verbrauch_kostenhinweis(2027, strtotime('2026-01-10'), $stats);
$check('unter 30 Tagen keine Hochrechnung', $h['laeuft'] && $h['hochrechnung'] === null);
$check('ohne Zähler nichts', verbrauch_kostenhinweis(2027, null, ['zaehler' => 0, 'kosten_jahr' => 0, 'ohne_tarif' => 0, 'je_art' => []]) === null);

/* ---------- Verlauf fürs Diagramm ---------- */
$GLOBALS['tabellen'] = ['GROUP BY MONTH' => static fn(array $p) => match (implode(',', array_slice($p, 1))) {
    'einnahme,bezahlt'  => [['m' => 3, 'betrag' => 1000]],
    'einnahme,zugesagt' => [['m' => 3, 'betrag' => 400]],
    'einnahme,abgerechnet,gestellt' => [['m' => 5, 'betrag' => 800]],
    'ausgabe,offen'     => [['m' => 2, 'betrag' => 150]],
    'ausgabe'           => [['m' => 2, 'betrag' => 500], ['m' => 4, 'betrag' => 200]],
    default => [],
}] + $GLOBALS['tabellen'];
$v = budget_verlauf(2026);
$check('Verlauf: Einnahmen nach Stufe', $v['eingegangen'][3] === 1000.0 && $v['zugesagt'][3] === 400.0 && $v['forderungen'][5] === 800.0 && $v['eingegangen'][5] === 0.0);
$check('Verlauf: bezahlt = Ausgaben ohne offene', $v['bezahlt'][2] === 350.0 && $v['offen'][2] === 150.0 && $v['bezahlt'][4] === 200.0 && count($v['bezahlt']) === 12);
unset($GLOBALS['tabellen']['GROUP BY MONTH']);
$verlaufLeer = ['eingegangen' => array_fill(1, 12, 0.0), 'zugesagt' => array_fill(1, 12, 0.0), 'forderungen' => array_fill(1, 12, 0.0), 'bezahlt' => array_fill(1, 12, 0.0), 'offen' => array_fill(1, 12, 0.0)];

/* ---------- Übersicht rendert die neuen Abschnitte ---------- */
$z = ['budget' => 10000.0, 'einnahmen' => 2500.0, 'ausgaben' => 4000.0, 'verfuegbar' => 12500.0, 'frei' => 8500.0, 'quote' => 32.0,
      'einnahmen_offen' => 500.0, 'einnahmen_forderungen' => 500.0, 'einnahmen_zugesagt' => 250.0, 'abrechnungen_wartend' => ['liste' => [], 'gelb' => 0, 'orange' => 0, 'rot' => 0, 'ueber30' => 0, 'summe_ueber30' => 0.0], 'ausgaben_offen' => 300.0, 'geplant_buchungen' => 200.0, 'geplant_veranstaltungen' => 1236.0,
      'geplant_verpflegung' => 336.0, 'geplant_anzahl' => 3, 'geplant' => 1436.0, 'frei_nach_planung' => 7064.0,
      'verfuegbar_mit_zusagen' => 12750.0, 'frei_mit_zusagen' => 8750.0];
$verlaufVoll = $verlaufLeer;
$verlaufVoll['eingegangen'][3] = 1000.0; $verlaufVoll['zugesagt'][3] = 250.0; $verlaufVoll['forderungen'][5] = 500.0; $verlaufVoll['bezahlt'][2] = 350.0; $verlaufVoll['offen'][2] = 300.0;
$html = render_partial('budget', ['jahr' => 2026, 'jahre' => [2026], 'budgets' => [], 'ohneTopf' => [], 'zuBestellen' => [], 'zurFreigabe' => [], 'zahlen' => $z,
    'kategorien' => [], 'einnahmeKategorien' => [], 'monate' => array_fill(1, 12, 0.0), 'monateEin' => array_fill(1, 12, 0.0), 'jeTopf' => [], 'letzte' => [],
    'offenGeplant' => [['id' => 9, 'art' => 'einnahme', 'datum' => '2026-05-02', 'bezeichnung' => 'Rechnung Landkreis', 'status' => 'offen', 'kategorie_label' => 'Einsatz', 'betrag_brutto' => 500, 'veranstaltung_titel' => null]],
    'veranstaltungen' => $g, 'verbrauchHinweis' => verbrauch_kostenhinweis(2027, strtotime('2026-07-01'), $stats), 'verlauf' => $verlaufVoll]);
$check('Mittel: offene Rechnungen schraffiert, Zusagen darunter', str_contains($html, 'offene Rechnungen: 300,00') && str_contains($html, 'Zugesagt, noch nicht da:</strong> 250,00')
    && str_contains($html, 'mit Zusagen <strong>12.750,00 €</strong> verfügbar') && str_contains($html, 'Forderungen: 500,00'));
$check('Verlauf gestapelt mit Legende', str_contains($html, 'months__seg--zusage') && str_contains($html, 'months__seg--forderung') && str_contains($html, 'months__seg--offen')
    && str_contains($html, 'legend--zusage') && str_contains($html, 'zugesagt 250,00'));
$check('Kacheln nennen offen und Planung', str_contains($html, 'abgerechnet oder gestellt') && str_contains($html, 'zugesagt</span>, noch nicht da') && str_contains($html, 'offene Rechnungen') && str_contains($html, 'nach Planung noch') && str_contains($html, '7.064'));
$check('Abschnitt Offen und geplant', str_contains($html, 'Offen und geplant') && str_contains($html, 'Übung Hochwasser') && str_contains($html, 'Rechnung Landkreis') && str_contains($html, '>offen<'));
$check('Nebenkosten-Hinweis', str_contains($html, 'Nebenkosten aus dem Verbrauch') && str_contains($html, 'hochgerechnet'));
$html = render_partial('budget', ['jahr' => 2026, 'jahre' => [2026], 'budgets' => [], 'ohneTopf' => [], 'zuBestellen' => [], 'zurFreigabe' => [], 'zahlen' => ['geplant' => 0.0] + $z,
    'kategorien' => [], 'einnahmeKategorien' => [], 'monate' => array_fill(1, 12, 0.0), 'monateEin' => array_fill(1, 12, 0.0), 'jeTopf' => [], 'letzte' => [],
    'offenGeplant' => [], 'veranstaltungen' => ['liste' => [], 'gesamt' => 0.0, 'verpflegung' => 0.0, 'anzahl' => 0], 'verbrauchHinweis' => null, 'verlauf' => $verlaufLeer]);
$check('ohne Planung kein Abschnitt', !str_contains($html, 'Offen und geplant') && !str_contains($html, 'Nebenkosten'));
$html = render_partial('budget_year_edit', ['eintrag' => ['jahr' => 2027, 'betrag' => '', 'beschreibung' => '', 'is_active' => 1], 'errors' => [], 'ausgaben' => 0.0,
    'verbrauchHinweis' => verbrauch_kostenhinweis(2027, strtotime('2026-07-01'), $stats)]);
$check('Jahresbudget mit Nebenkosten-Anhalt', str_contains($html, 'Nebenkosten aus dem Verbrauch 2026') && str_contains($html, 'hochgerechnet'));
$html = render_partial('expense_edit', ['art' => 'einnahme', 'expense' => ['id' => null, 'art' => 'einnahme', 'jahr' => 2026, 'datum' => '2026-01-01', 'bezeichnung' => '', 'beschreibung' => '', 'kategorie_id' => null, 'fachgruppe_id' => null, 'budget_id' => null, 'wish_id' => null, 'event_id' => null, 'betrag_brutto' => '', 'betrag_netto' => '', 'lieferant' => '', 'beleg_nr' => '', 'referenz' => '', 'bezahlt_am' => null, 'notiz' => '', 'status' => 'offen'], 'errors' => [], 'budgets' => [], 'wishes' => [], 'events' => []]);
$check('Formular: Weg der Einnahme, offen wird gestellt', str_contains($html, 'value="gestellt" checked') && str_contains($html, 'Weg der Einnahme'));

echo "$ok bestanden, $fail fehlgeschlagen\n";
