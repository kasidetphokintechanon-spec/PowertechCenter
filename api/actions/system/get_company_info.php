<?php
// api/actions/system/get_company_info.php
// Returns company info for all 3 companies from system_settings

$defaults = [
    'PTA' => [
        'name_th'   => 'บริษัท พาวเวอร์เทค เอนจิ้น แอสเซมบลี จำกัด',
        'name_en'   => 'Powertech Engine Assembly Co., Ltd.',
        'address_th'=> '',
        'address_en'=> '',
        'phone'     => '',
        'fax'       => '',
        'tax_id'    => '',
    ],
    'PT4' => [
        'name_th'   => 'บริษัท พาวเวอร์เทค 2004 จำกัด',
        'name_en'   => 'Powertech 2004 Co., Ltd.',
        'address_th'=> '',
        'address_en'=> '',
        'phone'     => '',
        'fax'       => '',
        'tax_id'    => '',
    ],
    'PTE' => [
        'name_th'   => 'บริษัท พาวเวอร์เทค เอนเนอร์ยี โซลูชั่นส์ จำกัด',
        'name_en'   => 'Powertech Energy Solutions Co., Ltd.',
        'address_th'=> '',
        'address_en'=> '',
        'phone'     => '',
        'fax'       => '',
        'tax_id'    => '',
    ],
];

$result = [];
foreach (['PTA','PT4','PTE'] as $co) {
    $key  = 'company_info_' . $co;
    $stmt = $conn->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ? LIMIT 1");
    $stmt->bind_param("s", $key);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($row && $row['setting_value']) {
        $parsed = json_decode($row['setting_value'], true);
        $result[$co] = array_merge($defaults[$co], $parsed ?: []);
    } else {
        $result[$co] = $defaults[$co];
    }
}

echo json_encode(['status' => 'success', 'data' => $result]);
?>
