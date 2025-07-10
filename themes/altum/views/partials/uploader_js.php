<?php defined('ALTUMCODE') || die() ?>

<?php ob_start() ?>
<script src="<?= ASSETS_FULL_URL . 'js/altum_uploader.js?v=' . PRODUCT_CODE ?>"></script>

<script>
    'use strict';

    if(document.querySelector('#upload_select_files')) {
        let altum_uploader = new AltumUploader({
            upload_button_selector: '#upload_select_files',
            upload_folder_button_selector: '#upload_select_folders',
            upload_area_selector: '#upload_main_dropzone',

            parallel_file_uploading: <?= json_encode((bool) settings()->transfers->parallel_file_uploading) ?>,
            auto_upload: <?= json_encode((bool) $this->user->preferences->transfers_auto_file_upload) ?>,
            auto_form_submission: <?= json_encode($this->user->preferences->transfers_auto_transfer_create ? '#upload_form' : false) ?>,
            upload_file_endpoint_url: <?= json_encode(url('files/create_api')) ?>,
            upload_file_endpoint_params: {
                global_token
            },

            delete_file_endpoint_url: <?= json_encode(url('files/delete_api')) ?>,
            delete_file_endpoint_params: {
                global_token
            },

            current_storage_size_usage: <?= $this->user->total_files_size ?? 0 ?>,
            storage_size_limit: <?= $this->user->plan_settings->storage_size_limit == -1 ? $this->user->plan_settings->storage_size_limit : $this->user->plan_settings->storage_size_limit * 1000 * 1000 ?>,

            blacklisted_file_extensions: <?= json_encode(settings()->transfers->blacklisted_file_extensions) ?>,
            files_per_transfer_limit: <?= (int) $this->user->plan_settings->files_per_transfer_limit ?>,
            transfer_size_limit: <?= (int) $this->user->plan_settings->transfer_size_limit == -1 ? $this->user->plan_settings->transfer_size_limit : $this->user->plan_settings->transfer_size_limit * 1000 * 1000 ?>,

            upload_in_chunks: true,
            chunk_size_limit: <?= settings()->transfers->chunk_size_limit * 1000 * 1000 ?>,

            translations: {
                blacklisted_file_extension: <?= json_encode(l('global.error_message.invalid_file_type')) ?>,
                files_per_transfer_limit: <?= json_encode(l('transfer.error_message.files_per_transfer_limit')) ?>,
                transfer_size_limit: <?= json_encode(l('transfer.error_message.transfer_size_limit')) ?>,
                storage_size_limit: <?= json_encode(l('transfer.error_message.storage_size_limit')) ?>,
            }
        });

        /* Add new file event */
        altum_uploader.on('added_file', file => {
            /* Display upload previews */
            document.querySelector('#upload_previews_wrapper').classList.remove('d-none');

            /* Clone file template */
            let clone = document.querySelector(`#upload_preview_template`).content.cloneNode(true);
            clone.querySelector('[data-altum-uuid]').setAttribute('data-altum-uuid', file.altum.uuid);
            clone.querySelector('[data-altum-name]').innerText = file.name;
            clone.querySelector('[data-altum-name]').title = file.name;
            clone.querySelector('[data-altum-size]').innerText = altum_uploader.get_formatted_bytes(file.size);

            /* Add remove handler */
            clone.querySelector('[data-altum-remove]').addEventListener('click', event => {
                let uuid = event.currentTarget.closest('[data-altum-uuid]').getAttribute('data-altum-uuid');
                altum_uploader.remove_file(uuid);
            })

            /* Add the new div */
            document.querySelector('#upload_previews').appendChild(clone);

            /* Add hidden input */
            let new_input = document.createElement('input');
            new_input.setAttribute('type', 'hidden');
            new_input.setAttribute('name', 'uploaded_files[]');
            new_input.setAttribute('value', file.altum.uuid);
            document.querySelector('#upload_form').appendChild(new_input);

            /* Total stats */
            display_total_stats();
        });

        /* Removed file event */
        altum_uploader.on('removed_file', file => {
            /* Remove preview div */
            document.querySelector(`[data-altum-uuid="${file.altum.uuid}"]`).remove();

            /* Remove form input */
            document.querySelector(`#upload_form input[value="${file.altum.uuid}"]`).remove();

            /* Total stats */
            display_total_stats();

            /* Don't display upload previews */
            if(!altum_uploader.extra.total_files) {
                document.querySelector('#upload_previews_wrapper').classList.add('d-none');
            }
        });

        /* Display upload progress for file */
        altum_uploader.on('uploading_file', file => {
            setTimeout(() => {
                let upload_progress = document.querySelector(`[data-altum-uuid="${file.altum.uuid}"] [data-altum-upload-progress]`);

                if(upload_progress) {
                    upload_progress.style.width = `${file.altum.upload_progress}%`;
                    upload_progress.innerHTML = `${file.altum.upload_progress}%`;
                    upload_progress.title = `${file.altum.upload_progress}%`;

                    /* Change and display total progress */
                    let total_upload_progress = document.querySelector(`#total_upload_progress`);
                    if(total_upload_progress) total_upload_progress.innerText = `${altum_uploader.extra.total_upload_progress}%`;

                    /* Finished uploading */
                    if(file.altum.upload_progress == 100) {
                        setTimeout(() => {
                            upload_progress.classList.add('bg-success');
                        }, 1000)
                    }
                }
            }, 150);
        });

        /* Upload file error */
        altum_uploader.on('upload_file_error', data => {
            let basic_error = <?= json_encode(l('global.error_message.file_upload')) ?>;
            let error = data?.response_data?.errors[0].title || basic_error;
            alert(error);
        });

        /* Form submit start */
        let form_submit_elements_processing = () => {
            /* Do not allow settings changes or adding more files */
            document.querySelector('#upload_previews_settings').classList.add('container-disabled');
            document.querySelector('#upload_select_files').classList.add('container-disabled');

            /* Modify the submit button */
            let submit_element = document.querySelector('#upload_form').querySelector('[type="submit"][name="submit"]');

            /* Disable the button */
            submit_element.setAttribute('disabled', 'disabled');

            /* Save the current button text */
            submit_element.setAttribute('data-inner-text', btoa(unescape(encodeURIComponent(submit_element.innerHTML))));

            /* Show a loading spinner instead of the text */
            submit_element.innerHTML = '<div class="spinner-border spinner-border-sm" role="status"></div> <span class="ml-2" id="total_upload_progress">0%</span>';
        }

        let form_submit_elements_reset = () => {
            document.querySelector('#upload_previews_settings').classList.remove('container-disabled');
            document.querySelector('#upload_select_files').classList.remove('container-disabled');

            /* Modify the submit button */
            let submit_element = document.querySelector('#upload_form').querySelector('[type="submit"][name="submit"]');

            /* Enable the button */
            submit_element.removeAttribute('disabled');

            /* Show the original button text */
            submit_element.innerHTML = decodeURIComponent(escape(atob(submit_element.getAttribute('data-inner-text'))));
        }

        /* Form submission */
        document.querySelector('#upload_form').addEventListener('submit', async event => {
            event.preventDefault();

            form_submit_elements_processing();

            /* Start the upload process */
            let upload_files = await altum_uploader.upload_files();

            if(!upload_files) {
                form_submit_elements_reset();
                return;
            }

            setTimeout(async () => {
                /* Prepare form data */
                let form = new FormData(document.querySelector('#upload_form'));

                /* Send request to server */
                let response = await fetch(`${url}transfer/create_api`, {
                    method: 'post',
                    body: form
                });

                let data = null;
                try {
                    data = await response.json();
                } catch (error) { /* :) */ }

                if(!response.ok) {
                    alert(data?.errors[0].title);
                } else {
                    /* Redirect */
                    redirect(`transfer/${data?.data.id}`);
                }

                form_submit_elements_reset();

            }, 500);
        });

        /* Helper functions */
        let display_total_stats = () => {
            let total_files = altum_uploader.extra.total_files;
            let files_per_transfer_limit = altum_uploader.options.files_per_transfer_limit == -1 ? '∞' : altum_uploader.options.files_per_transfer_limit;
            let transfer_size_limit = altum_uploader.options.transfer_size_limit == -1 ? '∞' : altum_uploader.options.transfer_size_limit;

            let text_class = total_files == files_per_transfer_limit ? `text-danger` : null;

            document.querySelector('#upload_total_files').innerHTML =
                '<i class="fas fa-fw fa-sm fa-copy"></i> <span class="ml-2 ' + text_class + '">' + <?= json_encode(l('transfer.files')) ?>.replace('%1$s', total_files).replace('%2$s', files_per_transfer_limit) + '</span>';

            document.querySelector('#upload_total_size').innerHTML = altum_uploader.get_formatted_bytes(altum_uploader.extra.total_size) + ' / ' + altum_uploader.get_formatted_bytes(transfer_size_limit);
        }

        /* Remove all handler */
        document.querySelector('#upload_remove_all').addEventListener('click', event => {
            altum_uploader.remove_files();
        })

        /* Set drag over active class */
        document.querySelector('#upload_main_dropzone').addEventListener('dragover', event => {
            document.querySelector('#upload_main_dropzone').classList.add('upload-drag-over-active');
        });

        /* Remove drag over active class */
        ['drop', 'dragleave'].forEach(event_type => document.querySelector('#upload_main_dropzone').addEventListener(event_type, event => {
            document.querySelector('#upload_main_dropzone').classList.remove('upload-drag-over-active');
        }));

        /* Set password to the altum uploader */
        document.querySelector('#password').addEventListener('change', event => {
            altum_uploader.options.upload_file_endpoint_params.password = event.currentTarget.value;

            file_encryption_handler();
        });

        /* Check if we allow the user to encrypt the files or not */
        let file_encryption_handler = () => {
            if(document.querySelector('#password').value.trim() != '' && !document.querySelector('#file_encryption_is_enabled').hasAttribute('data-plan-feature-no-access')) {
                document.querySelector('#file_encryption_is_enabled').removeAttribute('disabled');
            } else {
                let file_encryption_is_enabled = document.querySelector('#file_encryption_is_enabled');
                file_encryption_is_enabled.checked = false;
                file_encryption_is_enabled.dispatchEvent(new Event('change'));
                file_encryption_is_enabled.setAttribute('disabled', 'disabled');
            }
        }

        document.querySelector('#file_encryption_is_enabled').addEventListener('change', event => {
            altum_uploader.options.upload_file_endpoint_params.file_encryption_is_enabled = event.currentTarget.checked ? 1 : 0;

            if(event.currentTarget.checked) {
                document.querySelector('#file_preview_is_enabled').checked = false;
                document.querySelector('#file_preview_is_enabled').setAttribute('disabled', 'disabled');

                document.querySelector('#gallery_file_preview_is_enabled').checked = false;
                document.querySelector('#gallery_file_preview_is_enabled').setAttribute('disabled', 'disabled');
            } else {
                document.querySelector('#file_preview_is_enabled').removeAttribute('disabled');
                document.querySelector('#gallery_file_preview_is_enabled').removeAttribute('disabled');
            }
        });

        file_encryption_handler();

        let data_type_handler = (type) => {
            document.querySelectorAll(`[data-type]:not([data-type="${type}"])`).forEach(element => {
                element.classList.add('d-none');
                let input = element.querySelector('input,select,textarea');

                if(input) {
                    input.setAttribute('disabled', 'disabled');
                    if(input.getAttribute('required')) {
                        input.setAttribute('data-is-required', 'true');
                    }
                    input.removeAttribute('required');
                }
            });

            document.querySelectorAll(`[data-type="${type}"]`).forEach(element => {
                element.classList.remove('d-none');
                let input = element.querySelector('input,select,textarea');

                if(input) {
                    input.removeAttribute('disabled');
                    if(input.getAttribute('data-is-required')) {
                        input.setAttribute('required', 'required')
                    }
                }
            });
        }

        /* Type handler */
        data_type_handler(document.querySelector('input[name="type"]:checked').value);

        document.querySelectorAll('input[name="type"]').forEach(element => {
            element.addEventListener('change', event => {
                data_type_handler(document.querySelector('input[name="type"]:checked').value);
            });
        })
    }
</script>
<?php \Altum\Event::add_content(ob_get_clean(), 'javascript') ?>


<?php ob_start() ?>
<link href="<?= ASSETS_FULL_URL . 'css/libraries/daterangepicker.min.css?v=' . PRODUCT_CODE ?>" rel="stylesheet" media="screen,print">
<?php \Altum\Event::add_content(ob_get_clean(), 'head') ?>

<?php ob_start() ?>
<script src="<?= ASSETS_FULL_URL . 'js/libraries/moment.min.js?v=' . PRODUCT_CODE ?>"></script>
<script src="<?= ASSETS_FULL_URL . 'js/libraries/daterangepicker.min.js?v=' . PRODUCT_CODE ?>"></script>

<script>
    'use strict';

    /* Daterangepicker */
    let locale = <?= json_encode(require APP_PATH . 'includes/daterangepicker_translations.php') ?>;
    $('#expiration_datetime').daterangepicker({
        minDate: document.querySelector('#expiration_datetime').getAttribute('data-min-date'),
        maxDate: document.querySelector('#expiration_datetime').getAttribute('data-max-date'),
        alwaysShowCalendars: true,
        singleCalendar: true,
        singleDatePicker: true,
        locale: {...locale, format: 'YYYY-MM-DD HH:mm:ss'},
        timePicker: true,
        timePicker24Hour: true,
        timePickerSeconds: true,
    }, (start, end, label) => {
    });
</script>
<?php \Altum\Event::add_content(ob_get_clean(), 'javascript') ?>
