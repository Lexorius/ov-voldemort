<?php
declare(strict_types=1);
/*
 * Nachgemachter SMTP-Server für t_mail.php – nimmt genau eine Verbindung an,
 * spielt EHLO, AUTH LOGIN, MAIL FROM, RCPT TO, DATA und QUIT mit und schreibt
 * alles, was der Client sagt, in die Protokolldatei.
 *
 *   php hilfe_smtp.php <Port> <Protokoll>
 */
$port = (int)($argv[1] ?? 0);
$protokoll = (string)($argv[2] ?? '');
$server = stream_socket_server('tcp://127.0.0.1:' . $port, $nr, $text);
if ($server === false) {
    fwrite(STDERR, "kein Server: $text\n");
    exit(1);
}
file_put_contents($protokoll . '.bereit', '1');
$c = stream_socket_accept($server, 10);
if ($c === false) {
    exit(2);
}
$log = static function (string $z) use ($protokoll): void { file_put_contents($protokoll, $z . "\n", FILE_APPEND); };
$sag = static function (string $z) use ($c): void { fwrite($c, $z . "\r\n"); };
$sag('220 test.example ESMTP Attrappe');
$inDaten = false;
while (($zeile = fgets($c)) !== false) {
    $zeile = rtrim($zeile, "\r\n");
    $log($zeile);
    if ($inDaten) {
        if ($zeile === '.') { $inDaten = false; $sag('250 OK angenommen'); }
        continue;
    }
    $wort = strtoupper(strtok($zeile, ' ') ?: '');
    if ($wort === 'EHLO') { $sag('250-test.example'); $sag('250-AUTH LOGIN PLAIN'); $sag('250 OK'); }
    elseif ($wort === 'AUTH') {
        $sag('334 VXNlcm5hbWU6');
        $log('<Benutzer ' . trim((string)fgets($c)) . '>');
        $sag('334 UGFzc3dvcmQ6');
        $log('<Passwort ' . trim((string)fgets($c)) . '>');
        $sag('235 angemeldet');
    }
    elseif ($wort === 'MAIL' || $wort === 'RCPT') { $sag('250 OK'); }
    elseif ($wort === 'DATA') { $inDaten = true; $sag('354 her damit'); }
    elseif ($wort === 'QUIT') { $sag('221 tschüss'); break; }
    else { $sag('500 kenne ich nicht'); }
}
fclose($c);
fclose($server);
