<?php
namespace Altum\Controllers;

use Altum\Alerts;
use Altum\Uploads;
use Aws\Exception\AwsException;
use Aws\S3\S3Client;

defined('ALTUMCODE') || die();

class Preview extends Controller {

    public function index() {
        $file_uuid = isset($this->params[0]) ? hex2bin($this->params[0]) : null;

        /* Get the file */
        if(!$file = db()->where('file_uuid', $file_uuid)->getOne('files')) {
            redirect();
        }

        /* Get transfer details */
        if(!$transfer = db()->where('transfer_id', $file->transfer_id)->getOne('transfers')) {
            redirect();
        }
        $transfer->settings = json_decode($transfer->settings ?? '');

        /* Make sure the current user has access */
        if(
            !$transfer->settings->file_preview_is_enabled
            && ($transfer->uploader_id != md5(get_ip()))
            && (!$transfer->user_id || $transfer->user_id != $this->user->user_id)
        ) {
            redirect();
        }

        /* Make sure the file is previeable */
        $file_extension = explode('.', $file->name);
        $file_extension = end($file_extension);
        if(!in_array($file_extension, explode(',', settings()->transfers->preview_file_extensions))) {
            redirect();
        }

        /* Determine file path and source */
        $is_offloaded = \Altum\Plugin::is_active('offload') && settings()->offload->uploads_url;
        $file_path = UPLOADS_PATH . 'files/' . $file->name;
        $file_source = null;

        if(!$is_offloaded) {
            /* Local storage */
            if(!file_exists($file_path)) {
                redirect();
            }

            $file_source = @fopen($file_path, 'rb');
            $mime_type = mime_content_type($file_path) ?: 'application/octet-stream';
        } else {

            /* Amazon S3 storage */
            try {
                $s3 = new S3Client(get_aws_s3_config());
                $s3->registerStreamWrapper();
                $s3_path = 's3://' . settings()->offload->storage_name . '/' . UPLOADS_URL_PATH . Uploads::get_path('files') . $file->name;

                if(!file_exists($s3_path)) {
                    redirect();
                }
                $file_source = @fopen($s3_path, 'rb');

                /* Get the MIME type from S3 */
                $headObject = $s3->headObject([
                    'Bucket' => settings()->offload->storage_name,
                    'Key'    => UPLOADS_URL_PATH . Uploads::get_path('files') . $file->name
                ]);
                $mime_type = $headObject['ContentType'] ?? 'application/octet-stream';
            } catch (AwsException $exception) {
                Alerts::add_error($exception->getMessage());
                redirect();
            }

        }

        /* Set headers for file preview */
        header('Content-Type: ' . $mime_type);
        header('Content-Disposition: inline; filename="' . $file->original_name . '"');
        header('Cache-Control: public, max-age=3600');

        /* Stream the file */
        while ($buffer = fread($file_source, 5000 * 16)) {
            echo $buffer;
            flush();
        }

        /* Close file handle */
        fclose($file_source);
        die();
    }
}
