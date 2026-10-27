<?php
declare(strict_types=1);
/*
 * Protokoll per E-Mail: Empfänger aus Kontakten und Verteilern zusammenführen,
 * Dateiname, Vorlage, das PDF mit allen Teilen, der Versand mit Anhang.
 */
session_start();
$GLOBALS['settings'] = ['waehrung' => 'EUR', 'ov_name' => 'THW OV Musterstadt', 'protokoll_anmerkungen' => 'protokoll', 'protokoll_anmerkungen_namen' => '1'];
$GLOBALS['kontakte'] = [
    5 => ['id' => 5, 'vorname' => 'Anna', 'nachname' => 'Ärztin', 'organisation' => '', 'email' => 'anna@example.de', 'anrede' => '', 'titel' => ''],
    6 => ['id' => 6, 'vorname' => 'Ben', 'nachname' => 'Bauer', 'organisation' => '', 'email' => 'ben@example.de', 'anrede' => '', 'titel' => ''],
    7 => ['id' => 7, 'vorname' => 'Ohne', 'nachname' => 'Mail', 'organisation' => '', 'email' => '', 'anrede' => '', 'titel' => ''],
];
$GLOBALS['empfaenger'] = []; $GLOBALS['verteiler'] = []; $GLOBALS['mitglieder'] = [];
$GLOBALS['inserts'] = []; $GLOBALS['updates'] = []; $GLOBALS['execs'] = [];
function db_all(string $sql, array $p = []): array {
    if (str_contains($sql, 'FROM settings')) {
        $r = [];
        foreach ($GLOBALS['settings'] as $k => $v) { $r[] = ['skey'=>$k,'svalue'=>$v,'sgroup'=>'x','stype'=>'text','label'=>'','hint'=>'','sort_order'=>0]; }
        return $r;
    }
    if (str_contains($sql, 'FROM meeting_protokoll_empfaenger')) { return $GLOBALS['empfaenger']; }
    if (str_contains($sql, 'FROM meeting_protokoll_verteiler')) { return $GLOBALS['verteiler']; }
    if (str_contains($sql, 'FROM contact_group_members')) { return $GLOBALS['mitglieder'][(int)($p[0] ?? 0)] ?? []; }
    return [];
}
function db_row(string $sql, array $p = []): ?array {
    if (str_contains($sql, 'FROM contacts')) { return $GLOBALS['kontakte'][(int)($p[0] ?? 0)] ?? null; }
    if (str_contains($sql, 'FROM contact_groups')) { return (int)($p[0] ?? 0) === 9 ? ['id' => 9, 'name' => 'Helfer'] : null; }
    return null;
}
function db_val(string $sql, array $p = [], mixed $d = null) { return $d; }
function db_exec(string $sql, array $p = []): int { $GLOBALS['execs'][] = [$sql, $p]; return 1; }
function db_insert(string $t, array $d): int { $GLOBALS['inserts'][] = [$t, $d]; return count($GLOBALS['inserts']); }
function db_update(string $t, array $d, string $w, array $p): int { $GLOBALS['updates'][] = [$t, $d, $p]; return 1; }
function can(string $was, mixed $ctx = null): bool { return true; }
function current_user(): ?array { return ['id' => 1, 'display_name' => 'Anna Admin']; }
$app = dirname(__DIR__);
foreach (['util', 'settings', 'lists', 'view', 'mail', 'contacts', 'meetings', 'attendance', 'pdf', 'protokoll'] as $lib) { require $app . '/src/lib/' . $lib . '.php'; }

$ok = 0; $fail = 0;
$check = function (string $n, bool $c) use (&$ok, &$fail) { if ($c) { $ok++; } else { $fail++; echo "FAIL: $n\n"; } };
$k = $GLOBALS['kontakte'];

/* ---------- Zusammenführen ---------- */
$alle = meeting_protokoll_zusammenfuehren([$k[6], $k[7]], [[$k[5], $k[6]], [$k[6]]]);
$check('einmal je Kontakt, nur mit E-Mail, nach Namen', array_column($alle, 'id') === [5, 6]);
$check('leer bleibt leer', meeting_protokoll_zusammenfuehren([], []) === []);

/* ---------- Empfänger setzen ---------- */
$GLOBALS['inserts'] = [];
$check('Kontakte setzen: ohne Mail und unbekannt fallen weg', meeting_protokoll_empfaenger_setzen(3, [5, 5, 7, 99, 6]) === 2
    && count($GLOBALS['inserts']) === 2 && $GLOBALS['inserts'][0] === ['meeting_protokoll_empfaenger', ['meeting_id' => 3, 'contact_id' => 5]]
    && str_contains($GLOBALS['execs'][0][0], 'DELETE FROM meeting_protokoll_empfaenger'));
$GLOBALS['inserts'] = [];
$check('Verteiler setzen: nur vorhandene', meeting_protokoll_verteiler_setzen(3, [9, 8]) === 1 && $GLOBALS['inserts'][0] === ['meeting_protokoll_verteiler', ['meeting_id' => 3, 'group_id' => 9]]);
$GLOBALS['empfaenger'] = [$k[6]];
$GLOBALS['verteiler'] = [['id' => 9, 'name' => 'Helfer']];
$GLOBALS['mitglieder'] = [9 => [$k[5], $k[6], $k[7]]];
$check('Empfänger: Kontakte plus Verteilermitglieder', array_column(meeting_protokoll_empfaenger(3), 'id') === [5, 6]);

/* ---------- Dateiname, Zeit, Vorlage ---------- */
$meeting = ['id' => 3, 'titel' => 'Dienstbesprechung Oktober', 'datum' => '2026-10-27', 'ende_datum' => null, 'beginn' => '19:00:00', 'ende' => '21:15:00',
            'ort' => 'Unterkunft', 'treffen' => 'inhaus', 'link' => '', 'typ_label' => 'Dienstbesprechung', 'leitung' => 'Max Muster', 'protokoll_von' => 'Anna Admin',
            'teilnehmer' => 'Gast Gustav', 'beschreibung' => '', 'notizen' => "Nächstes Mal früher.\nKaffee besorgen.", 'status' => 'abgeschlossen'];
$check('Dateiname', meeting_protokoll_dateiname($meeting) === 'protokoll_2026-10-27_dienstbesprechung-oktober.pdf'
    && meeting_protokoll_dateiname(['titel' => 'Übung „Kabel"!', 'datum' => '2026-01-02']) === 'protokoll_2026-01-02_uebung-kabel.pdf');
$check('Wann', meeting_protokoll_wann($meeting) === '27.10.2026, 19:00 Uhr bis 21:15 Uhr');
$v = meeting_protokoll_vorlage($meeting, current_user(), 'THW OV Musterstadt');
$check('Vorlage', $v['betreff'] === 'Protokoll Dienstbesprechung Oktober vom 27.10.2026' && str_contains($v['text'], 'anbei das Protokoll der Besprechung „Dienstbesprechung Oktober" vom 27.10.2026 als PDF')
    && str_ends_with($v['text'], "Anna Admin\nTHW OV Musterstadt"));

/* ---------- Das PDF ---------- */
$daten = [
    'punkte' => [
        ['id' => 11, 'titel' => 'Fahrzeuge', 'fachgruppe_label' => 'FGr N', 'beschreibung' => 'Zustand der Fahrzeuge', 'status_label' => 'erledigt', 'status_slug' => 'erledigt', 'verantwortlich' => 'Ben', 'ergebnis' => 'GKW 1 zur HU.'],
        ['id' => 12, 'titel' => 'Sommerfest', 'fachgruppe_label' => '', 'beschreibung' => '', 'status_label' => 'vertagt', 'status_slug' => 'vertagt', 'verantwortlich' => '', 'ergebnis' => ''],
    ],
    'teilnehmer' => [
        ['anzeige' => 'Anna Ärztin', 'status_slug' => 'teilgenommen'], ['anzeige' => 'Ben Bauer', 'status_slug' => 'teilgenommen'],
        ['anzeige' => 'Clara', 'status_slug' => 'entschuldigt'], ['anzeige' => 'Dirk', 'status_slug' => 'fehlt'],
    ],
    'aufgaben' => [['id' => 44, 'titel' => 'HU-Termin machen', 'talking_point_id' => 11, 'tp_titel' => 'Fahrzeuge', 'faellig_am' => '2026-11-15']],
    'anmerkungen' => [11 => [['autor' => 'Clara', 'created_at' => '2026-10-26 10:00:00', 'body' => 'Bitte Reifen prüfen.']]],
    'mitNamen' => true, 'tpLinks' => [], 'tpEvents' => [], 'tpBudgets' => [], 'ov_name' => 'THW OV Musterstadt',
];
$pdf = meeting_protokoll_pdf($meeting, $daten, 1792900000);
$check('PDF gültig mit Titel', str_starts_with($pdf, '%PDF-1.4') && str_contains($pdf, '/Title (Protokoll Dienstbesprechung Oktober') && str_contains($pdf, '/Count 1'));
foreach (['(THW OV Musterstadt) Tj', '(Dienstbesprechung Oktober) Tj', '(Protokoll) Tj', '(27.10.2026, 19:00 Uhr bis 21:15 Uhr) Tj', '(Unterkunft', '(Max Muster) Tj',
          "(Anna \xc4rztin, Ben Bauer) Tj", '(Gast Gustav) Tj', '(Clara) Tj', '(Dirk) Tj', '(TOP 1: Fahrzeuge) Tj', '(FGr N) Tj', '(Zustand der Fahrzeuge) Tj',
          "(erledigt \xb7 verantwortlich: Ben \xb7 Aufgabe #44) Tj", '(GKW 1 zur HU.) Tj', '(TOP 2: Sommerfest) Tj', '(vertagt) Tj', '(Anmerkungen) Tj', "(Clara, 26.10.: Bitte Reifen pr\xfcfen.) Tj",
          '(Aufgaben) Tj', "(#44 HU-Termin machen \xb7 zu: Fahrzeuge \xb7 bis 15.11.2026) Tj", '(Notizen) Tj', "(N\xe4chstes Mal fr\xfcher.) Tj", '(Kaffee besorgen.) Tj', '(Seite 1 von 1) Tj'] as $t) {
    $check('PDF enthält ' . $t, str_contains($pdf, $t));
}
$check('Treffen online: Art des Treffens im PDF', (bool)preg_match('/\(Webex \\\\\([^)]*\\\\\)\) Tj/', meeting_protokoll_pdf(['treffen' => 'online', 'ort' => 'Webex', 'link' => 'https://x'] + $meeting, $daten)));
$check('ohne Punkte: Hinweis', str_contains(meeting_protokoll_pdf($meeting, ['punkte' => [], 'teilnehmer' => [], 'aufgaben' => [], 'anmerkungen' => []] + $daten), '(Keine Themen auf der Tagesordnung.) Tj'));

/* ---------- Versand ---------- */
$post = [];
$GLOBALS['mail_attrappe'] = static function (string $an, string $betreff, string $text, string $name, array $anhaenge = []) use (&$post): void { $post[] = [$an, $betreff, $text, $name, $anhaenge]; };
$GLOBALS['updates'] = [];
$r = meeting_protokoll_senden($meeting, current_user(), '', '', 1792900000);
$check('an beide Empfänger, je eine Mail mit PDF-Anhang', $r['ok'] && $r['gesendet'] === 2 && count($post) === 2 && $post[0][0] === 'anna@example.de' && $post[1][0] === 'ben@example.de'
    && $post[0][3] === 'Anna Ärztin' && $post[0][1] === 'Protokoll Dienstbesprechung Oktober vom 27.10.2026' && str_contains($post[0][2], 'anbei das Protokoll')
    && count($post[0][4]) === 1 && $post[0][4][0]['name'] === 'protokoll_2026-10-27_dienstbesprechung-oktober.pdf' && $post[0][4][0]['mime'] === 'application/pdf'
    && str_starts_with($post[0][4][0]['inhalt'], '%PDF-1.4') && $post[0][4][0]['inhalt'] === $post[1][4][0]['inhalt']);
$check('Versand vermerkt', $GLOBALS['updates'][0][0] === 'meetings' && $GLOBALS['updates'][0][1]['protokoll_gesendet_am'] === date('Y-m-d H:i:s', 1792900000)
    && $GLOBALS['updates'][0][1]['protokoll_gesendet_an'] === 'Anna Ärztin (anna@example.de), Ben Bauer (ben@example.de)' && str_contains($r['text'], 'Anna Ärztin (anna@example.de)'));
$post = [];
$r = meeting_protokoll_senden($meeting, current_user(), ' Eigener Betreff ', "Hallo\r\nText", 1792900000);
$check('eigener Betreff und Text', $post[0][1] === 'Eigener Betreff' && $post[0][2] === "Hallo\nText");
$GLOBALS['mail_attrappe'] = static function (string $an): void { if ($an === 'anna@example.de') { throw new MailException('Server weg'); } };
$r = meeting_protokoll_senden($meeting, current_user(), '', '');
$check('einer scheitert: trotzdem gelungen, benannt', $r['ok'] && $r['gesendet'] === 1 && str_contains($r['text'], 'nicht erreicht: Anna Ärztin: Server weg'));
$GLOBALS['empfaenger'] = []; $GLOBALS['verteiler'] = [];
$r = meeting_protokoll_senden($meeting, current_user(), '', '');
$check('ohne Empfänger kein Versand', !$r['ok'] && str_contains($r['text'], 'kein Kontakt'));

echo "$ok bestanden, $fail fehlgeschlagen\n";
