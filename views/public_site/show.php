<?php
/** @var string $title */
/** @var string $tenantName */
/** @var array $content */

?><!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-50 text-slate-900">
<header class="bg-white border-b border-slate-200">
    <div class="max-w-5xl mx-auto px-4 py-4 flex items-center justify-between">
        <div class="font-semibold">
            <?= e($tenantName !== '' ? $tenantName : $title) ?>
        </div>
        <a class="text-sm text-slate-700 hover:text-slate-900" href="/login">Connexion</a>
    </div>
</header>

<main class="max-w-5xl mx-auto px-4 py-8">
    <?php
    $schemaSections = null;
    if (is_array($content) && isset($content['sections']) && is_array($content['sections'])) {
        $schemaSections = $content['sections'];
    }
    ?>
    <?php if (empty($content)): ?>
        <div class="bg-white border border-slate-200 rounded-lg p-6">
            <h1 class="text-2xl font-semibold"><?= e($title) ?></h1>
            <p class="mt-2 text-slate-600">Page en cours de construction.</p>
        </div>
    <?php else: ?>
        <?php
        $renderBlock = static function (array $block): void {
            $type = (string)($block['type'] ?? '');
            $props = is_array($block['props'] ?? null) ? (array)$block['props'] : [];

            if ($type === 'text') {
                ?>
                <div class="bg-white border border-slate-200 rounded-lg p-6">
                    <?php if (!empty($props['title'])): ?>
                        <h2 class="text-xl font-semibold"><?= e((string)$props['title']) ?></h2>
                    <?php endif; ?>
                    <?php if (!empty($props['body'])): ?>
                        <div class="mt-2 whitespace-pre-wrap text-slate-700"><?= e((string)$props['body']) ?></div>
                    <?php endif; ?>
                </div>
                <?php
                return;
            }

            if ($type === 'image') {
                $src = (string)($props['src'] ?? '');
                $alt = (string)($props['alt'] ?? '');
                if ($src !== '') {
                    ?>
                    <div class="bg-white border border-slate-200 rounded-lg overflow-hidden">
                        <img src="<?= e($src) ?>" alt="<?= e($alt) ?>" class="w-full h-auto">
                    </div>
                    <?php
                }
                return;
            }

            if ($type === 'cta') {
                $label = (string)($props['label'] ?? '');
                $href = (string)($props['href'] ?? '');
                if ($label !== '' && $href !== '') {
                    ?>
                    <div>
                        <a class="inline-block bg-slate-900 text-white rounded px-4 py-2 text-sm" href="<?= e($href) ?>">
                            <?= e($label) ?>
                        </a>
                    </div>
                    <?php
                }
                return;
            }

            if ($type === 'contact') {
                ?>
                <div class="bg-white border border-slate-200 rounded-lg p-6">
                    <?php if (!empty($props['title'])): ?>
                        <h2 class="text-xl font-semibold"><?= e((string)$props['title']) ?></h2>
                    <?php endif; ?>
                    <div class="mt-2 text-slate-700 space-y-1">
                        <?php if (!empty($props['address'])): ?><div><?= e((string)$props['address']) ?></div><?php endif; ?>
                        <?php if (!empty($props['phone'])): ?><div><?= e((string)$props['phone']) ?></div><?php endif; ?>
                        <?php if (!empty($props['email'])): ?><div><?= e((string)$props['email']) ?></div><?php endif; ?>
                        <?php if (!empty($props['hours'])): ?><div><?= e((string)$props['hours']) ?></div><?php endif; ?>
                    </div>
                </div>
                <?php
                return;
            }

            if ($type === 'socials') {
                $items = is_array($props['items'] ?? null) ? (array)$props['items'] : [];
                ?>
                <div class="bg-white border border-slate-200 rounded-lg p-6">
                    <?php if (!empty($props['title'])): ?>
                        <h2 class="text-xl font-semibold"><?= e((string)$props['title']) ?></h2>
                    <?php endif; ?>
                    <?php if (!empty($items)): ?>
                        <div class="mt-3 flex flex-wrap gap-2">
                            <?php foreach ($items as $it): ?>
                                <?php if (is_array($it) && !empty($it['label']) && !empty($it['href'])): ?>
                                    <a class="border border-slate-200 rounded px-3 py-2 text-sm bg-slate-50 hover:bg-slate-100" href="<?= e((string)$it['href']) ?>" target="_blank" rel="noopener">
                                        <?= e((string)$it['label']) ?>
                                    </a>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                <?php
                return;
            }
        };
        ?>

        <?php if ($schemaSections !== null): ?>
            <?php foreach ($schemaSections as $section): ?>
                <?php
                $layout = is_array($section) ? (string)($section['layout'] ?? '1col') : '1col';
                $cols = 1;
                if ($layout === '2col') {
                    $cols = 2;
                } elseif ($layout === '3col') {
                    $cols = 3;
                }
                $columns = (is_array($section) && is_array($section['columns'] ?? null)) ? (array)$section['columns'] : [];
                ?>
                <section class="mt-6">
                    <div class="grid grid-cols-1 <?= $cols === 2 ? 'md:grid-cols-2' : '' ?> <?= $cols === 3 ? 'md:grid-cols-3' : '' ?> gap-4">
                        <?php for ($i = 0; $i < $cols; $i++): ?>
                            <?php $blocks = isset($columns[$i]) && is_array($columns[$i]) ? (array)$columns[$i] : []; ?>
                            <div class="space-y-4">
                                <?php foreach ($blocks as $b): ?>
                                    <?php if (is_array($b)): ?>
                                        <?php $renderBlock($b); ?>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                        <?php endfor; ?>
                    </div>
                </section>
            <?php endforeach; ?>
        <?php else: ?>
            <?php foreach ($content as $block): ?>
                <?php if (is_array($block)): ?>
                    <section class="mt-6">
                        <?php $renderBlock($block); ?>
                    </section>
                <?php endif; ?>
            <?php endforeach; ?>
        <?php endif; ?>
    <?php endif; ?>
</main>
</body>
</html>
