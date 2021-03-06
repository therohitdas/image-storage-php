<?php
require("vendor/autoload.php");

// env var secret
$secret = getenv("SECRET");
$filePath = getenv("FILE_PATH");
$salt = getenv("SALT");
$file_input_name = getenv("FILE_INPUT_NAME");
$allowed_hosts = getenv("ALLOWED_HOST");
$allowed_hosts = strtolower($allowed_hosts);
$allowed_hosts = explode(",", $allowed_hosts);
$disable_host_check = getenv("DISABLE_HOST_CHECK") === "true";

if (array_key_exists('HTTP_ORIGIN', $_SERVER)) {
    $origin = $_SERVER['HTTP_ORIGIN'];
} else if (array_key_exists('HTTP_REFERER', $_SERVER)) {
    $origin = $_SERVER['HTTP_REFERER'];
} else {
    $origin = false;
}

$origin = explode("/", $origin);
$origin = $origin[2];
$origin = explode(":", $origin);
$origin = $origin[0];

error_log($origin);

$imageUploader = new ImageUploader();
$imageUploader->setPath($filePath);
$imageUploader->setSalt($salt);

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_FILES[$file_input_name]) ) {

    $allowed = (isset($_POST["secret"]) && $_POST["secret"] == $secret);
    $allowed = $allowed || (in_array($origin, $allowed_hosts));

    if ($allowed) {
        // generate random 10 char id
        if (isset($_POST["id"])) {
            $id = $_POST["id"];
        } else {
            $id = substr(md5(uniqid(rand(), true)), 0, 10);
        }
        try{
        $imageUploader->upload($_FILES[$file_input_name], $id);
        // send id in json format
        echo json_encode(array("id" => $id));
        } catch (Exception $e) {
            echo json_encode(array("error" => $e->getMessage()));
        }        
    } else {
        echo json_encode(array("error" => "Unauthorized"));
        die();
    }
} elseif ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['id'])) {
    $allowed = (isset($_POST["secret"]) && $_POST["secret"] == $secret);
    $allowed = $allowed || (in_array($origin, $allowed_hosts));
    if (!$allowed) {
        echo json_encode(array("error" => "Unauthorized"));
        die();
    }
    if (strlen($id = $_POST["id"]) <= 10) {
        if ($imageUploader->exists($id)) {
            $image_name = "";
            if ($salt === null) {
                $image_name = md5($id);
            } else {
                $image_name = md5($id . $salt);
            }

            $image_path = $filePath . DIRECTORY_SEPARATOR . $image_name;
            if (unlink($image_path)) {
                echo json_encode(array("id" => $id));
            } else {
                echo json_encode(array("error" => "Could not delete image"));
            }
        } else {
            echo json_encode(array("error" => "Not found"));
            http_response_code(404);
            die();
        }
    } else {
        echo json_encode(array("error" => "Not found"));
        die();
    }
}
elseif ($_SERVER["REQUEST_METHOD"] == "GET") {
    // allowed host check
    if (!$disable_host_check){
        if ($origin !== false && (!in_array($origin, $allowed_hosts))) {
            echo json_encode(array("error" => "Unauthorized"));
            die();
        }
    }
    if (isset($_GET["id"]) && strlen($_GET["id"]) <= 10) {
        if ($imageUploader->exists($_GET["id"])) {
            $imageUploader->serve($_GET["id"]);
        } else {
            echo json_encode(array("error" => "Not found"));
            http_response_code(404);
            die();
        }
    } else {
        echo json_encode(array("error" => "Not found"));
        die();
    }
}

else {
    echo json_encode(array("error" => "Bad Request"));
    http_response_code(400);
    die();
}
