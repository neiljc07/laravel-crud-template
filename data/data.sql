-- MariaDB dump 10.17  Distrib 10.4.8-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: e_broker
-- ------------------------------------------------------
-- Server version	10.4.8-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Dumping data for table `brands`
--

LOCK TABLES `brands` WRITE;
/*!40000 ALTER TABLE `brands` DISABLE KEYS */;
INSERT INTO `brands` VALUES (1,'brand','brand',1,1,'2020-03-30 03:20:15','2020-03-30 03:53:19'),(2,'test','test',1,28,'2020-03-30 05:12:24','2020-03-30 05:12:24');
/*!40000 ALTER TABLE `brands` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `failed_jobs`
--

LOCK TABLES `failed_jobs` WRITE;
/*!40000 ALTER TABLE `failed_jobs` DISABLE KEYS */;
/*!40000 ALTER TABLE `failed_jobs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `industries`
--

LOCK TABLES `industries` WRITE;
/*!40000 ALTER TABLE `industries` DISABLE KEYS */;
INSERT INTO `industries` VALUES (37,'qrwqrqwr','wqrqwr',1,'2020-03-27 03:39:00','2020-03-27 03:39:00'),(38,'yyjytj','ghjghjhg',1,'2020-03-27 03:39:59','2020-03-27 03:39:59'),(39,'ghjghj','hgjghj',1,'2020-03-27 03:40:31','2020-03-30 03:51:40'),(40,'ghjhg','jhgjhgj',1,'2020-03-27 03:41:21','2020-03-29 15:02:07');
/*!40000 ALTER TABLE `industries` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `project_attachments`
--

LOCK TABLES `project_attachments` WRITE;
/*!40000 ALTER TABLE `project_attachments` DISABLE KEYS */;
INSERT INTO `project_attachments` VALUES (29,34,'public/projects/attachments/TEG3YV1mMWnJjEUIURffwHUckTjA7iH3G0dAVmIC.txt','2020-03-27 03:47:11','2020-03-27 03:47:11'),(30,34,'public/projects/attachments/b1uCHEOo6zgmmgmDnbGmrw1VtHprInxSd0egCZ7C.pdf','2020-03-27 03:55:08','2020-03-27 03:55:08'),(31,35,'public/projects/attachments/7UzYSnMNNjDpzQpoI9URfeSn4glNbPloWYWIgrC7.pdf','2020-03-27 03:58:32','2020-03-27 03:58:32'),(32,43,'public/projects/attachments/okTBNrG32RW1x5BAXXEHg1RNsy59kCfYJeA6Sttt.png','2020-03-30 02:19:54','2020-03-30 02:19:54'),(33,44,'public/projects/attachments/BjtUF755ysxnRcHvRipwVtoMjA4eVgApzjDiot34.png','2020-03-30 04:14:11','2020-03-30 04:14:11'),(34,45,'public/projects/attachments/5F4Bb15M4RnabzdAcM1kbEKHUjTvJ6rZanhAPpCO.png','2020-03-30 05:14:27','2020-03-30 05:14:27'),(35,46,'public/projects/attachments/rRK89ByYL3DQTYbBVOK2c7kTu7iZq8IefhwKcMNk.png','2020-03-30 05:27:55','2020-03-30 05:27:55'),(36,47,'public/projects/attachments/HTBn6DHjdfnf9aEG52pYBHDQywFCsk8tQQalNj55.png','2020-03-30 05:30:59','2020-03-30 05:30:59'),(37,48,'public/projects/attachments/5rH3NRbnvsKwUD9QBOUU3fOkM6387XnGWMkfs1oz.png','2020-03-30 06:01:51','2020-03-30 06:01:51'),(38,49,'public/projects/attachments/olI5AIQFG5j5yRrobv5hPMZchQiVEzP3hREMKPIX.png','2020-03-30 06:05:38','2020-03-30 06:05:38'),(39,50,'public/projects/attachments/Hm7z5pSARNKgGWEpb3wApeAtokbShX6ByaTM0hfe.png','2020-03-30 06:09:13','2020-03-30 06:09:13'),(40,51,'public/projects/attachments/ytkFyETaMz5jpcaSejS7b1Wyn8XfszsTcnPAJ1Ka.png','2020-03-30 06:11:33','2020-03-30 06:11:33');
/*!40000 ALTER TABLE `project_attachments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `project_bids`
--

LOCK TABLES `project_bids` WRITE;
/*!40000 ALTER TABLE `project_bids` DISABLE KEYS */;
INSERT INTO `project_bids` VALUES (6,45,1,100.0000,1,'2020-03-30 06:42:22','2020-03-30 06:51:09'),(7,45,31,79.0000,0,'2020-03-30 06:51:25','2020-03-30 06:54:26'),(8,45,1,21.0000,0,'2020-03-30 06:54:40','2020-03-30 06:57:04'),(9,46,1,1.0000,0,'2020-03-30 08:37:10','2020-03-30 08:37:10');
/*!40000 ALTER TABLE `project_bids` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `project_industries`
--

LOCK TABLES `project_industries` WRITE;
/*!40000 ALTER TABLE `project_industries` DISABLE KEYS */;
INSERT INTO `project_industries` VALUES (102,34,38,'2020-03-27 03:56:47','2020-03-27 03:56:47'),(105,35,38,'2020-03-27 03:58:43','2020-03-27 03:58:43'),(106,35,39,'2020-03-27 03:58:43','2020-03-27 03:58:43'),(117,43,37,'2020-03-30 02:22:05','2020-03-30 02:22:05'),(118,43,38,'2020-03-30 02:22:05','2020-03-30 02:22:05'),(123,44,38,'2020-03-30 04:21:25','2020-03-30 04:21:25'),(124,44,39,'2020-03-30 04:21:25','2020-03-30 04:21:25'),(135,46,38,'2020-03-30 05:27:55','2020-03-30 05:27:55'),(137,47,39,'2020-03-30 05:31:15','2020-03-30 05:31:15'),(140,45,38,'2020-03-30 05:33:09','2020-03-30 05:33:09'),(141,45,39,'2020-03-30 05:33:09','2020-03-30 05:33:09'),(142,48,39,'2020-03-30 06:01:50','2020-03-30 06:01:50'),(143,49,39,'2020-03-30 06:05:38','2020-03-30 06:05:38'),(144,49,40,'2020-03-30 06:05:38','2020-03-30 06:05:38'),(151,50,39,'2020-03-30 06:10:55','2020-03-30 06:10:55'),(152,50,40,'2020-03-30 06:10:55','2020-03-30 06:10:55'),(155,51,39,'2020-03-30 06:11:40','2020-03-30 06:11:40'),(156,51,40,'2020-03-30 06:11:40','2020-03-30 06:11:40');
/*!40000 ALTER TABLE `project_industries` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `project_status`
--

LOCK TABLES `project_status` WRITE;
/*!40000 ALTER TABLE `project_status` DISABLE KEYS */;
INSERT INTO `project_status` VALUES (1,'PENDING','PENDING',1,'2020-03-13 02:04:01','2020-03-13 02:04:01'),(2,'APPROVED','APPROVED',1,'2020-03-17 00:32:38','2020-03-18 03:01:46'),(3,'POSTED','POSTED',1,'2020-03-27 03:34:37','2020-03-27 03:34:37');
/*!40000 ALTER TABLE `project_status` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `projects`
--

LOCK TABLES `projects` WRITE;
/*!40000 ALTER TABLE `projects` DISABLE KEYS */;
INSERT INTO `projects` VALUES (34,'jkjh','khjk','hkhkjhk','',NULL,NULL,1,1.0000,NULL,'2020-03-27 00:00:00',NULL,NULL,0,1,1,1,NULL,1,NULL,'2020-03-27 03:42:18','2020-03-27 03:56:47',NULL,NULL,NULL,NULL),(35,'asfasf','safasf','asfsafsaf','',NULL,NULL,1,1.0000,1.0000,'2020-03-27 00:00:00','2020-03-27 00:00:00',1,1,29,1,2,1,0,'2020-03-27 11:58:43','2020-03-27 03:58:32','2020-03-27 03:58:43',NULL,NULL,NULL,NULL),(43,'project','qwr','qwrqwr','',NULL,'public/projects/thumbnails/MRnIy8o7z9PHqaMNsq17BTHdb3ZzXInLIV1Qhjjb.png',123,1.0000,NULL,'2020-03-30 00:00:00',NULL,NULL,0,1,1,2,NULL,0,'2020-03-30 10:22:05','2020-03-30 02:19:54','2020-03-30 02:22:05',NULL,NULL,NULL,NULL),(44,'project edited','qwrsdgsdg','qwr','qerewqr',1,'public/projects/thumbnails/pp81bo19riO8xGnrIGGn9sUFM9PHhmtLdfHIHJLt.png',1,1.0000,NULL,'2020-03-30 00:00:00',NULL,NULL,1,1,1,3,1,0,'2020-03-30 13:46:58','2020-03-30 04:14:11','2020-03-30 05:46:58',NULL,NULL,NULL,NULL),(45,'qrqw','rwqr','wqrwqr','qwrwqr',2,'public/projects/thumbnails/Q77xMhC88M9hmWZ0y1Lnfc7nzTZIAOAkVIHAIhxn.png',1,1.0000,100.0000,'2020-03-30 00:00:00','2020-03-30 00:00:00',1,1,28,1,3,1,0,'2020-03-30 13:47:11','2020-03-30 05:14:27','2020-03-30 08:30:49',3,1,'2020-03-30 16:30:49',8),(46,'wqrqwr','rqwr','qwrqwr','qwrqwr',NULL,'public/projects/thumbnails/jnjMVd5XOJjoVq5MIS1oRghuM0ENTYKKslhbPxIQ.png',1,1.0000,1.0000,'2020-03-30 00:00:00','2020-03-30 00:00:00',1,1,28,1,3,1,0,NULL,'2020-03-30 05:27:55','2020-03-30 08:37:31',NULL,1,'2020-03-30 16:37:31',9),(47,'qwr','wqrqwr','wqr','wqrr',2,'public/projects/thumbnails/owWSgxKlVGVcSQyTxn38pTXpyQY1Sj4q3ZtNsFOj.png',1,1.0000,1.0000,'2020-03-30 00:00:00','2020-03-30 00:00:00',1,1,28,1,3,1,0,'2020-03-30 13:47:17','2020-03-30 05:30:59','2020-03-30 05:47:17',NULL,NULL,NULL,NULL),(48,'erew','twet','ewtew','tewt',1,'public/projects/thumbnails/beuOonBwCfG3P9iXg59Qp4jLVutHrsYBpSDYjb1y.png',1,1.0000,NULL,'2020-03-30 00:00:00',NULL,NULL,0,1,1,1,NULL,1,NULL,'2020-03-30 06:01:50','2020-03-30 06:01:50',NULL,NULL,NULL,NULL),(49,'test','test','test','test',NULL,'public/projects/thumbnails/xfobxs9YnB6zwSM5ATatqJ1OazNVfxHLkh0yhQTh.png',1,1.0000,1.0000,'2020-03-30 00:00:00','2020-03-30 00:00:00',1,1,28,1,1,NULL,0,NULL,'2020-03-30 06:05:38','2020-03-30 06:05:38',NULL,NULL,NULL,NULL),(50,'teqwr','teqwr','teqwr','teqwr',NULL,'public/projects/thumbnails/ZFoS3HpX9M8zB70rFyxWZJgNpojn54Kbp7xHYkPi.png',1,1.0000,1.0000,'2020-03-30 00:00:00','2020-03-30 00:00:00',1,1,28,1,3,1,0,NULL,'2020-03-30 06:09:13','2020-03-30 06:10:55',3,NULL,NULL,NULL),(51,'qwtqwt','qwtqwt','qwtqwt','qwtqwt',2,'public/projects/thumbnails/Giw9vIHoGz8rqE9XLqyBUN2BdcjU2VcFMNEruftB.png',1,1.0000,1.0000,'2020-03-30 00:00:00','2020-03-30 00:00:00',1,1,28,1,3,1,0,NULL,'2020-03-30 06:11:33','2020-03-30 06:11:40',2,NULL,NULL,NULL);
/*!40000 ALTER TABLE `projects` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `user_details`
--

LOCK TABLES `user_details` WRITE;
/*!40000 ALTER TABLE `user_details` DISABLE KEYS */;
INSERT INTO `user_details` VALUES (14,'tutyu','tyuyt','uytutyu',28,'2020-03-27 03:42:48','2020-03-27 03:42:48'),(15,'qwrqwr','qwrq','rqwr',29,'2020-03-27 03:43:33','2020-03-27 03:43:33'),(16,'eryery','ryrey','reyery',30,'2020-03-27 03:44:52','2020-03-27 03:44:52'),(17,'QWRQWR','qwrqw','rqwrqwr',31,'2020-03-27 03:45:11','2020-03-27 03:45:11');
/*!40000 ALTER TABLE `user_details` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `user_industries`
--

LOCK TABLES `user_industries` WRITE;
/*!40000 ALTER TABLE `user_industries` DISABLE KEYS */;
INSERT INTO `user_industries` VALUES (23,28,38,'2020-03-27 03:42:48','2020-03-27 03:42:48'),(24,29,38,'2020-03-27 03:43:33','2020-03-27 03:43:33'),(25,29,40,'2020-03-27 03:43:33','2020-03-27 03:43:33'),(26,30,37,'2020-03-27 03:44:52','2020-03-27 03:44:52'),(27,30,38,'2020-03-27 03:44:52','2020-03-27 03:44:52'),(28,31,37,'2020-03-27 03:45:11','2020-03-27 03:45:11'),(29,31,38,'2020-03-27 03:45:11','2020-03-27 03:45:11');
/*!40000 ALTER TABLE `user_industries` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `user_types`
--

LOCK TABLES `user_types` WRITE;
/*!40000 ALTER TABLE `user_types` DISABLE KEYS */;
INSERT INTO `user_types` VALUES (1,'ADMIN','Admin',1,'2020-03-12 16:00:00','2020-03-12 16:00:00'),(2,'BIDDER','BIDDER',1,'2020-03-13 02:06:16','2020-03-17 02:08:02'),(3,'CLIENT','Client',1,'2020-03-17 00:51:57','2020-03-17 02:08:00'),(4,'ghjhg','jghj',1,'2020-03-27 03:41:28','2020-03-29 15:22:01');
/*!40000 ALTER TABLE `user_types` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'Dwight Schrute','cuenzaneil@gmail.com',NULL,'$2y$10$0p4DfwwpfzE.fslyL9bHJuoG0uG2jElJbrG8pDFIxmMD6NSXVSkYy',1,1,'public/profile_pictures/Dwight Schrute.webp',NULL,'2020-03-13 01:49:35','2020-03-18 02:58:57'),(28,'CLIENT 1','hjkjh@mail.com',NULL,'$2y$10$a/vS6uqQk7WujolS963/UelOu.mGIlxL6T7r.1CSe9rQKl2ao9n.G',3,1,NULL,NULL,'2020-03-27 03:42:48','2020-03-27 03:42:48'),(29,'CLIENT 2','qwrqwr@mail.com',NULL,'$2y$10$Wh88.0eTbo4QoUr79g/Em.qZKg4vEHjOAEK3j1lyR3yXmgqnEbU6e',3,1,NULL,NULL,'2020-03-27 03:43:33','2020-03-27 03:43:33'),(30,'CLIENT 3','hgjhgjh@mail.com',NULL,'$2y$10$Vc5NYRllBU78xpxjA3F3TONDcpL/icSL4w03tANhlHT1gnYgVs7NC',3,1,NULL,NULL,'2020-03-27 03:44:52','2020-03-27 03:44:52'),(31,'CLIENT 4','test@mail.com',NULL,'$2y$10$GLgjnL0xjBCcO/DC5g1JvOC/iyJnTYxkIFsPv6YwU6SF61OxwlplC',3,1,NULL,NULL,'2020-03-27 03:45:11','2020-03-27 03:45:11');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2020-03-31 13:41:36
