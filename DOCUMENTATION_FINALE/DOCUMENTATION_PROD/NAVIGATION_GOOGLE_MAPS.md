# Navigation Google Maps dans la timeline (Coursier)

Objectif: Afficher une carte intégrée avec l’itinéraire pour chaque étape de la livraison et permettre au coursier de lancer la navigation vocale Google Maps (avec possibilité de couper le son via l’UI Google, comme d’habitude).

## 1) Pré-requis Google Cloud

Activer dans le projet GCP:
- Maps SDK for Android (affichage de la carte dans l’app)
- Directions API (récupération d’itinéraires)
- (Optionnel) Geocoding API / Places API si vous utilisez des adresses/POI

Clés à utiliser (séparer par usage):
- Clé Android (restreinte par SHA-1 + package) pour Maps SDK (tiles)
- Clé serveur (restreinte par IP/Hôte) pour Directions API via un proxy backend (recommandé)

## 2) Sécurisation des clés

- NE PAS embarquer une clé serveur dans l’APK. Utiliser le proxy `api/directions_proxy.php`.
- Sur le serveur, placez la clé Directions dans une variable d’environnement `GOOGLE_DIRECTIONS_API_KEY` ou dans `data/secret_google_directions_key.txt` (non versionné).

## 3) Proxy Directions côté serveur

Endpoint ajouté: `api/directions_proxy.php`

Paramètres (GET):
- `origin=lat,lng` (obligatoire)
- `destination=lat,lng` (obligatoire)
- `mode=driving|walking|transit|bicycling|two_wheeler` (défaut: driving)
- `language` (défaut: fr)
- `region` (défaut: ci)
- `waypoints=lat1,lng1|lat2,lng2` (optionnel)
- `avoid=tolls|highways|ferries` (optionnel)
- `alternatives=true|false` (défaut: false)

Réponse: `{ ok: true, directions: <payload JSON Google> }` ou `{ ok: false, error: "..." }`

Exemple:
```
GET /api/directions_proxy.php?origin=5.3575,-4.0083&destination=5.3167,-4.0033&mode=driving&language=fr&region=ci
```

## 4) Android – Dépendances

Build Gradle (module):
- com.google.maps.android:maps-compose
- com.google.android.gms:play-services-maps
- com.google.android.gms:play-services-location (si vous affichez la position courante)
- Retrofit/OkHttp ou Ktor pour interroger le proxy Directions

AndroidManifest:
- meta-data `com.google.android.geo.API_KEY` avec la clé Android (restreinte)
- permissions: ACCESS_FINE_LOCATION / ACCESS_COARSE_LOCATION (si localisation)
- uses-feature: `android.hardware.location.gps`

## 5) Android – Client Directions (Retrofit)

Interface:
```kotlin
interface DirectionsService {
    @GET("/api/directions_proxy.php")
    suspend fun getDirections(
        @Query("origin") origin: String,
        @Query("destination") destination: String,
        @Query("mode") mode: String = "driving",
        @Query("language") language: String = "fr",
        @Query("region") region: String = "ci",
        @Query("waypoints") waypoints: String? = null,
        @Query("alternatives") alternatives: String = "false",
        @Query("avoid") avoid: String? = null,
    ): DirectionsProxyResponse
}

data class DirectionsProxyResponse(
    val ok: Boolean,
    val directions: DirectionsResponse?,
    val error: String? = null
)

data class DirectionsResponse(
    val routes: List<Route> = emptyList()
)

data class Route(
    val overview_polyline: Polyline? = null
)

data class Polyline(val points: String)
```

Décodage polyline:
```kotlin
fun decodePolyline(poly: String): List<LatLng> {
    val len = poly.length
    var index = 0
    val path = mutableListOf<LatLng>()
    var lat = 0
    var lng = 0
    while (index < len) {
        var b: Int
        var shift = 0
        var result = 0
        do {
            b = poly[index++].code - 63
            result = result or ((b and 0x1f) shl shift)
            shift += 5
        } while (b >= 0x20)
        val dlat = if ((result and 1) != 0) (result shr 1).inv() else (result shr 1)
        lat += dlat

        shift = 0
        result = 0
        do {
            b = poly[index++].code - 63
            result = result or ((b and 0x1f) shl shift)
            shift += 5
        } while (b >= 0x20)
        val dlng = if ((result and 1) != 0) (result shr 1).inv() else (result shr 1)
        lng += dlng

        path += LatLng(lat / 1E5, lng / 1E5)
    }
    return path
}
```

## 6) Android – Composable MapNavigationCard

Affiche la carte, place Pickup et Dropoff, trace la polyline, ajuste la caméra, et propose un bouton pour lancer Google Maps (voix). Sélection de la cible selon l’étape:
- Avant pickup (ACCEPTED, EN_ROUTE_PICKUP, PICKUP_ARRIVED): destination = pickup
- Après pickup (PICKED_UP, EN_ROUTE_DELIVERY, DELIVERY_ARRIVED): destination = dropoff

Intent Google Maps (voix):
```kotlin
fun launchTurnByTurn(context: Context, dest: LatLng, label: String? = null) {
    val uri = Uri.parse("google.navigation:q=${'$'}{dest.latitude},${'$'}{dest.longitude}&mode=d")
    val intent = Intent(Intent.ACTION_VIEW, uri).apply {
        setPackage("com.google.android.apps.maps")
    }
    try {
        context.startActivity(intent)
    } catch (e: ActivityNotFoundException) {
        // Fallback: ouvrir la carte simple
        val gmmIntentUri = Uri.parse("geo:0,0?q=${'$'}{dest.latitude},${'$'}{dest.longitude}(${label ?: "Destination"})")
        context.startActivity(Intent(Intent.ACTION_VIEW, gmmIntentUri))
    }
}
```

Carte Compose (extrait):
```kotlin
@OptIn(MapsComposeExperimentalApi::class)
@Composable
fun MapNavigationCard(
    modifier: Modifier = Modifier,
    courierLocation: LatLng?,
    pickup: LatLng?,
    dropoff: LatLng?,
    step: DeliveryStep,
    directionsService: DirectionsService,
    onStartNavigation: (LatLng) -> Unit,
) {
    val context = LocalContext.current
    val mapUiSettings = remember { MapUiSettings(zoomControlsEnabled = false) }
    val mapProperties = remember { MapProperties(isMyLocationEnabled = courierLocation != null) }

    val (origin, destination) = remember(courierLocation, pickup, dropoff, step) {
        val dest = when (step) {
            DeliveryStep.ACCEPTED, DeliveryStep.EN_ROUTE_PICKUP, DeliveryStep.PICKUP_ARRIVED -> pickup
            DeliveryStep.PICKED_UP, DeliveryStep.EN_ROUTE_DELIVERY, DeliveryStep.DELIVERY_ARRIVED, DeliveryStep.DELIVERED, DeliveryStep.CASH_CONFIRMED -> dropoff
            else -> null
        }
        courierLocation to dest
    }

    var path by remember { mutableStateOf<List<LatLng>>(emptyList()) }

    LaunchedEffect(origin, destination) {
        path = emptyList()
        val o = origin
        val d = destination
        if (o != null && d != null) {
            runCatching {
                val resp = directionsService.getDirections(
                    origin = "${'$'}{o.latitude},${'$'}{o.longitude}",
                    destination = "${'$'}{d.latitude},${'$'}{d.longitude}",
                    mode = "driving",
                    language = "fr",
                    region = "ci"
                )
                if (resp.ok == true) {
                    val points = resp.directions?.routes?.firstOrNull()?.overview_polyline?.points
                    if (!points.isNullOrBlank()) {
                        path = decodePolyline(points)
                    }
                }
            }
        }
    }

    Column(modifier) {
        GoogleMap(
            modifier = Modifier
                .fillMaxWidth()
                .height(200.dp)
                .clip(RoundedCornerShape(12.dp)),
            properties = mapProperties,
            uiSettings = mapUiSettings,
            onMapLoaded = {
                // Ajustement caméra
            }
        ) {
            courierLocation?.let { Marker(state = MarkerState(it), title = "Vous") }
            pickup?.let { Marker(state = MarkerState(it), title = "Pickup") }
            dropoff?.let { Marker(state = MarkerState(it), title = "Livraison") }
            if (path.isNotEmpty()) {
                Polyline(points = path, color = Color(0xFF0B57D0), width = 10f)
            }
        }
        Spacer(Modifier.height(8.dp))
        val dest = destination
        Button(
            onClick = { if (dest != null) onStartNavigation(dest) },
            enabled = dest != null,
            modifier = Modifier.fillMaxWidth()
        ) { Text("Démarrer la navigation") }
    }
}
```

Intégration: dans `CoursesScreen` (carte “Progression de la livraison”), insérer `MapNavigationCard` sous la timeline et appeler `launchTurnByTurn` dans `onStartNavigation`.

## 7) Permissions & Fallback

- Demandez ACCESS_FINE_LOCATION au runtime. Si refusé: afficher la carte sans "my location" et calculer l’itinéraire à partir du dernier point connu (ou directement naviguer vers la destination).
- Si `pickup`/`dropoff` manquent: ouvrir Google Maps vers l’adresse textuelle si disponible.
- Si Directions échoue: proposer quand même le bouton “Démarrer la navigation”.

## 8) Comportement vocal

- La voix est gérée par l’app Google Maps (turn-by-turn). Le coursier peut couper/rétablir le son via l’interface Google Maps (bouton mute). Aucun développement supplémentaire requis dans l’app.

---

Checklist d’intégration rapide côté Android:
- [ ] Ajout des dépendances Maps/Location/Retrofit
- [ ] Clé Android placée dans Manifest et restreinte
- [ ] Directions via `api/directions_proxy.php`
- [ ] `MapNavigationCard` intégré dans la carte Timeline
- [ ] Intent `google.navigation:` branché sur le bouton
- [ ] Gestion permissions localisation et fallback
