# 🔄 AUTOMATISATION CRON POUR CONSOLIDATION DOCUMENTATION

## Intégration dans cron_master.php

Ajouter cette section dans le fichier `Scripts/Scripts cron/cron_master.php` :

```php
// Consolidation automatique de documentation (quotidien à 2h)
if (date('H:i') === '02:00') {
    $consolidation_script = dirname(__DIR__, 2) . '/consolidate_docs.php';
    if (file_exists($consolidation_script)) {
        include_once $consolidation_script;
        error_log("[CRON] Documentation consolidée automatiquement");
    }
}
```

## Configuration CRON LWS

**URL à ajouter :**
`https://coursier.conciergerie-privee-suzosky.com/consolidate_docs.php`

**Fréquence recommandée :**
- **Quotidien** : `0 2 * * *` (2h du matin)
- **Hebdomadaire** : `0 2 * * 0` (dimanche 2h)

## Monitoring

Le script génère automatiquement :
- ✅ **Logs détaillés** : `DOCUMENTATION_FINALE/consolidation.log`
- ✅ **Horodatage** : Chaque exécution est datée
- ✅ **Nettoyage** : Garde les 5 dernières versions
- ✅ **Version LATEST** : Toujours accessible via `CONSOLIDATED_DOCS_LATEST.md`