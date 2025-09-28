# GUIDE SIMPLE - MISES À JOUR AUTOMATIQUES LWS

## 🎯 Ce que fait le système automatique

Quand vous uploadez vos modifications sur LWS, le système :

1. **Détecte automatiquement** les nouvelles tables que vous avez créées en local
2. **Détecte automatiquement** les nouvelles colonnes ajoutées aux tables existantes  
3. **Détecte automatiquement** les nouveaux index créés
4. **Génère automatiquement** les scripts de mise à jour
5. **Applique automatiquement** ces mises à jour sur la base de données LWS

## 🚀 Comment ça marche pour vous

### Étape 1 : Travaillez normalement en local
- Créez vos nouvelles tables avec phpMyAdmin
- Ajoutez de nouvelles colonnes à vos tables
- Tout ce que vous faites en local sera détecté automatiquement

### Étape 2 : Synchronisation vers LWS
Lancez le script comme d'habitude :
```
BAT\SYNC_COURSIER_PROD.bat
```

**NOUVEAU** : Le script va maintenant :
- Analyser votre base de données locale
- Détecter tous les changements depuis la dernière fois
- Générer automatiquement les migrations nécessaires
- Préparer les fichiers pour LWS

### Étape 3 : Upload sur LWS
Uploadez tout le contenu de `coursier_prod` sur LWS comme d'habitude.

### Étape 4 : Mise à jour automatique sur LWS
Le cron sur LWS va automatiquement :
- Détecter les nouvelles migrations
- Créer les nouvelles tables
- Ajouter les nouvelles colonnes
- Créer les nouveaux index

## 📁 Fichiers automatiques créés

Le système crée automatiquement ces fichiers (ne pas toucher) :
- `diagnostic_logs/db_structure_snapshot.json` : Photo de votre DB locale
- `diagnostic_logs/auto_migration_generator.log` : Journal des détections
- `Scripts/db_schema_migrations.php` : Scripts de mise à jour (mis à jour automatiquement)

## ✅ Avantages pour vous

- **Zéro code à écrire** : Tout est automatique
- **Zéro risque d'erreur** : Le système détecte précisément les changements
- **Zéro manipulation manuelle** : Travaillez en local, uploadez, c'est tout !
- **Historique complet** : Toutes les modifications sont tracées

## 🔄 Configuration LWS (une seule fois)

Ajoutez cette ligne au crontab LWS pour que les mises à jour se lancent automatiquement :
```bash
0 2 * * * /usr/bin/php /path/to/Scripts/Scripts\ cron/automated_db_migration.php
```

## 🆘 En cas de problème

Consultez les logs automatiques :
- `diagnostic_logs/auto_migration_generator.log` : Détection des changements
- `diagnostic_logs/db_migrations.log` : Application des mises à jour sur LWS

**Résultat** : Vous développez en local, vous uploadez, tout se met à jour automatiquement sur LWS ! 🎉