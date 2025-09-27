# 🔔 Documentation Complète FCM & Sonnerie (Coursier)

Cette documentation décrit l'architecture, la configuration, les scripts, les tests et la résolution de problèmes pour le système de notifications push Firebase Cloud Messaging (FCM) qui déclenche une sonnerie (service Android `OrderRingService`).

---
## 1. Objectif Fonctionnel
Quand une nouvelle commande arrive, le téléphone du coursier doit :
1. Recevoir une notification push quasi instantanément.
2. Afficher une notification (silencieuse côté OS pour éviter le double sonore).
3. Démarrer une sonnerie en boucle (2s répétées) jusqu'à action utilisateur (arrêt, ouverture commande).

---
## 2. Composants
| Couche | Élément | Rôle |
|--------|---------|------|
| Backend PHP | `device_tokens` (table) | Stocke les tokens FCM des coursiers |
| Backend PHP | `api/lib/fcm_enhanced.php` | Envoi unifié HTTP v1 (et fallback legacy si activé) + logs |
| Backend PHP | `notifications_log_fcm` (table) | Journal de chaque envoi (réponse HTTP, succès) |
| Backend PHP | Scripts tests | `test_one_click_ring.php`, `test_one_click_ring_data_only.php`, `test_urgent_order_with_sound.php` |
| Android | `FCMService.kt` | Réception messages, déclenche `OrderRingService` |
| Android | `OrderRingService.kt` | Foreground service : lit `raw/new_order_sound` en boucle courte |
| Android | `NotificationSoundService` (si futur) | Extension possible (sons multiples) |

---
## 3. Flux Technique
1. L'app obtient un token via FCM (`onNewToken`).
2. Token envoyé au backend (`ApiService.registerDeviceToken`).
3. Quand backend veut alerter : appel `fcm_send_with_log()` → construit payload FCM → HTTP v1.
4. FCM livre message → `FCMService.onMessageReceived()`.
5. Si `type = new_order` → `OrderRingService.start()` → sonnerie.
6. Notification affichée silencieusement (canal `orders_notify_silent`).

---
## 4. Format du Payload HTTP v1
Exemple (mode notification + data) :
```json
{
  "message": {
    "token": "<FCM_TOKEN>",
    "notification": {"title": "🔔 Nouvelle commande", "body": "5000 FCFA"},
    "data": {
      "type": "new_order",
      "order_id": "12345",
      "title": "🔔 Nouvelle commande",
      "body": "5000 FCFA" 
    },
    "android": {"priority": "HIGH", "notification": {"sound": "default"}}
  }
}
```

Mode DATA-ONLY (force passage par `onMessageReceived` même si app en arrière-plan) :
```json
{
  "message": {
    "token": "<FCM_TOKEN>",
    "data": {
      "type": "new_order",
      "order_id": "TEST_RING_DATA_...",
      "title": "🔔 TEST DATA",
      "body": "Test data-only"
    },
    "android": {"priority": "HIGH", "notification": {"sound": "default"}}
  }
}
```
Activer via ajout `'_data_only' => true` dans `$data` côté PHP (automatiquement retiré avant envoi).

---
## 5. Librairie PHP `fcm_enhanced.php`
Fonctions clés :
- `fcm_send_with_log($tokens, $title, $body, $data, $coursier_id, $order_id)`
  - Détection service account → HTTP v1 (prioritaire)
  - Fallback legacy si `FCM_SERVER_KEY` présent ou flag `FCM_FORCE_LEGACY`
  - Journalisation table `notifications_log_fcm`
- `_data_only` (clé spéciale) : transforme le message en data-only.
- Variables d'environnement supportées :
  - `FIREBASE_SERVICE_ACCOUNT_FILE` : chemin JSON service account
  - `FCM_VALIDATE_ONLY=1` : envoi dry-run
  - `FCM_EXTRA_SCOPE_CLOUD=1` : ajoute scope OAuth cloud-platform (diagnostics)
  - `FCM_FORCE_LEGACY=1` ou fichier `data/force_legacy_fcm` : force la voie legacy

---
## 6. Scripts Utiles
| Script | Usage |
|--------|-------|
| `test_one_click_ring.php` | Test simple (notification + data) |
| `test_one_click_ring_data_only.php` | Test data-only (fiable pour déclencher service) |
| `test_urgent_order_with_sound.php` | Crée commande + envoi urgent avec métadonnées |
| `debug_fcm_permissions.php` | Vérifie service account / scopes / legacy key |
| `test_fcm_iam_permission.php` | Test IAM `cloudmessaging.messages.create` |
| `healthcheck_fcm.php` | Envoi validate_only data-only (monitoring / cron) |
| `status_fcm.php` | Tableau de bord live + historique + tests |

---
## 7. Table `device_tokens`
Champs recommandés :
| Colonne | Type | Commentaire |
|---------|------|-------------|
| `id` | INT PK | |
| `coursier_id` | INT | FK logique vers coursiers |
| `token` | TEXT | Token FCM actuel |
| `token_hash` | CHAR(64) | SHA2 pour éviter doublons |
| `updated_at` | TIMESTAMP | Mise à jour automatique |

Nettoyage : script `clean_tokens.php` (supprime doublons / tokens obsolètes si présent).

---
## 8. Table `notifications_log_fcm`
Schéma :
```
id, coursier_id, order_id, notification_type, title, message,
fcm_tokens_used, fcm_response_code, fcm_response, success, created_at
```
Requêtes utiles :
```sql
SELECT * FROM notifications_log_fcm ORDER BY id DESC LIMIT 20;
SELECT success, COUNT(*) FROM notifications_log_fcm GROUP BY success;
```

---
## 9. Android – Points Critiques
- `FCMService` crée canal silencieux (`orders_notify_silent`) → pas de son double.
- Sonnerie réelle = `OrderRingService` (MediaPlayer + boucle 2s).
- Fichier audio : `app/src/main/res/raw/new_order_sound.*` (vérifier présence).
- Permission POST_NOTIFICATIONS requise Android 13+ (gérée dans l'UI de l'app / paramètres système).
- Foreground Service : déclaré avec `android:foregroundServiceType="mediaPlayback"`.

---
## 10. Choisir Notification vs Data-Only
| Mode | Avantage | Risque |
|------|----------|-------|
| notification + data | Affichage système automatique si app tuée | Parfois `onMessageReceived` non invoqué (Android gère direct) |
| data-only | Garantie passage par `onMessageReceived` | Doit construire notification soi-même (fait par `showNotification`) |

Stratégie actuelle : data-only pour fiabilité de la sonnerie.

---
## 11. Procédure de Test Rapide (Check santé)
1. Vérifier token présent :
   ```sql
   SELECT coursier_id, LEFT(token,40) FROM device_tokens ORDER BY updated_at DESC LIMIT 1;
   ```
2. Test data-only :
   ```bash
   php test_one_click_ring_data_only.php
   ```
3. Sur téléphone :
   - Voir notification
   - Sonnerie démarre
4. Vérifier log :
   ```sql
   SELECT id,title,fcm_response_code,success FROM notifications_log_fcm ORDER BY id DESC LIMIT 5;
   ```

---
## 12. Erreurs Fréquentes & Solutions
| Symptôme | Cause | Solution |
|----------|-------|----------|
| 403 cloudmessaging.messages.create | Rôle IAM insuffisant / propagation | Ajouter rôle Firebase Cloud Messaging Admin, patienter 5–10 min |
| 200 OK mais pas de son | Message traité en mode notification-only | Passer en data-only (`_data_only`), vérifier logs FCMService |
| Pas de log FCMService | Token pas celui de l'app ou canal notifications bloqué | Regarder logcat, régénérer token (réinstaller / vider données) |
| `onNewToken` jamais appelé | google-services.json invalide | Télécharger bon fichier Firebase console (package exact debug) |
| MediaPlayer silencieux | Fichier audio manquant ou volume média OFF | Vérifier `res/raw/new_order_sound.*`, monter volume |
| Legacy fallback inexistant | Pas de server key | Activer API legacy dans console ou ignorer si HTTP v1 stable |

---
## 13. Sécurité & Bonnes Pratiques
- Ne pas committer le server key legacy (`data/secret_fcm_key.txt` vide ou ignoré).
- Restreindre les rôles IAM après validation (retirer `Editor`).
- Rotation clé service account si fuite suspectée.
- Limiter accès scripts debug en prod (protéger par IP ou auth admin).

---
## 14. Durcissement Futur
- Ajout retry automatique (ex: backoff 1s/3s) sur échecs transitoires.
- Envoi groupé pour multi-coursiers (topics ou multicast loop déjà supporté).
- Ajout métriques Prometheus (compteur succès/échec).
- Monitoring access_token (cache in-memory + expiration).

---
## 15. Glossaire Express
| Terme | Définition |
|-------|------------|
| Data-only | Message FCM sans bloc `notification` forçant passage dans `onMessageReceived` |
| HTTP v1 | API moderne FCM avec OAuth2 (service account) |
| Legacy | Ancienne API clé serveur (AAAA…) |
| Foreground Service | Service Android prioritaire affichant notification persistante |

---
## 16. Historique Résolution (Résumé)
- Étape 1: Token échouait (google-services.json invalide) → corrigé.
- Étape 2: 403 IAM → ajout rôles + propagation.
- Étape 3: validate_only + scope cloud-platform pour diagnostiquer.
- Étape 4: Succès HTTP v1 mais pas de son → passage en data-only.
- Étape 5: Sonnerie confirmée → documentation finalisée (ce fichier).

---
## 17. Check-list Avant Mise en Prod
- [ ] google-services.json (prod) appId correct
- [ ] Service account dédié FCM (rôles minimaux)
- [ ] Table `notifications_log_fcm` présente
- [ ] Test `php test_one_click_ring_data_only.php` OK
- [ ] Token réel actif (vérifier date `updated_at` < 24h)
- [ ] Son présent dans `res/raw`
- [ ] Notification visible + sonnerie sur device test

---
## 18. Raccourcis / Commandes
```bash
php test_one_click_ring_data_only.php
php debug_fcm_permissions.php
adb logcat -d | findstr /i "FCMService OrderRingService"
```

---
## 19. Maintenance
- Purge vieux logs : `DELETE FROM notifications_log_fcm WHERE created_at < NOW() - INTERVAL 30 DAY;`
- Vérif quotidienne (cron) : `php healthcheck_fcm.php` (retour JSON ok/false). Exemple de sortie : `{ "ok": true, "method": "http_v1", "validate_only": true }`

---
## 20. Auteur & Dernière Maj
- Générée automatiquement assistée (2025-09-23)
- Contributeurs: backend + assistant automatisé

---
Fin ✅
