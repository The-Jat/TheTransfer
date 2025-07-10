<?php defined('ALTUMCODE') || die() ?>

<div class="row justify-content-center">
    <div class="col-10">
        <div class="mb-4">
            <h1 class="h3"><?= sprintf(l('t_transfer.download.header'), $data->transfer->name)  ?></h1>
            <?php if($data->transfer->description): ?>
                <p class="text-muted"><?= $data->transfer->description ?></p>
            <?php endif ?>

            <div class="row text-muted small">
                <?php if($data->transfer->expiration_datetime): ?>
                    <div class="col-auto"><i class="fas fa-fw fa-sm fa-hourglass-half mr-1"></i> <?= sprintf(l('t_transfer.download.expiration_datetime'), \Altum\Date::get_time_until($data->transfer->expiration_datetime)) ?></div>
                <?php endif ?>

                <?php if($data->transfer->downloads_limit): ?>
                    <?php $downloads_left = ($data->transfer->downloads_limit - $data->transfer->downloads) <= 0 ? 0 : $data->transfer->downloads_limit - $data->transfer->downloads; ?>
                    <div class="col-auto"><i class="fas fa-fw fa-sm fa-download mr-1"></i> <?= sprintf(l('t_transfer.download.downloads_limit'), nr($downloads_left)) ?></div>
                <?php endif ?>
            </div>
        </div>

        <?= \Altum\Alerts::output_alerts() ?>

        <div class="row align-items-center bg-gray-100 rounded mb-3 py-1 font-weight-bold">
            <div class="col text-truncate text-muted">
                <i class="fas fa-fw fa-sm fa-copy"></i>
                <span class="ml-2"><?= sprintf(l('t_transfer.download.files'), nr($data->files_stats['total_files'])) ?></span>
            </div>
            <div class="col-auto">
                <span class="text-muted"><?= get_formatted_bytes($data->files_stats['total_size']) ?></span>
            </div>
        </div>

        <?php if($data->files_stats['total_files']): ?>
            <form action="" method="post" role="form">
                <input type="hidden" name="token" value="<?= \Altum\Csrf::get() ?>" />
                <input type="hidden" name="type" value="download" />

                <?php foreach($data->files as $file): ?>
                    <div class="row align-items-center my-3">
                        <div class="col d-flex align-items-center text-truncate">
                            <?php if($file->is_encrypted): ?>
                                <span data-toggle="tooltip" title="<?= l('t_transfer.file_is_encrypted') ?>">
                                    <i class="fas fa-fw fa-sm fa-fingerprint text-primary"></i>
                                </span>
                            <?php else: ?>

                                <?php
                                $file_extension = explode('.', $file->name);
                                $file_extension = end($file_extension);
                                ?>

                                <?php if($data->transfer->settings->file_preview_is_enabled && in_array($file_extension, explode(',', settings()->transfers->preview_file_extensions))): ?>
                                    <a href="<?= url('preview/' . bin2hex($file->file_uuid)) ?>" class="text-decoration-none" target="_blank" data-toggle="tooltip" title="<?= l('t_transfer.file_preview') ?>">
                                        <i class="fas fa-fw fa-eye text-primary"></i>
                                    </a>
                                <?php else: ?>
                                    <span data-toggle="tooltip">
                                        <i class="fas fa-fw fa-sm fa-file text-muted"></i>
                                    </span>
                                <?php endif ?>
                            <?php endif ?>

                            <button type="submit" name="download" value="<?= $file->file_id ?>" class="btn btn-sm btn-link" data-toggle="tooltip" title="<?= l('global.download') ?>">
                                <i class="fas fa-fw fa-download text-muted"></i>
                            </button>

                            <span class="text-truncate ml-1"><?= $file->original_name ?></span>
                        </div>

                        <div class="col-auto">
                            <span class="text-muted"><?= get_formatted_bytes($file->size) ?></span>
                        </div>
                    </div>

                    <?php
                    $gallery_file_preview_images = ['jpg', 'jpeg', 'png', 'gif', 'svg', 'webp'];
                    $gallery_file_preview_videos = ['mp4', 'webm'];
                    $gallery_file_preview_audios = ['mp3', 'ogg'];
                    ?>
                    <?php if($data->transfer->settings->gallery_file_preview_is_enabled && in_array($file_extension, array_merge($gallery_file_preview_images, $gallery_file_preview_videos, $gallery_file_preview_audios))): ?>
                        <div class="row align-items-center mb-5">
                            <div class="col">
                                <?php if(in_array($file_extension, $gallery_file_preview_images)): ?>
                                    <a href="<?= url('preview/' . bin2hex($file->file_uuid)) ?>" class="text-decoration-none" target="_blank">
                                        <img src="<?= url('preview/' . bin2hex($file->file_uuid)) ?>" class="w-100 rounded" data-toggle="tooltip" title="<?= l('t_transfer.file_preview') ?>" />
                                    </a>
                                <?php endif ?>

                                <?php if(in_array($file_extension, $gallery_file_preview_videos)): ?>
                                    <video class="w-100 rounded" data-toggle="tooltip" title="<?= l('t_transfer.file_preview') ?>" controls>
                                        <source src="<?= url('preview/' . bin2hex($file->file_uuid)) ?>">
                                    </video>
                                <?php endif ?>

                                <?php if(in_array($file_extension, $gallery_file_preview_audios)): ?>
                                    <audio class="w-100 rounded" data-toggle="tooltip" title="<?= l('t_transfer.file_preview') ?>" controls>
                                        <source src="<?= url('preview/' . bin2hex($file->file_uuid)) ?>">
                                    </audio>
                                <?php endif ?>
                            </div>
                        </div>
                    <?php endif ?>
                <?php endforeach ?>

                <?php if(settings()->captcha->transfer_download_is_enabled): ?>
                    <div class="form-group">
                        <?php $data->captcha->display() ?>
                    </div>
                <?php endif ?>

                <button type="submit" class="btn btn-block btn-primary mt-4" <?= $data->transfer_user->plan_settings->download_unlocking_time > 0 ? 'disabled="disabled"' : null ?>>
                    <i class="fas fa-fw fa-sm fa-download mr-1"></i> <?= l('global.download') ?>
                </button>

                <?php if($data->transfer_user->plan_settings->download_unlocking_time > 0): ?>
                    <div class="text-center text-muted mt-3" id="download_unlock_seconds">
                        <?= sprintf(l('t_transfer.download.download_unlock_seconds'), $data->transfer_user->plan_settings->download_unlocking_time) ?>
                    </div>
                <?php endif ?>
            </form>
        <?php endif ?>
    </div>
</div>

<?php if($data->transfer_user->plan_settings->download_unlocking_time > 0): ?>
    <?php ob_start() ?>
    <script>
        'use strict';

        let download_unlock_seconds = <?= $data->transfer_user->plan_settings->download_unlocking_time ?>;

        let download_unlock_seconds_remaining = download_unlock_seconds;

        if(document.querySelector('button[type="submit"]')) {
            let countdown = setInterval(() => {
                document.querySelector('#download_unlock_seconds').innerHTML = <?= json_encode(l('t_transfer.download.download_unlock_seconds')) ?>.replace('%s', download_unlock_seconds_remaining);

                download_unlock_seconds_remaining -= 1;

                if(download_unlock_seconds_remaining < 0) {
                    clearInterval(countdown);
                    document.querySelector('#download_unlock_seconds').classList.add('d-none');
                    document.querySelector('button[type="submit"]').removeAttribute('disabled');
                }
            }, 1000);
        }
    </script>
    <?php \Altum\Event::add_content(ob_get_clean(), 'javascript') ?>
<?php endif ?>
