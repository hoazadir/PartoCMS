# ************************************************************
# Sequel Ace SQL dump
# Version 20062
#
# https://sequel-ace.com/
# https://github.com/Sequel-Ace/Sequel-Ace
#
# Host: 127.0.0.1 (MySQL 8.3.0)
# Database: maleki-parts-db
# Generation Time: 1403-05-11 21:44:19 +0000
# ************************************************************


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
SET NAMES utf8mb4;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE='NO_AUTO_VALUE_ON_ZERO', SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;


# Dump of table provinces
# ------------------------------------------------------------

CREATE TABLE `provinces` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

LOCK TABLES `provinces` WRITE;
/*!40000 ALTER TABLE `provinces` DISABLE KEYS */;

INSERT INTO `provinces` (`id`, `name`)
VALUES
	(1001,'آذربایجان شرقی'),
	(1002,'آذربایجان غربی'),
	(1003,'اردبیل'),
	(1004,'اصفهان'),
	(1005,'البرز'),
	(1006,'ایلام'),
	(1007,'بوشهر'),
	(1008,'تهران'),
	(1009,'چهارمحال و بختیاری'),
	(1010,'خراسان جنوبی'),
	(1011,'خراسان رضوی'),
	(1012,'خراسان شمالی'),
	(1013,'خوزستان'),
	(1014,'زنجان'),
	(1015,'سمنان'),
	(1016,'سیستان وبلوچستان'),
	(1017,'فارس'),
	(1018,'قزوین'),
	(1019,'قم'),
	(1020,'کردستان'),
	(1021,'کرمان'),
	(1022,'کهگیلویه و بویراحمد'),
	(1023,'گلستان'),
	(1024,'گیلان'),
	(1025,'لرستان'),
	(1026,'مازندران'),
	(1027,'مرکزی'),
	(1028,'هرمزگان'),
	(1029,'همدان'),
	(1030,'کرمانشاه'),
	(1031,'یزد');

/*!40000 ALTER TABLE `provinces` ENABLE KEYS */;
UNLOCK TABLES;



/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;
/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
