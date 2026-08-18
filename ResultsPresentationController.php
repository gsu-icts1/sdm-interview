-- =============================================================================
-- Gwanda State University — SDM Pre-Interview, Question 7
-- Database structure: studentmember, studentprogrammestatus, tblregistered_module
-- Candidate submission — cleaned structure with PKs, FKs, data types and indexes
--
-- NOTES / ASSUMPTIONS (also stated in the ERD legend):
--  1. The original extract (pengindb.sql) declares NO foreign keys. The
--     relationships below are implied by the shared studentNumber column and
--     have been made explicit here with FK constraints.
--  2. studentNumber lengths were inconsistent in the extract (varchar(20) in
--     studentmember/studentprogrammestatus vs varchar(15) in
--     tblregistered_module). FK columns must match the referenced column
--     exactly, so all are standardised to VARCHAR(20).
--  3. dateBirth was varchar(255) in the extract; corrected to DATE.
--  4. tblregistered_module.seatingIdFk references an exam-seating table that
--     is not part of this extract; it is left as an indexed column with the
--     FK commented out.
-- =============================================================================

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `tblregistered_module`;
DROP TABLE IF EXISTS `studentprogrammestatus`;
DROP TABLE IF EXISTS `studentmember`;
SET FOREIGN_KEY_CHECKS = 1;

-- ---------------------------------------------------------------------------
-- 1. studentmember — master record of every student (parent entity)
-- ---------------------------------------------------------------------------
CREATE TABLE `studentmember` (
  `id`                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `studentNumber`       VARCHAR(20)  NOT NULL,
  `applicationNumber`   VARCHAR(50)  NOT NULL,
  `programmeCode`       VARCHAR(12)  DEFAULT NULL,
  `nationalId`          VARCHAR(20)  NOT NULL,
  `title`               VARCHAR(10)  NOT NULL,
  `lastName`            VARCHAR(100) NOT NULL,
  `firstName`           VARCHAR(100) NOT NULL,
  `middleName`          VARCHAR(100) DEFAULT NULL,
  `sex`                 VARCHAR(10)  NOT NULL,
  `dateBirth`           DATE         NOT NULL,
  `passportNumber`      VARCHAR(30)  DEFAULT NULL,
  `passportExpiryDate`  DATE         DEFAULT NULL,
  `physicalAddress1`    VARCHAR(255) NOT NULL,
  `physicalAddress2`    VARCHAR(255) DEFAULT NULL,
  `nextOfKinName`       VARCHAR(150) DEFAULT NULL,
  `nextOfKinEmail`      VARCHAR(150) DEFAULT NULL,
  `nextOfKinPhone`      VARCHAR(30)  DEFAULT NULL,
  `sponsor`             VARCHAR(30)  DEFAULT NULL,
  `status`              VARCHAR(20)  DEFAULT NULL,
  `year`                VARCHAR(4)   DEFAULT NULL,
  `specialNeeds`        VARCHAR(50)  DEFAULT NULL,
  `mobileNumber`        VARCHAR(30)  NOT NULL,
  `email`               VARCHAR(150) DEFAULT NULL,
  `nationality`         VARCHAR(60)  DEFAULT NULL,
  `is_offered`          TINYINT      NOT NULL DEFAULT 0,
  `campus`              VARCHAR(20)  DEFAULT NULL,
  `created_at`          TIMESTAMP    NULL DEFAULT NULL,
  `updated_at`          TIMESTAMP    NULL DEFAULT NULL,
  `deleted_at`          TIMESTAMP    NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `studentmember_studentnumber_unique`     (`studentNumber`),
  UNIQUE KEY `studentmember_applicationnumber_unique` (`applicationNumber`),
  UNIQUE KEY `studentmember_nationalid_unique`        (`nationalId`),
  KEY `studentmember_programmecode_index` (`programmeCode`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- 2. studentprogrammestatus — status of a student on a programme per
--    year/semester/session (child of studentmember, 1:N)
-- ---------------------------------------------------------------------------
CREATE TABLE `studentprogrammestatus` (
  `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `studentNumber`    VARCHAR(20)  NOT NULL,
  `programmeCode`    VARCHAR(12)  NOT NULL,
  `yearOfStudy`      VARCHAR(4)   NOT NULL,
  `semesterOfStudy`  VARCHAR(4)   NOT NULL,
  `year`             VARCHAR(4)   NOT NULL,
  `session`          VARCHAR(10)  NOT NULL,
  `status`           VARCHAR(30)  NOT NULL COMMENT 'registered-exempted-defer-graduated',
  `recordStatus`     VARCHAR(20)  NOT NULL DEFAULT 'Unknown' COMMENT 'Old-current-previous',
  `regType`          VARCHAR(20)  NOT NULL DEFAULT 'Manual'  COMMENT 'online-manual',
  `periodCycle`      TINYINT      NOT NULL DEFAULT 0,
  `format`           VARCHAR(20)  NOT NULL COMMENT 'con/para/block',
  `remarks`          VARCHAR(255) DEFAULT NULL COMMENT 'decisions',
  `campus`           VARCHAR(20)  DEFAULT NULL,
  `created_at`       TIMESTAMP    NULL DEFAULT NULL,
  `updated_at`       TIMESTAMP    NULL DEFAULT NULL,
  `deleted_at`       TIMESTAMP    NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `student_status` (`studentNumber`,`programmeCode`,`yearOfStudy`,
                               `semesterOfStudy`,`session`,`recordStatus`,`format`),
  KEY `sps_studentnumber_index` (`studentNumber`),
  KEY `sps_programme_session_index` (`programmeCode`,`session`),
  CONSTRAINT `fk_sps_studentmember`
    FOREIGN KEY (`studentNumber`) REFERENCES `studentmember` (`studentNumber`)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- 3. tblregistered_module — modules a student is registered for in a given
--    session/semester (child of studentmember, 1:N)
-- ---------------------------------------------------------------------------
CREATE TABLE `tblregistered_module` (
  `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `studentNumber`    VARCHAR(20) NOT NULL,          -- widened from 15 to match parent
  `programmeCode`    VARCHAR(12) NOT NULL,
  `moduleCode`       VARCHAR(30) NOT NULL,
  `yearOfStudy`      VARCHAR(4)  NOT NULL,
  `semesterOfStudy`  VARCHAR(4)  NOT NULL,
  `session`          VARCHAR(15) NOT NULL,
  `seatingIdFk`      BIGINT UNSIGNED NOT NULL,
  `is_approved`      TINYINT     NOT NULL DEFAULT 1,
  `is_supplement`    INT         NOT NULL DEFAULT 0,
  `created_at`       TIMESTAMP   NULL DEFAULT NULL,
  `updated_at`       TIMESTAMP   NULL DEFAULT NULL,
  `deleted_at`       TIMESTAMP   NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `trm_unique_registration` (`studentNumber`,`moduleCode`,`session`,`is_supplement`),
  KEY `trm_studentnumber_index` (`studentNumber`),
  KEY `trm_module_session_index` (`moduleCode`,`session`),
  KEY `trm_seatingid_index` (`seatingIdFk`),
  CONSTRAINT `fk_trm_studentmember`
    FOREIGN KEY (`studentNumber`) REFERENCES `studentmember` (`studentNumber`)
    ON UPDATE CASCADE ON DELETE RESTRICT
  -- , CONSTRAINT `fk_trm_seating` FOREIGN KEY (`seatingIdFk`)
  --     REFERENCES `tblexam_seating` (`id`)   -- table not in this extract
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
