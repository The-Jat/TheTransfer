<?php
/*
 * Copyright (c) 2025 AltumCode (https://altumcode.com/)
 *
 * This software is licensed exclusively by AltumCode and is sold only via https://altumcode.com/.
 * Unauthorized distribution, modification, or use of this software without a valid license is not permitted and may be subject to applicable legal actions.
 *
 * 🌍 View all other existing AltumCode projects via https://altumcode.com/
 * 📧 Get in touch for support or general queries via https://altumcode.com/contact
 * 📤 Download the latest version via https://altumcode.com/downloads
 *
 * 🐦 X/Twitter: https://x.com/AltumCode
 * 📘 Facebook: https://facebook.com/altumcode
 * 📸 Instagram: https://instagram.com/altumcode
 */

namespace Altum\Controllers;

use Altum\Alerts;
use Altum\Response;
use Altum\Traits\Apiable;
use Altum\Uploads;

defined('ALTUMCODE') || die();

class Files extends Controller {
    use Apiable;

    public function index() {
        redirect('not-found');
    }

    public function create_api() {

        set_time_limit(0);

        if(empty($_POST)) {
            redirect();
        }

        /* Define the return content to be treated as JSON */
        header('Content-Type: application/json');

        /* Get potential API key */
        $api_key = \Altum\Authentication::get_authorization_bearer();

        /* Check for the plan limit */
        if(is_logged_in()) {
            // :)
        }

        /* API */
        elseif($api_key) {
            $this->user = db()->where('api_key', $api_key)->where('status', 1)->getOne('users');

            if(!$this->user) {
                $this->response_error(l('api.error_message.no_access'), 401);
            }

            $this->user->plan_settings = json_decode($this->user->plan_settings);

            if(!$this->user->plan_settings->api_is_enabled) {
                $this->response_error(l('api.error_message.no_access'), 401);
            }
        }

        /* Guest */
        else {
            if($this->user->plan_settings->transfers_limit == 0) {
                $this->response_error(l('global.info_message.plan_feature_limit'), 401);
            }
        }

        /* Check for required fields */
        $required_fields = ['uuid', 'chunk_index', 'total_chunks', 'file_name'];
        foreach($required_fields as $field) {
            if(!isset($_POST[$field])) {
                $this->response_error(l('global.error_message.empty_fields'), 401);
                break 1;
            }
        }

        if(empty($_FILES['file']['name'])) {
            $this->response_error(l('global.error_message.empty_fields'), 401);
        }

        // if(!$api_key) {
        //     if(!\Altum\Csrf::check('global_token')) {
        //         $this->response_error(l('global.error_message.invalid_csrf_token'), 401);
        //     }
        // }

        /* Filter some the variables */
        $_POST['uuid'] = preg_replace('/[^a-zA-Z0-9]/', '', $_POST['uuid'] ?? '');
        $_POST['uuid'] = hex2bin($_POST['uuid']);
        if(!$_POST['uuid']) {
            $_POST['uuid'] = str_replace('-', '', random_bytes(16));
        }
        $_POST['total_chunks'] = (int) $_POST['total_chunks'];
        $_POST['chunk_index'] = (int) $_POST['chunk_index'];
        $_POST['total_chunks'] = (int) $_POST['total_chunks'];
        $_POST['password'] = !empty($_POST['password']) && $this->user->plan_settings->password_protection_is_enabled ? $_POST['password'] : null;
        $_POST['file_encryption_is_enabled'] = $this->user->plan_settings->file_encryption_is_enabled ? (bool) ($_POST['file_encryption_is_enabled'] ?? false) : false;

        /* Uploaded file */
        $file_name = input_clean($_POST['file_name']);
        $file_extension = explode('.', $file_name);
        $file_extension = mb_strtolower(end($file_extension));
        $file_temp = $_FILES['file']['tmp_name'];

        /* Check for any errors */
        if($_FILES['file']['error'] == UPLOAD_ERR_INI_SIZE) {
            $this->response_error(sprintf(l('global.error_message.file_size_limit'), get_max_upload()), 401);
        }

        if($_FILES['file']['error'] && $_FILES['file']['error'] != UPLOAD_ERR_INI_SIZE) {
            $this->response_error(l('global.error_message.file_upload'), 401);
        }

        if(!strpos($file_name, '.')) {
            $this->response_error(l('global.error_message.invalid_file_type'), 401);
        }

        $blacklisted_file_extensions = explode(',', settings()->transfers->blacklisted_file_extensions);
        if(in_array($file_extension, $blacklisted_file_extensions)) {
            $this->response_error(l('global.error_message.invalid_file_type'), 401);
        }

        if(!\Altum\Plugin::is_active('offload') || (\Altum\Plugin::is_active('offload') && !settings()->offload->uploads_url)) {
            if(!is_writable(UPLOADS_PATH . Uploads::get_path('files'))) {
                $this->response_error(sprintf(l('global.error_message.directory_not_writable'), UPLOADS_PATH . Uploads::get_path('files')), 401);
            }
        }

        if($_FILES['file']['size'] > settings()->transfers->chunk_size_limit * 1000000) {
            $this->response_error(sprintf(l('global.error_message.file_size_limit'), settings()->transfers->chunk_size_limit), 401);
        }

        /* Get file details if any */
        $file = db()->where('file_uuid', $_POST['uuid'])->getOne('files');

        /* Create the file entry */
        if(!$file) {
            /* Generate random file name */
            $new_file_name = generate_readable_uuid() . '.' . $file_extension . '.temp';

            /* TODO: Add if/else checks for fopen fails */
            $file_destination = @fopen(Uploads::get_full_path('files') . $new_file_name, 'wb');
            $file_temp_source = @fopen($file_temp, 'rb');

            /* Upload the file without encryption */
            while($buffer = fread($file_temp_source, 5000 * 16)) {
                fwrite($file_destination, $buffer);
            }

            /* Close the file stream */
            fclose($file_destination);
            fclose($file_temp_source);

            $status = $_POST['total_chunks'] > 1 ? 'uploading' : 'uploaded';

            /* Database query */
            $file_id = db()->insert('files', [
                'file_uuid' => $_POST['uuid'],
                'uploader_id' => md5(get_ip()),
                'user_id' => $this->user->user_id ?? null,
                'name' => $new_file_name,
                'original_name' => $file_name,
                'size' => $_FILES['file']['size'],
                'status' => $_POST['total_chunks'] > 1 ? 'uploading' : 'uploaded',
                'is_encrypted' => (int) $_POST['file_encryption_is_enabled'],
                'datetime' => get_date(),
            ]);

        }

        else {
            /* Check if file is already finished */
            if($file->status == 'uploaded') {
                /* Prepare the data */
                $data = [
                    'id' => (int) $file->file_id,
                    'user_id' => (int) $file->user_id,
                    'transfer_id' => (int) $file->transfer_id,
                    'file_uuid' => bin2hex($file->file_uuid),
                    'uploader_id' => $file->uploader_id,
                    'name' => $file->name,
                    'original_name' => $file->original_name,
                    'size' => (int) $file->size,
                    'status' => $file->status,
                    'is_encrypted' => (bool) $file->is_encrypted,
                    'datetime' => $file->datetime,
                ];

                Response::jsonapi_success($data);
            }

            /* TODO: Add if/else checks for fopen fails */
            $file_destination = @fopen(Uploads::get_full_path('files') . $file->name, 'ab');
            $file_temp_source = @fopen($file_temp, 'rb');

            /* Upload the file without encryption */
            while($buffer = fread($file_temp_source, 5000 * 16)) {
                fwrite($file_destination, $buffer);
            }

            /* Close the file stream */
            fclose($file_destination);
            fclose($file_temp_source);

            /* Check file size against limit */
            if(filesize(Uploads::get_full_path('files') . $file->name) > $this->user->plan_settings->transfer_size_limit * 1000 * 1000 && $this->user->plan_settings->transfer_size_limit != -1) {
                $this->response_error(sprintf(l('global.error_message.file_size_limit'), $this->user->plan_settings->transfer_size_limit), 401);
            }
        }

        if(($_POST['chunk_index']+1) == $_POST['total_chunks']) {
            $current_file_name = $file ? $file->name : $new_file_name;
            $new_file_name = str_replace('.temp', '', $current_file_name);
            $file_id = $file_id ?? $file->file_id;

            /* Check file size against limit */
            if(filesize(Uploads::get_full_path('files') . $current_file_name) > $this->user->plan_settings->transfer_size_limit * 1000 * 1000 && $this->user->plan_settings->transfer_size_limit != -1) {
                $this->response_error(sprintf(l('global.error_message.file_size_limit'), $this->user->plan_settings->transfer_size_limit), 401);
            }

            /* Encrypt file if needed */
            if($_POST['password'] && $_POST['file_encryption_is_enabled']) {
                /* Encrypt */
                encrypt_file(Uploads::get_full_path('files') . $current_file_name, Uploads::get_full_path('files') . $new_file_name, $_POST['password']);

                /* Delete old file */
                unlink(Uploads::get_full_path('files') . $current_file_name);
            } else {

                /* Rename file */
                rename(Uploads::get_full_path('files') . $current_file_name, Uploads::get_full_path('files') . $new_file_name);
            }

            /* Get full file size after upload */
            $file_size = filesize(Uploads::get_full_path('files') . $new_file_name);

            /* Upload to external storage if needed */
            if(\Altum\Plugin::is_active('offload') && settings()->offload->uploads_url) {
                try {
                    $s3 = new \Aws\S3\S3Client(get_aws_s3_config());

                    /* Upload */
                    $uploader = new \Aws\S3\MultipartUploader($s3, Uploads::get_full_path('files') . $new_file_name, [
                        'Bucket' => settings()->offload->storage_name,
                        'Key' => UPLOADS_URL_PATH . Uploads::get_path('files') . $new_file_name,
                    ]);

                    /* Upload */
                    $uploader->upload();
                } catch (\Exception $exception) {
                    $this->response_error($exception->getMessage(), 401);
                }

                /* Delete the local file */
                unlink(Uploads::get_full_path('files') . $new_file_name);
            }

            $status = 'uploaded';

            /* Database query */
            db()->where('file_id', $file_id)->update('files', [
                'name' => $new_file_name,
                'status' => $status,
                'size' => $file_size,
            ]);

        }

        /* Prepare the data */
        $data = [
            'id' => (int) $file_id,
            'user_id' => $this->user->user_id ? (int) $this->user->user_id : null,
            'transfer_id' => null,
            'file_uuid' => bin2hex($_POST['uuid']),
            'uploader_id' => md5(get_ip()),
            'name' => $new_file_name,
            'original_name' => $file_name,
            'size' => (int) $_FILES['file']['size'] ?? $file_size,
            'status' => $status ?? $file->status,
            'is_encrypted' => (bool) (int) $_POST['file_encryption_is_enabled'],
            'datetime' => get_date(),
        ];

        Response::jsonapi_success($data);
    }

    public function delete_api() {

        if(empty($_POST)) {
            redirect();
        }

        /* Check for required fields */
        $required_fields = ['uuid'];
        foreach($required_fields as $field) {
            if(!isset($_POST[$field])) {
                $this->response_error(l('global.error_message.empty_fields'), 401);
                break 1;
            }
        }

        if(!\Altum\Csrf::check('global_token')) {
            $this->response_error(l('global.error_message.invalid_csrf_token'), 401);
        }

        if(!$file = db()->where('file_uuid', $_POST['uuid'])->getOne('files')) {
            //$this->response_error(l('global.error_message.basic'), 401);
            // File was most likely not yet uploaded, skip */
            die();
        }

        if($file->transfer_id || $file->uploader_id != md5(get_ip())) {
            $this->response_error(l('global.error_message.basic'), 401);
        }

        /* Delete uploaded file */
        Uploads::delete_uploaded_file($file->name, 'files');

        /* Delete the resource */
        db()->where('file_id', $file->file_id)->delete('files');

        die();

    }

    public function delete() {

        /* Team checks */
        if(\Altum\Teams::is_delegated() && !\Altum\Teams::has_access('delete.transfers')) {
            Alerts::add_info(l('global.info_message.team_no_access'));
            redirect('transfers');
        }

        if(empty($_POST)) {
            redirect('transfers');
        }

        //ALTUMCODE:DEMO if(DEMO) if($this->user->user_id == 1) Alerts::add_error('Please create an account on the demo to test out this function.');

        if(!\Altum\Csrf::check()) {
            Alerts::add_error(l('global.error_message.invalid_csrf_token'));
            redirect();
        }

        $file_id = (int) $_POST['file_id'];

        /* Get file details */
        if(!$file = db()->where('file_id', $file_id)->getOne('files', ['file_id', 'transfer_id', 'name', 'original_name'])) {
            redirect();
        }

        /* Make sure the current user has access */
        if(($file->uploader_id != md5(get_ip()) && ($file->user_id && $file->user_id != $this->user->user_id))) {
            redirect();
        }

        if(!Alerts::has_field_errors() && !Alerts::has_errors()) {

            /* Delete uploaded file */
            Uploads::delete_uploaded_file($file->name, 'files');

            /* Delete the resource */
            db()->where('file_id', $file->file_id)->delete('files');

            /* Set a nice success message */
            Alerts::add_success(sprintf(l('global.success_message.delete1'), '<strong>' . $file->original_name . '</strong>'));

            /* Clear the cache */
            cache()->deleteItem('files?transfer_id=' . $file->transfer_id);

            redirect('transfer/' . $file->transfer_id);

        }

        redirect();
    }

}
