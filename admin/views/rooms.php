<?php if (!defined('ABSPATH')) exit;
$palette = array('var(--sa-navy)','var(--sa-green)','var(--sa-red)','var(--sa-green-lt)','var(--sa-forest)','var(--sa-dark)','var(--sa-yellow)','var(--sa-green-pale)');
$ss_page_title    = 'Kabinetai';
$ss_page_subtitle = 'Kabinetų sąrašas — naudojamas sudarant tvarkaraščius ir kabinetų tvarkaraščius.';
$ss_page_actions  = '<button class="button button-primary ss-open-modal" data-modal="room">+ Pridėti kabinetą</button>';
?>
<div class="wrap ss-wrap">
<?php include SS_PLUGIN_DIR . 'admin/views/partials/header.php'; ?>
<div id="ss-notice" class="notice" style="display:none"><p></p></div>

<!-- Bulk toolbar -->
<div class="ss-bulk-bar">
  <div class="ss-bulk-left">
    <label class="ss-cb-all" title="Pažymėti visus">
      <input type="checkbox" id="ss-rooms-select-all">
    </label>
    <input type="text" class="ss-search-inp" id="ss-rooms-search" placeholder="Ieškoti kabineto...">
    <span class="ss-sel-badge" id="ss-rooms-badge" style="display:none">
      <span id="ss-rooms-sel-n">0</span> pasirinkta
    </span>
  </div>
  <div class="ss-bulk-right" id="ss-rooms-bulk-right" style="display:none">
    <select class="ss-bulk-select" id="ss-rooms-action">
      <option value="">— Pasirinkite veiksmą —</option>
      <option value="delete">Ištrinti pasirinktus</option>
    </select>
    <button class="button ss-bulk-apply" data-entity="rooms">Vykdyti</button>
  </div>
</div>

<table class="wp-list-table widefat fixed striped ss-table" id="ss-rooms-table">
<thead><tr>
  <th class="ss-cb-col"><input type="checkbox" id="ss-rooms-th-all" title="Pažymėti visus"></th>
  <th style="width:45%">Kabinetas</th>
  <th style="width:15%">Naudojimas</th>
  <th style="width:15%">Spalva</th>
  <th>Veiksmai</th>
</tr></thead>
<tbody>
<?php if (empty($rooms)): ?>
<tr><td colspan="5" style="text-align:center;padding:32px;color:var(--sa-green-pale)">
  Dar nėra kabinetų. <a href="#" class="ss-open-modal" data-modal="room">Pridėkite pirmąjį!</a>
</td></tr>
<?php else: foreach ($rooms as $r):
  $usage = (int)($room_usage[$r->id] ?? 0); ?>
<tr id="ss-room-row-<?= (int)$r->id ?>" data-search="<?= esc_attr(mb_strtolower($r->name)) ?>">
  <td class="ss-cb-col"><input type="checkbox" class="ss-row-cb" value="<?= (int)$r->id ?>"></td>
  <td><strong><?= esc_html($r->name) ?></strong></td>
  <td>
    <?php if ($usage > 0): ?>
      <span style="display:inline-block;background:#E5F4E8;color:var(--sa-forest);font-size:12px;font-weight:600;padding:2px 10px;"><?= $usage ?> pamokų</span>
    <?php else: ?>
      <span style="color:var(--sa-green-pale);font-size:12px">nenaudojamas</span>
    <?php endif; ?>
  </td>
  <td><span class="ss-color-badge" style="background:<?= esc_attr($r->color) ?>"><?= esc_html($r->color) ?></span></td>
  <td>
    <button class="button button-small ss-edit-room"
      data-id="<?= esc_attr($r->id) ?>" data-name="<?= esc_attr($r->name) ?>"
      data-color="<?= esc_attr($r->color) ?>">Redaguoti</button>
    <button class="button button-small ss-btn-danger ss-delete-room"
      data-id="<?= esc_attr($r->id) ?>" data-name="<?= esc_attr($r->name) ?>">Ištrinti</button>
  </td>
</tr>
<?php endforeach; endif; ?>
</tbody>
</table>

<!-- Room Modal -->
<div id="ss-modal-room" class="ss-modal-overlay" style="display:none">
<div class="ss-modal">
<div class="ss-modal-head">
  <h2 id="ss-modal-room-title">Pridėti kabinetą</h2>
  <button class="ss-modal-x ss-close-modal"><span class="dashicons dashicons-no-alt"></span></button>
</div>
<div class="ss-modal-body">
  <input type="hidden" id="ss-room-id">
  <div class="ss-field"><label>Kabineto pavadinimas *</label><input type="text" id="ss-room-name" class="regular-text" placeholder="pvz. 301, Sporto salė"></div>
  <div class="ss-field"><label>Spalva</label>
    <div class="ss-cpicker">
      <div class="ss-swatches"><?php foreach ($palette as $c): ?>
        <button type="button" class="ss-swatch" data-color="<?= esc_attr($c) ?>" style="background:<?= esc_attr($c) ?>"></button>
      <?php endforeach; ?></div>
      <div class="ss-cpicker-inputs">
        <input type="color" class="ss-native-color" value="#224768">
        <input type="text" class="ss-hex-input" value="#224768" placeholder="#224768" maxlength="7">
      </div>
      <input type="hidden" class="ss-color-val" value="#224768">
    </div>
  </div>
</div>
<div class="ss-modal-foot">
  <button class="button ss-close-modal" id="ss-cancel-room">Atšaukti</button>
  <button class="button button-primary" id="ss-save-room">Išsaugoti</button>
</div>
</div>
</div>
</div>