package com.suzosky.coursier.ui.screens
import androidx.compose.runtime.getValue
import androidx.compose.runtime.setValue
import android.app.Activity
import android.content.Context
import android.net.Uri
import java.io.File
import java.io.FileOutputStream
import java.io.InputStream
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.KeyboardActions
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Lock
import androidx.compose.material.icons.filled.Send
import androidx.compose.material.icons.filled.Visibility
import androidx.compose.material.icons.filled.VisibilityOff
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.DropdownMenu
import androidx.compose.material3.DropdownMenuItem
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.SnackbarHost
import androidx.compose.material3.SnackbarHostState
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.mutableIntStateOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.focus.FocusRequester
import androidx.compose.ui.focus.focusRequester
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.ImeAction
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.ui.text.input.PasswordVisualTransformation
import androidx.compose.ui.text.input.VisualTransformation
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.core.content.FileProvider
import com.suzosky.coursier.network.ApiService
import com.suzosky.coursier.ui.components.GlassContainer
import com.suzosky.coursier.ui.components.GradientButton
import com.suzosky.coursier.ui.theme.*
import com.suzosky.coursier.ui.theme.MontserratFontFamily

// Fonctions utilitaires d'abord

// Convertit un Uri (galerie/appareil photo) en fichier temporaire pour upload
fun uriToFile(context: Context, uri: Uri): File? {
    return try {
        val inputStream: InputStream? = context.contentResolver.openInputStream(uri)
        val file = File.createTempFile("upload_", ".jpg", context.cacheDir)
        val outputStream = FileOutputStream(file)
        inputStream?.copyTo(outputStream)
        inputStream?.close()
        outputStream.close()
        file
    } catch (_: Exception) {
        null
    }
}

@Composable
fun SegmentedTab(selected: Boolean, text: String, onClick: () -> Unit, modifier: Modifier = Modifier) {
    val contentColor = if (selected) PrimaryDark else White80
    Box(
        modifier = modifier
            .height(38.dp)
            .clip(RoundedCornerShape(50))
            .then(
                if (selected) Modifier.background(GradientGoldBrush) 
                else Modifier.background(Color.Transparent)
            )
            .clickable { onClick() }
            .padding(horizontal = 12.dp),
        contentAlignment = Alignment.Center
    ) {
        Text(
            text,
            color = contentColor,
            fontWeight = if (selected) FontWeight.SemiBold else FontWeight.Normal,
            fontSize = 14.sp
        )
    }
}

@Composable
fun FilePickerRow(label: String, file: java.io.File?, onFilePicked: (java.io.File?) -> Unit) {
    val context = LocalContext.current
    val activity = context as? Activity
    val galleryLauncher = rememberLauncherForActivityResult(ActivityResultContracts.GetContent()) { uri: Uri? ->
        if (uri != null && activity != null) {
            val file = uriToFile(context, uri)
            onFilePicked(file)
        }
    }
    var tempPhotoFile by rememberSaveable { mutableStateOf<File?>(null) }
    val cameraLauncher = rememberLauncherForActivityResult(ActivityResultContracts.TakePicture()) { success: Boolean ->
        if (success && tempPhotoFile != null) {
            onFilePicked(tempPhotoFile)
        }
    }
    Column(Modifier.fillMaxWidth().padding(vertical = 6.dp)) {
        Text(label.uppercase(), color = PrimaryGold, fontWeight = FontWeight.Bold, fontFamily = MontserratFontFamily, fontSize = 13.sp, modifier = Modifier.padding(bottom = 6.dp))
        Box(
            modifier = Modifier
                .fillMaxWidth()
                .clip(RoundedCornerShape(14.dp))
                .background(GlassBg)
                .border(2.dp, GlassBorder, RoundedCornerShape(14.dp))
                .clickable { galleryLauncher.launch("image/*") }
                .padding(vertical = 18.dp),
            contentAlignment = Alignment.Center
        ) {
            Column(horizontalAlignment = Alignment.CenterHorizontally) {
                Icon(
                    painter = painterResource(id = com.suzosky.coursier.R.drawable.ic_menu),
                    contentDescription = "Camera",
                    tint = PrimaryGold,
                    modifier = Modifier.size(32.dp)
                )
                Spacer(Modifier.height(6.dp))
                Text(
                    if (file == null) "Cliquez pour choisir ou prendre une photo" else "Fichier sélectionné : ${file.name}",
                    color = White80,
                    fontSize = 13.sp,
                    fontFamily = MontserratFontFamily,
                    fontWeight = FontWeight.Medium
                )
                if (file == null) {
                    Spacer(Modifier.height(4.dp))
                    Row(horizontalArrangement = Arrangement.Center) {
                        GradientButton(text = "Galerie", modifier = Modifier.padding(end = 6.dp).height(38.dp)) {
                            galleryLauncher.launch("image/*")
                        }
                        GradientButton(text = "Photo", modifier = Modifier.height(38.dp)) {
                            val photoFile = File.createTempFile("photo_", ".jpg", context.cacheDir)
                            tempPhotoFile = photoFile
                            val photoUri = FileProvider.getUriForFile(
                                context,
                                context.packageName + ".provider",
                                photoFile
                            )
                            cameraLauncher.launch(photoUri)
                        }
                    }
                }
            }
        }
    }
}

@Composable
fun DropdownMenuBox(selected: String, onTypeChange: (String) -> Unit) {
    var expanded by remember { mutableStateOf(false) }
    Box {
        Button(
            onClick = { expanded = true },
            colors = ButtonDefaults.buttonColors(containerColor = Color(0xFFE0E0E0)),
            contentPadding = PaddingValues(horizontal = 16.dp, vertical = 4.dp)
        ) {
            Text(if (selected == "coursier_moto") "Coursier Moto" else "Coursier Cargo", color = Color(0xFF1A1A1A))
        }
        DropdownMenu(expanded = expanded, onDismissRequest = { expanded = false }) {
            DropdownMenuItem(text = { Text("Coursier Moto") }, onClick = { onTypeChange("coursier_moto"); expanded = false })
            DropdownMenuItem(text = { Text("Coursier Cargo") }, onClick = { onTypeChange("coursier_cargo"); expanded = false })
        }
    }
}

// Fonction principale
@Composable
fun LoginScreen(onLoginSuccess: () -> Unit) {
    val snackbarHostState = remember { SnackbarHostState() }
    var tab by remember { mutableIntStateOf(0) } // 0 = Connexion, 1 = Inscription
    var identifier by remember { mutableStateOf("") }
    var password by remember { mutableStateOf("") }
    var passwordVisible by remember { mutableStateOf(false) }
    var loading by remember { mutableStateOf(false) }
    var error by remember { mutableStateOf<String?>(null) }
    var success by remember { mutableStateOf<String?>(null) }

    // Champs inscription
    var nom by remember { mutableStateOf("") }
    var prenoms by remember { mutableStateOf("") }
    var dateNaissance by remember { mutableStateOf("") }
    var lieuNaissance by remember { mutableStateOf("") }
    var lieuResidence by remember { mutableStateOf("") }
    var telephone by remember { mutableStateOf("") }
    var typePoste by remember { mutableStateOf("coursier_moto") }
    // Fichiers
    var pieceRecto by remember { mutableStateOf<java.io.File?>(null) }
    var pieceVerso by remember { mutableStateOf<java.io.File?>(null) }
    var permisRecto by remember { mutableStateOf<java.io.File?>(null) }
    var permisVerso by remember { mutableStateOf<java.io.File?>(null) }

    // Arrière-plan gradient sombre or brand
    Box(
        modifier = Modifier
            .fillMaxSize()
            .background(
                brush = GradientDarkGoldBrush
            )
            .padding(horizontal = 20.dp, vertical = 32.dp)
    ) {
        // Conteneur central en verre
        GlassContainer(
            modifier = Modifier.align(Alignment.Center).fillMaxWidth().padding(horizontal = 8.dp)
        ) {
            Column(horizontalAlignment = Alignment.CenterHorizontally) {
                // Logo / Titre
                Text(
                    "SUZOSKY",
                    color = PrimaryGold,
                    fontSize = 34.sp,
                    fontWeight = FontWeight.ExtraBold
                )
                Text(
                    "ESPACE COURSIER",
                    color = White80,
                    fontSize = 14.sp,
                    fontWeight = FontWeight.Medium
                )
                Spacer(Modifier.height(20.dp))

                // Onglets
                Row(
                    Modifier
                        .fillMaxWidth()
                        .background(GlassBg, RoundedCornerShape(50))
                        .padding(4.dp),
                    horizontalArrangement = Arrangement.Center
                ) {
                    SegmentedTab(
                        selected = tab == 0,
                        text = "Connexion",
                        onClick = { tab = 0 },
                        modifier = Modifier.weight(1f)
                    )
                    Spacer(Modifier.width(8.dp))
                    SegmentedTab(
                        selected = tab == 1,
                        text = "Inscription",
                        onClick = { tab = 1 },
                        modifier = Modifier.weight(1f)
                    )
                }
                Spacer(Modifier.height(20.dp))

                if (tab == 0) {
                    // Formulaire de connexion
                    Column {
                        val passwordFocusRequester = remember { FocusRequester() }
                        val ctx = LocalContext.current
                        // Persist / restore last used identifier type & value
                        var identifierType by remember { mutableStateOf("matricule") } // matricule | telephone | email
                        LaunchedEffect(Unit) {
                            try {
                                val prefs = ctx.getSharedPreferences("suzosky_prefs", Context.MODE_PRIVATE)
                                identifierType = prefs.getString("last_login_type", "matricule") ?: "matricule"
                                identifier = prefs.getString("last_login_value", "") ?: ""
                            } catch (_: Exception) {
                            }
                        }

                        fun saveLastLoginPref() {
                            try {
                                val prefs = ctx.getSharedPreferences("suzosky_prefs", Context.MODE_PRIVATE)
                                prefs.edit().putString("last_login_type", identifierType).putString("last_login_value", identifier).apply()
                            } catch (_: Exception) { }
                        }

                        // Row: small dropdown selector for type + identifier field
                        Row(modifier = Modifier.fillMaxWidth(), verticalAlignment = Alignment.CenterVertically) {
                            var expandedIdType by remember { mutableStateOf(false) }
                            Button(
                                onClick = { expandedIdType = true },
                                colors = ButtonDefaults.buttonColors(containerColor = Color(0xFFE0E0E0)),
                                contentPadding = androidx.compose.ui.unit.PaddingValues(horizontal = 12.dp, vertical = 8.dp),
                                modifier = Modifier.padding(end = 8.dp)
                            ) {
                                Text(
                                    when (identifierType) {
                                        "telephone" -> "Téléphone"
                                        "email" -> "Email"
                                        else -> "Matricule"
                                    },
                                    color = Color(0xFF1A1A1A)
                                )
                            }
                            DropdownMenu(expanded = expandedIdType, onDismissRequest = { expandedIdType = false }) {
                                DropdownMenuItem(text = { Text("Matricule") }, onClick = { identifierType = "matricule"; expandedIdType = false; saveLastLoginPref() })
                                DropdownMenuItem(text = { Text("Téléphone") }, onClick = { identifierType = "telephone"; expandedIdType = false; saveLastLoginPref() })
                                DropdownMenuItem(text = { Text("Email") }, onClick = { identifierType = "email"; expandedIdType = false; saveLastLoginPref() })
                            }

                            OutlinedTextField(
                                value = identifier,
                                onValueChange = { identifier = it; saveLastLoginPref() },
                                label = { Text("Matricule, Téléphone ou Email") },
                                placeholder = { Text("Votre matricule ou numéro") },
                                modifier = Modifier
                                    .weight(1f),
                                singleLine = true,
                                keyboardOptions = KeyboardOptions(imeAction = ImeAction.Next),
                                keyboardActions = KeyboardActions(
                                    onNext = {
                                        passwordFocusRequester.requestFocus()
                                    }
                                )
                            )
                        }
                        Spacer(Modifier.height(12.dp))
                        OutlinedTextField(
                            value = password,
                            onValueChange = { password = it },
                            label = { Text("Mot de passe") },
                            placeholder = { Text("Votre mot de passe") },
                            visualTransformation = if (passwordVisible) VisualTransformation.None else PasswordVisualTransformation(),
                            trailingIcon = {
                                val image = if (passwordVisible) Icons.Filled.Visibility else Icons.Filled.VisibilityOff
                                IconButton(onClick = { passwordVisible = !passwordVisible }) {
                                    Icon(imageVector = image, contentDescription = if (passwordVisible) "Cacher" else "Afficher")
                                }
                            },
                            modifier = Modifier
                                .fillMaxWidth()
                                .focusRequester(passwordFocusRequester),
                            singleLine = true,
                            keyboardOptions = KeyboardOptions(imeAction = ImeAction.Done),
                            keyboardActions = KeyboardActions(
                                onDone = {
                                    if (identifier.isBlank() || password.isBlank()) {
                                        error = "Veuillez remplir tous les champs"
                                    } else if (!loading) {
                                        loading = true
                                        error = null
                                        ApiService.login(identifier, password) { successLogin, errMsg ->
                                            loading = false
                                            if (successLogin) {
                                                onLoginSuccess()
                                            } else {
                                                error = errMsg ?: "Erreur de connexion"
                                            }
                                        }
                                    }
                                }
                            )
                        )
                        Spacer(Modifier.height(8.dp))
                        // Ligne d'options allégée (suppression test serveur)
                        Row(
                            Modifier.fillMaxWidth(),
                            horizontalArrangement = Arrangement.Center,
                            verticalAlignment = Alignment.CenterVertically
                        ) {
                            Text(
                                "Mot de passe oublié ?",
                                color = Color(0xFFC9A13B),
                                modifier = Modifier
                                    .padding(top = 2.dp)
                                    .clickable { error = "Veuillez contacter l'administration pour réinitialiser votre mot de passe." }
                            )
                        }
                        Spacer(Modifier.height(16.dp))
                        GradientButton(
                            text = if (loading) "Connexion..." else "Se connecter",
                            modifier = Modifier.fillMaxWidth(),
                            enabled = !loading
                        ) {
                            if (identifier.isBlank() || password.isBlank()) {
                                error = "Veuillez remplir tous les champs"
                                return@GradientButton
                            }
                            
                            loading = true
                            error = null
                            
                            // Log des tentatives de connexion
                            println("🔐 Tentative de connexion avec: $identifier")
                            
                            ApiService.login(identifier, password) { successLogin, errMsg ->
                                loading = false
                                if (successLogin) {
                                    println("✅ Connexion réussie pour: $identifier")
                                    onLoginSuccess()
                                } else {
                                    println("❌ Échec de connexion: $errMsg")
                                    error = errMsg ?: "Erreur de connexion"
                                }
                            }
                        }
                        
                        // (Bouton test serveur supprimé)
                    }
                } else {
                    // Formulaire d'inscription refait façon web
                    val scrollState = rememberScrollState()
                    Column(
                        modifier = Modifier.verticalScroll(scrollState)
                    ) {
                        // Ligne 1 : Nom, Prénoms
                        Row(Modifier.fillMaxWidth()) {
                            GlassContainer(modifier = Modifier.weight(1f).padding(end = 8.dp)) {
                                Text("Nom *", color = PrimaryGold, fontWeight = FontWeight.Bold, fontFamily = MontserratFontFamily, fontSize = 14.sp, modifier = Modifier.padding(bottom = 4.dp))
                                OutlinedTextField(
                                    value = nom,
                                    onValueChange = { nom = it },
                                    placeholder = { Text("") },
                                    modifier = Modifier.fillMaxWidth(),
                                    singleLine = true
                                )
                            }
                            GlassContainer(modifier = Modifier.weight(1f).padding(start = 8.dp)) {
                                Text("Prénoms *", color = PrimaryGold, fontWeight = FontWeight.Bold, fontFamily = MontserratFontFamily, fontSize = 14.sp, modifier = Modifier.padding(bottom = 4.dp))
                                OutlinedTextField(
                                    value = prenoms,
                                    onValueChange = { prenoms = it },
                                    placeholder = { Text("") },
                                    modifier = Modifier.fillMaxWidth(),
                                    singleLine = true
                                )
                            }
                        }
                        Spacer(Modifier.height(12.dp))
                        // Ligne 2 : Date de naissance, Lieu de naissance
                        Row(Modifier.fillMaxWidth()) {
                            GlassContainer(modifier = Modifier.weight(1f).padding(end = 8.dp)) {
                                Text("Date de naissance", color = PrimaryGold, fontWeight = FontWeight.Bold, fontFamily = MontserratFontFamily, fontSize = 14.sp, modifier = Modifier.padding(bottom = 4.dp))
                                OutlinedTextField(
                                    value = dateNaissance,
                                    onValueChange = { dateNaissance = it },
                                    placeholder = { Text("") },
                                    modifier = Modifier.fillMaxWidth(),
                                    singleLine = true
                                )
                            }
                            GlassContainer(modifier = Modifier.weight(1f).padding(start = 8.dp)) {
                                Text("Lieu de naissance", color = PrimaryGold, fontWeight = FontWeight.Bold, fontFamily = MontserratFontFamily, fontSize = 14.sp, modifier = Modifier.padding(bottom = 4.dp))
                                OutlinedTextField(
                                    value = lieuNaissance,
                                    onValueChange = { lieuNaissance = it },
                                    placeholder = { Text("") },
                                    modifier = Modifier.fillMaxWidth(),
                                    singleLine = true
                                )
                            }
                        }
                        Spacer(Modifier.height(12.dp))
                        // Lieu de résidence (full width)
                        GlassContainer(modifier = Modifier.fillMaxWidth()) {
                            Text("Lieu de résidence", color = PrimaryGold, fontWeight = FontWeight.Bold, fontFamily = MontserratFontFamily, fontSize = 14.sp, modifier = Modifier.padding(bottom = 4.dp))
                            OutlinedTextField(
                                value = lieuResidence,
                                onValueChange = { lieuResidence = it },
                                placeholder = { Text("Commune, quartier") },
                                modifier = Modifier.fillMaxWidth(),
                                singleLine = true
                            )
                        }
                        Spacer(Modifier.height(12.dp))
                        // Ligne 3 : Téléphone, Type de coursier
                        Row(Modifier.fillMaxWidth()) {
                            GlassContainer(modifier = Modifier.weight(1f).padding(end = 8.dp)) {
                                Text("Téléphone *", color = PrimaryGold, fontWeight = FontWeight.Bold, fontFamily = MontserratFontFamily, fontSize = 14.sp, modifier = Modifier.padding(bottom = 4.dp))
                                OutlinedTextField(
                                    value = telephone,
                                    onValueChange = { telephone = it },
                                    placeholder = { Text("07 XX XX XX XX") },
                                    modifier = Modifier.fillMaxWidth(),
                                    singleLine = true
                                )
                            }
                            GlassContainer(modifier = Modifier.weight(1f).padding(start = 8.dp)) {
                                Text("Type de coursier", color = PrimaryGold, fontWeight = FontWeight.Bold, fontFamily = MontserratFontFamily, fontSize = 14.sp, modifier = Modifier.padding(bottom = 4.dp))
                                DropdownMenuBox(typePoste, onTypeChange = { typePoste = it })
                            }
                        }
                        Spacer(Modifier.height(16.dp))
                        // Uploads (full width, effet visuel)
                        FilePickerRow("Pièce d'identité (Recto)", pieceRecto, onFilePicked = { pieceRecto = it })
                        FilePickerRow("Pièce d'identité (Verso)", pieceVerso, onFilePicked = { pieceVerso = it })
                        FilePickerRow("Permis de conduire (Recto)", permisRecto, onFilePicked = { permisRecto = it })
                        FilePickerRow("Permis de conduire (Verso)", permisVerso, onFilePicked = { permisVerso = it })
                        Spacer(Modifier.height(20.dp))
                        GradientButton(
                            text = if (loading) "Envoi..." else "Envoyer candidature",
                            modifier = Modifier.fillMaxWidth(),
                            enabled = !loading
                        ) {
                            loading = true
                            error = null
                            success = null
                            ApiService.registerCoursier(
                                nom, prenoms, dateNaissance, lieuNaissance, lieuResidence, telephone, typePoste,
                                pieceRecto, pieceVerso, permisRecto, permisVerso
                            ) { ok, msg ->
                                loading = false
                                if (ok) success = "Votre candidature a bien été envoyée ! L'administration a reçu votre dossier. Vous recevrez une confirmation par SMS après validation." else error = msg ?: "Erreur lors de l'inscription"
                            }
                        }
                    }
                }
            }
        }
        
        // Messages et Snackbar
        if (error != null) {
            LaunchedEffect(error) {
                snackbarHostState.showSnackbar(error ?: "Erreur inconnue")
            }
        }
        if (success != null) {
            LaunchedEffect(success) {
                snackbarHostState.showSnackbar(success ?: "Succès")
            }
        }
        SnackbarHost(hostState = snackbarHostState)
    }
}