<?php

// configuration
require("../includes/config.php");

$id = $_SESSION["id"]; //get id from session








$ledger = query("SELECT * FROM ledger WHERE (user=?)", $id);







// render portfolio (pass in new portfolio table and cash)
render("account_form.php", ["title" => "Account", "ledger" => $ledger]);
?>                    
