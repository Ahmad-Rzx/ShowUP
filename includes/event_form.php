<?php
/**
 * Shared create/edit event form.
 *
 * The page must provide:
 *   $formAction   string  URL the form posts to
 *   $submitLabel  string  button text
 *   $values       array   current field values
 *   $errors       array   field name => message
 *   $categories   array   rows from the categories table
 *   $cancelUrl    string  optional link shown next to the submit button
 */
$cancelUrl = $cancelUrl ?? null;
$mode = $values['event_mode'] === 'online' ? 'online' : 'physical';
?>
<form id="event-form" class="form-card" method="post" action="<?= e($formAction) ?>" novalidate>
  <?= csrf_field() ?>

  <?php if (isset($errors['form'])): ?>
    <div class="auth-alert" role="alert"><?= e($errors['form']) ?></div>
  <?php endif; ?>

  <div class="form-grid">
    <div class="field field-full">
      <label for="title">Event title</label>
      <input type="text" id="title" name="title" value="<?= e($values['title']) ?>"
             maxlength="150" autocomplete="off"
             <?= isset($errors['title']) ? 'aria-invalid="true"' : '' ?>
             aria-describedby="title-error">
      <p class="field-error" id="title-error"><?= e($errors['title'] ?? '') ?></p>
    </div>

    <div class="field">
      <label for="category_id">Category</label>
      <select id="category_id" name="category_id"
              <?= isset($errors['category_id']) ? 'aria-invalid="true"' : '' ?>
              aria-describedby="category_id-error">
        <option value="0">Choose a category</option>
        <?php foreach ($categories as $category): ?>
          <option value="<?= (int) $category['category_id'] ?>"
            <?= (int) $values['category_id'] === (int) $category['category_id'] ? 'selected' : '' ?>>
            <?= e($category['name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
      <p class="field-error" id="category_id-error"><?= e($errors['category_id'] ?? '') ?></p>
    </div>

    <div class="field">
      <label for="capacity">Capacity <span class="optional">(optional)</span></label>
      <input type="number" id="capacity" name="capacity" value="<?= e($values['capacity']) ?>"
             min="1" max="100000" step="1" inputmode="numeric"
             <?= isset($errors['capacity']) ? 'aria-invalid="true"' : '' ?>
             aria-describedby="capacity-hint capacity-error">
      <p class="field-hint" id="capacity-hint">Leave empty for unlimited seats.</p>
      <p class="field-error" id="capacity-error"><?= e($errors['capacity'] ?? '') ?></p>
    </div>

    <fieldset class="fieldset field-full">
      <legend>How will people attend?</legend>
      <div class="mode-options">
        <label class="mode-option">
          <input type="radio" name="event_mode" value="physical" <?= $mode === 'physical' ? 'checked' : '' ?>>
          <span>
            <strong>In person</strong>
            <span class="field-hint">A venue and a street address.</span>
          </span>
        </label>
        <label class="mode-option">
          <input type="radio" name="event_mode" value="online" <?= $mode === 'online' ? 'checked' : '' ?>>
          <span>
            <strong>Online</strong>
            <span class="field-hint">A meeting link, shared with confirmed attendees.</span>
          </span>
        </label>
      </div>
      <p class="field-error" id="event_mode-error"><?= e($errors['event_mode'] ?? '') ?></p>
    </fieldset>

    <div id="physical-fields" class="form-grid field-full" <?= $mode === 'physical' ? '' : 'hidden' ?>>
      <div class="field">
        <label for="venue_name">Venue name</label>
        <input type="text" id="venue_name" name="venue_name" value="<?= e($values['venue_name']) ?>"
               maxlength="150" autocomplete="organization"
               <?= isset($errors['venue_name']) ? 'aria-invalid="true"' : '' ?>
               aria-describedby="venue_name-error">
        <p class="field-error" id="venue_name-error"><?= e($errors['venue_name'] ?? '') ?></p>
      </div>

      <div class="field">
        <label for="city">City <span class="optional">(optional)</span></label>
        <input type="text" id="city" name="city" value="<?= e($values['city']) ?>"
               maxlength="80" autocomplete="address-level2"
               <?= isset($errors['city']) ? 'aria-invalid="true"' : '' ?>
               aria-describedby="city-error">
        <p class="field-error" id="city-error"><?= e($errors['city'] ?? '') ?></p>
      </div>

      <div class="field field-full">
        <label for="address">Street address</label>
        <input type="text" id="address" name="address" value="<?= e($values['address']) ?>"
               maxlength="255" autocomplete="street-address"
               <?= isset($errors['address']) ? 'aria-invalid="true"' : '' ?>
               aria-describedby="address-error">
        <p class="field-error" id="address-error"><?= e($errors['address'] ?? '') ?></p>
      </div>
    </div>

    <div id="online-fields" class="field field-full" <?= $mode === 'online' ? '' : 'hidden' ?>>
      <label for="online_link">Meeting link</label>
      <input type="url" id="online_link" name="online_link" value="<?= e($values['online_link']) ?>"
             maxlength="500" placeholder="https://"
             <?= isset($errors['online_link']) ? 'aria-invalid="true"' : '' ?>
             aria-describedby="online_link-hint online_link-error">
      <p class="field-hint" id="online_link-hint">Only the host and confirmed attendees can see this link.</p>
      <p class="field-error" id="online_link-error"><?= e($errors['online_link'] ?? '') ?></p>
    </div>

    <div class="field">
      <label for="start_datetime">Starts</label>
      <input type="datetime-local" id="start_datetime" name="start_datetime"
             value="<?= e($values['start_datetime']) ?>"
             <?= isset($errors['start_datetime']) ? 'aria-invalid="true"' : '' ?>
             aria-describedby="start_datetime-error">
      <p class="field-error" id="start_datetime-error"><?= e($errors['start_datetime'] ?? '') ?></p>
    </div>

    <div class="field">
      <label for="end_datetime">Ends</label>
      <input type="datetime-local" id="end_datetime" name="end_datetime"
             value="<?= e($values['end_datetime']) ?>"
             <?= isset($errors['end_datetime']) ? 'aria-invalid="true"' : '' ?>
             aria-describedby="end_datetime-error">
      <p class="field-error" id="end_datetime-error"><?= e($errors['end_datetime'] ?? '') ?></p>
    </div>

    <div class="field field-full">
      <label for="description">Description</label>
      <textarea id="description" name="description" rows="8" maxlength="5000"
                <?= isset($errors['description']) ? 'aria-invalid="true"' : '' ?>
                aria-describedby="description-hint description-error"><?= e($values['description']) ?></textarea>
      <p class="field-hint" id="description-hint">What will happen, who it is for and what to bring.</p>
      <p class="field-error" id="description-error"><?= e($errors['description'] ?? '') ?></p>
    </div>
  </div>

  <div class="form-actions">
    <button type="submit" class="primary-btn"><?= e($submitLabel) ?></button>
    <?php if ($cancelUrl !== null): ?>
      <a class="secondary-btn" href="<?= e($cancelUrl) ?>">Cancel</a>
    <?php endif; ?>
  </div>
</form>
