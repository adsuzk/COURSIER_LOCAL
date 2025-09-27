package com.suzosky.coursier.ui.screens

import androidx.compose.foundation.background
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
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
import com.suzosky.coursier.network.ApiService
import java.text.SimpleDateFormat
import java.util.Calendar
import java.util.Locale
import java.text.NumberFormat

data class GainPeriode(
    val periode: String,
    val totalGains: Double,
    val nombreCommandes: Int,
    val gainMoyen: Double,
    val details: List<GainDetail>
)

data class GainDetail(
    val date: String,
    val commandeId: String,
    val client: String,
    val montant: Double,
    val commission: Double,
    val net: Double
)

@Composable
fun GainsScreen(
    coursierId: Int = 1
) {
    var loading by remember { mutableStateOf(true) }
    var error by remember { mutableStateOf<String?>(null) }
    var gainsData by remember { mutableStateOf(listOf<GainPeriode>()) }

    LaunchedEffect(coursierId) {
        loading = true
        error = null
        ApiService.getCoursierOrders(
            coursierId = coursierId,
            status = "all",
            limit = 500,
            offset = 0
        ) { data, err ->
            if (data == null) {
                error = err ?: "Erreur inconnue"
                loading = false
                return@getCoursierOrders
            }
            try {
                val commandes = (data["commandes"] as? List<*>)?.mapNotNull { it as? Map<*, *> } ?: emptyList()
                gainsData = computeGainsPerPeriods(commandes)
            } catch (e: Exception) {
                error = "Erreur traitement données: ${e.message}"
            } finally {
                loading = false
            }
        }
    }

    val totalGainsGlobal = gainsData.sumOf { it.totalGains }
    val totalCommandesGlobal = gainsData.sumOf { it.nombreCommandes }

    Box(
        modifier = Modifier
            .fillMaxSize()
            .background(
                Brush.verticalGradient(
                    colors = listOf(
                        PrimaryDark,
                        SecondaryBlue.copy(alpha = 0.8f),
                        PrimaryDark
                    )
                )
            )
    ) {
        Column(
            modifier = Modifier
                .fillMaxSize()
                .padding(16.dp)
                .verticalScroll(rememberScrollState())
        ) {
            // Header avec titre et résumé global
            Card(
                modifier = Modifier.fillMaxWidth(),
                colors = CardDefaults.cardColors(
                    containerColor = GlassBg
                ),
                elevation = CardDefaults.cardElevation(defaultElevation = 8.dp),
                shape = RoundedCornerShape(16.dp)
            ) {
                Column(
                    modifier = Modifier.padding(20.dp),
                    horizontalAlignment = Alignment.CenterHorizontally
                ) {
                    Text(
                        text = "💰 MES GAINS",
                        style = MaterialTheme.typography.headlineMedium,
                        fontWeight = FontWeight.Bold,
                        color = PrimaryGold,
                        textAlign = TextAlign.Center
                    )
                    
                    Spacer(modifier = Modifier.height(16.dp))
                    
                    // Résumé global avec design amélioré
                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.SpaceEvenly
                    ) {
                        GainsSummaryItem(
                            title = "Total Gagné",
                            value = formatFcfa(totalGainsGlobal.toInt()),
                            icon = Icons.Default.AccountBalanceWallet,
                            color = PrimaryGold
                        )
                        
                        GainsSummaryItem(
                            title = "Courses",
                            value = "$totalCommandesGlobal",
                            icon = Icons.Default.DirectionsBike,
                            color = SuccessGreen
                        )
                        
                        GainsSummaryItem(
                            title = "Moyenne",
                            value = formatFcfa(
                                if (totalCommandesGlobal > 0) (totalGainsGlobal / totalCommandesGlobal).toInt() else 0
                            ),
                            icon = Icons.Default.TrendingUp,
                            color = SuzoskySecondary
                        )
                    }
                }
            }
            
            Spacer(modifier = Modifier.height(20.dp))
            
            if (error != null) {
                Card(
                    modifier = Modifier.fillMaxWidth(),
                    colors = CardDefaults.cardColors(containerColor = GlassBg),
                    shape = RoundedCornerShape(12.dp)
                ) {
                    Text(
                        text = error ?: "",
                        color = Color.Red,
                        modifier = Modifier.padding(16.dp)
                    )
                }
            } else if (loading) {
                Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.Center) {
                    CircularProgressIndicator()
                }
            } else {
                // Liste des périodes avec design amélioré + détails compact
                gainsData.forEach { periode ->
                    GainsPeriodCard(periode = periode)
                    if (periode.details.isNotEmpty()) {
                        Spacer(modifier = Modifier.height(8.dp))
                        GainsDetailsTable(details = periode.details)
                    }
                    Spacer(modifier = Modifier.height(16.dp))
                }
            }
        }
    }
}

private fun computeGainsPerPeriods(commandes: List<Map<*, *>>): List<GainPeriode> {
    // On attend des champs standards mappés dans ApiService.getCoursierOrders
    val sdfDateTime = SimpleDateFormat("yyyy-MM-dd HH:mm:ss", Locale.getDefault())
    val nowCal = Calendar.getInstance()
    data class Item(val date: java.util.Date, val montant: Double, val id: String, val client: String)
    val items = commandes.mapNotNull { m ->
        try {
            val dateStr = "${m["dateCommande"] ?: ""} ${m["heureCommande"] ?: "00:00:00"}"
            val date = sdfDateTime.parse(dateStr) ?: return@mapNotNull null
            val montant = (m["prix"] as? Number)?.toDouble() ?: 0.0
            val id = (m["id"] as? String) ?: ""
            val client = (m["clientNom"] as? String) ?: ""
            Item(date, montant, id, client)
        } catch (_: Exception) { null }
    }

    fun isSameWeek(c1: Calendar, c2: Calendar) =
        c1.get(Calendar.WEEK_OF_YEAR) == c2.get(Calendar.WEEK_OF_YEAR) && c1.get(Calendar.YEAR) == c2.get(Calendar.YEAR)

    val weeks = listOf(0, 1, 2).map { offsetWeeks ->
        val cal = Calendar.getInstance().apply { add(Calendar.WEEK_OF_YEAR, -offsetWeeks) }
        val weekItems = items.filter { itCalItem ->
            val c = Calendar.getInstance().apply { time = itCalItem.date }
            isSameWeek(c, cal)
        }
        val total = weekItems.sumOf { it.montant }
        val count = weekItems.size
        val moyenne = if (count > 0) total / count else 0.0
        val label = when (offsetWeeks) {
            0 -> "Cette semaine"
            1 -> "Semaine dernière"
            else -> "Il y a 2 semaines"
        }
        val details = weekItems.sortedByDescending { it.date }.map { wi ->
            GainDetail(
                date = SimpleDateFormat("dd/MM/yyyy", Locale.getDefault()).format(wi.date),
                commandeId = wi.id,
                client = wi.client,
                montant = wi.montant,
                commission = 0.0,
                net = wi.montant
            )
        }
        GainPeriode(
            periode = label,
            totalGains = total,
            nombreCommandes = count,
            gainMoyen = moyenne,
            details = details
        )
    }
    return weeks
}

@Composable
fun GainsPeriodCard(periode: GainPeriode) {
    Card(
        modifier = Modifier.fillMaxWidth(),
        colors = CardDefaults.cardColors(
            containerColor = GlassBg
        ),
        elevation = CardDefaults.cardElevation(defaultElevation = 6.dp),
        shape = RoundedCornerShape(16.dp)
    ) {
        Column(
            modifier = Modifier.padding(20.dp)
        ) {
            // En-tête de la période
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                Text(
                    text = periode.periode,
                    style = MaterialTheme.typography.titleLarge,
                    fontWeight = FontWeight.Bold,
                    color = PrimaryGold
                )
                
                Text(
                    text = formatFcfa(periode.totalGains.toInt()),
                    style = MaterialTheme.typography.headlineSmall,
                    fontWeight = FontWeight.Bold,
                    color = SuccessGreen
                )
            }
            
            Spacer(modifier = Modifier.height(12.dp))
            
            // Statistiques de la période
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceEvenly
            ) {
                StatItem(
                    label = "Courses",
                    value = "${periode.nombreCommandes}",
                    color = SuzoskySecondary
                )
                
                StatItem(
                    label = "Moyenne",
                    value = formatFcfa(periode.gainMoyen.toInt()),
                    color = PrimaryGold
                )
            }
        }
    }
}

@Composable
fun GainsDetailsTable(details: List<GainDetail>) {
    var showAll by remember { mutableStateOf(false) }
    val maxRows = 6
    val displayed = if (showAll) details else details.take(maxRows)
    val total = details.sumOf { it.net }
    Card(
        modifier = Modifier.fillMaxWidth(),
        colors = CardDefaults.cardColors(containerColor = GlassBg),
        shape = RoundedCornerShape(12.dp)
    ) {
        Column(modifier = Modifier.padding(12.dp)) {
            // Header
            Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) {
                Text("Date", style = MaterialTheme.typography.labelSmall, color = Color.White.copy(alpha = 0.7f))
                Text("Client", style = MaterialTheme.typography.labelSmall, color = Color.White.copy(alpha = 0.7f))
                Text("Net", style = MaterialTheme.typography.labelSmall, color = Color.White.copy(alpha = 0.7f))
            }
            Spacer(Modifier.height(8.dp))
            displayed.forEach { d ->
                Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) {
                    Text(d.date, style = MaterialTheme.typography.bodySmall)
                    Text(d.client, style = MaterialTheme.typography.bodySmall)
                    Text(formatFcfa(d.net.toInt()), style = MaterialTheme.typography.bodySmall, color = PrimaryGold)
                }
                Divider(color = Color.White.copy(alpha = 0.06f))
            }

            // Total row
            Spacer(Modifier.height(6.dp))
            Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) {
                Text("Total", style = MaterialTheme.typography.labelSmall, fontWeight = FontWeight.Bold)
                Spacer(Modifier.weight(1f))
                Text(formatFcfa(total.toInt()), style = MaterialTheme.typography.labelSmall, fontWeight = FontWeight.Bold, color = PrimaryGold)
            }

            // Voir toutes les lignes
            if (!showAll && details.size > maxRows) {
                Spacer(Modifier.height(8.dp))
                TextButton(onClick = { showAll = true }) {
                    Text("Voir toutes les lignes")
                }
            }
        }
    }
}

private fun formatFcfa(amount: Int): String {
    val nf = NumberFormat.getInstance(Locale.FRANCE)
    return nf.format(amount.coerceAtLeast(0)) + " FCFA"
}

@Composable
fun StatItem(
    label: String,
    value: String,
    color: Color
) {
    Column(
        horizontalAlignment = Alignment.CenterHorizontally
    ) {
        Text(
            text = value,
            style = MaterialTheme.typography.titleMedium,
            fontWeight = FontWeight.Bold,
            color = color
        )
        Text(
            text = label,
            style = MaterialTheme.typography.bodySmall,
            color = Color.White.copy(alpha = 0.7f)
        )
    }
}

@Composable
fun GainsSummaryItem(
    title: String,
    value: String,
    icon: ImageVector,
    color: Color
) {
    Column(
        horizontalAlignment = Alignment.CenterHorizontally,
        modifier = Modifier.padding(8.dp)
    ) {
        Icon(
            imageVector = icon,
            contentDescription = title,
            tint = color,
            modifier = Modifier.size(32.dp)
        )
        
        Spacer(modifier = Modifier.height(8.dp))
        
        Text(
            text = value,
            style = MaterialTheme.typography.titleLarge,
            fontWeight = FontWeight.Bold,
            color = Color.White,
            textAlign = TextAlign.Center
        )
        
        Text(
            text = title,
            style = MaterialTheme.typography.bodySmall,
            color = Color.White.copy(alpha = 0.7f),
            textAlign = TextAlign.Center
        )
    }
}