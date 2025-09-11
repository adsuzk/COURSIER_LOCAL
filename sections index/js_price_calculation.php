<?php
// Calcul automatique des prix via Google Distance Matrix API
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
        const PRICING = {
            normale: { base: 300, perKm: 300, color: '#4CAF50', name: 'Normal' },
            urgente: { base: 1000, perKm: 500, color: '#FF9800', name: 'Urgent' },
            express: { base: 1500, perKm: 700, color: '#F44336', name: 'Express' }
        };
        
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
                    console.error('❌ DistanceMatrixService status:', status);
                    // Afficher le message d'erreur et conserver la section visible
                    section.style.display = 'block';
                    section.classList.add('price-error');
                    section.innerHTML = `<div class="error-message">Erreur DistanceMatrix: ${status}</div>`;
                    return;
                }
                const el = response.rows[0].elements[0];
                console.log('📍 Element de réponse:', el);
                if (el.status !== 'OK') {
                    console.error('❌ DistanceMatrix element status:', el.status);
                    // Si pas de résultats, afficher uniquement estimation minimale
                    if (el.status === 'ZERO_RESULTS') {
                        // Priorité choisie
                        let pr = 'normale';
                        prios.forEach(r => { if (r.checked) pr = r.value; });
                        const cfg = PRICING[pr];
                        const fallbackCost = cfg.base;
                        // Mettre à jour UI avec temps et prix estimatifs
                        const distElem = document.getElementById('distance-info');
                        if (distElem) distElem.innerHTML = `📏 -`;
                        const timeElem = document.getElementById('time-info');
                        if (timeElem) timeElem.innerHTML = `⏱️ -`;
                        const breakdownElem = document.getElementById('price-breakdown');
                        if (breakdownElem) breakdownElem.innerHTML = '';
                        const totalElem = document.getElementById('total-price');
                        if (totalElem) {
                            totalElem.innerHTML = `💰 ${fallbackCost} FCFA`;
                            totalElem.style.borderColor = cfg.color;
                        }
                        // Afficher section
                        section.style.display = 'block';
                        section.classList.add('price-visible');
                        return;
                    }
                    // Autres erreurs, affichage d'erreur
                    section.style.display = 'block';
                    section.classList.add('price-error');
                    section.innerHTML = `<div class="error-message">Erreur itinéraire: ${el.status}</div>`;
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
                const cost = cfg.base + Math.ceil(kmVal * cfg.perKm);
                console.log('💰 Prix calculé:', {priorite: pr, config: cfg, cout: cost});
                // Mise à jour UI - affichage détaillé
                // Update form price section if present
                const distElem = document.getElementById('distance-info');
                if (distElem) distElem.innerHTML = `📏 ${distText}`;
                const timeElem = document.getElementById('time-info');
                if (timeElem) timeElem.innerHTML = `⏱️ ${durText}`;
                const breakdownElem = document.getElementById('price-breakdown');
                if (breakdownElem) breakdownElem.innerHTML = `
                    <div class="price-line">
                        <span class="description">Base (${cfg.name})</span>
                        <span class="amount">${cfg.base} FCFA</span>
                    </div>
                    <div class="price-line">
                        <span class="description">${kmVal.toFixed(1)} km × ${cfg.perKm} FCFA/km</span>
                        <span class="amount">${Math.ceil(kmVal * cfg.perKm)} FCFA</span>
                    </div>
                    <div class="price-separator"></div>`;
                const totalElem = document.getElementById('total-price');
                if (totalElem) {
                    totalElem.innerHTML = `💰 ${cost} FCFA`;
                    totalElem.style.borderColor = cfg.color;
                }
                if (section) {
                    section.style.display = 'block';
                    section.classList.add('price-visible');
                }
                // Update map overlay if present and show it
                const rd = document.getElementById('routeDistance');
                const rt = document.getElementById('routeDuration');
                const rp = document.getElementById('routePrice');
                const routeInfo = document.getElementById('routeInfo');
                if (rd) rd.textContent = distText;
                if (rt) rt.textContent = durText;
                if (rp) rp.textContent = cost;
                if (routeInfo) routeInfo.style.display = 'block';
                console.log('✅ Prix mis à jour et section affichée:', {cost});
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
