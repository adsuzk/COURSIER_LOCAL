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
                <?php
                // sections/js_form_handling.php - Validation et formatage du formulaire de commande
                ?>
                <script>
                (() => {
                    const form = document.getElementById('orderForm');
                    function vEmail(v) { return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v); }
                    function vPhone(v) { const c=v.replace(/\s/g,''); return /^((\+225|225|0)\d{8,9})$/.test(c); }
                    function fPhone(v){ let d=v.replace(/\D/g,''); if(d.startsWith('225'))d=d.slice(3); if(!d.startsWith('0')&&d.length===8)d='0'+d; return d.replace(/(\d{2})(?=\d)/g,'$1 '); }
                    function sErr(el,m){ let e=el.parentNode.querySelector('.field-error'); if(e)e.remove(); e=document.createElement('div'); e.className='field-error'; e.style.color='#f00'; e.textContent=m; el.parentNode.appendChild(e);}  
                    function hErr(el){ let e=el.parentNode.querySelector('.field-error'); if(e)e.remove(); }
                    function valid(){ let ok=true,msg=[];
                        const dep=document.getElementById('departure'), dst=document.getElementById('destination'), ph=document.getElementById('senderPhone'), pr=document.querySelector('input[name=priority]:checked');
                        if(!dep.value.trim()){ok=false;msg.push('Départ requis');dep.style.borderColor='#f00';}else{dep.style.borderColor='';}
                        if(!dst.value.trim()){ok=false;msg.push('Destination requise');dst.style.borderColor='#f00';}else{dst.style.borderColor='';}
                        if(!ph.value.trim()||!vPhone(ph.value)){ok=false;msg.push('Téléphone invalide');ph.style.borderColor='#f00';}else{ph.style.borderColor='';}
                        if(!pr){ok=false;msg.push('Priorité requise');}
                        if(!ok)alert(msg.join("\n")); return ok;
                    }
                        function onSub(e){
                            e.preventDefault();
                            if (!valid()) return;
                            // Determine payment method
                            const methodEl = document.querySelector('input[name="paymentMethod"]:checked');
                            const paymentMethod = methodEl ? methodEl.value : 'cash';
                            if (paymentMethod === 'cash') {
                                // Cash: submit form normally
                                form.submit();
                            } else {
                                // Non-cash: initiate CinetPay modal
                                // Collect form data
                                const formData = new FormData(form);
                                fetch('/api/initiate_order_payment.php', {
                                    method: 'POST',
                                    body: formData
                                })
                                .then(res => res.json())
                                .then(data => {
                                    if (data.success && data.payment_url) {
                                        // Show embedded CinetPay modal
                                        showPaymentModal(data.payment_url);
                                    } else {
                                        alert('Erreur lors de l\'initialisation du paiement.');
                                    }
                                })
                                .catch(err => {
                                    console.error('Paiement init error:', err);
                                    alert('Impossible d\'initier le paiement.');
                                });
                            }
                        }
                        // Exposer processOrder pour l'attribut onclick inline comme alias de onSub
                        window.processOrder = onSub;
                    document.addEventListener('DOMContentLoaded',()=>{
                        document.getElementById('senderPhone').addEventListener('input',e=>e.target.value=fPhone(e.target.value));
                        document.querySelectorAll('input[type=email]').forEach(i=>i.addEventListener('blur',e=>{ if(i.value&&!vEmail(i.value)){sErr(i,'Email invalide');}else{hErr(i);} }));
                        const btn=document.querySelector('.submit-btn'); if(btn)btn.addEventListener('click',onSub);
                    });
                })();
                </script>
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
