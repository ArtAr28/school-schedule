<?php if (!defined('ABSPATH')) exit;

$nonce         = wp_create_nonce('ss_admin_nonce');
$managed_roles = SS_Accounts::managed_roles();
$managed_caps  = SS_Accounts::managed_caps();

// Vartotojų skaičius kiekvienai rolei
$role_counts = [];
foreach (array_keys($managed_roles) as $rs) {
    $role_counts[$rs] = count(get_users(['role' => $rs, 'number' => -1, 'fields' => 'ids']));
}
?>
<div class="wrap ss-wrap ss-roles-page">
<?php
$ss_page_title    = 'Rolės ir teisės';
$ss_page_subtitle = 'Tvarkykite rolių galimybes. Individualias teises – per Paskyrų skydelį.';
include SS_PLUGIN_DIR . 'admin/views/partials/header.php';
?>
<div id="ss-roles-notice" class="notice" style="display:none"><p></p></div>

<!-- ═══ ROLIŲ KORTELĖS ═══ -->
<div class="ss-roles-grid">
<?php foreach ($managed_roles as $role_slug => $info):
    $wp_role   = get_role($role_slug);
    $role_caps = $wp_role ? $wp_role->capabilities : [];
    $count     = $role_counts[$role_slug] ?? 0;
    $is_admin  = ($role_slug === 'administrator');
?>
<div class="ss-rolecard<?= $is_admin ? ' is-admin' : '' ?>" data-role="<?= esc_attr($role_slug) ?>" style="--rc:<?= esc_attr($info['color']) ?>;--rcbg:<?= esc_attr($info['bg']) ?>">
  <div class="ss-rolecard-head">
    <span class="ss-rolecard-ico dashicons <?= esc_attr($info['icon']) ?>"></span>
    <div class="ss-rolecard-titlewrap">
      <div class="ss-rolecard-title"><?= esc_html($info['label']) ?></div>
      <div class="ss-rolecard-sub"><code><?= esc_html($role_slug) ?></code> · <?= (int)$count ?> vart.</div>
    </div>
    <span class="ss-rolecard-access"><?= $info['no_admin'] ? '<span class="dashicons dashicons-lock"></span> Be wp-admin' : '<span class="dashicons dashicons-yes-alt"></span> Pilna prieiga' ?></span>
  </div>

  <div class="ss-rolecard-body">
    <?php if (!$is_admin): ?>
    <?php foreach ($managed_caps as $group => $caps): ?>
    <div class="ss-rolecap-group"><?= esc_html($group) ?></div>
    <?php foreach ($caps as $cap_key => $cap_label):
        $checked = isset($role_caps[$cap_key]) && $role_caps[$cap_key];
        $is_read = ($cap_key === 'read');
    ?>
    <label class="ss-rolecap">
      <span class="ss-rolecap-name"><?= esc_html($cap_label) ?><br><code><?= esc_html($cap_key) ?></code></span>
      <span class="ss-switch">
        <input type="checkbox" class="ss-role-cap-cb" data-role="<?= esc_attr($role_slug) ?>"
               value="<?= esc_attr($cap_key) ?>" <?= $checked ? 'checked' : '' ?> <?= $is_read ? 'disabled title="Visada įjungta"' : '' ?>>
        <span class="ss-slider"></span>
      </span>
    </label>
    <?php endforeach; ?>
    <?php endforeach; ?>

    <div class="ss-rolecard-foot">
      <button class="button button-primary ss-save-role-btn" data-role="<?= esc_attr($role_slug) ?>"><span class="dashicons dashicons-database"></span> Išsaugoti</button>
      <span class="ss-role-msg" data-role="<?= esc_attr($role_slug) ?>"></span>
    </div>
    <?php else: ?>
    <p class="ss-roles-hint">Administratoriaus rolės teisių redaguoti negalima — ji turi visišką prieigą prie sistemos.</p>
    <a href="<?= esc_url(admin_url('users.php?role=administrator')) ?>" class="button">Peržiūrėti administratorius <span class="dashicons dashicons-external"></span></a>
    <?php endif; ?>
  </div>
</div>
<?php endforeach; ?>
</div>

<!-- ═══ MOKINYS + IMP ═══ -->
<div class="ss-card ss-roles-impcard">
  <h3 class="ss-roles-cardtitle"><span class="dashicons dashicons-welcome-learn-more"></span> Mokinys + IMP prieiga</h3>
  <p class="ss-roles-hint">
    IMP (individualus mokymosi planas) prieiga — <strong>papildoma teisė</strong> konkrečiai paskyrai, ne visai mokinio rolei.
    Suteikiama per <a href="<?= esc_url(admin_url('admin.php?page=ss-accounts')) ?>">Paskyros</a> → mokinys →
    skirtukas <strong>Rolė ir teisės</strong> → <code>ss_imp_access</code>.
  </p>
  <?php
  $imp_list = [];
  foreach (get_users(['role' => 'ss_student', 'number' => -1]) as $u) {
      if ($u->has_cap('ss_imp_access')) $imp_list[] = $u;
  }
  if ($imp_list): ?>
  <div class="ss-roles-implabel">IMP teisę turi (<?= count($imp_list) ?>):</div>
  <div class="ss-roles-impchips">
    <?php foreach ($imp_list as $u): ?>
    <a href="<?= esc_url(admin_url('admin.php?page=ss-accounts')) ?>#uid=<?= $u->ID ?>" class="ss-roles-impchip"><?= esc_html($u->display_name) ?></a>
    <?php endforeach; ?>
  </div>
  <?php else: ?>
  <p class="ss-roles-hint" style="margin:0">Nė vienas mokinys šiuo metu neturi IMP teisės.</p>
  <?php endif; ?>
</div>

<!-- ═══ GREITAS TEISIŲ VALDYMAS ═══ -->
<div class="ss-card ss-roles-quick">
  <h3 class="ss-roles-cardtitle"><span class="dashicons dashicons-performance"></span> Greitas teisių valdymas</h3>
  <p class="ss-roles-hint">Suteikite arba atimkite papildomą teisę konkrečiam vartotojui (nepriklausomai nuo rolės).</p>

  <div class="ss-roles-quickrow">
    <div class="ss-roles-quickfld">
      <label>Paskyra</label>
      <select id="ss-quick-user">
        <option value="">— Pasirinkite vartotoją —</option>
        <?php
        $role_label_map = ['administrator'=>'Admin','ss_teacher'=>'Mokytojas','ss_employee'=>'Darbuotojas','ss_student'=>'Mokinys'];
        foreach (get_users(['role__in'=>['ss_student','ss_teacher','ss_employee','administrator'],'number'=>-1,'orderby'=>'display_name']) as $u):
            $rl = $role_label_map[$u->roles[0] ?? ''] ?? ($u->roles[0] ?? '—'); ?>
        <option value="<?= $u->ID ?>"><?= esc_html($u->display_name) ?> (<?= esc_html($rl) ?>)</option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="ss-roles-quickfld">
      <label>Teisė</label>
      <select id="ss-quick-cap">
        <?php foreach ($managed_caps as $group => $caps): ?>
        <optgroup label="<?= esc_attr($group) ?>">
          <?php foreach ($caps as $key => $label): ?>
          <option value="<?= esc_attr($key) ?>"><?= esc_html($label) ?></option>
          <?php endforeach; ?>
        </optgroup>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="ss-roles-quickbtns">
      <button class="button button-primary" id="ss-quick-grant"><span class="dashicons dashicons-yes"></span> Suteikti</button>
      <button class="button ss-btn-danger" id="ss-quick-revoke"><span class="dashicons dashicons-no-alt"></span> Atimti</button>
    </div>
  </div>
  <div id="ss-quick-msg" class="ss-roles-quickmsg"></div>

  <div id="ss-user-caps-wrap" class="ss-roles-usercaps" style="display:none">
    <h4 id="ss-user-caps-title">Vartotojo papildomos teisės</h4>
    <div id="ss-user-caps-list" class="ss-roles-impchips"></div>
    <p id="ss-user-caps-empty" class="ss-roles-hint" style="display:none;margin:0">Nėra papildomų teisių (visos iš rolės).</p>
  </div>
</div>

<style>
.ss-roles-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(330px,1fr));gap:18px;margin:20px 0 26px;}
.ss-rolecard{background:var(--sa-surface);border:1px solid var(--sa-border);box-shadow:var(--sa-shadow);overflow:hidden;display:flex;flex-direction:column;border-top:3px solid var(--rc);transition:box-shadow .2s;}
.ss-rolecard:hover{box-shadow:0 6px 24px rgba(1,42,54,.10);}
.ss-rolecard-head{display:flex;align-items:center;gap:12px;padding:16px 18px;border-bottom:1px solid var(--sa-border);background:var(--rcbg);}
.ss-rolecard-ico{font-size:24px;line-height:1;width:42px;height:42px;display:flex;align-items:center;justify-content:center;background:var(--sa-white);flex-shrink:0;box-shadow:0 1px 4px rgba(0,0,0,.08);}
.ss-rolecard-titlewrap{flex:1;min-width:0;}
.ss-rolecard-title{font-size:15px;font-weight:800;color:var(--rc);}
.ss-rolecard-sub{font-size:11px;color:var(--sa-dark);opacity:.65;margin-top:2px;}
.ss-rolecard-sub code{background:none;padding:0;font-size:11px;}
.ss-rolecard-access{font-size:10.5px;font-weight:700;padding:4px 10px;background:var(--sa-white);color:var(--rc);white-space:nowrap;flex-shrink:0;}
.ss-rolecard-body{padding:14px 18px 18px;flex:1;}
.ss-rolecap-group{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--sa-green-pale);margin:14px 0 6px;}
.ss-rolecap-group:first-child{margin-top:0;}
.ss-rolecap{display:flex;align-items:center;gap:10px;padding:7px 8px;cursor:pointer;transition:background .1s;}
.ss-rolecap:hover{background:var(--sa-bg);}
.ss-rolecap-name{flex:1;font-size:12.5px;color:var(--sa-dark);line-height:1.4;}
.ss-rolecap-name code{font-size:10px;color:var(--sa-green-pale);font-family:monospace;background:none;padding:0;}
.ss-rolecard-foot{margin-top:16px;display:flex;align-items:center;gap:12px;padding-top:14px;border-top:1px solid var(--sa-border);}
.ss-role-msg{font-size:12px;font-weight:600;}
.ss-rolecard.is-admin .ss-rolecard-body{display:flex;flex-direction:column;gap:12px;align-items:flex-start;}
.ss-roles-cardtitle{margin:0 0 6px;font-size:15px;font-weight:700;color:var(--sa-dark);}
.ss-roles-hint{font-size:13px;color:var(--sa-green-lt);line-height:1.6;margin:0 0 14px;}
.ss-roles-hint code{font-size:12px;background:var(--sa-bg);padding:1px 6px;}
.ss-roles-implabel{font-size:12px;font-weight:700;color:var(--sa-green-lt);margin-bottom:8px;}
.ss-roles-impchips{display:flex;flex-wrap:wrap;gap:7px;}
.ss-roles-impchip{background:#E5F4E8;color:var(--sa-forest);padding:4px 13px;font-size:12.5px;text-decoration:none;border:1px solid #BFE3C6;transition:.15s;}
.ss-roles-impchip:hover{background:#d3edd8;color:var(--sa-forest);}
.ss-roles-quickrow{display:flex;gap:14px;flex-wrap:wrap;align-items:flex-end;}
.ss-roles-quickfld{flex:1;min-width:200px;}
.ss-roles-quickfld label{display:block;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.4px;color:var(--sa-green-lt);margin-bottom:6px;}
.ss-roles-quickfld select{width:100%;border:1.5px solid var(--sa-border);padding:8px 12px;font-size:13px;color:var(--sa-dark);background:var(--sa-white);outline:none;font-family:inherit;}
.ss-roles-quickfld select:focus{border-color:var(--sa-green);box-shadow:0 0 0 3px rgba(79,137,82,.14);}
.ss-roles-quickbtns{display:flex;gap:8px;}
.ss-roles-quickmsg{margin-top:12px;font-size:13px;min-height:20px;font-weight:600;}
.ss-roles-usercaps{margin-top:20px;padding-top:16px;border-top:1px solid var(--sa-border);}
.ss-roles-usercaps h4{margin:0 0 10px;font-size:13px;font-weight:700;color:var(--sa-dark);}
.ss-rolecard-ico.dashicons{font-size:24px;width:24px;height:24px;}
.ss-rolecard-access .dashicons{font-size:13px;width:13px;height:13px;vertical-align:text-bottom;}
.ss-roles-cardtitle .dashicons{font-size:18px;width:18px;height:18px;vertical-align:text-bottom;color:var(--sa-green);}
.ss-roles-page .button .dashicons{font-size:16px;width:16px;height:16px;line-height:1.5;vertical-align:text-bottom;}
.ss-role-msg .dashicons,.ss-roles-quickmsg .dashicons{font-size:14px;width:14px;height:14px;vertical-align:text-bottom;}
</style>

<script>
(function($){
var nonce='<?= esc_js($nonce) ?>';
function dico(n){return '<span class="dashicons dashicons-'+n+'"></span>';}
function escHtml(s){return String(s==null?'':s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');}

function showNotice(msg,type){
  var $n=$('#ss-roles-notice');
  $n.removeClass('notice-success notice-error').addClass('notice-'+(type||'success')).find('p').text(msg);
  $n.show(); setTimeout(function(){$n.fadeOut();},4000);
}

/* Išsaugoti rolės teises */
$(document).on('click','.ss-save-role-btn',function(){
  var role=$(this).data('role'), caps=[];
  $('.ss-role-cap-cb[data-role="'+role+'"]:checked').each(function(){caps.push($(this).val());});
  var $btn=$(this).prop('disabled',true).text('Saugoma…');
  $.post(ajaxurl,{action:'ss_admin_save_role_caps',nonce:nonce,role:role,caps:caps},function(res){
    $btn.prop('disabled',false).html(dico('database')+' Išsaugoti');
    var $msg=$('.ss-role-msg[data-role="'+role+'"]');
    if(res.success){$msg.html(dico('yes-alt')+' Išsaugota!').css('color','var(--sa-forest)');setTimeout(function(){$msg.empty();},3000);}
    else $msg.html(dico('warning')+' '+escHtml(res.data.message||'Klaida')).css('color','#C00');
  });
});

/* Greitas valdymas */
function setQuickMsg(msg,ok){$('#ss-quick-msg').html(dico(ok?'yes-alt':'warning')+' '+escHtml(msg)).css('color',ok?'var(--sa-forest)':'#C00');setTimeout(function(){$('#ss-quick-msg').empty();},3500);}

function loadUserExtraCaps(uid){
  if(!uid){$('#ss-user-caps-wrap').hide();return;}
  $('#ss-user-caps-title').text('Papildomos teisės: '+$('#ss-quick-user option:selected').text());
  $.post(ajaxurl,{action:'ss_admin_get_account_detail',nonce:nonce,user_id:uid},function(res){
    if(!res.success) return;
    var extra=res.data.detail.extra_caps||{}, individual=[];
    Object.keys(extra).forEach(function(cap){if(extra[cap])individual.push(cap);});
    var $list=$('#ss-user-caps-list').empty(), $empty=$('#ss-user-caps-empty');
    if(individual.length){$empty.hide();individual.forEach(function(cap){$list.append('<span class="ss-roles-impchip">'+cap+'</span>');});}
    else $empty.show();
    $('#ss-user-caps-wrap').show();
  });
}
$('#ss-quick-user').on('change',function(){loadUserExtraCaps($(this).val());});

function changeCap(grant){
  var uid=$('#ss-quick-user').val(), cap=$('#ss-quick-cap').val();
  if(!uid||!cap){setQuickMsg('Pasirinkite vartotoją ir teisę.',false);return;}
  $.post(ajaxurl,{action:'ss_admin_get_account_detail',nonce:nonce,user_id:uid},function(res){
    if(!res.success){setQuickMsg(res.data.message,false);return;}
    var current=res.data.detail.extra_caps||{}, active=[];
    Object.keys(current).forEach(function(c){if(current[c]&&(grant||c!==cap))active.push(c);});
    if(grant&&active.indexOf(cap)===-1)active.push(cap);
    $.post(ajaxurl,{action:'ss_admin_set_user_caps',nonce:nonce,user_id:uid,caps:active},function(r2){
      if(r2.success){setQuickMsg((grant?'Teisė suteikta: ':'Teisė atimta: ')+cap,true);loadUserExtraCaps(uid);}
      else setQuickMsg(r2.data.message,false);
    });
  });
}
$('#ss-quick-grant').on('click',function(){changeCap(true);});
$('#ss-quick-revoke').on('click',function(){changeCap(false);});

})(jQuery);
</script>
