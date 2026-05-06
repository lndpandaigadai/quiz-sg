<?php

function getSheet($gid, $skipHeader = false) {
    $url = "https://docs.google.com/spreadsheets/d/11s_sX9mMdLJO8abSvAyITCtu5NZXhRtz03okAjLompQ/export?format=csv&gid=".$gid;

    $data = [];

    if (($h = fopen($url, "r")) !== FALSE) {
        if ($skipHeader) fgetcsv($h);
        $header = fgetcsv($h);

        while (($row = fgetcsv($h)) !== FALSE) {
            $data[] = array_combine(
                array_map('trim', $header),
                array_map('trim', $row)
            );
        }
        fclose($h);
    }

    return $data;
}

// MASTER REGION 2
$masterCabang = getSheet(180146110);
$masterCabang = array_values(array_filter($masterCabang, fn($d)=>$d['Region']=='2'));
$listCabang = array_map(fn($d)=>strtolower($d['Cabang']), $masterCabang);

// DATA
$cabangAgg = getSheet(0,true);
$cabangCons = getSheet(413245452,true);
$penaksirAgg = getSheet(747676228,true);
$penaksirCons = getSheet(1515135316,true);
$inkCabang = getSheet(859373172,true);

// FILTER REGION
function filterRegion($data,$list){
    return array_values(array_filter($data, fn($d)=>isset($d['Cabang']) && in_array(strtolower($d['Cabang']),$list)));
}

$cabangAgg = filterRegion($cabangAgg,$listCabang);
$cabangCons = filterRegion($cabangCons,$listCabang);
$penaksirAgg = filterRegion($penaksirAgg,$listCabang);
$penaksirCons = filterRegion($penaksirCons,$listCabang);
$inkCabang = filterRegion($inkCabang,$listCabang);

?>

<!DOCTYPE html>
<html>
<head>
<title>Dashboard Region 2</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
*{box-sizing:border-box}
body{margin:0;font-family:Segoe UI;background:#f5f7fb;display:flex}

/* SIDEBAR */
.sidebar{
    width:250px;
    height:100vh;
    background:#111827;
    color:#fff;
    position:fixed;
    transition:transform .3s ease;
}
.sidebar.hide{transform:translateX(-100%)}

.sidebar h3{
    padding:20px;
    border-bottom:1px solid #374151;
}

.menu button{
    width:100%;
    background:none;
    border:none;
    color:#cbd5e1;
    padding:12px 20px;
    display:flex;
    gap:10px;
    cursor:pointer;
}
.menu button:hover,
.menu button.active{
    background:#4f46e5;
    color:#fff;
}

/* CONTENT */
.content{
    margin-left:250px;
    padding:20px;
    width:calc(100% - 250px);
    transition:all .3s ease;
}
.content.full{
    margin-left:0;
    width:100%;
}

/* TOPBAR */
.topbar{
    display:flex;
    justify-content:space-between;
    margin-bottom:15px;
}
.toggle{
    background:#4f46e5;
    color:#fff;
    border:none;
    padding:8px 12px;
    border-radius:6px;
    cursor:pointer;
}

/* FILTER */
.filter{
    display:flex;
    gap:10px;
    flex-wrap:wrap;
}
.filter select{
    padding:8px;
    border-radius:6px;
    border:1px solid #ddd;
}

/* TABLE */
.table-wrap{
    width:100%;
    overflow-x:auto;
}

table{
    width:100%;
    border-collapse:collapse;
    margin-top:20px;
    background:#fff;
}
th{
    background:#4f46e5;
    color:#fff;
    padding:10px;
}
td{
    padding:10px;
    border-bottom:1px solid #eee;
}

/* BADGE */
.badge{
    padding:4px 10px;
    border-radius:20px;
    font-size:12px;
}
.urgent{background:#fee2e2;color:#b91c1c}
.monitor{background:#fef3c7;color:#92400e}
.netral{background:#dcfce7;color:#166534}

@media(max-width:768px){
    .content{margin-left:0;width:100%}
    .sidebar{position:absolute;z-index:10}
}
</style>

</head>
<body>

<!-- SIDEBAR -->
<div class="sidebar" id="sidebar">
    <h3><i class="bi bi-graph-up"></i> Dashboard</h3>

    <div class="menu">
        <button class="menu-btn active" data-mode="agg"><i class="bi bi-fire"></i> Cabang Aggressive</button>
        <button class="menu-btn" data-mode="cons"><i class="bi bi-shield-check"></i> Cabang Conservative</button>
        <button class="menu-btn" data-mode="pa"><i class="bi bi-person-gear"></i> Penaksir Aggressive</button>
        <button class="menu-btn" data-mode="pc"><i class="bi bi-person-check"></i> Penaksir Conservative</button>
        <button class="menu-btn" data-mode="ink"><i class="bi bi-exclamation-triangle"></i> Inkonsistensi</button>

        <!-- ✅ SUMMARY MENU -->
        <button class="menu-btn" data-mode="summary">
            <i class="bi bi-bar-chart"></i> Summary
        </button>
    </div>
</div>

<!-- CONTENT -->
<div class="content" id="content">

<div class="topbar">
    <button class="toggle" onclick="toggleSidebar()">
        <i class="bi bi-list"></i>
    </button>
</div>

<div class="filter">

<select id="klaster">
<option value="">Semua Klaster</option>
<?php
$klaster = array_unique(array_column($masterCabang,'Klaster'));
sort($klaster);
foreach($klaster as $k){
    echo "<option>$k</option>";
}
?>
</select>

<select id="cabang"></select>

<select id="status">
<option value="">Semua Status</option>
<option>URGENT</option>
<option>NEED TO MONITOR</option>
<option>NEUTRAL</option>
</select>

</div>

<div class="table-wrap">
<table>
<thead id="thead"></thead>
<tbody id="tbody"></tbody>
</table>
</div>

</div>

<script>

const master = <?php echo json_encode($masterCabang); ?>;

const data = {
    agg: <?php echo json_encode($cabangAgg); ?>,
    cons: <?php echo json_encode($cabangCons); ?>,
    pa: <?php echo json_encode($penaksirAgg); ?>,
    pc: <?php echo json_encode($penaksirCons); ?>,
    ink: <?php echo json_encode($inkCabang); ?>,
    summary: []
};

let currentMode = "agg";

function toggleSidebar(){
    document.getElementById("sidebar").classList.toggle("hide");
    document.getElementById("content").classList.toggle("full");
}

/* MENU SWITCH */
document.querySelectorAll(".menu-btn").forEach(btn=>{
    btn.onclick = ()=>{
        document.querySelectorAll(".menu-btn").forEach(b=>b.classList.remove("active"));
        btn.classList.add("active");
        currentMode = btn.dataset.mode;
        render();
    };
});

const klasterEl = document.getElementById("klaster");
const cabangEl = document.getElementById("cabang");
const statusEl = document.getElementById("status");

function updateCabang(){
    let filtered = master;

    if (klasterEl.value){
        filtered = master.filter(d=>d.Klaster===klasterEl.value);
    }

    cabangEl.innerHTML = `<option value="">Semua Cabang</option>`;
    [...new Set(filtered.map(d=>d.Cabang))].forEach(c=>{
        cabangEl.innerHTML += `<option>${c}</option>`;
    });
}

function renderSummary(){

    const all = [
        ...data.agg,
        ...data.cons,
        ...data.pa,
        ...data.pc,
        ...data.ink
    ];

    // helper ambil persen dari field (fallback aman)
    function getPercent(val){
        if (!val) return 0;
        let num = parseFloat(String(val).replace("%",""));
        return isNaN(num) ? 0 : num;
    }

    // klasifikasi status berdasarkan RULE KAMU
    let urgent = 0;
    let monitor = 0;
    let neutral = 0;

    all.forEach(r=>{

        let mis = 0;
        let imei = 0;

        // cari field fleksibel
        Object.keys(r).forEach(k=>{
            if (k.toLowerCase().includes("mis")){
                mis = getPercent(r[k]);
            }
            if (k.toLowerCase().includes("imei")){
                imei = getPercent(r[k]);
            }
        });

        if (mis > 5 || imei > 3){
            urgent++;
        }
        else if ((mis >= 2.1 && mis <= 5) || (imei >= 1 && imei <= 3)){
            monitor++;
        }
        else{
            neutral++;
        }
    });

    const total = urgent + monitor + neutral;

    const tbody = document.getElementById("tbody");
    const thead = document.getElementById("thead");

    thead.innerHTML = "";
    tbody.innerHTML = `
        <tr>
        <td colspan="10">

        <!-- KPI CARDS -->
        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:15px;margin-bottom:20px">

            <div style="background:#fff;padding:20px;border-radius:12px">
                <h3>🚨 URGENT</h3>
                <h1 style="color:#b91c1c">${urgent}</h1>
                <small>${((urgent/total)*100||0).toFixed(1)}%</small>
                <p style="font-size:12px;color:#666">
                    Misgrading > 5% OR IMEI > 3%
                </p>
            </div>

            <div style="background:#fff;padding:20px;border-radius:12px">
                <h3>⚠️ NEED TO MONITOR</h3>
                <h1 style="color:#92400e">${monitor}</h1>
                <small>${((monitor/total)*100||0).toFixed(1)}%</small>
                <p style="font-size:12px;color:#666">
                    Misgrading 2.1–5% OR IMEI 1–3%
                </p>
            </div>

            <div style="background:#fff;padding:20px;border-radius:12px">
                <h3>✅ NORMAL</h3>
                <h1 style="color:#166534">${neutral}</h1>
                <small>${((neutral/total)*100||0).toFixed(1)}%</small>
            </div>

        </div>

        <!-- CHART -->
        <div style="background:#fff;padding:20px;border-radius:12px">
            <canvas id="summaryChart"></canvas>
        </div>

        </td>
        </tr>
    `;

    // CHART JS
    new Chart(document.getElementById("summaryChart"), {
        type: 'doughnut',
        data: {
            labels: ['Urgent', 'Need Monitor', 'Normal'],
            datasets: [{
                data: [urgent, monitor, neutral],
                backgroundColor: [
                    '#ef4444',
                    '#f59e0b',
                    '#22c55e'
                ]
            }]
        },
        options: {
            plugins: {
                legend: {
                    position: 'bottom'
                }
            }
        }
    });
}
/* MAIN RENDER */
function render(){

    if (currentMode === "summary"){
        renderSummary();
        return;
    }

    let rows = data[currentMode];
    let selectedStatus = statusEl.value;

    let cabangAllowed = master;

    if (klasterEl.value){
        cabangAllowed = master.filter(d=>d.Klaster===klasterEl.value);
    }

    let listCabang = cabangAllowed.map(d=>d.Cabang.toLowerCase());

    rows = rows.filter(r=>r.Cabang && listCabang.includes(r.Cabang.toLowerCase()));

    if (cabangEl.value){
        rows = rows.filter(r=>r.Cabang===cabangEl.value);
    }

    if (selectedStatus){
        rows = rows.filter(r=>{
            let key = Object.keys(r).find(k=>k.toLowerCase().includes("status"));
            return key && r[key].toUpperCase().includes(selectedStatus);
        });
    }

    const thead = document.getElementById("thead");
    const tbody = document.getElementById("tbody");

    if (!rows.length){
        tbody.innerHTML = "<tr><td>No Data</td></tr>";
        thead.innerHTML = "";
        return;
    }

    let headers = Object.keys(rows[0]);

    thead.innerHTML = "<tr>"+headers.map(h=>`<th>${h}</th>`).join("")+"</tr>";

    tbody.innerHTML = rows.map(r=>{
        return "<tr>"+headers.map(h=>{
            let val = r[h];

            if (val?.includes?.("URGENT")) val = `<span class="badge urgent">${val}</span>`;
            else if (val?.includes?.("MONITOR")) val = `<span class="badge monitor">${val}</span>`;
            else if (val?.includes?.("NEUTRAL")) val = `<span class="badge netral">${val}</span>`;

            return `<td>${val ?? ""}</td>`;
        }).join("")+"</tr>";
    }).join("");

}

klasterEl.onchange = ()=>{updateCabang(); render();};
cabangEl.onchange = render;
statusEl.onchange = render;

updateCabang();
render();

</script>

</body>
</html>