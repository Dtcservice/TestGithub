<?php
include('..//../class/class.php');
$obj = new main_class();

$filename = 'Summary.csv';
header('Content-Encoding: UTF-8');
header('Content-type: application/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename='.$filename);
$fp = fopen('php://output', 'w');
    
    $header = array("กลุ่มซิม",
     "หมายเลขซิม", 
     "ค่าแพคเกจ" , 
     "ยอดรวมค่าใช้จ่าย" ,
     "ยอดเงินเกินแพคเกจ" , 
     "ยอด Credit Limit" , 
     "สถานะ Credit" ,
     "สถานะซิม" , 
     "สถานะบิล" , 
     "แจ้ง Opr ดำเนินการ" , 
     "วันที่แจ้ง Opr ดำเนินการ" , 
     "การใช้ข้อมูล",
     "อายุซิม",
     "ผู้ให้บริการ",
     "กลุ่มผู้ให้บริการ");

     $sql_getErpStatus = "SELECT *
     FROM main_org_posi";
     $get_ErpStatus = $obj->getQuery139($sql_getErpStatus,1);
        

$b = array();

fputs( $fp, "\xEF\xBB\xBF" );
fputcsv($fp, $header);
foreach ($get_ErpStatus["info"] as $a) {
    //  print_r($a);

    $test = chkCreditStatus(0,0);

     $b["sim"] = $a["pos_name_th"];
     $b["name"] = $a["status"];
     $b["test"] = $test;

     
    // print_r($b);
    // print_r($q);

 fputcsv($fp, $b);
 
}

// $query = "SELECT * FROM toy";
// $result = mysqli_query($conn, $query);
// while($row = mysqli_fetch_row($result)) {
// 	fputcsv($fp, $row);
// }
fclose($fp);
exit;


function chkCreditStatus($packChargeTT,$creditLimit){
	if ($creditLimit == 0) {
		$value = 'No Credit';
	}
	else if ($creditLimit > $packChargeTT) {
		$value = 'In Credit';
	}
	else {
		$value = 'Over Credit';
	}
	return($value);
}
?>
