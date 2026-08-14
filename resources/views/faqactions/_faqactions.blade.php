<?php

if ($mode === 'edit'):
    ?>
    <h1 align="center">Edit Section or Item</h1>
    <?php if (empty($arr)): ?>
        <p>Invalid id</p>
    <?php elseif ($arr['type'] === 'item'): ?>
        <form method="post" action="faqactions.php?action=edititem">
            @csrf
            <table border="1" cellspacing="0" cellpadding="10" align="center">
            <tr><td>ID:</td><td><?php echo (int) $arr['id']; ?> <input type="hidden" name="id" value="<?php echo (int) $arr['id']; ?>" /></td></tr>
            <tr><td>Question:</td><td><input style="width: 600px;" type="text" name="question" value="<?php echo $arr['question']; ?>" /></td></tr>
            <tr><td style="vertical-align: top;">Answer:</td><td><textarea rows=20 style="width: 600px; height=600px;" name="answer"><?php echo $arr['answer']; ?></textarea></td></tr>
            <tr><td>Status:</td><td>
                <select name="flag" style="width: 110px;">
                    <option value="0" style="color: #FF0000;"<?php echo $arr['flag'] == 0 ? ' selected="selected"' : ''; ?>>Hidden</option>
                    <option value="1" style="color: #000000;"<?php echo $arr['flag'] == 1 ? ' selected="selected"' : ''; ?>>Normal</option>
                    <option value="2" style="color: #0000FF;"<?php echo $arr['flag'] == 2 ? ' selected="selected"' : ''; ?>>Updated</option>
                    <option value="3" style="color: #008000;"<?php echo $arr['flag'] == 3 ? ' selected="selected"' : ''; ?>>New</option>
                </select>
            </td></tr>
            <tr><td>Category:</td><td>
                <select style="width: 400px;" name="categ">
                    <?php foreach ($categories as $cat): ?>
                    <option value="<?php echo (int) $cat['link_id']; ?>"<?php echo $cat['link_id'] == $arr['categ'] ? ' selected="selected"' : ''; ?>><?php echo htmlspecialchars((string) $cat['question']); ?></option>
                    <?php endforeach; ?>
                </select>
            </td></tr>
            <tr><td colspan="2" align="center"><input type="submit" name="edit" value="Edit" style="width: 60px;"></td></tr>
            </table>
        </form>
    <?php elseif ($arr['type'] === 'categ'): ?>
        <form method="post" action="faqactions.php?action=editsect">
            @csrf
            <table border="1" cellspacing="0" cellpadding="10" align="center">
            <tr><td>ID:</td><td><?php echo (int) $arr['id']; ?> <input type="hidden" name="id" value="<?php echo (int) $arr['id']; ?>" /></td></tr>
            <tr><td>Language:</td><td><?php echo htmlspecialchars((string) $arr['lang_name']); ?></td></tr>
            <tr><td>Title:</td><td><input style="width: 300px;" type="text" name="title" value="<?php echo $arr['question']; ?>" /></td></tr>
            <tr><td>Status:</td><td>
                <select name="flag" style="width: 110px;">
                    <option value="0" style="color: #FF0000;"<?php echo $arr['flag'] == 0 ? ' selected="selected"' : ''; ?>>Hidden</option>
                    <option value="1" style="color: #000000;"<?php echo $arr['flag'] == 1 ? ' selected="selected"' : ''; ?>>Normal</option>
                </select>
            </td></tr>
            <tr><td colspan="2" align="center"><input type="submit" name="edit" value="Edit" style="width: 60px;"></td></tr>
            </table>
        </form>
    <?php endif;

elseif ($mode === 'confirm_delete'):
    ?>
    <h1 align="center">Confirmation required</h1>
    <table border="1" cellspacing="0" cellpadding="5" align="center" width="95%">
    <tr><td align="center">Please click <a href="faqactions.php?action=delete&id=<?php echo (int) $id; ?>&confirm=yes">here</a> to confirm.</td></tr>
    </table>
    <?php

elseif ($mode === 'additem'):
    ?>
    <h1 align="center">Add Item</h1>
    <form method="post" action="faqactions.php?action=addnewitem">
        @csrf
        <table border="1" cellspacing="0" cellpadding="10" align="center">
        <tr><td>Question:</td><td><input style="width: 600px;" type="text" name="question" value="" /></td></tr>
        <tr><td style="vertical-align: top;">Answer:</td><td><textarea rows=20 style="width: 600px; height=600px;" name="answer"></textarea></td></tr>
        <tr><td>Status:</td><td>
            <select name="flag" style="width: 110px;">
                <option value="0" style="color: #FF0000;">Hidden</option>
                <option value="1" style="color: #000000;">Normal</option>
                <option value="2" style="color: #0000FF;">Updated</option>
                <option value="3" style="color: #008000;" selected="selected">New</option>
            </select>
        </td></tr>
        <input type="hidden" name="categ" value="<?php echo (int) $inid; ?>">
        <input type="hidden" name="langid" value="<?php echo (int) $langid; ?>">
        <tr><td colspan="2" align="center"><input type="submit" value="Add" style="width: 60px;"></td></tr>
        </table>
    </form>
    <?php

elseif ($mode === 'addsection'):
    ?>
    <h1 align="center">Add Section</h1>
    <form method="post" action="faqactions.php?action=addnewsect">
        @csrf
        <table border="1" cellspacing="0" cellpadding="10" align="center">
        <tr><td>Title:</td><td><input style="width: 300px;" type="text" name="title" value="" /></td></tr>
        <tr><td>Language:</td><td>
            <select name="language">
                <?php foreach ($languages as $row): ?>
                <option value="<?php echo (int) $row['id']; ?>"<?php echo $row['site_lang_folder'] == $deflang ? ' selected' : ''; ?>><?php echo htmlspecialchars((string) $row['lang_name']); ?></option>
                <?php endforeach; ?>
            </select>
        </td></tr>
        <tr><td>Status:</td><td>
            <select name="flag" style="width: 110px;">
                <option value="0" style="color: #FF0000;">Hidden</option>
                <option value="1" style="color: #000000;" selected="selected">Normal</option>
            </select>
        </td></tr>
        <tr><td colspan="2" align="center"><input type="submit" name="edit" value="Add" style="width: 60px;"></td></tr>
        </table>
    </form>
    <?php
endif;

?>
