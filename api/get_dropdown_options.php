<?php
/**
 * get_dropdown_options.php
 * AJAX handler: Returns JSON for hierarchical dropdown population.
 * Actions: get_colleges | get_departments | get_batches
 * Access: All roles (Global API)
 */
require_once '../includes/auth_check.php';
require_once '../config/db_connect.php';
checkAuth();

header('Content-Type: application/json');

if (isset($_GET['action'])) {
    $action = $_GET['action'];

    if ($action == 'get_colleges') {
        // Fetch Unique Colleges
        $stmt = $pdo->query("SELECT DISTINCT college FROM departments 
                             WHERE college IS NOT NULL AND college != ''
                             ORDER BY college ASC");
        echo json_encode($stmt->fetchAll(PDO::FETCH_COLUMN));
        exit;
    }

    if ($action == 'get_departments') {
        if (isset($_GET['college']) && !empty($_GET['college'])) {
            $college = $_GET['college'];
            $stmt = $pdo->prepare("SELECT id, name, study_years FROM departments WHERE college = ? ORDER BY name ASC");
            $stmt->execute([$college]);
        } else {
            // Fallback or fetch all if no college selected? 
            // Ideally we shouldn't fetch all if college is required, but let's support it if needed or return empty.
            // Let's return all for now if no college, or maybe just return empty to force selection?
            // User wants hierarchy. If no college, maybe show nothing?
            // Let's return nothing or handle "All" case if implemented.
            // For safety:
            $stmt = $pdo->query("SELECT id, name, study_years FROM departments ORDER BY name ASC");
        }

        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Add display name logic if needed, but for now returned name is fine
        foreach ($result as &$row) {
            $row['display_name'] = $row['name'];
        }

        echo json_encode($result);
        exit;
    }

    if ($action == 'get_batches' && isset($_GET['department_id'])) {
        $dept_id = $_GET['department_id'];

        // Fetch department info including college to determine type
        $stmt = $pdo->prepare("SELECT name, study_years, college FROM departments WHERE id = ?");
        $stmt->execute([$dept_id]);
        $dept = $stmt->fetch(PDO::FETCH_ASSOC);
        $study_years = $dept['study_years'] ?? 4;
        $college = $dept['college'] ?? '';
        $deptName = $dept['name'] ?? '';

        // Default type
        $dept_type = 'regular';
        $batches = [];
        $semesters = [];

        if (stripos($deptName, 'Medicine') !== false || $deptName === 'ህክምና') {
            $batches = [1, 2, 3, 4, 5, 6, 7, 8];
            $semesters = [
                1 => [2],
                2 => [1, 2],
                3 => [1, 2],
                4 => [1, 2],
                5 => [1, 2],
                6 => [1, 2],
                7 => [1, 2],
                8 => [1, 2]
            ];
        } elseif (stripos($deptName, 'Law') !== false || $deptName === 'ህግ') {
            $batches = [1, 2, 3, 4, 5];
            $semesters = [
                1 => [2],
                2 => [1, 2],
                3 => [1, 2],
                4 => [1, 2],
                5 => [1, 2]
            ];
        } elseif (
            stripos($deptName, 'Software') !== false || 
            stripos($deptName, 'Electrical') !== false || 
            stripos($deptName, 'Mechanical') !== false || 
            stripos($deptName, 'Health Science') !== false || 
            stripos($deptName, 'Animal Science') !== false || 
            stripos($deptName, 'Veterinary') !== false || 
            stripos($deptName, 'ሶፍትዌር ምህንድስና') !== false ||
            stripos($deptName, 'ኤሌክትሪካል ምህንድስና') !== false ||
            stripos($deptName, 'ሜካኒካል ምህንድስና') !== false ||
            stripos($deptName, 'ጤና ሳይንስ') !== false ||
            stripos($deptName, 'እንስሳት ሳይንስ') !== false ||
            stripos($deptName, 'እንስሳት ህክምና') !== false
        ) {
            $batches = [2, 3, 4, 5];
            $semesters = [
                2 => [1, 2],
                3 => [1, 2],
                4 => [1, 2],
                5 => [1, 2]
            ];
        } elseif (stripos($college, 'Remedial') !== false || stripos($deptName, 'Remedial') !== false || stripos($deptName, 'ሪሚዲያል') !== false) {
            $dept_type = 'remedial';
            $batches = [];
            $semesters = [];
        } elseif (stripos($college, 'Freshman') !== false || stripos($deptName, 'Freshman') !== false || stripos($deptName, 'ፍሬሽማን') !== false) {
            $dept_type = 'freshman';
            $batches = [1];
            $semesters = [
                1 => [1, 2]
            ];
        } else {
            // Other departments
            $batches = [2, 3, 4];
            $semesters = [
                2 => [1, 2],
                3 => [1, 2],
                4 => [1, 2]
            ];
        }

        echo json_encode(['dept_type' => $dept_type, 'batches' => $batches, 'semesters' => $semesters]);
        exit;
    }

    // Get all distinct batches (no department required) - for independent Year of Study filter
    if ($action == 'get_all_batches') {
        $stmt = $pdo->query("SELECT DISTINCT batch FROM students WHERE batch IS NOT NULL ORDER BY batch ASC");
        $batches = $stmt->fetchAll(PDO::FETCH_COLUMN);
        // Convert to integers
        $batches = array_map('intval', $batches);
        echo json_encode(['batches' => $batches]);
        exit;
    }
}
?>