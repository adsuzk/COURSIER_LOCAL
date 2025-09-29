import androidx.compose.foundation.background
package com.suzosky.coursier.ui.screens

import androidx.compose.foundation.layout.*
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.lazy.items
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
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.suzosky.coursier.ui.theme.*
import java.text.NumberFormat
import java.text.SimpleDateFormat
import java.util.Calendar
import java.util.Locale

// Small enums used by this screen (moved here to avoid missing reference errors)
enum class SortField { DATE, MONTANT }
enum class SortOrder { ASC, DESC }
enum class PeriodFilter { TOUT, AUJOURD_HUI, SEMAINE, MOIS }

// Data
data class HistoriqueCommande(
    val id: String,
    val clientNom: String,
    val adresseEnlevement: String,
    val adresseLivraison: String,
    val prix: Double,
    val date: String,
    val heure: String,
    val typeCommande: String = "Standard",
    val statut: String = "en_cours"
)

// Screen
@Composable
fun HistoriqueScreen() {
    Column(modifier = Modifier
        .fillMaxSize()
        .background(MaterialTheme.colorScheme.background)) {

        Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceEvenly) {
            FilterChip(onClick = {}, label = { Text("Tri: Date") }, selected = false,
                colors = FilterChipDefaults.filterChipColors(selectedContainerColor = PrimaryGold.copy(alpha = 0.2f), selectedLabelColor = PrimaryGold))
            FilterChip(onClick = {}, label = { Text("Tri: Montant") }, selected = false,
                colors = FilterChipDefaults.filterChipColors(selectedContainerColor = PrimaryGold.copy(alpha = 0.2f), selectedLabelColor = PrimaryGold))
            FilterChip(onClick = {}, label = { Text("Tri secondaire: Statut") }, selected = false,
                colors = FilterChipDefaults.filterChipColors(selectedContainerColor = PrimaryGold, selectedLabelColor = PrimaryDark))
            IconButton(onClick = {}) { Icon(Icons.Default.ArrowDownward, contentDescription = null, tint = PrimaryGold) }
        }

        Spacer(modifier = Modifier.height(16.dp))

        Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceEvenly) {
            HistoryStatItem(value = "0", label = "Total", color = PrimaryGold)
            HistoryStatItem(value = "0", label = "Livrées", color = Color.Green)
            HistoryStatItem(value = formatFcfa(0), label = "Gains", color = Color(0xFF00BCD4))
        }

        Spacer(modifier = Modifier.height(12.dp))

        // TODO: list of commandes
    }
}

// Small components
@Composable
private fun HistoryStatItem(value: String, label: String, color: Color) {
    Column(horizontalAlignment = Alignment.CenterHorizontally) {
        Text(text = value, color = color, fontSize = 20.sp, fontWeight = FontWeight.Bold)
        Text(text = label, color = Color.White.copy(alpha = 0.7f), fontSize = 12.sp)
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun CommandeHistoriqueCard(commande: HistoriqueCommande) {
    val statutColor = when (commande.statut) {
        "livree" -> Color.Green
        "annulee" -> Color.Red
        "en_cours" -> WarningYellow
        else -> Color.Gray
    }

    val statutText = when (commande.statut) {
        "livree" -> "Livrée"
        "annulee" -> "Annulée"
        "en_cours" -> "En cours"
        else -> "Inconnue"
    }

    Card(modifier = Modifier.fillMaxWidth(), colors = CardDefaults.cardColors(containerColor = GlassBg.copy(alpha = 0.8f)), shape = RoundedCornerShape(16.dp), onClick = {}) {
        Column(modifier = Modifier
            .fillMaxWidth()
            .padding(16.dp)) {
            Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween, verticalAlignment = Alignment.Top) {
                Column {
                    Text(text = "Commande #${commande.id}", color = Color.White, fontSize = 16.sp, fontWeight = FontWeight.Bold)
                    Text(text = "${commande.date} à ${commande.heure}", color = Color.White.copy(alpha = 0.6f), fontSize = 12.sp)
                }

                Surface(color = statutColor.copy(alpha = 0.2f), shape = RoundedCornerShape(8.dp)) {
                    Text(text = statutText, modifier = Modifier.padding(horizontal = 12.dp, vertical = 4.dp), color = statutColor, fontSize = 11.sp, fontWeight = FontWeight.Medium)
                }
            }

            Spacer(modifier = Modifier.height(12.dp))

            Row(verticalAlignment = Alignment.CenterVertically) {
                Icon(imageVector = Icons.Default.Person, contentDescription = null, tint = PrimaryGold, modifier = Modifier.size(16.dp))
                Spacer(modifier = Modifier.width(8.dp))
                Text(text = commande.clientNom, color = Color.White, fontSize = 14.sp, fontWeight = FontWeight.Medium)

                if (commande.typeCommande != "Standard") {
                    Spacer(modifier = Modifier.width(8.dp))
                    Surface(color = PrimaryGold.copy(alpha = 0.2f), shape = RoundedCornerShape(6.dp)) {
                        Text(text = commande.typeCommande, modifier = Modifier.padding(horizontal = 8.dp, vertical = 2.dp), color = PrimaryGold, fontSize = 10.sp, fontWeight = FontWeight.Medium)
                    }
                }
            }

            Spacer(modifier = Modifier.height(8.dp))

            Column(verticalArrangement = Arrangement.spacedBy(4.dp)) {
                AddressRow(icon = Icons.Default.LocationOn, address = commande.adresseEnlevement, label = "Enlèvement")
                AddressRow(icon = Icons.Default.Flag, address = commande.adresseLivraison, label = "Livraison")
            }

            Spacer(modifier = Modifier.height(12.dp))

            Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.End) {
                Text(text = formatFcfa(commande.prix.toInt()), color = PrimaryGold, fontSize = 18.sp, fontWeight = FontWeight.Bold)
            }
        }
    }
}

// Utilities
private fun formatFcfa(amount: Int): String {
    val nf = NumberFormat.getInstance(Locale("fr", "FR"))
    return nf.format(amount.coerceAtLeast(0)) + " FCFA"
}

private fun filterBySearch(list: List<HistoriqueCommande>, query: String): List<HistoriqueCommande> {
    val q = query.trim().lowercase(Locale.getDefault())
    if (q.isEmpty()) return list
    return list.filter { c ->
        c.clientNom.lowercase(Locale.getDefault()).contains(q) ||
                c.adresseEnlevement.lowercase(Locale.getDefault()).contains(q) ||
                c.adresseLivraison.lowercase(Locale.getDefault()).contains(q) ||
                c.id.lowercase(Locale.getDefault()).contains(q)
    }
}

private fun sortCommandes(list: List<HistoriqueCommande>, field: SortField, order: SortOrder, secondaryByStatus: Boolean): List<HistoriqueCommande> {
    val sdf = SimpleDateFormat("yyyy-MM-dd HH:mm:ss", Locale.getDefault())
    fun statusRank(s: String): Int = when (s) {
        "livree" -> 0
        "en_cours" -> 1
        "annulee" -> 2
        else -> 3
    }
    val comparatorPrimary = when (field) {
        SortField.DATE -> compareBy<HistoriqueCommande> { runCatching { sdf.parse("${it.date} ${it.heure}")?.time ?: 0L }.getOrDefault(0L) }
        SortField.MONTANT -> compareBy<HistoriqueCommande> { it.prix }
    }
    val comparator = if (secondaryByStatus) comparatorPrimary.thenBy { statusRank(it.statut) } else comparatorPrimary
    val sorted = list.sortedWith(comparator)
    return if (order == SortOrder.ASC) sorted else sorted.asReversed()
}

@Composable
private fun DateHeader(dateStr: String) {
    Surface(color = MaterialTheme.colorScheme.surface.copy(alpha = 0.9f), shadowElevation = 2.dp) {
        Row(modifier = Modifier.fillMaxWidth().padding(horizontal = 12.dp, vertical = 6.dp), verticalAlignment = Alignment.CenterVertically) {
            Icon(imageVector = Icons.Default.CalendarToday, contentDescription = null, tint = PrimaryGold, modifier = Modifier.size(14.dp))
            Spacer(modifier = Modifier.width(8.dp))
            Text(text = dateStr, color = MaterialTheme.colorScheme.onSurface.copy(alpha = 0.8f), style = MaterialTheme.typography.labelMedium)
        }
    }
}

private fun filterByPeriod(list: List<HistoriqueCommande>, period: PeriodFilter): List<HistoriqueCommande> {
    if (period == PeriodFilter.TOUT) return list
    val sdf = SimpleDateFormat("yyyy-MM-dd", Locale.getDefault())
    val calNow = Calendar.getInstance()

    fun sameDay(d: java.util.Date, cal: Calendar): Boolean {
        val c = Calendar.getInstance().apply { time = d }
        return c.get(Calendar.YEAR) == cal.get(Calendar.YEAR) && c.get(Calendar.DAY_OF_YEAR) == cal.get(Calendar.DAY_OF_YEAR)
    }
    fun sameWeek(d: java.util.Date, cal: Calendar): Boolean {
        val c = Calendar.getInstance().apply { time = d }
        return c.get(Calendar.WEEK_OF_YEAR) == cal.get(Calendar.WEEK_OF_YEAR) && c.get(Calendar.YEAR) == cal.get(Calendar.YEAR)
    }
    fun sameMonth(d: java.util.Date, cal: Calendar): Boolean {
        val c = Calendar.getInstance().apply { time = d }
        return c.get(Calendar.MONTH) == cal.get(Calendar.MONTH) && c.get(Calendar.YEAR) == cal.get(Calendar.YEAR)
    }

    return list.filter { item ->
        try {
            val date = sdf.parse(item.date) ?: return@filter false
            when (period) {
                PeriodFilter.AUJOURD_HUI -> sameDay(date, calNow)
                PeriodFilter.SEMAINE -> sameWeek(date, calNow)
                PeriodFilter.MOIS -> sameMonth(date, calNow)
                PeriodFilter.TOUT -> true
            }
        } catch (_: Exception) { false }
    }
}

@Composable
private fun AddressRow(icon: ImageVector, address: String, label: String) {
    Row(verticalAlignment = Alignment.Top) {
        Icon(imageVector = icon, contentDescription = null, tint = Color.White.copy(alpha = 0.6f), modifier = Modifier.size(14.dp))
        Spacer(modifier = Modifier.width(8.dp))
        Column {
            Text(text = label, color = Color.White.copy(alpha = 0.5f), fontSize = 10.sp)
            Text(text = address, color = Color.White.copy(alpha = 0.8f), fontSize = 12.sp, maxLines = 1, overflow = TextOverflow.Ellipsis)
        }
    }
}