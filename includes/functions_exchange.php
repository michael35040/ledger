<?php
//dump all variables if i hit error
//apologize(var_dump(get_defined_vars()));
//throw new Exception(var_dump(get_defined_vars())); 
//apologize(var_dump(get_defined_vars()));

function transfer($payer, $payee, $quantity, $symbol)
{
    require 'constants.php';

    if($symbol==$unittype){$quantity=setPrice($quantity);} //if it is currency

    $referenceID=($payer . $payee . $quantity); //concatenate
    $reference=uniqid($referenceID,true);  //unique id reference to trade
    $negquantity=($quantity*-1);
//REMOVE
    if (query("INSERT INTO ledger (category, user, symbol, amount, reference, xuser, xsymbol, xamount, xreference, status, note)
                        VALUES (?,?,?,?,?,?,?,?,?,?,?)",
            'trade',
            $payer, $symbol, $negquantity, $reference,
            $payee, $symbol, $quantity, $reference,
            0, 'transfer-remove payer') === false) {query("ROLLBACK"); query("SET AUTOCOMMIT=1"); throw new Exception("Ledger Insert Failure"); }
//GIVE
    if (query("INSERT INTO ledger (category, user, symbol, amount, reference, xuser, xsymbol, xamount, xreference, status, note)
                        VALUES (?,?,?,?,?,?,?,?,?,?,?)",
            'trade',
            $payee, $symbol, $quantity, $reference,
            $payer, $symbol, $negquantity, $reference,
            0, 'transfer-give payee') === false) {query("ROLLBACK"); query("SET AUTOCOMMIT=1"); throw new Exception("Ledger Insert Failure"); }

}

///////////////////////////////
//CONVERT INTEGER PRICE TO FLOAT
///////////////////////////////
function getPrice($price)
{
    require 'constants.php';
    $price = $price/1000;
    return($price);
}
function setPrice($price)
{
    require 'constants.php';
    if (preg_match("/^([0-9.]+)$/", $price) == false) {apologize("You submitted an invalid price.");}
    if ($price<0){ apologize("Price must be positive!");} //if quantity is numeric
    $price = $price*1000;
    $price=floor($price);
    return($price);
}


/////////////////////////////////
//COMMISSION
/////////////////////////////////
function getCommission($total)
{
    require 'constants.php'; //for $divisor
    $commissionAmount = $total * $commission; //ie 13.6875 = 273.75 * 0.05  //(5qty * $54.75)
    $commissionAmount = floor($commissionAmount); //drops decimals
    return($commissionAmount);

}


////////////////////////////////////
//PLACE ORDER
////////////////////////////////////
//apologize(var_dump(get_defined_vars()));

function placeOrder($symbol, $type, $side, $quantity, $price, $id)
{   require 'constants.php'; //for $divisor
//apologize(var_dump(get_defined_vars()));
//CHECKS INPUT
//CHECK FOR EMPTY VARIABLES
    if(empty($symbol)) { throw new Exception("Invalid order. Trade symbol required."); } //check to see if empty
    if (!ctype_alnum($symbol)) {throw new Exception("Symbol must be alphanumeric!");}
//QUERY TO SEE IF SYMBOL EXISTS
    $symbolCheck = query("SELECT symbol FROM assets WHERE symbol =?", $symbol);
    if (count($symbolCheck) != 1) {throw new Exception("Incorrect Symbol. Not listed on the exchange! (7)");} //row count
    $symbol = strtoupper($symbol); //cast to UpperCase
    if(empty($type)) { throw new Exception("Invalid order. Trade type required."); } //check to see if empty
    if($type!='market' && $type!='limit' && $type!='marketprice'){ throw new Exception("Invalid order type."); }
    if(empty($side)) { throw new Exception("Invalid order. Trade side required."); } //check to see if empty
    if($side!='a' && $side!='b'){ throw new Exception("Invalid order side."); }
    if(!ctype_alpha($type) || !ctype_alpha($side)) { throw new Exception("Type and side must be alphabetic!");} //if symbol is alpha (alnum for alphanumeric)
//SET QUANTITY
    if($type!='marketprice')
    {
        if(empty($quantity)) { throw new Exception("Invalid order. Trade quantity required."); } //check to see if empty
        if($quantity>2000000000){ throw new Exception("Invalid order. Trade quantity exceeds limits."); }
        if($quantity < 0){throw new Exception("Quantity must be positive!");}
        if (preg_match("/^\d+$/", $quantity) == false) { throw new Exception("The quantity must enter a whole, positive integer."); } // if quantity is invalid (not a whole positive integer)
        if (!is_int($quantity) ) { throw new Exception("Quantity must be numeric!");} //if quantity is numeric
    }
//QUERY TO SEE IF USER EXISTS
    if(empty($id)) { throw new Exception("Invalid order. User required."); } //check to see if empty
    $userCheck = query("SELECT count(id) as number FROM users WHERE id =?", $id);
    if ($userCheck[0]["number"] != 1) {throw new Exception("No user exists!");} //row count

            $referenceID=($id); //concatenate
            $reference=uniqid($referenceID,true);  //unique id reference to trade
            $ouid = null;
            
    query("SET AUTOCOMMIT=0");
    query("START TRANSACTION;"); //initiate a SQL transaction in case of error between transaction and commit

//LIMIT ASK
    if($type=='limit' && $side=='a'){
        if(empty($price)){query("ROLLBACK");  query("SET AUTOCOMMIT=1"); throw new Exception("Invalid order. Limit order trade price required");}
        if($price>9000000000000000000){ query("ROLLBACK");  query("SET AUTOCOMMIT=1"); throw new Exception("Invalid order. Trade price exceeds limits."); }
        $price = setPrice($price);
        $transaction = 'ASK';
        $tradeAmount = 0;
        //CHECK TO SEE IF SELLER HAS ENOUGH SHARES
        $userQuantity = query("SELECT SUM(amount) AS quantity FROM ledger WHERE (user=? AND symbol=?)", $id, $symbol); //$unittype (ie USD or XBT) is native currency set in constants.php
        if(!empty($userQuantity)){$userQuantity = $userQuantity[0]["quantity"];}
        else{$userQuantity = 0;}
        //ENSURE SELLER HAS ENOUGH QUANTITY
        if($userQuantity <= 0) {query("ROLLBACK");  query("SET AUTOCOMMIT=1"); throw new Exception("Ask order failed. User (#" . $id . ")  quantity: " . $userQuantity);}
        elseif($userQuantity < $quantity) {query("ROLLBACK");  query("SET AUTOCOMMIT=1"); throw new Exception("Ask order not placed. Trade quantity (" . $quantity . ") exceeds user (" . $id . ") quantity (" . $userQuantity . ").");}
        elseif($userQuantity >= $quantity){
                $negquantity=$quantity*-1;
                if (query("INSERT INTO ledger (
                    category, 
                    user, symbol, amount, reference, 
                    xuser, xsymbol, xamount, xreference, 
                    status, note, biduid, askuid)
                    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)",
                    'order',
                    $id, $symbol, $negquantity, $reference,
                    1, $symbol, $quantity, $reference,
                    0, $transaction, $ouid, $ouid
                    ) === false) { query("ROLLBACK");  query("SET AUTOCOMMIT=1"); throw new Exception("Updates Accounts Failure 3"); }
        }
        else {query("ROLLBACK");  query("SET AUTOCOMMIT=1"); throw new Exception("Updates Portfolio Failure 4");}
    }
//LIMIT BUY
    elseif($type=='limit' && $side=='b'){
        if(empty($price)){query("ROLLBACK");  query("SET AUTOCOMMIT=1"); throw new Exception("Invalid order. Limit order trade price required");}
        if($price>9000000000000000000){ query("ROLLBACK");  query("SET AUTOCOMMIT=1"); throw new Exception("Invalid order. Trade price exceeds limits."); }
        $price = setPrice($price);
        $transaction = 'BID';
        //QUERY CASH & UPDATE
        $unitsQ = query("SELECT SUM(amount) AS units FROM ledger WHERE (user=? AND symbol=?)", $id, $unittype); //$unittype (ie USD or XBT) is native currency set in constants.php
        if(!empty($unitsQ[0]['units'])){$userUnits = $unitsQ[0]['units'];}	//convert array from query to value
        //IF USERUNITS IS EMPTY (0, NULL, etc.)
        else{query("ROLLBACK");  query("SET AUTOCOMMIT=1"); throw new Exception("Order failed. User (#" . $id . ") has unknown balance");}
        //CHECK FOR 0 or NEGATIVE BALANCE
        if($userUnits <= 0){query("ROLLBACK");  query("SET AUTOCOMMIT=1"); throw new Exception("Order failed. User (#" . $id . ") balance: " . $userUnits);}
        //DETERMINE TRADEAMOUNT BASED ON ORDER TYPE
        $tradeAmount = $price * $quantity;
        //ENSURE BUYER HAS ENOUGH FUNDS
        if ($userUnits < $tradeAmount) { query("ROLLBACK");  query("SET AUTOCOMMIT=1"); throw new Exception("Trade amount (" . $tradeAmount . ") exceeds user (" . $id . ") funds (" . $userUnits . ")." ); }
        elseif($userUnits >= $tradeAmount){
                $negtradeAmount=$tradeAmount*-1;
                if (query("INSERT INTO ledger (
                    category, 
                    user, symbol, amount, reference, 
                    xuser, xsymbol, xamount, xreference, 
                    status, note, biduid, askuid)
                    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)",
                    'order',
                    $id, $unittype, $negtradeAmount, $reference,
                    1, $unittype, $tradeAmount, $reference,
                    0, $transaction, $ouid, $ouid
                    ) === false) { query("ROLLBACK");  query("SET AUTOCOMMIT=1"); throw new Exception("Updates Accounts Failure 3"); }
        }
        else{  query("ROLLBACK");  query("SET AUTOCOMMIT=1"); throw new Exception("Updates Accounts Failure 5");}
    }
//MARKET ASK
    elseif($type=='market' && $side=='a'){
        $price=0;//market order doesn't require price
        $otherSide='b';
        //CHECK FOR LIMIT ORDERS SINCE MARKET ORDERS REQUIRE THEM
        $limitOrdersQ = query("SELECT SUM(quantity) AS limitorders FROM orderbook WHERE (type='limit' AND side=? AND symbol=?)", $otherSide, $symbol);
        $limitOrders = $limitOrdersQ[0]['limitorders'];
        if(empty($limitOrders)) { query("ROLLBACK");  query("SET AUTOCOMMIT=1"); throw new Exception("No limit orders.");}
        $transaction = 'ASK';
        $tradeAmount = 0;
        //CHECK TO SEE IF SELLER HAS ENOUGH SHARES
        $userQuantity = query("SELECT SUM(amount) AS quantity FROM ledger WHERE (user=? AND symbol=?)", $id, $symbol); //$unittype (ie USD or XBT) is native currency set in constants.php
        if(!empty($userQuantity)){$userQuantity = $userQuantity[0]["quantity"];}
        else{$userQuantity = 0;}
        //ENSURE SELLER HAS ENOUGH QUANTITY
        if($userQuantity <= 0) {query("ROLLBACK");  query("SET AUTOCOMMIT=1"); throw new Exception("Ask order failed. User (#" . $id . ")  quantity: " . $userQuantity);}
        elseif($userQuantity < $quantity) {query("ROLLBACK");  query("SET AUTOCOMMIT=1"); throw new Exception("Ask order not placed. Trade quantity (" . $quantity . ") exceeds user (" . $id . ") quantity (" . $userQuantity . ").");}
        elseif($userQuantity >= $quantity){
                $negquantity=$quantity*-1;
                if (query("INSERT INTO ledger (
                    category, 
                    user, symbol, amount, reference, 
                    xuser, xsymbol, xamount, xreference, 
                    status, note, biduid, askuid)
                    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)",
                    'order',
                    $id, $symbol, $negquantity, $reference,
                    1, $symbol, $quantity, $reference,
                    0, $transaction, $ouid, $ouid
                    ) === false) { query("ROLLBACK");  query("SET AUTOCOMMIT=1"); throw new Exception("Updates Accounts Failure 3"); }
        }
        else {query("ROLLBACK");  query("SET AUTOCOMMIT=1"); throw new Exception("Updates Portfolio Failure 2");}
    }
//MARKET BUY
    elseif($type=='market' && $side=='b'){
        $price=0;//market order doesn't require price
        $otherSide='a';
        //CHECK FOR LIMIT ORDERS SINCE MARKET ORDERS REQUIRE THEM
        $limitOrdersQ = query("SELECT SUM(quantity) AS limitorders FROM orderbook WHERE (type='limit' AND side=? AND symbol=?)", $otherSide, $symbol);
        $limitOrders = $limitOrdersQ[0]['limitorders'];
        if(empty($limitOrders)) { query("ROLLBACK");  query("SET AUTOCOMMIT=1"); throw new Exception("No limit orders.");}
        $transaction = 'BID';
        //QUERY CASH & UPDATE
        $unitsQ = query("SELECT SUM(amount) AS units FROM ledger WHERE (user=? AND symbol=?)", $id, $unittype); //$unittype (ie USD or XBT) is native currency set in constants.php
        if(!empty($unitsQ[0]['units'])){$userUnits = $unitsQ[0]['units'];}	//convert array from query to value
        //IF USERUNITS IS EMPTY (0, NULL, etc.)
        else{query("ROLLBACK");  query("SET AUTOCOMMIT=1"); throw new Exception("Order failed. User (#" . $id . ") has unknown balance");}
        //CHECK FOR 0 or NEGATIVE BALANCE
        if ($userUnits <= 0){query("ROLLBACK");  query("SET AUTOCOMMIT=1"); throw new Exception("Order failed. User (#" . $id . ") balance: " . $userUnits);}
        //DETERMINE TRADEAMOUNT BASED ON ORDER TYPE
        $tradeAmount = $unitsQ[0]['units'];  //market orders lock all of the users funds to ensure it goes through
        //ENSURE BUYER HAS ENOUGH FUNDS
        if ($userUnits < $tradeAmount) { query("ROLLBACK");  query("SET AUTOCOMMIT=1"); throw new Exception("Trade amount (" . $tradeAmount . ") exceeds user (" . $id . ") funds (" . $userUnits . ")." ); }
        elseif($userUnits >= $tradeAmount){
                $negtradeAmount=$tradeAmount*-1;
                if (query("INSERT INTO ledger (
                    category, 
                    user, symbol, amount, reference, 
                    xuser, xsymbol, xamount, xreference, 
                    status, note, biduid, askuid)
                    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)",
                    'order',
                    $id, $unittype, $negtradeAmount, $reference,
                    1, $unittype, $tradeAmount, $reference,
                    0, $transaction, $ouid, $ouid
                    ) === false) { query("ROLLBACK");  query("SET AUTOCOMMIT=1"); throw new Exception("Updates Accounts Failure 4"); }
        }
        else{  query("ROLLBACK");  query("SET AUTOCOMMIT=1"); throw new Exception("Updates Accounts Failure 5");}
    }
//CONVERT BUY
    elseif($type=='marketprice' && $side=='b'){
        if(empty($price)){query("ROLLBACK");  query("SET AUTOCOMMIT=1"); throw new Exception("Invalid order. Trade price required for this order type.");}
        if($price>9000000000000000000){ query("ROLLBACK");  query("SET AUTOCOMMIT=1"); throw new Exception("Invalid order. Trade price exceeds limits."); }
        $price = setPrice($price);
        $otherSide='a';
        //CHECK FOR LIMIT ORDERS SINCE MARKET ORDERS REQUIRE THEM
        $limitOrdersQ = query("SELECT SUM(quantity) AS limitorders FROM orderbook WHERE (type='limit' AND side=? AND symbol=?)", $otherSide, $symbol);
        $limitOrders = $limitOrdersQ[0]['limitorders'];
        if(empty($limitOrders)) { query("ROLLBACK");  query("SET AUTOCOMMIT=1"); throw new Exception("No limit orders.");}
        $transaction = 'BID';
        //QUERY CASH & UPDATE
        $unitsQ = query("SELECT SUM(amount) AS units FROM ledger WHERE (user=? AND symbol=?)", $id, $unittype); //$unittype (ie USD or XBT) is native currency set in constants.php
        if(!empty($unitsQ[0]['units'])){$userUnits = $unitsQ[0]['units'];}	//convert array from query to value
        //IF USERUNITS IS EMPTY (0, NULL, etc.)
        else{query("ROLLBACK");  query("SET AUTOCOMMIT=1"); throw new Exception("Order failed. User (#" . $id . ") has unknown balance");}
        //CHECK FOR 0 or NEGATIVE BALANCE
        if ($userUnits <= 0){query("ROLLBACK");  query("SET AUTOCOMMIT=1"); throw new Exception("Order failed. User (#" . $id . ") balance: " . $userUnits);}
        //DETERMINE TRADEAMOUNT BASED ON ORDER TYPE
        $tradeAmount = $price; //buyer only can spend what they listed in the price column
        //ENSURE BUYER HAS ENOUGH FUNDS
        if ($userUnits < $tradeAmount) { query("ROLLBACK");  query("SET AUTOCOMMIT=1"); throw new Exception("Trade amount (" . $tradeAmount . ") exceeds user (" . $id . ") funds (" . $userUnits . ")." ); }
        elseif($userUnits >= $tradeAmount){
                 $negtradeAmount=$tradeAmount*-1;
                if (query("INSERT INTO ledger (
                    category, 
                    user, symbol, amount, reference, 
                    xuser, xsymbol, xamount, xreference, 
                    status, note, biduid, askuid)
                    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)",
                    'order',
                    $id, $unittype, $negtradeAmount, $reference,
                    1, $unittype, $tradeAmount, $reference,
                    0, $transaction, $ouid, $ouid
                    ) === false) { query("ROLLBACK");  query("SET AUTOCOMMIT=1"); throw new Exception("Updates Accounts Failure 4"); }
          
        }
        else{  query("ROLLBACK");  query("SET AUTOCOMMIT=1"); throw new Exception("Updates Accounts Failure 5");}
        $type='market';//convert back to market order
    }
    else{query("ROLLBACK");  query("SET AUTOCOMMIT=1");  throw new Exception("Invalid order."); };


//INSERT INTO ORDERBOOK
    if (query("INSERT INTO orderbook (symbol, side, type, price, total, original, quantity, user, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
            $symbol, $side, $type, $price, $tradeAmount, $quantity, $quantity, $id, 1) === false)
    {
        query("ROLLBACK");  query("SET AUTOCOMMIT=1");
        apologize("Insert Orderbook Failure");
    }

//INSERT TO LEDGER
    $rows = query("SELECT LAST_INSERT_ID() AS uid"); //this takes the id to the next page
    $ouid = $rows[0]["uid"]; //sets sql query to var

    query("COMMIT;"); //If no errors, commit changes
    query("SET AUTOCOMMIT=1");

//UPDATE ORDER ID IN LEDGER
if($transaction=='BID'){query("UPDATE ledger SET biduid=? WHERE reference=?", $ouid, $reference);}
if($transaction=='ASK'){query("UPDATE ledger SET askuid=? WHERE reference=?", $ouid, $reference);}


    //PROCESS ORDERBOOK
    try {processOrderbook($symbol);}
    catch(Exception $e) {apologize($e->getMessage());}

    //RETURN
    return array($transaction, $symbol, $tradeAmount, $quantity);
}








////////////////////////////////////
//CANCEL ORDER
////////////////////////////////////
function cancelOrder($uid)
{ require 'constants.php';
$order = query("SELECT side, quantity, uid, symbol, type, price, id, total FROM orderbook WHERE uid = ?", $uid);
if(!empty($order)) {
@$side = $order[0]["side"];
@$quantity = $order[0]["quantity"];
@$uid = $order[0]["uid"];
@$symbol = $order[0]["symbol"];
@$type = $order[0]["type"];
@$price = $order[0]["price"];
@$id = $order[0]["id"];
query("SET AUTOCOMMIT=0");
query("START TRANSACTION;"); //initiate a SQL transaction in case of error between transaction and commit
@$total = $order[0]["total"];
if ($side == 'a') {
if (query("UPDATE portfolio SET quantity = (quantity + ?) WHERE (symbol = ? AND id = ?)", $quantity, $symbol, $id) === false) {
query("ROLLBACK");
query("SET AUTOCOMMIT=1");
throw new Exception("Failure Cancel 2");
}
} elseif ($side == 'b') {
if (query("UPDATE accounts SET units = (units + ?) WHERE id = ?", $total, $id) === false) //MOVE CASH TO units FUNDS
{
query("ROLLBACK");
query("SET AUTOCOMMIT=1");
throw new Exception("Failure Cancel 4");
}
}

//DELETE ORDER
if (query("UPDATE orderbook SET status=2 WHERE uid=?", $uid) === false) {
        query("ROLLBACK");
        query("SET AUTOCOMMIT=1");
        throw new Exception("Failure Cancel");
}

//UPDATE HISTORY
if ($quantity > 0) //to prevent spamming history with cleanup of orderbook of empty orders.
{ $total=($total*-1); //set to negative for calculations on order form total.
if (query("INSERT INTO history (id, ouid, transaction, symbol, quantity, price, total) VALUES (?, ?, ?, ?, ?, ?, ?)", $id, $uid, 'CANCEL', $symbol, $quantity, $price, $total) === false) { query("ROLLBACK"); query("SET AUTOCOMMIT=1"); throw new Exception("Insert History Failure 3c"); }
}
else //($quantity <=0)
{ $price=0; $total=0;//order was executed and the price listed was not the actual amount and the total was what was left over.
if (query("INSERT INTO history (id, ouid, transaction, symbol, quantity, price, total) VALUES (?, ?, ?, ?, ?, ?, ?)", $id, $uid, 'EXECUTED', $symbol, $quantity, $price, $total) === false) { query("ROLLBACK"); query("SET AUTOCOMMIT=1"); throw new Exception("Insert History Failure 3c"); }
}




if($loud!='quiet'){echo("<br>Canceled [ID: " . $id . ", UID:" . $uid . ", Side:" . $side . ", Type:" . $type . ", Total:" . $total . ", Quantity:" . $quantity . ", Symbol:" . $symbol . "]");}
query("COMMIT;"); //If no errors, commit changes
query("SET AUTOCOMMIT=1");
//PROCESS ORDERBOOK
try {processOrderbook($symbol);}
catch(Exception $e) {apologize($e->getMessage());}
} //!empty
//var_dump(get_defined_vars());
//throw new Exception("Market orders require limit orders. No ask limit orders for the bid market order. Deleting market order.");
}
}


////////////////////////////////////
//CHECK FOR WHICH ORDERS ARE AT TOP OF ORDERBOOK
////////////////////////////////////
function OrderbookTop($symbol)
{    require 'constants.php';
    if($loud!='quiet'){echo("<br>[" . $symbol . "] Conducting check for top of orderbook...");}

    //MARKET ORDERS SHOULD BE AT TOP IF THEY EXIST
    $marketOrders = query("SELECT * FROM orderbook WHERE (symbol = ? AND type = 'market' AND quantity>0 AND status=1) ORDER BY uid ASC LIMIT 0, 1", $symbol);
    if(!empty($marketOrders))
    {   @$marketSide=$marketOrders[0]["side"];
        $tradeType = 'market';
        if($marketSide == 'b') {
            $asks = query("SELECT * FROM orderbook WHERE (symbol = ? AND side = ? AND type = 'limit' AND quantity>0 AND status=1) ORDER BY price ASC, uid ASC LIMIT 0, 1", $symbol, 'a');
            while ((!empty($marketOrders)) && ($marketOrders[0]["side"] == 'b') && (empty($asks))) {  //cancel all bid market orders since there are no limit ask orders.
                cancelOrder($marketOrders[0]["uid"]);
                //ORDER BY FIRST UID
                $marketOrders = query("SELECT * FROM orderbook WHERE (symbol = ? AND side = ? AND type = 'market' AND quantity>0 AND status=1) ORDER BY uid ASC LIMIT 0, 1", $symbol, 'b');
                //ORDER BY LOWEST PRICE THEN FIRST UID
                $asks = query("SELECT * FROM orderbook WHERE (symbol = ? AND side = ? AND type = 'limit' AND quantity>0 AND status=1) ORDER BY price ASC, uid ASC LIMIT 0, 1", $symbol, 'a');
            }
            $marketOrders[0]["price"]=$asks[0]["price"]; //give it the same price so they execute
            $bids = $marketOrders;
        }    //assign top price to the ask since it is a bid market order
        elseif($marketSide == 'a')
        {
            $bids = query("SELECT * FROM orderbook WHERE (symbol = ? AND side = ? AND type = 'limit' AND quantity>0 AND status=1) ORDER BY price DESC, uid ASC LIMIT 0, 1", $symbol, 'b');
            while ((!empty($marketOrders)) && ($marketOrders[0]["side"] == 'a') && (empty($bids))) {
                cancelOrder($marketOrders[0]["uid"]);
                //ORDER BY FIRST UID
                $marketOrders = query("SELECT * FROM orderbook WHERE (symbol = ? AND side = ? AND type = 'market' AND quantity>0 AND status=1) ORDER BY uid ASC LIMIT 0, 1", $symbol, 'a');
                //ORDER BY HIGHEST PRICE THEN FIRST UID
                $bids = query("SELECT * FROM orderbook WHERE (symbol = ? AND side = ? AND type = 'limit' AND quantity>0 AND status=1) ORDER BY price DESC, uid ASC LIMIT 0, 1", $symbol, 'b');
            }
            $marketOrders[0]["price"]=$bids[0]["price"]; //give it the same price so they execute
            $asks = $marketOrders;
            //apologize(var_dump(get_defined_vars()));

        }   //assign top price to the bid since it is an ask market order
        else { throw new Exception("Market Side Error!"); }
    }
    elseif(empty($marketOrders))
    {   $bids = query("SELECT * FROM orderbook WHERE (symbol = ? AND side = ? AND type = 'limit' AND quantity>0 AND status=1) ORDER BY price DESC, uid ASC LIMIT 0, 1", $symbol, 'b');
        $asks = query("SELECT * FROM orderbook WHERE (symbol = ? AND side = ? AND type = 'limit' AND quantity>0 AND status=1) ORDER BY price ASC, uid ASC LIMIT 0, 1", $symbol, 'a');
        $tradeType = 'limit'; }
    else {throw new Exception("Market Order Error!");}

    $topOrders["asks"]=$asks;
    $topOrders["bids"]=$bids;
    $topOrders["tradeType"]=$tradeType;
    return($topOrders);
}


////////////////////////////////////
//EXCHANGE MARKET ALL
////////////////////////////////////         
function processOrderbook($symbol=null)
{   require 'constants.php';
    $startDate = time();
    $totalProcessed=0;
    if($loud!='quiet'){echo(date("Y-m-d H:i:s"));}



    if(empty($symbol))
    {
        //GET A QUERY OF ALL SYMBOLS FROM ASSETS
        $symbols = query("SELECT symbol FROM assets ORDER BY symbol ASC");

        //to prevent stopping on error for symbol (i.e. user does not have enough funds, all user orders deleted
        $error=1;
        while($error>0)
        {
            $error=0;
            foreach ($symbols as $symbol)
            {   if($loud!='quiet'){echo("<br><br>[" . $symbol["symbol"] . "] Processing orderbook...");}
                try {$orderbook = orderbook($symbol["symbol"]);
                    if($loud!='quiet'){echo('<br>[' . $symbol["symbol"] . '] Processed ' . $orderbook["orderProcessed"] . ' orders.');}
                    $totalProcessed = ($totalProcessed + $orderbook["orderProcessed"]);

                }
                catch(Exception $e) {
                    if($loud!='quiet'){echo('<br><div style="color:red;">Error: [' . $symbol["symbol"] . "] " . $e->getMessage() . '</div>');}
                    $error=$error+1;
                }
            }
        }

    }
    else
    {   if($loud!='quiet'){echo("<br>[" . $symbol . "] Processing orderbook...");}
        $symbolCheck = query("SELECT symbol FROM assets WHERE symbol =?", $symbol);
        if (count($symbolCheck) != 1) {throw new Exception("[" . $symbol . "] Incorrect Symbol. Not listed on the exchange! (5)");} //row count

        $error=1;
        while($error>0) {
            $error = 0;

            try {
                $orderbook = orderbook($symbol);
                if (isset($orderbook)) {
                    if($loud!='quiet'){echo('<br><div style="color:red; font-weight: bold;">[' . $symbol . '] Processed ' . $orderbook["orderProcessed"] . " orders</div>");}
                    $totalProcessed = ($totalProcessed + $orderbook["orderProcessed"]);
                }
            } catch (Exception $e) {
                if($loud!='quiet'){echo '<br>[' . $symbol . "] " . $e->getMessage();}
                $error = $error + 1;
            }
        }

    }
    if($loud!='quiet'){echo("<br>");}


    if($loud!='quiet'){ echo(date("Y-m-d H:i:s"));}
    $endDate =  time();
    $totalTime = $endDate-$startDate;
    if($totalTime != 0){$speed=$totalProcessed/$totalTime;}
    else{$speed=0;}
    if($loud!='quiet'){echo("<br><br><b>Processed " . $totalProcessed . " orders in " . $totalTime . " seconds! " . $speed . " orders/sec</b>");}

    return($totalProcessed);

}


////////////////////////////////////
//EXCHANGE MARKET
////////////////////////////////////
function orderbook($symbol)
{
    require 'constants.php';
    if($loud!='quiet'){echo("<br>[" . $symbol . "] Computing orderbook...");}

    //PROCESS MARKET ORDERS
    if(empty($symbol)){throw new Exception("No symbol selected!");}

    //QUERY TO SEE IF SYMBOL EXISTS
    $symbolCheck = query("SELECT symbol FROM assets WHERE symbol =?", $symbol);
    if (count($symbolCheck) != 1) {throw new Exception("Incorrect Symbol. Not listed on the exchange! (6)");} //row count

    //FIND TOP OF ORDERBOOK
    $topOrders = OrderbookTop($symbol); //try catch
    $asks = $topOrders["asks"];
    $bids = $topOrders["bids"];
    $tradeType = $topOrders["tradeType"];
    if (empty($asks) || empty($bids)) { $orderbook['orderProcessed'] = 0; return($orderbook); }  //{ throw new Exception("No bid limit orders. Unable to cross any orders."); }
    $topAskPrice = (float)$asks[0]["price"];
    $topBidPrice = (float)$bids[0]["price"];

    //PROCESS ORDERS
    $orderProcessed = 0; //orders processed
    $orderbook["symbol"]=$symbol;
    while ($topBidPrice >= $topAskPrice)
    {   @$topAskUID = (int)($asks[0]["uid"]); //order id; unique id
        @$topAskSymbol = ($asks[0]["symbol"]); //symbol of equity
        @$topAskSide = ($asks[0]["side"]); //bid or ask
        @$topAskDate = ($asks[0]["date"]);
        @$topAskType = ($asks[0]["type"]); //limit or market
        @$topAskSize = (int)($asks[0]["quantity"]); //size or quantity of trade
        @$topAskUser = (int)($asks[0]["user"]); //user id
        @$topBidUID = (int)($bids[0]["uid"]); //order id; unique id
        @$topBidSymbol = ($bids[0]["symbol"]);
        @$topBidSide = ($bids[0]["side"]); //bid or ask
        @$topBidDate = ($bids[0]["date"]);
        @$topBidType = ($bids[0]["type"]); //limit or market
        @$topBidSize = (int)($bids[0]["quantity"]);
        @$topBidUser = (int)($bids[0]["user"]);
        //NO LONGER USING//@$topBidUnits = (float)($bids[0]["total"]);

        $orderProcessed++; //orders processed plus 1

        if ($topBidPrice >= $topAskPrice) //TRADES ARE POSSIBLE
        { if($loud!='quiet'){echo("<br>[" . $symbol . "] Trade possible...");}

            //START TRANSACTION
            query("SET AUTOCOMMIT=0");
            query("START TRANSACTION;"); //initiate a SQL transaction in case of error between transaction and commit

            //DETERMINE EXECUTED PRICE (bid or ask) BY EARLIER DATE TIME using UID
            if ($topBidUID < $topAskUID) { $tradePrice = $topBidPrice;} //with dates or uid, the smaller one is older
            elseif ($topBidUID > $topAskUID) { $tradePrice = $topAskPrice; }
            else {  query("ROLLBACK"); query("SET AUTOCOMMIT=1"); throw new Exception("$topBidUID / $topAskUID Bid and Ask UID same!"); } //rollback on failure

            //DETERMINE TRADE SIZE
            if ($topBidSize <= $topAskSize) { $tradeSize = $topBidSize;}  //BID IS SMALLER SO DELETE AND UPDATE ASK ORDER
            elseif ($topBidSize > $topAskSize) { $tradeSize = $topAskSize;}
            else {  query("ROLLBACK"); query("SET AUTOCOMMIT=1"); throw new Exception("Bid Size or Ask Size Unknown!"); } //rollback on failure
            if ($tradeSize == 0) {throw new Exception("Trade Size is 0"); } //catch if trade size is null or zero

            //TRADE AMOUNT
            $tradeAmount = ($tradePrice * $tradeSize);
            if ($tradeAmount == 0) {throw new Exception("Trade Amount is 0");}

            //COMMISSION AMOUNT
            $commissionAmount = getCommission($tradeAmount);

            ////////////
            //ORDERBOOK
            /////////////

            //BID INFO------
            // UPDATE BID ORDER //REMOVE units FUNDS
            $BidUnitsQ = query("SELECT total FROM orderbook WHERE uid=?", $topBidUID);
            $BidUnits = (float)$BidUnitsQ[0]["total"];

            //IF BUYER DOESN'T HAVE ENOUGH FUNDS CANCEL ORDER
            if ($BidUnits < ($tradeAmount+$commissionAmount))
            {
                //IF MARKET ADJUST THE TRADESIZE DOWN IF USER DOESNT HAVE ENOUGH FUNDS.
                //CHECK TO SEE IF MARKET ORDER (if $bidtype='market')
                if($topBidType=='market') {
                    //IF LESS THAN 1, WE CANT GO SMALLER SO CANCEL
                    if ($tradeSize <= 1) {
                        query("ROLLBACK");
                        query("SET AUTOCOMMIT=1");
                        cancelOrder($topBidUID);
                        throw new Exception("Unable to make the order any smaller");
                    }
                    else{ //$tradeSize > 1
                        //CALCULATE WHAT THEY CAN AFFORD BASED ON ASKPRICE ($tradeSize = $bidUnits/$AskPrice)
                        $oldTradeSize = $tradeSize; //keep for history
                        $newTradeSize = floor($BidUnits / ($tradePrice + ($tradePrice*$commission)));
                        //ERROR CHECK TO SEE IF NEW SIZE IS BIGGER THAN OLD ONE. THIS PREVENTS IT FROM BEING LARGER THAN THE ASK SIZE
                        if ($newTradeSize > $oldTradeSize) {
                            query("ROLLBACK");
                            query("SET AUTOCOMMIT=1");
                            cancelOrder($topBidUID);
                            throw new Exception("New trade size is larger than old trade size");
                        }
                        //ERROR CHECK TO SEE IF LESS THAN 1
                        if ($newTradeSize < 1) {
                            query("ROLLBACK");
                            query("SET AUTOCOMMIT=1");
                            cancelOrder($topBidUID);
                            throw new Exception("New trade size is less than 1");
                        }
                        //CALCULATE NEW TRADEAMOUNT
                        $tradeSize = $newTradeSize;
                        $tradeAmount = ($tradePrice * $tradeSize);
                        //COMMISSION AMOUNT ON NEW AMOUNT
                        $commissionAmount = getCommission($tradeAmount);
                        //CHECK AGAIN WITH NEW AMOUNT
                        if ($BidUnits < ($tradeAmount+$commissionAmount)) {
                            query("ROLLBACK");
                            query("SET AUTOCOMMIT=1");
                            cancelOrder($topBidUID);
                            throw new Exception("Buyer does not have enough funds ($BidUnits, $tradeAmount, $commissionAmount). Buyers orders deleted (1)");}
                    }
                }
                //IF LIMIT AND NOT MARKET JUST DELETE ORDER DUE TO LACK OF FUNDS
                else
                { //($tradeType='limit') OR $topAskType='market'
                    query("ROLLBACK");
                    query("SET AUTOCOMMIT=1");
                    cancelOrder($topBidUID);
                    throw new Exception("Buyer does not have enough funds ($BidUnits, $tradeAmount, $commissionAmount). Buyers orders deleted (2)");
                }
            }

//UPDATE BID ORDER QUANTITY
if (query("UPDATE orderbook SET quantity=(quantity-?), total=(total-?) WHERE uid=?", $tradeSize, $tradeAmount, $topBidUID) === false)
  {query("ROLLBACK"); query("SET AUTOCOMMIT=1"); throw new Exception("Update OB Failure: #5"); }
 
            //ASK INFO------IS THIS REDUNDANT----
            $orderbookQuantity = query("SELECT quantity FROM orderbook WHERE (uid = ?)", $topAskUID);
            $orderbookQuantity = (int)$orderbookQuantity[0]["quantity"];
            //IF SELLER TRYING TO SELL MORE THEN THEY OWN CANCEL ORDER
            if ($tradeSize > $orderbookQuantity) { query("ROLLBACK"); query("SET AUTOCOMMIT=1"); cancelOrder($topAskUID); throw new Exception("$topAskUser Seller does not have enough quantity. Seller's order deleted."); }

            //UPDATE ASK ORDER //REMOVE QUANTITY
            if (query("UPDATE orderbook SET quantity=(quantity-?) WHERE uid=?", $tradeSize, $topAskUID) === false){query("ROLLBACK"); query("SET AUTOCOMMIT=1"); throw new Exception("Size OB Failure: #3"); } //rollback on failure

          //UPDATE ORDER STATUS AS CLOSED (STATUS=0)
            if($tradeSize==$topAskSize)
            {
                if (query("UPDATE orderbook SET status=0 WHERE uid=?", $topAskUID) === false){query("ROLLBACK"); query("SET AUTOCOMMIT=1"); throw new Exception("Update OB Failure: #5"); }
            }
            if($tradeSize==$topBidSize)
            {
                if (query("UPDATE orderbook SET status=0 WHERE uid=?", $topBidUID) === false){query("ROLLBACK"); query("SET AUTOCOMMIT=1"); throw new Exception("Update OB Failure: #5"); }
            }            
      

            ///////////
            //ACCOUNTS
            ///////////

            $referenceID=($topAskUID . $topBidUID . $tradeSize); //concatenate
            $reference=uniqid($referenceID,true);  //unique id reference to trade

            $negtradeSize=($tradeSize*-1);
            $negtradeAmount=($tradeAmount*-1); //WHAT ABOUT COMMISSION
//REMOVE ASK SHARES
            if (query("INSERT INTO ledger (category, user, symbol, amount, reference, xuser, xsymbol, xamount, xreference, status, note, biduid, askuid)
                        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)",
                    'trade',
                    $topAskUser, $symbol, $negtradeSize, $reference,
                    $topBidUser, $unittype, $tradeAmount, $reference,
                    0, 'ask-remove shares', $topBidUID, $topAskUID
                    ) === false) {query("ROLLBACK"); query("SET AUTOCOMMIT=1"); throw new Exception("Ledger Insert Failure"); }
//GIVE BIDDER SHARES
            if (query("INSERT INTO ledger (category, user, symbol, amount, reference, xuser, xsymbol, xamount, xreference, status, note, biduid, askuid)
                        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)",
                    'trade',
                    $topBidUser, $symbol, $tradeSize, $reference,
                    $topAskUser, $unittype, $negtradeAmount, $reference,
                    0, 'bid-give shares', $topBidUID, $topAskUID
                    ) === false) {query("ROLLBACK"); query("SET AUTOCOMMIT=1"); throw new Exception("Ledger Insert Failure"); }
//REMOVE BIDDER UNITS
            if (query("INSERT INTO ledger (category, user, symbol, amount, reference, xuser, xsymbol, xamount, xreference, status, note, biduid, askuid)
                        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)",
                    'trade',
                    $topBidUser, $unittype, $negtradeAmount, $reference,
                    $topAskUser, $symbol, $tradeSize, $reference,
                    0, 'bid-remove units', $topBidUID, $topAskUID
                    ) === false) {query("ROLLBACK"); query("SET AUTOCOMMIT=1"); throw new Exception("Ledger Insert Failure"); }
//GIVE ASK UNITS
            if (query("INSERT INTO ledger (category, user, symbol, amount, reference, xuser, xsymbol, xamount, xreference, status, note, biduid, askuid)
                        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)",
                    'trade',
                    $topAskUser, $unittype, $tradeAmount, $reference,
                    $topBidUser, $symbol, $negtradeSize, $reference,
                    0, 'ask-give units', $topBidUID, $topAskUID
                    ) === false) {query("ROLLBACK"); query("SET AUTOCOMMIT=1"); throw new Exception("Ledger Insert Failure"); }
//COMMISSION
            $negCommission=($commissionAmount*-1);
            if (query("INSERT INTO ledger (category, user, symbol, amount, reference, xuser, xsymbol, xamount, xreference, status, note, biduid, askuid)
                        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)",
                    'trade',
                    $adminid, $unittype, $commissionAmount, $reference,
                    $topBidUser, $unittype, $negCommission, $reference,
                    0, 'admin-give commission', $topBidUID, $topAskUID
                    ) === false) {query("ROLLBACK"); query("SET AUTOCOMMIT=1"); throw new Exception("Ledger Insert Failure"); }
            if (query("INSERT INTO ledger (category, user, symbol, amount, reference, xuser, xsymbol, xamount, xreference, status, note, biduid, askuid)
                        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)",
                    'trade',
                    $topBidUser, $unittype, $negCommission, $reference,
                    $adminid, $unittype, $commissionAmount, $reference,
                    0, 'bid-remove commission', $topBidUID, $topAskUID
                    ) === false) {query("ROLLBACK"); query("SET AUTOCOMMIT=1"); throw new Exception("Ledger Insert Failure"); }


            //ALL THINGS OKAY, COMMIT TRANSACTIONS
            query("COMMIT;"); //If no errors, commit changes
            query("SET AUTOCOMMIT=1");



            //LAST TRADE INFO TO RETURN ON FUNCTION
            $orderbook['topAskPrice'] = ($asks[0]["price"]); //limit price
            $orderbook['topAskUID'] = ($asks[0]["uid"]);  //order id; unique id
            $orderbook['topAskSymbol'] = ($asks[0]["symbol"]); //symbol of equity
            $orderbook['topAskSide'] = ($asks[0]["side"]);  //bid or ask
            $orderbook['topAskDate'] = ($asks[0]["date"]);
            $orderbook['topAskType'] =  ($asks[0]["type"]);  //limit or market
            $orderbook['topAskSize'] = ($asks[0]["quantity"]); //size or quantity of trade
            $orderbook['topAskUser'] = ($asks[0]["user"]); //user id
            $orderbook['topBidPrice'] = ($bids[0]["price"]);
            $orderbook['topBidUID'] = ($bids[0]["uid"]);//order id; unique id
            $orderbook['topBidSymbol'] = ($bids[0]["symbol"]);
            $orderbook['topBidSide'] = ($bids[0]["side"]);  //bid or ask
            $orderbook['topBidDate'] = ($bids[0]["date"]);
            $orderbook['topBidType'] = ($bids[0]["type"]); //limit or market
            $orderbook['topBidSize'] = ($bids[0]["quantity"]);
            $orderbook['topBidUser'] = ($bids[0]["user"]);
            if (empty($tradePrice)) {$tradePrice = 0;} //if no trades so should be empty
            $orderbook['tradePrice'] = $tradePrice;
            $orderbook['tradeType'] = $tradeType;

            if($loud!='quiet'){
                echo("<br><br><b>Executed: Trade Price: " . (number_format($orderbook['tradePrice'],2,".",",")) . " x " . $tradeSize . " (" . $orderbook['tradeType'] . ")</b>");
                echo("<br>Ask Price: " . (number_format($orderbook['topAskPrice'],2,".",",")));
                echo("<br>Ask UID: " . $orderbook['topAskUID']); //order id; unique id
                echo("<br>Ask Symbol: " . $orderbook['topAskSymbol']); //symbol of equity
                echo("<br>Ask Side: " . $orderbook['topAskSide']); //bid or ask
                echo("<br>Ask Date: " . $orderbook['topAskDate']);
                echo("<br>Ask Type: " . $orderbook['topAskType']);  //limit or market
                echo("<br>Ask Size: " . $orderbook['topAskSize']); //size or quantity of trade
                echo("<br>Ask User: " .  $orderbook['topAskUser']); //user id
                echo("<br>Bid Price: " . (number_format($orderbook['topBidPrice'],2,".",","))); //might need to make (float)
                echo("<br>Bid UID: " . $orderbook['topBidUID']); //order id; unique id
                echo("<br>Bid Symbol: " . $orderbook['topBidSymbol']);
                echo("<br>Bid Side: " . $orderbook['topBidSide']); //bid or ask
                echo("<br>Bid Date: " . $orderbook['topBidDate']);
                echo("<br>Bid Type: " . $orderbook['topBidType']); //limit or market
                echo("<br>Bid Size: " . $orderbook['topBidSize']);
                echo("<br>Bid User: " . $orderbook['topBidUser']);
            }

            //FIND TOP OF ORDERBOOK
            $topOrders = OrderbookTop($symbol); //try catch
            $asks = $topOrders["asks"];
            $bids = $topOrders["bids"];
            if (empty($asks) || empty($bids)) { $orderbook['orderProcessed'] = 0; return($orderbook); }  //{ throw new Exception("No bid limit orders. Unable to cross any orders."); }
            $topAskPrice = (float)$asks[0]["price"];
            $topBidPrice = (float)$bids[0]["price"];
            $tradeType = $topOrders["tradeType"];





        } //IF TRADES ARE POSSIBLE
        elseif($topBidPrice < $topAskPrice){throw new Exception("No trades possible!");}
        //{throw new Exception("No trades possible!");} //TRADES ARE NOT POSSIBLE
        else {throw new Exception("ERROR!");}

    } //BOTTOM of WHILE STATEMENT


    $orderbook['orderProcessed'] = $orderProcessed;
    return($orderbook);

} //END OF FUNCTION




















///////////////////////
//
//
//
//  PUBLIC OFFERING SETTINGS
//
//
//



////////////////////////////////////
//Update Stock
////////////////////////////////////
function updateSymbol($symbol, $newSymbol, $userid, $name, $type, $url, $rating, $description)
{
    query("SET AUTOCOMMIT=0");
    query("START TRANSACTION;"); //initiate a SQL transaction in case of error between transaction and commit
    //CHECK TO SEE IF SYMBOL EXISTS
    $symbolCheck = query("SELECT symbol FROM assets WHERE symbol =?", $symbol);//Checks to see if they already own stock to determine if we should insert or update tables
    $countOwnersRows = count($symbolCheck);
    if ($countOwnersRows != 1) {query("ROLLBACK"); query("SET AUTOCOMMIT=1"); throw new Exception("Symbol does not exist."); }

    if (!empty($userid)) { if (query("UPDATE assets SET userid = ? WHERE symbol = ?", $userid, $symbol) === false) {query("ROLLBACK"); query("SET AUTOCOMMIT=1");throw new Exception("Failure to update");} }
    if (!empty($name)) { if (query("UPDATE assets SET name = ? WHERE symbol = ?", $name, $symbol) === false) {query("ROLLBACK"); query("SET AUTOCOMMIT=1");throw new Exception("Failure to update");} }
    if (!empty($type)) { if (query("UPDATE assets SET type=? WHERE symbol = ?", $type, $symbol) === false) {query("ROLLBACK"); query("SET AUTOCOMMIT=1");throw new Exception("Failure to update");} }
    if (!empty($url)) { if (query("UPDATE assets SET url = ? WHERE symbol = ?", $url, $symbol) === false) {query("ROLLBACK"); query("SET AUTOCOMMIT=1");throw new Exception("Failure to update");} }
    if (!empty($rating)) { if (query("UPDATE assets SET rating = ? WHERE symbol = ?", $rating, $symbol) === false) {query("ROLLBACK"); query("SET AUTOCOMMIT=1");throw new Exception("Failure to update");} }
    if (!empty($description)) { if (query("UPDATE assets SET description = ? WHERE symbol = ?", $description, $symbol) === false) {query("ROLLBACK"); query("SET AUTOCOMMIT=1");throw new Exception("Failure to update");} }
    if (!empty($newSymbol)) {
        if (query("UPDATE assets SET symbol = ? WHERE symbol = ?", $newSymbol, $symbol) === false) {query("ROLLBACK"); query("SET AUTOCOMMIT=1");throw new Exception("Failure to update");}
        if (query("UPDATE orderbook SET symbol = ? WHERE symbol = ?", $newSymbol, $symbol) === false) {query("ROLLBACK"); query("SET AUTOCOMMIT=1");throw new Exception("Failure to update");}
        if (query("UPDATE ledger SET symbol = ? WHERE symbol = ?", $newSymbol, $symbol) === false) {query("ROLLBACK"); query("SET AUTOCOMMIT=1");throw new Exception("Failure to update");}
    }

    query("COMMIT;"); //If no errors, commit changes
    query("SET AUTOCOMMIT=1");
    return("$symbol Update successful!");
}



////////////////////////////////////
//DE LISTING
////////////////////////////////////
function delist($symbol)
{
//cancel all orders on orderbook
    if(query("UPDATE orderbook SET status=2 WHERE (symbol = ?)", $symbol) === false){ query("ROLLBACK");  query("SET AUTOCOMMIT=1"); throw new Exception("RA1"); }

    //delete from assets
    if (query("DELETE FROM assets WHERE (symbol = ?)", $symbol) === false) { query("ROLLBACK"); query("SET AUTOCOMMIT=1"); throw new Exception("Failure: #RA2"); }

    //update ledger to delist
    if(query("UPDATE ledger SET status=2 WHERE (symbol = ? AND (status=0 OR status=1))", $symbol) === false){ query("ROLLBACK");  query("SET AUTOCOMMIT=1"); throw new Exception("RA1"); }

}


////////////////////////////////////
//Public Offering (initial)
////////////////////////////////////
function publicOffering($symbol, $name, $userid, $issued, $type, $fee, $url, $rating, $description)
{   require 'constants.php';
    $transaction='INITIAL'; //public offering
    if (empty($symbol)) {query("ROLLBACK"); query("SET AUTOCOMMIT=1"); throw new Exception("You must enter symbol."); }
    $symbol = strtoupper($symbol); //cast to UpperCase

    query("SET AUTOCOMMIT=0");
    query("START TRANSACTION;"); //initiate a SQL transaction in case of error between transaction and commit


    if (empty($fee)) { $fee=0; }
    if (empty($userid)) {query("ROLLBACK"); query("SET AUTOCOMMIT=1");throw new Exception("You must enter owner user # when conducting a follow on public offering ."); }
    $feeQuantity = ($issued * $fee);
    $ownersQuantity = ($issued - $feeQuantity);
    $price = 0; //since a public offering, cost is 0.

    //CHECK TO SEE IF SYMBOL EXISTS
    $symbolCheck = query("SELECT symbol FROM assets WHERE symbol =?", $symbol);//Checks to see if they already own stock to determine if we should insert or update tables
    $countOwnersRows = count($symbolCheck);
    if ($countOwnersRows != 0) {query("ROLLBACK"); query("SET AUTOCOMMIT=1"); throw new Exception("Symbol already exsists."); }

    //INSERT ASSET
    if (query("INSERT INTO assets (`symbol`, `name`, `userid`, `fee`, `issued`, `url`, `type`, `rating`, `description`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)", $symbol, $name, $userid, $fee, $issued, $url, $type, $rating, $description) === false)  //create IPO
    { query("ROLLBACK"); query("SET AUTOCOMMIT=1"); throw new Exception("Failure to insert into assets"); }

    $referenceID=($userid . $issued); //concatenate
    $reference=uniqid($referenceID,true);  //unique id reference to trade

//INSERT SHARES INTO PORTFOLIO OF OWNER MINUS FEE
//GIVE BIDDER SHARES
    if (query("INSERT INTO ledger (category, user, symbol, amount, reference, xuser, xsymbol, xamount, xreference, status, note)
                        VALUES (?,?,?,?,?,?,?,?,?,?,?)",
            'trade',
            $userid, $symbol, $ownersQuantity, $reference,
            $adminid, $unittype, 0, $reference,
            0, 'IPO') === false) {query("ROLLBACK"); query("SET AUTOCOMMIT=1"); throw new Exception("Ledger Insert Failure"); }

//INSERT FEE SHARES INTO PORTFOLIO OF ADMIN
    if (query("INSERT INTO ledger (category, user, symbol, amount, reference, xuser, xsymbol, xamount, xreference, status, note)
                        VALUES (?,?,?,?,?,?,?,?,?,?,?)",
            'trade',
            $adminid, $symbol, $feeQuantity, $reference,
            $userid, $unittype, 0, $reference,
            0, 'IPO Fee') === false) {query("ROLLBACK"); query("SET AUTOCOMMIT=1"); throw new Exception("Ledger Insert Failure"); }

    query("COMMIT;"); //If no errors, commit changes
    query("SET AUTOCOMMIT=1");

    return("$symbol public offering successful!");
} //function



////////////////////////////////////
//Public Offering (follow on)
////////////////////////////////////
function publicOffering2($symbol, $userid, $issued, $fee)
{   require 'constants.php';

    $transaction='ISSUE'; //public offering
    query("SET AUTOCOMMIT=0");
    query("START TRANSACTION;"); //initiate a SQL transaction in case of error between transaction and commit
    $symbol = strtoupper($symbol); //cast to UpperCase
    //CHECK TO SEE IF SYMBOL EXISTS
    $symbolCheck = query("SELECT symbol FROM assets WHERE symbol =?", $symbol);//Checks to see if they already own stock to determine if we should insert or update tables
    $countOwnersRows = count($symbolCheck);
    if ($countOwnersRows != 1) {query("ROLLBACK"); query("SET AUTOCOMMIT=1"); apologize("Symbol does not exsist."); }
    $feeQuantity = ($issued * $fee);
    $ownersQuantity = ($issued - $feeQuantity);
    $price = 0; //since a public offering, cost is 0.
    if (query("UPDATE assets SET issued=(issued+?) WHERE symbol = ?", $issued, $symbol) === false)
    {query("ROLLBACK"); query("SET AUTOCOMMIT=1");apologize("Failure to update assets"); }
//INSERT SHARES INTO PORTFOLIO OF OWNER MINUS FEE

    $referenceID=($userid . $issued); //concatenate
    $reference=uniqid($referenceID,true);  //unique id reference to trade

//INSERT SHARES INTO PORTFOLIO OF OWNER MINUS FEE
//GIVE BIDDER SHARES
    if (query("INSERT INTO ledger (category, user, symbol, amount, reference, xuser, xsymbol, xamount, xreference, status, note)
                        VALUES (?,?,?,?,?,?,?,?,?,?,?)",
            'trade',
            $userid, $symbol, $ownersQuantity, $reference,
            $adminid, $unittype, 0, $reference,
            0, '2PO') === false) {query("ROLLBACK"); query("SET AUTOCOMMIT=1"); throw new Exception("Ledger Insert Failure"); }

//INSERT FEE SHARES INTO PORTFOLIO OF ADMIN
    if (query("INSERT INTO ledger (category, user, symbol, amount, reference, xuser, xsymbol, xamount, xreference, status, note)
                        VALUES (?,?,?,?,?,?,?,?,?,?,?)",
            'trade',
            $adminid, $symbol, $feeQuantity, $reference,
            $userid, $unittype, 0, $reference,
            0, '2PO Fee') === false) {query("ROLLBACK"); query("SET AUTOCOMMIT=1"); throw new Exception("Ledger Insert Failure"); }


    query("COMMIT;"); //If no errors, commit changes
    query("SET AUTOCOMMIT=1");
    return("$symbol Public offering successful!");
} //function


////////////////////////////////////
//Public Offering (reverse)
////////////////////////////////////
function removeQuantity($symbol, $userid, $issued)
{   require 'constants.php';

    $transaction='REMOVE'; //reverse offering
    query("SET AUTOCOMMIT=0");
    query("START TRANSACTION;"); //initiate a SQL transaction in case of error between transaction and commit
    $symbol = strtoupper($symbol); //cast to UpperCase

    //CHECK TO SEE IF SYMBOL EXISTS
    $symbolCheck = query("SELECT symbol FROM assets WHERE symbol =?", $symbol);//Checks to see if they already own stock to determine if we should insert or update tables
    $countOwnersRows = count($symbolCheck);
    if ($countOwnersRows != 1) {query("ROLLBACK"); query("SET AUTOCOMMIT=1"); apologize("Symbol does not exist."); }
    if (query("UPDATE assets SET issued=(issued-?) WHERE symbol = ?", $issued, $symbol) === false)
    {query("ROLLBACK"); query("SET AUTOCOMMIT=1");apologize("Failure to update assets"); }



    //CHECK TO SEE IF USER HAS ENOUGH FOR REMOVAL
    $ownerPortfolio = query("SELECT SUM(amount) AS total FROM ledger WHERE (user=? AND symbol=?", $userid, $symbol); //$unittype (ie USD or XBT) is native currency set in constants.php
    $userQuantity = (float)$ownerPortfolio[0]["total"];
    if ($userQuantity < $issued) {query("ROLLBACK"); query("SET AUTOCOMMIT=1"); apologize("User does not have enough for removal."); exit();} //update portfolio} //updates if stock already owned

//INSERT TRADE 
    $referenceID=($userid . $issued); //concatenate
    $reference=uniqid($referenceID,true);  //unique id reference to trade

//INSERT SHARES INTO PORTFOLIO OF OWNER MINUS FEE
//GIVE BIDDER SHARES
    $negIssued=($issued*-1);
    if (query("INSERT INTO ledger (category, user, symbol, amount, reference, xuser, xsymbol, xamount, xreference, status, note)
                        VALUES (?,?,?,?,?,?,?,?,?,?,?)",
            'trade',
            $userid, $symbol, $negIssued, $reference,
            $userid, $unittype, 0, $reference,
            0, 'RO') === false) {query("ROLLBACK"); query("SET AUTOCOMMIT=1"); throw new Exception("Ledger Insert Failure"); }


    query("COMMIT;"); //If no errors, commit changes
    query("SET AUTOCOMMIT=1");
    return("$symbol Public offering successful!");
} //function









////////////////////////////////////
//CHECK FOR NEGATIVE VALUES
////////////////////////////////////
function negativeValues()
{   require 'constants.php';
    /*BROKEN-NEED TO CALCULATE TOTAL SINCE NO LONGER PART OF ORDER BOOK
        $negativeValueOrderbook = query("SELECT quantity, total, uid FROM orderbook WHERE (quantity < 0 OR total < 0) LIMIT 0, 1");
        if(!empty($negativeValueOrderbook)) {
            throw new Exception("<br>Negative Orderbook Values! UID: " . $negativeValueOrderbook[0]["uid"] . ", Quantity: " . $negativeValueOrderbook[0]["quantity"] . ", Total: " . $negativeValueOrderbook[0]["total"]);}
        //eventually all users order using id
    */
    /*BROKEN-NEED TO CALCULATE USERS FUNDS FIRST
        $negativeValueAccounts = query("SELECT units, id FROM accounts WHERE (units < 0) LIMIT 0, 1");
        if(!empty($negativeValueAccounts))
        {
            if(query("UPDATE orderbook SET type = 2 WHERE user = ?", $negativeValueAccounts[0]["user"]) === false){ apologize("Unable to cancel all orders!"); }
            throw new Exception("<br>Canceled All Users (ID:" . $negativeValueAccounts[0]["id"] . ") orders due to negative account balance. Current balance: " . $negativeValueAccounts[0]["units"]);}
        //eventually all users order using id     throw new Exception(var_dump(get_defined_vars()));
    */
}





