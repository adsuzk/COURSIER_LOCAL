# 📘 DOCUMENTATION SYSTÈME SUZOSKY COURSIER - v2.0
**Date de mise à jour:** 1er Octobre 2025  
**Système:** Plateforme de livraison en temps réel avec paiement intégré

---

## 🎯 ARCHITECTURE GÉNÉRALE

### Stack Technique
- **Backend:** PHP 7.4+ / MySQL
- **Frontend:** HTML5 / CSS3 / JavaScript ES6+
- **Mobile:** Android (Kotlin + Jetpack Compose)
- **Temps réel:** Firebase Cloud Messaging (FCM)
- **Paiement:** CinetPay (intégré en modal)
- **Maps:** Google Maps API

---

## 📱 APPLICATION MOBILE COURSIER

### Écrans Principaux

#### 1. **Mes Courses** (`UnifiedCoursesScreen.kt`)
- **Intégration totale** : Carte Google Maps + infos + actions sur un seul écran
- **Affichage** :
  - Map full screen avec marqueurs (coursier, pickup, delivery)
  - Panel info en haut : numéro commande, distance, ETA
  - 2 numéros cliquables :
    * **Tél. Client** (icône verte 📞)
    * **Tél. Destinataire** (icône verte 📞)
  - Panel actions en bas : Accepter/Refuser ou Validation selon étape
- **Pas de modals** : tout intégré dans une seule vue

#### 2. **Portefeuille** (`ModernWalletScreen.kt`)
- **Design glassmorphism** moderne
- **Header** : Solde actuel avec gradient doré
- **Action unique** : Bouton "Recharger" (pleine largeur)
- **Recharge** :
  - Modal avec 6 montants prédéfinis
  - **Champ de saisie manuelle** avec clavier numérique
  - Modal de paiement Suzosky (branding complet)
- **Boutons fonctionnels** :
  - Historique (navigation)
  - Factures (navigation)
- **Stats** : Aujourd'hui, Ce mois

#### 3. **Support/Chat** (`ModernChatScreen.kt`)
- **Design WhatsApp-like**
- **Header** : Avatar Support doré + badge "en ligne"
- **Bulles de messages** :
  - Coursier → Bulles dorées à droite
  - Admin → Bulles translucides à gauche
  - Double check (✓✓) pour messages lus
- **État vide** : Quick replies cliquables
- **Input moderne** :
  - Bouton pièce jointe
  - Champ multi-lignes
  - Bouton envoi doré (actif si texte non vide)

#### 4. **Mon Profil** (`ModernProfileScreen.kt`)
- **Header** :
  - Avatar avec initiales
  - **Matricule coursier RÉEL** : `ID: CM20250003` (récupéré depuis `agents_suzosky.matricule`)
    - Format: CM + YYYYMMDD + numéro séquentiel
    - Couleur dorée (#D4A853)
    - Fallback vers `C{coursier_id}` si matricule vide en BDD
  - Badge de niveau dans cercle doré
  - Rating 5 étoiles
- **4 Stats Cards** :
  - 📦 Courses totales
  - 📈 Aujourd'hui
  - 💰 Gains totaux
  - 🏆 Rang actuel (niveau)
- **Badges & Réalisations** (scroll horizontal) :
  - 🏆 Débutant (débloqué)
  - 💎 Pro (débloqué)
  - ⚡ Rapide (débloqué)
  - ⭐ 5 étoiles (verrouillé)
  - 💎 VIP (verrouillé)
- **Barre de progression** : Niveau actuel → prochain
- **Actions** :
  - ⚙️ Paramètres
  - 🔔 Notifications
  - 🔒 Sécurité
  - ❓ Aide & Support
  - 🚪 Se déconnecter (rouge)
- **Infos** : Téléphone + Date d'inscription

#### 5. **Menu du Bas** (`BottomNavigationBar.kt`)
- **Icônes modernes** Filled/Outlined :
  - 🚚 Courses : `LocalShipping`
  - 💰 Wallet : `AccountBalanceWallet`
  - 💬 Support : `Chat`
  - 👤 Profil : `Person`
- **Animations** :
  - Changement couleur progressif
  - Agrandissement icône sélectionnée
  - Effet glow doré autour icône active
  - Background glassmorphism pour onglet actif
- **Couleurs** :
  - Sélectionné : Or Suzosky (#D4A853)
  - Non sélectionné : Blanc transparent
- **Texte en gras** quand sélectionné
- **Hauteur** : 80dp (textes visibles)

### Notifications FCM

#### Flux de Notification
1. **Serveur** → Envoie notification FCM au coursier
2. **App** → `FCMService.kt` reçoit la notification
3. **Affichage** :
   - Notification système Android
   - Dialog Accept/Refuse **dans l'app**
   - Son de notification (boucle jusqu'à action)
4. **Actions** :
   - Accepter → `order_response.php` (statut: accepted)
   - Refuser → `order_response.php` (statut: rejected)

#### APIs Notifications
- **POST** `/api/order_response.php` : Réponse coursier (accept/reject)
- **GET** `/api/get_coursier_data.php` : Récupère commandes avec GPS + phones

---

## 🌐 SITE WEB CLIENT (INDEX.PHP)

### Flux de Commande

#### **MODE ESPÈCES** 💵
1. Client remplit formulaire
2. Clic "Commander"
3. Soumission directe → Enregistrement BDD
4. Recherche coursier automatique
5. Suivi en temps réel sur l'index

#### **MODE PAIEMENT EN LIGNE** 💳 (NOUVEAU FLUX CORRIGÉ)
1. Client remplit formulaire
2. Clic "Commander"
3. **ÉTAPE 1** : Ouverture modal CinetPay AVANT enregistrement
   - API : `POST /api/initiate_payment_only.php`
   - Génère URL de paiement
   - Ouvre modal **dans l'index** (pas de redirection)
4. **ÉTAPE 2** : Client effectue le paiement dans le modal
   - Modal écoute les messages de CinetPay
   - Détecte succès/échec du paiement
5. **ÉTAPE 3** : SI paiement confirmé → Enregistrement commande
   - API : `POST /api/create_order_after_payment.php`
   - Enregistre la commande avec `statut_paiement='paye'`
   - Lance recherche coursier automatique
6. **ÉTAPE 4** : Suivi en temps réel sur l'index (sans quitter la page)

### APIs de Paiement

#### 1. **initiate_payment_only.php**
```
POST /api/initiate_payment_only.php

Paramètres:
- order_number: string (SZK{timestamp})
- amount: int (montant en FCFA)
- client_name: string
- client_phone: string
- client_email: string

Réponse SUCCESS:
{
  "success": true,
  "payment_url": "https://checkout.cinetpay.com/payment/...",
  "transaction_id": "SZK_123456",
  "message": "URL de paiement générée avec succès"
}

Réponse ERREUR:
{
  "success": false,
  "message": "Description de l'erreur"
}
```

**IMPORTANT** : Cette API génère **uniquement** l'URL de paiement. La commande n'est **PAS** enregistrée.

#### 2. **create_order_after_payment.php**
```
POST /api/create_order_after_payment.php

Paramètres (FormData):
- Tous les champs du formulaire de commande
- Mode paiement automatiquement défini à 'cinetpay'
- Statut paiement automatiquement défini à 'paye'

Réponse SUCCESS:
{
  "success": true,
  "message": "Commande enregistrée avec succès",
  "order_id": 123,
  "order_number": "SZK1234567890",
  "redirect_url": "/index.php?order_success=SZK1234567890"
}

Réponse ERREUR:
{
  "success": false,
  "message": "Description de l'erreur"
}
```

**IMPORTANT** : Cette API est appelée **uniquement** après confirmation du paiement.

### Modal de Paiement (Index Web)

La fonction `window.showPaymentModal(url, callback)` est définie dans `sections_index/js_payment.php`.

**Utilisation** :
```javascript
window.showPaymentModal(paymentUrl, function(success) {
    if (success) {
        console.log('✅ Paiement confirmé !');
        // Appeler create_order_after_payment.php
    } else {
        console.log('❌ Paiement échoué/annulé');
        // Permettre réessai
    }
});
```

**Fonctionnalités** :
- Modal full-screen avec iframe CinetPay
- Branding Suzosky (header doré, logo)
- Écoute des messages `postMessage` de CinetPay
- Détection auto: `status: 'success'`, `status: 'ACCEPTED'`, `payment_status: 'ACCEPTED'`, `code: '00'`
- Bouton fermer avec animation
- Loading indicator
- Responsive (mobile + desktop)

**Détection du paiement réussi** :
Le modal écoute les événements `postMessage` envoyés par CinetPay et détecte automatiquement:
- `data.status === 'success'`
- `data.status === 'ACCEPTED'`
- `data.payment_status === 'ACCEPTED'`
- `data.code === '00'`
- Messages texte contenant "success" ou "accepted"

---

## 🎙️ GUIDAGE VOCAL (Application Mobile)

### Système Text-to-Speech Intégré

Le guidage vocal est géré par **NavigationScreen** avec l'API Android Text-to-Speech (TTS).

**Fonctionnalités** :
- ✅ Instructions vocales en temps réel
- ✅ Calcul automatique de la distance restante
- ✅ Alertes de proximité ("Vous arrivez à destination")
- ✅ Annonces de changement de direction
- ✅ Fonctionne entièrement DANS l'application (pas de Google Maps externe)
- ✅ Bouton activation/désactivation dans Mes Courses

**Activation** :
- Bouton micro dans l'écran "Mes Courses" (en haut à droite)
- Visible uniquement pendant: ACCEPTED, EN_ROUTE_PICKUP, EN_ROUTE_DELIVERY
- Couleur verte = activé, gris = désactivé

**Avantages** :
- Pas besoin d'ouvrir Google Maps
- Le coursier reste dans l'app
- Instructions en français
- Économie de batterie
- Contrôle total sur les instructions

---

## 🔄 SYSTÈME D'ATTRIBUTION COURSIER

### Fichier : `attribution_intelligente.php`

#### Fonction Principale
```php
assignerCoursierIntelligent($commandeId)
```

#### Critères de Sélection
1. **Disponibilité** : Coursier `en_ligne` et `disponible`
2. **Proximité** : Distance GPS calculée (coordonnées pickup)
3. **Charge** : Nombre de commandes actives
4. **Performance** : Note moyenne du coursier

#### Score de Sélection
```php
score = (proximite * 0.4) + (charge * 0.3) + (note * 0.3)
```

Le coursier avec le **meilleur score** est assigné.

#### Notification Automatique
Après assignation → Envoi FCM au coursier sélectionné

---

## 📊 BASE DE DONNÉES

### Table : `commandes`

#### Champs Essentiels
```sql
id INT PRIMARY KEY AUTO_INCREMENT
client_id INT
client_nom VARCHAR(255)
client_telephone VARCHAR(20)
client_email VARCHAR(255)
adresse_depart TEXT
adresse_destination TEXT
latitude_retrait DECIMAL(10,8)
longitude_retrait DECIMAL(11,8)
latitude_livraison DECIMAL(10,8)
longitude_livraison DECIMAL(11,8)
distance_km DECIMAL(10,2)
prix_livraison DECIMAL(10,2)
telephone_destinataire VARCHAR(20)  -- ✅ NOUVEAU
nom_destinataire VARCHAR(255)
notes_speciales TEXT
mode_paiement ENUM('espece','cinetpay','om','momo')
statut_paiement ENUM('en_attente','paye','echoue')
numero_commande VARCHAR(50) UNIQUE
statut ENUM('nouvelle','attente','acceptee','en_cours','livree','annulee')
coursier_id INT (nullable)
date_creation DATETIME
date_acceptation DATETIME (nullable)
date_livraison DATETIME (nullable)
```

#### Statuts de Commande
- `nouvelle` : Commande créée, en attente d'assignation
- `attente` : En attente d'acceptation coursier
- `acceptee` : Coursier a accepté
- `en_cours` : Coursier en route
- `livree` : Commande livrée
- `annulee` : Commande annulée

---

## 🎨 DESIGN SYSTEM SUZOSKY

### Couleurs Principales
```css
--primary-gold: #D4A853;      /* Or principal */
--primary-dark: #1A1A2E;      /* Noir foncé */
--secondary-blue: #16213E;    /* Bleu foncé */
--accent-red: #E94560;        /* Rouge accent */
--success-green: #27AE60;     /* Vert succès */
--glass-bg: rgba(255,255,255,0.08); /* Glassmorphism */
```

### Effets Visuels
- **Glassmorphism** : `background: rgba(255,255,255,0.08)` + `backdrop-filter: blur(10px)`
- **Ombres** : `box-shadow: 0 8px 32px rgba(31, 38, 135, 0.37)`
- **Gradients** :
  - Doré : `linear-gradient(135deg, #D4A853 0%, #F4E4B8 50%, #D4A853 100%)`
  - Foncé : `linear-gradient(135deg, #1A1A2E 0%, #16213E 100%)`
- **Coins arrondis** : `border-radius: 16px` à `24dp`

---

## 🔐 SÉCURITÉ

### Sessions Client
```php
$_SESSION['client_id']
$_SESSION['client_telephone']
$_SESSION['client_nom']
$_SESSION['client_email']
```

### Authentification Coursier
- **Login** : `login_coursier.php`
- **Token** : FCM device token stocké en BDD
- **Validation** : Chaque requête vérifie la session coursier

### CinetPay
- **API Key** : Définie dans `/cinetpay/config.php`
- **Site ID** : Défini dans `/cinetpay/config.php`
- **Callback** : `/api/cinetpay_callback.php` (webhook)
- **Return URL** : Retour après paiement
- **Mode** : TEST ou PROD

---

## 📝 LOGS & DEBUGGING

### Fichiers de Log
- `debug_connectivity.log` : Logs de connectivité
- `mobile_sync_debug.log` : Logs sync mobile
- `mobile_connection_log.txt` : Logs connexions

### Log PHP
```php
error_log("[TAG] Message");
```

### Activation Debug
```php
ini_set('display_errors', 1);
error_reporting(E_ALL);
```

---

## 🚀 DÉPLOIEMENT

### Prérequis
- PHP 7.4+
- MySQL 5.7+
- Extension cURL activée
- Extension PDO activée
- Clés Firebase configurées
- Clés CinetPay configurées

### Configuration
1. **config.php** : Paramètres BDD + URLs
2. **cinetpay/config.php** : Clés CinetPay
3. **Firebase** : Fichiers JSON dans `/`
4. **Google Maps** : API Key dans index

### Permissions
```bash
chmod 755 /path/to/COURSIER_LOCAL
chmod 644 /path/to/COURSIER_LOCAL/*.php
chmod 777 /path/to/COURSIER_LOCAL/diagnostic_logs
```

---

## 📞 SUPPORT & MAINTENANCE

### Points de Contrôle
1. **FCM** : Vérifier tokens actifs (`fcm_token_security.php`)
2. **BDD** : Vérifier intégrité tables
3. **Logs** : Surveiller erreurs PHP
4. **CinetPay** : Vérifier transactions en attente

### Tests Réguliers
- ✅ Commande espèces complète
- ✅ Commande paiement en ligne complète
- ✅ Notification FCM coursier
- ✅ Attribution automatique coursier
- ✅ Suivi GPS en temps réel

---

## 🔄 CHANGELOG

### v2.1 - 1er Octobre 2025
**CORRECTIONS MAJEURES** :
- ✅ **Flux paiement CinetPay corrigé** : Modal s'ouvre AVANT enregistrement commande
  - ÉTAPE 1: Appel `initiate_payment_only.php` pour générer URL paiement
  - ÉTAPE 2: Ouverture modal avec iframe CinetPay (branding Suzosky)
  - ÉTAPE 3: Écoute postMessage pour détecter confirmation paiement
  - ÉTAPE 4: SI confirmé → Appel `create_order_after_payment.php`
  - ÉTAPE 5: Enregistrement commande + recherche coursier automatique
  
- ✅ **Guidage vocal INTERNE à l'application** (plus d'ouverture Google Maps)
  - Système Text-to-Speech Android intégré
  - Instructions vocales en temps réel pendant la navigation
  - Alertes de proximité et changements de direction
  - Bouton activation/désactivation dans Mes Courses
  
- ✅ **Matricule coursier affiché correctement**
  - Récupéré depuis `agents_suzosky.matricule`
  - Format: CM20250003 (au lieu de C{id} généré)
  - Visible dans l'écran Profil
  - Sauvegardé en SharedPreferences
  
- ✅ **Modal de paiement avec branding Suzosky**
  - Header doré avec logo Suzosky
  - Instructions claires en français
  - Bouton fermer avec animation
  - Loading indicator pendant chargement
  - Responsive (mobile + desktop)

**NOUVELLES APIs** :
- `POST /api/initiate_payment_only.php` : Génère URL paiement sans enregistrer
  - Paramètres: order_number, amount, client_name, client_phone, client_email
  - Retourne: payment_url, transaction_id
  
- `POST /api/create_order_after_payment.php` : Enregistre commande après paiement confirmé
  - Paramètres: tous champs formulaire (mappés automatiquement)
  - Mode paiement: automatiquement 'cinetpay'
  - Statut paiement: automatiquement 'paye'

**CONFIGURATION CINETPAY** :
```php
// Dans config.php
function getCinetPayConfig(): array {
    return [
        'apikey'     => '8338609805877a8eaac7eb6.01734650',
        'site_id'    => '219503',
        'secret_key' => '17153003105e7ca6606cc157.46703056',
        'endpoint'   => 'https://api-checkout.cinetpay.com/v2/payment'
    ];
}
```

**MODIFICATIONS JAVASCRIPT** :
- Fonction `window.showPaymentModal(url, callback)` créée dans `sections_index/js_payment.php`
- Flux de paiement modifié dans `sections_index/order_form.php`
- Mapping automatique des champs formulaire → API

### v2.0 - 30 Septembre 2025
**CORRECTIONS INITIALES** :
- ✅ **Ajout 2 numéros cliquables** dans Mes Courses (Client + Destinataire)
- ✅ **Clavier numérique** pour saisie montant recharge
- ✅ **Textes visibles** dans menu bas (hauteur 80dp)

**NOUVEAUX ÉCRANS** :
- `UnifiedCoursesScreen.kt` : Écran Mes Courses intégré sans modal
- `ModernWalletScreen.kt` : Portefeuille glassmorphism moderne
- `ModernChatScreen.kt` : Support/Chat type WhatsApp
- `ModernProfileScreen.kt` : Profil avec gamification et badges

**AMÉLIORATIONS UX** :
- Animations fluides partout
- Design system cohérent
- Pas de modals intrusifs
- Tout intégré dans l'index (pas de redirections)

---

## 📚 RESSOURCES

### Documentation API
- Firebase FCM : https://firebase.google.com/docs/cloud-messaging
- CinetPay : https://cinetpay.com/documentation
- Google Maps : https://developers.google.com/maps/documentation

### Outils de Développement
- Android Studio : https://developer.android.com/studio
- Postman : Tests d'APIs
- Chrome DevTools : Debug frontend

---

**© 2025 Suzosky - Tous droits réservés**
