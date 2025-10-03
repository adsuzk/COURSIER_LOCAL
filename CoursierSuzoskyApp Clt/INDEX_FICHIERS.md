# 📁 INDEX DES FICHIERS - Application Client Suzosky

Navigation rapide dans tous les fichiers du projet.

---

## 📚 DOCUMENTATION (À LIRE EN PREMIER)

### 🌟 Pour Commencer
| Fichier | Description | Pour Qui |
|---------|-------------|----------|
| **RESUME_CLIENT.md** | ⭐ Résumé simple en français | **Vous (le client)** |
| **GUIDE_DEMARRAGE_RAPIDE.md** | ⚡ Setup et premier lancement | Développeur |
| **README_CLIENT_APP.md** | 📖 Documentation technique complète | Développeur |

### 📊 Analyse & Historique
| Fichier | Description | Utilité |
|---------|-------------|---------|
| **COMPARAISON_DESIGN.md** | Comparaison Web vs Android | Design review |
| **CHANGELOG_CLIENT.md** | Historique des versions | Suivi évolution |
| **REALISATION_COMPLETE.md** | Résumé technique de tout | Overview complet |

### 👥 Pour l'Équipe
| Fichier | Description | Utilité |
|---------|-------------|---------|
| **CONTRIBUTING.md** | Guide de contribution | Nouveaux développeurs |
| **README_NETWORK.md** | Config réseau (existant) | Debug connexion |

---

## 💻 CODE SOURCE

### 📱 Écrans (UI)
```
app/src/main/java/com/example/coursiersuzosky/ui/
```

| Fichier | Description | Écran Correspondant |
|---------|-------------|---------------------|
| **HomeScreen.kt** | Page d'accueil | 🏠 Accueil |
| **ServicesScreen.kt** | Liste des services | 🚛 Services |
| **OrderScreen.kt** | Formulaire commande | 📦 Commander |
| **ProfileScreen.kt** | Profil utilisateur | 👤 Profil |
| **LoginScreen.kt** | Connexion | 🔐 Login |

### 🎨 Design System
```
app/src/main/java/com/example/coursiersuzosky/ui/theme/
```

| Fichier | Description | Contenu |
|---------|-------------|---------|
| **Color.kt** | Couleurs Suzosky | Gold, Dark, SecondaryBlue, etc. |
| **Theme.kt** | Thème Material 3 | Configuration thème |
| **Type.kt** | Typographie | Styles de texte |

### 🌐 Réseau & API
```
app/src/main/java/com/example/coursiersuzosky/net/
```

| Fichier | Description | Responsabilité |
|---------|-------------|----------------|
| **ApiClient.kt** | Client HTTP | OkHttp, cookies |
| **ApiService.kt** | Endpoints API | Appels réseau |
| **ApiConfig.kt** | Configuration | URLs, timeouts |
| **SessionManager.kt** | Gestion session | Login/logout |
| **OrderModels.kt** | Models commandes | OrderRequest, OrderResponse |
| **AuthModels.kt** | Models auth | LoginRequest, LoginResponse |
| **DistanceModels.kt** | Models distance | DistanceRequest, DistanceResponse |
| **PersistentCookieJar.kt** | Cookies persistants | Stockage cookies |

### 🎯 Navigation & Main
```
app/src/main/java/com/example/coursiersuzosky/
```

| Fichier | Description | Rôle |
|---------|-------------|------|
| **MainActivity.kt** | Point d'entrée | Navigation, auth, diagnostics |

---

## ⚙️ CONFIGURATION

### Build & Gradle
| Fichier | Description | Utilité |
|---------|-------------|---------|
| **app/build.gradle.kts** | Configuration build | Dépendances, SDK versions |
| **build.gradle.kts** | Configuration projet | Settings globaux |
| **settings.gradle.kts** | Modules | Liste modules |
| **gradle.properties** | Propriétés | LOCAL_LAN_IP, etc. |
| **local.properties** | Config locale | SDK path (auto-généré) |

### Android Resources
| Dossier/Fichier | Description | Contenu |
|-----------------|-------------|---------|
| **app/src/main/res/values/strings.xml** | Chaînes texte | API keys Google |
| **app/src/main/res/drawable/** | Images/icons | Logos, icons |
| **app/src/main/AndroidManifest.xml** | Manifest | Permissions, config app |

---

## 🗺️ STRUCTURE COMPLÈTE DU PROJET

```
CoursierSuzoskyApp Clt/
│
├── 📚 DOCUMENTATION
│   ├── RESUME_CLIENT.md                    ⭐ COMMENCER ICI
│   ├── GUIDE_DEMARRAGE_RAPIDE.md
│   ├── README_CLIENT_APP.md
│   ├── COMPARAISON_DESIGN.md
│   ├── CHANGELOG_CLIENT.md
│   ├── REALISATION_COMPLETE.md
│   ├── CONTRIBUTING.md
│   ├── README_NETWORK.md
│   └── GUIDE_TEST.md
│
├── 📱 APP SOURCE CODE
│   └── app/
│       ├── src/
│       │   └── main/
│       │       ├── java/com/example/coursiersuzosky/
│       │       │   ├── MainActivity.kt               # Navigation principale
│       │       │   ├── ui/
│       │       │   │   ├── HomeScreen.kt            # 🏠 Accueil
│       │       │   │   ├── ServicesScreen.kt        # 🚛 Services
│       │       │   │   ├── OrderScreen.kt           # 📦 Commander
│       │       │   │   ├── ProfileScreen.kt         # 👤 Profil
│       │       │   │   ├── LoginScreen.kt           # 🔐 Login
│       │       │   │   └── theme/
│       │       │   │       ├── Color.kt             # Couleurs
│       │       │   │       ├── Theme.kt             # Thème
│       │       │   │       └── Type.kt              # Typo
│       │       │   └── net/
│       │       │       ├── ApiClient.kt             # HTTP Client
│       │       │       ├── ApiService.kt            # API Endpoints
│       │       │       ├── ApiConfig.kt             # Config
│       │       │       ├── SessionManager.kt        # Session
│       │       │       ├── OrderModels.kt           # Models
│       │       │       ├── AuthModels.kt
│       │       │       ├── DistanceModels.kt
│       │       │       └── PersistentCookieJar.kt
│       │       │
│       │       ├── res/
│       │       │   ├── values/
│       │       │   │   └── strings.xml              # API Keys ici
│       │       │   ├── drawable/
│       │       │   └── ...
│       │       │
│       │       └── AndroidManifest.xml              # Manifest
│       │
│       ├── build.gradle.kts                         # Config build
│       └── proguard-rules.pro
│
├── ⚙️ CONFIGURATION GRADLE
│   ├── build.gradle.kts                             # Config projet
│   ├── settings.gradle.kts                          # Modules
│   ├── gradle.properties                            # Propriétés
│   └── gradle/
│
└── 🔧 AUTRES
    ├── .gitignore
    ├── .vscode/
    └── local.properties
```

---

## 🎯 GUIDE DE NAVIGATION RAPIDE

### Je veux...

#### ...comprendre le projet
1. Lire **RESUME_CLIENT.md** (résumé simple)
2. Lire **REALISATION_COMPLETE.md** (résumé technique)

#### ...lancer l'application
1. Lire **GUIDE_DEMARRAGE_RAPIDE.md**
2. Configurer `strings.xml` (API keys)
3. Configurer `gradle.properties` (LOCAL_LAN_IP)
4. Run ▶️

#### ...modifier le design
1. Consulter **COMPARAISON_DESIGN.md**
2. Éditer `ui/theme/Color.kt` (couleurs)
3. Éditer `ui/theme/Theme.kt` (thème)

#### ...ajouter un écran
1. Lire **CONTRIBUTING.md** (guidelines)
2. Créer `ui/MonNouvelEcran.kt`
3. Ajouter route dans `MainActivity.kt`
4. Ajouter item dans Bottom Navigation

#### ...modifier un écran existant
- **Accueil** → Éditer `ui/HomeScreen.kt`
- **Services** → Éditer `ui/ServicesScreen.kt`
- **Commander** → Éditer `ui/OrderScreen.kt`
- **Profil** → Éditer `ui/ProfileScreen.kt`

#### ...changer l'API
1. Éditer `net/ApiConfig.kt` (URLs)
2. Éditer `net/ApiService.kt` (endpoints)
3. Éditer `app/build.gradle.kts` (BASE_URL)

#### ...débugger un problème
1. Consulter **GUIDE_DEMARRAGE_RAPIDE.md** (section troubleshooting)
2. Utiliser diagnostics intégrés (🐛 en mode DEBUG)
3. Voir logs : `adb logcat`

#### ...contribuer au projet
1. Lire **CONTRIBUTING.md** (guide complet)
2. Suivre les conventions
3. Créer une branche feature
4. Submit PR

---

## 📊 MÉTRIQUES DU PROJET

### Documentation
- **Nombre de docs** : 8 fichiers
- **Mots totaux** : ~18,000 mots
- **Pages équivalentes** : ~60 pages
- **Couverture** : 100%

### Code Source
- **Écrans** : 5 (Home, Services, Order, Profile, Login)
- **Composables** : 30+ réutilisables
- **Lignes de code** : ~2000 lignes Kotlin
- **Fichiers** : 15+ fichiers sources

### Architecture
- **Layers** : 2 (UI, Network)
- **Packages** : 3 (ui, net, theme)
- **Technologies** : 8 principales

---

## 🔍 RECHERCHE RAPIDE

### Par Fonctionnalité

| Je cherche... | Fichier à ouvrir |
|---------------|------------------|
| Couleurs Suzosky | `ui/theme/Color.kt` |
| Hero section | `ui/HomeScreen.kt` ligne ~60 |
| Cartes services | `ui/ServicesScreen.kt` ligne ~70 |
| Formulaire commande | `ui/OrderScreen.kt` ligne ~80 |
| Menu profil | `ui/ProfileScreen.kt` ligne ~90 |
| Appels API | `net/ApiService.kt` |
| Configuration réseau | `net/ApiConfig.kt` |
| Navigation | `MainActivity.kt` ligne ~100 |
| Bottom Nav | `MainActivity.kt` ligne ~150 |
| Diagnostics | `MainActivity.kt` ligne ~200 |

### Par Problème

| Problème | Solution dans |
|----------|---------------|
| Erreur build | **GUIDE_DEMARRAGE_RAPIDE.md** section "Problème build" |
| Carte ne s'affiche pas | **GUIDE_DEMARRAGE_RAPIDE.md** section "Carte" |
| Network error | **README_NETWORK.md** |
| Places API error | **GUIDE_DEMARRAGE_RAPIDE.md** section "Places" |
| Design différent | **COMPARAISON_DESIGN.md** |

---

## ✅ CHECKLIST NOUVEAU DÉVELOPPEUR

Quand vous rejoignez le projet :

- [ ] Lire **RESUME_CLIENT.md**
- [ ] Lire **GUIDE_DEMARRAGE_RAPIDE.md**
- [ ] Lire **CONTRIBUTING.md**
- [ ] Setup environnement (Android Studio, SDK)
- [ ] Configurer API keys dans `strings.xml`
- [ ] Configurer `LOCAL_LAN_IP` dans `gradle.properties`
- [ ] Lancer l'app en mode DEBUG
- [ ] Tester les 4 écrans
- [ ] Consulter les diagnostics (🐛)
- [ ] Lire le code de `HomeScreen.kt` (exemple)
- [ ] Prêt à contribuer ! 🚀

---

## 📞 BESOIN D'AIDE ?

### Documentation par Niveau

**Débutant :**
1. RESUME_CLIENT.md (français simple)
2. GUIDE_DEMARRAGE_RAPIDE.md (setup)

**Intermédiaire :**
1. README_CLIENT_APP.md (doc complète)
2. COMPARAISON_DESIGN.md (design)

**Avancé :**
1. CONTRIBUTING.md (guidelines)
2. Code source directement

---

**Navigation rapide créée ! Utilisez cet index pour trouver rapidement ce que vous cherchez. 🎯**
