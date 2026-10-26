<?php
require_once dirname(__DIR__, 2) . '/framework/functions/essential.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') error_page('POST required.', 405);
verify_csrf();

$dbName = (string)($_POST['db'] ?? '');
$table = (string)($_POST['table'] ?? '');
schema_database($dbName);
schema_table($dbName, $table);

$action = (string)($_POST['action'] ?? '');
$return = app_url('table/index.php', ['db'=>$dbName,'table'=>$table]);

function write_row(string $database, string $table, array $values, string $mode, array $key = []): void
{
    $cols = array_column(columns($database, $table), 'COLUMN_NAME');
    $values = array_intersect_key($values, array_flip($cols));
    $keys = primary_keys($database, $table);

    if ($mode === 'insert') {
        if (!$values) return;
        $names = array_keys($values);
        $sql = 'INSERT INTO ' . identifier($database) . '.' . identifier($table)
            . ' (' . implode(',', array_map('identifier', $names)) . ') VALUES ('
            . implode(',', array_fill(0, count($names), '?')) . ')';
        db()->prepare($sql)->execute(array_values($values));
        return;
    }

    if ($mode !== 'update' || !$keys || count($key) !== count($keys)) {
        throw new RuntimeException('Invalid record operation.');
    }
    if ($values) {
        $sets = implode(',', array_map(fn($c) => identifier($c) . '=?', array_keys($values)));
        $where = implode(' AND ', array_map(fn($c) => identifier($c) . '=?', $keys));
        db()->prepare('UPDATE ' . identifier($database) . '.' . identifier($table) . " SET $sets WHERE $where")
            ->execute([...array_values($values), ...array_map(fn($k) => $key[$k], $keys)]);
    }
}

try {
    if ($action === 'delete_parent') {
        $keys = primary_keys($dbName, $table);
        $key = is_array($_POST['key'] ?? null) ? $_POST['key'] : [];
        if (!$keys || count($key) !== count($keys)) throw new RuntimeException('Invalid primary key.');
        $where = implode(' AND ', array_map(fn($k) => identifier($k) . '=?', $keys));
        db()->prepare('DELETE FROM ' . identifier($dbName) . '.' . identifier($table) . " WHERE $where")
            ->execute(array_map(fn($k) => $key[$k], $keys));
        redirect($return);
    }

    if ($action !== 'save_parent') throw new RuntimeException('Unknown action.');

    $mode = (string)($_POST['mode'] ?? '');
    $keys = primary_keys($dbName, $table);
    $key = is_array($_POST['key'] ?? null) ? $_POST['key'] : [];
    $values = [];
    foreach (columns($dbName, $table) as $col) {
        $name = $col['COLUMN_NAME'];
        if (in_array($name, $keys, true) && $mode === 'insert') continue;
        $field = 'fe_' . $name;
        if (!array_key_exists($field, $_POST)) continue;
        $value = $_POST[$field];
        if (is_array($value)) throw new RuntimeException('Invalid field value.');
        if (in_array($col['DATA_TYPE'], ['datetime','timestamp'], true) && $value !== '') {
            $value = str_replace('T', ' ', (string)$value);
            if (strlen($value) === 16) $value .= ':00';
        }
        $values[$name] = $value;
    }

    db()->beginTransaction();
    write_row($dbName, $table, $values, $mode, $key);
    $parent = $mode === 'insert'
        ? (function () use ($dbName, $table, $keys) {
            $row = [];
            foreach ($keys as $k) $row[$k] = db()->lastInsertId();
            return $row;
        })()
        : find_record($dbName, $table, $key);

    foreach ((array)($_POST['child'] ?? []) as $childTable => $rows) {
        schema_table($dbName, (string)$childTable);
        $foreign = foreign_keys($dbName, (string)$childTable);
        $link = array_values(array_filter($foreign, fn($fk) => $fk['REFERENCED_TABLE_NAME'] === $table));
        if (!$link) continue;

        foreach ((array)$rows as $row) {
            if (!is_array($row)) continue;
            $childKey = is_array($row['key'] ?? null) ? $row['key'] : [];
            if (isset($row['delete'])) {
                $childKeys = primary_keys($dbName, (string)$childTable);
                if ($childKeys && count($childKey) === count($childKeys)) {
                    $where = implode(' AND ', array_map(fn($k) => identifier($k) . '=?', $childKeys));
                    db()->prepare('DELETE FROM ' . identifier($dbName) . '.' . identifier((string)$childTable) . " WHERE $where")
                        ->execute(array_map(fn($k) => $childKey[$k], $childKeys));
                }
                continue;
            }

            $fields = is_array($row['fields'] ?? null) ? $row['fields'] : [];
            $childValues = [];
            foreach ($link as $fk) {
                $childValues[$fk['COLUMN_NAME']] = $parent[$fk['REFERENCED_COLUMN_NAME']] ?? null;
            }
            foreach ($fields as $column => $value) {
                if (!is_array($value)) $childValues[(string)$column] = $value;
            }

            $childMode = (string)($row['mode'] ?? 'insert');
            $hasUserValues = count(array_diff(array_keys($childValues), array_column($link, 'COLUMN_NAME'))) > 0;
            if ($childMode === 'insert' && !$hasUserValues) continue;
            write_row($dbName, (string)$childTable, $childValues, $childMode, $childKey);
        }
    }

    db()->commit();
    redirect($return);
} catch (Throwable $e) {
    if (db()->inTransaction()) db()->rollBack();
    error_page('Save failed: ' . $e->getMessage(), 400);
}
