<?php

declare(strict_types=1);

namespace Sibs\KronosApi\Repository;

use PDO;
use RuntimeException;

final class IndividualResourceRepository
{
    private const EMPLOYEE_QUERY = 'SELECT gy_emp_id, gy_emp_code, gy_emp_type, gy_emp_schedtype, gy_emp_rate, '
        . 'gy_emp_email, gy_emp_lname, gy_emp_fname, gy_emp_mname, gy_emp_fullname, gy_acc_id, '
        . 'gy_emp_account, gy_emp_supervisor, gy_emp_om, gy_emp_leave_credits, gy_emp_hiredate, '
        . 'gy_emp_lastedit, gy_lastedit_by, gy_work_from, gy_gender, gy_dob, gy_civilstatus, '
        . 'gy_assignedloc, gy_tagumdate, gy_davaodate, gy_hybriddate, gy_accjoin, gy_nhodate, '
        . 'gy_fststartdate, gy_fstenddate, gy_pststartdate, gy_pstenddate, gy_certification, '
        . 'gy_gradbaystartdate, gy_gradbayenddate, gy_fullgolivedate, gy_promotiondate, '
        . 'gy_projempdate, gy_probempdate, gy_regempdate, gy_last_working_day '
        . 'FROM gy_employee WHERE TRIM(gy_emp_code) = TRIM(:employee_code) LIMIT 1';

    private const ACCOUNT_QUERY = 'SELECT gy_acc_id, gy_acc_name, gy_acc_ghl_name, gy_dept_id, gy_acc_status '
        . 'FROM gy_accounts WHERE gy_acc_id = :account_id LIMIT 1';

    private const DEPARTMENT_QUERY = 'SELECT id_department, name_department FROM gy_department '
        . 'WHERE id_department = :department_id LIMIT 1';

    private const USER_QUERY = 'SELECT gy_user_id, gy_user_code, ghl_contact_id, gy_full_name, gy_username, '
        . 'gy_user_type, gy_user_function, gy_head_code, gy_script_code, gy_user_status '
        . 'FROM gy_user WHERE TRIM(gy_user_code) = TRIM(:employee_code) LIMIT 1';

    private const SCHEDULE_QUERY = 'SELECT gy_sched_id, gy_emp_id, gy_sched_day, gy_sched_mode, gy_sched_login, '
        . 'gy_sched_breakout, gy_sched_breakin, gy_sched_logout, gy_sched_reg, gy_sched_by '
        . 'FROM gy_schedule WHERE gy_sched_id = :schedule_id LIMIT 1';

    private const SCHEDULE_ESCALATION_QUERY = 'SELECT gy_sched_esc_id, gy_sched_esc_code, gy_req_date, '
        . 'gy_req_status, gy_req_deny, gy_req_by, gy_req_to, gy_sup, gy_emp_code, gy_emp_fullname, '
        . 'gy_sched_day, gy_sched_mode, gy_sched_login, gy_sched_breakout, gy_sched_breakin, gy_sched_logout, '
        . 'gy_tracker_login, gy_tracker_logout, gy_req_reason, gy_req_photodir, gy_publish, old_sched_mode, '
        . 'old_sched_login, old_sched_breakout, old_sched_breakin, old_sched_logout, old_tracker_login, '
        . 'old_tracker_logout, msg_usercode FROM gy_schedule_escalate '
        . 'WHERE gy_sched_esc_id = :schedule_escalation_id LIMIT 1';

    private const SCHEDULE_RD_REQUEST_QUERY = 'SELECT gy_rd_id, gy_rd_date, gy_rd_status, gy_tracker_id, '
        . 'gy_user_id, gy_rd_approved_by FROM gy_schedule_rd_request '
        . 'WHERE gy_rd_id = :schedule_rd_request_id LIMIT 1';

    private const TRACKER_QUERY = 'SELECT gy_tracker_id, gy_tracker_code, gy_tracker_date, gy_emp_code, '
        . 'gy_emp_email, gy_emp_fullname, gy_account_id, gy_emp_account, gy_tracker_login, '
        . 'gy_tracker_breakout, gy_tracker_breakin, gy_tracker_logout, gy_tracker_wh, gy_tracker_bh, '
        . 'gy_tracker_ot, gy_tracker_ath, gy_tracker_status, gy_tracker_request, gy_tracker_reason, '
        . 'gy_tracker_history, gy_tracker_remarks, gy_tracker_loc '
        . 'FROM gy_tracker WHERE gy_tracker_id = :tracker_id LIMIT 1';

    private const ESCALATION_QUERY = 'SELECT gy_esc_id, gy_esc_type, gy_esc_reason, gy_esc_photodir, '
        . 'gy_esc_status, gy_esc_deny, gy_esc_date, gy_tracker_id, '
        . 'gy_tracker_date, gy_tracker_login, gy_tracker_breakout, gy_tracker_breakin, gy_tracker_logout, '
        . 'gy_tracker_wh, gy_tracker_bh, gy_tracker_ot, gy_publish, gy_usercode, old_tracker_date, '
        . 'old_tracker_login, old_tracker_breakout, old_tracker_breakin, old_tracker_logout, msg_usercode '
        . 'FROM gy_escalate WHERE gy_esc_id = :escalation_id LIMIT 1';

    private const LEAVE_QUERY = 'SELECT gy_leave_id, gy_acc_id, gy_leave_filed, gy_leave_type, '
        . 'gy_leave_paid, gy_leave_day, gy_leave_period_one, gy_leave_period_two, gy_emp_rate, '
        . 'gy_old_credits, gy_new_credits, gy_leave_date_from, gy_leave_date_to, gy_leave_reason, '
        . 'gy_leave_status, gy_leave_date_approved, gy_leave_remarks, '
        . 'gy_leave_attachment, gy_publish, msg_usercode FROM gy_leave '
        . 'WHERE gy_leave_id = :leave_id LIMIT 1';

    private const LEAVE_AVAILABILITY_QUERY = 'SELECT gy_leave_avail_id, gy_leave_avail_date, '
        . 'gy_leave_avail_dateto, gy_leave_avail_plotted, gy_leave_avail_approved, '
        . 'gy_leave_avail_justify, gy_acc_id FROM gy_leave_available '
        . 'WHERE gy_leave_avail_id = :leave_availability_id LIMIT 1';

    private const LEAVE_CREDIT_HISTORY_QUERY = 'SELECT lch_id, lch_emp_code, lch_date, lch_old_credits, '
        . 'lch_new_credits, lch_type, lch_trigger_date_type, lch_trigger_amount, '
        . 'lch_trigger_affected_type, lch_updated_by, lch_daterecorded, lch_operation '
        . 'FROM leave_credits_history WHERE lch_id = :leave_credit_history_id LIMIT 1';

    private const EMPLOYEE_LOG_QUERY = 'SELECT gy_log_id, gy_log_date, gy_log_code, gy_log_email, '
        . 'gy_log_fullname, gy_log_account, gy_log_status FROM gy_logs '
        . 'WHERE gy_log_id = :employee_log_id LIMIT 1';

    private const EMPLOYEE_EDIT_LOG_QUERY = 'SELECT l.gy_editlog_id, e.gy_emp_code, l.gy_edit_date '
        . 'FROM gy_editlog l LEFT JOIN gy_employee e ON l.gy_emp_id = e.gy_emp_id '
        . 'WHERE l.gy_editlog_id = :employee_edit_log_id LIMIT 1';

    private const DTR_PUBLISH_QUERY = 'SELECT dtr_publish_id, dtr_year, dtr_month, dtr_cutoff, gy_emp_code, '
        . 'dtr_noofhours, dtr_lateut, dtr_absences, dtr_regot, dtr_rdreg, dtr_rdot, dtr_shreg, '
        . 'dtr_shot, dtr_shrdreg, dtr_shrdot, dtr_lhreg, dtr_lhot, dtr_lhrdreg, dtr_lhrdot, '
        . 'dtr_ndreg, dtr_ndregot, dtr_ndrdreg, dtr_ndrdot, dtr_ndsh, dtr_ndshot, dtr_ndshrd, '
        . 'dtr_ndshrdot, dtr_ndlh, dtr_ndlhot, dtr_ndlhrd, dtr_ndlhrdot, dtr_mdrate, dtr_cmpute '
        . 'FROM dtr_publish WHERE dtr_publish_id = :dtr_publish_id LIMIT 1';

    private const TIMESHEET_ASSIGNMENT_QUERY = 'SELECT at_id, at_emp_code, at_account_id '
        . 'FROM assign_timesheet WHERE at_id = :timesheet_assignment_id LIMIT 1';

    private const ANNOUNCEMENT_QUERY = 'SELECT gy_ann_id, gy_ann_serial, gy_ann_type, gy_ann_date, '
        . 'gy_ann_end, gy_ann_caption, gy_ann_attachment FROM gy_announce '
        . 'WHERE gy_ann_id = :announcement_id LIMIT 1';

    private const CONFIRMATION_QUERY = 'SELECT gy_conf_id, gy_conf_date, gy_conf_by, gy_ann_id '
        . 'FROM gy_confirm WHERE gy_conf_id = :confirmation_id LIMIT 1';

    private const NOTIFICATION_QUERY = 'SELECT gy_notif_id, gy_notif_type, gy_user_code, gy_notif_text, '
        . 'gy_notif_date, gy_notif_ip FROM gy_notification WHERE gy_notif_id = :notification_id LIMIT 1';

    private const HOLIDAY_TYPE_QUERY = 'SELECT gy_hol_type_id, gy_hol_type_name, gy_hol_abbrv, '
        . 'gy_daybonus, gy_nightbonus, lateut, absnt, leaves, gy_day_start, gy_day_end, '
        . 'gy_night_start, gy_night_end, gy_hol_status FROM gy_holiday_types '
        . 'WHERE gy_hol_type_id = :holiday_type_id LIMIT 1';

    private const HOLIDAY_QUERY = 'SELECT gy_hol_id, gy_hol_type_id, gy_hol_reg, gy_hol_title, '
        . 'gy_hol_date, gy_a_year, gy_hol_lastday, gy_hol_loc FROM gy_holiday_calendar '
        . 'WHERE gy_hol_id = :holiday_id LIMIT 1';

    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @return array<string, mixed>|null */
    public function findEmployeeByCode(string $employeeCode): ?array
    {
        return $this->findOne(self::EMPLOYEE_QUERY, ':employee_code', $employeeCode, PDO::PARAM_STR);
    }

    /** @return array<string, mixed>|null */
    public function findAccountById(int $accountId): ?array
    {
        return $this->findOne(self::ACCOUNT_QUERY, ':account_id', $accountId, PDO::PARAM_INT);
    }

    /** @return array<string, mixed>|null */
    public function findDepartmentById(int $departmentId): ?array
    {
        return $this->findOne(self::DEPARTMENT_QUERY, ':department_id', $departmentId, PDO::PARAM_INT);
    }

    /** @return array<string, mixed>|null */
    public function findUserByEmployeeCode(string $employeeCode): ?array
    {
        return $this->findOne(self::USER_QUERY, ':employee_code', $employeeCode, PDO::PARAM_STR);
    }

    /** @return array<string, mixed>|null */
    public function findScheduleById(int $scheduleId): ?array
    {
        return $this->findOne(self::SCHEDULE_QUERY, ':schedule_id', $scheduleId, PDO::PARAM_INT);
    }

    /** @return array<string, mixed>|null */
    public function findScheduleEscalationById(int $scheduleEscalationId): ?array
    {
        return $this->findOne(
            self::SCHEDULE_ESCALATION_QUERY,
            ':schedule_escalation_id',
            $scheduleEscalationId,
            PDO::PARAM_INT
        );
    }

    /** @return array<string, mixed>|null */
    public function findScheduleRdRequestById(int $scheduleRdRequestId): ?array
    {
        return $this->findOne(
            self::SCHEDULE_RD_REQUEST_QUERY,
            ':schedule_rd_request_id',
            $scheduleRdRequestId,
            PDO::PARAM_INT
        );
    }

    /** @return array<string, mixed>|null */
    public function findTrackerById(int $trackerId): ?array
    {
        return $this->findOne(self::TRACKER_QUERY, ':tracker_id', $trackerId, PDO::PARAM_INT);
    }

    /** @return array<string, mixed>|null */
    public function findEscalationById(int $escalationId): ?array
    {
        return $this->findOne(self::ESCALATION_QUERY, ':escalation_id', $escalationId, PDO::PARAM_INT);
    }

    /** @return array<string, mixed>|null */
    public function findLeaveById(int $leaveId): ?array
    {
        return $this->findOne(self::LEAVE_QUERY, ':leave_id', $leaveId, PDO::PARAM_INT);
    }

    /** @return array<string, mixed>|null */
    public function findLeaveAvailabilityById(int $leaveAvailabilityId): ?array
    {
        return $this->findOne(
            self::LEAVE_AVAILABILITY_QUERY,
            ':leave_availability_id',
            $leaveAvailabilityId,
            PDO::PARAM_INT
        );
    }

    /** @return array<string, mixed>|null */
    public function findLeaveCreditHistoryById(int $leaveCreditHistoryId): ?array
    {
        return $this->findOne(
            self::LEAVE_CREDIT_HISTORY_QUERY,
            ':leave_credit_history_id',
            $leaveCreditHistoryId,
            PDO::PARAM_INT
        );
    }

    /** @return array<string, mixed>|null */
    public function findEmployeeLogById(int $employeeLogId): ?array
    {
        return $this->findOne(self::EMPLOYEE_LOG_QUERY, ':employee_log_id', $employeeLogId, PDO::PARAM_INT);
    }

    /** @return array<string, mixed>|null */
    public function findEmployeeEditLogById(int $employeeEditLogId): ?array
    {
        return $this->findOne(
            self::EMPLOYEE_EDIT_LOG_QUERY,
            ':employee_edit_log_id',
            $employeeEditLogId,
            PDO::PARAM_INT
        );
    }

    /** @return array<string, mixed>|null */
    public function findDtrPublishById(int $dtrPublishId): ?array
    {
        return $this->findOne(self::DTR_PUBLISH_QUERY, ':dtr_publish_id', $dtrPublishId, PDO::PARAM_INT);
    }

    /** @return array<string, mixed>|null */
    public function findTimesheetAssignmentById(int $assignmentId): ?array
    {
        return $this->findOne(
            self::TIMESHEET_ASSIGNMENT_QUERY,
            ':timesheet_assignment_id',
            $assignmentId,
            PDO::PARAM_INT
        );
    }

    /** @return array<string, mixed>|null */
    public function findAnnouncementById(int $announcementId): ?array
    {
        return $this->findOne(self::ANNOUNCEMENT_QUERY, ':announcement_id', $announcementId, PDO::PARAM_INT);
    }

    /** @return array<string, mixed>|null */
    public function findConfirmationById(int $confirmationId): ?array
    {
        return $this->findOne(self::CONFIRMATION_QUERY, ':confirmation_id', $confirmationId, PDO::PARAM_INT);
    }

    /** @return array<string, mixed>|null */
    public function findNotificationById(int $notificationId): ?array
    {
        return $this->findOne(self::NOTIFICATION_QUERY, ':notification_id', $notificationId, PDO::PARAM_INT);
    }

    /** @return array<string, mixed>|null */
    public function findHolidayTypeById(int $holidayTypeId): ?array
    {
        return $this->findOne(self::HOLIDAY_TYPE_QUERY, ':holiday_type_id', $holidayTypeId, PDO::PARAM_INT);
    }

    /** @return array<string, mixed>|null */
    public function findHolidayById(int $holidayId): ?array
    {
        return $this->findOne(self::HOLIDAY_QUERY, ':holiday_id', $holidayId, PDO::PARAM_INT);
    }

    /** @return array<string, mixed>|null */
    private function findOne(string $query, string $placeholder, int|string $value, int $type): ?array
    {
        $statement = $this->pdo->prepare($query);

        if ($statement === false) {
            throw new RuntimeException('Unable to prepare individual resource query.');
        }

        $statement->bindValue($placeholder, $value, $type);
        $statement->execute();
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }
}
