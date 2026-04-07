
<?php

use core_files\external\delete\draft;

require('../../config.php');

use local_wellbeing\service\analysis_service;
use core\chart_series;
use core\chart_bar;
use core\chart_pie;

$courseid = required_param('id', PARAM_INT);

$course = get_course($courseid);
$context = context_course::instance($courseid);

require_login($course);

$PAGE->set_url('/local/wellbeing/dashboard.php', ['id' => $courseid]);
$PAGE->set_context($context);
$PAGE->set_title('Wellbeing Dashboard');
$PAGE->set_heading($course->fullname);
$PAGE->set_pagelayout('report');

echo $OUTPUT->header();

if (has_capability('moodle/course:update', $context)) {
    $totals = analysis_service::get_course_aggregated_metrics($courseid);
    $isstudent = false;
} else {
    $totals = analysis_service::get_student_assignment_metrics_filtered($courseid, $USER->id);
    $isstudent = true;
}
if (empty($totals)) {
    echo $OUTPUT->notification('No wellbeing data available yet.', 'info');
    echo $OUTPUT->footer();
    exit;
}

/* --------------------------------------------------
   DATA PREPARATION
-------------------------------------------------- */

$totalresponses = array_sum($totals);

$percentages = [];
 $combined = [];
foreach ($totals as $item) {
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

/* --------------------------------------------------
   OVERALL WELLBEING SCORE
-------------------------------------------------- */

$totalScore = array_sum($combined);
$metricCount = count($combined);

$minScore = $metricCount;
$maxScore = $metricCount * 7;

$percentage = $metricCount > 0
    ? round((($totalScore - $minScore) / ($maxScore - $minScore)) * 100)
    : 0;


/* --------------------------------------------------
   ASSIGNMENT COMPLETION DATA
-------------------------------------------------- */

$progressdata = analysis_service::get_student_assignment_progress($courseid, $USER->id);
$totalassignments = 0;
$completed = 0;
$draft = 0;

if (!empty($progressdata)) {

    $totalassignments = count($progressdata);

    foreach ($progressdata as $item) {
        if ($item->status === 'submitted') {
            $completed++;
        } else if ($item->status === 'draft') {
            $draft++;
        }
    }
}

$completionpercentage = $totalassignments > 0
    ? round(($completed / $totalassignments) * 100)
    : 0;

$draftpercentage = $totalassignments > 0
    ? round(($draft / $totalassignments) * 100)
    : 0;

/* =========================
   HERO HEADER + STATS CARDS
========================= */


/* ✅ STYLE FIRST (IMPORTANT) */
echo '
<style>
/* OUTER CONTAINER */
.wb-dashboard-wrap {
    border: grey 1px solid;
    padding: 50px 30px;
    border-radius: 16px;
}

/* MINI CARDS */
.wb-mini-card {
    padding: 30px 18px;
    border-radius: 14px;
    color: #fff;
    box-shadow: 0 8px 20px rgba(0,0,0,0.08);
    transition: 0.3s;
}

.wb-mini-card:hover {
    transform: translateY(-5px);
}

/* COLORS */
.wb-card-orange {
    background: linear-gradient(135deg, #f97316, #fb923c);
}

.wb-card-green {
    background: linear-gradient(135deg, #22c55e, #4ade80);
}

.wb-card-red {
    background: linear-gradient(135deg, #ef4444, #f87171);
}

.wb-card-blue {
    background: linear-gradient(135deg, #06b6d4, #22d3ee);
}

/* TEXT */
.wb-mini-title {
    font-size: 13px;
    opacity: 0.9;
}

.wb-mini-value {
    font-size: 22px;
    font-weight: 700;
    margin: 5px 0;
}

.wb-mini-sub {
    font-size: 12px;
    opacity: 0.85;
}

</style>
';

/* =========================
   WRAPPER START
========================= */
if ($isstudent) {

    echo html_writer::start_div('wb-dashboard-wrap');
    echo html_writer::start_div('wb-header');

    echo html_writer::start_div('wb-header-content');

    echo html_writer::tag('h2', 'Welcome 👋', ['class'=>'wb-header-title']);

    echo html_writer::tag(
        'p',
        'Student Wellbeing Overview – here’s your current progress and insights',
        ['class'=>'wb-header-sub']
    );

    echo html_writer::end_div();

    echo html_writer::end_div();

/* =========================
   STATS CARDS
========================= */

echo html_writer::start_div('row g-3');

/* CARD 1 */
echo html_writer::start_div('col-md-3');
echo html_writer::start_div('wb-mini-card wb-card-orange');

echo html_writer::tag('div', 'Overall Score', ['class'=>'wb-mini-title']);
echo html_writer::tag('h3', "$percentage%", ['class'=>'wb-mini-value']);
echo html_writer::tag('div', 'Wellbeing Index', ['class'=>'wb-mini-sub']);

echo html_writer::end_div();
echo html_writer::end_div();

/* CARD 2 */
echo html_writer::start_div('col-md-3');
echo html_writer::start_div('wb-mini-card wb-card-green');

echo html_writer::tag('div', 'Assignments', ['class'=>'wb-mini-title']);
echo html_writer::tag('h3', "$totalassignments", ['class'=>'wb-mini-value']);
echo html_writer::tag('div', 'Total Given', ['class'=>'wb-mini-sub']);

echo html_writer::end_div();
echo html_writer::end_div();

/* CARD 3 */
echo html_writer::start_div('col-md-3');
echo html_writer::start_div('wb-mini-card wb-card-red');

echo html_writer::tag('div', 'Completion', ['class'=>'wb-mini-title']);
echo html_writer::tag('h3', "$completionpercentage%", ['class'=>'wb-mini-value']);
echo html_writer::tag('div', 'Work Progress', ['class'=>'wb-mini-sub']);

echo html_writer::end_div();
echo html_writer::end_div();

/* CARD 4 */
echo html_writer::start_div('col-md-3');
echo html_writer::start_div('wb-mini-card wb-card-blue');

echo html_writer::tag('div', 'Status', ['class'=>'wb-mini-title']);

if ($percentage < 40) {
    $status = "Low 💙";
} elseif ($percentage < 70) {
    $status = "Balanced 🌱";
} else {
    $status = "Great 🌟";
}

echo html_writer::tag('h3', $status, ['class'=>'wb-mini-value']);
echo html_writer::tag('div', 'Current Mood', ['class'=>'wb-mini-sub']);

echo html_writer::end_div();
echo html_writer::end_div();

echo html_writer::end_div(); // row

/* =========================
   WRAPPER END
========================= */

echo html_writer::end_div();
}


/* =========================
   ROW 2 → TREND + PROGRESS
========================= */

/* ✅ STYLE (LOAD FIRST) */
echo '
<style>

.wb-section {
    padding: 20px;
    border-radius: 16px;
    margin-top: 20px;
}

/* CARD */
.wb-card {
    background: #ffffff;
    border-radius: 14px;
    padding: 18px;
    box-shadow: 0 6px 18px rgba(0,0,0,0.06);
    height: 100%;
}

/* TITLE */
.wb-card-title {
    font-size: 15px;
    font-weight: 600;
    color: #0f172a;
    margin-bottom: 10px;
}

/* CHART */
.wb-chart-container {
    height: 300px;
}

/* CENTER ALIGN */
.wb-center {
    text-align: center;
}
/* =========================
   METRICS TABLE UI
========================= */

.wb-table-card {
    background: #ffffff;
    border-radius: 16px;
    padding: 20px;
    box-shadow: 0 8px 24px rgba(0,0,0,0.06);
}

.wb-table-title {
    font-size: 18px;
    font-weight: 600;
    margin-bottom: 15px;
    color: #0f172a;
}

/* TABLE */
.wb-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0 10px;
}

/* HEADER */
.wb-table thead th {
    font-size: 12px;
    text-transform: uppercase;
    color: #6b7280;
    font-weight: 600;
    padding: 10px;
    text-align: left;
}

/* ROW CARD STYLE */
.wb-table tbody tr {
    background: #f9fafb;
    border-radius: 10px;
    transition: 0.2s;
}

.wb-table tbody tr:hover {
    background: #f1f5f9;
    transform: scale(1.01);
}

/* CELLS */
.wb-table td {
    padding: 12px;
    font-size: 14px;
    color: #374151;
}

/* FIRST COLUMN */
.wb-assignment {
    font-weight: 600;
    color: #0f172a;
}

/* METRIC BADGES */
.wb-badge {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 500;
    background: #e0f2fe;
    color: #0369a1;
}
  /* =========================
   EMOTIONAL BREAKDOWN
========================= */

.wb-emotional-wrap {
    background: #f8fafc;
    border-radius: 18px;
    padding: 25px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 20px;
}

/* LEFT */
.wb-emotional-left {
    max-width: 30%;
}

.wb-emotional-title {
    font-size: 22px;
    font-weight: 700;
    color: #0f172a;
}

.wb-emotional-sub {
    font-size: 14px;
    color: #64748b;
    margin-top: 6px;
}

/* RIGHT */
.wb-emotional-cards {
    display: flex;
    gap: 18px;
    flex-wrap: wrap;
}

/* CARD */
.wb-em-card {
    width: 180px;
    border-radius: 16px;
    padding: 18px;
    color: #0f172a;
    background: #fff;
    box-shadow: 0 10px 25px rgba(0,0,0,0.08);
    position: relative;
    transition: 0.3s;
}

.wb-em-card:hover {
    transform: translateY(-6px);
}

/* EMOJI */
.wb-em-icon {
    position: absolute;
    top: -18px;
    left: 50%;
    transform: translateX(-50%);
    background: #ffffff;
    border-radius: 50%;
    padding: 10px;
    font-size: 20px;
    box-shadow: 0 4px 10px rgba(0,0,0,0.1);
}

/* TEXT */
.wb-em-label {
    text-align: center;
    font-weight: 600;
    margin-top: 18px;
    font-size: 14px;
}

.wb-em-desc {
    text-align: center;
    font-size: 12px;
    color: #475569;
    margin-top: 6px;
    min-height: 40px;
}

/* VALUE */
.wb-em-value {
    text-align: center;
    font-size: 26px;
    font-weight: 700;
    margin-top: 12px;
}

.wb-card-emotional-green {
    background: linear-gradient(135deg, #f0fdf4, #dcfce7);
}

</style>
';

/* =========================
   DATA
========================= */

$trend = \local_wellbeing\service\analysis_service::get_monthly_scores_from_history(
    $USER->id,
    $percentage
);

$labels = $trend['labels'] ?? [];
$values = $trend['values'] ?? [];
$currentIndex = $trend['currentIndex'] ?? -1;

$labels_json = json_encode(array_values($labels));
$values_json = json_encode(array_values($values));
$current_index = json_encode($currentIndex);

/* =========================
   UI START
========================= */
if ($isstudent) {
echo html_writer::start_div('wb-section');

echo html_writer::start_div('row g-4');

/* =====================================
   LEFT → WELLBEING TREND
===================================== */

echo html_writer::start_div('col-md-8');

echo html_writer::start_div('wb-card');

echo html_writer::tag('div', 'Wellbeing Trend', ['class'=>'wb-card-title']);

echo '<div class="wb-chart-container">
        <canvas id="wellbeingLineChart"></canvas>
      </div>';

echo html_writer::end_div();
echo html_writer::end_div();
}
/* =====================================
   RIGHT → ASSIGNMENT PROGRESS
===================================== */

if ($isstudent) {

echo html_writer::start_div('col-md-4');

echo html_writer::start_div('wb-card wb-center');

echo html_writer::tag('div', 'Assignment Progress Overview', ['class'=>'wb-card-title']);

/* LOGIC */

if ($completionpercentage < 25) {
    $progresscolor = "#dc3545";
    $icon = "🚀";
} elseif ($completionpercentage < 50) {
    $progresscolor = "#fd7e14";
    $icon = "📈";
} elseif ($completionpercentage < 75) {
    $progresscolor = "#ffc107";
    $icon = "🎯";
} elseif ($completionpercentage < 100) {
    $progresscolor = "#20c997";
    $icon = "💪";
} else {
    $progresscolor = "#28a745";
    $icon = "🏆";
}

/* GAUGE */

echo html_writer::start_div('', [
    'style' => "
        width:160px;
        height:160px;
        margin:15px auto;
        border-radius:50%;
        background:conic-gradient($progresscolor {$completionpercentage}%, #e9ecef {$completionpercentage}%);
        display:flex;
        align-items:center;
        justify-content:center;
    "
]);

/* INNER WHITE CIRCLE */
echo html_writer::start_div('', [
    'style' => "
        width:60px;
        height:60px;
        border-radius:50%;
        background:#ffffff;
        display:flex;
        align-items:center;
        justify-content:center;
        font-size:36px;
        box-shadow:0 4px 10px rgba(0,0,0,0.08);
    "
]);

echo $icon;

echo html_writer::end_div(); // inner white

echo html_writer::end_div(); // outer

/* TEXT */

echo html_writer::tag('div', "$completionpercentage% Completed", [
    'style'=>'font-weight:600;font-size:16px;margin-top:5px;'
]);

echo html_writer::tag(
    'div',
    "$completed Submitted • $draft Draft • $totalassignments Total",
    ['style'=>'font-size:13px;color:#6b7280;']
);

/* BAR */

echo html_writer::start_div('', [
    'style' => "
        width:100%;
        height:10px;
        border-radius:8px;
        overflow:hidden;
        display:flex;
        margin-top:15px;
        background:#e9ecef;
    "
]);

echo html_writer::div('', '', [
    'style' => "width:{$completionpercentage}%; background:#28a745;"
]);

echo html_writer::div('', '', [
    'style' => "width:{$draftpercentage}%; background:#fd7e14;"
]);

echo html_writer::end_div();

/* LEGEND */

echo html_writer::start_div('d-flex justify-content-center mt-3', [
    'style' => 'gap:15px; font-size:13px;'
]);

echo html_writer::tag('span', '🟢 Submitted', ['style'=>'color:#28a745;']);
echo html_writer::tag('span', '🟠 Draft', ['style'=>'color:#fd7e14;']);
echo html_writer::tag('span', '⚪ Remaining', ['style'=>'color:#6b7280;']);

echo html_writer::end_div();

echo html_writer::end_div(); // card
echo html_writer::end_div(); // col
}

echo html_writer::end_div(); // row
echo html_writer::end_div(); // section

/* =========================
   CHART JS
========================= */

$PAGE->requires->js_init_code("
require(['https://cdn.jsdelivr.net/npm/chart.js'], function(Chart) {

const ctx = document.getElementById('wellbeingLineChart').getContext('2d');

const labels = $labels_json;
const data = $values_json;
const currentIndex = $current_index;

const gradient = ctx.createLinearGradient(0, 0, 0, 220);
gradient.addColorStop(0, 'rgba(34,197,94,0.35)');
gradient.addColorStop(1, 'rgba(34,197,94,0.02)');

new Chart(ctx, {
    type: 'line',
    data: {
        labels: labels,
        datasets: [{
            data: data,
            borderColor: '#16a34a',
            backgroundColor: gradient,
            tension: 0.5,
            fill: true,
            pointRadius: data.map((_, i) => i === currentIndex ? 6 : 3),
            pointBackgroundColor: data.map((_, i) => i === currentIndex ? '#16a34a' : '#bbf7d0')
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
         scales: {
        x: {
            grid: {
                display: false   // ❌ removes vertical lines
            }
        },
        y: {
            grid: {
                display: false   // ❌ removes horizontal lines
            },
            beginAtZero: true
        }
        }
    }
});

});
");

$assignmentmetrics = analysis_service::get_student_assignment_metrics_filtered($courseid, $USER->id);
//  echo "<pre>";
// echo "📊 RAW TOTALS:\n";
// print_r($assignmentmetrics);
// echo "</pre>";
if ($isstudent) {
    if (!empty($assignmentmetrics)) {

        echo html_writer::start_div('row mt-4');
        echo html_writer::start_div('col-12');

        echo html_writer::start_div('card shadow-sm p-4');

        echo html_writer::tag('h4', 'Assignment-wise Wellbeing Metrics');

        /* ---------- CREATE METRIC MAP (M1, M2...) ---------- */

        $metricMap = [];
        $index = 1;

        /* 🔥 collect ALL unique metrics */
        foreach ($assignmentmetrics as $row) {
            foreach ($row['metrics'] as $metric => $val) {

                // normalize (avoid duplicates due to dots/case)
                $cleanMetric = trim(strtolower($metric));
                $cleanMetric = rtrim($cleanMetric, '.');

                if (!isset($metricMap[$cleanMetric])) {
                    $metricMap[$cleanMetric] = [
                        'label' => 'Metric ' . $index++,
                        'full' => $metric
                    ];
                }
            }
        }

        /* ---------- LEGEND ---------- */

        echo html_writer::start_div('mb-3');

    foreach ($metricMap as $m) {
        echo html_writer::tag('p', "<strong>{$m['label']}:</strong> {$m['full']}", [
            'style' => 'margin:0; font-size:13px; color:#6c757d;'
        ]);
    }

        echo html_writer::end_div();

        /* ---------- TABLE ---------- */

        echo '<table class="table table-bordered table-sm">';
        echo '<thead><tr><th>Assignment</th>';

        // Dynamic headers
        foreach ($metricMap as $m) {
            echo "<th>{$m['label']}</th>";
        }

        echo '</tr></thead><tbody>';

        foreach ($assignmentmetrics as $row) {

                echo "<tr>";
                echo "<td>" . format_string($row['assignment']) . "</td>";

            foreach ($metricMap as $clean => $m) {

            $value = '-';

            foreach ($row['metrics'] as $metric => $score) {

                $normalized = trim(strtolower($metric));
                $normalized = rtrim($normalized, '.');

                if ($normalized === $clean) {
                    $value = $score;
                    break;
                }
            }

            echo "<td>$value</td>";
        }

            echo "</tr>";
        }

        echo '</tbody></table>';

        echo html_writer::end_div(); // card
        echo html_writer::end_div(); // col
        echo html_writer::end_div(); // row
    }
}
/* --------------------------------------------------
   ROW 3 : BAR + PIE CHART
-------------------------------------------------- */

// $labels = array_keys($percentages);
// $values = array_values($percentages);
/* =========================
   ROW 4 → EMOTIONAL BREAKDOWN (CARDS UI)
========================= */
/* =========================
   METRIC TOTAL CALCULATION
========================= */

$metricTotals = [];
$metricCounts = [];

foreach ($assignmentmetrics as $row) {

    if (empty($row['metrics'])) continue;

    foreach ($row['metrics'] as $metric => $score) {

    if ($score === null || $score === '' || !is_numeric($score)) {
        continue; // 🚨 skip invalid
    }

    $normalized = trim(strtolower($metric));
    $normalized = rtrim($normalized, '.');

    if (!isset($metricTotals[$normalized])) {
        $metricTotals[$normalized] = 0;
        $metricCounts[$normalized] = 0;
    }

    $metricTotals[$normalized] += (float)$score;
    $metricCounts[$normalized] += 1;
}
}
/* =========================
   CONVERT TO PERCENTAGE
========================= */

$metricPercentages = [];

foreach ($metricTotals as $metric => $total) {

    $count = $metricCounts[$metric];

    // average score
    $avg = $count > 0 ? ($total / $count) : 0;
    $avg = max(1, min(7, $avg));

    // convert to %
    $percent = (($avg - 1) / (7 - 1)) * 100;
    $percent = max(0, min(100, $percent));

    $metricPercentages[$metric] = round($percent);
}
if ($isstudent) {
echo html_writer::start_div('row mt-5');
echo html_writer::start_div('col-12');

/* MAIN WRAPPER */
echo html_writer::start_div('wb-emotional-wrap wb-card-emotional-green');

/* LEFT SIDE */
echo html_writer::start_div('wb-emotional-left');

echo html_writer::tag('h3', 'Emotional Breakdown', ['class'=>'wb-emotional-title']);

echo html_writer::tag(
    'p',
    'Assessment of wellbeing metrics based on your responses',
    ['class'=>'wb-emotional-sub']
);

echo html_writer::end_div();

/* RIGHT SIDE (CARDS) */
echo html_writer::start_div('wb-emotional-cards');

/* COLORS + EMOJIS */
$colors = ['wb-card-orange','wb-card-green','wb-card-blue','wb-card-red'];
$emojis = ['💖','💡','🌈','🧠'];

$i = 0;

foreach ($metricMap as $clean => $m) {

    $value = $metricPercentages[$clean] ?? 0;
    $color = $colors[$i % count($colors)];
    $emoji = $emojis[$i % count($emojis)];

    echo html_writer::start_div("wb-em-card $color");

    /* EMOJI */
    echo html_writer::tag('div', $emoji, ['class'=>'wb-em-icon']);

    /* TITLE */
    echo html_writer::tag('div', $m['label'], ['class'=>'wb-em-label']);

    /* DESC */
    echo html_writer::tag('div', $m['full'], ['class'=>'wb-em-desc']);

    /* VALUE */
    echo html_writer::tag('div', "$value%", ['class'=>'wb-em-value']);

    echo html_writer::end_div();

    $i++;
}

echo html_writer::end_div(); // cards

echo html_writer::end_div(); // wrapper

echo html_writer::end_div();
echo html_writer::end_div();
}

//teacher
if (!$isstudent) {
    /* =========================
   TEACHER DASHBOARD DATA
========================= */

/* ✅ AVG SCORE (already done) */
$avgscore = analysis_service::get_teacher_avg_score($courseid);

/* =========================
   TOTAL STUDENTS
========================= */
$context = context_course::instance($courseid);

$students = get_enrolled_users($context, 'mod/assign:submit');
$totalstudents = count($students);

/* =========================
   TOTAL ASSIGNMENTS
========================= */
$totalassignments = $DB->count_records('assign', ['course' => $courseid]);

/* =========================
   AVG COMPLETION %
========================= */

$assignments = $DB->get_records('assign', ['course' => $courseid]);

$totalPossible = 0;
$totalSubmitted = 0;

$trend = analysis_service::get_teacher_monthly_trend($courseid);

$labels_json = json_encode($trend['labels']);
$values_json = json_encode($trend['values']);
$topstudents = analysis_service::get_top_performers($courseid, 3);
$allstudents = analysis_service::get_top_performers($courseid, 100); 

foreach ($assignments as $assign) {

    $submissions = $DB->get_records('assign_submission', [
        'assignment' => $assign->id
    ]);

    foreach ($students as $student) {

        $totalPossible++;

        foreach ($submissions as $sub) {
            if ($sub->userid == $student->id && $sub->status == 'submitted') {
                $totalSubmitted++;
                break;
            }
        }
    }
}

$avgcompletion = $totalPossible > 0
    ? round(($totalSubmitted / $totalPossible) * 100)
    : 0;

echo '
<style>

/* STUDENT ROW */
.wb-student {
    display:flex;
    align-items:center;
    justify-content:space-between;
    padding:8px 0;
}

.wb-avatar {
    width:30px;
    height:30px;
    background:#e5e7eb;
    border-radius:50%;
    display:flex;
    align-items:center;
    justify-content:center;
}

.wb-name {
    flex:1;
    margin-left:10px;
    font-size:14px;
}

.wb-score {
    font-weight:600;
    color:#16a34a;
}

/* VIEW ALL */
.wb-view-all {
    font-size:13px;
    color:#6b7280;
    margin-top:10px;
    cursor:pointer;
}

/* MODAL */
.wb-modal {
    display:none;
    position:fixed;
    top:0;
    left:0;
    width:100%;
    height:100%;
    background:rgba(0,0,0,0.4);
    align-items:center;
    justify-content:center;
    z-index:9999;
}

.wb-modal-content {
    background:#fff;
    width:400px;
    border-radius:12px;
    padding:15px;
}

.wb-modal-header {
    display:flex;
    justify-content:space-between;
    font-weight:600;
    margin-bottom:10px;
}

.wb-close {
    cursor:pointer;
}

.wb-modal-body {
    max-height:300px;
    overflow-y:auto;
}

</style>
';
echo '
<style>

/* ===== WRAPPER ===== */
.wb-wrap {
    background:#f6f7fb;
    padding:25px;
    border-radius:20px;
    font-family: "Segoe UI", sans-serif;
}

/* ===== HEADER ===== */
.wb-header-title {
    font-size:28px;
    font-weight:700;
    margin-bottom:5px;
}
.wb-header-sub {
    color:#6b7280;
    font-size:14px;
}

/* ===== CARDS ===== */
.wb-top-cards {
    display:flex;
    gap:15px;
    margin-top:20px;
}
.wb-card {
    flex:1;
    border-radius:16px;
    padding:18px;
    color:#fff;
    position:relative;
    overflow:hidden;
}
.wb-card h3 {
    font-size:26px;
    margin:5px 0;
}
.wb-card p {
    font-size:13px;
    opacity:.9;
}

/* gradients */
.wb-green { background:linear-gradient(135deg,#7dd3fc,#34d399); }
.wb-blue { background:linear-gradient(135deg,#60a5fa,#818cf8); }
.wb-orange { background:linear-gradient(135deg,#fbbf24,#fb923c); }
.wb-red { background:linear-gradient(135deg,#fb7185,#f97316); }

/* ===== SECTION ===== */
.wb-row {
    display:flex;
    gap:20px;
    margin-top:25px;
}

/* ===== PANEL ===== */
.wb-panel {
    background:#fff;
    border-radius:16px;
    padding:18px;
    box-shadow:0 4px 14px rgba(0,0,0,0.05);
    flex:1;
}

/* ===== TREND ===== */
.wb-trend { flex:2; }
.wb-chart {
    height: 260px;
    position: relative;
}

/* ===== TOP PERFORMERS ===== */
.wb-student {
    display:flex;
    align-items:center;
    margin-bottom:15px;
}
.wb-avatar {
    width:36px;
    height:36px;
    border-radius:50%;
    background:#e5e7eb;
    margin-right:10px;
}
.wb-name { flex:1; }
.wb-score {
    background:#ecfdf5;
    color:#059669;
    padding:5px 10px;
    border-radius:20px;
    font-weight:600;
}

/* ===== PARTICIPATION ===== */
.wb-progress {
    margin-bottom:15px;
}
.wb-progress-title {
    display:flex;
    justify-content:space-between;
    font-size:13px;
}
.wb-bar {
    height:8px;
    background:#e5e7eb;
    border-radius:6px;
    margin-top:5px;
}
.wb-bar-fill {
    height:100%;
    border-radius:6px;
    background:#22c55e;
}

/* ===== METRICS ===== */
.wb-metrics {
    display:flex;
    gap:12px;
}
.wb-metric {
    flex:1;
    background:#f1f5f9;
    border-radius:14px;
    padding:15px;
    text-align:center;
}
.wb-metric-emoji {
    font-size:22px;
}
.wb-metric-text {
    font-size:12px;
    margin-top:5px;
}
.wb-metric-val {
    font-size:18px;
    font-weight:700;
    margin-top:4px;
}
.wb-row {
    display: grid;
    grid-template-columns: 2fr 1fr; /* 70-30 ratio */
    gap: 20px;
    margin-top: 20px;
}

.wb-panel {
    background: #ffffff;
    border-radius: 14px;
    padding: 16px;
    box-shadow: 0 6px 18px rgba(0,0,0,0.06);
}

.wb-card-title {
    font-size: 14px;
    font-weight: 600;
    color: #0f172a;
    margin-bottom: 10px;
}

.wb-chart-container {
    position: relative;
    height: 260px; /* important for chart */
    width: 100%;
}
.wb-sub {
    font-size: 12px;
    color: #6b7280;
    margin-bottom: 12px;
}

.wb-progress-block {
    margin-bottom: 18px;
}

.wb-progress-title {
    display: flex;
    justify-content: space-between;
    font-size: 13px;
    font-weight: 500;
    margin-bottom: 6px;
}

.wb-bar {
    display: flex;
    height: 8px;
    border-radius: 10px;
    overflow: hidden;
    background: #e5e7eb;
}

.wb-bar-fill {
    height: 100%;
}

/* COLORS */
.wb-green { background: #22c55e; }
.wb-orange { background: #f59e0b; }
.wb-gray { background: #cbd5f5; }

/* LEGEND */
.wb-legend {
    font-size: 11px;
    color: #6b7280;
    margin-top: 6px;
    display: flex;
    gap: 12px;
}

.wb-dot {
    display: inline-block;
    width: 8px;
    height: 8px;
    border-radius: 50%;
    margin-right: 4px;
}
    /* GRID */
.wb-grid {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 20px;
}

/* PANEL */
.wb-panel {
    background: #fff;
    padding: 16px;
    border-radius: 12px;
}

/* TITLES */
.wb-card-title {
    font-weight: 600;
    margin-bottom: 6px;
}

.wb-sub {
    font-size: 13px;
    color: #64748b;
    margin-bottom: 10px;
}

/* ACCORDION */
.wb-accordion {
    border-bottom:1px solid #f1f5f9;
    padding:10px 0;
}

.wb-accordion-header {
    display:flex;
    justify-content:space-between;
    cursor:pointer;
}

.wb-accordion-body {
    display: none;
    margin-top: 10px;
}

.wb-accordion.active .wb-accordion-body {
    display: block;
}

/* STUDENTS */
.wb-student-row {
    display:flex;
    justify-content:space-between;
    padding:8px 0;
}

.wb-student-left {
    display:flex;
    gap:8px;
}

.wb-avatar {
    background:#f1f5f9;
    padding:6px;
    border-radius:50%;
}

.wb-score-badge {
    background:#ecfdf5;
    color:#16a34a;
    padding:4px 10px;
    border-radius:20px;
}

/* RIGHT SIDE */
.wb-progress-card {
    display:flex;
    gap:12px;
    margin-bottom:16px;
    padding:12px;
    border:1px solid #f1f5f9;
    border-radius:10px;
}

/* CIRCLE */
.wb-progress-circle {
    width:70px;
    height:70px;
    border-radius:50%;
    background: conic-gradient(#22c55e calc(var(--value)*1%), #e5e7eb 0%);
    display:flex;
    align-items:center;
    justify-content:center;
    font-weight:600;
}

/* BARS */
.wb-bars {
    display:flex;
    height:6px;
    margin:6px 0;
    overflow:hidden;
    border-radius:10px;
}

.wb-bar.submitted { background:#22c55e; }
.wb-bar.draft { background:#f59e0b; }
.wb-bar.pending { background:#ef4444; }

/* LEGEND */
.wb-legend {
    font-size:11px;
    display:flex;
    gap:10px;
}

.dot {
    width:8px;
    height:8px;
    border-radius:50%;
    display:inline-block;
}

.dot.green { background:#22c55e; }
.dot.orange { background:#f59e0b; }
.dot.red { background:#ef4444; }

/* MISC */
.wb-muted {
    color:#9ca3af;
}
 /* GRID */
.wb-ring-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 24px;
}

/* CARD */
.wb-ring-card {
    text-align: center;
}

/* SVG WRAP */
.wb-svg-ring {
    position: relative;
    width: 140px;
    height: 140px;
    margin: auto;
}

/* SVG */
.wb-svg-ring svg {
    width: 100%;
    height: 100%;
    transform: rotate(-90deg); /* start from top */
}

/* TRACKS */
.track {
    fill: none;
    stroke: #E8E9EB;
    opacity:0.4
}

.track.outer { stroke-width: 7; }
.track.middle { stroke-width: 7; }
.track.inner { stroke-width: 7; }

/* PROGRESS */
.progress {
    fill: none;
    stroke-linecap: round; /* 🔥 rounded ends */
    transition: stroke-dashoffset 0.6s ease;
}

.progress.green {
    stroke: #6CC070;
    stroke-width: 7;
}

.progress.orange {
    stroke: #F4A261;
    stroke-width: 7;
}

.progress.red {
    stroke: #E76F51;
    stroke-width: 7;
}

/* CENTER TEXT */
.wb-ring-center {
    position: absolute;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 22px;
    font-weight: 600;
    color: #1e293b;
}

/* TITLE */
.wb-ring-title {
    margin-top: 12px;
    font-weight: 500;
}

/* LEGEND */
.wb-ring-legend {
    margin-top: 8px;
    text-align: left;
    font-size: 12px;
    color: #64748b;
}

.dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    display: inline-block;
    margin-right: 6px;
}

.dot.green { background:#6CC070; }
.dot.orange { background:#F4A261; }
.dot.red { background:#E76F51; }
</style>

<div class="wb-wrap">

    <!-- HEADER -->
    <div class="wb-header-title">Teacher Dashboard 👩‍🏫</div>
    <div class="wb-header-sub">Monitor student wellbeing, performance & engagement</div>

    <!-- TOP CARDS -->
  
    <div class="wb-top-cards">

        <div class="wb-card wb-green">
            <div>Avg Score</div>
            <h3>'.$avgscore.'%</h3>
            <p>Class Average</p>
        </div>

        <div class="wb-card wb-blue">
            <div>Students</div>
            <h3>'.$totalstudents.'</h3>
            <p>Enrolled</p>
        </div>

        <div class="wb-card wb-orange">
            <div>Assignments</div>
            <h3>'.$totalassignments.'</h3>
            <p>Total Activities</p>
        </div>

        <div class="wb-card wb-red">
            <div>Avg Completion</div>
            <h3>'.$avgcompletion.'%</h3>
            <p>Class Progress</p>
        </div>

    </div>


    <!-- ROW 2 -->
  <div class="wb-row">

    <!-- TREND -->
    <div class="wb-panel wb-trend">
        <div class="wb-card-title">Class Wellbeing Trend</div>

        <div class="wb-chart-container">
            <canvas id="teacherTrendChart"></canvas>
        </div>
    </div>
';


    echo '<div class="wb-panel">
        <div style="font-weight:600;margin-bottom:10px;">Top Performers</div>';

            if (!empty($topstudents)) {

                foreach ($topstudents as $stu) {

                    echo '<div class="wb-student">
                            <div class="wb-avatar">👤</div>
                            <div class="wb-name">'.$stu['name'].'</div>
                            <div class="wb-score">'.$stu['score'].'%</div>
                        </div>';
                }

            } else {

                echo '<div style="color:#9ca3af;font-size:13px;">No data available</div>';
            }

    echo '<div class="wb-view-all" onclick="openTopStudentsModal()">View All →</div>
      </div>';
$assignmentParticipation = analysis_service::get_assignment_participation($courseid);
$assignmentStudentScores = analysis_service::get_assignment_student_scores($courseid);

echo'</div>';


echo '<div class="wb-grid pt-4">';

/* =========================
   LEFT SIDE → CLASS SCORE
========================= */
echo '<div class="wb-left">';

echo '<div class="wb-panel">
        <div class="wb-card-title">Class Overall Score</div>
        <div class="wb-sub">Assignment-wise student performance</div>';

if (!empty($assignmentStudentScores)) {

    foreach ($assignmentStudentScores as $assignment) {

        echo '
        <div class="wb-accordion">

            <div class="wb-accordion-header" onclick="toggleAccordion(this)">
                <span>'.format_string($assignment['name']).'</span>
                <span class="wb-arrow">▼</span>
            </div>

            <div class="wb-accordion-body">';

        if (!empty($assignment['students'])) {

            foreach ($assignment['students'] as $stu) {

                echo '
                <div class="wb-student-row">
                    <div class="wb-student-left">
                        <span class="wb-avatar">👤</span>
                        <span class="wb-name">'.$stu['name'].'</span>
                    </div>

                    <div class="wb-score-badge">'.$stu['score'].'%</div>
                </div>';
            }

        } else {
            echo '<div class="wb-muted">No student data</div>';
        }

        echo '
            </div>
        </div>';
    }

} else {
    echo '<div class="wb-muted">No data available</div>';
}

echo '</div>';
echo '</div>';


/* =========================
   RIGHT SIDE → ASSIGNMENT COMPLETION
========================= */

echo '<div class="wb-right">
        <div class="wb-panel">
            <div class="wb-card-title">Assignment Completion</div>

            <div class="wb-ring-grid">';

if (!empty($assignmentParticipation)) {

    foreach ($assignmentParticipation as $a) {

        $submitted = $a['submitted'];
        $draft = $a['draft'];
        $pending = $a['pending'];

        echo '
        <div class="wb-ring-card">

            <div class="wb-svg-ring">

                <svg viewBox="0 0 120 120">

                    <!-- TRACKS (GREY) -->
                    <circle cx="60" cy="60" r="50" class="track outer"/>
                    <circle cx="60" cy="60" r="38" class="track middle"/>
                    <circle cx="60" cy="60" r="26" class="track inner"/>

                    <!-- PROGRESS -->
                    <circle cx="60" cy="60" r="50"
                        class="progress green"
                        stroke-dasharray="'.(2*pi()*50).'"
                        stroke-dashoffset="'.(2*pi()*50*(1-$submitted/100)).'"
                    />

                    <circle cx="60" cy="60" r="38"
                        class="progress orange"
                        stroke-dasharray="'.(2*pi()*38).'"
                        stroke-dashoffset="'.(2*pi()*38*(1-$draft/100)).'"
                    />

                    <circle cx="60" cy="60" r="26"
                        class="progress red"
                        stroke-dasharray="'.(2*pi()*26).'"
                        stroke-dashoffset="'.(2*pi()*26*(1-$pending/100)).'"
                    />

                </svg>

                <div class="wb-ring-center">'.$submitted.'%</div>
            </div>

            <div class="wb-ring-title">'.format_string($a['name']).'</div>

            <div class="wb-ring-legend">
                <div><span class="dot green"></span> '.$submitted.'% Submitted</div>
                <div><span class="dot orange"></span> '.$draft.'% Draft</div>
                <div><span class="dot red"></span> '.$pending.'% Pending</div>
            </div>

        </div>';
    }

}

echo '</div></div></div>';
echo '</div>'; // grid

echo '
<script>
function toggleAccordion(el) {
    const parent = el.closest(".wb-accordion");

    // close others (optional clean UX)
    document.querySelectorAll(".wb-accordion").forEach(acc => {
        if (acc !== parent) {
            acc.classList.remove("active");
        }
    });

    parent.classList.toggle("active");
}
function openTopStudentsModal() {
    document.getElementById("topStudentsModal").style.display = "flex";
}

function closeTopStudentsModal() {
    document.getElementById("topStudentsModal").style.display = "none";
}
</script>
';
echo '
<div id="topStudentsModal" class="wb-modal">
    <div class="wb-modal-content">
        <div class="wb-modal-header">
            <span>All Students Ranking</span>
            <span class="wb-close" onclick="closeTopStudentsModal()">✖</span>
        </div>

        <div class="wb-modal-body">';
        
        if (!empty($allstudents)) {
            foreach ($allstudents as $stu) {
                echo '
                <div class="wb-student">
                    <div class="wb-avatar">👤</div>
                    <div class="wb-name">'.$stu['name'].'</div>
                    <div class="wb-score">'.$stu['score'].'%</div>
                </div>';
            }
        }

echo '
        </div>
    </div>
</div>
';
echo '<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>';
echo '
<script>
window.addEventListener("load", function () {

    console.log("🔥 INLINE JS WORKING");

    const canvas = document.getElementById("teacherTrendChart");

    if (!canvas) {
        console.error("❌ Canvas NOT found");
        return;
    }

    console.log("✅ Canvas found");

    const ctx = canvas.getContext("2d");

    // ✅ DATA FROM PHP
    const labels = '.$labels_json.';
    const data = '.$values_json.';

    console.log("📊 Labels:", labels);
    console.log("📊 Data:", data);

    const gradient = ctx.createLinearGradient(0, 0, 0, 260);
    gradient.addColorStop(0, "rgba(34,197,94,0.3)");
    gradient.addColorStop(1, "rgba(34,197,94,0.02)");

    new Chart(ctx, {
        type: "line",
        data: {
            labels: labels,
            datasets: [{
                data: data,
                borderColor: "#22c55e",
                backgroundColor: gradient,
                fill: true,
                tension: 0.4,
                pointRadius: 4,
                pointBackgroundColor: "#22c55e"
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { 
                legend: { display: false }
            },
            scales: {
                x: { 
                    grid: { display: false }
                },
                y: { 
                    grid: { display: false },
                    beginAtZero: true
                }
            }
        }
    });

});
</script>
';
}
echo $OUTPUT->footer();