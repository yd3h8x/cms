<?php
require_once dirname(__DIR__) . '/framework/functions/essential.php';

try {
    $items = databases();
} catch (Throwable $e) {
    $items = [];
    $message = $e->getMessage();
}

$pageTitle = 'Databases';
load_partial('html_begin');
load_partial('header');
?>
<main>
    <h2>Databases</h2>
    <?php if (!empty($message)): ?><p class="w3-panel w3-red"><?= h($message) ?></p><?php endif; ?>
    <ul class="w3-ul w3-card">
        <?php foreach ($items as $name): ?>
            <li><a href="<?= h(app_url('database/index.php', ['db' => $name])) ?>">
                <i class="fa-solid fa-database" aria-hidden="true"></i> <?= h($name) ?>
            </a></li>
        <?php endforeach; ?>
    </ul>
</main>
<?php load_partial('footer'); load_partial('html_end'); ?>
