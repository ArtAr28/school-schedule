<?php
if (!defined('ABSPATH')) exit;

// Convert maintenance times from UTC (storage) to WP timezone for display
$site_tz        = SS_Time::tz();
$maint_from_raw = get_option('ss_maint_from', '');
$maint_to_raw   = get_option('ss_maint_to', '');

if ($maint_from_raw) {
    $dt = DateTime::createFromFormat('Y-m-d H:i:s', $maint_from_raw, new DateTimeZone('UTC'));
    $maint_from_lt = $dt ? $dt->setTimezone($site_tz)->format('Y-m-d\TH:i') : '';
} else {
    $maint_from_lt = '';
}
if ($maint_to_raw) {
    $dt = DateTime::createFromFormat('Y-m-d H:i:s', $maint_to_raw, new DateTimeZone('UTC'));
    $maint_to_lt = $dt ? $dt->setTimezone($site_tz)->format('Y-m-d\TH:i') : '';
} else {
    $maint_to_lt = '';
}

$maint_status = SS_Maintenance::get_status();
?>
<div class="wrap ss-wrap">
<?php
$ss_page_title    = 'Aptarnavimo režimas';
$ss_page_subtitle = 'Sistemos uždarymas, perspėjimai, prieigos blokavimai.';
include SS_PLUGIN_DIR . 'admin/views/partials/header.php';
?>

<?php if ($maint_status['active']): ?>
<div style="padding:14px 16px;background:#FFE9E9;border:1px solid #F4C7C9;margin:16px 0">
  <strong style="font-size:13px;color:#9F2226"><span class="dashicons dashicons-warning" style="font-size:15px;width:15px;height:15px;vertical-align:text-bottom"></span> Sistema šiuo metu UŽDARYTA<?= !empty($maint_status['site_locked']) ? ' (užblokuota VISA svetainė)' : '' ?>.</strong>
  <span style="font-size:12px;color:#9F2226">Neprisijungę / neleisti vartotojai mato uždarymo ekraną. Administratoriai visada turi prieigą.</span>
</div>
<?php elseif (get_option('ss_maint_enabled',0) && get_option('ss_maint_type')==='timed'): ?>
<div style="padding:12px 16px;background:#E7EEF7;border:1px solid #B9CCE4;margin:16px 0;font-size:12px;color:#224768">
  <strong>Uždarymas pagal laiką įjungtas, bet langas šiuo metu neaktyvus.</strong>
  Nustatyta (jūsų laiku): nuo <strong><?= $maint_from_lt ? esc_html(str_replace('T',' ',$maint_from_lt)) : 'iškart' ?></strong>
  iki <strong><?= $maint_to_lt ? esc_html(str_replace('T',' ',$maint_to_lt)) : 'kol išjungsite' ?></strong>.
  Sistema užsidarys automatiškai atėjus „Nuo" laikui.
</div>
<?php endif; ?>

<form method="post" style="max-width:840px">
<?php wp_nonce_field('ss_maintenance','ss_maintenance_nonce') ?>

<div class="ss-card">
  <h2>Perspėjimo juosta</h2>
  <table class="form-table"><tbody>
    <tr><th scope="row">Perspėjimas</th>
      <td>
        <label>
          <input type="checkbox" name="maint_warning" value="1" <?= checked(get_option('ss_maint_warning',0),1,false) ?>>
          Rodyti perspėjimo juostą visuose puslapiuose
        </label>
        <div style="margin-top:8px;">
          <input type="text" name="maint_warning_text" class="large-text"
            placeholder="Perspėjimas: sistema bus laikinai uždaryta techninių darbų metu."
            value="<?= esc_attr(get_option('ss_maint_warning_text','')) ?>">
          <p class="description">Tekstas perspėjimo juostoje. Palikite tuščią, kad būtų rodomas numatytasis tekstas.</p>
        </div>
      </td>
    </tr>
  </tbody></table>
</div>

<div class="ss-card">
  <h2>Sistemos uždarymas</h2>
  <p class="description" style="margin-bottom:16px;">Kai sistema uždaryta, veikia tik prisijungimas (registracija stabdoma) — visi kiti plėtinio puslapiai rodo uždarymo ekraną.</p>
  <table class="form-table"><tbody>
    <tr><th scope="row">Būsena</th>
      <td>
        <?php $maint_type = get_option('ss_maint_type','indefinite'); ?>
        <fieldset>
          <label style="display:block;margin-bottom:6px;">
            <input type="radio" name="maint_type" value="off" <?= checked(!get_option('ss_maint_enabled',0), true, false) ?>>
            Sistema atidaryta (normalus veikimas)
          </label>
          <label style="display:block;margin-bottom:6px;">
            <input type="radio" name="maint_type" value="indefinite" <?= checked(get_option('ss_maint_enabled',0) && $maint_type==='indefinite',true,false) ?>>
            Uždaryti neribotam laikui <span style="color:#888;font-size:12px;">(reikia rankiniu būdu atidaryti)</span>
          </label>
          <label style="display:block;">
            <input type="radio" name="maint_type" value="timed" <?= checked(get_option('ss_maint_enabled',0) && $maint_type==='timed',true,false) ?>>
            Uždaryti nustatytam laikotarpiui
          </label>
        </fieldset>
        <div id="ss-maint-timed-fields" style="margin-top:10px;padding:12px 16px;background:#f9f9f9;border:1px solid #ddd;<?= (get_option('ss_maint_enabled',0) && $maint_type==='timed') ? '' : 'display:none;' ?>">
          <label style="display:block;margin-bottom:8px;">
            <span style="display:inline-block;width:60px;font-size:13px;">Nuo:</span>
            <input type="datetime-local" name="maint_from" lang="lt" value="<?= esc_attr($maint_from_lt) ?>">
          </label>
          <label style="display:block;">
            <span style="display:inline-block;width:60px;font-size:13px;">Iki:</span>
            <input type="datetime-local" name="maint_to" lang="lt" value="<?= esc_attr($maint_to_lt) ?>">
          </label>
        </div>
      </td>
    </tr>

    <tr><th scope="row">Uždarymo priežastis</th>
      <td>
        <input type="text" name="maint_reason" class="large-text"
          placeholder="pvz.: Planuojami techniniai darbai"
          value="<?= esc_attr(get_option('ss_maint_reason','')) ?>">
        <p class="description">Rodoma uždaryto puslapio informaciniame lange.</p>
      </td>
    </tr>

    <tr><th scope="row">Leidžiami vartotojai</th>
      <td>
        <?php
        $bypass_raw = get_option('ss_maint_bypass_users','');
        $bypass_ids = $bypass_raw !== '' ? array_filter(array_map('intval', explode(',', $bypass_raw))) : [];
        $all_users  = get_users(array('role__in' => array('ss_student','ss_teacher','ss_employee','administrator'), 'number' => 1000, 'orderby' => 'display_name'));
        ?>
        <input type="text" id="ss-bypass-search" class="regular-text"
               placeholder="Ieškoti pagal vardą arba el. paštą…"
               autocomplete="off" style="display:block;min-width:280px;margin-bottom:6px;">
        <select name="maint_bypass_users[]" id="ss-bypass-select" multiple size="8" style="min-width:280px;">
          <?php foreach ($all_users as $u): ?>
            <option value="<?= $u->ID ?>" <?= in_array($u->ID, $bypass_ids, true) ? 'selected' : '' ?>>
              <?= esc_html($u->display_name) ?> (<?= esc_html($u->user_email) ?>)
            </option>
          <?php endforeach; ?>
        </select>
        <p class="description" id="ss-bypass-count" style="margin-top:4px;"></p>
        <p class="description">Laikykite <kbd>Ctrl</kbd> (arba <kbd>⌘</kbd>) norėdami pasirinkti kelis. Paieška tik filtruoja sąrašą — jau pažymėti vartotojai lieka pažymėti. Administratoriai visada turi prieigą.</p>
      </td>
    </tr>
  </tbody></table>
</div>

<div class="ss-card">
  <h2>Prieigos blokavimai</h2>
  <p class="description" style="margin-bottom:16px;">
    <strong>Veikia tik tada, kai įjungtas sistemos uždarymas</strong> (neribotam laikui arba nustatytu laikotarpiu — jo metu).
    Sistemai atsidarius, blokavimai automatiškai nustoja galioti — jungiklių išjunginėti nereikia.
  </p>
  <?php if (!$maint_status['active']): ?>
  <div style="padding:10px 14px;background:#FFF4D6;border:1px solid #E8D68A;margin-bottom:14px;font-size:12px;color:#8A6A1F">
    Sistema šiuo metu <strong>atidaryta</strong>, todėl žemiau pažymėti blokavimai kol kas <strong>neveikia</strong>. Jie įsigalios, kai įjungsite sistemos uždarymą.
  </div>
  <?php endif; ?>
  <table class="form-table"><tbody>
    <tr><th scope="row">Visa svetainė</th>
      <td>
        <label>
          <input type="checkbox" name="maint_lock_site" value="1" <?= checked(get_option('ss_maint_lock_site',0),1,false) ?>>
          Blokuoti <strong>visą svetainę</strong> (išskyrus prisijungimą)
        </label>
        <p class="description">Uždaromi VISI puslapiai — ir temos, ir plėtinio (lankytojai mato „Svetainė laikinai uždaryta"). Atviras lieka tik prisijungimo puslapis, kad administratoriai / leisti vartotojai galėtų prisijungti.</p>
      </td>
    </tr>
    <tr><th scope="row">QR prisijungimas</th>
      <td>
        <label>
          <input type="checkbox" name="maint_block_qr" value="1" <?= checked(get_option('ss_maint_block_qr',0),1,false) ?>>
          Laikinai išjungti prisijungimą QR kodu
        </label>
        <p class="description">QR mygtukas paslepiamas, o visi QR žetonų endpoint'ai atmeta užklausas. Bendras QR nustatymas (Nustatymai) nekeičiamas.</p>
      </td>
    </tr>
    <tr><th scope="row">Google prisijungimas</th>
      <td>
        <label>
          <input type="checkbox" name="maint_block_google" value="1" <?= checked(get_option('ss_maint_block_google',0),1,false) ?>>
          Laikinai išjungti prisijungimą per Google
        </label>
        <p class="description">Google mygtukas paslepiamas, o Google prisijungimo srautas atmetamas. Paskyrų susiejimai nustatymuose lieka nepaliesti.</p>
      </td>
    </tr>
  </tbody></table>
</div>

<p class="submit"><input type="submit" class="button button-primary button-large" value="Išsaugoti"></p>
</form>
</div>

<script>
document.querySelectorAll('input[name="maint_type"]').forEach(function(r){
  r.addEventListener('change', function(){
    document.getElementById('ss-maint-timed-fields').style.display =
      (this.value === 'timed') ? '' : 'none';
  });
});

// Leidžiamų vartotojų paieška — filtruoja <option> sąrašą; pažymėjimai išlieka,
// nes paslėpti option'ai lieka DOM'e (multi-select būsena nekeičiama).
(function(){
  var inp = document.getElementById('ss-bypass-search');
  var sel = document.getElementById('ss-bypass-select');
  var cnt = document.getElementById('ss-bypass-count');
  if (!inp || !sel) return;
  function refresh(){
    var q = inp.value.toLowerCase().trim();
    var shown = 0;
    Array.prototype.forEach.call(sel.options, function(o){
      var m = !q || o.text.toLowerCase().indexOf(q) !== -1;
      o.hidden = !m;
      o.style.display = m ? '' : 'none';   // Safari/senesnės naršyklės
      if (m) shown++;
    });
    var selected = sel.querySelectorAll('option:checked').length;
    if (cnt) cnt.textContent = 'Rodoma: ' + shown + ' iš ' + sel.options.length
      + (selected ? ' · pažymėta: ' + selected : '');
  }
  inp.addEventListener('input', refresh);
  sel.addEventListener('change', refresh);
  refresh();
})();
</script>
