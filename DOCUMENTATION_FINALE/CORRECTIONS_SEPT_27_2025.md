# Corrections du 27 Septembre 2025

## 1. Chargement Prioritaire de Google Maps API

### Problème
La carte et l'autocomplétion Google ne se chargeaient pas immédiatement à l'ouverture de l'index.

### Solution Implémentée
- **Chargement précoce** : Script Google Maps unique dans le `<head>` via `index.php` (callback `initGoogleMapsEarly`)
- **Initialisation sécurisée** : `initMap()` délègue à `initializeMapAfterLoad()` (anti double init, gestion DOM ready)
- **Gestion d'attente** : Autocomplétion déclenchée dès disponibilité de `setupAutocomplete()` (retries contrôlés)
- **Suppression des doublons** : Retrait du chargement tardif et uniformisation de la clé API (env → constante → fallback)

### Fichiers Modifiés
- `index.php` : Injection unique de l'API dans le head + calcul dynamique de la clé
- `sections_index/js_google_maps.php` : Réécriture complète de l'initialisation (anti doublons, guard DOM, retry)
- `sections_index/js_initialization.php` : Préchargement d'assets via `ROOT_PATH` pour éviter les 404
- `sections_index/map.php` : (historique) amélioration de la gestion d'attente pour l'autocomplétion

## 2. Correction Erreur 404 lors de la Commande

### Problème
Lors de la soumission d'une commande avec paiement espèce, l'erreur suivante apparaissait dans la timeline :
```
404 Not Found - The requested URL was not found on this server
```
Console : `Failed to load resource: the server responded with a status of 404 (Not Found)`

### Cause
L'API `submitOrder()` utilisait un chemin absolu `/api/submit_order.php` au lieu d'utiliser le `ROOT_PATH` configuré pour les sous-dossiers.

### Solution Implémentée
- **Correction du chemin API** : Utilisation de `(window.ROOT_PATH || '') + '/api/submit_order.php'` dans `submitOrder()`
- **Compatibilité sous-dossiers** : Assure le fonctionnement correct même en développement local avec `localhost/COURSIER_LOCAL/`

### Fichiers Modifiés
- `sections_index/order_form.php` : Correction du chemin API dans la fonction `submitOrder()`

## 3. Améliorations de Performance

### Optimisations Ajoutées
- **Chargement asynchrone** : Google Maps API chargé avec `async defer`
- **Callback centralisé** : Gestion unifiée de l'initialisation Google Maps
- **Gestion d'erreurs** : Meilleure détection des échecs de chargement API
- **Retry automatique** : Tentatives multiples pour l'initialisation des cartes

## 4. Configuration et Compatibilité

### Environnements Supportés
- ✅ **Développement Local** : `localhost/COURSIER_LOCAL`
- ✅ **Production HTTPS** : Domaines avec certificats SSL
- ✅ **Sous-dossiers** : Installation dans des répertoires personnalisés

### Variables d'Environnement
```javascript
window.ROOT_PATH        // Chemin de base de l'application
window.googleMapsReady  // Statut de l'API Google Maps
window.googleMapsInitialized // Statut de l'initialisation complète
```

## 5. Tests et Validation

### Tests Effectués
- ✅ Chargement de l'index avec carte visible immédiatement
- ✅ Autocomplétion fonctionnelle dès la saisie
- ✅ Soumission de commande espèce sans erreur 404
- ✅ Timeline de suivi correctement alimentée

### Commandes de Test
```bash
# Test de syntaxe PHP
C:/xampp/php/php.exe -l api/submit_order.php

# Test simulation login
C:/xampp/php/php.exe -r "$_POST=[]; parse_str('email=test@test.com&password=abcde', $_POST); $_GET['action']='login'; $_SERVER['REQUEST_METHOD']='POST'; $_SERVER['HTTP_HOST']='localhost'; include 'api/auth.php';"
```

## 6. Notes Techniques

### API Google Maps
- **Clé API** : Résolution prioritaire `GOOGLE_MAPS_API_KEY` (env/const) → fallback `AIzaSyBjUgj9KM0SNj847a_bIsf6chWp9L8Hr1A`
- **Bibliothèques** : `places,geometry`
- **Restrictions** : Côte d'Ivoire (CI)

### Gestion d'Erreurs
- Timeout de 10 secondes pour détecter les échecs de chargement
- Messages d'erreur détaillés dans la console
- Retry automatique en cas d'indisponibilité temporaire

### Performance
- Chargement parallèle de l'API Google Maps et du contenu de la page
- Initialisation différée si les éléments DOM ne sont pas encore disponibles
- Cache browser optimisé pour les ressources statiques

---

**Date de mise à jour** : 27 septembre 2025  
**Version** : 2.0.1  
**Statut** : ✅ Validé et testé