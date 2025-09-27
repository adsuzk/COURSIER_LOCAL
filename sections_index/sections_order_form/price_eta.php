<?php
// sections_order_form/price_eta.php - Affichage du prix estimé et ETA
require_once __DIR__ . '/../../config.php';
// Récupérer paramètres depuis la base
$prix_km = 300;
$frais_base = 500;
try {
  $pdo = getDBConnection();
  $stmt = $pdo->query("SELECT parametre, valeur FROM parametres_tarification");
  $params = [];
  foreach ($stmt as $row) { $params[$row['parametre']] = (float)$row['valeur']; }
  if (isset($params['prix_kilometre'])) { $prix_km = (int)$params['prix_kilometre']; }
  if (isset($params['frais_base'])) { $frais_base = (int)$params['frais_base']; }
} catch (Exception $e) { /* valeurs par défaut */ }
?>
<div class="price-estimate" id="estimatedInfo" style="display:none;">
    <p>Prix estimé : <span id="estimatedPrice">-</span> FCFA</p>
    <p>ETA (min) : <span id="estimatedTime">-</span> min</p>
</div>
<script>
// Calculer distance via Google Distance Matrix
function calculatePriceAndEta() {
  const dep = document.getElementById('departure').value;
  const dest = document.getElementById('destination').value;
  if (!dep || !dest) return;
  const service = new google.maps.DistanceMatrixService();
  service.getDistanceMatrix({
    origins: [dep],
    destinations: [dest],
    travelMode: 'DRIVING',
    unitSystem: google.maps.UnitSystem.METRIC,
    region: 'CI'
  }, (response, status) => {
    if (status === 'OK') {
      const element = response.rows[0].elements[0];
      if (element.status === 'OK') {
        const distKm = element.distance.value / 1000;
        const pricePerKm = <?php echo (int)$prix_km; ?>;
        const baseFare = <?php echo (int)$frais_base; ?>;
        const price = Math.max(Math.round(baseFare + (distKm * pricePerKm)), baseFare);
        const etaMin = Math.round(element.duration.value / 60);
        document.getElementById('estimatedPrice').textContent = price;
        document.getElementById('estimatedTime').textContent = etaMin;
        document.getElementById('estimatedInfo').style.display = 'block';
      }
    }
  });
}
// Listeners
['departure','destination'].forEach(id => {
  document.addEventListener('change', e => {
    if (e.target.id === id) calculatePriceAndEta();
  });
});
</script>
