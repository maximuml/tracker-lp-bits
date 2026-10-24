<form id="compose" method="post" action="{{ $formAction }}">
	@csrf
	@if (! empty($parentId))
		<input type="hidden" name="pid" value="{{ $parentId }}" />
	@endif
	@if (! empty($returnto))
		<input type="hidden" name="returnto" value="{{ $returnto }}" />
	@endif
	<x-compose :title="new \Illuminate\Support\HtmlString($pageTitle)" :type="$composeType" :body="$body ?? ''" :has-subject="false" />
</form>
