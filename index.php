<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once('bootstrap.php');

use Src\Controller\USSDHandler;
use Src\Controller\PaymentController;
use Src\Controller\ExposeDataController;

switch ($_SERVER["REQUEST_METHOD"]) {
    case 'POST':

        // Get the authorization header
        $authorizationHeader = isset($_SERVER['HTTP_AUTHORIZATION']) ? $_SERVER['HTTP_AUTHORIZATION'] : '';
        echo json_encode(array("success" => true, "message" => getallheaders())); // Example response
        exit;
        // Extract the username and password from the authorization header
        $credentials = null;
        if (preg_match('/Basic\s+(.*)$/i', $authorizationHeader, $matches)) {
            $credentials = base64_decode($matches[1]);
        }

        if ($credentials) {
            // Extract the username and password from the credentials
            list($username, $password) = explode(':', $credentials);

            // Validate the username and password (fetch the expected username and password from the database)
            $expectedUsername = "Francis"; // Implement your logic to fetch the expected username from the database
            $expectedPassword = "Anlimah"; // Implement your logic to fetch the expected password from the database

            if ($username === $expectedUsername && $password === $expectedPassword) {
                $_POST = json_decode(file_get_contents("php://input"), true);

                // Continue with the rest of your code here
                header("Content-Type: application/json");
                http_response_code(201);
                echo json_encode(array("success" => true, "message" => "Authorized")); // Example response
                exit;
            } else {
                // The username or password is invalid, send an appropriate response message
                http_response_code(401); // Unauthorized
                header('WWW-Authenticate: Basic realm="API Authentication"');
                echo json_encode(array("message" => "Invalid credentials"));
                exit;
            }
        } else {
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
