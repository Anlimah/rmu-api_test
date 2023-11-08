<?php

namespace Src\Controller;

use Src\Controller\ExposeDataController;
use Src\Controller\VoucherPurchase;
use Predis\Client;

class APIEndpointHandler
{
    private $expose = null;
    private $redis = null;

    public function __construct()
    {
        $this->expose = new ExposeDataController();
        $this->redis = new Client();
    }

    public function authenticateAccess($username, $password)
    {
        return $this->expose->verifyAPIAccess($username, $password);
    }

    public function verifyRequestParam($param, $value): bool
    {
        if (isset($param[$value]) && !empty($param[$value])) return true;
        return false;
    }

    public function validateRequestParam($validateFunction, $param, $value): bool
    {
        if ($this->expose->$validateFunction($param[$value])) return true;
        return false;
    }

    public function checkCompanyCode($externalTransID, $api_user): mixed
    {
        $companyCode = substr($externalTransID, 0, 3);
        return $this->expose->fetchCompanyIDByCode($companyCode, $api_user);
    }

    public function getForms($payload, $api_user): mixed
    {
        if (empty($payload)) return array("resp_code" => "701", "message" => "Request body has no parameters.");
        if (!$this->verifyRequestParam($payload, "branch_name"))
            return array("resp_code" => "706", "message" => "Missing branch name in request body parameters.");
        if (!$this->validateRequestParam("validateInput", $payload, "branch_name"))
            return array("resp_code" => "707", "message" => "Invalid branch name in request body parameters.");

        $redisGetForms = $this->redis->get('getForms');
        if (!empty($redisGetForms)) {
            $data = unserialize($redisGetForms);
        } else {
            $data = $this->expose->getAllAvaialbleForms();
            $this->redis->set("getForms", serialize($data));
        }

        if (empty($data)) return array("resp_code" => "801", "message" => "Forms are currently unavailable.");
        $this->expose->activityLogger(json_encode($data), $payload["branch_name"] . " - getForms", $api_user);
        return array("resp_code" => "001", "message" => "successful", "data" => $data);
    }

    public function purchaseStatus($payload, $api_user): mixed
    {
        if (empty($payload)) return array("resp_code" => "701", "message" => "Request body has no parameters.");

        if (!$this->verifyRequestParam($payload, "ext_trans_id"))
            return array("resp_code" => "702", "message" => "Missing external transaction ID (ext_trans_id) in request body parameters.");
        if (!$this->validateRequestParam("validateInput", $payload, "ext_trans_id"))
            return array("resp_code" => "703", "message" => "Invalid external transaction ID (ext_trans_id) in request body parameters.");

        $extTransLen = strlen($payload["ext_trans_id"]);
        if ($extTransLen >= 15 && $extTransLen >= 20)
            return array("resp_code" => "704", "message" => "Invalid external transaction ID (ext_trans_id) length.");

        if (!$this->checkCompanyCode($payload["ext_trans_id"], $api_user))
            return array("resp_code" => "705", "message" => "Invalid external transaction ID (ext_trans_id) code.");

        if (!$this->verifyRequestParam($payload, "branch_name"))
            return array("resp_code" => "706", "message" => "Missing branch name in request body parameters.");
        if (!$this->validateRequestParam("validateInput", $payload, "branch_name"))
            return array("resp_code" => "707", "message" => "Invalid branch name in request body parameters.");

        $status = $this->expose->getPurchaseStatusByExtransID($payload["ext_trans_id"]);
        if (empty($status)) return array("resp_code" => "802", "message" => "No record found for this transaction ID.");

        $this->expose->activityLogger(json_encode($status[0]), "{$payload['ext_trans_id']} - getPurchaseStatusByExtransID", $api_user);
        return array("resp_code" => "001", "message" => "successful", "data" => $status[0]);
    }

    public function purchaseInfo($payload, $api_user): mixed
    {
        if (empty($payload)) return array("resp_code" => "701", "message" => "Request body has no parameters.");

        if (!$this->verifyRequestParam($payload, "ext_trans_id"))
            return array("resp_code" => "702", "message" => "Missing external transaction ID (ext_trans_id) in request body parameters.");
        if (!$this->validateRequestParam("validateInput", $payload, "ext_trans_id"))
            return array("resp_code" => "703", "message" => "Invalid external transaction ID (ext_trans_id) in request body parameters.");

        $extTransLen = strlen($payload["ext_trans_id"]);
        if ($extTransLen >= 15 && $extTransLen >= 20)
            return array("resp_code" => "704", "message" => "Invalid external transaction ID (ext_trans_id) length.");

        if (!$this->checkCompanyCode($payload["ext_trans_id"], $api_user))
            return array("resp_code" => "705", "message" => "Invalid external transaction ID (ext_trans_id) code.");

        if (!$this->verifyRequestParam($payload, "branch_name"))
            return array("resp_code" => "706", "message" => "Missing branch name in request body parameters.");
        if (!$this->validateRequestParam("validateInput", $payload, "branch_name"))
            return array("resp_code" => "707", "message" => "Invalid branch name in request body parameters.");

        $purchaseInfo = $this->expose->getPurchaseInfoByExtransID($payload["ext_trans_id"]);
        $this->expose->activityLogger(json_encode($purchaseInfo), "{$payload['ext_trans_id']} - getPurchaseInfoByExtransID", $api_user);

        if (empty($purchaseInfo)) return array("resp_code" => "802", "message" => "No record found for this transaction ID.");
        return array("resp_code" => "001", "message" => "successful", "data" => $purchaseInfo[0]);
    }

    public function purchaseForm($payload, $api_user): mixed
    {
        if (empty($payload)) return array("resp_code" => "701", "message" => "Request body has no parameters.");

        if (!$this->verifyRequestParam($payload, "ext_trans_id"))
            return array("resp_code" => "702", "message" => "Missing external transaction ID (ext_trans_id) in request body parameters.");
        if (!$this->validateRequestParam("validateInput", $payload, "ext_trans_id"))
            return array("resp_code" => "703", "message" => "Invalid external transaction ID (ext_trans_id) in request body parameters.");

        $extTransLen = strlen($payload["ext_trans_id"]);
        if ($extTransLen <= 15 && $extTransLen >= 20)
            return array("resp_code" => "704", "message" => "Invalid external transaction ID (ext_trans_id) length.");

        if (!$this->checkCompanyCode($payload["ext_trans_id"], $api_user))
            return array("resp_code" => "705", "message" => "Invalid external transaction ID (ext_trans_id) code.");

        if (!$this->verifyRequestParam($payload, "branch_name"))
            return array("resp_code" => "706", "message" => "Missing branch name in request body parameters.");
        if (!$this->validateRequestParam("validateInput", $payload, "branch_name"))
            return array("resp_code" => "707", "message" => "Invalid branch name in request body parameters.");

        if (!$this->verifyRequestParam($payload, "form_type"))
            return array("resp_code" => "708", "message" => "Missing form type in request body parameters.");
        if (!$this->validateRequestParam("validateText", $payload, "form_type"))
            return array("resp_code" => "709", "message" => "Invalid form type in request body parameters.");

        if (!$this->verifyRequestParam($payload, "customer_first_name"))
            return array("resp_code" => "710", "message" => "Missing customer first name in request body parameters.");
        if (!$this->validateRequestParam("validateName", $payload, "customer_first_name"))
            return array("resp_code" => "711", "message" => "Invalid customer first name in request body parameters.");

        if (!$this->verifyRequestParam($payload, "customer_last_name"))
            return array("resp_code" => "712", "message" => "Missing customer last name in request body parameters.");
        if (!$this->validateRequestParam("validateName", $payload, "customer_last_name"))
            return array("resp_code" => "713", "message" => "Invalid customer last name in request body parameters.");

        if (isset($payload["customer_email_address"])) {
            if (!$this->verifyRequestParam($payload, "customer_email_address"))
                return array("resp_code" => "714", "message" => "Missing customer email address in request body parameters.");
            if (!$this->validateRequestParam("validateEmail", $payload, "customer_email_address"))
                return array("resp_code" => "715", "message" => "Invalid customer email address in request body parameters.");
        }

        if (!$this->verifyRequestParam($payload, "customer_phone_number"))
            return array("resp_code" => "716", "message" => "Missing customer phone number in request body parameters.");
        if (!$this->validateRequestParam("validatePhone", $payload, "customer_phone_number"))
            return array("resp_code" => "717", "message" => "Invalid customer phone number in request body parameters.");

        if (!$this->verifyRequestParam($payload, "trans_dt"))
            return array("resp_code" => "718", "message" => "Missing transaction datetime in request body parameters.");
        if (!$this->validateRequestParam("validateDateTime", $payload, "trans_dt"))
            return array("resp_code" => "719", "message" => "Invalid transaction datetime in request body parameters.");

        if (!empty($this->expose->verifyExternalTransID($payload["ext_trans_id"], $api_user)))
            return array("resp_code" => "803", "message" => "Duplicate transaction request.");

        $formInfo = $this->expose->getFormDetailsByFormName($payload["form_type"]);
        if (empty($formInfo)) return array("resp_code" => "804", "message" => "Invalid form type.");

        $vendorID = $this->expose->getVendorIdByAPIUser($api_user);
        if (empty($vendorID)) return array("resp_code" => "805", "message" => "Failed fetching vendor details for processing.");

        $adminPeriod = $this->expose->getCurrentAdmissionPeriodID();
        if (empty($adminPeriod)) return array("resp_code" => "806", "message" => "Admission is currently closed.");

        $data['branch']         = $payload["branch_name"];
        $data['first_name']     = $payload["customer_first_name"];
        $data['last_name']      = $payload["customer_last_name"];
        $data['phone_number']   = $payload["customer_phone_number"];
        $data['ext_trans_id']   = $payload["ext_trans_id"];
        $data["ext_trans_dt"]   = $payload["trans_dt"];
        $data['form_id']        = $formInfo[0]["id"];
        $data['email_address']  = isset($payload["customer_email_address"]) ? $payload["customer_email_address"] : "";
        $data['country_name']   = "Ghana";
        $data['country_code']   = "+233";
        $data['amount']         = $formInfo[0]["amount"];
        $data['vendor_id']      = $vendorID[0]["vendor_id"];
        $data['pay_method']     = "CASH";
        $trans_id               = time();
        $data['admin_period']   = $adminPeriod[0]["id"];

        $voucher = new VoucherPurchase();

        $saved = $voucher->SaveFormPurchaseData($data, $trans_id);
        $this->expose->activityLogger(json_encode($data), "{$payload['ext_trans_id']} - SaveFormPurchaseData", $api_user);
        if (!$saved["success"]) return array("resp_code" => $saved["resp_code"], "message" => $saved["message"]);

        $loginGenrated = $voucher->genLoginsAndSend($saved["message"]);
        $this->expose->activityLogger(json_encode($saved), "{$payload['ext_trans_id']} - genLoginsAndSend", $api_user);
        if (!$loginGenrated["success"]) return array("resp_code" => $loginGenrated["resp_code"], "message" => $loginGenrated["message"]);

        $loginData = $voucher->getApplicantLoginInfoByTransID($loginGenrated["transID"])[0];
        $this->expose->activityLogger(json_encode($loginData), "{$payload['ext_trans_id']} - getApplicantLoginInfoByTransID", $api_user);

        $response = array("resp_code" => "001", "message" => "successful", "data" => $loginData);
        return $response;
    }

    public function sendPurchaseInfo($payload, $api_user): mixed
    {
        if (empty($payload)) return array("resp_code" => "701", "message" => "Request body has no parameters.");

        if (!$this->verifyRequestParam($payload, "ext_trans_id"))
            return array("resp_code" => "702", "message" => "Missing external transaction ID (ext_trans_id) in request body parameters.");
        if (!$this->validateRequestParam("validateInput", $payload, "ext_trans_id"))
            return array("resp_code" => "703", "message" => "Invalid external transaction ID (ext_trans_id) in request body parameters.");

        $extTransLen = strlen($payload["ext_trans_id"]);
        if ($extTransLen >= 15 && $extTransLen >= 15)
            return array("resp_code" => "704", "message" => "Invalid external transaction ID (ext_trans_id) length.");

        if (!$this->checkCompanyCode($payload["ext_trans_id"], $api_user))
            return array("resp_code" => "705", "message" => "Invalid external transaction ID (ext_trans_id) code.");

        if (!$this->verifyRequestParam($payload, "branch_name"))
            return array("resp_code" => "706", "message" => "Missing branch name in request body parameters.");
        if (!$this->validateRequestParam("validateInput", $payload, "branch_name"))
            return array("resp_code" => "707", "message" => "Invalid branch name in request body parameters.");

        $purchaseInfo = $this->expose->getPurchaseInfoByExtransID($payload["ext_trans_id"]);
        if (empty($purchaseInfo)) return array("resp_code" => "802", "message" => "No record found for this transaction ID.");
        $this->expose->activityLogger(json_encode($purchaseInfo), "{$payload['ext_trans_id']} - getPurchaseInfoByExtransID", $api_user);

        $message = 'Your RMU Online Application login details. ';
        $message .= 'APPLICATION NUMBER: ' . $purchaseInfo[0]['app_number'];
        $message .= '  PIN: ' . $purchaseInfo[0]['pin_number'] . ".";
        $message .= ' Follow the link, https://admissions.rmuictonline.com to start application process.';
        $to = "+233" . $purchaseInfo[0]["phone_number"];

        $response = json_decode($this->expose->sendSMS($to, $message));

        if (!$response->status) return array("resp_code" => "001", "message" => "successful");
        return array("resp_code" => "720", "message" => "Failed to send applicant login details via SMS.");
    }
}
