
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


        <!-- TOP PERFORMERS -->
        <div class="wb-panel">
            <div style="font-weight:600;margin-bottom:10px;">Top Performers</div>

            <div class="wb-student">
                <div class="wb-avatar"></div>
                <div class="wb-name">Valy Antonova</div>
                <div class="wb-score">92%</div>
            </div>

            <div class="wb-student">
                <div class="wb-avatar"></div>
                <div class="wb-name">Mark Neil</div>
                <div class="wb-score">87%</div>
            </div>

            <div class="wb-student">
                <div class="wb-avatar"></div>
                <div class="wb-name">Nenci Villy</div>
                <div class="wb-score">85%</div>
            </div>

            <div style="font-size:13px;color:#6b7280;margin-top:10px;">View All →</div>
        </div>

    </div>

    <!-- ROW 3 -->
    <div class="wb-row">

        <!-- PARTICIPATION -->
        <div class="wb-panel">
            <div style="font-weight:600;margin-bottom:10px;">Class Participation</div>

            <div class="wb-progress">
                <div class="wb-progress-title"><span>Assignment 1</span><span>85%</span></div>
                <div class="wb-bar"><div class="wb-bar-fill" style="width:85%"></div></div>
            </div>

            <div class="wb-progress">
                <div class="wb-progress-title"><span>Assignment 2</span><span>78%</span></div>
                <div class="wb-bar"><div class="wb-bar-fill" style="width:78%"></div></div>
            </div>

            <div class="wb-progress">
                <div class="wb-progress-title"><span>Assignment 3</span><span>92%</span></div>
                <div class="wb-bar"><div class="wb-bar-fill" style="width:92%"></div></div>
            </div>

        </div>

        <!-- METRICS -->
        <div class="wb-panel">
            <div style="font-weight:600;margin-bottom:10px;">Class Emotional Insights</div>

            <div class="wb-metrics">

                <div class="wb-metric">
                    <div class="wb-metric-emoji">💫</div>
                    <div class="wb-metric-text">I am a good person</div>
                    <div class="wb-metric-val">78%</div>
                </div>

                <div class="wb-metric">
                    <div class="wb-metric-emoji">🌱</div>
                    <div class="wb-metric-text">I am optimistic</div>
                    <div class="wb-metric-val">76%</div>
                </div>

                <div class="wb-metric">
                    <div class="wb-metric-emoji">🤝</div>
                    <div class="wb-metric-text">I feel supported</div>
                    <div class="wb-metric-val">73%</div>
                </div>

                <div class="wb-metric">
                    <div class="wb-metric-emoji">😊</div>
                    <div class="wb-metric-text">I am happy</div>
                    <div class="wb-metric-val">79%</div>
                </div>

            </div>
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