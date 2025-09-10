<?php
// sections_order_form/form_fields.php - Champs du formulaire de commande (adresse, téléphone, priorité)
?>
<div class="form-fields">
    <div class="address-row">
        <div class="form-group">
            <label for="departure">Départ (Expéditeur)</label>
            <div class="input-with-icon departure">
                <input type="text" id="departure" name="departure" placeholder="Adresse de départ..." required autocomplete="off">
            </div>
            <div class="location-controls">
                <button type="button" onclick="getCurrentLocation('departure')" class="gps-btn">📍 Ma position (A)</button>
                <small>💡 Déplacez le marqueur A pour précision</small>
            </div>
        </div>
        <div class="route-arrow">→</div>
        <div class="form-group">
            <label for="destination">Arrivée (Destinataire)</label>
            <div class="input-with-icon destination">
                <input type="text" id="destination" name="destination" placeholder="Adresse de destination..." required autocomplete="off">
            </div>
            <div class="location-controls">
                <button type="button" onclick="getCurrentLocation('destination')" class="gps-btn">📍 Ma position (B)</button>
                <small>💡 Déplacez le marqueur B pour précision</small>
            </div>
        </div>
    </div>

    <div class="form-group">
        <label for="senderPhone"><i class="fas fa-phone"></i> Téléphone Expéditeur</label>
        <div class="input-with-icon phone">
            <?php $sessionPhone = $_SESSION['client_telephone'] ?? ''; ?>
            <input type="tel" id="senderPhone" name="senderPhone"
                   placeholder="+225 XX XX XX XX XX"
                   maxlength="17"
                   required
                   value="<?= htmlspecialchars(
                       $sessionPhone
                   ) ?>"
                   <?= $sessionPhone ? 'readonly' : '' ?>
            >
        </div>
    </div>

    <div class="priority-options">
        <div class="priority-option">
            <input type="radio" id="normal" name="priority" value="normale" checked>
            <label for="normal" class="priority-label">🚶 Normal<br><small>1-2h</small></label>
        </div>
        <div class="priority-option">
            <input type="radio" id="urgent" name="priority" value="urgente">
            <label for="urgent" class="priority-label">⚡ Urgent<br><small>30min</small></label>
        </div>
        <div class="priority-option">
            <input type="radio" id="express" name="priority" value="express">
            <label for="express" class="priority-label">🚀 Express<br><small>15min</small></label>
        </div>
    </div>
</div>
