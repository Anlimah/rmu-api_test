<?php
/*ini_set('display_errors', 1);
error_reporting(E_ALL);*/

require_once('bootstrap.php');

use Src\Controller\APIEndpointHandler;

switch ($_SERVER["REQUEST_METHOD"]) {
    case 'POST':

        // Get the authorization header
        $authUsername = isset($_SERVER['PHP_AUTH_USER']) ? $_SERVER['PHP_AUTH_USER'] : '';
        $authPassword = isset($_SERVER['PHP_AUTH_PW']) ? $_SERVER['PHP_AUTH_PW'] : '';

        if ($authUsername && $authPassword) {

            $expose = new APIEndpointHandler();
            $user = $expose->verifyAPIAccess($authUsername, $authPassword);

            if ($user) {
                $_POST = json_decode(file_get_contents("php://input"), true);
                $response = array();

                $request_uri = explode('?', $_SERVER['REQUEST_URI'], 2)[0];
                $endpoint = '/' . basename($request_uri);

                switch ($endpoint) {
                    case '/getForms':
                        $response = $expose->getForms($user);
                        break;

                    case '/purchaseForm':
                        if (empty($_POST))
                            $response = array("success" => false, "message" => "Request parameters not passed");
                        else
                            $response = $expose->handleAPIBuyForms($_POST, $user);
                        break;

                    case '/purchaseStatus':
                        if (empty($_POST))
                            $response = array("success" => false, "message" => "Request parameters not passed");
                        else
                            $response = $expose->purchaseStatus($_POST, $user);
                        break;

                    case '/purchaseInfo':
                        if (empty($_POST))
                            $response = array("success" => false, "message" => "Request parameters not passed");
                        else
                            $response = $expose->purchaseInfo($_POST, $user);
                        break;

                    default:
                        # code...
                        break;
                }

                // Continue with the rest of your code here
                header("Content-Type: application/json");
                http_response_code(201);
                echo json_encode($response); // Example response
                exit;
            }

            //
            else {
                // The username or password is invalid, send an appropriate response message
                http_response_code(401); // Unauthorized
                header('WWW-Authenticate: Basic realm="API Authentication"');
                echo json_encode(array("message" => "Invalid credentials"));
                exit;
            }
        }

        //
        else {
            // The authorization header is missing or invalid, send an appropriate response message
            http_response_code(401); // Unauthorized
            header('WWW-Authenticate: Basic realm="API Authentication"');
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
