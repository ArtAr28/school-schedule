<?php if (!defined('ABSPATH')) exit;
$palette = array('var(--sa-green)','var(--sa-navy)','var(--sa-red)','var(--sa-green-lt)','var(--sa-forest)','var(--sa-dark)','var(--sa-yellow)','var(--sa-green-pale)');
$ss_page_title    = 'Pamokos';
$ss_page_subtitle = 'Dalyko–mokytojo kombinacijos. Naudojamos sudarant klasių tvarkaraščius.';
$ss_page_actions  = '<button class="button button-primary ss-open-modal" data-modal="lesson">+ Pridėti pamoką</button>';
?>
<div class="wrap ss-wrap">
<?php include SS_PLUGIN_DIR . 'admin/views/partials/header.php'; ?>
<?php if (empty($teachers)): ?>
<div class="notice notice-warning"><p>Pirma sukurkite <a href="<?= admin_url('admin.php?page=ss-teachers') ?>">mokytojus</a>.</p></div>
<?php endif; ?>
<div id="ss-notice" class="notice" style="display:none"><p></p></div>

<!-- Bulk toolbar -->
<div class="ss-bulk-bar">
  <div class="ss-bulk-left">
    <label class="ss-cb-all" title="Pažymėti visus">
      <input type="checkbox" id="ss-lessons-select-all">
    </label>
    <input type="text" class="ss-search-inp" id="ss-lessons-search" placeholder="Ieškoti pamokos...">
    <span class="ss-sel-badge" id="ss-lessons-badge" style="display:none">
      <span id="ss-lessons-sel-n">0</span> pasirinkta
    </span>
  </div>
  <div class="ss-bulk-right" id="ss-lessons-bulk-right" style="display:none">
    <select class="ss-bulk-select" id="ss-lessons-action">
      <option value="">— Pasirinkite veiksmą —</option>
      <option value="delete">Ištrinti pasirinktus</option>
    </select>
    <button class="button ss-bulk-apply" data-entity="lessons">Vykdyti</button>
  </div>
</div>

<table class="wp-list-table widefat fixed striped ss-table" id="ss-lessons-table">
<thead><tr>
  <th class="ss-cb-col"><input type="checkbox" id="ss-lessons-th-all" title="Pažymėti visus"></th>
  <th style="width:38%">Dalykas</th>
  <th style="width:28%">Mokytojas</th>
  <th style="width:11%">Spalva</th>
  <th>Veiksmai</th>
</tr></thead>
<tbody>
<?php if (empty($lessons)): ?>
<tr><td colspan="5" style="text-align:center;padding:32px;color:var(--sa-green-pale)">Dar nėra pamokų.</td></tr>
<?php else: foreach ($lessons as $l): ?>
<tr id="ss-lesson-row-<?= $l->id ?>" data-search="<?= esc_attr(mb_strtolower($l->subject . ' ' . ($l->teacher_name ?? '') . ' ' . ($l->teacher_short ?? ''))) ?>">
  <td class="ss-cb-col"><input type="checkbox" class="ss-row-cb" value="<?= $l->id ?>"></td>
  <td><strong><?= esc_html($l->subject) ?></strong></td>
  <td><?= esc_html($l->teacher_name ?? '—') ?><?= $l->teacher_short ? ' <code>('.$l->teacher_short.')</code>' : '' ?></td>
  <td><span class="ss-color-badge" style="background:<?= esc_attr($l->color) ?>"><?= esc_html($l->color) ?></span></td>
  <td>
    <button class="button button-small ss-edit-lesson"
      data-id="<?= $l->id ?>" data-subject="<?= esc_attr($l->subject) ?>"
      data-teacher="<?= esc_attr($l->teacher_id ?? '') ?>" data-color="<?= esc_attr($l->color) ?>">Redaguoti</button>
    <button class="button button-small ss-btn-danger ss-delete-lesson"
      data-id="<?= $l->id ?>" data-name="<?= esc_attr($l->subject) ?>">Ištrinti</button>
  </td>
</tr>
<?php endforeach; endif; ?>
</tbody>
</table>

<div id="ss-modal-lesson" class="ss-modal-overlay" style="display:none">
<div class="ss-modal">
<div class="ss-modal-head">
  <h2 id="ss-modal-lesson-title">Pridėti pamoką</h2>
  <button class="ss-modal-x ss-close-modal"><span class="dashicons dashicons-no-alt"></span></button>
</div>
<div class="ss-modal-body">
  <input type="hidden" id="ss-lesson-id">
  <div class="ss-field"><label>Dalyko pavadinimas *</label><input type="text" id="ss-lesson-subject" class="regular-text" placeholder="Matematika, Lietuvių k., ..."></div>
  <div class="ss-field"><label>Mokytojas</label>
    <select id="ss-lesson-teacher" class="regular-text">
      <option value="0">— Nepriskirtas —</option>
      <?php foreach ($teachers as $t): ?>
      <option value="<?= $t->id ?>"><?= esc_html($t->name) ?><?= $t->short ? ' ('.$t->short.')' : '' ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="ss-field"><label>Spalva</label>
    <div class="ss-cpicker">
      <div class="ss-swatches"><?php foreach ($palette as $c): ?>
        <button type="button" class="ss-swatch" data-color="<?= $c ?>" style="background:<?= $c ?>"></button>
      <?php endforeach; ?></div>
      <div class="ss-cpicker-inputs">
        <input type="color" class="ss-native-color" value="#6FA672">
        <input type="text" class="ss-hex-input" value="#6FA672" placeholder="#6FA672" maxlength="7">
      </div>
      <input type="hidden" class="ss-color-val" value="#6FA672">
    </div>
  </div>
</div>
<div class="ss-modal-foot">
  <button class="button ss-close-modal">Atšaukti</button>
  <button class="button button-primary" id="ss-save-lesson">Išsaugoti</button>
</div>
</div>
</div>
</div>
