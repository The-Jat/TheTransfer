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
use Altum\Captcha;
use Altum\Meta;
use Altum\Models\User;
use Altum\Title;
use Altum\Uploads;

defined('ALTUMCODE') || die();

class Transfer extends Controller {
    public $transfer = null;
    public $transfer_user = null;
    public $files = null;
    public $files_stats = [
        'total_size' => 0,
        'total_files' => 0,
    ];

    public function index() {

        /* Set the transfer resource from the router */
        $this->transfer = \Altum\Router::$data['transfer'];

        if(!$this->transfer) redirect('not-found');

        /* Parse some details */
        foreach(['settings', 'pixels_ids', 'notifications'] as $key) {
            $this->transfer->{$key} = json_decode($this->transfer->{$key} ?? '');
        }

        /* Check for expiration */
        if($this->transfer->downloads_limit && $this->transfer->downloads >= $this->transfer->downloads_limit) {
            Alerts::add_info(l('t_transfer.expired.info_message'));
            redirect('not-found');
        }

        if($this->transfer->expiration_datetime && (new \DateTime()) >= (new \DateTime($this->transfer->expiration_datetime))) {
            Alerts::add_info(l('t_transfer.expired.info_message'));
            redirect('not-found');
        }

        /* Initiate captcha */
        $captcha = new Captcha();

        /* Check if the user has access to the link */
        $has_access =
            !$this->transfer->settings->password ||
            (
                $this->transfer->settings->password
                && isset($_COOKIE['transfer_password_' . $this->transfer->transfer_id])
                && $_COOKIE['transfer_password_' . $this->transfer->transfer_id] == $this->transfer->settings->password
                && isset($_SESSION['transfer_password_' . $this->transfer->transfer_id])
            );

        if($this->transfer->user_id) {
            $this->transfer_user = (new User())->get_user_by_user_id($this->transfer->user_id);

            /* Make sure to check if the user is active */
            if($this->transfer_user->status != 1) {
                redirect('not-found');
            }

            /* Process the plan of the user */
            (new User())->process_user_plan_expiration_by_user($this->transfer_user);

            /* Do not let the user have password protection if the plan doesn't allow it */
            if(!$this->transfer_user->plan_settings->password_protection_is_enabled) {
                $has_access = true;
            }

            /* Set the default language of the user, including the link timezone */
            \Altum\Language::set_by_name($this->transfer_user->language);

            /* White label */
            if(settings()->main->white_labeling_is_enabled && $this->transfer_user->plan_settings->white_labeling_is_enabled && \Altum\Router::$controller_key != 'invoice' && \Altum\Router::$path != 'admin') {
                if($this->transfer_user->preferences->white_label_title) {
                    settings()->main->title = $this->transfer_user->preferences->white_label_title;
                    Title::initialize(settings()->main->title);
                }

                if($this->transfer_user->preferences->white_label_logo_light) {
                    settings()->main->logo_light = $this->transfer_user->preferences->white_label_logo_light;
                    settings()->main->logo_light_full_url = \Altum\Uploads::get_full_url('users') . settings()->main->logo_light;
                }

                if($this->transfer_user->preferences->white_label_logo_dark) {
                    settings()->main->logo_dark = $this->transfer_user->preferences->white_label_logo_dark;
                    settings()->main->logo_dark_full_url = \Altum\Uploads::get_full_url('users') . settings()->main->logo_dark;
                }

                if($this->transfer_user->preferences->white_label_favicon) {
                    settings()->main->favicon = $this->transfer_user->preferences->white_label_favicon;
                    settings()->main->favicon_full_url = \Altum\Uploads::get_full_url('users') . settings()->main->favicon;
                }
            }
        }

        /* Check if the password form is submitted */
        if(!$has_access && !empty($_POST) && isset($_POST['type']) && $_POST['type'] == 'password') {
            /* Check for any errors */
            if(!\Altum\Csrf::check()) {
                Alerts::add_error(l('global.error_message.invalid_csrf_token'));
            }

            if(!password_verify($_POST['password'], $this->transfer->settings->password)) {
                Alerts::add_field_error('password', l('t_transfer.password.error_message'));
            }

            if(!Alerts::has_field_errors() && !Alerts::has_errors()) {
                /* Set a cookie */
                setcookie('transfer_password_' . $this->transfer->transfer_id, $this->transfer->settings->password, time()+60*60*24*30);

                /* Set a session */
                $_SESSION['transfer_password_' . $this->transfer->transfer_id] = $_POST['password'];

                header('Location: ' . $this->transfer->full_url);

                die();
            }
        }

        /* Check for download form submission */
        if(!empty($_POST) && isset($_POST['type']) && $_POST['type'] == 'download') {
            /* Check for any errors */
            if(!\Altum\Csrf::check()) {
                Alerts::add_error(l('global.error_message.invalid_csrf_token'));
            }

            if(settings()->captcha->transfer_download_is_enabled && !$captcha->is_valid()) {
                Alerts::add_field_error('captcha', l('global.error_message.invalid_captcha'));
            }

            if(!Alerts::has_field_errors() && !Alerts::has_errors()) {
                $this->create_downloads();

                $this->process_files();

                /* Set no time limit for a download as it could take longer than the usual time limit of the server */
                set_time_limit(0);

                $this->process_notification_handlers();

                /* Clear the cache */
                cache()->deleteItemsByTag('transfer_id=' . $this->transfer->transfer_id);

                /* Detect if its just one file requested */
                $download_requested_file_id = isset($_POST['download']) && array_key_exists($_POST['download'], $this->files) ? (int) $_POST['download'] : null;

                /* If its just one file, simply download that */
                if($this->files_stats['total_files'] == 1 || $download_requested_file_id) {
                    $file = $download_requested_file_id ? $this->files[$download_requested_file_id] : reset($this->files);

                    /* Prepare headers */
                    header('Content-Description: File Transfer');
                    header('Content-Type: application/octet-stream');
                    header('Content-Disposition: attachment; filename="' . $file->original_name . '"');

                    /* Output file data to be downloaded */
                    if(!\Altum\Plugin::is_active('offload') || (\Altum\Plugin::is_active('offload') && !settings()->offload->uploads_url)) {

                        /* Make sure the file exists */
                        if(!file_exists(UPLOADS_PATH . 'files/' . $file->name)) {
                            redirect('not-found');
                        }

                        /* Local files */
                        $file_source = @fopen(UPLOADS_PATH . 'files/' . $file->name, 'rb');

                        if($file->is_encrypted) {
                            decrypt_and_output($file_source, $_SESSION['transfer_password_' . $this->transfer->transfer_id]);
                        } else {
                            while($buffer = fread($file_source, 5000 * 16)) {
                                echo $buffer;
                            }
                        }

                        /* Close the file stream */
                        fclose($file_source);
                    }

                    /* Offload storage */
                    else {
                        try {
                            $s3 = new \Aws\S3\S3Client(get_aws_s3_config());
                            $s3->registerStreamWrapper();
                        } catch (\Exception $exception) {
                            Alerts::add_error($exception->getMessage());
                            redirect('not-found');
                        }

                        /* Make sure the file exists */
                        if(!file_exists('s3://' .  settings()->offload->storage_name . '/' . UPLOADS_URL_PATH . Uploads::get_path('files') . $file->name)) {
                            redirect('not-found');
                        }

                        /* External files */
                        $file_source = @fopen('s3://' .  settings()->offload->storage_name . '/' . UPLOADS_URL_PATH . Uploads::get_path('files') . $file->name, 'rb');

                        if($file->is_encrypted) {
                            /* Download to a temp file */
                            $temp_file = tmpfile();
                            while($buffer = fread($file_source, 5000 * 16)) {
                                fwrite($temp_file, $buffer);
                            }
                            rewind($temp_file);

                            /* Decrypt the temp file & output */
                            decrypt_and_output($temp_file, $_SESSION['transfer_password_' . $this->transfer->transfer_id]);

                            /* Close the file stream */
                            fclose($temp_file);
                        } else {
                            while($buffer = fread($file_source, 5000 * 16)) {
                                echo $buffer;
                            }
                        }

                        /* Close the file stream */
                        fclose($file_source);
                    }

                    die();
                }

                /* Otherwise, zip them all up */
                else {
                    /* Create zipstream object */
                    $zip = new \ZipStream\ZipStream(
                        sendHttpHeaders: true,
                        outputName: get_slug($this->transfer->name) . '.zip'
                    );

                    /* Output file data to be downloaded */
                    if(!\Altum\Plugin::is_active('offload') || (\Altum\Plugin::is_active('offload') && !settings()->offload->uploads_url)) {

                        /* Add all files to the zip */
                        foreach($this->files as $file) {

                            /* Make sure the file exists */
                            if(!file_exists(UPLOADS_PATH . 'files/' . $file->name)) {
                                continue;
                            }

                            if($file->is_encrypted) {
                                /* Local files */
                                $file_source = @fopen(UPLOADS_PATH . 'files/' . $file->name, 'rb');

                                /* Decrypt into a temp file */
                                $temp_file = tmpfile();
                                decrypt_and_output($file_source, $_SESSION['transfer_password_' . $this->transfer->transfer_id], $temp_file);
                                rewind($temp_file);

                                /* Add temp file to the zip */
                                $zip->addFileFromStream($file->original_name, $temp_file);

                                /* Close the file stream */
                                fclose($temp_file);
                            } else {
                                $zip->addFileFromPath($file->original_name, UPLOADS_PATH . 'files/' . $file->name);
                            }
                        }
                    }

                    /* Offload storage */
                    else {

                        try {
                            $s3 = new \Aws\S3\S3Client(get_aws_s3_config());
                            $s3->registerStreamWrapper();
                        } catch (\Exception $exception) {
                            Alerts::add_error($exception->getMessage());
                            redirect('not-found');
                        }

                        /* Add all files to the zip */
                        foreach($this->files as $file) {

                            /* Local files */
                            $file_source = @fopen('s3://' .  settings()->offload->storage_name . '/' . UPLOADS_URL_PATH . Uploads::get_path('files') . $file->name, 'rb');

                            /* Download to a temp file */
                            $temp_file = tmpfile();
                            while($buffer = fread($file_source, 5000 * 16)) {
                                fwrite($temp_file, $buffer);
                            }
                            rewind($temp_file);

                            if($file->is_encrypted) {
                                /* Save unencrypted file to a new temp file */
                                $new_temp_file = tmpfile();

                                /* Decrypt the temp file */
                                decrypt_and_output($temp_file, $_SESSION['transfer_password_' . $this->transfer->transfer_id], $new_temp_file);

                                /* Add decrypted file to zip */
                                rewind($new_temp_file);
                                $zip->addFileFromStream($file->original_name, $new_temp_file);

                                /* Close the file stream */
                                fclose($new_temp_file);
                            } else {
                                $zip->addFileFromStream($file->original_name, $temp_file);
                            }

                            /* Close the file stream */
                            fclose($temp_file);
                            fclose($file_source);
                        }

                    }

                    /* Output file data to be downloaded */
                    $zip->finish();
                    die();
                }
            }
        }

        /* Display the password form */
        if(!$has_access) {
            /* Set a custom title */
            Title::set(l('t_transfer.password.title'));

            /* Main View */
            $data = [];

            $view = new \Altum\View('t/partials/password', (array) $this);
            $this->add_view_content('content', $view->run($data));
        }

        /* No password or access granted, display transfer details */
        else {

            $this->create_statistics();

            $this->process_pixels();

            $this->process_files();

            /* Set a custom title */
            Title::set(sprintf(l('t_transfer.download.title'), $this->transfer->name));
            Meta::set_canonical_url($this->transfer->full_url);

            /* Main View */
            $data = [
                'transfer' => $this->transfer,
                'transfer_user' => $this->transfer_user,
                'files' => $this->files,
                'files_stats' => $this->files_stats,
                'captcha' => $captcha,
            ];

            $view = new \Altum\View('t/partials/download', (array) $this);
            $this->add_view_content('content', $view->run($data));

        }

    }

    /* Get all files */
    private function process_files() {
        if($this->files) {
            return;
        }

        $this->files = (new \Altum\Models\Files())->get_files_by_transfer_id($this->transfer->transfer_id);

        foreach($this->files as $file) {
            $this->files_stats['total_size'] += $file->size;
            $this->files_stats['total_files']++;
        }
    }

    private function process_notification_handlers() {
        if(!$this->transfer->user_id) {
            return;
        }

        /* Get available notification handlers */
        $notification_handlers = (new \Altum\Models\NotificationHandlers())->get_notification_handlers_by_user_id($this->transfer->user_id);

        /* Core data to be sent to the new processor */
        $notification_data = [
            'transfer_id' => $this->transfer->transfer_id,
            'name'        => $this->transfer->name,
            'url'         => url('transfer/' . $this->transfer->transfer_id),
        ];

        /* Compose the generic notification text */
        $notification_message = sprintf(
            l('t_transfer.download.simple_notification', $this->transfer_user->language),
            $this->transfer->name,
            $notification_data['url']
        );

        /* Prepare the email template used by the email handler */
        $email_template = get_email_template(
            [
                '{{TRANSFER_NAME}}' => $this->transfer->name,
            ],
            l('global.emails.transfer_download.subject', $this->transfer_user->language),
            [
                '{{NAME}}'          => $this->transfer_user->name,
                '{{TRANSFER_LINK}}' => $notification_data['url'],
                '{{TRANSFER_NAME}}' => $this->transfer->name,
            ],
            l('global.emails.transfer_download.body', $this->transfer_user->language)
        );

        /* Build the context passed to the new NotificationHandlers class */
        $context = [
            /* User details */
            'user' => $this->transfer_user,

            /* Email */
            'email_template' => $email_template,

            /* Basic message for most integrations */
            'message' => $notification_message,

            /* Push notifications */
            'push_title'       => l('t_transfer.download.push_notification.title', $this->transfer_user->language),
            'push_description' => sprintf(
                l('t_transfer.download.push_notification.description', $this->transfer_user->language),
                $this->transfer->name
            ),

            /* Whatsapp */
            'whatsapp_template'   => 'transfer_downloaded',
            'whatsapp_parameters' => [
                $this->transfer->name,
                $notification_data['url'],
            ],

            /* Twilio call */
            'twilio_call_url' => SITE_URL .
                'twiml/t_transfer.download.simple_notification?param1=' .
                urlencode($this->transfer->name) .
                '&param2=' . urlencode($notification_data['url']),

            /* Internal notification */
            'internal_icon' => 'fas fa-download',

            /* Discord */
            'discord_color' => '2664261',

            /* Slack */
            'slack_emoji' => ':arrow_down:',
        ];

        /* Send notifications */
        \Altum\NotificationHandlers::process(
            $notification_handlers,
            $this->transfer->notifications->download,
            $notification_data,
            $context
        );
    }

    private function process_pixels() {
        if(count($this->transfer->pixels_ids ?? [])) {
            /* Get the needed pixels */
            $pixels = (new \Altum\Models\Pixel())->get_pixels_by_pixels_ids_and_user_id($this->transfer->pixels_ids, $this->transfer->user_id);

            /* Prepare the pixels view */
            $pixels_view = new \Altum\View('t/partials/pixels');
            $this->add_view_content('pixels', $pixels_view->run(['pixels' => $pixels]));
        }
    }

    /* Insert statistics log */
    private function create_statistics() {

        $cookie_name = 'transfer_statistics_' . $this->transfer->transfer_id;

        if(isset($_COOKIE[$cookie_name]) && (int) $_COOKIE[$cookie_name] >= 3) {
            return;
        }

        /* Add the unique hit to the transfers table */
        db()->where('transfer_id', $this->transfer->transfer_id)->update('transfers', ['pageviews' => db()->inc()]);

        /* Do not record advanced analytics if the plan does not allow */
        if(!$this->transfer_user || !$this->transfer_user->plan_settings->analytics_is_enabled) {
            return;
        }

        /* Detect extra details about the user */
        $whichbrowser = new \WhichBrowser\Parser($_SERVER['HTTP_USER_AGENT']);

        /* Do not track bots */
        if($whichbrowser->device->type == 'bot') {
            return;
        }

        /* Ignore excluded ips */
        $excluded_ips = array_flip($this->transfer_user->preferences->excluded_ips ?? []);
        if(isset($excluded_ips[get_ip()])) return;

        /* Detect extra details about the user */
        $browser_name = $whichbrowser->browser->name ?? null;
        $os_name = $whichbrowser->os->name ?? null;
        $browser_language = isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) ? mb_substr($_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 2) : null;
        $device_type = get_this_device_type();
        $is_unique = isset($_COOKIE[$cookie_name]) ? 0 : 1;

        /* Detect the location */
        try {
            $maxmind = (get_maxmind_reader_city())->get(get_ip());
        } catch(\Exception $exception) {
            /* :) */
        }
        $continent_code = isset($maxmind) && isset($maxmind['continent']) ? $maxmind['continent']['code'] : null;
        $country_code = isset($maxmind) && isset($maxmind['country']) ? $maxmind['country']['iso_code'] : null;
        $city_name = isset($maxmind) && isset($maxmind['city']) ? $maxmind['city']['names']['en'] : null;

        /* Process referrer */
        $referrer = isset($_SERVER['HTTP_REFERER']) ? parse_url($_SERVER['HTTP_REFERER']) : null;

        if(!isset($referrer)) {
            $referrer = [
                'host' => null,
                'path' => null
            ];
        }

        /* Check if the referrer comes from the same location */
        if(isset($referrer['host']) && $referrer['host'] == parse_url($this->transfer->full_url, PHP_URL_HOST)) {
            $is_unique = 0;

            $referrer = [
                'host' => null,
                'path' => null
            ];
        }

        $utm_source = input_clean($_GET['utm_source'] ?? null);
        $utm_medium = input_clean($_GET['utm_medium'] ?? null);
        $utm_campaign = input_clean($_GET['utm_campaign'] ?? null);

        /* Insert the log */
        db()->insert('statistics', [
            'transfer_id' => $this->transfer->transfer_id,
            'user_id' => $this->transfer_user->user_id,
            'continent_code' => $continent_code,
            'country_code' => $country_code,
            'city_name' => $city_name,
            'os_name' => $os_name,
            'browser_name' => $browser_name,
            'referrer_host' => $referrer['host'],
            'referrer_path' => $referrer['path'],
            'device_type' => $device_type,
            'browser_language' => $browser_language,
            'utm_source' => $utm_source,
            'utm_medium' => $utm_medium,
            'utm_campaign' => $utm_campaign,
            'is_unique' => $is_unique,
            'datetime' => get_date(),
        ]);

        /* Set cookie to try and avoid multiple entrances */
        $cookie_new_value = isset($_COOKIE[$cookie_name]) ? (int) $_COOKIE[$cookie_name] + 1 : 0;
        setcookie($cookie_name, (int) $cookie_new_value, time()+60*60*24*1);
    }

    /* Insert downloads log */
    private function create_downloads() {

        $cookie_name = 'transfer_downloads_' . $this->transfer->transfer_id;

        if(isset($_COOKIE[$cookie_name]) && (int) $_COOKIE[$cookie_name] >= 3) {
            return;
        }

        /* Add the unique hit to the transfers table */
        db()->where('transfer_id', $this->transfer->transfer_id)->update('transfers', ['downloads' => db()->inc()]);

        /* Do not record advanced analytics if the plan does not allow */
        if(!$this->transfer_user || !$this->transfer_user->plan_settings->analytics_is_enabled) {
            return;
        }

        /* Detect extra details about the user */
        $whichbrowser = new \WhichBrowser\Parser($_SERVER['HTTP_USER_AGENT']);

        /* Do not track bots */
        if($whichbrowser->device->type == 'bot') {
            return;
        }

        /* Detect extra details about the user */
        $browser_name = $whichbrowser->browser->name ?? null;
        $os_name = $whichbrowser->os->name ?? null;
        $browser_language = isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) ? mb_substr($_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 2) : null;
        $device_type = get_this_device_type();
        $is_unique = isset($_COOKIE[$cookie_name]) ? 0 : 1;

        /* Detect the location */
        try {
            $maxmind = (get_maxmind_reader_city())->get(get_ip());
        } catch(\Exception $exception) {
            /* :) */
        }
        $continent_code = isset($maxmind) && isset($maxmind['continent']) ? $maxmind['continent']['code'] : null;
        $country_code = isset($maxmind) && isset($maxmind['country']) ? $maxmind['country']['iso_code'] : null;
        $city_name = isset($maxmind) && isset($maxmind['city']) ? $maxmind['city']['names']['en'] : null;

        /* Process referrer */
        $referrer = [
            'host' => null,
            'path' => null
        ];

        if(isset($_SERVER['HTTP_REFERER'])) {
            $referrer = parse_url($_SERVER['HTTP_REFERER']);

            if($_SERVER['HTTP_REFERER'] == $this->transfer->full_url) {
                $is_unique = 0;

                $referrer = [
                    'host' => null,
                    'path' => null
                ];
            }
        }

        $utm_source = input_clean($_GET['utm_source'] ?? null);
        $utm_medium = input_clean($_GET['utm_medium'] ?? null);
        $utm_campaign = input_clean($_GET['utm_campaign'] ?? null);

        /* Insert the log */
        db()->insert('downloads', [
            'transfer_id' => $this->transfer->transfer_id,
            'user_id' => $this->transfer->user_id,
            'continent_code' => $continent_code,
            'country_code' => $country_code,
            'city_name' => $city_name,
            'os_name' => $os_name,
            'browser_name' => $browser_name,
            'referrer_host' => $referrer['host'],
            'referrer_path' => $referrer['path'],
            'device_type' => $device_type,
            'browser_language' => $browser_language,
            'utm_source' => $utm_source,
            'utm_medium' => $utm_medium,
            'utm_campaign' => $utm_campaign,
            'is_unique' => $is_unique,
            'datetime' => get_date(),
        ]);

        /* Set cookie to try and avoid multiple entrances */
        $cookie_new_value = isset($_COOKIE[$cookie_name]) ? (int) $_COOKIE[$cookie_name] + 1 : 0;
        setcookie($cookie_name, (int) $cookie_new_value, time()+60*60*24*1);
    }

}
