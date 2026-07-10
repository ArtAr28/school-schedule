<?php if (!defined('ABSPATH')) exit;

/* Active week (16:00 / Friday / weekend rollover handled by SS_Database::get_active_monday) */
$active_monday = isset($active_monday) ? $active_monday : SS_Database::get_active_monday();
$monday        = new DateTime($active_monday, SS_Time::tz());
$today         = new DateTime(SS_Time::today_ymd(), SS_Time::tz());
$today_dow     = SS_Time::dow();
/* Default selected date matches the dashboard's active day so the
 * Changes view shows the same day as the dashboard banner. */
$default_date  = SS_Time::active_date();

$days       = array('','Pirmadienis','Antradienis','Trečiadienis','Ketvirtadienis','Penktadienis');
$days_short = array('','Pr','An','Tr','Kt','Pn');

$logo_id   = (int) get_option('ss_logo_id', 0);
$logo_url  = $logo_id ? wp_get_attachment_image_url($logo_id, 'medium') : get_option('ss_logo_url', '');
if (!$logo_url) {
    $_theme_lid = get_theme_mod('custom_logo');
    $logo_url   = $_theme_lid ? wp_get_attachment_image_url($_theme_lid, 'medium') : '';
}
$site_name = stripslashes(get_option('ss_school_name', SS_SCHOOL_NAME));

$is_logged    = is_user_logged_in();
$current_user = $is_logged ? wp_get_current_user() : null;
$is_admin     = $is_logged && current_user_can('manage_options');
$is_teacher   = $is_logged && in_array('ss_teacher', (array)$current_user->roles);
// Ątspėk ad user preference
if ($is_logged) {
    $_atspek_uid        = get_current_user_id();
    $sett_atspek_mode   = get_user_meta($_atspek_uid, 'ss_atspek_mode',  true) ?: 'day';
    $sett_atspek_hours  = (int)(get_user_meta($_atspek_uid, 'ss_atspek_hours', true) ?: 24);
    if (!in_array($sett_atspek_mode, ['day','hours','off'], true)) $sett_atspek_mode = 'day';
    $sett_atspek_hours  = min(168, max(1, $sett_atspek_hours));
} else {
    $sett_atspek_mode  = 'day';
    $sett_atspek_hours = 24;
}
$is_employee  = $is_logged && in_array('ss_employee', (array)$current_user->roles);
$role_lbl     = $is_admin ? 'Administratorius' : ($is_teacher ? 'Mokytojas' : ($is_employee ? 'Darbuotojas' : 'Mokinys'));

$dashboard_url = ($id = get_option('ss_dashboard_page_id')) ? get_permalink($id) : home_url();
$login_url     = ($id = get_option('ss_login_page_id'))     ? get_permalink($id) : wp_login_url();
$changes_url   = ($id = get_option('ss_changes_page_id'))   ? get_permalink($id) : '';
$sett_url      = ($id = get_option('ss_settings_page_id'))  ? get_permalink($id) : '';
$is_teacher    = $is_logged && in_array('ss_teacher', (array)$current_user->roles);
$is_imp        = ($is_logged && !$is_admin && !$is_teacher) ? SS_Database::user_is_imp(get_current_user_id()) : false;
?>
<div class="ss-app" id="ss-app">

  <!-- ════ HEADER ════ -->
  <?php
    $ss_on_dashboard = false;
    $ss_active       = 'changes';
    include SS_PLUGIN_DIR . 'public/views/partials/app-nav.php';
    include SS_PLUGIN_DIR . 'public/views/partials/maintenance-banner.php';
  ?>

  <main class="ss-main">

    <!-- Spausdinimo antraštė (matoma tik @media print) -->
    <div class="ss-print-header">
      <h1><?= esc_html($site_name) ?></h1>
      <div class="ss-ph-sub">Pamokų pakeitimai</div>
      <div class="ss-ph-meta">Spausdinta: <?= esc_html(SS_Time::date('Y-m-d H:i')) ?></div>
    </div>

    <div class="ss-changes-public">
      <div class="ss-cp-header">
        <h2>Pamokų pakeitimai</h2>
        <p class="ss-cp-subtitle">
          Savaitė: <?= esc_html($monday->format('m-d')) ?> – <?= esc_html((clone $monday)->modify('+4 days')->format('m-d')) ?>
          <?php
            // "Kita savaitė" pip appears whenever the displayed Monday is
            // not the Monday of the real current week (i.e. Fri after 16:00
            // or weekend rollover).
            $real_monday_dt = SS_Time::now();
            $rdow = (int)$real_monday_dt->format('N');
            $real_monday_dt->modify('-' . ($rdow - 1) . ' days');
            $is_next_week = ($monday->format('Y-m-d') !== $real_monday_dt->format('Y-m-d'));
          ?>
          <?php if ($is_next_week): ?>
          <span class="ss-cp-week-pip">Kita savaitė</span>
          <?php endif; ?>
        </p>
      </div>

      <!-- Day tabs (Mon..Fri of active week) -->
      <div class="ss-cp-tabs">
        <?php for ($i = 0; $i < 5; $i++):
          $d = (clone $monday)->modify("+{$i} days");
          $iso = $d->format('Y-m-d');
          $isActive = ($iso === $default_date);
        ?>
        <button class="ss-cp-tab<?= $isActive ? ' active' : '' ?>" data-date="<?= esc_attr($iso) ?>">
          <span class="ss-cpt-day"><?= esc_html($days[$i+1]) ?></span>
          <span class="ss-cpt-date"><?= esc_html($d->format('m-d')) ?></span>
          <?php if ($iso === $today->format('Y-m-d')): ?>
            <span class="ss-cpt-badge">Šiandien</span>
          <?php endif; ?>
        </button>
        <?php endfor; ?>
      </div>

      <!-- Content -->
      <div class="ss-cp-content">

        <?php
        // ════ ĄSPĖK + APKLAUSOS / VIKTORINOS KARUSELĖ ════
        $promo_inline       = true;
        $promo_atspek_mode  = $sett_atspek_mode;
        $promo_atspek_hours = $sett_atspek_hours;
        include SS_PLUGIN_DIR . 'public/views/partials/promo-carousel.php';
        ?>

        <div class="ss-cp-absent-section" id="ss-cp-absent" style="display:none;">
          <div class="ss-cp-absent-header" id="ss-cp-absent-header">
            <span id="ss-cp-absent-heading">Šiandien nebus:</span>
          </div>
          <div class="ss-cp-absent-list" id="ss-cp-absent-list"></div>
          <div class="ss-cp-day-comment" id="ss-cp-day-comment" style="display:none;">
            <svg viewBox="0 0 20 20" fill="currentColor" width="14" height="14" style="flex-shrink:0;margin-top:2px"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/></svg>
            <div class="ss-day-comment-html" id="ss-cp-day-comment-text"></div>
          </div>
        </div>

        <div class="ss-cp-periods" id="ss-cp-periods">
          <?php foreach ($periods as $p): ?>
          <div class="ss-cp-period" data-period="<?= (int)$p->id ?>">
            <div class="ss-cp-period-header">
              <span class="ss-cpp-num"><?= esc_html($p->period_number) ?></span>
              <span class="ss-cpp-time"><?= esc_html(substr($p->start_time,0,5)) ?> – <?= esc_html(substr($p->end_time,0,5)) ?></span>
            </div>
            <div class="ss-cp-period-changes" id="ss-cp-changes-<?= (int)$p->id ?>">
              <p class="ss-cp-empty">Pakeitimų nėra</p>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </main>
</div>

<script>
jQuery(function($){
  var defaultDate = <?= json_encode($default_date) ?>;
  var currentDate = $('.ss-cp-tab.active').data('date') || defaultDate;

  function loadChanges(date) {
    // Show loading state: dim content, disable tabs
    $('.ss-cp-tab').prop('disabled', true);
    $('[id^="ss-cp-changes-"]').css('opacity', '0.4');
    $('#ss-cp-absent').css('opacity', '0.4');
    if (!$('#ss-cp-loading').length) {
      $('<div id="ss-cp-loading" style="text-align:center;padding:18px 0;"><span class="ss-cp-spinner"></span></div>')
        .insertBefore($('.ss-cp-periods').length ? '.ss-cp-periods' : $('[id^="ss-cp-changes-"]').first().closest('*'));
    }
    $('#ss-cp-loading').show();
    $.post(ss_pub.ajax_url, {
      action: 'ss_get_changes_public',
      date: date
    }, function(res) {
      $('#ss-cp-loading').hide();
      $('[id^="ss-cp-changes-"]').css('opacity', '');
      $('#ss-cp-absent').css('opacity', '');
      $('.ss-cp-tab').prop('disabled', false);
      if (!res.success) return;
      renderChanges(res.data.changes || [], res.data.absent || [], res.data.comment || '');
    }).fail(function(){
      $('#ss-cp-loading').hide();
      $('[id^="ss-cp-changes-"]').css('opacity', '');
      $('#ss-cp-absent').css('opacity', '');
      $('.ss-cp-tab').prop('disabled', false);
    });
  }

  function renderChanges(changes, absent, comment) {
    $('[id^="ss-cp-changes-"]').html('<p class="ss-cp-empty">Pakeitimų nėra</p>');
    var byPeriod = {};
    $.each(changes, function(i, ch) {
      if (!byPeriod[ch.period_id]) byPeriod[ch.period_id] = [];
      byPeriod[ch.period_id].push(ch);
    });
    $.each(byPeriod, function(pid, items) {
      var $container = $('#ss-cp-changes-' + pid).empty();
      $.each(items, function(i, ch) {
        // Jei pakeitimas skirtas tik vienam pogrupiui — parodome, kuriam
        // (dalykas · mokytojas · grupė), kad būtų aišku, kam jis taikomas.
        var meta = '';
        if (ch.subgroup_label) {
          var parts = [];
          if (ch.subject)      parts.push(esc(ch.subject));
          if (ch.teacher_name) parts.push(esc(ch.teacher_name));
          parts.push(esc(ch.subgroup_label));
          meta = '<span class="ss-cpc-group">' + parts.join(' · ') + '</span>';
        }
        var $card = $('<div class="ss-cp-change-card">' +
          '<span class="ss-cpc-class">' + esc(ch.class_name || '?') + '</span>' +
          meta +
          '<span class="ss-cpc-comment">' + esc(ch.comment) + '</span>' +
          '</div>');
        $container.append($card);
      });
    });

    var hasAbsent  = absent.length > 0;
    var hasComment = comment && comment.trim();

    if (hasAbsent || hasComment) {
      $('#ss-cp-absent').show();

      if (hasAbsent) {
        $('#ss-cp-absent-header').show();
        var $list = $('#ss-cp-absent-list').empty();
        $.each(absent, function(i, a) {
          var fp = parseInt(a.from_period || 0, 10);
          var extra = fp > 0
            ? ' <span class="ss-cp-absent-from">(nuo ' + fp + ' pamokos)</span>'
            : '';
          $list.append('<span class="ss-cp-absent-name">' + esc(a.teacher_name) + extra + '</span>');
        });
      } else {
        $('#ss-cp-absent-header').hide();
        $('#ss-cp-absent-list').empty();
      }

      if (hasComment) {
        $('#ss-cp-day-comment-text').html(comment);
        $('#ss-cp-day-comment').show();
      } else {
        $('#ss-cp-day-comment').hide();
      }
    } else {
      $('#ss-cp-absent').hide();
    }
  }

  function esc(s) {
    return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
  }

  $('.ss-cp-tab').on('click', function() {
    $('.ss-cp-tab').removeClass('active');
    $(this).addClass('active');
    currentDate = $(this).data('date');
    loadChanges(currentDate);
  });

  loadChanges(currentDate);
});
</script>
