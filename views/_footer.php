<?php $current = $_GET['p'] ?? 'dashboard'; ?>
</main>
<nav class="app-nav">
    <a class="app-nav__item <?= str_starts_with($current, 'dashboard') ? 'is-active' : '' ?>"
       href="<?= h(url('', ['p' => 'dashboard'])) ?>">
        <span class="app-nav__icon">●</span>
        <span class="app-nav__label">ホーム</span>
    </a>
    <a class="app-nav__item <?= str_starts_with($current, 'customer') ? 'is-active' : '' ?>"
       href="<?= h(url('', ['p' => 'customers'])) ?>">
        <span class="app-nav__icon">◆</span>
        <span class="app-nav__label">顧客</span>
    </a>
    <a class="app-nav__item <?= $current === 'purchase_new' ? 'is-active' : '' ?>"
       href="<?= h(url('', ['p' => 'purchase_new'])) ?>">
        <span class="app-nav__icon">+</span>
        <span class="app-nav__label">記録</span>
    </a>
    <a class="app-nav__item <?= $current === 'history' ? 'is-active' : '' ?>"
       href="<?= h(url('', ['p' => 'history'])) ?>">
        <span class="app-nav__icon">≡</span>
        <span class="app-nav__label">履歴</span>
    </a>
    <a class="app-nav__item <?= ($current === 'export' || $current === 'import') ? 'is-active' : '' ?>"
       href="<?= h(url('', ['p' => 'export'])) ?>">
        <span class="app-nav__icon">↓</span>
        <span class="app-nav__label">データ</span>
    </a>
</nav>
</body>
</html>
