# RAMPS workforce reads (v1)

Both endpoints require the existing API `Authorization: Bearer <JWT>` header. They are read-only and return `Cache-Control: private, no-store`.

## Weekly account metrics

`GET /api/v1/workforce-weekly-account-metrics?date_from=2026-10-01&date_to=2026-10-07&account_ids=4&calculation_mode=standard`

| Parameter | Meaning |
| --- | --- |
| `date_from`, `date_to` | Required strict `YYYY-MM-DD` inclusive range; `date_from` must be on/after `1000-02-12` and the maximum span is 730 days. |
| `account_ids` | Optional comma-separated positive account IDs (at most 100); omitted means all eligible active accounts. |
| `calculation_mode` | Optional; `standard` (default) or `single_account_fast`. The latter requires exactly one account ID. HRIS must select it explicitly only when replacing the existing single-account fast path; one ID never implies fast mode. |

HTTP 200:

```json
{
  "success": true,
  "data": {
    "calculation_mode": "standard",
    "weeks": [{
      "weekStart": "2026-10-01",
      "weekEnd": "2026-10-07",
      "effectiveWeekEnd": "2026-10-06",
      "accounts": [{
        "accountId": 4,
        "actualHeadcount": 38,
        "scheduledDays": 6,
        "scheduledCount": 141,
        "presentCount": 126,
        "attendanceDataCount": 126,
        "absenteeismCount": 15,
        "attritionPastCount": 3,
        "unmappedAttritionPastCount": 3,
        "interviewPopulationCount": 8,
        "nhoPopulationCount": 1,
        "fstPopulationCount": 3,
        "pstPopulationCount": 25,
        "projectedToBeEndorsedCount": 10,
        "attritionInterviewToNhoCount": 0,
        "attritionNhoToFstPstCount": 0,
        "attritionFstToPstCount": 0
      }]
    }]
  }
}
```

`weekStart` begins at `date_from`, then advances in seven-day windows; the last `weekEnd` is clipped to `date_to`. `effectiveWeekEnd` is one cutoff shared by every selected account in a week: the latest tracked day within that scope, capped at the last completed day in Asia/Manila. It is `null` when no tracking exists. In that case, non-attendance calculations use the day before `weekStart`, matching HRIS. Attendance never treats an untracked account/day as an absence.

`actualHeadcount` is active-user agent headcount as of the cutoff. `standard` retains `getWeeklyAccountRows` semantics; `single_account_fast` retains the RAMPS fast-path semantics, including its missing-hire-date and missing-last-working-date treatment. Zero dates and valid dates follow their respective source formulas. Do not interchange these modes.

`scheduledDays` is the number of distinct tracked days with mode-1 schedules. `scheduledCount` is distinct scheduled employee/day pairs; `presentCount` and `absenteeismCount` partition those pairs by tracker or published escalation presence. `attendanceDataCount` is distinct present employee-code/day pairs.

`attritionPastCount` counts distinct direct-account leavers plus employees with no account ID matched by account name; `unmappedAttritionPastCount` is the latter subset. The window is the 42 days ending on the cutoff. `interviewPopulationCount`, `nhoPopulationCount`, `fstPopulationCount`, `pstPopulationCount`, and `projectedToBeEndorsedCount` are HRIS stage populations as of that cutoff. The three `attrition...Count` fields count their corresponding 42-day stage-transition predicates. Counts are zero when no row qualifies.

Invalid parameters return HTTP 400 with `{"success":false,"message":"Invalid query parameters."}`. Unexpected errors are logged server-side and return HTTP 500 with `{"success":false,"message":"Server error."}`.

## Metadata

`GET /api/v1/workforce-report-metadata`

HTTP 200: `{"success":true,"data":{"firstAgentHireDate":"YYYY-MM-DD"}}`, or `null` if no valid agent hire date exists. This is the earliest valid `gy_emp_hiredate` for `gy_emp_type = 1`, computed by SQL aggregation; zero and invalid dates are excluded.

## Postman requests

Create two GET requests under Optimized Views using the URLs above and Postman's Bearer Token authorization. The repository has no committed Postman collection to update.

## Verification

Run `php tests/run.php` for isolated route, validation, and SQL-contract tests. Run `php tests/ramps_parity.php` where the adjacent SiBS HRIS source, Node dependencies, and configured read-only Kronos MySQL access are available. The latter compares six complete weeks of `standard` account metrics and sampled `single_account_fast` accounts directly with their corresponding existing HRIS calculations. It does not create or modify tables.
