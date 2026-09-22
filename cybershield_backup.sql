/*M!999999\- enable the sandbox mode */ 
-- MariaDB dump 10.19-11.8.8-MariaDB, for debian-linux-gnu (x86_64)
--
-- Host: localhost    Database: cybershield
-- ------------------------------------------------------
-- Server version	11.8.8-MariaDB-1 from Debian

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*M!100616 SET @OLD_NOTE_VERBOSITY=@@NOTE_VERBOSITY, NOTE_VERBOSITY=0 */;

--
-- Table structure for table `admin_users`
--

DROP TABLE IF EXISTS `admin_users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `admin_users` (
  `admin_id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('super_admin','analyst','viewer') NOT NULL DEFAULT 'analyst',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`admin_id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `admin_users`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `admin_users` WRITE;
/*!40000 ALTER TABLE `admin_users` DISABLE KEYS */;
INSERT INTO `admin_users` VALUES
(2,'admin','admin@cybershield.local','$2y$12$Hsi.k2x3GvBNsaCFWqJilON66.3u7Pt1ESGUMJkvUFdC1y5qBzRPa','super_admin','2026-08-17 23:19:08');
/*!40000 ALTER TABLE `admin_users` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `campaign_targets`
--

DROP TABLE IF EXISTS `campaign_targets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `campaign_targets` (
  `campaign_id` int(11) NOT NULL,
  `target_id` int(11) NOT NULL,
  PRIMARY KEY (`campaign_id`,`target_id`),
  KEY `target_id` (`target_id`),
  CONSTRAINT `campaign_targets_ibfk_1` FOREIGN KEY (`campaign_id`) REFERENCES `campaigns` (`campaign_id`) ON DELETE CASCADE,
  CONSTRAINT `campaign_targets_ibfk_2` FOREIGN KEY (`target_id`) REFERENCES `targets` (`target_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `campaign_targets`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `campaign_targets` WRITE;
/*!40000 ALTER TABLE `campaign_targets` DISABLE KEYS */;
INSERT INTO `campaign_targets` VALUES
(5,1),
(4,2),
(31,2),
(31,3),
(6,5),
(7,6),
(8,7),
(9,7),
(10,7),
(11,7),
(12,7),
(13,7),
(14,7),
(15,7),
(16,7),
(17,7),
(18,7),
(19,7),
(20,7),
(24,7),
(25,7),
(26,7),
(27,7),
(28,7),
(29,7),
(21,8),
(22,9),
(23,10),
(31,10),
(30,18);
/*!40000 ALTER TABLE `campaign_targets` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `campaigns`
--

DROP TABLE IF EXISTS `campaigns`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `campaigns` (
  `campaign_id` int(11) NOT NULL AUTO_INCREMENT,
  `campaign_name` varchar(150) NOT NULL,
  `attack_type` enum('email','quishing','smishing','vishing','attachment','whatsapp','wabot','deepfake_call') NOT NULL,
  `template_id` int(11) DEFAULT NULL,
  `launch_date` datetime DEFAULT NULL,
  `scheduled_at` datetime DEFAULT NULL,
  `auto_dispatch` tinyint(1) DEFAULT 0,
  `status` enum('draft','scheduled','running','completed','cancelled','active') DEFAULT 'draft',
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `lp_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`campaign_id`),
  KEY `template_id` (`template_id`),
  KEY `created_by` (`created_by`),
  KEY `lp_id` (`lp_id`),
  CONSTRAINT `campaigns_ibfk_1` FOREIGN KEY (`template_id`) REFERENCES `templates` (`template_id`) ON DELETE SET NULL,
  CONSTRAINT `campaigns_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `admin_users` (`admin_id`) ON DELETE SET NULL,
  CONSTRAINT `campaigns_ibfk_3` FOREIGN KEY (`lp_id`) REFERENCES `landing_page_templates` (`lp_id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=32 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `campaigns`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `campaigns` WRITE;
/*!40000 ALTER TABLE `campaigns` DISABLE KEYS */;
INSERT INTO `campaigns` VALUES
(1,'Test Campaign 1','email',NULL,'2026-08-17 07:40:18',NULL,0,'cancelled',NULL,'2026-08-17 11:40:18',NULL),
(2,'QR Test','quishing',1,'2026-08-18 00:00:00',NULL,0,'draft',2,'2026-08-19 00:22:39',NULL),
(3,'malecious','attachment',1,'2026-08-19 00:00:00',NULL,0,'draft',2,'2026-08-19 00:35:14',NULL),
(4,'qr testing ','quishing',1,'2026-08-20 00:00:00',NULL,0,'draft',2,'2026-08-20 11:32:43',NULL),
(5,'qr test','quishing',1,'2026-08-24 00:00:00',NULL,0,'draft',2,'2026-08-24 10:45:46',NULL),
(6,'QR Test','quishing',1,'2026-08-24 00:00:00',NULL,0,'running',2,'2026-08-24 10:51:11',NULL),
(7,'qr test','quishing',NULL,'2026-08-26 00:00:00',NULL,0,'cancelled',2,'2026-08-26 10:41:54',NULL),
(8,'amazon','email',NULL,'2026-08-27 00:00:00',NULL,0,'running',2,'2026-08-27 15:21:10',NULL),
(9,'amazon','email',7,'2026-08-28 00:00:00',NULL,0,'running',2,'2026-08-28 16:59:32',NULL),
(10,'amazon','email',NULL,'2026-08-28 00:00:00',NULL,0,'draft',2,'2026-08-28 17:23:26',NULL),
(11,'amazon','email',NULL,'2026-08-28 00:00:00',NULL,0,'draft',2,'2026-08-28 17:24:04',NULL),
(12,'random msg','email',8,NULL,NULL,0,'draft',2,'2026-08-28 17:26:02',NULL),
(13,'random msg','email',8,'2026-08-28 00:00:00',NULL,0,'draft',2,'2026-08-28 17:34:18',NULL),
(14,'random msg','email',8,'2026-08-28 00:00:00',NULL,0,'draft',2,'2026-08-28 17:36:49',NULL),
(15,'amazon','email',7,'2026-09-01 11:27:00',NULL,0,'cancelled',2,'2026-08-29 03:25:07',11),
(16,'random msg','email',8,'2026-08-29 00:00:00',NULL,0,'draft',2,'2026-08-29 04:01:50',12),
(17,'insta','email',9,'2026-08-29 00:00:00',NULL,0,'draft',2,'2026-08-29 09:12:12',9),
(18,'reset','email',10,'2026-08-29 00:00:00',NULL,0,'draft',2,'2026-08-29 09:34:34',9),
(19,'email test','email',11,'2026-08-31 00:00:00',NULL,0,'draft',2,'2026-08-31 10:56:04',13),
(20,'1','attachment',2,NULL,NULL,0,'draft',2,'2026-09-01 16:29:49',11),
(21,'1','email',1,NULL,NULL,0,'draft',2,'2026-09-03 10:45:48',14),
(22,'1','email',9,NULL,NULL,0,'cancelled',2,'2026-09-03 10:51:02',9),
(23,'1','quishing',16,NULL,NULL,0,'active',2,'2026-09-03 11:07:51',NULL),
(24,'1','email',17,NULL,NULL,0,'draft',2,'2026-09-07 13:24:55',11),
(25,'1','email',8,NULL,NULL,0,'draft',2,'2026-09-07 13:37:49',NULL),
(26,'1','email',18,NULL,NULL,0,'draft',2,'2026-09-07 13:39:49',NULL),
(27,'1','email',11,NULL,NULL,0,'draft',2,'2026-09-07 14:14:09',NULL),
(28,'1','email',18,NULL,NULL,0,'draft',2,'2026-09-07 14:16:40',NULL),
(29,'1','email',19,NULL,NULL,0,'draft',2,'2026-09-07 14:18:53',NULL),
(30,'1','email',20,NULL,NULL,0,'scheduled',2,'2026-09-09 02:52:58',13),
(31,'QR Test','quishing',16,NULL,NULL,0,'active',2,'2026-09-13 14:58:59',7);
/*!40000 ALTER TABLE `campaigns` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `landing_page_templates`
--

DROP TABLE IF EXISTS `landing_page_templates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `landing_page_templates` (
  `lp_id` int(11) NOT NULL AUTO_INCREMENT,
  `lp_name` varchar(150) NOT NULL,
  `page_title` varchar(150) DEFAULT NULL,
  `body_html` mediumtext DEFAULT NULL,
  `is_ai_gen` tinyint(1) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`lp_id`)
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `landing_page_templates`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `landing_page_templates` WRITE;
/*!40000 ALTER TABLE `landing_page_templates` DISABLE KEYS */;
INSERT INTO `landing_page_templates` VALUES
(4,'Microsoft 365 Login','Sign in to your account','<style>*{margin:0;padding:0;box-sizing:border-box;font-family:\"Segoe UI\",sans-serif}body{background:#f3f2f1;display:flex;align-items:center;justify-content:center;min-height:100vh}.box{background:#fff;padding:44px;width:440px;box-shadow:0 2px 6px rgba(0,0,0,.2)}.logo{font-size:22px;font-weight:600;color:#0078d4;margin-bottom:20px}h1{font-size:24px;font-weight:600;margin-bottom:16px}p{font-size:13px;color:#605e5c;margin-bottom:20px}input{width:100%;border:1px solid #8a8886;padding:8px 10px;font-size:15px;margin-bottom:16px;outline:none}input:focus{border-color:#0078d4}button{background:#0078d4;color:#fff;border:none;padding:10px;width:100%;font-size:15px;cursor:pointer}button:hover{background:#106ebe}.foot{font-size:12px;color:#0078d4;margin-top:16px;cursor:pointer}</style>\n<div class=\"box\"><div class=\"logo\">&#x1D4DC; Microsoft</div><h1>Sign in</h1><p>to continue to Microsoft 365</p><form method=\"POST\"><input type=\"hidden\" name=\"uid\" value=\"{{uid}}\"><input type=\"email\" name=\"email\" placeholder=\"Email, phone, or Skype\" required><input type=\"password\" name=\"password\" placeholder=\"Password\" required><button type=\"submit\">Sign in</button></form><div class=\"foot\">Forgot password?</div><div style=\"margin-top:12px;font-size:12px\">No account? <span style=\"color:#0078d4;cursor:pointer\">Create one!</span></div></div>',0,'2026-08-27 06:11:25'),
(5,'Google Workspace Login','Sign in - Google Accounts','<style>*{margin:0;padding:0;box-sizing:border-box;font-family:Roboto,sans-serif}body{background:#fff;display:flex;align-items:center;justify-content:center;min-height:100vh}.card{border:1px solid #dadce0;border-radius:8px;padding:48px 40px 36px;width:450px;text-align:center}.glogo{font-size:26px;font-weight:500;margin-bottom:24px}.glogo span{color:#4285f4}h1{font-size:24px;font-weight:400;margin-bottom:8px}p{font-size:16px;color:#202124;margin-bottom:24px}.sub{font-size:14px;color:#5f6368;margin-bottom:24px}input{width:100%;border:1px solid #dadce0;border-radius:4px;padding:13px 15px;font-size:16px;outline:none;margin-bottom:16px}input:focus{border-color:#1a73e8;box-shadow:0 0 0 2px #e8f0fe}button{background:#1a73e8;color:#fff;border:none;border-radius:4px;padding:10px 24px;font-size:14px;cursor:pointer;float:right}button:hover{background:#1557b0}</style>\n<div class=\"card\"><div class=\"glogo\"><span>G</span>oogle</div><h1>Sign in</h1><p class=\"sub\">Use your Google Account</p><form method=\"POST\"><input type=\"hidden\" name=\"uid\" value=\"{{uid}}\"><input type=\"email\" name=\"email\" placeholder=\"Email or phone\" required><input type=\"password\" name=\"password\" placeholder=\"Enter your password\" required><button type=\"submit\">Next</button></form><div style=\"clear:both;margin-top:20px;font-size:13px;color:#1a73e8;cursor:pointer\">Forgot email?</div></div>',0,'2026-08-27 06:11:25'),
(6,'IT Security Alert','Security Alert - Immediate Action Required','<style>*{margin:0;padding:0;box-sizing:border-box;font-family:\"Segoe UI\",sans-serif}body{background:#1a1a2e;display:flex;align-items:center;justify-content:center;min-height:100vh}.box{background:#fff;border-radius:8px;overflow:hidden;width:440px;box-shadow:0 8px 32px rgba(0,0,0,.5)}.header{background:#c0392b;padding:24px;text-align:center;color:#fff}.header h1{font-size:20px;font-weight:600}.header p{font-size:13px;margin-top:6px;opacity:.9}.body{padding:32px}.alert{background:#fdecea;border:1px solid #f5c6cb;border-radius:4px;padding:12px;font-size:13px;color:#721c24;margin-bottom:20px}label{font-size:13px;color:#333;display:block;margin-bottom:6px}input{width:100%;border:1px solid #ccc;border-radius:4px;padding:10px;font-size:14px;margin-bottom:16px;outline:none}input:focus{border-color:#c0392b}button{background:#c0392b;color:#fff;border:none;border-radius:4px;padding:12px;width:100%;font-size:14px;cursor:pointer}button:hover{background:#a93226}</style>\n<div class=\"box\"><div class=\"header\"><h1>🔒 Security Alert</h1><p>Unauthorized access attempt detected on your account</p></div><div class=\"body\"><div class=\"alert\">⚠️ Your account has been flagged. Verify your identity immediately to prevent suspension.</div><form method=\"POST\"><input type=\"hidden\" name=\"uid\" value=\"{{uid}}\"><label>Employee ID / Email</label><input type=\"email\" name=\"email\" required placeholder=\"your.email@company.com\"><label>Current Password</label><input type=\"password\" name=\"password\" required placeholder=\"Enter current password\"><button type=\"submit\">Verify & Secure Account</button></form></div></div>',0,'2026-08-27 06:11:25'),
(7,'HR Payroll Portal','Payroll Portal - Session Expired','<style>*{margin:0;padding:0;box-sizing:border-box;font-family:Arial,sans-serif}body{background:#f0f4f8;display:flex;align-items:center;justify-content:center;min-height:100vh}.wrap{width:420px}.header{background:#2c3e50;color:#fff;padding:16px 24px;border-radius:8px 8px 0 0;display:flex;align-items:center;gap:12px}.header h2{font-size:16px}.card{background:#fff;padding:32px;border-radius:0 0 8px 8px;box-shadow:0 4px 16px rgba(0,0,0,.1)}.banner{background:#fff3cd;border:1px solid #ffc107;border-radius:4px;padding:10px 14px;font-size:13px;color:#856404;margin-bottom:20px}label{font-size:13px;color:#555;display:block;margin-bottom:5px}input{width:100%;border:1px solid #ccc;border-radius:4px;padding:9px 12px;font-size:14px;margin-bottom:14px;outline:none}input:focus{border-color:#2c3e50}button{background:#2c3e50;color:#fff;border:none;border-radius:4px;padding:11px;width:100%;font-size:14px;cursor:pointer}button:hover{background:#1a252f}.foot{font-size:12px;color:#888;margin-top:14px;text-align:center}</style>\n<div class=\"wrap\"><div class=\"header\"><span style=\"font-size:22px\">💼</span><div><h2>HR Payroll Portal</h2><div style=\"font-size:12px;opacity:.8\">Employee Self-Service</div></div></div><div class=\"card\"><div class=\"banner\">⏱️ Your session has expired. Please log in again to view your payslip.</div><form method=\"POST\"><input type=\"hidden\" name=\"uid\" value=\"{{uid}}\"><label>Employee Email</label><input type=\"email\" name=\"email\" required placeholder=\"employee@company.com\"><label>Password</label><input type=\"password\" name=\"password\" required placeholder=\"••••••••\"><button type=\"submit\">Log In to Payroll</button></form><div class=\"foot\">Having trouble? Contact IT Support</div></div></div>',0,'2026-08-27 06:11:25'),
(8,'VPN Remote Access','VPN Portal - Authentication Required','<style>*{margin:0;padding:0;box-sizing:border-box;font-family:\"Courier New\",monospace}body{background:#0d1117;display:flex;align-items:center;justify-content:center;min-height:100vh;color:#58a6ff}.terminal{background:#161b22;border:1px solid #30363d;border-radius:8px;width:460px;overflow:hidden}.t-header{background:#21262d;padding:12px 16px;display:flex;align-items:center;gap:8px}.dot{width:12px;height:12px;border-radius:50%}.t-body{padding:32px}.t-title{font-size:18px;color:#58a6ff;margin-bottom:6px}.t-sub{font-size:12px;color:#8b949e;margin-bottom:24px}label{font-size:12px;color:#8b949e;display:block;margin-bottom:6px}input{width:100%;background:#0d1117;border:1px solid #30363d;border-radius:4px;padding:10px;font-size:14px;color:#e6edf3;margin-bottom:14px;outline:none;font-family:\"Courier New\"}input:focus{border-color:#58a6ff}button{background:#1f6feb;color:#fff;border:none;border-radius:4px;padding:10px;width:100%;font-size:14px;cursor:pointer;font-family:\"Courier New\"}button:hover{background:#388bfd}.status{font-size:11px;color:#3fb950;margin-top:14px}♦</style>\n<div class=\"terminal\"><div class=\"t-header\"><div class=\"dot\" style=\"background:#ff5f57\"></div><div class=\"dot\" style=\"background:#febc2e\"></div><div class=\"dot\" style=\"background:#28c840\"></div><span style=\"margin-left:8px;font-size:12px;color:#8b949e\">vpn-access.company.internal</span></div><div class=\"t-body\"><div class=\"t-title\">🔐 Secure Remote Access</div><div class=\"t-sub\">Corporate VPN Authentication Portal v2.4 | Authorized users only</div><form method=\"POST\"><input type=\"hidden\" name=\"uid\" value=\"{{uid}}\"><label>USERNAME</label><input type=\"text\" name=\"email\" required placeholder=\"domain\\username\"><label>PASSWORD</label><input type=\"password\" name=\"password\" required placeholder=\"••••••••\"><button type=\"submit\">>> AUTHENTICATE</button></form><div class=\"status\">● SECURE CONNECTION ESTABLISHED | TLS 1.3</div></div></div>',0,'2026-08-27 06:11:25'),
(9,'Social Media Login (Instagram Style)','Instagram - Log in','<style>*{margin:0;padding:0;box-sizing:border-box;font-family:-apple-system,BlinkMacSystemFont,\"Segoe UI\",sans-serif}body{background:#fafafa;display:flex;align-items:center;justify-content:center;min-height:100vh}.wrap{width:360px}.card{background:#fff;border:1px solid #dbdbdb;border-radius:3px;padding:40px}.logo{text-align:center;margin-bottom:20px;font-size:32px;font-family:Billabong,cursive;letter-spacing:-1px;color:#262626;font-weight:400}.logo span{background:linear-gradient(45deg,#f09433,#e6683c,#dc2743,#cc2366,#bc1888);-webkit-background-clip:text;-webkit-text-fill-color:transparent;font-size:36px;display:block}.sep{display:flex;align-items:center;margin:16px 0;color:#8e8e8e;font-size:13px}.sep::before,.sep::after{content:\"\";flex:1;height:1px;background:#dbdbdb;margin:0 14px}input{width:100%;background:#fafafa;border:1px solid #dbdbdb;border-radius:3px;padding:9px 8px;font-size:14px;margin-bottom:6px;outline:none}input:focus{border-color:#a8a8a8}button{width:100%;background:linear-gradient(45deg,#f09433,#e6683c,#dc2743,#cc2366);color:#fff;border:none;border-radius:4px;padding:8px;font-size:14px;font-weight:600;cursor:pointer;margin-top:8px}button:hover{opacity:.9}.foot{text-align:center;padding:16px;border:1px solid #dbdbdb;border-radius:3px;margin-top:10px;font-size:14px;color:#262626}</style>\n<div class=\"wrap\"><div class=\"card\"><div class=\"logo\"><span>Instagram</span></div><form method=\"POST\"><input type=\"hidden\" name=\"uid\" value=\"{{uid}}\"><input type=\"text\" name=\"email\" placeholder=\"Phone number, username, or email\" required><input type=\"password\" name=\"password\" placeholder=\"Password\" required><button type=\"submit\">Log in</button></form><div style=\"display:flex;align-items:center;margin:16px 0\"><div style=\"flex:1;height:1px;background:#dbdbdb\"></div><div style=\"padding:0 14px;font-size:13px;color:#8e8e8e\">OR</div><div style=\"flex:1;height:1px;background:#dbdbdb\"></div></div><div style=\"text-align:center;font-size:14px;color:#385185;font-weight:600;cursor:pointer\">Log in with Facebook</div><div style=\"text-align:center;margin-top:14px;font-size:12px;color:#0095f6;cursor:pointer\">Forgot password?</div></div><div class=\"foot\">Don\'t have an account? <span style=\"color:#0095f6;font-weight:600\">Sign up</span></div></div>',0,'2026-08-27 06:12:04'),
(10,'Snapchat Login','Snapchat - Log In','<style>*{margin:0;padding:0;box-sizing:border-box;font-family:\"Graphik\",Helvetica,sans-serif}body{background:#FFFC00;display:flex;align-items:center;justify-content:center;min-height:100vh}.card{background:#fff;border-radius:16px;padding:40px 36px;width:380px;box-shadow:0 4px 20px rgba(0,0,0,.15)}.ghost{text-align:center;font-size:56px;margin-bottom:8px}h1{text-align:center;font-size:22px;font-weight:700;color:#000;margin-bottom:6px}.sub{text-align:center;font-size:14px;color:#666;margin-bottom:24px}input{width:100%;border:2px solid #e0e0e0;border-radius:8px;padding:13px 14px;font-size:15px;margin-bottom:12px;outline:none;transition:border .2s}input:focus{border-color:#FFFC00}button{width:100%;background:#FFFC00;color:#000;border:none;border-radius:24px;padding:13px;font-size:15px;font-weight:700;cursor:pointer;margin-top:6px}button:hover{background:#f0ee00}.links{display:flex;justify-content:space-between;margin-top:16px;font-size:13px;color:#0095f6}</style>\n<div class=\"card\"><div class=\"ghost\">👻</div><h1>Log In</h1><p class=\"sub\">Sign in to your Snapchat account</p><form method=\"POST\"><input type=\"hidden\" name=\"uid\" value=\"{{uid}}\"><input type=\"text\" name=\"email\" placeholder=\"Username or Email\" required><input type=\"password\" name=\"password\" placeholder=\"Password\" required><button type=\"submit\">Log In</button></form><div class=\"links\"><span style=\"cursor:pointer\">Forgot Password?</span><span style=\"cursor:pointer\">Sign Up</span></div></div>',0,'2026-08-27 06:12:04'),
(11,'Amazon Order Verification','Amazon.in - Sign In','<style>*{margin:0;padding:0;box-sizing:border-box;font-family:Arial,sans-serif}body{background:#fff;display:flex;flex-direction:column;align-items:center;padding-top:20px;min-height:100vh}.logo{font-size:28px;font-weight:700;color:#232f3e;margin-bottom:16px}.logo span{color:#FF9900}hr{border:1px solid #ddd;width:400px;margin-bottom:20px}.card{border:1px solid #ddd;border-radius:4px;padding:24px;width:348px}.card h1{font-size:21px;font-weight:400;margin-bottom:20px}label{font-size:13px;font-weight:700;display:block;margin-bottom:4px}input{width:100%;border:1px solid #a6a6a6;border-radius:3px;padding:7px 7px;font-size:14px;margin-bottom:14px;outline:none}input:focus{border:1px solid #e77600;box-shadow:0 0 3px 2px rgba(228,121,17,.5)}button{background:linear-gradient(to bottom,#f7dfa5,#f0c14b);border:1px solid #a88734;border-radius:3px;padding:8px;width:100%;font-size:14px;cursor:pointer}button:hover{background:linear-gradient(to bottom,#f5d47a,#eeb825)}.note{font-size:11px;color:#555;margin-top:14px}.sep{text-align:center;font-size:12px;color:#767676;margin:14px 0;position:relative}.sep::before,.sep::after{content:\"\";position:absolute;top:50%;width:45%;height:1px;background:#ddd}.sep::before{left:0}.sep::after{right:0}</style>\n<div class=\"logo\">amazon<span>.in</span></div><hr><div class=\"card\"><h1>Sign in</h1><form method=\"POST\"><input type=\"hidden\" name=\"uid\" value=\"{{uid}}\"><label>Email or mobile phone number</label><input type=\"email\" name=\"email\" required><label>Password</label><input type=\"password\" name=\"password\" required><button type=\"submit\">Continue</button></form><div class=\"note\">By continuing, you agree to Amazon\'s Conditions of Use and Privacy Notice.</div><div class=\"sep\">New to Amazon?</div><button style=\"background:#e7e9ec;border:1px solid #adb1b8\" onclick=\"return false\">Create your Amazon account</button></div>',0,'2026-08-27 06:12:04'),
(12,'Email Account Update','Email Security - Account Verification Required','<style>*{margin:0;padding:0;box-sizing:border-box;font-family:\"Segoe UI\",Arial,sans-serif}body{background:#f5f5f5;display:flex;align-items:center;justify-content:center;min-height:100vh}.wrap{width:480px}.top{background:#0072c6;color:#fff;padding:20px 30px;border-radius:8px 8px 0 0;display:flex;align-items:center;gap:14px}.top-icon{font-size:28px}.top h2{font-size:18px;font-weight:600}.top p{font-size:12px;opacity:.9;margin-top:3px}.card{background:#fff;padding:30px;border:1px solid #ddd;border-top:none;border-radius:0 0 8px 8px}.alert{background:#fff4e5;border-left:4px solid #ff8c00;padding:12px 16px;font-size:13px;color:#7a4100;margin-bottom:20px;border-radius:0 4px 4px 0}label{font-size:13px;color:#333;font-weight:600;display:block;margin-bottom:5px}input{width:100%;border:1px solid #ccc;border-radius:4px;padding:10px 12px;font-size:14px;margin-bottom:14px;outline:none}input:focus{border-color:#0072c6}button{background:#0072c6;color:#fff;border:none;border-radius:4px;padding:11px;width:100%;font-size:14px;cursor:pointer}button:hover{background:#005a9e}.foot{font-size:11px;color:#888;margin-top:16px;text-align:center}</style>\n<div class=\"wrap\"><div class=\"top\"><div class=\"top-icon\">✉️</div><div><h2>Email Account Verification</h2><p>Security & Privacy Team</p></div></div><div class=\"card\"><div class=\"alert\">⚠️ <strong>Action Required:</strong> Unusual sign-in activity detected. Verify your account within 24 hours to avoid suspension.</div><form method=\"POST\"><input type=\"hidden\" name=\"uid\" value=\"{{uid}}\"><label>Email Address</label><input type=\"email\" name=\"email\" required placeholder=\"Enter your email address\"><label>Current Password</label><input type=\"password\" name=\"password\" required placeholder=\"Enter your password\"><button type=\"submit\">Verify My Account</button></form><div class=\"foot\">This is an automated security notification. Do not share your credentials with anyone.</div></div></div>',0,'2026-08-27 06:12:04'),
(13,'Style Import - kurise.karnavatiuniversity.edu.in','	KARNAVATI','<style>\r\n*{margin:0;padding:0;box-sizing:border-box;font-family:\"Segoe UI\",Arial,sans-serif}\r\nbody{background:#f5f5f5;display:flex;align-items:center;justify-content:center;min-height:100vh}\r\n.card{background:#fff;border-radius:8px;padding:40px;width:420px;box-shadow:0 4px 20px rgba(0,0,0,.15)}\r\n.logo{text-align:center;font-size:22px;font-weight:700;color:#1a73e8;margin-bottom:8px;padding-bottom:16px;border-bottom:2px solid #1a73e8}\r\n.sub{text-align:center;font-size:14px;color:#666;margin-bottom:24px;margin-top:8px}\r\nlabel{font-size:13px;color:#333;display:block;margin-bottom:5px;font-weight:600}\r\ninput{width:100%;border:1px solid #ddd;border-radius:4px;padding:11px 12px;font-size:14px;margin-bottom:14px;outline:none;transition:border .2s}\r\ninput:focus{border-color:#1a73e8}\r\nbutton{background:#1a73e8;color:#ffffff;border:none;border-radius:4px;padding:12px;width:100%;font-size:15px;font-weight:600;cursor:pointer}\r\nbutton:hover{opacity:.9}\r\n.foot{text-align:center;font-size:12px;color:#888;margin-top:16px}\r\n</style>\r\n<div class=\"card\">\r\n  <div class=\"logo\"><img src=\'https://kurise.karnavatiuniversity.edu.in/SigninForm.aspx/images/fevicon.ico\' style=\'height:28px;margin-right:10px;vertical-align:middle\' onerror=\'this.style.display=\\\"none\\\"\'>kurise.karnavatiuniversity.edu.in</div>\r\n  <p class=\"sub\">Sign in to your account</p>\r\n  <form method=\"POST\">\r\n    <input type=\"hidden\" name=\"uid\" value=\"{{uid}}\">\r\n    <label>Email / Username</label>\r\n    <input type=\"email\" name=\"email\" required placeholder=\"Enter your email\">\r\n    <label>Password</label>\r\n    <input type=\"password\" name=\"password\" required placeholder=\"Enter your password\">\r\n    <button type=\"submit\">Sign In</button>\r\n  </form>\r\n  <div class=\"foot\">Having trouble? Contact support</div>\r\n</div>',0,'2026-08-31 10:52:59'),
(14,'diigitruce','digitruce','your id \r\npassworld',0,'2026-09-03 10:44:58'),
(15,'Style Import - digitruce.com','DigiTruce | Cybersecurity &amp; Digital Defense Solutions','<style>\r\n*{margin:0;padding:0;box-sizing:border-box;font-family:\"Segoe UI\",Arial,sans-serif}\r\nbody{background:#f5f5f5;display:flex;align-items:center;justify-content:center;min-height:100vh}\r\n.card{background:#fff;border-radius:8px;padding:40px;width:420px;box-shadow:0 4px 20px rgba(0,0,0,.15)}\r\n.logo{text-align:center;font-size:22px;font-weight:700;color:#1a73e8;margin-bottom:8px;padding-bottom:16px;border-bottom:2px solid #1a73e8}\r\n.sub{text-align:center;font-size:14px;color:#666;margin-bottom:24px;margin-top:8px}\r\nlabel{font-size:13px;color:#333;display:block;margin-bottom:5px;font-weight:600}\r\ninput{width:100%;border:1px solid #ddd;border-radius:4px;padding:11px 12px;font-size:14px;margin-bottom:14px;outline:none;transition:border .2s}\r\ninput:focus{border-color:#1a73e8}\r\nbutton{background:#1a73e8;color:#ffffff;border:none;border-radius:4px;padding:12px;width:100%;font-size:15px;font-weight:600;cursor:pointer}\r\nbutton:hover{opacity:.9}\r\n.foot{text-align:center;font-size:12px;color:#888;margin-top:16px}\r\n</style>\r\n<div class=\"card\">\r\n  <div class=\"logo\"><img src=\'https://digitruce.com/contact/assets/webtitllelogo-B61EtMow.png\' style=\'height:28px;margin-right:10px;vertical-align:middle\' onerror=\'this.style.display=\\\"none\\\"\'>digitruce.com</div>\r\n  <p class=\"sub\">Sign in to your account</p>\r\n  <form method=\"POST\">\r\n    <input type=\"hidden\" name=\"uid\" value=\"{{uid}}\">\r\n    <label>Email / Username</label>\r\n    <input type=\"email\" name=\"email\" required placeholder=\"Enter your email\">\r\n    <label>Password</label>\r\n    <input type=\"password\" name=\"password\" required placeholder=\"Enter your password\">\r\n    <button type=\"submit\">Sign In</button>\r\n  </form>\r\n  <div class=\"foot\">Having trouble? Contact support</div>\r\n</div>',0,'2026-09-03 11:15:19'),
(16,'kurise','kurise','<!DOCTYPE html><html><head><meta charset=\'UTF-8\'>\n<style>\nbody{font-family:Arial,sans-serif;background:#f5f5f5;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0}\n.card{background:#fff;padding:40px;border-radius:8px;box-shadow:0 2px 20px rgba(0,0,0,0.1);width:100%;max-width:400px}\nh1{color:#0078d4;margin-bottom:24px;font-size:24px}\ninput{width:100%;padding:10px 14px;border:1px solid #ddd;border-radius:4px;margin-bottom:16px;font-size:14px;box-sizing:border-box}\nbutton{width:100%;padding:12px;background:#0078d4;color:#fff;border:none;border-radius:4px;font-size:15px;font-weight:bold;cursor:pointer}\n</style></head>\n<body><div class=\'card\'><h1>Sign In</h1>\n<form method=\'POST\'>\n<input type=\'email\' name=\'email\' placeholder=\'Email address\' required>\n<input type=\'password\' name=\'password\' placeholder=\'Password\' required>\n<button type=\'submit\'>Sign In</button>\n</form></div></body></html>',0,'2026-09-10 03:38:37'),
(17,'kurise','kurise','<!DOCTYPE html><html><head><meta charset=\'UTF-8\'>\n<style>\nbody{font-family:Arial,sans-serif;background:#f5f5f5;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0}\n.card{background:#fff;padding:40px;border-radius:8px;box-shadow:0 2px 20px rgba(0,0,0,0.1);width:100%;max-width:400px}\nh1{color:#0078d4;margin-bottom:24px;font-size:24px}\ninput{width:100%;padding:10px 14px;border:1px solid #ddd;border-radius:4px;margin-bottom:16px;font-size:14px;box-sizing:border-box}\nbutton{width:100%;padding:12px;background:#0078d4;color:#fff;border:none;border-radius:4px;font-size:15px;font-weight:bold;cursor:pointer}\n</style></head>\n<body><div class=\'card\'><h1>Sign In</h1>\n<form method=\'POST\'>\n<input type=\'email\' name=\'email\' placeholder=\'Email address\' required>\n<input type=\'password\' name=\'password\' placeholder=\'Password\' required>\n<button type=\'submit\'>Sign In</button>\n</form></div></body></html>',0,'2026-09-10 03:39:46'),
(18,'AI - bankinglogin page','bankinglogin page Login','<!DOCTYPE html>\n<html><head><meta charset=\'UTF-8\'><meta name=\'viewport\' content=\'width=device-width,initial-scale=1\'>\n<title>Employee Login</title>\n<style>\n*{margin:0;padding:0;box-sizing:border-box}\nbody{font-family:Arial,sans-serif;background:linear-gradient(135deg,#1a1a2e,#16213e);display:flex;align-items:center;justify-content:center;min-height:100vh}\n.card{background:#fff;padding:44px;border-radius:12px;width:100%;max-width:420px;box-shadow:0 20px 60px rgba(0,0,0,0.3)}\n.logo{text-align:center;font-size:28px;margin-bottom:8px}\nh1{text-align:center;font-size:22px;color:#1a1a2e;margin-bottom:4px}\np{text-align:center;font-size:13px;color:#666;margin-bottom:28px}\nlabel{display:block;font-size:12px;color:#555;margin-bottom:6px;font-weight:600}\ninput{width:100%;padding:12px 14px;border:1px solid #ddd;border-radius:6px;font-size:14px;outline:none;margin-bottom:18px}\ninput:focus{border-color:#1a1a2e;box-shadow:0 0 0 3px rgba(26,26,46,0.1)}\n.btn{width:100%;padding:12px;background:#1a1a2e;color:#fff;border:none;border-radius:6px;font-size:15px;font-weight:600;cursor:pointer}\n.btn:hover{background:#0f172a}\n.footer{text-align:center;margin-top:16px;font-size:12px;color:#999}\n</style></head>\n<body>\n<div class=\'card\'>\n  <div class=\'logo\'>🏢</div>\n  <h1>Employee Portal</h1>\n  <p>Sign in to your corporate account</p>\n  <form method=\'POST\'>\n    <input type=\'hidden\' name=\'uid\' value=\'{{uid}}\'>\n    <label>Email Address</label>\n    <input type=\'email\' name=\'email\' placeholder=\'your@company.com\' required>\n    <label>Password</label>\n    <input type=\'password\' name=\'password\' placeholder=\'Enter your password\' required>\n    <button type=\'submit\' class=\'btn\'>Sign In</button>\n  </form>\n  <div class=\'footer\'>© 2026 Company Portal. All rights reserved.</div>\n</div>\n</body></html>',1,'2026-09-10 03:39:55');
/*!40000 ALTER TABLE `landing_page_templates` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `lms_training`
--

DROP TABLE IF EXISTS `lms_training`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `lms_training` (
  `training_id` int(11) NOT NULL AUTO_INCREMENT,
  `target_id` int(11) NOT NULL,
  `campaign_id` int(11) NOT NULL,
  `status` enum('not_started','in_progress','completed') DEFAULT 'not_started',
  `quiz_score` int(11) DEFAULT 0,
  `completed_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`training_id`),
  KEY `campaign_id` (`campaign_id`),
  KEY `idx_lms_target` (`target_id`),
  CONSTRAINT `lms_training_ibfk_1` FOREIGN KEY (`target_id`) REFERENCES `targets` (`target_id`) ON DELETE CASCADE,
  CONSTRAINT `lms_training_ibfk_2` FOREIGN KEY (`campaign_id`) REFERENCES `campaigns` (`campaign_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `lms_training`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `lms_training` WRITE;
/*!40000 ALTER TABLE `lms_training` DISABLE KEYS */;
INSERT INTO `lms_training` VALUES
(1,1,1,'completed',33,'2026-08-26 10:57:23'),
(2,2,1,'completed',100,'2026-08-24 10:56:39'),
(3,3,1,'not_started',0,NULL),
(4,7,18,'not_started',0,NULL),
(5,7,19,'not_started',0,NULL);
/*!40000 ALTER TABLE `lms_training` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `simulation_logs`
--

DROP TABLE IF EXISTS `simulation_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `simulation_logs` (
  `log_id` int(11) NOT NULL AUTO_INCREMENT,
  `campaign_id` int(11) NOT NULL,
  `target_id` int(11) NOT NULL,
  `tracking_token` varchar(64) NOT NULL,
  `is_email_sent` tinyint(1) DEFAULT 0,
  `is_opened` tinyint(1) DEFAULT 0,
  `is_clicked` tinyint(1) DEFAULT 0,
  `is_credential_submitted` tinyint(1) DEFAULT 0,
  `is_attachment_download` tinyint(1) DEFAULT 0,
  `is_attachment_opened` tinyint(1) DEFAULT 0,
  `is_qr_scanned` tinyint(1) DEFAULT 0,
  `is_report_phishing` tinyint(1) DEFAULT 0,
  `event_timestamp` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`log_id`),
  UNIQUE KEY `tracking_token` (`tracking_token`),
  KEY `idx_logs_campaign` (`campaign_id`),
  KEY `idx_logs_target` (`target_id`),
  CONSTRAINT `simulation_logs_ibfk_1` FOREIGN KEY (`campaign_id`) REFERENCES `campaigns` (`campaign_id`) ON DELETE CASCADE,
  CONSTRAINT `simulation_logs_ibfk_2` FOREIGN KEY (`target_id`) REFERENCES `targets` (`target_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=62 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `simulation_logs`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `simulation_logs` WRITE;
/*!40000 ALTER TABLE `simulation_logs` DISABLE KEYS */;
INSERT INTO `simulation_logs` VALUES
(2,1,1,'cd00c141-5f1e-435b-aa45-6646df0b35e3',1,1,1,1,0,0,0,0,'2026-08-17 11:40:56'),
(3,1,2,'6240e295-6050-4151-87ad-83a4855af724',1,0,0,1,1,0,1,0,'2026-08-17 11:40:56'),
(4,1,3,'933b1728-ea19-4ad3-b596-039fe059c0bd',1,1,1,1,0,0,0,1,'2026-08-17 11:40:56'),
(5,2,1,'c7e31ec6-30ab-4f9b-9626-5ae963296ee8',1,0,0,0,1,1,0,0,'2026-08-19 00:27:08'),
(6,2,2,'2ebd13b2-fb8e-4330-9c9e-7c87cf0e3cc7',1,0,0,0,0,0,0,0,'2026-08-19 00:27:08'),
(7,2,3,'43241385-e493-477c-8552-45b7e711b0d5',1,0,0,0,0,0,0,0,'2026-08-19 00:27:08'),
(8,4,2,'2e8a07c8-3fb8-4cf7-8c7c-2a1c6f325852',1,0,0,0,0,0,0,0,'2026-08-20 11:33:09'),
(9,5,1,'0a1aff65-cc36-4c81-bc4e-22e54d7aa0fa',1,0,0,0,0,0,0,0,'2026-08-24 10:46:18'),
(10,5,1,'f87472cb-b06d-4947-8e07-ab85d2eebd96',1,0,0,0,0,0,0,0,'2026-08-24 10:47:30'),
(11,6,5,'f3647a07-2e39-4daf-96a0-9f214ab43048',1,0,0,0,0,0,0,0,'2026-08-24 10:51:50'),
(12,7,6,'541fde9b-d65a-4e29-8104-825da365ac4b',1,0,0,0,0,0,0,0,'2026-08-26 10:42:54'),
(13,7,6,'8727a05a-8463-470e-9973-536a476bf77e',1,0,0,0,0,0,0,0,'2026-08-26 10:44:59'),
(20,15,7,'f50988e1-6068-4207-8bf5-1e7df134c3b8',1,0,0,0,0,0,0,0,'2026-08-29 03:34:40'),
(21,14,7,'9063e16b-f25a-4d78-a0fa-b8fe3b29e297',1,0,0,0,0,0,0,0,'2026-08-29 03:41:04'),
(22,16,7,'0a01973d-c87d-40f2-bbde-14586d62b778',1,0,0,0,0,0,0,0,'2026-08-29 04:01:59'),
(23,16,7,'ddc1841d-4735-443c-b70a-d84db33f114e',1,0,0,0,0,0,0,0,'2026-08-29 08:57:04'),
(24,15,7,'200e5964-76d8-4f0d-a12d-9f22391f2fe0',1,0,0,0,0,0,0,0,'2026-08-29 09:06:06'),
(25,15,7,'bb6a0644-f022-476e-b67b-30f5de12daad',1,0,0,0,0,0,0,0,'2026-08-29 09:10:23'),
(26,17,7,'af769b80-7903-44ff-a9e6-98b5ba56b07d',1,0,0,0,0,0,0,0,'2026-08-29 09:12:20'),
(27,17,7,'fa1ebde5-43eb-4993-bbe3-1466dc61a14f',1,0,0,0,0,0,0,0,'2026-08-29 09:16:25'),
(28,13,7,'ae41f565-53bd-4018-b52e-a352c8bd3d97',1,0,0,0,0,0,0,0,'2026-08-29 09:20:07'),
(29,18,7,'3695945d-8389-4851-a499-017f26c8536e',1,0,1,0,0,0,0,1,'2026-08-29 09:34:42'),
(30,18,7,'3f652a8e-2b5a-4440-ab40-c8c76345e731',1,0,0,0,0,0,0,0,'2026-08-29 09:44:20'),
(31,19,7,'e3ea1c2c-068b-4405-b564-4d46cc301752',1,0,1,0,0,0,0,0,'2026-08-31 10:56:18'),
(32,19,7,'4f982273-2fb3-4ec9-88c0-66798e8b422b',1,0,0,0,0,0,0,0,'2026-08-31 16:00:29'),
(33,15,7,'62ece8b0-246a-4272-bfba-732e7831e23c',1,0,0,0,0,0,0,0,'2026-09-01 15:28:34'),
(34,15,7,'a12458e0-1e4d-4e9d-97fa-7d41af2f32db',1,0,0,0,0,0,0,0,'2026-09-01 15:32:23'),
(35,19,7,'e80522f6-f495-40d8-8fb2-871cefdf1861',1,0,0,0,0,0,0,0,'2026-09-01 16:19:49'),
(36,17,7,'1e6b7a03-44db-4854-82c2-87142398b50b',1,0,0,0,0,0,0,0,'2026-09-01 16:20:59'),
(37,20,7,'f1a6e7e0-76a9-4f3e-ad41-0bf80eb1937b',1,0,0,0,0,0,0,0,'2026-09-01 16:30:00'),
(38,20,7,'18585697-dc7c-4c4c-9387-311a316dbdc0',1,0,0,0,0,0,0,0,'2026-09-02 15:34:19'),
(39,19,7,'af15b20b-b1a6-4722-a58e-a19014bbb1be',1,0,0,0,0,0,0,0,'2026-09-02 15:34:57'),
(40,20,7,'35df5170-4281-4970-b101-f2d616eaeecc',1,0,0,0,0,0,0,0,'2026-09-02 15:41:59'),
(41,20,7,'9d003b26-8b08-4263-85c7-1d15654ca5e5',1,0,0,0,0,0,0,0,'2026-09-02 16:00:53'),
(42,19,7,'5bebd0b7-bbec-4f11-a16c-7e0afbdd80b7',1,0,0,0,0,0,0,0,'2026-09-02 16:17:55'),
(43,21,8,'7821550e-3553-48b5-9957-8d45d19fb66c',1,0,0,0,0,0,0,0,'2026-09-03 10:46:02'),
(44,22,9,'2659ab0b-ca89-4d5b-82bf-767fe6ea4680',1,0,0,0,0,0,0,0,'2026-09-03 10:51:10'),
(45,24,7,'ea13c1a0-8d49-4772-911b-d9a26e729788',1,0,0,0,0,0,0,0,'2026-09-07 13:25:10'),
(46,25,7,'4c0fec57-8b57-4828-a3c8-bf2c4996abf9',1,0,0,0,0,0,0,0,'2026-09-07 13:37:59'),
(47,26,7,'34ca5e22-52b6-4b35-aafd-0ed5d0652847',1,0,0,0,0,0,0,0,'2026-09-07 13:40:00'),
(48,27,7,'e4e3b089-4d89-48d9-b925-51caef588e00',1,0,0,0,0,0,0,0,'2026-09-07 14:14:16'),
(49,28,7,'7bca8c32-a553-4045-acf3-dc0203711ef3',1,0,0,0,0,0,0,0,'2026-09-07 14:16:46'),
(50,29,7,'8a321028-bb3e-4e45-8b9d-44ce04cd514d',1,0,0,0,0,0,0,0,'2026-09-07 14:18:59'),
(51,30,18,'0719fa51-33ef-4408-8de6-aeb5606ef17f',1,0,0,0,0,0,0,0,'2026-09-09 02:53:08'),
(52,31,2,'20b87ecb-5ae5-48d1-967f-13f1a5b947ac',1,0,0,0,0,0,0,0,'2026-09-13 15:28:22'),
(53,31,3,'84a91841-c210-4cf9-ac91-9dcfed53ec91',1,0,0,0,0,0,0,0,'2026-09-13 15:28:22'),
(54,31,10,'2b5e4b09-f372-459e-980b-70c7c46bf419',1,0,0,0,0,0,0,0,'2026-09-13 15:28:22'),
(55,31,2,'f64cf88a-b737-46f0-b67d-7aaca89f2db9',1,0,0,0,0,0,0,0,'2026-09-13 15:31:42'),
(56,31,3,'dc9da456-08be-4ee7-9a30-1d5bcb32bd51',1,0,0,0,0,0,0,0,'2026-09-13 15:31:42'),
(57,31,10,'cb9cbe5c-7331-4670-ac4f-2632cda8a4a0',1,0,0,0,0,0,0,0,'2026-09-13 15:31:42'),
(58,31,2,'bd489a1d-3490-4560-8fc5-1baf1f724835',1,0,0,0,0,0,0,0,'2026-09-13 15:32:08'),
(59,31,3,'c69950b8-4928-4b63-8504-b9f5bf5a0977',1,0,0,0,0,0,0,0,'2026-09-13 15:32:08'),
(60,31,10,'c86242ee-da52-4978-ba2c-5151d6eebe39',1,0,0,0,0,0,0,0,'2026-09-13 15:32:08'),
(61,23,10,'f3d27ab2-4e74-48b1-920e-bf9f4717b5bd',1,0,0,0,0,0,0,0,'2026-09-13 15:37:23');
/*!40000 ALTER TABLE `simulation_logs` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `targets`
--

DROP TABLE IF EXISTS `targets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `targets` (
  `target_id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `department` varchar(100) DEFAULT NULL,
  `phone_number` varchar(20) DEFAULT NULL,
  `current_risk_score` int(11) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`target_id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `targets`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `targets` WRITE;
/*!40000 ALTER TABLE `targets` DISABLE KEYS */;
INSERT INTO `targets` VALUES
(1,'Riya Malaviya','riya@test.com','HR','222222222',7,'2026-08-17 11:35:10'),
(2,'Prachi','prachi@test.com','Finance','11111111',5,'2026-08-17 11:37:34'),
(3,'Animesh ','animesh@test.com','It','8888888888',4,'2026-08-17 11:37:34'),
(4,'krishna','krishna@test.com','it','5454554545',0,'2026-08-20 11:36:26'),
(5,'Rajvi','rajvi@test.com','HR','5156416546',0,'2026-08-24 10:50:07'),
(6,'xyz','xyx@test.com','it','1545225445',0,'2026-08-26 10:41:13'),
(7,'Riya','riya.918.mr@gmail.com','HR','11894894',0,'2026-08-27 15:18:12'),
(8,'animesh ','ak@digitruce.com','IT','5454554545',0,'2026-09-03 10:43:24'),
(9,'prachi','ku2407u432@karnavatiuniversity.edu.in','IT','1545225445',0,'2026-09-03 10:49:47'),
(10,'Riya Malaviya','riya@company.com','IT','+91987654321',0,'2026-09-06 09:22:35'),
(11,'Prachi Shah','prachi@company.com','Finance','+91912345678',0,'2026-09-06 09:22:35'),
(12,'Animesh Patel','animesh@company.com','HR','+91923456789',0,'2026-09-06 09:22:35'),
(13,'Raj Mehta','raj@company.com','Sales','+91934567890',0,'2026-09-06 09:22:35'),
(14,'Nisha Gupta','nisha@company.com','IT','+91945678901',0,'2026-09-06 09:22:35'),
(15,'Kiran Joshi','kiran@company.com','Finance','+91956789012',0,'2026-09-06 09:22:35'),
(16,'Deepak Sharma','deepak@company.com','Sales','+91967890123',0,'2026-09-06 09:22:35'),
(17,'Pooja Verma','pooja@company.com','HR','+91978901234',0,'2026-09-06 09:22:35'),
(18,'jiya ','jiyachaudhari735@gmail.com','sales','1484688464',0,'2026-09-09 02:48:31');
/*!40000 ALTER TABLE `targets` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `templates`
--

DROP TABLE IF EXISTS `templates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `templates` (
  `template_id` int(11) NOT NULL AUTO_INCREMENT,
  `template_name` varchar(150) NOT NULL,
  `sender_name` varchar(100) DEFAULT NULL,
  `logo_url` varchar(500) DEFAULT NULL,
  `custom_domain` varchar(255) DEFAULT NULL,
  `subject` varchar(255) DEFAULT NULL,
  `body_html` mediumtext DEFAULT NULL,
  `is_ai_gen` tinyint(1) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `attachment_type` varchar(50) DEFAULT NULL,
  `attachment_label` varchar(150) DEFAULT NULL,
  PRIMARY KEY (`template_id`)
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `templates`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `templates` WRITE;
/*!40000 ALTER TABLE `templates` DISABLE KEYS */;
INSERT INTO `templates` VALUES
(1,'AI Generated - urgent password reset notice','IT Support',NULL,NULL,'Action Required: Verify Your Account Immediately','<p>Dear {{name}},</p>\n<p>We noticed unusual activity on your account. Your email has been temporarily deactivated.</p>\n<p>Please verify your identity immediately by clicking the link below:</p>\n<p><a href=\"{{tracking_link}}\" style=\"background:#0078d4;color:#fff;padding:10px 20px;text-decoration:none;border-radius:4px;display:inline-block;\">Verify My Account</a></p>\n<p>If you did not request this, please <a href=\"{{report_link}}\">report this email</a>.</p>\n<p>Regards,<br>Security Team</p>\n{{tracking_pixel}}',1,'2026-08-17 11:52:58',NULL,NULL),
(2,'attachment','it',NULL,NULL,'ransomeware','',0,'2026-08-19 00:49:04',NULL,NULL),
(3,'attachment','it',NULL,NULL,'ransomeware','',0,'2026-08-19 00:59:36',NULL,NULL),
(4,'Copy of AI Generated - urgent password reset notice','IT Support',NULL,NULL,'Action Required: Verify Your Account Immediately','<p>Dear Employee,</p><p>We noticed unusual activity on your account. Please verify your credentials immediately to avoid suspension.</p><p><a href=\'{{tracking_link}}\'>Verify Now</a></p>',1,'2026-08-21 14:35:26',NULL,NULL),
(5,'Copy of AI Generated - urgent password reset notice','IT Support',NULL,NULL,'Action Required: Verify Your Account Immediately','<p>Dear Employee,</p><p>We noticed unusual activity on your account. Please verify your credentials immediately to avoid suspension.</p><p><a href=\'{{tracking_link}}\'>Verify Now</a></p>',1,'2026-08-24 10:50:34',NULL,NULL),
(6,'Copy of Copy of AI Generated - urgent password reset notice','IT Support',NULL,NULL,'Action Required: Verify Your Account Immediately','<p>Dear Employee,</p><p>We noticed unusual activity on your account. Please verify your credentials immediately to avoid suspension.</p><p><a href=\'{{tracking_link}}\'>Verify Now</a></p>',1,'2026-08-26 10:39:45',NULL,NULL),
(7,'amazon','amazon.in',NULL,NULL,'urgent sale for iphone','',0,'2026-08-27 15:20:27',NULL,NULL),
(8,'random msg','cybersheild',NULL,'','to verify account','hello \r\ndear riya \r\nyour email hase been deactivated\r\n\r\n{{tracking_link}}{{tracking_pixel}}\r\n\r\nyou need to login again',0,'2026-08-28 17:25:33','',''),
(9,'instagram','insta.org',NULL,NULL,'for reset password','<div style=\"font-family:Arial,sans-serif;max-width:600px;margin:auto;padding:20px\">\n<h2 style=\"color:#e91e8c\">Account Deactivation Notice</h2>\n<p>Dear {{name}},</p>\n<p>Your account has been temporarily deactivated. To activate it again, please click the button below:</p>\n<br>\n<a href=\"{{tracking_link}}\" style=\"background:#e91e8c;color:#fff;padding:12px 24px;text-decoration:none;border-radius:4px;font-weight:bold;display:inline-block;\">\nActivate My Account\n</a>\n<br><br>\n<p style=\"font-size:12px;color:#999\">If you did not request this, <a href=\"{{report_link}}\">click here to report</a>.</p>\n</div>\n{{tracking_pixel}}',0,'2026-08-29 09:11:29',NULL,NULL),
(10,'reset','krisha',NULL,NULL,'reset','dear riya \r\ni have your id \r\npls change ths passworld \r\n\r\n{{tracking_link}}{{tracking_pixel}}\r\n\r\n\r\n\r\n\r\n\r\n\r\n\r\n\r\n\r\n\r\n\r\n\r\n\r\n\r\n\r\n\r\n\r\n\r\n\r\n{{report_link}}',0,'2026-08-29 09:33:46',NULL,NULL),
(11,'test','priya',NULL,NULL,'test','heypriya,\r\n\r\n\r\nit me riya,\r\n\r\n{{tracking_link}}\r\n\r\n{{tracking_pixel}}\r\n\r\n<a href=\"{{tracking_link}}\" style=\"background:#0078d4;color:#fff;padding:10px 24px;text-decoration:none;border-radius:4px;display:inline-block;font-weight:bold;\">Click Here</a>\r\n\r\n\r\n\r\n{{report_link}}\r\n',0,'2026-08-31 10:55:13',NULL,NULL),
(12,'Ransomware Alert - IT Support','IT Support Team',NULL,NULL,'URGENT: Ransomware Detected on Your Device','<div style=\"font-family:Arial,sans-serif;max-width:600px;margin:auto;padding:20px;background:#fff;\">\n<div style=\"background:#c0392b;padding:16px;border-radius:6px 6px 0 0;text-align:center;\">\n<h2 style=\"color:#fff;margin:0;\">⚠️ SECURITY ALERT</h2></div>\n<div style=\"padding:24px;border:1px solid #ddd;border-top:none;border-radius:0 0 6px 6px;\">\n<p>Dear {{name}},</p>\n<p>Our security system has detected <strong>ransomware activity</strong> on your device. Immediate action is required to prevent data loss.</p>\n<p>Please verify your identity immediately:</p>\n<a href=\"{{tracking_link}}\" style=\"background:#c0392b;color:#fff;padding:12px 28px;text-decoration:none;border-radius:4px;display:inline-block;font-weight:bold;\">Verify Identity Now</a>\n<p style=\"margin-top:20px;font-size:12px;color:#999;\">If you did not trigger this alert, <a href=\"{{report_link}}\">report it here</a>.</p>\n</div></div>\n{{tracking_pixel}}',0,'2026-09-01 15:09:30',NULL,NULL),
(13,'Invoice Payment - Finance','Finance Department',NULL,NULL,'Action Required: Approve Invoice #INV-2026','<div style=\"font-family:Arial,sans-serif;max-width:600px;margin:auto;padding:20px;background:#fff;\">\n<div style=\"background:#2c3e50;padding:16px;border-radius:6px 6px 0 0;\">\n<h2 style=\"color:#fff;margin:0;\">📄 Invoice Approval Required</h2></div>\n<div style=\"padding:24px;border:1px solid #ddd;border-top:none;border-radius:0 0 6px 6px;\">\n<p>Dear {{name}},</p>\n<p>An invoice of <strong>₹45,000</strong> requires your immediate approval before end of business day.</p>\n<p>Please review and approve:</p>\n<a href=\"{{tracking_link}}\" style=\"background:#2c3e50;color:#fff;padding:12px 28px;text-decoration:none;border-radius:4px;display:inline-block;font-weight:bold;\">Review Invoice</a>\n<p style=\"margin-top:20px;font-size:12px;color:#999;\">Not expecting this? <a href=\"{{report_link}}\">Report suspicious email</a>.</p>\n</div></div>\n{{tracking_pixel}}',0,'2026-09-01 15:09:30',NULL,NULL),
(14,'HR Policy Update','HR Department',NULL,NULL,'Important: Updated Leave Policy - Action Required','<div style=\"font-family:Arial,sans-serif;max-width:600px;margin:auto;padding:20px;background:#fff;\">\n<div style=\"background:#27ae60;padding:16px;border-radius:6px 6px 0 0;\">\n<h2 style=\"color:#fff;margin:0;\">📋 HR Policy Update</h2></div>\n<div style=\"padding:24px;border:1px solid #ddd;border-top:none;border-radius:0 0 6px 6px;\">\n<p>Dear {{name}},</p>\n<p>Our leave policy has been updated effective immediately. All employees must acknowledge the new policy by <strong>end of this week</strong>.</p>\n<p>Please login to acknowledge:</p>\n<a href=\"{{tracking_link}}\" style=\"background:#27ae60;color:#fff;padding:12px 28px;text-decoration:none;border-radius:4px;display:inline-block;font-weight:bold;\">Acknowledge Policy</a>\n<p style=\"margin-top:20px;font-size:12px;color:#999;\">Questions? <a href=\"{{report_link}}\">Report if suspicious</a>.</p>\n</div></div>\n{{tracking_pixel}}',0,'2026-09-01 15:09:30',NULL,NULL),
(15,'Microsoft Password Expiry','Microsoft Account Team',NULL,NULL,'Your Microsoft password expires in 24 hours','<div style=\"font-family:Segoe UI,Arial,sans-serif;max-width:600px;margin:auto;padding:20px;background:#fff;\">\n<div style=\"padding:16px;border-bottom:2px solid #0078d4;margin-bottom:20px;\">\n<span style=\"font-size:22px;font-weight:700;color:#0078d4;\">Microsoft</span></div>\n<p>Dear {{name}},</p>\n<p>Your Microsoft account password will expire in <strong>24 hours</strong>. To continue using Microsoft 365 services without interruption, please update your password now.</p>\n<a href=\"{{tracking_link}}\" style=\"background:#0078d4;color:#fff;padding:12px 28px;text-decoration:none;border-radius:4px;display:inline-block;font-weight:bold;\">Update Password</a>\n<p style=\"margin-top:20px;font-size:12px;color:#999;\">If you did not request this, <a href=\"{{report_link}}\">report phishing</a>.</p>\n</div>\n{{tracking_pixel}}',0,'2026-09-01 15:09:30',NULL,NULL),
(16,'Google Account Suspended','Google Security',NULL,NULL,'Your Google Account has been suspended','<div style=\"font-family:Roboto,Arial,sans-serif;max-width:600px;margin:auto;padding:20px;background:#fff;\">\n<div style=\"padding:16px;margin-bottom:20px;text-align:center;\">\n<span style=\"font-size:24px;font-weight:500;color:#4285f4;\">G</span><span style=\"font-size:24px;font-weight:500;color:#ea4335;\">o</span><span style=\"font-size:24px;font-weight:500;color:#fbbc05;\">o</span><span style=\"font-size:24px;font-weight:500;color:#4285f4;\">g</span><span style=\"font-size:24px;font-weight:500;color:#34a853;\">l</span><span style=\"font-size:24px;font-weight:500;color:#ea4335;\">e</span></div>\n<p>Dear {{name}},</p>\n<p>We detected <strong>unusual sign-in activity</strong> on your Google Account. Your account has been temporarily suspended for security reasons.</p>\n<p>Please verify your identity to restore access:</p>\n<a href=\"{{tracking_link}}\" style=\"background:#1a73e8;color:#fff;padding:12px 28px;text-decoration:none;border-radius:4px;display:inline-block;font-weight:bold;\">Verify Account</a>\n<p style=\"margin-top:20px;font-size:12px;color:#999;\">Not you? <a href=\"{{report_link}}\">Report this email</a>.</p>\n</div>\n{{tracking_pixel}}',0,'2026-09-01 15:09:30',NULL,NULL),
(17,'trojen','trojan','','abc@xyz.com','nseiu','dear riya\r\n\r\n{{tracking_link}}\r\n\r\n{{tracking_pixel}}\r\n\r\nclick here <p>Please download and review the attached document:</p><a href=\"{{tracking_link}}\" style=\"background:#e67e22;color:#fff;padding:10px 24px;text-decoration:none;border-radius:4px;display:inline-block;font-weight:bold;\">📎 Download Attachment</a>',0,'2026-09-07 13:23:54','invoice','payment'),
(18,'test msg','test.org','','','test msg','<p>{{tracking_link}}{{tracking_pixel}}</p>\r\n<p>dear riya</p>\r\n<p>click there</p>\r\n<p>i am riya</p>\r\n\r\n',0,'2026-09-07 13:39:23','',''),
(19,'final msg','company.org','','','plain text not allowed','<p>hey riya</p>\n<p>my name is riya also</p>\n<p>i am winner</p>\n<p>no one can stop me</p>\n<p>{{tracking_link}}</p>\n<p>{{tracking_pixel}}</p>\n<p>{{report_link}}</p>\n',0,'2026-09-07 14:18:22','',''),
(20,'kurise','kurise','','','for attendence','<p>dear jiya</p>\n<p>{{tracking_link}}</p>\n<p>{{tracking_pixel}}</p>\n',0,'2026-09-09 02:52:24','job_offer','job offer letter');
/*!40000 ALTER TABLE `templates` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*M!100616 SET NOTE_VERBOSITY=@OLD_NOTE_VERBOSITY */;

-- Dump completed on 2026-09-22  4:19:53
