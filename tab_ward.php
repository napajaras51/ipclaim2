<?php
require_once 'config.php';
$active_tab = 'ward';

$ward_donut = [];
if ($pdo) {
    try {
        $r = $pdo->query("SELECT wardname, COUNT(an) AS total_ward
            FROM tmp_chart WHERE status = 'รอตึกส่งออก'
            GROUP BY wardname ORDER BY total_ward DESC");
        $ward_donut = $r->fetchAll();
    } catch (Exception $e) {}
}

$pivot_ward = [];
if ($pdo) {
    try {
        $r = $pdo->query("SELECT
            COALESCE(wardname,'ยอดรวมทั้งหมด') AS ward3,
            SUM(CASE WHEN month_d='2026-02' THEN 1 ELSE 0 END) AS total_0226,
            SUM(CASE WHEN month_d='2026-02' AND date_diff>3 THEN 1 ELSE 0 END) AS over3_0226,
            SUM(CASE WHEN month_d='2026-03' THEN 1 ELSE 0 END) AS total_0326,
            SUM(CASE WHEN month_d='2026-03' AND date_diff>3 THEN 1 ELSE 0 END) AS over3_0326,
            COUNT(an) AS total_all_ward3,
            SUM(CASE WHEN date_diff>3 THEN 1 ELSE 0 END) AS total_over3_ward3
            FROM tmp_chart WHERE status='รอตึกส่งออก'
            GROUP BY wardname WITH ROLLUP");
        $pivot_ward = $r->fetchAll();
    } catch (Exception $e) {}
}

$detail_ward = [];
if ($pdo) {
    try {
        $r = $pdo->query("SELECT an, hn, ptname, admdate, dchdate, date_diff, wardname
            FROM tmp_chart WHERE status='รอตึกส่งออก'
            ORDER BY wardname, date_diff DESC");
        $detail_ward = $r->fetchAll();
    } catch (Exception $e) {}
}

$grouped_ward = [];
foreach ($detail_ward as $row) { $grouped_ward[$row['wardname']][] = $row; }
$total_ward_records = count($detail_ward);

function maskName(string $name): string {
    $name = trim($name);
    if ($name === '') return '***';
    $parts = preg_split('/\s+/u', $name);
    $masked = [];
    foreach ($parts as $p) {
        $chars = mb_str_split($p, 1, 'UTF-8');
        $masked[] = count($chars)<=1 ? $p.'***' : $chars[0].str_repeat('*',min(3,count($chars)-1));
    }
    return implode(' ', $masked);
}

include '_header.php';
?>

<style>
.treemap-wrap { position:relative; width:100%; overflow:hidden; border-radius:10px; }
.treemap-cell {
  position:absolute; box-sizing:border-box; display:flex; flex-direction:column;
  align-items:center; justify-content:center; text-align:center;
  cursor:default; border:2px solid white; border-radius:4px;
  transition:filter .15s; padding:4px;
}
.treemap-cell:hover { filter:brightness(1.12); z-index:10; }
.treemap-label { font-weight:700; line-height:1.2; }
.treemap-val   { font-weight:600; opacity:.92; }
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
.pagination { display:flex; align-items:center; gap:.5rem; flex-wrap:wrap; }
.pg-btn {
  padding:.35rem .85rem; border-radius:7px; font-size:.82rem; font-weight:600;
  border:1.5px solid #1B6B3A; color:#1B6B3A; background:white; cursor:pointer; transition:.15s;
}
.pg-btn:hover:not(:disabled) { background:#1B6B3A; color:white; }
.pg-btn:disabled { opacity:.35; cursor:default; }
.pg-btn.active  { background:#1B6B3A; color:white; }
.pg-info { font-size:.82rem; color:#555; }
</style>

<!-- Donut + Treemap -->
<div class="section-card">
  <div class="section-title">รอตึกส่งออก — สัดส่วนแยกรายตึก</div>
  <?php if (empty($ward_donut)): ?>
    <p class="text-gray-400 text-sm text-center py-6">ไม่พบข้อมูล</p>
  <?php else: ?>
  <div style="display:flex;flex-wrap:wrap;gap:1.5rem;align-items:flex-start;">
    <div style="flex:1;min-width:260px;max-width:400px;">
      <div style="font-size:.8rem;font-weight:600;color:#1B6B3A;margin-bottom:.5rem;text-align:center;">Donut Chart</div>
      <canvas id="wardDonutChart"></canvas>
    </div>
    <div style="flex:1;min-width:280px;">
      <div style="font-size:.8rem;font-weight:600;color:#1B6B3A;margin-bottom:.5rem;text-align:center;">Tree Map</div>
      <div class="treemap-wrap" id="wardTreemap" style="height:300px;background:#f2faf5;"></div>
    </div>
  </div>
  <?php endif; ?>
</div>

<!-- Pivot -->
<div class="section-card">
  <div class="section-title">รอตึกส่งออกเกิน 3 วัน — เปรียบเทียบรายเดือน</div>
  <div class="table-scroll">
    <table class="data-table">
      <thead>
        <tr>
          <th rowspan="2" style="vertical-align:middle">ชื่อตึก</th>
          <th colspan="2" class="center" style="background:#145230;">ก.พ. 2569</th>
          <th colspan="2" class="center" style="background:#1B6B3A;">มี.ค. 2569</th>
          <th rowspan="2" class="center" style="vertical-align:middle">รวมทั้งหมด</th>
          <th rowspan="2" class="center" style="vertical-align:middle">เกิน 3 วัน (รวม)</th>
        </tr>
        <tr>
          <th class="center" style="background:#1e6038;font-size:.8rem;">ยอดรวม</th>
          <th class="center" style="background:#1e6038;font-size:.8rem;">เกิน 3 วัน</th>
          <th class="center" style="background:#239150;font-size:.8rem;">ยอดรวม</th>
          <th class="center" style="background:#239150;font-size:.8rem;">เกิน 3 วัน</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($pivot_ward)): ?>
        <tr><td colspan="7" class="center text-gray-400 py-6">ไม่พบข้อมูล</td></tr>
        <?php else: foreach ($pivot_ward as $row): ?>
        <tr>
          <td><?= htmlspecialchars($row['ward3']) ?></td>
          <td class="center"><?= number_format($row['total_0226']) ?></td>
          <td class="center text-orange-600 font-semibold"><?= number_format($row['over3_0226']) ?></td>
          <td class="center"><?= number_format($row['total_0326']) ?></td>
          <td class="center text-orange-600 font-semibold"><?= number_format($row['over3_0326']) ?></td>
          <td class="center font-bold"><?= number_format($row['total_all_ward3']) ?></td>
          <td class="center font-bold text-red-600"><?= number_format($row['total_over3_ward3']) ?></td>
        </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Detail Table + Pagination -->
<div class="section-card">
  <div class="flex items-center justify-between mb-3 flex-wrap gap-2">
    <div class="section-title mb-0" style="margin-bottom:0">
      รายละเอียดคนไข้ รอตึกส่งออก — แยกรายตึก
      <span style="font-size:.8rem;font-weight:400;color:#555;margin-left:.5rem;">(<?= $total_ward_records ?> รายการ)</span>
    </div>
    <?php if (!empty($grouped_ward)): ?>
    <button onclick="openPrintPreview()"
      class="no-print flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-semibold text-white"
      style="background:#1B6B3A;">
      🖨️ Preview / พิมพ์ PDF
    </button>
    <?php endif; ?>
  </div>

  <?php if (empty($grouped_ward)): ?>
    <p class="text-gray-400 text-sm text-center py-6">ไม่พบข้อมูล</p>
  <?php else: ?>
  <div class="pdpa-notice">
    🔒 <strong>PDPA:</strong> ชื่อ-สกุลผู้ป่วยถูกปกปิดบางส่วนตาม พ.ร.บ. คุ้มครองข้อมูลส่วนบุคคล พ.ศ. 2562
    — ใช้เลข AN / HN สำหรับการอ้างอิงภายใน
  </div>

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
          <th class="center">ค้าง (วัน)</th><th>ตึก</th>
        </tr>
      </thead>
      <tbody id="wardDetailBody"></tbody>
    </table>
  </div>

  <div class="flex items-center justify-between mt-3 flex-wrap gap-2 no-print">
    <div class="pagination" id="pgBottom"></div>
    <div class="pg-info" id="pgInfoB"></div>
  </div>

  <script id="wardDetailData" type="application/json">
  <?php
  $flat = [];
  foreach ($grouped_ward as $ward => $patients) {
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
        <div style="font-size:1rem;font-weight:700;">🖨️ Preview — รอตึกส่งออก</div>
        <div style="font-size:.78rem;opacity:.8;">โรงพยาบาลโพนทอง · กลุ่มงานประกันสุขภาพ ยุทธศาสตร์</div>
      </div>
      <div style="display:flex;gap:.5rem;flex-shrink:0;flex-wrap:wrap;">
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

<script>
// ===== DONUT =====
const wardData = <?= json_encode($ward_donut) ?>;
const palette  = ['#1B6B3A','#5DBF82','#27AE60','#A8DFB9','#0f4023','#7ED9A0','#239150','#C5EDCE','#3BAD69','#92D4A8'];

if (wardData.length > 0) {
  const labels = wardData.map(r => r.wardname || '(ไม่ระบุ)');
  const values = wardData.map(r => parseInt(r.total_ward));
  const total  = values.reduce((a,b)=>a+b,0);

  new Chart(document.getElementById('wardDonutChart'), {
    type: 'doughnut',
    data: { labels, datasets:[{ data:values, backgroundColor:palette, borderWidth:2, borderColor:'#fff', hoverOffset:8 }] },
    options: {
      cutout:'55%', responsive:true,
      plugins: {
        legend: { position:'bottom', labels:{ font:{size:12}, padding:12 } },
        tooltip: { callbacks:{ label: ctx => {
          const pct = ((ctx.raw/total)*100).toFixed(1);
          return ` ${ctx.label}: ${ctx.raw.toLocaleString('th-TH')} ราย (${pct}%)`;
        }}}
      }
    },
    plugins:[{ id:'ct', afterDraw(c){
      const {ctx,chartArea:{left,top,right,bottom}}=c;
      const cx=(left+right)/2, cy=(top+bottom)/2;
      ctx.save(); ctx.textAlign='center'; ctx.textBaseline='middle';
      ctx.fillStyle='#1A2E22'; ctx.font='bold 24px IBM Plex Sans Thai,Sarabun,sans-serif';
      ctx.fillText(total.toLocaleString('th-TH'),cx,cy-8);
      ctx.font='12px IBM Plex Sans Thai,Sarabun,sans-serif'; ctx.fillStyle='#6B7280';
      ctx.fillText('รายการ',cx,cy+14); ctx.restore();
    }}]
  });

  renderTreemap('wardTreemap', wardData.map((r,i)=>({
    label: r.wardname||'(ไม่ระบุ)', value:parseInt(r.total_ward), color:palette[i%palette.length]
  })));
}

// ===== TREEMAP =====
function renderTreemap(id, items) {
  const c = document.getElementById(id);
  if (!c||!items.length) return;
  const W=c.offsetWidth||400, H=c.offsetHeight||300;
  c.style.position='relative';
  const total = items.reduce((a,b)=>a+b.value,0);
  const rects = squarify(items.map(i=>({...i,area:(i.value/total)*W*H})),{x:0,y:0,w:W,h:H});
  rects.forEach(r=>{
    const el=document.createElement('div');
    el.className='treemap-cell';
    el.style.cssText=`left:${r.x}px;top:${r.y}px;width:${r.w}px;height:${r.h}px;background:${r.color};`;
    const fs=Math.max(9,Math.min(15,Math.sqrt(r.w*r.h)/7));
    const pct=((r.value/total)*100).toFixed(1);
    if(r.h>28&&r.w>40){
      el.innerHTML=`<span class="treemap-label" style="font-size:${fs}px;color:white;text-shadow:0 1px 3px rgba(0,0,0,.4);">${r.label}</span>
        <span class="treemap-val" style="font-size:${fs-2}px;color:rgba(255,255,255,.9);">${r.value.toLocaleString('th-TH')} (${pct}%)</span>`;
    }
    el.title=`${r.label}: ${r.value.toLocaleString('th-TH')} ราย (${pct}%)`;
    c.appendChild(el);
  });
}
function squarify(items,rect){
  if(!items.length)return[];
  const sorted=[...items].sort((a,b)=>b.area-a.area), result=[];
  layoutRow(sorted,rect,result); return result;
}
function layoutRow(items,rect,result){
  if(!items.length)return;
  const{x,y,w,h}=rect, totalArea=items.reduce((a,i)=>a+i.area,0);
  const side=Math.min(w,h);
  let best=Infinity,rowItems=[],rowArea=0;
  for(let i=0;i<items.length;i++){
    const cand=[...rowItems,items[i]], cArea=rowArea+items[i].area;
    const ratio=worstRatio(cand,cArea,side);
    if(ratio<=best){best=ratio;rowItems=cand;rowArea=cArea;}else break;
  }
  const frac=rowArea/totalArea, horiz=w>=h;
  let cx=x,cy=y;
  rowItems.forEach(it=>{
    const f=it.area/rowArea;
    const rw=horiz?frac*w:f*w, rh=horiz?f*h:frac*h;
    result.push({...it,x:cx,y:cy,w:Math.max(0,rw-2),h:Math.max(0,rh-2)});
    if(horiz)cy+=rh;else cx+=rw;
  });
  const rem=items.slice(rowItems.length);
  if(rem.length){
    const nr=horiz?{x:x+frac*w,y,w:w-frac*w,h}:{x,y:y+frac*h,w,h:h-frac*h};
    layoutRow(rem,nr,result);
  }
}
function worstRatio(items,total,side){
  let worst=0;
  items.forEach(it=>{ const r=(side*side*it.area)/(total*total); worst=Math.max(worst,Math.max(r,1/r)); });
  return worst;
}

// ===== PAGINATION =====
const PER_PAGE = 50;
let currentPage = 1;
let allRows = [];
try { allRows = JSON.parse(document.getElementById('wardDetailData')?.textContent||'[]'); } catch(e){}
const totalPages = Math.max(1, Math.ceil(allRows.length / PER_PAGE));

function escHtml(s){ return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

function badgeClass(d){ return d>7?'badge-red':d>3?'badge-warn':'badge-green'; }

function renderPage(page){
  currentPage = Math.max(1,Math.min(page,totalPages));
  const start=(currentPage-1)*PER_PAGE, slice=allRows.slice(start,start+PER_PAGE);

  // Group by ward
  const pg={};
  slice.forEach(r=>{ if(!pg[r.wardname])pg[r.wardname]=[]; pg[r.wardname].push(r); });

  let html='', rn=start+1;
  for(const[ward,pts] of Object.entries(pg)){
    html+=`<tr class="group-header"><td colspan="8">🏥 ${escHtml(ward||'(ไม่ระบุตึก)')} <span style="opacity:.75;margin-left:.5rem;">(${pts.length} ราย ในหน้านี้)</span></td></tr>`;
    pts.forEach(p=>{
      const d=parseInt(p.date_diff)||0;
      html+=`<tr>
        <td class="center text-xs text-gray-400">${rn++}</td>
        <td class="font-mono text-xs">${escHtml(p.an)}</td>
        <td class="font-mono text-xs">${escHtml(p.hn)}</td>
        <td>${escHtml(p.ptname_masked)}</td>
        <td class="center text-xs">${escHtml(p.admdate)}</td>
        <td class="center text-xs">${escHtml(p.dchdate)}</td>
        <td class="center"><span class="badge ${badgeClass(d)}">${d} วัน</span></td>
        <td class="text-xs">${escHtml(p.wardname)}</td>
      </tr>`;
    });
  }
  document.getElementById('wardDetailBody').innerHTML = html;
  renderPagination();
}

function renderPagination(){
  const info=`หน้า ${currentPage}/${totalPages} (แสดง ${Math.min(PER_PAGE,(currentPage-1)*PER_PAGE+1)}–${Math.min(currentPage*PER_PAGE,allRows.length)} จาก ${allRows.length} รายการ)`;
  ['pgInfo','pgInfoB'].forEach(id=>{ const el=document.getElementById(id); if(el) el.textContent=info; });
  ['pgTop','pgBottom'].forEach(id=>{
    const pg=document.getElementById(id); if(!pg) return;
    let b='';
    b+=`<button class="pg-btn" onclick="renderPage(1)" ${currentPage===1?'disabled':''}>« หน้าแรก</button>`;
    b+=`<button class="pg-btn" onclick="renderPage(${currentPage-1})" ${currentPage===1?'disabled':''}>‹ ก่อนหน้า</button>`;
    const s=Math.max(1,currentPage-2), e=Math.min(totalPages,currentPage+2);
    for(let i=s;i<=e;i++) b+=`<button class="pg-btn ${i===currentPage?'active':''}" onclick="renderPage(${i})">${i}</button>`;
    b+=`<button class="pg-btn" onclick="renderPage(${currentPage+1})" ${currentPage===totalPages?'disabled':''}>ถัดไป ›</button>`;
    b+=`<button class="pg-btn" onclick="renderPage(${totalPages})" ${currentPage===totalPages?'disabled':''}>หน้าสุดท้าย »</button>`;
    pg.innerHTML=b;
  });
}

if(allRows.length>0) renderPage(1);

// ===== PRINT PREVIEW =====
let pCurrent=1;
const PPERPAGE=50;
const pTotal=Math.max(1,Math.ceil(allRows.length/PPERPAGE));

function openPrintPreview(){ pCurrent=1; document.getElementById('printModal').classList.add('open'); renderPrintPage(); }
function closePrintPreview(){ document.getElementById('printModal').classList.remove('open'); }
function printNextPage(){ if(pCurrent<pTotal){ pCurrent++; renderPrintPage(); } }
function printPrevPage(){ if(pCurrent>1){ pCurrent--; renderPrintPage(); } }

function renderPrintPage(){
  const now=new Date().toLocaleString('th-TH',{timeZone:'Asia/Bangkok'});
  const ml=document.getElementById('monthLabel')?.value||'';
  const start=(pCurrent-1)*PPERPAGE, slice=allRows.slice(start,start+PPERPAGE);

  document.getElementById('printPageInfo').textContent=`หน้า ${pCurrent}/${pTotal}`;
  document.getElementById('btnPrintPrev').disabled=pCurrent<=1;
  document.getElementById('btnPrintNext').disabled=pCurrent>=pTotal;

  const pg={};
  slice.forEach(r=>{ if(!pg[r.wardname])pg[r.wardname]=[]; pg[r.wardname].push(r); });

  let rows='', rn=start+1;
  for(const[ward,pts] of Object.entries(pg)){
    rows+=`<tr style="background:linear-gradient(90deg,#1B6B3A,#5DBF82);color:white;">
      <td colspan="8" style="padding:6px 10px;font-weight:700;">🏥 ${escHtml(ward||'(ไม่ระบุตึก)')} (${pts.length} ราย)</td></tr>`;
    pts.forEach(p=>{
      const d=parseInt(p.date_diff)||0;
      const bc=d>7?'#fee2e2;color:#991b1b':d>3?'#fef3c7;color:#92400e':'#d1fae5;color:#065f46';
      rows+=`<tr>
        <td style="color:#999;font-size:8pt;">${rn++}</td>
        <td style="font-family:monospace;font-size:8pt;">${escHtml(p.an)}</td>
        <td style="font-family:monospace;font-size:8pt;">${escHtml(p.hn)}</td>
        <td>${escHtml(p.ptname_masked)}</td>
        <td style="text-align:center;font-size:8pt;">${escHtml(p.admdate)}</td>
        <td style="text-align:center;font-size:8pt;">${escHtml(p.dchdate)}</td>
        <td style="text-align:center;"><span style="background:${bc};padding:1px 8px;border-radius:99px;font-size:8pt;font-weight:700;">${d} วัน</span></td>
        <td style="font-size:8pt;">${escHtml(p.wardname)}</td>
      </tr>`;
    });
  }

  document.getElementById('printModalBody').innerHTML=`
    <div style="font-family:'Sarabun',sans-serif;font-size:.85rem;color:#1A2E22;">
      <div style="text-align:center;margin-bottom:1rem;padding-bottom:.75rem;border-bottom:2px solid #1B6B3A;">
        <div style="font-size:1.05rem;font-weight:700;color:#1B6B3A;">รายงานการส่งข้อมูล Claim ผู้ป่วยใน</div>
        <div style="font-size:.85rem;color:#555;">โรงพยาบาลโพนทอง · กลุ่มงานประกันสุขภาพ ยุทธศาสตร์</div>
        <div style="font-size:.78rem;color:#239150;font-weight:600;">จัดทำโดย นางสาวนภาจรัส พรมรี นักสาธารณสุขชำนาญการ</div>
        ${ml?`<div style="font-size:.8rem;color:#239150;">ประจำเดือน ${ml}</div>`:''}
        <div style="font-size:.75rem;color:#888;margin-top:.2rem;">
          รอตึกส่งออก | หน้า ${pCurrent}/${pTotal} | พิมพ์: ${now}
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
            <th style="background:#1B6B3A;color:white;padding:7px 8px;">ตึก</th>
          </tr>
        </thead>
        <tbody>${rows}</tbody>
      </table>
    </div>`;
}

function doPrint(){
  const content=document.getElementById('printModalBody').innerHTML;
  const win=window.open('','_blank','width=960,height=720');
  win.document.write(`<!DOCTYPE html>
<html lang="th"><head><meta charset="UTF-8">
<title>รอตึกส่งออก — รพ.โพนทอง</title>
<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;600;700&display=swap" rel="stylesheet">
<style>
  *{font-family:'Sarabun',sans-serif;box-sizing:border-box;}
  body{margin:1.2cm;color:#1A2E22;font-size:10pt;}
  table{width:100%;border-collapse:collapse;}
  th{background:#1B6B3A !important;color:white !important;padding:5px 7px;-webkit-print-color-adjust:exact;print-color-adjust:exact;}
  td{padding:4px 7px;border-bottom:1px solid #c9e8d4;vertical-align:middle;font-size:9pt;}
  tr:nth-child(even) td{background:#eaf7ef;-webkit-print-color-adjust:exact;print-color-adjust:exact;}
  @page{margin:1.2cm;size:A4 landscape;}
</style>
</head><body>${content}</body></html>`);
  win.document.close();
  win.onload=()=>{win.focus();win.print();};
}

document.getElementById('printModal').addEventListener('click',function(e){if(e.target===this)closePrintPreview();});
</script>

<?php include '_footer.php'; ?>
