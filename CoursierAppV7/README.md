# 📱 Application Android Suzosky Coursier

## 🚦 Clé API Google Maps/Routes utilisée

- **Clé API Android** : `AIzaSyAWcWWwpRx9myEaROuKrOPeL5wfbfQxCmk`
- **Usage** : Cette clé est utilisée pour toutes les fonctionnalités Google Maps, Directions et Routes dans l’application Android.
- **Sécurité** : Elle est restreinte dans la Google Cloud Console aux signatures SHA-1 de l’application et aux APIs nécessaires (Google Maps SDK for Android, Routes API, Directions API).
- **Important** : Une seule clé Android suffit pour toutes les fonctionnalités cartographiques et d’itinéraire. Ne jamais utiliser de clé web ou de fichier JSON dans l’application mobile.

Version native Android (Jetpack Compose) reproduisant fidèlement l'interface web `coursier.php`.

---
## 🎯 Objectifs
- Parité visuelle et fonctionnelle avec l'interface web existante
- Architecture claire et extensible (écran Login → Dashboard → Carte)
- Design system unifié (tokens + composants)
- Préparation intégration API (commandes, statut, paiements)

---
## 🧩 Architecture
```
CoursierAppV7/
  app/
    src/main/java/com/suzosky/coursier/
      MainActivity.kt              <- Navigation & racine thème
      ui/theme/                    <- Tokens couleur, typographie, dimensions
      ui/components/               <- Composants réutilisables (Glass, Buttons, Chips...)
      ui/screens/                  <- LoginScreen, CoursierScreen, MapScreen
      utils/TarificationSuzosky.kt <- Calculs distance / gains (parité logique)
```

### Écrans
- `LoginScreen` : Connexion + Inscription (upload pièces) avec esthétique gradient + verre
- `CoursierScreen` : Header (statut + solde), mini-carte, stats, liste commandes
- `MapScreen` : Affichage itinéraire + tarification dynamique

---
## 🎨 Design System
### Couleurs principales (extraits)
| Token | Rôle |
|-------|------|
| `PrimaryGold` | Accent principal (brand) |
| `PrimaryDark` | Fond sombre principal |
| `GlassBg` | Panneaux translucides |
| `AccentBlue / AccentRed` | Statuts / actions |

Gradients définis : `GradientGoldBrush`, `GradientDarkGoldBrush`, succès / warning / danger.

### Typographie
Police : Montserrat (poids variés). Styles centralisés dans `Type.kt` / `SuzoskyTextStyles`.

### Dimensions
Espacements & rayons dans `Dimens.kt` (ex: `space16`, `radius16`, `radius24`).

---
## 🧱 Composants Clés
| Composant | Description |
|-----------|-------------|
| `GlassContainer` | Conteneur translucide + ombre interne |
| `GradientButton` | Bouton pill gradient gold |
| `StatusChip` | Sélecteur EN_LIGNE / HORS_LIGNE animé |
| `CommandeCard` | Carte d'une commande + actions contextualisées |
| `SuzoskyButton` | Variantes (Primary, Success, Warning, Danger, Secondary, Ghost) |
| `MiniMapPreview` | Carte Google mini intégrée Dashboard |

---
## 🔄 Flux Navigation
`MainActivity` -> NavHost :
- `login`
- `coursier`
- `map`

Callbacks :
- `onOpenMap` → navigation vers `map`
- `onRecharge` → stub incrément solde (à remplacer API / paiement réel)

---
## 🧮 Tarification / Gains
`TarificationSuzosky` fournit :
- Distance formatée
- Durée estimée
- Tarif final (FCFA)
- Attente (surcoût)

---
## 🗺️ Mini‑Carte
`MiniMapPreview` : GoogleMap centrée Abidjan (zoom 11) + overlay gradient léger.

---
## 🔌 Intégrations prévues (prochaines étapes)
1. Appels API réels pour commandes (remplacer mock dans `MainActivity`).
2. Endpoint statut coursier (EN_LIGNE/HORS_LIGNE).
3. Recharge solde (intégration CinetPay ou passerelle interne).
4. Détails commande (dialog / bottom sheet).
5. Tracking temps réel (WebSocket ou polling léger).

### Position / Tracking (service Foreground robuste)
L'application inclut désormais un `LocationForegroundService` robuste qui :

- Démarre en service foreground (notification persistante) pour garantir la continuité en arrière-plan.
- Demande et vérifie les permissions runtime (ACCESS_FINE_LOCATION, ACCESS_BACKGROUND_LOCATION, POST_NOTIFICATIONS si nécessaire).
- Collecte des positions haute précision via FusedLocationProvider.
- Envoie les positions au serveur au format JSON {coursier_id, lat, lng, accuracy} en utilisant le helper `ApiService.updateCoursierPosition(...)`.
- Gère une file locale avec retry/exponential backoff pour résilience réseau (queue + stockage temporaire si hors-ligne).
- Supporte batching (regroupe plusieurs positions si le réseau est lent) et politesses battery-aware (réduit la fréquence si batterie faible).

SharedPreferences : clé utilisée `suzosky_prefs` ; l'ID du coursier est lu depuis `coursier_id` dans ces prefs. Le service expose des actions START/STOP et des helpers dans `MainActivity` pour contrôle manuel.

Assurez-vous que le device peut atteindre l'hôte de développement (voir `local.properties` `debug.localHost`) et que l'utilisateur accorde la permission background de localisation sur Android 10+.

---
## 🧪 QA Visuelle
Ajustements effectués :
- Alpha overlay mini-carte 0.25
- Ombre interne GlassContainer
- Animation statut chips (opacity tween)
- Uniformisation tailles Tab 14sp

À surveiller : densité liste sur petits écrans, dark mode auto, accessibilité (contrast ratios).

---
## 🛠️ Construction & Lancement
Ouvrir projet dans Android Studio Flamingo+.
Synchroniser Gradle puis lancer sur émulateur API 24+.

---
## 📁 Mapping Web → Mobile
| Web | Mobile |
|-----|--------|
| `coursier.php` header | `DashboardHeader` |
| Tableau commandes | `LazyColumn` + `CommandeCard` |
| Boutons statut | `StatusChip` |
| Formulaire login | `LoginScreen` |
| Carte (plein écran) | `MapScreen` |

---
## ♿ Accessibilité (en prévision)
- Ajouter `contentDescription` manquants (icônes secondaires)
- Support tailles dynamiques (fontScale)
- Mode clair (générer palette LightColors)

---
## ⚠️ Limitations actuelles
- Statut persistant non stocké (mémoire volatile)
- Pas encore de cache commandes
- Pas d’état offline / retry réseau
- Éviter les noms de fichiers Kotlin avec accents (ex: `Améliorée`) car certains environnements Windows + daemon Kotlin provoquent des erreurs de chemin; fichier renommé en `CommandeCardAmelioree.kt`.

---
## 🚀 Personnalisation rapide
1. Changer brand : modifier `PrimaryGold` / gradient dans `Color.kt`.
2. Ajuster arrondis : éditer `Dimens.kt`.
3. Ajouter variante bouton : étendre `SuzoskyButtonStyle`.

---
## 📄 Licence / Droits
Code interne propriété Suzosky (non publié open-source). Usage restreint.

---
## ✍️ Auteurs
Refonte Android native assistée par génération automatisée (2025).

---
## 🔁 Miroir Documentation
Copie synchronisée aussi dans `DOCUMENTATION_FINALE/README_ANDROID.md`.

