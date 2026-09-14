<form id="compose" method="post" action="{{ $formAction }}">
	@csrf
	@if (! empty($parentId))
		<input type="hidden" name="pid" value="{{ $parentId }}" />
	@endif
	@if (! empty($returnto))
		<input type="hidden" name="returnto" value="{{ $returnto }}" />
	@endif
	@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml(\App\Support\Frame::composeBegin(new \Illuminate\Support\HtmlString($pageTitle), $composeType, e($body ?? ''), false, '', 100)))
	@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml(\App\Support\Frame::composeEnd()))
</form>
