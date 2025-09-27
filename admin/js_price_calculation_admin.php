<?php
// admin/js_price_calculation_admin.php - Calcul détaillé pour l'administration
?>
<script>
console.log('🔧 Module de calcul de prix ADMIN chargé');

// Version détaillée pour l'administration
function calculatePriceAdmin(departure, destination, priority = 'normale') {
    return new Promise((resolve, reject) => {
        if (!window.google || !google.maps || !google.maps.DistanceMatrixService) {
            reject('DistanceMatrixService non disponible');
            return;
        }
        
        const service = new google.maps.DistanceMatrixService();
        const PRICING = {
            normale: { base: 300, perKm: 300, color: '#4CAF50', name: 'Normal' },
            urgente: { base: 1000, perKm: 500, color: '#FF9800', name: 'Urgent' },
            express: { base: 1500, perKm: 700, color: '#F44336', name: 'Express' }
        };
        
        service.getDistanceMatrix({
            origins: [departure],
            destinations: [destination],
            travelMode: google.maps.TravelMode.DRIVING,
            unitSystem: google.maps.UnitSystem.METRIC
        }, function(response, status) {
            if (status !== 'OK') {
                reject(`Erreur DistanceMatrix: ${status}`);
                return;
            }
            
            const el = response.rows[0].elements[0];
            if (el.status !== 'OK') {
                reject(`Erreur élément: ${el.status}`);
                return;
            }
            
            const distText = el.distance.text;
            const durText = el.duration.text;
            const kmVal = el.distance.value / 1000;
            const cfg = PRICING[priority];
            const baseCost = cfg.base;
            const kmCost = Math.ceil(kmVal * cfg.perKm);
            const totalCost = baseCost + kmCost;
            
            resolve({
                distance: distText,
                duration: durText,
                kilometers: kmVal,
                priority: priority,
                priorityName: cfg.name,
                baseCost: baseCost,
                kmCost: kmCost,
                totalCost: totalCost,
                color: cfg.color,
                breakdown: {
                    base: `Base (${cfg.name}): ${baseCost} FCFA`,
                    distance: `${kmVal.toFixed(1)} km × ${cfg.perKm} FCFA/km: ${kmCost} FCFA`,
                    total: `Total: ${totalCost} FCFA`
                }
            });
        });
    });
}

// Fonction pour générer l'HTML détaillé admin
function generateAdminPriceDisplay(priceData) {
    return `
        <div class="admin-price-details" style="
            background: rgba(212, 168, 83, 0.1);
            border: 1px solid rgba(212, 168, 83, 0.3);
            border-radius: 8px;
            padding: 12px;
            margin: 8px 0;
            font-size: 0.9rem;
        ">
            <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                <span style="color: #666;">📏 Distance:</span>
                <strong>${priceData.distance}</strong>
            </div>
            <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                <span style="color: #666;">⏱️ Durée:</span>
                <strong>${priceData.duration}</strong>
            </div>
            <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                <span style="color: #666;">Base (${priceData.priorityName}):</span>
                <span>${priceData.baseCost} FCFA</span>
            </div>
            <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                <span style="color: #666;">${priceData.kilometers.toFixed(1)} km × ${priceData.baseCost === 300 ? 300 : priceData.baseCost === 1000 ? 500 : 700} FCFA/km:</span>
                <span>${priceData.kmCost} FCFA</span>
            </div>
            <div style="
                display: flex; 
                justify-content: space-between; 
                border-top: 1px solid rgba(212, 168, 83, 0.3); 
                padding-top: 8px; 
                margin-top: 8px;
                font-weight: bold;
                color: ${priceData.color};
            ">
                <span>💰 Total:</span>
                <span>${priceData.totalCost} FCFA</span>
            </div>
        </div>
    `;
}

// Fonction compacte pour les listes
function generateCompactPriceDisplay(priceData) {
    return `
        <div class="compact-price" style="
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(212, 168, 83, 0.1);
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.85rem;
        ">
            <span style="color: #666;">📏 ${priceData.distance}</span>
            <span style="color: ${priceData.color}; font-weight: bold;">💰 ${priceData.totalCost} FCFA</span>
        </div>
    `;
}

// Exposer les fonctions globalement pour usage admin
window.calculatePriceAdmin = calculatePriceAdmin;
window.generateAdminPriceDisplay = generateAdminPriceDisplay;
window.generateCompactPriceDisplay = generateCompactPriceDisplay;
</script>
