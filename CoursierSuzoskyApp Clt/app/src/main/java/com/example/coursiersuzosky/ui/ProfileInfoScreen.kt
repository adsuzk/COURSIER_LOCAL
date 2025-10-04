package com.suzosky.coursierclient.ui

import androidx.compose.animation.*
import androidx.compose.foundation.*
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.compose.ui.platform.LocalContext
import com.suzosky.coursierclient.net.ClientStore
import com.suzosky.coursierclient.net.ApiService
import com.suzosky.coursierclient.net.ClientInfo
import com.suzosky.coursierclient.ui.theme.*
import kotlinx.coroutines.launch

@Composable
fun ProfileInfoScreen() {
    val context = LocalContext.current
    val scope = rememberCoroutineScope()
    var clientPhone by remember { mutableStateOf(ClientStore.getClientPhone(context) ?: "") }
    var info by remember { mutableStateOf<ClientInfo?>(null) }
    var loading by remember { mutableStateOf(false) }
    var error by remember { mutableStateOf<String?>(null) }

    LaunchedEffect(clientPhone) {
        if (clientPhone.isNotBlank()) {
            loading = true
            error = null
            try {
                val resp = ApiService.getClientInfo(clientPhone)
                if (resp.success) info = resp.data else error = resp.message ?: "Profil introuvable"
            } catch (e: Exception) {
                error = ApiService.friendlyError(e)
            } finally { loading = false }
        }
    }

    Box(
        modifier = Modifier
            .fillMaxSize()
            .background(
                Brush.verticalGradient(
                    colors = listOf(Dark, SecondaryBlue, Dark)
                )
            )
    ) {
        Column(
            modifier = Modifier
                .fillMaxSize()
                .verticalScroll(rememberScrollState())
                .padding(24.dp)
        ) {
            // Header
            Row(
                modifier = Modifier.fillMaxWidth(),
                verticalAlignment = Alignment.CenterVertically
            ) {
                Box(
                    modifier = Modifier
                        .size(80.dp)
                        .clip(CircleShape)
                        .background(
                            Brush.linearGradient(
                                colors = listOf(Gold, GoldLight)
                            )
                        )
                        .border(3.dp, Gold.copy(alpha = 0.3f), CircleShape),
                    contentAlignment = Alignment.Center
                ) {
                    Icon(
                        imageVector = Icons.Filled.Person,
                        contentDescription = null,
                        modifier = Modifier.size(40.dp),
                        tint = Dark
                    )
                }
                Spacer(Modifier.width(16.dp))
                Column {
                    Text(
                        text = "Mon Profil",
                        fontSize = 24.sp,
                        fontWeight = FontWeight.Bold,
                        color = Gold
                    )
                    Text(
                        text = "Informations personnelles",
                        fontSize = 14.sp,
                        color = Color.White.copy(alpha = 0.6f)
                    )
                }
            }

            Spacer(Modifier.height(32.dp))

            if (loading) {
                Box(
                    modifier = Modifier.fillMaxWidth(),
                    contentAlignment = Alignment.Center
                ) {
                    CircularProgressIndicator(color = Gold)
                }
            } else if (error != null) {
                Card(
                    modifier = Modifier.fillMaxWidth(),
                    colors = CardDefaults.cardColors(
                        containerColor = AccentRed.copy(alpha = 0.2f)
                    ),
                    shape = RoundedCornerShape(16.dp)
                ) {
                    Row(
                        modifier = Modifier.padding(16.dp),
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        Icon(
                            imageVector = Icons.Filled.Error,
                            contentDescription = null,
                            tint = AccentRed,
                            modifier = Modifier.size(24.dp)
                        )
                        Spacer(Modifier.width(12.dp))
                        Text(
                            text = error!!,
                            color = Color.White,
                            fontSize = 14.sp
                        )
                    }
                }
            } else {
                info?.let { c ->
                    // Nom complet
                    InfoCard(
                        icon = Icons.Filled.Badge,
                        label = "Nom complet",
                        value = "${c.prenoms} ${c.nom}"
                    )
                    Spacer(Modifier.height(12.dp))

                    // Email
                    InfoCard(
                        icon = Icons.Filled.Email,
                        label = "Email",
                        value = c.email ?: "Non renseigné"
                    )
                    Spacer(Modifier.height(12.dp))

                    // Téléphone
                    InfoCard(
                        icon = Icons.Filled.Phone,
                        label = "Téléphone",
                        value = c.telephone ?: "Non renseigné"
                    )
                    Spacer(Modifier.height(12.dp))

                    // Statut
                    InfoCard(
                        icon = Icons.Filled.Star,
                        label = "Statut",
                        value = "Client Premium",
                        valueColor = Gold
                    )
                }
            }

            Spacer(Modifier.height(24.dp))

            // Actions
            Button(
                onClick = { /* TODO: Éditer profil */ },
                modifier = Modifier
                    .fillMaxWidth()
                    .height(56.dp),
                shape = RoundedCornerShape(16.dp),
                colors = ButtonDefaults.buttonColors(
                    containerColor = Gold.copy(alpha = 0.2f),
                    contentColor = Gold
                ),
                border = BorderStroke(1.dp, Gold.copy(alpha = 0.5f))
            ) {
                Icon(
                    imageVector = Icons.Filled.Edit,
                    contentDescription = null,
                    modifier = Modifier.size(20.dp)
                )
                Spacer(Modifier.width(12.dp))
                Text(
                    text = "Modifier mes informations",
                    fontSize = 16.sp,
                    fontWeight = FontWeight.SemiBold
                )
            }
        }
    }
}

@Composable
private fun InfoCard(
    icon: androidx.compose.ui.graphics.vector.ImageVector,
    label: String,
    value: String,
    valueColor: Color = Color.White
) {
    Card(
        modifier = Modifier.fillMaxWidth(),
        shape = RoundedCornerShape(16.dp),
        colors = CardDefaults.cardColors(
            containerColor = Color.White.copy(alpha = 0.05f)
        ),
        border = BorderStroke(1.dp, Gold.copy(alpha = 0.1f))
    ) {
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .padding(20.dp),
            verticalAlignment = Alignment.CenterVertically
        ) {
            Box(
                modifier = Modifier
                    .size(48.dp)
                    .clip(CircleShape)
                    .background(Gold.copy(alpha = 0.15f)),
                contentAlignment = Alignment.Center
            ) {
                Icon(
                    imageVector = icon,
                    contentDescription = null,
                    tint = Gold,
                    modifier = Modifier.size(24.dp)
                )
            }
            Spacer(Modifier.width(16.dp))
            Column(modifier = Modifier.weight(1f)) {
                Text(
                    text = label,
                    fontSize = 13.sp,
                    color = Color.White.copy(alpha = 0.6f),
                    fontWeight = FontWeight.Medium
                )
                Spacer(Modifier.height(4.dp))
                Text(
                    text = value,
                    fontSize = 16.sp,
                    color = valueColor,
                    fontWeight = FontWeight.SemiBold
                )
            }
        }
    }
}
