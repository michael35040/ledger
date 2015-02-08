TO DO:
Orders-Remove from Ledger and add to order. WHen Order is fullfilled, add it to the new user on ledger. Sync using same ref number.

use grains (480 grains to ounce).

have 4 main metals for assets (gold, silver, platinum, pallidium).


=========
LEDGER
--------
                                                  to calculate    to calculate
                                                     total           cost
                                        |--------------------|  |---------------------|
uid     date    reference   category    user	asset   amount  xuser	xasset  xamount status      note
1       1/1     1           trade       1	usd     +100    2	btc     -1      cleared 
2       1/1     1           trade       2	usd     -100    1	btc     +1      cleared 
3       1/1     1           trade       1	btc     +1      2	usd     -100    cleared 
4       1/1     1           trade       2	btc     -1      1	usd     +100    cleared 
    

=======
SYMBOLS
-------

billio: money of copper, or copper and silver; bullion.
nigra moneta: copper money.
blanci: white money, sterling.
cambitor: an exchanger, a moneyer.
cambium: an exchange; a mint.
chevantia: a loan of money.
chevicia: a loan.
escambiator: an exchanger, money changer.
escambio, escambium: exchange; a place for changing money.
moneta: money; a mint.
monetacio: coining, minting.
monetagium: mintage, right of coining; tribute paid by tenants to the lord that he should not change the money he had coined.
monetare: to coin; to mint.
monetarius: a moneyer; a coiner; a banker.
monetatio: coining.
monetum: a mint.



Silver Bullion .999 Pure -1ozt (Will be settled via bar and/or rounds in varying sizes according to order)
Silver Bullion 1oz Rounds 
Silver Government Coin .999 Pure (Mexico Libertad, Austria Philharmonic, Australia)
Silver Coin .90 Pure  -$1 face
Silver American Eagle
Silver Canadian Maple 

B-Bar
R-Round

G-Au
S-Ag

Roman:
I -1
V -5
X -10
L -50
C -100
D -500
M -1000

Various Types Nucl:
G Am Eagle 1oz
GAE .1oz
American Gold Buffalo
Chinese Panda Gold
GCM -can mapl
Gold Bar
G Krugerrand
SAE
SAE 500
Austrian Silver Philharmonic
Bullion Bar 100oz
Bullion Bar 10oz
Can Maple Platinum
Pallidum Bullion
Gold Bullion Bar 100g
Silver Bullion Bar 100oz
Canada Maple Leaf Silver




=============
CALCULATING PORTFOLIO
-------------
$qty1 = query("SELECT SUM(qty1) AS qty, SUM(units1) AS cost FROM ledger WHERE (user1=? AND asset1=?)", $user, $asset);
$qty2 = query("SELECT SUM(qty2) AS qty, SUM(units2) AS cost FROM ledger WHERE (user2=? AND asset2=?)", $user, $asset);
$quantity = $qty1[0]['qty']+$qty2[0]['qty'];
$cost =     $qty1[0]['cost']+$qty2[0]['cost'];

=============
CALCULATING UNITS
-------------
$units1 = query("SELECT SUM(units1) AS units1 FROM ledger WHERE (user1=?)", $user);
$units2 = query("SELECT SUM(units2) AS units2 FROM ledger WHERE (user2=?)", $user);
$units = $units1+$units2;

=============
SHOW LEDGER
-------------
$ledger = query("SELECT * FROM ledger WHERE (user1=? OR user2=?)", $user,$user);


==========================================
WEBSITE
-------------------------------------------------
../includes/FUNCTIONS.php
    -all functions

../includes/CONSTANTS.php
    -all the constants

../includes/DB.php
    -connecting to the database

---------------------------------------------
INDEX.php
    -basic information
    -register
    -login

LOGIN.php
    -login

REGISTER.php
    -register
    
HEADER.php
    -menu.php
    
FOOTER.php
    -copyright, etc.
--------------------------------------------    
ADMIN.php
    -user control
    
EXCHANGE.php
    -buy, sell

ACCOUNT.php
    -current info (email, phone, etc)
    -update info
    -withdraw
    -deposit

PORTFOLIO.php
    -show units
    -show all assets
        -quantity
        -cost
        -current value (based on highest bid price)
    
LEDGER.php
    -show ledger of user (user1 OR user2)
    -filter based on (trades, deposit, withdraw)
    -sort based on qty, amt, date
    
ORDERS.php
    -show open orders of user

ASSETS.php
    -info
    -ownership
    -include orderbook.php
    -include trades.php

----------------------------------------
ORDERBOOK.php    
    -orderbook boilerplate (either for asset or user)

TRADES.php
    -trades boilerplate (either for asset or user)
----------------------------------------------    

=========================================
SITE DESIGN
-----------------------------------------
                INDEX (info, login, register)
                



                 ACCOUNT
          /     |      \      \    
        /       |        \      \
    INFO    PORTFOLIO   LEDGER  ORDERS    
                
                

              ASSETS
         /      |       \           
        /       |        \        
     INFO  ORDERBOOK   TRADES             
               
