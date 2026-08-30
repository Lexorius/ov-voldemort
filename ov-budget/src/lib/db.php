<?php
declare(strict_types=1);

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $c = app_config('db', []);
    if (!$c) {
        throw new RuntimeException('Keine Datenbank-Konfiguration gefunden (config/config.php).');
    }

    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=%s',
        $c['host'] ?? 'localhost',
        (int)($c['port'] ?? 3306),
        $c['name'] ?? '',
        $c['charset'] ?? 'utf8mb4'
    );

    $pdo = new PDO($dsn, $c['user'] ?? '', $c['pass'] ?? '', [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);

    return $pdo;
}

/** Eine Zeile holen */
function db_row(string $sql, array $params = []): ?array
{
    $st = db()->prepare($sql);
    $st->execute($params);
    $row = $st->fetch();
    return $row === false ? null : $row;
}

/** Alle Zeilen holen */
function db_all(string $sql, array $params = []): array
{
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st->fetchAll();
}

/** Einzelwert holen */
function db_val(string $sql, array $params = [], mixed $default = null): mixed
{
    $st = db()->prepare($sql);
    $st->execute($params);
    $v = $st->fetchColumn();
    return $v === false ? $default : $v;
}

function db_exec(string $sql, array $params = []): int
{
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st->rowCount();
}

function db_insert(string $table, array $data): int
{
    $cols = array_keys($data);
    $sql = 'INSERT INTO ' . $table . ' (' . implode(',', $cols) . ') VALUES ('
        . implode(',', array_fill(0, count($cols), '?')) . ')';
    db()->prepare($sql)->execute(array_values($data));
    return (int)db()->lastInsertId();
}

function db_update(string $table, array $data, string $where, array $whereParams = []): int
{
    $set = implode(',', array_map(static fn($c) => $c . '=?', array_keys($data)));
    $sql = 'UPDATE ' . $table . ' SET ' . $set . ' WHERE ' . $where;
    $st = db()->prepare($sql);
    $st->execute([...array_values($data), ...$whereParams]);
    return $st->rowCount();
}
