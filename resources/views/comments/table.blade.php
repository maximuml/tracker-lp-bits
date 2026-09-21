{{-- Legacy commenttable() frame: two nested layout tables preserved as-is --}}
<table data-nx="data" class="main"><tr><td class="embedded" >
<table data-nx="data"><tr><td class="text" >
@foreach ($vm->rows as $row)
<div><table data-nx="data" id="cid{{ $row->id }}"><tr><td class="embedded nx-w-99p">#{{ $row->id }}&nbsp;&nbsp;<span class="nx-color-gray">{{ __('legacy/functions.text_by') }}</span>{{ $row->author }}&nbsp;&nbsp;<span class="nx-color-gray">{{ __('legacy/functions.text_at') }}</span>{{ $row->addedTime }}@if ($row->showViewOriginal) - [<a href="comment.php?action=vieworiginal&amp;cid={{ $row->id }}&amp;type={{ $vm->type }}">{{ __('legacy/functions.text_view_original') }}</a>]@endif</td><td class="embedded nowrap nx-w-1p"><a href="#top"><img class="top" src="pic/trans.gif" alt="Top" title="Top" /></a>&nbsp;&nbsp;</td></tr></table></div>
<table data-nx="data" class="main">
<tr>
<td class="rowfollow nx-va-top nx-w-150px">{{ $row->avatar }}</td>
<td class="rowfollow word-break-all nx-va-top"><br />{{ $row->text }}@if ($row->editedBy)<br /><p><span class="small">{{ __('legacy/functions.text_last_edited_by') }}{{ $row->editedBy }}{{ __('legacy/functions.text_edited_at') }}{{ $row->editedAt }}</span></p>
@endif</td>
</tr>
<tr><td class="toolbox"> <img class="{{ $row->online ? 'f_online' : 'f_offline' }}" src="pic/trans.gif" alt="{{ $row->online ? 'Online' : 'Offline' }}" title="{{ $row->online ? __('legacy/functions.title_online') : __('legacy/functions.title_offline') }}" /><a href="sendmessage.php?receiver={{ $row->userId }}"><img class="f_pm" src="pic/trans.gif" alt="PM" title="{{ $row->pmTitle }}" /></a><a href="report.php?commentid={{ $row->id }}"><img class="f_report" src="pic/trans.gif" alt="Report" title="{{ $vm->reportTitle }}" /></a></td><td class="toolbox nx-align-right"><a href="comment.php?action=add&amp;sub=quote&amp;cid={{ $row->id }}&amp;pid={{ $vm->parentId }}&amp;type={{ $vm->type }}"><img class="f_quote" src="pic/trans.gif" alt="Quote" title="{{ __('legacy/functions.title_reply_with_quote') }}" /></a><a href="comment.php?action=add&amp;pid={{ $vm->parentId }}&amp;type={{ $vm->type }}"><img class="f_reply" src="pic/trans.gif" alt="Add Reply" title="{{ $vm->replyTitle }}" /></a>@if ($row->canDelete)<a href="comment.php?action=delete&amp;cid={{ $row->id }}&amp;type={{ $vm->type }}"><img class="f_delete" src="pic/trans.gif" alt="Delete" title="{{ __('legacy/functions.title_delete') }}" /></a>@endif @if ($row->canEdit)<a href="comment.php?action=edit&amp;cid={{ $row->id }}&amp;type={{ $vm->type }}"><img class="f_edit" src="pic/trans.gif" alt="Edit" title="{{ __('legacy/functions.title_edit') }}" /></a>@endif</td>
</tr></table>
@endforeach
</td></tr></table>
</td></tr></table>
