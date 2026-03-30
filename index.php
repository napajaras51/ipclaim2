<?php
require_once 'config.php';
$active_tab = 'home';

// ---- Stacked Horizontal Bar ----
$months_data = [];
if ($pdo) {
    try {
        $r = $pdo->query("SELECT
            month_d,
            SUM(CASE WHEN status = 'ส่งเคลมแล้ว' THEN 1 ELSE 0 END) AS sent,
            SUM(CASE WHEN status = 'รอเคลม'      THEN 1 ELSE 0 END) AS waiting
            FROM tmp_chart
            WHERE status IN ('ส่งเคลมแล้ว', 'รอเคลม')
              AND month_d IS NOT NULL
            GROUP BY month_d
            ORDER BY month_d ASC");
        $months_data = $r->fetchAll();
    } catch (Exception $e) {}
}

// ---- Pivot: ส่งเคลมแล้ว แยกกองทุน ----
$pivot_sent = [];
if ($pdo) {
    try {
        $r = $pdo->query("SELECT
            COALESCE(mainscl, 'ยอดรวมทั้งหมด') AS mainscl,
            SUM(CASE WHEN month_d = '2026-02' THEN 1 ELSE 0 END) AS m202602,
            SUM(CASE WHEN month_d = '2026-03' THEN 1 ELSE 0 END) AS m202603,
            COUNT(an) AS total
            FROM tmp_chart
            WHERE status = 'ส่งเคลมแล้ว'
            GROUP BY mainscl WITH ROLLUP");
        $pivot_sent = $r->fetchAll();
    } catch (Exception $e) {}
}

// ---- Pivot: รอเคลม แยกกองทุน (ข้อ 2) ----
$pivot_wait = [];
if ($pdo) {
    try {
        $r = $pdo->query("SELECT
            COALESCE(mainscl, 'ยอดรวมทั้งหมด') AS mainscl,
            SUM(CASE WHEN month_d = '2026-02' THEN 1 ELSE 0 END) AS m202602,
            SUM(CASE WHEN month_d = '2026-03' THEN 1 ELSE 0 END) AS m202603,
            COUNT(an) AS total
            FROM tmp_chart
            WHERE status = 'รอเคลม'
            GROUP BY mainscl WITH ROLLUP");
        $pivot_wait = $r->fetchAll();
    } catch (Exception $e) {}
}

include '_header.php';
?>

<!-- Stacked Horizontal Bar -->
<div class="section-card">
  <div class="section-title">สถานะส่งเคลมรายเดือน — เปรียบเทียบ ส่งเคลมแล้ว vs รอเคลม</div>
  <?php if (empty($months_data)): ?>
    <p class="text-gray-400 text-sm text-center py-6">ไม่พบข้อมูล</p>
  <?php else: ?>
  <div style="position:relative; height:<?= max(180, count($months_data) * 90) ?>px;">
    <canvas id="claimStackChart"></canvas>
  </div>
  <div class="flex gap-6 justify-center mt-4 text-sm">
    <span class="flex items-center gap-2">
      <span style="display:inline-block;width:14px;height:14px;background:#1B6B3A;border-radius:3px;"></span>ส่งเคลมแล้ว
    </span>
    <span class="flex items-center gap-2">
      <span style="display:inline-block;width:14px;height:14px;background:#F2994A;border-radius:3px;"></span>รอเคลม
    </span>
  </div>
  <?php endif; ?>
</div>

<!-- Pivot: ส่งเคลมแล้ว แยกกองทุน -->
<div class="section-card">
  <div class="section-title">✅ ส่งเคลมแล้ว — แยกรายกองทุน เปรียบเทียบรายเดือน</div>
  <div class="table-scroll">
    <table class="data-table">
      <thead>
        <tr>
          <th>กองทุน / สิทธิ</th>
          <th class="center">ก.พ. 2569 (2026-02)</th>
          <th class="center">มี.ค. 2569 (2026-03)</th>
          <th class="center">รวมทั้งหมด</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($pivot_sent)): ?>
        <tr><td colspan="4" class="center text-gray-400 py-6">ไม่พบข้อมูล</td></tr>
        <?php else: foreach ($pivot_sent as $row): ?>
        <tr>
          <td><?= htmlspecialchars($row['mainscl']) ?></td>
          <td class="center"><?= number_format($row['m202602']) ?></td>
          <td class="center"><?= number_format($row['m202603']) ?></td>
          <td class="center font-semibold"><?= number_format($row['total']) ?></td>
        </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Pivot: รอเคลม แยกกองทุน -->
<div class="section-card">
  <div class="section-title">⏳ รอเคลม — แยกรายกองทุน เปรียบเทียบรายเดือน</div>
  <div class="table-scroll">
    <table class="data-table">
      <thead>
        <tr>
          <th>กองทุน / สิทธิ</th>
          <th class="center">ก.พ. 2569 (2026-02)</th>
          <th class="center">มี.ค. 2569 (2026-03)</th>
          <th class="center">รวมทั้งหมด</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($pivot_wait)): ?>
        <tr><td colspan="4" class="center text-gray-400 py-6">ไม่พบข้อมูล</td></tr>
        <?php else: foreach ($pivot_wait as $row): ?>
        <tr>
          <td><?= htmlspecialchars($row['mainscl']) ?></td>
          <td class="center"><?= number_format($row['m202602']) ?></td>
          <td class="center"><?= number_format($row['m202603']) ?></td>
          <td class="center font-semibold"><?= number_format($row['total']) ?></td>
        </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2"></script>
<script>
Chart.register(ChartDataLabels);
const monthsRaw = <?= json_encode($months_data) ?>;
if (monthsRaw.length > 0) {
  const labels   = monthsRaw.map(r => r.month_d);
  const sentData = monthsRaw.map(r => parseInt(r.sent)    || 0);
  const waitData = monthsRaw.map(r => parseInt(r.waiting) || 0);

  new Chart(document.getElementById('claimStackChart'), {
    type: 'bar',
    data: {
      labels,
      datasets: [
        {
          label: 'ส่งเคลมแล้ว',
          data: sentData,
          backgroundColor: '#1B6B3A',
          stack: 'stack',
          datalabels: {
            anchor: 'center', align: 'center',
            color: '#fff', font: { size: 12, weight: '700' },
            formatter: v => v > 0 ? v.toLocaleString('th-TH') + ' ราย' : ''
          }
        },
        {
          label: 'รอเคลม',
          data: waitData,
          backgroundColor: '#F2994A',
          stack: 'stack',
          datalabels: {
            anchor: 'center', align: 'center',
            color: '#fff', font: { size: 12, weight: '700' },
            formatter: v => v > 0 ? v.toLocaleString('th-TH') + ' ราย' : ''
          }
        }
      ]
    },
    options: {
      indexAxis: 'y',
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: { display: false },
        tooltip: {
          callbacks: { label: ctx => ` ${ctx.dataset.label}: ${ctx.raw.toLocaleString('th-TH')} ราย` }
        },
        datalabels: {}
      },
      scales: {
        x: {
          stacked: true, beginAtZero: true,
          ticks: { callback: v => v.toLocaleString('th-TH') },
          grid: { color: '#e0f0e6' }
        },
        y: {
          stacked: true, grid: { display: false },
          ticks: { font: { size: 13, weight: '600' } }
        }
      }
    }
  });
}
</script>

<?php include '_footer.php'; ?>
