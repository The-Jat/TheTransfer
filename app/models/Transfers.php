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

namespace Altum\Models;

use Altum\Uploads;

defined('ALTUMCODE') || die();

class Transfers extends Model {

    public function get_transfer_full_url($transfer, $user, $domains = null) {

        /* Detect the URL of the link */
        if($transfer->domain_id) {

            /* Get available custom domains */
            if(!$domains) {
                $domains = (new \Altum\Models\Domain())->get_available_domains_by_user($user);
            }

            if(isset($domains[$transfer->domain_id])) {
                $transfer->full_url = $domains[$transfer->domain_id]->scheme . $domains[$transfer->domain_id]->host . '/' . $transfer->url . '/';
            }

        } else {

            $transfer->full_url = SITE_URL . $transfer->url . '/';

        }

        return $transfer->full_url;
    }

    public function delete($transfer_id, $user_id = null) {

        /* Get all files related to the transfer */
        $files_result = database()->query("
                SELECT `file_id`, `name`
                FROM `files`
                WHERE `transfer_id` = {$transfer_id}
            ");

        while($file = $files_result->fetch_object()) {

            /* Delete the stored file */
            Uploads::delete_uploaded_file($file->name, 'files');

            /* Delete the resource */
            db()->where('file_id', $file->file_id)->delete('files');

            /* Clear the cache */
            cache()->deleteItem('files?transfer_id=' . $transfer_id);
        }

        /* Delete the resource */
        db()->where('transfer_id', $transfer_id)->delete('transfers');

        if(!$user_id) {
            $user_id = db()->where('transfer_id', $transfer_id)->getValue('transfers', 'user_id');
        }

        /* Update the user */
        (new \Altum\Models\Files())->calculate_and_update_file_usage($user_id);

        /* Clear the cache */
        cache()->deleteItemsByTag('transfer_id=' . $transfer_id);
        cache()->deleteItem('transfers_total?user_id=' . $user_id);
    }

    public function get_expiration_datetime_text($expiration_datetime) {
        if(!$expiration_datetime) {
            return l('transfers.expiration_datetime_null');
        }

        $expiration_datetime_object = (new \DateTime($expiration_datetime));
        $now_datetime_object = (new \DateTime());

        if($now_datetime_object < $expiration_datetime_object) {
            return sprintf(l('transfers.expiration_datetime_x'), \Altum\Date::get_time_until($expiration_datetime));
        } else {
            return l('transfers.pending_deletion');
        }
    }

    public function get_downloads_limit_text($downloads, $downloads_limit) {
        return sprintf(l('transfers.downloads_limit'), $downloads, $downloads_limit ?? '∞');
    }
}
