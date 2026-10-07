{{-- Legacy commenttable() frame: two nested layout tables preserved as-is --}}
<x-data-table role="presentation" class="nx-main"><tr><td class="nx-embedded" >
<x-data-table role="presentation"><tr><td class="text" >
@foreach ($vm->rows as $row)
<div><x-data-table role="presentation" id="cid{{ $row->id }}"><tr><td class="nx-embedded w-[99%]">#{{ $row->id }}&nbsp;&nbsp;<span class="text-nxm-text-dim">{{ __('functions.text_by') }}</span>{{ $row->author }}&nbsp;&nbsp;<span class="text-nxm-text-dim">{{ __('functions.text_at') }}</span>{{ $row->addedTime }}@if ($row->showViewOriginal) - [<a href="/comment?action=vieworiginal&amp;cid={{ $row->id }}&amp;type={{ $vm->type }}">{{ __('functions.text_view_original') }}</a>]@endif</td><td class="nx-embedded whitespace-nowrap w-[1%]"><a href="#top" title="Top" aria-label="Top">↑</a>&nbsp;&nbsp;</td></tr></x-data-table></div>
<x-data-table role="presentation" class="nx-main">
<tr>
<td class="align-top w-[9.375rem]">{{ $row->avatar }}</td>
<td class="align-top break-all"><br />{{ $row->text }}@if ($row->editedBy)<br /><p><span class="small">{{ __('functions.text_last_edited_by') }}{{ $row->editedBy }}{{ __('functions.text_edited_at') }}{{ $row->editedAt }}</span></p>
@endif</td>
</tr>
<tr><td class="toolbox"><span class="nx-cmt-tools"><span class="nx-post__status @if ($row->online) nx-post__status--on @endif" title="{{ $row->online ? __('functions.title_online') : __('functions.title_offline') }}"></span><a class="nx-postbtn" href="/web/sendmessage?receiver={{ $row->userId }}" title="{{ $row->pmTitle }}">PM</a><a class="nx-postbtn" href="/web/report?commentid={{ $row->id }}" title="{{ $vm->reportTitle }}">Report</a></span></td><td class="toolbox text-right"><span class="nx-cmt-tools nx-cmt-tools--right"><a class="nx-postbtn" href="/comment/add?amp;sub=quote&amp;cid={{ $row->id }}&amp;pid={{ $vm->parentId }}&amp;type={{ $vm->type }}" title="{{ __('functions.title_reply_with_quote') }}">Quote</a><a class="nx-postbtn" href="/comment/add?amp;pid={{ $vm->parentId }}&amp;type={{ $vm->type }}" title="{{ $vm->replyTitle }}">Reply</a>@if ($row->canDelete)<a class="nx-postbtn nx-postbtn--danger" href="/comment?action=delete&amp;cid={{ $row->id }}&amp;type={{ $vm->type }}" title="{{ __('functions.title_delete') }}">Delete</a>@endif @if ($row->canEdit)<a class="nx-postbtn" href="/comment?action=edit&amp;cid={{ $row->id }}&amp;type={{ $vm->type }}" title="{{ __('functions.title_edit') }}">Edit</a>@endif</span></td>
</tr></x-data-table>
@endforeach
</td></tr></x-data-table>
</td></tr></x-data-table>
