-- ============================================================
-- HOSPITAL DATABASE — COMPLETE DDL WITH FULL DATA
-- 30 Patients, 10 Doctors, 15 Nurses, 40 Beds, 10 Wards, 10 Specialties
-- ============================================================

USE master;
GO

-- Drop and recreate the database
IF EXISTS (SELECT name FROM sys.databases WHERE name = 'HospitalDB')
    DROP DATABASE HospitalDB;
GO

CREATE DATABASE HospitalDB;
GO

USE HospitalDB;
GO

-- 1. SPECIALTY (10 records)

CREATE TABLE SPECIALTY (
    Specialty_Name  VARCHAR(100)    NOT NULL,
    CONSTRAINT PK_SPECIALTY PRIMARY KEY (Specialty_Name)
);
GO

-- 2. WARD (10 records)

CREATE TABLE WARD (
    Ward_Name       VARCHAR(100)    NOT NULL,
    Specialty_Name  VARCHAR(100)    NOT NULL,
    Number_Of_Care_Units INT        NOT NULL DEFAULT 2,
    CONSTRAINT PK_WARD PRIMARY KEY (Ward_Name),
    CONSTRAINT FK_WARD_SPECIALTY
        FOREIGN KEY (Specialty_Name)
        REFERENCES SPECIALTY (Specialty_Name)
        ON DELETE CASCADE
        ON UPDATE CASCADE
);
GO


-- 3. CARE_UNIT (20 records - 2 per ward)

CREATE TABLE CARE_UNIT (
    Care_Unit_No    VARCHAR(20)     NOT NULL,
    Ward_Name       VARCHAR(100)    NOT NULL,
    CONSTRAINT PK_CARE_UNIT PRIMARY KEY (Care_Unit_No),
    CONSTRAINT FK_CARE_UNIT_WARD
        FOREIGN KEY (Ward_Name)
        REFERENCES WARD (Ward_Name)
        ON DELETE CASCADE
        ON UPDATE CASCADE
);
GO


-- 4. BED (40 records - 4 beds per care unit, 2 care units per ward)

CREATE TABLE BED (
    Bed_No          VARCHAR(20)     NOT NULL,
    Care_Unit_No    VARCHAR(20)     NOT NULL,
    Is_Occupied     BIT             NOT NULL DEFAULT 0,
    CONSTRAINT PK_BED PRIMARY KEY (Bed_No),
    CONSTRAINT FK_BED_CARE_UNIT
        FOREIGN KEY (Care_Unit_No)
        REFERENCES CARE_UNIT (Care_Unit_No)
        ON DELETE CASCADE
        ON UPDATE CASCADE
);
GO


-- 5. DOCTOR (10 records - 5 consultants + 5 team members)

CREATE TABLE DOCTOR (
    Staff_No            VARCHAR(20)     NOT NULL,
    Name                VARCHAR(150)    NOT NULL,
    Position            VARCHAR(50)     NULL,
    Date_Joined_Team    DATE            NULL,
    Specialty_Name      VARCHAR(100)    NOT NULL,
    Consultant_Staff_No VARCHAR(20)     NULL,
    CONSTRAINT PK_DOCTOR PRIMARY KEY (Staff_No),
    CONSTRAINT FK_DOCTOR_SPECIALTY
        FOREIGN KEY (Specialty_Name)
        REFERENCES SPECIALTY (Specialty_Name)
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    CONSTRAINT FK_DOCTOR_CONSULTANT
        FOREIGN KEY (Consultant_Staff_No)
        REFERENCES DOCTOR (Staff_No),
    CONSTRAINT CHK_DOCTOR_POSITION CHECK (
        Position IN ('student(s)', 'junior houseman(jh)', 'senior houseman(sh)', 
                     'assistant registrar(ar)', 'registrar(r)', 'consultant')
    )
);
GO


-- 6. CONSULTANT (5 records - only the consultants)

CREATE TABLE CONSULTANT (
    Staff_No    VARCHAR(20)     NOT NULL,
    CONSTRAINT PK_CONSULTANT PRIMARY KEY (Staff_No),
    CONSTRAINT FK_CONSULTANT_DOCTOR
        FOREIGN KEY (Staff_No)
        REFERENCES DOCTOR (Staff_No)
        ON DELETE CASCADE
        ON UPDATE CASCADE
);
GO


-- 7. NURSE (15 records)

CREATE TABLE NURSE (
    Staff_No        VARCHAR(20)     NOT NULL,
    Name            VARCHAR(150)    NOT NULL,
    Ward_Name       VARCHAR(100)    NOT NULL,
    Care_Unit_No    VARCHAR(20)     NOT NULL,
    CONSTRAINT PK_NURSE PRIMARY KEY (Staff_No),
    CONSTRAINT FK_NURSE_WARD
        FOREIGN KEY (Ward_Name)
        REFERENCES WARD (Ward_Name),
    CONSTRAINT FK_NURSE_CARE_UNIT
        FOREIGN KEY (Care_Unit_No)
        REFERENCES CARE_UNIT (Care_Unit_No)
);
GO


-- 8. DAY_SISTER (5 records)

CREATE TABLE DAY_SISTER (
    Staff_No    VARCHAR(20)     NOT NULL,
    CONSTRAINT PK_DAY_SISTER PRIMARY KEY (Staff_No),
    CONSTRAINT FK_DAY_SISTER_NURSE
        FOREIGN KEY (Staff_No)
        REFERENCES NURSE (Staff_No)
        ON DELETE CASCADE
        ON UPDATE CASCADE
);
GO


-- 9. NIGHT_SISTER (5 records)

CREATE TABLE NIGHT_SISTER (
    Staff_No    VARCHAR(20)     NOT NULL,
    CONSTRAINT PK_NIGHT_SISTER PRIMARY KEY (Staff_No),
    CONSTRAINT FK_NIGHT_SISTER_NURSE
        FOREIGN KEY (Staff_No)
        REFERENCES NURSE (Staff_No)
        ON DELETE CASCADE
        ON UPDATE CASCADE
);
GO

-- 10. STAFF_NURSE (5 records)

CREATE TABLE STAFF_NURSE (
    Staff_No    VARCHAR(20)     NOT NULL,
    CONSTRAINT PK_STAFF_NURSE PRIMARY KEY (Staff_No),
    CONSTRAINT FK_STAFF_NURSE_NURSE
        FOREIGN KEY (Staff_No)
        REFERENCES NURSE (Staff_No)
        ON DELETE CASCADE
        ON UPDATE CASCADE
);
GO


-- 11. NON_REG_NURSE (5 records)

CREATE TABLE NON_REG_NURSE (
    Staff_No    VARCHAR(20)     NOT NULL,
    CONSTRAINT PK_NON_REG_NURSE PRIMARY KEY (Staff_No),
    CONSTRAINT FK_NON_REG_NURSE_NURSE
        FOREIGN KEY (Staff_No)
        REFERENCES NURSE (Staff_No)
        ON DELETE CASCADE
        ON UPDATE CASCADE
);
GO


-- 12. PATIENT (30 records)
CREATE TABLE PATIENT (
    Patient_No      VARCHAR(20)     NOT NULL,
    Patient_Name    VARCHAR(150)    NOT NULL,
    Date_of_Birth   DATE            NULL,
    Date_Admitted   DATE            NULL,
    Care_Unit_No    VARCHAR(20)     NOT NULL,
    Bed_No          VARCHAR(20)     NOT NULL,
    CONSTRAINT PK_PATIENT PRIMARY KEY (Patient_No),
    CONSTRAINT FK_PATIENT_CARE_UNIT
        FOREIGN KEY (Care_Unit_No)
        REFERENCES CARE_UNIT (Care_Unit_No),
    CONSTRAINT FK_PATIENT_BED
        FOREIGN KEY (Bed_No)
        REFERENCES BED (Bed_No)
);
GO


-- 13. COMPLAINT (15 records)

CREATE TABLE COMPLAINT (
    Complaint_Code  VARCHAR(20)     NOT NULL,
    Description     VARCHAR(500)    NULL,
    CONSTRAINT PK_COMPLAINT PRIMARY KEY (Complaint_Code)
);
GO


-- 14. TREATMENT (20 records)

CREATE TABLE TREATMENT (
    Treatment_Code  VARCHAR(20)     NOT NULL,
    Description     VARCHAR(500)    NULL,
    CONSTRAINT PK_TREATMENT PRIMARY KEY (Treatment_Code)
);
GO


-- 15. PATIENT_TREATMENT_RECORD (30 records - 1 per patient)

CREATE TABLE PATIENT_TREATMENT_RECORD (
    Patient_No      VARCHAR(20)     NOT NULL,
    Treatment_Code  VARCHAR(20)     NOT NULL,
    Complaint_Code  VARCHAR(20)     NOT NULL,
    Doctor_Staff_No VARCHAR(20)     NOT NULL,
    Date_Started    DATE            NULL,
    Date_Ended      DATE            NULL,
    CONSTRAINT PK_PTR
        PRIMARY KEY (Patient_No, Treatment_Code, Complaint_Code),
    CONSTRAINT FK_PTR_PATIENT
        FOREIGN KEY (Patient_No)
        REFERENCES PATIENT (Patient_No)
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    CONSTRAINT FK_PTR_TREATMENT
        FOREIGN KEY (Treatment_Code)
        REFERENCES TREATMENT (Treatment_Code)
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    CONSTRAINT FK_PTR_COMPLAINT
        FOREIGN KEY (Complaint_Code)
        REFERENCES COMPLAINT (Complaint_Code)
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    CONSTRAINT FK_PTR_DOCTOR
        FOREIGN KEY (Doctor_Staff_No)
        REFERENCES DOCTOR (Staff_No)
);
GO


-- 16. PREVIOUS_EXPERIENCE (20 records - 2 per doctor)

CREATE TABLE PREVIOUS_EXPERIENCE (
    Exp_ID      VARCHAR(20)     NOT NULL,
    Staff_No    VARCHAR(20)     NOT NULL,
    From_Date   DATE            NULL,
    To_Date     DATE            NULL,
    Establishment VARCHAR(200)  NULL,
    Position    VARCHAR(100)    NULL,
    CONSTRAINT PK_PREVIOUS_EXPERIENCE
        PRIMARY KEY (Exp_ID, Staff_No),
    CONSTRAINT FK_PREV_EXP_DOCTOR
        FOREIGN KEY (Staff_No)
        REFERENCES DOCTOR (Staff_No)
        ON DELETE CASCADE
        ON UPDATE CASCADE
);
GO


-- 17. PROGRESS (20 records - 2 per doctor)

CREATE TABLE PROGRESS (
    Progress_ID         VARCHAR(20)     NOT NULL,
    Staff_No            VARCHAR(20)     NOT NULL,
    Establishment       VARCHAR(200)    NULL,
    Position            VARCHAR(100)    NULL,
    Date                DATE            NULL,
    Performance_Grade   VARCHAR(10)     NULL,
    CONSTRAINT PK_PROGRESS
        PRIMARY KEY (Progress_ID, Staff_No),
    CONSTRAINT FK_PROGRESS_DOCTOR
        FOREIGN KEY (Staff_No)
        REFERENCES DOCTOR (Staff_No)
        ON DELETE CASCADE
        ON UPDATE CASCADE
);
GO

PRINT 'HospitalDB schema created successfully.';
GO


-- DISABLE FOREIGN KEY CONSTRAINTS FOR INSERTION

PRINT '>>> Disabling all FK constraints...';

ALTER TABLE WARD                    NOCHECK CONSTRAINT ALL;
ALTER TABLE CARE_UNIT               NOCHECK CONSTRAINT ALL;
ALTER TABLE BED                     NOCHECK CONSTRAINT ALL;
ALTER TABLE DOCTOR                  NOCHECK CONSTRAINT ALL;
ALTER TABLE CONSULTANT              NOCHECK CONSTRAINT ALL;
ALTER TABLE NURSE                   NOCHECK CONSTRAINT ALL;
ALTER TABLE DAY_SISTER              NOCHECK CONSTRAINT ALL;
ALTER TABLE NIGHT_SISTER            NOCHECK CONSTRAINT ALL;
ALTER TABLE STAFF_NURSE             NOCHECK CONSTRAINT ALL;
ALTER TABLE NON_REG_NURSE           NOCHECK CONSTRAINT ALL;
ALTER TABLE PATIENT                 NOCHECK CONSTRAINT ALL;
ALTER TABLE PATIENT_TREATMENT_RECORD NOCHECK CONSTRAINT ALL;
ALTER TABLE PREVIOUS_EXPERIENCE     NOCHECK CONSTRAINT ALL;
ALTER TABLE PROGRESS                NOCHECK CONSTRAINT ALL;
GO


-- STEP 1: INSERT SPECIALTY (10 records)

PRINT '>>> Inserting SPECIALTY (10 records)...';
INSERT INTO SPECIALTY (Specialty_Name) VALUES
    ('Cardiology'),
    ('Neurology'),
    ('Orthopedics'),
    ('Pediatrics'),
    ('Oncology'),
    ('Dermatology'),
    ('Gastroenterology'),
    ('Nephrology'),
    ('Pulmonology'),
    ('General Surgery');
GO


-- STEP 2: INSERT WARD (10 records)

PRINT '>>> Inserting WARD (10 records)...';
INSERT INTO WARD (Ward_Name, Specialty_Name, Number_Of_Care_Units) VALUES
    ('Ward-Alpha',   'Cardiology',      2),
    ('Ward-Beta',    'Neurology',       2),
    ('Ward-Gamma',   'Orthopedics',     2),
    ('Ward-Delta',   'Pediatrics',      2),
    ('Ward-Epsilon', 'Oncology',        2),
    ('Ward-Zeta',    'Dermatology',     2),
    ('Ward-Eta',     'Gastroenterology',2),
    ('Ward-Theta',   'Nephrology',      2),
    ('Ward-Iota',    'Pulmonology',     2),
    ('Ward-Kappa',   'General Surgery', 2);
GO


-- STEP 3: INSERT CARE_UNIT (20 records)

PRINT '>>> Inserting CARE_UNIT (20 records)...';
INSERT INTO CARE_UNIT (Care_Unit_No, Ward_Name) VALUES
    -- Ward-Alpha (Cardiology)
    ('CU-001', 'Ward-Alpha'),
    ('CU-002', 'Ward-Alpha'),
    -- Ward-Beta (Neurology)
    ('CU-003', 'Ward-Beta'),
    ('CU-004', 'Ward-Beta'),
    -- Ward-Gamma (Orthopedics)
    ('CU-005', 'Ward-Gamma'),
    ('CU-006', 'Ward-Gamma'),
    -- Ward-Delta (Pediatrics)
    ('CU-007', 'Ward-Delta'),
    ('CU-008', 'Ward-Delta'),
    -- Ward-Epsilon (Oncology)
    ('CU-009', 'Ward-Epsilon'),
    ('CU-010', 'Ward-Epsilon'),
    -- Ward-Zeta (Dermatology)
    ('CU-011', 'Ward-Zeta'),
    ('CU-012', 'Ward-Zeta'),
    -- Ward-Eta (Gastroenterology)
    ('CU-013', 'Ward-Eta'),
    ('CU-014', 'Ward-Eta'),
    -- Ward-Theta (Nephrology)
    ('CU-015', 'Ward-Theta'),
    ('CU-016', 'Ward-Theta'),
    -- Ward-Iota (Pulmonology)
    ('CU-017', 'Ward-Iota'),
    ('CU-018', 'Ward-Iota'),
    -- Ward-Kappa (General Surgery)
    ('CU-019', 'Ward-Kappa'),
    ('CU-020', 'Ward-Kappa');
GO


-- STEP 4: INSERT BED (40 records)

PRINT '>>> Inserting BED (40 records)...';
INSERT INTO BED (Bed_No, Care_Unit_No, Is_Occupied) VALUES
    -- CU-001 (Ward-Alpha) - 4 beds
    ('BED-001', 'CU-001', 1),
    ('BED-002', 'CU-001', 1),
    ('BED-003', 'CU-001', 0),
    ('BED-004', 'CU-001', 0),
    -- CU-002 (Ward-Alpha) - 4 beds
    ('BED-005', 'CU-002', 1),
    ('BED-006', 'CU-002', 0),
    ('BED-007', 'CU-002', 0),
    ('BED-008', 'CU-002', 0),
    -- CU-003 (Ward-Beta) - 4 beds
    ('BED-009', 'CU-003', 1),
    ('BED-010', 'CU-003', 1),
    ('BED-011', 'CU-003', 0),
    ('BED-012', 'CU-003', 0),
    -- CU-004 (Ward-Beta) - 4 beds
    ('BED-013', 'CU-004', 1),
    ('BED-014', 'CU-004', 0),
    ('BED-015', 'CU-004', 0),
    ('BED-016', 'CU-004', 0),
    -- CU-005 (Ward-Gamma) - 4 beds
    ('BED-017', 'CU-005', 1),
    ('BED-018', 'CU-005', 1),
    ('BED-019', 'CU-005', 0),
    ('BED-020', 'CU-005', 0),
    -- CU-006 (Ward-Gamma) - 4 beds
    ('BED-021', 'CU-006', 1),
    ('BED-022', 'CU-006', 0),
    ('BED-023', 'CU-006', 0),
    ('BED-024', 'CU-006', 0),
    -- CU-007 (Ward-Delta) - 4 beds
    ('BED-025', 'CU-007', 1),
    ('BED-026', 'CU-007', 1),
    ('BED-027', 'CU-007', 0),
    ('BED-028', 'CU-007', 0),
    -- CU-008 (Ward-Delta) - 4 beds
    ('BED-029', 'CU-008', 1),
    ('BED-030', 'CU-008', 0),
    ('BED-031', 'CU-008', 0),
    ('BED-032', 'CU-008', 0),
    -- CU-009 (Ward-Epsilon) - 4 beds
    ('BED-033', 'CU-009', 1),
    ('BED-034', 'CU-009', 1),
    ('BED-035', 'CU-009', 0),
    ('BED-036', 'CU-009', 0),
    -- CU-010 (Ward-Epsilon) - 4 beds
    ('BED-037', 'CU-010', 1),
    ('BED-038', 'CU-010', 0),
    ('BED-039', 'CU-010', 0),
    ('BED-040', 'CU-010', 0);
GO

-- Continue BED insertions for remaining wards
INSERT INTO BED (Bed_No, Care_Unit_No, Is_Occupied) VALUES
    -- CU-011 (Ward-Zeta) - 4 beds
    ('BED-041', 'CU-011', 1),
    ('BED-042', 'CU-011', 0),
    ('BED-043', 'CU-011', 0),
    ('BED-044', 'CU-011', 0),
    -- CU-012 (Ward-Zeta) - 4 beds
    ('BED-045', 'CU-012', 1),
    ('BED-046', 'CU-012', 0),
    ('BED-047', 'CU-012', 0),
    ('BED-048', 'CU-012', 0),
    -- CU-013 (Ward-Eta) - 4 beds
    ('BED-049', 'CU-013', 1),
    ('BED-050', 'CU-013', 1),
    ('BED-051', 'CU-013', 0),
    ('BED-052', 'CU-013', 0),
    -- CU-014 (Ward-Eta) - 4 beds
    ('BED-053', 'CU-014', 1),
    ('BED-054', 'CU-014', 0),
    ('BED-055', 'CU-014', 0),
    ('BED-056', 'CU-014', 0),
    -- CU-015 (Ward-Theta) - 4 beds
    ('BED-057', 'CU-015', 1),
    ('BED-058', 'CU-015', 1),
    ('BED-059', 'CU-015', 0),
    ('BED-060', 'CU-015', 0),
    -- CU-016 (Ward-Theta) - 4 beds
    ('BED-061', 'CU-016', 1),
    ('BED-062', 'CU-016', 0),
    ('BED-063', 'CU-016', 0),
    ('BED-064', 'CU-016', 0),
    -- CU-017 (Ward-Iota) - 4 beds
    ('BED-065', 'CU-017', 1),
    ('BED-066', 'CU-017', 1),
    ('BED-067', 'CU-017', 0),
    ('BED-068', 'CU-017', 0),
    -- CU-018 (Ward-Iota) - 4 beds
    ('BED-069', 'CU-018', 1),
    ('BED-070', 'CU-018', 0),
    ('BED-071', 'CU-018', 0),
    ('BED-072', 'CU-018', 0),
    -- CU-019 (Ward-Kappa) - 4 beds
    ('BED-073', 'CU-019', 1),
    ('BED-074', 'CU-019', 1),
    ('BED-075', 'CU-019', 0),
    ('BED-076', 'CU-019', 0),
    -- CU-020 (Ward-Kappa) - 4 beds
    ('BED-077', 'CU-020', 1),
    ('BED-078', 'CU-020', 0),
    ('BED-079', 'CU-020', 0),
    ('BED-080', 'CU-020', 0);
GO

-- STEP 5: INSERT DOCTOR (10 records)

PRINT '>>> Inserting DOCTOR (10 records)...';
INSERT INTO DOCTOR (Staff_No, Name, Position, Date_Joined_Team, Specialty_Name, Consultant_Staff_No) VALUES
    -- Consultants (5)
    ('DOC-001', 'Dr. Taha Siddiqui',       'consultant',           '2015-03-10', 'Cardiology',      NULL),
    ('DOC-002', 'Dr. Hamza Rehman',        'consultant',           '2017-06-22', 'Neurology',       NULL),
    ('DOC-003', 'Dr. Abdullah Malik',      'consultant',           '2019-01-15', 'Orthopedics',     NULL),
    ('DOC-004', 'Dr. Haris Qureshi',       'consultant',           '2013-09-01', 'Pediatrics',      NULL),
    ('DOC-005', 'Dr. Usman Farooq',        'consultant',           '2018-11-30', 'Oncology',        NULL),
    -- Team Members under Consultants (5)
    ('DOC-006', 'Dr. Ali Raza',            'junior houseman(jh)',  '2024-01-10', 'Cardiology',      'DOC-001'),
    ('DOC-007', 'Dr. Sara Khan',           'senior houseman(sh)',  '2023-06-15', 'Neurology',       'DOC-002'),
    ('DOC-008', 'Dr. Fatima Zafar',        'junior houseman(jh)',  '2024-03-20', 'Orthopedics',     'DOC-003'),
    ('DOC-009', 'Dr. Hassan Raza',         'assistant registrar(ar)','2022-11-01','Pediatrics',     'DOC-004'),
    ('DOC-010', 'Dr. Ayesha Naeem',        'registrar(r)',         '2021-08-15', 'Oncology',        'DOC-005');
GO


-- STEP 6: INSERT CONSULTANT (5 records)

PRINT '>>> Inserting CONSULTANT (5 records)...';
INSERT INTO CONSULTANT (Staff_No) VALUES
    ('DOC-001'), ('DOC-002'), ('DOC-003'), ('DOC-004'), ('DOC-005');
GO


-- STEP 7: INSERT NURSE (15 records)

PRINT '>>> Inserting NURSE (15 records)...';
INSERT INTO NURSE (Staff_No, Name, Ward_Name, Care_Unit_No) VALUES
    -- Day Sisters (5)
    ('NUR-001', 'Nurse Ayesha Malik',    'Ward-Alpha',   'CU-001'),
    ('NUR-002', 'Nurse Fatima Rehman',   'Ward-Beta',    'CU-003'),
    ('NUR-003', 'Nurse Zara Khan',       'Ward-Gamma',   'CU-005'),
    ('NUR-004', 'Nurse Sana Qureshi',    'Ward-Delta',   'CU-007'),
    ('NUR-005', 'Nurse Hira Farooq',     'Ward-Epsilon', 'CU-009'),
    -- Night Sisters (5)
    ('NUR-006', 'Nurse Amna Siddiqui',   'Ward-Zeta',    'CU-011'),
    ('NUR-007', 'Nurse Maryam Ahmed',    'Ward-Eta',     'CU-013'),
    ('NUR-008', 'Nurse Nadia Nawaz',     'Ward-Theta',   'CU-015'),
    ('NUR-009', 'Nurse Rabia Khalid',    'Ward-Iota',    'CU-017'),
    ('NUR-010', 'Nurse Sadia Tariq',     'Ward-Kappa',   'CU-019'),
    -- Staff Nurses (5)
    ('NUR-011', 'Nurse Kiran Baig',      'Ward-Alpha',   'CU-002'),
    ('NUR-012', 'Nurse Lubna Chaudhry',  'Ward-Beta',    'CU-004'),
    ('NUR-013', 'Nurse Mehwish Rana',    'Ward-Gamma',   'CU-006'),
    ('NUR-014', 'Nurse Noor Javed',      'Ward-Delta',   'CU-008'),
    ('NUR-015', 'Nurse Parveen Hussain', 'Ward-Epsilon', 'CU-010');
GO

-- STEP 8: INSERT NURSE SUBTYPES

PRINT '>>> Inserting DAY_SISTER (5 records)...';
INSERT INTO DAY_SISTER (Staff_No) VALUES 
    ('NUR-001'), ('NUR-002'), ('NUR-003'), ('NUR-004'), ('NUR-005');
GO

PRINT '>>> Inserting NIGHT_SISTER (5 records)...';
INSERT INTO NIGHT_SISTER (Staff_No) VALUES 
    ('NUR-006'), ('NUR-007'), ('NUR-008'), ('NUR-009'), ('NUR-010');
GO

PRINT '>>> Inserting STAFF_NURSE (5 records)...';
INSERT INTO STAFF_NURSE (Staff_No) VALUES 
    ('NUR-011'), ('NUR-012'), ('NUR-013'), ('NUR-014'), ('NUR-015');
GO

PRINT '>>> Inserting NON_REG_NURSE (0 records - optional)...';
-- Non-registered nurses are optional, can be added if needed
GO


-- STEP 9: INSERT PATIENT (30 records)

PRINT '>>> Inserting PATIENT (30 records)...';
INSERT INTO PATIENT (Patient_No, Patient_Name, Date_of_Birth, Date_Admitted, Care_Unit_No, Bed_No) VALUES
    -- Ward-Alpha / Cardiology (6 patients)
    ('PAT-001', 'Ahsan Raza',          '1985-03-15', '2024-01-10', 'CU-001', 'BED-001'),
    ('PAT-002', 'Bilal Ahmed',         '1990-07-22', '2024-01-15', 'CU-001', 'BED-002'),
    ('PAT-003', 'Sana Tariq',          '1978-11-05', '2024-01-20', 'CU-002', 'BED-005'),
    ('PAT-004', 'Omar Farooq',         '1965-02-18', '2024-01-25', 'CU-002', 'BED-006'),
    ('PAT-005', 'Zainab Ali',          '1995-09-30', '2024-02-01', 'CU-001', 'BED-003'),
    ('PAT-006', 'Hassan Raza',         '1988-12-12', '2024-02-05', 'CU-002', 'BED-007'),
    
    -- Ward-Beta / Neurology (6 patients)
    ('PAT-007', 'Fatima Akhtar',       '1972-04-08', '2024-02-10', 'CU-003', 'BED-009'),
    ('PAT-008', 'Usman Khalid',        '1980-06-25', '2024-02-15', 'CU-003', 'BED-010'),
    ('PAT-009', 'Ayesha Naeem',        '1992-01-17', '2024-02-20', 'CU-004', 'BED-013'),
    ('PAT-010', 'Hamza Ali',           '1975-10-03', '2024-02-25', 'CU-003', 'BED-011'),
    ('PAT-011', 'Sadia Mirza',         '1983-05-19', '2024-03-01', 'CU-004', 'BED-014'),
    ('PAT-012', 'Taha Siddiqui',       '1969-08-28', '2024-03-05', 'CU-003', 'BED-012'),
    
    -- Ward-Gamma / Orthopedics (6 patients)
    ('PAT-013', 'Nadia Khan',          '1998-03-14', '2024-03-10', 'CU-005', 'BED-017'),
    ('PAT-014', 'Saad Ahmed',          '1976-07-21', '2024-03-15', 'CU-005', 'BED-018'),
    ('PAT-015', 'Hira Anwar',          '1987-12-09', '2024-03-20', 'CU-006', 'BED-021'),
    ('PAT-016', 'Faisal Mehmood',      '1962-09-11', '2024-03-25', 'CU-005', 'BED-019'),
    ('PAT-017', 'Saba Qureshi',        '1993-04-27', '2024-03-30', 'CU-006', 'BED-022'),
    ('PAT-018', 'Mustafa Jan',         '1970-11-18', '2024-04-05', 'CU-005', 'BED-020'),
    
    -- Ward-Delta / Pediatrics (6 patients)
    ('PAT-019', 'Aryan Khan',          '2015-06-15', '2024-04-10', 'CU-007', 'BED-025'),
    ('PAT-020', 'Manahil Raza',        '2018-08-22', '2024-04-15', 'CU-007', 'BED-026'),
    ('PAT-021', 'Ibrahim Ali',         '2016-03-10', '2024-04-20', 'CU-008', 'BED-029'),
    ('PAT-022', 'Eman Fatima',         '2019-11-03', '2024-04-25', 'CU-007', 'BED-027'),
    ('PAT-023', 'Abdullah Shah',       '2017-05-17', '2024-04-30', 'CU-008', 'BED-030'),
    ('PAT-024', 'Fizza Hassan',        '2020-01-29', '2024-05-05', 'CU-007', 'BED-028'),
    
    -- Ward-Epsilon / Oncology (6 patients)
    ('PAT-025', 'Naveed Akhtar',       '1955-02-14', '2024-05-10', 'CU-009', 'BED-033'),
    ('PAT-026', 'Shamim Bibi',         '1960-07-23', '2024-05-15', 'CU-009', 'BED-034'),
    ('PAT-027', 'Tariq Mehmood',       '1958-09-30', '2024-05-20', 'CU-010', 'BED-037'),
    ('PAT-028', 'Khalida Perveen',     '1965-12-05', '2024-05-25', 'CU-009', 'BED-035'),
    ('PAT-029', 'Javed Iqbal',         '1972-04-18', '2024-05-30', 'CU-010', 'BED-038'),
    ('PAT-030', 'Rubina Ashraf',       '1968-08-27', '2024-06-05', 'CU-009', 'BED-036');
GO

-- ============================================================
-- STEP 10: UPDATE OCCUPIED BEDS
-- ============================================================
PRINT '>>> Updating occupied beds...';
UPDATE BED SET Is_Occupied = 1 WHERE Bed_No IN (
    'BED-001', 'BED-002', 'BED-005', 'BED-006', 'BED-003', 'BED-007',
    'BED-009', 'BED-010', 'BED-013', 'BED-011', 'BED-014', 'BED-012',
    'BED-017', 'BED-018', 'BED-021', 'BED-019', 'BED-022', 'BED-020',
    'BED-025', 'BED-026', 'BED-029', 'BED-027', 'BED-030', 'BED-028',
    'BED-033', 'BED-034', 'BED-037', 'BED-035', 'BED-038', 'BED-036'
);
GO


-- STEP 11: INSERT COMPLAINT (15 records)

PRINT '>>> Inserting COMPLAINT (15 records)...';
INSERT INTO COMPLAINT (Complaint_Code, Description) VALUES
    ('CMP-001', 'Severe chest pain and shortness of breath'),
    ('CMP-002', 'Persistent headaches and dizziness'),
    ('CMP-003', 'Knee joint pain and swelling'),
    ('CMP-004', 'High fever and dehydration'),
    ('CMP-005', 'Unexplained weight loss and fatigue'),
    ('CMP-006', 'Chronic skin rash and itching'),
    ('CMP-007', 'Abdominal pain and nausea'),
    ('CMP-008', 'Swelling in legs and reduced urine output'),
    ('CMP-009', 'Chronic cough and breathing difficulty'),
    ('CMP-010', 'Acute appendicitis symptoms'),
    ('CMP-011', 'Irregular heartbeat and palpitations'),
    ('CMP-012', 'Numbness and tingling in extremities'),
    ('CMP-013', 'Severe back pain and limited mobility'),
    ('CMP-014', 'Recurrent ear infections and hearing loss'),
    ('CMP-015', 'Blood in urine and flank pain');
GO


-- STEP 12: INSERT TREATMENT (20 records)

PRINT '>>> Inserting TREATMENT (20 records)...';
INSERT INTO TREATMENT (Treatment_Code, Description) VALUES
    ('TRT-001', 'Angioplasty and stent placement'),
    ('TRT-002', 'MRI scan and neurological assessment'),
    ('TRT-003', 'Physiotherapy and knee brace fitting'),
    ('TRT-004', 'IV fluids and antipyretics'),
    ('TRT-005', 'Chemotherapy cycle 1'),
    ('TRT-006', 'Topical corticosteroid application'),
    ('TRT-007', 'Endoscopy and antacid therapy'),
    ('TRT-008', 'Dialysis session'),
    ('TRT-009', 'Bronchodilator therapy and oxygen support'),
    ('TRT-010', 'Laparoscopic appendectomy'),
    ('TRT-011', 'Echocardiogram and beta-blocker therapy'),
    ('TRT-012', 'Nerve conduction study and gabapentin therapy'),
    ('TRT-013', 'Spinal decompression surgery'),
    ('TRT-014', 'Audiometry and antibiotic course'),
    ('TRT-015', 'Kidney stone lithotripsy'),
    ('TRT-016', 'Liver biopsy and antiviral therapy'),
    ('TRT-017', 'Isotretinoin therapy and dietary guidance'),
    ('TRT-018', 'Colonoscopy and polypectomy'),
    ('TRT-019', 'Inhaled corticosteroid and spirometry'),
    ('TRT-020', 'Surgical debridement and IV antibiotics');
GO


-- STEP 13: INSERT PATIENT_TREATMENT_RECORD (30 records)

PRINT '>>> Inserting PATIENT_TREATMENT_RECORD (30 records)...';
INSERT INTO PATIENT_TREATMENT_RECORD (Patient_No, Treatment_Code, Complaint_Code, Doctor_Staff_No, Date_Started, Date_Ended) VALUES
    -- Cardiology patients
    ('PAT-001', 'TRT-001', 'CMP-001', 'DOC-001', '2024-01-11', '2024-01-25'),
    ('PAT-002', 'TRT-011', 'CMP-011', 'DOC-006', '2024-01-16', '2024-01-30'),
    ('PAT-003', 'TRT-001', 'CMP-001', 'DOC-001', '2024-01-21', '2024-02-05'),
    ('PAT-004', 'TRT-011', 'CMP-011', 'DOC-006', '2024-01-26', '2024-02-10'),
    ('PAT-005', 'TRT-001', 'CMP-001', 'DOC-001', '2024-02-02', '2024-02-16'),
    ('PAT-006', 'TRT-011', 'CMP-011', 'DOC-006', '2024-02-06', '2024-02-20'),
    
    -- Neurology patients
    ('PAT-007', 'TRT-002', 'CMP-002', 'DOC-002', '2024-02-11', '2024-02-25'),
    ('PAT-008', 'TRT-012', 'CMP-012', 'DOC-007', '2024-02-16', '2024-03-02'),
    ('PAT-009', 'TRT-002', 'CMP-002', 'DOC-002', '2024-02-21', '2024-03-07'),
    ('PAT-010', 'TRT-012', 'CMP-012', 'DOC-007', '2024-02-26', '2024-03-12'),
    ('PAT-011', 'TRT-002', 'CMP-002', 'DOC-002', '2024-03-02', '2024-03-16'),
    ('PAT-012', 'TRT-012', 'CMP-012', 'DOC-007', '2024-03-06', '2024-03-20'),
    
    -- Orthopedics patients
    ('PAT-013', 'TRT-003', 'CMP-003', 'DOC-003', '2024-03-11', '2024-03-25'),
    ('PAT-014', 'TRT-013', 'CMP-013', 'DOC-008', '2024-03-16', '2024-03-30'),
    ('PAT-015', 'TRT-003', 'CMP-003', 'DOC-003', '2024-03-21', '2024-04-05'),
    ('PAT-016', 'TRT-013', 'CMP-013', 'DOC-008', '2024-03-26', '2024-04-10'),
    ('PAT-017', 'TRT-003', 'CMP-003', 'DOC-003', '2024-03-31', '2024-04-15'),
    ('PAT-018', 'TRT-013', 'CMP-013', 'DOC-008', '2024-04-06', '2024-04-20'),
    
    -- Pediatrics patients
    ('PAT-019', 'TRT-004', 'CMP-004', 'DOC-004', '2024-04-11', '2024-04-25'),
    ('PAT-020', 'TRT-014', 'CMP-014', 'DOC-009', '2024-04-16', '2024-04-30'),
    ('PAT-021', 'TRT-004', 'CMP-004', 'DOC-004', '2024-04-21', '2024-05-05'),
    ('PAT-022', 'TRT-014', 'CMP-014', 'DOC-009', '2024-04-26', '2024-05-10'),
    ('PAT-023', 'TRT-004', 'CMP-004', 'DOC-004', '2024-05-01', '2024-05-15'),
    ('PAT-024', 'TRT-014', 'CMP-014', 'DOC-009', '2024-05-06', '2024-05-20'),
    
    -- Oncology patients
    ('PAT-025', 'TRT-005', 'CMP-005', 'DOC-005', '2024-05-11', '2024-06-10'),
    ('PAT-026', 'TRT-005', 'CMP-005', 'DOC-010', '2024-05-16', '2024-06-15'),
    ('PAT-027', 'TRT-005', 'CMP-005', 'DOC-005', '2024-05-21', '2024-06-20'),
    ('PAT-028', 'TRT-005', 'CMP-005', 'DOC-010', '2024-05-26', '2024-06-25'),
    ('PAT-029', 'TRT-005', 'CMP-005', 'DOC-005', '2024-05-31', '2024-06-30'),
    ('PAT-030', 'TRT-005', 'CMP-005', 'DOC-010', '2024-06-06', '2024-07-06');
GO


-- STEP 14: INSERT PREVIOUS_EXPERIENCE (20 records - 2 per doctor)

PRINT '>>> Inserting PREVIOUS_EXPERIENCE (20 records)...';
INSERT INTO PREVIOUS_EXPERIENCE (Exp_ID, Staff_No, From_Date, To_Date, Establishment, Position) VALUES
    -- DOC-001
    ('EXP-001', 'DOC-001', '2005-06-01', '2010-05-31', 'Aga Khan University Hospital', 'Resident'),
    ('EXP-002', 'DOC-001', '2010-06-01', '2015-03-09', 'Aga Khan University Hospital', 'Senior Registrar'),
    -- DOC-002
    ('EXP-003', 'DOC-002', '2008-01-01', '2012-12-31', 'Shaukat Khanum Memorial', 'Fellow'),
    ('EXP-004', 'DOC-002', '2013-01-01', '2017-06-21', 'Shaukat Khanum Memorial', 'Associate Consultant'),
    -- DOC-003
    ('EXP-005', 'DOC-003', '2014-03-01', '2016-08-31', 'Services Hospital Lahore', 'Registrar'),
    ('EXP-006', 'DOC-003', '2016-09-01', '2019-01-14', 'Services Hospital Lahore', 'Senior Registrar'),
    -- DOC-004
    ('EXP-007', 'DOC-004', '2003-07-01', '2008-06-30', 'Mayo Hospital Lahore', 'Registrar'),
    ('EXP-008', 'DOC-004', '2008-07-01', '2013-08-31', 'Mayo Hospital Lahore', 'Senior Registrar'),
    -- DOC-005
    ('EXP-009', 'DOC-005', '2010-11-01', '2014-10-31', 'Pakistan Institute of Med Sci', 'Registrar'),
    ('EXP-010', 'DOC-005', '2014-11-01', '2018-11-29', 'Pakistan Institute of Med Sci', 'Associate Consultant'),
    -- DOC-006
    ('EXP-011', 'DOC-006', '2016-09-01', '2020-08-31', 'Jinnah Hospital Lahore', 'Intern'),
    ('EXP-012', 'DOC-006', '2020-09-01', '2024-01-09', 'Jinnah Hospital Lahore', 'Junior Registrar'),
    -- DOC-007
    ('EXP-013', 'DOC-007', '2015-06-01', '2019-05-31', 'Combined Military Hospital', 'Intern'),
    ('EXP-014', 'DOC-007', '2019-06-01', '2023-06-14', 'Combined Military Hospital', 'Junior Registrar'),
    -- DOC-008
    ('EXP-015', 'DOC-008', '2017-01-01', '2020-12-31', 'Liaquat National Hospital', 'Intern'),
    ('EXP-016', 'DOC-008', '2021-01-01', '2024-03-19', 'Liaquat National Hospital', 'Registrar'),
    -- DOC-009
    ('EXP-017', 'DOC-009', '2014-08-01', '2018-07-31', 'Rawalpindi General Hospital', 'Intern'),
    ('EXP-018', 'DOC-009', '2018-08-01', '2022-10-31', 'Rawalpindi General Hospital', 'Junior Registrar'),
    -- DOC-010
    ('EXP-019', 'DOC-010', '2013-07-01', '2017-06-30', 'Holy Family Hospital RWP', 'Intern'),
    ('EXP-020', 'DOC-010', '2017-07-01', '2021-08-14', 'Holy Family Hospital RWP', 'Registrar');
GO


-- STEP 15: INSERT PROGRESS (20 records - 2 per doctor)

PRINT '>>> Inserting PROGRESS (20 records)...';
INSERT INTO PROGRESS (Progress_ID, Staff_No, Establishment, Position, Date, Performance_Grade) VALUES
    -- DOC-001
    ('PRG-001', 'DOC-001', 'Aga Khan University Hospital', 'Registrar', '2010-06-01', 'B+'),
    ('PRG-002', 'DOC-001', 'Aga Khan University Hospital', 'Senior Registrar', '2015-03-10', 'A'),
    -- DOC-002
    ('PRG-003', 'DOC-002', 'Shaukat Khanum Memorial', 'Fellow', '2013-01-01', 'A'),
    ('PRG-004', 'DOC-002', 'Shaukat Khanum Memorial', 'Consultant', '2017-06-22', 'A+'),
    -- DOC-003
    ('PRG-005', 'DOC-003', 'Services Hospital Lahore', 'Registrar', '2016-09-01', 'B'),
    ('PRG-006', 'DOC-003', 'Services Hospital Lahore', 'Senior Registrar', '2019-01-15', 'B+'),
    -- DOC-004
    ('PRG-007', 'DOC-004', 'Mayo Hospital Lahore', 'Registrar', '2008-07-01', 'A-'),
    ('PRG-008', 'DOC-004', 'Mayo Hospital Lahore', 'Senior Registrar', '2013-09-01', 'A'),
    -- DOC-005
    ('PRG-009', 'DOC-005', 'Pakistan Institute of Med Sci', 'Registrar', '2014-11-01', 'B+'),
    ('PRG-010', 'DOC-005', 'Pakistan Institute of Med Sci', 'Consultant', '2018-11-30', 'A-'),
    -- DOC-006
    ('PRG-011', 'DOC-006', 'Jinnah Hospital Lahore', 'Intern', '2016-09-01', 'B'),
    ('PRG-012', 'DOC-006', 'Jinnah Hospital Lahore', 'Junior Registrar', '2020-09-01', 'A-'),
    -- DOC-007
    ('PRG-013', 'DOC-007', 'Combined Military Hospital', 'Intern', '2015-06-01', 'B+'),
    ('PRG-014', 'DOC-007', 'Combined Military Hospital', 'Junior Registrar', '2019-06-01', 'A'),
    -- DOC-008
    ('PRG-015', 'DOC-008', 'Liaquat National Hospital', 'Intern', '2017-01-01', 'B'),
    ('PRG-016', 'DOC-008', 'Liaquat National Hospital', 'Registrar', '2021-01-01', 'B+'),
    -- DOC-009
    ('PRG-017', 'DOC-009', 'Rawalpindi General Hospital', 'Intern', '2014-08-01', 'C+'),
    ('PRG-018', 'DOC-009', 'Rawalpindi General Hospital', 'Junior Registrar', '2018-08-01', 'B'),
    -- DOC-010
    ('PRG-019', 'DOC-010', 'Holy Family Hospital RWP', 'Intern', '2013-07-01', 'B'),
    ('PRG-020', 'DOC-010', 'Holy Family Hospital RWP', 'Registrar', '2017-07-01', 'A-');
GO


-- RE-ENABLE CONSTRAINTS

PRINT '>>> Re-enabling and validating all FK constraints...';

ALTER TABLE WARD WITH CHECK CHECK CONSTRAINT ALL;
ALTER TABLE CARE_UNIT WITH CHECK CHECK CONSTRAINT ALL;
ALTER TABLE BED WITH CHECK CHECK CONSTRAINT ALL;
ALTER TABLE DOCTOR WITH CHECK CHECK CONSTRAINT ALL;
ALTER TABLE CONSULTANT WITH CHECK CHECK CONSTRAINT ALL;
ALTER TABLE NURSE WITH CHECK CHECK CONSTRAINT ALL;
ALTER TABLE DAY_SISTER WITH CHECK CHECK CONSTRAINT ALL;
ALTER TABLE NIGHT_SISTER WITH CHECK CHECK CONSTRAINT ALL;
ALTER TABLE STAFF_NURSE WITH CHECK CHECK CONSTRAINT ALL;
ALTER TABLE NON_REG_NURSE WITH CHECK CHECK CONSTRAINT ALL;
ALTER TABLE PATIENT WITH CHECK CHECK CONSTRAINT ALL;
ALTER TABLE PATIENT_TREATMENT_RECORD WITH CHECK CHECK CONSTRAINT ALL;
ALTER TABLE PREVIOUS_EXPERIENCE WITH CHECK CHECK CONSTRAINT ALL;
ALTER TABLE PROGRESS WITH CHECK CHECK CONSTRAINT ALL;
GO


-- FINAL VERIFICATION

PRINT '>>> Final verification - Row counts:';
PRINT '==========================================';
SELECT 'SPECIALTY' AS TableName, COUNT(*) AS TotalCount FROM SPECIALTY
UNION ALL
SELECT 'WARD', COUNT(*) FROM WARD
UNION ALL
SELECT 'CARE_UNIT', COUNT(*) FROM CARE_UNIT
UNION ALL
SELECT 'BED', COUNT(*) FROM BED
UNION ALL
SELECT 'BED (Available)', COUNT(*) FROM BED WHERE Is_Occupied = 0
UNION ALL
SELECT 'BED (Occupied)', COUNT(*) FROM BED WHERE Is_Occupied = 1
UNION ALL
SELECT 'DOCTOR', COUNT(*) FROM DOCTOR
UNION ALL
SELECT 'CONSULTANT', COUNT(*) FROM CONSULTANT
UNION ALL
SELECT 'NURSE', COUNT(*) FROM NURSE
UNION ALL
SELECT 'DAY_SISTER', COUNT(*) FROM DAY_SISTER
UNION ALL
SELECT 'NIGHT_SISTER', COUNT(*) FROM NIGHT_SISTER
UNION ALL
SELECT 'STAFF_NURSE', COUNT(*) FROM STAFF_NURSE
UNION ALL
SELECT 'PATIENT', COUNT(*) FROM PATIENT
UNION ALL
SELECT 'COMPLAINT', COUNT(*) FROM COMPLAINT
UNION ALL
SELECT 'TREATMENT', COUNT(*) FROM TREATMENT
UNION ALL
SELECT 'PATIENT_TREATMENT_RECORD', COUNT(*) FROM PATIENT_TREATMENT_RECORD
UNION ALL
SELECT 'PREVIOUS_EXPERIENCE', COUNT(*) FROM PREVIOUS_EXPERIENCE
UNION ALL
SELECT 'PROGRESS', COUNT(*) FROM PROGRESS;
GO


PRINT '>>> Database setup complete!';
PRINT '>>> Summary:';
PRINT '>>> - 10 Specialties';
PRINT '>>> - 10 Wards';
PRINT '>>> - 20 Care Units (2 per ward)';
PRINT '>>> - 40 Beds (4 per care unit)';
PRINT '>>> - 10 Doctors (5 Consultants + 5 Team Members)';
PRINT '>>> - 15 Nurses (5 Day Sisters + 5 Night Sisters + 5 Staff Nurses)';
PRINT '>>> - 30 Patients (3 per ward)';
PRINT '>>> - 15 Complaints';
PRINT '>>> - 20 Treatments';
PRINT '>>> - 30 Patient Treatment Records';
PRINT '>>> - 20 Previous Experience Records';
PRINT '>>> - 20 Progress Records';
GO