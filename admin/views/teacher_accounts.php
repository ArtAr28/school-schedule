<?php if (!defined('ABSPATH')) exit;

global $wpdb;

// All teacher records from the plugin DB
$db_teachers = SS_Database::get_teachers();

// All WP users that could hold a teacher account (teachers, employees, admins, others)
$wp_users = get_users(array('number' => -1, 'orderby' => 'display_name'));

// Which WP accounts are already linked to a teacher record
$linked_uids = array();
foreach ($db_teachers as $t) {
    if ((int)$t->user_id > 0) $linked_uids[(int)$t->user_id] = (int)$t->id;
}

// Lesson counts per teacher (single query → map)
$lesson_counts = array();
$lc_rows = $wpdb->get_results(
    "SELECT l.teacher_id AS tid, COUNT(DISTINCT cs.id) AS cnt
     FROM {$wpdb->prefix}ss_lessons l
     JOIN {$wpdb->prefix}ss_class_schedule cs ON cs.lesson_id = l.id
     GROUP BY l.teacher_id"
);
foreach ($lc_rows as $r) { $lesson_counts[(int)$r->tid] = (int)$r->cnt; }

$ss_page_title    = 'Mokytojų paskyros';
$ss_page_subtitle = 'Susiekite mokytojų įrašus su prisijungimo paskyromis — kad mokytojas matytų savo tvarkaraštį.';
$ss_page_actions  = '<button class="button button-primary" id="ss-tacc-create-open">+ Sukurti mokytojo paskyrą</button>';
?>
<div class="wrap ss-wrap">
<?php include SS_PLUGIN_DIR . 'admin/views/partials/header.php'; ?>
<div id="ss-notice" class="notice" style="display:none"><p></p></div>

<!-- Toolbar -->
<div class="ss-bulk-bar">
  <div class="ss-bulk-left">
    <input type="text" class="ss-search-inp" id="ss-tacc-search" placeholder="Ieškoti mokytojo...">
  </div>
</div>

<table class="wp-list-table widefat fixed striped ss-table" id="ss-tacc-table">
<thead><tr>
  <th style="width:28%">Mokytojas</th>
  <th style="width:24%">El. paštas</th>
  <th style="width:26%">Prisijungimo paskyra</th>
  <th style="width:10%;text-align:center">Pamokų</th>
  <th>Veiksmai</th>
</tr></thead>
<tbody>
<?php if (empty($db_teachers)): ?>
<tr><td colspan="5" style="text-align:center;padding:32px;color:var(--sa-green-pale)">
  Dar nėra mokytojų. Sukurkite juos <a href="<?= admin_url('admin.php?page=ss-teachers') ?>">čia</a>.
</td></tr>
<?php else: foreach ($db_teachers as $t):
  $linked_user = ((int)$t->user_id > 0) ? get_user_by('id', (int)$t->user_id) : null;
  $lcount      = $lesson_counts[(int)$t->id] ?? 0;
  $search      = mb_strtolower($t->name . ' ' . $t->short . ' ' . $t->email . ' ' . ($linked_user ? $linked_user->user_login : ''));
?>
<tr id="ss-tacc-row-<?= (int)$t->id ?>" data-search="<?= esc_attr($search) ?>">
  <td>
    <strong><?= esc_html($t->name) ?></strong>
    <?php if ($t->short): ?><code style="margin-left:6px"><?= esc_html($t->short) ?></code><?php endif; ?>
  </td>
  <td style="font-size:13px"><?= esc_html($t->email ?: '—') ?></td>
  <td>
    <?php if ($linked_user): ?>
      <span class="ss-imp-pill" style="background:#E6F3E7;color:var(--sa-forest)"><span class="dashicons dashicons-yes" style="font-size:14px;width:14px;height:14px;vertical-align:text-bottom"></span> <?= esc_html($linked_user->user_login) ?></span>
    <?php else: ?>
      <span class="ss-no-imp">— Nesusieta</span>
    <?php endif; ?>
  </td>
  <td style="text-align:center">
    <strong style="color:<?= $lcount ? 'var(--sa-green)' : 'var(--sa-green-pale)' ?>"><?= (int)$lcount ?></strong>
  </td>
  <td>
    <?php if ($linked_user): ?>
      <a href="<?= admin_url('user-edit.php?user_id=' . (int)$linked_user->ID) ?>" class="button button-small">Peržiūrėti</a>
      <button class="button button-small ss-btn-danger ss-tacc-unlink"
        data-id="<?= (int)$t->id ?>" data-name="<?= esc_attr($t->name) ?>">Atsieti</button>
    <?php else: ?>
      <button class="button button-small button-primary ss-tacc-link"
        data-id="<?= (int)$t->id ?>"
        data-name="<?= esc_attr($t->name) ?>"
        data-email="<?= esc_attr($t->email) ?>"><span class="dashicons dashicons-admin-links"></span> Susieti paskyrą</button>
    <?php endif; ?>
  </td>
</tr>
<?php endforeach; endif; ?>
</tbody>
</table>

<!-- ══ LINK MODAL ══ -->
<div id="ss-modal-tacc-link" class="ss-modal-overlay" style="display:none">
<div class="ss-modal">
<div class="ss-modal-head">
  <h2>Susieti paskyrą: <em id="ss-tacc-link-tname" style="font-weight:500;color:var(--sa-green)"></em></h2>
  <button class="ss-modal-x ss-close-modal"><span class="dashicons dashicons-no-alt"></span></button>
</div>
<div class="ss-modal-body">
  <input type="hidden" id="ss-tacc-link-tid">
  <div class="ss-field">
    <label>Pasirinkite esamą paskyrą</label>
    <select id="ss-tacc-wp-user" class="regular-text">
      <option value="">— Sukurti naują paskyrą —</option>
      <?php foreach ($wp_users as $u):
        if (isset($linked_uids[(int)$u->ID])) continue; // already linked to another teacher
        $roles = (array)$u->roles;
        $rl = in_array('administrator',$roles,true) ? 'administratorius'
            : (in_array('ss_teacher',$roles,true) ? 'mokytojas'
            : (in_array('ss_employee',$roles,true) ? 'darbuotojas'
            : (in_array('ss_student',$roles,true) ? 'mokinys' : 'vartotojas')));
      ?>
      <option value="<?= (int)$u->ID ?>"><?= esc_html($u->display_name) ?> · <?= esc_html($u->user_login) ?> (<?= esc_html($rl) ?>)</option>
      <?php endforeach; ?>
    </select>
    <p class="description" style="margin-top:6px">Paskyrai bus suteiktos mokytojo teisės.</p>
  </div>

  <div id="ss-tacc-new-fields">
    <hr style="border:none;border-top:1px solid var(--sa-light);margin:14px 0">
    <p class="description" style="margin-top:0">Naujos paskyros duomenys:</p>
    <div class="ss-field"><label>Vartotojo vardas *</label><input type="text" id="ss-tacc-new-login" class="regular-text" placeholder="pvz. jonas.jonaitis"></div>
    <div class="ss-field"><label>El. paštas *</label><input type="email" id="ss-tacc-new-email" class="regular-text"></div>
    <div class="ss-field"><label>Slaptažodis *</label>
      <div style="display:flex;gap:8px">
        <input type="text" id="ss-tacc-new-pass" class="regular-text" placeholder="sugeneruojamas automatiškai">
        <button type="button" class="button ss-tacc-genpass" data-target="ss-tacc-new-pass"><span class="dashicons dashicons-randomize"></span></button>
      </div>
    </div>
  </div>
</div>
<div class="ss-modal-foot">
  <button class="button ss-close-modal">Atšaukti</button>
  <button class="button button-primary" id="ss-tacc-do-link">Susieti</button>
</div>
</div>
</div>

<!-- ══ CREATE MODAL ══ -->
<div id="ss-modal-tacc-create" class="ss-modal-overlay" style="display:none">
<div class="ss-modal">
<div class="ss-modal-head">
  <h2>Sukurti mokytojo paskyrą</h2>
  <button class="ss-modal-x ss-close-modal"><span class="dashicons dashicons-no-alt"></span></button>
</div>
<div class="ss-modal-body">
  <div class="ss-field">
    <label>Mokytojas *</label>
    <select id="ss-tacc-create-tid" class="regular-text">
      <option value="">— Pasirinkite —</option>
      <?php foreach ($db_teachers as $t):
        if ((int)$t->user_id > 0) continue; // already has an account
      ?>
      <option value="<?= (int)$t->id ?>"
              data-name="<?= esc_attr($t->name) ?>"
              data-email="<?= esc_attr($t->email) ?>"><?= esc_html($t->name) ?><?= $t->short ? ' ('.esc_html($t->short).')' : '' ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="ss-field"><label>Vartotojo vardas *</label><input type="text" id="ss-tacc-create-login" class="regular-text" placeholder="pvz. jonas.jonaitis"></div>
  <div class="ss-field"><label>El. paštas *</label><input type="email" id="ss-tacc-create-email" class="regular-text"></div>
  <div class="ss-field"><label>Slaptažodis *</label>
    <div style="display:flex;gap:8px">
      <input type="text" id="ss-tacc-create-pass" class="regular-text" placeholder="sugeneruojamas automatiškai">
      <button type="button" class="button ss-tacc-genpass" data-target="ss-tacc-create-pass"><span class="dashicons dashicons-randomize"></span></button>
    </div>
  </div>
</div>
<div class="ss-modal-foot">
  <button class="button ss-close-modal">Atšaukti</button>
  <button class="button button-primary" id="ss-tacc-do-create">Sukurti ir susieti</button>
</div>
</div>
</div>
</div>

<script>
(function($){
  var url   = '<?= admin_url('admin-ajax.php') ?>';
  var nonce = '<?= wp_create_nonce('ss_admin_nonce') ?>';

  function notice(msg,type){
    var $n=$('#ss-notice');
    $n.removeClass('notice-success notice-error').addClass('notice-'+(type||'success')).find('p').text(msg);
    $n.show(); $('html,body').animate({scrollTop:0},300);
  }
  function genPass(){
    var c='abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789!@#',p='';
    for(var i=0;i<12;i++) p+=c[Math.floor(Math.random()*c.length)];
    return p;
  }
  function slugName(name){
    return (name||'').toLowerCase().replace(/[ąčęėįšųūž]/g,function(c){
      return {'ą':'a','č':'c','ę':'e','ė':'e','į':'i','š':'s','ų':'u','ū':'u','ž':'z'}[c]||c;
    }).replace(/[^a-z0-9 ]/g,'').trim().split(/\s+/).join('.');
  }

  // Password generators
  $(document).on('click','.ss-tacc-genpass',function(){
    $('#'+$(this).data('target')).val(genPass());
  });

  // Search filter
  $('#ss-tacc-search').on('input',function(){
    var q=$(this).val().toLowerCase().trim();
    $('#ss-tacc-table tbody tr').each(function(){
      var ok=!q || (($(this).data('search')||'').indexOf(q)!==-1);
      $(this).toggle(ok);
    });
  });

  /* ── LINK ── */
  $(document).on('click','.ss-tacc-link',function(){
    var $b=$(this);
    $('#ss-tacc-link-tid').val($b.data('id'));
    $('#ss-tacc-link-tname').text($b.data('name'));
    $('#ss-tacc-wp-user').val('');
    $('#ss-tacc-new-login').val(slugName($b.data('name')));
    $('#ss-tacc-new-email').val($b.data('email')||'');
    $('#ss-tacc-new-pass').val(genPass());
    $('#ss-tacc-new-fields').show();
    $('#ss-modal-tacc-link').show();
  });

  // Toggle new-account fields depending on selection
  $('#ss-tacc-wp-user').on('change',function(){
    $('#ss-tacc-new-fields').toggle($(this).val()==='');
  });

  $('#ss-tacc-do-link').on('click',function(){
    var tid=$('#ss-tacc-link-tid').val();
    var uid=$('#ss-tacc-wp-user').val();
    var $btn=$(this); $btn.prop('disabled',true).text('Saugoma...');
    if(uid){
      // Link to existing account
      $.post(url,{action:'ss_link_teacher_account',nonce:nonce,teacher_id:tid,user_id:uid},function(res){
        $btn.prop('disabled',false).text('Susieti');
        if(res.success){ notice(res.data.message||'Susieta!'); setTimeout(function(){location.reload();},800); }
        else notice((res.data&&res.data.message)||'Klaida.','error');
      });
    } else {
      // Create new account + link
      var un=$('#ss-tacc-new-login').val().trim(), em=$('#ss-tacc-new-email').val().trim(), pw=$('#ss-tacc-new-pass').val();
      if(!un||!em){ alert('Vartotojo vardas ir el. paštas privalomi.'); $btn.prop('disabled',false).text('Susieti'); return; }
      $.post(url,{action:'ss_create_teacher_account',nonce:nonce,teacher_id:tid,username:un,email:em,password:pw},function(res){
        $btn.prop('disabled',false).text('Susieti');
        if(res.success){ notice(res.data.message||'Sukurta!'); setTimeout(function(){location.reload();},900); }
        else notice((res.data&&res.data.message)||'Klaida.','error');
      });
    }
  });

  /* ── UNLINK ── */
  $(document).on('click','.ss-tacc-unlink',function(){
    var tid=$(this).data('id'), name=$(this).data('name');
    if(!confirm('Atsieti "'+name+'" nuo prisijungimo paskyros?\n(Paskyra nebus ištrinta.)')) return;
    $.post(url,{action:'ss_unlink_teacher_account',nonce:nonce,teacher_id:tid},function(res){
      if(res.success){ notice(res.data.message||'Atsieta.'); setTimeout(function(){location.reload();},700); }
      else notice((res.data&&res.data.message)||'Klaida.','error');
    });
  });

  /* ── CREATE (top button) ── */
  $('#ss-tacc-create-open').on('click',function(){
    $('#ss-tacc-create-tid').val('');
    $('#ss-tacc-create-login').val('');
    $('#ss-tacc-create-email').val('');
    $('#ss-tacc-create-pass').val(genPass());
    $('#ss-modal-tacc-create').show();
  });
  $('#ss-tacc-create-tid').on('change',function(){
    var $opt=$(this).find(':selected');
    var name=$opt.data('name')||'', email=$opt.data('email')||'';
    if(name && !$('#ss-tacc-create-login').val()) $('#ss-tacc-create-login').val(slugName(name));
    if(email && !$('#ss-tacc-create-email').val()) $('#ss-tacc-create-email').val(email);
  });
  $('#ss-tacc-do-create').on('click',function(){
    var tid=$('#ss-tacc-create-tid').val();
    var un=$('#ss-tacc-create-login').val().trim(), em=$('#ss-tacc-create-email').val().trim(), pw=$('#ss-tacc-create-pass').val();
    if(!tid){ alert('Pasirinkite mokytoją.'); return; }
    if(!un||!em){ alert('Vartotojo vardas ir el. paštas privalomi.'); return; }
    var $btn=$(this); $btn.prop('disabled',true).text('Kuriama...');
    $.post(url,{action:'ss_create_teacher_account',nonce:nonce,teacher_id:tid,username:un,email:em,password:pw},function(res){
      $btn.prop('disabled',false).text('Sukurti ir susieti');
      if(res.success){ notice(res.data.message||'Sukurta!'); setTimeout(function(){location.reload();},900); }
      else notice((res.data&&res.data.message)||'Klaida.','error');
    });
  });
})(jQuery);
</script>