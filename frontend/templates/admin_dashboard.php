<h1>Submitted reports</h1>
<form method="post" action="/admin/logout">
<input type="hidden" name="csrf_token" value="<?= $e($view['csrf']) ?>">
<button type="submit">Sign out</button>
</form>
<?php if ($view['reports'] === []): ?>
<p>No reports yet.</p>
<?php else: ?>
<table>
<thead><tr><th>Received (UTC)</th><th>Name</th><th>Fang spotted</th><th></th></tr></thead>
<tbody>
<?php foreach ($view['reports'] as $report): ?>
<tr>
<td><?= $e(gmdate('Y-m-d H:i', $report['created_at'])) ?></td>
<td><?= $e($report['first_name'] . ' ' . $report['last_name']) ?></td>
<td><?= $e($report['fang_spotted']) ?></td>
<td><a href="/admin/reports/<?= $e($report['public_id']) ?>">Open</a></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
<?php endif; ?>
