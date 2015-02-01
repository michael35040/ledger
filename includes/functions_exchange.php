<?php
//throw new Exception(var_dump(get_defined_vars()));
function transfer($quantity, $symbol, $userid)
{
}

///////////////////////////////
//CONVERT INTEGER PRICE TO FLOAT
///////////////////////////////
function getPrice($price)
{
    require 'constants.php';

    //$price = $price/(pow(10, $decimalplaces););
    //$price = $price/(10**$decimalplaces);
    $price = $price/1000;

    //setlocale(LC_MONETARY, 'en_US');
    //$price = money_format('%(#10n', $price) . "\n"; // ($        1,234.57)
    return($price);
}
function setPrice($price)
{
    require 'constants.php';

    if (preg_match("/^([0-9.]+)$/", $price) == false) {apologize("You submitted an invalid price.");}
    if ($price<0){ apologize("Price must be positive!");} //if quantity is numeric

    //$price = $price*(pow(10, $decimalplaces););
    //$price = $price*(10**$decimalplaces);
    $price = $price*1000;

    $price=floor($price);
    //if (!is_int($price)) { apologize("Price must be numeric!");} //if quantity is numeric
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





//apologize(var_dump(get_defined_vars()));

////////////////////////////////////
//PLACE ORDER
////////////////////////////////////
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
        $userQuantity = query("SELECT quantity FROM portfolio WHERE (id = ? AND symbol = ?)", $id, $symbol);//
        if(!empty($userQuantity)){$userQuantity = $userQuantity[0]["quantity"];}
        else{$userQuantity = 0;}
        //ENSURE SELLER HAS ENOUGH QUANTITY
        if($userQuantity <= 0) {query("ROLLBACK");  query("SET AUTOCOMMIT=1"); throw new Exception("Ask order failed. User (#" . $id . ")  quantity: " . $userQuantity);}
        elseif($userQuantity < $quantity) {query("ROLLBACK");  query("SET AUTOCOMMIT=1"); throw new Exception("Ask order not placed. Trade quantity (" . $quantity . ") exceeds user (" . $id . ") quantity (" . $userQuantity . ").");}
        elseif($userQuantity >= $quantity){if(query("UPDATE portfolio SET quantity = (quantity - ?) WHERE (id = ? AND symbol = ?)", $quantity, $id, $symbol) === false){ query("ROLLBACK");  query("SET AUTOCOMMIT=1"); throw new Exception("Updates Accounts Failure 3"); }}
        else {query("ROLLBACK");  query("SET AUTOCOMMIT=1"); throw new Exception("Updates Portfolio Failure 4");}
    }
//LIMIT BUY
    elseif($type=='limit' && $side=='b'){
        if(empty($price)){query("ROLLBACK");  query("SET AUTOCOMMIT=1"); throw new Exception("Invalid order. Limit order trade price required");}
        if($price>9000000000000000000){ query("ROLLBACK");  query("SET AUTOCOMMIT=1"); throw new Exception("Invalid order. Trade price exceeds limits."); }
        $price = setPrice($price);
        $transaction = 'BID';
        //QUERY CASH & UPDATE
        $unitsQ =	query("SELECT units FROM accounts WHERE id = ?", $id); //query db how much cash user has
        if(!empty($unitsQ[0]['units'])){$userUnits = $unitsQ[0]['units'];}	//convert array from query to value
        //IF USERUNITS IS EMPTY (0, NULL, etc.)
        else{query("ROLLBACK");  query("SET AUTOCOMMIT=1"); throw new Exception("Order failed. User (#" . $id . ") has unknown balance");}
        //CHECK FOR 0 or NEGATIVE BALANCE
        if($userUnits <= 0){query("ROLLBACK");  query("SET AUTOCOMMIT=1"); throw new Exception("Order failed. User (#" . $id . ") balance: " . $userUnits);}
        //DETERMINE TRADEAMOUNT BASED ON ORDER TYPE
        $tradeAmount = $price * $quantity;
        //ENSURE BUYER HAS ENOUGH FUNDS
        if ($userUnits < $tradeAmount) { query("ROLLBACK");  query("SET AUTOCOMMIT=1"); throw new Exception("Trade amount (" . $tradeAmount . ") exceeds user (" . $id . ") funds (" . $userUnits . ")." ); }
        elseif($userUnits >= $tradeAmount){if(query("UPDATE accounts SET units = (units - ?) WHERE id = ?", $tradeAmount, $id) === false) { query("ROLLBACK");  query("SET AUTOCOMMIT=1"); throw new Exception("Updates Accounts Failure 4");}}
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
        $userQuantity = query("SELECT quantity FROM portfolio WHERE (id = ? AND symbol = ?)", $id, $symbol);//
        if(!empty($userQuantity)){$userQuantity = $userQuantity[0]["quantity"];}
        else{$userQuantity = 0;}
        //ENSURE SELLER HAS ENOUGH QUANTITY
        if($userQuantity <= 0) {query("ROLLBACK");  query("SET AUTOCOMMIT=1"); throw new Exception("Ask order failed. User (#" . $id . ")  quantity: " . $userQuantity);}
        elseif($userQuantity < $quantity) {query("ROLLBACK");  query("SET AUTOCOMMIT=1"); throw new Exception("Ask order not placed. Trade quantity (" . $quantity . ") exceeds user (" . $id . ") quantity (" . $userQuantity . ").");}
        elseif($userQuantity >= $quantity){if(query("UPDATE portfolio SET quantity = (quantity - ?) WHERE (id = ? AND symbol = ?)", $quantity, $id, $symbol) === false){ query("ROLLBACK");  query("SET AUTOCOMMIT=1"); throw new Exception("Updates Accounts Failure 3"); }}
        else {query("ROLLBACK");  query("SET AUTOCOMMIT=1"); throw new Exception("Updates Portfolio Failure 4");}
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
        $unitsQ =	query("SELECT units FROM accounts WHERE id = ?", $id); //query db how much cash user has
        if(!empty($unitsQ[0]['units'])){$userUnits = $unitsQ[0]['units'];}	//convert array from query to value
        //IF USERUNITS IS EMPTY (0, NULL, etc.)
        else{query("ROLLBACK");  query("SET AUTOCOMMIT=1"); throw new Exception("Order failed. User (#" . $id . ") has unknown balance");}
        //CHECK FOR 0 or NEGATIVE BALANCE
        if ($userUnits <= 0){query("ROLLBACK");  query("SET AUTOCOMMIT=1"); throw new Exception("Order failed. User (#" . $id . ") balance: " . $userUnits);}
        //DETERMINE TRADEAMOUNT BASED ON ORDER TYPE
        $tradeAmount = $unitsQ[0]['units'];  //market orders lock all of the users funds to ensure it goes through
        //ENSURE BUYER HAS ENOUGH FUNDS
        if ($userUnits < $tradeAmount) { query("ROLLBACK");  query("SET AUTOCOMMIT=1"); throw new Exception("Trade amount (" . $tradeAmount . ") exceeds user (" . $id . ") funds (" . $userUnits . ")." ); }
        elseif($userUnits >= $tradeAmount){if(query("UPDATE accounts SET units = (units - ?) WHERE id = ?", $tradeAmount, $id) === false) { query("ROLLBACK");  query("SET AUTOCOMMIT=1"); throw new Exception("Updates Accounts Failure 4");}}
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
        $unitsQ =	query("SELECT units FROM accounts WHERE id = ?", $id); //query db how much cash user has
        if(!empty($unitsQ[0]['units'])){$userUnits = $unitsQ[0]['units'];}	//convert array from query to value
        //IF USERUNITS IS EMPTY (0, NULL, etc.)
        else{query("ROLLBACK");  query("SET AUTOCOMMIT=1"); throw new Exception("Order failed. User (#" . $id . ") has unknown balance");}
        //CHECK FOR 0 or NEGATIVE BALANCE
        if ($userUnits <= 0){query("ROLLBACK");  query("SET AUTOCOMMIT=1"); throw new Exception("Order failed. User (#" . $id . ") balance: " . $userUnits);}
        //DETERMINE TRADEAMOUNT BASED ON ORDER TYPE
        $tradeAmount = $price; //buyer only can spend what they listed in the price column
        //ENSURE BUYER HAS ENOUGH FUNDS
        if ($userUnits < $tradeAmount) { query("ROLLBACK");  query("SET AUTOCOMMIT=1"); throw new Exception("Trade amount (" . $tradeAmount . ") exceeds user (" . $id . ") funds (" . $userUnits . ")." ); }
        elseif($userUnits >= $tradeAmount){if(query("UPDATE accounts SET units = (units - ?) WHERE id = ?", $tradeAmount, $id) === false) { query("ROLLBACK");  query("SET AUTOCOMMIT=1"); throw new Exception("Updates Accounts Failure 4");}}
        else{  query("ROLLBACK");  query("SET AUTOCOMMIT=1"); throw new Exception("Updates Accounts Failure 5");}
        $type='market';//convert back to market order
    }
    else{query("ROLLBACK");  query("SET AUTOCOMMIT=1");  throw new Exception("Invalid order."); };


//INSERT INTO ORDERBOOK
    if (query("INSERT INTO orderbook (symbol, side, type, price, total, quantity, id) VALUES (?, ?, ?, ?, ?, ?, ?)", $symbol, $side, $type, $price, $tradeAmount, $quantity, $id) === false) { query("ROLLBACK");  query("SET AUTOCOMMIT=1"); throw new Exception("Insert Orderbook Failure"); }
//UPDATE HISTORY (ON ORDERS PAGE)
    $rows = query("SELECT LAST_INSERT_ID() AS uid"); //this takes the id to the next page
    $ouid = $rows[0]["uid"]; //sets sql query to var
    if (query("INSERT INTO history (id, ouid, transaction, symbol, quantity, price, total) VALUES (?, ?, ?, ?, ?, ?, ?)", $id, $ouid, $transaction, $symbol, $quantity, $price, $tradeAmount) === false) { query("ROLLBACK");  query("SET AUTOCOMMIT=1"); throw new Exception("Insert History Failure 3"); }
    query("COMMIT;"); //If no errors, commit changes
    query("SET AUTOCOMMIT=1");

    //PROCESS ORDERBOOK
    try {processOrderbook($symbol);}
    catch(Exception $e) {apologize($e->getMessage());}

    //RETURN
    return array($transaction, $symbol, $tradeAmount, $quantity);
}





////////////////////////////////////
//CHECK FOR NEGATIVE VALUES
////////////////////////////////////
function negativeValues()
{   require 'constants.php';
    $negativeValueOrderbook = query("SELECT quantity, total, uid FROM orderbook WHERE (quantity < 0 OR total < 0) LIMIT 0, 1");
    if(!empty($negativeValueOrderbook)) {
        throw new Exception("<br>Negative Orderbook Values! UID: " . $negativeValueOrderbook[0]["uid"] . ", Quantity: " . $negativeValueOrderbook[0]["quantity"] . ", Total: " . $negativeValueOrderbook[0]["total"]);}
    //eventually all users order using id
    $negativeValueAccounts = query("SELECT units, id FROM accounts WHERE (units < 0) LIMIT 0, 1");
    if(!empty($negativeValueAccounts))
    {
        if(query("UPDATE orderbook SET type = 'cancel' WHERE id = ?", $negativeValueAccounts[0]["id"]) === false){ apologize("Unable to cancel all orders!"); }
        throw new Exception("<br>Canceled All Users (ID:" . $negativeValueAccounts[0]["id"] . ") orders due to negative account balance. Current balance: " . $negativeValueAccounts[0]["units"]);}
    //eventually all users order using id     throw new Exception(var_dump(get_defined_vars()));
}



////////////////////////////////////
//CANCEL ORDER
////////////////////////////////////
function cancelOrder($uid)
{
    //set order uid to canceled

}



////////////////////////////////////
//CHECK FOR WHICH ORDERS ARE AT TOP OF ORDERBOOK
////////////////////////////////////
function OrderbookTop($symbol)
{    require 'constants.php';
    if($loud!='quiet'){echo("<br>[" . $symbol . "] Conducting check for top of orderbook...");}

    //MARKET ORDERS SHOULD BE AT TOP IF THEY EXIST
    $marketOrders = query("SELECT * FROM orderbook WHERE (symbol = ? AND type = 'market' AND quantity>0) ORDER BY uid ASC LIMIT 0, 1", $symbol);
    if(!empty($marketOrders))
    {   @$marketSide=$marketOrders[0]["side"];
        $tradeType = 'market';
        if($marketSide == 'b') {
            $asks = query("SELECT * FROM orderbook WHERE (symbol = ? AND side = ? AND type = 'limit' AND quantity>0) ORDER BY price ASC, uid ASC LIMIT 0, 1", $symbol, 'a');
            while ((!empty($marketOrders)) && ($marketOrders[0]["side"] == 'b') && (empty($asks))) {  //cancel all bid market orders since there are no limit ask orders.
                cancelOrder($marketOrders[0]["uid"]);
                //ORDER BY FIRST UID
                $marketOrders = query("SELECT * FROM orderbook WHERE (symbol = ? AND side = ? AND type = 'market' AND quantity>0) ORDER BY uid ASC LIMIT 0, 1", $symbol, 'b');
                //ORDER BY LOWEST PRICE THEN FIRST UID
                $asks = query("SELECT 	* FROM orderbook WHERE (symbol = ? AND side = ? AND type = 'limit' AND quantity>0) ORDER BY price ASC, uid ASC LIMIT 0, 1", $symbol, 'a');
            }
            $marketOrders[0]["price"]=$asks[0]["price"]; //give it the same price so they execute
            $bids = $marketOrders;
        }    //assign top price to the ask since it is a bid market order
        elseif($marketSide == 'a')
        {
            $bids = query("SELECT * FROM orderbook WHERE (symbol = ? AND side = ? AND type = 'limit' AND quantity>0) ORDER BY price DESC, uid ASC LIMIT 0, 1", $symbol, 'b');
            while ((!empty($marketOrders)) && ($marketOrders[0]["side"] == 'a') && (empty($bids))) {
                cancelOrder($marketOrders[0]["uid"]);
                //ORDER BY FIRST UID
                $marketOrders = query("SELECT * FROM orderbook WHERE (symbol = ? AND side = ? AND type = 'market' AND quantity>0) ORDER BY uid ASC LIMIT 0, 1", $symbol, 'a');
                //ORDER BY HIGHEST PRICE THEN FIRST UID
                $bids = query("SELECT * FROM orderbook WHERE (symbol = ? AND side = ? AND type = 'limit' AND quantity>0) ORDER BY price DESC, uid ASC LIMIT 0, 1", $symbol, 'b');
            }
            $marketOrders[0]["price"]=$bids[0]["price"]; //give it the same price so they execute
            $asks = $marketOrders;
            //apologize(var_dump(get_defined_vars()));

        }   //assign top price to the bid since it is an ask market order
        else { throw new Exception("Market Side Error!"); }
    }
    elseif(empty($marketOrders))
    {   $bids = query("SELECT * FROM orderbook WHERE (symbol = ? AND side = ? AND type = 'limit' AND quantity>0) ORDER BY price DESC, uid ASC LIMIT 0, 1", $symbol, 'b');
        $asks = query("SELECT * FROM orderbook WHERE (symbol = ? AND side = ? AND type = 'limit' AND quantity>0) ORDER BY price ASC, uid ASC LIMIT 0, 1", $symbol, 'a');
        $tradeType = 'limit'; }
    else {throw new Exception("Market Order Error!");}

    $topOrders["asks"]=$asks;
    $topOrders["bids"]=$bids;
    $topOrders["tradeType"]=$tradeType;
    return($topOrders);
}
//throw new Exception(var_dump(get_defined_vars())); //dump all variables if i hit error


////////////////////////////////////
//EXCHANGE MARKET ALL
////////////////////////////////////            apologize(var_dump(get_defined_vars()));

//apologize(var_dump(get_defined_vars()));
function processOrderbook($symbol=null)
{   require 'constants.php';
    $startDate = time();
    $totalProcessed=0;
    if($loud!='quiet'){echo(date("Y-m-d H:i:s"));}



    if(empty($symbol))
    {
        //GET A QUERY OF ALL SYMBOLS FROM ASSETS
        $symbols =	query("SELECT symbol FROM assets ORDER BY symbol ASC");

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
{ require 'constants.php';
//   apologize(var_dump(get_defined_vars())); //dump all variables if i hit error
    if($loud!='quiet'){echo("<br>[" . $symbol . "] Computing orderbook...");}
    //$adminid = 1;

    require 'constants.php';

    //PROCESS MARKET ORDERS
    if(empty($symbol)){throw new Exception("No symbol selected!");}

    //QUERY TO SEE IF SYMBOL EXISTS
    $symbolCheck = query("SELECT symbol FROM assets WHERE symbol =?", $symbol);
    if (count($symbolCheck) != 1) {throw new Exception("Incorrect Symbol. Not listed on the exchange! (6)");} //row count

    //FIND TOP OF ORDERBOOK
    $topOrders = OrderbookTop($symbol); //try catch
    $asks = $topOrders["asks"];
    $bids = $topOrders["bids"];

    if (empty($asks) || empty($bids)) { $orderbook['orderProcessed'] = 0; return($orderbook); }  //{ throw new Exception("No bid limit orders. Unable to cross any orders."); }

    $topAskPrice = (float)$asks[0]["price"];
    $topBidPrice = (float)$bids[0]["price"];
    $tradeType = $topOrders["tradeType"];

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
        @$topAskUser = (int)($asks[0]["id"]); //user id
        @$topBidUID = (int)($bids[0]["uid"]); //order id; unique id
        @$topBidSymbol = ($bids[0]["symbol"]);
        @$topBidSide = ($bids[0]["side"]); //bid or ask
        @$topBidDate = ($bids[0]["date"]);
        @$topBidType = ($bids[0]["type"]); //limit or market
        @$topBidSize = (int)($bids[0]["quantity"]);
        @$topBidUser = (int)($bids[0]["id"]);
        @$topBidUnits = (float)($bids[0]["total"]);

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
            $orderbookUnitsQ = query("SELECT total FROM orderbook WHERE uid=?", $topBidUID);
            $orderbookUnits = (float)$orderbookUnitsQ[0]["total"];
            //throw new Exception(var_dump(get_defined_vars()));

            //IF BUYER DOESN'T HAVE ENOUGH FUNDS CANCEL ORDER
            if ($orderbookUnits < ($tradeAmount+$commissionAmount))
            {
                //IF MARKET ADJUST THE TRADESIZE DOWN.
                //CHECK TO SEE IF MARKET ORDER (if $bidtype='market')
                if($topBidType='market') {
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
                        $newTradeSize = floor($orderbookUnits / ($tradePrice + ($tradePrice*$commission)));
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
                        if ($orderbookUnits < ($tradeAmount+$commissionAmount)) {
                            query("ROLLBACK");
                            query("SET AUTOCOMMIT=1");
                            cancelOrder($topBidUID);
                            throw new Exception("Buyer does not have enough funds. Buyers orders deleted (1)");}

                        //INSERT INTO HISTORY TO ACCOUNT FOR DIFFERENCE (updated bid order ID, adjust size based on value...)
                        if (query("INSERT INTO history (id, ouid, transaction, symbol, quantity, price, total) VALUES (?, ?, ?, ?, ?, ?, ?)", $topBidUser, $topBidUID, 'QTY CHANGE', $symbol, $newTradeSize, $tradePrice, $tradeAmount) === false) {
                            query("ROLLBACK");
                            query("SET AUTOCOMMIT=1");
                            throw new Exception("Insert History Failure 3");
                        }
                    }

                }
                //IF LIMIT
                else
                { //($tradeType='limit') OR $topAskType='market'
                    query("ROLLBACK");
                    query("SET AUTOCOMMIT=1");
                    cancelOrder($topBidUID);
                    throw new Exception("Buyer does not have enough funds. Buyers orders deleted (2)");
                }

            }
            if (query("UPDATE orderbook SET quantity=(quantity-?), total=(total-?) WHERE uid=?", $tradeSize, $tradeAmount, $topBidUID) === false)
            {query("ROLLBACK"); query("SET AUTOCOMMIT=1"); throw new Exception("Update OB Failure: #5"); }


            //ASK INFO------
            $orderbookQuantity = query("SELECT quantity FROM orderbook WHERE (uid = ?)", $topAskUID);
            $orderbookQuantity = (int)$orderbookQuantity[0]["quantity"];
            // throw new Exception(var_dump(get_defined_vars()));

            //REMOVE SHARES FROM ASK USER
            //IF SELLER TRYING TO SELL MORE THEN THEY OWN CANCEL ORDER
            if ($tradeSize > $orderbookQuantity) { query("ROLLBACK"); query("SET AUTOCOMMIT=1"); cancelOrder($topAskUID);
                throw new Exception("$topAskUser Seller does not have enough quantity. Seller's order deleted."); }
            //UPDATE ASK ORDER //REMOVE QUANTITY
            if (query("UPDATE orderbook SET quantity=(quantity-?) WHERE uid=?", $tradeSize, $topAskUID) === false)
            {query("ROLLBACK"); query("SET AUTOCOMMIT=1"); throw new Exception("Size OB Failure: #3"); } //rollback on failure


            ///////////
            //ACCOUNTS
            ///////////
            //GIVE UNITS TO ASK USER MINUS COMMISSION
            if (query("UPDATE accounts SET units = (units + ? - ?) WHERE id = ?", $tradeAmount, $commissionAmount, $topAskUser) === false)
            { query("ROLLBACK"); query("SET AUTOCOMMIT=1"); throw new Exception("Update Accounts Failure: #11"); }
            //GIVE COMMISSION TO ADMIN/OWNER
            if ($commissionAmount > 0)
            {   if (query("UPDATE accounts SET units = (units + ?) WHERE id = ?", $commissionAmount, $adminid) === false)
            { query("ROLLBACK"); query("SET AUTOCOMMIT=1"); throw new Exception("Update Accounts Failure: #11a"); }
            }

            ///////////
            //PORTFOLIO
            ///////////
            //CHECK THE QUANTITY FOR INSERT OR DELETE
            $askPortfolio = query("SELECT quantity, symbol FROM portfolio WHERE (symbol=? AND id=?)", $symbol, $topAskUser);

            //QUICK ERROR CHECK
            $askPortfolioRows = count($askPortfolio);
            if ($askPortfolioRows != 1){ query("ROLLBACK"); query("SET AUTOCOMMIT=1"); throw new Exception("$topAskUser does not own any $symbol! #20a"); }

            if(empty($askPortfolio[0]["quantity"])){$askPortfolio=0;}
            else{$askPortfolio = $askPortfolio[0]["quantity"];}

            $askOrderbook =	query("SELECT SUM(quantity) AS quantity FROM orderbook WHERE (id=? AND symbol =? AND side='a')", $topAskUser, $symbol);	  // query user's portfolio
            if(empty($askOrderbook[0]["quantity"])){$askOrderbook=0;}
            else{$askOrderbook = $askOrderbook[0]["quantity"];}

            // DELETE IF TRADE IS ALL THEY OWN//WOULD BE 0 SINCE THE REST WOULD BE IN ORDERBOOK
            if($askPortfolio < 0 || $askOrderbook < 0) { query("ROLLBACK"); query("SET AUTOCOMMIT=1"); throw new Exception("Failure: #14aa. OB or P negative value." . $topAskUser); }
            if($askPortfolio == 0 && $askOrderbook == 0) {if (query("DELETE FROM portfolio WHERE (id = ? AND symbol = ?)", $topAskUser, $symbol) === false)
            { query("ROLLBACK"); query("SET AUTOCOMMIT=1"); throw new Exception("Failure: #14"); } }
            // QUANTITY WERE REMOVED WHEN PUT INTO ORDERBOOK BUT NEED TO UPDATE PRICE
            elseif($askPortfolio > 0 || $askOrderbook > 0) {if (query("UPDATE portfolio SET price = (price - ? - ?) WHERE (id = ? AND symbol = ?)", $tradeAmount, $commissionAmount, $topAskUser, $symbol) === false)
            { query("ROLLBACK"); query("SET AUTOCOMMIT=1"); throw new Exception("Failure: #14a"); } }
            else { query("ROLLBACK"); query("SET AUTOCOMMIT=1"); throw new Exception("Failure: #14b. Seller has no portfolio." . $topAskUser); }

            //GIVE SHARES TO BID USER
            $bidQuantityRows = query("SELECT symbol FROM portfolio WHERE (id = ? AND symbol = ?)", $topBidUser, $symbol); //Checks to see if they already own stock to determine if we should insert or update tables
            $countRows = count($bidQuantityRows);
            //INSERT IF NOT ALREADY OWNED
            if ($countRows == 0)
            {   if (query("INSERT INTO portfolio (id, symbol, quantity, price) VALUES (?, ?, ?, ?)", $topBidUser, $symbol, $tradeSize, $tradeAmount) === false) {
                query("ROLLBACK"); query("SET AUTOCOMMIT=1"); throw new Exception("Failure: #18, Bid Quantity & Trade Size"); } }
            //UPDATE IF ALREADY OWNED
            elseif($countRows == 1)
            {   if (query("UPDATE portfolio  SET quantity = (quantity + ?), price = (price + ?) WHERE (id = ? AND symbol = ?)", $tradeSize, $tradeAmount, $topBidUser, $symbol) === false)
            {   query("ROLLBACK"); query("SET AUTOCOMMIT=1"); throw new Exception("Failure: #19"); } }
            //ERROR: TO MANY ROWS
            else { query("ROLLBACK"); query("SET AUTOCOMMIT=1"); throw new Exception("$topBidUser has too many $symbol Portfolios! #20b"); }  //throw new Exception(var_dump(get_defined_vars()));




            //query("ROLLBACK"); query("SET AUTOCOMMIT=1"); apologize(var_dump(get_defined_vars()));       //dump all variables if i hit error


            ///////////
            //TRADE
            ///////////
            if (query("INSERT INTO trades (symbol, buyer, seller, quantity, price, commission, total, type, bidorderuid, askorderuid) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)", $symbol, $topBidUser, $topAskUser, $tradeSize, $tradePrice, $commissionAmount, $tradeAmount, $tradeType, $topBidUID, $topAskUID) === false)  { query("ROLLBACK"); query("SET AUTOCOMMIT=1"); throw new Exception("Failure: #21a"); }

            //ALL THINGS OKAY, COMMIT TRANSACTIONS
            query("COMMIT;"); //If no errors, commit changes
            query("SET AUTOCOMMIT=1");

            /* */
            //LAST TRADE INFO TO RETURN ON FUNCTION
            //if ($topAskType == 'market') { $topAskPrice = 'market'; } //null//$tradePrice;}     //since the do while loop gives it the next orders price, not the last traded
            //if ($topBidType == 'market') { $topBidPrice = 'market'; } //null// $tradePrice;}     //since the do while loop gives it the next orders price, not the last traded
            $orderbook['topAskPrice'] = ($asks[0]["price"]); //limit price
            $orderbook['topAskUID'] = ($asks[0]["uid"]);  //order id; unique id
            $orderbook['topAskSymbol'] = ($asks[0]["symbol"]); //symbol of equity
            $orderbook['topAskSide'] = ($asks[0]["side"]);  //bid or ask
            $orderbook['topAskDate'] = ($asks[0]["date"]);
            $orderbook['topAskType'] =  ($asks[0]["type"]);  //limit or market
            $orderbook['topAskSize'] = ($asks[0]["quantity"]); //size or quantity of trade
            $orderbook['topAskUser'] = ($asks[0]["id"]); //user id
            $orderbook['topBidPrice'] = ($bids[0]["price"]);
            $orderbook['topBidUID'] = ($bids[0]["uid"]);//order id; unique id
            $orderbook['topBidSymbol'] = ($bids[0]["symbol"]);
            $orderbook['topBidSide'] = ($bids[0]["side"]);  //bid or ask
            $orderbook['topBidDate'] = ($bids[0]["date"]);
            $orderbook['topBidType'] = ($bids[0]["type"]); //limit or market
            $orderbook['topBidSize'] = ($bids[0]["quantity"]);
            $orderbook['topBidUnits'] = ($bids[0]["total"]);
            $orderbook['topBidUser'] = ($bids[0]["id"]);
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

//catch(Exception $e) {echo('<br>Message: [' . $symbol . "] " . $e->getMessage());}

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
        if (query("UPDATE history SET symbol = ? WHERE symbol = ?", $newSymbol, $symbol) === false) {query("ROLLBACK"); query("SET AUTOCOMMIT=1");throw new Exception("Failure to update");}
        if (query("UPDATE orderbook SET symbol = ? WHERE symbol = ?", $newSymbol, $symbol) === false) {query("ROLLBACK"); query("SET AUTOCOMMIT=1");throw new Exception("Failure to update");}
        if (query("UPDATE portfolio SET symbol = ? WHERE symbol = ?", $newSymbol, $symbol) === false) {query("ROLLBACK"); query("SET AUTOCOMMIT=1");throw new Exception("Failure to update");}
        if (query("UPDATE trades SET symbol = ? WHERE symbol = ?", $newSymbol, $symbol) === false) {query("ROLLBACK"); query("SET AUTOCOMMIT=1");throw new Exception("Failure to update");}
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
    if(query("UPDATE orderbook SET type = ('cancel') WHERE (symbol = ?)", $symbol) === false){ query("ROLLBACK");  query("SET AUTOCOMMIT=1"); throw new Exception("RA1"); }

    //delete from assets
    if (query("DELETE FROM assets WHERE (symbol = ?)", $symbol) === false) { query("ROLLBACK"); query("SET AUTOCOMMIT=1"); throw new Exception("Failure: #RA2"); }

    //delete from portfolio
    if (query("DELETE FROM portfolio WHERE (symbol = ?)", $symbol) === false) { query("ROLLBACK"); query("SET AUTOCOMMIT=1"); throw new Exception("Failure: #RA3"); }

    if (query("INSERT INTO history (id, ouid, transaction, symbol, quantity, price, total) VALUES (?, ?, ?, ?, ?, ?, ?)", 1, 0, 'DELISTED', $symbol, 0, 0, 0) === false) { query("ROLLBACK");  query("SET AUTOCOMMIT=1"); throw new Exception("Failure: #RA4"); }

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

//INSERT SHARES INTO PORTFOLIO OF OWNER MINUS FEE
    $ownerPortfolio = query("SELECT symbol FROM portfolio WHERE (id =? AND symbol =?)", $userid, $symbol);//Checks to see if they already own stock to determine if we should insert or update tables
    $countOwnersRows = count($ownerPortfolio);
    if ($countOwnersRows == 0)
    {
        if (query("INSERT INTO portfolio (id, symbol, quantity, price) VALUES (?, ?, ?, ?)", $userid, $symbol, $ownersQuantity, $price) === false)
        {query("ROLLBACK"); query("SET AUTOCOMMIT=1"); throw new Exception("Insert to Owners Portfolio Error");} //update portfolio
    } //updates if stock already owned
    elseif ($countOwnersRows == 1) //else update db
    {   if (query("UPDATE portfolio  SET quantity = (quantity + ?), price = (price + ?) WHERE (id = ? AND symbol = ?)", $ownersQuantity, $price, $userid, $symbol) === false)
    {query("ROLLBACK"); query("SET AUTOCOMMIT=1"); throw new Exception("Update to Owners Portfolio Error");} //update portfolio
    }
    else
    {
        query("ROLLBACK"); query("SET AUTOCOMMIT=1"); throw new Exception("Public Offering Error: Too many symbol rows in assets. $symbol / $userid");
    } //apologizes if first two conditions are not meet

//INSERT TRADE INTO PORTFOLIO OF OWNER MINUS FEE
    if (query("INSERT INTO trades (symbol, buyer, seller, quantity, price, commission, total, type, bidorderuid, askorderuid) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)", $symbol, $userid, $userid, $ownersQuantity, $price, $fee, 0, $transaction, 0, 0) === false)
    {query("ROLLBACK"); query("SET AUTOCOMMIT=1"); throw new Exception("Insert Owner Trade Error");}

//INSERT FEE SHARES INTO PORTFOLIO OF ADMIN
    $adminPortfolio = query("SELECT symbol FROM portfolio WHERE (id = ? AND symbol = ?)", $adminid, $symbol);//Checks to see if they already own stock to determine if we should insert or update tables
    $adminPortfolio = count($adminPortfolio);
    if ($adminPortfolio == 0)
    { if (query("INSERT INTO portfolio (id, symbol, quantity, price) VALUES (?, ?, ?, ?)", $adminid, $symbol, $feeQuantity, $price) === false) {
        query("ROLLBACK"); //rollback on failure
        query("SET AUTOCOMMIT=1");            throw new Exception("Insert Fee to Admin Error");} //update portfolio
    } //updates if stock already owned
    elseif ($adminPortfolio == 1) //else update db
    {
        if (query("UPDATE portfolio  SET quantity = (quantity + ?), price = (price + ?) WHERE (id = ? AND symbol = ?)", $feeQuantity, $price, $adminid, $symbol) === false) {
            query("ROLLBACK"); //rollback on failure
            query("SET AUTOCOMMIT=1");
            throw new Exception("Update to Admin Portfolio Error");
        } //update portfolio
    } else {
        query("ROLLBACK"); //rollback on failure
        query("SET AUTOCOMMIT=1");
        //apologize(var_dump(get_defined_vars()));       //dump all variables if i hit error
        throw new Exception("Admin Portfolio Error");
    } //apologizes if first two conditions are not meet


    //INSERT TRADE SHARES INTO PORTFOLIO OF ADMIN
    if (query("INSERT INTO trades (symbol, buyer, seller, quantity, price, commission, total, type, bidorderuid, askorderuid) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)", $symbol, $adminid, $userid, $feeQuantity, $price, $fee, 0, $transaction, 0, 0) === false) {
        query("ROLLBACK"); //rollback on failure
        query("SET AUTOCOMMIT=1");
        throw new Exception("Insert Admin Trade Error");
    }


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
    $ownerPortfolio = query("SELECT symbol FROM portfolio WHERE (id =? AND symbol =?)", $userid, $symbol);//Checks to see if they already own stock to determine if we should insert or update tables
    $countOwnersRows = count($ownerPortfolio);
    if ($countOwnersRows == 0)
    {
        if (query("INSERT INTO portfolio (id, symbol, quantity, price) VALUES (?, ?, ?, ?)", $userid, $symbol, $ownersQuantity, $price) === false)
        {query("ROLLBACK"); query("SET AUTOCOMMIT=1"); apologize("Insert to Owners Portfolio Error1");} //update portfolio
    } //updates if stock already owned
    elseif ($countOwnersRows == 1) //else update db
    {   if (query("UPDATE portfolio  SET quantity = (quantity + ?), price = (price + ?) WHERE (id = ? AND symbol = ?)", $ownersQuantity, $price, $userid, $symbol) === false)
    {query("ROLLBACK"); query("SET AUTOCOMMIT=1"); apologize("Update to Owners Portfolio Error2");} //update portfolio
    }
    else
    {
        query("ROLLBACK"); query("SET AUTOCOMMIT=1"); apologize("Public Offering Error: Too many symbol rows in assets. $symbol / $userid");
    } //apologizes if first two conditions are not meet
//INSERT TRADE INTO PORTFOLIO OF OWNER MINUS FEE
    if (query("INSERT INTO trades (symbol, buyer, seller, quantity, price, commission, total, type, bidorderuid, askorderuid) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)", $symbol, $userid, $userid, $ownersQuantity, $price, $fee, 0, $transaction, 0, 0) === false)
    {query("ROLLBACK"); query("SET AUTOCOMMIT=1"); apologize("Insert Owner Trade Error");}
//INSERT FEE SHARES INTO PORTFOLIO OF ADMIN
    $adminPortfolio = query("SELECT symbol FROM portfolio WHERE (id = ? AND symbol = ?)", $adminid, $symbol);//Checks to see if they already own stock to determine if we should insert or update tables
    $adminPortfolio = count($adminPortfolio);
    if ($adminPortfolio == 0)
    { if (query("INSERT INTO portfolio (id, symbol, quantity, price) VALUES (?, ?, ?, ?)", $adminid, $symbol, $feeQuantity, $price) === false) {
        query("ROLLBACK"); //rollback on failure
        query("SET AUTOCOMMIT=1");            apologize("Insert Fee to Admin Error");} //update portfolio
    } //updates if stock already owned
    elseif ($adminPortfolio == 1) //else update db
    {
        if (query("UPDATE portfolio  SET quantity = (quantity + ?), price = (price + ?) WHERE (id = ? AND symbol = ?)", $feeQuantity, $price, $adminid, $symbol) === false) {
            query("ROLLBACK"); //rollback on failure
            query("SET AUTOCOMMIT=1");
            apologize("Update to Admin Portfolio Error");
        } //update portfolio
    } else {
        query("ROLLBACK"); //rollback on failure
        query("SET AUTOCOMMIT=1");
        //apologize(var_dump(get_defined_vars()));       //dump all variables if i hit error
        apologize("Admin Portfolio Error");
    } //apologizes if first two conditions are not meet
    //INSERT TRADE SHARES INTO PORTFOLIO OF ADMIN
    if($feeQuantity>0){
        if (query("INSERT INTO trades (symbol, buyer, seller, quantity, price, commission, total, type, bidorderuid, askorderuid) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)", $symbol, $adminid, $userid, $feeQuantity, $price, $fee, 0, $transaction, 0, 0) === false) {
            query("ROLLBACK"); //rollback on failure
            query("SET AUTOCOMMIT=1");
            apologize("Insert Admin Trade Error");
        }
    }

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

    //CHECK TO SEE IF SYMBOL EXISTS
    $ownerPortfolio = query("SELECT symbol FROM portfolio WHERE (id =? AND symbol =?)", $userid, $symbol);//Checks to see if they already own stock to determine if we should insert or update tables
    $countOwnersRows = count($ownerPortfolio);
    if ($countOwnersRows == 0) {query("ROLLBACK"); query("SET AUTOCOMMIT=1"); apologize("No assets to remove.");} //update portfolio} //updates if stock already owned
    elseif($countOwnersRows == 1) {if (query("UPDATE portfolio  SET quantity = (quantity - ?) WHERE (id = ? AND symbol = ?)", $issued, $userid, $symbol) === false) {query("ROLLBACK"); query("SET AUTOCOMMIT=1"); apologize("Update to Owners Portfolio Error2");}} //update portfolio
    else {query("ROLLBACK"); query("SET AUTOCOMMIT=1"); apologize("Public Offering Error: Too many symbol rows in assets. $symbol / $userid");} //apologizes if first two conditions are not meet

    //CHECK TO SEE IF USER HAS ENOUGH FOR REMOVAL
    $ownerPortfolio = query("SELECT quantity FROM portfolio WHERE (id =? AND symbol =?)", $userid, $symbol);//Checks to see if they already own stock to determine if we should insert or update tables
    $userQuantity = $ownerPortfolio[0]["quantity"];
    if ($userQuantity < $issued) {query("ROLLBACK"); query("SET AUTOCOMMIT=1"); apologize("User does not have enough for removal."); exit();} //update portfolio} //updates if stock already owned

//INSERT TRADE 
    if (query("INSERT INTO trades (symbol, buyer, seller, quantity, price, commission, total, type, bidorderuid, askorderuid) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)", $symbol, $userid, $userid, $issued, 0, 0, 0, $transaction, 0, 0) === false)
    {query("ROLLBACK"); query("SET AUTOCOMMIT=1"); apologize("Insert Owner Trade Error");}

    query("COMMIT;"); //If no errors, commit changes
    query("SET AUTOCOMMIT=1");
    return("$symbol Public offering successful!");
} //function











