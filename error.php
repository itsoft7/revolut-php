<?php

print "<h1>Error</h1>";

if (isset($_GET['msg'])) {
    print "<pre>";
    print_r($_GET['msg']);
    print "</pre>";
}