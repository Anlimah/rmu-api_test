<?php

namespace Src\Controller;

use Src\Controller\ExposeDataController;
use Src\Controller\VoucherPurchase;
use Src\System\DatabaseMethods;

class APIEndpointHandler
{
    private $expose         = null;
    private $dm             = null;

    public function __construct()
    {
        $this->expose = new ExposeDataController();
        $this->dm = new DatabaseMethods();
    }

    public function run()
    {
        return false;
    }

    public function verifyAPIAccess($username, $password)
    {
        $sql = "SELECT * FROM `api_users` WHERE `username`=:u";
        $data = $this->dm->getData($sql, array(':u' => sha1($username)));
        if (!empty($data)) if (password_verify($password, $data[0]["password"])) return $data;
        return 0;
    }

    public function getForms()
    {
        return $this->dm->getData("SELECT `name` AS form, `amount` AS price FROM `forms`");
    }

    public function getTransactionStatusByExtransID($externalTransID)
    {
        $query = "SELECT `app_number`, `pin_number` FROM `purchase_detail` WHERE `ext_trans_id` = :t";
        return $this->dm->getData($query, array(':t' => $externalTransID));
    }

    public function verifyRequestData($data): bool
    {
        if (!isset($data["company_name"]) || empty($data["company_name"])) return false;
        if (!isset($data["form_type"]) || empty($data["form_type"])) return false;
        if (!isset($data["customer_first_name"]) || empty($data["customer_first_name"])) return false;
        if (!isset($data["customer_last_name"]) || empty($data["customer_last_name"])) return false;
        if (!isset($data["customer_phone_number"]) || empty($data["customer_phone_number"])) return false;
        if (!isset($data["ext_trans_id"]) || empty($data["ext_trans_id"])) return false;
        if (!isset($data["trans_dt"]) || empty($data["trans_dt"])) return false;
        return true;
    }

    public function validateRequestData($data): bool
    {
        if (!$this->expose->validateInput($data["company_name"])) return false;
        if (!$this->expose->validateInputTextOnly($data["form_type"])) return false;
        if (!$this->expose->validateInput($data["customer_first_name"])) return false;
        if (!$this->expose->validateInput($data["customer_last_name"])) return false;
        if (!$this->expose->validateInput($data["customer_phone_number"])) return false;
        if (!$this->expose->validateInputTextNumber($data["ext_trans_id"])) return false;
        if (!$this->expose->validateDate($data["trans_dt"])) return false;
        if (isset($data["customer_email_address"]) && !empty($data["customer_email_address"]))
            if (!$this->expose->validateEmail($data["customer_email_address"])) return false;
        return true;
    }

    public function getVendorIdByAPIUser($api_user)
    {
        $str = "SELECT `id` FROM `vendor_details` WHERE `api_user`=:a";
        return $this->dm->getID($str, array(':a' => $api_user));
    }

    public function handleAPIBuyForms($payload, $api_user)
    {
        if (!$this->verifyRequestData($payload)) return array("success" => false, "message" => "Request parameters not complete");
        if (!$this->validateRequestData($payload)) return array("success" => false, "message" => "Request parameters have invalid data");

        $vendor_id = $this->getVendorIdByAPIUser($api_user);
        $formInfo = $this->expose->getFormDetailsByFormName($payload["form_type"]);

        $data['first_name'] = $payload["customer_first_name"];
        $data['last_name'] = $payload["customer_last_name"];
        $data['email_address'] = isset($payload["customer_email_address"]) ? $payload["customer_email_address"] : "";
        $data['country_name'] = "Ghana";
        $data['country_code'] = "+233";
        $data['amount'] = $formInfo["amount"];
        $data['form_id'] = $formInfo["id"];
        $data['vendor_id'] = $vendor_id;
        $data['pay_method'] = "CASH";
        $trans_id = time();
        $data['admin_period'] = $this->expose->getCurrentAdmissionPeriodID();

        //save Data to database
        $voucher = new VoucherPurchase();
        $saved = $voucher->SaveFormPurchaseData($data, $trans_id);
        $this->expose->activityLogger(json_encode($data), "vendor", $api_user);
        if ($saved["success"]) $loginGenrated = $voucher->genLoginsAndSend($saved["message"]);
        $this->expose->activityLogger(json_encode($saved), "system", $api_user);
        if ($loginGenrated["success"]) $response = array("success" => true, "message" => "Successfull");
        $response["data"] = $voucher->getApplicantLoginInfoByTransID($loginGenrated["exttrid"]);
        return $response;
    }

    public function transactionStatus($data, $api_user)
    {
        if (!isset($data['ext_trans_id']) || empty($data['ext_trans_id']))
            return array("success" => false, "message" => "Request parameters not complete");
        if (!$this->expose->validateInputTextNumber($data["ext_trans_id"]))
            return array("success" => false, "message" => "Request parameters have invalid data");

        $status = $this->getTransactionStatusByExtransID($data["ext_trans_id"]);
        if (empty($status)) return array("success" => false, "message" => "No transaction matched this transaction ID");
        $this->expose->activityLogger(json_encode($status), "vendor", $api_user);
        return array("success" => true, "message" => "Successfull", "data" => $status);
    }
}
