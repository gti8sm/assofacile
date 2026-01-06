<?php

$title = 'Admin - Site public';
ob_start();

$tenantSlug = (string)($tenantRow['slug'] ?? '');
$tenantKey = $tenantSlug !== '' ? $tenantSlug : (string)($tenantRow['id'] ?? '');

$pageId = $page['id'] ?? null;
$isHome = !empty($page['is_home']);
$isPublished = !empty($page['is_published']);
$slug = (string)($page['slug'] ?? '');
$publicUrl = $isHome ? ('/s/' . $tenantKey) : ('/s/' . $tenantKey . '/' . $slug);
?>
<div class="flex items-center justify-between">
    <div>
        <h1 class="text-2xl font-semibold"><?= $pageId ? 'Éditer une page' : 'Nouvelle page' ?></h1>
        <div class="text-xs text-slate-500"><?= e((string)($tenantRow['name'] ?? '')) ?></div>
    </div>
    <div class="flex items-center gap-2">
        <a class="border border-slate-300 rounded px-3 py-2 text-sm" href="/admin/public-site/pages">Retour</a>
    </div>
</div>

<?php if (!empty($flash)): ?>
    <div class="mt-4 p-3 rounded bg-emerald-50 text-emerald-700 text-sm border border-emerald-200">
        <?= e($flash) ?>
    </div>
<?php endif; ?>

<?php if (!empty($error)): ?>
    <div class="mt-4 p-3 rounded bg-red-50 text-red-700 text-sm border border-red-200">
        <?= e($error) ?>
    </div>
<?php endif; ?>

<div class="mt-4 grid grid-cols-1 lg:grid-cols-3 gap-4">
    <form method="post" action="/admin/public-site/pages/save" class="lg:col-span-2 bg-white border border-slate-200 rounded-lg p-4 space-y-4" onsubmit="window.__ps_builder_sync && window.__ps_builder_sync();">
        <input type="hidden" name="_csrf" value="<?= e(App\Support\Csrf::token()) ?>">
        <input type="hidden" name="id" value="<?= e((string)($pageId ?? 0)) ?>">

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
                <label class="block text-sm font-medium mb-1">Titre</label>
                <input name="title" value="<?= e((string)($page['title'] ?? '')) ?>" class="w-full border border-slate-300 rounded px-3 py-2">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Slug</label>
                <input name="slug" value="<?= e((string)($page['slug'] ?? '')) ?>" class="w-full border border-slate-300 rounded px-3 py-2" placeholder="ex: accueil, contact, adherer">
                <div class="mt-1 text-xs text-slate-500">Minuscules, chiffres, tirets.</div>
            </div>
        </div>

        <label class="flex items-center gap-2 text-sm">
            <input type="checkbox" name="is_home" value="1" <?= $isHome ? 'checked' : '' ?>>
            Définir comme page d'accueil
        </label>

        <input type="hidden" name="content_json" id="content_json" value="">

        <div class="border border-slate-200 rounded-lg overflow-hidden">
            <div class="bg-slate-50 px-4 py-3 flex items-center justify-between">
                <div class="font-medium">Builder (sections)</div>
                <div class="flex items-center gap-2">
                    <button class="border border-slate-300 rounded px-3 py-1.5 text-xs" type="button" onclick="window.__ps_builder_addSection('1col')">+ Section 1 colonne</button>
                    <button class="border border-slate-300 rounded px-3 py-1.5 text-xs" type="button" onclick="window.__ps_builder_addSection('2col')">+ Section 2 colonnes</button>
                    <button class="border border-slate-300 rounded px-3 py-1.5 text-xs" type="button" onclick="window.__ps_builder_addSection('3col')">+ Section 3 colonnes</button>
                </div>
            </div>
            <div class="p-4" id="builder_root"></div>
        </div>

        <details class="border border-slate-200 rounded-lg">
            <summary class="cursor-pointer select-none px-4 py-3 bg-slate-50 font-medium">Mode avancé (JSON)</summary>
            <div class="p-4">
                <label class="block text-sm font-medium mb-1">Contenu (JSON)</label>
                <textarea id="content_json_raw" class="w-full border border-slate-300 rounded px-3 py-2 font-mono text-xs" rows="16"><?= e((string)$contentJson) ?></textarea>
                <div class="mt-2 flex items-center gap-2">
                    <button class="border border-slate-300 rounded px-3 py-2 text-sm" type="button" onclick="window.__ps_builder_loadFromRaw();">Recharger le builder depuis le JSON</button>
                </div>
            </div>
        </details>

        <button class="bg-slate-900 text-white rounded px-3 py-2 text-sm" type="submit">Sauvegarder (brouillon)</button>
    </form>

    <div class="bg-white border border-slate-200 rounded-lg p-4 space-y-3">
        <div class="font-semibold">Publication</div>
        <div class="text-sm text-slate-600">
            Statut :
            <?php if ($isPublished): ?>
                <span class="text-emerald-700">Publiée</span>
            <?php else: ?>
                <span class="text-slate-600">Brouillon</span>
            <?php endif; ?>
        </div>

        <?php if ($pageId): ?>
            <form method="post" action="/admin/public-site/pages/publish">
                <input type="hidden" name="_csrf" value="<?= e(App\Support\Csrf::token()) ?>">
                <input type="hidden" name="id" value="<?= e((string)$pageId) ?>">
                <button class="bg-emerald-600 text-white rounded px-3 py-2 text-sm" type="submit">Publier la dernière révision</button>
            </form>

            <a class="border border-slate-300 rounded px-3 py-2 text-sm inline-block" href="<?= e($publicUrl) ?>" target="_blank">Ouvrir</a>
        <?php else: ?>
            <div class="text-sm text-slate-500">Crée la page puis publie-la.</div>
        <?php endif; ?>

        <div class="text-xs text-slate-500">
            URL publique: <span class="font-mono"><?= e($publicUrl) ?></span>
        </div>
    </div>
</div>

<script>
(() => {
    const root = document.getElementById('builder_root');
    const raw = document.getElementById('content_json_raw');
    const hidden = document.getElementById('content_json');

    if (!root || !raw || !hidden) {
        return;
    }

    const defaultBlock = (type) => {
        if (type === 'text') {
            return { type: 'text', props: { title: 'Titre', body: 'Texte...' } };
        }
        if (type === 'image') {
            return { type: 'image', props: { src: '', alt: '' } };
        }
        if (type === 'cta') {
            return { type: 'cta', props: { label: 'Cliquer', href: '/' } };
        }
        if (type === 'contact') {
            return { type: 'contact', props: { title: 'Contact', address: '', phone: '', email: '', hours: '' } };
        }
        if (type === 'socials') {
            return { type: 'socials', props: { title: 'Réseaux sociaux', items: [{ label: 'Facebook', href: '' }] } };
        }
        return { type: 'text', props: { title: 'Titre', body: 'Texte...' } };
    };

    const normalize = (data) => {
        if (data && typeof data === 'object' && Array.isArray(data.sections)) {
            return data;
        }
        if (Array.isArray(data)) {
            return {
                schema: 2,
                sections: [{ layout: '1col', columns: [data] }],
            };
        }
        return { schema: 2, sections: [] };
    };

    let state = normalize(JSON.parse(raw.value || '{"schema":2,"sections":[]}'));

    const sync = () => {
        const json = JSON.stringify(state);
        hidden.value = json;
        try {
            raw.value = JSON.stringify(state, null, 2);
        } catch (e) {
        }
    };

    const el = (tag, attrs = {}, children = []) => {
        const n = document.createElement(tag);
        for (const [k, v] of Object.entries(attrs)) {
            if (k === 'class') n.className = v;
            else if (k === 'text') n.textContent = v;
            else if (k.startsWith('on') && typeof v === 'function') n.addEventListener(k.substring(2), v);
            else n.setAttribute(k, v);
        }
        for (const c of children) n.appendChild(c);
        return n;
    };

    const render = () => {
        root.innerHTML = '';

        if (!Array.isArray(state.sections)) state.sections = [];

        state.sections.forEach((section, si) => {
            const layout = section.layout || '1col';
            const cols = layout === '3col' ? 3 : (layout === '2col' ? 2 : 1);
            if (!Array.isArray(section.columns)) section.columns = [];
            while (section.columns.length < cols) section.columns.push([]);
            section.columns = section.columns.slice(0, cols);

            const header = el('div', { class: 'flex items-center justify-between gap-2' }, [
                el('div', { class: 'font-medium', text: `Section ${si + 1} (${cols} colonne${cols > 1 ? 's' : ''})` }),
                el('div', { class: 'flex items-center gap-2' }, [
                    el('button', { type: 'button', class: 'border border-slate-300 rounded px-2 py-1 text-xs', onclick: () => { if (si > 0) { const t = state.sections[si - 1]; state.sections[si - 1] = state.sections[si]; state.sections[si] = t; render(); sync(); } } }, [document.createTextNode('↑')]),
                    el('button', { type: 'button', class: 'border border-slate-300 rounded px-2 py-1 text-xs', onclick: () => { if (si < state.sections.length - 1) { const t = state.sections[si + 1]; state.sections[si + 1] = state.sections[si]; state.sections[si] = t; render(); sync(); } } }, [document.createTextNode('↓')]),
                    el('button', { type: 'button', class: 'border border-red-200 text-red-700 rounded px-2 py-1 text-xs', onclick: () => { state.sections.splice(si, 1); render(); sync(); } }, [document.createTextNode('Supprimer')]),
                ]),
            ]);

            const gridClass = cols === 3 ? 'grid grid-cols-1 md:grid-cols-3 gap-3' : (cols === 2 ? 'grid grid-cols-1 md:grid-cols-2 gap-3' : 'grid grid-cols-1 gap-3');
            const grid = el('div', { class: gridClass });

            section.columns.forEach((col, ci) => {
                if (!Array.isArray(col)) section.columns[ci] = [];
                const colWrap = el('div', { class: 'border border-slate-200 rounded-lg p-3 bg-slate-50' });

                const addRow = el('div', { class: 'flex items-center justify-between mb-2' }, [
                    el('div', { class: 'text-xs text-slate-600', text: `Colonne ${ci + 1}` }),
                    (() => {
                        const select = el('select', { class: 'border border-slate-300 rounded px-2 py-1 text-xs' });
                        ['text', 'image', 'cta', 'contact', 'socials'].forEach(t => {
                            select.appendChild(el('option', { value: t, text: t }));
                        });
                        const btn = el('button', { type: 'button', class: 'ml-2 border border-slate-300 rounded px-2 py-1 text-xs', onclick: () => { const t = select.value; section.columns[ci].push(defaultBlock(t)); render(); sync(); } }, [document.createTextNode('+ Bloc')]);
                        const wrap = el('div', {});
                        wrap.appendChild(select);
                        wrap.appendChild(btn);
                        return wrap;
                    })(),
                ]);
                colWrap.appendChild(addRow);

                (section.columns[ci] || []).forEach((block, bi) => {
                    if (!block || typeof block !== 'object') return;
                    if (!block.props || typeof block.props !== 'object') block.props = {};

                    const card = el('div', { class: 'bg-white border border-slate-200 rounded-lg p-3 mb-2' });
                    const top = el('div', { class: 'flex items-center justify-between gap-2' }, [
                        (() => {
                            const select = el('select', { class: 'border border-slate-300 rounded px-2 py-1 text-xs' });
                            ['text', 'image', 'cta', 'contact', 'socials'].forEach(t => {
                                const opt = el('option', { value: t, text: t });
                                if ((block.type || '') === t) opt.selected = true;
                                select.appendChild(opt);
                            });
                            select.addEventListener('change', () => {
                                const t = select.value;
                                section.columns[ci][bi] = defaultBlock(t);
                                render();
                                sync();
                            });
                            return select;
                        })(),
                        el('div', { class: 'flex items-center gap-1' }, [
                            el('button', { type: 'button', class: 'border border-slate-300 rounded px-2 py-1 text-xs', onclick: () => { if (bi > 0) { const t = section.columns[ci][bi - 1]; section.columns[ci][bi - 1] = section.columns[ci][bi]; section.columns[ci][bi] = t; render(); sync(); } } }, [document.createTextNode('↑')]),
                            el('button', { type: 'button', class: 'border border-slate-300 rounded px-2 py-1 text-xs', onclick: () => { if (bi < section.columns[ci].length - 1) { const t = section.columns[ci][bi + 1]; section.columns[ci][bi + 1] = section.columns[ci][bi]; section.columns[ci][bi] = t; render(); sync(); } } }, [document.createTextNode('↓')]),
                            el('button', { type: 'button', class: 'border border-red-200 text-red-700 rounded px-2 py-1 text-xs', onclick: () => { section.columns[ci].splice(bi, 1); render(); sync(); } }, [document.createTextNode('Suppr')]),
                        ]),
                    ]);
                    card.appendChild(top);

                    const type = block.type || 'text';
                    const fields = el('div', { class: 'mt-2 space-y-2' });

                    const input = (label, value, onChange) => {
                        const wrap = el('div');
                        wrap.appendChild(el('div', { class: 'text-xs text-slate-600 mb-1', text: label }));
                        const inp = el('input', { class: 'w-full border border-slate-300 rounded px-2 py-1 text-sm', value: value || '' });
                        inp.addEventListener('input', () => onChange(inp.value));
                        wrap.appendChild(inp);
                        return wrap;
                    };

                    const textarea = (label, value, onChange) => {
                        const wrap = el('div');
                        wrap.appendChild(el('div', { class: 'text-xs text-slate-600 mb-1', text: label }));
                        const ta = el('textarea', { class: 'w-full border border-slate-300 rounded px-2 py-1 text-sm', rows: '3' });
                        ta.value = value || '';
                        ta.addEventListener('input', () => onChange(ta.value));
                        wrap.appendChild(ta);
                        return wrap;
                    };

                    if (type === 'text') {
                        fields.appendChild(input('Titre', block.props.title || '', v => { block.props.title = v; sync(); }));
                        fields.appendChild(textarea('Texte', block.props.body || '', v => { block.props.body = v; sync(); }));
                    }

                    if (type === 'image') {
                        fields.appendChild(input('URL image', block.props.src || '', v => { block.props.src = v; sync(); }));
                        fields.appendChild(input('Alt', block.props.alt || '', v => { block.props.alt = v; sync(); }));
                    }

                    if (type === 'cta') {
                        fields.appendChild(input('Libellé', block.props.label || '', v => { block.props.label = v; sync(); }));
                        fields.appendChild(input('Lien', block.props.href || '', v => { block.props.href = v; sync(); }));
                    }

                    if (type === 'contact') {
                        fields.appendChild(input('Titre', block.props.title || '', v => { block.props.title = v; sync(); }));
                        fields.appendChild(textarea('Adresse', block.props.address || '', v => { block.props.address = v; sync(); }));
                        fields.appendChild(input('Téléphone', block.props.phone || '', v => { block.props.phone = v; sync(); }));
                        fields.appendChild(input('Email', block.props.email || '', v => { block.props.email = v; sync(); }));
                        fields.appendChild(input('Horaires', block.props.hours || '', v => { block.props.hours = v; sync(); }));
                    }

                    if (type === 'socials') {
                        fields.appendChild(input('Titre', block.props.title || '', v => { block.props.title = v; sync(); }));
                        if (!Array.isArray(block.props.items)) block.props.items = [];
                        const list = el('div', { class: 'space-y-2' });
                        block.props.items.forEach((it, ii) => {
                            if (!it || typeof it !== 'object') return;
                            const row = el('div', { class: 'grid grid-cols-1 sm:grid-cols-2 gap-2' });
                            row.appendChild(input('Label', it.label || '', v => { it.label = v; sync(); }));
                            row.appendChild(input('Lien', it.href || '', v => { it.href = v; sync(); }));
                            const del = el('button', { type: 'button', class: 'border border-red-200 text-red-700 rounded px-2 py-1 text-xs mt-1', onclick: () => { block.props.items.splice(ii, 1); render(); sync(); } }, [document.createTextNode('Supprimer lien')]);
                            list.appendChild(row);
                            list.appendChild(del);
                        });
                        const add = el('button', { type: 'button', class: 'border border-slate-300 rounded px-2 py-1 text-xs', onclick: () => { block.props.items.push({ label: 'Lien', href: '' }); render(); sync(); } }, [document.createTextNode('+ Lien')]);
                        fields.appendChild(list);
                        fields.appendChild(add);
                    }

                    card.appendChild(fields);
                    colWrap.appendChild(card);
                });

                grid.appendChild(colWrap);
            });

            const sectionWrap = el('div', { class: 'mb-4' }, [
                header,
                el('div', { class: 'mt-2' }, [grid]),
            ]);
            root.appendChild(sectionWrap);
        });
    };

    window.__ps_builder_addSection = (layout) => {
        const cols = layout === '3col' ? 3 : (layout === '2col' ? 2 : 1);
        const columns = [];
        for (let i = 0; i < cols; i++) columns.push([]);
        state.sections.push({ layout, columns });
        render();
        sync();
    };

    window.__ps_builder_sync = () => {
        sync();
    };

    window.__ps_builder_loadFromRaw = () => {
        try {
            state = normalize(JSON.parse(raw.value || '{"schema":2,"sections":[]}'));
            render();
            sync();
        } catch (e) {
            alert('JSON invalide');
        }
    };

    render();
    sync();
})();
</script>

<?php
$content = ob_get_clean();
require base_path('views/layout.php');
