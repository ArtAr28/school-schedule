<?php if (!defined('ABSPATH')) exit; ?>
<div class="ss-cpicker">
    <div class="ss-swatches">
        <?php foreach(['#4F46E5','#7C3AED','#DB2777','#DC2626','#D97706','#059669','#0284C7','#0891B2','#65A30D','#374151'] as $c):?>
        <button type="button" class="ss-swatch" data-color="<?=$c?>" style="background:<?=$c?>" title="<?=$c?>"></button>
        <?php endforeach;?>
    </div>
    <div class="ss-cpicker-inputs">
        <input type="color" class="ss-native-color" value="#4F46E5" title="Pasirinkite spalvą">
        <input type="text" class="ss-hex-input small-text" value="#4F46E5" placeholder="#ffffff" maxlength="7">
    </div>
    <input type="hidden" class="ss-color-val" value="#4F46E5">
</div>
