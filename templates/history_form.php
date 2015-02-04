
<table class="table table-condensed  table-bordered" >
    <tr   class="success" ><td colspan="4"  style="font-size:20px; text-align: center;">HISTORY (<?php echo(strtoupper($tabletitle)); ?>) &nbsp;
            <?php
            //	Display link to all history as long as your not already there
            if (isset($title))
            {
                if ($title !== "All History")
                {
                    echo('

                        <span class="input-group-btn" style="display:inline;">
    <form method="post" action="history.php"><button type="submit" class="btn btn-success btn-xs" name="history" value="all">
        <span class="glyphicon glyphicon-plus-sign"></span> Show All
    </button></form>
</span>

	');
                }
                else
                {
                    echo('

                        <span class="input-group-btn" style="display:inline;">
    <form method="post" action="history.php"><button type="submit" class="btn btn-success btn-xs" name="history" value="limit">
        <span class="glyphicon glyphicon-minus-sign"></span> Last 10
    </button></form>
</span>

	');
                }
            }

            ?>


        </td></tr> <!--blank row breaker-->


    <tr   class="active" >

        <th>UID</th>
        <th>Date/Time (Y/M/D)</th>
        <th>Category</th>
        <th>User</th>
        <th>Symbol</th>
        <th>Amount</th>
        <th>Reference</th>
        <th>X-User</th>
        <th>X-Symbol</th>
        <th>X-Amount</th>
        <th>X-Reference</th>
        <th>Status</th>
        <th>Note</th>
    </tr>

    <?php
    foreach ($history as $row)
    {
    	$price = getPrice($row["price"]);
    	$total = getPrice($row["total"]);

        echo("<tr>");
        echo("<td>" . htmlspecialchars($row["uid"]) . "</td>");
        echo("<td>" . htmlspecialchars(date('Y-m-d H:i:s',strtotime($row["date"]))) . "</td>");
        echo("<td>" . htmlspecialchars($row["category"]) . "</td>");
        echo("<td>" . htmlspecialchars($row["user"]) . "</td>");
        echo("<td>" . htmlspecialchars(strtoupper($row["symbol"])) . "</td>");
        echo("<td>" . $unitsymbol . (number_format($row["amount"],$decimalplaces,".",",")) . "</td>");
        echo("<td>" . htmlspecialchars($row["reference"]) . "</td>");
        echo("<td>" . htmlspecialchars($row["xuser"]) . "</td>");
        echo("<td>" . htmlspecialchars(strtoupper($row["xsymbol"])) . "</td>");
        echo("<td>" . $unitsymbol . (number_format($row["xamount"],$decimalplaces,".",",")) . "</td>");
        echo("<td>" . htmlspecialchars($row["xreference"]) . "</td>");
        echo("<td>" . htmlspecialchars($row["status"]) . "</td>");
        echo("<td>" . htmlspecialchars($row["note"]) . "</td>");
        echo("</tr>");
    }
    if($history==null){echo('<td colspan="13">None</td>');}
    ?>




</table>

