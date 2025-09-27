# Android – Google Maps: clé API et vérifications

Ce guide explique comment faire fonctionner la carte Google Maps dans l’application Android (package `com.suzosky.coursier`) de manière fiable et sécurisée.

## 1) Utiliser une clé Android distincte de la clé Web
- N’utilisez pas la même clé que celle du site Web (JS). Créez une clé API Google Maps dédiée à Android.
- Type de restriction: « Clé restreinte Android ».
- Restrictions requises:
  - Nom de package: `com.suzosky.coursier`
  - Empreinte SHA-1 (debug et release si vous testez les deux)

Services à activer pour cette clé Android:
- Maps SDK for Android
- Places API (si vous utilisez l’autocomplétion ou les détails de lieux)

## 2) Récupérer les SHA-1 (debug et release)
Vous avez deux options:
- Android Studio: Ouvrez la fenêtre Gradle > votre module `app` > Tasks > android > `signingReport`. Le panneau Run affiche `Variant: debug` et `Variant: release` avec les SHA-1.
- Ou via le keystore directement (non détaillé ici).

Conservez:
- SHA-1 Debug (keystore debug Android Studio)
- SHA-1 Release (votre keystore de production si signé en local)

Ajoutez ces SHA-1 dans les restrictions de la clé Android avec le package `com.suzosky.coursier`.

## 3) Où placer la clé dans le projet
L’application lit la clé dans le manifest via `@string/google_maps_key`:
- Fichier: `CoursierAppV7/app/src/main/res/values/strings.xml`
- Entrée: `<string name="google_maps_key">VOTRE_CLE_ANDROID</string>`

Optionnel (plus sécurisé): déplacer la clé dans `local.properties` et la passer par `build.gradle` avec `resValue`/`manifestPlaceholders`. On peut le faire ensuite si vous souhaitez masquer la clé dans le repo.

## 4) Vérifier côté code et dépendances
Déjà en place:
- Manifest utilise `@string/google_maps_key` et le thème `@style/Theme.SuzoskyCoursier`.
- Dépendances Maps/Location unifiées (Maps 19.0.0, Location 21.3.0, Maps Compose 4.3.3).
- La requête Directions (si utilisée) s’exécute en arrière-plan (coroutines), évitant les ANR.

Important: accordez les permissions de localisation au démarrage (l’application les demande). Assurez-vous que « Google Play Services » du téléphone est à jour.

## 5) Tests et erreurs fréquentes
- Premier lancement: la carte doit s’afficher (fond Google) même sans marqueurs.
- Si la carte est grise avec un message « For development purposes only »/« API key not authorized »:
  - La clé n’est pas de type Android ou les restrictions (package/SHA-1) ne correspondent pas.
  - Vérifiez le package `com.suzosky.coursier` et la bonne SHA-1 (debug vs release).
- Si rien ne s’affiche et aucun message clair:
  - Activez Logcat et filtrez « Google Maps Android API ».
  - Vérifiez la connectivité et les permissions.

## 6) Nettoyage du projet (fait)
- Code de démonstration/clone exclu du build:
  - `app/build.gradle.kts` exclut `**/com/suzoskycoursier/clonecoursierapp/**` et `**/MainActivityDiagnostic.kt`.
- Thème de prod: `Theme.SuzoskyCoursier` appliqué dans le Manifest et `res/values/themes.xml`.

Note: les fichiers de démonstration peuvent encore exister sur disque; ils ne sont plus compilés. Vous pouvez les supprimer manuellement si souhaité:
- `app/src/main/java/com/suzoskycoursier/clonecoursierapp/**`
- `app/src/main/java/com/suzosky/coursier/MainActivityDiagnostic.kt`

## 7) Checklist rapide
- [ ] Clé Android créée et restreinte (package + SHA-1)
- [ ] `strings.xml` mis à jour avec la clé Android
- [ ] App installée sur un appareil avec Google Play Services
- [ ] Permissions de localisation accordées
- [ ] La carte apparaît et suit la position quand activée

Besoin d’aide pour générer/installer la clé ou pour masquer la clé dans le build (resValue/manifestPlaceholders)? Dites-moi et je l’automatise.
