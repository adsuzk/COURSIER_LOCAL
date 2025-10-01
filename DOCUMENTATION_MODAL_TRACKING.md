# 🎯 MODAL DE TRACKING - SYSTÈME SIMPLE ET FONCTIONNEL

**Date :** 1er octobre 2025  
**Objectif :** Ajout d'un modal de tracking simple avec boutons Live et Historique

---

## ✅ FONCTIONNALITÉS AJOUTÉES

### 1. **BOUTONS DE TRACKING**

#### Bouton "Tracking Live" 🔴
- **Quand** : Commande EN COURS (`attribuee`, `acceptee`, `en_cours`)
- **Condition** : Coursier assigné
- **Style** : Bleu brillant avec dégradé
- **Action** : Ouvre le modal en mode "live"

```html
<button class="btn-track live" onclick="openTrackingPopup(123, 'live')">
    <i class="fas fa-satellite"></i> Tracking Live
</button>
```

#### Bouton "Historique" 📊
- **Quand** : Commande TERMINÉE (`livree`, `annulee`)
- **Condition** : Coursier assigné
- **Style** : Bleu transparent avec bordure
- **Action** : Ouvre le modal en mode "history"

```html
<button class="btn-track history" onclick="openTrackingPopup(123, 'history')">
    <i class="fas fa-history"></i> Historique
</button>
```

#### Badge "Pas de coursier" ⚠️
- **Quand** : Pas de coursier assigné
- **Style** : Jaune avec icône d'alerte
- **Action** : Aucune (informatif seulement)

---

### 2. **MODAL DE TRACKING**

#### Design
- **Overlay** : Fond noir semi-transparent avec flou
- **Card** : Dégradé sombre avec bordure subtile
- **Animations** : FadeIn (overlay) + SlideUp (card)
- **Responsive** : S'adapte aux petits écrans

#### Contenu
Le modal affiche **3 cartes d'information** :

##### 📍 Carte Coursier
```
🏍️ COURSIER
━━━━━━━━━━━━━━━
Jean Dupont
📞 +225 07 12 34 56 78
🟢 En ligne
```

##### 🗺️ Carte Itinéraire
```
🗺️ ITINÉRAIRE
━━━━━━━━━━━━━━━
Départ : Cocody Angré 7e Tranche
Arrivée : Yopougon Siporex
```

##### ⏱️ Carte Temps & Prix
```
⏱️ TEMPS & PRIX
━━━━━━━━━━━━━━━
⏰ Début : 01/10/2025 à 14:30:00
✅ Fin : 01/10/2025 à 15:15:00
⏱️ Durée : 45 min
💰 Prix : 2 000 FCFA
```

**Si course en cours :**
```
⏱️ Durée : 25 min (en cours)
```

##### 🗺️ Section Carte (future)
```
[Espace réservé pour Google Maps]
Position: Lat 5.359951, Lng -4.008256
```

---

### 3. **API DE TRACKING**

**Endpoint :** `api/tracking_simple.php`

#### Paramètres
```
GET /api/tracking_simple.php?commande_id=123&mode=live
```

| Paramètre | Type | Requis | Description |
|-----------|------|--------|-------------|
| `commande_id` | int | ✅ Oui | ID de la commande |
| `mode` | string | ⚠️ Optionnel | `live` ou `history` |

#### Réponse Success
```json
{
  "success": true,
  "mode": "live",
  "commande": {
    "id": 123,
    "code_commande": "T265E67",
    "statut": "en_cours",
    "adresse_depart": "Cocody Angré 7e Tranche",
    "adresse_arrivee": "Yopougon Siporex",
    "prix_estime": "2000.00",
    "created_at": "2025-10-01 14:30:00",
    "updated_at": "2025-10-01 15:15:00"
  },
  "coursier": {
    "nom": "Jean Dupont",
    "telephone": "+225 07 12 34 56 78",
    "statut": "en_ligne"
  },
  "duree": {
    "debut": "01/10/2025 à 14:30:00",
    "fin": "01/10/2025 à 15:15:00",
    "duree_formatted": "45 min"
  },
  "position": {
    "lat": 5.359951,
    "lng": -4.008256
  },
  "timestamp": "2025-10-01 15:20:00"
}
```

#### Réponse Error
```json
{
  "success": false,
  "error": "Commande introuvable"
}
```

---

## 🎨 STYLES CSS

### Boutons de Tracking
```css
.btn-track {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 18px;
    border-radius: 12px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
}

.btn-track.live {
    background: linear-gradient(135deg, #2563eb, #60a5fa);
    color: #ffffff;
    box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
}

.btn-track.history {
    background: rgba(37, 99, 235, 0.18);
    color: #93c5fd;
    border: 1px solid rgba(37, 99, 235, 0.3);
}
```

### Modal
```css
.tracking-modal-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.75);
    backdrop-filter: blur(4px);
    z-index: 9999;
    animation: fadeIn 0.3s ease;
}

.tracking-modal-card {
    background: linear-gradient(135deg, #1e293b, #0f172a);
    border-radius: 20px;
    max-width: 900px;
    max-height: 85vh;
    animation: slideUp 0.3s ease;
}
```

---

## 💻 JAVASCRIPT

### Fonction openTrackingPopup()
```javascript
function openTrackingPopup(commandeId, mode) {
    // Afficher le modal
    modal.classList.add('active');
    
    // Loader
    modalContent.innerHTML = `<div>Loading...</div>`;
    
    // Charger via API
    fetch(`api/tracking_simple.php?commande_id=${commandeId}&mode=${mode}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                renderTrackingData(data, mode);
            }
        });
}
```

### Fonction closeTrackingModal()
```javascript
function closeTrackingModal(event) {
    modal.classList.remove('active');
}
```

### Fonction renderTrackingData()
```javascript
function renderTrackingData(data, mode) {
    // Construit le HTML avec les 3 cartes d'info
    // + section carte si position disponible
}
```

### Events
- **Clic sur overlay** : Ferme le modal
- **Touche Escape** : Ferme le modal
- **Clic sur X** : Ferme le modal

---

## 🧪 TESTS

### Test 1 : Bouton Tracking Live

1. **Aller sur** `admin.php?section=commandes`
2. **Trouver** une commande avec :
   - Statut : `en_cours` ou `acceptee`
   - Coursier assigné
3. **Vérifier** :
   - ✅ Bouton bleu "Tracking Live" visible
   - ✅ Icône satellite présente
4. **Cliquer** sur le bouton
5. **Vérifier** :
   - ✅ Modal s'ouvre avec animation
   - ✅ Titre : "📡 Tracking Live"
   - ✅ Sous-titre : "Commande #XXX"
   - ✅ Loader visible
6. **Attendre** chargement
7. **Vérifier** :
   - ✅ 3 cartes d'info affichées
   - ✅ Données correctes (nom coursier, adresses, durée)
   - ✅ Durée affiche "(en cours)"

### Test 2 : Bouton Historique

1. **Trouver** une commande avec :
   - Statut : `livree`
   - Coursier assigné
2. **Vérifier** :
   - ✅ Bouton transparent "Historique" visible
   - ✅ Icône historique présente
3. **Cliquer** sur le bouton
4. **Vérifier** :
   - ✅ Modal s'ouvre
   - ✅ Titre : "📊 Historique de course"
   - ✅ Durée affiche temps total (ex: "45 min")
   - ✅ Date/heure de fin affichée

### Test 3 : Badge sans coursier

1. **Trouver** une commande avec :
   - Pas de coursier assigné
2. **Vérifier** :
   - ✅ Badge jaune "Pas de coursier" visible
   - ✅ Aucun bouton tracking

### Test 4 : Fermeture Modal

1. **Ouvrir** un modal tracking
2. **Tester** fermeture :
   - ✅ Clic sur overlay (fond noir) → ferme
   - ✅ Clic sur bouton X → ferme
   - ✅ Touche Escape → ferme
   - ✅ Clic dans la carte blanche → ne ferme PAS

### Test 5 : Responsive

1. **Redimensionner** la fenêtre (mobile)
2. **Vérifier** :
   - ✅ Modal s'adapte (max 95vw)
   - ✅ Cartes en colonne sur petit écran
   - ✅ Scrollable si contenu trop grand

---

## 📊 CALCUL DE DURÉE

### Course EN COURS
```php
$debut = strtotime($commande['created_at']);
$maintenant = time();
$duree_secondes = $maintenant - $debut;
// → "25 min (en cours)"
```

### Course TERMINÉE
```php
$debut = strtotime($commande['created_at']);
$fin = strtotime($commande['updated_at']);
$duree_secondes = $fin - $debut;
// → "45 min"
// ou → "1h 15min"
```

---

## 🗺️ GÉOLOCALISATION (À VENIR)

Actuellement, la section carte affiche :
```
[Espace réservé pour Google Maps]
Position: Lat 5.359951, Lng -4.008256
```

**Pour activer Google Maps** (optionnel) :
1. Obtenir une clé API Google Maps
2. Ajouter le SDK dans le HTML
3. Remplacer le placeholder par `new google.maps.Map()`

---

## 📝 FICHIERS MODIFIÉS

### 1. admin_commandes_enhanced.php
**Lignes 418-437** : Ajout des boutons tracking
```php
<?php if ($hasCoursier && $isActive): ?>
    <button class="btn-track live" onclick="openTrackingPopup(<?= (int) $commande['id'] ?>, 'live')">
        <i class="fas fa-satellite"></i> Tracking Live
    </button>
<?php elseif ($hasCoursier && $isCompleted): ?>
    <button class="btn-track history" onclick="openTrackingPopup(<?= (int) $commande['id'] ?>, 'history')">
        <i class="fas fa-history"></i> Historique
    </button>
<?php endif; ?>
```

**Lignes 1378-1428** : Styles CSS boutons tracking
**Lignes 1775-1947** : Styles CSS modal
**Lignes 2086-2140** : HTML du modal
**Lignes 2143-2255** : JavaScript modal

### 2. api/tracking_simple.php ✅ NOUVEAU
**136 lignes** : API complète pour récupérer les données de tracking

---

## ✅ RÉSUMÉ

### Ce qui fonctionne maintenant

| Fonctionnalité | Statut | Description |
|----------------|--------|-------------|
| **Bouton Tracking Live** | ✅ | Commandes en cours avec coursier |
| **Bouton Historique** | ✅ | Commandes terminées avec coursier |
| **Badge "Pas de coursier"** | ✅ | Commandes sans coursier |
| **Modal responsive** | ✅ | S'adapte aux petits écrans |
| **API tracking** | ✅ | Récupère les données complètes |
| **Calcul durée** | ✅ | En cours ET terminée |
| **Info coursier** | ✅ | Nom, téléphone, statut |
| **Itinéraire** | ✅ | Adresses départ/arrivée |
| **Prix** | ✅ | Prix estimé affiché |
| **Animations** | ✅ | FadeIn + SlideUp |
| **Fermeture** | ✅ | Overlay, X, Escape |

### À implémenter plus tard (optionnel)

| Fonctionnalité | Priorité | Description |
|----------------|----------|-------------|
| **Carte Google Maps** | 🔶 Moyenne | Afficher trajet sur carte |
| **Rafraîchissement auto** | 🔶 Moyenne | Mettre à jour toutes les 10s |
| **Historique positions** | 🔷 Basse | Timeline des déplacements |
| **Notifications** | 🔷 Basse | Alertes changement statut |

---

## 🚀 UTILISATION

### Scénario 1 : Suivi en temps réel

1. Ouvrir `admin.php?section=commandes`
2. Trouver une course en cours
3. Cliquer sur "Tracking Live"
4. Voir les infos du coursier et la durée écoulée

### Scénario 2 : Consulter l'historique

1. Ouvrir `admin.php?section=commandes`
2. Trouver une course terminée
3. Cliquer sur "Historique"
4. Voir la durée totale et les horaires

---

**Statut :** ✅ **MODAL DE TRACKING FONCTIONNEL**  
**Prêt à tester !** 🎉
