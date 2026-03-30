<?php
require_once 'config.php';
$active_tab = 'doctor';

// 5.1 Bar chart
$doctor_chart = [];
if ($pdo) {
    try {
        $r = $pdo->query("SELECT doctor_name AS doctor, COUNT(an) AS total_doctor
            FROM tmp_chart
            WHERE status_chart = 'รอแพทย์สรุป'
            GROUP BY doctor_name ORDER BY total_doctor DESC");
        $doctor_chart = $r->fetchAll();
    } catch (Exception $e) {}
}

// 5.2 Pivot
$pivot_doctor = [];
if ($pdo) {
    try {
        $r = $pdo->query("SELECT
            COALESCE(doctor_name,'ยอดรวมทั้งหมด') AS doctor7,
            SUM(CASE WHEN month_d = '2026-02' THEN 1 ELSE 0 END) AS m202602,
            SUM(CASE WHEN month_d = '2026-03' THEN 1 ELSE 0 END) AS m202603,
            COUNT(an) AS total_doctor7
            FROM tmp_chart
            WHERE status_chart = 'รอแพทย์สรุป' AND date_diff > 7
            GROUP BY doctor_name WITH ROLLUP");
        $pivot_doctor = $r->fetchAll();
    } catch (Exception $e) {}
}

// 5.3 Detail
$detail_doctor = [];
if ($pdo) {
    try {
        $r = $pdo->query("SELECT an, hn, ptname, admdate, dchdate, date_diff, doctor_name
            FROM tmp_chart
            WHERE status_chart = 'รอแพทย์สรุป' AND date_diff > 7
            ORDER BY doctor_name, date_diff DESC");
        $detail_doctor = $r->fetchAll();
    } catch (Exception $e) {}
}

$grouped = [];
foreach ($detail_doctor as $row) {
    $grouped[$row['doctor_name']][] = $row;
}

$total_records = count($detail_doctor);

function maskName(string $name): string {
    $name = trim($name);
    if ($name === '') return '***';
    $parts = preg_split('/\s+/u', $name);
    $masked = [];
    foreach ($parts as $p) {
        $chars = mb_str_split($p, 1, 'UTF-8');
        if (count($chars) <= 1) { $masked[] = $p . '***'; }
        else { $masked[] = $chars[0] . str_repeat('*', min(3, count($chars)-1)); }
    }
    return implode(' ', $masked);
}

include '_header.php';
?>

<style>
@media print {
  .no-print { display:none !important; }
  body { background:white !important; }
  .section-card { box-shadow:none !important; border:1px solid #ccc !important; break-inside:avoid; }
  .badge,.group-header td { -webkit-print-color-adjust:exact; print-color-adjust:exact; }
}
#printModal {
  display:none; position:fixed; inset:0; z-index:9999;
  background:rgba(0,0,0,0.55); overflow-y:auto;
}
#printModal.open { display:flex; align-items:flex-start; justify-content:center; padding:2rem 1rem; }
#printModalContent {
  background:white; border-radius:14px; width:100%; max-width:960px;
  box-shadow:0 20px 60px rgba(0,0,0,0.3); overflow:hidden;
}
.modal-header {
  background:linear-gradient(135deg,#0f4023,#1B6B3A);
  color:white; padding:1rem 1.5rem;
  display:flex; align-items:center; justify-content:space-between; gap:1rem;
}
.modal-body { padding:1.5rem; max-height:68vh; overflow-y:auto; }
.pdpa-notice {
  background:#fff8e1; border:1px solid #f59e0b; border-radius:8px;
  padding:.65rem 1rem; font-size:.8rem; color:#92400e; margin-bottom:1rem;
}
/* Pagination */
.pagination { display:flex; align-items:center; gap:.5rem; flex-wrap:wrap; }
.pg-btn {
  padding:.35rem .85rem; border-radius:7px; font-size:.82rem; font-weight:600;
  border:1.5px solid #1B6B3A; color:#1B6B3A; background:white; cursor:pointer;
  transition:.15s;
}
.pg-btn:hover:not(:disabled) { background:#1B6B3A; color:white; }
.pg-btn:disabled { opacity:.35; cursor:default; }
.pg-btn.active  { background:#1B6B3A; color:white; }
.pg-info { font-size:.82rem; color:#555; }
</style>

<!-- Bar Chart: รอแพทย์สรุป แยกรายแพทย์ -->
<div class="section-card no-print">
  <div class="section-title">รอแพทย์สรุป — แยกรายแพทย์</div>
  <?php if (empty($doctor_chart)): ?>
    <p class="text-gray-400 text-sm text-center py-6">ไม่พบข้อมูล</p>
  <?php else: ?>
  <div style="overflow-x:auto;">
    <div style="min-width:400px; position:relative;"
         id="doctorChartWrap">
      <canvas id="doctorBarChart"></canvas>
    </div>
  </div>
  <?php endif; ?>
</div>

<!-- Pivot -->
<div class="section-card no-print">
  <div class="section-title">รอแพทย์สรุปเกิน 7 วัน — เปรียบเทียบรายเดือน</div>
  <div class="table-scroll">
    <table class="data-table">
      <thead>
        <tr>
          <th>ชื่อแพทย์</th>
          <th class="center">ก.พ. 2569</th>
          <th class="center">มี.ค. 2569</th>
          <th class="center">รวม</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($pivot_doctor)): ?>
        <tr><td colspan="4" class="center text-gray-400 py-6">ไม่พบข้อมูล</td></tr>
        <?php else: foreach ($pivot_doctor as $row): ?>
        <tr>
          <td><?= htmlspecialchars($row['doctor7']) ?></td>
          <td class="center"><?= number_format($row['m202602']) ?></td>
          <td class="center"><?= number_format($row['m202603']) ?></td>
          <td class="center font-semibold"><?= number_format($row['total_doctor7']) ?></td>
        </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Detail Table + Pagination + Print -->
<div class="section-card">
  <div class="flex items-center justify-between mb-3 flex-wrap gap-2">
    <div class="section-title mb-0" style="margin-bottom:0">
      รายละเอียดคนไข้ รอแพทย์สรุปเกิน 7 วัน
      <span style="font-size:.8rem;font-weight:400;color:#555;margin-left:.5rem;">(<?= $total_records ?> รายการ)</span>
    </div>
    <?php if (!empty($grouped)): ?>
    <button onclick="openPrintPreview()"
      class="no-print flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-semibold text-white"
      style="background:#1B6B3A;">
      🖨️ Preview / พิมพ์ PDF
    </button>
    <?php endif; ?>
  </div>

  <?php if (empty($grouped)): ?>
    <p class="text-gray-400 text-sm text-center py-6">ไม่พบข้อมูล</p>
  <?php else: ?>
  <div class="pdpa-notice">
    🔒 <strong>PDPA:</strong> ชื่อ-สกุลผู้ป่วยถูกปกปิดบางส่วนตาม พ.ร.บ. คุ้มครองข้อมูลส่วนบุคคล พ.ศ. 2562
    — ใช้เลข AN / HN สำหรับการอ้างอิงภายใน
  </div>

  <!-- Pagination controls top -->
  <div class="flex items-center justify-between mb-3 flex-wrap gap-2 no-print">
    <div class="pagination" id="pgTop"></div>
    <div class="pg-info" id="pgInfo"></div>
  </div>

  <div class="table-scroll">
    <table class="data-table">
      <thead>
        <tr>
          <th>#</th><th>AN</th><th>HN</th><th>ชื่อ-สกุล (ปกปิด)</th>
          <th class="center">วันรับ</th><th class="center">วันจำหน่าย</th>
          <th class="center">ค้าง (วัน)</th><th>แพทย์</th>
        </tr>
      </thead>
      <tbody id="doctorDetailBody">
        <!-- Rendered by JS pagination -->
      </tbody>
    </table>
  </div>

  <!-- Pagination controls bottom -->
  <div class="flex items-center justify-between mt-3 flex-wrap gap-2 no-print">
    <div class="pagination" id="pgBottom"></div>
    <div class="pg-info" id="pgInfoB"></div>
  </div>

  <!-- Hidden: full data for JS -->
  <script id="doctorDetailData" type="application/json">
  <?php
  // Flatten grouped into array of rows (keep group header info)
  $flat = [];
  foreach ($grouped as $docName => $patients) {
      $flat[] = ['_group' => true, 'doctor_name' => $docName, 'count' => count($patients)];
      foreach ($patients as $p) {
          $p['ptname_masked'] = maskName($p['ptname']);
          $flat[] = $p;
      }
  }
  echo json_encode($flat, JSON_UNESCAPED_UNICODE);
  ?>
  </script>
  <?php endif; ?>
</div>

<!-- Print Modal -->
<div id="printModal">
  <div id="printModalContent">
    <div class="modal-header">
      <div>
        <div style="font-size:1rem;font-weight:700;">🖨️ Preview — รอแพทย์สรุปเกิน 7 วัน</div>
        <div style="font-size:.78rem;opacity:.8;">โรงพยาบาลโพนทอง · กลุ่มงานประกันสุขภาพ ยุทธศาสตร์</div>
      </div>
      <div style="display:flex;gap:.5rem;flex-shrink:0;">
        <div style="font-size:.78rem;color:rgba(255,255,255,.75);align-self:center;" id="printPageInfo"></div>
        <button onclick="printPrevPage()" id="btnPrintPrev"
          style="background:rgba(255,255,255,.2);color:white;border:1px solid rgba(255,255,255,.4);padding:.4rem .9rem;border-radius:7px;cursor:pointer;font-size:.85rem;">
          ◀ ก่อนหน้า
        </button>
        <button onclick="printNextPage()" id="btnPrintNext"
          style="background:rgba(255,255,255,.2);color:white;border:1px solid rgba(255,255,255,.4);padding:.4rem .9rem;border-radius:7px;cursor:pointer;font-size:.85rem;">
          ถัดไป ▶
        </button>
        <button onclick="doPrint()"
          style="background:white;color:#1B6B3A;border:none;padding:.4rem 1.1rem;border-radius:7px;font-weight:700;font-size:.85rem;cursor:pointer;">
          🖨️ พิมพ์หน้านี้
        </button>
        <button onclick="closePrintPreview()"
          style="background:rgba(255,255,255,.15);color:white;border:1px solid rgba(255,255,255,.35);padding:.4rem .9rem;border-radius:7px;cursor:pointer;">
          ✕
        </button>
      </div>
    </div>
    <div class="modal-body" id="printModalBody"></div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2"></script>
<script>
// ===== BAR CHART: รอแพทย์สรุป แยกรายแพทย์ (ข้อ 3: เพิ่ม label) =====
Chart.register(ChartDataLabels);
const doctorData = <?= json_encode($doctor_chart) ?>;
if (doctorData.length > 0) {
  const labels  = doctorData.map(r => r.doctor || '(ไม่ระบุ)');
  const values  = doctorData.map(r => parseInt(r.total_doctor));
  const maxVal  = Math.max(...values);
  const wrap    = document.getElementById('doctorChartWrap');
  const canvas  = document.getElementById('doctorBarChart');
  const barH    = 44;
  const chartH  = Math.max(220, labels.length * barH);
  wrap.style.height  = chartH + 'px';
  canvas.style.height = chartH + 'px';

  new Chart(canvas, {
    type: 'bar',
    data: {
      labels,
      datasets: [{
        label: 'จำนวนชาร์ท',
        data: values,
        backgroundColor: labels.map((_, i) => `hsl(${140 + i*5}, ${Math.max(35,60-i*2)}%, ${Math.min(55,35+i*3)}%)`),
        borderRadius: 4,
        borderSkipped: false,
      }]
    },
    options: {
      indexAxis: 'y',
      responsive: true,
      maintainAspectRatio: false,
      layout: { padding: { right: 56 } },
      plugins: {
        legend: { display: false },
        tooltip: { callbacks: { label: ctx => ` ${ctx.raw.toLocaleString('th-TH')} ราย` } },
        datalabels: {
          anchor: 'end',
          align: 'end',
          color: '#1A2E22',
          font: { size: 12, weight: '700' },
          formatter: v => v.toLocaleString('th-TH') + ' ราย'
        }
      },
      scales: {
        x: {
          beginAtZero: true,
          max: Math.ceil(maxVal * 1.18),
          ticks: { callback: v => v.toLocaleString('th-TH') },
          grid: { color: '#e0f0e6' }
        },
        y: { grid: { display: false }, ticks: { font: { size: 12 } } }
      }
    }
  });
}

// ===== PAGINATION (ข้อ 4) =====
const PER_PAGE = 50;
let currentPage = 1;
let allRows = [];

try {
  allRows = JSON.parse(document.getElementById('doctorDetailData')?.textContent || '[]');
} catch(e) { allRows = []; }

// Build data rows only (exclude group headers for page counting)
const dataRowsOnly = allRows.filter(r => !r._group);
const totalPages   = Math.max(1, Math.ceil(dataRowsOnly.length / PER_PAGE));

function getBadgeClass(d) {
  return d > 14 ? 'badge-red' : (d > 7 ? 'badge-warn' : 'badge-blue');
}

function renderPage(page) {
  currentPage = Math.max(1, Math.min(page, totalPages));
  const start = (currentPage - 1) * PER_PAGE;
  const end   = start + PER_PAGE;
  const pageRows = dataRowsOnly.slice(start, end);

  // Group page rows by doctor
  const pageGrouped = {};
  pageRows.forEach(r => {
    if (!pageGrouped[r.doctor_name]) pageGrouped[r.doctor_name] = [];
    pageGrouped[r.doctor_name].push(r);
  });

  let html = '', rowNum = start + 1;
  for (const [doc, patients] of Object.entries(pageGrouped)) {
    html += `<tr class="group-header">
      <td colspan="8">👨‍⚕️ ${escHtml(doc || '(ไม่ระบุแพทย์)')}
        <span style="opacity:.75;margin-left:.5rem;">(${patients.length} ราย ในหน้านี้)</span>
      </td></tr>`;
    patients.forEach(p => {
      const d = parseInt(p.date_diff) || 0;
      html += `<tr>
        <td class="center text-xs text-gray-400">${rowNum++}</td>
        <td class="font-mono text-xs">${escHtml(p.an)}</td>
        <td class="font-mono text-xs">${escHtml(p.hn)}</td>
        <td>${escHtml(p.ptname_masked)}</td>
        <td class="center text-xs">${escHtml(p.admdate)}</td>
        <td class="center text-xs">${escHtml(p.dchdate)}</td>
        <td class="center"><span class="badge ${getBadgeClass(d)}">${d} วัน</span></td>
        <td class="text-xs">${escHtml(p.doctor_name)}</td>
      </tr>`;
    });
  }

  document.getElementById('doctorDetailBody').innerHTML = html;
  renderPagination();
}

function renderPagination() {
  const info = `หน้า ${currentPage} / ${totalPages} (แสดง ${Math.min(PER_PAGE,(currentPage-1)*PER_PAGE+1)}–${Math.min(currentPage*PER_PAGE, dataRowsOnly.length)} จาก ${dataRowsOnly.length} รายการ)`;
  document.getElementById('pgInfo').textContent  = info;
  document.getElementById('pgInfoB').textContent = info;

  ['pgTop','pgBottom'].forEach(id => {
    const pg = document.getElementById(id);
    let btns = '';
    btns += `<button class="pg-btn" onclick="renderPage(1)" ${currentPage===1?'disabled':''}>« หน้าแรก</button>`;
    btns += `<button class="pg-btn" onclick="renderPage(${currentPage-1})" ${currentPage===1?'disabled':''}>‹ ก่อนหน้า</button>`;

    // Page number buttons (show up to 5 around current)
    const s = Math.max(1, currentPage-2), e = Math.min(totalPages, currentPage+2);
    for (let i=s; i<=e; i++) {
      btns += `<button class="pg-btn ${i===currentPage?'active':''}" onclick="renderPage(${i})">${i}</button>`;
    }

    btns += `<button class="pg-btn" onclick="renderPage(${currentPage+1})" ${currentPage===totalPages?'disabled':''}>ถัดไป ›</button>`;
    btns += `<button class="pg-btn" onclick="renderPage(${totalPages})" ${currentPage===totalPages?'disabled':''}>หน้าสุดท้าย »</button>`;
    pg.innerHTML = btns;
  });
}

function escHtml(s) {
  return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

if (allRows.length > 0) renderPage(1);

// ===== PRINT PREVIEW with pagination =====
let printCurrentPage = 1;
const PRINT_PER_PAGE = 50;
const printTotalPages = Math.max(1, Math.ceil(dataRowsOnly.length / PRINT_PER_PAGE));

function openPrintPreview() {
  printCurrentPage = 1;
  document.getElementById('printModal').classList.add('open');
  renderPrintPage();
}
function closePrintPreview() {
  document.getElementById('printModal').classList.remove('open');
}
function printNextPage() {
  if (printCurrentPage < printTotalPages) { printCurrentPage++; renderPrintPage(); }
}
function printPrevPage() {
  if (printCurrentPage > 1) { printCurrentPage--; renderPrintPage(); }
}

function renderPrintPage() {
  const now  = new Date().toLocaleString('th-TH', {timeZone:'Asia/Bangkok'});
  const ml   = document.getElementById('monthLabel')?.value || '';
  const start= (printCurrentPage - 1) * PRINT_PER_PAGE;
  const slice= dataRowsOnly.slice(start, start + PRINT_PER_PAGE);

  document.getElementById('printPageInfo').textContent =
    `หน้า ${printCurrentPage}/${printTotalPages}`;
  document.getElementById('btnPrintPrev').disabled = printCurrentPage <= 1;
  document.getElementById('btnPrintNext').disabled = printCurrentPage >= printTotalPages;

  // Group
  const pg = {};
  slice.forEach(r => { if(!pg[r.doctor_name]) pg[r.doctor_name]=[]; pg[r.doctor_name].push(r); });

  let rows = '', rn = start + 1;
  for (const [doc, pts] of Object.entries(pg)) {
    rows += `<tr style="background:linear-gradient(90deg,#1B6B3A,#5DBF82);color:white;">
      <td colspan="8" style="padding:6px 10px;font-weight:700;">👨‍⚕️ ${escHtml(doc||'(ไม่ระบุ)')} (${pts.length} ราย)</td></tr>`;
    pts.forEach(p => {
      const d = parseInt(p.date_diff)||0;
      const bc = d>14?'#fee2e2;color:#991b1b':d>7?'#fef3c7;color:#92400e':'#dbeafe;color:#1e40af';
      rows += `<tr>
        <td style="color:#999;font-size:8pt;">${rn++}</td>
        <td style="font-family:monospace;font-size:8pt;">${escHtml(p.an)}</td>
        <td style="font-family:monospace;font-size:8pt;">${escHtml(p.hn)}</td>
        <td>${escHtml(p.ptname_masked)}</td>
        <td style="text-align:center;font-size:8pt;">${escHtml(p.admdate)}</td>
        <td style="text-align:center;font-size:8pt;">${escHtml(p.dchdate)}</td>
        <td style="text-align:center;"><span style="background:${bc};padding:1px 8px;border-radius:99px;font-size:8pt;font-weight:700;">${d} วัน</span></td>
        <td style="font-size:8pt;">${escHtml(p.doctor_name)}</td>
      </tr>`;
    });
  }

  document.getElementById('printModalBody').innerHTML = `
    <div style="font-family:'Sarabun',sans-serif;font-size:.85rem;color:#1A2E22;">
      <div style="text-align:center;margin-bottom:1rem;padding-bottom:.75rem;border-bottom:2px solid #1B6B3A;">
        <div style="font-size:1.05rem;font-weight:700;color:#1B6B3A;">รายงานการส่งข้อมูล Claim ผู้ป่วยใน</div>
        <div style="font-size:.85rem;color:#555;">โรงพยาบาลโพนทอง · กลุ่มงานประกันสุขภาพ ยุทธศาสตร์</div>
        <div style="font-size:.78rem;color:#239150;font-weight:600;">จัดทำโดย นางสาวนภาจรัส พรมรี นักสาธารณสุขชำนาญการ</div>
        ${ml?`<div style="font-size:.8rem;color:#239150;">ประจำเดือน ${ml}</div>`:''}
        <div style="font-size:.75rem;color:#888;margin-top:.2rem;">
          รอแพทย์สรุปเกิน 7 วัน | หน้า ${printCurrentPage}/${printTotalPages} | พิมพ์: ${now}
        </div>
      </div>
      <div style="background:#fff8e1;border:1px solid #f59e0b;border-radius:6px;padding:.5rem .9rem;font-size:.75rem;color:#92400e;margin-bottom:.75rem;">
        🔒 PDPA: ชื่อ-สกุลผู้ป่วยถูกปกปิดบางส่วนตาม พ.ร.บ. คุ้มครองข้อมูลส่วนบุคคล พ.ศ. 2562
      </div>
      <table style="width:100%;border-collapse:collapse;font-size:.8rem;">
        <thead>
          <tr>
            <th style="background:#1B6B3A;color:white;padding:7px 8px;text-align:center;">#</th>
            <th style="background:#1B6B3A;color:white;padding:7px 8px;">AN</th>
            <th style="background:#1B6B3A;color:white;padding:7px 8px;">HN</th>
            <th style="background:#1B6B3A;color:white;padding:7px 8px;">ชื่อ-สกุล (ปกปิด)</th>
            <th style="background:#1B6B3A;color:white;padding:7px 8px;text-align:center;">วันรับ</th>
            <th style="background:#1B6B3A;color:white;padding:7px 8px;text-align:center;">วันจำหน่าย</th>
            <th style="background:#1B6B3A;color:white;padding:7px 8px;text-align:center;">ค้าง (วัน)</th>
            <th style="background:#1B6B3A;color:white;padding:7px 8px;">แพทย์</th>
          </tr>
        </thead>
        <tbody>${rows}</tbody>
      </table>
    </div>`;
}

function doPrint() {
  const content = document.getElementById('printModalBody').innerHTML;
  const win = window.open('','_blank','width=960,height=720');
  win.document.write(`<!DOCTYPE html>
<html lang="th"><head><meta charset="UTF-8">
<title>รอแพทย์สรุปเกิน 7 วัน — รพ.โพนทอง</title>
<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;600;700&display=swap" rel="stylesheet">
<style>
  * { font-family:'Sarabun',sans-serif; box-sizing:border-box; }
  body { margin:1.2cm; color:#1A2E22; font-size:10pt; }
  table { width:100%; border-collapse:collapse; }
  th { background:#1B6B3A !important; color:white !important; padding:5px 7px; -webkit-print-color-adjust:exact; print-color-adjust:exact; }
  td { padding:4px 7px; border-bottom:1px solid #c9e8d4; vertical-align:middle; font-size:9pt; }
  tr:nth-child(even) td { background:#eaf7ef; -webkit-print-color-adjust:exact; print-color-adjust:exact; }
  @page { margin:1.2cm; size:A4 landscape; }
</style>
</head><body>${content}</body></html>`);
  win.document.close();
  win.onload = () => { win.focus(); win.print(); };
}

document.getElementById('printModal').addEventListener('click', function(e){
  if (e.target===this) closePrintPreview();
});
</script>

<?php include '_footer.php'; ?>
