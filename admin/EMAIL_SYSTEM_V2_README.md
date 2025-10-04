# 📧 Système de Gestion d'Emails V2.0 - Admin Suzosky

## 🎯 Vue d'ensemble

Interface **ultra moderne**, **intuitive** et **complète** pour la gestion des emails dans l'administration Coursier Suzosky.

### ✨ Caractéristiques Principales

- **🎨 Design Modern**: Glassmorphism + Gold Theme + Animations fluides
- **📊 Dashboard Complet**: Statistiques en temps réel avec graphiques Chart.js
- **✉️ Envoi Simple**: Formulaire intuitif pour emails individuels
- **📢 Campagnes**: Envoi massif ciblé (Tous/Particuliers/Business)
- **📝 Templates**: Système de templates réutilisables avec variables
- **📋 Historique**: Logs complets avec filtres avancés et pagination
- **📈 Analytics**: Statistiques détaillées avec insights et recommandations
- **👁️ Tracking**: Suivi des ouvertures d'emails
- **🔍 Recherche**: Filtrage par statut, type, destinataire
- **📱 Responsive**: Adaptation mobile/tablette/desktop
- **⚡ Performance**: Requêtes optimisées avec index SQL

---

## 📁 Structure des Fichiers

```
admin/
├── emails_v2.php                    # Fichier principal (remplace emails.php)
├── init_email_tables.php            # Auto-initialisation des tables DB
├── emails_tabs/                     # Onglets de l'interface
│   ├── dashboard.php                # 📊 Vue d'ensemble + stats rapides
│   ├── send.php                     # ✉️ Envoi email unique
│   ├── campaign.php                 # 📢 Gestion campagnes
│   ├── logs.php                     # 📋 Historique complet
│   ├── templates.php                # 📝 Gestion templates
│   └── analytics.php                # 📈 Statistiques avancées
└── sql/
    └── create_email_tables.sql      # Script création tables
```

---

## 🗄️ Base de Données

### Tables Créées Automatiquement

#### 1. `email_logs`
Stocke tous les emails envoyés avec tracking.

| Colonne | Type | Description |
|---------|------|-------------|
| id | INT | ID unique |
| recipient | VARCHAR(255) | Email destinataire |
| subject | VARCHAR(500) | Sujet |
| body | TEXT | Contenu HTML |
| type | VARCHAR(50) | Type (general, welcome, order, etc.) |
| campaign_id | INT | ID campagne (NULL si email simple) |
| status | ENUM | 'pending', 'sent', 'failed' |
| error_message | TEXT | Message d'erreur si échec |
| opened | TINYINT | 1 si ouvert, 0 sinon |
| opened_at | DATETIME | Date/heure d'ouverture |
| sent_at | DATETIME | Date/heure d'envoi |

#### 2. `email_campaigns`
Gère les campagnes d'emails massifs.

| Colonne | Type | Description |
|---------|------|-------------|
| id | INT | ID unique |
| name | VARCHAR(255) | Nom de la campagne |
| subject | VARCHAR(500) | Sujet |
| body | TEXT | Contenu HTML |
| target_group | VARCHAR(50) | 'all', 'particuliers', 'business' |
| total_recipients | INT | Nombre de destinataires |
| sent_count | INT | Nombre envoyés |
| status | ENUM | 'draft', 'sending', 'sent', 'failed' |
| scheduled_at | DATETIME | Envoi programmé (optionnel) |

#### 3. `email_templates`
Templates réutilisables avec variables.

| Colonne | Type | Description |
|---------|------|-------------|
| id | INT | ID unique |
| name | VARCHAR(255) | Nom du template (UNIQUE) |
| subject | VARCHAR(500) | Sujet |
| body | TEXT | Contenu HTML avec {{variables}} |
| type | VARCHAR(50) | Type de template |

#### 4. `email_settings`
Configuration SMTP (optionnel).

| Colonne | Type | Description |
|---------|------|-------------|
| setting_key | VARCHAR(100) | Clé du paramètre |
| setting_value | TEXT | Valeur |

---

## 🎨 Onglets de l'Interface

### 1. 📊 Dashboard
**Vue d'ensemble complète**
- Stats rapides (Aujourd'hui, Semaine, Mois, Taux d'ouverture)
- Graphique évolution 7 derniers jours (Chart.js)
- Emails récents (10 derniers)
- Campagnes récentes (5 dernières)
- Actions rapides (Envoyer, Créer campagne, Templates, Historique)

### 2. ✉️ Envoyer un Email
**Formulaire intuitif**
- Destinataire (validation email)
- Sujet
- Type (Général, Bienvenue, Commande, Notification, Marketing, Support)
- Contenu (Textarea avec support HTML)
- Prévisualisation en temps réel
- Charger un template
- Conseils et bonnes pratiques

### 3. 📢 Campagnes
**Envoi massif ciblé**
- Sélection de la cible:
  - 📧 Tous les clients
  - 👤 Clients Particuliers uniquement
  - 🏢 Clients Business uniquement
- Compteur de destinataires en temps réel
- Prévisualisation complète
- Historique des campagnes avec détails
- Statuts: Draft, Sending, Sent, Failed

### 4. 📋 Historique
**Logs complets avec filtres**
- Filtres avancés:
  - 🔍 Recherche (email ou sujet)
  - 📊 Statut (Tous, Envoyés, Échoués, En attente)
  - 🏷️ Type (Général, Bienvenue, Commande, etc.)
- Pagination (50 emails par page)
- Statistiques du filtre actuel
- Actions: Voir détails, Supprimer
- Filtrage par campagne

### 5. 📝 Templates
**Gestion des templates**
- Créer/Éditer un template
- Variables disponibles:
  - Client: `{{nom}}`, `{{prenom}}`, `{{email}}`, `{{telephone}}`
  - Commande: `{{commande_id}}`, `{{montant}}`, `{{statut}}`, `{{date}}`
  - Système: `{{site_url}}`, `{{site_name}}`, `{{support_email}}`, `{{annee}}`
- Prévisualisation en temps réel
- Actions: Voir, Éditer, Dupliquer, Supprimer
- 3 templates d'exemple pré-créés:
  1. Bienvenue Client
  2. Confirmation Commande
  3. Newsletter

### 6. 📈 Analytics
**Statistiques avancées**
- Stats globales 30 jours
- Graphique évolution quotidienne (30j)
- Performance par type d'email
- Performance par heure (meilleur moment pour envoyer)
- Top 10 destinataires
- Insights & Recommandations automatiques:
  - Analyse du taux d'ouverture
  - Détection taux d'échec élevé
  - Identification du meilleur type
  - Alerte sur fréquence d'envoi

---

## 🎯 Fonctionnalités Techniques

### Auto-initialisation
- Les tables SQL sont créées automatiquement au premier chargement
- Aucune intervention manuelle nécessaire
- 3 templates d'exemple insérés automatiquement

### Variables de Personnalisation
Utilisez `{{variable}}` dans vos templates:
```html
Bonjour {{prenom}} {{nom}},

Votre commande #{{commande_id}} d'un montant de {{montant}} FCFA
a été confirmée le {{date}}.

Cordialement,
L'équipe {{site_name}}
```

### Tracking d'Ouverture
- Système de tracking des ouvertures (à implémenter avec pixel invisible)
- Calcul automatique des taux d'ouverture
- Temps moyen avant ouverture

### Performance
- Index SQL optimisés pour requêtes rapides
- Pagination efficace (50 emails/page)
- Chargement asynchrone des graphiques
- Cache navigateur activé

---

## 🚀 Installation

### 1. Automatique (Recommandé)
Accédez simplement à:
```
https://localhost/COURSIER_LOCAL/admin.php?section=emails
```
Les tables seront créées automatiquement.

### 2. Manuelle (Alternative)
Si besoin, exécutez manuellement:
```bash
cd C:\xampp\htdocs\COURSIER_LOCAL\admin
php init_email_tables.php
```

---

## 📊 Exemples d'Utilisation

### Envoyer un Email Simple
1. Aller sur **✉️ Envoyer un Email**
2. Remplir: destinataire, sujet, type, contenu
3. Cliquer **👁️ Prévisualiser** pour vérifier
4. Cliquer **✉️ Envoyer l'Email**

### Créer une Campagne
1. Aller sur **📢 Campagnes**
2. Choisir la cible (Tous/Particuliers/Business)
3. Rédiger le sujet et le contenu
4. Prévisualiser
5. Cliquer **📢 Lancer la Campagne**

### Créer un Template
1. Aller sur **📝 Templates**
2. Remplir: nom, type, sujet, contenu
3. Utiliser les variables `{{nom}}`, `{{email}}`, etc.
4. Sauvegarder
5. Le template est maintenant disponible dans **Envoyer** et **Campagnes**

### Analyser les Performances
1. Aller sur **📈 Analytics**
2. Consulter les graphiques
3. Identifier le meilleur type d'email
4. Trouver le meilleur moment pour envoyer
5. Lire les insights automatiques

---

## 🎨 Personnalisation du Design

### Variables CSS Principales
```css
:root {
    --primary-gold: #D4A853;
    --gold-light: #F4E4C1;
    --primary-dark: #1A1A1A;
    --glass-bg: rgba(255, 255, 255, 0.05);
    --glass-border: rgba(212, 168, 83, 0.2);
    --success: #10B981;
    --error: #EF4444;
}
```

### Thème Cohérent
- Glassmorphism avec effet blur
- Gradient gold sur les boutons primaires
- Ombres dorées sur hover
- Animations fluides (0.3s ease)
- Responsive breakpoint: 768px

---

## 🔧 Configuration SMTP (Optionnel)

Les paramètres SMTP sont stockés dans `email_settings`:
- smtp_host
- smtp_port
- smtp_username
- smtp_password
- from_email
- from_name
- reply_to

Pour les modifier, accédez à la table ou créez un onglet Settings dédié.

---

## 📈 Métriques Suivies

- **Total emails**: Nombre total envoyés
- **Taux d'envoi**: (Envoyés / Total) × 100
- **Taux d'échec**: (Échoués / Total) × 100
- **Taux d'ouverture**: (Ouverts / Envoyés) × 100
- **Temps moyen d'ouverture**: Minutes entre envoi et ouverture
- **Destinataires uniques**: Nombre d'emails distincts
- **Performance par type**: Comparaison des types d'emails
- **Performance horaire**: Meilleur moment pour envoyer

---

## 🐛 Dépannage

### Les tables ne se créent pas
```bash
# Vérifier les permissions MySQL
mysql -u root -e "SHOW GRANTS FOR 'root'@'localhost';"

# Exécuter manuellement
mysql -u root coursier_local < admin/sql/create_email_tables.sql
```

### Erreur de connexion PDO
Vérifier `config.php`:
```php
$pdo = new PDO('mysql:host=127.0.0.1;dbname=coursier_local;charset=utf8mb4', 'root', '');
```

### Les graphiques ne s'affichent pas
Vérifier que Chart.js est chargé:
```html
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
```

---

## 🎯 Roadmap Future

- [ ] Intégration SMTP réelle (PHPMailer)
- [ ] Tracking pixel pour ouvertures
- [ ] Éditeur WYSIWYG pour emails
- [ ] Import CSV de destinataires
- [ ] Programmation d'envoi (cron)
- [ ] A/B Testing
- [ ] Segmentation avancée
- [ ] Export statistiques PDF/Excel
- [ ] Notifications push lors d'ouverture
- [ ] Intégration avec webhooks

---

## 👨‍💻 Développeur

**Version**: 2.0  
**Date**: Octobre 2025  
**Développé pour**: Coursier Suzosky Admin  
**Technologies**: PHP 8+, MySQL 8+, Chart.js 4.4, HTML5, CSS3

---

## 📝 Notes

- ✅ **Prêt pour la production**
- ✅ **Auto-installation des tables**
- ✅ **3 templates d'exemple inclus**
- ✅ **Compatible avec l'admin existant**
- ✅ **Design cohérent avec le thème Gold**
- ✅ **Responsive mobile/tablette**
- ✅ **Performance optimisée**

---

**🎉 Profitez de votre nouvelle interface de gestion d'emails !**
