<?php
/**
 * Einrichtungsassistent.
 * Nach erfolgreicher Installation sollte diese Datei gelöscht oder
 * per Webserver gesperrt werden.
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require $root . '/src/lib/util.php';
require $root . '/src/lib/db.php';

$configFile = $root . '/config/config.php';
$configExists = is_file($configFile);

session_start();

/** SQL-Datei ausführen */
function run_sql_file(PDO $pdo, string $file): int
{
    $sql = (string)file_get_contents($file);
    // Kommentarzeilen entfernen
    $sql = preg_replace('/^\s*--.*$/m', '', $sql) ?? $sql;
    $count = 0;
    foreach (preg_split('/;\s*(\r?\n|$)/', $sql) ?: [] as $stmt) {
        $stmt = trim($stmt);
        if ($stmt === '') {
            continue;
        }
        $pdo->exec($stmt);
        $count++;
    }
    return $count;
}

$errors = [];
$done = false;

$form = [
    'host' => $_POST['host'] ?? 'localhost',
    'port' => $_POST['port'] ?? '3306',
    'name' => $_POST['name'] ?? 'ov_budget',
    'user' => $_POST['user'] ?? 'ov_budget',
    'pass' => $_POST['pass'] ?? '',
    'base_path' => $_POST['base_path'] ?? '',
    'admin_user' => $_POST['admin_user'] ?? 'admin',
    'admin_name' => $_POST['admin_name'] ?? '',
    'admin_mail' => $_POST['admin_mail'] ?? '',
    'ov_name'    => $_POST['ov_name'] ?? 'THW Ortsverband ',
];

// Bereits installiert? Dann abbrechen.
if ($configExists) {
    try {
        $cfg = require $configFile;
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            $cfg['db']['host'], (int)$cfg['db']['port'], $cfg['db']['name']);
        $pdo = new PDO($dsn, $cfg['db']['user'], $cfg['db']['pass'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $hasUsers = (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() > 0;
        if ($hasUsers) {
            http_response_code(403);
            exit('<!doctype html><meta charset="utf-8"><p style="font:16px system-ui;padding:2rem">'
                . 'Die Anwendung ist bereits eingerichtet. Bitte <strong>public/install.php</strong> löschen. '
                . '<a href="index.php">Zur Anmeldung</a></p>');
        }
    } catch (Throwable) {
        // Konfiguration vorhanden, aber Datenbank noch leer – Assistent darf weiterlaufen
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pass = (string)$form['pass'];

    // 1) Verbindung testen
    try {
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            $form['host'], (int)$form['port'], $form['name']);
        $pdo = new PDO($dsn, (string)$form['user'], $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    } catch (PDOException $ex) {
        $errors[] = 'Verbindung zur Datenbank fehlgeschlagen: ' . $ex->getMessage();
        $pdo = null;
    }

    // 2) Admin-Angaben prüfen
    $adminUser = strtolower(trim((string)$form['admin_user']));
    $adminPass = (string)($_POST['admin_pass'] ?? '');
    $adminPass2 = (string)($_POST['admin_pass2'] ?? '');

    if (!preg_match('/^[a-z0-9._-]{3,60}$/', $adminUser)) {
        $errors[] = 'Der Benutzername des Administrators darf nur Kleinbuchstaben, Ziffern, Punkt, '
            . 'Bindestrich und Unterstrich enthalten.';
    }
    if (mb_strlen($adminPass) < 10) {
        $errors[] = 'Das Administrator-Passwort muss mindestens 10 Zeichen lang sein.';
    }
    if ($adminPass !== $adminPass2) {
        $errors[] = 'Die beiden Passwörter stimmen nicht überein.';
    }

    // 3) Schreibrechte prüfen
    if (!is_dir($root . '/config') || !is_writable($root . '/config')) {
        $errors[] = 'Das Verzeichnis config/ ist nicht beschreibbar.';
    }
    $uploadDir = $root . '/storage/uploads';
    if (!is_dir($uploadDir)) {
        @mkdir($uploadDir, 0770, true);
    }
    if (!is_dir($uploadDir) || !is_writable($uploadDir)) {
        $errors[] = 'Das Verzeichnis storage/uploads/ ist nicht beschreibbar.';
    }

    if (!$errors && $pdo) {
        try {
            run_sql_file($pdo, $root . '/sql/schema.sql');
            run_sql_file($pdo, $root . '/sql/seed.sql');

            // Ortsverbandsname übernehmen
            $ov = trim((string)$form['ov_name']);
            if ($ov !== '') {
                $st = $pdo->prepare('UPDATE settings SET svalue = ? WHERE skey = ?');
                $st->execute([$ov, 'ov_name']);
                $st->execute([$ov, 'ov_kurz']);
            }

            // Administrator anlegen
            $st = $pdo->prepare(
                'INSERT INTO users (username, email, display_name, password_hash, role, is_active)
                 VALUES (?,?,?,?,\'admin\',1)'
            );
            $st->execute([
                $adminUser,
                trim((string)$form['admin_mail']),
                trim((string)$form['admin_name']) ?: $adminUser,
                password_hash($adminPass, PASSWORD_DEFAULT),
            ]);

            // Konfiguration schreiben
            $cfg = "<?php\n"
                . "/** Erzeugt vom Einrichtungsassistenten am " . date('d.m.Y H:i') . " */\n"
                . "return [\n"
                . "    'db' => [\n"
                . "        'host'    => " . var_export((string)$form['host'], true) . ",\n"
                . "        'port'    => " . (int)$form['port'] . ",\n"
                . "        'name'    => " . var_export((string)$form['name'], true) . ",\n"
                . "        'user'    => " . var_export((string)$form['user'], true) . ",\n"
                . "        'pass'    => " . var_export($pass, true) . ",\n"
                . "        'charset' => 'utf8mb4',\n"
                . "    ],\n"
                . "    'base_path'    => " . var_export(rtrim((string)$form['base_path'], '/'), true) . ",\n"
                . "    'upload_dir'   => dirname(__DIR__) . '/storage/uploads',\n"
                . "    'session_name' => 'ovbudget',\n"
                . "    'debug'        => false,\n"
                . "];\n";

            if (file_put_contents($configFile, $cfg) === false) {
                $errors[] = 'Die Datei config/config.php konnte nicht geschrieben werden.';
            } else {
                @chmod($configFile, 0640);
                $done = true;
            }
        } catch (Throwable $ex) {
            $errors[] = 'Einrichtung fehlgeschlagen: ' . $ex->getMessage();
        }
    }
}
?>
<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Einrichtung · OV-Budget</title>
<link rel="stylesheet" href="assets/css/app.css">
</head>
<body>
<header class="topbar">
  <div class="topbar__brand"><span class="topbar__logo">THW</span>
    <span class="topbar__names"><strong>OV-Budget</strong><small>Einrichtung</small></span></div>
</header>

<main class="page" style="max-width:760px">
<?php if ($done): ?>
  <div class="alert alert--success">
    <strong>Fertig.</strong> Datenbank angelegt, Grunddaten eingespielt und der Administrator-Zugang erstellt.
  </div>
  <div class="card">
    <h1>Noch drei Handgriffe</h1>
    <ol>
      <li>Diese Datei löschen: <span class="mono">public/install.php</span></li>
      <li>Sicherstellen, dass <span class="mono">config/</span>, <span class="mono">src/</span>,
          <span class="mono">views/</span>, <span class="mono">sql/</span> und <span class="mono">storage/</span>
          nicht über den Webserver erreichbar sind (Document-Root sollte <span class="mono">public/</span> sein).</li>
      <li>Unter <em>Verwaltung → Einstellungen</em> Namen, Texte und das Haushaltsjahr anpassen.</li>
    </ol>
    <p><a class="btn" href="index.php">Zur Anmeldung</a></p>
  </div>
<?php else: ?>
  <div class="pagehead"><div>
    <h1>Einrichtung</h1>
    <p>Datenbank verbinden, Tabellen anlegen und den ersten Administrator erstellen.</p>
  </div></div>

  <?php if ($errors): ?>
    <div class="alert alert--error"><ul>
      <?php foreach ($errors as $er): ?><li><?= htmlspecialchars($er, ENT_QUOTES, 'UTF-8') ?></li><?php endforeach; ?>
    </ul></div>
  <?php endif; ?>

  <form method="post" class="form">
    <section class="card">
      <h2>Datenbank</h2>
      <div class="grid2">
        <div class="field"><label for="host">Host</label>
          <input type="text" id="host" name="host" required value="<?= htmlspecialchars((string)$form['host'], ENT_QUOTES) ?>"></div>
        <div class="field"><label for="port">Port</label>
          <input type="number" id="port" name="port" required value="<?= (int)$form['port'] ?>"></div>
        <div class="field"><label for="name">Datenbankname</label>
          <input type="text" id="name" name="name" required value="<?= htmlspecialchars((string)$form['name'], ENT_QUOTES) ?>"></div>
        <div class="field"><label for="user">Benutzer</label>
          <input type="text" id="user" name="user" required value="<?= htmlspecialchars((string)$form['user'], ENT_QUOTES) ?>"></div>
        <div class="field"><label for="pass">Passwort</label>
          <input type="password" id="pass" name="pass" value="<?= htmlspecialchars((string)$form['pass'], ENT_QUOTES) ?>"></div>
        <div class="field"><label for="base_path">Unterverzeichnis (optional)</label>
          <input type="text" id="base_path" name="base_path" placeholder="/budget"
                 value="<?= htmlspecialchars((string)$form['base_path'], ENT_QUOTES) ?>">
          <small>Leer lassen, wenn die App direkt unter der Domain liegt.</small></div>
      </div>
      <p class="small muted">Die Datenbank muss bereits existieren; die Tabellen legt der Assistent an.</p>
    </section>

    <section class="card">
      <h2>Ortsverband und Administrator</h2>
      <div class="grid2">
        <div class="field"><label for="ov_name">Name des Ortsverbands</label>
          <input type="text" id="ov_name" name="ov_name" value="<?= htmlspecialchars((string)$form['ov_name'], ENT_QUOTES) ?>"></div>
        <div class="field"><label for="admin_user">Benutzername</label>
          <input type="text" id="admin_user" name="admin_user" required autocapitalize="none"
                 value="<?= htmlspecialchars((string)$form['admin_user'], ENT_QUOTES) ?>"></div>
        <div class="field"><label for="admin_name">Anzeigename</label>
          <input type="text" id="admin_name" name="admin_name"
                 value="<?= htmlspecialchars((string)$form['admin_name'], ENT_QUOTES) ?>"></div>
        <div class="field"><label for="admin_mail">E-Mail</label>
          <input type="email" id="admin_mail" name="admin_mail"
                 value="<?= htmlspecialchars((string)$form['admin_mail'], ENT_QUOTES) ?>"></div>
        <div class="field"><label for="admin_pass">Passwort</label>
          <input type="password" id="admin_pass" name="admin_pass" required autocomplete="new-password">
          <small>Mindestens 10 Zeichen.</small></div>
        <div class="field"><label for="admin_pass2">Passwort wiederholen</label>
          <input type="password" id="admin_pass2" name="admin_pass2" required autocomplete="new-password"></div>
      </div>
    </section>

    <button class="btn" type="submit">Jetzt einrichten</button>
  </form>
<?php endif; ?>
</main>
</body>
</html>
