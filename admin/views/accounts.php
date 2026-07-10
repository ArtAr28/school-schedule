<?php
if (!defined('ABSPATH')) exit;

$nonce         = wp_create_nonce('ss_admin_nonce');
$all_classes   = SS_Database::get_classes();
$managed_caps  = SS_Accounts::managed_caps();
$managed_roles = SS_Accounts::managed_roles();

// Vartotojų skaičius kiekvienai rolei (kortelėms viršuje)
$role_counts = [];
foreach (array_keys($managed_roles) as $rs) {
    $role_counts[$rs] = count(get_users(['role' => $rs, 'number' => -1, 'fields' => 'ids']));
}
$total_users = array_sum($role_counts);
?>
<div class="wrap ss-wrap ss-acc-page">
<?php
$ss_page_title    = 'Paskyros';
$ss_page_subtitle = 'Valdykite visas sistemos paskyras — duomenys, rolės, teisės, sesijos, blokavimas.';
include SS_PLUGIN_DIR . 'admin/views/partials/header.php';
?>

<!-- ─── Rolių suvestinės kortelės ──────────────────────────── -->
<div class="ss-acc-rolecards">
  <button type="button" class="ss-acc-rolecard is-active" data-role="">
    <!--<span class="ss-acc-rc-ico" style="background:#ECEFF1;color:#37474F">∑</span>-->
    <span class="ss-acc-rc-txt"><span class="ss-acc-rc-n"><?= (int)$total_users ?></span><span class="ss-acc-rc-l">Visos paskyros</span></span>
  </button>
  <?php foreach ($managed_roles as $rs => $ri): ?>
  <button type="button" class="ss-acc-rolecard" data-role="<?= esc_attr($rs) ?>">
    <!--<span class="ss-acc-rc-ico" style="background:<?= esc_attr($ri['bg']) ?>;color:<?= esc_attr($ri['color']) ?>"><?= $ri['icon'] ?></span>-->
    <span class="ss-acc-rc-txt"><span class="ss-acc-rc-n"><?= (int)($role_counts[$rs] ?? 0) ?></span><span class="ss-acc-rc-l"><?= esc_html($ri['label']) ?></span></span>
  </button>
  <?php endforeach; ?>
</div>

<!-- ─── Įrankių juosta ─────────────────────────────────────── -->
<div class="ss-acc-toolbar">
  <input id="ss-acc-search" type="search" class="ss-search-inp" placeholder="Paieška: vardas, el. paštas, prisijungimas…">
  <select id="ss-acc-role-filter" class="ss-bulk-select">
    <option value="">Visos rolės</option>
    <?php foreach ($managed_roles as $rs => $ri): ?>
    <option value="<?= esc_attr($rs) ?>"><?= esc_html($ri['label']) ?></option>
    <?php endforeach; ?>
  </select>
  <label class="ss-acc-chk">
    <input type="checkbox" id="ss-acc-show-blocked"> Tik blokuotos
  </label>
  <button id="ss-acc-refresh" class="button ss-acc-refresh" title="Atnaujinti"><span class="dashicons dashicons-update"></span></button>
  <span id="ss-acc-count" class="ss-acc-count"></span>
</div>

<!-- ─── Lentelė ────────────────────────────────────────────── -->
<div class="ss-acc-tablewrap">
  <table class="ss-table ss-acc-table">
    <thead>
      <tr>
        <th class="ss-acc-th-av"></th>
        <th>Vartotojas</th>
        <th class="ss-acc-th-hide">El. paštas</th>
        <th>Rolė</th>
        <th class="ss-acc-th-hide">Klasės</th>
        <th>Statusas</th>
        <th class="ss-acc-th-act"></th>
      </tr>
    </thead>
    <tbody id="ss-acc-tbody">
      <tr><td colspan="7" class="ss-acc-empty">Kraunama…</td></tr>
    </tbody>
  </table>
</div>

<!-- ═══════════════════════════════════════════════════════════
     REDAGAVIMO MODALAS
════════════════════════════════════════════════════════════ -->
<div id="ss-acc-modal" class="ss-acc-modal">
  <div class="ss-acc-modal-box">

    <!-- Antraštė -->
    <div class="ss-acc-modal-head">
      <img id="ss-m-avatar" src="" width="46" height="46" alt="">
      <div class="ss-acc-mh-info">
        <div id="ss-m-name" class="ss-acc-mh-name"></div>
        <div id="ss-m-meta" class="ss-acc-mh-meta"></div>
      </div>
      <span id="ss-m-blocked-badge" class="ss-acc-pill ss-acc-pill-block" style="display:none">Blokuotas</span>
      <button id="ss-acc-modal-close" class="ss-acc-modal-x" aria-label="Uždaryti"><span class="dashicons dashicons-no-alt"></span></button>
    </div>

    <!-- Skirtukai -->
    <div class="ss-acc-tabs">
      <?php
      $tabs = [
        'data'      => ['', 'Duomenys'],
        'role'      => ['', 'Rolė ir teisės'],
        'schedules' => ['', 'Tvarkaraščiai'],
        'notify'    => ['', 'Pranešimai'],
        'security'  => ['', 'Saugumas / 2FA'],
        'sessions'  => ['', 'Sesijos / įrenginiai'],
        'block'     => ['', 'Blokavimas'],
      ];
      foreach ($tabs as $tid => $tab): ?>
      <button class="ss-m-tab-btn" data-tab="<?= $tid ?>"><?= $tab[0] ?> <?= esc_html($tab[1]) ?></button>
      <?php endforeach; ?>
    </div>

    <!-- Turinys -->
    <div class="ss-acc-modal-body">

      <!-- ── TAB: Duomenys ── -->
      <div class="ss-m-tab" data-tab="data">
        <div class="ss-acc-grid2">
          <div class="ss-field"><label>Vardas</label><input type="text" id="ss-m-first-name" class="regular-text"></div>
          <div class="ss-field"><label>Pavardė</label><input type="text" id="ss-m-last-name" class="regular-text"></div>
          <div class="ss-field"><label>Rodomas vardas</label><input type="text" id="ss-m-display-name" class="regular-text"></div>
          <div class="ss-field"><label>Telefono numeris</label><input type="text" id="ss-m-phone" class="regular-text" placeholder="+370…"></div>
          <div class="ss-field"><label>El. paštas</label><input type="email" id="ss-m-email" class="regular-text"></div>
          <div class="ss-field"><label>Vartotojo vardas <span class="ss-acc-dim">(nekeičiamas)</span></label><input type="text" id="ss-m-login" class="regular-text" readonly></div>
          <div class="ss-field ss-acc-col2">
            <label>Naujas slaptažodis <span class="ss-acc-dim">(palikite tuščią, jei nekeičiate)</span></label>
            <div class="ss-acc-pwrow">
              <input type="password" id="ss-m-password" class="regular-text" placeholder="" autocomplete="new-password">
              <button type="button" id="ss-m-pw-toggle" class="button" title="Rodyti / slėpti"><span class="dashicons dashicons-visibility"></span></button>
              <button type="button" id="ss-m-pw-gen" class="button">Generuoti</button>
            </div>
          </div>
        </div>
        <div class="ss-acc-actions">
          <button id="ss-m-save-data" class="button button-primary">Išsaugoti duomenis</button>
        </div>
      </div>

      <!-- ── TAB: Rolė ir teisės ── -->
      <div class="ss-m-tab" data-tab="role" style="display:none">
        <label class="ss-acc-sublabel">Rolė</label>
        <div id="ss-m-role-grid" class="ss-acc-rolepick-grid">
          <?php foreach ($managed_roles as $rs => $ri): ?>
          <label class="ss-role-pick" data-role="<?= esc_attr($rs) ?>" style="--rc:<?= esc_attr($ri['color']) ?>;--rcbg:<?= esc_attr($ri['bg']) ?>">
            <input type="radio" name="ss-m-role" value="<?= esc_attr($rs) ?>">
            <span class="ss-role-pick-ico dashicons <?= esc_attr($ri['icon']) ?>"></span>
            <span class="ss-role-pick-name"><?= esc_html($ri['label']) ?></span>
            <?php if ($ri['no_admin']): ?><span class="ss-role-pick-tag">be wp-admin</span><?php endif; ?>
          </label>
          <?php endforeach; ?>
        </div>
        <div class="ss-acc-actions"><button id="ss-m-save-role" class="button button-primary">Pakeisti rolę</button></div>

        <hr class="ss-acc-hr">
        <h4 class="ss-acc-h4">Individualios papildomos teisės</h4>
        <p class="ss-acc-hint">Priskiriamos tik šiai paskyrai, papildomai prie rolės teisių. Pilkos žymos – paveldėtos iš rolės.</p>
        <div id="ss-m-caps-grid" class="ss-acc-caps">
          <?php foreach ($managed_caps as $group => $caps): ?>
          <div class="ss-acc-caps-group"><?= esc_html($group) ?></div>
          <?php foreach ($caps as $cap_key => $cap_label): ?>
          <label class="ss-cap-row">
            <input type="checkbox" class="ss-m-cap-cb" value="<?= esc_attr($cap_key) ?>">
            <span class="ss-cap-name"><?= esc_html($cap_label) ?></span>
            <code class="ss-cap-key"><?= esc_html($cap_key) ?></code>
            <span class="ss-cap-source"></span>
          </label>
          <?php endforeach; ?>
          <?php endforeach; ?>
        </div>
        <div class="ss-acc-actions"><button id="ss-m-save-caps" class="button button-primary">Išsaugoti teises</button></div>
      </div>

      <!-- ── TAB: Tvarkaraščiai ── -->
      <div class="ss-m-tab" data-tab="schedules" style="display:none">
        <p class="ss-acc-hint">Pažymėkite tvarkaraščius, prie kurių turi prieigą ši paskyra.</p>
        <div id="ss-m-classes-grid" class="ss-acc-classes">
          <?php foreach ($all_classes as $cls): ?>
          <label class="ss-acc-classchk">
            <input type="checkbox" class="ss-m-class-cb" value="<?= (int)$cls->id ?>">
            <span><?= esc_html($cls->name) ?></span>
          </label>
          <?php endforeach; ?>
        </div>
        <?php if (empty($all_classes)): ?><p class="ss-acc-hint">Nėra sukurtų tvarkaraščių.</p><?php endif; ?>
        <div class="ss-acc-actions"><button id="ss-m-save-classes" class="button button-primary">Išsaugoti tvarkaraščius</button></div>
      </div>

      <!-- ── TAB: Pranešimai (iš naudotojo nustatymų) ── -->
      <div class="ss-m-tab" data-tab="notify" style="display:none">
        <p class="ss-acc-hint">Įjunkite arba išjunkite šios paskyros pranešimus ir privatumo nustatymus — tuos pačius, kuriuos vartotojas mato savo nustatymuose.</p>

        <div class="ss-acc-panel">
          <div class="ss-acc-panel-row">
            <div>
              <div class="ss-acc-panel-title">Pakeitimų pranešimai el. paštu</div>
              <div class="ss-acc-panel-sub">Kitos dienos pamokų pakeitimai (asmeniniai) ir bendras komentaras.</div>
            </div>
            <label class="ss-acc-switch"><input type="checkbox" id="ss-m-notify-changes"><span></span></label>
          </div>
          <div id="ss-m-notify-changes-opts" style="margin-top:12px;display:none">
            <label class="ss-acc-sublabel">Kada siųsti</label>
            <div class="ss-acc-blocktype" style="margin-bottom:10px">
              <label class="ss-acc-radio"><input type="radio" name="ss-m-notify-mode" value="immediate"> Iškart po paskelbimo</label>
              <label class="ss-acc-radio"><input type="radio" name="ss-m-notify-mode" value="time"> Nurodytu laiku</label>
            </div>
            <div id="ss-m-notify-time-wrap" style="display:none">
              <label class="ss-acc-sublabel">Laikas (16:00–21:00)</label>
              <select id="ss-m-notify-time" class="ss-acc-dtinput" style="max-width:160px">
                <?php foreach ((class_exists('SS_ChangeNotify') ? SS_ChangeNotify::time_options() : []) as $t): ?>
                <option value="<?= esc_attr($t) ?>"><?= esc_html($t) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
        </div>

        <div class="ss-acc-panel">
          <div class="ss-acc-panel-row">
            <div>
              <div class="ss-acc-panel-title">Naujo įrenginio pranešimai</div>
              <div class="ss-acc-panel-sub">El. laiškas prisijungus iš naujo, dar nematyto įrenginio.</div>
            </div>
            <label class="ss-acc-switch"><input type="checkbox" id="ss-m-notify-device"><span></span></label>
          </div>
        </div>

        <div class="ss-acc-panel">
          <div class="ss-acc-panel-row">
            <div>
              <div class="ss-acc-panel-title">Slėpti vardą viktorinose</div>
              <div class="ss-acc-panel-sub">Viktorinų / apklausų rezultatuose rodomas atsitiktinis vardas.</div>
            </div>
            <label class="ss-acc-switch"><input type="checkbox" id="ss-m-notify-anon"><span></span></label>
          </div>
        </div>

        <div class="ss-acc-actions"><button id="ss-m-save-notify" class="button button-primary">Išsaugoti pranešimus</button></div>
      </div>

      <!-- ── TAB: Saugumas / 2FA ── -->
      <div class="ss-m-tab" data-tab="security" style="display:none">
        <div class="ss-acc-panel">
          <div class="ss-acc-panel-row">
            <div>
              <div class="ss-acc-panel-title">Dviejų veiksnių autentifikacija (2FA)</div>
              <div id="ss-m-2fa-status" class="ss-acc-panel-sub"></div>
              <div id="ss-m-2fa-backup" class="ss-acc-dim"></div>
            </div>
            <div class="ss-acc-panel-btns">
              <button id="ss-m-2fa-disable" class="button ss-btn-danger" style="display:none">Išjungti 2FA</button>
              <button id="ss-m-2fa-enable" class="button button-primary" style="display:none">Įjungti 2FA</button>
            </div>
          </div>
          <div id="ss-m-2fa-msg" class="ss-acc-2fa-msg" style="display:none"></div>
        </div>

        <div class="ss-acc-panel">
          <div class="ss-acc-panel-title">Paskyros informacija</div>
          <table class="ss-acc-info">
            <tr><td>Registruotas</td><td id="ss-m-reg-date"></td></tr>
            <tr><td>Vartotojo ID</td><td id="ss-m-uid" class="ss-acc-mono"></td></tr>
          </table>
        </div>
      </div>

      <!-- ── TAB: Sesijos ir įrenginiai ── -->
      <div class="ss-m-tab" data-tab="sessions" style="display:none">
        <div class="ss-acc-sec-head">
          <h4 class="ss-acc-h4">Aktyvios sesijos</h4>
          <button id="ss-m-kill-all" class="button ss-btn-danger">Baigti visas</button>
        </div>
        <div id="ss-m-sessions-list" class="ss-acc-cardlist"></div>
        <div id="ss-m-no-sessions" class="ss-acc-hint" style="display:none">Nėra aktyvių sesijų.</div>

        <hr class="ss-acc-hr">

        <div class="ss-acc-sec-head">
          <h4 class="ss-acc-h4">Žinomi įrenginiai</h4>
          <button id="ss-m-clear-devices" class="button ss-btn-danger">Išvalyti visus</button>
        </div>
        <div id="ss-m-devices-list" class="ss-acc-cardlist"></div>
        <div id="ss-m-no-devices" class="ss-acc-hint" style="display:none">Nėra žinomų įrenginių.</div>
      </div>

      <!-- ── TAB: Blokavimas ── -->
      <div class="ss-m-tab" data-tab="block" style="display:none">
        <div id="ss-m-block-info" class="ss-acc-blockinfo" style="display:none">
          <div class="ss-acc-blockinfo-title">Paskyra šiuo metu blokuota</div>
          <table class="ss-acc-info">
            <tr><td>Tipas</td><td id="ss-m-block-type"></td></tr>
            <tr><td>Iki</td><td id="ss-m-block-until"></td></tr>
            <tr><td>Priežastis</td><td id="ss-m-block-reason-show"></td></tr>
          </table>
          <div class="ss-acc-actions"><button id="ss-m-unblock-btn" class="button button-primary ss-acc-btn-green"><span class="dashicons dashicons-unlock"></span> Atblokuoti paskyrą</button></div>
        </div>

        <h4 class="ss-acc-h4">Blokuoti paskyrą</h4>
        <div class="ss-acc-blockform">
          <div>
            <label class="ss-acc-sublabel">Bloko tipas</label>
            <div class="ss-acc-blocktype">
              <label class="ss-acc-radio"><input type="radio" name="ss-m-block-type" value="indefinite" checked>Neribotam laikui</label>
              <label class="ss-acc-radio"><input type="radio" name="ss-m-block-type" value="timed">Laikinai (iki datos)</label>
            </div>
          </div>
          <div id="ss-m-block-until-wrap" style="display:none">
            <label class="ss-acc-sublabel">Blokuota iki</label>
            <input type="datetime-local" id="ss-m-block-until-input" class="regular-text ss-acc-dtinput">
          </div>
          <div>
            <label class="ss-acc-sublabel">Priežastis (siunčiama vartotojui laiške)</label>
            <textarea id="ss-m-block-reason" rows="3" class="ss-acc-textarea" placeholder="Pvz.: Pažeistos naudojimosi taisyklės…"></textarea>
          </div>
          <label class="ss-acc-radio"><input type="checkbox" id="ss-m-block-notify" checked> Siųsti pranešimą vartotojui el. paštu</label>
          <div><button id="ss-m-block-btn" class="button ss-btn-danger ss-acc-block-go">Blokuoti paskyrą</button></div>
        </div>
      </div>

    </div><!-- /turinys -->

    <!-- Apačios juosta -->
    <div class="ss-acc-modal-foot">
      <span id="ss-m-feedback" class="ss-acc-feedback"></span>
      <a id="ss-m-wp-edit" href="#" target="_blank" class="button">WP redaguoti <span class="dashicons dashicons-external"></span></a>
      <button id="ss-acc-modal-close-2" class="button">Uždaryti</button>
    </div>
  </div>
</div>

<!-- ═══ Stiliai ═══ -->
<style>
.ss-acc-page .ss-table{border-collapse:separate;border-spacing:0;}
/* Rolių suvestinės kortelės */
.ss-acc-rolecards{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px;margin:18px 0;}
.ss-acc-rolecard{display:flex;align-items:center;gap:12px;padding:14px 16px;background:var(--sa-surface);border:1.5px solid var(--sa-border);box-shadow:var(--sa-shadow);cursor:pointer;transition:border-color .15s,transform .12s;text-align:left;font-family:inherit;}
.ss-acc-rolecard:hover{border-color:var(--sa-green-pale);/*transform:translateY(-1px);*/}
.ss-acc-rolecard.is-active{/border-color:var(--sa-green);box-shadow:0 0 0 3px rgba(79,137,82,.13);}
.ss-acc-rc-ico{width:40px;height:40px;display:flex;align-items:center;justify-content:center;font-size:20px;flex-shrink:0;}
.ss-acc-rc-txt{display:flex;flex-direction:column;min-width:0;}
.ss-acc-rc-n{font-size:22px;font-weight:800;color:var(--sa-dark);line-height:1.1;}
.ss-acc-rc-l{font-size:12px;color:var(--sa-green-lt);}
/* Įrankių juosta */
.ss-acc-toolbar{display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-bottom:14px;background:var(--sa-surface);border:1px solid var(--sa-border);padding:12px 14px;box-shadow:var(--sa-shadow);}
.ss-acc-toolbar .ss-search-inp{flex:1;min-width:200px;width:auto!important;}
.ss-acc-chk{display:flex;align-items:center;gap:7px;font-size:13px;color:var(--sa-dark);cursor:pointer;user-select:none;white-space:nowrap;}
.ss-acc-chk input{accent-color:var(--sa-green);width:16px;height:16px;}
.ss-acc-refresh{font-size:15px!important;line-height:1!important;width:38px;}
.ss-acc-count{margin-left:auto;font-size:13px;color:var(--sa-green-lt);white-space:nowrap;}
/* Lentelė */
.ss-acc-tablewrap{background:var(--sa-surface);border:1px solid var(--sa-border);overflow:hidden;box-shadow:var(--sa-shadow);}
.ss-acc-table{width:100%;margin:0;}
.ss-acc-table thead th{background:var(--sa-dark)!important;color:var(--sa-white)!important;font-size:11px!important;font-weight:700!important;text-transform:uppercase;letter-spacing:.4px;padding:11px 14px;text-align:left;border:none;}
.ss-acc-table tbody td{padding:10px 14px;border-bottom:1px solid var(--sa-border);font-size:13px;color:var(--sa-dark);vertical-align:middle;}
.ss-acc-table tbody tr{cursor:pointer;transition:background .12s;}
.ss-acc-table tbody tr:hover{background:#f0f9f0!important;}
.ss-acc-table tbody tr:last-child td{border-bottom:none;}
.ss-acc-th-av{width:48px;}
.ss-acc-th-act{width:120px;}
.ss-acc-empty{padding:40px!important;text-align:center;color:var(--sa-green-lt);font-style:italic;}
.ss-acc-av{width:34px;height:34px;border:1px solid var(--sa-border);display:block;object-fit:cover;}
.ss-acc-uname{font-weight:700;font-size:13.5px;color:var(--sa-dark);}
.ss-acc-ulogin{font-size:11px;color:var(--sa-green-lt);}
.ss-acc-pill{display:inline-flex;align-items:center;gap:4px;padding:3px 10px;font-size:11px;font-weight:700;white-space:nowrap;}
.ss-acc-pill-block{background:#FFE3E3;color:#9F2226;}
.ss-acc-pill-2fa{background:#E5F4E8;color:var(--sa-forest);}
.ss-acc-pill-imp{background:#FFF4D6;color:#8A6A1F;}
.ss-acc-rolebadge{display:inline-block;padding:3px 12px;font-size:11.5px;font-weight:700;white-space:nowrap;}
.ss-acc-status-ok{color:var(--sa-green);font-size:12px;font-weight:600;}
.ss-acc-status-block{color:var(--sa-red);font-size:12px;font-weight:600;}
.ss-acc-edit-btn{}
@media(max-width:900px){.ss-acc-th-hide,.ss-acc-page .ss-acc-table td.ss-acc-hide{display:none;}}
/* Modalas — flex'u centruotas overlay'us + dėžutė su max-height ir viduje
   slenkamu turiniu. Antraštė (uždaryti), skirtukai ir apačios mygtukai lieka
   matomi visada; slenka tik vidinis turinys. Anksčiau buvo
   position:absolute;top:50% + translateY(-50%), todėl aukštesnis už ekraną
   modalas „išstumdavo" viršutinius mygtukus už ekrano ribų. */
.ss-acc-modal{display:none;position:fixed;inset:0;z-index:100000;background:rgba(1,42,54,.55);backdrop-filter:blur(3px);padding:24px 16px;}
.ss-acc-modal.is-open{display:flex;align-items:center;justify-content:center;}
.ss-acc-modal-box{position:relative;width:min(880px,96vw);max-height:calc(100vh - 48px);display:flex;flex-direction:column;background:var(--sa-white);box-shadow:0 24px 70px rgba(1,42,54,.35);overflow:hidden;}
.ss-acc-modal-head{flex-shrink:0;display:flex;align-items:center;gap:14px;padding:18px 22px;border-bottom:1px solid var(--sa-border);background:var(--sa-bg);}
.ss-acc-modal-head #ss-m-avatar{border:2px solid var(--sa-white);box-shadow:0 1px 4px rgba(0,0,0,.12);object-fit:cover;}
.ss-acc-mh-info{flex:1;min-width:0;}
.ss-acc-mh-name{font-size:17px;font-weight:800;color:var(--sa-dark);line-height:1.2;}
.ss-acc-mh-meta{font-size:12px;color:var(--sa-green-lt);margin-top:2px;}
.ss-acc-modal-x{width:32px;height:32px;border:1px solid var(--sa-border);background:var(--sa-white);color:var(--sa-green-lt);cursor:pointer;font-size:14px;transition:.15s;flex-shrink:0;}
.ss-acc-modal-x:hover{background:#fff0f0;color:var(--sa-red);border-color:#F5A0A2;}
.ss-acc-tabs{flex-shrink:0;display:flex;gap:4px;padding:10px 22px 0;border-bottom:1px solid var(--sa-border);background:var(--sa-bg);overflow-x:auto;}
.ss-m-tab-btn{background:none;border:none;cursor:pointer;padding:9px 14px;font-size:13px;font-weight:600;color:var(--sa-green-lt);border-bottom:3px solid transparent;margin-bottom:-1px;white-space:nowrap;transition:.15s;font-family:inherit;}
.ss-m-tab-btn:hover{color:var(--sa-dark);background:var(--sa-white);}
.ss-m-tab-btn.ss-tab-active{color:var(--sa-green);border-bottom-color:var(--sa-green);background:var(--sa-white);}
.ss-acc-modal-body{flex:1;overflow-y:auto;padding:22px;}
.ss-acc-grid2{display:grid;grid-template-columns:1fr 1fr;gap:14px;}
.ss-acc-col2{grid-column:1/-1;}
.ss-acc-page .ss-field{margin-bottom:0;}
.ss-acc-page .ss-field label{display:block;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.4px;color:var(--sa-green-lt);margin-bottom:6px;}
.ss-acc-dim{color:var(--sa-green-pale);font-weight:400;text-transform:none;letter-spacing:0;}
.ss-acc-pwrow{display:flex;gap:8px;align-items:center;}
.ss-acc-pwrow input{flex:1;}
.ss-acc-actions{margin-top:18px;display:flex;justify-content:flex-end;gap:10px;}
.ss-acc-sublabel{display:block;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.4px;color:var(--sa-green-lt);margin-bottom:8px;}
.ss-acc-hr{border:none;border-top:1px solid var(--sa-border);margin:22px 0;}
.ss-acc-h4{margin:0;font-size:14px;font-weight:700;color:var(--sa-dark);}
.ss-acc-hint{font-size:12.5px;color:var(--sa-green-lt);margin:0 0 14px;}
/* Rolės pasirinkimas */
.ss-acc-rolepick-grid{display:flex;gap:10px;flex-wrap:wrap;}
.ss-role-pick{display:flex;align-items:center;gap:8px;padding:11px 16px;border:2px solid var(--sa-border);cursor:pointer;font-size:13px;font-weight:600;color:var(--sa-dark);background:var(--sa-white);transition:.15s;}
.ss-role-pick input{display:none;}
.ss-role-pick:hover{border-color:var(--rc);}
.ss-role-pick.ss-role-selected{border-color:var(--rc);background:var(--rcbg);color:var(--rc);}
.ss-role-pick-ico{font-size:18px;}
.ss-role-pick-tag{font-size:10px;color:var(--sa-green-pale);margin-left:2px;}
/* Teisės */
.ss-acc-caps-group{font-size:10px;font-weight:700;text-transform:uppercase;color:var(--sa-green-pale);letter-spacing:.5px;margin:14px 0 6px;}
.ss-cap-row{display:flex;align-items:center;gap:10px;padding:7px 10px;cursor:pointer;font-size:13px;transition:background .1s;}
.ss-cap-row:hover{background:var(--sa-bg);}
.ss-cap-row input{accent-color:var(--sa-green);width:15px;height:15px;}
.ss-cap-name{flex:1;color:var(--sa-dark);}
.ss-cap-key{font-size:10px;color:var(--sa-green-pale);font-family:monospace;background:none;}
.ss-cap-source{font-size:10px;color:var(--sa-green-lt);min-width:64px;text-align:right;}
/* Tvarkaraščiai */
.ss-acc-classes{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:8px;}
.ss-acc-classchk{display:flex;align-items:center;gap:10px;padding:10px 14px;border:1.5px solid var(--sa-border);cursor:pointer;font-size:13px;color:var(--sa-dark);transition:.15s;}
.ss-acc-classchk:hover{border-color:var(--sa-green-pale);background:var(--sa-bg);}
.ss-acc-classchk.is-on{border-color:var(--sa-green);background:#f0f9f0;}
.ss-acc-classchk input{accent-color:var(--sa-green);width:15px;height:15px;}
/* Paneliai */
.ss-acc-panel{background:var(--sa-bg);border:1px solid var(--sa-border);padding:16px 18px;margin-bottom:14px;}
.ss-acc-panel-row{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;}
.ss-acc-panel-title{font-size:14px;font-weight:700;color:var(--sa-dark);margin-bottom:4px;}
.ss-acc-panel-sub{font-size:13px;color:var(--sa-green-lt);}
.ss-acc-panel-btns{display:flex;gap:8px;}
/* Toggle jungiklis (pranešimų skirtuke) */
.ss-acc-switch{position:relative;display:inline-block;width:44px;height:24px;flex-shrink:0;cursor:pointer;}
.ss-acc-switch input{position:absolute;opacity:0;width:0;height:0;}
.ss-acc-switch span{position:absolute;inset:0;background:#cbd5e1;transition:background .15s;}
.ss-acc-switch span::before{content:"";position:absolute;left:3px;top:3px;width:18px;height:18px;background:var(--sa-white);transition:transform .15s;box-shadow:0 1px 3px rgba(0,0,0,.2);}
.ss-acc-switch input:checked + span{background:var(--sa-green);}
.ss-acc-switch input:checked + span::before{transform:translateX(20px);}
.ss-acc-2fa-msg{margin-top:12px;padding:12px 14px;background:var(--sa-white);border:1px solid var(--sa-border);font-size:13px;}
.ss-acc-2fa-msg code{display:block;margin-top:6px;font-size:13px;font-family:monospace;word-break:break-all;color:var(--sa-navy);background:var(--sa-bg);padding:6px 10px;}
.ss-acc-info{width:100%;border-collapse:collapse;font-size:13px;}
.ss-acc-info td{padding:7px 4px;border-bottom:1px solid var(--sa-border);color:var(--sa-dark);}
.ss-acc-info td:first-child{color:var(--sa-green-lt);width:140px;}
.ss-acc-info tr:last-child td{border-bottom:none;}
.ss-acc-mono{font-family:monospace;}
/* Sesijos / įrenginiai */
.ss-acc-sec-head{display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;}
.ss-acc-cardlist{display:flex;flex-direction:column;gap:8px;}
.ss-acc-devcard{display:flex;align-items:center;gap:12px;padding:12px 14px;border:1px solid var(--sa-border);background:var(--sa-white);}
.ss-acc-devcard.is-blocked{background:#FFF6F6;border-color:#F5C2C2;}
.ss-acc-devcard-ico{width:34px;height:34px;background:var(--sa-bg);display:flex;align-items:center;justify-content:center;font-size:17px;flex-shrink:0;}
.ss-acc-devcard-body{flex:1;min-width:0;}
.ss-acc-devcard-title{font-size:13px;font-weight:700;color:var(--sa-dark);display:flex;align-items:center;gap:6px;flex-wrap:wrap;}
.ss-acc-devcard-meta{font-size:11.5px;color:var(--sa-green-lt);margin-top:3px;display:flex;gap:8px;flex-wrap:wrap;}
.ss-acc-devcard-actions{display:flex;gap:6px;flex-shrink:0;}
.ss-acc-tag{font-size:10px;font-weight:700;padding:2px 8px;}
.ss-acc-tag-cur{background:#E7EEF7;color:var(--sa-navy);}
.ss-acc-tag-block{background:#FFE3E3;color:#9F2226;}
.ss-acc-tag-method{background:var(--sa-bg);color:var(--sa-green-lt);}
/* Blokavimas */
.ss-acc-blockinfo{margin-bottom:20px;padding:16px 18px;background:#FFF1F1;border:1px solid #F5C2C2;}
.ss-acc-blockinfo-title{font-size:14px;font-weight:700;color:#9F2226;margin-bottom:8px;}
.ss-acc-blockform{display:flex;flex-direction:column;gap:14px;margin-top:14px;}
.ss-acc-blocktype{display:flex;gap:10px;flex-wrap:wrap;}
.ss-acc-radio{display:flex;align-items:center;gap:8px;font-size:13px;color:var(--sa-dark);cursor:pointer;}
.ss-acc-blocktype .ss-acc-radio{flex:1;min-width:180px;padding:11px 16px;border:2px solid var(--sa-border);font-weight:600;}
.ss-acc-textarea{width:100%;border:1.5px solid var(--sa-border);padding:10px;font-size:13px;font-family:inherit;resize:vertical;color:var(--sa-dark);box-sizing:border-box;}
.ss-acc-textarea:focus,.ss-acc-dtinput:focus{border-color:var(--sa-green);outline:none;box-shadow:0 0 0 3px rgba(79,137,82,.14);}
.ss-acc-dtinput{max-width:280px;}
.ss-acc-btn-green{background:var(--sa-green)!important;border-color:var(--sa-green)!important;color:var(--sa-white)!important;}
.ss-acc-btn-green:hover{background:var(--sa-forest)!important;}
.ss-acc-block-go{font-weight:700!important;}
/* Apačia */
.ss-acc-modal-foot{flex-shrink:0;padding:14px 22px;border-top:1px solid var(--sa-border);background:var(--sa-bg);display:flex;align-items:center;gap:12px;}
.ss-acc-feedback{font-size:13px;flex:1;}
@media(max-width:680px){.ss-acc-grid2{grid-template-columns:1fr;}}
/* Dashicons dydžiai */
.ss-acc-page .dashicons{vertical-align:text-bottom;}
.ss-acc-pill .dashicons,.ss-acc-status-ok .dashicons,.ss-acc-status-block .dashicons{font-size:14px;width:14px;height:14px;line-height:14px;}
.ss-role-pick-ico.dashicons{font-size:18px;width:18px;height:18px;}
.ss-acc-devcard-ico .dashicons{font-size:18px;width:18px;height:18px;}
.ss-acc-feedback .dashicons{vertical-align:text-bottom;margin-right:3px;}
.ss-acc-modal-x .dashicons,.ss-acc-refresh .dashicons{font-size:18px;width:18px;height:18px;line-height:1;}
.ss-acc-page .button .dashicons{font-size:16px;width:16px;height:16px;line-height:1.5;vertical-align:text-bottom;}
</style>

<!-- ═══ JavaScript ═══ -->
<script>
(function($){
var nonce='<?= esc_js($nonce) ?>';
var currentUid=0;
var currentDetail={};

function escHtml(str){
  return String(str==null?'':str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
function dico(n){return '<span class="dashicons dashicons-'+n+'"></span>';}
var roleMap={'ss_student':['#E3F2FD','#1565C0'],'ss_teacher':['#F3E5F5','#6A1B9A'],'ss_employee':['#FFF3E0','#E65100'],'administrator':['#FCE4EC','#880E4F']};

/* ─── Sąrašo įkėlimas ─── */
function loadAccounts(){
  var role   = $('#ss-acc-role-filter').val();
  var search = $('#ss-acc-search').val();
  var onlyBlocked = $('#ss-acc-show-blocked').is(':checked');
  $('#ss-acc-tbody').html('<tr><td colspan="7" class="ss-acc-empty">Kraunama…</td></tr>');
  $.post(ajaxurl,{action:'ss_admin_get_accounts',nonce:nonce,role:role,search:search},function(res){
    if(!res.success){$('#ss-acc-tbody').html('<tr><td colspan="7" class="ss-acc-empty" style="color:#C00">Klaida: '+escHtml(res.data.message)+'</td></tr>');return;}
    var accounts=res.data.accounts;
    if(onlyBlocked) accounts=accounts.filter(function(a){return a.blocked;});
    $('#ss-acc-count').text('Rodoma: '+accounts.length);
    if(!accounts.length){$('#ss-acc-tbody').html('<tr><td colspan="7" class="ss-acc-empty">Pagal kriterijus vartotojų nerasta.</td></tr>');return;}
    var html='';
    $.each(accounts,function(i,a){
      var rc=roleMap[a.role_slug]||['#ECEFF1','#37474F'];
      var badges='';
      if(a['2fa'])  badges+=' <span class="ss-acc-pill ss-acc-pill-2fa">'+dico('shield-alt')+' 2FA</span>';
      if(a.imp)     badges+=' <span class="ss-acc-pill ss-acc-pill-imp">'+dico('star-filled')+' IMP</span>';
      html+='<tr data-uid="'+a.id+'">'
        +'<td><img class="ss-acc-av" src="'+escHtml(a.avatar)+'" width="34" height="34" alt=""></td>'
        +'<td><div class="ss-acc-uname">'+escHtml(a.display_name)+'</div><div class="ss-acc-ulogin">@'+escHtml(a.login)+'</div>'+badges+'</td>'
        +'<td class="ss-acc-hide">'+escHtml(a.email)+'</td>'
        +'<td><span class="ss-acc-rolebadge" style="background:'+rc[0]+';color:'+rc[1]+'">'+escHtml(a.role)+'</span></td>'
        +'<td class="ss-acc-hide">'+escHtml((a.classes||[]).join(', ')||'—')+'</td>'
        +'<td>'+(a.blocked
            ? '<span class="ss-acc-status-block">'+dico('lock')+' Blokuotas'+(a.block_until?' iki '+escHtml(a.block_until):'')+'</span>'
            : '<span class="ss-acc-status-ok">'+dico('yes-alt')+' Aktyvi</span>')+'</td>'
        +'<td><button class="button button-small ss-acc-edit-btn" data-uid="'+a.id+'">'+dico('edit')+' Redaguoti</button></td>'
        +'</tr>';
    });
    $('#ss-acc-tbody').html(html);
  });
}

var searchTimer;
$('#ss-acc-search').on('input',function(){ clearTimeout(searchTimer); searchTimer=setTimeout(loadAccounts,350); });
$('#ss-acc-role-filter,#ss-acc-show-blocked').on('change',loadAccounts);
$('#ss-acc-refresh').on('click',loadAccounts);

/* Rolių kortelės = greitas filtras */
$('.ss-acc-rolecard').on('click',function(){
  $('.ss-acc-rolecard').removeClass('is-active');
  $(this).addClass('is-active');
  $('#ss-acc-role-filter').val($(this).data('role'));
  $('#ss-acc-show-blocked').prop('checked',false);
  loadAccounts();
});

$(document).on('click','#ss-acc-tbody tr,#ss-acc-tbody .ss-acc-edit-btn',function(e){
  e.stopPropagation();
  var uid=$(this).closest('tr').data('uid')||$(this).data('uid');
  if(uid) openModal(parseInt(uid));
});

loadAccounts();

var hash=window.location.hash;
if(hash && hash.startsWith('#uid=')){
  var uid=parseInt(hash.slice(5));
  if(uid) setTimeout(function(){ openModal(uid); },600);
}

/* ─── Modalas ─── */
function openModal(uid){
  currentUid=uid; currentDetail={}; setFeedback('');
  $('#ss-acc-modal').addClass('is-open');
  document.body.style.overflow='hidden';
  $('#ss-m-name').text('Kraunama…'); $('#ss-m-meta').text('');
  activateTab('data');
  $.post(ajaxurl,{action:'ss_admin_get_account_detail',nonce:nonce,user_id:uid},function(res){
    if(!res.success){setFeedback('Klaida: '+(res.data.message||'Nežinoma klaida'),'error');return;}
    currentDetail=res.data.detail;
    populateModal(currentDetail);
  });
}
function closeModal(){ $('#ss-acc-modal').removeClass('is-open'); document.body.style.overflow=''; }
$('#ss-acc-modal-close,#ss-acc-modal-close-2').on('click',closeModal);
$('#ss-acc-modal').on('click',function(e){if($(e.target).is('#ss-acc-modal'))closeModal();});
$(document).on('keydown',function(e){if(e.key==='Escape'&&$('#ss-acc-modal').is(':visible'))closeModal();});

function populateModal(d){
  var roles=d.roles||[]; var rslug=roles[0]||'ss_student';
  var roleName={'ss_student':'Mokinys','ss_teacher':'Mokytojas','ss_employee':'Darbuotojas','administrator':'Administratorius'}[rslug]||rslug;
  var imp=d.extra_caps&&d.extra_caps['ss_imp_access'];
  $('#ss-m-avatar').attr('src',d.avatar||'');
  $('#ss-m-name').text(d.display_name||d.login);
  $('#ss-m-meta').text('@'+d.login+' · '+roleName+(imp?' · IMP':''));
  $('#ss-m-wp-edit').attr('href','<?= esc_url(admin_url('user-edit.php')) ?>?user_id='+d.id);
  $('#ss-m-blocked-badge').toggle(!!d.block);

  $('#ss-m-first-name').val(d.first_name||'');
  $('#ss-m-last-name').val(d.last_name||'');
  $('#ss-m-display-name').val(d.display_name||'');
  $('#ss-m-phone').val(d.phone||'');
  $('#ss-m-email').val(d.email||'');
  $('#ss-m-login').val(d.login||'');
  $('#ss-m-password').val('');

  $('.ss-role-pick').removeClass('ss-role-selected');
  $('.ss-role-pick[data-role="'+rslug+'"]').addClass('ss-role-selected');
  $('input[name="ss-m-role"][value="'+rslug+'"]').prop('checked',true);

  var extra=d.extra_caps||{};
  $('.ss-m-cap-cb').each(function(){
    var cap=$(this).val();
    $(this).prop('checked',!!extra[cap]);
    $(this).closest('label').find('.ss-cap-source').text(!!extra[cap]?'aktyvi':'');
  });

  var classIds=d.class_ids||[];
  $('.ss-m-class-cb').each(function(){
    var on=classIds.indexOf(parseInt($(this).val()))>=0;
    $(this).prop('checked',on);
    $(this).closest('label').toggleClass('is-on',on);
  });

  var fa=d['2fa_enabled'];
  $('#ss-m-2fa-status').html(fa?dico('yes-alt')+' Įjungta':dico('no-alt')+' Išjungta').css('color',fa?'var(--sa-forest)':'#999');
  $('#ss-m-2fa-backup').text(fa?'Atsarginiai kodai: '+(d['2fa_backup']||0):'');
  $('#ss-m-2fa-disable').toggle(!!fa);
  $('#ss-m-2fa-enable').toggle(!fa);
  $('#ss-m-2fa-msg').hide().text('');

  $('#ss-m-uid').text('#'+d.id);
  var regDate=d.registered?new Date(d.registered.replace(' ','T')).toLocaleDateString('lt-LT',{year:'numeric',month:'long',day:'numeric'}):'—';
  $('#ss-m-reg-date').text(regDate);

  renderSessions(d.sessions||[]);
  renderDevices(d.devices||[]);
  renderBlockInfo(d.block);

  // Pranešimų skirtukas
  var nt = d.notify || {};
  $('#ss-m-notify-changes').prop('checked', !!nt.changes_on);
  $('input[name="ss-m-notify-mode"][value="'+(nt.changes_mode==='time'?'time':'immediate')+'"]').prop('checked', true);
  $('#ss-m-notify-time').val(nt.changes_time || '17:00');
  $('#ss-m-notify-device').prop('checked', !!nt.new_device);
  $('#ss-m-notify-anon').prop('checked', !!nt.anon_quiz);
  notifyToggle();
}

function notifyToggle(){
  $('#ss-m-notify-changes-opts').toggle($('#ss-m-notify-changes').is(':checked'));
  $('#ss-m-notify-time-wrap').toggle($('input[name="ss-m-notify-mode"]:checked').val()==='time');
}

function activateTab(tid){
  $('.ss-m-tab').hide();
  $('.ss-m-tab[data-tab="'+tid+'"]').show();
  $('.ss-m-tab-btn').removeClass('ss-tab-active');
  $('.ss-m-tab-btn[data-tab="'+tid+'"]').addClass('ss-tab-active');
}
$(document).on('click','.ss-m-tab-btn',function(){ activateTab($(this).data('tab')); });

function setFeedback(msg,type){
  var $fb=$('#ss-m-feedback');
  if(!msg){$fb.empty();return;}
  $fb.html(dico(type==='error'?'warning':'yes-alt')+'<span class="ss-acc-fb-txt">'+escHtml(msg)+'</span>').css('color',type==='error'?'#C00':'var(--sa-forest)');
  setTimeout(function(){if($fb.find('.ss-acc-fb-txt').text()===msg)$fb.empty();},4000);
}

/* ─── Slaptažodis ─── */
$('#ss-m-pw-toggle').on('click',function(){
  var $pw=$('#ss-m-password'); $pw.attr('type',$pw.attr('type')==='password'?'text':'password');
});
$('#ss-m-pw-gen').on('click',function(){
  var chars='abcdefghijkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789!@#$%';
  var pw='';for(var i=0;i<14;i++)pw+=chars[Math.floor(Math.random()*chars.length)];
  $('#ss-m-password').val(pw).attr('type','text');
});

/* ─── Išsaugoti duomenis ─── */
$('#ss-m-save-data').on('click',function(){
  var $btn=$(this).prop('disabled',true).text('Saugoma…');
  var payload={action:'ss_admin_update_account',nonce:nonce,user_id:currentUid,
    first_name:$('#ss-m-first-name').val(),last_name:$('#ss-m-last-name').val(),
    display_name:$('#ss-m-display-name').val(),email:$('#ss-m-email').val(),phone:$('#ss-m-phone').val()};
  var pw=$('#ss-m-password').val(); if(pw) payload.password=pw;
  $.post(ajaxurl,payload,function(res){
    $btn.prop('disabled',false).html(dico('database')+' Išsaugoti duomenis');
    if(res.success){setFeedback(res.data.message);loadAccounts();}
    else setFeedback(res.data.message||'Klaida','error');
  });
});

/* ─── Rolė ─── */
$('.ss-role-pick').on('click',function(){
  $('.ss-role-pick').removeClass('ss-role-selected');
  $(this).addClass('ss-role-selected');
  $(this).find('input[type=radio]').prop('checked',true);
});
$('#ss-m-save-role').on('click',function(){
  var role=$('input[name="ss-m-role"]:checked').val();
  if(!role){setFeedback('Pasirinkite rolę.','error');return;}
  if(!confirm('Keisti rolę į „'+role+'"?')) return;
  var $btn=$(this).prop('disabled',true).text('Saugoma…');
  $.post(ajaxurl,{action:'ss_admin_change_user_role',nonce:nonce,user_id:currentUid,role:role},function(res){
    $btn.prop('disabled',false).html(dico('database')+' Pakeisti rolę');
    if(res.success){setFeedback(res.data.message);loadAccounts();}
    else setFeedback(res.data.message||'Klaida','error');
  });
});

/* ─── Teisės ─── */
$(document).on('change','.ss-m-cap-cb',function(){
  $(this).closest('label').find('.ss-cap-source').text($(this).is(':checked')?'aktyvi':'');
});
$('#ss-m-save-caps').on('click',function(){
  var caps=[]; $('.ss-m-cap-cb:checked').each(function(){caps.push($(this).val());});
  var $btn=$(this).prop('disabled',true).text('Saugoma…');
  $.post(ajaxurl,{action:'ss_admin_set_user_caps',nonce:nonce,user_id:currentUid,caps:caps},function(res){
    $btn.prop('disabled',false).html(dico('database')+' Išsaugoti teises');
    setFeedback(res.success?res.data.message:(res.data.message||'Klaida'),res.success?'':'error');
  });
});

/* ─── Tvarkaraščiai ─── */
$(document).on('change','.ss-m-class-cb',function(){
  $(this).closest('label').toggleClass('is-on',$(this).is(':checked'));
});
$('#ss-m-save-classes').on('click',function(){
  var ids=[]; $('.ss-m-class-cb:checked').each(function(){ids.push($(this).val());});
  var $btn=$(this).prop('disabled',true).text('Saugoma…');
  // classes_provided=1 → leidžia ir nuimti visus tvarkaraščius (tuščias masyvas).
  $.post(ajaxurl,{action:'ss_admin_update_account',nonce:nonce,user_id:currentUid,class_ids:ids,classes_provided:1},function(res){
    $btn.prop('disabled',false).html(dico('database')+' Išsaugoti tvarkaraščius');
    if(res.success){setFeedback('Tvarkaraščiai išsaugoti.');loadAccounts();}
    else setFeedback(res.data.message||'Klaida','error');
  });
});

/* ─── Pranešimai (iš naudotojo nustatymų) ─── */
$(document).on('change','#ss-m-notify-changes',notifyToggle);
$(document).on('change','input[name="ss-m-notify-mode"]',notifyToggle);
$('#ss-m-save-notify').on('click',function(){
  var $btn=$(this).prop('disabled',true).text('Saugoma…');
  $.post(ajaxurl,{
    action:'ss_admin_save_user_notify', nonce:nonce, user_id:currentUid,
    changes_on:   $('#ss-m-notify-changes').is(':checked')?1:0,
    changes_mode: $('input[name="ss-m-notify-mode"]:checked').val()||'immediate',
    changes_time: $('#ss-m-notify-time').val()||'17:00',
    new_device:   $('#ss-m-notify-device').is(':checked')?1:0,
    anon_quiz:    $('#ss-m-notify-anon').is(':checked')?1:0
  },function(res){
    $btn.prop('disabled',false).html(dico('database')+' Išsaugoti pranešimus');
    if(res.success){ setFeedback(res.data.message); if(res.data.notify) currentDetail.notify=res.data.notify; }
    else setFeedback((res.data&&res.data.message)||'Klaida','error');
  });
});

/* ─── 2FA ─── */
$('#ss-m-2fa-disable').on('click',function(){
  if(!confirm('Išjungti 2FA šiai paskyrai? Atsarginiai kodai bus ištrinti.')) return;
  var $btn=$(this).prop('disabled',true).text('…');
  $.post(ajaxurl,{action:'ss_admin_toggle_2fa',nonce:nonce,user_id:currentUid,enable:0},function(res){
    $btn.prop('disabled',false).html(dico('unlock')+' Išjungti 2FA');
    if(res.success){
      setFeedback(res.data.message);
      $('#ss-m-2fa-status').html(dico('no-alt')+' Išjungta').css('color','#999');
      $('#ss-m-2fa-backup').text(''); $('#ss-m-2fa-disable').hide(); $('#ss-m-2fa-enable').show();
      currentDetail['2fa_enabled']=false;
    } else setFeedback(res.data.message||'Klaida','error');
  });
});
$('#ss-m-2fa-enable').on('click',function(){
  if(!confirm('Įjungti 2FA šiai paskyrai? Bus sugeneruotas naujas raktas.')) return;
  var $btn=$(this).prop('disabled',true).text('…');
  $.post(ajaxurl,{action:'ss_admin_toggle_2fa',nonce:nonce,user_id:currentUid,enable:1},function(res){
    $btn.prop('disabled',false).html(dico('lock')+' Įjungti 2FA');
    if(res.success){
      setFeedback(res.data.message);
      $('#ss-m-2fa-status').html(dico('yes-alt')+' Įjungta').css('color','var(--sa-forest)');
      $('#ss-m-2fa-disable').show(); $('#ss-m-2fa-enable').hide();
      if(res.data.secret){
        $('#ss-m-2fa-msg').html(dico('admin-network')+' Naujas slaptasis raktas. Vartotojas turės jį suvesti autentifikatoriuje:<code>'+escHtml(res.data.secret)+'</code>').show();
      }
      currentDetail['2fa_enabled']=true;
    } else setFeedback(res.data.message||'Klaida','error');
  });
});

/* ─── Sesijos ─── */
function renderSessions(sessions){
  var $list=$('#ss-m-sessions-list').empty(), $empty=$('#ss-m-no-sessions');
  if(!sessions||!sessions.length){$empty.show();$list.hide();return;}
  $list.show();$empty.hide();
  $.each(sessions,function(i,s){
    var meta='';
    if(s.geo) meta+='<span>'+dico('location')+' '+escHtml(s.geo)+'</span>';
    if(s.ip)  meta+='<span>· IP: '+escHtml(s.ip)+'</span>';
    meta+='<span>· Prisijungė: '+escHtml(s.login_fmt)+'</span>';
    $list.append(
      '<div class="ss-acc-devcard">'
      +'<div class="ss-acc-devcard-ico">'+dico('desktop')+'</div>'
      +'<div class="ss-acc-devcard-body"><div class="ss-acc-devcard-title">'+escHtml(s.device||s.ua||'Nežinoma naršyklė')+'</div>'
      +'<div class="ss-acc-devcard-meta">'+meta+'</div></div>'
      +'<div class="ss-acc-devcard-actions"><button class="button button-small ss-btn-danger ss-kill-session" data-verifier="'+escHtml(s.verifier)+'">Baigti</button></div>'
      +'</div>'
    );
  });
}
$(document).on('click','.ss-kill-session',function(){
  var v=$(this).data('verifier'), $btn=$(this).prop('disabled',true).text('…');
  $.post(ajaxurl,{action:'ss_admin_destroy_session',nonce:nonce,user_id:currentUid,verifier:v},function(res){
    if(res.success){setFeedback(res.data.message);renderSessions(res.data.sessions);}
    else{$btn.prop('disabled',false).text('Baigti');setFeedback(res.data.message||'Klaida','error');}
  });
});
$('#ss-m-kill-all').on('click',function(){
  if(!confirm('Baigti visas aktyvias sesijas?')) return;
  var $btn=$(this).prop('disabled',true).text('…');
  $.post(ajaxurl,{action:'ss_admin_destroy_all_sessions',nonce:nonce,user_id:currentUid},function(res){
    $btn.prop('disabled',false).text('Baigti visas');
    if(res.success){setFeedback(res.data.message);renderSessions([]);}
    else setFeedback(res.data.message||'Klaida','error');
  });
});

/* ─── Įrenginiai ─── */
function renderDevices(devices){
  var $list=$('#ss-m-devices-list').empty(), $empty=$('#ss-m-no-devices');
  if(!devices||!devices.length){$empty.show();$list.hide();return;}
  $list.show();$empty.hide();
  $.each(devices,function(i,d){
    var meta='';
    if(d.geo) meta+='<span>'+dico('location')+' '+escHtml(d.geo)+'</span>';
    if(d.ip)  meta+='<span>· IP: '+escHtml(d.ip)+'</span>';
    meta+='<span>· '+escHtml(d.last_fmt)+'</span>';
    var tags=(d.blocked?'<span class="ss-acc-tag ss-acc-tag-block">Blokuotas</span>':'');
    $list.append(
      '<div class="ss-acc-devcard'+(d.blocked?' is-blocked':'')+'">'
      +'<div class="ss-acc-devcard-ico">'+(d.method==='google_auth'?dico('google'):(/Android|iPhone|iPad/.test(d.ua||'')?dico('smartphone'):dico('desktop')))+'</div>'
      +'<div class="ss-acc-devcard-body"><div class="ss-acc-devcard-title">'+escHtml(d.device||d.ua||'Nežinomas įrenginys')+' '+tags+'</div>'
      +'<div class="ss-acc-devcard-meta">'+meta+'</div></div>'
      +'<div class="ss-acc-devcard-actions">'
      +(d.blocked
          ? '<button class="button button-small ss-device-unblock" data-fp="'+escHtml(d.fp)+'">Atblokuoti</button>'
          : '<button class="button button-small ss-device-block" data-fp="'+escHtml(d.fp)+'">Blokuoti</button>')
      +'<button class="button button-small ss-btn-danger ss-device-remove" data-fp="'+escHtml(d.fp)+'" title="Pašalinti">'+dico('no-alt')+'</button>'
      +'</div></div>'
    );
  });
}
$(document).on('click','.ss-device-block,.ss-device-unblock',function(){
  var fp=$(this).data('fp'), isBlock=$(this).hasClass('ss-device-block');
  $.post(ajaxurl,{action:'ss_admin_remove_device',nonce:nonce,user_id:currentUid,fp:fp,block:isBlock?1:0},function(res){
    if(res.success){setFeedback(res.data.message);renderDevices(res.data.devices);}
    else setFeedback(res.data.message||'Klaida','error');
  });
});
$(document).on('click','.ss-device-remove',function(){
  var fp=$(this).data('fp');
  if(!confirm('Pašalinti šį įrenginį iš žinomų?')) return;
  $.post(ajaxurl,{action:'ss_admin_remove_device',nonce:nonce,user_id:currentUid,fp:fp},function(res){
    if(res.success){setFeedback(res.data.message);renderDevices(res.data.devices);}
    else setFeedback(res.data.message||'Klaida','error');
  });
});
$('#ss-m-clear-devices').on('click',function(){
  if(!confirm('Išvalyti visų žinomų įrenginių istoriją?')) return;
  var $btn=$(this).prop('disabled',true).text('…');
  $.post(ajaxurl,{action:'ss_admin_clear_devices',nonce:nonce,user_id:currentUid},function(res){
    $btn.prop('disabled',false).text('Išvalyti visus');
    if(res.success){setFeedback(res.data.message);renderDevices([]);}
    else setFeedback(res.data.message||'Klaida','error');
  });
});

/* ─── Blokavimas ─── */
function renderBlockInfo(block){
  if(block){
    $('#ss-m-block-info').show();
    $('#ss-m-block-type').text(block.type==='timed'?'Laikinas':'Neribotam laikui');
    $('#ss-m-block-until').text(block.until_fmt||'—');
    $('#ss-m-block-reason-show').text(block.reason||'—');
  } else $('#ss-m-block-info').hide();
}
$('input[name="ss-m-block-type"]').on('change',function(){
  $('#ss-m-block-until-wrap').toggle($(this).val()==='timed');
});
$('#ss-m-unblock-btn').on('click',function(){
  if(!confirm('Atblokuoti šią paskyrą?')) return;
  var $btn=$(this).prop('disabled',true).text('…');
  $.post(ajaxurl,{action:'ss_admin_unblock_account',nonce:nonce,user_id:currentUid},function(res){
    $btn.prop('disabled',false).html(dico('unlock')+' Atblokuoti paskyrą');
    if(res.success){setFeedback(res.data.message);currentDetail.block=null;renderBlockInfo(null);$('#ss-m-blocked-badge').hide();loadAccounts();}
    else setFeedback(res.data.message||'Klaida','error');
  });
});
$('#ss-m-block-btn').on('click',function(){
  var type=$('input[name="ss-m-block-type"]:checked').val()||'indefinite';
  var reason=$('#ss-m-block-reason').val();
  var notify=$('#ss-m-block-notify').is(':checked')?1:0;
  var until=0;
  if(type==='timed'){
    var dt=$('#ss-m-block-until-input').val();
    if(!dt){setFeedback('Nurodykite bloko pabaigos datą ir laiką.','error');return;}
    until=Math.floor(new Date(dt).getTime()/1000);
    if(until<=Math.floor(Date.now()/1000)){setFeedback('Bloko pabaiga turi būti ateityje.','error');return;}
  }
  if(!confirm('Tikrai blokuoti šią paskyrą?')) return;
  var $btn=$(this).prop('disabled',true).text('…');
  $.post(ajaxurl,{action:'ss_admin_block_account',nonce:nonce,user_id:currentUid,type:type,until:until,reason:reason,notify:notify},function(res){
    $btn.prop('disabled',false).html(dico('lock')+' Blokuoti paskyrą');
    if(res.success){setFeedback(res.data.message);currentDetail.block=res.data.block;renderBlockInfo(res.data.block);$('#ss-m-blocked-badge').show();loadAccounts();}
    else setFeedback(res.data.message||'Klaida','error');
  });
});

})(jQuery);
</script>
