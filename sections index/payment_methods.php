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
