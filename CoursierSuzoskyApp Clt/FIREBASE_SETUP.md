# Firebase setup (Android)

1. Dans Firebase Console, ajoute l’application Android avec l’ID de package:
   - com.suzosky.coursierclient
   - Fournis l’empreinte SHA-1 (debug) fournie précédemment.
2. Télécharge le fichier google-services.json et place-le ici:
   - app/google-services.json
3. (Optionnel) Ajoute la SHA-1 release si tu signes avec un keystore de prod.
4. Active Realtime Database et règles de lecture/écriture adaptées.
5. Relance une compilation.

Ce projet inclut:
- Plugin Google Services
- Dépendances Firebase BoM + firebase-database-ktx
- Un wrapper `RealtimeManager` (lecture/écriture + Flow)

Tu pourras ensuite souscrire aux positions coursier et statuts commande.
