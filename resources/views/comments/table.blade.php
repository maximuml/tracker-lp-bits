{{-- Legacy commenttable() frame: two nested layout tables preserved as-is --}}
<table data-nx="data" role="presentation" class="main"><tr><td class="embedded" >
<table data-nx="data" role="presentation"><tr><td class="text" >
@foreach ($vm->rows as $row)
<div><table data-nx="data" role="presentation" id="cid{{ $row->id }}"><tr><td class="embedded nx-w-99p">#{{ $row->id }}&nbsp;&nbsp;<span class="nx-color-gray">{{ __('legacy/functions.text_by') }}</span>{{ $row->author }}&nbsp;&nbsp;<span class="nx-color-gray">{{ __('legacy/functions.text_at') }}</span>{{ $row->addedTime }}@if ($row->showViewOriginal) - [<a href="comment.php?action=vieworiginal&amp;cid={{ $row->id }}&amp;type={{ $vm->type }}">{{ __('legacy/functions.text_view_original') }}</a>]@endif</td><td class="embedded nowrap nx-w-1p"><a href="#top" title="Top" aria-label="Top">↑</a>&nbsp;&nbsp;</td></tr></table></div>
<table data-nx="data" role="presentation" class="main">
<tr>
<td class="rowfollow nx-va-top nx-w-150px">{{ $row->avatar }}</td>
<td class="rowfollow word-break-all nx-va-top"><br />{{ $row->text }}@if ($row->editedBy)<br /><p><span class="small">{{ __('legacy/functions.text_last_edited_by') }}{{ $row->editedBy }}{{ __('legacy/functions.text_edited_at') }}{{ $row->editedAt }}</span></p>
@endif</td>
</tr>
<tr><td class="toolbox"><span class="nx-cmt-tools"><span class="nx-post__status @if ($row->online) nx-post__status--on @endif" title="{{ $row->online ? __('legacy/functions.title_online') : __('legacy/functions.title_offline') }}"></span><a class="nx-postbtn" href="sendmessage.php?receiver={{ $row->userId }}" title="{{ $row->pmTitle }}">PM</a><a class="nx-postbtn" href="report.php?commentid={{ $row->id }}" title="{{ $vm->reportTitle }}">Report</a></span></td><td class="toolbox nx-align-right"><span class="nx-cmt-tools nx-cmt-tools--right"><a class="nx-postbtn" href="comment.php?action=add&amp;sub=quote&amp;cid={{ $row->id }}&amp;pid={{ $vm->parentId }}&amp;type={{ $vm->type }}" title="{{ __('legacy/functions.title_reply_with_quote') }}">Quote</a><a class="nx-postbtn" href="comment.php?action=add&amp;pid={{ $vm->parentId }}&amp;type={{ $vm->type }}" title="{{ $vm->replyTitle }}">Reply</a>@if ($row->canDelete)<a class="nx-postbtn nx-postbtn--danger" href="comment.php?action=delete&amp;cid={{ $row->id }}&amp;type={{ $vm->type }}" title="{{ __('legacy/functions.title_delete') }}">Delete</a>@endif @if ($row->canEdit)<a class="nx-postbtn" href="comment.php?action=edit&amp;cid={{ $row->id }}&amp;type={{ $vm->type }}" title="{{ __('legacy/functions.title_edit') }}">Edit</a>@endif</span></td>
</tr></table>
@endforeach
</td></tr></table>
</td></tr></table>
