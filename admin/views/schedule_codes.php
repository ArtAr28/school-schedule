<?php if (!defined('ABSPATH')) exit;
$ss_page_title    = 'Tvarkaraščių kodai';
$ss_page_subtitle = 'Unikalūs 8 simbolių kodai tvarkaraščiams ir el. paštų prisegimas registracijai.';
?>
<div class="wrap ss-wrap ss-codes-page">
  <?php include SS_PLUGIN_DIR . 'admin/views/partials/header.php'; ?>

  <div id="ss-notice" class="notice" style="display:none"><p></p></div>

  <!-- TABS -->
  <h2 class="nav-tab-wrapper" style="margin-top:14px;">
    <a href="#" class="nav-tab nav-tab-active" data-tab="codes">Kodai</a>
    <a href="#" class="nav-tab" data-tab="links">Prisegti el. paštai</a>
  </h2>

  <!-- ───────────── TAB: KODAI ───────────── -->
  <div class="ss-tab-pane" data-pane="codes">
    <div class="ss-codes-toolbar">
      <div class="ss-toolbar-left">
        <span class="ss-toolbar-count"><strong id="ss-codes-shown"><?= count($codes) ?></strong> tvarkaraščių</span>
        <input type="search" id="ss-codes-search" class="ss-codes-search" placeholder="Ieškoti pagal pavadinimą ar kodą…">
      </div>
      <form method="post" action="<?= esc_url(admin_url('admin-post.php')) ?>" style="display:inline;">
        <input type="hidden" name="action" value="ss_export_class_codes_csv">
        <?php wp_nonce_field('ss_export_codes'); ?>
        <button type="submit" class="button button-secondary">
          <span class="dashicons dashicons-download" style="margin-top:3px"></span>
          Eksportuoti CSV
        </button>
      </form>
    </div>

    <table class="wp-list-table widefat striped ss-table ss-codes-table">
      <thead>
        <tr>
          <th>Tvarkaraštis</th>
          <th>Kodas</th>
          <th style="width:90px;">Mokinių</th>
          <th style="width:120px;">Prisegtų paštų</th>
          <th style="width:90px;">IMP</th>
          <th style="width:160px;">Veiksmai</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($codes)): ?>
        <tr><td colspan="6" style="padding:24px;color:#94A3B8;text-align:center">Tvarkaraščių dar nėra. Sukurkite juos TVARKARAŠČIAI skiltyje.</td></tr>
        <?php else: foreach ($codes as $c): ?>
        <tr data-id="<?= (int)$c->id ?>">
          <td>
            <strong><?= esc_html($c->name) ?></strong>
            <?php if (!empty($c->color)): ?>
              <span class="ss-color-dot" style="background:<?= esc_attr($c->color) ?>"></span>
            <?php endif; ?>
          </td>
          <td>
            <code class="ss-code-pill"><?= esc_html($c->code ?: '—') ?></code>
          </td>
          <td><?= (int)$c->student_count ?></td>
          <td><?= (int)$c->link_count ?></td>
          <td>
            <?= ((int)$c->is_imp === 1) ? '<span class="ss-imp-badge">IMP</span>' : '—' ?>
          </td>
          <td style="white-space:nowrap;">
            <button class="button button-small ss-copy-code" type="button" data-code="<?= esc_attr($c->code) ?>" title="Kopijuoti kodą">
              <span class="dashicons dashicons-clipboard" style="margin-top:3px"></span>
            </button>
            <button class="button button-small ss-regen-code" type="button" data-id="<?= (int)$c->id ?>" title="Sugeneruoti naują kodą">
              <span class="dashicons dashicons-update" style="margin-top:3px"></span>
            </button>
          </td>
        </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>

  <!-- ───────────── TAB: PRISEGTI EL. PAŠTAI ───────────── -->
  <div class="ss-tab-pane" data-pane="links" style="display:none;">
    <div class="ss-codes-toolbar">
      <div class="ss-toolbar-left">
        <span class="ss-toolbar-count"><strong id="ss-links-count"><?= count($links) ?></strong> prisegtų el. paštų</span>
        <input type="search" id="ss-links-search" class="ss-codes-search" placeholder="Ieškoti pagal el. paštą, tvarkaraštį ar kodą…">
      </div>
      <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
        <button type="button" class="button button-primary" id="ss-show-add-link">
          <span class="dashicons dashicons-plus-alt2" style="margin-top:3px"></span>
          Pridėti prisegimą
        </button>
        <button type="button" class="button" id="ss-show-import">
          <span class="dashicons dashicons-upload" style="margin-top:3px"></span>
          Importuoti iš CSV
        </button>
        <form method="post" action="<?= esc_url(admin_url('admin-post.php')) ?>" style="display:inline;">
          <input type="hidden" name="action" value="ss_export_class_links_csv">
          <?php wp_nonce_field('ss_export_links'); ?>
          <button type="submit" class="button">
            <span class="dashicons dashicons-download" style="margin-top:3px"></span>
            Eksportuoti CSV
          </button>
        </form>
      </div>
    </div>

    <!-- Add single link panel -->
    <div class="ss-add-link-panel" id="ss-add-link-panel" style="display:none;">
      <h3>Pridėti prisegimą</h3>
      <div class="ss-form-row">
        <label>El. paštas
          <input type="email" id="ss-link-email" placeholder="vardas.pavarde@azuolynogimnazija.lt">
        </label>
        <label>Tvarkaraštis
          <select id="ss-link-class">
            <option value="">— pasirinkti —</option>
            <?php foreach ($classes as $cls): ?>
            <option value="<?= (int)$cls->id ?>"><?= esc_html($cls->name) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <div class="ss-form-row-actions">
          <button type="button" class="button button-primary" id="ss-save-link">Išsaugoti</button>
          <button type="button" class="button" id="ss-cancel-link">Atšaukti</button>
        </div>
      </div>
    </div>

    <!-- CSV import panel -->
    <div class="ss-add-link-panel" id="ss-import-panel" style="display:none;">
      <h3>CSV importas</h3>
      <p class="description">
        Įklijuokite arba įkelkite CSV failą. Stulpeliai: <code>email;tvarkaraščio pavadinimas</code> arba <code>email;kodas</code>.
        Pirmoji eilutė (antraštė) bus praleista.
      </p>
      <p class="description" style="margin-top:4px;">
        Pavyzdys: <code>jonas.jonaitis@azuolynogimnazija.lt;IIIf</code> arba <code>jonas.jonaitis@azuolynogimnazija.lt;ABCD2345</code>
      </p>
      <input type="file" id="ss-csv-file" accept=".csv,text/csv" style="margin:8px 0;">
      <textarea id="ss-csv-text" rows="8" placeholder="email;tvarkaraštis
jonas.jonaitis@azuolynogimnazija.lt;IIIf
ona.onaite@azuolynogimnazija.lt;IIa"></textarea>
      <div class="ss-form-row-actions">
        <button type="button" class="button button-primary" id="ss-import-go">Importuoti</button>
        <button type="button" class="button" id="ss-cancel-import">Atšaukti</button>
      </div>
    </div>

    <table class="wp-list-table widefat striped ss-table ss-links-table">
      <thead>
        <tr>
          <th>El. paštas</th>
          <th>Tvarkaraštis</th>
          <th>Kodas</th>
          <th style="width:160px;">Pridėta</th>
          <th style="width:60px;"></th>
        </tr>
      </thead>
      <tbody id="ss-links-tbody">
        <?php if (empty($links)): ?>
        <tr class="ss-links-empty"><td colspan="5" style="padding:24px;color:#94A3B8;text-align:center">Prisegimų dar nėra.</td></tr>
        <?php else: foreach ($links as $l): ?>
        <tr data-id="<?= (int)$l->id ?>">
          <td><code><?= esc_html($l->email) ?></code></td>
          <td><strong><?= esc_html($l->class_name ?: '—') ?></strong></td>
          <td><code class="ss-code-pill"><?= esc_html($l->class_code ?: '—') ?></code></td>
          <td><?= esc_html(mysql2date('Y-m-d H:i', $l->created_at)) ?></td>
          <td>
            <button class="button-link ss-link-delete" type="button" data-id="<?= (int)$l->id ?>" title="Pašalinti" aria-label="Pašalinti">
              <span class="dashicons dashicons-trash"></span>
            </button>
          </td>
        </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<style>
.ss-codes-page .ss-codes-toolbar{
  display:flex;justify-content:space-between;align-items:center;
  flex-wrap:wrap;gap:12px;margin:14px 0;
}
.ss-codes-page .ss-toolbar-left{display:flex;align-items:center;gap:14px;flex-wrap:wrap;}
.ss-codes-page .ss-toolbar-count{color:#475569;font-size:13px;}
.ss-codes-page .ss-toolbar-count strong{color:var(--sa-dark);font-size:16px;}
.ss-codes-page .ss-codes-search{
  min-width:280px;padding:7px 12px;border:1px solid #c3c4c7;
  font-size:13px;font-family:inherit;
}
.ss-codes-page .ss-codes-search:focus{border-color:var(--sa-green);outline:none;box-shadow:0 0 0 2px rgba(79,137,82,.15);}
.ss-codes-page tr.ss-row-hidden{display:none;}

.ss-codes-page .ss-code-pill{
  display:inline-block;font-family:'SFMono-Regular',Menlo,Consolas,monospace;
  font-weight:700;letter-spacing:2px;font-size:13px;
  padding:5px 10px;background:#F1F5F9;color:var(--sa-dark);
  border:1px solid #CBD5E1;
}
.ss-codes-page .ss-color-dot{
  display:inline-block;width:10px;height:10px;
  margin-left:8px;vertical-align:middle;border:1px solid rgba(0,0,0,.1);
}
.ss-codes-page .ss-imp-badge{
  display:inline-block;padding:2px 8px;
  background:#FFF4D6;color:#8A6A1F;font-size:11px;font-weight:700;
}
.ss-codes-page .ss-add-link-panel{
  background:var(--sa-white);border:1px solid #c3c4c7;
  padding:16px 20px;margin:12px 0 18px;
}
.ss-codes-page .ss-add-link-panel h3{margin:0 0 10px;font-size:15px;}
.ss-codes-page .ss-form-row{
  display:flex;flex-wrap:wrap;gap:14px;align-items:flex-end;
}
.ss-codes-page .ss-form-row label{
  display:flex;flex-direction:column;gap:4px;font-weight:600;font-size:13px;
  flex:1;min-width:240px;
}
.ss-codes-page .ss-form-row input,
.ss-codes-page .ss-form-row select{
  width:100%;padding:6px 10px;border:1px solid #c3c4c7;
  font-family:inherit;font-size:14px;
}
.ss-codes-page .ss-form-row-actions{display:flex;gap:8px;align-items:center;}
.ss-codes-page textarea#ss-csv-text{
  width:100%;font-family:'SFMono-Regular',Menlo,Consolas,monospace;
  font-size:12px;padding:10px;border:1px solid #c3c4c7;
}
.ss-codes-page .ss-link-delete{color:var(--sa-red);cursor:pointer;}
.ss-codes-page .ss-link-delete:hover{color:#9F2226;}
.ss-codes-page .nav-tab-active{cursor:default;}
</style>

<script>
jQuery(function($){
  function showNotice(msg, type){
    var $n=$('#ss-notice');
    $n.removeClass('notice-success notice-error').addClass('notice-'+(type||'success')).find('p').text(msg);
    $n.show(); clearTimeout(showNotice._t);
    showNotice._t = setTimeout(function(){ $n.fadeOut(); }, 4000);
  }

  /* Paieška: kodų ir prisegimų lentelėse (filtruoja eilutes pagal tekstą) */
  function filterTable(query, $rows, $counter){
    var q = (query || '').toLowerCase().trim();
    var shown = 0;
    $rows.each(function(){
      var $r = $(this);
      if ($r.hasClass('ss-links-empty') || $r.hasClass('ss-no-results')) return;
      var hit = !q || $r.text().toLowerCase().indexOf(q) !== -1;
      $r.toggleClass('ss-row-hidden', !hit);
      if (hit) shown++;
    });
    if ($counter && $counter.length) $counter.text(shown);
    return shown;
  }
  $(document).on('input', '#ss-codes-search', function(){
    filterTable($(this).val(), $('.ss-codes-table tbody tr'), $('#ss-codes-shown'));
  });
  $(document).on('input', '#ss-links-search', function(){
    filterTable($(this).val(), $('#ss-links-tbody tr'), $('#ss-links-count'));
  });

  /* Tabs */
  $(document).on('click', '.nav-tab', function(e){
    e.preventDefault();
    var tab = $(this).data('tab');
    $('.nav-tab').removeClass('nav-tab-active');
    $(this).addClass('nav-tab-active');
    $('.ss-tab-pane').hide();
    $('.ss-tab-pane[data-pane="'+tab+'"]').show();
  });

  /* Copy code */
  $(document).on('click', '.ss-copy-code', function(){
    var code = $(this).data('code') || '';
    if (!code) return;
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(code).then(function(){
        showNotice('Kodas „'+code+'" nukopijuotas.');
      });
    } else {
      var $t = $('<textarea>').val(code).appendTo('body').select();
      document.execCommand('copy'); $t.remove();
      showNotice('Kodas „'+code+'" nukopijuotas.');
    }
  });

  /* Regenerate code */
  $(document).on('click', '.ss-regen-code', function(){
    var $btn = $(this), id = $btn.data('id');
    if (!confirm('Sugeneruoti naują kodą? Senas kodas nebegalios.')) return;
    $btn.prop('disabled', true);
    $.post(ss_admin.ajax_url, {
      action: 'ss_regenerate_class_code', nonce: ss_admin.nonce, class_id: id
    }, function(res){
      $btn.prop('disabled', false);
      if (!res.success) { showNotice((res.data && res.data.message) || 'Klaida.', 'error'); return; }
      var $row = $btn.closest('tr');
      $row.find('.ss-code-pill').text(res.data.code);
      $row.find('.ss-copy-code').data('code', res.data.code).attr('data-code', res.data.code);
      showNotice('Naujas kodas: ' + res.data.code);
    }).fail(function(jqXHR, status, err) {
      $btn.prop('disabled', false);
      showNotice('Serverio klaida: ' + err, 'error');
    });
  });

  /* Add link panel */
  $(document).on('click', '#ss-show-add-link', function(){
    $('#ss-import-panel').hide();
    $('#ss-add-link-panel').show();
    $('#ss-link-email').focus();
  });
  $(document).on('click', '#ss-cancel-link', function(){
    $('#ss-add-link-panel').hide();
    $('#ss-link-email').val(''); $('#ss-link-class').val('');
  });
  $(document).on('click', '#ss-save-link', function(){
    var email = ($('#ss-link-email').val()||'').trim().toLowerCase();
    var cid   = $('#ss-link-class').val();
    if (!email || !cid) { showNotice('Užpildykite abu laukus.', 'error'); return; }
    var $btn = $(this); $btn.prop('disabled', true);
    $.post(ss_admin.ajax_url, {
      action: 'ss_save_class_link', nonce: ss_admin.nonce,
      email: email, class_id: cid
    }, function(res){
      $btn.prop('disabled', false);
      if (!res.success) { showNotice((res.data && res.data.message) || 'Klaida.', 'error'); return; }
      showNotice('Prisegimas išsaugotas.');
      $('#ss-cancel-link').trigger('click');
      location.reload();
    }).fail(function(jqXHR, status, err) {
      $btn.prop('disabled', false);
      showNotice('Serverio klaida: ' + err, 'error');
    });
  });

  /* CSV import panel */
  $(document).on('click', '#ss-show-import', function(){
    $('#ss-add-link-panel').hide();
    $('#ss-import-panel').show();
  });
  $(document).on('click', '#ss-cancel-import', function(){
    $('#ss-import-panel').hide(); $('#ss-csv-text').val(''); $('#ss-csv-file').val('');
  });
  $(document).on('change', '#ss-csv-file', function(e){
    var f = e.target.files && e.target.files[0]; if (!f) return;
    var reader = new FileReader();
    reader.onload = function(){ $('#ss-csv-text').val(reader.result); };
    reader.readAsText(f, 'UTF-8');
  });
  $(document).on('click', '#ss-import-go', function(){
    var csv = ($('#ss-csv-text').val()||'').trim();
    if (!csv) { showNotice('Įklijuokite CSV duomenis arba pasirinkite failą.', 'error'); return; }
    var $btn = $(this); $btn.prop('disabled', true).text('Importuojama...');
    $.post(ss_admin.ajax_url, {
      action: 'ss_import_class_links', nonce: ss_admin.nonce, csv: csv
    }, function(res){
      $btn.prop('disabled', false).text('Importuoti');
      if (!res.success) { showNotice((res.data && res.data.message) || 'Klaida.', 'error'); return; }
      showNotice(res.data.message);
      $('#ss-cancel-import').trigger('click');
      location.reload();
    }).fail(function(jqXHR, status, err) {
      $btn.prop('disabled', false).text('Importuoti');
      showNotice('Serverio klaida: ' + err, 'error');
    });
  });

  /* Delete link */
  $(document).on('click', '.ss-link-delete', function(){
    var id = $(this).data('id');
    if (!confirm('Pašalinti šį prisegimą?')) return;
    var $row = $(this).closest('tr');
    $.post(ss_admin.ajax_url, {
      action: 'ss_delete_class_link', nonce: ss_admin.nonce, id: id
    }, function(res){
      if (!res.success) { showNotice((res.data && res.data.message) || 'Klaida.', 'error'); return; }
      $row.fadeOut(200, function(){
        $(this).remove();
        var n = $('#ss-links-tbody tr').length;
        $('#ss-links-count').text(n);
        if (n === 0) {
          $('#ss-links-tbody').html('<tr class="ss-links-empty"><td colspan="5" style="padding:24px;color:#94A3B8;text-align:center">Prisegimų dar nėra.</td></tr>');
        }
      });
      showNotice('Pašalinta.');
    }).fail(function(jqXHR, status, err) {
      showNotice('Serverio klaida: ' + err, 'error');
    });
  });
});
</script>
