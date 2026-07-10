/* WP-Admin login: QR + Google + 2FA — SS_WP_Login */
(function ($) {
'use strict';

var CFG  = typeof ss_wpl !== 'undefined' ? ss_wpl : {};
var AJAX = CFG.ajax_url || '/wp-admin/admin-ajax.php';

/* ════ 2FA auto-submit (WP admin ss_2fa puslapiui) ════ */
var $faInput = $('#ss-wpl-2fa-code');
if ($faInput.length) {
    $faInput.on('input', function () {
        var v = this.value.replace(/\D/g, '').slice(0, 6);
        this.value = v;
        if (v.length === 6) {
            var $form = $('#ss-wpl-2fa-form');
            $form.attr('data-submitting', '1');
            setTimeout(function () { $form[0].submit(); }, 120);
        }
    });
    setTimeout(function () { $faInput[0] && $faInput[0].focus(); }, 200);
}

/* ════ QR mygtukas ════ */
if (!CFG.qr_enabled || !$('#ss-wpl-qr-btn').length) {
    /* skip */
} else {
    var token  = null;
    var pollTm = null;
    var timeTm = null;
    var expAt  = 0;
    var pending2faToken = null;

    function showState(id) {
        $('#ss-wpl-state-loading,#ss-wpl-state-active,#ss-wpl-state-confirmed,' +
          '#ss-wpl-state-2fa,#ss-wpl-state-expired,#ss-wpl-state-rejected')
            .removeClass('active');
        $('#ss-wpl-state-' + id).addClass('active');
    }
    function stopPoll()  { if (pollTm) { clearInterval(pollTm);  pollTm  = null; } }
    function stopTimer() { if (timeTm) { clearInterval(timeTm); timeTm = null; } }
    function stopAll()   { stopPoll(); stopTimer(); }

    function openQR() {
        $('#ss-wpl-qr-modal').css('display', 'flex');
        createToken();
    }
    function closeQR() {
        $('#ss-wpl-qr-modal').hide();
        stopAll();
        token = null;
        pending2faToken = null;
    }

    $('#ss-wpl-qr-btn').on('click', openQR);
    $('#ss-wpl-qr-close, #ss-wpl-qr-overlay').on('click', closeQR);
    $(document).on('keydown', function (e) {
        if (e.key === 'Escape' && $('#ss-wpl-qr-modal').is(':visible')) closeQR();
    });

    function createToken() {
        showState('loading');
        stopAll();
        token = null;
        $.post(AJAX, { action: 'ss_qr_create' }, function (res) {
            if (!res.success) { alert(res.data.message || 'Klaida.'); closeQR(); return; }
            token = res.data.token;
            expAt = Date.now() + res.data.expires_in * 1000;
            var $img = $('#ss-wpl-qr-img');
            var qrSrc = 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&ecc=H&margin=2&data=' +
                encodeURIComponent(res.data.confirm_url);
            $img.off().attr('src', qrSrc)
                .on('load',  function () { showState('active'); startPoll(); startTimer(); })
                .on('error', function () { showState('active'); startPoll(); startTimer(); });
        }).fail(function () { alert('Ryšio klaida.'); closeQR(); });
    }

    function startPoll() {
        stopPoll();
        pollTm = setInterval(function () {
            if (!token) return;
            $.post(AJAX, { action: 'ss_qr_poll', token: token }, function (res) {
                if (!res.success) return;
                var st = res.data.status;
                if (st === 'confirmed') {
                    stopAll();
                    showState('confirmed');
                    setTimeout(function () { window.location.href = res.data.redirect; }, 1200);
                } else if (st === '2fa_required') {
                    stopAll();
                    pending2faToken = res.data.token;
                    showState('2fa');
                    setTimeout(function () {
                        var $c = $('#ss-wpl-qr-2fa-code');
                        $c.length && $c[0].focus();
                    }, 100);
                } else if (st === 'expired')  { stopAll(); showState('expired'); }
                  else if (st === 'rejected') { stopAll(); showState('rejected'); }
            });
        }, 2000);
    }

    function startTimer() {
        stopTimer();
        tick();
        timeTm = setInterval(tick, 1000);
    }
    function tick() {
        var left = Math.max(0, Math.round((expAt - Date.now()) / 1000));
        var m = Math.floor(left / 60), s = left % 60;
        $('#ss-wpl-qr-timer').text(m + ':' + (s < 10 ? '0' : '') + s);
        if (left === 0) stopTimer();
    }

    $('#ss-wpl-qr-refresh, #ss-wpl-qr-refresh2').on('click', createToken);

    /* QR inline 2FA */
    function submit2fa(code) {
        if (!pending2faToken || code.length !== 6) return;
        var $btn = $('#ss-wpl-qr-2fa-submit');
        var $err = $('#ss-wpl-qr-2fa-err');
        $btn.prop('disabled', true).text('…');
        $err.hide();
        $.post(AJAX, { action: 'ss_verify_2fa', token: pending2faToken, code: code },
            function (res) {
                $btn.prop('disabled', false).text('Patvirtinti');
                if (res.success) {
                    showState('confirmed');
                    setTimeout(function () { window.location.href = res.data.redirect; }, 1000);
                } else {
                    if (res.data && res.data.token) pending2faToken = res.data.token;
                    $err.text(res.data.message || 'Klaida.').show();
                    $('#ss-wpl-qr-2fa-code').val('').focus();
                }
            }
        ).fail(function () {
            $btn.prop('disabled', false).text('Patvirtinti');
            $err.text('Ryšio klaida.').show();
        });
    }

    $('#ss-wpl-qr-2fa-code').on('input', function () {
        var v = this.value.replace(/\D/g, '').slice(0, 6);
        this.value = v;
        if (v.length === 6) submit2fa(v);
    });
    $('#ss-wpl-qr-2fa-submit').on('click', function () {
        submit2fa($('#ss-wpl-qr-2fa-code').val().replace(/\D/g, ''));
    });
}

/* ════ Google mygtukas ════ */
if (CFG.gg_enabled && $('#ss-wpl-gg-btn').length) {
    $('#ss-wpl-gg-btn').on('click', function () {
        var $btn = $(this);
        $btn.prop('disabled', true).css('opacity', '0.7');
        $.post(AJAX, { action: 'ss_google_start', mode: 'login', return_url: window.location.href },
            function (res) {
                if (res.success && res.data.url) {
                    window.location.href = res.data.url;
                } else {
                    alert((res.data && res.data.message) || 'Klaida.');
                    $btn.prop('disabled', false).css('opacity', '1');
                }
            }
        ).fail(function () {
            alert('Ryšio klaida.');
            $btn.prop('disabled', false).css('opacity', '1');
        });
    });
}

/* Perkelti QR+Google blokus į #loginform (kad atrodo kaip plugino forma) */
$(function () {
    var $wrap = $('#ss-wpl-alt-wrap');
    if ($wrap.length) {
        var $form = $('#loginform');
        if ($form.length) $form.append($wrap.show());
    }
});

/* ════ Password Reset: silpno slaptažodžio UX pataisymas ════
 *
 * WordPress'o password-strength-meter.js slepia #wp-submit
 * naudodamas .addClass('hidden') ir jQuery .hide() kai slaptažodis silpnas.
 * Mes persveriame šį elgesį: mygtukas visada MATOMAS, bet DISABLED
 * kol vartotojas nepažymi "naudoti silpną slaptažodį" checkbox'o.
 * ═══════════════════════════════════════════════════════════ */
$(function () {
    var $form   = $('#resetpassform');
    if (!$form.length) return;

    var $btn    = $form.find('#wp-submit');
    var $weakCb = $form.find('input[name="pw_weak"], #pw-checkbox');
    var $meter  = $('#pass-strength-result');
    var $weakRow = $form.find('.pw-weak');
    var isWeak  = false;

    if (!$btn.length) return;

    /* ── Pagalbinė: atkurti mygtuką iš WP paslėpto stiliaus ── */
    function restoreBtn() {
        /* Pašalinti class="hidden" */
        if ($btn.hasClass('hidden')) $btn.removeClass('hidden');
        /* Pašalinti inline style="display:none" kurį jQuery .hide() prideda */
        if ($btn[0].style.display === 'none') {
            $btn[0].style.removeProperty('display');
        }
        /* Valdyti disabled pagal silpnumo būseną ir checkbox'ą */
        $btn.prop('disabled', isWeak && !$weakCb.is(':checked'));
    }

    /* ── MutationObserver: stebi #wp-submit atributų keitimus ── */
    if (window.MutationObserver) {
        new MutationObserver(function () { restoreBtn(); })
            .observe($btn[0], { attributes: true, attributeFilter: ['class', 'style'] });
    } else {
        /* Fallback senoms naršyklėms */
        setInterval(function () {
            if ($btn.hasClass('hidden') || $btn[0].style.display === 'none') restoreBtn();
        }, 80);
    }

    /* ── Sekti slaptažodžio stiprumą per #pass-strength-result ── */
    function updateStrength() {
        var cls = ($meter.attr('class') || '') + ' ' + ($meter.text() || '');
        var wasWeak = isWeak;
        isWeak = /\b(short|bad)\b/i.test(cls);
        if (isWeak !== wasWeak) {
            if (!isWeak) {
                /* Slaptažodis tapo stiprus — įjungti mygtuką */
                $btn.prop('disabled', false);
            } else {
                /* Tapo silpnas — išjungti ir parodyti warning */
                $btn.prop('disabled', !$weakCb.is(':checked'));
            }
        }
    }

    if (window.MutationObserver && $meter.length) {
        new MutationObserver(updateStrength)
            .observe($meter[0], { attributes: true, childList: true, subtree: true, characterData: true });
    }

    /* ── Checkbox keičia disabled būseną ── */
    $weakCb.on('change', function () {
        if (isWeak) $btn.prop('disabled', !$(this).is(':checked'));
    });

    /* ── Pradinė būsena: jei forma atidaryta su jau silpnu slaptažodžiu ── */
    restoreBtn();
});

})(jQuery);
