<?php
/**
 * @var \App\View\AppView $this
 * @var \App\Model\Entity\Inbox $inbox
 *
 * Settings form element — UI-SPEC §3 / D-28 / D-07 / D-08 / D-10 / D-11.
 * Consumed by templates/Users/dashboard.php and templates/Inboxes/settings.php.
 */
$probabilityPct = (int)round((float)$inbox->ssr_probability * 100);
$welcomeMessage = $inbox->welcome_message !== null ? (string)$inbox->welcome_message : '';
$isAccepting = (bool)$inbox->is_accepting;
?>
<?= $this->Form->create(null, [
    'url' => '/dashboard/settings',
    'type' => 'post',
    'class' => 'settings-form',
]) ?>
    <fieldset>
        <legend>SSR 確率(送信者が開示される確率)</legend>
        <div class="probability-control">
            <input type="range"
                   name="ssr_probability_pct_range"
                   min="0" max="100" step="1"
                   value="<?= $probabilityPct ?>"
                   aria-label="確率スライダ"
                   id="prob-range">
            <input type="number"
                   name="ssr_probability_pct"
                   min="0" max="100" step="1"
                   value="<?= $probabilityPct ?>"
                   aria-label="確率値"
                   id="prob-number">
            <span class="probability-suffix">%</span>
        </div>
        <p class="text-secondary">デフォルト 10%、0% / 100% 設定時は確認ダイアログが表示されます</p>
    </fieldset>

    <fieldset>
        <legend>welcome message(送信フォーム上部に表示される歓迎文、任意)</legend>
        <textarea name="welcome_message" maxlength="1000" rows="4"><?= h($welcomeMessage) ?></textarea>
    </fieldset>

    <fieldset>
        <legend>受信を受け付ける</legend>
        <label>
            <input type="checkbox" name="is_accepting" value="1" <?= $isAccepting ? 'checked' : '' ?>>
            現在この受信箱でメッセージを受け付ける
        </label>
        <p class="text-secondary">OFF にすると <code>/&lt;slug&gt;</code> で送信フォームが非表示になります</p>
    </fieldset>

    <button type="submit" class="button primary-button">保存する</button>

    <fieldset class="settings-form__danger-zone">
        <legend>退会</legend>
        <p class="text-secondary">アカウントを削除すると元に戻せません。</p>
        <a href="/account/delete" class="button button-clear button-destructive">退会の手続きへ</a>
    </fieldset>
<?= $this->Form->end() ?>

<script>
(function () {
    var range = document.getElementById('prob-range');
    var number = document.getElementById('prob-number');
    if (!range || !number) { return; }
    range.addEventListener('input', function () { number.value = range.value; });
    number.addEventListener('input', function () { range.value = number.value; });
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
