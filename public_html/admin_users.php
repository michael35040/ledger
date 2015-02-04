<?php

// configuration
require("../includes/config.php");

$id = $_SESSION["id"];
if ($id != 1) { apologize("Unauthorized!");}
if ($id == 1) {


    if (isset($_POST["activate"]))
    {
        $id = $_POST["activate"];
        if ($id == 'ALL') { if (query("UPDATE users SET active=1 WHERE 1") === false) {apologize("Unable to activate all users!");}}
        else { if (query("UPDATE users SET active=1 WHERE id=?", $id) === false) {apologize("Unable to activate user!");}}
        redirect('admin_users.php');
    }
    if (isset($_POST["deactivate"]))
    {
        $id = $_POST["deactivate"];
        if ($id == 'ALL') {
            if (query("UPDATE users SET active=0 WHERE 1") === false) {apologize("Unable to deactivate all users!");}
            if (query("UPDATE users SET active=1 WHERE id=?", $adminid) === false) {apologize("Unable to activate all users!");}
        }
        else { if (query("UPDATE users SET active=0 WHERE id=?", $id) === false) {apologize("Unable to deactivate user!");}}
        redirect('admin_users.php');
    }



//var_dump(get_defined_vars()); 
    render("admin_users_form.php", ["title" => "Users", "searchusers" => $searchusers]);   // render output


} //$id
?>
