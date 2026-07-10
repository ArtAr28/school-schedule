<?php if (!defined('ABSPATH')) exit;
$days    = array('Pirmadienis','Antradienis','Trečiadienis','Ketvirtadienis','Penktadienis');
$palette = array('var(--sa-green)','var(--sa-navy)','var(--sa-red)','var(--sa-green-lt)','var(--sa-forest)','var(--sa-dark)','var(--sa-yellow)','var(--sa-green-pale)');
$ss_page_title    = 'Tvarkaraščiai';
$ss_page_subtitle = 'Klasių sąrašas ir jų savaitiniai tvarkaraščiai.';
$ss_page_actions  = '<button class="button button-primary ss-open-modal" data-modal="class">+ Pridėti tvarkaraštį</button>';
?>
<div class="wrap ss-wrap">
<?php include SS_PLUGIN_DIR . 'admin/views/partials/header.php'; ?>

<?php if (empty($periods)): ?>
<div class="notice notice-warning"><p><span class="dashicons dashicons-warning"></span> Pirma nustatykite <a href="<?= admin_url('admin.php?page=ss-settings') ?>">pamokų laiką</a> nustatymuose!</p></div>
<?php endif; ?>
<?php if (empty($lessons)): ?>
<div class="notice notice-warning"><p><span class="dashicons dashicons-warning"></span> Pirma sukurkite <a href="<?= admin_url('admin.php?page=ss-lessons') ?>">pamokas</a>!</p></div>
<?php endif; ?>
<div id="ss-notice" class="notice" style="display:none"><p></p></div>

<!-- Bulk toolbar -->
<div class="ss-bulk-bar">
  <div class="ss-bulk-left">
    <label class="ss-cb-all" title="Pažymėti visus">
      <input type="checkbox" id="ss-classes-select-all">
    </label>
    <input type="text" class="ss-search-inp" id="ss-classes-search" placeholder="Ieškoti tvarkaraščio...">
    <span class="ss-sel-badge" id="ss-classes-badge" style="display:none">
      <span id="ss-classes-sel-n">0</span> pasirinkta
    </span>
  </div>
  <div class="ss-bulk-right" id="ss-classes-bulk-right" style="display:none">
    <select class="ss-bulk-select" id="ss-classes-action">
      <option value="">— Pasirinkite veiksmą —</option>
      <option value="toggle_imp">Perjungti IMP</option>
      <option value="delete">Ištrinti pasirinktus</option>
    </select>
    <button class="button ss-bulk-apply" data-entity="classes">Vykdyti</button>
  </div>
</div>

<table class="wp-list-table widefat fixed striped ss-table" id="ss-classes-table">
<thead><tr>
  <th class="ss-cb-col"><input type="checkbox" id="ss-classes-th-all" title="Pažymėti visus"></th>
  <th style="width:32%">Klasė / Tvarkaraštis</th>
  <th style="width:14%">Kodas</th>
  <th style="width:9%">Spalva</th>
  <th style="width:8%">IMP</th>
  <th>Veiksmai</th>
</tr></thead>
<tbody>
<?php if (empty($classes)): ?>
<tr><td colspan="6" style="text-align:center;padding:32px;color:var(--sa-green-pale)">
  Dar nėra tvarkaraščių. <a href="#" class="ss-open-modal" data-modal="class">Sukurkite pirmą!</a>
  arba <a href="<?= admin_url('admin.php?page=ss-import') ?>">importuokite tvarkaraštį</a>.
</td></tr>
<?php else: foreach ($classes as $cls): $is_imp = !empty($cls->is_imp); ?>
<tr id="ss-cls-row-<?= $cls->id ?>"
    data-imp="<?= $is_imp ? '1' : '0' ?>"
    data-search="<?= esc_attr(mb_strtolower($cls->name)) ?>">
  <td class="ss-cb-col"><input type="checkbox" class="ss-row-cb" value="<?= $cls->id ?>"></td>
  <td>
    <div style="display:flex;align-items:center;gap:8px;">
      <div class="ss-cls-dot" style="background:<?= esc_attr($cls->color) ?>;flex-shrink:0"></div>
      <strong><?= esc_html($cls->name) ?></strong>
      <?php if ($is_imp): ?><span class="ss-imp-pill" id="ss-imp-badge-<?= $cls->id ?>">IMP</span><?php else: ?><span id="ss-imp-badge-<?= $cls->id ?>"></span><?php endif; ?>
    </div>
  </td>
  <td><code style="font-size:12px;letter-spacing:1px"><?= esc_html($cls->code ?: '—') ?></code></td>
  <td><span class="ss-color-badge" style="background:<?= esc_attr($cls->color) ?>"><?= esc_html($cls->color) ?></span></td>
  <td id="ss-imp-cell-<?= $cls->id ?>">
    <?php if ($is_imp): ?>
      <span class="ss-imp-pill">Taip</span>
    <?php else: ?>
      <span class="ss-no-imp">Ne</span>
    <?php endif; ?>
  </td>
  <td>
    <button class="button button-small ss-open-grid"
      data-id="<?= $cls->id ?>" data-name="<?= esc_attr($cls->name) ?>"><span class="dashicons dashicons-calendar-alt"></span> Tvarkaraštis</button>
    <button class="button button-small ss-edit-class"
      data-id="<?= $cls->id ?>" data-name="<?= esc_attr($cls->name) ?>"
      data-color="<?= esc_attr($cls->color) ?>" data-is-imp="<?= $is_imp ? '1' : '0' ?>">Redaguoti</button>
    <button class="button button-small ss-btn-danger ss-delete-class"
      data-id="<?= $cls->id ?>" data-name="<?= esc_attr($cls->name) ?>">Ištrinti</button>
  </td>
</tr>
<?php endforeach; endif; ?>
</tbody>
</table>

<!-- ══ ADD / EDIT CLASS MODAL ══ -->
<div id="ss-modal-class" class="ss-modal-overlay" style="display:none">
<div class="ss-modal">
<div class="ss-modal-head">
  <h2 id="ss-modal-class-title">Pridėti tvarkaraštį</h2>
  <button class="ss-modal-x ss-close-modal"><span class="dashicons dashicons-no-alt"></span></button>
</div>
<div class="ss-modal-body">
  <input type="hidden" id="ss-class-id">
  <div class="ss-field"><label>Klasės pavadinimas *</label>
    <input type="text" id="ss-class-name" class="regular-text" placeholder="1a, 2b, 10A...">
  </div>
  <div class="ss-field">
    <label style="display:flex;align-items:center;gap:8px;cursor:pointer;text-transform:none;font-size:13px;font-weight:600;color:var(--sa-dark);">
      <input type="checkbox" id="ss-class-is-imp" value="1" style="width:15px;height:15px;accent-color:var(--sa-green);">
      <span>Individualusis mokymosi planas (IMP)</span>
    </label>
    <p style="margin:6px 0 0;font-size:12px;color:var(--sa-green-lt);">IMP turintys mokiniai gali matyti mokytojų tvarkaraščius.</p>
  </div>
  <div class="ss-field"><label>Spalva</label>
    <div class="ss-cpicker">
      <div class="ss-swatches"><?php foreach ($palette as $c): ?>
        <button type="button" class="ss-swatch" data-color="<?= $c ?>" style="background:<?= $c ?>"></button>
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
  <button class="button ss-close-modal">Atšaukti</button>
  <button class="button button-primary" id="ss-save-class">Išsaugoti</button>
</div>
</div>
</div>

<!-- ══ SCHEDULE GRID MODAL ══ -->
<div id="ss-modal-grid" class="ss-modal-overlay ss-modal-wide" style="display:none">
<div class="ss-modal" style="max-width:900px">
<div class="ss-modal-head">
  <h2>Tvarkaraštis: <span id="ss-grid-class-name"></span></h2>
  <div style="display:flex;gap:8px;align-items:center">
    <div id="ss-grid-code-section" style="display:flex;gap:6px;align-items:center;margin-right:12px;">
      <span style="font-size:12px;color:var(--sa-green-lt);">Kodas:</span>
      <code id="ss-grid-code" style="font-size:14px;font-weight:700;background:#f0f5f0;padding:4px 10px;letter-spacing:2px;">--------</code>
      <button type="button" class="button button-small" id="ss-copy-grid-code" title="Kopijuoti kodą"><span class="dashicons dashicons-clipboard"></span></button>
      <button type="button" class="button button-small" id="ss-regen-grid-code" title="Generuoti naują kodą"><span class="dashicons dashicons-update"></span></button>
    </div>
    <button class="button button-primary" id="ss-save-grid">Išsaugoti</button>
    <button class="ss-modal-x ss-close-modal"><span class="dashicons dashicons-no-alt"></span></button>
  </div>
</div>
<div class="ss-modal-body">
  <p style="font-size:12px;color:var(--sa-green-lt);margin-bottom:12px">
    Spustelėkite ant langelio norėdami pasirinkti pamoką. Prie vieno langelio galite pridėti kelis pogrupius.
  </p>
  <div id="ss-grid-loading" style="text-align:center;padding:40px;color:var(--sa-green-lt)">Kraunama...</div>
  <div id="ss-grid-wrap" style="display:none;overflow-x:auto">
    <table class="ss-grid-table" id="ss-schedule-grid">
      <thead>
        <tr>
          <th class="ss-period-th">Pamoka</th>
          <?php foreach ($days as $day): ?><th><?= esc_html($day) ?></th><?php endforeach; ?>
        </tr>
      </thead>
      <tbody id="ss-grid-tbody"></tbody>
    </table>
  </div>
</div>
</div>
</div>

<!-- Pass data to JS -->
<script>
var ss_periods = <?= json_encode(array_map(fn($p) => array('id'=>$p->id,'number'=>$p->period_number,'start'=>substr($p->start_time,0,5),'end'=>substr($p->end_time,0,5)), $periods)) ?>;
var ss_lessons = <?= json_encode(array_map(fn($l) => array('id'=>$l->id,'label'=>$l->subject.($l->teacher_name?' — '.$l->teacher_name:''),'color'=>$l->color), $lessons)) ?>;
var ss_rooms = <?= json_encode(array_map(fn($r) => array('id'=>$r->id,'name'=>$r->name), $rooms)) ?>;
</script>
</div>
