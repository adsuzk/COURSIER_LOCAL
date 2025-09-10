
package com.suzosky.coursier.ui.screens

import android.Manifest
import android.os.Build
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.core.content.FileProvider
import android.app.Activity
import android.content.Context
import android.content.Intent
import android.net.Uri
import android.os.Environment
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.ui.platform.LocalContext
import java.io.File
import java.io.FileOutputStream
import java.io.InputStream

import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.material3.Text
import androidx.compose.material3.SnackbarHost
import androidx.compose.material3.SnackbarHostState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Visibility
import androidx.compose.material.icons.filled.VisibilityOff
import androidx.compose.material.icons.filled.Lock
import androidx.compose.material.icons.filled.Send
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.DropdownMenu
import androidx.compose.material3.DropdownMenuItem
import androidx.compose.material3.Button
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.OutlinedTextField

import androidx.compose.material3.SnackbarHost
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.res.painterResource
import androidx.compose.material.icons.filled.Send
import androidx.compose.ui.text.input.PasswordVisualTransformation
import androidx.compose.ui.text.input.VisualTransformation
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.suzosky.coursier.network.ApiService
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.verticalScroll

@Composable
fun LoginScreen(onLoginSuccess: () -> Unit) {
    val snackbarHostState = remember { SnackbarHostState() }
    var tab by remember { mutableStateOf(0) } // 0 = Connexion, 1 = Inscription
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



    Box(
        modifier = Modifier
            .fillMaxSize()
            .background(Color(0xFF1A1A1A))
    ) {
        Column(
            modifier = Modifier.align(Alignment.Center).padding(24.dp).background(Color.White, RoundedCornerShape(16.dp)).padding(24.dp),
            horizontalAlignment = Alignment.CenterHorizontally
        ) {
            Text("SUZOSKY", color = Color(0xFFC9A13B), fontSize = 32.sp)
            Text("ESPACE COURSIER", color = Color(0xFF1A1A1A), fontSize = 16.sp)
            Spacer(Modifier.height(16.dp))

            // Onglets
            Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.Center) {
                TabButton(selected = tab == 0, text = "Connexion", onClick = { tab = 0 })
                Spacer(Modifier.width(8.dp))
                TabButton(selected = tab == 1, text = "Inscription", onClick = { tab = 1 })
            }
            Spacer(Modifier.height(16.dp))

            // Messages
            if (error != null) {
                LaunchedEffect(error) {
                    snackbarHostState.showSnackbar(error!!)
                }
            }
            if (success != null) {
                LaunchedEffect(success) {
                    snackbarHostState.showSnackbar(success!!)
                }
            }
            SnackbarHost(hostState = snackbarHostState)

            if (tab == 0) {
                // Formulaire de connexion
                Column {
                    OutlinedTextField(
                        value = identifier,
                        onValueChange = { identifier = it },
                        label = { Text("Matricule, Téléphone ou Email") },
                        placeholder = { Text("Votre matricule ou numéro") },
                        modifier = Modifier.fillMaxWidth()
                    )
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
                        modifier = Modifier.fillMaxWidth()
                    )
                    Spacer(Modifier.height(8.dp))
                    Text(
                        "Mot de passe oublié ?",
                        color = Color(0xFFC9A13B),
                        modifier = Modifier.align(Alignment.End).clickable { error = "Veuillez contacter l'administration pour réinitialiser votre mot de passe." }
                    )
                    Spacer(Modifier.height(16.dp))
                    Button(
                        onClick = {
                            loading = true
                            error = null
                            ApiService.login(identifier, password) { successLogin, errMsg ->
                                loading = false
                                if (successLogin) onLoginSuccess() else error = errMsg ?: "Erreur de connexion"
                            }
                        },
                        enabled = !loading,
                        modifier = Modifier.fillMaxWidth(),
                        colors = ButtonDefaults.buttonColors(containerColor = Color(0xFFC9A13B), contentColor = Color.White)
                    ) {
                        Icon(Icons.Filled.Lock, contentDescription = null)
                        Spacer(Modifier.width(8.dp))
                        Text(if (loading) "Connexion..." else "Se connecter")
                    }
                }
            } else {
                // Formulaire d'inscription complet avec upload
                val scrollState = rememberScrollState()
                Column(
                    modifier = Modifier.verticalScroll(scrollState)
                ) {
                    // Ligne 1 : Nom, Prénoms
                    Row(Modifier.fillMaxWidth()) {
                        OutlinedTextField(
                            value = nom,
                            onValueChange = { nom = it },
                            label = { Text("Nom *") },
                            modifier = Modifier.weight(1f).padding(end = 4.dp),
                            singleLine = true,
                            placeholder = { Text("") }
                        )
                        OutlinedTextField(
                            value = prenoms,
                            onValueChange = { prenoms = it },
                            label = { Text("Prénoms *") },
                            modifier = Modifier.weight(1f).padding(start = 4.dp),
                            singleLine = true,
                            placeholder = { Text("") }
                        )
                    }
                    Spacer(Modifier.height(8.dp))
                    // Ligne 2 : Date de naissance, Lieu de naissance
                    Row(Modifier.fillMaxWidth()) {
                        OutlinedTextField(
                            value = dateNaissance,
                            onValueChange = { dateNaissance = it },
                            label = { Text("Date de naissance") },
                            modifier = Modifier.weight(1f).padding(end = 4.dp),
                            singleLine = true,
                            placeholder = { Text("") }
                        )
                        OutlinedTextField(
                            value = lieuNaissance,
                            onValueChange = { lieuNaissance = it },
                            label = { Text("Lieu de naissance") },
                            modifier = Modifier.weight(1f).padding(start = 4.dp),
                            singleLine = true,
                            placeholder = { Text("") }
                        )
                    }
                    Spacer(Modifier.height(8.dp))
                    // Lieu de résidence (full width)
                    OutlinedTextField(
                        value = lieuResidence,
                        onValueChange = { lieuResidence = it },
                        label = { Text("Lieu de résidence") },
                        modifier = Modifier.fillMaxWidth(),
                        singleLine = true,
                        placeholder = { Text("Commune, quartier") }
                    )
                    Spacer(Modifier.height(8.dp))
                    // Ligne 3 : Téléphone, Type de coursier
                    Row(Modifier.fillMaxWidth()) {
                        OutlinedTextField(
                            value = telephone,
                            onValueChange = { telephone = it },
                            label = { Text("Téléphone *") },
                            modifier = Modifier.weight(1f).padding(end = 4.dp),
                            singleLine = true,
                            placeholder = { Text("07 XX XX XX XX") }
                        )
                        // Type de coursier (select)
                        Column(Modifier.weight(1f).padding(start = 4.dp)) {
                            Text("Type de coursier", modifier = Modifier.padding(bottom = 4.dp))
                            DropdownMenuBox(typePoste, onTypeChange = { typePoste = it })
                        }
                    }
                    Spacer(Modifier.height(12.dp))
                    // Uploads (full width)
                    FilePickerRow("Pièce d'identité (Recto)", pieceRecto, onFilePicked = { pieceRecto = it })
                    FilePickerRow("Pièce d'identité (Verso)", pieceVerso, onFilePicked = { pieceVerso = it })
                    FilePickerRow("Permis de conduire (Recto)", permisRecto, onFilePicked = { permisRecto = it })
                    FilePickerRow("Permis de conduire (Verso)", permisVerso, onFilePicked = { permisVerso = it })
                    Spacer(Modifier.height(16.dp))
                    Button(
                        onClick = {
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
                        },
                        modifier = Modifier
                            .fillMaxWidth()
                            .height(56.dp),
                        colors = ButtonDefaults.buttonColors(containerColor = Color(0xFF388E3C), contentColor = Color.White),
                        shape = RoundedCornerShape(12.dp)
                    ) {
                        Icon(Icons.Filled.Send, contentDescription = null)
                        Spacer(Modifier.width(8.dp))
                        Text(if (loading) "Envoi..." else "Envoyer candidature", fontSize = 18.sp)
                    }
                }
            }
        }
    }
}

// Sélecteur de fichier (image/photo)

@Composable
fun FilePickerRow(label: String, file: java.io.File?, onFilePicked: (java.io.File?) -> Unit) {
    val context = LocalContext.current
    val activity = context as? Activity
    // Pour la galerie
    val galleryLauncher = rememberLauncherForActivityResult(ActivityResultContracts.GetContent()) { uri: Uri? ->
        if (uri != null && activity != null) {
            val file = uriToFile(context, uri)
            onFilePicked(file)
        }
    }
    // Pour l'appareil photo
    var tempPhotoFile by rememberSaveable { mutableStateOf<File?>(null) }
    val cameraLauncher = rememberLauncherForActivityResult(ActivityResultContracts.TakePicture()) { success: Boolean ->
        if (success && tempPhotoFile != null) {
            onFilePicked(tempPhotoFile)
        }
    }
    Row(verticalAlignment = Alignment.CenterVertically, modifier = Modifier.fillMaxWidth().padding(vertical = 4.dp)) {
        Text(label, modifier = Modifier.weight(1f))
        if (file != null) {
            Text(file.name, color = Color(0xFF388E3C), modifier = Modifier.padding(horizontal = 8.dp))
        }
        Button(onClick = { galleryLauncher.launch("image/*") }, colors = ButtonDefaults.buttonColors(containerColor = Color(0xFFE0E0E0))) {
            Text(if (file == null) "Choisir" else "Changer")
        }
        Spacer(Modifier.width(4.dp))
        Button(onClick = {
            // Créer un fichier temporaire pour la photo
            val photoFile = File.createTempFile("photo_", ".jpg", context.cacheDir)
            tempPhotoFile = photoFile
            val photoUri = FileProvider.getUriForFile(
                context,
                context.packageName + ".provider",
                photoFile
            )
            cameraLauncher.launch(photoUri)
        }, colors = ButtonDefaults.buttonColors(containerColor = Color(0xFFE0E0E0))) {
            Text("Prendre une photo")
        }
    }
}

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
    } catch (e: Exception) {
        null
    }
}

@Composable
fun TabButton(selected: Boolean, text: String, onClick: () -> Unit) {
    Button(
        onClick = onClick,
        colors = ButtonDefaults.buttonColors(
            containerColor = if (selected) Color(0xFFC9A13B) else Color(0xFFE0E0E0),
            contentColor = if (selected) Color.White else Color(0xFF1A1A1A)
        ),
        shape = RoundedCornerShape(50),
        modifier = Modifier.height(36.dp)
    ) {
        Text(text)
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
