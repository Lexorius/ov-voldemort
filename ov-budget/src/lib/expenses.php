<?php
declare(strict_types=1);

/**
 * Gesamtbudget je Haushaltsjahr und die laufenden Buchungen.
 *
 * Eine Buchung ist entweder eine Ausgabe oder eine Einnahme – etwa die
 * Kostenerstattung für einen Einsatz oder eine technische Hilfeleistung.
 * Beides steckt in derselben Tabelle und unterscheidet sich nur in der
 * Spalte "art"; so gelten Filter, Liste und Ausgabe für beide Richtungen.
 *
 * Die Wunschliste plant, dieses Modul bucht das tatsächlich Geflossene.
 */

const BUCHUNGSARTEN = ['ausgabe' => 'Ausgaben', 'einnahme' => 'Einnahmen'];
/**
 * Stand einer Buchung. bezahlt und offen zählen in den Ist-Zahlen (das Geld
 * ist geflossen oder die Rechnung liegt vor), geplant nur in der Planung.
 */
const BUCHUNG_STATUS = [
    'bezahlt' => 'bezahlt',
    'offen'   => 'gebucht – Rechnung liegt vor oder ist bestellt, noch nicht bezahlt',
    'geplant' => 'geplant – noch keine Rechnung',
];
/** Wie viele Tage vor dem Stichtag die Leitung erinnert wird */
const BUDGET_STICHTAG_ERINNERUNG = [30, 14, 7, 1, 0];
/**
 * Einnahmen gehen einen längeren Weg: Der OV rechnet den Einsatz ab, die
 * Regionalstelle stellt Rechnung oder Gebührenbescheid, sagt dem OV Mittel
 * zu und weist sie schließlich zu. Reihenfolge = Rangfolge.
 */
const EINNAHME_STUFEN = [
    'geplant'     => 'erwartet – Abrechnung noch nicht erstellt',
    'abgerechnet' => 'abgerechnet – Einsatzabrechnung eingereicht',
    'gestellt'    => 'gestellt – Rechnung oder Gebührenbescheid ist raus',
    'zugesagt'    => 'zugesagt – Mittel sind dem OV versprochen',
    'bezahlt'     => 'eingegangen / zugewiesen',
];
/** Felder je Stufe: Datum, Betrag, Nummer */
const EINNAHME_STUFEN_FELDER = [
    'abgerechnet' => ['datum' => 'abgerechnet_am', 'betrag' => 'abgerechnet_betrag', 'label' => 'Abgerechnet'],
    'gestellt'    => ['datum' => 'gestellt_am', 'betrag' => 'gestellt_betrag', 'nr' => 'gestellt_nr', 'label' => 'Rechnung / Bescheid'],
    'zugesagt'    => ['datum' => 'zugesagt_am', 'betrag' => 'zugesagt_betrag', 'label' => 'Budget zugesagt'],
    'bezahlt'     => ['datum' => 'bezahlt_am', 'label' => 'Eingegangen / zugewiesen'],
];
/**
 * Bedingung für alles, was als Ist zählt: bezahlt und erhaltene Rechnungen.
 * Zugesagte Mittel stehen daneben – verfügbar ist nur, was da ist.
 * Abgerechnet und gestellt sind Forderungen, geplant ist Planung.
 */
const BUCHUNG_IST = "status IN ('bezahlt','offen')";
/** Forderungen: Einnahmen, die dem OV zustehen, aber weder zugesagt noch da sind */
const EINNAHME_FORDERUNG = ['abgerechnet', 'gestellt'];
/** Stufen, in denen eine Abrechnung wartet – vom Einreichen bis zum Geld */
const EINNAHME_WARTEND = ['abgerechnet', 'gestellt', 'zugesagt'];
/** Ampel: ab so vielen Tagen Warten wird es gelb, orange, rot */
const EINNAHME_WARN_TAGE = ['gelb' => 30, 'orange' => 60, 'rot' => 90];
const EINNAHME_WARN_FARBEN = ['gelb' => '#ca8a04', 'orange' => '#ea580c', 'rot' => '#b91c1c'];

function buchung_status(string $status): string
{
    return array_key_exists($status, BUCHUNG_STATUS) || array_key_exists($status, EINNAHME_STUFEN) ? $status : 'bezahlt';
}

/** Die Stufen in der Reihenfolge des Weges, zum Vergleichen. Reine Funktion. */
function einnahme_rang(string $stufe): int
{
    $rang = array_flip(array_keys(EINNAHME_STUFEN));
    return $rang[$stufe] ?? $rang['bezahlt'];
}

/**
 * Stufe einer Einnahme aus dem gewählten Stand und den Daten: Ein Datum
 * hebt auf seine Stufe, der gewählte Stand kann höher sein, nie tiefer als
 * das späteste Datum. Reine Funktion.
 */
function einnahme_stufe(array $e, string $gewaehlt = 'bezahlt'): string
{
    $stufe = array_key_exists($gewaehlt, EINNAHME_STUFEN) ? $gewaehlt : 'bezahlt';
    foreach (EINNAHME_STUFEN_FELDER as $key => $f) {
        if (!empty($e[$f['datum']]) && einnahme_rang($key) > einnahme_rang($stufe)) {
            $stufe = $key;
        }
    }
    return $stufe;
}

/** Kennzeichen in Listen – bezahlt bleibt unmarkiert, das ist der Normalfall */
function buchung_status_badge(string $status): string
{
    return match (buchung_status($status)) {
        'offen'       => '<span class="badge" style="background:#b45309" title="gebucht – Rechnung liegt vor oder ist bestellt, noch nicht bezahlt">gebucht</span>',
        'geplant'     => '<span class="badge badge--outline" title="geplant, noch keine Rechnung">geplant</span>',
        'abgerechnet' => '<span class="badge" style="background:#0369a1" title="Einsatzabrechnung eingereicht">abgerechnet</span>',
        'gestellt'    => '<span class="badge" style="background:#b45309" title="Rechnung oder Gebührenbescheid gestellt, Geld noch nicht da">gestellt</span>',
        'zugesagt'    => '<span class="badge" style="background:#15803d" title="Mittel zugesagt, noch nicht zugewiesen">zugesagt</span>',
        default       => '',
    };
}

/**
 * Wie lange eine Abrechnung schon wartet – ab dem Tag der Abrechnung, sonst
 * des Bescheids, sonst der Buchung. null, wenn sie nicht wartet (erwartet,
 * eingegangen). Reine Funktion.
 */
function einnahme_alter(array $e, ?int $jetzt = null): ?array
{
    if ((string)($e['art'] ?? 'einnahme') !== 'einnahme' || !in_array((string)($e['status'] ?? ''), EINNAHME_WARTEND, true)) {
        return null;
    }
    $seit = (string)($e['abgerechnet_am'] ?: ($e['gestellt_am'] ?: ($e['datum'] ?? '')));
    if ($seit === '') {
        return null;
    }
    $tage = (int)floor((($jetzt ?? time()) - strtotime($seit)) / 86400);
    $stufe = 'gruen';
    foreach (EINNAHME_WARN_TAGE as $farbe => $ab) {
        if ($tage >= $ab) {
            $stufe = $farbe;
        }
    }
    return ['tage' => max(0, $tage), 'stufe' => $stufe, 'seit' => $seit];
}

/** Kennzeichen „wartet seit 45 Tagen" in Ampelfarbe – leer unter 30 Tagen. Reine Funktion. */
function einnahme_alter_badge(?array $alter): string
{
    if ($alter === null || $alter['stufe'] === 'gruen') {
        return '';
    }
    return '<span class="badge" style="background:' . EINNAHME_WARN_FARBEN[$alter['stufe']] . '" title="seit '
        . e(de_date($alter['seit'])) . ' ohne Geld">wartet seit ' . (int)$alter['tage'] . ' Tagen</span>';
}

/**
 * Wartende Abrechnungen eines Jahres mit Alter, älteste zuerst; dazu die
 * Zählung je Ampelstufe.
 */
function einnahmen_wartend(int $jahr, ?int $jetzt = null): array
{
    $in = implode(',', array_fill(0, count(EINNAHME_WARTEND), '?'));
    return einnahmen_wartend_aus(
        db_all("SELECT * FROM expenses WHERE jahr = ? AND art = 'einnahme' AND status IN ($in)", array_merge([$jahr], EINNAHME_WARTEND)),
        $jetzt
    );
}

/** Dasselbe aus einer Liste – reine Funktion */
function einnahmen_wartend_aus(array $rows, ?int $jetzt = null): array
{
    $out = ['liste' => [], 'gelb' => 0, 'orange' => 0, 'rot' => 0, 'ueber30' => 0, 'summe_ueber30' => 0.0];
    foreach ($rows as $e) {
        $a = einnahme_alter($e, $jetzt);
        if ($a === null) {
            continue;
        }
        $e['alter'] = $a;
        $out['liste'][] = $e;
        if ($a['stufe'] !== 'gruen') {
            $out[$a['stufe']]++;
            $out['ueber30']++;
            $out['summe_ueber30'] += (float)$e['betrag_brutto'];
        }
    }
    usort($out['liste'], static fn($x, $y) => $y['alter']['tage'] <=> $x['alter']['tage']);
    return $out;
}

/** Betreff der Anfrage an die Regionalstelle. Reine Funktion. */
function einnahmen_anfrage_betreff(string $ov, int $anzahl): string
{
    return sprintf('Bearbeitungsstand unserer Einsatzabrechnungen (%d offen) – %s', $anzahl, $ov !== '' ? $ov : 'Ortsverband');
}

/**
 * Der Text der Anfrage: jede wartende Abrechnung mit Datum, Betrag,
 * Nummern und Stand, dazu Summe und Gruß. $ctx: ov, name, funktion,
 * empfaenger, email, telefon. Reine Funktion.
 */
function einnahmen_anfrage_text(array $rows, array $ctx): string
{
    $ov = (string)($ctx['ov'] ?? '');
    $z = [];
    $z[] = ($ctx['empfaenger'] ?? '') !== '' ? 'Sehr geehrte Damen und Herren der ' . $ctx['empfaenger'] . ',' : 'Sehr geehrte Damen und Herren,';
    $z[] = '';
    if (!$rows) {
        $z[] = 'derzeit warten wir auf keine Einsatzabrechnung.';
    } else {
        $z[] = count($rows) === 1
            ? 'für die folgende Einsatzabrechnung unseres Ortsverbands liegt uns noch kein Eingang vor. Wir bitten um eine kurze Rückmeldung zum Bearbeitungsstand – ob und wann mit der Zuweisung zu rechnen ist oder ob noch Unterlagen fehlen:'
            : sprintf('für die folgenden %d Einsatzabrechnungen unseres Ortsverbands liegt uns noch kein Eingang vor. Wir bitten um eine kurze Rückmeldung zum Bearbeitungsstand – ob und wann mit der Zuweisung zu rechnen ist oder ob noch Unterlagen fehlen:', count($rows));
        $z[] = '';
        $summe = 0.0;
        $zugesagt = 0.0;
        foreach (array_values($rows) as $i => $e) {
            $summe += (float)$e['betrag_brutto'];
            $teile = [];
            if (!empty($e['abgerechnet_am'])) {
                $teile[] = 'abgerechnet am ' . de_date((string)$e['abgerechnet_am']) . (($e['abgerechnet_betrag'] ?? null) !== null && $e['abgerechnet_betrag'] !== '' ? ' über ' . money((float)$e['abgerechnet_betrag']) : '');
            } else {
                $teile[] = 'vom ' . de_date((string)$e['datum']);
            }
            if (trim((string)($e['referenz'] ?? '')) !== '') {
                $teile[] = 'Einsatz-/Auftragsnummer ' . $e['referenz'];
            }
            if (trim((string)($e['beleg_nr'] ?? '')) !== '') {
                $teile[] = 'Aktenzeichen ' . $e['beleg_nr'];
            }
            if (!empty($e['gestellt_am'])) {
                $teile[] = (trim((string)($e['gestellt_nr'] ?? '')) !== '' ? 'Bescheid/Rechnung Nr. ' . $e['gestellt_nr'] : 'Bescheid/Rechnung') . ' vom ' . de_date((string)$e['gestellt_am'])
                    . (($e['gestellt_betrag'] ?? null) !== null && $e['gestellt_betrag'] !== '' ? ' über ' . money((float)$e['gestellt_betrag']) : '');
            }
            if ((string)$e['status'] === 'zugesagt') {
                $zugesagt += (float)$e['betrag_brutto'];
                $teile[] = 'Mittel zugesagt' . (!empty($e['zugesagt_am']) ? ' am ' . de_date((string)$e['zugesagt_am']) : '') . ', noch nicht zugewiesen';
            }
            $tage = (int)($e['alter']['tage'] ?? 0);
            $teile[] = 'offen seit ' . $tage . ' Tag' . ($tage === 1 ? '' : 'en');
            $z[] = sprintf('%d. %s – %s', $i + 1, (string)$e['bezeichnung'], money((float)$e['betrag_brutto']));
            $z[] = '   ' . implode(', ', $teile);
        }
        $z[] = '';
        $z[] = sprintf('Insgesamt: %d Abrechnung%s über %s%s.', count($rows), count($rows) === 1 ? '' : 'en', money($summe),
            $zugesagt > 0 ? ' (davon ' . money($zugesagt) . ' bereits zugesagt)' : '');
        $z[] = '';
        $z[] = 'Falls zu einer Abrechnung noch etwas fehlt, reichen wir es gern umgehend nach.';
    }
    $z[] = '';
    $z[] = 'Vielen Dank und freundliche Grüße';
    $z[] = (string)($ctx['name'] ?? '');
    if (($ctx['funktion'] ?? '') !== '') {
        $z[] = (string)$ctx['funktion'];
    }
    if ($ov !== '') {
        $z[] = $ov;
    }
    $kontakt = array_filter([(string)($ctx['telefon'] ?? ''), (string)($ctx['email'] ?? '')]);
    if ($kontakt) {
        $z[] = implode(' · ', $kontakt);
    }
    return implode("\n", $z);
}

/** „2 rot, 1 orange" – reine Funktion */
function einnahmen_wartend_text(array $w): string
{
    $teile = [];
    foreach (['rot', 'orange', 'gelb'] as $f) {
        if ($w[$f] > 0) {
            $teile[] = $w[$f] . ' über ' . EINNAHME_WARN_TAGE[$f] . ' Tage';
        }
    }
    return implode(', ', $teile);
}

/**
 * Tägliche Meldung an die Leitung: Abrechnungen, die eine neue Ampelstufe
 * erreicht haben – je Abrechnung und Stufe nur einmal.
 */
function einnahmen_warnungen_taeglich(?int $jetzt = null, ?array $wartend = null): int
{
    if (!function_exists('notify_ereignis_aktiv') || !notify_ereignis_aktiv('abrechnung_wartet')) {
        return 0;
    }
    $jetzt ??= time();
    $jahr = (int)date('Y', $jetzt);
    $zeilen = [];
    $merken = [];
    $rang = ['gruen' => 0, 'gelb' => 1, 'orange' => 2, 'rot' => 3];
    foreach ($wartend ?? array_merge(einnahmen_wartend($jahr, $jetzt)['liste'], einnahmen_wartend($jahr - 1, $jetzt)['liste']) as $e) {
        $stufe = $e['alter']['stufe'];
        if ($stufe === 'gruen') {
            continue;
        }
        $gemeldet = state_get('einnahme_warn_' . (int)$e['id'], 'gruen');
        if (($rang[$gemeldet] ?? 0) >= $rang[$stufe]) {
            continue;
        }
        $merken[(int)$e['id']] = $stufe;
        $zeilen[] = sprintf('%s: %s, wartet seit %d Tagen (%s)', (string)$e['bezeichnung'], money((float)$e['betrag_brutto']),
            (int)$e['alter']['tage'], EINNAHME_STUFEN[(string)$e['status']] !== '' ? explode(' –', EINNAHME_STUFEN[(string)$e['status']])[0] : (string)$e['status']);
    }
    if (!$zeilen) {
        return 0;
    }
    $n = notify_queue(notify_leitung(), 'abrechnung_wartet', count($zeilen) === 1 ? 'Abrechnung wartet auf Geld' : count($zeilen) . ' Abrechnungen warten auf Geld',
        implode("
", array_slice($zeilen, 0, 8)), '?p=expenses&art=einnahme&status=abgerechnet');
    foreach ($merken as $id => $stufe) {
        state_save('einnahme_warn_' . $id, $stufe);
    }
    return $n;
}

/** Der Weg einer Einnahme als kurze Zeile: „abgerechnet 12.03. 1.250 € · Bescheid 05.04. Nr. 4711 1.100 €". Reine Funktion. */
function einnahme_weg(array $e): string
{
    $teile = [];
    foreach (EINNAHME_STUFEN_FELDER as $key => $f) {
        if (empty($e[$f['datum']])) {
            continue;
        }
        $t = ($key === 'bezahlt' ? 'eingegangen' : $f['label']) . ' ' . de_date((string)$e[$f['datum']]);
        if (!empty($f['nr']) && trim((string)($e[$f['nr']] ?? '')) !== '') {
            $t .= ' Nr. ' . $e[$f['nr']];
        }
        if (!empty($f['betrag']) && $e[$f['betrag']] !== null && $e[$f['betrag']] !== '') {
            $t .= ' ' . money((float)$e[$f['betrag']]);
        }
        $teile[] = $t;
    }
    return implode(' · ', $teile);
}

/**
 * Stand der Einnahmen eines Jahres je Stufe: Anzahl und Summe, dazu die
 * Forderungen, das Zugesagte, das Eingegangene und der Verlust zwischen
 * Abrechnung und Bescheid (wo beide Beträge bekannt sind).
 */
function einnahmen_stand(int $jahr): array
{
    $out = ['stufen' => [], 'forderungen' => 0.0, 'zugesagt' => 0.0, 'eingegangen' => 0.0, 'erwartet' => 0.0,
            'abgerechnet_summe' => 0.0, 'gestellt_summe' => 0.0, 'kuerzung' => 0.0, 'kuerzung_anzahl' => 0];
    foreach (EINNAHME_STUFEN as $key => $label) {
        $out['stufen'][$key] = ['label' => $label, 'anzahl' => 0, 'summe' => 0.0];
    }
    foreach (db_all(
        'SELECT status, COUNT(*) AS anzahl, COALESCE(SUM(betrag_brutto),0) AS summe
         FROM expenses WHERE jahr = ? AND art = ? GROUP BY status',
        [$jahr, 'einnahme']
    ) as $r) {
        $s = buchung_status((string)$r['status']);
        if (!isset($out['stufen'][$s])) {
            continue;
        }
        $out['stufen'][$s]['anzahl'] += (int)$r['anzahl'];
        $out['stufen'][$s]['summe'] += (float)$r['summe'];
    }
    foreach (EINNAHME_FORDERUNG as $s) {
        $out['forderungen'] += $out['stufen'][$s]['summe'];
    }
    $out['zugesagt'] = $out['stufen']['zugesagt']['summe'];
    $out['eingegangen'] = $out['stufen']['bezahlt']['summe'];
    $out['erwartet'] = $out['stufen']['geplant']['summe'];
    $k = db_row(
        'SELECT COUNT(*) AS anzahl, COALESCE(SUM(abgerechnet_betrag),0) AS abgerechnet, COALESCE(SUM(gestellt_betrag),0) AS gestellt
         FROM expenses WHERE jahr = ? AND art = ? AND abgerechnet_betrag IS NOT NULL AND gestellt_betrag IS NOT NULL',
        [$jahr, 'einnahme']
    );
    if ($k && (int)$k['anzahl'] > 0) {
        $out['kuerzung_anzahl'] = (int)$k['anzahl'];
        $out['abgerechnet_summe'] = (float)$k['abgerechnet'];
        $out['gestellt_summe'] = (float)$k['gestellt'];
        $out['kuerzung'] = round((float)$k['abgerechnet'] - (float)$k['gestellt'], 2);
    }
    return $out;
}

/** Nur bekannte Richtungen zulassen */
function buchungsart(string $art): string
{
    return array_key_exists($art, BUCHUNGSARTEN) ? $art : 'ausgabe';
}

/** Liste, aus der die Kategorien der jeweiligen Richtung stammen */
function buchung_list_key(string $art): string
{
    return buchungsart($art) === 'einnahme' ? 'einnahme_kategorie' : 'ausgabe_kategorie';
}

/* ---------------- Jahresbudget ---------------- */

function budget_year(int $jahr): ?array
{
    return db_row('SELECT * FROM budget_years WHERE jahr = ?', [$jahr]);
}

function budget_year_betrag(int $jahr): float
{
    return (float)(budget_year($jahr)['betrag'] ?? 0);
}

function budget_year_save(int $jahr, float $betrag, string $beschreibung, int $aktiv, ?string $stichtag = null): void
{
    db_exec(
        'INSERT INTO budget_years (jahr, betrag, beschreibung, is_active, stichtag) VALUES (?,?,?,?,?)
         ON DUPLICATE KEY UPDATE betrag = VALUES(betrag), beschreibung = VALUES(beschreibung),
                                 is_active = VALUES(is_active), stichtag = VALUES(stichtag)',
        [$jahr, $betrag, $beschreibung, $aktiv, $stichtag]
    );
    audit('jahresbudget.gespeichert', 'budget_year', $jahr, money($betrag) . ($stichtag ? ', Stichtag ' . de_date($stichtag) : ''));
}

/**
 * Der Stichtag eines Haushaltsjahres: ab diesem Tag darf nichts mehr auf das
 * Jahresbudget ausgegeben werden. Liefert Tage bis dahin (negativ: vorbei)
 * und ob gesperrt ist. Reine Funktion.
 */
function budget_stichtag_info(?string $stichtag, ?int $jetzt = null): array
{
    $stichtag = trim((string)$stichtag);
    if ($stichtag === '' || str_starts_with($stichtag, '0000')) {
        return ['stichtag' => null, 'tage' => null, 'gesperrt' => false];
    }
    $heute = strtotime(date('Y-m-d', $jetzt ?? time()));
    $tage = (int)round((strtotime($stichtag) - $heute) / 86400);
    return ['stichtag' => $stichtag, 'tage' => $tage, 'gesperrt' => $tage < 0];
}

/**
 * Darf eine Ausgabe mit diesem Datum noch auf das Jahresbudget? Nein, wenn
 * das Datum nach dem Stichtag liegt – geplante Ausgaben sind davon frei.
 * Rückgabe: Fehlertext oder null.
 */
function budget_sperre_pruefen(int $jahr, string $datum, string $status, ?array $eintrag = null): ?string
{
    if ($status === 'geplant') {
        return null;
    }
    $eintrag ??= budget_year($jahr);
    $stichtag = trim((string)($eintrag['stichtag'] ?? ''));
    if ($stichtag === '' || str_starts_with($stichtag, '0000') || $datum <= $stichtag) {
        return null;
    }
    return sprintf('Das Haushaltsjahr %d ist seit dem Stichtag %s geschlossen – danach dürfen keine Ausgaben mehr auf das Jahresbudget gebucht werden. '
        . 'Als „geplant" lässt sich die Ausgabe trotzdem vormerken.', $jahr, de_date($stichtag));
}

/**
 * Tägliche Erinnerung an die Leitung: 30, 14, 7 und 1 Tag vor dem Stichtag
 * und am Tag selbst – je Schwelle einmal, mit dem, was noch frei ist.
 */
function budget_stichtag_taeglich(?int $jetzt = null, ?array $eintrag = null, ?float $frei = null): int
{
    if (!function_exists('notify_ereignis_aktiv') || !notify_ereignis_aktiv('budget_stichtag')) {
        return 0;
    }
    $jetzt ??= time();
    $jahr = (int)date('Y', $jetzt);
    $eintrag ??= budget_year($jahr);
    $info = budget_stichtag_info($eintrag['stichtag'] ?? null, $jetzt);
    if ($info['tage'] === null || $info['tage'] < 0) {
        return 0;
    }
    $schwelle = null;
    foreach (BUDGET_STICHTAG_ERINNERUNG as $s) {
        if ($info['tage'] <= $s) {
            $schwelle = $s;   // die kleinste Schwelle, die schon erreicht ist
        }
    }
    if ($schwelle === null) {
        return 0;
    }
    $marke = $jahr . ':' . $schwelle;
    if (state_get('budget_stichtag_gemeldet', '') === $marke) {
        return 0;
    }
    $frei ??= budget_jahr_zahlen($jahr)['frei'];
    $text = $info['tage'] === 0
        ? sprintf('Heute ist der Stichtag des Jahresbudgets %d. Ab morgen dürfen keine Ausgaben mehr darauf gebucht werden – noch frei: %s.', $jahr, money($frei))
        : sprintf('Noch %d Tag%s bis zum Stichtag des Jahresbudgets %d (%s). Danach dürfen keine Ausgaben mehr darauf gebucht werden – noch frei: %s.',
            $info['tage'], $info['tage'] === 1 ? '' : 'e', $jahr, de_date($info['stichtag']), money($frei));
    $n = notify_queue(notify_leitung(), 'budget_stichtag', 'Stichtag des Jahresbudgets', $text, '?p=budget&jahr=' . $jahr);
    state_save('budget_stichtag_gemeldet', $marke);
    return $n;
}

/** Alle Jahre, zu denen es irgendetwas gibt */
function budget_years_known(): array
{
    $rows = db_all(
        'SELECT jahr FROM budget_years
         UNION SELECT jahr FROM budgets
         UNION SELECT jahr FROM expenses
         ORDER BY jahr DESC'
    );
    $jahre = array_map(static fn($r) => (int)$r['jahr'], $rows);
    $aktuell = haushaltsjahr();
    if (!in_array($aktuell, $jahre, true)) {
        $jahre[] = $aktuell;
        rsort($jahre);
    }
    return $jahre;
}

/** Budgettöpfe eines Jahres samt Zahlen für die Verwaltung */
function budget_pots(int $jahr): array
{
    return db_all(
        'SELECT b.*, k.label AS kategorie_label, f.label AS fachgruppe_label,
                (SELECT COALESCE(SUM(w.netto_gesamt),0) FROM wishes w
                  LEFT JOIN list_items s ON s.id = w.status_id
                 WHERE w.budget_id = b.id AND COALESCE(s.is_final,0) = 0) AS verplant,
                (SELECT COUNT(*) FROM wishes w WHERE w.budget_id = b.id) AS wuensche,
                (SELECT COUNT(*) FROM expenses e WHERE e.budget_id = b.id) AS buchungen,
                (SELECT COALESCE(SUM(e.betrag_netto),0) FROM expenses e
                 WHERE e.budget_id = b.id AND e.art = \'ausgabe\' AND e.' . BUCHUNG_IST . ') AS ausgegeben,
                (SELECT COALESCE(SUM(e.betrag_netto),0) FROM expenses e
                 WHERE e.budget_id = b.id AND e.art = \'ausgabe\' AND e.status = \'geplant\') AS geplant
         FROM budgets b
         LEFT JOIN list_items k ON k.id = b.kategorie_id
         LEFT JOIN list_items f ON f.id = b.fachgruppe_id
         WHERE b.jahr = ?
         ORDER BY b.is_active DESC, b.name',
        [$jahr]
    );
}

/**
 * Töpfe eines Jahres in ein anderes übernehmen – zum Jahreswechsel.
 * Gleichnamige Töpfe im Zieljahr bleiben unberührt. Gibt die Anzahl zurück.
 */
function budget_pot_copy(int $von, int $nach): int
{
    $vorhanden = array_map(
        static fn($b) => mb_strtolower(trim((string)$b['name'])),
        db_all('SELECT name FROM budgets WHERE jahr = ?', [$nach])
    );
    $kopiert = 0;
    foreach (db_all('SELECT * FROM budgets WHERE jahr = ? AND is_active = 1 ORDER BY name', [$von]) as $b) {
        if (in_array(mb_strtolower(trim((string)$b['name'])), $vorhanden, true)) {
            continue;
        }
        db_insert('budgets', [
            'jahr'          => $nach,
            'name'          => (string)$b['name'],
            'kategorie_id'  => $b['kategorie_id'],
            'fachgruppe_id' => $b['fachgruppe_id'],
            'betrag_netto'  => (float)$b['betrag_netto'],
            'beschreibung'  => (string)$b['beschreibung'],
            'is_active'     => 1,
        ]);
        $kopiert++;
    }
    if ($kopiert > 0) {
        audit('budget.uebernommen', 'budget', null,
            sprintf('%d Topf/Töpfe von %d nach %d', $kopiert, $von, $nach));
    }
    return $kopiert;
}

/* ---------------- Ausgaben ---------------- */

function expense_find(int $id): ?array
{
    return db_row('SELECT * FROM expenses WHERE id = ?', [$id]);
}

/**
 * $f: jahr, art, q, kategorie_id, fachgruppe_id, budget_id, von, bis, sort, limit
 */
function expense_query(array $f = []): array
{
    $w = [];
    $p = [];

    if (!empty($f['jahr'])) {
        $w[] = 'e.jahr = ?';
        $p[] = (int)$f['jahr'];
    }
    if (!empty($f['art'])) {
        $w[] = 'e.art = ?';
        $p[] = buchungsart((string)$f['art']);
    }
    if (!empty($f['q'])) {
        $w[] = '(e.bezeichnung LIKE ? OR e.beschreibung LIKE ? OR e.lieferant LIKE ?'
            . ' OR e.beleg_nr LIKE ? OR e.referenz LIKE ?)';
        $like = '%' . $f['q'] . '%';
        array_push($p, $like, $like, $like, $like, $like);
    }
    foreach (['kategorie_id', 'fachgruppe_id', 'budget_id', 'event_id', 'bestellung_id'] as $col) {
        if (!empty($f[$col])) {
            $w[] = 'e.' . $col . ' = ?';
            $p[] = (int)$f[$col];
        }
    }
    if (!empty($f['status'])) {
        $w[] = 'e.status = ?';
        $p[] = buchung_status((string)$f['status']);
    }
    if (!empty($f['von'])) {
        $w[] = 'e.datum >= ?';
        $p[] = $f['von'];
    }
    if (!empty($f['bis'])) {
        $w[] = 'e.datum <= ?';
        $p[] = $f['bis'];
    }

    $order = match ($f['sort'] ?? 'datum') {
        'betrag' => 'e.betrag_brutto DESC',
        'name'   => 'e.bezeichnung ASC',
        'alt'    => 'e.datum ASC, e.id ASC',
        default  => 'e.datum DESC, e.id DESC',
    };

    $sql = 'SELECT e.*,
                   ka.label AS kategorie_label, ka.color AS kategorie_color,
                   fg.label AS fachgruppe_label,
                   b.name AS budget_name,
                   w.bezeichnung AS wunsch_bezeichnung,
                   ev.titel AS veranstaltung_titel,
                   u.display_name AS erfasser,
                   bs.nummer AS bestellung_nummer,
                   (SELECT GROUP_CONCAT(lw.bezeichnung ORDER BY lw.bezeichnung SEPARATOR \' · \') FROM expense_links l JOIN wishes lw ON lw.id = l.ziel_id
                     WHERE l.expense_id = e.id AND l.typ = \'wish\') AS wuensche_namen,
                   (SELECT GROUP_CONCAT(lv.bezeichnung ORDER BY lv.bezeichnung SEPARATOR \' · \') FROM expense_links l JOIN vehicles lv ON lv.id = l.ziel_id
                     WHERE l.expense_id = e.id AND l.typ = \'vehicle\') AS fahrzeuge_namen
            FROM expenses e
            LEFT JOIN bestellungen bs ON bs.id = e.bestellung_id
            LEFT JOIN list_items ka ON ka.id = e.kategorie_id
            LEFT JOIN list_items fg ON fg.id = e.fachgruppe_id
            LEFT JOIN budgets    b  ON b.id  = e.budget_id
            LEFT JOIN wishes     w  ON w.id  = e.wish_id
            LEFT JOIN events     ev ON ev.id = e.event_id
            LEFT JOIN users      u  ON u.id  = e.created_by'
        . ($w ? ' WHERE ' . implode(' AND ', $w) : '')
        . ' ORDER BY ' . $order;

    if (!empty($f['limit'])) {
        $sql .= ' LIMIT ' . (int)$f['limit'];
    }

    return db_all($sql, $p);
}

function expense_stats(array $rows): array
{
    $s = ['anzahl' => count($rows), 'summe' => 0.0];
    foreach ($rows as $r) {
        $s['summe'] += (float)$r['betrag_brutto'];
    }
    return $s;
}

/** Ausgaben eines Jahres je Kategorie */
function expense_by_category(int $jahr, string $art = 'ausgabe'): array
{
    // Ohne Kategorie erfasste Ausgaben kommen als NULL zurück und werden
    // in der Anzeige beschriftet – kein SQL-Literal, das je nach sql_mode
    // als Bezeichner gelesen werden könnte.
    return db_all(
        'SELECT ka.id, ka.label, ka.color,
                COUNT(*) AS anzahl,
                SUM(e.betrag_brutto) AS betrag
         FROM expenses e
         LEFT JOIN list_items ka ON ka.id = e.kategorie_id
         WHERE e.jahr = ? AND e.art = ? AND e.' . BUCHUNG_IST . '
         GROUP BY ka.id, ka.label, ka.color
         ORDER BY betrag DESC',
        [$jahr, buchungsart($art)]
    );
}

/** Ausgaben eines Jahres je Monat, immer zwölf Werte */
function expense_by_month(int $jahr, string $art = 'ausgabe', ?array $status = null): array
{
    $out = array_fill(1, 12, 0.0);
    $p = [$jahr, buchungsart($art)];
    if ($status === null) {
        $bedingung = BUCHUNG_IST;
    } else {
        $bedingung = 'status IN (' . implode(',', array_fill(0, count($status), '?')) . ')';
        $p = array_merge($p, array_map('buchung_status', $status));
    }
    foreach (db_all(
        'SELECT MONTH(datum) AS m, SUM(betrag_brutto) AS betrag
         FROM expenses WHERE jahr = ? AND art = ? AND ' . $bedingung . ' GROUP BY MONTH(datum)',
        $p
    ) as $r) {
        $out[(int)$r['m']] = (float)$r['betrag'];
    }
    return $out;
}

/**
 * Der Verlauf fürs Diagramm: je Monat Einnahmen (eingegangen, zugesagt,
 * Forderungen) und Ausgaben (bezahlt, offene Rechnungen).
 */
function budget_verlauf(int $jahr): array
{
    $ausgaben = expense_by_month($jahr);
    $offen = expense_by_month($jahr, 'ausgabe', ['offen']);
    $bezahlt = [];
    foreach ($ausgaben as $m => $v) {
        $bezahlt[$m] = $v - (float)($offen[$m] ?? 0);
    }
    return [
        'eingegangen' => expense_by_month($jahr, 'einnahme', ['bezahlt']),
        'zugesagt'    => expense_by_month($jahr, 'einnahme', ['zugesagt']),
        'forderungen' => expense_by_month($jahr, 'einnahme', EINNAHME_FORDERUNG),
        'bezahlt'     => $bezahlt,
        'offen'       => $offen,
        'geplant'     => expense_by_month($jahr, 'ausgabe', ['geplant']),
    ];
}

/** Summe der Buchungen eines Jahres in einer Richtung */
function expense_total(int $jahr, string $art = 'ausgabe'): float
{
    return (float)db_val(
        'SELECT COALESCE(SUM(betrag_brutto),0) FROM expenses WHERE jahr = ? AND art = ? AND ' . BUCHUNG_IST,
        [$jahr, buchungsart($art)],
        0
    );
}

/** Summe der Buchungen eines Jahres mit einem bestimmten Stand */
function expense_total_status(int $jahr, string $art, string $status): float
{
    return (float)db_val(
        'SELECT COALESCE(SUM(betrag_brutto),0) FROM expenses WHERE jahr = ? AND art = ? AND status = ?',
        [$jahr, buchungsart($art), buchung_status($status)],
        0
    );
}

/** Offene und geplante Buchungen eines Jahres – für die Übersicht */
function expense_offen_geplant(int $jahr): array
{
    return db_all(
        'SELECT e.*, ka.label AS kategorie_label, ka.color AS kategorie_color, ev.titel AS veranstaltung_titel
         FROM expenses e
         LEFT JOIN list_items ka ON ka.id = e.kategorie_id
         LEFT JOIN events ev ON ev.id = e.event_id
         WHERE e.jahr = ? AND e.status <> \'bezahlt\'
         ORDER BY e.status, e.datum, e.id',
        [$jahr]
    );
}

/** Einnahmen eines Jahres */
function income_total(int $jahr): float
{
    return expense_total($jahr, 'einnahme');
}

/**
 * Die Zahlen des Haushaltsjahres, wie sie überall gezeigt werden:
 * Zuweisung, Einnahmen, Ausgaben, daraus verfügbar und frei. Töpfe und
 * verplante Wünsche kommen getrennt dazu.
 */
function budget_jahr_zahlen(int $jahr): array
{
    $eintrag = budget_year($jahr);
    $budget = (float)($eintrag['betrag'] ?? 0);
    $stichtag = budget_stichtag_info($eintrag['stichtag'] ?? null);
    $einnahmen = income_total($jahr);
    $ausgaben = expense_total($jahr);
    $verfuegbar = $budget + $einnahmen;
    $zugesagt = expense_total_status($jahr, 'einnahme', 'zugesagt');
    $geplantBuchungen = expense_total_status($jahr, 'ausgabe', 'geplant');
    $veranstaltungen = function_exists('events_geplante_kosten') ? events_geplante_kosten($jahr) : ['gesamt' => 0.0, 'verpflegung' => 0.0, 'anzahl' => 0];
    $geplant = $geplantBuchungen + (float)$veranstaltungen['gesamt'];
    return [
        'budget'     => $budget,
        'einnahmen'  => $einnahmen,
        'ausgaben'   => $ausgaben,
        'verfuegbar' => $verfuegbar,
        'frei'       => $verfuegbar - $ausgaben,
        'quote'      => $verfuegbar > 0 ? min(100.0, $ausgaben / $verfuegbar * 100) : 0.0,
        // Einnahmen: zugesagt und Forderungen (abgerechnet, gestellt) stecken nicht in einnahmen
        'einnahmen_zugesagt'    => $zugesagt,
        'verfuegbar_mit_zusagen' => $verfuegbar + $zugesagt,
        'frei_mit_zusagen'       => $verfuegbar + $zugesagt - $ausgaben,
        'einnahmen_forderungen' => expense_total_status($jahr, 'einnahme', 'abgerechnet') + expense_total_status($jahr, 'einnahme', 'gestellt'),
        'abrechnungen_wartend'  => einnahmen_wartend($jahr),
        'einnahmen_offen'       => expense_total_status($jahr, 'einnahme', 'abgerechnet') + expense_total_status($jahr, 'einnahme', 'gestellt'),
        // Ausgaben: Rechnung erhalten, noch nicht bezahlt – steckt schon in ausgaben
        'ausgaben_offen'  => expense_total_status($jahr, 'ausgabe', 'offen'),
        // Stichtag: ab dann keine Ausgaben mehr auf das Jahresbudget
        'stichtag'        => $stichtag['stichtag'],
        'stichtag_tage'   => $stichtag['tage'],
        'gesperrt'        => $stichtag['gesperrt'],
        // Planung: geplante Buchungen plus Veranstaltungen (Kosten und Verpflegung abzüglich schon Gebuchtem)
        'geplant_buchungen'       => $geplantBuchungen,
        'geplant_veranstaltungen' => (float)$veranstaltungen['gesamt'],
        'geplant_verpflegung'     => (float)$veranstaltungen['verpflegung'],
        'geplant_anzahl'          => (int)$veranstaltungen['anzahl'],
        'geplant'                 => $geplant,
        'frei_nach_planung'       => $verfuegbar - $ausgaben - $geplant,
    ];
}

/** Bereits verbuchte Ausgaben je Budgettopf */
function expense_by_budget(int $jahr): array
{
    $out = [];
    foreach (db_all(
        'SELECT budget_id, SUM(betrag_brutto) AS betrag
         FROM expenses WHERE jahr = ? AND art = ? AND budget_id IS NOT NULL AND ' . BUCHUNG_IST . ' GROUP BY budget_id',
        [$jahr, 'ausgabe']
    ) as $r) {
        $out[(int)$r['budget_id']] = ['betrag' => (float)$r['betrag']];
    }
    return $out;
}

/**
 * Buchung aus dem Formular speichern. Gibt [id, fehler[]] zurück.
 */
function expense_save_from_post(?array $existing, array $user): array
{
    $errors = [];
    $art = buchungsart(post_str('art', (string)($existing['art'] ?? 'ausgabe')));

    $bezeichnung = post_str('bezeichnung');
    if ($bezeichnung === '') {
        $errors[] = 'Bitte eine Bezeichnung angeben.';
    }

    $datum = post_date('datum');
    if (!$datum) {
        $errors[] = 'Bitte ein gültiges Datum angeben.';
    }

    // Einnahmen: Daten und Beträge je Stufe
    $stufen = [];
    if ($art === 'einnahme') {
        foreach (EINNAHME_STUFEN_FELDER as $key => $f) {
            if ($key === 'bezahlt') {
                continue;
            }
            $stufen[$f['datum']] = post_date($f['datum']);
            $wert = trim(post_str($f['betrag']));
            $stufen[$f['betrag']] = $wert === '' ? null : round(post_dec($f['betrag']), 2);
            if (!empty($f['nr'])) {
                $stufen[$f['nr']] = mb_substr(post_str($f['nr']), 0, 100);
            }
        }
    }

    // Ein Betrag, keine Mehrwertsteuer: Beide Spalten tragen denselben Wert,
    // damit ältere Auswertungen und Sicherungen weiter passen. Bei Einnahmen
    // ohne eigenen Betrag gilt der der letzten Stufe.
    $betrag = round(post_dec('betrag'), 2);
    if ($betrag <= 0) {
        foreach (['zugesagt_betrag', 'gestellt_betrag', 'abgerechnet_betrag'] as $feld) {
            if (($stufen[$feld] ?? null) !== null && $stufen[$feld] > 0) {
                $betrag = $stufen[$feld];
                break;
            }
        }
    }
    if ($betrag <= 0) {
        $errors[] = 'Bitte einen Betrag größer als null angeben.';
    }
    // Mehrere Wünsche und Fahrzeuge je Buchung
    $wishIds = array_values(array_filter(array_map('intval', (array)post('wishes', [])), static fn($i) => $i > 0));
    $vehicleIds = array_values(array_filter(array_map('intval', (array)post('vehicles', [])), static fn($i) => $i > 0));
    if ($art === 'ausgabe' && $datum) {
        $gewaehltVorab = buchung_status(post_str('status', (string)($existing['status'] ?? 'bezahlt')));
        $jahrVorab = post_int('jahr') ?: (int)substr((string)$datum, 0, 4);
        $sperre = budget_sperre_pruefen($jahrVorab, (string)$datum, post_date('bezahlt_am') ? 'bezahlt' : $gewaehltVorab);
        if ($sperre !== null) {
            $errors[] = $sperre;
        }
    }
    $brutto = $betrag;
    $netto = $betrag;
    $mwst = 0.0;

    if ($errors) {
        return [null, $errors];
    }

    $data = [
        'art'           => $art,
        'jahr'          => post_int('jahr') ?: (int)substr((string)$datum, 0, 4),
        'datum'         => $datum,
        'bezeichnung'   => mb_substr($bezeichnung, 0, 200),
        'beschreibung'  => post_str('beschreibung'),
        'kategorie_id'  => post_int('kategorie_id'),
        'fachgruppe_id' => post_int('fachgruppe_id'),
        'budget_id'     => post_int('budget_id'),
        'wish_id'       => $wishIds[0] ?? post_int('wish_id'),
        'event_id'      => post_int('event_id'),
        'bestellung_id' => post_int('bestellung_id'),
        'betrag_brutto' => $brutto,
        'mwst_satz'     => $mwst,
        'betrag_netto'  => $netto,
        'lieferant'     => mb_substr(post_str('lieferant'), 0, 150),
        'beleg_nr'      => mb_substr(post_str('beleg_nr'), 0, 100),
        'referenz'      => mb_substr(post_str('referenz'), 0, 100),
        'bezahlt_am'    => post_date('bezahlt_am'),
        'notiz'         => post_str('notiz'),
        'updated_by'    => (int)$user['id'],
    ];
    // Ein Zahlungsdatum heißt bezahlt, egal was der Stand sagt; bei Einnahmen
    // hebt jedes Stufendatum auf seine Stufe
    $gewaehlt = buchung_status(post_str('status', (string)($existing['status'] ?? 'bezahlt')));
    if ($art === 'einnahme') {
        $data += $stufen;
        $data['status'] = einnahme_stufe($data, array_key_exists($gewaehlt, EINNAHME_STUFEN) ? $gewaehlt : 'bezahlt');
    } else {
        $data['status'] = $data['bezahlt_am'] ? 'bezahlt' : (array_key_exists($gewaehlt, BUCHUNG_STATUS) ? $gewaehlt : 'bezahlt');
    }

    if ($existing) {
        db_update('expenses', $data, 'id = ?', [$existing['id']]);
        $id = (int)$existing['id'];
        audit($art . '.bearbeitet', 'expense', $id, $data['bezeichnung'] . ' / ' . money($brutto));
    } else {
        $data['created_by'] = (int)$user['id'];
        $id = db_insert('expenses', $data);
        audit($art . '.erfasst', 'expense', $id, $data['bezeichnung'] . ' / ' . money($brutto));
    }
    if ((isset($_POST['wishes']) || isset($_POST['vehicles']) || isset($_POST['bezuege'])) && function_exists('expense_links_speichern')) {
        expense_links_speichern($id, $wishIds, $vehicleIds);
    }

    return [$id, []];
}
