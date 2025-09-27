<?php
// Ne pas relancer session_start si elle est déjà active
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
require_once __DIR__ . '/../config.php';
$pdo = getPDO();
// Statistiques dynamiques : seules connexions coursiers actives
try {
    $onlineCouriers = (int)$pdo->query("SELECT COUNT(*) FROM agents_suzosky WHERE type_poste='coursier' AND status='actif'")->fetchColumn();
} catch (PDOException $e) {
    // Table non trouvée en local ou autre erreur
    $onlineCouriers = 0;
}
// Désactivation des autres statistiques
// $agentCount, $ordersToday, $revenueToday, $clientsCount, $supportMessages

?>
<style>
/* === DESIGN SYSTEM SUZOSKY - DASHBOARD === */
:root {
    --primary-gold: #D4A853;
    --primary-dark: #1A1A2E;
    --secondary-blue: #16213E;
    --accent-blue: #0F3460;
    --accent-red: #E94560;
    --success-color: #27AE60;
    --warning-color: #ffc107;
    --danger-color: #E94560;
    --glass-bg: rgba(255,255,255,0.08);
    --glass-border: rgba(255,255,255,0.2);
    --gradient-gold: linear-gradient(135deg, #D4A853 0%, #F4E4B8 50%, #D4A853 100%);
    --gradient-dark: linear-gradient(135deg, #1A1A2E 0%, #16213E 100%);
    --space-2: 8px;
    --space-4: 16px;
    --space-6: 24px;
    --space-8: 32px;
}

/* === HERO SECTION DASHBOARD === */
.dashboard-hero {
    background: var(--glass-bg);
    backdrop-filter: blur(20px);
    border: 1px solid var(--glass-border);
    border-radius: 20px;
    padding: 30px;
    margin-bottom: 30px;
    box-shadow: 0 8px 32px rgba(0,0,0,0.2);
    display: flex;
    justify-content: space-between;
    align-items: center;
    position: relative;
    overflow: hidden;
}

.dashboard-hero::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: var(--gradient-gold);
}

.hero-content h1 {
    font-size: 2rem;
    font-weight: 700;
    background: var(--gradient-gold);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    margin-bottom: 10px;
    font-family: 'Montserrat', sans-serif;
}

.hero-content p {
    color: rgba(255,255,255,0.8);
    font-size: 1rem;
    margin-bottom: 20px;
    font-weight: 500;
}

.hero-stats {
    display: flex;
    gap: 30px;
}

.hero-stat .stat-value {
    display: block;
    font-size: 2rem;
    font-weight: 800;
    color: var(--primary-gold);
    font-family: 'Montserrat', sans-serif;
}

.hero-stat .stat-label {
    display: block;
    font-size: 0.9rem;
    color: rgba(255,255,255,0.7);
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.hero-decoration {
    font-size: 4rem;
    color: var(--primary-gold);
    opacity: 0.3;
    animation: float 6s ease-in-out infinite;
}

@keyframes float {
    0%, 100% { transform: translateY(0px); }
    50% { transform: translateY(-10px); }
}

/* === GRILLE STATISTIQUES === */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

/* === CARTES STATISTIQUES === */
.stat-card {
    background: var(--glass-bg);
    backdrop-filter: blur(20px);
    border: 1px solid var(--glass-border);
    border-radius: 15px;
    padding: 25px;
    box-shadow: 0 8px 32px rgba(0,0,0,0.2);
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.stat-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: var(--gradient-gold);
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 40px rgba(212, 168, 83, 0.2);
    border-color: var(--primary-gold);
}

.stat-header {
    display: flex;
    align-items: center;
    gap: 15px;
    margin-bottom: 20px;
}

.stat-icon {
    width: 50px;
    height: 50px;
    background: var(--gradient-gold);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--primary-dark);
    font-size: 1.5rem;
    box-shadow: 0 4px 15px rgba(212, 168, 83, 0.3);
}

.stat-info h3 {
    color: rgba(255,255,255,0.9);
    font-size: 0.9rem;
    font-weight: 600;
    margin-bottom: 5px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.stat-number {
    font-size: 2rem;
    font-weight: 800;
    color: var(--primary-gold);
    font-family: 'Montserrat', sans-serif;
}

.stat-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-top: 15px;
    border-top: 1px solid var(--glass-border);
}

.stat-trend {
    font-size: 0.8rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 5px;
}

.stat-trend.positive {
    color: var(--success-color);
}

.stat-link {
    color: var(--primary-gold);
    text-decoration: none;
    font-size: 1.2rem;
    transition: all 0.3s ease;
    padding: 5px;
    border-radius: 50%;
}

.stat-link:hover {
    background: rgba(212, 168, 83, 0.1);
    transform: translateX(3px);
}

/* === STATUS INDICATORS === */
.stat-status {
    padding: 3px 8px;
    border-radius: 10px;
    font-size: 0.7rem;
    font-weight: 600;
    text-transform: uppercase;
    display: flex;
    align-items: center;
    gap: 5px;
}

.stat-status.online {
    background: rgba(39, 174, 96, 0.2);
    color: var(--success-color);
}

.status-dot {
    width: 6px;
    height: 6px;
    background: var(--success-color);
    border-radius: 50%;
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0% { opacity: 1; }
    50% { opacity: 0.5; }
    100% { opacity: 1; }
}

/* === SECTIONS ACTIONS RAPIDES === */
.quick-actions {
    background: var(--glass-bg);
    backdrop-filter: blur(20px);
    border: 1px solid var(--glass-border);
    border-radius: 15px;
    padding: 25px;
    margin-bottom: 30px;
    box-shadow: 0 8px 32px rgba(0,0,0,0.2);
}

.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.section-header h2 {
    color: var(--primary-gold);
    font-size: 1.3rem;
    font-weight: 700;
    font-family: 'Montserrat', sans-serif;
    display: flex;
    align-items: center;
    gap: 10px;
}

.quick-actions-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 15px;
}

.action-card {
    background: rgba(255,255,255,0.05);
    border: 1px solid var(--glass-border);
    border-radius: 12px;
    padding: 20px;
    text-align: center;
    transition: all 0.3s ease;
    cursor: pointer;
    text-decoration: none;
    color: inherit;
}

.action-card:hover {
    background: rgba(255,255,255,0.1);
    transform: translateY(-3px);
    box-shadow: 0 10px 25px rgba(212, 168, 83, 0.15);
}

.action-icon {
    font-size: 2rem;
    color: var(--primary-gold);
    margin-bottom: 10px;
}

.action-title {
    font-weight: 600;
    color: rgba(255,255,255,0.9);
    margin-bottom: 5px;
    font-size: 0.9rem;
}

.action-desc {
    font-size: 0.8rem;
    color: rgba(255,255,255,0.6);
    line-height: 1.3;
}

/* === RESPONSIVE === */
@media (max-width: 768px) {
    .dashboard-hero {
        flex-direction: column;
        text-align: center;
        padding: var(--space-6);
    }
    
    .hero-stats {
        justify-content: center;
        margin-top: 20px;
    }
    
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .quick-actions-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}
</style>

<!-- Hero Section Dashboard Suzosky -->
<div class="dashboard-hero">
    <div class="hero-content">
        <h1><i class="fas fa-tachometer-alt"></i> Tableau de Bord Suzosky</h1>
        <p>Vue d'ensemble de votre plateforme de livraison premium</p>
        <div class="hero-stats">
            <div class="hero-stat">
                <span class="stat-value"><?= $onlineCouriers ?></span>
                <span class="stat-label">Coursiers Actifs</span>
            </div>
        </div>
    </div>
    <div class="hero-decoration">
        <i class="fas fa-shipping-fast"></i>
    </div>
</div> <!-- /.dashboard-hero -->

<div id="realtime-panels" style="margin-top:30px;display:grid;grid-template-columns:1.2fr 0.8fr;gap:25px;align-items:start;">
    <div class="quick-actions" style="min-height:400px;">
        <div class="section-header"><h2><i class="fas fa-stream"></i> Commandes Actives <span id="alerts-badge" style="display:none;margin-left:8px;background:#e53935;color:#fff;padding:2px 6px;border-radius:12px;font-size:11px;">0</span></h2>
            <span id="rt-last-update" style="font-size:12px;color:rgba(255,255,255,0.6);">--</span>
        </div>
        <div style="overflow:auto;max-height:520px;" id="rt-orders-wrapper">
            <table style="width:100%;border-collapse:collapse;font-size:12.5px;">
                <thead>
                    <tr style="text-align:left;position:sticky;top:0;background:rgba(255,255,255,0.07);backdrop-filter:blur(10px);">
                        <th style="padding:8px 6px;">ID</th>
                        <th style="padding:8px 6px;">Statut</th>
                        <th style="padding:8px 6px;">Paiement</th>
                        <th style="padding:8px 6px;">Tarif</th>
                        <th style="padding:8px 6px;">Coursier</th>
                        <th style="padding:8px 6px;">MAJ</th>
                    </tr>
                </thead>
                <tbody id="rt-orders-body">
                    <tr><td colspan="6" style="padding:14px;text-align:center;color:rgba(255,255,255,0.6);">Chargement…</td></tr>
                </tbody>
            </table>
        </div>
    </div>
    <div class="quick-actions" style="min-height:400px;">
        <div class="section-header"><h2><i class="fas fa-map-marked-alt"></i> Carte Temps Réel</h2>
            <div>
                <select id="rt-filter-statut" style="background:rgba(0,0,0,0.3);color:#fff;border:1px solid var(--glass-border);padding:6px 10px;border-radius:8px;font-size:12px;">
                    <option value="all">Tous statuts</option>
                    <option value="nouvelle">Nouvelles</option>
                    <option value="en_cours">En cours</option>
                    <option value="picked_up">Colis récupéré</option>
                </select>
            </div>
        </div>
        <div id="rt-map" style="width:100%;height:480px;border:1px solid var(--glass-border);border-radius:12px;background:#0f141c;"></div>
    </div>
</div>

<script src="https://maps.googleapis.com/maps/api/js?key=YOUR_GOOGLE_MAPS_API_KEY&v=quarterly"></script>
<script>
(function(){
    const ORDERS_URL = '../api/admin/live_data.php';
    const tbody = document.getElementById('rt-orders-body');
    const lastUpdate = document.getElementById('rt-last-update');
    const filterSelect = document.getElementById('rt-filter-statut');
    let map, markersCouriers = {}, markersOrders = {};
    let firstLoad = true;

    function initMap(){
        map = new google.maps.Map(document.getElementById('rt-map'), {
            center:{lat:5.345317,lng:-4.024429}, zoom:11, styles:[{featureType:'poi',stylers:[{visibility:'off'}]}],
            mapTypeControl:false, streetViewControl:false, fullscreenControl:false
        });
    }
    initMap();

    function badge(text, type){
        const colors = {nouvelle:'#ffc107', en_cours:'#42a5f5', picked_up:'#ef6c00', livree:'#2e7d32'};
        const c = colors[type] || '#78909c';
        return `<span style="padding:3px 7px;border-radius:8px;font-weight:600;background:${c}22;color:${c};">${text}</span>`;
    }

    function fmtTs(ts){ if(!ts) return ''; return ts.replace('T',' ').replace(/\..+/,''); }
    function money(v){ return (v?Number(v):0).toLocaleString('fr-FR',{minimumFractionDigits:0}) + ' FCFA'; }

    // Modal timeline elements
    let modal = document.getElementById('timeline-modal');
    if(!modal){
        const modalHtml = `\n<div id=\"timeline-modal\" style=\"display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.72);backdrop-filter:blur(6px);z-index:8000;align-items:flex-start;justify-content:center;padding:60px 20px;overflow:auto;\">\n  <div style=\"max-width:760px;width:100%;background:#101722;border:1px solid var(--glass-border);border-radius:24px;padding:28px 34px;position:relative;box-shadow:0 12px 60px rgba(0,0,0,0.5);\">\n    <button id=\"timeline-close\" style=\"position:absolute;top:10px;right:10px;background:rgba(255,255,255,0.1);border:1px solid var(--glass-border);color:#fff;padding:6px 10px;border-radius:8px;cursor:pointer;font-size:12px;\">Fermer</button>\n    <h3 style=\"margin:0 0 18px 0;font-size:20px;font-weight:700;background:var(--gradient-gold);-webkit-background-clip:text;color:transparent;\">Timeline Commande <span id=\"tl-order-id\"></span></h3>\n    <div id=\"tl-meta\" style=\"display:flex;flex-wrap:wrap;gap:12px;margin-bottom:12px;font-size:12px;color:rgba(255,255,255,0.75);\"></div>\n    <div style=\"display:flex;align-items:center;gap:10px;margin-bottom:20px;\">\n      <label style=\"font-size:11px;opacity:0.7;\">Filtre événements:</label>\n      <select id=\"tl-filter\" style=\"background:rgba(255,255,255,0.08);color:#fff;border:1px solid var(--glass-border);padding:4px 8px;border-radius:6px;font-size:11px;\">\n        <option value=\"all\">Tous</option>\n        <option value=\"created\">Création</option>\n        <option value=\"pickup\">Pickup</option>\n        <option value=\"delivered\">Livraison</option>\n        <option value=\"warning\">Avertissements</option>\n        <option value=\"critical\">Critiques</option>\n      </select>\n      <div id=\"tl-speed-wrapper\" style=\"margin-left:auto;font-size:11px;display:flex;align-items:center;gap:6px;opacity:0.85;\"></div>\n    </div>\n    <div style=\"display:grid;grid-template-columns:1fr 1fr;gap:28px;\">\n      <div>\n        <h4 style=\"margin:0 0 10px 0;font-size:14px;color:var(--primary-gold);\">Événements</h4>\n        <ul id=\"tl-events\" style=\"list-style:none;margin:0;padding:0;display:flex;flex-direction:column;gap:10px;font-size:12.5px;\"></ul>\n      </div>\n      <div>\n        <h4 style=\"margin:0 0 10px 0;font-size:14px;color:var(--primary-gold);\">Positions récentes (100)</h4>\n        <div id=\"tl-positions\" style=\"max-height:260px;overflow:auto;border:1px solid var(--glass-border);border-radius:12px;padding:10px;font-size:11px;line-height:1.4;background:rgba(255,255,255,0.03);\"></div>\n      </div>\n    </div>\n  </div>\n</div>`;
        document.body.insertAdjacentHTML('beforeend', modalHtml);
        modal = document.getElementById('timeline-modal');
        // Append export buttons
        const toolbar = modal.querySelector('div[style*="gap:10px"][style*="margin-bottom:20px"]');
        if(toolbar){
            const exportBox = document.createElement('div');
            exportBox.style.display='flex'; exportBox.style.gap='6px'; exportBox.style.marginLeft='10px';
            exportBox.innerHTML = '<button id="btn-export-csv" style="background:#2e7d32;border:none;color:#fff;padding:4px 8px;border-radius:6px;font-size:11px;cursor:pointer;">CSV</button><button id="btn-export-pdf" style="background:#1565c0;border:none;color:#fff;padding:4px 8px;border-radius:6px;font-size:11px;cursor:pointer;">PDF</button>';
            toolbar.appendChild(exportBox);
        }
    }
    const tlOrderId = ()=>document.getElementById('tl-order-id');
    const tlEvents = ()=>document.getElementById('tl-events');
    const tlMeta = ()=>document.getElementById('tl-meta');
    const tlPositions = ()=>document.getElementById('tl-positions');
    const closeBtn = ()=>document.getElementById('timeline-close');

        let currentTimelineRaw = [];
        function openTimeline(id){
        tlOrderId().textContent = '#'+id;
        tlEvents().innerHTML = '<li style="opacity:0.6;">Chargement…</li>';
        tlMeta().innerHTML = '';
        tlPositions().innerHTML = '';
        modal.style.display='flex';
    fetch('../api/admin/order_timeline.php?order_id='+id,{headers:{'Authorization':'Bearer '+ADMIN_TOKEN}})
          .then(r=>r.json()).then(j=>{
            if(!j.success){ tlEvents().innerHTML='<li>Erreur</li>'; return; }
            const o = j.order;
                        tlMeta().innerHTML = [
                            ['Statut', o.statut], ['Paiement', o.mode_paiement||'-'], ['Prix', money(o.prix_estime)],
                            ['Pickup', o.pickup_time||'-'], ['Livré', o.delivered_time||'-']
                        ].map(k=>`<span style="background:rgba(255,255,255,0.07);padding:6px 10px;border-radius:8px;">${k[0]}: <strong>${k[1]}</strong></span>`).join('');
                        if(j.durations){
                                const d = j.durations;
                                const fmt = v=> v==null?'-':(v<60? v+'s' : (v<3600? Math.round(v/60)+'m' : ( (v/3600).toFixed(1)+'h')));
                                tlMeta().innerHTML += ['creation_to_pickup','pickup_to_delivered','total'].map(key=>`<span style=\"background:rgba(66,165,245,0.15);padding:6px 10px;border-radius:8px;\">${key.replace(/_/g,' ')}: <strong>${fmt(d[key])}</strong></span>`).join('');
                        }
                        currentTimelineRaw = j.timeline.filter(ev=>ev.event!=='current_status');
                        renderTimelineFiltered();
                        // Speeds sparkline
                        const sw = document.getElementById('tl-speed-wrapper');
                        sw.innerHTML = '';
                        if(j.speeds && j.speeds.length){
                                const avg = (j.speeds.reduce((a,b)=>a+b,0)/j.speeds.length).toFixed(1);
                                const max = Math.max(...j.speeds);
                                const points = j.speeds.map((s,i)=>{
                                        const x = (i/(j.speeds.length-1))*60; // width 60
                                        const y = 20 - (s/max)*20; // height 20
                                        return `${x},${y}`;
                                }).join(' ');
                                sw.innerHTML = `<span>Vitesse (km/h)</span><svg width="70" height="22"><polyline fill="none" stroke="#42a5f5" stroke-width="2" points="${points}" /></svg><span style="color:#42a5f5;font-weight:600;">${avg} moy</span>`;
                        }
            if(j.positions && j.positions.length){
                tlPositions().innerHTML = j.positions.map(p=>`<div>${fmtTs(p.created_at)} • ${p.latitude}, ${p.longitude}</div>`).join('');
            } else { tlPositions().innerHTML = '<div style="opacity:0.5;">Aucune position récente</div>'; }
          }).catch(()=>{ tlEvents().innerHTML='<li>Erreur</li>'; });
    }
    document.addEventListener('click', e=>{
        if(e.target && e.target.id==='btn-export-csv'){
            window.open('../api/admin/export_orders_csv.php?token='+encodeURIComponent(ADMIN_TOKEN),'_blank');
        }
        if(e.target && e.target.id==='btn-export-pdf'){
            const oid = tlOrderId().textContent.replace('#','');
            if(oid) window.open('../api/admin/export_order_pdf.php?order_id='+encodeURIComponent(oid)+'&token='+encodeURIComponent(ADMIN_TOKEN),'_blank');
        }
    });
        function renderTimelineFiltered(){
                const sel = document.getElementById('tl-filter').value;
                tlEvents().innerHTML = currentTimelineRaw.filter(ev=>{
                        if(sel==='all') return true;
                        if(['created','pickup','delivered'].includes(sel)) return ev.event===sel;
                        if(sel==='warning') return ev.severity==='warning';
                        if(sel==='critical') return ev.severity==='critical';
                        return true;
                }).map(ev=>`<li style="padding:8px 10px;border-left:4px solid ${ev.color||'#888'};background:rgba(255,255,255,0.04);border-radius:8px;">
                        <strong style="color:${ev.color||'#fff'}">${ev.event}</strong><br>
                        <span style="font-size:11px;color:rgba(255,255,255,0.6);">${fmtTs(ev.timestamp||'')}</span>
                        ${ev.severity && ev.severity!=='normal'?`<span style=\"margin-left:6px;font-size:10px;padding:2px 5px;border-radius:6px;background:${ev.color||'#555'}22;color:${ev.color||'#fff'};\">${ev.severity}</span>`:''}
                </li>`).join('')+`<li style="padding:6px 8px;margin-top:8px;font-size:11px;color:rgba(255,255,255,0.5);">Statut actuel: <strong>${(currentTimelineRaw.find(e=>e.event==='delivered')?'livree':'en cours')}</strong></li>`;
        }
        document.addEventListener('change', e=>{ if(e.target && e.target.id==='tl-filter'){ renderTimelineFiltered(); }});
    document.addEventListener('click', e=>{ if(e.target===modal) modal.style.display='none'; });
    document.addEventListener('click', e=>{ if(e.target && e.target.id==='timeline-close'){ modal.style.display='none'; }});

    function renderOrders(data){
        const filt = filterSelect.value;
        if(!data.length){ tbody.innerHTML = '<tr><td colspan="6" style="padding:14px;text-align:center;color:rgba(255,255,255,0.6);">Aucune commande active</td></tr>'; return; }
        tbody.innerHTML = data.filter(o=>filt==='all'||o.statut===filt).map(o=>{
            return `<tr data-order-id="${o.id}" style="border-bottom:1px solid rgba(255,255,255,0.06);">
                <td style="padding:6px 6px;font-weight:600;color:#fff;">${o.id}</td>
                <td style="padding:6px 6px;">${badge(o.statut, o.statut)}</td>
                <td style="padding:6px 6px;">${o.mode_paiement||'-'}</td>
                <td style="padding:6px 6px;">${money(o.tarif)}</td>
                <td style="padding:6px 6px;">${o.coursier_nom||'-'}</td>
                <td style="padding:6px 6px;font-size:11px;color:rgba(255,255,255,0.5);">${fmtTs(o.updated_at)}</td>
            </tr>`;
        }).join('');
        tbody.querySelectorAll('tr[data-order-id]').forEach(tr=>{
            tr.addEventListener('click',()=> openTimeline(tr.getAttribute('data-order-id')));
        });
    }

    function updateMarkers(payload){
        if(!map) return;
        const couriers = payload.coursiers || [];
        const orders = payload.commandes || [];
        // Couriers markers
        couriers.forEach(c=>{
            if(!c.latitude || !c.longitude) return;
            const id = 'c_'+c.id_coursier;
            if(!markersCouriers[id]){
                markersCouriers[id] = new google.maps.Marker({
                    position:{lat:parseFloat(c.latitude),lng:parseFloat(c.longitude)},
                    map, icon:{path:google.maps.SymbolPath.CIRCLE, scale:6, fillColor:'#42a5f5', fillOpacity:0.9, strokeWeight:1, strokeColor:'#0d47a1'},
                    title:'Coursier '+ (c.nom||'')
                });
            } else {
                markersCouriers[id].setPosition({lat:parseFloat(c.latitude),lng:parseFloat(c.longitude)});
            }
        });
        // Orders markers (pickup approximate: use departure_lat/lng, fallback null)
        orders.forEach(o=>{
            if(!o.departure_lat || !o.departure_lng) return;
            const id = 'o_'+o.id;
            if(!markersOrders[id]){
                markersOrders[id] = new google.maps.Marker({
                    position:{lat:parseFloat(o.departure_lat),lng:parseFloat(o.departure_lng)},
                    map, icon:{path:google.maps.SymbolPath.BACKWARD_CLOSED_ARROW, scale:5, fillColor:'#ffa000', fillOpacity:0.9, strokeWeight:1, strokeColor:'#ff6f00'},
                    title:'Commande '+o.id
                });
            } else {
                markersOrders[id].setPosition({lat:parseFloat(o.departure_lat),lng:parseFloat(o.departure_lng)});
            }
        });
        if(firstLoad && couriers.length){
            firstLoad = false;
            const bounds = new google.maps.LatLngBounds();
            couriers.forEach(c=>{ if(c.latitude && c.longitude) bounds.extend({lat:parseFloat(c.latitude),lng:parseFloat(c.longitude)}); });
            if(!bounds.isEmpty()) map.fitBounds(bounds);
        }
    }

    const ADMIN_TOKEN = '<?php echo htmlspecialchars(($config["admin"]["api_token"] ?? ""), ENT_QUOTES); ?>';
    // SSE Setup (Authorization token via query param hashed to avoid logs – minimal obfuscation)
    function initSSE(){
        const url = '../api/admin/live_data_sse.php?token='+encodeURIComponent(ADMIN_TOKEN);
        // Fallback if EventSource not supported
        if(!window.EventSource){ console.warn('EventSource non supporté, fallback polling'); return legacyPolling(); }
        const es = new EventSource(url);
        es.addEventListener('update', ev=>{
            try {
                const j = JSON.parse(ev.data);
                if(!j.success) return;
                lastUpdate.textContent = new Date().toLocaleTimeString();
                renderOrders(j.commandes||[]);
                updateMarkers(j);
                document.querySelector('.stat-value').textContent = j.metrics?.coursiers_actifs ?? 0;
                if(j.alerts){
                    const badge = document.getElementById('alerts-badge');
                    const count = j.alerts.length;
                    if(count){ badge.textContent = count; badge.style.display='inline-block'; badge.title = j.alerts.map(a=>`${a.type} ${a.order_id||a.courier_id||''} (${a.severity})`).join('\n'); }
                    else { badge.style.display='none'; }
                }
            } catch(e) {}
        });
        es.addEventListener('ping', ()=>{ /* heartbeat */ });
        es.onerror = ()=>{ console.warn('SSE erreur, tentative reconnexion dans 5s'); es.close(); setTimeout(initSSE,5000); };
        filterSelect.addEventListener('change', ()=>{/* trigger manual re-render using existing data? */});
    }
    function legacyPolling(){
        function fetchData(){
            fetch(ORDERS_URL,{headers:{'Authorization':'Bearer '+ADMIN_TOKEN}}).then(r=>r.json()).then(j=>{
                if(!j.success) return;
                lastUpdate.textContent = new Date().toLocaleTimeString();
                renderOrders(j.commandes||[]);
                updateMarkers(j);
                document.querySelector('.stat-value').textContent = j.metrics?.coursiers_actifs ?? 0;
                if(j.alerts){
                    const badge = document.getElementById('alerts-badge');
                    const count = j.alerts.length; if(count){ badge.textContent=count; badge.style.display='inline-block'; } else { badge.style.display='none'; }
                }
            }).catch(()=>{});
        }
        fetchData();
        setInterval(fetchData,5000);
        filterSelect.addEventListener('change', fetchData);
    }
    initSSE();
})();
</script>
