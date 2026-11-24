<?php
$report = $view['report'];
$labels = [
    'first_name' => 'First name',
    'last_name' => 'Last name',
    'email' => 'Email address',
    'occurred_when' => 'When it happened',
    'duration' => 'Time gone',
    'alien_count' => 'Number of aliens',
    'alien_description' => 'Alien description',
    'alien_actions' => 'What they did',
    'fang_spotted' => 'Fang spotted',
    'comments' => 'Other comments',
];
?>
<h1>Report details</h1>
<p><a href="/admin">Back to all reports</a></p>
<dl>
<?php foreach ($labels as $field => $label): ?>
<dt><?= $e($label) ?></dt>
<dd><?= $e($report[$field]) ?></dd>
<?php endforeach; ?>
</dl>
<form method="post" action="/admin/reports/<?= $e($report['public_id']) ?>/delete">
<input type="hidden" name="csrf_token" value="<?= $e($view['csrf']) ?>">
<button class="danger" type="submit">Delete report</button>
</form>
