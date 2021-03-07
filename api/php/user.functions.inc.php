<?php

$conn = connectToDb();
##### User Functions #####
function changeEmail($loginid, $email){
    global $seed;
    $response = (object) array();
    $response->valid = true;
    if (!valid_email($email)) {

        $response->valid = false;
        $response->message = 'Invalid email address';
        return $response;

    }

    if (user_email_exists($email)) {
      $response->valid = false;
      $response->message = 'An account already exists with this email address';
      return $response;
    }

    // now we update the email in the database
    $query = sprintf("update login set email = '%s' where loginid = '%s'",
        mysqli_real_escape_string($conn, $email), mysqli_real_escape_string($conn, $loginid));

    if (mysqli_query($conn, $query)) {
        return $response;
    } else {
        $response->valid = false;
        $response->message = 'Unable to change email';
        return $response;
    }
}

function changePassword($username, $currentpassword, $newpassword, $newpassword2){
    global $seed;
    $response = (object) array();
    $response->valid = true;
    if (!valid_username($username)) {

        $response->valid = false;
        $response->message = 'Invalid username';
        return $response;

    } else if (!user_exists($username)) {

        $response->valid = false;
        $response->message = 'User already exists';
        return $response;

    }
    if (!valid_password($newpassword) ){

        $response->valid = false;
        $response->message = 'New password is invalid';
        return $response;

    } else if ($newpassword != $newpassword2){
        $response->valid = false;
        $response->message = 'Passwords don\'t match';
        return $response;
    }

    // we get the current password from the database
    $query = sprintf("SELECT password FROM login WHERE username = '%s' LIMIT 1",
        mysqli_real_escape_string($conn, $username));

    $result = mysqli_query($conn, $query);
    $row= mysqli_fetch_row($result);

    // compare it with the password the user entered, if they don't match, we return false, he needs to enter the correct password.
    if ($row[0] != sha1($currentpassword.$seed)){
        $response->valid = false;
        $response->message = 'Incorrect password';
        return $response;
    }

    // now we update the password in the database
    $query = sprintf("update login set password = '%s' where username = '%s'",
        mysqli_real_escape_string($conn, sha1($newpassword.$seed)), mysqli_real_escape_string($conn, $username));

    if (mysqli_query($conn, $query)) {
        return $response;
    } else {
        $response->valid = false;
        $response->message = 'Unable to change password';
        return $response;
    }
}

function delete_account($loginid, $password){
    global $seed;
    $response = (object) array();
    $response->valid = true;
    if (!valid_password($password) ){

        $response->valid = false;
        $response->message = 'Password is invalid';
        return $response;

    }

    // we get the current password from the database
    $query = sprintf("SELECT password FROM login WHERE loginid = '%s' LIMIT 1",
        mysqli_real_escape_string($conn, $loginid));

    $result = mysqli_query($conn, $query);
    $row= mysqli_fetch_row($result);

    // compare it with the password the user entered, if they don't match, we return false, he needs to enter the correct password.
    if ($row[0] != sha1($password.$seed)){
        $response->valid = false;
        $response->message = 'Incorrect password';
        return $response;
    }

    // now we update 'deleted' in the database
    $query = sprintf("update login set deleted = 1 where loginid = '%s'",
        mysqli_real_escape_string($conn, mysqli_real_escape_string($conn, $loginid)));

    if (mysqli_query($conn, $query)) {
    } else {
        $response->valid = false;
        $response->message = 'Unable to delete account';
        return $response;
    }

    // now we update 'deleted time' in the database
    $query = sprintf("update login set deleted_time = now() where loginid = '%s'",
        mysqli_real_escape_string($conn, mysqli_real_escape_string($conn, $loginid)));

    if (mysqli_query($conn, $query)) {
        return $response;
    }
}

function user_exists($username) {
    $conn = connectToDb();
    if (!valid_username($username)) {
        return false;
    }
    $escapedUsername = mysqli_real_escape_string($conn, $conn, $username);
    $query = sprintf("SELECT loginid FROM login WHERE username = '%s' LIMIT 1",
        $escapedUsername);
    $result = mysqli_query($conn, $conn, $query);

    if (mysqli_num_rows($result) > 0) {
        return true;
    } else {
        return false;
    }

    return false;
}

function user_email_exists($email) {
  if (!valid_email($email)) {
      return false;
  }

  $query = sprintf("SELECT loginid FROM login WHERE email = '%s' LIMIT 1",
      mysqli_real_escape_string($conn, $email));

  $result = mysqli_query($conn, $query);

  if (mysqli_num_rows($result) > 0) {
      return true;
  } else {
      return false;
  }

  return false;
}
function retailer_exists($username) {
    if (!valid_username($username)) {
        return false;
    }

    $query = sprintf("SELECT id FROM retailers WHERE username = '%s' LIMIT 1",
        mysqli_real_escape_string($conn, $username));

    $result = mysqli_query($conn, $query);

    if (mysqli_num_rows($result) > 0) {
        return true;
    } else {
        return false;
    }

    return false;
}


function activateUser($uid, $actcode) {

    $query = sprintf("select activated from login where loginid = '%s' and actcode = '%s' and activated = 0  limit 1",
        mysqli_real_escape_string($conn, $uid), mysqli_real_escape_string($conn, $actcode));

    $result = mysqli_query($conn, $query);

    if (mysqli_num_rows($result) == 1) {

        $sql = sprintf("update login set activated = '1'  where loginid = '%s' and actcode = '%s'",
            mysqli_real_escape_string($conn, $uid), mysqli_real_escape_string($conn, $actcode));

        if (mysqli_query($conn, $sql)) {
            return true;
        } else {
            return false;
        }

    } else {

        return false;

    }
}

function registerNewUser($username, $password, $password2, $email) {

    global $seed;
    $response = (object) array();
    $response->valid = true;

    if (!valid_username($username)) {
        $response->valid = false;
        $response->message = 'Invalid username';
        return $response;
    } elseif (!valid_password($password)) {
        $response->valid = false;
        $response->message = 'Invalid password';
        return $response;
    } elseif (!valid_email($email)) {
        $response->valid = false;
        $response->message = 'Invalid email';
        return $response;
    } elseif (user_email_exists($email)) {
        $response->valid = false;
        $response->message = 'An account already exists with this email address';
        return $response;
    } elseif ($password != $password2) {
        $response->valid = false;
        $response->message = 'Passwords do not match';
        return $response;
    } elseif (user_exists($username)) {
        $response->valid = false;
        $response->message = $username . ' has already been taken';
        return $response;
    }


    $code = generate_code(20);
    $sql = sprintf("insert into login (username,password,email,actcode,create_time,last_login) value ('%s','%s','%s','%s', now(), now())",
        mysqli_real_escape_string($conn, $username), mysqli_real_escape_string($conn, sha1($password . $seed))
        , mysqli_real_escape_string($conn, $email), mysqli_real_escape_string($conn, $code));


    if (mysqli_query($conn, $sql)) {
        $id = mysqli_insert_id($conn);

        if (sendActivationEmail($username, $password, $id, $email, $code)) {
            return $response;
        } else {
            $response->valid = false;
            $response->message = 'Unable to send activation email';
            return $response;
        }

    } else {
        $response->valid = false;
        $response->message = 'Unable to register';
        return $response;
    }
}

function lostPassword($username, $email) {

    global $seed;
    $response = (object) array();
    $response->valid = true;

    if (!valid_username($username)) {
        $response->valid = false;
        $response->message = 'Invalid username';
        return $response;
    } elseif (!user_exists($username)) {
        $response->valid = false;
        $response->message = 'User ' . $username . ' doesn\'t exist';
        return $response;
    } else if (!valid_email($email)) {
        $response->valid = false;
        $response->message = 'Invalid email';
        return $response;
    }
    else if (!user_email_exists($email)) {
        $response->valid = false;
        $response->message = 'No account exists for ' . $email;
        return $response;
    }

    $query = sprintf("select loginid from login where username = '%s' and email = '%s' limit 1",
        $username, $email);

    $result = mysqli_query($conn, $query);

    if (mysqli_num_rows($result) != 1) {
        $response->valid = false;
        $response->message = 'Incorrect user or email address';
        return $response;
    }


    $newpass = generate_code(8);

    $query = sprintf("update login set password = '%s' where username = '%s'",
        mysqli_real_escape_string($conn, sha1($newpass.$seed)), mysqli_real_escape_string($conn, $username));

    if (mysqli_query($conn, $query)) {

        if (sendLostPasswordEmail($username, $email, $newpass)) {
            return $response;
        } else {
            $response->valid = false;
            $response->message = 'Unable to send lost password email';
            return $response;
        }

    } else {
        $response->valid = false;
        $response->message = 'Unable to reset password';
        return $response;
    }
}

?>
