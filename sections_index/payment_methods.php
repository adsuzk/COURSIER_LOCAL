<?php
// Simplified payment methods section
?>
<!-- Import payment methods CSS -->
<link rel="stylesheet" href="css/components/form/payment-methods.css">

<div class="payment-methods-container">
    <div class="payment-methods-title">
        💳 Choisissez votre mode de paiement
    </div>
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
</div>
