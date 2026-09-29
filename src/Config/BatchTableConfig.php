<?php

declare(strict_types=1);

namespace Sibs\KronosApi\Config;

use InvalidArgumentException;

final class BatchTableConfig
{
    /**
     * @var array<string, array{
     *     route: string,
     *     table: string,
     *     primaryKey: string,
     *     columns: list<string>
     * }>
     */
    private const TABLES = [
        'edit-logs' => [
            'route' => 'edit-logs',
            'table' => 'gy_editlog',
            'primaryKey' => 'gy_editlog_id',
            'columns' => ['gy_editlog_id', 'gy_emp_id', 'gy_edit_date'],
        ],
        'schedule-escalations' => [
            'route' => 'schedule-escalations',
            'table' => 'gy_schedule_escalate',
            'primaryKey' => 'gy_sched_esc_id',
            'columns' => ['gy_sched_esc_id', 'gy_sched_esc_code', 'gy_req_date', 'gy_req_status', 'gy_req_deny', 'gy_req_by', 'gy_req_to', 'gy_sup', 'gy_emp_code', 'gy_emp_fullname', 'gy_sched_day', 'gy_sched_mode', 'gy_sched_login', 'gy_sched_breakout', 'gy_sched_breakin', 'gy_sched_logout', 'gy_tracker_login', 'gy_tracker_logout', 'gy_req_reason', 'gy_req_photodir', 'gy_publish', 'old_sched_mode', 'old_sched_login', 'old_sched_breakout', 'old_sched_breakin', 'old_sched_logout', 'old_tracker_login', 'old_tracker_logout', 'msg_usercode'],
        ],
        'qds-query-keys' => [
            'route' => 'qds-query-keys',
            'table' => 'qds_querykey',
            'primaryKey' => 'qdsqk_id',
            'columns' => ['qdsqk_id', 'kronos_key_name', 'query_key', 'audit_col_id'],
        ],
        'team-data' => [
            'route' => 'team-data',
            'table' => 'team_data',
            'primaryKey' => 'data_id',
            'columns' => ['data_id', 'col_id', 'row_id', 'tool_id', 'data_value'],
        ],
        'holiday-types' => [
            'route' => 'holiday-types',
            'table' => 'gy_holiday_types',
            'primaryKey' => 'gy_hol_type_id',
            'columns' => ['gy_hol_type_id', 'gy_hol_type_name', 'gy_hol_abbrv', 'gy_daybonus', 'gy_nightbonus', 'lateut', 'absnt', 'leaves', 'gy_day_start', 'gy_day_end', 'gy_night_start', 'gy_night_end', 'gy_hol_status'],
        ],
        'team-column-list' => [
            'route' => 'team-column-list',
            'table' => 'team_collist',
            'primaryKey' => 'col_id',
            'columns' => ['col_id', 'team_id', 'col_val', 'col_type', 'col_status', 'col_order'],
        ],
        'logs' => [
            'route' => 'logs',
            'table' => 'gy_logs',
            'primaryKey' => 'gy_log_id',
            'columns' => ['gy_log_id', 'gy_log_date', 'gy_emp_id', 'gy_log_code', 'gy_log_email', 'gy_log_fullname', 'gy_log_account', 'gy_log_status'],
        ],
        'schedules' => [
            'route' => 'schedules',
            'table' => 'gy_schedule',
            'primaryKey' => 'gy_sched_id',
            'columns' => ['gy_sched_id', 'gy_emp_id', 'gy_sched_day', 'gy_sched_mode', 'gy_sched_login', 'gy_sched_breakout', 'gy_sched_breakin', 'gy_sched_logout', 'gy_sched_reg', 'gy_sched_by'],
        ],
        'projects' => [
            'route' => 'projects',
            'table' => 'gy_my_project',
            'primaryKey' => 'gy_project',
            'columns' => ['gy_project', 'gy_project_name', 'gy_project_address', 'gy_system_title', 'gy_year_origin', 'gy_url', 'gy_convert_to'],
        ],
        'tool-details' => [
            'route' => 'tool-details',
            'table' => 'tool_details',
            'primaryKey' => 'toold_id',
            'columns' => ['toold_id', 'toold_sortid', 'toold_listid', 'toold_label', 'toold_type', 'toold_status'],
        ],
        'notifications' => [
            'route' => 'notifications',
            'table' => 'gy_notification',
            'primaryKey' => 'gy_notif_id',
            'columns' => ['gy_notif_id', 'gy_notif_type', 'gy_user_code', 'gy_notif_text', 'gy_notif_date', 'gy_notif_ip'],
        ],
        'holiday-calendar' => [
            'route' => 'holiday-calendar',
            'table' => 'gy_holiday_calendar',
            'primaryKey' => 'gy_hol_id',
            'columns' => ['gy_hol_id', 'gy_hol_type_id', 'gy_hol_reg', 'gy_hol_title', 'gy_hol_date', 'gy_a_year', 'gy_hol_lastday', 'gy_hol_loc'],
        ],
        'tools' => [
            'route' => 'tools',
            'table' => 'tool_list',
            'primaryKey' => 'tool_id',
            'columns' => ['tool_id', 'tool_name', 'tool_status'],
        ],
        'requests' => [
            'route' => 'requests',
            'table' => 'gy_request',
            'primaryKey' => 'gy_req_id',
            'columns' => ['gy_req_id', 'gy_req_code', 'gy_req_date', 'gy_req_status', 'gy_req_by', 'gy_emp_code', 'gy_emp_fullname', 'gy_sched_day', 'gy_sched_mode', 'gy_sched_login', 'gy_sched_breakout', 'gy_sched_breakin', 'gy_sched_logout', 'gy_req_reason'],
        ],
        'reasons' => [
            'route' => 'reasons',
            'table' => 'gy_reason',
            'primaryKey' => 'gy_reason_id',
            'columns' => ['gy_reason_id', 'gy_reason_name'],
        ],
        'schedule-rd-requests' => [
            'route' => 'schedule-rd-requests',
            'table' => 'gy_schedule_rd_request',
            'primaryKey' => 'gy_rd_id',
            'columns' => ['gy_rd_id', 'gy_rd_date', 'gy_rd_status', 'gy_tracker_id', 'gy_user_id', 'gy_rd_approved_by'],
        ],
        'leave-available' => [
            'route' => 'leave-available',
            'table' => 'gy_leave_available',
            'primaryKey' => 'gy_leave_avail_id',
            'columns' => ['gy_leave_avail_id', 'gy_leave_avail_date', 'gy_leave_avail_dateto', 'gy_leave_avail_plotted', 'gy_leave_avail_approved', 'gy_user_id', 'gy_leave_avail_justify', 'gy_acc_id'],
        ],
        'qds-assign-groups' => [
            'route' => 'qds-assign-groups',
            'table' => 'qds_assign_group',
            'primaryKey' => 'qag_id',
            'columns' => ['qag_id', 'qag_sibsid', 'qag_account'],
        ],
        'processes' => [
            'route' => 'processes',
            'table' => 'gy_process',
            'primaryKey' => 'gy_process_id',
            'columns' => ['gy_process_id', 'gy_process_ref', 'gy_process_date_from', 'gy_process_date_to', 'EmployeeNumber', 'EmployeeName', 'NoOfHours', 'UnderTime', 'Absenses', 'RegularOT', 'RestDay', 'RestDayOT', 'SpecialHoliday', 'SpecialHolidayOT', 'SpecialHolidayRestDay', 'SpecialHolidayRestDayOT', 'LegalHoliday', 'LegalHolidayOT', 'LegalHolidayRestday', 'LegalHolidayRestdayOT', 'NightDiffRegular', 'NightDiffRegularOT', 'NightDiffRestDay', 'NightDiffRestDayOT', 'NightDiffSpecialHoliday', 'NightDiffSpecialHolidayOT', 'NightDiffSpecialHolidayRestDay', 'NightDiffSpecialHolidayRestDayOT', 'NightDiffLegalHoliday', 'NightDiffLegalHolidayOT', 'NightDiffLegalHolidayRestDay', 'NightDiffLegalHolidayRestDayOT'],
        ],
        'team-tools' => [
            'route' => 'team-tools',
            'table' => 'team_toollist',
            'primaryKey' => 'team_id',
            'columns' => ['team_id', 'team_name', 'team_owner', 'team_switch'],
        ],
        'tool-data' => [
            'route' => 'tool-data',
            'table' => 'tool_data',
            'primaryKey' => 'td_id',
            'columns' => ['td_id', 'td_tooldid', 'td_emp_code', 'td_value', 'td_status'],
        ],
        'temp-supervisors' => [
            'route' => 'temp-supervisors',
            'table' => 'gy_temp_sup',
            'primaryKey' => 'temp_sup_id',
            'columns' => ['temp_sup_id', 'temp_sup_code', 'temp_sup_date', 'temp_sup_by'],
        ],
        'whitelist' => [
            'route' => 'whitelist',
            'table' => 'gy_whitelist',
            'primaryKey' => 'id',
            'columns' => ['id', 'sibs_id', 'ip', 'details'],
        ],
        'leave-credit-history' => [
            'route' => 'leave-credit-history',
            'table' => 'leave_credits_history',
            'primaryKey' => 'lch_id',
            'columns' => ['lch_id', 'lch_emp_code', 'lch_date', 'lch_old_credits', 'lch_new_credits', 'lch_type', 'lch_trigger_date_type', 'lch_trigger_amount', 'lch_trigger_affected_type', 'lch_updated_by', 'lch_daterecorded', 'lch_operation'],
        ],
        'tracker' => [
            'route' => 'tracker',
            'table' => 'gy_tracker',
            'primaryKey' => 'gy_tracker_id',
            'columns' => ['gy_tracker_id', 'gy_tracker_code', 'gy_tracker_date', 'gy_emp_code', 'gy_emp_email', 'gy_emp_fullname', 'gy_account_id', 'gy_emp_account', 'gy_tracker_login', 'gy_tracker_breakout', 'gy_tracker_breakin', 'gy_tracker_logout', 'gy_tracker_wh', 'gy_tracker_bh', 'gy_tracker_ot', 'gy_tracker_ath', 'gy_tracker_status', 'gy_tracker_request', 'gy_tracker_reason', 'gy_tracker_history', 'gy_tracker_remarks', 'gy_tracker_om', 'gy_tracker_loc'],
        ],
        'leaves' => [
            'route' => 'leaves',
            'table' => 'gy_leave',
            'primaryKey' => 'gy_leave_id',
            'columns' => ['gy_leave_id', 'gy_user_id', 'gy_acc_id', 'gy_leave_filed', 'gy_leave_type', 'gy_leave_paid', 'gy_leave_day', 'gy_leave_period_one', 'gy_leave_period_two', 'gy_emp_rate', 'gy_old_credits', 'gy_new_credits', 'gy_leave_date_from', 'gy_leave_date_to', 'gy_leave_reason', 'gy_leave_status', 'gy_leave_approver', 'gy_leave_date_approved', 'gy_leave_remarks', 'gy_leave_attachment', 'gy_publish', 'msg_usercode'],
        ],
        'escalations' => [
            'route' => 'escalations',
            'table' => 'gy_escalate',
            'primaryKey' => 'gy_esc_id',
            'columns' => ['gy_esc_id', 'gy_esc_type', 'gy_esc_reason', 'gy_esc_photodir', 'gy_esc_status', 'gy_esc_deny', 'gy_esc_date', 'gy_esc_by', 'gy_esc_to', 'gy_sup', 'gy_tracker_id', 'gy_tracker_date', 'gy_tracker_login', 'gy_tracker_breakout', 'gy_tracker_breakin', 'gy_tracker_logout', 'gy_tracker_wh', 'gy_tracker_bh', 'gy_tracker_ot', 'gy_publish', 'gy_usercode', 'old_tracker_date', 'old_tracker_login', 'old_tracker_breakout', 'old_tracker_breakin', 'old_tracker_logout', 'msg_usercode'],
        ],
    ];

    /**
     * @return array<string, array{
     *     route: string,
     *     table: string,
     *     primaryKey: string,
     *     columns: list<string>
     * }>
     */
    public static function all(): array
    {
        return self::TABLES;
    }

    /**
     * @return array{
     *     route: string,
     *     table: string,
     *     primaryKey: string,
     *     columns: list<string>
     * }
     */
    public static function get(string $route): array
    {
        return self::TABLES[$route]
            ?? throw new InvalidArgumentException('Unknown batch table route.');
    }
}
