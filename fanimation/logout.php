<?php
session_start();
require 'includes/db_connect.php';

session_unset();
session_destroy();
header('Location: index.php');
exit;