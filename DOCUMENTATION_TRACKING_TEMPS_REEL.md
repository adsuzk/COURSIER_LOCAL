# 📍 SYSTÈME DE TRACKING TEMPS RÉEL - DOCUMENTATION
**Date:** 1er Octobre 2025  
**Version:** 2.2.0  
**Page:** `admin.php?section=commandes`

---

## 🎯 VUE D'ENSEMBLE

Le système de tracking temps réel permet aux administrateurs de suivre les coursiers et leurs commandes en direct depuis la page admin. Il comprend:

- **Boutons de tracking** sur chaque commande
- **Modal interactif** avec carte Google Maps
- **API temps réel** (`api/tracking_realtime.php`)
- **Mise à jour automatique** toutes les 10 secondes

---

## 📱 BOUTONS DE TRACKING

### États des boutons selon le statut de la commande

| Statut commande | Bouton affiché | Icône | Couleur | Action |
|-----------------|----------------|-------|---------|--------|
| **Sans coursier** | "Pas de coursier" | 🚫 ban | Gris (disabled) | Message d'erreur |
| **attribuee, acceptee, en_cours** | "Tracking Live" | 🛰️ satellite | Vert | Ouvre modal mode "live" |
| **livree, annulee** | "Historique" | 🕒 history | Orange | Ouvre modal mode "history" |
| **nouvelle, en_attente** | "En attente" | ⏰ clock | Bleu | Ouvre modal mode "pending" |

### Code des boutons (admin_commandes_enhanced.php, lignes 325-365)

```php
$hasCoursier = !empty($commande['coursier_id']);
$isActive = in_array($statut, ['attribuee', 'acceptee', 'en_cours'], true);
$isCompleted = in_array($statut, ['livree', 'annulee'], true);

if (!$hasCoursier) {
    $trackLabel = 'Pas de coursier';
    $trackAction = 'showTrackingUnavailable(); return false;';
    $trackDisabled = 'disabled';
} elseif ($isActive) {
    $trackLabel = 'Tracking Live';
    $trackIcon = 'satellite';
    $trackTitle = 'Suivi en temps réel';
    $trackAction = "openTrackingModal({$commande['id']}, {$commande['coursier_id']}, 'live');";
} elseif ($isCompleted) {
    $trackLabel = 'Historique';
    $trackIcon = 'history';
    $trackTitle = 'Consulter la course';
    $trackAction = "openTrackingModal({$commande['id']}, {$commande['coursier_id']}, 'history');";
}
```

---

## 🗺️ MODAL DE TRACKING

### Structure du modal (lignes 1774-1835)

Le modal comprend **3 onglets** :

#### 1. **Vue d'ensemble** (onglet par défaut)
- **Informations coursier**
  - Nom complet
  - Statut connexion (en ligne/hors ligne)
  - Numéro de téléphone cliquable
  - Dernière position connue
  
- **File d'attente**
  - Position actuelle dans la file
  - Nombre total de commandes
  - Prochaine commande à traiter

- **Estimations**
  - ETA pickup (temps estimé jusqu'au point de départ)
  - ETA delivery (temps estimé jusqu'à la livraison)
  - Distance totale

- **Détails commande**
  - Code commande
  - Adresse départ
  - Adresse arrivée
  - Prix
  - Mode de paiement

#### 2. **Carte** (Google Maps)
- **Marqueurs affichés** :
  - 📍 **Vert** : Point de départ (pickup)
  - 📍 **Rouge** : Point d'arrivée (dropoff)
  - 🚗 **Bleu** : Position actuelle du coursier (si en cours)
  
- **Fonctionnalités** :
  - Zoom automatique pour afficher tous les marqueurs
  - Refresh toutes les 10 secondes
  - Affichage des infobulles au clic

#### 3. **Timeline** (Historique)
- Liste chronologique des événements :
  - Création commande
  - Attribution coursier
  - Acceptation
  - Départ vers pickup
  - Arrivée pickup
  - Départ vers delivery
  - Livraison
  - Événements personnalisés

---

## 🔧 FONCTIONS JAVASCRIPT

### Fonction principale : `openTrackingModal()`

**Fichier:** `admin_commandes_enhanced.php` (ligne 2399)

```javascript
function openTrackingModal(commandeId, coursierId, mode) {
    currentCommandeId = commandeId;
    trackingModal = document.getElementById('trackingModal');
    trackingModal.classList.add('visible');
    document.body.classList.add('modal-open');
    
    // Mise à jour titre et sous-titre
    document.getElementById('trackingTitle').textContent = 'Commande #' + commandeId;
    document.getElementById('trackingSubtitle').textContent = 
        mode === 'history' ? 'Historique de la course' : 'Suivi en temps réel';
    
    // Chargement des données
    fetchTrackingData(true);
    startTrackingInterval(trackingIntervalMs); // 10 secondes
}
```

### Fonction de fermeture : `closeTrackingModal()`

**Ligne 2434**

```javascript
function closeTrackingModal() {
    trackingModal.classList.remove('visible');
    document.body.classList.remove('modal-open');
    if (trackingTimer) {
        clearInterval(trackingTimer);
        trackingTimer = null;
    }
}
```

### Fonction de changement d'onglet : `switchTrackingTab()`

**Ligne 2445**

```javascript
function switchTrackingTab(tab) {
    // Active l'onglet sélectionné
    document.querySelectorAll('.modal-tabs button').forEach(btn => {
        btn.classList.toggle('active', btn.dataset.tab === tab);
    });
    document.querySelectorAll('.modal-tab').forEach(content => {
        content.classList.toggle('active', content.id === 'tab-' + tab);
    });
}
```

### Fonction de rafraîchissement des données : `fetchTrackingData()`

**Ligne 2475**

```javascript
function fetchTrackingData(forceFetch = false) {
    if (!currentCommandeId) return;
    
    fetch(`api/tracking_realtime.php?commande_id=${currentCommandeId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateTrackingOverview(data);
                updateTrackingMap(data);
                renderTimeline(data.timeline || []);
            }
        })
        .catch(error => {
            console.error('Erreur fetch tracking:', error);
        });
}
```

### Fonction de mise à jour de la carte : `updateTrackingMap()`

**Ligne 2655**

```javascript
function updateTrackingMap(data) {
    ensureTrackingMap(() => {
        const bounds = new google.maps.LatLngBounds();
        
        // Marqueur point de départ
        if (data.pickup?.lat && data.pickup?.lng) {
            if (!trackingPickupMarker) {
                trackingPickupMarker = new google.maps.Marker({
                    map: trackingMapInstance,
                    position: { lat: data.pickup.lat, lng: data.pickup.lng },
                    icon: {
                        url: 'https://maps.google.com/mapfiles/ms/icons/green-dot.png',
                        scaledSize: new google.maps.Size(32, 32)
                    },
                    title: 'Point de départ'
                });
            }
            bounds.extend(trackingPickupMarker.getPosition());
        }
        
        // Marqueur point d'arrivée
        if (data.dropoff?.lat && data.dropoff?.lng) {
            if (!trackingDropoffMarker) {
                trackingDropoffMarker = new google.maps.Marker({
                    map: trackingMapInstance,
                    position: { lat: data.dropoff.lat, lng: data.dropoff.lng },
                    icon: {
                        url: 'https://maps.google.com/mapfiles/ms/icons/red-dot.png',
                        scaledSize: new google.maps.Size(32, 32)
                    },
                    title: 'Destination'
                });
            }
            bounds.extend(trackingDropoffMarker.getPosition());
        }
        
        // Marqueur position coursier
        if (data.position_coursier?.lat && data.position_coursier?.lng) {
            if (!trackingCourierMarker) {
                trackingCourierMarker = new google.maps.Marker({
                    map: trackingMapInstance,
                    position: { 
                        lat: data.position_coursier.lat, 
                        lng: data.position_coursier.lng 
                    },
                    icon: {
                        url: 'https://maps.google.com/mapfiles/ms/icons/blue-dot.png',
                        scaledSize: new google.maps.Size(40, 40)
                    },
                    title: 'Coursier'
                });
            } else {
                // Mise à jour position si le marqueur existe déjà
                trackingCourierMarker.setPosition({
                    lat: data.position_coursier.lat,
                    lng: data.position_coursier.lng
                });
            }
            bounds.extend(trackingCourierMarker.getPosition());
        }
        
        // Ajuster la vue pour afficher tous les marqueurs
        if (!bounds.isEmpty()) {
            trackingMapInstance.fitBounds(bounds);
        }
    });
}
```

---

## 🌐 API BACKEND

### Endpoint : `api/tracking_realtime.php`

**Méthode:** GET  
**Paramètre:** `commande_id` (obligatoire)

**Exemple d'appel:**
```
GET /api/tracking_realtime.php?commande_id=142
```

**Réponse JSON:**
```json
{
    "success": true,
    "commande": {
        "id": 142,
        "code_commande": "TEST20251001085525",
        "statut": "attribuee",
        "adresse_depart": "Cocody Angré 7ème Tranche",
        "adresse_arrivee": "Plateau Cité Administrative",
        "prix_estime": 1500,
        "created_at": "2025-10-01 06:55:25"
    },
    "coursier": {
        "id": 5,
        "nom": "ZALLE Ismael",
        "telephone": "+225 07 XX XX XX XX",
        "matricule": "CM20250003",
        "statut_connexion": "en_ligne",
        "last_seen": "2025-10-01 07:30:00"
    },
    "position_coursier": {
        "lat": 5.359951,
        "lng": -4.008256,
        "timestamp": "2025-10-01 07:29:45",
        "status": "en_deplacement"
    },
    "pickup": {
        "lat": 5.365,
        "lng": -4.012,
        "address": "Cocody Angré 7ème Tranche"
    },
    "dropoff": {
        "lat": 5.320,
        "lng": -4.025,
        "address": "Plateau Cité Administrative"
    },
    "estimations": {
        "pickup_eta_minutes": 12,
        "delivery_eta_minutes": 25,
        "total_distance_km": 8.5
    },
    "queue": {
        "position": 1,
        "total": 3,
        "orders": [
            {
                "id": 142,
                "code_commande": "TEST20251001085525",
                "is_current": true
            },
            {
                "id": 143,
                "code_commande": "TEST20251001090012",
                "is_current": false
            }
        ]
    },
    "timeline": [
        {
            "label": "Commande créée",
            "timestamp": "2025-10-01 06:55:25",
            "formatted": "01/10/2025 06:55",
            "status": "completed",
            "icon": "✅"
        },
        {
            "label": "Coursier assigné",
            "description": "ZALLE Ismael",
            "timestamp": "2025-10-01 06:55:26",
            "formatted": "01/10/2025 06:55",
            "status": "completed",
            "icon": "🚗"
        },
        {
            "label": "En cours",
            "timestamp": "2025-10-01 07:00:00",
            "formatted": "01/10/2025 07:00",
            "status": "active",
            "icon": "🏃"
        }
    ]
}
```

---

## 🗺️ CONFIGURATION GOOGLE MAPS

### Clé API

La clé Google Maps est définie dans `config.php` :

```php
define('GOOGLE_MAPS_API_KEY', 'AIzaSyBFw0Qbyq9zTFTd-tUY6dZWTgaQzuU17R8');
```

**Alternative avec variable d'environnement:**
```php
define('GOOGLE_MAPS_API_KEY', getenv('GOOGLE_MAPS_API_KEY') ?: 'VOTRE_CLE_PAR_DEFAUT');
```

### Chargement dynamique de l'API

**Fichier:** `admin_commandes_enhanced.php` (ligne 2590)

```javascript
function loadGoogleMapsApi(callback) {
    if (typeof google !== 'undefined' && google.maps) {
        callback();
        return;
    }
    
    if (!window.GOOGLE_MAPS_API_KEY) {
        console.error('Clé Google Maps non configurée');
        return;
    }
    
    const script = document.createElement('script');
    script.src = `https://maps.googleapis.com/maps/api/js?key=${encodeURIComponent(window.GOOGLE_MAPS_API_KEY)}&v=weekly&libraries=places`;
    script.async = true;
    script.defer = true;
    script.onload = callback;
    script.onerror = () => {
        console.error('Erreur chargement Google Maps API');
    };
    document.head.appendChild(script);
}
```

---

## 🧪 TESTS ET VALIDATION

### Script de test fourni

**Fichier:** `test_tracking_admin.html`

Ouvrir dans le navigateur :
```
https://localhost/COURSIER_LOCAL/test_tracking_admin.html
```

**Tests effectués:**
1. ✅ Vérification base de données (commandes avec coursier)
2. ✅ Test API tracking_realtime.php
3. ✅ Vérification clé Google Maps
4. ✅ Validation interface admin (boutons, modal, fonctions JS)

### Scripts PHP de support

1. **`test_tracking_db.php`** - Vérifie les données en base
2. **`test_tracking_config.php`** - Vérifie la configuration Google Maps

---

## 📊 STATISTIQUES ET MÉTRIQUES

### Intervalle de mise à jour

- **Modal ouvert:** Rafraîchissement toutes les **10 secondes**
- **Variable:** `trackingIntervalMs = 10000` (ligne 1891)

### Performance

- Requête SQL optimisée avec LEFT JOIN
- Cache des marqueurs Google Maps (pas de recréation)
- Mise à jour uniquement des positions changées

---

## 🎨 STYLES CSS

### Classes principales

```css
.btn-track {
    /* Bouton de tracking */
}

.btn-track.live {
    /* Bouton tracking en direct (vert) */
}

.btn-track.history {
    /* Bouton historique (orange) */
}

.tracking-modal {
    /* Modal plein écran */
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    z-index: 10000;
}

#trackingMap {
    /* Conteneur carte Google Maps */
    width: 100%;
    height: 500px;
}
```

---

## 🚀 UTILISATION

### Pour l'administrateur

1. Ouvrir `admin.php?section=commandes`
2. Cliquer sur le bouton de tracking d'une commande :
   - **"Tracking Live"** pour commandes en cours
   - **"Historique"** pour commandes terminées
3. Dans le modal :
   - Onglet **"Vue d'ensemble"** : Infos complètes
   - Onglet **"Carte"** : Visualisation GPS
   - Onglet **"Timeline"** : Historique événements
4. Le modal se met à jour automatiquement toutes les 10 secondes
5. Fermer avec le bouton ❌ ou la touche Échap

---

## 🐛 DÉBOGAGE

### Vérifier que les boutons s'affichent

**Console navigateur:**
```javascript
// Vérifier si les commandes ont un coursier
document.querySelectorAll('.btn-track').forEach(btn => {
    console.log(btn.textContent, btn.disabled);
});
```

### Vérifier l'API

**Test direct:**
```
GET https://localhost/COURSIER_LOCAL/api/tracking_realtime.php?commande_id=142
```

### Vérifier Google Maps

**Console navigateur:**
```javascript
console.log('Clé Google Maps:', window.GOOGLE_MAPS_API_KEY);
console.log('Google Maps chargé:', typeof google !== 'undefined' && google.maps);
```

### Logs PHP

Ajouter dans `api/tracking_realtime.php` :
```php
error_log("Tracking request for commande_id: " . $_GET['commande_id']);
```

---

## 📝 NOTES IMPORTANTES

1. **Statut `attribuee`** : C'est bien `attribuee` (pas `assignee`) dans la base de données
2. **Clé Google Maps** : Vérifier que la clé est valide et a les API activées :
   - Maps JavaScript API
   - Places API (optionnel)
3. **HTTPS** : Google Maps nécessite HTTPS en production
4. **Positions GPS** : Le coursier doit avoir l'app mobile avec géolocalisation active

---

## 🔗 FICHIERS LIÉS

- `admin_commandes_enhanced.php` - Interface admin avec modal
- `api/tracking_realtime.php` - API backend données temps réel
- `config.php` - Configuration (clé Google Maps)
- `test_tracking_admin.html` - Script de test complet
- `test_tracking_db.php` - Test base de données
- `test_tracking_config.php` - Test configuration

---

**Documentation créée le:** 1er Octobre 2025 - 07:45  
**Version système:** 2.2.0  
**Statut:** ✅ PRODUCTION - Système complet et fonctionnel
