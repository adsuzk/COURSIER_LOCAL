<?php
// sections_order_form/price_eta.php - Affichage du prix estimé et ETA
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
        const price = Math.round(distKm * 300);
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
