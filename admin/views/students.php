<?php if (!defined('ABSPATH')) exit;
$ss_page_title    = 'Mokiniai';
$ss_page_subtitle = 'Mokinių paskyros ir priskirtos klasės.';
$ss_page_actions  = '<button class="button button-primary ss-open-modal" data-modal="newuser">+ Sukurti vartotoją</button>';
?>
<div class="wrap ss-wrap">
<?php include SS_PLUGIN_DIR . 'admin/views/partials/header.php'; ?>
<div id="ss-notice" class="notice" style="display:none"><p></p></div>

<!-- Bulk toolbar -->
<div class="ss-bulk-bar">
  <div class="ss-bulk-left">
    <label class="ss-cb-all" title="Pažymėti visus">
      <input type="checkbox" id="ss-students-select-all">
    </label>
    <input type="text" class="ss-search-inp" id="ss-students-search" placeholder="Ieškoti mokinio...">
    <span class="ss-sel-badge" id="ss-students-badge" style="display:none">
      <span id="ss-students-sel-n">0</span> pasirinkta
    </span>
  </div>
  <div class="ss-bulk-right" id="ss-students-bulk-right" style="display:none">
    <select class="ss-bulk-select" id="ss-students-action">
      <option value="">— Pasirinkite veiksmą —</option>
      <option value="delete">Ištrinti pasirinktus</option>
    </select>
    <button class="button ss-bulk-apply" data-entity="students">Vykdyti</button>
  </div>
</div>

<table class="wp-list-table widefat fixed striped ss-table" id="ss-students-table">
<thead><tr>
  <th class="ss-cb-col"><input type="checkbox" id="ss-students-th-all" title="Pažymėti visus"></th>
  <th style="width:22%">Vardas</th>
  <th style="width:9%">Klasė</th>
  <th style="width:15%">Vartotojas</th>
  <th style="width:22%">El. paštas</th>
  <th style="width:16%">Tvarkaraštis(-čiai)</th>
  <th>Veiksmai</th>
</tr></thead>
<tbody>
<?php if (empty($students)): ?>
<tr><td colspan="7" style="text-align:center;padding:32px;color:var(--sa-green-pale)">
  Dar nėra mokinių. <a href="#" class="ss-open-modal" data-modal="newuser">Sukurkite pirmąjį!</a>
</td></tr>
<?php else: foreach ($students as $s):
  $uc     = SS_Database::get_user_classes($s->ID);
  $cnames = array_map(fn($r) => esc_html($r->name), $uc);
  $lvl    = SS_Roles::get_student_level($s->ID);
  $search = mb_strtolower($s->display_name . ' ' . $s->user_login . ' ' . $s->user_email . ' ' . $lvl['label'] . ' ' . implode(' ', $cnames));
?>
<tr id="ss-student-row-<?= $s->ID ?>" data-search="<?= esc_attr($search) ?>">
  <td class="ss-cb-col"><input type="checkbox" class="ss-row-cb" value="<?= $s->ID ?>"></td>
  <td><strong><?= esc_html($s->display_name) ?></strong></td>
  <td class="ss-stud-class-cell" data-id="<?= $s->ID ?>">
    <select class="ss-stud-level" title="Klasė (I–IV)">
      <option value="">—</option>
      <?php foreach (SS_Roles::student_levels() as $L): ?>
      <option value="<?= esc_attr($L) ?>" <?= $lvl['level'] === $L ? 'selected' : '' ?>><?= esc_html($L) ?></option>
      <?php endforeach; ?>
    </select>
    <input type="text" class="ss-stud-letter" maxlength="3" value="<?= esc_attr($lvl['letter']) ?>" placeholder="a" title="Klasės raidė">
    <span class="ss-stud-class-ok dashicons dashicons-yes" style="display:none;color:var(--sa-green)"></span>
  </td>
  <td><code><?= esc_html($s->user_login) ?></code></td>
  <td style="font-size:13px"><?= esc_html($s->user_email) ?></td>
  <td><?= $cnames ? implode(', ', $cnames) : '<em style="color:var(--sa-green-pale)">Nepriskirta</em>' ?></td>
  <td>
    <button class="button button-small ss-assign-btn"
      data-id="<?= $s->ID ?>" data-name="<?= esc_attr($s->display_name) ?>">Priskirti klases</button>
    <button class="button button-small ss-btn-danger ss-delete-student"
      data-id="<?= $s->ID ?>" data-name="<?= esc_attr($s->display_name) ?>">Ištrinti</button>
  </td>
</tr>
<?php endforeach; endif; ?>
</tbody>
</table>

<!-- ══ CREATE USER MODAL ══ -->
<div id="ss-modal-newuser" class="ss-modal-overlay" style="display:none">
<div class="ss-modal">
<div class="ss-modal-head">
  <h2>Sukurti mokinį / mokytoją</h2>
  <button class="ss-modal-x ss-close-modal"><span class="dashicons dashicons-no-alt"></span></button>
</div>
<div class="ss-modal-body">
  <div class="ss-field"><label>Vardas Pavardė *</label>
    <input type="text" id="ss-new-display" class="regular-text" placeholder="Ona Onaitė">
  </div>
  <div class="ss-field"><label>Vartotojo vardas *</label>
    <input type="text" id="ss-new-login" class="regular-text" placeholder="ona.onaite">
  </div>
  <div class="ss-field"><label>El. paštas *</label>
    <input type="email" id="ss-new-email" class="regular-text">
  </div>
  <div class="ss-field"><label>Slaptažodis *</label>
    <div style="display:flex;gap:8px">
      <input type="text" id="ss-new-pass" class="regular-text" placeholder="sugeneruotas automatiškai">
      <button type="button" class="button" id="ss-gen-pass" title="Generuoti slaptažodį"><span class="dashicons dashicons-randomize"></span></button>
    </div>
  </div>
  <div class="ss-field"><label>Rolė</label>
    <select id="ss-new-role" class="regular-text">
      <option value="ss_student">Mokinys</option>
      <option value="ss_teacher">Mokytojas</option>
    </select>
  </div>
</div>
<div class="ss-modal-foot">
  <button class="button ss-close-modal">Atšaukti</button>
  <button class="button button-primary" id="ss-do-create-user">Sukurti</button>
</div>
</div>
</div>

<!-- ══ ASSIGN CLASSES MODAL ══ -->
<div id="ss-modal-assign" class="ss-modal-overlay" style="display:none">
<div class="ss-modal">
<div class="ss-modal-head">
  <h2>Klasės: <em id="ss-assign-name" style="font-weight:500;color:var(--sa-green)"></em></h2>
  <button class="ss-modal-x ss-close-modal"><span class="dashicons dashicons-no-alt"></span></button>
</div>
<div class="ss-modal-body">
  <input type="hidden" id="ss-assign-uid">
  <div style="max-height:340px;overflow-y:auto">
    <?php foreach ($classes as $cls): ?>
    <label class="ss-check-label">
      <input type="checkbox" class="ss-cls-check" value="<?= $cls->id ?>">
      <span class="ss-cls-dot" style="background:<?= esc_attr($cls->color) ?>"></span>
      <?= esc_html($cls->name) ?>
    </label>
    <?php endforeach; ?>
    <?php if (empty($classes)): ?><p style="color:var(--sa-green-pale)">Nėra sukurtų klasių.</p><?php endif; ?>
  </div>
</div>
<div class="ss-modal-foot">
  <button class="button ss-close-modal">Atšaukti</button>
  <button class="button button-primary" id="ss-save-assign">Išsaugoti priskyrimus</button>
</div>
</div>
</div>
</div>

<style>
.ss-stud-class-cell{white-space:nowrap;}
.ss-stud-class-cell .ss-stud-level{padding:2px 4px;border:1px solid #c3c4c7;font-size:13px;vertical-align:middle;}
.ss-stud-class-cell .ss-stud-letter{width:46px;padding:3px 6px;border:1px solid #c3c4c7;font-size:13px;vertical-align:middle;margin-left:4px;}
.ss-stud-class-cell .ss-stud-class-ok{vertical-align:middle;margin-left:3px;font-size:18px;width:18px;height:18px;}
.ss-stud-class-cell.is-saving{opacity:.55;}
</style>

<script>
(function($){
  // Mokinio klasės (I–IV) redagavimas tiesiog lentelėje — auto-išsaugojimas.
  function saveClass($cell){
    var uid    = $cell.data('id');
    var level  = $cell.find('.ss-stud-level').val();
    var letter = $cell.find('.ss-stud-letter').val();
    var $ok    = $cell.find('.ss-stud-class-ok');
    $cell.addClass('is-saving'); $ok.hide();
    $.post(ss_admin.ajax_url, {
      action:'ss_save_student_class', nonce:ss_admin.nonce,
      user_id:uid, level:level, letter:letter
    }, function(res){
      $cell.removeClass('is-saving');
      if(res && res.success){
        if(res.data && typeof res.data.letter !== 'undefined') $cell.find('.ss-stud-letter').val(res.data.letter);
        $ok.show(); setTimeout(function(){ $ok.fadeOut(400); }, 1400);
      } else {
        alert((res && res.data && res.data.message) || 'Nepavyko išsaugoti klasės.');
      }
    }).fail(function(){ $cell.removeClass('is-saving'); alert('Ryšio klaida.'); });
  }
  $(document).on('change', '.ss-stud-class-cell .ss-stud-level', function(){ saveClass($(this).closest('.ss-stud-class-cell')); });
  $(document).on('change blur', '.ss-stud-class-cell .ss-stud-letter', function(){
    var $cell=$(this).closest('.ss-stud-class-cell');
    if($(this).data('prev') === $(this).val()) return; // niekas nepasikeitė
    $(this).data('prev', $(this).val());
    saveClass($cell);
  });
  $('.ss-stud-class-cell .ss-stud-letter').each(function(){ $(this).data('prev', $(this).val()); });
})(jQuery);
</script>
