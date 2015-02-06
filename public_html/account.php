<?php

// configuration
require("../includes/config.php");




//$ledger = query("SELECT * FROM ledger WHERE (user=?)", $id);


// render portfolio (pass in new portfolio table and cash)
render("account_form.php", [
    "title" => "Account"
]);
?>                    
