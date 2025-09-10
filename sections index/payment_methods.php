<?php
// Simplified payment methods section
?>
<style>
/* Simplified Suzosky payment icons selector */
.payment-methods-section {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 12px;
    margin: 20px 0;
}
.payment-methods-section input[type="radio"] {
    display: none;
}
.payment-methods-section label {
    cursor: pointer;
}
.payment-methods-section label img {
    width: 24px;
    opacity: 0.6;
    transition: opacity 0.3s, transform 0.3s;
}
.payment-methods-section input[type="radio"]:checked + img {
    opacity: 1;
    transform: scale(1.2);
}
</style>

<div class="payment-methods-section">
    <label>
        <input type="radio" name="paymentMethod" value="cash" required>
        <img src="assets/img/payment/cash.svg" alt="Espèces">
    </label>
    <label>
        <input type="radio" name="paymentMethod" value="orange_money">
        <img src="assets/img/payment/orange-money.svg" alt="Orange Money">
    </label>
    <label>
        <input type="radio" name="paymentMethod" value="mtn_money">
        <img src="assets/img/payment/mtn-money.svg" alt="MTN Money">
    </label>
    <label>
        <input type="radio" name="paymentMethod" value="moov_money">
        <img src="assets/img/payment/moov-money.svg" alt="Moov Money">
    </label>
    <label>
        <input type="radio" name="paymentMethod" value="wave">
        <img src="assets/img/payment/wave.svg" alt="Wave">
    </label>
    <label>
        <input type="radio" name="paymentMethod" value="card">
        <img src="assets/img/payment/card.svg" alt="Carte bancaire">
    </label>
</div>
    background: rgba(255, 255, 255, 0.1);
    border-color: rgba(255, 255, 255, 0.3);
    transform: translateY(-2px);
}

.payment-icon {
    width: 50px;
    height: 50px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 12px;
    flex-shrink: 0;
    position: relative;
    overflow: hidden;
}

.payment-icon::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(45deg, rgba(212, 168, 83, 0.1), rgba(212, 168, 83, 0.2));
    opacity: 0;
    transition: opacity 0.3s ease;
}

.payment-option input[type="radio"]:checked + label .payment-icon::before {
    opacity: 1;
}

.payment-logo {
    max-width: 35px;
    max-height: 35px;
    display: block;
    filter: brightness(0.9);
    transition: filter 0.3s ease;
}

.payment-option input[type="radio"]:checked + label .payment-logo {
    filter: brightness(1.1);
}

.payment-details {
    flex: 1;
}

.payment-name {
    display: block;
    font-weight: 700;
    font-size: 1.05rem;
    margin-bottom: 5px;
    color: #fff;
    font-family: 'Montserrat', sans-serif;
}

.payment-info {
    display: block;
    font-size: 0.85rem;
    color: rgba(255, 255, 255, 0.7);
    font-weight: 400;
    line-height: 1.3;
}

.payment-badge {
    display: inline-block;
    background: rgba(212, 168, 83, 0.2);
    color: #D4A853;
    font-size: 0.7rem;
    font-weight: 700;
    padding: 3px 8px;
    border-radius: 8px;
    margin-top: 5px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

/* Catégories de paiement */
.payment-category {
    margin-bottom: 25px;
}

.payment-category-title {
    color: #D4A853;
    font-size: 1rem;
    font-weight: 700;
    margin-bottom: 15px;
    display: flex;
    align-items: center;
    gap: 10px;
    font-family: 'Montserrat', sans-serif;
    padding-left: 5px;
}

.payment-category-title::before {
    content: '';
    width: 4px;
    height: 20px;
    background: linear-gradient(135deg, #D4A853, #F4E4B8);
    border-radius: 2px;
}

/* Responsive */
@media (max-width: 768px) {
    .payment-methods-section {
        padding: 20px 15px;
        margin: 20px 0;
    }
    
    .payment-options-grid {
        grid-template-columns: 1fr;
        gap: 12px;
    }
    
    .payment-option label {
        padding: 15px;
        gap: 12px;
    }
    
    .payment-icon {
        width: 45px;
        height: 45px;
    }
    
    .payment-logo {
        max-width: 30px;
        max-height: 30px;
    }
    
    .payment-name {
        font-size: 1rem;
    }
    
    .payment-info {
        font-size: 0.8rem;
    }
}

@media (max-width: 480px) {
    .payment-header h3 {
        font-size: 1.1rem;
    }
    
    .payment-option label {
        padding: 12px;
        gap: 10px;
    }
    
    .payment-icon {
        width: 40px;
        height: 40px;
    }
}

/* Animation d'apparition séquentielle */
.payment-option {
    animation: paymentSlideIn 0.5s ease forwards;
    opacity: 0;
    transform: translateY(20px);
}

.payment-option:nth-child(1) { animation-delay: 0.1s; }
.payment-option:nth-child(2) { animation-delay: 0.2s; }
.payment-option:nth-child(3) { animation-delay: 0.3s; }
.payment-option:nth-child(4) { animation-delay: 0.4s; }
.payment-option:nth-child(5) { animation-delay: 0.5s; }
.payment-option:nth-child(6) { animation-delay: 0.6s; }

@keyframes paymentSlideIn {
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
</style>

<div class="payment-methods-section" id="paymentMethodsSection" style="display: none;">
    <div class="payment-header">
        <h3><i class="fas fa-credit-card"></i> Choisissez votre mode de paiement</h3>
        <p class="payment-subtitle">Sélectionnez la méthode qui vous convient le mieux</p>
    </div>
    
    <!-- Paiement traditionnel -->
    <div class="payment-category">
        <div class="payment-category-title">
            <i class="fas fa-hand-holding-dollar"></i>
            Paiement traditionnel
        </div>
        <div class="payment-options-grid">
            <div class="payment-option" data-method="cash" data-provider="none">
                <input type="radio" name="paymentMethod" value="cash" id="payment_cash" checked>
                <label for="payment_cash">
                    <div class="payment-icon">
                        <i class="fas fa-money-bills" style="color: #4CAF50; font-size: 1.5rem;"></i>
                    </div>
                    <div class="payment-details">
                        <span class="payment-name">Espèces à la livraison</span>
                        <span class="payment-info">Payez directement au coursier<br>Aucun frais supplémentaire</span>
                        <span class="payment-badge">Recommandé</span>
                    </div>
                </label>
            </div>
        </div>
    </div>
    
    <!-- Paiement mobile -->
    <div class="payment-category">
        <div class="payment-category-title">
            <i class="fas fa-mobile-alt"></i>
            Mobile Money (CinetPay)
        </div>
        <div class="payment-options-grid">
            <div class="payment-option" data-method="orange_money" data-provider="cinetpay">
                <input type="radio" name="paymentMethod" value="orange_money" id="payment_orange">
                <label for="payment_orange">
                    <div class="payment-icon">
                        <i class="fas fa-mobile-screen" style="color: #FF6600; font-size: 1.5rem;"></i>
                    </div>
                    <div class="payment-details">
                        <span class="payment-name">Orange Money</span>
                        <span class="payment-info">Paiement instantané et sécurisé<br>Via CinetPay</span>
                        <span class="payment-badge">Instantané</span>
                    </div>
                </label>
            </div>
            
            <div class="payment-option" data-method="mtn_money" data-provider="cinetpay">
                <input type="radio" name="paymentMethod" value="mtn_money" id="payment_mtn">
                <label for="payment_mtn">
                    <div class="payment-icon">
                        <i class="fas fa-mobile-screen" style="color: #FFCC00; font-size: 1.5rem;"></i>
                    </div>
                    <div class="payment-details">
                        <span class="payment-name">MTN Mobile Money</span>
                        <span class="payment-info">Paiement instantané et sécurisé<br>Via CinetPay</span>
                        <span class="payment-badge">Instantané</span>
                    </div>
                </label>
            </div>
            
            <div class="payment-option" data-method="moov_money" data-provider="cinetpay">
                <input type="radio" name="paymentMethod" value="moov_money" id="payment_moov">
                <label for="payment_moov">
                    <div class="payment-icon">
                        <i class="fas fa-mobile-screen" style="color: #0066CC; font-size: 1.5rem;"></i>
                    </div>
                    <div class="payment-details">
                        <span class="payment-name">Moov Money</span>
                        <span class="payment-info">Paiement instantané et sécurisé<br>Via CinetPay</span>
                        <span class="payment-badge">Instantané</span>
                    </div>
                </label>
            </div>
        </div>
    </div>
    
    <!-- Paiement numérique -->
    <div class="payment-category">
        <div class="payment-category-title">
            <i class="fas fa-wallet"></i>
            Portefeuille numérique (CinetPay)
        </div>
        <div class="payment-options-grid">
            <div class="payment-option" data-method="wave" data-provider="cinetpay">
                <input type="radio" name="paymentMethod" value="wave" id="payment_wave">
                <label for="payment_wave">
                    <div class="payment-icon">
                        <i class="fas fa-wave-square" style="color: #00D4AA; font-size: 1.5rem;"></i>
                    </div>
                    <div class="payment-details">
                        <span class="payment-name">Wave</span>
                        <span class="payment-info">Paiement rapide et sans frais<br>Via CinetPay</span>
                        <span class="payment-badge">Sans frais</span>
                    </div>
                </label>
            </div>
            
            <div class="payment-option" data-method="card" data-provider="cinetpay">
                <input type="radio" name="paymentMethod" value="card" id="payment_card">
                <label for="payment_card">
                    <div class="payment-icon">
                        <i class="fas fa-credit-card" style="color: #6C63FF; font-size: 1.5rem;"></i>
                    </div>
                    <div class="payment-details">
                        <span class="payment-name">Carte bancaire</span>
                        <span class="payment-info">Visa / Mastercard<br>Via CinetPay (frais 2.5%)</span>
                        <span class="payment-badge">Sécurisé</span>
                    </div>
                </label>
            </div>
        </div>
    </div>
</div>

<script>
// === GESTION DES MODES DE PAIEMENT ===

function showPaymentMethods() {
    const paymentSection = document.getElementById('paymentMethodsSection');
    if (paymentSection) {
        paymentSection.style.display = 'block';
        // Petite pause pour l'affichage, puis animation
        setTimeout(() => {
            paymentSection.classList.add('show');
        }, 50);
    }
}

function hidePaymentMethods() {
    const paymentSection = document.getElementById('paymentMethodsSection');
    if (paymentSection) {
        paymentSection.classList.remove('show');
        setTimeout(() => {
            paymentSection.style.display = 'none';
        }, 400);
    }
}

function isFormValidForPayment() {
    // Vérifier que tous les champs requis sont remplis (sauf description)
    const departure = document.getElementById('departure')?.value.trim();
    const destination = document.getElementById('destination')?.value.trim();
    const senderPhone = document.getElementById('senderPhone')?.value.trim();
    const receiverPhone = document.getElementById('receiverPhone')?.value.trim();
    
    // Vérifier qu'un calcul de prix a été effectué
    const priceSection = document.getElementById('price-calculation-section');
    const isPriceCalculated = priceSection && priceSection.classList.contains('price-visible');
    
    return departure && destination && senderPhone && receiverPhone && isPriceCalculated;
}

function checkFormCompletionForPayment() {
    if (isFormValidForPayment()) {
        showPaymentMethods();
    } else {
        hidePaymentMethods();
    }
}

// Gestion des changements de méthode de paiement
document.addEventListener('change', function(e) {
    if (e.target.name === 'paymentMethod') {
        const selectedMethod = e.target.value;
        const selectedOption = e.target.closest('.payment-option');
        const provider = selectedOption?.dataset.provider;
        
        console.log('Méthode de paiement sélectionnée:', selectedMethod, 'Provider:', provider);
        
        // Mettre à jour l'affichage si nécessaire
        updatePaymentMethodDisplay(selectedMethod, provider);
    }
});

function updatePaymentMethodDisplay(method, provider) {
    // Ajouter une classe CSS pour identifier le type de paiement
    const paymentSection = document.getElementById('paymentMethodsSection');
    if (paymentSection) {
        // Supprimer les anciennes classes
        paymentSection.classList.remove('payment-cash', 'payment-cinetpay');
        
        // Ajouter la nouvelle classe
        if (provider === 'cinetpay') {
            paymentSection.classList.add('payment-cinetpay');
        } else {
            paymentSection.classList.add('payment-cash');
        }
    }
    
    // Émettre un événement personnalisé pour l'intégration
    window.dispatchEvent(new CustomEvent('paymentMethodChanged', {
        detail: { method, provider }
    }));
}

function getSelectedPaymentMethod() {
    const selectedRadio = document.querySelector('input[name="paymentMethod"]:checked');
    if (selectedRadio) {
        const option = selectedRadio.closest('.payment-option');
        return {
            method: selectedRadio.value,
            provider: option?.dataset.provider || 'none'
        };
    }
    return { method: 'cash', provider: 'none' };
}

// Initialisation
document.addEventListener('DOMContentLoaded', function() {
    // Surveiller les changements du formulaire
    const formInputs = ['departure', 'destination', 'senderPhone', 'receiverPhone'];
    formInputs.forEach(inputId => {
        const input = document.getElementById(inputId);
        if (input) {
            input.addEventListener('input', checkFormCompletionForPayment);
            input.addEventListener('change', checkFormCompletionForPayment);
        }
    });
    
    // Surveiller les changements de priorité
    document.querySelectorAll('input[name="priority"]').forEach(radio => {
        radio.addEventListener('change', checkFormCompletionForPayment);
    });
    
    // Vérification initiale
    setTimeout(checkFormCompletionForPayment, 1000);
});

// Événement personnalisé pour l'integration avec le calcul de prix
window.addEventListener('priceCalculated', function() {
    setTimeout(checkFormCompletionForPayment, 500);
});

// Exporter les fonctions pour usage externe
window.PaymentMethods = {
    show: showPaymentMethods,
    hide: hidePaymentMethods,
    isFormValid: isFormValidForPayment,
    checkCompletion: checkFormCompletionForPayment,
    getSelected: getSelectedPaymentMethod
};
</script>
