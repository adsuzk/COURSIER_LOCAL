# 🤝 Guide de Contribution - Suzosky Client App

## Bienvenue !

Merci de contribuer à l'application cliente Suzosky ! Ce guide vous aidera à maintenir la qualité et la cohérence du code.

---

## 📋 Table des Matières

1. [Code Style](#-code-style)
2. [Architecture](#-architecture)
3. [Composables Guidelines](#-composables-guidelines)
4. [Design System](#-design-system)
5. [Gestion d'État](#-gestion-détat)
6. [Réseau & API](#-réseau--api)
7. [Tests](#-tests)
8. [Git Workflow](#-git-workflow)
9. [Review Process](#-review-process)

---

## 🎨 Code Style

### Kotlin Conventions

Suivez les [conventions Kotlin officielles](https://kotlinlang.org/docs/coding-conventions.html).

```kotlin
// ✅ BON
fun calculatePrice(distance: Double): Int {
    return (800 + distance * 200).toInt()
}

// ❌ MAUVAIS
fun calc_price(d: Double): Int {
    return (800+d*200).toInt()
}
```

### Naming Conventions

| Type | Convention | Exemple |
|------|------------|---------|
| Classes | PascalCase | `HomeScreen`, `OrderViewModel` |
| Fonctions | camelCase | `calculateDistance`, `showMessage` |
| Composables | PascalCase | `ServiceCard`, `ProfileMenuItem` |
| Variables | camelCase | `totalPrice`, `isLoading` |
| Constantes | SCREAMING_SNAKE_CASE | `MAX_DISTANCE`, `API_TIMEOUT` |

### Organisation Imports

```kotlin
// 1. Standard library
import android.os.Bundle
import android.util.Log

// 2. AndroidX
import androidx.compose.runtime.*
import androidx.compose.ui.Modifier

// 3. Third-party
import com.google.android.gms.maps.*

// 4. Project
import com.example.coursiersuzosky.ui.theme.*
import com.example.coursiersuzosky.net.*
```

---

## 🏗️ Architecture

### Structure des Packages

```
com.example.coursiersuzosky/
├── ui/                          # UI Layer
│   ├── screens/                # Écrans complets
│   ├── components/             # Composables réutilisables
│   └── theme/                  # Design system
├── net/                        # Network Layer
│   ├── api/                    # API endpoints
│   ├── models/                 # Data models
│   └── repositories/           # Data repositories (futur)
├── viewmodel/                  # ViewModels (Phase 2)
├── data/                       # Data Layer (Phase 2)
│   ├── local/                  # Room DB
│   └── remote/                 # API
└── utils/                      # Utilitaires
```

### Séparation des Responsabilités

```kotlin
// ✅ BON : Composable simple, état externalisé
@Composable
fun PriceDisplay(price: Int) {
    Text(text = "$price FCFA", style = MaterialTheme.typography.titleLarge)
}

// ❌ MAUVAIS : Logique métier dans le Composable
@Composable
fun PriceDisplay(distance: Double) {
    val price = remember { (800 + distance * 200).toInt() }
    Text(text = "$price FCFA")
}
```

---

## 🧩 Composables Guidelines

### Paramètres Obligatoires vs Optionnels

```kotlin
@Composable
fun ServiceCard(
    // Obligatoires en premier
    icon: String,
    title: String,
    description: String,
    // Optionnels avec valeurs par défaut
    modifier: Modifier = Modifier,
    onClick: (() -> Unit)? = null
) {
    // Implementation
}
```

### Preview Composables

Toujours créer des `@Preview` pour vos composables :

```kotlin
@Preview(showBackground = true)
@Composable
private fun ServiceCardPreview() {
    CoursierSuzoskyTheme {
        ServiceCard(
            icon = "🚛",
            title = "Livraison Express",
            description = "Livraison en 30 minutes"
        )
    }
}
```

### State Hoisting

```kotlin
// ✅ BON : État hissé
@Composable
fun SearchBar(
    query: String,
    onQueryChange: (String) -> Unit
) {
    OutlinedTextField(
        value = query,
        onValueChange = onQueryChange
    )
}

// Usage
@Composable
fun ParentScreen() {
    var query by remember { mutableStateOf("") }
    SearchBar(query = query, onQueryChange = { query = it })
}

// ❌ MAUVAIS : État interne
@Composable
fun SearchBar() {
    var query by remember { mutableStateOf("") }
    OutlinedTextField(value = query, onValueChange = { query = it })
}
```

### Composables Réutilisables

Créer des composables dans `ui/components/` :

```kotlin
// ui/components/SuzoskyButton.kt
@Composable
fun SuzoskyButton(
    text: String,
    onClick: () -> Unit,
    modifier: Modifier = Modifier,
    icon: ImageVector? = null,
    enabled: Boolean = true
) {
    Button(
        onClick = onClick,
        modifier = modifier.height(56.dp),
        enabled = enabled,
        colors = ButtonDefaults.buttonColors(
            containerColor = Gold,
            disabledContainerColor = Gold.copy(alpha = 0.5f)
        ),
        shape = RoundedCornerShape(12.dp)
    ) {
        if (icon != null) {
            Icon(imageVector = icon, contentDescription = null)
            Spacer(Modifier.width(8.dp))
        }
        Text(
            text = text,
            style = MaterialTheme.typography.titleMedium.copy(
                fontWeight = FontWeight.Bold,
                color = Dark
            )
        )
    }
}
```

---

## 🎨 Design System

### Utiliser les Couleurs Suzosky

```kotlin
// ✅ BON : Utiliser les couleurs du thème
Card(
    colors = CardDefaults.cardColors(
        containerColor = SecondaryBlue.copy(alpha = 0.6f)
    )
)

// ❌ MAUVAIS : Couleurs hardcodées
Card(
    colors = CardDefaults.cardColors(
        containerColor = Color(0xFF16213E).copy(alpha = 0.6f)
    )
)
```

### Spacing Cohérent

```kotlin
// ✅ BON : Multiples de 4dp
Column(
    modifier = Modifier.padding(16.dp),
    verticalArrangement = Arrangement.spacedBy(8.dp)
) {
    // Content
}

// ❌ MAUVAIS : Valeurs arbitraires
Column(
    modifier = Modifier.padding(13.dp),
    verticalArrangement = Arrangement.spacedBy(7.dp)
) {
    // Content
}
```

### Typography Material 3

```kotlin
// ✅ BON : Utiliser les styles Material
Text(
    text = "Titre",
    style = MaterialTheme.typography.headlineMedium.copy(
        fontWeight = FontWeight.Bold,
        color = Gold
    )
)

// ❌ MAUVAIS : Styles hardcodés
Text(
    text = "Titre",
    fontSize = 24.sp,
    fontWeight = FontWeight.Bold,
    color = Color(0xFFD4A853)
)
```

---

## 🔄 Gestion d'État

### Remember vs RememberSaveable

```kotlin
// Pour état simple (perdu à la recomposition)
var expanded by remember { mutableStateOf(false) }

// Pour état à préserver (rotation écran, etc.)
var userInput by rememberSaveable { mutableStateOf("") }
```

### LaunchedEffect pour Side Effects

```kotlin
@Composable
fun OrderScreen(orderId: String) {
    var order by remember { mutableStateOf<Order?>(null) }
    
    LaunchedEffect(orderId) {
        // Appel API
        order = ApiClient.getOrder(orderId)
    }
    
    // UI
}
```

### Éviter Recompositions Inutiles

```kotlin
// ✅ BON : Lambda stabilisée
val onClick = remember { { /* action */ } }
Button(onClick = onClick)

// ❌ MAUVAIS : Lambda recréée à chaque recomposition
Button(onClick = { /* action */ })
```

---

## 🌐 Réseau & API

### Structure Appels API

```kotlin
// net/api/OrderService.kt
suspend fun createOrder(request: OrderRequest): Result<OrderResponse> {
    return withContext(Dispatchers.IO) {
        try {
            val response = ApiClient.post("/orders", request)
            if (response.isSuccessful) {
                Result.success(response.body()!!)
            } else {
                Result.failure(ApiException(response.code()))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }
}
```

### Gestion Erreurs

```kotlin
@Composable
fun OrderScreen() {
    var isLoading by remember { mutableStateOf(false) }
    var errorMessage by remember { mutableStateOf<String?>(null) }
    
    fun submitOrder() {
        scope.launch {
            isLoading = true
            errorMessage = null
            
            val result = OrderService.createOrder(orderRequest)
            
            result.fold(
                onSuccess = { response ->
                    showMessage("Commande créée : ${response.id}")
                },
                onFailure = { error ->
                    errorMessage = when (error) {
                        is NetworkException -> "Erreur réseau"
                        is ApiException -> "Erreur serveur : ${error.code}"
                        else -> "Erreur inconnue"
                    }
                }
            )
            
            isLoading = false
        }
    }
    
    // UI avec loading et error states
}
```

### Timeout & Retry

```kotlin
// ApiClient.kt
val client = OkHttpClient.Builder()
    .connectTimeout(30, TimeUnit.SECONDS)
    .readTimeout(30, TimeUnit.SECONDS)
    .writeTimeout(30, TimeUnit.SECONDS)
    .addInterceptor(RetryInterceptor(maxRetries = 3))
    .build()
```

---

## 🧪 Tests

### Tests Unitaires (À implémenter)

```kotlin
// viewmodel/OrderViewModelTest.kt
class OrderViewModelTest {
    @Test
    fun `calculate price returns correct value`() {
        val distance = 10.0 // km
        val expected = 2800 // FCFA
        
        val result = OrderViewModel.calculatePrice(distance)
        
        assertEquals(expected, result)
    }
}
```

### Tests UI Compose (À implémenter)

```kotlin
class HomeScreenTest {
    @get:Rule
    val composeTestRule = createComposeRule()
    
    @Test
    fun heroSection_displays_correct_title() {
        composeTestRule.setContent {
            CoursierSuzoskyTheme {
                HomeScreen(
                    onNavigateToOrder = {},
                    onNavigateToServices = {},
                    showMessage = {}
                )
            }
        }
        
        composeTestRule
            .onNodeWithText("🚴 Coursier N°1 Abidjan")
            .assertIsDisplayed()
    }
}
```

---

## 🔀 Git Workflow

### Branches

- `main` - Production
- `develop` - Développement
- `feature/xxx` - Nouvelles fonctionnalités
- `bugfix/xxx` - Corrections de bugs
- `hotfix/xxx` - Corrections urgentes

### Commit Messages

Format : `type(scope): message`

Types :
- `feat` - Nouvelle fonctionnalité
- `fix` - Correction de bug
- `refactor` - Refactorisation
- `style` - Changements de style/formatage
- `docs` - Documentation
- `test` - Ajout/modification tests
- `chore` - Tâches de maintenance

Exemples :
```
feat(home): add hero section with CTA button
fix(order): correct distance calculation logic
refactor(theme): extract colors to Color.kt
docs(readme): update setup instructions
```

### Pull Requests

Template PR :

```markdown
## Description
Brève description des changements

## Type de changement
- [ ] Nouvelle fonctionnalité
- [ ] Correction de bug
- [ ] Refactorisation
- [ ] Documentation

## Checklist
- [ ] Code suit les conventions du projet
- [ ] Commentaires ajoutés si nécessaire
- [ ] Tests ajoutés/mis à jour
- [ ] Documentation mise à jour
- [ ] Build passe sans warnings

## Screenshots (si UI)
[Ajouter captures d'écran]

## Tests effectués
- [ ] Test sur émulateur
- [ ] Test sur appareil physique
- [ ] Test des cas limites
```

---

## 👀 Review Process

### Checklist Reviewer

- [ ] Code style respecté
- [ ] Architecture cohérente
- [ ] Pas de logique métier dans les Composables
- [ ] État correctement hissé
- [ ] Couleurs du Design System utilisées
- [ ] Pas de hardcoded strings (utiliser strings.xml)
- [ ] Gestion erreurs appropriée
- [ ] Performance acceptable
- [ ] Pas de warning de build
- [ ] Tests (si applicables)

### Critères d'Approbation

1. **Fonctionnel** : La fonctionnalité marche comme attendu
2. **Design** : Respecte la charte Suzosky
3. **Code** : Lisible, maintenable, documenté
4. **Performance** : Pas de lag visible
5. **Tests** : Couverts par tests (Phase 2)

---

## 🚀 Déploiement

### Build Debug

```bash
./gradlew assembleDebug
```

### Build Release

```bash
# 1. Vérifier version dans build.gradle.kts
# 2. Créer tag git
git tag v1.0.0
git push origin v1.0.0

# 3. Build
./gradlew assembleRelease

# 4. Tester l'APK
adb install app/build/outputs/apk/release/app-release.apk
```

### Checklist Release

- [ ] Version incrémentée (versionCode + versionName)
- [ ] CHANGELOG mis à jour
- [ ] Tests réussis
- [ ] Build release sans erreurs
- [ ] APK testé sur plusieurs appareils
- [ ] Tag git créé
- [ ] Documentation à jour

---

## 📚 Ressources

### Documentation Officielle
- [Jetpack Compose](https://developer.android.com/jetpack/compose)
- [Material Design 3](https://m3.material.io/)
- [Kotlin Coroutines](https://kotlinlang.org/docs/coroutines-overview.html)

### Outils Utiles
- [Compose Preview](https://developer.android.com/jetpack/compose/tooling/previews)
- [Layout Inspector](https://developer.android.com/studio/debug/layout-inspector)
- [Network Profiler](https://developer.android.com/studio/profile/network-profiler)

---

## 🤝 Questions ?

- Consulter la documentation : `README_CLIENT_APP.md`
- Voir les exemples dans le code existant
- Ouvrir une issue GitHub
- Contacter l'équipe : dev@suzosky.com

---

**Merci de contribuer à Suzosky ! 🚀**
