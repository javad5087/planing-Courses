<?php
declare(strict_types=1);

/* ================= دروس ================= */

/** همه دروس همراه با مجموع صفحات و دقایق ثبت‌شده */
function courses_all(bool $activeOnly = false): array
{
    $sql = 'SELECT c.*,
                COALESCE((SELECT SUM(pages)   FROM study_logs l WHERE l.course_id = c.id), 0) AS done_pages,
                COALESCE((SELECT SUM(minutes) FROM study_logs l WHERE l.course_id = c.id), 0) AS done_minutes
            FROM courses c'
        . ($activeOnly ? ' WHERE c.status = \'active\'' : '')
        . ' ORDER BY c.sort_order, c.id';
    return db()->query($sql)->fetchAll();
}

function course_get(int $id): ?array
{
    $st = db()->prepare('SELECT * FROM courses WHERE id = ?');
    $st->execute([$id]);
    $r = $st->fetch();
    return $r ?: null;
}

/* ================= برنامه‌ریزی ================= */

/** روزهای مطالعه (بدون روزهای تعطیل) بین دو تاریخ، شامل هر دو سر بازه */
function study_days(string $from, string $to, array $off): array
{
    $days = [];
    if ($from > $to) {
        return $days;
    }
    $d = $from;
    $guard = 0;
    while ($d <= $to && $guard++ < 4000) {
        if (!in_array(j_weekday($d), $off, true)) {
            $days[] = $d;
        }
        $d = ymd_add_days($d, 1);
    }
    return $days;
}

/**
 * برنامهٔ ثابت: صفحات به‌طور مساوی بین همهٔ روزهای مطالعهٔ بازه تقسیم می‌شود.
 * خروجی: [['date'=>, 'from'=>, 'to'=>, 'pages'=>], ...]
 */
function plan_schedule(array $c, array $off): array
{
    $days = study_days($c['plan_start'], $c['plan_end'], $off);
    $n = count($days);
    $total = (int)$c['total_pages'];
    if ($n === 0 || $total <= 0) {
        return [];
    }
    $base = intdiv($total, $n);
    $rem = $total % $n;
    $page = (int)$c['start_page'];
    $out = [];
    foreach ($days as $i => $d) {
        $cnt = $base + ($i < $rem ? 1 : 0);
        if ($cnt > 0) {
            $out[] = ['date' => $d, 'from' => $page, 'to' => $page + $cnt - 1, 'pages' => $cnt];
        } else {
            $out[] = ['date' => $d, 'from' => null, 'to' => null, 'pages' => 0];
        }
        $page += $cnt;
    }
    return $out;
}

/** صفحات خوانده‌شدهٔ یک درس در یک روز مشخص */
function pages_on(int $courseId, string $ymd): array
{
    $st = db()->prepare('SELECT COALESCE(SUM(pages),0) AS p, COALESCE(SUM(minutes),0) AS m
        FROM study_logs WHERE course_id = ? AND log_date = ?');
    $st->execute([$courseId, $ymd]);
    $r = $st->fetch();
    return ['pages' => (int)$r['p'], 'minutes' => (int)$r['m']];
}

/** وضعیت لحظه‌ای برنامهٔ یک درس نسبت به امروز */
function plan_status(array $c, string $today, array $off): array
{
    $total = (int)$c['total_pages'];
    $done = (int)($c['done_pages'] ?? 0);
    $remaining = max(0, $total - $done);
    $pct = $total > 0 ? min(100, (int)floor($done * 100 / $total)) : 0;

    $status = [
        'total' => $total,
        'done' => $done,
        'remaining' => $remaining,
        'percent' => $pct,
        'configured' => $total > 0,
        'finished' => $total > 0 && $remaining === 0,
        'not_started' => $c['plan_start'] > $today,
        'overdue' => $c['plan_end'] < $today && $remaining > 0,
        'days_left' => 0,
        'per_day' => 0,
        'per_week' => 0,
        'today_target' => 0,
        'today_done' => 0,
        'today_minutes' => 0,
        'today_is_study_day' => !in_array(j_weekday($today), $off, true),
        'next_page' => (int)$c['start_page'] + $done,
    ];

    $t = pages_on((int)$c['id'], $today);
    $status['today_done'] = $t['pages'];
    $status['today_minutes'] = $t['minutes'];

    if (!$status['configured'] || $status['finished']) {
        return $status;
    }

    // روزهای باقی‌ماندهٔ مطالعه؛ از امروز (یا شروع برنامه اگر هنوز شروع نشده)
    $from = max($today, $c['plan_start']);
    $left = study_days($from, $c['plan_end'], $off);
    $status['days_left'] = count($left);

    // مقدار پایه برای امروز = صفحات باقی‌مانده در ابتدای امروز ÷ روزهای مطالعهٔ باقی‌مانده
    $remainingAtDawn = $remaining + $status['today_done'];
    if ($status['days_left'] > 0) {
        $status['per_day'] = (int)ceil($remainingAtDawn / $status['days_left']);
        $studyPerWeek = max(1, 7 - count($off));
        $status['per_week'] = $status['per_day'] * $studyPerWeek;
    }
    if (!$status['not_started'] && !$status['overdue'] && $status['today_is_study_day']) {
        $status['today_target'] = $status['per_day'];
    }
    return $status;
}

/** تعداد روزهای متوالی (تا امروز) که حداقل یک مطالعه ثبت شده */
function study_streak(string $today): int
{
    $rows = db()->query('SELECT DISTINCT log_date FROM study_logs WHERE (pages > 0 OR minutes > 0) ORDER BY log_date DESC')
        ->fetchAll(PDO::FETCH_COLUMN);
    if (!$rows) {
        return 0;
    }
    $set = array_flip($rows);
    $d = isset($set[$today]) ? $today : ymd_add_days($today, -1);
    $n = 0;
    while (isset($set[$d])) {
        $n++;
        $d = ymd_add_days($d, -1);
    }
    return $n;
}

/* ================= آمار ================= */

/** جمع صفحات/دقایق هر درس در بازه */
function stats_by_course(string $from, string $to): array
{
    $st = db()->prepare('SELECT course_id, SUM(pages) AS pages, SUM(minutes) AS minutes,
            COUNT(DISTINCT log_date) AS days
        FROM study_logs WHERE log_date BETWEEN ? AND ? GROUP BY course_id');
    $st->execute([$from, $to]);
    $out = [];
    foreach ($st as $r) {
        $out[(int)$r['course_id']] = [
            'pages' => (int)$r['pages'],
            'minutes' => (int)$r['minutes'],
            'days' => (int)$r['days'],
        ];
    }
    return $out;
}

/** مجموع کل بازه */
function stats_total(string $from, string $to): array
{
    $st = db()->prepare('SELECT COALESCE(SUM(pages),0) AS pages, COALESCE(SUM(minutes),0) AS minutes,
            COUNT(DISTINCT CASE WHEN pages > 0 OR minutes > 0 THEN log_date END) AS days
        FROM study_logs WHERE log_date BETWEEN ? AND ?');
    $st->execute([$from, $to]);
    $r = $st->fetch();
    return ['pages' => (int)$r['pages'], 'minutes' => (int)$r['minutes'], 'days' => (int)$r['days']];
}

/** اطلاعات بازهٔ جاری: day | week | month | year */
function period_info(string $p, string $ref): array
{
    switch ($p) {
        case 'week':
            $from = week_start($ref);
            $to = ymd_add_days($from, 6);
            return [
                'from' => $from, 'to' => $to,
                'label' => 'هفتهٔ ' . jfmt($from, 'day') . ' تا ' . jfmt($to, 'long'),
                'prev' => ymd_add_days($from, -7), 'next' => ymd_add_days($from, 7),
                'title' => 'هفتگی',
            ];
        case 'month':
            [$from, $to] = month_range($ref);
            return [
                'from' => $from, 'to' => $to, 'label' => jfmt($from, 'month'),
                'prev' => ymd_add_days($from, -1), 'next' => ymd_add_days($to, 1),
                'title' => 'ماهانه',
            ];
        case 'year':
            [$from, $to] = year_range($ref);
            [$jy] = ymd_to_j($from);
            return [
                'from' => $from, 'to' => $to, 'label' => 'سال ' . fa($jy),
                'prev' => ymd_add_days($from, -1), 'next' => ymd_add_days($to, 1),
                'title' => 'سالانه',
            ];
        default:
            return [
                'from' => $ref, 'to' => $ref, 'label' => jfmt($ref, 'full'),
                'prev' => ymd_add_days($ref, -1), 'next' => ymd_add_days($ref, 1),
                'title' => 'روزانه',
            ];
    }
}

/** n بازهٔ متوالی منتهی به بازهٔ مرجع، برای نمودار */
function trailing_buckets(string $p, string $ref, int $n): array
{
    $buckets = [];
    $cur = $ref;
    for ($i = 0; $i < $n; $i++) {
        $info = period_info($p, $cur);
        switch ($p) {
            case 'week':
                $label = jfmt($info['from'], 'day');
                break;
            case 'month':
                [$jy, $jm] = ymd_to_j($info['from']);
                $label = JALALI_MONTHS[$jm];
                break;
            case 'year':
                [$jy] = ymd_to_j($info['from']);
                $label = fa($jy);
                break;
            default:
                [, , $jd] = ymd_to_j($info['from']);
                $label = fa($jd);
        }
        array_unshift($buckets, [
            'label' => $label,
            'sub' => $p === 'day' ? weekday_name($info['from']) : '',
            'from' => $info['from'],
            'to' => $info['to'],
            'current' => $i === 0,
        ]);
        $cur = $info['prev'];
    }
    return $buckets;
}

/**
 * مقادیر هر بازه به تفکیک درس: [bucketIndex][courseId] = ['pages'=>, 'minutes'=>]
 */
function bucket_series(array $buckets): array
{
    $from = $buckets[0]['from'];
    $to = $buckets[count($buckets) - 1]['to'];
    $st = db()->prepare('SELECT log_date, course_id, SUM(pages) AS pages, SUM(minutes) AS minutes
        FROM study_logs WHERE log_date BETWEEN ? AND ? GROUP BY log_date, course_id');
    $st->execute([$from, $to]);
    $series = array_fill(0, count($buckets), []);
    foreach ($st as $r) {
        foreach ($buckets as $i => $b) {
            if ($r['log_date'] >= $b['from'] && $r['log_date'] <= $b['to']) {
                $cid = (int)$r['course_id'];
                if (!isset($series[$i][$cid])) {
                    $series[$i][$cid] = ['pages' => 0, 'minutes' => 0];
                }
                $series[$i][$cid]['pages'] += (int)$r['pages'];
                $series[$i][$cid]['minutes'] += (int)$r['minutes'];
                break;
            }
        }
    }
    return $series;
}

/** نمودار میله‌ای انباشته (SVG) به تفکیک درس. $metric: pages | minutes */
function svg_stacked_bars(array $buckets, array $series, array $courses, string $metric): string
{
    $n = count($buckets);
    $w = 760;
    $h = 240;
    $padL = 44;
    $padR = 10;
    $padT = 14;
    $padB = $buckets[0]['sub'] !== '' ? 46 : 32;
    $plotW = $w - $padL - $padR;
    $plotH = $h - $padT - $padB;

    $totals = [];
    $max = 0;
    foreach ($series as $i => $byCourse) {
        $t = 0;
        foreach ($byCourse as $v) {
            $t += $v[$metric];
        }
        $totals[$i] = $t;
        $max = max($max, $t);
    }
    // مقیاس «خوب» برای محور
    $scaleMax = $metric === 'minutes' ? max(60, (int)ceil($max / 60) * 60) : max(5, (int)ceil($max / 5) * 5);
    $ticks = 4;
    $unit = $metric === 'minutes' ? 'ساعت' : 'صفحه';

    $colors = [];
    foreach ($courses as $c) {
        $colors[(int)$c['id']] = $c['color'];
    }

    $slot = $plotW / max(1, $n);
    $barW = min(34, $slot * 0.62);

    $svg = '<svg class="chart" viewBox="0 0 ' . $w . ' ' . $h . '" role="img" aria-label="نمودار ' . e($unit) . '" xmlns="http://www.w3.org/2000/svg">';
    for ($t = 0; $t <= $ticks; $t++) {
        $val = $scaleMax * $t / $ticks;
        $y = $padT + $plotH - ($plotH * $t / $ticks);
        $label = $metric === 'minutes' ? fnum($val / 60, 1) : fnum($val);
        $svg .= '<line x1="' . $padL . '" y1="' . round($y, 1) . '" x2="' . ($w - $padR) . '" y2="' . round($y, 1) . '" class="gl"/>';
        $svg .= '<text x="' . ($padL - 6) . '" y="' . round($y + 4, 1) . '" class="axis" text-anchor="end">' . $label . '</text>';
    }
    foreach ($buckets as $i => $b) {
        // ترتیب RTL: قدیمی‌ترین در راست، جدیدترین در چپ
        $cx = $w - $padR - $slot * ($i + 0.5);
        $x = $cx - $barW / 2;
        $yCursor = $padT + $plotH;
        foreach ($courses as $c) {
            $cid = (int)$c['id'];
            $v = $series[$i][$cid][$metric] ?? 0;
            if ($v <= 0) {
                continue;
            }
            $bh = $plotH * $v / $scaleMax;
            $yCursor -= $bh;
            $tip = $c['title'] . ': ' . ($metric === 'minutes' ? fmt_minutes((int)$v) : fnum($v) . ' صفحه');
            $svg .= '<rect x="' . round($x, 1) . '" y="' . round($yCursor, 1) . '" width="' . round($barW, 1)
                . '" height="' . round($bh, 1) . '" fill="' . e($colors[$cid] ?? '#2271b1') . '"><title>' . e($tip) . '</title></rect>';
        }
        if ($totals[$i] > 0) {
            $tv = $metric === 'minutes' ? fnum($totals[$i] / 60, 1) : fnum($totals[$i]);
            $svg .= '<text x="' . round($cx, 1) . '" y="' . round($yCursor - 4, 1) . '" class="val" text-anchor="middle">' . $tv . '</text>';
        }
        $cls = $b['current'] ? 'xlab cur' : 'xlab';
        $svg .= '<text x="' . round($cx, 1) . '" y="' . ($h - $padB + 16) . '" class="' . $cls . '" text-anchor="middle">' . e($b['label']) . '</text>';
        if ($b['sub'] !== '') {
            $svg .= '<text x="' . round($cx, 1) . '" y="' . ($h - $padB + 30) . '" class="xsub" text-anchor="middle">' . e(mb_substr($b['sub'], 0, 3)) . '</text>';
        }
    }
    $svg .= '</svg>';
    return $svg;
}
