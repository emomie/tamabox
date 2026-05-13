<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Inbox $inbox
 *
 * UI-04 — Inbox settings form (Phase 6 Calm Gacha rewrite of UI-SPEC v1 §3).
 * Hi-fi: ~/projects/handoff_tamabox/screens/Settings.jsx
 *
 * Consumed by templates/Users/dashboard.php and templates/Inboxes/settings.php.
 * AppBar is the responsibility of the parent template (this element renders form sections only).
 *
 * Behavioral parity (preserved):
 * - Field names ssr_probability_pct_range / ssr_probability_pct / welcome_message / is_accepting
 * - Confirm dialog at 0% and 100% on submit
 * - Slider <-> number sync (existing JS, extended to update visual track + thumb + display)
 */
$probabilityPct = (int)round((float)$inbox->ssr_probability * 100);
$welcomeMessage = $inbox->welcome_message !== null ? (string)$inbox->welcome_message : '';
$isAccepting = (bool)$inbox->is_accepting;
?>
<?= $this->Form->create(null, [
    'url' => '/dashboard/settings',
    'type' => 'post',
    'class' => 'settings-form tb-settings-form',
]) ?>

    <section class="tb-settings__section">
        <div class="tb-section-label">SSR 確率</div>
        <div class="tb-card tb-settings__ssr-card">
            <div class="tb-settings__ssr-head">
                <span class="tb-settings__ssr-label">送信者が開示される確率</span>
                <span class="tb-settings__ssr-value">
                    <span class="tb-mono tb-settings__ssr-num" id="prob-display"><?= $probabilityPct ?></span><span class="tb-settings__ssr-suffix">%</span>
                </span>
            </div>

            <div class="tb-slider" id="prob-slider" style="--p: <?= $probabilityPct ?>;">
                <div class="tb-slider__track" aria-hidden="true"></div>
                <div class="tb-slider__fill" aria-hidden="true"></div>
                <input type="range"
                       name="ssr_probability_pct_range"
                       min="0" max="100" step="1"
                       value="<?= $probabilityPct ?>"
                       aria-label="確率スライダ"
                       class="tb-slider__input"
                       id="prob-range">
                <div class="tb-slider__thumb" aria-hidden="true"></div>
            </div>

            <div class="tb-slider__scale" aria-hidden="true">
                <span>0%</span><span>25</span><span>50</span><span>75</span><span>100</span>
            </div>

            <div class="tb-settings__presets">
                <?php foreach ([0, 5, 10, 25, 50] as $preset): ?>
                    <button type="button"
                            class="tb-preset<?= $preset === $probabilityPct ? ' is-active' : '' ?>"
                            data-preset="<?= $preset ?>"
                    ><?= $preset ?>%</button>
                <?php endforeach; ?>
            </div>

            <p class="tb-settings__hint">
                高くするほど、送信者の Bluesky アカウントが受信時に開示されやすくなります。
            </p>

            <label class="tb-settings__num-control" aria-label="確率値(数字入力)">
                <input type="number"
                       name="ssr_probability_pct"
                       min="0" max="100" step="1"
                       value="<?= $probabilityPct ?>"
                       class="tb-input tb-settings__num-input"
                       id="prob-number">
                <span class="tb-mono tb-settings__num-suffix">%</span>
            </label>
        </div>
    </section>

    <section class="tb-settings__section">
        <div class="tb-section-label">ウェルカムメッセージ</div>
        <textarea name="welcome_message" maxlength="1000" rows="4"
                  class="tb-input tb-settings__welcome"
                  placeholder="送信フォーム上部に表示される歓迎文(任意)"><?= h($welcomeMessage) ?></textarea>
        <p class="tb-settings__sub">送信フォームの上部に表示されます。最大 1000 文字。</p>
    </section>

    <section class="tb-settings__section">
        <div class="tb-card tb-settings__row">
            <div class="tb-settings__row-body">
                <div class="tb-settings__row-title">新規メッセージを受け付ける</div>
                <div class="tb-settings__row-sub">OFF にすると <code>/&lt;slug&gt;</code> で送信フォームが非表示になります</div>
            </div>
            <label class="tb-toggle">
                <input type="checkbox" name="is_accepting" value="1" <?= $isAccepting ? 'checked' : '' ?> class="tb-toggle__input">
                <span class="tb-toggle__pill" aria-hidden="true">
                    <span class="tb-toggle__knob"></span>
                </span>
                <span class="visually-hidden">受信を受け付ける</span>
            </label>
        </div>
    </section>

    <div class="tb-settings__save">
        <button type="submit" class="tb-btn tb-btn--primary tb-btn--full">保存する</button>
    </div>

    <section class="tb-settings__section tb-settings__danger">
        <div class="tb-section-label tb-section-label--danger">危険ゾーン</div>
        <div class="tb-card tb-settings__danger-card">
            <a href="/account/delete" class="tb-danger-row">
                <span>退会の手続きへ</span>
                <?= $this->element('icon', ['name' => 'chevron', 'size' => 16]) ?>
            </a>
        </div>
    </section>

<?= $this->Form->end() ?>

<script>
(function () {
    var range   = document.getElementById('prob-range');
    var number  = document.getElementById('prob-number');
    var display = document.getElementById('prob-display');
    var slider  = document.getElementById('prob-slider');
    if (!range || !number) { return; }

    function sync(v) {
        v = Math.max(0, Math.min(100, parseInt(v, 10) || 0));
        range.value = v;
        number.value = v;
        if (display) { display.textContent = v; }
        if (slider) { slider.style.setProperty('--p', v); }
        var presets = document.querySelectorAll('.tb-preset');
        presets.forEach(function (b) {
            if (parseInt(b.getAttribute('data-preset'), 10) === v) { b.classList.add('is-active'); }
            else { b.classList.remove('is-active'); }
        });
    }

    range.addEventListener('input',  function () { sync(range.value); });
    number.addEventListener('input', function () { sync(number.value); });
    document.querySelectorAll('.tb-preset').forEach(function (b) {
        b.addEventListener('click', function (e) {
            e.preventDefault();
            sync(b.getAttribute('data-preset'));
        });
    });

    var form = document.querySelector('.settings-form');
    if (form) {
        form.addEventListener('submit', function (e) {
            var v = parseInt(number.value, 10);
            if (v === 0) {
                if (!confirm('0% にするとコア体験(送信者開示の楽しみ)が失われますが、それでも設定しますか?')) {
                    e.preventDefault();
                }
            } else if (v === 100) {
                if (!confirm('全てのメッセージで送信者が開示されます — 本当によろしいですか?')) {
                    e.preventDefault();
                }
            }
        });
    }
})();
</script>
