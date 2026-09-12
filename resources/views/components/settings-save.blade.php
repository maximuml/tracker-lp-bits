@props(['label', 'text' => 'Save'])
<tr>
    <td class="rowhead nowrap" valign="top" align="right">{{ $label }}</td>
    <td class="rowfollow" valign="top" align="left"><input type="submit" name="save" value="{{ $text }}"></td>
</tr>
