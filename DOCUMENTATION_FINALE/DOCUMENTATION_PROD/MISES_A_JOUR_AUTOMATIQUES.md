# Mises à jour automatiques de l’application Android (APK)

Ce document décrit, de façon claire et technique, le fonctionnement complet du système de mise à jour Android côté Suzosky: dépôt des APK, lecture des métadonnées JSON, synchronisation UI + API en temps réel, et téléchargement sécurisé.

---

## 🎯 Objectif

- Seule action manuelle: téléverser l’APK et, si possible, un JSON de métadonnées associé.
- Tout le reste est automatique: détection + propagation vers l’interface Admin (App Updates, Applications) et vers l’API consultée par les appareils.

---

## 🧩 Architecture et fichiers clés

- Admin UI
  - `admin/applications.php`: page pour téléverser les APK (+ JSON optionnel) et lister les applis.
  - `admin/app_updates.php`: page “Mises à jour automatiques” avec sous-menu:
    - Vue Mise à jour: cartes APK, version courante, appareils, stats.
    - Vue Télémétrie: KPIs globaux, tableur structuré des appareils, panneau de détails par appareil (localisation, crashes, sessions, événements) et signalement.
  - `admin/assets/js/auto_refresh_app_updates.js`: rafraîchissement périodique (5s) des blocs critiques.
- API
  - `api/app_updates.php`: endpoint REST consulté par les appareils pour savoir s’il existe une mise à jour.
- Métadonnées et configuration
  - `admin/uploads/`: répertoire de dépôt des APK et du pointeur JSON `latest_apk.json`.
  - `data/app_versions.json`: source de vérité globale (consommée par l’API et l’Admin).
  - `lib/version_helpers.php`: helpers partagés pour charger/écrire la config et appliquer la dernière APK.
- Téléchargement
  - `admin/download_apk.php`: sert les APK avec les bons headers (sécurisé, fiable).
  
AJAX Télémétrie
- `admin/ajax_telemetry.php`: détail appareil (GET) et signalement (POST)

---

## 🔁 Flux de bout en bout

1) Admin → Applications → Téléverser:
   - Fichiers:
     - Obligatoire: `*.apk`
     - Optionnel: `*.json` (métadonnées)
   - Le serveur enregistre l’APK dans `admin/uploads/` et met à jour `admin/uploads/latest_apk.json` en conservant la version précédente dans `previous`.
   - Si un JSON est fourni, il est lu et fusionné (version_code, version_name, changelog, force_update, min_supported_version).
   - La configuration globale `data/app_versions.json` est immédiatement mise à jour via `vu_overlay_with_latest_upload()`.

2) Admin → Mises à jour automatiques:
   - Affiche instantanément la dernière version (grâce au rafraîchissement automatique et la synchro persistée).
   - Liens de téléchargement pointent vers `download_apk.php`.

3) Appareils → API `/api/app_updates.php`:
   - Reçoivent la `latest_version` (version_code, version_name, apk_url, size, etc.).
   - Si `version_code` de l’appareil est inférieur, alors `update_available: true` + `download_url` fourni.

---

## 📦 Formats JSON de métadonnées acceptés

Deux formats sont pris en charge lors de l’upload (fichier champ `apk_meta`):

1) Android Gradle output metadata (output-metadata.json)
```json
{
  "elements": [
    {
      "type": "SINGLE",
      "versionCode": 42,
      "versionName": "2.1.0"
    }
  ]
}
```

2) JSON simple personnalisé
```json
{
  "version_code": 42,
  "version_name": "2.1.0",
  "changelog": [
    "Optimisation des performances",
    "Corrections de bugs",
    "Nouveaux écrans de suivi"
  ],
  "force_update": false,
  "min_supported_version": 35
}
```

Si le JSON n’est pas fourni, le système essaie une détection automatique complémentaire (`admin/update_apk_metadata.php`).

---

## 🧠 Règles d’overlay (fusion) des métadonnées

Fonction: `vu_overlay_with_latest_upload(&$config, $persist)`
- Lit `admin/uploads/latest_apk.json` et met à jour `config['current_version']`:
  - `version_code`, `version_name`, `apk_url` (via `download_apk.php?file=...`), `apk_size`, `release_date`.
  - Si fournis dans `latest_apk.json`: `force_update`, `min_supported_version`, `changelog` (sinon garde la valeur existante ou applique un défaut).
- Si `$persist === true`, écrit immédiatement `data/app_versions.json`.

Fonction: `vu_update_latest_meta_with_previous($filename, $metaExtras)`
- Crée ou met à jour `admin/uploads/latest_apk.json` en
  - ajoutant `file`, `uploaded_at`, `apk_size`
  - copiant la version précédente dans `previous`
  - fusionnant les champs `metaExtras` issus du JSON uploadé (ou d’une détection automatique).

---

## 🔌 API de mises à jour

Endpoint: `GET /api/app_updates.php`

Paramètres de requête:
- `device_id` (string) – requis
- `version_code` (int) – code de version actuellement installé sur l’appareil

Réponse (exemple):
```json
{
  "update_available": true,
  "force_update": false,
  "latest_version": {
    "version_code": 42,
    "version_name": "2.1.0",
    "apk_url": "/admin/download_apk.php?file=suzosky-coursier-20250919-153012.apk",
    "apk_size": 18610510,
    "release_date": "2025-09-19 15:30:12",
    "force_update": false,
    "min_supported_version": 35,
    "changelog": ["Optimisation des performances", "Corrections de bugs"]
  },
  "auto_install": true,
  "check_interval": 3600,
  "download_url": "https://example.com/admin/download_apk.php?file=suzosky-coursier-20250919-153012.apk"
}
```

Notes:
- `download_url` n’est présent que si `update_available` est `true`.
- Le champ `devices` de `data/app_versions.json` est mis à jour automatiquement: `last_check`, `update_status`, etc., via les appels `GET/POST`.

---

## 🖥️ Interface Admin (UX)

- Applications (`admin/applications.php`)
  - Formulaire: fichier APK (obligatoire) + fichier JSON (optionnel).
  - Après upload, un encart indique la “Dernière APK” avec taille et bouton Télécharger.
- Mises à jour automatiques (`admin/app_updates.php`)
  - Sous-menu: Mise à jour | Télémétrie
  - Mise à jour:
    - Cartes: Version actuelle et version précédente.
    - Formulaire (optionnel): forcer une version (rarement nécessaire).
    - Tableau des appareils (côté mise à jour) et statistiques.
  - Télémétrie:
    - KPIs: total, actifs aujourd’hui, inactifs 7j, dormants, crashes 24h, appareils à problème, à jour, maj requise.
    - Tableur appareils: appareil, modèle, Android, version, dernière activité, crashes 7j, localisation, statut, actions.
    - Détails appareil (clic sur une ligne): résumé complet, localisation (si disponible), crashes récents, sessions récentes, événements récents.
    - Signalement: bouton par ligne pour remonter un incident (“l’application ne fonctionne pas”).
- Auto‑refresh (5s): sections critiques actualisées sans recharger toute la page.

---

## 🔐 Sécurité & bonnes pratiques

- Téléchargement via `admin/download_apk.php` avec headers corrects (MIME, cache‑control, longueur).
- Répertoires autorisés vérifiés côté serveur (pas de chemin arbitraire).
- JSON optionnel validé de manière défensive (types, structure).
- L’endpoint `admin/ajax_telemetry.php` respecte la lecture seule pour GET et n’écrit que des signalements contrôlés pour POST.
- Pensez à protéger l’accès Admin (authentification) et à servir l’API en HTTPS.

---

## 🛠️ Dépannage

- “La page n’affiche pas la dernière version”
  - Vérifier que `admin/uploads/latest_apk.json` contient `file` et (si fourni) `version_code`/`version_name`.
  - Attendre 5s (auto‑refresh) ou recharger la page.
- “L’API ne propose pas de mise à jour”
  - Appeler `/api/app_updates.php?device_id=TEST&version_code=0` pour forcer un cas de mise à jour.
  - Vérifier `data/app_versions.json` → `current_version.version_code` > 0 et `apk_url` non vide.
- “Le téléchargement échoue”
- “La télémétrie ne s’affiche pas”
  - Vérifier la connexion DB (config.php) et la présence des tables `app_devices`, `app_crashes`, `app_sessions`, `app_events`.
  - Assurez-vous que les événements avec lat/lng sont bien stockés en JSON (`event_data`).
  - Vérifier les droits d’accès à `admin/ajax_telemetry.php`.
  - Vérifier l’existence du fichier dans `admin/uploads/`.
  - Tester le lien `/admin/download_apk.php?file=nom_du_fichier.apk`.

---

## 📚 FAQ

- Puis‑je ne pas fournir de JSON ?
  - Oui. Le système tente une détection automatique. Fournir le JSON accélère et fiabilise les champs version/changelog et les politiques de mise à jour.
- Le formulaire “Publier cette version” est‑il encore utile ?
  - Il devient optionnel. L’overlay automatique alimente déjà l’API et l’UI.
- Peut‑on réduire encore l’intervalle de rafraîchissement ?
  - Oui, modifier `REFRESH_MS` dans `admin/assets/js/auto_refresh_app_updates.js`.

---

## 📎 Annexes

### Exemple complet de `latest_apk.json`
```json
{
  "file": "suzosky-coursier-20250919-153012.apk",
  "uploaded_at": "2025-09-19T15:30:12+00:00",
  "apk_size": 18610510,
  "version_code": 42,
  "version_name": "2.1.0",
  "force_update": false,
  "min_supported_version": 35,
  "changelog": [
    "Optimisation des performances",
    "Corrections de bugs"
  ],
  "previous": {
    "file": "suzosky-coursier-20250918-101500.apk",
    "version_code": 41,
    "version_name": "2.0.9",
    "apk_size": 18500432,
    "uploaded_at": "2025-09-18T10:15:00+00:00"
  }
}
```

---

## 🔗 Connexions principales (références)

- Ingestion upload: `admin/admin.php`
- Aide centrale: `lib/version_helpers.php`
- Pointeur apk courant: `admin/uploads/latest_apk.json`
- Config globale: `data/app_versions.json`
- API: `api/app_updates.php`
- Pages admin: `admin/applications.php`, `admin/app_updates.php`
- Téléchargement: `admin/download_apk.php`
- Auto‑refresh: `admin/assets/js/auto_refresh_app_updates.js`
