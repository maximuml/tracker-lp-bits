@php
echo '<form id="compose" method="post" action="' . e($formAction) . '">' . "\n";
echo '<input type="hidden" name="_token" value="' . e(csrf_token()) . '" />' . "\n";
if (! empty($parentId)) {
    echo '<input type="hidden" name="pid" value="' . $parentId . '" />' . "\n";
}
if (! empty($returnto)) {
    echo '<input type="hidden" name="returnto" value="' . e($returnto) . '" />' . "\n";
}
echo \App\Support\Frame::composeBegin($pageTitle, $composeType, e($body ?? ''), false, '', 100);
echo \App\Support\Frame::composeEnd();
echo '</form>';
@endphp
