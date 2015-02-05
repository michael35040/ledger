


<?php
$ledger = query("SELECT * FROM ledger");
//$ledger = query("SELECT * FROM ledger WHERE (user=?)", $id);
$units = query("SELECT SUM(amount) AS units FROM ledger WHERE (user=? AND symbol=?)", $user, $unittype);

echo $units[0]["units"];
?>


<table class="table table-striped table-condensed table-bordered" >
    <tr  class="success">
        <td colspan="3" style="font-size:20px; text-align: center;">LEDGER</td>
    </tr>
    <tr>
        <td><strong>UID</strong></td>
        <td><strong>Date</strong></td>
        <td><strong>Category</strong></td>
        <td><strong>User</strong></td>
        <td><strong>Symbol</strong></td>
        <td><strong>Amount</strong></td>
        <td><strong>Reference</strong></td>
        <td><strong>xUser</strong></td>
        <td><strong>xSymbol</strong></td>
        <td><strong>xAmount</strong></td>
        <td><strong>xReference</strong></td>
        <td><strong>Status</strong></td>
        <td><strong>Note</strong></td>
    </tr>
    <?php
    foreach ($ledger as $row)
    {
        $uid = $row["uid"];
        $date = $row["date"];// $date = date("Y,n,j", $row["date"]); //date('Y-m-d H:i:s', strtotime($row["date"]))     // $date = htmlspecialchars(date('Y-m-d H:i:s', strtotime($row["date"])));
        $category = $row["category"];
        $user = $row["user"];
        $symbol = strtoupper($row["symbol"]);
        $amount = getPrice($row["amount"]);
        $reference = $row["reference"];
        $xuser = $row["xuser"];
        $xsymbol = strtoupper($row["xsymbol"]);
        $xamount = getPrice($row["xamount"]);
        $xreference = $row["xreference"];
        $status = $row["status"];
        $note = $row["note"];


        ?>
        <tr>
            <td><?php echo(number_format($uid, 0, '.', ',')); ?></span></td>
            <td><?php echo(htmlspecialchars($date)); ?></td>
            <td><?php echo(htmlspecialchars($category)); ?></td>
            <td><?php echo(number_format($user, 0, '.', ',')); ?></span></td>
            <td><?php echo(htmlspecialchars($symbol)); ?></td>
            <td><?php echo(number_format($amount, 2, '.', ',')); ?></span></td>
            <td><?php echo(htmlspecialchars($reference)); ?></td>
            <td><?php echo(number_format($xuser, 0, '.', ',')); ?></span></td>
            <td><?php echo(htmlspecialchars($xsymbol)); ?></td>
            <td><?php echo(number_format($xamount, 2, '.', ',')); ?></span></td>
            <td><?php echo(htmlspecialchars($xreference)); ?></td>
            <td><?php echo(htmlspecialchars($status)); ?></td>
            <td><?php echo(htmlspecialchars($note)); ?></td>
        </tr>
    <?php
    }
    ?>

</table>




