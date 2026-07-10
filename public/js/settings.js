/**
 * settings.js — nustatymų puslapio sąveikos.
 *
 * Anksčiau šis kodas buvo įterptas tiesiai į settings.php (<script> blokai).
 * Iškeltas į atskirą failą: geresnis naršyklės kešavimas + CSP draugiškumas.
 * PHP duomenys (nonce'ai, ar QR įjungtas) perduodami per `ss_settings`
 * (wp_localize_script), AJAX URL — per `ss_pub.ajax_url`.
 */
(function ($) {
  'use strict';

  var CHECK_SVG = '<svg width="14" height="14" viewBox="0 0 20 20" fill="currentColor" style="vertical-align:text-bottom"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>';
  var WARN_SVG  = '<svg width="14" height="14" viewBox="0 0 20 20" fill="currentColor" style="vertical-align:text-bottom"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>';

  $(function () {
    var S    = window.ss_settings || {};
    var AJAX = (window.ss_pub && ss_pub.ajax_url) || '';

    /* ════════════════════════════════════════════════════════════════
       QR prisijungimo perjungimas (rodomas tik kai funkcija įjungta)
       ════════════════════════════════════════════════════════════════ */
    if (S.qr_enabled && S.qr_nonce) {
      $('#ss-qr-allowed').on('change', function () {
        var $cb  = $(this);
        var $msg = $('#ss-qr-allowed-msg');
        $cb.prop('disabled', true);
        $.post(AJAX, {
          action: 'ss_settings_toggle_qr_login',
          nonce: S.qr_nonce,
          enabled: $cb.is(':checked') ? 1 : 0
        }, function (res) {
          $cb.prop('disabled', false);
          if (res.success) {
            $msg.html(CHECK_SVG + ' ' + ($cb.is(':checked') ? 'QR prisijungimas įjungtas' : 'QR prisijungimas išjungtas'))
                .css('color', 'var(--g)').show().delay(2500).fadeOut();
          } else {
            $cb.prop('checked', !$cb.is(':checked')); // revert
            $msg.text((res.data && res.data.message) || 'Klaida.').css('color', 'var(--r)').show();
          }
        }).fail(function () {
          $cb.prop('disabled', false);
          $cb.prop('checked', !$cb.is(':checked'));
          $msg.text('Ryšio klaida.').css('color', 'var(--r)').show();
        });
      });
    }

    /* ════════════════════════════════════════════════════════════════
       2FA (TOTP) nustatymas — įjungimas / patvirtinimas / išjungimas
       ════════════════════════════════════════════════════════════════ */
    var _2faNonce = S.twofa_nonce || '';
    if (!_2faNonce) return; // 2FA blokas nepateiktas šiame puslapyje

    // ── Įjungimas ──
    $('#ss-2fa-start-btn').on('click', function () {
      var $btn = $(this).prop('disabled', true).text('Generuojama…');
      $.post(AJAX, { action: 'ss_2fa_setup_start', nonce: _2faNonce }, function (r) {
        $btn.prop('disabled', false).text('Įjungti 2FA');
        if (!r.success) { $('#ss-2fa-start-msg').text((r.data && r.data.message) || 'Klaida').show(); return; }
        // Generate the QR locally from the otpauth:// URL (secret never leaves the browser).
        var prov = r.data.prov || '';
        if (window.qrcode && prov) {
          try {
            var qr = qrcode(0, 'M');
            qr.addData(prov);
            qr.make();
            $('#ss-2fa-qr-img').attr('src', qr.createDataURL(6, 8)).show();
          } catch (e) { $('#ss-2fa-qr-wrap').hide(); }
        } else {
          $('#ss-2fa-qr-wrap').hide();   // no QR lib → rely on the manual key below
        }
        $('#ss-2fa-secret-txt').text(r.data.secret);
        $('#ss-2fa-step1').hide();
        $('#ss-2fa-step2').show();
        setTimeout(function () { $('#ss-2fa-verify-code').focus(); }, 200);
      }).fail(function () { $btn.prop('disabled', false).text('Įjungti 2FA'); alert('Ryšio klaida.'); });
    });

    // ── Verifikavimas ──
    function doVerify() {
      var code = $('#ss-2fa-verify-code').val().replace(/\D/g, '');
      var $msg = $('#ss-2fa-verify-msg');
      if (code.length !== 6) { $msg.text('Kodas turi būti 6 skaitmenys.').css('color', 'var(--r)').show(); return; }
      var $btn = $('#ss-2fa-verify-btn').prop('disabled', true).text('Tikrinama…');
      $.post(AJAX, { action: 'ss_2fa_setup_verify', nonce: _2faNonce, code: code }, function (r) {
        $btn.prop('disabled', false).text('Patvirtinti ir įjungti');
        if (r.success) {
          // Parodyti atsarginius kodus PRIEŠ reloadą
          if (r.data.backup_codes && r.data.backup_codes.length) {
            var codes = r.data.backup_codes;
            var html = '<div style="background:#fff8e1;border:1px solid #ffd54f;padding:16px;margin-top:14px">'
              + '<p style="margin:0 0 10px;font-weight:700;color:#e65100">' + WARN_SVG + ' Išsaugokite atsarginius kodus</p>'
              + '<p style="margin:0 0 12px;font-size:13px;color:#555">Kiekvienas kodas naudojamas tik vieną kartą. Saugokite juos saugioje vietoje.</p>'
              + '<div style="display:grid;grid-template-columns:1fr 1fr;gap:6px;margin-bottom:12px">';
            codes.forEach(function (c) {
              html += '<code style="background:var(--wh);border:1px solid #ddd;padding:4px 8px;font-size:14px;text-align:center;letter-spacing:2px">' + c + '</code>';
            });
            html += '</div>'
              + '<button type="button" id="ss-backup-continue" class="ss-btn ss-btn-pri ss-btn-sm">Supratau, tęsti →</button>'
              + '</div>';
            // 2FA is now enabled — replace the whole setup step with just the backup
            // codes + one continue button (hides the QR, key and „Patvirtinti" button).
            $('#ss-2fa-step2').children().not('#ss-2fa-verify-msg').hide();
            $msg.html(html).css('color', '').show();
            $('#ss-backup-continue').one('click', function () { location.reload(); });
          } else {
            $msg.html(CHECK_SVG + ' ').append(document.createTextNode(r.data.message)).css('color', 'var(--fr)').show();
            setTimeout(function () { location.reload(); }, 1200);
          }
        } else {
          $msg.text(r.data.message || 'Klaida.').css('color', 'var(--r)').show();
          $('#ss-2fa-verify-code').val('').focus();
        }
      }).fail(function () { $btn.prop('disabled', false).text('Patvirtinti ir įjungti'); alert('Ryšio klaida.'); });
    }
    $('#ss-2fa-verify-btn').on('click', doVerify);
    $('#ss-2fa-verify-code').on('input', function () {
      this.value = this.value.replace(/\D/g, '').slice(0, 6);
      if (this.value.length === 6) doVerify();
    });

    // ── Išjungimas ──
    function doDisable() {
      var code = $('#ss-2fa-dis-code').val().replace(/\D/g, '');
      var $msg = $('#ss-2fa-dis-msg');
      if (code.length !== 6) { $msg.text('Kodas turi būti 6 skaitmenys.').css('color', 'var(--r)').show(); return; }
      var $btn = $('#ss-2fa-disable-btn').prop('disabled', true).text('Tikrinama…');
      $.post(AJAX, { action: 'ss_2fa_setup_disable', nonce: _2faNonce, code: code }, function (r) {
        $btn.prop('disabled', false).text('Išjungti 2FA');
        if (r.success) {
          $msg.html(CHECK_SVG + ' ').append(document.createTextNode(r.data.message)).css('color', 'var(--fr)').show();
          setTimeout(function () { location.reload(); }, 1200);
        } else {
          $msg.text(r.data.message || 'Klaida.').css('color', 'var(--r)').show();
          $('#ss-2fa-dis-code').val('').focus();
        }
      }).fail(function () { $btn.prop('disabled', false).text('Išjungti 2FA'); alert('Ryšio klaida.'); });
    }
    $('#ss-2fa-disable-btn').on('click', doDisable);
    $('#ss-2fa-dis-code').on('input', function () {
      this.value = this.value.replace(/\D/g, '').slice(0, 6);
      if (this.value.length === 6) doDisable();
    });
  });
})(jQuery);
