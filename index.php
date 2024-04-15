<?php
require("users.class.php");

function wrong_request() {
    $users = Users::Instance();
    $users->sendStatus(405); //not found
    echo '{"error":"Method not allowed. Check your syntax"}';
    exit;
}

function route($method, $path, $fn) {
    $users = Users::Instance();
    $rawurl = (!empty($_SERVER['REQUEST_URL']))
        ? $_SERVER['REQUEST_URL']
        : $_SERVER['REQUEST_URI'];
    $dir = dirname($_SERVER['SCRIPT_NAME']);
    $uri = $dir != "/"
        ? str_replace($dir, '', $rawurl)
        : $rawurl;
    $uri = explode("?", $uri, 2);
    $path = "/^" . str_replace("/", "\/", $path) . "$/";
    $path = preg_replace("/\([^\)]*\)/", "([^\/]+)", $path);
    $apply = preg_match($path, $uri[0], $matches);

    $req_method = array_key_exists("HTTP_X_HTTP_METHOD_OVERRIDE", $_SERVER)
        ? strtoupper($_SERVER["HTTP_X_HTTP_METHOD_OVERRIDE"])
        : strtoupper($_SERVER["REQUEST_METHOD"]);
    if ($req_method == $method && $apply == 1) {
        call_user_func_array(array($users, $fn), array_slice($matches, 1));
        exit;
    }
}

header("Content-Type: application/json");
route("GET", "/", "rootPath");
route("POST", "/users/", "createUser");
route("GET", "/users/", "listUsers");
route("GET", "/users/(user_id)", "getUser");
route("PUT", "/users/(user_id)", "updateUser");
route("DELETE", "/users/(user_id)", "deleteUser");

route("POST", "/comments/", "createComment");
route("GET", "/comments/", "listComments");
route("GET", "/comments/(id)", "getComment");
route("PUT", "/comments/(id)", "updateComment");
route("DELETE", "/comments/(id)", "deleteComment");

wrong_request();
