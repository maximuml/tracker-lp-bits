-- NexusPHP schema snapshot at tag v2.0.1 (commit a67ff123).
-- Purpose: migration-upgrade CI fixture -- loads the previous release's
-- database state (schema + migrations table), then HEAD migrations run on top.
-- Regenerate on each release: mysqldump -unexusphp --single-transaction \
--   --no-tablespaces --set-gtid-purged=OFF --skip-comments nexusphp
--   > database/schema/<new-tag>.mysql.sql  (from a DB migrated at that tag)
--

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
DROP TABLE IF EXISTS `activity_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `activity_log` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `log_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `subject_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `event` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `subject_id` bigint unsigned DEFAULT NULL,
  `causer_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `causer_id` bigint unsigned DEFAULT NULL,
  `properties` json DEFAULT NULL,
  `batch_uuid` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `subject` (`subject_type`,`subject_id`),
  KEY `causer` (`causer_type`,`causer_id`),
  KEY `activity_log_log_name_index` (`log_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `activity_log` WRITE;
/*!40000 ALTER TABLE `activity_log` DISABLE KEYS */;
/*!40000 ALTER TABLE `activity_log` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `adclicks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `adclicks` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `adid` int unsigned DEFAULT NULL,
  `userid` int unsigned DEFAULT NULL,
  `added` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `adclicks` WRITE;
/*!40000 ALTER TABLE `adclicks` DISABLE KEYS */;
/*!40000 ALTER TABLE `adclicks` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `adminpanel`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `adminpanel` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `url` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `info` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `adminpanel` WRITE;
/*!40000 ALTER TABLE `adminpanel` DISABLE KEYS */;
/*!40000 ALTER TABLE `adminpanel` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `agent_allowed_exception`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `agent_allowed_exception` (
  `family_id` tinyint unsigned NOT NULL DEFAULT '0',
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `peer_id` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `agent` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `comment` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  PRIMARY KEY (`id`),
  KEY `agent_allowed_exception_family_id_index` (`family_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `agent_allowed_exception` WRITE;
/*!40000 ALTER TABLE `agent_allowed_exception` DISABLE KEYS */;
/*!40000 ALTER TABLE `agent_allowed_exception` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `agent_allowed_family`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `agent_allowed_family` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `family` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `start_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `peer_id_pattern` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `peer_id_match_num` tinyint unsigned NOT NULL DEFAULT '0',
  `peer_id_matchtype` enum('dec','hex') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'dec',
  `peer_id_start` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `agent_pattern` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `agent_match_num` tinyint unsigned NOT NULL DEFAULT '0',
  `agent_matchtype` enum('dec','hex') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'dec',
  `agent_start` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `exception` enum('yes','no') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'no',
  `allowhttps` enum('yes','no') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'no',
  `comment` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `hits` mediumint unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `agent_allowed_family` WRITE;
/*!40000 ALTER TABLE `agent_allowed_family` DISABLE KEYS */;
/*!40000 ALTER TABLE `agent_allowed_family` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `attachments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `attachments` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `userid` mediumint unsigned NOT NULL DEFAULT '0',
  `width` smallint unsigned NOT NULL DEFAULT '0',
  `added` datetime DEFAULT NULL,
  `filename` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `dlkey` char(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `filetype` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `filesize` bigint unsigned NOT NULL DEFAULT '0',
  `location` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `downloads` mediumint NOT NULL DEFAULT '0',
  `isimage` smallint unsigned NOT NULL DEFAULT '0',
  `thumb` smallint unsigned NOT NULL DEFAULT '0',
  `driver` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'local',
  PRIMARY KEY (`id`),
  KEY `pid` (`userid`,`id`),
  KEY `attachments_added_isimage_downloads_index` (`added`,`isimage`,`downloads`),
  KEY `attachments_dlkey_index` (`dlkey`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `attachments` WRITE;
/*!40000 ALTER TABLE `attachments` DISABLE KEYS */;
/*!40000 ALTER TABLE `attachments` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `attendance`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `attendance` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uid` int unsigned NOT NULL DEFAULT '0',
  `added` datetime NOT NULL,
  `points` int unsigned NOT NULL DEFAULT '0',
  `days` int unsigned NOT NULL DEFAULT '1',
  `total_days` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `attendance_uid_index` (`uid`),
  KEY `attendance_added_index` (`added`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `attendance` WRITE;
/*!40000 ALTER TABLE `attendance` DISABLE KEYS */;
/*!40000 ALTER TABLE `attendance` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `attendance_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `attendance_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uid` int NOT NULL,
  `points` int NOT NULL,
  `date` date NOT NULL,
  `is_retroactive` smallint NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `attendance_logs_uid_date_unique` (`uid`,`date`),
  KEY `attendance_logs_date_index` (`date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `attendance_logs` WRITE;
/*!40000 ALTER TABLE `attendance_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `attendance_logs` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `audiocodecs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `audiocodecs` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `image` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `sort_index` tinyint unsigned NOT NULL DEFAULT '0',
  `mode` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `audiocodecs` WRITE;
/*!40000 ALTER TABLE `audiocodecs` DISABLE KEYS */;
/*!40000 ALTER TABLE `audiocodecs` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `avps`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `avps` (
  `arg` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `value_s` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `value_i` int NOT NULL DEFAULT '0',
  `value_u` int unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`arg`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `avps` WRITE;
/*!40000 ALTER TABLE `avps` DISABLE KEYS */;
/*!40000 ALTER TABLE `avps` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `bans`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `bans` (
  `id` smallint unsigned NOT NULL AUTO_INCREMENT,
  `added` datetime DEFAULT NULL,
  `addedby` mediumint unsigned NOT NULL DEFAULT '0',
  `comment` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `first` bigint NOT NULL DEFAULT '0',
  `last` bigint NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `bans_first_last_index` (`first`,`last`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `bans` WRITE;
/*!40000 ALTER TABLE `bans` DISABLE KEYS */;
/*!40000 ALTER TABLE `bans` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `bitbucket`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `bitbucket` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `owner` mediumint unsigned NOT NULL DEFAULT '0',
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `added` datetime DEFAULT NULL,
  `public` enum('0','1') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `bitbucket` WRITE;
/*!40000 ALTER TABLE `bitbucket` DISABLE KEYS */;
/*!40000 ALTER TABLE `bitbucket` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `blocks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `blocks` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `userid` mediumint unsigned NOT NULL DEFAULT '0',
  `blockid` mediumint unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `blocks_userid_blockid_unique` (`userid`,`blockid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `blocks` WRITE;
/*!40000 ALTER TABLE `blocks` DISABLE KEYS */;
/*!40000 ALTER TABLE `blocks` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `bonus_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `bonus_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `business_type` int NOT NULL DEFAULT '0',
  `uid` int NOT NULL,
  `old_total_value` decimal(20,1) NOT NULL,
  `value` decimal(20,1) NOT NULL,
  `new_total_value` decimal(20,1) NOT NULL,
  `comment` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `bonus_logs_uid_index` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `bonus_logs` WRITE;
/*!40000 ALTER TABLE `bonus_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `bonus_logs` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `bookmarks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `bookmarks` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `torrentid` mediumint unsigned NOT NULL DEFAULT '0',
  `userid` mediumint unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `bookmarks_userid_torrentid_index` (`userid`,`torrentid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `bookmarks` WRITE;
/*!40000 ALTER TABLE `bookmarks` DISABLE KEYS */;
/*!40000 ALTER TABLE `bookmarks` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `categories` (
  `id` smallint unsigned NOT NULL AUTO_INCREMENT,
  `mode` int unsigned NOT NULL DEFAULT '0',
  `class_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT '',
  `name` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `image` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT '',
  `sort_index` smallint unsigned NOT NULL DEFAULT '0',
  `icon_id` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `categories_mode_sort_index_index` (`mode`,`sort_index`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `categories` WRITE;
/*!40000 ALTER TABLE `categories` DISABLE KEYS */;
/*!40000 ALTER TABLE `categories` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `caticons`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `caticons` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `folder` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `cssfile` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT '',
  `multilang` enum('yes','no') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'no',
  `secondicon` enum('yes','no') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'no',
  `designer` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT '',
  `comment` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT '',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `caticons` WRITE;
/*!40000 ALTER TABLE `caticons` DISABLE KEYS */;
/*!40000 ALTER TABLE `caticons` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `cheaters`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cheaters` (
  `id` mediumint unsigned NOT NULL AUTO_INCREMENT,
  `added` datetime DEFAULT NULL,
  `userid` mediumint unsigned NOT NULL DEFAULT '0',
  `torrentid` mediumint unsigned NOT NULL DEFAULT '0',
  `uploaded` bigint unsigned NOT NULL DEFAULT '0',
  `downloaded` bigint unsigned NOT NULL DEFAULT '0',
  `anctime` mediumint unsigned NOT NULL DEFAULT '0',
  `seeders` mediumint unsigned NOT NULL DEFAULT '0',
  `leechers` mediumint unsigned NOT NULL DEFAULT '0',
  `hit` tinyint unsigned NOT NULL DEFAULT '0',
  `dealtby` mediumint unsigned NOT NULL DEFAULT '0',
  `dealtwith` smallint NOT NULL DEFAULT '0',
  `comment` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `cheaters_torrentid_index` (`torrentid`),
  KEY `cheaters_userid_index` (`userid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `cheaters` WRITE;
/*!40000 ALTER TABLE `cheaters` DISABLE KEYS */;
/*!40000 ALTER TABLE `cheaters` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `chronicle`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `chronicle` (
  `id` mediumint unsigned NOT NULL AUTO_INCREMENT,
  `userid` mediumint unsigned NOT NULL DEFAULT '0',
  `added` datetime DEFAULT NULL,
  `txt` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`),
  KEY `chronicle_added_index` (`added`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `chronicle` WRITE;
/*!40000 ALTER TABLE `chronicle` DISABLE KEYS */;
/*!40000 ALTER TABLE `chronicle` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `codecs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `codecs` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `sort_index` tinyint unsigned NOT NULL DEFAULT '0',
  `mode` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `codecs` WRITE;
/*!40000 ALTER TABLE `codecs` DISABLE KEYS */;
/*!40000 ALTER TABLE `codecs` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `comments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `comments` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user` mediumint unsigned NOT NULL DEFAULT '0',
  `torrent` mediumint unsigned NOT NULL DEFAULT '0',
  `added` datetime DEFAULT NULL,
  `text` text COLLATE utf8mb4_unicode_ci,
  `ori_text` text COLLATE utf8mb4_unicode_ci,
  `editedby` mediumint unsigned NOT NULL DEFAULT '0',
  `editdate` datetime DEFAULT NULL,
  `offer` mediumint unsigned NOT NULL DEFAULT '0',
  `request` int NOT NULL DEFAULT '0',
  `anonymous` enum('yes','no') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'no',
  PRIMARY KEY (`id`),
  KEY `comments_torrent_id_index` (`torrent`,`id`),
  KEY `comments_offer_id_index` (`offer`,`id`),
  KEY `comments_user_index` (`user`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `comments` WRITE;
/*!40000 ALTER TABLE `comments` DISABLE KEYS */;
/*!40000 ALTER TABLE `comments` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `complain_replies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `complain_replies` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `complain` int NOT NULL,
  `userid` int NOT NULL DEFAULT '0',
  `added` datetime NOT NULL,
  `body` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `ip` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `complain_replies` WRITE;
/*!40000 ALTER TABLE `complain_replies` DISABLE KEYS */;
/*!40000 ALTER TABLE `complain_replies` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `complains`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `complains` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `body` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `added` datetime NOT NULL,
  `answered` smallint NOT NULL DEFAULT '0',
  `ip` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `complains` WRITE;
/*!40000 ALTER TABLE `complains` DISABLE KEYS */;
/*!40000 ALTER TABLE `complains` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `countries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `countries` (
  `id` smallint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `flagpic` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `countries` WRITE;
/*!40000 ALTER TABLE `countries` DISABLE KEYS */;
/*!40000 ALTER TABLE `countries` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `exam_progress`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `exam_progress` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `exam_user_id` int NOT NULL,
  `exam_id` int NOT NULL,
  `uid` int NOT NULL,
  `torrent_id` int NOT NULL,
  `index` int NOT NULL,
  `init_value` bigint NOT NULL DEFAULT '0',
  `value` bigint NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `exam_progress_exam_user_id_index` (`exam_user_id`),
  KEY `exam_progress_exam_id_index` (`exam_id`),
  KEY `exam_progress_uid_index` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `exam_progress` WRITE;
/*!40000 ALTER TABLE `exam_progress` DISABLE KEYS */;
/*!40000 ALTER TABLE `exam_progress` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `exam_users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `exam_users` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uid` int NOT NULL,
  `exam_id` int NOT NULL,
  `status` int NOT NULL DEFAULT '0',
  `begin` datetime DEFAULT NULL,
  `end` datetime DEFAULT NULL,
  `progress` text COLLATE utf8mb4_unicode_ci,
  `is_done` tinyint NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `exam_users_uid_index` (`uid`),
  KEY `exam_users_exam_id_index` (`exam_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `exam_users` WRITE;
/*!40000 ALTER TABLE `exam_users` DISABLE KEYS */;
/*!40000 ALTER TABLE `exam_users` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `exams`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `exams` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `begin` datetime DEFAULT NULL,
  `end` datetime DEFAULT NULL,
  `duration` int NOT NULL DEFAULT '0',
  `filters` text COLLATE utf8mb4_unicode_ci,
  `indexes` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` tinyint NOT NULL DEFAULT '0',
  `is_discovered` tinyint NOT NULL DEFAULT '0',
  `priority` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `recurring` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `type` int NOT NULL DEFAULT '1',
  `success_reward_bonus` int NOT NULL DEFAULT '0',
  `fail_deduct_bonus` int NOT NULL DEFAULT '0',
  `max_user_count` int NOT NULL DEFAULT '0',
  `background_color` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'blue',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `exams` WRITE;
/*!40000 ALTER TABLE `exams` DISABLE KEYS */;
/*!40000 ALTER TABLE `exams` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `failed_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `failed_jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `connection` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `exception` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `failed_jobs` WRITE;
/*!40000 ALTER TABLE `failed_jobs` DISABLE KEYS */;
/*!40000 ALTER TABLE `failed_jobs` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `faq`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `faq` (
  `id` smallint unsigned NOT NULL AUTO_INCREMENT,
  `link_id` smallint unsigned NOT NULL DEFAULT '0',
  `lang_id` smallint unsigned NOT NULL DEFAULT '6',
  `type` enum('categ','item') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'item',
  `question` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `answer` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `flag` tinyint unsigned NOT NULL DEFAULT '1',
  `categ` smallint unsigned NOT NULL DEFAULT '0',
  `order` smallint unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `faq` WRITE;
/*!40000 ALTER TABLE `faq` DISABLE KEYS */;
/*!40000 ALTER TABLE `faq` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `files`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `files` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `torrent` mediumint unsigned NOT NULL DEFAULT '0',
  `filename` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `size` bigint unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `files_torrent_index` (`torrent`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `files` WRITE;
/*!40000 ALTER TABLE `files` DISABLE KEYS */;
/*!40000 ALTER TABLE `files` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `forummods`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `forummods` (
  `id` smallint unsigned NOT NULL AUTO_INCREMENT,
  `forumid` smallint unsigned NOT NULL DEFAULT '0',
  `userid` mediumint unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `forummods_forumid_index` (`forumid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `forummods` WRITE;
/*!40000 ALTER TABLE `forummods` DISABLE KEYS */;
/*!40000 ALTER TABLE `forummods` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `forums`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `forums` (
  `id` smallint unsigned NOT NULL AUTO_INCREMENT,
  `sort` smallint unsigned NOT NULL DEFAULT '0',
  `name` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `description` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `minclassread` tinyint unsigned NOT NULL DEFAULT '0',
  `minclasswrite` tinyint unsigned NOT NULL DEFAULT '0',
  `postcount` int unsigned NOT NULL DEFAULT '0',
  `topiccount` int unsigned NOT NULL DEFAULT '0',
  `minclasscreate` tinyint unsigned NOT NULL DEFAULT '0',
  `forid` smallint unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `forums` WRITE;
/*!40000 ALTER TABLE `forums` DISABLE KEYS */;
/*!40000 ALTER TABLE `forums` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `friends`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `friends` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `userid` mediumint unsigned NOT NULL DEFAULT '0',
  `friendid` mediumint unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `friends_userid_friendid_unique` (`userid`,`friendid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `friends` WRITE;
/*!40000 ALTER TABLE `friends` DISABLE KEYS */;
/*!40000 ALTER TABLE `friends` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `funds`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `funds` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `usd` decimal(8,2) NOT NULL DEFAULT '0.00',
  `cny` decimal(8,2) NOT NULL DEFAULT '0.00',
  `user` mediumint unsigned NOT NULL DEFAULT '0',
  `added` datetime DEFAULT NULL,
  `memo` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `funds` WRITE;
/*!40000 ALTER TABLE `funds` DISABLE KEYS */;
/*!40000 ALTER TABLE `funds` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `hit_and_runs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `hit_and_runs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uid` int NOT NULL,
  `torrent_id` int NOT NULL,
  `snatched_id` int NOT NULL,
  `status` int NOT NULL DEFAULT '1',
  `comment` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `leech_time_no_seeder_begin` bigint NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `hit_and_runs_uid_torrent_id_unique` (`uid`,`torrent_id`),
  UNIQUE KEY `hit_and_runs_snatched_id_unique` (`snatched_id`),
  KEY `hit_and_runs_status_index` (`status`),
  KEY `hit_and_runs_created_at_index` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `hit_and_runs` WRITE;
/*!40000 ALTER TABLE `hit_and_runs` DISABLE KEYS */;
/*!40000 ALTER TABLE `hit_and_runs` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `invites`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `invites` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `inviter` mediumint unsigned NOT NULL DEFAULT '0',
  `invitee` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `hash` char(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `time_invited` datetime DEFAULT NULL,
  `valid` tinyint NOT NULL DEFAULT '1',
  `invitee_register_uid` int DEFAULT NULL,
  `invitee_register_email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `invitee_register_username` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `expired_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `pre_register_email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pre_register_username` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `invites_hash_index` (`hash`),
  KEY `invites_inviter_index` (`inviter`),
  KEY `invites_expired_at_index` (`expired_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `invites` WRITE;
/*!40000 ALTER TABLE `invites` DISABLE KEYS */;
/*!40000 ALTER TABLE `invites` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `iplog`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `iplog` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `ip` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `userid` mediumint unsigned NOT NULL DEFAULT '0',
  `access` datetime DEFAULT NULL,
  `uri` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `count` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `iplog_userid_index` (`userid`),
  KEY `iplog_access_index` (`access`),
  KEY `iplog_ip_index` (`ip`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `iplog` WRITE;
/*!40000 ALTER TABLE `iplog` DISABLE KEYS */;
/*!40000 ALTER TABLE `iplog` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `language`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `language` (
  `id` smallint unsigned NOT NULL AUTO_INCREMENT,
  `lang_name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `flagpic` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `sub_lang` tinyint unsigned NOT NULL DEFAULT '1',
  `rule_lang` tinyint unsigned NOT NULL DEFAULT '0',
  `site_lang` tinyint unsigned NOT NULL DEFAULT '0',
  `site_lang_folder` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `trans_state` enum('up-to-date','outdate','incomplete','need-new','unavailable') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'unavailable',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `language` WRITE;
/*!40000 ALTER TABLE `language` DISABLE KEYS */;
/*!40000 ALTER TABLE `language` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `locations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `locations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `location_main` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `location_sub` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `flagpic` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `start_ip` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `end_ip` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `theory_upspeed` int unsigned NOT NULL DEFAULT '10',
  `practical_upspeed` int unsigned NOT NULL DEFAULT '10',
  `theory_downspeed` int unsigned NOT NULL DEFAULT '10',
  `practical_downspeed` int unsigned NOT NULL DEFAULT '10',
  `hit` int unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `locations` WRITE;
/*!40000 ALTER TABLE `locations` DISABLE KEYS */;
/*!40000 ALTER TABLE `locations` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `login_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `login_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uid` int NOT NULL,
  `ip` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL,
  `country` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `city` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `client` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `login_logs_uid_index` (`uid`),
  KEY `login_logs_ip_index` (`ip`),
  KEY `login_logs_country_index` (`country`),
  KEY `login_logs_city_index` (`city`),
  KEY `login_logs_client_index` (`client`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `login_logs` WRITE;
/*!40000 ALTER TABLE `login_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `login_logs` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `loginattempts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `loginattempts` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `ip` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `added` datetime DEFAULT NULL,
  `banned` enum('yes','no') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'no',
  `attempts` smallint unsigned NOT NULL DEFAULT '0',
  `type` enum('login','recover') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'login',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `loginattempts` WRITE;
/*!40000 ALTER TABLE `loginattempts` DISABLE KEYS */;
/*!40000 ALTER TABLE `loginattempts` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `magic`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `magic` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `torrentid` int NOT NULL DEFAULT '0',
  `userid` int NOT NULL DEFAULT '0',
  `value` int NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `magic_torrentid_index` (`torrentid`),
  KEY `magic_userid_index` (`userid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `magic` WRITE;
/*!40000 ALTER TABLE `magic` DISABLE KEYS */;
/*!40000 ALTER TABLE `magic` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `medals`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `medals` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `get_type` int NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `image_large` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `image_small` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `price` int NOT NULL DEFAULT '0',
  `display_on_medal_page` int NOT NULL DEFAULT '1',
  `duration` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `sale_begin_time` datetime DEFAULT NULL,
  `sale_end_time` datetime DEFAULT NULL,
  `inventory` int DEFAULT NULL,
  `bonus_addition_factor` float NOT NULL DEFAULT '0',
  `bonus_addition_duration` int NOT NULL DEFAULT '0' COMMENT '魔力加成有效天数，0 永久有效',
  `gift_fee_factor` float NOT NULL DEFAULT '0',
  `priority` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `medals` WRITE;
/*!40000 ALTER TABLE `medals` DISABLE KEYS */;
/*!40000 ALTER TABLE `medals` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `media`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `media` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `sort_index` tinyint unsigned NOT NULL DEFAULT '0',
  `mode` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `media` WRITE;
/*!40000 ALTER TABLE `media` DISABLE KEYS */;
/*!40000 ALTER TABLE `media` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `message_templates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `message_templates` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `language_id` int NOT NULL,
  `content` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `message_templates_name_language_id_unique` (`name`,`language_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `message_templates` WRITE;
/*!40000 ALTER TABLE `message_templates` DISABLE KEYS */;
/*!40000 ALTER TABLE `message_templates` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `messages` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `sender` mediumint unsigned NOT NULL DEFAULT '0',
  `receiver` mediumint unsigned NOT NULL DEFAULT '0',
  `added` datetime DEFAULT NULL,
  `subject` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `msg` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `unread` enum('yes','no') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'yes',
  `location` smallint NOT NULL DEFAULT '1',
  `saved` enum('no','yes') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'no',
  PRIMARY KEY (`id`),
  KEY `messages_sender_index` (`sender`),
  KEY `messages_receiver_index` (`receiver`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `messages` WRITE;
/*!40000 ALTER TABLE `messages` DISABLE KEYS */;
/*!40000 ALTER TABLE `messages` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `migrations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=211 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `migrations` WRITE;
/*!40000 ALTER TABLE `migrations` DISABLE KEYS */;
INSERT INTO `migrations` VALUES (1,'2016_06_01_000001_create_oauth_auth_codes_table',1),(2,'2016_06_01_000002_create_oauth_access_tokens_table',1),(3,'2016_06_01_000003_create_oauth_refresh_tokens_table',1),(4,'2016_06_01_000004_create_oauth_clients_table',1),(5,'2016_06_01_000005_create_oauth_personal_access_clients_table',1),(6,'2019_12_14_000001_create_personal_access_tokens_table',1),(7,'2021_06_08_113437_create_adclicks_table',1),(8,'2021_06_08_113437_create_adminpanel_table',1),(9,'2021_06_08_113437_create_agent_allowed_exception_table',1),(10,'2021_06_08_113437_create_agent_allowed_family_table',1),(11,'2021_06_08_113437_create_attachments_table',1),(12,'2021_06_08_113437_create_attendance_table',1),(13,'2021_06_08_113437_create_audiocodecs_table',1),(14,'2021_06_08_113437_create_avps_table',1),(15,'2021_06_08_113437_create_bans_table',1),(16,'2021_06_08_113437_create_bitbucket_table',1),(17,'2021_06_08_113437_create_blocks_table',1),(18,'2021_06_08_113437_create_bookmarks_table',1),(19,'2021_06_08_113437_create_categories_table',1),(20,'2021_06_08_113437_create_caticons_table',1),(21,'2021_06_08_113437_create_cheaters_table',1),(22,'2021_06_08_113437_create_chronicle_table',1),(23,'2021_06_08_113437_create_codecs_table',1),(24,'2021_06_08_113437_create_comments_table',1),(25,'2021_06_08_113437_create_countries_table',1),(26,'2021_06_08_113437_create_exam_progress_table',1),(27,'2021_06_08_113437_create_exam_users_table',1),(28,'2021_06_08_113437_create_exams_table',1),(29,'2021_06_08_113437_create_failed_jobs_table',1),(30,'2021_06_08_113437_create_faq_table',1),(31,'2021_06_08_113437_create_files_table',1),(32,'2021_06_08_113437_create_forummods_table',1),(33,'2021_06_08_113437_create_forums_table',1),(34,'2021_06_08_113437_create_friends_table',1),(35,'2021_06_08_113437_create_funds_table',1),(36,'2021_06_08_113437_create_invites_table',1),(37,'2021_06_08_113437_create_iplog_table',1),(38,'2021_06_08_113437_create_language_table',1),(39,'2021_06_08_113437_create_locations_table',1),(40,'2021_06_08_113437_create_loginattempts_table',1),(41,'2021_06_08_113437_create_magic_table',1),(42,'2021_06_08_113437_create_media_table',1),(43,'2021_06_08_113437_create_messages_table',1),(44,'2021_06_08_113437_create_modpanel_table',1),(45,'2021_06_08_113437_create_news_table',1),(46,'2021_06_08_113437_create_offers_table',1),(47,'2021_06_08_113437_create_offervotes_table',1),(48,'2021_06_08_113437_create_overforums_table',1),(49,'2021_06_08_113437_create_peers_table',1),(50,'2021_06_08_113437_create_pmboxes_table',1),(51,'2021_06_08_113437_create_pollanswers_table',1),(52,'2021_06_08_113437_create_polls_table',1),(53,'2021_06_08_113437_create_posts_table',1),(54,'2021_06_08_113437_create_processings_table',1),(55,'2021_06_08_113437_create_readposts_table',1),(56,'2021_06_08_113437_create_regimages_table',1),(57,'2021_06_08_113437_create_reports_table',1),(58,'2021_06_08_113437_create_resreq_table',1),(59,'2021_06_08_113437_create_rules_table',1),(60,'2021_06_08_113437_create_searchbox_fields_table',1),(61,'2021_06_08_113437_create_searchbox_table',1),(62,'2021_06_08_113437_create_secondicons_table',1),(63,'2021_06_08_113437_create_settings_table',1),(64,'2021_06_08_113437_create_shoutbox_table',1),(65,'2021_06_08_113437_create_sitelog_table',1),(66,'2021_06_08_113437_create_snatched_table',1),(67,'2021_06_08_113437_create_sources_table',1),(68,'2021_06_08_113437_create_staffmessages_table',1),(69,'2021_06_08_113437_create_standards_table',1),(70,'2021_06_08_113437_create_stylesheets_table',1),(71,'2021_06_08_113437_create_suggest_table',1),(72,'2021_06_08_113437_create_sysoppanel_table',1),(73,'2021_06_08_113437_create_thanks_table',1),(74,'2021_06_08_113437_create_topics_table',1),(75,'2021_06_08_113437_create_torrent_secrets_table',1),(76,'2021_06_08_113437_create_torrents_custom_field_values_table',1),(77,'2021_06_08_113437_create_torrents_custom_fields_table',1),(78,'2021_06_08_113437_create_torrents_state_table',1),(79,'2021_06_08_113437_create_torrents_table',1),(80,'2021_06_08_113437_create_user_ban_logs_table',1),(81,'2021_06_08_113437_create_users_table',1),(82,'2021_06_10_181005_add_two_step_secret_to_users_table',1),(83,'2021_06_11_141214_add_init_value_to_exam_progress_table',1),(84,'2021_06_11_161551_add_completedat_index_to_snatched_table',1),(85,'2021_06_13_215440_add_total_days_to_attendance_table',1),(86,'2021_06_18_125347_create_hit_and_runs_table',1),(87,'2021_06_19_141415_add_hr_to_torrents_table',1),(88,'2021_06_20_005557_create_bonus_logs_table',1),(89,'2021_06_24_013107_add_seed_points_to_users_table',1),(90,'2022_01_06_023153_create_medals_table',1),(91,'2022_01_06_153905_create_user_medals_table',1),(92,'2022_02_25_021356_add_id_to_agent_allowed_exception_table',1),(93,'2022_03_07_012545_create_tags_table',1),(94,'2022_03_07_012753_create_torrent_tags_table',1),(95,'2022_03_08_040415_add_icon_id_to_categories_table',1),(96,'2022_03_08_041115_add_invitee_fields_to_invites_table',1),(97,'2022_03_08_041951_add_custom_fields_to_searchbox_table',1),(98,'2022_03_08_042734_add_pt_gen_tags_technical_info_to_torrents_table',1),(99,'2022_03_08_043201_add_page_to_users_table',1),(100,'2022_03_17_202628_add_index_to_cheaters_table',1),(101,'2022_03_19_020327_add_status_to_user_medals_table',1),(102,'2022_03_26_162038_add_margin_padding_to_tags_table',1),(103,'2022_04_02_163930_create_attendance_logs_table',1),(104,'2022_04_03_041642_add_attendance_card_to_users_table',1),(105,'2022_04_05_022036_handle_not_null_default_0000_datetime',1),(106,'2022_04_18_030257_handle_peers_peer_id_unique',1),(107,'2022_04_18_153140_add_priority_to_exams_table',1),(108,'2022_04_20_195415_add_ipv6_to_peers_table',1),(109,'2022_05_03_145712_add_times_field_to_magic_table',1),(110,'2022_05_03_155158_add_cover_to_torrents_table',1),(111,'2022_05_06_160029_create_complains_table',1),(112,'2022_05_06_165409_create_complain_replies_table',1),(113,'2022_05_06_191830_add_autoload_to_settings_table',1),(114,'2022_06_13_172104_create_torrent_operation_logs_table',1),(115,'2022_06_14_021936_add_approval_status_to_torrents_table',1),(116,'2022_07_08_215348_add_deadline_to_torrents_state_table',1),(117,'2022_07_20_194152_create_seedbox_records_table',1),(118,'2022_08_09_163552_create_user_metas_table',1),(119,'2022_08_09_235716_create_username_change_logs_table',1),(120,'2022_08_16_042239_create_torrent_deny_reasons_table',1),(121,'2022_08_22_030816_add_permission_to_staffmessages_table',1),(122,'2022_08_26_061516_add_begin_to_torrents_state_table',1),(123,'2022_09_02_031539_add_extra_to_searchbox_table',1),(124,'2022_09_05_230532_add_mode_to_section_related',1),(125,'2022_09_06_004318_add_section_name_to_searchbox_table',1),(126,'2022_09_06_030324_change_searchbox_field_extra_to_json',1),(127,'2022_09_12_181952_add_is_seed_box_to_peers_table',1),(128,'2022_09_13_204800_add_offer_allowed_count_to_users_table',1),(129,'2022_09_16_164224_create_plugins_table',1),(130,'2022_09_17_150606_add_pos_state_until_to_torrents_table',1),(131,'2022_09_19_043749_add_display_to_torrents_custom_fields_table',1),(132,'2022_10_13_002653_add_ip_to_complains_table',1),(133,'2022_10_30_024325_add_mode_to_tags_table',1),(134,'2022_11_23_042152_add_seed_points_seed_times_update_time_to_users_table',1),(135,'2022_12_10_034926_add_expired_at_to_invites_table',1),(136,'2022_12_10_041706_change_invites_table_invitee_default_empty',1),(137,'2023_01_10_072601_add_display_on_medal_page_to_medals_table',1),(138,'2023_01_11_044915_add_description_to_tags_table',1),(139,'2023_01_24_132053_add_sale_begin_end_time_and_inventory_to_medals_table',1),(140,'2023_01_26_210814_add_bonus_addition_factor_to_medals_table',1),(141,'2023_01_27_143831_add_gift_fee_factor_to_medals_table',1),(142,'2023_01_28_170836_add_priority_to_user_medals_table',1),(143,'2023_01_30_154106_create_login_logs_table',1),(144,'2023_01_31_172522_add_is_allowed_to_seed_box_records_table',1),(145,'2023_02_11_024403_add_price_to_torrents_table',1),(146,'2023_02_11_042230_create_torrent_buy_logs_table',1),(147,'2023_03_04_031319_add_index_to_last_action_field_of_peers_table',1),(148,'2023_03_29_021950_handle_snatched_user_torrent_unique',1),(149,'2023_04_01_005409_add_unique_torrent_peer_user_to_peers_table',1),(150,'2023_04_12_011330_change_agent_allow_deny_table_comment_field_nullable',1),(151,'2023_04_30_054425_alter_table_messages_msg_column_type_from_text_to_mediumtext',1),(152,'2023_04_30_054546_alter_table_torrents_descr_ori_descr_columns_type_from_text_to_mediumtext',1),(153,'2023_06_01_013150_change_bonus_log_table_value_decimal',1),(154,'2023_07_12_014056_add_seed_points_per_hour_to_users_table',1),(155,'2023_07_25_010623_add_pieces_hash_to_torrents_table',1),(156,'2023_08_23_020717_add_pre_register_email_and_username_to_invites_table',1),(157,'2024_02_24_004527_alter_table_torrents_change_field_cache_stamp_to_int',1),(158,'2024_03_17_021209_add_skips_authorization_field_to_oauth_clients_table',1),(159,'2024_03_23_044831_alter_table_users_change_secret_and_edit_secret_to_varchar',1),(160,'2024_04_13_042013_add_recurring_field_to_exams_table',1),(161,'2024_05_15_033436_add_field_type_to_exams_table',1),(162,'2024_08_08_021256_add_max_user_count_to_exams_table',1),(163,'2024_08_23_040843_add_background_color_to_exams_table',1),(164,'2024_08_26_000000_add_remark_to_torrents_state_table',1),(165,'2024_10_13_035900_change_torrents_table_info_hash_nullable',1),(166,'2024_11_18_160647_add_asn_to_seed_box_records_table',1),(167,'2024_12_24_170913_add_expires_at_to_personal_access_tokens_table',1),(168,'2024_12_29_202156_add_driver_to_attachments_table',1),(169,'2025_01_08_133552_create_torrent_extra_table',1),(170,'2025_01_08_133847_create_user_modify_logs_table',1),(171,'2025_01_18_235747_drop_users_table_text_column',1),(172,'2025_01_18_235757_drop_torrents_table_text_column',1),(173,'2025_01_20_012625_add_index_to_torrents_table_last_action_field',1),(174,'2025_03_29_121708_change_users_table_passhash_field_length',1),(175,'2025_04_03_123951_add_auth_key_to_table_users',1),(176,'2025_04_10_145925_add_uid_to_sitelog_table',1),(177,'2025_04_17_091208_create_user_passkeys_table',1),(178,'2025_04_21_113522_add_priority_field_to_medals_table',1),(179,'2025_04_23_042250_create_oauth_providers_table',1),(180,'2025_04_30_215644_create_social_accounts_table',1),(181,'2025_05_02_153802_add_provider_id_to_users_table',1),(182,'2025_05_09_215710_add_index_to_seed_box_records_table',1),(183,'2025_05_13_163426_add_index_to_access_field_of_iplog_table',1),(184,'2025_06_03_140409_add_seeding_torrent_count_field_to_users_table',1),(185,'2025_06_04_153154_update_invalid_datetime_value',1),(186,'2025_06_09_222012_add_hr_and_buy_id_to_snatched_table',1),(187,'2025_06_18_210723_create_message_templates_table',1),(188,'2025_06_19_194137_create_tracker_urls_table',1),(189,'2025_06_19_194434_add_tracker_url_id_to_users_table',1),(190,'2025_07_13_232240_add_bonus_addition_duration_to_medals',1),(191,'2025_07_22_001027_create_require_seed_torrents_table',1),(192,'2025_07_22_034710_create_user_require_seed_torrents_table',1),(193,'2025_08_15_132400_add_leech_time_no_seeder_to_snatched_table',1),(194,'2025_08_15_132620_add_leech_time_no_seeder_begin_to_hit_and_runs_table',1),(195,'2025_09_07_034041_add_index_to_added_field_on_torrents_table',1),(196,'2025_10_05_030400_create_activity_log_table',1),(197,'2025_10_05_030401_add_event_column_to_activity_log_table',1),(198,'2025_10_05_030402_add_batch_uuid_column_to_activity_log_table',1),(199,'2025_10_12_052151_add_uri_and_count_field_to_iplog_table',1),(200,'2025_11_19_033120_add_seeding_bonus_per_hour_to_users_table',1),(201,'2025_12_11_000000_add_notice_days_to_torrents_state_table',1),(202,'2026_01_14_122903_add_more_index_to_hit_and_runs_table',1),(203,'2026_04_23_014825_change_user_passkeys_table_aaguid_field_lower_case',1),(204,'2026_07_26_000000_drop_allowedemails_and_bannedemails_tables',1),(205,'2026_07_29_000000_remove_helpbox',1),(206,'2026_07_31_125228_fix_biglybt_agent_regex',1),(207,'2026_07_31_184554_add_edited_to_shoutbox_table',1),(208,'2026_07_31_184555_create_shoutbox_reactions_table',1),(209,'2026_08_01_100000_fix_shoutbox_reactions_emoji_collation',1),(210,'2026_08_13_001721_change_categories_mode_to_unsigned_int',1);
/*!40000 ALTER TABLE `migrations` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `modpanel`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `modpanel` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `url` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `info` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `modpanel` WRITE;
/*!40000 ALTER TABLE `modpanel` DISABLE KEYS */;
/*!40000 ALTER TABLE `modpanel` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `news`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `news` (
  `id` mediumint unsigned NOT NULL AUTO_INCREMENT,
  `userid` mediumint unsigned NOT NULL DEFAULT '0',
  `added` datetime DEFAULT NULL,
  `body` text COLLATE utf8mb4_unicode_ci,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `notify` enum('yes','no') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'no',
  PRIMARY KEY (`id`),
  KEY `news_added_index` (`added`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `news` WRITE;
/*!40000 ALTER TABLE `news` DISABLE KEYS */;
/*!40000 ALTER TABLE `news` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `oauth_access_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `oauth_access_tokens` (
  `id` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `client_id` bigint unsigned NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `scopes` text COLLATE utf8mb4_unicode_ci,
  `revoked` smallint NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `oauth_access_tokens_user_id_index` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `oauth_access_tokens` WRITE;
/*!40000 ALTER TABLE `oauth_access_tokens` DISABLE KEYS */;
/*!40000 ALTER TABLE `oauth_access_tokens` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `oauth_auth_codes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `oauth_auth_codes` (
  `id` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `client_id` bigint unsigned NOT NULL,
  `scopes` text COLLATE utf8mb4_unicode_ci,
  `revoked` smallint NOT NULL,
  `expires_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `oauth_auth_codes_user_id_index` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `oauth_auth_codes` WRITE;
/*!40000 ALTER TABLE `oauth_auth_codes` DISABLE KEYS */;
/*!40000 ALTER TABLE `oauth_auth_codes` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `oauth_clients`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `oauth_clients` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned DEFAULT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `secret` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `provider` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `redirect` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `personal_access_client` smallint NOT NULL,
  `password_client` smallint NOT NULL,
  `revoked` smallint NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `skips_authorization` smallint NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `oauth_clients_user_id_index` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `oauth_clients` WRITE;
/*!40000 ALTER TABLE `oauth_clients` DISABLE KEYS */;
/*!40000 ALTER TABLE `oauth_clients` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `oauth_personal_access_clients`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `oauth_personal_access_clients` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `client_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `oauth_personal_access_clients` WRITE;
/*!40000 ALTER TABLE `oauth_personal_access_clients` DISABLE KEYS */;
/*!40000 ALTER TABLE `oauth_personal_access_clients` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `oauth_providers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `oauth_providers` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `client_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `client_secret` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `authorization_endpoint_url` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token_endpoint_url` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_info_endpoint_url` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `id_claim` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `username_claim` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email_claim` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `level_claim` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `level_limit` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `enabled` smallint NOT NULL,
  `priority` int NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `oauth_providers_uuid_unique` (`uuid`),
  UNIQUE KEY `oauth_providers_name_unique` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `oauth_providers` WRITE;
/*!40000 ALTER TABLE `oauth_providers` DISABLE KEYS */;
/*!40000 ALTER TABLE `oauth_providers` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `oauth_refresh_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `oauth_refresh_tokens` (
  `id` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `access_token_id` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `revoked` smallint NOT NULL,
  `expires_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `oauth_refresh_tokens_access_token_id_index` (`access_token_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `oauth_refresh_tokens` WRITE;
/*!40000 ALTER TABLE `oauth_refresh_tokens` DISABLE KEYS */;
/*!40000 ALTER TABLE `oauth_refresh_tokens` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `offers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `offers` (
  `id` mediumint unsigned NOT NULL AUTO_INCREMENT,
  `userid` mediumint unsigned NOT NULL DEFAULT '0',
  `name` varchar(225) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `descr` text COLLATE utf8mb4_unicode_ci,
  `added` datetime DEFAULT NULL,
  `allowedtime` datetime DEFAULT NULL,
  `yeah` smallint unsigned NOT NULL DEFAULT '0',
  `against` smallint unsigned NOT NULL DEFAULT '0',
  `category` smallint unsigned NOT NULL DEFAULT '0',
  `comments` mediumint unsigned NOT NULL DEFAULT '0',
  `allowed` enum('allowed','pending','denied') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  PRIMARY KEY (`id`),
  KEY `offers_userid_index` (`userid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `offers` WRITE;
/*!40000 ALTER TABLE `offers` DISABLE KEYS */;
/*!40000 ALTER TABLE `offers` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `offervotes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `offervotes` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `offerid` mediumint unsigned NOT NULL DEFAULT '0',
  `userid` mediumint unsigned NOT NULL DEFAULT '0',
  `vote` enum('yeah','against') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'yeah',
  PRIMARY KEY (`id`),
  KEY `offervotes_userid_index` (`userid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `offervotes` WRITE;
/*!40000 ALTER TABLE `offervotes` DISABLE KEYS */;
/*!40000 ALTER TABLE `offervotes` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `overforums`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `overforums` (
  `id` smallint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `description` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `minclassview` tinyint unsigned NOT NULL DEFAULT '0',
  `sort` tinyint unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `overforums` WRITE;
/*!40000 ALTER TABLE `overforums` DISABLE KEYS */;
/*!40000 ALTER TABLE `overforums` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `peers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `peers` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `torrent` mediumint unsigned NOT NULL DEFAULT '0',
  `peer_id` varbinary(20) NOT NULL,
  `ip` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `port` smallint unsigned NOT NULL DEFAULT '0',
  `uploaded` bigint unsigned NOT NULL DEFAULT '0',
  `downloaded` bigint unsigned NOT NULL DEFAULT '0',
  `to_go` bigint unsigned NOT NULL DEFAULT '0',
  `seeder` enum('yes','no') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'no',
  `started` datetime DEFAULT NULL,
  `last_action` datetime DEFAULT NULL,
  `prev_action` datetime DEFAULT NULL,
  `connectable` enum('yes','no') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'yes',
  `userid` mediumint unsigned NOT NULL DEFAULT '0',
  `agent` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `finishedat` int unsigned NOT NULL DEFAULT '0',
  `downloadoffset` bigint unsigned NOT NULL DEFAULT '0',
  `uploadoffset` bigint unsigned NOT NULL DEFAULT '0',
  `passkey` char(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `ipv4` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `ipv6` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `is_seed_box` tinyint NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `peers_torrent_peer_id_userid_unique` (`torrent`,`peer_id`,`userid`),
  KEY `peers_last_action_index` (`last_action`),
  KEY `peers_peer_id_index` (`peer_id`),
  KEY `peers_userid_index` (`userid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `peers` WRITE;
/*!40000 ALTER TABLE `peers` DISABLE KEYS */;
/*!40000 ALTER TABLE `peers` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `personal_access_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `personal_access_tokens` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tokenable_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tokenable_id` bigint unsigned NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `abilities` text COLLATE utf8mb4_unicode_ci,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `personal_access_tokens_token_unique` (`token`),
  KEY `personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`,`tokenable_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `personal_access_tokens` WRITE;
/*!40000 ALTER TABLE `personal_access_tokens` DISABLE KEYS */;
/*!40000 ALTER TABLE `personal_access_tokens` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `plugins`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `plugins` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `display_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `package_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `remote_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `installed_version` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `status` int NOT NULL DEFAULT '-1',
  `status_result` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `plugins_package_name_unique` (`package_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `plugins` WRITE;
/*!40000 ALTER TABLE `plugins` DISABLE KEYS */;
/*!40000 ALTER TABLE `plugins` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `pmboxes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pmboxes` (
  `id` mediumint unsigned NOT NULL AUTO_INCREMENT,
  `userid` mediumint unsigned NOT NULL DEFAULT '0',
  `boxnumber` tinyint unsigned NOT NULL DEFAULT '2',
  `name` varchar(15) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `pmboxes` WRITE;
/*!40000 ALTER TABLE `pmboxes` DISABLE KEYS */;
/*!40000 ALTER TABLE `pmboxes` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `pollanswers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pollanswers` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `pollid` mediumint unsigned NOT NULL DEFAULT '0',
  `userid` mediumint unsigned NOT NULL DEFAULT '0',
  `selection` tinyint unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `pollanswers_pollid_index` (`pollid`),
  KEY `pollanswers_userid_index` (`userid`),
  KEY `pollanswers_selection_index` (`selection`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `pollanswers` WRITE;
/*!40000 ALTER TABLE `pollanswers` DISABLE KEYS */;
/*!40000 ALTER TABLE `pollanswers` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `polls`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `polls` (
  `id` mediumint unsigned NOT NULL AUTO_INCREMENT,
  `added` datetime DEFAULT NULL,
  `question` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `option0` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `option1` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `option2` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `option3` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `option4` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `option5` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `option6` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `option7` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `option8` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `option9` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `option10` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `option11` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `option12` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `option13` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `option14` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `option15` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `option16` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `option17` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `option18` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `option19` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `polls` WRITE;
/*!40000 ALTER TABLE `polls` DISABLE KEYS */;
/*!40000 ALTER TABLE `polls` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `posts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `posts` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `topicid` mediumint unsigned NOT NULL DEFAULT '0',
  `userid` mediumint unsigned NOT NULL DEFAULT '0',
  `added` datetime DEFAULT NULL,
  `body` text COLLATE utf8mb4_unicode_ci,
  `ori_body` text COLLATE utf8mb4_unicode_ci,
  `editedby` mediumint unsigned NOT NULL DEFAULT '0',
  `editdate` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `posts_topicid_id_index` (`topicid`,`id`),
  KEY `posts_userid_index` (`userid`),
  KEY `posts_added_index` (`added`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `posts` WRITE;
/*!40000 ALTER TABLE `posts` DISABLE KEYS */;
/*!40000 ALTER TABLE `posts` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `processings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `processings` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `sort_index` tinyint unsigned NOT NULL DEFAULT '0',
  `mode` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `processings` WRITE;
/*!40000 ALTER TABLE `processings` DISABLE KEYS */;
/*!40000 ALTER TABLE `processings` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `readposts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `readposts` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `userid` mediumint unsigned NOT NULL DEFAULT '0',
  `topicid` mediumint unsigned NOT NULL DEFAULT '0',
  `lastpostread` int unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `readposts_userid_index` (`userid`),
  KEY `readposts_topicid_index` (`topicid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `readposts` WRITE;
/*!40000 ALTER TABLE `readposts` DISABLE KEYS */;
/*!40000 ALTER TABLE `readposts` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `regimages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `regimages` (
  `id` mediumint unsigned NOT NULL AUTO_INCREMENT,
  `imagehash` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `imagestring` varchar(8) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `dateline` int unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `regimages` WRITE;
/*!40000 ALTER TABLE `regimages` DISABLE KEYS */;
/*!40000 ALTER TABLE `regimages` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `reports`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `reports` (
  `id` mediumint unsigned NOT NULL AUTO_INCREMENT,
  `addedby` mediumint unsigned NOT NULL DEFAULT '0',
  `added` datetime DEFAULT NULL,
  `reportid` int unsigned NOT NULL DEFAULT '0',
  `type` enum('torrent','user','offer','request','post','comment','subtitle') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'torrent',
  `reason` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `dealtby` mediumint unsigned NOT NULL DEFAULT '0',
  `dealtwith` smallint NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `reports` WRITE;
/*!40000 ALTER TABLE `reports` DISABLE KEYS */;
/*!40000 ALTER TABLE `reports` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `require_seed_torrents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `require_seed_torrents` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `torrent_id` int NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `require_seed_torrents_torrent_id_unique` (`torrent_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `require_seed_torrents` WRITE;
/*!40000 ALTER TABLE `require_seed_torrents` DISABLE KEYS */;
/*!40000 ALTER TABLE `require_seed_torrents` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `resreq`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `resreq` (
  `id` int NOT NULL AUTO_INCREMENT,
  `reqid` int NOT NULL DEFAULT '0',
  `torrentid` int NOT NULL DEFAULT '0',
  `chosen` enum('yes','no') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'no',
  PRIMARY KEY (`id`),
  KEY `resreq_reqid_index` (`reqid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `resreq` WRITE;
/*!40000 ALTER TABLE `resreq` DISABLE KEYS */;
/*!40000 ALTER TABLE `resreq` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `rules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `rules` (
  `id` smallint unsigned NOT NULL AUTO_INCREMENT,
  `lang_id` smallint unsigned NOT NULL DEFAULT '6',
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `text` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `rules` WRITE;
/*!40000 ALTER TABLE `rules` DISABLE KEYS */;
/*!40000 ALTER TABLE `rules` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `searchbox`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `searchbox` (
  `id` smallint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `section_name` json DEFAULT NULL,
  `showsubcat` smallint NOT NULL DEFAULT '0',
  `showsource` smallint NOT NULL DEFAULT '0',
  `showmedium` smallint NOT NULL DEFAULT '0',
  `showcodec` smallint NOT NULL DEFAULT '0',
  `showstandard` smallint NOT NULL DEFAULT '0',
  `showprocessing` smallint NOT NULL DEFAULT '0',
  `showaudiocodec` smallint NOT NULL DEFAULT '0',
  `catsperrow` smallint unsigned NOT NULL DEFAULT '7',
  `catpadding` smallint unsigned NOT NULL DEFAULT '25',
  `custom_fields` text COLLATE utf8mb4_unicode_ci,
  `custom_fields_display_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT '',
  `custom_fields_display` text COLLATE utf8mb4_unicode_ci,
  `extra` json DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `searchbox` WRITE;
/*!40000 ALTER TABLE `searchbox` DISABLE KEYS */;
/*!40000 ALTER TABLE `searchbox` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `searchbox_fields`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `searchbox_fields` (
  `id` int NOT NULL AUTO_INCREMENT,
  `searchbox_id` int NOT NULL,
  `field_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `field_id` int NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `update_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `searchbox_fields_searchbox_id_field_type_field_id_unique` (`searchbox_id`,`field_type`,`field_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `searchbox_fields` WRITE;
/*!40000 ALTER TABLE `searchbox_fields` DISABLE KEYS */;
/*!40000 ALTER TABLE `searchbox_fields` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `secondicons`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `secondicons` (
  `id` smallint unsigned NOT NULL AUTO_INCREMENT,
  `source` tinyint unsigned NOT NULL DEFAULT '0',
  `medium` tinyint unsigned NOT NULL DEFAULT '0',
  `codec` tinyint unsigned NOT NULL DEFAULT '0',
  `standard` tinyint unsigned NOT NULL DEFAULT '0',
  `processing` tinyint unsigned NOT NULL DEFAULT '0',
  `audiocodec` tinyint unsigned NOT NULL DEFAULT '0',
  `name` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `class_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `image` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `mode` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `secondicons` WRITE;
/*!40000 ALTER TABLE `secondicons` DISABLE KEYS */;
/*!40000 ALTER TABLE `secondicons` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `seed_box_records`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `seed_box_records` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `type` int NOT NULL,
  `uid` int NOT NULL,
  `status` int NOT NULL DEFAULT '0',
  `operator` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bandwidth` int DEFAULT NULL,
  `ip` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ip_begin` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ip_end` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ip_begin_numeric` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ip_end_numeric` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL,
  `version` int NOT NULL,
  `comment` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `is_allowed` int NOT NULL DEFAULT '0',
  `asn` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `seed_box_records_ip_begin_numeric_index` (`ip_begin_numeric`),
  KEY `seed_box_records_ip_end_numeric_index` (`ip_end_numeric`),
  KEY `seed_box_records_asn_index` (`asn`),
  KEY `seed_box_records_uid_index` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `seed_box_records` WRITE;
/*!40000 ALTER TABLE `seed_box_records` DISABLE KEYS */;
/*!40000 ALTER TABLE `seed_box_records` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `settings` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `value` mediumtext COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `autoload` enum('yes','no') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'yes',
  PRIMARY KEY (`id`),
  UNIQUE KEY `settings_name_unique` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `settings` WRITE;
/*!40000 ALTER TABLE `settings` DISABLE KEYS */;
/*!40000 ALTER TABLE `settings` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `shoutbox`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `shoutbox` (
  `id` int NOT NULL AUTO_INCREMENT,
  `userid` mediumint unsigned NOT NULL DEFAULT '0',
  `date` int unsigned NOT NULL DEFAULT '0',
  `edited_by` int unsigned NOT NULL DEFAULT '0',
  `edited_at` int unsigned NOT NULL DEFAULT '0',
  `text` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` enum('sb') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'sb',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `shoutbox` WRITE;
/*!40000 ALTER TABLE `shoutbox` DISABLE KEYS */;
/*!40000 ALTER TABLE `shoutbox` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `shoutbox_reactions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `shoutbox_reactions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `shoutbox_id` int unsigned NOT NULL,
  `user_id` int unsigned NOT NULL,
  `reaction` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `shoutbox_reactions_unique` (`shoutbox_id`,`user_id`,`reaction`),
  KEY `shoutbox_reactions_shoutbox_id_index` (`shoutbox_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `shoutbox_reactions` WRITE;
/*!40000 ALTER TABLE `shoutbox_reactions` DISABLE KEYS */;
/*!40000 ALTER TABLE `shoutbox_reactions` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `sitelog`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sitelog` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `added` datetime DEFAULT NULL,
  `txt` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `security_level` enum('normal','mod') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'normal',
  `uid` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `sitelog_added_index` (`added`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `sitelog` WRITE;
/*!40000 ALTER TABLE `sitelog` DISABLE KEYS */;
/*!40000 ALTER TABLE `sitelog` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `snatched`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `snatched` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `torrentid` mediumint unsigned NOT NULL DEFAULT '0',
  `userid` mediumint unsigned NOT NULL DEFAULT '0',
  `ip` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `port` smallint unsigned NOT NULL DEFAULT '0',
  `uploaded` bigint unsigned NOT NULL DEFAULT '0',
  `downloaded` bigint unsigned NOT NULL DEFAULT '0',
  `to_go` bigint unsigned NOT NULL DEFAULT '0',
  `seedtime` int unsigned NOT NULL DEFAULT '0',
  `leechtime` int unsigned NOT NULL DEFAULT '0',
  `last_action` datetime DEFAULT NULL,
  `startdat` datetime DEFAULT NULL,
  `completedat` datetime DEFAULT NULL,
  `finished` enum('yes','no') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'no',
  `hit_and_run_id` bigint NOT NULL DEFAULT '0',
  `buy_log_id` bigint NOT NULL DEFAULT '0',
  `leech_time_no_seeder` bigint NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `snatched_torrentid_userid_unique` (`torrentid`,`userid`),
  KEY `snatched_completedat_index` (`completedat`),
  KEY `snatched_userid_index` (`userid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `snatched` WRITE;
/*!40000 ALTER TABLE `snatched` DISABLE KEYS */;
/*!40000 ALTER TABLE `snatched` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `social_accounts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `social_accounts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint NOT NULL,
  `provider_id` bigint NOT NULL,
  `provider_user_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `provider_email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `provider_username` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `social_accounts_user_id_provider_id_unique` (`user_id`,`provider_id`),
  UNIQUE KEY `social_accounts_provider_id_provider_user_id_unique` (`provider_id`,`provider_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `social_accounts` WRITE;
/*!40000 ALTER TABLE `social_accounts` DISABLE KEYS */;
/*!40000 ALTER TABLE `social_accounts` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `sources`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sources` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `sort_index` tinyint unsigned NOT NULL DEFAULT '0',
  `mode` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `sources` WRITE;
/*!40000 ALTER TABLE `sources` DISABLE KEYS */;
/*!40000 ALTER TABLE `sources` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `staffmessages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `staffmessages` (
  `id` mediumint unsigned NOT NULL AUTO_INCREMENT,
  `sender` mediumint unsigned NOT NULL DEFAULT '0',
  `added` datetime DEFAULT NULL,
  `msg` text COLLATE utf8mb4_unicode_ci,
  `subject` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `answeredby` mediumint unsigned NOT NULL DEFAULT '0',
  `answered` smallint NOT NULL DEFAULT '0',
  `answer` text COLLATE utf8mb4_unicode_ci,
  `permission` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `staffmessages_permission_index` (`permission`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `staffmessages` WRITE;
/*!40000 ALTER TABLE `staffmessages` DISABLE KEYS */;
/*!40000 ALTER TABLE `staffmessages` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `standards`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `standards` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `sort_index` tinyint unsigned NOT NULL DEFAULT '0',
  `mode` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `standards` WRITE;
/*!40000 ALTER TABLE `standards` DISABLE KEYS */;
/*!40000 ALTER TABLE `standards` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `stylesheets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `stylesheets` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `uri` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `name` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `addicode` text COLLATE utf8mb4_unicode_ci,
  `designer` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `comment` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `stylesheets` WRITE;
/*!40000 ALTER TABLE `stylesheets` DISABLE KEYS */;
/*!40000 ALTER TABLE `stylesheets` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `suggest`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `suggest` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `keywords` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `userid` mediumint unsigned NOT NULL DEFAULT '0',
  `adddate` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `suggest_keywords_index` (`keywords`),
  KEY `suggest_adddate_index` (`adddate`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `suggest` WRITE;
/*!40000 ALTER TABLE `suggest` DISABLE KEYS */;
/*!40000 ALTER TABLE `suggest` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `sysoppanel`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sysoppanel` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `url` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `info` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `sysoppanel` WRITE;
/*!40000 ALTER TABLE `sysoppanel` DISABLE KEYS */;
/*!40000 ALTER TABLE `sysoppanel` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `tags`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tags` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `color` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `priority` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `padding` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '1px 2px',
  `margin` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '0 4px 0 0',
  `border_radius` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '0',
  `font_size` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '12px',
  `font_color` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '#ffffff',
  `description` text COLLATE utf8mb4_unicode_ci,
  `mode` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `tags_name_unique` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `tags` WRITE;
/*!40000 ALTER TABLE `tags` DISABLE KEYS */;
/*!40000 ALTER TABLE `tags` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `thanks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `thanks` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `torrentid` mediumint unsigned NOT NULL DEFAULT '0',
  `userid` mediumint unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `thanks_torrentid_userid_unique` (`torrentid`,`userid`),
  KEY `thanks_userid_index` (`userid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `thanks` WRITE;
/*!40000 ALTER TABLE `thanks` DISABLE KEYS */;
/*!40000 ALTER TABLE `thanks` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `topics`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `topics` (
  `id` mediumint unsigned NOT NULL AUTO_INCREMENT,
  `userid` mediumint unsigned NOT NULL DEFAULT '0',
  `subject` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `locked` enum('yes','no') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'no',
  `forumid` smallint unsigned NOT NULL DEFAULT '0',
  `firstpost` int unsigned NOT NULL DEFAULT '0',
  `lastpost` int unsigned NOT NULL DEFAULT '0',
  `sticky` enum('no','yes') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'no',
  `hlcolor` tinyint unsigned NOT NULL DEFAULT '0',
  `views` int unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `topics_forumid_lastpost_index` (`forumid`,`lastpost`),
  KEY `topics_forumid_sticky_lastpost_index` (`forumid`,`sticky`,`lastpost`),
  KEY `topics_userid_index` (`userid`),
  KEY `topics_subject_index` (`subject`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `topics` WRITE;
/*!40000 ALTER TABLE `topics` DISABLE KEYS */;
/*!40000 ALTER TABLE `topics` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `torrent_buy_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `torrent_buy_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uid` int NOT NULL,
  `torrent_id` int NOT NULL,
  `price` int NOT NULL,
  `channel` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `torrent_buy_logs_uid_index` (`uid`),
  KEY `torrent_buy_logs_torrent_id_index` (`torrent_id`),
  KEY `torrent_buy_logs_price_index` (`price`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `torrent_buy_logs` WRITE;
/*!40000 ALTER TABLE `torrent_buy_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `torrent_buy_logs` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `torrent_deny_reasons`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `torrent_deny_reasons` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `hits` int NOT NULL DEFAULT '0',
  `priority` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `torrent_deny_reasons` WRITE;
/*!40000 ALTER TABLE `torrent_deny_reasons` DISABLE KEYS */;
/*!40000 ALTER TABLE `torrent_deny_reasons` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `torrent_extras`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `torrent_extras` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `torrent_id` int NOT NULL,
  `descr` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `media_info` text COLLATE utf8mb4_unicode_ci,
  `nfo` blob,
  `pt_gen` mediumtext COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `torrent_extras_torrent_id_unique` (`torrent_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `torrent_extras` WRITE;
/*!40000 ALTER TABLE `torrent_extras` DISABLE KEYS */;
/*!40000 ALTER TABLE `torrent_extras` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `torrent_operation_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `torrent_operation_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `torrent_id` int NOT NULL,
  `uid` int NOT NULL,
  `action_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `comment` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `torrent_operation_logs_torrent_id_index` (`torrent_id`),
  KEY `torrent_operation_logs_uid_index` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `torrent_operation_logs` WRITE;
/*!40000 ALTER TABLE `torrent_operation_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `torrent_operation_logs` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `torrent_secrets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `torrent_secrets` (
  `id` int NOT NULL AUTO_INCREMENT,
  `uid` int NOT NULL,
  `torrent_id` int NOT NULL DEFAULT '0',
  `secret` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `torrent_secrets_uid_index` (`uid`),
  KEY `torrent_secrets_torrent_id_index` (`torrent_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `torrent_secrets` WRITE;
/*!40000 ALTER TABLE `torrent_secrets` DISABLE KEYS */;
/*!40000 ALTER TABLE `torrent_secrets` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `torrent_tags`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `torrent_tags` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `torrent_id` int NOT NULL,
  `tag_id` int NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `torrent_tags_torrent_id_tag_id_unique` (`torrent_id`,`tag_id`),
  KEY `torrent_tags_tag_id_index` (`tag_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `torrent_tags` WRITE;
/*!40000 ALTER TABLE `torrent_tags` DISABLE KEYS */;
/*!40000 ALTER TABLE `torrent_tags` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `torrents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `torrents` (
  `id` mediumint unsigned NOT NULL AUTO_INCREMENT,
  `info_hash` varbinary(20) DEFAULT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `filename` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `save_as` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `cover` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `small_descr` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `category` smallint unsigned NOT NULL DEFAULT '0',
  `source` tinyint unsigned NOT NULL DEFAULT '0',
  `medium` tinyint unsigned NOT NULL DEFAULT '0',
  `codec` tinyint unsigned NOT NULL DEFAULT '0',
  `standard` tinyint unsigned NOT NULL DEFAULT '0',
  `processing` tinyint unsigned NOT NULL DEFAULT '0',
  `audiocodec` tinyint unsigned NOT NULL DEFAULT '0',
  `size` bigint unsigned NOT NULL DEFAULT '0',
  `added` datetime DEFAULT NULL,
  `type` enum('single','multi') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'single',
  `numfiles` smallint unsigned NOT NULL DEFAULT '0',
  `comments` mediumint unsigned NOT NULL DEFAULT '0',
  `views` int unsigned NOT NULL DEFAULT '0',
  `hits` int unsigned NOT NULL DEFAULT '0',
  `times_completed` mediumint unsigned NOT NULL DEFAULT '0',
  `leechers` mediumint unsigned NOT NULL DEFAULT '0',
  `seeders` mediumint unsigned NOT NULL DEFAULT '0',
  `last_action` datetime DEFAULT NULL,
  `visible` enum('yes','no') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'yes',
  `banned` enum('yes','no') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'no',
  `owner` mediumint unsigned NOT NULL DEFAULT '0',
  `sp_state` tinyint unsigned NOT NULL DEFAULT '1',
  `promotion_time_type` tinyint unsigned NOT NULL DEFAULT '0',
  `promotion_until` datetime DEFAULT NULL,
  `anonymous` enum('yes','no') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'no',
  `url` int unsigned DEFAULT NULL,
  `pos_state` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'normal',
  `pos_state_until` datetime DEFAULT NULL,
  `cache_stamp` int NOT NULL DEFAULT '0',
  `last_reseed` datetime DEFAULT NULL,
  `hr` tinyint NOT NULL DEFAULT '0',
  `approval_status` int NOT NULL DEFAULT '0',
  `price` int NOT NULL DEFAULT '0',
  `pieces_hash` char(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  UNIQUE KEY `torrents_info_hash_unique` (`info_hash`),
  KEY `torrents_visible_pos_state_id_index` (`visible`,`pos_state`,`id`),
  KEY `torrents_category_visible_banned_index` (`category`,`visible`,`banned`),
  KEY `torrents_visible_banned_pos_state_id_index` (`visible`,`banned`,`pos_state`,`id`),
  KEY `torrents_name_index` (`name`),
  KEY `torrents_owner_index` (`owner`),
  KEY `torrents_url_index` (`url`),
  KEY `torrents_pieces_hash_index` (`pieces_hash`),
  KEY `torrents_last_action_index` (`last_action`),
  KEY `torrents_added_index` (`added`),
  KEY `torrents_promotion_until_promotion_time_type_index` (`promotion_until`,`promotion_time_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `torrents` WRITE;
/*!40000 ALTER TABLE `torrents` DISABLE KEYS */;
/*!40000 ALTER TABLE `torrents` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `torrents_custom_field_values`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `torrents_custom_field_values` (
  `id` int NOT NULL AUTO_INCREMENT,
  `torrent_id` int NOT NULL DEFAULT '0',
  `custom_field_id` int NOT NULL DEFAULT '0',
  `custom_field_value` mediumtext COLLATE utf8mb4_unicode_ci,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `torrents_custom_field_values_torrent_id_index` (`torrent_id`),
  KEY `torrents_custom_field_values_custom_field_id_index` (`custom_field_id`),
  KEY `custom_field_value` (`custom_field_value`(191))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `torrents_custom_field_values` WRITE;
/*!40000 ALTER TABLE `torrents_custom_field_values` DISABLE KEYS */;
/*!40000 ALTER TABLE `torrents_custom_field_values` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `torrents_custom_fields`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `torrents_custom_fields` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `label` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `type` enum('text','textarea','select','radio','checkbox','image') COLLATE utf8mb4_unicode_ci NOT NULL,
  `required` tinyint NOT NULL DEFAULT '0',
  `is_single_row` int NOT NULL DEFAULT '0',
  `options` text COLLATE utf8mb4_unicode_ci,
  `help` text COLLATE utf8mb4_unicode_ci,
  `display` text COLLATE utf8mb4_unicode_ci,
  `priority` int NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `torrents_custom_fields` WRITE;
/*!40000 ALTER TABLE `torrents_custom_fields` DISABLE KEYS */;
/*!40000 ALTER TABLE `torrents_custom_fields` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `torrents_state`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `torrents_state` (
  `global_sp_state` tinyint unsigned NOT NULL DEFAULT '1',
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `deadline` datetime DEFAULT NULL,
  `remark` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notice_days` int NOT NULL DEFAULT '0',
  `begin` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `torrents_state` WRITE;
/*!40000 ALTER TABLE `torrents_state` DISABLE KEYS */;
/*!40000 ALTER TABLE `torrents_state` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `tracker_urls`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tracker_urls` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `url` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `enabled` tinyint NOT NULL DEFAULT '1',
  `is_default` tinyint NOT NULL DEFAULT '0',
  `priority` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `tracker_urls` WRITE;
/*!40000 ALTER TABLE `tracker_urls` DISABLE KEYS */;
/*!40000 ALTER TABLE `tracker_urls` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `user_ban_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_ban_logs` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `uid` int NOT NULL DEFAULT '0',
  `username` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `operator` int NOT NULL DEFAULT '0',
  `reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_ban_logs_uid_index` (`uid`),
  KEY `user_ban_logs_username_index` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `user_ban_logs` WRITE;
/*!40000 ALTER TABLE `user_ban_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `user_ban_logs` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `user_medals`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_medals` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uid` int NOT NULL,
  `medal_id` int NOT NULL,
  `expire_at` datetime DEFAULT NULL,
  `bonus_addition_expire_at` datetime DEFAULT NULL COMMENT '魔力加成过期时间，null 永不过期',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `status` int NOT NULL DEFAULT '1',
  `priority` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `user_medals_uid_index` (`uid`),
  KEY `user_medals_medal_id_index` (`medal_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `user_medals` WRITE;
/*!40000 ALTER TABLE `user_medals` DISABLE KEYS */;
/*!40000 ALTER TABLE `user_medals` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `user_metas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_metas` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uid` int NOT NULL,
  `meta_key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` int NOT NULL DEFAULT '0',
  `deadline` datetime DEFAULT NULL,
  `meta_value` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_metas_uid_index` (`uid`),
  KEY `user_metas_meta_key_index` (`meta_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `user_metas` WRITE;
/*!40000 ALTER TABLE `user_metas` DISABLE KEYS */;
/*!40000 ALTER TABLE `user_metas` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `user_modify_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_modify_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `content` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_modify_logs_user_id_index` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `user_modify_logs` WRITE;
/*!40000 ALTER TABLE `user_modify_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `user_modify_logs` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `user_passkeys`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_passkeys` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` mediumint unsigned NOT NULL,
  `aaguid` text COLLATE utf8mb4_unicode_ci,
  `credential_id` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `public_key` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `counter` int unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `user_passkeys` WRITE;
/*!40000 ALTER TABLE `user_passkeys` DISABLE KEYS */;
/*!40000 ALTER TABLE `user_passkeys` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `user_require_seed_torrents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_require_seed_torrents` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `torrent_id` int NOT NULL,
  `seed_time_begin` bigint NOT NULL,
  `uploaded_begin` bigint NOT NULL,
  `last_settlement_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_require_seed_torrents_user_id_torrent_id_unique` (`user_id`,`torrent_id`),
  KEY `user_require_seed_torrents_torrent_id_index` (`torrent_id`),
  KEY `user_require_seed_torrents_last_settlement_at_index` (`last_settlement_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `user_require_seed_torrents` WRITE;
/*!40000 ALTER TABLE `user_require_seed_torrents` DISABLE KEYS */;
/*!40000 ALTER TABLE `user_require_seed_torrents` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `username_change_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `username_change_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uid` int NOT NULL,
  `operator` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `change_type` int NOT NULL DEFAULT '0',
  `username_old` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `username_new` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `username_change_logs` WRITE;
/*!40000 ALTER TABLE `username_change_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `username_change_logs` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `passhash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `secret` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `auth_key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `email` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `status` enum('pending','confirmed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `added` datetime DEFAULT NULL,
  `last_login` datetime DEFAULT NULL,
  `last_access` datetime DEFAULT NULL,
  `last_home` datetime DEFAULT NULL,
  `last_offer` datetime DEFAULT NULL,
  `forum_access` datetime DEFAULT NULL,
  `last_staffmsg` datetime DEFAULT NULL,
  `last_pm` datetime DEFAULT NULL,
  `last_comment` datetime DEFAULT NULL,
  `last_post` datetime DEFAULT NULL,
  `last_browse` int unsigned NOT NULL DEFAULT '0',
  `last_music` int unsigned NOT NULL DEFAULT '0',
  `last_catchup` int unsigned NOT NULL DEFAULT '0',
  `editsecret` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `privacy` enum('strong','normal','low') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'normal',
  `stylesheet` tinyint unsigned NOT NULL DEFAULT '1',
  `caticon` tinyint unsigned NOT NULL DEFAULT '1',
  `fontsize` enum('small','medium','large') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'medium',
  `info` text COLLATE utf8mb4_unicode_ci,
  `acceptpms` enum('yes','friends','no') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'yes',
  `commentpm` enum('yes','no') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'yes',
  `ip` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `class` tinyint unsigned NOT NULL DEFAULT '1',
  `max_class_once` tinyint NOT NULL DEFAULT '1',
  `avatar` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `uploaded` bigint unsigned NOT NULL DEFAULT '0',
  `downloaded` bigint unsigned NOT NULL DEFAULT '0',
  `seedtime` bigint unsigned NOT NULL DEFAULT '0',
  `leechtime` bigint unsigned NOT NULL DEFAULT '0',
  `title` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `country` smallint unsigned NOT NULL DEFAULT '107',
  `notifs` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `enabled` enum('yes','no') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'yes',
  `avatars` enum('yes','no') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'yes',
  `donor` enum('yes','no') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'no',
  `donated` decimal(8,2) NOT NULL DEFAULT '0.00',
  `donated_cny` decimal(8,2) NOT NULL DEFAULT '0.00',
  `donoruntil` datetime DEFAULT NULL,
  `warned` enum('yes','no') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'no',
  `warneduntil` datetime DEFAULT NULL,
  `noad` enum('yes','no') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'no',
  `noaduntil` datetime DEFAULT NULL,
  `torrentsperpage` tinyint unsigned NOT NULL DEFAULT '0',
  `topicsperpage` tinyint unsigned NOT NULL DEFAULT '0',
  `postsperpage` tinyint unsigned NOT NULL DEFAULT '0',
  `clicktopic` enum('firstpage','lastpage') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'firstpage',
  `deletepms` enum('yes','no') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'yes',
  `savepms` enum('yes','no') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'no',
  `support` enum('yes','no') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'no',
  `picker` enum('yes','no') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'no',
  `stafffor` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `supportfor` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `pickfor` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `supportlang` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `passkey` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `uploadpos` enum('yes','no') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'yes',
  `forumpost` enum('yes','no') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'yes',
  `downloadpos` enum('yes','no') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'yes',
  `clientselect` tinyint unsigned NOT NULL DEFAULT '0',
  `signatures` enum('yes','no') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'yes',
  `signature` varchar(800) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `lang` smallint unsigned NOT NULL DEFAULT '6',
  `cheat` smallint NOT NULL DEFAULT '0',
  `invites` smallint unsigned NOT NULL DEFAULT '0',
  `invited_by` mediumint unsigned NOT NULL DEFAULT '0',
  `gender` enum('Male','Female','N/A') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'N/A',
  `vip_added` enum('yes','no') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'no',
  `vip_until` datetime DEFAULT NULL,
  `seedbonus` decimal(20,1) NOT NULL DEFAULT '0.0',
  `charity` decimal(10,1) NOT NULL DEFAULT '0.0',
  `parked` enum('yes','no') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'no',
  `leechwarn` enum('yes','no') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'no',
  `leechwarnuntil` datetime DEFAULT NULL,
  `lastwarned` datetime DEFAULT NULL,
  `timeswarned` tinyint unsigned NOT NULL DEFAULT '0',
  `warnedby` mediumint unsigned NOT NULL DEFAULT '0',
  `sbnum` smallint unsigned NOT NULL DEFAULT '70',
  `sbrefresh` smallint unsigned NOT NULL DEFAULT '120',
  `showimdb` enum('yes','no') COLLATE utf8mb4_unicode_ci DEFAULT 'yes',
  `showdescription` enum('yes','no') COLLATE utf8mb4_unicode_ci DEFAULT 'yes',
  `showcomment` enum('yes','no') COLLATE utf8mb4_unicode_ci DEFAULT 'yes',
  `showclienterror` enum('yes','no') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'no',
  `showdlnotice` smallint NOT NULL DEFAULT '1',
  `tooltip` enum('minorimdb','medianimdb','off') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'off',
  `shownfo` enum('yes','no') COLLATE utf8mb4_unicode_ci DEFAULT 'yes',
  `timetype` enum('timeadded','timealive') COLLATE utf8mb4_unicode_ci DEFAULT 'timealive',
  `appendsticky` enum('yes','no') COLLATE utf8mb4_unicode_ci DEFAULT 'yes',
  `appendnew` enum('yes','no') COLLATE utf8mb4_unicode_ci DEFAULT 'yes',
  `appendpromotion` enum('highlight','word','icon','off') COLLATE utf8mb4_unicode_ci DEFAULT 'icon',
  `appendpicked` enum('yes','no') COLLATE utf8mb4_unicode_ci DEFAULT 'yes',
  `dlicon` enum('yes','no') COLLATE utf8mb4_unicode_ci DEFAULT 'yes',
  `bmicon` enum('yes','no') COLLATE utf8mb4_unicode_ci DEFAULT 'yes',
  `showsmalldescr` enum('yes','no') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'yes',
  `showcomnum` enum('yes','no') COLLATE utf8mb4_unicode_ci DEFAULT 'yes',
  `showlastcom` enum('yes','no') COLLATE utf8mb4_unicode_ci DEFAULT 'no',
  `showlastpost` enum('yes','no') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'no',
  `pmnum` tinyint unsigned NOT NULL DEFAULT '10',
  `page` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT '',
  `two_step_secret` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `seed_points` decimal(20,1) NOT NULL DEFAULT '0.0',
  `seed_points_per_hour` decimal(20,1) NOT NULL DEFAULT '0.0',
  `seed_bonus_per_hour` decimal(20,1) NOT NULL DEFAULT '0.0',
  `attendance_card` int NOT NULL DEFAULT '0',
  `offer_allowed_count` int NOT NULL DEFAULT '0',
  `seed_points_updated_at` datetime DEFAULT NULL,
  `seed_time_updated_at` datetime DEFAULT NULL,
  `provider_id` bigint NOT NULL DEFAULT '0',
  `seeding_torrent_count` int NOT NULL DEFAULT '0',
  `seeding_torrent_size` bigint NOT NULL DEFAULT '0',
  `last_announce_at` datetime DEFAULT NULL,
  `tracker_url_id` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_username_unique` (`username`),
  KEY `users_status_added_index` (`status`,`added`),
  KEY `users_last_access_index` (`last_access`),
  KEY `users_ip_index` (`ip`),
  KEY `users_class_index` (`class`),
  KEY `users_uploaded_index` (`uploaded`),
  KEY `users_downloaded_index` (`downloaded`),
  KEY `users_country_index` (`country`),
  KEY `users_enabled_index` (`enabled`),
  KEY `users_warned_index` (`warned`),
  KEY `users_passkey_index` (`passkey`),
  KEY `users_cheat_index` (`cheat`)
) ENGINE=InnoDB AUTO_INCREMENT=10001 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

