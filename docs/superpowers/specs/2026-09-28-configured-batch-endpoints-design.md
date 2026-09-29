# Configured Batch Endpoints Design

## Objective

Add JWT-protected, read-only cursor endpoints for the requested database tables without creating one repository/controller pair per table. Preserve every existing endpoint and all existing JWT behavior.

## Scope

The implementation creates 27 endpoints. `/api/v1/qds-notifications` is intentionally skipped because live schema inspection found that `qds_notify` has no primary key, unique index, or any index. `/api/v1/sibs-accounts` is also skipped because final verification found `sibs_accounts` absent from configured DB1; similarly named tables in other schemas are outside the authorized connection scope. The database schema will not be changed.

No requested route already exists in `routes/api.php`.

## Architecture

### Fixed configuration

`Sibs\KronosApi\Config\BatchTableConfig` owns the complete route whitelist. Each entry contains only server-authored values:

- route path segment;
- physical table name;
- verified primary-key column;
- explicit ordered column allowlist.

The class exposes the whole immutable configuration to route registration and resolves entries by route key for tests. HTTP input can never provide or override SQL identifiers.

### Repository

`Sibs\KronosApi\Repository\BatchTableRepository` receives PDO plus one trusted configuration entry. It constructs one query from the entry's allowlisted identifiers:

```sql
SELECT <explicit columns>
FROM <configured table>
WHERE <configured primary key> > :after_id
ORDER BY <configured primary key> ASC
LIMIT 101
```

Only `:after_id` is a runtime query value and it is bound as `PDO::PARAM_INT`. The repository performs SELECT operations only.

### Controller

`Sibs\KronosApi\Controller\BatchTableController` receives a repository factory and the configured primary-key name. It mirrors `EmployeeController`:

- omitted, malformed, and negative `after_id` normalize to `0`;
- fetch 101 rows and return at most 100;
- `has_more` is true only when more than 100 rows were fetched;
- `next_cursor` is the configured primary-key value from the last returned row;
- empty results return `next_cursor: null` and `has_more: false`;
- exceptions are logged server-side and produce only the generic HTTP 500 JSON response;
- responses are JSON with `Cache-Control: private, no-store`.

### Routes and authentication

`routes/api.php` creates one controller per whitelist entry using the shared classes and registers each fixed path. Every route receives the existing `JwtAuthMiddleware`. Repository construction remains lazy so rejected JWT requests do not connect to MySQL.

Existing routes and auth classes are not modified beyond adding these route registrations and their shared repository factory.

## Verified schema whitelist

The following mapping was read from MySQL `INFORMATION_SCHEMA.COLUMNS` on 2026-09-28. Columns remain in physical ordinal order.

- `edit-logs` → `gy_editlog`, PK `gy_editlog_id`: `gy_editlog_id`, `gy_emp_id`, `gy_edit_date`
- `schedule-escalations` → `gy_schedule_escalate`, PK `gy_sched_esc_id`: `gy_sched_esc_id`, `gy_sched_esc_code`, `gy_req_date`, `gy_req_status`, `gy_req_deny`, `gy_req_by`, `gy_req_to`, `gy_sup`, `gy_emp_code`, `gy_emp_fullname`, `gy_sched_day`, `gy_sched_mode`, `gy_sched_login`, `gy_sched_breakout`, `gy_sched_breakin`, `gy_sched_logout`, `gy_tracker_login`, `gy_tracker_logout`, `gy_req_reason`, `gy_req_photodir`, `gy_publish`, `old_sched_mode`, `old_sched_login`, `old_sched_breakout`, `old_sched_breakin`, `old_sched_logout`, `old_tracker_login`, `old_tracker_logout`, `msg_usercode`
- `qds-query-keys` → `qds_querykey`, PK `qdsqk_id`: `qdsqk_id`, `kronos_key_name`, `query_key`, `audit_col_id`
- `team-data` → `team_data`, PK `data_id`: `data_id`, `col_id`, `row_id`, `tool_id`, `data_value`
- `holiday-types` → `gy_holiday_types`, PK `gy_hol_type_id`: `gy_hol_type_id`, `gy_hol_type_name`, `gy_hol_abbrv`, `gy_daybonus`, `gy_nightbonus`, `lateut`, `absnt`, `leaves`, `gy_day_start`, `gy_day_end`, `gy_night_start`, `gy_night_end`, `gy_hol_status`
- `team-column-list` → `team_collist`, PK `col_id`: `col_id`, `team_id`, `col_val`, `col_type`, `col_status`, `col_order`
- `logs` → `gy_logs`, PK `gy_log_id`: `gy_log_id`, `gy_log_date`, `gy_emp_id`, `gy_log_code`, `gy_log_email`, `gy_log_fullname`, `gy_log_account`, `gy_log_status`
- `schedules` → `gy_schedule`, PK `gy_sched_id`: `gy_sched_id`, `gy_emp_id`, `gy_sched_day`, `gy_sched_mode`, `gy_sched_login`, `gy_sched_breakout`, `gy_sched_breakin`, `gy_sched_logout`, `gy_sched_reg`, `gy_sched_by`
- `projects` → `gy_my_project`, PK `gy_project`: `gy_project`, `gy_project_name`, `gy_project_address`, `gy_system_title`, `gy_year_origin`, `gy_url`, `gy_convert_to`
- `tool-details` → `tool_details`, PK `toold_id`: `toold_id`, `toold_sortid`, `toold_listid`, `toold_label`, `toold_type`, `toold_status`
- `notifications` → `gy_notification`, PK `gy_notif_id`: `gy_notif_id`, `gy_notif_type`, `gy_user_code`, `gy_notif_text`, `gy_notif_date`, `gy_notif_ip`
- `holiday-calendar` → `gy_holiday_calendar`, PK `gy_hol_id`: `gy_hol_id`, `gy_hol_type_id`, `gy_hol_reg`, `gy_hol_title`, `gy_hol_date`, `gy_a_year`, `gy_hol_lastday`, `gy_hol_loc`
- `tools` → `tool_list`, PK `tool_id`: `tool_id`, `tool_name`, `tool_status`
- `requests` → `gy_request`, PK `gy_req_id`: `gy_req_id`, `gy_req_code`, `gy_req_date`, `gy_req_status`, `gy_req_by`, `gy_emp_code`, `gy_emp_fullname`, `gy_sched_day`, `gy_sched_mode`, `gy_sched_login`, `gy_sched_breakout`, `gy_sched_breakin`, `gy_sched_logout`, `gy_req_reason`
- `reasons` → `gy_reason`, PK `gy_reason_id`: `gy_reason_id`, `gy_reason_name`
- `schedule-rd-requests` → `gy_schedule_rd_request`, PK `gy_rd_id`: `gy_rd_id`, `gy_rd_date`, `gy_rd_status`, `gy_tracker_id`, `gy_user_id`, `gy_rd_approved_by`
- `leave-available` → `gy_leave_available`, PK `gy_leave_avail_id`: `gy_leave_avail_id`, `gy_leave_avail_date`, `gy_leave_avail_dateto`, `gy_leave_avail_plotted`, `gy_leave_avail_approved`, `gy_user_id`, `gy_leave_avail_justify`, `gy_acc_id`
- `qds-assign-groups` → `qds_assign_group`, PK `qag_id`: `qag_id`, `qag_sibsid`, `qag_account`
- `processes` → `gy_process`, PK `gy_process_id`: `gy_process_id`, `gy_process_ref`, `gy_process_date_from`, `gy_process_date_to`, `EmployeeNumber`, `EmployeeName`, `NoOfHours`, `UnderTime`, `Absenses`, `RegularOT`, `RestDay`, `RestDayOT`, `SpecialHoliday`, `SpecialHolidayOT`, `SpecialHolidayRestDay`, `SpecialHolidayRestDayOT`, `LegalHoliday`, `LegalHolidayOT`, `LegalHolidayRestday`, `LegalHolidayRestdayOT`, `NightDiffRegular`, `NightDiffRegularOT`, `NightDiffRestDay`, `NightDiffRestDayOT`, `NightDiffSpecialHoliday`, `NightDiffSpecialHolidayOT`, `NightDiffSpecialHolidayRestDay`, `NightDiffSpecialHolidayRestDayOT`, `NightDiffLegalHoliday`, `NightDiffLegalHolidayOT`, `NightDiffLegalHolidayRestDay`, `NightDiffLegalHolidayRestDayOT`
- `team-tools` → `team_toollist`, PK `team_id`: `team_id`, `team_name`, `team_owner`, `team_switch`
- `tool-data` → `tool_data`, PK `td_id`: `td_id`, `td_tooldid`, `td_emp_code`, `td_value`, `td_status`
- `temp-supervisors` → `gy_temp_sup`, PK `temp_sup_id`: `temp_sup_id`, `temp_sup_code`, `temp_sup_date`, `temp_sup_by`
- `whitelist` → `gy_whitelist`, PK `id`: `id`, `sibs_id`, `ip`, `details`
- `leave-credit-history` → `leave_credits_history`, PK `lch_id`: `lch_id`, `lch_emp_code`, `lch_date`, `lch_old_credits`, `lch_new_credits`, `lch_type`, `lch_trigger_date_type`, `lch_trigger_amount`, `lch_trigger_affected_type`, `lch_updated_by`, `lch_daterecorded`, `lch_operation`
- `tracker` → `gy_tracker`, PK `gy_tracker_id`: `gy_tracker_id`, `gy_tracker_code`, `gy_tracker_date`, `gy_emp_code`, `gy_emp_email`, `gy_emp_fullname`, `gy_account_id`, `gy_emp_account`, `gy_tracker_login`, `gy_tracker_breakout`, `gy_tracker_breakin`, `gy_tracker_logout`, `gy_tracker_wh`, `gy_tracker_bh`, `gy_tracker_ot`, `gy_tracker_ath`, `gy_tracker_status`, `gy_tracker_request`, `gy_tracker_reason`, `gy_tracker_history`, `gy_tracker_remarks`, `gy_tracker_om`, `gy_tracker_loc`
- `leaves` → `gy_leave`, PK `gy_leave_id`: `gy_leave_id`, `gy_user_id`, `gy_acc_id`, `gy_leave_filed`, `gy_leave_type`, `gy_leave_paid`, `gy_leave_day`, `gy_leave_period_one`, `gy_leave_period_two`, `gy_emp_rate`, `gy_old_credits`, `gy_new_credits`, `gy_leave_date_from`, `gy_leave_date_to`, `gy_leave_reason`, `gy_leave_status`, `gy_leave_approver`, `gy_leave_date_approved`, `gy_leave_remarks`, `gy_leave_attachment`, `gy_publish`, `msg_usercode`
- `escalations` → `gy_escalate`, PK `gy_esc_id`: `gy_esc_id`, `gy_esc_type`, `gy_esc_reason`, `gy_esc_photodir`, `gy_esc_status`, `gy_esc_deny`, `gy_esc_date`, `gy_esc_by`, `gy_esc_to`, `gy_sup`, `gy_tracker_id`, `gy_tracker_date`, `gy_tracker_login`, `gy_tracker_breakout`, `gy_tracker_breakin`, `gy_tracker_logout`, `gy_tracker_wh`, `gy_tracker_bh`, `gy_tracker_ot`, `gy_publish`, `gy_usercode`, `old_tracker_date`, `old_tracker_login`, `old_tracker_breakout`, `old_tracker_breakin`, `old_tracker_logout`, `msg_usercode`

Skipped:

- `qds-notifications` → `qds_notify`: no primary key or index; user approved skipping this endpoint.
- `sibs-accounts` → `sibs_accounts`: absent from configured DB1; cross-schema access was not authorized.

## Error and security behavior

- All routes require a valid Bearer JWT through the unchanged middleware.
- No table, primary key, column, sort, filter, or SQL fragment comes from the request.
- No database metadata endpoint is exposed.
- SQL, exception messages, credentials, and stack traces never appear in responses.
- Database and JSON-serialization exceptions are logged with the existing server-side logging pattern.
- The response contains only columns listed above.

## Testing

Tests will prove:

1. the whitelist contains exactly the 27 safe endpoint mappings and neither skipped route;
2. every configured table, primary key, and column matches the captured schema contract;
3. the repository emits an explicit prepared query and binds only `after_id` as an integer;
4. the shared controller handles 101, exactly 100, short, empty, omitted, malformed, and negative cursor cases;
5. the controller uses the actual final primary-key value and sanitizes/logs failures;
6. every generated route rejects missing JWT before constructing a repository and accepts a valid JWT;
7. the existing test suite, PHP syntax checks, and Composer validation remain green.
