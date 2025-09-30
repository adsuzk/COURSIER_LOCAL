-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- HÃ´te : localhost
-- GÃ©nÃ©rÃ© le : sam. 27 sep. 2025 Ã  03:43
-- Version du serveur : 10.11.11-MariaDB-deb12
-- Version de PHP : 8.2.28

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de donnÃ©es : `conci2547642_1m4twb`
--

-- --------------------------------------------------------

--
-- Structure de la table `account_history`
--

CREATE TABLE `account_history` (
  `id` int(11) NOT NULL,
  `coursier_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `type` enum('credit','debit') NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `admins`
--

CREATE TABLE `admins` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- DÃ©chargement des donnÃ©es de la table `admins`
--

INSERT INTO `admins` (`id`, `username`, `email`, `password`, `created_at`) VALUES
(1, 'admin', 'mail@suzosky.com', '$2y$10$BeiMWdID8sZ6PxMGWXAw2uqh0lmEeY.KtV5WECH6PNf3SU.AJi6K6', '2025-08-10 06:02:14');

-- --------------------------------------------------------

--
-- Structure de la table `admin_actions`
--

CREATE TABLE `admin_actions` (
  `id` int(11) NOT NULL,
  `admin_id` varchar(50) NOT NULL,
  `action_type` varchar(50) NOT NULL,
  `target_id` varchar(50) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `date_creation` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- DÃ©chargement des donnÃ©es de la table `admin_actions`
--

INSERT INTO `admin_actions` (`id`, `admin_id`, `action_type`, `target_id`, `description`, `ip_address`, `date_creation`) VALUES
(1, 'SYSTEM', 'new_chat', 'TEST_FINAL_1755782072', 'Nouveau chat support: Test Final depuis Test', '193.203.239.82', '2025-08-21 13:14:32'),
(2, 'SYSTEM', 'new_chat', 'TEST_FINAL_1755782292', 'Nouveau chat support: Test Final depuis Test', '193.203.239.82', '2025-08-21 13:18:12');

-- --------------------------------------------------------

--
-- Structure de la table `admin_audit_unified`
--

CREATE TABLE `admin_audit_unified` (
  `id` int(11) NOT NULL,
  `timestamp` timestamp(6) NULL DEFAULT current_timestamp(6),
  `admin_id` int(11) DEFAULT NULL,
  `admin_username` varchar(50) DEFAULT NULL,
  `action_type` enum('create','read','update','delete','login','logout') NOT NULL,
  `target_table` varchar(50) DEFAULT NULL,
  `target_id` int(11) DEFAULT NULL,
  `changes_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`changes_json`)),
  `interface_source` enum('admin','business','coursier','concierge','recrutement') NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `admin_chat_status`
--

CREATE TABLE `admin_chat_status` (
  `id` int(11) NOT NULL DEFAULT 1,
  `is_online` tinyint(1) DEFAULT 0,
  `last_seen` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- DÃ©chargement des donnÃ©es de la table `admin_chat_status`
--

INSERT INTO `admin_chat_status` (`id`, `is_online`, `last_seen`) VALUES
(1, 0, '2025-08-17 04:23:18');

-- --------------------------------------------------------

--
-- Structure de la table `agents`
--

CREATE TABLE `agents` (
  `id` int(11) NOT NULL,
  `nom` varchar(100) NOT NULL,
  `telephone` varchar(20) NOT NULL,
  `email` varchar(100) NOT NULL,
  `role` enum('agent','superviseur','admin') DEFAULT 'agent',
  `specialite` enum('support_client','support_business','technique','commercial') DEFAULT 'support_client',
  `statut` enum('actif','inactif') DEFAULT 'actif',
  `en_ligne` tinyint(1) DEFAULT 0,
  `password_hash` varchar(255) NOT NULL,
  `derniere_connexion` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- DÃ©chargement des donnÃ©es de la table `agents`
--

INSERT INTO `agents` (`id`, `nom`, `telephone`, `email`, `role`, `specialite`, `statut`, `en_ligne`, `password_hash`, `derniere_connexion`, `created_at`) VALUES
(1, 'Admin SystÃ¨me', '+225 07 00 00 00 00', 'admin@suzosky.com', 'admin', 'support_client', 'actif', 0, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', NULL, '2025-08-08 09:03:00'),
(2, 'Agent Support', '+225 07 00 00 00 01', 'support@suzosky.com', 'agent', 'support_client', 'actif', 0, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', NULL, '2025-08-08 09:03:00');

-- --------------------------------------------------------

--
-- Structure de la table `agents_suzosky`
--

CREATE TABLE `agents_suzosky` (
  `id` int(11) NOT NULL,
  `matricule` varchar(20) NOT NULL,
  `nom` varchar(100) NOT NULL,
  `prenoms` varchar(100) NOT NULL,
  `date_naissance` date NOT NULL,
  `lieu_naissance` varchar(100) NOT NULL,
  `type_poste` enum('chauffeur','coursier_moto','coursier_cargo','agent_conciergerie') NOT NULL,
  `telephone` varchar(20) NOT NULL,
  `email` varchar(100) NOT NULL,
  `piece_identite` enum('cni','passeport') NOT NULL,
  `numero_piece` varchar(50) NOT NULL,
  `contact_urgence_nom` varchar(100) NOT NULL,
  `contact_urgence_tel` varchar(20) NOT NULL,
  `contact_urgence_residence` varchar(200) NOT NULL,
  `status` enum('actif','inactif','suspendu') DEFAULT 'actif',
  `shipday_id` varchar(50) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `password` varchar(255) DEFAULT NULL COMMENT 'Mot de passe hashÃ©',
  `password_plain` varchar(50) DEFAULT NULL COMMENT 'Mot de passe en clair (temporaire)',
  `first_login_done` tinyint(1) DEFAULT 0 COMMENT 'Premier login effectuÃ©',
  `password_changed_at` timestamp NULL DEFAULT NULL COMMENT 'Date dernier changement mot de passe',
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'Date mise Ã  jour',
  `plain_password` varchar(5) NOT NULL DEFAULT '',
  `nationalite` varchar(100) DEFAULT NULL,
  `lieu_residence` varchar(150) DEFAULT NULL,
  `cni` varchar(100) DEFAULT NULL,
  `permis` varchar(100) DEFAULT NULL,
  `urgence_nom` varchar(100) DEFAULT NULL,
  `urgence_prenoms` varchar(100) DEFAULT NULL,
  `urgence_lien` varchar(100) DEFAULT NULL,
  `urgence_lieu_residence` varchar(150) DEFAULT NULL,
  `urgence_telephone` varchar(30) DEFAULT NULL,
  `current_session_token` varchar(100) DEFAULT NULL,
  `last_login_at` datetime DEFAULT NULL,
  `last_login_ip` varchar(64) DEFAULT NULL,
  `last_login_user_agent` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- DÃ©chargement des donnÃ©es de la table `agents_suzosky`
--

INSERT INTO `agents_suzosky` (`id`, `matricule`, `nom`, `prenoms`, `date_naissance`, `lieu_naissance`, `type_poste`, `telephone`, `email`, `piece_identite`, `numero_piece`, `contact_urgence_nom`, `contact_urgence_tel`, `contact_urgence_residence`, `status`, `shipday_id`, `created_at`, `password`, `password_plain`, `first_login_done`, `password_changed_at`, `updated_at`, `plain_password`, `nationalite`, `lieu_residence`, `cni`, `permis`, `urgence_nom`, `urgence_prenoms`, `urgence_lien`, `urgence_lieu_residence`, `urgence_telephone`, `current_session_token`, `last_login_at`, `last_login_ip`, `last_login_user_agent`) VALUES
(3, 'CM20250001', 'YAPO', 'Emmanuel', '1990-01-01', 'abidjan', 'coursier_moto', '0758842029', 'yapadone@gmail.com', 'cni', 'AUTO_20250827094600', 'Ã€ renseigner', '0000000000', 'Ã€ renseigner', 'actif', NULL, '2025-08-27 07:46:00', NULL, NULL, 0, NULL, '2025-08-27 07:46:00', '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(4, 'CM20250002', 'DEMBA', 'IBRAHIM', '1988-12-31', 'BAFOULABE / MALI', 'coursier_moto', '0777036262', 'demba.ibrahim@suzosky.com', 'cni', '18801204016013M', 'Ã€ renseigner', '0000000000', 'Ã€ renseigner', 'actif', NULL, '2025-08-27 09:00:31', '$2y$10$z.BTOQi4IX9ly/.sdoASY..DO/hLqxYBOyO2it5CFPx8YNxejp5Ke', NULL, 0, NULL, '2025-09-15 19:53:42', 'RsJyY', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(5, 'CM20250003', 'ZALLE', 'Ismael', '1997-07-19', 'Marcory', 'coursier_moto', '0566462665', 'ismael.Z@suzosky.com', 'cni', '', '', '', '', 'actif', NULL, '2025-09-20 14:38:14', '$2y$10$CYoCqMy18MWi6KBbe2Y3HO68QTSIhUa5X2JJ46wf55M9yAiX00JlC', NULL, 0, NULL, '2025-09-26 21:56:53', '7xTx3', 'IVOIRIENNE', 'CI', 'CI008093291', '', '', '', '', '', '', 'ad414b2c4d5384205b16655e8d24ccae', '2025-09-26 23:56:53', '102.209.220.59', 'okhttp/4.12.0');

-- --------------------------------------------------------

--
-- Structure de la table `agents_unified`
--

CREATE TABLE `agents_unified` (
  `id` int(11) NOT NULL,
  `matricule` varchar(20) NOT NULL,
  `nom` varchar(100) NOT NULL,
  `prenoms` varchar(100) NOT NULL,
  `telephone` varchar(20) NOT NULL,
  `email` varchar(150) DEFAULT NULL,
  `type_poste` enum('coursier','chauffeur','agent_call_center','superviseur','livreur') NOT NULL,
  `statut` enum('actif','inactif','suspendu','en_attente') DEFAULT 'en_attente',
  `password_hash` varchar(255) NOT NULL,
  `password_plain` varchar(10) NOT NULL COMMENT 'Mot de passe visible pour admin',
  `adresse` text DEFAULT NULL,
  `ville` varchar(50) DEFAULT 'Abidjan',
  `zone_travail` varchar(100) DEFAULT NULL,
  `date_embauche` date NOT NULL,
  `date_creation` timestamp NULL DEFAULT current_timestamp(),
  `date_modification` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` varchar(50) DEFAULT 'system',
  `notes_admin` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `agent_chats`
--

CREATE TABLE `agent_chats` (
  `id` int(11) NOT NULL,
  `chat_id` varchar(255) NOT NULL,
  `agent_matricule` varchar(50) DEFAULT NULL,
  `agent_name` varchar(255) DEFAULT NULL,
  `agent_phone` varchar(50) DEFAULT NULL,
  `agent_type` varchar(100) DEFAULT NULL,
  `device_info` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`device_info`)),
  `is_blocked` tinyint(1) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `last_activity` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `agent_messages`
--

CREATE TABLE `agent_messages` (
  `id` int(11) NOT NULL,
  `chat_id` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `sender_type` enum('agent','admin') NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `app_crashes`
--

CREATE TABLE `app_crashes` (
  `id` int(11) UNSIGNED NOT NULL,
  `device_id` varchar(128) NOT NULL,
  `crash_hash` varchar(64) NOT NULL COMMENT 'Hash unique pour groupement',
  `app_version_code` int(11) NOT NULL,
  `android_version` varchar(20) DEFAULT NULL,
  `device_model` varchar(100) DEFAULT NULL,
  `crash_type` varchar(50) NOT NULL COMMENT 'EXCEPTION, ANR, NATIVE_CRASH',
  `exception_class` varchar(255) DEFAULT NULL,
  `exception_message` text DEFAULT NULL,
  `stack_trace` longtext DEFAULT NULL,
  `screen_name` varchar(100) DEFAULT NULL,
  `user_action` varchar(255) DEFAULT NULL,
  `memory_usage` int(11) DEFAULT NULL COMMENT 'MB',
  `battery_level` int(3) DEFAULT NULL COMMENT '0-100',
  `network_type` varchar(20) DEFAULT NULL,
  `is_resolved` tinyint(1) NOT NULL DEFAULT 0,
  `occurrence_count` int(11) UNSIGNED NOT NULL DEFAULT 1,
  `first_occurred` datetime NOT NULL DEFAULT current_timestamp(),
  `last_occurred` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- DÃ©chargement des donnÃ©es de la table `app_crashes`
--

INSERT INTO `app_crashes` (`id`, `device_id`, `crash_hash`, `app_version_code`, `android_version`, `device_model`, `crash_type`, `exception_class`, `exception_message`, `stack_trace`, `screen_name`, `user_action`, `memory_usage`, `battery_level`, `network_type`, `is_resolved`, `occurrence_count`, `first_occurred`, `last_occurred`, `created_at`) VALUES
(1, 'f54ce76595d20317', 'd16a1c645e62197b21f256c0740911caae7bd247a74661383bdbda6749136c72', 1, '14', 'itel A671L', 'EXCEPTION', 'RuntimeException', 'Unable to create service com.suzosky.coursier.services.AutoUpdateService: java.lang.SecurityException: com.suzosky.coursier: One of RECEIVER_EXPORTED or RECEIVER_NOT_EXPORTED should be specified when a receiver isn\'t being registered exclusively for system broadcasts', 'java.lang.RuntimeException: Unable to create service com.suzosky.coursier.services.AutoUpdateService: java.lang.SecurityException: com.suzosky.coursier: One of RECEIVER_EXPORTED or RECEIVER_NOT_EXPORTED should be specified when a receiver isn\'t being registered exclusively for system broadcasts\n	at android.app.ActivityThread.handleCreateService(ActivityThread.java:4869)\n	at android.app.ActivityThread.-$$Nest$mhandleCreateService(Unknown Source:0)\n	at android.app.ActivityThread$H.handleMessage(ActivityThread.java:2390)\n	at android.os.Handler.dispatchMessage(Handler.java:106)\n	at android.os.Looper.loopOnce(Looper.java:205)\n	at android.os.Looper.loop(Looper.java:294)\n	at android.app.ActivityThread.main(ActivityThread.java:8492)\n	at java.lang.reflect.Method.invoke(Native Method)\n	at com.android.internal.os.RuntimeInit$MethodAndArgsCaller.run(RuntimeInit.java:640)\n	at com.android.internal.os.ZygoteInit.main(ZygoteInit.java:1026)\nCaused by: java.lang.SecurityException: com.suzosky.coursier: One of RECEIVER_EXPORTED or RECEIVER_NOT_EXPORTED should be specified when a receiver isn\'t being registered exclusively for system broadcasts\n	at android.os.Parcel.createExceptionOrNull(Parcel.java:3079)\n	at android.os.Parcel.createException(Parcel.java:3063)\n	at android.os.Parcel.readException(Parcel.java:3046)\n	at android.os.Parcel.readException(Parcel.java:2988)\n	at android.app.IActivityManager$Stub$Proxy.registerReceiverWithFeature(IActivityManager.java:6065)\n	at android.app.ContextImpl.registerReceiverInternal(ContextImpl.java:1955)\n	at android.app.ContextImpl.registerReceiver(ContextImpl.java:1895)\n	at android.app.ContextImpl.registerReceiver(ContextImpl.java:1883)\n	at android.content.ContextWrapper.registerReceiver(ContextWrapper.java:755)\n	at com.suzosky.coursier.services.AutoUpdateService.registerReceiver(AutoUpdateService.kt:155)\n	at com.suzosky.coursier.services.AutoUpdateService.onCreate(AutoUpdateService.kt:85)\n	at android.app.ActivityThread.handleCreateService(ActivityThread.java:4856)\n	... 9 more\nCaused by: android.os.RemoteException: Remote stack trace:\n	at com.android.server.am.ActivityManagerService.registerReceiverWithFeature(ActivityManagerService.java:14612)\n	at android.app.IActivityManager$Stub.onTransact$registerReceiverWithFeature$(IActivityManager.java:11378)\n	at android.app.IActivityManager$Stub.onTransact(IActivityManager.java:2870)\n	at com.android.server.am.ActivityManagerService.onTransact(ActivityManagerService.java:2890)\n	at android.os.Binder.execTransactInternal(Binder.java:1339)\n\n', 'UnknownScreen', 'App crashed unexpectedly', 6, -1, 'Unknown', 0, 16, '2025-09-19 09:03:58', '2025-09-19 12:48:18', '2025-09-19 09:03:58'),
(2, 'b4561b144f259733', 'eb7165312cd898354734cd269ee1621f3690de01e3e02d9f5491fcc294214683', 1, '14', 'itel A671L', 'EXCEPTION', 'RuntimeException', 'Unable to create service com.suzosky.coursier.services.AutoUpdateService: java.lang.SecurityException: com.suzosky.coursier.debug: One of RECEIVER_EXPORTED or RECEIVER_NOT_EXPORTED should be specified when a receiver isn\'t being registered exclusively for system broadcasts', 'java.lang.RuntimeException: Unable to create service com.suzosky.coursier.services.AutoUpdateService: java.lang.SecurityException: com.suzosky.coursier.debug: One of RECEIVER_EXPORTED or RECEIVER_NOT_EXPORTED should be specified when a receiver isn\'t being registered exclusively for system broadcasts\n	at android.app.ActivityThread.handleCreateService(ActivityThread.java:4869)\n	at android.app.ActivityThread.-$$Nest$mhandleCreateService(Unknown Source:0)\n	at android.app.ActivityThread$H.handleMessage(ActivityThread.java:2390)\n	at android.os.Handler.dispatchMessage(Handler.java:106)\n	at android.os.Looper.loopOnce(Looper.java:205)\n	at android.os.Looper.loop(Looper.java:294)\n	at android.app.ActivityThread.main(ActivityThread.java:8492)\n	at java.lang.reflect.Method.invoke(Native Method)\n	at com.android.internal.os.RuntimeInit$MethodAndArgsCaller.run(RuntimeInit.java:640)\n	at com.android.internal.os.ZygoteInit.main(ZygoteInit.java:1026)\nCaused by: java.lang.SecurityException: com.suzosky.coursier.debug: One of RECEIVER_EXPORTED or RECEIVER_NOT_EXPORTED should be specified when a receiver isn\'t being registered exclusively for system broadcasts\n	at android.os.Parcel.createExceptionOrNull(Parcel.java:3079)\n	at android.os.Parcel.createException(Parcel.java:3063)\n	at android.os.Parcel.readException(Parcel.java:3046)\n	at android.os.Parcel.readException(Parcel.java:2988)\n	at android.app.IActivityManager$Stub$Proxy.registerReceiverWithFeature(IActivityManager.java:6065)\n	at android.app.ContextImpl.registerReceiverInternal(ContextImpl.java:1955)\n	at android.app.ContextImpl.registerReceiver(ContextImpl.java:1895)\n	at android.app.ContextImpl.registerReceiver(ContextImpl.java:1883)\n	at android.content.ContextWrapper.registerReceiver(ContextWrapper.java:755)\n	at com.suzosky.coursier.services.AutoUpdateService.registerReceiver(AutoUpdateService.kt:188)\n	at com.suzosky.coursier.services.AutoUpdateService.onCreate(AutoUpdateService.kt:108)\n	at android.app.ActivityThread.handleCreateService(ActivityThread.java:4856)\n	... 9 more\n', 'UnknownScreen', 'App crashed unexpectedly', 9, -1, 'Unknown', 0, 1, '2025-09-19 12:52:33', '2025-09-19 12:52:33', '2025-09-19 12:52:33');

-- --------------------------------------------------------

--
-- Structure de la table `app_devices`
--

CREATE TABLE `app_devices` (
  `id` int(11) UNSIGNED NOT NULL,
  `device_id` varchar(128) NOT NULL COMMENT 'Android ID ou UUID gÃ©nÃ©rÃ©',
  `courier_id` int(11) UNSIGNED DEFAULT NULL COMMENT 'ID du coursier si connectÃ©',
  `device_model` varchar(100) DEFAULT NULL COMMENT 'ModÃ¨le appareil',
  `device_brand` varchar(50) DEFAULT NULL COMMENT 'Marque appareil',
  `android_version` varchar(20) DEFAULT NULL COMMENT 'Version Android',
  `app_version_code` int(11) NOT NULL DEFAULT 1 COMMENT 'Code version app',
  `app_version_name` varchar(20) NOT NULL DEFAULT '1.0' COMMENT 'Nom version',
  `first_install` datetime NOT NULL DEFAULT current_timestamp(),
  `last_seen` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `total_sessions` int(11) UNSIGNED NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- DÃ©chargement des donnÃ©es de la table `app_devices`
--

INSERT INTO `app_devices` (`id`, `device_id`, `courier_id`, `device_model`, `device_brand`, `android_version`, `app_version_code`, `app_version_name`, `first_install`, `last_seen`, `total_sessions`, `is_active`, `created_at`, `updated_at`) VALUES
(1, '6b08cf30e80c3633', NULL, 'itel A665L', 'Itel', '13', 1, '1.0', '2025-09-19 08:54:43', '2025-09-19 08:58:13', 0, 1, '2025-09-19 08:54:43', '2025-09-19 08:58:13'),
(2, 'f54ce76595d20317', NULL, 'itel A671L', 'Itel', '14', 1, '1.0', '2025-09-19 09:03:58', '2025-09-19 12:48:16', 0, 1, '2025-09-19 09:03:58', '2025-09-19 12:48:16'),
(7, 'd4ec77bc47845f42', NULL, 'itel A665L', 'Itel', '13', 1, '1.0-debug', '2025-09-19 09:39:30', '2025-09-19 12:28:30', 0, 1, '2025-09-19 09:39:30', '2025-09-19 12:28:30'),
(19, 'ad81f63c58d9a7e2', NULL, 'itel A665L', 'Itel', '13', 1, '1.0', '2025-09-19 12:13:08', '2025-09-19 12:13:08', 0, 1, '2025-09-19 12:13:08', '2025-09-19 12:13:08'),
(24, 'b4561b144f259733', NULL, 'itel A671L', 'Itel', '14', 1, '1.0-debug', '2025-09-19 12:52:20', '2025-09-19 13:24:56', 0, 1, '2025-09-19 12:52:20', '2025-09-19 13:24:56');

-- --------------------------------------------------------

--
-- Structure de la table `app_events`
--

CREATE TABLE `app_events` (
  `id` int(11) UNSIGNED NOT NULL,
  `device_id` varchar(128) NOT NULL,
  `event_type` varchar(50) NOT NULL COMMENT 'APP_START, SCREEN_VIEW, FEATURE_USE',
  `event_name` varchar(100) NOT NULL,
  `screen_name` varchar(100) DEFAULT NULL,
  `event_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`event_data`)),
  `session_id` varchar(64) DEFAULT NULL,
  `occurred_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `app_notifications`
--

CREATE TABLE `app_notifications` (
  `id` int(11) UNSIGNED NOT NULL,
  `device_id` varchar(128) DEFAULT NULL COMMENT 'NULL = broadcast',
  `notification_type` varchar(50) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `action_url` varchar(500) DEFAULT NULL,
  `priority` enum('LOW','NORMAL','HIGH','URGENT') NOT NULL DEFAULT 'NORMAL',
  `sent_at` datetime DEFAULT NULL,
  `delivered_at` datetime DEFAULT NULL,
  `clicked_at` datetime DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `app_sessions`
--

CREATE TABLE `app_sessions` (
  `id` int(11) UNSIGNED NOT NULL,
  `device_id` varchar(128) NOT NULL,
  `session_id` varchar(64) NOT NULL COMMENT 'UUID unique',
  `started_at` datetime NOT NULL DEFAULT current_timestamp(),
  `ended_at` datetime DEFAULT NULL,
  `duration_seconds` int(11) UNSIGNED DEFAULT NULL,
  `screens_visited` int(11) UNSIGNED NOT NULL DEFAULT 0,
  `actions_performed` int(11) UNSIGNED NOT NULL DEFAULT 0,
  `crashed` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- DÃ©chargement des donnÃ©es de la table `app_sessions`
--

INSERT INTO `app_sessions` (`id`, `device_id`, `session_id`, `started_at`, `ended_at`, `duration_seconds`, `screens_visited`, `actions_performed`, `crashed`) VALUES
(1, '6b08cf30e80c3633', 'sess_c07aee70-93c0-4b2c-8a70-b5f7d93296bd', '2025-09-19 08:54:43', '2025-09-19 08:55:27', 44, 0, 0, 0),
(2, '6b08cf30e80c3633', 'sess_065cd161-1409-4b53-8587-74901ea811fc', '2025-09-19 08:58:06', '2025-09-19 08:58:08', 2, 0, 0, 0),
(3, '6b08cf30e80c3633', 'sess_cc3f663d-b9e0-4861-b88d-e849a63ba21f', '2025-09-19 08:58:14', '2025-09-19 08:58:23', 9, 0, 0, 0),
(4, 'f54ce76595d20317', 'sess_0eed7bb4-cf86-448d-be93-551d562eb2c4', '2025-09-19 09:03:58', '2025-09-19 09:03:58', 0, 0, 0, 1),
(5, 'f54ce76595d20317', 'sess_1c95e5fe-7b15-409e-bf31-1745039067f0', '2025-09-19 09:04:36', '2025-09-19 09:04:36', 0, 0, 0, 1),
(6, 'f54ce76595d20317', 'sess_13930965-8677-448d-87ed-119fb1b31710', '2025-09-19 09:04:42', '2025-09-19 09:04:42', 0, 0, 0, 1),
(7, 'f54ce76595d20317', 'sess_c341c1cf-bdba-4288-aa07-aa444d635a34', '2025-09-19 09:04:49', '2025-09-19 09:04:50', 1, 0, 0, 1),
(8, 'f54ce76595d20317', 'sess_7ba79567-cbc4-46d3-9b74-aa599cd54ef5', '2025-09-19 09:04:57', '2025-09-19 09:04:57', 0, 0, 0, 1),
(9, 'd4ec77bc47845f42', 'sess_7cc913ea-40e5-4232-b2b8-25782faad980', '2025-09-19 09:39:30', '2025-09-19 09:49:05', 575, 0, 0, 0),
(10, 'd4ec77bc47845f42', 'sess_3e761cf5-379d-49bb-8be6-16e216096266', '2025-09-19 09:49:05', '2025-09-19 09:49:09', 4, 0, 0, 0),
(11, 'd4ec77bc47845f42', 'sess_f0fe92da-419e-455b-a15f-d016652f3eea', '2025-09-19 09:49:18', '2025-09-19 09:49:59', 41, 0, 0, 0),
(12, 'd4ec77bc47845f42', 'sess_9a8a4e93-11e3-43e7-a3a3-1a60c9703415', '2025-09-19 09:50:28', '2025-09-19 09:50:59', 31, 0, 0, 0),
(13, 'd4ec77bc47845f42', 'sess_6fe2a533-154d-49ec-9265-5ae2b9c90009', '2025-09-19 09:51:00', '2025-09-19 10:03:00', 720, 0, 0, 0),
(14, 'd4ec77bc47845f42', 'sess_aa6d73ed-c378-406f-af67-c62ac76ee7a3', '2025-09-19 10:03:02', '2025-09-19 10:04:07', 65, 0, 0, 0),
(15, 'd4ec77bc47845f42', 'sess_9fe34590-1e72-49b1-a535-9bf16c5aebcc', '2025-09-19 10:05:18', '2025-09-19 10:05:20', 2, 0, 0, 0),
(16, 'd4ec77bc47845f42', 'sess_40e2ac4d-7ce3-4420-979d-af989d31dd57', '2025-09-19 10:05:36', '2025-09-19 10:06:44', 68, 0, 0, 0),
(17, 'd4ec77bc47845f42', 'sess_c209a362-6a82-44da-88e8-d154d2c4aebb', '2025-09-19 10:06:54', NULL, NULL, 0, 0, 0),
(18, 'd4ec77bc47845f42', 'sess_decd4396-4909-4722-86f2-2382b6ccd621', '2025-09-19 10:07:19', '2025-09-19 10:08:59', 100, 0, 0, 0),
(19, 'f54ce76595d20317', 'sess_5a9d5f2e-6298-40e8-b892-339884ad0b9b', '2025-09-19 10:50:10', '2025-09-19 10:50:14', 4, 0, 0, 1),
(20, 'f54ce76595d20317', 'sess_565c0238-4a91-4a56-a4cd-e11159020e4c', '2025-09-19 10:50:27', '2025-09-19 10:50:27', 0, 0, 0, 1),
(21, 'f54ce76595d20317', 'sess_7e5bb800-9462-4e8e-9cbb-4ed14d8a2338', '2025-09-19 10:51:18', '2025-09-19 10:51:18', 0, 0, 0, 1),
(22, 'f54ce76595d20317', 'sess_377b2bc1-9b97-4d69-a4c5-766a3357ab97', '2025-09-19 10:52:04', '2025-09-19 10:52:05', 1, 0, 0, 1),
(23, 'd4ec77bc47845f42', 'sess_2c875103-64a5-41d3-b132-2848ba346563', '2025-09-19 11:09:16', '2025-09-19 11:10:01', 45, 0, 0, 0),
(24, 'f54ce76595d20317', 'sess_b767a9be-690b-4cb1-93bb-fdd31dfcfcf5', '2025-09-19 11:29:09', '2025-09-19 11:29:09', 0, 0, 0, 1),
(25, 'd4ec77bc47845f42', 'sess_e851115a-a4cc-4f7b-99aa-f8c0e9554d16', '2025-09-19 11:31:16', '2025-09-19 11:32:27', 71, 0, 0, 0),
(26, 'f54ce76595d20317', 'sess_6e2e56db-fe07-486d-9d99-dcc5f0d84790', '2025-09-19 11:42:46', '2025-09-19 11:42:54', 8, 0, 0, 1),
(27, 'd4ec77bc47845f42', 'sess_b620b1dc-5e76-4743-9347-06b4c94ec87f', '2025-09-19 11:47:22', '2025-09-19 11:47:24', 2, 0, 0, 0),
(28, 'f54ce76595d20317', 'sess_4e25ef20-52b5-4cc6-9c32-9a4245cabc2c', '2025-09-19 12:03:29', '2025-09-19 12:03:34', 5, 0, 0, 1),
(29, 'f54ce76595d20317', 'sess_74b89f67-a5db-43ac-aaa7-01dd60447f3b', '2025-09-19 12:05:16', '2025-09-19 12:05:17', 1, 0, 0, 1),
(30, 'ad81f63c58d9a7e2', 'sess_3215f3c8-f367-4edc-a055-5bc07b160c43', '2025-09-19 12:13:08', '2025-09-19 12:14:04', 56, 0, 0, 0),
(31, 'f54ce76595d20317', 'sess_3a4dddd3-bce4-4435-ac83-0d311fbcb57b', '2025-09-19 12:18:28', '2025-09-19 12:18:28', 0, 0, 0, 1),
(32, 'd4ec77bc47845f42', 'sess_b5386128-ac6a-4fff-81f4-cf1a963d0f7e', '2025-09-19 12:28:30', '2025-09-19 12:30:06', 96, 0, 0, 0),
(33, 'd4ec77bc47845f42', 'sess_7d423955-7eab-4e51-aab4-05e6bd6b83db', '2025-09-19 12:42:38', '2025-09-19 12:42:45', 7, 0, 0, 0),
(34, 'f54ce76595d20317', 'sess_314763bd-37c9-49e2-9957-26e656279ee8', '2025-09-19 12:48:00', '2025-09-19 12:48:02', 2, 0, 0, 1),
(35, 'f54ce76595d20317', 'sess_3bb9ecbe-3301-41c9-85ee-08c0587faaa1', '2025-09-19 12:48:16', '2025-09-19 12:48:18', 2, 0, 0, 1),
(36, 'ad81f63c58d9a7e2', 'sess_767818f1-2516-44d0-a75d-f2bcb049f953', '2025-09-19 12:51:57', '2025-09-19 12:51:57', 0, 0, 0, 0),
(37, 'b4561b144f259733', 'sess_86e719c0-c73e-4ce3-aa81-302fb778bb67', '2025-09-19 12:52:20', '2025-09-19 12:52:33', 13, 0, 0, 1),
(38, 'b4561b144f259733', 'sess_402508ba-f166-48c7-b310-440013b680fd', '2025-09-19 13:24:55', '2025-09-19 13:25:46', 51, 0, 0, 0),
(39, 'b4561b144f259733', 'sess_3b97cf37-65a3-4f16-a08f-b99d0965ce10', '2025-09-19 13:39:06', '2025-09-19 13:39:52', 46, 0, 0, 0);

-- --------------------------------------------------------

--
-- Structure de la table `app_versions`
--

CREATE TABLE `app_versions` (
  `id` int(11) UNSIGNED NOT NULL,
  `version_code` int(11) NOT NULL COMMENT 'Code version numÃ©rique',
  `version_name` varchar(20) NOT NULL COMMENT 'Version lisible',
  `apk_filename` varchar(255) NOT NULL COMMENT 'Nom fichier APK',
  `apk_size` bigint(20) UNSIGNED NOT NULL COMMENT 'Taille en bytes',
  `min_android_version` int(11) NOT NULL DEFAULT 24,
  `release_notes` text DEFAULT NULL COMMENT 'Notes de version',
  `is_mandatory` tinyint(1) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `uploaded_at` datetime NOT NULL DEFAULT current_timestamp(),
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- DÃ©chargement des donnÃ©es de la table `app_versions`
--

INSERT INTO `app_versions` (`id`, `version_code`, `version_name`, `apk_filename`, `apk_size`, `min_android_version`, `release_notes`, `is_mandatory`, `is_active`, `uploaded_at`, `created_at`) VALUES
(1, 1, '1.0', 'suzosky-coursier-production.apk', 18639158, 24, 'Version initiale avec tÃ©lÃ©mÃ©trie', 0, 0, '2025-09-18 21:59:54', '2025-09-18 21:59:54'),
(2, 2, '1.1', 'suzosky-coursier-20250918-220821.apk', 18639158, 24, 'Upload automatique du 18/09/2025 Ã  22:08', 0, 0, '2025-09-18 22:08:21', '2025-09-18 22:08:21'),
(3, 3, '1.2', 'suzosky-coursier-20250918-230925.apk', 18639158, 24, 'Upload automatique du 18/09/2025 Ã  23:09', 0, 0, '2025-09-18 23:09:25', '2025-09-18 23:09:25'),
(4, 4, '1.3', 'suzosky-coursier-20250918-231936.apk', 18639158, 24, 'Upload automatique du 18/09/2025 Ã  23:19', 0, 0, '2025-09-18 23:19:36', '2025-09-18 23:19:36'),
(5, 5, '1.4', 'suzosky-coursier-20250918-232702.apk', 18639158, 24, 'Upload automatique du 18/09/2025 Ã  23:27', 0, 0, '2025-09-18 23:27:02', '2025-09-18 23:27:02'),
(6, 6, '1.5', 'suzosky-coursier-20250919-085702.apk', 18639190, 24, 'Upload automatique du 19/09/2025 Ã  08:57', 0, 0, '2025-09-19 08:57:02', '2025-09-19 08:57:02'),
(7, 7, '1.6', 'suzosky-coursier-20250919-101127.apk', 18639282, 24, 'Upload automatique du 19/09/2025 Ã  10:11', 0, 0, '2025-09-19 10:11:27', '2025-09-19 10:11:27'),
(8, 8, '1.7', 'suzosky-coursier-20250919-101743.apk', 25840207, 24, 'Upload automatique du 19/09/2025 Ã  10:17', 0, 0, '2025-09-19 10:17:43', '2025-09-19 10:17:43'),
(9, 9, '1.8', 'suzosky-coursier-20250919-113906.apk', 18774940, 24, 'Upload automatique du 19/09/2025 Ã  11:39', 0, 0, '2025-09-19 11:39:06', '2025-09-19 11:39:06'),
(10, 10, '1.9', 'suzosky-coursier-20250919-120138.apk', 18775216, 24, 'Upload automatique du 19/09/2025 Ã  12:01', 0, 0, '2025-09-19 12:01:38', '2025-09-19 12:01:38'),
(11, 11, '1.10', 'suzosky-coursier-20250919-124539.apk', 18776236, 24, 'Upload automatique du 19/09/2025 Ã  12:45', 0, 0, '2025-09-19 12:45:39', '2025-09-19 12:45:39'),
(12, 12, '1.11', 'suzosky-coursier-20250919-190451.apk', 18776154, 24, 'Upload automatique du 19/09/2025 Ã  19:04', 0, 0, '2025-09-19 19:04:51', '2025-09-19 19:04:51'),
(13, 13, '1.12', 'suzosky-coursier-20250919-190621.apk', 18776154, 24, 'Upload automatique du 19/09/2025 Ã  19:06', 0, 0, '2025-09-19 19:06:21', '2025-09-19 19:06:21'),
(14, 14, '1.13', 'suzosky-coursier-20250919-191553.apk', 18776154, 24, 'Upload automatique du 19/09/2025 Ã  19:15', 0, 0, '2025-09-19 19:15:53', '2025-09-19 19:15:53'),
(16, 15, '1.14', 'suzosky-coursier-20250921-084416.apk', 19466689, 24, 'Upload automatique du 21/09/2025 Ã  08:44', 0, 1, '2025-09-21 08:44:17', '2025-09-21 08:44:17');

-- --------------------------------------------------------

--
-- Structure de la table `bonus_penalites`
--

CREATE TABLE `bonus_penalites` (
  `id` int(11) NOT NULL,
  `coursier_id` int(11) NOT NULL,
  `montant` decimal(8,2) NOT NULL,
  `type` enum('bonus','penalite') NOT NULL,
  `categorie` enum('performance','rapidite','satisfaction','volume','retard','annulation','note_faible','manuel') NOT NULL,
  `description` text DEFAULT NULL,
  `commande_id` int(11) DEFAULT NULL,
  `periode_reference` varchar(20) DEFAULT NULL,
  `applique` tinyint(1) DEFAULT 0,
  `approuve_par` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `business_clients`
--

CREATE TABLE `business_clients` (
  `id` int(11) NOT NULL,
  `id_business` int(11) DEFAULT NULL,
  `nom_entreprise` varchar(200) NOT NULL,
  `contact_nom` varchar(100) NOT NULL,
  `contact_email` varchar(150) NOT NULL,
  `contact_telephone` varchar(20) DEFAULT NULL,
  `secteur_activite` varchar(100) DEFAULT NULL,
  `adresse` text DEFAULT NULL,
  `date_creation` timestamp NULL DEFAULT current_timestamp(),
  `date_upload` timestamp NULL DEFAULT NULL,
  `statut` enum('actif','inactif','en_attente') DEFAULT 'actif'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `candidatures`
--

CREATE TABLE `candidatures` (
  `id` int(11) NOT NULL,
  `poste_id` int(11) DEFAULT NULL,
  `offre_id` int(11) NOT NULL,
  `nom` varchar(100) NOT NULL,
  `prenom` varchar(100) NOT NULL,
  `telephone` varchar(20) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `cv_url` varchar(255) DEFAULT NULL,
  `lettre_motivation` text DEFAULT NULL,
  `statut` enum('nouvelle','en_cours','acceptee','refusee','archivee') DEFAULT 'nouvelle',
  `notes_rh` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `candidatures_suzosky`
--

CREATE TABLE `candidatures_suzosky` (
  `id` int(11) NOT NULL,
  `nom` varchar(100) NOT NULL,
  `prenoms` varchar(100) NOT NULL,
  `telephone` varchar(20) NOT NULL,
  `email` varchar(100) NOT NULL,
  `poste_souhaite` enum('chauffeur','coursier_moto','coursier_cargo','agent_conciergerie') NOT NULL,
  `experience` text DEFAULT NULL,
  `cv_path` varchar(255) DEFAULT NULL,
  `status` enum('en_attente','accepte','refuse') DEFAULT 'en_attente',
  `date_candidature` timestamp NULL DEFAULT current_timestamp(),
  `date_traitement` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- DÃ©chargement des donnÃ©es de la table `candidatures_suzosky`
--

INSERT INTO `candidatures_suzosky` (`id`, `nom`, `prenoms`, `telephone`, `email`, `poste_souhaite`, `experience`, `cv_path`, `status`, `date_candidature`, `date_traitement`) VALUES
(1, 'KOUASSI', 'Jean Baptiste', '+225 07 12 34 56 78', 'kouassi.jean@email.com', 'coursier_moto', 'ExpÃ©rience de 3 ans en livraison', NULL, 'en_attente', '2025-08-10 06:08:34', NULL),
(2, 'TRAORE', 'Aminata', '+225 05 98 76 54 32', 'traore.aminata@email.com', 'agent_conciergerie', 'Formation en service clientÃ¨le', NULL, 'en_attente', '2025-08-10 06:08:34', NULL),
(3, 'DIABATE', 'Seydou', '+225 01 11 22 33 44', 'diabate.seydou@email.com', 'chauffeur', '5 ans d\'expÃ©rience en transport', NULL, 'en_attente', '2025-08-10 06:08:34', NULL);

-- --------------------------------------------------------

--
-- Structure de la table `chat_admins`
--

CREATE TABLE `chat_admins` (
  `id` int(11) NOT NULL,
  `admin_id` int(11) NOT NULL,
  `display_name` varchar(100) NOT NULL,
  `status` enum('online','offline','busy','away') DEFAULT 'offline',
  `max_concurrent_chats` int(11) DEFAULT 5,
  `current_active_chats` int(11) DEFAULT 0,
  `auto_assign` tinyint(1) DEFAULT 1,
  `last_activity` timestamp NULL DEFAULT current_timestamp(),
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `chat_blocked_devices`
--

CREATE TABLE `chat_blocked_devices` (
  `id` int(11) NOT NULL,
  `device_identifier` varchar(100) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `blocked_reason` text NOT NULL,
  `blocked_by` int(11) NOT NULL,
  `blocked_at` timestamp NULL DEFAULT current_timestamp(),
  `is_permanent` tinyint(1) DEFAULT 0,
  `unblocked_at` timestamp NULL DEFAULT NULL,
  `unblocked_by` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `chat_conversations`
--

CREATE TABLE `chat_conversations` (
  `id` int(11) NOT NULL,
  `type` varchar(20) NOT NULL DEFAULT 'particulier',
  `client_id` int(11) NOT NULL,
  `last_message` text DEFAULT '',
  `last_timestamp` datetime DEFAULT current_timestamp(),
  `unread_count` int(11) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- DÃ©chargement des donnÃ©es de la table `chat_conversations`
--

INSERT INTO `chat_conversations` (`id`, `type`, `client_id`, `last_message`, `last_timestamp`, `unread_count`, `created_at`) VALUES
(1, 'particulier', 99999, 'Test de crÃ©ation', '2025-09-19 07:45:28', 1, '2025-09-19 07:45:28'),
(2, 'particulier', 99999, 'Test de crÃ©ation', '2025-09-19 07:45:37', 1, '2025-09-19 07:45:37'),
(3, 'particulier', 99999, 'Test de crÃ©ation', '2025-09-19 07:46:44', 1, '2025-09-19 07:46:44'),
(4, 'particulier', 258620292, 'cc', '2025-09-19 08:01:49', 3, '2025-09-19 07:50:21'),
(5, 'particulier', 271552282, '', '2025-09-19 10:46:06', 0, '2025-09-19 10:46:06'),
(6, 'particulier', 271552724, '', '2025-09-19 10:46:06', 0, '2025-09-19 10:46:06'),
(7, 'particulier', 272518716, '', '2025-09-19 11:01:59', 0, '2025-09-19 11:01:59'),
(8, 'particulier', 280713896, '', '2025-09-19 13:18:34', 0, '2025-09-19 13:18:34'),
(9, 'particulier', 280716474, '', '2025-09-19 13:18:39', 0, '2025-09-19 13:18:39'),
(10, 'particulier', 280716464, '', '2025-09-19 13:18:39', 0, '2025-09-19 13:18:39'),
(11, 'particulier', 320933054, '', '2025-09-20 00:28:53', 0, '2025-09-20 00:28:53'),
(12, 'particulier', 412800010, '', '2025-09-21 02:34:50', 0, '2025-09-21 02:34:50'),
(13, 'particulier', 438093905, '', '2025-09-21 09:01:34', 0, '2025-09-21 09:01:34'),
(14, 'particulier', 442577689, '', '2025-09-21 10:16:17', 0, '2025-09-21 10:16:17'),
(15, 'particulier', 493869946, '', '2025-09-22 00:31:10', 0, '2025-09-22 00:31:10'),
(16, 'particulier', 504288141, '', '2025-09-22 03:24:48', 0, '2025-09-22 03:24:48'),
(17, 'particulier', 521998285, '', '2025-09-22 08:19:58', 0, '2025-09-22 08:19:58'),
(18, 'particulier', 529970095, '', '2025-09-22 10:32:50', 0, '2025-09-22 10:32:50'),
(19, 'particulier', 99999, 'Test de crÃ©ation', '2025-09-22 17:28:02', 1, '2025-09-22 17:28:02'),
(20, 'particulier', 499200010, '', '2025-09-22 20:09:59', 0, '2025-09-22 20:09:59'),
(21, 'particulier', 579030524, '', '2025-09-23 00:10:50', 0, '2025-09-23 00:10:50'),
(22, 'particulier', 579031681, '', '2025-09-23 00:10:50', 0, '2025-09-23 00:10:50'),
(23, 'particulier', 580000425, '', '2025-09-23 00:26:42', 0, '2025-09-23 00:26:42'),
(24, 'particulier', 580002769, '', '2025-09-23 00:26:43', 0, '2025-09-23 00:26:43'),
(25, 'particulier', 617140311, '', '2025-09-23 10:45:56', 0, '2025-09-23 10:45:56'),
(26, 'particulier', 617140332, '', '2025-09-23 10:45:56', 0, '2025-09-23 10:45:56'),
(27, 'particulier', 618107304, '', '2025-09-23 11:01:48', 0, '2025-09-23 11:01:48'),
(28, 'particulier', 728263274, '', '2025-09-24 17:37:44', 0, '2025-09-24 17:37:44'),
(29, 'particulier', 774293298, '', '2025-09-25 06:24:53', 0, '2025-09-25 06:24:53'),
(30, 'particulier', 785749861, '', '2025-09-25 09:35:50', 0, '2025-09-25 09:35:50'),
(31, 'particulier', 836617442, '', '2025-09-25 23:43:39', 0, '2025-09-25 23:43:39'),
(32, 'particulier', 852702257, '', '2025-09-26 04:11:42', 0, '2025-09-26 04:11:42'),
(33, 'particulier', 852702467, '', '2025-09-26 04:11:42', 0, '2025-09-26 04:11:42'),
(34, 'particulier', 866179711, '', '2025-09-26 07:56:21', 0, '2025-09-26 07:56:20'),
(35, 'particulier', 866551896, '', '2025-09-26 08:02:33', 0, '2025-09-26 08:02:33'),
(36, 'particulier', 927338915, '', '2025-09-27 00:55:40', 0, '2025-09-27 00:55:40');

-- --------------------------------------------------------

--
-- Structure de la table `chat_messages`
--

CREATE TABLE `chat_messages` (
  `id` int(11) NOT NULL,
  `conversation_id` int(11) NOT NULL,
  `sender_type` varchar(20) NOT NULL DEFAULT 'client',
  `sender_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `timestamp` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- DÃ©chargement des donnÃ©es de la table `chat_messages`
--

INSERT INTO `chat_messages` (`id`, `conversation_id`, `sender_type`, `sender_id`, `message`, `timestamp`) VALUES
(1, 19, 'client', 99999, 'Message de test automatique', '2025-09-22 17:28:02');

-- --------------------------------------------------------

--
-- Structure de la table `chat_messages_suzosky`
--

CREATE TABLE `chat_messages_suzosky` (
  `id` int(11) NOT NULL,
  `session_id` varchar(100) NOT NULL,
  `message` text NOT NULL,
  `sender` enum('client','admin') NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `user_type` enum('client','admin') DEFAULT 'client'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- DÃ©chargement des donnÃ©es de la table `chat_messages_suzosky`
--

INSERT INTO `chat_messages_suzosky` (`id`, `session_id`, `message`, `sender`, `is_read`, `created_at`, `user_type`) VALUES
(1, 'chat_1754808320159_0eepnjw0r', 'cc', 'client', 0, '2025-08-10 06:45:46', 'client'),
(3, 'b1631b4733d98b0387472344ae0f9365', 'cc', 'client', 0, '2025-08-12 08:32:09', 'client'),
(4, 'b1631b4733d98b0387472344ae0f9365', 'cc', 'client', 0, '2025-08-12 08:32:40', 'client'),
(5, '3807e96954c8fb92f03c8d236718240f', 'cc', 'client', 0, '2025-08-12 08:32:55', 'client'),
(6, 'b1631b4733d98b0387472344ae0f9365', 'cc', 'client', 0, '2025-08-12 08:38:51', 'client'),
(7, 'b1631b4733d98b0387472344ae0f9365', 'cc', 'client', 0, '2025-08-12 08:44:34', 'client'),
(8, 'b1631b4733d98b0387472344ae0f9365', 'cc', 'client', 0, '2025-08-12 08:50:22', 'client'),
(9, 'b1631b4733d98b0387472344ae0f9365', 'cc', 'client', 0, '2025-08-12 09:15:09', 'client'),
(10, 'b1631b4733d98b0387472344ae0f9365', 'cc', 'client', 0, '2025-08-12 09:17:13', 'client'),
(11, '268dec301b4c18a20e41a1a563413625', 'TEST URGENCE ADMIN - 09:26:19', 'client', 0, '2025-08-12 09:26:20', 'client'),
(12, '05d496f613ad391a05752fc9dde82220', 'TEST URGENCE ADMIN - 09:26:21', 'client', 0, '2025-08-12 09:26:21', 'client'),
(13, '79bf8f9ac3383b2468f502d7e3013136', 'TEST URGENCE ADMIN - 09:26:25', 'client', 0, '2025-08-12 09:26:25', 'client'),
(14, '3338d1e7e321dcc36488a7628aaeca66', 'TEST URGENCE ADMIN - 09:26:29', 'client', 0, '2025-08-12 09:26:29', 'client'),
(15, 'b1631b4733d98b0387472344ae0f9365', 'cc', 'client', 0, '2025-08-12 09:40:55', 'client'),
(16, 'ef8ee03c681c5668764960ae0c8eb89c', 'Cc', 'client', 0, '2025-08-12 10:02:14', 'client'),
(17, 'b1631b4733d98b0387472344ae0f9365', 'cc', 'client', 0, '2025-08-12 10:18:34', 'client'),
(18, 'b1631b4733d98b0387472344ae0f9365', 'cc', 'client', 0, '2025-08-12 10:23:30', 'client'),
(19, 'b1631b4733d98b0387472344ae0f9365', 'cc', 'client', 0, '2025-08-12 12:41:48', 'client'),
(20, '436b999f6494e993c6aea80eb8a5534e', 'TEST URGENCE ADMIN - 12:55:33', 'client', 0, '2025-08-12 12:55:34', 'client');

-- --------------------------------------------------------

--
-- Structure de la table `chat_notifications`
--

CREATE TABLE `chat_notifications` (
  `id` int(11) NOT NULL,
  `session_id` varchar(100) NOT NULL,
  `message_preview` varchar(100) NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- DÃ©chargement des donnÃ©es de la table `chat_notifications`
--

INSERT INTO `chat_notifications` (`id`, `session_id`, `message_preview`, `is_read`, `created_at`, `updated_at`) VALUES
(1, 'b1631b4733d98b0387472344ae0f9365', 'cc', 0, '2025-08-12 08:44:34', '2025-08-12 12:41:48'),
(3, '3807e96954c8fb92f03c8d236718240f', 'cc', 0, '2025-08-12 08:32:55', '2025-08-12 08:32:55'),
(9, '268dec301b4c18a20e41a1a563413625', 'TEST URGENCE ADMIN - 09:26:19', 0, '2025-08-12 09:26:20', '2025-08-12 09:26:20'),
(10, '05d496f613ad391a05752fc9dde82220', 'TEST URGENCE ADMIN - 09:26:21', 0, '2025-08-12 09:26:21', '2025-08-12 09:26:21'),
(11, '79bf8f9ac3383b2468f502d7e3013136', 'TEST URGENCE ADMIN - 09:26:25', 0, '2025-08-12 09:26:25', '2025-08-12 09:26:25'),
(12, '3338d1e7e321dcc36488a7628aaeca66', 'TEST URGENCE ADMIN - 09:26:29', 0, '2025-08-12 09:26:29', '2025-08-12 09:26:29'),
(14, 'ef8ee03c681c5668764960ae0c8eb89c', 'Cc', 0, '2025-08-12 10:02:14', '2025-08-12 10:02:14'),
(18, '436b999f6494e993c6aea80eb8a5534e', 'TEST URGENCE ADMIN - 12:55:33', 0, '2025-08-12 12:55:34', '2025-08-12 12:55:34');

-- --------------------------------------------------------

--
-- Structure de la table `chat_quick_replies`
--

CREATE TABLE `chat_quick_replies` (
  `id` int(11) NOT NULL,
  `title` varchar(100) NOT NULL,
  `message` text NOT NULL,
  `category` enum('greeting','closing','info','escalation','custom') DEFAULT 'custom',
  `is_active` tinyint(1) DEFAULT 1,
  `usage_count` int(11) DEFAULT 0,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- DÃ©chargement des donnÃ©es de la table `chat_quick_replies`
--

INSERT INTO `chat_quick_replies` (`id`, `title`, `message`, `category`, `is_active`, `usage_count`, `created_by`, `created_at`) VALUES
(1, 'Salutation', 'Bonjour ! Bienvenue sur le chat SUZOSKY. Comment puis-je vous aider aujourd\'hui ?', 'greeting', 1, 0, 1, '2025-08-11 04:18:40'),
(2, 'Merci et fermeture', 'Merci pour votre confiance en SUZOSKY ! N\'hÃ©sitez pas Ã  nous recontacter si vous avez d\'autres questions.', 'closing', 1, 0, 1, '2025-08-11 04:18:40'),
(3, 'Informations gÃ©nÃ©rales', 'Pour plus d\'informations sur nos services, je vous invite Ã  consulter notre site web ou Ã  nous appeler directement.', 'info', 1, 0, 1, '2025-08-11 04:18:40'),
(4, 'Prise de contact', 'Je prends note de votre demande. Un de nos conseillers va vous recontacter dans les plus brefs dÃ©lais.', 'escalation', 1, 0, 1, '2025-08-11 04:18:40'),
(5, 'DÃ©lai de livraison', 'Nos dÃ©lais de livraison standard sont de 30 minutes Ã  2h selon la zone gÃ©ographique et le type de service demandÃ©.', 'info', 1, 0, 1, '2025-08-11 04:18:40'),
(6, 'Tarification', 'Nos tarifs varient selon la distance et le type de service. Souhaitez-vous que je vous fasse un devis personnalisÃ© ?', 'info', 1, 0, 1, '2025-08-11 04:18:40'),
(7, 'Salutation', 'Bonjour ! Bienvenue sur le chat SUZOSKY. Comment puis-je vous aider aujourd\'hui ?', 'greeting', 1, 0, 1, '2025-08-11 04:18:44'),
(8, 'Merci et fermeture', 'Merci pour votre confiance en SUZOSKY ! N\'hÃ©sitez pas Ã  nous recontacter si vous avez d\'autres questions.', 'closing', 1, 0, 1, '2025-08-11 04:18:44'),
(9, 'Informations gÃ©nÃ©rales', 'Pour plus d\'informations sur nos services, je vous invite Ã  consulter notre site web ou Ã  nous appeler directement.', 'info', 1, 0, 1, '2025-08-11 04:18:44'),
(10, 'Prise de contact', 'Je prends note de votre demande. Un de nos conseillers va vous recontacter dans les plus brefs dÃ©lais.', 'escalation', 1, 0, 1, '2025-08-11 04:18:44'),
(11, 'DÃ©lai de livraison', 'Nos dÃ©lais de livraison standard sont de 30 minutes Ã  2h selon la zone gÃ©ographique et le type de service demandÃ©.', 'info', 1, 0, 1, '2025-08-11 04:18:44'),
(12, 'Tarification', 'Nos tarifs varient selon la distance et le type de service. Souhaitez-vous que je vous fasse un devis personnalisÃ© ?', 'info', 1, 0, 1, '2025-08-11 04:18:44'),
(13, 'Salutation', 'Bonjour ! Bienvenue sur le chat SUZOSKY. Comment puis-je vous aider aujourd\'hui ?', 'greeting', 1, 0, 1, '2025-08-11 04:18:44'),
(14, 'Merci et fermeture', 'Merci pour votre confiance en SUZOSKY ! N\'hÃ©sitez pas Ã  nous recontacter si vous avez d\'autres questions.', 'closing', 1, 0, 1, '2025-08-11 04:18:44'),
(15, 'Informations gÃ©nÃ©rales', 'Pour plus d\'informations sur nos services, je vous invite Ã  consulter notre site web ou Ã  nous appeler directement.', 'info', 1, 0, 1, '2025-08-11 04:18:44'),
(16, 'Prise de contact', 'Je prends note de votre demande. Un de nos conseillers va vous recontacter dans les plus brefs dÃ©lais.', 'escalation', 1, 0, 1, '2025-08-11 04:18:44'),
(17, 'DÃ©lai de livraison', 'Nos dÃ©lais de livraison standard sont de 30 minutes Ã  2h selon la zone gÃ©ographique et le type de service demandÃ©.', 'info', 1, 0, 1, '2025-08-11 04:18:44'),
(18, 'Tarification', 'Nos tarifs varient selon la distance et le type de service. Souhaitez-vous que je vous fasse un devis personnalisÃ© ?', 'info', 1, 0, 1, '2025-08-11 04:18:44'),
(19, 'Salutation', 'Bonjour ! Bienvenue sur le chat SUZOSKY. Comment puis-je vous aider aujourd\'hui ?', 'greeting', 1, 0, 1, '2025-08-11 04:18:44'),
(20, 'Merci et fermeture', 'Merci pour votre confiance en SUZOSKY ! N\'hÃ©sitez pas Ã  nous recontacter si vous avez d\'autres questions.', 'closing', 1, 0, 1, '2025-08-11 04:18:44'),
(21, 'Informations gÃ©nÃ©rales', 'Pour plus d\'informations sur nos services, je vous invite Ã  consulter notre site web ou Ã  nous appeler directement.', 'info', 1, 0, 1, '2025-08-11 04:18:44'),
(22, 'Prise de contact', 'Je prends note de votre demande. Un de nos conseillers va vous recontacter dans les plus brefs dÃ©lais.', 'escalation', 1, 0, 1, '2025-08-11 04:18:44'),
(23, 'DÃ©lai de livraison', 'Nos dÃ©lais de livraison standard sont de 30 minutes Ã  2h selon la zone gÃ©ographique et le type de service demandÃ©.', 'info', 1, 0, 1, '2025-08-11 04:18:44'),
(24, 'Tarification', 'Nos tarifs varient selon la distance et le type de service. Souhaitez-vous que je vous fasse un devis personnalisÃ© ?', 'info', 1, 0, 1, '2025-08-11 04:18:44'),
(25, 'Salutation', 'Bonjour ! Bienvenue sur le chat SUZOSKY. Comment puis-je vous aider aujourd\'hui ?', 'greeting', 1, 0, 1, '2025-08-11 04:18:47'),
(26, 'Merci et fermeture', 'Merci pour votre confiance en SUZOSKY ! N\'hÃ©sitez pas Ã  nous recontacter si vous avez d\'autres questions.', 'closing', 1, 0, 1, '2025-08-11 04:18:47'),
(27, 'Informations gÃ©nÃ©rales', 'Pour plus d\'informations sur nos services, je vous invite Ã  consulter notre site web ou Ã  nous appeler directement.', 'info', 1, 0, 1, '2025-08-11 04:18:47'),
(28, 'Prise de contact', 'Je prends note de votre demande. Un de nos conseillers va vous recontacter dans les plus brefs dÃ©lais.', 'escalation', 1, 0, 1, '2025-08-11 04:18:47'),
(29, 'DÃ©lai de livraison', 'Nos dÃ©lais de livraison standard sont de 30 minutes Ã  2h selon la zone gÃ©ographique et le type de service demandÃ©.', 'info', 1, 0, 1, '2025-08-11 04:18:47'),
(30, 'Tarification', 'Nos tarifs varient selon la distance et le type de service. Souhaitez-vous que je vous fasse un devis personnalisÃ© ?', 'info', 1, 0, 1, '2025-08-11 04:18:47'),
(31, 'Salutation', 'Bonjour ! Bienvenue sur le chat SUZOSKY. Comment puis-je vous aider aujourd\'hui ?', 'greeting', 1, 0, 1, '2025-08-11 04:18:47'),
(32, 'Merci et fermeture', 'Merci pour votre confiance en SUZOSKY ! N\'hÃ©sitez pas Ã  nous recontacter si vous avez d\'autres questions.', 'closing', 1, 0, 1, '2025-08-11 04:18:47'),
(33, 'Informations gÃ©nÃ©rales', 'Pour plus d\'informations sur nos services, je vous invite Ã  consulter notre site web ou Ã  nous appeler directement.', 'info', 1, 0, 1, '2025-08-11 04:18:47'),
(34, 'Prise de contact', 'Je prends note de votre demande. Un de nos conseillers va vous recontacter dans les plus brefs dÃ©lais.', 'escalation', 1, 0, 1, '2025-08-11 04:18:47'),
(35, 'DÃ©lai de livraison', 'Nos dÃ©lais de livraison standard sont de 30 minutes Ã  2h selon la zone gÃ©ographique et le type de service demandÃ©.', 'info', 1, 0, 1, '2025-08-11 04:18:47'),
(36, 'Tarification', 'Nos tarifs varient selon la distance et le type de service. Souhaitez-vous que je vous fasse un devis personnalisÃ© ?', 'info', 1, 0, 1, '2025-08-11 04:18:47'),
(37, 'Salutation', 'Bonjour ! Bienvenue sur le chat SUZOSKY. Comment puis-je vous aider aujourd\'hui ?', 'greeting', 1, 0, 1, '2025-08-11 04:18:50'),
(38, 'Merci et fermeture', 'Merci pour votre confiance en SUZOSKY ! N\'hÃ©sitez pas Ã  nous recontacter si vous avez d\'autres questions.', 'closing', 1, 0, 1, '2025-08-11 04:18:50'),
(39, 'Informations gÃ©nÃ©rales', 'Pour plus d\'informations sur nos services, je vous invite Ã  consulter notre site web ou Ã  nous appeler directement.', 'info', 1, 0, 1, '2025-08-11 04:18:50'),
(40, 'Prise de contact', 'Je prends note de votre demande. Un de nos conseillers va vous recontacter dans les plus brefs dÃ©lais.', 'escalation', 1, 0, 1, '2025-08-11 04:18:50'),
(41, 'DÃ©lai de livraison', 'Nos dÃ©lais de livraison standard sont de 30 minutes Ã  2h selon la zone gÃ©ographique et le type de service demandÃ©.', 'info', 1, 0, 1, '2025-08-11 04:18:50'),
(42, 'Tarification', 'Nos tarifs varient selon la distance et le type de service. Souhaitez-vous que je vous fasse un devis personnalisÃ© ?', 'info', 1, 0, 1, '2025-08-11 04:18:50'),
(43, 'Salutation', 'Bonjour ! Bienvenue sur le chat SUZOSKY. Comment puis-je vous aider aujourd\'hui ?', 'greeting', 1, 0, 1, '2025-08-11 04:18:50'),
(44, 'Merci et fermeture', 'Merci pour votre confiance en SUZOSKY ! N\'hÃ©sitez pas Ã  nous recontacter si vous avez d\'autres questions.', 'closing', 1, 0, 1, '2025-08-11 04:18:50'),
(45, 'Informations gÃ©nÃ©rales', 'Pour plus d\'informations sur nos services, je vous invite Ã  consulter notre site web ou Ã  nous appeler directement.', 'info', 1, 0, 1, '2025-08-11 04:18:50'),
(46, 'Prise de contact', 'Je prends note de votre demande. Un de nos conseillers va vous recontacter dans les plus brefs dÃ©lais.', 'escalation', 1, 0, 1, '2025-08-11 04:18:50'),
(47, 'DÃ©lai de livraison', 'Nos dÃ©lais de livraison standard sont de 30 minutes Ã  2h selon la zone gÃ©ographique et le type de service demandÃ©.', 'info', 1, 0, 1, '2025-08-11 04:18:50'),
(48, 'Tarification', 'Nos tarifs varient selon la distance et le type de service. Souhaitez-vous que je vous fasse un devis personnalisÃ© ?', 'info', 1, 0, 1, '2025-08-11 04:18:50'),
(49, 'Salutation', 'Bonjour ! Bienvenue sur le chat SUZOSKY. Comment puis-je vous aider aujourd\'hui ?', 'greeting', 1, 0, 1, '2025-08-11 04:18:53'),
(50, 'Merci et fermeture', 'Merci pour votre confiance en SUZOSKY ! N\'hÃ©sitez pas Ã  nous recontacter si vous avez d\'autres questions.', 'closing', 1, 0, 1, '2025-08-11 04:18:53'),
(51, 'Informations gÃ©nÃ©rales', 'Pour plus d\'informations sur nos services, je vous invite Ã  consulter notre site web ou Ã  nous appeler directement.', 'info', 1, 0, 1, '2025-08-11 04:18:53'),
(52, 'Prise de contact', 'Je prends note de votre demande. Un de nos conseillers va vous recontacter dans les plus brefs dÃ©lais.', 'escalation', 1, 0, 1, '2025-08-11 04:18:53'),
(53, 'DÃ©lai de livraison', 'Nos dÃ©lais de livraison standard sont de 30 minutes Ã  2h selon la zone gÃ©ographique et le type de service demandÃ©.', 'info', 1, 0, 1, '2025-08-11 04:18:53'),
(54, 'Tarification', 'Nos tarifs varient selon la distance et le type de service. Souhaitez-vous que je vous fasse un devis personnalisÃ© ?', 'info', 1, 0, 1, '2025-08-11 04:18:53'),
(55, 'Salutation', 'Bonjour ! Bienvenue sur le chat SUZOSKY. Comment puis-je vous aider aujourd\'hui ?', 'greeting', 1, 0, 1, '2025-08-11 04:18:53'),
(56, 'Merci et fermeture', 'Merci pour votre confiance en SUZOSKY ! N\'hÃ©sitez pas Ã  nous recontacter si vous avez d\'autres questions.', 'closing', 1, 0, 1, '2025-08-11 04:18:53'),
(57, 'Informations gÃ©nÃ©rales', 'Pour plus d\'informations sur nos services, je vous invite Ã  consulter notre site web ou Ã  nous appeler directement.', 'info', 1, 0, 1, '2025-08-11 04:18:53'),
(58, 'Prise de contact', 'Je prends note de votre demande. Un de nos conseillers va vous recontacter dans les plus brefs dÃ©lais.', 'escalation', 1, 0, 1, '2025-08-11 04:18:53'),
(59, 'DÃ©lai de livraison', 'Nos dÃ©lais de livraison standard sont de 30 minutes Ã  2h selon la zone gÃ©ographique et le type de service demandÃ©.', 'info', 1, 0, 1, '2025-08-11 04:18:53'),
(60, 'Tarification', 'Nos tarifs varient selon la distance et le type de service. Souhaitez-vous que je vous fasse un devis personnalisÃ© ?', 'info', 1, 0, 1, '2025-08-11 04:18:53'),
(61, 'Salutation', 'Bonjour ! Bienvenue sur le chat SUZOSKY. Comment puis-je vous aider aujourd\'hui ?', 'greeting', 1, 0, 1, '2025-08-11 04:18:53'),
(62, 'Merci et fermeture', 'Merci pour votre confiance en SUZOSKY ! N\'hÃ©sitez pas Ã  nous recontacter si vous avez d\'autres questions.', 'closing', 1, 0, 1, '2025-08-11 04:18:53'),
(63, 'Informations gÃ©nÃ©rales', 'Pour plus d\'informations sur nos services, je vous invite Ã  consulter notre site web ou Ã  nous appeler directement.', 'info', 1, 0, 1, '2025-08-11 04:18:53'),
(64, 'Prise de contact', 'Je prends note de votre demande. Un de nos conseillers va vous recontacter dans les plus brefs dÃ©lais.', 'escalation', 1, 0, 1, '2025-08-11 04:18:53'),
(65, 'DÃ©lai de livraison', 'Nos dÃ©lais de livraison standard sont de 30 minutes Ã  2h selon la zone gÃ©ographique et le type de service demandÃ©.', 'info', 1, 0, 1, '2025-08-11 04:18:53'),
(66, 'Tarification', 'Nos tarifs varient selon la distance et le type de service. Souhaitez-vous que je vous fasse un devis personnalisÃ© ?', 'info', 1, 0, 1, '2025-08-11 04:18:53'),
(67, 'Salutation', 'Bonjour ! Bienvenue sur le chat SUZOSKY. Comment puis-je vous aider aujourd\'hui ?', 'greeting', 1, 0, 1, '2025-08-11 04:18:55'),
(68, 'Merci et fermeture', 'Merci pour votre confiance en SUZOSKY ! N\'hÃ©sitez pas Ã  nous recontacter si vous avez d\'autres questions.', 'closing', 1, 0, 1, '2025-08-11 04:18:55'),
(69, 'Informations gÃ©nÃ©rales', 'Pour plus d\'informations sur nos services, je vous invite Ã  consulter notre site web ou Ã  nous appeler directement.', 'info', 1, 0, 1, '2025-08-11 04:18:55'),
(70, 'Prise de contact', 'Je prends note de votre demande. Un de nos conseillers va vous recontacter dans les plus brefs dÃ©lais.', 'escalation', 1, 0, 1, '2025-08-11 04:18:55'),
(71, 'DÃ©lai de livraison', 'Nos dÃ©lais de livraison standard sont de 30 minutes Ã  2h selon la zone gÃ©ographique et le type de service demandÃ©.', 'info', 1, 0, 1, '2025-08-11 04:18:55'),
(72, 'Tarification', 'Nos tarifs varient selon la distance et le type de service. Souhaitez-vous que je vous fasse un devis personnalisÃ© ?', 'info', 1, 0, 1, '2025-08-11 04:18:55'),
(73, 'Salutation', 'Bonjour ! Bienvenue sur le chat SUZOSKY. Comment puis-je vous aider aujourd\'hui ?', 'greeting', 1, 0, 1, '2025-08-11 04:18:56'),
(74, 'Merci et fermeture', 'Merci pour votre confiance en SUZOSKY ! N\'hÃ©sitez pas Ã  nous recontacter si vous avez d\'autres questions.', 'closing', 1, 0, 1, '2025-08-11 04:18:56'),
(75, 'Informations gÃ©nÃ©rales', 'Pour plus d\'informations sur nos services, je vous invite Ã  consulter notre site web ou Ã  nous appeler directement.', 'info', 1, 0, 1, '2025-08-11 04:18:56'),
(76, 'Prise de contact', 'Je prends note de votre demande. Un de nos conseillers va vous recontacter dans les plus brefs dÃ©lais.', 'escalation', 1, 0, 1, '2025-08-11 04:18:56'),
(77, 'DÃ©lai de livraison', 'Nos dÃ©lais de livraison standard sont de 30 minutes Ã  2h selon la zone gÃ©ographique et le type de service demandÃ©.', 'info', 1, 0, 1, '2025-08-11 04:18:56'),
(78, 'Tarification', 'Nos tarifs varient selon la distance et le type de service. Souhaitez-vous que je vous fasse un devis personnalisÃ© ?', 'info', 1, 0, 1, '2025-08-11 04:18:56'),
(79, 'Salutation', 'Bonjour ! Bienvenue sur le chat SUZOSKY. Comment puis-je vous aider aujourd\'hui ?', 'greeting', 1, 0, 1, '2025-08-11 04:18:56'),
(80, 'Merci et fermeture', 'Merci pour votre confiance en SUZOSKY ! N\'hÃ©sitez pas Ã  nous recontacter si vous avez d\'autres questions.', 'closing', 1, 0, 1, '2025-08-11 04:18:56'),
(81, 'Informations gÃ©nÃ©rales', 'Pour plus d\'informations sur nos services, je vous invite Ã  consulter notre site web ou Ã  nous appeler directement.', 'info', 1, 0, 1, '2025-08-11 04:18:56'),
(82, 'Prise de contact', 'Je prends note de votre demande. Un de nos conseillers va vous recontacter dans les plus brefs dÃ©lais.', 'escalation', 1, 0, 1, '2025-08-11 04:18:56'),
(83, 'DÃ©lai de livraison', 'Nos dÃ©lais de livraison standard sont de 30 minutes Ã  2h selon la zone gÃ©ographique et le type de service demandÃ©.', 'info', 1, 0, 1, '2025-08-11 04:18:56'),
(84, 'Tarification', 'Nos tarifs varient selon la distance et le type de service. Souhaitez-vous que je vous fasse un devis personnalisÃ© ?', 'info', 1, 0, 1, '2025-08-11 04:18:56'),
(85, 'Salutation', 'Bonjour ! Bienvenue sur le chat SUZOSKY. Comment puis-je vous aider aujourd\'hui ?', 'greeting', 1, 0, 1, '2025-08-11 04:18:59'),
(86, 'Merci et fermeture', 'Merci pour votre confiance en SUZOSKY ! N\'hÃ©sitez pas Ã  nous recontacter si vous avez d\'autres questions.', 'closing', 1, 0, 1, '2025-08-11 04:18:59'),
(87, 'Informations gÃ©nÃ©rales', 'Pour plus d\'informations sur nos services, je vous invite Ã  consulter notre site web ou Ã  nous appeler directement.', 'info', 1, 0, 1, '2025-08-11 04:18:59'),
(88, 'Prise de contact', 'Je prends note de votre demande. Un de nos conseillers va vous recontacter dans les plus brefs dÃ©lais.', 'escalation', 1, 0, 1, '2025-08-11 04:18:59'),
(89, 'DÃ©lai de livraison', 'Nos dÃ©lais de livraison standard sont de 30 minutes Ã  2h selon la zone gÃ©ographique et le type de service demandÃ©.', 'info', 1, 0, 1, '2025-08-11 04:18:59'),
(90, 'Tarification', 'Nos tarifs varient selon la distance et le type de service. Souhaitez-vous que je vous fasse un devis personnalisÃ© ?', 'info', 1, 0, 1, '2025-08-11 04:18:59'),
(91, 'Salutation', 'Bonjour ! Bienvenue sur le chat SUZOSKY. Comment puis-je vous aider aujourd\'hui ?', 'greeting', 1, 0, 1, '2025-08-11 04:18:59'),
(92, 'Merci et fermeture', 'Merci pour votre confiance en SUZOSKY ! N\'hÃ©sitez pas Ã  nous recontacter si vous avez d\'autres questions.', 'closing', 1, 0, 1, '2025-08-11 04:18:59'),
(93, 'Informations gÃ©nÃ©rales', 'Pour plus d\'informations sur nos services, je vous invite Ã  consulter notre site web ou Ã  nous appeler directement.', 'info', 1, 0, 1, '2025-08-11 04:18:59'),
(94, 'Prise de contact', 'Je prends note de votre demande. Un de nos conseillers va vous recontacter dans les plus brefs dÃ©lais.', 'escalation', 1, 0, 1, '2025-08-11 04:18:59'),
(95, 'DÃ©lai de livraison', 'Nos dÃ©lais de livraison standard sont de 30 minutes Ã  2h selon la zone gÃ©ographique et le type de service demandÃ©.', 'info', 1, 0, 1, '2025-08-11 04:18:59'),
(96, 'Tarification', 'Nos tarifs varient selon la distance et le type de service. Souhaitez-vous que je vous fasse un devis personnalisÃ© ?', 'info', 1, 0, 1, '2025-08-11 04:18:59'),
(97, 'Salutation', 'Bonjour ! Bienvenue sur le chat SUZOSKY. Comment puis-je vous aider aujourd\'hui ?', 'greeting', 1, 0, 1, '2025-08-11 04:19:05'),
(98, 'Merci et fermeture', 'Merci pour votre confiance en SUZOSKY ! N\'hÃ©sitez pas Ã  nous recontacter si vous avez d\'autres questions.', 'closing', 1, 0, 1, '2025-08-11 04:19:05'),
(99, 'Informations gÃ©nÃ©rales', 'Pour plus d\'informations sur nos services, je vous invite Ã  consulter notre site web ou Ã  nous appeler directement.', 'info', 1, 0, 1, '2025-08-11 04:19:05'),
(100, 'Prise de contact', 'Je prends note de votre demande. Un de nos conseillers va vous recontacter dans les plus brefs dÃ©lais.', 'escalation', 1, 0, 1, '2025-08-11 04:19:05'),
(101, 'DÃ©lai de livraison', 'Nos dÃ©lais de livraison standard sont de 30 minutes Ã  2h selon la zone gÃ©ographique et le type de service demandÃ©.', 'info', 1, 0, 1, '2025-08-11 04:19:05'),
(102, 'Tarification', 'Nos tarifs varient selon la distance et le type de service. Souhaitez-vous que je vous fasse un devis personnalisÃ© ?', 'info', 1, 0, 1, '2025-08-11 04:19:05'),
(103, 'Salutation', 'Bonjour ! Bienvenue sur le chat SUZOSKY. Comment puis-je vous aider aujourd\'hui ?', 'greeting', 1, 0, 1, '2025-08-11 04:19:07'),
(104, 'Merci et fermeture', 'Merci pour votre confiance en SUZOSKY ! N\'hÃ©sitez pas Ã  nous recontacter si vous avez d\'autres questions.', 'closing', 1, 0, 1, '2025-08-11 04:19:07'),
(105, 'Informations gÃ©nÃ©rales', 'Pour plus d\'informations sur nos services, je vous invite Ã  consulter notre site web ou Ã  nous appeler directement.', 'info', 1, 0, 1, '2025-08-11 04:19:07'),
(106, 'Prise de contact', 'Je prends note de votre demande. Un de nos conseillers va vous recontacter dans les plus brefs dÃ©lais.', 'escalation', 1, 0, 1, '2025-08-11 04:19:07'),
(107, 'DÃ©lai de livraison', 'Nos dÃ©lais de livraison standard sont de 30 minutes Ã  2h selon la zone gÃ©ographique et le type de service demandÃ©.', 'info', 1, 0, 1, '2025-08-11 04:19:07'),
(108, 'Tarification', 'Nos tarifs varient selon la distance et le type de service. Souhaitez-vous que je vous fasse un devis personnalisÃ© ?', 'info', 1, 0, 1, '2025-08-11 04:19:07'),
(109, 'Salutation', 'Bonjour ! Bienvenue sur le chat SUZOSKY. Comment puis-je vous aider aujourd\'hui ?', 'greeting', 1, 0, 1, '2025-08-11 04:21:48'),
(110, 'Merci et fermeture', 'Merci pour votre confiance en SUZOSKY ! N\'hÃ©sitez pas Ã  nous recontacter si vous avez d\'autres questions.', 'closing', 1, 0, 1, '2025-08-11 04:21:48'),
(111, 'Informations gÃ©nÃ©rales', 'Pour plus d\'informations sur nos services, je vous invite Ã  consulter notre site web ou Ã  nous appeler directement.', 'info', 1, 0, 1, '2025-08-11 04:21:48'),
(112, 'Prise de contact', 'Je prends note de votre demande. Un de nos conseillers va vous recontacter dans les plus brefs dÃ©lais.', 'escalation', 1, 0, 1, '2025-08-11 04:21:48'),
(113, 'DÃ©lai de livraison', 'Nos dÃ©lais de livraison standard sont de 30 minutes Ã  2h selon la zone gÃ©ographique et le type de service demandÃ©.', 'info', 1, 0, 1, '2025-08-11 04:21:48'),
(114, 'Tarification', 'Nos tarifs varient selon la distance et le type de service. Souhaitez-vous que je vous fasse un devis personnalisÃ© ?', 'info', 1, 0, 1, '2025-08-11 04:21:48'),
(115, 'Salutation', 'Bonjour ! Bienvenue sur le chat SUZOSKY. Comment puis-je vous aider aujourd\'hui ?', 'greeting', 1, 0, 1, '2025-08-11 05:11:57'),
(116, 'Merci et fermeture', 'Merci pour votre confiance en SUZOSKY ! N\'hÃ©sitez pas Ã  nous recontacter si vous avez d\'autres questions.', 'closing', 1, 0, 1, '2025-08-11 05:11:57'),
(117, 'Informations gÃ©nÃ©rales', 'Pour plus d\'informations sur nos services, je vous invite Ã  consulter notre site web ou Ã  nous appeler directement.', 'info', 1, 0, 1, '2025-08-11 05:11:57'),
(118, 'Prise de contact', 'Je prends note de votre demande. Un de nos conseillers va vous recontacter dans les plus brefs dÃ©lais.', 'escalation', 1, 0, 1, '2025-08-11 05:11:57'),
(119, 'DÃ©lai de livraison', 'Nos dÃ©lais de livraison standard sont de 30 minutes Ã  2h selon la zone gÃ©ographique et le type de service demandÃ©.', 'info', 1, 0, 1, '2025-08-11 05:11:57'),
(120, 'Tarification', 'Nos tarifs varient selon la distance et le type de service. Souhaitez-vous que je vous fasse un devis personnalisÃ© ?', 'info', 1, 0, 1, '2025-08-11 05:11:57'),
(121, 'Salutation', 'Bonjour ! Bienvenue sur le chat SUZOSKY. Comment puis-je vous aider aujourd\'hui ?', 'greeting', 1, 0, 1, '2025-08-11 05:34:29'),
(122, 'Merci et fermeture', 'Merci pour votre confiance en SUZOSKY ! N\'hÃ©sitez pas Ã  nous recontacter si vous avez d\'autres questions.', 'closing', 1, 0, 1, '2025-08-11 05:34:29'),
(123, 'Informations gÃ©nÃ©rales', 'Pour plus d\'informations sur nos services, je vous invite Ã  consulter notre site web ou Ã  nous appeler directement.', 'info', 1, 0, 1, '2025-08-11 05:34:29'),
(124, 'Prise de contact', 'Je prends note de votre demande. Un de nos conseillers va vous recontacter dans les plus brefs dÃ©lais.', 'escalation', 1, 0, 1, '2025-08-11 05:34:29'),
(125, 'DÃ©lai de livraison', 'Nos dÃ©lais de livraison standard sont de 30 minutes Ã  2h selon la zone gÃ©ographique et le type de service demandÃ©.', 'info', 1, 0, 1, '2025-08-11 05:34:29'),
(126, 'Tarification', 'Nos tarifs varient selon la distance et le type de service. Souhaitez-vous que je vous fasse un devis personnalisÃ© ?', 'info', 1, 0, 1, '2025-08-11 05:34:29'),
(127, 'Salutation', 'Bonjour ! Bienvenue sur le chat SUZOSKY. Comment puis-je vous aider aujourd\'hui ?', 'greeting', 1, 0, 1, '2025-08-11 05:34:36'),
(128, 'Merci et fermeture', 'Merci pour votre confiance en SUZOSKY ! N\'hÃ©sitez pas Ã  nous recontacter si vous avez d\'autres questions.', 'closing', 1, 0, 1, '2025-08-11 05:34:36'),
(129, 'Informations gÃ©nÃ©rales', 'Pour plus d\'informations sur nos services, je vous invite Ã  consulter notre site web ou Ã  nous appeler directement.', 'info', 1, 0, 1, '2025-08-11 05:34:36'),
(130, 'Prise de contact', 'Je prends note de votre demande. Un de nos conseillers va vous recontacter dans les plus brefs dÃ©lais.', 'escalation', 1, 0, 1, '2025-08-11 05:34:36'),
(131, 'DÃ©lai de livraison', 'Nos dÃ©lais de livraison standard sont de 30 minutes Ã  2h selon la zone gÃ©ographique et le type de service demandÃ©.', 'info', 1, 0, 1, '2025-08-11 05:34:36'),
(132, 'Tarification', 'Nos tarifs varient selon la distance et le type de service. Souhaitez-vous que je vous fasse un devis personnalisÃ© ?', 'info', 1, 0, 1, '2025-08-11 05:34:36'),
(133, 'Salutation', 'Bonjour ! Bienvenue sur le chat SUZOSKY. Comment puis-je vous aider aujourd\'hui ?', 'greeting', 1, 0, 1, '2025-08-11 05:34:44'),
(134, 'Merci et fermeture', 'Merci pour votre confiance en SUZOSKY ! N\'hÃ©sitez pas Ã  nous recontacter si vous avez d\'autres questions.', 'closing', 1, 0, 1, '2025-08-11 05:34:44'),
(135, 'Informations gÃ©nÃ©rales', 'Pour plus d\'informations sur nos services, je vous invite Ã  consulter notre site web ou Ã  nous appeler directement.', 'info', 1, 0, 1, '2025-08-11 05:34:44'),
(136, 'Prise de contact', 'Je prends note de votre demande. Un de nos conseillers va vous recontacter dans les plus brefs dÃ©lais.', 'escalation', 1, 0, 1, '2025-08-11 05:34:44'),
(137, 'DÃ©lai de livraison', 'Nos dÃ©lais de livraison standard sont de 30 minutes Ã  2h selon la zone gÃ©ographique et le type de service demandÃ©.', 'info', 1, 0, 1, '2025-08-11 05:34:44'),
(138, 'Tarification', 'Nos tarifs varient selon la distance et le type de service. Souhaitez-vous que je vous fasse un devis personnalisÃ© ?', 'info', 1, 0, 1, '2025-08-11 05:34:44'),
(139, 'Salutation', 'Bonjour ! Bienvenue sur le chat SUZOSKY. Comment puis-je vous aider aujourd\'hui ?', 'greeting', 1, 0, 1, '2025-08-11 05:34:47'),
(140, 'Merci et fermeture', 'Merci pour votre confiance en SUZOSKY ! N\'hÃ©sitez pas Ã  nous recontacter si vous avez d\'autres questions.', 'closing', 1, 0, 1, '2025-08-11 05:34:47'),
(141, 'Informations gÃ©nÃ©rales', 'Pour plus d\'informations sur nos services, je vous invite Ã  consulter notre site web ou Ã  nous appeler directement.', 'info', 1, 0, 1, '2025-08-11 05:34:47'),
(142, 'Prise de contact', 'Je prends note de votre demande. Un de nos conseillers va vous recontacter dans les plus brefs dÃ©lais.', 'escalation', 1, 0, 1, '2025-08-11 05:34:47'),
(143, 'DÃ©lai de livraison', 'Nos dÃ©lais de livraison standard sont de 30 minutes Ã  2h selon la zone gÃ©ographique et le type de service demandÃ©.', 'info', 1, 0, 1, '2025-08-11 05:34:47'),
(144, 'Tarification', 'Nos tarifs varient selon la distance et le type de service. Souhaitez-vous que je vous fasse un devis personnalisÃ© ?', 'info', 1, 0, 1, '2025-08-11 05:34:47'),
(145, 'Salutation', 'Bonjour ! Bienvenue sur le chat SUZOSKY. Comment puis-je vous aider aujourd\'hui ?', 'greeting', 1, 0, 1, '2025-08-11 05:34:47'),
(146, 'Merci et fermeture', 'Merci pour votre confiance en SUZOSKY ! N\'hÃ©sitez pas Ã  nous recontacter si vous avez d\'autres questions.', 'closing', 1, 0, 1, '2025-08-11 05:34:47'),
(147, 'Informations gÃ©nÃ©rales', 'Pour plus d\'informations sur nos services, je vous invite Ã  consulter notre site web ou Ã  nous appeler directement.', 'info', 1, 0, 1, '2025-08-11 05:34:47'),
(148, 'Prise de contact', 'Je prends note de votre demande. Un de nos conseillers va vous recontacter dans les plus brefs dÃ©lais.', 'escalation', 1, 0, 1, '2025-08-11 05:34:47'),
(149, 'DÃ©lai de livraison', 'Nos dÃ©lais de livraison standard sont de 30 minutes Ã  2h selon la zone gÃ©ographique et le type de service demandÃ©.', 'info', 1, 0, 1, '2025-08-11 05:34:47'),
(150, 'Tarification', 'Nos tarifs varient selon la distance et le type de service. Souhaitez-vous que je vous fasse un devis personnalisÃ© ?', 'info', 1, 0, 1, '2025-08-11 05:34:47'),
(151, 'Salutation', 'Bonjour ! Bienvenue sur le chat SUZOSKY. Comment puis-je vous aider aujourd\'hui ?', 'greeting', 1, 0, 1, '2025-08-11 05:34:51'),
(152, 'Merci et fermeture', 'Merci pour votre confiance en SUZOSKY ! N\'hÃ©sitez pas Ã  nous recontacter si vous avez d\'autres questions.', 'closing', 1, 0, 1, '2025-08-11 05:34:51'),
(153, 'Informations gÃ©nÃ©rales', 'Pour plus d\'informations sur nos services, je vous invite Ã  consulter notre site web ou Ã  nous appeler directement.', 'info', 1, 0, 1, '2025-08-11 05:34:51'),
(154, 'Prise de contact', 'Je prends note de votre demande. Un de nos conseillers va vous recontacter dans les plus brefs dÃ©lais.', 'escalation', 1, 0, 1, '2025-08-11 05:34:51'),
(155, 'DÃ©lai de livraison', 'Nos dÃ©lais de livraison standard sont de 30 minutes Ã  2h selon la zone gÃ©ographique et le type de service demandÃ©.', 'info', 1, 0, 1, '2025-08-11 05:34:51'),
(156, 'Tarification', 'Nos tarifs varient selon la distance et le type de service. Souhaitez-vous que je vous fasse un devis personnalisÃ© ?', 'info', 1, 0, 1, '2025-08-11 05:34:51'),
(157, 'Salutation', 'Bonjour ! Bienvenue sur le chat SUZOSKY. Comment puis-je vous aider aujourd\'hui ?', 'greeting', 1, 0, 1, '2025-08-11 05:34:53'),
(158, 'Merci et fermeture', 'Merci pour votre confiance en SUZOSKY ! N\'hÃ©sitez pas Ã  nous recontacter si vous avez d\'autres questions.', 'closing', 1, 0, 1, '2025-08-11 05:34:53'),
(159, 'Informations gÃ©nÃ©rales', 'Pour plus d\'informations sur nos services, je vous invite Ã  consulter notre site web ou Ã  nous appeler directement.', 'info', 1, 0, 1, '2025-08-11 05:34:53'),
(160, 'Prise de contact', 'Je prends note de votre demande. Un de nos conseillers va vous recontacter dans les plus brefs dÃ©lais.', 'escalation', 1, 0, 1, '2025-08-11 05:34:53'),
(161, 'DÃ©lai de livraison', 'Nos dÃ©lais de livraison standard sont de 30 minutes Ã  2h selon la zone gÃ©ographique et le type de service demandÃ©.', 'info', 1, 0, 1, '2025-08-11 05:34:53'),
(162, 'Tarification', 'Nos tarifs varient selon la distance et le type de service. Souhaitez-vous que je vous fasse un devis personnalisÃ© ?', 'info', 1, 0, 1, '2025-08-11 05:34:53'),
(163, 'Salutation', 'Bonjour ! Bienvenue sur le chat SUZOSKY. Comment puis-je vous aider aujourd\'hui ?', 'greeting', 1, 0, 1, '2025-08-11 05:34:53'),
(164, 'Merci et fermeture', 'Merci pour votre confiance en SUZOSKY ! N\'hÃ©sitez pas Ã  nous recontacter si vous avez d\'autres questions.', 'closing', 1, 0, 1, '2025-08-11 05:34:53'),
(165, 'Informations gÃ©nÃ©rales', 'Pour plus d\'informations sur nos services, je vous invite Ã  consulter notre site web ou Ã  nous appeler directement.', 'info', 1, 0, 1, '2025-08-11 05:34:53'),
(166, 'Prise de contact', 'Je prends note de votre demande. Un de nos conseillers va vous recontacter dans les plus brefs dÃ©lais.', 'escalation', 1, 0, 1, '2025-08-11 05:34:53'),
(167, 'DÃ©lai de livraison', 'Nos dÃ©lais de livraison standard sont de 30 minutes Ã  2h selon la zone gÃ©ographique et le type de service demandÃ©.', 'info', 1, 0, 1, '2025-08-11 05:34:53'),
(168, 'Tarification', 'Nos tarifs varient selon la distance et le type de service. Souhaitez-vous que je vous fasse un devis personnalisÃ© ?', 'info', 1, 0, 1, '2025-08-11 05:34:53'),
(169, 'Salutation', 'Bonjour ! Bienvenue sur le chat SUZOSKY. Comment puis-je vous aider aujourd\'hui ?', 'greeting', 1, 0, 1, '2025-08-11 05:35:05'),
(170, 'Merci et fermeture', 'Merci pour votre confiance en SUZOSKY ! N\'hÃ©sitez pas Ã  nous recontacter si vous avez d\'autres questions.', 'closing', 1, 0, 1, '2025-08-11 05:35:05'),
(171, 'Informations gÃ©nÃ©rales', 'Pour plus d\'informations sur nos services, je vous invite Ã  consulter notre site web ou Ã  nous appeler directement.', 'info', 1, 0, 1, '2025-08-11 05:35:05'),
(172, 'Prise de contact', 'Je prends note de votre demande. Un de nos conseillers va vous recontacter dans les plus brefs dÃ©lais.', 'escalation', 1, 0, 1, '2025-08-11 05:35:05'),
(173, 'DÃ©lai de livraison', 'Nos dÃ©lais de livraison standard sont de 30 minutes Ã  2h selon la zone gÃ©ographique et le type de service demandÃ©.', 'info', 1, 0, 1, '2025-08-11 05:35:05'),
(174, 'Tarification', 'Nos tarifs varient selon la distance et le type de service. Souhaitez-vous que je vous fasse un devis personnalisÃ© ?', 'info', 1, 0, 1, '2025-08-11 05:35:05'),
(175, 'Salutation', 'Bonjour ! Bienvenue sur le chat SUZOSKY. Comment puis-je vous aider aujourd\'hui ?', 'greeting', 1, 0, 1, '2025-08-11 05:35:06'),
(176, 'Merci et fermeture', 'Merci pour votre confiance en SUZOSKY ! N\'hÃ©sitez pas Ã  nous recontacter si vous avez d\'autres questions.', 'closing', 1, 0, 1, '2025-08-11 05:35:06'),
(177, 'Informations gÃ©nÃ©rales', 'Pour plus d\'informations sur nos services, je vous invite Ã  consulter notre site web ou Ã  nous appeler directement.', 'info', 1, 0, 1, '2025-08-11 05:35:06'),
(178, 'Prise de contact', 'Je prends note de votre demande. Un de nos conseillers va vous recontacter dans les plus brefs dÃ©lais.', 'escalation', 1, 0, 1, '2025-08-11 05:35:06'),
(179, 'DÃ©lai de livraison', 'Nos dÃ©lais de livraison standard sont de 30 minutes Ã  2h selon la zone gÃ©ographique et le type de service demandÃ©.', 'info', 1, 0, 1, '2025-08-11 05:35:06'),
(180, 'Tarification', 'Nos tarifs varient selon la distance et le type de service. Souhaitez-vous que je vous fasse un devis personnalisÃ© ?', 'info', 1, 0, 1, '2025-08-11 05:35:06'),
(181, 'Salutation', 'Bonjour ! Bienvenue sur le chat SUZOSKY. Comment puis-je vous aider aujourd\'hui ?', 'greeting', 1, 0, 1, '2025-08-11 05:35:06'),
(182, 'Merci et fermeture', 'Merci pour votre confiance en SUZOSKY ! N\'hÃ©sitez pas Ã  nous recontacter si vous avez d\'autres questions.', 'closing', 1, 0, 1, '2025-08-11 05:35:06'),
(183, 'Informations gÃ©nÃ©rales', 'Pour plus d\'informations sur nos services, je vous invite Ã  consulter notre site web ou Ã  nous appeler directement.', 'info', 1, 0, 1, '2025-08-11 05:35:06'),
(184, 'Prise de contact', 'Je prends note de votre demande. Un de nos conseillers va vous recontacter dans les plus brefs dÃ©lais.', 'escalation', 1, 0, 1, '2025-08-11 05:35:06'),
(185, 'DÃ©lai de livraison', 'Nos dÃ©lais de livraison standard sont de 30 minutes Ã  2h selon la zone gÃ©ographique et le type de service demandÃ©.', 'info', 1, 0, 1, '2025-08-11 05:35:06'),
(186, 'Tarification', 'Nos tarifs varient selon la distance et le type de service. Souhaitez-vous que je vous fasse un devis personnalisÃ© ?', 'info', 1, 0, 1, '2025-08-11 05:35:06');

-- --------------------------------------------------------

--
-- Structure de la table `chat_sessions`
--

CREATE TABLE `chat_sessions` (
  `id` int(11) NOT NULL,
  `session_id` varchar(50) NOT NULL,
  `client_name` varchar(100) DEFAULT NULL,
  `client_email` varchar(150) DEFAULT NULL,
  `client_phone` varchar(20) DEFAULT NULL,
  `client_ip` varchar(45) NOT NULL,
  `client_location` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`client_location`)),
  `device_identifier` varchar(100) NOT NULL,
  `assigned_admin` int(11) DEFAULT NULL,
  `status` enum('waiting','active','closed','blocked') DEFAULT 'waiting',
  `priority` enum('low','normal','high','urgent') DEFAULT 'normal',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `last_message_at` timestamp NULL DEFAULT current_timestamp(),
  `is_blocked` tinyint(1) DEFAULT 0,
  `blocked_reason` text DEFAULT NULL,
  `blocked_by` int(11) DEFAULT NULL,
  `blocked_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `chat_unified`
--

CREATE TABLE `chat_unified` (
  `id` int(11) NOT NULL,
  `chat_id` varchar(50) NOT NULL,
  `type_chat` enum('client_admin','coursier_admin','agent_admin','business_admin') NOT NULL,
  `sender_type` enum('admin','client','coursier','agent','business') NOT NULL,
  `sender_id` int(11) DEFAULT NULL,
  `sender_name` varchar(100) NOT NULL,
  `message` text NOT NULL,
  `message_type` enum('text','file','image','system') DEFAULT 'text',
  `is_read` tinyint(1) DEFAULT 0,
  `timestamp` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `clients`
--

CREATE TABLE `clients` (
  `id` int(11) NOT NULL,
  `nom` varchar(100) NOT NULL,
  `telephone` varchar(20) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `adresse` text DEFAULT NULL,
  `password_hash` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `balance` decimal(10,2) DEFAULT 0.00 COMMENT 'Solde client',
  `type_client` enum('client','coursier','admin') DEFAULT 'client'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- DÃ©chargement des donnÃ©es de la table `clients`
--

INSERT INTO `clients` (`id`, `nom`, `telephone`, `email`, `adresse`, `password_hash`, `created_at`, `updated_at`, `balance`, `type_client`) VALUES
(1, 'ClientExp2029', '+2250758842029', NULL, NULL, NULL, '2025-09-03 00:47:05', '2025-09-03 00:47:05', 0.00, 'client'),
(2, 'ClientDest3769', '+2250102453769', NULL, NULL, NULL, '2025-09-03 00:47:05', '2025-09-03 00:47:05', 0.00, 'client'),
(3, 'ClientExp7 69', '+225 01 02 45 37 69', NULL, NULL, NULL, '2025-09-26 18:45:52', '2025-09-26 18:45:52', 0.00, 'client'),
(11, 'ClientExp0405', '2250102030405', NULL, NULL, NULL, '2025-09-26 18:45:52', '2025-09-26 18:45:52', 0.00, 'client'),
(12, 'ClientDest0201', '2250504030201', NULL, NULL, NULL, '2025-09-26 18:45:52', '2025-09-26 18:45:52', 0.00, 'client'),
(13, 'YAPO', 'ExpÃ©diteur', '+2250758842029', 'yapadone@gmail.com', '2025-09-01 03:33:07', '2025-09-03 00:08:13', '0000-00-00 00:00:00', 0.00, 'client'),
(15, 'ClientDest3769', 'Destinataire', '+2250102453769', NULL, '2025-09-02 16:42:56', '2025-09-03 00:08:13', '0000-00-00 00:00:00', 0.00, 'client'),
(16, 'ClientDest3769', '2250102453769', NULL, NULL, NULL, '2025-09-26 18:45:52', '2025-09-26 18:45:52', 0.00, 'client'),
(17, 'Manu', '0102453769', 'manudu225@gmail.com', NULL, NULL, '2025-09-26 18:45:52', '2025-09-26 18:45:52', 0.00, 'client'),
(76, 'ClientExp6789', 'Client', '0123456789', NULL, '2025-09-03 15:57:47', NULL, '0000-00-00 00:00:00', 0.00, 'client');

-- --------------------------------------------------------

--
-- Structure de la table `clients_business`
--

CREATE TABLE `clients_business` (
  `id` int(11) NOT NULL,
  `nom_entreprise` varchar(150) NOT NULL,
  `contact_nom` varchar(100) NOT NULL,
  `telephone` varchar(20) NOT NULL,
  `email` varchar(100) NOT NULL,
  `adresse` text DEFAULT NULL,
  `type_compte` enum('standard','premium','enterprise') DEFAULT 'standard',
  `credit_limite` decimal(10,2) DEFAULT 0.00,
  `credit_utilise` decimal(10,2) DEFAULT 0.00,
  `password_hash` varchar(255) NOT NULL,
  `statut` enum('actif','suspendu','inactif') DEFAULT 'actif',
  `date_inscription` timestamp NULL DEFAULT current_timestamp(),
  `derniere_connexion` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- DÃ©chargement des donnÃ©es de la table `clients_business`
--

INSERT INTO `clients_business` (`id`, `nom_entreprise`, `contact_nom`, `telephone`, `email`, `adresse`, `type_compte`, `credit_limite`, `credit_utilise`, `password_hash`, `statut`, `date_inscription`, `derniere_connexion`, `created_at`, `updated_at`) VALUES
(1, 'PUSHI CI', 'Jean Kouassi', '+225 07 11 11 11 11', 'contact@pushi.ci', NULL, 'standard', 0.00, 0.00, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'actif', '2025-08-08 09:03:00', NULL, '2025-08-08 09:03:00', '2025-08-08 09:03:00'),
(2, 'Express Logistics', 'Marie TraorÃ©', '+225 07 22 22 22 22', 'contact@express-logistics.ci', NULL, 'standard', 0.00, 0.00, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'actif', '2025-08-08 09:03:00', NULL, '2025-08-08 09:03:00', '2025-08-08 09:03:00');

-- --------------------------------------------------------

--
-- Structure de la table `clients_business_unified`
--

CREATE TABLE `clients_business_unified` (
  `id` int(11) NOT NULL,
  `nom_entreprise` varchar(200) NOT NULL,
  `contact_nom` varchar(100) NOT NULL,
  `contact_telephone` varchar(20) NOT NULL,
  `contact_email` varchar(150) DEFAULT NULL,
  `adresse_complete` text NOT NULL,
  `ville` varchar(50) DEFAULT 'Abidjan',
  `secteur_activite` varchar(100) DEFAULT NULL,
  `type_client` enum('particulier','entreprise','partenaire') DEFAULT 'entreprise',
  `statut` enum('actif','inactif','suspendu') DEFAULT 'actif',
  `date_inscription` timestamp NULL DEFAULT current_timestamp(),
  `derniere_commande` timestamp NULL DEFAULT NULL,
  `total_commandes` int(11) DEFAULT 0,
  `ca_total` decimal(12,2) DEFAULT 0.00,
  `notes_admin` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `clients_particuliers`
--

CREATE TABLE `clients_particuliers` (
  `id` int(11) NOT NULL,
  `nom` varchar(100) NOT NULL,
  `prenoms` varchar(100) NOT NULL,
  `telephone` varchar(20) NOT NULL,
  `email` varchar(150) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `current_session_token` varchar(100) DEFAULT NULL,
  `last_login_at` datetime DEFAULT NULL,
  `last_login_ip` varchar(64) DEFAULT NULL,
  `last_login_user_agent` varchar(255) DEFAULT NULL,
  `date_creation` timestamp NULL DEFAULT current_timestamp(),
  `date_derniere_commande` timestamp NULL DEFAULT NULL,
  `statut` enum('actif','inactif') DEFAULT 'actif',
  `reset_token` varchar(255) DEFAULT NULL,
  `reset_expires_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- DÃ©chargement des donnÃ©es de la table `clients_particuliers`
--

INSERT INTO `clients_particuliers` (`id`, `nom`, `prenoms`, `telephone`, `email`, `password`, `current_session_token`, `last_login_at`, `last_login_ip`, `last_login_user_agent`, `date_creation`, `date_derniere_commande`, `statut`, `reset_token`, `reset_expires_at`) VALUES
(1, 'YAPO', 'Emmanuel', '+225 07 58 84 20 29', 'yapadone@gmail.com', '$2y$10$SyI0It4xo2fjaX9AdxvGGOWibDW.2t3yM2MQBJ4T9gEWf98/CxXda', NULL, NULL, NULL, NULL, '2025-09-08 06:11:32', NULL, 'actif', 'f0cdb43a2ed4284b7a6209fa4ccbfc71', '2025-09-26 02:54:55'),
(2, 'Test', 'User', '+22500 00 00 00 00', 'test@test.com', '$2y$10$KYfQ8XFIhzMNswaY7hPLf.ZdqHls5wz6kaRc2SCs87kTI9y96rGwa', NULL, NULL, NULL, NULL, '2025-09-12 00:52:11', NULL, 'actif', NULL, NULL),
(3, 'ClientExp7 69', 'Client', '+225 01 02 45 37 69', NULL, NULL, NULL, NULL, NULL, NULL, '2025-09-21 08:24:00', NULL, 'actif', NULL, NULL),
(11, 'ClientExp0405', 'Client', '2250102030405', NULL, NULL, NULL, NULL, NULL, NULL, '2025-09-21 09:00:32', NULL, 'actif', NULL, NULL),
(12, 'ClientDest0201', 'Client', '2250504030201', NULL, NULL, NULL, NULL, NULL, NULL, '2025-09-21 09:00:32', NULL, 'actif', NULL, NULL),
(15, 'ClientExp2029', 'Client', '2250758842029', NULL, NULL, NULL, NULL, NULL, NULL, '2025-09-21 17:31:37', NULL, 'actif', NULL, NULL),
(16, 'ClientDest3769', 'Client', '2250102453769', NULL, NULL, NULL, NULL, NULL, NULL, '2025-09-21 17:31:37', NULL, 'actif', NULL, NULL),
(17, 'Manu', 'Yaa', '0102453769', 'manudu225@gmail.com', '$2y$10$tqYxk19P5tNc3yCmhhuSk.p7QaaSeQCEPa2wIsykWDEi9GlBZJkR.', '8e1a3e7851aa71fe7048cd4ad04f194a', '2025-09-26 08:03:31', '2001:42d8:3b23:9d00:f950:6f4d:a87:bdbc', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-26 02:09:13', NULL, 'actif', NULL, NULL),
(66, 'ClientExp0707', 'Client', '2250707070707', NULL, NULL, NULL, NULL, NULL, NULL, '2025-09-26 21:55:39', NULL, 'actif', NULL, NULL),
(67, 'ClientDest0808', 'Client', '2250808080808', NULL, NULL, NULL, NULL, NULL, NULL, '2025-09-26 21:55:39', NULL, 'actif', NULL, NULL);

-- --------------------------------------------------------

--
-- Structure de la table `clients_unified`
--

CREATE TABLE `clients_unified` (
  `id` int(11) NOT NULL,
  `code_client` varchar(20) DEFAULT NULL,
  `nom_complet` varchar(200) NOT NULL,
  `telephone` varchar(20) NOT NULL,
  `email` varchar(150) DEFAULT NULL,
  `adresse_complete` text DEFAULT NULL,
  `ville` varchar(50) DEFAULT 'Abidjan',
  `type_client` enum('particulier','entreprise','business','concierge_client') DEFAULT 'particulier',
  `statut` enum('actif','inactif','suspendu') DEFAULT 'actif',
  `concierge_id` int(11) DEFAULT NULL COMMENT 'ID du concierge assignÃ©',
  `date_inscription` timestamp NULL DEFAULT current_timestamp(),
  `derniere_commande` timestamp NULL DEFAULT NULL,
  `total_commandes` int(11) DEFAULT 0,
  `ca_total` decimal(12,2) DEFAULT 0.00,
  `interface_creation` enum('admin','business','coursier','concierge') NOT NULL,
  `notes_admin` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `commandes`
--

CREATE TABLE `commandes` (
  `id` int(11) NOT NULL,
  `order_number` varchar(50) NOT NULL,
  `code_commande` varchar(20) NOT NULL,
  `client_type` enum('particulier','business') NOT NULL,
  `client_id` int(11) DEFAULT NULL,
  `client_business_id` int(11) DEFAULT NULL,
  `client_nom` varchar(100) DEFAULT NULL,
  `client_telephone` varchar(20) NOT NULL,
  `adresse_retrait` text NOT NULL,
  `latitude_retrait` decimal(10,8) DEFAULT NULL,
  `longitude_retrait` decimal(11,8) DEFAULT NULL,
  `adresse_livraison` text NOT NULL,
  `latitude_livraison` decimal(10,8) DEFAULT NULL,
  `longitude_livraison` decimal(11,8) DEFAULT NULL,
  `description_colis` text DEFAULT NULL,
  `poids_estime` decimal(5,2) DEFAULT NULL,
  `dimensions` varchar(50) DEFAULT NULL,
  `valeur_declaree` decimal(10,2) DEFAULT NULL,
  `fragile` tinyint(1) DEFAULT 0,
  `coursier_id` int(11) DEFAULT NULL,
  `statut` varchar(32) DEFAULT 'nouvelle',
  `priorite` enum('normale','urgente','express') DEFAULT 'normale',
  `heure_souhaitee_retrait` timestamp NULL DEFAULT NULL,
  `heure_souhaitee_livraison` timestamp NULL DEFAULT NULL,
  `heure_acceptation` timestamp NULL DEFAULT NULL,
  `heure_retrait` timestamp NULL DEFAULT NULL,
  `heure_livraison` timestamp NULL DEFAULT NULL,
  `prix_base` decimal(8,2) NOT NULL,
  `frais_supplementaires` decimal(8,2) DEFAULT 0.00,
  `prix_total` decimal(8,2) NOT NULL,
  `mode_paiement` enum('especes','mobile_money','carte_bancaire','wave','credit_business') DEFAULT 'especes',
  `prix_estime` decimal(10,2) NOT NULL DEFAULT 0.00,
  `statut_paiement` enum('attente','paye','echec') DEFAULT 'attente',
  `note_coursier` decimal(3,2) DEFAULT NULL,
  `commentaire_client` text DEFAULT NULL,
  `note_service` decimal(3,2) DEFAULT NULL,
  `annule_par` enum('client','coursier','admin') DEFAULT NULL,
  `raison_annulation` text DEFAULT NULL,
  `distance_estimee` decimal(5,2) DEFAULT NULL,
  `zone_retrait` varchar(50) DEFAULT NULL,
  `zone_livraison` varchar(50) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `expediteur_id` int(11) DEFAULT NULL,
  `destinataire_id` int(11) DEFAULT NULL,
  `adresse_depart` varchar(255) NOT NULL,
  `latitude_depart` decimal(10,7) DEFAULT NULL,
  `longitude_depart` decimal(10,7) DEFAULT NULL,
  `adresse_arrivee` varchar(255) NOT NULL,
  `latitude_arrivee` decimal(10,7) DEFAULT NULL,
  `longitude_arrivee` decimal(10,7) DEFAULT NULL,
  `telephone_expediteur` varchar(20) DEFAULT NULL,
  `telephone_destinataire` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- DÃ©chargement des donnÃ©es de la table `commandes`
--

INSERT INTO `commandes` (`id`, `order_number`, `code_commande`, `client_type`, `client_id`, `client_business_id`, `client_nom`, `client_telephone`, `adresse_retrait`, `latitude_retrait`, `longitude_retrait`, `adresse_livraison`, `latitude_livraison`, `longitude_livraison`, `description_colis`, `poids_estime`, `dimensions`, `valeur_declaree`, `fragile`, `coursier_id`, `statut`, `priorite`, `heure_souhaitee_retrait`, `heure_souhaitee_livraison`, `heure_acceptation`, `heure_retrait`, `heure_livraison`, `prix_base`, `frais_supplementaires`, `prix_total`, `mode_paiement`, `prix_estime`, `statut_paiement`, `note_coursier`, `commentaire_client`, `note_service`, `annule_par`, `raison_annulation`, `distance_estimee`, `zone_retrait`, `zone_livraison`, `created_at`, `updated_at`, `expediteur_id`, `destinataire_id`, `adresse_depart`, `latitude_depart`, `longitude_depart`, `adresse_arrivee`, `latitude_arrivee`, `longitude_arrivee`, `telephone_expediteur`, `telephone_destinataire`) VALUES
(2, 'SZK20250903076d35', '', 'particulier', 1, NULL, NULL, '', '', NULL, NULL, '', NULL, NULL, '', NULL, NULL, NULL, 0, NULL, 'nouvelle', 'normale', NULL, NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, '', 0.00, 'attente', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-09-03 00:47:05', '2025-09-03 00:47:05', 1, 2, 'Champroux Stadium, Abidjan, CÃ´te d\'Ivoire', NULL, NULL, 'Sipim Atlantide PORT-BOUÃ‹T, Abidjan, CÃ´te d\'Ivoire', NULL, NULL, '+2250758842029', '+2250102453769'),
(4, '', 'SZK202509033b993b', 'particulier', 1, NULL, NULL, '', '', NULL, NULL, '', NULL, NULL, '', NULL, NULL, NULL, 0, NULL, 'nouvelle', 'normale', NULL, NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, '', 0.00, 'attente', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-09-03 00:57:03', '2025-09-03 00:57:03', 1, 2, 'Champroux Stadium, Abidjan, CÃ´te d\'Ivoire', NULL, NULL, 'Sipim Atlantide PORT-BOUÃ‹T, Abidjan, CÃ´te d\'Ivoire', NULL, NULL, '+2250758842029', '+2250102453769'),
(32, 'SZK20250904c95509', 'SZK250904347444', 'particulier', 1, NULL, NULL, '', '', NULL, NULL, '', NULL, NULL, '', NULL, NULL, NULL, 0, NULL, 'nouvelle', 'normale', NULL, NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, '', 0.00, 'attente', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-09-04 10:38:18', '2025-09-04 10:38:18', 13, 15, 'Champroux Stadium, Abidjan, CÃ´te d\'Ivoire', NULL, NULL, 'Sipim Atlantide PORT-BOUÃ‹T, Abidjan, CÃ´te d\'Ivoire', NULL, NULL, '+2250758842029', '+2250102453769'),
(33, 'SZK202509049945e8', 'SZK250904226797', 'particulier', 1, NULL, NULL, '', '', NULL, NULL, '', NULL, NULL, '', NULL, NULL, NULL, 0, NULL, 'nouvelle', 'normale', NULL, NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, '', 0.00, 'attente', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-09-04 10:43:17', '2025-09-04 10:43:17', 13, 15, 'Champroux Stadium, Abidjan, CÃ´te d\'Ivoire', NULL, NULL, 'Sipim Atlantide PORT-BOUÃ‹T, Abidjan, CÃ´te d\'Ivoire', NULL, NULL, '+2250758842029', '+2250102453769'),
(34, 'SZK202509045a337a', 'SZK250904843966', 'particulier', 1, NULL, NULL, '', '', NULL, NULL, '', NULL, NULL, '', NULL, NULL, NULL, 0, NULL, 'nouvelle', 'normale', NULL, NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, '', 0.00, 'attente', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-09-04 10:47:19', '2025-09-04 10:47:19', 13, 15, 'Champroux Stadium, Abidjan, CÃ´te d\'Ivoire', NULL, NULL, 'Sipim Atlantide PORT-BOUÃ‹T, Abidjan, CÃ´te d\'Ivoire', NULL, NULL, '+2250758842029', '+2250102453769'),
(35, 'SZK20250904e9d68e', 'SZK250904397534', 'particulier', 1, NULL, NULL, '', '', NULL, NULL, '', NULL, NULL, '', NULL, NULL, NULL, 0, NULL, 'nouvelle', 'normale', NULL, NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, '', 0.00, 'attente', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-09-04 12:30:50', '2025-09-04 12:30:50', 13, 15, 'Champroux Stadium, Abidjan, CÃ´te d\'Ivoire', NULL, NULL, 'Sipim Atlantide PORT-BOUÃ‹T, Abidjan, CÃ´te d\'Ivoire', NULL, NULL, '+2250758842029', '+2250102453769'),
(36, 'SZK202509047a8f68', 'SZK250904389863', 'particulier', 1, NULL, NULL, '', '', NULL, NULL, '', NULL, NULL, '', NULL, NULL, NULL, 0, NULL, 'nouvelle', 'normale', NULL, NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, '', 0.00, 'attente', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-09-04 12:32:22', '2025-09-04 12:32:22', 13, 15, 'Champroux Stadium, Abidjan, CÃ´te d\'Ivoire', NULL, NULL, 'Sipim Atlantide PORT-BOUÃ‹T, Abidjan, CÃ´te d\'Ivoire', NULL, NULL, '+2250758842029', '+2250102453769'),
(37, 'SZK20250904d4f58e', 'SZK250904506131', 'particulier', 1, NULL, NULL, '', '', NULL, NULL, '', NULL, NULL, '', NULL, NULL, NULL, 0, NULL, 'nouvelle', 'normale', NULL, NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, '', 0.00, 'attente', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-09-04 12:54:18', '2025-09-04 12:54:18', 13, 13, 'Champroux Stadium, Abidjan, CÃ´te d\'Ivoire', NULL, NULL, 'Sipim Atlantide PORT-BOUÃ‹T, Abidjan, CÃ´te d\'Ivoire', NULL, NULL, '+2250758842029', '+2250758842029'),
(38, 'SZK20250904d2b896', 'SZK250904113431', 'particulier', 1, NULL, NULL, '', '', NULL, NULL, '', NULL, NULL, '', NULL, NULL, NULL, 0, NULL, 'nouvelle', 'normale', NULL, NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, '', 0.00, 'attente', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-09-04 14:06:16', '2025-09-04 14:06:16', 13, 15, 'Champroux Stadium, Abidjan, CÃ´te d\'Ivoire', NULL, NULL, 'Sipim Atlantide PORT-BOUÃ‹T, Abidjan, CÃ´te d\'Ivoire', NULL, NULL, '+2250758842029', '+2250102453769'),
(39, 'SZK202509049d6e98', 'SZK250904128129', 'particulier', 1, NULL, NULL, '', '', NULL, NULL, '', NULL, NULL, '', NULL, NULL, NULL, 0, NULL, 'nouvelle', 'normale', NULL, NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, '', 0.00, 'attente', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-09-04 14:42:44', '2025-09-04 14:42:44', 13, 15, 'Champroux Stadium, Abidjan, CÃ´te d\'Ivoire', NULL, NULL, 'Sipim Atlantide PORT-BOUÃ‹T, Abidjan, CÃ´te d\'Ivoire', NULL, NULL, '+2250758842029', '+2250102453769'),
(40, 'SZK20250904dfec60', 'SZK250904912573', 'particulier', 1, NULL, NULL, '', '', NULL, NULL, '', NULL, NULL, '', NULL, NULL, NULL, 0, NULL, 'nouvelle', 'normale', NULL, NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, '', 0.00, 'attente', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-09-04 15:22:46', '2025-09-04 15:22:46', 13, 15, 'Champroux Stadium, Abidjan, CÃ´te d\'Ivoire', NULL, NULL, 'Sipim Atlantide PORT-BOUÃ‹T, Abidjan, CÃ´te d\'Ivoire', NULL, NULL, '+2250758842029', '+2250102453769'),
(41, 'SZK202509047656d0', 'SZK250904566350', 'particulier', 1, NULL, NULL, '', '', NULL, NULL, '', NULL, NULL, '', NULL, NULL, NULL, 0, NULL, 'nouvelle', 'normale', NULL, NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, '', 0.00, 'attente', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-09-04 15:24:45', '2025-09-04 15:24:45', 13, 15, 'Champroux Stadium, Abidjan, CÃ´te d\'Ivoire', NULL, NULL, 'Sipim Atlantide PORT-BOUÃ‹T, Abidjan, CÃ´te d\'Ivoire', NULL, NULL, '+2250758842029', '+2250102453769'),
(42, 'SZK202509044087a6', 'SZK250904340303', 'particulier', 1, NULL, NULL, '', '', NULL, NULL, '', NULL, NULL, '', NULL, NULL, NULL, 0, NULL, 'nouvelle', 'normale', NULL, NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, '', 0.00, 'attente', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-09-04 15:34:18', '2025-09-04 15:34:18', 13, 15, 'Champroux Stadium, Abidjan, CÃ´te d\'Ivoire', NULL, NULL, 'Sipim Atlantide PORT-BOUÃ‹T, Abidjan, CÃ´te d\'Ivoire', NULL, NULL, '+2250758842029', '+2250102453769'),
(43, 'SZK20250904d8fc73', 'SZK250904989811', 'particulier', 1, NULL, NULL, '', '', NULL, NULL, '', NULL, NULL, '', NULL, NULL, NULL, 0, NULL, 'nouvelle', 'normale', NULL, NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, '', 0.00, 'attente', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-09-04 16:10:35', '2025-09-04 16:10:35', 13, 15, 'Champroux Stadium, Abidjan, CÃ´te d\'Ivoire', NULL, NULL, 'Sipim Atlantide PORT-BOUÃ‹T, Abidjan, CÃ´te d\'Ivoire', NULL, NULL, '+2250758842029', '+2250102453769'),
(44, 'SZK2025090486bff8', 'SZK250904280870', 'particulier', 1, NULL, NULL, '', '', NULL, NULL, '', NULL, NULL, '', NULL, NULL, NULL, 0, NULL, 'nouvelle', 'normale', NULL, NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, '', 0.00, 'attente', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-09-04 16:18:46', '2025-09-04 16:18:46', 13, 15, 'Champroux Stadium, Abidjan, CÃ´te d\'Ivoire', NULL, NULL, 'Sipim Atlantide PORT-BOUÃ‹T, Abidjan, CÃ´te d\'Ivoire', NULL, NULL, '+2250758842029', '+2250102453769'),
(45, 'SZK2025090487d6f5', 'SZK250904394884', 'particulier', 1, NULL, NULL, '', '', NULL, NULL, '', NULL, NULL, '', NULL, NULL, NULL, 0, NULL, 'nouvelle', 'normale', NULL, NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, '', 0.00, 'attente', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-09-04 16:27:45', '2025-09-04 16:27:45', 13, 15, 'Champroux Stadium, Abidjan, CÃ´te d\'Ivoire', NULL, NULL, 'Sipim Atlantide PORT-BOUÃ‹T, Abidjan, CÃ´te d\'Ivoire', NULL, NULL, '+2250758842029', '+2250102453769'),
(46, 'SZK202509047c2e1d', 'SZK250904417150', 'particulier', 1, NULL, NULL, '', '', NULL, NULL, '', NULL, NULL, '', NULL, NULL, NULL, 0, NULL, 'nouvelle', 'normale', NULL, NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, '', 6400.00, 'attente', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-09-04 17:57:16', '2025-09-04 17:57:16', 13, 15, 'Champroux Stadium, Abidjan, CÃ´te d\'Ivoire', NULL, NULL, 'Sipim Atlantide PORT-BOUÃ‹T, Abidjan, CÃ´te d\'Ivoire', NULL, NULL, '+2250758842029', '+2250102453769'),
(47, 'SZK20250904b50001', 'SZK250904420793', 'particulier', 1, NULL, NULL, '', '', NULL, NULL, '', NULL, NULL, '', NULL, NULL, NULL, 0, NULL, 'nouvelle', 'normale', NULL, NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, '', 6400.00, 'attente', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-09-04 18:03:20', '2025-09-04 18:03:20', 13, 15, 'Champroux Stadium, Abidjan, CÃ´te d\'Ivoire', NULL, NULL, 'Sipim Atlantide PORT-BOUÃ‹T, Abidjan, CÃ´te d\'Ivoire', NULL, NULL, '+2250758842029', '+2250102453769'),
(48, 'SZK202509043f5c95', 'SZK250904100350', 'particulier', 1, NULL, NULL, '', '', NULL, NULL, '', NULL, NULL, '', NULL, NULL, NULL, 0, NULL, 'nouvelle', 'normale', NULL, NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, '', 6400.00, 'attente', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-09-04 18:05:35', '2025-09-04 18:05:35', 13, 15, 'Champroux Stadium, Abidjan, CÃ´te d\'Ivoire', NULL, NULL, 'Sipim Atlantide PORT-BOUÃ‹T, Abidjan, CÃ´te d\'Ivoire', NULL, NULL, '+2250758842029', '+2250102453769'),
(49, 'SZK20250904efaabf', 'SZK250904789369', 'particulier', 1, NULL, NULL, '', '', NULL, NULL, '', NULL, NULL, '', NULL, NULL, NULL, 0, NULL, 'nouvelle', 'normale', NULL, NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, '', 6400.00, 'attente', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-09-04 18:23:12', '2025-09-04 18:23:12', 13, 15, 'Champroux Stadium, Abidjan, CÃ´te d\'Ivoire', NULL, NULL, 'Sipim Atlantide PORT-BOUÃ‹T, Abidjan, CÃ´te d\'Ivoire', NULL, NULL, '+2250758842029', '+2250102453769'),
(50, 'SZK202509043b0367', 'SZK250904401561', 'particulier', 1, NULL, NULL, '', '', NULL, NULL, '', NULL, NULL, '', NULL, NULL, NULL, 0, NULL, 'nouvelle', 'normale', NULL, NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, '', 6400.00, 'attente', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-09-04 18:24:07', '2025-09-04 18:24:07', 13, 15, 'Champroux Stadium, Abidjan, CÃ´te d\'Ivoire', NULL, NULL, 'Sipim Atlantide PORT-BOUÃ‹T, Abidjan, CÃ´te d\'Ivoire', NULL, NULL, '+2250758842029', '+2250102453769'),
(51, 'SZK20250904c340b9', 'SZK250904770527', 'particulier', 1, NULL, NULL, '', '', NULL, NULL, '', NULL, NULL, '', NULL, NULL, NULL, 0, NULL, 'nouvelle', 'normale', NULL, NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, '', 6400.00, 'attente', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-09-04 18:26:57', '2025-09-04 18:26:57', 13, 15, 'Champroux Stadium, Abidjan, CÃ´te d\'Ivoire', NULL, NULL, 'Sipim Atlantide PORT-BOUÃ‹T, Abidjan, CÃ´te d\'Ivoire', NULL, NULL, '+2250758842029', '+2250102453769'),
(53, 'SZK202509040e1697', 'SZK250904504953', 'particulier', 1, NULL, NULL, '', '', NULL, NULL, '', NULL, NULL, '', NULL, NULL, NULL, 0, NULL, 'nouvelle', 'normale', NULL, NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, '', 6400.00, 'attente', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-09-04 18:30:29', '2025-09-04 18:30:29', 13, 15, 'Champroux Stadium, Abidjan, CÃ´te d\'Ivoire', NULL, NULL, 'Sipim Atlantide PORT-BOUÃ‹T, Abidjan, CÃ´te d\'Ivoire', NULL, NULL, '+2250758842029', '+2250102453769'),
(54, 'SZK20250904dbb7c3', 'SZK250904811969', 'particulier', 1, NULL, NULL, '', '', NULL, NULL, '', NULL, NULL, '', NULL, NULL, NULL, 0, NULL, 'nouvelle', 'normale', NULL, NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, '', 6400.00, 'attente', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-09-04 18:32:42', '2025-09-04 18:32:42', 13, 15, 'Champroux Stadium, Abidjan, CÃ´te d\'Ivoire', NULL, NULL, 'Sipim Atlantide PORT-BOUÃ‹T, Abidjan, CÃ´te d\'Ivoire', NULL, NULL, '+2250758842029', '+2250102453769'),
(55, 'SZK202509045992d7', 'SZK250904546863', 'particulier', 1, NULL, NULL, '', '', NULL, NULL, '', NULL, NULL, '', NULL, NULL, NULL, 0, NULL, 'nouvelle', 'normale', NULL, NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, '', 6400.00, 'attente', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-09-04 18:33:42', '2025-09-04 18:33:42', 13, 15, 'Champroux Stadium, Abidjan, CÃ´te d\'Ivoire', NULL, NULL, 'Sipim Atlantide PORT-BOUÃ‹T, Abidjan, CÃ´te d\'Ivoire', NULL, NULL, '+2250758842029', '+2250102453769'),
(56, 'SZK20250904d43fde', 'SZK250904424431', 'particulier', 1, NULL, NULL, '', '', NULL, NULL, '', NULL, NULL, '', NULL, NULL, NULL, 0, NULL, 'nouvelle', 'normale', NULL, NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, '', 6400.00, 'attente', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-09-04 18:37:15', '2025-09-04 18:37:15', 13, 15, 'Champroux Stadium, Abidjan, CÃ´te d\'Ivoire', NULL, NULL, 'Sipim Atlantide PORT-BOUÃ‹T, Abidjan, CÃ´te d\'Ivoire', NULL, NULL, '+2250758842029', '+2250102453769'),
(57, 'SZK20250904c7da25', 'SZK250904179379', 'particulier', 1, NULL, NULL, '', '', NULL, NULL, '', NULL, NULL, '', NULL, NULL, NULL, 0, NULL, 'nouvelle', 'normale', NULL, NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, '', 6400.00, 'attente', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-09-04 18:37:33', '2025-09-04 18:37:33', 13, 15, 'Champroux Stadium, Abidjan, CÃ´te d\'Ivoire', NULL, NULL, 'Sipim Atlantide PORT-BOUÃ‹T, Abidjan, CÃ´te d\'Ivoire', NULL, NULL, '+2250758842029', '+2250102453769'),
(58, 'SZK20250904926449', 'SZK250904597415', 'particulier', 1, NULL, NULL, '', '', NULL, NULL, '', NULL, NULL, '', NULL, NULL, NULL, 0, NULL, 'nouvelle', 'normale', NULL, NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, '', 6400.00, 'attente', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-09-04 18:38:06', '2025-09-04 18:38:06', 13, 15, 'Champroux Stadium, Abidjan, CÃ´te d\'Ivoire', NULL, NULL, 'Sipim Atlantide PORT-BOUÃ‹T, Abidjan, CÃ´te d\'Ivoire', NULL, NULL, '+2250758842029', '+2250102453769'),
(59, 'SZK2025090432ab7d', 'SZK250904510492', 'particulier', 1, NULL, NULL, '', '', NULL, NULL, '', NULL, NULL, '', NULL, NULL, NULL, 0, NULL, 'nouvelle', 'normale', NULL, NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, '', 6400.00, 'attente', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-09-04 18:52:48', '2025-09-04 18:52:48', 13, 15, 'Champroux Stadium, Abidjan, CÃ´te d\'Ivoire', NULL, NULL, 'Sipim Atlantide PORT-BOUÃ‹T, Abidjan, CÃ´te d\'Ivoire', NULL, NULL, '+2250758842029', '+2250102453769'),
(60, 'SZK202509043f948f', 'SZK250904670906', 'particulier', 1, NULL, NULL, '', '', NULL, NULL, '', NULL, NULL, '', NULL, NULL, NULL, 0, NULL, 'nouvelle', 'normale', NULL, NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, '', 6400.00, 'attente', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-09-04 19:03:41', '2025-09-04 19:03:41', 13, 15, 'Champroux Stadium, Abidjan, CÃ´te d\'Ivoire', NULL, NULL, 'Sipim Atlantide PORT-BOUÃ‹T, Abidjan, CÃ´te d\'Ivoire', NULL, NULL, '+2250758842029', '+2250102453769'),
(61, 'SZK202509041156a3', 'SZK250904463128', 'particulier', 1, NULL, NULL, '', '', NULL, NULL, '', NULL, NULL, '', NULL, NULL, NULL, 0, NULL, 'nouvelle', 'normale', NULL, NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, '', 6400.00, 'attente', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-09-04 19:14:38', '2025-09-04 19:14:38', 13, 15, 'Champroux Stadium, Abidjan, CÃ´te d\'Ivoire', NULL, NULL, 'Sipim Atlantide PORT-BOUÃ‹T, Abidjan, CÃ´te d\'Ivoire', NULL, NULL, '+2250758842029', '+2250102453769'),
(62, 'SZK202509043e090f', 'SZK250904175678', 'particulier', 1, NULL, NULL, '', '', NULL, NULL, '', NULL, NULL, '', NULL, NULL, NULL, 0, NULL, 'nouvelle', 'normale', NULL, NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, '', 6400.00, 'attente', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-09-04 19:16:24', '2025-09-04 19:16:24', 13, 15, 'Champroux Stadium, Abidjan, CÃ´te d\'Ivoire', NULL, NULL, 'Sipim Atlantide PORT-BOUÃ‹T, Abidjan, CÃ´te d\'Ivoire', NULL, NULL, '+2250758842029', '+2250102453769'),
(63, 'SZK202509047886be', 'SZK250904352019', 'particulier', 1, NULL, NULL, '', '', NULL, NULL, '', NULL, NULL, '', NULL, NULL, NULL, 0, NULL, 'nouvelle', 'normale', NULL, NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, '', 6400.00, 'attente', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-09-04 19:20:56', '2025-09-04 19:20:56', 155, 156, 'Champroux Stadium, Abidjan, CÃ´te d\'Ivoire', NULL, NULL, 'Sipim Atlantide PORT-BOUÃ‹T, Abidjan, CÃ´te d\'Ivoire', NULL, NULL, '+2252250758842', '+2252250575584'),
(64, 'SZK20250904555505', 'SZK250904609929', 'particulier', 1, NULL, NULL, '', '', NULL, NULL, '', NULL, NULL, '', NULL, NULL, NULL, 0, NULL, 'nouvelle', 'normale', NULL, NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, '', 6400.00, 'attente', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-09-04 19:22:13', '2025-09-04 19:22:13', 13, 15, 'Champroux Stadium, Abidjan, CÃ´te d\'Ivoire', NULL, NULL, 'Sipim Atlantide PORT-BOUÃ‹T, Abidjan, CÃ´te d\'Ivoire', NULL, NULL, '+2250758842029', '+2250102453769'),
(65, 'SZK20250904a37b85', 'SZK250904351725', 'particulier', 1, NULL, NULL, '', '', NULL, NULL, '', NULL, NULL, '', NULL, NULL, NULL, 0, NULL, 'nouvelle', 'normale', NULL, NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, '', 6400.00, 'attente', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-09-04 19:32:38', '2025-09-04 19:32:38', 13, 15, 'Champroux Stadium, Abidjan, CÃ´te d\'Ivoire', NULL, NULL, 'Sipim Atlantide PORT-BOUÃ‹T, Abidjan, CÃ´te d\'Ivoire', NULL, NULL, '+2250758842029', '+2250102453769'),
(66, 'SZK2025090476116a', 'SZK250904458738', 'particulier', 1, NULL, NULL, '', '', NULL, NULL, '', NULL, NULL, '', NULL, NULL, NULL, 0, NULL, 'nouvelle', 'normale', NULL, NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, '', 6400.00, 'attente', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-09-04 19:41:24', '2025-09-04 19:41:24', 161, 15, 'Champroux Stadium, Abidjan, CÃ´te d\'Ivoire', NULL, NULL, 'Sipim Atlantide PORT-BOUÃ‹T, Abidjan, CÃ´te d\'Ivoire', NULL, NULL, '+2250758853029', '+2250102453769'),
(67, 'SZK20250904570f95', 'SZK250904775624', 'particulier', 1, NULL, NULL, '', '', NULL, NULL, '', NULL, NULL, '', NULL, NULL, NULL, 0, NULL, 'nouvelle', 'normale', NULL, NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, '', 8320.00, 'attente', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-09-04 20:03:46', '2025-09-04 20:03:46', 13, 15, 'Champroux Stadium, Abidjan, CÃ´te d\'Ivoire', NULL, NULL, 'Sipim Atlantide PORT-BOUÃ‹T, Abidjan, CÃ´te d\'Ivoire', NULL, NULL, '+2250758842029', '+2250102453769'),
(68, 'SZK2025090480483d', 'SZK250904233178', 'particulier', 1, NULL, NULL, '', '', NULL, NULL, '', NULL, NULL, '', NULL, NULL, NULL, 0, NULL, 'nouvelle', 'normale', NULL, NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, '', 8320.00, 'attente', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-09-04 20:07:19', '2025-09-04 20:07:19', 13, 15, 'Champroux Stadium, Abidjan, CÃ´te d\'Ivoire', NULL, NULL, 'Sipim Atlantide PORT-BOUÃ‹T, Abidjan, CÃ´te d\'Ivoire', NULL, NULL, '+2250758842029', '+2250102453769'),
(69, 'SZK20250904ac79e4', 'SZK250904257979', 'particulier', 1, NULL, NULL, '', '', NULL, NULL, '', NULL, NULL, '', NULL, NULL, NULL, 0, NULL, 'nouvelle', 'normale', NULL, NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, '', 8320.00, 'attente', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-09-04 20:13:44', '2025-09-04 20:13:44', 13, 15, 'Champroux Stadium, Abidjan, CÃ´te d\'Ivoire', NULL, NULL, 'Sipim Atlantide PORT-BOUÃ‹T, Abidjan, CÃ´te d\'Ivoire', NULL, NULL, '+2250758842029', '+2250102453769'),
(70, 'SZK202509041d0a5c', 'SZK250904310764', 'particulier', 1, NULL, NULL, '', '', NULL, NULL, '', NULL, NULL, '', NULL, NULL, NULL, 0, NULL, 'nouvelle', 'normale', NULL, NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, '', 8320.00, 'attente', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-09-04 20:16:27', '2025-09-04 20:16:27', 13, 15, 'Champroux Stadium, Abidjan, CÃ´te d\'Ivoire', NULL, NULL, 'Sipim Atlantide PORT-BOUÃ‹T, Abidjan, CÃ´te d\'Ivoire', NULL, NULL, '+2250758842029', '+2250102453769'),
(71, 'SZK2025090429b498', 'SZK250904869870', 'particulier', 1, NULL, NULL, '', '', NULL, NULL, '', NULL, NULL, '', NULL, NULL, NULL, 0, NULL, 'livree', 'normale', NULL, NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, '', 8320.00, 'attente', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-09-04 20:18:25', '2025-09-26 14:18:16', 13, 15, 'Champroux Stadium, Abidjan, CÃ´te d\'Ivoire', NULL, NULL, 'Sipim Atlantide PORT-BOUÃ‹T, Abidjan, CÃ´te d\'Ivoire', NULL, NULL, '+2250758842029', '+2250102453769'),
(72, 'SZK20250921594571', 'SZK250921225224', 'particulier', 1, NULL, NULL, '', '', NULL, NULL, '', NULL, NULL, '', NULL, NULL, NULL, 0, NULL, 'livree', 'normale', NULL, NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, '', 0.00, 'attente', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-09-21 08:24:00', '2025-09-26 14:17:47', 3, 1, 'Champroux Stadium, Abidjan, CÃ´te d\'Ivoire', NULL, NULL, 'Sipim Atlantide PORT-BOUÃ‹T, Abidjan, CÃ´te d\'Ivoire', NULL, NULL, '+225 01 02 45 37 69', '+225 07 58 84 20 29'),
(76, 'SZK202509218af379', 'SZK250921769695', 'particulier', 1, NULL, NULL, '', '', NULL, NULL, '', NULL, NULL, '', NULL, NULL, NULL, 0, NULL, 'livree', '', NULL, NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, '', 0.00, 'attente', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-09-21 09:00:32', '2025-09-26 06:15:32', 11, 12, 'Plateau, Abidjan', NULL, NULL, 'Marcory, Abidjan', NULL, NULL, '2250102030405', '2250504030201'),
(77, 'SZK20250921c5f13c', 'SZK250921496162', 'particulier', 1, NULL, NULL, '', '', NULL, NULL, '', NULL, NULL, '', NULL, NULL, NULL, 0, NULL, 'livree', '', NULL, NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, '', 0.00, 'attente', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-09-21 09:00:38', '2025-09-26 14:17:36', 11, 12, 'Plateau, Abidjan', NULL, NULL, 'Marcory, Abidjan', NULL, NULL, '2250102030405', '2250504030201'),
(78, 'SZK2025092132be3c', 'SZK250921861842', 'particulier', 15, NULL, NULL, '', '', NULL, NULL, '', NULL, NULL, '', NULL, NULL, NULL, 0, NULL, 'livree', 'normale', NULL, NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, '', 0.00, 'attente', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-09-21 17:31:37', '2025-09-26 14:16:48', 15, 16, 'Champroux Stadium, Abidjan, CÃ´te d\'Ivoire', NULL, NULL, 'Sipim Atlantide PORT-BOUÃ‹T, Abidjan, CÃ´te d\'Ivoire', NULL, NULL, '2250758842029', '2250102453769'),
(79, 'SZK20250926e44b9b', 'SZK250926898369', 'particulier', 1, NULL, NULL, '', '', NULL, NULL, '', NULL, NULL, '', NULL, NULL, NULL, 0, NULL, 'nouvelle', 'normale', NULL, NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, '', 0.00, 'attente', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-09-26 15:05:57', '2025-09-26 15:05:57', 16, 15, 'Champroux Stadium, Abidjan, CÃ´te d\'Ivoire', NULL, NULL, 'Sipim Atlantide PORT-BOUÃ‹T, Abidjan, CÃ´te d\'Ivoire', NULL, NULL, '2250102453769', '2250758842029'),
(80, 'SZK20250926f401d9', 'SZK250926839870', 'particulier', 1, NULL, NULL, '', '', NULL, NULL, '', NULL, NULL, '', NULL, NULL, NULL, 0, NULL, 'nouvelle', 'normale', NULL, NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, '', 2923.00, 'attente', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-09-26 15:13:21', '2025-09-26 15:13:21', 16, 15, 'Champroux Stadium, Abidjan, CÃ´te d\'Ivoire', NULL, NULL, 'Sipim Atlantide PORT-BOUÃ‹T, Abidjan, CÃ´te d\'Ivoire', NULL, NULL, '2250102453769', '2250758842029'),
(81, 'SZK20250926878ed8', 'SZK250926501773', 'particulier', 1, NULL, NULL, '', '', NULL, NULL, '', NULL, NULL, '', NULL, NULL, NULL, 0, NULL, 'nouvelle', 'normale', NULL, NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, '', 0.00, 'attente', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-09-26 15:13:32', '2025-09-26 15:13:32', 16, 15, 'Champroux Stadium, Abidjan, CÃ´te d\'Ivoire', NULL, NULL, 'Sipim Atlantide PORT-BOUÃ‹T, Abidjan, CÃ´te d\'Ivoire', NULL, NULL, '2250102453769', '2250758842029'),
(82, 'SZK20250926d7acf5', 'SZK250926132244', 'particulier', 1, NULL, NULL, '', '', NULL, NULL, '', NULL, NULL, '', NULL, NULL, NULL, 0, NULL, 'nouvelle', 'normale', NULL, NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, '', 6713.00, 'attente', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-09-26 15:31:13', '2025-09-26 15:31:13', 16, 15, 'Chawarma+ 8e Tranche, Abidjan, CÃ´te d\'Ivoire', NULL, NULL, 'Sipim Atlantide PORT-BOUÃ‹T, Abidjan, CÃ´te d\'Ivoire', NULL, NULL, '2250102453769', '2250758842029'),
(83, 'SZK202509264f8bc4', 'SZK250926321812', 'particulier', 1, NULL, NULL, '', '', NULL, NULL, '', NULL, NULL, '', NULL, NULL, NULL, 0, NULL, 'nouvelle', 'normale', NULL, NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, '', 0.00, 'attente', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-09-26 15:54:21', '2025-09-26 15:54:21', 16, 15, 'Chawarma+ 8e Tranche, Abidjan, CÃ´te d\'Ivoire', NULL, NULL, 'Sipim Atlantide PORT-BOUÃ‹T, Abidjan, CÃ´te d\'Ivoire', NULL, NULL, '2250102453769', '2250758842029'),
(84, 'SZK202509263fd3ea', 'SZK250926750430', 'particulier', 1, NULL, NULL, '', '', NULL, NULL, '', NULL, NULL, '', NULL, NULL, NULL, 0, NULL, 'nouvelle', 'normale', NULL, NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, '', 2923.00, 'attente', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-09-26 15:54:59', '2025-09-26 15:54:59', 16, 15, 'Champroux Stadium, Abidjan, CÃ´te d\'Ivoire', NULL, NULL, 'Sipim Atlantide PORT-BOUÃ‹T, Abidjan, CÃ´te d\'Ivoire', NULL, NULL, '2250102453769', '2250758842029'),
(85, 'SZK202509264a5b69', 'SZK250926994138', 'particulier', 1, NULL, NULL, '', '', NULL, NULL, '', NULL, NULL, '', NULL, NULL, NULL, 0, NULL, 'nouvelle', 'normale', NULL, NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, '', 0.00, 'attente', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-09-26 15:55:04', '2025-09-26 15:55:04', 16, 15, 'Champroux Stadium, Abidjan, CÃ´te d\'Ivoire', NULL, NULL, 'Sipim Atlantide PORT-BOUÃ‹T, Abidjan, CÃ´te d\'Ivoire', NULL, NULL, '2250102453769', '2250758842029'),
(86, 'SZK20250926e90316', 'SZK250926194716', 'particulier', 1, NULL, NULL, '', '', NULL, NULL, '', NULL, NULL, '', NULL, NULL, NULL, 0, NULL, 'nouvelle', 'normale', NULL, NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, '', 2924.00, 'attente', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-09-26 16:19:17', '2025-09-26 16:19:17', 16, 15, 'Champroux Stadium, Abidjan, CÃ´te d\'Ivoire', NULL, NULL, 'Sipim Atlantide PORT-BOUÃ‹T, Abidjan, CÃ´te d\'Ivoire', NULL, NULL, '2250102453769', '2250758842029'),
(87, 'SZK20250926dacafb', 'SZK250926258726', 'particulier', 1, NULL, NULL, '', '', NULL, NULL, '', NULL, NULL, '', NULL, NULL, NULL, 0, NULL, 'nouvelle', 'normale', NULL, NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, '', 0.00, 'attente', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-09-26 16:19:29', '2025-09-26 16:19:29', 16, 15, 'Champroux Stadium, Abidjan, CÃ´te d\'Ivoire', NULL, NULL, 'Sipim Atlantide PORT-BOUÃ‹T, Abidjan, CÃ´te d\'Ivoire', NULL, NULL, '2250102453769', '2250758842029'),
(88, 'SZK202509263c3373', 'SZK250926337004', 'particulier', 1, NULL, NULL, '', '', NULL, NULL, '', NULL, NULL, '', NULL, NULL, NULL, 0, NULL, 'nouvelle', 'normale', NULL, NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, '', 0.00, 'attente', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-09-26 16:31:55', '2025-09-26 16:31:55', 16, 15, 'Champroux Stadium, Abidjan, CÃ´te d\'Ivoire', NULL, NULL, 'Sipim Atlantide PORT-BOUÃ‹T, Abidjan, CÃ´te d\'Ivoire', NULL, NULL, '2250102453769', '2250758842029'),
(89, 'SZK20250926664679', 'SZK250926855180', 'particulier', 1, NULL, NULL, '', '', NULL, NULL, '', NULL, NULL, '', NULL, NULL, NULL, 0, NULL, 'nouvelle', 'normale', NULL, NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, '', 2923.00, 'attente', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-09-26 16:32:27', '2025-09-26 16:32:27', 16, 15, 'Champroux Stadium, Abidjan, CÃ´te d\'Ivoire', NULL, NULL, 'Sipim Atlantide PORT-BOUÃ‹T, Abidjan, CÃ´te d\'Ivoire', NULL, NULL, '2250102453769', '2250758842029'),
(90, 'SZK202509267e8c10', 'SZK250926543250', 'particulier', 1, NULL, NULL, '', '', NULL, NULL, '', NULL, NULL, '', NULL, NULL, NULL, 0, NULL, 'nouvelle', 'normale', NULL, NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, '', 0.00, 'attente', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-09-26 16:32:39', '2025-09-26 16:32:39', 16, 15, 'Champroux Stadium, Abidjan, CÃ´te d\'Ivoire', NULL, NULL, 'Sipim Atlantide PORT-BOUÃ‹T, Abidjan, CÃ´te d\'Ivoire', NULL, NULL, '2250102453769', '2250758842029'),
(91, 'SZK202509266d7e4b', 'SZK250926883754', 'particulier', 1, NULL, NULL, '', '', NULL, NULL, '', NULL, NULL, '', NULL, NULL, NULL, 0, NULL, 'nouvelle', 'normale', NULL, NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, '', 2921.00, 'attente', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-09-26 16:39:50', '2025-09-26 16:39:50', 16, 15, 'Champroux Stadium, Abidjan, CÃ´te d\'Ivoire', NULL, NULL, 'Sipim Atlantide PORT-BOUÃ‹T, Abidjan, CÃ´te d\'Ivoire', NULL, NULL, '2250102453769', '2250758842029'),
(92, 'SZK2025092621aef9', 'SZK250926279685', 'particulier', 1, NULL, NULL, '', '', NULL, NULL, '', NULL, NULL, '', NULL, NULL, NULL, 0, NULL, 'nouvelle', 'normale', NULL, NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, '', 2923.00, 'attente', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-09-26 17:01:22', '2025-09-26 17:01:22', 16, 15, 'Champroux Stadium, Abidjan, CÃ´te d\'Ivoire', NULL, NULL, 'Sipim Atlantide PORT-BOUÃ‹T, Abidjan, CÃ´te d\'Ivoire', NULL, NULL, '2250102453769', '2250758842029'),
(93, 'SZK20250926c8c987', 'SZK250926854980', 'particulier', 1, NULL, NULL, '', '', NULL, NULL, '', NULL, NULL, '', NULL, NULL, NULL, 0, NULL, 'nouvelle', 'normale', NULL, NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, '', 2923.00, 'attente', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-09-26 17:16:47', '2025-09-26 17:16:47', 16, 15, 'Champroux Stadium, Abidjan, CÃ´te d\'Ivoire', NULL, NULL, 'Sipim Atlantide PORT-BOUÃ‹T, Abidjan, CÃ´te d\'Ivoire', NULL, NULL, '2250102453769', '2250758842029'),
(94, 'SZK20250926b6b815', 'SZK250926640512', 'particulier', 1, NULL, NULL, '', '', NULL, NULL, '', NULL, NULL, '', NULL, NULL, NULL, 0, NULL, 'nouvelle', 'normale', NULL, NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, '', 2923.00, 'attente', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-09-26 17:16:52', '2025-09-26 17:16:52', 16, 15, 'Champroux Stadium, Abidjan, CÃ´te d\'Ivoire', NULL, NULL, 'Sipim Atlantide PORT-BOUÃ‹T, Abidjan, CÃ´te d\'Ivoire', NULL, NULL, '2250102453769', '2250758842029'),
(95, 'SZK20250926d2ec12', 'SZK250926452490', 'particulier', 1, NULL, NULL, '', '', NULL, NULL, '', NULL, NULL, '', NULL, NULL, NULL, 0, NULL, 'nouvelle', 'normale', NULL, NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, '', 3394.00, 'attente', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-09-26 17:57:12', '2025-09-26 17:57:12', 16, 15, 'Champroux Stadium, Abidjan, CÃ´te d\'Ivoire', NULL, NULL, 'Sipim Atlantide PORT-BOUÃ‹T, Abidjan, CÃ´te d\'Ivoire', NULL, NULL, '2250102453769', '2250758842029'),
(96, 'SZK202509265a8198', 'SZK250926662057', 'particulier', 1, NULL, 'ClientExp3769', '2250102453769', 'Champroux Stadium, Abidjan, CÃ´te d\'Ivoire', 5.30509750, -3.99246690, 'Sipim Atlantide PORT-BOUÃ‹T, Abidjan, CÃ´te d\'Ivoire', 5.25082460, -3.94498370, '', NULL, NULL, NULL, 0, NULL, 'nouvelle', 'normale', NULL, NULL, NULL, NULL, NULL, 3394.00, 0.00, 3394.00, 'especes', 3394.00, 'attente', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-09-26 18:34:16', '2025-09-26 18:34:16', 16, 15, 'Champroux Stadium, Abidjan, CÃ´te d\'Ivoire', NULL, NULL, 'Sipim Atlantide PORT-BOUÃ‹T, Abidjan, CÃ´te d\'Ivoire', NULL, NULL, '2250102453769', '2250758842029'),
(97, 'SZK2025092686c1cf', 'SZK250926361174', 'particulier', 16, NULL, 'ClientExp3769', '2250102453769', 'Champroux Stadium, Abidjan, CÃ´te d\'Ivoire', 5.30509750, -3.99246690, 'Sipim Atlantide PORT-BOUÃ‹T, Abidjan, CÃ´te d\'Ivoire', 5.25082460, -3.94498370, '', NULL, NULL, NULL, 0, NULL, 'nouvelle', 'normale', NULL, NULL, NULL, NULL, NULL, 3448.00, 0.00, 3448.00, 'especes', 3448.00, 'attente', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-09-26 18:45:52', '2025-09-26 18:45:52', 16, 15, 'Champroux Stadium, Abidjan, CÃ´te d\'Ivoire', NULL, NULL, 'Sipim Atlantide PORT-BOUÃ‹T, Abidjan, CÃ´te d\'Ivoire', NULL, NULL, '2250102453769', '2250758842029'),
(98, 'SZK2025092651d96f', 'SZK250926245028', 'particulier', 16, NULL, 'ClientExp3769', '2250102453769', 'Champroux Stadium, Abidjan, CÃ´te d\'Ivoire', 5.30509750, -3.99246690, 'Sipim Atlantide PORT-BOUÃ‹T, Abidjan, CÃ´te d\'Ivoire', 5.25082460, -3.94498370, '', NULL, NULL, NULL, 0, NULL, 'nouvelle', 'normale', NULL, NULL, NULL, NULL, NULL, 2923.00, 0.00, 2923.00, 'especes', 2923.00, 'attente', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-09-26 20:24:46', '2025-09-26 20:24:46', 16, 15, 'Champroux Stadium, Abidjan, CÃ´te d\'Ivoire', NULL, NULL, 'Sipim Atlantide PORT-BOUÃ‹T, Abidjan, CÃ´te d\'Ivoire', NULL, NULL, '2250102453769', '2250758842029'),
(99, 'SZK20250926ce55e3', 'SZK250926526667', 'particulier', 16, NULL, 'ClientExp3769', '2250102453769', 'Champroux Stadium, Abidjan, CÃ´te d\'Ivoire', 5.30509750, -3.99246690, 'Sipim Atlantide PORT-BOUÃ‹T, Abidjan, CÃ´te d\'Ivoire', 5.25082460, -3.94498370, '', NULL, NULL, NULL, 0, NULL, 'nouvelle', 'normale', NULL, NULL, NULL, NULL, NULL, 2923.00, 0.00, 2923.00, 'especes', 2923.00, 'attente', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-09-26 20:40:21', '2025-09-26 20:40:21', 16, 15, 'Champroux Stadium, Abidjan, CÃ´te d\'Ivoire', NULL, NULL, 'Sipim Atlantide PORT-BOUÃ‹T, Abidjan, CÃ´te d\'Ivoire', NULL, NULL, '2250102453769', '2250758842029'),
(100, 'SZK2025092661dccb', 'SZK250926251749', 'particulier', 16, NULL, 'ClientExp3769', '2250102453769', 'Champroux Stadium, Abidjan, CÃ´te d\'Ivoire', 5.30509750, -3.99246690, 'Sipim Atlantide PORT-BOUÃ‹T, Abidjan, CÃ´te d\'Ivoire', 5.25082460, -3.94498370, '', NULL, NULL, NULL, 0, NULL, 'nouvelle', 'normale', NULL, NULL, NULL, NULL, NULL, 2923.00, 0.00, 2923.00, 'especes', 2923.00, 'attente', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-09-26 20:40:27', '2025-09-26 20:40:27', 16, 15, 'Champroux Stadium, Abidjan, CÃ´te d\'Ivoire', NULL, NULL, 'Sipim Atlantide PORT-BOUÃ‹T, Abidjan, CÃ´te d\'Ivoire', NULL, NULL, '2250102453769', '2250758842029'),
(101, 'SZK20250926e41853', 'SZK250926282338', 'particulier', 16, NULL, 'ClientExp3769', '2250102453769', 'Champroux Stadium, Abidjan, CÃ´te d\'Ivoire', 5.30509750, -3.99246690, 'Sipim Atlantide PORT-BOUÃ‹T, Abidjan, CÃ´te d\'Ivoire', 5.25082460, -3.94498370, '', NULL, NULL, NULL, 0, NULL, 'nouvelle', 'normale', NULL, NULL, NULL, NULL, NULL, 2923.00, 0.00, 2923.00, 'especes', 2923.00, 'attente', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-09-26 20:47:46', '2025-09-26 20:47:46', 16, 15, 'Champroux Stadium, Abidjan, CÃ´te d\'Ivoire', NULL, NULL, 'Sipim Atlantide PORT-BOUÃ‹T, Abidjan, CÃ´te d\'Ivoire', NULL, NULL, '2250102453769', '2250758842029'),
(102, 'SZK20250926556783', 'SZK250926627155', 'particulier', 16, NULL, 'ClientExp3769', '2250102453769', 'Champroux Stadium, Abidjan, CÃ´te d\'Ivoire', 5.30509750, -3.99246690, 'Sipim Atlantide PORT-BOUÃ‹T, Abidjan, CÃ´te d\'Ivoire', 5.25082460, -3.94498370, '', NULL, NULL, NULL, 0, NULL, 'nouvelle', 'normale', NULL, NULL, NULL, NULL, NULL, 2923.00, 0.00, 2923.00, 'especes', 2923.00, 'attente', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-09-26 21:37:22', '2025-09-26 21:37:22', 16, 15, 'Champroux Stadium, Abidjan, CÃ´te d\'Ivoire', NULL, NULL, 'Sipim Atlantide PORT-BOUÃ‹T, Abidjan, CÃ´te d\'Ivoire', NULL, NULL, '2250102453769', '2250758842029'),
(103, 'SZK20250926b8e543', 'SZK250926846873', 'particulier', 1, NULL, 'ClientExp0707', '2250707070707', 'Test DÃ©part Cocody', 5.33640000, -4.02670000, 'Test Destination Plateau', 5.35000000, -4.01000000, '', NULL, NULL, NULL, 0, NULL, 'nouvelle', 'normale', NULL, NULL, NULL, NULL, NULL, 2000.00, 0.00, 2000.00, 'especes', 2000.00, 'attente', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-09-26 21:55:39', '2025-09-26 21:55:39', 66, 67, 'Test DÃ©part Cocody', NULL, NULL, 'Test Destination Plateau', NULL, NULL, '2250707070707', '2250808080808'),
(104, 'SZK20250926c5a664', 'SZK250926639192', 'particulier', 1, NULL, 'ClientExp0707', '2250707070707', 'Test DÃ©part Cocody', 5.33640000, -4.02670000, 'Test Destination Plateau', 5.35000000, -4.01000000, '', NULL, NULL, NULL, 0, NULL, 'nouvelle', 'normale', NULL, NULL, NULL, NULL, NULL, 2000.00, 0.00, 2000.00, 'especes', 2000.00, 'attente', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-09-26 21:58:21', '2025-09-26 21:58:21', 66, 67, 'Test DÃ©part Cocody', NULL, NULL, 'Test Destination Plateau', NULL, NULL, '2250707070707', '2250808080808'),
(105, 'SZK20250927f6d1d5', 'SZK250927726006', 'particulier', 16, NULL, 'ClientExp3769', '2250102453769', 'Champroux Stadium, Abidjan, CÃ´te d\'Ivoire', 5.30509750, -3.99246690, 'Sipim Atlantide PORT-BOUÃ‹T, Abidjan, CÃ´te d\'Ivoire', 5.25082460, -3.94498370, '', NULL, NULL, NULL, 0, NULL, 'nouvelle', 'normale', NULL, NULL, NULL, NULL, NULL, 2923.00, 0.00, 2923.00, 'especes', 2923.00, 'attente', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-09-27 00:17:15', '2025-09-27 00:17:15', 16, 15, 'Champroux Stadium, Abidjan, CÃ´te d\'Ivoire', NULL, NULL, 'Sipim Atlantide PORT-BOUÃ‹T, Abidjan, CÃ´te d\'Ivoire', NULL, NULL, '2250102453769', '2250758842029'),
(106, 'SZK20250927960cb6', 'SZK250927868589', 'particulier', 16, NULL, 'ClientExp3769', '2250102453769', 'Champroux Stadium, Abidjan, CÃ´te d\'Ivoire', 5.30509750, -3.99246690, 'Sipim Atlantide PORT-BOUÃ‹T, Abidjan, CÃ´te d\'Ivoire', 5.25082460, -3.94498370, '', NULL, NULL, NULL, 0, NULL, 'nouvelle', 'normale', NULL, NULL, NULL, NULL, NULL, 2923.00, 0.00, 2923.00, 'especes', 2923.00, 'attente', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-09-27 00:38:41', '2025-09-27 00:38:41', 16, 15, 'Champroux Stadium, Abidjan, CÃ´te d\'Ivoire', NULL, NULL, 'Sipim Atlantide PORT-BOUÃ‹T, Abidjan, CÃ´te d\'Ivoire', NULL, NULL, '2250102453769', '2250758842029'),
(107, 'SZK20250927082e24', 'SZK250927805578', 'particulier', 16, NULL, 'ClientExp3769', '2250102453769', 'Champroux Stadium, Abidjan, CÃ´te d\'Ivoire', 5.30509750, -3.99246690, 'Sipim Atlantide PORT-BOUÃ‹T, Abidjan, CÃ´te d\'Ivoire', 5.25082460, -3.94498370, '', NULL, NULL, NULL, 0, NULL, 'nouvelle', 'normale', NULL, NULL, NULL, NULL, NULL, 2923.00, 0.00, 2923.00, 'especes', 2923.00, 'attente', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-09-27 01:59:34', '2025-09-27 01:59:34', 16, 15, 'Champroux Stadium, Abidjan, CÃ´te d\'Ivoire', NULL, NULL, 'Sipim Atlantide PORT-BOUÃ‹T, Abidjan, CÃ´te d\'Ivoire', NULL, NULL, '2250102453769', '2250758842029'),
(108, 'SZK202509274411ac', 'SZK250927917906', 'particulier', 16, NULL, 'ClientExp3769', '2250102453769', 'Champroux Stadium, Abidjan, CÃ´te d\'Ivoire', 5.30509750, -3.99246690, 'Sipim Atlantide PORT-BOUÃ‹T, Abidjan, CÃ´te d\'Ivoire', 5.25082460, -3.94498370, '', NULL, NULL, NULL, 0, NULL, 'nouvelle', 'normale', NULL, NULL, NULL, NULL, NULL, 2923.00, 0.00, 2923.00, 'especes', 2923.00, 'attente', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-09-27 02:24:45', '2025-09-27 02:24:45', 16, 15, 'Champroux Stadium, Abidjan, CÃ´te d\'Ivoire', NULL, NULL, 'Sipim Atlantide PORT-BOUÃ‹T, Abidjan, CÃ´te d\'Ivoire', NULL, NULL, '2250102453769', '2250758842029'),
(109, 'SZK2025092760d818', 'SZK250927106380', 'particulier', 16, NULL, 'ClientExp3769', '2250102453769', 'Champroux Stadium, Abidjan, CÃ´te d\'Ivoire', 5.30509750, -3.99246690, 'Sipim Atlantide PORT-BOUÃ‹T, Abidjan, CÃ´te d\'Ivoire', 5.25082460, -3.94498370, '', NULL, NULL, NULL, 0, NULL, 'nouvelle', 'normale', NULL, NULL, NULL, NULL, NULL, 2923.00, 0.00, 2923.00, 'especes', 2923.00, 'attente', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-09-27 02:40:47', '2025-09-27 02:40:47', 16, 15, 'Champroux Stadium, Abidjan, CÃ´te d\'Ivoire', NULL, NULL, 'Sipim Atlantide PORT-BOUÃ‹T, Abidjan, CÃ´te d\'Ivoire', NULL, NULL, '2250102453769', '2250758842029');

--
-- DÃ©clencheurs `commandes`
--
DELIMITER $$
CREATE TRIGGER `update_coursier_stats_after_commande` AFTER UPDATE ON `commandes` FOR EACH ROW BEGIN
    IF NEW.statut = 'livree' AND OLD.statut != 'livree' THEN
        UPDATE coursiers 
        SET total_commandes = total_commandes + 1
        WHERE id = NEW.coursier_id;
    END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `update_note_moyenne_coursier` AFTER UPDATE ON `commandes` FOR EACH ROW BEGIN
    IF NEW.note_coursier IS NOT NULL AND NEW.coursier_id IS NOT NULL THEN
        UPDATE coursiers 
        SET note_moyenne = (
            SELECT AVG(note_coursier) 
            FROM commandes 
            WHERE coursier_id = NEW.coursier_id 
            AND note_coursier IS NOT NULL
        )
        WHERE id = NEW.coursier_id;
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Structure de la table `commandes_classiques`
--

CREATE TABLE `commandes_classiques` (
  `id` int(11) NOT NULL,
  `coursier_id` int(11) DEFAULT NULL,
  `statut` varchar(32) DEFAULT 'nouvelle',
  `mode_paiement` varchar(32) DEFAULT NULL,
  `prix_estime` decimal(10,2) DEFAULT NULL,
  `pickup_time` datetime DEFAULT NULL,
  `delivered_time` datetime DEFAULT NULL,
  `cash_collected` tinyint(1) DEFAULT 0,
  `cash_amount` decimal(10,2) DEFAULT NULL,
  `date_acceptation` datetime DEFAULT NULL,
  `date_creation` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- DÃ©chargement des donnÃ©es de la table `commandes_classiques`
--

INSERT INTO `commandes_classiques` (`id`, `coursier_id`, `statut`, `mode_paiement`, `prix_estime`, `pickup_time`, `delivered_time`, `cash_collected`, `cash_amount`, `date_acceptation`, `date_creation`) VALUES
(3, 5, 'acceptee', NULL, NULL, NULL, NULL, 0, NULL, '2025-09-23 00:11:41', '2025-09-22 19:07:54');

-- --------------------------------------------------------

--
-- Structure de la table `commandes_coursier`
--

CREATE TABLE `commandes_coursier` (
  `id` int(11) NOT NULL,
  `coursier_id` int(11) NOT NULL,
  `client_nom` varchar(100) NOT NULL,
  `client_telephone` varchar(20) NOT NULL,
  `adresse_enlevement` text NOT NULL,
  `adresse_livraison` text NOT NULL,
  `distance` decimal(10,2) DEFAULT 0.00,
  `prix_livraison` decimal(10,2) NOT NULL,
  `description` text DEFAULT NULL,
  `statut` enum('nouvelle','acceptee','en_cours','livree','annulee') DEFAULT 'nouvelle',
  `date_commande` datetime DEFAULT current_timestamp(),
  `date_acceptation` datetime DEFAULT NULL,
  `date_livraison` datetime DEFAULT NULL,
  `type_commande` varchar(50) DEFAULT 'Standard',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- DÃ©chargement des donnÃ©es de la table `commandes_coursier`
--

INSERT INTO `commandes_coursier` (`id`, `coursier_id`, `client_nom`, `client_telephone`, `adresse_enlevement`, `adresse_livraison`, `distance`, `prix_livraison`, `description`, `statut`, `date_commande`, `date_acceptation`, `date_livraison`, `type_commande`, `created_at`, `updated_at`) VALUES
(1, 1, 'Client Test', '0700000000', 'Adresse A', 'Adresse B', 2.50, 2500.00, 'Commande de test', 'nouvelle', '2025-09-19 10:00:24', NULL, NULL, 'Standard', '2025-09-19 08:00:24', '2025-09-19 08:00:24'),
(2, 5, 'Client Test Notification', '0652810937', 'Point de retrait test', 'Destination test', 3.00, 3000.00, 'Test notification complÃ¨te - 2025-09-22 06:49:40', 'nouvelle', '2025-09-22 06:49:40', NULL, NULL, 'Standard', '2025-09-22 04:49:40', '2025-09-22 04:49:40'),
(3, 5, 'Client Test Notification', '0682926906', 'Point de retrait test', 'Destination test', 3.00, 3000.00, 'Test notification complÃ¨te - 2025-09-22 06:52:00', 'nouvelle', '2025-09-22 06:52:00', NULL, NULL, 'Standard', '2025-09-22 04:52:00', '2025-09-22 04:52:00');

-- --------------------------------------------------------

--
-- Structure de la table `commandes_coursiers`
--

CREATE TABLE `commandes_coursiers` (
  `id` int(11) NOT NULL,
  `commande_id` int(11) NOT NULL,
  `coursier_id` int(11) NOT NULL,
  `statut` varchar(32) DEFAULT 'assignee',
  `active` tinyint(1) DEFAULT 0,
  `date_attribution` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- DÃ©chargement des donnÃ©es de la table `commandes_coursiers`
--

INSERT INTO `commandes_coursiers` (`id`, `commande_id`, `coursier_id`, `statut`, `active`, `date_attribution`) VALUES
(1, 3, 5, 'acceptee', 1, '2025-09-22 18:49:02');

-- --------------------------------------------------------

--
-- Structure de la table `commandes_suzosky`
--

CREATE TABLE `commandes_suzosky` (
  `id` int(11) NOT NULL,
  `numero_commande` varchar(50) NOT NULL,
  `expediteur_nom` varchar(100) DEFAULT NULL,
  `expediteur_tel` varchar(20) DEFAULT NULL,
  `destinataire_nom` varchar(100) DEFAULT NULL,
  `destinataire_tel` varchar(20) DEFAULT NULL,
  `adresse_depart` text DEFAULT NULL,
  `adresse_arrivee` text DEFAULT NULL,
  `prix` decimal(10,2) DEFAULT NULL,
  `status` enum('en_attente','assignee','en_cours','livree','annulee') DEFAULT 'en_attente',
  `coursier_id` int(11) DEFAULT NULL,
  `shipday_order_id` varchar(50) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `livree_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `commandes_unified`
--

CREATE TABLE `commandes_unified` (
  `id` int(11) NOT NULL,
  `numero_commande` varchar(20) NOT NULL,
  `client_id` int(11) DEFAULT NULL,
  `client_nom` varchar(100) NOT NULL,
  `client_telephone` varchar(20) NOT NULL,
  `agent_id` int(11) DEFAULT NULL,
  `type_commande` enum('livraison','course','transport') NOT NULL,
  `adresse_depart` text NOT NULL,
  `adresse_arrivee` text NOT NULL,
  `description_colis` text DEFAULT NULL,
  `prix` decimal(10,2) NOT NULL,
  `statut` enum('en_attente','assignee','en_cours','livree','annulee') DEFAULT 'en_attente',
  `date_commande` timestamp NULL DEFAULT current_timestamp(),
  `date_assignation` timestamp NULL DEFAULT NULL,
  `date_livraison` timestamp NULL DEFAULT NULL,
  `interface_source` enum('admin','business','coursier','agent','concierge','mobile') NOT NULL,
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `comptes_clients_business`
--

CREATE TABLE `comptes_clients_business` (
  `id` int(11) NOT NULL,
  `client_id` int(11) NOT NULL,
  `nom_entreprise` varchar(150) NOT NULL,
  `nom_contact` varchar(100) NOT NULL,
  `prenom_contact` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `telephone` varchar(20) DEFAULT NULL,
  `secteur_activite` varchar(100) DEFAULT NULL,
  `adresse` text DEFAULT NULL,
  `solde` decimal(10,2) DEFAULT 0.00,
  `statut` enum('actif','inactif','suspendu') DEFAULT 'actif',
  `date_creation` datetime DEFAULT current_timestamp(),
  `date_modification` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `admin_createur` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `comptes_clients_particuliers`
--

CREATE TABLE `comptes_clients_particuliers` (
  `id` int(11) NOT NULL,
  `client_id` int(11) NOT NULL,
  `nom` varchar(100) NOT NULL,
  `prenom` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `telephone` varchar(20) DEFAULT NULL,
  `solde` decimal(10,2) DEFAULT 0.00,
  `statut` enum('actif','inactif','suspendu') DEFAULT 'actif',
  `date_inscription` datetime DEFAULT current_timestamp(),
  `date_modification` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `comptes_coursiers`
--

CREATE TABLE `comptes_coursiers` (
  `id` int(11) NOT NULL,
  `coursier_id` int(11) NOT NULL,
  `solde` decimal(10,2) DEFAULT 0.00,
  `statut` enum('actif','inactif','suspendu') DEFAULT 'actif',
  `date_creation` datetime DEFAULT current_timestamp(),
  `date_modification` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `configuration`
--

CREATE TABLE `configuration` (
  `id` int(11) NOT NULL,
  `cle` varchar(100) NOT NULL,
  `valeur` text DEFAULT NULL,
  `description` text DEFAULT NULL,
  `type` enum('string','integer','decimal','boolean','json') DEFAULT 'string',
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- DÃ©chargement des donnÃ©es de la table `configuration`
--

INSERT INTO `configuration` (`id`, `cle`, `valeur`, `description`, `type`, `updated_at`) VALUES
(1, 'tarif_base_km', '300', 'Tarif de base par kilomÃ¨tre en FCFA', 'decimal', '2025-08-08 09:03:00'),
(2, 'tarif_minimum', '1000', 'Tarif minimum d\'une course en FCFA', 'decimal', '2025-08-08 09:03:00'),
(3, 'tarif_urgence_multiplier', '1.5', 'Multiplicateur pour les courses urgentes', 'decimal', '2025-08-08 09:03:00'),
(4, 'tarif_express_multiplier', '2.0', 'Multiplicateur pour les courses express', 'decimal', '2025-08-08 09:03:00'),
(5, 'commission_coursier_pct', '70', 'Pourcentage du coursier sur le prix de la course', 'decimal', '2025-08-08 09:03:00'),
(6, 'bonus_rapidite_seuil', '15', 'Seuil en minutes pour le bonus rapiditÃ©', 'integer', '2025-08-08 09:03:00'),
(7, 'bonus_rapidite_montant', '500', 'Montant du bonus rapiditÃ© en FCFA', 'decimal', '2025-08-08 09:03:00'),
(8, 'penalite_retard_seuil', '30', 'Seuil en minutes pour pÃ©nalitÃ© retard', 'integer', '2025-08-08 09:03:00'),
(9, 'penalite_retard_montant', '1000', 'Montant de la pÃ©nalitÃ© retard en FCFA', 'decimal', '2025-08-08 09:03:00'),
(10, 'twilio_account_sid', '', 'Account SID Twilio', 'string', '2025-08-08 09:03:00'),
(11, 'twilio_auth_token', '', 'Auth Token Twilio', 'string', '2025-08-08 09:03:00'),
(12, 'twilio_phone_number', '', 'NumÃ©ro de tÃ©lÃ©phone Twilio', 'string', '2025-08-08 09:03:00'),
(13, 'google_maps_api_key', '', 'ClÃ© API Google Maps', 'string', '2025-08-08 09:03:00'),
(14, 'cinetpay_api_key', '', 'ClÃ© API CinetPay', 'string', '2025-08-08 09:03:00'),
(15, 'shipday_api_key', '', 'ClÃ© API ShipDay', 'string', '2025-08-08 09:03:00');

-- --------------------------------------------------------

--
-- Structure de la table `conversations`
--

CREATE TABLE `conversations` (
  `id` int(11) NOT NULL,
  `type` enum('client_agent','business_agent','interne','client_coursier') NOT NULL,
  `participant1_type` enum('client','business','coursier','agent') NOT NULL,
  `participant1_id` int(11) NOT NULL,
  `participant2_type` enum('client','business','coursier','agent') NOT NULL,
  `participant2_id` int(11) NOT NULL,
  `commande_id` int(11) DEFAULT NULL,
  `statut` enum('active','fermee','archivee') DEFAULT 'active',
  `derniere_activite` timestamp NULL DEFAULT current_timestamp(),
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `coursiers`
--

CREATE TABLE `coursiers` (
  `id` int(11) NOT NULL,
  `nom` varchar(100) NOT NULL,
  `telephone` varchar(20) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `adresse` text DEFAULT NULL,
  `type_coursier` enum('interne','business','partenaire') DEFAULT 'interne',
  `zones_couvertes` text DEFAULT NULL,
  `statut` enum('actif','inactif','suspendu','en_attente') DEFAULT 'en_attente',
  `disponible` tinyint(1) DEFAULT 1,
  `note_moyenne` decimal(3,2) DEFAULT 0.00,
  `total_commandes` int(11) DEFAULT 0,
  `vehicule_type` enum('moto','voiture','velo','pied') DEFAULT 'moto',
  `numero_permis` varchar(50) DEFAULT NULL,
  `cni` varchar(50) DEFAULT NULL,
  `photo_profil` varchar(255) DEFAULT NULL,
  `documents_valides` tinyint(1) DEFAULT 0,
  `password_hash` varchar(255) DEFAULT NULL,
  `derniere_connexion` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `solde_wallet` decimal(10,2) DEFAULT 0.00,
  `delivery_earnings` decimal(10,2) DEFAULT 0.00,
  `credit_balance` decimal(10,2) DEFAULT 0.00,
  `password_plain` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `coursiers_stats`
--

CREATE TABLE `coursiers_stats` (
  `id` int(11) NOT NULL,
  `agent_id` int(11) NOT NULL,
  `commandes_total` int(11) DEFAULT 0,
  `commandes_livrees` int(11) DEFAULT 0,
  `commandes_annulees` int(11) DEFAULT 0,
  `temps_moyen_livraison` decimal(5,2) DEFAULT 0.00,
  `note_satisfaction` decimal(3,2) DEFAULT 0.00,
  `temps_en_ligne_minutes` int(11) DEFAULT 0,
  `temps_service_minutes` int(11) DEFAULT 0,
  `dernier_update` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `coursiers_stats_suzosky`
--

CREATE TABLE `coursiers_stats_suzosky` (
  `id` int(11) NOT NULL,
  `agent_id` int(11) NOT NULL,
  `commandes_total` int(11) DEFAULT 0,
  `commandes_livrees` int(11) DEFAULT 0,
  `commandes_annulees` int(11) DEFAULT 0,
  `temps_moyen_livraison` decimal(5,2) DEFAULT 0.00,
  `note_satisfaction` decimal(3,2) DEFAULT 0.00,
  `temps_en_ligne_minutes` int(11) DEFAULT 0,
  `temps_service_minutes` int(11) DEFAULT 0,
  `dernier_update` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `coursier_chats`
--

CREATE TABLE `coursier_chats` (
  `id` int(11) NOT NULL,
  `chat_id` varchar(255) DEFAULT NULL,
  `coursier_matricule` varchar(50) DEFAULT NULL,
  `coursier_name` varchar(255) DEFAULT NULL,
  `coursier_phone` varchar(50) DEFAULT NULL,
  `device_info` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`device_info`)),
  `is_blocked` tinyint(1) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `last_activity` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `coursier_messages`
--

CREATE TABLE `coursier_messages` (
  `id` int(11) NOT NULL,
  `chat_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `sender_type` enum('coursier','admin') NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `coursier_wallet_history`
--

CREATE TABLE `coursier_wallet_history` (
  `id` int(11) NOT NULL,
  `coursier_id` varchar(20) NOT NULL,
  `type` enum('credit','debit') NOT NULL,
  `montant` decimal(10,2) NOT NULL,
  `description` text DEFAULT NULL,
  `transaction_id` varchar(100) DEFAULT NULL,
  `date_creation` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `delivery_otps`
--

CREATE TABLE `delivery_otps` (
  `id` int(11) NOT NULL,
  `commande_id` int(11) NOT NULL,
  `otp_code` varchar(10) NOT NULL,
  `attempts` int(11) DEFAULT 0,
  `max_attempts` int(11) DEFAULT 5,
  `expires_at` datetime NOT NULL,
  `generated_at` datetime DEFAULT current_timestamp(),
  `validated_at` datetime DEFAULT NULL,
  `validated_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `delivery_proofs`
--

CREATE TABLE `delivery_proofs` (
  `id` int(11) NOT NULL,
  `commande_id` int(11) NOT NULL,
  `type` enum('photo','signature') NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `mime_type` varchar(100) DEFAULT NULL,
  `size_bytes` int(11) DEFAULT NULL,
  `uploaded_by` int(11) DEFAULT NULL,
  `uploaded_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `device_tokens`
--

CREATE TABLE `device_tokens` (
  `id` int(11) NOT NULL,
  `coursier_id` int(11) NOT NULL,
  `token` varchar(255) NOT NULL,
  `platform` varchar(20) DEFAULT 'android',
  `app_version` varchar(50) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `last_used` timestamp NULL DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `dispatch_locks`
--

CREATE TABLE `dispatch_locks` (
  `id` int(11) NOT NULL,
  `commande_id` int(11) NOT NULL,
  `locked_by` int(11) DEFAULT NULL,
  `status` enum('locked','released','expired') DEFAULT 'locked',
  `ttl_seconds` int(11) DEFAULT 60,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- DÃ©chargement des donnÃ©es de la table `dispatch_locks`
--

INSERT INTO `dispatch_locks` (`id`, `commande_id`, `locked_by`, `status`, `ttl_seconds`, `created_at`, `updated_at`) VALUES
(1, 3, 5, 'locked', 60, '2025-09-22 18:49:02', '2025-09-23 01:32:22');

-- --------------------------------------------------------

--
-- Structure de la table `logs_activites`
--

CREATE TABLE `logs_activites` (
  `id` int(11) NOT NULL,
  `type_activite` varchar(100) NOT NULL,
  `description` text NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `date_activite` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `messages`
--

CREATE TABLE `messages` (
  `id` int(11) NOT NULL,
  `conversation_id` int(11) NOT NULL,
  `sender_type` enum('client','business','coursier','agent') NOT NULL,
  `sender_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `type_message` enum('text','image','file','location','system') DEFAULT 'text',
  `fichier_url` varchar(255) DEFAULT NULL,
  `lu` tinyint(1) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `notifications_log`
--

CREATE TABLE `notifications_log` (
  `id` int(11) NOT NULL,
  `commande_id` int(11) DEFAULT NULL,
  `destinataire_type` enum('client','business','coursier','agent') NOT NULL,
  `destinataire_id` int(11) NOT NULL,
  `destinataire_telephone` varchar(20) DEFAULT NULL,
  `type_notification` enum('nouvelle_commande','commande_acceptee','coursier_en_route','livraison_terminee','bonus','penalite','rappel_paiement','autre') NOT NULL,
  `canal` enum('sms','email','push','whatsapp') NOT NULL,
  `contenu` text NOT NULL,
  `statut` enum('envoye','echec','en_attente') DEFAULT 'en_attente',
  `reference_externe` varchar(100) DEFAULT NULL,
  `cout` decimal(6,4) DEFAULT 0.0000,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `offres_emploi`
--

CREATE TABLE `offres_emploi` (
  `id` int(11) NOT NULL,
  `intitule` varchar(150) NOT NULL,
  `fiche_poste` text NOT NULL,
  `competences_requises` text DEFAULT NULL,
  `type_contrat` enum('cdi','cdd','stage','freelance') NOT NULL,
  `localisation` varchar(100) DEFAULT NULL,
  `salaire_min` decimal(10,2) DEFAULT NULL,
  `salaire_max` decimal(10,2) DEFAULT NULL,
  `date_expiration` date NOT NULL,
  `statut` enum('active','expiree','pourvue','suspendue') DEFAULT 'active',
  `nombre_candidatures` int(11) DEFAULT 0,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `order_payments`
--

CREATE TABLE `order_payments` (
  `id` int(11) NOT NULL,
  `transaction_id` varchar(120) NOT NULL,
  `order_number` varchar(60) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `status` enum('pending','completed','failed','cancelled') DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- DÃ©chargement des donnÃ©es de la table `order_payments`
--

INSERT INTO `order_payments` (`id`, `transaction_id`, `order_number`, `amount`, `status`, `created_at`, `updated_at`) VALUES
(1, 'ORDER_TEST123_1756904087_1630', 'TEST123', 1000.00, 'pending', '2025-09-03 12:54:47', '2025-09-03 12:54:47'),
(2, 'ORDER_SZK20250903123456_1756908839_7361', 'SZK20250903123456', 5000.00, 'pending', '2025-09-03 14:13:59', '2025-09-03 14:13:59'),
(3, 'ORDER_SZK20250904c95509_1756982298_1724', 'SZK20250904c95509', 0.00, 'pending', '2025-09-04 10:38:18', '2025-09-04 10:38:18'),
(4, 'ORDER_SZK202509049945e8_1756982597_8839', 'SZK202509049945e8', 0.00, 'pending', '2025-09-04 10:43:17', '2025-09-04 10:43:17'),
(5, 'ORDER_SZK202509045a337a_1756982839_2935', 'SZK202509045a337a', 0.00, 'pending', '2025-09-04 10:47:19', '2025-09-04 10:47:19'),
(6, 'ORDER_SZK20250904e9d68e_1756989050_6242', 'SZK20250904e9d68e', 0.00, 'pending', '2025-09-04 12:30:50', '2025-09-04 12:30:50'),
(7, 'ORDER_SZK202509047a8f68_1756989142_1674', 'SZK202509047a8f68', 0.00, 'pending', '2025-09-04 12:32:22', '2025-09-04 12:32:22'),
(8, 'ORDER_SZK20250904d4f58e_1756990458_2967', 'SZK20250904d4f58e', 0.00, 'pending', '2025-09-04 12:54:18', '2025-09-04 12:54:18'),
(9, 'ORDER_SZK20250904d2b896_1756994776_1247', 'SZK20250904d2b896', 0.00, 'pending', '2025-09-04 14:06:16', '2025-09-04 14:06:16'),
(10, 'ORDER_SZK202509049d6e98_1756996964_5996', 'SZK202509049d6e98', 0.00, 'pending', '2025-09-04 14:42:44', '2025-09-04 14:42:44'),
(11, 'ORDER_SZK20250904dfec60_1756999366_3794', 'SZK20250904dfec60', 0.00, 'pending', '2025-09-04 15:22:46', '2025-09-04 15:22:46'),
(12, 'ORDER_SZK202509047656d0_1756999485_4608', 'SZK202509047656d0', 0.00, 'pending', '2025-09-04 15:24:45', '2025-09-04 15:24:45'),
(13, 'ORDER_SZK202509044087a6_1757000058_5089', 'SZK202509044087a6', 0.00, 'pending', '2025-09-04 15:34:18', '2025-09-04 15:34:18'),
(14, 'ORDER_SZK20250904d8fc73_1757002235_2237', 'SZK20250904d8fc73', 0.00, 'pending', '2025-09-04 16:10:35', '2025-09-04 16:10:35'),
(15, 'ORDER_SZK2025090486bff8_1757002726_7676', 'SZK2025090486bff8', 0.00, 'pending', '2025-09-04 16:18:46', '2025-09-04 16:18:46'),
(16, 'ORDER_SZK2025090487d6f5_1757003265_4731', 'SZK2025090487d6f5', 0.00, 'pending', '2025-09-04 16:27:45', '2025-09-04 16:27:45'),
(17, 'ORDER_SZK202509047c2e1d_1757008636_7494', 'SZK202509047c2e1d', 6400.00, 'pending', '2025-09-04 17:57:16', '2025-09-04 17:57:16'),
(18, 'ORDER_SZK20250904b50001_1757009000_9759', 'SZK20250904b50001', 6400.00, 'pending', '2025-09-04 18:03:20', '2025-09-04 18:03:20'),
(19, 'ORDER_SZK202509043f5c95_1757009135_1352', 'SZK202509043f5c95', 6400.00, 'pending', '2025-09-04 18:05:35', '2025-09-04 18:05:35'),
(20, 'ORDER_SZK20250904efaabf_1757010192_9587', 'SZK20250904efaabf', 6400.00, 'pending', '2025-09-04 18:23:12', '2025-09-04 18:23:12'),
(21, 'ORDER_SZK202509043b0367_1757010247_6705', 'SZK202509043b0367', 6400.00, 'pending', '2025-09-04 18:24:07', '2025-09-04 18:24:07'),
(22, 'ORDER_SZK20250904c340b9_1757010417_3779', 'SZK20250904c340b9', 6400.00, 'pending', '2025-09-04 18:26:57', '2025-09-04 18:26:57'),
(23, 'ORDER_SZK202509040e1697_1757010629_8596', 'SZK202509040e1697', 6400.00, 'pending', '2025-09-04 18:30:29', '2025-09-04 18:30:29'),
(24, 'ORDER_SZK20250904dbb7c3_1757010762_2220', 'SZK20250904dbb7c3', 6400.00, 'pending', '2025-09-04 18:32:42', '2025-09-04 18:32:42'),
(25, 'ORDER_SZK202509045992d7_1757010822_4617', 'SZK202509045992d7', 6400.00, 'pending', '2025-09-04 18:33:42', '2025-09-04 18:33:42'),
(26, 'ORDER_SZK20250904d43fde_1757011035_4585', 'SZK20250904d43fde', 6400.00, 'pending', '2025-09-04 18:37:15', '2025-09-04 18:37:15'),
(27, 'ORDER_SZK20250904c7da25_1757011053_6338', 'SZK20250904c7da25', 6400.00, 'pending', '2025-09-04 18:37:33', '2025-09-04 18:37:33'),
(28, 'ORDER_SZK20250904926449_1757011086_6491', 'SZK20250904926449', 6400.00, 'pending', '2025-09-04 18:38:06', '2025-09-04 18:38:06'),
(29, 'ORDER_SZK2025090432ab7d_1757011968_5159', 'SZK2025090432ab7d', 6400.00, 'pending', '2025-09-04 18:52:48', '2025-09-04 18:52:48'),
(30, 'ORDER_SZK202509043f948f_1757012621_2584', 'SZK202509043f948f', 6400.00, 'pending', '2025-09-04 19:03:41', '2025-09-04 19:03:41'),
(31, 'ORDER_SZK202509041156a3_1757013278_6785', 'SZK202509041156a3', 6400.00, 'pending', '2025-09-04 19:14:38', '2025-09-04 19:14:38'),
(32, 'ORDER_SZK202509043e090f_1757013384_8455', 'SZK202509043e090f', 6400.00, 'pending', '2025-09-04 19:16:24', '2025-09-04 19:16:24'),
(33, 'ORDER_SZK202509047886be_1757013656_8544', 'SZK202509047886be', 6400.00, 'pending', '2025-09-04 19:20:56', '2025-09-04 19:20:56'),
(34, 'ORDER_SZK20250904555505_1757013733_9768', 'SZK20250904555505', 6400.00, 'pending', '2025-09-04 19:22:13', '2025-09-04 19:22:13'),
(35, 'ORDER_SZK20250904a37b85_1757014358_2172', 'SZK20250904a37b85', 6400.00, 'pending', '2025-09-04 19:32:38', '2025-09-04 19:32:38'),
(36, 'ORDER_SZK2025090476116a_1757014884_2762', 'SZK2025090476116a', 6400.00, 'pending', '2025-09-04 19:41:24', '2025-09-04 19:41:24'),
(37, 'ORDER_SZK20250904570f95_1757016226_9161', 'SZK20250904570f95', 8320.00, 'pending', '2025-09-04 20:03:46', '2025-09-04 20:03:46'),
(38, 'ORDER_SZK2025090480483d_1757016439_6986', 'SZK2025090480483d', 8320.00, 'pending', '2025-09-04 20:07:19', '2025-09-04 20:07:19'),
(39, 'ORDER_SZK20250904ac79e4_1757016824_8044', 'SZK20250904ac79e4', 8320.00, 'pending', '2025-09-04 20:13:44', '2025-09-04 20:13:44'),
(40, 'ORDER_SZK202509041d0a5c_1757016987_1625', 'SZK202509041d0a5c', 8320.00, 'pending', '2025-09-04 20:16:27', '2025-09-04 20:16:27'),
(41, 'ORDER_SZK2025090429b498_1757017105_5939', 'SZK2025090429b498', 8320.00, 'pending', '2025-09-04 20:18:25', '2025-09-04 20:18:25'),
(42, 'ORDER_SZK1757782344022_1757782344_6707', 'SZK1757782344022', 3224.00, 'pending', '2025-09-13 16:52:24', '2025-09-13 16:52:24'),
(43, 'ORDER_SZK1757782554702_1757782555_1420', 'SZK1757782554702', 300.00, 'pending', '2025-09-13 16:55:55', '2025-09-13 16:55:55'),
(44, 'ORDER_SZK1757783190410_1757783190_9917', 'SZK1757783190410', 3224.00, 'pending', '2025-09-13 17:06:30', '2025-09-13 17:06:30'),
(45, 'ORDER_SZK1757783862252_1757783862_8483', 'SZK1757783862252', 300.00, 'pending', '2025-09-13 17:17:42', '2025-09-13 17:17:42'),
(46, 'ORDER_SZK1757784707342_1757784707_9924', 'SZK1757784707342', 3224.00, 'pending', '2025-09-13 17:31:47', '2025-09-13 17:31:47'),
(47, 'ORDER_SZK1757785046558_1757785047_9806', 'SZK1757785046558', 5159.00, 'pending', '2025-09-13 17:37:27', '2025-09-13 17:37:27'),
(48, 'ORDER_SZK1757785818331_1757785819_9074', 'SZK1757785818331', 300.00, 'pending', '2025-09-13 17:50:19', '2025-09-13 17:50:19'),
(49, 'ORDER_SZK1757786189650_1757786190_6079', 'SZK1757786189650', 300.00, 'pending', '2025-09-13 17:56:30', '2025-09-13 17:56:30'),
(50, 'ORDER_SZK1757788262430_1757788262_4582', 'SZK1757788262430', 3224.00, 'pending', '2025-09-13 18:31:02', '2025-09-13 18:31:02'),
(51, 'ORDER_SZK1757788895884_1757788896_8800', 'SZK1757788895884', 300.00, 'pending', '2025-09-13 18:41:36', '2025-09-13 18:41:36'),
(52, 'ORDER_SZK1757869960523_1757869960_4985', 'SZK1757869960523', 300.00, 'pending', '2025-09-14 17:12:40', '2025-09-14 17:12:40'),
(53, 'ORDER_SZK1757876023105_1757876022_7855', 'SZK1757876023105', 3224.00, 'pending', '2025-09-14 18:53:42', '2025-09-14 18:53:42'),
(54, 'ORDER_SZK1757885340019_1757885340_3435', 'SZK1757885340019', 2233.00, 'pending', '2025-09-14 21:29:00', '2025-09-14 21:29:00'),
(55, 'ORDER_SZK1758112576166_1758112577_4479', 'SZK1758112576166', 3224.00, 'pending', '2025-09-17 12:36:18', '2025-09-17 12:36:18'),
(56, 'ORDER_SZK1758470609651_1758470609_2497', 'SZK1758470609651', 3224.00, 'pending', '2025-09-21 16:03:29', '2025-09-21 16:03:29'),
(57, 'ORDER_SZK1758872150865_1758872151_9967', 'SZK1758872150865', 51.00, 'pending', '2025-09-26 07:35:50', '2025-09-26 07:35:50'),
(58, 'ORDER_SZK1758890257538_1758890257_8454', 'SZK1758890257538', 2924.00, 'pending', '2025-09-26 12:37:37', '2025-09-26 12:37:37'),
(59, 'ORDER_SZK1758906135654_1758906135_2710', 'SZK1758906135654', 2166836.00, 'pending', '2025-09-26 17:02:15', '2025-09-26 17:02:15'),
(60, 'ORDER_SZK202509265a8198_1758911656_2157', 'SZK202509265a8198', 3394.00, 'pending', '2025-09-26 18:34:16', '2025-09-26 18:34:16'),
(61, 'ORDER_SZK2025092686c1cf_1758912352_1513', 'SZK2025092686c1cf', 3448.00, 'pending', '2025-09-26 18:45:52', '2025-09-26 18:45:52'),
(62, 'ORDER_SZK2025092651d96f_1758918286_2727', 'SZK2025092651d96f', 2923.00, 'pending', '2025-09-26 20:24:46', '2025-09-26 20:24:46'),
(63, 'ORDER_SZK20250926ce55e3_1758919221_2931', 'SZK20250926ce55e3', 2923.00, 'pending', '2025-09-26 20:40:21', '2025-09-26 20:40:21'),
(64, 'ORDER_SZK2025092661dccb_1758919227_1874', 'SZK2025092661dccb', 2923.00, 'pending', '2025-09-26 20:40:27', '2025-09-26 20:40:27'),
(65, 'ORDER_SZK20250926e41853_1758919666_5761', 'SZK20250926e41853', 2923.00, 'pending', '2025-09-26 20:47:46', '2025-09-26 20:47:46'),
(66, 'ORDER_SZK20250926556783_1758922642_8917', 'SZK20250926556783', 2923.00, 'pending', '2025-09-26 21:37:22', '2025-09-26 21:37:22'),
(67, 'ORDER_SZK20250926b8e543_1758923739_6200', 'SZK20250926b8e543', 2000.00, 'pending', '2025-09-26 21:55:39', '2025-09-26 21:55:39'),
(68, 'ORDER_SZK20250926c5a664_1758923901_5634', 'SZK20250926c5a664', 2000.00, 'pending', '2025-09-26 21:58:21', '2025-09-26 21:58:21'),
(69, 'ORDER_SZK20250927f6d1d5_1758932235_1773', 'SZK20250927f6d1d5', 2923.00, 'pending', '2025-09-27 00:17:15', '2025-09-27 00:17:15'),
(70, 'ORDER_SZK20250927960cb6_1758933521_7223', 'SZK20250927960cb6', 2923.00, 'pending', '2025-09-27 00:38:41', '2025-09-27 00:38:41'),
(71, 'ORDER_SZK20250927082e24_1758938374_9338', 'SZK20250927082e24', 2923.00, 'pending', '2025-09-27 01:59:34', '2025-09-27 01:59:34'),
(72, 'ORDER_SZK202509274411ac_1758939885_1431', 'SZK202509274411ac', 2923.00, 'pending', '2025-09-27 02:24:45', '2025-09-27 02:24:45'),
(73, 'ORDER_SZK2025092760d818_1758940847_8769', 'SZK2025092760d818', 2923.00, 'pending', '2025-09-27 02:40:47', '2025-09-27 02:40:47');

-- --------------------------------------------------------

--
-- Structure de la table `order_status_history`
--

CREATE TABLE `order_status_history` (
  `id` int(11) NOT NULL,
  `commande_id` int(11) NOT NULL,
  `old_status` varchar(50) DEFAULT NULL,
  `new_status` varchar(50) NOT NULL,
  `changed_by` varchar(50) DEFAULT NULL,
  `coursier_id` int(11) DEFAULT NULL,
  `note` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `parametres_tarification`
--

CREATE TABLE `parametres_tarification` (
  `id` int(11) NOT NULL,
  `parametre` varchar(50) NOT NULL,
  `valeur` decimal(10,4) NOT NULL,
  `date_modification` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- DÃ©chargement des donnÃ©es de la table `parametres_tarification`
--

INSERT INTO `parametres_tarification` (`id`, `parametre`, `valeur`, `date_modification`) VALUES
(1, 'prix_kilometre', 300.0000, '2025-09-18 17:24:41'),
(2, 'frais_base', 0.0000, '2025-09-22 20:50:19'),
(51, 'commission_suzosky', 30.0000, '2025-09-22 20:50:57'),
(53, 'supp_km_rate', 100.0000, '2025-09-22 07:35:19'),
(54, 'supp_km_free_allowance', 1.0000, '2025-09-22 07:35:19'),
(67, 'frais_plateforme', 5.0000, '2025-09-22 19:55:31');

-- --------------------------------------------------------

--
-- Structure de la table `payment_transactions`
--

CREATE TABLE `payment_transactions` (
  `id` int(11) NOT NULL,
  `transaction_id` varchar(100) NOT NULL,
  `coursier_id` varchar(20) NOT NULL,
  `montant` decimal(10,2) NOT NULL,
  `statut` enum('pending','success','failed','error','cancelled') DEFAULT 'pending',
  `cinetpay_transaction_id` varchar(100) DEFAULT NULL,
  `payment_method` varchar(50) DEFAULT NULL,
  `date_creation` timestamp NULL DEFAULT current_timestamp(),
  `date_completion` timestamp NULL DEFAULT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `error_message` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `payouts_minimal`
--

CREATE TABLE `payouts_minimal` (
  `id` int(11) NOT NULL,
  `coursier_id` int(11) NOT NULL,
  `period_label` varchar(20) NOT NULL,
  `total_livraisons` int(11) DEFAULT 0,
  `montant_total` decimal(10,2) DEFAULT 0.00,
  `statut` enum('en_attente','pret','paye') DEFAULT 'en_attente',
  `generated_at` datetime DEFAULT current_timestamp(),
  `paid_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `postes`
--

CREATE TABLE `postes` (
  `id` int(11) NOT NULL,
  `titre` varchar(255) NOT NULL,
  `description_courte` text DEFAULT NULL,
  `description_complete` text DEFAULT NULL,
  `qualites_requises` text DEFAULT NULL,
  `experience_requise` text DEFAULT NULL,
  `date_expiration` date NOT NULL,
  `statut` enum('actif','inactif') DEFAULT 'actif'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- DÃ©chargement des donnÃ©es de la table `postes`
--

INSERT INTO `postes` (`id`, `titre`, `description_courte`, `description_complete`, `qualites_requises`, `experience_requise`, `date_expiration`, `statut`) VALUES
(5, 'Agent Commercial', 'Prospection BtoB', 'DÃ©veloppement de la clientÃ¨le entreprise.', 'Sens du relationnel', '1 an', '2025-10-29', 'actif');

-- --------------------------------------------------------

--
-- Structure de la table `recharges`
--

CREATE TABLE `recharges` (
  `id` int(11) NOT NULL,
  `coursier_id` int(11) NOT NULL,
  `montant` decimal(10,2) NOT NULL,
  `currency` varchar(10) DEFAULT 'XOF',
  `cinetpay_transaction_id` varchar(255) DEFAULT NULL,
  `status` enum('pending','success','failed') DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `details` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`details`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- DÃ©chargement des donnÃ©es de la table `recharges`
--

INSERT INTO `recharges` (`id`, `coursier_id`, `montant`, `currency`, `cinetpay_transaction_id`, `status`, `created_at`, `updated_at`, `details`) VALUES
(1, 1, 5000.00, 'XOF', NULL, 'pending', '2025-09-19 08:07:55', '2025-09-19 08:07:55', NULL),
(2, 1, 5000.00, 'XOF', NULL, 'pending', '2025-09-20 12:53:07', '2025-09-20 12:53:07', NULL),
(3, 1, 5000.00, 'XOF', NULL, 'pending', '2025-09-20 14:17:25', '2025-09-20 14:17:25', NULL),
(4, 1, 5000.00, 'XOF', NULL, 'pending', '2025-09-20 14:18:18', '2025-09-20 14:18:18', NULL),
(5, 1, 2000.00, 'XOF', NULL, 'pending', '2025-09-20 14:18:54', '2025-09-20 14:18:54', NULL),
(6, 1, 5000.00, 'XOF', NULL, 'pending', '2025-09-21 00:52:57', '2025-09-21 00:52:57', NULL);

-- --------------------------------------------------------

--
-- Structure de la table `recharges_coursiers`
--

CREATE TABLE `recharges_coursiers` (
  `id` int(11) NOT NULL,
  `coursier_id` int(11) NOT NULL,
  `montant` decimal(10,2) NOT NULL,
  `reference_paiement` varchar(100) DEFAULT NULL,
  `statut` enum('en_attente','validee','refusee') DEFAULT 'en_attente',
  `date_demande` datetime DEFAULT current_timestamp(),
  `date_validation` datetime DEFAULT NULL,
  `commentaire_admin` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `sessions_unified`
--

CREATE TABLE `sessions_unified` (
  `id` int(11) NOT NULL,
  `session_id` varchar(128) NOT NULL,
  `user_type` enum('admin','agent','client','business') NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `user_identifier` varchar(100) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `user_agent` text DEFAULT NULL,
  `interface_source` enum('admin','business','coursier','agent','concierge') NOT NULL,
  `login_time` timestamp NULL DEFAULT current_timestamp(),
  `last_activity` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_active` tinyint(1) DEFAULT 1,
  `logout_time` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `support_chats`
--

CREATE TABLE `support_chats` (
  `id` int(11) NOT NULL,
  `chat_id` varchar(255) DEFAULT NULL,
  `user_type` enum('client','business') DEFAULT 'client',
  `username` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `country` varchar(100) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `commune` varchar(100) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `device_info` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`device_info`)),
  `is_blocked` tinyint(1) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `last_activity` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `client_name` varchar(255) DEFAULT NULL,
  `client_email` varchar(255) DEFAULT NULL,
  `status` enum('active','closed') DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- DÃ©chargement des donnÃ©es de la table `support_chats`
--

INSERT INTO `support_chats` (`id`, `chat_id`, `user_type`, `username`, `email`, `phone`, `country`, `city`, `commune`, `ip_address`, `user_agent`, `device_info`, `is_blocked`, `created_at`, `last_activity`, `client_name`, `client_email`, `status`) VALUES
(8, 'CHAT_1755775635_9e27a1e67', 'client', 'Client Test Exact', 'test@exact.com', '+225 07 12 34 56 78', 'CÃ´te d\'Ivoire', 'Abidjan', 'Cocody', '193.203.239.82', 'Mozilla/5.0 (Test Browser)', '{\"userAgent\":\"Mozilla\\/5.0 (Test Browser)\",\"platform\":\"Test Platform\",\"language\":\"fr-FR\"}', 0, '2025-08-21 11:27:16', '2025-08-21 11:27:16', NULL, NULL, 'active'),
(10, 'CHAT_1755775979_f9d55006c', 'client', 'Client Test Exact', 'test@exact.com', '+225 07 12 34 56 78', 'CÃ´te d\'Ivoire', 'Abidjan', 'Cocody', '193.203.239.82', 'Mozilla/5.0 (Test Browser)', '{\"userAgent\":\"Mozilla\\/5.0 (Test Browser)\",\"platform\":\"Test Platform\",\"language\":\"fr-FR\"}', 0, '2025-08-21 11:32:59', '2025-08-21 11:32:59', NULL, NULL, 'active'),
(13, 'CHAT_1755776298_0af5e4961', 'client', 'Client Test Exact', 'test@exact.com', '+225 07 12 34 56 78', 'CÃ´te d\'Ivoire', 'Abidjan', 'Cocody', '193.203.239.82', 'Mozilla/5.0 (Test Browser)', '{\"userAgent\":\"Mozilla\\/5.0 (Test Browser)\",\"platform\":\"Test Platform\",\"language\":\"fr-FR\"}', 0, '2025-08-21 11:38:18', '2025-08-21 11:38:18', NULL, NULL, 'active'),
(14, 'DIRECT_TEST_1755776550', 'client', 'Test Direct', 'direct@test.com', '+225 07 12 34 56 78', 'CÃ´te d\'Ivoire', 'Abidjan', 'Cocody', '193.203.239.82', 'Direct Test Browser', '{\"userAgent\":\"Direct Test Browser\",\"platform\":\"Direct Test\",\"language\":\"fr-FR\"}', 0, '2025-08-21 11:42:30', '2025-08-21 11:42:30', NULL, NULL, 'active'),
(15, 'DIRECT_TEST_1755776724', 'client', 'Test Direct', 'direct@test.com', '+225 07 12 34 56 78', 'CÃ´te d\'Ivoire', 'Abidjan', 'Cocody', '193.203.239.82', 'Direct Test Browser', '{\"userAgent\":\"Direct Test Browser\",\"platform\":\"Direct Test\",\"language\":\"fr-FR\"}', 0, '2025-08-21 11:45:24', '2025-08-21 11:45:24', NULL, NULL, 'active'),
(16, 'DEBUG_TEST_1755776931', 'client', 'Debug Test', 'debug@test.com', '+225 07 12 34 56 78', 'CÃ´te d\'Ivoire', 'Abidjan', 'Cocody', '193.203.239.82', 'Debug Browser', '{\"userAgent\":\"Debug Browser\",\"platform\":\"Debug\",\"language\":\"fr-FR\"}', 0, '2025-08-21 11:48:51', '2025-08-21 11:48:51', NULL, NULL, 'active'),
(21, 'DEBUG_INIT_1755777180', 'client', 'Test Debug Init', 'debug@init.com', '+225 07 12 34 56 78', 'CÃ´te d\'Ivoire', 'Abidjan', 'Cocody', '193.203.239.82', 'Debug Browser', '{\"userAgent\":\"Debug Browser\",\"platform\":\"Debug Platform\",\"language\":\"fr-FR\"}', 0, '2025-08-21 11:53:00', '2025-08-21 11:53:00', NULL, NULL, 'active'),
(22, 'CHAT_1755777274_8af5db2ff', 'client', 'Client Test Exact', 'test@exact.com', '+225 07 12 34 56 78', 'CÃ´te d\'Ivoire', 'Abidjan', 'Cocody', '193.203.239.82', 'Mozilla/5.0 (Test Browser)', '{\"userAgent\":\"Mozilla\\/5.0 (Test Browser)\",\"platform\":\"Test Platform\",\"language\":\"fr-FR\"}', 0, '2025-08-21 11:54:34', '2025-08-21 11:54:34', NULL, NULL, 'active'),
(23, 'TEST_1755778461', 'client', 'Test', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '2025-08-21 12:14:21', '2025-08-21 12:14:21', NULL, NULL, 'active'),
(25, 'TEST_FINAL_1755782072', 'client', 'Test Final', 'test@final.com', '+225 07 12 34 56 78', 'CÃ´te d\'Ivoire', 'Abidjan', 'Test', '193.203.239.82', 'Test Browser', '{\"userAgent\":\"Test Browser\",\"platform\":\"Test\",\"language\":\"fr-FR\"}', 0, '2025-08-21 13:14:32', '2025-08-21 13:14:32', NULL, NULL, 'active'),
(26, 'TEST_FINAL_1755782292', 'client', 'Test Final', 'test@final.com', '+225 07 12 34 56 78', 'CÃ´te d\'Ivoire', 'Abidjan', 'Test', '193.203.239.82', 'Test Browser', '{\"userAgent\":\"Test Browser\",\"platform\":\"Test\",\"language\":\"fr-FR\"}', 0, '2025-08-21 13:18:12', '2025-08-21 13:18:12', NULL, NULL, 'active');

-- --------------------------------------------------------

--
-- Structure de la table `support_messages`
--

CREATE TABLE `support_messages` (
  `id` int(11) NOT NULL,
  `chat_id` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `sender_type` enum('user','admin') NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `sender_name` varchar(255) DEFAULT 'Utilisateur',
  `ip_address` varchar(45) DEFAULT NULL,
  `sent_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- DÃ©chargement des donnÃ©es de la table `support_messages`
--

INSERT INTO `support_messages` (`id`, `chat_id`, `message`, `sender_type`, `is_read`, `created_at`, `sender_name`, `ip_address`, `sent_at`) VALUES
(9, 'TEST_FINAL_1755782072', 'Bonjour Test Final ! ðŸ‘‹\n\nBienvenue sur le support Suzosky. Notre Ã©quipe va vous rÃ©pondre rapidement.\n\nType de demande : Question gÃ©nÃ©rale\nCommune : Test', 'admin', 0, '2025-08-21 13:14:32', 'Support Suzosky', '193.203.239.82', '2025-08-21 13:14:32'),
(10, 'TEST_FINAL_1755782072', 'Message de test final', 'user', 0, '2025-08-21 13:14:32', 'Test Final', '193.203.239.82', '2025-08-21 13:14:32'),
(11, 'TEST_FINAL_1755782292', 'Bonjour Test Final ! ðŸ‘‹\n\nBienvenue sur le support Suzosky. Notre Ã©quipe va vous rÃ©pondre rapidement.\n\nType de demande : Question gÃ©nÃ©rale\nCommune : Test', 'admin', 0, '2025-08-21 13:18:12', 'Support Suzosky', '193.203.239.82', '2025-08-21 13:18:12'),
(12, 'TEST_FINAL_1755782292', 'Message de test final', 'user', 0, '2025-08-21 13:18:12', 'Test Final', '193.203.239.82', '2025-08-21 13:18:12');

-- --------------------------------------------------------

--
-- Structure de la table `system_sync_heartbeats`
--

CREATE TABLE `system_sync_heartbeats` (
  `component` varchar(80) NOT NULL,
  `status` varchar(32) NOT NULL DEFAULT 'ok',
  `metrics_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metrics_json`)),
  `last_seen_at` datetime NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- DÃ©chargement des donnÃ©es de la table `system_sync_heartbeats`
--

INSERT INTO `system_sync_heartbeats` (`component`, `status`, `metrics_json`, `last_seen_at`, `created_at`, `updated_at`) VALUES
('admin_commandes', 'ok', '{\"filters\":{\"statut\":\"\",\"coursier\":\"\",\"date\":\"\",\"priorite\":\"\",\"transaction\":\"\"},\"commandes_count\":73,\"stats\":{\"total\":73,\"nouvelle\":68,\"assignee\":0,\"en_cours\":0,\"livree\":5,\"annulee\":0}}', '2025-09-27 03:50:44', '2025-09-25 14:49:02', '2025-09-27 03:50:44'),
('frontend_index', 'ok', '{\"request_uri\":\"\\/\",\"host\":\"coursier.conciergerie-privee-suzosky.com\",\"session_active\":1,\"user_agent_hash\":\"2c2dcbeaf685\"}', '2025-09-27 05:21:52', '2025-09-25 14:46:00', '2025-09-27 05:21:52');

-- --------------------------------------------------------

--
-- Structure de la table `tarifs`
--

CREATE TABLE `tarifs` (
  `id` int(11) NOT NULL,
  `zone` varchar(100) NOT NULL,
  `distance_min` decimal(5,2) NOT NULL,
  `distance_max` decimal(5,2) NOT NULL,
  `prix_base` decimal(10,2) NOT NULL,
  `prix_par_km` decimal(10,2) NOT NULL,
  `actif` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- DÃ©chargement des donnÃ©es de la table `tarifs`
--

INSERT INTO `tarifs` (`id`, `zone`, `distance_min`, `distance_max`, `prix_base`, `prix_par_km`, `actif`, `created_at`) VALUES
(1, 'Abidjan Centre', 0.00, 5.00, 2000.00, 300.00, 1, '2025-08-10 13:30:45'),
(2, 'Abidjan Ã‰tendue', 5.00, 15.00, 3000.00, 400.00, 1, '2025-08-10 13:30:45'),
(3, 'Grand Abidjan', 15.00, 50.00, 5000.00, 500.00, 1, '2025-08-10 13:30:45');

-- --------------------------------------------------------

--
-- Structure de la table `tracking_coursiers`
--

CREATE TABLE `tracking_coursiers` (
  `id` int(11) NOT NULL,
  `coursier_id` int(11) NOT NULL,
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `accuracy` decimal(6,2) DEFAULT NULL,
  `timestamp` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `lat` double DEFAULT NULL,
  `lng` double DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- DÃ©chargement des donnÃ©es de la table `tracking_coursiers`
--

INSERT INTO `tracking_coursiers` (`id`, `coursier_id`, `latitude`, `longitude`, `accuracy`, `timestamp`, `created_at`, `updated_at`, `lat`, `lng`) VALUES
(1, 9001, 5.34531700, -4.02442900, NULL, '2025-09-21 11:00:36', '2025-09-21 09:00:36', '2025-09-21 09:00:36', 5.345317, -4.024429);

-- --------------------------------------------------------

--
-- Structure de la table `transactions`
--

CREATE TABLE `transactions` (
  `id` int(11) NOT NULL,
  `commande_id` int(11) NOT NULL,
  `reference_transaction` varchar(100) NOT NULL,
  `montant` decimal(10,2) NOT NULL,
  `type_transaction` enum('paiement','remboursement','commission') NOT NULL,
  `methode_paiement` enum('especes','orange_money','moov_money','mtn_money','wave','carte_visa','carte_mastercard') NOT NULL,
  `statut` enum('pending','success','failed','cancelled') DEFAULT 'pending',
  `reference_externe` varchar(100) DEFAULT NULL,
  `frais_transaction` decimal(8,2) DEFAULT 0.00,
  `details_reponse` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`details_reponse`)),
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `transactions_financieres`
--

CREATE TABLE `transactions_financieres` (
  `id` int(11) NOT NULL,
  `type` enum('credit','debit') NOT NULL,
  `montant` decimal(10,2) NOT NULL,
  `compte_type` enum('coursier','client') NOT NULL,
  `compte_id` int(11) NOT NULL,
  `reference` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `statut` enum('en_attente','reussi','echoue') DEFAULT 'reussi',
  `date_creation` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Doublure de structure pour la vue `view_device_stats`
-- (Voir ci-dessous la vue rÃ©elle)
--
CREATE TABLE `view_device_stats` (
`device_id` varchar(128)
,`device_model` varchar(100)
,`device_brand` varchar(50)
,`android_version` varchar(20)
,`app_version_code` int(11)
,`app_version_name` varchar(20)
,`last_seen` datetime
,`total_sessions` int(11) unsigned
,`crash_count` bigint(21)
,`activity_status` varchar(8)
,`update_status` varchar(13)
);

-- --------------------------------------------------------

--
-- Doublure de structure pour la vue `vue_commandes_aujourd_hui`
-- (Voir ci-dessous la vue rÃ©elle)
--
CREATE TABLE `vue_commandes_aujourd_hui` (
`total_commandes` bigint(21)
,`commandes_livrees` bigint(21)
,`commandes_annulees` bigint(21)
,`revenus_jour` decimal(30,2)
,`temps_moyen_livraison` decimal(24,4)
);

-- --------------------------------------------------------

--
-- Doublure de structure pour la vue `vue_performance_coursiers`
-- (Voir ci-dessous la vue rÃ©elle)
--
CREATE TABLE `vue_performance_coursiers` (
`id` int(11)
,`nom` varchar(100)
,`telephone` varchar(20)
,`note_moyenne` decimal(3,2)
,`total_commandes` int(11)
,`commandes_ce_mois` bigint(21)
,`livrees_ce_mois` bigint(21)
,`taux_reussite` decimal(26,2)
,`revenus_generes` decimal(30,2)
);

-- --------------------------------------------------------

--
-- Structure de la vue `view_device_stats`
--
DROP TABLE IF EXISTS `view_device_stats`;

CREATE OR REPLACE VIEW `view_device_stats`  AS SELECT `d`.`device_id` AS `device_id`, `d`.`device_model` AS `device_model`, `d`.`device_brand` AS `device_brand`, `d`.`android_version` AS `android_version`, `d`.`app_version_code` AS `app_version_code`, `d`.`app_version_name` AS `app_version_name`, `d`.`last_seen` AS `last_seen`, `d`.`total_sessions` AS `total_sessions`, coalesce(`c`.`crash_count`,0) AS `crash_count`, CASE WHEN `d`.`last_seen` >= current_timestamp() - interval 1 day THEN 'ACTIVE' WHEN `d`.`last_seen` >= current_timestamp() - interval 7 day THEN 'INACTIVE' ELSE 'DORMANT' END AS `activity_status`, CASE WHEN `v`.`version_code` > `d`.`app_version_code` THEN 'UPDATE_NEEDED' ELSE 'UP_TO_DATE' END AS `update_status` FROM ((`app_devices` `d` left join (select `app_crashes`.`device_id` AS `device_id`,count(0) AS `crash_count` from `app_crashes` where `app_crashes`.`created_at` >= current_timestamp() - interval 30 day group by `app_crashes`.`device_id`) `c` on(`c`.`device_id` = `d`.`device_id`)) join (select max(`app_versions`.`version_code`) AS `version_code` from `app_versions` where `app_versions`.`is_active` = 1) `v`) WHERE `d`.`is_active` = 1 ;

-- --------------------------------------------------------

--
-- Structure de la vue `vue_commandes_aujourd_hui`
--
DROP TABLE IF EXISTS `vue_commandes_aujourd_hui`;

CREATE ALGORITHM=UNDEFINED DEFINER=`conci2547642_1m4twb`@`localhost` SQL SECURITY DEFINER VIEW `vue_commandes_aujourd_hui`  AS SELECT count(0) AS `total_commandes`, count(case when `commandes`.`statut` = 'livree' then 1 end) AS `commandes_livrees`, count(case when `commandes`.`statut` = 'annulee' then 1 end) AS `commandes_annulees`, sum(case when `commandes`.`statut` = 'livree' then `commandes`.`prix_total` else 0 end) AS `revenus_jour`, avg(case when `commandes`.`statut` = 'livree' and `commandes`.`heure_retrait` is not null and `commandes`.`heure_livraison` is not null then timestampdiff(MINUTE,`commandes`.`heure_retrait`,`commandes`.`heure_livraison`) end) AS `temps_moyen_livraison` FROM `commandes` WHERE cast(`commandes`.`created_at` as date) = curdate() ;

-- --------------------------------------------------------

--
-- Structure de la vue `vue_performance_coursiers`
--
DROP TABLE IF EXISTS `vue_performance_coursiers`;

CREATE ALGORITHM=UNDEFINED DEFINER=`conci2547642_1m4twb`@`localhost` SQL SECURITY DEFINER VIEW `vue_performance_coursiers`  AS SELECT `c`.`id` AS `id`, `c`.`nom` AS `nom`, `c`.`telephone` AS `telephone`, `c`.`note_moyenne` AS `note_moyenne`, `c`.`total_commandes` AS `total_commandes`, count(`co`.`id`) AS `commandes_ce_mois`, count(case when `co`.`statut` = 'livree' then 1 end) AS `livrees_ce_mois`, round(count(case when `co`.`statut` = 'livree' then 1 end) * 100.0 / nullif(count(`co`.`id`),0),2) AS `taux_reussite`, sum(case when `co`.`statut` = 'livree' then `co`.`prix_total` else 0 end) AS `revenus_generes` FROM (`coursiers` `c` left join `commandes` `co` on(`c`.`id` = `co`.`coursier_id` and date_format(`co`.`created_at`,'%Y-%m') = date_format(current_timestamp(),'%Y-%m'))) WHERE `c`.`statut` = 'actif' GROUP BY `c`.`id` ;

--
-- Index pour les tables dÃ©chargÃ©es
--

--
-- Index pour la table `account_history`
--
ALTER TABLE `account_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `coursier_id` (`coursier_id`);

--
-- Index pour la table `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Index pour la table `admin_actions`
--
ALTER TABLE `admin_actions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_admin` (`admin_id`),
  ADD KEY `idx_action` (`action_type`),
  ADD KEY `idx_date` (`date_creation`);

--
-- Index pour la table `admin_audit_unified`
--
ALTER TABLE `admin_audit_unified`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_timestamp` (`timestamp`),
  ADD KEY `idx_admin` (`admin_id`),
  ADD KEY `idx_action` (`action_type`),
  ADD KEY `idx_interface` (`interface_source`);

--
-- Index pour la table `admin_chat_status`
--
ALTER TABLE `admin_chat_status`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `agents`
--
ALTER TABLE `agents`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `telephone` (`telephone`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_statut` (`statut`),
  ADD KEY `idx_en_ligne` (`en_ligne`);

--
-- Index pour la table `agents_suzosky`
--
ALTER TABLE `agents_suzosky`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `matricule` (`matricule`),
  ADD UNIQUE KEY `uniq_telephone` (`telephone`);

--
-- Index pour la table `agents_unified`
--
ALTER TABLE `agents_unified`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `matricule` (`matricule`),
  ADD KEY `idx_matricule` (`matricule`),
  ADD KEY `idx_type_statut` (`type_poste`,`statut`),
  ADD KEY `idx_telephone` (`telephone`);

--
-- Index pour la table `agent_chats`
--
ALTER TABLE `agent_chats`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `chat_id` (`chat_id`),
  ADD KEY `idx_agent_chats_activity` (`last_activity`);

--
-- Index pour la table `agent_messages`
--
ALTER TABLE `agent_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `chat_id` (`chat_id`),
  ADD KEY `idx_agent_messages_read` (`is_read`,`sender_type`);

--
-- Index pour la table `app_crashes`
--
ALTER TABLE `app_crashes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_device` (`device_id`),
  ADD KEY `idx_crash_hash` (`crash_hash`),
  ADD KEY `idx_version` (`app_version_code`),
  ADD KEY `idx_type` (`crash_type`),
  ADD KEY `idx_resolved` (`is_resolved`);

--
-- Index pour la table `app_devices`
--
ALTER TABLE `app_devices`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_device` (`device_id`),
  ADD KEY `idx_courier` (`courier_id`),
  ADD KEY `idx_version` (`app_version_code`),
  ADD KEY `idx_last_seen` (`last_seen`);

--
-- Index pour la table `app_events`
--
ALTER TABLE `app_events`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_device` (`device_id`),
  ADD KEY `idx_event_type` (`event_type`),
  ADD KEY `idx_occurred` (`occurred_at`);

--
-- Index pour la table `app_notifications`
--
ALTER TABLE `app_notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_device` (`device_id`),
  ADD KEY `idx_type` (`notification_type`),
  ADD KEY `idx_sent` (`sent_at`);

--
-- Index pour la table `app_sessions`
--
ALTER TABLE `app_sessions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_session` (`session_id`),
  ADD KEY `idx_device` (`device_id`),
  ADD KEY `idx_started` (`started_at`);

--
-- Index pour la table `app_versions`
--
ALTER TABLE `app_versions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_version_code` (`version_code`),
  ADD KEY `idx_version_name` (`version_name`),
  ADD KEY `idx_active` (`is_active`);

--
-- Index pour la table `bonus_penalites`
--
ALTER TABLE `bonus_penalites`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_coursier` (`coursier_id`),
  ADD KEY `idx_type` (`type`),
  ADD KEY `idx_periode` (`periode_reference`),
  ADD KEY `idx_applique` (`applique`),
  ADD KEY `commande_id` (`commande_id`),
  ADD KEY `approuve_par` (`approuve_par`);

--
-- Index pour la table `business_clients`
--
ALTER TABLE `business_clients`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `id_business` (`id_business`),
  ADD KEY `idx_nom_entreprise` (`nom_entreprise`),
  ADD KEY `idx_email` (`contact_email`),
  ADD KEY `idx_date_creation` (`date_creation`);

--
-- Index pour la table `candidatures`
--
ALTER TABLE `candidatures`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_offre` (`offre_id`),
  ADD KEY `idx_statut` (`statut`),
  ADD KEY `idx_telephone` (`telephone`);

--
-- Index pour la table `candidatures_suzosky`
--
ALTER TABLE `candidatures_suzosky`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `chat_admins`
--
ALTER TABLE `chat_admins`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_admin` (`admin_id`);

--
-- Index pour la table `chat_blocked_devices`
--
ALTER TABLE `chat_blocked_devices`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_device` (`device_identifier`),
  ADD KEY `idx_ip` (`ip_address`);

--
-- Index pour la table `chat_conversations`
--
ALTER TABLE `chat_conversations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_type` (`type`),
  ADD KEY `idx_client_id` (`client_id`),
  ADD KEY `idx_last_timestamp` (`last_timestamp`);

--
-- Index pour la table `chat_messages`
--
ALTER TABLE `chat_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_conversation_id` (`conversation_id`),
  ADD KEY `idx_timestamp` (`timestamp`),
  ADD KEY `idx_sender` (`sender_type`,`sender_id`);

--
-- Index pour la table `chat_messages_suzosky`
--
ALTER TABLE `chat_messages_suzosky`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_session` (`session_id`),
  ADD KEY `idx_created` (`created_at`);

--
-- Index pour la table `chat_notifications`
--
ALTER TABLE `chat_notifications`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `session_id` (`session_id`),
  ADD KEY `idx_session` (`session_id`),
  ADD KEY `idx_read` (`is_read`);

--
-- Index pour la table `chat_quick_replies`
--
ALTER TABLE `chat_quick_replies`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `chat_sessions`
--
ALTER TABLE `chat_sessions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `session_id` (`session_id`),
  ADD UNIQUE KEY `device_identifier` (`device_identifier`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_device` (`device_identifier`),
  ADD KEY `idx_created` (`created_at`),
  ADD KEY `idx_priority` (`priority`);

--
-- Index pour la table `chat_unified`
--
ALTER TABLE `chat_unified`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_chat` (`chat_id`),
  ADD KEY `idx_type` (`type_chat`),
  ADD KEY `idx_unread` (`is_read`),
  ADD KEY `idx_timestamp` (`timestamp`);

--
-- Index pour la table `clients`
--
ALTER TABLE `clients`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `telephone` (`telephone`),
  ADD KEY `idx_telephone` (`telephone`);

--
-- Index pour la table `clients_business`
--
ALTER TABLE `clients_business`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `telephone` (`telephone`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_telephone` (`telephone`),
  ADD KEY `idx_statut` (`statut`);

--
-- Index pour la table `clients_business_unified`
--
ALTER TABLE `clients_business_unified`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_telephone` (`contact_telephone`),
  ADD KEY `idx_statut` (`statut`),
  ADD KEY `idx_ville` (`ville`);

--
-- Index pour la table `clients_particuliers`
--
ALTER TABLE `clients_particuliers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `telephone` (`telephone`),
  ADD KEY `idx_telephone` (`telephone`),
  ADD KEY `idx_date_creation` (`date_creation`),
  ADD KEY `idx_statut` (`statut`),
  ADD KEY `idx_email` (`email`);

--
-- Index pour la table `clients_unified`
--
ALTER TABLE `clients_unified`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code_client` (`code_client`),
  ADD KEY `idx_telephone` (`telephone`),
  ADD KEY `idx_statut` (`statut`),
  ADD KEY `idx_type` (`type_client`),
  ADD KEY `idx_concierge` (`concierge_id`);

--
-- Index pour la table `commandes`
--
ALTER TABLE `commandes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code_commande` (`code_commande`),
  ADD UNIQUE KEY `order_number` (`order_number`),
  ADD KEY `idx_code` (`code_commande`),
  ADD KEY `idx_statut` (`statut`),
  ADD KEY `idx_client_telephone` (`client_telephone`),
  ADD KEY `idx_coursier` (`coursier_id`),
  ADD KEY `idx_client_business` (`client_business_id`),
  ADD KEY `idx_date` (`created_at`),
  ADD KEY `idx_zone_retrait` (`zone_retrait`),
  ADD KEY `idx_priorite` (`priorite`),
  ADD KEY `client_id` (`client_id`),
  ADD KEY `idx_commandes_date_statut` (`created_at`,`statut`),
  ADD KEY `idx_commandes_coursier_date` (`coursier_id`,`created_at`);

--
-- Index pour la table `commandes_classiques`
--
ALTER TABLE `commandes_classiques`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_coursier` (`coursier_id`);

--
-- Index pour la table `commandes_coursier`
--
ALTER TABLE `commandes_coursier`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_coursier_id` (`coursier_id`),
  ADD KEY `idx_statut` (`statut`),
  ADD KEY `idx_date_commande` (`date_commande`);

--
-- Index pour la table `commandes_coursiers`
--
ALTER TABLE `commandes_coursiers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_pair` (`commande_id`,`coursier_id`),
  ADD KEY `idx_coursier` (`coursier_id`);

--
-- Index pour la table `commandes_suzosky`
--
ALTER TABLE `commandes_suzosky`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `numero_commande` (`numero_commande`),
  ADD KEY `coursier_id` (`coursier_id`);

--
-- Index pour la table `commandes_unified`
--
ALTER TABLE `commandes_unified`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `numero_commande` (`numero_commande`),
  ADD KEY `client_id` (`client_id`),
  ADD KEY `idx_numero` (`numero_commande`),
  ADD KEY `idx_statut` (`statut`),
  ADD KEY `idx_date` (`date_commande`),
  ADD KEY `idx_agent` (`agent_id`);

--
-- Index pour la table `comptes_clients_business`
--
ALTER TABLE `comptes_clients_business`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `client_id` (`client_id`);

--
-- Index pour la table `comptes_clients_particuliers`
--
ALTER TABLE `comptes_clients_particuliers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `client_id` (`client_id`);

--
-- Index pour la table `comptes_coursiers`
--
ALTER TABLE `comptes_coursiers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `coursier_id` (`coursier_id`);

--
-- Index pour la table `configuration`
--
ALTER TABLE `configuration`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `cle` (`cle`),
  ADD KEY `idx_cle` (`cle`);

--
-- Index pour la table `conversations`
--
ALTER TABLE `conversations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_participants` (`participant1_type`,`participant1_id`,`participant2_type`,`participant2_id`),
  ADD KEY `idx_commande` (`commande_id`),
  ADD KEY `idx_statut` (`statut`);

--
-- Index pour la table `coursiers`
--
ALTER TABLE `coursiers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `telephone` (`telephone`),
  ADD KEY `idx_telephone` (`telephone`),
  ADD KEY `idx_statut` (`statut`),
  ADD KEY `idx_disponible` (`disponible`),
  ADD KEY `idx_zones` (`zones_couvertes`(100));

--
-- Index pour la table `coursiers_stats`
--
ALTER TABLE `coursiers_stats`
  ADD PRIMARY KEY (`id`),
  ADD KEY `agent_id` (`agent_id`);

--
-- Index pour la table `coursiers_stats_suzosky`
--
ALTER TABLE `coursiers_stats_suzosky`
  ADD PRIMARY KEY (`id`),
  ADD KEY `agent_id` (`agent_id`);

--
-- Index pour la table `coursier_chats`
--
ALTER TABLE `coursier_chats`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_coursier_chats_activity` (`last_activity`);

--
-- Index pour la table `coursier_messages`
--
ALTER TABLE `coursier_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_coursier_messages_read` (`is_read`,`sender_type`),
  ADD KEY `fk_coursier_messages_chat` (`chat_id`);

--
-- Index pour la table `coursier_wallet_history`
--
ALTER TABLE `coursier_wallet_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_coursier` (`coursier_id`),
  ADD KEY `idx_type` (`type`),
  ADD KEY `idx_date` (`date_creation`);

--
-- Index pour la table `delivery_otps`
--
ALTER TABLE `delivery_otps`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `commande_id` (`commande_id`),
  ADD KEY `idx_expires` (`expires_at`);

--
-- Index pour la table `delivery_proofs`
--
ALTER TABLE `delivery_proofs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_commande` (`commande_id`),
  ADD KEY `idx_type` (`type`),
  ADD KEY `idx_uploaded_by` (`uploaded_by`);

--
-- Index pour la table `device_tokens`
--
ALTER TABLE `device_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_token` (`token`),
  ADD KEY `idx_coursier` (`coursier_id`),
  ADD KEY `idx_active` (`is_active`);

--
-- Index pour la table `dispatch_locks`
--
ALTER TABLE `dispatch_locks`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `commande_id` (`commande_id`),
  ADD KEY `idx_locked_by` (`locked_by`);

--
-- Index pour la table `logs_activites`
--
ALTER TABLE `logs_activites`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_type` (`type_activite`),
  ADD KEY `idx_date` (`date_activite`);

--
-- Index pour la table `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_conversation` (`conversation_id`),
  ADD KEY `idx_sender` (`sender_type`,`sender_id`),
  ADD KEY `idx_date` (`created_at`),
  ADD KEY `idx_lu` (`lu`),
  ADD KEY `idx_messages_conversation_date` (`conversation_id`,`created_at`);

--
-- Index pour la table `notifications_log`
--
ALTER TABLE `notifications_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_commande` (`commande_id`),
  ADD KEY `idx_destinataire` (`destinataire_type`,`destinataire_id`),
  ADD KEY `idx_type` (`type_notification`),
  ADD KEY `idx_statut` (`statut`),
  ADD KEY `idx_date` (`created_at`);

--
-- Index pour la table `offres_emploi`
--
ALTER TABLE `offres_emploi`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_statut` (`statut`),
  ADD KEY `idx_expiration` (`date_expiration`),
  ADD KEY `idx_created_by` (`created_by`);

--
-- Index pour la table `order_payments`
--
ALTER TABLE `order_payments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `transaction_id` (`transaction_id`),
  ADD KEY `idx_order_number` (`order_number`);

--
-- Index pour la table `order_status_history`
--
ALTER TABLE `order_status_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_commande` (`commande_id`),
  ADD KEY `idx_new_status` (`new_status`),
  ADD KEY `idx_coursier` (`coursier_id`);

--
-- Index pour la table `parametres_tarification`
--
ALTER TABLE `parametres_tarification`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `parametre` (`parametre`);

--
-- Index pour la table `payment_transactions`
--
ALTER TABLE `payment_transactions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `transaction_id` (`transaction_id`),
  ADD KEY `idx_coursier` (`coursier_id`),
  ADD KEY `idx_transaction` (`transaction_id`),
  ADD KEY `idx_statut` (`statut`),
  ADD KEY `idx_date` (`date_creation`);

--
-- Index pour la table `payouts_minimal`
--
ALTER TABLE `payouts_minimal`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_coursier_period` (`coursier_id`,`period_label`),
  ADD KEY `idx_statut` (`statut`);

--
-- Index pour la table `postes`
--
ALTER TABLE `postes`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `recharges`
--
ALTER TABLE `recharges`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `cinetpay_transaction_id` (`cinetpay_transaction_id`),
  ADD KEY `idx_coursier` (`coursier_id`),
  ADD KEY `idx_status` (`status`);

--
-- Index pour la table `recharges_coursiers`
--
ALTER TABLE `recharges_coursiers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `coursier_id` (`coursier_id`);

--
-- Index pour la table `sessions_unified`
--
ALTER TABLE `sessions_unified`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `session_id` (`session_id`),
  ADD KEY `idx_session` (`session_id`),
  ADD KEY `idx_user` (`user_type`,`user_id`),
  ADD KEY `idx_activity` (`last_activity`),
  ADD KEY `idx_active` (`is_active`);

--
-- Index pour la table `support_chats`
--
ALTER TABLE `support_chats`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_support_chats_activity` (`last_activity`);

--
-- Index pour la table `support_messages`
--
ALTER TABLE `support_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_support_messages_read` (`is_read`,`sender_type`),
  ADD KEY `fk_support_messages_chat` (`chat_id`);

--
-- Index pour la table `system_sync_heartbeats`
--
ALTER TABLE `system_sync_heartbeats`
  ADD PRIMARY KEY (`component`);

--
-- Index pour la table `tarifs`
--
ALTER TABLE `tarifs`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `tracking_coursiers`
--
ALTER TABLE `tracking_coursiers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_coursier_created` (`coursier_id`,`created_at`),
  ADD KEY `idx_coursier_updated` (`coursier_id`,`updated_at`);

--
-- Index pour la table `transactions`
--
ALTER TABLE `transactions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `reference_transaction` (`reference_transaction`),
  ADD KEY `idx_commande` (`commande_id`),
  ADD KEY `idx_reference` (`reference_transaction`),
  ADD KEY `idx_statut` (`statut`),
  ADD KEY `idx_methode` (`methode_paiement`),
  ADD KEY `idx_transactions_date` (`created_at`);

--
-- Index pour la table `transactions_financieres`
--
ALTER TABLE `transactions_financieres`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT pour les tables dÃ©chargÃ©es
--

--
-- AUTO_INCREMENT pour la table `account_history`
--
ALTER TABLE `account_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `admins`
--
ALTER TABLE `admins`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT pour la table `admin_actions`
--
ALTER TABLE `admin_actions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT pour la table `admin_audit_unified`
--
ALTER TABLE `admin_audit_unified`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `agents`
--
ALTER TABLE `agents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT pour la table `agents_suzosky`
--
ALTER TABLE `agents_suzosky`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT pour la table `agents_unified`
--
ALTER TABLE `agents_unified`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `agent_chats`
--
ALTER TABLE `agent_chats`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `agent_messages`
--
ALTER TABLE `agent_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `app_crashes`
--
ALTER TABLE `app_crashes`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT pour la table `app_devices`
--
ALTER TABLE `app_devices`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT pour la table `app_events`
--
ALTER TABLE `app_events`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `app_notifications`
--
ALTER TABLE `app_notifications`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `app_sessions`
--
ALTER TABLE `app_sessions`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=40;

--
-- AUTO_INCREMENT pour la table `app_versions`
--
ALTER TABLE `app_versions`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT pour la table `bonus_penalites`
--
ALTER TABLE `bonus_penalites`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `business_clients`
--
ALTER TABLE `business_clients`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `candidatures`
--
ALTER TABLE `candidatures`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `candidatures_suzosky`
--
ALTER TABLE `candidatures_suzosky`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT pour la table `chat_admins`
--
ALTER TABLE `chat_admins`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `chat_blocked_devices`
--
ALTER TABLE `chat_blocked_devices`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `chat_conversations`
--
ALTER TABLE `chat_conversations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;

--
-- AUTO_INCREMENT pour la table `chat_messages`
--
ALTER TABLE `chat_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT pour la table `chat_messages_suzosky`
--
ALTER TABLE `chat_messages_suzosky`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT pour la table `chat_notifications`
--
ALTER TABLE `chat_notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT pour la table `chat_quick_replies`
--
ALTER TABLE `chat_quick_replies`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=187;

--
-- AUTO_INCREMENT pour la table `chat_sessions`
--
ALTER TABLE `chat_sessions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `chat_unified`
--
ALTER TABLE `chat_unified`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `clients`
--
ALTER TABLE `clients`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=162;

--
-- AUTO_INCREMENT pour la table `clients_business`
--
ALTER TABLE `clients_business`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT pour la table `clients_business_unified`
--
ALTER TABLE `clients_business_unified`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `clients_particuliers`
--
ALTER TABLE `clients_particuliers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=80;

--
-- AUTO_INCREMENT pour la table `clients_unified`
--
ALTER TABLE `clients_unified`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `commandes`
--
ALTER TABLE `commandes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=110;

--
-- AUTO_INCREMENT pour la table `commandes_classiques`
--
ALTER TABLE `commandes_classiques`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT pour la table `commandes_coursier`
--
ALTER TABLE `commandes_coursier`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT pour la table `commandes_coursiers`
--
ALTER TABLE `commandes_coursiers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT pour la table `commandes_suzosky`
--
ALTER TABLE `commandes_suzosky`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `commandes_unified`
--
ALTER TABLE `commandes_unified`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `comptes_clients_business`
--
ALTER TABLE `comptes_clients_business`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `comptes_clients_particuliers`
--
ALTER TABLE `comptes_clients_particuliers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `comptes_coursiers`
--
ALTER TABLE `comptes_coursiers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1403;

--
-- AUTO_INCREMENT pour la table `configuration`
--
ALTER TABLE `configuration`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT pour la table `conversations`
--
ALTER TABLE `conversations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `coursiers`
--
ALTER TABLE `coursiers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT pour la table `coursiers_stats`
--
ALTER TABLE `coursiers_stats`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `coursiers_stats_suzosky`
--
ALTER TABLE `coursiers_stats_suzosky`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `coursier_chats`
--
ALTER TABLE `coursier_chats`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `coursier_messages`
--
ALTER TABLE `coursier_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `coursier_wallet_history`
--
ALTER TABLE `coursier_wallet_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `delivery_otps`
--
ALTER TABLE `delivery_otps`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `delivery_proofs`
--
ALTER TABLE `delivery_proofs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `device_tokens`
--
ALTER TABLE `device_tokens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `dispatch_locks`
--
ALTER TABLE `dispatch_locks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT pour la table `logs_activites`
--
ALTER TABLE `logs_activites`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `messages`
--
ALTER TABLE `messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `notifications_log`
--
ALTER TABLE `notifications_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `offres_emploi`
--
ALTER TABLE `offres_emploi`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `order_payments`
--
ALTER TABLE `order_payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=74;

--
-- AUTO_INCREMENT pour la table `order_status_history`
--
ALTER TABLE `order_status_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `parametres_tarification`
--
ALTER TABLE `parametres_tarification`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=113;

--
-- AUTO_INCREMENT pour la table `payment_transactions`
--
ALTER TABLE `payment_transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `payouts_minimal`
--
ALTER TABLE `payouts_minimal`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `postes`
--
ALTER TABLE `postes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT pour la table `recharges`
--
ALTER TABLE `recharges`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT pour la table `recharges_coursiers`
--
ALTER TABLE `recharges_coursiers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `sessions_unified`
--
ALTER TABLE `sessions_unified`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `support_chats`
--
ALTER TABLE `support_chats`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT pour la table `support_messages`
--
ALTER TABLE `support_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT pour la table `tarifs`
--
ALTER TABLE `tarifs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT pour la table `tracking_coursiers`
--
ALTER TABLE `tracking_coursiers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT pour la table `transactions`
--
ALTER TABLE `transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `transactions_financieres`
--
ALTER TABLE `transactions_financieres`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Contraintes pour les tables dÃ©chargÃ©es
--

--
-- Contraintes pour la table `account_history`
--
ALTER TABLE `account_history`
  ADD CONSTRAINT `account_history_ibfk_1` FOREIGN KEY (`coursier_id`) REFERENCES `coursiers` (`id`);

--
-- Contraintes pour la table `agent_messages`
--
ALTER TABLE `agent_messages`
  ADD CONSTRAINT `agent_messages_ibfk_1` FOREIGN KEY (`chat_id`) REFERENCES `agent_chats` (`chat_id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `bonus_penalites`
--
ALTER TABLE `bonus_penalites`
  ADD CONSTRAINT `bonus_penalites_ibfk_1` FOREIGN KEY (`coursier_id`) REFERENCES `coursiers` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `bonus_penalites_ibfk_2` FOREIGN KEY (`commande_id`) REFERENCES `commandes` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `bonus_penalites_ibfk_3` FOREIGN KEY (`approuve_par`) REFERENCES `agents` (`id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `candidatures`
--
ALTER TABLE `candidatures`
  ADD CONSTRAINT `candidatures_ibfk_1` FOREIGN KEY (`offre_id`) REFERENCES `offres_emploi` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `chat_messages`
--
ALTER TABLE `chat_messages`
  ADD CONSTRAINT `fk_chat_messages_conversation` FOREIGN KEY (`conversation_id`) REFERENCES `chat_conversations` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `clients_unified`
--
ALTER TABLE `clients_unified`
  ADD CONSTRAINT `clients_unified_ibfk_1` FOREIGN KEY (`concierge_id`) REFERENCES `agents_unified` (`id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `commandes`
--
ALTER TABLE `commandes`
  ADD CONSTRAINT `commandes_ibfk_1` FOREIGN KEY (`coursier_id`) REFERENCES `coursiers` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `commandes_ibfk_2` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `commandes_ibfk_3` FOREIGN KEY (`client_business_id`) REFERENCES `clients_business` (`id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `commandes_suzosky`
--
ALTER TABLE `commandes_suzosky`
  ADD CONSTRAINT `commandes_suzosky_ibfk_1` FOREIGN KEY (`coursier_id`) REFERENCES `agents_suzosky` (`id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `commandes_unified`
--
ALTER TABLE `commandes_unified`
  ADD CONSTRAINT `commandes_unified_ibfk_1` FOREIGN KEY (`client_id`) REFERENCES `clients_business_unified` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `commandes_unified_ibfk_2` FOREIGN KEY (`agent_id`) REFERENCES `agents_unified` (`id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `comptes_coursiers`
--
ALTER TABLE `comptes_coursiers`
  ADD CONSTRAINT `comptes_coursiers_ibfk_1` FOREIGN KEY (`coursier_id`) REFERENCES `coursiers` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `conversations`
--
ALTER TABLE `conversations`
  ADD CONSTRAINT `conversations_ibfk_1` FOREIGN KEY (`commande_id`) REFERENCES `commandes` (`id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `coursiers_stats`
--
ALTER TABLE `coursiers_stats`
  ADD CONSTRAINT `coursiers_stats_ibfk_1` FOREIGN KEY (`agent_id`) REFERENCES `agents` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `coursiers_stats_suzosky`
--
ALTER TABLE `coursiers_stats_suzosky`
  ADD CONSTRAINT `coursiers_stats_suzosky_ibfk_1` FOREIGN KEY (`agent_id`) REFERENCES `agents_suzosky` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `coursier_messages`
--
ALTER TABLE `coursier_messages`
  ADD CONSTRAINT `fk_coursier_messages_chat` FOREIGN KEY (`chat_id`) REFERENCES `coursier_chats` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `messages`
--
ALTER TABLE `messages`
  ADD CONSTRAINT `messages_ibfk_1` FOREIGN KEY (`conversation_id`) REFERENCES `conversations` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `notifications_log`
--
ALTER TABLE `notifications_log`
  ADD CONSTRAINT `notifications_log_ibfk_1` FOREIGN KEY (`commande_id`) REFERENCES `commandes` (`id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `offres_emploi`
--
ALTER TABLE `offres_emploi`
  ADD CONSTRAINT `offres_emploi_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `agents` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `recharges`
--
ALTER TABLE `recharges`
  ADD CONSTRAINT `recharges_ibfk_1` FOREIGN KEY (`coursier_id`) REFERENCES `clients_particuliers` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `recharges_coursiers`
--
ALTER TABLE `recharges_coursiers`
  ADD CONSTRAINT `recharges_coursiers_ibfk_1` FOREIGN KEY (`coursier_id`) REFERENCES `coursiers` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `transactions`
--
ALTER TABLE `transactions`
  ADD CONSTRAINT `transactions_ibfk_1` FOREIGN KEY (`commande_id`) REFERENCES `commandes` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

