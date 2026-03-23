
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
if ($isstudent) {

} else {
    echo $OUTPUT->heading('Course Wellbeing Report');
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

//CHeck calculation
// $totalresponses = array_sum($totals);

// $percentages = [];

// echo "<pre>";
// echo "📊 RAW TOTALS:\n";
// print_r($totals);
// echo "</pre>";

// $combined = [];

// foreach ($totals as $item) {

//     if (empty($item['metrics'])) {
//         continue;
//     }

//     foreach ($item['metrics'] as $metric => $score) {

//         if (!isset($combined[$metric])) {
//             $combined[$metric] = 0;
//         }

//         $combined[$metric] += $score;
//     }
// }

// /* ---------- DEBUG COMBINED ---------- */

// echo "<pre>";
// echo "📦 COMBINED METRICS:\n";
// foreach ($combined as $metric => $value) {
//     echo $metric . " => " . $value . "\n";
// }
// echo "</pre>";

// /* --------------------------------------------------
//    OVERALL WELLBEING SCORE
// -------------------------------------------------- */

// $totalScore = array_sum($combined);
// $metricCount = count($combined);

// $minScore = $metricCount;
// $maxScore = $metricCount * 7;

// /* ---------- DEBUG CALCULATION ---------- */

// echo "<pre>";
// echo "🧮 TOTAL SCORE: $totalScore\n";
// echo "📊 METRIC COUNT: $metricCount\n";
// echo "📉 MIN SCORE: $minScore\n";
// echo "📈 MAX SCORE: $maxScore\n";

// if ($metricCount > 0) {

//     $numerator = $totalScore - $minScore;
//     $denominator = $maxScore - $minScore;

//     echo "\n🧠 FORMULA:\n";
//     echo "($totalScore - $minScore) / ($denominator)\n";

//     $raw = $denominator > 0 ? ($numerator / $denominator) : 0;

//     echo "Raw Value: $raw\n";

//     $percentage = round($raw * 100);

// } else {
//     $percentage = 0;
// }

// echo "\n🎯 FINAL PERCENTAGE: $percentage%\n";
// echo "</pre>";
//check calucltions
if ($percentage < 20) {
    $emoji = "😟"; $color = "#dc3545";
} elseif ($percentage < 40) {
    $emoji = "😕"; $color = "#fd7e14";
} elseif ($percentage < 60) {
    $emoji = "😐"; $color = "#ffc107";
} elseif ($percentage < 80) {
    $emoji = "🙂"; $color = "#20c997";
} else {
    $emoji = "😄"; $color = "#28a745";
}

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
// $totalassignments = 0;
// $completed = 0;
// $draft= 0;

// if (!empty($progressdata)) {

//     $totalassignments = count($progressdata);

//     foreach ($progressdata as $item) {
//         if ($item->status === 'submitted') {
//             $completed++;
//         } else if ($item->status === 'draft') {
//             $draft++;
//         }
//     }
// }

// $completionpercentage = $totalassignments > 0
//     ? round(($completed / $totalassignments) * 100)
//     : 0;


/* --------------------------------------------------
   ROW 1 : 
-------------------------------------------------- */
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

/* --------------------------------------------------
   ROW 4 : DETAILED BREAKDOWN
-------------------------------------------------- */

echo html_writer::start_div('row mt-5');

echo html_writer::start_div('col-12');

echo html_writer::tag('h4', 'Detailed Breakdown');

echo html_writer::start_div('card shadow-sm p-4 mb-4');

echo html_writer::start_tag('table', [
    'class' => 'table table-striped table-bordered',
    'style' => 'width:100%;'
]);

echo html_writer::start_tag('thead');
echo html_writer::start_tag('tr');

echo html_writer::tag('th', 'Emotion', ['style'=>'width:200px;']);
echo html_writer::tag('th', 'Total Score');

echo html_writer::end_tag('tr');
echo html_writer::end_tag('thead');

echo html_writer::start_tag('tbody');

foreach ($percentages as $emotion => $percent) {

    $cleanname = ucwords(str_replace('_', ' ', $emotion));

    switch ($emotion) {

        case 'very_happy':
            $color = '#1f9d78';
            break;

        case 'happy':
            $color = '#38a169';
            break;

        case 'neutral':
            $color = '#718096';
            break;

        case 'sad':
            $color = '#dd6b20';
            break;

        case 'depressed':
            $color = '#c53030';
            break;

        default:
            $color = '#6c757d';
    }

    echo html_writer::start_tag('tr');

    echo html_writer::tag(
        'td',
        '<span style="font-size:14px;color:#495057;">'.$cleanname.'</span>'
    );

    echo html_writer::start_tag('td');

    echo '
    <div style="
        background:#e9ecef;
        border-radius:6px;
        height:10px;
        overflow:hidden;
    ">
        <div style="
            width:'.$percent.'%;
            background:'.$color.';
            height:100%;
        "></div>
    </div>

    <div style="
        font-size:12px;
        color:#6c757d;
        margin-top:4px;
        text-align:right;
    ">
        '.$percent.'%
    </div>
    ';

    echo html_writer::end_tag('td');

    echo html_writer::end_tag('tr');
}

echo html_writer::end_tag('tbody');
echo html_writer::end_tag('table');

echo html_writer::end_div(); // card
echo html_writer::end_div(); // col-12
echo html_writer::end_div(); // row

echo $OUTPUT->footer();