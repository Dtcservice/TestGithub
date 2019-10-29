<?php
include('..//../class/class.php');
ini_set('memory_limit', '1024M');
ini_set('max_execution_time', 40000);
$obj = new main_class();

$sim_info = array();
$opr = $_REQUEST["getOpr"];
$bill_s_month = $_REQUEST["getYear"]."-".$_REQUEST["getMonth"]."-01";
$bill_e_month = $obj->get_date_lastday($bill_s_month);


$filename = 'Summary '.$_REQUEST["getYear"].'-'.$_REQUEST["getMonth"].' - '.$opr.'.csv';
header('Content-Encoding: UTF-8');
header('Content-type: application/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename='.$filename);
$fp = fopen('php://output', 'w');
fputs( $fp, "\xEF\xBB\xBF" );

?>
T

<?php
if ($opr == "all_opr") {
	$opr = "";
}

// -------------------------------------------------------------------------------------------------------------------------------
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


	$sql = "DELETE FROM tbtemp_erp_sim";
	$obj->getQuery139($sql,0);

	$sql_getErpStatus = "SELECT ms_erp.EquipmentCode, ms_erp.Status, 
	LEFT(obj_ref.CREATED_ON,8) AS CREATED_ON,
	LEFT(info_erp.CANCELED_ON,8) AS CANCELED_ON
		FROM DTC_V_SIM_ERP_DETAIL AS ms_erp
		LEFT JOIN	(SELECT MAX(CREATED_ON) AS CREATED_ON, EquipmentCode
					FROM DTC_V_SIM_RefDoc
					GROUP BY EquipmentCode
					) AS obj_ref ON obj_ref.EquipmentCode = ms_erp.EquipmentCode
		LEFT JOIN	(SELECT MAX(CANCELED_ON) AS maxCANCELED_ON, EquipmentCode
					FROM DTC_V_SIM_ERP_DETAIL
					WHERE ObjectID IS NOT NULL
					GROUP BY EquipmentCode
					) AS obj_erp ON obj_erp.EquipmentCode = ms_erp.EquipmentCode
		LEFT JOIN (SELECT EquipmentCode, CANCELED_ON
					FROM DTC_V_SIM_ERP_DETAIL
					GROUP BY EquipmentCode, CANCELED_ON
					) AS info_erp ON info_erp.EquipmentCode = obj_erp.EquipmentCode 
					AND info_erp.CANCELED_ON = obj_erp.maxCANCELED_ON
		WHERE ms_erp.EquipmentType = 'SIM'
		AND ms_erp.EquipmentCode NOT LIKE 'LC%'
		AND ms_erp.EquipmentCode NOT LIKE 'LED%'
		GROUP BY ms_erp.EquipmentCode, obj_ref.CREATED_ON, ms_erp.Status, info_erp.CANCELED_ON
		ORDER BY ms_erp.EquipmentCode DESC";
	$get_ErpStatus = $obj->getQueryERP($sql_getErpStatus,1);

	$sql = "INSERT INTO tbtemp_erp_sim (sim_no, status, create_on, cancel_on)
	VALUES ";
	foreach ($get_ErpStatus["info"] as $b) {
		$sql .= $syst."('".$b["EquipmentCode"]."','".$b["Status"]."','".$b["CREATED_ON"]."','".$b["CANCELED_ON"]."')";
		$syst = ",";
	}
	$obj->getQuery139($sql,1);

	$sql_getCosting = "SELECT opr.sim_operator_name, opr.sim_operator_group, opr.sim_group, opr.sim_no, 
	SUM(opr.sim_package_charge) AS sim_package_charge, SUM(opr.sim_billing_charge_tt) AS sim_billing_charge_tt,
	opr.sim_credit_limit, opr.sim_data_used, opr.sim_sms_used, closed.max_date, closed.action, tmp.status, tmp.create_on, tmp.cancel_on
	FROM tb_siminfo_operator_s AS opr 
	LEFT JOIN (	SELECT ms.sim_no, max.max_date, ms.action 
			   FROM tb_sim_closed AS ms 
			   INNER JOIN (SELECT sim_no, MAX(contact_date) as max_date 
						   FROM tb_sim_closed
						   GROUP BY sim_no) AS max ON max.sim_no = ms.sim_no AND max.max_date = ms.contact_date 
			   GROUP BY ms.sim_no, max.max_date, ms.action) AS closed ON opr.sim_no = closed.sim_no
	LEFT JOIN tbtemp_erp_sim AS tmp ON opr.sim_no = tmp.sim_no
	WHERE opr.billing_month = '$bill_e_month'
	AND opr.sim_operator_name LIKE '%$opr%'
	GROUP BY opr.sim_operator_name, opr.sim_operator_group, opr.sim_group, opr.sim_no,
	opr.sim_credit_limit, opr.sim_data_used, opr.sim_sms_used, closed.max_date, closed.action, tmp.status, tmp.create_on, tmp.cancel_on
	ORDER BY opr.sim_no DESC";
    $get_costing_opr = $obj->getQuery139($sql_getCosting,1);
    
    
    fputcsv($fp, $header);
    $getDataSim = array();
	$i = 0;
	foreach ($get_costing_opr["info"] as $a) {
		if ($a["status"]) {
            $chkOP = chkOverPackage($a["sim_package_charge"],$a["sim_billing_charge_tt"]);
            $chkCS = chkCreditStatus($a["sim_billing_charge_tt"],$a["sim_credit_limit"]);
            $chkPS = chkPaymentStatus($a,$bill_s_month,$bill_e_month);
            $chkIO1 = chkInformOpr($a["max_date"],$a["action"],$a["cancel_on"],$a["status"],1);
            $chkIO2 = chkInformOpr($a["max_date"],$a["action"],$a["cancel_on"],$a["status"],2);
            $chkU = chkUsed($a);
            $chkA = chkAge($a);

            $getDataSim["simg"] = $a["sim_group"];
            $getDataSim["simno"] = $a["sim_no"];
            $getDataSim["sim_package_charge"] = $a["sim_package_charge"];
            $getDataSim["sim_billing_charge_tt"] = $a["sim_billing_charge_tt"];
            $getDataSim["chkOverPackage"] = $chkOP;
            $getDataSim["sim_credit_limit"] = $a["sim_credit_limit"];
            $getDataSim["chkCreditStatus"] = $chkCS;
            $getDataSim["status"] = $a["status"];
            $getDataSim["chkPaymentStatus"] = $chkPS;
            $getDataSim["chkInformOpr1"] = $chkIO1;
            $getDataSim["chkInformOpr2"] = $chkIO2;
            $getDataSim["chkUsed"] = $chkU;
            $getDataSim["chkAge"] = $chkA;
            $getDataSim["sim_operator_name"] = $a["sim_operator_name"];
            $getDataSim["sim_operator_group"] = $a["sim_operator_group"];
            fputcsv($fp, $getDataSim);
		}
		else {
            $chkOP = chkOverPackage($a["sim_package_charge"],$a["sim_billing_charge_tt"]);
            $chkCS = chkCreditStatus($a["sim_billing_charge_tt"],$a["sim_credit_limit"]);
            $chkPS = chkPaymentStatus($a,$bill_s_month,$bill_e_month);
            $chkIO1 = chkInformOpr($a["max_date"],$a["action"],$a["cancel_on"],$a["status"],1);
            $chkIO2 = chkInformOpr($a["max_date"],$a["action"],$a["cancel_on"],$a["status"],2);
            $chkU = chkUsed($a);
            $chkA = chkAge($a);

            $getDataSim["simg"] = $a["sim_group"];
            $getDataSim["simno"] = $a["sim_no"];
            $getDataSim["sim_package_charge"] = $a["sim_package_charge"];
            $getDataSim["sim_billing_charge_tt"] = $a["sim_billing_charge_tt"];
            $getDataSim["chkOverPackage"] = $chkOP;
            $getDataSim["sim_credit_limit"] = $a["sim_credit_limit"];
            $getDataSim["chkCreditStatus"] = $chkCS;
            $getDataSim["status"] = "ไม่พบข้อมูลใน ERP";
            $getDataSim["chkPaymentStatus"] = "-";
            $getDataSim["chkInformOpr1"] = "-";
            $getDataSim["chkInformOpr2"] = "-";
            $getDataSim["chkUsed"] = $chkU;
            $getDataSim["chkAge"] = $chkA;
            $getDataSim["sim_operator_name"] = $a["sim_operator_name"];
            $getDataSim["sim_operator_group"] = $a["sim_operator_group"];
            fputcsv($fp, $getDataSim);
		}
		// usleep(100000); // debuging purpose
		// ob_flush();
		// flush();
		// echo $html_s;
    }
    
    fclose($fp);
    exit;
?>




<?php
// ฟังค์ชั้น สำหรับ ทำค่าใช้จ่ายซิม

function chkOverPackage($packCharge,$packChargeTT){
	if ($packChargeTT > $packCharge) {
		$value = $packChargeTT - $packCharge;
	}

	else {
		$value = 0;
	}
	return($value);
}

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

function chkInformOpr($date,$action,$scDate,$status,$modes){
	if ($date) {
		if ($modes == 1) {
			$value = $date;
		}
		else {
			$value = $action;
		}
	}
	else {
		if ($scDate) {
			if ($modes == 1) {
				$value = $scDate;
			}
			else {
				$value = "ยกเลิกตาม SC Date";
			}
		}
		else {
			if ($modes == 1) {
				$value = "ไม่พบข้อมูลการแจ้ง Opr";
			}
			else {
				$value = "ไม่พบข้อมูลการแจ้ง Opr";
			}
		}
	}
	return($value);
}

function chkPaymentStatus($a,$s,$e){
	$s = str_replace("-","",$s);
	$e = str_replace("-","",$e);
	
	if (!$a["status"]) {
		$value = "ไม่พบข้อมูลใน ERP";
	}
	else if (trim($a["status"]) == "C") {
		if ($a["sim_billing_charge_tt"] > 0) {
			if ($a["max_date"] != "") {
				if ($a["max_date"] < $s) {
					if (trim($a["action"]) == "ยกเลิกถาวร") {
						$value = "C/N (ยกเลิกก่อนรอบบิล)";
					}
					else if (trim($a["action"]) == "Reconnect Sim"){
						$value = "ทำจ่ายปกติ (เปิดสัญญาณก่อนรอบบิล / ตรวจสอบข้อมูล)";
					}
					else {
						$value = "ไม่พบเงื่อนไข (Check.1 !!)";
					}
				}
				else if ($a["max_date"] <= $e){
					if (trim($a["action"]) == "ยกเลิกถาวร") {
						$value = "ทำจ่ายปกติ (ยกเลิกหลังเริ่มรอบบิล)";
					}
					else if (trim($a["action"]) == "Reconnect Sim"){
						$value = "ทำจ่ายปกติ (เปิดสัญญาณหลังเริ่มรอบบิล / ตรวจสอบข้อมูล)";
					}
					else {
						$value = "ไม่พบเงื่อนไข (Check.2 !!)";
					}
				}
				else if ($a["max_date"] > $e){
					if (trim($a["action"]) == "ยกเลิกถาวร") {
						$value = "ทำจ่ายปกติ (ยกเลิกหลังเริ่มรอบบิล)";
					}
					else if (trim($a["action"]) == "Reconnect Sim"){
						$value = "ทำจ่ายปกติ (เปิดสัญญาณหลังเริ่มรอบบิล / ตรวจสอบข้อมูล)";
					}
					else {
						$value = "ไม่พบเงื่อนไข (Check.3 !!)";
					}
				}
				else {
					$value = "ไม่พบเงื่อนไข (Check.4 !!)";
				}
			}
			else {
				if ($a["cancel_on"] != "") {
					$value = "ทำจ่าย (ไม่พบข้อมูลแจ้ง Opr / ตรวจสอบข้อมูล / แจ้งปิดสัญญาณ)";
				}
				else {
					$value = "ทำจ่าย (ไม่พบการปิด End SC Date / ตรวจสอบข้อมูล / แจ้งปิดสัญญาณ)";
				}
			}
		}
		else {
			$value = "-";
		}
	}
	else {
		$value = "ทำจ่ายปกติ";
	}
	return $value;
}

function chkUsed($info){
	$info["sim_data_used"] = str_replace("KB.","",$info["sim_data_used"]);
	$info["sim_data_used"] = str_replace("MB.","",$info["sim_data_used"]);
	$info["sim_data_used"] = str_replace("GB.","",$info["sim_data_used"]);
	$info["sim_data_used"] = str_replace(" ","",$info["sim_data_used"]);

	if ($info["sim_data_used"] != '0KB' && $info["sim_data_used"] != '0' && $info["sim_data_used"] != '') {
		$value = "มีการใช้งาน";
	}
	else {
		$value = "ไม่มีการใช้งาน";
	}
	return $value;
}

function chkAge($info){
	$obj = new main_class();
	$date = date("Ym")."01";
	
	if ($info["create_on"] < date("Ym",strtotime($date."-1 month"))."01") {
		if ($info["create_on"] < date("Ym",strtotime($date."-4 month"))."01") {
			if ($info["create_on"] < date("Ym",strtotime($date."-7 month"))."01") {
				if ($info["create_on"] < date("Ym",strtotime($date."-10 month"))."01") {
					if ($info["create_on"] < date("Ym",strtotime($date."-13 month"))."01") {
						$value = "มากกว่า 12 เดือน";
					}
					else {
						$value = "10 ถึง 12 เดือน";
					}
				}
				else {
					$value = "7 ถึง 9 เดือน";
				}
			}
			else {
				$value = "4 ถึง 6 เดือน";
			}
		}
		else {
			$value = "1 ถึง 3 เดือน";
		}
	}
	else {
		$value = "ไม่ถึง 1 เดือน";
	}
	return $value;
}

function calMonth($date,$countOff){

}
?>
