# 📚 Documentation complète Suzosky – Coursier (Master)

> Ce document unifie et consolide la documentation du projet. Il doit permettre de reproduire à la virgule près l’ensemble du système (backend PHP, app Android, intégrations externes, opérations, sécurité). Les informations obsolètes ou en doublon sont retirées. Les sections pointent vers les fichiers sources lorsque nécessaire.

## Table des matières
- Architecture générale (stack, modules, flux)
- Environnements, URLs, chemins et couleurs
- Backend PHP (config, BDD, APIs, sécurité)
- Paiements CinetPay (config duale, flux, callbacks)
- Notifications et FCM
- Système de commandes (statuts, timeline, tracking)
- Comptabilité et finances (wallet, commissions, snapshots)
- Application Android (build, config, debug/prod)
- Outils, scripts, surveillance et tâches planifiées
- Annexes (migrations, checklists, dépannage)

---

## 1. Architecture générale
- Backend: PHP 8 + SQLite/MySQL (selon config), XAMPP sous Windows pour dev local.
- Front/Portail admin: pages PHP + assets JS/CSS.
- Mobile: Android (Kotlin, Jetpack Compose, Hilt, Maps Compose, FCM).
- Intégrations:
  - Google Maps, Places, Directions.
  - CinetPay v2 (paiements, recharges), double compte (client vs coursier).
- Dossiers clés:
  - `COURSIER_LOCAL/` racine du projet
  - `api/` endpoints REST-ish
  - `cinetpay/` intégration paiements (callbacks, notify, return)
  - `CoursierAppV7/` app Android
  - `DOCUMENTATION_FINALE/` docs consolidées
  - `diagnostic_logs/` journaux système

## 2. Environnements, URLs, chemins et couleurs
- Base PRODUCTION (LWS): `https://coursier.conciergerie-privee-suzosky.com/COURSIER_LOCAL`
- Base DEV locale: `http://localhost/COURSIER_LOCAL`
- App Android BuildConfig:
  - Debug: `USE_PROD_SERVER=false`, `DEBUG_LOCAL_HOST` configurable via `local.properties` (clé `debug.localHost`), `FORCE_LOCAL_ONLY`.
  - Release: `USE_PROD_SERVER=true` (pointage LWS), valeurs de debug conservées pour diag.
- Couleurs/Thème:
  - Material 3, palette Compose. Voir `CoursierAppV7/app/src/main/...` (thème) et assets.

## 3. Backend PHP – Config, BDD, APIs, sécurité
- Fichier `config.php` centralise la configuration (base URL, PDO, helpers, getCinetPayConfig contextuel).
- BDD:
  - SQLite fichier `coursier_local.db` ou MySQL selon config.
  - Tables clés: commandes, statuts, coursiers, wallet, historiques.
  - Scripts d’init: tâche VS Code `init-db-coursier` (exécute `setup_database.php`).
- Sécurité et sessions:
  - Sessions PHP initialisées en entrypoints.
  - IP logging, validation des entrées (normalisation champs dans `api/submit_order.php`).
- APIs principales:
  - `api/submit_order.php` (création de commande)
  - `api/update_order_status.php` (maj statuts)
  - `api/init_recharge.php`, `api/cinetpay_callback.php` (recharges)
  - `cinetpay/payment_notify.php`, `cinetpay/payment_return.php`

## 4. Paiements CinetPay – Double compte, flux, callbacks
- Contexte double:
  - Client (site index) vs Coursier (app).
  - `getCinetPayConfig('client')` et `getCinetPayConfig('coursier')` fournissent apikey/site_id/secret/endpoint appropriés.
- Fichiers:
  - `cinetpay/config.php` dérive les constantes depuis `getCinetPayConfig('coursier')` par défaut.
  - `api/initiate_payment_only.php` force le contexte client pour l’index.
  - `api/init_recharge.php` utilise le contexte coursier.
  - `cinetpay/cinetpay_integration.php` centralise les appels (initiateRecharge, checkPaymentStatus, logs).
- Journalisation:
  - `diagnostic_logs/cinetpay_api.log` (requêtes/réponses), `cinetpay/cinetpay_notification.log`.
- Règles succès v2: HTTP 200/201, `code: "201"`, présence de `payment_url`.

## 5. Notifications et FCM
- App Android: `firebase-messaging-ktx` via BoM.
- google-services.json: un client par packageId (debug et release).
- Serveur: `configure_fcm_api.php`, `CONFIGURATION_FCM_URGENT.md`.

## 6. Système de commandes – Statuts, timeline, tracking
- Statuts synchronisés côté app/admin: création → acceptation → pickup → en route → livré.
- Fixes intégrés: boucle de rotation, delivery marker, voice TTS pour nouvelles commandes.
- Tracking admin: carte, markers, fallback polylines (Directions), recadrage post “J’ai récupéré”.

## 7. Comptabilité – Wallet, commissions, snapshots
- Min solde strict > 2 000 FCFA.
- Déductions automatiques: Commission% + Plateforme% + Ads% à la complétion.
- Snapshot par commande, synchronisé admin/app.
- Historique Portefeuille: modal fonctionnelle.

## 8. Application Android – Build et configuration
- Module: `CoursierAppV7/app/build.gradle.kts`
  - `applicationId = "com.suzosky.coursier"` (release), debug ajoute `.debug`.
  - Plugins: Google Services, Hilt, Compose.
- google-services.json (Firebase):
  - Doit contenir deux blocs client si on gère debug et release. Exemples:
    - release: `package_name: "com.suzosky.coursier"`
    - debug: `package_name: "com.suzosky.coursier.debug"`
  - Si le fichier actuel n’a que `com.suzosky.coursier.debug`, ajouter la config release pour corriger l’erreur “No matching client found for package name 'com.suzosky.coursier'”.
- CMD utiles (Windows PowerShell):
  - Gradle install debug: dans `CoursierAppV7`: `./gradlew.bat installDebug`
  - Assemble release: `./gradlew.bat assembleRelease`

## 9. Outils, scripts, surveillance
- Sync `.md` → `DOCUMENTATION_FINALE`: `BAT/SYNC_MD_DOCUMENTATION_FINALE.bat` + `PS1/SYNC_MD_DOCUMENTATION_FINALE.ps1` (watcher mono-instance).
- Auto-start Watcher (Tâches planifiées): `BAT/INSTALL_AUTOSTART_SYNC_MD.bat`.
- Protection GitHub auto: `BAT/PROTECTION_GITHUB.bat` → PowerShell `PS1/PROTECTION_GITHUB_SIMPLE.ps1` (push toutes 5s, sans secrets).

## 10. Annexes – Migrations, checklists, dépannage
- Dépannage Android: erreur Google Services
  - Cause: absence de client Firebase pour `com.suzosky.coursier` dans google-services.json.
  - Fix rapide: ajouter un bloc client release dans `CoursierAppV7/app/google-services.json` avec le même `project_number`/`project_id` que le debug, mais `package_name: "com.suzosky.coursier"` et `mobilesdk_app_id` propre à ce package depuis la console Firebase.
- Dépannage CinetPay: vérifier `diagnostic_logs/cinetpay_api.log` pour code 201 et url.
- Check statuts commandes: scripts `check_*.php`.

---

Fin du document maître. Maintenir ce fichier à jour après chaque série de corrections majeures. Les sections peuvent être enrichies avec extraits précis (couleurs, styles, schémas de tables) selon les besoins. 