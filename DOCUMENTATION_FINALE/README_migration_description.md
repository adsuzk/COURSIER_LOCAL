# Correction colonne `description` / `description_colis`

## Symptôme
Erreur dans l'app Android:
```
SQLSTATE[42S22]: Column not found: 1054 Unknown column 'description' in 'field list'
```

Cela provient d'une requête front ou d'un endpoint legacy qui s'attend à trouver une colonne `description` dans la vue/table `commandes_coursier`.

## Cause racine
La vue `commandes_coursier` pointait statiquement vers des colonnes de `commandes_classiques` et ne fournissait pas de champ `description` si les noms réels différaient (`description_colis`). Après des migrations, certains environnements n'avaient pas la colonne ou la vue n'était pas régénérée.

## Correctif apporté
1. `install_legacy_compat.php` régénère maintenant dynamiquement la vue en détectant les colonnes disponibles et en mappant:
   - description <- `description_colis` ou `description` sinon chaîne vide
   - distance <- `distance_estimee` / `distance_calculee` / `distance`
   - prix_livraison <- `tarif_livraison` / `prix_estime` / `prix_total`
2. Script d'inspection: `detect_missing_description_columns.php` pour afficher les tables manquantes.
3. Script SQL optionnel: `quick_fix_description.sql` pour ajouter `description_colis` si absent.

## Étapes recommandées
1. Exécuter dans le navigateur: `http://<host>/COURSIER_LOCAL/detect_missing_description_columns.php`
2. Si `commandes_classiques` n'a pas `description_colis`, lancer un ALTER manuel ou le script SQL.
3. Lancer: `php install_legacy_compat.php` pour recréer la vue dynamique.
4. Tester l'endpoint / la fonctionnalité dans l'app.

## Validation rapide
- Créer une commande via `api/submit_order.php`.
- Vérifier qu'elle apparaît via l'endpoint legacy / liste utilisée par l'app (poll ou get).
- Observer que le champ `description` est présent et non bloquant.

## En cas de persistance de l'erreur
- Vider caches: redémarrer Apache/MySQL.
- Vérifier qu'aucun ancien script n'utilise directement `commandes_coursier` comme table physique.
- Vérifier les logs `diagnostics_errors.log`.

---
Dernière mise à jour: 