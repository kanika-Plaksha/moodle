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
    $totals = analysis_service::get_user_course_metrics($courseid, $USER->id);
    $isstudent = true;
}
if ($isstudent) {
echo $OUTPUT->heading('Student Wellbeing Overview');
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
foreach ($totals as $metric => $score) {
    $percentages[$metric] = round(($score / 7) * 100); // individual metric %
}

/* --------------------------------------------------
   OVERALL WELLBEING SCORE
-------------------------------------------------- */

$totalScore = array_sum($totals);
$metricCount = count($totals);

$minScore = $metricCount;
$maxScore = $metricCount * 7;

$percentage = $metricCount > 0
    ? round((($totalScore - $minScore) / ($maxScore - $minScore)) * 100)
    : 0;
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
   ROW 1 : WELLBEING + ASSIGNMENT COMPLETION
-------------------------------------------------- */

echo html_writer::start_div('row mt-4');


/* WELLBEING CARD */

/* WELLBEING CARD */

if ($isstudent) {

echo html_writer::start_div('col-md-6');

echo html_writer::start_div('card p-4 shadow-sm text-center');

echo html_writer::tag('h3', 'Overall Well-Being Trend Analysis');

/* WELLBEING GAUGE */

echo html_writer::start_div('', [
    'style' => "
        width:200px;
        height:200px;
        margin:25px auto;
        border-radius:50%;
        background:conic-gradient($color {$percentage}%, #e9ecef {$percentage}%);
        display:flex;
        align-items:center;
        justify-content:center;
        font-size:60px;
        box-shadow:0 4px 15px rgba(0,0,0,0.15);
    "
]);

echo $emoji;

echo html_writer::end_div();

echo html_writer::tag('h4', $percentage . '% Overall Wellbeing');


/* WELLBEING INTERPRETATION */

if ($percentage < 40) {

    echo html_writer::tag(
        'p',
        'Your wellbeing is currently low. Focus on self-care and balance 💙',
        ['class' => 'mt-2']
    );

} elseif ($percentage < 70) {

    echo html_writer::tag(
        'p',
        'You are maintaining steady wellbeing. Keep building healthy habits 🌱',
        ['class' => 'mt-2']
    );

} else {

    echo html_writer::tag(
        'p',
        'Excellent emotional balance. You are thriving! 🌟',
        ['class' => 'mt-2']
    );
}


/* TREND INSIGHT BOX */

$highestemotion = array_keys($totals, max($totals))[0];
$lowestemotion  = array_keys($totals, min($totals))[0];

echo html_writer::start_div('alert alert-light mt-4 text-start');
echo html_writer::end_div();


echo html_writer::end_div();
echo html_writer::end_div();
}

if (!$isstudent) {

$submissiondata = analysis_service::get_assignment_submission_overview($courseid);


if (!empty($submissiondata)) {

echo html_writer::start_div('col-md-6');
echo html_writer::start_div('card p-4 shadow-sm');

echo html_writer::tag('h3', 'Assignment Submission Overview');

echo '<div class="mt-3">';

foreach ($submissiondata as $row) {

    $total = $row->totalstudents;
    $submitted = $row->submitted;

    $percent = $total > 0 ? round(($submitted / $total) * 100) : 0;

    /* Color logic */

    if ($percent < 40) {
        $color = "#dc3545";
    } elseif ($percent < 70) {
        $color = "#ffc107";
    } else {
        $color = "#28a745";
    }

    echo '
    <div style="margin-bottom:18px;">

        <div style="display:flex;justify-content:space-between;font-size:14px;margin-bottom:6px;">
            <strong>'.format_string($row->assignment).'</strong>
            <span>'.$submitted.' / '.$total.' ('.$percent.'%)</span>
        </div>

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
                transition:width .4s ease;
            "></div>
        </div>

    </div>';
}

echo '</div>';

echo html_writer::end_div();
echo html_writer::end_div();

}

}

/* ASSIGNMENT COMPLETION CARD */

if ($isstudent) {

echo html_writer::start_div('col-md-6');

echo html_writer::start_div('card p-4 shadow-sm text-center');

echo html_writer::tag('h3', 'Assignment Progress Overview');

/* COLOR + ICON LOGIC */

if ($completionpercentage < 25) {
    $progresscolor = "#dc3545"; // red
    $icon = "🚀"; 
    $label = "Getting Started";
} elseif ($completionpercentage < 50) {
    $progresscolor = "#fd7e14"; // orange
    $icon = "📈";
    $label = "Building Momentum";
} elseif ($completionpercentage < 75) {
    $progresscolor = "#ffc107"; // yellow
    $icon = "🎯";
    $label = "Making Progress";
} elseif ($completionpercentage < 100) {
    $progresscolor = "#20c997"; // teal
    $icon = "💪";
    $label = "Almost There";
} else {
    $progresscolor = "#28a745"; // green
    $icon = "🏆";
    $label = "Completed";
}

/* CIRCULAR GAUGE (Submitted %) */

echo html_writer::start_div('', [
    'style' => "
        width:180px;
        height:180px;
        margin:20px auto;
        border-radius:50%;
        background:conic-gradient($progresscolor {$completionpercentage}%, #e9ecef {$completionpercentage}%);
        display:flex;
        align-items:center;
        justify-content:center;
        font-size:50px;
        box-shadow:0 4px 15px rgba(0,0,0,0.15);
    "
]);

echo $icon;

echo html_writer::end_div();

/* MAIN TEXT */

echo html_writer::tag(
    'h4',
    "$completed Submitted • $draft Draft • $totalassignments Total"
);

echo html_writer::tag(
    'p',
    "$completionpercentage% Completed",
    ['style' => "font-weight:500;color:black;font-size:16px;"]
);

/* PROGRESS BREAKDOWN BAR */

echo html_writer::start_div('', [
    'style' => "
        width:100%;
        height:12px;
        border-radius:10px;
        overflow:hidden;
        display:flex;
        margin-top:15px;
        background:#e9ecef;
    "
]);

// Submitted (Green)
echo html_writer::div('', '', [
    'style' => "width:{$completionpercentage}%; background:#28a745;"
]);

// Draft (Orange)
echo html_writer::div('', '', [
    'style' => "width:{$draftpercentage}%; background:#fd7e14;"
]);

echo html_writer::end_div();

/* LEGEND */

echo html_writer::start_div('d-flex justify-content-center mt-3', [
    'style' => 'gap:20px; font-size:14px;'
]);

echo html_writer::tag('span', '🟢 Submitted', ['style' => 'color:#28a745;']);
echo html_writer::tag('span', '🟠 Draft', ['style' => 'color:#fd7e14;']);
echo html_writer::tag('span', '⚪ Remaining', ['style' => 'color:#6c757d;']);

echo html_writer::end_div();

echo html_writer::end_div(); // card
echo html_writer::end_div(); // col

echo html_writer::end_div(); // CLOSE ROW
}
/* --------------------------------------------------
   ROW 2 : ASSIGNMENT EMOTIONAL METRICS
-------------------------------------------------- */
$assignmentmetrics = analysis_service::get_student_assignment_metrics_filtered($courseid, $USER->id);

if (!empty($assignmentmetrics)) {

    echo html_writer::start_div('row mt-4');
    echo html_writer::start_div('col-12');

    echo html_writer::start_div('card shadow-sm p-4');

    echo html_writer::tag('h4', 'Assignment-wise Wellbeing Metrics');

    /* ---------- CREATE METRIC MAP (M1, M2...) ---------- */

    $metricMap = [];
    $index = 1;

    // Take first assignment to map metrics
    foreach ($assignmentmetrics[0]['metrics'] as $metric => $val) {
        $metricMap[$metric] = 'Metrics ' . $index++;
    }

    /* ---------- LEGEND ---------- */

    echo html_writer::start_div('mb-3');

    foreach ($metricMap as $full => $short) {
        echo html_writer::tag('p', "<strong>$short:</strong> $full", [
            'style' => 'margin:0; font-size:13px; color:#6c757d;'
        ]);
    }

    echo html_writer::end_div();

    /* ---------- TABLE ---------- */

    echo '<table class="table table-bordered table-sm">';
    echo '<thead><tr><th>Assignment</th>';

    // Dynamic headers
    foreach ($metricMap as $short) {
        echo "<th>$short</th>";
    }

    echo '</tr></thead><tbody>';

    foreach ($assignmentmetrics as $row) {

        echo "<tr>";
        echo "<td>" . format_string($row['assignment']) . "</td>";

        foreach ($metricMap as $full => $short) {
            $value = $row['metrics'][$full] ?? '-';
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

$labels = array_keys($percentages);
$values = array_values($percentages);

$barchart = new chart_bar();
$barchart->set_title('Aggregated Emotional Metrics');
$barchart->add_series(new chart_series('Emotion Score', $values));
$barchart->set_labels($labels);

$piechart = new chart_pie();
$piechart->set_title('Emotion Distribution');
$piechart->add_series(new chart_series('', $values));
$piechart->set_labels($labels);

echo html_writer::start_div('row mt-4');

echo html_writer::start_div('col-md-6');
echo $OUTPUT->render($barchart);
echo html_writer::end_div();

echo html_writer::start_div('col-md-6');
echo $OUTPUT->render($piechart);
echo html_writer::end_div();

echo html_writer::end_div();
$PAGE->requires->js_init_code("
    const style = document.createElement('style');
    style.innerHTML = `
        .chart-table,
        .chart-data-table,
        .core-chart-table,
        .chart-data,
        a[data-region='chart-table-toggle'],
        a[data-action='toggle-chart-data'] {
            display: none !important;
        }
    `;
    document.head.appendChild(style);
");

/* --------------------------------------------------
   ROW 4 : DETAILED BREAKDOWN
-------------------------------------------------- */
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