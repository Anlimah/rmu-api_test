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
        if (!empty($data)) if (password_verify($password, $data[0]["password"])) return $data[0]["id"];
        return 0;
    }

    public function getForms($api_user)
    {
        $data = $this->dm->getData("SELECT `name` AS form, `amount` AS price FROM `forms`");
        $this->expose->activityLogger(json_encode($data), "vendor", $api_user);
        return $data;
    }

    public function getPurchaseStatusByExtransID($externalTransID)
    {
        $query = "SELECT `status`, `ext_trans_id` FROM `purchase_detail` WHERE `ext_trans_id` = :t";
        return $this->dm->getData($query, array(':t' => $externalTransID));
    }

    public function getPurchaseInfoByExtransID($externalTransID)
    {
        $query = "SELECT CONCAT('RMU-', `app_number`) AS app_number, `pin_number`, `ext_trans_id`  
                FROM `purchase_detail` WHERE `ext_trans_id` = :t";
        return $this->dm->getData($query, array(':t' => $externalTransID));
    }

    public function verifyRequestData($data): bool
    {
        if (!isset($data["branch"]) || empty($data["branch"])) return false;
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
        if (!$this->expose->validateInput($data["branch"])) return false;
        if (!$this->expose->validateText($data["form_type"])) return false;
        if (!$this->expose->validateInput($data["customer_first_name"])) return false;
        if (!$this->expose->validateInput($data["customer_last_name"])) return false;
        if (!$this->expose->validateInput($data["customer_phone_number"])) return false;
        if (!$this->expose->validateInput($data["ext_trans_id"])) return false;
        if (!$this->expose->validateDateTime($data["trans_dt"])) return false;
        if (isset($data["customer_email_address"]) && !empty($data["customer_email_address"]))
            if (!$this->expose->validateEmail($data["customer_email_address"])) return false;
        return true;
    }

    public function getVendorIdByAPIUser($api_user)
    {
        $query = "SELECT `id` FROM `vendor_details` WHERE `api_user`=:a";
        return $this->dm->getID($query, array(':a' => $api_user));
    }

    public function handleAPIBuyForms($payload, $api_user)
    {
        if (!$this->verifyRequestData($payload)) return array("success" => false, "message" => "Request parameters not complete");
        if (!$this->validateRequestData($payload)) return array("success" => false, "message" => "Request parameters have invalid data");

        $vendor_id = $this->getVendorIdByAPIUser($api_user);
        $formInfo = $this->expose->getFormDetailsByFormName($payload["form_type"])[0];

        $data['first_name'] = $payload["customer_first_name"];
        $data['last_name'] = $payload["customer_last_name"];
        $data['email_address'] = isset($payload["customer_email_address"]) ? $payload["customer_email_address"] : "";
        $data['country_name'] = "Ghana";
        $data['country_code'] = "+233";
        $data['phone_number'] = $payload["customer_phone_number"];
        $data['ext_trans_id'] = $payload["ext_trans_id"];
        $data['amount'] = $formInfo["amount"];
        $data['form_id'] = $formInfo["id"];
        $data['vendor_id'] = $vendor_id;
        $data['pay_method'] = "CASH";
        $trans_id = time();
        $data['admin_period'] = $this->expose->getCurrentAdmissionPeriodID();

        //save Data to database
        $voucher = new VoucherPurchase();
        $saved = $voucher->SaveFormPurchaseData($data, $trans_id);
        $this->expose->activityLogger(json_encode($data), "{$payload['ext_trans_id']} - SaveFormPurchaseData", $api_user);

        if ($saved["success"]) $loginGenrated = $voucher->genLoginsAndSend($saved["message"]);
        $this->expose->activityLogger(json_encode($saved), "{$payload['ext_trans_id']} - genLoginsAndSend", $api_user);

        if ($loginGenrated["success"]) $response = array("success" => true, "message" => "Successfull");
        $loginData = $voucher->getApplicantLoginInfoByTransID($loginGenrated["exttrid"])[0];
        $this->expose->activityLogger(json_encode($loginData), "{$payload['ext_trans_id']} - genLoginsAndSend", $api_user);

        $response["data"] = $loginData;
        return $response;
    }

    public function purchaseStatus($payload, $api_user)
    {
        if (!isset($payload['ext_trans_id']) || empty($payload['ext_trans_id']))
            return array("success" => false, "message" => "Request parameters not complete.");
        if (!$this->expose->validateInput($payload["ext_trans_id"]))
            return array("success" => false, "message" => "Request parameters have invalid data.");

        $status = $this->getPurchaseStatusByExtransID($payload["ext_trans_id"]);
        if (empty($status)) return array("success" => false, "message" => "No record matched this transaction ID.");

        $this->expose->activityLogger(json_encode($status[0]), "{$payload['ext_trans_id']} - getPurchaseStatusByExtransID", $api_user);
        return array("success" => true, "message" => "Successfull", "data" => $status[0]);
    }

    public function purchaseInfo($payload, $api_user)
    {
        if (!isset($payload['ext_trans_id']) || empty($payload['ext_trans_id']))
            return array("success" => false, "message" => "Request parameters not complete");
        if (!$this->expose->validateInput($payload["ext_trans_id"]))
            return array("success" => false, "message" => "Request parameters have invalid data");

        $purchaseInfo = $this->getPurchaseInfoByExtransID($payload["ext_trans_id"]);
        if (empty($purchaseInfo)) return array("success" => false, "message" => "No transaction matched this transaction ID");

        $this->expose->activityLogger(json_encode($purchaseInfo[0]), "{$payload['ext_trans_id']} - getPurchaseInfoByExtransID", $api_user);
        return array("success" => true, "message" => "Successfull", "data" => $purchaseInfo[0]);
    }
}
