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

defined('ALTUMCODE') || die();

class AdminTransfers extends Controller {

    public function index() {

        /* Prepare the filtering system */
        $filters = (new \Altum\Filters(['user_id', 'domain_id', 'project_id'], ['name', 'url'], ['transfer_id', 'expiration_datetime', 'last_datetime', 'datetime', 'name', 'url', 'pageviews', 'downloads', 'downloads_limit', 'total_files', 'total_size']));
        $filters->set_default_order_by($this->user->preferences->transfers_default_order_by, $this->user->preferences->default_order_type ?? settings()->main->default_order_type);
        $filters->set_default_results_per_page($this->user->preferences->default_results_per_page ?? settings()->main->default_results_per_page);

        /* Prepare the paginator */
        $total_rows = database()->query("SELECT COUNT(*) AS `total` FROM `transfers` WHERE 1 = 1 {$filters->get_sql_where()}")->fetch_object()->total ?? 0;
        $paginator = (new \Altum\Paginator($total_rows, $filters->get_results_per_page(), $_GET['page'] ?? 1, url('admin/transfers?' . $filters->get_get() . '&page=%d')));

        /* Get the data */
        $transfers = [];
        $transfers_result = database()->query("
            SELECT
                `transfers`.*, `users`.`name` AS `user_name`, `users`.`email` AS `user_email`, `users`.`avatar` AS `user_avatar`
            FROM
                `transfers`
            LEFT JOIN
                `users` ON `transfers`.`user_id` = `users`.`user_id`
            WHERE
                1 = 1
                {$filters->get_sql_where('transfers')}
                {$filters->get_sql_order_by('transfers')}

            {$paginator->get_sql_limit()}
        ");
        while($row = $transfers_result->fetch_object()) {
            $row->settings = json_decode($row->settings ?? '');
            $transfers[] = $row;
        }

        /* Export handler */
        process_export_csv($transfers, 'include', ['transfer_id', 'domain_id', 'project_id', 'user_id', 'pixels_ids', 'name', 'url', 'total_files', 'total_size', 'pageviews', 'downloads_limit', 'downloads', 'expiration_datetime', 'datetime', 'last_datetime'], sprintf(l('transfers.title')));
        process_export_json($transfers, 'include', ['transfer_id', 'domain_id', 'project_id', 'user_id', 'pixels_ids', 'name', 'url', 'settings', 'total_files', 'total_size', 'pageviews', 'downloads_limit', 'downloads', 'expiration_datetime', 'datetime', 'last_datetime'], sprintf(l('transfers.title')));

        /* Prepare the pagination view */
        $pagination = (new \Altum\View('partials/admin_pagination', (array) $this))->run(['paginator' => $paginator]);

        /* Main View */
        $data = [
            'transfers' => $transfers,
            'filters' => $filters,
            'pagination' => $pagination
        ];

        $view = new \Altum\View('admin/transfers/index', (array) $this);

        $this->add_view_content('content', $view->run($data));

    }

    public function bulk() {

        /* Check for any errors */
        if(empty($_POST)) {
            redirect('admin/transfers');
        }

        if(empty($_POST['selected'])) {
            redirect('admin/transfers');
        }

        if(!isset($_POST['type'])) {
            redirect('admin/transfers');
        }

        //ALTUMCODE:DEMO if(DEMO) Alerts::add_error('This command is blocked on the demo.');

        if(!\Altum\Csrf::check()) {
            Alerts::add_error(l('global.error_message.invalid_csrf_token'));
        }

        if(!Alerts::has_field_errors() && !Alerts::has_errors()) {

            set_time_limit(0);

            switch($_POST['type']) {
                case 'delete':

                    foreach($_POST['selected'] as $transfer_id) {

                        $transfer = db()->where('transfer_id', $transfer_id)->getOne('transfers', ['user_id']);

                        (new \Altum\Models\Transfers())->delete($transfer_id, $transfer->user_id);

                    }

                    break;
            }

            /* Set a nice success message */
            Alerts::add_success(l('bulk_delete_modal.success_message'));

        }

        redirect('admin/transfers');
    }

    public function delete() {

        $transfer_id = isset($this->params[0]) ? (int) $this->params[0] : null;

        //ALTUMCODE:DEMO if(DEMO) Alerts::add_error('This command is blocked on the demo.');

        if(!\Altum\Csrf::check('global_token')) {
            Alerts::add_error(l('global.error_message.invalid_csrf_token'));
        }

        if(!$transfer = db()->where('transfer_id', $transfer_id)->getOne('transfers', ['transfer_id', 'user_id', 'name'])) {
            redirect('admin/transfers');
        }

        if(!Alerts::has_field_errors() && !Alerts::has_errors()) {

            (new \Altum\Models\Transfers())->delete($transfer->transfer_id, $transfer->user_id);

            /* Set a nice success message */
            Alerts::add_success(sprintf(l('global.success_message.delete1'), '<strong>' . $transfer->name . '</strong>'));

        }

        redirect('admin/transfers');
    }

}
