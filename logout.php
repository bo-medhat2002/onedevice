<?php
// logout.php
include 'config.php';
session_destroy();
header('Location: login.php');
