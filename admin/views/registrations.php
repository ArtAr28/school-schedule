<?php if (!defined('ABSPATH')) exit;
$status_label = array(
    'pending'  => 'Laukia patvirtinimo',
    'approved' => 'Patvirtinta',
    'rejected' => 'Atmesta',
);
$role_label = array(
    'student'  => 'Mokinys',
    'teacher'  => 'Mokytojas',
    'employee' => 'Darbuotojas',
    'admin'    => 'Administracija',
);
// Pull teachers list (for teacher matching dropdown)
global $wpdb;
$teachers_for_match = $wpdb->get_results("SELECT id, name FROM {$wpdb->prefix}ss_teachers ORDER BY name ASC");
?>
<div class="wrap ss-wrap ss-registrations-page">
<?php
$ss_page_title    = 'Registracijos';
$ss_page_subtitle = 'Naujos registracijos – patvirtinkite mokinių, mokytojų ir administracijos paskyras.';
include SS_PLUGIN_DIR . 'admin/views/partials/header.php';
?>
<div id="ss-notice" class="notice" style="display:none"><p></p></div>

<!-- Paieška + žymėjimo pagalbininkai + masiniai veiksmai -->
<div class="ss-reg-toolbar">

  <div class="ss-reg-toolbar-row">
    <div class="ss-reg-search-wrap">
      <span class="dashicons dashicons-search"></span>
      <input type="text" id="ss-reg-search" autocomplete="off"
             placeholder="Ieškoti pagal vardą, el. paštą, klasę…">
    </div>
    <div class="ss-reg-qsel-group">
      <span class="ss-reg-toolbar-label">Žymėti:</span>
      <button type="button" class="ss-reg-chip ss-reg-qsel" data-status="pending">
        <span class="ss-reg-dot" style="--dc:#E8B93E"></span>Laukiančius
      </button>
      <button type="button" class="ss-reg-chip ss-reg-qsel" data-status="approved">
        <span class="ss-reg-dot" style="--dc:var(--sa-green)"></span>Patvirtintus
      </button>
      <button type="button" class="ss-reg-chip ss-reg-qsel" data-status="rejected">
        <span class="ss-reg-dot" style="--dc:var(--sa-red)"></span>Atmestus
      </button>
      <select id="ss-reg-qsel-role" class="ss-reg-select ss-reg-select-role" title="Žymėti pagal rolę">
        <option value="">Pagal rolę…</option>
        <option value="student">Mokinius</option>
        <option value="teacher">Mokytojus</option>
        <option value="employee">Darbuotojus</option>
        <option value="admin">Administraciją</option>
      </select>
    </div>
  </div>

  <div class="ss-reg-toolbar-row ss-reg-toolbar-actions">
    <select id="ss-reg-bulk-op" class="ss-reg-select">
      <option value="">— Masinis veiksmas —</option>
      <option value="approve_noclass">Priimti be tvarkaraščio</option>
      <option value="reject">Atmesti</option>
      <option value="delete">Ištrinti</option>
    </select>
    <button type="button" id="ss-reg-bulk-apply">
      <span class="dashicons dashicons-yes"></span>Taikyti
    </button>
    <span id="ss-reg-bulk-count" class="ss-reg-count">Pasirinkta: 0</span>
  </div>

</div>

<table class="wp-list-table widefat striped ss-table" id="ss-registrations-table">
  <thead>
    <tr>
      <th style="width:28px;"><input type="checkbox" id="ss-reg-check-all" title="Žymėti visus"></th>
      <th>Vardas, pavardė</th>
      <th>El. paštas</th>
      <th>Tipas</th>
      <th>Klasė / Detalė</th>
      <th>Pateikta</th>
      <th>Būsena</th>
      <th>Priskirti</th>
      <th></th>
      <th></th>
    </tr>
  </thead>
  <tbody>
    <?php if (empty($registrations)): ?>
    <tr><td colspan="10" style="padding:24px;color:var(--sa-green-pale);text-align:center">Registracijų dar nėra.</td></tr>
    <?php else: foreach ($registrations as $r):
      $is_pending = ($r->status === 'pending' && (int)$r->verified === 1 && !empty($r->password_hash));
      $is_unverified_or_incomplete = ($r->status === 'pending' && !$is_pending);
      $reg_role = $r->reg_role ?: 'student';
    ?>
    <tr id="ss-reg-row-<?= (int)$r->id ?>" data-id="<?= (int)$r->id ?>" data-role="<?= esc_attr($reg_role) ?>" data-status="<?= esc_attr($r->status) ?>">
      <td><input type="checkbox" class="ss-reg-check" value="<?= (int)$r->id ?>"></td>
      <td>
        <strong><?= esc_html(trim($r->first_name . ' ' . $r->last_name)) ?></strong>
        <?php if ($is_unverified_or_incomplete): ?>
          <br><small style="color:var(--sa-green-pale)">
            <?= ((int)$r->verified !== 1) ? 'Nepatvirtino el. pašto' : 'Neišsiuntė formos' ?>
          </small>
        <?php endif; ?>
      </td>
      <td><code><?= esc_html($r->email) ?></code></td>
      <td>
        <?php
        $rcls = $reg_role === 'teacher'   ? 'ss-role-pill ss-role-teacher'
              : ($reg_role === 'admin'     ? 'ss-role-pill ss-role-admin'
              : ($reg_role === 'employee' ? 'ss-role-pill ss-role-employee'
              :                              'ss-role-pill ss-role-student'));
        ?>
        <span class="<?= $rcls ?>"><?= esc_html($role_label[$reg_role] ?? $reg_role) ?></span>
      </td>
      <td><strong><?= esc_html($r->class_label ?: '—') ?></strong></td>
      <td><?= esc_html(mysql2date('Y-m-d H:i', $r->created_at)) ?></td>
      <td>
        <span class="ss-reg-status ss-reg-status-<?= esc_attr($r->status) ?>">
          <?= esc_html($status_label[$r->status] ?? $r->status) ?>
        </span>
        <?php if ($r->status === 'rejected' && $r->reject_reason): ?>
          <br><small style="color:var(--sa-red)"><?= esc_html($r->reject_reason) ?></small>
        <?php endif; ?>
      </td>
      <td>
        <?php if ($is_pending && $reg_role === 'student'): ?>
        <select class="ss-reg-class regular-text" style="max-width:220px;">
          <option value="">— pasirinkti klasę —</option>
          <?php foreach ($classes as $c):
            $sel = ($r->class_label && stripos($c->name, $r->class_label) !== false) ? ' selected' : '';
          ?>
          <option value="<?= (int)$c->id ?>"<?= $sel ?>><?= esc_html($c->name) ?></option>
          <?php endforeach; ?>
        </select>
        <?php elseif ($is_pending && $reg_role === 'teacher'): ?>
          <select class="ss-reg-teacher regular-text" style="max-width:220px;">
            <option value="">— parinkti mokytoją —</option>
            <?php foreach ($teachers_for_match as $t):
              $sel = ((int)$r->matched_teacher_id === (int)$t->id) ? ' selected' : '';
            ?>
              <option value="<?= (int)$t->id ?>"<?= $sel ?>><?= esc_html($t->name) ?></option>
            <?php endforeach; ?>
          </select>
        <?php elseif ($is_pending && $reg_role === 'employee'): ?>
          <button class="button button-primary ss-reg-approve" type="button" data-id="<?= (int)$r->id ?>" data-role="employee">Patvirtinti kaip darbuotoją</button>
        <?php elseif ($is_pending && $reg_role === 'admin'): ?>
          <em style="color:var(--sa-green-pale)">Nereikia priskirti</em>
        <?php elseif ($r->status === 'approved' && $r->assigned_class_id):
          $cl = SS_Database::get_class($r->assigned_class_id);
        ?>
        <span><?= $cl ? esc_html($cl->name) : '—' ?></span>
        <?php else: ?>
        <span style="color:var(--sa-green-pale)">—</span>
        <?php endif; ?>
      </td>
      <td style="white-space:nowrap;">
        <?php if ($is_pending): ?>
          <button class="button button-primary ss-reg-approve" type="button" data-id="<?= (int)$r->id ?>" data-role="<?= esc_attr($reg_role) ?>">Patvirtinti</button>
          <button class="button ss-reg-reject" type="button" data-id="<?= (int)$r->id ?>">Atmesti</button>
        <?php elseif ($r->status === 'pending'): ?>
          <em style="color:var(--sa-green-pale)">Laukia vartotojo</em>
        <?php else: ?>
          <em style="color:var(--sa-green-pale)"><?= esc_html($status_label[$r->status]) ?></em>
        <?php endif; ?>
      </td>
      <td style="text-align:center;">
        <button class="ss-reg-delete" type="button" data-id="<?= (int)$r->id ?>" title="Ištrinti registraciją" aria-label="Ištrinti">&times;</button>
      </td>
    </tr>
    <?php endforeach; endif; ?>
  </tbody>
</table>

<style>
.ss-reg-status{
  display:inline-block;padding:3px 10px;
  font-size:11px;font-weight:700;letter-spacing:.3px;text-transform:uppercase;
}
.ss-reg-status-pending{background:#FFF4D6;color:#8A6A1F;border:1px solid var(--sa-yellow);}
.ss-reg-status-approved{background:#E5F4E8;color:var(--sa-forest);border:1px solid var(--sa-green-pale);}
.ss-reg-status-rejected{background:#FFE9E9;color:#9F2226;border:1px solid #F5A0A2;}
.ss-role-pill{display:inline-block;padding:3px 10px;font-size:11px;font-weight:700;letter-spacing:.2px;}
.ss-role-student{background:#E5F4E8;color:var(--sa-forest);}
.ss-role-teacher{background:#E7EEF7;color:var(--sa-navy);}
.ss-role-admin    {background:#F5E6E6;color:#9F2226;}
.ss-role-employee {background:#FFF3E0;color:#E65100;}
.ss-reg-delete{
  background:none;border:none;cursor:pointer;
  color:var(--sa-red);font-size:18px;font-weight:700;
  line-height:1;padding:2px 6px;
  transition:background .15s,color .15s;
}
.ss-reg-delete:hover{background:#FFE9E9;color:#9F2226;}

/* ── Įrankių juosta (paieška / žymėjimas / masiniai veiksmai) ── */
.ss-reg-toolbar{
  background:var(--sa-surface);border:1px solid var(--sa-border);
  box-shadow:var(--sa-shadow);padding:14px 16px;margin:16px 0 12px;
  display:flex;flex-direction:column;gap:12px;
}
.ss-reg-toolbar-row{display:flex;align-items:center;gap:10px;flex-wrap:wrap;}
.ss-reg-toolbar-label{
  font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;
  color:var(--sa-green-lt);margin-right:2px;
}
.ss-reg-search-wrap{position:relative;flex:1 1 260px;max-width:380px;}
.ss-reg-search-wrap .dashicons{
  position:absolute;left:10px;top:50%;transform:translateY(-50%);
  font-size:16px;width:16px;height:16px;color:var(--sa-green-lt);pointer-events:none;
}
.ss-reg-search-wrap input{
  width:100%;height:36px;padding:0 12px 0 34px;
  border:1.5px solid var(--sa-border);border-radius:0;background:var(--sa-white);
  font-size:13px;color:var(--sa-dark);outline:none;box-shadow:none;
  transition:border-color .15s,box-shadow .15s;
}
.ss-reg-search-wrap input:focus{border-color:var(--sa-green);box-shadow:0 0 0 3px rgba(79,137,82,.12);}
.ss-reg-qsel-group{display:flex;align-items:center;gap:8px;flex-wrap:wrap;}
.ss-reg-chip{
  display:inline-flex;align-items:center;gap:7px;height:32px;padding:0 14px;
  background:var(--sa-white);border:1.5px solid var(--sa-border);border-radius:999px;
  font-family:inherit;font-size:12.5px;font-weight:600;color:var(--sa-dark);
  cursor:pointer;transition:border-color .15s,background .15s,box-shadow .15s;
}
.ss-reg-chip:hover{border-color:var(--sa-green);background:var(--sa-bg);box-shadow:0 2px 8px rgba(1,42,54,.06);}
.ss-reg-chip:active{transform:translateY(1px);}
.ss-reg-dot{width:9px;height:9px;border-radius:50%;background:var(--dc,var(--sa-green));flex-shrink:0;}
.ss-reg-select{
  height:36px;padding:0 30px 0 12px;border:1.5px solid var(--sa-border);border-radius:0;
  background:var(--sa-white);font-size:13px;color:var(--sa-dark);outline:none;box-shadow:none;
  transition:border-color .15s;min-width:210px;max-width:100%;line-height:34px;
}
.ss-reg-select:focus{border-color:var(--sa-green);box-shadow:0 0 0 3px rgba(79,137,82,.12);}
.ss-reg-select-role{min-width:150px;height:32px;border-radius:999px;font-size:12.5px;font-weight:600;}
.ss-reg-toolbar-actions{border-top:1px solid var(--sa-border);padding-top:12px;}
#ss-reg-bulk-apply{
  display:inline-flex;align-items:center;gap:6px;height:36px;padding:0 18px;
  background:var(--sa-green);border:1.5px solid var(--sa-green);border-radius:0;
  font-family:inherit;font-size:13px;font-weight:700;color:var(--sa-white);
  cursor:pointer;transition:background .15s,border-color .15s,box-shadow .15s;
}
#ss-reg-bulk-apply:hover{background:var(--sa-forest);border-color:var(--sa-forest);box-shadow:0 3px 10px rgba(20,69,47,.25);}
#ss-reg-bulk-apply:disabled{opacity:.6;cursor:default;}
#ss-reg-bulk-apply .dashicons{font-size:16px;width:16px;height:16px;}
.ss-reg-count{
  display:inline-flex;align-items:center;height:32px;padding:0 14px;
  background:var(--sa-bg);border:1px solid var(--sa-border);border-radius:999px;
  font-size:12px;font-weight:600;color:var(--sa-green-lt);
  transition:background .15s,color .15s,border-color .15s;
}
.ss-reg-count.has{background:#E5F4E8;border-color:var(--sa-green-pale);color:var(--sa-forest);}
</style>

<script>
jQuery(function($){
  function showNotice(msg, type){
    var $n=$('#ss-notice');
    $n.removeClass('notice-success notice-error').addClass('notice-'+(type||'success')).find('p').text(msg);
    $n.show(); setTimeout(function(){ $n.fadeOut(); }, 3500);
  }

  $(document).on('click', '.ss-reg-approve', function(){
    var $btn  = $(this);
    var id    = $btn.data('id');
    var role  = $btn.data('role') || 'student';
    var $row  = $('#ss-reg-row-'+id);
    var payload = { action: 'ss_approve_registration', nonce: ss_admin.nonce, id: id };
    if (role === 'student') {
      var cid = $row.find('.ss-reg-class').val();
      if (!cid) { alert('Pasirinkite tvarkaraštį (klasę).'); return; }
      payload.class_id = cid;
    } else if (role === 'teacher') {
      var tid = $row.find('.ss-reg-teacher').val();
      if (!tid) { alert('Parinkite mokytoją iš sąrašo.'); return; }
      payload.teacher_id = tid;
    }
    if (!confirm('Patvirtinti registraciją? Bus sukurta paskyra ir išsiųstas patvirtinimas el. paštu.')) return;
    $btn.prop('disabled', true).text('Saugoma...');
    $.post(ss_admin.ajax_url, payload, function(res){
      $btn.prop('disabled', false).text('Patvirtinti');
      if (res.success) {
        showNotice(res.data.message || 'Patvirtinta.');
        setTimeout(function(){ location.reload(); }, 700);
      } else {
        showNotice((res.data && res.data.message) || 'Klaida.', 'error');
      }
    });
  });

  $(document).on('click', '.ss-reg-reject', function(){
    var id = $(this).data('id');
    var reason = prompt('Atmetimo priežastis (neprivaloma):', '');
    if (reason === null) return;
    $.post(ss_admin.ajax_url, {
      action: 'ss_reject_registration',
      nonce: ss_admin.nonce,
      id: id, reason: reason
    }, function(res){
      if (res.success) {
        showNotice('Atmesta.');
        setTimeout(function(){ location.reload(); }, 600);
      } else {
        showNotice((res.data && res.data.message) || 'Klaida.', 'error');
      }
    });
  });

  /* ── Masiniai veiksmai ── */
  function regRows(){ return $('#ss-registrations-table tbody tr[data-id]'); }
  function visibleRows(){ return regRows().filter(':visible'); }
  function bulkChecked(){
    return $('.ss-reg-check:checked').map(function(){ return parseInt(this.value,10); }).get();
  }
  function bulkRefreshCount(){
    var n = bulkChecked().length;
    $('#ss-reg-bulk-count').text('Pasirinkta: ' + n).toggleClass('has', n > 0);
  }
  $('#ss-reg-check-all').on('change', function(){
    // „Žymėti visus" taikoma tik MATOMOMS (po paieškos filtro) eilutėms.
    visibleRows().find('.ss-reg-check').prop('checked', this.checked);
    bulkRefreshCount();
  });
  $(document).on('change', '.ss-reg-check', bulkRefreshCount);

  /* ── Paieška — filtruoja eilutes; paslėptos eilutės atžymimos ── */
  $('#ss-reg-search').on('input', function(){
    var q = $(this).val().toLowerCase().trim();
    regRows().each(function(){
      var $tr = $(this);
      var match = !q || $tr.text().toLowerCase().indexOf(q) !== -1;
      $tr.toggle(match);
      if (!match) $tr.find('.ss-reg-check').prop('checked', false);
    });
    $('#ss-reg-check-all').prop('checked', false);
    bulkRefreshCount();
  });

  /* ── Greitas žymėjimas pagal būseną / rolę (tik tarp matomų eilučių) ── */
  function quickSelect(attr, val){
    regRows().find('.ss-reg-check').prop('checked', false); // nauja atranka
    visibleRows().each(function(){
      if ($(this).data(attr) === val) $(this).find('.ss-reg-check').prop('checked', true);
    });
    $('#ss-reg-check-all').prop('checked', false);
    bulkRefreshCount();
  }
  $('.ss-reg-qsel').on('click', function(){ quickSelect('status', $(this).data('status')); });
  $('#ss-reg-qsel-role').on('change', function(){
    if (this.value) { quickSelect('role', this.value); this.value=''; }
  });

  $('#ss-reg-bulk-apply').on('click', function(){
    var op  = $('#ss-reg-bulk-op').val();
    var ids = bulkChecked();
    if (!op)        { alert('Pasirinkite masinį veiksmą.'); return; }
    if (!ids.length){ alert('Pažymėkite bent vieną registraciją.'); return; }

    var payload = { action:'ss_bulk_registrations', nonce:ss_admin.nonce, op:op, ids:ids };
    var confirms = {
      approve_noclass: 'Patvirtinti ' + ids.length + ' registracijas BE tvarkaraščio? Bus sukurtos paskyros ir išsiųsti laiškai. Netinkamos (nebaigtos/jau patvirtintos) bus praleistos.',
      reject:          'Atmesti ' + ids.length + ' registracijas? Vartotojams bus išsiųsti laiškai.',
      'delete':        'Ištrinti ' + ids.length + ' registracijas? Veiksmo anuliuoti nebus galima.'
    };
    if (op === 'reject') {
      var reason = prompt('Atmetimo priežastis (bendra visoms, neprivaloma):', '');
      if (reason === null) return;
      payload.reason = reason;
    }
    if (!confirm(confirms[op])) return;

    var applyHtml = '<span class="dashicons dashicons-yes"></span>Taikyti';
    var $btn = $(this).prop('disabled', true).html('<span class="dashicons dashicons-update"></span>Vykdoma...');
    $.post(ss_admin.ajax_url, payload, function(res){
      $btn.prop('disabled', false).html(applyHtml);
      if (res.success) {
        showNotice(res.data.message || 'Atlikta.');
        setTimeout(function(){ location.reload(); }, 800);
      } else {
        showNotice((res.data && res.data.message) || 'Klaida.', 'error');
      }
    }).fail(function(){
      $btn.prop('disabled', false).html(applyHtml);
      showNotice('Serverio klaida.', 'error');
    });
  });

  $(document).on('click', '.ss-reg-delete', function(){
    var id = $(this).data('id');
    if (!confirm('Ar tikrai norite ištrinti šią registraciją? Veiksmo anuliuoti nebus galima.')) return;
    var $btn = $(this);
    $btn.prop('disabled', true);
    $.post(ss_admin.ajax_url, {
      action: 'ss_delete_registration',
      nonce: ss_admin.nonce,
      id: id
    }, function(res){
      if (res.success) {
        $('#ss-reg-row-'+id).fadeOut(300, function(){ $(this).remove(); });
        showNotice(res.data.message || 'Ištrinta.');
      } else {
        $btn.prop('disabled', false);
        showNotice((res.data && res.data.message) || 'Klaida.', 'error');
      }
    });
  });
});
</script>
</div>
