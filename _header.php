<?php
// _header.php — shared header + KPI cards
// $active_tab = 'home' | 'doctor' | 'ward' | 'dept'
if (!isset($active_tab)) $active_tab = 'home';

// ---- KPI data ----
$total_an = 0;
$kpi_status = ['ส่งเคลมแล้ว' => 0, 'รอเคลม' => 0, 'รอตึกส่งออก' => 0];

if ($pdo) {
    try {
        $r = $pdo->query("SELECT COUNT(an) AS total_an FROM tmp_chart");
        $total_an = $r->fetchColumn() ?? 0;

        $r2 = $pdo->query("SELECT status AS status_chart, COUNT(an) AS total_status FROM tmp_chart GROUP BY status");
        foreach ($r2->fetchAll() as $row) {
            if (isset($kpi_status[$row['status_chart']])) {
                $kpi_status[$row['status_chart']] = $row['total_status'];
            }
        }
    } catch (Exception $e) { /* silently fail */ }
}

$tabs = [
    'home'   => ['label' => 'หน้าหลัก',         'file' => 'index.php',      'icon' => '🏠'],
    'doctor' => ['label' => 'แยกรายแพทย์',       'file' => 'tab_doctor.php', 'icon' => '👨‍⚕️'],
    'ward'   => ['label' => 'แยกรายตึก',          'file' => 'tab_ward.php',   'icon' => '🏥'],
    'dept'   => ['label' => 'แยกรายแผนก',        'file' => 'tab_dept.php',   'icon' => '📋'],
];
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>รายงานการส่งข้อมูล Claim ผู้ป่วยใน — รพ.โพนทอง</title>
<script src="https://cdn.tailwindcss.com"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&family=IBM+Plex+Sans+Thai:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
  :root {
    --primary:   #1B6B3A;   /* เขียวเข้ม สาธารณสุข */
    --primary2:  #239150;   /* เขียวกลาง */
    --secondary: #5DBF82;   /* เขียวอ่อน */
    --bg:        #F2FAF5;   /* ขาวอมเขียวอ่อนมาก */
    --bg2:       #E6F5EC;   /* stripe / เน้น */
    --text:      #1A2E22;   /* เขียวเข้มมาก ใช้เป็น text */
    --highlight: #27AE60;
    --danger:    #EB5757;
    --warn:      #F2994A;
  }
  * { font-family: 'IBM Plex Sans Thai', 'Sarabun', sans-serif; }
  body { background: var(--bg); color: var(--text); }

  /* Glass KPI Cards */
  .glass-card {
    background: rgba(255,255,255,0.80);
    backdrop-filter: blur(16px);
    -webkit-backdrop-filter: blur(16px);
    border: 1px solid rgba(255,255,255,0.95);
    box-shadow: 0 4px 24px rgba(27,107,58,0.10), 0 1px 4px rgba(0,0,0,0.06);
    border-radius: 16px;
    transition: transform .2s, box-shadow .2s;
  }
  .glass-card:hover { transform: translateY(-2px); box-shadow: 0 8px 32px rgba(27,107,58,0.20); }

  /* Header gradient — เขียวเข้ม → เขียวกลาง → เขียวอ่อน */
  .header-bar {
    background: linear-gradient(135deg, #0f4023 0%, #1B6B3A 45%, #2ea357 80%, #5DBF82 100%);
  }

  /* Tab nav */
  .tab-btn {
    transition: all .2s;
    border-bottom: 3px solid transparent;
  }
  .tab-btn.active {
    border-bottom: 3px solid white;
    background: rgba(255,255,255,0.20);
  }
  .tab-btn:not(.active):hover {
    background: rgba(255,255,255,0.10);
    border-bottom: 3px solid rgba(255,255,255,0.5);
  }

  /* Tables */
  .data-table { border-collapse: collapse; width: 100%; font-size: .875rem; }
  .data-table th {
    background: var(--primary);
    color: white;
    padding: 10px 14px;
    text-align: left;
    font-weight: 600;
  }
  .data-table th.center, .data-table td.center { text-align: center; }
  .data-table td {
    padding: 9px 14px;
    border-bottom: 1px solid #c9e8d4;
    vertical-align: middle;
  }
  .data-table tr:nth-child(even) td { background: #eaf7ef; }
  .data-table tr:last-child td { background: #c8ecd6; font-weight: 700; }
  .data-table tr:hover td { background: #d4f0de; }

  /* Section card */
  .section-card {
    background: white;
    border-radius: 14px;
    border: 1px solid #d4eddf;
    box-shadow: 0 2px 12px rgba(27,107,58,0.07);
    padding: 1.5rem;
    margin-bottom: 1.5rem;
  }
  .section-title {
    font-size: 1rem;
    font-weight: 700;
    color: var(--primary);
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
    gap: .5rem;
  }
  .section-title::before {
    content: '';
    display: inline-block;
    width: 4px;
    height: 1.1em;
    background: var(--primary);
    border-radius: 2px;
  }

  /* DB error banner */
  .db-error { background: #fff0f0; border: 1px solid #fca5a5; border-radius: 10px; padding: 1rem; color: #b91c1c; margin-bottom: 1rem; }

  /* Scrollable table wrapper */
  .table-scroll { overflow-x: auto; border-radius: 10px; }

  /* Group row in detail table */
  .group-header td {
    background: linear-gradient(90deg, #1B6B3A 0%, #5DBF82 100%) !important;
    color: white !important;
    font-weight: 700 !important;
    font-size: .8rem;
    letter-spacing: .04em;
  }

  /* Badge */
  .badge {
    display: inline-block;
    padding: 2px 10px;
    border-radius: 99px;
    font-size: .75rem;
    font-weight: 600;
  }
  .badge-green { background: #d1fae5; color: #065f46; }
  .badge-blue  { background: #dbeafe; color: #1e40af; }
  .badge-warn  { background: #fef3c7; color: #92400e; }
  .badge-red   { background: #fee2e2; color: #991b1b; }

  @media (max-width: 640px) {
    .data-table th, .data-table td { padding: 7px 8px; font-size: .78rem; }
  }
</style>
</head>
<body class="min-h-screen">

<!-- ===== HEADER BAR ===== -->
<div class="header-bar text-white shadow-lg">
  <div class="max-w-screen-xl mx-auto px-4 py-4">

    <!-- Top row: title + month input + datetime -->
    <div class="flex flex-wrap items-center gap-3 mb-3">
      <div class="flex items-center gap-3 flex-1 min-w-0">
        <div class="w-10 h-10 rounded-xl bg-white/20 flex items-center justify-center text-xl flex-shrink-0">📊</div>
        <div class="min-w-0">
          <h1 class="text-base sm:text-xl font-bold leading-tight">รายงานการส่งข้อมูล Claim ผู้ป่วยใน</h1>
          <p class="text-green-100 text-xs sm:text-sm">โรงพยาบาลโพนทอง · กลุ่มงานประกันสุขภาพ ยุทธศาสตร์</p>
          <p class="text-green-200 text-xs mt-0.5">✍️ จัดทำโดย นางสาวนภาจรัส พรมรี นักสาธารณสุขชำนาญการ</p>
        </div>
      </div>
      <div class="flex items-center gap-2 flex-shrink-0">
        <label class="text-xs text-green-100">ประจำเดือน</label>
        <input type="text" id="monthLabel"
          class="bg-white/20 border border-white/40 rounded-lg px-3 py-1.5 text-sm text-white placeholder-green-200 focus:outline-none focus:bg-white/30 w-36"
          placeholder="มีนาคม 2569"
          value="<?= htmlspecialchars($_COOKIE['monthLabel'] ?? '') ?>">
      </div>
      <div class="text-xs text-green-100 text-right flex-shrink-0">
        <div id="clock" class="font-mono text-sm text-white font-semibold">--:--:--</div>
        <div id="dateStr" class="text-green-200">-- --- ----</div>
      </div>
    </div>

    <!-- KPI Cards -->
    <?php if (isset($db_error)): ?>
    <div class="db-error mb-3 text-sm">⚠️ เชื่อมต่อฐานข้อมูลไม่ได้: <?= htmlspecialchars($db_error) ?></div>
    <?php endif; ?>

    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-3">
      <!-- Total -->
      <div class="glass-card p-4">
        <div class="text-xs text-gray-500 mb-1">ชาร์ททั้งหมด</div>
        <div class="text-2xl font-bold text-gray-800"><?= number_format($total_an) ?></div>
        <div class="text-xs text-green-600 mt-1">รายการ</div>
      </div>
      <!-- Sent -->
      <div class="glass-card p-4">
        <div class="text-xs text-gray-500 mb-1">ส่งเคลมแล้ว</div>
        <div class="text-2xl font-bold text-green-600"><?= number_format($kpi_status['ส่งเคลมแล้ว']) ?></div>
        <?php $pct = $total_an > 0 ? round($kpi_status['ส่งเคลมแล้ว']/$total_an*100,1) : 0; ?>
        <div class="text-xs text-gray-400 mt-1"><?= $pct ?>%</div>
      </div>
      <!-- Pending claim -->
      <div class="glass-card p-4">
        <div class="text-xs text-gray-500 mb-1">รอเคลม</div>
        <div class="text-2xl font-bold text-yellow-500"><?= number_format($kpi_status['รอเคลม']) ?></div>
        <?php $pct2 = $total_an > 0 ? round($kpi_status['รอเคลม']/$total_an*100,1) : 0; ?>
        <div class="text-xs text-gray-400 mt-1"><?= $pct2 ?>%</div>
      </div>
      <!-- Ward pending -->
      <div class="glass-card p-4">
        <div class="text-xs text-gray-500 mb-1">รอตึกส่งออก</div>
        <div class="text-2xl font-bold text-red-500"><?= number_format($kpi_status['รอตึกส่งออก']) ?></div>
        <?php $pct3 = $total_an > 0 ? round($kpi_status['รอตึกส่งออก']/$total_an*100,1) : 0; ?>
        <div class="text-xs text-gray-400 mt-1"><?= $pct3 ?>%</div>
      </div>
    </div>

    <!-- Tab Navigation -->
    <div class="flex gap-1 overflow-x-auto pb-0">
      <?php foreach ($tabs as $key => $tab): ?>
      <a href="<?= $tab['file'] ?>"
         class="tab-btn <?= $active_tab === $key ? 'active' : '' ?> px-4 py-2 rounded-t-lg text-sm font-medium text-white whitespace-nowrap flex items-center gap-1.5">
        <span><?= $tab['icon'] ?></span>
        <span><?= $tab['label'] ?></span>
      </a>
      <?php endforeach; ?>
    </div>

  </div>
</div>

<!-- Main content area -->
<div class="max-w-screen-xl mx-auto px-4 py-6">

<script>
// Clock
(function() {
  function updateClock() {
    const now = new Date();
    const opts = { timeZone: 'Asia/Bangkok' };
    const t = now.toLocaleTimeString('th-TH', { ...opts, hour12: false });
    const d = now.toLocaleDateString('th-TH', { ...opts, year: 'numeric', month: 'long', day: 'numeric', weekday: 'long' });
    document.getElementById('clock').textContent = t;
    document.getElementById('dateStr').textContent = d;
  }
  updateClock();
  setInterval(updateClock, 1000);

  // Save month label to cookie
  document.getElementById('monthLabel').addEventListener('change', function() {
    document.cookie = 'monthLabel=' + encodeURIComponent(this.value) + '; path=/; max-age=86400';
  });
})();
</script>
