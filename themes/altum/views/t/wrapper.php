<?php defined('ALTUMCODE') || die() ?>
<!DOCTYPE html>
<html lang="<?= \Altum\Language::$code ?>" dir="<?= l('direction') ?>">
    <head>
        <title><?= \Altum\Title::get() ?></title>
        <base href="<?= SITE_URL ?>">
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />

        <?php if(\Altum\Plugin::is_active('pwa') && settings()->pwa->is_enabled): ?>
            <meta name="theme-color" content="<?= settings()->pwa->theme_color ?>"/>
            <link rel="manifest" href="<?= SITE_URL . UPLOADS_URL_PATH . \Altum\Uploads::get_path('pwa') . 'manifest.json' ?>" />
        <?php endif ?>

        <?php if(\Altum\Meta::$description): ?>
            <meta name="description" content="<?= \Altum\Meta::$description ?>" />
        <?php endif ?>
        <?php if(\Altum\Meta::$keywords): ?>
            <meta name="keywords" content="<?= \Altum\Meta::$keywords ?>" />
        <?php endif ?>

        <?php \Altum\Meta::output() ?>

        <?php if(\Altum\Meta::$canonical): ?>
            <link rel="canonical" href="<?= \Altum\Meta::$canonical ?>" />
        <?php endif ?>

        <?php if(!empty(settings()->main->favicon)): ?>
            <link href="<?= settings()->main->favicon_full_url ?>" rel="icon" />
        <?php endif ?>

        <link href="<?= ASSETS_FULL_URL . 'css/' . \Altum\ThemeStyle::get_file() . '?v=' . PRODUCT_CODE ?>" id="css_theme_style" rel="stylesheet" media="screen,print">
        <link href="<?= ASSETS_FULL_URL . 'css/transfer-custom.css?v=' . PRODUCT_CODE ?>" rel="stylesheet" media="screen,print">

        <?= \Altum\Event::get_content('head') ?>

        <?php if(is_logged_in() && !user()->plan_settings->export->pdf): ?>
            <style>@media print { body { display: none; } }</style>
        <?php endif ?>

        <?php if(!empty(settings()->custom->head_js_transfers)): ?>
            <?= get_settings_custom_head_js('head_js_transfers') ?>
        <?php endif ?>

        <?php if(!empty(settings()->custom->head_css_transfers)): ?>
            <style><?= settings()->custom->head_css_transfers ?></style>
        <?php endif ?>

        <?php if(!empty($this->transfer->settings->custom_css) && $this->transfer_user->plan_settings->custom_css_is_enabled): ?>
            <style><?= $this->transfer->settings->custom_css ?></style>
        <?php endif ?>
    </head>

    <body class="<?= l('direction') == 'rtl' ? 'rtl' : null ?>" data-theme-style="<?= \Altum\ThemeStyle::get() ?>">
        <?php require THEME_PATH . 'views/partials/cookie_consent.php' ?>
        <?php require THEME_PATH . 'views/partials/ad_blocker_detector.php' ?>

        <main class="altum-animate altum-animate-fill-none altum-animate-fade-in mt-5 mt-lg-8">

            <?php require THEME_PATH . 'views/t/partials/header.php' ?>

            <?php require THEME_PATH . 'views/t/partials/ads_header.php' ?>

            <div class="container">
                <div class="row">
                    <div class="col-md-10 offset-md-1">
                        <div class="card py-6">
                            <div class="card-body">
                                <?= $this->views['content'] ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <?php require THEME_PATH . 'views/t/partials/ads_footer.php' ?>
        </main>

        <?= $this->views['footer'] ?>

        <?php if(settings()->transfers->report_is_enabled): ?>
            <div id="info" class="link-info">
                <a href="<?= url('contact?subject=' . urlencode(sprintf(l('t_transfer.report.subject'), remove_url_protocol_from_url($this->transfer->full_url))) . '&message=' . urlencode(l('t_transfer.report.message'))) ?>" target="_blank" title="<?= l('t_transfer.report') ?>">
                    <i class="fas fa-fw fa-xs fa-flag"></i>
                </a>
            </div>
        <?php endif ?>

        <?= \Altum\Event::get_content('modals') ?>

        <?php require THEME_PATH . 'views/partials/js_global_variables.php' ?>

        <?php foreach(['libraries/jquery.slim.min.js', 'libraries/popper.min.js', 'libraries/bootstrap.min.js', 'custom.js', 'libraries/fontawesome.min.js', 'libraries/fontawesome-solid.min.js'] as $file): ?>
            <script src="<?= ASSETS_FULL_URL ?>js/<?= $file ?>?v=<?= PRODUCT_CODE ?>"></script>
        <?php endforeach ?>

        <?= \Altum\Event::get_content('javascript') ?>

        <?php if(!empty($this->transfer->settings->custom_js) && $this->transfer_user->plan_settings->custom_js_is_enabled): ?>
            <?= $this->transfer->settings->custom_js ?>
        <?php endif ?>
    </body>
</html>
