<?php
// Calcul automatique des prix via Google Distance Matrix API
require_once __DIR__ . '/../config.php';

$prix_km = 300;
$frais_base = 500;
try {
    $pdo = getDBConnection();
    $stmt = $pdo->query("SELECT parametre, valeur FROM parametres_tarification");
    $params = [];
    foreach ($stmt as $row) {
        $params[$row['parametre']] = (float) $row['valeur'];
    }
    if (isset($params['prix_kilometre'])) {
        $prix_km = (int) $params['prix_kilometre'];
    }
    if (isset($params['frais_base'])) {
        $frais_base = (int) $params['frais_base'];
    }
} catch (Throwable $e) {
    // Valeurs par défaut si la lecture échoue
}
?>
<script>
console.log('🔧 Module de calcul de prix chargé');

// Initialisation du module de calcul de prix (attend DOM si nécessaire)
(function() {
    function setupPriceCalc() {
        if (!window.google || !google.maps || !google.maps.DistanceMatrixService) {
            console.log('🔄 DistanceMatrixService non chargé, tentative dans 1000ms...');
            setTimeout(setupPriceCalc, 1000);
            return;
        }
        console.log('✅ Google DistanceMatrixService prêt!');
        
        const service = new google.maps.DistanceMatrixService();
        const dep = document.getElementById('departure');
        const dest = document.getElementById('destination');
        const prios = document.querySelectorAll('input[name="priority"]');
        const section = document.getElementById('price-calculation-section');
        
        // Vérifier que tous les éléments DOM existent
        if (!dep || !dest || !section) {
            console.error('❌ Éléments DOM manquants:', {dep: !!dep, dest: !!dest, section: !!section});
            return;
        }
        console.log('✅ Tous les éléments DOM trouvés');
    
        // Tarifaires
        const BASE_FARE = Math.max(0, <?php echo (int) $frais_base; ?>);
        const PRICE_PER_KM = Math.max(0, <?php echo (int) $prix_km; ?>);
        const PRIORITY_MULTIPLIERS = {
            normale: { base: 1.0, perKm: 1.0, color: '#4CAF50', name: 'Normal' },
            urgente: { base: 1.4, perKm: 1.3, color: '#FF9800', name: 'Urgent' },
            express: { base: 1.8, perKm: 1.6, color: '#F44336', name: 'Express' }
        };
        const PRICING = Object.fromEntries(
            Object.entries(PRIORITY_MULTIPLIERS).map(([key, cfg]) => [
                key,
                {
                    base: Math.max(BASE_FARE, Math.round(BASE_FARE * cfg.base)),
                    perKm: Math.max(PRICE_PER_KM, Math.round(PRICE_PER_KM * cfg.perKm)),
                    color: cfg.color,
                    name: cfg.name
                }
            ])
        );
        
        function calculate() {
            const o = dep.value.trim();
            const d = dest.value.trim();
            // Debug: log des valeurs saisies
            console.log('🧮 PriceCalc.calculate appelé avec:', {depart: o, destination: d});
            if (!o || !d) {
                console.log('⚠️ Un des champs est vide, masquage section');
                section.style.display = 'none';
                return;
            }
            console.log('🚀 Appel Google DistanceMatrix...');
            // Reset previous error/visibility state
            section.classList.remove('price-error');
            section.classList.remove('price-visible');
            service.getDistanceMatrix({
                origins: [o],
                destinations: [d],
                travelMode: google.maps.TravelMode.DRIVING,
                unitSystem: google.maps.UnitSystem.METRIC
            }, function(response, status) {
                console.log('📡 Réponse Google DistanceMatrix:', {status, response});
                if (status !== 'OK') {
                    console.warn('⚠️ DistanceMatrixService status non OK, status:', status);
                    // Fallback: afficher prix minimum et temps en placeholder
                    let pr = 'normale';
                    prios.forEach(r => { if (r.checked) pr = r.value; });
                    const cfg = PRICING[pr];
                    const fallbackCost = cfg.base;
                    // Masquer distance et détails
                    const distElem = document.getElementById('distance-info');
                    if (distElem) distElem.style.display = 'none';
                    const breakdownElem = document.getElementById('price-breakdown');
                    if (breakdownElem) breakdownElem.style.display = 'none';
                    // Afficher temps placeholder
                    const timeElem = document.getElementById('time-info');
                    if (timeElem) {
                        timeElem.style.display = 'block';
                        timeElem.innerHTML = `⏱️ -`;
                    }
                    // Afficher prix minimum
                    const totalElem = document.getElementById('total-price');
                    if (totalElem) {
                        totalElem.style.display = 'block';
                        totalElem.innerHTML = `💰 ${fallbackCost} FCFA`;
                        totalElem.style.borderColor = cfg.color;
                    }
                    // Afficher section
                    section.style.display = 'block';
                    section.classList.add('price-visible');
                    return;
                }
                const el = response.rows[0].elements[0];
                console.log('📍 Element de réponse:', el);
                if (el.status !== 'OK') {
                    console.warn('⚠️ DistanceMatrix élément status non OK, status:', el.status);
                    // Fallback: afficher prix minimum et temps en placeholder
                    let pr = 'normale';
                    prios.forEach(r => { if (r.checked) pr = r.value; });
                    const cfg = PRICING[pr];
                    const fallbackCost = cfg.base;
                    const timeElem = document.getElementById('time-info');
                    if (timeElem) timeElem.innerHTML = `⏱️ -`;
                    const totalElem = document.getElementById('total-price');
                    if (totalElem) totalElem.innerHTML = `<span class="total-amount">${fallbackCost} FCFA</span>`;
                    section.style.display = 'block';
                    section.classList.add('price-visible');
                    return;
                }
                // Récupération
                const distText = el.distance.text;
                const durText  = el.duration.text;
                const kmVal    = el.distance.value / 1000;
                console.log('📊 Données calculées:', {distText, durText, kmVal});
                // Priorité choisie
                let pr = 'normale';
                prios.forEach(r => { if (r.checked) pr = r.value; });
                const cfg = PRICING[pr];
                const cost = Math.max(cfg.base, cfg.base + Math.ceil(kmVal * cfg.perKm));
                console.log('💰 Prix calculé:', {priorite: pr, config: cfg, cout: cost});
                // Mise à jour UI : afficher uniquement durée et prix
                const timeElem2 = document.getElementById('time-info');
                if (timeElem2) timeElem2.innerHTML = `⏱️ ${durText}`;
                const totalElem2 = document.getElementById('total-price');
                if (totalElem2) totalElem2.innerHTML = `<span class="total-amount">${cost} FCFA</span>`;
                // Afficher section
                section.style.display = 'block';
                section.classList.add('price-visible');
                console.log('✅ Formulaire: prix mis à jour →', cost, 'FCFA');
                // Afficher section
                section.style.display = 'block';
                section.classList.add('price-visible');
                // Map overlay updates disabled by requirement
                // console.log('✅ Prix mis à jour et section affichée:', {cost});
            });
        }
        
        // Événements
        console.log('🎯 Attachement des événements...');
        dep.addEventListener('input', calculate);
        dep.addEventListener('blur', calculate);
        dest.addEventListener('input', calculate);
        dest.addEventListener('blur', calculate);
        prios.forEach(r => r.addEventListener('change', calculate));
        console.log('✅ Événements attachés');
        // Calcul initial si les deux champs sont déjà remplis
        console.log('🔄 Calcul initial...');
        calculate();
    }
    // Si document déjà prêt, exécuter immédiatement
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', setupPriceCalc);
    } else {
        setupPriceCalc();
    }
})();
</script>
