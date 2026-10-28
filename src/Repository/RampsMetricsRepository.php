<?php

declare(strict_types=1);

namespace Sibs\KronosApi\Repository;

use DateInterval;
use DateTimeImmutable;
use PDO;
use RuntimeException;

final class RampsMetricsRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function firstAgentHireDate(): ?string
    {
        $sql = "SELECT DATE_FORMAT(MIN(gy_emp_hiredate), '%Y-%m-%d') AS first_agent_hire_date "
            . "FROM gy_employee WHERE gy_emp_type = 1 "
            . "AND gy_emp_hiredate IS NOT NULL "
            . "AND YEAR(gy_emp_hiredate) >= 1000 AND LAST_DAY(gy_emp_hiredate) IS NOT NULL";
        $statement = $this->pdo->prepare($sql);
        if ($statement === false) {
            throw new RuntimeException('Unable to prepare RAMPS metadata query.');
        }
        $statement->execute();
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return is_array($row) && is_string($row['first_agent_hire_date'] ?? null)
            ? $row['first_agent_hire_date'] : null;
    }

    /** @param list<int> $accountIds
     *  @return list<array<string, mixed>>
     */
    public function weeklyMetrics(string $from, string $to, array $accountIds, string $mode, string $lastCompletedDay): array
    {
        $weeks = [];
        $cursor = new DateTimeImmutable($from);
        $last = new DateTimeImmutable($to);
        while ($cursor <= $last) {
            $weekEnd = min($cursor->add(new DateInterval('P6D')), $last);
            $weeks[] = [
                'weekStart' => $cursor->format('Y-m-d'),
                'weekEnd' => $weekEnd->format('Y-m-d'),
                'effectiveWeekEnd' => null,
                'accounts' => [],
            ];
            $cursor = $weekEnd->add(new DateInterval('P1D'));
        }

        $accounts = $this->accounts($accountIds, $mode);
        if ($accounts === []) {
            return $weeks;
        }
        $ids = array_map(static fn (array $row): int => (int) $row['account_id'], $accounts);
        $defaults = [
            'actualHeadcount' => 0, 'scheduledDays' => 0, 'scheduledCount' => 0,
            'presentCount' => 0, 'attendanceDataCount' => 0, 'absenteeismCount' => 0,
            'attritionPastCount' => 0, 'unmappedAttritionPastCount' => 0,
            'interviewPopulationCount' => 0, 'nhoPopulationCount' => 0,
            'fstPopulationCount' => 0, 'pstPopulationCount' => 0,
            'projectedToBeEndorsedCount' => 0, 'attritionInterviewToNhoCount' => 0,
            'attritionNhoToFstPstCount' => 0, 'attritionFstToPstCount' => 0,
        ];
        foreach ($weeks as &$week) {
            foreach ($ids as $id) {
                $week['accounts'][] = ['accountId' => $id] + $defaults;
            }
        }
        unset($week);
        $accountPositions = array_flip($ids);

        foreach ($this->attendanceRows($weeks, $ids, $mode, $from, $lastCompletedDay) as $row) {
            $weekIndex = (int) $row['week_index'];
            $position = $accountPositions[(int) $row['account_id']] ?? null;
            if (!isset($weeks[$weekIndex]) || $position === null) {
                continue;
            }
            $weeks[$weekIndex]['effectiveWeekEnd'] = $row['effective_week_end'] ?: null;
            foreach ([
                'scheduled_days' => 'scheduledDays', 'scheduled_count' => 'scheduledCount',
                'present_count' => 'presentCount', 'attendance_data_count' => 'attendanceDataCount',
                'absenteeism_count' => 'absenteeismCount',
            ] as $column => $field) {
                $weeks[$weekIndex]['accounts'][$position][$field] = (int) ($row[$column] ?? 0);
            }
        }

        foreach ($this->workforceRows($weeks, $ids, $mode) as $row) {
            $weekIndex = (int) $row['week_index'];
            $position = $accountPositions[(int) $row['account_id']] ?? null;
            if (!isset($weeks[$weekIndex]) || $position === null) {
                continue;
            }
            foreach ([
                'actual_headcount' => 'actualHeadcount', 'direct_attrition_past_count' => 'attritionPastCount',
                'interview_population_count' => 'interviewPopulationCount',
                'nho_population_count' => 'nhoPopulationCount',
                'fst_population_count' => 'fstPopulationCount',
                'pst_population_count' => 'pstPopulationCount',
                'projected_to_be_endorsed_count' => 'projectedToBeEndorsedCount',
                'attrition_interview_to_nho_count' => 'attritionInterviewToNhoCount',
                'attrition_nho_to_fst_pst_count' => 'attritionNhoToFstPstCount',
                'attrition_fst_to_pst_count' => 'attritionFstToPstCount',
            ] as $column => $field) {
                $weeks[$weekIndex]['accounts'][$position][$field] = (int) ($row[$column] ?? 0);
            }
        }

        foreach ($this->unmappedAttritionRows($weeks, $ids) as $row) {
            $weekIndex = (int) $row['week_index'];
            $position = $accountPositions[(int) $row['account_id']] ?? null;
            if (!isset($weeks[$weekIndex]) || $position === null) {
                continue;
            }
            $count = (int) $row['unmapped_attrition_past_count'];
            $weeks[$weekIndex]['accounts'][$position]['unmappedAttritionPastCount'] = $count;
            $weeks[$weekIndex]['accounts'][$position]['attritionPastCount'] += $count;
        }
        return $weeks;
    }

    /** @param list<int> $ids
     *  @return list<array<string, mixed>>
     */
    private function accounts(array $ids, string $mode): array
    {
        $params = [];
        $filter = $ids === [] ? '' : ' AND gy_acc_id IN (' . $this->idPlaceholders($ids, 'requested', $params) . ')';
        $standard = $mode === 'standard'
            ? ' AND gy_acc_id NOT IN (0, 40, 49) AND gy_acc_name IS NOT NULL AND gy_acc_ghl_name IS NOT NULL'
            : '';
        return $this->rows('SELECT gy_acc_id AS account_id FROM gy_accounts '
            . 'WHERE gy_acc_status = 0 AND gy_acc_id > 0' . $standard . $filter
            . ' ORDER BY gy_acc_id ASC', $params);
    }

    /** @param list<array<string, mixed>> $weeks
     *  @param list<int> $ids
     *  @return list<array<string, mixed>>
     */
    private function attendanceRows(array $weeks, array $ids, string $mode, string $from, string $lastCompletedDay): array
    {
        $params = [
            ':from_date' => $from, ':completed_day' => $lastCompletedDay,
            ':tracker_from' => $from, ':tracker_until' => $lastCompletedDay,
            ':tracker_from_zero' => $from, ':tracker_until_zero' => $lastCompletedDay,
            ':escalation_from' => $from, ':escalation_until' => $lastCompletedDay,
            ':schedule_from' => $from, ':schedule_until' => $lastCompletedDay,
        ];
        $weekSql = $this->weeksSql($weeks, $params, false);
        $agentIds = $this->idPlaceholders($ids, 'attendance_agent', $params);
        $accountIds = $this->idPlaceholders($ids, 'attendance_account', $params);
        $active = $this->activePredicate('aa.last_working_day', 'c.effective_week_end', $mode);
        $sql = "WITH weeks AS ($weekSql),
            active_agents AS (
                SELECT e.gy_emp_id AS employee_id, e.gy_acc_id AS account_id,
                    e.gy_emp_code AS employee_code, e.gy_last_working_day AS last_working_day
                FROM gy_employee e
                WHERE e.gy_emp_type = 1 AND e.gy_acc_id IN ($agentIds)
                    AND EXISTS (SELECT 1 FROM gy_user u WHERE u.gy_user_status = 0
                        AND RTRIM(u.gy_user_code) = RTRIM(e.gy_emp_code))
            ),
            tracker_window AS (
                SELECT t.gy_emp_code,
                    CASE WHEN LEFT(CAST(t.gy_tracker_login AS CHAR), 10) = '0000-00-00'
                        THEN DATE(t.gy_tracker_date) ELSE DATE(t.gy_tracker_login) END AS attendance_day
                FROM gy_tracker t
                WHERE t.gy_tracker_login IS NOT NULL AND (
                    (t.gy_tracker_login >= :tracker_from AND t.gy_tracker_login < DATE_ADD(:tracker_until, INTERVAL 1 DAY))
                    OR (LEFT(CAST(t.gy_tracker_login AS CHAR), 10) = '0000-00-00'
                        AND t.gy_tracker_date >= :tracker_from_zero
                        AND t.gy_tracker_date < DATE_ADD(:tracker_until_zero, INTERVAL 1 DAY)))
                GROUP BY t.gy_emp_code, attendance_day
            ),
            escalation_window AS (
                SELECT sx.gy_emp_code, DATE(sx.gy_sched_day) AS attendance_day
                FROM gy_schedule_escalate sx
                WHERE sx.gy_publish = 1
                    AND sx.gy_sched_day BETWEEN :escalation_from AND :escalation_until
                    AND (
                    (sx.gy_tracker_login IS NOT NULL AND LEFT(CAST(sx.gy_tracker_login AS CHAR), 10) <> '0000-00-00')
                    OR (sx.gy_tracker_logout IS NOT NULL AND LEFT(CAST(sx.gy_tracker_logout AS CHAR), 10) <> '0000-00-00'))
                GROUP BY sx.gy_emp_code, attendance_day
            ),
            schedule_window AS (
                SELECT s.gy_emp_id, s.gy_sched_day
                FROM gy_schedule s
                WHERE s.gy_sched_mode = 1
                    AND s.gy_sched_day BETWEEN :schedule_from AND :schedule_until
                GROUP BY s.gy_emp_id, s.gy_sched_day
            ),
            presence_raw AS (
                SELECT aa.account_id, LOWER(RTRIM(aa.employee_code)) AS employee_code,
                    t.attendance_day
                FROM tracker_window t INNER JOIN active_agents aa
                    ON RTRIM(t.gy_emp_code) = RTRIM(aa.employee_code)
                UNION ALL
                SELECT aa.account_id, LOWER(RTRIM(aa.employee_code)) AS employee_code,
                    sx.attendance_day
                FROM escalation_window sx INNER JOIN active_agents aa
                    ON RTRIM(sx.gy_emp_code) = RTRIM(aa.employee_code)
            ),
            presence AS (
                SELECT DISTINCT account_id, employee_code, attendance_day
                FROM presence_raw
                WHERE attendance_day BETWEEN :from_date AND :completed_day
                    AND attendance_day IS NOT NULL AND YEAR(attendance_day) > 0
            ),
            tracked_days AS (SELECT DISTINCT account_id, attendance_day FROM presence),
            cutoffs AS (
                SELECT w.week_index, MAX(td.attendance_day) AS effective_week_end
                FROM weeks w LEFT JOIN tracked_days td
                    ON td.attendance_day BETWEEN w.week_start AND w.week_end
                GROUP BY w.week_index
            ),
            schedule_pairs AS (
                SELECT DISTINCT w.week_index, aa.account_id, aa.employee_id,
                    DATE(s.gy_sched_day) AS schedule_day,
                    LOWER(RTRIM(aa.employee_code)) AS employee_code,
                    CASE WHEN p.employee_code IS NULL THEN 0 ELSE 1 END AS has_attendance
                FROM schedule_window s INNER JOIN active_agents aa ON s.gy_emp_id = aa.employee_id
                INNER JOIN weeks w ON s.gy_sched_day BETWEEN w.week_start AND w.week_end
                INNER JOIN cutoffs c ON c.week_index = w.week_index
                    AND c.effective_week_end IS NOT NULL
                    AND s.gy_sched_day <= c.effective_week_end
                INNER JOIN tracked_days td ON td.account_id = aa.account_id
                    AND td.attendance_day = DATE(s.gy_sched_day)
                LEFT JOIN presence p ON p.account_id = aa.account_id
                    AND p.employee_code = LOWER(RTRIM(aa.employee_code))
                    AND p.attendance_day = DATE(s.gy_sched_day)
                WHERE ($active)
            )
            SELECT w.week_index, a.gy_acc_id AS account_id,
                c.effective_week_end, COUNT(DISTINCT sp.schedule_day) AS scheduled_days,
                COUNT(sp.employee_id) AS scheduled_count,
                COALESCE(SUM(sp.has_attendance), 0) AS present_count,
                COUNT(DISTINCT CASE WHEN sp.has_attendance = 1
                    THEN CONCAT(sp.employee_code, '::', sp.schedule_day) END) AS attendance_data_count,
                COUNT(sp.employee_id) - COALESCE(SUM(sp.has_attendance), 0) AS absenteeism_count
            FROM weeks w CROSS JOIN gy_accounts a
            INNER JOIN cutoffs c ON c.week_index = w.week_index
            LEFT JOIN schedule_pairs sp ON sp.week_index = w.week_index AND sp.account_id = a.gy_acc_id
            WHERE a.gy_acc_id IN ($accountIds)
            GROUP BY w.week_index, a.gy_acc_id, c.effective_week_end
            ORDER BY w.week_index, a.gy_acc_id";
        return $this->rows($sql, $params);
    }

    private function activePredicate(string $column, string $cutoff, string $mode): string
    {
        $zero = "LEFT(CAST($column AS CHAR), 10) = '0000-00-00'";
        $later = "DATE($column) > $cutoff";
        return $mode === 'single_account_fast'
            ? "($column IS NULL OR $zero OR $later)"
            : "($zero OR $later)";
    }

    /** @param list<array<string, mixed>> $weeks
     *  @param list<int> $ids
     *  @return list<array<string, mixed>>
     */
    private function workforceRows(array $weeks, array $ids, string $mode): array
    {
        $params = [];
        $weekSql = $this->weeksSql($weeks, $params, true);
        $accountIds = $this->idPlaceholders($ids, 'workforce_account', $params);
        $active = $this->activePredicate('e.gy_last_working_day', 'w.cutoff_day', $mode);
        $hire = $mode === 'single_account_fast'
            ? "(e.gy_emp_hiredate IS NULL OR LEFT(CAST(e.gy_emp_hiredate AS CHAR), 10) = '0000-00-00' OR DATE(e.gy_emp_hiredate) <= w.cutoff_day)"
            : "(LEFT(CAST(e.gy_emp_hiredate AS CHAR), 10) = '0000-00-00' OR DATE(e.gy_emp_hiredate) <= w.cutoff_day)";
        $valid = static fn (string $col): string => "($col IS NOT NULL AND LEFT(CAST($col AS CHAR), 10) <> '0000-00-00')";
        $by = static fn (string $col): string => '(' . $valid($col) . " AND DATE($col) <= w.cutoff_day)";
        $after = static fn (string $col): string => "($col IS NULL OR LEFT(CAST($col AS CHAR), 10) = '0000-00-00' OR DATE($col) > w.cutoff_day)";
        $past = static fn (string $col): string => '(' . $valid($col) . " AND DATE($col) BETWEEN w.past_start AND w.cutoff_day)";
        $id = 'e.gy_emp_id';
        $count = static fn (string $condition): string => "COUNT(DISTINCT CASE WHEN $condition THEN e.gy_emp_id END)";
        $hasUser = 'e.has_active_user = 1';
        $fields = [
            'actual_headcount' => $count("$hasUser AND $hire AND $active"),
            'direct_attrition_past_count' => $count($past('e.gy_last_working_day')),
            'interview_population_count' => $count("$hasUser AND " . $past('e.gy_emp_hiredate') . " AND $active"),
            'nho_population_count' => $count($by('e.gy_nhodate') . ' AND ' . $after('e.gy_fststartdate') . ' AND '
                . $after('e.gy_pststartdate') . ' AND ' . $after('e.gy_fullgolivedate') . " AND $active"),
            'fst_population_count' => $count($by('e.gy_fststartdate') . ' AND ' . $after('e.gy_pststartdate') . ' AND '
                . $after('e.gy_fullgolivedate') . " AND $active"),
            'pst_population_count' => $count($by('e.gy_pststartdate') . ' AND ' . $after('e.gy_fullgolivedate') . " AND $active"),
            'projected_to_be_endorsed_count' => $count('(' . $by('e.gy_pstenddate') . ' OR ' . $by('e.gy_certification') . ') AND '
                . $after('e.gy_fullgolivedate') . " AND $active"),
            'attrition_interview_to_nho_count' => $count("$hasUser AND " . $past('e.gy_emp_hiredate') . ' AND '
                . $after('e.gy_nhodate') . " AND $active"),
            'attrition_nho_to_fst_pst_count' => $count($past('e.gy_nhodate') . ' AND ' . $after('e.gy_fststartdate') . ' AND '
                . $after('e.gy_pststartdate') . " AND $active"),
            'attrition_fst_to_pst_count' => $count('(' . $past('e.gy_fststartdate') . ' OR '
                . $past('e.gy_fstenddate') . ') AND ' . $after('e.gy_pststartdate') . " AND $active"),
        ];
        $select = [];
        foreach ($fields as $alias => $expression) {
            $select[] = $expression . ' AS ' . $alias;
        }
        $sql = "WITH weeks AS ($weekSql), agents AS (
                SELECT gy_emp_id, gy_acc_id, gy_emp_hiredate, gy_last_working_day,
                    gy_nhodate, gy_fststartdate, gy_fstenddate, gy_pststartdate,
                    gy_pstenddate, gy_certification, gy_fullgolivedate,
                    EXISTS (SELECT 1 FROM gy_user u WHERE u.gy_user_status = 0
                        AND RTRIM(u.gy_user_code) = RTRIM(src.gy_emp_code)) AS has_active_user
                FROM gy_employee src WHERE gy_emp_type = 1
            )
            SELECT w.week_index, a.gy_acc_id AS account_id, " . implode(', ', $select) . "
            FROM weeks w CROSS JOIN gy_accounts a
            LEFT JOIN agents e ON e.gy_acc_id = a.gy_acc_id
            WHERE a.gy_acc_id IN ($accountIds)
            GROUP BY w.week_index, a.gy_acc_id
            ORDER BY w.week_index, a.gy_acc_id";
        return $this->rows($sql, $params);
    }

    /** @param list<array<string, mixed>> $weeks
     *  @param list<int> $ids
     *  @return list<array<string, mixed>>
     */
    private function unmappedAttritionRows(array $weeks, array $ids): array
    {
        $params = [];
        $weekSql = $this->weeksSql($weeks, $params, true);
        $accountIds = $this->idPlaceholders($ids, 'unmapped_account', $params);
        $sql = "WITH weeks AS ($weekSql)
            SELECT w.week_index, a.gy_acc_id AS account_id,
                COUNT(DISTINCT e.gy_emp_id) AS unmapped_attrition_past_count
            FROM weeks w CROSS JOIN gy_accounts a
            LEFT JOIN gy_employee e ON e.gy_emp_type = 1
                AND (e.gy_acc_id IS NULL OR e.gy_acc_id = 0)
                AND e.gy_emp_account IS NOT NULL
                AND TRIM(e.gy_emp_account) <> ''
                AND (LOWER(TRIM(e.gy_emp_account)) = LOWER(TRIM(a.gy_acc_name))
                    OR LOWER(TRIM(e.gy_emp_account)) = LOWER(TRIM(a.gy_acc_ghl_name)))
                AND e.gy_last_working_day IS NOT NULL
                AND LEFT(CAST(e.gy_last_working_day AS CHAR), 10) <> '0000-00-00'
                AND DATE(e.gy_last_working_day) BETWEEN w.past_start AND w.cutoff_day
            WHERE a.gy_acc_id IN ($accountIds)
            GROUP BY w.week_index, a.gy_acc_id
            ORDER BY w.week_index, a.gy_acc_id";
        return $this->rows($sql, $params);
    }

    /** @param list<array<string, mixed>> $weeks
     *  @param array<string, mixed> $params
     */
    private function weeksSql(array $weeks, array &$params, bool $cutoffs): string
    {
        $parts = [];
        foreach ($weeks as $index => $week) {
            $prefix = 'w' . $index;
            $params[':' . $prefix . '_start'] = $week['weekStart'];
            $params[':' . $prefix . '_end'] = $week['weekEnd'];
            $part = 'SELECT ' . $index . ' AS week_index, :' . $prefix . '_start AS week_start, :'
                . $prefix . '_end AS week_end';
            if ($cutoffs) {
                $cutoff = $week['effectiveWeekEnd'] ?? (new DateTimeImmutable($week['weekStart']))
                    ->sub(new DateInterval('P1D'))->format('Y-m-d');
                $params[':' . $prefix . '_cutoff'] = $cutoff;
                $params[':' . $prefix . '_past'] = (new DateTimeImmutable($cutoff))
                    ->sub(new DateInterval('P41D'))->format('Y-m-d');
                $part .= ', :' . $prefix . '_cutoff AS cutoff_day, :' . $prefix . '_past AS past_start';
            }
            $parts[] = $part;
        }
        return implode(' UNION ALL ', $parts);
    }

    /** @param list<int> $ids
     *  @param array<string, mixed> $params
     */
    private function idPlaceholders(array $ids, string $prefix, array &$params): string
    {
        $parts = [];
        foreach ($ids as $index => $id) {
            $name = ':' . $prefix . $index;
            $params[$name] = $id;
            $parts[] = $name;
        }
        return implode(', ', $parts);
    }

    /** @param array<string, mixed> $params
     *  @return list<array<string, mixed>>
     */
    private function rows(string $sql, array $params): array
    {
        $statement = $this->pdo->prepare($sql);
        if ($statement === false) {
            throw new RuntimeException('Unable to prepare RAMPS metrics query.');
        }
        $statement->execute($params);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }
}
