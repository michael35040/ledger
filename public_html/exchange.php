<?php
require("../includes/config.php");  // configuration
$id = $_SESSION["id"]; //get id from session
if ($_SERVER["REQUEST_METHOD"] == "POST")// if form is submitted
{
    @$symbol = $_POST["symbol"];	//assign post variables to local variables, not really needed but makes coding easier
    @$type = $_POST["type"]; //limit or market
    @$side = $_POST["side"]; //buy/bid or sell/ask 
    @$quantity = (int)$_POST["quantity"]; //not set on market orders
    @$dollar = (int)$_POST["dollar"]; //not set on market orders
    @$cents = (int)$_POST["cents"]; //not set on market orders

    $dollar = sanatize("quantity", $dollar);
    if($cents>99 || $cents<0){apologize("Incorrect decimal!");}
    //if($cents!=0 && $cents!=25 && $cents!=50 && $cents!=75){apologize("Incorrect decimal!");}
    $cents=$cents/100;
    $price=$dollar+$cents;

    //FORMATS AND SCRUBS VARIABLES
    $quantity = sanatize("quantity", $quantity);
    $symbol = sanatize("alphabet", $symbol);
    $type = sanatize("alphabet", $type);
    $side = sanatize("alphabet", $side);
    $symbol = strtoupper($symbol); //cast to UpperCase

    try {placeOrder($symbol, $type, $side, $quantity, $price, $id);}
    catch(Exception $e) {apologize($e->getMessage());}

    redirect("orders.php");
    } //if post
else
{
    $assets =	query("SELECT symbol FROM assets ORDER BY symbol ASC");	  // query user's portfolio

    $stocks = []; //to send to next page
    foreach ($assets as $row)		// for each of user's stocks
    {   $stock = [];
        $stock["symbol"] = $row["symbol"]; //set variable from stock info
        $stocksQ =	query("SELECT SUM(amount) AS quantity FROM ledger WHERE (user=? AND symbol =? and status=0)", $id, $row["symbol"]);	  // query user's portfolio
        $stock["quantity"] = $stocksQ[0]["quantity"];
        $askQuantity =	query("SELECT SUM(quantity) AS quantity FROM orderbook WHERE (user=? AND symbol =? AND side='a')", $id, $row["symbol"]);	  // query user's portfolio
        $askQuantity = $askQuantity[0]["quantity"]; //shares trading
        $stock["locked"] = (int)$askQuantity;
        $stocks[] = $stock;
    }


    render("exchange_form.php", ["title" => "Exchange", "stocks" => $stocks, "assets" => $assets]); // render buy form
}
// apologize(var_dump(get_defined_vars())); //dump all variables if i hit error  
?>
