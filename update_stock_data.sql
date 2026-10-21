-- Stock items: category + unit update
-- Run this on production DB via phpMyAdmin

-- CATEGORY
UPDATE stock_items SET category='battery'  WHERE item_code LIKE '%-CHA%';
UPDATE stock_items SET category='cleaning' WHERE item_code LIKE '%-CLN%';
UPDATE stock_items SET category='bag'      WHERE item_code LIKE '%-BAG%';
UPDATE stock_items SET category='tool'     WHERE item_code LIKE '%-BRM%' OR item_code LIKE '%-MOP%' OR item_code LIKE '%-EQP%';
UPDATE stock_items SET category='beverage' WHERE item_code LIKE '%-BEV%';
UPDATE stock_items SET category='paper'    WHERE item_code LIKE '%-PAP%';
UPDATE stock_items SET category='office'   WHERE category IN ('other','0','') AND (
    item_code LIKE '%-SPN%' OR item_code LIKE '%-SPM%' OR item_code LIKE '%-SBS%' OR
    item_code LIKE '%-SGU%' OR item_code LIKE '%-SLP%' OR item_code LIKE '%-SDT%' OR
    item_code LIKE '%-STP%' OR item_code LIKE '%-SCT%' OR item_code LIKE '%-SCC%' OR
    item_code LIKE '%-SFC%' OR item_code LIKE '%-SSP%' OR item_code LIKE '%-SES%' OR
    item_code LIKE '%-SPC%' OR item_code LIKE '%-SRL%' OR item_code LIKE '%-SDC%'
);
UPDATE stock_items SET category='office' WHERE category IN ('other','0','') AND item_code LIKE '540-00-%';

-- UNIT
UPDATE stock_items SET unit='ก้อน'    WHERE item_code LIKE '%-CHA%';
UPDATE stock_items SET unit='แพ็ค'    WHERE item_code LIKE '%-BEV%';
UPDATE stock_items SET unit='ถุง'     WHERE item_code LIKE '%-BAG%';
UPDATE stock_items SET unit='ขวด'     WHERE item_code IN ('540-02-CLN001','540-02-CLN002','540-02-CLN003','540-02-CLN006','540-02-CLN008','540-02-CLN012','540-02-CLN013','540-02-CLN024','540-02-CLN025','540-02-CLN101');
UPDATE stock_items SET unit='ถุง'     WHERE item_code='540-02-CLN023';
UPDATE stock_items SET unit='ชิ้น'    WHERE item_code IN ('540-02-CLN109','540-02-CLN115');
UPDATE stock_items SET unit='แผ่น'    WHERE item_code IN ('540-02-CLN116','540-02-CLN117','540-02-CLN118','540-02-CLN119');
UPDATE stock_items SET unit='อัน'     WHERE item_code='540-02-CLN120';
UPDATE stock_items SET unit='กระป๋อง' WHERE item_code='540-02-CLN114';
UPDATE stock_items SET unit='ม้วน'    WHERE item_code='540-02-PAP001';
UPDATE stock_items SET unit='แพ็ค'    WHERE item_code='540-02-PAP002';
UPDATE stock_items SET unit='กล่อง'   WHERE item_code='540-02-PAP003';
UPDATE stock_items SET unit='อัน'     WHERE item_code LIKE '%-BRM%' OR item_code LIKE '%-EQP%' OR item_code IN ('540-02-MOP001','540-02-MOP002','540-02-MOP004');
UPDATE stock_items SET unit='ผืน'     WHERE item_code IN ('540-02-MOP003','540-02-MOP005','540-02-MOP006','540-02-MOP007');
UPDATE stock_items SET unit='ด้าม'    WHERE item_code LIKE '%-SPN%' OR item_code LIKE '%-SPM%' OR item_code LIKE '%-SPC015%';
UPDATE stock_items SET unit='เล่ม'    WHERE item_code LIKE '%-SBS%';
UPDATE stock_items SET unit='ก้อน'    WHERE item_code LIKE '%-SGU%';
UPDATE stock_items SET unit='ชิ้น'    WHERE item_code IN ('540-00-SES001','540-00-SLP002','540-00-SCT012');
UPDATE stock_items SET unit='ม้วน'    WHERE item_code IN ('540-00-SCT009','540-00-SCT010','540-00-SDT001','540-00-STP028');
UPDATE stock_items SET unit='อัน'     WHERE item_code IN ('540-00-SCT011','540-00-SRL002');
UPDATE stock_items SET unit='แผ่น'    WHERE item_code LIKE '%-SCC%';
UPDATE stock_items SET unit='แพ็ค'    WHERE item_code='540-00-SFC006';
UPDATE stock_items SET unit='ซอง'     WHERE item_code IN ('540-00-SFC012','540-00-SFC013');
UPDATE stock_items SET unit='กล่อง'   WHERE item_code LIKE '%-SSP%' OR item_code LIKE '%-SDC%' OR item_code IN ('540-00-SPC013','540-00-SPC014');
