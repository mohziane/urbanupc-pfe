<?php
// Champs réutilisables pour le formulaire service (create + edit)
$sf = $editService ?? [];
$cats = ['informatique' => 'Informatique', 'rh' => 'Ressources Humaines', 'finance' => 'Finance',
         'juridique' => 'Juridique', 'logistique' => 'Logistique', 'autre' => 'Autre'];
?>
<div class="row g-3">
    <div class="col-md-6">
        <label class="form-label small fw-semibold text-muted">NOM DU SERVICE <span class="text-danger">*</span></label>
        <input type="text" name="name" class="form-control"
               value="<?= htmlspecialchars($sf['name'] ?? '') ?>" required>
    </div>
    <div class="col-md-6">
        <label class="form-label small fw-semibold text-muted">CATÉGORIE</label>
        <select name="category" class="form-select">
            <?php foreach ($cats as $k => $v): ?>
            <option value="<?= $k ?>" <?= (($sf['category'] ?? '') === $k) ? 'selected' : '' ?>><?= $v ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-12">
        <label class="form-label small fw-semibold text-muted">DESCRIPTION</label>
        <textarea name="description" class="form-control" rows="2"><?= htmlspecialchars($sf['description'] ?? '') ?></textarea>
    </div>
    <div class="col-md-4">
        <label class="form-label small fw-semibold text-muted">ICÔNE FONT AWESOME</label>
        <div class="input-group">
            <span class="input-group-text"><i class="fas <?= htmlspecialchars($sf['icon'] ?? 'fa-cogs') ?>" id="iconPreview"></i></span>
            <input type="text" name="icon" id="iconInput" class="form-control"
                   value="<?= htmlspecialchars($sf['icon'] ?? 'fa-cogs') ?>"
                   placeholder="fa-cogs">
        </div>
        <div class="form-text">ex: fa-headset, fa-laptop, fa-users</div>
    </div>
    <div class="col-md-4">
        <label class="form-label small fw-semibold text-muted">NOM DU CONTACT</label>
        <input type="text" name="contact_name" class="form-control"
               value="<?= htmlspecialchars($sf['contact_name'] ?? '') ?>">
    </div>
    <div class="col-md-4">
        <label class="form-label small fw-semibold text-muted">EMAIL DU CONTACT</label>
        <input type="email" name="contact_email" class="form-control"
               value="<?= htmlspecialchars($sf['contact_email'] ?? '') ?>">
    </div>
    <div class="col-md-4">
        <label class="form-label small fw-semibold text-muted">STATUT</label>
        <select name="status" class="form-select">
            <option value="active" <?= (($sf['status'] ?? 'active') === 'active') ? 'selected' : '' ?>>Actif</option>
            <option value="maintenance" <?= (($sf['status'] ?? '') === 'maintenance') ? 'selected' : '' ?>>Maintenance</option>
            <option value="offline" <?= (($sf['status'] ?? '') === 'offline') ? 'selected' : '' ?>>Hors ligne</option>
        </select>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const input = document.getElementById('iconInput');
    const preview = document.getElementById('iconPreview');
    if (input && preview) {
        input.addEventListener('input', () => {
            preview.className = 'fas ' + input.value;
        });
    }
});
</script>
