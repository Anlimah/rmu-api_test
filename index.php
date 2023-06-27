<?php

require_once('bootstrap.php');

use Src\Controller\USSDHandler;
use Src\Controller\PaymentController;
use Src\Controller\ExposeDataController;

switch ($_SERVER["REQUEST_METHOD"]) {
    case 'POST':

        // Get the authorization header
        $authorizationHeader = isset($_SERVER['HTTP_AUTHORIZATION']) ? $_SERVER['HTTP_AUTHORIZATION'] : '';

        // Extract the secret key from the authorization header
        $secretKey = null;
        if (preg_match('/Bearer\s+(.*)$/i', $authorizationHeader, $matches)) {
            $secretKey = $matches[1];
        }

        if ($secretKey) {
            // Retrieve the client ID and signature from the secret key
            list($clientId, $signature) = explode(':', $secretKey);

            // Verify the signature against the payload and client secret (fetch the client secret from the database)
            $clientSecret = $expose->getSecretKeyFromDatabase($clientId); // Implement your logic to fetch the client secret from the database

            $payload = json_encode(array(
                "amount" => $data["amount"],
                "callback_url" => $callback_url,
                "customer_number" => $data["phone_number"],
                "exttrid" => $trans_id,
                "nw" => $data["network"],
                "reference" => "RMU Forms Online",
                "service_id" => getenv('ORCHARD_SERVID'),
                "trans_type" => "CTM",
                "ts" => date("Y-m-d H:i:s")
            ));

            $expectedSignature = hash_hmac("sha256", $payload, $clientSecret);

            if ($signature === $expectedSignature) {
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
            } else {
                // The signature is invalid, send an appropriate response message
                http_response_code(401); // Unauthorized
                echo json_encode(array("message" => "Invalid signature"));
                exit;
            }
        } else {
            // The authorization header is missing or invalid, send an appropriate response message
            http_response_code(401); // Unauthorized
            echo json_encode(array("message" => "Authorization required"));
            exit;
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
