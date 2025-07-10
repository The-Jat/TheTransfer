<?php defined('ALTUMCODE') || die() ?>


<div class="container">
    <?= \Altum\Alerts::output_alerts() ?>

    <?php if(settings()->main->breadcrumbs_is_enabled): ?>
        <nav aria-label="breadcrumb">
            <ol class="custom-breadcrumbs small">
                <li>
                    <a href="<?= url('transfers') ?>"><?= l('transfers.breadcrumb') ?></a><i class="fas fa-fw fa-angle-right"></i>
                </li>
                <li class="active" aria-current="page"><?= l('transfer.breadcrumb') ?></li>
            </ol>
        </nav>
    <?php endif ?>

    <div class="d-flex justify-content-between align-items-center mb-2">
        <h1 class="h4 text-truncate mb-0"><i class="fas fa-fw fa-xs fa-paper-plane mr-1"></i> <?= $data->transfer->name ?></h1>

        <div class="d-flex align-items-center col-auto p-0">
            <div>
                <button
                        id="url_copy"
                        type="button"
                        class="btn btn-link text-secondary"
                        data-toggle="tooltip"
                        title="<?= l('global.clipboard_copy') ?>"
                        aria-label="<?= l('global.clipboard_copy') ?>"
                        data-copy="<?= l('global.clipboard_copy') ?>"
                        data-copied="<?= l('global.clipboard_copied') ?>"
                        data-clipboard-text="<?= $data->transfer->full_url ?>"
                >
                    <i class="fas fa-fw fa-sm fa-copy"></i>
                </button>
            </div>

            <div data-toggle="tooltip" title="<?= l('global.share') ?>" aria-label="<?= l('global.share') ?>">
                <button
                        id="share"
                        type="button"
                        class="btn btn-link text-secondary"
                        data-toggle="modal"
                        data-target="#share_modal"
                        data-url="<?= $data->transfer->full_url ?>"
                >
                    <i class="fas fa-fw fa-sm fa-share-alt"></i>
                </button>
            </div>

            <?= include_view(THEME_PATH . 'views/transfers/transfer_dropdown_button.php', ['id' => $data->transfer->transfer_id, 'resource_name' => $data->transfer->name]) ?>
        </div>
    </div>

    <p class="text-truncate">
        <a href="<?= $data->transfer->full_url ?>" target="_blank">
            <i class="fas fa-fw fa-sm fa-external-link-alt text-muted mr-1"></i> <?= remove_url_protocol_from_url($data->transfer->full_url) ?>
        </a>
    </p>

    <div class="row mt-3">
        <!-- Total Files -->
        <div class="col-12 col-sm-6 col-xl-3 p-3 position-relative text-truncate">
            <div class="card d-flex flex-row h-100 overflow-hidden">
                <div class="pl-3 d-flex flex-column justify-content-center">
                    <div class="p-2 rounded-2x index-widget-icon d-flex align-items-center justify-content-center bg-primary-50">
                        <i class="fas fa-fw fa-sm fa-copy text-primary"></i>
                    </div>
                </div>
                <div class="card-body text-truncate">
                    <?= sprintf(l('transfer.widget.total_files'), '<span class="h6">' . nr($data->files_stats['total_files']) . '</span>') ?>
                </div>
            </div>
        </div>

        <!-- Total Files Size -->
        <div class="col-12 col-sm-6 col-xl-3 p-3 position-relative text-truncate">
            <div class="card d-flex flex-row h-100 overflow-hidden">
                <div class="pl-3 d-flex flex-column justify-content-center">
                    <div class="p-2 rounded-2x index-widget-icon d-flex align-items-center justify-content-center bg-primary-50">
                        <i class="fas fa-fw fa-sm fa-hdd text-primary"></i>
                    </div>
                </div>
                <div class="card-body text-truncate">
                    <?= sprintf(l('transfer.widget.total_files_size'), '<span class="h6">' . get_formatted_bytes($data->files_stats['total_size']) . '</span>') ?>
                </div>
            </div>
        </div>

        <!-- Downloads Limit -->
        <div class="col-12 col-sm-6 col-xl-3 p-3 position-relative text-truncate">
            <div class="card d-flex flex-row h-100 overflow-hidden position-relative">
                <div class="pl-3 d-flex flex-column justify-content-center">
                    <div class="p-2 rounded-2x index-widget-icon d-flex align-items-center justify-content-center bg-primary-50">
                        <i class="fas fa-fw fa-sm fa-download text-primary"></i>
                    </div>
                </div>
                <div class="card-body text-truncate">
                    <a href="<?= url('transfer-downloads/' . $data->transfer->transfer_id) ?>" class="text-reset text-decoration-none stretched-link">
                        <?= (new \Altum\Models\Transfers())->get_downloads_limit_text($data->transfer->downloads, $data->transfer->downloads_limit) ?>
                    </a>
                </div>
            </div>
        </div>

        <!-- Pageviews -->
        <div class="col-12 col-sm-6 col-xl-3 p-3 position-relative text-truncate">
            <div class="card d-flex flex-row h-100 overflow-hidden position-relative">
                <div class="pl-3 d-flex flex-column justify-content-center">
                    <div class="p-2 rounded-2x index-widget-icon d-flex align-items-center justify-content-center bg-primary-50">
                        <i class="fas fa-fw fa-sm fa-chart-bar text-primary"></i>
                    </div>
                </div>
                <div class="card-body text-truncate">
                    <a href="<?= url('transfer-statistics/' . $data->transfer->transfer_id) ?>" class="text-reset text-decoration-none stretched-link">
                        <?= sprintf(l('transfer.widget.pageviews'), '<span class="h6">' . nr($data->transfer->pageviews) . '</span>') ?>
                    </a>
                </div>
            </div>
        </div>

        <!-- Transfer Type (Link / Email) -->
        <div class="col-12 col-sm-6 col-xl-3 p-3 position-relative text-truncate" data-toggle="tooltip" title="<?= $data->transfer->type == 'link' ? $data->transfer->url : $data->transfer->email_to ?>">
            <div class="card d-flex flex-row h-100 overflow-hidden">
                <div class="pl-3 d-flex flex-column justify-content-center">
                    <div class="p-2 rounded-2x index-widget-icon d-flex align-items-center justify-content-center bg-primary-50">
                        <?php if($data->transfer->type == 'link'): ?>
                            <i class="fas fa-fw fa-sm fa-link text-primary"></i>
                        <?php else: ?>
                            <i class="fas fa-fw fa-sm fa-envelope text-primary"></i>
                        <?php endif ?>
                    </div>
                </div>
                <div class="card-body text-truncate">
                    <?= l('transfer.type.' . $data->transfer->type) ?>
                </div>
            </div>
        </div>

        <!-- Expiration Date -->
        <div class="col-12 col-sm-6 col-xl-3 p-3 position-relative text-truncate">
            <div class="card d-flex flex-row h-100 overflow-hidden">
                <div class="pl-3 d-flex flex-column justify-content-center">
                    <div class="p-2 rounded-2x index-widget-icon d-flex align-items-center justify-content-center bg-primary-50">
                        <i class="fas fa-fw fa-sm fa-calendar text-primary"></i>
                    </div>
                </div>
                <div class="card-body text-truncate">
                    <?= (new \Altum\Models\Transfers())->get_expiration_datetime_text($data->transfer->expiration_datetime) ?>
                </div>
            </div>
        </div>

        <!-- Datetime (Created) -->
        <div class="col-12 col-sm-6 col-xl-3 p-3 position-relative text-truncate"
             data-toggle="tooltip"
             data-html="true"
             title="<?= sprintf(l('global.datetime_tooltip'), '<br />' . \Altum\Date::get($data->transfer->datetime, 2) . '<br /><small>' . \Altum\Date::get($data->transfer->datetime, 3) . '</small>' . '<br /><small>(' . \Altum\Date::get_timeago($data->transfer->datetime) . ')</small>') ?>">
            <div class="card d-flex flex-row h-100 overflow-hidden">
                <div class="pl-3 d-flex flex-column justify-content-center">
                    <div class="p-2 rounded-2x index-widget-icon d-flex align-items-center justify-content-center bg-primary-50">
                        <i class="fas fa-fw fa-sm fa-clock text-primary"></i>
                    </div>
                </div>
                <div class="card-body text-truncate">
                    <?= $data->transfer->datetime ? \Altum\Date::get_timeago($data->transfer->datetime) : '-' ?>
                </div>
            </div>
        </div>

        <!-- Datetime (Last) -->
        <div class="col-12 col-sm-6 col-xl-3 p-3 position-relative text-truncate"
             data-toggle="tooltip"
             data-html="true"
             title="<?= sprintf(l('global.last_datetime_tooltip'), ($data->transfer->last_datetime ? '<br />' . \Altum\Date::get($data->transfer->last_datetime, 2) . '<br /><small>' . \Altum\Date::get($data->transfer->last_datetime, 3) . '</small>' . '<br /><small>(' . \Altum\Date::get_timeago($data->transfer->last_datetime) . ')</small>' : '<br />-')) ?>">
            <div class="card d-flex flex-row h-100 overflow-hidden">
                <div class="pl-3 d-flex flex-column justify-content-center">
                    <div class="p-2 rounded-2x index-widget-icon d-flex align-items-center justify-content-center bg-primary-50">
                        <i class="fas fa-fw fa-sm fa-clock-rotate-left text-primary"></i>
                    </div>
                </div>
                <div class="card-body text-truncate">
                    <?= $data->transfer->last_datetime ? \Altum\Date::get_timeago($data->transfer->last_datetime) : '-' ?>
                </div>
            </div>
        </div>
    </div>

    <div class="my-5">
        <div class="d-flex align-items-center mb-3">
            <h2 class="small font-weight-bold text-uppercase text-muted mb-0 mr-3"><i class="fas fa-fw fa-sm fa-copy mr-1"></i> <?= l('transfer.table.files') ?></h2>

            <div class="flex-fill">
                <hr class="border-gray-100" />
            </div>
        </div>

        <?php if(count($data->files)): ?>
            <div class="table-responsive table-custom-container">
                <table class="table table-custom">
                    <thead>
                    <tr>
                        <th><?= l('global.name') ?></th>
                        <th><?= l('files.size') ?></th>
                        <th></th>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody>

                    <?php foreach($data->files as $row): ?>

                        <tr>
                            <td class="text-nowrap">
                                <span title="<?= $row->original_name ?>"><?= string_truncate($row->original_name, 32) ?></span>
                            </td>

                            <td class="text-nowrap">
                                <span class="badge badge-info"><?= get_formatted_bytes($row->size) ?></span>
                            </td>

                            <td class="text-nowrap">
                                <?php if($row->is_encrypted): ?>
                                    <span class="mr-2" data-toggle="tooltip" title="<?= l('transfers.file_encryption_is_enabled') . ': ' . l('global.yes') ?>">
                                        <i class="fas fa-fw fa-fingerprint text-primary"></i>
                                    </span>

                                    <span class="mr-2" data-toggle="tooltip" title="<?= l('transfers.file_preview_not_possible') ?>">
                                        <i class="fas fa-fw fa-eye-slash text-muted"></i>
                                    </span>
                                <?php else: ?>
                                    <span class="mr-2" data-toggle="tooltip" title="<?= l('transfers.file_encryption_is_enabled') . ': ' . l('global.no') ?>">
                                        <i class="fas fa-fw fa-fingerprint text-muted"></i>
                                    </span>

                                    <?php
                                    $file_extension = explode('.', $row->name);
                                    $file_extension = end($file_extension);
                                    ?>

                                    <?php if(in_array($file_extension, explode(',', settings()->transfers->preview_file_extensions))): ?>
                                        <a href="<?= url('preview/' . bin2hex($row->file_uuid)) ?>" target="_blank" class="mr-2" data-toggle="tooltip" title="<?= l('transfers.file_preview') ?>">
                                            <i class="fas fa-fw fa-eye text-primary"></i>
                                        </a>
                                    <?php else: ?>
                                        <span class="mr-2" data-toggle="tooltip" title="<?= l('transfers.file_preview_not_possible') ?>">
                                            <i class="fas fa-fw fa-eye-slash text-muted"></i>
                                        </span>
                                    <?php endif ?>
                                <?php endif ?>
                            </td>

                            <td>
                                <div class="d-flex justify-content-end">
                                    <?= include_view(THEME_PATH . 'views/files/file_dropdown_button.php', ['id' => $row->file_id, 'resource_name' => $row->original_name]) ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach ?>

                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <?= include_view(THEME_PATH . 'views/partials/no_data.php', [
                'filters_get' => $data->filters->get ?? [],
                'name' => 'transfer.files',
                'has_secondary_text' => false,
            ]); ?>
        <?php endif ?>

        <div class="my-5">
            <div class="d-flex align-items-center mb-3">
                <h2 class="small font-weight-bold text-uppercase text-muted mb-0 mr-3"><i class="fas fa-fw fa-sm fa-chart-bar mr-1"></i> <?= l('transfer.table.latest_statistics') ?></h2>

                <div class="flex-fill">
                    <hr class="border-gray-100" />
                </div>
            </div>

            <?php if(count($data->statistics)): ?>
                <div class="table-responsive table-custom-container">
                    <table class="table table-custom">
                        <thead>
                        <tr>
                            <th class="">
                                <div><?= l('global.country') ?></div>
                                <div><?= l('global.city') ?></div>
                            </th>
                            <th class=""><?= l('transfer_statistics.table.device') ?></th>
                            <th class="">
                                <div><?= l('transfer_statistics.table.os') ?></div>
                                <div><?= l('transfer_statistics.table.browser') ?></div>
                            </th>
                            <th class=""><?= l('transfer_statistics.table.referrer') ?></th>
                            <th class=""><?= l('global.datetime') ?></th>
                        </tr>
                        </thead>

                        <tbody>

                        <?php foreach($data->statistics as $row): ?>
                            <tr>
                                <td class="text-nowrap">
                                    <div class="d-flex align-items-center">
                                        <div class="table-image-wrapper mr-3">
                                            <img src="<?= ASSETS_FULL_URL . 'images/countries/' . ($row->country_code ? mb_strtolower($row->country_code) : 'unknown') . '.svg' ?>" class="img-fluid icon-favicon" />
                                        </div>

                                        <div class="d-flex flex-column">
                                            <span class=""><?= $row->country_code ? get_country_from_country_code($row->country_code) : l('global.unknown') ?></span>
                                            <span class="text-muted small"><?= $row->city_name ?? l('global.unknown') ?></span>
                                        </div>
                                    </div>
                                </td>

                                <td class="text-nowrap">
                                <span class="badge badge-light">
                                    <?= $row->device_type ? '<i class="fas fa-fw fa-sm fa-' . $row->device_type . ' mr-1"></i>' . l('global.device.' . $row->device_type) : l('global.unknown') ?>
                                </span>
                                </td>

                                <td class="text-nowrap">
                                    <div>
                                        <img src="<?= ASSETS_FULL_URL . 'images/os/' . os_name_to_os_key($row->os_name) . '.svg' ?>" class="img-fluid icon-favicon-small mr-1" />
                                        <span class="font-size-small"><?= $row->os_name ?: l('global.unknown') ?></span>
                                    </div>
                                    <div>
                                        <img src="<?= ASSETS_FULL_URL . 'images/browsers/' . browser_name_to_browser_key($row->browser_name) . '.svg' ?>" class="img-fluid icon-favicon-small mr-1" />
                                        <span class="font-size-small"><?= $row->browser_name ?: l('global.unknown') ?></span>
                                    </div>
                                </td>

                                <td class="text-nowrap">
                                    <?php if(!$row->referrer_host): ?>
                                        <span><?= l('transfer_statistics.referrer_direct') ?></span>
                                    <?php elseif($row->referrer_host == 'qr'): ?>
                                        <span><?= l('transfer_statistics.referrer_qr') ?></span>
                                    <?php else: ?>
                                        <img referrerpolicy="no-referrer" src="<?= get_favicon_url_from_domain($row->referrer_host) ?>" class="img-fluid icon-favicon mr-1" loading="lazy" />
                                        <a href="<?= url('transfer-statistics/' . $data->transfer->transfer_id . '?type=referrer_path&referrer_host=' . $row->referrer_host . '&start_date=' . $data->datetime['start_date'] . '&end_date=' . $data->datetime['end_date']) ?>" title="<?= $row->referrer_host ?>" class=""><?= $row->referrer_host ?></a>
                                        <a href="<?= 'https://' . $row->referrer_host ?>" target="_blank" rel="nofollow noopener" class="text-muted ml-1"><i class="fas fa-fw fa-xs fa-external-link-alt"></i></a>
                                    <?php endif ?>
                                </td>

                                <td class="text-nowrap">
                                    <span class="text-muted" data-toggle="tooltip" title="<?= \Altum\Date::get($row->datetime, 1) ?>"><?= \Altum\Date::get_timeago($row->datetime) ?></span>
                                </td>
                            </tr>
                        <?php endforeach ?>

                        <tr>
                            <td colspan="5">
                                <a href="<?= url('transfer-statistics/' . $data->transfer->transfer_id . '?type=entries') ?>" class="text-muted">
                                    <i class="fas fa-angle-right fa-sm fa-fw mr-1"></i> <?= l('global.view_more') ?>
                                </a>
                            </td>
                        </tr>

                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <?= include_view(THEME_PATH . 'views/partials/no_data.php', [
                    'filters_get' => $data->filters->get ?? [],
                    'name' => 'transfer.latest_statistics',
                    'has_secondary_text' => false,
                ]); ?>
            <?php endif ?>
        </div>

        <div class="my-5">
            <div class="d-flex align-items-center mb-3">
                <h2 class="small font-weight-bold text-uppercase text-muted mb-0 mr-3"><i class="fas fa-fw fa-sm fa-chart-bar mr-1"></i> <?= l('transfer.table.latest_downloads') ?></h2>

                <div class="flex-fill">
                    <hr class="border-gray-100" />
                </div>
            </div>

            <?php if(count($data->downloads)): ?>
                <div class="table-responsive table-custom-container">
                    <table class="table table-custom">
                        <thead>
                        <tr>
                            <th class="">
                                <div><?= l('global.country') ?></div>
                                <div><?= l('global.city') ?></div>
                            </th>
                            <th class=""><?= l('transfer_statistics.table.device') ?></th>
                            <th class="">
                                <div><?= l('transfer_statistics.table.os') ?></div>
                                <div><?= l('transfer_statistics.table.browser') ?></div>
                            </th>
                            <th class=""><?= l('transfer_statistics.table.referrer') ?></th>
                            <th class=""><?= l('global.datetime') ?></th>
                        </tr>
                        </thead>

                        <tbody>

                        <?php foreach($data->downloads as $row): ?>
                            <tr>
                                <td class="text-nowrap">
                                    <div class="d-flex align-items-center">
                                        <div class="table-image-wrapper mr-3">
                                            <img src="<?= ASSETS_FULL_URL . 'images/countries/' . ($row->country_code ? mb_strtolower($row->country_code) : 'unknown') . '.svg' ?>" class="img-fluid icon-favicon" />
                                        </div>

                                        <div class="d-flex flex-column">
                                            <span class=""><?= $row->country_code ? get_country_from_country_code($row->country_code) : l('global.unknown') ?></span>
                                            <span class="text-muted small"><?= $row->city_name ?? l('global.unknown') ?></span>
                                        </div>
                                    </div>
                                </td>

                                <td class="text-nowrap">
                        <span class="badge badge-light">
                            <?= $row->device_type ? '<i class="fas fa-fw fa-sm fa-' . $row->device_type . ' mr-1"></i>' . l('global.device.' . $row->device_type) : l('global.unknown') ?>
                        </span>
                                </td>

                                <td class="text-nowrap">
                                    <div>
                                        <img src="<?= ASSETS_FULL_URL . 'images/os/' . os_name_to_os_key($row->os_name) . '.svg' ?>" class="img-fluid icon-favicon-small mr-1" />
                                        <span class="font-size-small"><?= $row->os_name ?: l('global.unknown') ?></span>
                                    </div>
                                    <div>
                                        <img src="<?= ASSETS_FULL_URL . 'images/browsers/' . browser_name_to_browser_key($row->browser_name) . '.svg' ?>" class="img-fluid icon-favicon-small mr-1" />
                                        <span class="font-size-small"><?= $row->browser_name ?: l('global.unknown') ?></span>
                                    </div>
                                </td>

                                <td class="text-nowrap">
                                    <?php if(!$row->referrer_host): ?>
                                        <span><?= l('transfer_statistics.referrer_direct') ?></span>
                                    <?php elseif($row->referrer_host == 'qr'): ?>
                                        <span><?= l('transfer_statistics.referrer_qr') ?></span>
                                    <?php else: ?>
                                        <img referrerpolicy="no-referrer" src="<?= get_favicon_url_from_domain($row->referrer_host) ?>" class="img-fluid icon-favicon mr-1" loading="lazy" />
                                        <a href="<?= url('transfer-statistics/' . $data->transfer->transfer_id . '?type=referrer_path&referrer_host=' . $row->referrer_host . '&start_date=' . $data->datetime['start_date'] . '&end_date=' . $data->datetime['end_date']) ?>" title="<?= $row->referrer_host ?>" class=""><?= $row->referrer_host ?></a>
                                        <a href="<?= 'https://' . $row->referrer_host ?>" target="_blank" rel="nofollow noopener" class="text-muted ml-1"><i class="fas fa-fw fa-xs fa-external-link-alt"></i></a>
                                    <?php endif ?>
                                </td>

                                <td class="text-nowrap">
                                    <span class="text-muted" data-toggle="tooltip" title="<?= \Altum\Date::get($row->datetime, 1) ?>"><?= \Altum\Date::get_timeago($row->datetime) ?></span>
                                </td>
                            </tr>
                        <?php endforeach ?>

                        <tr>
                            <td colspan="5">
                                <a href="<?= url('transfer-statistics/' . $data->transfer->transfer_id . '?type=entries') ?>" class="text-muted">
                                    <i class="fas fa-angle-right fa-sm fa-fw mr-1"></i> <?= l('global.view_more') ?>
                                </a>
                            </td>
                        </tr>

                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <?= include_view(THEME_PATH . 'views/partials/no_data.php', [
                    'filters_get' => $data->filters->get ?? [],
                    'name' => 'transfer.latest_downloads',
                    'has_secondary_text' => false,
                ]); ?>
            <?php endif ?>
        </div>

    </div>



    <?php \Altum\Event::add_content(include_view(THEME_PATH . 'views/partials/universal_delete_modal_form.php', [
        'name' => 'file',
        'resource_id' => 'file_id',
        'has_dynamic_resource_name' => true,
        'path' => 'files/delete'
    ]), 'modals'); ?>

    <?php include_view(THEME_PATH . 'views/partials/clipboard_js.php') ?>
    <?php include_view(THEME_PATH . 'views/partials/share_modal_js.php') ?>
