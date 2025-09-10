<?php
// sections/js_form_handling.php - Fonctions de gestion des formulaires et validation
?>
    <script>
    // Fonctions de validation
    function isValidEmail(email) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }
    
    function isValidIvorianPhone(phone) {
        // Formats acceptés : +225 XX XX XX XX XX, 225XXXXXXXXXX, 0XXXXXXXXX
        const phoneRegex = /^(\+225|225|0)[0-9\s]{8,12}$/;
        const cleanPhone = phone.replace(/\s/g, '');
        return phoneRegex.test(cleanPhone) && cleanPhone.length >= 10;
    }
    
    function formatIvorianPhone(phone) {
                input.style.borderColor = '';
                hideFieldError(input);
            });
            
            // Supprimer les marqueurs de la carte
            if (markerA) {
                markerA.setMap(null);
                markerA = null;
            }
            if (markerB) {
                markerB.setMap(null);
                markerB = null;
            }
            
            // Supprimer la route
            if (directionsRenderer) {
                directionsRenderer.setDirections({routes: []});
            }
            
            // Réinitialiser les informations de prix et distance
            clearPriceDisplay();
            
            // Recentrer la carte sur Abidjan
            if (map) {
                map.setCenter({lat: 5.3600, lng: -4.0083});
                map.setZoom(12);
            }
            
            showMessage('Formulaire réinitialisé', 'info');
        }
    }
    
    // Traitement centralisé du clic sur "Commander"
    function processOrder() {
        // Validation minimale des champs
        const departure = document.getElementById('departure').value.trim();
        const destination = document.getElementById('destination').value.trim();
        const senderPhone = document.getElementById('senderPhone').value.trim();
        const priority = document.querySelector('input[name="priority"]:checked');
        if (!departure || !destination || !senderPhone || !priority) {
            alert('Veuillez remplir tous les champs avant de commander.');
            return;
        }
        // Si non connecté : vérifier en base et ouvrir le bon modal
        if (!window.currentClient) {
            fetch('api/auth.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'action=check_phone&phone=' + encodeURIComponent(senderPhone)
            })
            .then(r => r.json())
            .then(d => {
                if (d.success) {
                    if (d.exists) {
                        openConnexionModal();
                    } else {
                        openRegisterModal();
                    }
                } else {
                    alert(d.error || 'Erreur de vérification du numéro');
                }
            })
            .catch(() => alert('Erreur réseau lors de la vérification du numéro'));
            return;
        <?php
        // sections/js_form_handling.php - Validation et formatage du formulaire de commande
        ?>
        <script>
        (() => {
            const orderForm = document.getElementById('orderForm');
            // Validation e-mail
            function isValidEmail(v) { return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v); }
            // Validation téléphone CI
            function isValidPhone(v) { const c=v.replace(/\s/g,''); return /^((\+225|225|0)\d{8,9})$/.test(c); }
            // Formatage téléphone CI
            function formatPhone(v) {
                let d=v.replace(/\D/g,''); if(d.startsWith('225'))d=d.slice(3); if(!d.startsWith('0')&&d.length===8)d='0'+d;
                return d.replace(/(\d{2})(?=\d)/g,'$1 ');
            }
            function showError(el,msg){ let e=el.parentNode.querySelector('.field-error'); if(e)e.remove(); e=document.createElement('div');e.className='field-error';e.style.color='#f00';e.textContent=msg;el.parentNode.appendChild(e); }
            function hideError(el){ let e=el.parentNode.querySelector('.field-error'); if(e)e.remove(); }
            function validate() {
                const dep=document.getElementById('departure'), dst=document.getElementById('destination'), phone=document.getElementById('senderPhone'), pr=document.querySelector('input[name=priority]:checked');
                let ok=true, msgs=[];
                if(!dep.value.trim()){ok=false;msgs.push('Départ requis');dep.style.borderColor='#f00';}else{dep.style.borderColor='';}
                if(!dst.value.trim()){ok=false;msgs.push('Destination requise');dst.style.borderColor='#f00';}else{dst.style.borderColor='';}
                if(!phone.value.trim()||!isValidPhone(phone.value)){ok=false;msgs.push('Téléphone invalide');phone.style.borderColor='#f00';}else{phone.style.borderColor='';}
                if(!pr){ok=false;msgs.push('Priorité requise');}
                if(!ok){alert(msgs.join('\n'));}
                return ok;
            }
            function onSubmit(e){ e.preventDefault(); if(validate()){orderForm.submit();} }
            document.addEventListener('DOMContentLoaded',()=>{
                document.getElementById('senderPhone').addEventListener('input',e=>e.target.value=formatPhone(e.target.value));
                document.querySelectorAll('input[type=email]').forEach(i=>i.addEventListener('blur',e=>{if(i.value&&!isValidEmail(i.value)){showError(i,'Email invalide');}else{hideError(i);}}));
                const btn=document.querySelector('.submit-btn'); if(btn){btn.addEventListener('click',onSubmit);}  
            });
        })();
        </script>
            `;
            const estDurationInput = document.getElementById('estDuration');
            if (estDurationInput) estDurationInput.value = calculation.duration.text;
        }

        // Détail du calcul
        if (priceBreakdown) {
            priceBreakdown.innerHTML = `
                <div class="price-line">
                    <span class="description">Tarif de base (${calculation.config.name})</span>
                    <span class="amount">${calculation.baseFare} FCFA</span>
                </div>
                <div class="price-line">
                    <span class="description">Distance (${calculation.distanceKm.toFixed(1)} km × ${calculation.config.perKmRate} FCFA/km)</span>
                    <span class="amount">${calculation.distanceCost} FCFA</span>
                </div>
                <div class="price-separator"></div>
            `;
        }

        // Prix total
        if (totalPriceElement) {
            totalPriceElement.innerHTML = `
                <span class="total-label">Prix total estimé</span>
                <span class="total-amount">${calculation.totalPrice} FCFA</span>
            `;
            totalPriceElement.style.borderColor = calculation.config.color;
            const estPriceInput = document.getElementById('estPrice');
            if (estPriceInput) estPriceInput.value = `${calculation.totalPrice} FCFA`;
        }

        // Animation d'apparition
        setTimeout(() => {
            priceSection.classList.add('price-visible');
        }, 100);
    }

    // Affichage du loading
    function showPriceLoading() {
        const priceSection = document.getElementById('price-calculation-section');
        const distanceInfo = document.getElementById('distance-info');
        const timeInfo = document.getElementById('time-info');
        const priceBreakdown = document.getElementById('price-breakdown');
        const totalPriceElement = document.getElementById('total-price');

        if (!priceSection) return;

        priceSection.style.display = 'block';
        priceSection.classList.remove('price-calculated', 'price-visible');
        priceSection.classList.add('price-loading');

        const loadingHTML = '<i class="fas fa-spinner fa-spin"></i> Calcul en cours...';
        
        if (distanceInfo) distanceInfo.innerHTML = loadingHTML;
        if (timeInfo) timeInfo.innerHTML = loadingHTML;
        if (priceBreakdown) priceBreakdown.innerHTML = loadingHTML;
        if (totalPriceElement) totalPriceElement.innerHTML = loadingHTML;
    }

    // Effacement de l'affichage du prix
    function clearPriceDisplay() {
        const priceSection = document.getElementById('price-calculation-section');
        if (priceSection) {
            priceSection.style.display = 'none';
            priceSection.classList.remove('price-calculated', 'price-visible', 'price-loading');
        }
    }

    // Affichage d'erreur
    function showPriceError(message) {
        const priceSection = document.getElementById('price-calculation-section');
        const totalPriceElement = document.getElementById('total-price');
        
        if (priceSection && totalPriceElement) {
            priceSection.style.display = 'block';
            priceSection.classList.remove('price-loading', 'price-calculated');
            priceSection.classList.add('price-error');
            
            totalPriceElement.innerHTML = `
                <span class="error-message">
                    <i class="fas fa-exclamation-triangle"></i>
                    ${message}
                </span>
            `;
            
            // Masquer après 5 secondes
            setTimeout(clearPriceDisplay, 5000);
        }
    }

    // Estimation de prix sans API (fallback)
    function estimatePriceWithoutAPI(departure, destination, priority) {
        // Estimation très basique basée sur la longueur des adresses
        const estimatedKm = Math.max(2, Math.min(50, (departure.length + destination.length) / 10));
        const config = PRICING_CONFIG[priority] || PRICING_CONFIG.normale;
        
        const baseFare = config.baseFare;
        const distanceCost = Math.ceil(estimatedKm * config.perKmRate);
        const totalPrice = baseFare + distanceCost;
        
        displayPriceBreakdown({
            distance: { text: `~${estimatedKm.toFixed(1)} km`, value: estimatedKm * 1000 },
            duration: { text: `~${Math.ceil(estimatedKm * 2)} min` },
            priority: priority,
            config: config,
            baseFare: baseFare,
            distanceCost: distanceCost,
            totalPrice: totalPrice,
            distanceKm: estimatedKm
        });
    }

    // Initialisation au chargement : Price Calculation via Google Distance Matrix
    document.addEventListener('DOMContentLoaded', function() {
        // Attente que Google DistanceMatrixService soit disponible
        function setupPriceCalc() {
            if (!window.google || !google.maps || !google.maps.DistanceMatrixService) {
                console.log('DistanceMatrixService non chargé, tentative dans 1000ms...');
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
            service.getDistanceMatrix({
                origins: [o],
                destinations: [d],
                travelMode: google.maps.TravelMode.DRIVING,
                unitSystem: google.maps.UnitSystem.METRIC
            }, function(response, status) {
                console.log('📡 Réponse Google DistanceMatrix:', {status, response});
                if (status !== 'OK') {
                    console.error('❌ DistanceMatrixService status:', status);
                    return;
                }
                const el = response.rows[0].elements[0];
                console.log('📍 Element de réponse:', el);
                if (el.status !== 'OK') {
                    console.error('❌ DistanceMatrix element status:', el.status);
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
                // Mise à jour UI
                document.getElementById('distance-info').innerHTML   = `📏 ${distText}`;
                document.getElementById('time-info').innerHTML       = `⏱️ ${durText}`;
                document.getElementById('price-breakdown').innerHTML = `
                    <div class="price-line">
                        <span class="description">Base (${cfg.name})</span>
                        <span class="amount">${cfg.base} FCFA</span>
                    </div>
                    <div class="price-line">
                        <span class="description">${kmVal.toFixed(1)} km × ${cfg.perKm} FCFA/km</span>
                        <span class="amount">${Math.ceil(kmVal * cfg.perKm)} FCFA</span>
                    </div>
                    <div class="price-separator"></div>`;
                const tp = document.getElementById('total-price');
                tp.innerHTML = `💰 ${cost} FCFA`;
                tp.style.borderColor = cfg.color;
                section.style.display = 'block';
                console.log('✅ Interface mise à jour, section affichée!');
            });
        }
        
        // Événements
            // Déclenche le calcul lors de la saisie ou perte de focus
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
        setupPriceCalc();
    });
    </script>
