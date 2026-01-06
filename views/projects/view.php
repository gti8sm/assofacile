<?php

$title = 'Projet';
ob_start();

$project = is_array($project ?? null) ? $project : [];
$actions = is_array($actions ?? null) ? $actions : [];
$tasks = is_array($tasks ?? null) ? $tasks : [];
$participants = is_array($participants ?? null) ? $participants : [];
$treasuryBudgets = is_array($treasuryBudgets ?? null) ? $treasuryBudgets : [];
$treasuryTransactions = is_array($treasuryTransactions ?? null) ? $treasuryTransactions : [];
$links = is_array($links ?? null) ? $links : [];
$documents = is_array($documents ?? null) ? $documents : [];

$projectId = (int)($project['id'] ?? 0);
$projectName = (string)($project['name'] ?? '');
$projectStatus = (string)($project['status'] ?? '');
$projectStarts = (string)($project['starts_on'] ?? '');
$projectEnds = (string)($project['ends_on'] ?? '');
$projectFunding = (string)($project['funding_type'] ?? '');
$projectDescription = (string)($project['description'] ?? '');

$statsTasksTotal = count($tasks);
$statsTasksDone = 0;
$statsTasksOverdue = 0;
$statsTasksUpcoming7 = 0;
$today = date('Y-m-d');
$in7 = date('Y-m-d', strtotime('+7 days'));
foreach ($tasks as $t) {
    $st = (string)($t['status'] ?? '');
    if ($st === 'done') {
        $statsTasksDone++;
    }
    $due = (string)($t['due_on'] ?? '');
    if ($due !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $due)) {
        if ($st !== 'done' && $due < $today) {
            $statsTasksOverdue++;
        }
        if ($st !== 'done' && $due >= $today && $due <= $in7) {
            $statsTasksUpcoming7++;
        }
    }
}
$statsParticipantsTotal = count($participants);
$statsActionsTotal = count($actions);

$budgetIncomeCents = 0;
$budgetExpenseCents = 0;
foreach ($treasuryTransactions as $tt) {
    $txType = (string)($tt['type'] ?? '');
    $allocCents = (int)($tt['allocated_cents'] ?? 0);
    if ($allocCents <= 0) {
        continue;
    }
    if ($txType === 'income') {
        $budgetIncomeCents += $allocCents;
    } else {
        $budgetExpenseCents += $allocCents;
    }
}
$budgetBalanceCents = $budgetIncomeCents - $budgetExpenseCents;

$tabCounts = [
    'actions' => $statsActionsTotal,
    'tasks' => $statsTasksTotal,
    'budget' => count($treasuryTransactions),
    'docs' => count($documents),
    'links' => count($links),
];
?>
<div class="max-w-5xl">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h1 class="text-2xl font-semibold"><?= e($projectName !== '' ? $projectName : 'Projet') ?></h1>
            <div class="mt-1 text-xs text-slate-500">
                #<?= e((string)$projectId) ?>
                <?php if ($projectStatus !== ''): ?> · <span class="bg-slate-100 text-slate-700 px-2 py-1 rounded"><?= e($projectStatus) ?></span><?php endif; ?>
                <?php if ($projectStarts !== '' || $projectEnds !== ''): ?> · <?= e(trim(($projectStarts !== '' ? $projectStarts : '') . (($projectEnds !== '' ? ' → ' . $projectEnds : '')))) ?><?php endif; ?>
                <?php if ($projectFunding !== ''): ?> · <?= e($projectFunding) ?><?php endif; ?>
            </div>
        </div>

        <div class="flex flex-wrap gap-2">
            <a class="border border-slate-300 rounded px-3 py-2 text-sm" href="<?= e(tenant_path('/projects')) ?>">Retour</a>
            <?php if (App\Support\Access::can((int)$_SESSION['tenant_id'], (int)$_SESSION['user_id'], 'projects', 'write')): ?>
                <a class="border border-slate-300 rounded px-3 py-2 text-sm" href="<?= e(tenant_path('/projects/edit?id=' . $projectId)) ?>">Modifier</a>
                <form method="post" action="<?= e(tenant_path('/projects/duplicate')) ?>">
                    <input type="hidden" name="_csrf" value="<?= e(App\Support\Csrf::token()) ?>">
                    <input type="hidden" name="project_id" value="<?= e((string)$projectId) ?>">
                    <button class="border border-slate-300 rounded px-3 py-2 text-sm" type="submit">Dupliquer</button>
                </form>
            <?php endif; ?>
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

    <?php if ($projectDescription !== ''): ?>
        <div class="mt-4 bg-white border border-slate-200 rounded-lg p-4">
            <div class="text-sm font-medium">Description</div>
            <div class="mt-1 text-sm text-slate-700 whitespace-pre-line"><?= e($projectDescription) ?></div>
        </div>
    <?php endif; ?>

    <div class="mt-6">
        <div class="border-b border-slate-200 flex flex-wrap gap-2">
            <button type="button" class="project-tab px-3 py-2 text-sm rounded-t border border-slate-200 bg-white" data-tab="recap">Récap</button>
            <button type="button" class="project-tab px-3 py-2 text-sm rounded-t border border-slate-200 bg-slate-50" data-tab="actions">Sous-projets / Actions <span class="ml-1 text-xs bg-slate-100 text-slate-700 px-2 py-0.5 rounded"><?= (int)($tabCounts['actions'] ?? 0) ?></span></button>
            <button type="button" class="project-tab px-3 py-2 text-sm rounded-t border border-slate-200 bg-slate-50" data-tab="tasks">Tâches <span class="ml-1 text-xs bg-slate-100 text-slate-700 px-2 py-0.5 rounded"><?= (int)($tabCounts['tasks'] ?? 0) ?></span></button>
            <button type="button" class="project-tab px-3 py-2 text-sm rounded-t border border-slate-200 bg-slate-50" data-tab="budget">Budget <span class="ml-1 text-xs bg-slate-100 text-slate-700 px-2 py-0.5 rounded"><?= (int)($tabCounts['budget'] ?? 0) ?></span></button>
            <button type="button" class="project-tab px-3 py-2 text-sm rounded-t border border-slate-200 bg-slate-50" data-tab="docs">Docs <span class="ml-1 text-xs bg-slate-100 text-slate-700 px-2 py-0.5 rounded"><?= (int)($tabCounts['docs'] ?? 0) ?></span></button>
            <button type="button" class="project-tab px-3 py-2 text-sm rounded-t border border-slate-200 bg-slate-50" data-tab="links">Liens utiles <span class="ml-1 text-xs bg-slate-100 text-slate-700 px-2 py-0.5 rounded"><?= (int)($tabCounts['links'] ?? 0) ?></span></button>
        </div>

        <div class="mt-4">
            <div class="project-tab-panel" data-panel="recap">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                    <div class="bg-white border border-slate-200 rounded-lg p-4">
                        <div class="text-sm font-medium">Résumé</div>
                        <div class="mt-3 grid grid-cols-2 gap-3">
                            <div class="border border-slate-200 rounded p-3">
                                <div class="text-xs text-slate-500">Actions</div>
                                <div class="text-lg font-semibold"><?= (int)$statsActionsTotal ?></div>
                            </div>
                            <div class="border border-slate-200 rounded p-3">
                                <div class="text-xs text-slate-500">Participants</div>
                                <div class="text-lg font-semibold"><?= (int)$statsParticipantsTotal ?></div>
                            </div>
                            <div class="border border-slate-200 rounded p-3">
                                <div class="text-xs text-slate-500">Tâches</div>
                                <div class="text-lg font-semibold"><?= (int)$statsTasksTotal ?></div>
                                <div class="text-xs text-slate-500 mt-1">Terminées: <?= (int)$statsTasksDone ?></div>
                            </div>
                            <div class="border border-slate-200 rounded p-3">
                                <div class="text-xs text-slate-500">À surveiller</div>
                                <div class="text-xs text-slate-700 mt-1">En retard: <span class="font-semibold"><?= (int)$statsTasksOverdue ?></span></div>
                                <div class="text-xs text-slate-700">À venir (7j): <span class="font-semibold"><?= (int)$statsTasksUpcoming7 ?></span></div>
                            </div>
                            <div class="border border-slate-200 rounded p-3 col-span-2">
                                <div class="text-xs text-slate-500">Budget (écritures liées)</div>
                                <div class="mt-1 grid grid-cols-1 sm:grid-cols-3 gap-2 text-sm">
                                    <div class="text-emerald-700">Recettes: <span class="font-semibold"><?= number_format($budgetIncomeCents / 100, 2, ',', ' ') ?> €</span></div>
                                    <div class="text-rose-700">Dépenses: <span class="font-semibold"><?= number_format($budgetExpenseCents / 100, 2, ',', ' ') ?> €</span></div>
                                    <div class="text-slate-900">Solde: <span class="font-semibold"><?= number_format($budgetBalanceCents / 100, 2, ',', ' ') ?> €</span></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white border border-slate-200 rounded-lg p-4">
                        <div class="text-sm font-medium">Informations</div>
                        <div class="mt-2 text-sm text-slate-700 space-y-1">
                            <div><span class="text-slate-500">Statut:</span> <?= e($projectStatus !== '' ? $projectStatus : '—') ?></div>
                            <div><span class="text-slate-500">Dates:</span> <?= e(($projectStarts !== '' || $projectEnds !== '') ? trim(($projectStarts !== '' ? $projectStarts : '') . (($projectEnds !== '' ? ' → ' . $projectEnds : ''))) : '—') ?></div>
                            <div><span class="text-slate-500">Financement:</span> <?= e($projectFunding !== '' ? $projectFunding : '—') ?></div>
                        </div>
                        <div class="mt-4 flex flex-wrap gap-2">
                            <?php if (App\Support\Access::can((int)$_SESSION['tenant_id'], (int)$_SESSION['user_id'], 'projects', 'write')): ?>
                                <button type="button" class="border border-slate-300 rounded px-3 py-2 text-sm" data-modal-open="modal_add_task">Ajouter tâche</button>
                                <button type="button" class="border border-slate-300 rounded px-3 py-2 text-sm" data-modal-open="modal_add_action">Ajouter action</button>
                                <button type="button" class="border border-slate-300 rounded px-3 py-2 text-sm" data-modal-open="modal_add_participant">Ajouter participant</button>
                                <button type="button" class="border border-slate-300 rounded px-3 py-2 text-sm" data-modal-open="modal_add_doc">Ajouter doc</button>
                                <button type="button" class="border border-slate-300 rounded px-3 py-2 text-sm" data-modal-open="modal_add_link">Ajouter lien</button>
                            <?php endif; ?>
                            <a class="border border-slate-300 rounded px-3 py-2 text-sm" href="<?= e(tenant_path('/treasury')) ?>">Ouvrir trésorerie</a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="project-tab-panel hidden" data-panel="actions">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                    <div class="bg-white border border-slate-200 rounded-lg p-4">
                        <div class="flex items-center justify-between">
                            <div class="text-sm font-medium">Actions / sous-projets</div>
                            <?php if (App\Support\Access::can((int)$_SESSION['tenant_id'], (int)$_SESSION['user_id'], 'projects', 'write')): ?>
                                <button type="button" class="border border-slate-300 rounded px-3 py-2 text-sm" data-modal-open="modal_add_action">Ajouter</button>
                            <?php endif; ?>
                        </div>

                        <?php if (empty($actions)): ?>
                            <div class="mt-2 text-sm text-slate-500">Aucune action.</div>
                        <?php else: ?>
                            <div class="mt-3 space-y-2">
                                <?php foreach ($actions as $a): ?>
                                    <?php
                                    $aid = (int)($a['id'] ?? 0);
                                    $pCount = 0;
                                    $tCount = 0;
                                    foreach ($participants as $pp) {
                                        if ((int)($pp['action_id'] ?? 0) === $aid) {
                                            $pCount++;
                                        }
                                    }
                                    foreach ($tasks as $t) {
                                        if ((int)($t['action_id'] ?? 0) === $aid) {
                                            $tCount++;
                                        }
                                    }
                                    ?>
                                    <div class="border border-slate-200 rounded p-3">
                                        <div class="flex items-start justify-between gap-2">
                                            <div>
                                                <div class="font-medium text-sm"><?= e((string)$a['name']) ?></div>
                                                <div class="mt-1 text-xs text-slate-500">
                                                    Participants: <?= (int)$pCount ?> · Tâches: <?= (int)$tCount ?>
                                                </div>
                                            </div>
                                            <?php if (App\Support\Access::can((int)$_SESSION['tenant_id'], (int)$_SESSION['user_id'], 'projects', 'write')): ?>
                                                <a class="text-sm underline" href="<?= e(tenant_path('/projects/actions/edit?id=' . $aid . '&project_id=' . $projectId)) ?>">Modifier</a>
                                            <?php endif; ?>
                                        </div>
                                        <?php if (!empty($a['schedule_rule'])): ?>
                                            <div class="text-xs text-slate-500 mt-1">Récurrence: <?= e((string)$a['schedule_rule']) ?></div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <div class="mt-4 text-xs text-slate-500">Création via la modale « Ajouter ».</div>
                    </div>

                    <div class="bg-white border border-slate-200 rounded-lg p-4">
                        <div class="flex items-center justify-between">
                            <div class="text-sm font-medium">Participants</div>
                            <a class="text-xs text-slate-600 hover:text-slate-900" href="<?= e(tenant_path('/tiers')) ?>">Ouvrir les tiers</a>
                            <?php if (App\Support\Access::can((int)$_SESSION['tenant_id'], (int)$_SESSION['user_id'], 'projects', 'write')): ?>
                                <button type="button" class="border border-slate-300 rounded px-3 py-2 text-sm" data-modal-open="modal_add_participant">Ajouter</button>
                            <?php endif; ?>
                        </div>

                        <div class="mt-3">
                            <input id="participants_search" class="w-full border border-slate-300 rounded px-3 py-2 text-sm" placeholder="Rechercher un participant (nom, rôle, action)..." autocomplete="off">
                        </div>

                        <?php if (empty($participants)): ?>
                            <div class="mt-2 text-sm text-slate-500">Aucun participant.</div>
                        <?php else: ?>
                            <div class="mt-3 bg-white border border-slate-200 rounded-lg overflow-hidden">
                                <table class="w-full text-sm">
                                    <thead class="bg-slate-50">
                                    <tr>
                                        <th class="text-left p-3">Rôle</th>
                                        <th class="text-left p-3">Personne</th>
                                        <th class="text-left p-3">Action</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($participants as $pp): ?>
                                        <?php
                                        $role = (string)($pp['role'] ?? '');
                                        $tierName = (string)($pp['tier_name'] ?? '');
                                        $userName = (string)($pp['user_name'] ?? '');
                                        $actionName = (string)($pp['action_name'] ?? '');
                                        $who = $tierName !== '' ? $tierName : ($userName !== '' ? $userName : ('#' . (string)($pp['tier_id'] ?? '')));
                                        $rowSearch = mb_strtolower(trim($role . ' ' . $who . ' ' . $actionName));
                                        ?>
                                        <tr class="border-t border-slate-200 project-participant" data-search="<?= e($rowSearch) ?>">
                                            <td class="p-3"><?= e($role !== '' ? $role : '—') ?></td>
                                            <td class="p-3"><?= e($who !== '' ? $who : '—') ?></td>
                                            <td class="p-3"><?= e($actionName !== '' ? $actionName : '—') ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>

                        <div class="mt-4 text-xs text-slate-500">Création via la modale « Ajouter ».</div>
                    </div>
                </div>
            </div>

            <div class="project-tab-panel hidden" data-panel="tasks">
                <div class="bg-white border border-slate-200 rounded-lg p-4">
                    <div class="flex items-center justify-between">
                        <div class="text-sm font-medium">Tâches</div>
                        <?php if (App\Support\Access::can((int)$_SESSION['tenant_id'], (int)$_SESSION['user_id'], 'projects', 'write')): ?>
                            <button type="button" class="border border-slate-300 rounded px-3 py-2 text-sm" data-modal-open="modal_add_task">Ajouter</button>
                        <?php endif; ?>
                    </div>

                    <div class="mt-3">
                        <input id="tasks_search" class="w-full border border-slate-300 rounded px-3 py-2 text-sm" placeholder="Rechercher une tâche..." autocomplete="off">
                    </div>

                    <div class="mt-3 grid grid-cols-1 sm:grid-cols-4 gap-3">
                        <div>
                            <label class="block text-xs text-slate-600 mb-1">Statut</label>
                            <select id="tasks_filter_status" class="w-full border border-slate-300 rounded px-3 py-2 text-sm">
                                <option value="">Tous</option>
                                <option value="todo">todo</option>
                                <option value="doing">doing</option>
                                <option value="done">done</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs text-slate-600 mb-1">Période</label>
                            <select id="tasks_filter_when" class="w-full border border-slate-300 rounded px-3 py-2 text-sm">
                                <option value="">Toutes</option>
                                <option value="upcoming">À venir</option>
                                <option value="overdue">En retard</option>
                                <option value="nodate">Sans date</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs text-slate-600 mb-1">Action</label>
                            <select id="tasks_filter_action" class="w-full border border-slate-300 rounded px-3 py-2 text-sm">
                                <option value="">Toutes</option>
                                <?php foreach ($actions as $a): ?>
                                    <option value="<?= e((string)$a['id']) ?>"><?= e((string)$a['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="flex items-end">
                            <button id="tasks_filter_reset" type="button" class="border border-slate-300 rounded px-3 py-2 text-sm w-full">Réinitialiser</button>
                        </div>
                    </div>

                    <?php if (empty($tasks)): ?>
                        <div class="mt-3 text-sm text-slate-500">Aucune tâche.</div>
                    <?php else: ?>
                        <div class="mt-3 space-y-2" id="tasks_list">
                            <?php foreach ($tasks as $t): ?>
                                <?php
                                $label = (string)$t['title'];
                                $due = (string)($t['due_on'] ?? '');
                                $actionName = (string)($t['action_name'] ?? '');
                                $status = (string)($t['status'] ?? '');
                                $aid = (int)($t['action_id'] ?? 0);
                                ?>
                                <div class="border border-slate-200 rounded p-3 project-task" data-status="<?= e($status) ?>" data-due="<?= e($due) ?>" data-action-id="<?= $aid > 0 ? $aid : 0 ?>" data-search="<?= e(mb_strtolower(trim($label . ' ' . $status . ' ' . $due . ' ' . $actionName))) ?>">
                                    <div class="flex items-start justify-between gap-2">
                                        <div>
                                            <div class="font-medium text-sm"><?= e($label) ?></div>
                                            <div class="mt-1 text-xs text-slate-500">
                                                <?= e($status !== '' ? $status : '—') ?>
                                                <?php if ($due !== ''): ?> · échéance: <?= e($due) ?><?php endif; ?>
                                                <?php if ($actionName !== ''): ?> · action: <?= e($actionName) ?><?php endif; ?>
                                            </div>
                                        </div>
                                        <?php if (App\Support\Access::can((int)$_SESSION['tenant_id'], (int)$_SESSION['user_id'], 'projects', 'write')): ?>
                                            <a class="text-sm underline" href="<?= e(tenant_path('/projects/tasks/edit?id=' . (int)$t['id'] . '&project_id=' . $projectId)) ?>">Modifier</a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <div class="mt-4 text-xs text-slate-500">Création via la modale « Ajouter ».</div>
                </div>
            </div>

            <div class="project-tab-panel hidden" data-panel="budget">
                <div class="bg-white border border-slate-200 rounded-lg p-4">
                    <div class="flex items-center justify-between">
                        <div class="text-sm font-medium">Budget / Trésorerie</div>
                        <a class="text-xs text-slate-600 hover:text-slate-900" href="<?= e(tenant_path('/treasury')) ?>">Ouvrir la trésorerie</a>
                    </div>

                    <div class="mt-3 grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div class="border border-slate-200 rounded p-3">
                            <div class="text-xs text-slate-500">Recettes liées</div>
                            <div class="text-lg font-semibold text-emerald-700"><?= number_format($budgetIncomeCents / 100, 2, ',', ' ') ?> €</div>
                        </div>
                        <div class="border border-slate-200 rounded p-3">
                            <div class="text-xs text-slate-500">Dépenses liées</div>
                            <div class="text-lg font-semibold text-rose-700"><?= number_format($budgetExpenseCents / 100, 2, ',', ' ') ?> €</div>
                        </div>
                        <div class="border border-slate-200 rounded p-3">
                            <div class="text-xs text-slate-500">Solde</div>
                            <div class="text-lg font-semibold"><?= number_format($budgetBalanceCents / 100, 2, ',', ' ') ?> €</div>
                        </div>
                    </div>

                    <?php if (!empty($treasuryBudgets)): ?>
                        <div class="mt-2 text-xs text-slate-600">
                            Budgets liés:
                            <?= e(implode(', ', array_values(array_filter(array_map(static fn($b) => (string)($b['name'] ?? ''), $treasuryBudgets))))) ?>
                        </div>
                    <?php endif; ?>

                    <?php if (empty($treasuryTransactions)): ?>
                        <div class="mt-3 text-sm text-slate-500">Aucune écriture liée via la ventilation budget.</div>
                    <?php else: ?>
                        <div class="mt-3 bg-white border border-slate-200 rounded-lg overflow-hidden">
                            <table class="w-full text-sm">
                                <thead class="bg-slate-50">
                                <tr>
                                    <th class="text-left p-3">Date</th>
                                    <th class="text-left p-3">Libellé</th>
                                    <th class="text-left p-3">Type</th>
                                    <th class="text-right p-3">Montant</th>
                                    <th class="text-right p-3">Ventilé</th>
                                    <th class="text-right p-3">Voir</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($treasuryTransactions as $tt): ?>
                                    <?php
                                    $txId = (int)($tt['id'] ?? 0);
                                    $txType = (string)($tt['type'] ?? '');
                                    $txLabel = (string)($tt['label'] ?? '');
                                    $txDate = (string)($tt['occurred_on'] ?? '');
                                    $amountCents = (int)($tt['amount_cents'] ?? 0);
                                    $allocCents = (int)($tt['allocated_cents'] ?? 0);
                                    ?>
                                    <tr class="border-t border-slate-200">
                                        <td class="p-3 text-slate-700"><?= e($txDate !== '' ? $txDate : '—') ?></td>
                                        <td class="p-3"><?= e($txLabel !== '' ? $txLabel : '—') ?></td>
                                        <td class="p-3 text-slate-700"><?= e($txType !== '' ? $txType : '—') ?></td>
                                        <td class="p-3 text-right text-slate-700"><?= number_format($amountCents / 100, 2, ',', ' ') ?> €</td>
                                        <td class="p-3 text-right text-slate-700"><?= number_format($allocCents / 100, 2, ',', ' ') ?> €</td>
                                        <td class="p-3 text-right">
                                            <?php if ($txId > 0): ?>
                                                <a class="text-xs underline" href="<?= e(tenant_path('/treasury/edit?id=' . $txId . '&return_to=' . urlencode('/projects/view?id=' . $projectId))) ?>">Ouvrir</a>
                                            <?php else: ?>
                                                —
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="project-tab-panel hidden" data-panel="docs">
                <div class="bg-white border border-slate-200 rounded-lg p-4">
                    <div class="flex items-center justify-between">
                        <div class="text-sm font-medium">Documents</div>
                        <?php if (App\Support\Access::can((int)$_SESSION['tenant_id'], (int)$_SESSION['user_id'], 'projects', 'write')): ?>
                            <button type="button" class="border border-slate-300 rounded px-3 py-2 text-sm" data-modal-open="modal_add_doc">Ajouter</button>
                        <?php endif; ?>
                    </div>

                    <?php if (empty($documents)): ?>
                        <div class="mt-3 text-sm text-slate-500">Aucun document.</div>
                    <?php else: ?>
                        <div class="mt-3 space-y-2">
                            <?php foreach ($documents as $d): ?>
                                <?php
                                $did = (int)($d['id'] ?? 0);
                                $title = (string)($d['title'] ?? '');
                                $url = (string)($d['url'] ?? '');
                                $notes = (string)($d['notes'] ?? '');
                                $hasFile = !empty($d['local_path']) || !empty($d['gdrive_file_id']);
                                ?>
                                <div class="border border-slate-200 rounded p-3 flex items-start justify-between gap-2">
                                    <div class="min-w-0">
                                        <div class="font-medium text-sm truncate"><?= e($title !== '' ? $title : 'Document') ?></div>
                                        <?php if ($hasFile && $did > 0): ?>
                                            <a class="text-xs underline text-slate-700" href="<?= e(tenant_path('/projects/documents/download?id=' . $did)) ?>">Télécharger</a>
                                        <?php endif; ?>
                                        <?php if ($url !== ''): ?>
                                            <a class="text-xs underline text-slate-700" href="<?= e($url) ?>" target="_blank" rel="noreferrer"><?= e($url) ?></a>
                                        <?php endif; ?>
                                        <?php if ($notes !== ''): ?>
                                            <div class="mt-1 text-xs text-slate-500 whitespace-pre-line"><?= e($notes) ?></div>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($did > 0 && App\Support\Access::can((int)$_SESSION['tenant_id'], (int)$_SESSION['user_id'], 'projects', 'write')): ?>
                                        <form method="post" action="<?= e(tenant_path('/projects/documents/delete')) ?>" onsubmit="const r = prompt('Motif de suppression (optionnel) :'); this.querySelector('input[name=delete_reason]').value = (r || '').trim(); return confirm('Déplacer ce document dans la corbeille ?');">
                                            <input type="hidden" name="_csrf" value="<?= e(App\Support\Csrf::token()) ?>">
                                            <input type="hidden" name="project_id" value="<?= e((string)$projectId) ?>">
                                            <input type="hidden" name="id" value="<?= e((string)$did) ?>">
                                            <input type="hidden" name="delete_reason" value="">
                                            <button class="border border-red-300 text-red-700 rounded px-2 py-1 text-xs" type="submit">Supprimer</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="project-tab-panel hidden" data-panel="links">
                <div class="bg-white border border-slate-200 rounded-lg p-4">
                    <div class="flex items-center justify-between">
                        <div class="text-sm font-medium">Liens utiles</div>
                        <?php if (App\Support\Access::can((int)$_SESSION['tenant_id'], (int)$_SESSION['user_id'], 'projects', 'write')): ?>
                            <button type="button" class="border border-slate-300 rounded px-3 py-2 text-sm" data-modal-open="modal_add_link">Ajouter</button>
                        <?php endif; ?>
                    </div>

                    <?php if (empty($links)): ?>
                        <div class="mt-3 text-sm text-slate-500">Aucun lien.</div>
                    <?php else: ?>
                        <div class="mt-3 grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <?php foreach ($links as $l): ?>
                                <?php
                                $lid = (int)($l['id'] ?? 0);
                                $label = (string)($l['label'] ?? '');
                                $url = (string)($l['url'] ?? '');
                                ?>
                                <div class="border border-slate-200 rounded p-3 flex items-start justify-between gap-2">
                                    <div class="min-w-0">
                                        <div class="font-medium text-sm truncate"><?= e($label !== '' ? $label : 'Lien') ?></div>
                                        <a class="text-xs underline text-slate-700" href="<?= e($url) ?>" target="_blank" rel="noreferrer"><?= e($url) ?></a>
                                    </div>
                                    <?php if ($lid > 0 && App\Support\Access::can((int)$_SESSION['tenant_id'], (int)$_SESSION['user_id'], 'projects', 'write')): ?>
                                        <form method="post" action="<?= e(tenant_path('/projects/links/delete')) ?>" onsubmit="return confirm('Supprimer ce lien ?');">
                                            <input type="hidden" name="_csrf" value="<?= e(App\Support\Csrf::token()) ?>">
                                            <input type="hidden" name="project_id" value="<?= e((string)$projectId) ?>">
                                            <input type="hidden" name="id" value="<?= e((string)$lid) ?>">
                                            <button class="border border-red-300 text-red-700 rounded px-2 py-1 text-xs" type="submit">Supprimer</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if (App\Support\Access::can((int)$_SESSION['tenant_id'], (int)$_SESSION['user_id'], 'projects', 'write')): ?>
<script>
(() => {
    const tabBtns = Array.from(document.querySelectorAll('.project-tab'));
    const panels = Array.from(document.querySelectorAll('.project-tab-panel'));
    const openTabBtns = Array.from(document.querySelectorAll('.project-open-tab'));

    const setActive = (tab) => {
        if (!tab) tab = 'recap';
        for (const b of tabBtns) {
            const isActive = b.dataset.tab === tab;
            b.className = 'project-tab px-3 py-2 text-sm rounded-t border border-slate-200 ' + (isActive ? 'bg-white' : 'bg-slate-50');
        }
        for (const p of panels) {
            p.classList.toggle('hidden', p.dataset.panel !== tab);
        }
    };

    const getTabFromHash = () => {
        const h = (window.location.hash || '').replace('#', '').trim();
        if (!h) return 'recap';
        return h;
    };

    for (const b of tabBtns) {
        b.addEventListener('click', () => {
            const tab = b.dataset.tab || 'recap';
            window.location.hash = tab;
            setActive(tab);
        });
    }
    for (const b of openTabBtns) {
        b.addEventListener('click', () => {
            const tab = b.dataset.tab || 'recap';
            window.location.hash = tab;
            setActive(tab);
        });
    }
    window.addEventListener('hashchange', () => setActive(getTabFromHash()));
    setActive(getTabFromHash());

    const modalIds = ['modal_add_action', 'modal_add_task', 'modal_add_participant', 'modal_add_doc', 'modal_add_link'];
    const closeModal = (id) => {
        const el = document.getElementById(id);
        if (!el) return;
        el.classList.add('hidden');
    };
    const openModal = (id) => {
        const el = document.getElementById(id);
        if (!el) return;
        el.classList.remove('hidden');
        const focusable = el.querySelector('input,select,textarea,button');
        if (focusable) focusable.focus();
    };

    document.addEventListener('click', (e) => {
        const t = e.target;
        if (!(t instanceof HTMLElement)) return;
        const open = t.getAttribute('data-modal-open');
        if (open) {
            openModal(open);
            return;
        }
        const close = t.getAttribute('data-modal-close');
        if (close) {
            closeModal(close);
            return;
        }
        if (t.classList.contains('project-modal-backdrop')) {
            const id = t.getAttribute('data-modal-id');
            if (id) closeModal(id);
        }
    });

    document.addEventListener('keydown', (e) => {
        if (e.key !== 'Escape') return;
        for (const id of modalIds) {
            const el = document.getElementById(id);
            if (el && !el.classList.contains('hidden')) {
                closeModal(id);
            }
        }
    });

    const input = document.getElementById('project_tier_input');
    const hiddenId = document.getElementById('project_tier_id');
    const box = document.getElementById('project_tier_suggestions');
    if (!input || !hiddenId || !box) return;

    let lastQuery = '';
    let abortCtrl = null;

    const close = () => {
        box.classList.add('hidden');
        box.innerHTML = '';
    };

    const setTier = (id, name) => {
        hiddenId.value = String(id || '');
        if (typeof name === 'string') {
            input.value = name;
        }
        close();
    };

    const render = (items) => {
        if (!Array.isArray(items) || items.length === 0) {
            close();
            return;
        }
        box.innerHTML = '';
        for (const it of items) {
            const id = Number(it.id || 0);
            const name = String(it.name || '').trim();
            if (!id || !name) continue;

            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'w-full text-left px-3 py-2 text-sm hover:bg-slate-50';
            btn.textContent = name;
            btn.addEventListener('click', () => setTier(id, name));
            box.appendChild(btn);
        }
        if (box.childNodes.length === 0) {
            close();
            return;
        }
        box.classList.remove('hidden');
    };

    input.addEventListener('input', async () => {
        const q = input.value.trim();
        hiddenId.value = '';
        if (q.length < 2) {
            close();
            return;
        }
        if (q === lastQuery) return;
        lastQuery = q;

        try {
            if (abortCtrl) abortCtrl.abort();
            abortCtrl = new AbortController();
            const res = await fetch('<?= e(tenant_path('/projects/tiers/search')) ?>?q=' + encodeURIComponent(q), { signal: abortCtrl.signal });
            if (!res.ok) {
                close();
                return;
            }
            const data = await res.json();
            render((data && data.items) ? data.items : []);
        } catch (e) {
            close();
        }
    });

    input.addEventListener('blur', () => {
        setTimeout(() => close(), 150);
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') close();
    });
})();
</script>
<?php endif; ?>

<script>
(() => {
    const list = document.getElementById('tasks_list');
    if (!list) return;

    const statusSel = document.getElementById('tasks_filter_status');
    const whenSel = document.getElementById('tasks_filter_when');
    const actionSel = document.getElementById('tasks_filter_action');
    const resetBtn = document.getElementById('tasks_filter_reset');

    const today = '<?= e($today) ?>';

    const applyFilters = () => {
        const status = statusSel ? statusSel.value : '';
        const when = whenSel ? whenSel.value : '';
        const actionId = actionSel ? actionSel.value : '';
        const q = (document.getElementById('tasks_search')?.value || '').trim().toLowerCase();

        const items = Array.from(list.querySelectorAll('.project-task'));
        for (const it of items) {
            const st = (it.dataset.status || '');
            const due = (it.dataset.due || '');
            const aid = (it.dataset.actionId || '0');

            let ok = true;
            if (status && st !== status) ok = false;
            if (ok && actionId && aid !== actionId) ok = false;

            if (ok && when) {
                if (when === 'nodate') {
                    if (due) ok = false;
                }
                if (when === 'overdue') {
                    if (!due || due >= today || st === 'done') ok = false;
                }
                if (when === 'upcoming') {
                    if (!due || due < today || st === 'done') ok = false;
                }
            }

            if (ok && q) {
                const s = (it.dataset.search || '').toLowerCase();
                if (!s.includes(q)) ok = false;
            }

            it.style.display = ok ? '' : 'none';
        }
    };

    if (statusSel) statusSel.addEventListener('change', applyFilters);
    if (whenSel) whenSel.addEventListener('change', applyFilters);
    if (actionSel) actionSel.addEventListener('change', applyFilters);
    if (resetBtn) resetBtn.addEventListener('click', () => {
        if (statusSel) statusSel.value = '';
        if (whenSel) whenSel.value = '';
        if (actionSel) actionSel.value = '';
        const ts = document.getElementById('tasks_search');
        if (ts) ts.value = '';
        applyFilters();
    });

    const ts = document.getElementById('tasks_search');
    if (ts) ts.addEventListener('input', applyFilters);

    applyFilters();
})();
</script>

<script>
(() => {
    const input = document.getElementById('participants_search');
    if (!input) return;
    const rows = Array.from(document.querySelectorAll('.project-participant'));
    const apply = () => {
        const q = input.value.trim().toLowerCase();
        for (const r of rows) {
            const s = (r.getAttribute('data-search') || '').toLowerCase();
            r.style.display = !q || s.includes(q) ? '' : 'none';
        }
    };
    input.addEventListener('input', apply);
})();
</script>

<?php if (App\Support\Access::can((int)$_SESSION['tenant_id'], (int)$_SESSION['user_id'], 'projects', 'write')): ?>
<div id="modal_add_action" class="hidden fixed inset-0 z-50">
    <div class="project-modal-backdrop absolute inset-0 bg-black/40" data-modal-id="modal_add_action"></div>
    <div class="relative mx-auto mt-24 max-w-lg bg-white rounded-lg border border-slate-200 p-4">
        <div class="flex items-center justify-between">
            <div class="text-sm font-medium">Ajouter une action</div>
            <button type="button" class="text-sm" data-modal-close="modal_add_action">Fermer</button>
        </div>
        <form method="post" action="<?= e(tenant_path('/projects/actions/add')) ?>" class="mt-4 space-y-3">
            <input type="hidden" name="_csrf" value="<?= e(App\Support\Csrf::token()) ?>">
            <input type="hidden" name="project_id" value="<?= e((string)$projectId) ?>">
            <div>
                <label class="block text-sm font-medium mb-1">Nom</label>
                <input name="name" class="w-full border border-slate-300 rounded px-3 py-2" required>
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Règle (optionnel)</label>
                <input name="schedule_rule" class="w-full border border-slate-300 rounded px-3 py-2" placeholder="Ex: weekly:tuesday">
            </div>
            <div class="flex justify-end gap-2">
                <button type="button" class="border border-slate-300 rounded px-3 py-2 text-sm" data-modal-close="modal_add_action">Annuler</button>
                <button class="bg-slate-900 text-white rounded px-3 py-2 text-sm" type="submit">Créer</button>
            </div>
        </form>
    </div>
</div>

<div id="modal_add_task" class="hidden fixed inset-0 z-50">
    <div class="project-modal-backdrop absolute inset-0 bg-black/40" data-modal-id="modal_add_task"></div>
    <div class="relative mx-auto mt-24 max-w-lg bg-white rounded-lg border border-slate-200 p-4">
        <div class="flex items-center justify-between">
            <div class="text-sm font-medium">Ajouter une tâche</div>
            <button type="button" class="text-sm" data-modal-close="modal_add_task">Fermer</button>
        </div>
        <form method="post" action="<?= e(tenant_path('/projects/tasks/add')) ?>" class="mt-4 space-y-3">
            <input type="hidden" name="_csrf" value="<?= e(App\Support\Csrf::token()) ?>">
            <input type="hidden" name="project_id" value="<?= e((string)$projectId) ?>">
            <div>
                <label class="block text-sm font-medium mb-1">Titre</label>
                <input name="title" class="w-full border border-slate-300 rounded px-3 py-2" required>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="block text-sm font-medium mb-1">Échéance</label>
                    <input name="due_on" type="date" class="w-full border border-slate-300 rounded px-3 py-2">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Action</label>
                    <select name="action_id" class="w-full border border-slate-300 rounded px-3 py-2">
                        <option value="">—</option>
                        <?php foreach ($actions as $a): ?>
                            <option value="<?= e((string)$a['id']) ?>"><?= e((string)$a['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="flex justify-end gap-2">
                <button type="button" class="border border-slate-300 rounded px-3 py-2 text-sm" data-modal-close="modal_add_task">Annuler</button>
                <button class="bg-slate-900 text-white rounded px-3 py-2 text-sm" type="submit">Créer</button>
            </div>
        </form>
    </div>
</div>

<div id="modal_add_participant" class="hidden fixed inset-0 z-50">
    <div class="project-modal-backdrop absolute inset-0 bg-black/40" data-modal-id="modal_add_participant"></div>
    <div class="relative mx-auto mt-24 max-w-2xl bg-white rounded-lg border border-slate-200 p-4">
        <div class="flex items-center justify-between">
            <div class="text-sm font-medium">Ajouter un participant</div>
            <button type="button" class="text-sm" data-modal-close="modal_add_participant">Fermer</button>
        </div>
        <form method="post" action="<?= e(tenant_path('/projects/participants/add')) ?>" class="mt-4 space-y-3">
            <input type="hidden" name="_csrf" value="<?= e(App\Support\Csrf::token()) ?>">
            <input type="hidden" name="project_id" value="<?= e((string)$projectId) ?>">
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div>
                    <label class="block text-sm font-medium mb-1">Tiers</label>
                    <div class="relative">
                        <input id="project_tier_input" class="w-full border border-slate-300 rounded px-3 py-2" placeholder="Rechercher un tiers..." autocomplete="off" required>
                        <input type="hidden" name="tier_id" id="project_tier_id" value="">
                        <div id="project_tier_suggestions" class="hidden absolute z-20 mt-1 w-full bg-white border border-slate-200 rounded shadow-sm overflow-hidden"></div>
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Rôle</label>
                    <input name="role" class="w-full border border-slate-300 rounded px-3 py-2" placeholder="Ex: Intervenant" required>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Action</label>
                    <select name="action_id" class="w-full border border-slate-300 rounded px-3 py-2">
                        <option value="">—</option>
                        <?php foreach ($actions as $a): ?>
                            <option value="<?= e((string)$a['id']) ?>"><?= e((string)$a['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="flex justify-end gap-2">
                <button type="button" class="border border-slate-300 rounded px-3 py-2 text-sm" data-modal-close="modal_add_participant">Annuler</button>
                <button class="bg-slate-900 text-white rounded px-3 py-2 text-sm" type="submit">Ajouter</button>
            </div>
        </form>
    </div>
</div>

<div id="modal_add_doc" class="hidden fixed inset-0 z-50">
    <div class="project-modal-backdrop absolute inset-0 bg-black/40" data-modal-id="modal_add_doc"></div>
    <div class="relative mx-auto mt-24 max-w-lg bg-white rounded-lg border border-slate-200 p-4">
        <div class="flex items-center justify-between">
            <div class="text-sm font-medium">Ajouter un document</div>
            <button type="button" class="text-sm" data-modal-close="modal_add_doc">Fermer</button>
        </div>
        <form method="post" action="<?= e(tenant_path('/projects/documents/add')) ?>" class="mt-4 space-y-3" enctype="multipart/form-data">
            <input type="hidden" name="_csrf" value="<?= e(App\Support\Csrf::token()) ?>">
            <input type="hidden" name="project_id" value="<?= e((string)$projectId) ?>">
            <div>
                <label class="block text-sm font-medium mb-1">Titre</label>
                <input name="title" class="w-full border border-slate-300 rounded px-3 py-2" required>
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Fichier (optionnel)</label>
                <input type="file" name="file" class="w-full border border-slate-300 rounded px-3 py-2" />
                <div class="mt-1 text-xs text-slate-500">Si Drive est actif, le fichier sera envoyé sur Drive, sinon stocké en local.</div>
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">URL (optionnel)</label>
                <input name="url" class="w-full border border-slate-300 rounded px-3 py-2" placeholder="https://...">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Notes (optionnel)</label>
                <textarea name="notes" class="w-full border border-slate-300 rounded px-3 py-2" rows="3"></textarea>
            </div>
            <div class="flex justify-end gap-2">
                <button type="button" class="border border-slate-300 rounded px-3 py-2 text-sm" data-modal-close="modal_add_doc">Annuler</button>
                <button class="bg-slate-900 text-white rounded px-3 py-2 text-sm" type="submit">Ajouter</button>
            </div>
        </form>
    </div>
</div>

<div id="modal_add_link" class="hidden fixed inset-0 z-50">
    <div class="project-modal-backdrop absolute inset-0 bg-black/40" data-modal-id="modal_add_link"></div>
    <div class="relative mx-auto mt-24 max-w-lg bg-white rounded-lg border border-slate-200 p-4">
        <div class="flex items-center justify-between">
            <div class="text-sm font-medium">Ajouter un lien utile</div>
            <button type="button" class="text-sm" data-modal-close="modal_add_link">Fermer</button>
        </div>
        <form method="post" action="<?= e(tenant_path('/projects/links/add')) ?>" class="mt-4 space-y-3">
            <input type="hidden" name="_csrf" value="<?= e(App\Support\Csrf::token()) ?>">
            <input type="hidden" name="project_id" value="<?= e((string)$projectId) ?>">
            <div>
                <label class="block text-sm font-medium mb-1">Label</label>
                <input name="label" class="w-full border border-slate-300 rounded px-3 py-2" required>
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">URL</label>
                <input name="url" class="w-full border border-slate-300 rounded px-3 py-2" placeholder="https://..." required>
            </div>
            <div class="flex justify-end gap-2">
                <button type="button" class="border border-slate-300 rounded px-3 py-2 text-sm" data-modal-close="modal_add_link">Annuler</button>
                <button class="bg-slate-900 text-white rounded px-3 py-2 text-sm" type="submit">Ajouter</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php
$content = ob_get_clean();
require base_path('views/layout.php');
