<?php
$fields = [
    ['first_name', 'First name', 'text', 'given-name', true],
    ['last_name', 'Last name', 'text', 'family-name', true],
    ['email', 'Email address', 'email', 'email', true],
    ['occurred_when', 'When did it happen?', 'text', 'off', true],
    ['duration', 'How long were you gone?', 'text', 'off', false],
    ['alien_count', 'How many did you see?', 'text', 'off', false],
];
$areas = [
    ['alien_description', 'Describe them'],
    ['alien_actions', 'What did they do to you?'],
    ['comments', 'Anything else you want to add?'],
];
$values = $view['values'];
$errors = $view['errors'];
$spotted = $values['fang_spotted'] ?? '';
?>
<h1>Aliens Abducted Me</h1>
<p>Share your story of alien abduction.</p>
<form method="post" action="/reports">
<input type="hidden" name="csrf_token" value="<?= $e($view['csrf']) ?>">
<?php foreach ($fields as [$name, $label, $type, $autocomplete, $required]): ?>
<label for="<?= $e($name) ?>"><?= $e($label) ?></label>
<input id="<?= $e($name) ?>" name="<?= $e($name) ?>" type="<?= $e($type) ?>" autocomplete="<?= $e($autocomplete) ?>" value="<?= $e($values[$name] ?? '') ?>"<?= $required ? ' required' : '' ?>>
<?php if (isset($errors[$name])): ?><p class="error"><?= $e($errors[$name]) ?></p><?php endif; ?>
<?php endforeach; ?>
<?php foreach ($areas as [$name, $label]): ?>
<label for="<?= $e($name) ?>"><?= $e($label) ?></label>
<textarea id="<?= $e($name) ?>" name="<?= $e($name) ?>" rows="3"><?= $e($values[$name] ?? '') ?></textarea>
<?php if (isset($errors[$name])): ?><p class="error"><?= $e($errors[$name]) ?></p><?php endif; ?>
<?php endforeach; ?>
<fieldset>
<legend>Have you seen my dog Fang?</legend>
<label><input type="radio" name="fang_spotted" value="yes"<?= $spotted === 'yes' ? ' checked' : '' ?> required> Yes</label>
<label><input type="radio" name="fang_spotted" value="no"<?= $spotted === 'no' ? ' checked' : '' ?>> No</label>
<?php if (isset($errors['fang_spotted'])): ?><p class="error"><?= $e($errors['fang_spotted']) ?></p><?php endif; ?>
</fieldset>
<button type="submit">Report abduction</button>
</form>
