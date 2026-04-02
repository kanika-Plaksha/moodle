<?php

namespace local_wellbeing\service;
use context_course;
use stdClass;

defined('MOODLE_INTERNAL') || die();

class analysis_service {
    public static function process_submission(int $submissionid): void {
        global $DB;

        //debugging("====================================", DEBUG_DEVELOPER);
        //debugging("WB: process_submission() CALLED", DEBUG_DEVELOPER);
        //debugging("WB: Received submission.id = {$submissionid}", DEBUG_DEVELOPER);

        // 1️⃣ Fetch assign_submission (MAIN record)
        $submission = $DB->get_record(
            'assign_submission',
            ['id' => $submissionid],
            '*',
            IGNORE_MISSING
        );

        if (!$submission) {
            $DB->delete_records('local_wellbeing_metrics', [
                'submissionid' => $submissionid
            ]);
            //debugging("WB ERROR: assign_submission NOT FOUND for id {$submissionid}", DEBUG_DEVELOPER);
            return;
        }

        //debugging("WB: assign_submission.timemodified = {$submission->timemodified}", DEBUG_DEVELOPER);

        // 2️⃣ Fetch corresponding onlinetext
        $textrec = $DB->get_record(
            'assignsubmission_onlinetext',
            ['submission' => $submissionid],
            'id, onlinetext',
            IGNORE_MISSING
        );

        if (!$textrec) {
            $DB->delete_records('local_wellbeing_metrics', [
                'submissionid' => $submissionid
            ]);
            //debugging("WB ERROR: No onlinetext record found for submission {$submissionid}", DEBUG_DEVELOPER);
            return;
        }

        // 3️⃣ Validate text
        if (empty(trim($textrec->onlinetext))) {
            $DB->delete_records('local_wellbeing_metrics', [
                'submissionid' => $submissionid
            ]);
            return;
        }

        $text = trim(strip_tags($textrec->onlinetext));
        //debugging("WB: text length = " . strlen($text), DEBUG_DEVELOPER);

        // 4️⃣ Resolve course
        $assign = $DB->get_record(
            'assign',
            ['id' => $submission->assignment],
            'id, course',
            MUST_EXIST
        );

        $courseid = $assign->course;
        //debugging("WB: courseid = {$courseid}", DEBUG_DEVELOPER);

        // 5️⃣ Check if record exists
        $existing = $DB->get_record(
            'local_wellbeing_metrics',
            ['submissionid' => $submissionid],
            '*',
            IGNORE_MISSING
        );

        // 6️⃣ Call Gemini
        //debugging("WB: Calling Gemini now...", DEBUG_DEVELOPER);

        $metrics = self::call_gemini_api($text,$assign->id);

        if (empty($metrics)) {
            //debugging("WB ERROR: Gemini returned empty metrics", DEBUG_DEVELOPER);
            return;
        }

        //debugging("WB: Gemini metrics = " . json_encode($metrics), DEBUG_DEVELOPER);

        // 7️⃣ UPSERT
        if ($existing) {

            debugging("WB: Performing UPDATE of metrics", DEBUG_DEVELOPER);

            $existing->metrics      = json_encode($metrics);
            $existing->timemodified = time();

            $DB->update_record('local_wellbeing_metrics', $existing);

            //debugging("WB: UPDATE COMPLETE", DEBUG_DEVELOPER);

        } else {

            debugging("WB: Performing INSERT of metrics", DEBUG_DEVELOPER);

            $record = (object)[
                'courseid'     => $courseid,
                'assignmentid' => $submission->assignment,
                'submissionid' => $submissionid,
                'userid'       => $submission->userid,
                'metrics'      => json_encode($metrics),
                'timecreated'  => time(),
                'timemodified' => time(),
            ];

            $DB->insert_record('local_wellbeing_metrics', $record);

            //debugging("WB: INSERT COMPLETE", DEBUG_DEVELOPER);
        }

        //debugging("WB: process_submission DONE ✔", DEBUG_DEVELOPER);
        //debugging("====================================", DEBUG_DEVELOPER);
    }

    private static function call_gemini_api(string $text, int $assignid): array {

        global $DB;

        debugging("WB: Starting Gemini call for assignid {$assignid}", DEBUG_DEVELOPER);

        $apikey = get_config('local_aiemotion', 'geminiapikey');
        if (empty($apikey)) {
            debugging("WB ERROR: Gemini API key missing", DEBUG_DEVELOPER);
            return [];
        }

        /*
        -----------------------------------
        1. GET ASSIGNMENT COURSE
        -----------------------------------
        */

        $cm = get_coursemodule_from_instance('assign', $assignid);

        if (!$cm) {
            debugging("WB ERROR: Course module not found for assignid {$assignid}", DEBUG_DEVELOPER);
            return [];
        }

        $courseid = $cm->course;
        debugging("WB: Course ID = {$courseid}", DEBUG_DEVELOPER);

        /*
        -----------------------------------
        2. GET PROMPT FROM COURSE TABLE
        -----------------------------------
        */

        $courseconfig = $DB->get_record(
            'local_wellbeing_courses',
            ['courseid' => $courseid],
            '*',
            IGNORE_MISSING
        );

        if (!$courseconfig || empty($courseconfig->metrics_prompt)) {
            debugging("WB ERROR: Prompt not found for course {$courseid}", DEBUG_DEVELOPER);
            return [];
        }

        $prompttemplate = $courseconfig->metrics_prompt;

        debugging("WB: Prompt template loaded", DEBUG_DEVELOPER);

        /*
        -----------------------------------
        3. GET SELECTED METRICS FOR ASSIGNMENT
        -----------------------------------
        */

        // $assignmetrics = $DB->get_record(
        //     'local_wb_assign_metrics',
        //     ['assignid' => $cm->id],
        //     '*',
        //     IGNORE_MISSING
        // );

        // if (!$assignmetrics) {
        //     debugging("WB ERROR: No metrics record found for assign {$cm->id}", DEBUG_DEVELOPER);
        //     return [];
        // }

        $raw = $courseconfig->metrics_name_json;

        // Extract text inside <p> tags
        preg_match_all('/<p[^>]*>(.*?)<\/p>/i', $raw, $matches);

        $metrics = array_filter(array_map(function($item) {
            return trim(strip_tags($item), " ,");
        }, $matches[1]));

        if (!$metrics || !is_array($metrics)) {
            debugging("WB ERROR: Metrics JSON invalid", DEBUG_DEVELOPER);
            return [];
        }

        debugging("WB: Selected metrics = " . json_encode($metrics), DEBUG_DEVELOPER);

        /*
        -----------------------------------
        4. PREPARE METRICS LIST
        -----------------------------------
        */

        $metricslist = "";

        foreach ($metrics as $metric) {
            $metricslist .= "- {$metric}\n";
        }

        debugging("WB: Metrics list for prompt = {$metricslist}", DEBUG_DEVELOPER);

        /*
        -----------------------------------
        5. BUILD FINAL PROMPT
        -----------------------------------
        */

        $prompt = str_replace(
            ['{{METRICS}}', '{{TEXT}}'],
            [$metricslist, $text],
            $prompttemplate
        );

        debugging("WB: Final prompt sent to Gemini = {$prompt}", DEBUG_DEVELOPER);

        /*
        -----------------------------------
        6. GEMINI API CALL
        -----------------------------------
        */

        $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=' . $apikey;

        $payload = [
            'contents' => [
                ['parts' => [['text' => $prompt]]]
            ]
        ];

        debugging("WB: Calling Gemini API", DEBUG_DEVELOPER);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_TIMEOUT => 30
        ]);

        $response = curl_exec($ch);
        curl_close($ch);

        if (!$response) {
            debugging("WB ERROR: No response from Gemini", DEBUG_DEVELOPER);
            return [];
        }

        debugging("WB: Raw Gemini response = {$response}", DEBUG_DEVELOPER);

        $decoded = json_decode($response, true);
        $output = $decoded['candidates'][0]['content']['parts'][0]['text'] ?? '';

        /*
        -----------------------------------
        7. CLEAN RESPONSE
        -----------------------------------
        */

        $output = preg_replace('/```json|```/', '', $output);
        $output = trim($output);

        debugging("WB: Gemini cleaned output = {$output}", DEBUG_DEVELOPER);

        $metricsresult = json_decode($output, true);

        if (!is_array($metricsresult)) {
            debugging("WB ERROR: Gemini output not valid JSON", DEBUG_DEVELOPER);
            return [];
        }

        debugging("WB: Gemini parsed result = " . json_encode($metricsresult), DEBUG_DEVELOPER);

        /*
        -----------------------------------
        8. FILTER ONLY SELECTED METRICS
        -----------------------------------
        */

        $filtered = [];

        foreach ($metrics as $metric) {
            if (isset($metricsresult[$metric])) {
                $filtered[$metric] = (int)$metricsresult[$metric];
            }
        }

        debugging("WB: Filtered result (selected metrics only) = " . json_encode($filtered), DEBUG_DEVELOPER);

        return $filtered;
    }

    public static function get_course_aggregated_metrics(int $courseid): array {
        global $DB;

        $records = $DB->get_records(
            'local_wellbeing_metrics',
            ['courseid' => $courseid]
        );

        $totals = [];

        foreach ($records as $record) {

            $metrics = json_decode($record->metrics, true);

            if (!is_array($metrics)) {
                continue;
            }

            foreach ($metrics as $key => $value) {

                if (!isset($totals[$key])) {
                    $totals[$key] = 0;
                }

                $totals[$key] += (int)$value;
            }
        }

        return $totals;
    }

    // public static function get_user_course_metrics(int $courseid, int $userid): array {
    //     global $DB;

    //     $records = $DB->get_records(
    //         'local_wellbeing_metrics',
    //         [
    //             'courseid' => $courseid,
    //             'userid'   => $userid
    //         ]
    //     );

    //     $totals = [];

    //     foreach ($records as $record) {

    //         $metrics = json_decode($record->metrics, true);

    //         if (!is_array($metrics)) {
    //             continue;
    //         }

    //         foreach ($metrics as $key => $value) {

    //             if (!isset($totals[$key])) {
    //                 $totals[$key] = 0;
    //             }

    //             $totals[$key] += (int)$value;
    //         }
    //     }

    //     return $totals;
    // }

    public static function get_student_assignment_progress($courseid, $userid) {
        global $DB;

        $sql = "
            SELECT a.name,
                s.status,
                s.timemodified
            FROM {assign_submission} s
            JOIN {assign} a ON a.id = s.assignment
            WHERE a.course = ?
            AND s.userid = ?
            ORDER BY s.timemodified ASC
        ";

        return $DB->get_records_sql($sql, [$courseid, $userid]);
    }
    public static function local_wellbeing_store_previous_month_if_needed($result, $courseid, $userid) {
        global $DB;

        // Current & last month
        $currentMonth = strtotime(date('Y-m-01'));
        $lastMonth = strtotime('-1 month', $currentMonth);

        // Check if already stored
        $exists = $DB->record_exists('local_wb_metrics_history', [
            'snapshot_month' => $lastMonth
        ]);

        if ($exists) {
            return;
        }
         
        $combined = [];


    foreach ($result as $item) {

        if (empty($item['metrics'])) {
            continue;
        }

        foreach ($item['metrics'] as $metric => $score) {

            if (!isset($combined[$metric])) {
                $combined[$metric] = 0;
            }

            $combined[$metric] += $score;
        }
    }


    $totalScore = array_sum($combined);
    $metricCount = count($combined);


    $minScore = $metricCount;
    $maxScore = $metricCount * 7;

    $percentage = $metricCount > 0
    ? round((($totalScore - $minScore) / ($maxScore - $minScore)) * 100)
    : 0;


               
            // ✅ Store
            $history = new stdClass();
            $history->submissionid = $item['submissionid'];
            $history->assignid = $item['assignid'];
            $history->userid = $userid;
            $history->courseid = $courseid;
            $history->metrics = $percentage;
            $history->snapshot_month = $lastMonth;
            $history->timecreated = time();

            $DB->insert_record('local_wb_metrics_history', $history);
        
    }
    public static function get_student_assignment_metrics_filtered($courseid, $userid) {
    

    global $DB;

        $sql = "
            SELECT a.id as assignid,
                a.name,
                m.metrics,m.submissionid
            FROM {local_wellbeing_metrics} m
            JOIN {assign_submission} s ON s.id = m.submissionid
            JOIN {assign} a ON a.id = s.assignment
            WHERE a.course = ?
            AND s.userid = ?
        ";

        $records = $DB->get_records_sql($sql, [$courseid, $userid]);


        $result = [];

        foreach ($records as $record) {

            $rawmetrics = json_decode($record->metrics, true);

            if (empty($rawmetrics)) {
                continue;
            }

            /* ---------- FETCH SELECTED METRICS ---------- */
            $assignmetrics = $DB->get_record(
                'local_wb_assign_metrics',
                ['assignid' => $record->assignid],
                'metricname',
                IGNORE_MISSING
            );

            if (empty($assignmetrics) || empty($assignmetrics->metricname)) {
                continue;
            }

            /* ---------- DECODE SELECTED ---------- */
            $selectedmetrics = json_decode($assignmetrics->metricname, true);

            if (empty($selectedmetrics) || !is_array($selectedmetrics)) {
                continue;
            }

            /* ---------- FILTER ---------- */
            $filtered = [];

            foreach ($selectedmetrics as $metric) {
                $metric = trim($metric);
                $filtered[$metric] = $rawmetrics[$metric] ?? 0;
            }
            
            /* ---------- STORE RESULT ---------- */
            $result[] = [
            'assignment' => $record->name,
            'assignid' => $record->assignid ?? 0,
            'submissionid' => $record->submissionid ?? 0,
            'metrics' => $filtered
        ]; 
        }
        self::local_wellbeing_store_previous_month_if_needed($result, $courseid, $userid);
        return $result;
    }
    public static function get_assignment_submission_overview($courseid) {

        global $DB;

        $totalstudents = count(get_enrolled_users(
            context_course::instance($courseid),
            'mod/assign:submit'
        ));

        $sql = "
        SELECT
        a.id,
        a.name AS assignment,
        COUNT(DISTINCT s.userid) AS submitted
        FROM {assign} a
        LEFT JOIN {assign_submission} s
        ON s.assignment = a.id
        AND s.status = 'submitted'
        WHERE a.course = :courseid
        GROUP BY a.id,a.name
        ORDER BY a.duedate
        ";

        $data = $DB->get_records_sql($sql,['courseid'=>$courseid]);

        foreach ($data as $d) {
            $d->totalstudents = $totalstudents;
        }

        return $data;
    }
    public static function get_monthly_scores_from_history($userid, $currentScore) {
    global $DB;

    $data = [];

    /* ---------- STEP 1: GET DB DATA ---------- */
    $records = $DB->get_records_sql("
        SELECT id,metrics, snapshot_month
        FROM {local_wb_metrics_history}
        WHERE userid = ?
    ", [$userid]);

    foreach ($records as $row) {

        $monthKey = date('Y-m', $row->snapshot_month);

        $metrics = json_decode($row->metrics, true);

        if (!is_array($metrics)) {
            continue;
        }

        $totalScore = array_sum($metrics);
        $metricCount = count($metrics);

        if ($metricCount == 0) {
            continue;
        }

        $minScore = $metricCount;
        $maxScore = $metricCount * 7;

        $percentage = round((($totalScore - $minScore) / ($maxScore - $minScore)) * 100);

        $data[$monthKey] = $percentage;
    }

    /* ---------- STEP 2: GENERATE LAST 6 MONTHS ---------- */
    $labels = [];
    $values = [];

    $currentYear = date('Y');
    $currentMonth = date('n'); // 1–12

    for ($m = 1; $m <= $currentMonth; $m++) {

        $timestamp = strtotime("$currentYear-$m-01");

        $monthKey = date('Y-m', $timestamp);
        $monthLabel = date('M', $timestamp);

        $labels[] = $monthLabel;

        /* if no data → 0 */
        $values[] = $data[$monthKey] ?? 0;
    }

    /* ---------- STEP 3: CURRENT MONTH ---------- */
    $currentMonthKey = date('Y-m');
    $currentMonthLabel = date('M');

    $currentIndex = array_search($currentMonthLabel, $labels);

    if ($currentIndex !== false) {
        $values[$currentIndex] = $currentScore;
    }

    return [
        'labels' => $labels,
        'values' => $values,
        'currentIndex' => $currentIndex
    ];
    }
    public static function get_teacher_avg_score($courseid) {
    global $DB;

     "<pre>===== DEBUG: TEACHER AVG SCORE =====\n";

    /* STEP 1 → LATEST MONTH */
    $latestmonth = $DB->get_field_sql("
        SELECT MAX(snapshot_month)
        FROM {local_wb_metrics_history}
        WHERE courseid = ?
    ", [$courseid]);

     "Latest Month: " . $latestmonth . "\n";

    if (!$latestmonth) {
         "❌ No latest month found\n";
         "</pre>";
        return 0;
    }

    /* STEP 2 → FETCH METRICS */
    $records = $DB->get_records_sql("
        SELECT 
            CONCAT(userid, '-', id) as id,
            metrics
        FROM {local_wb_metrics_history}
        WHERE courseid = ?
        AND snapshot_month = ?
    ", [$courseid, $latestmonth]);

     "Total Records Fetched: " . count($records) . "\n";

    if (empty($records)) {
         "❌ No records found\n";
         "</pre>";
        return 0;
    }

    /* STEP 3 → CALCULATE AVG */
    $totalScore = 0;
    $countRows = 0;

    foreach ($records as $rec) {

         "---- Record {$rec->id} ----\n";
         "Raw Metrics: " . $rec->metrics . "\n";

        if (is_numeric($rec->metrics)) {
            $totalScore += $rec->metrics;
            $countRows++;

             "✔ Added: {$rec->metrics}\n";
        } else {
             "⚠️ Skipping (not numeric)\n";
        }
    }

     "Total Score: $totalScore\n";
     "Valid Rows Count: $countRows\n";

    /* FINAL AVG */
    $avgscore = $countRows ? round($totalScore / $countRows) : 0;

     "✅ FINAL AVG SCORE: $avgscore\n";
     "===========================</pre>";

    return $avgscore;
    }
    public static function get_teacher_monthly_trend($courseid) {
    global $DB;

    /* =========================
       STEP 1 → GET ALL MONTHS
    ========================= */
    $months = $DB->get_records_sql("
        SELECT DISTINCT snapshot_month
        FROM {local_wb_metrics_history}
        WHERE courseid = ?
        ORDER BY snapshot_month ASC
    ", [$courseid]);

    if (empty($months)) {
        return [
            'labels' => [],
            'values' => []
        ];
    }

    $labels = [];
    $values = [];

    /* =========================
       STEP 2 → LOOP MONTHS
    ========================= */
    foreach ($months as $m) {

        $month = $m->snapshot_month;

      

        /* FETCH ALL RECORDS FOR MONTH */
        $records = $DB->get_records_sql("
            SELECT metrics
            FROM {local_wb_metrics_history}
            WHERE courseid = ?
            AND snapshot_month = ?
        ", [$courseid, $month]);

        $monthTotal = 0;
        $studentCount = 0;

        foreach ($records as $rec) {

            $metrics = json_decode($rec->metrics, true);

            // 🔥 HANDLE SINGLE NUMBER CASE (your bug earlier)
            if (is_numeric($metrics)) {
                $monthTotal += $metrics;
                $studentCount++;
                continue;
            }

            if (!is_array($metrics)) {
                continue;
            }

            $sum = 0;
            $count = 0;

            foreach ($metrics as $value) {
                if (is_numeric($value)) {
                    $sum += $value;
                    $count++;
                }
            }

            if ($count > 0) {
                $avg = $sum / $count;
                $monthTotal += $avg;
                $studentCount++;
            }
        }

        $monthAvg = $studentCount ? round($monthTotal / $studentCount) : 0;


        /* FORMAT MONTH LABEL */
        $labels[] = date('M', $month);
        $values[] = $monthAvg;
    }

    return [
        'labels' => $labels,
        'values' => $values
    ];
    }

}
