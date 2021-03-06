<?php
/**
 * Created by PhpStorm.
 * User: rex
 * Date: 5/26/2019
 * Time: 9:51 PM
 */

include_once 'auth_util.php';
include_once 'config.php';
include_once 'account_utils.php';
include_once 'EmailUtils.php';

$headers = apache_request_headers();

$validationError = AuthUtil::validate($headers);
if ($validationError != null) {
    echo $validationError;
    exit(0);
}
$username = $headers["username"];

$sql = "SELECT isStaff, isMentor FROM users WHERE username = '$username'";
$user = ApiHelper::queryOne($sql);

if ($user == null) {
    echo ApiHelper::buildErrorResponse("Something went wrong. Please try again later");
    exit(0);
}

if (!$user["isMentor"] && !$user["isStaff"]) {
    echo ApiHelper::buildErrorResponse("You do not have authorization to perform this action.");
    exit(0);
}

$json_body = file_get_contents('php://input');
$params = (array)json_decode($json_body);

$id = $params["id"];

$sql = "SELECT * FROM promotion WHERE id = $id";
$promotion = ApiHelper::queryOne($sql);

if ($promotion == null) {
    echo ApiHelper::buildErrorResponse("Something went wrong. Please try again later");
    exit(0);
}

$status = $promotion["status"];
if ($status == "pending" && $user["isMentor"] && $username == $promotion["mentor"]) {
    $sql = "DELETE FROM promotion WHERE id = $id";
    if (ApiHelper::exec($sql) > 0) {
        EmailUtils::sendInformationEmail($promotion["username"], "Promotion declined",
            "Dear " . $promotion["username"]
            . "\n\nYour recent request to promote/renew yourself as merchant under tag of " . $promotion["mentor"]
            . " has been declined by " . $promotion["mentor"] . ". If you find this unexpected/mistake, please contact with your mentor."
            . "\n\nRegards\nTeam 6t9"
            . "\n\n(NB: This is an autogenerated email. If you reply here, your reply will not reach us.)");
        echo ApiHelper::buildSuccessResponse((int)$id, "Request declined successfully.");
    } else {
        echo ApiHelper::buildErrorResponse("Something went wrong. Please try again later");
    }
} else if ($status == "verified" && $user["isStaff"]) {
    $sql = "DELETE FROM promotion WHERE id = $id";
    $message = "";
    if (ApiHelper::exec($sql) > 0) {
        $message = "Request declined successfully.";
        $au = new AccountUtils();
        $au->addCredit($promotion["mentor"], 200000, true);
        $au->addHistory($promotion["mentor"], "+", 200000, "Refunded 200000 BDT from denial of a merchant promotion request.");

        EmailUtils::sendInformationEmail($promotion["username"], "Promotion declined",
            "Dear " . $promotion["username"]
            . "\n\nYour recent request to promote/renew yourself as merchant under tag of " . $promotion["mentor"]
            . " has been declined by authority. If you find this unexpected/mistake, please contact any staff for more information."
            . "\n\nRegards\nTeam 6t9"
            . "\n\n(NB: This is an autogenerated email. If you reply here, your reply will not reach us.)");

        EmailUtils::sendInformationEmail($promotion["mentor"], "Promotion declined",
            "Dear " . $promotion["mentor"]
            . "\n\nYour recent request to promote/renew " . $promotion["username"] . " as merchant under tag of yourself"
            . " has been declined by authority. The amount 200000 BDT has been refunded to your account. If you find this unexpected/mistake, please contact any staff for more information."
            . "\n\nRegards\nTeam 6t9"
            . "\n\n(NB: This is an autogenerated email. If you reply here, your reply will not reach us.)");

        echo ApiHelper::buildSuccessResponse((int)$id, $message);
    } else {
        echo ApiHelper::buildErrorResponse("Something went wrong. Please try again later");
    }
} else {
    echo ApiHelper::buildErrorResponse("You do not have authorization to perform this action.");
}