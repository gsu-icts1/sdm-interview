-- =============================================================================
-- Gwanda State University - SDM Pre-Interview (Practical, Question 7)
-- Miniature database extract - STRUCTURE ONLY
--
-- Below are the three (3) tables you are required to model in an
-- Entity-Relationship Diagram (ERD):
--     1. studentmember
--     2. studentprogrammestatus
--     3. tblregistered_module
--
-- Read the CREATE TABLE and ALTER TABLE statements to identify columns,
-- data types, primary keys and unique keys. From those, deduce the
-- entities, attributes and relationships and draw the ERD.
--
-- Source: extracted from the running University database (pengindb).
-- =============================================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

-- -----------------------------------------------------------------------------
-- Table structure for table `studentmember`
-- -----------------------------------------------------------------------------

CREATE TABLE `studentmember` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `studentNumber` varchar(20) NOT NULL,
  `applicationNumber` varchar(255) NOT NULL,
  `programmeCode` varchar(255) DEFAULT NULL,
  `nationalId` varchar(20) NOT NULL,
  `title` varchar(255) NOT NULL,
  `lastName` varchar(255) NOT NULL,
  `firstName` varchar(255) NOT NULL,
  `middleName` varchar(255) DEFAULT NULL,
  `sex` varchar(255) NOT NULL,
  `dateBirth` varchar(255) NOT NULL,
  `passportNumber` varchar(255) DEFAULT NULL,
  `passportExpiryDate` varchar(255) DEFAULT NULL,
  `physicalAddress1` varchar(255) NOT NULL,
  `physicalAddress2` varchar(255) DEFAULT NULL,
  `nextOfKinName` varchar(255) DEFAULT NULL,
  `nextOfKinEmail` varchar(255) DEFAULT NULL,
  `nextOfKinPhone` varchar(255) DEFAULT NULL,
  `sponsor` varchar(30) DEFAULT NULL,
  `status` varchar(20) DEFAULT NULL,
  `year` varchar(4) DEFAULT NULL,
  `specialNeeds` varchar(50) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `mobileNumber` varchar(255) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `nationality` varchar(255) DEFAULT NULL,
  `is_offered` tinyint(4) DEFAULT 0,
  `campus` varchar(20) DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Table structure for table `studentprogrammestatus`
-- -----------------------------------------------------------------------------

CREATE TABLE `studentprogrammestatus` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `studentNumber` varchar(20) NOT NULL,
  `programmeCode` varchar(12) NOT NULL,
  `yearOfStudy` varchar(4) NOT NULL,
  `semesterOfStudy` varchar(4) NOT NULL,
  `year` varchar(4) NOT NULL,
  `session` varchar(10) NOT NULL,
  `status` varchar(30) NOT NULL COMMENT 'registered-exempted-defer-graduated',
  `recordStatus` varchar(20) NOT NULL DEFAULT 'Unknown' COMMENT 'Old-current-previous',
  `regType` varchar(255) NOT NULL DEFAULT 'Manual' COMMENT 'online-manual',
  `periodCycle` tinyint(4) NOT NULL DEFAULT 0,
  `format` varchar(255) NOT NULL COMMENT 'con/para/block',
  `remarks` varchar(255) DEFAULT NULL COMMENT 'decisions',
  `campus` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Table structure for table `tblregistered_module`
-- -----------------------------------------------------------------------------

CREATE TABLE `tblregistered_module` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `studentNumber` varchar(15) NOT NULL,
  `programmeCode` varchar(15) NOT NULL,
  `moduleCode` varchar(30) NOT NULL,
  `yearOfStudy` varchar(15) NOT NULL,
  `semesterOfStudy` varchar(15) NOT NULL,
  `session` varchar(15) NOT NULL,
  `seatingIdFk` bigint(20) UNSIGNED NOT NULL,
  `is_approved` tinyint(4) NOT NULL DEFAULT 1,
  `is_supplement` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- Indexes / Primary keys / Unique keys
-- =============================================================================

--
-- Indexes for table `studentmember`
--
ALTER TABLE `studentmember`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `studentmember_studentnumber_unique` (`studentNumber`),
  ADD UNIQUE KEY `studentmember_applicationnumber_unique` (`applicationNumber`),
  ADD UNIQUE KEY `studentmember_nationalid_unique` (`nationalId`);

--
-- Indexes for table `studentprogrammestatus`
--
ALTER TABLE `studentprogrammestatus`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `student_status` (
    `studentNumber`,`programmeCode`,`yearOfStudy`,
    `semesterOfStudy`,`session`,`recordStatus`,`format`
  );

--
-- Indexes for table `tblregistered_module`
--
ALTER TABLE `tblregistered_module`
  ADD PRIMARY KEY (`id`);

-- =============================================================================
-- AUTO_INCREMENT values (as at extract time)
-- =============================================================================

ALTER TABLE `studentmember`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1337;

ALTER TABLE `studentprogrammestatus`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8558;

ALTER TABLE `tblregistered_module`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17303;

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
