

<?php

require("../includes/config.php");
$id = $_SESSION["id"];
$title = "Dashboard";
$assets = query("SELECT symbol FROM assets"); // for processing, delete, and remove orderbook menu

if ($id != 1) { apologize("Unauthorized!"); exit();}
else
{






    if(isset($_POST['addasset'])) {
        $symbol = strtolower($_POST['addasset']);
        $depository = $_POST['depository'];
        $description = $_POST['description'];
        $asw = $_POST['asw'];
        $purity = $_POST['purity'];
        $country = $_POST['country'];
        $year = $_POST['year'];
        $quantity = $_POST['quantity'];
        $weight = $_POST['weight'];

        if (query("  INSERT INTO storage (symbol, depository, description, asw, purity, country, year, weight, quantity)
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
                $symbol, $depository, $description, $asw, $purity, $country, $year, $weight, $quantity) === false)
        {apologize("Database Failure #P2.");} //update portfolio
    }


    if (isset($_POST["removeAsset"]))
    {
        $uid = $_POST["removeAsset"];
        if (!ctype_digit($uid)){apologize("Invalid asset #");}
        if (query("DELETE FROM `storage` WHERE uid = ?", $uid) === false) {apologize("Unable to cancel order!");}
    }


    if (isset($_POST["updateasset"]))
    {
        $uid =  $_POST['updateasset'];
        $symbol = strtolower($_POST['symbol']);
        $depository = $_POST['depository'];
        $description = $_POST['description'];
        $asw = $_POST['asw'];
        $purity = $_POST['purity'];
        $country = $_POST['country'];
        $year = $_POST['year'];
        $weight = $_POST['weight'];
        $quantity = $_POST['quantity'];

        if (!ctype_digit($uid)){apologize("Invalid asset #");}
        if (query("UPDATE `storage` SET symbol=?, depository=?, description=?, asw=?, purity=?, country=?, year=?, weight=?, quantity=? WHERE uid=?",
                $symbol, $depository, $description, $asw, $purity, $country, $year, $weight, $quantity, $uid) === false)
        {apologize("Database Failure #P3.");} //update portfolio

    }







    if(isset($_POST['process'])) {
        if ($_POST["process"] == 'ALL') {
            try {
                $processOrderbook = processOrderbook();
            } catch (Exception $e) {
                echo('Error: ' . $e->getMessage() . '<br>');
            }         //catch exception
        } else {
            try {
                $processOrderbook = processOrderbook($_POST["process"]);
            } catch (Exception $e) {
                echo('Error: ' . $e->getMessage() . '<br>');
            }         //catch exception
        }

        echo($processOrderbook . " orders processed.");
    }




    if(isset($_POST['transaction']))
    {
        //get variables
        if ( empty($_POST['quantity']) ||  empty($_POST['userid'])) { apologize("Please fill all required fields."); } //check to see if empty
        // if symbol or quantity empty
        $userid = sanatize('quantity', $_POST['userid']);
        $quantity = setPrice($_POST['quantity']);
        $transaction = strtoupper($_POST['transaction']);
        $symbol = $unittype;

        if($transaction=="WITHDRAW")
        {
            $totalq = query("SELECT units FROM accounts WHERE id = ?", $userid);
            @$total = (float)$totalq[0]["units"]; //convert array to value
            if ($quantity > $total)  //only allows user to deposit if they have less than
            { apologize("You only have " . number_format($total,2,".",",") . " to withdraw!"); }
            $quantity = ($quantity*-1);
        }
        /* IF TRANFER
        UID - TRANSFER #
        ID - USER TRANSFEREE
        OUID - 0
        DATE - CURRENT TIMESTAMP
        TRANSACTION - TRANSFER
        SYMBOL - MONEY TYPE (ie USD)
        COUNTERPARTY - TRANSFEREE
        QUANTITY -  0
        PRICE - 0
        TOTAL - AMOUNT
        */


        // transaction information
        query("SET AUTOCOMMIT=0");
        query("START TRANSACTION;"); //initiate a SQL transaction in case of error between transaction and commit
        // update cash after transaction for user          
        if (query("UPDATE accounts SET units = (units + ?) WHERE id = ?", $quantity, $userid) === false)
        {query("ROLLBACK"); query("SET AUTOCOMMIT=1"); apologize("Database Failure #P1.");} //update portfolio
        //update transaction history for user
        if (query("INSERT INTO history (id, transaction, symbol, quantity, price, counterparty, total) VALUES (?, ?, ?, ?, ?, ?, ?)", $userid, $transaction, $symbol, 0, 0, $adminid, $quantity) === false)
        {query("ROLLBACK"); query("SET AUTOCOMMIT=1"); apologize("Database Failure #P2.");} //update portfolio
        query("COMMIT;"); //If no errors, commit changes
        query("SET AUTOCOMMIT=1");
    }






    if(isset($_POST['reset'])) {
        $user = sanatize('quantity', $_POST['user']);
        $password = $_POST['reset'];
        $options = ['cost' => 12,];
        $password = password_hash($password, PASSWORD_BCRYPT, $options);
        query("UPDATE users SET password=?, fails=0 WHERE id=?", $password, $user);
    }










    if(isset($_POST['lock'])) {
        $user = sanatize('quantity', $_POST['lock']);
        query("UPDATE users SET active=0 WHERE id=?", $user);
    }







    if(isset($_POST['notice'])) {
        $user = sanatize('quantity', $_POST['user']);
        $notice = sanatize('address', $_POST['notice']);
        query("INSERT INTO notification (id, notice, status) VALUES (?, ?, ?)", $user, $notice, 1);
    }

















    if(isset($_POST['admin'])) {
        if ($_POST['admin'] == 'all') {clear_all();}
        if ($_POST['admin'] == 'test') {test();}
        if ($_POST['admin'] == 'orderbook') {clear_orderbook();}
        if ($_POST['admin'] == 'trades') {clear_trades();}
        if ($_POST['admin'] == 'populate') {populatetrades();}
        if ($_POST['admin'] == 'createstocks') {
            try {
                createStocks();
            } catch (Exception $e) {
                echo 'Message: ' . $e->getMessage();
            }
        }
        if ($_POST['admin'] == 'randomorders') {
            try {
                $randomOrders = randomOrders();
            } catch (Exception $e) {
                echo('Error: ' . $e->getMessage() . '<br>');
            }         //catch exception
        }
    } //if admin post








//apologize(var_dump(get_defined_vars())); //dump all variables anywhere (displays in header)
    require("../templates/header.php");
    ?>
    <style>
        #middle
        {
            background-color:transparent;
            border:0;
        }
    </style>




    <form action="admin.php"  class="symbolForm" method="post"   >
        <fieldset>
            <table class="table table-condensed table-striped table-bordered" id="admin" style="border-collapse:collapse;text-align:center;vertical-align:middle;">
                <tr><th colspan=2>TESTING</th></tr>
                <tr><td><input type="radio" name="admin" value="all"></td>          <td>Clear All</td></tr>
                <tr><td><input type="radio" name="admin" value="test"></td>          <td>New Environment</td></tr>
                <tr><td><input type="radio" name="admin" value="orderbook"></td>    <td>Clear Orderbook</td></tr>
                <tr><td><input type="radio" name="admin" value="trades"></td>       <td>Clear Trades</td></tr>
                <tr><td><input type="radio" name="admin" value="createstocks"></td> <td>Create Stocks</td></tr>
                <tr><td><input type="radio" name="admin" value="randomorders"></td> <td>Random Orders</td></tr>
                <tr><td><input type="radio" name="admin" value="populate"></td>      <td>Populate</td></tr>
                <tr><td colspan='2'>
                        <button type="submit" class="btn btn-info"><b> SUBMIT </b></button></span>
                    </td></tr>
            </table>

        </fieldset>
    </form>




























    <table class="table table-condensed table-striped table-bordered" id="notice" style="border-collapse:collapse;text-align:center;vertical-align:middle;">
        <thead>
        <tr>
            <td class="info"><strong>NOTIFICATION</strong></td>
        </tr>
        </thead>
        <tbody>
        <tr>
            <td>
                <form action="admin.php"  class="noticeForm" method="post">
                    <input type="text" name="user" placeholder="User"  size="4">
                    <input type="text" name="notice" placeholder="Notice"  size="70">
                    <button type="submit" class="btn btn-info"><b> SUBMIT </b></button>
                </form>
            </td>
        </tr>
        </tbody>
    </table>












    <table class="table table-condensed table-striped table-bordered" id="password" style="border-collapse:collapse;text-align:center;vertical-align:middle;">
        <thead>
        <tr>
            <td class="danger"><strong>RESET PASSWORD</strong></td>
        </tr>
        </thead>
        <tbody>
        <tr>
            <td>
                <form action="admin.php" method="post">
                    <input type="text" name="user" placeholder="User"  size="4">
                    <input type="text" name="reset" placeholder="Password"  size="35">
                    <button type="submit" class="btn btn-danger"><b> RESET </b></button>
                </form>
            </td>
        </tr>
        </tbody>
    </table>




















    <table class="table table-condensed table-striped table-bordered" id="lock" style="border-collapse:collapse;text-align:center;vertical-align:middle;">
        <thead>
        <tr>
            <td class="warning"><strong>LOCK</strong></td>
        </tr>
        </thead>
        <tbody>
        <tr>
            <td>
                <form action="admin.php" method="post">
                    <input type="text" name="lock" placeholder="User"  size="4">
                    <button type="submit" class="btn btn-warning"><b> LOCK </b></button>
                </form>
            </td>
        </tr>
        </tbody>
    </table>













    <table class="table table-condensed table-striped table-bordered" id="process" style="border-collapse:collapse;text-align:center;vertical-align:middle;">
        <thead>
        <tr>
            <td class="success"><strong>PROCESS ORDERBOOK</strong></td>
        </tr>
        </thead>
        <tbody>
        <tr>
            <td>
                <form action="admin.php"  class="processForm" method="post">

                    <select name="process"  class="form-control" >
                        <?php
                        if (empty($assets)) {
                            echo("<option value=''>No Assets</option>");
                        } else {
                            //echo ('    <option class="select-dash" disabled="disabled">-All Assets-</option>');
                            echo ('    <option value="ALL">-All Assets-</option>');
                            foreach ($assets as $asset) {
                                $symbol = $asset["symbol"];
                                echo("<option value='" . $symbol . "'>  " . $symbol . "</option>");
                            }
                        }
                        ?>
                    </select>
                    <button type="submit" class="btn btn-success"><b> PROCESS </b></button></span>
                </form></td>
        </tr>
        </tbody>
    </table>












    <table class="table table-condensed table-striped table-bordered" id="notice" style="border-collapse:collapse;text-align:center;vertical-align:middle;">
        <thead>
        <tr>
            <td class="success" colspan="3"><strong>TRANSACTION</strong></td>
        </tr>
        </thead>
        <tbody>
        <tr>
            <td>
                <script type="text/javascript">
                    function FillUnits(f)
                    { if(f.copyunits.checked == true) {f.quantity.value = <?php echo($units); ?>;}}
                </script>
                <form action="admin.php" method="post" class="formtable">
                    <fieldset>
                        <input class="input-small" name="userid" placeholder="User ID" type="number" min="0" max="any" required /><br />
                        <input class="input-medium" type="number" name="quantity" placeholder="Amount/Quantity" step="0.01" min="0" max="any" required /><br>
                        <button type="submit"
                                class="btn btn-warning btn-xs"
                                name="transaction"
                                value="withdraw">WITHDRAW
                        </button>
                        <button type="submit"
                                class="btn btn-success btn-xs"
                                name="transaction"
                                value="deposit">DEPOSIT
                        </button>

                        <br /><input type="checkbox" name="copyunits" onclick="FillUnits(this.form)"> All <?php echo($unittype);?>
                    </fieldset>
                </form>
            </td>
        </tr>
        </tbody>
    </table>
















    <?php
    require("../templates/footer.php");

} //if adminid
?>
