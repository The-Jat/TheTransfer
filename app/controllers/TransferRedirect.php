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

use Altum\Models\User;

defined('ALTUMCODE') || die();

class TransferRedirect extends Controller {

    public function index() {

        $transfer_id = isset($this->params[0]) ? (int) $this->params[0] : null;

        if(!$transfer = db()->where('transfer_id', $transfer_id)->getOne('transfers', ['transfer_id', 'domain_id', 'user_id', 'url'])) {
            redirect();
        }

        $transfer_user = (new User())->get_user_by_user_id($transfer->user_id);

        /* Only works if admin or owner of transfer */
        if(is_logged_in() && (user()->type == 1 || $transfer_user->user_id == $transfer->user_id)) {
            /* Generate the transfer full URL base */
            $transfer->full_url = (new \Altum\Models\Transfers())->get_transfer_full_url($transfer, $transfer_user);

            header('Location: ' . $transfer->full_url);
            die();
        } else {
            redirect('not-found');
        }

    }
}
