// Read-only parity oracle. Run through tests/ramps_parity.php; never used by the API.
import fs from 'node:fs';
import path from 'node:path';
import { pathToFileURL } from 'node:url';

const apiRoot = path.resolve(path.dirname(new URL(import.meta.url).pathname), '..');
const hrisRoot = process.env.HRIS_ROOT || path.resolve(apiRoot, '../SiBS-HRIS-Server');
const moduleUrl = (relative) => pathToFileURL(path.join(hrisRoot, relative)).href;
const dotenv = (await import(moduleUrl('node_modules/dotenv/lib/main.js'))).default;
const mysql = (await import(moduleUrl('node_modules/mysql2/promise.js'))).default;
dotenv.config({ path: path.join(apiRoot, '.env'), quiet: true });

const [mode, from, end, accountId, cutoff, lastCompletedDay] = process.argv.slice(2);
if (!['standard', 'single_account_fast'].includes(mode) || !/^\d{4}-\d{2}-\d{2}$/.test(from || '') ||
    !/^\d{4}-\d{2}-\d{2}$/.test(end || '') || !/^\d{4}-\d{2}-\d{2}$/.test(cutoff || '') ||
    !/^\d{4}-\d{2}-\d{2}$/.test(lastCompletedDay || '')) {
  throw new Error('Invalid parity-test arguments.');
}

const connection = await mysql.createConnection({
  host: process.env.DB1_HOST,
  port: Number(process.env.DB1_PORT),
  database: process.env.DB1_NAME,
  user: process.env.DB1_USER,
  password: process.env.DB1_PASSWORD,
  dateStrings: true,
});
const query = async (sql, args = []) => (await connection.query(sql, args))[0];
const source = await import(moduleUrl('src/services/kronos/kronosWeeklyHiringReads.js'));
const dependencies = {
  getEmployees: () => query(`SELECT gy_emp_id, gy_emp_type, gy_emp_code, gy_acc_id, gy_emp_account,
    gy_emp_hiredate, gy_last_working_day, gy_nhodate, gy_fststartdate, gy_fstenddate,
    gy_pststartdate, gy_pstenddate, gy_certification, gy_fullgolivedate FROM gy_employee`),
  getUsers: () => query('SELECT gy_user_code, gy_user_status FROM gy_user'),
  getTrackers: () => query(`SELECT gy_emp_code, gy_tracker_date, gy_tracker_login FROM gy_tracker
    WHERE (gy_tracker_login >= ? AND gy_tracker_login < DATE_ADD(?, INTERVAL 1 DAY))
      OR (LEFT(CAST(gy_tracker_login AS CHAR), 10) = '0000-00-00'
        AND gy_tracker_date >= ? AND gy_tracker_date < DATE_ADD(?, INTERVAL 1 DAY))`, [from, end, from, end]),
  getSchedules: () => query(`SELECT gy_emp_id, gy_sched_day, gy_sched_mode FROM gy_schedule
    WHERE gy_sched_day BETWEEN ? AND ?`, [from, end]),
  getScheduleEscalations: () => query(`SELECT gy_emp_code, gy_publish, gy_tracker_login,
    gy_tracker_logout, gy_sched_day FROM gy_schedule_escalate
    WHERE gy_sched_day BETWEEN ? AND ?`, [from, end]),
  getAccounts: () => query('SELECT gy_acc_id, gy_acc_name, gy_acc_ghl_name, gy_dept_id, gy_acc_status FROM gy_accounts'),
  getDepartments: () => query('SELECT id_department, name_department FROM gy_department'),
};
const fields = ['actualHeadcount', 'scheduledDays', 'scheduledCount', 'presentCount',
  'attendanceDataCount', 'absenteeismCount', 'attritionPastCount', 'unmappedAttritionPastCount',
  'interviewPopulationCount', 'nhoPopulationCount', 'fstPopulationCount', 'pstPopulationCount',
  'projectedToBeEndorsedCount', 'attritionInterviewToNhoCount',
  'attritionNhoToFstPstCount', 'attritionFstToPstCount'];
const metrics = (row) => Object.fromEntries(fields.map((field) => [field, row[field]]));

try {
  if (mode === 'standard') {
    const pastStart = new Date(Date.parse(`${cutoff}T00:00:00Z`) - 41 * 86400000).toISOString().slice(0, 10);
    const rows = await source.getWeeklyAccountRows({
      weekStart: from, attendanceEnd: cutoff, cutoff, pastStart, pastEnd: cutoff,
      cluster: 'All', account: 'All',
    }, dependencies);
    process.stdout.write(JSON.stringify(rows.map((row) => ({ accountId: Number(row.id), metrics: metrics(row) }))));
  } else {
    const snapshot = await source.getWeeklyHiringSnapshot(dependencies);
    const routeSource = fs.readFileSync(path.join(hrisRoot, 'src/routes/weeklyHiringPlan.js'), 'utf8');
    const base = routeSource.slice(routeSource.indexOf('const fastSingleAccountBaseCache = new Map();'),
      routeSource.indexOf('\nasync function primeFastSingleAccountForecastWindow'));
    const overview = routeSource.slice(routeSource.indexOf('async function getFastSingleAccountOverviewKronosRow({'),
      routeSource.indexOf('\nasync function resolveSelectedAccountIds'));
    if (!base || !overview) throw new Error('HRIS fast-path source anchors changed.');
    const helpers = `
      const normalizeDateKey = (v) => v == null ? null : String(v).slice(0, 10);
      const minDateKey = (a,b) => a && b ? (a < b ? a : b) : a || b;
      const addDaysToDateKey = (v,n) => { const d = new Date(v + 'T00:00:00Z'); d.setUTCDate(d.getUTCDate()+n); return d.toISOString().slice(0,10); };
      const getPhilippineYesterdayDateKey = () => lastCompletedDay;
      const clampWeekEndToLastCompletedDay = (a,b) => { const capped=minDateKey(b,getPhilippineYesterdayDateKey()); return capped >= a ? capped : null; };
      const getWeeklyHiringSnapshot = async () => snapshot;
      const hrisTables = {sibsCluster:'unused'};
      const hrisDb = {query:async () => [[{cluster_name:'unused'}]]};
      const fastSingleAccountOverviewCache = new Map();
      const FAST_SINGLE_ACCOUNT_OVERVIEW_CACHE_TTL_MS = 20_000;
      const console = {log:()=>{}};
    `;
    const fast = new Function('snapshot', 'lastCompletedDay', `${helpers}\n${base}\n${overview}\nreturn getFastSingleAccountOverviewKronosRow;`)(snapshot, lastCompletedDay);
    const result = await fast({ accountId: Number(accountId), weekStart: from, weekEnd: end });
    process.stdout.write(JSON.stringify({ effectiveWeekEnd: result.effectiveWeekEnd,
      metrics: result.row ? metrics(result.row) : null }));
  }
} finally {
  await connection.end();
}
