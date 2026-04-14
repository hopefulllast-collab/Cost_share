<?php
$request_uri = $_SERVER['REQUEST_URI'] ?? '';
$referer = $_SERVER['HTTP_REFERER'] ?? '';
$active_role = null;

// Determine active role from current URL or Referer
$check_url = $request_uri;
if (strpos($check_url, '/api/') !== false || strpos($check_url, '/common/') !== false || strpos($check_url, 'logout.php') !== false) {
    if ($referer !== '') {
        $check_url = $referer;
    }
}

if (strpos($check_url, '/modules/student/') !== false) $active_role = 'student';
elseif (strpos($check_url, '/modules/registrar/') !== false) $active_role = 'registrar';
elseif (strpos($check_url, '/modules/admin/') !== false) $active_role = 'admin';
elseif (strpos($check_url, '/modules/academic_vp/') !== false) $active_role = 'academic_vp';
elseif (strpos($check_url, '/modules/cost_sharing/') !== false) $active_role = 'cost_sharing_pro';
elseif (strpos($check_url, '/modules/transcript/') !== false) $active_role = 'transcript_pro';
elseif (strpos($check_url, '/modules/department/') !== false) $active_role = 'department_head';

if ($active_role) {
    $expected_name = "DMU_" . strtoupper($active_role);
    if (session_status() === PHP_SESSION_ACTIVE) {
        if (session_name() !== $expected_name) {
            session_write_close();
            session_name($expected_name);
            session_start();
        }
    } else {
        session_name($expected_name);
        session_start();
    }
} else {
    // Attempt to detect existing session name from cookies
    $existing_session = null;
    foreach ($_COOKIE as $key => $value) {
        if (strpos($key, 'DMU_') === 0) {
            $existing_session = $key;
            break;
        }
    }
    
    if (session_status() === PHP_SESSION_NONE) {
        if ($existing_session) {
            session_name($existing_session);
        }
        session_start();
    }
}
?>
