<?php
require_once dirname(__DIR__, 2) . '/framework/functions/essential.php';

$dbName = (string)($_GET['db'] ?? '');
$table = (string)($_GET['table'] ?? '');
schema_database($dbName);
schema_table($dbName, $table);

$cols = columns($dbName, $table);
$keys = primary_keys($dbName, $table);
$key = is_array($_GET['key'] ?? null) ? $_GET['key'] : [];
$record = $key ? find_record($dbName, $table, $key) : null;
if ($key && !$record) error_page('Record not found.', 404);
$editing = $record !== null;
$refs = referencing_tables($dbName, $table);
$pageTitle = ($editing ? 'Edit ' : 'Add ') . $table;

function form_control(array $col, mixed $value, string $name, string $id, bool $primary, string $database, string $table): void
{
    $type = strtolower($col['DATA_TYPE']);
    $length = $col['CHARACTER_MAXIMUM_LENGTH'];
    $isFk = false;
    foreach (foreign_keys($database, $table) as $fk) {
        if ($fk['COLUMN_NAME'] === $col['COLUMN_NAME'] && str_ends_with($col['COLUMN_NAME'], '_id')) $isFk = true;
    }
    $attrs = render_comment_attributes((string)$col['COLUMN_COMMENT']);
    $label = proper_case($col['COLUMN_NAME']);
    echo '<div class="form-field"><label for="' . h($id) . '">' . h($label) . '</label>';

    if ($primary && in_array($type, ['int','bigint'], true)) {
        echo '<input class="w3-input" type="number" id="' . h($id) . '" name="' . h($name) . '" value="' . h($value) . '" disabled>';
    } elseif ($isFk) {
        echo '<select class="w3-select" id="' . h($id) . '" name="' . h($name) . '" ' . $attrs . '>';
        echo '<option value="">— Select —</option>';
        foreach (foreign_options($database, $table, $col['COLUMN_NAME']) as $option) {
            $selected = (string)$value === (string)$option['fk_value'] ? ' selected' : '';
            echo '<option value="' . h($option['fk_value']) . '"' . $selected . '>' . h($option['fk_label']) . '</option>';
        }
        echo '</select>';
    } elseif ($type === 'tinyint' && $col['COLUMN_TYPE'] === 'tinyint(1)') {
        echo '<input type="hidden" name="' . h($name) . '" value="0">';
        echo '<input type="checkbox" id="' . h($id) . '" name="' . h($name) . '" value="1"'
            . ((string)$value === '1' ? ' checked' : '') . ' ' . $attrs . '>';
    } elseif (in_array($type, ['text','mediumtext','longtext'], true) || ($type === 'varchar' && (int)$length > 50)) {
        echo '<textarea class="w3-input autogrow" id="' . h($id) . '" name="' . h($name) . '"'
            . ($type === 'varchar' ? ' maxlength="' . (int)$length . '"' : '') . ' ' . $attrs . '>' . h($value) . '</textarea>';
    } else {
        $inputType = match ($type) {
            'date' => 'date',
            'datetime','timestamp' => 'datetime-local',
            'int','bigint','decimal','float','double' => 'number',
            default => 'text',
        };
        if ($inputType === 'datetime-local' && $value) $value = str_replace(' ', 'T', substr((string)$value, 0, 16));
        $maxlength = $type === 'varchar' ? ' maxlength="' . (int)$length . '"' : '';
        echo '<input class="w3-input" type="' . h($inputType) . '" id="' . h($id) . '" name="' . h($name)
            . '" value="' . h($value) . '"' . $maxlength . ' ' . $attrs . '>';
    }
    echo '</div>';
}

load_partial('html_begin');
load_partial('header');
?>
<main>
    <h2><?= h($pageTitle) ?></h2>
    <form method="post" action="/form/save.php">
        <input type="hidden" name="_csrf" value="<?= h(csrf_token()) ?>">
        <input type="hidden" name="action" value="<?= $editing ? 'save_parent' : 'save_parent' ?>">
        <input type="hidden" name="db" value="<?= h($dbName) ?>">
        <input type="hidden" name="table" value="<?= h($table) ?>">
        <?php if ($editing): ?>
            <input type="hidden" name="mode" value="update">
            <?php foreach ($keys as $k): ?><input type="hidden" name="key[<?= h($k) ?>]" value="<?= h($record[$k]) ?>"><?php endforeach; ?>
        <?php else: ?><input type="hidden" name="mode" value="insert"><?php endif; ?>

        <?php foreach ($cols as $col):
            $column = $col['COLUMN_NAME'];
            $primary = in_array($column, $keys, true);
            if ($primary && $editing) continue;
            $value = $record[$column] ?? $col['COLUMN_DEFAULT'] ?? '';
            form_control($col, $value, 'fe_' . $column, 'fe-' . str_replace('_','-',$column), $primary, $dbName, $table);
        endforeach; ?>

        <?php foreach ($refs as $childTable => $fkColumns):
            $childCols = columns($dbName, $childTable);
            $childKeys = primary_keys($dbName, $childTable);
            $existing = $editing ? child_records($dbName, $childTable, $fkColumns, $record) : [];
            $titleParts = [];
            foreach ($childCols as $childCol) {
                if (!in_array($childCol['COLUMN_NAME'], array_column($fkColumns, 'COLUMN_NAME'), true)
                    && !in_array($childCol['COLUMN_NAME'], ['id'], true)) $titleParts[] = proper_case($childCol['COLUMN_NAME']);
            }
            $legend = proper_case($childTable) . (count($titleParts) ? ' ' . implode(' ', $titleParts) : '');
            $containerId = 'children-' . preg_replace('/[^a-zA-Z0-9_-]/', '-', $childTable);
        ?>
            <fieldset class="w3-border"><legend><?= h($legend) ?></legend>
                <div id="<?= h($containerId) ?>" data-next-index="<?= count($existing) ?>">
                    <?php foreach ($existing as $rowIndex => $childRow): ?>
                        <div class="child-row">
                            <input type="hidden" name="child[<?= h($childTable) ?>][<?= $rowIndex ?>][mode]" value="update">
                            <?php foreach ($childKeys as $childKey): ?>
                                <input type="hidden" name="child[<?= h($childTable) ?>][<?= $rowIndex ?>][key][<?= h($childKey) ?>]" value="<?= h($childRow[$childKey]) ?>">
                            <?php endforeach; ?>
                            <?php foreach ($childCols as $childCol):
                                $cn = $childCol['COLUMN_NAME'];
                                if (in_array($cn, array_column($fkColumns, 'COLUMN_NAME'), true) || in_array($cn, $childKeys, true)) continue;
                                form_control($childCol, $childRow[$cn], "child[$childTable][$rowIndex][fields][$cn]", "child-$childTable-$rowIndex-" . str_replace('_','-',$cn), false, $dbName, $childTable);
                            endforeach; ?>
                            <button class="w3-button w3-red" name="child[<?= h($childTable) ?>][<?= $rowIndex ?>][delete]" value="1" title="Delete child record" data-confirm="Delete this referencing record?">
                                <i class="fa-solid fa-trash" aria-hidden="true"></i>
                            </button>
                        </div>
                    <?php endforeach; ?>
                    <template>
                        <div class="child-row">
                            <input type="hidden" name="child[<?= h($childTable) ?>][__INDEX__][mode]" value="insert">
                            <?php foreach ($childCols as $childCol):
                                $cn = $childCol['COLUMN_NAME'];
                                if (in_array($cn, array_column($fkColumns, 'COLUMN_NAME'), true) || in_array($cn, $childKeys, true)) continue;
                                form_control($childCol, '', "child[$childTable][__INDEX__][fields][$cn]", "child-$childTable-new-" . str_replace('_','-',$cn), false, $dbName, $childTable);
                            endforeach; ?>
                        </div>
                    </template>
                </div>
                <button type="button" class="w3-button w3-teal" data-add-child="<?= h($containerId) ?>" title="Add referencing record">
                    <i class="fa-solid fa-plus" aria-hidden="true"></i>
                </button>
            </fieldset>
        <?php endforeach; ?>

        <button class="w3-button w3-green" title="Save"><i class="fa-solid fa-floppy-disk" aria-hidden="true"></i></button>
        <a class="w3-button w3-grey" title="Cancel" href="<?= h(app_url('table/index.php', ['db'=>$dbName,'table'=>$table])) ?>">
            <i class="fa-solid fa-xmark" aria-hidden="true"></i>
        </a>
    </form>
</main>
<?php load_partial('footer'); load_partial('html_end'); ?>
