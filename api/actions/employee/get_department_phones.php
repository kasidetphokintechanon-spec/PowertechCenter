<?php
// api/actions/get_department_phones.php
$result = $conn->query("SELECT * FROM department_contacts");
$phones = [];
if ($result) {
    while($row = $result->fetch_assoc()) {
        $co = $row['company'];
        $dept = $row['department'];
        $sub = $row['sub_department'];
        $num = $row['phone'];
        if (!isset($phones[$co])) $phones[$co] = [];
        if (empty($sub)) $phones[$co][$dept] = $num;
        else {
            if (!isset($phones[$co][$dept]) || !is_array($phones[$co][$dept])) $phones[$co][$dept] = [];
            $phones[$co][$dept][$sub] = $num;
        }
    }
}
echo json_encode(['status' => 'success', 'data' => $phones]);
?>