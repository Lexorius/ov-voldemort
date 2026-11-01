<?php
declare(strict_types=1);
/* Dubletten im Kontaktmodul: finden, Gruppen, Vorschlag, Zusammenführen (rein und gegen die Attrappe) */
session_start();
$GLOBALS['settings'] = ['telefon_landesvorwahl' => '+49', 'kontakte_extra_felder' => "funk|Funkrufname|text\nmitglied|Fördermitglied|bool"];
$GLOBALS['kontakte'] = [];
$GLOBALS['execs'] = [];
$GLOBALS['updates'] = [];
$GLOBALS['tx'] = [];

function db_all(string $sql, array $p = []): array {
    if (str_contains($sql, 'FROM settings')) {
        $r = [];
        foreach ($GLOBALS['settings'] as $k => $v) {
            $r[] = ['skey' => $k, 'svalue' => $v, 'sgroup' => 'x', 'stype' => 'text', 'label' => '', 'hint' => '', 'sort_order' => 0];
        }
        return $r;
    }
    return [];
}
function db_row(string $sql, array $p = []): ?array {
    if (str_contains($sql, 'FROM contacts WHERE id = ?')) {
        return $GLOBALS['kontakte'][(int)$p[0]] ?? null;
    }
    return null;
}
function db_val(string $sql, array $p = [], mixed $d = null) { return $d; }
function db_exec(string $sql, array $p = []): int { $GLOBALS['execs'][] = [$sql, $p]; return str_starts_with($sql, 'UPDATE IGNORE') ? 1 : 0; }
function db_insert(string $t, array $d): int { return 1; }
function db_update(string $t, array $d, string $w, array $p): int { $GLOBALS['updates'][] = [$t, $d, $p]; return 1; }
function can(string $was, mixed $ctx = null): bool { return true; }
function current_user(): ?array { return ['id' => 1]; }
final class StubStmt { public function execute(array $p): bool { return true; } }
final class StubPdo {
    public function prepare(string $s): StubStmt { return new StubStmt(); }
    public function beginTransaction(): bool { $GLOBALS['tx'][] = 'begin'; return true; }
    public function commit(): bool { $GLOBALS['tx'][] = 'commit'; return true; }
    public function rollBack(): bool { $GLOBALS['tx'][] = 'rollback'; return true; }
}
function db(): StubPdo { return new StubPdo(); }

$app = dirname(__DIR__);
foreach (['util', 'settings', 'lists', 'import', 'contacts', 'kontakt_dubletten'] as $lib) {
    require $app . '/src/lib/' . $lib . '.php';
}

$ok = 0; $fail = 0;
$check = function (string $n, bool $c, mixed $info = null) use (&$ok, &$fail) {
    if ($c) { $ok++; } else { $fail++; echo "FAIL: $n" . ($info !== null ? ' – ' . var_export($info, true) : '') . "\n"; }
};

/** Kontaktzeile mit allen Spalten */
function k(int $id, array $x): array {
    return $x + ['id' => $id, 'anrede' => '', 'titel' => '', 'vorname' => '', 'nachname' => '', 'organisation' => '',
        'position' => '', 'kategorie_id' => null, 'email' => '', 'telefon' => '', 'mobil' => '', 'strasse' => '', 'plz' => '',
        'ort' => '', 'land' => '', 'anschreiben' => '', 'notiz' => '', 'extra' => null, 'is_active' => 1,
        'created_at' => '2026-01-0' . min(9, $id) . ' 10:00:00'];
}

/* ---------- Finden ---------- */
$kontakte = [
    k(1,  ['vorname' => 'Anna', 'nachname' => 'Berg', 'organisation' => 'Stadt', 'email' => 'anna.berg@x.de', 'telefon' => '0221 1234567']),
    k(2,  ['vorname' => 'Anna', 'nachname' => 'Berg', 'organisation' => 'Stadt Musterstadt', 'email' => 'ANNA.BERG@X.DE ']),
    k(3,  ['vorname' => 'Bernd', 'nachname' => 'Muster', 'organisation' => 'Feuerwehr', 'mobil' => '0160 9876543']),
    k(4,  ['vorname' => 'B.', 'nachname' => 'Muster', 'organisation' => 'Feuerwehr', 'telefon' => '+49 160 9876543']),
    k(5,  ['vorname' => 'Klaus', 'nachname' => 'Meier', 'organisation' => 'Firma A', 'email' => 'info@firma.de']),
    k(6,  ['vorname' => 'Petra', 'nachname' => 'Schulz', 'organisation' => 'Firma A', 'email' => 'info@firma.de']),
    k(7,  ['vorname' => 'Hans', 'nachname' => 'Müller', 'organisation' => 'Rathaus']),
    k(8,  ['vorname' => 'hans', 'nachname' => 'Mueller']),
    k(9,  ['vorname' => 'Jonas', 'nachname' => 'Schmidt', 'organisation' => 'Kreis X']),
    k(10, ['vorname' => 'Jonas', 'nachname' => 'Schmid', 'organisation' => 'Kreis X']),
    k(11, ['organisation' => 'Freiwillige Feuerwehr Musterstadt']),
    k(12, ['organisation' => 'Freiwillige-Feuerwehr  Musterstadt']),
    k(13, ['vorname' => 'Lisa', 'nachname' => 'Krause', 'telefon' => '112']),
    k(14, ['vorname' => 'Tom', 'nachname' => 'Krause', 'telefon' => '112']),
    k(15, ['vorname' => 'Anna', 'nachname' => 'Berg', 'organisation' => 'Kreis', 'is_active' => 0]),
    k(16, ['nachname' => 'Weber', 'organisation' => 'Presse']),
    k(17, ['nachname' => 'Weber', 'organisation' => 'Presse']),
    k(18, ['vorname' => 'Eva', 'nachname' => 'Weber', 'organisation' => 'Verlag']),
];

$paare = dublette_paare($kontakte);
$p = static fn(int $a, int $b) => $paare[dublette_paar($a, $b)] ?? null;

$check('Anna Berg: gleiche E-Mail trotz Schreibweise, sicher', ($p(1, 2)['stufe'] ?? '') === 'sicher', $p(1, 2));
$check('Anna Berg: andere Organisation wird als Grund genannt', in_array('gleicher Name, andere Organisation', $p(1, 2)['gruende'] ?? [], true));
$check('Muster: gleiche Rufnummer über Telefon und Mobil, Initiale passt', ($p(3, 4)['stufe'] ?? '') === 'sicher'
    && in_array('gleiche Rufnummer', $p(3, 4)['gruende'], true), $p(3, 4));
$check('Firma A: gleiches Postfach, anderer Name ist nur möglich', ($p(5, 6)['stufe'] ?? '') === 'moeglich'
    && $p(5, 6)['gruende'] === ['gleiche E-Mail-Adresse, aber anderer Name'], $p(5, 6));
$check('Müller/Mueller: Umlaut und Großschreibung egal, Organisation fehlt einmal', ($p(7, 8)['stufe'] ?? '') === 'sicher', $p(7, 8));
$check('Schmidt/Schmid: Tippfehler bei gleicher Organisation', ($p(9, 10)['stufe'] ?? '') === 'wahrscheinlich'
    && $p(9, 10)['gruende'] === ['ähnlicher Nachname (Tippfehler?)'], $p(9, 10));
$check('Organisationen ohne Ansprechpartner', ($p(11, 12)['stufe'] ?? '') === 'sicher', $p(11, 12));
$check('Geschwister mit kurzer Nummer sind keine Dublette', $p(13, 14) === null, $p(13, 14));
$check('Anna Berg beim Kreis: gleicher Name, andere Organisation', ($p(1, 15)['stufe'] ?? '') === 'moeglich', $p(1, 15));
$check('Weber/Weber ohne Vornamen bei Presse', ($p(16, 17)['stufe'] ?? '') === 'wahrscheinlich', $p(16, 17));
$check('Eva Weber beim Verlag passt zu keinem Weber', $p(16, 18) === null && $p(17, 18) === null);
$check('kein Paar mit sich selbst', !isset($paare['1:1']));

$gruppen = kontakt_dubletten_finden($kontakte);
$idsVon = static fn(array $gs) => array_map(static fn($g) => $g['ids'], $gs);
$check('sieben Gruppen', count($gruppen) === 7, $idsVon($gruppen));
$check('Anna Berg: drei Kontakte in einer Gruppe, sicher zuerst', $gruppen[0]['ids'] === [1, 2, 15] && $gruppen[0]['stufe'] === 'sicher', $gruppen[0]);
$check('sichere vor wahrscheinlichen vor möglichen', array_column($gruppen, 'stufe')
    === ['sicher', 'sicher', 'sicher', 'sicher', 'wahrscheinlich', 'wahrscheinlich', 'moeglich'], array_column($gruppen, 'stufe'));
$check('Gründe je Gruppe gesammelt', in_array('gleiche E-Mail-Adresse', $gruppen[0]['gruende'], true)
    && in_array('gleicher Name, andere Organisation', $gruppen[0]['gruende'], true));

$gruppen2 = kontakt_dubletten_finden($kontakte, [[10, 9], [5, 6]]);
$check('gemerkte Paare fallen weg', count($gruppen2) === 5 && !in_array([9, 10], $idsVon($gruppen2), true)
    && !in_array([5, 6], $idsVon($gruppen2), true), $idsVon($gruppen2));
$check('leere Liste', kontakt_dubletten_finden([]) === []);
$check('Stufe lesbar', dublette_stufe('moeglich')[0] === 'möglich' && dublette_stufe('sicher')[1] === '#b91c1c');

/* ---------- Vorschlag und Zusammenführen (rein) ---------- */
$a = k(21, ['vorname' => 'Anna', 'nachname' => 'Berg', 'email' => 'anna@x.de', 'telefon' => '0221 1', 'notiz' => 'Erster Kontakt 2024',
            'kategorie_id' => 5, 'extra' => json_encode(['funk' => 'Heros 1'])]);
$b = k(22, ['vorname' => 'Anna', 'nachname' => 'Berg', 'organisation' => 'Stadt', 'mobil' => '0151 2', 'ort' => 'Köln', 'notiz' => 'Zuständig für Katastrophenschutz',
            'is_active' => 0, 'extra' => json_encode(['funk' => 'Heros 2', 'mitglied' => '1'])]);
$c = k(23, ['nachname' => 'Berg', 'email' => 'berg@alt.de', 'notiz' => 'Erster Kontakt 2024']);
$menge = [21 => $a, 22 => $b, 23 => $c];
$extra = contact_extra_fields();
$check('Zusatzfelder aus den Einstellungen gelesen', array_keys($extra) === ['funk', 'mitglied'], $extra);

$v = dublette_vorschlag($menge, 21, $extra);
$check('Vorschlag: Ziel liefert, was es hat', $v['email'] === 21 && $v['telefon'] === 21 && $v['vorname'] === 21);
$check('Vorschlag: Lücken füllt der erste mit Wert', $v['organisation'] === 22 && $v['mobil'] === 22 && $v['ort'] === 22);
$check('Vorschlag: zwei verschiedene Notizen heißen alle', $v['notiz'] === 'alle');
$check('Vorschlag: Zusatzfeld aus dem Ziel, fehlendes vom anderen', $v['extra:funk'] === 21 && $v['extra:mitglied'] === 22);
$check('Vorschlag: nirgends ein Wert fällt auf das Ziel', $v['land'] === 21);

$v2 = dublette_vorschlag([21 => $a, 23 => $c], 23, $extra);
$check('Vorschlag mit gleichen Notizen ist kein alle', $v2['notiz'] === 23 && $v2['email'] === 23 && $v2['vorname'] === 21);

$d = dublette_zusammenfuehren($menge, 21, ['email' => 23, 'notiz' => 'alle', 'ort' => 99, 'extra:funk' => '22', 'kategorie_id' => 22], $extra);
$check('Zusammenführen: gewählte E-Mail', $d['email'] === 'berg@alt.de');
$check('Zusammenführen: Notizen untereinander, Dubletten im Text nur einmal', $d['notiz'] === "Erster Kontakt 2024\n\nZuständig für Katastrophenschutz", $d['notiz']);
$check('Zusammenführen: fremde Nummer fällt auf den Vorschlag', $d['ort'] === 'Köln');
$check('Zusammenführen: Zusatzfelder zusammengelegt', json_decode($d['extra'], true) === ['funk' => 'Heros 2', 'mitglied' => '1'], $d['extra']);
$check('Zusammenführen: Kategorie vom Kontakt ohne Kategorie wird leer', $d['kategorie_id'] === null);
$check('Zusammenführen: aktiv, wenn einer aktiv war', $d['is_active'] === 1);
$check('Zusammenführen: Vorname aus dem Ziel', $d['vorname'] === 'Anna' && $d['organisation'] === 'Stadt');

$d2 = dublette_zusammenfuehren([22 => $b, 23 => $c], 23, ['notiz' => 22], $extra);
$check('Zusammenführen: eine Notiz gewählt statt alle', $d2['notiz'] === 'Zuständig für Katastrophenschutz');
$check('Zusammenführen: beide inaktiv bleibt inaktiv', dublette_zusammenfuehren([22 => $b, 24 => k(24, ['nachname' => 'X', 'is_active' => 0])], 22, [])['is_active'] === 0);

$check('Ziel-Vorschlag: der vollste Kontakt', dublette_ziel_vorschlag($menge) === 22);
$check('Ziel-Vorschlag: Verweise zählen mit', dublette_ziel_vorschlag($menge, [23 => ['Verteiler' => 9]]) === 23);
$check('Ziel-Vorschlag: bei Gleichstand der älteste', dublette_ziel_vorschlag([31 => k(31, ['nachname' => 'A']), 30 => k(30, ['nachname' => 'B'])]) === 30);

/* ---------- Zusammenführen gegen die Attrappe ---------- */
$GLOBALS['kontakte'] = $menge;
$erg = kontakt_dubletten_zusammenfuehren(21, [22, 23, 21, 0], ['notiz' => 'alle'], ['id' => 7]);
$check('Ergebnis: Ziel, gelöscht, umgehängt', $erg['ziel'] === 21 && $erg['geloescht'] === 2 && $erg['umgehaengt'] === 2 * count(DUBLETTE_VERWEISE) && $erg['name'] === 'Anna Berg', $erg);
$check('Transaktion begonnen und abgeschlossen', $GLOBALS['tx'] === ['begin', 'commit']);
$check('Ziel aktualisiert mit Bearbeiter', count($GLOBALS['updates']) === 1 && $GLOBALS['updates'][0][0] === 'contacts'
    && $GLOBALS['updates'][0][1]['updated_by'] === 7 && $GLOBALS['updates'][0][2] === [21]);
$sqls = array_column($GLOBALS['execs'], 0);
$check('Gästenamen gesichert', substr_count(implode("\n", $sqls), 'UPDATE event_guests SET name') === 2);
$check('alle Verweistabellen je Quelle umgehängt', count(array_filter($sqls, static fn($s) => str_starts_with($s, 'UPDATE IGNORE'))) === 2 * count(DUBLETTE_VERWEISE));
$umh = array_values(array_filter($GLOBALS['execs'], static fn($e) => str_starts_with($e[0], 'UPDATE IGNORE contact_group_members')));
$check('Verteiler: von Quelle auf Ziel', $umh[0][1] === [21, 22] && $umh[1][1] === [21, 23], $umh);
$loesch = array_values(array_filter($GLOBALS['execs'], static fn($e) => str_starts_with($e[0], 'DELETE FROM contacts')));
$check('Quellen gelöscht, Ziel nicht', array_map(static fn($e) => $e[1][0], $loesch) === [22, 23]);
$reihen = array_keys(array_filter($sqls, static fn($s) => str_starts_with($s, 'DELETE FROM contacts WHERE')));
$check('Löschen erst nach dem Umhängen', $reihen[0] > 0 && str_starts_with($sqls[$reihen[0] - 1], 'UPDATE IGNORE'));

$GLOBALS['tx'] = [];
try {
    kontakt_dubletten_zusammenfuehren(21, [21, 99], [], ['id' => 7]);
    $check('nur ein vorhandener Kontakt wird abgelehnt', false);
} catch (RuntimeException $ex) {
    $check('nur ein vorhandener Kontakt wird abgelehnt', $GLOBALS['tx'] === []);
}

/* ---------- Merkliste ---------- */
$GLOBALS['execs'] = [];
$n = kontakt_dubletten_ignorieren([5, 3, 9, 3, 0], ['id' => 2]);
$ins = array_filter($GLOBALS['execs'], static fn($e) => str_starts_with($e[0], 'INSERT IGNORE INTO contact_dubletten_ignoriert'));
$check('alle Paare der Gruppe gemerkt, kleinere Nummer zuerst', count($ins) === 3
    && array_map(static fn($e) => [$e[1][0], $e[1][1]], array_values($ins)) === [[3, 5], [5, 9], [3, 9]], array_values($ins));
kontakt_dubletten_wieder_pruefen(9, 2);
$check('wieder prüfen: Reihenfolge egal', end($GLOBALS['execs'])[1] === [2, 9]);

echo "$ok bestanden, $fail fehlgeschlagen\n";
