<?php defined('ALTUMCODE') || die() ?>

<?php if(!$this->transfer->settings->is_removed_branding || ($this->transfer->settings->is_removed_branding && !$this->transfer_user->plan_settings->removable_branding_is_enabled)) :?>
    <div class="text-center mb-4">
        <a href="<?= url() ?>" target="_blank">
            <?php if(settings()->main->{'logo_' . \Altum\ThemeStyle::get()} != ''): ?>
                <img src="<?= settings()->main->{'logo_' . \Altum\ThemeStyle::get() . '_full_url'} ?>" style="max-width: 100%; height: 4rem; max-height: 4rem;" alt="<?= l('global.accessibility.logo_alt') ?>" />
            <?php else: ?>
                <span class="h3"><?= settings()->main->title ?></span>
            <?php endif ?>
        </a>
    </div>
<?php endif ?>
