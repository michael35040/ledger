<?php

// configuration
require("../includes/config.php");

$allAssets =	query("SELECT * FROM assets ORDER BY symbol ASC");

$assets = []; //to send to next page

$indexMarketCap = 0;
$indexValue = 0;
$timeframe=0;
foreach ($allAssets as $row)		// for each of user's stocks
{
    $asset = [];
    $asset["symbol"] = $row["symbol"]; //set variable from stock info
    $asset["name"] = $row["name"]; //set variable from stock info
    $asset["date"] = $row["date"]; //date listed on exchange
    $asset["userid"] = $row["userid"];
    $asset["fee"] = $row["fee"];
    $asset["issued"] = $row["issued"]; //shares issued

    //    $public =	query("SELECT SUM(quantity) AS quantity FROM portfolio WHERE symbol =?", $asset["symbol"]);	  // query user's portfolio
        if(empty($public[0]["quantity"])){$public[0]["quantity"]=0;}
        $publicQuantity = $public[0]["quantity"]; //shares held
        $askQuantity =	query("SELECT SUM(quantity) AS quantity FROM orderbook WHERE symbol =? AND side='a'", $asset["symbol"]);	  // query user's portfolio
        $askQuantity = $askQuantity[0]["quantity"]; //shares trading
    $asset["public"] = $askQuantity+$publicQuantity;

    $asset["url"] = $row["url"]; //webpage
    $asset["type"] = $row["type"]; //type of asset (stock, currency, commodity)
    $asset["rating"] = $row["rating"]; //my rating
    $asset["description"] = $row["description"]; //description of asset
        $bid =	query("SELECT price FROM orderbook WHERE (symbol = ? AND side = ? AND type = 'limit') ORDER BY price DESC, uid ASC LIMIT 0, 1", $asset["symbol"], 'b');
        if(empty($bid)){$bid=0;}
    $asset["bid"] = getPrice($bid[0]["price"]); //stock price per share
        $ask =	query("SELECT price FROM orderbook WHERE (symbol = ? AND side = ? AND type = 'limit') ORDER BY price ASC, uid ASC LIMIT 0, 1", $asset["symbol"], 'a');
        if(empty($ask)){$ask=0;}
    $asset["ask"] = getPrice($ask[0]["price"]); //stock price per share



    //apologize(var_dump(get_defined_vars()));

}

// render portfolio (pass in new portfolio table and cash)
render("assets_form.php",
    [   "title" => "Assets",
        "assets" => $assets,
        "indexMarketCap" => $indexMarketCap,
        "indexValue" => $indexValue]);

?>
