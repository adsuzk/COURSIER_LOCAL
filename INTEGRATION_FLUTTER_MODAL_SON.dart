// 🎯 CODE FLUTTER POUR INTÉGRER LE SYSTÈME DE NOTIFICATION

// ════════════════════════════════════════════════════════════════
// 1. DÉPENDANCES À AJOUTER DANS pubspec.yaml
// ════════════════════════════════════════════════════════════════

dependencies:
  flutter:
    sdk: flutter
  audioplayers: ^5.2.1          # Pour le son
  vibration: ^1.8.4             # Pour la vibration
  flutter_local_notifications: ^16.3.0  # Pour notifications système
  http: ^1.1.0                  # Pour les requêtes API

// ════════════════════════════════════════════════════════════════
// 2. SERVICE DE POLLING (À CRÉER: lib/services/polling_service.dart)
// ════════════════════════════════════════════════════════════════

import 'dart:async';
import 'package:http/http.dart' as http;
import 'dart:convert';
import 'package:audioplayers/audioplayers.dart';
import 'package:vibration/vibration.dart';

class PollingService {
  static const String API_BASE = 'http://your-server.com/COURSIER_LOCAL';
  static const Duration POLLING_INTERVAL = Duration(seconds: 1);
  
  final int coursierId;
  Timer? _pollingTimer;
  List<dynamic> _lastCommandes = [];
  final AudioPlayer _audioPlayer = AudioPlayer();
  
  // Callback quand nouvelle commande détectée
  Function(Map<String, dynamic>)? onNewCommande;
  
  PollingService({required this.coursierId});
  
  // Démarrer le polling
  void start() {
    print('🚀 Démarrage du polling (1 seconde)');
    _pollingTimer?.cancel();
    _pollingTimer = Timer.periodic(POLLING_INTERVAL, (_) => _poll());
    _poll(); // Premier appel immédiat
  }
  
  // Arrêter le polling
  void stop() {
    print('⏹️ Arrêt du polling');
    _pollingTimer?.cancel();
  }
  
  // Polling des commandes
  Future<void> _poll() async {
    try {
      final response = await http.get(
        Uri.parse('$API_BASE/mobile_sync_api.php?action=get_commandes&coursier_id=$coursierId'),
      ).timeout(Duration(seconds: 5));
      
      if (response.statusCode == 200) {
        final data = jsonDecode(response.body);
        
        if (data['success'] && data['commandes'] != null) {
          final nouvelles = (data['commandes'] as List)
              .where((c) => c['statut'] == 'nouvelle')
              .toList();
          
          // Détecter nouvelle commande
          for (var commande in nouvelles) {
            bool isNew = !_lastCommandes.any((last) => last['id'] == commande['id']);
            if (isNew) {
              print('🚨 NOUVELLE COMMANDE: ${commande['code_commande']}');
              _onNewCommandeDetected(commande);
            }
          }
          
          _lastCommandes = nouvelles;
        }
      }
    } catch (e) {
      print('❌ Erreur polling: $e');
    }
  }
  
  // Quand nouvelle commande détectée
  void _onNewCommandeDetected(Map<String, dynamic> commande) {
    // Jouer le son
    _playNotificationSound();
    
    // Vibrer
    _vibrate();
    
    // Appeler le callback
    onNewCommande?.call(commande);
  }
  
  // Jouer le son de notification (3 bips)
  Future<void> _playNotificationSound() async {
    for (int i = 0; i < 3; i++) {
      await _audioPlayer.play(AssetSource('sounds/notification.mp3'));
      await Future.delayed(Duration(milliseconds: 400));
    }
  }
  
  // Vibrer le téléphone
  Future<void> _vibrate() async {
    if (await Vibration.hasVibrator() ?? false) {
      Vibration.vibrate(
        pattern: [0, 200, 100, 200, 100, 200], // Motif: attendre-vibrer-pause-vibrer...
        intensities: [0, 255, 0, 255, 0, 255],
      );
    }
  }
  
  void dispose() {
    stop();
    _audioPlayer.dispose();
  }
}

// ════════════════════════════════════════════════════════════════
// 3. MODAL POPUP (À CRÉER: lib/widgets/commande_modal.dart)
// ════════════════════════════════════════════════════════════════

import 'package:flutter/material.dart';

class CommandeModal extends StatelessWidget {
  final Map<String, dynamic> commande;
  final VoidCallback onAccept;
  final VoidCallback onRefuse;
  
  const CommandeModal({
    required this.commande,
    required this.onAccept,
    required this.onRefuse,
  });
  
  @override
  Widget build(BuildContext context) {
    return Dialog(
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
      child: Container(
        padding: EdgeInsets.all(30),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            // Icône animée
            TweenAnimationBuilder(
              tween: Tween<double>(begin: 0, end: 1),
              duration: Duration(milliseconds: 500),
              builder: (context, double value, child) {
                return Transform.scale(
                  scale: value,
                  child: Text('🚨', style: TextStyle(fontSize: 60)),
                );
              },
            ),
            SizedBox(height: 15),
            
            // Titre
            Text(
              'NOUVELLE COMMANDE!',
              style: TextStyle(
                fontSize: 24,
                fontWeight: FontWeight.bold,
                color: Color(0xFFD4A853),
              ),
            ),
            Text(
              commande['code_commande'] ?? '',
              style: TextStyle(fontSize: 14, color: Colors.grey),
            ),
            SizedBox(height: 20),
            
            // Détails
            Container(
              padding: EdgeInsets.all(15),
              decoration: BoxDecoration(
                color: Colors.grey[100],
                borderRadius: BorderRadius.circular(10),
              ),
              child: Column(
                children: [
                  _buildDetail('📍', 'Départ', commande['adresse_depart'] ?? 'N/A'),
                  Divider(),
                  _buildDetail('🎯', 'Arrivée', commande['adresse_arrivee'] ?? 'N/A'),
                  Divider(),
                  _buildDetail('💰', 'Prix', '${commande['prix_estime'] ?? '0'} FCFA'),
                  Divider(),
                  _buildDetail('📏', 'Distance', '${commande['distance_estimee'] ?? 'N/A'} km'),
                ],
              ),
            ),
            SizedBox(height: 25),
            
            // Boutons
            Row(
              children: [
                Expanded(
                  child: ElevatedButton(
                    onPressed: onAccept,
                    style: ElevatedButton.styleFrom(
                      backgroundColor: Colors.green,
                      padding: EdgeInsets.symmetric(vertical: 15),
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(10),
                      ),
                    ),
                    child: Text(
                      '✅ ACCEPTER',
                      style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold),
                    ),
                  ),
                ),
                SizedBox(width: 15),
                Expanded(
                  child: ElevatedButton(
                    onPressed: onRefuse,
                    style: ElevatedButton.styleFrom(
                      backgroundColor: Colors.red,
                      padding: EdgeInsets.symmetric(vertical: 15),
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(10),
                      ),
                    ),
                    child: Text(
                      '❌ REFUSER',
                      style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold),
                    ),
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
  
  Widget _buildDetail(String icon, String label, String value) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(icon, style: TextStyle(fontSize: 20)),
        SizedBox(width: 10),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                label,
                style: TextStyle(fontWeight: FontWeight.bold, fontSize: 12),
              ),
              Text(value, style: TextStyle(fontSize: 14)),
            ],
          ),
        ),
      ],
    );
  }
}

// ════════════════════════════════════════════════════════════════
// 4. UTILISATION DANS LA PAGE PRINCIPALE (lib/pages/coursier_home.dart)
// ════════════════════════════════════════════════════════════════

import 'package:flutter/material.dart';
import '../services/polling_service.dart';
import '../widgets/commande_modal.dart';
import 'package:http/http.dart' as http;

class CoursierHomePage extends StatefulWidget {
  final int coursierId;
  
  const CoursierHomePage({required this.coursierId});
  
  @override
  _CoursierHomePageState createState() => _CoursierHomePageState();
}

class _CoursierHomePageState extends State<CoursierHomePage> {
  late PollingService _pollingService;
  
  @override
  void initState() {
    super.initState();
    
    // Initialiser le service de polling
    _pollingService = PollingService(coursierId: widget.coursierId);
    
    // Définir le callback pour nouvelle commande
    _pollingService.onNewCommande = (commande) {
      _showCommandeModal(commande);
    };
    
    // Démarrer le polling
    _pollingService.start();
  }
  
  @override
  void dispose() {
    _pollingService.dispose();
    super.dispose();
  }
  
  // Afficher le modal
  void _showCommandeModal(Map<String, dynamic> commande) {
    showDialog(
      context: context,
      barrierDismissible: false, // Ne pas fermer en cliquant dehors
      builder: (context) => CommandeModal(
        commande: commande,
        onAccept: () => _acceptCommande(commande['id'], commande['code_commande']),
        onRefuse: () => _refuseCommande(commande['id'], commande['code_commande']),
      ),
    );
  }
  
  // Accepter la commande
  Future<void> _acceptCommande(int commandeId, String code) async {
    Navigator.of(context).pop(); // Fermer le modal
    
    try {
      final response = await http.post(
        Uri.parse('${PollingService.API_BASE}/mobile_sync_api.php?action=accept_commande&coursier_id=${widget.coursierId}&commande_id=$commandeId'),
      );
      
      // Afficher confirmation
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('✅ Commande $code acceptée!')),
      );
    } catch (e) {
      print('Erreur: $e');
    }
  }
  
  // Refuser la commande
  Future<void> _refuseCommande(int commandeId, String code) async {
    Navigator.of(context).pop(); // Fermer le modal
    
    try {
      final response = await http.post(
        Uri.parse('${PollingService.API_BASE}/mobile_sync_api.php?action=refuse_commande&coursier_id=${widget.coursierId}&commande_id=$commandeId&raison=Refus coursier'),
      );
      
      // Afficher confirmation
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('❌ Commande $code refusée')),
      );
    } catch (e) {
      print('Erreur: $e');
    }
  }
  
  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text('Coursier Suzosky'),
        actions: [
          // Indicateur de polling
          Padding(
            padding: EdgeInsets.all(16),
            child: Icon(Icons.circle, color: Colors.green, size: 12),
          ),
        ],
      ),
      body: Center(
        child: Text('En attente de commandes...'),
      ),
    );
  }
}

// ════════════════════════════════════════════════════════════════
// 5. FICHIER SON À AJOUTER (assets/sounds/notification.mp3)
// ════════════════════════════════════════════════════════════════

// Créez un fichier MP3 avec un son de notification ou utilisez un son gratuit
// Placez-le dans: assets/sounds/notification.mp3
// N'oubliez pas de le déclarer dans pubspec.yaml:
//
// flutter:
//   assets:
//     - assets/sounds/notification.mp3

// ════════════════════════════════════════════════════════════════
// ✅ INSTALLATION TERMINÉE
// ════════════════════════════════════════════════════════════════
