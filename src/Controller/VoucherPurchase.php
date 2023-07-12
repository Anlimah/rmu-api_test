<?php

namespace Src\Controller;

use Src\System\DatabaseMethods;
use Src\Controller\ExposeDataController;

class VoucherPurchase
{
    private $expose;
    private $dm;

    public function __construct()
    {
        $this->expose = new ExposeDataController();
        $this->dm = new DatabaseMethods();
    }

    public function logActivity(int $user_id, $operation, $description)
    {
        $query = "INSERT INTO `activity_logs`(`user_id`, `operation`, `description`) VALUES (:u,:o,:d)";
        $params = array(":u" => $user_id, ":o" => $operation, ":d" => $description);
        $this->dm->inputData($query, $params);
    }

    private function genPin(int $length_pin = 9)
    {
        $str_result = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        return substr(str_shuffle($str_result), 0, $length_pin);
    }

    private function genAppNumber(int $type, int $year)
    {
        $user_code = $this->expose->genCode(5);
        $app_number = ($type * 10000000) + ($year * 100000) + $user_code;
        return $app_number;
    }

    private function doesCodeExists($code)
    {
        $query = "SELECT `id` FROM `applicants_login` WHERE `app_number`=:p";
        if ($this->dm->getID($query, array(':p' => sha1($code)))) return 1;
        return 0;
    }

    private function saveVendorPurchaseData(int $ti, $et, $br, int $vd, int $fi, int $ap, $pm, float $am, $fn, $ln, $em, $cn, $cc, $pn, $td)
    {
        $query = "INSERT INTO `purchase_detail` (`id`, `ext_trans_id`, `sold_by`, `vendor`, `form_id`, `admission_period`, `payment_method`, `first_name`, `last_name`, `email_address`, `country_name`, `country_code`, `phone_number`, `amount`, `ext_trans_datetime`) 
                VALUES(:ti, :et, :br, :vd, :fi, :ap, :pm, :fn, :ln, :em, :cn, :cc, :pn, :am, :td)";
        $params = array(
            ':ti' => $ti, ':et' => $et, ':br' => $br, ':vd' => $vd, ':fi' => $fi, ':pm' => $pm, ':ap' => $ap,
            ':fn' => $fn, ':ln' => $ln, ':em' => $em, ':cn' => $cn, ':cc' => $cc, ':pn' => $pn, ':am' => $am, ':td' => $td
        );
        if ($this->dm->inputData($query, $params)) return $ti;
        return 0;
    }

    private function updateVendorPurchaseData(int $trans_id, int $app_number, $pin_number, $status)
    {
        $query = "UPDATE `purchase_detail` SET `app_number`= :a,`pin_number`= :p, `status` = :s WHERE `id` = :t";
        return $this->dm->getData($query, array(':a' => $app_number, ':p' => $pin_number, ':s' => $status, ':t' => $trans_id));
    }

    private function registerApplicantPersI($user_id)
    {
        $query = "INSERT INTO `personal_information` (`app_login`) VALUES(:a)";
        $this->dm->inputData($query, array(':a' => $user_id));
    }

    private function registerApplicantProgI($user_id)
    {
        $query = "INSERT INTO `program_info` (`app_login`) VALUES(:a)";
        $this->dm->inputData($query, array(':a' => $user_id));
    }

    private function registerApplicantPreUni($user_id)
    {
        $query = "INSERT INTO `previous_uni_records` (`app_login`) VALUES(:a)";
        $this->dm->inputData($query, array(':a' => $user_id));
    }

    private function setFormSectionsChecks($user_id)
    {
        $query = "INSERT INTO `form_sections_chek` (`app_login`) VALUES(:a)";
        $this->dm->inputData($query, array(':a' => $user_id));
    }

    private function setHeardAboutUs($user_id)
    {
        $query = "INSERT INTO `heard_about_us` (`app_login`) VALUES(:a)";
        $this->dm->inputData($query, array(':a' => $user_id));
    }

    private function getApplicantLoginID($app_number)
    {
        $query = "SELECT `id` FROM `applicants_login` WHERE `app_number` = :a;";
        return $this->dm->getID($query, array(':a' => sha1($app_number)));
    }

    private function createApplicantUser($app_number, $pin, $who)
    {
        $hashed_pin = password_hash($pin, PASSWORD_DEFAULT);
        $query = "INSERT INTO `applicants_login` (`app_number`, `pin`, `purchase_id`) VALUES(:a, :p, :b)";
        $params = array(':a' => sha1($app_number), ':p' => $hashed_pin, ':b' => $who);

        if ($this->dm->inputData($query, $params)) {
            $user_id = $this->getApplicantLoginID($app_number);

            //register in Personal information table in db
            $this->registerApplicantPersI($user_id);

            //register in Programs information
            $this->registerApplicantProgI($user_id);

            //register in Previous university information
            $this->registerApplicantPreUni($user_id);

            //Set initial form checks
            $this->setFormSectionsChecks($user_id);

            //Set initial form checks
            $this->setHeardAboutUs($user_id);

            return 1;
        }
        return 0;
    }

    private function genLoginDetails(int $type, int $year)
    {
        $rslt = 1;
        while ($rslt) {
            $app_num = $this->genAppNumber($type, $year);
            $rslt = $this->doesCodeExists($app_num);
        }
        $pin = strtoupper($this->genPin());
        return array('app_number' => $app_num, 'pin_number' => $pin);
    }

    //Get and Set IDs for foreign keys

    public function getVendorIDByTransactionID(int $trans_id)
    {
        return $this->dm->getData("SELECT vendor FROM purchase_detail WHERE id = :i", array(":i" => $trans_id));
    }

    public function SaveFormPurchaseData($data, $trans_id)
    {
        if (empty($data) && empty($trans_id)) return array(
            "success" => false, "resp_code" => "807", "message" => "Purchase data required!"
        );

        $fn = $data['first_name'];
        $ln = $data['last_name'];
        $em = $data['email_address'];
        $cn = $data['country_name'];
        $cc = $data['country_code'];
        $pn = $data['phone_number'];
        $et = $data['ext_trans_id'];
        $am = $data['amount'];
        $fi = $data['form_id'];
        $vd = $data['vendor_id'];
        $br = $data['branch'];
        $td = $data["ext_trans_dt"];

        if ($data['pay_method'] == 'MOM') $pay_method = "MOMO";
        else if ($data['pay_method'] == 'CRD') $pay_method = "CARD";
        else $pay_method = $data['pay_method'];

        $pm = $pay_method;
        $ap_id = $data['admin_period'];

        $purchase_id = $this->saveVendorPurchaseData($trans_id, $et, $br, $vd, $fi, $ap_id, $pm, $am, $fn, $ln, $em, $cn, $cc, $pn, $td);
        if (!$purchase_id) return array(
            "success" => false, "resp_code" => "808",  "message" => "Failed saving purchase data!"
        );

        return array("success" => true, "message" => $purchase_id);
    }

    public function getTransactionStatusFromDB($trans_id)
    {
        $query = "SELECT `id`, `status` FROM `purchase_detail` WHERE `id` = :t";
        return $this->dm->getData($query, array(':t' => $trans_id));
    }

    public function updateTransactionStatusInDB($status, $trans_id)
    {
        $query = "UPDATE `purchase_detail` SET `status` = :s WHERE `id` = :t";
        return $this->dm->getData($query, array(':s' => $status, ':t' => $trans_id));
    }

    private function getAppPurchaseData(int $trans_id)
    {
        $query = "SELECT f.`form_category`, pd.`country_code`, pd.`phone_number`, pd.`email_address` 
                FROM `purchase_detail` AS pd, forms AS f WHERE pd.`id` = :t AND f.`id` = pd.`form_id`";
        return $this->dm->getData($query, array(':t' => $trans_id));
    }

    public function genLoginsAndSend(int $trans_id)
    {
        $dataArray = $this->getAppPurchaseData($trans_id);

        if (empty($dataArray)) return array(
            "success" => false, "resp_code" => "809",  "message" => "Failed fetching purchase information"
        );

        $data = $dataArray[0];
        $app_type = 0;

        if ($data["form_category"] >= 2) $app_type = 1;
        else if ($data["form_category"] == 1) $app_type = 2;

        $app_year = $this->expose->getAdminYearCode();
        $login_details = $this->genLoginDetails($app_type, $app_year);

        if ($this->createApplicantUser($login_details['app_number'], $login_details['pin_number'], $trans_id)) {

            if ($this->updateVendorPurchaseData($trans_id, $login_details['app_number'], $login_details['pin_number'], 'COMPLETED')) {
                $vendor_id = $this->getVendorIDByTransactionID($trans_id);

                $this->logActivity(
                    $vendor_id[0]["vendor"],
                    "INSERT",
                    "Vendor {$vendor_id[0]["vendor"]} sold form with transaction ID {$trans_id}"
                );

                return array("success" => true, "transID" => $trans_id);
            } else {
                return array("success" => false, "resp_code" => "810",  "message" => "Failed to update login details!");
            }
        } else {
            return array("success" => false, "resp_code" => "811",  "message" => "Failed to save generated login details!");
        }
    }

    public function getApplicantLoginInfoByTransID($trans_id)
    {
        $query = "SELECT CONCAT('RMU-', `app_number`) AS app_number, `pin_number`, `ext_trans_id`, `ext_trans_datetime` AS trans_dt 
                FROM `purchase_detail` WHERE `id` = :t";
        return $this->dm->getData($query, array(':t' => $trans_id));
    }
}
