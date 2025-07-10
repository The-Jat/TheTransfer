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

class Transfers extends Controller {

    public function index() {
        \Altum\Authentication::guard();

        /* Get available custom domains */
        $domains = (new \Altum\Models\Domain())->get_available_domains_by_user($this->user);

        /* Prepare the filtering system */
        $filters = (new \Altum\Filters(['domain_id', 'project_id', 'domain_id', 'pixels_ids'], ['name', 'url'], ['transfer_id', 'expiration_datetime', 'last_datetime', 'datetime', 'name', 'url', 'pageviews', 'downloads', 'downloads_limit', 'total_files', 'total_size'], [], ['pixels_ids' => 'json_contains']));
        $filters->set_default_order_by($this->user->preferences->transfers_default_order_by, $this->user->preferences->default_order_type ?? settings()->main->default_order_type);
        $filters->set_default_results_per_page($this->user->preferences->default_results_per_page ?? settings()->main->default_results_per_page);

        /* Prepare the paginator */
        $total_rows = database()->query("SELECT COUNT(*) AS `total` FROM `transfers` WHERE `user_id` = {$this->user->user_id} {$filters->get_sql_where()}")->fetch_object()->total ?? 0;
        $paginator = (new \Altum\Paginator($total_rows, $filters->get_results_per_page(), $_GET['page'] ?? 1, url('transfers?' . $filters->get_get() . '&page=%d')));

        /* Get the transfers */
        $transfers = [];
        $transfers_result = database()->query("
            SELECT
                *
            FROM
                `transfers`
            WHERE
                `user_id` = {$this->user->user_id}
                {$filters->get_sql_where()}
            {$filters->get_sql_order_by()}
            {$paginator->get_sql_limit()}
        ");

        while($row = $transfers_result->fetch_object()) {
            $row->full_url = (new \Altum\Models\Transfers())->get_transfer_full_url($row, $this->user, $domains);
            $row->settings = json_decode($row->settings ?? '');
            $transfers[] = $row;
        }

        /* Export handler */
        process_export_csv($transfers, 'include', ['transfer_id', 'domain_id', 'project_id', 'user_id', 'pixels_ids', 'name', 'url', 'total_files', 'total_size', 'pageviews', 'downloads_limit', 'downloads', 'expiration_datetime', 'datetime', 'last_datetime'], sprintf(l('transfers.title')));
        process_export_json($transfers, 'include', ['transfer_id', 'domain_id', 'project_id', 'user_id', 'pixels_ids', 'name', 'url', 'settings', 'total_files', 'total_size', 'pageviews', 'downloads_limit', 'downloads', 'expiration_datetime', 'datetime', 'last_datetime'], sprintf(l('transfers.title')));

        /* Prepare the pagination view */
        $pagination = (new \Altum\View('partials/pagination', (array) $this))->run(['paginator' => $paginator]);

        $projects = (new \Altum\Models\Projects())->get_projects_by_user_id($this->user->user_id);

        /* Get statistics */
        if(count($transfers) && !$filters->has_applied_filters) {
            $start_date_query = (new \DateTime())->modify('-' . (settings()->main->chat_days ?? 30) . ' day')->format('Y-m-d');
            $end_date_query = (new \DateTime('tomorrow'))->modify('+1 day')->format('Y-m-d');

            $convert_tz_sql = get_convert_tz_sql('`datetime`', $this->user->timezone);

            $statistics_result_query = "
                SELECT
                    COUNT(`id`) AS `pageviews`,
                    SUM(`is_unique`) AS `visitors`,
                    DATE_FORMAT({$convert_tz_sql}, '%Y-%m-%d') AS `formatted_date`
                FROM
                    `statistics`
                WHERE   
                    `user_id` = {$this->user->user_id} 
                    AND ({$convert_tz_sql} BETWEEN '{$start_date_query}' AND '{$end_date_query}')
                GROUP BY
                    `formatted_date`
                ORDER BY
                    `formatted_date`
            ";

            $transfers_chart = \Altum\Cache::cache_function_result('statistics?user_id=' . $this->user->user_id, null, function() use ($statistics_result_query) {
                $transfers_chart = [];

                $statistics_result = database()->query($statistics_result_query);

                /* Generate the raw chart data and save logs for later usage */
                while($row = $statistics_result->fetch_object()) {
                    $label = \Altum\Date::get($row->formatted_date, 5, \Altum\Date::$default_timezone);

                    $transfers_chart[$label] = [
                        'pageviews' => $row->pageviews,
                        'visitors' => $row->visitors
                    ];
                }

                return $transfers_chart;
            }, 60 * 60 * settings()->main->chart_cache ?? 12);

            $transfers_chart = get_chart_data($transfers_chart);
        }

        /* Prepare the view */
        $data = [
            'transfers_chart' => $transfers_chart ?? null,
            'projects' => $projects,
            'domains' => $domains,
            'transfers' => $transfers,
            'total_transfers' => $total_rows,
            'pagination' => $pagination,
            'filters' => $filters,
        ];

        $view = new \Altum\View('transfers/index', (array) $this);

        $this->add_view_content('content', $view->run($data));
    }

    public function bulk() {

        \Altum\Authentication::guard();

        /* Check for any errors */
        if(empty($_POST)) {
            redirect('transfers');
        }

        if(empty($_POST['selected'])) {
            redirect('transfers');
        }

        if(!isset($_POST['type'])) {
            redirect('transfers');
        }

        //ALTUMCODE:DEMO if(DEMO) Alerts::add_error('This command is blocked on the demo.');

        if(!\Altum\Csrf::check()) {
            Alerts::add_error(l('global.error_message.invalid_csrf_token'));
        }

        if(!Alerts::has_field_errors() && !Alerts::has_errors()) {

            set_time_limit(0);

            switch($_POST['type']) {
                case 'delete':

                    /* Team checks */
                    if(\Altum\Teams::is_delegated() && !\Altum\Teams::has_access('delete.projects')) {
                        Alerts::add_info(l('global.info_message.team_no_access'));
                        redirect('transfers');
                    }

                    foreach($_POST['selected'] as $transfer_id) {
                        if($transfer = db()->where('transfer_id', $transfer_id)->where('user_id', $this->user->user_id)->getOne('transfers', ['transfer_id'])) {
                            (new \Altum\Models\Transfers())->delete($transfer->transfer_id, $this->user->user_id);
                        }
                    }

                    break;
            }

            /* Set a nice success message */
            Alerts::add_success(l('bulk_delete_modal.success_message'));

        }

        redirect('transfers');
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
            redirect('transfers');
        }

        $transfer_id = (int) $_POST['transfer_id'];

        /* Get transfer details */
        if(!$transfer = db()->where('transfer_id', $transfer_id)->getOne('transfers')) {
            redirect();
        }

        /* Make sure the current user has access */
        if(($transfer->uploader_id != md5(get_ip())) && (!$transfer->user_id || $transfer->user_id != $this->user->user_id)) {
            redirect();
        }

        if(!Alerts::has_field_errors() && !Alerts::has_errors()) {

            (new \Altum\Models\Transfers())->delete($transfer->transfer_id, $this->user->user_id);

            /* Set a nice success message */
            Alerts::add_success(sprintf(l('global.success_message.delete1'), '<strong>' . $transfer->name . '</strong>'));

            redirect('transfers');

        }

        redirect('transfers');
    }

}
