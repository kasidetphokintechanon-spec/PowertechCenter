<?php
// ---- ใส่รหัสผ่านที่คุณต้องการตั้งในเครื่องหมาย ' ' ----
$passwordToHash = '123456'; 

echo "รหัสผ่านคือ: " . $passwordToHash . "<br><br>";
echo "รหัสผ่านที่เข้ารหัสแล้ว (Hash) คือ: <br>";
echo password_hash($passwordToHash, PASSWORD_DEFAULT);
?>