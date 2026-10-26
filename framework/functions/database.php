<?php
declare(strict_types=1);

function db(): PDO
{
    static $pdo;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $c = $GLOBALS['config'];
    $dsn = sprintf('mysql:host=%s;port=%d;charset=%s', $c['db_host'], $c['db_port'], $c['charset']);
    $pdo = new PDO($dsn, $c['db_user'], $c['db_password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    return $pdo;
}

function identifier(string $name): string
{
    if ($name === '' || str_contains($name, "\0")) {
        throw new RuntimeException('Invalid database identifier.');
    }
    return '`' . str_replace('`', '``', $name) . '`';
}

function databases(): array
{
    return db()->query('SHOW DATABASES')->fetchAll(PDO::FETCH_COLUMN);
}

function tables(string $database): array
{
    $sql = 'SELECT TABLE_NAME FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = ? AND TABLE_TYPE = "BASE TABLE" ORDER BY TABLE_NAME';
    $stmt = db()->prepare($sql);
    $stmt->execute([$database]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

function columns(string $database, string $table): array
{
    $stmt = db()->prepare(
        'SELECT COLUMN_NAME, DATA_TYPE, COLUMN_TYPE, CHARACTER_MAXIMUM_LENGTH,
                IS_NULLABLE, COLUMN_KEY, COLUMN_DEFAULT, EXTRA, COLUMN_COMMENT
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? ORDER BY ORDINAL_POSITION'
    );
    $stmt->execute([$database, $table]);
    return $stmt->fetchAll();
}

function primary_keys(string $database, string $table): array
{
    $stmt = db()->prepare(
        'SELECT COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE
         WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND CONSTRAINT_NAME="PRIMARY"
         ORDER BY ORDINAL_POSITION'
    );
    $stmt->execute([$database, $table]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

function foreign_keys(string $database, string $table): array
{
    $stmt = db()->prepare(
        'SELECT COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
         FROM information_schema.KEY_COLUMN_USAGE
         WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND REFERENCED_TABLE_NAME IS NOT NULL
         ORDER BY ORDINAL_POSITION'
    );
    $stmt->execute([$database, $table]);
    return $stmt->fetchAll();
}

function referencing_tables(string $database, string $parent): array
{
    $stmt = db()->prepare(
        'SELECT TABLE_NAME, COLUMN_NAME, REFERENCED_COLUMN_NAME
         FROM information_schema.KEY_COLUMN_USAGE
         WHERE TABLE_SCHEMA=? AND REFERENCED_TABLE_NAME=?
         ORDER BY TABLE_NAME, ORDINAL_POSITION'
    );
    $stmt->execute([$database, $parent]);
    $groups = [];
    foreach ($stmt as $row) {
        $groups[$row['TABLE_NAME']][] = $row;
    }
    return $groups;
}

function schema_table(string $database, string $table): void
{
    if (!in_array($table, tables($database), true)) {
        error_page('Unknown table.', 404);
    }
}

function schema_database(string $database): void
{
    if (!in_array($database, databases(), true)) {
        error_page('Unknown database.', 404);
    }
}

function fetch_records(string $database, string $table, int $limit, int $offset): array
{
    schema_table($database, $table);
    $sql = 'SELECT * FROM ' . identifier($database) . '.' . identifier($table)
        . ' LIMIT ' . max(1, $limit) . ' OFFSET ' . max(0, $offset);
    return db()->query($sql)->fetchAll();
}

function record_count(string $database, string $table): int
{
    schema_table($database, $table);
    return (int)db()->query(
        'SELECT COUNT(*) FROM ' . identifier($database) . '.' . identifier($table)
    )->fetchColumn();
}

function find_record(string $database, string $table, array $key): ?array
{
    schema_table($database, $table);
    $keys = primary_keys($database, $table);
    if (!$keys || count($key) !== count($keys)) {
        return null;
    }
    $where = implode(' AND ', array_map(fn($k) => identifier($k) . '=?', $keys));
    $stmt = db()->prepare('SELECT * FROM ' . identifier($database) . '.' . identifier($table) . " WHERE $where");
    $stmt->execute(array_map(fn($k) => $key[$k], $keys));
    return $stmt->fetch() ?: null;
}

function foreign_options(string $database, string $table, string $keyColumn): array
{
    $matches = array_values(array_filter(foreign_keys($database, $table),
        fn($fk) => $fk['COLUMN_NAME'] === $keyColumn));
    if (!$matches) {
        return [];
    }
    $fk = $matches[0];
    $refTable = $fk['REFERENCED_TABLE_NAME'];
    $refKey = $fk['REFERENCED_COLUMN_NAME'];
    schema_table($database, $refTable);

    $labelColumn = $refKey;
    foreach (columns($database, $refTable) as $col) {
        if ($col['COLUMN_NAME'] !== $refKey &&
            in_array($col['DATA_TYPE'], ['char','varchar','text','tinytext','mediumtext','longtext'], true)) {
            $labelColumn = $col['COLUMN_NAME'];
            break;
        }
    }

    $sql = 'SELECT ' . identifier($refKey) . ' AS fk_value, '
         . identifier($labelColumn) . ' AS fk_label FROM '
         . identifier($database) . '.' . identifier($refTable)
         . ' ORDER BY ' . identifier($labelColumn);
    return db()->query($sql)->fetchAll();
}

function child_records(string $database, string $childTable, array $foreignColumns, array $parent): array
{
    $conditions = [];
    $values = [];
    foreach ($foreignColumns as $fk) {
        $conditions[] = identifier($fk['COLUMN_NAME']) . '=?';
        $values[] = $parent[$fk['REFERENCED_COLUMN_NAME']];
    }
    $stmt = db()->prepare('SELECT * FROM ' . identifier($database) . '.' . identifier($childTable)
        . ' WHERE ' . implode(' AND ', $conditions));
    $stmt->execute($values);
    return $stmt->fetchAll();
}
