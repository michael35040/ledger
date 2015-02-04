<?php
require("../includes/config.php");
// if form was submitted
$title = "History";
$id =  $_SESSION["id"];
$limit = "LIMIT 0, 10";
$tabletitle = "Last 10";
//history post submit button was press
if(isset($_POST['history']))
{   if ($_POST['history'] == 'all')
    {   $limit = "";
        $title = "All History";
        $tabletitle = "All";
    } //for unlimited option
} 

//HISTORY
$ledger = query("SELECT * FROM ledger WHERE (user=?) ORDER BY uid DESC $limit", $id);

render(
    "history_form.php",
    [
        "title" => $title,
        "ledger" => $ledger,
        "tabletitle" => $tabletitle,
    ]);

?>
