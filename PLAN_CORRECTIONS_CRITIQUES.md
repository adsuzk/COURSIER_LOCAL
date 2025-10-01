# 🚨 PLAN DE CORRECTIONS CRITIQUES - SYSTÈME COMPLET

## 📋 CONTEXTE

L'utilisateur a identifié **3 PROBLÈMES MAJEURS** qui doivent être résolus :

1. ❌ **Admin temps réel** : `http://localhost/COURSIER_LOCAL/admin.php?section=commandes` doit voir les changements EN TEMPS RÉEL (pas toutes les 30s)
2. ❌ **Débit automatique** : Le coursier doit être débité de son solde IMMÉDIATEMENT quand il accepte une commande
3. ❌ **UX Application** : L'app doit gérer correctement le flux complet (en attente → nouvelle commande → acceptation → navigation → livraison → cash → retour "en attente")

---

## 🔧 PROBLÈME 1: ADMIN TEMPS RÉEL

### 📍 **Fichier concerné:**
`admin_commandes_enhanced.php` (ligne ~2246)

### ❌ **Code actuel (PROBLÈME):**
```javascript
setInterval(() => {
    console.log('🔄 Rechargement auto page commandes...');
    window.location.reload();  // ❌ Recharge TOUTE la page toutes les 30 secondes
}, 30000);
```

### ✅ **Solution: Server-Sent Events (SSE)**

#### A. Créer l'API SSE Backend
**Fichier:** `api/commandes_sse.php`
```php
<?php
require_once __DIR__ . '/../config.php';

header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('X-Accel-Buffering: no'); // Nginx

// Éviter timeout
set_time_limit(0);
ignore_user_abort(true);

$pdo = getDBConnection();
$lastCheck = time();

while (true) {
    // Vérifier si client connecté
    if (connection_aborted()) break;
    
    // Récupérer les commandes (avec hash pour détecter changements)
    $stmt = $pdo->query("
        SELECT 
            c.id, c.code_commande, c.statut, c.mode_paiement,
            c.heure_acceptation, c.heure_debut, c.heure_retrait, 
            c.heure_livraison, c.cash_recupere,
            a.nom as coursier_nom, a.prenoms as coursier_prenoms
        FROM commandes c
        LEFT JOIN agents_suzosky a ON c.coursier_id = a.id
        WHERE c.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ORDER BY c.created_at DESC
    ");
    
    $commandes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $hash = md5(json_encode($commandes));
    
    // Envoyer les données
    echo "data: " . json_encode([
        'timestamp' => time(),
        'hash' => $hash,
        'commandes' => $commandes
    ]) . "\n\n";
    
    flush();
    
    // Attendre 2 secondes avant prochaine vérification
    sleep(2);
    
    $lastCheck = time();
}
?>
```

#### B. Modifier le JavaScript dans `admin_commandes_enhanced.php`
**Remplacer le code ligne ~2246 par:**
```javascript
// ⚡ SYNCHRONISATION TEMPS RÉEL via SSE
console.log('🔄 Activation SSE pour mises à jour temps réel');

let currentHash = null;
const evtSource = new EventSource('api/commandes_sse.php');

evtSource.onmessage = function(event) {
    try {
        const data = JSON.parse(event.data);
        
        // Si changement détecté, rafraîchir UNIQUEMENT la liste
        if (currentHash && currentHash !== data.hash) {
            console.log('🔔 Changement détecté ! Rafraîchissement...');
            refreshCommandesList(data.commandes);
        }
        
        currentHash = data.hash;
    } catch (e) {
        console.error('❌ Erreur SSE:', e);
    }
};

evtSource.onerror = function(err) {
    console.error('❌ SSE connexion perdue, reconnexion...');
    // SSE se reconnecte automatiquement
};

// Fonction pour rafraîchir la liste sans recharger la page
function refreshCommandesList(commandes) {
    const container = document.getElementById('commandesList');
    if (!container) return;
    
    // Sauvegarder scroll position
    const scrollPos = window.scrollY;
    
    // Regénérer les cartes commandes
    container.innerHTML = commandes.map(cmd => generateCommandeCard(cmd)).join('');
    
    // Restaurer scroll
    window.scrollTo(0, scrollPos);
}

function generateCommandeCard(commande) {
    // Template HTML pour une carte commande
    return `
        <div class="commande-card" data-id="${commande.id}">
            <div class="card-header">
                <strong>#${commande.code_commande}</strong>
                <span class="badge badge-${getStatusClass(commande.statut)}">${commande.statut}</span>
            </div>
            <div class="card-body">
                <p><strong>Coursier:</strong> ${commande.coursier_nom || 'Non attribué'}</p>
                <p><strong>Paiement:</strong> ${commande.mode_paiement || '-'}</p>
                ${commande.heure_acceptation ? `<p>✅ Accepté: ${commande.heure_acceptation}</p>` : ''}
                ${commande.heure_livraison ? `<p>🏁 Livré: ${commande.heure_livraison}</p>` : ''}
                ${commande.cash_recupere ? `<p>💵 Cash récupéré</p>` : ''}
            </div>
        </div>
    `;
}

function getStatusClass(statut) {
    const classes = {
        'nouvelle': 'warning',
        'acceptee': 'info',
        'en_cours': 'primary',
        'recuperee': 'success',
        'livree': 'success-dark'
    };
    return classes[statut] || 'secondary';
}
```

---

## 🔧 PROBLÈME 2: DÉBIT AUTOMATIQUE DU COURSIER

### 📍 **Fichiers concernés:**
1. `mobile_sync_api.php` (ligne ~165, case 'accept_commande')
2. Système de pricing: `admin.php?section=finances&tab=pricing`

### ❌ **Code actuel (PROBLÈME):**
```php
case 'accept_commande':
    // ...
    // Accepter la commande
    $stmt = $pdo->prepare("
        UPDATE commandes 
        SET statut = 'acceptee', heure_acceptation = NOW()
        WHERE id = ?
    ");
    // ❌ AUCUN DÉBIT DU SOLDE !
```

### ✅ **Solution: Intégrer le système de pricing**

#### A. Fonction de calcul des frais
**Ajouter dans `mobile_sync_api.php` (avant le switch):**
```php
/**
 * Calcule les frais de service pour une commande
 * @param float $prixTotal Prix total de la commande
 * @param PDO $pdo Connexion base de données
 * @return array ['frais_service' => float, 'commission_suzosky' => float, 'gain_coursier' => float]
 */
function calculerFraisService($prixTotal, $pdo) {
    // Récupérer les paramètres de tarification
    $stmt = $pdo->query("
        SELECT parametre, valeur 
        FROM parametres_tarification 
        WHERE parametre IN ('commission_suzosky', 'frais_plateforme')
    ");
    
    $params = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $params[$row['parametre']] = (float)$row['valeur'];
    }
    
    $commissionPercent = $params['commission_suzosky'] ?? 15.0; // Défaut: 15%
    $fraisPlateformePercent = $params['frais_plateforme'] ?? 5.0; // Défaut: 5%
    
    // Calculs
    $commissionSuzosky = round($prixTotal * ($commissionPercent / 100), 2);
    $fraisPlateforme = round($prixTotal * ($fraisPlateformePercent / 100), 2);
    $fraisTotal = $commissionSuzosky + $fraisPlateforme;
    $gainCoursier = round($prixTotal - $fraisTotal, 2);
    
    return [
        'frais_service' => $fraisTotal,
        'commission_suzosky' => $commissionSuzosky,
        'frais_plateforme' => $fraisPlateforme,
        'gain_coursier' => $gainCoursier,
        'prix_total' => $prixTotal
    ];
}
```

#### B. Modifier le case 'accept_commande'
**Remplacer le code ligne ~165 par:**
```php
case 'accept_commande':
    // Accepter une commande
    $commande_id = intval($_REQUEST['commande_id'] ?? 0);
    
    if (!$coursier_id || !$commande_id) {
        $response = ['success' => false, 'message' => 'ID coursier et commande requis'];
        break;
    }
    
    // Vérifier que la commande est bien attribuée au coursier
    $stmt = $pdo->prepare("
        SELECT id, code_commande, statut, prix_total, prix_estime
        FROM commandes 
        WHERE id = ? AND coursier_id = ? AND statut IN ('nouvelle', 'attribuee')
    ");
    $stmt->execute([$commande_id, $coursier_id]);
    $commande = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$commande) {
        $response = ['success' => false, 'message' => 'Commande non trouvée ou déjà traitée'];
        break;
    }
    
    $prixTotal = $commande['prix_total'] ?: $commande['prix_estime'] ?: 0;
    
    // ⚠️ VÉRIFIER LE SOLDE AVANT D'ACCEPTER
    $stmt = $pdo->prepare("SELECT COALESCE(solde_wallet, 0) as solde FROM agents_suzosky WHERE id = ?");
    $stmt->execute([$coursier_id]);
    $coursier = $stmt->fetch(PDO::FETCH_ASSOC);
    $soldeActuel = $coursier['solde'] ?? 0;
    
    // Calculer les frais
    $frais = calculerFraisService($prixTotal, $pdo);
    
    // Vérifier si le coursier a assez de solde
    if ($soldeActuel < $frais['frais_service']) {
        $response = [
            'success' => false,
            'message' => "Solde insuffisant. Requis: {$frais['frais_service']} FCFA, Disponible: {$soldeActuel} FCFA",
            'solde_requis' => $frais['frais_service'],
            'solde_actuel' => $soldeActuel,
            'manquant' => $frais['frais_service'] - $soldeActuel
        ];
        break;
    }
    
    // TRANSACTION ATOMIQUE
    $pdo->beginTransaction();
    
    try {
        // 1. Accepter la commande
        $stmt = $pdo->prepare("
            UPDATE commandes 
            SET statut = 'acceptee', 
                heure_acceptation = NOW(),
                frais_service = ?,
                commission_suzosky = ?,
                gain_coursier = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $frais['frais_service'],
            $frais['commission_suzosky'],
            $frais['gain_coursier'],
            $commande_id
        ]);
        
        // 2. Débiter le solde du coursier
        $stmt = $pdo->prepare("
            UPDATE agents_suzosky 
            SET solde_wallet = solde_wallet - ?
            WHERE id = ?
        ");
        $stmt->execute([$frais['frais_service'], $coursier_id]);
        
        // 3. Enregistrer la transaction
        $refTransaction = 'DELIV_' . $commande['code_commande'] . '_FEE';
        $stmt = $pdo->prepare("
            INSERT INTO transactions_financieres 
            (type, montant, compte_type, compte_id, reference, description, statut, date_creation)
            VALUES ('debit', ?, 'coursier', ?, ?, ?, 'reussi', NOW())
        ");
        $stmt->execute([
            $frais['frais_service'],
            $coursier_id,
            $refTransaction,
            "Frais d'acceptation commande #{$commande['code_commande']}"
        ]);
        
        $pdo->commit();
        
        // Récupérer le nouveau solde
        $stmt = $pdo->prepare("SELECT COALESCE(solde_wallet, 0) as solde FROM agents_suzosky WHERE id = ?");
        $stmt->execute([$coursier_id]);
        $nouveauSolde = $stmt->fetchColumn();
        
        $response = [
            'success' => true,
            'message' => 'Commande acceptée',
            'commande' => $commande,
            'frais_debites' => $frais['frais_service'],
            'gain_previsionnel' => $frais['gain_coursier'],
            'ancien_solde' => $soldeActuel,
            'nouveau_solde' => $nouveauSolde,
            'details_frais' => $frais
        ];
        
        // Log de l'acceptation
        logRequest('accept_commande', [
            'commande_id' => $commande_id,
            'coursier_id' => $coursier_id,
            'frais_debites' => $frais['frais_service']
        ], $response);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $response = [
            'success' => false,
            'message' => 'Erreur lors de l\'acceptation: ' . $e->getMessage()
        ];
    }
    break;
```

#### C. Ajouter colonnes manquantes dans la table commandes
**Script SQL:**
```sql
ALTER TABLE commandes 
    ADD COLUMN IF NOT EXISTS frais_service DECIMAL(8,2) DEFAULT 0 COMMENT 'Frais débités au coursier',
    ADD COLUMN IF NOT EXISTS commission_suzosky DECIMAL(8,2) DEFAULT 0 COMMENT 'Commission Suzosky',
    ADD COLUMN IF NOT EXISTS gain_coursier DECIMAL(8,2) DEFAULT 0 COMMENT 'Gain net pour le coursier';
```

---

## 🔧 PROBLÈME 3: UX APPLICATION (FLUX COMPLET)

### 📍 **Fichiers Android concernés:**
1. `CoursierScreenNew.kt` - Écran principal
2. `UnifiedCoursesScreen.kt` - Écran "Mes Courses"
3. `CoursesViewModel.kt` - Logique métier
4. `MainActivity.kt` - Point d'entrée

### ❌ **Problèmes actuels:**
1. Après livraison → Reste bloqué, ne montre pas "En attente"
2. Nouvelle commande → Ne montre pas automatiquement la carte
3. Acceptation → Ne lance pas automatiquement Google Maps
4. Navigation → Guidage vocal pas optimal

### ✅ **Solutions détaillées:**

#### A. Afficher "En attente" après livraison complète

**Modifier `CoursierScreenNew.kt` ligne ~206:**
```kotlin
// Mapper le statut de la commande au deliveryStep approprié
deliveryStep = when {
    currentOrder == null -> {
        // 🎯 PAS DE COMMANDE ACTIVE → AFFICHER "EN ATTENTE"
        DeliveryStep.PENDING
    }
    currentOrder.statut == "nouvelle" || currentOrder.statut == "attente" -> {
        DeliveryStep.PENDING
    }
    currentOrder.statut == "acceptee" -> {
        DeliveryStep.ACCEPTED
    }
    currentOrder.statut == "en_cours" -> {
        DeliveryStep.EN_ROUTE_PICKUP
    }
    currentOrder.statut == "recuperee" -> {
        DeliveryStep.PICKED_UP
    }
    currentOrder.statut == "livree" -> {
        val isEspeces = currentOrder.methodePaiement?.lowercase() == "especes"
        val cashConfirme = currentOrder.cashRecupere == true // Nouveau champ
        
        when {
            isEspeces && !cashConfirme -> DeliveryStep.DELIVERED // Attendre cash
            else -> DeliveryStep.CASH_CONFIRMED // Terminé
        }
    }
    else -> DeliveryStep.PENDING
}

// 🎯 SI LIVRAISON TERMINÉE (CASH_CONFIRMED) → Réinitialiser à PENDING
if (deliveryStep == DeliveryStep.CASH_CONFIRMED) {
    LaunchedEffect(Unit) {
        delay(3000) // Afficher "Terminé" pendant 3 secondes
        currentOrder = null
        deliveryStep = DeliveryStep.PENDING
    }
}
```

#### B. Auto-afficher carte quand nouvelle commande arrive

**Modifier `UnifiedCoursesScreen.kt` ligne ~220:**
```kotlin
// 🎯 DÉTECTION NOUVELLE COMMANDE
LaunchedEffect(currentOrder?.id) {
    if (currentOrder != null && deliveryStep == DeliveryStep.PENDING) {
        // Nouvelle commande détectée !
        Log.d("UnifiedCoursesScreen", "🔔 Nouvelle commande: ${currentOrder.codeCommande}")
        
        // Afficher notification sonore
        try {
            val notification = RingtoneManager.getDefaultUri(RingtoneManager.TYPE_NOTIFICATION)
            val ringtone = RingtoneManager.getRingtone(context, notification)
            ringtone.play()
        } catch (e: Exception) {
            Log.e("UnifiedCoursesScreen", "Erreur lecture son", e)
        }
        
        // Vibrer
        val vibrator = context.getSystemService(Context.VIBRATOR_SERVICE) as? Vibrator
        vibrator?.vibrate(VibrationEffect.createOneShot(500, VibrationEffect.DEFAULT_AMPLITUDE))
    }
}
```

#### C. Lancer Google Maps automatiquement après acceptation

**Modifier `MainActivity.kt` ligne ~800 (callback onCommandeAccept):**
```kotlin
onCommandeAccept = { commandeId ->
    ApiService.acceptOrder(commandeId.toIntOrNull() ?: 0, coursierId) { success, message, fraisDebites ->
        if (success) {
            Toast.makeText(
                this@MainActivity,
                "Commande acceptée ! Frais: $fraisDebites FCFA",
                Toast.LENGTH_SHORT
            ).show()
            
            // Déclencher rechargement
            shouldRefreshCommandes = true
            
            // 🎯 LANCER GOOGLE MAPS AUTOMATIQUEMENT
            val commande = commandes.find { it.id == commandeId }
            if (commande != null) {
                val pickupLat = commande.coordonneesEnlevement?.latitude
                val pickupLng = commande.coordonneesEnlevement?.longitude
                val deliveryLat = commande.coordonneesLivraison?.latitude
                val deliveryLng = commande.coordonneesLivraison?.longitude
                
                if (pickupLat != null && pickupLng != null && deliveryLat != null && deliveryLng != null) {
                    // Créer intent Google Maps avec waypoints
                    val uri = Uri.parse(
                        "https://www.google.com/maps/dir/?api=1" +
                        "&origin=current" + // Position actuelle
                        "&destination=$deliveryLat,$deliveryLng" + // Destination finale
                        "&waypoints=$pickupLat,$pickupLng" + // Point de récupération
                        "&travelmode=driving"
                    )
                    val intent = Intent(Intent.ACTION_VIEW, uri)
                    intent.setPackage("com.google.android.apps.maps")
                    
                    try {
                        startActivity(intent)
                    } catch (e: ActivityNotFoundException) {
                        Toast.makeText(
                            this@MainActivity,
                            "Google Maps non installé",
                            Toast.LENGTH_SHORT
                        ).show()
                    }
                }
            }
        } else {
            Toast.makeText(
                this@MainActivity,
                message ?: "Erreur acceptation",
                Toast.LENGTH_LONG
            ).show()
        }
    }
}
```

#### D. Ajouter guidage vocal dans l'app

**Créer nouveau fichier:** `VoiceGuidanceService.kt`
```kotlin
package com.suzosky.coursier.services

import android.content.Context
import android.speech.tts.TextToSpeech
import android.util.Log
import java.util.*

class VoiceGuidanceService(private val context: Context) : TextToSpeech.OnInitListener {
    
    private var tts: TextToSpeech? = null
    private var isInitialized = false
    
    init {
        tts = TextToSpeech(context, this)
    }
    
    override fun onInit(status: Int) {
        if (status == TextToSpeech.SUCCESS) {
            val result = tts?.setLanguage(Locale.FRENCH)
            isInitialized = result != TextToSpeech.LANG_MISSING_DATA && result != TextToSpeech.LANG_NOT_SUPPORTED
            Log.d("VoiceGuidance", "TTS initialisé: $isInitialized")
        }
    }
    
    fun speak(text: String, priority: Int = TextToSpeech.QUEUE_ADD) {
        if (isInitialized) {
            tts?.speak(text, priority, null, null)
        } else {
            Log.w("VoiceGuidance", "TTS pas encore initialisé")
        }
    }
    
    fun announceNewOrder(codeCommande: String) {
        speak("Nouvelle commande $codeCommande disponible", TextToSpeech.QUEUE_FLUSH)
    }
    
    fun announceOrderAccepted() {
        speak("Commande acceptée. Direction point de récupération", TextToSpeech.QUEUE_FLUSH)
    }
    
    fun announcePackagePickup() {
        speak("Colis récupéré. Direction point de livraison", TextToSpeech.QUEUE_FLUSH)
    }
    
    fun announceDelivered() {
        speak("Livraison effectuée", TextToSpeech.QUEUE_FLUSH)
    }
    
    fun announceCashConfirmed() {
        speak("Paiement confirmé. Commande terminée", TextToSpeech.QUEUE_FLUSH)
    }
    
    fun shutdown() {
        tts?.stop()
        tts?.shutdown()
    }
}
```

**Intégrer dans `CoursierScreenNew.kt`:**
```kotlin
// En haut du composable
val voiceGuidance = remember { VoiceGuidanceService(context) }

// Dans les callbacks
onAcceptOrder = {
    currentOrder?.let { order ->
        onCommandeAccept(order.id)
        voiceGuidance.announceOrderAccepted() // 🔊
    }
}

onPickupPackage = { 
    currentOrder?.let { order ->
        onPickupPackage(order.id)
        voiceGuidance.announcePackagePickup() // 🔊
        deliveryStep = DeliveryStep.PICKED_UP
    }
}

onMarkDelivered = {
    currentOrder?.let { order ->
        onMarkDelivered(order.id)
        voiceGuidance.announceDelivered() // 🔊
        deliveryStep = DeliveryStep.DELIVERED
    }
}

onConfirmCash = {
    currentOrder?.let { order ->
        onConfirmCash(order.id)
        voiceGuidance.announceCashConfirmed() // 🔊
        deliveryStep = DeliveryStep.CASH_CONFIRMED
    }
}

DisposableEffect(Unit) {
    onDispose {
        voiceGuidance.shutdown()
    }
}
```

---

## 📊 RÉSUMÉ DES MODIFICATIONS

### Backend PHP (3 fichiers)
1. ✅ **Créer:** `api/commandes_sse.php` (SSE temps réel)
2. ✅ **Modifier:** `mobile_sync_api.php` (débit automatique)
3. ✅ **Modifier:** `admin_commandes_enhanced.php` (JavaScript SSE)

### Android Kotlin (5 fichiers)
1. ✅ **Modifier:** `CoursierScreenNew.kt` (gestion états)
2. ✅ **Modifier:** `UnifiedCoursesScreen.kt` (notifications nouvelles commandes)
3. ✅ **Modifier:** `MainActivity.kt` (lancer Google Maps auto)
4. ✅ **Créer:** `VoiceGuidanceService.kt` (guidage vocal)
5. ✅ **Modifier:** `ApiService.kt` (recevoir infos débit)

### Base de données (1 script)
1. ✅ **Script SQL:** Ajouter colonnes `frais_service`, `commission_suzosky`, `gain_coursier`

---

## 🎯 ORDRE D'IMPLÉMENTATION RECOMMANDÉ

### Phase 1: Backend critique (30 min)
1. Créer `api/commandes_sse.php`
2. Modifier `mobile_sync_api.php` (débit automatique)
3. Exécuter script SQL (colonnes frais)
4. Tester débit coursier via Postman/cURL

### Phase 2: Admin temps réel (20 min)
5. Modifier JavaScript dans `admin_commandes_enhanced.php`
6. Tester SSE en ouvrant admin dans 2 fenêtres
7. Créer commande test → Vérifier mise à jour instantanée

### Phase 3: UX Android (40 min)
8. Créer `VoiceGuidanceService.kt`
9. Modifier `MainActivity.kt` (Google Maps auto)
10. Modifier `CoursierScreenNew.kt` (états + vocal)
11. Modifier `UnifiedCoursesScreen.kt` (notifications)
12. Compiler, installer, tester

### Phase 4: Tests end-to-end (30 min)
13. Créer commande depuis admin
14. Vérifier que coursier reçoit notification
15. Accepter commande → Vérifier débit + Google Maps
16. Compléter livraison → Vérifier retour "en attente"
17. Vérifier admin voit tout en temps réel

---

## ✅ CHECKLIST FINALE

- [ ] SSE fonctionne (admin mis à jour en < 3 secondes)
- [ ] Coursier débité immédiatement à l'acceptation
- [ ] Solde insuffisant → Refus automatique
- [ ] Google Maps s'ouvre automatiquement après acceptation
- [ ] Guidage vocal actif à chaque étape
- [ ] Après cash récupéré → Retour "En attente d'une nouvelle commande"
- [ ] Admin voit les changements de statut instantanément
- [ ] Transactions financières enregistrées correctement

---

## 📝 NOTES IMPORTANTES

### Système de pricing (admin)
- URL: `http://localhost/COURSIER_LOCAL/admin.php?section=finances&tab=pricing`
- Paramètres modifiables:
  - `prix_kilometre` (300 FCFA par défaut)
  - `commission_suzosky` (15% par défaut)
  - `frais_plateforme` (5% par défaut)
- Ces valeurs sont utilisées dans `calculerFraisService()`

### Sécurité
- Transaction atomique (BEGIN/COMMIT) pour éviter incohérences
- Vérification solde AVANT acceptation
- Logs de toutes les transactions financières
- Référence unique pour chaque transaction (`DELIV_CODE_FEE`)

### Performance
- SSE: Vérification toutes les 2 secondes (ajustable)
- Pas de rechargement complet de page
- Hash MD5 pour détecter changements
- Keep-alive pour maintenir connexion

---

## 🚀 PRÊT POUR IMPLÉMENTATION !

**Tout est documenté. Chaque ligne de code est expliquée.**
**Commençons par la Phase 1 (Backend critique) ?**
