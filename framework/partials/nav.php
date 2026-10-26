<nav class="w3-bar w3-black" aria-label="Breadcrumb">
    <?php foreach (current_breadcrumbs() as $crumb): ?>
        <a class="w3-bar-item w3-button" href="<?= h($crumb[1]) ?>"><?= h($crumb[0]) ?></a>
    <?php endforeach; ?>
    <form class="w3-bar-item w3-right" method="get" action="">
        <?php foreach (['db','table','id'] as $param): ?>
            <?php if (isset($_GET[$param])): ?>
                <input type="hidden" name="<?= h($param) ?>" value="<?= h($_GET[$param]) ?>">
            <?php endif; ?>
        <?php endforeach; ?>
        <label for="per-page">Rows</label>
        <select id="per-page" name="per_page" onchange="this.form.submit()">
            <?php foreach ($GLOBALS['config']['allowed_records_per_page'] as $n): ?>
                <option value="<?= (int)$n ?>" <?= current_records_per_page() === $n ? 'selected' : '' ?>><?= (int)$n ?></option>
            <?php endforeach; ?>
        </select>
    </form>
</nav>
