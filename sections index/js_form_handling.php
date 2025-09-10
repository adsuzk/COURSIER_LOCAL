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
        // Nettoyer le numéro
        let cleaned = phone.replace(/\D/g, '');
        
        // Supprimer le code pays s'il est présent
        if (cleaned.startsWith('225')) {
            cleaned = cleaned.substring(3);
        }
        
        // Ajouter le 0 au début si nécessaire
        if (!cleaned.startsWith('0') && cleaned.length === 8) {
            cleaned = '0' + cleaned;
        }
        
        // Formater : 07 12 34 56 78
        if (cleaned.length >= 10) {
            return cleaned.replace(/(\d{2})(\d{2})(\d{2})(\d{2})(\d{2})/, '$1 $2 $3 $4 $5');
        }
        
        return phone;
    }
    
    // Formatage automatique du numéro de téléphone
    function setupPhoneFormatting() {
        const phoneInputs = document.querySelectorAll('input[type="tel"]');
        
        phoneInputs.forEach(input => {
            input.addEventListener('input', function() {
                this.value = formatIvorianPhone(this.value);
            });
            
            input.addEventListener('blur', function() {
                if (this.value && !isValidIvorianPhone(this.value)) {
                    this.style.borderColor = '#ff4757';
                    showFieldError(this, 'Format de numéro invalide');
                } else {
                    this.style.borderColor = '';
                    hideFieldError(this);
                }
            });
        });
    }
    
    // Validation des emails en temps réel
    function setupEmailValidation() {
        const emailInputs = document.querySelectorAll('input[type="email"]');
        
        emailInputs.forEach(input => {
            input.addEventListener('blur', function() {
                if (this.value && !isValidEmail(this.value)) {
                    this.style.borderColor = '#ff4757';
                    showFieldError(this, 'Format d\'email invalide');
                } else {
                    this.style.borderColor = '';
                    hideFieldError(this);
                }
            });
        });
    }
    
    // Affichage des erreurs de champ
    function showFieldError(field, message) {
        hideFieldError(field);
        
        const errorDiv = document.createElement('div');
        errorDiv.className = 'field-error';
        errorDiv.textContent = message;
        errorDiv.style.cssText = `
            color: #ff4757;
            font-size: 12px;
            margin-top: 4px;
            display: block;
        `;
        
        field.parentNode.appendChild(errorDiv);
    }
    
    function hideFieldError(field) {
        const existingError = field.parentNode.querySelector('.field-error');
        if (existingError) {
            existingError.remove();
        }
    }
    
    // Validation du formulaire de commande
    function validateOrderForm() {
        const departure = document.getElementById('departure').value.trim();
        const destination = document.getElementById('destination').value.trim();
        const phone = document.getElementById('senderPhone').value.trim();
        const priority = document.querySelector('input[name="priority"]:checked');
        
        let isValid = true;
        let errors = [];
        
        // Vérifier l'adresse de départ
        if (!departure) {
            errors.push('L\'adresse de départ est requise');
            document.getElementById('departure').style.borderColor = '#ff4757';
            isValid = false;
        } else {
            document.getElementById('departure').style.borderColor = '';
        }
        
        // Vérifier l'adresse de destination
        if (!destination) {
            errors.push('L\'adresse de destination est requise');
            document.getElementById('destination').style.borderColor = '#ff4757';
            isValid = false;
        } else {
            document.getElementById('destination').style.borderColor = '';
        }
        
        // Vérifier le numéro de téléphone
        if (!phone) {
            errors.push('Le numéro de téléphone de l\'expéditeur est requis');
            document.getElementById('senderPhone').style.borderColor = '#ff4757';
            isValid = false;
        } else if (!isValidIvorianPhone(phone)) {
            errors.push('Format de numéro de téléphone invalide');
            document.getElementById('senderPhone').style.borderColor = '#ff4757';
            isValid = false;
        } else {
            document.getElementById('senderPhone').style.borderColor = '';
        }
        
        // Vérifier la priorité
        if (!priority) {
            errors.push('Veuillez sélectionner une priorité');
            isValid = false;
        }
        
        // Vérifier que les marqueurs sont placés
        if (!markerA || !markerB) {
            errors.push('Veuillez placer les marqueurs sur la carte');
            isValid = false;
        }
        
        if (!isValid) {
            alert(errors.join('\n'));
            return false;
        }
        
        return true;
    }
    
    // Réinitialisation du formulaire
    function resetOrderForm() {
        if (confirm('Êtes-vous sûr de vouloir réinitialiser le formulaire ?')) {
            // Réinitialiser les champs
            document.getElementById('departure').value = '';
            document.getElementById('destination').value = '';
            // Réinitialiser le téléphone expéditeur uniquement si pas de session ouverte
            if (!window.currentClient) {
                document.getElementById('senderPhone').value = '';
            }
            
            // Réinitialiser les priorités
            document.querySelector('input[name="priority"][value="normale"]').checked = true;
            
            // Réinitialiser les méthodes de paiement
            document.getElementById('mobile-money').checked = true;
            document.getElementById('especes').checked = false;
            
            // Réinitialiser les styles des champs
            const inputs = document.querySelectorAll('#orderForm input, #orderForm select');
            inputs.forEach(input => {
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
        }
        // Utilisateur connecté : soumettre la commande
        submitOrder();
    }

    // Soumission du formulaire de commande
    function submitOrder() {
        if (!validateOrderForm()) {
            return;
        }
        
        // Vérifier l'authentification
        if (!window.currentClient) {
            openConnexionModal();
            return;
        }
        
        const orderData = {
            user_id: currentUser.id,
            departure: document.getElementById('departure').value,
            destination: document.getElementById('destination').value,
            phone: document.getElementById('senderPhone').value,
            priority: document.querySelector('input[name="priority"]:checked').value,
            payment_method: document.querySelector('input[name="payment"]:checked').value,
            departure_coords: markerA ? {
                lat: markerA.getPosition().lat(),
                lng: markerA.getPosition().lng()
            } : null,
            destination_coords: markerB ? {
                lat: markerB.getPosition().lat(),
                lng: markerB.getPosition().lng()
            } : null,
            distance: document.getElementById('distance-info').textContent,
            price: document.getElementById('price-info').textContent,
            timestamp: new Date().toISOString()
        };
        
        console.log('Données de commande avant assignation:', orderData);
        // Appel à l'API pour assigner un coursier
        fetch('api/assign_courier.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ pickup: orderData.departure_coords })
        })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                orderData.courier = res.courier;
                console.log('Coursier assigné:', res.courier);
                // Lancer le paiement/modal final
                showPaymentModal(orderData);
            } else {
                alert(res.error || 'Aucun coursier disponible');
            }
        })
        .catch(() => alert('Erreur réseau lors de l’assignation du coursier'));
    }
    
    // Mise à jour des onglets selon l'état de connexion
    function updateOrderTabs() {
        const guestTab = document.querySelector('[data-tab="guest"]');
        const userTab = document.querySelector('[data-tab="user"]');
        
        if (isLoggedIn) {
            if (guestTab) guestTab.style.display = 'none';
            if (userTab) userTab.style.display = 'block';
        } else {
            if (guestTab) guestTab.style.display = 'block';
            if (userTab) userTab.style.display = 'none';
        }
    }
    
    // Auto-complétion et suggestions
    function setupFormEnhancements() {
        // Formatage automatique du téléphone
        setupPhoneFormatting();
        
        // Validation des emails
        setupEmailValidation();
        
        // Sauvegarde automatique (draft)
        setupAutosave();
    }
    
    // Sauvegarde automatique du formulaire
    function setupAutosave() {
        const formInputs = document.querySelectorAll('#orderForm input, #orderForm select');
        
        formInputs.forEach(input => {
            input.addEventListener('input', debounce(() => {
                saveFormDraft();
            }, 1000));
        });
    }
    
    function saveFormDraft() {
        if (!isLoggedIn) return;
        
        const formData = {
            departure: document.getElementById('departure').value,
            destination: document.getElementById('destination').value,
            priority: document.querySelector('input[name="priority"]:checked')?.value,
            payment_method: document.querySelector('input[name="payment"]:checked')?.value,
            timestamp: Date.now()
        };
        
        localStorage.setItem('orderDraft_' + currentUser.id, JSON.stringify(formData));
    }
    
    function loadFormDraft() {
        if (!isLoggedIn) return;
        
        const draft = localStorage.getItem('orderDraft_' + currentUser.id);
        if (draft) {
            const formData = JSON.parse(draft);
            
            // Charger seulement si le draft est récent (moins de 24h)
            if (Date.now() - formData.timestamp < 24 * 60 * 60 * 1000) {
                document.getElementById('departure').value = formData.departure || '';
                document.getElementById('destination').value = formData.destination || '';
                
                if (formData.priority) {
                    const priorityRadio = document.querySelector(`input[name="priority"][value="${formData.priority}"]`);
                    if (priorityRadio) priorityRadio.checked = true;
                }
                
                if (formData.payment_method) {
                    const paymentRadio = document.querySelector(`input[name="payment"][value="${formData.payment_method}"]`);
                    if (paymentRadio) paymentRadio.checked = true;
                }
            }
        }
    }
    
    // Fonction utilitaire de debounce
    function debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }

    // CALCUL AUTOMATIQUE DES PRIX
    let priceCalculationService;
    let lastCalculationRequest = null;

    // Configuration des tarifs
    const PRICING_CONFIG = {
        normale: {
            name: 'Normal',
            baseFare: 300, // FCFA
            perKmRate: 300, // FCFA par km
            color: '#4CAF50'
        },
        urgente: {
            name: 'Urgent',
            baseFare: 1000, // FCFA
            perKmRate: 500, // FCFA par km
            color: '#FF9800'
        },
        express: {
            name: 'Express',
            baseFare: 1500, // FCFA
            perKmRate: 700, // FCFA par km
            color: '#F44336'
        }
    };

    // Initialisation du service de calcul des prix
    function initializePriceCalculation() {
        if (typeof google !== 'undefined' && google.maps && google.maps.DistanceMatrixService) {
            priceCalculationService = new google.maps.DistanceMatrixService();
            console.log('Service de calcul des prix initialisé');
            
            // Écouter les changements d'adresses
            setupPriceCalculationListeners();
        } else {
            console.warn('Google Maps API non disponible pour le calcul des prix');
            setTimeout(initializePriceCalculation, 1000);
        }
    }

    // Configuration des écouteurs pour le calcul automatique
    function setupPriceCalculationListeners() {
        const departureInput = document.getElementById('departure');
        const destinationInput = document.getElementById('destination');
        const priorityInputs = document.querySelectorAll('input[name="priority"]');

        if (departureInput && destinationInput) {
            console.log('setupPriceCalculationListeners: initialisation des écouteurs');
            // Calcul automatique avec debounce sur les adresses
            const debouncedCalculation = debounce(calculatePriceAutomatically, 1500);
            
            // Recalculer automatiquement lors de la saisie ou perte de focus
            // Recalcul automatique lors de la saisie ou perte de focus
            departureInput.addEventListener('input', debouncedCalculation);
            departureInput.addEventListener('blur', debouncedCalculation);
            destinationInput.addEventListener('input', debouncedCalculation);
            destinationInput.addEventListener('blur', debouncedCalculation);
            
            // Calcul immédiat sur changement de priorité
            priorityInputs.forEach(input => {
                input.addEventListener('change', event => {
                    calculatePriceAutomatically();
                });
            });
        }
    }

    // Fonction principale de calcul automatique
    function calculatePriceAutomatically() {
    console.log('calculatePriceAutomatically: appelée');
    const departure = document.getElementById('departure')?.value?.trim();
        const destination = document.getElementById('destination')?.value?.trim();
        const selectedPriority = document.querySelector('input[name="priority"]:checked')?.value || 'normale';

        // Vérifier si on a les deux adresses
        if (!departure || !destination || departure.length < 3 || destination.length < 3) {
            clearPriceDisplay();
            return;
        }

        // Annuler la requête précédente si elle existe
        if (lastCalculationRequest) {
            lastCalculationRequest.abort = true;
        }

    // ESTIMATION IMMÉDIATE (fallback) pour garantir affichage sans attendre l'API
    estimatePriceWithoutAPI(departure, destination, selectedPriority);
    return;
    }

    // Calcul et affichage du prix
    function calculateAndDisplayPrice(distance, duration, priority) {
        const config = PRICING_CONFIG[priority] || PRICING_CONFIG.normale;
        const distanceKm = distance.value / 1000; // Convertir en km
        
        // Calculs
        const baseFare = config.baseFare;
        const distanceCost = Math.ceil(distanceKm * config.perKmRate);
        const totalPrice = baseFare + distanceCost;
        
        // Affichage
        displayPriceBreakdown({
            distance: distance,
            duration: duration,
            priority: priority,
            config: config,
            baseFare: baseFare,
            distanceCost: distanceCost,
            totalPrice: totalPrice,
            distanceKm: distanceKm
        });
    }

    // Affichage détaillé du calcul de prix
    function displayPriceBreakdown(calculation) {
        const priceSection = document.getElementById('price-calculation-section');
        const distanceInfo = document.getElementById('distance-info');
        const timeInfo = document.getElementById('time-info');
        const priceBreakdown = document.getElementById('price-breakdown');
        const totalPriceElement = document.getElementById('total-price');

        if (!priceSection) return;

        // Afficher la section
        priceSection.style.display = 'block';
        priceSection.classList.add('price-calculated');

        // Distance et durée
        if (distanceInfo) {
            distanceInfo.innerHTML = `
                <i class="fas fa-route"></i>
                <span class="distance-value">${calculation.distance.text}</span>
            `;
            // Champs estimés basiques
            const estDistanceInput = document.getElementById('estDistance');
            if (estDistanceInput) estDistanceInput.value = calculation.distance.text;
        }

        if (timeInfo) {
            timeInfo.innerHTML = `
                <i class="fas fa-clock"></i>
                <span class="time-value">${calculation.duration.text}</span>
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

    // Initialisation au chargement de la page
    document.addEventListener('DOMContentLoaded', function() {
        // Configurer les écouteurs pour le calcul automatique
        setupPriceCalculationListeners();
        // Initialiser le service de calcul de prix (tentative immédiate)
        initializePriceCalculation();
    });
    </script>
