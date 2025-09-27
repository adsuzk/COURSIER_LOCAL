package com.suzosky.coursier.ui.screens

import androidx.compose.foundation.background
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.foundation.rememberScrollState
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.suzosky.coursier.ui.theme.*

data class SupportItem(
    val icon: ImageVector,
    val title: String,
    val subtitle: String,
    val action: String,
    val color: Color
)

data class FAQItem(
    val question: String,
    val answer: String
)

@Composable
fun SupportScreen() {
    val supportItems = remember {
        listOf(
            SupportItem(
                icon = Icons.Default.Phone,
                title = "Assistance téléphonique",
                subtitle = "Disponible 24h/7j",
                action = "+33 1 42 34 56 78",
                color = Color(0xFF4CAF50)
            ),
            SupportItem(
                icon = Icons.Default.Email,
                title = "Support par email",
                subtitle = "Réponse sous 2h",
                action = "support@suzosky.com",
                color = Color(0xFF2196F3)
            ),
            SupportItem(
                icon = Icons.Default.Chat,
                title = "Chat en direct",
                subtitle = "Agent disponible",
                action = "Ouvrir le chat",
                color = Color(0xFF9C27B0)
            ),
            SupportItem(
                icon = Icons.Default.Share,
                title = "WhatsApp Business",
                subtitle = "Réponse rapide",
                action = "+33 6 12 34 56 78",
                color = Color(0xFF25D366)
            )
        )
    }
    
    val faqItems = remember {
        listOf(
            FAQItem(
                question = "Comment puis-je modifier mon statut ?",
                answer = "Vous pouvez modifier votre statut (En ligne/Hors ligne) directement depuis l'écran principal en appuyant sur le bouton de statut en haut à droite."
            ),
            FAQItem(
                question = "Quand suis-je payé ?",
                answer = "Les paiements sont effectués chaque semaine le vendredi. Vous recevez vos gains par virement bancaire sur le compte que vous avez renseigné."
            ),
            FAQItem(
                question = "Comment signaler un problème avec une commande ?",
                answer = "En cas de problème, contactez immédiatement le support via le chat en direct ou appelez le numéro d'urgence. N'annulez jamais une commande sans accord préalable."
            ),
            FAQItem(
                question = "Comment optimiser mes gains ?",
                answer = "Pour optimiser vos gains : restez en ligne aux heures de pointe (12h-14h et 19h-21h), acceptez rapidement les commandes, maintenez une bonne note client."
            ),
            FAQItem(
                question = "Que faire en cas de retard ?",
                answer = "Prévenez immédiatement le client et le support via l'application. Communiquez un nouveau délai réaliste et excusez-vous pour le désagrément."
            )
        )
    }

    Box(
        modifier = Modifier
            .fillMaxSize()
            .background(PrimaryDark)
    ) {
        // Arrière-plan avec gradient plus sombre
        Box(
            modifier = Modifier
                .fillMaxSize()
                .background(
                    Brush.verticalGradient(
                        colors = listOf(
                            PrimaryDark,
                            SecondaryBlue,
                            PrimaryDark
                        )
                    )
                )
        )

        Column(
            modifier = Modifier
                .fillMaxSize()
                .verticalScroll(rememberScrollState())
                .padding(16.dp)
        ) {
            // En-tête
            Card(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(bottom = 20.dp),
                colors = CardDefaults.cardColors(
                    containerColor = SecondaryBlue.copy(alpha = 0.8f)
                ),
                shape = RoundedCornerShape(20.dp)
            ) {
                Column(
                    modifier = Modifier
                        .fillMaxWidth()
                        .padding(24.dp),
                    horizontalAlignment = Alignment.CenterHorizontally
                ) {
                    Icon(
                        imageVector = Icons.Default.SupportAgent,
                        contentDescription = null,
                        tint = PrimaryGold,
                        modifier = Modifier.size(48.dp)
                    )
                    
                    Spacer(modifier = Modifier.height(16.dp))
                    
                    Text(
                        text = "Centre d'Aide",
                        style = SuzoskyTextStyles.sectionTitle,
                        color = Color.White,
                        fontSize = 24.sp,
                        fontWeight = FontWeight.Bold
                    )
                    
                    Text(
                        text = "Notre équipe support est là pour vous aider",
                        color = Color.White.copy(alpha = 0.7f),
                        fontSize = 14.sp,
                        textAlign = TextAlign.Center
                    )
                }
            }

            // Options de contact
            Text(
                text = "Nous contacter",
                color = Color.White,
                fontSize = 18.sp,
                fontWeight = FontWeight.SemiBold,
                modifier = Modifier.padding(bottom = 12.dp)
            )
            
            supportItems.forEach { item ->
                SupportContactCard(item = item)
                Spacer(modifier = Modifier.height(8.dp))
            }
            
            Spacer(modifier = Modifier.height(24.dp))
            
            // Section FAQ
            Text(
                text = "Questions Fréquentes",
                color = Color.White,
                fontSize = 18.sp,
                fontWeight = FontWeight.SemiBold,
                modifier = Modifier.padding(bottom = 12.dp)
            )
            
            faqItems.forEach { faq ->
                FAQCard(faqItem = faq)
                Spacer(modifier = Modifier.height(8.dp))
            }
            
            Spacer(modifier = Modifier.height(24.dp))
            
            // Urgence
            Card(
                modifier = Modifier.fillMaxWidth(),
                colors = CardDefaults.cardColors(
                    containerColor = Color.Red.copy(alpha = 0.2f)
                ),
                shape = RoundedCornerShape(16.dp)
            ) {
                Column(
                    modifier = Modifier
                        .fillMaxWidth()
                        .padding(20.dp),
                    horizontalAlignment = Alignment.CenterHorizontally
                ) {
                    Icon(
                        imageVector = Icons.Default.Warning,
                        contentDescription = null,
                        tint = Color.Red,
                        modifier = Modifier.size(32.dp)
                    )
                    
                    Spacer(modifier = Modifier.height(12.dp))
                    
                    Text(
                        text = "Urgence",
                        color = Color.White,
                        fontSize = 18.sp,
                        fontWeight = FontWeight.Bold
                    )
                    
                    Text(
                        text = "En cas d'urgence ou de problème grave, appelez immédiatement",
                        color = Color.White.copy(alpha = 0.8f),
                        fontSize = 12.sp,
                        textAlign = TextAlign.Center
                    )
                    
                    Spacer(modifier = Modifier.height(16.dp))
                    
                    Button(
                        onClick = { /* TODO: Appel d'urgence */ },
                        colors = ButtonDefaults.buttonColors(
                            containerColor = Color.Red
                        ),
                        shape = RoundedCornerShape(12.dp)
                    ) {
                        Icon(
                            imageVector = Icons.Default.Phone,
                            contentDescription = null,
                            modifier = Modifier.size(20.dp)
                        )
                        Spacer(modifier = Modifier.width(8.dp))
                        Text(
                            text = "Urgence: +33 1 42 34 56 99",
                            fontSize = 14.sp,
                            fontWeight = FontWeight.Medium
                        )
                    }
                }
            }
            
            Spacer(modifier = Modifier.height(32.dp))
        }
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun SupportContactCard(item: SupportItem) {
    Card(
        modifier = Modifier.fillMaxWidth(),
        colors = CardDefaults.cardColors(
            containerColor = SecondaryBlue.copy(alpha = 0.6f)
        ),
        shape = RoundedCornerShape(16.dp),
        onClick = {
            // TODO: Implémenter l'action selon le type
        }
    ) {
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .padding(16.dp),
            verticalAlignment = Alignment.CenterVertically
        ) {
            // Icône avec couleur
            Box(
                modifier = Modifier
                    .size(48.dp)
                    .clip(RoundedCornerShape(12.dp))
                    .background(item.color.copy(alpha = 0.2f)),
                contentAlignment = Alignment.Center
            ) {
                Icon(
                    imageVector = item.icon,
                    contentDescription = null,
                    tint = item.color,
                    modifier = Modifier.size(24.dp)
                )
            }
            
            Spacer(modifier = Modifier.width(16.dp))
            
            // Texte
            Column(
                modifier = Modifier.weight(1f)
            ) {
                Text(
                    text = item.title,
                    color = Color.White,
                    fontSize = 16.sp,
                    fontWeight = FontWeight.SemiBold
                )
                Text(
                    text = item.subtitle,
                    color = Color.White.copy(alpha = 0.6f),
                    fontSize = 12.sp
                )
                Text(
                    text = item.action,
                    color = item.color,
                    fontSize = 14.sp,
                    fontWeight = FontWeight.Medium
                )
            }
            
            // Flèche
            Icon(
                imageVector = Icons.Default.ChevronRight,
                contentDescription = null,
                tint = Color.White.copy(alpha = 0.4f),
                modifier = Modifier.size(20.dp)
            )
        }
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun FAQCard(faqItem: FAQItem) {
    var expanded by remember { mutableStateOf(false) }
    
    Card(
        modifier = Modifier.fillMaxWidth(),
        colors = CardDefaults.cardColors(
            containerColor = SecondaryBlue.copy(alpha = 0.4f)
        ),
        shape = RoundedCornerShape(12.dp),
        onClick = { expanded = !expanded }
    ) {
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .padding(16.dp)
        ) {
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                Text(
                    text = faqItem.question,
                    color = Color.White,
                    fontSize = 14.sp,
                    fontWeight = FontWeight.Medium,
                    modifier = Modifier.weight(1f)
                )
                Icon(
                    imageVector = if (expanded) Icons.Default.ExpandLess else Icons.Default.ExpandMore,
                    contentDescription = null,
                    tint = PrimaryGold,
                    modifier = Modifier.size(24.dp)
                )
            }
            
            if (expanded) {
                Spacer(modifier = Modifier.height(12.dp))
                Text(
                    text = faqItem.answer,
                    color = Color.White.copy(alpha = 0.8f),
                    fontSize = 13.sp,
                    lineHeight = 18.sp
                )
            }
        }
    }
}