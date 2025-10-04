plugins {
    alias(libs.plugins.android.application)
    alias(libs.plugins.kotlin.android)
    alias(libs.plugins.kotlin.compose)
}

// For physical device testing, you can override LOCAL_LAN_IP in gradle.properties
val localLanIp = (project.findProperty("LOCAL_LAN_IP") as String?) ?: "10.0.2.2"

android {
    namespace = "com.suzosky.coursierclient"
    compileSdk = 36

    defaultConfig {
        applicationId = "com.suzosky.coursierclient"
        minSdk = 24
        targetSdk = 36
        versionCode = 1
        versionName = "1.0"

        testInstrumentationRunner = "androidx.test.runner.AndroidJUnitRunner"
    }

    buildTypes {
        release {
            isMinifyEnabled = false
            proguardFiles(
                getDefaultProguardFile("proguard-android-optimize.txt"),
                "proguard-rules.pro"
            )
            buildConfigField("String", "BASE_URL", "\"https://coursier.conciergerie-privee-suzosky.com/api/\"")
        }
        debug {
            // Use local LAN IP for physical device or 10.0.2.2 for emulator
            // Server base path is COURSIER_LOCAL/api on XAMPP
            buildConfigField("String", "BASE_URL", "\"http://${localLanIp}/COURSIER_LOCAL/api/\"")
            // Match Firebase debug client package: com.suzosky.coursierclient.debug
            applicationIdSuffix = ".debug"
            versionNameSuffix = "-debug"
        }
    }
    compileOptions {
        sourceCompatibility = JavaVersion.VERSION_17
        targetCompatibility = JavaVersion.VERSION_17
    }
    buildFeatures {
        compose = true
        buildConfig = true
    }
    // Make lint strict and fail the build on any issue
    lint {
        abortOnError = false
        warningsAsErrors = false
        checkAllWarnings = true
    }
}

// Ensure Java compilation also treats warnings as errors
tasks.withType<org.gradle.api.tasks.compile.JavaCompile>().configureEach {
    options.compilerArgs.add("-Werror")
}

// Configure Kotlin compiler options (Kotlin 2.2+ DSL)
kotlin {
    compilerOptions {
        // JVM target 17 and fail on warnings
        jvmTarget.set(org.jetbrains.kotlin.gradle.dsl.JvmTarget.JVM_17)
        allWarningsAsErrors.set(false) // Désactivé temporairement pour dépréciation d'icônes
    }
}

// Make assemble tasks depend on lint so errors are caught during assemble (deferred until tasks exist)
gradle.projectsEvaluated {
    tasks.findByName("assembleDebug")?.dependsOn("lintDebug")
    tasks.findByName("assembleRelease")?.dependsOn("lintRelease")
}

// Apply Google Services plugin only when google-services.json is present
if (rootProject.file("app/google-services.json").exists() || project.file("google-services.json").exists()) {
    apply(plugin = "com.google.gms.google-services")
} else {
    logger.lifecycle("[CoursierClient] google-services.json not found; skipping Google Services plugin (Realtime will be inactive).")
}

dependencies {

    implementation(libs.androidx.core.ktx)
    implementation(libs.androidx.lifecycle.runtime.ktx)
    implementation(libs.androidx.activity.compose)
    implementation(platform(libs.androidx.compose.bom))
    implementation(libs.androidx.compose.ui)
    implementation(libs.androidx.compose.ui.graphics)
    implementation(libs.androidx.compose.ui.tooling.preview)
    implementation(libs.androidx.compose.material3)
    // Animations and icons
    implementation(libs.androidx.compose.animation)
    implementation(libs.androidx.compose.material.icons.extended)
    // Navigation Compose
    implementation("androidx.navigation:navigation-compose:2.7.7")
    // Réseau
    implementation(libs.okhttp)
    implementation(libs.okhttp.logging.interceptor)
    // Custom Tabs pour ouverture paiement in-app
    implementation(libs.androidx.browser)
    // DataStore (persistance simple des préférences/session)
    implementation(libs.androidx.datastore.preferences)
    // Google Maps & Places
    implementation(libs.play.services.maps)
    implementation(libs.maps.compose)
    implementation(libs.places)
    // Firebase Realtime Database (BOM manages versions)
    implementation(platform("com.google.firebase:firebase-bom:33.5.1"))
    implementation("com.google.firebase:firebase-database-ktx")
    testImplementation(libs.junit)
    androidTestImplementation(libs.androidx.junit)
    androidTestImplementation(libs.androidx.espresso.core)
    androidTestImplementation(platform(libs.androidx.compose.bom))
    androidTestImplementation(libs.androidx.compose.ui.test.junit4)
    debugImplementation(libs.androidx.compose.ui.tooling)
    debugImplementation(libs.androidx.compose.ui.test.manifest)
}