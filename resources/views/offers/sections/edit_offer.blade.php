<form id="compose" method="post" name="compose" action="/web/offers/edit">
@csrf
<input type="hidden" name="id" value="{{ $edit_offer['id'] }}" />
<div class="nx-fgrid nx-fgrid--flat w-[97%]">
<div class="nx-ffull nx-colhead text-center">{{ __('legacy/offers.text_edit_offer')}}</div>
<div class="nx-fhead">{{ __('legacy/offers.row_type')}}<span class="text-nxm-danger">*</span></div><div class="nx-fcell"><select name="category">
@foreach ($edit_offer['catOptions'] ?? [] as $opt)<option value="{{ $opt->id }}"{{ $opt->id === ($edit_offer['catId'] ?? 0) ? ' selected' : '' }}>{{ $opt->name }}</option>
@endforeach</select>
</div>
<div class="nx-fhead">{{ __('legacy/offers.row_title')}}<span class="text-nxm-danger">*</span></div><div class="nx-fcell"><input type="text" name="name" value="{{ $edit_offer['title'] }}" /></div>
<div class="nx-fhead">{{ __('legacy/offers.row_post_or_photo')}}</div><div class="nx-fcell"><input type="text" name="picture" value='' /><br />{{ __('legacy/offers.text_link_to_picture') }} <a href="tags.php" title="What is Tag?">{{ __('legacy/offers.text_tag') }}</a> {{ __('legacy/offers.text_link_to_picture_end') }}</div>
<div class="nx-fhead"><b>{{ __('legacy/offers.row_description')}}<span class="text-nxm-danger">*</span></b></div><div class="nx-fcell">
<livewire:bbcode-editor form="compose" text="body" :content="$edit_offer['bodyContent'] ?? ''" />
</div>
<div class="nx-ffull nx-toolbox text-center"><input id="qr" type="submit" value="{{ __('legacy/offers.submit_edit_offer')}}" class="btn" /></div>
</div></form><br />
