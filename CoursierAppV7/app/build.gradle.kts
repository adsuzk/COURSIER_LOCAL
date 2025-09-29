import java.util.Properties

plugins {
    id("com.android.application")
    id("org.jetbrains.kotlin.android")
    id("kotlin-kapt")
    id("dagger.hilt.android.plugin")
    id("org.jetbrains.kotlin.plugin.serialization") version "1.9.23"
    // Firebase Google Services Plugin
    id("com.google.gms.google-services")
}

android {
    namespace = "com.suzosky.coursier"
    compileSdk = 35

    defaultConfig {
        applicationId = "com.suzosky.coursier"
        minSdk = 21
        targetSdk = 35
        versionCode = 1
        versionName = "1.0"

        testInstrumentationRunner = "androidx.test.runner.AndroidJUnitRunner"
        vectorDrawables {
            useSupportLibrary = true
        }
    }

    // Read developer LAN IP from local.properties (debug.localHost)
    val localProps = Properties()
    val lpFile = rootProject.file("local.properties")
    if (lpFile.exists()) {
        lpFile.inputStream().use { localProps.load(it) }
    }
    // Default to common LAN IP if local.properties not set to avoid accidental misconfig on dev machines
    val debugLocalHost: String = (localProps.getProperty("debug.localHost") ?: "").trim().takeIf { it.isNotBlank() } ?: "http://192.168.1.25"
    val debugForceLocalOnly: Boolean = (localProps.getProperty("debug.forceLocalOnly") ?: "false")
        .trim()
        .equals("true", ignoreCase = true)

    buildTypes {
        debug {
            // Evite les conflits de signature si une version release est déjà installée
            applicationIdSuffix = ".debug"
            versionNameSuffix = "-debug"
            // On privilégie le serveur local en debug mais le fallback production reste possible (configurable)
            buildConfigField("boolean", "USE_PROD_SERVER", "false")
            // Optionnel: base de production disponible pour tests ciblés
            buildConfigField("String", "PROD_BASE", "\"https://coursier.conciergerie-privee-suzosky.com/COURSIER_LOCAL\"")
            // Optional: set your PC LAN IP here via local.properties: debug.localHost=192.168.1.100
            buildConfigField("String", "DEBUG_LOCAL_HOST", "\"${debugLocalHost}\"")
            // Permettre d'imposer un fonctionnement 100% local si nécessaire
            buildConfigField("boolean", "FORCE_LOCAL_ONLY", debugForceLocalOnly.toString())
        }
        release {
            isMinifyEnabled = false
            proguardFiles(
                getDefaultProguardFile("proguard-android-optimize.txt"),
                "proguard-rules.pro"
            )
            // En production, utiliser toujours le serveur en ligne (LWS)
            buildConfigField("boolean", "USE_PROD_SERVER", "true")
            buildConfigField("String", "PROD_BASE", "\"https://coursier.conciergerie-privee-suzosky.com/COURSIER_LOCAL\"")
            // Conservé pour d'éventuels diagnostics côté release (non utilisé quand USE_PROD_SERVER=true)
            buildConfigField("String", "DEBUG_LOCAL_HOST", "\"${debugLocalHost}\"")
            buildConfigField("boolean", "FORCE_LOCAL_ONLY", "false")
        }
    }
    compileOptions {
        sourceCompatibility = JavaVersion.VERSION_11
        targetCompatibility = JavaVersion.VERSION_11
    }
    kotlinOptions {
        jvmTarget = "11"
    }
    buildFeatures {
        compose = true
        buildConfig = true
    }
    composeOptions {
    kotlinCompilerExtensionVersion = "1.5.11"
    }
    packaging {
        resources {
            excludes += "/META-INF/{AL2.0,LGPL2.1}"
        }
    }
}

dependencies {
    // AndroidX Core
    // Kotlin reflection (pour la réflexion dans TarificationSuzosky)
    implementation("org.jetbrains.kotlin:kotlin-reflect:1.9.23")
    implementation(libs.androidx.core.ktx)
    implementation(libs.androidx.lifecycle.runtime.ktx)
    implementation(libs.androidx.activity.compose)
    
    // Compose BOM (version gérée automatiquement)
    implementation(platform("androidx.compose:compose-bom:2024.01.00"))
    implementation(libs.androidx.material3)
    implementation(libs.androidx.ui)
    implementation(libs.androidx.ui.graphics)
    implementation(libs.androidx.ui.tooling.preview)
    // Compose Foundation (required for LazyColumn.stickyHeader)
    implementation("androidx.compose.foundation:foundation:1.7.6")
    // Compose Animation (required for AnimatedVisibility, fadeIn)
    implementation("androidx.compose.animation:animation:1.7.6")
    // Compose UI Text (required for KeyboardOptions)
    implementation("androidx.compose.ui:ui-text:1.7.6")
    
    // Navigation Compose
    implementation("androidx.navigation:navigation-compose:2.8.5")
    
    // ViewModel Compose
    implementation("androidx.lifecycle:lifecycle-viewmodel-compose:2.8.7")
    
    // Material Icons Extended
    implementation("androidx.compose.material:material-icons-extended:1.7.6")
    
    // Coil pour le chargement d'images
    implementation("io.coil-kt:coil-compose:2.4.0")
    
    // Google Maps et Places API (versions unifiées)
    implementation("com.google.android.gms:play-services-maps:19.0.0")
    implementation("com.google.android.gms:play-services-location:21.3.0")
    implementation("com.google.maps.android:maps-compose:4.3.3")
    implementation("com.google.android.libraries.places:places:3.5.0")
    
        // Dagger Hilt pour l'injection de dépendances
        implementation("com.google.dagger:hilt-android:2.48")
        implementation("androidx.hilt:hilt-navigation-compose:1.1.0")
        kapt("com.google.dagger:hilt-android-compiler:2.48")
    
    // Coroutines
    implementation("org.jetbrains.kotlinx:kotlinx-coroutines-android:1.8.1")
    
    // JSON handling
        implementation("org.jetbrains.kotlinx:kotlinx-serialization-json:1.6.3")
        implementation("org.jetbrains.kotlinx:kotlinx-serialization-json-jvm:1.6.3")
    
    // HTTP client pour API
    implementation("com.squareup.okhttp3:okhttp:4.12.0")
    implementation("com.squareup.okhttp3:logging-interceptor:4.12.0")
    
    // Retrofit pour les appels API
    // Auto-update system dependencies
    implementation("com.google.code.gson:gson:2.10.1")
    
    // Permissions
    implementation("com.google.accompanist:accompanist-permissions:0.34.0")
    
    // Location Services (déjà déclarés ci-dessus)
    
    // Material Components Android (thèmes, styles)
    implementation("com.google.android.material:material:1.10.0")
    // Google Maps Utility library for polyline decoding
    implementation("com.google.maps.android:android-maps-utils:2.3.0")

    // AndroidX App Startup for Initializer
    implementation("androidx.startup:startup-runtime:1.1.1")

    // Lifecycle Process for ProcessLifecycleOwner
    implementation("androidx.lifecycle:lifecycle-process:2.8.7")

    // Chrome Custom Tabs for native payment flow
    implementation("androidx.browser:browser:1.8.0")
    
    // Firebase Cloud Messaging for push notifications
    implementation(platform("com.google.firebase:firebase-bom:33.5.1"))
    implementation("com.google.firebase:firebase-messaging-ktx")
    // (Optionnel) ExoPlayer si un jour on veut clipper précisément l'audio
    // implementation("com.google.android.exoplayer:exoplayer:2.19.1")
    // Tests
    testImplementation(libs.junit)
    androidTestImplementation(libs.androidx.junit)
    androidTestImplementation(libs.androidx.espresso.core)
    androidTestImplementation(platform(libs.androidx.compose.bom))
    androidTestImplementation(libs.junit4)
    debugImplementation(libs.androidx.ui.tooling)
    debugImplementation(libs.androidx.ui.test.manifest)
}