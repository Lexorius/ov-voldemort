<?php
declare(strict_types=1);
/*
 * Sicherheitsdurchsicht der Anwendung: Rücksprung nach dem Anmelden,
 * Formelschutz im CSV, Zeitgleichheit der Anmeldung.
 */
$GLOBALS['settings'] = [];
function db_all(string $sql, array $p = []): array { return []; }
function db_row(string $sql, array $p = []): ?array { return $GLOBALS['user'] ?? null; }
function db_val(string $sql, array $p = [], mixed $d = null) { return 0; }
function db_exec(string $sql, array $p = []): int { return 1; }
function db_insert(string $t, array $d): int { return 1; }
function db_update(string $t, array $d, string $w, array $p): int { return 1; }
function setting_int(string $k, int $d = 0): int { return $d; }
function render(string $v, array $vars = []): void {}
$app = dirname(__DIR__);
require $app . '/src/lib/util.php';
require $app . '/src/lib/auth.php';
require $app . '/src/lib/totp.php';

$ok = 0; $fail = 0;
$check = function (string $n, bool $c) use (&$ok, &$fail) { if ($c) { $ok++; } else { $fail++; echo "FAIL: $n\n"; } };

/* ---------- Rücksprung ---------- */
$check('interner Pfad gilt', url_ist_intern('/index.php?p=wishes&id=3'));
$check('Wurzel gilt', url_ist_intern('/'));
$check('fremder Server hinter // nicht', !url_ist_intern('//fremd.example/index.php'));
$check('Rückstrich-Variante nicht', !url_ist_intern('/\\fremd.example'));
$check('absolute Adresse nicht', !url_ist_intern('https://fremd.example/'));
$check('leer nicht', !url_ist_intern(''));
$check('Zeilenumbruch nicht', !url_ist_intern("/index.php\r\nSet-Cookie: x=1"));

$_SERVER['REQUEST_URI'] = '//fremd.example/x';
$check('current_url fällt auf das Dashboard zurück', current_url() === '/index.php?p=dashboard');
$_SERVER['REQUEST_URI'] = '/index.php?p=todos';
$check('current_url behält Internes', current_url() === '/index.php?p=todos');

/* ---------- CSV ---------- */
$z = csv_sicher(['=HYPERLINK("http://x")', '+cmd|calc', '-2+3', '@SUM(A1)', "\tformel", 'Normaler Text', '', 7, null]);
$check('Formel bekommt Hochkomma', $z[0] === "'=HYPERLINK(\"http://x\")");
$check('Plus-Formel bekommt Hochkomma', $z[1] === "'+cmd|calc");
$check('Minus-Formel bekommt Hochkomma', $z[2] === "'-2+3");
$check('@-Formel bekommt Hochkomma', $z[3] === "'@SUM(A1)");
$check('Tabulator am Anfang', $z[4] === "'\tformel");
$check('Text bleibt', $z[5] === 'Normaler Text');
$check('leer, Zahl, null bleiben', $z[6] === '' && $z[7] === 7 && $z[8] === null);

$z = csv_sicher(['+49 171 2345678', '-12,50', '+49 (0) 221 / 123-45', '-']);
$check('Rufnummer bleibt', $z[0] === '+49 171 2345678');
$check('negativer Betrag bleibt', $z[1] === '-12,50');
$check('Rufnummer mit Klammern bleibt', $z[2] === '+49 (0) 221 / 123-45');
$check('einzelner Strich bleibt', $z[3] === '-');

/* ---------- Anmeldung: Zeit ---------- */
$check('Blindhash ist ein echter Hash', password_get_info(auth_blindhash())['algo'] !== null);
$check('Blindhash passt zu nichts', !password_verify('', auth_blindhash()));
$check('Blindhash bleibt gleich', auth_blindhash() === auth_blindhash());

$GLOBALS['user'] = null;
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$s = hrtime(true); auth_attempt('gibtsnicht', 'irgendwas'); $unbekannt = hrtime(true) - $s;
$GLOBALS['user'] = ['id' => 1, 'username' => 'anna', 'password_hash' => password_hash('richtig!!!', PASSWORD_DEFAULT), 'is_active' => 1];
$s = hrtime(true); auth_attempt('anna', 'falsch'); $bekannt = hrtime(true) - $s;
$verhaeltnis = $unbekannt / max(1, $bekannt);
$check('unbekannter Name dauert etwa gleich lang', $verhaeltnis > 0.3 && $verhaeltnis < 3.0);
session_start();
[$ok2] = auth_attempt('anna', 'richtig!!!');
$check('richtiges Passwort geht weiter', $ok2 === true);

echo "$ok bestanden, $fail fehlgeschlagen\n";
