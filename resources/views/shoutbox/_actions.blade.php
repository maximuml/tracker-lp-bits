<span class="shout-actions">@if ($canEdit) <a href="#" class="shout-action-edit" data-shout-edit="{{ $msgId }}" title="{{ __('shoutbox.title_edit_shout') }}">[{{ __('shoutbox.text_edit') }}]</a>@endif
@if ($canDelete) <a href="#" class="shout-action-del" data-shout-del="{{ $msgId }}" title="{{ __('shoutbox.title_delete_shout') }}">[{{ __('shoutbox.text_del') }}]</a>@endif</span>
