<?php
// IVOR PAINE MEMORIAL HOSPITAL — Database Management System


$serverName = "DESKTOP-N658JKQ\\SQLEXPRESS";
$connectionOptions = [
    "Database"               => "HospitalDB",
    "TrustServerCertificate" => true,
    "LoginTimeout"           => 30
];

$conn    = @sqlsrv_connect($serverName, $connectionOptions);
$dbError = null;
if (!$conn) {
    $dbError = print_r(sqlsrv_errors(), true);
}


// HELPER FUNCTIONS


function runQuery($conn, $sql, $params = null) {
    if (!$conn) return [];
    $stmt = ($params !== null)
        ? sqlsrv_query($conn, $sql, $params)
        : sqlsrv_query($conn, $sql);
    if ($stmt === false) {
        return ['__error' => print_r(sqlsrv_errors(), true)];
    }
    $rows = [];
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        foreach ($row as $k => $v) {
            if ($v instanceof DateTime) $row[$k] = $v->format('Y-m-d');
        }
        $rows[] = $row;
    }
    sqlsrv_free_stmt($stmt);
    return $rows;
}

function firstRow($conn, $sql, $params = null) {
    $r = runQuery($conn, $sql, $params);
    return (is_array($r) && !isset($r['__error']) && count($r) > 0) ? $r[0] : null;
}

function h($s) {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function renderTable($rows, $caption = '') {
    if (!is_array($rows) || count($rows) === 0) {
        echo '<div class="alert alert-warning mt-2">No records found.</div>';
        return;
    }
    if (isset($rows['__error'])) {
        echo '<div class="alert alert-danger">Query error: ' . h($rows['__error']) . '</div>';
        return;
    }
    $cols = array_keys($rows[0]);
    echo '<div class="table-responsive mt-3">';
    echo '<table class="table table-striped table-bordered table-hover table-sm align-middle">';
    if ($caption) {
        echo '<caption class="caption-top fw-semibold text-muted">' . h($caption) . '</caption>';
    }
    echo '<thead class="table-dark"><tr>';
    foreach ($cols as $c) {
        echo '<th>' . h(str_replace('_', ' ', $c)) . '</th>';
    }
    echo '</tr></thead><tbody>';
    foreach ($rows as $row) {
        echo '<tr>';
        foreach ($row as $v) {
            echo '<td>' . h($v) . '</td>';
        }
        echo '</tr>';
    }
    echo '</tbody></table></div>';
}

function nextId($conn, $table, $pkCol, $prefix, $padLen = 3) {
    $row = firstRow($conn,
        "SELECT MAX(CAST(SUBSTRING($pkCol, LEN('$prefix')+2, 99) AS INT)) AS m FROM $table");
    $num = ($row && $row['m'] !== null) ? (int)$row['m'] + 1 : 1;
    return $prefix . '-' . str_pad($num, $padLen, '0', STR_PAD_LEFT);
}


// PRE-COMPUTE NEXT IDs


$nextPatientNo = nextId($conn, 'PATIENT',  'Patient_No', 'PAT');
$nextDoctorNo  = nextId($conn, 'DOCTOR',   'Staff_No',   'DOC');
$nextNurseNo   = nextId($conn, 'NURSE',    'Staff_No',   'NUR');
$nextBedNo     = nextId($conn, 'BED',      'Bed_No',     'BED');


// LOOKUP LISTS


$wardList       = runQuery($conn, "SELECT Ward_Name, Number_Of_Care_Units FROM WARD ORDER BY Ward_Name");
$consultantList = runQuery($conn, "SELECT Staff_No, Name FROM DOCTOR WHERE Position = 'consultant' ORDER BY Name");
$patientList    = runQuery($conn, "SELECT Patient_No, Patient_Name FROM PATIENT ORDER BY Patient_No");
$complaintList  = runQuery($conn, "SELECT Complaint_Code, Description FROM COMPLAINT ORDER BY Complaint_Code");
$specialtyList  = runQuery($conn, "SELECT Specialty_Name FROM SPECIALTY ORDER BY Specialty_Name");
$careUnitList   = runQuery($conn, "SELECT Care_Unit_No, Ward_Name FROM CARE_UNIT ORDER BY Care_Unit_No");


// CURRENT PAGE


$page      = $_GET['page'] ?? 'dashboard';
$insertMsg = null;


if (isset($_GET['ajax'])) {
    header('Content-Type: application/json');

    if ($_GET['ajax'] === 'get_care_units' && isset($_GET['ward'])) {
        $ward     = $_GET['ward'];
        $careUnits = runQuery($conn,
            "SELECT Care_Unit_No FROM CARE_UNIT WHERE Ward_Name = ? ORDER BY Care_Unit_No",
            [$ward]
        );
        echo json_encode($careUnits);
        exit;
    }

    if ($_GET['ajax'] === 'get_available_beds' && isset($_GET['cu'])) {
        $cu   = $_GET['cu'];
        $beds = runQuery($conn,
            "SELECT Bed_No FROM BED WHERE Care_Unit_No = ? AND Is_Occupied = 0 ORDER BY Bed_No",
            [$cu]
        );
        echo json_encode($beds);
        exit;
    }

    exit;
}


// INSERT: WARD


if ($page === 'ins_ward' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $wn       = trim($_POST['ward_name']      ?? '');
    $sp       = trim($_POST['specialty_name'] ?? '');
    $numUnits = intval($_POST['num_care_units'] ?? 2);

    if ($wn === '' || $sp === '') {
        $insertMsg = ['type' => 'danger', 'text' => 'All fields are required.'];
    } else {
        $chk = firstRow($conn, "SELECT 1 AS n FROM WARD WHERE Ward_Name = ?", [$wn]);
        if ($chk) {
            $insertMsg = ['type' => 'danger', 'text' => "Ward '$wn' already exists."];
        } else {
            $ok = sqlsrv_query($conn,
                "INSERT INTO WARD (Ward_Name, Specialty_Name, Number_Of_Care_Units) VALUES (?,?,?)",
                [$wn, $sp, $numUnits]
            );
            if ($ok) {
                $insertMsg = [
                    'type' => 'success',
                    'text' => "Ward <strong>" . h($wn) . "</strong> added successfully with <strong>$numUnits</strong> care unit(s)."
                ];
            } else {
                $insertMsg = ['type' => 'danger', 'text' => 'Insert failed: ' . print_r(sqlsrv_errors(), true)];
            }
            $wardList = runQuery($conn, "SELECT Ward_Name, Number_Of_Care_Units FROM WARD ORDER BY Ward_Name");
        }
    }
}


// INSERT: BED


if ($page === 'ins_bed' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $bn = trim($_POST['bed_no']      ?? '');
    $cu = trim($_POST['care_unit_no'] ?? '');

    if ($bn === '' || $cu === '') {
        $insertMsg = ['type' => 'danger', 'text' => 'Bed No and Care Unit are required.'];
    } else {
        $chk = firstRow($conn, "SELECT 1 AS n FROM BED WHERE Bed_No = ?", [$bn]);
        if ($chk) {
            $insertMsg = ['type' => 'danger', 'text' => "Bed '$bn' already exists."];
        } else {
            $ok = sqlsrv_query($conn,
                "INSERT INTO BED (Bed_No, Care_Unit_No, Is_Occupied) VALUES (?,?,0)",
                [$bn, $cu]
            );
            $insertMsg = $ok
                ? ['type' => 'success', 'text' => "Bed <strong>" . h($bn) . "</strong> added successfully."]
                : ['type' => 'danger',  'text' => 'Insert failed: ' . print_r(sqlsrv_errors(), true)];
            $nextBedNo = nextId($conn, 'BED', 'Bed_No', 'BED');
        }
    }
}


// INSERT: PATIENT


if ($page === 'ins_patient' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $pno = trim($_POST['patient_no']    ?? '');
    $pnm = trim($_POST['patient_name']  ?? '');
    $dob = trim($_POST['dob']           ?? '');
    $da  = trim($_POST['date_admitted'] ?? '');
    $cu  = trim($_POST['care_unit_no']  ?? '');
    $bn  = trim($_POST['bed_no']        ?? '');

    if ($pno === '' || $pnm === '' || $cu === '' || $bn === '') {
        $insertMsg = ['type' => 'danger', 'text' => 'All fields are required.'];
    } else {
        $chk = firstRow($conn, "SELECT 1 AS n FROM PATIENT WHERE Patient_No = ?", [$pno]);
        if ($chk) {
            $insertMsg = ['type' => 'danger', 'text' => "Patient No '$pno' already exists."];
        } else {
            $dobParam = ($dob !== '') ? $dob : null;
            $daParam  = ($da  !== '') ? $da  : null;
            $ok = sqlsrv_query($conn,
                "INSERT INTO PATIENT (Patient_No, Patient_Name, Date_of_Birth, Date_Admitted, Care_Unit_No, Bed_No)
                 VALUES (?,?,?,?,?,?)",
                [$pno, $pnm, $dobParam, $daParam, $cu, $bn]
            );
            if ($ok) {
                sqlsrv_query($conn, "UPDATE BED SET Is_Occupied = 1 WHERE Bed_No = ?", [$bn]);
                $insertMsg = [
                    'type' => 'success',
                    'text' => "Patient <strong>" . h($pnm) . "</strong> admitted successfully."
                ];
            } else {
                $insertMsg = ['type' => 'danger', 'text' => 'Insert failed: ' . print_r(sqlsrv_errors(), true)];
            }
            $patientList   = runQuery($conn, "SELECT Patient_No, Patient_Name FROM PATIENT ORDER BY Patient_No");
            $nextPatientNo = nextId($conn, 'PATIENT', 'Patient_No', 'PAT');
        }
    }
}


// INSERT: DOCTOR


if ($page === 'ins_doctor' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $sno   = trim($_POST['staff_no']             ?? '');
    $nm    = trim($_POST['name']                 ?? '');
    $pos   = trim($_POST['position']             ?? '');
    $djt   = trim($_POST['date_joined']          ?? '');
    $sp    = trim($_POST['specialty_name']       ?? '');
    $cons  = trim($_POST['consultant_staff_no']  ?? '');
    $isCon = isset($_POST['is_consultant']) ? 1 : 0;

    if ($sno === '' || $nm === '' || $sp === '') {
        $insertMsg = ['type' => 'danger', 'text' => 'Staff No, Name and Specialty are required.'];
    } else {
        $chk = firstRow($conn, "SELECT 1 AS n FROM DOCTOR WHERE Staff_No = ?", [$sno]);
        if ($chk) {
            $insertMsg = ['type' => 'danger', 'text' => "Staff No '$sno' already exists."];
        } else {
            $posValue  = $isCon ? 'consultant' : $pos;
            $consValue = ($cons !== '') ? $cons : null;
            $djtParam  = ($djt  !== '') ? $djt  : null;
            $ok = sqlsrv_query($conn,
                "INSERT INTO DOCTOR (Staff_No, Name, Position, Date_Joined_Team, Specialty_Name, Consultant_Staff_No)
                 VALUES (?,?,?,?,?,?)",
                [$sno, $nm, $posValue, $djtParam, $sp, $consValue]
            );
            if ($ok && $isCon) {
                sqlsrv_query($conn, "INSERT INTO CONSULTANT (Staff_No) VALUES (?)", [$sno]);
            }
            $insertMsg = $ok
                ? ['type' => 'success', 'text' => "Doctor <strong>" . h($nm) . "</strong> added successfully."]
                : ['type' => 'danger',  'text' => 'Insert failed: ' . print_r(sqlsrv_errors(), true)];
            $consultantList = runQuery($conn, "SELECT Staff_No, Name FROM DOCTOR WHERE Position = 'consultant' ORDER BY Name");
            $nextDoctorNo   = nextId($conn, 'DOCTOR', 'Staff_No', 'DOC');
        }
    }
}


// INSERT: NURSE


if ($page === 'ins_nurse' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $sno  = trim($_POST['staff_no']     ?? '');
    $nm   = trim($_POST['name']         ?? '');
    $wn   = trim($_POST['ward_name']    ?? '');
    $cu   = trim($_POST['care_unit_no'] ?? '');
    $role = trim($_POST['role']         ?? '');

    if ($sno === '' || $nm === '' || $wn === '' || $cu === '' || $role === '') {
        $insertMsg = ['type' => 'danger', 'text' => 'All fields are required.'];
    } else {
        $chk = firstRow($conn, "SELECT 1 AS n FROM NURSE WHERE Staff_No = ?", [$sno]);
        if ($chk) {
            $insertMsg = ['type' => 'danger', 'text' => "Staff No '$sno' already exists."];
        } else {
            $ok = sqlsrv_query($conn,
                "INSERT INTO NURSE (Staff_No, Name, Ward_Name, Care_Unit_No) VALUES (?,?,?,?)",
                [$sno, $nm, $wn, $cu]
            );
            if ($ok) {
                $roleMap = [
                    'day_sister'   => "INSERT INTO DAY_SISTER   (Staff_No) VALUES (?)",
                    'night_sister' => "INSERT INTO NIGHT_SISTER (Staff_No) VALUES (?)",
                    'staff_nurse'  => "INSERT INTO STAFF_NURSE  (Staff_No) VALUES (?)",
                    'non_reg'      => "INSERT INTO NON_REG_NURSE(Staff_No) VALUES (?)",
                ];
                if (isset($roleMap[$role])) {
                    sqlsrv_query($conn, $roleMap[$role], [$sno]);
                }
                $insertMsg = ['type' => 'success', 'text' => "Nurse <strong>" . h($nm) . "</strong> added successfully."];
            } else {
                $insertMsg = ['type' => 'danger', 'text' => 'Insert failed: ' . print_r(sqlsrv_errors(), true)];
            }
            $nextNurseNo = nextId($conn, 'NURSE', 'Staff_No', 'NUR');
        }
    }
}


// INSERT: SPECIALTY


if ($page === 'ins_specialty' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $sn = trim($_POST['specialty_name'] ?? '');
    if ($sn === '') {
        $insertMsg = ['type' => 'danger', 'text' => 'Specialty name is required.'];
    } else {
        $chk = firstRow($conn, "SELECT 1 AS n FROM SPECIALTY WHERE Specialty_Name = ?", [$sn]);
        if ($chk) {
            $insertMsg = ['type' => 'danger', 'text' => "Specialty '$sn' already exists."];
        } else {
            $ok = sqlsrv_query($conn, "INSERT INTO SPECIALTY (Specialty_Name) VALUES (?)", [$sn]);
            $insertMsg = $ok
                ? ['type' => 'success', 'text' => "Specialty <strong>" . h($sn) . "</strong> added successfully."]
                : ['type' => 'danger',  'text' => 'Insert failed: ' . print_r(sqlsrv_errors(), true)];
            $specialtyList = runQuery($conn, "SELECT Specialty_Name FROM SPECIALTY ORDER BY Specialty_Name");
        }
    }
}


// FORM 1 — PATIENT RECORD


$patientRecord  = null;
$patientHistory = null;
$patientInput   = trim($_GET['patient_no'] ?? '');

if ($page === 'form_patient' && $patientInput !== '') {
    $patientRecord = firstRow($conn, "
        SELECT  p.Patient_No, p.Patient_Name, p.Date_of_Birth, p.Date_Admitted,
                p.Care_Unit_No, p.Bed_No, cu.Ward_Name, w.Specialty_Name
        FROM    PATIENT p
        JOIN    CARE_UNIT cu ON p.Care_Unit_No = cu.Care_Unit_No
        JOIN    WARD w       ON cu.Ward_Name   = w.Ward_Name
        WHERE   p.Patient_No = ?",
        [$patientInput]
    );

    $patientHistory = runQuery($conn, "
        SELECT  ptr.Complaint_Code,
                c.Description  AS Complaint,
                ptr.Treatment_Code,
                t.Description  AS Treatment,
                d.Staff_No     AS Doctor_No,
                d.Name         AS Doctor_Name,
                CASE WHEN con.Staff_No IS NOT NULL THEN 'Yes' ELSE 'No' END AS Is_Consultant,
                ptr.Date_Started,
                ptr.Date_Ended
        FROM    PATIENT_TREATMENT_RECORD ptr
        JOIN    COMPLAINT  c   ON ptr.Complaint_Code  = c.Complaint_Code
        JOIN    TREATMENT  t   ON ptr.Treatment_Code  = t.Treatment_Code
        JOIN    DOCTOR     d   ON ptr.Doctor_Staff_No = d.Staff_No
        LEFT JOIN CONSULTANT con ON d.Staff_No = con.Staff_No
        WHERE   ptr.Patient_No = ?
        ORDER BY ptr.Date_Started",
        [$patientInput]
    );
}


// FORM 2 — WARD RECORD


$wardRecord   = null;
$wardNurses   = null;
$wardPatients = null;
$wardInput    = trim($_GET['ward_name'] ?? '');

if ($page === 'form_ward' && $wardInput !== '') {
    $wardRecord = firstRow($conn,
        "SELECT Ward_Name, Specialty_Name, Number_Of_Care_Units FROM WARD WHERE Ward_Name = ?",
        [$wardInput]
    );

    $wardNurses = runQuery($conn, "
        SELECT  n.Staff_No, n.Name, n.Care_Unit_No,
                CASE
                    WHEN ds.Staff_No IS NOT NULL THEN 'Day Sister'
                    WHEN ns.Staff_No IS NOT NULL THEN 'Night Sister'
                    WHEN sn.Staff_No IS NOT NULL THEN 'Staff Nurse'
                    WHEN nr.Staff_No IS NOT NULL THEN 'Non-Reg Nurse'
                    ELSE 'Nurse'
                END AS Role
        FROM    NURSE n
        LEFT JOIN DAY_SISTER   ds ON n.Staff_No = ds.Staff_No
        LEFT JOIN NIGHT_SISTER ns ON n.Staff_No = ns.Staff_No
        LEFT JOIN STAFF_NURSE  sn ON n.Staff_No = sn.Staff_No
        LEFT JOIN NON_REG_NURSE nr ON n.Staff_No = nr.Staff_No
        WHERE   n.Ward_Name = ?
        ORDER BY Role, n.Name",
        [$wardInput]
    );

    $wardPatients = runQuery($conn, "
        SELECT  p.Patient_No, p.Patient_Name, p.Care_Unit_No, p.Bed_No,
                p.Date_Admitted, d.Name AS Consultant_Name
        FROM    PATIENT p
        JOIN    CARE_UNIT cu ON p.Care_Unit_No = cu.Care_Unit_No
        LEFT JOIN PATIENT_TREATMENT_RECORD ptr ON p.Patient_No    = ptr.Patient_No
        LEFT JOIN DOCTOR d                     ON ptr.Doctor_Staff_No = d.Staff_No
        WHERE   cu.Ward_Name = ?
        ORDER BY p.Patient_No",
        [$wardInput]
    );
}


// FORM 3 — CONSULTANT TEAM RECORD


$consultRecord   = null;
$consultExp      = null;
$consultProgress = null;
$teamMembers     = null;
$staffInput      = trim($_GET['staff_no'] ?? '');

if ($page === 'form_consultant' && $staffInput !== '') {
    $consultRecord = firstRow($conn, "
        SELECT  d.Staff_No, d.Name, d.Position, d.Date_Joined_Team, d.Specialty_Name,
                CASE WHEN c.Staff_No IS NOT NULL THEN 'Yes' ELSE 'No' END AS Is_Consultant
        FROM    DOCTOR d
        LEFT JOIN CONSULTANT c ON d.Staff_No = c.Staff_No
        WHERE   d.Staff_No = ?",
        [$staffInput]
    );

    if ($consultRecord && $consultRecord['Is_Consultant'] === 'Yes') {
        $consultExp = runQuery($conn,
            "SELECT Exp_ID, From_Date, To_Date, Establishment, Position
             FROM PREVIOUS_EXPERIENCE WHERE Staff_No = ? ORDER BY From_Date",
            [$staffInput]
        );
        $consultProgress = runQuery($conn,
            "SELECT Progress_ID, Establishment, Position, Date, Performance_Grade
             FROM PROGRESS WHERE Staff_No = ? ORDER BY Date",
            [$staffInput]
        );
        $teamMembers = runQuery($conn, "
            SELECT  d.Staff_No, d.Name, d.Position, d.Date_Joined_Team, d.Specialty_Name,
                    (SELECT TOP 1 Performance_Grade FROM PROGRESS
                     WHERE Staff_No = d.Staff_No ORDER BY Date DESC) AS Latest_Grade
            FROM    DOCTOR d
            WHERE   d.Consultant_Staff_No = ? AND d.Staff_No != ?
            ORDER BY d.Position, d.Name",
            [$staffInput, $staffInput]
        );
    } elseif ($consultRecord) {
        $consultExp = runQuery($conn,
            "SELECT Exp_ID, From_Date, To_Date, Establishment, Position
             FROM PREVIOUS_EXPERIENCE WHERE Staff_No = ? ORDER BY From_Date",
            [$staffInput]
        );
        $consultProgress = runQuery($conn,
            "SELECT Progress_ID, Establishment, Position, Date, Performance_Grade
             FROM PROGRESS WHERE Staff_No = ? ORDER BY Date",
            [$staffInput]
        );
    }
}


// QUERIES Q1 – Q12


$qResults = null;

// Q1 — Consultants & Their Teams
if ($page === 'q1') {
    $qResults = runQuery($conn, "
        SELECT  c.Staff_No     AS Consultant_No,
                c.Name         AS Consultant_Name,
                c.Specialty_Name,
                d.Staff_No     AS Doctor_No,
                d.Name         AS Doctor_Name,
                d.Position,
                d.Date_Joined_Team
        FROM    CONSULTANT cons
        JOIN    DOCTOR c ON cons.Staff_No         = c.Staff_No
        LEFT JOIN DOCTOR d ON d.Consultant_Staff_No = c.Staff_No
        WHERE   d.Staff_No IS NOT NULL AND d.Staff_No != c.Staff_No
        ORDER BY c.Name, d.Position, d.Name"
    );
}

// Q2 — Wards, Sisters & Staff Nurses
if ($page === 'q2') {
    $qResults = runQuery($conn, "
        SELECT DISTINCT
                w.Ward_Name,
                w.Specialty_Name,
                (SELECT TOP 1 n.Name FROM NURSE n
                    JOIN DAY_SISTER ds ON n.Staff_No = ds.Staff_No
                    WHERE n.Ward_Name = w.Ward_Name)  AS Day_Sister,
                (SELECT TOP 1 n.Name FROM NURSE n
                    JOIN NIGHT_SISTER ns ON n.Staff_No = ns.Staff_No
                    WHERE n.Ward_Name = w.Ward_Name)  AS Night_Sister,
                cu.Care_Unit_No,
                (SELECT TOP 1 n.Name FROM NURSE n
                    JOIN STAFF_NURSE sn ON n.Staff_No = sn.Staff_No
                    WHERE n.Care_Unit_No = cu.Care_Unit_No) AS Staff_Nurse_In_Charge
        FROM    WARD w
        JOIN    CARE_UNIT cu ON cu.Ward_Name = w.Ward_Name
        ORDER BY w.Ward_Name, cu.Care_Unit_No"
    );
}

// Q3 — Patients, Complaints & Treatments
if ($page === 'q3') {
    $qResults = runQuery($conn, "
        SELECT  p.Patient_No, p.Patient_Name,
                c.Complaint_Code, c.Description AS Complaint,
                t.Treatment_Code, t.Description AS Treatment,
                d.Name AS Doctor_Name,
                ptr.Date_Started, ptr.Date_Ended
        FROM    PATIENT p
        JOIN    PATIENT_TREATMENT_RECORD ptr ON p.Patient_No      = ptr.Patient_No
        JOIN    COMPLAINT c                  ON ptr.Complaint_Code = c.Complaint_Code
        JOIN    TREATMENT t                  ON ptr.Treatment_Code = t.Treatment_Code
        JOIN    DOCTOR    d                  ON ptr.Doctor_Staff_No = d.Staff_No
        ORDER BY p.Patient_Name"
    );
}

// Q4 — Junior Housemen & Their Patients
if ($page === 'q4') {
    $qResults = runQuery($conn, "
        SELECT DISTINCT
                d.Staff_No     AS JH_No,
                d.Name         AS Junior_Doctor,
                d.Position,
                p.Patient_No,
                p.Patient_Name,
                p.Care_Unit_No,
                sn.Name        AS Staff_Nurse_Of_CareUnit
        FROM    DOCTOR d
        JOIN    PATIENT_TREATMENT_RECORD ptr ON d.Staff_No    = ptr.Doctor_Staff_No
        JOIN    PATIENT p                    ON ptr.Patient_No = p.Patient_No
        LEFT JOIN NURSE       sn  ON sn.Care_Unit_No = p.Care_Unit_No
        LEFT JOIN STAFF_NURSE snr ON sn.Staff_No     = snr.Staff_No
        WHERE   d.Position IN ('junior houseman(jh)', 'student(s)')
        ORDER BY d.Name, p.Patient_Name"
    );
}

// Q5 — Consultants with Unique Specialty
if ($page === 'q5') {
    $qResults = runQuery($conn, "
        SELECT  d.Staff_No, d.Name AS Consultant_Name, d.Specialty_Name
        FROM    CONSULTANT c
        JOIN    DOCTOR d ON c.Staff_No = d.Staff_No
        WHERE   d.Specialty_Name IN (
            SELECT  d2.Specialty_Name
            FROM    CONSULTANT c2
            JOIN    DOCTOR d2 ON c2.Staff_No = d2.Staff_No
            GROUP BY d2.Specialty_Name
            HAVING COUNT(*) = 1
        )
        ORDER BY d.Specialty_Name"
    );
}

// Q6 — Complaints, Treatments & Doctor Experience
if ($page === 'q6') {
    $qResults = runQuery($conn, "
        SELECT DISTINCT
                c.Complaint_Code, c.Description AS Complaint,
                t.Description    AS Treatment,
                d.Staff_No       AS Doctor_No,
                d.Name           AS Doctor_Name,
                pe.Exp_ID, pe.From_Date, pe.To_Date,
                pe.Establishment,
                pe.Position      AS Experience_Position
        FROM    PATIENT_TREATMENT_RECORD ptr
        JOIN    COMPLAINT c  ON ptr.Complaint_Code  = c.Complaint_Code
        JOIN    TREATMENT t  ON ptr.Treatment_Code  = t.Treatment_Code
        JOIN    DOCTOR    d  ON ptr.Doctor_Staff_No  = d.Staff_No
        LEFT JOIN PREVIOUS_EXPERIENCE pe ON d.Staff_No = pe.Staff_No
        ORDER BY c.Complaint_Code, d.Name, pe.From_Date"
    );
}

// Q7 — Patients with Multiple Complaints
if ($page === 'q7') {
    $qResults = runQuery($conn, "
        SELECT  p.Patient_No, p.Patient_Name,
                c.Description AS Complaint,
                t.Description AS Treatment,
                ptr.Date_Started, ptr.Date_Ended
        FROM    PATIENT p
        JOIN    PATIENT_TREATMENT_RECORD ptr ON p.Patient_No      = ptr.Patient_No
        JOIN    COMPLAINT c                  ON ptr.Complaint_Code = c.Complaint_Code
        JOIN    TREATMENT t                  ON ptr.Treatment_Code = t.Treatment_Code
        WHERE   p.Patient_No IN (
            SELECT Patient_No FROM PATIENT_TREATMENT_RECORD
            GROUP BY Patient_No
            HAVING COUNT(DISTINCT Complaint_Code) > 1
        )
        ORDER BY p.Patient_No, c.Complaint_Code"
    );
}

// Q8 — Patients Grouped by Treatment within Complaint
if ($page === 'q8') {
    $qResults = runQuery($conn, "
        SELECT  c.Complaint_Code, c.Description AS Complaint,
                t.Treatment_Code, t.Description AS Treatment,
                p.Patient_No, p.Patient_Name
        FROM    PATIENT_TREATMENT_RECORD ptr
        JOIN    PATIENT   p ON ptr.Patient_No      = p.Patient_No
        JOIN    COMPLAINT c ON ptr.Complaint_Code  = c.Complaint_Code
        JOIN    TREATMENT t ON ptr.Treatment_Code  = t.Treatment_Code
        ORDER BY c.Description, t.Description, p.Patient_Name"
    );
}

// Q9 — Doctor Performance History (requires staff input)
$q9_staff = trim($_GET['q9_staff'] ?? '');
if ($page === 'q9' && $q9_staff !== '') {
    $qResults = runQuery($conn, "
        SELECT  d.Staff_No, d.Name AS Doctor_Name,
                d.Position         AS Current_Position,
                d.Specialty_Name,
                pr.Establishment,
                pr.Position        AS Role_At_Establishment,
                pr.Date,
                pr.Performance_Grade
        FROM    DOCTOR d
        JOIN    PROGRESS pr ON d.Staff_No = pr.Staff_No
        WHERE   d.Staff_No = ?
        ORDER BY pr.Date",
        [$q9_staff]
    );
}

// Q10 — Full Medical Details for a Patient (requires patient input)
$q10_pat = trim($_GET['q10_pat'] ?? '');
if ($page === 'q10' && $q10_pat !== '') {
    $qResults = runQuery($conn, "
        SELECT  p.Patient_No, p.Patient_Name, p.Date_of_Birth, p.Date_Admitted,
                w.Ward_Name, w.Specialty_Name, p.Care_Unit_No, p.Bed_No,
                d.Staff_No   AS Doctor_No,
                d.Name       AS Doctor_Name,
                CASE WHEN con.Staff_No IS NOT NULL THEN 'Yes' ELSE 'No' END AS Is_Consultant,
                c.Complaint_Code, c.Description AS Complaint,
                t.Treatment_Code, t.Description AS Treatment,
                ptr.Date_Started, ptr.Date_Ended
        FROM    PATIENT p
        JOIN    CARE_UNIT cu ON p.Care_Unit_No    = cu.Care_Unit_No
        JOIN    WARD w       ON cu.Ward_Name       = w.Ward_Name
        JOIN    PATIENT_TREATMENT_RECORD ptr ON p.Patient_No      = ptr.Patient_No
        JOIN    DOCTOR    d  ON ptr.Doctor_Staff_No = d.Staff_No
        LEFT JOIN CONSULTANT con ON d.Staff_No    = con.Staff_No
        JOIN    COMPLAINT c  ON ptr.Complaint_Code = c.Complaint_Code
        JOIN    TREATMENT t  ON ptr.Treatment_Code = t.Treatment_Code
        WHERE   p.Patient_No = ?
        ORDER BY ptr.Date_Started",
        [$q10_pat]
    );
}

// Q11 — Treatments by Complaint & Date Range (requires all three inputs)
$q11_cmp  = trim($_GET['q11_cmp']  ?? '');
$q11_from = trim($_GET['q11_from'] ?? '');
$q11_to   = trim($_GET['q11_to']   ?? '');
if ($page === 'q11' && $q11_cmp !== '' && $q11_from !== '' && $q11_to !== '') {
    $qResults = runQuery($conn, "
        SELECT  t.Treatment_Code, t.Description AS Treatment,
                c.Description AS Complaint,
                p.Patient_No, p.Patient_Name,
                d.Name        AS Doctor_Name,
                ptr.Date_Started, ptr.Date_Ended
        FROM    PATIENT_TREATMENT_RECORD ptr
        JOIN    TREATMENT t ON ptr.Treatment_Code  = t.Treatment_Code
        JOIN    COMPLAINT c ON ptr.Complaint_Code  = c.Complaint_Code
        JOIN    PATIENT   p ON ptr.Patient_No       = p.Patient_No
        JOIN    DOCTOR    d ON ptr.Doctor_Staff_No  = d.Staff_No
        WHERE   ptr.Complaint_Code = ?
          AND   ptr.Date_Started  >= ?
          AND   ptr.Date_Started  <= ?
        ORDER BY t.Description",
        [$q11_cmp, $q11_from, $q11_to]
    );
}

// Q12 — Staff Positions Count
if ($page === 'q12') {
    $qResults = runQuery($conn, "
        SELECT Position, COUNT(*) AS Staff_Count FROM DOCTOR GROUP BY Position
        UNION ALL SELECT 'Day Sister',            COUNT(*) FROM DAY_SISTER
        UNION ALL SELECT 'Night Sister',          COUNT(*) FROM NIGHT_SISTER
        UNION ALL SELECT 'Staff Nurse',           COUNT(*) FROM STAFF_NURSE
        UNION ALL SELECT 'Non-Registered Nurse',  COUNT(*) FROM NON_REG_NURSE
        ORDER BY Position"
    );
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Ivor Paine Memorial Hospital — DB System</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        :root {
            --hb:  #0a2342;
            --hbm: #1a4a7a;
            --acc: #c0392b;
            --ins: #0f5132;
        }

        body {
            background: #f4f6f9;
            font-family: 'Segoe UI', sans-serif;
        }

        /* ── Top bar ── */
        .topbar {
            background: linear-gradient(135deg, var(--hb) 0%, var(--hbm) 100%);
            color: #fff;
            padding: 0 1.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            height: 64px;
            box-shadow: 0 2px 8px rgba(0,0,0,.35);
            position: sticky;
            top: 0;
            z-index: 1000;
        }
        .topbar .logo { font-size: 1.5rem; }
        .topbar h1    { font-size: 1.15rem; margin: 0; font-weight: 700; }
        .topbar small { font-size: .7rem; opacity: .75; display: block; }

        /* ── Sidebar ── */
        #sidebar {
            width: 260px;
            min-height: calc(100vh - 64px);
            background: var(--hb);
            color: #cfd8ea;
            position: fixed;
            top: 64px;
            left: 0;
            bottom: 0;
            overflow-y: auto;
            z-index: 900;
        }
        #sidebar .sec-label {
            font-size: .65rem;
            font-weight: 700;
            text-transform: uppercase;
            color: #7fa8d4;
            padding: .9rem 1.2rem .3rem;
        }
        #sidebar a {
            display: flex;
            align-items: center;
            gap: .55rem;
            color: #cfd8ea;
            text-decoration: none;
            padding: .48rem 1.2rem .48rem 1.5rem;
            font-size: .83rem;
            border-left: 3px solid transparent;
        }
        #sidebar a:hover         { background: rgba(255,255,255,.07); color: #fff; }
        #sidebar a.active        { background: rgba(255,255,255,.12); color: #fff; border-left-color: #4fc3f7; }
        #sidebar a i             { width: 16px; text-align: center; }

        /* ── Main content ── */
        #main {
            margin-left: 260px;
            padding: 1.6rem 2rem;
            min-height: calc(100vh - 64px);
        }

        .page-title {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--hb);
            border-left: 4px solid #4fc3f7;
            padding-left: .75rem;
            margin-bottom: 1.2rem;
        }

        /* ── Cards ── */
        .info-card {
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 1px 6px rgba(0,0,0,.1);
            padding: 1.2rem 1.5rem;
            margin-bottom: 1rem;
        }
        .info-card h6 {
            color: var(--hbm);
            font-weight: 700;
            font-size: .78rem;
            text-transform: uppercase;
            margin-bottom: .6rem;
        }

        .kv-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: .35rem .8rem; }
        .kv-item .kv-k { font-size: .73rem; color: #888; font-weight: 600; text-transform: uppercase; }
        .kv-item .kv-v { font-size: .92rem; color: #2c3e50; font-weight: 500; }

        /* ── Search form ── */
        .search-form {
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 1px 6px rgba(0,0,0,.1);
            padding: 1rem 1.25rem;
            margin-bottom: 1.2rem;
            display: flex;
            flex-wrap: wrap;
            align-items: flex-end;
            gap: .75rem;
        }
        .search-form label { font-size: .8rem; font-weight: 600; color: var(--hb); }
        .btn-search {
            background: var(--hbm);
            color: #fff;
            border: none;
            padding: .42rem 1.1rem;
            border-radius: 5px;
            font-size: .85rem;
            display: flex;
            align-items: center;
            gap: .4rem;
        }
        .btn-search:hover { background: var(--hb); color: #fff; }

        /* ── Insert card & button ── */
        .ins-card {
            background: #fff;
            border-radius: 8px;
            border-left: 4px solid #2ecc71;
            box-shadow: 0 1px 6px rgba(0,0,0,.1);
            padding: 1.4rem 1.6rem;
            margin-bottom: 1.2rem;
        }
        .btn-insert {
            background: #1a6e3c;
            color: #fff;
            border: none;
            padding: .45rem 1.3rem;
            border-radius: 5px;
            display: inline-flex;
            align-items: center;
            gap: .45rem;
        }
        .btn-insert:hover { background: #155c32; color: #fff; }

        .auto-id-field {
            background: #e8f5e9 !important;
            border: 1px solid #a5d6a7 !important;
            color: #1b5e20 !important;
            font-weight: 700;
            cursor: not-allowed;
        }

        /* ── Tables ── */
        .table th { font-size: .75rem; text-transform: uppercase; background: var(--hb) !important; color: #fff !important; }
        .table td { font-size: .82rem; }

        /* ── Role badges ── */
        .badge-ds { background: #1565c0; color: white; padding: 2px 8px; border-radius: 12px; font-size: 11px; }
        .badge-ns { background: #6a1b9a; color: white; padding: 2px 8px; border-radius: 12px; font-size: 11px; }
        .badge-sn { background: #2e7d32; color: white; padding: 2px 8px; border-radius: 12px; font-size: 11px; }
        .badge-nr { background: #e65100; color: white; padding: 2px 8px; border-radius: 12px; font-size: 11px; }

        /* ── Dashboard stat cards ── */
        .dash-card {
            border-radius: 10px;
            padding: 1.2rem 1.5rem;
            color: #fff;
            box-shadow: 0 3px 10px rgba(0,0,0,.15);
            text-align: center;
        }
        .dash-card .dc-num { font-size: 2.2rem; font-weight: 800; }
        .dash-card .dc-lbl { font-size: .78rem; opacity: .85; }

        /* ── Misc ── */
        .required-star      { color: var(--acc); }
        .team-member-row:hover { background: #e8f0fb; }
        .preview-card       { background: #fff; border-radius: 8px; box-shadow: 0 1px 6px rgba(0,0,0,.1); padding: 1rem 1.25rem; }
        .preview-card h6    { color: var(--hbm); font-weight: 700; font-size: .78rem; text-transform: uppercase; margin-bottom: .6rem; }
        .text-success       { color: #2e7d32 !important; }
        .text-info          { color: #0d6efd !important; }
        .text-muted         { color: #6c757d !important; }
        .spinner-border-sm  { width: 1rem; height: 1rem; border-width: 0.15em; }
    </style>
</head>
<body>

<!-- TOP BAR -->
<div class="topbar">
    <div class="logo"><i class="fa-solid fa-hospital-user"></i></div>
    <div>
        <h1>Ivor Paine Memorial Hospital</h1>
        <small>Database Management System | CS204 Lab Project</small>
    </div>
    <?php if ($dbError): ?>
        <span class="ms-auto badge bg-danger">
            <i class="fa fa-exclamation-triangle me-1"></i>DB Offline
        </span>
    <?php else: ?>
        <span class="ms-auto badge bg-success">
            <i class="fa fa-circle me-1"></i>Connected
        </span>
    <?php endif; ?>
</div>

<!--  SIDEBAR -->
<nav id="sidebar">
    <div class="sec-label">Navigation</div>
    <a href="?page=dashboard" class="<?= $page === 'dashboard' ? 'active' : '' ?>">
        <i class="fa fa-gauge-high"></i> Dashboard
    </a>

    <div class="sec-label" style="margin-top:.5rem">Forms</div>
    <a href="?page=form_patient"    class="<?= $page === 'form_patient'    ? 'active' : '' ?>"><i class="fa fa-file-medical"></i>  Patient Record</a>
    <a href="?page=form_ward"       class="<?= $page === 'form_ward'       ? 'active' : '' ?>"><i class="fa fa-bed-pulse"></i>      Ward Record</a>
    <a href="?page=form_consultant" class="<?= $page === 'form_consultant' ? 'active' : '' ?>"><i class="fa fa-user-doctor"></i>    Consultant Team Record</a>

    <div class="sec-label" style="margin-top:.5rem">Data Entry</div>
    <a href="?page=ins_specialty" class="<?= $page === 'ins_specialty' ? 'active' : '' ?>"><i class="fa fa-stethoscope"></i>  Add Specialty</a>
    <a href="?page=ins_ward"      class="<?= $page === 'ins_ward'      ? 'active' : '' ?>"><i class="fa fa-hospital"></i>      Add Ward</a>
    <a href="?page=ins_bed"       class="<?= $page === 'ins_bed'       ? 'active' : '' ?>"><i class="fa fa-bed-pulse"></i>     Add Bed</a>
    <a href="?page=ins_doctor"    class="<?= $page === 'ins_doctor'    ? 'active' : '' ?>"><i class="fa fa-user-doctor"></i>   Add Doctor</a>
    <a href="?page=ins_nurse"     class="<?= $page === 'ins_nurse'     ? 'active' : '' ?>"><i class="fa fa-user-nurse"></i>    Add Nurse</a>
    <a href="?page=ins_patient"   class="<?= $page === 'ins_patient'   ? 'active' : '' ?>"><i class="fa fa-bed"></i>           Add Patient</a>

    <div class="sec-label" style="margin-top:.5rem">Reports &amp; Queries</div>
    <a href="?page=q1"  class="<?= $page === 'q1'  ? 'active' : '' ?>"><i class="fa fa-users-between-lines"></i> Q1 · Consultants &amp; Teams</a>
    <a href="?page=q2"  class="<?= $page === 'q2'  ? 'active' : '' ?>"><i class="fa fa-hospital"></i>            Q2 · Wards &amp; Sisters</a>
    <a href="?page=q3"  class="<?= $page === 'q3'  ? 'active' : '' ?>"><i class="fa fa-notes-medical"></i>       Q3 · Patients, Complaints &amp; Treatments</a>
    <a href="?page=q4"  class="<?= $page === 'q4'  ? 'active' : '' ?>"><i class="fa fa-user-graduate"></i>       Q4 · Junior Housemen &amp; Patients</a>
    <a href="?page=q5"  class="<?= $page === 'q5'  ? 'active' : '' ?>"><i class="fa fa-star"></i>                Q5 · Unique Specialty Consultants</a>
    <a href="?page=q6"  class="<?= $page === 'q6'  ? 'active' : '' ?>"><i class="fa fa-microscope"></i>          Q6 · Complaints, Treatments &amp; Experience</a>
    <a href="?page=q7"  class="<?= $page === 'q7'  ? 'active' : '' ?>"><i class="fa fa-list-check"></i>          Q7 · Patients w/ Multiple Complaints</a>
    <a href="?page=q8"  class="<?= $page === 'q8'  ? 'active' : '' ?>"><i class="fa fa-table-cells"></i>         Q8 · Patients by Treatment &amp; Complaint</a>
    <a href="?page=q9"  class="<?= $page === 'q9'  ? 'active' : '' ?>"><i class="fa fa-chart-line"></i>          Q9 · Doctor Performance History</a>
    <a href="?page=q10" class="<?= $page === 'q10' ? 'active' : '' ?>"><i class="fa fa-id-card-clip"></i>        Q10 · Full Medical Details</a>
    <a href="?page=q11" class="<?= $page === 'q11' ? 'active' : '' ?>"><i class="fa fa-calendar-days"></i>       Q11 · Treatments by Complaint &amp; Date</a>
    <a href="?page=q12" class="<?= $page === 'q12' ? 'active' : '' ?>"><i class="fa fa-chart-bar"></i>           Q12 · Staff Positions Count</a>
</nav>

<!--  MAIN CONTENT -->
<main id="main">

    <?php if ($dbError): ?>
        <div class="alert alert-danger">
            <i class="fa fa-triangle-exclamation me-2"></i> Database connection failed.
            <details><summary>Error details</summary><pre><?= h($dbError) ?></pre></details>
        </div>
    <?php endif; ?>

    <!-- DASHBOARD -->
    <?php if ($page === 'dashboard'): ?>
        <div class="page-title"><i class="fa fa-gauge-high me-2"></i>Dashboard</div>

        <?php
        $stats = [
            ['q' => 'SELECT COUNT(*) AS n FROM PATIENT',   'lbl' => 'Patients',   'icon' => 'fa-bed',         'bg' => '#1565c0'],
            ['q' => 'SELECT COUNT(*) AS n FROM DOCTOR',    'lbl' => 'Doctors',    'icon' => 'fa-user-doctor', 'bg' => '#2e7d32'],
            ['q' => 'SELECT COUNT(*) AS n FROM NURSE',     'lbl' => 'Nurses',     'icon' => 'fa-user-nurse',  'bg' => '#6a1b9a'],
            ['q' => 'SELECT COUNT(*) AS n FROM WARD',      'lbl' => 'Wards',      'icon' => 'fa-hospital',    'bg' => '#0288d1'],
            ['q' => 'SELECT COUNT(*) AS n FROM BED',       'lbl' => 'Beds',       'icon' => 'fa-bed-pulse',   'bg' => '#e65100'],
            ['q' => 'SELECT COUNT(*) AS n FROM SPECIALTY', 'lbl' => 'Specialties','icon' => 'fa-stethoscope', 'bg' => '#c62828'],
        ];
        ?>
        <div class="row g-3 mb-4">
            <?php foreach ($stats as $s): ?>
                <?php $r = firstRow($conn, $s['q']); $n = $r['n'] ?? '–'; ?>
                <div class="col-6 col-md-4 col-lg-2">
                    <div class="dash-card" style="background: <?= $s['bg'] ?>">
                        <div style="font-size:1.5rem; margin-bottom:.3rem">
                            <i class="fa <?= $s['icon'] ?>"></i>
                        </div>
                        <div class="dc-num"><?= h($n) ?></div>
                        <div class="dc-lbl"><?= h($s['lbl']) ?></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="row g-3">
            <div class="col-md-6">
                <div class="info-card">
                    <h6><i class="fa fa-clock-rotate-left me-1"></i>Recent Patient Admissions</h6>
                    <?php renderTable(runQuery($conn,
                        "SELECT TOP 5 Patient_No, Patient_Name, Date_Admitted
                         FROM PATIENT ORDER BY Date_Admitted DESC"
                    )); ?>
                </div>
            </div>
            <div class="col-md-6">
                <div class="info-card">
                    <h6><i class="fa fa-list me-1"></i>Specialty Overview</h6>
                    <?php renderTable(runQuery($conn, "
                        SELECT  s.Specialty_Name,
                                (SELECT COUNT(*) FROM WARD   w WHERE w.Specialty_Name = s.Specialty_Name) AS Wards,
                                (SELECT COUNT(*) FROM DOCTOR d WHERE d.Specialty_Name = s.Specialty_Name) AS Doctors
                        FROM    SPECIALTY s
                        ORDER BY s.Specialty_Name"
                    )); ?>
                </div>
            </div>
        </div>

    <!-- ADD SPECIALTY  -->
    <?php elseif ($page === 'ins_specialty'): ?>
        <div class="page-title"><i class="fa fa-stethoscope me-2"></i>Add New Specialty</div>

        <?php if ($insertMsg): ?>
            <div class="alert alert-<?= $insertMsg['type'] ?>"><?= $insertMsg['text'] ?></div>
        <?php endif; ?>

        <div class="ins-card">
            <form method="post">
                <div class="mb-3">
                    <label class="form-label">Specialty Name <span class="required-star">*</span></label>
                    <input type="text" name="specialty_name" class="form-control" required>
                </div>
                <button type="submit" class="btn-insert"><i class="fa fa-plus"></i> Save Specialty</button>
            </form>
        </div>

        <div class="preview-card">
            <h6>All Specialties</h6>
            <?php renderTable(runQuery($conn, "SELECT Specialty_Name FROM SPECIALTY ORDER BY Specialty_Name")); ?>
        </div>

    <!--  ADD WARD -->
    <?php elseif ($page === 'ins_ward'): ?>
        <div class="page-title"><i class="fa fa-hospital me-2"></i>Add New Ward</div>

        <?php if ($insertMsg): ?>
            <div class="alert alert-<?= $insertMsg['type'] ?>"><?= $insertMsg['text'] ?></div>
        <?php endif; ?>

        <div class="ins-card">
            <form method="post">
                <div class="mb-3">
                    <label class="form-label">Ward Name <span class="required-star">*</span></label>
                    <input type="text" name="ward_name" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Specialty <span class="required-star">*</span></label>
                    <select name="specialty_name" class="form-select" required>
                        <option value="">-- select specialty --</option>
                        <?php foreach ($specialtyList as $s): ?>
                            <option value="<?= h($s['Specialty_Name']) ?>"><?= h($s['Specialty_Name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Number of Care Units <span class="required-star">*</span></label>
                    <input type="number" name="num_care_units" class="form-control" min="1" max="10" value="2" required>
                    <small class="text-muted">Care units will be auto-generated.</small>
                </div>
                <button type="submit" class="btn-insert"><i class="fa fa-plus"></i> Save Ward</button>
            </form>
        </div>

        <div class="preview-card">
            <h6>All Wards</h6>
            <?php renderTable(runQuery($conn, "
                SELECT  w.Ward_Name, w.Specialty_Name, w.Number_Of_Care_Units,
                        (SELECT COUNT(*) FROM CARE_UNIT cu WHERE cu.Ward_Name = w.Ward_Name) AS Actual_Care_Units
                FROM    WARD w
                ORDER BY w.Ward_Name"
            )); ?>
        </div>

        <div class="preview-card mt-3">
            <h6>All Care Units</h6>
            <?php renderTable(runQuery($conn, "
                SELECT  cu.Care_Unit_No, cu.Ward_Name,
                        (SELECT COUNT(*)   FROM BED b WHERE b.Care_Unit_No = cu.Care_Unit_No)                    AS Total_Beds,
                        (SELECT COUNT(*)   FROM BED b WHERE b.Care_Unit_No = cu.Care_Unit_No AND b.Is_Occupied = 0) AS Available_Beds
                FROM    CARE_UNIT cu
                ORDER BY cu.Care_Unit_No"
            )); ?>
        </div>

    <!--  ADD BED -->
    <?php elseif ($page === 'ins_bed'): ?>
        <div class="page-title"><i class="fa fa-bed-pulse me-2"></i>Add New Bed</div>

        <?php if ($insertMsg): ?>
            <div class="alert alert-<?= $insertMsg['type'] ?>"><?= $insertMsg['text'] ?></div>
        <?php endif; ?>

        <div class="ins-card">
            <form method="post" id="bedForm">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Bed No</label>
                        <input type="text" name="bed_no" class="form-control auto-id-field"
                               value="<?= h($nextBedNo) ?>" readonly>
                        <small class="text-success">Auto-generated</small>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Select Ward <span class="required-star">*</span></label>
                        <select name="ward_name" class="form-select" required id="bedWardSelect">
                            <option value="">-- select ward --</option>
                            <?php foreach ($wardList as $w): ?>
                                <option value="<?= h($w['Ward_Name']) ?>"><?= h($w['Ward_Name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Care Unit <span class="required-star">*</span></label>
                        <select name="care_unit_no" class="form-select" required id="bedCUSelect" disabled>
                            <option value="">-- select ward first --</option>
                        </select>
                        <div id="bedCULoading" style="display:none;" class="mt-1 text-info">
                            <i class="fa fa-spinner fa-spin"></i> Loading care units...
                        </div>
                        <small class="text-muted">Select a ward first to see care units.</small>
                    </div>
                </div>
                <button type="submit" class="btn-insert mt-3"><i class="fa fa-plus"></i> Save Bed</button>
            </form>
        </div>

        <script>
            document.getElementById('bedWardSelect').addEventListener('change', function () {
                const wardName = this.value;
                const cuSelect  = document.getElementById('bedCUSelect');
                const cuLoading = document.getElementById('bedCULoading');

                if (wardName === '') {
                    cuSelect.innerHTML = '<option value="">-- select ward first --</option>';
                    cuSelect.disabled  = true;
                    cuLoading.style.display = 'none';
                    return;
                }

                cuSelect.innerHTML = '<option value="">Loading...</option>';
                cuSelect.disabled  = true;
                cuLoading.style.display = 'block';

                fetch('?ajax=get_care_units&ward=' + encodeURIComponent(wardName))
                    .then(r => r.json())
                    .then(data => {
                        cuLoading.style.display = 'none';
                        cuSelect.innerHTML = '<option value="">-- select care unit --</option>';
                        if (data.length === 0) {
                            cuSelect.innerHTML = '<option value="">-- no care units found for this ward --</option>';
                            cuSelect.disabled  = true;
                        } else {
                            data.forEach(cu => {
                                const opt   = document.createElement('option');
                                opt.value   = cu.Care_Unit_No;
                                opt.textContent = cu.Care_Unit_No;
                                cuSelect.appendChild(opt);
                            });
                            cuSelect.disabled = false;
                        }
                    })
                    .catch(err => {
                        cuLoading.style.display = 'none';
                        console.error('Error:', err);
                        cuSelect.innerHTML = '<option value="">-- error loading care units --</option>';
                        cuSelect.disabled  = true;
                    });
            });
        </script>

        <div class="preview-card">
            <h6>All Beds</h6>
            <?php renderTable(runQuery($conn, "
                SELECT  b.Bed_No, b.Care_Unit_No, cu.Ward_Name,
                        CASE WHEN b.Is_Occupied = 1 THEN 'Occupied' ELSE 'Available' END AS Status
                FROM    BED b
                JOIN    CARE_UNIT cu ON b.Care_Unit_No = cu.Care_Unit_No
                ORDER BY b.Bed_No"
            )); ?>
        </div>

    <!-- ADD PATIENT -->
    <?php elseif ($page === 'ins_patient'): ?>
        <div class="page-title"><i class="fa fa-bed me-2"></i>Admit New Patient</div>

        <?php if ($insertMsg): ?>
            <div class="alert alert-<?= $insertMsg['type'] ?>"><?= $insertMsg['text'] ?></div>
        <?php endif; ?>

        <div class="ins-card">
            <form method="post" id="patientForm">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Patient No</label>
                        <input type="text" name="patient_no" class="form-control auto-id-field"
                               value="<?= h($nextPatientNo) ?>" readonly>
                        <small class="text-success">Auto-generated</small>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Patient Name <span class="required-star">*</span></label>
                        <input type="text" name="patient_name" id="patientName" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Date of Birth</label>
                        <input type="date" name="dob" id="patientDob" class="form-control">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Date Admitted</label>
                        <input type="date" name="date_admitted" id="patientDateAdmitted" class="form-control">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Select Ward <span class="required-star">*</span></label>
                        <select class="form-select" required id="patientWardSelect">
                            <option value="">-- select ward --</option>
                            <?php foreach ($wardList as $w): ?>
                                <option value="<?= h($w['Ward_Name']) ?>"><?= h($w['Ward_Name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Care Unit <span class="required-star">*</span></label>
                        <select name="care_unit_no" class="form-select" required id="patientCUSelect" disabled>
                            <option value="">-- select ward first --</option>
                        </select>
                        <div id="patientCULoading" style="display:none;" class="mt-1 text-info">
                            <i class="fa fa-spinner fa-spin"></i> Loading care units...
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Available Bed <span class="required-star">*</span></label>
                        <select name="bed_no" class="form-select" required id="patientBedSelect" disabled>
                            <option value="">-- select care unit first --</option>
                        </select>
                        <small class="text-info">
                            <i class="fa fa-info-circle"></i> Only shows available (not occupied) beds.
                        </small>
                        <div id="bedLoading" style="display:none;" class="mt-1 text-info">
                            <i class="fa fa-spinner fa-spin"></i> Loading beds...
                        </div>
                    </div>
                </div>
                <button type="submit" class="btn-insert mt-3"><i class="fa fa-plus"></i> Admit Patient</button>
            </form>
        </div>

        <script>
            // Load Care Units when Ward is selected
            document.getElementById('patientWardSelect').addEventListener('change', function () {
                const wardName  = this.value;
                const cuSelect  = document.getElementById('patientCUSelect');
                const cuLoading = document.getElementById('patientCULoading');
                const bedSelect = document.getElementById('patientBedSelect');

                if (wardName === '') {
                    cuSelect.innerHTML  = '<option value="">-- select ward first --</option>';
                    cuSelect.disabled   = true;
                    cuLoading.style.display = 'none';
                    bedSelect.innerHTML = '<option value="">-- select care unit first --</option>';
                    bedSelect.disabled  = true;
                    return;
                }

                cuSelect.innerHTML = '<option value="">Loading...</option>';
                cuSelect.disabled  = true;
                cuLoading.style.display = 'block';

                fetch('?ajax=get_care_units&ward=' + encodeURIComponent(wardName))
                    .then(r => r.json())
                    .then(data => {
                        cuLoading.style.display = 'none';
                        cuSelect.innerHTML = '<option value="">-- select care unit --</option>';
                        if (data.length === 0) {
                            cuSelect.innerHTML = '<option value="">-- no care units found --</option>';
                            cuSelect.disabled  = true;
                        } else {
                            data.forEach(cu => {
                                const opt   = document.createElement('option');
                                opt.value   = cu.Care_Unit_No;
                                opt.textContent = cu.Care_Unit_No;
                                cuSelect.appendChild(opt);
                            });
                            cuSelect.disabled = false;
                        }
                        bedSelect.innerHTML = '<option value="">-- select care unit first --</option>';
                        bedSelect.disabled  = true;
                    })
                    .catch(err => {
                        cuLoading.style.display = 'none';
                        console.error('Error:', err);
                        cuSelect.innerHTML = '<option value="">-- error loading care units --</option>';
                        cuSelect.disabled  = true;
                    });
            });

            // Load Available Beds when Care Unit is selected
            document.getElementById('patientCUSelect').addEventListener('change', function () {
                const careUnitNo = this.value;
                const bedSelect  = document.getElementById('patientBedSelect');
                const bedLoading = document.getElementById('bedLoading');

                if (careUnitNo === '') {
                    bedSelect.innerHTML = '<option value="">-- select care unit first --</option>';
                    bedSelect.disabled  = true;
                    return;
                }

                bedSelect.innerHTML = '<option value="">Loading beds...</option>';
                bedSelect.disabled  = true;
                bedLoading.style.display = 'block';

                fetch('?ajax=get_available_beds&cu=' + encodeURIComponent(careUnitNo))
                    .then(r => r.json())
                    .then(data => {
                        bedLoading.style.display = 'none';
                        if (data.length === 0) {
                            bedSelect.innerHTML = '<option value="">-- no available beds in this care unit --</option>';
                            bedSelect.disabled  = true;
                        } else {
                            bedSelect.innerHTML = '<option value="">-- select bed --</option>';
                            data.forEach(bed => {
                                const opt   = document.createElement('option');
                                opt.value   = bed.Bed_No;
                                opt.textContent = bed.Bed_No;
                                bedSelect.appendChild(opt);
                            });
                            bedSelect.disabled = false;
                        }
                    })
                    .catch(err => {
                        bedLoading.style.display = 'none';
                        console.error('Error:', err);
                        bedSelect.innerHTML = '<option value="">-- error loading beds --</option>';
                        bedSelect.disabled  = true;
                    });
            });
        </script>

        <div class="preview-card">
            <h6>All Patients</h6>
            <?php renderTable(runQuery($conn, "
                SELECT  p.Patient_No, p.Patient_Name, p.Date_of_Birth, p.Date_Admitted,
                        cu.Ward_Name, p.Care_Unit_No, p.Bed_No
                FROM    PATIENT p
                JOIN    CARE_UNIT cu ON p.Care_Unit_No = cu.Care_Unit_No
                ORDER BY p.Date_Admitted DESC"
            )); ?>
        </div>

    <!--  ADD DOCTOR -->
    <?php elseif ($page === 'ins_doctor'): ?>
        <div class="page-title"><i class="fa fa-user-doctor me-2"></i>Add New Doctor</div>

        <?php if ($insertMsg): ?>
            <div class="alert alert-<?= $insertMsg['type'] ?>"><?= $insertMsg['text'] ?></div>
        <?php endif; ?>

        <div class="ins-card">
            <form method="post">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Staff No</label>
                        <input type="text" name="staff_no" class="form-control auto-id-field"
                               value="<?= h($nextDoctorNo) ?>" readonly>
                        <small class="text-success">Auto-generated</small>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Full Name <span class="required-star">*</span></label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Position</label>
                        <select name="position" class="form-select">
                            <option value="">-- select position --</option>
                            <option value="student(s)">student(s)</option>
                            <option value="junior houseman(jh)">junior houseman(jh)</option>
                            <option value="senior houseman(sh)">senior houseman(sh)</option>
                            <option value="assistant registrar(ar)">assistant registrar(ar)</option>
                            <option value="registrar(r)">registrar(r)</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Consultant (Team Lead)</label>
                        <select name="consultant_staff_no" class="form-select">
                            <option value="">-- none (self is consultant) --</option>
                            <?php foreach ($consultantList as $d): ?>
                                <option value="<?= h($d['Staff_No']) ?>">
                                    <?= h($d['Staff_No']) ?> - <?= h($d['Name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Date Joined Team</label>
                        <input type="date" name="date_joined" class="form-control">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Specialty <span class="required-star">*</span></label>
                        <select name="specialty_name" class="form-select" required>
                            <option value="">-- select specialty --</option>
                            <?php foreach ($specialtyList as $s): ?>
                                <option value="<?= h($s['Specialty_Name']) ?>"><?= h($s['Specialty_Name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-12">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox"
                                   name="is_consultant" id="chkCon" value="1">
                            <label class="form-check-label" for="chkCon">Register as Consultant</label>
                        </div>
                    </div>
                </div>
                <button type="submit" class="btn-insert mt-3"><i class="fa fa-plus"></i> Save Doctor</button>
            </form>
        </div>

        <div class="preview-card">
            <h6>All Doctors</h6>
            <?php renderTable(runQuery($conn, "
                SELECT  d.Staff_No, d.Name, d.Position, d.Specialty_Name, d.Date_Joined_Team,
                        CASE WHEN c.Staff_No IS NOT NULL THEN 'Yes' ELSE 'No' END AS Is_Consultant,
                        (SELECT Name FROM DOCTOR WHERE Staff_No = d.Consultant_Staff_No) AS Under_Consultant
                FROM    DOCTOR d
                LEFT JOIN CONSULTANT c ON d.Staff_No = c.Staff_No
                ORDER BY d.Name"
            )); ?>
        </div>

    <!-- ADD NURSE -->
    <?php elseif ($page === 'ins_nurse'): ?>
        <div class="page-title"><i class="fa fa-user-nurse me-2"></i>Add New Nurse</div>

        <?php if ($insertMsg): ?>
            <div class="alert alert-<?= $insertMsg['type'] ?>"><?= $insertMsg['text'] ?></div>
        <?php endif; ?>

        <div class="ins-card">
            <form method="post">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Staff No</label>
                        <input type="text" name="staff_no" class="form-control auto-id-field"
                               value="<?= h($nextNurseNo) ?>" readonly>
                        <small class="text-success">Auto-generated</small>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Full Name <span class="required-star">*</span></label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Ward <span class="required-star">*</span></label>
                        <select name="ward_name" class="form-select" required>
                            <option value="">-- select ward --</option>
                            <?php foreach ($wardList as $w): ?>
                                <option value="<?= h($w['Ward_Name']) ?>"><?= h($w['Ward_Name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Care Unit <span class="required-star">*</span></label>
                        <select name="care_unit_no" class="form-select" required>
                            <option value="">-- select care unit --</option>
                            <?php foreach ($careUnitList as $cu): ?>
                                <option value="<?= h($cu['Care_Unit_No']) ?>">
                                    <?= h($cu['Care_Unit_No']) ?> (<?= h($cu['Ward_Name']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Role <span class="required-star">*</span></label>
                        <select name="role" class="form-select" required>
                            <option value="">-- select role --</option>
                            <option value="day_sister">Day Sister</option>
                            <option value="night_sister">Night Sister</option>
                            <option value="staff_nurse">Staff Nurse</option>
                            <option value="non_reg">Non-Registered Nurse</option>
                        </select>
                    </div>
                </div>
                <button type="submit" class="btn-insert mt-3"><i class="fa fa-plus"></i> Save Nurse</button>
            </form>
        </div>

        <div class="preview-card">
            <h6>All Nurses</h6>
            <?php renderTable(runQuery($conn, "
                SELECT  n.Staff_No, n.Name, n.Ward_Name, n.Care_Unit_No,
                        CASE
                            WHEN ds.Staff_No IS NOT NULL THEN 'Day Sister'
                            WHEN ns.Staff_No IS NOT NULL THEN 'Night Sister'
                            WHEN sn.Staff_No IS NOT NULL THEN 'Staff Nurse'
                            WHEN nr.Staff_No IS NOT NULL THEN 'Non-Reg Nurse'
                            ELSE 'Unassigned'
                        END AS Role
                FROM    NURSE n
                LEFT JOIN DAY_SISTER    ds ON n.Staff_No = ds.Staff_No
                LEFT JOIN NIGHT_SISTER  ns ON n.Staff_No = ns.Staff_No
                LEFT JOIN STAFF_NURSE   sn ON n.Staff_No = sn.Staff_No
                LEFT JOIN NON_REG_NURSE nr ON n.Staff_No = nr.Staff_No
                ORDER BY n.Ward_Name, Role, n.Name"
            )); ?>
        </div>

    <!--  FORM 1 — PATIENT RECORD-->
    <?php elseif ($page === 'form_patient'): ?>
        <div class="page-title"><i class="fa fa-file-medical me-2"></i>Patient Record</div>

        <form class="search-form" method="get">
            <input type="hidden" name="page" value="form_patient">
            <div>
                <label>Patient No</label>
                <input type="text" name="patient_no" class="form-control"
                       value="<?= h($patientInput) ?>" style="width:200px">
            </div>
            <button type="submit" class="btn-search"><i class="fa fa-search"></i> Search</button>
        </form>

        <?php if ($patientInput !== '' && $patientRecord === null): ?>
            <div class="alert alert-warning">Patient not found.</div>
        <?php endif; ?>

        <?php if ($patientRecord): ?>
            <div class="info-card">
                <h6>Patient Information</h6>
                <div class="kv-grid">
                    <?php foreach ([
                        'Patient No'    => 'Patient_No',
                        'Patient Name'  => 'Patient_Name',
                        'Date of Birth' => 'Date_of_Birth',
                        'Date Admitted' => 'Date_Admitted',
                        'Ward'          => 'Ward_Name',
                        'Specialty'     => 'Specialty_Name',
                        'Care Unit'     => 'Care_Unit_No',
                        'Bed No'        => 'Bed_No',
                    ] as $lbl => $key): ?>
                        <div class="kv-item">
                            <div class="kv-k"><?= h($lbl) ?></div>
                            <div class="kv-v"><?= h($patientRecord[$key] ?? '—') ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="info-card">
                <h6>Medical History</h6>
                <?php renderTable($patientHistory); ?>
            </div>
        <?php endif; ?>

    <!-- FORM 2 — WARD RECORD-->
    <?php elseif ($page === 'form_ward'): ?>
        <div class="page-title"><i class="fa fa-bed-pulse me-2"></i>Ward Record</div>

        <form class="search-form" method="get">
            <input type="hidden" name="page" value="form_ward">
            <div>
                <label>Ward Name</label>
                <select name="ward_name" class="form-select" style="width:200px">
                    <option value="">-- select ward --</option>
                    <?php foreach ($wardList as $w): ?>
                        <option value="<?= h($w['Ward_Name']) ?>"
                            <?= $wardInput === $w['Ward_Name'] ? 'selected' : '' ?>>
                            <?= h($w['Ward_Name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn-search"><i class="fa fa-search"></i> Search</button>
        </form>

        <?php if ($wardRecord): ?>
            <div class="info-card">
                <h6>Ward Information</h6>
                <div class="kv-grid">
                    <div class="kv-item"><div class="kv-k">Ward Name</div>           <div class="kv-v"><?= h($wardRecord['Ward_Name']) ?></div></div>
                    <div class="kv-item"><div class="kv-k">Specialty</div>           <div class="kv-v"><?= h($wardRecord['Specialty_Name']) ?></div></div>
                    <div class="kv-item"><div class="kv-k">Number of Care Units</div><div class="kv-v"><?= h($wardRecord['Number_Of_Care_Units']) ?></div></div>
                </div>
            </div>

            <div class="info-card">
                <h6>Nursing Staff</h6>
                <?php
                $roleColors = [
                    'Day Sister'    => 'badge-ds',
                    'Night Sister'  => 'badge-ns',
                    'Staff Nurse'   => 'badge-sn',
                    'Non-Reg Nurse' => 'badge-nr',
                ];
                if ($wardNurses && count($wardNurses)): ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-bordered table-sm">
                            <thead class="table-dark">
                                <tr><th>Staff No</th><th>Name</th><th>Role</th><th>Care Unit</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($wardNurses as $n):
                                    $cls = $roleColors[$n['Role']] ?? 'bg-secondary'; ?>
                                    <tr>
                                        <td><?= h($n['Staff_No']) ?></td>
                                        <td><?= h($n['Name']) ?></td>
                                        <td><span class="badge <?= $cls ?>"><?= h($n['Role']) ?></span></td>
                                        <td><?= h($n['Care_Unit_No']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-warning">No nursing staff found.</div>
                <?php endif; ?>
            </div>

            <div class="info-card">
                <h6>Patient Information</h6>
                <?php renderTable($wardPatients); ?>
            </div>
        <?php endif; ?>

    <!--  FORM 3 — CONSULTANT TEAM RECORD -->
    <?php elseif ($page === 'form_consultant'): ?>
        <div class="page-title"><i class="fa fa-user-doctor me-2"></i>Consultant Team Record</div>

        <form class="search-form" method="get">
            <input type="hidden" name="page" value="form_consultant">
            <div>
                <label>Consultant Staff No</label>
                <select name="staff_no" class="form-select" style="width:250px">
                    <option value="">-- select consultant --</option>
                    <?php foreach ($consultantList as $d): ?>
                        <option value="<?= h($d['Staff_No']) ?>"
                            <?= $staffInput === $d['Staff_No'] ? 'selected' : '' ?>>
                            <?= h($d['Staff_No']) ?> - <?= h($d['Name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn-search"><i class="fa fa-search"></i> Search</button>
        </form>

        <?php if ($staffInput !== '' && $consultRecord === null): ?>
            <div class="alert alert-warning">Staff not found.</div>
        <?php endif; ?>

        <?php if ($consultRecord): ?>
            <div class="info-card">
                <h6>Consultant Information</h6>
                <div class="kv-grid">
                    <?php foreach ([
                        'Staff No'        => 'Staff_No',
                        'Name'            => 'Name',
                        'Position'        => 'Position',
                        'Date Joined Team'=> 'Date_Joined_Team',
                        'Specialty'       => 'Specialty_Name',
                    ] as $lbl => $key): ?>
                        <div class="kv-item">
                            <div class="kv-k"><?= h($lbl) ?></div>
                            <div class="kv-v"><?= h($consultRecord[$key] ?? '—') ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <?php if ($consultRecord['Is_Consultant'] === 'Yes'): ?>
                <div class="info-card">
                    <h6>Doctors in Team</h6>
                    <?php if ($teamMembers && count($teamMembers) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-bordered table-sm">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Staff No</th><th>Name</th><th>Position</th>
                                        <th>Date Joined Team</th><th>Specialty</th><th>Latest Grade</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($teamMembers as $tm): ?>
                                        <tr class="team-member-row">
                                            <td><?= h($tm['Staff_No']) ?></td>
                                            <td><?= h($tm['Name']) ?></td>
                                            <td><?= h($tm['Position']) ?></td>
                                            <td><?= h($tm['Date_Joined_Team']) ?></td>
                                            <td><?= h($tm['Specialty_Name']) ?></td>
                                            <td><?= h($tm['Latest_Grade'] ?? '—') ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">No doctors assigned to this consultant's team.</div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <div class="info-card">
                <h6>Previous Experience</h6>
                <?php renderTable($consultExp); ?>
            </div>
            <div class="info-card">
                <h6>Progress / Performance History</h6>
                <?php renderTable($consultProgress); ?>
            </div>
        <?php endif; ?>

    <!--   QUERIES Q1 – Q12-->
    <?php elseif (in_array($page, ['q1','q2','q3','q4','q5','q6','q7','q8','q9','q10','q11','q12'])): ?>

        <?php
        $qTitles = [
            'q1'  => 'Q1 - Consultants & Their Teams',
            'q2'  => 'Q2 - Wards, Sisters & Staff Nurses',
            'q3'  => 'Q3 - Patients, Complaints & Treatments',
            'q4'  => 'Q4 - Junior Housemen & Their Patients',
            'q5'  => 'Q5 - Consultants with Unique Specialty',
            'q6'  => 'Q6 - Complaints, Treatments & Doctor Experience',
            'q7'  => 'Q7 - Patients with Multiple Complaints',
            'q8'  => 'Q8 - Patients Grouped by Treatment within Complaint',
            'q9'  => 'Q9 - Doctor Performance History',
            'q10' => 'Q10 - Full Medical Details for a Patient',
            'q11' => 'Q11 - Treatments by Complaint & Date Range',
            'q12' => 'Q12 - Staff Positions & Count',
        ];
        ?>
        <div class="page-title"><i class="fa fa-table me-2"></i><?= $qTitles[$page] ?></div>

        <!-- Q9 filter -->
        <?php if ($page === 'q9'): ?>
            <form class="search-form" method="get">
                <input type="hidden" name="page" value="q9">
                <div>
                    <label>Doctor (Staff No)</label>
                    <select name="q9_staff" class="form-select" style="width:250px">
                        <option value="">-- select doctor --</option>
                        <?php foreach (runQuery($conn, "SELECT Staff_No, Name FROM DOCTOR ORDER BY Name") as $d): ?>
                            <option value="<?= h($d['Staff_No']) ?>"
                                <?= $q9_staff === $d['Staff_No'] ? 'selected' : '' ?>>
                                <?= h($d['Staff_No']) ?> - <?= h($d['Name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn-search"><i class="fa fa-search"></i> Run Query</button>
            </form>
        <?php endif; ?>

        <!-- Q10 filter -->
        <?php if ($page === 'q10'): ?>
            <form class="search-form" method="get">
                <input type="hidden" name="page" value="q10">
                <div>
                    <label>Patient No</label>
                    <select name="q10_pat" class="form-select" style="width:250px">
                        <option value="">-- select patient --</option>
                        <?php foreach ($patientList as $p): ?>
                            <option value="<?= h($p['Patient_No']) ?>"
                                <?= $q10_pat === $p['Patient_No'] ? 'selected' : '' ?>>
                                <?= h($p['Patient_No']) ?> - <?= h($p['Patient_Name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn-search"><i class="fa fa-search"></i> Run Query</button>
            </form>
        <?php endif; ?>

        <!-- Q11 filter -->
        <?php if ($page === 'q11'): ?>
            <form class="search-form" method="get">
                <input type="hidden" name="page" value="q11">
                <div>
                    <label>Complaint</label>
                    <select name="q11_cmp" class="form-select" style="width:300px">
                        <option value="">-- select complaint --</option>
                        <?php foreach ($complaintList as $c): ?>
                            <option value="<?= h($c['Complaint_Code']) ?>"
                                <?= $q11_cmp === $c['Complaint_Code'] ? 'selected' : '' ?>>
                                <?= h($c['Complaint_Code']) ?> - <?= h(substr($c['Description'], 0, 40)) ?>...
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label>From Date</label>
                    <input type="date" name="q11_from" class="form-control" value="<?= h($q11_from) ?>">
                </div>
                <div>
                    <label>To Date</label>
                    <input type="date" name="q11_to" class="form-control" value="<?= h($q11_to) ?>">
                </div>
                <button type="submit" class="btn-search"><i class="fa fa-search"></i> Run Query</button>
            </form>
        <?php endif; ?>

        <?php
        $showQuery = !(
            ($page === 'q9'  && $q9_staff === '') ||
            ($page === 'q10' && $q10_pat  === '') ||
            ($page === 'q11' && ($q11_cmp === '' || $q11_from === '' || $q11_to === ''))
        );
        ?>
        <?php if (!$showQuery && in_array($page, ['q9', 'q10', 'q11'])): ?>
            <div class="alert alert-info">Fill in the fields above and click Run Query.</div>
        <?php else: ?>
            <div class="info-card"><?php renderTable($qResults); ?></div>
        <?php endif; ?>

    <?php endif; ?>

</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>