/* Ąžuolyno Tvarkaraštis — Public JS v5 */
(function($){
'use strict';
var SS={
  init:function(){ this.login(); this.register(); this.newPassword(); this.dashboard(); this.promoCarousel(); this.surveyModal(); this.eurovizija(); this.qrLogin(); this.twoFactor(); this.viewPdfButtons(); },

  /* ════ TURNSTILE helpers (kelių valdiklių puslapyje) ════ */
  tsToken:function(sel){
    var el=document.querySelector(sel+' [name="cf-turnstile-response"]');
    return el?el.value:'';
  },
  tsReset:function(sel){
    try{ if(window.turnstile){ var c=document.querySelector(sel); if(c) turnstile.reset(c); } }catch(e){}
  },

  /* ════ PRIVERSTINIS SLAPTAŽODŽIO KEITIMAS ════ */
  showNewPw:function(token,$fromCard){
    SS._pwchgToken=token;
    var $c=$('#ss-card-newpw');
    if($fromCard&&$fromCard.length){ $fromCard.fadeOut(160,function(){$c.fadeIn(160);$('#ss-newpw-pass').focus();}); }
    else{ $('.ss-form-card').hide(); $c.fadeIn(160,function(){$('#ss-newpw-pass').focus();}); }
  },
  newPassword:function(){
    var $f=$('#ss-newpw-form'); if(!$f.length) return;
    $f.on('submit',function(e){
      e.preventDefault();
      var $btn=$('#ss-newpw-submit'),$lbl=$btn.find('.ss-btn-lbl'),$spin=$btn.find('.ss-btn-spin'),$err=$('#ss-newpw-err');
      var p1=$('#ss-newpw-pass').val()||'',p2=$('#ss-newpw-pass2').val()||'';
      var min=(window.SS_PW_RULES&&SS_PW_RULES.min)?SS_PW_RULES.min:8;
      $err.hide();
      if(p1.length<min){ $err.text('Slaptažodis turi būti bent '+min+' simbolių.').show(); return; }
      if(p1!==p2){ $err.text('Slaptažodžiai nesutampa.').show(); return; }
      $lbl.hide();$spin.show();$btn.prop('disabled',true);
      $.post(ss_pub.ajax_url,{action:'ss_set_new_password',token:SS._pwchgToken,password:p1,password2:p2},function(res){
        if(res.success){ $lbl.text('Prisijungiama...').show();$spin.hide();window.location.href=res.data.redirect; }
        else{ $lbl.show();$spin.hide();$btn.prop('disabled',false); $err.text((res.data&&res.data.message)||'Klaida.').show(); }
      }).fail(function(){ $lbl.show();$spin.hide();$btn.prop('disabled',false); $err.text('Serverio klaida.').show(); });
    });
  },

  /* ════ PDF (bet kurio atverto tvarkaraščio: mokytojo/klasės/kabineto) ════ */
  viewPdfBtnHtml:function(kind,id){
    return '<button type="button" class="ss-print-sched ss-view-pdf no-print" data-kind="'+kind+'" data-id="'+id+'" title="Parsisiųsti PDF">'
      + '<svg viewBox="0 0 20 20" fill="currentColor" width="16" height="16"><path fill-rule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm3.293-7.707a1 1 0 011.414 0L9 10.586V3a1 1 0 112 0v7.586l1.293-1.293a1 1 0 111.414 1.414l-3 3a1 1 0 01-1.414 0l-3-3a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>'
      + '<span>PDF</span></button>';
  },
  viewPdfButtons:function(){
    // Tas pats parinkčių modalas ir spausdinimo srautas kaip „mano tvarkaraščio" PDF.
    $(document).on('click','.ss-view-pdf',function(){
      var $b=$(this);
      if($b.prop('disabled')) return;
      $b.prop('disabled',true).addClass('is-loading');
      $.post(ss_pub.ajax_url,{
        action:'ss_get_view_pdf_data',nonce:ss_pub.front_nonce,
        kind:$b.data('kind'),id:$b.data('id')
      },function(res){
        $b.prop('disabled',false).removeClass('is-loading');
        if(!res.success){ SS.toast((res.data&&res.data.message)||'Klaida','err'); return; }
        SS.pdfOptions(res.data);
      }).fail(function(){
        $b.prop('disabled',false).removeClass('is-loading');
        SS.toast('Serverio klaida','err');
      });
    });
  },

  /* ════ LOGIN ════ */
  login:function(){
    var $lc=$('#ss-card-login'),$fc=$('#ss-card-forgot');
    if(!$lc.length) return;
    $('#ss-toggle-pass').on('click',function(){
      var $i=$('#ss-pass'),show=$i.attr('type')==='password';
      $i.attr('type',show?'text':'password');
      $('#ss-eye-on').toggle(!show);$('#ss-eye-off').toggle(show);
    });
    $('#ss-show-forgot').on('click',function(){
      $lc.fadeOut(160,function(){$fc.fadeIn(160);$('#ss-forgot-inp').focus();});
    });
    $('#ss-back-to-login').on('click',function(){
      $fc.fadeOut(160,function(){$lc.fadeIn(160);$('#ss-user').focus();});
    });
    $('#ss-login-form').on('submit',function(e){
      e.preventDefault();
      if(SS._lockUntil && Date.now() < SS._lockUntil) return;   // device locked
      var $btn=$('#ss-login-submit'),$lbl=$btn.find('.ss-btn-lbl'),$spin=$btn.find('.ss-btn-spin');
      var $err=$('#ss-login-err'),$ok=$('#ss-login-ok');
      $lbl.hide();$spin.show();$btn.prop('disabled',true);$err.hide();$ok.hide();
      function resetBtn(){ $lbl.show();$spin.hide();$btn.prop('disabled',false); }
      function tsToken(){ return SS.tsToken('#ss-ts-login'); }
      function tsReset(){ SS.tsReset('#ss-ts-login'); }
      // Turnstile įjungtas, bet žetono dar nėra — palaukti / paprašyti patvirtinti.
      if(ss_pub.ts_enabled && !tsToken()){
        $err.text('Palaukite — tikriname, ar esate žmogus.').show(); resetBtn();
        return;
      }
      function doLogin(retried){
        $.post(ss_pub.ajax_url,{
          action:'ss_do_login',nonce:ss_pub.login_nonce,
          username:$('#ss-user').val().trim(),password:$('#ss-pass').val(),
          remember:$('#ss-remember').is(':checked')?1:0,
          ts_token:tsToken()
        },function(res){
          if(res.success){
            if(res.data.status==='2fa_required'){
              resetBtn();
              SS._pending2faToken=res.data.token;
              var $tc2=$('#ss-card-2fa');
              $lc.fadeOut(160,function(){$tc2.fadeIn(160);$('#ss-2fa-code').val('').focus();});
            } else if(res.data.status==='must_change_pw'){
              resetBtn();
              SS.showNewPw(res.data.token, $lc);
            } else { $lbl.text('Prisijungiama...').show();$spin.hide();window.location.href=res.data.redirect; }
          } else {
            /* Expired nonce returned as JSON — refresh and retry once */
            if(!retried && res.data && res.data.code==='expired_nonce'){
              $.post(ss_pub.ajax_url,{action:'ss_refresh_nonce'},function(r){
                if(r.success){ ss_pub.login_nonce=r.data.login_nonce; doLogin(true); }
                else{ $err.text(res.data.message||'Klaida.').show(); resetBtn(); }
              }).fail(function(){ $err.text(res.data.message||'Klaida.').show(); resetBtn(); });
            } else if(res.data && res.data.code==='rate_limited' && res.data.retry_after>0){
              /* Device temporary lockout — disable the whole form + show a timer */
              $('#ss-pass').val('');
              SS.applyLoginLockout(res.data.retry_after, res.data.message||'Prisijungimas iš šio įrenginio laikinai užblokuotas.');
            } else {
              /* Turnstile žetonas vienkartinis — po bet kokios klaidos
                 (blogas slaptažodis / nepraėjęs iššūkis) generuojam naują. */
              tsReset();
              $err.text(res.data.message||'Klaida.').show(); resetBtn(); $('#ss-pass').val('').focus();
            }
          }
        }).fail(function(xhr){
          /* HTTP 403 — nonce expired or WAF block; try to refresh nonce and retry once */
          if(xhr.status===403 && !retried){
            $.post(ss_pub.ajax_url,{action:'ss_refresh_nonce'},function(r){
              if(r.success){ ss_pub.login_nonce=r.data.login_nonce; doLogin(true); }
              else{ $err.text('Serverio klaida.').show(); resetBtn(); }
            }).fail(function(){ $err.text('Serverio klaida.').show(); resetBtn(); });
          } else {
            $err.text('Serverio klaida.').show(); resetBtn();
          }
        });
      }
      doLogin(false);
    });
    $('#ss-forgot-form').on('submit',function(e){
      e.preventDefault();
      var $btn=$('#ss-forgot-submit'),$lbl=$btn.find('.ss-btn-lbl'),$spin=$btn.find('.ss-btn-spin');
      var $err=$('#ss-forgot-err'),$ok=$('#ss-forgot-ok'),login=$('#ss-forgot-inp').val().trim();
      $err.hide();$ok.hide();
      if(!login){$err.text('Įveskite el. paštą arba vartotojo vardą.').show();return;}
      if(ss_pub.ts_enabled && !SS.tsToken('#ss-ts-forgot')){
        $err.text('Palaukite — tikriname, ar esate žmogus.').show(); return;
      }
      $lbl.hide();$spin.show();$btn.prop('disabled',true);
      function doForgot(retried){
        $.post(ss_pub.ajax_url,{action:'ss_forgot_password',nonce:ss_pub.login_nonce,login:login,ts_token:SS.tsToken('#ss-ts-forgot')},function(res){
          $lbl.show();$spin.hide();$btn.prop('disabled',false);
          if(res.success){$ok.text(res.data.message).show();$('#ss-forgot-inp').val('');SS.tsReset('#ss-ts-forgot');}
          else if(!retried && res.data && res.data.code==='expired_nonce'){
            $.post(ss_pub.ajax_url,{action:'ss_refresh_nonce'},function(r){
              if(r.success){ ss_pub.login_nonce=r.data.login_nonce; doForgot(true); }
              else{ $err.text(res.data.message||'Klaida.').show(); }
            }).fail(function(){ $err.text(res.data.message||'Klaida.').show(); });
          } else { SS.tsReset('#ss-ts-forgot'); $err.text(res.data.message||'Klaida.').show(); }
        }).fail(function(xhr){
          if(xhr.status===403 && !retried){
            $.post(ss_pub.ajax_url,{action:'ss_refresh_nonce'},function(r){
              if(r.success){ ss_pub.login_nonce=r.data.login_nonce; doForgot(true); }
              else{ $err.text('Serverio klaida.').show();$lbl.show();$spin.hide();$btn.prop('disabled',false); }
            }).fail(function(){ $err.text('Serverio klaida.').show();$lbl.show();$spin.hide();$btn.prop('disabled',false); });
          } else { $err.text('Serverio klaida.').show();$lbl.show();$spin.hide();$btn.prop('disabled',false); }
        });
      }
      doForgot(false);
    });

    // Resume an active device lockout after a page reload (localStorage = instant,
    // server = authoritative — covers cleared storage or a fresh tab).
    var savedUntil=parseInt((window.localStorage&&localStorage.getItem('ss_login_lock'))||'0',10);
    var savedLeft=Math.ceil((savedUntil-Date.now())/1000);
    if(savedLeft>0){ SS.applyLoginLockout(savedLeft); }
    $.post(ss_pub.ajax_url,{action:'ss_login_lock_status'},function(r){
      if(r&&r.success&&r.data.locked&&r.data.retry_after>0){ SS.applyLoginLockout(r.data.retry_after); }
    });

    setTimeout(function(){ if(!SS._lockUntil) $('#ss-user').focus(); },200);
  },

  /* Device lockout — disables the WHOLE login form and shows a live MM:SS timer
     with a label. Auto-lifts when it reaches 0 (no reload needed); persists
     across reloads via localStorage. Server stays the source of truth. */
  applyLoginLockout:function(seconds,message){
    seconds=parseInt(seconds,10)||0;
    if(seconds<=0) return;
    var until=Date.now()+seconds*1000;
    if(SS._lockUntil && SS._lockUntil>=until-1500) return;   // already locked at least this long
    SS._lockUntil=until;
    try{ if(window.localStorage) localStorage.setItem('ss_login_lock',String(until)); }catch(e){}

    var $form=$('#ss-login-form');
    var $controls=$('#ss-user,#ss-pass,#ss-remember,#ss-login-submit,#ss-show-forgot,#ss-toggle-pass');
    $controls.prop('disabled',true);
    $form.addClass('ss-form-locked');
    $('#ss-login-err').hide(); $('#ss-login-ok').hide();
    $('#ss-login-submit .ss-btn-lbl').show(); $('#ss-login-submit .ss-btn-spin').hide();

    var $bar=$('#ss-login-lock');
    if(!$bar.length){
      $bar=$('<div id="ss-login-lock" class="ss-login-lock" role="alert"></div>');
      $form.before($bar);
    }
    var LOCK_SVG='<svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/></svg>';
    function fmt(s){ var m=Math.floor(s/60), x=s%60; return m+':'+(x<10?'0':'')+x; }
    function render(left){
      $bar.html(LOCK_SVG
        +'<div class="ss-login-lock-txt">'
        +'<div class="ss-login-lock-msg">'+(message||'Prisijungimas iš šio įrenginio laikinai užblokuotas.')+'</div>'
        +'<div class="ss-login-lock-timer">'+fmt(left)+'</div>'
        +'</div>').show();
    }
    if(SS._lockTimer){ clearInterval(SS._lockTimer); }
    function tick(){
      var left=Math.ceil((SS._lockUntil-Date.now())/1000);
      if(left<=0){
        clearInterval(SS._lockTimer); SS._lockTimer=null; SS._lockUntil=0;
        try{ if(window.localStorage) localStorage.removeItem('ss_login_lock'); }catch(e){}
        $controls.prop('disabled',false);
        $form.removeClass('ss-form-locked');
        $bar.fadeOut(200,function(){ $bar.remove(); });
        $('#ss-user').focus();
        return;
      }
      render(left);
    }
    tick();
    SS._lockTimer=setInterval(tick,1000);
  },

  /* 2FA lockout — tokia pati animacija kaip prisijungimo blokas: užrakina 2FA
     kortelę ir rodo gyvą MM:SS laikmatį. Pasibaigus, pending žetonas serveryje
     jau sunaikintas, todėl vartotojas grąžinamas į prisijungimo kortelę. */
  applyTwoFaLockout:function(seconds,message){
    seconds=parseInt(seconds,10)||0;
    if(seconds<=0) return;
    var until=Date.now()+seconds*1000;
    if(SS._fa2LockUntil && SS._fa2LockUntil>=until-1500) return;
    SS._fa2LockUntil=until;

    var $card=$('#ss-card-2fa');
    var $controls=$('#ss-2fa-code,#ss-2fa-submit,#ss-2fa-use-backup,#ss-2fa-back');
    $controls.prop('disabled',true);
    $card.addClass('ss-form-locked');
    $('#ss-2fa-err').hide();
    $('#ss-2fa-submit .ss-btn-lbl').show(); $('#ss-2fa-submit .ss-btn-spin').hide();

    var $bar=$('#ss-2fa-lock');
    if(!$bar.length){
      $bar=$('<div id="ss-2fa-lock" class="ss-login-lock" role="alert"></div>');
      $card.before($bar);   // virš kortelės — kad laikmatis neprigestų (.ss-form-locked)
    }
    var LOCK_SVG='<svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/></svg>';
    function fmt(s){ var m=Math.floor(s/60), x=s%60; return m+':'+(x<10?'0':'')+x; }
    function render(left){
      $bar.html(LOCK_SVG
        +'<div class="ss-login-lock-txt">'
        +'<div class="ss-login-lock-msg">'+(message||'Per daug neteisingų kodų. Prisijungimas laikinai užblokuotas.')+'</div>'
        +'<div class="ss-login-lock-timer">'+fmt(left)+'</div>'
        +'</div>').show();
    }
    if(SS._fa2LockTimer){ clearInterval(SS._fa2LockTimer); }
    function tick(){
      var left=Math.ceil((SS._fa2LockUntil-Date.now())/1000);
      if(left<=0){
        clearInterval(SS._fa2LockTimer); SS._fa2LockTimer=null; SS._fa2LockUntil=0;
        $controls.prop('disabled',false);
        $card.removeClass('ss-form-locked');
        $bar.fadeOut(200,function(){ $bar.remove(); });
        SS._pending2faToken=null;   // žetonas serveryje jau sunaikintas
        $card.fadeOut(160,function(){ $('#ss-card-login').fadeIn(160); $('#ss-user').focus(); });
        return;
      }
      render(left);
    }
    tick();
    SS._fa2LockTimer=setInterval(tick,1000);
  },

  /* ════ REGISTRATION (multi-step) ════ */
  register:function(){
    var $rc=$('#ss-card-register'), $lc=$('#ss-card-login'), $fc=$('#ss-card-forgot');
    if(!$rc.length) return;

    var state={ email:'', role:'student', first_name:'', last_name:'', class_level:'', class_letter:'', password:'', prelinked_class:null };

    function isTeacherStyleEmail(em){
      var local = String(em||'').toLowerCase().split('@')[0]||'';
      return local && !/^\d/.test(local);
    }

    function showStep(n){
      $rc.find('.ss-reg-step-form').hide();
      if (typeof n === 'number') {
        $rc.find('.ss-reg-step-form[data-step="'+n+'"]').show();
      } else {
        $rc.find('.ss-reg-step-form[data-step="'+n+'"]').show();
      }
      $('#ss-reg-success').toggle(n===5);
      $('#ss-reg-pending').toggle(n===6);
      // Step indicator (only meaningful for numeric steps 1..4)
      var nn = (typeof n === 'number') ? n : (n==='role'?2:(n==='staff'?3:1));
      $rc.find('.ss-reg-step').each(function(){
        var s=parseInt($(this).data('step'),10);
        $(this).toggleClass('active', s<=nn && nn<=4).toggleClass('done', s<nn && nn<=4);
      });
      $('#ss-reg-err').hide(); $('#ss-reg-ok').hide();
    }

    function setBusy($btn,busy){
      $btn.prop('disabled',busy);
      $btn.find('.ss-btn-lbl').toggle(!busy);
      $btn.find('.ss-btn-spin').toggle(busy);
    }
    function showErr(msg){ $('#ss-reg-err').text(msg).show(); $('#ss-reg-ok').hide(); }
    function showOk(msg){ $('#ss-reg-ok').text(msg).show(); $('#ss-reg-err').hide(); }

    /* ── Slaptažodžio reikalavimai (rodomi iš karto, tikrinami prieš pereinant toliau) ── */
    function pwRules(){ return window.SS_PW_RULES || { min:8, upper:true, digit:true }; }
    function pwError(pw){
      var r = pwRules();
      pw = String(pw||'');
      if (pw.length < r.min)                       return 'Slaptažodis turi būti bent ' + r.min + ' simbolių.';
      if (r.upper && !/[A-ZĄČĘĖĮŠŲŪŽ]/.test(pw))   return 'Slaptažodyje turi būti bent viena didžioji raidė.';
      if (r.digit && !/[0-9]/.test(pw))            return 'Slaptažodyje turi būti bent vienas skaičius.';
      return null;
    }
    function updatePwReqs(inputId){
      var pw = String($('#'+inputId).val()||''), r = pwRules();
      var $list = $('.ss-pw-reqs[data-for="'+inputId+'"]');
      $list.find('li[data-rule="len"]').toggleClass('ok',   pw.length >= r.min);
      $list.find('li[data-rule="upper"]').toggleClass('ok', /[A-ZĄČĘĖĮŠŲŪŽ]/.test(pw));
      $list.find('li[data-rule="digit"]').toggleClass('ok', /[0-9]/.test(pw));
    }
    $(document).on('input', '#ss-reg-pass',       function(){ updatePwReqs('ss-reg-pass'); });
    $(document).on('input', '#ss-reg-staff-pass', function(){ updatePwReqs('ss-reg-staff-pass'); });

    $('#ss-show-register').on('click',function(){
      $lc.fadeOut(140,function(){ $fc.hide(); state.email=''; state.prelinked_class=null; showStep(1); $rc.fadeIn(140,function(){$('#ss-reg-email').focus();}); });
    });
    $('#ss-reg-back-to-login, #ss-reg-done-btn, #ss-reg-pending-btn').on('click',function(){
      $rc.fadeOut(140,function(){ $lc.fadeIn(140,function(){$('#ss-user').focus();}); });
    });

    /* Step 1: send code */
    $('#ss-reg-form-email').on('submit',function(e){
      e.preventDefault();
      var em=($('#ss-reg-email').val()||'').trim().toLowerCase();
      if(!em){ showErr('Įveskite el. paštą.'); return; }
      if(em.indexOf('@azuolynogimnazija.lt')!==em.length-'@azuolynogimnazija.lt'.length){
        showErr('Leidžiamas tik mokyklinis el. paštas.');
        return;
      }
      if(ss_pub.ts_enabled && !SS.tsToken('#ss-ts-register')){
        showErr('Palaukite — tikriname, ar esate žmogus.'); return;
      }
      var $btn=$('#ss-reg-send-btn'); setBusy($btn,true);
      $.post(ss_pub.ajax_url,{ action:'ss_register_send_code', email:em, ts_token:SS.tsToken('#ss-ts-register') },function(res){
        setBusy($btn,false);
        if(res.success){
          state.email=em;
          $('#ss-reg-mailto').text(em);
          SS.tsReset('#ss-ts-register');  /* naujas žetonas pakartotiniam siuntimui */
          showStep(2);   /* step 2 already shows „Kodas išsiųstas į <email>" — no duplicate alert */
          setTimeout(function(){$('#ss-reg-code').focus();},80);
        } else {
          SS.tsReset('#ss-ts-register');
          showErr((res.data&&res.data.message)||'Klaida.');
        }
      }).fail(function(){ setBusy($btn,false); SS.tsReset('#ss-ts-register'); showErr('Serverio klaida.'); });
    });

    /* Resend code */
    $('#ss-reg-resend').on('click',function(){
      if(!state.email) return;
      var $btn=$(this); $btn.prop('disabled',true).text('Siunčiama...');
      $.post(ss_pub.ajax_url,{ action:'ss_register_send_code', email:state.email, ts_token:SS.tsToken('#ss-ts-register') },function(res){
        $btn.prop('disabled',false).text('Siųsti naują kodą');
        if(res.success){ SS.tsReset('#ss-ts-register'); showOk(res.data.message||'Kodas išsiųstas iš naujo.'); }
        else{ SS.tsReset('#ss-ts-register'); showErr((res.data&&res.data.message)||'Klaida.'); }
      }).fail(function(){ $btn.prop('disabled',false).text('Siųsti naują kodą'); showErr('Serverio klaida.'); });
    });

    /* Step 2: verify code */
    $('#ss-reg-form-code').on('submit',function(e){
      e.preventDefault();
      var code=($('#ss-reg-code').val()||'').replace(/\D/g,'');
      if(code.length!==6){ showErr('Kodas turi būti 6 skaitmenys.'); return; }
      var $btn=$('#ss-reg-verify-btn'); setBusy($btn,true);
      $.post(ss_pub.ajax_url,{ action:'ss_register_verify_code', email:state.email, code:code },function(res){
        setBusy($btn,false);
        if(res.success){
          // Rolės pasirinkimas leidžiamas TIK ne skaitmenimis prasidedantiems el. paštams
          // (pvz. vardenis.pavardenis@…). Skaitmenimis prasidedantys (pvz.
          // 27.vardenis.pavardenis@…) — visada mokiniai.
          if (isTeacherStyleEmail(state.email)) {
            showStep('role');
          } else {
            state.role = 'student';
            showStep(3);
            setTimeout(function(){$('#ss-reg-first').focus();},80);
          }
        }
        else{ showErr((res.data&&res.data.message)||'Klaida.'); }
      }).fail(function(){ setBusy($btn,false); showErr('Serverio klaida.'); });
    });

    /* Role choice — student / teacher / admin */
    $(document).on('click', '.ss-role-btn', function(){
      var role = $(this).data('role');
      state.role = role;
      if (role === 'student') {
        showStep(3);
        setTimeout(function(){$('#ss-reg-first').focus();},80);
      } else {
        var intro = 'Įveskite savo vardą ir pavardę. Administratoriaus paskyra bus išsiųsta patikrai.';
        if (role === 'teacher')  intro = 'Įveskite savo vardą ir pavardę. Pagal juos parinksime mokytojo paskyrą.';
        if (role === 'employee') intro = 'Įveskite savo vardą ir pavardę. Darbuotojo paskyra bus išsiųsta patikrai.';
        $('#ss-staff-intro').text(intro);
        showStep('staff');
        setTimeout(function(){$('#ss-reg-staff-first').focus();},80);
      }
    });
    $(document).on('click', '#ss-reg-staff-back', function(){
      showStep('role');
    });

    /* Staff submission */
    $('#ss-reg-form-staff').on('submit', function(e){
      e.preventDefault();
      var first=($('#ss-reg-staff-first').val()||'').trim();
      var last =($('#ss-reg-staff-last').val()||'').trim();
      var p1=$('#ss-reg-staff-pass').val(), p2=$('#ss-reg-staff-pass2').val();
      if(!first||!last){ showErr('Užpildykite vardą ir pavardę.'); return; }
      var pwe1=pwError(p1); if(pwe1){ showErr(pwe1); return; }
      if(p1!==p2){ showErr('Slaptažodžiai nesutampa.'); return; }
      var $btn=$('#ss-reg-staff-submit'); setBusy($btn,true);
      $.post(ss_pub.ajax_url,{
        action:'ss_register_submit',
        email: state.email,
        first_name: first,
        last_name: last,
        password: p1,
        mode: 'staff',
        reg_role: state.role
      }, function(res){
        setBusy($btn,false);
        if (res.success) {
          if (res.data.status === 'approved') {
            // Logged in server-side → straight to the dashboard onboarding.
            if (res.data.redirect) { window.location.href = res.data.redirect; return; }
            $('#ss-reg-success-title').text('Registracija sėkminga!');
            $('#ss-reg-success-message').html(res.data.message || 'Jūsų paskyra sukurta.');
            showStep(5);
          } else {
            showStep(6);
          }
        } else {
          showErr((res.data&&res.data.message) || 'Klaida.');
        }
      }).fail(function(){ setBusy($btn,false); showErr('Serverio klaida.'); });
    });

    /* Live class preview I-IV + letter */
    function refreshClassPreview(){
      var lvl=$('#ss-reg-level').val()||'?';
      var ltr=($('#ss-reg-letter').val()||'').toLowerCase();
      $('#ss-reg-class-preview').text('Klasė: '+(lvl==='?'?'?':lvl)+(ltr||''));
    }
    $('#ss-reg-level, #ss-reg-letter').on('input change',refreshClassPreview);

    /* Step 3: submit details - go to step 4 (schedule code) */
    $('#ss-reg-form-details').on('submit',function(e){
      e.preventDefault();
      var first=($('#ss-reg-first').val()||'').trim();
      var last =($('#ss-reg-last').val()||'').trim();
      var lvl  =$('#ss-reg-level').val();
      var ltr  =($('#ss-reg-letter').val()||'').trim();
      var p1   =$('#ss-reg-pass').val();
      var p2   =$('#ss-reg-pass2').val();
      if(!first||!last||!lvl||!ltr){ showErr('Užpildykite visus laukus.'); return; }
      if(!/^[A-Za-zĄČĘĖĮŠŲŪŽąčęėįšųūž]{1,3}$/u.test(ltr)){ showErr('Klasės raidė turi būti 1–3 raidės.'); return; }
      var pwe=pwError(p1); if(pwe){ showErr(pwe); return; }
      if(p1!==p2){ showErr('Slaptažodžiai nesutampa.'); return; }
      
      // Save to state
      state.first_name = first;
      state.last_name = last;
      state.class_level = lvl;
      state.class_letter = ltr;
      state.password = p1;
      
      // Check if email is pre-linked to a class
      var $btn=$('#ss-reg-submit-btn'); setBusy($btn,true);
      $.post(ss_pub.ajax_url,{ action:'ss_check_email_link', nonce:ss_pub.login_nonce, email:state.email },function(res){
        setBusy($btn,false);
        if(res.success && res.data.linked){
          // Email is pre-linked - show prelinked panel
          state.prelinked_class = res.data;
          $('#ss-prelinked-class').text(res.data.class_name);
          $('#ss-schedule-prelinked').show();
          $('#ss-schedule-code-entry').hide();
        } else {
          // Not pre-linked - show code entry panel
          state.prelinked_class = null;
          $('#ss-schedule-prelinked').hide();
          $('#ss-schedule-code-entry').show();
        }
        showStep(4);
        setTimeout(function(){$('#ss-schedule-code').focus();},80);
      }).fail(function(){
        setBusy($btn,false);
        // On error, default to code entry
        state.prelinked_class = null;
        $('#ss-schedule-prelinked').hide();
        $('#ss-schedule-code-entry').show();
        showStep(4);
      });
    });

    /* Step 4: finish registration with schedule code, prelink, or skip */
    function submitRegistration(mode, scheduleCode) {
      var $btn = mode === 'prelinked' ? $('#ss-reg-finish-prelinked') : 
                 mode === 'code' ? $('#ss-reg-finish-code') : null;
      if($btn) setBusy($btn, true);
      
      $.post(ss_pub.ajax_url,{
        action:'ss_register_submit',
        email: state.email,
        first_name: state.first_name,
        last_name: state.last_name,
        class_level: state.class_level,
        class_letter: state.class_letter,
        password: state.password,
        schedule_code: scheduleCode || '',
        mode: mode
      },function(res){
        if($btn) setBusy($btn, false);
        if(res.success){
          if(res.data.status === 'approved'){
            // Auto-approved & logged in server-side → straight to the dashboard.
            if(res.data.redirect){ window.location.href = res.data.redirect; return; }
            $('#ss-reg-success-title').text('Registracija sėkminga!');
            $('#ss-reg-success-message').html('Jūsų paskyra sukurta ir priskirta prie tvarkaraščio <strong>'+res.data.class_name+'</strong>.<br>Dabar galite prisijungti su savo el. paštu ir slaptažodžiu.');
            showStep(5);
          } else {
            // Pending manual approval
            showStep(6);
          }
        } else {
          showErr((res.data&&res.data.message)||'Klaida.');
        }
      }).fail(function(){ if($btn) setBusy($btn, false); showErr('Serverio klaida.'); });
    }

    $('#ss-reg-form-schedule').on('submit', function(e){
      e.preventDefault();
      var $btn = $(document.activeElement);
      var mode = $btn.data('mode') || 'code';
      
      if(mode === 'prelinked'){
        submitRegistration('prelinked', '');
      } else if(mode === 'code'){
        var code = ($('#ss-schedule-code').val()||'').replace(/[^A-Za-z0-9]/g,'').toUpperCase();
        if(code.length !== 8){
          showErr('Kodas turi būti 8 simboliai.');
          return;
        }
        submitRegistration('code', code);
      }
    });

    $('#ss-reg-skip-code').on('click', function(){
      submitRegistration('skip', '');
    });
  },

  /* ════ DASHBOARD ════ */
  dashboard:function(){
    if(!$('#ss-app').length) return;
    this.tabs();this.dayTabs();this.viewToggle();this.mobileMenu();
    this.logout();this.subgroups();this.weekSubgroups();this.personal();this.pdf();
    this.teacherSchedules();this.roomSchedules();this.allSchedules();this.teacherSlotGroups();this.settings();
    this.onboarding();
  },

  /* First-run onboarding wizard (rendered only when ss_needs_onboarding is set). */
  onboarding:function(){
    var $ob=$('#ss-onboarding'); if(!$ob.length) return;
    var nonce=ss_pub.front_nonce, step=1, max=6, done=false;
    document.body.style.overflow='hidden';
    function show(n){
      step=Math.max(1,Math.min(max,n));
      $ob.find('.ss-onb-step').hide();
      $ob.find('.ss-onb-step[data-ob="'+step+'"]').show();
      $ob.find('.ss-onb-progress span').css('width',(step/max*100)+'%');
      $ob.find('.ss-onb-back').toggle(step>1);
      $ob.find('.ss-onb-next').text(step===max?'Baigti':'Toliau');
    }
    show(1);
    $ob.on('click','.ss-onb-next',function(){ if(step<max) show(step+1); else finish(); });
    $ob.on('click','.ss-onb-back',function(){ show(step-1); });
    $ob.on('click','.ss-onb-skip',function(){ finish(); });

    // Crop + upload (same flow as Nustatymai page)
    var obCropper=null;
    function obUploadAvatar(blob,filename){
      var fd=new FormData();
      fd.append('action','ss_settings_upload_avatar'); fd.append('nonce',nonce); fd.append('avatar',blob,filename);
      var $b=$ob.find('.ss-onb-av-btn'); $b.addClass('is-busy');
      $.ajax({url:ss_pub.ajax_url,type:'POST',data:fd,processData:false,contentType:false,dataType:'json',
        success:function(r){
          $b.removeClass('is-busy');
          if(r.success){
            var u=r.data.url+'?v='+Date.now();
            $('#ss-onb-av-img').attr('src',u);
            $('.ss-hdr-avatar').attr('src',u);
            SS.toast('Nuotrauka įkelta.');
          } else { SS.toast(r.data&&r.data.message?r.data.message:'Klaida.',true); }
        },
        error:function(){ $b.removeClass('is-busy'); SS.toast('Ryšio klaida.',true); }
      });
    }
    function obCloseCrop(){
      $('#ss-onb-crop-modal').css('display','none');
      if(obCropper){ obCropper.destroy(); obCropper=null; }
    }

    $ob.on('change','#ss-onb-avatar-input',function(){
      var f=this.files[0]; if(!f) return;
      if(f.size>3*1024*1024){ SS.toast('Failas per didelis (maks. 3 MB).',true); this.value=''; return; }
      if(window.Cropper){
        var reader=new FileReader();
        reader.onload=function(e){
          var $img=$('#ss-onb-crop-img').attr('src',e.target.result);
          $('#ss-onb-crop-modal').css('display','flex');
          if(obCropper){ obCropper.destroy(); obCropper=null; }
          obCropper=new Cropper($img[0],{
            aspectRatio:1, viewMode:1, dragMode:'move', autoCropArea:0.9,
            responsive:true, checkOrientation:true, background:false
          });
        };
        reader.readAsDataURL(f);
      } else {
        // No crop library available → upload original.
        obUploadAvatar(f, f.name||'avatar.jpg');
      }
      this.value='';
    });

    $('#ss-onb-crop-cancel').on('click', obCloseCrop);
    $('#ss-onb-crop-confirm').on('click',function(){
      if(!obCropper) return;
      var canvas=obCropper.getCroppedCanvas({width:300,height:300,imageSmoothingQuality:'high'});
      obCloseCrop();
      canvas.toBlob(function(blob){ obUploadAvatar(blob,'avatar.jpg'); },'image/jpeg',0.92);
    });

    function finish(){
      if(done) return; done=true;
      obCloseCrop();
      $('#ss-onb-crop-modal').remove();
      var showName=$ob.find('#ss-onb-showname').is(':checked');
      var nd=$ob.find('#ss-onb-newdevice').is(':checked')?1:0;
      var cn=$ob.find('#ss-onb-changes').is(':checked')?1:0;
      $.post(ss_pub.ajax_url,{action:'ss_settings_save_anon_quiz',nonce:nonce,hide:showName?0:1});
      $.post(ss_pub.ajax_url,{action:'ss_settings_save_new_device',nonce:nonce,on:nd});
      $.post(ss_pub.ajax_url,{action:'ss_settings_save_changes_notify',nonce:nonce,on:cn,mode:'immediate'});
      $.post(ss_pub.ajax_url,{action:'ss_onboarding_finish',nonce:nonce});
      $ob.fadeOut(200,function(){ $ob.remove(); document.body.style.overflow=''; });
    }
  },

  tabs:function(){
    $(document).on('click','.ss-hnav-btn[data-tab]',function(e){
      e.preventDefault();
      var t=$(this).data('tab');
      // Update all matching nav buttons (desktop + mobile)
      $('.ss-hnav-btn[data-tab]').removeClass('active');
      $('.ss-hnav-btn[data-tab="'+t+'"]').addClass('active');
      // Switch tab sections
      $('.ss-tab').removeClass('active');
      $('#tab-'+t).addClass('active');
      // Close mobile nav
      $('#ss-mobile-nav').removeClass('open');
      $('#ss-hamburger').removeClass('is-open').attr('aria-expanded','false');
      // Scroll to top of content on mobile
      if(window.innerWidth<769){
        $('html,body').animate({scrollTop:0},150);
      }
    });
  },

  dayTabs:function(){
    var today=parseInt(ss_pub.today)||0;
    var activeDay=Math.min(Math.max(parseInt(ss_pub.active_day)||1,1),5);
    SS.activateDay(activeDay);
    $(document).on('click','.ss-dtab',function(){ SS.activateDay(parseInt($(this).data('day'))); });
  },
  activateDay:function(d){
    $('.ss-dtab').removeClass('active').attr('aria-selected','false');
    $('.ss-dtab[data-day="'+d+'"]').addClass('active').attr('aria-selected','true');
    $('.ss-dpanel').removeClass('active');$('.ss-dpanel[data-day="'+d+'"]').addClass('active');
    var $b=$('.ss-dtab[data-day="'+d+'"]')[0];
    if($b&&window.innerWidth<600) $b.scrollIntoView({behavior:'smooth',inline:'center',block:'nearest'});
  },

  viewToggle:function(){
    $('#ss-view-day').on('click',function(){
      $(this).addClass('active');$('#ss-view-week').removeClass('active');
      $('#ss-day-view').show();$('#ss-week-view').hide();
    });
    $('#ss-view-week').on('click',function(){
      $(this).addClass('active');$('#ss-view-day').removeClass('active');
      $('#ss-week-view').show();$('#ss-day-view').hide();
      $('html,body').animate({scrollTop:Math.max(0,$('#ss-week-view').offset().top-80)},200);
    });
  },

  mobileMenu:function(){
    var $nav=$('#ss-mobile-nav'),$btn=$('#ss-hamburger');
    function setOpen(open){
      $nav.toggleClass('open',open);
      $btn.toggleClass('is-open',open).attr('aria-expanded',open?'true':'false');
      document.body.style.overflow=open?'hidden':'';   /* lock page scroll behind the drawer */
    }
    $btn.on('click',function(e){
      e.stopPropagation();
      setOpen(!$nav.hasClass('open'));
    });
    // Click the dim backdrop (or anywhere outside) to close.
    $(document).on('click',function(e){
      if($nav.hasClass('open')&&!$(e.target).closest('#ss-mobile-nav,#ss-hamburger').length){
        setOpen(false);
      }
    });
    // Choosing a menu item closes the drawer.
    $nav.on('click','.ss-hnav-btn',function(){ setOpen(false); });
  },

  logout:function(){
    function doLogout(){
      $.post(ss_pub.ajax_url,{action:'ss_do_logout',nonce:ss_pub.logout_nonce},function(res){
        if(res.success) window.location.href=res.data.redirect;
      });
    }
    $('#ss-do-logout').on('click',function(){$(this).prop('disabled',true);doLogout();});
    $('#ss-do-logout-mob').on('click',doLogout);
  },

  /* ════ DAY VIEW SUBGROUPS ════ */
  subgroups:function(){
    var _subLocked=false;
    $(document).on('click','.ss-sub-btn',function(){
      if(_subLocked) return;
      _subLocked=true;
      var $btn=$(this),schedId=$btn.data('id'),classId=$btn.data('class'),day=$btn.data('day'),period=$btn.data('period');
      $btn.siblings('.ss-sub-btn').removeClass('active');$btn.addClass('active');
      // Disable all subgroup buttons in this period and show spinner on clicked btn
      var $allBtns=$btn.closest('.ss-sub-picker').find('.ss-sub-btn');
      $allBtns.prop('disabled',true);
      $btn.addClass('ss-sub-loading').html($btn.html()+'<span class="ss-sub-spinner"></span>');
      $.post(ss_pub.ajax_url,{action:'ss_save_subgroup_pref',nonce:ss_pub.front_nonce,
        schedule_id:schedId,class_id:classId,day:day,period_id:period},
      function(res){
        if(res.success){SS.toast('Pogrupis pasirinktas!');setTimeout(function(){location.reload();},400);}
        else{_subLocked=false;$allBtns.prop('disabled',false);$btn.removeClass('ss-sub-loading').find('.ss-sub-spinner').remove();}
      }).fail(function(){_subLocked=false;$allBtns.prop('disabled',false);$btn.removeClass('ss-sub-loading').find('.ss-sub-spinner').remove();});
    });
  },

  /* ════ WEEK VIEW SUBGROUP PICKER ════ */
  weekSubgroups:function(){
    var $popup=$('#ss-wt-sub-popup');
    if(!$popup.length) return;

    $(document).on('click','.ss-wt-sub-trigger',function(e){
      e.stopPropagation();
      var $trigger=$(this);
      var $cell=$trigger.closest('.ss-wt-cell');
      var pid=$cell.data('period'),day=$cell.data('day'),classId=$cell.data('class');
      var $data=$cell.find('.ss-wt-sub-data');
      if(!$data.length) return;

      // Build popup content
      var html='<div class="ss-wtsub-title">Pasirinkite pogrupį</div>';
      $data.find('.ss-wt-sub-item').each(function(){
        var active=$(this).hasClass('active')?'ss-wtsub-active':'';
        html+='<button class="ss-wtsub-item '+active+'" '
          +'data-id="'+$(this).data('id')+'" '
          +'data-color="'+$(this).data('color')+'" '
          +'data-class="'+classId+'" data-day="'+day+'" data-period="'+pid+'">'
          +$(this).text()+'</button>';
      });
      $popup.html(html);

      // Position popup near trigger
      var off=$trigger.offset(),pw=180,ph=120;
      var left=off.left+$trigger.outerWidth()+4;
      var top=off.top;
      if(left+pw>window.innerWidth) left=off.left-pw-4;
      if(top+ph>window.innerHeight) top=window.innerHeight-ph-8;
      $popup.css({left:left+'px',top:top+'px'}).show();

      setTimeout(function(){
        $(document).one('click.wtsub',function(e2){
          if(!$(e2.target).closest('#ss-wt-sub-popup').length) $popup.hide();
        });
      },50);
    });

    // Click on popup item
    $(document).on('click','.ss-wtsub-item',function(){
      var $btn=$(this);
      if($btn.hasClass('ss-wtsub-loading')) return;
      // lock all items in the popup
      $popup.find('.ss-wtsub-item').prop('disabled',true).addClass('ss-wtsub-loading');
      $btn.html('<span class="ss-sub-spinner ss-sub-spinner--dark"></span> '+$btn.text());
      var schedId=$btn.data('id'),classId=$btn.data('class'),day=$btn.data('day'),period=$btn.data('period');
      $.post(ss_pub.ajax_url,{action:'ss_save_subgroup_pref',nonce:ss_pub.front_nonce,
        schedule_id:schedId,class_id:classId,day:day,period_id:period},
      function(res){
        if(res.success){$popup.hide();SS.toast('Pogrupis pasirinktas!');setTimeout(function(){location.reload();},400);}
        else{$popup.find('.ss-wtsub-item').prop('disabled',false).removeClass('ss-wtsub-loading');}
      }).fail(function(){$popup.find('.ss-wtsub-item').prop('disabled',false).removeClass('ss-wtsub-loading');});
    });
  },

  /* ════ PDF ════ — REPLACED — see renderPdf below */
  pdf:function(){
    $('#ss-download-pdf').on('click',function(){
      var $btn=$(this);
      if($btn.prop('disabled')) return;
      // Loading state while the schedule data is fetched (modal opens afterwards).
      $btn.prop('disabled',true).addClass('is-loading');
      $.post(ss_pub.ajax_url,{action:'ss_get_pdf_data',nonce:ss_pub.front_nonce},function(res){
        $btn.prop('disabled',false).removeClass('is-loading');
        if(!res.success){ SS.toast((res.data&&res.data.message)||'Klaida','err'); return; }
        SS.pdfOptions(res.data);
      }).fail(function(){
        $btn.prop('disabled',false).removeClass('is-loading');
        SS.toast('Serverio klaida','err');
      });
    });
  },

  /* PDF options modal — content / page size / colour + live preview */
  pdfOptions:function(data){
    $('#ss-pdf-opts').remove();
    var seg=function(group,items){
      var h='<div class="ss-pdfo-seg" data-group="'+group+'">';
      items.forEach(function(it,i){
        h+='<button type="button" class="ss-pdfo-chip'+(i===0?' active':'')+'" data-val="'+it.v+'">'+it.l+'</button>';
      });
      return h+'</div>';
    };
    var mode    = data.mode || 'student';
    var hasLogo = !!data.logo_b64;

    var settings=''
      +'<div class="ss-pdfo-fld"><label>Turinys</label>'
        +seg('content',[{v:'full',l:'Visas tvarkaraštis'},{v:'times',l:'Tik pamokų laikas'}])+'</div>';
    if(mode==='student'){
      settings+='<div class="ss-pdfo-fld"><label>Grupės</label>'
        +seg('groups',[{v:'selected',l:'Tik pasirinktos'},{v:'all',l:'Visos grupės'}])+'</div>';
    }
    if(mode==='teacher' && data.can_imp_lists){
      // Turintys teisę matyti IMP mokinių sąrašus gali rinktis: sąrašas ar tik skaičius.
      settings+='<div class="ss-pdfo-fld"><label>IMP mokiniai</label>'
        +seg('implist',[{v:'names',l:'Mokinių sąrašai'},{v:'count',l:'Tik skaičius'}])+'</div>';
    }
    settings+='<div class="ss-pdfo-fld"><label>Orientacija</label>'
        +seg('orient',[{v:'landscape',l:'Gulsčias'},{v:'portrait',l:'Stačias'}])+'</div>'
      +'<div class="ss-pdfo-fld"><label>Spalvos</label>'
        +seg('color',[{v:'color',l:'Spalvotas'},{v:'bw',l:'Nespalvotas'}])+'</div>';
    if(hasLogo){
      settings+='<div class="ss-pdfo-fld"><label>Logotipas</label>'
        +seg('logo',[{v:'on',l:'Su logotipu'},{v:'off',l:'Be logotipo'}])+'</div>';
    }

    var html=''
      +'<div class="ss-pdfo-overlay" id="ss-pdf-opts">'
      +'<div class="ss-pdfo-box ss-pdfo-box-wide">'
      +'<div class="ss-pdfo-head"><h3>Spausdinti tvarkaraštį</h3><button type="button" class="ss-pdfo-x" aria-label="Uždaryti">✕</button></div>'
      +'<div class="ss-pdfo-cols">'
      +'<div class="ss-pdfo-body">'+settings+'</div>'
      +'<div class="ss-pdfo-preview"><div class="ss-pdfo-preview-label">Peržiūra</div><div class="ss-pdfo-preview-inner" id="ss-pdfo-preview"></div></div>'
      +'</div>'
      +'<div class="ss-pdfo-foot">'
        +'<button type="button" class="ss-pdfo-cancel">Atšaukti</button>'
        +'<button type="button" class="ss-pdfo-print"><svg viewBox="0 0 20 20" fill="currentColor" width="15" height="15" style="vertical-align:-2px;margin-right:5px"><path fill-rule="evenodd" d="M5 4v3H4a2 2 0 00-2 2v3a2 2 0 002 2h1v2a1 1 0 001 1h8a1 1 0 001-1v-2h1a2 2 0 002-2V9a2 2 0 00-2-2h-1V4a1 1 0 00-1-1H6a1 1 0 00-1 1zm8 0H7v3h6V4zm0 8H7v4h6v-4z" clip-rule="evenodd"/></svg>Spausdinti</button>'
      +'</div>'
      +'</div></div>';
    var $m=$(html).appendTo('body');
    document.body.style.overflow='hidden';
    function close(){
      $m.remove(); document.body.style.overflow='';
    }
    function getOpts(){
      var opts={};
      $m.find('.ss-pdfo-seg').each(function(){
        opts[$(this).data('group')]=$(this).find('.ss-pdfo-chip.active').data('val');
      });
      return opts;
    }
    /* The on-screen preview uses the EXACT same HTML that gets printed, so what
       the user sees is what comes out of the printer (or „Išsaugoti kaip PDF"). */
    function refresh(){
      $('#ss-pdfo-preview').html('<div class="ss-pdfo-htmlpv">'+SS.pdfPreviewHtml(data,getOpts())+'</div>');
    }
    refresh();

    $m.on('click','.ss-pdfo-chip',function(){
      var $g=$(this).closest('.ss-pdfo-seg');
      $g.find('.ss-pdfo-chip').removeClass('active');
      $(this).addClass('active');
      refresh();
    });
    $m.on('click','.ss-pdfo-x,.ss-pdfo-cancel',close);
    $m.on('click',function(e){ if(e.target===this) close(); });

    // Print the schedule. Rendered into a hidden <iframe> and printed from there —
    // no pop-up window, so pop-up blockers never interfere. The browser's print
    // dialog also lets the user „Išsaugoti kaip PDF".
    $m.on('click','.ss-pdfo-print',function(){
      SS.printSchedule(data,getOpts());
    });
  },

  /* Live HTML preview of the schedule (also reused for the print window) */
  pdfPreviewHtml:function(data,opts){
    opts=opts||{};
    var periods=data.periods||[], mode=data.mode||'student';
    var grid=SS.filterGridForPdf(data.grid, data.subgroup_prefs, mode, (opts.groups==='all'));
    var colored=(opts.color||'color')==='color';
    if((opts.content||'full')==='times'){
      var t='<table class="ss-pv-tbl"><thead><tr><th>Pamoka</th><th>Pradžia</th><th>Pabaiga</th></tr></thead><tbody>';
      periods.forEach(function(p){
        t+='<tr><td>'+p.period_number+'</td><td>'+(p.start_time||'').substr(0,5)+'</td><td>'+(p.end_time||'').substr(0,5)+'</td></tr>';
      });
      return t+'</tbody></table>';
    }
    // Personal items → placed in the matching period cell (clamped into the grid,
    // so out-of-bounds times never drift off the page).
    var personalCell={};
    (data.personal||[]).forEach(function(it){
      var st=(it.start_time||'').substr(0,5), target=null, lastP=null;
      for(var i=0;i<periods.length;i++){
        lastP=periods[i];
        if(st < (periods[i].end_time||'').substr(0,5)){ target=periods[i]; break; }
      }
      if(!target) target=lastP;
      if(target){
        var k=target.id; personalCell[k]=personalCell[k]||{};
        (personalCell[k][it.day_of_week]=personalCell[k][it.day_of_week]||[]).push(it);
      }
    });
    var days=['Pirmadienis','Antradienis','Trečiadienis','Ketvirtadienis','Penktadienis'];
    var h='<table class="ss-pv-tbl ss-pv-grid'+(colored?'':' ss-pv-bw')+'"><thead><tr><th class="ss-pv-th-time">Laikas</th>';
    days.forEach(function(d){ h+='<th>'+d+'</th>'; });
    h+='</tr></thead><tbody>';
    periods.forEach(function(p){
      h+='<tr><td class="ss-pv-time"><b>'+p.period_number+'</b><span>'+(p.start_time||'').substr(0,5)+'</span><span>'+(p.end_time||'').substr(0,5)+'</span></td>';
      for(var d=1;d<=5;d++){
        var entries=(grid[p.id]&&grid[p.id][d])?grid[p.id][d]:[];
        h+='<td>';
        if(mode==='teacher'){
          // Mokytojo PDF: visos to paties slot'o klasės (ir visi IMP) — VIENAME
          // langelyje pagal tas pačias taisykles kaip ekrane.
          h+=SS.pdfTeacherCellBox(entries,opts,colored);
        } else {
        entries.forEach(function(e){
          if((e.is_free==1||e.is_free==='1')&&!e.subject) return; // „nėra pamokos" pogrupis
          var det=SS.pdfDetail(e,mode);
          var style='';
          if(colored){
            var rgb=SS.hexToRgb(e.color||e.lesson_color||'var(--g)');
            var tr=Math.round(rgb.r+(255-rgb.r)*0.82),tg=Math.round(rgb.g+(255-rgb.g)*0.82),tb=Math.round(rgb.b+(255-rgb.b)*0.82);
            style=' style="background:rgb('+tr+','+tg+','+tb+');border-color:rgb('+rgb.r+','+rgb.g+','+rgb.b+');color:rgb('+Math.round(rgb.r*0.55)+','+Math.round(rgb.g*0.55)+','+Math.round(rgb.b*0.55)+')"';
          }
          h+='<div class="ss-pv-box"'+style+'><span class="ss-pv-subj">'+SS.esc(SS.pdfSubject(e))+'</span>'+(det?'<span class="ss-pv-det">'+SS.esc(det)+'</span>':'')+'</div>';
        });
        }
        var pers=(personalCell[p.id]&&personalCell[p.id][d])?personalCell[p.id][d]:[];
        pers.forEach(function(it){
          var pstyle='';
          if(colored){
            var prgb=SS.hexToRgb(it.color||'var(--g)');
            var ptr=Math.round(prgb.r+(255-prgb.r)*0.88),ptg=Math.round(prgb.g+(255-prgb.g)*0.88),ptb=Math.round(prgb.b+(255-prgb.b)*0.88);
            pstyle=' style="background:rgb('+ptr+','+ptg+','+ptb+');border-color:rgb('+prgb.r+','+prgb.g+','+prgb.b+');color:rgb('+Math.round(prgb.r*0.55)+','+Math.round(prgb.g*0.55)+','+Math.round(prgb.b*0.55)+')"';
          }
          var ptime=(it.start_time||'').substr(0,5)+'–'+(it.end_time||'').substr(0,5);
          var pdet=ptime+(it.notes?' · '+it.notes:'');
          h+='<div class="ss-pv-box ss-pv-box-personal"'+pstyle+'><span class="ss-pv-subj">'+SS.esc(it.subject||'Asmeninis')+'</span><span class="ss-pv-det">'+SS.esc(pdet)+'</span></div>';
        });
        h+='</td>';
      }
      h+='</tr>';
    });
    return h+'</tbody></table>';
  },

  /* Render the schedule into a hidden <iframe> and print from there. No pop-up
     window is opened, so pop-up blockers never interfere. The browser's print
     dialog also lets the user „Išsaugoti kaip PDF". */
  printSchedule:function(data,opts){
    opts=opts||{};
    var size=(opts.size||'a4').toLowerCase();
    var orient=opts.orient || ((opts.content==='times')?'portrait':'landscape');
    var school=data.school||'';
    var title=data.schedule_title||'Savaitės tvarkaraštis';
    var name=data.name||'';
    var roleLabel={teacher:'Mokytojo tvarkaraštis',class:'Klasės tvarkaraštis',room:'Kabineto tvarkaraštis'}[data.mode]||'Mokinio tvarkaraštis';
    var showLogo=(opts.logo!=='off') && !!data.logo_b64;
    var logoTag=showLogo
      ? '<img class="ss-pv-logo" src="data:'+(data.logo_mime||'image/png')+';base64,'+data.logo_b64+'">'
      : '';
    var css=''
      // Force backgrounds/colours to print — browsers omit them by default,
      // which is why the printed cells & header used to come out colourless.
      +'*{box-sizing:border-box;-webkit-print-color-adjust:exact;print-color-adjust:exact;color-adjust:exact;}'
      +'html,body{-webkit-print-color-adjust:exact;print-color-adjust:exact;}'
      +'@page{size:'+size+' '+orient+';margin:8mm;}'
      +'body{font-family:"Noto Sans",Arial,Helvetica,sans-serif;color:#1a1a1a;margin:0;padding:10px;}'
      +'.ss-pv-head{display:flex;justify-content:space-between;align-items:center;border-bottom:2px solid var(--nb);padding-bottom:6px;margin-bottom:10px;}'
      +'.ss-pv-head .l{display:flex;align-items:center;gap:10px;}'
      +'.ss-pv-logo{height:38px;width:auto;}'
      +'.ss-pv-head .l b{font-size:15px;color:var(--dk);}.ss-pv-head .l span{display:block;font-size:11px;color:#555;}'
      +'.ss-pv-head .r{text-align:right;}.ss-pv-head .r b{font-size:13px;}.ss-pv-head .r span{display:block;font-size:10px;color:#666;}'
      +'.ss-pv-tbl{width:100%;border-collapse:collapse;table-layout:fixed;}'
      +'.ss-pv-tbl th,.ss-pv-tbl td{border:0.5px solid #b4b4b4;padding:3px;vertical-align:top;font-size:10px;}'
      +'.ss-pv-tbl thead th{background:var(--nb);color:var(--wh);text-align:center;font-size:10px;padding:5px 3px;}'
      +'.ss-pv-time{background:#eef3ee;text-align:center;font-size:9px;}.ss-pv-time b{display:block;font-size:11px;}.ss-pv-time span{display:block;}'
      +'.ss-pv-box{border:1px solid #9b9b9b;background:#f2f2f2;padding:2px 3px;margin-bottom:2px;}'
      +'.ss-pv-box-personal{border-style:dashed;}'
      +'.ss-pv-subj{display:block;font-weight:700;font-size:9.5px;}'
      +'.ss-pv-det{display:block;font-size:8px;color:#444;}'
      +'.ss-pv-grid td:first-child{width:34px;}'
      // B&W mode — high-contrast for mono printers
      +'.ss-pv-bw th,.ss-pv-bw td{border-color:#6e6e6e;}'
      +'.ss-pv-bw thead th{background:#1a1a1a;}'
      +'.ss-pv-bw .ss-pv-time{background:#ededed;color:#000;}'
      +'.ss-pv-bw .ss-pv-box{background:var(--wh);border:1.3px solid #000;}'
      +'.ss-pv-bw .ss-pv-subj{color:#000;}'
      +'.ss-pv-bw .ss-pv-det{color:#2d2d2d;}';
    var head='<div class="ss-pv-head"><div class="l">'+logoTag+'<div><b>'+SS.esc(school)+'</b><span>'+SS.esc(title)+'</span></div></div>'
      +'<div class="r"><b>'+SS.esc(name)+'</b><span>'+roleLabel+'</span></div></div>';
    var body=SS.pdfPreviewHtml(data,opts);
    var doc='<!doctype html><html><head><meta charset="utf-8"><title>'+SS.esc(name||'Tvarkaraštis')+'</title><style>'+css+'</style></head><body>'
      +head+body
      +'</body></html>';
    // Hidden iframe → print without opening a pop-up window.
    var old=document.getElementById('ss-print-frame'); if(old){ try{old.remove();}catch(e){} }
    var ifr=document.createElement('iframe');
    ifr.id='ss-print-frame';
    ifr.setAttribute('aria-hidden','true');
    ifr.style.cssText='position:fixed;right:0;bottom:0;width:0;height:0;border:0;visibility:hidden;';
    document.body.appendChild(ifr);
    var printed=false;
    function fire(){
      if(printed) return; printed=true;
      try{ ifr.contentWindow.focus(); ifr.contentWindow.print(); }
      catch(e){ console.error('[PDF] print error',e); SS.toast('Nepavyko atidaryti spausdinimo lango.','err'); }
      setTimeout(function(){ try{ifr.remove();}catch(e){} },60000);
    }
    ifr.onload=function(){ setTimeout(fire,250); };
    var idoc=ifr.contentWindow.document;
    idoc.open(); idoc.write(doc); idoc.close();
    setTimeout(fire,800);   /* fallback if onload doesn't fire for written docs */
  },

  loadPdfLibs:function(cb){
    // The autotable UMD normally patches window.jspdf.jsPDF on load, but in some
    // bundler/global setups it doesn't auto-attach. If the plugin's applyPlugin
    // helper is on the page, call it manually so autoTable becomes available.
    function ensureAutoTable(){
      if(window.jspdf && window.jspdf.jsPDF &&
         typeof window.jspdf.jsPDF.prototype.autoTable!=='function'){
        var ap=(typeof window.applyPlugin==='function') ? window.applyPlugin
              : (window.jspdf && typeof window.jspdf.applyPlugin==='function') ? window.jspdf.applyPlugin
              : null;
        if(ap){ try{ ap(window.jspdf.jsPDF); }catch(e){ console.warn('[PDF] applyPlugin klaida:',e); } }
      }
    }
    function scriptLoaded(){
      ensureAutoTable();
      return window.jspdf && window.jspdf.jsPDF &&
             typeof window.jspdf.jsPDF.prototype.autoTable==='function';
    }
    if(scriptLoaded()){return cb(null);}

    // Load one script, trying a list of mirror URLs until one succeeds.
    function loadFromList(urls,done){
      var i=0;
      (function next(){
        if(i>=urls.length){ done(new Error('nepavyko užkrauti nė vieno šaltinio')); return; }
        var src=urls[i++];
        var s=document.createElement('script');
        s.src=src;
        s.onload=function(){ done(null); };
        s.onerror=function(){ console.warn('[PDF] nepavyko įkelti:',src); s.remove(); next(); };
        document.head.appendChild(s);
      })();
    }

    // Prefer the copy bundled with the plugin (works offline / when CDNs are blocked),
    // then fall back to public mirrors.
    var base=(window.ss_pub&&ss_pub.plugin_url)?ss_pub.plugin_url:'';
    var jspdfUrls=[
      base+'public/js/vendor/jspdf.umd.min.js',
      'https://cdn.jsdelivr.net/npm/jspdf@2.5.1/dist/jspdf.umd.min.js',
      'https://unpkg.com/jspdf@2.5.1/dist/jspdf.umd.min.js',
      'https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js'
    ];
    var autoTableUrls=[
      base+'public/js/vendor/jspdf.plugin.autotable.min.js',
      'https://cdn.jsdelivr.net/npm/jspdf-autotable@3.5.25/dist/jspdf.plugin.autotable.min.js',
      'https://unpkg.com/jspdf-autotable@3.5.25/dist/jspdf.plugin.autotable.min.js',
      'https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.25/jspdf.plugin.autotable.min.js'
    ];
    if(!base){ jspdfUrls.shift(); autoTableUrls.shift(); }

    loadFromList(jspdfUrls,function(err){
      if(err) return cb(err);
      loadFromList(autoTableUrls,function(err2){
        if(err2) return cb(err2);
        if(!scriptLoaded()) return cb(new Error('autoTable neužsikrovė'));
        cb(null);
      });
    });
  },

  /* Build the jsPDF document and return {doc, filename}. Shared by both the
     live preview (rendered into an <iframe>) and the actual download, so the
     preview is byte-for-byte the same as the downloaded file. */
  buildPdfDoc:function(data,opts){
    opts = opts || {};
    var size    = 'a4';                             /* fixed — paper size option removed */
    var content = opts.content || 'full';          /* 'full' | 'times' */
    var colored = (opts.color||'color') === 'color';
    var headFill= colored ? [34,71,104] : [20,20,20];
    var gridLine= colored ? [180,180,180] : [110,110,110];  /* darker grid for B&W printers */
    var school    = data.school         || '';
    var schedTitle= data.schedule_title || 'Savaitės tvarkaraštis';
    var name      = data.name           || '';
    var username  = data.username       || name;
    var mode      = data.mode           || 'student';
    var periods   = data.periods        || [];
    var grid      = SS.filterGridForPdf(data.grid, data.subgroup_prefs, mode, (opts.groups==='all'));
    var showLogo  = (opts.logo!=='off') && !!data.logo_b64;
    var days      = ['Pirmadienis','Antradienis','Tre\u010diadienis','Ketvirtadienis','Penktadienis'];

    /* ── Fonts ── */
    var jsPDF = window.jspdf.jsPDF;
    var orientation = opts.orient || ((content==='times') ? 'portrait' : 'landscape');
    var doc   = new jsPDF({orientation:orientation,unit:'mm',format:size});
    var ff    = 'helvetica';

    if(data.font_reg_b64){
      try{
        doc.addFileToVFS('NotoSans-Regular.ttf',data.font_reg_b64);
        doc.addFont('NotoSans-Regular.ttf','NotoSans','normal');
        ff='NotoSans';
      }catch(e){console.warn('Font reg klaida:',e);}
    }
    if(data.font_bold_b64){
      try{
        doc.addFileToVFS('NotoSans-Bold.ttf',data.font_bold_b64);
        doc.addFont('NotoSans-Bold.ttf','NotoSans','bold');
      }catch(e){console.warn('Font bold klaida:',e);}
    }

    /* ── Sizes ── */
    var pw   = doc.internal.pageSize.getWidth();   /* 297 mm */
    var mg   = 10;
    var hdrH = 24;

    /* ── Header block ── */
    doc.setFillColor(248,248,248);
    doc.roundedRect(mg,mg,pw-2*mg,hdrH,2,2,'F');
    doc.setDrawColor(210,210,210);
    doc.setLineWidth(0.25);
    doc.line(mg,mg+hdrH,pw-mg,mg+hdrH);

    // Optional logo at the left of the header
    var textX = mg+5;
    if(showLogo){
      try{
        var fmt=(data.logo_mime && data.logo_mime.indexOf('png')>=0) ? 'PNG' : 'JPEG';
        var ls=hdrH-8;
        doc.addImage('data:'+(data.logo_mime||'image/png')+';base64,'+data.logo_b64, fmt, mg+4, mg+4, ls, ls);
        textX = mg+4+ls+4;
      }catch(e){ console.warn('Logo klaida:',e); }
    }

    doc.setFont(ff,'bold');  doc.setFontSize(13); doc.setTextColor(20,20,20);
    doc.text(school, textX, mg+9);

    doc.setFont(ff,'normal'); doc.setFontSize(8.5); doc.setTextColor(90,90,90);
    doc.text(schedTitle, textX, mg+16);

    doc.setFont(ff,'bold'); doc.setFontSize(12); doc.setTextColor(20,20,20);
    doc.text(name, pw-mg-5, mg+9, {align:'right'});

    var roleLabel = mode==='teacher'?'Mokytojo tvarkaraštis':'Mokinio tvarkaraštis';
    var now   = new Date();
    var dtStr = now.toLocaleDateString('lt-LT',{year:'numeric',month:'2-digit',day:'2-digit'})
              + ' ' + now.toLocaleTimeString('lt-LT',{hour:'2-digit',minute:'2-digit'});
    doc.setFont(ff,'normal'); doc.setFontSize(7.5); doc.setTextColor(110,110,110);
    doc.text(roleLabel, pw-mg-5, mg+15.5, {align:'right'});
    doc.text('Sugeneravo: '+username+'  \u2022  '+dtStr, pw-mg-5, mg+21, {align:'right'});

    /* ── Build table rows ── */
    /* ── Option: only lesson times (bell schedule) ── */
    if(content==='times'){
      var trows=[];
      periods.forEach(function(p){
        trows.push([ String(p.period_number), (p.start_time||'').substr(0,5), (p.end_time||'').substr(0,5) ]);
      });
      doc.autoTable({
        startY: mg+hdrH+3, margin:{left:mg,right:mg},
        head:[[ {content:'Pamoka'},{content:'Pradžia'},{content:'Pabaiga'} ]],
        body:trows, theme:'grid', tableWidth:pw-2*mg,
        styles:{font:ff,fontSize:11,halign:'center',valign:'middle',textColor:[25,25,25],
                lineColor:[180,180,180],lineWidth:0.25,cellPadding:3},
        headStyles:{font:ff,fontStyle:'bold',fillColor:headFill,textColor:[255,255,255],fontSize:11},
        columnStyles:{0:{cellWidth:(pw-2*mg)*0.34,fontStyle:'bold',
                         fillColor: colored?[238,243,238]:[238,238,238]}},
      });
      var safeNameT=(name||'tvarkarastis').replace(/[^A-Za-z0-9_À-ž\-]+/g,'_');
      return {doc:doc, filename:'Pamoku-laikai-'+safeNameT+'.pdf'};
    }

    var LH      = 3.8;  /* line height mm */
    var PAD     = 1.8;  /* box internal padding */
    var BOX_GAP = 2;    /* gap between boxes */
    var CELL_PAD= 2.5;  /* cell top/bottom padding */

    function entryBoxH(hasDetail){ return PAD*2 + (hasDetail?2:1)*LH + (hasDetail?0.8:0); }
    function cellHeight(entries){
      if(!entries||!entries.length) return 0;
      var h=CELL_PAD*2;
      entries.forEach(function(e,i){
        var det=SS.pdfDetail(e,mode);
        h+=entryBoxH(!!det)+(i>0?BOX_GAP:0);
      });
      return h;
    }

    var timeColW = 12;   /* siauresnis pamokos numerio / laiko stulpelis */
    var dayColW  = (pw-2*mg-timeColW)/5;

    /* Per-row stored entries for didDrawCell */
    var rowEntries=[];
    var bodyRows=[];

    periods.forEach(function(p){
      var maxH=14; /* minimum row height */
      var rowEnt=[];
      for(var d=1;d<=5;d++){
        var ent=(grid[p.id]&&grid[p.id][d])?grid[p.id][d]:[];
        rowEnt.push(ent);
        var ch=cellHeight(ent);
        if(ch>maxH) maxH=ch;
      }
      rowEntries.push(rowEnt);

      var tStart=(p.start_time||'').substr(0,5);
      var tEnd  =(p.end_time  ||'').substr(0,5);

      var row=[
        {content:p.period_number+'\n'+tStart+'\n'+tEnd,
         styles:{halign:'center',valign:'middle',fontStyle:'bold',font:ff,minCellHeight:maxH}},
      ];
      for(var d2=1;d2<=5;d2++){
        row.push({content:'',styles:{minCellHeight:maxH,font:ff}});
      }
      bodyRows.push(row);
    });

    /* ── autoTable ── */
    var headCols=[{content:'Laikas',styles:{halign:'center',fontStyle:'bold',font:ff}}];
    days.forEach(function(d){headCols.push({content:d,styles:{halign:'center',fontStyle:'bold',font:ff}});});

    doc.autoTable({
      startY    : mg+hdrH+3,
      margin    : {left:mg,right:mg},
      head      : [headCols],
      body      : bodyRows,
      theme     : 'grid',
      tableWidth: pw-2*mg,
      styles:{
        font:ff,fontSize:8,textColor:[25,25,25],
        cellPadding:{top:0,right:0,bottom:0,left:0},
        lineColor:gridLine,lineWidth:colored?0.25:0.3,
        overflow:'linebreak',valign:'top',
      },
      headStyles:{
        font:ff,fontStyle:'bold',
        fillColor:headFill,textColor:[255,255,255],
        fontSize:8.5,
        cellPadding:{top:4,right:3,bottom:4,left:3},
      },
      columnStyles:{
        0:{cellWidth:timeColW,fillColor: colored?[238,243,238]:[238,238,238],fontStyle:'bold',
           halign:'center',valign:'middle',fontSize:7.5,
           cellPadding:{top:3,right:2,bottom:3,left:2}},
        1:{cellWidth:dayColW},2:{cellWidth:dayColW},
        3:{cellWidth:dayColW},4:{cellWidth:dayColW},5:{cellWidth:dayColW},
      },
      didDrawCell:function(hook){
        if(hook.section!=='body'||hook.column.index===0) return;
        var ri   = hook.row.index;
        var ci   = hook.column.index-1; /* 0-based day index */
        var entries = rowEntries[ri] && rowEntries[ri][ci] ? rowEntries[ri][ci] : [];
        if(!entries.length) return;

        var x  = hook.cell.x+CELL_PAD;
        var y  = hook.cell.y+CELL_PAD;
        var cw = hook.cell.width-CELL_PAD*2;

        entries.forEach(function(e,ei){
          if(ei>0) y+=BOX_GAP;
          var subj  = SS.pdfSubject(e);
          var detail= SS.pdfDetail(e,mode);
          var hasD  = !!detail;
          var boxH  = entryBoxH(hasD);

          /* Box — colour-tinted (with lesson colour) or high-contrast B&W.
             B&W is tuned for laser/mono printers: white fill, solid black
             border and pure-black bold text so every cell reads cleanly. */
          var detailColor;
          if(colored){
            var rgb=SS.hexToRgb(e.color||e.lesson_color||'var(--g)');
            var tr=Math.round(rgb.r+(255-rgb.r)*0.82),
                tg=Math.round(rgb.g+(255-rgb.g)*0.82),
                tb=Math.round(rgb.b+(255-rgb.b)*0.82);
            doc.setFillColor(tr,tg,tb);
            doc.setDrawColor(rgb.r,rgb.g,rgb.b);
            doc.setLineWidth(0.4);
            doc.roundedRect(x,y,cw,boxH,1.2,1.2,'FD');
            doc.setTextColor(Math.round(rgb.r*0.55),Math.round(rgb.g*0.55),Math.round(rgb.b*0.55));
            detailColor=[70,70,70];
          } else {
            doc.setFillColor(255,255,255);
            doc.setDrawColor(0,0,0);
            doc.setLineWidth(0.45);
            doc.roundedRect(x,y,cw,boxH,1,1,'FD');
            doc.setTextColor(0,0,0);
            detailColor=[45,45,45];
          }

          /* Subject bold */
          doc.setFont(ff,'bold');
          doc.setFontSize(7.5);
          doc.text(subj,x+PAD+0.5,y+PAD+LH-0.5,{maxWidth:cw-PAD*2-1});

          /* Detail normal smaller */
          if(hasD){
            doc.setFont(ff,'normal');
            doc.setFontSize(6.5);
            doc.setTextColor(detailColor[0],detailColor[1],detailColor[2]);
            doc.text(detail,x+PAD+0.5,y+PAD+LH*2-0.3,{maxWidth:cw-PAD*2-1});
          }
          y+=boxH;
        });
      },
    });

    /* ── Save ── */
    var safeName=(name||'tvarkarastis').replace(/[^A-Za-z0-9_\u00C0-\u017E\-]+/g,'_');
    return {doc:doc, filename:'Tvarkarastis-'+safeName+'.pdf'};
  },

  /* Build + download the PDF (thin wrapper around buildPdfDoc). */
  renderPdf:function(data,opts){
    var r=SS.buildPdfDoc(data,opts);
    if(!r||!r.doc) return;
    r.doc.save(r.filename);
    SS.toast('PDF parsisiųstas');
  },

  /* Build the PDF and return a blob URL for the <iframe> live preview,
     so the preview is identical to the downloaded file. */
  pdfPreviewUrl:function(data,opts){
    var r=SS.buildPdfDoc(data,opts);
    if(!r||!r.doc) return null;
    return r.doc.output('bloburl');
  },

  /* Helper: format detail line for a lesson entry */
  pdfDetail:function(e,mode){
    // Subgroup is shown in the subject line (in parentheses), not here.
    if(mode==='teacher'){
      var cn=e.class_name||'';
      if(e.room) cn+=' (kab. '+e.room+')';
      return cn;
    }
    if(mode==='room'){
      var rp=[];
      if(e.class_name)   rp.push(e.class_name);
      if(e.teacher_name) rp.push(e.teacher_name);
      return rp.join(', ');
    }
    var pts=[];
    if(e.teacher_name) pts.push(e.teacher_name);
    if(e.room)         pts.push('kab. '+e.room);
    return pts.join(', ');
  },

  /* Mokytojo PDF langelis: paprastos klasės kableliu, IMP — sąrašas su klase
     skliaustuose (rikiuota pagal klasę, tada pagal pavadinimą) ARBA tik skaičius. */
  pdfTeacherCellBox:function(entries,opts,colored){
    if(!entries||!entries.length) return '';
    var imp=[],reg=[];
    entries.forEach(function(e){
      var isImp=(e.is_imp===true||e.is_imp===1||e.is_imp==='1');
      (isImp?imp:reg).push(e);
    });
    var first=entries[0];
    var style='';
    if(colored){
      var rgb=SS.hexToRgb(first.color||first.lesson_color||'var(--g)');
      var tr=Math.round(rgb.r+(255-rgb.r)*0.82),tg=Math.round(rgb.g+(255-rgb.g)*0.82),tb=Math.round(rgb.b+(255-rgb.b)*0.82);
      style=' style="background:rgb('+tr+','+tg+','+tb+');border-color:rgb('+rgb.r+','+rgb.g+','+rgb.b+');color:rgb('+Math.round(rgb.r*0.55)+','+Math.round(rgb.g*0.55)+','+Math.round(rgb.b*0.55)+')"';
    }
    var det=[];
    if(reg.length){
      det.push(reg.map(function(e){
        var n=e.class_name||'';
        if(e.subgroup_label) n+=' · '+e.subgroup_label;
        return n;
      }).join(', '));
    }
    if(imp.length){
      var seen={},items=[];
      imp.forEach(function(e){ var k=e.class_name||''; if(seen[k])return; seen[k]=1; items.push(e); });
      if(opts.implist==='names'){
        items.sort(function(a,b){
          var ca=a.class_label||'',cb=b.class_label||'';
          if(ca===''&&cb!=='')return 1;
          if(ca!==''&&cb==='')return -1;
          var c=ca.localeCompare(cb,'lt');
          return c!==0?c:(a.class_name||'').localeCompare(b.class_name||'','lt');
        });
        det.push(items.map(function(e){
          return (e.class_name||'')+(e.class_label?' ('+e.class_label+')':'');
        }).join(', '));
      } else {
        det.push(items.length+' '+SS.studentWord(items.length));
      }
    }
    if(first.room) det.push('kab. '+first.room);
    var html='<div class="ss-pv-box"'+style+'><span class="ss-pv-subj">'+SS.esc(first.subject||'')+'</span>';
    det.forEach(function(dd){ html+='<span class="ss-pv-det">'+SS.esc(dd)+'</span>'; });
    return html+'</div>';
  },

  /* Subject label with the subgroup in parentheses, e.g. \u201eMatematika (1 grup\u0117)" */
  pdfSubject:function(e){
    var s=e.subject||'';
    if(e.subgroup_label) s+=' ('+e.subgroup_label+')';
    return s;
  },

  /* Filter a schedule grid down to the subgroups the student actually picked.
     prefs: { "<class>_<day>_<period>": schedule_id }. showAll bypasses filtering. */
  filterGridForPdf:function(grid,prefs,mode,showAll){
    grid=grid||{};
    if(showAll || mode!=='student' || !prefs) return grid;
    var out={};
    Object.keys(grid).forEach(function(pid){
      out[pid]={};
      Object.keys(grid[pid]).forEach(function(day){
        var arr=grid[pid][day]||[];
        var byClass={};
        arr.forEach(function(e){ var c=e.class_id||0; (byClass[c]=byClass[c]||[]).push(e); });
        var kept=[];
        Object.keys(byClass).forEach(function(c){
          var list=byClass[c];
          if(list.length<=1){ kept=kept.concat(list); return; }
          var pref=prefs[c+'_'+day+'_'+pid];
          if(pref){
            var sel=list.filter(function(e){ return String(e.id)===String(pref); });
            kept=kept.concat(sel.length?sel:[list[0]]); // chosen one (or first if id missing)
          } else {
            // No explicit choice \u2192 default to the FIRST subgroup, exactly like the
            // dashboard does (it shows entries[0] as active). This keeps the PDF in
            // "tik pasirinktos" mode consistent with what the student sees on screen,
            // instead of dumping every subgroup into the cell.
            kept.push(list[0]);
          }
        });
        out[pid][day]=kept;
      });
    });
    return out;
  },

    hexToRgb:function(hex){
    hex=String(hex||'var(--g)').replace('#','');
    if(hex.length===3) hex=hex.split('').map(function(c){return c+c;}).join('');
    return {
      r:parseInt(hex.substr(0,2),16)||79,
      g:parseInt(hex.substr(2,2),16)||137,
      b:parseInt(hex.substr(4,2),16)||82
    };
  },

  /* ════ PERSONAL ITEMS ════ */
  personal:function(){
    var $modal=$('#ss-personal-modal');
    $(document).on('click','#ss-open-personal',function(){SS.openPM();});
    $(document).on('click','.ss-p-edit',function(){
      try{SS.openPM(JSON.parse($(this).attr('data-item')));}catch(e){SS.openPM();}
    });
    $(document).on('click','#ss-modal-close,#ss-cancel-personal',function(){SS.closePM();});
    $(document).on('click','#ss-personal-modal',function(e){if($(e.target).is('#ss-personal-modal'))SS.closePM();});
    $(document).on('keydown',function(e){if(e.key==='Escape'&&$('#ss-personal-modal').is(':visible'))SS.closePM();});
    $(document).on('click','.ss-fswatch',function(){SS.setPC($(this).data('color'));});
    $('#ss-p-native').on('input change',function(){
      var c=$(this).val();$('#ss-p-hex').val(c);$('#ss-pcolor').val(c);$('.ss-fswatch').removeClass('active');
    });
    $('#ss-p-hex').on('input',function(){
      var v=$(this).val().trim();
      if(/^#[0-9A-Fa-f]{6}$/.test(v)){$('#ss-p-native').val(v);$('#ss-pcolor').val(v);$('.ss-fswatch').removeClass('active');$('.ss-fswatch[data-color="'+v+'"]').addClass('active');}
    });
    $('#ss-personal-form').on('submit',function(e){
      e.preventDefault();
      var $btn=$('#ss-save-personal'),$err=$('#ss-perr'),subj=$('#ss-psubj').val().trim();
      $err.hide();if(!subj){$err.text('Prašome įvesti pavadinimą.').show();return;}
      $btn.prop('disabled',true).text('Saugoma...');
      $.post(ss_pub.ajax_url,{action:'ss_save_personal',nonce:ss_pub.front_nonce,
        id:$('#ss-pid').val(),subject:subj,day_of_week:$('#ss-pday').val(),
        start_time:$('#ss-pstart').val(),end_time:$('#ss-pend').val(),
        color:$('#ss-pcolor').val()||'var(--g)',notes:$('#ss-pnotes').val().trim()
      },function(res){
        if(res.success){SS.closePM();SS.toast('Išsaugota!');setTimeout(function(){location.reload();},600);}
        else{$err.text((res.data&&res.data.message)||'Klaida.').show();$btn.prop('disabled',false).text('Išsaugoti');}
      }).fail(function(){$err.text('Serverio klaida.').show();$btn.prop('disabled',false).text('Išsaugoti');});
    });
    $(document).on('click','.ss-p-del',function(){
      var $item=$(this).closest('.ss-p-item'),name=$item.find('.ss-p-subj').text();
      if(!confirm('Ištrinti "'+name+'"?')) return;
      $.post(ss_pub.ajax_url,{action:'ss_delete_personal',nonce:ss_pub.front_nonce,id:$(this).data('id')},
      function(res){if(res.success)$item.fadeOut(250,function(){$(this).remove();SS.toast('Ištrinta.');});});
    });
  },
  openPM:function(item){
    $('#ss-modal-title').text(item?'Redaguoti įrašą':'Pridėti įrašą');
    $('#ss-perr').hide();$('#ss-personal-form')[0].reset();$('#ss-pid').val('');SS.setPC('#4F8952');
    if(item){$('#ss-pid').val(item.id||'');$('#ss-psubj').val(item.subject||'');$('#ss-pday').val(item.day_of_week||1);$('#ss-pstart').val((item.start_time||'').substring(0,5)||'');$('#ss-pend').val((item.end_time||'').substring(0,5)||'');$('#ss-pnotes').val(item.notes||'');SS.setPC(item.color||'#4F8952');}
    $('#ss-save-personal').prop('disabled',false).text('Išsaugoti');
    $('#ss-personal-modal').addClass('ss-open').css('display','');
    document.body.style.overflow='hidden';
    setTimeout(function(){$('#ss-psubj').focus();},80);
  },
  closePM:function(){
    $('#ss-personal-modal').removeClass('ss-open').css('display','');
    document.body.style.overflow='';
  },
  setPC:function(c){
    // Tik tikras HEX — seni įrašai galėjo turėti var(--x), kurio spalvos laukas
    // ir sanitize_hex_color nepriima; tokiu atveju krentam į numatytąją.
    if(!/^#[0-9a-fA-F]{6}$/.test(String(c||''))) c='#4F8952';
    $('#ss-pcolor').val(c);$('#ss-p-native').val(c);$('#ss-p-hex').val(c);
    $('.ss-fswatch').removeClass('active');
    $('.ss-fswatch[data-color="'+c+'"]').addClass('active');
  },

  /* ════ TEACHER SLOT GROUP MODAL (IMP students list) ════ */
  teacherSlotGroups:function(){
    $(document).on('click','.ss-wt-imp-trigger',function(e){
      e.preventDefault();
      var $b=$(this);
      if($b.hasClass('is-busy')) return;
      var payload={
        action:'ss_get_teacher_slot_groups',
        nonce:ss_pub.front_nonce,
        day:$b.data('day'),
        period:$b.data('period')
      };
      var tid=parseInt($b.data('teacher-id')||0,10);
      if(tid>0) payload.teacher_id=tid;
      $b.addClass('is-busy');
      $.post(ss_pub.ajax_url,payload,function(res){
        if(!res.success){ alert((res.data&&res.data.message)||'Klaida'); return; }
        SS.openStudentsModal(res.data.groups||[]);
      }).always(function(){ $b.removeClass('is-busy'); });
    });
  },

  openStudentsModal:function(groups){
    var html='<div class="ss-students-modal" id="ss-students-modal">'
      +'<div class="ss-students-modal-inner">'
      +'<div class="ss-students-modal-head">'
      +'<h3>Mokinių sąrašas</h3>'
      +'<button type="button" class="ss-students-modal-close" aria-label="Uždaryti">&times;</button>'
      +'</div>'
      +'<div class="ss-students-modal-body">';
    if(!groups.length){
      html+='<p style="color:#94A3B8;font-style:italic;margin:0;">Mokinių sąrašas tuščias.</p>';
    } else {
      // IMP tvarkaraštis skirtas vienam mokiniui — rodome tik (sunumeruotus)
      // tvarkaraščių pavadinimus, be atskiro mokinių sąrašo.
      html+='<ol class="ss-sm-students">';
      $.each(groups,function(_,g){
        var cls=g.class_label?' <span class="ss-sm-class">('+SS.esc(g.class_label)+')</span>':'';
        html+='<li>'+SS.esc(g.class_name)+cls+(g.subgroup_label?' · '+SS.esc(g.subgroup_label):'')+'</li>';
      });
      html+='</ol>';
    }
    html+='</div></div></div>';
    $('#ss-students-modal').remove();
    $('body').append(html);
    document.body.style.overflow='hidden';
    $(document).on('click.smodal','.ss-students-modal-close, .ss-students-modal',function(e){
      if(e.target.classList.contains('ss-students-modal-close') || e.target.classList.contains('ss-students-modal')){
        $('#ss-students-modal').remove();
        document.body.style.overflow='';
        $(document).off('click.smodal');
      }
    });
  },

  /* ════ TEACHER SCHEDULES (IMP) ════ */
  teacherSchedules:function(){
    var $list=$('#ss-teachers-list');
    if(!$list.length) return;
    var $wrap=$('#ss-teacher-schedule-wrap');
    var $loading=$('#ss-teacher-schedule-loading');
    var $content=$('#ss-teacher-schedule-content');

    // Search filter
    $('#ss-teacher-search').on('input',function(){
      var q=$(this).val().toLowerCase().trim();
      $('.ss-teacher-card').each(function(){
        var name=$(this).data('name')||'';
        $(this).toggle(name.indexOf(q)!==-1);
      });
    });

    // Click on teacher card
    $(document).on('click','.ss-teacher-card',function(){
      var tid=$(this).data('id');
      $list.hide();
      $('.ss-search-box').hide();
      $wrap.show();
      $loading.show();
      $content.empty();

      $.post(ss_pub.ajax_url,{
        action:'ss_get_teacher_schedule',
        nonce:ss_pub.front_nonce,
        teacher_id:tid
      },function(res){
        $loading.hide();
        if(res.success){
          SS.renderTeacherSchedule(res.data,$content);
        } else {
          $content.html('<div class="ss-alert ss-alert-err">'+(res.data&&res.data.message||'Klaida')+'</div>');
        }
      }).fail(function(){
        $loading.hide();
        $content.html('<div class="ss-alert ss-alert-err">Serverio klaida</div>');
      });
    });

    // Back button
    $('#ss-back-to-teachers').on('click',function(){
      $wrap.hide();
      $content.empty();
      $list.show();
      $('.ss-search-box').show();
    });
  },

  renderTeacherSchedule:function(data,$container){
    var grid=data.grid||{};
    var periods=data.periods||[];
    var teacher=data.teacher||{};
    var viewer=data.viewer||'student';     // 'student' | 'teacher'
    var days=['Pirmadienis','Antradienis','Trečiadienis','Ketvirtadienis','Penktadienis'];
    var days_s=['Pr','An','Tr','Kt','Pn'];

    var html='<div class="ss-ts-head"><div>'
      +'<h3 class="ss-ts-title">'+SS.esc(teacher.name)+'</h3>'
      +'<p class="ss-ts-sub">Mokytojo savaitės tvarkaraštis</p></div>'
      +SS.viewPdfBtnHtml('teacher',teacher.id||0)+'</div>';
    html+='<div class="ss-week-scroll"><table class="ss-week-table">';
    html+='<thead><tr><th class="ss-wt-time">Laikas</th>';
    for(var di=0;di<5;di++){
      html+='<th class="ss-wt-dh"><div class="ss-wt-dh-inner">';
      html+='<span class="ss-wt-dl">'+days[di]+'</span>';
      html+='<span class="ss-wt-ds">'+days_s[di]+'</span>';
      html+='</div></th>';
    }
    html+='</tr></thead><tbody>';

    $.each(periods,function(i,p){
      html+='<tr><td class="ss-wt-time-cell"><strong>'+p.period_number+'</strong><span>'+p.start_time.substr(0,5)+'</span><span>'+p.end_time.substr(0,5)+'</span></td>';
      for(var d=1;d<=5;d++){
        var entries=grid[p.id]&&grid[p.id][d]?grid[p.id][d]:[];
        html+='<td class="ss-wt-cell">';
        if(entries.length){
          var color=entries[0].lesson_color||'var(--g)';
          html+='<div class="ss-wt-lesson" style="--lc:'+color+'">';
          html+='<span class="ss-wt-subj">'+SS.esc(entries[0].subject)+'</span>';

          // Atskiriame IMP ir paprastas klases
          var impEntries=[], regEntries=[];
          $.each(entries,function(_,e){
            if(parseInt(e.is_imp,10)===1) impEntries.push(e);
            else regEntries.push(e);
          });

          var lineParts=[];
          // Paprastos klases - per kableli (su pogrupių žyma jei yra)
          if(regEntries.length){
            var regNames=$.map(regEntries,function(e){
              var n=SS.esc(e.class_name||'');
              if(e.subgroup_label) n+=' · '+SS.esc(e.subgroup_label);
              return n;
            }).join(', ');
            lineParts.push('<span class="ss-wt-tchr">'+regNames+'</span>');
          }
          // IMP - skaičiuojam unikalias IMP klases/tvarkaraščius (ne WP accountus)
          if(impEntries.length){
            var impClassIds={};
            $.each(impEntries,function(_,e){
              var cid=parseInt(e.class_id||0,10);
              if(cid) impClassIds[cid]=1;
            });
            var totalStudents=Object.keys(impClassIds).length || impEntries.length;
            var label = totalStudents + ' ' + SS.studentWord(totalStudents);
            if(viewer==='teacher'){
              // Mokytojas/admin: paspaudus atsidaro mokiniu sarasas
              lineParts.push('<button type="button" class="ss-wt-imp-trigger" '
                +'data-day="'+d+'" data-period="'+p.id+'" '
                +'data-teacher-id="'+(teacher.id||0)+'">'+label+'</button>');
            } else {
              // Mokinys/kitas mokytojas: TIK kiekis (be sarasu)
              lineParts.push('<span class="ss-wt-count">'+label+'</span>');
            }
          }
          html+=lineParts.join('<span class="ss-wt-sep"> · </span>');
          html+='</div>';
        }
        html+='</td>';
      }
      html+='</tr>';
    });

    html+='</tbody></table></div>';
    html+='<p class="ss-week-hint">Slinkite į šoną norėdami matyti visas dienas</p>';
    $container.html(html);
  },

  /* ════ ROOM SCHEDULES (KABINETAI) ════ */
  roomSchedules:function(){
    var $list=$('#ss-rooms-list');
    if(!$list.length) return;
    var $wrap=$('#ss-room-schedule-wrap');
    var $loading=$('#ss-room-schedule-loading');
    var $content=$('#ss-room-schedule-content');

    // Search filter
    $('#ss-room-search').on('input',function(){
      var q=$(this).val().toLowerCase().trim();
      $('.ss-room-card').each(function(){
        var name=$(this).data('name')||'';
        $(this).toggle(String(name).indexOf(q)!==-1);
      });
    });

    // Click on room card
    $(document).on('click','.ss-room-card',function(){
      var rid=$(this).data('id');
      $list.hide();
      $('#tab-rooms .ss-search-box').hide();
      $wrap.show();
      $loading.show();
      $content.empty();

      $.post(ss_pub.ajax_url,{
        action:'ss_get_room_schedule',
        nonce:ss_pub.front_nonce,
        room_id:rid
      },function(res){
        $loading.hide();
        if(res.success){
          SS.renderRoomSchedule(res.data,$content);
        } else {
          $content.html('<div class="ss-alert ss-alert-err">'+(res.data&&res.data.message||'Klaida')+'</div>');
        }
      }).fail(function(){
        $loading.hide();
        $content.html('<div class="ss-alert ss-alert-err">Serverio klaida</div>');
      });
    });

    // Back button
    $('#ss-back-to-rooms').on('click',function(){
      $wrap.hide();
      $content.empty();
      $list.show();
      $('#tab-rooms .ss-search-box').show();
    });
  },

  renderRoomSchedule:function(data,$container){
    var grid=data.grid||{};
    var periods=data.periods||[];
    var room=data.room||{};
    var days=['Pirmadienis','Antradienis','Trečiadienis','Ketvirtadienis','Penktadienis'];
    var days_s=['Pr','An','Tr','Kt','Pn'];

    var html='<div class="ss-ts-head"><div>'
      +'<h3 class="ss-ts-title">'+SS.esc(room.name)+'</h3>'
      +'<p class="ss-ts-sub">Kabineto savaitės tvarkaraštis</p></div>'
      +SS.viewPdfBtnHtml('room',room.id||0)+'</div>';
    html+='<div class="ss-week-scroll"><table class="ss-week-table">';
    html+='<thead><tr><th class="ss-wt-time">Laikas</th>';
    for(var di=0;di<5;di++){
      html+='<th class="ss-wt-dh"><div class="ss-wt-dh-inner">';
      html+='<span class="ss-wt-dl">'+days[di]+'</span>';
      html+='<span class="ss-wt-ds">'+days_s[di]+'</span>';
      html+='</div></th>';
    }
    html+='</tr></thead><tbody>';

    $.each(periods,function(i,p){
      html+='<tr><td class="ss-wt-time-cell"><strong>'+p.period_number+'</strong><span>'+p.start_time.substr(0,5)+'</span><span>'+p.end_time.substr(0,5)+'</span></td>';
      for(var d=1;d<=5;d++){
        var entries=grid[p.id]&&grid[p.id][d]?grid[p.id][d]:[];
        html+='<td class="ss-wt-cell">';
        if(entries.length){
          var color=entries[0].lesson_color||'var(--g)';
          html+='<div class="ss-wt-lesson" style="--lc:'+color+'">';
          html+='<span class="ss-wt-subj">'+SS.esc(entries[0].subject)+'</span>';

          // Atskiriame IMP ir paprastas klases — visi IMP sudedami į vieną langelį
          var impEntries=[], regEntries=[];
          $.each(entries,function(_,e){
            if(parseInt(e.is_imp,10)===1) impEntries.push(e);
            else regEntries.push(e);
          });

          var lineParts=[];
          if(regEntries.length){
            var regNames=$.map(regEntries,function(e){
              var n=SS.esc(e.class_name||'');
              if(e.subgroup_label) n+=' · '+SS.esc(e.subgroup_label);
              if(e.teacher_name) n+=' ('+SS.esc(e.teacher_name)+')';
              return n;
            }).join(', ');
            lineParts.push('<span class="ss-wt-tchr">'+regNames+'</span>');
          }
          if(impEntries.length){
            var impClassIds={};
            $.each(impEntries,function(_,e){
              var cid=parseInt(e.class_id||0,10);
              if(cid) impClassIds[cid]=1;
            });
            var totalStudents=Object.keys(impClassIds).length || impEntries.length;
            lineParts.push('<span class="ss-wt-count">'+totalStudents+' '+SS.studentWord(totalStudents)+'</span>');
          }
          html+=lineParts.join('<span class="ss-wt-sep"> · </span>');
          html+='</div>';
        }
        html+='</td>';
      }
      html+='</tr>';
    });

    html+='</tbody></table></div>';
    html+='<p class="ss-week-hint">Slinkite į šoną norėdami matyti visas dienas</p>';
    $container.html(html);
  },

  /* lietuviska zmones forma: 1 mokinys, 2-9 mokiniai, 10+ mokiniu, 11-19 mokiniu */
  studentWord:function(n){
    n=parseInt(n,10)||0;
    var mod10=n%10, mod100=n%100;
    if(mod10===1 && mod100!==11) return 'mokinys';
    if(mod10>=2 && mod10<=9 && (mod100<10 || mod100>=20)) return 'mokiniai';
    return 'mokinių';
  },

  /* ════ ALL SCHEDULES (TEACHERS) ════ */
  allSchedules:function(){
    var $panel=$('#tab-allschedules');
    if(!$panel.length) return;

    // Type tabs (students / teachers / rooms)
    $('.ss-type-tab').on('click',function(){
      var type=$(this).data('type');
      $('.ss-type-tab').removeClass('active');
      $(this).addClass('active');
      $('.ss-all-panel').removeClass('active');
      $('.ss-all-'+type).addClass('active');
    });

    // Class search
    $('#ss-class-search').on('input',function(){
      var q=$(this).val().toLowerCase().trim();
      $('.ss-class-card').each(function(){
        $(this).toggle(($(this).data('name')||'').indexOf(q)!==-1);
      });
    });

    // All teacher search
    $('#ss-all-teacher-search').on('input',function(){
      var q=$(this).val().toLowerCase().trim();
      $('#ss-all-teachers-list .ss-teacher-card').each(function(){
        $(this).toggle(($(this).data('name')||'').indexOf(q)!==-1);
      });
    });

    // Click class card
    $(document).on('click','.ss-class-card',function(){
      var cid=$(this).data('id');
      var $wrap=$('#ss-class-schedule-wrap');
      var $list=$('#ss-classes-list');
      var $search=$('.ss-all-students .ss-search-box');
      var $loading=$('#ss-class-schedule-loading');
      var $content=$('#ss-class-schedule-content');

      $list.hide();$search.hide();$wrap.show();$loading.show();$content.empty();

      $.post(ss_pub.ajax_url,{
        action:'ss_get_class_schedule',
        nonce:ss_pub.front_nonce,
        class_id:cid
      },function(res){
        $loading.hide();
        if(res.success) SS.renderClassSchedule(res.data,$content);
        else $content.html('<div class="ss-alert ss-alert-err">'+(res.data&&res.data.message||'Klaida')+'</div>');
      }).fail(function(){
        $loading.hide();
        $content.html('<div class="ss-alert ss-alert-err">Serverio klaida</div>');
      });
    });

    // Back from class schedule
    $('#ss-back-to-classes').on('click',function(){
      $('#ss-class-schedule-wrap').hide();
      $('#ss-class-schedule-content').empty();
      $('#ss-classes-list').show();
      $('.ss-all-students .ss-search-box').show();
    });

    // Click teacher card in all schedules
    $(document).on('click','#ss-all-teachers-list .ss-teacher-card',function(){
      var tid=$(this).data('id');
      var $wrap=$('#ss-all-teacher-schedule-wrap');
      var $list=$('#ss-all-teachers-list');
      var $search=$('.ss-all-teachers .ss-search-box');
      var $loading=$('#ss-all-teacher-schedule-loading');
      var $content=$('#ss-all-teacher-schedule-content');

      $list.hide();$search.hide();$wrap.show();$loading.show();$content.empty();

      $.post(ss_pub.ajax_url,{
        action:'ss_get_teacher_schedule',
        nonce:ss_pub.front_nonce,
        teacher_id:tid
      },function(res){
        $loading.hide();
        if(res.success) SS.renderTeacherSchedule(res.data,$content);
        else $content.html('<div class="ss-alert ss-alert-err">'+(res.data&&res.data.message||'Klaida')+'</div>');
      }).fail(function(){
        $loading.hide();
        $content.html('<div class="ss-alert ss-alert-err">Serverio klaida</div>');
      });
    });

    // Back from all teacher schedule
    $('#ss-back-to-all-teachers').on('click',function(){
      $('#ss-all-teacher-schedule-wrap').hide();
      $('#ss-all-teacher-schedule-content').empty();
      $('#ss-all-teachers-list').show();
      $('.ss-all-teachers .ss-search-box').show();
    });

    // Room search (in „Visi tvarkaraščiai“ panel)
    $('#ss-all-room-search').on('input',function(){
      var q=$(this).val().toLowerCase().trim();
      $('#ss-all-rooms-list .ss-room-card').each(function(){
        $(this).toggle(String($(this).data('name')||'').indexOf(q)!==-1);
      });
    });

    // Click room card in all schedules
    $(document).on('click','#ss-all-rooms-list .ss-room-card',function(){
      var rid=$(this).data('id');
      var $wrap=$('#ss-all-room-schedule-wrap');
      var $list=$('#ss-all-rooms-list');
      var $search=$('.ss-all-rooms .ss-search-box');
      var $loading=$('#ss-all-room-schedule-loading');
      var $content=$('#ss-all-room-schedule-content');

      $list.hide();$search.hide();$wrap.show();$loading.show();$content.empty();

      $.post(ss_pub.ajax_url,{
        action:'ss_get_room_schedule',
        nonce:ss_pub.front_nonce,
        room_id:rid
      },function(res){
        $loading.hide();
        if(res.success) SS.renderRoomSchedule(res.data,$content);
        else $content.html('<div class="ss-alert ss-alert-err">'+(res.data&&res.data.message||'Klaida')+'</div>');
      }).fail(function(){
        $loading.hide();
        $content.html('<div class="ss-alert ss-alert-err">Serverio klaida</div>');
      });
    });

    // Back from all room schedule
    $('#ss-back-to-all-rooms').on('click',function(){
      $('#ss-all-room-schedule-wrap').hide();
      $('#ss-all-room-schedule-content').empty();
      $('#ss-all-rooms-list').show();
      $('.ss-all-rooms .ss-search-box').show();
    });
  },

  renderClassSchedule:function(data,$container){
    var grid=data.grid||{};
    var periods=data.periods||[];
    var info=data.class_info||{};
    var days=['Pirmadienis','Antradienis','Trečiadienis','Ketvirtadienis','Penktadienis'];
    var days_s=['Pr','An','Tr','Kt','Pn'];

    var html='<div class="ss-ts-head"><div>'
      +'<h3 class="ss-ts-title">'+SS.esc(info.name)+'</h3>'
      +'<p class="ss-ts-sub">Klasės savaitės tvarkaraštis</p></div>'
      +SS.viewPdfBtnHtml('class',info.id||0)+'</div>';
    html+='<div class="ss-week-scroll"><table class="ss-week-table">';
    html+='<thead><tr><th class="ss-wt-time">Laikas</th>';
    for(var di=0;di<5;di++){
      html+='<th class="ss-wt-dh"><div class="ss-wt-dh-inner">';
      html+='<span class="ss-wt-dl">'+days[di]+'</span>';
      html+='<span class="ss-wt-ds">'+days_s[di]+'</span>';
      html+='</div></th>';
    }
    html+='</tr></thead><tbody>';

    $.each(periods,function(i,p){
      html+='<tr><td class="ss-wt-time-cell"><strong>'+p.period_number+'</strong><span>'+p.start_time.substr(0,5)+'</span><span>'+p.end_time.substr(0,5)+'</span></td>';
      for(var d=1;d<=5;d++){
        var entries=grid[d]&&grid[d][p.id]?grid[d][p.id]:[];
        html+='<td class="ss-wt-cell">';
        if(entries.length){
          var color=entries[0].lesson_color||'var(--g)';
          if(entries.length===1){
            var e=entries[0];
            html+='<div class="ss-wt-lesson" style="--lc:'+color+'">';
            html+='<span class="ss-wt-subj">'+SS.esc(e.subject)+'</span>';
            if(e.subgroup_label) html+='<span class="ss-wt-sub-ico">'+SS.esc(e.subgroup_label)+'</span>';
            if(e.teacher_name) html+='<span class="ss-wt-tchr">'+SS.esc(e.teacher_name)+'</span>';
            html+='</div>';
          } else {
            html+='<div class="ss-wt-lesson ss-wt-multi" style="--lc:'+color+'">';
            $.each(entries,function(ei,e){
              var isFree=e.is_free==1||!e.subject;
              html+='<div class="ss-wt-multi-row'+(isFree?' ss-wt-free':'')+'">';
              if(isFree){
                html+='<span class="ss-wt-subj">'+(e.subgroup_label?SS.esc(e.subgroup_label)+': ':'')+'\u2014 nėra pamokos</span>';
              } else {
                html+='<span class="ss-wt-subj">'+SS.esc(e.subject)+(e.subgroup_label?' <em style="font-weight:400;opacity:.8">('+SS.esc(e.subgroup_label)+')</em>':'')+'</span>';
                if(e.teacher_name) html+='<span class="ss-wt-tchr">'+SS.esc(e.teacher_name)+'</span>';
              }
              html+='</div>';
            });
            html+='</div>';
          }
        }
        html+='</td>';
      }
      html+='</tr>';
    });

    html+='</tbody></table></div>';
    html+='<p class="ss-week-hint">Slinkite į šoną norėdami matyti visas dienas</p>';
    $container.html(html);
  },

  /* ════════════════════════════════════════════
     SETTINGS TAB
  ════════════════════════════════════════════ */
  settings:function(){
    if(!$('.ss-settings-page').length && !$('#tab-settings').length) return;
    var nonce=ss_pub.front_nonce;

    /* ── Avatar upload with crop ── */
    var $inp=$('#ss-avatar-input');
    var $preview=$('#ss-avatar-preview');
    var $prog=$('#ss-avatar-prog');
    var $progFill=$('#ss-avatar-prog-fill');
    var cropper=null;

    $inp.on('change',function(){
      var file=this.files[0];
      if(!file) return;
      if(file.size>3*1024*1024){ SS.toast('Failas per didelis (maks. 3 MB).',true); $inp.val(''); return; }
      var reader=new FileReader();
      reader.onload=function(e){
        // show modal
        var $modal=$('#ss-crop-modal');
        var $img=$('#ss-crop-img');
        $img.attr('src',e.target.result);
        $modal.css('display','flex');
        // init Cropper
        if(cropper){ cropper.destroy(); cropper=null; }
        $img.off('ready').one('ready',function(){});
        cropper=new Cropper($img[0],{
          aspectRatio:1,
          viewMode:1,
          dragMode:'move',
          autoCropArea:0.9,
          responsive:true,
          checkOrientation:true,
          background:false,
        });
      };
      reader.readAsDataURL(file);
      $inp.val('');
    });

    $('#ss-crop-cancel').on('click',function(){
      $('#ss-crop-modal').css('display','none');
      if(cropper){ cropper.destroy(); cropper=null; }
    });

    $('#ss-crop-confirm').on('click',function(){
      if(!cropper) return;
      var $btn=$(this).prop('disabled',true).text('Įkeliama…');
      var canvas=cropper.getCroppedCanvas({width:300,height:300,imageSmoothingQuality:'high'});
      canvas.toBlob(function(blob){
        var fd=new FormData();
        fd.append('action','ss_settings_upload_avatar');
        fd.append('nonce',nonce);
        fd.append('avatar',blob,'avatar.jpg');
        $('#ss-crop-modal').css('display','none');
        if(cropper){ cropper.destroy(); cropper=null; }
        $prog.show(); $progFill.css('width','0%');
        $.ajax({
          url:ss_pub.ajax_url,type:'POST',data:fd,
          processData:false,contentType:false,
          xhr:function(){
            var x=new window.XMLHttpRequest();
            x.upload.addEventListener('progress',function(e){
              if(e.lengthComputable) $progFill.css('width',Math.round(e.loaded/e.total*100)+'%');
            });
            return x;
          },
          success:function(r){
            $prog.hide();
            $btn.prop('disabled',false).html('<svg width="14" height="14" viewBox="0 0 20 20" fill="currentColor" style="vertical-align:-2px"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg> Apkarpyti ir įkelti');
            if(r.success){
              $preview.attr('src',r.data.url+'?v='+Date.now());
              $('.ss-hdr-avatar, #ss-hdr-avatar-img').attr('src',r.data.url+'?v='+Date.now());
              if(!$('#ss-avatar-del').length){
                $('<button type="button" class="ss-avatar-del" id="ss-avatar-del" title="Pašalinti nuotrauką">✕</button>').appendTo('.ss-avatar-preview-wrap');
                SS.settingsBindDelAvatar();
              }
              SS.toast('Nuotrauka įkelta.');
            } else { SS.toast(r.data.message||'Klaida.',true); }
          },
          error:function(){ $prog.hide(); $btn.prop('disabled',false); SS.toast('Ryšio klaida.',true); }
        });
      },'image/jpeg',0.92);
    });

    SS.settingsBindDelAvatar();

    /* ── Ątspėk settings ── */
    $('input[name="ss_atspek_mode"]').on('change',function(){
      var val=$(this).val();
      // update active class
      $('.ss-atspek-opt').removeClass('ss-atspek-opt--active');
      $(this).closest('.ss-atspek-opt').addClass('ss-atspek-opt--active');
      $('#ss-atspek-hours-wrap').toggle(val==='hours');
    });

    $('#ss-atspek-save').on('click',function(){
      var mode=$('input[name="ss_atspek_mode"]:checked').val()||'day';
      var hours=parseInt($('#ss-atspek-hours').val(),10)||24;
      hours=Math.min(168,Math.max(1,hours));
      var $btn=$(this).prop('disabled',true).text('Saugoma…');
      var $msg=$('#ss-atspek-msg').hide();
      $.post(ss_pub.ajax_url,{action:'ss_settings_save_atspek',nonce:nonce,mode:mode,hours:hours},function(r){
        $btn.prop('disabled',false).text('Išsaugoti');
        if(r.success){
          $msg.removeClass('ss-err').addClass('ss-ok ss-smsg').text('✓ Išsaugota.').show();
          SS.toast('Ątspėk nustatymai išsaugoti.');
          setTimeout(function(){ $msg.fadeOut(400); },3000);
        } else {
          $msg.removeClass('ss-ok').addClass('ss-err ss-smsg').text(r.data&&r.data.message?r.data.message:'Klaida.').show();
        }
      },'json').fail(function(){
        $btn.prop('disabled',false).text('Išsaugoti');
        $msg.removeClass('ss-ok').addClass('ss-err ss-smsg').text('Ryšio klaida.').show();
      });
    });

    /* ── Privacy: hide name in quiz results ── */
    $('#ss-anon-quiz').on('change',function(){
      var hide=$(this).is(':checked')?1:0;
      var $msg=$('#ss-anon-msg').hide();
      $.post(ss_pub.ajax_url,{action:'ss_settings_save_anon_quiz',nonce:nonce,hide:hide},function(r){
        if(r.success){
          $msg.removeClass('ss-err').addClass('ss-ok ss-smsg').text(hide?'✓ Vardas paslėptas.':'✓ Vardas rodomas.').show();
          SS.toast(hide?'Vardas viktorinose paslėptas.':'Vardas viktorinose rodomas.');
          setTimeout(function(){ $msg.fadeOut(400); },3000);
        } else {
          $msg.removeClass('ss-ok').addClass('ss-err ss-smsg').text(r.data&&r.data.message?r.data.message:'Klaida.').show();
        }
      },'json').fail(function(){
        $msg.removeClass('ss-ok').addClass('ss-err ss-smsg').text('Ryšio klaida.').show();
      });
    });

    /* ── Security: new-device login notifications ── */
    $('#ss-new-device').on('change',function(){
      var on=$(this).is(':checked')?1:0;
      var $msg=$('#ss-new-device-msg').hide();
      $.post(ss_pub.ajax_url,{action:'ss_settings_save_new_device',nonce:nonce,on:on},function(r){
        if(r.success){
          $msg.removeClass('ss-err').addClass('ss-ok ss-smsg').text(on?'✓ Pranešimai įjungti.':'✓ Pranešimai išjungti.').show();
          SS.toast(on?'Pranešimai apie naują įrenginį įjungti.':'Pranešimai apie naują įrenginį išjungti.');
          setTimeout(function(){ $msg.fadeOut(400); },3000);
        } else {
          $msg.removeClass('ss-ok').addClass('ss-err ss-smsg').text(r.data&&r.data.message?r.data.message:'Klaida.').show();
        }
      },'json').fail(function(){
        $msg.removeClass('ss-ok').addClass('ss-err ss-smsg').text('Ryšio klaida.').show();
      });
    });

    /* ── Mano tvarkaraštis: apply code / check by email ── */
    function schedMsg(text, ok){
      $('#ss-sched-msg').removeClass('ss-ok ss-err').addClass(ok?'ss-ok':'ss-err').text(text).show();
    }
    $('#ss-sched-code').on('input',function(){ this.value=this.value.toUpperCase().replace(/[^A-Z0-9]/g,'').slice(0,8); });
    $('#ss-sched-apply').on('click',function(){
      var code=($('#ss-sched-code').val()||'').trim();
      if(code.length<6){ schedMsg('Įveskite teisingą tvarkaraščio kodą.',false); return; }
      var $b=$(this).prop('disabled',true).text('Tikrinama…');
      $.post(ss_pub.ajax_url,{action:'ss_settings_apply_code',nonce:nonce,code:code},function(r){
        $b.prop('disabled',false).text('Gauti tvarkaraštį');
        if(r.success){ schedMsg('✓ Tvarkaraštis pakeistas į „'+r.data.class_name+'". Įkeliama…',true); SS.toast('Tvarkaraštis atnaujintas.'); setTimeout(function(){location.reload();},1100); }
        else { schedMsg((r.data&&r.data.message)||'Klaida.',false); }
      },'json').fail(function(){ $b.prop('disabled',false).text('Gauti tvarkaraštį'); schedMsg('Ryšio klaida.',false); });
    });
    $('#ss-sched-check').on('click',function(){
      var $b=$(this).prop('disabled',true).text('Tikrinama…');
      $.post(ss_pub.ajax_url,{action:'ss_settings_check_email',nonce:nonce},function(r){
        $b.prop('disabled',false).text('Patikrinti pagal paštą');
        if(r.success && r.data.found){ schedMsg('✓ Pagal el. paštą priskirtas tvarkaraštis „'+r.data.class_name+'". Įkeliama…',true); SS.toast('Tvarkaraštis atnaujintas.'); setTimeout(function(){location.reload();},1100); }
        else if(r.success){ schedMsg('Jūsų el. paštui tvarkaraštis nepriskirtas. Naudokite kodą.',false); }
        else { schedMsg((r.data&&r.data.message)||'Klaida.',false); }
      },'json').fail(function(){ $b.prop('disabled',false).text('Patikrinti pagal paštą'); schedMsg('Ryšio klaida.',false); });
    });

    /* ── Settings search: filter cards live ── */
    $('#ss-set-search').on('input',function(){
      var q=($(this).val()||'').toLowerCase().trim();
      var any=false;
      $('.ss-settings-wrap .ss-scard').each(function(){
        var hit = !q || $(this).text().toLowerCase().indexOf(q)>=0;
        $(this).toggle(hit); if(hit) any=true;
      });
      $('#ss-set-noresults').toggle(!any && !!q);
    });

    /* ── Logo upload (admin only) ── */
    $('#ss-logo-input').on('change',function(){
      var file=this.files[0];
      if(!file) return;
      if(file.size>3*1024*1024){ SS.toast('Failas per didelis (maks. 3 MB).',true); $(this).val(''); return; }
      var fd=new FormData();
      fd.append('action','ss_settings_upload_logo');
      fd.append('nonce',nonce);
      fd.append('logo',file,file.name);
      var $prog=$('#ss-logo-prog').show();
      var $fill=$('#ss-logo-prog-fill').css('width','0%');
      var $msg=$('#ss-logo-msg').hide();
      $.ajax({
        url:ss_pub.ajax_url,type:'POST',data:fd,processData:false,contentType:false,
        xhr:function(){
          var x=new window.XMLHttpRequest();
          x.upload.addEventListener('progress',function(e){
            if(e.lengthComputable) $fill.css('width',Math.round(e.loaded/e.total*100)+'%');
          });
          return x;
        },
        success:function(r){
          $prog.hide();
          if(r.success){
            var src=r.data.url+'?v='+Date.now();
            $('#ss-logo-preview').attr('src',src).show();
            $('#ss-logo-preview-placeholder').hide();
            $('.ss-header-logo').attr('src',src).show();
            $msg.removeClass('ss-err').addClass('ss-ok ss-smsg').text('✓ Logotipas įkeltas.').show();
            setTimeout(function(){ $msg.fadeOut(400); },3000);
            SS.toast('Logotipas išsaugotas.');
            // Show delete button if not already present
            if(!$('#ss-logo-del').length){
              $('<button type="button" id="ss-logo-del" class="ss-btn ss-btn-sm" style="background:#fee2e2;color:#b91c1c;border:none;">Pašalinti</button>').insertAfter($('#ss-logo-input').closest('label'));
              bindLogoDel();
            }
          } else {
            $msg.removeClass('ss-ok').addClass('ss-err ss-smsg').text(r.data&&r.data.message?r.data.message:'Klaida.').show();
          }
        },
        error:function(){ $prog.hide(); $msg.removeClass('ss-ok').addClass('ss-err ss-smsg').text('Ryšio klaida.').show(); }
      });
      $(this).val('');
    });

    function bindLogoDel(){
      $('#ss-logo-del').off('click').on('click',function(){
        if(!confirm('Pašalinti logotipą?')) return;
        var $btn=$(this).prop('disabled',true);
        $.post(ss_pub.ajax_url,{action:'ss_settings_delete_logo',nonce:nonce},function(r){
          $btn.prop('disabled',false);
          if(r.success){
            $('#ss-logo-preview').attr('src','').hide();
            $('#ss-logo-preview-placeholder').show();
            $('.ss-header-logo').hide();
            $btn.remove();
            SS.toast('Logotipas pašalintas.');
          } else { SS.toast(r.data&&r.data.message?r.data.message:'Klaida.',true); }
        },'json').fail(function(){ $btn.prop('disabled',false); SS.toast('Ryšio klaida.',true); });
      });
    }
    bindLogoDel();

    /* ── Password change flow ── */
    var $step1=$('#ss-pw-step1'),$step2=$('#ss-pw-step2'),$step3=$('#ss-pw-step3'),$pwmsg=$('#ss-pw-msg');
    function pwMsg(txt,ok){
      $pwmsg.removeClass('ss-ok ss-err').addClass(ok?'ss-ok':'ss-err').text(txt).show();
    }

    $('#ss-pw-send-code').on('click',function(){
      var $btn=$(this).prop('disabled',true).text('Siunčiama…');
      $pwmsg.hide();
      $.post(ss_pub.ajax_url,{action:'ss_settings_send_pw_code',nonce:nonce},function(r){
        $btn.prop('disabled',false).text('Gauti patvirtinimo kodą');
        if(r.success){ pwMsg(r.data.message||'Kodas išsiųstas.',true); $step1.hide(); $step2.show(); $('#ss-pw-code-inp').val('').focus(); }
        else{ pwMsg(r.data.message||'Klaida.',false); }
      },'json').fail(function(){ $btn.prop('disabled',false); pwMsg('Ryšio klaida.',false); });
    });

    $('#ss-pw-resend').on('click',function(){
      $step2.hide(); $step1.show(); $('#ss-pw-send-code').trigger('click');
    });

    $('#ss-pw-verify-code').on('click',function(){
      var code=$('#ss-pw-code-inp').val().replace(/\D/g,'');
      if(code.length!==6){ pwMsg('Įveskite 6 skaitmenų kodą.',false); return; }
      var $btn=$(this).prop('disabled',true).text('Tikrinama…');
      $pwmsg.hide();
      $.post(ss_pub.ajax_url,{action:'ss_settings_verify_pw_code',nonce:nonce,code:code},function(r){
        $btn.prop('disabled',false).text('Patvirtinti');
        if(r.success){ pwMsg(r.data.message||'Patvirtinta.',true); $step2.hide(); $step3.show(); $('#ss-pw-new').focus(); }
        else{ pwMsg(r.data.message||'Klaida.',false); }
      },'json').fail(function(){ $btn.prop('disabled',false); pwMsg('Ryšio klaida.',false); });
    });

    $('#ss-pw-code-inp').on('keydown',function(e){ if(e.key==='Enter') $('#ss-pw-verify-code').trigger('click'); });

    $('#ss-pw-submit').on('click',function(){
      var pw=$('#ss-pw-new').val();
      var pw2=$('#ss-pw-new2').val();
      if(pw.length<8){ pwMsg('Slaptažodis turi būti bent 8 simbolių.',false); return; }
      if(pw!==pw2){ pwMsg('Slaptažodžiai nesutampa.',false); return; }
      var $btn=$(this).prop('disabled',true).text('Keičiama…');
      $pwmsg.hide();
      $.post(ss_pub.ajax_url,{action:'ss_settings_change_pw',nonce:nonce,password:pw,password2:pw2},function(r){
        $btn.prop('disabled',false).text('Pakeisti slaptažodį');
        if(r.success){
          pwMsg('✓ '+( r.data.message||'Slaptažodis pakeistas.'),true);
          $step3.hide(); $step1.show();
          $('#ss-pw-new,#ss-pw-new2').val('');
          SS.toast('Slaptažodis sėkmingai pakeistas.');
        } else{ pwMsg(r.data.message||'Klaida.',false); }
      },'json').fail(function(){ $btn.prop('disabled',false); pwMsg('Ryšio klaida.',false); });
    });

    /* ── Sessions ── */
    SS.settingsLoadSessions();
    SS.settingsLoadDevices();
  },

  settingsBindDelAvatar:function(){
    $(document).off('click','#ss-avatar-del').on('click','#ss-avatar-del',function(){
      if(!confirm('Pašalinti profilio nuotrauką?')) return;
      $.post(ss_pub.ajax_url,{action:'ss_settings_delete_avatar',nonce:ss_pub.front_nonce},function(r){
        if(r.success){
          var def='https://www.gravatar.com/avatar/?d=mystery&s=80';
          $('#ss-avatar-preview').attr('src',def);
          $('.ss-hdr-avatar').attr('src',def);
          $('#ss-avatar-del').remove();
          SS.toast('Nuotrauka pašalinta.');
        } else{ SS.toast(r.data&&r.data.message?r.data.message:'Klaida.',true); }
      },'json');
    });
  },

  settingsLoadSessions:function(){
    var $list=$('#ss-sessions-list');
    if(!$list.length) return;
    $.post(ss_pub.ajax_url,{action:'ss_settings_get_sessions',nonce:ss_pub.front_nonce},function(r){
      if(!r.success){ $list.html('<p class="ss-scard-hint">Nepavyko gauti sesijų.</p>'); return; }
      var sessions=r.data.sessions||[];
      if(!sessions.length){ $list.html('<p class="ss-scard-hint">Nėra aktyvių sesijų.</p>'); return; }
      var html='';
      $.each(sessions,function(i,s){
        var ua=SS.settingsParseUA(s.ua||'');
        var isCurrent=s.is_current;
        html+='<div class="ss-session-item">';
        html+='<div class="ss-session-ico'+(isCurrent?' ss-session-current':'')+'"><svg viewBox="0 0 20 20" fill="currentColor" width="18" height="18"><path fill-rule="evenodd" d="M3 5a2 2 0 012-2h10a2 2 0 012 2v8a2 2 0 01-2 2h-2.22l.123.489.804.804A1 1 0 0113 18H7a1 1 0 01-.707-1.707l.804-.804L7.22 15H5a2 2 0 01-2-2V5zm5.771 7H5V5h10v7H8.771z" clip-rule="evenodd"/></svg></div>';
        html+='<div class="ss-session-body">';
        html+='<span class="ss-session-ua">'+SS.esc(ua.name)+(isCurrent?'<span class="ss-session-badge">Dabartinė</span>':'')+'</span>';
        html+='<div class="ss-session-meta">';
        if(s.geo) html+='<span>📍 '+SS.esc(s.geo)+'</span>';
        if(s.ip) html+='<span>· IP: '+SS.esc(s.ip)+'</span>';
        if(s.expiration) html+='<span>· Galioja iki: '+SS.esc(s.expiration)+'</span>';
        html+='</div></div>';
        if(!isCurrent){
          html+='<button type="button" class="ss-btn-danger ss-revoke-session" data-token="'+SS.esc(s.token)+'">Atjungti</button>';
        }
        html+='</div>';
      });
      $list.html(html);

      $list.on('click','.ss-revoke-session',function(){
        if(!confirm('Atjungti šią sesiją?')) return;
        var token=$(this).data('token');
        var $btn=$(this).prop('disabled',true).text('Atjungiama…');
        $.post(ss_pub.ajax_url,{action:'ss_settings_revoke_session',nonce:ss_pub.front_nonce,token:token},function(r){
          if(r.success){ $btn.closest('.ss-session-item').fadeOut(200,function(){$(this).remove();}); SS.toast('Sesija atjungta.'); }
          else{ $btn.prop('disabled',false).text('Atjungti'); SS.toast(r.data&&r.data.message?r.data.message:'Klaida.',true); }
        },'json');
      });
    },'json');
  },

  settingsParseUA:function(ua){
    if(!ua) return {name:'Nežinomas įrenginys'};
    var name='Naršyklė';
    if(/Chrome\/[0-9]/.test(ua)&&!/Chromium|Edg\/|OPR\//.test(ua)) name='Chrome';
    else if(/Firefox\//.test(ua)) name='Firefox';
    else if(/Edg\//.test(ua)) name='Edge';
    else if(/OPR\/|Opera\//.test(ua)) name='Opera';
    else if(/Safari\//.test(ua)&&!/Chrome/.test(ua)) name='Safari';
    else if(/MSIE|Trident/.test(ua)) name='Internet Explorer';
    var os='';
    if(/Windows NT/.test(ua)) os=' · Windows';
    else if(/Mac OS X/.test(ua)) os=' · macOS';
    else if(/Linux/.test(ua)&&!/Android/.test(ua)) os=' · Linux';
    else if(/Android/.test(ua)) os=' · Android';
    else if(/iPhone|iPad/.test(ua)) os=' · iOS';
    return {name:name+os};
  },

  settingsMethodLabel:function(method){
    var map={'password':'Slaptažodis','2fa':'Slaptažodis + 2FA','backup_code':'Atsarginis 2FA kodas','qr':'QR kodas','google':'Google paskyra'};
    return map[method]||method||'Slaptažodis';
  },

  settingsLoadDevices:function(){
    var $list=$('#ss-devices-list');
    if(!$list.length) return;

    function render(devs){
      if(!devs.length){$list.html('<p class="ss-scard-hint">Istorija tuščia — prisijungimai bus fiksuojami nuo šiol.</p>');return;}
      var html='';
      $.each(devs,function(i,d){
        var ua=SS.settingsParseUA(d.ua||'');
        var methodLbl=SS.settingsMethodLabel(d.method);
        var isMob=/Android|iPhone|iPad/.test(d.ua||'');
        var devIco=isMob
          ? '<svg viewBox="0 0 20 20" fill="currentColor" width="18" height="18"><path fill-rule="evenodd" d="M7 2a2 2 0 00-2 2v12a2 2 0 002 2h6a2 2 0 002-2V4a2 2 0 00-2-2H7zm3 14a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/></svg>'
          : '<svg viewBox="0 0 20 20" fill="currentColor" width="18" height="18"><path fill-rule="evenodd" d="M3 5a2 2 0 012-2h10a2 2 0 012 2v8a2 2 0 01-2 2h-2.22l.123.489.804.804A1 1 0 0113 18H7a1 1 0 01-.707-1.707l.804-.804L7.22 15H5a2 2 0 01-2-2V5zm5.771 7H5V5h10v7H8.771z" clip-rule="evenodd"/></svg>';
        var methodBadge='<span class="ss-dev-badge ss-dev-badge--'+SS.esc(d.method||'password')+'">'+SS.esc(methodLbl)+'</span>';
        var stateBadge=d.is_blocked?'<span class="ss-dev-badge ss-dev-badge--blocked">Užblokuotas</span>':'';
        var currentBadge=d.is_current?'<span class="ss-dev-badge ss-dev-badge--current">Dabartinis</span>':'';

        var actionBtn='';
        if(!d.is_current){
          if(d.is_blocked){
            actionBtn='<button type="button" class="ss-btn-ghost ss-unblock-device" data-fp="'+SS.esc(d.fp)+'">Atblokuoti</button>';
          } else {
            actionBtn='<button type="button" class="ss-btn-danger ss-block-device" data-fp="'+SS.esc(d.fp)+'">Blokuoti</button>';
          }
        }

        html+='<div class="ss-session-item'+(d.is_blocked?' ss-dev-item--blocked':'')+'" data-fp="'+SS.esc(d.fp)+'">';
        html+='<div class="ss-session-ico">'+devIco+'</div>';
        html+='<div class="ss-session-body">';
        html+='<span class="ss-session-ua">'+SS.esc(ua.name)+' '+methodBadge+currentBadge+stateBadge+'</span>';
        html+='<div class="ss-session-meta">';
        if(d.geo)  html+='<span>📍 '+SS.esc(d.geo)+'</span>';
        if(d.ip)   html+='<span>· IP: '+SS.esc(d.ip)+'</span>';
        if(d.date) html+='<span>· '+SS.esc(d.date)+'</span>';
        html+='</div></div>';
        if(actionBtn) html+='<div class="ss-session-actions">'+actionBtn+'</div>';
        html+='</div>';
      });
      $list.html(html);

      /* Blokuoti */
      $list.off('click','.ss-block-device').on('click','.ss-block-device',function(){
        if(!confirm('Užblokuoti šį įrenginį? Iš jo nebegalima bus prisijungti prie Jūsų paskyros.')) return;
        var fp=$(this).data('fp');
        var $btn=$(this).prop('disabled',true).text('Blokuojama…');
        $.post(ss_pub.ajax_url,{action:'ss_settings_block_device',nonce:ss_pub.front_nonce,fp:fp},function(r){
          if(r.success){
            SS.toast('Įrenginys užblokuotas.');
            SS.settingsLoadDevices();
          } else {
            $btn.prop('disabled',false).text('Blokuoti');
            SS.toast(r.data&&r.data.message?r.data.message:'Klaida.',true);
          }
        },'json');
      });

      /* Atblokuoti */
      $list.off('click','.ss-unblock-device').on('click','.ss-unblock-device',function(){
        if(!confirm('Atblokuoti šį įrenginį?')) return;
        var fp=$(this).data('fp');
        var $btn=$(this).prop('disabled',true).text('Atblokuojama…');
        $.post(ss_pub.ajax_url,{action:'ss_settings_unblock_device',nonce:ss_pub.front_nonce,fp:fp},function(r){
          if(r.success){
            SS.toast('Įrenginys atblokuotas.');
            SS.settingsLoadDevices();
          } else {
            $btn.prop('disabled',false).text('Atblokuoti');
            SS.toast(r.data&&r.data.message?r.data.message:'Klaida.',true);
          }
        },'json');
      });
    }

    $.post(ss_pub.ajax_url,{action:'ss_settings_get_devices',nonce:ss_pub.front_nonce},function(r){
      if(!r.success){$list.html('<p class="ss-scard-hint">Nepavyko gauti istorijos.</p>');return;}
      render(r.data.devices||[]);
    },'json');
  },

  /* ════ TOAST ════ */
  toast:function(msg,err){
    var $t=$('<div>').css({position:'fixed',bottom:'24px',right:'24px',zIndex:99999,background:err?'var(--r)':'var(--dk)',color:'var(--wh)',padding:'12px 20px',borderRadius:'11px',fontSize:'14px',fontWeight:'600',display:'flex',alignItems:'center',gap:'10px',boxShadow:'0 8px 28px rgba(1,42,54,.25)',transform:'translateY(60px)',opacity:'0',transition:'all .3s cubic-bezier(.4,0,.2,1)'}).append($('<span>').css({width:'7px',height:'7px',borderRadius:'50%',background:err?'#F87171':'var(--gl)',flexShrink:0})).append(msg).appendTo('body');
    setTimeout(function(){$t.css({transform:'translateY(0)',opacity:'1'});},10);
    setTimeout(function(){$t.css({transform:'translateY(60px)',opacity:'0'});setTimeout(function(){$t.remove();},320);},2800);
  },
  esc:function(s){return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');}
};

/* ═══════════════════════════════════════════════════════
   PROMO KARUSELĖ — carousel + Ąspėk uždarymas
═══════════════════════════════════════════════════════ */
SS.promoCarousel = function() {
  var $wrap = $('#ss-promo-wrap');
  if (!$wrap.length) return;

  var total    = parseInt($wrap.data('total'), 10) || 1;
  var rotate   = parseInt($wrap.data('rotate'), 10) || 15000;
  var mode     = $wrap.data('atspek-mode') || 'day';
  var hours    = parseInt($wrap.data('atspek-hours'), 10) || 24;
  var current  = 0;
  var timer    = null;

  /* ── Ąspėk visibility check ── */
  var atspekSlide = $wrap.find('[data-slide-type="atspek"]');
  if (atspekSlide.length) {
    var hide = false;
    if (mode === 'off') {
      hide = true;
    } else {
      var now = Date.now();
      var ttlMs = mode === 'hours' ? hours * 3600000 : 86400000;
      var storedTs  = parseInt(localStorage.getItem('ss_atspek_ts') || '0', 10);
      var storedDay = localStorage.getItem('ss_atspek_day') || '';
      var today     = new Date().toISOString().slice(0, 10);
      if (mode === 'day'   && storedDay === today)              hide = true;
      if (mode === 'hours' && storedTs && (now - storedTs) < ttlMs) hide = true;
    }
    if (hide) {
      atspekSlide.remove();
      // Rebuild items list after removal
      $wrap.find('.ss-promo-slide').each(function(i) {
        $(this).attr('data-idx', i).toggleClass('is-active', i === 0);
      });
      total = $wrap.find('.ss-promo-slide').length;
      if (!total) { $wrap.hide(); return; }
      // Re-build dots
      rebuildDots();
    }
  }

  // Now reveal after atspek decision (no flash)
  $wrap.css('visibility', 'visible');

  if (total <= 1) {
    $wrap.find('.ss-promo-arrow, .ss-promo-dots').hide();
  }

  function rebuildDots() {
    var $dots = $wrap.find('.ss-promo-dots').empty();
    $wrap.find('.ss-promo-slide').each(function(i) {
      $dots.append(
        $('<button type="button" class="ss-promo-dot' + (i === 0 ? ' is-active' : '') + '">')
          .attr('data-dot-idx', i)
      );
    });
  }

  function goTo(idx) {
    var $slides = $wrap.find('.ss-promo-slide');
    var $dots   = $wrap.find('.ss-promo-dot');
    current = ((idx % total) + total) % total;
    $slides.removeClass('is-active').eq(current).addClass('is-active');
    $dots.removeClass('is-active').eq(current).addClass('is-active');
  }

  function startTimer() {
    clearInterval(timer);
    if (total <= 1) return;
    timer = setInterval(function() { goTo(current + 1); }, rotate);
  }

  startTimer();

  // Arrows
  $wrap.on('click', '.ss-promo-arrow-prev', function() {
    goTo(current - 1); startTimer();
  });
  $wrap.on('click', '.ss-promo-arrow-next', function() {
    goTo(current + 1); startTimer();
  });

  // Dots
  $wrap.on('click', '.ss-promo-dot', function() {
    goTo(parseInt($(this).data('dot-idx'), 10)); startTimer();
  });

  // Ąspėk close button
  $wrap.on('click', '#ss-atspek-close', function(e) {
    e.preventDefault(); e.stopPropagation();
    localStorage.setItem('ss_atspek_ts',  String(Date.now()));
    localStorage.setItem('ss_atspek_day', new Date().toISOString().slice(0, 10));
    var $slide = $(this).closest('.ss-promo-slide');
    $slide.remove();
    total = $wrap.find('.ss-promo-slide').length;
    if (!total) { $wrap.hide(); clearInterval(timer); return; }
    current = 0;
    $wrap.find('.ss-promo-slide').eq(0).addClass('is-active');
    rebuildDots();
    if (total <= 1) $wrap.find('.ss-promo-arrow, .ss-promo-dots').hide();
    startTimer();
  });
};

/* ═══════════════════════════════════════════════════════
   APKLAUSŲ / VIKTORINŲ FRONTEND MODALAS
═══════════════════════════════════════════════════════ */
/* ════ ĄŽUOLYNO EUROVIZIJA — balsavimas ════ */
SS.eurovizija = function() {
  var $root = $('#ss-euro');
  if (!$root.length) return;
  var $form = $('#ss-euro-form');
  if (!$form.length) return; // ne balsavimo (ballot) būsena — nieko nedarom

  // Pasirinktos kortelės paryškinimas (vienas pasirinkimas grupėje).
  $form.on('change', 'input[type=radio]', function(){
    var nm = $(this).attr('name');
    $form.find('input[name="'+nm+'"]').closest('.ss-euro-card').removeClass('is-selected');
    $(this).closest('.ss-euro-card').addClass('is-selected');
  });

  $form.on('submit', function(e){
    e.preventDefault();
    var ids = [];
    $form.find('input[type=radio]:checked').each(function(){ ids.push(parseInt(this.value, 10)); });
    var $msg = $('#ss-euro-msg');
    if (!ids.length){ $msg.text('Pasirinkite bent vieną dalyvį.').css('color','var(--r)').show(); return; }

    var $btn = $('#ss-euro-submit').prop('disabled', true);
    $.post(ss_pub.ajax_url, {
      action: 'ss_euro_vote',
      nonce: ss_pub.front_nonce,
      candidate_ids: JSON.stringify(ids)
    }, function(res){
      if (res && res.success){
        // Serveris perpiešs „ačiū" būseną po perkrovimo.
        window.location.reload();
      } else {
        $btn.prop('disabled', false);
        $msg.text((res && res.data && res.data.message) || 'Klaida.').css('color','var(--r)').show();
      }
    }).fail(function(){
      $btn.prop('disabled', false);
      $msg.text('Ryšio klaida.').css('color','var(--r)').show();
    });
  });
};

SS.surveyModal = function() {
  var $overlay = $('#ss-survey-modal');
  if (!$overlay.length) return;

  var $body   = $('#ss-svf-body');
  var $submit = $('#ss-svf-submit');
  var $pill   = $('#ss-svf-pill');
  var $title  = $('#ss-svf-title');
  var $times  = $('#ss-svf-times');
  var sid  = null;   // dabar atidarytos apklausos id
  var data = null;   // serverio duomenys

  /* ── Pagalbinės ── */
  function esc(s){ return String(s == null ? '' : s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
  function p2(n){ return n < 10 ? '0' + n : '' + n; }
  function ltDateTime(dt){
    if (!dt) return '';
    var d = new Date((dt + '').replace(' ', 'T'));
    if (isNaN(d)) return dt;
    return d.getFullYear() + '-' + p2(d.getMonth()+1) + '-' + p2(d.getDate()) + ' ' + p2(d.getHours()) + ':' + p2(d.getMinutes());
  }

  /* SVG ikonos (jokių emoji) */
  var SVG = {
    trophy: '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M18 2H6v2H2v3a5 5 0 0 0 5 5h.41A6 6 0 0 0 11 15.9V18H7v2h10v-2h-4v-2.1a6 6 0 0 0 3.59-3.9H17a5 5 0 0 0 5-5V4h-4V2zM6 10a3 3 0 0 1-3-3V6h3v4zm15-3a3 3 0 0 1-3 3V6h3v1z"/></svg>',
    clock:  '<svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"/></svg>',
    check:  '<svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>',
    chart:  '<svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M2 11a1 1 0 011-1h2a1 1 0 011 1v5a1 1 0 01-1 1H3a1 1 0 01-1-1v-5zM8 7a1 1 0 011-1h2a1 1 0 011 1v9a1 1 0 01-1 1H9a1 1 0 01-1-1V7zM14 4a1 1 0 011-1h2a1 1 0 011 1v12a1 1 0 01-1 1h-2a1 1 0 01-1-1V4z"/></svg>',
    info:   '<svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/></svg>',
    spark:  '<svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M10 1.8l1.7 4.6 4.6 1.7-4.6 1.7L10 14.4 8.3 9.8 3.7 8.1l4.6-1.7L10 1.8z"/></svg>',
    up:     '<svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M14.707 12.707a1 1 0 01-1.414 0L10 9.414l-3.293 3.293a1 1 0 01-1.414-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 010 1.414z" clip-rule="evenodd"/></svg>',
    down:   '<svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>'
  };

  /* Dalyvio avataras (WP) arba inicialo apskritimas */
  function lbAvatar(e){
    if (e.avatar) return '<img class="ss-podium-av" src="' + esc(e.avatar) + '" alt="" loading="lazy">';
    var ini = (String(e.name || '?').trim().charAt(0) || '?').toUpperCase();
    return '<span class="ss-podium-av ss-podium-av-fb">' + esc(ini) + '</span>';
  }

  /* Pjedestalas: 2 vieta kairėje, 1 centre, 3 dešinėje */
  function podiumHtml(top){
    var byRank = {}; top.forEach(function(e){ byRank[e.rank] = e; });
    function col(e){
      if (!e) return '';
      var rpct = e.max > 0 ? Math.round(e.score * 100 / e.max) : 0;
      return '<div class="ss-podium-col p' + e.rank + (e.is_me ? ' is-me' : '') + '">'
        + '<div class="ss-podium-top">' + lbAvatar(e)
        + '<div class="ss-podium-name" title="' + esc(e.name) + '">' + esc(e.name) + '</div>'
        + '<div class="ss-podium-score">' + e.score + '/' + e.max + ' · ' + rpct + '%</div></div>'
        + '<div class="ss-podium-base"><span class="ss-podium-rank">' + e.rank + '</span>'
        + (e.is_me ? '<span class="ss-podium-you">Jūs</span>' : '') + '</div></div>';
    }
    return '<div class="ss-podium">' + col(byRank[2]) + col(byRank[1]) + col(byRank[3]) + '</div>';
  }

  function close(){ $overlay.attr('aria-hidden','true').hide(); }

  /* ── Atidarymas ── */
  $(document).on('click', '[data-survey-open]', function(){ openSurvey(parseInt($(this).data('survey-open'), 10)); });

  function openSurvey(id){
    sid = id; data = null;
    $title.text('Kraunama…'); $pill.hide(); $times.empty(); $submit.hide();
    $body.html('<div class="ss-svf-skel"><div class="ss-svf-skel-line w60"></div><div class="ss-svf-skel-block"></div><div class="ss-svf-skel-block"></div><div class="ss-svf-skel-line w40"></div><div class="ss-svf-skel-block"></div></div>');
    $overlay.removeAttr('aria-hidden').show();
    $.post(ss_pub.ajax_url, { action:'ss_survey_front_get', nonce:ss_pub.front_nonce, survey_id:id }, function(res){
      if (!res || !res.success) { statePanel(SVG.info, esc(res && res.data && res.data.message || 'Klaida.'), ''); return; }
      data = res.data; render(data);
    }, 'json').fail(function(){ statePanel(SVG.info, 'Ryšio klaida.', ''); });
  }

  /* ── Antraštė (pavadinimas, žyma, laikai) ── */
  function renderHead(s){
    var isQuiz = s.type === 'quiz';
    $title.text(s.title || (isQuiz ? 'Viktorina' : 'Apklausa'));
    $pill.removeClass('is-quiz').addClass(isQuiz ? 'is-quiz' : '').text(isQuiz ? 'Viktorina' : 'Apklausa').show();
    var $t = $times.empty();
    function add(label, dt, cls){
      if (!dt) return;
      $t.append('<span class="ss-stime is-' + cls + '"><span class="ss-stime-label">' + label + '</span> <span class="ss-stime-val">' + ltDateTime(dt) + '</span></span>');
    }
    if (s.can_answer && s.close_at)                        add('Iki', s.close_at, 'active');
    else if (!s.can_answer && !s.show_results && s.results_show_from) add('Rezultatai nuo', s.results_show_from, 'future');
    else if (s.show_results && s.results_close_at)         add('Rezultatai iki', s.results_close_at, 'active');
  }

  /* ── Karuselės kortelės mygtukas ── */
  function syncCard(label, cls){
    var $b = $('[data-survey-id="' + sid + '"] .ss-survey-banner-btn');
    if (!$b.length) return;
    $b.find('svg').remove();
    $b.removeClass('is-results is-waiting is-answered').addClass(cls || '').text(label);
  }

  /* ── Bendras „būsenos" langas (centruotas) ── */
  function statePanel(ico, title, sub){
    $submit.hide();
    $body.html('<div class="ss-svf-done"><div class="ss-svf-done-ico">' + ico + '</div>'
      + '<h3>' + title + '</h3>'
      + (sub ? '<p class="ss-svf-wait-sub">' + sub + '</p>' : '') + '</div>');
  }

  /* ── Pagrindinis maršrutizatorius ── */
  function render(s){
    renderHead(s);
    // 1) Dar neatsakė, gali atsakyti, rezultatai dar nematomi → klausimų forma.
    if (!s.answered && s.can_answer && !s.show_results) { syncCard('Dalyvauti', ''); renderForm(s); return; }
    // 2) Rezultatai matomi (atsakiusiems ir ne) → rezultatų langas.
    if (s.show_results) { syncCard('Rezultatai', 'is-results'); renderResults(s); return; }
    // 3) Atsakė, bet rezultatai dar nepaskelbti → padėka / laukimas.
    if (s.answered) {
      syncCard('Atsakyta', 'is-answered');
      statePanel(SVG.clock, s.type === 'quiz' ? 'Viktorina baigta!' : 'Ačiū už atsakymus!',
        s.results_show_from ? 'Rezultatai bus paskelbti ' + ltDateTime(s.results_show_from) : 'Rezultatai bus paskelbti vėliau.');
      return;
    }
    // 4) Neatsakė ir negali → uždaryta / dar neprasidėjo.
    syncCard('Laukiama…', 'is-waiting');
    var notStarted = s.open_from && new Date((s.open_from + '').replace(' ','T')) > new Date();
    if (notStarted) {
      statePanel(SVG.clock, (s.type === 'quiz' ? 'Viktorina' : 'Apklausa') + ' dar neprasidėjo', 'Pradžia: ' + ltDateTime(s.open_from));
    } else {
      statePanel(SVG.clock, (s.type === 'quiz' ? 'Viktorina baigta' : 'Apklausa baigta'),
        s.results_show_from ? 'Rezultatai bus paskelbti ' + ltDateTime(s.results_show_from) : 'Atsakymų priėmimas šiuo metu uždarytas.');
    }
  }

  /* ── Klausimų forma ── */
  function questionInput(q, qi){
    if (q.type === 'text') {
      return '<textarea class="ss-svf-text" rows="3" placeholder="Jūsų atsakymas…"></textarea>';
    }
    if (q.type === 'rank') {
      var opts = (q.options || []).map(function(o, oi){ return { oi: oi, text: o.text }; });
      opts.sort(function(){ return Math.random() - 0.5; });
      var rh = '<div class="ss-svf-rank">';
      opts.forEach(function(o, pos){
        rh += '<div class="ss-svf-rank-item" data-oi="' + o.oi + '">'
          + '<span class="ss-svf-rank-num">' + (pos + 1) + '</span>'
          + '<span class="ss-svf-rank-label">' + esc(o.text) + '</span>'
          + '<button type="button" class="ss-svf-rank-btn ss-rank-up" title="Aukštyn"' + (pos === 0 ? ' disabled' : '') + '>' + SVG.up + '</button>'
          + '<button type="button" class="ss-svf-rank-btn ss-rank-down" title="Žemyn"' + (pos === opts.length - 1 ? ' disabled' : '') + '>' + SVG.down + '</button>'
          + '</div>';
      });
      return rh + '</div>';
    }
    var it = q.type === 'multi' ? 'checkbox' : 'radio';
    var h = '';
    (q.options || []).forEach(function(o, oi){
      h += '<label class="ss-svf-opt"><input type="' + it + '" name="q_' + qi + '" value="' + oi + '" class="ss-svf-inp"><span class="ss-svf-opt-label">' + esc(o.text) + '</span></label>';
    });
    return h;
  }

  function renderForm(s){
    $submit.show().prop('disabled', false).text('Pateikti');
    var html = '';
    if (s.description) html += '<div class="ss-svf-desc">' + esc(s.description) + '</div>';
    (s.questions || []).forEach(function(q, qi){
      html += '<div class="ss-svf-q" data-qi="' + qi + '" data-type="' + esc(q.type || 'single') + '">'
        + '<p class="ss-svf-q-title"><span class="ss-svf-q-num">' + (qi + 1) + '</span><span>' + esc(q.text) + '</span></p>'
        + questionInput(q, qi)
        + '</div>';
    });
    $body.html(html);

    $body.off('click', '.ss-svf-opt').on('click', '.ss-svf-opt', function(){
      var $q = $(this).closest('.ss-svf-q');
      if ($q.data('type') !== 'multi') $q.find('.ss-svf-opt').removeClass('is-selected');
      $(this).toggleClass('is-selected', $(this).find('input').is(':checked'));
    });
    $body.off('click', '.ss-rank-up, .ss-rank-down').on('click', '.ss-rank-up, .ss-rank-down', function(){
      var $item = $(this).closest('.ss-svf-rank-item'), $rank = $item.closest('.ss-svf-rank');
      var isUp = $(this).hasClass('ss-rank-up'), $items = $rank.find('.ss-svf-rank-item'), idx = $items.index($item);
      if (isUp && idx > 0) $item.insertBefore($items.eq(idx - 1));
      else if (!isUp && idx < $items.length - 1) $item.insertAfter($items.eq(idx + 1));
      $rank.find('.ss-svf-rank-item').each(function(i){
        $(this).find('.ss-svf-rank-num').text(i + 1);
        $(this).find('.ss-rank-up').prop('disabled', i === 0);
        $(this).find('.ss-rank-down').prop('disabled', i === $rank.find('.ss-svf-rank-item').length - 1);
      });
    });
  }

  /* ── Pateikimas ── */
  $submit.on('click', function(){
    if (!data) return;
    var answers = {}, valid = true;
    (data.questions || []).forEach(function(q, qi){
      var $q = $body.find('[data-qi="' + qi + '"]'), t = q.type || 'single';
      if (t === 'text') {
        answers[qi] = ($q.find('.ss-svf-text').val() || '').trim();
      } else if (t === 'rank') {
        var ord = []; $q.find('.ss-svf-rank-item').each(function(){ ord.push(parseInt($(this).data('oi'), 10)); });
        answers[qi] = ord;
      } else if (t === 'multi') {
        var ch = []; $q.find('.ss-svf-inp:checked').each(function(){ ch.push(parseInt($(this).val(), 10)); });
        if (!ch.length) valid = false;
        answers[qi] = ch;
      } else {
        var v = $q.find('.ss-svf-inp:checked').val();
        if (v === undefined) valid = false;
        answers[qi] = parseInt(v, 10);
      }
    });
    if (!valid) { SS.toast('Atsakykite į visus klausimus.', true); return; }

    $submit.prop('disabled', true).text('Siunčiama…');
    $.post(ss_pub.ajax_url, { action:'ss_survey_submit_response', nonce:ss_pub.front_nonce, survey_id:sid, answers:JSON.stringify(answers) }, function(res){
      $submit.prop('disabled', false).text('Pateikti');
      if (!res || !res.success) { SS.toast(res && res.data && res.data.message || 'Klaida.', true); return; }
      var p = res.data;
      data.answered     = true;
      data.user_answers = answers;
      data.user_score   = p.score;
      data.user_max     = p.max;
      data.show_results = !!p.show_results;
      data.show_answers = !!p.show_answers;
      if (p.results) {
        data.aggregate       = p.results.aggregate;
        data.responses_count = p.results.responses_count;
        if (p.results.questions) data.questions = p.results.questions;
      }
      if (p.leaderboard) data.leaderboard = p.leaderboard;
      renderHead(data);
      if (data.show_results) { syncCard('Rezultatai', 'is-results'); renderResults(data); }
      else {
        syncCard('Atsakyta', 'is-answered');
        statePanel(SVG.clock, data.type === 'quiz' ? 'Viktorina baigta!' : 'Ačiū už atsakymus!',
          data.results_show_from ? 'Rezultatai bus paskelbti ' + ltDateTime(data.results_show_from) : 'Rezultatai bus paskelbti vėliau.');
      }
    }, 'json').fail(function(){ $submit.prop('disabled', false).text('Pateikti'); SS.toast('Ryšio klaida.', true); });
  });

  /* Iš rezultatų — atgal prie klausimų (kai dar gali atsakyti) */
  $body.on('click', '.ss-svf-answer-btn', function(){ if (data) renderForm(data); });

  /* ── Rezultatų klausimo eilutė ── */
  function resultQuestion(s, q, qi){
    var h = '<div class="ss-svf-result-q"><h4>' + (qi + 1) + '. ' + esc(q.text) + '</h4>';
    var agg = (s.aggregate && s.aggregate[qi]) || {};
    if (q.type === 'text') {
      var list = agg.text || [];
      if (!list.length) h += '<div class="ss-svf-text-ans" style="opacity:.6">—</div>';
      list.forEach(function(ta){ var t = (ta && typeof ta === 'object') ? ta.text : ta; h += '<div class="ss-svf-text-ans">' + esc(t) + '</div>'; });
    } else if (q.type === 'rank') {
      var ra = agg.rank_avg || {};
      var sorted = (q.options || []).map(function(o, oi){ return { oi: oi, text: o.text, avg: ra[oi] }; })
        .sort(function(a, b){ return (a.avg || 99) - (b.avg || 99); });
      sorted.forEach(function(it, i){
        h += '<div class="ss-svf-bar-row"><div class="ss-svf-bar-label">' + (i + 1) + '. ' + esc(it.text) + '</div>'
          + '<div class="ss-svf-bar-track"></div><div class="ss-svf-bar-count">Ø ' + (it.avg != null ? it.avg.toFixed(1) : '—') + '</div></div>';
      });
    } else {
      var counts = agg.counts || {}, total = 0; for (var k in counts) total += (counts[k] || 0);
      var ua = s.user_answers ? s.user_answers[qi] : null;
      (q.options || []).forEach(function(o, oi){
        var c = counts[oi] || 0, pct = total ? Math.round(c * 100 / total) : 0;
        var mine = (q.type === 'multi') ? (Array.isArray(ua) && ua.indexOf(oi) !== -1) : (parseInt(ua) === oi);
        var correct = o.correct;
        var lblCls = (mine ? ' is-mine' : '') + (correct ? ' is-correct' : '');
        var fillCls = correct ? '' : (mine && !correct ? ' is-wrong' : '');
        h += '<div class="ss-svf-bar-row"><div class="ss-svf-bar-label' + lblCls + '">' + esc(o.text)
          + (correct ? ' <span class="ss-svf-tag-ok">teisingas</span>' : '')
          + (mine && !correct ? ' <span class="ss-svf-tag-bad">jūsų</span>' : '')
          + '</div><div class="ss-svf-bar-track"><div class="ss-svf-bar-fill' + fillCls + '" style="width:' + pct + '%"></div></div>'
          + '<div class="ss-svf-bar-count">' + c + ' (' + pct + '%)</div></div>';
      });
    }
    return h + '</div>';
  }

  /* ── Rezultatų langas ── */
  function renderResults(s){
    $submit.hide();
    var isQuiz = s.type === 'quiz';
    var html = '';

    // Antraštės zona: viktorinos balas (jei atsakė) arba bendra antraštė.
    if (isQuiz && s.answered && s.user_max != null) {
      var sc = parseInt(s.user_score, 10) || 0, mx = parseInt(s.user_max, 10) || 0;
      var pct = mx > 0 ? Math.round(sc * 100 / mx) : 0;
      var tone = pct >= 80 ? 'is-great' : (pct >= 50 ? 'is-ok' : 'is-low');
      var head = pct >= 80 ? 'Puiku!' : (pct >= 50 ? 'Neblogai!' : 'Dar pasimokykite');
      html += '<div class="ss-quiz-score ' + tone + '">'
        + '<div class="ss-quiz-ring" style="--pct:' + pct + ';"><div class="ss-quiz-ring-in">' + pct + '<span>%</span></div></div>'
        + '<div class="ss-quiz-score-info"><div class="ss-quiz-score-head">' + head + '</div>'
        + '<div class="ss-quiz-score-pts">' + sc + ' / ' + mx + ' taškų</div></div></div>';
    } else {
      html += '<div class="ss-svf-done">'
        + '<div class="ss-svf-done-ico ' + (isQuiz ? 'is-quiz' : 'is-ok') + '">' + (isQuiz ? SVG.trophy : (s.answered ? SVG.check : SVG.chart)) + '</div>'
        + '<h3>' + (isQuiz ? 'Viktorinos rezultatai' : (s.answered ? 'Ačiū už atsakymus!' : 'Apklausos rezultatai')) + '</h3>'
        + (s.responses_count != null ? '<p class="ss-svf-wait-sub">Dalyvavo: ' + s.responses_count + '</p>' : '')
        + '</div>';
    }

    // Rezultatų lentelė (viktorinos) — pjedestalas su avatarais
    var lb = s.leaderboard;
    if (lb && lb.top && lb.top.length) {
      html += '<div class="ss-svf-leaderboard"><p class="ss-svf-lb-title"><span class="ss-svf-lb-ico">' + SVG.trophy + '</span> Rezultatų lentelė</p>';
      html += podiumHtml(lb.top);
      html += '</div>';
      if (lb.user_rank) {
        html += '<p class="ss-svf-user-rank">' + (lb.user_rank <= 3
          ? '<span class="ss-svf-rank-ico">' + SVG.spark + '</span> Jūs — <strong>' + lb.user_rank + ' vietoje</strong> iš ' + lb.total + ' dalyvių!'
          : 'Jūsų vieta: <strong>' + lb.user_rank + '</strong> iš ' + lb.total + ' dalyvių.') + '</p>';
      }
    }

    // Klausimų suvestinė (pilna peržiūra)
    if (s.show_answers && s.aggregate) {
      html += '<div class="ss-svf-results"><div class="ss-svf-results-h">Klausimai ir atsakymai</div>';
      (s.questions || []).forEach(function(q, qi){ html += resultQuestion(s, q, qi); });
      html += '</div>';
    }

    // Mygtukas atsakyti (dar gali)
    if (!s.answered && s.can_answer) {
      html += '<div class="ss-svf-answer-cta"><button type="button" class="ss-svf-answer-btn">' + (isQuiz ? 'Atsakyti į viktoriną' : 'Atsakyti į apklausą') + '</button></div>';
    }

    $body.html(html);
  }

  /* ── Uždarymas ── */
  $overlay.on('click', '[data-fr-close]', close);
  $overlay.on('click', function(e){ if (e.target === this) close(); });
  $(document).on('keydown', function(e){ if (e.key === 'Escape' && $overlay.is(':visible')) close(); });
};

/* ════════════════════════════════════════════════════════════════════
   QR KODO PRISIJUNGIMAS
   ════════════════════════════════════════════════════════════════════ */
SS.qrLogin = function() {
  if (!$('#ss-qr-open-btn').length) return;

  var AJAX   = (typeof ss_pub !== 'undefined') ? ss_pub.ajax_url : ajaxurl;
  var CIRC   = 2 * Math.PI * 38; // svg apskritimo perimetras (r=38)
  var token  = null;
  var pollTm = null;
  var timeTm = null;
  var expAt  = 0;
  var inFlight = false;   // ar šiuo metu vyksta ss_qr_poll užklausa
  var done     = false;   // ar jau pasiektas galutinis statusas (nebeklausti)

  /* ── Atidaryti / uždaryti modalą ── */
  function openModal() {
    $('#ss-qr-modal').fadeIn(200);
    $('body').addClass('ss-qr-open');
    createToken();
  }
  function closeModal() {
    $('#ss-qr-modal').fadeOut(180);
    $('body').removeClass('ss-qr-open');
    stopAll();
    token = null;
  }

  $('#ss-qr-open-btn').on('click', openModal);
  $('#ss-qr-close, #ss-qr-overlay').on('click', closeModal);
  $(document).on('keydown', function(e) {
    if (e.key === 'Escape' && $('#ss-qr-modal').is(':visible')) closeModal();
  });

  /* ── Sukurti žetoną ── */
  function createToken() {
    showState('loading');
    stopAll();
    token = null;
    done = false; inFlight = false;

    $.post(AJAX, { action: 'ss_qr_create' }, function(res) {
      if (!res.success) {
        alert(res.data.message || 'Klaida. Bandykite dar kartą.');
        closeModal();
        return;
      }
      token  = res.data.token;
      expAt  = Date.now() + res.data.expires_in * 1000;

      // QR generuojamas LOKALIAI (qrcode.min.js) — be išorinių užklausų, veikia ir
      // kai mokyklos tinkle blokuojami CDN. ECC 'H', kad logotipas centre netrukdytų.
      var $img = $('#ss-qr-img'), ok = false;
      if (typeof qrcode !== 'undefined') {
        try {
          var q = qrcode(0, 'H');
          q.addData(res.data.confirm_url);
          q.make();
          $img.attr('src', q.createDataURL(8, 8));
          ok = true;
        } catch (e) { ok = false; }
      }
      if (!ok) {
        $img.attr('src', 'data:image/svg+xml,' + encodeURIComponent(
          '<svg xmlns="http://www.w3.org/2000/svg" width="220" height="220"><rect fill="#f5f5f5" width="220" height="220"/><text x="110" y="105" text-anchor="middle" font-size="11" fill="#666">QR kodas</text><text x="110" y="122" text-anchor="middle" font-size="9" fill="#999">nepavyko sugeneruoti</text></svg>'
        ));
      }
      showState('active');
      startPoll();
      startTimer();
    }).fail(function() {
      alert('Ryšio klaida. Patikrinkite internetą ir bandykite dar kartą.');
      closeModal();
    });
  }

  /* ── Polling (kas 2s) ── */
  function startPoll() {
    stopPoll();
    pollTm = setInterval(function() {
      // Neklausti, jei nėra žetono, jau baigta, arba ankstesnė užklausa dar vykdoma
      // (svarbu: patvirtinimo apklausa serveryje gali užtrukti — kitaip antra apklausa
      //  perskaitytų jau suvartotą žetoną ir klaidingai parodytų „QR baigė galioti").
      if (!token || done || inFlight) return;
      inFlight = true;
      $.post(AJAX, { action: 'ss_qr_poll', token: token }, function(res) {
        if (done || !res.success) return;
        var st = res.data.status;
        if (st === 'confirmed') {
          done = true; stopAll(); showState('confirmed');
          setTimeout(function(){ window.location.href = res.data.redirect; }, 1400);
        } else if (st === '2fa_required') {
          done = true; stopAll();
          SS._pending2faToken = res.data.token;
          $('#ss-qr-modal').fadeOut(200, function(){
            var $tc2 = $('#ss-card-2fa');
            $('#ss-card-login').hide(); $tc2.fadeIn(160); $('#ss-2fa-code').val('').focus();
          });
        } else if (st === 'expired')  { done = true; stopAll(); showState('expired'); }
          else if (st === 'rejected') { done = true; stopAll(); showState('rejected'); }
      }).always(function(){ inFlight = false; });
    }, 2000);
  }
  function stopPoll() { if (pollTm) { clearInterval(pollTm); pollTm = null; } }

  /* ── Laikmatis ── */
  function startTimer() {
    stopTimer();
    tick();
    timeTm = setInterval(tick, 1000);
  }
  function stopTimer() { if (timeTm) { clearInterval(timeTm); timeTm = null; } }
  function tick() {
    var left = Math.max(0, Math.round((expAt - Date.now()) / 1000));
    var m = Math.floor(left / 60), s = left % 60;
    $('#ss-qr-timer-txt').text(m + ':' + (s < 10 ? '0' : '') + s);
    // Atnaujinti SVG apskritimą
    var frac   = left / 60;
    var offset = CIRC * (1 - frac);
    $('#ss-qr-timer-ring').attr('stroke-dashoffset', offset.toFixed(2));
    // Spalva: žalia → geltona → raudona
    var hue = Math.round(frac * 110); // 110° (žalia) → 0° (raudona)
    $('#ss-qr-timer-ring').css('stroke', 'hsl(' + hue + ',75%,45%)');
    if (left === 0) stopTimer();
  }

  function stopAll() { stopPoll(); stopTimer(); }

  /* ── Perjungti vaizdus ── */
  function showState(id) {
    $('#ss-qr-state-loading,#ss-qr-state-active,#ss-qr-state-confirmed,#ss-qr-state-expired,#ss-qr-state-rejected')
      .hide();
    $('#ss-qr-state-' + id).show();
  }

  /* ── Atnaujinimo mygtukai ── */
  $('#ss-qr-refresh, #ss-qr-refresh2').on('click', createToken);
};

/* ════════════════════════════════════════════════
   GOOGLE PRISIJUNGIMAS
   ════════════════════════════════════════════════ */

/* Pagalbinė funkcija: gauti Google auth URL iš serverio ir peradresuoti */
function ssGoogleRedirect(mode, returnUrl) {
  $.post(ss_pub.ajax_url, {
    action:     'ss_google_start',
    mode:       mode,
    return_url: returnUrl || window.location.href
  }, function(resp) {
    if (resp && resp.success && resp.data && resp.data.url) {
      window.location.href = resp.data.url;
    } else {
      var msg = (resp && resp.data && resp.data.message)
                ? resp.data.message
                : 'Klaida. Bandykite dar kartą.';
      alert(msg);
    }
  }).fail(function() {
    alert('Serverio klaida. Patikrinkite interneto ryšį.');
  });
}

/* ── Prisijungimo puslapio mygtukas ── */
$(document).on('click', '#ss-google-login-btn', function() {
  if (!ss_pub.google_enabled) return;
  var $btn = $(this);
  $btn.prop('disabled', true).css('opacity', 0.7);
  $.post(ss_pub.ajax_url, {
    action:     'ss_google_start',
    mode:       'login',
    return_url: window.location.href
  }, function(resp) {
    if (resp && resp.success && resp.data && resp.data.url) {
      window.location.href = resp.data.url;
    } else {
      var msg = (resp && resp.data && resp.data.message)
                ? resp.data.message
                : 'Klaida. Bandykite dar kartą.';
      $btn.prop('disabled', false).css('opacity', '');
      var $err = $('#ss-login-err');
      $err.text(msg).show();
    }
  }).fail(function() {
    $btn.prop('disabled', false).css('opacity', '');
    $('#ss-login-err').text('Serverio klaida. Patikrinkite interneto ryšį.').show();
  });
});

/* ── Nustatymų puslapio susieti/atsusieti ── */

/* Susieti */
$(document).on('click', '#ss-google-link-btn', function() {
  var $btn = $(this).prop('disabled', true).text('Kraunama…');
  var returnUrl = (ss_pub.settings_url || window.location.href).split('?')[0];
  $.post(ss_pub.ajax_url, {
    action:     'ss_google_start',
    mode:       'link',
    return_url: returnUrl
  }, function(resp) {
    if (resp && resp.success && resp.data && resp.data.url) {
      window.location.href = resp.data.url;
    } else {
      $btn.prop('disabled', false).text('Susieti su Google paskyra');
      var msg = (resp && resp.data && resp.data.message)
                ? resp.data.message : 'Klaida.';
      ssGoogleMsg(msg, false);
    }
  }).fail(function() {
    $btn.prop('disabled', false).text('Susieti su Google paskyra');
    ssGoogleMsg('Serverio klaida.', false);
  });
});

/* Atsusieti */
$(document).on('click', '#ss-google-unlink-btn', function() {
  if (!confirm('Ar tikrai norite atsusieti Google paskyrą?')) return;
  var $btn   = $(this);
  var nonce  = $btn.data('nonce');
  $btn.prop('disabled', true).text('Atsieta…');
  $.post(ss_pub.ajax_url, {
    action: 'ss_google_unlink',
    nonce:  nonce
  }, function(resp) {
    if (resp && resp.success) {
      // Pakeisti UI: parodyti "Susieti" mygtuką
      $('#ss-google-status').html(
        '<button type="button" class="ss-btn ss-btn-pri ss-btn-sm" id="ss-google-link-btn">' +
        '<svg width="14" height="14" viewBox="0 0 24 24" style="vertical-align:-2px" aria-hidden="true">' +
        '<path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#fff"/>' +
        '<path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#cde8cd"/>' +
        '<path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l3.66-2.84z" fill="#e8e0b0"/>' +
        '<path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#f2b8b8"/>' +
        '</svg> Susieti su Google paskyra</button>'
      );
      ssGoogleMsg('Google paskyra sėkmingai atsieta.', true);
    } else {
      $btn.prop('disabled', false).text('Atsusieti');
      var msg = (resp && resp.data && resp.data.message) ? resp.data.message : 'Klaida.';
      ssGoogleMsg(msg, false);
    }
  }).fail(function() {
    $btn.prop('disabled', false).text('Atsusieti');
    ssGoogleMsg('Serverio klaida.', false);
  });
});

function ssGoogleMsg(text, ok) {
  var $m = $('#ss-google-msg');
  $m.removeClass('ss-smsg-ok ss-smsg-err')
    .addClass(ok ? 'ss-smsg-ok' : 'ss-smsg-err')
    .text(text).show();
  if (ok) setTimeout(function(){ $m.fadeOut(); }, 4000);
}

/* ═══ 2FA kortelė ═══ */
SS.twoFactor = function(){
  var $wrap = $('#ss-login-wrap');
  var $lc   = $('#ss-card-login');
  var $tc   = $('#ss-card-2fa');
  if (!$tc.length) return;
  var AJAX  = ss_pub.ajax_url;

  // Grįžti iš Google prisijungimo su 2FA pending
  var pending = $wrap.data('2fa-pending') || '';
  if (pending) {
    SS._pending2faToken = pending;
    $lc.hide(); $tc.show();
    setTimeout(function(){ $('#ss-2fa-code').focus(); }, 200);
  }

  // Grįžimo mygtukas
  $('#ss-2fa-back').on('click', function(){
    $tc.fadeOut(160, function(){ $lc.fadeIn(160); $('#ss-user').focus(); });
    SS._pending2faToken = null;
  });

  // 3.2: Atsarginių kodų režimas
  var _backupMode = false;
  $('#ss-2fa-use-backup').on('click', function() {
    _backupMode = !_backupMode;
    if (_backupMode) {
      $('#ss-2fa-sub').text('Įveskite 8 simbolių atsarginį kodą');
      $('#ss-2fa-lbl').text('Atsarginis kodas');
      $('#ss-2fa-code')
        .attr({ inputmode:'text', maxlength:'8', placeholder:'AABB1122', autocomplete:'off' })
        .val('').focus();
      $(this).text('⬅ Naudoti Authenticator kodą');
    } else {
      $('#ss-2fa-sub').text('Įveskite 6 skaitmenų kodą iš Authenticator programėlės');
      $('#ss-2fa-lbl').text('2FA kodas');
      $('#ss-2fa-code')
        .attr({ inputmode:'numeric', maxlength:'6', placeholder:'000000', autocomplete:'one-time-code' })
        .val('').focus();
      $(this).text('🔑 Atsarginis kodas');
    }
    $('#ss-2fa-err').hide();
  });

  function submit2fa(){
    var raw  = $('#ss-2fa-code').val();
    var code = _backupMode
      ? raw.replace(/[^A-Fa-f0-9]/g, '').toUpperCase()
      : raw.replace(/[^0-9]/g, '');
    var $err  = $('#ss-2fa-err');
    var $btn  = $('#ss-2fa-submit');
    var $lbl  = $btn.find('.ss-btn-lbl');
    var $spin = $btn.find('.ss-btn-spin');
    if (_backupMode) {
      if (code.length !== 8) { $err.text('Atsarginis kodas turi būti 8 simboliai.').show(); return; }
    } else {
      if (code.length !== 6) { $err.text('Kodas turi būti 6 skaitmenys.').show(); return; }
    }
    if (!SS._pending2faToken) { $err.text('Trūksta žetono. Prisijunkite iš naujo.').show(); return; }
    $err.hide(); $lbl.hide(); $spin.show(); $btn.prop('disabled', true);
    $.post(AJAX, { action: 'ss_verify_2fa', token: SS._pending2faToken, code: code },
      function(res){
        $lbl.show(); $spin.hide(); $btn.prop('disabled', false);
        if (res.success) {
          if (res.data && res.data.status === 'must_change_pw') {
            SS.showNewPw(res.data.token, $('#ss-card-2fa'));
            return;
          }
          $lbl.text('Prisijungiama...');
          window.location.href = res.data.redirect;
        } else {
          var code2 = res.data && res.data.code;
          if (res.data && res.data.token) SS._pending2faToken = res.data.token;
          if (code2 === 'rate_limited' && res.data.retry_after > 0) {
            // 5 min. blokas — ta pati animacija kaip prisijungimo bloke.
            SS.applyTwoFaLockout(res.data.retry_after, (res.data && res.data.message) || 'Per daug neteisingų kodų. Prisijungimas laikinai užblokuotas.');
            return;
          }
          if (code2 === 'device_blocked') {
            // Blokuotas įrenginys — slėpti formą, rodyti aiškų pranešimą
            $('#ss-2fa-submit, #ss-2fa-use-backup, #ss-2fa-back').hide();
            $err.html('&#128683; <strong>Šis įrenginys yra užblokuotas.</strong><br>Atblokuokite jį prisijungę prie paskyros iš kito įrenginio.').css({'white-space':'normal','line-height':'1.5'}).show();
          } else {
            $err.text((res.data && res.data.message) || 'Klaida.').show();
            $('#ss-2fa-code').val('').focus();
          }
        }
      }
    ).fail(function(){
      $lbl.show(); $spin.hide(); $btn.prop('disabled', false);
      $err.text('Ryšio klaida.').show();
    });
  }

  $('#ss-2fa-submit').on('click', submit2fa);
  $('#ss-2fa-code').on('input', function(){
    if (_backupMode) {
      this.value = this.value.replace(/[^A-Fa-f0-9]/gi, '').slice(0, 8).toUpperCase();
    } else {
      this.value = this.value.replace(/[^0-9]/g, '').slice(0, 6);
      if (this.value.length === 6) submit2fa();
    }
  });
};

$(document).ready(function(){SS.init();});
})(jQuery);
