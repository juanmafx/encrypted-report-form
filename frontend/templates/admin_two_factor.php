<h1>Two-factor verification</h1>
<?php if ($view['error'] !== ''): ?><p class="error" role="alert"><?= $e($view['error']) ?></p><?php endif; ?>
<form method="post" action="/admin/two-factor">
<input type="hidden" name="csrf_token" value="<?= $e($view['csrf']) ?>">
<label for="code">Authenticator code</label>
<input id="code" name="code" type="text" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" required>
<button type="submit">Verify</button>
</form>
