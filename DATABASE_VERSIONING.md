# Système de versioning des bases de données

## Convention de nommage
- Format: `coursier_lws_AAAAMMJJ`
- Exemple: `coursier_lws_20250928` (28 septembre 2025)

## Bases de données disponibles
- **coursier_lws_20250928** : Base active du serveur LWS (28/09/2025) - 77 tables
  - Source: Dump du serveur de production LWS
  - Status: ✅ ACTIVE (utilisée par l'app en développement)

## Gestion des évolutions
1. **Nouvelle version** : Créer une nouvelle base avec la date du jour
2. **Import** : `php setup_database.php -- --db=coursier_lws_AAAAMMJJ --dump=chemin/vers/dump.sql --force`
3. **Activation** : Modifier `DB_NAME` dans `env_override.php`
4. **Nettoyage** : Supprimer les anciennes versions après validation

## Scripts utiles
```bash
# Créer une nouvelle version
php setup_database.php -- --db=coursier_lws_$(Get-Date -Format "yyyyMMdd") --dump=nouveau_dump.sql --force

# Lister les bases existantes
mysql -u root -e "SHOW DATABASES LIKE 'coursier_lws_%';"

# Sauvegarder avant évolution
mysqldump -u root coursier_lws_20250928 > backup_20250928.sql
```

## Historique
- 2025-09-28 : Création du système de versioning avec base LWS active