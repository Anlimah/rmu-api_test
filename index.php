<?php

require_once('bootstrap.php');

use Src\Controller\USSDHandler;
use Src\Controller\PaymentController;
use Src\Controller\ExposeDataController;

//echo json_encode(array("uri" => $request_uri, "endpoint" => $endpoint));

switch ($_SERVER["REQUEST_METHOD"]) {
    case 'POST':
        $_POST = json_decode(file_get_contents("php://input"), true);
        $response = array();

        if (!empty($_POST)) {

            $request_uri = explode('?', $_SERVER['REQUEST_URI'], 2)[0];
            $endpoint = '/' . basename($request_uri);


            switch ($endpoint) {

                case 'forms':
                    break;

                case 'buy':
                    break;

                case 'status':
                    break;

                case 'ussd':

                    $payData = array();

                    if (!empty($_POST)) $response = (new USSDHandler($_POST))->run();

                    if (isset($response["data"])) $payData = $response["data"];

                    if (!empty($payData)) {
                        sleep(8);
                        (new PaymentController())->orchardPaymentControllerB($payData);
                    }

                    break;

                case 'confirm':

                    $expose = new ExposeDataController();
                    $expose->requestLogger(json_encode($_POST));

                    if (!empty($_POST)) {
                        $transaction_id = $expose->validatePhone($_POST["trans_ref"]);
                        $data = $expose->confirmPurchase($transaction_id);
                        $expose->requestLogger($transaction_id . " - " . $data[0]["message"]);
                    }

                    break;

                default:
                    # code...
                    break;
            }
        }

        break;

    case 'GET':
        header("Content-Type: text/html");
        require_once 'advert.html';
        break;

    default:
        header("HTTP/1.1 403 Forbidden");
        header("Content-Type: text/html");
        break;
}
