-- MySQL dump 10.13  Distrib 8.0.32, for Linux (x86_64)
--
-- Host: localhost    Database: wordpress
-- ------------------------------------------------------
-- Server version	8.0.32

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `wp_commentmeta`
--

DROP TABLE IF EXISTS `wp_commentmeta`;
CREATE TABLE `wp_commentmeta` (
  `meta_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `comment_id` bigint unsigned NOT NULL DEFAULT '0',
  `meta_key` varchar(255) COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  `meta_value` longtext COLLATE utf8mb4_unicode_520_ci,
  PRIMARY KEY (`meta_id`),
  KEY `comment_id` (`comment_id`),
  KEY `meta_key` (`meta_key`(191))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

--
-- Table structure for table `wp_comments`
--

DROP TABLE IF EXISTS `wp_comments`;
CREATE TABLE `wp_comments` (
  `comment_ID` bigint unsigned NOT NULL AUTO_INCREMENT,
  `comment_post_ID` bigint unsigned NOT NULL DEFAULT '0',
  `comment_author` tinytext COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `comment_author_email` varchar(100) COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT '',
  `comment_author_url` varchar(200) COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT '',
  `comment_author_IP` varchar(100) COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT '',
  `comment_date` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `comment_date_gmt` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `comment_content` text COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `comment_karma` int NOT NULL DEFAULT '0',
  `comment_approved` varchar(20) COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT '1',
  `comment_agent` varchar(255) COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT '',
  `comment_type` varchar(20) COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT 'comment',
  `comment_parent` bigint unsigned NOT NULL DEFAULT '0',
  `user_id` bigint unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`comment_ID`),
  KEY `comment_post_ID` (`comment_post_ID`),
  KEY `comment_approved_date_gmt` (`comment_approved`,`comment_date_gmt`),
  KEY `comment_date_gmt` (`comment_date_gmt`),
  KEY `comment_parent` (`comment_parent`),
  KEY `comment_author_email` (`comment_author_email`(10))
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

--
-- Dumping data for table `wp_comments`
--

INSERT INTO `wp_comments` VALUES 
(1,1,'Ein WordPress-Kommentator','wapuu@wordpress.example','https://wordpress.org/','192.168.1.100','2024-01-15 10:30:00','2024-01-15 09:30:00','Hallo, das ist ein Kommentar.\nUm mit dem Freischalten, Bearbeiten und Löschen von Kommentaren zu beginnen, besuche bitte die Kommentare-Ansicht im Dashboard.',0,'1','Mozilla/5.0','comment',0,0),
(2,3,'Maria Schmidt','maria@example.com','https://maria-blog.at','85.123.45.67','2024-02-20 14:22:15','2024-02-20 13:22:15','Toller Artikel! Hat mir sehr geholfen.',0,'1','Mozilla/5.0 (Windows NT 10.0; Win64; x64)','comment',0,0),
(3,3,'Thomas Huber','thomas.huber@gmail.com','','91.22.145.88','2024-02-21 09:15:33','2024-02-21 08:15:33','Ich stimme Maria zu, sehr informativ!',0,'1','Mozilla/5.0 (iPhone; CPU iPhone OS 16_0)','comment',2,0),
(4,5,'Anna Berger','anna.berger@company.at','https://company.at','77.88.99.11','2024-03-10 16:45:00','2024-03-10 15:45:00','Könnten Sie mehr über dieses Thema schreiben?',0,'1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)','comment',0,0),
(5,7,'Spam Bot','spam@spammer.ru','https://buy-viagra.ru','1.2.3.4','2024-03-15 03:22:11','2024-03-15 02:22:11','Buy cheap products now!!!',0,'spam','curl/7.64.1','comment',0,0),
(6,5,'Admin','admin@example.com','','127.0.0.1','2024-03-11 10:00:00','2024-03-11 09:00:00','Danke für das Feedback! Wir arbeiten an einem Folgeartikel.',0,'1','Mozilla/5.0','comment',4,1),
(7,9,'Peter Wagner','peter.w@outlook.com','','195.34.12.88','2024-04-05 11:30:22','2024-04-05 10:30:22','Endlich mal jemand der das verständlich erklärt!',0,'1','Mozilla/5.0 (Linux; Android 13)','comment',0,0),
(8,5,'Sabine Klein','sabine.klein@web.de','https://sabine-design.de','88.77.66.55','2024-04-08 15:20:00','2024-04-08 14:20:00','Super Artikel! Habe direkt die Tipps umgesetzt.',0,'1','Mozilla/5.0 (Windows NT 10.0; Win64; x64)','comment',0,0),
(9,7,'Developer123','dev@techblog.io','https://techblog.io','45.33.22.11','2024-04-10 09:45:00','2024-04-10 08:45:00','ProcessWire ist definitiv unterschätzt. Nutze es seit Jahren.',0,'1','Mozilla/5.0 (X11; Linux x86_64)','comment',0,0),
(10,9,'Shop Owner','shop@example.at','','192.168.50.1','2024-04-12 13:00:00','2024-04-12 12:00:00','WooCommerce läuft bei mir seit 3 Jahren problemlos.',0,'1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)','comment',0,0),
(11,5,'SEO Experte','seo@marketing.de','https://marketing.de','78.90.12.34','2024-04-15 10:30:00','2024-04-15 09:30:00','Wichtig ist auch noch: Core Web Vitals nicht vergessen!',0,'1','Mozilla/5.0 (Windows NT 10.0)','comment',6,0),
(12,7,'Anna Berger','anna.berger@company.at','https://company.at','77.88.99.11','2024-04-18 16:00:00','2024-04-18 15:00:00','Wann kommt der Artikel über Drupal?',0,'0','Mozilla/5.0 (iPhone; CPU iPhone OS 17_0)','comment',0,0),
(13,9,'Trackback','','https://other-blog.com/woocommerce-tips/','72.232.101.12','2024-04-20 08:00:00','2024-04-20 07:00:00','[...] Wie in diesem Tutorial erklärt wird [...]',0,'1','','trackback',0,0),
(14,1,'Pingback','','https://news-site.de/wordpress-news/','91.22.33.44','2024-04-22 11:00:00','2024-04-22 10:00:00','[...] Ein guter Einstieg für WordPress [...]',0,'1','','pingback',0,0),
(15,5,'Thomas Huber','thomas.huber@gmail.com','','91.22.145.88','2024-04-25 14:30:00','2024-04-25 13:30:00','Update: Google hat jetzt neue Guidelines veröffentlicht.',0,'1','Mozilla/5.0 (Windows NT 11.0; Win64; x64)','comment',0,0);

--
-- Dumping data for table `wp_commentmeta`
--

INSERT INTO `wp_commentmeta` VALUES 
(1,1,'_wp_trash_meta_status','1'),
(2,1,'_wp_trash_meta_time','1706520000'),
(3,2,'rating','5'),
(4,2,'verified','1'),
(5,3,'rating','4'),
(6,4,'_edit_lock','1706780000:1'),
(7,5,'akismet_result','true'),
(8,5,'akismet_as_submitted','a:15:{s:14:\"comment_author\";s:8:\"Spam Bot\";s:20:\"comment_author_email\";s:15:\"spam@spammer.ru\";}'),
(9,6,'admin_reply','1'),
(10,7,'_wp_comment_author_IP_country','AT'),
(11,2,'helpful_votes','12'),
(12,3,'helpful_votes','3'),
(13,4,'response_time','86400'),
(14,6,'pinned','1'),
(15,7,'sentiment_score','0.85');

--
-- Table structure for table `wp_links`
--

DROP TABLE IF EXISTS `wp_links`;
CREATE TABLE `wp_links` (
  `link_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `link_url` varchar(255) COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT '',
  `link_name` varchar(255) COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT '',
  `link_image` varchar(255) COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT '',
  `link_target` varchar(25) COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT '',
  `link_description` varchar(255) COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT '',
  `link_visible` varchar(20) COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT 'Y',
  `link_owner` bigint unsigned NOT NULL DEFAULT '1',
  `link_rating` int NOT NULL DEFAULT '0',
  `link_updated` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `link_rel` varchar(255) COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT '',
  `link_notes` mediumtext COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `link_rss` varchar(255) COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT '',
  PRIMARY KEY (`link_id`),
  KEY `link_visible` (`link_visible`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

--
-- Dumping data for table `wp_links`
--

INSERT INTO `wp_links` VALUES 
(1,'https://wordpress.org/','WordPress','','_blank','Die offizielle WordPress-Website','Y',1,9,'2024-01-15 10:00:00','friend','Hauptressource für WP','https://wordpress.org/news/feed/'),
(2,'https://developer.wordpress.org/','WordPress Developer','','_blank','Entwickler-Dokumentation','Y',1,8,'2024-02-01 12:00:00','','Technische Docs',''),
(3,'https://processwire.com/','ProcessWire CMS','','_blank','Alternatives PHP CMS','Y',1,7,'2024-02-15 09:00:00','colleague','Gutes CMS für Entwickler','https://processwire.com/blog/rss/'),
(4,'https://woocommerce.com/','WooCommerce','','','E-Commerce Plugin','Y',1,6,'2024-03-01 14:00:00','','Shop-Lösung',''),
(5,'https://yoast.com/','Yoast SEO','','_blank','SEO Plugin Hersteller','N',1,5,'2024-03-10 11:00:00','','SEO Tools','https://yoast.com/feed/');

--
-- Table structure for table `wp_options`
--

DROP TABLE IF EXISTS `wp_options`;
CREATE TABLE `wp_options` (
  `option_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `option_name` varchar(191) COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT '',
  `option_value` longtext COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `autoload` varchar(20) COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT 'yes',
  PRIMARY KEY (`option_id`),
  UNIQUE KEY `option_name` (`option_name`),
  KEY `autoload` (`autoload`)
) ENGINE=InnoDB AUTO_INCREMENT=200 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

--
-- Dumping data for table `wp_options`
--

INSERT INTO `wp_options` VALUES 
(1,'siteurl','https://example.com','yes'),
(2,'home','https://example.com','yes'),
(3,'blogname','Mein WordPress Blog','yes'),
(4,'blogdescription','Ein weiterer WordPress-Blog','yes'),
(5,'users_can_register','0','yes'),
(6,'admin_email','admin@example.com','yes'),
(7,'start_of_week','1','yes'),
(8,'use_balanceTags','0','yes'),
(9,'use_smilies','1','yes'),
(10,'require_name_email','1','yes'),
(11,'comments_notify','1','yes'),
(12,'posts_per_rss','10','yes'),
(13,'rss_use_excerpt','0','yes'),
(14,'mailserver_url','mail.example.com','yes'),
(15,'mailserver_login','login@example.com','yes'),
(16,'mailserver_pass','','yes'),
(17,'mailserver_port','110','yes'),
(18,'default_category','1','yes'),
(19,'default_comment_status','open','yes'),
(20,'default_ping_status','open','yes'),
(21,'default_pingback_flag','1','yes'),
(22,'posts_per_page','10','yes'),
(23,'date_format','d.m.Y','yes'),
(24,'time_format','H:i','yes'),
(25,'links_updated_date_format','d.m.Y H:i','yes'),
(26,'comment_moderation','0','yes'),
(27,'moderation_notify','1','yes'),
(28,'permalink_structure','/%postname%/','yes'),
(29,'rewrite_rules','a:95:{s:11:\"^wp-json/?$\";s:22:\"index.php?rest_route=/\";s:14:\"^wp-json/(.*)?\";s:33:\"index.php?rest_route=/$matches[1]\";}','yes'),
(30,'hack_file','0','yes'),
(31,'blog_charset','UTF-8','yes'),
(32,'moderation_keys','','no'),
(33,'active_plugins','a:3:{i:0;s:31:\"contact-form-7/wp-contact-form-7.php\";i:1;s:24:\"wordpress-seo/wp-seo.php\";i:2;s:27:\"woocommerce/woocommerce.php\";}','yes'),
(34,'category_base','','yes'),
(35,'ping_sites','http://rpc.pingomatic.com/','yes'),
(36,'comment_max_links','2','yes'),
(37,'gmt_offset','1','yes'),
(38,'default_email_category','1','yes'),
(39,'recently_edited','','no'),
(40,'template','flavflavor','yes'),
(41,'stylesheet','flavorblog-flavor','yes'),
(42,'comment_registration','0','yes'),
(43,'html_type','text/html','yes'),
(44,'use_trackback','0','yes'),
(45,'default_role','subscriber','yes'),
(46,'db_version','55853','yes'),
(47,'uploads_use_yearmonth_folders','1','yes'),
(48,'upload_path','','yes'),
(49,'blog_public','1','yes'),
(50,'default_link_category','2','yes'),
(51,'show_on_front','page','yes'),
(52,'tag_base','','yes'),
(53,'show_avatars','1','yes'),
(54,'avatar_rating','G','yes'),
(55,'upload_url_path','','yes'),
(56,'thumbnail_size_w','150','yes'),
(57,'thumbnail_size_h','150','yes'),
(58,'thumbnail_crop','1','yes'),
(59,'medium_size_w','300','yes'),
(60,'medium_size_h','300','yes'),
(61,'avatar_default','mystery','yes'),
(62,'large_size_w','1024','yes'),
(63,'large_size_h','1024','yes'),
(64,'image_default_link_type','none','yes'),
(65,'image_default_size','','yes'),
(66,'image_default_align','','yes'),
(67,'close_comments_for_old_posts','0','yes'),
(68,'close_comments_days_old','14','yes'),
(69,'thread_comments','1','yes'),
(70,'thread_comments_depth','5','yes'),
(71,'page_comments','0','yes'),
(72,'comments_per_page','50','yes'),
(73,'default_comments_page','newest','yes'),
(74,'comment_order','asc','yes'),
(75,'sticky_posts','a:0:{}','yes'),
(76,'widget_categories','a:2:{i:1;a:0:{}s:12:\"_multiwidget\";i:1;}','yes'),
(77,'widget_text','a:2:{i:1;a:0:{}s:12:\"_multiwidget\";i:1;}','yes'),
(78,'widget_rss','a:2:{i:1;a:0:{}s:12:\"_multiwidget\";i:1;}','yes'),
(79,'uninstall_plugins','a:0:{}','no'),
(80,'timezone_string','Europe/Vienna','yes'),
(81,'page_for_posts','0','yes'),
(82,'page_on_front','2','yes'),
(83,'default_post_format','0','yes'),
(84,'link_manager_enabled','0','yes'),
(85,'finished_splitting_shared_terms','1','yes'),
(86,'site_icon','0','yes'),
(87,'medium_large_size_w','768','yes'),
(88,'medium_large_size_h','0','yes'),
(89,'wp_page_for_privacy_policy','3','yes'),
(90,'show_comments_cookies_opt_in','1','yes'),
(91,'admin_email_lifespan','1735689600','yes'),
(92,'disallowed_keys','','no'),
(93,'comment_previously_approved','1','yes'),
(94,'auto_plugin_theme_update_emails','a:0:{}','no'),
(95,'auto_update_core_dev','enabled','yes'),
(96,'auto_update_core_minor','enabled','yes'),
(97,'auto_update_core_major','unset','yes'),
(98,'wp_force_deactivated_plugins','a:0:{}','yes'),
(99,'initial_db_version','55853','yes'),
(100,'wp_user_roles','a:5:{s:13:\"administrator\";a:2:{s:4:\"name\";s:13:\"Administrator\";s:12:\"capabilities\";a:61:{s:13:\"switch_themes\";b:1;s:11:\"edit_themes\";b:1;s:16:\"activate_plugins\";b:1;s:12:\"edit_plugins\";b:1;s:10:\"edit_users\";b:1;s:10:\"edit_files\";b:1;s:14:\"manage_options\";b:1;s:17:\"moderate_comments\";b:1;s:17:\"manage_categories\";b:1;s:12:\"manage_links\";b:1;s:12:\"upload_files\";b:1;s:6:\"import\";b:1;s:15:\"unfiltered_html\";b:1;s:10:\"edit_posts\";b:1;s:17:\"edit_others_posts\";b:1;s:20:\"edit_published_posts\";b:1;s:13:\"publish_posts\";b:1;s:10:\"edit_pages\";b:1;s:4:\"read\";b:1;s:8:\"level_10\";b:1;s:7:\"level_9\";b:1;s:7:\"level_8\";b:1;s:7:\"level_7\";b:1;s:7:\"level_6\";b:1;s:7:\"level_5\";b:1;s:7:\"level_4\";b:1;s:7:\"level_3\";b:1;s:7:\"level_2\";b:1;s:7:\"level_1\";b:1;s:7:\"level_0\";b:1;s:17:\"edit_others_pages\";b:1;s:20:\"edit_published_pages\";b:1;s:13:\"publish_pages\";b:1;s:12:\"delete_pages\";b:1;s:19:\"delete_others_pages\";b:1;s:22:\"delete_published_pages\";b:1;s:12:\"delete_posts\";b:1;s:19:\"delete_others_posts\";b:1;s:22:\"delete_published_posts\";b:1;s:20:\"delete_private_posts\";b:1;s:18:\"edit_private_posts\";b:1;s:18:\"read_private_posts\";b:1;s:20:\"delete_private_pages\";b:1;s:18:\"edit_private_pages\";b:1;s:18:\"read_private_pages\";b:1;s:12:\"delete_users\";b:1;s:12:\"create_users\";b:1;s:17:\"unfiltered_upload\";b:1;s:14:\"edit_dashboard\";b:1;s:14:\"update_plugins\";b:1;s:14:\"delete_plugins\";b:1;s:15:\"install_plugins\";b:1;s:13:\"update_themes\";b:1;s:14:\"install_themes\";b:1;s:11:\"update_core\";b:1;s:10:\"list_users\";b:1;s:12:\"remove_users\";b:1;s:13:\"promote_users\";b:1;s:18:\"edit_theme_options\";b:1;s:13:\"delete_themes\";b:1;s:6:\"export\";b:1;}}s:6:\"editor\";a:2:{s:4:\"name\";s:6:\"Editor\";s:12:\"capabilities\";a:34:{s:17:\"moderate_comments\";b:1;s:17:\"manage_categories\";b:1;s:12:\"manage_links\";b:1;s:12:\"upload_files\";b:1;s:15:\"unfiltered_html\";b:1;s:10:\"edit_posts\";b:1;s:17:\"edit_others_posts\";b:1;s:20:\"edit_published_posts\";b:1;s:13:\"publish_posts\";b:1;s:10:\"edit_pages\";b:1;s:4:\"read\";b:1;s:7:\"level_7\";b:1;s:7:\"level_6\";b:1;s:7:\"level_5\";b:1;s:7:\"level_4\";b:1;s:7:\"level_3\";b:1;s:7:\"level_2\";b:1;s:7:\"level_1\";b:1;s:7:\"level_0\";b:1;s:17:\"edit_others_pages\";b:1;s:20:\"edit_published_pages\";b:1;s:13:\"publish_pages\";b:1;s:12:\"delete_pages\";b:1;s:19:\"delete_others_pages\";b:1;s:22:\"delete_published_pages\";b:1;s:12:\"delete_posts\";b:1;s:19:\"delete_others_posts\";b:1;s:22:\"delete_published_posts\";b:1;s:20:\"delete_private_posts\";b:1;s:18:\"edit_private_posts\";b:1;s:18:\"read_private_posts\";b:1;s:20:\"delete_private_pages\";b:1;s:18:\"edit_private_pages\";b:1;s:18:\"read_private_pages\";b:1;}}s:6:\"author\";a:2:{s:4:\"name\";s:6:\"Author\";s:12:\"capabilities\";a:10:{s:12:\"upload_files\";b:1;s:10:\"edit_posts\";b:1;s:20:\"edit_published_posts\";b:1;s:13:\"publish_posts\";b:1;s:4:\"read\";b:1;s:7:\"level_2\";b:1;s:7:\"level_1\";b:1;s:7:\"level_0\";b:1;s:12:\"delete_posts\";b:1;s:22:\"delete_published_posts\";b:1;}}s:11:\"contributor\";a:2:{s:4:\"name\";s:11:\"Contributor\";s:12:\"capabilities\";a:5:{s:10:\"edit_posts\";b:1;s:4:\"read\";b:1;s:7:\"level_1\";b:1;s:7:\"level_0\";b:1;s:12:\"delete_posts\";b:1;}}s:10:\"subscriber\";a:2:{s:4:\"name\";s:10:\"Subscriber\";s:12:\"capabilities\";a:2:{s:4:\"read\";b:1;s:7:\"level_0\";b:1;}}}','yes'),
(101,'fresh_site','0','yes'),
(102,'WPLANG','de_DE','yes'),
(103,'widget_block','a:6:{i:2;a:1:{s:7:\"content\";s:19:\"<!-- wp:search /-->\";}i:3;a:1:{s:7:\"content\";s:154:\"<!-- wp:group --><div class=\"wp-block-group\"><!-- wp:heading --><h2>Aktuelle Beiträge</h2><!-- /wp:heading --><!-- wp:latest-posts /--></div><!-- /wp:group -->\";}i:4;a:1:{s:7:\"content\";s:227:\"<!-- wp:group --><div class=\"wp-block-group\"><!-- wp:heading --><h2>Letzte Kommentare</h2><!-- /wp:heading --><!-- wp:latest-comments {\"displayAvatar\":false,\"displayDate\":false,\"displayExcerpt\":false} /--></div><!-- /wp:group -->\";}i:5;a:1:{s:7:\"content\";s:146:\"<!-- wp:group --><div class=\"wp-block-group\"><!-- wp:heading --><h2>Archiv</h2><!-- /wp:heading --><!-- wp:archives /--></div><!-- /wp:group -->\";}i:6;a:1:{s:7:\"content\";s:150:\"<!-- wp:group --><div class=\"wp-block-group\"><!-- wp:heading --><h2>Kategorien</h2><!-- /wp:heading --><!-- wp:categories /--></div><!-- /wp:group -->\";}s:12:\"_multiwidget\";i:1;}','yes'),
(104,'sidebars_widgets','a:2:{s:19:\"wp_inactive_widgets\";a:0:{}s:9:\"sidebar-1\";a:5:{i:0;s:7:\"block-2\";i:1;s:7:\"block-3\";i:2;s:7:\"block-4\";i:3;s:7:\"block-5\";i:4;s:7:\"block-6\";}}','yes'),
(105,'cron','a:8:{i:1706544000;a:1:{s:34:\"wp_privacy_delete_old_export_files\";a:1:{s:32:\"40cd750bba9870f18aada2478b24840a\";a:3:{s:8:\"schedule\";s:6:\"hourly\";s:4:\"args\";a:0:{}s:8:\"interval\";i:3600;}}}i:1706562000;a:4:{s:18:\"wp_https_detection\";a:1:{s:32:\"40cd750bba9870f18aada2478b24840a\";a:3:{s:8:\"schedule\";s:10:\"twicedaily\";s:4:\"args\";a:0:{}s:8:\"interval\";i:43200;}}s:16:\"wp_version_check\";a:1:{s:32:\"40cd750bba9870f18aada2478b24840a\";a:3:{s:8:\"schedule\";s:10:\"twicedaily\";s:4:\"args\";a:0:{}s:8:\"interval\";i:43200;}}s:17:\"wp_update_plugins\";a:1:{s:32:\"40cd750bba9870f18aada2478b24840a\";a:3:{s:8:\"schedule\";s:10:\"twicedaily\";s:4:\"args\";a:0:{}s:8:\"interval\";i:43200;}}s:16:\"wp_update_themes\";a:1:{s:32:\"40cd750bba9870f18aada2478b24840a\";a:3:{s:8:\"schedule\";s:10:\"twicedaily\";s:4:\"args\";a:0:{}s:8:\"interval\";i:43200;}}}i:1706605200;a:1:{s:32:\"recovery_mode_clean_expired_keys\";a:1:{s:32:\"40cd750bba9870f18aada2478b24840a\";a:3:{s:8:\"schedule\";s:5:\"daily\";s:4:\"args\";a:0:{}s:8:\"interval\";i:86400;}}}i:1706608800;a:2:{s:19:\"wp_scheduled_delete\";a:1:{s:32:\"40cd750bba9870f18aada2478b24840a\";a:3:{s:8:\"schedule\";s:5:\"daily\";s:4:\"args\";a:0:{}s:8:\"interval\";i:86400;}}s:25:\"delete_expired_transients\";a:1:{s:32:\"40cd750bba9870f18aada2478b24840a\";a:3:{s:8:\"schedule\";s:5:\"daily\";s:4:\"args\";a:0:{}s:8:\"interval\";i:86400;}}}i:1706612400;a:1:{s:30:\"wp_scheduled_auto_draft_delete\";a:1:{s:32:\"40cd750bba9870f18aada2478b24840a\";a:3:{s:8:\"schedule\";s:5:\"daily\";s:4:\"args\";a:0:{}s:8:\"interval\";i:86400;}}}i:1707123600;a:1:{s:30:\"wp_site_health_scheduled_check\";a:1:{s:32:\"40cd750bba9870f18aada2478b24840a\";a:3:{s:8:\"schedule\";s:6:\"weekly\";s:4:\"args\";a:0:{}s:8:\"interval\";i:604800;}}}s:7:\"version\";i:2;}','yes'),
(106,'theme_mods_flavorblog-flavor','a:4:{i:0;b:0;s:18:\"nav_menu_locations\";a:1:{s:7:\"primary\";i:2;}s:18:\"custom_css_post_id\";i:-1;s:16:\"sidebars_widgets\";a:2:{s:4:\"time\";i:1706432100;s:4:\"data\";a:2:{s:19:\"wp_inactive_widgets\";a:0:{}s:9:\"sidebar-1\";a:5:{i:0;s:7:\"block-2\";i:1;s:7:\"block-3\";i:2;s:7:\"block-4\";i:3;s:7:\"block-5\";i:4;s:7:\"block-6\";}}}}','yes');

--
-- Table structure for table `wp_postmeta`
--

DROP TABLE IF EXISTS `wp_postmeta`;
CREATE TABLE `wp_postmeta` (
  `meta_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `post_id` bigint unsigned NOT NULL DEFAULT '0',
  `meta_key` varchar(255) COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  `meta_value` longtext COLLATE utf8mb4_unicode_520_ci,
  PRIMARY KEY (`meta_id`),
  KEY `post_id` (`post_id`),
  KEY `meta_key` (`meta_key`(191))
) ENGINE=InnoDB AUTO_INCREMENT=50 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

--
-- Dumping data for table `wp_postmeta`
--

INSERT INTO `wp_postmeta` VALUES 
(1,2,'_wp_page_template','default'),
(2,3,'_wp_page_template','default'),
(3,5,'_edit_last','1'),
(4,5,'_edit_lock','1706520000:1'),
(5,5,'_thumbnail_id','15'),
(6,6,'_wp_attached_file','2024/01/hero-image.jpg'),
(7,6,'_wp_attachment_metadata','a:6:{s:5:\"width\";i:1920;s:6:\"height\";i:1080;s:4:\"file\";s:22:\"2024/01/hero-image.jpg\";s:8:\"filesize\";i:245678;s:5:\"sizes\";a:4:{s:6:\"medium\";a:5:{s:4:\"file\";s:22:\"hero-image-300x169.jpg\";s:5:\"width\";i:300;s:6:\"height\";i:169;s:9:\"mime-type\";s:10:\"image/jpeg\";s:8:\"filesize\";i:12345;}s:5:\"large\";a:5:{s:4:\"file\";s:23:\"hero-image-1024x576.jpg\";s:5:\"width\";i:1024;s:6:\"height\";i:576;s:9:\"mime-type\";s:10:\"image/jpeg\";s:8:\"filesize\";i:89012;}s:9:\"thumbnail\";a:5:{s:4:\"file\";s:22:\"hero-image-150x150.jpg\";s:5:\"width\";i:150;s:6:\"height\";i:150;s:9:\"mime-type\";s:10:\"image/jpeg\";s:8:\"filesize\";i:5678;}s:12:\"medium_large\";a:5:{s:4:\"file\";s:22:\"hero-image-768x432.jpg\";s:5:\"width\";i:768;s:6:\"height\";i:432;s:9:\"mime-type\";s:10:\"image/jpeg\";s:8:\"filesize\";i:56789;}}s:10:\"image_meta\";a:12:{s:8:\"aperture\";s:1:\"0\";s:6:\"credit\";s:0:\"\";s:6:\"camera\";s:0:\"\";s:7:\"caption\";s:0:\"\";s:17:\"created_timestamp\";s:1:\"0\";s:9:\"copyright\";s:0:\"\";s:12:\"focal_length\";s:1:\"0\";s:3:\"iso\";s:1:\"0\";s:13:\"shutter_speed\";s:1:\"0\";s:5:\"title\";s:0:\"\";s:11:\"orientation\";s:1:\"0\";s:8:\"keywords\";a:0:{}}}'),
(8,7,'_edit_last','1'),
(9,7,'_edit_lock','1706780000:1'),
(10,8,'_menu_item_type','post_type'),
(11,8,'_menu_item_menu_item_parent','0'),
(12,8,'_menu_item_object_id','2'),
(13,8,'_menu_item_object','page'),
(14,8,'_menu_item_target',''),
(15,8,'_menu_item_classes','a:1:{i:0;s:0:\"\";}'),
(16,8,'_menu_item_xfn',''),
(17,8,'_menu_item_url',''),
(18,9,'_edit_last','1'),
(19,9,'_yoast_wpseo_primary_category','1'),
(20,9,'_yoast_wpseo_content_score','60'),
(21,9,'_yoast_wpseo_estimated-reading-time-minutes','4'),
(22,10,'_edit_last','2'),
(23,10,'_thumbnail_id','16'),
(24,11,'_wp_attached_file','2024/02/team-photo.png'),
(25,11,'_wp_attachment_metadata','a:6:{s:5:\"width\";i:800;s:6:\"height\";i:600;s:4:\"file\";s:22:\"2024/02/team-photo.png\";s:8:\"filesize\";i:156789;s:5:\"sizes\";a:3:{s:6:\"medium\";a:5:{s:4:\"file\";s:22:\"team-photo-300x225.png\";s:5:\"width\";i:300;s:6:\"height\";i:225;s:9:\"mime-type\";s:9:\"image/png\";s:8:\"filesize\";i:23456;}s:9:\"thumbnail\";a:5:{s:4:\"file\";s:22:\"team-photo-150x150.png\";s:5:\"width\";i:150;s:6:\"height\";i:150;s:9:\"mime-type\";s:9:\"image/png\";s:8:\"filesize\";i:8901;}s:12:\"medium_large\";a:5:{s:4:\"file\";s:22:\"team-photo-768x576.png\";s:5:\"width\";i:768;s:6:\"height\";i:576;s:9:\"mime-type\";s:9:\"image/png\";s:8:\"filesize\";i:98765;}}s:10:\"image_meta\";a:12:{s:8:\"aperture\";s:1:\"0\";s:6:\"credit\";s:0:\"\";s:6:\"camera\";s:0:\"\";s:7:\"caption\";s:0:\"\";s:17:\"created_timestamp\";s:1:\"0\";s:9:\"copyright\";s:0:\"\";s:12:\"focal_length\";s:1:\"0\";s:3:\"iso\";s:1:\"0\";s:13:\"shutter_speed\";s:1:\"0\";s:5:\"title\";s:0:\"\";s:11:\"orientation\";s:1:\"0\";s:8:\"keywords\";a:0:{}}}'),
(26,12,'_wp_attached_file','2024/03/download.pdf'),
(27,12,'_wp_attachment_metadata','a:0:{}'),
(28,13,'_wc_product_price','29.99'),
(29,13,'_wc_product_sku','PROD-001'),
(30,13,'_stock_status','instock'),
(31,14,'_wc_product_price','49.99'),
(32,14,'_wc_product_sku','PROD-002'),
(33,14,'_stock_status','instock'),
(34,5,'_yoast_wpseo_focuskw','seo grundlagen'),
(35,5,'_yoast_wpseo_metadesc','Lernen Sie die SEO Grundlagen in diesem umfassenden Leitfaden für Einsteiger.'),
(36,5,'_yoast_wpseo_linkdex','75'),
(37,7,'_yoast_wpseo_focuskw','cms vergleich'),
(38,7,'_yoast_wpseo_linkdex','68'),
(39,9,'_yoast_wpseo_focuskw','woocommerce tutorial'),
(40,13,'_regular_price','39.99'),
(41,13,'_sale_price','29.99'),
(42,13,'_price','29.99'),
(43,13,'_stock','100'),
(44,13,'_manage_stock','yes'),
(45,13,'_weight','0.5'),
(46,13,'_length','10'),
(47,13,'_width','15'),
(48,13,'_height','2'),
(49,14,'_regular_price','59.99'),
(50,14,'_sale_price','49.99'),
(51,14,'_price','49.99'),
(52,14,'_stock','50'),
(53,14,'_downloadable','yes'),
(54,14,'_virtual','yes'),
(55,14,'_download_limit','-1'),
(56,14,'_download_expiry','-1'),
(57,5,'_wp_old_slug','seo-basics'),
(58,5,'_pingme','1'),
(59,5,'_encloseme','1'),
(60,6,'_wp_attachment_image_alt','Hero Bild für die Startseite'),
(61,11,'_wp_attachment_image_alt','Unser Team'),
(62,1,'_edit_last','1'),
(63,1,'_edit_lock','1706432100:1'),
(64,2,'_edit_last','1'),
(65,3,'_edit_last','1'),
(66,10,'_edit_last','2'),
(67,10,'_yoast_wpseo_content_score','80'),
(68,15,'_edit_last','1'),
(69,15,'_wp_trash_meta_status','draft'),
(70,15,'_wp_desired_post_slug','wordpress-performance-optimieren'),
(71,13,'total_sales','25'),
(72,14,'total_sales','42'),
(73,13,'_wc_average_rating','4.5'),
(74,13,'_wc_review_count','8'),
(75,14,'_wc_average_rating','4.8'),
(76,14,'_wc_review_count','15'),
(77,5,'views_count','1250'),
(78,7,'views_count','890'),
(79,9,'views_count','2340'),
(80,1,'views_count','156');

--
-- Table structure for table `wp_posts`
--

DROP TABLE IF EXISTS `wp_posts`;
CREATE TABLE `wp_posts` (
  `ID` bigint unsigned NOT NULL AUTO_INCREMENT,
  `post_author` bigint unsigned NOT NULL DEFAULT '0',
  `post_date` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `post_date_gmt` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `post_content` longtext COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `post_title` text COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `post_excerpt` text COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `post_status` varchar(20) COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT 'publish',
  `comment_status` varchar(20) COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT 'open',
  `ping_status` varchar(20) COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT 'open',
  `post_password` varchar(255) COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT '',
  `post_name` varchar(200) COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT '',
  `to_ping` text COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `pinged` text COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `post_modified` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `post_modified_gmt` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `post_content_filtered` longtext COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `post_parent` bigint unsigned NOT NULL DEFAULT '0',
  `guid` varchar(255) COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT '',
  `menu_order` int NOT NULL DEFAULT '0',
  `post_type` varchar(20) COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT 'post',
  `post_mime_type` varchar(100) COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT '',
  `comment_count` bigint NOT NULL DEFAULT '0',
  PRIMARY KEY (`ID`),
  KEY `post_name` (`post_name`(191)),
  KEY `type_status_date` (`post_type`,`post_status`,`post_date`,`ID`),
  KEY `post_parent` (`post_parent`),
  KEY `post_author` (`post_author`)
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

--
-- Dumping data for table `wp_posts`
--

INSERT INTO `wp_posts` VALUES 
(1,1,'2024-01-15 10:00:00','2024-01-15 09:00:00','<!-- wp:paragraph -->\n<p>Willkommen bei WordPress. Dies ist dein erster Beitrag. Bearbeite oder lösche ihn und beginne mit dem Schreiben!</p>\n<!-- /wp:paragraph -->','Hallo Welt!','','publish','open','open','','hallo-welt','','','2024-01-15 10:00:00','2024-01-15 09:00:00','',0,'https://example.com/?p=1',0,'post','',1),
(2,1,'2024-01-15 10:00:00','2024-01-15 09:00:00','<!-- wp:paragraph -->\n<p>Dies ist eine Beispielseite. Sie unterscheidet sich von einem Blog-Beitrag, da sie stets an derselben Stelle bleibt und (bei den meisten Themes) in der Website-Navigation angezeigt wird.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:paragraph -->\n<p>Die meisten starten mit einer „Über uns"-Seite mit einer Vorstellung für potenzielle Besucher der Website.</p>\n<!-- /wp:paragraph -->','Startseite','','publish','closed','closed','','startseite','','','2024-01-15 10:00:00','2024-01-15 09:00:00','',0,'https://example.com/?page_id=2',0,'page','',0),
(3,1,'2024-01-15 10:00:00','2024-01-15 09:00:00','<!-- wp:paragraph -->\n<p>Wir nehmen Ihre Privatsphäre sehr ernst. Diese Datenschutzerklärung beschreibt, wie wir Ihre personenbezogenen Daten erfassen, verwenden und schützen.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:heading -->\n<h2>Welche Daten wir erheben</h2>\n<!-- /wp:heading -->\n\n<!-- wp:paragraph -->\n<p>Wir erheben verschiedene Arten von Informationen, darunter:</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:list -->\n<ul>\n<li>Name und Kontaktdaten</li>\n<li>E-Mail-Adresse</li>\n<li>IP-Adressen und Browser-Informationen</li>\n</ul>\n<!-- /wp:list -->','Datenschutzerklärung','','publish','closed','closed','','datenschutzerklaerung','','','2024-01-15 10:00:00','2024-01-15 09:00:00','',0,'https://example.com/?page_id=3',0,'page','',2),
(4,1,'2024-01-15 10:00:00','2024-01-15 09:00:00','','Startseite','','inherit','closed','closed','','2-revision-v1','','','2024-01-15 10:00:00','2024-01-15 09:00:00','',2,'https://example.com/?p=4',0,'revision','',0),
(5,1,'2024-02-10 14:30:00','2024-02-10 13:30:00','<!-- wp:paragraph -->\n<p>In diesem Artikel erfahren Sie alles über die Grundlagen der Suchmaschinenoptimierung. SEO ist heute wichtiger denn je für den Erfolg Ihrer Website.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:heading -->\n<h2>Was ist SEO?</h2>\n<!-- /wp:heading -->\n\n<!-- wp:paragraph -->\n<p>SEO steht für Search Engine Optimization und umfasst alle Maßnahmen, die dazu dienen, die Sichtbarkeit einer Website in den organischen Suchergebnissen zu verbessern.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:heading {\"level\":3} -->\n<h3>On-Page SEO</h3>\n<!-- /wp:heading -->\n\n<!-- wp:paragraph -->\n<p>On-Page SEO bezieht sich auf alle Optimierungen, die direkt auf Ihrer Website vorgenommen werden können, wie z.B. die Optimierung von Meta-Tags, Überschriften und Inhalten.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:heading {\"level\":3} -->\n<h3>Off-Page SEO</h3>\n<!-- /wp:heading -->\n\n<!-- wp:paragraph -->\n<p>Off-Page SEO umfasst alle Aktivitäten außerhalb Ihrer Website, die Ihr Ranking beeinflussen, wie z.B. Backlinks und Social Signals.</p>\n<!-- /wp:paragraph -->','SEO Grundlagen für Einsteiger','Ein umfassender Leitfaden für SEO-Anfänger','publish','open','open','','seo-grundlagen-fuer-einsteiger','','','2024-02-15 09:00:00','2024-02-15 08:00:00','',0,'https://example.com/?p=5',0,'post','',2),
(6,1,'2024-01-20 11:00:00','2024-01-20 10:00:00','','hero-image','','inherit','open','closed','','hero-image','','','2024-01-20 11:00:00','2024-01-20 10:00:00','',5,'https://example.com/wp-content/uploads/2024/01/hero-image.jpg',0,'attachment','image/jpeg',0),
(7,1,'2024-03-01 16:00:00','2024-03-01 15:00:00','<!-- wp:paragraph -->\n<p>Die richtige Wahl des Content-Management-Systems ist entscheidend für den langfristigen Erfolg Ihres Web-Projekts. In diesem Vergleich betrachten wir die Vor- und Nachteile verschiedener Systeme.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:heading -->\n<h2>WordPress</h2>\n<!-- /wp:heading -->\n\n<!-- wp:paragraph -->\n<p>WordPress ist das weltweit meistgenutzte CMS mit einem Marktanteil von über 40%. Es punktet durch seine einfache Bedienung und die riesige Plugin-Bibliothek.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:heading -->\n<h2>ProcessWire</h2>\n<!-- /wp:heading -->\n\n<!-- wp:paragraph -->\n<p>ProcessWire ist ein flexibles PHP-CMS, das sich besonders für Entwickler eignet, die volle Kontrolle über ihre Projekte haben möchten.</p>\n<!-- /wp:paragraph -->','CMS Vergleich: WordPress vs. ProcessWire','Welches System passt zu Ihrem Projekt?','publish','open','open','','cms-vergleich-wordpress-vs-processwire','','','2024-03-05 10:00:00','2024-03-05 09:00:00','',0,'https://example.com/?p=7',0,'post','',0),
(8,1,'2024-01-16 12:00:00','2024-01-16 11:00:00','','','','publish','closed','closed','','8','','','2024-01-16 12:00:00','2024-01-16 11:00:00','',0,'https://example.com/?p=8',1,'nav_menu_item','',0),
(9,2,'2024-03-20 09:00:00','2024-03-20 08:00:00','<!-- wp:paragraph -->\n<p>WooCommerce ist die beliebteste E-Commerce-Lösung für WordPress. In diesem Tutorial zeigen wir Ihnen, wie Sie Ihren eigenen Online-Shop einrichten.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:heading -->\n<h2>Installation</h2>\n<!-- /wp:heading -->\n\n<!-- wp:paragraph -->\n<p>Die Installation von WooCommerce ist denkbar einfach. Navigieren Sie zu Plugins → Installieren und suchen Sie nach \"WooCommerce\".</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:heading -->\n<h2>Grundeinstellungen</h2>\n<!-- /wp:heading -->\n\n<!-- wp:paragraph -->\n<p>Nach der Installation führt Sie der Setup-Assistent durch die wichtigsten Einstellungen wie Standort, Währung und Zahlungsmethoden.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:heading -->\n<h2>Produkte anlegen</h2>\n<!-- /wp:heading -->\n\n<!-- wp:paragraph -->\n<p>Produkte können Sie unter Produkte → Erstellen anlegen. WooCommerce unterstützt verschiedene Produkttypen wie einfache Produkte, variable Produkte und gruppierte Produkte.</p>\n<!-- /wp:paragraph -->','WooCommerce Shop einrichten - Komplette Anleitung','Schritt-für-Schritt zum eigenen Online-Shop','publish','open','open','','woocommerce-shop-einrichten','','','2024-03-22 14:00:00','2024-03-22 13:00:00','',0,'https://example.com/?p=9',0,'post','',1),
(10,2,'2024-04-01 10:00:00','2024-04-01 09:00:00','<!-- wp:paragraph -->\n<p>Unser Team besteht aus erfahrenen Webentwicklern und Designern, die mit Leidenschaft an Ihren Projekten arbeiten.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:heading -->\n<h2>Unsere Mission</h2>\n<!-- /wp:heading -->\n\n<!-- wp:paragraph -->\n<p>Wir glauben daran, dass jedes Unternehmen eine professionelle Online-Präsenz verdient. Deshalb setzen wir uns dafür ein, hochwertige Websites zu erschwinglichen Preisen zu liefern.</p>\n<!-- /wp:paragraph -->','Über uns','','publish','closed','closed','','ueber-uns','','','2024-04-01 10:00:00','2024-04-01 09:00:00','',0,'https://example.com/?page_id=10',0,'page','',0),
(11,1,'2024-02-15 14:00:00','2024-02-15 13:00:00','','team-photo','','inherit','open','closed','','team-photo','','','2024-02-15 14:00:00','2024-02-15 13:00:00','',10,'https://example.com/wp-content/uploads/2024/02/team-photo.png',0,'attachment','image/png',0),
(12,1,'2024-03-10 09:30:00','2024-03-10 08:30:00','','Produktkatalog 2024','','inherit','open','closed','','produktkatalog-2024','','','2024-03-10 09:30:00','2024-03-10 08:30:00','',0,'https://example.com/wp-content/uploads/2024/03/download.pdf',0,'attachment','application/pdf',0),
(13,1,'2024-03-25 11:00:00','2024-03-25 10:00:00','<!-- wp:paragraph -->\n<p>Hochwertiges WordPress Theme mit modernem Design.</p>\n<!-- /wp:paragraph -->','Premium Theme Basic','','publish','closed','closed','','premium-theme-basic','','','2024-03-25 11:00:00','2024-03-25 10:00:00','',0,'https://example.com/?post_type=product&p=13',0,'product','',0),
(14,1,'2024-03-25 11:30:00','2024-03-25 10:30:00','<!-- wp:paragraph -->\n<p>Professionelles WordPress Theme mit erweiterten Funktionen und Premium-Support.</p>\n<!-- /wp:paragraph -->','Premium Theme Pro','','publish','closed','closed','','premium-theme-pro','','','2024-03-25 11:30:00','2024-03-25 10:30:00','',0,'https://example.com/?post_type=product&p=14',0,'product','',0),
(15,1,'2024-04-10 08:00:00','2024-04-10 07:00:00','<!-- wp:paragraph -->\n<p>Entwurf für einen neuen Blogbeitrag über Performance-Optimierung...</p>\n<!-- /wp:paragraph -->','WordPress Performance optimieren','','draft','open','open','','wordpress-performance-optimieren','','','2024-04-10 08:00:00','2024-04-10 07:00:00','',0,'https://example.com/?p=15',0,'post','',0),
(16,1,'2024-04-15 14:00:00','2024-04-15 13:00:00','','seo-featured','','inherit','open','closed','','seo-featured','','','2024-04-15 14:00:00','2024-04-15 13:00:00','',5,'https://example.com/wp-content/uploads/2024/04/seo-featured.jpg',0,'attachment','image/jpeg',0);

--
-- Table structure for table `wp_termmeta`
--

DROP TABLE IF EXISTS `wp_termmeta`;
CREATE TABLE `wp_termmeta` (
  `meta_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `term_id` bigint unsigned NOT NULL DEFAULT '0',
  `meta_key` varchar(255) COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  `meta_value` longtext COLLATE utf8mb4_unicode_520_ci,
  PRIMARY KEY (`meta_id`),
  KEY `term_id` (`term_id`),
  KEY `meta_key` (`meta_key`(191))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

--
-- Dumping data for table `wp_termmeta`
--

INSERT INTO `wp_termmeta` VALUES 
(1,1,'order','0'),
(2,1,'display_type','default'),
(3,3,'_yoast_wpseo_primary_category',''),
(4,4,'_yoast_wpseo_content_score','30'),
(5,5,'order','1'),
(6,5,'thumbnail_id','16'),
(7,6,'order','2'),
(8,6,'display_type','products'),
(9,6,'thumbnail_id','11'),
(10,7,'_yoast_wpseo_bctitle','Webentwicklung'),
(11,8,'product_count_product_cat','2'),
(12,8,'thumbnail_id','6'),
(13,9,'_yoast_wpseo_focuskw','plugins'),
(14,1,'_edit_lock','1706520000:1'),
(15,2,'menu_icon','dashicons-menu'),
(16,3,'description','Artikel über WordPress CMS'),
(17,4,'description','Suchmaschinenoptimierung'),
(18,5,'featured','1'),
(19,6,'woocommerce_term_meta','a:2:{s:4:\"featured\";s:1:\"1\";s:5:\"color\";s:7:\"#ff6600\";}'),
(20,7,'custom_field','test_value_123');

--
-- Table structure for table `wp_terms`
--

DROP TABLE IF EXISTS `wp_terms`;
CREATE TABLE `wp_terms` (
  `term_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(200) COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT '',
  `slug` varchar(200) COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT '',
  `term_group` bigint NOT NULL DEFAULT '0',
  PRIMARY KEY (`term_id`),
  KEY `slug` (`slug`(191)),
  KEY `name` (`name`(191))
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

--
-- Dumping data for table `wp_terms`
--

INSERT INTO `wp_terms` VALUES 
(1,'Allgemein','allgemein',0),
(2,'Hauptmenü','hauptmenue',0),
(3,'WordPress','wordpress',0),
(4,'SEO','seo',0),
(5,'Tutorials','tutorials',0),
(6,'E-Commerce','e-commerce',0),
(7,'Webentwicklung','webentwicklung',0),
(8,'Themes','themes',0),
(9,'Plugins','plugins',0);

--
-- Table structure for table `wp_term_relationships`
--

DROP TABLE IF EXISTS `wp_term_relationships`;
CREATE TABLE `wp_term_relationships` (
  `object_id` bigint unsigned NOT NULL DEFAULT '0',
  `term_taxonomy_id` bigint unsigned NOT NULL DEFAULT '0',
  `term_order` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`object_id`,`term_taxonomy_id`),
  KEY `term_taxonomy_id` (`term_taxonomy_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

--
-- Dumping data for table `wp_term_relationships`
--

INSERT INTO `wp_term_relationships` VALUES 
(1,1,0),
(5,4,0),
(5,7,0),
(7,3,0),
(7,7,0),
(8,2,0),
(9,3,0),
(9,5,0),
(9,6,0),
(13,6,0),
(13,8,0),
(14,6,0),
(14,8,0),
(15,3,0),
(15,7,0);

--
-- Table structure for table `wp_term_taxonomy`
--

DROP TABLE IF EXISTS `wp_term_taxonomy`;
CREATE TABLE `wp_term_taxonomy` (
  `term_taxonomy_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `term_id` bigint unsigned NOT NULL DEFAULT '0',
  `taxonomy` varchar(32) COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT '',
  `description` longtext COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `parent` bigint unsigned NOT NULL DEFAULT '0',
  `count` bigint NOT NULL DEFAULT '0',
  PRIMARY KEY (`term_taxonomy_id`),
  UNIQUE KEY `term_id_taxonomy` (`term_id`,`taxonomy`),
  KEY `taxonomy` (`taxonomy`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

--
-- Dumping data for table `wp_term_taxonomy`
--

INSERT INTO `wp_term_taxonomy` VALUES 
(1,1,'category','Die Standard-Kategorie für Beiträge',0,1),
(2,2,'nav_menu','',0,1),
(3,3,'post_tag','',0,3),
(4,4,'post_tag','',0,1),
(5,5,'category','Hilfreiche Anleitungen und Tutorials',0,1),
(6,6,'category','Alles rund um E-Commerce und Online-Shops',0,3),
(7,7,'post_tag','',0,3),
(8,8,'product_cat','',0,2),
(9,9,'post_tag','',0,0);

--
-- Table structure for table `wp_usermeta`
--

DROP TABLE IF EXISTS `wp_usermeta`;
CREATE TABLE `wp_usermeta` (
  `umeta_id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL DEFAULT '0',
  `meta_key` varchar(255) COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  `meta_value` longtext COLLATE utf8mb4_unicode_520_ci,
  PRIMARY KEY (`umeta_id`),
  KEY `user_id` (`user_id`),
  KEY `meta_key` (`meta_key`(191))
) ENGINE=InnoDB AUTO_INCREMENT=30 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

--
-- Dumping data for table `wp_usermeta`
--

INSERT INTO `wp_usermeta` VALUES 
(1,1,'nickname','admin'),
(2,1,'first_name','Max'),
(3,1,'last_name','Mustermann'),
(4,1,'description','Website-Administrator und Hauptautor'),
(5,1,'rich_editing','true'),
(6,1,'syntax_highlighting','true'),
(7,1,'comment_shortcuts','false'),
(8,1,'admin_color','fresh'),
(9,1,'use_ssl','0'),
(10,1,'show_admin_bar_front','true'),
(11,1,'locale','de_DE'),
(12,1,'wp_capabilities','a:1:{s:13:\"administrator\";b:1;}'),
(13,1,'wp_user_level','10'),
(14,1,'dismissed_wp_pointers',''),
(15,1,'show_welcome_panel','1'),
(16,1,'session_tokens','a:1:{s:64:\"abc123def456\";a:4:{s:10:\"expiration\";i:1707580800;s:2:\"ip\";s:9:\"127.0.0.1\";s:2:\"ua\";s:68:\"Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/121.0.0.0\";s:5:\"login\";i:1706976000;}}'),
(17,1,'wp_dashboard_quick_press_last_post_id','15'),
(18,1,'community-events-location','a:1:{s:2:\"ip\";s:9:\"127.0.0.0\";}'),
(19,2,'nickname','redakteur'),
(20,2,'first_name','Lisa'),
(21,2,'last_name','Müller'),
(22,2,'description','Redakteurin für technische Artikel'),
(23,2,'rich_editing','true'),
(24,2,'syntax_highlighting','true'),
(25,2,'admin_color','light'),
(26,2,'locale','de_DE'),
(27,2,'wp_capabilities','a:1:{s:6:\"editor\";b:1;}'),
(28,2,'wp_user_level','7'),
(29,2,'show_admin_bar_front','true'),
(30,3,'nickname','hans'),
(31,3,'first_name','Hans'),
(32,3,'last_name','Schmidt'),
(33,3,'description','Gastautor für Technologie-Themen'),
(34,3,'wp_capabilities','a:1:{s:6:\"author\";b:1;}'),
(35,3,'wp_user_level','2'),
(36,3,'locale','de_DE'),
(37,4,'nickname','maria'),
(38,4,'first_name','Maria'),
(39,4,'last_name','Weber'),
(40,4,'wp_capabilities','a:1:{s:10:\"subscriber\";b:1;}'),
(41,4,'wp_user_level','0'),
(42,4,'locale','de_AT'),
(43,5,'nickname','shopmanager'),
(44,5,'first_name','Shop'),
(45,5,'last_name','Manager'),
(46,5,'wp_capabilities','a:1:{s:12:\"shop_manager\";b:1;}'),
(47,5,'wp_user_level','5'),
(48,5,'billing_address_1','Musterstraße 123'),
(49,5,'billing_city','Wien'),
(50,5,'billing_postcode','1010'),
(51,5,'billing_country','AT'),
(52,1,'meta_box_order_dashboard','a:4:{s:6:\"normal\";s:47:\"dashboard_right_now,dashboard_activity\";s:4:\"side\";s:21:\"dashboard_quick_press\";}'),
(53,1,'closedpostboxes_dashboard','a:0:{}'),
(54,2,'managenav-menuscolumnshidden','a:5:{i:0;s:11:\"link-target\";i:1;s:15:\"title-attribute\";i:2;s:3:\"xfn\";}'),
(55,3,'_woocommerce_persistent_cart_1','a:1:{s:4:\"cart\";a:0:{}}');

--
-- Table structure for table `wp_users`
--

DROP TABLE IF EXISTS `wp_users`;
CREATE TABLE `wp_users` (
  `ID` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_login` varchar(60) COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT '',
  `user_pass` varchar(255) COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT '',
  `user_nicename` varchar(50) COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT '',
  `user_email` varchar(100) COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT '',
  `user_url` varchar(100) COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT '',
  `user_registered` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `user_activation_key` varchar(255) COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT '',
  `user_status` int NOT NULL DEFAULT '0',
  `display_name` varchar(250) COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT '',
  PRIMARY KEY (`ID`),
  KEY `user_login_key` (`user_login`),
  KEY `user_nicename` (`user_nicename`),
  KEY `user_email` (`user_email`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

--
-- Dumping data for table `wp_users`
--

INSERT INTO `wp_users` VALUES 
(1,'admin','$P$BHashedPasswordHere12345678901','admin','admin@example.com','https://example.com','2024-01-15 09:00:00','',0,'Max Mustermann'),
(2,'redakteur','$P$BHashedPasswordHere98765432109','redakteur','lisa.mueller@example.com','','2024-01-20 10:00:00','',0,'Lisa Müller'),
(3,'autor','$P$BHashedPasswordHere55566677788','autor','hans.schmidt@example.com','https://hans-schreibt.de','2024-02-01 11:00:00','',0,'Hans Schmidt'),
(4,'subscriber1','$P$BHashedPasswordHere11122233344','subscriber1','maria.weber@gmail.com','','2024-02-15 14:00:00','',0,'Maria Weber'),
(5,'shopmanager','$P$BHashedPasswordHere99988877766','shopmanager','shop@example.com','','2024-03-01 09:00:00','',0,'Shop Manager');

/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;
/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2024-04-20 15:30:00