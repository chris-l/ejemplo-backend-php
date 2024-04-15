<?php

class Users
{
    private $db;
    private $post_data = array();
    private $content_type;
    private $status_codes = array(
        "404" => "Not Found",
        "405" => "Method Not Allowed",
        "409" => "Conflict",
        "422" => "Unprocessable Content"
    );


    private function __construct($opts)
    {
        if (array_key_exists("CONTENT_TYPE", $_SERVER)) {
            $this->content_type = $_SERVER["CONTENT_TYPE"];
        }
        if ($this->content_type === "application/json") {
            try {
                $raw = file_get_contents("php://input");
                $this->post_data = json_decode($raw, true);
            } catch (Exception $e) {
            }
        }
        $this->db = new PDO("mysql:host=" . getenv("MYSQL_SERVER") . ";dbname=" . getenv("MYSQL_DATABASE"), getenv("MYSQL_USER"), getenv("MYSQL_PASSWORD"));
    }


    static public function Instance($opts = array())
    {
        static $inst = null;
        if ($inst === null) {
            $inst = new Users($opts);
        }
        return $inst;
    }

    public function sendStatus($status)
    {
        header($_SERVER["SERVER_PROTOCOL"] . " $status " . $this->status_codes[$status]);
    }

    private function existsUser($user_id)
    {
        $check = $this->db->prepare("SELECT COUNT(*) FROM user WHERE id = :id");
        $check->bindParam(':id', $user_id);
        $check->execute();
        $userCount = $check->fetchColumn();
        if ($userCount == 0) {
            $this->sendStatus(404);
            echo '{"success":false,"error":"The user id doesn\'t exists"}';
            exit;
        }
    }

    private function existsComment($id)
    {
        $check = $this->db->prepare("SELECT COUNT(*) FROM user_comment WHERE id = :id");
        $check->bindParam(':id', $id);
        $check->execute();
        $userCount = $check->fetchColumn();
        if ($userCount == 0) {
            $this->sendStatus(404);
            echo '{"success":false,"error":"The comment id doesn\'t exists"}';
            exit;
        }
    }

    public function rootPath()
    {
        echo '{"msg":"API demo"}';
    }

    private function validateUser()
    {
        if (!array_key_exists("email", $this->post_data) || !filter_var($this->post_data["email"], FILTER_VALIDATE_EMAIL)) {
            $this->sendStatus(422);
            echo '{"success": false, "error": "Invalid or missing email"}';
            exit;
        }
        if (!array_key_exists("openid", $this->post_data) || $this->post_data["openid"] === "") {
            $this->sendStatus(422);
            echo '{"success": false, "error": "Missing openid"}';
            exit;
        }
        if (!array_key_exists("fullname", $this->post_data) || $this->post_data["fullname"] === "") {
            $this->sendStatus(422);
            echo '{"success": false, "error": "Missing fullname"}';
            exit;
        }
        if (!array_key_exists("pass", $this->post_data) || $this->post_data["pass"] === "") {
            $this->sendStatus(422);
            echo '{"success": false, "error": "Missing pass"}';
            exit;
        }
    }

    private function validateComment()
    {
        if (!array_key_exists("user", $this->post_data) || !is_int($this->post_data["user"])) {
            $this->sendStatus(422);
            echo '{"success": false, "error": "Invalid or missing user id"}';
            exit;
        }
        if (!array_key_exists("coment_text", $this->post_data) || !is_string($this->post_data["coment_text"])) {
            $this->sendStatus(422);
            echo '{"success": false, "error": "Missing coment_text"}';
            exit;
        }
        if (array_key_exists("likes", $this->post_data) && !is_int($this->post_data["likes"])) {
            $this->sendStatus(422);
            echo '{"success": false, "error": "Invalid likes"}';
            exit;
        }
    }

    public function createUser()
    {
        $this->validateUser();
        $request = $this->db->prepare("INSERT INTO user (fullname, email, pass, openid) VALUES (:fullname, :email, :pass, :openid)");
        $pass_hash = password_hash($this->post_data["pass"], PASSWORD_BCRYPT);

        // Bind parameters
        $request->bindParam(':fullname', $this->post_data["fullname"]);
        $request->bindParam(':email', $this->post_data["email"]);
        $request->bindParam(':pass', $pass_hash);
        $request->bindParam(':openid', $this->post_data["openid"]);

        try {
            $request->execute();
        } catch(PDOException $e) {
            $this->sendStatus(409);
            echo '{"success": false, "error": "' . $e->getMessage() . '"}';
            exit;
        }

        echo '{"success": true, "id": ' . $this->db->lastInsertId() . '}';
    }

    public function listUsers()
    {
        $request = $this->db->prepare("SELECT id, fullname, email FROM user");
        $request->execute();
        $result = $request->fetchAll();
        //$user = $request->fetch(PDO::FETCH_ASSOC);
        if ($result == false) {
            echo '[]';
            exit;
        }

        echo json_encode(array_map(function ($x) {
            return array(
                "id" => $x["id"],
                "fullname" => $x["fullname"],
                "email" => $x["email"]
            );
        }, $result));
    }

    public function getUser($user_id)
    {
        $request = $this->db->prepare("SELECT * FROM user WHERE id = :id");
        $request->execute(array('id' => $user_id));
        $user = $request->fetch(PDO::FETCH_ASSOC);
        if ($user == false) {
            $this->sendStatus(404);
            echo '{"success": false, "error": "Not found"}';
            exit;
        }

        echo json_encode($user);
    }

    public function deleteUser($user_id)
    {

        $this->existsUser($user_id);
        $request = $this->db->prepare("DELETE FROM user WHERE id = :id");
        $user = $request->execute(array('id' => $user_id));
        echo '{"success":true}';
    }

    public function updateUser($user_id)
    {
        $this->validateUser();
        $pass_hash = password_hash($this->post_data["pass"], PASSWORD_BCRYPT);
        $this->existsUser($user_id);
        $request = $this->db->prepare("UPDATE user SET fullname = :fullname, email = :email, pass = :pass, openid = :openid WHERE id = :id");
        $request->bindParam(':id', $user_id);
        $request->bindParam(':fullname', $this->post_data["fullname"]);
        $request->bindParam(':email', $this->post_data["email"]);
        $request->bindParam(':pass', $pass_hash);
        $request->bindParam(':openid', $this->post_data["openid"]);

        try {
            $request->execute();
        } catch(PDOException $e) {
            $this->sendStatus(409);
            echo '{"success": false, "error": "' . $e->getMessage() . '"}';
            exit;
        }

        echo '{"success":true}';
    }

    public function createComment()
    {
        $this->validateComment();
        $request = $this->db->prepare("INSERT INTO user_comment (user, coment_text, likes) VALUES (:user, :coment_text, :likes)");

        $this->existsUser($this->post_data["user"]);
        // Bind parameters
        $request->bindParam(':user', $this->post_data["user"]);
        $request->bindParam(':coment_text', $this->post_data["coment_text"]);
        $request->bindParam(':likes', $this->post_data["likes"]);

        try {
            $request->execute();
        } catch(PDOException $e) {
            $this->sendStatus(409);
            echo '{"success": false, "error": "' . $e->getMessage() . '"}';
            exit;
        }

        echo '{"success": true, "id": ' . $this->db->lastInsertId() . '}';
    }

    public function listComments()
    {
        $request = $this->db->prepare("SELECT id, user, creation_date FROM user_comment");
        $request->execute();
        $result = $request->fetchAll();
        if ($result == false) {
            echo '[]';
            exit;
        }

        echo json_encode(array_map(function ($x) {
            return array(
                "id" => $x["id"],
                "user" => $x["user"],
                "creation_date" => $x["creation_date"]
            );
        }, $result));
    }

    public function getComment($id)
    {
        $request = $this->db->prepare("SELECT * FROM user_comment WHERE id = :id");
        $request->execute(array('id' => $id));
        $result = $request->fetch(PDO::FETCH_ASSOC);
        if ($result == false) {
            $this->sendStatus(404);
            echo '{"success": false, "error": "Not found"}';
            exit;
        }

        echo json_encode($result);
    }

    public function deleteComment($id)
    {

        $this->existsComment($id);
        $request = $this->db->prepare("DELETE FROM user_comment WHERE id = :id");
        $request->execute(array('id' => $id));
        echo '{"success":true}';
    }

    public function updateComment($id)
    {
        $this->validateComment();
        $this->existsComment($id);
        $this->existsUser($this->post_data["user"]);
        $request = $this->db->prepare("UPDATE user_comment SET user = :user, coment_text = :coment_text, likes = :likes WHERE id = :id");
        $request->bindParam(':id', $id);
        $request->bindParam(':user', $this->post_data["user"]);
        $request->bindParam(':coment_text', $this->post_data["coment_text"]);
        $request->bindParam(':likes', $this->post_data["likes"]);

        try {
            $request->execute();
        } catch(PDOException $e) {
            $this->sendStatus(409);
            echo '{"success": false, "error": "' . $e->getMessage() . '"}';
            exit;
        }

        echo '{"success":true}';
    }
}
