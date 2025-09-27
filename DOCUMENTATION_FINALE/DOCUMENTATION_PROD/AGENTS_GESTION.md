# 👤 Gestion des Agents Suzosky (Mise à jour 18/09/2025)

## 🆕 Formulaire d'ajout d'agent (UX complet)

- Accessible depuis l'admin, bouton "Nouvel Agent"
- Modal avec sections :
  - **Matricule** (généré automatiquement, non éditable)
  - **Prénoms** (obligatoire)
  - **Nom** (obligatoire)
  - **Date de naissance** (sélecteur calendrier UX)
  - **Lieu de naissance**
  - **Numéro de téléphone**
  - **Lieu de résidence**
  - **Adresse mail** (facultatif)
  - **Numéro de la CNI**
  - **Numéro du permis de conduire**
  - **Fonction** (menu déroulant : Coursier Moto, Concierge)

### 👥 Section "Personne à contacter en cas d'urgence"
- Nom, Prénoms, Lien, Lieu de résidence, Téléphone

### 📎 Section "Pièces justificatives"
- Upload CNI recto/verso
- Upload Permis de conduire recto/verso

---

## 🔄 Synchronisation automatique
- Lors de la création d'un agent :
  - L'agent est ajouté à la table `agents_suzosky`
  - Si la fonction est coursier/coursier moto/coursier vélo, un compte financier est créé dans `comptes_coursiers` (solde=0, statut=actif)
  - Les pièces justificatives sont stockées dans `/admin/uploads/`
- L'agent apparaît instantanément dans la liste des agents ET dans la section finances (si coursier)

---

## 🛡️ Sécurité & intégrité
- Tous les champs obligatoires sont validés côté client et serveur
- Les fichiers uploadés sont stockés avec un nom unique lié au matricule
- La génération du matricule est automatique et non éditable

---

## 📝 Historique & corrections
- Correction de la synchronisation entre agents et comptes financiers (affichage finances.php)
- Ajout d'un script de diagnostic pour vérifier la cohérence agents/coursiers/finances
- Formulaire d'ajout d'agent modernisé et complet (UX, uploads, validation)

---

## 📚 Pour aller plus loin
- Voir aussi :
  - `DOCUMENTATION_FINALE/INDEX_DOCUMENTATION_COMPLETE.md`
  - `Test/_root_migrated/diagnostic_coursiers.php` (diagnostic synchronisation)
  - `admin/finances.php` (gestion comptes coursiers)
  - `admin/agents.php` (listing et ajout agents)
