<?php if (!defined('ABSPATH')) exit; ?>
<div id="ssimp-root" class="ssimp"
     data-ajax="<?php echo esc_url(admin_url('admin-ajax.php')); ?>"
     data-nonce="<?php echo esc_attr(wp_create_nonce('ss_front_nonce')); ?>">
  <div class="ssimp-hero">
    <div>
      <h2>Individualus ugdymo planas</h2>
      <p class="ssimp-sub">Įkelkite užpildytą IUP <code>.xlsx</code> failą — sistema perskaitys pasirinkimus ir susies juos su jūsų paskyra.</p>
    </div>
  </div>
  <div id="ssimp-body" class="ssimp-loading">Kraunama…</div>
  <div id="ssimp-msg" class="ssimp-msg"></div>
</div>

<style>
.ssimp{max-width:880px;margin:0 auto;font-family:'Google Sans','Segoe UI',sans-serif;color:var(--dk)}
.ssimp-hero{display:flex;align-items:center;gap:16px;margin-bottom:18px;/*padding:20px 22px;*/
  /*background:linear-gradient(135deg,#f3f9f3,#eef6ef);border:1px solid #dcebdd*/}
.ssimp-hero-ic{flex:none;width:54px;height:54px;background:var(--g);color:var(--wh);
  display:flex;align-items:center;justify-content:center;box-shadow:0 6px 16px rgba(79,137,82,.28)}
.ssimp-hero h2{margin:0;font-size:22px;font-weight:800;letter-spacing:-.01em}
.ssimp-sub{margin:4px 0 0;color:#5B6B5C;font-size:13.5px;line-height:1.5}
.ssimp-sub code,.ssimp-drop code{background:#e4efe4;padding:1px 6px;font-size:12px;color:#2f5d33}
.ssimp-card{background:var(--wh);border:1px solid #E6ECE6;padding:20px 22px;margin-bottom:14px;
  box-shadow:0 1px 3px rgba(1,42,54,.04)}
.ssimp-card h3{margin:0 0 14px;font-size:13px;font-weight:800;color:var(--g);text-transform:uppercase;letter-spacing:.5px;
  display:flex;align-items:center;gap:8px}
.ssimp-card h3 svg{width:16px;height:16px}
/* Priėmimo lango juosta */
.ssimp-win{display:flex;align-items:center;gap:11px;font-size:14px;padding:13px 16px;margin-bottom:16px;font-weight:600}
.ssimp-win svg{flex:none;width:20px;height:20px}
.ssimp-win.open{background:#E7F5EA;color:#22683a}
.ssimp-win.before{background:#FFF6DD;color:#876412}
.ssimp-win.after{background:#FCEAEA;color:#9a282c}
.ssimp-win strong{font-weight:800}
/* Įkėlimo zona */
.ssimp-drop{border:2px dashed #c4ddc6;padding:28px 22px;text-align:center;background:#F7FBF7;
  transition:.15s;cursor:pointer}
.ssimp-drop:hover{border-color:#9fcaa2;background:#F1F8F2}
.ssimp-drop.drag{border-color:var(--g);background:#E9F5EB}
.ssimp-drop p{margin:5px 0;color:#5B6B5C;font-size:13.5px}
.ssimp-drop a{color:var(--g);font-weight:700;text-decoration:none}
.ssimp-drop a:hover{text-decoration:underline}
.ssimp-drop-ic{width:40px;height:40px;background:#E7F0E7;color:var(--g);
  display:flex;align-items:center;justify-content:center;margin:0 auto 12px}
.ssimp-file{font-weight:700;color:var(--dk);margin-top:8px!important}
.ssimp-actions{display:flex;align-items:center;gap:14px;margin-top:14px;flex-wrap:wrap}
.ssimp-btn{display:inline-flex;align-items:center;gap:8px;background:var(--g);color:var(--wh);border:0;
  padding:12px 22px;font-size:14px;font-weight:700;cursor:pointer;transition:.15s}
.ssimp-btn svg{width:17px;height:17px}
.ssimp-btn:hover{background:#3C6B3F}
.ssimp-btn:disabled{opacity:.45;cursor:default}
.ssimp-muted{color:#7a8a7b;font-size:12px;margin:0}
/* Esamas planas */
.ssimp-filerow{display:flex;align-items:center;gap:10px;background:#F7FBF7;border:1px solid #E6ECE6;
  padding:11px 14px;margin:0 0 14px;font-size:13px}
.ssimp-filerow svg{flex:none;width:20px;height:20px;color:var(--g)}
.ssimp-filerow .meta{color:#7a8a7b}
.ssimp-tbl{width:100%;border-collapse:collapse;font-size:13px}
.ssimp-tbl th,.ssimp-tbl td{text-align:left;padding:9px 10px;border-bottom:1px solid #EEF3EE}
.ssimp-tbl th{color:var(--gl);font-weight:700;font-size:11px;text-transform:uppercase;letter-spacing:.3px}
.ssimp-tbl tr:last-child td{border-bottom:0}
.ssimp-tbl td.n{text-align:center;width:52px}
.ssimp-lvl{display:inline-block;min-width:20px;text-align:center;font-weight:700;font-size:11px;padding:2px 7px;background:#eef3ee;color:#4f7a52}
.ssimp-tot{display:flex;gap:22px;font-size:13px;color:#5B6B5C;margin-top:14px;padding-top:12px;border-top:1px solid #EEF3EE;flex-wrap:wrap}
.ssimp-tot strong{color:var(--dk);font-size:17px;display:block}
.ssimp-msg{margin-top:12px;font-size:13.5px;font-weight:600}
.ssimp-loading{color:var(--gl);padding:24px;text-align:center}
@media(max-width:560px){.ssimp-hero{flex-direction:column;text-align:center}}
</style>

<script>
(function(){
  var root=document.getElementById('ssimp-root'), AJAX=root.dataset.ajax, NONCE=root.dataset.nonce;
  var fileSel=null;

  var ICON={
    check:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>',
    clock:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>',
    lock:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>',
    upload:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>',
    file:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>',
    send:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m22 2-7 20-4-9-9-4Z"/><path d="M22 2 11 13"/></svg>',
    edit:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>',
    list:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><circle cx="3.5" cy="6" r="1"/><circle cx="3.5" cy="12" r="1"/><circle cx="3.5" cy="18" r="1"/></svg>'
  };

  function esc(s){var d=document.createElement('div');d.textContent=(s==null?'':s);return d.innerHTML;}
  function post(action,data){
    var fd;
    if(data instanceof FormData){ fd=data; }
    else { data=data||{}; fd=new FormData(); for(var k in data) fd.append(k,data[k]); }
    fd.append('action',action); fd.append('nonce',NONCE);
    return fetch(AJAX,{method:'POST',body:fd,credentials:'same-origin'}).then(function(r){return r.json();});
  }
  function fmt(dt){ return dt ? esc(dt.replace('T',' ').slice(0,16)) : ''; }

  function load(){
    post('ss_imp_up_status').then(function(res){
      if(!res.success){ document.getElementById('ssimp-body').innerHTML='<div class="ssimp-card">'+esc((res.data&&res.data.message)||'Klaida.')+'</div>'; return; }
      render(res.data);
    });
  }

  function render(st){
    var h='';

    // Priėmimo lango būsena
    if(st.window==='open'){
      h+='<div class="ssimp-win open">'+ICON.check+'<span>Plano priėmimas atviras'+(st.close_at?' — iki <strong>'+fmt(st.close_at)+'</strong>':'')+'</span></div>';
    } else if(st.window==='before'){
      h+='<div class="ssimp-win before">'+ICON.clock+'<span>Priėmimas dar neprasidėjo'+(st.open_at?' — prasidės <strong>'+fmt(st.open_at)+'</strong>':'')+'</span></div>';
    } else {
      h+='<div class="ssimp-win after">'+ICON.lock+'<span>Priėmimas pasibaigė'+(st.close_at?' (<strong>'+fmt(st.close_at)+'</strong>)':'')+'</span></div>';
    }

    // Įkėlimo zona (tik kai galima pateikti)
    if(st.can_submit){
      var editing = st.mine && st.mine.has;
      h+='<div class="ssimp-card"><h3>'+(editing?ICON.edit+'Koreguoti planą':ICON.upload+'Pateikti planą')+'</h3>';
      h+='<div class="ssimp-drop" id="ssimp-drop">'
        +'<input type="file" id="ssimp-file" accept=".xlsx" style="display:none">'
        +'<div class="ssimp-drop-ic">'+ICON.upload+'</div>'
        +'<p><strong>Vilkite užpildytą <code>.xlsx</code> failą čia</strong></p>'
        +'<p>arba <a href="#" id="ssimp-pick">pasirinkite iš kompiuterio</a></p>'
        +'<p id="ssimp-fname" class="ssimp-file"></p></div>';
      h+='<div class="ssimp-actions">'
        +'<button class="ssimp-btn" id="ssimp-send" disabled>'+ICON.send+'<span>Pateikti planą</span></button>'
        +'<span class="ssimp-muted">Naujas įkėlimas pakeičia ankstesnį planą.</span></div>';
      h+='</div>';
    }

    // Esamas planas
    if(st.mine && st.mine.has){
      h+='<div class="ssimp-card"><h3>'+ICON.list+'Jūsų pateiktas planas</h3>';
      h+='<div class="ssimp-filerow">'+ICON.file+'<div><strong>'+esc(st.mine.file_name||'—')+'</strong>'
        +'<div class="meta">Atnaujinta '+fmt(st.mine.updated)+'</div></div></div>';
      if(st.mine.resolved && st.mine.resolved.length){
        h+='<table class="ssimp-tbl"><thead><tr><th>Dalykas</th><th>Kursas</th><th class="n">III kl.</th><th class="n">IV kl.</th></tr></thead><tbody>';
        st.mine.resolved.forEach(function(it){
          h+='<tr><td>'+esc(it.name)+'</td><td>'+(it.level?'<span class="ssimp-lvl">'+esc(it.level)+'</span>':'—')+'</td>'
            +'<td class="n">'+(it.h3||'')+'</td><td class="n">'+(it.h4||'')+'</td></tr>';
        });
        h+='</tbody></table>';
        h+='<div class="ssimp-tot"><span>Dalykų<strong>'+st.mine.resolved.length+'</strong></span>'
          +'<span>Val. III kl.<strong>'+st.mine.total_h3+'</strong></span>'
          +'<span>Val. IV kl.<strong>'+st.mine.total_h4+'</strong></span></div>';
      }
      h+='</div>';
    } else if(st.window!=='open'){
      h+='<div class="ssimp-card"><p style="margin:0;color:#7a8a7b">Planas dar nepateiktas.</p></div>';
    }

    document.getElementById('ssimp-body').className='';
    document.getElementById('ssimp-body').innerHTML=h;
    bind(st);
  }

  function bind(st){
    fileSel=null;
    var drop=document.getElementById('ssimp-drop');
    if(!drop) return;
    var input=document.getElementById('ssimp-file');
    var send=document.getElementById('ssimp-send');
    function choose(f){
      if(!f) return;
      if(!/\.xlsx$/i.test(f.name)){ msg('Netinkamas formatas — reikia .xlsx bylos.', true); return; }
      fileSel=f; document.getElementById('ssimp-fname').textContent='Pasirinkta: '+f.name;
      send.disabled=false; msg('',false);
    }
    document.getElementById('ssimp-pick').addEventListener('click',function(e){e.preventDefault();input.click();});
    drop.addEventListener('click',function(e){ if(e.target===drop||e.target.closest('.ssimp-drop-ic')) input.click(); });
    input.addEventListener('change',function(){ choose(input.files[0]); });
    ['dragenter','dragover'].forEach(function(ev){ drop.addEventListener(ev,function(e){e.preventDefault();drop.classList.add('drag');}); });
    ['dragleave','drop'].forEach(function(ev){ drop.addEventListener(ev,function(e){e.preventDefault();drop.classList.remove('drag');}); });
    drop.addEventListener('drop',function(e){ choose(e.dataTransfer.files[0]); });

    send.addEventListener('click',function(){
      if(!fileSel){ msg('Pasirinkite failą.', true); return; }
      if(st.mine && st.mine.has && !confirm('Pateikti šį failą? Jis pakeis ankstesnį jūsų planą.')) return;
      var fd=new FormData(); fd.append('file', fileSel);
      send.disabled=true; send.querySelector('span').textContent='Siunčiama…';
      post('ss_imp_up_submit', fd).then(function(r){
        if(r.success){
          var warn=(r.data.warnings&&r.data.warnings.length)?(' Pastaba: '+r.data.warnings.join('; ')):'';
          msg('Planas pateiktas ('+r.data.mine.resolved.length+' dalykų).'+warn, false);
          render(r.data);
        } else {
          send.disabled=false; send.querySelector('span').textContent='Pateikti planą';
          msg((r.data&&r.data.message)||'Klaida.', true);
        }
      }).catch(function(){ send.disabled=false; send.querySelector('span').textContent='Pateikti planą'; msg('Ryšio klaida.', true); });
    });
  }

  function msg(t,err){ var m=document.getElementById('ssimp-msg'); m.style.color=err?'#D0454A':'#22683a'; m.textContent=t; }
  load();
})();
</script>
