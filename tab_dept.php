<?php
require_once 'config.php';
$active_tab = 'dept';

// 7.1 Donut: สัดส่วนรอเคลมแยกแผนก
$dept_donut = [];
if ($pdo) {
    try {
        $r = $pdo->query("SELECT status_chart, COUNT(an) AS total_dep
            FROM tmp_chart
            WHERE status_chart NOT IN ('ส่งเคลมแล้ว','รอตึกส่งออก')
            GROUP BY status_chart");
        $dept_donut = $r->fetchAll();
    } catch (Exception $e) {}
}

// 7.2 Pivot: รอเคลมแยกแผนก เปรียบเทียบรายเดือน — แก้ bug: alias ซ้ำ
$pivot_dept = [];
if ($pdo) {
    try {
        $r = $pdo->query("SELECT
            COALESCE(status_chart,'ยอดรวมทั้งหมด') AS dept_name,
            SUM(CASE WHEN month_d = '2026-02' THEN 1 ELSE 0 END) AS m202602,
            SUM(CASE WHEN month_d = '2026-03' THEN 1 ELSE 0 END) AS m202603,
            COUNT(an) AS total
            FROM tmp_chart
            WHERE status_chart NOT IN ('ส่งเคลมแล้ว','รอตึกส่งออก')
            GROUP BY status_chart WITH ROLLUP");
        $pivot_dept = $r->fetchAll();
    } catch (Exception $e) {}
}

include '_header.php';
?>

<!-- Donut Chart: แยกแผนก -->
<div class="section-card">
  <div class="section-title">สัดส่วนชาร์ทรอเคลม — แยกรายแผนก</div>
  <?php if (empty($dept_donut)): ?>
    <p class="text-gray-400 text-sm text-center py-6">ไม่พบข้อมูล</p>
  <?php else: ?>
  <div class="flex justify-center">
    <div style="max-width: 540px; width: 100%;">
      <canvas id="deptDonutChart"></canvas>
    </div>
  </div>
  <?php endif; ?>
</div>

<!-- Pivot: แยกแผนก รายเดือน -->
<div class="section-card">
  <div class="section-title">ชาร์ทรอเคลม — แยกรายแผนก เปรียบเทียบรายเดือน</div>
  <div class="table-scroll">
    <table class="data-table">
      <thead>
        <tr>
          <th>แผนก (สถานะ)</th>
          <th class="center">ก.พ. 2569</th>
          <th class="center">มี.ค. 2569</th>
          <th class="center">รวมทั้งหมด</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($pivot_dept)): ?>
        <tr><td colspan="4" class="center text-gray-400 py-6">ไม่พบข้อมูล</td></tr>
        <?php else: foreach ($pivot_dept as $row): ?>
        <tr>
          <td><?= htmlspecialchars($row['dept_name']) ?></td>
          <td class="center"><?= number_format($row['m202602']) ?></td>
          <td class="center"><?= number_format($row['m202603']) ?></td>
          <td class="center font-semibold"><?= number_format($row['total']) ?></td>
        </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
const deptData = <?= json_encode($dept_donut) ?>;
if (deptData.length > 0) {
  const palette = [
    '#1B6B3A','#5DBF82','#F2994A','#27AE60','#239150',
    '#A8DFB9','#EB5757','#7ED9A0','#F2C94C','#3BAD69',
    '#C5EDCE','#0f4023','#92D4A8','#FFEAA7','#B2DFDB'
  ];
  const labels = deptData.map(r => r.status_chart || '(ไม่ระบุ)');
  const values = deptData.map(r => parseInt(r.total_dep));
  const total = values.reduce((a,b) => a+b, 0);

  new Chart(document.getElementById('deptDonutChart'), {
    type: 'doughnut',
    data: {
      labels,
      datasets: [{
        data: values,
        backgroundColor: palette.slice(0, labels.length),
        borderWidth: 2,
        borderColor: '#fff',
        hoverOffset: 8
      }]
    },
    options: {
      cutout: '52%',
      responsive: true,
      plugins: {
        legend: {
          position: 'right',
          labels: {
            font: { size: 12 },
            padding: 14,
            generateLabels(chart) {
              const ds = chart.data.datasets[0];
              return chart.data.labels.map((label, i) => ({
                text: `${label}: ${values[i].toLocaleString('th-TH')}`,
                fillStyle: ds.backgroundColor[i],
                strokeStyle: '#fff',
                lineWidth: 1,
                index: i
              }));
            }
          }
        },
        tooltip: {
          callbacks: {
            label: ctx => {
              const pct = ((ctx.raw / total) * 100).toFixed(1);
              return ` ${ctx.label}: ${ctx.raw.toLocaleString('th-TH')} ราย (${pct}%)`;
            }
          }
        }
      }
    },
    plugins: [{
      id: 'center_text',
      afterDraw(chart) {
        const { ctx, chartArea: { left, top, right, bottom } } = chart;
        const cx = (left + right) / 2, cy = (top + bottom) / 2;
        ctx.save();
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        ctx.fillStyle = '#1F2937';
        ctx.font = 'bold 26px IBM Plex Sans Thai, Sarabun, sans-serif';
        ctx.fillText(total.toLocaleString('th-TH'), cx, cy - 8);
        ctx.font = '13px IBM Plex Sans Thai, Sarabun, sans-serif';
        ctx.fillStyle = '#6B7280';
        ctx.fillText('รายการ', cx, cy + 14);
        ctx.restore();
      }
    }]
  });
}
</script>

<?php include '_footer.php'; ?>
