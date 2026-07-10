<?php
/**
 * Žinučių puslapis: asmeniniai ir grupiniai pokalbiai.
 * Turinį pildo public/js/social.js per apsaugotus AJAX galinius taškus.
 */
if (!defined('ABSPATH')) exit;
$open_chat = (int) ($_GET['c'] ?? 0);
?>
<div class="ss-app ss-app--chat" id="ss-app">
  <?php
    $ss_on_dashboard = false;
    $ss_active       = 'messages';
    include SS_PLUGIN_DIR . 'public/views/partials/app-nav.php';
    include SS_PLUGIN_DIR . 'public/views/partials/maintenance-banner.php';
  ?>
  <main class="ss-main">
    <div class="ss-msg-app" id="ss-msg-app" data-open="<?= (int)$open_chat ?>">

      <!-- Kairė: pokalbių sąrašas -->
      <aside class="ss-msg-side" id="ss-msg-side">
        <div class="ss-msg-side-head">
          <h2>Žinutės</h2>
          <button type="button" class="ss-msg-new" id="ss-new-chat" title="Naujas pokalbis" aria-label="Naujas pokalbis"><?= SS_Social::icon('plus', 22) ?></button>
        </div>
        <div class="ss-msg-list" id="ss-chat-list">
          <div class="ss-msg-empty" id="ss-chat-list-empty" style="display:none">Pokalbių dar nėra. Spauskite „+“, kad pradėtumėte.</div>
        </div>
      </aside>

      <!-- Dešinė: aktyvus pokalbis -->
      <section class="ss-msg-view" id="ss-chat-view">
        <div class="ss-msg-placeholder" id="ss-chat-placeholder">
          <div class="ss-msg-ph-ico"><?= SS_Social::icon('chat', 52) ?></div>
          <p>Pasirinkite pokalbį iš sąrašo arba spauskite „+“, kad pradėtumėte naują.</p>
        </div>

        <div class="ss-msg-conv" id="ss-chat-conv" style="display:none">
          <header class="ss-msg-conv-head">
            <button type="button" class="ss-msg-back" id="ss-chat-back" aria-label="Atgal"><?= SS_Social::icon('back', 22) ?></button>
            <div class="ss-msg-conv-av" id="ss-chat-av"></div>
            <div class="ss-msg-conv-id">
              <div class="ss-msg-conv-title" id="ss-chat-title"></div>
              <div class="ss-msg-conv-sub" id="ss-chat-sub"></div>
            </div>
            <button type="button" class="ss-msg-gear" id="ss-chat-manage" title="Grupės nustatymai" style="display:none"><?= SS_Social::icon('gear', 20) ?></button>
          </header>
          <div class="ss-msg-scroll" id="ss-chat-messages"></div>
          <div class="ss-chat-typing" id="ss-chat-typing" style="display:none"></div>

          <!-- Lipdukų parinkiklis -->
          <div class="ss-sticker-panel" id="ss-sticker-panel" style="display:none"></div>

          <form class="ss-msg-composer" id="ss-chat-composer">
            <div class="ss-msg-editing" id="ss-msg-editing" style="display:none">
              <span><?= SS_Social::icon('pencil', 14) ?> Redaguojama žinutė</span>
              <button type="button" id="ss-edit-cancel" aria-label="Atšaukti">&times;</button>
            </div>
            <div class="ss-msg-composer-row">
              <div class="ss-attach-wrap">
                <button type="button" class="ss-msg-attach" id="ss-chat-attach" title="Pridėti" aria-label="Pridėti"><?= SS_Social::icon('plus', 22) ?></button>
                <div class="ss-attach-menu" id="ss-attach-menu" style="display:none">
                  <button type="button" data-att="media"><?= SS_Social::icon('image', 18) ?> Nuotrauka / failas</button>
                  <button type="button" data-att="sticker"><?= SS_Social::icon('smiley', 18) ?> Lipdukas</button>
                  <button type="button" data-att="poll" id="ss-att-poll"><?= SS_Social::icon('poll', 18) ?> Balsavimas</button>
                  <div class="ss-attach-hint">Failai iki 10 MB. Saugomi 7 dienas.</div>
                </div>
              </div>
              <input type="file" id="ss-chat-file" accept="image/jpeg,image/png,image/gif,image/webp,application/pdf" style="display:none">
              <textarea id="ss-chat-input" rows="1" placeholder="Rašykite žinutę…" maxlength="4000"></textarea>
              <button type="submit" class="ss-msg-send" aria-label="Siųsti"><?= SS_Social::icon('send', 22) ?></button>
            </div>
          </form>
          <div class="ss-chat-readonly" id="ss-chat-readonly" style="display:none"><?= SS_Social::icon('channel', 16) ?> Tik kanalo administratorius gali rašyti.</div>
        </div>
      </section>

      <!-- Kontaktų parinkiklis (per visą ekraną — naujas pokalbis / nariai) -->
      <div class="ss-picker" id="ss-picker" style="display:none">
        <header class="ss-picker-head">
          <button type="button" class="ss-msg-back" id="ss-picker-back" aria-label="Atgal"><?= SS_Social::icon('back', 22) ?></button>
          <h2 id="ss-picker-title">Naujas pokalbis</h2>
        </header>
        <div class="ss-picker-tools">
          <input type="text" id="ss-picker-search" class="ss-minp" placeholder="Ieškoti pagal vardą…" autocomplete="off">
          <label class="ss-picker-channel" id="ss-picker-channel-row" style="display:none">
            <input type="checkbox" id="ss-picker-ischannel"><span>Kanalas — rašo tik administratorius (vienpusis)</span>
          </label>
          <div class="ss-picker-gname" id="ss-picker-gname" style="display:none">
            <input type="text" id="ss-picker-gtitle" class="ss-minp" placeholder="Pavadinimas…" maxlength="120">
          </div>
        </div>
        <div class="ss-picker-list" id="ss-picker-list"></div>
        <footer class="ss-picker-foot">
          <div class="ss-picker-count" id="ss-picker-count">Pažymėta: 0</div>
          <button type="button" class="ss-btn-save" id="ss-picker-confirm" disabled>Pradėti</button>
        </footer>
      </div>

      <!-- Pokalbio nustatymai (išvažiuoja iš dešinės; sąrašas nuvažiuoja kairėn) -->
      <aside class="ss-chat-settings" id="ss-chat-settings">
        <header class="ss-settings-head">
          <button type="button" class="ss-msg-back" id="ss-settings-close" aria-label="Uždaryti"><?= SS_Social::icon('back', 22) ?></button>
          <h3 id="ss-settings-title">Nustatymai</h3>
        </header>
        <div class="ss-settings-body" id="ss-settings-body"></div>
        <input type="file" id="ss-set-avfile" accept="image/jpeg,image/png,image/webp,image/gif" style="display:none">
      </aside>
    </div>
  </main>

  <!-- Reakcijų parinkiklis (plaukiojantis) -->
  <div class="ss-react-pop" id="ss-react-pop" style="display:none"></div>

  <!-- „Perskaitė“ (grupės žinutės detalės) -->
  <div class="ss-modal-wrap" id="ss-reads-modal" role="dialog" aria-modal="true">
    <div class="ss-modal-box">
      <div class="ss-modal-hd"><h3>Perskaitė</h3><button type="button" class="ss-modal-close" id="ss-reads-close">&times;</button></div>
      <div class="ss-modal-bd" id="ss-reads-body"></div>
    </div>
  </div>

  <!-- Naujas balsavimas (tik grupėse) -->
  <div class="ss-modal-wrap" id="ss-poll-modal" role="dialog" aria-modal="true">
    <div class="ss-modal-box">
      <div class="ss-modal-hd"><h3>Naujas balsavimas</h3><button type="button" class="ss-modal-close" id="ss-poll-close">&times;</button></div>
      <div class="ss-modal-bd">
        <label class="ss-mlbl">Klausimas</label>
        <input type="text" id="ss-poll-q" class="ss-minp" maxlength="200" placeholder="Pvz. Kada susitinkame?">
        <label class="ss-mlbl" style="margin-top:12px">Atsakymai</label>
        <div id="ss-poll-opts"></div>
        <button type="button" class="ss-prof-mini-btn ss-mini-ghost" id="ss-poll-add" style="margin-top:6px">+ Pridėti atsakymą</button>
        <label class="ss-toggle-row" style="display:flex;align-items:center;gap:10px;margin-top:14px;cursor:pointer">
          <input type="checkbox" id="ss-poll-multi" style="width:16px;height:16px;accent-color:var(--g)">
          <span style="font-size:14px;color:var(--dk)">Leisti kelis pasirinkimus</span>
        </label>
        <div class="ss-modal-ft">
          <button type="button" class="ss-btn-cancel" id="ss-poll-cancel">Atšaukti</button>
          <button type="button" class="ss-btn-save" id="ss-poll-create">Sukurti</button>
        </div>
      </div>
    </div>
  </div>
</div>
