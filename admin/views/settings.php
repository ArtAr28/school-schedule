<?php
if (!defined('ABSPATH')) exit;
$login_img_id  = (int)get_option('ss_login_image_id', 0);
$login_img_url = $login_img_id ? wp_get_attachment_image_url($login_img_id, 'medium') : '';
wp_enqueue_media();
?>
<div class="wrap ss-wrap">
<?php
$ss_page_title    = 'Sistemos nustatymai';
$ss_page_subtitle = 'Bendrieji nustatymai, puslapiai, pamokų laikai.';
include SS_PLUGIN_DIR . 'admin/views/partials/header.php';
?>
<form method="post" style="max-width:840px">
<?php wp_nonce_field('ss_settings','ss_settings_nonce') ?>

<div class="ss-card">
  <h2>Pagrindiniai</h2>
  <table class="form-table"><tbody>
    <tr><th scope="row">Administratoriaus el. paštas</th>
      <td>
        <input type="email" name="admin_email" class="regular-text"
               value="<?= esc_attr(get_option('ss_admin_email', get_option('admin_email'))) ?>">
        <p class="description">Šiuo adresu bus siunčiami pranešimai apie naujas registracijas.</p>
      </td>
    </tr>
    <tr><th scope="row">Mokyklos pavadinimas</th>
      <td><input type="text" name="school_name" class="regular-text" value="<?= esc_attr(get_option('ss_school_name',SS_SCHOOL_NAME)) ?>"></td></tr>
    <tr><th scope="row">Tvarkaraščio pavadinimas (PDF)</th>
      <td><input type="text" name="schedule_title" class="regular-text" value="<?= esc_attr(get_option('ss_schedule_title','Savaitės tvarkaraštis')) ?>">
        <p class="description">Rodomas sugeneruotame PDF faile po mokyklos pavadinimu.</p></td></tr>
    <tr><th scope="row">Mokslo metai</th>
      <td><input type="text" name="school_year" class="regular-text" value="<?= esc_attr(get_option('ss_school_year', SS_Time::date('Y').'–'.((int)SS_Time::date('Y')+1))) ?>"></td></tr>
    <tr><th scope="row">Prisijungimo puslapis</th>
      <td><select name="login_page" class="regular-text">
        <option value="">— Nepasirinkta —</option>
        <?php foreach($pages as $p): ?><option value="<?= $p->ID ?>" <?= selected(get_option('ss_login_page_id'),$p->ID,false) ?>><?= esc_html($p->post_title) ?></option><?php endforeach; ?>
      </select><p class="description">Puslapis su <code>[ss_login]</code> kodu.</p></td></tr>
    <tr><th scope="row">Tvarkaraščio puslapis</th>
      <td><select name="dashboard_page" class="regular-text">
        <option value="">— Nepasirinkta —</option>
        <?php foreach($pages as $p): ?><option value="<?= $p->ID ?>" <?= selected(get_option('ss_dashboard_page_id'),$p->ID,false) ?>><?= esc_html($p->post_title) ?></option><?php endforeach; ?>
      </select><p class="description">Puslapis su <code>[ss_dashboard]</code> kodu.</p></td></tr>
    <tr><th scope="row">Pakeitimų puslapis</th>
      <td><select name="changes_page" class="regular-text">
        <option value="">— Nepasirinkta —</option>
        <?php foreach($pages as $p): ?><option value="<?= $p->ID ?>" <?= selected(get_option('ss_changes_page_id'),$p->ID,false) ?>><?= esc_html($p->post_title) ?></option><?php endforeach; ?>
      </select><p class="description">Puslapis su <code>[ss_changes]</code> kodu.</p></td></tr>
    <tr><th scope="row">Nustatymų puslapis</th>
      <td><select name="settings_page" class="regular-text">
        <option value="">— Nepasirinkta —</option>
        <?php foreach($pages as $p): ?><option value="<?= $p->ID ?>" <?= selected(get_option('ss_settings_page_id'),$p->ID,false) ?>><?= esc_html($p->post_title) ?></option><?php endforeach; ?>
      </select><p class="description">Tuščias puslapis (kodo nereikia) — čia bus nustatymų puslapis.</p></td></tr>
    <tr><th scope="row">Individualaus plano puslapis</th>
      <td><select name="imp_page" class="regular-text">
        <option value="">— Nepasirinkta —</option>
        <?php foreach($pages as $p): ?><option value="<?= $p->ID ?>" <?= selected(get_option('ss_imp_page_id'),$p->ID,false) ?>><?= esc_html($p->post_title) ?></option><?php endforeach; ?>
      </select><p class="description">Puslapis su <code>[ss_imp_form]</code> kodu — individualaus ugdymo plano (IMP) pateikimui.</p></td></tr>
    <tr><th scope="row">„Ąžuolyno Eurovizija" puslapis</th>
      <td><select name="euro_page" class="regular-text">
        <option value="">— Nepasirinkta —</option>
        <?php foreach($pages as $p): ?><option value="<?= $p->ID ?>" <?= selected(get_option('ss_euro_page_id'),$p->ID,false) ?>><?= esc_html($p->post_title) ?></option><?php endforeach; ?>
      </select><p class="description">Tuščias puslapis (kodo nereikia) — čia bus „Ąžuolyno Eurovizija" balsavimas. Konfigūruojama skiltyje <strong>Tvarkaraštis → Eurovizija</strong>.</p></td></tr>
    <tr><th scope="row">Profilio puslapis</th>
      <td><select name="profile_page" class="regular-text">
        <option value="">— Nepasirinkta —</option>
        <?php foreach($pages as $p): ?><option value="<?= $p->ID ?>" <?= selected(get_option('ss_profile_page_id'),$p->ID,false) ?>><?= esc_html($p->post_title) ?></option><?php endforeach; ?>
      </select><p class="description">Tuščias puslapis (kodo nereikia) — vartotojo profilis: avataras, pasiekimai, draugai. Kitą profilį atveria <code>?u=ID</code>.</p></td></tr>
    <tr><th scope="row">Žinučių puslapis</th>
      <td><select name="messages_page" class="regular-text">
        <option value="">— Nepasirinkta —</option>
        <?php foreach($pages as $p): ?><option value="<?= $p->ID ?>" <?= selected(get_option('ss_messages_page_id'),$p->ID,false) ?>><?= esc_html($p->post_title) ?></option><?php endforeach; ?>
      </select><p class="description">Tuščias puslapis (kodo nereikia) — asmeniniai ir grupiniai pokalbiai tarp draugų.</p></td></tr>
  </tbody></table>
</div>

<div class="ss-card">
  <h2>Mokyklos logotipas</h2>
  <input type="hidden" name="logo_url" id="ss-logo-url" value="<?= esc_attr(get_option('ss_logo_url','')) ?>">
  <input type="hidden" name="logo_id"  id="ss-logo-id"  value="<?= esc_attr(get_option('ss_logo_id', '')) ?>">
  <div id="ss-logo-preview" style="margin-bottom:12px;<?= get_option('ss_logo_url') ? '' : 'display:none;' ?>">
    <img id="ss-logo-img" src="<?= esc_url(get_option('ss_logo_url','')) ?>" alt="" style="max-height:60px;width:auto;border:1px solid #ddd;display:block;">
  </div>
  <button type="button" class="button" id="ss-logo-pick">Pasirinkti / Pakeisti logotipą</button>
  <button type="button" class="button" id="ss-logo-remove" style="<?= get_option('ss_logo_url') ? '' : 'display:none;' ?>">Pašalinti logotipą</button>
</div>

<div class="ss-card">
  <h2>Prisijungimo puslapio nuotrauka</h2>
  <p class="description" style="margin-bottom:14px;">Pasirenkama. Jei įkelsite nuotrauką – ji bus rodoma dešinėje prisijungimo puslapio pusėje. Jei nuotraukos nebus, prisijungimo forma bus centruota per visą ekraną.</p>
  <input type="hidden" name="login_image_id" id="ss-login-image-id" value="<?= esc_attr($login_img_id) ?>">
  <div id="ss-login-image-preview" style="margin-bottom:12px;<?= $login_img_url ? '' : 'display:none;' ?>">
    <img id="ss-login-image-img" src="<?= esc_url($login_img_url) ?>" alt="" style="max-width:300px;height:auto;border:1px solid #ddd;display:block;">
  </div>
  <button type="button" class="button" id="ss-login-image-pick">Pasirinkti / Pakeisti nuotrauką</button>
  <button type="button" class="button" id="ss-login-image-remove" style="<?= $login_img_url ? '' : 'display:none;' ?>">Pašalinti nuotrauką</button>
</div>

<div class="ss-card">
  <h2>Pamokų laikas</h2>
  <p class="description" style="margin-bottom:12px">Nustatykite kiekvienos pamokos pradžios ir pabaigos laiką. Šie laikai rodomi mokinių tvarkaraštyje.</p>
  <table class="ss-periods-table">
    <thead><tr><th>#</th><th>Pradžia</th><th>Pabaiga</th><th></th></tr></thead>
    <tbody id="ss-periods-body">
      <?php foreach ($periods as $i => $p): ?>
      <tr class="ss-period-row">
        <td><strong><?= (int)$p->period_number ?> pamoka</strong></td>
        <td><input type="time" lang="lt" name="periods[<?= (int)$i ?>][start_time]" value="<?= esc_attr(substr($p->start_time,0,5)) ?>" class="small-text"></td>
        <td><input type="time" lang="lt" name="periods[<?= (int)$i ?>][end_time]"   value="<?= esc_attr(substr($p->end_time,0,5)) ?>"   class="small-text"></td>
        <td><button type="button" class="button button-small ss-remove-period" style="color:var(--sa-red)"><span class="dashicons dashicons-no-alt" style="font-size:16px;width:16px;height:16px;vertical-align:text-bottom"></span></button></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <button type="button" class="button" id="ss-add-period" style="margin-top:10px">+ Pridėti pamokos laiką</button>
</div>

<?php
$atspek_img_id  = (int)get_option('ss_atspek_image_id', 0);
$atspek_img_url = $atspek_img_id ? wp_get_attachment_image_url($atspek_img_id, 'medium') : '';
?>
<div class="ss-card">
  <h2>ĄTSPĖK reklama</h2>
  <p class="description" style="margin-bottom:14px;">Reklama bus rodoma tvarkaraščių ir pakeitimų puslapiuose. Užpildykite laukus žemiau – jei nuoroda tuščia, juosta nerodoma.</p>
  <table class="form-table"><tbody>
    <tr><th scope="row">Žaidimo nuoroda</th>
      <td>
        <input type="url" name="atspek_game_url" class="regular-text" placeholder="https://..." value="<?= esc_attr(get_option('ss_atspek_game_url', '')) ?>">
        <p class="description">Mygtuko ŽAISTI nukreipimo adresas.</p>
      </td>
    </tr>
    <tr><th scope="row">Aprašymas</th>
      <td>
        <input type="text" name="atspek_game_description" class="regular-text" placeholder="Pvz.: Atspėk dienos žodį lietuviškai!" value="<?= esc_attr(get_option('ss_atspek_game_description', '')) ?>">
        <p class="description">Trumpas tekstas po žaidimo pavadinimu.</p>
      </td>
    </tr>
    <tr><th scope="row">Fono nuotrauka</th>
      <td>
        <input type="hidden" name="atspek_image_id" id="ss-atspek-image-id" value="<?= esc_attr($atspek_img_id) ?>">
        <div id="ss-atspek-image-preview" style="margin-bottom:10px;<?= $atspek_img_url ? '' : 'display:none;' ?>">
          <img id="ss-atspek-image-img" src="<?= esc_url($atspek_img_url) ?>" alt="" style="max-width:280px;height:auto;border:1px solid #ddd;display:block;">
        </div>
        <button type="button" class="button" id="ss-atspek-image-pick">Pasirinkti / Pakeisti nuotrauką</button>
        <button type="button" class="button" id="ss-atspek-image-remove" style="<?= $atspek_img_url ? '' : 'display:none;' ?>">Pašalinti</button>
        <p class="description">Rodoma kaip fono nuotrauka juostoje. Rekomenduojamas dydis: ~800×300 px.</p>
      </td>
    </tr>
  </tbody></table>
</div>

<div class="ss-card">
  <h2>QR kodo prisijungimas</h2>
  <p class="description" style="margin-bottom:16px;">Leidžia vartotojams prisijungti prie paskyros nuskenuojant QR kodą iš kito įrenginio, kuriame jie jau yra prisijungę.</p>
  <table class="form-table"><tbody>
    <tr>
      <th scope="row">QR prisijungimas</th>
      <td>
        <label>
          <input type="checkbox" name="qr_login_enabled" value="1" <?= checked(get_option('ss_qr_login_enabled',0),1,false) ?>>
          Įjungti QR kodo prisijungimą
        </label>
        <p class="description" style="margin-top:6px;">Prisijungimo puslapyje atsiras mygtukas <em>„Prisijungti per QR kodą"</em>. Vartotojas nuskenavęs QR kodu jau prisijungusiame telefone patvirtina prisijungimą.</p>
      </td>
    </tr>
  </tbody></table>
</div>

<div class="ss-card">
  <h2>Google prisijungimas</h2>
  <p class="description" style="margin-bottom:16px;">Leidžia vartotojams prisijungti prie paskyros per Google. Vartotojas turi iš anksto susieti Google paskyrą savo nustatymuose.</p>
  <table class="form-table"><tbody>
    <tr>
      <th scope="row">Google prisijungimas</th>
      <td>
        <label>
          <input type="checkbox" name="google_login_enabled" value="1" <?= checked(get_option('ss_google_login_enabled',0),1,false) ?>>
          Įjungti prisijungimą per Google
        </label>
        <p class="description" style="margin-top:6px;">Prisijungimo puslapyje atsiras mygtukas <em>„Prisijungti per Google"</em>.</p>
      </td>
    </tr>
    <tr>
      <th scope="row">Client ID</th>
      <td>
        <input type="text" name="google_client_id"
               value="<?= esc_attr(get_option('ss_google_client_id','')) ?>"
               class="regular-text" placeholder="xxxxxxxxxx.apps.googleusercontent.com">
        <p class="description">Google Cloud Console → Credentials → OAuth 2.0 Client ID.</p>
      </td>
    </tr>
    <tr>
      <th scope="row">Client Secret</th>
      <td>
        <input type="password" name="google_client_secret"
               value=""
               placeholder="<?= get_option('ss_google_client_secret') ? '(nustatytas — palikite tuščią jei nekeičiate)' : 'Įklijuokite Client Secret' ?>"
               class="regular-text" autocomplete="new-password">
        <p class="description">Google Cloud Console → Credentials → Client Secret. <?php if(get_option('ss_google_client_secret')): ?><span style="color:#2ea44f"><span class="dashicons dashicons-yes" style="font-size:15px;width:15px;height:15px;vertical-align:text-bottom"></span> Sekretas nustatytas</span><?php endif; ?></p>
      </td>
    </tr>
    <tr>
      <th scope="row">Redirect URI</th>
      <td>
        <div style="display:flex;align-items:stretch;gap:0;max-width:520px;">
          <input type="text" id="ss-google-cb-url" readonly
                 value="<?= esc_attr(SS_Google_Auth::get_callback_url()) ?>"
                 style="flex:1;font-family:monospace;font-size:12px;background:#f0f4f8;border:1.5px solid #c5d3de;border-right:none;padding:7px 10px;color:#1a3a6b;min-width:0;">
          <button type="button" id="ss-google-copy-btn"
                  onclick="var el=document.getElementById('ss-google-cb-url');el.select();try{navigator.clipboard.writeText(el.value).then(function(){var b=document.getElementById('ss-google-copy-btn');b.textContent='Nukopijuota';b.style.background='var(--sa-green)';b.style.color='var(--sa-white)';setTimeout(function(){b.textContent='Kopijuoti';b.style.background='';b.style.color='';},2500);});}catch(e){document.execCommand('copy');}"
                  style="padding:7px 14px;border:1.5px solid #c5d3de;background:var(--sa-white);cursor:pointer;font-size:13px;font-weight:600;white-space:nowrap;flex-shrink:0;transition:all .2s;">Kopijuoti</button>
        </div>
      </td>
    </tr>
  </tbody></table>
</div>

<div class="ss-card">
  <h2>Aptarnavimo režimas</h2>
  <p class="description">Perspėjimo juosta, sistemos uždarymas ir prieigos blokavimai perkelti į atskirą puslapį:
    <a href="<?= esc_url(admin_url('admin.php?page=ss-maintenance')) ?>"><strong>Tvarkaraštis → Aptarnavimas</strong></a>.
  </p>
</div>

<div class="ss-card">
  <h2>Specialūs kodai</h2>
  <table class="widefat"><tbody>
    <tr><td><code>[ss_login]</code></td><td>Prisijungimas</td></tr>
    <tr><td><code>[ss_dashboard]</code></td><td>Tvarkaraštis</td></tr>
    <tr><td><code>[ss_changes]</code></td><td>Pakeitimai</td></tr>
  </tbody></table>
</div>

<p class="submit"><input type="submit" class="button button-primary button-large" value="Išsaugoti nustatymus"></p>
</form>
</div>

<script>
(function($){
  var i = <?= count($periods) ?>;
  document.getElementById('ss-add-period').addEventListener('click', function(){
    var tr = document.createElement('tr');
    tr.className = 'ss-period-row';
    tr.innerHTML = '<td><strong>'+(i+1)+' pamoka</strong></td><td><input type="time" name="periods['+i+'][start_time]" class="small-text"></td><td><input type="time" name="periods['+i+'][end_time]" class="small-text"></td><td><button type="button" class="button button-small ss-remove-period" style="color:var(--sa-red)"><span class="dashicons dashicons-no-alt" style="font-size:16px;width:16px;height:16px;vertical-align:text-bottom"></span></button></td>';
    document.getElementById('ss-periods-body').appendChild(tr);
    i++;
  });
  document.addEventListener('click', function(e){
    if (e.target.classList.contains('ss-remove-period')) {
      if (confirm('Pašalinti šios pamokos laiką?')) e.target.closest('tr').remove();
    }
  });

  // Logo media picker
  var logoFrame;
  $(document).on('click', '#ss-logo-pick', function(e){
    e.preventDefault();
    if (logoFrame) { logoFrame.open(); return; }
    logoFrame = wp.media({
      title: 'Pasirinkite logotipą',
      button: { text: 'Naudoti šį logotipą' },
      library: { type: 'image' },
      multiple: false
    });
    logoFrame.on('select', function(){
      var att = logoFrame.state().get('selection').first().toJSON();
      var url = att.sizes && att.sizes.medium ? att.sizes.medium.url : att.url;
      $('#ss-logo-url').val(url);
      $('#ss-logo-id').val(att.id);
      $('#ss-logo-img').attr('src', url);
      $('#ss-logo-preview').show();
      $('#ss-logo-remove').show();
    });
    logoFrame.open();
  });
  $(document).on('click', '#ss-logo-remove', function(e){
    e.preventDefault();
    $('#ss-logo-url').val('');
    $('#ss-logo-id').val('');
    $('#ss-logo-img').attr('src', '');
    $('#ss-logo-preview').hide();
    $(this).hide();
  });

  // Login image media picker
  var frame;
  $(document).on('click', '#ss-login-image-pick', function(e){
    e.preventDefault();
    if (frame) { frame.open(); return; }
    frame = wp.media({
      title: 'Pasirinkite prisijungimo nuotrauką',
      button: { text: 'Naudoti šią nuotrauką' },
      library: { type: 'image' },
      multiple: false
    });
    frame.on('select', function(){
      var att = frame.state().get('selection').first().toJSON();
      $('#ss-login-image-id').val(att.id);
      $('#ss-login-image-img').attr('src', att.sizes && att.sizes.medium ? att.sizes.medium.url : att.url);
      $('#ss-login-image-preview').show();
      $('#ss-login-image-remove').show();
    });
    frame.open();
  });
  $(document).on('click', '#ss-login-image-remove', function(e){
    e.preventDefault();
    $('#ss-login-image-id').val('');
    $('#ss-login-image-img').attr('src', '');
    $('#ss-login-image-preview').hide();
    $(this).hide();
  });

  // Ątspės banner image picker
  var atspekFrame;
  $(document).on('click', '#ss-atspek-image-pick', function(e){
    e.preventDefault();
    if (atspekFrame) { atspekFrame.open(); return; }
    atspekFrame = wp.media({
      title: 'Pasirinkite reklamos fono nuotrauką',
      button: { text: 'Naudoti šią nuotrauką' },
      library: { type: 'image' },
      multiple: false
    });
    atspekFrame.on('select', function(){
      var att = atspekFrame.state().get('selection').first().toJSON();
      $('#ss-atspek-image-id').val(att.id);
      $('#ss-atspek-image-img').attr('src', att.sizes && att.sizes.medium ? att.sizes.medium.url : att.url);
      $('#ss-atspek-image-preview').show();
      $('#ss-atspek-image-remove').show();
    });
    atspekFrame.open();
  });
  $(document).on('click', '#ss-atspek-image-remove', function(e){
    e.preventDefault();
    $('#ss-atspek-image-id').val('');
    $('#ss-atspek-image-img').attr('src', '');
    $('#ss-atspek-image-preview').hide();
    $(this).hide();
  });
})(jQuery);
</script>

<?php include SS_PLUGIN_DIR . 'admin/views/partials/system-io.php'; ?>
