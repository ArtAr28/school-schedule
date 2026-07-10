/* Ąžuolyno — socialinis modulis: profilis (draugai) + žinutės (pokalbiai). */
(function ($) {
  'use strict';
  if (typeof ss_social === 'undefined') return;

  var ME = parseInt(ss_social.me, 10) || 0;

  // WP Dashicons ikona (grupės avataras ir kt.) — atitinka PHP SS_Social::icon().
  function dashicon(name, size) {
    var s = size || 20;
    return '<span class="dashicons dashicons-' + name + ' ss-ic" aria-hidden="true" ' +
      'style="font-size:' + s + 'px;width:' + s + 'px;height:' + s + 'px;"></span>';
  }

  function post(action, data, cb, fail) {
    data = data || {};
    data.action = 'ss_' + action;
    data.nonce = ss_social.nonce;
    $.post(ss_social.ajax_url, data, function (res) { cb && cb(res); })
      .fail(function () { fail ? fail() : toast('Serverio klaida', true); });
  }
  function toast(msg, err) {
    var $t = $('<div class="ss-social-toast' + (err ? ' err' : '') + '"></div>').text(msg);
    $('body').append($t);
    setTimeout(function () { $t.addClass('show'); }, 10);
    setTimeout(function () { $t.removeClass('show'); setTimeout(function () { $t.remove(); }, 300); }, 2600);
  }
  function el(tag, cls, text) {
    var e = document.createElement(tag);
    if (cls) e.className = cls;
    if (text != null) e.textContent = text;
    return e;
  }
  function esc(s) { return $('<div>').text(s == null ? '' : s).html(); }
  function profileUrl(uid) { var u = ss_social.profile_url || ''; return u + (u.indexOf('?') > -1 ? '&' : '?') + 'u=' + uid; }

  /* ══════════════════════════ PROFILIS / DRAUGAI ══════════════════════════ */
  var FRIEND_LABEL = {
    none:        'Pridėti į draugus',
    pending_out: 'Kvietimas išsiųstas',
    pending_in:  'Priimti kvietimą',
    friends:     'Draugai ✓'
  };
  function friendBtnRender($btn, rel) {
    $btn.attr('data-rel', rel).text(FRIEND_LABEL[rel] || 'Pridėti į draugus');
    $btn.toggleClass('is-friends', rel === 'friends')
        .toggleClass('is-pending', rel === 'pending_out')
        .toggleClass('is-accept', rel === 'pending_in');
  }

  function initProfile() {
    var $actions = $('#ss-prof-actions');
    var uid = parseInt($actions.data('uid'), 10) || 0;

    // ── Savo profilio redagavimas: avataras (pieštukas → įkėlimas) ──
    var $avInput = $('#ss-prof-av-input');
    $('#ss-edit-avatar').on('click', function () { $avInput.click(); });
    $avInput.on('change', function () {
      var f = this.files && this.files[0]; if (!f) return;
      var fd = new FormData();
      fd.append('action', 'ss_settings_upload_avatar');
      fd.append('nonce', ss_social.nonce);
      fd.append('avatar', f);
      toast('Įkeliama…');
      $.ajax({ url: ss_social.ajax_url, method: 'POST', data: fd, processData: false, contentType: false })
        .done(function (res) {
          if (res.success) { $('#ss-prof-av-img').attr('src', res.data.url + (res.data.url.indexOf('?') > -1 ? '&' : '?') + 'v=' + Date.now()); toast('Nuotrauka atnaujinta'); }
          else toast((res.data && res.data.message) || 'Klaida', true);
        }).fail(function () { toast('Serverio klaida', true); });
      this.value = '';
    });

    // ── Fonas (pieštukas → presetų iškylantis langas) ──
    var $covPop = $('#ss-cover-pop');
    $('#ss-edit-cover').on('click', function (e) { e.stopPropagation(); $covPop.toggleClass('open'); });
    $(document).on('click', function () { $covPop.removeClass('open'); });
    $covPop.on('click', function (e) { e.stopPropagation(); });
    $covPop.on('click', '.ss-cover-swatch', function () {
      var b = this;
      post('save_profile_cover', { cover: b.getAttribute('data-cover') }, function (res) {
        if (res.success) {
          $('#ss-prof-cover').css('background', res.data.css);
          $covPop.find('.ss-cover-swatch').removeClass('active'); $(b).addClass('active');
          $covPop.removeClass('open');
        } else toast((res.data && res.data.message) || 'Klaida', true);
      });
    });

    var $fb = $('.ss-prof-friend');
    if ($fb.length) friendBtnRender($fb, $actions.data('rel'));

    $(document).on('click', '.ss-prof-friend', function () {
      var $b = $(this), rel = $b.attr('data-rel');
      var map = {
        none:        ['friend_request', 'pending_out'],
        pending_in:  ['friend_accept',  'friends'],
        pending_out: ['friend_remove',  'none'],
        friends:     ['friend_remove',  'none']
      };
      if (rel === 'friends' && !confirm('Pašalinti iš draugų?')) return;
      var pair = map[rel]; if (!pair) return;
      $b.prop('disabled', true);
      post(pair[0], { user_id: uid }, function (res) {
        $b.prop('disabled', false);
        if (res.success) {
          var newRel = res.data.status || pair[1];
          friendBtnRender($b, newRel);
          $actions.attr('data-rel', newRel);
          if (newRel === 'friends' || rel === 'friends') location.reload();
        } else { toast((res.data && res.data.message) || 'Klaida', true); }
      });
    });

    // Rašyti žinutę → sukuriam/atveriam asmeninį pokalbį
    $(document).on('click', '.ss-prof-msg', function () {
      var t = parseInt($(this).data('uid'), 10);
      $(this).prop('disabled', true);
      post('chat_create', { kind: 'direct', user_id: t }, function (res) {
        if (res.success) location.href = ss_social.messages_url + (ss_social.messages_url.indexOf('?') > -1 ? '&' : '?') + 'c=' + res.data.chat_id;
        else toast((res.data && res.data.message) || 'Klaida', true);
      });
    });

    // Draugystės kvietimai (savo profilyje)
    var $reqBox = $('#ss-friend-requests');
    if ($reqBox.length) {
      post('friend_requests', {}, function (res) {
        if (!res.success || !res.data.requests.length) return;
        $reqBox.show().empty();
        $reqBox.append(el('div', 'ss-friend-reqs-title', 'Draugystės kvietimai (' + res.data.requests.length + ')'));
        res.data.requests.forEach(function (u) {
          var row = el('div', 'ss-freq-row');
          var img = el('img', 'ss-freq-av'); img.src = u.avatar; row.appendChild(img);
          row.appendChild(el('span', 'ss-freq-name', u.name));
          var acc = el('button', 'ss-prof-mini-btn', 'Priimti');
          var dec = el('button', 'ss-prof-mini-btn ss-mini-ghost', 'Atmesti');
          acc.onclick = function () { post('friend_accept', { user_id: u.id }, function (r) { if (r.success) location.reload(); }); };
          dec.onclick = function () { post('friend_decline', { user_id: u.id }, function () { $(row).remove(); }); };
          row.appendChild(acc); row.appendChild(dec);
          $reqBox.append(row);
        });
      });
    }

    // Draugų paieškos modalas
    var $modal = $('#ss-find-friends-modal');
    $('#ss-open-find-friends').on('click', function () { $modal.addClass('open'); $('#ss-ff-search').focus(); });
    $('#ss-ff-close').on('click', function () { $modal.removeClass('open'); });
    $modal.on('click', function (e) { if (e.target === this) $modal.removeClass('open'); });
    var searchT;
    $('#ss-ff-search').on('input', function () {
      var q = $(this).val();
      clearTimeout(searchT);
      searchT = setTimeout(function () { doSearch(q); }, 250);
    });
    function doSearch(q) {
      var $r = $('#ss-ff-results');
      if (q.trim().length < 2) { $r.empty(); return; }
      post('user_search', { q: q }, function (res) {
        $r.empty();
        if (!res.success || !res.data.users.length) { $r.append(el('p', 'ss-prof-empty', 'Nieko nerasta.')); return; }
        res.data.users.forEach(function (u) {
          var row = el('div', 'ss-ff-row');
          var a = document.createElement('a'); a.className = 'ss-ff-user'; a.href = ss_social.profile_url + (ss_social.profile_url.indexOf('?') > -1 ? '&' : '?') + 'u=' + u.id;
          var img = el('img', 'ss-freq-av'); img.src = u.avatar; a.appendChild(img);
          var info = el('div', 'ss-ff-info');
          info.appendChild(el('span', 'ss-freq-name', u.name));
          info.appendChild(el('span', 'ss-friend-role', u.role));
          a.appendChild(info); row.appendChild(a);
          var btn = el('button', 'ss-prof-mini-btn');
          friendActionBtn(btn, u);
          row.appendChild(btn);
          $r.append(row);
        });
      });
    }
    function friendActionBtn(btn, u) {
      var rel = u.rel;
      if (rel === 'friends') { btn.textContent = 'Draugai ✓'; btn.disabled = true; btn.classList.add('is-friends'); return; }
      if (rel === 'pending_out') { btn.textContent = 'Išsiųsta'; btn.disabled = true; return; }
      btn.textContent = (rel === 'pending_in') ? 'Priimti' : 'Pridėti';
      btn.onclick = function () {
        var act = (rel === 'pending_in') ? 'friend_accept' : 'friend_request';
        btn.disabled = true;
        post(act, { user_id: u.id }, function (r) {
          if (r.success) { btn.textContent = (r.data.status === 'friends') ? 'Draugai ✓' : 'Išsiųsta'; btn.classList.toggle('is-friends', r.data.status === 'friends'); }
          else { btn.disabled = false; toast((r.data && r.data.message) || 'Klaida', true); }
        });
      };
    }
  }

  /* ══════════════════════════ ŽINUTĖS / POKALBIAI ══════════════════════════ */
  var SECTIONS = [
    ['friend',     'Draugai'],
    ['classmate',  'Klasiokai'],
    ['my_student', 'Mano mokiniai'],
    ['employee',   'Darbuotojai'],
    ['teacher',    'Mokytojai'],
    ['student',    'Mokiniai'],
    ['admin',      'Administratoriai'],
    ['other',      'Kiti']
  ];

  function initMessages() {
    var $app = $('#ss-msg-app');
    if ($app.data('inited')) return;      // apsauga nuo dvigubo inicijavimo (dvigubų klausytojų)
    $app.data('inited', true);
    var cur = { id: 0, lastId: 0, type: 'direct', isAdmin: false, members: [], reads: [], rows: {}, poll: null, editing: 0, lastUnread: null, sending: false };
    var picker = { mode: 'new', chatId: 0, selected: {}, contacts: [], search: '' };

    /* ─── Naršyklės pranešimai (jei įrenginys palaiko) ─── */
    function ensureNotifyPermission() {
      if (!('Notification' in window)) return;
      if (Notification.permission === 'default') { try { Notification.requestPermission(); } catch (e) {} }
    }
    function maybeNotify(total) {
      if (cur.lastUnread == null) { cur.lastUnread = total; return; } // pirmas įkėlimas — netriginam
      if (total > cur.lastUnread && document.hidden && ('Notification' in window) && Notification.permission === 'granted') {
        try {
          var n = new Notification('Naujos žinutės', { body: 'Turite ' + total + ' neperskaitytų žinučių.' });
          n.onclick = function () { window.focus(); n.close(); };
        } catch (e) {}
      }
      cur.lastUnread = total;
    }

    /* ─── Meniu ženkliukas (neperskaitytų suma) ─── */
    function updateNavBadge(total) {
      var $msgs = $('.ss-hnav-msgs');
      var $b = $msgs.find('.ss-hnav-badge');
      if (total > 0) {
        var txt = total > 99 ? '99+' : String(total);
        if ($b.length) $b.text(txt); else $msgs.append('<span class="ss-hnav-badge">' + txt + '</span>');
      } else { $b.remove(); }
    }

    /* ─── Pokalbių sąrašas ─── */
    function loadList(cb) {
      post('chat_list', {}, function (res) {
        var $list = $('#ss-chat-list');
        $list.find('.ss-chat-item').remove();
        if (!res.success) return;
        var chats = res.data.chats || [];
        var totalUnread = chats.reduce(function (s, c) { return s + (c.unread || 0); }, 0);
        updateNavBadge(totalUnread);
        maybeNotify(totalUnread);
        $('#ss-chat-list-empty').toggle(chats.length === 0);
        chats.forEach(function (c) {
          var it = el('div', 'ss-chat-item' + (c.id === cur.id ? ' active' : ''));
          it.setAttribute('data-id', c.id);
          var av = el('div', 'ss-chat-item-av');
          if ((c.type === 'group' || c.type === 'channel') && c.avatar_img) { var gm = document.createElement('img'); gm.src = c.avatar_img; av.appendChild(gm); }
          else if (c.type === 'group' || c.type === 'channel') { av.classList.add('grp'); av.innerHTML = dashicon(c.type === 'channel' ? 'megaphone' : 'groups', 22); }
          else { var im = document.createElement('img'); im.src = c.avatar; av.appendChild(im); }
          it.appendChild(av);
          var mid = el('div', 'ss-chat-item-mid');
          mid.appendChild(el('div', 'ss-chat-item-title', c.title));
          mid.appendChild(el('div', 'ss-chat-item-last', c.last || (c.type === 'group' ? c.members + ' nariai' : '')));
          it.appendChild(mid);
          if (c.unread > 0) it.appendChild(el('span', 'ss-chat-unread', c.unread > 99 ? '99+' : c.unread));
          it.onclick = function () { openChat(c.id); };
          $list.append(it);
        });
        cb && cb();
      });
    }

    function stopPoll() { if (cur.poll) { clearInterval(cur.poll); cur.poll = null; } }

    /* ─── Pokalbio atvėrimas ─── */
    function openChat(id) {
      stopPoll();
      closePicker();
      ensureNotifyPermission();
      cur.id = id; cur.lastId = 0; cur.rows = {}; cur.editing = 0;
      $('#ss-chat-list .ss-chat-item').removeClass('active').filter('[data-id="' + id + '"]').addClass('active');
      post('chat_open', { chat_id: id }, function (res) {
        if (!res.success) { toast((res.data && res.data.message) || 'Klaida', true); return; }
        var c = res.data.chat;
        cur.type = c.type; cur.isAdmin = !!c.is_admin; cur.members = res.data.members || []; cur.reads = res.data.reads || [];
        $('#ss-chat-placeholder').hide();
        $('#ss-chat-conv').show();
        $app.addClass('conv-open');
        $('#ss-sticker-panel').hide(); $('#ss-attach-menu').hide(); $('#ss-react-pop').hide();
        cancelEdit();
        $('#ss-chat-title').text(c.title);
        cur.settings = c.settings || { bg: 'default', bg_css: '', avatar: '' };
        $app.removeClass('settings-open');
        var grouplike = (c.type === 'group' || c.type === 'channel');
        var $av = $('#ss-chat-av').empty().removeClass('online recent grp');
        if (grouplike && cur.settings.avatar) { var gim = document.createElement('img'); gim.src = cur.settings.avatar; $av[0].appendChild(gim); }
        else if (grouplike) { $av.addClass('grp').html(dashicon(c.type === 'channel' ? 'megaphone' : 'groups', 22)); }
        else { var aim = document.createElement('img'); aim.src = c.avatar || ''; $av[0].appendChild(aim); }
        applyChatBg(cur.settings.bg_css);
        cur.memberCount = c.member_count || cur.members.length;
        cur.subCount = c.sub_count || 0;
        if (c.type === 'channel') $('#ss-chat-sub').text('Kanalas · ' + cur.memberCount + ' nariai');
        else if (c.type === 'group') $('#ss-chat-sub').text(cur.memberCount + ' nariai');
        else applyPresence(c.presence);
        $('#ss-chat-manage').toggle(grouplike);
        var canPost = c.can_post !== false;
        $('#ss-chat-composer').toggle(canPost);
        $('#ss-chat-readonly').toggle(!canPost);
        $('#ss-chat-typing').hide().text('');
        var $m = $('#ss-chat-messages').empty();
        cur.initialLoading = true; cur.lastAuthor = null;
        (res.data.messages || []).forEach(function (msg) { appendMsg($m[0], msg); });
        cur.initialLoading = false;
        updateReceipts();
        scrollBottom();
        if (res.data.messages.length) cur.lastId = res.data.messages[res.data.messages.length - 1].id;
        cur.poll = setInterval(pollNew, 4000);
        $('#ss-chat-list .ss-chat-item[data-id="' + id + '"] .ss-chat-unread').remove();
        var rem = 0; $('#ss-chat-list .ss-chat-unread').each(function () { var n = parseInt(this.textContent, 10); rem += isNaN(n) ? 0 : n; });
        updateNavBadge(rem);
      });
    }

    /* ─── Žinutės piešimas ─── */
    function appendMsg(container, msg) {
      if (cur.rows[msg.id]) return; // apsauga nuo dublikatų (siuntimo atsakas + apklausa gali sutapti)
      var anim = cur.initialLoading ? '' : ' ss-anim';
      if (msg.type === 'system') {
        cur.lastAuthor = null; // po sisteminės žinutės vėl rodom avatarą/vardą
        var sys = el('div', 'ss-msg-sys' + anim); sys.setAttribute('data-id', msg.id);
        sys.appendChild(el('span', 'ss-msg-sys-t', msg.body));
        container.appendChild(sys);
        cur.rows[msg.id] = { row: sys, sys: true };
        return;
      }
      var mine = msg.user_id === ME;
      var grouplike = (cur.type === 'group' || cur.type === 'channel');
      var cont = (cur.lastAuthor === msg.user_id); // to paties autoriaus tęsinys
      cur.lastAuthor = msg.user_id;
      var wrap = el('div', 'ss-msg-row' + (mine ? ' mine' : '') + (cont ? ' ss-cont' : '') + (grouplike && !mine ? ' ss-grp' : '') + anim);
      wrap.setAttribute('data-id', msg.id);
      if (!mine && grouplike && !cont) {
        var av = document.createElement('img'); av.className = 'ss-msg-bub-av'; av.src = msg.avatar; av.title = msg.name;
        av.onclick = function () { location.href = profileUrl(msg.user_id); };
        wrap.appendChild(av);
      }
      var bub = el('div', 'ss-msg-bub' + (msg.type === 'sticker' ? ' sticker' : ''));
      if (!mine && grouplike && !cont) {
        var nm = el('div', 'ss-msg-bub-name', msg.name);
        nm.onclick = function () { location.href = profileUrl(msg.user_id); };
        bub.appendChild(nm);
      }
      var body = el('div', 'ss-msg-bub-body');
      renderBody(body, msg);
      bub.appendChild(body);
      var meta = el('div', 'ss-msg-bub-meta');
      var time = el('span', 'ss-msg-bub-time', msg.time + (msg.edited ? ' · redaguota' : ''));
      meta.appendChild(time);
      var receipt = null;
      if (mine) { receipt = el('span', 'ss-msg-receipt'); meta.appendChild(receipt); }
      bub.appendChild(meta);
      var reBar = el('div', 'ss-msg-reactions');
      renderReactions(reBar, msg);
      bub.appendChild(reBar);
      wrap.appendChild(bub);
      var real = (typeof msg.id === 'number');
      if (real) {
        var reactBtn = el('button', 'ss-msg-react-btn'); reactBtn.title = 'Reaguoti'; reactBtn.innerHTML = dashicon('smiley', 14);
        reactBtn.onclick = function (e) { e.stopPropagation(); openReactPop(reactBtn, msg.id); };
        wrap.appendChild(reactBtn);
      }
      if (mine && real && (msg.type === 'text' || !msg.type)) {
        var edit = el('button', 'ss-msg-edit-btn'); edit.title = 'Redaguoti'; edit.innerHTML = dashicon('edit', 14);
        edit.onclick = function () { startEdit(msg.id); };
        wrap.appendChild(edit);
      }
      container.appendChild(wrap);
      cur.rows[msg.id] = { row: wrap, body: body, time: time, receipt: receipt, reactions: reBar, msg: msg };
    }
    /* ─── Reakcijos ─── */
    function renderReactions(bar, msg) {
      bar.innerHTML = '';
      (msg.reactions || []).forEach(function (r) {
        var chip = el('button', 'ss-react-chip' + (r.mine ? ' mine' : ''));
        chip.appendChild(el('span', 'ss-react-emo', r.emoji));
        chip.appendChild(el('span', 'ss-react-cnt', r.count));
        chip.onclick = function () { react(msg.id, r.emoji); };
        bar.appendChild(chip);
      });
    }
    function react(mid, emoji) {
      post('chat_react', { message_id: mid, emoji: emoji }, function (res) {
        if (!res.success) { toast((res.data && res.data.message) || 'Klaida', true); return; }
        var r = cur.rows[mid];
        if (r) { r.msg.reactions = res.data.reactions; renderReactions(r.reactions, r.msg); }
      });
    }
    var reactMid = 0;
    function openReactPop(anchor, mid) {
      var $pop = $('#ss-react-pop');
      if (!$pop.data('built')) {
        (ss_social.reactions || []).forEach(function (em) {
          var b = el('button', 'ss-react-pick', em);
          b.onclick = function () { react(reactMid, em); $pop.hide(); };
          $pop[0].appendChild(b);
        });
        $pop.data('built', true);
      }
      reactMid = mid;
      $pop.css('display', 'flex');
      var rect = anchor.getBoundingClientRect();
      var pw = $pop.outerWidth(), ph = $pop.outerHeight();
      var left = Math.max(8, Math.min(rect.left, window.innerWidth - pw - 8));
      var top = rect.top - ph - 6; if (top < 8) top = rect.bottom + 6;
      $pop.css({ left: left + 'px', top: top + 'px' });
    }
    function scrollBottom() { var s = document.getElementById('ss-chat-messages'); if (s) s.scrollTop = s.scrollHeight; }
    function showTyping(names) {
      var $t = $('#ss-chat-typing');
      if (!names || !names.length) { $t.hide().text(''); return; }
      $t.text(names.length === 1 ? (names[0] + ' rašo…') : (names.length + ' rašo…')).css('display', 'block');
    }
    function applyChatBg(css) { var s = document.getElementById('ss-chat-messages'); if (s) s.style.background = css || ''; }
    function applyPresence(pr) {
      pr = pr || { status: 'hidden', label: '' };
      $('#ss-chat-sub').text(pr.label || '');
      var $av = $('#ss-chat-av').removeClass('online recent');
      if (pr.status === 'online') $av.addClass('online');
      else if (pr.status === 'recent') $av.addClass('recent');
    }

    /* ─── Turinio piešimas pagal tipą ─── */
    function renderBody(bodyEl, msg) {
      if (msg.type === 'sticker' && msg.sticker && msg.sticker.url) {
        var i = document.createElement('img'); i.className = 'ss-sticker-img'; i.src = msg.sticker.url; i.alt = 'lipdukas';
        bodyEl.appendChild(i); return;
      }
      if (msg.type === 'media' && msg.media) { renderMedia(bodyEl, msg); return; }
      if (msg.type === 'poll' && msg.poll) { renderPoll(bodyEl, msg); return; }
      bodyEl.textContent = msg.body;
    }
    function fmtSize(b) { b = b || 0; if (b < 1024) return b + ' B'; if (b < 1048576) return Math.round(b / 1024) + ' KB'; return (b / 1048576).toFixed(1) + ' MB'; }
    function renderMedia(bodyEl, msg) {
      var m = msg.media;
      if (m.expired) { bodyEl.appendChild(el('div', 'ss-media-expired', (m.name || 'Failas') + ' — nebegalioja (7 d.)')); return; }
      if (m.is_image) {
        var a = document.createElement('a'); a.href = m.url; a.target = '_blank'; a.rel = 'noopener'; a.className = 'ss-media-img-link';
        var img = document.createElement('img'); img.className = 'ss-media-img'; img.src = m.url; img.alt = m.name || '';
        a.appendChild(img); bodyEl.appendChild(a);
      } else {
        var f = document.createElement('a'); f.href = m.url; f.target = '_blank'; f.rel = 'noopener'; f.className = 'ss-media-file';
        f.innerHTML = dashicon('paperclip', 20);
        var info = el('div', 'ss-media-file-info');
        info.appendChild(el('span', 'ss-media-file-name', m.name || 'failas'));
        info.appendChild(el('span', 'ss-media-file-size', fmtSize(m.size)));
        f.appendChild(info); bodyEl.appendChild(f);
      }
    }
    function renderPoll(bodyEl, msg) {
      var p = msg.poll;
      var wrap = el('div', 'ss-poll');
      wrap.appendChild(el('div', 'ss-poll-q', p.q));
      var total = p.options.reduce(function (s, o) { return s + o.votes; }, 0);
      p.options.forEach(function (o, idx) {
        var voted = p.my.indexOf(idx) > -1;
        var opt = el('button', 'ss-poll-opt' + (voted ? ' voted' : ''));
        var pct = total > 0 ? Math.round(o.votes / total * 100) : 0;
        var bar = el('span', 'ss-poll-bar'); bar.style.width = pct + '%';
        opt.appendChild(bar);
        var lab = el('span', 'ss-poll-opt-t');
        lab.appendChild(el('span', 'ss-poll-opt-txt', o.text));
        lab.appendChild(el('span', 'ss-poll-opt-n', o.votes + (total ? ' · ' + pct + '%' : '')));
        opt.appendChild(lab);
        opt.onclick = function () { votePoll(msg.id, idx); };
        wrap.appendChild(opt);
      });
      wrap.appendChild(el('div', 'ss-poll-foot', (p.multi ? 'Keli pasirinkimai · ' : '') + p.voters + ' balsav.'));
      bodyEl.appendChild(wrap);
    }
    function votePoll(mid, idx) {
      post('chat_poll_vote', { message_id: mid, opt: idx }, function (res) {
        if (!res.success) { toast((res.data && res.data.message) || 'Klaida', true); return; }
        var r = cur.rows[mid]; if (r) { r.msg.poll = res.data.poll; r.body.innerHTML = ''; renderPoll(r.body, r.msg); }
      });
    }

    /* ─── „Varnelės“ (asmeniniai) / „Perskaitė“ (grupės) ─── */
    function updateReceipts() {
      Object.keys(cur.rows).forEach(function (k) {
        var r = cur.rows[k]; if (!r.receipt) return;
        var id = parseInt(k, 10);
        if (cur.type === 'direct') {
          var peer = cur.reads[0];
          var read = !!(peer && peer.read_id >= id);
          r.receipt.innerHTML = dashicon(read ? 'yes-alt' : 'yes', 14);
          r.receipt.className = 'ss-msg-receipt' + (read ? ' read' : '');
          r.receipt.onclick = null; r.receipt.style.cursor = 'default';
        } else {
          var cnt = cur.reads.filter(function (x) { return x.read_id >= id; }).length;
          // Kanale narių sąrašo klientui neturim (rodom tik adminus), todėl be „plačiau".
          if (cur.type === 'channel') {
            r.receipt.textContent = 'Perskaitė ' + cnt;
            r.receipt.className = 'ss-msg-receipt';
            r.receipt.style.cursor = 'default'; r.receipt.onclick = null;
          } else {
            r.receipt.textContent = 'Perskaitė ' + cnt + ' · plačiau';
            r.receipt.className = 'ss-msg-receipt group';
            r.receipt.style.cursor = 'pointer';
            r.receipt.onclick = function () { openReads(id); };
          }
        }
      });
    }

    function pollNew() {
      if (!cur.id) return;
      post('chat_poll', { chat_id: cur.id, since_id: cur.lastId }, function (res) {
        if (!res.success) return;
        if (res.data.reads) cur.reads = res.data.reads;
        if (cur.type === 'direct' && res.data.presence) applyPresence(res.data.presence);
        showTyping(res.data.typing || []);
        var msgs = res.data.messages || [];
        if (msgs.length) {
          var s = document.getElementById('ss-chat-messages');
          var near = s.scrollHeight - s.scrollTop - s.clientHeight < 120;
          msgs.forEach(function (m) { appendMsg(s, m); cur.lastId = m.id; });
          if (near) scrollBottom();
        }
        updateReceipts();
      });
    }

    /* ─── Siųsti / redaguoti ─── */
    function startEdit(id) {
      var r = cur.rows[id]; if (!r) return;
      cur.editing = id;
      var $inp = $('#ss-chat-input');
      $inp.val(r.msg.body).focus();
      $inp[0].style.height = 'auto'; $inp[0].style.height = Math.min(120, $inp[0].scrollHeight) + 'px';
      $('#ss-msg-editing').show();
    }
    function cancelEdit() {
      cur.editing = 0;
      $('#ss-msg-editing').hide();
      var $inp = $('#ss-chat-input'); $inp.val(''); $inp[0].style.height = 'auto';
    }
    $('#ss-edit-cancel').on('click', cancelEdit);

    $('#ss-chat-composer').on('submit', function (e) {
      e.preventDefault();
      var $inp = $('#ss-chat-input'), body = $inp.val().trim();
      if (!body || !cur.id) return;

      if (cur.editing) {
        var id = cur.editing;
        post('chat_edit', { message_id: id, body: body }, function (res) {
          if (!res.success) { toast((res.data && res.data.message) || 'Nepavyko', true); return; }
          var r = cur.rows[id];
          if (r) { r.msg = res.data.message; r.body.textContent = res.data.message.body; r.time.textContent = res.data.message.time + ' · redaguota'; }
          cancelEdit();
        });
        return;
      }

      $inp.val('').css('height', 'auto');
      sendMessageOptimistic(body);
    });
    // Optimistinis siuntimas: žinutė iškart pasirodo su „siunčiama" animacija,
    // o gavus atsakymą pakeičiama tikrąja (arba pažymima kaip nepavykusi).
    var tmpSeq = 0;
    function sendMessageOptimistic(body) {
      var s = document.getElementById('ss-chat-messages');
      var tempId = 'tmp_' + Date.now() + '_' + (++tmpSeq);
      appendMsg(s, { id: tempId, user_id: ME, type: 'text', body: body, time: '', edited: 0 });
      var r = cur.rows[tempId]; if (r) r.row.classList.add('ss-pending');
      scrollBottom();
      post('chat_send', { chat_id: cur.id, body: body }, function (res) {
        if (cur.rows[tempId]) { cur.rows[tempId].row.remove(); delete cur.rows[tempId]; }
        if (!res.success) { toast((res.data && res.data.message) || 'Nepavyko išsiųsti', true); return; }
        appendMsg(s, res.data.message); cur.lastId = res.data.message.id; updateReceipts(); scrollBottom();
      }, function () {
        if (cur.rows[tempId]) { cur.rows[tempId].row.classList.remove('ss-pending'); cur.rows[tempId].row.classList.add('ss-failed'); }
        toast('Nepavyko išsiųsti', true);
      });
    }
    var lastTyping = 0;
    $('#ss-chat-input').on('input', function () {
      this.style.height = 'auto'; this.style.height = Math.min(120, this.scrollHeight) + 'px';
      var now = Date.now();
      if (cur.id && this.value && now - lastTyping > 3000) { lastTyping = now; post('chat_typing', { chat_id: cur.id }, null, function () {}); }
    }).on('keydown', function (e) {
      if (e.key === 'Escape' && cur.editing) { cancelEdit(); return; }
      if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); $('#ss-chat-composer').submit(); }
    });
    $('#ss-chat-back').on('click', function () { $app.removeClass('conv-open'); });

    /* ─── Priedai („+"): failas / lipdukas / balsavimas ─── */
    var $attachMenu = $('#ss-attach-menu');
    function closeAttach() { $attachMenu.hide(); }
    $('#ss-chat-attach').on('click', function (e) {
      e.stopPropagation();
      $('#ss-att-poll').toggle(cur.type === 'group');
      $('#ss-sticker-panel').hide();
      $attachMenu.toggle();
    });
    $(document).on('click', function () { closeAttach(); $('#ss-react-pop').hide(); });
    $('#ss-chat-messages').on('scroll', function () { $('#ss-react-pop').hide(); });
    $attachMenu.on('click', function (e) { e.stopPropagation(); });
    $attachMenu.on('click', 'button[data-att]', function () {
      var t = $(this).data('att'); closeAttach();
      if (t === 'media') $('#ss-chat-file').click();
      else if (t === 'sticker') toggleStickers();
      else if (t === 'poll') openPoll();
    });

    // Failo įkėlimas
    $('#ss-chat-file').on('change', function () {
      var f = this.files && this.files[0]; if (!f || !cur.id) { this.value = ''; return; }
      if (f.size > 10 * 1024 * 1024) { toast('Failas per didelis (iki 10 MB).', true); this.value = ''; return; }
      var fd = new FormData();
      fd.append('action', 'ss_chat_upload'); fd.append('nonce', ss_social.nonce);
      fd.append('chat_id', cur.id); fd.append('file', f);
      toast('Įkeliama…');
      $.ajax({ url: ss_social.ajax_url, method: 'POST', data: fd, processData: false, contentType: false })
        .done(function (res) {
          if (res.success) { var s = document.getElementById('ss-chat-messages'); appendMsg(s, res.data.message); cur.lastId = res.data.message.id; updateReceipts(); scrollBottom(); }
          else toast((res.data && res.data.message) || 'Klaida', true);
        }).fail(function () { toast('Serverio klaida', true); });
      this.value = '';
    });

    // Lipdukai
    var $stickerPanel = $('#ss-sticker-panel');
    function toggleStickers() {
      if ($stickerPanel.is(':visible')) { $stickerPanel.hide(); return; }
      if (!$stickerPanel.data('built')) {
        (ss_social.stickers || []).forEach(function (st) {
          var b = el('button', 'ss-sticker-btn'); b.type = 'button';
          var im = document.createElement('img'); im.src = st.url; im.alt = st.key; b.appendChild(im);
          b.onclick = function () { sendSticker(st.key); };
          $stickerPanel[0].appendChild(b);
        });
        $stickerPanel.data('built', true);
      }
      $stickerPanel.css('display', 'flex');
    }
    function sendSticker(key) {
      $stickerPanel.hide();
      if (!cur.id) return;
      post('chat_sticker', { chat_id: cur.id, sticker: key }, function (res) {
        if (!res.success) { toast((res.data && res.data.message) || 'Klaida', true); return; }
        var s = document.getElementById('ss-chat-messages'); appendMsg(s, res.data.message); cur.lastId = res.data.message.id; updateReceipts(); scrollBottom();
      });
    }

    // Balsavimas (tik grupėse)
    var $pollM = $('#ss-poll-modal');
    function pollOptRow(val) {
      var row = el('div', 'ss-poll-opt-row');
      var inp = document.createElement('input'); inp.type = 'text'; inp.className = 'ss-minp ss-poll-opt-inp'; inp.maxLength = 120; inp.placeholder = 'Atsakymas'; if (val) inp.value = val;
      row.appendChild(inp);
      var del = el('button', 'ss-poll-opt-del', '×'); del.type = 'button';
      del.onclick = function () { if ($('#ss-poll-opts .ss-poll-opt-row').length > 2) $(row).remove(); };
      row.appendChild(del);
      return row;
    }
    function openPoll() {
      if (cur.type !== 'group') { toast('Balsavimai galimi tik grupėse.', true); return; }
      $('#ss-poll-q').val(''); $('#ss-poll-multi').prop('checked', false);
      var $o = $('#ss-poll-opts').empty(); $o.append(pollOptRow()); $o.append(pollOptRow());
      $pollM.addClass('open');
    }
    $('#ss-poll-add').on('click', function () { $('#ss-poll-opts').append(pollOptRow()); });
    $('#ss-poll-close, #ss-poll-cancel').on('click', function () { $pollM.removeClass('open'); });
    $pollM.on('click', function (e) { if (e.target === this) $pollM.removeClass('open'); });
    $('#ss-poll-create').on('click', function () {
      var q = $('#ss-poll-q').val().trim();
      var opts = $('#ss-poll-opts .ss-poll-opt-inp').map(function () { return this.value.trim(); }).get().filter(function (v) { return v; });
      if (!q) { toast('Įveskite klausimą', true); return; }
      if (opts.length < 2) { toast('Reikia bent 2 atsakymų', true); return; }
      var $b = $(this).prop('disabled', true);
      post('chat_poll_create', { chat_id: cur.id, question: q, options: opts, multi: $('#ss-poll-multi').is(':checked') ? 1 : 0 }, function (res) {
        $b.prop('disabled', false);
        if (!res.success) { toast((res.data && res.data.message) || 'Klaida', true); return; }
        $pollM.removeClass('open');
        var s = document.getElementById('ss-chat-messages'); appendMsg(s, res.data.message); cur.lastId = res.data.message.id; updateReceipts(); scrollBottom();
      });
    });

    /* ─── „Perskaitė“ detalės (grupės) ─── */
    var $rm = $('#ss-reads-modal');
    $('#ss-reads-close').on('click', function () { $rm.removeClass('open'); });
    $rm.on('click', function (e) { if (e.target === this) $rm.removeClass('open'); });
    function openReads(msgId) {
      var $b = $('#ss-reads-body').empty();
      var readSet = {}; cur.reads.forEach(function (x) { if (x.read_id >= msgId) readSet[x.id] = 1; });
      var others = cur.members.filter(function (m) { return m.id !== ME; });
      var readList = others.filter(function (m) { return readSet[m.id]; });
      var unreadList = others.filter(function (m) { return !readSet[m.id]; });
      function section(title, arr) {
        if (!arr.length) return;
        $b.append(el('div', 'ss-mlbl', title + ' (' + arr.length + ')'));
        arr.forEach(function (m) {
          var row = el('div', 'ss-mm-row');
          var im = el('img', 'ss-freq-av'); im.src = m.avatar; row.appendChild(im);
          row.appendChild(el('span', 'ss-freq-name', m.name));
          $b.append(row);
        });
      }
      section('Perskaitė', readList);
      section('Dar neperskaitė', unreadList);
      if (!others.length) $b.append(el('p', 'ss-prof-empty', 'Grupėje daugiau narių nėra.'));
      $rm.addClass('open');
    }

    /* ─── Kontaktų parinkiklis (naujas pokalbis / pridėti narių) ─── */
    var $pk = $('#ss-picker');
    function openPicker(mode, chatId) {
      picker.mode = mode; picker.chatId = chatId || 0; picker.selected = {}; picker.contacts = []; picker.search = '';
      $('#ss-picker-title').text(mode === 'add' ? 'Pridėti narių' : 'Naujas pokalbis');
      $('#ss-picker-confirm').text(mode === 'add' ? 'Pridėti' : 'Pradėti').prop('disabled', true);
      $('#ss-picker-search').val('');
      $('#ss-picker-gtitle').val('');
      $('#ss-picker-gname').hide();
      $('#ss-picker-ischannel').prop('checked', false);
      $('#ss-picker-channel-row').toggle(mode === 'new');
      $('#ss-picker-count').text('Pažymėta: 0');
      $('#ss-picker-list').html('<p class="ss-prof-empty" style="padding:20px">Kraunama…</p>');
      $pk.css('display', 'flex');
      post('chat_contacts', { exclude_chat: picker.chatId }, function (res) {
        picker.contacts = (res.success && res.data.contacts) || [];
        renderPicker();
        $('#ss-picker-search').focus();
      });
    }
    function closePicker() { $pk.hide(); }
    $('#ss-picker-back').on('click', closePicker);
    $('#ss-new-chat').on('click', function () { openPicker('new', 0); });
    $('#ss-picker-search').on('input', function () { picker.search = this.value.toLowerCase(); renderPicker(); });

    function renderPicker() {
      var $list = $('#ss-picker-list').empty();
      var q = picker.search;
      var pool = picker.contacts.filter(function (u) { return !q || u.name.toLowerCase().indexOf(q) > -1; });
      if (!pool.length) { $list.append(el('p', 'ss-prof-empty', 'Nieko nerasta, su kuo galėtumėte susirašinėti.')); return; }
      SECTIONS.forEach(function (sec) {
        var arr = pool.filter(function (u) { return u.cat === sec[0]; });
        if (!arr.length) return;
        var head = el('div', 'ss-picker-sec');
        head.appendChild(el('span', 'ss-picker-sec-t', sec[1]));
        var all = el('button', 'ss-picker-selall', 'Pažymėti visus');
        all.onclick = function () {
          var every = arr.every(function (u) { return picker.selected[u.id]; });
          arr.forEach(function (u) {
            if (every) delete picker.selected[u.id]; else picker.selected[u.id] = u;
          });
          renderPicker(); refreshPickerFoot();
        };
        head.appendChild(all);
        $list.append(head);
        arr.forEach(function (u) {
          var lbl = el('label', 'ss-picker-row');
          var cb = document.createElement('input'); cb.type = 'checkbox'; cb.value = u.id; cb.checked = !!picker.selected[u.id];
          cb.onchange = function () { if (cb.checked) picker.selected[u.id] = u; else delete picker.selected[u.id]; refreshPickerFoot(); };
          lbl.appendChild(cb);
          var im = el('img', 'ss-freq-av'); im.src = u.avatar; lbl.appendChild(im);
          var info = el('div', 'ss-picker-info');
          info.appendChild(el('span', 'ss-freq-name', u.name));
          info.appendChild(el('span', 'ss-friend-role', u.role));
          lbl.appendChild(info);
          $list.append(lbl);
        });
      });
    }
    function refreshPickerFoot() {
      var n = Object.keys(picker.selected).length;
      var isChan = $('#ss-picker-ischannel').is(':checked');
      $('#ss-picker-count').text('Pažymėta: ' + n);
      $('#ss-picker-confirm').prop('disabled', n === 0);
      $('#ss-picker-gname').toggle(picker.mode === 'new' && (n >= 2 || isChan));
      if (picker.mode === 'add') $('#ss-picker-confirm').text('Pridėti');
      else if (isChan) $('#ss-picker-confirm').text('Sukurti kanalą');
      else if (n >= 2) $('#ss-picker-confirm').text('Sukurti grupę');
      else $('#ss-picker-confirm').text('Pradėti');
    }
    $('#ss-picker-ischannel').on('change', refreshPickerFoot);
    $('#ss-picker-confirm').on('click', function () {
      var ids = Object.keys(picker.selected);
      if (!ids.length) return;
      var $btn = $(this).prop('disabled', true);

      if (picker.mode === 'add') {
        var chatId = picker.chatId, done = 0, ok = 0;
        ids.forEach(function (id) {
          post('chat_add_member', { chat_id: chatId, user_id: id }, function (r) {
            done++; if (r.success) ok++;
            if (done === ids.length) {
              $btn.prop('disabled', false);
              closePicker();
              toast(ok + ' pridėta');
              openChat(chatId);
            }
          });
        });
        return;
      }

      // Naujas pokalbis
      var isChan = $('#ss-picker-ischannel').is(':checked');
      if (!isChan && ids.length === 1) {
        post('chat_create', { kind: 'direct', user_id: ids[0] }, function (res) {
          $btn.prop('disabled', false);
          if (res.success) { closePicker(); loadList(function () { openChat(res.data.chat_id); }); }
          else toast((res.data && res.data.message) || 'Klaida', true);
        });
      } else {
        var title = $('#ss-picker-gtitle').val().trim();
        post('chat_create', { kind: isChan ? 'channel' : 'group', title: title, members: ids }, function (res) {
          $btn.prop('disabled', false);
          if (res.success) { closePicker(); loadList(function () { openChat(res.data.chat_id); }); }
          else toast((res.data && res.data.message) || 'Klaida', true);
        });
      }
    });

    /* ─── Pokalbio nustatymai (išvažiuojanti panelė) ─── */
    function closeSettings() { $app.removeClass('settings-open'); }
    $('#ss-settings-close').on('click', closeSettings);
    $('#ss-chat-manage').on('click', function () { openSettings(); });
    function openSettings() {
      var isChan = cur.type === 'channel';
      $('#ss-settings-title').text(isChan ? 'Kanalo nustatymai' : 'Grupės nustatymai');
      var $b = $('#ss-settings-body').empty();
      function section(title) {
        var s = el('div', 'ss-set-section');
        if (title) s.appendChild(el('div', 'ss-set-section-title', title));
        $b.append(s); return s;
      }

      if (cur.isAdmin) {
        // ── Paveikslėlis + pavadinimas (viena antraštinė sekcija) ──
        var head = section('');
        var avbox = el('div', 'ss-set-avbox');
        var av = el('div', 'ss-set-av');
        if (cur.settings.avatar) { var im = document.createElement('img'); im.src = cur.settings.avatar; av.appendChild(im); } else av.innerHTML = dashicon(isChan ? 'megaphone' : 'groups', 30);
        var camera = el('button', 'ss-set-av-edit'); camera.title = 'Keisti nuotrauką'; camera.innerHTML = dashicon('camera', 15);
        camera.onclick = function () { $('#ss-set-avfile').click(); };
        av.appendChild(camera);
        avbox.appendChild(av);
        head.appendChild(avbox);
        var nrow = el('div', 'ss-set-row');
        var ti = document.createElement('input'); ti.className = 'ss-minp'; ti.value = $('#ss-chat-title').text(); ti.placeholder = 'Pavadinimas';
        nrow.appendChild(ti);
        var sv = el('button', 'ss-prof-mini-btn', 'Išsaugoti');
        sv.onclick = function () { post('chat_rename', { chat_id: cur.id, title: ti.value.trim() }, function (r) { if (r.success) { $('#ss-chat-title').text(r.data.title); toast('Pervadinta'); } }); };
        nrow.appendChild(sv);
        head.appendChild(nrow);

        // ── Fonas ──
        var bgSec = section('Pokalbio fonas');
        var grid = el('div', 'ss-bg-grid');
        var bgs = ss_social.chat_bgs || {};
        Object.keys(bgs).forEach(function (key) {
          var sw = el('button', 'ss-bg-swatch' + (cur.settings.bg === key ? ' active' : ''));
          sw.style.background = bgs[key] || 'var(--bg)';
          sw.title = key === 'default' ? 'Numatytas' : '';
          sw.onclick = function () {
            post('chat_set_bg', { chat_id: cur.id, bg: key }, function (r) {
              if (!r.success) { toast((r.data && r.data.message) || 'Klaida', true); return; }
              cur.settings.bg = r.data.bg; cur.settings.bg_css = r.data.bg_css;
              applyChatBg(r.data.bg_css);
              grid.querySelectorAll('.ss-bg-swatch').forEach(function (x) { x.classList.remove('active'); });
              sw.classList.add('active');
            });
          };
          grid.appendChild(sw);
        });
        bgSec.appendChild(grid);
      }

      // ── Nariai ──
      var chanNonAdmin = isChan && !cur.isAdmin;
      var memLabel = chanNonAdmin ? ('Administratoriai (' + cur.members.length + ')') : ('Nariai (' + (cur.memberCount || cur.members.length) + ')');
      var memSec = section(memLabel);
      var list = el('div', 'ss-mm-members');
      cur.members.forEach(function (u) {
        var row = el('div', 'ss-mm-row');
        var im = el('img', 'ss-freq-av'); im.style.cursor = 'pointer'; im.src = u.avatar; im.onclick = function () { location.href = profileUrl(u.id); };
        row.appendChild(im);
        var info = el('div', 'ss-mm-info');
        var nm = el('span', 'ss-freq-name', u.name); nm.style.cursor = 'pointer'; nm.onclick = function () { location.href = profileUrl(u.id); };
        info.appendChild(nm);
        if (u.role === 'admin') info.appendChild(el('span', 'ss-mm-badge', 'administratorius'));
        row.appendChild(info);
        if (cur.isAdmin && u.id !== ME) {
          var rm = el('button', 'ss-mm-remove'); rm.title = 'Pašalinti'; rm.innerHTML = dashicon('close', 16);
          rm.onclick = function () { post('chat_remove_member', { chat_id: cur.id, user_id: u.id }, function (r) { if (r.success) { $(row).remove(); } }); };
          row.appendChild(rm);
        }
        list.appendChild(row);
      });
      memSec.appendChild(list);
      if (chanNonAdmin) memSec.appendChild(el('div', 'ss-mm-sub', 'Prenumeratorių: ' + (cur.subCount || 0)));
      if (cur.isAdmin) {
        var addBtn = el('button', 'ss-set-add', ''); addBtn.innerHTML = dashicon('plus', 16) + '<span>Pridėti narių</span>';
        addBtn.onclick = function () { closeSettings(); openPicker('add', cur.id); };
        memSec.appendChild(addBtn);
      }

      // ── Palikti ──
      var leave = el('button', 'ss-set-leave', isChan ? 'Palikti kanalą' : 'Palikti grupę');
      leave.onclick = function () { if (!confirm(isChan ? 'Tikrai palikti kanalą?' : 'Tikrai palikti grupę?')) return; post('chat_leave', { chat_id: cur.id }, function (r) { if (r.success) { closeSettings(); cur.id = 0; stopPoll(); $('#ss-chat-conv').hide(); $('#ss-chat-placeholder').show(); $app.removeClass('conv-open'); loadList(); } }); };
      $b.append(leave);

      $app.addClass('settings-open');
    }
    // Pokalbio paveikslėlio įkėlimas
    $('#ss-set-avfile').on('change', function () {
      var f = this.files && this.files[0]; if (!f || !cur.id) { this.value = ''; return; }
      if (f.size > 5 * 1024 * 1024) { toast('Nuotrauka per didelė (iki 5 MB).', true); this.value = ''; return; }
      var fd = new FormData();
      fd.append('action', 'ss_chat_upload_avatar'); fd.append('nonce', ss_social.nonce);
      fd.append('chat_id', cur.id); fd.append('file', f);
      toast('Įkeliama…');
      $.ajax({ url: ss_social.ajax_url, method: 'POST', data: fd, processData: false, contentType: false })
        .done(function (res) {
          if (!res.success) { toast((res.data && res.data.message) || 'Klaida', true); return; }
          cur.settings.avatar = res.data.avatar;
          if ($app.hasClass('settings-open')) openSettings();
          var $ca = $('#ss-chat-av').empty().removeClass('grp'); $ca.append($('<img>').attr('src', res.data.avatar));
          loadList();
          toast('Atnaujinta');
        }).fail(function () { toast('Serverio klaida', true); });
      this.value = '';
    });

    /* ─── Pradžia ─── */
    loadList(function () {
      var open = parseInt($app.data('open'), 10) || 0;
      if (open) openChat(open);
    });
    setInterval(function () { if (!document.hidden) loadList(); }, 12000);
  }

  // Buvimo statuso „širdies plakimas" — atnaujina ss_last_active (per guard()).
  function heartbeat() {
    if (document.hidden) return;
    $.post(ss_social.ajax_url, { action: 'ss_chat_unread_count', nonce: ss_social.nonce });
  }

  $(function () {
    if ($('#ss-prof-actions').length || $('#ss-find-friends-modal').length) initProfile();
    if ($('#ss-msg-app').length) initMessages();
    heartbeat();
    setInterval(heartbeat, 60000);
  });
})(jQuery);
