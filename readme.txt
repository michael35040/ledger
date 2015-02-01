
=========
LEDGER
--------
                                                  to calculate    to calculate
                                                     total           cost
                                                |------------|  |-------------|
uid     date    payee   reference   category    asset   amount  xasset  xamount status      note
1       1/1     1       1           trade       usd     +100    btc     -1      cleared 
2       1/1     2       1           trade       usd     -100    btc     +1      cleared 
3       1/1     1       1           trade       btc     +1      usd     -100    cleared 
4       1/1     2       1           trade       btc     -1      usd     +100    cleared 
       
-- --------------------------------------------------------

--
-- Table structure for table `ledger`
--      


DROP TABLE IF EXISTS `ledger`;
CREATE TABLE IF NOT EXISTS `ledger` (
  `uid` int(10) NOT NULL AUTO_INCREMENT COMMENT 'unique id',
  `date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `user` int(9) NOT NULL COMMENT 'payee user id',
  `category` varchar(32) NOT NULL COMMENT 'trade, transfer, deposit, withdraw',
  `symbol` varchar(10) NOT NULL COMMENT 'asset symbol',
  `amount` int(20) NOT NULL COMMENT 'positive or negative sign',
  `reference` varchar(32) NOT NULL COMMENT 'bid or ask uid or hash to group a trade of 4 entries',
  `xsymbol` varchar(10) NOT NULL COMMENT 'FOR COST counter symbol',
  `xamount` int(20) NOT NULL COMMENT 'FOR COST counter positive or negative sign',
  `xreference` varchar(32) NOT NULL COMMENT 'bid or ask uid or hash to group a trade of 4 entries',
  `xuser` int(9) NOT NULL COMMENT 'counter payer user id',
  `status` varchar(32) NOT NULL COMMENT 'cleared, pending, canceled',
  `note` varchar(32) NOT NULL COMMENT 'if canceled-reason',
  PRIMARY KEY (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 ;

--
-- Truncate table before insert `ledger`
--

TRUNCATE TABLE `ledger`;
-- --------------------------------------------------------





-- --------------------------------------------------------

--
-- Table structure for table `orders`
--       


DROP TABLE IF EXISTS `orders`;
CREATE TABLE IF NOT EXISTS `orders` (
  `uid` int(10) NOT NULL AUTO_INCREMENT COMMENT 'unique id',
  `date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `symbol` varchar(10) NOT NULL,
  `side` varchar(1) NOT NULL COMMENT 'a:ask or b:bid',
  `type` varchar(6) NOT NULL COMMENT 'limit or market',
  `price` bigint(20) unsigned NOT NULL,
  `original` int(20) unsigned NOT NULL COMMENT 'original quantity',
  `quantity` int(20) NOT NULL COMMENT 'remaining quantity of order',
  `user` int(9) NOT NULL COMMENT 'user id',
  `status` varchar(10) NOT NULL COMMENT 'open closed canceled',
  PRIMARY KEY (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 ;

--
-- Truncate table before insert `orders`
--

TRUNCATE TABLE `orders`;
-- --------------------------------------------------------




-- --------------------------------------------------------

--
-- Table structure for table `assets`
--

DROP TABLE IF EXISTS `assets`;
CREATE TABLE IF NOT EXISTS `assets` (
  `uid` int(10) NOT NULL AUTO_INCREMENT COMMENT 'unique assets id',
  `symbol` varchar(10) NOT NULL COMMENT 'ticker',
  `name` varchar(63) NOT NULL,
  `date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'listed on',
  `issued` int(11) NOT NULL COMMENT 'shares issued ie 20k',
  `type` varchar(63) NOT NULL COMMENT 'shares or commodity',
  `fee` decimal(65,30) DEFAULT NULL COMMENT 'listing fee of exchange',
  `userid` int(10) DEFAULT NULL COMMENT 'user id',
  `url` varchar(63) DEFAULT NULL COMMENT 'webpage',
  `rating` int(11) DEFAULT NULL COMMENT '4 stars or white',
  `description` varchar(999) DEFAULT NULL,
  PRIMARY KEY (`uid`),
  UNIQUE KEY `symbol` (`symbol`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 ;

--
-- Truncate table before insert `assets`
--

TRUNCATE TABLE `assets`;
-- --------------------------------------------------------






-- --------------------------------------------------------

--
-- Table structure for table `login`
--

DROP TABLE IF EXISTS `login`;
CREATE TABLE IF NOT EXISTS `login` (
  `uid` int(10) NOT NULL AUTO_INCREMENT COMMENT 'unique id',
  `id` int(10) NOT NULL COMMENT 'user id',
  `date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ip` varchar(15) NOT NULL,
  `success_fail` varchar(1) NOT NULL,
  PRIMARY KEY (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 ;

--
-- Truncate table before insert `login`
--

TRUNCATE TABLE `login`;
-- --------------------------------------------------------




-- --------------------------------------------------------

--
-- Table structure for table `notification`;
--

DROP TABLE IF EXISTS `notification`;
CREATE TABLE IF NOT EXISTS `notification` (
  `uid` int(10) NOT NULL AUTO_INCREMENT COMMENT 'unique error id',
  `id` int(10) unsigned NOT NULL COMMENT 'user id',
  `date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `type` varchar(100) NOT NULL COMMENT 'short description',
  `description` varchar(255) NOT NULL COMMENT 'longer description',
  `status` int(1) NOT NULL COMMENT '1 open or 0 close',
  PRIMARY KEY (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 ;

--
-- Truncate table before insert `notification`;
--

TRUNCATE TABLE `notification`;
-- --------------------------------------------------------







-- --------------------------------------------------------

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
CREATE TABLE IF NOT EXISTS `users` (
  `id` int(10) NOT NULL AUTO_INCREMENT COMMENT 'user id',
  `email` varchar(63) NOT NULL,
  `fname` varchar(63) NOT NULL,
  `lname` varchar(63) NOT NULL,
  `birth` date NOT NULL,
  `address` varchar(63) NOT NULL,
  `city` varchar(63) NOT NULL,
  `region` varchar(63) NOT NULL,
  `zip` int(20) NOT NULL,
  `phone` int(20) NOT NULL,
  `question` varchar(63) NOT NULL,
  `answer` varchar(63) NOT NULL,
  `password` char(128) NOT NULL,
  `registered` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_login` timestamp NULL DEFAULT NULL,
  `ip` varchar(15) NOT NULL,
  `fails` int(1) NOT NULL DEFAULT '0' COMMENT 'failed login attempts',
  `active` int(1) NOT NULL DEFAULT '0' COMMENT '0 inactive or 1 active',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 ;

--
-- Truncate table before insert `users`
--

TRUNCATE TABLE `users`;
--
-- Dumping data for table `users`
--

INSERT INTO `users` (`email`, `fname`, `lname`, `birth`, `address`, `city`, `region`, `zip`, `phone`, `question`, `answer`, `password`, `registered`, `last_login`, `ip`, `fails`, `active`) VALUES
('a@pulwar.com', 'a', 'pulwar', '2014-05-04', 'pulwar st 12 po #box 123', 'CityofPulwar', 'IA', 111112, 12, 'What?', 'Yeah!', '$2a$11$mSIPrGz706xUee70qha1NeWEZ/CR/.ufGS1uzTzr5wsQHApBx6Vz2', '2014-11-07 07:00:00', '2014-12-01 18:02:25', '143.85.101.19', 0, 1);

-- --------------------------------------------------------












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
               
