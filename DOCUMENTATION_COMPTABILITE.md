# 📊 MODULE COMPTABILITÉ SUZOSKY

## 🎯 Vue d'ensemble

Le module de comptabilité offre une analyse financière complète et précise de toute l'activité de livraison Suzosky.

## ✨ Fonctionnalités principales

### 1. **Métriques principales**
- 💰 Chiffre d'affaires global
- 🚴 Revenus coursiers (après commission)
- 🏢 Commission Suzosky
- ⚙️ Frais plateforme
- 📢 Frais publicitaires
- ✨ Revenus nets Suzosky

### 2. **Filtres intelligents**
- 📅 Période personnalisable (date début / date fin)
- 🔍 Filtrage en temps réel
- 📊 Mise à jour instantanée des métriques

### 3. **Exports professionnels**
- 📥 **Export Excel (.xlsx)** : Comptabilité complète avec tableaux formatés
- 📄 **Export PDF** : Rapport professionnel aux couleurs Suzosky
- 🎨 Design professionnel et lisible

### 4. **Précision comptable**
- ⚡ **Taux historiques** : Les calculs utilisent les taux qui étaient en vigueur au moment de chaque livraison
- 🔄 **Synchronisation parfaite** : Même si les taux changent, les calculs restent exacts
- 📈 **Historique complet** : Visualisation de l'évolution des taux dans le temps

## 📋 Structure des données

### Chiffre d'affaires (CA)
```
CA Total = Somme de tous les prix des livraisons terminées
```

### Revenus coursiers
```
Revenus coursiers = CA Total - Commission Suzosky
                  = CA Total × (1 - Taux commission)
```

### Commission Suzosky
```
Commission = CA Total × Taux commission
```

### Frais plateforme
```
Frais plateforme = CA Total × Taux plateforme
```

### Frais publicitaires
```
Frais publicitaires = CA Total × Taux publicitaires
```

### Revenus nets Suzosky
```
Revenus nets = Commission - Frais plateforme - Frais publicitaires
```

## 🎨 Design et ergonomie

### Couleurs Suzosky
- **Or principal** : #FFB800 (Accents, boutons, headers)
- **Or secondaire** : #FFA000 (Dégradés)
- **Sombre** : #1a1a1a (Texte principal)
- **Vert succès** : #00AA00 (Revenus nets)
- **Orange** : #FF9800 (Frais)
- **Bleu** : #2196F3 (Informations)

### Interface responsive
- ✅ Desktop (grille adaptative)
- ✅ Tablette (colonnes flexibles)
- ✅ Mobile (colonne unique)

### Animations
- 📊 Barres de progression animées
- 🎯 Cartes interactives (hover effects)
- ⚡ Transitions fluides

## 📊 Sections du module

### 1. En-tête
- Titre avec gradient doré
- Description de la période sélectionnée
- Barre de filtres intégrée

### 2. Métriques principales (6 cartes)
Chaque carte affiche :
- Icône représentative
- Label descriptif
- Valeur en FCFA
- Sous-titre explicatif
- Badge avec pourcentage

### 3. Graphique de répartition
- Visualisation en barres horizontales
- Animation au chargement
- Comparaison visuelle des montants

### 4. Détails revenus Suzosky
- Formule de calcul
- Décomposition des charges
- Mise en évidence de la marge nette

### 5. Performance par coursier
- Tableau complet avec statistiques
- Nombre de livraisons
- CA généré par coursier
- Prix moyen
- Part du CA total

### 6. Historique des taux
- Table des configurations historiques
- Indication du taux actif
- Évolution dans le temps

### 7. Évolution journalière
- CA par jour
- Variation en pourcentage
- Tendances visuelles

## 🔧 Utilisation

### Accès
```
Admin Panel → Finances → Comptabilité
```

### Filtrer une période
1. Sélectionnez la **date de début**
2. Sélectionnez la **date de fin**
3. Cliquez sur **🔍 Filtrer**

### Exporter
#### Excel
```
Cliquez sur 📥 Excel
→ Téléchargement automatique du fichier .xlsx
→ Fichier : comptabilite_suzosky_YYYY-MM-DD_au_YYYY-MM-DD.xlsx
```

#### PDF
```
Cliquez sur 📄 PDF
→ Téléchargement automatique du fichier .pdf
→ Fichier : comptabilite_suzosky_YYYY-MM-DD_au_YYYY-MM-DD.pdf
```

## 📝 Tables de la base de données

### `config_tarification`
```sql
CREATE TABLE config_tarification (
    id INT AUTO_INCREMENT PRIMARY KEY,
    date_application DATETIME NOT NULL,
    taux_commission DECIMAL(5,2) NOT NULL DEFAULT 15.00,
    frais_plateforme DECIMAL(5,2) NOT NULL DEFAULT 5.00,
    frais_publicitaires DECIMAL(5,2) NOT NULL DEFAULT 3.00,
    prix_kilometre INT NOT NULL DEFAULT 100,
    frais_base INT NOT NULL DEFAULT 500,
    supp_km_rate INT NOT NULL DEFAULT 100,
    supp_km_free_allowance DECIMAL(5,2) NOT NULL DEFAULT 0.5,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_date_application (date_application)
);
```

### Requêtes importantes

#### Récupérer le CA total
```sql
SELECT 
    COUNT(*) as nb_livraisons,
    SUM(prix) as ca_total,
    AVG(prix) as prix_moyen
FROM commandes 
WHERE statut = 'livree' 
AND date_creation BETWEEN ? AND ?
```

#### Récupérer les commandes avec taux historiques
```sql
SELECT 
    c.*,
    (SELECT taux_commission FROM config_tarification 
     WHERE date_application <= c.date_creation 
     ORDER BY date_application DESC LIMIT 1) as taux_commission_suzosky,
    (SELECT frais_plateforme FROM config_tarification 
     WHERE date_application <= c.date_creation 
     ORDER BY date_application DESC LIMIT 1) as frais_plateforme,
    (SELECT frais_publicitaires FROM config_tarification 
     WHERE date_application <= c.date_creation 
     ORDER BY date_application DESC LIMIT 1) as frais_publicitaires
FROM commandes c
WHERE c.statut = 'livree'
AND c.date_creation BETWEEN ? AND ?
```

## 🚀 Mises à jour des taux

### Modifier les taux
```
Admin Panel → Finances → Calcul des prix
→ Modifier les sliders
→ Les nouveaux taux s'appliquent immédiatement
→ Historique conservé automatiquement
```

### Impact des changements
- ✅ Les **anciennes commandes** gardent leurs taux d'origine
- ✅ Les **nouvelles commandes** utilisent les nouveaux taux
- ✅ La **comptabilité** reste toujours juste

## 📧 Support

Pour toute question ou problème :
- 📱 Contactez l'équipe technique Suzosky
- 📧 Email : support@suzosky.com
- 🌐 Documentation : docs.suzosky.com

## 📜 Changelog

### Version 1.0.0 (02/10/2025)
- ✨ Version initiale du module comptabilité
- 📊 Métriques complètes (CA, commissions, frais, revenus)
- 📥 Export Excel avec PhpSpreadsheet
- 📄 Export PDF avec TCPDF
- 🎨 Design Suzosky professionnel
- ⚡ Calculs avec taux historiques
- 📈 Statistiques par coursier
- 🔍 Filtres par période
- 📊 Graphiques de répartition
- 📋 Historique des taux

---

**Développé avec ❤️ pour Suzosky**
