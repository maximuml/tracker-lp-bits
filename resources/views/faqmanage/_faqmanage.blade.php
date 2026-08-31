
<h1 align="center">FAQ Management</h1>
<form method="post" action="faqactions.php?action=reorder">
@csrf
<?php
foreach ($faqCateg as $lang => $temp2) {
    foreach ($temp2 as $id => $temp) {
        echo '<br />' . "\n";
        echo '<table border="1" cellspacing="0" cellpadding="5" align="center" width="95%">' . "\n";
        echo '<tr><td class="colhead" align="center" colspan="2">Position</td><td class="colhead" align="left">Section/Item Title</td><td class="colhead" align="center">Language</td><td class="colhead" align="center">Status</td><td class="colhead" align="center">Actions</td></tr>' . "\n";

        $itemCount = count($temp2);
        echo '<tr><td align="center" width="40px"><select name="order[' . (int) $id . ']">';
        for ($n = 1; $n <= $itemCount; $n++) {
            $sel = ($n == $temp['order']) ? ' selected="selected"' : '';
            echo '<option value="' . $n . '"' . $sel . '>' . $n . '</option>';
        }
        $status = ($temp['flag'] == "0") ? '<font color="red">Hidden</font>' : 'Normal';
        echo '</select></td><td align="center" width="40px">&nbsp;</td><td><b>' . htmlspecialchars((string) $temp['title'], ENT_QUOTES | ENT_HTML5 | ENT_SUBSTITUTE, 'UTF-8') . '</b></td><td align="center" width="60px">' . htmlspecialchars((string) $temp['lang_name'], ENT_QUOTES | ENT_HTML5 | ENT_SUBSTITUTE, 'UTF-8') . '</td><td align="center" width="60px">' . $status . '</td><td align="center" width="60px"><a href="faqactions.php?action=edit&id=' . (int) $temp['id'] . '">Edit</a> <a href="faqactions.php?action=delete&id=' . (int) $temp['id'] . '">Delete</a></td></tr>' . "\n";

        if (array_key_exists('items', $temp) && is_array($temp['items'])) {
            foreach ($temp['items'] as $id2 => $tempItem) {
                $subCount = count($temp['items']);
                echo '<tr><td align="center" width="40px">&nbsp;</td><td align="center" width="40px"><select name="order[' . (int) $id2 . ']">';
                for ($n = 1; $n <= $subCount; $n++) {
                    $sel = ($n == $tempItem['order']) ? ' selected="selected"' : '';
                    echo '<option value="' . $n . '"' . $sel . '>' . $n . '</option>';
                }
                if ($tempItem['flag'] == "0") $status = '<font color="#FF0000">Hidden</font>';
                elseif ($tempItem['flag'] == "2") $status = '<font color="#0000FF"><img src="pic/updated.png" alt="Updated" width="46" height="11" align="absbottom"></font>';
                elseif ($tempItem['flag'] == "3") $status = '<font color="#008000"><img src="pic/new.png" alt="New" width="27" height="11" align="absbottom"></font>';
                else $status = 'Normal';
                echo '</select></td><td>' . htmlspecialchars((string) $tempItem['question'], ENT_QUOTES | ENT_HTML5 | ENT_SUBSTITUTE, 'UTF-8') . '</td><td align="center"></td><td align="center" width="60px">' . $status . '</td><td align="center" width="60px"><a href="faqactions.php?action=edit&id=' . (int) $id2 . '">Edit</a> <a href="faqactions.php?action=delete&id=' . (int) $id2 . '">Delete</a></td></tr>' . "\n";
            }
        }

        echo '<tr><td colspan="6" align="center"><a href="faqactions.php?action=additem&inid=' . (int) $id . '&langid=' . (int) $lang . '">Add new item</a></td></tr>' . "\n";
        echo '</table>' . "\n";
    }
}

if (! empty($faqOrphaned)) {
    echo '<br />' . "\n";
    echo '<table border="1" cellspacing="0" cellpadding="5" align="center" width="95%">' . "\n";
    echo '<tr><td align="center" colspan="3"><b style="color: #FF0000">Orphaned Items</b></td></tr>' . "\n";
    echo '<tr><td class="colhead" align="left">Item Title</td><td class="colhead" align="center">Status</td><td class="colhead" align="center">Actions</td></tr>' . "\n";
    foreach ($faqOrphaned as $lang => $temp2) {
        foreach ($temp2 as $id => $temp) {
            if ($temp['flag'] == "0") $status = '<font color="#FF0000">Hidden</font>';
            elseif ($temp['flag'] == "2") $status = '<font color="#0000FF">Updated</font>';
            elseif ($temp['flag'] == "3") $status = '<font color="#008000">New</font>';
            else $status = 'Normal';
            echo '<tr><td>' . htmlspecialchars((string) $temp['question'], ENT_QUOTES | ENT_HTML5 | ENT_SUBSTITUTE, 'UTF-8') . '</td><td align="center" width="60px">' . $status . '</td><td align="center" width="60px"><a href="faqactions.php?action=edit&id=' . (int) $id . '">edit</a> <a href="faqactions.php?action=delete&id=' . (int) $id . '">delete</a></td></tr>' . "\n";
        }
    }
    echo '</table>' . "\n";
}
?>
<br />
<table border="1" cellspacing="0" cellpadding="5" align="center" width="95%">
    <tr><td align="center"><a href="faqactions.php?action=addsection">Add new section</a></td></tr>
</table>
<p align="center"><input type="submit" name="reorder" value="Reorder"></p>
</form>
<p>When the position numbers don't reflect the position in the table, it means the order id is bigger than the total number of sections/items and you should check all the order id's in the table and click "reorder"</p>

