<?php
require_once "header.php";

unset($_SESSION['loginid']); unset($_SESSION['username']);
session_destroy();
