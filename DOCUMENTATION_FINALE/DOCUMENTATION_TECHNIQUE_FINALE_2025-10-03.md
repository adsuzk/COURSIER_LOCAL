# 📌 DOCUMENTATION TECHNIQUE FINALE — COURSIIER SUZOSKY (03 Oct 2025)

Ce document décrit l’état exact de la production et les mécanismes critiques (FCM, index, disponibilité, build Android). Il remplace toute information contradictoire ailleurs.

---

## 1) Environnement PRODUCTION (LWS)

- Domaine coursier: https://coursier.conciergerie-privee-suzosky.com
- Index mapping: la racine `/` et `/index.php` répondent en HTTP 200 avec un contenu équivalent; le formulaire s’ouvre selon la disponibilité calculée (voir §3).
- PHP: 8.3.26 (extensions critiques: curl, openssl, pdo_mysql, json, mbstring)
- DOCUMENT_ROOT: `/var/www/conciergerie-privee-suzosky.com/htdocs`
- DB MySQL (MariaDB 10.11):
  - Host: 185.98.131.214 (hostname logique: mysql35.lws-hosting.com)
  - Port: 3306
  - Database: `conci2547642_1m4twb`
  - User: `conci2547642_1m4twb`
  - Timezone DB: SYSTEM=CEST (delta DB↔PHP observé: +7200s)

---

## 2) FCM — Configuration et Envoi

- Projet Firebase: `coursier-suzosky`
- Compte de service: `data/firebase_service_account.json` (chargé en prod)
- Envoi de push: HTTP v1 (OAuth messages:send) prioritaire; fallback legacy par clé uniquement si nécessaire (env `FCM_SERVER_KEY` ou `data/secret_fcm_key.txt`).
- Fichiers serveur impliqués:
  - `fcm_manager.php` (route principale d’envoi; préfère HTTP v1 s’il trouve un compte de service, sinon legacy)
  - `fcm_v1_manager.php` (implémentation OAuth v1; génération JWT → access_token → POST messages:send)
  - `migrate_to_fcm_v1.php` (outillage)

Variables d’environnement supportées:
- `FIREBASE_SERVICE_ACCOUNT_FILE` (facultatif) — force un chemin absolu vers un JSON de service.
- `FCM_SERVER_KEY` (fallback legacy).

Points critiques:
- Aucune « réussite simulée »: si clé/compte manquant, l’envoi échoue explicitement.
- Les tokens « UNREGISTERED » sont nettoyés côté serveur lors des réponses FCM.

---

## 3) Disponibilité Index — Gate via FCMTokenSecurity

Source: `fcm_token_security.php` (shim) → charge `Scripts/Scripts cron/fcm_token_security.php` si présent; sinon fallback fonctionnel intégré (depuis 03/10) calculant la dispo directement en SQL.

Algorithme (mode par défaut: freshness):
- Table: `device_tokens`
- Colonnes clés: `is_active` (TINYINT), `last_ping` DATETIME (fallback `updated_at` si absent)
- Seuil fraicheur: 60s par défaut; surchargé par `FCM_AVAILABILITY_THRESHOLD_SECONDS` (env)
- Deux modes:
  - freshness (défaut): ouverture si `fresh_count > 0` (is_active=1 ET last_ping ≤ seuil)
  - immediate: ouverture si `active_count > 0` (ignorer fraicheur). Activez via `FCM_IMMEDIATE_DETECTION=true`

SQL exact (schéma avec is_active):
```
SELECT
  SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) AS active_count,
  SUM(CASE WHEN is_active = 1 AND TIMESTAMPDIFF(SECOND, COALESCE(last_ping, updated_at), NOW()) <= :threshold THEN 1 ELSE 0 END) AS fresh_count,
  MAX(CASE WHEN is_active = 1 THEN COALESCE(last_ping, updated_at) END) AS last_active_at,
  MAX(COALESCE(last_ping, updated_at)) AS last_seen_at
FROM device_tokens;
```

Sortie fournie à l’index:
- `can_accept_orders` (bool), `available_coursiers` (int), `fresh_coursiers` (int), `seconds_since_last_active` (int), `detection_mode`, `threshold_seconds`.

Important: `get_coursier_availability.php` est plus permissif (OU entre active_count>0 et fresh_count>0). L’index suit la logique stricte de FCMTokenSecurity (par défaut freshness).

---

## 4) Cycle de vie des tokens — Endpoints

Endpoints principaux:
- `api/register_device_token_simple.php` (POST):
  - Body: `coursier_id` (int), `token` (string), `platform` (ex: android), `app_version` (string), `agent_id` (optionnel)
  - Effet: UPSERT par `token_hash` (SHA-256 du token); `is_active=1`, `last_ping=NOW()`, `updated_at=NOW()`
  - Logs: `diagnostic_logs/token_reg.log` (si activé)

- `api/ping_device_token.php` (POST):
  - Body: `coursier_id`, `token`, `platform`, `app_version`
  - Effet: UPSERT (mêmes clés), rafraîchit `last_ping`, met `is_active=1`, marque `agents_suzosky.en_ligne=1`
  - Logs: `diagnostic_logs/token_ping.log` (si activé)

Schéma minimal attendu de `device_tokens`:
```
id INT PK AI,
coursier_id INT NOT NULL,
token TEXT NOT NULL,
token_hash CHAR(64),
is_active TINYINT(1) DEFAULT 1,
platform VARCHAR(32),
app_version VARCHAR(64),
updated_at DATETIME,
last_ping DATETIME,
agent_id INT NULL,
device_type VARCHAR(32) NULL
```

---

## 5) Application Android — Build & Sécurité FCM

- Domaine prod embarqué (Release): `https://coursier.conciergerie-privee-suzosky.com`
- `google-services.json` unifié (debug+release) — mobilesdk_app_id valide pour release.
- Garde build Gradle: tâche de vérification configuration-cache-safe qui échoue si un `mobilesdk_app_id` placeholder est détecté en release.
- Commande de build (Windows) tolérante aux locks lint:
  - `gradlew.bat assembleRelease -x lintVitalRelease -x lintVitalAnalyzeRelease`

Fichiers clés:
- `CoursierAppV7/app/build.gradle.kts`: define BuildConfig (USE_PROD_SERVER=true en release), PROD_BASE, et la tâche VerifyGoogleServices.
- `CoursierAppV7/app/google-services.json`: unifié.
- `ApiService.kt`: enregistre et ping les tokens sur les endpoints ci-dessus; base URL adaptée prod/dev.
- `FCMService.kt`: gère onNewToken → enregistrement/ping immédiats.

---

## 6) Outils de Diagnostic (prod)

- `diagnostic_fcm_full.php` (root): sortie texte prêt à copier-coller, couvre:
  - Environnement, index `/` vs `/index.php`, FCM (compte/clé), DB et schéma `device_tokens`, counts, top 5 tokens récents
  - Reachability des endpoints, logs récents, deltas d’horloge DB↔PHP
  - Exécution de la logique réelle `FCMTokenSecurity` [5b]
  - Paramètres: `coursier_id`, `threshold`, `verbose`, `test_ping=1` (POST contrôlé)

Recommandations sécurité:
- Protéger ou supprimer `diagnostic_fcm_full.php` après débogage (auth .htaccess ou whitelisting IP).

---

## 7) Checklist post-déploiement

1. `data/firebase_service_account.json` présent et lisible, projet=`coursier-suzosky`.
2. `fcm_token_security.php` réel accessible; à défaut, le shim fallback (amélioré) est en place.
3. Endpoints `register_device_token_simple.php` et `ping_device_token.php` présents et acceptant des POST.
4. `device_tokens` contient au moins un enregistrement `is_active=1` avec `last_ping≤60s` (sinon activer `FCM_IMMEDIATE_DETECTION=true` temporairement).
5. App Android release construite avec `google-services.json` unifié et guard Gradle actif.

---

## 8) Variables d’environnement supportées

- `ENVIRONMENT=production` (ou fichier `FORCE_PRODUCTION_DB`)
- `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS` (ignorés si force prod)
- `FIREBASE_SERVICE_ACCOUNT_FILE`, `FCM_SERVER_KEY`
- `FCM_AVAILABILITY_THRESHOLD_SECONDS`, `FCM_IMMEDIATE_DETECTION`
- `ADMIN_API_TOKEN` (dashboard)

---

Fin du document.
