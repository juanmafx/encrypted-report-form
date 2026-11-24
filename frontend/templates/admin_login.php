<h1>Admin sign in</h1>
<?php if ($view['error'] !== ''): ?><p class="error" role="alert"><?= $e($view['error']) ?></p><?php endif; ?>
<form method="post" action="/admin/login">
<input type="hidden" name="csrf_token" value="<?= $e($view['csrf']) ?>">
<label for="username">Username</label>
<input id="username" name="username" type="text" autocomplete="username" required>
<label for="password">Password</label>
<input id="password" name="password" type="password" autocomplete="current-password" required>
<button type="submit">Continue</button>
</form>
