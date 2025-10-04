package com.suzosky.coursierclient.ui

import androidx.compose.foundation.*
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.suzosky.coursierclient.ui.theme.*

@Composable
fun CguScreen() {
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
                .padding(24.dp)
        ) {
            // Header
            Row(
                modifier = Modifier.fillMaxWidth(),
                verticalAlignment = Alignment.CenterVertically
            ) {
                Icon(
                    imageVector = Icons.Filled.Gavel,
                    contentDescription = null,
                    tint = Gold,
                    modifier = Modifier.size(32.dp)
                )
                Spacer(Modifier.width(16.dp))
                Text(
                    text = "Conditions Générales d'Utilisation",
                    fontSize = 22.sp,
                    fontWeight = FontWeight.Bold,
                    color = Gold
                )
            }

            Spacer(Modifier.height(24.dp))

            // Scrollable content
            Card(
                modifier = Modifier.fillMaxSize(),
                shape = RoundedCornerShape(16.dp),
                colors = CardDefaults.cardColors(
                    containerColor = Color.White.copy(alpha = 0.05f)
                ),
                border = BorderStroke(1.dp, Gold.copy(alpha = 0.1f))
            ) {
                Column(
                    modifier = Modifier
                        .fillMaxSize()
                        .verticalScroll(rememberScrollState())
                        .padding(20.dp)
                ) {
                    SectionTitle(title = "1. Présentation du service")
                    SectionContent(
                        text = "SUZOSKY est une plateforme de mise en relation entre clients et coursiers professionnels pour la livraison rapide de colis et documents en Côte d'Ivoire."
                    )

                    Spacer(Modifier.height(20.dp))

                    SectionTitle(title = "2. Acceptation des conditions")
                    SectionContent(
                        text = "En utilisant notre application, vous acceptez pleinement et sans réserve les présentes conditions générales d'utilisation. Si vous n'acceptez pas ces conditions, veuillez ne pas utiliser nos services."
                    )

                    Spacer(Modifier.height(20.dp))

                    SectionTitle(title = "3. Inscription et compte utilisateur")
                    SectionContent(
                        text = "• Vous devez fournir des informations exactes et à jour lors de votre inscription\n" +
                                "• Vous êtes responsable de la confidentialité de vos identifiants\n" +
                                "• Vous devez être majeur pour créer un compte\n" +
                                "• Un compte ne peut être utilisé que par une seule personne"
                    )

                    Spacer(Modifier.height(20.dp))

                    SectionTitle(title = "4. Commandes et paiements")
                    SectionContent(
                        text = "• Les prix sont affichés en Francs CFA (FCFA) et incluent tous les frais\n" +
                                "• Les paiements peuvent être effectués en espèces ou via CinetPay\n" +
                                "• Une fois confirmée, toute commande est définitive\n" +
                                "• Les annulations sont possibles selon notre politique de remboursement"
                    )

                    Spacer(Modifier.height(20.dp))

                    SectionTitle(title = "5. Responsabilités")
                    SectionContent(
                        text = "• Le client est responsable de l'exactitude des adresses de livraison\n" +
                                "• Le client doit s'assurer que le colis ne contient pas d'objets interdits\n" +
                                "• SUZOSKY décline toute responsabilité en cas de dommages dus à un emballage inadéquat\n" +
                                "• Les coursiers sont des partenaires indépendants"
                    )

                    Spacer(Modifier.height(20.dp))

                    SectionTitle(title = "6. Objets interdits")
                    SectionContent(
                        text = "Il est strictement interdit de transporter:\n" +
                                "• Substances illégales ou dangereuses\n" +
                                "• Armes et munitions\n" +
                                "• Produits périssables sans emballage approprié\n" +
                                "• Animaux vivants\n" +
                                "• Documents ou objets de valeur supérieure à 500 000 FCFA sans assurance"
                    )

                    Spacer(Modifier.height(20.dp))

                    SectionTitle(title = "7. Données personnelles")
                    SectionContent(
                        text = "Vos données personnelles sont collectées et traitées conformément à notre politique de confidentialité. Nous nous engageons à protéger vos informations et à ne les partager qu'avec nos partenaires coursiers pour l'exécution de vos commandes."
                    )

                    Spacer(Modifier.height(20.dp))

                    SectionTitle(title = "8. Réclamations")
                    SectionContent(
                        text = "En cas de problème avec une livraison, vous disposez de 48 heures pour nous contacter via l'application ou par email. Nous nous engageons à traiter chaque réclamation dans un délai de 72 heures."
                    )

                    Spacer(Modifier.height(20.dp))

                    SectionTitle(title = "9. Modifications des CGU")
                    SectionContent(
                        text = "SUZOSKY se réserve le droit de modifier les présentes conditions à tout moment. Les utilisateurs seront informés des modifications importantes via l'application."
                    )

                    Spacer(Modifier.height(20.dp))

                    SectionTitle(title = "10. Contact")
                    SectionContent(
                        text = "Pour toute question concernant ces conditions:\n" +
                                "• Email: support@suzosky.ci\n" +
                                "• Téléphone: +225 XX XX XX XX XX\n" +
                                "• Adresse: Abidjan, Côte d'Ivoire"
                    )

                    Spacer(Modifier.height(32.dp))

                    Divider(color = Gold.copy(alpha = 0.2f), thickness = 1.dp)

                    Spacer(Modifier.height(16.dp))

                    Text(
                        text = "Dernière mise à jour: Janvier 2025",
                        fontSize = 12.sp,
                        color = Color.White.copy(alpha = 0.5f),
                        modifier = Modifier.align(Alignment.CenterHorizontally)
                    )

                    Text(
                        text = "© 2025 SUZOSKY. Tous droits réservés.",
                        fontSize = 12.sp,
                        color = Color.White.copy(alpha = 0.5f),
                        modifier = Modifier.align(Alignment.CenterHorizontally)
                    )

                    Spacer(Modifier.height(20.dp))
                }
            }
        }
    }
}

@Composable
private fun SectionTitle(title: String) {
    Text(
        text = title,
        fontSize = 16.sp,
        fontWeight = FontWeight.Bold,
        color = Gold,
        lineHeight = 22.sp
    )
}

@Composable
private fun SectionContent(text: String) {
    Text(
        text = text,
        fontSize = 14.sp,
        color = Color.White.copy(alpha = 0.8f),
        lineHeight = 20.sp,
        modifier = Modifier.padding(top = 8.dp)
    )
}
