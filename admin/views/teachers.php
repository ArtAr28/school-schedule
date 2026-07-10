<?php if (!defined('ABSPATH')) exit;
$palette = array('var(--sa-green)','var(--sa-navy)','var(--sa-red)','var(--sa-green-lt)','var(--sa-forest)','var(--sa-dark)','var(--sa-yellow)','var(--sa-green-pale)');
$ss_page_title    = 'Mokytojai';
$ss_page_subtitle = 'Mokytojų sąrašas — naudojamas sudarant pamokas ir tvarkaraščius.';
$ss_page_actions  = '<button class="button button-primary ss-open-modal" data-modal="teacher">+ Pridėti mokytoją</button>';
?>
<div class="wrap ss-wrap">
<?php include SS_PLUGIN_DIR . 'admin/views/partials/header.php'; ?>
<div id="ss-notice" class="notice" style="display:none"><p></p></div>

<!-- Bulk toolbar -->
<div class="ss-bulk-bar">
  <div class="ss-bulk-left">
    <label class="ss-cb-all" title="Pažymėti visus">
      <input type="checkbox" id="ss-teachers-select-all">
    </label>
    <input type="text" class="ss-search-inp" id="ss-teachers-search" placeholder="Ieškoti mokytojo...">
    <span class="ss-sel-badge" id="ss-teachers-badge" style="display:none">
      <span id="ss-teachers-sel-n">0</span> pasirinkta
    </span>
  </div>
  <div class="ss-bulk-right" id="ss-teachers-bulk-right" style="display:none">
    <select class="ss-bulk-select" id="ss-teachers-action">
      <option value="">— Pasirinkite veiksmą —</option>
      <option value="delete">Ištrinti pasirinktus</option>
    </select>
    <button class="button ss-bulk-apply" data-entity="teachers">Vykdyti</button>
  </div>
</div>

<table class="wp-list-table widefat fixed striped ss-table" id="ss-teachers-table">
<thead><tr>
  <th class="ss-cb-col"><input type="checkbox" id="ss-teachers-th-all" title="Pažymėti visus"></th>
  <th style="width:33%">Vardas Pavardė</th>
  <th style="width:11%">Santrumpa</th>
  <th style="width:24%">El. paštas</th>
  <th style="width:9%">Spalva</th>
  <th>Veiksmai</th>
</tr></thead>
<tbody>
<?php if (empty($teachers)): ?>
<tr><td colspan="6" style="text-align:center;padding:32px;color:var(--sa-green-pale)">
  Dar nėra mokytojų. <a href="#" class="ss-open-modal" data-modal="teacher">Pridėkite pirmąjį!</a>
</td></tr>
<?php else: foreach ($teachers as $t): ?>
<tr id="ss-teacher-row-<?= (int)$t->id ?>" data-search="<?= esc_attr(mb_strtolower($t->name . ' ' . $t->short . ' ' . $t->email)) ?>">
  <td class="ss-cb-col"><input type="checkbox" class="ss-row-cb" value="<?= (int)$t->id ?>"></td>
  <td><strong><?= esc_html($t->name) ?></strong></td>
  <td><code><?= esc_html($t->short) ?></code></td>
  <td style="font-size:13px"><?= esc_html($t->email) ?></td>
  <td><span class="ss-color-badge" style="background:<?= esc_attr($t->color) ?>"><?= esc_html($t->color) ?></span></td>
  <td>
    <button class="button button-small ss-edit-teacher"
      data-id="<?= esc_attr($t->id) ?>" data-name="<?= esc_attr($t->name) ?>"
      data-short="<?= esc_attr($t->short) ?>" data-email="<?= esc_attr($t->email) ?>"
      data-color="<?= esc_attr($t->color) ?>">Redaguoti</button>
    <button class="button button-small ss-btn-danger ss-delete-teacher"
      data-id="<?= esc_attr($t->id) ?>" data-name="<?= esc_attr($t->name) ?>">Ištrinti</button>
  </td>
</tr>
<?php endforeach; endif; ?>
</tbody>
</table>

<!-- Teacher Modal -->
<div id="ss-modal-teacher" class="ss-modal-overlay" style="display:none">
<div class="ss-modal">
<div class="ss-modal-head">
  <h2 id="ss-modal-teacher-title">Pridėti mokytoją</h2>
  <button class="ss-modal-x ss-close-modal"><span class="dashicons dashicons-no-alt"></span></button>
</div>
<div class="ss-modal-body">
  <input type="hidden" id="ss-teacher-id">
  <div class="ss-field"><label>Vardas Pavardė *</label><input type="text" id="ss-teacher-name" class="regular-text" placeholder="Jonas Jonaitis"></div>
  <div class="ss-field"><label>Santrumpa</label><input type="text" id="ss-teacher-short" class="small-text" placeholder="J.J." maxlength="10"></div>
  <div class="ss-field"><label>El. paštas</label><input type="email" id="ss-teacher-email" class="regular-text"></div>
  <div class="ss-field"><label>Spalva</label>
    <div class="ss-cpicker">
      <div class="ss-swatches"><?php foreach ($palette as $c): ?>
        <button type="button" class="ss-swatch" data-color="<?= esc_attr($c) ?>" style="background:<?= esc_attr($c) ?>"></button>
      <?php endforeach; ?></div>
      <div class="ss-cpicker-inputs">
        <input type="color" class="ss-native-color" value="#4F8952">
        <input type="text" class="ss-hex-input" value="#4F8952" placeholder="#4F8952" maxlength="7">
      </div>
      <input type="hidden" class="ss-color-val" value="#4F8952">
    </div>
  </div>
</div>
<div class="ss-modal-foot">
  <button class="button ss-close-modal" id="ss-cancel-teacher">Atšaukti</button>
  <button class="button button-primary" id="ss-save-teacher">Išsaugoti</button>
</div>
</div>
</div>
</div>
