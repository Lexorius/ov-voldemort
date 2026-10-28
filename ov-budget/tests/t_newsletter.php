<?php
declare(strict_types=1);
/*
 * Newsletter: Kennung und Abmeldelink, Fußzeile, freie Adressen, Sperrliste
 * aus Prüfsummen, Empfänger zusammenführen, Upload, Formular, Planen,
 * Versand in Portionen, Probe, Ansichten.
 */
session_start();
define('OVB_TEST_UPLOADS', true);
$tmp = sys_get_temp_dir() . '/ovb-newsletter-test';
@mkdir($tmp, 0777, true);
foreach (glob($tmp . '/newsletter/*') ?: [] as $f) { @unlink($f); }
putenv('OVB_UPLOAD_DIR=' . $tmp);
$GLOBALS['settings'] = ['waehrung' => 'EUR', 'ov_name' => 'THW OV Musterstadt', 'newsletter_fusstext' => '', 'mail_aktiv' => '1', 'mail_host' => 'smtp.example', 'mail_absender' => 'ov@example.de', 'upload_max_mb' => '10'];
$GLOBALS['merker'] = []; $GLOBALS['inserts'] = []; $GLOBALS['updates'] = []; $GLOBALS['execs'] = [];
$GLOBALS['sperre'] = []; $GLOBALS['versand'] = []; $GLOBALS['geplant'] = []; $GLOBALS['laeuft'] = [];
$GLOBALS['kontakte'] = [5 => ['id' => 5, 'vorname' => 'Anna', 'nachname' => 'Ärztin', 'organisation' => '', 'email' => 'anna@example.de', 'anrede' => '', 'titel' => ''],
                        6 => ['id' => 6, 'vorname' => 'Ben', 'nachname' => 'Bauer', 'organisation' => '', 'email' => 'ben@example.de', 'anrede' => '', 'titel' => '']];
$GLOBALS['connector'] = ['id' => 3, 'name' => 'Feedback', 'url' => 'https://feedback.example.de', 'kurz_url' => '', 'is_active' => 1, 'fuer_newsletter' => 1, 'pem' => 'x', 'pubkey' => 'y', 'server_pub' => 'z'];
function db_all(string $sql, array $p = []): array {
    if (str_contains($sql, 'FROM settings')) {
        $r = [];
        foreach ($GLOBALS['settings'] as $k => $v) { $r[] = ['skey'=>$k,'svalue'=>$v,'sgroup'=>'x','stype'=>'text','label'=>'','hint'=>'','sort_order'=>0]; }
        return $r;
    }
    if (str_contains($sql, 'FROM newsletter_sperre')) { return $GLOBALS['sperre']; }
    if (str_contains($sql, 'FROM newsletter_versand') && str_contains($sql, 'gesendet_am IS NULL')) {
        $offen = array_values(array_filter($GLOBALS['versand'], fn($v) => $v['gesendet_am'] === null && $v['fehler'] === ''));
        return preg_match('/LIMIT (\d+)/', $sql, $m) ? array_slice($offen, 0, (int)$m[1]) : $offen;   // die Portion wie die Datenbank
    }
    if (str_contains($sql, 'FROM newsletter_versand')) { return $GLOBALS['versand']; }
    if (str_contains($sql, "status = 'geplant'")) { return $GLOBALS['geplant']; }
    if (str_contains($sql, "status = 'laeuft'")) { return $GLOBALS['laeuft']; }
    if (str_contains($sql, 'FROM newsletter_kontakte')) { return [['contact_id' => 5]]; }
    if (str_contains($sql, 'FROM newsletter_verteiler')) { return [['group_id' => 9]]; }
    if (str_contains($sql, 'FROM contact_group_members')) { return [$GLOBALS['kontakte'][5], $GLOBALS['kontakte'][6], ['id' => 7, 'vorname' => 'Ohne', 'nachname' => 'Mail', 'email' => '']]; }
    if (str_contains($sql, 'FROM newsletter n')) { return $GLOBALS['liste'] ?? []; }
    return [];
}
function db_row(string $sql, array $p = []): ?array {
    if (str_contains($sql, 'FROM newsletter_versand')) {
        $g = count($GLOBALS['versand']); $f = count(array_filter($GLOBALS['versand'], fn($v) => $v['gesendet_am'] !== null)); $e = count(array_filter($GLOBALS['versand'], fn($v) => $v['fehler'] !== ''));
        return ['gesamt' => $g, 'fertig' => $f, 'fehler' => $e];
    }
    if (str_contains($sql, 'FROM newsletter_sperre WHERE id')) { foreach ($GLOBALS['sperre'] as $s) { if ($s['id'] === (int)$p[0]) { return $s; } } return null; }
    if (str_contains($sql, 'FROM connectors')) { return (int)($p[0] ?? 0) === 3 ? $GLOBALS['connector'] : null; }
    if (str_contains($sql, 'FROM contacts')) { return $GLOBALS['kontakte'][(int)($p[0] ?? 0)] ?? null; }
    if (str_contains($sql, 'FROM newsletter n')) { return $GLOBALS['n'] ?? null; }
    return null;
}
function db_val(string $sql, array $p = [], mixed $d = null) {
    if (str_contains($sql, 'FROM settings')) { return $GLOBALS['merker'][$p[0] ?? ''] ?? $d; }
    if (str_contains($sql, 'FROM newsletter_sperre WHERE token')) { foreach ($GLOBALS['sperre'] as $s) { if ($s['token'] === $p[0]) { return $s['id']; } } return $d; }
    if (str_contains($sql, 'COUNT(*) FROM newsletter')) { return 3; }
    return $d;
}
function db_exec(string $sql, array $p = []): int { if (str_contains($sql, 'INTO settings')) { $GLOBALS['merker'][$p[0]] = (string)$p[1]; } $GLOBALS['execs'][] = [$sql, $p]; return 1; }
function db_insert(string $t, array $d): int {
    $GLOBALS['inserts'][] = [$t, $d];
    if ($t === 'newsletter_sperre') { $GLOBALS['sperre'][] = ['id' => 100 + count($GLOBALS['sperre'])] + $d; }
    if ($t === 'newsletter_versand') { $GLOBALS['versand'][] = ['id' => 200 + count($GLOBALS['versand']), 'gesendet_am' => null, 'fehler' => ''] + $d; }
    return 100 + count($GLOBALS['inserts']);
}
function db_update(string $t, array $d, string $w, array $p): int {
    $GLOBALS['updates'][] = [$t, $d, $p];
    if ($t === 'newsletter_versand') { foreach ($GLOBALS['versand'] as &$v) { if ($v['id'] === (int)$p[0]) { $v = $d + $v; } } unset($v); }
    return 1;
}
function can(string $was, mixed $ctx = null): bool { return true; }
function current_user(): ?array { return ['id' => 1, 'display_name' => 'Anna Admin', 'email' => 'admin@example.de']; }
$app = dirname(__DIR__);
foreach (['util', 'settings', 'lists', 'view', 'webpush', 'mail', 'contacts', 'uploads', 'connector', 'newsletter'] as $lib) { require $app . '/src/lib/' . $lib . '.php'; }
$user = current_user();

$ok = 0; $fail = 0;
$check = function (string $n, bool $c) use (&$ok, &$fail) { if ($c) { $ok++; } else { $fail++; echo "FAIL: $n\n"; } };

/* ---------- Kennung, Link, Fußzeile ---------- */
$g = newsletter_geheimnis();
$check('Geheimnis einmal erzeugt und gemerkt', strlen($g) === 64 && newsletter_geheimnis() === $g);
$k = newsletter_kennung(' Anna@Example.de ');
$check('Kennung: HMAC, unabhängig von Groß/Klein und Rand, ohne die Adresse', strlen($k) === 64 && $k === newsletter_kennung('anna@example.de') && $k !== newsletter_kennung('ben@example.de')
    && newsletter_kennung('a@b.de', 'x') !== newsletter_kennung('a@b.de', 'y'));
$check('Abmeldelink', newsletter_abmeldelink($GLOBALS['connector'], $k) === 'https://feedback.example.de/index.php?p=abmelden&a=' . $k);
$check('Fußzeile mit Vorgabe', newsletter_fuss('', 'https://x/ab', 'THW OV') === "\n\n--\nDu bekommst diesen Newsletter von THW OV. Abbestellen: https://x/ab");
$check('Fußzeile aus der Einstellung, Link ergänzt wenn er fehlt', str_contains(newsletter_fuss("Ortsverband {ov}\r\nAbmelden: {link}", 'L', 'OV'), "Ortsverband OV\nAbmelden: L")
    && str_ends_with(newsletter_fuss('Kein Platzhalter', 'L', 'OV'), "Kein Platzhalter\nAbbestellen: L"));
$check('freie Adressen', newsletter_adressen_parsen("info@example.de\nMax Muster <max@example.de>; kaputt, \"Anna\" <anna@example.de>\n") === [
    ['email' => 'info@example.de', 'name' => ''], ['email' => 'max@example.de', 'name' => 'Max Muster'], ['email' => 'anna@example.de', 'name' => 'Anna']]);

/* ---------- Sperrliste: nur Prüfsummen ---------- */
$check('sperren speichert nur die Kennung', newsletter_sperren('Ben@example.de', 'von Hand') && $GLOBALS['inserts'][0][0] === 'newsletter_sperre'
    && !array_key_exists('email', $GLOBALS['inserts'][0][1]) && $GLOBALS['inserts'][0][1]['token'] === newsletter_kennung('ben@example.de') && $GLOBALS['inserts'][0][1]['grund'] === 'von Hand');
$check('doppelt sperren: nein', !newsletter_sperren('ben@example.de', 'nochmal'));
$check('Abmeldungen übernehmen: nur gültige Kennungen, Bekannte nicht doppelt', newsletter_abmeldungen_uebernehmen([['kennung' => newsletter_kennung('ben@example.de'), 'ts' => 1792900000],
    ['kennung' => newsletter_kennung('clara@example.de'), 'ts' => 1792900000], ['kennung' => 'zu kurz']]) === 1 && count($GLOBALS['sperre']) === 2
    && str_contains($GLOBALS['sperre'][1]['grund'], 'Abmeldung über den Link'));
$mengen = newsletter_sperre_mengen();
$check('gesperrt: Ben und Clara, nicht Anna', newsletter_gesperrt('BEN@example.de', $mengen) && newsletter_gesperrt('clara@example.de', $mengen) && !newsletter_gesperrt('anna@example.de', $mengen));
newsletter_entsperren(101);
$check('entsperren löscht', str_contains(end($GLOBALS['execs'])[0], 'DELETE FROM newsletter_sperre'));

/* ---------- Empfänger ---------- */
$r = newsletter_empfaenger_zusammenfuehren([$GLOBALS['kontakte'][5], $GLOBALS['kontakte'][6], ['id' => 7, 'email' => '']],
    [['email' => 'Anna@example.de', 'name' => 'Doppelt'], ['email' => 'clara@example.de', 'name' => 'Clara'], ['email' => 'dora@example.de', 'name' => '']], $mengen);
$check('zusammenführen: einmal je Adresse, Gesperrte raus und gezählt, Name aus dem Kontakt', array_column($r['empfaenger'], 'email') === ['anna@example.de', 'dora@example.de']
    && $r['empfaenger'][0]['name'] === 'Anna Ärztin' && $r['gesperrt'] === 2);
$n = ['id' => 7, 'titel' => 'OV-Nachrichten Oktober', 'betreff' => '', 'text' => '', 'orig_name' => 'nachrichten.pdf', 'stored_name' => '', 'size_bytes' => 0,
      'status' => 'entwurf', 'senden_am' => null, 'connector_id' => 3, 'adressen' => "dora@example.de\nben@example.de"];
$r = newsletter_empfaenger_aufloesen($n);
$check('aus der Datenbank: Kontakt, Verteiler, frei – ohne Gesperrte', array_column($r['empfaenger'], 'email') === ['anna@example.de', 'dora@example.de'] && $r['gesperrt'] === 2);
$v = newsletter_vorlage($n, 'THW OV');
$check('Vorlage: Betreff aus dem Titel, kurzer Text', $v['betreff'] === 'OV-Nachrichten Oktober' && str_contains($v['text'], '„OV-Nachrichten Oktober" als PDF') && str_ends_with($v['text'], 'THW OV'));

/* ---------- Upload und Formular ---------- */
$pdfTmp = $tmp . '/probe.pdf';
file_put_contents($pdfTmp, "%PDF-1.4\n%test\n");
$check('Upload: nur PDF, nur echte PDFs', is_string(newsletter_upload_pruefen(['error' => 0, 'tmp_name' => $pdfTmp, 'name' => 'x.docx', 'size' => 10]))
    && is_array(newsletter_upload_pruefen(['error' => 0, 'tmp_name' => $pdfTmp, 'name' => 'x.pdf', 'size' => 10])) && is_string(newsletter_upload_pruefen(['error' => 4])));
file_put_contents($tmp . '/kein.pdf', 'hallo');
$check('Upload: Kopfzeile geprüft', newsletter_upload_pruefen(['error' => 0, 'tmp_name' => $tmp . '/kein.pdf', 'name' => 'x.pdf', 'size' => 5]) === 'Das ist kein PDF.');
$_POST = ['titel' => '', 'senden_am' => 'kaputt'];
[$id, $f] = newsletter_save_from_post(null, $user);
$check('Formular: Titel, Zeitpunkt, PDF bemängelt', $id === null && count($f) === 3);
$GLOBALS['inserts'] = []; $GLOBALS['updates'] = []; $GLOBALS['execs'] = [];
$_FILES = ['pdf' => ['error' => 0, 'tmp_name' => $pdfTmp, 'name' => 'nachrichten.pdf', 'size' => 15]];
$_POST = ['titel' => ' OV-Nachrichten ', 'betreff' => '', 'text' => "Hallo\r\nWelt", 'connector_id' => '3', 'senden_am' => '2026-11-02T08:00', 'adressen' => 'dora@example.de',
          'kontakte' => ['5'], 'kontakte_feld' => '1', 'verteiler' => ['9'], 'verteiler_feld' => '1'];
[$id, $f] = newsletter_save_from_post(null, $user);
$check('angelegt: Entwurf, Betreff aus Titel, Zeitpunkt, Datei abgelegt, KI eingereiht, Empfänger gesetzt', $id === 101 && $f === []
    && $GLOBALS['inserts'][0][1]['status'] === 'entwurf' && $GLOBALS['inserts'][0][1]['betreff'] === 'OV-Nachrichten' && $GLOBALS['inserts'][0][1]['senden_am'] === '2026-11-02 08:00:00'
    && $GLOBALS['inserts'][0][1]['text'] === "Hallo\nWelt" && count(glob($tmp . '/newsletter/nl_101_*.pdf')) === 1
    && $GLOBALS['updates'][0][1]['orig_name'] === 'nachrichten.pdf' && $GLOBALS['updates'][0][1]['mime'] === 'application/pdf'
    && in_array(['newsletter_kontakte', ['newsletter_id' => 101, 'contact_id' => 5]], $GLOBALS['inserts'], true) && in_array(['newsletter_verteiler', ['newsletter_id' => 101, 'group_id' => 9]], $GLOBALS['inserts'], true));
$stored = basename(glob($tmp . '/newsletter/nl_101_*.pdf')[0]);
[$id, $f] = newsletter_save_from_post(['id' => 7, 'status' => 'gesendet'], $user);
$check('Verschicktes lässt sich nicht ändern', $id === null && str_contains($f[0], 'nicht mehr ändern'));
$_POST = []; $_FILES = [];

/* ---------- Planen ---------- */
$n['stored_name'] = $stored;
$r = newsletter_planen(['connector_id' => 0] + $n, null, $user);
$check('planen ohne Connector', !$r['ok'] && str_contains($r['text'], 'Connector'));
$r = newsletter_planen(['stored_name' => ''] + $n, null, $user);
$check('planen ohne PDF', !$r['ok'] && str_contains($r['text'], 'PDF'));
$GLOBALS['inserts'] = []; $GLOBALS['updates'] = []; $GLOBALS['versand'] = [];
$r = newsletter_planen($n, '2026-11-02 08:00', $user);
$check('geplant: Versandzeilen mit Kennung, Status und Zeitpunkt', $r['ok'] && $r['anzahl'] === 2 && count($GLOBALS['versand']) === 2 && $GLOBALS['versand'][0]['email'] === 'anna@example.de'
    && $GLOBALS['versand'][0]['token'] === newsletter_kennung('anna@example.de') && $GLOBALS['versand'][0]['name'] === 'Anna Ärztin'
    && end($GLOBALS['updates'])[1] === ['status' => 'geplant', 'senden_am' => '2026-11-02 08:00:00'] && str_contains($r['text'], '2 gesperrte ausgelassen'));
$r = newsletter_planen(['status' => 'laeuft'] + $n, null, $user);
$check('läuft schon: nicht nochmal planen', !$r['ok']);

/* ---------- Versand in Portionen ---------- */
$post = [];
$GLOBALS['mail_attrappe'] = static function (string $an, string $betreff, string $text, string $name, array $anhaenge = []) use (&$post): void {
    if ($an === 'dora@example.de') { throw new MailException('Postfach voll'); }   // Dora ist in dieser Prüfung immer unerreichbar
    $post[] = [$an, $betreff, $text, $name, $anhaenge];
};
$jetzt = (int)strtotime('2026-11-02 08:00:30');
$GLOBALS['geplant'] = [['senden_am' => '2026-11-02 08:00:00'] + $n];
$GLOBALS['laeuft'] = [['status' => 'laeuft', 'connector_name' => 'Feedback'] + $n];
$GLOBALS['updates'] = [];
$r = newsletter_versand_abarbeiten(1, $jetzt);
$check('Portion 1: gestartet, eine Mail mit PDF und Abmeldelink', $r['gestartet'] === 1 && $r['gesendet'] === 1 && $r['fertig'] === 0 && count($post) === 1 && $post[0][0] === 'anna@example.de'
    && $post[0][1] === 'OV-Nachrichten Oktober' && str_contains($post[0][2], 'Abbestellen: https://feedback.example.de/index.php?p=abmelden&a=' . newsletter_kennung('anna@example.de'))
    && $post[0][4][0]['name'] === 'nachrichten.pdf' && str_starts_with($post[0][4][0]['inhalt'], '%PDF') && $GLOBALS['updates'][0][1]['status'] === 'laeuft'
    && $GLOBALS['versand'][0]['gesendet_am'] === date('Y-m-d H:i:s', $jetzt));
$GLOBALS['geplant'] = [];
$r = newsletter_versand_abarbeiten(10, $jetzt);
$check('Portion 2: Fehler vermerkt, Newsletter fertig', $r['gesendet'] === 0 && $r['fehler'] === 1 && $r['fertig'] === 1 && $GLOBALS['versand'][1]['fehler'] === 'Postfach voll'
    && end($GLOBALS['updates'])[1]['status'] === 'gesendet');
$stand = newsletter_versandstand(7);
$check('Versandstand', $stand === ['gesamt' => 2, 'fertig' => 1, 'fehler' => 1, 'offen' => 0]);
$GLOBALS['sperre'][] = ['id' => 150, 'token' => newsletter_kennung('anna@example.de'), 'grund' => '', 'gesperrt_am' => '2026-11-02 08:00:00'];
$GLOBALS['versand'] = [['id' => 300, 'newsletter_id' => 7, 'email' => 'anna@example.de', 'name' => '', 'token' => newsletter_kennung('anna@example.de'), 'gesendet_am' => null, 'fehler' => '']];
$post = [];
$r = newsletter_versand_abarbeiten(10, $jetzt);
$check('zwischenzeitlich gesperrt: nicht verschickt, vermerkt', $r['gesendet'] === 0 && $post === [] && $GLOBALS['versand'][0]['fehler'] === 'gesperrt');
$GLOBALS['settings']['mail_aktiv'] = '0'; settings_reset_cache();
$check('Versand aus: nichts', newsletter_versand_abarbeiten(10, $jetzt) === ['gestartet' => 0, 'gesendet' => 0, 'fehler' => 0, 'fertig' => 0]);
$GLOBALS['settings']['mail_aktiv'] = '1'; settings_reset_cache();

$post = [];
$r = newsletter_probe($n, 'admin@example.de', $user);
$check('Probe: eigene Adresse, Betreff markiert', $r['ok'] && $post[0][0] === 'admin@example.de' && $post[0][1] === '[Probe] OV-Nachrichten Oktober' && count($post[0][4]) === 1);
newsletter_abbrechen($n);
$check('abbrechen: offene Zeilen weg, Entwurf', str_contains(end($GLOBALS['execs'])[0], 'gesendet_am IS NULL') && end($GLOBALS['updates'])[1]['status'] === 'entwurf');

/* ---------- Ansichten ---------- */
$html = render_partial('newsletter', ['title' => 'Newsletter', 'rows' => [['id' => 7, 'titel' => 'OV-Nachrichten', 'orig_name' => 'n.pdf', 'size_bytes' => 1500, 'status' => 'geplant', 'senden_am' => '2026-11-02 08:00:00', 'gesendet_am' => null, 'versand_gesamt' => 2, 'versand_fertig' => 0, 'versand_fehler' => 0]],
    'blatt' => seiten_blaettern(1, 1, 50), 'darf' => true, 'mailAktiv' => false, 'connectoren' => [], 'gesperrt' => 2]);
$check('Liste: Hinweis nur für interne Newsletter', str_contains($html, 'Nur für interne Newsletter') && str_contains($html, 'nur mit Zustimmung') && str_contains($html, 'rechtlichen Voraussetzungen'));
$check('Liste', str_contains($html, 'OV-Nachrichten') && str_contains($html, 'ab 02.11.2026') && str_contains($html, 'Sperrliste (2)') && str_contains($html, 'Verwaltung → E-Mail') && str_contains($html, 'Verwendung „Newsletter"'));
$html = render_partial('newsletter_detail', ['n' => ['stored_name' => $stored, 'size_bytes' => 15] + $n, 'connector' => $GLOBALS['connector'], 'vorlage' => $v, 'fuss' => newsletter_fuss('', 'https://x', 'OV'),
    'empfaenger' => ['empfaenger' => [['email' => 'anna@example.de', 'name' => 'Anna']], 'gesperrt' => 1], 'stand' => ['gesamt' => 0, 'fertig' => 0, 'fehler' => 0, 'offen' => 0], 'versand' => [],
    'kiDaten' => ['ergebnisse' => [], 'auftraege' => []], 'darf' => true, 'mailAktiv' => true, 'meineMail' => 'admin@example.de']);
$check('Detail Entwurf: Hinweis vor dem Versand', str_contains($html, 'Nur für interne Newsletter'));
$check('Detail Entwurf: Text bearbeitbar, Planen und Sofort, Probe, Empfänger, Löschen', str_contains($html, 'name="action" value="text"') && str_contains($html, 'value="planen"')
    && str_contains($html, 'value="sofort"') && str_contains($html, 'value="probe"') && str_contains($html, '1 Adressen · 1 gesperrt') && str_contains($html, 'value="loeschen"') && str_contains($html, 'PDF ansehen'));
$html = render_partial('newsletter_detail', ['n' => ['status' => 'laeuft', 'stored_name' => $stored] + $n, 'connector' => $GLOBALS['connector'], 'vorlage' => $v, 'fuss' => '',
    'empfaenger' => ['empfaenger' => [], 'gesperrt' => 0], 'stand' => ['gesamt' => 2, 'fertig' => 1, 'fehler' => 1, 'offen' => 0],
    'versand' => [['email' => 'a@b.de', 'name' => 'A', 'gesendet_am' => '2026-11-02 08:00:00', 'fehler' => ''], ['email' => 'c@d.de', 'name' => '', 'gesendet_am' => null, 'fehler' => 'Postfach voll']],
    'kiDaten' => ['ergebnisse' => [], 'auftraege' => []], 'darf' => true, 'mailAktiv' => true, 'meineMail' => '']);
$check('Detail läuft: kein Hinweis mehr', !str_contains($html, 'Nur für interne Newsletter'));
$check('Detail läuft: Abbrechen, Versandtabelle mit Fehler, kein Text bearbeiten', str_contains($html, 'Versand abbrechen') && str_contains($html, 'Postfach voll') && !str_contains($html, 'name="action" value="text"') && !str_contains($html, 'value="loeschen"'));
$html = render_partial('newsletter_edit', ['n' => $n, 'errors' => ['Fehler X'], 'connectoren' => [$GLOBALS['connector']], 'kontaktOptionen' => [['id' => 5, 'text' => 'Anna', 'sub' => 'anna@example.de', 'gruppe' => '']], 'kontakteGewaehlt' => [5],
    'verteilerOptionen' => [['id' => 9, 'text' => 'Helfer', 'sub' => '3 Kontakt(e)', 'gruppe' => '']], 'verteilerGewaehlt' => [], 'maxMb' => 10]);
$check('Bearbeiten: Hinweis vor den Empfängern', str_contains($html, 'Nur für interne Newsletter') && strpos($html, 'Nur für interne Newsletter') < strpos($html, 'data-wahl="kontakte"'));
$check('Bearbeiten: PDF-Feld, Connector, Zeitpunkt, Kontakte und Verteiler, freie Adressen', str_contains($html, 'name="pdf"') && str_contains($html, 'value="3" selected') && str_contains($html, 'name="senden_am"')
    && str_contains($html, 'data-wahl="kontakte"') && str_contains($html, 'name="kontakte[]" value="5"') && str_contains($html, 'data-wahl="verteiler"') && str_contains($html, 'name="adressen"') && str_contains($html, 'Fehler X'));
$html = render_partial('newsletter_sperre', ['rows' => [['id' => 1, 'token' => str_repeat('ab', 32), 'grund' => 'Abmeldung', 'gesperrt_am' => '2026-11-01 10:00:00']], 'blatt' => seiten_blaettern(1, 1, 100), 'darf' => true, 'connectoren' => [$GLOBALS['connector']], 'letzterAbruf' => 0]);
$check('Sperrliste: nur Prüfsumme, Sperren und Aufheben, Abmeldungen holen', str_contains($html, 'abababababababab…') && !str_contains($html, '@') && str_contains($html, 'value="sperren"') && str_contains($html, 'value="entsperren"') && str_contains($html, 'value="abmeldungen"'));

foreach (glob($tmp . '/newsletter/*') ?: [] as $f) { @unlink($f); }
echo "$ok bestanden, $fail fehlgeschlagen\n";
