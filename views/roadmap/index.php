<?php
$title = 'Roadmap';
ob_start();
?>
<h1 class="text-2xl font-semibold">Roadmap</h1>
<p class="mt-2 text-slate-600">Évolutions prévues (consultable via navigateur).</p>

<div class="mt-4 bg-white border border-slate-200 rounded-lg p-4">
    <pre class="whitespace-pre-wrap text-sm leading-6"><?= e($content) ?></pre>
</div>
<?php
$content = ob_get_clean();
require base_path('views/layout.php');
