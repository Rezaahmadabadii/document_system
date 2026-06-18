<?php
session_name('doc_system');
session_start();
session_destroy();
header('Location: login.php');
exit;