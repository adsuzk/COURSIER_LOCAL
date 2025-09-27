# ⚡ FCM – Guide Express

Pour la version détaillée, lire `FCM_PUSH_NOTIFICATIONS.md`.

## Test rapide sonnerie
```
php test_one_click_ring_data_only.php
```
Attendu : 200 HTTP v1 + sonnerie sur téléphone.

## Résolution rapide
| Problème | Action |
|----------|--------|
| 403 IAM | Vérifier rôles service account (Firebase Cloud Messaging Admin) |
| Pas de son | Utiliser data-only (`test_one_click_ring_data_only.php`) |
| Pas de log FCMService | Token erroné → relancer app pour régénérer token |
| MediaPlayer silencieux | Vérifier volume média + fichier `res/raw/new_order_sound` |

## Tables
- `device_tokens`
- `notifications_log_fcm`

## Scripts utiles
```
php debug_fcm_permissions.php
php test_one_click_ring.php
php test_one_click_ring_data_only.php
```

## Production checklist
- Service account OK
- HTTP v1 fonctionne
- Sonnerie confirmée
- Logs FCM propres

Fin ✅
