<?php

##### User Functions #####
// True if the account exists, isn't deleted, and the password matches.
function password_matches_login($conn, $loginid, $password) {
    global $seed;
    if (!is_string($password)) {
        return false;
    }
    $query = sprintf("SELECT password FROM login WHERE loginid = '%s' AND deleted = 0 LIMIT 1",
        mysqli_real_escape_string($conn, $loginid));
    $result = mysqli_query($conn, $query);
    if (!$result) {
        return false;
    }
    $row = mysqli_fetch_row($result);
    if (!$row) {
        return false;
    }
    return hash_equals($row[0], sha1($password . $seed));
}

function changeEmail($loginid, $email, $password){
    $conn = connectToDb();
    global $seed;
    $response = (object) array();
    $response->valid = true;

    // Changing the email address is sensitive, so the current password is required.
    if (!password_matches_login($conn, $loginid, $password)) {
        $response->valid = false;
        $response->message = 'Incorrect password';
        return $response;
    }
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
    $conn = connectToDb();
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
    $conn = connectToDb();
    $response = (object) array();
    $response->valid = true;
    if (!valid_password($password) ){

        $response->valid = false;
        $response->message = 'Password is invalid';
        return $response;

    }

    if (!password_matches_login($conn, $loginid, $password)) {
        $response->valid = false;
        $response->message = 'Incorrect password';
        return $response;
    }

    // Tombstone the account: free its username and email for reuse, and mark it deleted.
    // The loginid stays the same, so existing designs keep their owner.
    $query = sprintf("update login set deleted = 1, deleted_time = now(), username = '%s', email = '%s' where loginid = '%s'",
        mysqli_real_escape_string($conn, deleted_account_username($loginid)),
        mysqli_real_escape_string($conn, deleted_account_email($loginid)),
        mysqli_real_escape_string($conn, $loginid));

    if (!mysqli_query($conn, $query)) {
        $response->valid = false;
        $response->message = 'Unable to delete account';
    }
    return $response;
}

function user_exists($username) {
    $conn = connectToDb();
    if (!valid_username($username)) {
        return false;
    }
    $escapedUsername = mysqli_real_escape_string($conn, $username);
    $query = sprintf("SELECT loginid FROM login WHERE username = '%s' LIMIT 1",
        $escapedUsername);
    $result = mysqli_query($conn, $query);

    if (mysqli_num_rows($result) > 0) {
        return true;
    } else {
        return false;
    }

    return false;
}

function user_email_exists($email) {
    $conn = connectToDb();
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
    $conn = connectToDb();

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
    $conn = connectToDb();

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
    } elseif (is_reserved_username($username)) {
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
        $response->message = unactivated_email_exists($email)
            ? 'An account with this email address is waiting for activation. Use "Resend activation email" on the sign-in page.'
            : 'An account already exists with this email address';
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
    $conn = connectToDb();


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
            // Remove the unactivated row so the user can try again instead of being stuck.
            mysqli_query($conn, sprintf("delete from login where loginid = '%s' and activated = 0",
                mysqli_real_escape_string($conn, $id)));
            $response->message = 'Unable to send activation email';
            return $response;
        }

    } else {
        $response->valid = false;
        $response->message = 'Unable to register';
        return $response;
    }
}

// True if an account with this email exists but hasn't been activated (and isn't deleted or disabled).
function unactivated_email_exists($email) {
    $conn = connectToDb();
    $query = sprintf("SELECT loginid FROM login WHERE email = '%s' AND activated = 0 AND deleted = 0 AND disabled = 0 LIMIT 1",
        mysqli_real_escape_string($conn, $email));
    $result = mysqli_query($conn, $query);
    return $result !== false && mysqli_num_rows($result) > 0;
}

// Issues a new activation code for an unactivated account and emails it. The caller should always report
// success, so the response doesn't reveal whether an email is registered. Returns valid = false only for a
// malformed email address.
function resendActivationEmail($email) {
    $conn = connectToDb();
    $response = (object) array();
    $response->valid = true;
    $response->message = '';

    if (!valid_email($email)) {
        $response->valid = false;
        $response->message = 'Invalid email';
        return $response;
    }

    $query = sprintf("SELECT loginid, username FROM login WHERE email = '%s' AND activated = 0 AND deleted = 0 AND disabled = 0 LIMIT 1",
        mysqli_real_escape_string($conn, $email));
    $result = mysqli_query($conn, $query);
    if ($result === false || mysqli_num_rows($result) != 1) {
        return $response;
    }
    $row = mysqli_fetch_assoc($result);

    // Rotate the code so older activation links stop working.
    $code = generate_code(20);
    $sql = sprintf("update login set actcode = '%s' where loginid = '%s' and activated = 0",
        mysqli_real_escape_string($conn, $code), mysqli_real_escape_string($conn, $row['loginid']));
    if (mysqli_query($conn, $sql)) {
        sendActivationEmail($row['username'], '', $row['loginid'], $email, $code);
    }
    return $response;
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
    $conn = connectToDb();

    $query = sprintf("select loginid from login where username = '%s' and email = '%s' limit 1",
        mysqli_real_escape_string($conn, $username), mysqli_real_escape_string($conn, $email));

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
