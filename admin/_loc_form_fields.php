<?php
/**
 * _loc_form_fields.php — Shared form fields for Add/Edit location modals.
 * Included by manage_locations.php. Not a standalone page.
 */
$categories_list = ['Nature', 'Adventure', 'Culture', 'Agri-Tourism', 'Accommodation', 'Heritage'];
?>
<div class="row g-3">
    <div class="col-12">
        <label class="form-label fw-semibold">Title *</label>
        <input type="text" name="title" class="form-control"
               maxlength="300" required placeholder="e.g. Lake Sebu">
    </div>
    <div class="col-12">
        <label class="form-label fw-semibold">Description *</label>
        <textarea name="description" class="form-control" rows="4"
                  maxlength="5000" required
                  placeholder="Detailed description of the destination…"></textarea>
    </div>
    <div class="col-md-6">
        <label class="form-label fw-semibold">Entry Cost *</label>
        <input type="text" name="cost" class="form-control"
               maxlength="100" required placeholder="e.g. Free entry or ₱200–₱500">
    </div>
    <div class="col-md-6">
        <label class="form-label fw-semibold">Category *</label>
        <select name="category" class="form-select" required>
            <option value="">-- Select --</option>
            <?php foreach ($categories_list as $cat): ?>
            <option value="<?= e($cat) ?>"><?= e($cat) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-12">
        <label class="form-label fw-semibold">
            Image URL <span class="text-muted fw-normal">(optional, https://…)</span>
        </label>
        <input type="url" name="image_url" class="form-control"
               maxlength="500" placeholder="https://example.com/image.jpg">
    </div>
    <div class="col-md-6">
        <label class="form-label fw-semibold">
            Latitude <span class="text-muted fw-normal">(optional)</span>
        </label>
        <input type="number" name="latitude" class="form-control"
               step="0.000001" min="-90" max="90" placeholder="e.g. 6.2933">
    </div>
    <div class="col-md-6">
        <label class="form-label fw-semibold">
            Longitude <span class="text-muted fw-normal">(optional)</span>
        </label>
        <input type="number" name="longitude" class="form-control"
               step="0.000001" min="-180" max="180" placeholder="e.g. 124.7769">
    </div>
</div>
